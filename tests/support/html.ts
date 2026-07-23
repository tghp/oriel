/**
 * Normalize rendered form HTML for snapshot comparison: security field values
 * change every render (timing token) or per session (nonce) and must not
 * churn snapshots. Structure — including the fields' presence — is preserved.
 */
export function normalizeFormHtml(html: string): string {
  return html
    .replace(
      /(<input[^>]*name="_oriel_tk"[^>]*value=")[^"]*(")/g,
      '$1__ORIEL_TK__$2',
    )
    .replace(
      /(<input[^>]*name="_oriel_nonce"[^>]*value=")[^"]*(")/g,
      '$1__ORIEL_NONCE__$2',
    );
}
