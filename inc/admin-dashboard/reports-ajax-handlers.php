<?php
/**
 * Reports AJAX Handlers
 *
 * AJAX endpoints for reports dashboard
 * - Fetch report data
 * - Export PDF
 * - Export Excel
 *
 * @package sc_events
 * @since 2.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get report data via AJAX
 */
function sc_ajax_get_report_data() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'sc_reports_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    global $wpdb;

    $event_id = !empty($_POST['event_id']) ? intval($_POST['event_id']) : null;
    $date_from = sanitize_text_field($_POST['date_from'] ?? date('Y-m-01'));
    $date_to = sanitize_text_field($_POST['date_to'] ?? date('Y-m-d'));

    $current_user = wp_get_current_user();
    $is_admin = current_user_can('manage_options');

    // Tables
    $events_table = $wpdb->prefix . 'sc_events';
    $attendees_table = $wpdb->prefix . 'sc_attendees';
    $tickets_table = $wpdb->prefix . 'sc_tickets';

    // Author filter for non-admins
    $author_filter = '';
    $author_params = array();
    if (!$is_admin) {
        $author_filter = 'AND e.author_id = %d';
        $author_params[] = $current_user->ID;
    }

    // Event filter
    $event_filter = '';
    $event_params = array();
    if ($event_id) {
        $event_filter = 'AND e.id = %d';
        $event_params[] = $event_id;
    }

    // ========================================
    // STATISTICS
    // ========================================

    // Total events
    $total_events_sql = "SELECT COUNT(*) FROM $events_table e WHERE 1=1 $author_filter $event_filter";
    $total_events = $wpdb->get_var($wpdb->prepare(
        $total_events_sql,
        array_merge($author_params, $event_params)
    ) ?: $total_events_sql);

    // Total attendees
    $total_attendees_sql = "SELECT COUNT(*)
        FROM $attendees_table a
        INNER JOIN $events_table e ON a.event_id = e.id
        WHERE a.status = 'active' AND a.payment_status = 'success'
        AND a.created_at BETWEEN %s AND %s
        $author_filter $event_filter";

    $params = array($date_from . ' 00:00:00', $date_to . ' 23:59:59');
    $params = array_merge($params, $author_params, $event_params);

    $total_attendees = $wpdb->get_var($wpdb->prepare($total_attendees_sql, $params));

    // Total revenue
    $total_revenue_sql = "SELECT COALESCE(SUM(a.amount_paid), 0)
        FROM $attendees_table a
        INNER JOIN $events_table e ON a.event_id = e.id
        WHERE a.status = 'active' AND a.payment_status = 'success'
        AND a.created_at BETWEEN %s AND %s
        $author_filter $event_filter";

    $total_revenue = $wpdb->get_var($wpdb->prepare($total_revenue_sql, $params));

    // Check-in rate
    $checkin_sql = "SELECT
        COUNT(CASE WHEN a.checked_in = 1 THEN 1 END) as checked_in,
        COUNT(*) as total
        FROM $attendees_table a
        INNER JOIN $events_table e ON a.event_id = e.id
        WHERE a.status = 'active' AND a.payment_status = 'success'
        AND a.created_at BETWEEN %s AND %s
        $author_filter $event_filter";

    $checkin_data = $wpdb->get_row($wpdb->prepare($checkin_sql, $params));
    $checkin_rate = $checkin_data && $checkin_data->total > 0
        ? round(($checkin_data->checked_in / $checkin_data->total) * 100, 1)
        : 0;

    // ========================================
    // REGISTRATIONS CHART (by day)
    // ========================================

    $registrations_sql = "SELECT DATE(a.created_at) as date, COUNT(*) as count
        FROM $attendees_table a
        INNER JOIN $events_table e ON a.event_id = e.id
        WHERE a.status = 'active' AND a.payment_status = 'success'
        AND a.created_at BETWEEN %s AND %s
        $author_filter $event_filter
        GROUP BY DATE(a.created_at)
        ORDER BY date ASC";

    $registrations_data = $wpdb->get_results($wpdb->prepare($registrations_sql, $params));

    $registrations_chart = array(
        'labels' => array(),
        'data' => array()
    );

    foreach ($registrations_data as $row) {
        $registrations_chart['labels'][] = date('M d', strtotime($row->date));
        $registrations_chart['data'][] = (int) $row->count;
    }

    // ========================================
    // REVENUE CHART (by day)
    // ========================================

    $revenue_sql = "SELECT DATE(a.created_at) as date, SUM(a.amount_paid) as revenue
        FROM $attendees_table a
        INNER JOIN $events_table e ON a.event_id = e.id
        WHERE a.status = 'active' AND a.payment_status = 'success'
        AND a.created_at BETWEEN %s AND %s
        $author_filter $event_filter
        GROUP BY DATE(a.created_at)
        ORDER BY date ASC";

    $revenue_data = $wpdb->get_results($wpdb->prepare($revenue_sql, $params));

    $revenue_chart = array(
        'labels' => array(),
        'data' => array()
    );

    foreach ($revenue_data as $row) {
        $revenue_chart['labels'][] = date('M d', strtotime($row->date));
        $revenue_chart['data'][] = (float) $row->revenue;
    }

    // ========================================
    // TICKETS DISTRIBUTION CHART
    // ========================================

    $tickets_sql = "SELECT t.name, COUNT(a.id) as count
        FROM $attendees_table a
        INNER JOIN $tickets_table t ON a.ticket_id = t.id
        INNER JOIN $events_table e ON a.event_id = e.id
        WHERE a.status = 'active' AND a.payment_status = 'success'
        AND a.created_at BETWEEN %s AND %s
        $author_filter $event_filter
        GROUP BY t.id
        ORDER BY count DESC
        LIMIT 5";

    $tickets_data = $wpdb->get_results($wpdb->prepare($tickets_sql, $params));

    $tickets_chart = array(
        'labels' => array(),
        'data' => array()
    );

    foreach ($tickets_data as $row) {
        $tickets_chart['labels'][] = $row->name;
        $tickets_chart['data'][] = (int) $row->count;
    }

    // ========================================
    // EVENTS STATUS CHART
    // ========================================

    $events_status_sql = "SELECT
        SUM(CASE WHEN status = 'publish' AND start_date > CURDATE() THEN 1 ELSE 0 END) as upcoming,
        SUM(CASE WHEN status = 'publish' AND start_date <= CURDATE() AND end_date >= CURDATE() THEN 1 ELSE 0 END) as ongoing,
        SUM(CASE WHEN status = 'completed' OR end_date < CURDATE() THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM $events_table e
        WHERE 1=1 $author_filter $event_filter";

    $events_status = $wpdb->get_row($wpdb->prepare(
        $events_status_sql,
        array_merge($author_params, $event_params)
    ) ?: $events_status_sql);

    $is_rtl = sc_is_rtl();
    $events_chart = array(
        'labels' => array(
            $is_rtl ? 'قادمة' : 'Upcoming',
            $is_rtl ? 'جارية' : 'Ongoing',
            $is_rtl ? 'مكتملة' : 'Completed',
            $is_rtl ? 'ملغاة' : 'Cancelled'
        ),
        'data' => array(
            (int) ($events_status->upcoming ?? 0),
            (int) ($events_status->ongoing ?? 0),
            (int) ($events_status->completed ?? 0),
            (int) ($events_status->cancelled ?? 0)
        )
    );

    // ========================================
    // TOP EVENTS
    // ========================================

    $top_events_sql = "SELECT e.id, e.title,
        COUNT(a.id) as attendees,
        COALESCE(SUM(a.amount_paid), 0) as revenue
        FROM $events_table e
        LEFT JOIN $attendees_table a ON a.event_id = e.id
            AND a.status = 'active' AND a.payment_status = 'success'
        WHERE 1=1 $author_filter
        GROUP BY e.id
        ORDER BY attendees DESC
        LIMIT 5";

    $top_events = $wpdb->get_results($wpdb->prepare(
        $top_events_sql,
        $author_params
    ) ?: $top_events_sql);

    $top_events_list = array();
    foreach ($top_events as $event) {
        $top_events_list[] = array(
            'id' => $event->id,
            'title' => $event->title,
            'attendees' => (int) $event->attendees,
            'revenue' => (float) $event->revenue
        );
    }

    // ========================================
    // RECENT REGISTRATIONS
    // ========================================

    $recent_sql = "SELECT a.name, e.title as event, DATE(a.created_at) as date
        FROM $attendees_table a
        INNER JOIN $events_table e ON a.event_id = e.id
        WHERE a.status = 'active' AND a.payment_status = 'success'
        $author_filter
        ORDER BY a.created_at DESC
        LIMIT 10";

    $recent_registrations = $wpdb->get_results($wpdb->prepare(
        $recent_sql,
        $author_params
    ) ?: $recent_sql);

    $recent_list = array();
    foreach ($recent_registrations as $reg) {
        $recent_list[] = array(
            'name' => $reg->name,
            'event' => $reg->event,
            'date' => date('Y-m-d', strtotime($reg->date))
        );
    }

    // ========================================
    // RESPONSE
    // ========================================

    wp_send_json_success(array(
        'stats' => array(
            'total_events' => (int) $total_events,
            'total_attendees' => (int) $total_attendees,
            'total_revenue' => (float) $total_revenue,
            'checkin_rate' => $checkin_rate
        ),
        'registrations_chart' => $registrations_chart,
        'revenue_chart' => $revenue_chart,
        'tickets_chart' => $tickets_chart,
        'events_chart' => $events_chart,
        'top_events' => $top_events_list,
        'recent_registrations' => $recent_list
    ));
}
add_action('wp_ajax_sc_get_report_data', 'sc_ajax_get_report_data');

