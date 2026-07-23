import { test, expect, type Page, type Locator } from '@playwright/test';
import { uniqueMarker } from '../support/markers';
import { findSubmissionMeta } from '../support/wp';
import { waitForMessage } from '../support/mailpit';

/**
 * Field-type coverage for the two kitchen-sink fixtures (all 7 non-captcha
 * types): render wiring, validation (non-AJAX redirect-back and AJAX inline),
 * and happy paths asserting stored meta formats and notification emails.
 *
 * Verified contracts (from plugin source):
 * - Input id `oriel_{fieldId}`, name `oriel[{fieldId}]` (AbstractField).
 * - Error placeholder `<div class="oriel-field__error" data-error-for="oriel_{fieldId}">`
 *   inside the field wrapper; wrapper class `oriel-field--oriel_{fieldId}`
 *   (checkbox wrapper uses the raw id: `oriel-field--agree`).
 * - Required label markup: `<span class="oriel-field__required">*</span>`.
 * - Stored meta `_oriel_{fieldId}`; checkbox sanitizes to int 1/0 which WP
 *   stores as the strings "1"/"0"; select/radio store the raw option value.
 * - Email body (EmailNotifier): `<p><strong>{label}</strong><br>{value}</p>`
 *   per email=>true field; checkbox renders Yes/No; select/radio render
 *   `{value} ({label})`.
 * - REST validation failure: 422 `{ success: false, errors: {fieldId: msg} }`
 *   (RestResponseStep); oriel.js writes each message into the matching
 *   [data-error-for] div and flags the wrapper with .oriel-field--has-error.
 */

const SUBMIT_ENDPOINT = '/oriel/v1/submit';

function form(page: Page, formId: string): Locator {
  return page.locator(`form#oriel-form-${formId}`);
}

type KitchenSinkData = {
  name: string;
  email: string;
  message: string;
  agree: boolean;
  topic: string;
  contactMethod: 'email' | 'phone';
};

async function fillKitchenSink(f: Locator, data: KitchenSinkData) {
  await f.locator('#oriel_name').fill(data.name);
  await f.locator('#oriel_email').fill(data.email);
  await f.locator('#oriel_message').fill(data.message);

  if (data.agree) {
    await f.locator('#oriel_agree').check();
  }

  await f.locator('#oriel_topic').selectOption(data.topic);
  await f.locator(`#oriel_contact_method_${data.contactMethod}`).check();
}

/** Expected post meta for a kitchen-sink submission, in stored formats. */
function expectedMeta(data: KitchenSinkData): Record<string, string> {
  return {
    _oriel_name: data.name,
    _oriel_email: data.email,
    _oriel_message: data.message,
    // Checkbox sanitizes to int 1/0; WP stores meta as strings "1"/"0".
    _oriel_agree: data.agree ? '1' : '0',
    // Select and radio store the raw option value, not the label.
    _oriel_topic: data.topic,
    _oriel_contact_method: data.contactMethod,
    // Hidden field keeps its std default.
    _oriel_source: 'fixture-default',
  };
}

