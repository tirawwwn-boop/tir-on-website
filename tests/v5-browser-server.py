"""Loopback bridge for the disposable WordPress CGI fixture used by browser QA."""
import argparse
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import mimetypes
from pathlib import Path
import shutil
import signal
import subprocess
import sys
from urllib.parse import unquote, urlsplit

from atelier_cgi_fixture import Fixture, ORIGIN, ROOT, RUNTIME

STATIC = {'.css', '.js', '.jpg', '.jpeg', '.png', '.webp', '.avif', '.svg', '.ico', '.gif', '.woff', '.woff2'}


def prepare(fixture, revision):
    content = fixture.web / 'wp-content'
    if revision:
        # Check out only versioned storefront code. The local fixture provides WordPress and its test DB.
        baseline = fixture.root / 'baseline'
        baseline.mkdir()
        archive = subprocess.run(
            ['git', 'archive', revision, 'theme/tir-on', 'local/mu-plugins'], cwd=ROOT,
            capture_output=True, check=True,
        ).stdout
        subprocess.run(['tar', '-x', '-C', str(baseline)], input=archive, check=True)
        for link in ['themes', 'mu-plugins']:
            (content / link).unlink()
        (content / 'themes').mkdir()
        (content / 'themes' / 'tir-on').symlink_to(baseline / 'theme/tir-on')
        (content / 'mu-plugins').symlink_to(baseline / 'local/mu-plugins')
    if not revision:
        fixture.run_wp('eval', "$c = new WC_Coupon(wc_get_coupon_id_by_code('qa-v5-10')); "
                       "$c->set_code('qa-v5-10'); $c->set_discount_type('percent'); $c->set_amount(10); $c->save();")
    uploads = content / 'uploads'
    uploads.mkdir(exist_ok=True)
    for sample in (RUNTIME / 'wordpress/wp-content/uploads').glob('woocommerce-placeholder*.webp'):
        shutil.copyfile(sample, uploads / sample.name)


def handler(fixture):
    class Handler(BaseHTTPRequestHandler):
        def do_GET(self):
            self.respond()

        do_POST = do_GET
        do_HEAD = do_GET

        def respond(self):
            path = Path(unquote(urlsplit(self.path).path).lstrip('/'))
            if '..' in path.parts:
                self.send_error(404)
                return
            local = fixture.web / path
            if path.parts and path.parts[0] in ('wp-content', 'wp-admin', 'wp-includes') and path.suffix in STATIC:
                if not local.is_file():
                    self.send_error(404)
                    return
                data = local.read_bytes()
                self.send_response(200)
                self.send_header('Content-Type', mimetypes.guess_type(local)[0] or 'application/octet-stream')
            else:
                headers = {k: v for k, v in self.headers.items() if k.lower() not in ('host', 'connection', 'content-length')}
                request_body = self.rfile.read(int(self.headers.get('Content-Length', 0)))
                with fixture.session() as session:
                    response = session.request(self.command, ORIGIN + self.path, data=request_body, headers=headers,
                                               allow_redirects=False)
                data = response.content
                self.send_response(response.status_code)
                for key, value in response.raw._original_response.msg.items():
                    if key.lower() not in ('status', 'connection', 'transfer-encoding', 'content-length'):
                        self.send_header(key, value)
            self.send_header('Content-Length', str(len(data)))
            self.end_headers()
            if self.command != 'HEAD':
                try:
                    self.wfile.write(data)
                except (BrokenPipeError, ConnectionResetError):
                    pass

        def log_message(self, *args):
            pass

    return Handler


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--baseline', metavar='GIT_REVISION', help='Load the storefront at this Git revision.')
    fixture = Fixture()
    try:
        prepare(fixture, parser.parse_args().baseline)
        signal.signal(signal.SIGTERM, lambda *_: sys.exit())
        with ThreadingHTTPServer(('127.0.0.1', 4173), handler(fixture)) as server:
            print('Test fixture ready at', ORIGIN, flush=True)
            server.serve_forever()
    finally:
        fixture.close()
