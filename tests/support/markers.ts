let counter = 0;

/**
 * Unique per-test value. Tests stamp this into a submission field and assert
 * by searching for it (post meta, Mailpit) — the suite's isolation mechanism
 * instead of DB cleanup.
 */
export function uniqueMarker(prefix: string): string {
  counter += 1;
  return `${prefix}-${Date.now()}-${process.pid}-${counter}`;
}
