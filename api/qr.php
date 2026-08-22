<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/lib/Auth.php';

function rx_qr_fail(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo $message;
    exit;
}

header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('Vary: Origin, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    rx_qr_fail(405, 'POST only');
}

$expectedOrigin = redfox_configured_origin();
$requestOrigin = rtrim(trim((string)($_SERVER['HTTP_ORIGIN'] ?? '')), '/');
if ($expectedOrigin === '' || $requestOrigin === '' || !hash_equals($expectedOrigin, $requestOrigin)) {
    rx_qr_fail(403, 'Origin denied');
}
$preAuthRate = redfox_login_rate_check('qr-preauth-ip', redfox_client_ip(), 180, 60);
if (empty($preAuthRate['allowed'])) {
    header('Retry-After: ' . max(1, (int)($preAuthRate['retry_after'] ?? 60)));
    rx_qr_fail(429, 'Rate limited');
}
$token = RedFoxAuth::extractBearerToken();
$user = is_string($token) && $token !== '' ? RedFoxAuth::userFromToken($token) : null;
$userId = is_array($user) ? (string)($user['id'] ?? '') : '';
if ($userId === '' || !ctype_digit($userId)) {
    rx_qr_fail(401, 'Authentication required');
}
if (($user['User_Status'] ?? '') === 'block') {
    rx_qr_fail(403, 'Access denied');
}

$contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''), 2)[0]));
if ($contentType !== 'application/json') {
    rx_qr_fail(415, 'application/json required');
}
$contentLength = filter_var($_SERVER['CONTENT_LENGTH'] ?? null, FILTER_VALIDATE_INT);
if ($contentLength === false || $contentLength < 2 || $contentLength > 4096) {
    rx_qr_fail(413, 'Invalid payload');
}
$raw = file_get_contents('php://input', false, null, 0, 4097);
if (!is_string($raw) || $raw === '' || strlen($raw) > 4096) {
    rx_qr_fail(400, 'Invalid payload');
}
$input = json_decode($raw, true, 8, JSON_BIGINT_AS_STRING);
if (!is_array($input) || json_last_error() !== JSON_ERROR_NONE || count($input) > 8) {
    rx_qr_fail(400, 'Invalid JSON');
}

$payload = isset($input['d']) && is_string($input['d']) ? $input['d'] : '';
if ($payload === '' || strlen($payload) > 2048) {
    rx_qr_fail(400, 'QR data is invalid');
}
$size = filter_var($input['s'] ?? 320, FILTER_VALIDATE_INT, ['options' => ['min_range' => 80, 'max_range' => 800]]);
if ($size === false) {
    rx_qr_fail(400, 'QR size is invalid');
}
$style = isset($input['style']) && is_string($input['style']) ? strtolower($input['style']) : 'plain';
if (!in_array($style, ['plain', 'fancy'], true)) {
    rx_qr_fail(400, 'QR style is invalid');
}
$currencyLabel = isset($input['cur']) && is_string($input['cur']) ? substr($input['cur'], 0, 16) : '';
$networkLabel = isset($input['net']) && is_string($input['net']) ? substr($input['net'], 0, 16) : '';
$backgroundEnabled = !array_key_exists('bg', $input) || $input['bg'] !== false;

$ipRate = redfox_login_rate_check('qr-ip', redfox_client_ip(), 120, 60);
$userRate = redfox_login_rate_check('qr-user', $userId, 60, 60);
$fancyRate = $style === 'fancy' ? redfox_login_rate_check('qr-fancy-user', $userId, 10, 60) : ['allowed' => true, 'retry_after' => 0];
if (empty($ipRate['allowed']) || empty($userRate['allowed']) || empty($fancyRate['allowed'])) {
    $retryAfter = max(1, (int)($ipRate['retry_after'] ?? 0), (int)($userRate['retry_after'] ?? 0), (int)($fancyRate['retry_after'] ?? 0));
    header('Retry-After: ' . $retryAfter);
    rx_qr_fail(429, 'Rate limited');
}

$autoloadCandidates = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    dirname(__DIR__, 2) . '/vendor/autoload.php',
];
foreach ($autoloadCandidates as $auto) {
    if (is_file($auto)) {
        require_once $auto;
        break;
    }
}

if (!class_exists('\\Endroid\\QrCode\\Builder\\Builder')) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Local QR renderer is unavailable';
    exit;
}

