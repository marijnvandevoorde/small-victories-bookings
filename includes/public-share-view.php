<?php
if (!defined('ABSPATH')) {
    exit;
}

// $form is already available from the parent context
global $wpdb;

// Get form fields
$fields = [];
if (!empty($form->fields)) {
    $fields = json_decode($form->fields, true);
}

// Get all options with their submissions in a single query
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT
        o.id as option_id,
        o.name as option_name,
        o.sort_order,
        s.id as submission_id,
        s.response
     FROM {$wpdb->prefix}timeslot_options o
     LEFT JOIN {$wpdb->prefix}timeslot_submission_options so ON o.id = so.option_id
     LEFT JOIN {$wpdb->prefix}timeslot_submissions s ON so.submission_id = s.id
     WHERE o.form_id = %d
     ORDER BY o.sort_order ASC, s.submitted_at ASC",
    $form->id
));

// Organize results by option
$options_data = [];
$max_submissions = 0;

foreach ($results as $row) {
    $option_id = $row->option_id;

    if (!isset($options_data[$option_id])) {
        $options_data[$option_id] = [
            'name' => $row->option_name,
            'submissions' => []
        ];
    }

    // Only add submission if it exists (LEFT JOIN might return null)
    if ($row->submission_id) {
        $options_data[$option_id]['submissions'][] = $row->response;
    }
}

// Calculate max submissions
foreach ($options_data as $option) {
    $max_submissions = max($max_submissions, count($option['submissions']));
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
            border: 1px solid #ddd;
            vertical-align: top;
        }
        th {
            background-color: #f6f7f7;
            font-weight: 600;
            color: #1d2327;
        }
        tr:hover {
            background-color: #f6f7f7;
        }
        .option-name {
            font-weight: 600;
            background-color: #f6f7f7;
        }
        .submission-cell {
            white-space: pre-line;
            min-width: 150px;
        }
        .field-item {
            margin-bottom: 5px;
        }
        .field-label {
            font-weight: 600;
            color: #1d2327;
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

        <?php if (!empty($options_data)): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th><?php _e('Option', 'small-victories-bookings'); ?></th>
                            <?php
                            // Create header columns for submissions
                            if ($max_submissions == 0) {
                                echo '<th colspan="100">' . __('No submissions yet', 'small-victories-bookings') . '</th>';
                            } else {
                                for ($i = 1; $i <= $max_submissions; $i++) {
                                    echo '<th>' . sprintf(__('Submission %d', 'small-victories-bookings'), $i) . '</th>';
                                }
                            }
                            ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($options_data as $option): ?>
                            <tr>
                                <td class="option-name"><?php echo esc_html($option['name']); ?></td>
                                <?php
                                // Display each submission for this option
                                foreach ($option['submissions'] as $response_json) {
                                    $response = json_decode($response_json, true);
                                    echo '<td class="submission-cell">';
                                    if (is_array($response)) {
                                        foreach ($fields as $field) {
                                            if (isset($response[$field['label']])) {
                                                echo '<div class="field-item">';
                                                echo '<span class="field-label">' . esc_html($field['label']) . ':</span> ';
                                                echo esc_html($response[$field['label']]);
                                                echo '</div>';
                                            }
                                        }
                                    }
                                    echo '</td>';
                                }

                                // Fill remaining columns with empty cells
                                $submission_count = count($option['submissions']);
                                for ($i = $submission_count; $i < $max_submissions; $i++) {
                                    echo '<td></td>';
                                }
                                ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p><?php _e('No options available for this form.', 'small-victories-bookings'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
