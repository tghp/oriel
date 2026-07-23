import { test, expect, type Page } from '@playwright/test';
import { BASE_URL, CACHED_URL } from '../support/urls';
import { uniqueMarker } from '../support/markers';
import { findSubmissionMeta } from '../support/wp';

/**
 * Full-page-caching suite: proves the plugin's security design survives a
 * page being served from cache.
 *
 * Two mechanisms:
 *  - Real cache (CACHED_URL, port 8789): end-to-end submit from a page nginx
 *    genuinely served from its FastCGI cache.
 *  - Simulated staleness (uncached port): route-intercept the document and
 *    rewrite the timing token / replay captured HTML, then drive the form
 *    normally — lets us fast-forward "cached hours ago" without waiting.
 */

const KITCHEN_SINK_CONFIRMATION =
  'Thanks — your kitchen_sink submission was received.';

/** base64(unix seconds) — the _oriel_tk format FormRenderer emits. */
function tokenAgedBy(secondsAgo: number): string {
  const ts = Math.floor(Date.now() / 1000) - secondsAgo;
  return Buffer.from(String(ts)).toString('base64');
}

/**
 * FormRenderer emits the honeypot as a text input inside an off-screen
 * aria-hidden div, concatenated with no whitespace.
 */
const HONEYPOT_INPUT_RE = /aria-hidden="true"><input type="text" name="([^"]+)"/;

async function fillAndSubmitKitchenSink(
  page: Page,
  marker: string,
  email: string,
): Promise<void> {
  await page.fill('input[name="oriel[name]"]', marker);
  await page.fill('input[name="oriel[email]"]', email);
  await page.click('form#oriel-form-kitchen_sink [type="submit"]');
}

/**
 * Intercept the initial GET for /kitchen-sink/ and rewrite the _oriel_tk
 * value in the real response, simulating a page cached `token`'s age ago.
 * The form POST (same URL) and the post-redirect GET (has a query string,
 * which the glob does not match) pass through untouched.
 */
async function serveWithForgedToken(page: Page, token: string): Promise<void> {
  await page.route('**/kitchen-sink/', async (route) => {
    if (route.request().method() !== 'GET') {
      await route.fallback();
      return;
    }

    const response = await route.fetch();
    const html = (await response.text()).replace(
      /(name="_oriel_tk"[^>]*value=")[^"]*(")/,
      `$1${token}$2`,
    );
    await route.fulfill({ response, body: html });
  });

  await page.goto('/kitchen-sink/');
  // Sanity: the DOM really carries the forged token.
  await expect(page.locator('input[name="_oriel_tk"]')).toHaveValue(token);
}

test.describe('full-page caching — real nginx cache', () => {
  test('submits end-to-end from a genuinely cached page', async ({ page }) => {
    const marker = uniqueMarker('fpc-cached-submit');
    const url = `${CACHED_URL}/kitchen-sink/`;

    // Warm until nginx serves the document from cache. Sibling suites also
    // hit this URL, so tolerate MISS/EXPIRED rounds before the HIT.
    let cacheStatus = '';
    for (let attempt = 0; attempt < 10 && cacheStatus !== 'HIT'; attempt++) {
      const response = await page.goto(url);
      cacheStatus = response?.headers()['x-cache-status'] ?? '';
    }
    expect(cacheStatus).toBe('HIT');

    // The POST bypasses the cache (nginx skips POSTs) and the redirect back
    // carries ?oriel-submitted, which also bypasses (query string rule).
    await fillAndSubmitKitchenSink(page, marker, 'fpc-cached@example.test');

    await expect(page).toHaveURL(/oriel-submitted=kitchen_sink/);
    await expect(page.locator('.oriel-form__message--success')).toContainText(
      KITCHEN_SINK_CONFIRMATION,
    );

    const meta = findSubmissionMeta('_oriel_name', marker);
    expect(meta).not.toBeNull();
    expect(meta?.['_oriel_email']).toBe('fpc-cached@example.test');
  });

  test('cache-served HTML still contains all security inputs', async ({
    request,
  }) => {
    const url = `${CACHED_URL}/kitchen-sink/`;

    let cacheStatus = '';
    let body = '';
    for (let attempt = 0; attempt < 10 && cacheStatus !== 'HIT'; attempt++) {
      const response = await request.get(url);
      cacheStatus = response.headers()['x-cache-status'] ?? '';
      body = await response.text();
    }
    expect(cacheStatus).toBe('HIT');

    // Timing token present with a decodable base64 unix timestamp — the
    // cached copy is submittable within the max_time window.
    const tokenMatch = body.match(/name="_oriel_tk"[^>]*value="([^"]*)"/);
    expect(tokenMatch).not.toBeNull();
    const renderTime = Number(
      Buffer.from(tokenMatch![1], 'base64').toString(),
    );
    expect(renderTime).toBeGreaterThan(0);
    expect(renderTime).toBeLessThanOrEqual(Math.ceil(Date.now() / 1000));

    // Honeypot input present.
    expect(body).toMatch(HONEYPOT_INPUT_RE);

    // No nonce for anonymous visitors — by design, so the cached copy never
    // carries a token tied to someone else's session.
    expect(body).not.toContain('name="_oriel_nonce"');
  });
});

