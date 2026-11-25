"""Atelier regressions on a disposable, loopback-only PHP/WooCommerce fixture.

Example with a prepared .runtime and the local PHP CGI executable:
  TIRON_PHP_CGI=/path/to/php-cgi python3 tests/atelier-operations.py
Alternate adapters provide Fixture.run_wp(*args), .session(), .storage, .web and cleanup.
"""
import argparse
from concurrent.futures import ThreadPoolExecutor
from hashlib import sha256
from importlib import import_module
from io import BytesIO
import json
import os
from pathlib import Path
import re
import shutil
import time
from urllib.parse import parse_qs, urlencode, urljoin, urlparse, urlunparse

from PIL import Image, ImageDraw

from commerce_smoke import assert_local, checkout_payload, form_fields, page
from regression import CART, PRODUCT, personalized_form, test_upload_validation


STAGES = (
    'received', 'awaiting-photo-review', 'photo-approved', 'production',
    'quality-control', 'ready-to-ship', 'completed',
)
DASHBOARD = '/wp-admin/admin.php?page=tiron-atelier'
ADMIN_POST = '/wp-admin/admin-post.php'
FIXTURE_PHP = str(Path(__file__).with_name('atelier-fixtures.php'))
PHOTO_FORMATS = ('PNG', 'JPEG', 'WEBP')


