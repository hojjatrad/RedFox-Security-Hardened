<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/Security.php';

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL {$message}\n");
    exit(1);
};

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$valid = [
    'example.com' => 'example.com',
    'EXAMPLE.COM.' => 'example.com',
    'example.com:8443' => 'example.com:8443',
    '127.0.0.1:80' => '127.0.0.1:80',
    '[2001:db8::1]:443' => '[2001:db8::1]:443',
];
foreach ($valid as $input => $expected) {
    $actual = redfox_normalize_request_host($input);
    if ($actual !== $expected) $fail("request host rejected: {$input}");
}

$invalid = [
    '', 'example.com/path', 'example.com\\path', 'user@example.com',
    'example.com?query', 'example.com#fragment', "example.com\r\nX-Test: yes",
    'bad_host', 'example.com:0', 'example.com:65536', ':443', '[bad]',
    'example.com,evil.test', 'example.com;evil.test',
];
foreach ($invalid as $input) {
    if (redfox_normalize_request_host($input) !== '') $fail("request host accepted: {$input}");
}

restore_error_handler();
echo "RUNTIME SECURITY OK hosts=" . (count($valid) + count($invalid)) . "\n";
