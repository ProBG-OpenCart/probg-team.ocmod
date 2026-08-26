const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8080';
const ARTIFACT_DIR = process.env.ARTIFACT_DIR || '/tmp/probg-team-browser-e2e';
const CATEGORY_NAME = 'Browser E2E Category';
const MEMBER_NAME = 'Browser E2E Member';
const BLOCK_NAME = 'Browser E2E Members';
const MENU_NAME = 'Browser E2E Menu';
const IMAGE_PATH = 'catalog/browser-e2e.png';

fs.mkdirSync(ARTIFACT_DIR, { recursive: true });

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

function adminUrl(route, token, extra = '') {
  const suffix = extra ? `&${extra}` : '';
  return `${BASE_URL}/admin/index.php?route=${encodeURIComponent(route)}&user_token=${encodeURIComponent(token)}${suffix}`;
}

async function gotoAdmin(page, route, token, extra = '') {
  const response = await page.goto(adminUrl(route, token, extra), { waitUntil: 'domcontentloaded' });
  assert(response && response.ok(), `${route} returned HTTP ${response ? response.status() : 'no response'}`);
  await page.waitForLoadState('networkidle').catch(() => {});
}

async function submitForm(page, formId) {
  const navigation = page.waitForNavigation({ waitUntil: 'domcontentloaded' });
  await page.locator(`button[type="submit"][form="${formId}"]`).click();
  await navigation;
  await page.waitForLoadState('networkidle').catch(() => {});
}

async function setSummernote(page, textarea, html) {
  const selector = await textarea.evaluate((el) => {
    if (!el.id) {
      el.id = `e2e-summernote-${Math.random().toString(36).slice(2)}`;
    }
    return `#${el.id}`;
  });

  await page.waitForFunction((sel) => {
    const element = window.jQuery && window.jQuery(sel);
    return Boolean(element && element.length && typeof element.summernote === 'function' && element.next('.note-editor').length);
  }, selector);

  await page.evaluate(({ sel, value }) => {
    window.jQuery(sel).summernote('code', value);
  }, { sel: selector, value: html });

  const value = await textarea.inputValue();
  assert(value.includes(html.replace(/<[^>]+>/g, '').trim().split(' ')[0]), `Summernote did not sync ${selector}`);
}

async function chooseFixtureImage(page, thumbSelector, inputSelector) {
  await page.locator(thumbSelector).click();
  await page.locator('#button-image').waitFor({ state: 'visible' });
  await page.locator('#button-image').click();
  await page.locator('#modal-image #filemanager').waitFor({ state: 'visible' });

  const directImage = page.locator('#modal-image a.thumbnail img[title="browser-e2e.png"]');
  if (await directImage.count() === 0) {
    const catalogCheckbox = page.locator('#modal-image input[name="path[]"][value="catalog"]');
    assert(await catalogCheckbox.count() > 0, 'Image Manager did not expose the catalog directory');
    const catalogCell = catalogCheckbox.locator('xpath=ancestor::div[contains(@class,"col-sm-3") or contains(@class,"col-xs-6")][1]');
    await catalogCell.locator('a.directory').click();
    await page.locator('#modal-image a.thumbnail img[title="browser-e2e.png"]').waitFor({ state: 'visible' });
  }

  await page.locator('#modal-image a.thumbnail', {
    has: page.locator('img[title="browser-e2e.png"]')
  }).click();

  await page.locator('#modal-image').waitFor({ state: 'hidden' }).catch(() => {});
  const selected = await page.locator(inputSelector).inputValue();
  assert(selected === IMAGE_PATH, `${inputSelector} expected ${IMAGE_PATH}, got ${selected}`);
}

