/* Local-only browser regression. Install Playwright in the QA environment. */
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');

const origin = new URL(process.argv[2] || 'http://127.0.0.1:8080').origin;
assert(['localhost', '127.0.0.1', '[::1]'].includes(new URL(origin).hostname));

async function run() {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_EXECUTABLE });
  try {
    for (const width of [360, 390, 430, 1440]) {
      const mobile = width < 700;
      const page = await browser.newPage({
        viewport: { width, height: 844 }, isMobile: mobile, hasTouch: mobile, reducedMotion: 'reduce',
      });
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
      await page.route(/^https?:/, route => new URL(route.request().url()).origin === origin ? route.continue() : route.abort());
      assert.equal((await page.goto(origin + '/')).status(), 200);
      const fits = () => page.evaluate(() => document.documentElement.scrollWidth === innerWidth);
      assert(await fits());
      if (mobile) {
        const menu = page.locator('.menu-trigger');
        await menu.click();
        assert.equal(await menu.getAttribute('aria-expanded'), 'true');
        await menu.click();
      }
      const hero = page.locator('.hero-frame.is-active img');
      await hero.evaluate(img => img.decode());
      const original = await hero.evaluate(img => img.currentSrc);
      await page.locator('[data-next]').click();
      await hero.evaluate(img => img.decode());
      assert.notEqual(await hero.evaluate(img => img.currentSrc), original);
      await page.locator('.hero-sub').click();
      assert(page.url().endsWith('#descopera-lumina'));
      const range = page.locator('.tl-compare-range');
      await range.press('End');
      assert.equal(await range.inputValue(), '100');
      await page.locator('[data-light="off"]').click();
      assert.equal(await range.inputValue(), '50');
      await page.locator('.tl-unlit').evaluate(img => img.decode());
      await page.locator('[data-light="on"]').click();
      await page.locator('.tl-lit').evaluate(img => img.decode());
      await page.locator('.hero-actions .button-gold').click();
      await page.waitForURL('**/produs/luna/');
      assert.equal(await page.locator('h1').innerText(), 'Luna');
      assert.equal(await page.locator('.lp-price').innerText(), '249 RON');
      assert(await fits());
      const shortcut = page.locator('.lp-personalize-link');
      assert.equal(await shortcut.isVisible(), mobile);
      if (mobile) {
        await shortcut.click();
        assert(page.url().endsWith('#personalizare'));
      }
      await page.locator('[data-main-photo]').evaluate(img => img.decode());
      assert.equal(await page.locator('form[data-personalization]').count(), 1);
      assert(await page.locator('[data-add-product]').isEnabled());
      assert.deepEqual(errors, []);
      console.log(`PASS Chromium ${width}px · Home, carusel, comparator, meniu, Luna, CTA, overflow și consolă`);
      await page.close();
    }
  } finally {
    await browser.close();
  }
}
run().catch(error => { console.error(error); process.exitCode = 1; });
