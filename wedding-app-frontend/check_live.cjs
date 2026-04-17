const puppeteer = require('puppeteer');

(async () => {
    const browser = await puppeteer.launch();
    const page = await browser.newPage();

    page.on('console', msg => console.log('PAGE LOG:', msg.text()));
    page.on('pageerror', err => console.log('PAGE ERROR:', err.message));

    console.log("Navigating to https://emanuel-wedding.vercel.app/...");
    try {
        await page.goto('https://emanuel-wedding.vercel.app/', { waitUntil: 'networkidle2' });
        console.log("Done");
    } catch(err) {
        console.log("Goto error:", err.message);
    }
    await browser.close();
})();
