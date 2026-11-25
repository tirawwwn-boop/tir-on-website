/* Run only against the disposable loopback WordPress fixture. Uses generated photo bytes. */
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const origin = 'http://127.0.0.1:4173';
const before = process.argv.includes('--baseline');
const output = process.env.TIRON_QA_OUTPUT;
const widths = before ? [390,1440] : [320,360,390,430,768,1440];
const results = [];
if (output) fs.mkdirSync(output, {recursive:true});

async function settled(page) {
  await page.waitForLoadState('networkidle');
  await page.evaluate(() => document.fonts.ready);
}

async function measure(page, width, route) {
  await settled(page);
  const stats = await page.evaluate(() => {
    let start = 0, previous = 0, sum = 0, cls = 0;
    for (const e of window.qaShifts || []) {
      if (e.t - previous >= 1000 || e.t - start >= 5000) { sum = 0; start = e.t; }
      sum += e.v; previous = e.t; cls = Math.max(cls, sum);
    }
    const missing = [...document.images].filter(img => img.currentSrc && img.complete && !img.naturalWidth);
    return {overflow:document.documentElement.scrollWidth-document.documentElement.clientWidth,
      cls:Number(cls.toFixed(4)), broken:missing.map(img=>new URL(img.currentSrc).pathname)};
  });
  console.log(width, route, JSON.stringify(stats));
  results.push({width,route,...stats});
  if (stats.cls > 0.10) console.warn(JSON.stringify(await page.evaluate(()=>window.qaShifts)));
  if (!before) {
    assert.equal(stats.overflow,0, route + ' overflow at ' + width);
    assert.deepEqual(stats.broken,[], route + ' broken images');
    assert(stats.cls <= 0.10, route + ' layout shift at ' + width + ': ' + stats.cls);
  }
  if (output && [390,1440].includes(width)) {
    await page.screenshot({path:path.join(output, route + '-' + width + '.jpg'), quality:84, type:'jpeg', fullPage:true});
  }
}

async function sample(page) {
  const data = await page.evaluate(() => {
    const canvas = document.createElement('canvas');
    canvas.width=1400; canvas.height=1200;
    const ctx=canvas.getContext('2d');
    const gradient=ctx.createLinearGradient(0,0,1400,1200);
    gradient.addColorStop(0,'#846b52'); gradient.addColorStop(1,'#d8cab1');
    ctx.fillStyle=gradient; ctx.fillRect(0,0,1400,1200);
    ctx.fillStyle='#f7ebcd'; ctx.beginPath(); ctx.arc(850,410,140,0,Math.PI*2); ctx.fill();
    ctx.fillStyle='#373d36'; ctx.beginPath(); ctx.moveTo(0,1200); ctx.lineTo(450,600);
    ctx.lineTo(630,860); ctx.lineTo(1000,650); ctx.lineTo(1400,1090); ctx.lineTo(1400,1200); ctx.fill();
    return canvas.toDataURL('image/png').split(',')[1];
  });
  return {name:'peisaj-de-test.png',mimeType:'image/png',buffer:Buffer.from(data,'base64')};
}

