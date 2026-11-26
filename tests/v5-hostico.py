"""Exercise the cPanel release entrypoint with a local clone and synthetic WordPress tree."""
import json
import os
from pathlib import Path
import subprocess
import tempfile
from zipfile import ZipFile
from io import BytesIO

ROOT = Path(__file__).resolve().parents[1]
BASE = os.environ.get('TIRON_QA_RELEASE_BASE', '1d6ad55b78893399fdea32f4e0fa2f383c7704b6')


def test(account):
    repo = account / 'clone'
    subprocess.run(['git', 'clone', '--local', '--no-hardlinks', '--quiet', str(ROOT), str(repo)], check=True)
    def git(*args):
        return subprocess.check_output(['git', *args], cwd=repo)
    git('remote', 'set-url', 'origin', 'git@github.com:tirawwwn-boop/tir-on-website.git')
    head = git('rev-parse', 'HEAD').decode().strip()
    control = account / 'tiron-release-control'
    control.mkdir(mode=0o700)
    site = account / 'public_html'
    content = site / 'wp-content'
    def packages(revision):
        entries = {}
        for name, folder in [('theme', 'themes'), ('mu-plugins', 'mu-plugins')]:
            archive = git('show', revision + ':dist/hostico/tir-on-' + name + '.zip')
            with ZipFile(BytesIO(archive)) as files:
                for item in files.namelist():
                    entries[content / folder / item] = files.read(item)
        return entries
    old, new = packages(BASE), packages(head)
    sentinels = [site / 'wp-config.php', site / 'wp-includes/version.php', content / 'uploads/example',
                 content / 'mu-plugins/another-plugin.php', account / 'private-photos/test']
    for path, data in {**old, **dict.fromkeys(sentinels, b'synthetic')}.items():
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_bytes(data)
    def run(action=None, success=True, **fields):
        if action:
            request = dict(action=action, approved_commit=head, installed_commit=BASE,
                           wordpress_root=str(site), public_roots=[str(site)])
            (control / 'request.json').write_text(json.dumps({**request, **fields}))
        p = subprocess.run(['bash', str(ROOT / 'scripts/runtime-php.sh'),
            'deployment/hostico/release/hostico.php'], env=dict(os.environ, HOME=str(account)),
            cwd=repo, capture_output=True, text=True)
        assert (p.returncode == 0) == success, p.stdout + p.stderr
        assert all(s.read_bytes() == b'synthetic' for s in sentinels)
    run()
    run('apply', False)
    run('plan', False, approved_commit=BASE)
    first = content / 'themes/tir-on/functions.php'
    first.write_bytes(b'local edit')
    run('plan', False)
    assert first.read_bytes() == b'local edit'
    first.write_bytes(old[first])
    run('plan')
    assert all(p.read_bytes() == data for p,data in old.items())
    run('apply')
    assert all(p.read_bytes() == data for p,data in new.items())
    state = json.loads((control / 'state.json').read_text())
    run('rollback', backup_id=state['backup'])
    assert all(p.read_bytes() == data for p,data in old.items())
    assert not any(p.exists() for p in new.keys() - old.keys())
    print('PASS no approval, wrong SHA, missing plan, local drift, full apply and rollback, preserved unmanaged files')

if __name__ == '__main__':
    with tempfile.TemporaryDirectory() as tmp:
        test(Path(tmp))