try {

    $qrSize = $style === 'fancy' ? 560 : $size;
    $builder = new \Endroid\QrCode\Builder\Builder(
        writer: new \Endroid\QrCode\Writer\PngWriter(),
        writerOptions: [],
        data: $payload,
        encoding: new \Endroid\QrCode\Encoding\Encoding('UTF-8'),
        errorCorrectionLevel: \Endroid\QrCode\ErrorCorrectionLevel::Medium,
        size: $qrSize,
        margin: 2,
    );
    $result = $builder->build();
    $qrBinary = $result->getString();


    if ($style === 'fancy') {
        $infocardPath = __DIR__ . '/../infocard.php';
        if (is_file($infocardPath)) {
            require_once $infocardPath;
            if (function_exists('createCryptoQrCard')) {
                $cur = $currencyLabel;
                $net = $networkLabel;
                $composite = @createCryptoQrCard($qrBinary, null, $cur, $net);
                if (is_string($composite) && $composite !== '') {
                    header('Content-Type: image/png');
                    echo $composite;
                    exit;
                }
            }
        }

    }


    $disableBg = !$backgroundEnabled;
    if (!$disableBg) {
        $projectRoot = dirname(__DIR__);
        $bgCandidates = [
            $projectRoot . '/images.jpeg',
            $projectRoot . '/images.jpg',
            $projectRoot . '/custom.jpg',
            $projectRoot . '/custom.jpeg',
        ];
        $bgPath = null;
        foreach ($bgCandidates as $cand) {
            if (is_file($cand) && is_readable($cand)) { $bgPath = $cand; break; }
        }
        if ($bgPath !== null) {
            $qrImg = @imagecreatefromstring($qrBinary);
            $bgImg = @imagecreatefromstring((string) @file_get_contents($bgPath));
            if ($qrImg !== false && $bgImg !== false) {
                $bgW = imagesx($bgImg); $bgH = imagesy($bgImg);
                $qrW = imagesx($qrImg); $qrH = imagesy($qrImg);


                $shorter   = min($bgW, $bgH);
                $targetQr  = (int) round($shorter * 0.55);
                if ($targetQr < 200) {
                    $targetQr = min(200, $shorter);
                }

                if ($qrW !== $targetQr || $qrH !== $targetQr) {
                    $resized = imagecreatetruecolor($targetQr, $targetQr);
                    $w = imagecolorallocate($resized, 255, 255, 255);
                    imagefill($resized, 0, 0, $w);
                    imagecopyresampled(
                        $resized, $qrImg,
                        0, 0, 0, 0,
                        $targetQr, $targetQr,
                        $qrW, $qrH
                    );
                    imagedestroy($qrImg);
                    $qrImg = $resized;
                    $qrW = $qrH = $targetQr;
                }


                $padding = (int) round($targetQr * 0.10);
                $boxSize = $targetQr + ($padding * 2);
                $boxX    = (int) (($bgW - $boxSize) / 2);
                $boxY    = (int) (($bgH - $boxSize) / 2);


                $boxClipX = max(0, $boxX);
                $boxClipY = max(0, $boxY);
                $boxClipW = min($boxSize, $bgW - $boxClipX);
                $boxClipH = min($boxSize, $bgH - $boxClipY);


                $bgBackup = imagecreatetruecolor($boxClipW, $boxClipH);
                if ($bgBackup !== false) {
                    imagecopy($bgBackup, $bgImg, 0, 0, $boxClipX, $boxClipY, $boxClipW, $boxClipH);
                }

                $glass = imagecreatetruecolor($boxClipW, $boxClipH);
                if ($glass !== false) {
                    imagecopy($glass, $bgImg, 0, 0, $boxClipX, $boxClipY, $boxClipW, $boxClipH);


                    for ($i = 0; $i < 22; $i++) {
                        @imagefilter($glass, IMG_FILTER_GAUSSIAN_BLUR);
                    }


                    $totalLum = 0;
                    $samples  = 5;
                    for ($sx = 0; $sx < $samples; $sx++) {
                        for ($sy = 0; $sy < $samples; $sy++) {
                            $px = imagecolorat(
                                $glass,
                                (int) ($boxClipW * ($sx + 0.5) / $samples),
                                (int) ($boxClipH * ($sy + 0.5) / $samples)
                            );
                            $r = ($px >> 16) & 0xFF;
                            $g = ($px >>  8) & 0xFF;
                            $b =  $px        & 0xFF;
                            $totalLum += (0.299 * $r + 0.587 * $g + 0.114 * $b);
                        }
                    }
                    $avgLum = $totalLum / ($samples * $samples);


                    if     ($avgLum < 70)  { $opacity = 55; $bBoost = 16; $tintR = 255; $tintG = 255; $tintB = 255; $needInnerLine = false; }
                    elseif ($avgLum < 130) { $opacity = 45; $bBoost = 12; $tintR = 255; $tintG = 255; $tintB = 255; $needInnerLine = false; }
                    elseif ($avgLum < 190) { $opacity = 38; $bBoost = 8;  $tintR = 255; $tintG = 255; $tintB = 255; $needInnerLine = false; }
                    elseif ($avgLum < 230) { $opacity = 30; $bBoost = 4;  $tintR = 245; $tintG = 248; $tintB = 252; $needInnerLine = true;  }
                    else                   { $opacity = 55; $bBoost = -2; $tintR = 220; $tintG = 230; $tintB = 245; $needInnerLine = true;  }

                    if ($bBoost !== 0) {
                        @imagefilter($glass, IMG_FILTER_BRIGHTNESS, $bBoost);
                    }


                    $overlay = imagecreatetruecolor($boxClipW, $boxClipH);
                    $oTint   = imagecolorallocate($overlay, $tintR, $tintG, $tintB);
                    imagefill($overlay, 0, 0, $oTint);
                    imagecopymerge($glass, $overlay, 0, 0, 0, 0, $boxClipW, $boxClipH, $opacity);
                    imagedestroy($overlay);


                    if ($needInnerLine) {
                        $inner = imagecolorallocatealpha($glass, 80, 100, 130, 95);
                        if ($inner !== false) {
                            imagerectangle($glass, 2, 2, $boxClipW - 3, $boxClipH - 3, $inner);
                        }
                    }


                    $radius = (int) round(min($boxClipW, $boxClipH) * 0.10);
                    if ($radius > 4 && $bgBackup !== false) {
                        $r2 = $radius * $radius;
                        $corners = [
                            ['cx' => $radius - 1,         'cy' => $radius - 1,         'sx' => 0,                  'sy' => 0,                  'ex' => $radius,    'ey' => $radius],
                            ['cx' => $boxClipW - $radius, 'cy' => $radius - 1,         'sx' => $boxClipW - $radius, 'sy' => 0,                  'ex' => $boxClipW,  'ey' => $radius],
                            ['cx' => $radius - 1,         'cy' => $boxClipH - $radius, 'sx' => 0,                  'sy' => $boxClipH - $radius, 'ex' => $radius,    'ey' => $boxClipH],
                            ['cx' => $boxClipW - $radius, 'cy' => $boxClipH - $radius, 'sx' => $boxClipW - $radius, 'sy' => $boxClipH - $radius, 'ex' => $boxClipW,  'ey' => $boxClipH],
                        ];
                        foreach ($corners as $c) {
                            for ($x = $c['sx']; $x < $c['ex']; $x++) {
                                for ($y = $c['sy']; $y < $c['ey']; $y++) {
                                    $dx = $x - $c['cx'];
                                    $dy = $y - $c['cy'];
                                    if ($dx * $dx + $dy * $dy > $r2) {
                                        imagesetpixel($glass, $x, $y, imagecolorat($bgBackup, $x, $y));
                                    }
                                }
                            }
                        }
                    }


                    imagecopy($bgImg, $glass, $boxClipX, $boxClipY, 0, 0, $boxClipW, $boxClipH);
                    imagedestroy($glass);
                } else {
                    $whiteBox = imagecolorallocate($bgImg, 255, 255, 255);
                    imagefilledrectangle($bgImg, $boxX, $boxY, $boxX + $boxSize, $boxY + $boxSize, $whiteBox);
                }
                if ($bgBackup !== false) { imagedestroy($bgBackup); }

                $x = (int) (($bgW - $qrW) / 2);
                $y = (int) (($bgH - $qrH) / 2);
                imagecopy($bgImg, $qrImg, $x, $y, 0, 0, $qrW, $qrH);

                ob_start();
                imagepng($bgImg);
                $merged = ob_get_clean();
                imagedestroy($qrImg);
                imagedestroy($bgImg);
                if ($merged !== false && $merged !== '') {
                    header('Content-Type: image/png');
                    echo $merged;
                    exit;
                }
            }
            if ($qrImg) imagedestroy($qrImg);
            if ($bgImg) imagedestroy($bgImg);
        }
    }

    header('Content-Type: ' . $result->getMimeType());
    echo $qrBinary;
} catch (Throwable $e) {
    rx_log_event_structured('error', 'qr_render_failed', ['exception' => get_class($e), 'code' => (string)$e->getCode()]);
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'QR rendering failed';
    exit;
}

