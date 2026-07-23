import { test, expect, type Page } from '@playwright/test';
import { uniqueMarker } from '../support/markers';
import { searchMessages, waitForMessage } from '../support/mailpit';
import { findSubmissionMeta } from '../support/wp';

/*
 * These specs load real provider SDKs and hit real verification APIs with the
 * providers' official test keys — external network required. Retries absorb
 * provider/network flake; the raised timeout leaves room for widget loads
 * over a real network.
 */
test.describe.configure({ retries: 2, timeout: 60_000 });

const TOKEN_INPUT = 'input[name="oriel[_captcha_token]"]';

// Widget SDK load + explicit render + (for Turnstile test keys) auto-solve
// can be slow over a real network.
const WIDGET_TIMEOUT = 30_000;

// CaptchaStep uses one message for both the empty-token and the
// failed-verification paths.
const CAPTCHA_ERROR = 'Verification failed. Please try again.';

/**
 * Navigate to a Turnstile fixture page and wait for the always-pass test
 * widget to solve and write its token into the hidden input.
 */
async function gotoTurnstileAndWaitForToken(
  page: Page,
  path: string,
): Promise<string> {
  await page.goto(path);

  const token = page.locator(TOKEN_INPUT);
  await expect(token).not.toHaveValue('', { timeout: WIDGET_TIMEOUT });

  return token.inputValue();
}

