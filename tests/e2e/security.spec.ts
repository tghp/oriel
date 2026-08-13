import { test, expect, type Page } from '@playwright/test';
import { BASE_URL } from '../support/urls';
import { uniqueMarker } from '../support/markers';
import { findSubmissionMeta } from '../support/wp';

/**
 * Security checks (SecurityStep) run before validation and post creation, in
 * order: HoneypotCheck, RateLimitCheck, TimingCheck, NonceCheck. A rejection
 * sets errors['security'] and halts the pipeline, so no oriel_submission post
 * is created. For this non-AJAX form, RedirectStep stores state and redirects
 * back with ?oriel-errors={formId}; FormRenderer then shows the same generic
 * banner used for validation errors (.oriel-form__message--error) — the
 * 'security' error key matches no field id, so no field-level error appears.
 */

const FORM_URL = '/security-min/';
const SUBMIT_ENDPOINT = '/oriel/v1/submit';
const FORM = 'form#oriel-form-security_min';
const MARKER_INPUT = `${FORM} input[name="oriel[marker]"]`;
// HoneypotCheck::CANDIDATES starts with 'comment'; security_min's only field
// id is 'marker', so the first candidate wins.
const HONEYPOT_INPUT = `${FORM} input[name="comment"]`;
const TIMING_INPUT = `${FORM} input[name="_oriel_tk"]`;
const NONCE_INPUT = `${FORM} input[name="_oriel_nonce"]`;
const MARKER_META_KEY = '_oriel_marker';

const GENERIC_ERROR =
  'There were errors with your submission. Please correct them and try again.';
const CONFIRMATION = 'Thanks — your security_min submission was received.';

/** Valid, unique public IPv4 — ClientIp::fromHeader runs FILTER_VALIDATE_IP. */
function uniqueTestIp(): string {
  const octet = () => 1 + Math.floor(Math.random() * 254);
  return `13.${octet()}.${octet()}.${octet()}`;
}

async function submitMarker(page: Page, marker: string): Promise<void> {
  await page.fill(MARKER_INPUT, marker);
  await page.click(`${FORM} [type="submit"]`);
}

async function expectRejected(page: Page): Promise<void> {
  await expect(page).toHaveURL(/oriel-errors=security_min/);
  await expect(page.locator('.oriel-form__message--error')).toHaveText(
    GENERIC_ERROR,
  );
}

async function expectAccepted(page: Page): Promise<void> {
  await expect(page).toHaveURL(/oriel-submitted=security_min/);
  await expect(page.locator('.oriel-form__message--success')).toHaveText(
    CONFIRMATION,
  );
}

test.describe('honeypot', () => {
  test('filled honeypot rejects with the generic message and stores nothing', async ({
    page,
  }) => {
    const marker = uniqueMarker('sec-honeypot');

    await page.goto(FORM_URL);

    // Rendered as text input inside an off-screen aria-hidden container.
    const honeypot = page.locator(HONEYPOT_INPUT);
    await expect(honeypot).toBeAttached();
    await expect(honeypot).toHaveValue('');
    await expect(
      page.locator(`${FORM} div[aria-hidden="true"] input[name="comment"]`),
    ).toBeAttached();

    // A bot autofills every field, including the trap.
    await honeypot.fill('http://spam.example.com');
    await submitMarker(page, marker);

    await expectRejected(page);
    expect(findSubmissionMeta(MARKER_META_KEY, marker)).toBeNull();
  });
});

test.describe('timing — min_time', () => {
  // Restore a real minimum-fill time for this describe block only.
  test.use({ extraHTTPHeaders: { 'X-Oriel-Test': '{"min_time":3}' } });

  test('instant submission is rejected', async ({ page }) => {
    const marker = uniqueMarker('sec-toofast');

    await page.goto(FORM_URL);
    await submitMarker(page, marker);

    await expectRejected(page);
    expect(findSubmissionMeta(MARKER_META_KEY, marker)).toBeNull();
  });

  test('submission after the minimum time is accepted', async ({ page }) => {
    const marker = uniqueMarker('sec-slowenough');

    await page.goto(FORM_URL);
    // min_time is 3s; wait past it so elapsed >= 3 on the server clock.
    await page.waitForTimeout(4000);
    await submitMarker(page, marker);

    await expectAccepted(page);
    expect(findSubmissionMeta(MARKER_META_KEY, marker)).not.toBeNull();
  });
});

