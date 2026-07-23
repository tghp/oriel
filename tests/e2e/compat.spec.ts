import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { normalizeFormHtml } from '../support/html';
import { uniqueMarker } from '../support/markers';
import { waitForMessage } from '../support/mailpit';
import { findSubmissionMeta } from '../support/wp';

/**
 * tghpmb compat mode contract: with `compat => 'tghpmb'`, Oriel's rendered
 * HTML must keep matching the selectors that existing Meta-Box-era
 * stylesheets target. Every transformation asserted below comes from
 * src/Compat/TghpmbCompat.php; the fixture form is `compat_tghpmb`
 * (compat_prefix `_tghptest_`, fields: email `email` + text `message`,
 * both required).
 */

const FORM_ID = 'compat_tghpmb';
const PAGE_PATH = '/compat-tghpmb/';
const COMPAT_PREFIX = '_tghptest_';

const COMPAT_CSS_PATH = path.resolve(
  __dirname,
  '../../assets/compat/tghpmb.css',
);

/**
 * Class tokens in assets/compat/tghpmb.css that intentionally do NOT match
 * the steady-state rendered form. This skip-list is part of the contract
 * documentation: anything listed here is state-dependent, JS-added, admin
 * screen chrome, or a field type the fixture form doesn't include. Every
 * other class token in the stylesheet must match at least one element on
 * the rendered page.
 */
const SELECTOR_SKIP_LIST: Record<string, string> = {
  // ── Meta Box clone/sortable feature — Oriel has no cloneable fields, so
  //    this structure can never appear in Oriel output.
  'rwmb-clone': 'Meta Box cloneable-field markup; Oriel does not render clones',
  'rwmb-clone-icon': 'drag handle inside Meta Box clone rows',
  'rwmb-clone-template': 'hidden clone template row, Meta Box JS feature',
  'rwmb-sort-clone': 'sortable clone row, Meta Box JS feature',
  'remove-clone': 'JS-added clone control button',
  'add-clone': 'JS-added clone control button',
  dashicons: 'icon font class used only inside clone controls',
  'rwmb-sortable-placeholder': 'jQuery UI drag state, added while sorting',

  // ── Validation state — added client-side after a failed submit, not
  //    present on a fresh render.
  'rwmb-error': 'jQuery-validation error state class',

  // ── Field types / markup the compat fixture form does not include.
  //    Compat emits rwmb-{type} per configured field, so these would apply
  //    to forms with the matching field types.
  'rwmb-textarea': 'textarea field type not in the compat_tghpmb fixture',
  'rwmb-hidden-wrapper': 'hidden field type not in the compat_tghpmb fixture',
  'rwmb-input-group': 'Meta Box input-group markup, no Oriel equivalent',
  'select2-container': 'select2 JS enhancement of select fields',

  // ── wp-admin-only selectors carried over from the Meta Box stylesheet;
  //    they target edit screens, never frontend form output.
  'edit-post-meta-boxes-area': 'block editor meta box area (admin)',
  'is-side': 'block editor side panel state (admin)',
  inside: 'postbox body (admin)',
  'rwmb-meta-box': 'admin meta box container',
  postbox: 'admin meta box container',
  hndle: 'admin postbox heading',
  handlediv: 'admin postbox toggle',
  'postbox-header': 'admin postbox header',
  postarea: 'admin post editor area',
  'rwmb-seamless': 'admin seamless meta box style',
};

/**
 * Extract unique class tokens from the selector portions of a stylesheet.
 * Walks braces so declaration bodies (e.g. url(...) values) are excluded,
 * and selectors inside @media blocks are still collected.
 */
