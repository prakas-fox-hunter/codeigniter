const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const { RGBLuminanceSource, BinaryBitmap, HybridBinarizer, QRCodeReader } = require('@zxing/library');
const fs = require('node:fs');
const base=process.env.TEST_BASE_URL || 'http://localhost:8000';
(async()=>{
  const browser=await chromium.launch({headless:true,...(process.platform === 'win32' ? {channel: process.env.TEST_BROWSER_CHANNEL || 'msedge'} : {})});
  try {
    fs.mkdirSync('test-results',{recursive:true});
    const context=await browser.newContext({viewport:{width:1440,height:1000}});const page=await context.newPage();const errors=[];
    page.on('pageerror',error=>errors.push(error.message));
    await page.goto(base);await page.locator('.hero-art').waitFor();await page.screenshot({path:'test-results/home-desktop.png',fullPage:true});
    const email=`ui-${Date.now()}@example.com`;
    await page.goto(base+'/register');await page.locator('[name=name]').fill('UI Traveler');await page.locator('[name=email]').fill(email);await page.locator('[name=password]').fill('TravelPass123!');await page.locator('#auth-form button').click();await page.waitForURL('**/flights');
    const tomorrow=new Date(Date.now()+86400000).toLocaleDateString('en-CA',{timeZone:'Asia/Jakarta'});
    await page.locator('[name=date]').fill(tomorrow);await page.locator('[name=time]').selectOption('15:00');await page.locator('#flight-filters button').first().click();await page.waitForFunction(()=>document.querySelector('.ticket-card')?.textContent.includes('Citilink') && document.querySelector('.ticket-card')?.textContent.includes('15:00'));
    await page.locator('.ticket-card a').first().click();await page.locator('#booking-form button').waitFor();await page.locator('[name=passenger_name]').fill('UI Traveler');await page.locator('#booking-form button').click();await page.locator('#pay-form button').waitFor();await page.locator('#pay-form button').click();await page.locator('#qr-result img').waitFor();
    const qr=await page.locator('#qr-result img').evaluate(async img=>{await img.decode();const canvas=document.createElement('canvas');canvas.width=img.naturalWidth;canvas.height=img.naturalHeight;const ctx=canvas.getContext('2d');ctx.drawImage(img,0,0);return {width:canvas.width,height:canvas.height,pixels:Array.from(ctx.getImageData(0,0,canvas.width,canvas.height).data)};});
    const luminances=new Uint8ClampedArray(qr.width*qr.height);for(let i=0;i<luminances.length;i++) luminances[i]=(qr.pixels[i*4]+2*qr.pixels[i*4+1]+qr.pixels[i*4+2])/4;
    const decoded=new QRCodeReader().decode(new BinaryBitmap(new HybridBinarizer(new RGBLuminanceSource(luminances,qr.width,qr.height)))).getText();
    assert.equal(decoded,await page.locator('#simulate-scan').getAttribute('href'));
    await page.screenshot({path:'test-results/checkout-desktop.png',fullPage:true});
    const scan=await context.newPage();await scan.goto(decoded);await scan.getByRole('heading',{name:'Pembayaran berhasil!'}).waitFor();await scan.close();
    await page.goto(base+'/bookings');await page.getByText('Terkonfirmasi',{exact:true}).waitFor();await page.screenshot({path:'test-results/bookings-desktop.png',fullPage:true});
    await page.goto(base+'/docs');await page.locator('.opblock').first().waitFor();assert.equal(await page.locator('.opblock').count(),17);await page.screenshot({path:'test-results/swagger.png',fullPage:true});
    await page.setViewportSize({width:390,height:844});
    for(const path of ['/','/flights','/bookings','/account']) {
      await page.goto(base+path);await page.waitForTimeout(400);assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),`Overflow on ${path}`);await page.screenshot({path:`test-results/mobile-${path.replace(/\W/g,'')||'home'}.png`,fullPage:true});
    }
    await page.goto(base+'/account');await page.locator('[name=email]').waitFor();await page.locator('.danger summary').click();await page.locator('#delete-form [name=password]').fill('TravelPass123!');page.once('dialog',dialog=>dialog.accept());await page.locator('#delete-form button').click();await page.waitForURL(base+'/');
    assert.deepEqual(errors,[]);console.log('PASS: browser registration, filters, booking, QR decode, payment, Swagger and 390px responsive checks.');
  } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
