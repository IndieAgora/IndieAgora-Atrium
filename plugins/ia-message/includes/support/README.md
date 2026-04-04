# includes/support

Support layer for installation, asset registration, security helpers, routes, and AJAX entrypoints.

`ajax.php` is the stable entry file and should keep public action registration centralised.
Actual callback bodies now live under `includes/support/ajax/` by intent.
