# Submission processing is a fixed pipeline of self-guarding steps

Every submission runs through the same ordered sequence of processing steps (security → captcha → validate → create post → hooks → email → cleanup → redirect → REST response) over one mutable processing context. Two properties are deliberate:

**The step list is hard-coded.** There is no `oriel_processing_steps` filter. Step order is a correctness and security invariant — security checks must precede captcha verification, which must precede validation, which must precede persistence. Steps are internal; the public extension contract is the hooks *inside* steps (`oriel_security_checks`, `oriel_validate`, `oriel_after_process`, the email filters).

**Steps self-guard rather than the loop branching.** The processor runs all steps unconditionally; each step inspects the context (`shouldHalt`, `isRest`) and returns it unchanged when it must not act. This keeps the processor trivial and puts each step's activation condition next to its logic.

A related invariant: the validate step sanitizes each value first and writes the sanitized value back into the context before validating, so every downstream step (persistence, email) sees sanitized data only.

## Consequences

- Third parties cannot insert a pipeline step. If a real need appears, add a filter then — with the ordering invariants documented — rather than assuming this was an oversight.
