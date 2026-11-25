"""Verify V5 classic guest checkout in isolated WordPress, including a different delivery address."""
import json
from urllib.parse import urlparse

from atelier_cgi_fixture import Fixture, ORIGIN
from commerce_smoke import checkout_payload, form_fields, make_sample, page


def run():
    fixture = Fixture()
    try:
        session = fixture.session()
        product = ORIGIN + '/produs/luna/'
        checkout = ORIGIN + '/finalizare-comanda/'
        form, = page(session.get(product)).xpath('//form[@data-personalization]')
        session.post(product, data=form_fields(form), files={'tiron_photo': ('mostra.png', make_sample(), 'image/png')})
        fields = page(session.get(checkout))
        phone, = fields.xpath('//form[@name="checkout"]//input[@type="tel"]')
        assert phone.get('name') == 'billing_phone' and phone.get('aria-required') == 'true'
        assert fields.xpath('//section[@aria-labelledby="order_review_heading"]//*[@id="payment"]')
        assert fields.xpath('//details[not(@open)]//*[@name="order_comments"]')
        action, payload = checkout_payload(session, checkout)
        for value, message in [('', 'Introdu numărul de telefon pentru livrare'), ('nevalid', 'telefon')]:
            payload['billing_phone'] = value
            response = session.post(action, data=payload)
            errors = page(response).xpath('//*[contains(@class,"woocommerce-error")]')
            assert '/order-received/' not in response.url
            assert any(message.casefold() in e.text_content().casefold() for e in errors)
        # Keep the order's billing and courier contacts consistent when addresses differ.
        payload.update(billing_phone='0700000000', ship_to_different_address='1',
                       shipping_first_name='Test', shipping_last_name='Destinatar',
                       shipping_address_1='Adresă sintetică 2', shipping_city='Brașov',
                       shipping_postcode='500001', shipping_country='RO', shipping_state='BV',
                       order_comments='Instrucțiune sintetică')
        response = session.post(action, data=payload)
        assert '/order-received/' in response.url
        order_id = int(urlparse(response.url).path.rstrip('/').split('/')[-1])
        info = fixture.run_wp('eval', f'$o=wc_get_order({order_id});echo wp_json_encode(['
                              '$o->get_billing_phone(),$o->get_shipping_phone(),$o->get_shipping_city(),'
                              '$o->get_customer_note(),$o->get_payment_method()]);')
        assert json.loads(info) == ['0700000000', '0700000000', 'Brașov', 'Instrucțiune sintetică', 'tiron_local_test']
        print('PASS one required delivery phone, missing/invalid rejected, alternate address, saved contacts, optional notes, simulated guest order')
    finally:
        fixture.close()


if __name__ == '__main__':
    run()
