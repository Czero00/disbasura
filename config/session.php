<?php
// DisBasura — Simple session, NO timeout, NO expiry
//
// Each role area gets its OWN session cookie (admin / collector /
// resident-leader) instead of all three sharing one PHPSESSID.
// Without this, opening admin + collector + resident in different
// tabs of the same browser merges them into a single session, and
// logging out of one role wipes the others too (session_destroy()
// in logout.php clears everything, not just that role's keys).
if (session_status() === PHP_SESSION_NONE) {
    $path = $_SERVER['SCRIPT_NAME'] ?? '';
    $role = $_GET['role'] ?? ''; // used by logout.php, which sits outside /admin//collector/
    if (strpos($path, '/admin/') !== false || $role === 'admin') {
        session_name('disbasura_admin');
    } elseif (strpos($path, '/collector/') !== false || $role === 'collector') {
        session_name('disbasura_collector');
    } else {
        session_name('disbasura_resident'); // resident + sitio leader
    }
    session_start();
}
