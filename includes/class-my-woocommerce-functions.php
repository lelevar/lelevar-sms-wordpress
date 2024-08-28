<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

use Lelevar\Sms\SmsService;

class My_WooCommerce_Functions
{
    private static $instance = null;

    private function __construct()
    {
        // Initialize WooCommerce hooks here
        error_log('My_WooCommerce_Functions Init');
        add_action('woocommerce_update_order', [$this, 'lelevar_wc_update_order'], 10, 1);
        add_action('woocommerce_thankyou', [$this, 'lelevar_wc_thankyou'], 10, 1);
    }

    public static function init()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
    }

    public static function lelevar_wc_update_order($order_id)
    {
        error_log("Order #{$order_id} woocommerce_update_order");
        $order = wc_get_order($order_id);
        error_log("Order #{$order}");
        error_log("-------------------------------");
    }

    public function lelevar_wc_thankyou($order_id)
    {
        if (!$order_id) {
            return;
        }

        // Get order details
        $order = wc_get_order($order_id);

        // Check if the SMS has already been sent
        if ($order->get_meta('_sms_sent')) {
            // SMS has already been sent, no need to send again
            error_log("SMS has already been queued, no need to send again");
            return;
        }

        // Retrieve the billing phone number
        $phone = $order->get_billing_phone();

        // If billing phone is not available, try getting the customer mobile number from user meta
        if (!$phone) {
            $customer_id = $order->get_user_id(); // Get the customer/user ID associated with the order
            if ($customer_id) {
                $phone = get_user_meta($customer_id, 'mobile', true); // Assuming 'mobile' is stored as user meta
            }
        }

        // If neither phone number is available, log this or handle it differently
        if (!$phone) {
            error_log("No phone number available for order #{$order_id}");
            return;
        }

        // Get the customer's name
        $f_name = $order->get_billing_first_name();
        $l_name = $order->get_billing_last_name();
        $a_name = $f_name ?? $l_name;
        $name = $f_name . ' ' . $l_name;

        // Get the settings options
        $send_to_customer = get_option('lelevar_send_to_customer', 'yes');
        $send_to_admin = get_option('lelevar_send_to_admin', 'yes');
        $send_to_shop_manager = get_option('lelevar_send_to_shop_manager', 'yes');
        $customer_message_template = get_option('lelevar_customer_message_template', "Hi {name}, your order #{order_id} has been received, and we are getting it ready. Thank you for choosing us!");
        $admin_message_template = get_option('lelevar_admin_message_template', "New Order Received: Order #{order_id} has been placed.");

        // Replace placeholders in the customer message template
        $message = str_replace(['{name}', '{order_id}'], [$a_name, $order_id], $customer_message_template);

        if ($send_to_customer === 'yes') {
            // Save the SMS to the outbox for the customer
            $item = array(
                'mobile'   => $phone,
                'name'     => $name, // Use the customer's name
                'message'  => $message,
            );
            $this->save_sms_to_outbox($item);
            $this->add_contact_if_not_exists($item);

            // Log the message (for debugging)
            error_log($message . " sent to customer " . $phone);
            error_log("-------------------------------");
        }

        if ($send_to_admin === 'yes' || $send_to_shop_manager === 'yes') {
            // Send the SMS to all administrators and shop managers
            $message_admin = str_replace('{order_id}', $order_id, $admin_message_template);
            $this->send_sms_to_admins_and_managers($message_admin, $send_to_admin, $send_to_shop_manager);
        }

        // Mark this order as having had the SMS sent
        $order->update_meta_data('_sms_sent', true);
        $order->save(); // Ensure the meta data is saved
    }

    private function send_sms_to_admins_and_managers($message, $send_to_admin, $send_to_shop_manager)
    {
        // Determine which roles to target based on settings
        $roles = [];
        if ($send_to_admin === 'yes') {
            $roles[] = 'administrator';
        }
        if ($send_to_shop_manager === 'yes') {
            $roles[] = 'shop_manager';
        }

        if (empty($roles)) {
            return;
        }

        $users = get_users(array(
            'role__in' => $roles,
            'fields' => array('ID', 'display_name'),
        ));

        foreach ($users as $user) {
            // Get the mobile number from the user meta
            $user_mobile = get_user_meta($user->ID, 'mobile', true);

            // If mobile number is available, send SMS
            if ($user_mobile) {
                $item = array(
                    'mobile'   => $user_mobile,
                    'name'     => $user->display_name,
                    'message'  => $message,
                );
                $this->save_sms_to_outbox($item);
                $this->add_contact_if_not_exists($item);

                // Log the message (for debugging)
                error_log($message . " sent to user " . $user->display_name . " with role " . implode(', ', $roles) . " at " . $user_mobile);
            } else {
                // Log if no mobile number is found for the user
                error_log("No mobile number found for user " . $user->display_name . " with role " . implode(', ', $roles));
            }
        }
    }

    private function save_sms_to_outbox($item)
    {
        global $wpdb;

        $apikey = get_option('lelevar_sms_apikey', '');
        $sender_name = get_option('lelevar_sms_sender_name', '');
        $name = $item['name'];
        $mobile = $item['mobile'];
        $message = $item['message'];
        $status = "pending";

        if ($apikey && $sender_name) {
            // Initialize the SMS service with your API key
            $smsService = new SmsService($apikey); // Replace with your actual API key
            // Define the parameters for the SMS
            $params = [
                'mobile' => $mobile, // Recipient's mobile number
                'content' => $message, // SMS content
                'sender_name' => $sender_name // Optional: Define sender name if needed
            ];
            // Send the SMS
            $response = $smsService->sendSms($params);

            try {
                if ($response->success && isset($response->data['status']) && !$response->data['status']) {
                    if ($response->data['message'] === "Insufficient Unit Balance") {
                        add_action('admin_notices', function () {
                        ?>
                            <div class="notice notice-error is-dismissible">
                                <p><?php _e('Error: Insufficient Unit Balance for sending SMS.', 'your-text-domain'); ?></p>
                            </div>
                        <?php
                        });
                    }
                }

                // Log the response object for debugging purposes
                error_log(print_r($response, true));

                if ($response->success && isset($response->data['status']) && !$response->data['status']) {
                    if ($response->data['message'] === "Insufficient Unit Balance") {
                        // Set a transient to store the alert message (useful to persist across page reloads)
                        set_transient('lelevar_sms_insufficient_balance_notice', true, 30);
                    }
                }
            } catch (\Throwable $th) {
                //throw $th;
            }
            
            // Handle the response
            if ($response->success) {
                $status = "sent";
            } else {
                $status = "failed";
            }
        }

        // Save the details into the outbox table
        $wpdb->insert(
            $wpdb->prefix . 'lelevar_outbox_table',
            array(
                'mobile'   => $mobile,
                'name'     => $name,
                'message'  => $message,
                'status'   => $status,
                'date'     => current_time('mysql'),
            )
        );
    }

    private function add_contact_if_not_exists($item)
    {
        global $wpdb;
        $recipient_table_name = $wpdb->prefix . 'lelevar_recipient_table';

        // Check if the contact already exists using mobile number
        $contact_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $recipient_table_name WHERE mobile = %s",
            $item['mobile']
        ));

        // If the contact does not exist, add it
        if ($contact_exists == 0) {
            $wpdb->insert(
                $recipient_table_name,
                array(
                    'name'     => $item['name'],
                    'mobile'   => $item['mobile'],
                    'status'   => 'active',
                )
            );
        }
    }

    // Avoid cloning or unserialization
    private function __clone() {}
    public function __wakeup() {}
}
