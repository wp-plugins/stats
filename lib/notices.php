<?php

function stats_admin_notices() {
	stats_notice_blog_id();
//	stats_notice_footer();
}

function stats_notice_blog_id() {
	if ( stats_get_api_key() || isset($_GET['page']) && $_GET['page'] == 'wpstats' )
		return;
	// Skip the notice if plugin activated network-wide.
	if ( function_exists('is_plugin_active_for_network') && is_plugin_active_for_network(plugin_basename(__FILE__)) )
		return;
	echo "<div class='updated' style='background-color:#f66;'><p>" . sprintf(__('<a href="%s">WordPress.com Stats</a> needs attention: please enter an API key or disable the plugin.', 'stats'), stats_admin_path()) . "</p></div>";
}

function stats_notice_footer() {
	if ( !stats_get_api_key() || stats_get_option('footer') )
		return;
	if ( function_exists('is_plugin_active_for_network') && is_plugin_active_for_network(plugin_basename(__FILE__)) )
		return;
	if ( strpos(wp_remote_get(get_bloginfo('siteurl')), 'stats_footer_test') ) {
		stats_set_option('footer', true);
		return;
	}
	echo "<div class='updated' style='background-color:#f66;'><p>" . __('WordPress.com Stats is unable to work properly because your theme seems to lack the necessary footer code. Usually this can be fixed by adding the following code just before &lt;/body&gt; in footer.php:', 'stats') . "</p><p><code>&lt;?php wp_footer(); ?&gt;</code></p></div>";
}