test.describe('timing — token tampering', () => {
  test('token older than max_time is rejected', async ({ page }) => {
    const marker = uniqueMarker('sec-stale');

    await page.goto(FORM_URL);

    // base64(now - 90000): past the 86400s max_time default in TimingCheck.
    const staleToken = Buffer.from(
      String(Math.floor(Date.now() / 1000) - 90_000),
    ).toString('base64');

    await page
      .locator(TIMING_INPUT)
      .evaluate((el, value) => {
        (el as HTMLInputElement).value = value;
      }, staleToken);
    await submitMarker(page, marker);

    await expectRejected(page);
    expect(findSubmissionMeta(MARKER_META_KEY, marker)).toBeNull();
  });

  test('malformed (non-base64) token is rejected', async ({ page }) => {
    const marker = uniqueMarker('sec-garbage-tk');

    await page.goto(FORM_URL);

    // '!' is outside the base64 alphabet — strict base64_decode returns false.
    await page.locator(TIMING_INPUT).evaluate((el) => {
      (el as HTMLInputElement).value = '!!!not-base64!!!';
    });
    await submitMarker(page, marker);

    await expectRejected(page);
    expect(findSubmissionMeta(MARKER_META_KEY, marker)).toBeNull();
  });
});

test.describe('rate limit', () => {
  test('third submission from one IP is rejected; another IP is unaffected', async ({
    browser,
  }) => {
    // Only override rate_limit — min_time stays at the fixture's fast 0.
    const overrides = { 'X-Oriel-Test': '{"rate_limit":2}' };

    const limitedContext = await browser.newContext({
      baseURL: BASE_URL,
      extraHTTPHeaders: { ...overrides, 'X-Oriel-Test-IP': uniqueTestIp() },
    });
    const page = await limitedContext.newPage();

    // RateLimitCheck rejects once the pre-submit count reaches the limit, so
    // with rate_limit=2 the first two pass and the third is rejected.
    const first = uniqueMarker('sec-rl-1');
    await page.goto(FORM_URL);
    await submitMarker(page, first);
    await expectAccepted(page);

    const second = uniqueMarker('sec-rl-2');
    await page.goto(FORM_URL);
    await submitMarker(page, second);
    await expectAccepted(page);

    const third = uniqueMarker('sec-rl-3');
    await page.goto(FORM_URL);
    await submitMarker(page, third);
    await expectRejected(page);
    expect(findSubmissionMeta(MARKER_META_KEY, third)).toBeNull();

    await limitedContext.close();

    // A different client IP gets its own transient bucket.
    const otherContext = await browser.newContext({
      baseURL: BASE_URL,
      extraHTTPHeaders: { ...overrides, 'X-Oriel-Test-IP': uniqueTestIp() },
    });
    const otherPage = await otherContext.newPage();

    const isolated = uniqueMarker('sec-rl-other');
    await otherPage.goto(FORM_URL);
    await submitMarker(otherPage, isolated);
    await expectAccepted(otherPage);
    expect(findSubmissionMeta(MARKER_META_KEY, isolated)).not.toBeNull();

    await otherContext.close();
  });
});

