#!/usr/bin/env python3
"""Isolated, destructive-on-own-data integration tests. Python standard library only."""
import argparse
import json
import os
import sys
import uuid
import urllib.request
import urllib.error

class Suite:
    def __init__(self, base, users):
        self.base = base.rstrip('/')
        self.users = users
        self.tokens = {}
        self.results = []
        self.created = {'subreddits': [], 'posts': [], 'comments': []}
        self.suffix = uuid.uuid4().hex[:10]

    def request(self, method, path, role=None, data=None, upload=None):
        headers = {'Accept': 'application/json'}
        if role:
            headers['Authorization'] = 'Bearer ' + self.tokens[role]
        payload = None
        if upload:
            field, filename, content, mimetype = upload
            boundary = '----apitest' + self.suffix
            payload = (('--' + boundary + '\r\nContent-Disposition: form-data; name="' + field + '"; filename="' + filename + '"\r\nContent-Type: ' + mimetype + '\r\n\r\n').encode() + content + ('\r\n--' + boundary + '--\r\n').encode())
            headers['Content-Type'] = 'multipart/form-data; boundary=' + boundary
        elif data is not None:
            payload = json.dumps(data).encode('utf-8')
            headers['Content-Type'] = 'application/json'
        req = urllib.request.Request(self.base + path, data=payload, headers=headers, method=method)
        try:
            with urllib.request.urlopen(req, timeout=15) as response:
                status, raw = response.status, response.read(1000000)
        except urllib.error.HTTPError as error:
            status, raw = error.code, error.read(1000000)
        except Exception as error:
            return 0, {'connection_error': str(error)}
        try:
            return status, json.loads(raw)
        except (ValueError, UnicodeDecodeError):
            return status, {'non_json': raw[:200].decode('utf-8', 'replace')}

    def check(self, label, method, path, expected, role=None, data=None, upload=None):
        status, body = self.request(method, path, role, data, upload)
        valid = status in expected and isinstance(body, dict) and 'connection_error' not in body and 'non_json' not in body
        if status < 400 and valid:
            valid = (set(body) == {'data', 'meta', 'message'}
                     and (body['meta'] is None or isinstance(body['meta'], dict))
                     and (body['message'] is None or isinstance(body['message'], str)))
        if status >= 400 and valid:
            error = body.get('error')
            valid = isinstance(error, dict) and error.get('status') == status and isinstance(error.get('message'), str) and isinstance(error.get('code'), str)
        result = 'PASS' if valid else 'FAIL'
        self.results.append({'test': label, 'result': result, 'status': status, 'expected': expected, 'response': self.redact(body) if not valid else None})
        print(f'{result:4}  {label} (HTTP {status})')
        # Keep lifecycle checks using their original field names while validating
        # the real Stage 8 envelope on every successful HTTP response.
        if valid and status < 400:
            payload = body['data']
            view = dict(payload) if isinstance(payload, dict) else {'data': payload}
            if isinstance(body['meta'], dict):
                view.update(body['meta'])
            if body['message'] is not None:
                view['message'] = body['message']
            return valid, view
        return valid, body

    @staticmethod
    def redact(body):
        if not isinstance(body, dict):
            return None
        def clean(value):
            if isinstance(value, dict):
                return {k: ('[REDACTED]' if k.lower() in ('token', 'access_token', 'refresh_token', 'password') else clean(v)) for k, v in value.items()}
            if isinstance(value, list):
                return [clean(v) for v in value]
            return value
        return clean(body)

    def skip(self, label, reason):
        self.results.append({'test': label, 'result': 'SKIP', 'reason': reason})
        print(f'SKIP  {label}: {reason}')

    def login(self):
        for role in ('user_a', 'user_b', 'admin'):
            account = self.users.get(role, {})
            if not account.get('login') or not account.get('password'):
                self.skip(role + ' login', 'missing credentials')
                continue
            ok, body = self.check(role + ' login', 'POST', '/api/auth/login', [200], data={'login': account['login'], 'password': account['password']})
            if ok and isinstance(body.get('token'), str):
                self.tokens[role] = body['token']
                self.check(role + ' profile', 'GET', '/api/auth/me', [200], role=role)
            else:
                self.skip(role + ' protected tests', 'login did not return a token')
        return all(role in self.tokens for role in ('user_a', 'user_b', 'admin'))

    def run(self):
        if not self.login():
            self.skip('Resource integration suite', 'all three accounts are required')
            return
        a, b, admin = 'user_a', 'user_b', 'admin'
        self.check('Guest cannot create subreddit', 'POST', '/api/subreddits', [401], data={'title': 'apitest', 'description': 'test'})
        self.check('User cannot access admin reports', 'GET', '/api/admin/reports', [403], role=a)
        self.check('User cannot access admin users', 'GET', '/api/admin/users', [403], role=b)
        self.check('Admin lists reports', 'GET', '/api/admin/reports', [200], role=admin)
        self.check('Admin lists posts', 'GET', '/api/admin/posts', [200], role=admin)
        self.check('Admin lists comments', 'GET', '/api/admin/comments', [200], role=admin)
        self.check('Admin lists subreddits', 'GET', '/api/admin/subreddits', [200], role=admin)
        self.check('Authenticated feed', 'GET', '/api/posts/feed?page=1&limit=2', [200], role=a)
        self.check('Invalid subreddit rejected', 'POST', '/api/subreddits', [422], role=a, data={'title': '!', 'description': ''})
        title = 'apitest ' + self.suffix
        ok, body = self.check('Create own subreddit', 'POST', '/api/subreddits', [201], role=a, data={'title': title, 'description': 'Integration test subreddit'})
        subreddit = body.get('subreddit') if ok else None
        sid = self.id_of(subreddit)
        if not sid:
            self.skip('Subreddit lifecycle', 'creation failed or returned no ID')
            return
        self.created['subreddits'].append(sid)
        self.check('Read created subreddit', 'GET', f'/api/subreddits/{sid}', [200])
        self.check('Other user cannot edit subreddit', 'PUT', f'/api/subreddits/{sid}', [400, 403], role=b, data={'title': title + ' edited', 'description': 'Unauthorized edit'})
        self.check('Other user cannot delete subreddit', 'DELETE', f'/api/subreddits/{sid}', [400, 403], role=b)
        self.check('Other user cannot delete subreddit icon', 'DELETE', f'/api/subreddits/{sid}/icon', [400, 403], role=b)
        self.check('Owner updates subreddit', 'PUT', f'/api/subreddits/{sid}', [200], role=a, data={'title': title, 'description': 'Updated test description'})
        self.check('Subscribe user B', 'POST', f'/api/subreddits/{sid}/toggle-sub', [200], role=b)
        self.check('List user B subscriptions', 'GET', '/api/users/me/subscriptions', [200], role=b)
        self.check('Unsubscribe user B', 'POST', f'/api/subreddits/{sid}/toggle-sub', [200], role=b)
        self.check('Invalid post rejected', 'POST', '/api/posts', [422], role=a, data={'subreddit_id': sid, 'title': '', 'body': ''})
        post_data = {'subreddit_id': sid, 'title': 'Integration test post ' + self.suffix, 'body': 'Temporary content for integration testing.'}
        ok, body = self.check('Create own post', 'POST', '/api/posts', [201], role=a, data=post_data)
        pid = self.id_of(body.get('post')) if ok else None
        if not pid:
            self.skip('Post and comment lifecycle', 'post creation failed or returned no ID')
            return
        self.created['posts'].append(pid)
        self.check('Read created post', 'GET', f'/api/posts/{pid}', [200])
        self.check('List posts by subreddit', 'GET', f'/api/subreddits/{sid}/posts', [200])
        self.check('Other user cannot update post', 'PUT', f'/api/posts/{pid}', [400, 403], role=b, data={'title': 'Unauthorized change', 'body': 'Unauthorized content'})
        self.check('Other user cannot delete post', 'DELETE', f'/api/posts/{pid}', [400, 403], role=b)
        self.check('Other user cannot toggle post lock', 'PATCH', f'/api/posts/{pid}/lock', [400, 403], role=b)
        self.check('Other user cannot remove post image', 'DELETE', f'/api/posts/{pid}/image', [400, 403], role=b)
        self.check('Owner updates post', 'PUT', f'/api/posts/{pid}', [200], role=a, data={'title': 'Updated integration test post', 'body': 'Updated test body'})
        self.check('Owner locks post', 'PATCH', f'/api/posts/{pid}/lock', [200], role=a)
        self.check('Owner unlocks post', 'PATCH', f'/api/posts/{pid}/lock', [200], role=a)
        self.check('Invalid comment rejected', 'POST', f'/api/posts/{pid}/comments', [422], role=a, data={'body': ''})
        ok, body = self.check('Create own comment', 'POST', f'/api/posts/{pid}/comments', [201], role=a, data={'body': 'Temporary test comment ' + self.suffix})
        cid = self.id_of(body.get('comment')) if ok else None
        if cid:
            self.created['comments'].append(cid)
            self.check('List post comments', 'GET', f'/api/posts/{pid}/comments', [200])
            self.check('Other user cannot edit comment', 'PUT', f'/api/comments/{cid}', [400, 403], role=b, data={'body': 'Unauthorized comment edit'})
            self.check('Other user cannot delete comment', 'DELETE', f'/api/comments/{cid}', [400, 403], role=b)
            self.check('Owner updates comment', 'PUT', f'/api/comments/{cid}', [200], role=a, data={'body': 'Updated temporary comment'})
            self.check('Owner deletes comment', 'DELETE', f'/api/comments/{cid}', [200], role=a)
            self.created['comments'].remove(cid)
        else:
            self.skip('Comment ownership tests', 'comment creation failed or returned no ID')
        self.check('Reject invalid post image', 'POST', f'/api/posts/{pid}/image', [400, 415, 422], role=a, upload=('image', 'invalid.txt', b'not an image', 'text/plain'))
        self.check('Reject invalid subreddit icon', 'POST', f'/api/subreddits/{sid}/icon', [400, 415, 422], role=a, upload=('icon', 'invalid.txt', b'not an image', 'text/plain'))
        self.check('Reject invalid avatar', 'POST', '/api/users/avatar', [400, 415, 422], role=a, upload=('avatar', 'invalid.txt', b'not an image', 'text/plain'))
        self.check('Public posts pagination', 'GET', '/api/posts?page=1&limit=2', [200])
        self.check('Public subreddit list', 'GET', '/api/subreddits', [200])
        self.check('Search endpoint', 'GET', '/api/search?q=apitest', [200, 422])

    @staticmethod
    def id_of(value):
        if isinstance(value, dict):
            for key in ('id', 'subredditId', 'postId', 'commentId', 'subreddit_id', 'post_id', 'comment_id'):
                if str(value.get(key, '')).isdigit() and int(value[key]) > 0:
                    return int(value[key])
        return None

    def cleanup(self):
        print('\nCleaning up only resources created by this run...')
        for resource, role in (('comments', 'user_a'), ('posts', 'user_a'), ('subreddits', 'user_a')):
            path = {'comments': '/api/comments/', 'posts': '/api/posts/', 'subreddits': '/api/subreddits/'}[resource]
            for item_id in reversed(self.created[resource]):
                self.check('Cleanup ' + resource + ' #' + str(item_id), 'DELETE', path + str(item_id), [200, 204], role=role)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--base-url', default='http://webforumredit')
    parser.add_argument('--credentials', default='credentials.json')
    parser.add_argument('--report', default='api_full_report.json')
    args = parser.parse_args()
    if not os.path.isfile(args.credentials):
        parser.error('Credentials file does not exist')
    with open(args.credentials, encoding='utf-8-sig') as file:
        users = json.load(file)
    suite = Suite(args.base_url, users)
    try:
        suite.run()
    except KeyboardInterrupt:
        print('\nInterrupted; attempting cleanup...')
    except Exception as error:
        print('Unexpected test runner error:', type(error).__name__, str(error))
    finally:
        if 'user_a' in suite.tokens:
            suite.cleanup()
        with open(args.report, 'w', encoding='utf-8') as file:
            json.dump({'base_url': args.base_url, 'run_id': suite.suffix, 'results': suite.results, 'remaining_resources': suite.created}, file, indent=2, ensure_ascii=False)
    passed = sum(x['result'] == 'PASS' for x in suite.results)
    failed = sum(x['result'] == 'FAIL' for x in suite.results)
    skipped = sum(x['result'] == 'SKIP' for x in suite.results)
    print(f'\n{passed} passed, {failed} failed, {skipped} skipped. Report: {args.report}')
    sys.exit(1 if failed or skipped else 0)

if __name__ == '__main__':
    main()