test.describe('captcha: turnstile', () => {
  test('happy path: widget solves, submission stored, captcha stays transient', async ({
    page,
  }) => {
    const marker = uniqueMarker('captcha-ts');

    // The always-pass test widget completes without interaction; the render
    // callback writes the token into the hidden input.
    const token = await gotoTurnstileAndWaitForToken(page, '/captcha-turnstile/');

    // CaptchaField renders the target div with provider/sitekey data attrs.
    // Turnstile's actual widget sits in a closed shadow root, invisible to
    // locators — the observable render proof is oriel.js's rendered flag and
    // Turnstile's own hidden response input in the light DOM.
    const widget = page.locator('.oriel-captcha');
    await expect(widget).toHaveAttribute('data-captcha-provider', 'turnstile');
    await expect(widget).toHaveAttribute(
      'data-captcha-sitekey',
      '1x00000000000000000000AA',
    );
    await expect(widget).toHaveAttribute('data-captcha-rendered', 'true');
    await expect(
      widget.locator('input[name="cf-turnstile-response"]'),
    ).toBeAttached();

    await page.fill('input[name="oriel[name]"]', marker);
    await page.fill('input[name="oriel[email]"]', 'captcha-ts@example.test');
    await page.click('form#oriel-form-captcha_turnstile [type="submit"]');

    await expect(
      page.locator('#oriel-captcha_turnstile .oriel-form__message--success'),
    ).toHaveText('Thanks — your captcha_turnstile submission was received.', {
      timeout: WIDGET_TIMEOUT,
    });

    // Submission stored with the regular fields...
    const meta = findSubmissionMeta('_oriel_name', marker);
    expect(meta).not.toBeNull();
    expect(meta!['_oriel_email']).toBe('captcha-ts@example.test');

    // ...but the captcha field is transient: no meta under the field id, no
    // token meta, and the token value appears nowhere in the stored meta.
    expect(meta).not.toHaveProperty('_oriel_captcha');
    expect(meta).not.toHaveProperty('_oriel__captcha_token');
    for (const value of Object.values(meta!)) {
      expect(JSON.stringify(value)).not.toContain(token);
    }

    // Email delivered to the form's recipient with the marker...
    const message = await waitForMessage(marker, { timeoutMs: 20_000 });
    expect(message.To[0].Address).toBe('captcha_turnstile@example.test');
    expect(message.Subject).toBe('Oriel Test: captcha_turnstile');
    expect(message.HTML).toContain(marker);

    // ...and no captcha content: the field is transient and has no email
    // flag, so neither its screen-reader label ("Verification"), the token
    // input name, nor the token value may leak into the body.
    expect(message.HTML).not.toContain('Verification');
    expect(message.HTML).not.toContain('_captcha_token');
    expect(message.HTML).not.toContain(token);
  });

  test('widget resets after AJAX success: turnstile.reset called, token cleared then re-issued', async ({
    page,
  }) => {
    const marker = uniqueMarker('captcha-ts-reset');

    await gotoTurnstileAndWaitForToken(page, '/captcha-turnstile/');

    // Instrument the page: spy on turnstile.reset and record every write to
    // the hidden token input's value (oriel.js resetCaptchaWidget does both).
    // The dummy sitekey always issues the same token string, so token
    // inequality can't prove a reset — the write sequence can.
    await page.evaluate(
      ({ tokenSelector }) => {
        const w = window as any;

        w.__resetCalls = [];
        const originalReset = w.turnstile.reset.bind(w.turnstile);
        w.turnstile.reset = (widgetId: unknown) => {
          w.__resetCalls.push(widgetId);
          return originalReset(widgetId);
        };

        w.__tokenWrites = [];
        const input = document.querySelector(tokenSelector) as HTMLInputElement;
        const descriptor = Object.getOwnPropertyDescriptor(
          HTMLInputElement.prototype,
          'value',
        )!;
        Object.defineProperty(input, 'value', {
          get() {
            return descriptor.get!.call(this);
          },
          set(v: string) {
            w.__tokenWrites.push(v);
            descriptor.set!.call(this, v);
          },
        });
      },
      { tokenSelector: TOKEN_INPUT },
    );

    await page.fill('input[name="oriel[name]"]', marker);
    await page.fill('input[name="oriel[email]"]', 'captcha-ts@example.test');
    await page.click('form#oriel-form-captcha_turnstile [type="submit"]');

    await expect(
      page.locator('#oriel-captcha_turnstile .oriel-form__message--success'),
    ).toBeVisible({ timeout: WIDGET_TIMEOUT });

    // oriel.js resetCaptchaWidget: turnstile.reset(widgetId) exactly once.
    await expect
      .poll(() => page.evaluate(() => (window as any).__resetCalls.length), {
        timeout: WIDGET_TIMEOUT,
      })
      .toBe(1);

    // ...then it explicitly clears the token input, and the reset (always-
    // pass) widget re-solves and writes a fresh token after the clear.
    await expect
      .poll(
        () =>
          page.evaluate(() => {
            const writes = (window as any).__tokenWrites as string[];
            const clearIndex = writes.indexOf('');
            if (clearIndex === -1) {
              return 'no-clear-write';
            }
            const reissued = writes
              .slice(clearIndex + 1)
              .some((v) => v !== '');
            return reissued ? 'cleared-then-reissued' : 'cleared-only';
          }),
        { timeout: WIDGET_TIMEOUT },
      )
      .toBe('cleared-then-reissued');

    // Net effect: the input holds a live token again, ready for a re-submit.
    await expect(page.locator(TOKEN_INPUT)).not.toHaveValue('');
  });

  test('server-side verification failure shows field error, stores nothing, sends nothing', async ({
    page,
  }) => {
    const marker = uniqueMarker('captcha-ts-fail');

    // Always-pass sitekey: the widget still yields a token client-side.
    await gotoTurnstileAndWaitForToken(page, '/captcha-turnstile-fail/');

    await page.fill('input[name="oriel[name]"]', marker);
    await page.fill('input[name="oriel[email]"]', 'captcha-ts@example.test');
    await page.click('form#oriel-form-captcha_turnstile_fail [type="submit"]');

    // CaptchaStep attaches the error to the captcha field id, so it lands in
    // the placeholder next to the widget and flags the field wrapper.
    const form = page.locator('form#oriel-form-captcha_turnstile_fail');
    await expect(form.locator('[data-error-for="oriel_captcha"]')).toHaveText(
      CAPTCHA_ERROR,
      { timeout: WIDGET_TIMEOUT },
    );
    await expect(form.locator('.oriel-field--oriel_captcha')).toHaveClass(
      /oriel-field--has-error/,
    );
    await expect(
      page.locator('#oriel-captcha_turnstile_fail .oriel-form__message--error'),
    ).toBeVisible();

    // Pipeline halted before CreatePostStep and EmailStep.
    expect(findSubmissionMeta('_oriel_name', marker)).toBeNull();
    expect(await searchMessages(marker)).toHaveLength(0);
  });

  test('missing token is rejected server-side with the captcha field error', async ({
    page,
  }) => {
    const marker = uniqueMarker('captcha-ts-notoken');

    // Use the always-pass form: its secret verifies any real token, so a
    // rejection can only come from CaptchaStep's empty-token branch.
    // Let the widget solve, then blank the token — simulating a submit
    // without widget completion.
    await gotoTurnstileAndWaitForToken(page, '/captcha-turnstile/');
    await page
      .locator(TOKEN_INPUT)
      .evaluate((el) => ((el as HTMLInputElement).value = ''));

    await page.fill('input[name="oriel[name]"]', marker);
    await page.fill('input[name="oriel[email]"]', 'captcha-ts@example.test');

    const responsePromise = page.waitForResponse(
      (res) =>
        res.url().includes('/oriel/v1/submit') &&
        res.request().method() === 'POST',
    );
    await page.click('form#oriel-form-captcha_turnstile [type="submit"]');

    // RestResponseStep: field errors -> 422 with an errors map keyed by
    // field id.
    const response = await responsePromise;
    expect(response.status()).toBe(422);
    const body = await response.json();
    expect(body.success).toBe(false);
    expect(body.errors.captcha).toBe(CAPTCHA_ERROR);

    // oriel.js paints the error into the captcha field's placeholder.
    const form = page.locator('form#oriel-form-captcha_turnstile');
    await expect(form.locator('[data-error-for="oriel_captcha"]')).toHaveText(
      CAPTCHA_ERROR,
    );

    expect(findSubmissionMeta('_oriel_name', marker)).toBeNull();
    expect(await searchMessages(marker)).toHaveLength(0);
  });
});

