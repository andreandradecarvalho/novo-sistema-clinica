#!/usr/bin/env python3
"""Manage the isolated, synthetic local homologation environment."""
import argparse
import base64
from datetime import datetime, timezone
import json
import os
from pathlib import Path
import secrets
import subprocess

ROOT = Path(__file__).resolve().parents[1]
ENV_PATH = ROOT / '.env.homologacao'
COMPOSE = ['docker', 'compose', '--env-file', str(ENV_PATH), '-f', str(ROOT / 'compose.homologacao.yaml')]


def prepare():
    if ENV_PATH.is_symlink():
        raise SystemExit('Recusado: .env.homologacao não pode ser symlink.')
    if ENV_PATH.exists():
        ENV_PATH.chmod(0o600)
        existing = ENV_PATH.read_text()
        if not any(line.startswith('CPF_SEARCH_KEY=') for line in existing.splitlines()):
            with ENV_PATH.open('a') as stream:
                stream.write('\nCPF_SEARCH_KEY=' + secrets.token_hex(32) + '\n')
        return
    values = {
        'APP_NAME': 'Precordis_Homologacao', 'APP_ENV': 'staging',
        'CPF_SEARCH_KEY': secrets.token_hex(32),
        'APP_KEY': 'base64:' + base64.b64encode(secrets.token_bytes(32)).decode(),
        'APP_DEBUG': 'false', 'APP_URL': 'http://clinica-a.localhost:8082', 'APP_PORT': '8082',
        'APP_LOCALE': 'pt_BR', 'APP_FALLBACK_LOCALE': 'pt_BR',
        'LOCAL_UID': str(os.getuid()), 'LOCAL_GID': str(os.getgid()),
        'DB_CONNECTION': 'mariadb', 'DB_HOST': 'db', 'DB_PORT': '3306',
        'DB_DATABASE': 'novo_sistema_clinica_homologacao', 'DB_USERNAME': 'clinica_homologacao',
        'DB_PASSWORD': secrets.token_urlsafe(32), 'DB_URL': '',
        'MARIADB_ROOT_PASSWORD': secrets.token_urlsafe(32),
        'SESSION_DRIVER': 'tenant-database', 'SESSION_COOKIE': 'precordis_homologacao_session',
        'SESSION_DOMAIN': '', 'SESSION_ENCRYPT': 'true', 'SESSION_SECURE_COOKIE': 'false',
        'CACHE_STORE': 'redis', 'QUEUE_CONNECTION': 'redis', 'REDIS_CLIENT': 'predis',
        'REDIS_HOST': 'redis', 'REDIS_PREFIX': 'precordis-homologacao-',
        'REDIS_QUEUE_RETRY_AFTER': '180', 'MAIL_MAILER': 'array',
        'LOG_CHANNEL': 'stderr', 'LOG_LEVEL': 'warning', 'BROADCAST_CONNECTION': 'log',
    }
    for clinic in ['A', 'B']:
        values['HOMOLOG_' + clinic + '_ADMIN_PASSWORD'] = 'Pc9!' + secrets.token_urlsafe(20)
        values['HOMOLOG_' + clinic + '_RECEPTION_PASSWORD'] = 'Pc9!' + secrets.token_urlsafe(20)
        values['HOMOLOG_' + clinic + '_TOTP_SECRET'] = base64.b32encode(secrets.token_bytes(20)).decode()
    fd = os.open(ENV_PATH, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    with os.fdopen(fd, 'w') as stream:
        stream.write('# Homologação local isolada. Não publicar.\n')
        stream.write('\n'.join(key + '=' + value for key, value in values.items()) + '\n')


def run(*args, capture=False):
    # The explicit env file is authoritative; inherited shell variables must not select another DB.
    env = os.environ.copy()
    for line in ENV_PATH.read_text().splitlines():
        if '=' in line and not line.startswith('#'):
            env.pop(line.split('=', 1)[0], None)
    return subprocess.run(COMPOSE + list(args), cwd=ROOT, env=env, check=True, text=True, capture_output=capture)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('action', choices=['prepare', 'up', 'check', 'status', 'down'])
    parser.add_argument('--registries', action='store_true', help='Incluir cadastros/identidade por HTTP na ação check')
    parser.add_argument('--stage3', action='store_true', help='Incluir fundação, cadastros e fluxos da etapa 3 na ação check')
    parser.add_argument('--orders', action='store_true', help='Incluir etapa 3 e pedidos particulares por HTTP na ação check')
    args = parser.parse_args()
    if (args.registries or args.stage3 or args.orders) and args.action != 'check':
        parser.error('--registries/--stage3/--orders só podem ser usados com check')
    prepare()
    if args.action == 'prepare':
        print('Ambiente privado preparado em .env.homologacao; nenhum segredo exibido.')
    elif args.action == 'up':
        if not (ROOT / 'vendor/autoload.php').exists() or not (ROOT / 'public/build/manifest.json').exists():
            raise SystemExit('Instale dependências e execute npm run build conforme README antes da homologação.')
        if (ROOT / 'public/hot').exists():
            raise SystemExit('Encerre o Vite de desenvolvimento e remova seu public/hot antes da homologação.')
        run('build', 'app')
        run('up', '-d', '--wait', 'db', 'redis')
        run('run', '--rm', 'init')
        run('run', '--rm', '--no-deps', 'app', 'php', 'artisan', 'config:clear')
        run('run', '--rm', '--no-deps', 'app', 'php', 'artisan', 'homologation:migrate')
        run('run', '--rm', '--no-deps', 'app', 'php', 'artisan', 'db:seed', '--class=HomologationSeeder', '--force')
        run('up', '-d', '--wait', 'app', 'horizon')
        print('Homologação iniciada em clinica-a.localhost:8082 e clinica-b.localhost:8082.')
    elif args.action == 'check':
        flags = ['--orders'] if args.orders else (['--stage3'] if args.stage3 else (['--registries'] if args.registries else []))
        result = run('exec', '-T', 'app', 'php', 'artisan', 'homologation:check', *flags, capture=True)
        print(result.stdout)
        report_path = '/app/storage/app/homologacao-orders-report.json' if args.orders else ('/app/storage/app/homologacao-stage3-report.json' if args.stage3 else ('/app/storage/app/homologacao-registries-report.json' if args.registries else '/app/storage/app/homologacao-report.json'))
        report = json.loads(run('exec', '-T', 'app', 'cat', report_path, capture=True).stdout)
        report['source_revision'] = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip()
        report['working_tree_dirty'] = bool(subprocess.check_output(['git', 'status', '--porcelain'], cwd=ROOT, text=True).strip())
        prefix = 'homologacao-pedidos-etapa-04-' if args.orders else ('homologacao-etapa-03-' if args.stage3 else ('homologacao-etapa-02-' if args.registries else 'homologacao-local-'))
        filename = ROOT / 'docs/outros' / (prefix + datetime.now(timezone.utc).date().isoformat() + '.json')
        filename.write_text(json.dumps(report, indent=2, ensure_ascii=False) + '\n')
        print('Evidência sem segredos: ' + str(filename.relative_to(ROOT)))
    elif args.action == 'status':
        run('ps')
    elif args.action == 'down':
        run('down')
        print('Serviços de homologação encerrados; volumes preservados.')


if __name__ == '__main__':
    try:
        main()
    except subprocess.CalledProcessError as exc:
        # No request bodies, environment or generated secrets in error output.
        if exc.stdout:
            for line in exc.stdout.splitlines():
                if line.startswith(('PASS ', 'FAIL ')):
                    print(line)
        print('Operação de homologação falhou. Exit code:', exc.returncode)
        raise SystemExit(exc.returncode)
