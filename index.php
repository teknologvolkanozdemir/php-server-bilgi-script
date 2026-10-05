<?php
declare(strict_types=1);

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function displayValue($value): string
{
    if (is_bool($value)) {
        return $value ? 'Etkin' : 'Devre dışı';
    }

    if ($value === false || $value === null || $value === '') {
        return 'Bilgi alınamadı';
    }

    return (string) $value;
}

function readSystemMemory(): array
{
    $memory = [];
    if (!function_exists('fopen') || !function_exists('fgets') || !function_exists('fclose')) {
        return $memory;
    }

    $file = @fopen('/proc/meminfo', 'r');

    if ($file === false) {
        return $memory;
    }

    $labels = [
        'MemTotal' => 'Toplam bellek',
        'MemAvailable' => 'Kullanılabilir bellek',
        'MemFree' => 'Boş bellek',
        'SwapTotal' => 'Toplam takas alanı',
        'SwapFree' => 'Boş takas alanı',
    ];
    while (($line = fgets($file)) !== false) {
        if (preg_match('/^(MemTotal|MemAvailable|MemFree|SwapTotal|SwapFree):\s+(\d+)\s+kB$/', $line, $matches)) {
            $memory[$labels[$matches[1]]] = number_format((int) $matches[2] / 1024, 0) . ' MB';
        }
    }

    fclose($file);
    return $memory;
}

function readServiceProcesses(): array
{
    $processes = [];
    if (!function_exists('scandir') || !function_exists('file_get_contents')) {
        return $processes;
    }

    $entries = @scandir('/proc');

    if ($entries === false) {
        return $processes;
    }

    foreach ($entries as $entry) {
        if (preg_match('/\A[0-9]+\z/', $entry) !== 1) {
            continue;
        }

        $name = @file_get_contents('/proc/' . $entry . '/comm');
        if ($name !== false) {
            $processes[] = trim($name);
        }
    }

    return $processes;
}

function checkLocalPort(int $port): ?bool
{
    if (!function_exists('fsockopen')) {
        return null;
    }

    $errorCode = 0;
    $errorMessage = '';
    $socket = @fsockopen('127.0.0.1', $port, $errorCode, $errorMessage, 0.2);

    if ($socket === false) {
        return false;
    }

    fclose($socket);
    return true;
}

$expectedUser = getenv('SERVER_INFO_USERNAME');
$expectedPassword = getenv('SERVER_INFO_PASSWORD');
$hasCredentials = $expectedUser !== false
    && $expectedPassword !== false
    && $expectedUser !== ''
    && $expectedPassword !== '';
$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocalRequest = in_array($remoteAddress, ['127.0.0.1', '::1'], true);
$isSecureRequest = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

if ($hasCredentials) {
    if (!$isLocalRequest && !$isSecureRequest) {
        http_response_code(403);
        exit('Uzaktan kimlik doğrulama için HTTPS gereklidir.');
    }

    $providedUser = $_SERVER['PHP_AUTH_USER'] ?? '';
    $providedPassword = $_SERVER['PHP_AUTH_PW'] ?? '';
    $authorized = function_exists('hash_equals')
        && hash_equals((string) $expectedUser, (string) $providedUser)
        && hash_equals((string) $expectedPassword, (string) $providedPassword);

    if (!$authorized) {
        header('WWW-Authenticate: Basic realm="Sunucu Bilgi Raporu"');
        http_response_code(401);
        exit('Bu rapora erişmek için kimlik doğrulaması gereklidir.');
    }
} elseif (!$isLocalRequest) {
    http_response_code(403);
    exit('Uzaktan erişim kapalı. Uzak erişim için SERVER_INFO_USERNAME ve SERVER_INFO_PASSWORD ortam değişkenlerini ayarlayın.');
}

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate');

$extensions = get_loaded_extensions();
sort($extensions, SORT_NATURAL | SORT_FLAG_CASE);

$disabledFunctions = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
$disabledFunctions = $disabledFunctions === [] ? ['Yok'] : $disabledFunctions;

