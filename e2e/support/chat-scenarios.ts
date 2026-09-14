import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

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
    const isModernChat = scenario.path === '/chat/vue' || scenario.path === '/chat/svelte';
    if (isModernChat) {
      await installModernChatWebSocket(page);
    }

    await login(page);
    await page.waitForLoadState('networkidle');

    const browserErrors: string[] = [];
    page.on('pageerror', (error) => browserErrors.push(error.message));
    page.on('console', (message) => {
      if (message.type() === 'error') {
        browserErrors.push(message.text());
      }
    });

    const payloadPromise = scenario.network ? captureNetworkPayload(page, scenario.network) : null;
    await page.goto(scenario.path);

    if (payloadPromise && scenario.network) {
      assertNetworkPayload(await payloadPromise, scenario.network.format);
    }

    await expect(page.locator(scenario.messageInput)).toBeVisible();
    if (isModernChat) {
      await assertModernChatAccessibility(page, scenario.messageInput);
      await assertModernChatSearch(page);
      await expect(page.locator('link[rel="manifest"]')).toHaveAttribute('href', '/manifest.webmanifest');
      await page.context().setOffline(true);
      await expect(page.getByText('You’re offline.')).toBeVisible();
      await page.context().setOffline(false);
      await expect(page.getByText('You’re offline.')).toBeHidden();
    }
    await assertNoSeriousAccessibilityViolations(page);

    const message = `${scenario.name} message ${Date.now()}`;
    if (scenario.authorInput) {
      await page.locator(scenario.authorInput).fill('e2euser');
    }
    await page.locator(scenario.messageInput).fill(message);
    if (isModernChat) {
      await page.getByRole('button', { name: 'Send Message' }).click();
    } else {
      await page.locator(scenario.submitButton).click();
    }

    await expect(page.locator('#messagewindow')).toContainText(message);
    expect(browserErrors, browserErrors.join('\n')).toEqual([]);
  });
}

async function assertModernChatAccessibility(page: Page, messageInput: string): Promise<void> {
  await expect(page.getByRole('main', { name: 'CodeIgniter Chat' })).toBeVisible();

  const messageLog = page.getByRole('log', { name: 'Chat messages' });
  await expect(messageLog).toBeVisible();
  await expect(messageLog).toHaveAttribute('aria-live', 'polite');

  const skipLink = page.getByRole('link', { name: 'Skip to messages' });
  await skipLink.focus();
  await expect(skipLink).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(messageLog).toBeFocused();

  const input = page.locator(messageInput);
  await expect(input).toHaveAttribute('aria-describedby', 'formatting-help');
  await expect(input).toHaveAttribute('aria-invalid', 'false');
  await input.focus();
  const inputFocusOutline = await input.evaluate((element) => {
    const styles = getComputedStyle(element);
    return {
      style: styles.outlineStyle,
      width: Number.parseFloat(styles.outlineWidth),
    };
  });
  expect(inputFocusOutline.style).not.toBe('none');
  expect(inputFocusOutline.width).toBeGreaterThanOrEqual(3);
  await expect(page.getByRole('group', { name: 'Message formatting' })).toBeVisible();
  const formattingButton = page.getByRole('button', { name: 'Format as bold' });
  await expect(formattingButton).toBeVisible();

  await input.fill('Clear this draft');
  await input.press('Escape');
  await expect(input).toBeFocused();
  await expect(input).toHaveValue('');

  await input.fill('First line');
  await input.press('Shift+Enter');
  await input.pressSequentially('Second line');
  await expect(input).toHaveValue('First line\nSecond line');
  await input.press('Escape');

  await page.emulateMedia({ reducedMotion: 'no-preference' });
  const defaultTransitionMs = await formattingButton.evaluate((element) => {
    const duration = getComputedStyle(element).transitionDuration;
    return duration.endsWith('ms') ? Number.parseFloat(duration) : Number.parseFloat(duration) * 1000;
  });
  expect(defaultTransitionMs).toBeGreaterThan(1);

  await page.emulateMedia({ reducedMotion: 'reduce' });
  expect(await page.evaluate(() => window.matchMedia('(prefers-reduced-motion: reduce)').matches)).toBe(true);
  const reducedTransitionMs = await formattingButton.evaluate((element) => {
    const duration = getComputedStyle(element).transitionDuration;
    return duration.endsWith('ms') ? Number.parseFloat(duration) : Number.parseFloat(duration) * 1000;
  });
  expect(reducedTransitionMs).toBeLessThanOrEqual(1);
  await page.emulateMedia({ reducedMotion: 'no-preference' });
}

