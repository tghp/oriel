# Submissions are stored as a CPT plus taxonomy, not a custom table

Submission posts are `oriel_submission` posts with field values in `_oriel_`-prefixed post meta, grouped by an `oriel_form` taxonomy term per form. A custom table was the alternative and was rejected because posts give us what a table would cost effort to build: a free admin screen (`show_ui`) with a Forms column, no schema or migration code to own, and interop with the whole WordPress ecosystem (WP_Query, exports, backups, hooks). Submission volume is contact-form scale, so meta-table query performance was accepted as a non-issue.

## Consequences

- Field values live in unindexed meta; querying submissions *by field value* at scale would be slow. If that need ever arrives, revisit.
- Storage is coupled to WordPress post semantics (trash, revisions off, `capability_type => 'page'`).
