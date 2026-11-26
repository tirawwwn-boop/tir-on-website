/* Isolated browser consent transitions. Intercept every request; no vendor contact. */
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
const path = require('node:path');
(async () => {
  const browser = await chromium.launch({executablePath:process.env.CHROMIUM_EXECUTABLE});
  try {
    const page = await browser.newPage();
    const vendors = [];
    await page.route('**/*', route => {
      const host = new URL(route.request().url()).hostname;
      if (host === 'localhost') return route.fulfill({contentType:'text/html',body:
        '<details open id="tiron-marketing"><summary>Preferințe</summary><button data-choice="yes">Accept</button><button data-choice="no">Refuz</button><p role="status"></p></details>'});
      vendors.push(host);
      return route.fulfill({contentType:'text/javascript',body:''});
    });
    async function load(catalog) {
      await page.goto('http://localhost/');
      await page.evaluate(catalog => window.tironMarketing={catalog,ids:{meta:'123456',tiktok:'TESTPIXEL123456'}},catalog);
      await page.addScriptTag({path:path.join(__dirname,'../theme/tir-on/assets/marketing.js')});
    }
    await load(true);
    assert.equal(vendors.length,0);
    await page.click('[data-choice=no]');
    assert.equal(vendors.length,0);
    await page.click('[data-choice=yes]');
    await page.waitForLoadState('networkidle');
    assert.deepEqual([...vendors].sort(),['analytics.tiktok.com','connect.facebook.net']);
    await load(false);
    assert.equal(vendors.length,2);
    await page.click('[data-choice=no]');
    await load(true);
    assert.equal(vendors.length,2);
    await page.evaluate(()=>Object.defineProperty(navigator,'globalPrivacyControl',{value:true}));
    await page.addScriptTag({path:path.join(__dirname,'../theme/tir-on/assets/marketing.js')});
    assert(await page.locator('[data-choice=yes]').isDisabled());
    console.log('PASS initial/refusal zero tags, acceptance allows both, sensitive pages clean, withdrawal, GPC (vendor stubs)');
  } finally { await browser.close(); }
})().catch(e=>{console.error(e);process.exitCode=1});
