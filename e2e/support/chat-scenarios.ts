import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type Response } from '@playwright/test';

type NetworkFormat = 'json' | 'xml';

interface ChatScenario {
  name: string;
  path: string;
  messageInput: string;
  submitButton: string;
  authorInput?: string;
  network?: {
    path: string;
    format: NetworkFormat;
  };
}

export function chatScenario(scenario: ChatScenario): void {
  test(`${scenario.name} posts and displays a message without browser errors`, async ({ page }) => {
    await login(page);

    const browserErrors: string[] = [];
    page.on('pageerror', error => browserErrors.push(error.message));
    page.on('console', message => {
      if (message.type() === 'error') {
        browserErrors.push(message.text());
      }
    });

    const payloadPromise = scenario.network
      ? captureNetworkPayload(page, scenario.network)
      : null;

    await page.goto(scenario.path);

    if (payloadPromise && scenario.network) {
      assertNetworkPayload(await payloadPromise, scenario.network.format);
    }

    await expect(page.locator(scenario.messageInput)).toBeVisible();
    await assertNoSeriousAccessibilityViolations(page);

    const message = `${scenario.name} message ${Date.now()}`;
    if (scenario.authorInput) {
      await page.locator(scenario.authorInput).fill('e2euser');
    }
    await page.locator(scenario.messageInput).fill(message);
    await page.locator(scenario.submitButton).click();

    await expect(page.locator('#messagewindow')).toContainText(message);
    expect(browserErrors, browserErrors.join('\n')).toEqual([]);
  });
}

async function login(page: Page): Promise<void> {
  await page.goto('/auth/login');
  await page.locator('#username').fill('e2euser');
  await page.locator('#password').fill('Playwright123!');
  await page.getByRole('button', { name: 'Login' }).click();
  await expect(page).toHaveURL(/\/chat$/);
}

function assertNetworkPayload(payload: unknown, format: NetworkFormat): void {
  if (format === 'json') {
    expect(payload).toHaveProperty('messages');
    expect(payload).toHaveProperty('pagination');
    return;
  }

  const xml = String(payload);
  expect(xml).toContain('<messages>');
  expect(xml).not.toContain('<parsererror>');
}

function captureNetworkPayload(page: Page, network: NonNullable<ChatScenario['network']>): Promise<unknown> {
  return new Promise((resolve, reject) => {
    const handleResponse = async (response: Response): Promise<void> => {
      if (!new URL(response.url()).pathname.endsWith(network.path)) {
        return;
      }

      page.off('response', handleResponse);

      try {
        expect(response.ok()).toBeTruthy();
        resolve(network.format === 'json' ? await response.json() : await response.text());
      } catch (error) {
        reject(error);
      }
    };

    page.on('response', handleResponse);
  });
}

async function assertNoSeriousAccessibilityViolations(page: Page): Promise<void> {
  const results = await new AxeBuilder({ page }).analyze();
  const blocking = results.violations.filter(violation =>
    violation.impact === 'critical' || violation.impact === 'serious',
  );

  expect(blocking, blocking.map(violation => `${violation.id}: ${violation.help}`).join('\n')).toEqual([]);
}
