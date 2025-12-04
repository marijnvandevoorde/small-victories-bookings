<?php
if (!defined('ABSPATH')) {
    exit;
}

// $form is already available from the parent context
global $wpdb;

$submissions = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}timeslot_submissions WHERE form_id = %d ORDER BY submitted_at DESC",
    $form->id
));

// Get form fields
$fields = [];
if (!empty($form->fields)) {
    $fields = json_decode($form->fields, true);
}

// Get page title
$page_title = sprintf(__('Submissions for: %s', 'small-victories-bookings'), $form->name);

// Output HTML
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($page_title); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f0f0f1;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        h1 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #1d2327;
        }
        .table-wrapper {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f6f7f7;
            font-weight: 600;
            color: #1d2327;
        }
        tr:hover {
            background-color: #f6f7f7;
        }
        .summary {
            margin-top: 20px;
            font-weight: 600;
        }
        .empty-state {
            padding: 40px;
            text-align: center;
            color: #646970;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo esc_html($page_title); ?></h1>

        <?php if (!empty($submissions)): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($fields as $field): ?>
                                <th><?php echo esc_html($field['label']); ?></th>
                            <?php endforeach; ?>
                            <th><?php _e('Selected Options', 'small-victories-bookings'); ?></th>
                            <th><?php _e('Submitted At', 'small-victories-bookings'); ?></th>
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
            </div>

            <p class="summary">
                <strong><?php _e('Total submissions:', 'small-victories-bookings'); ?></strong> <?php echo count($submissions); ?>
            </p>
        <?php else: ?>
            <div class="empty-state">
                <p><?php _e('No submissions yet for this form.', 'small-victories-bookings'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
