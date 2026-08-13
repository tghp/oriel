# Built-in rendering with filters; no template overrides; no CSS

Oriel renders all form HTML itself. There is no template file a theme can copy and override — customization happens through rendering filters (classes, attributes, IDs, wrappers, content insertion). Three reasons: overridden templates rot — theme copies fork the markup and silently miss upstream fixes; a canonical DOM is the contract — compat modes, the bundled JS, and CSS targeting all rely on one known structure, and filters compose where templates replace; and developers usually want surgical changes (one class, one wrapper) rather than re-owning the whole markup.

On assets: Oriel ships **no CSS** — output is unstyled by design, bring your own. It does ship minimal functional JS (`oriel.js`: AJAX submission, scroll-to-form, hide/toggle). The decision is about presentation, not assets in general: never ship styles; behavioral JS is allowed when a feature needs it.

## Consequences

- Every markup need must be met by a filter, which is why the rendering filter surface is large (~30 filters). That is the accepted cost.
- Compat modes (ADR-0008) are possible precisely because rendering is filterable rather than replaceable.
