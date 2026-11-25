"""Verify release archives against their sources and the checksum manifest."""
from hashlib import sha256
from pathlib import Path
from zipfile import ZipFile

ROOT = Path(__file__).resolve().parents[1]
DIST = ROOT / 'dist/hostico'


def contents(archive):
    with ZipFile(DIST / archive) as package:
        assert package.testzip() is None
        files = package.namelist()
        assert len(files) == len(set(files))
        assert not any(part.startswith('.') for name in files for part in name.split('/'))
        return {name: package.read(name) for name in files}


def main():
    for line in (DIST / 'SHA256SUMS').read_text().splitlines():
        expected, filename = line.split('  ')
        assert sha256((DIST / filename).read_bytes()).hexdigest() == expected
    theme = contents('tir-on-theme.zip')
    assert 'tir-on/style.css' in theme and 'tir-on/views/product.php' in theme
    assert all(name.startswith('tir-on/') and data == (ROOT / 'theme' / name).read_bytes() for name, data in theme.items())
    mu = contents('tir-on-mu-plugins.zip')
    assert set(mu) == {
        'tir-on-catalog.php', 'tir-on-local-preview.php', 'tir-on-hostico.php',
        'tiron/admin-photos.php', 'tiron/cart-orders.php', 'tiron/private-photos.php',
        'tiron/test-payments.php', 'tiron/hostico/seed.php', 'tiron/hostico/emails.php',
    }
    overrides = {
        'tir-on-hostico.php': 'deployment/hostico/mu-plugins/tir-on-hostico.php',
        'tiron/hostico/seed.php': 'setup/seed.php',
        'tiron/hostico/emails.php': 'deployment/hostico/emails.php',
    }
    assert all(data == (ROOT / overrides.get(name, 'local/mu-plugins/' + name)).read_bytes() for name, data in mu.items())
    assert "define( 'TIRON_ENABLE_TEST_CHECKOUT', false );" in (DIST / 'wp-config-tiron.txt').read_text()
    print('PASS integritate, structură și surse arhive Hostico; gateway-ul local este exclus')


if __name__ == '__main__':
    main()
