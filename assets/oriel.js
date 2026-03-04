(function () {
  'use strict';

  /**
   * Scroll-to-form on page load.
   *
   * Checks for ?oriel-errors={id} or ?oriel-submitted={id} query params.
   * If the form is inside a hidden toggle, expands it first.
   */
  function handleScrollOnLoad() {
    var params = new URLSearchParams(window.location.search);
    var targetId = params.get('oriel-errors') || params.get('oriel-submitted');

    if (!targetId) {
      return;
    }

    var wrapper = document.getElementById('oriel-' + targetId);

    if (!wrapper) {
      return;
    }

    // If inside a hidden toggle container, expand it first.
    var hiddenParent = wrapper.closest('[hidden]');

    if (hiddenParent) {
      var toggleBtn = document.querySelector(
        '[aria-controls="' + hiddenParent.id + '"]'
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
    var toggles = document.querySelectorAll('.oriel-form__toggle');

    for (var i = 0; i < toggles.length; i++) {
      toggles[i].addEventListener('click', handleToggle);
    }
  }

  function handleToggle(e) {
    var btn = e.currentTarget;
    var targetId = btn.getAttribute('aria-controls');

    if (!targetId) {
      return;
    }

    var target = document.getElementById(targetId);

    if (!target) {
      return;
    }

    var isHidden = target.hasAttribute('hidden');

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
    var forms = document.querySelectorAll('form[data-oriel-ajax]');

    for (var i = 0; i < forms.length; i++) {
      forms[i].addEventListener('submit', handleAjaxSubmit);
    }
  }

  function handleAjaxSubmit(e) {
    e.preventDefault();

    var form = e.currentTarget;
    var ajaxUrl = form.getAttribute('data-oriel-ajax');
    var wrapper = form.closest('.oriel-form');
    var submitBtn = form.querySelector('[type="submit"]');

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
      .then(function (response) {
        return response.json().then(function (data) {
          return { status: response.status, body: data };
        });
      })
      .then(function (result) {
        var body = result.body;

        if (body.success) {
          // Redirect if requested.
          if (body.redirect) {
            window.location.href = body.redirect;
            return;
          }

          // Show success message.
          var message = body.message || 'Your submission has been received.';
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
          var errorMsg = body.message || 'Submission rejected.';
          showMessage(wrapper, form, errorMsg, 'error');
        }
      })
      .catch(function () {
        showMessage(
          wrapper,
          form,
          'An error occurred. Please try again.',
          'error'
        );
      })
      .finally(function () {
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

    var messages = wrapper.querySelectorAll('.oriel-form__message');

    for (var i = 0; i < messages.length; i++) {
      messages[i].remove();
    }
  }

  /**
   * Clear all field-level errors.
   */
  function clearFieldErrors(form) {
    var errorDivs = form.querySelectorAll('[data-error-for]');

    for (var i = 0; i < errorDivs.length; i++) {
      errorDivs[i].textContent = '';
    }

    var errorFields = form.querySelectorAll('.oriel-field--has-error');

    for (var i = 0; i < errorFields.length; i++) {
      errorFields[i].classList.remove('oriel-field--has-error');
    }
  }

  /**
   * Show a form-level message.
   */
  function showMessage(wrapper, form, text, type) {
    if (!wrapper) {
      return;
    }

    var div = document.createElement('div');
    div.className = 'oriel-form__message oriel-form__message--' + type;
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
    for (var key in errors) {
      if (!errors.hasOwnProperty(key)) {
        continue;
      }

      var inputId = 'oriel_' + key;
      var errorDiv = form.querySelector('[data-error-for="' + inputId + '"]');

      if (errorDiv) {
        errorDiv.textContent = errors[key];
      }

      // Add error class to the field wrapper.
      var fieldWrapper = form.querySelector('.oriel-field--' + inputId);

      if (fieldWrapper) {
        fieldWrapper.classList.add('oriel-field--has-error');
      }
    }
  }

  /**
   * Regenerate the timing token after a successful AJAX submission.
   */
  function regenerateTimingToken(form) {
    var tokenInput = form.querySelector('[name="_oriel_tk"]');

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
