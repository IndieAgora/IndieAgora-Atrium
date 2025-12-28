=== IA PeerTube Login Sync ===
Contributors: indieagora
Tags: peertube, phpbb, atrium, login
Requires at least: 6.0
Tested up to: 6.6
Stable tag: 0.1.0
License: GPLv2 or later

Allows LOCAL PeerTube users (on your instance) to log in to Atrium without separate signup. On first login (or via ACP batch sync),
the plugin creates/links:
- canonical phpBB user (matched by email, else created as standard user)
- WP shadow user
- mapping row in wp_ia_identity_map (created by IA Auth)

== Dependencies ==
- IA Engine (credentials)
- IA Auth (identity map table)
