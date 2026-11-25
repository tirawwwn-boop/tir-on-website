"""V5 checkout, discounts and guest isolation. Uses only a disposable local WP fixture."""
import json
from urllib.parse import urlparse

from atelier_cgi_fixture import Fixture, ORIGIN
from commerce_smoke import checkout_payload, form_fields, make_sample, page
from regression import CART, PRODUCT, personalized_form, test_upload_validation


def add_luna(session, notes):
    _, fields = personalized_form(session, ORIGIN)
    image = ('personalizare-qa.png', make_sample(), 'image/png')
    response = session.post(ORIGIN + PRODUCT, data={**fields, 'tiron_notes': notes},
                            files={'tiron_photo': image})
    assert response.url.endswith(CART) and notes in response.text
    return page(response)


def total(cart):
    [amount] = cart.xpath('//tr[contains(@class,"order-total")]//bdi')
    return amount.text_content().replace('\xa0', ' ').strip()


def cart_form(cart):
    [form] = cart.xpath('//form[contains(@class,"woocommerce-cart-form")]')
    return form_fields(form)


def update_quantity(session, cart, value):
    fields = cart_form(cart)
    [quantity] = [name for name in fields if name.startswith('cart[') and name.endswith('[qty]')]
    return page(session.post(ORIGIN + CART, data={**fields, quantity: str(value), 'update_cart': '1'}))


def apply_coupon(session, cart, code):
    return page(session.post(ORIGIN + CART, data={
        **cart_form(cart), 'coupon_code': code, 'apply_coupon': '1',
    }))


def check_flow(fixture):
    with fixture.session() as first, fixture.session() as second:
        test_upload_validation(second, ORIGIN)
        cart = add_luna(first, 'Personalizare sintetică A')
        assert total(cart).startswith('249,00')
        other = add_luna(second, 'Personalizare sintetică B')
        assert 'sintetică A' not in other.text_content()
        assert 'sintetică B' not in cart.text_content()

        cart = update_quantity(first, cart, 2)
        assert total(cart).startswith('498,00')
        cart = update_quantity(first, cart, 100)
        assert total(cart).startswith('498,00') and cart.xpath('//*[contains(@class,"woocommerce-error")]')
        cart = apply_coupon(first, cart, 'inexistent-v5-test')
        assert total(cart).startswith('498,00') and cart.xpath('//*[contains(@class,"woocommerce-error")]')
        cart = apply_coupon(first, cart, 'qa-v5-10')
        assert total(cart).startswith('448,20')
        assert not page(second.get(ORIGIN + CART)).xpath('//tr[contains(@class,"cart-discount")]')
        [remove_coupon] = cart.xpath('//a[contains(@class,"woocommerce-remove-coupon")]/@href')
        cart = page(first.get(remove_coupon))
        assert total(cart).startswith('498,00')
        cart = apply_coupon(first, cart, 'qa-v5-10')

        [remove_item] = other.xpath('//a[contains(@class,"remove") and contains(@href,"remove_item")]/@href')
        assert not page(second.get(remove_item)).xpath('//tr[contains(@class,"cart_item")]')
        assert total(page(first.get(ORIGIN + CART))).startswith('448,20')

        [checkout] = cart.xpath('//a[contains(@class,"checkout-button")]/@href')
        form, fields = checkout_payload(first, checkout)
        markup = page(first.get(checkout))
        [phone] = markup.xpath('//form[@name="checkout"]//input[@type="tel"]')
        assert phone.get('name') == 'billing_phone'
        assert phone.get('required') is not None or phone.get('aria-required') == 'true'
        assert total(markup).startswith('448,20')
        failed = first.post(form, data={**fields, 'billing_phone': ''})
        assert '/order-received/' not in failed.url and 'telefon' in page(failed).text_content().lower()
        assert total(page(first.get(ORIGIN + CART))).startswith('448,20')

        receipt = first.post(form, data=fields)
        assert '/order-received/' in receipt.url and 'woocommerce-order-overview' in receipt.text
        order = int(urlparse(receipt.url).path.rstrip('/').split('/')[-1])
        result = fixture.run_wp('eval', f"""
            $o = wc_get_order({order});
            $lines = array_values($o->get_items());
            $photo = tiron_order_photo_path($lines[0]->get_meta('_tiron_photo_id'));
            echo wp_json_encode([
                'guest' => $o->get_customer_id(), 'phone' => $o->get_billing_phone(),
                'notes' => $lines[0]->get_meta('_tiron_notes'), 'qty' => $lines[0]->get_quantity(),
                'test' => $o->get_meta('_tiron_local_simulation'),
                'coupons' => $o->get_coupon_codes(), 'total' => $o->get_total(), 'status' => $o->get_status(),
                'dimensions' => $photo ? array_slice(getimagesize($photo), 0, 2) : null,
            ]);
        """)
        assert json.loads(result) == {
            'guest': 0, 'phone': fields['billing_phone'], 'notes': 'Personalizare sintetică A',
            'qty': 2, 'test': 'yes', 'coupons': ['qa-v5-10'], 'total': '448.20', 'status': 'on-hold',
            'dimensions': [48, 32],
        }, result
        assert not page(first.get(ORIGIN + CART)).xpath('//tr[contains(@class,"cart_item")]')
        assert not list((fixture.web / 'wp-content/uploads').rglob('personalizare-qa*'))


def main():
    fixture = Fixture()
    try:
        fixture.run_wp('eval', """
            if ('local' !== wp_get_environment_type() || !tiron_local_test_checkout_enabled()) {
                throw new RuntimeException('QA local only');
            }
            update_option('woocommerce_enable_coupons', 'yes');
            $coupon = new WC_Coupon();
            $coupon->set_code('qa-v5-10');
            $coupon->set_discount_type('percent');
            $coupon->set_amount('10');
            $coupon->save();
        """)
        check_flow(fixture)
        print('PASS valid/invalid uploads, isolated sessions, quantity limits, totals, coupon apply/remove, '
              'guest checkout, required telephone, order confirmation, private photo, notes and dimensions')
    finally:
        fixture.close()


if __name__ == '__main__':
    main()
