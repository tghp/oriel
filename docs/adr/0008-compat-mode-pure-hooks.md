# Compat mode is a pure-hooks module; core DOM changed where filters couldn't reach

Oriel replaced tghp-mb-contact (Meta Box frontend submission) on existing sites, and the goal was that existing stylesheets apply without changes. Compat mode achieves DOM and class-name parity per-form (`'compat' => 'tghpmb'`) through a module that only registers rendering filters — no forked renderer, no template, no field subclasses. Every callback no-ops for non-compat forms. Pure hooks means one renderer to maintain, and compat exercises the same public filter surface offered to users, keeping those filters honest.

Where filters alone couldn't achieve parity, the **default** DOM was changed for all forms rather than special-cased: the submit block moved outside the fields wrapper, every input gained an input-wrapper div, and `novalidate` / `autocomplete="off"` became default form attributes. Parity gaps that CSS doesn't target were accepted rather than chased: input `id`/`name` format stays `oriel_*`, security fields differ, and the error placeholder div is always present.

## Consequences

- Core markup carries structure that exists for parity (input wrapper div, submit inner wrapper). Removing it breaks compat mode.
- Adding a new compat target means a new filter-only module; if a target ever needs markup filters can't produce, that pressure goes into new core filters or defaults, not a forked renderer.
