# includes / support

WordPress support glue for assets, AJAX dispatch, security checks, and relationship endpoints.

## File tree
```text
├── ajax.php
├── assets.php
├── security.php
└── user-rel-ajax.php
```

## File roles
- `ajax.php` — PHP source file used by the plugin runtime.
- `assets.php` — PHP source file used by the plugin runtime.
- `security.php` — PHP source file used by the plugin runtime.
- `user-rel-ajax.php` — PHP source file used by the plugin runtime.

## Maintenance entry point
Use this folder README together with the sibling NOTES file before changing anything in the folder.

Update note: `assets.php` now registers `ia-discuss-youtube` as a shared helper dependency for feed/topic media assets.
