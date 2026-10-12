#!/usr/bin/env python3
"""ForumX refresh/session integration tests; Python 3.10+, standard library only.

Uses existing credentials.json (user_a, user_b, admin). Creates and revokes test
sessions; does not modify passwords or user profiles. Run on a TEST database.
"""
import argparse
import concurrent.futures
import json
import pathlib
import re
import sys
import urllib.error
import urllib.request

TOKEN = re.compile(r'^[a-f0-9]{64}$')

class Suite:
    def __init__(self, base, accounts, timeout):
        self.base = base.rstrip('/')
        self.accounts = accounts
        self.timeout = timeout
        self.results = []
        self.sessions = []

    def call(self, method, path, jwt=None, data=None):
        headers = {'Accept': 'application/json'}
        if jwt is not None:
            headers['Authorization'] = 'Bearer ' + jwt
        payload = None if data is None else json.dumps(data).encode('utf-8')
        if payload is not None:
            headers['Content-Type'] = 'application/json'
        req = urllib.request.Request(self.base + path, headers=headers, data=payload, method=method)
        try:
            with urllib.request.urlopen(req, timeout=self.timeout) as res:
                status, raw = res.status, res.read(1048577)
        except urllib.error.HTTPError as err:
            status, raw = err.code, err.read(1048577)
        except Exception as err:
            return 0, None, type(err).__name__
        if len(raw) > 1048576:
            return status, None, 'response exceeds 1 MiB'
        try:
            return status, json.loads(raw), None
        except (ValueError, UnicodeError):
            return status, None, 'invalid JSON'

    def check(self, label, method, path, expected, jwt=None, data=None, validate=None):
        status, body, error = self.call(method, path, jwt, data)
        issues = []
        if error:
            issues.append(error)
        if status not in expected:
            issues.append('expected %s, got %s' % (expected, status))
        if not isinstance(body, dict):
            issues.append('response must be a JSON object')
        elif status < 400:
            if set(body) != {'data', 'meta', 'message'}:
                issues.append('success envelope must contain data, meta, message')
            if body.get('meta') is not None and not isinstance(body.get('meta'), dict):
                issues.append('invalid meta')
            if body.get('message') is not None and not isinstance(body.get('message'), str):
                issues.append('invalid message')
        else:
            obj = body.get('error')
            if not isinstance(obj, dict) or obj.get('status') != status or not isinstance(obj.get('message'), str) or not isinstance(obj.get('code'), str):
                issues.append('invalid error envelope')
        payload = body.get('data') if isinstance(body, dict) and status < 400 else None
        if not issues and validate:
            try:
                if not validate(payload):
                    issues.append('unexpected response data')
            except (TypeError, ValueError, KeyError, AttributeError):
                issues.append('unexpected response data')
        passed = not issues
        self.results.append({'test': label, 'result': 'PASS' if passed else 'FAIL', 'status': status, 'detail': '; '.join(issues)})
        print('%-4s HTTP %-3s %s%s' % ('PASS' if passed else 'FAIL', status, label, (' — ' + '; '.join(issues)) if issues else ''))
        return payload if passed and status < 400 else None

    def skip(self, label, reason):
        self.results.append({'test': label, 'result': 'SKIP', 'detail': reason})
        print('SKIP          %s — %s' % (label, reason))

    def login(self, role):
        a = self.accounts[role]
        result = self.check(role + ' login', 'POST', '/api/auth/login', {200}, data={'login': a['login'], 'password': a['password']}, validate=lambda d: isinstance(d, dict) and isinstance(d.get('token'), str) and bool(TOKEN.fullmatch(d.get('refresh_token', ''))) and isinstance(d.get('user'), dict))
        if result:
            session = {'role': role, 'jwt': result['token'], 'rt': result['refresh_token']}
            self.sessions.append(session)
            return session
        return None

    def me(self, label, jwt, expected):
        return self.check(label, 'GET', '/api/auth/me', {expected}, jwt=jwt)

    def refresh(self, label, rt, expected=200):
        return self.check(label, 'POST', '/api/auth/refresh', {expected}, data={'refresh_token': rt}, validate=(lambda d: isinstance(d, dict) and isinstance(d.get('token'), str) and bool(TOKEN.fullmatch(d.get('refresh_token', '')))) if expected == 200 else None)

    def run(self, admin_cleanup=True, concurrency=True, concurrency_rounds=3):
        self.check('Anonymous protected route', 'GET', '/api/auth/me', {401})
        self.check('Missing refresh token', 'POST', '/api/auth/refresh', {422}, data={})
        self.check('Refresh token wrong type', 'POST', '/api/auth/refresh', {422}, data={'refresh_token': 123})
        self.check('Refresh token malformed', 'POST', '/api/auth/refresh', {422}, data={'refresh_token': 'wrong'})
        self.refresh('Unknown well-formed token', '0' * 64, 401)
        a = self.login('user_a')
        if not a:
            self.skip('Session lifecycle', 'user_a login failed')
            return
        b = self.login('user_a')
        other = self.login('user_b')
        admin = self.login('admin')
        self.me('Session A authorized', a['jwt'], 200)
        self.check('Non-admin stats forbidden', 'GET', '/api/admin/refresh-tokens/stats', {403}, jwt=a['jwt'])
        self.check('Non-admin cleanup forbidden', 'POST', '/api/admin/refresh-tokens/cleanup', {403}, jwt=a['jwt'])
        if admin:
            stats = self.check('Admin token stats', 'GET', '/api/admin/refresh-tokens/stats', {200}, jwt=admin['jwt'], validate=lambda d: isinstance(d, dict) and isinstance(d.get('stats'), dict) and all(isinstance(d['stats'].get(k), int) and d['stats'][k] >= 0 for k in ('total', 'active', 'used', 'revoked', 'expired', 'ready_for_cleanup')))
            if admin_cleanup:
                cleaned = self.check('Admin cleanup', 'POST', '/api/admin/refresh-tokens/cleanup', {200}, jwt=admin['jwt'], validate=lambda d: isinstance(d, dict) and isinstance(d.get('deleted_count'), int) and 0 <= d['deleted_count'] <= 10000)
                after = self.check('Admin stats after cleanup', 'GET', '/api/admin/refresh-tokens/stats', {200}, jwt=admin['jwt'], validate=lambda d: isinstance(d, dict) and isinstance(d.get('stats'), dict) and all(isinstance(d['stats'].get(k), int) and d['stats'][k] >= 0 for k in ('total', 'active', 'used', 'revoked', 'expired', 'ready_for_cleanup')))
                if cleaned is not None and stats is not None and after is not None:
                    before_count = stats['stats']['ready_for_cleanup']
                    after_count = after['stats']['ready_for_cleanup']
                    # Other clients may add new sessions, but cannot make old expired rows younger.
                    ok = after_count <= before_count and cleaned['deleted_count'] <= before_count
                    self.record('Cleanup stats consistency', ok, detail=f'before={before_count}, deleted={cleaned["deleted_count"]}, after={after_count}')
            else:
                self.skip('Admin cleanup execution', 'requires --admin-cleanup (deletes expired database records)')
        old_jwt, old_rt = a['jwt'], a['rt']
        rotated = self.refresh('Rotate A refresh token', old_rt)
        if rotated:
            a['jwt'], a['rt'] = rotated['token'], rotated['refresh_token']
            self.me('New JWT works after rotation', a['jwt'], 200)
            self.me('Old JWT still works within same session', old_jwt, 200)
            self.refresh('Replayed old refresh rejected', old_rt, 401)
            self.me('Replay revokes current JWT immediately', a['jwt'], 401)
            self.refresh('Replay revokes latest refresh token', a['rt'], 401)
        if b:
            self.me('Second login unaffected by replay', b['jwt'], 200)
            if other:
                self.check('Cross-user logout token rejected', 'POST', '/api/auth/logout', {401}, jwt=b['jwt'], data={'refresh_token': other['rt']})
                self.me('Cross-user attempt did not revoke session', b['jwt'], 200)
            self.check('Logout malformed refresh rejected', 'POST', '/api/auth/logout', {422}, jwt=b['jwt'], data={'refresh_token': 'x'})
            self.check('Logout session B', 'POST', '/api/auth/logout', {200}, jwt=b['jwt'], data={'refresh_token': b['rt']})
            self.me('Logout JWT rejected immediately', b['jwt'], 401)
            self.refresh('Logout refresh token rejected', b['rt'], 401)
        c = self.login('user_a')
        d = self.login('user_a')
        if c and d:
            self.me('Third login authorized', c['jwt'], 200)
            self.me('Fourth login authorized', d['jwt'], 200)
            self.check('Logout all sessions', 'POST', '/api/auth/logout-all', {200}, jwt=c['jwt'])
            self.me('Logout-all first JWT rejected', c['jwt'], 401)
            self.me('Logout-all second JWT rejected', d['jwt'], 401)
            self.refresh('Logout-all first refresh rejected', c['rt'], 401)
            self.refresh('Logout-all second refresh rejected', d['rt'], 401)
            if other:
                self.me('Other user unaffected by logout-all', other['jwt'], 200)
        if concurrency:
            for round_no in range(1, concurrency_rounds + 1):
                e = self.login('user_a')
                unaffected = self.login('user_b')
                if not e or not unaffected:
                    self.skip(f'Concurrent rotation round {round_no}', 'login failed')
                    continue
                # Barrier makes the two requests begin together as closely as possible.
                import threading
                barrier = threading.Barrier(2)
                def rotate_at_once():
                    barrier.wait(timeout=self.timeout)
                    return self.call('POST', '/api/auth/refresh', data={'refresh_token': e['rt']})
                with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
                    futures = [pool.submit(rotate_at_once) for _ in range(2)]
                    results = [f.result() for f in futures]
                statuses = [r[0] for r in results]
                successes = [r for r in results if r[0] == 200]
                rejects = [r for r in results if r[0] == 401]
                valid_success = len(successes) == 1 and isinstance(successes[0][1], dict) and isinstance(successes[0][1].get('data'), dict)
                if valid_success:
                    issued = successes[0][1]['data']
                    valid_success = isinstance(issued.get('token'), str) and bool(TOKEN.fullmatch(issued.get('refresh_token', '')))
                self.record(f'Concurrent rotation round {round_no}: exactly one success', valid_success and len(rejects) == 1, detail='statuses=' + repr(statuses))
                if valid_success:
                    self.me(f'Concurrent round {round_no}: issued JWT revoked after replay', issued['token'], 401)
                    self.refresh(f'Concurrent round {round_no}: issued refresh revoked', issued['refresh_token'], 401)
                self.me(f'Concurrent round {round_no}: unrelated session remains active', unaffected['jwt'], 200)
                # Explicitly close the unrelated session to avoid accumulating active sessions.
                self.check(f'Concurrent round {round_no}: cleanup unrelated session', 'POST', '/api/auth/logout', {200}, jwt=unaffected['jwt'], data={'refresh_token': unaffected['rt']})
        else:
            self.skip('Concurrent rotation', 'disabled by --no-concurrency')

    def record(self, label, passed, detail=''):
        self.results.append({'test': label, 'result': 'PASS' if passed else 'FAIL', 'detail': detail})
        print('%-4s %s%s' % ('PASS' if passed else 'FAIL', label, (' — ' + detail) if detail else ''))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--base-url', default='http://webforumredit')
    parser.add_argument('--credentials', default='credentials.json')
    parser.add_argument('--report', default='refresh_report.json')
    parser.add_argument('--timeout', type=float, default=15)
    parser.add_argument('--no-admin-cleanup', action='store_true', help='skip destructive admin cleanup')
    parser.add_argument('--no-concurrency', action='store_true', help='skip concurrent refresh requests')
    parser.add_argument('--concurrency-rounds', type=int, default=3, help='number of independent concurrent refresh trials (default: 3)')
    args = parser.parse_args()
    try:
        accounts = json.loads(pathlib.Path(args.credentials).read_text(encoding='utf-8-sig'))
        for name in ('user_a', 'user_b', 'admin'):
            if not isinstance(accounts.get(name), dict) or not accounts[name].get('login') or not accounts[name].get('password'):
                raise ValueError('credentials.json requires user_a, user_b and admin with login/password')
    except (OSError, ValueError) as err:
        parser.error('Invalid credentials file: ' + str(err))
    if args.concurrency_rounds < 1 or args.concurrency_rounds > 100:
        parser.error('--concurrency-rounds must be between 1 and 100')
    suite = Suite(args.base_url, accounts, args.timeout)
    try:
        suite.run(not args.no_admin_cleanup, not args.no_concurrency, args.concurrency_rounds)
    except KeyboardInterrupt:
        print('Interrupted')
        return 130
    finally:
        counts = {k: sum(r['result'] == k for r in suite.results) for k in ('PASS', 'FAIL', 'SKIP')}
        pathlib.Path(args.report).write_text(json.dumps({'base_url': args.base_url, 'counts': counts, 'results': suite.results}, ensure_ascii=False, indent=2), encoding='utf-8')
        print('\n%s. Report: %s' % (counts, args.report))
    return 1 if counts['FAIL'] or counts['SKIP'] else 0

if __name__ == '__main__':
    sys.exit(main())
