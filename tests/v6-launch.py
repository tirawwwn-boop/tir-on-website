"""Run the V6 functional checks on an isolated WordPress snapshot."""
from pathlib import Path
from atelier_cgi_fixture import Fixture, ORIGIN
from regression import test_catalog

fixture = Fixture()
try:
    test_catalog(fixture.session(), ORIGIN)
    print(fixture.run_wp('eval-file', str(Path(__file__).with_name('v6-launch.php').resolve())))
    for path in ['/', '/produs/luna/']:
        html = fixture.session().get(ORIGIN + path).text
        assert 'summary_large_image' in html and 'tironMarketing=' not in html
    fixture.run_wp('eval', '''
        update_option('tiron_commercial_v6', ['christmas'=>'Campanie sintetică',
            'from'=>wp_date('Y-m-d'), 'until'=>wp_date('Y-m-d'), 'home_description'=>'Descriere sintetică']);
    ''')
    home = fixture.session().get(ORIGIN).text
    assert 'Campanie sintetică' in home and 'content="Descriere sintetică"' in home
    fixture.run_wp('eval', "update_option('tiron_commercial_v6', ['christmas'=>'Campanie sintetică','from'=>'2001-01-01','until'=>'2001-01-02']);")
    assert 'Campanie sintetică' not in fixture.session().get(ORIGIN).text
    fixture.run_wp('option', 'update', 'blog_public', '1')
    assert '<sitemapindex' in fixture.session().get(ORIGIN + '/wp-sitemap.xml').text
    pages = fixture.session().get(ORIGIN + '/wp-sitemap-posts-page-1.xml').text
    assert '<urlset' in pages and '/finalizare-comanda/' not in pages and '/cos/' not in pages
    print('PASS product JSON-LD, RON price, social metadata, private robots and optional public sitemap')
finally:
    fixture.close()
