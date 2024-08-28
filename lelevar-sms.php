<?php
/*
Plugin Name: Lelevar SMS
Plugin URI: https://lelevar.com
Description: Lelevar SMS for WordPress streamlines the process of sending SMS messages directly from your site via the Lelevar SMS API. Seamlessly integrates with WooCommerce to deliver SMS notifications for order updates, confirmations, and more.
Author: lelevar
Version: 1.0.0
Author URI: https://lelevar.com
License: GPL2
GitHub Plugin URI: https://github.com/lelevar/lelevar-sms-wordpress
*/

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

add_action('admin_menu', 'lelevar_sms_actions');
register_activation_hook(__FILE__, 'install_smssettings_table');

function lelevar_sms_actions()
{
    add_menu_page("LelevarSMS", "Lelevar SMS", "manage_options", "lelevar_sms_menu", "sendsms_function", "dashicons-email-alt");
    add_submenu_page('lelevar_sms_menu', 'LelevarSMS', 'Send Sms', 'manage_options', 'lelevar_sms_menu', 'sendsms_function');
    add_submenu_page('lelevar_sms_menu', 'LelevarSMS', 'Outbox', 'manage_options', 'lelevar_sms_outbox', 'outbox_function');
    add_submenu_page('lelevar_sms_menu', 'LelevarSMS', 'Recipients', 'manage_options', 'lelevar_sms_recipient', 'recipient_function');
    add_submenu_page('lelevar_sms_menu', "LelevarSMS Settings", "Settings", "manage_options", "lelevar_sms_setting", "lelevar_smssettings");
}

function lelevar_smssettings()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    include_once(plugin_dir_path(__FILE__) . 'lelevar_sms_settings.php');
}

function sendsms_function()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    if (isset($_GET['idp'])) {
        $page = (int) $_GET['idp'];
        $page_inc = '';

        switch ($page) {
            case 1:
                $page_inc = 'lelevar_send_sms.php';
                break;
            case 2:
                $page_inc = 'page2.php';
                break;
            case 3:
                $page_inc = 'page3.php';
                break;
            default:
                $page_inc = 'lelevar_send_sms.php';
        }

        if ($page_inc && file_exists(plugin_dir_path(__FILE__) . $page_inc)) {
            include_once(plugin_dir_path(__FILE__) . $page_inc);
        }
    } else {
        include_once(plugin_dir_path(__FILE__) . 'lelevar_send_sms.php');
    }
}

function outbox_function()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    include_once(plugin_dir_path(__FILE__) . 'lelevar_outbox.php');
}

function recipient_function()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    include_once(plugin_dir_path(__FILE__) . 'lelevar_recipient.php');
}

include_once(plugin_dir_path(__FILE__) . 'lelevar_enforce_mobile_field.php');

function install_smssettings_table()
{
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    // Table for SMS settings
    $table_name_sms_settings = $wpdb->prefix . 'lelevar_sms_settings_table';
    $sql_sms_settings = "CREATE TABLE $table_name_sms_settings (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        apikey varchar(255) NOT NULL,
        sender_name varchar(255) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    // Table for recipient records
    $table_name_recipient = $wpdb->prefix . 'lelevar_recipient_table';
    $sql_recipient = "CREATE TABLE $table_name_recipient (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(105) NOT NULL,
        mobile varchar(105) NOT NULL,
        status varchar(105) NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY mobile (mobile)
    ) $charset_collate;";

    // Table for outbox records
    $table_name_outbox = $wpdb->prefix . 'lelevar_outbox_table';
    $sql_outbox = "CREATE TABLE $table_name_outbox (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        mobile varchar(25) NOT NULL,
        name varchar(225) NOT NULL,
        message varchar(900) NOT NULL,
        date datetime NOT NULL,
        report varchar(775) NOT NULL,
        group_id varchar(2) DEFAULT NULL,
        status varchar(105) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Execute the SQL queries
    dbDelta($sql_sms_settings);
    dbDelta($sql_recipient);
    dbDelta($sql_outbox);
}

// Check if WooCommerce is installed and active
function my_plugin_is_woocommerce_page()
{
    return true;
    // Check if it's a WooCommerce page
    if (function_exists('is_woocommerce')) {
        if (is_woocommerce() || is_cart() || is_checkout() || is_account_page()) {
            return true;
        }
    }
    return false;
}

// Function to check if WooCommerce is installed and active
function my_plugin_check_woocommerce()
{
    if (class_exists('WooCommerce')) {
        // Check if it's a WooCommerce-related page or in the admin
        if (my_plugin_is_woocommerce_page() || is_admin()) {
            // Include WooCommerce-specific functionality
            include_once plugin_dir_path(__FILE__) . 'includes/class-my-woocommerce-functions.php';

            // Check if the class is successfully loaded before using it
            if (class_exists('My_WooCommerce_Functions')) {
                // Initialize the WooCommerce functionality
                My_WooCommerce_Functions::init();
            } else {
                error_log('Class My_WooCommerce_Functions not found after including the file.');
            }
        }
    }
}
add_action('wp', 'my_plugin_check_woocommerce');
add_action('admin_init', 'my_plugin_check_woocommerce');

/**
 * Translation functions for the plugin
 */
function cltd_example_languages()
{
    load_plugin_textdomain('cltd_example', false, dirname(plugin_basename(__FILE__)));
}
add_action('init', 'cltd_example_languages');


// Hook into admin_notices to display the alert
add_action('admin_notices', 'lelevar_sms_insufficient_balance_notice');

function lelevar_sms_insufficient_balance_notice() {
    // Check if the notice should be displayed
    if (get_transient('lelevar_sms_insufficient_balance_notice')) {
        ?>
        <div class="notice notice-error">
            <p><?php _e('SMS not sent: Insufficient Unit Balance. Please top up your SMS balance.', 'lelevar_sms'); ?></p>
        </div>
        <?php
        // Delete the transient so the notice is only displayed once
        delete_transient('lelevar_sms_insufficient_balance_notice');
    }
}