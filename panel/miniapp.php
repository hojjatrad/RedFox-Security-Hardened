<?php
declare(strict_types=1);

/**
 * Compatibility entry point for installations that still reference the old
 * server-rendered Mini App URL. The former implementation trusted uid and
 * tg_init_data query parameters and exposed another user's balance/services.
 * Authentication now happens only in the dedicated /app + /api flow.
 */
header('Cache-Control: no-store, max-age=0');
header('Location: ../app/', true, 302);
exit;
