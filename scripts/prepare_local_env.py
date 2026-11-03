#!/usr/bin/env python3
"""Prepare local-only secrets without printing values or overwriting existing ones."""
import base64
import os
from pathlib import Path
import secrets

root = Path(__file__).resolve().parents[1]
env_path = root / '.env'
if env_path.is_symlink():
    raise SystemExit('Recusado: .env deve ser um arquivo local regular.')
if not env_path.exists():
    fd = os.open(env_path, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    with os.fdopen(fd, 'w') as stream:
        stream.write((root / '.env.example').read_text())
lines = env_path.read_text().splitlines()
def ensure(key, value):
    for index, line in enumerate(lines):
        if line.startswith(key + '='):
            if not line.split('=', 1)[1].strip().strip('"\''):
                lines[index] = key + '=' + value
            return
    lines.append(key + '=' + value)
ensure('APP_KEY', 'base64:' + base64.b64encode(secrets.token_bytes(32)).decode())
ensure('DB_PASSWORD', secrets.token_urlsafe(32))
ensure('MARIADB_ROOT_PASSWORD', secrets.token_urlsafe(32))
ensure('LOCAL_UID', str(os.getuid()))
ensure('LOCAL_GID', str(os.getgid()))
env_path.write_text('\n'.join(lines) + '\n')
env_path.chmod(0o600)
print('Ambiente local preparado; valores existentes preservados e segredos não exibidos.')
