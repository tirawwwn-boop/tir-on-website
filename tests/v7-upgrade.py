"""Install the actual V5/V7 Hostico ZIPs on one isolated WooCommerce database; exercise the real admin forms."""
from io import BytesIO
import json
from pathlib import Path
import sqlite3
import shutil
import subprocess
from zipfile import ZipFile

from lxml import html
from atelier_cgi_fixture import Fixture, ROOT, ORIGIN

V5 = '5127a78c54242731d367c85b64af8621f8516891'
PHP = str(ROOT / 'tests/v7-fixtures.php')
ADMIN = ORIGIN + '/wp-admin/admin.php?page=tiron-commercial&studio='
POST = ORIGIN + '/wp-admin/admin-post.php'


def install(fixture, base=None):
    for name, directory in [('theme','themes'), ('mu-plugins','mu-plugins')]:
        path = fixture.web / 'wp-content' / directory
        if path.is_symlink():
            path.unlink()
        elif path.exists():
            shutil.rmtree(path)  # exclusively the fixture's extracted package tree
        path.mkdir()
        rel = f'dist/hostico/tir-on-{name}.zip'
        source = BytesIO(subprocess.check_output(['git','show',V5+':'+rel],cwd=ROOT)) if base else ROOT / rel
        with ZipFile(source) as z:
            assert z.testzip() is None
            z.extractall(path)


def form(session, tab):
    page = html.fromstring(session.get(ADMIN + tab).content)
    node = page.xpath('//form[.//button[@value="save"]]')[0]
    data = dict(node.form_values())
    data['op'] = 'save'
    return data


