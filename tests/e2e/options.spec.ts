import { test, expect } from '@playwright/test';
import { waitForMessage } from '../support/mailpit';
import { uniqueMarker } from '../support/markers';
import { findSubmissionMeta } from '../support/wp';

test.describe('form options', () => {
  test('redirect option lands on the target page and stores the submission', async ({
    page,
  }) => {
    const marker = uniqueMarker('redirect');

    await page.goto('/redirect-form/');
    await page.fill('input[name="oriel[marker]"]', marker);
    await page.click('form#oriel-form-redirect_form [type="submit"]');

    // RedirectStep sends wp_redirect('/redirect-target/') on success instead
    // of the default ?oriel-submitted redirect-back.
    await expect(page).toHaveURL(/\/redirect-target\/$/);
    await expect(page.locator('body')).toContainText('Redirect landed.');

    // CreatePostStep ran before the redirect — submission meta is stored.
    const meta = findSubmissionMeta('_oriel_marker', marker);
    expect(meta).not.toBeNull();
    expect(meta?._oriel_marker).toBe(marker);
  });

  test('redirect option emails the submission', async ({ page }) => {
    const marker = uniqueMarker('redirect-mail');

    await page.goto('/redirect-form/');
    await page.fill('input[name="oriel[marker]"]', marker);
    await page.click('form#oriel-form-redirect_form [type="submit"]');
    await expect(page).toHaveURL(/\/redirect-target\/$/);

    // The marker field has email:true so it appears in the body.
    const message = await waitForMessage(marker);
    expect(message.To[0].Address).toBe('redirect_form@example.test');
    expect(message.Subject).toBe('Oriel Test: redirect_form');
    expect(`${message.Text}\n${message.HTML}`).toContain(marker);
  });

  test('delete_after_processing deletes the submission post after processing', async ({
    page,
  }) => {
    const marker = uniqueMarker('delete-after');

    await page.goto('/delete-after/');
    await page.fill('input[name="oriel[marker]"]', marker);
    await page.click('form#oriel-form-delete_after [type="submit"]');

    await expect(page).toHaveURL(/oriel-submitted=delete_after/);
    await expect(page.locator('.oriel-form__message--success')).toContainText(
      'Thanks — your delete_after submission was received.',
    );

    // CleanupStep hard-deleted the post before the redirect back.
    expect(findSubmissionMeta('_oriel_marker', marker)).toBeNull();

    // Escape hatch note: CleanupStep skips deletion when the post carries
    // _oriel_do_not_delete = '1'. It exists in source (CleanupStep.php) but is
    // untested here — setting that meta between CreatePostStep and CleanupStep
    // needs a PHP hook (e.g. oriel_after_process) registered in the web
    // process, which would mean modifying the fixture mu-plugin.
  });

  test('delete_after_processing still sends the notification email', async ({
    page,
  }) => {
    // Pipeline order in FormProcessor::run(): Security, Captcha, Validate,
    // CreatePost, Hooks, Email, Cleanup, Redirect, RestResponse — Email
    // precedes Cleanup, so deletion must not cost the notification.
    const marker = uniqueMarker('delete-after-mail');

    await page.goto('/delete-after/');
    await page.fill('input[name="oriel[marker]"]', marker);
    await page.click('form#oriel-form-delete_after [type="submit"]');
    await expect(page).toHaveURL(/oriel-submitted=delete_after/);

    const message = await waitForMessage(marker);
    expect(message.To[0].Address).toBe('delete_after@example.test');
    expect(`${message.Text}\n${message.HTML}`).toContain(marker);

    // Post is gone even though the email went out.
    expect(findSubmissionMeta('_oriel_marker', marker)).toBeNull();
  });

  test('hide shortcode arg renders the form collapsed behind a toggle button', async ({
    page,
  }) => {
    await page.goto('/toggle/');

    // wrapHidden(): <button class="oriel-form__toggle" aria-expanded="false"
    // aria-controls="oriel-toggle-toggle"> + <div id="oriel-toggle-toggle"
    // class="oriel-form__hidden" hidden> around the form markup.
    const button = page.locator('button.oriel-form__toggle');
    const container = page.locator('#oriel-toggle-toggle');
    const form = page.locator('form#oriel-form-toggle');

    await expect(button).toBeVisible();
    await expect(button).toHaveText('Show form');
    await expect(button).toHaveAttribute('aria-expanded', 'false');
    await expect(button).toHaveAttribute('aria-controls', 'oriel-toggle-toggle');
    await expect(container).toHaveClass('oriel-form__hidden');
    await expect(container).toHaveAttribute('hidden');
    await expect(form).toBeHidden();

    // First click expands: oriel.js handleToggle removes [hidden] and flips
    // aria-expanded.
    await button.click();
    await expect(container).not.toHaveAttribute('hidden');
    await expect(button).toHaveAttribute('aria-expanded', 'true');
    await expect(form).toBeVisible();

    // Second click collapses again (handleToggle re-adds [hidden]).
    await button.click();
    await expect(container).toHaveAttribute('hidden');
    await expect(button).toHaveAttribute('aria-expanded', 'false');
    await expect(form).toBeHidden();
  });

  test('revealed toggle form submits end-to-end', async ({ page }) => {
    const marker = uniqueMarker('toggle');

    await page.goto('/toggle/');
    await page.click('button.oriel-form__toggle');

    const form = page.locator('form#oriel-form-toggle');
    await expect(form).toBeVisible();

    await page.fill('input[name="oriel[marker]"]', marker);
    await page.click('form#oriel-form-toggle [type="submit"]');

    await expect(page).toHaveURL(/oriel-submitted=toggle/);

    // The redirect-back re-renders the form collapsed; handleScrollOnLoad in
    // oriel.js sees ?oriel-submitted=toggle and re-expands the hidden parent,
    // making the confirmation visible.
    await expect(page.locator('.oriel-form__message--success')).toContainText(
      'Thanks — your toggle submission was received.',
    );
    await expect(page.locator('button.oriel-form__toggle')).toHaveAttribute(
      'aria-expanded',
      'true',
    );

    const meta = findSubmissionMeta('_oriel_marker', marker);
    expect(meta).not.toBeNull();
    expect(meta?._oriel_marker).toBe(marker);
  });
});
