# Notes: assets / js / split / topic

## What changed in the 0.3.59 architecture pass
- Topic action source slices were rebuilt from the live runtime bundle into setup, action binding, and export files.

## File/function index
- `_ta.body.js` — Functions: findComposerTextarea, extractQuoteTextFromPost, decodeB64Utf8, copyToClipboard, makePostUrl, ensureConfirmModal, close, confirmModal, openReplyModal
- `topic_actions.bind_actions.js` — Functions: bindTopicActions
- `topic_actions.export.js` — Window exports: IA_DISCUSS_TOPIC_ACTIONS

## Editing rules for this folder
- Keep this folder documentation current when files in the folder change.