test.describe('captcha: recaptcha', () => {
  test('happy path: checkbox click yields token, submission stored and emailed', async ({
    page,
  }) => {
    const marker = uniqueMarker('captcha-re');

    await page.goto('/captcha-recaptcha/');

    const widget = page.locator('.oriel-captcha');
    await expect(widget).toHaveAttribute('data-captcha-provider', 'recaptcha');

    // reCAPTCHA v2 needs a click on the checkbox inside its anchor iframe.
    const anchorFrame = page.frameLocator('iframe[title="reCAPTCHA"]');
    const checkbox = anchorFrame.locator('#recaptcha-anchor');
    await expect(checkbox).toBeVisible({ timeout: WIDGET_TIMEOUT });
    await checkbox.click();

    // Test-key widget shows its testing warning and then checks itself.
    await expect(checkbox).toHaveAttribute('aria-checked', 'true', {
      timeout: WIDGET_TIMEOUT,
    });
    await expect
      .poll(() => page.locator(TOKEN_INPUT).inputValue(), {
        timeout: WIDGET_TIMEOUT,
      })
      .not.toBe('');

    await page.fill('input[name="oriel[name]"]', marker);
    await page.fill('input[name="oriel[email]"]', 'captcha-re@example.test');
    await page.click('form#oriel-form-captcha_recaptcha [type="submit"]');

    await expect(
      page.locator('#oriel-captcha_recaptcha .oriel-form__message--success'),
    ).toHaveText('Thanks — your captcha_recaptcha submission was received.', {
      timeout: WIDGET_TIMEOUT,
    });

    const meta = findSubmissionMeta('_oriel_name', marker);
    expect(meta).not.toBeNull();
    expect(meta!['_oriel_email']).toBe('captcha-re@example.test');
    expect(meta).not.toHaveProperty('_oriel_captcha');
    expect(meta).not.toHaveProperty('_oriel__captcha_token');

    const message = await waitForMessage(marker, { timeoutMs: 20_000 });
    expect(message.To[0].Address).toBe('captcha_recaptcha@example.test');
    expect(message.Subject).toBe('Oriel Test: captcha_recaptcha');
    expect(message.HTML).toContain(marker);
    expect(message.HTML).not.toContain('_captcha_token');
  });
});
