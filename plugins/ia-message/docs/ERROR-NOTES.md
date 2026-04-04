# Error Notes

## 2026-03-08

- AJAX send handler previously referenced `$ttype` before it was initialised. In most environments that would only raise a notice, but it also meant the DM block check could be skipped. This was normalised by moving thread type resolution above the block/privacy checks in `includes/support/ajax/messages.php`.
- Front-end runtime had empty intent-labelled helper files while most behaviour still lived in `assets/js/ia-message.boot.js`. This increases patch risk because unrelated fixes land in the same file. Initial extraction has started with API/state/modal helpers.

- Composer textarea was fixed-height, which made longer messages awkward to edit. The patch route uses a dedicated helper file with capped autosize instead of folding yet more UI behaviour into `assets/js/ia-message.boot.js`.

## Ongoing discipline

When a bug is traced to file sprawl, ordering, or implicit globals, add a note here before or alongside the code fix so the same mistake is not repeated later.

- Initial autosize patch allowed the textarea to appear too expanded at rest. Baseline behaviour was tightened so the composer starts compact and only grows after roughly two lines of content.
- Composer media support previously required manual file-picker attachment only. Clipboard file paste now reuses the same upload route to avoid creating a second media path that could drift out of sync.
