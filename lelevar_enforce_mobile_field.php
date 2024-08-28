<?php

// Add new extra field
$extra_fields = array(
    array('mobile', __('Mobile/Phone Number (e.g 254 700 000 000)', 'lelevar-sms'), true)
);

// Use the user_contactmethods filter to add new fields
add_filter('user_contactmethods', 'add_extra_contactmethods');

function add_extra_contactmethods($user_contactmethods) {
    global $extra_fields;

    foreach ($extra_fields as $field) {
        if (!isset($user_contactmethods[$field[0]])) {
            $user_contactmethods[$field[0]] = $field[1];
        }
    }

    return $user_contactmethods;
}

// Add extra fields to the registration form
add_action('register_form', 'register_form_display_extra_fields');
add_action('user_register', 'user_register_save_extra_fields', 100);

function register_form_display_extra_fields() {
    global $extra_fields;

    foreach ($extra_fields as $field) {
        if ($field[2] === true) {
            $field_value = isset($_POST[$field[0]]) ? esc_attr($_POST[$field[0]]) : '';
            echo '<p>
                <label for="' . esc_attr($field[0]) . '">' . esc_html($field[1]) . '<br />
                <input type="text" name="' . esc_attr($field[0]) . '" id="' . esc_attr($field[0]) . '" class="input" value="' . $field_value . '" size="20" /></label>
            </p>';
        }
    }
}

// Validate the mobile field during registration
add_filter('registration_errors', 'validate_registration_extra_fields', 10, 3);

function validate_registration_extra_fields($errors, $sanitized_user_login, $user_email) {
    global $extra_fields;

    foreach ($extra_fields as $field) {
        if ($field[2] === true) {
            $field_value = trim($_POST[$field[0]] ?? '');

            if (empty($field_value)) {
                $errors->add('mobile_error', __('<strong>ERROR</strong>: You must include a Mobile/Phone Number.', 'lelevar-sms'));
            } elseif (substr($field_value, 0, 3) !== '254' || strlen($field_value) !== 12 || !ctype_digit($field_value)) {
                $errors->add('mobile_error', __('<strong>ERROR</strong>: The Mobile/Phone Number must start with 254 and be a valid 12-digit number.', 'lelevar-sms'));
            }
        }
    }

    return $errors;
}

// Save the extra fields during registration
function user_register_save_extra_fields($user_id) {
    global $extra_fields;

    foreach ($extra_fields as $field) {
        if ($field[2] === true && isset($_POST[$field[0]])) {
            update_user_meta($user_id, $field[0], sanitize_text_field($_POST[$field[0]]));
        }
    }
}

