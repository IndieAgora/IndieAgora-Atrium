# IA Message Development Rules

1. Patch-first. Preserve existing behaviour, routes, selectors, action names, filters, hooks, and integration points unless a change is explicitly requested.
2. Keep public contracts stable. Existing AJAX action names, PHP function names used cross-plugin, and JS globals already consumed by the UI must not be renamed casually.
3. Split by intent. Prefer small files named for one responsibility over expanding unrelated monoliths.
4. Do not invent new Atrium endpoints or identity assumptions. Resolve only from confirmed tables, filters, user meta, and existing services already present in this plugin.
5. Document every meaningful change in `docs/LIVE-NOTES.md` and add durable architecture notes to `docs/DEVELOPMENT-NOTES.md` when the change affects future work.
6. Record pitfalls in `docs/ERROR-NOTES.md`, especially if a bug came from ordering, undefined variables, stale DOM references, or identity mapping ambiguity.
7. Preserve SPA behaviour. Assume Atrium may keep panels in DOM while changing active tabs. Avoid one-shot bindings that only work on first open.
8. Keep loader paths stable. Prefer refactoring behind existing loader files such as `includes/support/ajax.php` and `includes/support/assets.php`.
9. When splitting a file, keep the old entrypoint as a loader/shim when possible so upgrade diffs remain narrow.
10. Any future endpoint addition must be documented in `docs/ENDPOINTS.md` at the same time as the code change.
