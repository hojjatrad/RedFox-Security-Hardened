<?php
require_once __DIR__.'/../lib/CronGuard.php';
rx_cron_authorize();
require_once __DIR__.'/../cron/update_check.php';
