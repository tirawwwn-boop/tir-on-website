/* Local-only browser regression for the Luna photograph chooser. */
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');

const origin = new URL(process.argv[2] || 'http://127.0.0.1:8080').origin;
assert(['localhost', '127.0.0.1', '[::1]'].includes(new URL(origin).hostname));

async function sample(page, name, width, height) {
  const data = await page.evaluate(([w, h]) => {
    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    const context = canvas.getContext('2d');
    context.fillStyle = '#986541';
    context.fillRect(0, 0, w, h);
    context.fillStyle = '#efcf9c';
    context.fillRect(w / 4, h / 4, w / 2, h / 2);
    return canvas.toDataURL('image/png').split(',')[1];
  }, [width, height]);
  return { name, mimeType: 'image/png', buffer: Buffer.from(data, 'base64') };
}

async function run() {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_EXECUTABLE });
  try {
    for (const width of [390, 1440]) {
      const page = await browser.newPage({ viewport: { width, height: 844 }, isMobile: width < 700, hasTouch: width < 700, reducedMotion: 'reduce' });
      await page.route(/^https?:/, route => new URL(route.request().url()).origin === origin ? route.continue() : route.abort());
      const errors = [];
      let posts = 0;
      page.on('pageerror', error => errors.push(error.message));
      page.on('request', request => { if (request.method() === 'POST') posts++; });
      assert.equal((await page.goto(origin + '/produs/luna/#personalizare')).status(), 200);
      assert.equal(await page.locator('.lp-price').innerText(), '249 RON');

      const file = page.locator('#lp-file');
      const preview = page.locator('[data-chosen]');
      const outline = page.locator('[data-guide-outline]');
      const toggle = page.locator('[data-guide-switch]');
      const photo = page.locator('[data-local-photo]');
      const details = page.locator('[data-file-details]');
      const status = page.locator('#lp-file-status');
      const submit = page.locator('[data-add-product]');
      const landscape = await sample(page, 'peisaj-sintetic.png', 1800, 1250);
      const portrait = await sample(page, 'portret-sintetic.png', 720, 960);
      for (const [image, dimensions] of [[landscape, '1800 × 1250'], [portrait, '720 × 960']]) {
        await file.setInputFiles(image);
        await preview.waitFor({ state: 'visible' });
        assert.match(await details.innerText(), new RegExp(dimensions));
        assert.match(await photo.getAttribute('src'), /^blob:/);
        assert(await page.locator('[data-file-prompt]').innerText() === 'Schimbă fotografia');
        if (image === landscape) {
          assert(await toggle.isChecked());
        } else {
          assert(await outline.isHidden()); // Retain the customer's preference on replacement.
          await toggle.check();
        }
        await outline.waitFor({ state: 'visible' });
        const [guideBox, photoBox] = await Promise.all([outline.boundingBox(), photo.boundingBox()]);
        assert(Math.abs(guideBox.width - guideBox.height) < 1);
        assert(Math.abs(guideBox.width - Math.min(photoBox.width, photoBox.height) * 0.9) < 2);
        assert(Math.abs(guideBox.x + guideBox.width / 2 - photoBox.x - photoBox.width / 2) < 2);
        assert(Math.abs(guideBox.y + guideBox.height / 2 - photoBox.y - photoBox.height / 2) < 2);
        const unchanged = await file.evaluate(async input => Array.from(new Uint8Array(await new FormData(input.form).get('tiron_photo').arrayBuffer())));
        assert.deepEqual(Buffer.from(unchanged), image.buffer);
        await toggle.uncheck();
        assert(await submit.isEnabled());
      }
      assert.match(await status.innerText(), /Unele detalii se pot pierde/);
      assert.equal(await file.getAttribute('aria-invalid'), null);
      assert(await page.evaluate(() => document.documentElement.scrollWidth === innerWidth));
      await page.locator('[data-clear-photo]').click();
      assert(await preview.isHidden());
      assert.equal(await file.inputValue(), '');
      assert.equal(await page.evaluate(() => document.activeElement.id), 'lp-file');
      const quantity = page.locator('#lp-quantity');
      await quantity.fill('');
      await page.locator('[data-step="1"]').click();
      assert.equal(await quantity.inputValue(), await quantity.getAttribute('min'));

      await file.setInputFiles({ name: 'invalid.png', mimeType: 'image/png', buffer: Buffer.from('invalid image') });
      await status.getByText(/Conținutul imaginii/).waitFor();
      assert.equal(await file.getAttribute('aria-invalid'), 'true');
      await submit.click();
      assert.equal(posts, 0);
      assert(await preview.isHidden());
      const tooWide = await sample(page, 'prea-lata.png', 10001, 8);
      await file.setInputFiles(tooWide);
      await status.getByText(/megapixeli/).waitFor();
      assert.equal(await file.getAttribute('aria-invalid'), 'true');
      assert(await preview.isHidden());
      assert.equal(posts, 0);
      assert.deepEqual(errors, []);
      console.log(`PASS Chromium ${width}px · selecție, dimensiuni, ghid, înlocuire, validare, original nemodificat, fără POST la previzualizare`);
      await page.close();
    }
  } finally {
    await browser.close();
  }
}
run().catch(error => { console.error(error); process.exitCode = 1; });