test.describe('nonce (logged-in only)', () => {
  // Run this group sequentially in one worker: concurrent logins to the same
  // WP account race on the session_tokens user-meta (read-modify-write), and
  // a clobbered session renders the form anonymously — without a nonce.
  test.describe.configure({ mode: 'default' });

  async function loginAsAdmin(page: Page): Promise<void> {
    await page.goto('/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');
    await page.click('#wp-submit');
    await page.waitForURL(/wp-admin/);
  }

  test('logged-in render includes the nonce and a valid one is accepted', async ({
    page,
  }) => {
    const marker = uniqueMarker('sec-nonce-ok');

    await loginAsAdmin(page);
    await page.goto(FORM_URL);

    await expect(page.locator(NONCE_INPUT)).toBeAttached();

    await submitMarker(page, marker);

    await expectAccepted(page);
    expect(findSubmissionMeta(MARKER_META_KEY, marker)).not.toBeNull();
  });

  test('logged-in tampered nonce is rejected', async ({ page }) => {
    const marker = uniqueMarker('sec-nonce-bad');

    await loginAsAdmin(page);
    await page.goto(FORM_URL);

    await page.locator(NONCE_INPUT).evaluate((el) => {
      (el as HTMLInputElement).value = 'deadbeef00';
    });
    await submitMarker(page, marker);

    await expectRejected(page);
    expect(findSubmissionMeta(MARKER_META_KEY, marker)).toBeNull();
  });

  test('anonymous render has no nonce input yet submits fine', async ({
    page,
  }) => {
    const marker = uniqueMarker('sec-nonce-anon');

    await page.goto(FORM_URL);

    // FPC design: no nonce for guests, so pages stay cacheable and tokens
    // never go stale. NonceCheck skips anonymous submissions entirely.
    await expect(page.locator(NONCE_INPUT)).toHaveCount(0);

    await submitMarker(page, marker);

    await expectAccepted(page);
    expect(findSubmissionMeta(MARKER_META_KEY, marker)).not.toBeNull();
  });

  /**
   * AJAX (REST) identity — issue #1. Without a wp_rest nonce, core's REST
   * cookie auth demotes a logged-in submission to user 0, so after-process
   * hooks run anonymously and NonceCheck silently skips. The fixture's
   * oriel_after_process_security_min_ajax handler stamps the pipeline-time
   * user ID as _oriel_test_user_id so these specs can observe it.
   */
  test.describe('REST identity (security_min_ajax)', () => {
    const AJAX_FORM_URL = '/security-min-ajax/';
    const AJAX_FORM = 'form#oriel-form-security_min_ajax';
    const AJAX_ORIEL_NONCE_INPUT = `${AJAX_FORM} input[name="_oriel_nonce"]`;
    const AJAX_REST_NONCE_INPUT = `${AJAX_FORM} input[name="_wpnonce"]`;
    const AJAX_CONFIRMATION =
      'Thanks — your security_min_ajax submission was received.';
    const USER_ID_META_KEY = '_oriel_test_user_id';

    async function submitAjaxMarker(page: Page, marker: string) {
      await page.fill(`${AJAX_FORM} input[name="oriel[marker]"]`, marker);

      const [response] = await Promise.all([
        page.waitForResponse(
          (r) =>
            r.url().includes(SUBMIT_ENDPOINT) &&
            r.request().method() === 'POST',
        ),
        page.click(`${AJAX_FORM} [type="submit"]`),
      ]);

      return response;
    }

    test('logged-in AJAX submission carries a wp_rest nonce and keeps the user identity', async ({
      page,
    }) => {
      const marker = uniqueMarker('sec-rest-id');

      await loginAsAdmin(page);
      await page.goto(AJAX_FORM_URL);

      const restNonce = page.locator(AJAX_REST_NONCE_INPUT);
      await expect(restNonce).toBeAttached();
      await expect(restNonce).toHaveAttribute('type', 'hidden');
      await expect(restNonce).not.toHaveValue('');

      const response = await submitAjaxMarker(page, marker);
      expect(response.status()).toBe(200);
      await expect(page.locator('.oriel-form__message--success')).toHaveText(
        AJAX_CONFIRMATION,
      );

      const meta = findSubmissionMeta(MARKER_META_KEY, marker);
      expect(meta).not.toBeNull();
      // Admin (user 1) submitted; after-process hooks must run as them.
      expect(meta![USER_ID_META_KEY]).toBe('1');
    });

    test('anonymous AJAX render has no wp_rest nonce and processes as user 0', async ({
      page,
    }) => {
      const marker = uniqueMarker('sec-rest-anon');

      await page.goto(AJAX_FORM_URL);

      // FPC stance unchanged: anonymous pages stay nonce-free and cacheable.
      await expect(page.locator(AJAX_REST_NONCE_INPUT)).toHaveCount(0);

      const response = await submitAjaxMarker(page, marker);
      expect(response.status()).toBe(200);
      await expect(page.locator('.oriel-form__message--success')).toHaveText(
        AJAX_CONFIRMATION,
      );

      const meta = findSubmissionMeta(MARKER_META_KEY, marker);
      expect(meta).not.toBeNull();
      expect(meta![USER_ID_META_KEY]).toBe('0');
    });

    test('logged-in tampered _oriel_nonce is rejected on the REST path', async ({
      page,
    }) => {
      const marker = uniqueMarker('sec-rest-badnonce');

      await loginAsAdmin(page);
      await page.goto(AJAX_FORM_URL);

      await page.locator(AJAX_ORIEL_NONCE_INPUT).evaluate((el) => {
        (el as HTMLInputElement).value = 'deadbeef00';
      });

      const response = await submitAjaxMarker(page, marker);
      expect(response.status()).toBe(403);
      await expect(page.locator('.oriel-form__message--error')).toHaveText(
        'Submission rejected.',
      );

      expect(findSubmissionMeta(MARKER_META_KEY, marker)).toBeNull();
    });

    test('logged-in tampered _wpnonce is rejected by core with a surfaced error', async ({
      page,
    }) => {
      const marker = uniqueMarker('sec-rest-badrest');

      await loginAsAdmin(page);
      await page.goto(AJAX_FORM_URL);

      await page.locator(AJAX_REST_NONCE_INPUT).evaluate((el) => {
        (el as HTMLInputElement).value = 'deadbeef00';
      });

      const response = await submitAjaxMarker(page, marker);

      // Core's cookie auth rejects the whole request before the pipeline, so
      // this error body is core-owned — ADR-0006's generic-message rule only
      // governs the plugin's own rejections.
      expect(response.status()).toBe(403);
      const body = await response.json();
      expect(body.code).toBe('rest_cookie_invalid_nonce');

      // The bundled JS degrades to its generic failure handling.
      await expect(page.locator('.oriel-form__message--error')).not.toHaveCount(
        0,
      );

      expect(findSubmissionMeta(MARKER_META_KEY, marker)).toBeNull();
    });
  });
});
