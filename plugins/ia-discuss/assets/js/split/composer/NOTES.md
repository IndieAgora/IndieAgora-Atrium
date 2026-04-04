# Notes: assets / js / split / composer

## What changed in the 0.3.59 architecture pass
- Composer source slices were rebuilt from the live runtime bundle and renamed by intent where needed.

## File/function index
- `_composer.body.js` — Functions: htmlToBbcode, walk, wrapSelection, insertAtCursor, isImage, isVideo, makeToolbar, composerHTML
- `composer.bind_attachments.js` — Functions: setOpen, renderAttachList
- `composer.bind_state_and_files.js` — Functions: bindComposer, draftLoad, draftSave, draftClear, setError, openFilePicker
- `composer.export.js` — Window exports: IA_DISCUSS_UI_COMPOSER

## Editing rules for this folder
- Keep this folder documentation current when files in the folder change.
