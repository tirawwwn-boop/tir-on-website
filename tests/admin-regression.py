"""Verify private photo access on a local synthetic order."""
import argparse
from getpass import getpass
from urllib.parse import urljoin

import requests

from commerce_smoke import assert_local, page


def verify_admin_photo(admin, fresh_session, base_url, order_id):
    assert_local(base_url)
    for route in (
        f'/wp-admin/admin.php?page=wc-orders&action=edit&id={order_id}',
        f'/wp-admin/post.php?post={order_id}&action=edit',
    ):
        response = admin.get(urljoin(base_url, route))
        if response.status_code != 200:
            continue
        doc = page(response)
        links = doc.xpath('//a[contains(@href,"action=tiron_download_photo")]/@href')
        if links:
            break
    else:
        raise AssertionError('Fotografia comenzii nu apare în administrare.')

    assert 'Observații pentru atelier' in doc.text_content()
    link = urljoin(base_url, links[0])
    photo = admin.get(link)
    assert photo.status_code == 200 and photo.headers['Content-Type'] == 'image/jpeg'
    assert photo.content.startswith(b'\xff\xd8') and 'no-store' in photo.headers['Cache-Control']
    assert f'Luna-{order_id}-' in photo.headers['Content-Disposition']
    anonymous = fresh_session().get(link, allow_redirects=False)
    assert anonymous.status_code in (400, 403)
    invalid_nonce = link.split('&_wpnonce=')[0] + '&_wpnonce=invalid'
    assert admin.get(invalid_nonce).status_code == 403
    print('PASS comandă și fotografie în admin, JPEG privat, vizitator și nonce greșit refuzate')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('url')
    parser.add_argument('order_id', type=int)
    parser.add_argument('user')
    args = parser.parse_args()
    assert_local(args.url)
    session = requests.Session()
    login = urljoin(args.url, '/wp-login.php')
    session.get(login).raise_for_status()
    session.post(login, data={
        'log': args.user, 'pwd': getpass('Parola administratorului local: '),
        'testcookie': '1', 'redirect_to': urljoin(args.url, '/wp-admin/'),
    }).raise_for_status()
    verify_admin_photo(session, requests.Session, args.url, args.order_id)
