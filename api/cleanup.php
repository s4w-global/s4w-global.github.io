<?php
// Cron-based cleanup for shared hosting (MijnDomein compatible)
require __DIR__.'/db.php';

// Sessions cleanup
$pdo->exec("DELETE FROM sessions WHERE expires_at < NOW() OR is_active = 0");

// Tokens cleanup
$pdo->exec("DELETE FROM tokens WHERE expires_at < NOW()");

// Panic reports TTL cleanup
$pdo->exec("DELETE FROM reports WHERE report_type='panic' AND expires_at IS NOT NULL AND expires_at < NOW()");