def main():
    fx=Fixture()
    try:
        install(fx, V5)
        fx.run_wp('eval-file',PHP,'seed')
        creds=json.loads(fx.run_wp('eval-file',PHP,'login'))
        admin,guest,limited=fx.session(),fx.session(),fx.session()
        admin.cookies.update(creds['administrator'])
        limited.cookies.update(creds['subscriber'])
        # Complete the official gateway's normal first admin_init on V5 itself.
        assert admin.get(ORIGIN+'/wp-admin/admin.php?page=tiron-profit').status_code==200
        assert fx.run_wp('eval',"echo function_exists('ts_state') || function_exists('tiron_commercial_options') ? 'new':'v5';")=='v5'
        def schema():
            with sqlite3.connect(fx.root/'data/tiron.sqlite') as db:
                return db.execute("select name,sql from sqlite_master where type='table' order by name").fetchall()
        old_schema=schema()
        snapshot=lambda: fx.run_wp('eval-file',PHP,'snapshot')
        reference=snapshot()
        private=fx.storage / 'qa-private.png'
        private.write_bytes(b'private synthetic fixture')
        assert 'Copertă V5 sintetică' in fx.session().get(ORIGIN).text
        install(fx)
        assert snapshot()==reference
        assert fx.run_wp('eval',"echo get_option('tiron_brand_studio_v1',false)===false?'absent':'exists';")=='absent'
        media, invalid, legal=json.loads(fx.run_wp('eval-file',PHP,'media'))
        def send(data,code=302):
            response=admin.post(POST,data=data,allow_redirects=False)
            assert response.status_code==code,(response.status_code,html.fromstring(response.content).text_content()[-700:] if response.content else '')
        def save(tab,fields):
            data=form(admin,tab)
            data.update({'content['+key+']':value for key,value in fields.items()})
            send(data)
        initial=form(admin,'hero')
        assert limited.get(ADMIN+'brand').status_code==403
        assert limited.post(POST,data=initial).status_code==403
        send({**initial,'_wpnonce':'invalid'},403)
        send({**initial,'content[tiron_hero_title]':'<b>Ciornă privată</b>', 'content[slides]':'ambient'})
        send(initial,409)
        assert 'Ciornă privată' not in guest.get(ORIGIN).text
        assert form(admin,'hero')['content[tiron_hero_title]']=='Ciornă privată'
        preview=html.fromstring(admin.get(ADMIN+'brand').content).xpath('//a[contains(@href,"tiron_studio=")]/@href')[0]
        draft=admin.get(preview)
        assert draft.status_code==200 and 'Ciornă privată' in draft.text and 'no-store' in draft.headers['Cache-Control']
        assert guest.get(preview).status_code==403 and limited.get(preview).status_code==403
        send({**form(admin,'brand'),'content[logo]':invalid},400)
        send({**form(admin,'footer'),'content[instagram]':'javascript:alert(1)'},400)
        save('brand',{'logo':media,'icon':media,'type':'classic','palette':'bronze'})
        save('story',{'hide_cadouri':'1','tiron_image_relief':media})
        send({**form(admin,'story'),'op':'publish','confirm':'1'},400)
        save('story',{'hide_cadouri':'1','tiron_image_relief':''})
        save('luna',{'gallery':media,'spec_packaging':'Ambalaj editorial QA','package_image':media})
        save('footer',{'privacy':legal,'email':'qa@example.invalid'})
        save('campaign',{'q1':'Cum aleg fotografia?','a1':'Răspuns editorial sintetic.','announcement':'Anunț sintetic'})
        save('seo',{'home_title_seo':'Titlu SEO QA','home_desc_seo':'Descriere QA','home_social':media})
        send({**form(admin,'seo'),'op':'publish'},400)
        publish=lambda:send({**form(admin,'brand'),'op':'publish','confirm':'1'})
        publish()
        p=html.fromstring(guest.get(ORIGIN).content)
        assert p.xpath('//title/text()')==['Titlu SEO QA'] and p.xpath('//meta[@name="description"]/@content')==['Descriere QA']
        assert p.xpath('count(//*[@data-frame])')==2 and not p.xpath('//*[@id="cadouri"]')
        assert p.xpath('//img[contains(@class,"brand-logo") and contains(@src,"material-public-qa")]')
        assert 'Răspuns editorial sintetic.' in p.text_content() and 'Anunț sintetic' in p.text_content()
        assert p.xpath('//a[@href="mailto:qa@example.invalid"]')
        luna=guest.get(ORIGIN+'/produs/luna/').text
        assert 'Ambalaj editorial QA' in luna and 'material-public-qa' in luna and '249 RON' in luna
        send({**form(admin,'brand'),'op':'reset'})
        assert 'Ciornă privată' in guest.get(ORIGIN).text
        publish()
        assert 'Copertă V5 sintetică' in guest.get(ORIGIN).text
        history=html.fromstring(admin.get(ADMIN+'brand').content).xpath('//button[starts-with(@value,"restore-")]/@value')
        send({**form(admin,'brand'),'op':history[0]})
        publish()
        assert 'Ciornă privată' in guest.get(ORIGIN).text, 'Published hero after restoration'
        current=snapshot()
        assert current==reference,[i for i,(a,b) in enumerate(zip(json.loads(reference),json.loads(current))) if a!=b]
        install(fx,V5)
        assert 'Copertă V5 sintetică' in guest.get(ORIGIN).text and snapshot()==reference
        install(fx)
        assert 'Ciornă privată' in guest.get(ORIGIN).text, 'Published hero after restoration'
        current=snapshot()
        assert current==reference,[i for i,(a,b) in enumerate(zip(json.loads(reference),json.loads(current))) if a!=b]
        assert schema()==old_schema
        assert private.read_bytes()==b'private synthetic fixture'
        print('PASS V5 ZIP → V7 ZIP → V5 → V7 on the same WooCommerce DB; settings, order, Atelier metadata, photo, profit, price, Stripe settings preserved.')
        print('PASS role/CSRF, stale version, unsafe URL/media, grouped comparator, draft isolation, preview access, publication, persistence, reset, restore and storefront overrides.')
    finally:
        fx.close()

if __name__=='__main__':
    main()