test.describe('field type rendering', () => {
  test('all 7 field types render correctly wired on kitchen sink', async ({
    page,
  }) => {
    await page.goto('/kitchen-sink/');
    const f = form(page, 'kitchen_sink');
    await expect(f).toBeVisible();

    // -- text (required) --
    const nameInput = f.locator('#oriel_name');
    await expect(nameInput).toHaveAttribute('type', 'text');
    await expect(nameInput).toHaveAttribute('name', 'oriel[name]');
    await expect(nameInput).toHaveAttribute('required', 'required');
    await expect(f.locator('label[for="oriel_name"]')).toContainText('Name');
    await expect(
      f.locator('label[for="oriel_name"] .oriel-field__required'),
    ).toHaveText('*');

    // -- email (required) --
    const emailInput = f.locator('#oriel_email');
    await expect(emailInput).toHaveAttribute('type', 'email');
    await expect(emailInput).toHaveAttribute('name', 'oriel[email]');
    await expect(emailInput).toHaveAttribute('required', 'required');
    await expect(f.locator('label[for="oriel_email"]')).toContainText('Email');
    await expect(
      f.locator('label[for="oriel_email"] .oriel-field__required'),
    ).toHaveText('*');

    // -- textarea (optional: no required attr, no required symbol) --
    const messageInput = f.locator('textarea#oriel_message');
    await expect(messageInput).toHaveAttribute('name', 'oriel[message]');
    await expect(messageInput).not.toHaveAttribute('required');
    await expect(f.locator('label[for="oriel_message"]')).toHaveText('Message');
    await expect(
      f.locator('label[for="oriel_message"] .oriel-field__required'),
    ).toHaveCount(0);

    // -- checkbox: hidden "0" fallback + checkbox "1", desc text in label --
    const agreeWrapper = f.locator('.oriel-field--checkbox.oriel-field--agree');
    await expect(agreeWrapper).toHaveCount(1);
    await expect(
      agreeWrapper.locator('input[type="hidden"][name="oriel[agree]"]'),
    ).toHaveValue('0');
    const agreeInput = agreeWrapper.locator('input#oriel_agree');
    await expect(agreeInput).toHaveAttribute('type', 'checkbox');
    await expect(agreeInput).toHaveAttribute('name', 'oriel[agree]');
    await expect(agreeInput).toHaveValue('1');
    await expect(agreeInput).not.toBeChecked();
    await expect(agreeWrapper.locator('label[for="oriel_agree"]')).toHaveText(
      'I agree to the terms',
    );

    // -- select: all three options, correct values and labels --
    const select = f.locator('select#oriel_topic');
    await expect(select).toHaveAttribute('name', 'oriel[topic]');
    await expect(f.locator('label[for="oriel_topic"]')).toHaveText('Topic');
    const options = select.locator('option');
    await expect(options).toHaveCount(3);
    const expectedOptions: Array<[string, string]> = [
      ['general', 'General'],
      ['support', 'Support'],
      ['sales', 'Sales'],
    ];
    for (const [i, [value, label]] of expectedOptions.entries()) {
      await expect(options.nth(i)).toHaveAttribute('value', value);
      await expect(options.nth(i)).toHaveText(label);
    }

    // -- radio: both options, each option label wired to its input --
    await expect(f.locator('label[for="oriel_contact_method"]')).toHaveText(
      'Preferred contact method',
    );
    const radios = f.locator('input[type="radio"][name="oriel[contact_method]"]');
    await expect(radios).toHaveCount(2);
    const expectedRadios: Array<[string, string, string]> = [
      ['oriel_contact_method_email', 'email', 'Email'],
      ['oriel_contact_method_phone', 'phone', 'Phone'],
    ];
    for (const [id, value, label] of expectedRadios) {
      const radio = f.locator(`input#${id}`);
      await expect(radio).toHaveValue(value);
      await expect(radio).not.toBeChecked();
      await expect(f.locator(`label[for="${id}"]`)).toHaveText(label);
    }

    // -- hidden: std default value, no wrapper/label/error rendered --
    const hidden = f.locator('input#oriel_source');
    await expect(hidden).toHaveAttribute('type', 'hidden');
    await expect(hidden).toHaveAttribute('name', 'oriel[source]');
    await expect(hidden).toHaveValue('fixture-default');
    await expect(f.locator('[data-error-for="oriel_source"]')).toHaveCount(0);

    // -- error placeholders sit inside their own field wrapper, empty --
    for (const fieldId of ['name', 'email', 'message', 'topic', 'contact_method']) {
      const errorDiv = f.locator(
        `.oriel-field--oriel_${fieldId} [data-error-for="oriel_${fieldId}"]`,
      );
      await expect(errorDiv).toHaveCount(1);
      await expect(errorDiv).toHaveText('');
    }
    await expect(
      f.locator('.oriel-field--agree [data-error-for="oriel_agree"]'),
    ).toHaveText('');
  });
});

test.describe('non-ajax validation', () => {
  test('empty required fields redirect back with per-field inline errors', async ({
    page,
  }) => {
    await page.goto('/kitchen-sink/');
    await form(page, 'kitchen_sink').locator('[type="submit"]').click();

    await expect(page).toHaveURL(/oriel-errors=kitchen_sink/);
    await expect(page.locator('.oriel-form__message--error')).toHaveText(
      'There were errors with your submission. Please correct them and try again.',
    );

    const f = form(page, 'kitchen_sink');
    // Errors render inside the matching field's wrapper.
    await expect(
      f.locator('.oriel-field--oriel_name [data-error-for="oriel_name"]'),
    ).toHaveText('Name is required.');
    await expect(
      f.locator('.oriel-field--oriel_email [data-error-for="oriel_email"]'),
    ).toHaveText('Email is required.');
    // Optional fields stay error-free.
    await expect(f.locator('[data-error-for="oriel_message"]')).toHaveText('');
    await expect(f.locator('[data-error-for="oriel_agree"]')).toHaveText('');
  });

  test('invalid email format redirects back with email format error', async ({
    page,
  }) => {
    const marker = uniqueMarker('ks-nonajax-badmail');

    await page.goto('/kitchen-sink/');
    const f = form(page, 'kitchen_sink');
    await f.locator('#oriel_name').fill(marker);
    // Survives sanitize_email (unlike e.g. "not-an-email", which sanitizes to
    // "" and reads as a missing required value) but fails FILTER_VALIDATE_EMAIL.
    await f.locator('#oriel_email').fill('foo..bar@example.com');
    await f.locator('[type="submit"]').click();

    await expect(page).toHaveURL(/oriel-errors=kitchen_sink/);
    await expect(
      f.locator('.oriel-field--oriel_email [data-error-for="oriel_email"]'),
    ).toHaveText('Email must be a valid email address.');
    // The valid name has no error and repopulates.
    await expect(f.locator('[data-error-for="oriel_name"]')).toHaveText('');
    await expect(f.locator('#oriel_name')).toHaveValue(marker);
    await expect(f.locator('#oriel_email')).toHaveValue('foo..bar@example.com');
  });
});

