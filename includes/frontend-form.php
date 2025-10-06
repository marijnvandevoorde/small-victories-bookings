<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$form = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}timeslot_forms WHERE id = %d",
    $form_id
));

if (!$form) {
    echo '<p>' . __('Form not found.', 'small-victories-bookings') . '</p>';
    return;
}

// Get custom fields
$fields = [];
if (!empty($form->fields)) {
    $fields = json_decode($form->fields, true);
}
if (empty($fields)) {
    $fields = [
        ['label' => 'Name', 'required' => true, 'type' => 'text'],
        ['label' => 'Email', 'required' => true, 'type' => 'email']
    ];
}

$options = $wpdb->get_results($wpdb->prepare(
    "SELECT o.*, COUNT(so.id) as booked
     FROM {$wpdb->prefix}timeslot_options o
     LEFT JOIN {$wpdb->prefix}timeslot_submission_options so ON so.option_id = o.id
     WHERE o.form_id = %d
     GROUP BY o.id
     ORDER BY o.sort_order ASC",
    $form_id
));

if (empty($options)) {
    echo '<p>' . __('No options available for this form.', 'small-victories-bookings') . '</p>';
    return;
}
?>

<div class="timeslot-form-container" data-form-id="<?php echo esc_attr($form_id); ?>">
    <form class="timeslot-form" id="timeslot-form-<?php echo esc_attr($form_id); ?>">
        <div class="timeslot-form-fields">
            <?php foreach ($fields as $index => $field): ?>
                <div class="timeslot-field">
                    <label for="timeslot-field-<?php echo esc_attr($form_id . '-' . $index); ?>">
                        <?php echo esc_html($field['label']); ?><?php if (!empty($field['required'])): ?> *<?php endif; ?>
                    </label>
                    <input
                        type="<?php echo esc_attr($field['type']); ?>"
                        id="timeslot-field-<?php echo esc_attr($form_id . '-' . $index); ?>"
                        name="field_<?php echo esc_attr($index); ?>"
                        data-label="<?php echo esc_attr($field['label']); ?>"
                        <?php if (!empty($field['required'])): ?>required<?php endif; ?>>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="timeslot-options-label">
            <label><?php _e('Select Options *', 'small-victories-bookings'); ?></label>
        </div>

        <div class="timeslot-options" style="--columns: <?php echo esc_attr($form->columns); ?>;">
            <?php foreach ($options as $option): ?>
                <?php
                $available = $option->availability - $option->booked;
                $is_full = $available <= 0;
                ?>
                <div class="timeslot-option <?php echo $is_full ? 'timeslot-option-full' : ''; ?>">
                    <label>
                        <input type="checkbox"
                               name="options[]"
                               value="<?php echo esc_attr($option->id); ?>"
                               <?php echo $is_full ? 'disabled' : ''; ?>>
                        <div class="timeslot-option-content">
                            <div class="timeslot-option-name"><?php echo esc_html($option->name); ?></div>
                            <div class="timeslot-option-availability">
                                <?php if ($is_full): ?>
                                    <span class="timeslot-full-text"><?php _e('Full', 'small-victories-bookings'); ?></span>
                                <?php else: ?>
                                    <span class="timeslot-available-text"><?php echo esc_html($available); ?> <?php _e('available', 'small-victories-bookings'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="timeslot-form-message"></div>

        <div class="timeslot-form-submit">
            <button type="submit" class="timeslot-submit-button"><?php _e('Submit', 'small-victories-bookings'); ?></button>
        </div>
    </form>
</div>
