# Forms are code-defined via the `oriel_forms` filter; no admin UI

Oriel has no form builder and no admin settings. Forms exist only as definitions registered through the `oriel_forms` filter. This is the plugin's identity, decided for three reasons: form definitions belong in version control (reviewable, diffable, deployable across environments with no database state to sync); site editors cannot alter or break a form, so deployed code fully determines behavior; and a builder UI is the wrong product for a developer audience — it adds surface area, bugs, and upgrade burden that this audience does not want.

## Considered Options

- Admin UI / form builder — rejected as above.
- A registration API function (`oriel_register_form(...)`) instead of a filter — a filter was chosen as the idiomatic WordPress extension point; it also gives later-running code the chance to modify earlier registrations.

## Consequences

- Viewing which forms exist requires reading code; the admin only shows submissions, not forms.
- Everything configurable must be configurable in code — hence the large filter surface (see ADR-0004).