async function fillFirstLanguageInput(page, prefix, suffix, value) {
  const locator = page.locator(`input[name^="${prefix}"][name$="${suffix}"]`).first();
  assert(await locator.count() > 0, `Missing input ${prefix}...${suffix}`);
  await locator.fill(value);
  return locator;
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1100 } });
  const page = await context.newPage();
  const browserErrors = [];
  const browserWarnings = [];

  page.on('pageerror', (error) => browserErrors.push(`pageerror: ${error.message}`));
  page.on('console', (message) => {
    if (message.type() === 'error') {
      browserWarnings.push(`console: ${message.text()}`);
    }
  });

  try {
    await page.goto(`${BASE_URL}/admin/index.php?route=common/login`, { waitUntil: 'domcontentloaded' });
    await page.locator('input[name="username"]').fill('admin');
    await page.locator('input[name="password"]').fill('admin');
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      page.locator('button[type="submit"]').click()
    ]);

    const token = new URL(page.url()).searchParams.get('user_token');
    assert(token, 'Could not extract user_token after admin login');

    await gotoAdmin(page, 'marketplace/modification/refresh', token);
    await gotoAdmin(page, 'extension/extension/module/install', token, 'extension=probg_team');

    await gotoAdmin(page, 'extension/module/probg_team', token);
    assert(await page.locator('#form-probg-team').count() === 1, 'ProBG Team settings form did not render');
    assert(await page.locator('#menu-probg-team').count() === 1, 'ProBG Team sidebar entry did not render');

    await page.locator('a[href="#tab-content"]').click();
    await fillFirstLanguageInput(page, 'module_probg_team_description[0]', '[title]', 'Browser E2E Team');
    const settingsDescription = page.locator('textarea[name^="module_probg_team_description[0]"][name$="[description]"]').first();
    await setSummernote(page, settingsDescription, '<p>Browser E2E section description</p>');
    await fillFirstLanguageInput(page, 'module_probg_team_seo_url[0]', ']', 'browser-e2e-team');
    await submitForm(page, 'form-probg-team');
    assert((await page.content()).includes('Browser E2E Team'), 'Global Team settings were not persisted');

    await gotoAdmin(page, 'extension/probg_team/category/add', token);
    await fillFirstLanguageInput(page, 'category_description[', '[name]', CATEGORY_NAME);
    const categoryDescription = page.locator('textarea[name^="category_description["][name$="[description]"]').first();
    await setSummernote(page, categoryDescription, '<p>Browser E2E category description</p>');

    await page.locator('a[href="#tab-stores"]').click();
    const categoryStore = page.locator('input[name="category_store[]"]').first();
    if (!(await categoryStore.isChecked())) {
      await categoryStore.check();
    }
    const layoutSelect = page.locator('select[name^="category_layout["]').first();
    const layoutOptions = await layoutSelect.locator('option').evaluateAll((options) => options.map((option) => ({ value: option.value, text: option.textContent.trim() })));
    const nonDefaultLayout = layoutOptions.find((option) => option.value !== '0');
    assert(nonDefaultLayout, 'Stock OpenCart did not provide a non-default Layout for the category test');
    await layoutSelect.selectOption(nonDefaultLayout.value);
    await submitForm(page, 'form-category');
    assert((await page.content()).includes(CATEGORY_NAME), 'Category was not persisted through the browser form');

    const categoryRow = page.locator('tr', { hasText: CATEGORY_NAME }).first();
    assert(await categoryRow.count() === 1, 'Saved category row was not found');
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      categoryRow.locator('a[href*="category/edit"]').click()
    ]);
    await page.locator('a[href="#tab-stores"]').click();
    assert(await page.locator('select[name^="category_layout["]').first().inputValue() === nonDefaultLayout.value, 'Category Layout selection was not persisted');

    await gotoAdmin(page, 'extension/module/probg_team', token);
    await page.locator('a[href="#tab-blocks"]').click();
    await page.locator('#button-add-team-block').click();
    const blockEditor = page.locator('#probg-team-block-editors .probg-team-instance-editor').last();
    await blockEditor.locator('input.probg-team-instance-name').fill(BLOCK_NAME);
    const blockTitle = blockEditor.locator('input[name*="[title]["]').first();
    await blockTitle.evaluate((element, value) => {
      element.value = value;
      element.dispatchEvent(new Event('input', { bubbles: true }));
      element.dispatchEvent(new Event('change', { bubbles: true }));
    }, 'Browser E2E Members Block');
    await blockEditor.locator('select[name*="[team_category_id]"]').selectOption({ label: CATEGORY_NAME });

    await page.locator('a[href="#tab-menu"]').click();
    await page.locator('#button-add-team-menu').click();
    const menuEditor = page.locator('#probg-team-menu-editors .probg-team-instance-editor').last();
    await menuEditor.locator('input.probg-team-instance-name').fill(MENU_NAME);
    const menuTitle = menuEditor.locator('input[name*="[title]["]').first();
    await menuTitle.evaluate((element, value) => {
      element.value = value;
      element.dispatchEvent(new Event('input', { bubbles: true }));
      element.dispatchEvent(new Event('change', { bubbles: true }));
    }, 'Browser E2E Team Menu');
    await menuEditor.locator('select[name*="[team_category_id]"]').selectOption({ label: CATEGORY_NAME });
    await submitForm(page, 'form-probg-team');
    const settingsHtml = await page.content();
    assert(settingsHtml.includes(BLOCK_NAME), 'Browser-created members block did not persist');
    assert(settingsHtml.includes(MENU_NAME), 'Browser-created Team menu did not persist');

    await gotoAdmin(page, 'extension/probg_team/member/add', token);
    await fillFirstLanguageInput(page, 'member_description[', '[name]', MEMBER_NAME);
    const memberEditors = page.locator('textarea[data-toggle="summernote"]');
    assert(await memberEditors.count() >= 2, 'Member form did not expose both Summernote fields');
    await setSummernote(page, memberEditors.nth(0), '<p>Browser E2E short description</p>');
    await setSummernote(page, memberEditors.nth(1), '<p>Browser E2E full description</p>');

    await page.locator('a[href="#tab-images"]').click();
    await chooseFixtureImage(page, '#thumb-image', '#input-image');
    await page.locator('#images tfoot button.btn-primary').click();
    assert(await page.locator('#image-row0').count() === 1, 'Additional image JavaScript row was not created');
    await chooseFixtureImage(page, '#thumb-image0', '#input-image0');

    await page.locator('a[href="#tab-data"]').click();
    await page.locator('select[name="team_category_id"]').selectOption({ label: CATEGORY_NAME });
    await page.locator('a[href="#tab-stores"]').click();
    const memberStore = page.locator('input[name="member_store[]"]').first();
    if (!(await memberStore.isChecked())) {
      await memberStore.check();
    }
    await submitForm(page, 'form-member');
    assert((await page.content()).includes(MEMBER_NAME), 'Member was not persisted through the browser form');

    const memberRow = page.locator('tr', { hasText: MEMBER_NAME }).first();
    assert(await memberRow.count() === 1, 'Saved member row was not found');
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      memberRow.locator('a[href*="member/edit"]').click()
    ]);
    await page.locator('a[href="#tab-images"]').click();
    assert(await page.locator('#input-image').inputValue() === IMAGE_PATH, 'Main image selection was not persisted');
    assert(await page.locator('input[name^="member_image["][name$="[image]"]').first().inputValue() === IMAGE_PATH, 'Additional image selection was not persisted');
    await page.locator('a[href="#tab-data"]').click();
    assert((await page.locator('select[name="team_category_id"] option:checked').textContent()).trim() === CATEGORY_NAME, 'Member category selection was not persisted');

    if (browserErrors.length) {
      throw new Error(`Uncaught browser errors detected:\n${browserErrors.join('\n')}`);
    }

    if (browserWarnings.length) {
      console.warn(`Browser console warnings:\n${browserWarnings.join('\n')}`);
    }
    console.log('Browser E2E OK: Summernote, Image Manager, category Layout and typed module-instance UI flows');
  } catch (error) {
    await page.screenshot({ path: path.join(ARTIFACT_DIR, 'failure.png'), fullPage: true }).catch(() => {});
    fs.writeFileSync(path.join(ARTIFACT_DIR, 'failure.html'), await page.content().catch(() => ''), 'utf8');
    fs.writeFileSync(path.join(ARTIFACT_DIR, 'browser-errors.txt'), [...browserErrors, ...browserWarnings].join('\n'), 'utf8');
    console.error(error.stack || error.message || error);
    process.exitCode = 1;
  } finally {
    await browser.close();
  }
})();
