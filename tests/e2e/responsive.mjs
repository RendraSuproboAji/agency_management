/**
 * Tes regresi tampilan.
 *
 * Membuka setiap halaman utama pada lebar ponsel dan desktop, lalu gagal bila
 * ada gulir menyamping atau error konsol. Skrip inilah yang menemukan kalender
 * bersel 46px dan filter bar yang lebarnya mati — keduanya lolos dari 204 tes
 * PHP karena tidak satu pun menjalankan browser sungguhan.
 *
 * Jalankan: node tests/e2e/responsive.mjs [baseUrl]
 */
import { chromium } from 'playwright-core';

const BASE = process.argv[2] ?? process.env.APP_TEST_URL ?? 'http://127.0.0.1:8000';
const EMAIL = process.env.APP_TEST_EMAIL ?? 'admin@example.com';
const PASSWORD = process.env.APP_TEST_PASSWORD ?? 'rahasia12345';

const PAGES = [
    '/', '/clients', '/projects', '/equipment', '/invoices',
    '/sessions', '/sessions?view=calendar', '/requests', '/users',
    '/archive', '/profile', '/clients/create', '/projects/create',
];

const VIEWPORTS = [
    { label: 'ponsel', width: 360, height: 780 },
    { label: 'tablet', width: 768, height: 1024 },
    { label: 'desktop', width: 1280, height: 900 },
];

const failures = [];

// CHROMIUM_PATH dipakai bila Chromium sudah tersedia di mesin (mis. runner yang
// menyediakannya sendiri); kalau tidak, playwright-core memakai unduhan standar.
const browser = await chromium.launch({
    executablePath: process.env.CHROMIUM_PATH || undefined,
});

for (const viewport of VIEWPORTS) {
    const context = await browser.newContext({ viewport: { width: viewport.width, height: viewport.height } });
    const page = await context.newPage();

    const errors = [];
    page.on('console', (message) => message.type() === 'error' && errors.push(message.text()));
    page.on('pageerror', (error) => errors.push(String(error)));

    await page.goto(`${BASE}/login`);
    await page.fill('input[type=email]', EMAIL);
    await page.fill('input[type=password]', PASSWORD);
    await Promise.all([page.waitForNavigation().catch(() => {}), page.click('button[type=submit]')]);

    if (page.url().includes('/login')) {
        failures.push(`gagal masuk sebagai ${EMAIL} — periksa APP_TEST_EMAIL/APP_TEST_PASSWORD`);
        break;
    }

    for (const path of PAGES) {
        errors.length = 0;
        await page.goto(BASE + path);
        await page.waitForLoadState('networkidle');

        const overflow = await page.evaluate(() => {
            const root = document.documentElement;

            if (root.scrollWidth <= root.clientWidth + 1) {
                return null;
            }

            const culprit = [...document.querySelectorAll('body *')]
                .find((element) => element.getBoundingClientRect().right > root.clientWidth + 1);

            return {
                scrollWidth: root.scrollWidth,
                clientWidth: root.clientWidth,
                culprit: culprit ? `${culprit.tagName.toLowerCase()}.${culprit.className}`.slice(0, 90) : 'tidak diketahui',
            };
        });

        if (overflow) {
            failures.push(
                `${viewport.label} ${viewport.width}px ${path}: meluber ${overflow.scrollWidth}px ` +
                `> ${overflow.clientWidth}px — ${overflow.culprit}`,
            );
        }

        for (const error of new Set(errors)) {
            failures.push(`${viewport.label} ${viewport.width}px ${path}: error konsol — ${error}`);
        }
    }

    await context.close();
}

await browser.close();

if (failures.length) {
    console.error(`\n${failures.length} masalah tampilan:\n`);
    failures.forEach((failure) => console.error('  ✗ ' + failure));
    process.exit(1);
}

console.log(`Nol gulir menyamping dan nol error konsol pada ${PAGES.length} halaman × ${VIEWPORTS.length} lebar.`);
