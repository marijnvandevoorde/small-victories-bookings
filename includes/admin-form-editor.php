<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
$form = null;
$options = array();

if ($form_id) {
    $form = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}timeslot_forms WHERE id = %d",
        $form_id
    ));
    $options = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}timeslot_options WHERE form_id = %d ORDER BY sort_order ASC",
        $form_id
    ));
}

$fields = [];
if ($form && !empty($form->fields)) {
    $fields = json_decode($form->fields, true);
}
if (empty($fields)) {
    $fields = [
        ['label' => 'Name', 'required' => true, 'type' => 'text'],
        ['label' => 'Email', 'required' => true, 'type' => 'email']
    ];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['timeslot_form_nonce'])) {
    if (!wp_verify_nonce($_POST['timeslot_form_nonce'], 'timeslot_form_save')) {
        wp_die('Security check failed');
    }

    $form_name = sanitize_text_field($_POST['form_name']);
    $columns = intval($_POST['columns']);
    $email_template = sanitize_textarea_field($_POST['email_template']);

    // Process custom fields
    $custom_fields = [];
    if (isset($_POST['fields'])) {
        foreach ($_POST['fields'] as $field_data) {
            if (empty($field_data['label'])) continue;
            $custom_fields[] = [
                'label' => sanitize_text_field($field_data['label']),
                'required' => isset($field_data['required']),
                'type' => sanitize_text_field($field_data['type'])
            ];
        }
    }
    $fields_json = json_encode($custom_fields);

    if ($form_id) {
        // Update existing form
        $wpdb->update(
            $wpdb->prefix . 'timeslot_forms',
            array('name' => $form_name, 'columns' => $columns, 'fields' => $fields_json, 'email_template' => $email_template),
            array('id' => $form_id),
            array('%s', '%d', '%s', '%s'),
            array('%d')
        );
    } else {
        // Create new form with share key
        $share_key = wp_generate_uuid4();
        $wpdb->insert(
            $wpdb->prefix . 'timeslot_forms',
            array('name' => $form_name, 'columns' => $columns, 'fields' => $fields_json, 'email_template' => $email_template, 'share_key' => $share_key, 'created_at' => current_time('mysql')),
            array('%s', '%d', '%s', '%s', '%s', '%s')
        );
        $form_id = $wpdb->insert_id;
    }

    // Handle batch import (only for new forms or when explicitly used)
    if (!empty($_POST['batch_import']) && !empty(trim($_POST['batch_import']))) {
        $lines = explode("\n", $_POST['batch_import']);
        $sort_order = 0;

        // Only delete existing options if this is a batch import
        $wpdb->delete($wpdb->prefix . 'timeslot_options', array('form_id' => $form_id), array('%d'));

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $parts = explode(';', $line);
            if (count($parts) !== 2) continue;

            $option_name = trim($parts[0]);
            $availability = intval(trim($parts[1]));

            if (empty($option_name)) continue;

            $wpdb->insert(
                $wpdb->prefix . 'timeslot_options',
                array(
                    'form_id' => $form_id,
                    'name' => $option_name,
                    'availability' => $availability,
                    'sort_order' => $sort_order++
                ),
                array('%d', '%s', '%d', '%d')
            );
        }
    } elseif (isset($_POST['options'])) {
        // Handle individual option updates - UPDATE existing, INSERT new, DELETE removed
        $existing_option_ids = array();
        $submitted_option_ids = array();

        $sort_order = 0;
        foreach ($_POST['options'] as $option_data) {
            if (empty($option_data['name'])) continue;

            $option_id = isset($option_data['id']) ? intval($option_data['id']) : 0;

            if ($option_id > 0) {
                // Update existing option
                $wpdb->update(
                    $wpdb->prefix . 'timeslot_options',
                    array(
                        'name' => sanitize_text_field($option_data['name']),
                        'availability' => intval($option_data['availability']),
                        'sort_order' => $sort_order
                    ),
                    array('id' => $option_id),
                    array('%s', '%d', '%d'),
                    array('%d')
                );
                $submitted_option_ids[] = $option_id;
            } else {
                // Insert new option
                $wpdb->insert(
                    $wpdb->prefix . 'timeslot_options',
                    array(
                        'form_id' => $form_id,
                        'name' => sanitize_text_field($option_data['name']),
                        'availability' => intval($option_data['availability']),
                        'sort_order' => $sort_order
                    ),
                    array('%d', '%s', '%d', '%d')
                );
                $submitted_option_ids[] = $wpdb->insert_id;
            }

            $sort_order++;
        }

        // Delete options that were removed (not in submitted list)
        if (!empty($submitted_option_ids)) {
            $placeholders = implode(',', array_fill(0, count($submitted_option_ids), '%d'));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}timeslot_options WHERE form_id = %d AND id NOT IN ($placeholders)",
                array_merge(array($form_id), $submitted_option_ids)
            ));
        } else {
            // All options were removed
            $wpdb->delete($wpdb->prefix . 'timeslot_options', array('form_id' => $form_id), array('%d'));
        }
    }

    echo '<div class="notice notice-success"><p>Form saved successfully!</p></div>';

    // Reload form data
    $form = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}timeslot_forms WHERE id = %d",
        $form_id
    ));
    $options = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}timeslot_options WHERE form_id = %d ORDER BY sort_order ASC",
        $form_id
    ));

    // Reload fields
    $fields = [];
    if ($form && !empty($form->fields)) {
        $fields = json_decode($form->fields, true);
    }
}

