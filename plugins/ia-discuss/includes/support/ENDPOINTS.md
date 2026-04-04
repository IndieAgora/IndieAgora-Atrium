# includes/support endpoint notes

This folder contains the transport and registration layer for the Discuss endpoint surface.

## Files of interest

### `ajax.php`
- Builds the action map from module `ajax_routes()` declarations
- Registers `wp_ajax_{action}` hooks for every declared route
- Registers `wp_ajax_nopriv_{action}` hooks for routes marked `public`
- Enforces the `ia_discuss` nonce and central AJAX permission checks before dispatch

### `assets.php`
- Localises `window.IA_DISCUSS.ajaxUrl` to WordPress `admin-ajax.php`
- Localises the Discuss nonce used by client requests
- Localises optional Connect integration transport values
- Owns script/style registration order, so endpoint-calling scripts should remain wired through these handles

### `security.php`
- Provides shared security checks used before endpoint dispatch

### `user-rel-ajax.php`
Registers three direct authenticated AJAX handlers outside the module dispatcher:

- `ia_user_rel_status` → `ia_discuss_ajax_user_rel_status()`
- `ia_user_follow_toggle` → `ia_discuss_ajax_user_follow_toggle()`
- `ia_user_block_toggle` → `ia_discuss_ajax_user_block_toggle()`

These handlers verify the same `ia_discuss` nonce from POST.
