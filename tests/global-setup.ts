/**
 * Playwright global setup — resets test database and saves admin auth state
 *
 * @copyright 2024 Philippe Vollenweider
 * @license   AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>
 */

import { chromium, FullConfig } from '@playwright/test';
import { execSync } from 'child_process';
import * as path from 'path';
import * as fs from 'fs';

const ROLE_ACCOUNTS = [
  { role: 'admin',    username: 'testadmin' },
  { role: 'manager',  username: 'testmanager' },
  { role: 'user',     username: 'testuser' },
  { role: 'readonly', username: 'testreadonly' },
] as const;

async function globalSetup(config: FullConfig) {
  // 1. Reset the test database
  const resetScript = path.resolve(__dirname, 'fixtures/reset-db.sh');
  console.log('Resetting test database...');
  execSync(`bash ${resetScript}`, { stdio: 'inherit' });

  // 2. Log in as each role and persist auth state
  const authDir = path.resolve(__dirname, '.auth');
  if (!fs.existsSync(authDir)) fs.mkdirSync(authDir, { recursive: true });

  const baseURL = config.projects[0]?.use?.baseURL ?? 'http://localhost:8080';
  const browser = await chromium.launch();

  for (const { role, username } of ROLE_ACCOUNTS) {
    const context = await browser.newContext({ baseURL });
    const page = await context.newPage();

    await page.goto('/login.php');
    await page.fill('#username', username);
    await page.fill('#password', 'TestPassword1!');
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.includes('login.php'), { timeout: 10_000 });

    await context.storageState({ path: path.resolve(__dirname, `.auth/${role}.json`) });
    await context.close();
    console.log(`Auth state saved for role: ${role}`);
  }

  await browser.close();
  console.log('All auth states saved.');
}

export default globalSetup;
