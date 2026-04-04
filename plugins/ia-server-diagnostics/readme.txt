=== IA Server Diagnostics ===
Contributors: OpenAI
Tags: diagnostics, performance, logging, slow requests, cpu
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.1.2
License: GPLv2 or later

A lightweight diagnostics plugin to log slow WordPress requests and import server-side sampler snapshots.

== Description ==

This plugin is designed to help identify whether WordPress is involved when the server becomes intermittently slow.

It records:
- slow WordPress requests over a configurable threshold
- request type (front/admin/ajax/rest/cron)
- route hints
- AJAX action names when present
- callback targets for AJAX handlers
- request summaries for common parameters
- referer, response code, and output buffer state
- runtime
- peak memory
- optional slow query detail when SAVEQUERIES is enabled
- imported server snapshots from a companion shell script

It does not replace PHP-FPM slow logs, MariaDB slow query logs, or system monitoring.

== Installation ==

1. Upload the plugin zip via Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Open IA Diagnostics in wp-admin.
4. Configure thresholds under IA Diagnostics > Settings.
5. Place the companion shell script on the server and run it via cron.
6. Paste generated JSON into IA Diagnostics > Overview to import snapshots.

== Notes ==

- Query capture only works when SAVEQUERIES is enabled in wp-config.php.
- SAVEQUERIES adds overhead and should only be used when needed.
- This version uses manual sample import.


== Changelog ==

= 0.1.2 =
* Log AJAX action names, callback targets, request summaries, referer, response code, and output-buffer metadata for slow requests.
* Expand the Slow Requests admin screen to surface the extra diagnostic detail directly.

= 0.1.1 =
* Auto-import sampler JSON files from disk.
* Add sampler directory settings.
* Remove imported sample files after ingestion.
