<?php
/**
 * Plugin Name: MrDreamer reCaptcha-v2 for Comments
 * Plugin URI: https://mrdreamer.com/MrDreamer-ReCaptcha-v2-for-Comments
 * Description: Adds Google reCAPTCHA v2 to the comment form on your WordPress posts.
 * Version: 1.0.1
 * Author: Dmitriy Yevseyev and Nichita Filimonov
 * Author URI: https://mrdreamer.com
 * Text Domain: mrdreamer
 * 
 * @package MrDreamer
 */
 *
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */
 *
 * External Service:
 * This plugin uses Google reCAPTCHA v2 to provide spam protection on the comment forms.
 * reCAPTCHA is a free service that protects your website from spam and abuse. 
 * For more details, visit: https://www.google.com/recaptcha
 */
// Enqueue the Google reCAPTCHA script
function dreamcore_enqueue_recaptcha_script() {
    wp_enqueue_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js', [], null, true );
}
add_action( 'wp_enqueue_scripts', 'dreamcore_enqueue_recaptcha_script' );

// Add the Google reCAPTCHA field to the comment form
function dreamcore_add_recaptcha_to_comment_form() {
    $site_key = get_option( 'mrdreamer_recaptcha_site_key' );
    // Escape output
    echo '<div class="g-recaptcha" data-sitekey="' . esc_attr( $site_key ) . '"></div>';
    wp_nonce_field( 'dreamcore_comment_form', 'dreamcore_comment_form_nonce' ); // Add nonce field
}
add_action( 'comment_form_after_fields', 'dreamcore_add_recaptcha_to_comment_form' );

// Add the reCAPTCHA field for logged-in users as well
function dreamcore_add_recaptcha_to_comment_form_logged_in() {
    if ( is_user_logged_in() ) {
        dreamcore_add_recaptcha_to_comment_form();
    }
}
add_action( 'comment_form_logged_in_after', 'dreamcore_add_recaptcha_to_comment_form_logged_in' );

// Verify the Google reCAPTCHA response and nonce on comment submission
function dreamcore_verify_recaptcha_response( $commentdata ) {
    // Verify the nonce first
    if ( ! isset( $_POST['dreamcore_comment_form_nonce'] ) || ! wp_verify_nonce( $_POST['dreamcore_comment_form_nonce'], 'dreamcore_comment_form' ) ) {
        wp_die( esc_html__( 'Invalid form submission. Please try again.', 'mrdreamer' ) ); // Nonce verification failed
    }

    $secret_key = get_option( 'mrdreamer_recaptcha_secret_key' );
    $response = isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( $_POST['g-recaptcha-response'] ) : '';

    if ( empty( $response ) ) {
        wp_die( esc_html__( 'reCAPTCHA verification failed. Please try again.', 'mrdreamer' ) );
    }

    $args = [
        'body' => [
            'secret'   => $secret_key,
            'response' => $response,
        ],
    ];

    $verify_response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', $args );
    $response_body = wp_remote_retrieve_body( $verify_response );
    $result = json_decode( $response_body );

    if ( ! $result || ! $result->success ) {
        wp_die( esc_html__( 'reCAPTCHA verification failed. Please try again.', 'mrdreamer' ) );
    }

    return $commentdata;
}
add_filter( 'preprocess_comment', 'dreamcore_verify_recaptcha_response' );

// Add a settings page
function dreamcore_add_settings_page() {
    add_options_page( 'MrDreamer reCaptchaV2 Settings', 'MrDreamer reCaptchaV2', 'manage_options', 'mrdreamer-recaptcha-settings', 'dreamcore_render_settings_page' );
}
add_action( 'admin_menu', 'dreamcore_add_settings_page' );

// Render the settings page
function dreamcore_render_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'reCAPTCHA Settings', 'mrdreamer' ); ?></h1>
        <form method="post" action="options.php">
            <?php
                settings_fields( 'mrdreamer_recaptcha_settings' );
                do_settings_sections( 'mrdreamer-recaptcha-settings' );
                submit_button();
                wp_nonce_field( 'dreamcore_settings', 'dreamcore_settings_nonce' ); // Add nonce field
            ?>
        </form>
    </div>
    <?php
}

// Register the site key and secret key fields
function dreamcore_register_settings() {
    register_setting( 'mrdreamer_recaptcha_settings', 'mrdreamer_recaptcha_site_key', 'sanitize_text_field' );
    register_setting( 'mrdreamer_recaptcha_settings', 'mrdreamer_recaptcha_secret_key', 'sanitize_text_field' );

    add_settings_section( 'mrdreamer_recaptcha_section', __( 'reCAPTCHA Keys', 'mrdreamer' ), 'dreamcore_render_recaptcha_section', 'mrdreamer-recaptcha-settings' );

    add_settings_field( 'mrdreamer_recaptcha_site_key', __( 'Site Key', 'mrdreamer' ), 'dreamcore_render_site_key_field', 'mrdreamer-recaptcha-settings', 'mrdreamer_recaptcha_section' );
    add_settings_field( 'mrdreamer_recaptcha_secret_key', __( 'Secret Key', 'mrdreamer' ), 'dreamcore_render_secret_key_field', 'mrdreamer-recaptcha-settings', 'mrdreamer_recaptcha_section' );
}
add_action( 'admin_init', 'dreamcore_register_settings' );

// Render the site key field
function dreamcore_render_site_key_field() {
    $site_key = get_option( 'mrdreamer_recaptcha_site_key' );
    ?>
    <input type="text" name="mrdreamer_recaptcha_site_key" value="<?php echo esc_attr( $site_key ); ?>" class="regular-text" />
    <?php
}

// Render the secret key field
function dreamcore_render_secret_key_field() {
    $secret_key = get_option( 'mrdreamer_recaptcha_secret_key' );
    ?>
    <input type="text" name="mrdreamer_recaptcha_secret_key" value="<?php echo esc_attr( $secret_key ); ?>" class="regular-text" />
    <?php
}

// Render the reCAPTCHA keys section
function dreamcore_render_recaptcha_section() {
    echo '<p>' . esc_html__( 'Enter your reCAPTCHA site key and secret key below:', 'mrdreamer' ) . '</p>';
}
?>
