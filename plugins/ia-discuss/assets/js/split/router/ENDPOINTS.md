# assets/js/split/router endpoint notes

This folder contains the highest-consequence client endpoint wiring because it both reads/writes browser query-state and submits major write actions.

## Observed endpoint usage

- `ia_discuss_agoras`
- `ia_discuss_forum_meta`
- `ia_discuss_random_topic`
- `ia_discuss_edit_post`
- `ia_discuss_new_topic`
- `ia_discuss_reply`

## Browser route/query contract

Observed query parameters:

- `iad_view`
- `iad_q`
- `iad_topic`
- `iad_post`

These slices are concatenated into `assets/js/ia-discuss.router.js`.
