<?php
/** Optional at-rest encryption for panel credentials. */
declare(strict_types=1);
if (!function_exists('rx_env')) require_once __DIR__ . '/HostingSecrets.php';

function rx_key_from_env(string $name): ?string { $raw=trim((string)(rx_env($name)?:''));if($raw==='')return null;if(preg_match('/^[a-f0-9]{64}$/i',$raw))$key=hex2bin($raw);else{$d=base64_decode($raw,true);$key=is_string($d)?$d:null;}return is_string($key)&&strlen($key)===32?$key:null; }
function rx_secret_master_key(): ?string {
    static $loaded=false,$key=null;if($loaded)return$key;$loaded=true;$raw=trim((string)(rx_env('REDFOX_MASTER_KEY')?:''));if($raw==='')return null;
    if(preg_match('/^[a-f0-9]{64}$/i',$raw))$key=hex2bin($raw);else{$d=base64_decode($raw,true);if(is_string($d)&&strlen($d)===32)$key=$d;}
    if(!is_string($key)||strlen($key)!==32)throw new RuntimeException('REDFOX_MASTER_KEY must be 32 bytes encoded as 64 hex chars or base64.');return$key;
}
function rx_secret_is_encrypted(string $v): bool { return str_starts_with($v,'rxenc:v1:'); }
function rx_secret_encrypt(?string $value): ?string {
    if($value===null||$value===''||rx_secret_is_encrypted($value))return$value;$key=rx_secret_master_key();if($key===null)throw new RuntimeException('REDFOX_MASTER_KEY is required before storing credentials.');
    if(function_exists('sodium_crypto_secretbox')){$nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);$cipher=sodium_crypto_secretbox($value,$nonce,$key);return'rxenc:v1:s:'.base64_encode($nonce.$cipher);}
    if(function_exists('openssl_encrypt')){$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($value,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);if($cipher===false)throw new RuntimeException('Secret encryption failed');return'rxenc:v1:o:'.base64_encode($iv.$tag.$cipher);}
    throw new RuntimeException('No sodium or OpenSSL encryption backend is available.');
}
function rx_secret_decrypt(?string $value): ?string {
    if($value===null||$value===''||!rx_secret_is_encrypted($value))return$value;$key=rx_secret_master_key();if($key===null)throw new RuntimeException('Encrypted credentials exist but REDFOX_MASTER_KEY is unavailable.');$parts=explode(':',$value,4);if(count($parts)!==4)throw new RuntimeException('Malformed encrypted credential');$raw=base64_decode($parts[3],true);if(!is_string($raw))throw new RuntimeException('Malformed encrypted credential');
    if($parts[2]==='s'){if(!function_exists('sodium_crypto_secretbox_open'))throw new RuntimeException('Sodium unavailable');$n=SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;$plain=sodium_crypto_secretbox_open(substr($raw,$n),substr($raw,0,$n),$key);}
    elseif($parts[2]==='o'){if(strlen($raw)<28)throw new RuntimeException('Malformed encrypted credential');$plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));}
    else throw new RuntimeException('Unknown encrypted credential format');if(!is_string($plain))throw new RuntimeException('Credential decryption failed; verify REDFOX_MASTER_KEY.');return$plain;
}
function rx_secret_decrypt_panel_row(array $row): array { foreach(['password_panel','api_key','xui_api_token','secret_code'] as $k)if(array_key_exists($k,$row)&&is_string($row[$k]))$row[$k]=rx_secret_decrypt($row[$k]);return$row; }
function rx_secret_decrypt_db_result(string $table,string $field,$result){if($table!=='marzban_panel')return$result;$sensitive=['password_panel','api_key','xui_api_token','secret_code'];if(in_array($field,$sensitive,true)&&is_string($result))return rx_secret_decrypt($result);if(is_array($result)){if(array_is_list($result)){foreach($result as &$r)if(is_array($r))$r=rx_secret_decrypt_panel_row($r);unset($r);return$result;}return rx_secret_decrypt_panel_row($result);}return$result;}
