"""Local-only HTTP regression. Test data is generated and never sent externally."""
import argparse
import json
import shlex
import subprocess
from urllib.parse import urljoin, urlparse

import requests

from commerce_smoke import assert_local, checkout_payload, form_fields, make_sample, page, run_flow

PRODUCT = '/produs/luna/'
CART = '/cos/'


def personalized_form(session, base_url):
    response = session.get(urljoin(base_url, PRODUCT))
    form, = page(response).xpath('//form[@data-personalization]')
    return response, form_fields(form)


def test_catalog(session, base_url):
    response, payload = personalized_form(session, base_url)
    assert payload['tiron_nonce']
    assert 'no-store' in response.headers['Cache-Control']
    product = page(response)
    home = page(session.get(base_url))
    assert product.xpath('//main//h1/text()') == ['Luna']
    assert product.xpath('//*[@class="lp-price"]/text()') == ['249 RON']
    assert home.xpath('//*[@data-hero]') and home.xpath('//*[@id="descopera-lumina"]')
    for document in (home, product):
        assert document.xpath('//link[@rel="canonical" and starts-with(@href,"http")]')
        assert document.xpath('//meta[@name="robots" and contains(@content,"noindex")]')
        assert document.xpath('//meta[@property="og:image" and contains(@content,"social-luna.jpg")]')
    graphs = [json.loads(node) for node in product.xpath('//script[@type="application/ld+json"]/text()')]
    entries = [entry for graph in graphs for entry in graph.get('@graph', [graph])]
    schema, = [entry for entry in entries if entry.get('@type') == 'Product']
    assert not schema.get('aggregateRating') and not schema.get('review')
    assert schema.get('image')
    offer, = schema['offers']
    assert offer['priceCurrency'] == 'RON' and float(offer['price']) == 249
    assert 'priceValidUntil' not in offer


def test_upload_validation(session, base_url):
    product_url = urljoin(base_url, PRODUCT)
    for label, photo in (
        ('missing', None),
        ('unsupported extension', ('qa.gif', make_sample(), 'image/gif')),
        ('invalid raster', ('qa.png', b'not an image', 'image/png')),
        ('oversized format', ('qa.png', make_sample() + b'\0' * (11 * 1024 * 1024), 'image/png')),
    ):
        _, data = personalized_form(session, base_url)
        files = {'tiron_photo': photo} if photo else None
        response = session.post(product_url, data=data, files=files)
        assert response.url.endswith('#personalizare'), label
        assert page(response).xpath('//*[contains(@class,"woocommerce-error")]'), label
        assert page(session.get(urljoin(base_url, CART))).xpath('//span[contains(@class,"bag-count")]/text()') == ['0'], label


def test_payment_decline(session, base_url, configure_decline):
    _, data = personalized_form(session, base_url)
    files = {'tiron_photo': ('qa.png', make_sample(), 'image/png')}
    cart = page(session.post(urljoin(base_url, PRODUCT), data=data, files=files))
    checkout_url = urljoin(base_url, cart.xpath('//a[contains(@class,"checkout-button")]/@href')[0])
    action, fields = checkout_payload(session, checkout_url)
    configure_decline(True)
    try:
        failed = session.post(action, data=fields)
        assert '/order-received/' not in failed.url
        assert 'Plata de test a fost refuzată' in failed.text
        assert page(session.get(urljoin(base_url, CART))).xpath('//tr[contains(@class,"cart_item")]')
    finally:
        configure_decline(False)
    retry = session.post(action, data=fields)
    assert '/order-received/' in retry.url
    assert page(retry).xpath('//*[contains(@class,"woocommerce-order-overview")]')
    replay = session.post(action, data=fields)
    assert '/order-received/' not in replay.url or replay.url == retry.url
    return int(urlparse(retry.url).path.rstrip('/').split('/')[-1])


def run(session_factory, base_url, configure_decline=None):
    assert_local(base_url)
    test_catalog(session_factory(), base_url)
    test_upload_validation(session_factory(), base_url)
    order = run_flow(session_factory(), base_url)
    print(f'PASS catalog, SEO privat, patru validări upload, retry coș, checkout oaspete și confirmare {order}', flush=True)
    if configure_decline:
        retry_order = test_payment_decline(session_factory(), base_url, configure_decline)
        print(f'PASS refuz simulat, coș păstrat și confirmare la reîncercare {retry_order}', flush=True)
    return order


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('url')
    parser.add_argument('--wp-cli', help='Comanda WP-CLI pentru instanța locală de QA, cu fixtures/decline-gateway.php în MU.')
    args = parser.parse_args()
    configure_decline = None
    if args.wp_cli:
        assert_local(args.url)
        wp = shlex.split(args.wp_cli)
        environment = subprocess.check_output(wp + ['eval', 'echo wp_get_environment_type();'], text=True)
        if environment.strip() != 'local':
            raise SystemExit('Testează refuzul plății numai într-o instanță locală.')

        def configure_decline(enabled):
            subprocess.run(wp + ['option', 'update', 'tiron_qa_decline', '1' if enabled else '0'],
                           check=True, stdout=subprocess.DEVNULL)

    run(requests.Session, args.url, configure_decline)
