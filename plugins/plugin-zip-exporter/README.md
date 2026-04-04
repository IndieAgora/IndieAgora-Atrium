# Plugin Zip Exporter

This plugin adds an admin page under **Tools → Plugin Zip Exporter**.

It allows an administrator to:
- download any individual plugin folder in `wp-content/plugins` as a zip
- download the entire `wp-content/plugins` directory as a zip

## Requirements

- WordPress admin access with `manage_options`
- PHP `ZipArchive` enabled

## Notes

- This works on standard plugin folders in `wp-content/plugins`
- It does not add cloud storage, scheduling, or external endpoints
- Zip generation is done on demand through authenticated admin actions
