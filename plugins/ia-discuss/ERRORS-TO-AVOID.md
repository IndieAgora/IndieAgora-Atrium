# Errors to Avoid

## 2026-03-08 — bbcode.php regex quoting parse error

Issue:
A patch to protect code/pre blocks in `includes/render/bbcode.php` introduced a PHP parse error because single-quoted PHP strings were used for regex patterns that themselves contained unescaped single quotes inside character classes.

Symptom:
Plugin activation/runtime caused a critical error. `php -l includes/render/bbcode.php` reported: `Parse error: Unclosed '(' does not match ']'`.

Cause:
Patterns like these were unsafe in single-quoted PHP strings:
- `<a ... href=(["'])(...)...>`
- `[^\s<>"']+`

Rule:
When a regex pattern contains a literal single quote, do not wrap the PHP string in single quotes unless every embedded single quote is escaped correctly. Prefer double-quoted PHP strings for these regexes, then lint the edited PHP file before packaging.

Prevention checklist:
1. Run `php -l` on every edited PHP file before zipping.
2. Be extra careful with regexes containing `['"]`, HTML attribute patterns, or character classes that include `'`.
3. For code/pre protection work, patch only the target function and avoid touching unrelated regex quoting.

- UI overlay spacing regressions: when a fullscreen Discuss view sits under a fixed site header, offset the fullscreen sheet itself and reduce its height by the same amount. Do not add padding that increases viewport overflow unless that is explicitly intended.

## 2026-03-08 — personal-feed state leakage and over-broad repair attempts

Issue:
A feature add for `My Topics`, `My Replies`, and `My History` regressed unrelated feed behaviour because the new personal views were partially implemented by leaning on older global feed/reply/random paths.

Symptoms:
- `My Replies` looked like the global replies feed.
- Random appeared stuck on a tiny set of topics because it inherited history/personal-feed context.
- A later attempt to fix Random widened into unrelated topic/reply/feed breakage.

Causes:
1. New sidebar labels were added before all downstream view semantics were kept distinct.
2. Random was treated like another feed context instead of a standalone random-topic action.
3. The repair touched broader generated/runtime paths than the requested target, instead of diffing from the last known-good version and patching only the exact handler.

Rules:
- New user-personal views must stay isolated all the way through router state, API tab mapping, ordering, and card semantics.
- Random must stay decoupled from personal-feed/history state unless an intentional design note says otherwise.
- For regressions, restore the last known-good behaviour first, then patch only the failing handler/path.

Prevention checklist:
1. Trace a new view end-to-end: sidebar key -> router view -> API tab -> card-open semantics -> sort state.
2. When fixing one action, diff against the last known-good zip and limit edits to that action's files/functions.
3. After refactors, verify `Topics`, `Replies`, `0 replies`, `Random`, `My Topics`, `My Replies`, and `My History` separately before packaging.

## 2026-03-15 — layout mode vs theme regression

Issue:
A request for an optional phpBB-like forum navigation mode was implemented as a theme-style reskin.

Symptoms:
- The new option appeared twice or in the wrong place.
- Discuss kept the same feed/agora structure and only the colours changed.

Cause:
The requested behaviour change was in navigation/layout, not in palette/theme. Treating it as a theme caused the wrong UI surface to be patched.

Rule:
When the user asks for an alternative way to navigate the same data, keep that as a separate layout/view mode. Do not fold it into theme selection unless they explicitly ask for a visual theme only.

## Feed toolbar / asset rebuild pitfalls
- Do not edit only `assets/js/ia-discuss.ui.feed.js` when the source of truth is the split feed tree. Rebuild from `assets/js/split/feed/*.js` with `bash tools/build-assets.sh` or the next build will revert the live fix.
- When a UI change appears to "not apply", check both causes before touching enqueue order: (1) generated JS bundle not rebuilt after split-source edits, (2) packaged asset permissions prevent the built file from loading.


## 2026-03-21 — hidden attribute overridden by authored layout CSS

Issue:
The new pagination Jump To form was marked `hidden` in markup, but authored CSS on `.iad-feed-jump` forced `display:flex`, so the form rendered immediately instead of staying hidden until the button was clicked.

Symptoms:
- Jump To input showed all the time in page mode.
- It looked like the click toggle logic was broken even though the JS handler existed.
- SVG buttons also appeared to have text inside them because the accessibility helper class was missing.

Cause:
- Relying on the browser `hidden` attribute without adding an explicit authored rule in a stylesheet that also sets `display` for the same selector.
- Using `.iad-screen-reader-text` in markup without defining that helper class in CSS.

Rule:
Whenever a UI element is meant to stay hidden until click, add a matching authored `[hidden]{display:none !important;}` rule for that component if authored CSS also assigns `display`. Whenever SVG-only controls include accessibility text spans, ensure the screen-reader utility class actually exists in CSS.

Prevention checklist:
1. If markup contains `hidden`, search for authored CSS that sets `display` on the same selector.
2. If markup contains `.iad-screen-reader-text`, confirm the utility is defined before packaging.
3. After UI-icon changes, verify the rendered control shows only SVGs and not fallback text.

- Do not reintroduce visible sort-icon chrome when the feed already has a sort dropdown; keep one sort control only.
- Do not place the top pager on its own row when the requested layout calls for one shared header row.
