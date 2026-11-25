"""Requests-compatible loopback transport for a prepared local .runtime.

Each fixture uses a SQLite backup and a new private photo directory. No web
server, real customer database, mail account, or payment credentials are used.
"""
from email.parser import BytesHeaderParser
import os
from pathlib import Path
from secrets import token_hex
import shutil
import sqlite3
import subprocess
import tempfile
from types import SimpleNamespace
from urllib.parse import unquote, urlsplit

import requests
from requests.adapters import BaseAdapter
from requests.cookies import extract_cookies_to_jar
from requests.models import Response
from requests.structures import CaseInsensitiveDict


ROOT = Path(__file__).resolve().parents[1]
RUNTIME = ROOT / '.runtime'
ORIGIN = 'http://127.0.0.1:4173'


def configure_wordpress(path):
    # Explicit test storage prevents a copied wp-config.php from pointing at a
    # shared database or directory when this suite runs in another workspace.
    salts = [
        f"define('{kind}_{suffix}', '{token_hex(32)}');"
        for kind in ('AUTH', 'SECURE_AUTH', 'LOGGED_IN', 'NONCE')
        for suffix in ('KEY', 'SALT')
    ]
    statements = [
        "<?php",
        "define('DB_NAME', 'tiron');",
        "define('DB_USER', '');",
        "define('DB_PASSWORD', '');",
        "define('DB_HOST', 'localhost');",
        "define('DB_CHARSET', 'utf8mb4');",
        "define('DB_COLLATE', '');",
        "define('DB_PATH', dirname(__DIR__) . '/data/tiron.sqlite');",
        "define('WP_ENVIRONMENT_TYPE', 'local');",
        "define('TIRON_PRIVATE_PHOTO_DIR', dirname(__DIR__) . '/private-photos');",
        "define('TIRON_ENABLE_TEST_CHECKOUT', true);",
        "define('WP_HTTP_BLOCK_EXTERNAL', true);",
        "define('WP_DEBUG', false);",
        "define('DISALLOW_FILE_MODS', true);",
        "define('DISABLE_WP_CRON', true);",
        f"define('WP_HOME', '{ORIGIN}');",
        "define('WP_SITEURL', WP_HOME);",
        "$table_prefix = 'wp_';",
        *salts,
        "if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');",
        "require_once ABSPATH . 'wp-settings.php';",
    ]
    path.write_text('\n'.join(statements) + '\n')
    path.chmod(0o600)


class Fixture:
    def __init__(self):
        self.cgi = os.environ.get('TIRON_PHP_CGI') or shutil.which('php-cgi')
        if not self.cgi:
            raise RuntimeError('Configurează TIRON_PHP_CGI cu calea către PHP CGI local.')
        source = RUNTIME / 'wordpress'
        if not (source / 'wp-content/db.php').is_file() or not (RUNTIME / 'data/tiron.sqlite').is_file():
            raise RuntimeError('Este necesară o instalare locală .runtime cu WordPress, WooCommerce și SQLite.')
        self.temp = tempfile.TemporaryDirectory(prefix='tiron-atelier-qa-')
        self.root = Path(self.temp.name)
        self.web = self.root / 'wordpress'
        self.web.mkdir()
        self.storage = self.root / 'private-photos'
        self.storage.mkdir(mode=0o700)
        (self.root / 'data').mkdir()
        shutil.copytree(source / 'wp-admin', self.web / 'wp-admin')
        (self.web / 'wp-includes').symlink_to(source / 'wp-includes')
        for php in source.glob('*.php'):
            if php.name != 'wp-config.php':
                shutil.copyfile(php, self.web / php.name)
        configure_wordpress(self.web / 'wp-config.php')
        wp_content = self.web / 'wp-content'
        wp_content.mkdir()
        for folder in ('plugins', 'themes', 'mu-plugins', 'languages'):
            (wp_content / folder).symlink_to(source / 'wp-content' / folder)
        shutil.copyfile(source / 'wp-content/db.php', wp_content / 'db.php')
        database = RUNTIME / 'data/tiron.sqlite'
        with sqlite3.connect(database.as_uri() + '?mode=ro', uri=True) as original:
            with sqlite3.connect(self.root / 'data/tiron.sqlite') as copy:
                original.backup(copy)
        self.env = dict(os.environ, TIRON_PREVIEW_PORT='4173')
        bundled_libraries = RUNTIME / 'php/usr/lib/x86_64-linux-gnu'
        if bundled_libraries.is_dir():
            current = self.env.get('LD_LIBRARY_PATH')
            self.env['LD_LIBRARY_PATH'] = str(bundled_libraries) + (':' + current if current else '')

    def run_wp(self, *args):
        command = ['bash', str(ROOT / 'scripts/runtime-php.sh'), str(RUNTIME / 'wp-cli.phar'),
                   '--path=' + str(self.web), *args]
        process = subprocess.run(command, cwd=self.web, env=self.env, capture_output=True, timeout=75, check=True)
        return process.stdout.decode().strip()

    def session(self):
        session = requests.Session()
        session.trust_env = False
        for scheme in ('http://', 'https://'):
            session.mount(scheme, LocalCgi(self))
        return session

    def close(self):
        self.temp.cleanup()


