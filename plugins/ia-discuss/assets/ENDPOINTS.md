# assets endpoint notes

This folder does not register server endpoints itself. It contains the front-end callers and route readers for endpoints defined under `includes/`.

## Key facts

- Server transport consumed here: `window.IA_DISCUSS.ajaxUrl`
- Server nonce consumed here: `window.IA_DISCUSS.nonce`
- Main endpoint-calling code lives in `assets/js/`
- CSS files do not create or own endpoints

See also:

- Root master index: `../ENDPOINTS.md`
- JS caller map: `js/ENDPOINTS.md`