test.describe('full-page caching — simulated stale pages', () => {
  test('page cached 2 hours ago still submits (within max_time)', async ({
    page,
  }) => {
    const marker = uniqueMarker('fpc-stale-2h');

    await serveWithForgedToken(page, tokenAgedBy(7200));
    await fillAndSubmitKitchenSink(page, marker, 'fpc-stale-2h@example.test');

    await expect(page).toHaveURL(/oriel-submitted=kitchen_sink/);
    await expect(page.locator('.oriel-form__message--success')).toContainText(
      KITCHEN_SINK_CONFIRMATION,
    );
    expect(findSubmissionMeta('_oriel_name', marker)).not.toBeNull();
  });

  test('page cached 25 hours ago is rejected (beyond max_time)', async ({
    page,
  }) => {
    const marker = uniqueMarker('fpc-stale-25h');

    // 90000s > the 86400s max_time default in TimingCheck. Documents the
    // design limit: pages cached beyond max_time reject legitimate users.
    await serveWithForgedToken(page, tokenAgedBy(90_000));
    await fillAndSubmitKitchenSink(page, marker, 'fpc-stale-25h@example.test');

    // SecurityStep stores 'Submission rejected.' under the non-field key
    // 'security', so the visible surface is the generic error banner on the
    // ?oriel-errors redirect back.
    await expect(page).toHaveURL(/oriel-errors=kitchen_sink/);
    await expect(page.locator('.oriel-form__message--error')).toContainText(
      'There were errors with your submission',
    );

    // Security halts the pipeline before CreatePostStep — nothing stored.
    expect(findSubmissionMeta('_oriel_name', marker)).toBeNull();
  });

  test('HTML captured in one anonymous session submits from another', async ({
    browser,
  }) => {
    const marker = uniqueMarker('fpc-replay');

    // Capture the page as one anonymous visitor would receive it.
    const contextA = await browser.newContext();
    const pageA = await contextA.newPage();
    const responseA = await pageA.goto(`${BASE_URL}/kitchen-sink/`);
    expect(responseA?.status()).toBe(200);
    const capturedHtml = await responseA!.text();
    await contextA.close();

    // Serve that exact HTML to a completely fresh anonymous context — the
    // cache-shared-across-visitors scenario. Anonymous submissions never
    // depend on a nonce or session, so this must succeed.
    const contextB = await browser.newContext();
    const pageB = await contextB.newPage();
    await pageB.route('**/kitchen-sink/', async (route) => {
      if (route.request().method() !== 'GET') {
        await route.fallback();
        return;
      }
      await route.fulfill({
        status: 200,
        contentType: 'text/html',
        body: capturedHtml,
      });
    });

    await pageB.goto(`${BASE_URL}/kitchen-sink/`);
    await fillAndSubmitKitchenSink(pageB, marker, 'fpc-replay@example.test');

    await expect(pageB).toHaveURL(/oriel-submitted=kitchen_sink/);
    await expect(
      pageB.locator('.oriel-form__message--success'),
    ).toContainText(KITCHEN_SINK_CONFIRMATION);
    expect(findSubmissionMeta('_oriel_name', marker)).not.toBeNull();

    await contextB.close();
  });

  test('honeypot field name is identical across renders', async ({
    request,
  }) => {
    // HoneypotCheck::resolveFieldName is deterministic: first CANDIDATES
    // entry not colliding with a form field ID. A cached page therefore
    // always agrees with the processor. Verify across two fresh renders.
    const first = await request.get(
      `${BASE_URL}/kitchen-sink/?cb=${uniqueMarker('fpc-hp-a')}`,
    );
    const second = await request.get(
      `${BASE_URL}/kitchen-sink/?cb=${uniqueMarker('fpc-hp-b')}`,
    );

    const firstName = (await first.text()).match(HONEYPOT_INPUT_RE)?.[1];
    const secondName = (await second.text()).match(HONEYPOT_INPUT_RE)?.[1];

    expect(firstName).toBeTruthy();
    expect(secondName).toBe(firstName);
    // For kitchen_sink (no 'comment' field ID) the first candidate wins.
    expect(firstName).toBe('comment');
  });
});
