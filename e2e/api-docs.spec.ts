import { expect, test } from '@playwright/test';

test('renders the embedded OpenAPI contract with Swagger UI', async ({ page }) => {
  const consoleErrors: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'error') {
      consoleErrors.push(message.text());
    }
  });

  await page.goto('/api/docs');

  await expect(page).toHaveTitle('CodeIgniter Chat API documentation');
  await expect(page.locator('.swagger-ui .info .title')).toContainText('CodeIgniter Chat API');
  await expect(page.locator('.swagger-ui .opblock')).toHaveCount(24);
  expect(consoleErrors).toEqual([]);
});