function extractClassTokens(css: string): string[] {
  const stripped = css.replace(/\/\*[\s\S]*?\*\//g, '');
  const selectors: string[] = [];
  let buf = '';

  for (const ch of stripped) {
    if (ch === '{') {
      selectors.push(buf.trim());
      buf = '';
    } else if (ch === '}') {
      buf = '';
    } else {
      buf += ch;
    }
  }

  const tokens = new Set<string>();

  for (const selector of selectors) {
    if (selector.startsWith('@')) {
      continue;
    }

    for (const match of selector.matchAll(/\.(-?[_a-zA-Z][\w-]*)/g)) {
      tokens.add(match[1]);
    }
  }

  return [...tokens];
}

test.describe('tghpmb compat mode', () => {
  test('rewrites markup to the Meta Box selector contract', async ({
    page,
  }) => {
    await page.goto(PAGE_PATH);

    // Outer wrapper: oriel-form classes replaced wholesale, wrapper id
    // removed (filterWrapperId returns '').
    const wrapper = page.locator('div.tghpform');
    await expect(wrapper).toHaveCount(1);
    await expect(wrapper).toHaveAttribute(
      'class',
      `tghpform tghpform--${FORM_ID}`,
    );
    expect(await wrapper.getAttribute('id')).toBeNull();

    // Form element: id becomes the bare form ID, class becomes Meta Box's.
    const form = wrapper.locator(`form#${FORM_ID}`);
    await expect(form).toHaveCount(1);
    await expect(form).toHaveAttribute('class', 'rwmb-form mbfs-form');
    await expect(form).toHaveAttribute('method', 'post');

    // Fields wrapper: rwmb classes plus the form_{id} id attribute.
    const fieldsWrapper = form.locator(`div#form_${FORM_ID}`);
    await expect(fieldsWrapper).toHaveAttribute(
      'class',
      'rwmb-form-fields form',
    );

    // Per-field wrappers: rwmb-field + type-specific rwmb-{type}-wrapper +
    // field-{id} + required (both fixture fields are required).
    const emailField = fieldsWrapper.locator('.field-email');
    await expect(emailField).toHaveAttribute(
      'class',
      'rwmb-field rwmb-email-wrapper field-email required',
    );

    const messageField = fieldsWrapper.locator('.field-message');
    await expect(messageField).toHaveAttribute(
      'class',
      'rwmb-field rwmb-text-wrapper field-message required',
    );

    const perField = [
      { field: emailField, fieldId: 'email', type: 'email' },
      { field: messageField, fieldId: 'message', type: 'text' },
    ] as const;

    for (const { field, fieldId, type } of perField) {
      // Label wrapper carries the compat-prefixed id that aria-labelledby
      // points at.
      const labelWrapper = field.locator('div.rwmb-label');
      await expect(labelWrapper).toHaveAttribute(
        'id',
        `${COMPAT_PREFIX}${fieldId}-label`,
      );

      // The label itself loses its oriel class (filterLabelClass → '').
      const label = labelWrapper.locator('label');
      await expect(label).toHaveAttribute('class', '');
      await expect(label).toHaveAttribute('for', `oriel_${fieldId}`);

      // Required marker becomes rwmb-required.
      await expect(label.locator('span.rwmb-required')).toHaveText('*');

      // Input wrapper is rwmb-input; input gets the type class and the
      // compat-prefixed aria-labelledby.
      const input = field.locator(
        `div.rwmb-input > input[name="oriel[${fieldId}]"]`,
      );
      await expect(input).toHaveAttribute('class', `rwmb-${type}`);
      await expect(input).toHaveAttribute('type', type);
      await expect(input).toHaveAttribute(
        'aria-labelledby',
        `${COMPAT_PREFIX}${fieldId}-label`,
      );
    }

    // Submit structure: rwmb wrapper > rwmb-input > button carrying the
    // configured submit_class plus Meta Box's rwmb_submit name/value pair.
    const submitWrapper = form.locator('div.rwmb-form-submit');
    await expect(submitWrapper).toHaveAttribute(
      'class',
      'rwmb-field rwmb-button-wrapper rwmb-form-submit',
    );

    const submitButton = submitWrapper.locator(
      'div.rwmb-input > button[type="submit"]',
    );
    await expect(submitButton).toHaveAttribute(
      'class',
      'rwmb-button button button--blue-dark',
    );
    await expect(submitButton).toHaveAttribute('name', 'rwmb_submit');
    await expect(submitButton).toHaveAttribute('value', '1');
  });

  test('leaves Oriel plumbing untouched', async ({ page }) => {
    await page.goto(PAGE_PATH);

    const form = page.locator(`form#${FORM_ID}`);

    // Input name format is still oriel[...] — compat only changes classes
    // and presentation attributes, not what gets POSTed.
    await expect(form.locator('input[name="oriel[email]"]')).toBeVisible();
    await expect(form.locator('input[name="oriel[message]"]')).toBeVisible();
    await expect(form.locator('input[name="oriel_form_id"]')).toHaveValue(
      FORM_ID,
    );

    // Security fields still render: timing token, plus the honeypot —
    // 'comment' is the first HoneypotCheck candidate that doesn't collide
    // with a fixture field ID.
    await expect(form.locator('input[name="_oriel_tk"]')).toBeAttached();

    const honeypot = form.locator('input[name="comment"]');
    await expect(honeypot).toBeAttached();
    await expect(honeypot).toHaveAttribute('tabindex', '-1');

    // Error placeholders keep their Oriel class and data-error-for hook.
    await expect(
      form.locator('.oriel-field__error[data-error-for="oriel_email"]'),
    ).toBeAttached();
    await expect(
      form.locator('.oriel-field__error[data-error-for="oriel_message"]'),
    ).toBeAttached();
  });

  test('normalized form HTML matches snapshot', async ({ page }) => {
    await page.goto(PAGE_PATH);

    const html = await page
      .locator('div.tghpform')
      .evaluate((el) => el.outerHTML);

    expect(normalizeFormHtml(html)).toMatchSnapshot(
      'compat-tghpmb-form.html',
    );
  });

  test('every steady-state stylesheet selector matches the rendered form', async ({
    page,
  }) => {
    await page.goto(PAGE_PATH);

    // The compat stylesheet is enqueued at render time.
    await expect(page.locator('link#oriel-compat-tghpmb-css')).toBeAttached();

    const css = fs.readFileSync(COMPAT_CSS_PATH, 'utf8');
    const tokens = extractClassTokens(css);

    // Parsing sanity check — if extraction breaks, fail loudly instead of
    // vacuously passing on an empty token list.
    expect(tokens).toContain('rwmb-field');

    // Skip-list hygiene: every entry must still exist in the stylesheet,
    // otherwise the skip-list has gone stale.
    for (const skipped of Object.keys(SELECTOR_SKIP_LIST)) {
      expect(tokens, `stale skip-list entry: ${skipped}`).toContain(skipped);
    }

    const steadyState = tokens.filter((t) => !(t in SELECTOR_SKIP_LIST));
    expect(steadyState.length).toBeGreaterThan(0);

    for (const cls of steadyState) {
      const count = await page.locator(`.${cls}`).count();
      expect(
        count,
        `stylesheet class .${cls} matches nothing on the rendered page`,
      ).toBeGreaterThan(0);
    }
  });

  test('compat form still submits end to end', async ({ page }) => {
    const marker = uniqueMarker('compat-tghpmb');
    const email = `${marker}@example.test`;

    await page.goto(PAGE_PATH);
    await page.fill('input[name="oriel[email]"]', email);
    await page.fill('input[name="oriel[message]"]', marker);
    await page.click(`form#${FORM_ID} button[type="submit"]`);

    // Non-AJAX flow: redirect back with the submitted flag + confirmation.
    await expect(page).toHaveURL(/oriel-submitted=compat_tghpmb/);
    await expect(page.locator('.oriel-form__message--success')).toContainText(
      'Thanks — your compat_tghpmb submission was received.',
    );

    // Submission stored with meta intact.
    const meta = findSubmissionMeta('_oriel_message', marker);
    expect(meta).not.toBeNull();
    expect(meta?.['_oriel_email']).toBe(email);
  });

  test('notification email reaches the compat recipient', async ({ page }) => {
    const marker = uniqueMarker('compat-tghpmb-mail');
    const email = `${marker}@example.test`;

    await page.goto(PAGE_PATH);
    await page.fill('input[name="oriel[email]"]', email);
    await page.fill('input[name="oriel[message]"]', marker);
    await page.click(`form#${FORM_ID} button[type="submit"]`);
    await expect(page).toHaveURL(/oriel-submitted=compat_tghpmb/);

    const message = await waitForMessage(marker);
    expect(message.To[0].Address).toBe('compat_tghpmb@example.test');
  });
});
