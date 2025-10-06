<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$forms = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}timeslot_forms ORDER BY name ASC");
$selected_form = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;

if ($selected_form) {
    $submissions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}timeslot_submissions WHERE form_id = %d ORDER BY submitted_at DESC",
        $selected_form
    ));

    // Get form fields
    $form = $wpdb->get_row($wpdb->prepare(
        "SELECT fields FROM {$wpdb->prefix}timeslot_forms WHERE id = %d",
        $selected_form
    ));
    $fields = [];
    if ($form && !empty($form->fields)) {
        $fields = json_decode($form->fields, true);
    }
} else {
    $submissions = array();
    $fields = [];
}
?>

<div class="wrap">
    <h1>Submissions</h1>

    <?php if (empty($forms)): ?>
        <p>No forms found. <a href="<?php echo admin_url('admin.php?page=sv-bookings-new'); ?>">Create a form first</a>.</p>
    <?php else: ?>
        <form method="get" action="">
            <input type="hidden" name="page" value="sv-bookings-submissions">
            <label for="form_id">Select Form:</label>
            <select name="form_id" id="form_id" onchange="this.form.submit()">
                <option value="">-- Select a form --</option>
                <?php foreach ($forms as $form): ?>
                    <option value="<?php echo esc_attr($form->id); ?>" <?php selected($selected_form, $form->id); ?>>
                        <?php echo esc_html($form->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($selected_form && !empty($submissions)): ?>
            <h2>
                Submissions for: <?php
                    $form_name = $wpdb->get_var($wpdb->prepare(
                        "SELECT name FROM {$wpdb->prefix}timeslot_forms WHERE id = %d",
                        $selected_form
                    ));
                    echo esc_html($form_name);
                ?>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?timeslot_export_csv=1&form_id=' . $selected_form), 'timeslot_export_csv_' . $selected_form); ?>" class="page-title-action">Export to CSV</a>
            </h2>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <?php foreach ($fields as $field): ?>
                            <th><?php echo esc_html($field['label']); ?></th>
                        <?php endforeach; ?>
                        <th>Selected Options</th>
                        <th>Submitted At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submissions as $submission): ?>
                        <?php
                        $response = json_decode($submission->response, true);
                        $selected_options = $wpdb->get_results($wpdb->prepare(
                            "SELECT o.name
                             FROM {$wpdb->prefix}timeslot_options o
                             INNER JOIN {$wpdb->prefix}timeslot_submission_options so ON o.id = so.option_id
                             WHERE so.submission_id = %d
                             ORDER BY o.sort_order ASC",
                            $submission->id
                        ));
                        $option_names = array_map(function($opt) { return $opt->name; }, $selected_options);
                        ?>
                        <tr>
                            <?php foreach ($fields as $field): ?>
                                <td><?php echo esc_html(isset($response[$field['label']]) ? $response[$field['label']] : ''); ?></td>
                            <?php endforeach; ?>
                            <td><?php echo esc_html(implode(', ', $option_names)); ?></td>
                            <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($submission->submitted_at))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p><strong>Total submissions:</strong> <?php echo count($submissions); ?></p>
        <?php elseif ($selected_form): ?>
            <p>No submissions yet for this form.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>
