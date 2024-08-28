<?php

use Lelevar\Sms\SmsService;

global $wpdb;
$table_name = $wpdb->prefix . 'lelevar_outbox_table';
$recipient_table_name = $wpdb->prefix . 'lelevar_recipient_table';
$message = '';
$notice = '';
$default = array(
    'id' => 0,
    'user_id' => 0,
    'message' => '',
    'name' => '',
    'mobile' => '',
);

if (isset($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], basename(__FILE__))) {
    $item = shortcode_atts($default, $_REQUEST);

    if ($item['id'] == 0) {
        $item_valid = validate_contact($item);
        if ($item_valid === true) {
            save_sms_to_outbox($item);
            add_contact_if_not_exists($item);
            $message = __('Message queued successfully. It will be sent shortly.', 'cltd_example');
        } else {
            $notice = $item_valid;
        }
    } else {
        $item_valid = validate_contact($item);
        if ($item_valid === true) {
            save_sms_to_outbox($item);
            add_contact_if_not_exists($item);
            $message = __('Message queued successfully. It will be sent shortly.', 'cltd_example');
        } else {
            $notice = $item_valid;
        }
    }
} else {
    $item = $default;
    if (isset($_REQUEST['id'])) {
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $_REQUEST['id']), ARRAY_A);
        if (!$item) {
            $item = $default;
            $notice = __('Item not found', 'cltd_example');
        }
    }
}

add_meta_box('sendsms_form_meta_box', 'Send SMS', 'sendsms_form_meta_box_handler', 'sendsms', 'normal', 'default');

?>

<div class="wrap">
    <div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
    <h2><?php _e('Send SMS', 'cltd_example') ?></h2>

    <?php if (!empty($notice)): ?>
        <div id="notice" class="error">
            <p><?php echo $notice ?></p>
        </div>
    <?php endif; ?>
    <?php if (!empty($message)): ?>
        <div id="message" class="updated">
            <p><?php echo $message ?></p>
        </div>
    <?php endif; ?>

    <form id="form" name="form" method="POST" onsubmit="return validateForm();">
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce(basename(__FILE__)) ?>" />
        <input type="hidden" name="id" value="<?php echo esc_attr($item['id']) ?>" />

        <div class="metabox-holder" id="poststuff">
            <div id="post-body">
                <div id="post-body-content">
                    <?php do_meta_boxes('sendsms', 'normal', $item); ?>
                    <div id="warning-div" style="display: none; padding: 10px; background-color: #ffebe8; border: 1px solid #c00; color: #c00; margin-bottom: 10px;">
                        <?php _e('Please ensure that both the name and mobile number are provided.', 'cltd_example'); ?>
                    </div>
                    <input type="submit" value="<?php _e('Send', 'cltd_example') ?>" id="submit" class="button-primary" name="submit">
                </div>
            </div>
        </div>
    </form>
</div>