$form_name = $form ? $form->name : '';
$columns = $form ? $form->columns : 2;
$email_template = $form ? $form->email_template : '';
?>

<div class="wrap">
    <h1><?php echo $form_id ? 'Edit Form' : 'Add New Form'; ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field('timeslot_form_save', 'timeslot_form_nonce'); ?>

        <table class="form-table">
            <tr>
                <th><label for="form_name">Form Name</label></th>
                <td>
                    <input type="text" name="form_name" id="form_name" value="<?php echo esc_attr($form_name); ?>" class="regular-text" required>
                </td>
            </tr>
            <tr>
                <th><label for="columns">Number of Columns</label></th>
                <td>
                    <input type="number" name="columns" id="columns" value="<?php echo esc_attr($columns); ?>" min="1" max="6" required>
                    <p class="description">How many columns to display options in.</p>
                </td>
            </tr>
            <tr>
                <th><label for="email_template">Email Template</label></th>
                <td>
                    <textarea name="email_template" id="email_template" rows="8" class="large-text code"><?php echo esc_textarea($email_template); ?></textarea>
                    <p class="description">This text will be sent in the confirmation email to the user, followed by the list of selected options. Leave empty to disable email notifications.</p>
                </td>
            </tr>
        </table>

        <h2>Form Fields</h2>
        <p>Define the custom fields for your form:</p>
        <div id="fields-container" style="margin-bottom: 20px;">
            <?php if (!empty($fields)): ?>
                <?php foreach ($fields as $index => $field): ?>
                    <div class="field-row" style="margin-bottom: 15px; padding: 15px; background: #f9f9f9; border-left: 3px solid #2271b1;">
                        <div style="display: flex; gap: 15px; align-items: center; margin-bottom: 10px;">
                            <input type="text" name="fields[<?php echo $index; ?>][label]" value="<?php echo esc_attr($field['label']); ?>" placeholder="Field Label" class="regular-text" required>
                            <select name="fields[<?php echo $index; ?>][type]" style="width: 150px;">
                                <option value="text" <?php selected($field['type'], 'text'); ?>>Text</option>
                                <option value="email" <?php selected($field['type'], 'email'); ?>>Email</option>
                                <option value="tel" <?php selected($field['type'], 'tel'); ?>>Phone</option>
                                <option value="number" <?php selected($field['type'], 'number'); ?>>Number</option>
                            </select>
                            <label style="display: flex; align-items: center; gap: 5px;">
                                <input type="checkbox" name="fields[<?php echo $index; ?>][required]" value="1" <?php checked(!empty($field['required'])); ?>>
                                Required
                            </label>
                            <button type="button" class="button remove-field">Remove</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <button type="button" id="add-field" class="button">Add Field</button>

        <h2>Options</h2>
        <p>Choose one method to add options:</p>

        <div style="margin-bottom: 20px;">
            <h3>Batch Import</h3>
            <?php if ($form_id): ?>
                <?php
                $submission_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}timeslot_submissions WHERE form_id = %d",
                    $form_id
                ));
                if ($submission_count > 0): ?>
                    <div class="notice notice-warning inline" style="margin: 10px 0; padding: 10px;">
                        <p><strong>Warning:</strong> This form has <?php echo $submission_count; ?> submission(s). Using batch import will delete all existing options and their submission links. Use manual options instead to preserve data.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <p class="description">Paste options in the format: <code>Option Name;10</code> (one per line, where 10 is the availability)</p>
            <textarea name="batch_import" rows="10" class="large-text code" placeholder="Option 1;5&#10;Option 2;10&#10;Option 3;3"></textarea>
        </div>

        <div style="margin-bottom: 20px;">
            <h3>Manual Options</h3>
            <p class="description">Or add options manually (will be ignored if batch import is used):</p>
            <div id="options-container">
                <?php if (!empty($options)): ?>
                    <?php foreach ($options as $index => $option): ?>
                        <div class="option-row" style="margin-bottom: 10px; display: flex; gap: 10px; align-items: center;">
                            <input type="hidden" name="options[<?php echo $index; ?>][id]" value="<?php echo esc_attr($option->id); ?>">
                            <input type="text" name="options[<?php echo $index; ?>][name]" value="<?php echo esc_attr($option->name); ?>" placeholder="Option name" class="regular-text">
                            <input type="number" name="options[<?php echo $index; ?>][availability]" value="<?php echo esc_attr($option->availability); ?>" placeholder="Availability" min="0" style="width: 100px;">
                            <button type="button" class="button remove-option">Remove</button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="option-row" style="margin-bottom: 10px; display: flex; gap: 10px; align-items: center;">
                        <input type="text" name="options[0][name]" placeholder="Option name" class="regular-text">
                        <input type="number" name="options[0][availability]" placeholder="Availability" min="0" style="width: 100px;">
                        <button type="button" class="button remove-option">Remove</button>
                    </div>
                <?php endif; ?>
            </div>
            <button type="button" id="add-option" class="button">Add Option</button>
        </div>

        <p class="submit">
            <input type="submit" class="button button-primary" value="Save Form">
            <a href="<?php echo admin_url('admin.php?page=sv-bookings'); ?>" class="button">Cancel</a>
        </p>
    </form>

    <?php if ($form_id): ?>
        <div style="margin-top: 30px; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">
            <h3>Shortcode</h3>
            <p>Use this shortcode to display the form on any page or post:</p>
            <code style="font-size: 14px;">[timeslot_form id="<?php echo $form_id; ?>"]</code>
        </div>

        <?php if ($form && !empty($form->share_key)): ?>
            <div style="margin-top: 20px; padding: 15px; background: #f0f0f1; border-left: 4px solid #72aee6;">
                <h3>Share Submissions</h3>
                <p>Share this link to allow others to view submissions without logging in:</p>
                <code style="font-size: 14px;"><?php echo home_url('?sv_bookings_share=' . $form->share_key); ?></code>
                <br><br>
                <a href="<?php echo esc_url(home_url('?sv_bookings_share=' . $form->share_key)); ?>" class="button" target="_blank">Open Shared View</a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    let optionIndex = <?php echo !empty($options) ? count($options) : 1; ?>;
    let fieldIndex = <?php echo !empty($fields) ? count($fields) : 0; ?>;

    $('#add-field').on('click', function() {
        const row = $('<div class="field-row" style="margin-bottom: 15px; padding: 15px; background: #f9f9f9; border-left: 3px solid #2271b1;"></div>');
        const content = $('<div style="display: flex; gap: 15px; align-items: center; margin-bottom: 10px;"></div>');
        content.append('<input type="text" name="fields[' + fieldIndex + '][label]" placeholder="Field Label" class="regular-text" required>');
        content.append('<select name="fields[' + fieldIndex + '][type]" style="width: 150px;"><option value="text">Text</option><option value="email">Email</option><option value="tel">Phone</option><option value="number">Number</option></select>');
        content.append('<label style="display: flex; align-items: center; gap: 5px;"><input type="checkbox" name="fields[' + fieldIndex + '][required]" value="1"> Required</label>');
        content.append('<button type="button" class="button remove-field">Remove</button>');
        row.append(content);
        $('#fields-container').append(row);
        fieldIndex++;
    });

    $(document).on('click', '.remove-field', function() {
        $(this).closest('.field-row').remove();
    });

    $('#add-option').on('click', function() {
        const row = $('<div class="option-row" style="margin-bottom: 10px; display: flex; gap: 10px; align-items: center;"></div>');
        row.append('<input type="text" name="options[' + optionIndex + '][name]" placeholder="Option name" class="regular-text">');
        row.append('<input type="number" name="options[' + optionIndex + '][availability]" placeholder="Availability" min="0" style="width: 100px;">');
        row.append('<button type="button" class="button remove-option">Remove</button>');
        $('#options-container').append(row);
        optionIndex++;
    });

    $(document).on('click', '.remove-option', function() {
        $(this).closest('.option-row').remove();
    });
});
</script>