async function pageAt(browser,width) {
  const page = await browser.newPage({viewport:{width,height:width<768?844:900}, deviceScaleFactor:1,
    hasTouch:width<768,isMobile:width<768,reducedMotion:'reduce',locale:'ro-RO'});
  await page.addInitScript(() => {
    window.qaShifts = [];
    new PerformanceObserver(list => {
      for(const e of list.getEntries()) if(!e.hadRecentInput) window.qaShifts.push({v:e.value,t:e.startTime, sources:e.sources.map(s=>[s.node?.id || s.node?.className,s.previousRect.y,s.currentRect.y])});
    }).observe({type:'layout-shift',buffered:true});
  });
  const errors=[],external=[];
  await page.route(/^https?:/, route => {
    if(new URL(route.request().url()).origin === origin) return route.continue();
    external.push(new URL(route.request().url()).origin); return route.abort();
  });
  page.on('pageerror', error => errors.push(error.message));
  page.on('console', m => { if(m.type()==='error') errors.push(m.text()); });
  try {
    assert.equal((await page.goto(origin)).status(),200);
    await measure(page,width,'home');
    if (!before && width <= 700) {
      const media=await page.locator('.hero-media').boundingBox();
      const caption=await page.locator('.scene-caption').boundingBox();
      const controls=await page.locator('.scene-actions').boundingBox();
      assert(caption.y >= media.y+media.height-1 && controls.y >= media.y+media.height-1);
      assert(caption.x+caption.width <= controls.x+1);
    }
    const menu=page.locator('.menu-trigger');
    if(await menu.isVisible()) {
      await menu.click();
      assert.equal(await menu.getAttribute('aria-expanded'),'true');
      await page.keyboard.press('Escape');
      assert.equal(await menu.getAttribute('aria-expanded'),'false');
      assert(await menu.evaluate(el=>el===document.activeElement));
    }
    const active=page.locator('.hero-frame.is-active img');
    const first=await active.evaluate(img=>img.currentSrc);
    await page.locator('[data-next]').click(); await active.evaluate(img=>img.decode());
    assert.notEqual(await active.evaluate(img=>img.currentSrc),first);
    await page.locator('[data-prev]').click();
    await page.locator('.hero-sub').click();
    const range=page.locator('.tl-compare-range');
    await range.press('End'); assert.equal(await range.inputValue(),'100');
    await page.locator('[data-light="off"]').click(); assert.equal(await range.inputValue(),'50');
    await page.locator('[data-light="on"]').click();

    await page.locator('.hero-actions .button-gold').click();
    await page.waitForURL('**/produs/luna/');
    await measure(page,width,'luna');
    assert.equal(await page.locator('.lp-price').innerText(),'249 RON');
    if (!before && width === 390) {
      const shortcut=page.locator('[data-mobile-buy] a');
      assert.match(await page.locator('[data-mobile-buy]').innerText(), /249 RON/);
      await shortcut.click();
      assert(page.url().endsWith('#personalizare'));
      const upload=await page.locator('[data-file-drop]').boundingBox();
      const bar=await page.locator('[data-mobile-buy]').boundingBox();
      assert(bar.y+bar.height <= upload.y);
    }
    await page.locator('#lp-file').setInputFiles(await sample(page));
    await page.locator('[data-chosen]').waitFor({state:'visible'});
    if(!before) {
      assert(await page.locator('[data-guide-switch]').isChecked());
      assert(await page.locator('[data-guide-outline]').isVisible());
    }
    if(output && width===390) {
      const preview=page.locator('[data-chosen]');
      await preview.scrollIntoViewIfNeeded();
      await preview.screenshot({path:path.join(output,'guide-390.jpg'),quality:86});
    }
    await page.locator('#lp-notes').fill('Păstrează întregul peisaj.');
    await page.locator('[data-add-product]').click();
    await page.waitForURL('**/cos/');
    await measure(page,width,'cart');
    assert((await page.locator('main').innerText()).includes('Păstrează întregul peisaj.'));
    if (!before && width === 390) {
      const coupon=page.locator('.tiron-cart-coupon');
      assert.equal(await coupon.getAttribute('open'),null);
      await coupon.locator('summary').click();
      await page.locator('#coupon_code').fill('inexistent-test');
      await page.locator('[name=apply_coupon]').click();
      await page.waitForFunction(()=>document.querySelector('.woocommerce-error, .coupon-error-notice'));
      await page.locator('[data-cart-step="1"]').click();
      await page.locator('a.checkout-button').click();
      await page.waitForFunction(()=>document.querySelector('.order-total')?.innerText.includes('498,00'));
      assert.equal(await page.locator('input.qty').inputValue(),'2');
    }
    await page.locator('a.checkout-button').click();
    await page.locator('#billing_email').waitFor({state:'visible'});
    await measure(page,width,'checkout');
    if(!before) {
      const phones=page.locator('form.checkout input[type=tel]:visible');
      assert.equal(await phones.count(),1);
      assert(await phones.evaluate(el=>el.required || el.getAttribute('aria-required')==='true'
        || Boolean(el.closest('.validate-required'))));
    }
    assert.equal(await page.locator('input[name="createaccount"]').count(),0);
    assert.equal(await page.locator('input#payment_method_tiron_local_test').count(),1);
    if(!before && width===390) {
      for(const [k,v] of Object.entries({first_name:'Test',last_name:'Atelier',email:'v5-qa@example.invalid',
        address_1:'Adresa test 1',city:'București',postcode:'010101',phone:'0700000000'})) {
        await page.locator('#billing_'+k).fill(v);
      }
      await page.locator('#billing_state').selectOption('B');
      await settled(page);
      const agreement=page.locator('#terms');
      if(await agreement.isVisible()) await agreement.check();
      const delivered=page.waitForURL('**/order-received/**',{timeout:30000});
      await page.locator('#place_order').click(); await delivered;
      assert(await page.locator('.woocommerce-order-overview').isVisible());
    }
    console.log(width, 'errors',errors,'external',external);
    if(!before) { assert.deepEqual(errors,[]); assert.deepEqual(external,[]); }
  } finally {await page.close();}
}

(async()=>{
  const browser=await chromium.launch({executablePath:process.env.CHROMIUM_EXECUTABLE});
  try {
    for(const width of widths.filter(w=>!process.env.TIRON_QA_WIDTH || w===Number(process.env.TIRON_QA_WIDTH))) await pageAt(browser,width);
    if(output) fs.writeFileSync(path.join(output,'measurements.json'),JSON.stringify(results,null,2)+'\n');
  } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
