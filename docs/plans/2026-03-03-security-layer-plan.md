# Security Layer Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace nonce-only CSRF with a multi-layered security system (honeypot, rate limiting, timing check, conditional nonce) that works under full-page caching.

**Architecture:** New `SecurityStep` as first pipeline step in `FormProcessor`, orchestrating four checker classes via `SecurityCheckInterface`. Nonce field only rendered/verified for logged-in users. Honeypot field name dynamically selected to avoid collisions with form field IDs.

**Tech Stack:** PHP 7.4+, WordPress APIs (transients, nonces, filters)

---

### Task 1: SecurityCheckInterface

**Files:**
- Create: `src/Security/SecurityCheckInterface.php`

**Step 1: Create the interface**

```php
<?php

namespace Oriel\Security;

use Oriel\Processing\ProcessingContext;

interface SecurityCheckInterface
{
    /**
     * Run the security check.
     *
     * @return string|null Null on pass, error message on fail.
     */
    public function check(ProcessingContext $context): ?string;
}
```

**Step 2: Dump autoload**

Run: `cd /Users/josh/Sites/colossus/src/plugins/oriel && composer dump-autoload`

**Step 3: Commit**

```
feat(oriel): add SecurityCheckInterface
```

---

### Task 2: HoneypotCheck

**Files:**
- Create: `src/Security/HoneypotCheck.php`

**Step 1: Implement HoneypotCheck**

```php
<?php

namespace Oriel\Security;

use Oriel\Processing\ProcessingContext;

class HoneypotCheck implements SecurityCheckInterface
{
    /**
     * Candidate honeypot field names in priority order.
     * The first name not colliding with an existing form field ID is used.
     */
    private const CANDIDATES = [
        'address_line_1',
        'address_line_2',
        'phone_number',
        'company_email',
        'instagram_handle',
        'comment',
        'remark',
        'feedback',
        'notes',
        'message',
        'description',
        'additional_info',
        'extra_details',
        'user_bio',
        'profile_summary',
        'personal_statement',
        'about_me',
        'contact_info',
        'social_media_link',
        'website_url',
        'linkedin_profile',
        'twitter_handle',
    ];

    public function check(ProcessingContext $context): ?string
    {
        $fieldName = self::resolveFieldName($context->formConfig);

        if ($fieldName === null) {
            return null;
        }

        $value = $_POST[$fieldName] ?? null;

        if ($value !== null && $value !== '') {
            return 'Submission rejected.';
        }

        return null;
    }

    /**
     * Resolve the honeypot field name for a given form config.
     *
     * Picks the first candidate that doesn't collide with an existing field ID.
     * Returns null if all candidates are taken (extremely unlikely).
     *
     * Static so FormRenderer can call it at render time too.
     */
    public static function resolveFieldName(array $formConfig): ?string
    {
        $candidates = apply_filters('oriel_security_honeypot_candidates', self::CANDIDATES);

        $existingIds = array_column($formConfig['fields'] ?? [], 'id');

        foreach ($candidates as $candidate) {
            if (!in_array($candidate, $existingIds, true)) {
                return $candidate;
            }
        }

        return null;
    }
}
```

**Step 2: Commit**

```
feat(oriel): add HoneypotCheck security checker
```

---

### Task 3: RateLimitCheck

**Files:**
- Create: `src/Security/RateLimitCheck.php`

**Step 1: Implement RateLimitCheck**

```php
<?php

namespace Oriel\Security;

use Oriel\Processing\ProcessingContext;

class RateLimitCheck implements SecurityCheckInterface
{
    public function check(ProcessingContext $context): ?string
    {
        $maxAttempts = (int) apply_filters('oriel_security_rate_limit', 5);
        $window = (int) apply_filters('oriel_security_rate_window', 600);

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        if (empty($ip)) {
            return null;
        }

        $key = 'oriel_rl_' . md5($ip);
        $count = (int) get_transient($key);

        if ($count >= $maxAttempts) {
            return 'Submission rejected.';
        }

        set_transient($key, $count + 1, $window);

        return null;
    }
}
```

**Step 2: Commit**

```
feat(oriel): add RateLimitCheck security checker
```

---

### Task 4: TimingCheck

**Files:**
- Create: `src/Security/TimingCheck.php`

**Step 1: Implement TimingCheck**

The timing token field name is `_oriel_tk`. Value is `base64_encode(time())` set at render time.