async function assertModernChatSearch(page: Page): Promise<void> {
  const expectedDates = await page.evaluate(() => ({
    from: Math.floor(new Date(2026, 8, 13, 0, 0, 0, 0).getTime() / 1000),
    to: Math.floor(new Date(2026, 8, 14, 23, 59, 59, 999).getTime() / 1000),
  }));

  await page.getByLabel('Text').fill('stale');
  await page.getByRole('button', { name: 'Search', exact: true }).click();

  await page.getByLabel('Text').fill('needle');
  await page.getByLabel('From').fill('2026-09-13');
  await page.getByLabel('To').fill('2026-09-14');
  await page.getByRole('button', { name: 'Search', exact: true }).click();

  await expect(page.getByText('Showing filtered results.')).toBeVisible();
  await expect(page.locator('#messagewindow mark')).toContainText(/needle/i);
  await expect(page.locator('#messagewindow')).toContainText('<script>alert(1)</script>');
  await expect(page.locator('#messagewindow')).not.toContainText('Stale result');
  await expect(page.locator('#messagewindow script')).toHaveCount(0);
  await expect(page.locator('#messagewindow .message-content [onmouseover]')).toHaveCount(0);
  await expect(page.locator('#messagewindow .message-content a')).toHaveAttribute('href', 'https://example.test');
  await expect
    .poll(async () =>
      page.evaluate(() => window.__chatSearchRequests?.find((request) => request.search?.text === 'needle')?.search),
    )
    .toEqual({ text: 'needle', from: expectedDates.from, to: expectedDates.to });

  await page.getByRole('button', { name: 'Load More Messages' }).click();
  await expect(page.locator('#messagewindow')).toContainText('Second needle result');
  await expect
    .poll(async () =>
      page.evaluate(() =>
        window.__chatSearchRequests?.some(
          (request) =>
            request.page === 2 &&
            request.search?.text === 'needle' &&
            request.search.from === window.__expectedSearchDates?.from &&
            request.search.to === window.__expectedSearchDates?.to,
        ),
      ),
    )
    .toBe(true);

  await page.getByRole('button', { name: 'Clear search' }).click();
  await expect(page.getByText('Showing filtered results.')).toBeHidden();

  await page.getByLabel('Text').fill('server-error');
  await page.getByRole('button', { name: 'Search', exact: true }).click();
  await expect(page.getByRole('alert')).toContainText('Search filter rejected');
  await expect(page.getByRole('button', { name: 'Search', exact: true })).toBeEnabled();
  await expect(page.getByRole('button', { name: 'Search', exact: true })).toHaveText('Search');
  await page.getByRole('button', { name: 'Clear search' }).click();
  await expect(page.getByRole('alert')).toBeHidden();
}

async function installModernChatWebSocket(page: Page): Promise<void> {
  await page.addInitScript(() => {
    window.__chatSearchRequests = [];
    window.__expectedSearchDates = {
      from: Math.floor(new Date(2026, 8, 13, 0, 0, 0, 0).getTime() / 1000),
      to: Math.floor(new Date(2026, 8, 14, 23, 59, 59, 999).getTime() / 1000),
    };

    class MockChatWebSocket extends EventTarget {
      constructor(url) {
        super();
        this.url = url;
        setTimeout(() => this.dispatchEvent(new Event('open')), 0);
      }

      send(rawMessage) {
        const request = JSON.parse(rawMessage);
        let response = null;

        if (request.action === 'getMessages' && request.search) {
          window.__chatSearchRequests.push(request);
          const page = request.page || 1;
          response =
            request.search.text === 'server-error'
              ? {
                  action: 'error',
                  data: { requestId: request.requestId, code: 'INVALID_SEARCH', message: 'Search filter rejected' },
                }
              : {
                  action: 'searchResults',
                  data: {
                    requestId: request.requestId,
                    messages:
                      request.search.text === 'stale'
                        ? [
                            {
                              user: 'e2euser',
                              msg: 'Stale result',
                              timestamp: Math.floor(Date.now() / 1000),
                            },
                          ]
                        : page === 1
                          ? [
                              {
                                user: 'e2euser',
                                msg: 'Needle <script>alert(1)</script> https://example.test"onmouseover="alert(1) result',
                                timestamp: Math.floor(Date.now() / 1000),
                              },
                            ]
                          : [
                              {
                                user: 'e2euser',
                                msg: 'Second needle result',
                                timestamp: Math.floor(Date.now() / 1000) - 60,
                              },
                            ],
                    pagination: { hasNext: page === 1 },
                    filters: request.search,
                  },
                };
        } else if (request.action === 'getMessages') {
          response = {
            action: 'messages',
            data: {
              messages: [],
              pagination: { hasNext: false },
            },
          };
        } else if (request.action === 'sendMessage') {
          response = {
            action: 'newMessage',
            data: {
              user: request.username,
              msg: request.message,
              timestamp: Math.floor(Date.now() / 1000),
            },
          };
        }

        if (response) {
          const delay = request.search?.text === 'stale' ? 150 : 0;
          setTimeout(() => this.dispatchEvent(new MessageEvent('message', { data: JSON.stringify(response) })), delay);
        }
      }

      close() {
        this.dispatchEvent(new Event('close'));
      }
    }

    window.WebSocket = MockChatWebSocket;
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
  return page
    .waitForResponse((response) => {
      const resourceType = response.request().resourceType();
      if (resourceType !== 'fetch' && resourceType !== 'xhr') {
        return false;
      }

      return new URL(response.url()).pathname.endsWith(network.path);
    })
    .then(async (response) => {
      expect(response.ok()).toBeTruthy();
      return network.format === 'json' ? await response.json() : await response.text();
    });
}

async function assertNoSeriousAccessibilityViolations(page: Page): Promise<void> {
  const results = await new AxeBuilder({ page }).analyze();
  const blocking = results.violations.filter(
    (violation) => violation.impact === 'critical' || violation.impact === 'serious',
  );

  expect(blocking, blocking.map((violation) => `${violation.id}: ${violation.help}`).join('\n')).toEqual([]);
}
