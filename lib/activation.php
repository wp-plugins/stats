<?php

function stats_activate() {
	// Trigger footer test
	wp_remote_get(get_bloginfo('siteurl'));
}

function stats_deactivate() {
	//delete_option('stats_options');
	//delete_option('stats_dashboard_widget');
}

register_activation_hook(__FILE__, 'stats_activate');
register_deactivation_hook(__FILE__, 'stats_deactivate');
