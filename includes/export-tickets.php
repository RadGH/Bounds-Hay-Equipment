<?php

add_action('admin_menu', 'bounds_register_export_page');

function bounds_register_export_page() {
    add_submenu_page(
        'edit.php?post_type=ticket',
        'Export Tickets',
        'Export Tickets',
        'manage_options',
        'export-tickets',
        'bounds_export_page_callback'
    );
}

add_action('admin_init', function() {
    if (isset($_GET['export_tickets'])) {

		$content = bounds_get_csv_data();
		$t = time();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="tickets_export_'.$t.'.csv"');
        header('Content-Length: ' . strlen($content));

        echo $content;

        exit;
    }
});

function bounds_get_feedback($ticket_id) {

	$feedbacks = get_posts([
		'post_type'      => 'ticket-feedback',
		'meta_key'       => 'ticket_id',
		'meta_value'     => $ticket_id,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
	]);
	$feedback_strings = [];
	foreach ($feedbacks as $feedback) {
		$status = get_field('status', $feedback->ID);
		$form_name = get_field('name', $feedback->ID);
		$feedback_strings[] = "{$feedback->ID} | {$status} | {$form_name}";
	}

	return implode(', ', $feedback_strings);

}

function bounds_get_csv_data() {
	$args = [
		'post_type'      => 'ticket',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
	];
	$start_date = !empty($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : null;
	$end_date = !empty($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : null;
	if ($start_date || $end_date) {
		$date_query = [];
		if ($start_date) {
			$date_query['after'] = $start_date;
		}
		if ($end_date) {
			$date_query['before'] = $end_date;
		}
		$date_query['inclusive'] = true;
		$args['date_query'] = [$date_query];
	}
	$tickets = get_posts($args);

	$ticket_names = ["status","type_of_request","severity","odometer","name","email","phone_number","body"];
	$headers = array_merge(["ID","Title"],$ticket_names);
    $headers = array_merge($headers,["asset_id","asset_title","asset_types","asset_notify_categories","Feedback History"]);
    $stream = fopen("php://temp","r+");
    fputcsv($stream,$headers);
	foreach ($tickets as $ticket) {
		$row = [];
		$row[] = $ticket->ID;
		$row[] = $ticket->post_title;
		foreach ($ticket_names as $name) {
			$val = get_field($name,$ticket->ID);
			$row[] = $val;
		}
		$asset_id = get_field("asset",$ticket->ID);
		if ($asset_id) {
			$row[] = $asset_id;
			$asset = get_post( $asset_id );
			$row[] = $asset->post_title;
			$nc = get_the_terms( $asset_id, 'notify-cat' );
			$c_names = wp_list_pluck($nc, 'name');
			$notify_cats = implode(",",$c_names);
			$row[] = $notify_cats;
			$as = get_the_terms( $asset_id, 'asset-type' );
			$a_names = wp_list_pluck($as, 'name');
			$asset_types = implode(",",$a_names);
			$row[] = $asset_types;
		} else {
			$row[] = "";
			$row[] = "";
			$row[] = "";
			$row[] = "";
		}
		$row[] = bounds_get_feedback($ticket->ID);
		fputcsv($stream,$row);
	}
    rewind($stream);
    $csv = stream_get_contents($stream);
    fclose($stream);
	return $csv;
}

function bounds_export_page_callback() {

    ?>
    <div class="wrap">
        <h1>Export Tickets</h1>
        <form method="get" action="edit.php">
            <input type="hidden" name="post_type" value="ticket" />
            <input type="hidden" name="page" value="export-tickets" />
            <input type="hidden" name="export_tickets" value="true" />

            <label for="start_date">Start Date:</label>
            <input type="date" name="start_date" id="start_date">

            <label for="end_date">End Date:</label>
            <input type="date" name="end_date" id="end_date">

            <button type="submit" class="button button-secondary">Export</button>
        </form>
        <hr />
    </div>
    <?php

}
