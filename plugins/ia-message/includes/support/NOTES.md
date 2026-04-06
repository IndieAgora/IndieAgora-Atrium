# AJAX notes for ia-message / includes/support

Files in this directory inspected for AJAX handling:

- `README.md`
- `ajax.php`
- `assets.php`
- `capabilities.php`
- `install.php`
- `routes.php`
- `security.php`

Keep this directory focused on handler registration, request validation, and response payload shaping. When editing search behaviour, avoid unrelated render-path changes.
- 2026-04-06 hotfix: no AJAX/message-send logic changed for the lighter-style composer readability issue. Fix was constrained to ia-message theme CSS so the input field remains readable without altering behaviour or request flow.
