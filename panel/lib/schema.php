<?php
declare(strict_types=1);
/** Runtime schema mutation was removed. This module only verifies migrations. */
function redfox_schema_ready(PDO$pdo):bool{try{rx_require_schema($pdo,['crypto_wallets','app','shopSetting','reseller_ai_feature','rx_user_cache','nm_config_stock','nm_stock_shelves','nm_config_stock_log','nm_stock_product_map'],['setting'=>['reseller_ai_price','reseller_ai_days']]);return true;}catch(Throwable$e){error_log('[schema] '.redfox_exception_fingerprint($e));return false;}}
function redfox_ensure_column(PDO$pdo,string$table,string$column,string$definition=''):bool{rx_require_schema($pdo,[],[$table=>[$column]]);return true;}
