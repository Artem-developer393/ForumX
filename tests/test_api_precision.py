#!/usr/bin/env python3
"""Strict, read-only API regression and authorization checks for webForumRedit.

Python 3.10+ standard library only. Never modifies application data.
"""
import argparse
import json
import re
import sys
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

CODES = {400:'BAD_REQUEST',401:'UNAUTHORIZED',403:'FORBIDDEN',404:'NOT_FOUND',409:'CONFLICT',422:'UNPROCESSABLE_ENTITY',500:'INTERNAL_SERVER_ERROR'}
SECRET = re.compile(r'(?i)(sqlstate|stack trace|fatal error|uncaught |pdoexception|syntax error|select\s+.+\s+from|password\s*=|database connection|\\xampp\\|\\openserver\\)')

class Checks:
    def __init__(self, base, creds, timeout):
        self.base=base.rstrip('/'); self.creds=creds; self.timeout=timeout
        self.tokens={}; self.users={}; self.results=[]

    def call(self, method, path, role=None, data=None, auth=None):
        headers={'Accept':'application/json'}
        if role is not None: headers['Authorization']='Bearer '+self.tokens[role]
        if auth is not None: headers['Authorization']=auth
        raw=None
        if data is not None:
            raw=json.dumps(data,ensure_ascii=False).encode('utf-8')
            headers['Content-Type']='application/json'
        req=urllib.request.Request(self.base+path,headers=headers,data=raw,method=method)
        try:
            with urllib.request.urlopen(req,timeout=self.timeout) as res:
                status=res.status; payload=res.read(1048577)
        except urllib.error.HTTPError as exc:
            status=exc.code; payload=exc.read(1048577)
        except Exception as exc:
            return 0,None,'Transport error: '+type(exc).__name__
        if len(payload)>1048576: return status,None,'Response exceeds 1 MiB'
        try: return status,json.loads(payload.decode('utf-8')),None
        except (ValueError,UnicodeError): return status,None,'Invalid JSON response'

    def record(self,label,ok,status,why=''):
        self.results.append({'name':label,'result':'PASS' if ok else 'FAIL','status':status,'detail':why[:240]})
        print(f"{'PASS' if ok else 'FAIL':4} HTTP {status:<3} {label}"+((' — '+why[:120]) if not ok else ''))
        return ok

    def check(self,label,method,path,expected,role=None,data=None,auth=None,shape=None,validator=None):
        status,body,err=self.call(method,path,role,data,auth)
        problems=[]
        if err: problems.append(err)
        if status!=expected: problems.append(f'expected HTTP {expected}, got {status}')
        if not isinstance(body,dict): problems.append('JSON root must be an object')
        elif status>=400:
            error=body.get('error')
            if not isinstance(error,dict): problems.append('missing error object')
            else:
                if error.get('status')!=status: problems.append('error.status differs from HTTP status')
                if not isinstance(error.get('message'),str) or not error['message'].strip(): problems.append('missing error.message')
                expected_codes = {'VALIDATION_ERROR', 'UNPROCESSABLE_ENTITY'} if status == 422 else {CODES.get(status, 'HTTP_ERROR')}
                if error.get('code') not in expected_codes:
                    problems.append('unexpected error.code: expected '+', '.join(sorted(expected_codes))+', got '+repr(error.get('code')))
                if error.get('code') == 'VALIDATION_ERROR' and (not isinstance(error.get('fields'), dict) or not error['fields']):
                    problems.append('VALIDATION_ERROR must include nonempty error.fields object')
                if status>=500 and error.get('message')!='Internal server error': problems.append('500 leaks internal message')
                if SECRET.search(json.dumps(error,ensure_ascii=False)): problems.append('potential internal information disclosure')
        elif isinstance(body,dict):
            if set(body) != {'data', 'meta', 'message'}:
                problems.append('success envelope must contain exactly data, meta, message')
            if body.get('meta') is not None and not isinstance(body.get('meta'),dict):
                problems.append('meta must be object or null')
            if body.get('message') is not None and not isinstance(body.get('message'),str):
                problems.append('message must be string or null')
            if 'error' in body: problems.append('success response contains error')
            # Project legacy shapes are checked against an adapted view,
            # after independently validating the actual Stage 8 envelope.
            payload=body.get('data')
            legacy=dict(payload) if isinstance(payload,dict) else {'data':payload}
            if isinstance(body.get('meta'),dict): legacy.update(body['meta'])
            if body.get('message') is not None: legacy['message']=body['message']
            body=legacy
            for key,typ in (shape or {}).items():
                if key not in body: problems.append('missing success field '+key)
                elif not isinstance(body[key],typ): problems.append('incorrect type for '+key)
            if validator:
                try: problems.extend(validator(body))
                except Exception as exc: problems.append('validator failure: '+type(exc).__name__)
        self.record(label,not problems,status,'; '.join(problems))
        return body if not problems else None

    def run(self):
        for role in ('user_a','user_b','admin'):
            account=self.creds.get(role,{})
            if not isinstance(account,dict) or not account.get('login') or not account.get('password'):
                self.record(role+' credentials provided',False,0,'missing login/password'); continue
            body=self.check(role+' login','POST','/api/auth/login',200,data={'login':account['login'],'password':account['password']},shape={'token':str,'user':dict,'message':str})
            if body and body.get('token'):
                self.tokens[role]=body['token']
                profile=self.check(role+' authenticated identity','GET','/api/auth/me',200,role=role,shape={'user':dict})
                if profile: self.users[role]=profile['user']
        if len(self.tokens)!=3:
            self.record('All three identities available',False,0,'cannot safely continue role checks'); return
        # Public endpoints and response invariants. These are read-only.
        for label,path,shape in [
            ('Public posts','/api/posts?page=1&limit=2',{}),
            ('Public subreddits','/api/subreddits',{'data':list,'count':int}),
            ('Search posts','/api/search?q=test&type=posts&page=1&limit=2',{}),
            ('Search communities','/api/search?q=test&type=subreddits',{}),
            ('Search users','/api/search?q=test&type=users',{}),
            ('Report categories','/api/reports/categories',{'categories':list}),
        ]:
            role='user_a' if label=='Report categories' else None
            self.check(label,'GET',path,200,role=role,shape=shape)
        self.check('Missing route','GET','/api/__precision_missing_route__',404)
        self.check('Search missing q','GET','/api/search?type=posts',422)
        self.check('Search missing type','GET','/api/search?q=test',422)
        self.check('Search invalid type','GET','/api/search?q=test&type=unknown',422)
        self.check('Register validation','POST','/api/auth/register',422,data={})
        self.check('Login validation','POST','/api/auth/login',422,data={})
        self.check('Reset password validation','POST','/api/auth/reset-password',422,data={})
        self.check('Verify email validation','POST','/api/auth/verify-email',422,data={})
        self.check('Invalid login','POST','/api/auth/login',401,data={'login':'precision_nonexistent_account_987654','password':'WrongPassword!234'})
        # Exact auth matrix for every protected route; use safe GET requests only.
        user_get=[
            '/api/auth/me','/api/users/me/subscriptions','/api/posts/feed',
            '/api/reports/categories',
        ]
        admin_get=[
            '/api/admin/dashboard','/api/admin/users','/api/admin/posts',
            '/api/admin/comments','/api/admin/subreddits','/api/admin/reports',
        ]
        for path in user_get+admin_get:
            self.check('Guest blocked '+path,'GET',path,401)
            self.check('Malformed JWT blocked '+path,'GET',path,401,auth='Bearer invalid.invalid.invalid')
            self.check('Malformed scheme blocked '+path,'GET',path,401,auth='Basic abcdef')
        for path in admin_get:
            for role in ('user_a','user_b'):
                self.check(role+' forbidden '+path,'GET',path,403,role=role)
            self.check('Admin permitted '+path,'GET',path,200,role='admin')
        for path in user_get:
            for role in ('user_a','user_b','admin'):
                shape={'user':dict} if path=='/api/auth/me' else {'categories':list} if path=='/api/reports/categories' else {}
                self.check(role+' permitted '+path,'GET',path,200,role=role,shape=shape)
        # All of these requests must be rejected by authentication middleware BEFORE mutation.
        mutations=[
            ('POST','/api/subreddits',{'title':'precision should not create','description':'read-only test'}),
            ('POST','/api/posts',{'title':'precision should not create','body':'read-only test','subredditId':1}),
            ('POST','/api/reports',{'targetType':'post','targetId':1,'categoryId':1}),
            ('PUT','/api/users',{'bio':'precision should not update'}),
            ('POST','/api/users/deletion-request',{}),
            ('PATCH','/api/auth/change-password',{}),
            ('PATCH','/api/admin/users/1/ban',{}),
            ('PATCH','/api/admin/users/1/role',{}),
            ('DELETE','/api/admin/users/1',None),
            ('DELETE','/api/posts/1',None),
            ('DELETE','/api/subreddits/1',None),
            ('DELETE','/api/comments/1',None),
            ('PATCH','/api/posts/1/lock',None),
        ]
        for method,path,data in mutations:
            self.check('Guest mutation denied '+method+' '+path,method,path,401,data=data)
        for method,path,data in mutations:
            if path.startswith('/api/admin/'):
                for role in ('user_a','user_b'):
                    self.check(role+' admin mutation denied '+method+' '+path,method,path,403,role=role,data=data)
        # Validation only: malformed payloads are rejected before verification codes or email delivery.
        # Never request/resend a code or submit a syntactically valid verification payload.
        self.check('Subreddit invalid title','POST','/api/subreddits',422,role='user_a',data={'title':'!','description':''})
        self.check('Post missing fields','POST','/api/posts',422,role='user_a',data={})
        self.check('Report missing fields','POST','/api/reports',422,role='user_a',data={})
        self.check('Change password missing fields','PATCH','/api/auth/change-password',422,role='user_a',data={})
        # Cross-account identity: the two user tokens must not resolve to the same account.
        a=self.users.get('user_a',{}); b=self.users.get('user_b',{})
        id_a=a.get('id',a.get('userId')); id_b=b.get('id',b.get('userId'))
        self.record('Distinct regular account identities',bool(id_a and id_b and str(id_a)!=str(id_b)),200, '' if id_a and id_b and str(id_a)!=str(id_b) else 'missing or identical user IDs')
        for role,user in self.users.items():
            uid=user.get('id',user.get('userId'))
            if uid and str(uid).isdigit():
                self.check(role+' public profile','GET',f'/api/users/{uid}',200,shape={'user':dict})
                self.check(role+' public posts','GET',f'/api/users/{uid}/posts?page=1&limit=2',200)
                self.check(role+' public comments','GET',f'/api/users/{uid}/comments?page=1&limit=2',200)


def main():
    ap=argparse.ArgumentParser(description=__doc__)
    ap.add_argument('--base-url',default='http://webforumredit')
    ap.add_argument('--credentials',default='credentials.json')
    ap.add_argument('--report',default='precision_report.json')
    ap.add_argument('--timeout',type=int,default=15)
    args=ap.parse_args()
    try:
        creds=json.loads(Path(args.credentials).read_text(encoding='utf-8-sig'))
    except (OSError,ValueError) as exc:
        ap.error('Unable to read credentials file: '+type(exc).__name__)
    suite=Checks(args.base_url,creds,args.timeout)
    suite.run()
    counts={k:sum(r['result']==k for r in suite.results) for k in ('PASS','FAIL')}
    Path(args.report).write_text(json.dumps({'base_url':args.base_url,'counts':counts,'results':suite.results},indent=2,ensure_ascii=False),encoding='utf-8')
    print(f"\n{counts['PASS']} passed, {counts['FAIL']} failed. Report: {args.report}")
    return 1 if counts['FAIL'] else 0

if __name__=='__main__': sys.exit(main())
