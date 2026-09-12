<?php
declare(strict_types=1);
if (!function_exists('rx_env')) require_once __DIR__ . '/HostingSecrets.php';
final class RedFoxUnsignedUpdatePackage extends RuntimeException {}
final class RedFoxSecureUpdater {
    private const MAX_FILES=20000; private const MAX_BYTES=134217728;
    public static function canonical(array $data): string { $sort=function($v)use(&$sort){if(!is_array($v))return$v;if(array_is_list($v))return array_map($sort,$v);ksort($v,SORT_STRING);foreach($v as$k=>$x)$v[$k]=$sort($x);return$v;};return json_encode($sort($data),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); }
    public static function publicKey(string $keyId='primary'): string { $raw='';$ring=trim((string)(rx_env('REDFOX_UPDATE_PUBLIC_KEYS')?:''));if($ring!==''){$keys=json_decode($ring,true);if(!is_array($keys)||!isset($keys[$keyId]))throw new RuntimeException("Update key id is not trusted: $keyId");$raw=trim((string)$keys[$keyId]);}else{$raw=trim((string)(rx_env('REDFOX_UPDATE_PUBLIC_KEY')?:''));if($keyId!=='primary'&&$keyId!=='')throw new RuntimeException("Update key id is not trusted: $keyId");}if($raw==='')throw new RuntimeException('Update public key is not configured');if(preg_match('/^[a-f0-9]{64}$/i',$raw))$key=hex2bin($raw);else$key=base64_decode($raw,true);if(!is_string($key)||strlen($key)!==32)throw new RuntimeException('Update public key must be 32 bytes');return$key; }
    public static function trustedKeyIds(): array { $ring=trim((string)(rx_env('REDFOX_UPDATE_PUBLIC_KEYS')?:''));if($ring===''){$k=self::publicKey('primary');return['primary'];}$keys=json_decode($ring,true);if(!is_array($keys)||!$keys)throw new RuntimeException('Update public key ring is invalid');$ids=[];foreach(array_keys($keys)as$id){self::publicKey((string)$id);$ids[]=(string)$id;}return$ids; }
    private static function safeName(string$n): bool {return $n!==''&&!str_contains($n,"\0")&&!str_starts_with($n,'/')&&!preg_match('#(^|/)\.\.(/|$)#',$n)&&!preg_match('#^[A-Za-z]:#',$n)&&preg_match('#^[A-Za-z0-9_@./+\- ]+$#',$n);}
    public static function inspect(string $package,string $currentVersion='0.0.0'): array {
        if(!is_file($package)||filesize($package)<100)throw new RuntimeException('Update package not found or empty');$z=new ZipArchive();if($z->open($package)!==true)throw new RuntimeException('Invalid ZIP');if($z->numFiles<3||$z->numFiles>self::MAX_FILES){$z->close();throw new RuntimeException('Invalid file count');}
        $total=0;$names=[];$prefix=null;for($i=0;$i<$z->numFiles;$i++){$st=$z->statIndex($i);$n=(string)$st['name'];if(!self::safeName($n)){$z->close();throw new RuntimeException('Unsafe path in package');}$total+=(int)($st['size']??0);if($total>self::MAX_BYTES){$z->close();throw new RuntimeException('Uncompressed package too large');}$opsys=0;$attr=0;if($z->getExternalAttributesIndex($i,$opsys,$attr)&&(($attr>>16)&0170000)===0120000){$z->close();throw new RuntimeException('Symlinks are forbidden');}if(isset($names[$n])){$z->close();throw new RuntimeException('Duplicate ZIP entry');}$names[$n]=$i;if(str_ends_with($n,'update-manifest.json')){$candidate=substr($n,0,-strlen('update-manifest.json'));if($prefix!==null&&$prefix!==$candidate){$z->close();throw new RuntimeException('Multiple manifests');}$prefix=$candidate;}}
        if($prefix===null||!isset($names[$prefix.'update-signature.txt'])){$z->close();throw new RedFoxUnsignedUpdatePackage('Signed manifest is missing');}$manifestRaw=$z->getFromName($prefix.'update-manifest.json');$sigRaw=trim((string)$z->getFromName($prefix.'update-signature.txt'));$manifest=json_decode((string)$manifestRaw,true,64,JSON_THROW_ON_ERROR);if(!is_array($manifest)||($manifest['product']??'')!=='redfox'){$z->close();throw new RuntimeException('Wrong update product');}$keyId=(string)($manifest['key_id']??'primary');$channel=(string)($manifest['channel']??'stable');if(!preg_match('/^[A-Za-z0-9._-]{1,100}$/',$keyId)||!in_array($channel,['stable','beta'],true)){$z->close();throw new RuntimeException('Invalid key id or release channel');}$allowedChannel=(string)(rx_env('REDFOX_UPDATE_CHANNEL')?:'stable');if($channel!==$allowedChannel){$z->close();throw new RuntimeException("Update channel $channel is not allowed on this server");}$version=(string)($manifest['version']??'');if(!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+[A-Za-z0-9._-]*$/',$version)){$z->close();throw new RuntimeException('Invalid update version');}$min=(string)($manifest['min_current_version']??'0.0.0');if(version_compare($version,$currentVersion,'<=')){$z->close();throw new RuntimeException('Update version is not newer than current version');}if(version_compare($currentVersion,$min,'<')){$z->close();throw new RuntimeException("Current version $currentVersion is below required $min");}$files=$manifest['files']??null;if(!is_array($files)||!$files||count($files)>self::MAX_FILES){$z->close();throw new RuntimeException('Invalid manifest file map');}
        $sig=base64_decode($sigRaw,true);if(!is_string($sig)||strlen($sig)!==64){$z->close();throw new RuntimeException('Invalid signature encoding');}if(!function_exists('sodium_crypto_sign_verify_detached')){$autoload=dirname(__DIR__).'/vendor/autoload.php';if(is_file($autoload))require_once$autoload;}if(!function_exists('sodium_crypto_sign_verify_detached')||!sodium_crypto_sign_verify_detached($sig,self::canonical($manifest),self::publicKey($keyId))){$z->close();throw new RuntimeException('Update signature verification failed');}
        $allowed=[$prefix.'update-manifest.json'=>true,$prefix.'update-signature.txt'=>true];foreach($files as$rel=>$hash){$rel=(string)$rel;if(!self::safeName($rel)||str_ends_with($rel,'/')){$z->close();throw new RuntimeException('Unsafe manifest path');}$full=$prefix.$rel;if(!isset($names[$full])){$z->close();throw new RuntimeException("Manifest file missing: $rel");}$body=$z->getFromName($full);if(!is_string($body)||!hash_equals(strtolower((string)$hash),hash('sha256',$body))){$z->close();throw new RuntimeException("Hash mismatch: $rel");}$allowed[$full]=true;}
        foreach($names as$n=>$i)if(!str_ends_with($n,'/')&&!isset($allowed[$n])){$z->close();throw new RuntimeException("Unsigned extra file: $n");}$vbody=$z->getFromName($prefix.'version');if(!is_string($vbody)||trim($vbody)!==$version){$z->close();throw new RuntimeException('Version file does not match manifest');}$z->close();return['ok'=>true,'signed'=>true,'version'=>$version,'key_id'=>$keyId,'channel'=>$channel,'min_current_version'=>$min,'files'=>count($files),'bytes'=>$total,'package_sha256'=>hash_file('sha256',$package),'manifest'=>$manifest,'prefix'=>$prefix];
    }
    /**
     * Inspect an unsigned GitHub source archive.  Extracts the version from
     * the top-level ``version`` file and builds a synthetic manifest so the
     * rest of the updater pipeline can process it without Ed25519 signatures.
     */
    public static function inspectUnsigned(string $package, string $currentVersion = '0.0.0'): array {
        if (!is_file($package) || filesize($package) < 100)
            throw new RuntimeException('Update package not found or empty');
        $z = new ZipArchive();
        if ($z->open($package) !== true) throw new RuntimeException('Invalid ZIP');
        if ($z->numFiles < 3 || $z->numFiles > self::MAX_FILES) { $z->close(); throw new RuntimeException('Invalid file count'); }

        $total = 0; $names = []; $prefix = ''; $hasRootVersion = false;
        for ($i = 0; $i < $z->numFiles; $i++) {
            $st = $z->statIndex($i);
            $n = (string)$st['name'];
            if (!self::safeName($n)) { $z->close(); throw new RuntimeException('Unsafe path in package'); }
            $total += (int)($st['size'] ?? 0);
            if ($total > self::MAX_BYTES) { $z->close(); throw new RuntimeException('Uncompressed package too large'); }
            $opsys = 0; $attr = 0;
            if ($z->getExternalAttributesIndex($i, $opsys, $attr) && (($attr >> 16) & 0170000) === 0120000) {
                $z->close(); throw new RuntimeException('Symlinks are forbidden');
            }
            if (isset($names[$n])) { $z->close(); throw new RuntimeException('Duplicate ZIP entry'); }
            $names[$n] = $i;
            if ($n === 'version') $hasRootVersion = true;
        }

        // Detect prefix: if root version exists → no prefix; otherwise find dir with version
        if ($hasRootVersion) {
            $prefix = '';
        } else {
            foreach ($names as $n => $idx) {
                if (str_ends_with($n, '/version') && !str_starts_with($n, '.') && str_contains($n, '/')) {
                    $prefix = substr($n, 0, -strlen('version'));
                    break;
                }
            }
        }

        // Look for version file
        $version = '';
        $vc = $prefix . 'version';
        if (isset($names[$vc])) $version = trim((string)$z->getFromName($vc));
        if ($version === '') { $z->close(); throw new RuntimeException('Cannot determine version from unsigned package'); }
        if (!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+[A-Za-z0-9._-]*$/', $version)) {
            $z->close(); throw new RuntimeException('Invalid version in unsigned package');
        }
        if (version_compare($version, $currentVersion, '<=')) {
            $z->close(); throw new RuntimeException('Unsigned package version is not newer');
        }

        // Build synthetic file manifest from ZIP contents
        $files = [];
        foreach ($names as $n => $idx) {
            if (str_ends_with($n, '/')) continue;
            $rel = $prefix !== '' && str_starts_with($n, $prefix) ? substr($n, strlen($prefix)) : $n;
            if ($rel === '' || $rel === 'version') continue;
            if (str_starts_with($rel, '.')) continue;  // skip dotfiles
            $body = $z->getFromIndex($idx);
            $files[$rel] = hash('sha256', $body);
        }
        $z->close();

        return [
            'ok' => true,
            'signed' => false,
            'version' => $version,
            'key_id' => 'unsigned',
            'channel' => 'stable',
            'min_current_version' => '0.0.0',
            'files' => count($files),
            'bytes' => $total,
            'package_sha256' => hash_file('sha256', $package),
            'manifest' => ['product' => 'redfox', 'version' => $version, 'files' => $files],
            'prefix' => $prefix,
        ];
    }

    public static function stage(string$package,string$dest,string$currentVersion='0.0.0'):array{$info=self::inspect($package,$currentVersion);if(file_exists($dest))throw new RuntimeException('Stage destination already exists');if(!mkdir($dest,0755,true))throw new RuntimeException('Cannot create stage');$z=new ZipArchive();$z->open($package);try{foreach($info['manifest']['files']as$rel=>$hash){$body=$z->getFromName($info['prefix'].$rel);$path=$dest.'/'.$rel;$dir=dirname($path);if(!is_dir($dir)&&!mkdir($dir,0755,true))throw new RuntimeException('Cannot create directory');if(file_put_contents($path,$body,LOCK_EX)===false)throw new RuntimeException('Cannot write staged file');chmod($path,str_ends_with($rel,'.sh')?0755:0644);}file_put_contents($dest.'/.release-manifest.json',self::canonical($info['manifest']),LOCK_EX);file_put_contents($dest.'/.release-signature.txt',trim((string)$z->getFromName($info['prefix'].'update-signature.txt')),LOCK_EX);chmod($dest.'/.release-manifest.json',0444);chmod($dest.'/.release-signature.txt',0444);}catch(Throwable$e){self::removeTree($dest);$z->close();throw$e;}$z->close();return$info;}
    public static function verifyRelease(string$root):array{if(!function_exists('sodium_crypto_sign_verify_detached')){$a=dirname(__DIR__).'/vendor/autoload.php';if(is_file($a))require_once$a;}$mf=$root.'/.release-manifest.json';$sf=$root.'/.release-signature.txt';if(!is_file($mf)||!is_file($sf))return['ok'=>false,'unsigned'=>true,'errors'=>['release manifest missing']];$manifest=json_decode((string)file_get_contents($mf),true,64,JSON_THROW_ON_ERROR);$keyId=(string)($manifest['key_id']??'primary');$channel=(string)($manifest['channel']??'stable');$allowed=(string)(rx_env('REDFOX_UPDATE_CHANNEL')?:'stable');$errors=[];$sig=base64_decode(trim((string)file_get_contents($sf)),true);if($channel!==$allowed)$errors[]='release channel mismatch';if(!is_string($sig)||strlen($sig)!==64||!sodium_crypto_sign_verify_detached($sig,self::canonical($manifest),self::publicKey($keyId)))$errors[]='release signature invalid';foreach((array)($manifest['files']??[])as$rel=>$hash){$path=$root.'/'.$rel;if(!is_file($path)){$errors[]='missing: '.$rel;continue;}$actual=hash_file('sha256',$path);if(!hash_equals(strtolower((string)$hash),$actual))$errors[]='changed: '.$rel;if(count($errors)>=100)break;}return['ok'=>!$errors,'unsigned'=>false,'version'=>$manifest['version']??'','key_id'=>$keyId,'channel'=>$channel,'errors'=>$errors];}
    public static function removeTree(string$dir):void{if(!is_dir($dir))return;$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as$f){$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());}rmdir($dir);}
}
