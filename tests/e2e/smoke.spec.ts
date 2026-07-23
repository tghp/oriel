import { test, expect } from '@playwright/test';
import { CACHED_URL, MAILPIT_URL } from '../support/urls';

test.describe('stack smoke', () => {
  test('kitchen sink form renders with security fields', async ({ page }) => {
    await page.goto('/kitchen-sink/');

    const form = page.locator('form#oriel-form-kitchen_sink');
    await expect(form).toBeVisible();
    await expect(form.locator('input[name="_oriel_tk"]')).toBeAttached();
    await expect(form.locator('input[name="oriel[name]"]')).toBeVisible();
    await expect(form.locator('textarea[name="oriel[message]"]')).toBeVisible();
  });

  test('cached port serves a HIT on second load with no cookies', async ({
    request,
  }) => {
    const url = `${CACHED_URL}/kitchen-sink/`;

    const first = await request.get(url);
    expect(first.status()).toBe(200);
    expect(first.headers()['set-cookie']).toBeUndefined();

    const second = await request.get(url);
    expect(second.status()).toBe(200);
    expect(second.headers()['x-cache-status']).toBe('HIT');
  });

  test('mailpit API is reachable', async ({ request }) => {
    const res = await request.get(`${MAILPIT_URL}/api/v1/messages`);
    expect(res.status()).toBe(200);
  });
});
