<?php
declare(strict_types=1);header('Content-Type:application/json; charset=utf-8');header('Cache-Control:no-store');$v=trim((string)@file_get_contents(__DIR__.'/version'));echo json_encode(['ok'=>true,'service'=>'redfox','version'=>$v?:'unknown','time'=>time()],JSON_UNESCAPED_SLASHES);
