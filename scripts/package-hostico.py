#!/usr/bin/env python3
"""Build the browser-installable Hostico packages from tracked release sources."""
from hashlib import sha256
from pathlib import Path
import subprocess
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo

ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / 'dist/hostico'
HOSTICO = ROOT / 'deployment/hostico'
THEME = ROOT / 'theme'
MU = ROOT / 'local/mu-plugins'


def archive(path, entries):
    with ZipFile(path, 'w', compression=ZIP_DEFLATED, compresslevel=9) as output:
        for name, source in sorted(entries.items()):
            assert source.is_file() and not source.is_symlink(), source
            metadata = ZipInfo(name)
            metadata.external_attr = 0o100644 << 16
            output.writestr(metadata, source.read_bytes(), compress_type=ZIP_DEFLATED, compresslevel=9)
    with ZipFile(path) as output:
        assert output.testzip() is None
        assert set(output.namelist()) == set(entries)


def build():
    OUTPUT.mkdir(parents=True, exist_ok=True)
    tracked = subprocess.check_output(['git', 'ls-files', '-z', '--', 'theme/tir-on'], cwd=ROOT)
    theme = {
        path.relative_to(THEME).as_posix(): path
        for path in (ROOT / name for name in tracked.decode().split('\0') if name)
        if path.suffix in {'.php', '.css', '.js', '.txt', '.webp', '.jpg', '.png', '.svg', '.woff2'}
    }
    mu = {
        name: MU / name
        for name in (
            'tir-on-catalog.php', 'tir-on-local-preview.php', 'tiron/admin-photos.php',
            'tiron/cart-orders.php', 'tiron/private-photos.php', 'tiron/test-payments.php',
            'tiron/atelier.php', 'tiron/atelier.css',
        )
    }
    mu.update({
        'tir-on-hostico.php': HOSTICO / 'mu-plugins/tir-on-hostico.php',
        'tiron/hostico/seed.php': ROOT / 'setup/seed.php',
        'tiron/hostico/emails.php': HOSTICO / 'emails.php',
    })
    archive(OUTPUT / 'tir-on-theme.zip', theme)
    archive(OUTPUT / 'tir-on-mu-plugins.zip', mu)
    config_names = ['wp-config-tiron.txt', 'php-settings.txt']
    for name in config_names:
        (OUTPUT / name).write_bytes((HOSTICO / 'config' / name).read_bytes())
    names = ['tir-on-theme.zip', 'tir-on-mu-plugins.zip', *config_names]
    (OUTPUT / 'SHA256SUMS').write_text(''.join(
        f'{sha256((OUTPUT / name).read_bytes()).hexdigest()}  {name}\n' for name in names
    ))
    print('Hostico packages verified:', ', '.join(names))


if __name__ == '__main__':
    build()
