/**
 * Shared configuration and helper utilities for Alamia Accounts E2E tests.
 */
const { chromium } = require('playwright');

const RAW_BASE_URL = process.env.BASE_URL || 'http://localhost:8001/alamia-accounts';
const BASE_URL = RAW_BASE_URL.replace(/\/$/, '');
const API_URL = process.env.API_URL || 'http://localhost:8001/api';
const DEFAULT_USER = {
  email: process.env.TEST_EMAIL || 'admin@admin.com',
  password: process.env.TEST_PASSWORD || 'password',
};

/**
 * Launch browser using local Chrome.
 */
async function launchBrowser(options = {}) {
  try {
    return await chromium.launch({ channel: 'chrome', headless: true, ...options });
  } catch {
    return await chromium.launch({ headless: true, ...options });
  }
}

/**
 * Log into the Alamia Accounts application.
 */
async function login(page) {
  page.on('console', (msg) => console.log(`[Browser Console]: ${msg.text()}`));
  page.on('pageerror', (err) => console.log(`[Browser PageError]: ${err.message}`));

  const loginUrl = BASE_URL.includes('/alamia-accounts')
    ? `${BASE_URL}/login`
    : `${BASE_URL}/alamia-accounts/login`;

  console.log(`Navigating to login URL: ${loginUrl}`);
  await page.goto(loginUrl);
  await page.waitForTimeout(1000);

  if (page.url().includes('/login')) {
    const emailInput = page.locator('input[type="email"]');
    await emailInput.waitFor({ state: 'visible', timeout: 10000 });
    await emailInput.fill(DEFAULT_USER.email);
    await page.locator('input[type="password"]').fill(DEFAULT_USER.password);
    console.log(`Filled credentials for ${DEFAULT_USER.email}, clicking Submit...`);
    await page.getByRole('button', { name: /Sign In|Login/i }).click();
    await page.waitForTimeout(2000);
    console.log(`URL after login click: ${page.url()}`);
  }

  await page.waitForSelector('aside nav', { timeout: 15000 });
  await page.waitForTimeout(500);
}

/**
 * Switch active tenant company via the sidebar switcher.
 */
async function switchCompany(page, companyName) {
  const switcher = page.locator('aside button').filter({ hasText: /Company|KAMAL|TST|Main/i }).first();
  await switcher.click();
  await page.waitForTimeout(400);

  const option = page.getByText(new RegExp(companyName, 'i')).first();
  await option.click();
  await page.waitForTimeout(1200);
}

/**
 * Expand a parent menu and navigate to a sub-page.
 */
async function navigateTo(page, parentMenu, subMenu) {
  if (subMenu) {
    const subBtn = page.locator('aside nav button').filter({ hasText: new RegExp(`^${subMenu}$`, 'i') }).first();
    const isVisible = await subBtn.isVisible().catch(() => false);
    if (!isVisible) {
      const parentBtn = page.locator('aside nav button').filter({ hasText: new RegExp(`^${parentMenu}$`, 'i') }).first();
      await parentBtn.click();
      await page.waitForTimeout(400);
    }
    await subBtn.click();
    await page.waitForTimeout(1000);
  } else {
    const parentBtn = page.locator('aside nav button').filter({ hasText: new RegExp(`^${parentMenu}$`, 'i') }).first();
    await parentBtn.click();
    await page.waitForTimeout(800);
  }
}

module.exports = {
  BASE_URL,
  API_URL,
  DEFAULT_USER,
  launchBrowser,
  login,
  switchCompany,
  navigateTo,
};