```php
<?php

namespace Oriel\Security;

use Oriel\Processing\ProcessingContext;

class TimingCheck implements SecurityCheckInterface
{
    public const FIELD_NAME = '_oriel_tk';

    public function check(ProcessingContext $context): ?string
    {
        $minTime = (int) apply_filters('oriel_security_min_time', 3);

        $token = $_POST[self::FIELD_NAME] ?? '';

        if (empty($token)) {
            return 'Submission rejected.';
        }

        $decoded = base64_decode($token, true);

        if ($decoded === false) {
            return 'Submission rejected.';
        }

        $renderTime = (int) $decoded;

        if ($renderTime <= 0) {
            return 'Submission rejected.';
        }

        $elapsed = time() - $renderTime;

        if ($elapsed < $minTime) {
            return 'Submission rejected.';
        }

        return null;
    }
}
```

**Step 2: Commit**

```
feat(oriel): add TimingCheck security checker
```

---

### Task 5: NonceCheck

**Files:**
- Create: `src/Security/NonceCheck.php`

**Step 1: Implement NonceCheck**

Only runs when user is logged in. Checks `_oriel_nonce` against `oriel_submit_{formId}`.

```php
<?php

namespace Oriel\Security;

use Oriel\Processing\ProcessingContext;

class NonceCheck implements SecurityCheckInterface
{
    public function check(ProcessingContext $context): ?string
    {
        if (!is_user_logged_in()) {
            return null;
        }

        $nonce = $_POST['_oriel_nonce'] ?? '';

        if (!wp_verify_nonce($nonce, 'oriel_submit_' . $context->formId)) {
            return 'Submission rejected.';
        }

        return null;
    }
}
```

**Step 2: Commit**

```
feat(oriel): add NonceCheck security checker (logged-in only)
```

---

### Task 6: SecurityStep

**Files:**
- Create: `src/Processing/SecurityStep.php`

**Step 1: Implement SecurityStep**

Runs all checks in order. Halts on first failure.

```php
<?php

namespace Oriel\Processing;

use Oriel\Security\HoneypotCheck;
use Oriel\Security\RateLimitCheck;
use Oriel\Security\TimingCheck;
use Oriel\Security\NonceCheck;

class SecurityStep implements StepInterface
{
    public function process(ProcessingContext $context): ProcessingContext
    {
        $checks = [
            new HoneypotCheck(),
            new RateLimitCheck(),
            new TimingCheck(),
            new NonceCheck(),
        ];

        foreach ($checks as $check) {
            $error = $check->check($context);

            if ($error !== null) {
                $message = apply_filters('oriel_security_error_message', $error);
                $context->errors['security'] = $message;
                $context->shouldHalt = true;

                return $context;
            }
        }

        return $context;
    }
}
```

**Step 2: Commit**

```
feat(oriel): add SecurityStep pipeline step
```

---

### Task 7: Wire SecurityStep into FormProcessor

**Files:**
- Modify: `src/FormProcessor.php:56-63`

**Step 1: Add SecurityStep as first step**

Add `use Oriel\Processing\SecurityStep;` to imports. Insert `new SecurityStep()` as first element of `$steps` array.

The `$steps` array becomes:

```php
$steps = [
    new SecurityStep(),
    new ValidateStep(),
    new CreatePostStep(),
    new HooksStep(),
    new EmailStep(),
    new CleanupStep(),
    new RedirectStep(),
];
```

**Step 2: Commit**

```
feat(oriel): wire SecurityStep as first pipeline step
```

---

### Task 8: Remove nonce checks from Plugin.php

**Files:**
- Modify: `src/Plugin.php:70-95` (handleSubmission)
- Modify: `src/Plugin.php:103-143` (handleRestSubmission)

**Step 1: Update handleSubmission**

Remove the nonce verification block (lines 78-83). The method becomes:

```php
public function handleSubmission(): void
{
    if (empty($_POST['oriel_form_id'])) {
        return;
    }

    $formId = sanitize_text_field($_POST['oriel_form_id']);

    if (!$this->registry->get($formId)) {
        return;
    }

    $data = isset($_POST['oriel']) && is_array($_POST['oriel'])
        ? $_POST['oriel']
        : [];

    $processor = new FormProcessor($this->registry, $formId, $data);
    $processor->run();
}
```

**Step 2: Update handleRestSubmission**

Remove the nonce verification block (lines 111-115). The method becomes:

