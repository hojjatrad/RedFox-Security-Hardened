<?php
require_once __DIR__ . '/../lib/CronGuard.php';
rx_cron_authorize();
header('Content-Type: text/plain; charset=utf-8');
echo "Red Fox cron endpoint\n";