class LocalCgi(BaseAdapter):
    def __init__(self, fixture):
        self.fixture = fixture

    def close(self):
        pass

    def send(self, request, **kwargs):
        fixture = self.fixture
        target = urlsplit(request.url)
        if target.scheme != 'http' or target.netloc != '127.0.0.1:4173':
            raise ValueError('QA accepts only its private local WordPress fixture.')
        path = unquote(target.path)
        if '..' in Path(path).parts:
            raise ValueError('Invalid route.')
        if path == '/wp-admin/':
            entrypoint = '/wp-admin/index.php'
        elif (path.startswith('/wp-admin/') and path.endswith('.php')) or path == '/wp-login.php':
            entrypoint = path
        else:
            entrypoint = '/index.php'

        env = dict(
            fixture.env, REDIRECT_STATUS='200', GATEWAY_INTERFACE='CGI/1.1',
            SERVER_PROTOCOL='HTTP/1.1', SERVER_NAME='127.0.0.1', SERVER_PORT='4173',
            REMOTE_ADDR='127.0.0.1', DOCUMENT_ROOT=str(fixture.web), HTTP_HOST=target.netloc,
            REQUEST_URI=request.path_url, REQUEST_METHOD=request.method, QUERY_STRING=target.query,
            SCRIPT_FILENAME=str(fixture.web / entrypoint.lstrip('/')), SCRIPT_NAME=entrypoint,
        )
        for key, value in request.headers.items():
            name = key.replace('-', '_').upper()
            env[name if name in ('CONTENT_TYPE', 'CONTENT_LENGTH') else 'HTTP_' + name] = str(value)
        body = request.body or b''
        if isinstance(body, str):
            body = body.encode()
        if body:
            env['CONTENT_LENGTH'] = str(len(body))
        process = subprocess.run([fixture.cgi, '-c', str(RUNTIME / 'php.ini'), '-d', 'cgi.force_redirect=0'],
                                 cwd=fixture.web, env=env, input=body, capture_output=True,
                                 timeout=75, check=True)
        header, delim, content = process.stdout.partition(b'\r\n\r\n')
        if not delim:
            raise RuntimeError('PHP CGI did not return headers.')
        message = BytesHeaderParser().parsebytes(header + b'\r\n\r\n')
        response = Response()
        response.url = request.url
        response.status_code = int(message.get('Status', '302' if message.get('Location') else '200').split()[0])
        response.headers = CaseInsensitiveDict(message.items())
        response._content = content
        response._content_consumed = True
        response.encoding = requests.utils.get_encoding_from_headers(response.headers)
        response.raw = SimpleNamespace(_original_response=SimpleNamespace(msg=message), close=lambda: None)
        extract_cookies_to_jar(response.cookies, request, response.raw)
        return response