```php
public function handleRestSubmission(\WP_REST_Request $request): \WP_REST_Response
{
    $formId = sanitize_text_field($request->get_param('oriel_form_id') ?? '');

    if (empty($formId)) {
        return new \WP_REST_Response(['success' => false, 'message' => 'Missing form ID.'], 400);
    }

    if (!$this->registry->get($formId)) {
        return new \WP_REST_Response(['success' => false, 'message' => 'Form not found.'], 404);
    }

    $data = $request->get_param('oriel');
    $data = is_array($data) ? $data : [];

    $processor = new FormProcessor($this->registry, $formId, $data);
    $result = $processor->run();

    if (!empty($result->errors)) {
        if (isset($result->errors['security'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result->errors['security'],
            ], 403);
        }

        return new \WP_REST_Response(['success' => false, 'errors' => $result->errors], 422);
    }

    $response = ['success' => true];
    $formConfig = $this->registry->get($formId);

    if (!empty($formConfig['options']['confirmation'])) {
        $response['message'] = $formConfig['options']['confirmation'];
    }

    if (!empty($formConfig['options']['redirect'])) {
        $response['redirect'] = $formConfig['options']['redirect'];
    }

    return new \WP_REST_Response($response, 200);
}
```

Note the new check: if `$result->errors` contains a `security` key, return 403 instead of 422. This differentiates security rejections from validation errors.

**Step 3: Commit**

```
refactor(oriel): move nonce verification to SecurityStep, add 403 for security errors
```

---

### Task 9: Update FormRenderer to conditionally render security fields

**Files:**
- Modify: `src/FormRenderer.php:86-88`

**Step 1: Replace nonce line and add security fields**

Replace the nonce field line (line 88) with a call to a new private method. Add the method to the class.

Replace this line:
```php
$html .= wp_nonce_field('oriel_submit_' . $formId, '_oriel_nonce', true, false);
```

With:
```php
$html .= $this->renderSecurityFields($formId);
```

**Step 2: Add the renderSecurityFields method**

Add this private method to `FormRenderer`:

```php
/**
 * Render hidden security fields: conditional nonce, honeypot, timing token.
 */
private function renderSecurityFields(string $formId): string
{
    $html = '';

    // Nonce: only for logged-in users (avoids stale tokens under full-page caching).
    if (is_user_logged_in()) {
        $html .= wp_nonce_field('oriel_submit_' . $formId, '_oriel_nonce', true, false);
    }

    // Honeypot: hidden field that bots fill but humans don't.
    $honeypotName = \Oriel\Security\HoneypotCheck::resolveFieldName($this->config);

    if ($honeypotName !== null) {
        $html .= '<div style="position:absolute;left:-9999px;" aria-hidden="true">';
        $html .= '<input type="text"';
        $html .= ' name="' . esc_attr($honeypotName) . '"';
        $html .= ' value=""';
        $html .= ' tabindex="-1"';
        $html .= ' autocomplete="off"';
        $html .= ' />';
        $html .= '</div>';
    }

    // Timing token: encoded timestamp to detect instant submissions.
    $html .= '<input type="hidden"';
    $html .= ' name="' . esc_attr(\Oriel\Security\TimingCheck::FIELD_NAME) . '"';
    $html .= ' value="' . esc_attr(base64_encode((string) time())) . '"';
    $html .= ' />';

    return $html;
}
```

**Step 3: Commit**

```
feat(oriel): render honeypot, timing token, conditional nonce in forms
```

---

### Task 10: Dump autoload and verify

**Step 1: Regenerate autoload**

Run: `cd /Users/josh/Sites/colossus/src/plugins/oriel && composer dump-autoload`
Expected: `Generated autoload files` with no errors.

**Step 2: Verify file structure**

Run: `ls -la src/Security/ src/Processing/SecurityStep.php`

Expected output shows all 5 new Security files and SecurityStep.php.

**Step 3: Sanity-check PHP syntax on all new files**

Run: `php -l src/Security/SecurityCheckInterface.php && php -l src/Security/HoneypotCheck.php && php -l src/Security/RateLimitCheck.php && php -l src/Security/TimingCheck.php && php -l src/Security/NonceCheck.php && php -l src/Processing/SecurityStep.php`

Expected: `No syntax errors detected` for each file.

**Step 4: Syntax-check modified files**

Run: `php -l src/Plugin.php && php -l src/FormRenderer.php && php -l src/FormProcessor.php`

Expected: `No syntax errors detected` for each file.

**Step 5: Final commit if any autoload changes**

```
chore(oriel): regenerate autoload for security classes
```
