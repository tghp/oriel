## Form Options

| Key                       | Description                                                                               |
| ------------------------- | ----------------------------------------------------------------------------------------- |
| `redirect`                | URL to redirect to after submission                                                       |
| `confirmation`            | Success message shown after submission                                                    |
| `ajax`                    | Boolean, enables AJAX submission via REST API (see [AJAX Submissions](submissions.md#ajax-submissions)) |
| `email`                   | Array with `email` (recipient) and `title` (subject) keys                                 |
| `delete_after_processing` | Delete the submission post after hooks fire (unless `_oriel_do_not_delete` meta is `'1'`) |
| `class`                   | Extra CSS class on form wrapper                                                           |
| `submit_class`            | CSS class on submit button                                                                |
| `submit_text`             | Submit button label (default: `Submit`)                                                   |
| `compat`                  | Compat mode string. `'tghpmb'` enables Meta Box frontend submission output parity.        |
| `compat_prefix`           | Field prefix for compat mode (e.g. `'_tghpcontact_'`). Falls back to `_tghp{formId}_`.    |
