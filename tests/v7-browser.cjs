// Credentials arrive on stdin from the disposable loopback fixture.
const fs = require('node:fs');
const assert = require('node:assert/strict');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = 'http://127.0.0.1:4173';
const cookies = Object.entries(JSON.parse(fs.readFileSync(0, 'utf8')).administrator)
  .map(([name, value]) => ({ name, value, url: base }));
(async () => {
  const browser = await chromium.launch({executablePath: process.env.CHROMIUM_EXECUTABLE});
  try {
    for (const width of [360, 390, 430, 1440]) {
      const context = await browser.newContext({viewport:{width,height:width > 700 ? 980 : 844},isMobile:width<700,hasTouch:width<700});
      await context.addCookies(cookies);
      await context.route(/^https?:/, route => route.request().url().startsWith(base+'/') ? route.continue() : route.abort());
      const page = await context.newPage();
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      const response = await page.goto(base+'/wp-admin/admin.php?page=tiron-commercial&studio=brand');
      assert.equal(response.status(),200);
      const overflow = () => page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
      assert.equal(await overflow(),false);
      if ([390,1440].includes(width)) {
        await page.screenshot({path:'docs/media/v7-studio-'+width+'.png',animations:'disabled'});
      }
      await page.locator('.ts-pick').first().click();
      await page.locator('.media-modal:visible').waitFor();
      await page.locator('.media-modal-close:visible').click();
      await page.getByRole('link',{name:'Campanii și FAQ'}).click();
      await page.locator('#ts-announcement').fill('Anunț pentru verificarea locală');
      await page.getByRole('button',{name:'Salvează secțiunea în ciornă'}).click();
      await page.locator('.notice-success').waitFor();
      assert.equal(await page.locator('#ts-announcement').inputValue(),'Anunț pentru verificarea locală');
      await page.getByRole('link',{name:'Previzualizează Acasă'}).click();
      await page.getByText('Anunț pentru verificarea locală',{exact:true}).waitFor();
      assert.equal(await overflow(),false);
      assert.deepEqual(errors,[]);
      await context.close();
      console.log('PASS Brand Studio '+width+' px, media dialog, save and draft preview, no overflow or JS exceptions');
    }
  } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});