/**
 * Export report to Excel (CSV)
 */
function sc_ajax_export_report_excel() {
    // Verify nonce
    if (!wp_verify_nonce($_GET['nonce'] ?? '', 'sc_reports_nonce')) {
        wp_die('Invalid nonce');
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_die('Unauthorized');
    }

    global $wpdb;

    $event_id = !empty($_GET['event_id']) ? intval($_GET['event_id']) : null;
    $date_from = sanitize_text_field($_GET['date_from'] ?? date('Y-m-01'));
    $date_to = sanitize_text_field($_GET['date_to'] ?? date('Y-m-d'));

    $current_user = wp_get_current_user();
    $is_admin = current_user_can('manage_options');

    $events_table = $wpdb->prefix . 'sc_events';
    $attendees_table = $wpdb->prefix . 'sc_attendees';

    // Build query
    $where = "WHERE a.status = 'active' AND a.payment_status = 'success'
              AND a.created_at BETWEEN %s AND %s";
    $params = array($date_from . ' 00:00:00', $date_to . ' 23:59:59');

    if (!$is_admin) {
        $where .= " AND e.author_id = %d";
        $params[] = $current_user->ID;
    }

    if ($event_id) {
        $where .= " AND e.id = %d";
        $params[] = $event_id;
    }

    $sql = "SELECT a.name, a.email, a.phone, a.ticket_name, a.amount_paid,
                   a.checked_in, a.created_at, e.title as event_title
            FROM $attendees_table a
            INNER JOIN $events_table e ON a.event_id = e.id
            $where
            ORDER BY a.created_at DESC";

    $results = $wpdb->get_results($wpdb->prepare($sql, $params));

    // Output CSV
    $filename = 'report_' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // BOM for Excel UTF-8
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Header row
    fputcsv($output, array(
        'Event',
        'Name',
        'Email',
        'Phone',
        'Ticket',
        'Amount',
        'Checked In',
        'Date'
    ));

    // Data rows
    foreach ($results as $row) {
        fputcsv($output, array(
            $row->event_title,
            $row->name,
            $row->email,
            $row->phone,
            $row->ticket_name,
            $row->amount_paid,
            $row->checked_in ? 'Yes' : 'No',
            $row->created_at
        ));
    }

    fclose($output);
    exit;
}
add_action('wp_ajax_sc_export_report_excel', 'sc_ajax_export_report_excel');

