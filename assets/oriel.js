(function () {
  'use strict';

  /**
   * Scroll-to-form on page load.
   *
   * Checks for ?oriel-errors={id} or ?oriel-submitted={id} query params.
   * If the form is inside a hidden toggle, expands it first.
   */
  function handleScrollOnLoad() {
    const params = new URLSearchParams(window.location.search);
    const targetId = params.get('oriel-errors') || params.get('oriel-submitted');

    if (!targetId) {
      return;
    }

    const wrapper = document.getElementById(`oriel-${targetId}`);

    if (!wrapper) {
      return;
    }

    // If inside a hidden toggle container, expand it first.
    const hiddenParent = wrapper.closest('[hidden]');

    if (hiddenParent) {
      const toggleBtn = document.querySelector(
        `[aria-controls="${hiddenParent.id}"]`
      );

      if (toggleBtn) {
        hiddenParent.removeAttribute('hidden');
        toggleBtn.setAttribute('aria-expanded', 'true');
      }
    }

    wrapper.scrollIntoView({ behavior: 'smooth' });
  }

  /**
   * Bind toggle buttons.
   *
   * Toggles hidden/aria-expanded on the target element.
   */
  function initToggles() {
    const toggles = document.querySelectorAll('.oriel-form__toggle');

    for (const toggle of toggles) {
      toggle.addEventListener('click', handleToggle);
    }
  }

  function handleToggle(e) {
    const btn = e.currentTarget;
    const targetId = btn.getAttribute('aria-controls');

    if (!targetId) {
      return;
    }

    const target = document.getElementById(targetId);

    if (!target) {
      return;
    }

    const isHidden = target.hasAttribute('hidden');

    if (isHidden) {
      target.removeAttribute('hidden');
      btn.setAttribute('aria-expanded', 'true');
    } else {
      target.setAttribute('hidden', '');
      btn.setAttribute('aria-expanded', 'false');
    }
  }

  /**
   * Initialize AJAX submission on forms with [data-oriel-ajax].
   */
  function initAjaxForms() {
    const forms = document.querySelectorAll('form[data-oriel-ajax]');

    for (const form of forms) {
      form.addEventListener('submit', handleAjaxSubmit);
    }
  }

  function handleAjaxSubmit(e) {
    e.preventDefault();

    const form = e.currentTarget;
    const ajaxUrl = form.getAttribute('data-oriel-ajax');
    const wrapper = form.closest('.oriel-form');
    const submitBtn = form.querySelector('[type="submit"]');

    // Prevent double submit.
    if (form.classList.contains('oriel-form--submitting')) {
      return;
    }

    // Clear previous messages and field errors.
    clearMessages(wrapper);
    clearFieldErrors(form);

    form.classList.add('oriel-form--submitting');

    if (submitBtn) {
      submitBtn.disabled = true;
    }

    fetch(ajaxUrl, {
      method: 'POST',
      body: new FormData(form),
      credentials: 'same-origin',
    })
      .then((response) => {
        return response.json().then((data) => {
          return { status: response.status, body: data };
        });
      })
      .then((result) => {
        const { body } = result;

        if (body.success) {
          // Redirect if requested.
          if (body.redirect) {
            window.location.href = body.redirect;
            return;
          }

          // Show success message.
          const message = body.message || 'Your submission has been received.';
          showMessage(wrapper, form, message, 'success');

          // Reset form and regenerate timing token.
          form.reset();
          regenerateTimingToken(form);
        } else if (body.errors) {
          // Validation errors — show per-field errors.
          showFieldErrors(form, body.errors);

          // Also show a form-level error message.
          showMessage(
            wrapper,
            form,
            'There were errors with your submission. Please correct them and try again.',
            'error'
          );
        } else {
          // Security or generic error.
          const errorMsg = body.message || 'Submission rejected.';
          showMessage(wrapper, form, errorMsg, 'error');
        }
      })
      .catch(() => {
        showMessage(
          wrapper,
          form,
          'An error occurred. Please try again.',
          'error'
        );
      })
      .finally(() => {
        form.classList.remove('oriel-form--submitting');

        if (submitBtn) {
          submitBtn.disabled = false;
        }

        if (wrapper) {
          wrapper.scrollIntoView({ behavior: 'smooth' });
        }
      });
  }

  /**
   * Clear any existing form-level messages.
   */
  function clearMessages(wrapper) {
    if (!wrapper) {
      return;
    }

    const messages = wrapper.querySelectorAll('.oriel-form__message');

    for (const message of messages) {
      message.remove();
    }
  }

  /**
   * Clear all field-level errors.
   */
  function clearFieldErrors(form) {
    const errorDivs = form.querySelectorAll('[data-error-for]');

    for (const errorDiv of errorDivs) {
      errorDiv.textContent = '';
    }

    const errorFields = form.querySelectorAll('.oriel-field--has-error');

    for (const errorField of errorFields) {
      errorField.classList.remove('oriel-field--has-error');
    }
  }

  /**
   * Show a form-level message.
   */
  function showMessage(wrapper, form, text, type) {
    if (!wrapper) {
      return;
    }

    const div = document.createElement('div');
    div.className = `oriel-form__message oriel-form__message--${type}`;
    div.textContent = text;

    wrapper.insertBefore(div, form);
  }

  /**
   * Show per-field validation errors.
   *
   * Error keys from REST use raw field IDs (e.g. "name").
   * data-error-for uses input IDs (e.g. "oriel_name").
   */
  function showFieldErrors(form, errors) {
    for (const [key, value] of Object.entries(errors)) {
      const inputId = `oriel_${key}`;
      const errorDiv = form.querySelector(`[data-error-for="${inputId}"]`);

      if (errorDiv) {
        errorDiv.textContent = value;
      }

      // Add error class to the field wrapper.
      const fieldWrapper = form.querySelector(`.oriel-field--${inputId}`);

      if (fieldWrapper) {
        fieldWrapper.classList.add('oriel-field--has-error');
      }
    }
  }

  /**
   * Regenerate the timing token after a successful AJAX submission.
   */
  function regenerateTimingToken(form) {
    const tokenInput = form.querySelector('[name="_oriel_tk"]');

    if (tokenInput) {
      tokenInput.value = btoa(String(Math.floor(Date.now() / 1000)));
    }
  }

  // Initialize on DOM ready.
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  function init() {
    handleScrollOnLoad();
    initToggles();
    initAjaxForms();
  }
})();