def synthetic_photo(index):
    """Generate a uniquely coloured, asymmetric geometric image, never a person's photo."""
    width, height = ((880, 1320) if index % 2 else (1440, 960))
    if index % 7 == 0:
        width, height = (height // 2, width // 2)
    fmt = PHOTO_FORMATS[index % len(PHOTO_FORMATS)]
    canvas = Image.new('RGB', (width, height), (35 + index % 40, 28, 23))
    draw = ImageDraw.Draw(canvas)
    draw.ellipse((width // 5, height // 7, width * 4 // 5, height * 5 // 7),
                 fill=(202, 170 - index % 20, 122))
    draw.rectangle((width // 8, height * 4 // 5, width // 4, height * 9 // 10),
                   fill=(242, 233, 217))
    draw.text((width // 2, height // 2), f'QA-{index:03}', fill=(32, 25, 19))
    data = BytesIO()
    canvas.save(data, format=fmt)
    return (f'fixture-{index:03}.{fmt.lower()}', data.getvalue(), f'image/{fmt.lower()}'), [width, height]


def get_cart(session, origin):
    return page(session.get(urljoin(origin, CART)))


def cart_rows(cart):
    return cart.xpath('//tr[contains(concat(" ", normalize-space(@class), " "), " cart_item ")]')


def copy_session(fixture, session):
    clone = fixture.session()
    clone.cookies.update(session.cookies)
    return clone


def duplicate(fixture, session, url, payload, files=None):
    def send_once(_):
        with copy_session(fixture, session) as client:
            return client.post(url, data=payload, files=files, allow_redirects=False)

    with ThreadPoolExecutor(max_workers=2) as pool:
        return list(pool.map(send_once, range(2)))


def call(fixture, *args):
    return json.loads(fixture.run_wp('eval-file', FIXTURE_PHP, *args))


def add_photo(session, origin, photo, quantity=1, notes='', payload=None):
    if payload is None:
        _, payload = personalized_form(session, origin)
    data = {**payload, 'quantity': str(quantity), 'tiron_notes': notes}
    return session.post(urljoin(origin, PRODUCT), data=data, files={'tiron_photo': photo},
                        allow_redirects=False)


def place_orders(fixture, origin, count=12):
    expected = {}
    for i in range(count):
        session = fixture.session()
        original_lines = []
        for line in range(2 if i % 4 == 1 else 1):
            photo, dimensions = synthetic_photo(100 + i * 2 + line)
            notes = '' if i % 3 == 0 else f'Cadru sintetic {i + 1} / {line + 1}'
            quantity = 1 + (i + line) % 3
            response = add_photo(session, origin, photo, quantity, notes)
            assert response.status_code == 303 and '/cos/' in response.headers['Location'], i
            original_lines.append({'quantity': quantity, 'notes': notes, 'dimensions': dimensions})
        cart = get_cart(session, origin)
        assert len(cart_rows(cart)) == len(original_lines)
        checkout = urljoin(origin, cart.xpath('//a[contains(@class, "checkout-button")]/@href')[0])
        action, fields = checkout_payload(session, checkout)
        email = f'atelier-qa-{i:02}@example.invalid'
        fields.update(billing_email=email, billing_first_name='Atelier', billing_last_name=f'Test {i + 1:02}')
        status = 'on-hold'
        if i == 3:
            rejected = session.post(action, data={**fields, 'billing_first_name': ''}, allow_redirects=False)
            assert '/order-received/' not in rejected.headers.get('Location', '')
            assert not call(fixture, 'find', email)
            assert len(cart_rows(get_cart(session, origin))) == len(original_lines)
        if i in (5, count - 1):
            fixture.run_wp('option', 'update', 'tiron_qa_decline', '1')
            try:
                rejected = session.post(action, data=fields)
                assert 'Plata de test a fost refuzată' in rejected.text
                assert '/order-received/' not in rejected.url
            finally:
                fixture.run_wp('option', 'update', 'tiron_qa_decline', '0')
            failed_ids = call(fixture, 'find', email)
            assert len(failed_ids) == 1
            if i == count - 1:
                status = 'failed'
        if status != 'failed':
            if i == 0:
                responses = duplicate(fixture, session, action, fields)
                assert any('/order-received/' in r.headers.get('Location', '') for r in responses)
            else:
                confirmation = session.post(action, data=fields)
                assert '/order-received/' in confirmation.url
                assert 'woocommerce-order-overview' in confirmation.text
        ids = call(fixture, 'find', email)
        assert len(ids) == 1, (i, ids)
        if i == 5:
            assert ids == failed_ids
        expected[ids[0]] = {'status': status, 'email': email, 'lines': original_lines}
        session.close()
    return expected


def test_cart_edges(fixture, origin):
    session = fixture.session()
    photo, _ = synthetic_photo(123)
    _, payload = personalized_form(session, origin)
    # A nonce belongs to the guest session that generated it.
    with fixture.session() as other:
        personalized_form(other, origin)
        rejected = add_photo(other, origin, photo, payload=payload)
        assert rejected.status_code == 303 and rejected.headers['Location'].endswith('#personalizare')
        assert not cart_rows(get_cart(other, origin))
    requests = duplicate(fixture, session, urljoin(origin, PRODUCT), payload,
                         {'tiron_photo': photo})
    assert all(r.status_code == 303 and '/cos/' in r.headers['Location'] for r in requests)
    cart = get_cart(session, origin)
    assert len(cart_rows(cart)) == 1
    checkout = urljoin(origin, cart.xpath('//a[contains(@class, "checkout-button")]/@href')[0])
    staged, = (fixture.storage / 'staged').glob('*.jpg')
    expired = time.time() - 8 * 86400
    os.utime(staged, (expired, expired))
    fixture.run_wp('eval', 'tiron_cleanup_private_photos();')
    cart = get_cart(session, origin)
    assert not cart_rows(cart) and not staged.exists()
    assert 'nu mai este disponibilă' in cart.text_content()
    assert '/order-received/' not in session.get(checkout).url
    session.close()


def test_recover_draft(fixture, origin):
    with fixture.session() as session:
        _, fields = personalized_form(session, origin)
        rejected = add_photo(session, origin, ('invalid.png', b'not a PNG', 'image/png'),
                             quantity=5, notes='Text QA după respingere', payload=fields)
        assert rejected.status_code == 303 and rejected.headers['Location'].endswith('#personalizare')
        response, recovered = personalized_form(session, origin)
        assert recovered['quantity'] == '5'
        assert page(response).xpath('//textarea[@name="tiron_notes"]/text()') == ['Text QA după respingere']
        assert 'invalid.png' not in response.text
        _, fresh = personalized_form(session, origin)
        assert fresh['quantity'] == '1' and not fresh['tiron_notes']


def inspect_orders(fixture, expected):
    states = call(fixture, 'inspect', ','.join(map(str, expected)))
    photos = set()
    for order_id, wanted in expected.items():
        actual = states[str(order_id)]
        assert actual['status'] == wanted['status'] and not actual['paid'] and actual['paid_date'] is None
        assert actual['payment'] == 'tiron_local_test' and actual['currency'] == 'RON'
        assert actual['email'] == wanted['email'] and actual['note'] == ''
        assert float(actual['total']) == 249 * sum(line['quantity'] for line in wanted['lines'])
        assert len(actual['lines']) == len(wanted['lines'])
        for line, match in zip(actual['lines'], wanted['lines']):
            assert all(line[key] == match[key] for key in ('quantity', 'notes', 'dimensions'))
            assert line['sha256'] and re.fullmatch(r'[0-9a-f]{64}', line['photo'])
            assert line['photo'] not in photos
            photos.add(line['photo'])
    return states


def detail(origin, order_id):
    return urljoin(origin, DASHBOARD + f'&order_id={order_id}')


def stage_form(session, origin, order_id):
    doc = page(session.get(detail(origin, order_id)))
    form, = doc.xpath('//form[@data-atelier-form]')
    return form_fields(form), doc


def update_stage(session, origin, fields, stage, notes='QA · Verificare detalii'):
    return session.post(urljoin(origin, ADMIN_POST),
                        data={**fields, 'stage': stage, 'notes': notes}, allow_redirects=False)


def replace_query(url, **fields):
    parts = urlparse(url)
    query = {**{k: v[-1] for k, v in parse_qs(parts.query).items()}, **fields}
    return urlunparse(parts._replace(query=urlencode(query)))


def queue_metrics(document):
    return {
        item.get('data-atelier-kpi'): int(item.text_content())
        for item in document.xpath('//*[@data-atelier-kpi]')
    }


def test_dashboard(fixture, origin, expected, states, identities):
    admin, customer = fixture.session(), fixture.session()
    admin.cookies.update(identities['admin'])
    customer.cookies.update(identities['customer'])
    dashboard = urljoin(origin, DASHBOARD)
    admin_post = urljoin(origin, ADMIN_POST)
    orders = list(expected)
    target_id = orders[0]
    queue = page(admin.get(dashboard + '&stage=all'))
    assert queue.xpath('//*[@data-tiron-atelier and descendant::*[@data-atelier-filter]]')
    visible = {int(x) for x in queue.xpath('//*[@data-atelier-order]/@data-atelier-order')}
    assert set(orders) <= visible
    pending = page(admin.get(dashboard))
    initial = queue_metrics(pending)
    for order_id, wanted in expected.items():
        assert bool(pending.xpath(f'//*[@data-atelier-order="{order_id}"]')) == (wanted['status'] != 'failed')
    denied = customer.get(dashboard, allow_redirects=False)
    assert denied.status_code in (302, 403) and 'data-tiron-atelier' not in denied.text
    assert admin.get(detail(origin, 99999999), allow_redirects=False).status_code in (400, 403, 404)
    fields, doc = stage_form(admin, origin, target_id)
    assert int(fields['revision']) == 0 and fields['stage'] == 'received'
    primary_line = states[str(target_id)]['lines'][0]
    preview, = doc.xpath('//img[contains(@src, "action=tiron_preview_photo")]/@src')
    download, = doc.xpath('//a[contains(@href, "action=tiron_download_photo")]/@href')
    for url, disposition in ((preview, 'inline'), (download, 'attachment')):
        photo = admin.get(url)
        assert photo.status_code == 200 and photo.headers['Content-Type'] == 'image/jpeg'
        assert disposition in photo.headers['Content-Disposition'] and 'no-store' in photo.headers['Cache-Control']
        assert photo.headers['Cross-Origin-Resource-Policy'] == 'same-origin'
        assert sha256(photo.content).hexdigest() == primary_line['sha256']
        assert fixture.session().get(url, allow_redirects=False).status_code in (400, 403)
        assert customer.get(url, allow_redirects=False).status_code == 403
        foreign_item = states[str(orders[1])]['lines'][0]['id']
        assert admin.get(replace_query(url, item_id=foreign_item), allow_redirects=False).status_code in (403, 404)
        assert admin.get(replace_query(url, _wpnonce='invalid'), allow_redirects=False).status_code == 403
    admin_cookie, = [value for name, value in identities['admin'].items() if name.startswith('wordpress_logged_in_')]
    signed_cross_order = call(fixture, 'foreign_line_url', str(target_id), str(foreign_item), admin_cookie)
    assert admin.get(signed_cross_order, allow_redirects=False).status_code == 404
    assert not list((fixture.web / 'wp-content/uploads').rglob('fixture-*'))
    assert customer.post(admin_post, data=fields, allow_redirects=False).status_code == 403
    assert admin.post(admin_post, data={**fields, 'order_id': 'not-a-number'}, allow_redirects=False).status_code in (400, 403)
    assert admin.post(admin_post, data={**fields, 'order_id': str(orders[1])}, allow_redirects=False).status_code == 403
    assert update_stage(admin, origin, fields, 'invalid-stage').status_code == 400

    for stage in STAGES[1:]:
        assert update_stage(admin, origin, fields, stage).status_code == 303, stage
        fields, _ = stage_form(admin, origin, target_id)
        assert fields['stage'] == stage
    assert int(fields['revision']) == len(STAGES) - 1

    field_two, _ = stage_form(admin, origin, orders[1])
    concurrent = duplicate(fixture, admin, admin_post,
                           {**field_two, 'stage': 'quality-control', 'notes': 'Control sintetic'})
    assert sorted(r.status_code for r in concurrent) == [303, 409]
    assert update_stage(admin, origin, field_two, 'received').status_code == 409
    filtered = page(admin.get(dashboard + '&stage=quality-control'))
    assert filtered.xpath(f'//*[@data-atelier-order="{orders[1]}"]')
    assert not filtered.xpath(f'//*[@data-atelier-order="{target_id}"]')
    review = page(admin.get(dashboard + '&stage=review'))
    assert not review.xpath(f'//*[@data-atelier-order="{target_id}"]')
    assert queue_metrics(review) == {
        'orders': initial['orders'] - 1,
        'units': initial['units'] - sum(line['quantity'] for line in expected[target_id]['lines']),
        'review': initial['review'] - 1 - int(expected[orders[1]]['status'] != 'failed'),
    }

    # Updating Atelier progress and notes must not modify any native Woo order or line data.
    after = inspect_orders(fixture, expected)
    assert after[str(target_id)]['workflow']['stage'] == 'completed'
    assert after[str(orders[1])]['workflow']['notes'] == 'Control sintetic'
    for order_id, before in states.items():
        excluded = {'workflow'}
        assert {k: v for k, v in before.items() if k not in excluded} == {
            k: v for k, v in after[order_id].items() if k not in excluded
        }


def test_missing_plugins(fixture, origin, identities):
    """Disabling Atelier alone preserves the storefront; disabling Woo does not fatal."""
    admin = fixture.session()
    admin.cookies.update(identities['admin'])
    module = fixture.web / 'wp-content/mu-plugins/tiron/atelier.php'
    module.rename(module.with_suffix('.disabled'))
    try:
        assert admin.get(urljoin(origin, DASHBOARD), allow_redirects=False).status_code == 403
        _, fields = personalized_form(fixture.session(), origin)
        assert fields['tiron_nonce']
    finally:
        module.with_suffix('.disabled').rename(module)
    fixture.run_wp('plugin', 'deactivate', 'woocommerce')
    assert fixture.session().get(origin).status_code == 200
    unavailable = admin.get(urljoin(origin, DASHBOARD), allow_redirects=False)
    assert unavailable.status_code in (200, 403, 404)
    assert 'data-atelier-form' not in unavailable.text


def setup_fixture(fixture, mode):
    # The original fixture links MU plugins to the repository. Isolate modifications.
    mounted = fixture.web / 'wp-content/mu-plugins'
    if mounted.is_symlink():
        source = mounted.resolve()
        mounted.unlink()
        shutil.copytree(source, mounted)
    shutil.copyfile(Path(__file__).parent / 'fixtures/decline-gateway.php', mounted / 'tiron-qa-decline.php')
    (mounted / 'tiron-qa-network.php').write_text(
        "<?php\n"
        "add_filter('pre_http_request', static fn() => new WP_Error('qa_offline'), PHP_INT_MAX);\n"
        "add_action('phpmailer_init', static fn() => throw new RuntimeException('Unexpected email'));\n"
    )
    return call(fixture, 'setup', mode)


def run(fixture_factory, origin, mode, orders_only=False):
    assert_local(origin)
    fixture = fixture_factory()
    label = 'HPOS' if mode == 'yes' else 'CPT'
    try:
        identities = setup_fixture(fixture, mode)
        actual_mode = fixture.run_wp('eval', 'echo Automattic\\WooCommerce\\Utilities\\OrderUtil::custom_orders_table_usage_is_enabled() ? "yes" : "no";')
        assert actual_mode == mode
        test_upload_validation(fixture.session(), origin)
        test_cart_edges(fixture, origin)
        test_recover_draft(fixture, origin)
        print(f'PASS {label} invalid, oversized, absent photo, safe one-time recovery, guest nonce, concurrent cart and expiry', flush=True)
        expected = place_orders(fixture, origin)
        states = inspect_orders(fixture, expected)
        print(f'PASS {label} {len(states)} orders · multiple lines and quantities, portrait/landscape, missing notes, interrupted checkout, decline/retry, concurrent checkout', flush=True)
        if not orders_only:
            test_dashboard(fixture, origin, expected, states, identities)
            print(f'PASS {label} Atelier transitions, review/filtering, concurrent/stale saves, cross-order and unauthorized photo denial, Woo status/lines unchanged', flush=True)
            test_missing_plugins(fixture, origin, identities)
            print(f'PASS {label} missing Atelier module and WooCommerce dependency degrade without fatal errors', flush=True)
    finally:
        fixture.close()


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--fixture-module', default='atelier_cgi_fixture')
    parser.add_argument('--mode', choices=['yes', 'no'])
    parser.add_argument('--orders-only', action='store_true')
    arguments = parser.parse_args()
    adapter = import_module(arguments.fixture_module)
    for mode in ([arguments.mode] if arguments.mode else ['yes', 'no']):
        run(adapter.Fixture, adapter.ORIGIN, mode, arguments.orders_only)
