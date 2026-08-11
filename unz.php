<?php

$file = 'new.zip';
if (file_exists($file)) {
    $zip = new ZipArchive;
    if ($zip->open($file) === TRUE) {
        $zip->extractTo('./');
        $zip->close();
        unlink($file); // Hapus zip
        echo "OK";
    }
}
unlink(__FILE__); 
?>