$phpSettings = [
    'PHP sürümü' => PHP_VERSION,
    'PHP SAPI' => PHP_SAPI,
    'İşletim sistemi' => PHP_OS . ' (' . (function_exists('php_uname') ? php_uname('m') : 'mimari bilinmiyor') . ')',
    'Çekirdek sürümü' => function_exists('php_uname') ? php_uname('r') : 'Bilgi alınamadı',
    'Sunucu yazılımı' => $_SERVER['SERVER_SOFTWARE'] ?? 'Bilgi alınamadı',
    'İstek şeması' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'HTTPS' : 'HTTP',
    'Sunucu saat dilimi' => date_default_timezone_get(),
    'PHP integer boyutu' => (string) (PHP_INT_SIZE * 8) . ' bit',
    'Yüklü PHP uzantısı sayısı' => (string) count($extensions),
    'Bellek limiti' => ini_get('memory_limit'),
    'Maksimum çalışma süresi' => ini_get('max_execution_time') . ' saniye',
    'Maksimum yükleme boyutu' => ini_get('upload_max_filesize'),
    'Maksimum POST boyutu' => ini_get('post_max_size'),
    'Varsayılan soket zaman aşımı' => ini_get('default_socket_timeout') . ' saniye',
    'Hata gösterimi' => (bool) ini_get('display_errors'),
    'Hata günlüğü' => (bool) ini_get('log_errors'),
    'PHP sürüm başlığını gösterme' => (bool) ini_get('expose_php'),
    'URL üzerinden dosya açma' => (bool) ini_get('allow_url_fopen'),
    'Oturum kaydetme yöntemi' => ini_get('session.save_handler'),
    'Devre dışı bırakılmış fonksiyonlar' => implode(', ', $disabledFunctions),
];

$systemInformation = [
    'İşlemci yük ortalaması (1/5/15 dk)' => function_exists('sys_getloadavg')
        ? implode(' / ', array_map(static fn ($load): string => number_format((float) $load, 2), sys_getloadavg()))
        : 'Desteklenmiyor',
    'Çalışan işlem sayısı' => (string) count(readServiceProcesses()),
];

$uptime = function_exists('file_get_contents') ? @file_get_contents('/proc/uptime') : false;
if ($uptime !== false && preg_match('/^([0-9.]+)/', $uptime, $matches)) {
    $seconds = (int) $matches[1];
    $systemInformation['Sunucu çalışma süresi'] = sprintf(
        '%d gün %d saat %d dakika',
        intdiv($seconds, 86400),
        intdiv($seconds % 86400, 3600),
        intdiv($seconds % 3600, 60)
    );
}

$systemInformation = array_merge($systemInformation, readSystemMemory());
$freeDiskSpace = function_exists('disk_free_space') ? @disk_free_space(__DIR__) : false;
$totalDiskSpace = function_exists('disk_total_space') ? @disk_total_space(__DIR__) : false;
if ($freeDiskSpace !== false && $totalDiskSpace !== false) {
    $systemInformation['Script dizinindeki kullanılabilir disk'] = number_format($freeDiskSpace / 1073741824, 2) . ' GB / '
        . number_format($totalDiskSpace / 1073741824, 2) . ' GB';
}

$processes = readServiceProcesses();
$services = [
    ['SSH', 22, ['sshd', 'dropbear']],
    ['FTP', 21, ['vsftpd', 'proftpd', 'pure-ftpd']],
    ['SMTP', 25, ['master', 'sendmail']],
    ['DNS (TCP)', 53, ['named', 'unbound', 'dnsmasq']],
    ['HTTP', 80, ['apache2', 'httpd', 'nginx']],
    ['HTTPS', 443, ['apache2', 'httpd', 'nginx']],
    ['Alternatif HTTP', 8080, ['apache2', 'httpd', 'nginx']],
    ['Alternatif HTTPS', 8443, ['apache2', 'httpd', 'nginx']],
    ['MySQL / MariaDB', 3306, ['mysqld', 'mariadbd']],
    ['PostgreSQL', 5432, ['postgres']],
    ['Redis', 6379, ['redis-server']],
    ['Memcached', 11211, ['memcached']],
    ['PHP-FPM', 9000, ['php-fpm', 'php-fpm8.3', 'php-fpm8.2']],
    ['Elasticsearch', 9200, []],
    ['MongoDB', 27017, ['mongod']],
    ['RabbitMQ', 5672, ['beam.smp']],
    ['Kafka', 9092, []],
];

