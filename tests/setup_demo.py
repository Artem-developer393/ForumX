#!/usr/bin/env python3
"""Prepare verified local demo identities and an ignored credentials.json file.

Run only against a disposable/local ForumX database. Requires Docker Compose.
"""
import argparse
import json
import os
from pathlib import Path
import secrets
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
USERS = {'user_a': 'demo_alice', 'user_b': 'demo_bob', 'admin': 'demo_admin'}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--confirm-local', action='store_true', help='Confirm the database is local/disposable and may receive demo accounts')
    args = parser.parse_args()
    if not args.confirm_local:
        parser.error('Add --confirm-local only for a local/disposable Docker database')
    output = ROOT / 'credentials.json'
    if output.exists():
        parser.error('credentials.json already exists; back it up/remove it yourself before reseeding (passwords will rotate)')
    password = secrets.token_urlsafe(32)
    env = os.environ.copy()
    env['DEMO_SEED_PASSWORD'] = password
    cmd = ['docker', 'compose', 'exec', '-T', '-e', 'DEMO_SEED_ALLOW=1', '-e', 'DEMO_SEED_PASSWORD', 'api', 'php', 'tests/seed_demo.php']
    try:
        completed = subprocess.run(cmd, cwd=ROOT, env=env, check=False)
    except FileNotFoundError:
        parser.error('Docker CLI not found; start Docker Desktop')
    if completed.returncode != 0:
        print('Seed failed; credentials.json was not written.', file=sys.stderr)
        return completed.returncode
    content = {role: {'login': login, 'password': password} for role, login in USERS.items()}
    try:
        with output.open('x', encoding='utf-8') as handle:
            json.dump(content, handle, ensure_ascii=False, indent=2)
            handle.write('\n')
        if os.name != 'nt':
            output.chmod(0o600)
    except OSError as exc:
        print(f'Users created but could not write credentials.json: {exc}', file=sys.stderr)
        return 1
    print('Created ignored credentials.json. Keep it private. Demo accounts use .test emails and are pre-verified.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
