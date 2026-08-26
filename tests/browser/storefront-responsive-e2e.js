const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8080';
const CATEGORY_ID = process.env.CATEGORY_ID;
const MEMBER_ID = process.env.MEMBER_ID;
const THEME_MODE = process.env.THEME_MODE || 'default';
const OPENCART_VERSION = process.env.OPENCART_VERSION || 'unknown';
const ARTIFACT_DIR = process.env.ARTIFACT_DIR || '/tmp/probg-team-storefront-browser';

if (!CATEGORY_ID || !MEMBER_ID) {
  throw new Error('CATEGORY_ID and MEMBER_ID are required');
}

fs.mkdirSync(ARTIFACT_DIR, { recursive: true });

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

async function refreshOcmod(page) {
  await page.goto(`${BASE_URL}/admin/index.php?route=common/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="username"]').fill('admin');
  await page.locator('input[name="password"]').fill('admin');
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.locator('button[type="submit"]').click()
  ]);

  const token = new URL(page.url()).searchParams.get('user_token');
  assert(token, 'Could not extract user_token after admin login');

  const response = await page.goto(
    `${BASE_URL}/admin/index.php?route=marketplace/modification/refresh&user_token=${encodeURIComponent(token)}`,
    { waitUntil: 'domcontentloaded' }
  );
  assert(response && response.status() < 400, `OCMOD refresh returned HTTP ${response ? response.status() : 'no response'}`);
}

async function assertTheme(page) {
  const marker = page.locator('meta[name="probg-team-e2e-theme"][content="custom"]');
  if (THEME_MODE === 'custom') {
    assert(await marker.count() === 1, 'Synthetic custom theme header override did not render');
  } else {
    assert(await marker.count() === 0, 'Custom theme marker rendered in default theme mode');
  }
}

async function assertNoHorizontalOverflow(page, label) {
  const metrics = await page.evaluate(() => ({
    documentClientWidth: document.documentElement.clientWidth,
    documentScrollWidth: document.documentElement.scrollWidth,
    bodyScrollWidth: document.body ? document.body.scrollWidth : 0
  }));

  const widest = Math.max(metrics.documentScrollWidth, metrics.bodyScrollWidth);
  assert(
    widest <= metrics.documentClientWidth + 2,
    `${label} has horizontal page overflow: client=${metrics.documentClientWidth}, scroll=${widest}`
  );
}

async function assertTeamElementsStayInsideViewport(page, label) {
  const failures = await page.locator([
    '.probg-team-module',
    '.probg-team-menu',
    '.probg-team-card',
    '.probg-team-category-card',
    '.probg-team-member-image',
    '.probg-team-member-description',
    '.probg-team-gallery'
  ].join(',')).evaluateAll((elements) => {
    const width = document.documentElement.clientWidth;
    return elements.map((element) => {
      const rect = element.getBoundingClientRect();
      return {
        selector: element.className || element.id || element.tagName,
        left: rect.left,
        right: rect.right,
        width: rect.width,
        viewport: width
      };
    }).filter((item) => item.left < -2 || item.right > item.viewport + 2);
  });

  assert(failures.length === 0, `${label} has Team elements outside the viewport: ${JSON.stringify(failures)}`);
}

async function assertImagesResponsive(page, label) {
  const failures = await page.locator('#probg-team img, #probg-team-category img, #probg-team-member img, .probg-team-module img').evaluateAll((images) => {
    return images.map((image) => {
      const rect = image.getBoundingClientRect();
      const parentRect = image.parentElement ? image.parentElement.getBoundingClientRect() : rect;
      return {
        src: image.getAttribute('src'),
        width: rect.width,
        parentWidth: parentRect.width,
        complete: image.complete,
        naturalWidth: image.naturalWidth
      };
    }).filter((item) => !item.complete || item.naturalWidth < 1 || item.width > item.parentWidth + 2);
  });

  assert(failures.length === 0, `${label} has non-responsive/broken Team images: ${JSON.stringify(failures)}`);
}

async function openRoute(page, url, rootSelector, heading, label) {
  const response = await page.goto(url, { waitUntil: 'networkidle' });
  assert(response && response.ok(), `${label} returned HTTP ${response ? response.status() : 'no response'}`);
  await page.locator(rootSelector).waitFor({ state: 'visible' });
  assert((await page.locator(`${rootSelector} h1`).first().textContent()).includes(heading), `${label} heading is missing`);
  await assertTheme(page);
  await assertNoHorizontalOverflow(page, label);
  await assertTeamElementsStayInsideViewport(page, label);
  await assertImagesResponsive(page, label);
}

async function assertLayoutModules(page, label) {
  assert(await page.locator('.probg-team-module').count() >= 1, `${label} did not render the Team members Layout module`);
  assert(await page.locator('.probg-team-menu').count() >= 1, `${label} did not render the Team menu Layout module`);
  assert((await page.locator('.probg-team-module').first().textContent()).includes('Browser E2E Team Members'), `${label} members module title is missing`);
  assert((await page.locator('.probg-team-menu').first().textContent()).includes('Browser E2E Team Menu'), `${label} menu module title is missing`);
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage();
  const browserErrors = [];

  page.on('pageerror', (error) => browserErrors.push(error.message));

  const routes = {
    team: `${BASE_URL}/index.php?route=extension/probg_team/team`,
    category: `${BASE_URL}/index.php?route=extension/probg_team/category&probg_team_category_id=${CATEGORY_ID}`,
    member: `${BASE_URL}/index.php?route=extension/probg_team/member&probg_team_category_id=${CATEGORY_ID}&probg_team_member_id=${MEMBER_ID}`
  };

  const viewports = [
    { name: 'desktop', width: 1440, height: 1000 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'mobile', width: 390, height: 844 }
  ];

  try {
    await refreshOcmod(page);

    for (const viewport of viewports) {
      await page.setViewportSize({ width: viewport.width, height: viewport.height });

      await openRoute(page, routes.team, '#probg-team', 'Runtime Team', `${viewport.name} Team landing`);
      await assertLayoutModules(page, `${viewport.name} Team landing`);
      assert(await page.locator('.probg-team-category-card').count() >= 1, 'Team landing category card is missing');

      await openRoute(page, routes.category, '#probg-team-category', 'Runtime Category', `${viewport.name} category`);
      await assertLayoutModules(page, `${viewport.name} category`);
      assert(await page.locator('.probg-team-card').count() >= 1, 'Category member card is missing');

      await openRoute(page, routes.member, '#probg-team-member', 'Runtime Member', `${viewport.name} member`);
      await assertLayoutModules(page, `${viewport.name} member`);
      assert(await page.locator('.probg-team-gallery-link').count() >= 2, 'Member main/gallery image links are missing');
      assert(await page.locator('meta[property="og:title"][content="Runtime Member"]').count() === 1, 'Member Open Graph metadata is missing through the active theme header');

      const website = page.locator('.probg-team-contact-list a[href^="https://example.com/team/"]');
      assert(await website.count() === 1, 'Long website URL fixture is missing');

      if (viewport.name === 'mobile') {
        const richContent = await page.locator('.probg-team-member-description').evaluate((element) => ({
          clientWidth: element.clientWidth,
          scrollWidth: element.scrollWidth,
          overflowX: getComputedStyle(element).overflowX
        }));
        assert(richContent.scrollWidth > richContent.clientWidth, 'Mobile rich-content fixture is not wider than its container');
        assert(['auto', 'scroll'].includes(richContent.overflowX), `Mobile rich content is not internally scrollable (overflow-x=${richContent.overflowX})`);
      }
    }

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(routes.member, { waitUntil: 'networkidle' });
    await page.locator('.probg-team-gallery-link').first().click();
    await page.locator('.mfp-wrap').waitFor({ state: 'visible' });
    assert(await page.locator('.mfp-img').count() === 1, 'Magnific Popup did not render the Team image');
    await page.keyboard.press('Escape');
    await page.locator('.mfp-wrap').waitFor({ state: 'hidden' }).catch(() => {});

    if (browserErrors.length) {
      throw new Error(`Uncaught storefront browser errors:\n${browserErrors.join('\n')}`);
    }

    console.log(`Storefront responsive E2E OK: OpenCart ${OPENCART_VERSION}, theme=${THEME_MODE}, desktop/tablet/mobile`);
  } catch (error) {
    await page.screenshot({ path: path.join(ARTIFACT_DIR, 'failure.png'), fullPage: true }).catch(() => {});
    fs.writeFileSync(path.join(ARTIFACT_DIR, 'failure.html'), await page.content().catch(() => ''), 'utf8');
    fs.writeFileSync(path.join(ARTIFACT_DIR, 'browser-errors.txt'), browserErrors.join('\n'), 'utf8');
    console.error(error.stack || error.message || error);
    process.exitCode = 1;
  } finally {
    await browser.close();
  }
})();
