<?php

/**
 * This template displays the content for the Ticket Feedback post type.
 * This is used when an admin requests feedback to an existing ticket, after the ticket author follows the link sent to their email.
 * @global int $ticket_feedback_id
 */

$ticket_id = get_field( 'ticket_id', $ticket_feedback_id );
$asset_id = get_field( 'asset', $ticket_id );

// Check feedback status
$feedback_status = get_post_meta( get_the_ID(), 'status', true );

if ( $feedback_status === 'Closed' ) {
	// Display a message that the feedback has been sent
	echo '<p>Your feedback has been received. No further action is needed.</p>';
	echo '<p>Thank you.</p>';
	
	return;
}

echo '<div class="feedback-details">';

// echo '<div class="detail-item detail-ticket-id">Ticket ID: ' . esc_html( $ticket_id ) . '</div>';

echo '<div class="detail-item detail-equipment-title">Equipment: ' . ($asset_id ? esc_html(get_the_title( $asset_id )) : '<em>Not Specified</em>' ) . '</div>';


// Display the asset's post thumbnail
if ( has_post_thumbnail($asset_id) ) {
	echo '<div class="detail-item detail-ass-thumbnail">';
	echo '<div class="asset-thumbnail">';
	echo get_the_post_thumbnail( $asset_id, 'medium' );
	echo '</div>';
	echo '</div>';
}

// Display the original message body
$body = get_field( 'body', $ticket_id );

echo '<div class="detail-item detail-ticket-body">';
echo '<h3>Original Ticket Description:</h3>';
echo wpautop($body);
echo '</div>';

// Display the ticket's post thumbnail
if ( has_post_thumbnail($ticket_id) ) {
	echo '<div class="detail-item detail-ticket-thumbnail">';
	echo '<div class="thumbnail">';
	echo get_the_post_thumbnail( $ticket_id, 'medium' );
	echo '</div>';
	echo '</div>';
}

echo '</div>';

// Display the Gravity Form
BHE_Ticket_Feedback::get_instance()->display_feedback_form( $ticket_feedback_id );