test.describe('ajax validation', () => {
  test('empty required fields return 422 and render inline errors without navigation', async ({
    page,
  }) => {
    await page.goto('/kitchen-sink-ajax/');
    const f = form(page, 'kitchen_sink_ajax');

    const [response] = await Promise.all([
      page.waitForResponse(
        (r) =>
          r.url().includes(SUBMIT_ENDPOINT) && r.request().method() === 'POST',
      ),
      f.locator('[type="submit"]').click(),
    ]);

    // REST contract: 422 with per-field error map.
    expect(response.status()).toBe(422);
    const body = await response.json();
    expect(body).toEqual({
      success: false,
      errors: {
        name: 'Name is required.',
        email: 'Email is required.',
      },
    });

    // No navigation — still on the page with no oriel query params.
    await expect(page).toHaveURL(/\/kitchen-sink-ajax\/$/);

    // Form-level message and per-field inline errors.
    await expect(page.locator('.oriel-form__message--error')).toHaveText(
      'There were errors with your submission. Please correct them and try again.',
    );
    await expect(
      f.locator('.oriel-field--oriel_name [data-error-for="oriel_name"]'),
    ).toHaveText('Name is required.');
    await expect(
      f.locator('.oriel-field--oriel_email [data-error-for="oriel_email"]'),
    ).toHaveText('Email is required.');
    await expect(f.locator('.oriel-field--oriel_name')).toHaveClass(
      /oriel-field--has-error/,
    );
    await expect(f.locator('[data-error-for="oriel_message"]')).toHaveText('');
  });

  test('invalid email format returns 422 with format error inline', async ({
    page,
  }) => {
    await page.goto('/kitchen-sink-ajax/');
    const f = form(page, 'kitchen_sink_ajax');
    await f.locator('#oriel_name').fill('Ajax bad email');
    await f.locator('#oriel_email').fill('foo..bar@example.com');

    const [response] = await Promise.all([
      page.waitForResponse(
        (r) =>
          r.url().includes(SUBMIT_ENDPOINT) && r.request().method() === 'POST',
      ),
      f.locator('[type="submit"]').click(),
    ]);

    expect(response.status()).toBe(422);
    const body = await response.json();
    expect(body).toEqual({
      success: false,
      errors: { email: 'Email must be a valid email address.' },
    });

    await expect(page).toHaveURL(/\/kitchen-sink-ajax\/$/);
    await expect(
      f.locator('.oriel-field--oriel_email [data-error-for="oriel_email"]'),
    ).toHaveText('Email must be a valid email address.');
    await expect(f.locator('[data-error-for="oriel_name"]')).toHaveText('');
    // Values are untouched — no reset on validation failure.
    await expect(f.locator('#oriel_email')).toHaveValue('foo..bar@example.com');
  });
});

test.describe('non-ajax happy path', () => {
  test('submits all field types and stores meta in correct formats', async ({
    page,
  }) => {
    const marker = uniqueMarker('ks-nonajax-happy');
    const data: KitchenSinkData = {
      name: marker,
      email: `${marker}@example.com`,
      message: `Message body for ${marker}`,
      agree: true,
      topic: 'support',
      contactMethod: 'phone',
    };

    await page.goto('/kitchen-sink/');
    const f = form(page, 'kitchen_sink');
    await fillKitchenSink(f, data);
    await f.locator('[type="submit"]').click();

    await expect(page).toHaveURL(/oriel-submitted=kitchen_sink/);
    await expect(page.locator('.oriel-form__message--success')).toHaveText(
      'Thanks — your kitchen_sink submission was received.',
    );

    const meta = findSubmissionMeta('_oriel_name', marker);
    expect(meta).not.toBeNull();
    expect(meta).toMatchObject(expectedMeta(data));
  });

  test('sends notification email with correct recipient, subject and field formats', async ({
    page,
  }) => {
    const marker = uniqueMarker('ks-nonajax-mail');
    const data: KitchenSinkData = {
      name: marker,
      email: `${marker}@example.com`,
      message: `Message body for ${marker}`,
      agree: true,
      topic: 'support',
      contactMethod: 'phone',
    };

    await page.goto('/kitchen-sink/');
    const f = form(page, 'kitchen_sink');
    await fillKitchenSink(f, data);
    await f.locator('[type="submit"]').click();
    await expect(page).toHaveURL(/oriel-submitted=kitchen_sink/);

    // Notification email: recipient, subject, and email=>true field values in
    // EmailNotifier's format.
    const mail = await waitForMessage(marker);
    expect(mail.To[0].Address).toBe('kitchen_sink@example.test');
    expect(mail.Subject).toBe('Oriel Test: kitchen_sink');
    expect(mail.HTML).toContain(`<strong>Name</strong><br>${marker}`);
    expect(mail.HTML).toContain(`<strong>Email</strong><br>${data.email}`);
    expect(mail.HTML).toContain(`<strong>Message</strong><br>${data.message}`);
    expect(mail.HTML).toContain('<strong>Agree</strong><br>Yes');
    expect(mail.HTML).toContain('<strong>Topic</strong><br>support (Support)');
    expect(mail.HTML).toContain(
      '<strong>Preferred contact method</strong><br>phone (Phone)',
    );
    // Hidden `source` has no email=>true — must not leak into the email.
    expect(mail.HTML).not.toContain('fixture-default');
  });
});

