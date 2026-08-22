<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL {$message}\n");
    exit(1);
};

if (!class_exists('ParagonIE_Sodium_Compat')) $fail('sodium_compat autoload');

$qr = (new \Endroid\QrCode\Builder\Builder(
    writer: new \Endroid\QrCode\Writer\PngWriter(),
    data: 'vless://runtime-dependency-check@example.test:443',
    encoding: new \Endroid\QrCode\Encoding\Encoding('UTF-8'),
    errorCorrectionLevel: \Endroid\QrCode\ErrorCorrectionLevel::Medium,
    size: 200,
    margin: 2
))->build()->getString();
if (!str_starts_with($qr, "\x89PNG\r\n\x1a\n") || strlen($qr) < 100) $fail('QR PNG generation');

$tmp = tempnam(sys_get_temp_dir(), 'rx-xlsx-');
if (!is_string($tmp)) $fail('spreadsheet temp file');
try {
    $sheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet->getActiveSheet()->setCellValue('A1', 'RedFox dependency runtime');
    \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($sheet, 'Xlsx')->save($tmp);
    $prefix = file_get_contents($tmp, false, null, 0, 2);
    if ($prefix !== 'PK' || filesize($tmp) < 1000) $fail('XLSX generation');
    $sheet->disconnectWorksheets();
} finally {
    @unlink($tmp);
}

$lock = json_decode((string)file_get_contents($root . '/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
$installed = json_decode((string)file_get_contents($root . '/vendor/composer/installed.json'), true, 512, JSON_THROW_ON_ERROR);
$locked = [];
foreach (($lock['packages'] ?? []) as $package) $locked[(string)$package['name']] = (string)$package['version'];
$bundled = [];
foreach (($installed['packages'] ?? []) as $package) $bundled[(string)$package['name']] = (string)$package['version'];
if ($locked === [] || $locked !== $bundled) $fail('composer lock/vendor parity');

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php' || str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) continue;
    if (str_contains((string)file_get_contents($file->getPathname()), 'QrCode\\Builder\\PngBuilder')) $fail('obsolete PngBuilder reference');
}

echo "RUNTIME DEPENDENCIES OK packages=" . count($locked) . " qr_bytes=" . strlen($qr) . "\n";
