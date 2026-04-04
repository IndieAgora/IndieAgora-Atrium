# assets/js/split/composer endpoint notes

This folder does not directly define server endpoints.

It manages composer-side local state such as draft persistence and file binding. Server submissions are ultimately handed off through the router/UI layers that call `ia_discuss_new_topic`, `ia_discuss_reply`, and `ia_discuss_edit_post`.