test.describe('ajax happy path', () => {
  test('shows inline confirmation without navigation, resets form, stores meta', async ({
    page,
  }) => {
    const marker = uniqueMarker('ks-ajax-happy');
    const data: KitchenSinkData = {
      name: marker,
      email: `${marker}@example.com`,
      message: `Message body for ${marker}`,
      // Unchecked — asserts the "0" stored format.
      agree: false,
      topic: 'sales',
      contactMethod: 'email',
    };

    await page.goto('/kitchen-sink-ajax/');
    const f = form(page, 'kitchen_sink_ajax');
    await fillKitchenSink(f, data);

    const [response] = await Promise.all([
      page.waitForResponse(
        (r) =>
          r.url().includes(SUBMIT_ENDPOINT) && r.request().method() === 'POST',
      ),
      f.locator('[type="submit"]').click(),
    ]);

    expect(response.status()).toBe(200);
    expect(await response.json()).toEqual({
      success: true,
      message: 'Thanks — your kitchen_sink_ajax submission was received.',
    });

    // Inline confirmation, no navigation.
    await expect(page).toHaveURL(/\/kitchen-sink-ajax\/$/);
    await expect(page.locator('.oriel-form__message--success')).toHaveText(
      'Thanks — your kitchen_sink_ajax submission was received.',
    );

    // Form reset after success — back to DOM defaults.
    await expect(f.locator('#oriel_name')).toHaveValue('');
    await expect(f.locator('#oriel_email')).toHaveValue('');
    await expect(f.locator('#oriel_message')).toHaveValue('');
    await expect(f.locator('#oriel_agree')).not.toBeChecked();
    await expect(f.locator('#oriel_topic')).toHaveValue('general');
    await expect(f.locator('#oriel_contact_method_email')).not.toBeChecked();
    await expect(f.locator('#oriel_contact_method_phone')).not.toBeChecked();
    await expect(f.locator('#oriel_source')).toHaveValue('fixture-default');

    const meta = findSubmissionMeta('_oriel_name', marker);
    expect(meta).not.toBeNull();
    expect(meta).toMatchObject(expectedMeta(data));
  });

  test('sends notification email with correct recipient, subject and field formats', async ({
    page,
  }) => {
    const marker = uniqueMarker('ks-ajax-mail');
    const data: KitchenSinkData = {
      name: marker,
      email: `${marker}@example.com`,
      message: `Message body for ${marker}`,
      agree: false,
      topic: 'sales',
      contactMethod: 'email',
    };

    await page.goto('/kitchen-sink-ajax/');
    const f = form(page, 'kitchen_sink_ajax');
    await fillKitchenSink(f, data);

    const [response] = await Promise.all([
      page.waitForResponse(
        (r) =>
          r.url().includes(SUBMIT_ENDPOINT) && r.request().method() === 'POST',
      ),
      f.locator('[type="submit"]').click(),
    ]);
    expect(response.status()).toBe(200);

    const mail = await waitForMessage(marker);
    expect(mail.To[0].Address).toBe('kitchen_sink_ajax@example.test');
    expect(mail.Subject).toBe('Oriel Test: kitchen_sink_ajax');
    expect(mail.HTML).toContain(`<strong>Name</strong><br>${marker}`);
    expect(mail.HTML).toContain('<strong>Agree</strong><br>No');
    expect(mail.HTML).toContain('<strong>Topic</strong><br>sales (Sales)');
    expect(mail.HTML).toContain(
      '<strong>Preferred contact method</strong><br>email (Email)',
    );
    expect(mail.HTML).not.toContain('fixture-default');
  });
});