<?php
function sendsms_form_meta_box_handler($item)
{
    // Fetch all users
    $users = get_users(array('fields' => array('ID', 'display_name')));

    // Create an associative array of user details for JavaScript
    $user_details = array();
    foreach ($users as $user) {
        // Retrieve first name, last name, and mobile from profile or billing address
        $first_name = get_user_meta($user->ID, 'first_name', true);
        $last_name = get_user_meta($user->ID, 'last_name', true);
        $billing_first_name = get_user_meta($user->ID, 'billing_first_name', true);
        $billing_last_name = get_user_meta($user->ID, 'billing_last_name', true);

        // Determine which name to use
        $name = $first_name ?: $billing_first_name ?: $last_name ?: $billing_last_name ?: $user->display_name;

        $user_details[$user->ID] = array(
            'name' => $name,
            'mobile' => get_user_meta($user->ID, 'mobile', true), // Assuming 'mobile' is stored as user meta
        );
    }
?>

    <table cellspacing="2" cellpadding="5" style="width: 100%;" class="form-table">
        <tbody>
            <tr class="form-field">
                <th valign="top" scope="row">
                    <label for="user_id"><?php _e('Select User', 'cltd_example') ?></label>
                </th>
                <td>
                    <select id="user_id" name="user_id" required>
                        <option value=""><?php _e('Select a user...', 'cltd_example'); ?></option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($item['user_id'], $user->ID); ?>>
                                <?php echo esc_html($user->display_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr class="form-field">
                <th valign="top" scope="row">
                    <label for="name"><?php _e('User Name', 'cltd_example') ?></label>
                </th>
                <td>
                    <input type="text" id="name" name="name" value="<?php echo esc_attr($item['name']); ?>" />
                </td>
            </tr>
            <tr class="form-field">
                <th valign="top" scope="row">
                    <label for="mobile"><?php _e('Mobile Number', 'cltd_example') ?></label>
                </th>
                <td>
                    <input type="text" id="mobile" name="mobile" value="<?php echo esc_attr($item['mobile']); ?>" />
                </td>
            </tr>
            <tr class="form-field">
                <th valign="top" scope="row">
                    <label for="message"><?php _e('Message', 'cltd_example') ?></label>
                </th>
                <td>
                    <textarea onKeyUp="count_it()" maxlength="1000" id="message" name="message" rows="4" cols="50" required><?php echo esc_attr($item['message']) ?></textarea>
                    <p id="count"></p> &emsp;&emsp;&emsp;&emsp; <p id="count2"></p>
                </td>
            </tr>
        </tbody>
    </table>

    <script type="text/javascript">
        jQuery(document).ready(function($) {
            var userDetails = <?php echo json_encode($user_details); ?>;

            $('#user_id').change(function() {
                var userId = $(this).val();
                if (userId) {
                    var name = userDetails[userId].name;
                    var mobile = userDetails[userId].mobile;
                    $('#name').val(name);
                    $('#mobile').val(mobile);

                    // Show warning div if mobile or name is missing
                    if (!mobile || !name) {
                        $('#warning-div').show();
                    } else {
                        $('#warning-div').hide();
                    }
                } else {
                    $('#name').val('');
                    $('#mobile').val('');
                    $('#warning-div').hide();
                }
            });

            // Hide warning when user starts typing in name or mobile fields
            $('#name, #mobile').on('input', function() {
                if ($('#name').val() && $('#mobile').val()) {
                    $('#warning-div').hide();
                } else {
                    $('#warning-div').show();
                }
            });
        });

        function validateForm() {
            var name = document.getElementById('name').value;
            var mobile = document.getElementById('mobile').value;

            if (!name || !mobile) {
                document.getElementById('warning-div').style.display = 'block';
                return false;
            }

            return true;
        }
    </script>

<?php
}

function validate_contact($item)
{
    $messages = array();

    if (empty($item['user_id'])) $messages[] = __('User is required', 'cltd_example');
    if (empty($item['name'])) $messages[] = __('Name is required', 'cltd_example');
    if (empty($item['mobile'])) $messages[] = __('Mobile number is required', 'cltd_example');
    if (empty($item['message'])) $messages[] = __('Message is required', 'cltd_example');

    return empty($messages) ? true : implode('<br />', $messages);
}

function save_sms_to_outbox($item)
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

function add_contact_if_not_exists($item)
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
        $result = $wpdb->insert(
            $recipient_table_name,
            array(
                'name'     => $item['name'],
                'mobile'   => $item['mobile'],
                'status'   => 'active',
            )
        );
    }
}

?>

<script>
    function count_it() {
        var el_t = document.getElementById('message');
        var length = el_t.getAttribute("maxlength");

        var el_c = document.getElementById('count');
        var count2 = document.getElementById('count2');
        el_c.innerHTML = length;

        el_t.onkeyup = function() {
            var currentchar = this.value.length;
            var numofsms = Math.ceil(currentchar / 160);
            count2.innerHTML = currentchar + " Characters";
            count.innerHTML = numofsms + " SMS";
        };
    }
</script>