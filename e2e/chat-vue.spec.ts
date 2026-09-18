import { expect } from '@playwright/test';
import { chatScenario } from './support/chat-scenarios';

chatScenario({
  name: 'Vue chat',
  path: '/chat/vue',
  messageInput: '#message-input',
  submitButton: 'button[type="submit"]',
  async afterAssertions(page) {
    await page.getByRole('button', { name: 'Webhooks' }).click();
    const panel = page.getByRole('region', { name: 'Outgoing webhooks' });
    await expect(panel).toBeVisible();

    const url = `https://1.1.1.1/hooks/e2e-${Date.now()}`;
    await panel.getByLabel('Endpoint URL').fill(url);
    await panel.getByLabel('reaction.added').check();
    await panel.getByRole('button', { name: 'Create webhook' }).click();

    await expect(panel.getByText('Webhook created.')).toBeVisible();
    await expect(panel.getByText('Copy this signing secret now.')).toBeVisible();
    await expect(panel.locator('code')).toHaveText(/^[a-f0-9]{64}$/);
    const card = panel.locator('.webhook-card').filter({ hasText: url });
    await expect(card).toContainText('message.created, reaction.added');

    await card.getByLabel('Active').uncheck();
    await expect(panel.getByText('Webhook updated.')).toBeVisible();
    await card.getByRole('button', { name: 'Delivery history' }).click();
    await expect(card.getByText('No deliveries yet.')).toBeVisible();

    page.once('dialog', (dialog) => dialog.accept());
    await card.getByRole('button', { name: 'Delete' }).click();
    await expect(panel.getByText('Webhook deleted.')).toBeVisible();
    await expect(card).toHaveCount(0);
  },
});
