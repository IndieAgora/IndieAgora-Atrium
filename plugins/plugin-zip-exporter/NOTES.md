Plugin Zip Exporter notes

Version 1.0.1
- Patch-only download fix for zip streaming
- Uses CREATE|OVERWRITE when opening archives
- Clears output buffers before download and streams with readfile
- Keeps admin UI and plugin actions unchanged

Version 1.0.0
- Initial release
- Adds Tools admin page
- Adds single-plugin zip export
- Adds full plugins directory zip export

Files
- plugin-zip-exporter.php: bootstrap file
- includes/class-plugin-zip-exporter.php: admin UI and zip export handlers
- README.md: install and usage overview
- NOTES.md: change log and file notes
