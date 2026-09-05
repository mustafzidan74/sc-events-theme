<?php
/**
 * Dashboard - View Event Page
 * Standalone page for viewing event details
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Translations
$t = array(
    // Breadcrumb
    'events' => sc_t('dashboard_pages.events', 'Events'),
    'view' => sc_t('dashboard_pages.view', 'View'),

    // Buttons
    'edit_event' => sc_t('dashboard_pages.edit_event', 'Edit Event'),
    'view_public_page' => sc_t('dashboard_pages.view_public_page', 'View Public Page'),
    'back' => sc_t('dashboard_pages.back', 'Back'),

    // Status
    'draft' => sc_t('dashboard_pages.draft', 'Draft'),
    'completed' => sc_t('dashboard_pages.completed', 'Completed'),
    'ongoing' => sc_t('dashboard_pages.ongoing', 'Ongoing'),
    'upcoming' => sc_t('dashboard_pages.upcoming', 'Upcoming'),
    'cancelled' => sc_t('dashboard_pages.cancelled', 'Cancelled'),
    'private' => sc_t('dashboard_pages.private', 'Private'),

    // Stats
    'tickets_sold' => sc_t('dashboard_pages.tickets_sold', 'Tickets Sold'),
    'revenue' => sc_t('dashboard_pages.revenue', 'Revenue'),
    'capacity' => sc_t('dashboard_pages.capacity', 'Capacity'),

    // Date & Time
    'date_and_time' => sc_t('dashboard_pages.date_and_time', 'Date & Time'),
    'start' => sc_t('dashboard_pages.start', 'Start'),
    'end' => sc_t('dashboard_pages.end', 'End'),
    'timezone' => sc_t('dashboard_pages.timezone', 'Timezone'),
    'all_day_event' => sc_t('dashboard_pages.all_day_event', 'All Day Event'),

    // Location
    'location' => sc_t('dashboard_pages.location', 'Location'),
    'online_event' => sc_t('dashboard_pages.online_event', 'Online Event'),
    'join_meeting' => sc_t('dashboard_pages.join_meeting', 'Join Meeting'),
    'hybrid_event' => sc_t('dashboard_pages.hybrid_event', 'Hybrid Event'),
    'physical_location' => sc_t('dashboard_pages.physical_location', 'Physical Location'),
    'online_access' => sc_t('dashboard_pages.online_access', 'Online Access'),
    'physical_event' => sc_t('dashboard_pages.physical_event', 'Physical Event'),
    'no_meeting_link' => sc_t('dashboard_pages.no_meeting_link', 'No meeting link provided'),

    // Description
    'description' => sc_t('dashboard_pages.description', 'Description'),

    // Tickets
    'tickets' => sc_t('dashboard_pages.tickets', 'Tickets'),
    'no_tickets_configured' => sc_t('dashboard_pages.no_tickets_configured', 'No tickets configured for this event.'),
    'ticket_type' => sc_t('dashboard_pages.ticket_type', 'Ticket Type'),
    'price' => sc_t('dashboard_pages.price', 'Price'),
    'available' => sc_t('dashboard_pages.available', 'Available'),
    'sold' => sc_t('dashboard_pages.sold', 'Sold'),
    'status' => sc_t('dashboard_pages.status', 'Status'),
    'unlimited' => sc_t('dashboard_pages.unlimited', 'Unlimited'),
    'active' => sc_t('dashboard_pages.active', 'Active'),
    'inactive' => sc_t('dashboard_pages.inactive', 'Inactive'),

    // Attendees
    'recent_attendees' => sc_t('dashboard_pages.recent_attendees', 'Recent Attendees'),
    'view_all' => sc_t('dashboard_pages.view_all', 'View All'),
    'no_attendees_yet' => sc_t('dashboard_pages.no_attendees_yet', 'No attendees registered yet.'),
    'name' => sc_t('dashboard_pages.name', 'Name'),
    'email' => sc_t('dashboard_pages.email', 'Email'),
    'ticket' => sc_t('dashboard_pages.ticket', 'Ticket'),
    'date' => sc_t('dashboard_pages.date', 'Date'),
    'checked_in' => sc_t('dashboard_pages.checked_in', 'Checked In'),
    'not_checked_in' => sc_t('dashboard_pages.not_checked_in', 'Not Checked In'),

    // Quick Actions
    'quick_actions' => sc_t('dashboard_pages.quick_actions', 'Quick Actions'),
    'add_attendee' => sc_t('dashboard_pages.add_attendee', 'Add Attendee'),
    'view_attendees' => sc_t('dashboard_pages.view_attendees', 'View Attendees'),
    'checkin_scanner' => sc_t('dashboard_pages.checkin_scanner', 'Check-in Scanner'),

    // Event Details
    'event_details' => sc_t('dashboard_pages.event_details', 'Event Details'),
    'created' => sc_t('dashboard_pages.created', 'Created'),
    'updated' => sc_t('dashboard_pages.updated', 'Updated'),
    'deadline' => sc_t('dashboard_pages.deadline', 'Deadline'),
    'tracking' => sc_t('dashboard_pages.tracking', 'Tracking'),
    'enabled' => sc_t('dashboard_pages.enabled', 'Enabled'),
    'disabled' => sc_t('dashboard_pages.disabled', 'Disabled'),

    // Speakers & Organizers
    'speakers' => sc_t('dashboard_pages.speakers', 'Speakers'),
    'organizers' => sc_t('dashboard_pages.organizers', 'Organizers'),

    // Sales Summary
    'sales_summary' => sc_t('dashboard_pages.sales_summary', 'Sales Summary'),
    'total_revenue' => sc_t('dashboard_pages.total_revenue', 'Total Revenue'),
    'tickets_sold_of' => sc_t('dashboard_pages.tickets_sold_of', 'of'),
    'tickets_sold_text' => sc_t('dashboard_pages.tickets_sold_text', 'tickets sold'),
    'view_full_report' => sc_t('dashboard_pages.view_full_report', 'View Full Report'),

    // Permission
    'no_permission' => sc_t('dashboard_pages.no_permission', 'You do not have permission to access this page.'),
);

// Check permissions
if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die($t['no_permission']);
}

// Get event ID from URL
$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$event_id) {
    wp_redirect(home_url('/event-manager-dashboard/events'));
    exit;
}

// Get event data
$event = null;
if (class_exists('SC_Event')) {
    $event = SC_Event::get($event_id);
}

if (!$event) {
    wp_redirect(home_url('/event-manager-dashboard/events'));
    exit;
}

$page_title = esc_html($event->title);
get_template_part('template-parts/dashboard/components/dashboard', 'header');

// Get categories
$event_categories = array();
if (!empty($event->wp_post_id)) {
    $event_terms = wp_get_post_terms($event->wp_post_id, 'sc_event_category');
    if (!is_wp_error($event_terms)) {
        $event_categories = $event_terms;
    }
}

// Get speakers
global $wpdb;
$speakers = $wpdb->get_results($wpdb->prepare(
    "SELECT s.* FROM {$wpdb->prefix}sc_speakers s
     INNER JOIN {$wpdb->prefix}sc_event_speakers es ON s.id = es.speaker_id
     WHERE es.event_id = %d AND s.is_active = 1
     ORDER BY s.name ASC",
    $event_id
));

// Get organizers
$organizers = $wpdb->get_results($wpdb->prepare(
    "SELECT o.* FROM {$wpdb->prefix}sc_organizers o
     INNER JOIN {$wpdb->prefix}sc_event_organizers eo ON o.id = eo.organizer_id
     WHERE eo.event_id = %d AND o.is_active = 1
     ORDER BY o.name ASC",
    $event_id
));

// Get tickets
$tickets = array();
if (class_exists('SC_Ticket')) {
    $tickets = SC_Ticket::get_by_event($event_id);
    // Ensure sold count is accurate by checking attendees table
    $attendees_table = $wpdb->prefix . 'sc_attendees';
    foreach ($tickets as &$ticket) {
        // Force int cast - NULL becomes 0
        $ticket->sold = (int) ($ticket->sold ?? 0);
        $actual_sold = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $attendees_table WHERE ticket_id = %d AND status != 'cancelled'",
            $ticket->id
        ));
        // Always use the higher value (actual from DB or stored)
        $ticket->sold = max($ticket->sold, $actual_sold);
        $ticket->available = $ticket->quantity < 0 ? PHP_INT_MAX : max(0, (int) $ticket->quantity - $ticket->sold);
    }
    unset($ticket);
}

// Get recent attendees
$recent_attendees = array();
if (class_exists('SC_Attendee')) {
    $recent_attendees = SC_Attendee::get_all(array(
        'event_id' => $event_id,
        'limit' => 10,
        'orderby' => 'created_at',
        'order' => 'DESC'
    ));
}

// Calculate stats
$total_capacity = $event->total_capacity ?: 0;
$tickets_sold = $event->total_sold ?: 0;
$revenue = $event->total_revenue ?: 0;
$capacity_percent = $total_capacity > 0 ? min(100, round(($tickets_sold / $total_capacity) * 100)) : 0;

// Event status
$now = current_time('mysql');
$event_status_class = 'secondary';
$event_status_text = $t['draft'];

if ($event->status === 'publish') {
    if ($event->end_date < $now) {
        $event_status_class = 'info';
        $event_status_text = $t['completed'];
    } elseif ($event->start_date <= $now) {
        $event_status_class = 'success';
        $event_status_text = $t['ongoing'];
    } else {
        $event_status_class = 'primary';
        $event_status_text = $t['upcoming'];
    }
} elseif ($event->status === 'cancelled') {
    $event_status_class = 'danger';
    $event_status_text = $t['cancelled'];
} elseif ($event->status === 'private') {
    $event_status_class = 'warning';
    $event_status_text = $t['private'];
}
?>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'sidebar'); ?>

<!-- main page content body part -->
<div id="main-content">
    <div class="container-fluid">
        <div class="block-header">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <h2><?php echo esc_html($event->title); ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/events'); ?>"><?php echo $t['events']; ?></a></li>
                        <li class="breadcrumb-item active"><?php echo $t['view']; ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <a href="<?php echo home_url('/event-manager-dashboard/event-edit?id=' . $event_id); ?>" class="btn btn-primary ml-2">
                            <i class="fa fa-edit"></i> <?php echo $t['edit_event']; ?>
                        </a>
                        <a href="<?php echo home_url('/event/' . $event->slug); ?>" class="btn btn-outline-info ml-2" target="_blank">
                            <i class="fa fa-external-link"></i> <?php echo $t['view_public_page']; ?>
                        </a>
                        <a href="<?php echo home_url('/event-manager-dashboard/events'); ?>" class="btn btn-outline-secondary">
                            <i class="fa fa-arrow-left"></i> <?php echo $t['back']; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- Event Header -->
                <div class="card">
                    <div class="body">
                        <div class="row">
                            <?php if ($event->logo_image): ?>
                            <div class="col-md-4">
                                <div class="bg-light rounded d-flex align-items-center justify-content-center p-3" style="height: 200px;">
                                    <img src="<?php echo esc_url(wp_get_attachment_url($event->logo_image)); ?>" alt="<?php echo esc_attr($event->title); ?>" class="img-fluid" style="max-height: 180px; object-fit: contain;">
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="col-md-8">
                                <span class="badge badge-<?php echo $event_status_class; ?> mb-2"><?php echo $event_status_text; ?></span>
                                <h3><?php echo esc_html($event->title); ?></h3>

                                <div class="mb-3">
                                    <?php foreach ($event_categories as $cat): ?>
                                        <span class="badge badge-secondary"><?php echo esc_html($cat->name); ?></span>
                                    <?php endforeach; ?>
                                </div>

                                <?php if ($event->excerpt): ?>
                                    <p class="text-muted"><?php echo esc_html($event->excerpt); ?></p>
                                <?php endif; ?>

                                <div class="row text-center mt-4">
                                    <div class="col-4">
                                        <h4 class="text-primary mb-0"><?php echo number_format($tickets_sold); ?></h4>
                                        <small class="text-muted"><?php echo $t['tickets_sold']; ?></small>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="text-success mb-0"><?php echo number_format($revenue, 2); ?></h4>
                                        <small class="text-muted"><?php echo $t['revenue']; ?></small>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="text-info mb-0"><?php echo $capacity_percent; ?>%</h4>
                                        <small class="text-muted"><?php echo $t['capacity']; ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Date & Time -->
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-clock-o"></i> <?php echo $t['date_and_time']; ?></h2>
                    </div>
                    <div class="body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="icon-box bg-primary text-white rounded p-3 mr-3">
                                        <i class="fa fa-calendar fa-2x"></i>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block"><?php echo $t['start']; ?></small>
                                        <strong><?php echo date('l, F j, Y', strtotime($event->start_date)); ?></strong>
                                        <?php if ($event->start_time): ?>
                                            <span class="d-block"><?php echo date('g:i A', strtotime($event->start_time)); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="icon-box bg-danger text-white rounded p-3 mr-3">
                                        <i class="fa fa-calendar-check-o fa-2x"></i>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block"><?php echo $t['end']; ?></small>
                                        <strong><?php echo date('l, F j, Y', strtotime($event->end_date)); ?></strong>
                                        <?php if ($event->end_time): ?>
                                            <span class="d-block"><?php echo date('g:i A', strtotime($event->end_time)); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if ($event->all_day_event): ?>
                            <span class="badge badge-info mt-2"><?php echo $t['all_day_event']; ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Location -->
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-map-marker"></i> <?php echo $t['location']; ?></h2>
                    </div>
                    <div class="body">
                        <?php if ($event->location_type === 'online'): ?>
                            <div class="d-flex align-items-center">
                                <div class="icon-box bg-info text-white rounded p-3 mr-3">
                                    <i class="fa fa-video-camera fa-2x"></i>
                                </div>
                                <div>
                                    <span class="badge badge-info mb-2"><?php echo $t['online_event']; ?></span>
                                    <?php if ($event->meeting_link): ?>
                                        <div>
                                            <a href="<?php echo esc_url($event->meeting_link); ?>" target="_blank" class="btn btn-sm btn-primary">
                                                <i class="fa fa-external-link"></i> <?php echo $t['join_meeting']; ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php elseif ($event->location_type === 'hybrid'): ?>
                            <span class="badge badge-warning mb-3"><?php echo $t['hybrid_event']; ?></span>
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="fa fa-building"></i> <?php echo $t['physical_location']; ?></h6>
                                    <?php
                                    // Build location string without duplication
                                    $hybrid_parts = array();
                                    if ($event->venue_name) {
                                        $hybrid_parts[] = $event->venue_name;
                                    }
                                    if ($event->venue_address && $event->venue_address !== $event->venue_name) {
                                        if (!$event->venue_name || stripos($event->venue_name, $event->venue_address) === false) {
                                            $hybrid_parts[] = $event->venue_address;
                                        }
                                    }
                                    $hybrid_combined = ($event->venue_name ?? '') . ' ' . ($event->venue_address ?? '');
                                    if ($event->venue_city && stripos($hybrid_combined, $event->venue_city) === false) {
                                        $hybrid_parts[] = $event->venue_city;
                                    }
                                    if ($event->venue_country && stripos($hybrid_combined, $event->venue_country) === false) {
                                        $hybrid_parts[] = $event->venue_country;
                                    }
                                    echo esc_html(implode(', ', $hybrid_parts));
                                    ?>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="fa fa-video-camera"></i> <?php echo $t['online_access']; ?></h6>
                                    <?php if ($event->meeting_link): ?>
                                        <a href="<?php echo esc_url($event->meeting_link); ?>" target="_blank" class="btn btn-sm btn-info">
                                            <i class="fa fa-external-link"></i> <?php echo $t['join_meeting']; ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted"><?php echo $t['no_meeting_link']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="d-flex align-items-start">
                                <div class="icon-box bg-success text-white rounded p-3 mr-3">
                                    <i class="fa fa-building fa-2x"></i>
                                </div>
                                <div>
                                    <span class="badge badge-success mb-2"><?php echo $t['physical_event']; ?></span>
                                    <?php
                                    // Build full location string without any duplication
                                    $location_parts = array();

                                    // Add venue name if exists
                                    if ($event->venue_name) {
                                        $location_parts[] = $event->venue_name;
                                    }

                                    // Add address only if different from venue name
                                    if ($event->venue_address && $event->venue_address !== $event->venue_name) {
                                        // Check if address is not already contained in venue_name
                                        if (!$event->venue_name || stripos($event->venue_name, $event->venue_address) === false) {
                                            $location_parts[] = $event->venue_address;
                                        }
                                    }

                                    // Add city only if not in venue_name or address
                                    $combined = ($event->venue_name ?? '') . ' ' . ($event->venue_address ?? '');
                                    if ($event->venue_city && stripos($combined, $event->venue_city) === false) {
                                        $location_parts[] = $event->venue_city;
                                    }

                                    // Add country only if not in venue_name or address
                                    if ($event->venue_country && stripos($combined, $event->venue_country) === false) {
                                        $location_parts[] = $event->venue_country;
                                    }

                                    if (!empty($location_parts)):
                                        $first_part = array_shift($location_parts);
                                    ?>
                                        <h5><?php echo esc_html($first_part); ?></h5>
                                        <?php if (!empty($location_parts)): ?>
                                            <p class="mb-0"><?php echo esc_html(implode(', ', $location_parts)); ?></p>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Description -->
                <?php if ($event->description): ?>
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-file-text-o"></i> <?php echo $t['description']; ?></h2>
                    </div>
                    <div class="body event-description">
                        <?php echo wpautop($event->description); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tickets -->
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-ticket"></i> <?php echo $t['tickets']; ?></h2>
                    </div>
                    <div class="body">
                        <?php if (empty($tickets)): ?>
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> <?php echo $t['no_tickets_configured']; ?>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th><?php echo $t['ticket_type']; ?></th>
                                            <th><?php echo $t['price']; ?></th>
                                            <th><?php echo $t['available']; ?></th>
                                            <th><?php echo $t['sold']; ?></th>
                                            <th><?php echo $t['status']; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tickets as $ticket): ?>
                                            <?php
                                            $available = $ticket->quantity == -1 ? $t['unlimited'] : ($ticket->quantity - $ticket->sold);
                                            $sold_percent = $ticket->quantity > 0 ? round(($ticket->sold / $ticket->quantity) * 100) : 0;
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo esc_html($ticket->name); ?></strong>
                                                    <?php if ($ticket->description): ?>
                                                        <br><small class="text-muted"><?php echo esc_html($ticket->description); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo sc_format_price($ticket->price); ?>
                                                </td>
                                                <td><?php echo is_numeric($available) ? number_format($available) : $available; ?></td>
                                                <td>
                                                    <?php echo number_format((int) ($ticket->sold ?? 0)); ?>
                                                    <?php if ($ticket->quantity > 0): ?>
                                                        <div class="progress" style="height: 5px; margin-top: 5px;">
                                                            <div class="progress-bar bg-primary" style="width: <?php echo $sold_percent; ?>%"></div>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($ticket->is_active): ?>
                                                        <span class="badge badge-success"><?php echo $t['active']; ?></span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary"><?php echo $t['inactive']; ?></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Attendees -->
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-users"></i> <?php echo $t['recent_attendees']; ?></h2>
                        <ul class="header-dropdown">
                            <li>
                                <a href="<?php echo home_url('/event-manager-dashboard/attendees?event_id=' . $event_id); ?>" class="btn btn-sm btn-primary">
                                    <?php echo $t['view_all']; ?> <i class="fa fa-arrow-right"></i>
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="body">
                        <?php if (empty($recent_attendees)): ?>
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> <?php echo $t['no_attendees_yet']; ?>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th><?php echo $t['name']; ?></th>
                                            <th><?php echo $t['email']; ?></th>
                                            <th><?php echo $t['ticket']; ?></th>
                                            <th><?php echo $t['status']; ?></th>
                                            <th><?php echo $t['date']; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_attendees as $attendee): ?>
                                            <tr>
                                                <td><?php echo esc_html($attendee->name); ?></td>
                                                <td><?php echo esc_html($attendee->email); ?></td>
                                                <td><?php echo esc_html($attendee->ticket_name ?: '-'); ?></td>
                                                <td>
                                                    <?php if (!empty($attendee->checked_in)): ?>
                                                        <span class="badge badge-success"><?php echo $t['checked_in']; ?></span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary"><?php echo $t['not_checked_in']; ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($attendee->created_at)); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Quick Actions -->
                <div class="card">
                    <div class="header bg-primary">
                        <h2 class="text-white"><i class="fa fa-bolt"></i> <?php echo $t['quick_actions']; ?></h2>
                    </div>
                    <div class="body">
                        <a href="<?php echo home_url('/event-manager-dashboard/event-edit?id=' . $event_id); ?>" class="btn btn-primary btn-block mb-2">
                            <i class="fa fa-edit"></i> <?php echo $t['edit_event']; ?>
                        </a>
                        <a href="<?php echo home_url('/event-manager-dashboard/attendee-add?event_id=' . $event_id); ?>" class="btn btn-success btn-block mb-2">
                            <i class="fa fa-user-plus"></i> <?php echo $t['add_attendee']; ?>
                        </a>
                        <a href="<?php echo home_url('/event-manager-dashboard/attendees?event_id=' . $event_id); ?>" class="btn btn-info btn-block mb-2">
                            <i class="fa fa-users"></i> <?php echo $t['view_attendees']; ?>
                        </a>
                        <a href="<?php echo home_url('/event-manager-dashboard/scanner?event_id=' . $event_id); ?>" class="btn btn-warning btn-block mb-2">
                            <i class="fa fa-qrcode"></i> <?php echo $t['checkin_scanner']; ?>
                        </a>
                        <a href="<?php echo home_url('/event/' . $event->slug); ?>" class="btn btn-outline-secondary btn-block" target="_blank">
                            <i class="fa fa-external-link"></i> <?php echo $t['view_public_page']; ?>
                        </a>
                    </div>
                </div>

                <!-- Event Details -->
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-info-circle"></i> <?php echo $t['event_details']; ?></h2>
                    </div>
                    <div class="body">
                        <table class="table table-sm">
                            <tr>
                                <td><strong><?php echo $t['status']; ?></strong></td>
                                <td><span class="badge badge-<?php echo $event_status_class; ?>"><?php echo $event_status_text; ?></span></td>
                            </tr>
                            <tr>
                                <td><strong><?php echo $t['created']; ?></strong></td>
                                <td><?php echo date('M d, Y', strtotime($event->created_at)); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php echo $t['updated']; ?></strong></td>
                                <td><?php echo date('M d, Y', strtotime($event->updated_at)); ?></td>
                            </tr>
                            <?php if ($event->total_capacity): ?>
                            <tr>
                                <td><strong><?php echo $t['capacity']; ?></strong></td>
                                <td><?php echo number_format($event->total_capacity); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($event->registration_deadline): ?>
                            <tr>
                                <td><strong><?php echo $t['deadline']; ?></strong></td>
                                <td><?php echo date('M d, Y H:i', strtotime($event->registration_deadline)); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td><strong><?php echo $t['tracking']; ?></strong></td>
                                <td>
                                    <?php if ($event->attendance_tracking): ?>
                                        <span class="badge badge-success"><?php echo $t['enabled']; ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary"><?php echo $t['disabled']; ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Speakers -->
                <?php if (!empty($speakers)): ?>
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-microphone"></i> <?php echo $t['speakers']; ?></h2>
                    </div>
                    <div class="body">
                        <?php foreach ($speakers as $speaker): ?>
                            <div class="d-flex align-items-center mb-3">
                                <?php if (!empty($speaker->image)): ?>
                                    <img src="<?php echo esc_url(wp_get_attachment_url($speaker->image)); ?>" alt="<?php echo esc_attr($speaker->name); ?>" class="rounded-circle mr-3" style="width: 50px; height: 50px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mr-3" style="width: 50px; height: 50px;">
                                        <?php echo strtoupper(substr($speaker->name, 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <strong><?php echo esc_html($speaker->name); ?></strong>
                                    <?php if (!empty($speaker->title)): ?>
                                        <br><small class="text-muted"><?php echo esc_html($speaker->title); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Organizers -->
                <?php if (!empty($organizers)): ?>
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-building"></i> <?php echo $t['organizers']; ?></h2>
                    </div>
                    <div class="body">
                        <?php foreach ($organizers as $organizer): ?>
                            <div class="d-flex align-items-center mb-3">
                                <?php if ($organizer->logo): ?>
                                    <img src="<?php echo esc_url(wp_get_attachment_url($organizer->logo)); ?>" alt="<?php echo esc_attr($organizer->name); ?>" class="rounded mr-3" style="width: 50px; height: 50px; object-fit: contain;">
                                <?php else: ?>
                                    <div class="rounded bg-secondary text-white d-flex align-items-center justify-content-center mr-3" style="width: 50px; height: 50px;">
                                        <i class="fa fa-building"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <strong><?php echo esc_html($organizer->name); ?></strong>
                                    <?php if ($organizer->email): ?>
                                        <br><small class="text-muted"><?php echo esc_html($organizer->email); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Sales Summary -->
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-bar-chart"></i> <?php echo $t['sales_summary']; ?></h2>
                    </div>
                    <div class="body">
                        <div class="text-center mb-3">
                            <h2 class="text-success mb-0"><?php echo sc_format_price($revenue, false); ?></h2>
                            <small class="text-muted"><?php echo $t['total_revenue']; ?></small>
                        </div>

                        <div class="progress mb-2" style="height: 20px;">
                            <div class="progress-bar bg-success" style="width: <?php echo $capacity_percent; ?>%">
                                <?php echo $capacity_percent; ?>%
                            </div>
                        </div>
                        <small class="text-muted"><?php echo number_format($tickets_sold); ?> <?php echo $t['tickets_sold_of']; ?> <?php echo $total_capacity ? number_format($total_capacity) : $t['unlimited']; ?> <?php echo $t['tickets_sold_text']; ?></small>

                        <hr>

                        <a href="<?php echo home_url('/event-manager-dashboard/reports?event_id=' . $event_id); ?>" class="btn btn-outline-primary btn-block">
                            <i class="fa fa-pie-chart"></i> <?php echo $t['view_full_report']; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.icon-box {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.event-description img {
    max-width: 100%;
    height: auto;
}
.card .header h2 {
    font-size: 16px;
}
</style>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
