# DisBasura GPS, payment, and deployment safeguards

## GPS behavior

- The tracker treats a location as live for two minutes after its last update. Older positions remain visible as stale status only; their coordinates are hidden from the map.
- GPS history is recorded about once every 30 seconds. Offline fixes stay in the collector browser's IndexedDB and are sent to route history after connectivity returns. Queued fixes do not replace the current live position.
- Admins can inspect routes at `/disbasura/admin/tracking-history.php`. Residents and sitio leaders can see only collectors assigned to their own sitio; administrators can see all locations.
- Use HTTPS for access from other devices. Browser location permission and server reachability are both required for live tracking.

## Database and photo backups

Run `powershell -ExecutionPolicy Bypass -File .\tools\backup-disbasura.ps1` from the project directory. The script writes a timestamped ZIP containing a MySQL dump and uploaded photos to `Documents\DisBasuraBackups`, outside the web folder. The default XAMPP root account has no password; if you set one, provide it through the DISBASURA_DB_PASSWORD environment variable before running the script.

Backups contain personal data and photos. Protect the backup folder, copy backups to a separate device or protected cloud storage, and periodically confirm that an archive can be restored. Windows Task Scheduler can run the same script on a regular schedule.

## Apache upload/config protection

The `config/.htaccess` file denies direct web access to configuration files. The `uploads/.htaccess` file blocks script execution from the upload directory. These rules require Apache `AllowOverride` support and the relevant authorization module. Keep `config/local.php` and mail/SMS/payment secrets out of Git.

## QR Ph and cash payments

QR Ph payments require residents to provide a transaction reference and receipt image. A Sitio Leader verifies the transaction outside the app and records the decision in Review Payments. The app does not automatically verify transfers.
