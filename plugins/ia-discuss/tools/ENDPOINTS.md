# tools endpoint notes

No runtime endpoints are registered in this folder.

`build-assets.sh` rebuilds generated front-end bundles only. It does not affect WordPress route registration directly, but any client endpoint caller changes in `assets/js/split/` should be rebuilt through it.