?><!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sunucu Bilgi Raporu</title>
    <style>
        :root { color-scheme: light; font-family: system-ui, sans-serif; color: #172033; background: #f3f5f8; }
        body { max-width: 1100px; margin: 0 auto; padding: 24px; }
        h1 { margin-bottom: 8px; }
        h2 { margin: 0 0 12px; font-size: 1.2rem; }
        .note { color: #536078; line-height: 1.5; }
        section { margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #dce2eb; border-radius: 10px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 12px; border-bottom: 1px solid #e5e9f0; text-align: left; vertical-align: top; }
        th { background: #f7f8fa; }
        tr:last-child td { border-bottom: 0; }
        .status { font-weight: 650; }
        .running { color: #137547; }
        .missing { color: #8a5a00; }
        .unknown { color: #536078; }
        .tag { display: inline-block; margin: 3px; padding: 5px 9px; background: #edf1f7; border-radius: 6px; }
        footer { color: #657087; font-size: .9rem; padding: 4px 2px 24px; }
        @media (max-width: 600px) { body { padding: 12px; } section { padding: 14px; } th, td { padding: 8px; } }
    </style>
</head>
<body>
    <h1>Sunucu Bilgi Raporu</h1>
    <p class="note">Rapor oluşturulma zamanı: <?= escapeHtml(date(DATE_ATOM)) ?>. Bilgiler PHP işleminin görebildiği sistemle sınırlıdır.</p>

    <section>
        <h2>PHP ve web sunucusu</h2>
        <div class="table-wrap"><table>
            <tbody>
            <?php foreach ($phpSettings as $label => $value): ?>
                <tr><th><?= escapeHtml($label) ?></th><td><?= escapeHtml(displayValue($value)) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </section>

    <section>
        <h2>Sistem bilgileri</h2>
        <div class="table-wrap"><table>
            <tbody>
            <?php foreach ($systemInformation as $label => $value): ?>
                <tr><th><?= escapeHtml((string) $label) ?></th><td><?= escapeHtml(displayValue($value)) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </section>

    <section>
        <h2>Yüklü PHP uzantıları (<?= count($extensions) ?>)</h2>
        <?php foreach ($extensions as $extension): ?><span class="tag"><?= escapeHtml($extension) ?></span><?php endforeach; ?>
    </section>

    <section>
        <h2>Yaygın servisler</h2>
        <p class="note">Portlar yalnızca bu sunucunun 127.0.0.1 adresinde kısa bir TCP bağlantısıyla kontrol edilir. “Tespit edilemedi”, servisin kesinlikle kapalı olduğu anlamına gelmez; servis farklı portta, başka adreste veya PHP erişim kısıtları altında çalışıyor olabilir.</p>
        <div class="table-wrap"><table>
            <thead><tr><th>Servis</th><th>Yerel port</th><th>Durum</th><th>İşlem ipucu</th></tr></thead>
            <tbody>
            <?php foreach ($services as [$name, $port, $processNames]):
                $portStatus = checkLocalPort($port);
                $matchingProcesses = array_values(array_unique(array_intersect($processNames, $processes)));
                if ($portStatus === true) {
                    $status = 'Erişilebilir (çalışıyor)';
                    $statusClass = 'running';
                } elseif ($matchingProcesses !== []) {
                    $status = 'İşlem görüldü; port erişilemiyor';
                    $statusClass = 'unknown';
                } elseif ($portStatus === null) {
                    $status = 'Kontrol edilemedi';
                    $statusClass = 'unknown';
                } else {
                    $status = 'Tespit edilemedi';
                    $statusClass = 'missing';
                }
                ?>
                <tr>
                    <td><?= escapeHtml($name) ?></td>
                    <td>TCP <?= (int) $port ?></td>
                    <td class="status <?= escapeHtml($statusClass) ?>"><?= escapeHtml($status) ?></td>
                    <td><?= escapeHtml($matchingProcesses === [] ? 'İşlem bilgisi yok' : implode(', ', $matchingProcesses)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </section>

    <footer>Bu rapor ortam değişkenlerini, istek başlıklarını, parolaları veya PHP yapılandırma dosyalarının içeriklerini göstermez. Kullanım tamamlanınca dosyayı sunucudan kaldırın.</footer>
</body>
</html>
