# Notes: includes / support

## What changed in the 0.3.59 architecture pass
- Asset notes were updated to document the generated-runtime plus split-source workflow.

## File/function index
- `ajax.php` — Classes: IA_Discuss_Support_Ajax Functions/methods: boot, dispatch, ia_discuss_ajax
- `assets.php` — Functions/methods: ia_discuss_support_assets_boot, ia_discuss_register_assets, ia_discuss_enqueue_assets
- `security.php` — Classes: IA_Discuss_Support_Security Functions/methods: verify_nonce_from_post, can_run_ajax, ia_discuss_security
- `user-rel-ajax.php` — Functions/methods: ia_discuss_ajax_user_rel_status, ia_discuss_ajax_user_follow_toggle, ia_discuss_ajax_user_block_toggle

## Editing rules for this folder
- Keep this folder documentation current when files in the folder change.

## 0.3.63 asset dependency update
- Added script handle `ia-discuss-youtube` for the shared YouTube parser/embed helper.
- `ia-discuss-ui-feed` and `ia-discuss-topic-media` now depend on that handle so feed/topic media use the same parser at runtime.

## 0.3.92 feed pagination asset pass
- Kept existing enqueue order intact while extending the feed runtime bundle and toolbar CSS for numbered pagination / jump-to controls.
- No new standalone handles were introduced; the feed bundle rebuild stays on the existing `ia-discuss-ui-feed` handle.


## 0.3.93 packaging permission repair
- No enqueue-order logic was changed in `assets.php`. This repair pass normalises packaged file permissions so the already-registered JS dependency chain can actually load on the server.

## 0.3.94 asset workflow reminder
- No enqueue-order change was needed for this pass. The fix stays inside existing CSS/JS handles.
- To avoid false "JS dependencies not loaded" troubleshooting loops, verify the rebuilt runtime bundle exists after split-source edits and package readable file permissions with the zip.
## 0.3.95 asset discipline reminder
- Pagination UI work touched split feed JS plus split card-layout CSS only; the generated feed bundle was rebuilt afterwards.
- Avoid prior breakdowns by checking both split source edits and rebuilt runtime bundles before packaging.

