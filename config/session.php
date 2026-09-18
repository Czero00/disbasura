<?php
// DisBasura — Simple session, NO timeout, NO expiry
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
