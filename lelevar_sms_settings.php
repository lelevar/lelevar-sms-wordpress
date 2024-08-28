<?php

// Handle form submission and save the settings
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['smsmove_hidden']) && $_POST['smsmove_hidden'] == 'Y') {

    // Save SMS Options
    if (isset($_POST['sms-option'])) {
        $send_to_customer = isset($_POST['lelevar_send_to_customer']) ? 'yes' : 'no';
        update_option('lelevar_send_to_customer', $send_to_customer);
    }

    if (isset($_POST['sms-option'])) {
        $send_to_admin = isset($_POST['lelevar_send_to_admin']) ? 'yes' : 'no';
        update_option('lelevar_send_to_admin', $send_to_admin);
    }

    if (isset($_POST['sms-option'])) {
        $send_to_shop_manager = isset($_POST['lelevar_send_to_shop_manager']) ? 'yes' : 'no';
        update_option('lelevar_send_to_shop_manager', $send_to_shop_manager);
    }

    // Save SMS Templates
    if (isset($_POST['sms-template'])) {
        $customer_message_template = sanitize_textarea_field($_POST['lelevar_customer_message_template']);
        update_option('lelevar_customer_message_template', $customer_message_template);
    }
    if (isset($_POST['sms-template'])) {
        $admin_message_template = sanitize_textarea_field($_POST['lelevar_admin_message_template']);
        update_option('lelevar_admin_message_template', $admin_message_template);
    }

    // Save SMS API settings
    if (isset($_POST['sms-setting'])) {
        $apikey = sanitize_text_field($_POST['apikey']);
        update_option('lelevar_sms_apikey', $apikey);
    }

    if (isset($_POST['sms-setting'])) {
        $sender_name = sanitize_text_field($_POST['sender_name']);
        update_option('lelevar_sms_sender_name', $sender_name);
    }

    // Output success message
    echo '<div class="updated"><p><strong>' . __('Data saved.') . '</strong></p></div>';
}

// Retrieve the saved options
$send_to_customer = get_option('lelevar_send_to_customer', 'no');
$send_to_admin = get_option('lelevar_send_to_admin', 'no');
$send_to_shop_manager = get_option('lelevar_send_to_shop_manager', 'no');
$customer_message_template = get_option('lelevar_customer_message_template', "Hi {name}, your order #{order_id} has been received, and we are getting it ready. Thank you for choosing us!");
$admin_message_template = get_option('lelevar_admin_message_template', "New Order Received: Order #{order_id} has been placed.");
$apikey = get_option('lelevar_sms_apikey', '');
$sender_name = get_option('lelevar_sms_sender_name', '');

// Display the settings form
?>
<div class="wrap">
    <h2>SMS Settings</h2>

    <h2 class="nav-tab-wrapper">
        <a href="#sms-options" class="nav-tab nav-tab-active">SMS Options</a>
        <a href="#sms-templates" class="nav-tab">SMS Templates</a>
        <a href="#sms-api" class="nav-tab">SMS API</a>
    </h2>

    <div id="sms-options" class="tab-content" style="display: block;">
        <form method="post" action="">
            <input type="hidden" name="sms-option" value="sms-option">
            <input type="hidden" name="smsmove_hidden" value="Y">

            <h3><?php _e("SMS Options", 'text-domain'); ?></h3>
            <p>
                <label><input type="checkbox" name="lelevar_send_to_customer" value="yes" <?php checked($send_to_customer, 'yes'); ?> /> Send to customer on new order</label>
            </p>
            <p>
                <label><input type="checkbox" name="lelevar_send_to_admin" value="yes" <?php checked($send_to_admin, 'yes'); ?> /> Send to admins on new order</label>
            </p>
            <p>
                <label><input type="checkbox" name="lelevar_send_to_shop_manager" value="yes" <?php checked($send_to_shop_manager, 'yes'); ?> /> Send to shop managers on new order</label>
            </p>

            <p class="submit">
                <input type="submit" name="Submit" class="button-primary" value="<?php _e('Update Options', 'text-domain') ?>" />
            </p>
        </form>
    </div>

    <div id="sms-templates" class="tab-content" style="display: none;">
        <form method="post" action="">
            <input type="hidden" name="sms-template" value="sms-template">
            <input type="hidden" name="smsmove_hidden" value="Y">

            <h3><?php _e("SMS Templates", 'text-domain'); ?></h3>
            <p><?php _e("Message to Customer Template:"); ?></p>
            <textarea name="lelevar_customer_message_template" rows="4" cols="60"><?php echo esc_textarea($customer_message_template); ?></textarea>
            <hr />
            <p><?php _e("Message to Admin and Shop Manager Template:"); ?></p>
            <textarea name="lelevar_admin_message_template" rows="4" cols="60"><?php echo esc_textarea($admin_message_template); ?></textarea>

            <p class="submit">
                <input type="submit" name="Submit" class="button-primary" value="<?php _e('Update Templates', 'text-domain') ?>" />
            </p>
        </form>
    </div>

    <div id="sms-api" class="tab-content" style="display: none;">
        <form method="post" action="">
            <input type="hidden" name="sms-api" value="sms-api">
            <input type="hidden" name="smsmove_hidden" value="Y">

            <h3><?php _e("SMS API", 'text-domain'); ?></h3>
            <p><?php _e("API Key: "); ?></p>
            <p><input type="text" name="apikey" value="<?php echo esc_attr($apikey); ?>" size="60"></p>
            <hr />
            <p><?php _e("Sender Name: "); ?></p>
            <p><input type="text" name="sender_name" value="<?php echo esc_attr($sender_name); ?>" size="60"></p>

            <p class="submit">
                <input type="submit" name="Submit" class="button-primary" value="<?php _e('Update API Settings', 'text-domain') ?>" />
            </p>
        </form>
    </div>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        $('.nav-tab').click(function(e) {
            e.preventDefault();

            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            $('.tab-content').hide();
            var activeTab = $(this).attr('href');
            $(activeTab).show();
        });
    });
</script>