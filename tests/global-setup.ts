import { chromium, FullConfig } from '@playwright/test';
import { execSync } from 'child_process';
import * as path from 'path';
import * as fs from 'fs';

async function globalSetup(config: FullConfig) {
  // 1. Reset the test database
  const resetScript = path.resolve(__dirname, 'fixtures/reset-db.sh');
  console.log('Resetting test database...');
  execSync(`bash ${resetScript}`, { stdio: 'inherit' });

  // 2. Log in once and persist auth state
  const authDir = path.resolve(__dirname, '.auth');
  if (!fs.existsSync(authDir)) fs.mkdirSync(authDir, { recursive: true });

  const baseURL = config.projects[0]?.use?.baseURL ?? 'http://localhost:8080';
  const browser = await chromium.launch();
  const context = await browser.newContext({ baseURL });
  const page = await context.newPage();

  await page.goto('/login.php');
  await page.fill('#username', 'testadmin');
  await page.fill('#password', 'TestPassword1!');
  await page.click('button[type="submit"]');

  // Wait until we land on the member list (login succeeded)
  await page.waitForURL((url) => !url.pathname.includes('login.php'), { timeout: 10_000 });

  await context.storageState({ path: path.resolve(__dirname, '.auth/admin.json') });
  await browser.close();

  console.log('Auth state saved.');
}

export default globalSetup;