/**
 * Export report to PDF
 */
function sc_ajax_export_report_pdf() {
    // Verify nonce
    if (!wp_verify_nonce($_GET['nonce'] ?? '', 'sc_reports_nonce')) {
        wp_die('Invalid nonce');
    }

    // Check permissions
    if (!SC_Event_Manager_Dashboard::is_event_manager()) {
        wp_die('Unauthorized');
    }

    // For PDF, we'll generate an HTML page that can be printed as PDF
    // In production, you would use a library like TCPDF or mPDF

    global $wpdb;

    $event_id = !empty($_GET['event_id']) ? intval($_GET['event_id']) : null;
    $date_from = sanitize_text_field($_GET['date_from'] ?? date('Y-m-01'));
    $date_to = sanitize_text_field($_GET['date_to'] ?? date('Y-m-d'));

    $current_user = wp_get_current_user();
    $is_admin = current_user_can('manage_options');

    $events_table = $wpdb->prefix . 'sc_events';
    $attendees_table = $wpdb->prefix . 'sc_attendees';

    // Build query
    $where = "WHERE a.status = 'active' AND a.payment_status = 'success'
              AND a.created_at BETWEEN %s AND %s";
    $params = array($date_from . ' 00:00:00', $date_to . ' 23:59:59');

    if (!$is_admin) {
        $where .= " AND e.author_id = %d";
        $params[] = $current_user->ID;
    }

    if ($event_id) {
        $where .= " AND e.id = %d";
        $params[] = $event_id;
    }

    // Get summary
    $summary_sql = "SELECT
        COUNT(*) as total,
        SUM(a.amount_paid) as revenue,
        SUM(CASE WHEN a.checked_in = 1 THEN 1 ELSE 0 END) as checked_in
        FROM $attendees_table a
        INNER JOIN $events_table e ON a.event_id = e.id
        $where";

    $summary = $wpdb->get_row($wpdb->prepare($summary_sql, $params));

    // Get details
    $sql = "SELECT a.name, a.email, a.ticket_name, a.amount_paid,
                   a.checked_in, a.created_at, e.title as event_title
            FROM $attendees_table a
            INNER JOIN $events_table e ON a.event_id = e.id
            $where
            ORDER BY e.title, a.created_at DESC";

    $results = $wpdb->get_results($wpdb->prepare($sql, $params));

    $is_rtl = sc_is_rtl();
    ?>
    <!DOCTYPE html>
    <html dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>">
    <head>
        <meta charset="UTF-8">
        <title><?php echo $is_rtl ? 'تقرير الأحداث' : 'Events Report'; ?></title>
        <style>
            body {
                font-family: Arial, sans-serif;
                font-size: 12px;
                line-height: 1.4;
                color: #333;
                direction: <?php echo $is_rtl ? 'rtl' : 'ltr'; ?>;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
                padding-bottom: 20px;
                border-bottom: 2px solid #333;
            }
            .header h1 {
                margin: 0 0 10px;
                font-size: 24px;
            }
            .summary {
                display: flex;
                justify-content: space-around;
                margin-bottom: 30px;
                padding: 20px;
                background: #f5f5f5;
            }
            .summary-item {
                text-align: center;
            }
            .summary-item .value {
                font-size: 28px;
                font-weight: bold;
                color: #667eea;
            }
            .summary-item .label {
                font-size: 12px;
                color: #666;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            th, td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: <?php echo $is_rtl ? 'right' : 'left'; ?>;
            }
            th {
                background: #667eea;
                color: white;
            }
            tr:nth-child(even) {
                background: #f9f9f9;
            }
            .print-btn {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 10px 20px;
                background: #667eea;
                color: white;
                border: none;
                cursor: pointer;
                border-radius: 5px;
            }
            @media print {
                .print-btn { display: none; }
            }
        </style>
    </head>
    <body>
        <button class="print-btn" onclick="window.print()">
            <?php echo $is_rtl ? 'طباعة' : 'Print'; ?>
        </button>

        <div class="header">
            <h1><?php echo $is_rtl ? 'تقرير الأحداث' : 'Events Report'; ?></h1>
            <p><?php echo $date_from; ?> - <?php echo $date_to; ?></p>
        </div>

        <div class="summary">
            <div class="summary-item">
                <div class="value"><?php echo number_format($summary->total); ?></div>
                <div class="label"><?php echo $is_rtl ? 'إجمالي التسجيلات' : 'Total Registrations'; ?></div>
            </div>
            <div class="summary-item">
                <div class="value"><?php echo number_format($summary->revenue, 2); ?></div>
                <div class="label"><?php echo $is_rtl ? 'إجمالي الإيرادات' : 'Total Revenue'; ?></div>
            </div>
            <div class="summary-item">
                <div class="value"><?php echo number_format($summary->checked_in); ?></div>
                <div class="label"><?php echo $is_rtl ? 'تم تسجيل الحضور' : 'Checked In'; ?></div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th><?php echo $is_rtl ? 'الحدث' : 'Event'; ?></th>
                    <th><?php echo $is_rtl ? 'الاسم' : 'Name'; ?></th>
                    <th><?php echo $is_rtl ? 'البريد' : 'Email'; ?></th>
                    <th><?php echo $is_rtl ? 'التذكرة' : 'Ticket'; ?></th>
                    <th><?php echo $is_rtl ? 'المبلغ' : 'Amount'; ?></th>
                    <th><?php echo $is_rtl ? 'الحضور' : 'Checked In'; ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $row): ?>
                <tr>
                    <td><?php echo esc_html($row->event_title); ?></td>
                    <td><?php echo esc_html($row->name); ?></td>
                    <td><?php echo esc_html($row->email); ?></td>
                    <td><?php echo esc_html($row->ticket_name); ?></td>
                    <td><?php echo number_format($row->amount_paid, 2); ?></td>
                    <td><?php echo $row->checked_in ? ($is_rtl ? 'نعم' : 'Yes') : ($is_rtl ? 'لا' : 'No'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p style="margin-top: 30px; text-align: center; color: #999;">
            <?php echo $is_rtl ? 'تم إنشاء التقرير في' : 'Report generated on'; ?>
            <?php echo date('Y-m-d H:i:s'); ?>
        </p>
    </body>
    </html>
    <?php
    exit;
}
add_action('wp_ajax_sc_export_report_pdf', 'sc_ajax_export_report_pdf');
