import { test, expect } from '@playwright/test';

test.describe('non-ajax submission flow', () => {
  test('validation errors redirect back and repopulate values', async ({
    page,
  }) => {
    const marker = `e2e-${Date.now()}`;

    await page.goto('/kitchen-sink/');
    // Fill only the name; leave the required email empty to force a
    // validation error.
    await page.fill('input[name="oriel[name]"]', marker);
    await page.click('form#oriel-form-kitchen_sink [type="submit"]');

    await expect(page).toHaveURL(/oriel-errors=kitchen_sink/);
    await expect(page.locator('.oriel-form__message--error')).toBeVisible();
    // Stored state repopulates the submitted value after the redirect.
    await expect(page.locator('input[name="oriel[name]"]')).toHaveValue(
      marker,
    );
  });
});
