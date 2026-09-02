/**
 * Tes regresi tampilan.
 *
 * Membuka setiap halaman pada tiga lebar dan gagal bila ada gulir menyamping
 * atau error konsol. Skrip inilah yang menemukan kalender bersel 46px dan
 * filter bar yang lebarnya mati — keduanya lolos dari 218 tes PHP karena tidak
 * satu pun menjalankan browser sungguhan.
 *
 * Menguji tiga persona. Versi pertama hanya menguji halaman staf yang sudah
 * masuk, dan itulah sebabnya dua cacat sempat lolos: DetailList yang
 * meluberkan alamat email di halaman detail, dan dokumen cetak selebar A4 yang
 * memaksa klien menggeser layar. Halaman tamu dan portal klien kini ikut.
 *
 * Jalankan: node tests/e2e/responsive.mjs [baseUrl]
 */
import { chromium } from 'playwright-core';

const BASE = process.argv[2] ?? process.env.APP_TEST_URL ?? 'http://127.0.0.1:8000';
const EMAIL = process.env.APP_TEST_EMAIL ?? 'admin@example.com';
const PASSWORD = process.env.APP_TEST_PASSWORD ?? 'rahasia12345';
const CLIENT_EMAIL = process.env.APP_TEST_CLIENT_EMAIL ?? '';
const CLIENT_PASSWORD = process.env.APP_TEST_CLIENT_PASSWORD ?? '';

const GUEST_PAGES = ['/login', '/forgot-password', '/request', '/portal/login', '/portal/forgot-password'];

const STAFF_PAGES = [
    '/', '/clients', '/projects', '/equipment', '/invoices',
    '/sessions', '/sessions?view=calendar', '/requests', '/users',
    '/archive', '/activities', '/activities?subject=client', '/rates', '/rates/create', '/raw-data',
    '/profile', '/clients/create', '/projects/create',
];

const VIEWPORTS = [
    { label: 'ponsel', width: 360, height: 780 },
    { label: 'tablet', width: 768, height: 1024 },
    { label: 'desktop', width: 1280, height: 900 },
];

const failures = [];
let checked = 0;

const browser = await chromium.launch({
    // Dipakai bila Chromium sudah tersedia di mesin; kalau tidak,
    // playwright-core memakai unduhan standarnya.
    executablePath: process.env.CHROMIUM_PATH || undefined,
});

/**
 * Kumpulkan tautan yang cocok dari sebuah daftar, supaya URL detail tidak
 * perlu ditulis tetap di berkas ini.
 *
 * Mengambil beberapa, bukan hanya yang pertama: kalau hanya satu, cakupannya
 * bergantung pada urutan daftar dan panjang data yang kebetulan ada di baris
 * teratas — persis yang membuat cacat DetailList lolos.
 */
async function findLinks(page, listPath, pattern, limit = 1) {
    await page.goto(BASE + listPath);
    await page.waitForLoadState('networkidle');

    return page.evaluate(([source, max]) => {
        const matches = [...document.querySelectorAll('a[href]')]
            .map((anchor) => anchor.getAttribute('href'))
            .filter((value) => new RegExp(source).test(value));

        return [...new Set(matches)].slice(0, max);
    }, [pattern.source, limit]);
}

async function inspect(page, label, path) {
    const errors = [];
    const onConsole = (message) => message.type() === 'error' && errors.push(message.text());
    const onError = (error) => errors.push(String(error));

    page.on('console', onConsole);
    page.on('pageerror', onError);

    await page.goto(BASE + path);
    await page.waitForLoadState('networkidle');

    const overflow = await page.evaluate(() => {
        const root = document.documentElement;

        if (root.scrollWidth <= root.clientWidth + 1) {
            return null;
        }

        // Dua bentuk yang berbeda: elemen yang tepinya melewati layar, dan
        // elemen yang isinya lebih lebar dari dirinya sendiri. Bentuk kedua
        // yang dulu terlewat — satu alamat email tanpa titik patah.
        const culprit = [...document.querySelectorAll('body *')].find((element) => (
            element.getBoundingClientRect().right > root.clientWidth + 1
            || (element.clientWidth > 0 && element.scrollWidth > element.clientWidth + 1
                && getComputedStyle(element).overflowX === 'visible')
        ));

        return {
            scrollWidth: root.scrollWidth,
            clientWidth: root.clientWidth,
            culprit: culprit ? `${culprit.tagName.toLowerCase()}.${culprit.className}`.slice(0, 90) : 'tidak diketahui',
        };
    });

    page.off('console', onConsole);
    page.off('pageerror', onError);
    checked += 1;

    if (overflow) {
        failures.push(
            `${label} ${path}: meluber ${overflow.scrollWidth}px > ${overflow.clientWidth}px — ${overflow.culprit}`,
        );
    }

    for (const error of new Set(errors)) {
        failures.push(`${label} ${path}: error konsol — ${error}`);
    }
}

