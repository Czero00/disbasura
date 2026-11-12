<?php
// Copy to config/local.php. Keep real keys out of Git.
// Optional Semaphore SMS settings.
// Add these settings to config/local.php only after Semaphore approves the account.
// Leave the sender name empty to use the default sender registered in Semaphore.
define('SEMAPHORE_API_KEY', '');
define('SEMAPHORE_SENDER_NAME', '');

// Optional database password for tools/backup-disbasura.ps1.
// Prefer setting DISBASURA_DB_PASSWORD in the environment instead.
