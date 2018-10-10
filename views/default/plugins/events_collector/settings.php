<?php
/**
 * Events_collector plugin settings.
 */

$period = $vars['entity']->period;
$delete = $vars['entity']->delete;
if (!$period) {
	$period = 'monthly';
}

if (!$delete) {
	$delete = 'monthly';
}
?>
<div>
	<?php

		echo elgg_echo('events_collector:period') . ' ';
		echo elgg_view('input/select', array(
			'name' => 'params[period]',
			'options_values' => array(
				'weekly' => elgg_echo('interval:weekly'),
				'monthly' => elgg_echo('interval:monthly'),
				'yearly' => elgg_echo('interval:yearly'),
			),
			'value' => $period,
		));
	?>
</div>
<div>
	<?php

		echo elgg_echo('events_collector:delete') . ' ';
		echo elgg_view('input/select', array(
			'name' => 'params[delete]',
			'options_values' => array(
				'weekly' => elgg_echo('events_collector:week'),
				'monthly' => elgg_echo('events_collector:month'),
				'yearly' => elgg_echo('events_collector:year'),
				'never' => elgg_echo('events_collector:never'),
			),
			'value' => $delete,
		));
	?>
</div>
