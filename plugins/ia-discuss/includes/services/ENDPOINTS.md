# includes/services endpoint notes

This folder does not directly register WordPress AJAX or REST endpoints.

It provides the service layer consumed by endpoint handlers. Important side effects for endpoint work include:

- auth/user resolution for authenticated actions
- phpBB reads and writes behind Discuss actions
- membership and cover storage used by membership/moderation actions
- notifications emitted after topic/reply/moderation events

When a service change affects request/response shape or permissions, update the nearest module `ENDPOINTS.md` and the root master index.
