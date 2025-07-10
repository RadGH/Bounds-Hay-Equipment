<?php
/**
 * @global int $asset_id
 * @global WP_Term $asset_type
 *
 * @see BHE_Asset::get_asset_type_summary()
 */

?>
<table>
	<tr>
		<th>Make</th>
		<th>Model</th>
		<th>Year</th>
	</tr>
	<tr>
		<td><?php echo get_field( 'vehicle_make', $asset_id ); ?></td>
		<td><?php echo get_field( 'vehicle_model', $asset_id ); ?></td>
		<td><?php echo get_field( 'vehicle_year', $asset_id ); ?></td>
	</tr>
	
	<tr>
		<th>License Plate Number</th>
		<th>License Plate State</th>
	</tr>
	<tr>
		<td><?php echo get_field( 'license_plate_number', $asset_id ); ?></td>
		<td><?php echo get_field( 'license_plate_state', $asset_id ); ?></td>
	</tr>
	
	<tr>
		<th>Front Tire Size</th>
		<th>Back Tire Size</th>
	</tr>
	<tr>
		<td><?php echo get_field( 'tire_size_front', $asset_id ); ?></td>
		<td><?php echo get_field( 'tire_size_back', $asset_id ); ?></td>
	</tr>
	
	<tr>
		<th>Fuel Type</th>
	</tr>
	<tr>
		<td colspan="2"><?php echo get_field( 'fuel_type', $asset_id ); ?></td>
	</tr>
	
	<tr>
		<th>Usage Type</th>
	</tr>
	<tr>
		<td colspan="2"><?php echo get_field( 'usage_type', $asset_id ); ?></td>
	</tr>
	
	<tr>
		<th>Company Number</th>
	</tr>
	<tr>
		<td colspan="2"><?php echo get_field( 'company_number', $asset_id ); ?></td>
	</tr>
	
	<tr>
		<th>VIN</th>
	</tr>
	<tr>
		<td colspan="2"><?php echo get_field( 'vehicle_vin', $asset_id ); ?></td>
	</tr>
</table>
