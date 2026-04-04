# includes / render

Server-side formatting helpers for BBCode, attachments, and media extraction.

## File tree
```text
├── attachments.php
├── bbcode.php
└── media.php
```

## File roles
- `attachments.php` — PHP source file used by the plugin runtime.
- `bbcode.php` — PHP source file used by the plugin runtime.
- `media.php` — PHP source file used by the plugin runtime.

## Maintenance entry point
Use this folder README together with the sibling NOTES file before changing anything in the folder.
Update note: the render layer now distinguishes between list-only playlists and selected-video playlist URLs, using the standard YouTube embed domain for the latter.

Update note: playlist URLs are now rendered through YouTube's playlist embed form (`embed?listType=playlist&list=...`) instead of hybrid video-plus-playlist iframe paths.

Update note: playlist iframes were restored in 0.3.66. URLs with both `v=` and `list=` now embed the selected video in playlist context; list-only URLs use the playlist embed form.
