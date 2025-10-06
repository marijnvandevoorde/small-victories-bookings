<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['form_id'])) {
    $form_id = intval($_GET['form_id']);
    $wpdb->delete($wpdb->prefix . 'timeslot_submission_options', array(), array());
    $wpdb->delete($wpdb->prefix . 'timeslot_submissions', array('form_id' => $form_id), array('%d'));
    $wpdb->delete($wpdb->prefix . 'timeslot_options', array('form_id' => $form_id), array('%d'));
    $wpdb->delete($wpdb->prefix . 'timeslot_forms', array('id' => $form_id), array('%d'));
    echo '<div class="notice notice-success"><p>Form deleted successfully.</p></div>';
}

$forms = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}timeslot_forms ORDER BY created_at DESC");
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Booking Forms</h1>
    <a href="<?php echo admin_url('admin.php?page=sv-bookings-new'); ?>" class="page-title-action">Add New</a>
    <hr class="wp-header-end">

    <?php if (empty($forms)): ?>
        <p>No forms found. <a href="<?php echo admin_url('admin.php?page=sv-bookings-new'); ?>">Create your first form</a>.</p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Form Name</th>
                    <th>Columns</th>
                    <th>Options</th>
                    <th>Submissions</th>
                    <th>Shortcode</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($forms as $form): ?>
                    <?php
                    $options_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}timeslot_options WHERE form_id = %d",
                        $form->id
                    ));
                    $submissions_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}timeslot_submissions WHERE form_id = %d",
                        $form->id
                    ));
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($form->name); ?></strong></td>
                        <td><?php echo esc_html($form->columns); ?></td>
                        <td><?php echo intval($options_count); ?></td>
                        <td><?php echo intval($submissions_count); ?></td>
                        <td><code>[timeslot_form id="<?php echo $form->id; ?>"]</code></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=sv-bookings-new&form_id=' . $form->id); ?>">Edit</a> |
                            <a href="<?php echo admin_url('admin.php?page=sv-bookings&action=delete&form_id=' . $form->id); ?>"
                               onclick="return confirm('Are you sure you want to delete this form? All submissions will be lost.');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
