# includes endpoint notes

This folder owns the plugin's PHP-side endpoint surface.

## Endpoint ownership by subfolder

- `modules/` registers most AJAX actions through `IA_Discuss_Module_Interface::ajax_routes()` and the central dispatcher.
- `support/` contains the AJAX dispatcher, nonce/security enforcement, asset-localised transport values, and three direct user-relationship AJAX handlers.
- `services/` and `render/` support endpoint handlers but do not expose WordPress routes directly.

See:

- Master index: `../ENDPOINTS.md`
- Module action map: `modules/ENDPOINTS.md`
- Support transport notes: `support/ENDPOINTS.md`

- `ia_discuss_feed` now also serves the internal personal feed tabs `my_topics`, `my_replies`, and `my_history` via its existing `tab` transport field.

