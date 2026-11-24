"""Exercise the classic WooCommerce guest purchase using only the local simulation gateway.

Run against a local test installation. Creates one test order. Needs requests, lxml
and Pillow. The fixture driver can also supply a requests-compatible CGI session.
"""
import io
import sys
from urllib.parse import urljoin, urlparse

import requests
from lxml import html
from PIL import Image


def make_sample():
    """Generate a PNG on demand. No customer image or file is kept in the repository."""
    stream = io.BytesIO()
    Image.new('RGB', (48, 32), '#c9a878').save(stream, format='PNG')
    return stream.getvalue()


def form_fields(form):
    fields = {}
    for node in form.xpath('.//input[@name] | .//textarea[@name] | .//select[@name]'):
        kind = node.get('type', '')
        if kind in ('file', 'submit') or node.get('disabled') is not None:
            continue
        if kind in ('checkbox', 'radio') and node.get('checked') is None:
            continue
        if node.tag == 'select':
            options = node.xpath('./option[@selected]/@value') or node.xpath('./option[1]/@value')
            value = options[0] if options else ''
        else:
            value = node.get('value', '')
        fields[node.get('name')] = value
    return fields


def page(response):
    response.raise_for_status()
    return html.fromstring(response.content)


def run_flow(session, base_url):
    if urlparse(base_url).hostname not in ('127.0.0.1', 'localhost', '::1'):
        raise ValueError('Testul acceptă numai o instalare locală.')
    product_url = urljoin(base_url, '/produs/luna/')
    product_page = page(session.get(product_url))
    form, = product_page.xpath('//form[@enctype="multipart/form-data"]')
    payload = form_fields(form)
    assert payload['tiron_nonce'] and form.xpath('.//input[@name="tiron_photo" and @required]')
    notes = 'Test automat TIR-ON: imagine sintetică.'
    payload.update(quantity='2', tiron_notes=notes)
    files = {'tiron_photo': ('mostra-qa.png', make_sample(), 'image/png')}
    cart_response = session.post(product_url, data=payload, files=files)
    cart = page(cart_response)
    rows = cart.xpath('//tr[contains(concat(" ",normalize-space(@class)," ")," cart_item ")]')
    assert len(rows) == 1 and notes in rows[0].text_content(), 'Personalizarea lipsește din coș.'
    assert rows[0].xpath('.//input[@type="number"]/@value') == ['2']
    assert cart.xpath('//span[contains(@class,"bag-count")]/text()') == ['2']
    assert 'mostra-qa.png' not in cart_response.text
    # Repeating the completed form submission does not double the requested quantity.
    replay = page(session.post(product_url, data=payload, files=files))
    assert replay.xpath('//span[contains(@class,"bag-count")]/text()') == ['2']

    checkout_url = urljoin(base_url, cart.xpath('//a[contains(@class,"checkout-button")]/@href')[0])
    checkout = page(session.get(checkout_url))
    form, = checkout.xpath('//form[@name="checkout"]')
    rates = form.xpath('.//input[@name="shipping_method[0]"]/@value')
    assert rates, 'Configurează mai întâi livrarea locală de test.'
    assert form.xpath('.//input[@name="payment_method" and @value="tiron_local_test"]'), 'Simulatorul local trebuie activat.'
    fields = form_fields(form)
    fields.pop('ship_to_different_address', None)
    fields.update({
        'billing_first_name': 'Test', 'billing_last_name': 'TIRON',
        'billing_email': 'tiron-qa@example.invalid', 'billing_country': 'RO',
        'billing_address_1': 'Adresă de test 1', 'billing_city': 'București',
        'billing_state': 'B', 'billing_postcode': '010101', 'billing_phone': '0700000000',
        'shipping_method[0]': rates[0], 'payment_method': 'tiron_local_test',
        'woocommerce_checkout_place_order': '1',
    })
    if form.xpath('.//input[@name="terms"]'):
        fields['terms'] = 'on'
    confirmation = session.post(urljoin(checkout_url, form.get('action')), data=fields)
    result = page(confirmation)
    assert '/order-received/' in confirmation.url, 'WooCommerce nu a confirmat comanda locală.'
    assert result.xpath('//*[contains(@class,"woocommerce-order-overview")]')
    assert 'mostra-qa.png' not in confirmation.text
    assert '_tiron_photo_id' not in confirmation.text
    return int(urlparse(confirmation.url).path.rstrip('/').split('/')[-1])


if __name__ == '__main__':
    if len(sys.argv) != 2:
        sys.exit('Utilizare: python3 tests/commerce_smoke.py http://localhost:8080/')
    order_id = run_flow(requests.Session(), sys.argv[1])
    print('Comandă simulată confirmată, ID', order_id)