async function signIn(page, path, email, password) {
    await page.goto(BASE + path);
    await page.fill('input[type=email]', email);
    await page.fill('input[type=password]', password);
    await Promise.all([page.waitForNavigation().catch(() => {}), page.click('button[type=submit]')]);

    return ! page.url().includes('/login');
}

for (const viewport of VIEWPORTS) {
    const label = `${viewport.label} ${viewport.width}px`;
    const context = await browser.newContext({ viewport: { width: viewport.width, height: viewport.height } });

    // Tamu
    const guest = await context.newPage();
    for (const path of GUEST_PAGES) {
        await inspect(guest, label + ' tamu', path);
    }
    await guest.close();

    // Staf
    const staff = await context.newPage();

    if (! await signIn(staff, '/login', EMAIL, PASSWORD)) {
        failures.push(`${label}: gagal masuk sebagai ${EMAIL} — periksa APP_TEST_EMAIL/APP_TEST_PASSWORD`);
        await context.close();
        break;
    }

    // "create" bukan slug; tanpa dikecualikan, penemuan ini menghasilkan
    // /projects/create dan seluruh URL turunannya 404.
    const [project] = await findLinks(staff, '/projects', /^\/projects\/(?!create$)[^/]+$/);
    const [invoice] = await findLinks(staff, '/invoices', /^\/projects\/[^/]+\/invoices\/\d+$/);
    // Semua klien, bukan satu: halaman detail paling rentan pada data terpanjang.
    const clients = await findLinks(staff, '/clients', /^\/clients\/(?!create$)[^/]+$/, 6);

    const staffPages = [
        ...STAFF_PAGES,
        // Halaman detail dan cetak: yang paling padat isinya, dan dulu justru
        // tidak pernah diuji.
        ...(project ? [project, `${project}/deliverables/create`, `${project}/sessions/create`, `${project}/quotations/create`] : []),
        ...(invoice ? [invoice, `${invoice}/print`] : []),
        ...clients,
    ];

    for (const path of staffPages) {
        await inspect(staff, label + ' staf', path);
    }
    await staff.close();

    // Klien
    if (CLIENT_EMAIL && CLIENT_PASSWORD) {
        const portal = await context.newPage();

        if (await signIn(portal, '/portal/login', CLIENT_EMAIL, CLIENT_PASSWORD)) {
            const [portalProject] = await findLinks(portal, '/portal', /^\/portal\/projects\/[^/]+$/);

            for (const path of ['/portal', ...(portalProject ? [portalProject] : [])]) {
                await inspect(portal, label + ' portal', path);
            }
        } else {
            failures.push(`${label}: gagal masuk portal sebagai ${CLIENT_EMAIL}`);
        }

        await portal.close();
    }

    await context.close();
}

await browser.close();

if (failures.length) {
    console.error(`\n${failures.length} masalah tampilan:\n`);
    failures.forEach((failure) => console.error('  ✗ ' + failure));
    process.exit(1);
}

console.log(`Nol gulir menyamping dan nol error konsol pada ${checked} pemuatan halaman.`);

if (! CLIENT_EMAIL) {
    console.log('Catatan: portal klien dilewati — setel APP_TEST_CLIENT_EMAIL dan APP_TEST_CLIENT_PASSWORD.');
}
