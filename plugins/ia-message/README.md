# IA Message

Atrium-native messaging module for WordPress/Atrium surfaces.

This package now carries local development discipline files so future edits can be narrower, easier to reason about, and less likely to break unrelated messaging behaviour.

Key runtime surfaces:
- PHP bootstrap: `ia-message.php`, `includes/ia-message.php`
- AJAX endpoint loader: `includes/support/ajax.php`
- AJAX callbacks by intent: `includes/support/ajax/*.php`
- Services: `includes/services/*.php`
- Panel template: `includes/templates/panel.php`
- Front-end runtime: `assets/js/ia-message.boot.js` plus extracted helpers in `assets/js/ia-message.*.js`

Documentation index:
- `docs/RULES.md`
- `docs/DEVELOPMENT-NOTES.md`
- `docs/ERROR-NOTES.md`
- `docs/LIVE-NOTES.md`
- `docs/ENDPOINTS.md`
- `assets/js/README.md`
- `includes/support/README.md`
- `includes/services/README.md`
