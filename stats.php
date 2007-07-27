<?php
/*
Plugin Name: WordPress.com Stats
Plugin URI: http://wordpress.org/extend/plugins/stats/
Description: Tracks views, post/page views, referrers, and clicks. Requires a WordPress.com API key.
Author: Andy Skelton
Version: 1.1.1

Requires WordPress 2.1 or later. Not for use with WPMU.

Looking for a way to hide the gif? Don't use "display:none"! Put this in your stylesheet:
img#wpstats{width:0px;height:0px;overflow:hidden;}

*/

// If you hardcode a WP.com API key here, all key config screens will be hidden.
$stats_wpcom_api_key = '';

function stats_get_api_key() {
	if ( !empty( $GLOBALS['stats_wpcom_api_key'] ) )
		return $GLOBALS['stats_wpcom_api_key'];

	return stats_get_option('api_key');
}

function stats_set_api_key($api_key) {
	stats_set_option('api_key', $api_key);
}

function stats_get_options() {
	$options = get_option( 'stats_options' );

	if ( !isset( $options['version'] ) || $options['version'] < STATS_VERSION ) {
		$options = stats_upgrade_options( $options );

		stats_set_options( $options );
	}

	return $options;
}

function stats_get_option( $option ) {
	$options = stats_get_options();
	
	if ( isset( $options[$option] ) )
		return $options[$option];

	return null;
}

function stats_set_option( $option, $value ) {
	$options = stats_get_options();
	
	$options[$option] = $value;
	
	stats_set_options($options);
}

function stats_set_options($options) {
	update_option( 'stats_options', $options );
}

function stats_upgrade_options( $options ) {
	$defaults = array(
		'host'         => '',
		'path'         => '',
		'blog_id'      => false,
	);

	if ( is_array( $options ) && !empty( $options ) )
		$options = array_merge( $defaults, $options );
	else
		$options = $defaults;

	$options['version'] = STATS_VERSION;

	return $options;
}

function stats_footer() {
	global $wp_the_query, $current_user;

	$options = stats_get_options();

	if ( !empty($current_user->ID) || empty($options['blog_id']) )
		return;

	$a['blog'] = $options['blog_id'];
	$a['v'] = 'ext';
	if ( ( $wp_the_query->is_single || $wp_the_query->is_page ) && !$wp_the_query->is_attachment )
		$a['post'] = $wp_the_query->get_queried_object_id();
	else
		$a['post'] = '0';

?>
<script src="http://stats.wordpress.com/e-<?php echo gmdate('YW'); ?>.js" type="text/javascript"></script>
<script type="text/javascript">
st_go({<?php echo stats_array($a); ?>});
var load_cmc = function(){linktracker_init(<?php echo "{$a['blog']},{$a['post']},2"; ?>);};
if ( typeof addLoadEvent != 'undefined' ) addLoadEvent(load_cmc);
else load_cmc();
</script>
<?php
}

function stats_array($kvs) {
	$kvs = apply_filters('stats_array', $kvs);
	$kvs = array_map('addslashes', $kvs);
	foreach ( $kvs as $k => $v )
		$jskvs[] = "$k:'$v'";
	return join(',', $jskvs);
}

function stats_admin_menu() {
	if ( stats_get_option('blog_id') ) {
		$hook = add_submenu_page('index.php', __('Blog Stats'), __('Blog Stats'), 'manage_options', 'stats', 'stats_reports_page');
		add_action("load-$hook", 'stats_reports_load');
	}
	$hook = add_submenu_page('plugins.php', __('WordPress.com Stats Plugin'), __('WordPress.com Stats'), 'manage_options', 'wpstats', 'stats_admin_page');
	add_action("load-$hook", 'stats_admin_load');
	add_action("admin_head-$hook", 'stats_admin_head');
	add_action('admin_notices', 'stats_admin_notices');
}

function stats_reports_load() {
	add_action('admin_head', 'stats_reports_head');
}

function stats_reports_head() {
?>
<style type="text/css">
	body { height: 100%; }
	#statsreport { height: 2500px; width: 100%; }
</style>
<?php
}

function stats_reports_page() {
	$blog_id = stats_get_option('blog_id');
	echo "<iframe id='statsreport' frameborder='0' src='http://dashboard.wordpress.com/wp-admin/index.php?page=estats&blog=$blog_id&noheader=true'></iframe>";
}

function stats_admin_load() {
	global $plugin_page;

	if ( ! empty( $_POST['action'] ) ) {
		switch( $_POST['action'] ) {
			case 'get_blog_id' :
				if ( isset( $_POST['usesavedkey'] ) )
					$key = get_option('wordpress_api_key');
				else $key = $_POST['api_key'];
				$blog_id = stats_get_blog_id( $key );
				wp_redirect( "plugins.php?page=$plugin_page" );
				exit;
		}
	}

	$options = stats_get_options();
	$api_key = stats_get_api_key();
	if ( empty( $options['blog_id'] ) && !empty( $api_key ) )
		stats_get_blog_id( $api_key );
}

function stats_admin_notices() {
	if ( stats_get_api_key() )
		return;
	echo "<div class='updated' style='background-color:#f66;'><p>" . sprintf(__('<a href="%s">WordPress.com Stats</a> needs attention: please enter an API key or disable the plugin.'), "plugins.php?page=wpstats") . "</p></div>";
}

function stats_admin_head() {
	?>
	<style type="text/css">
		#statserror {
			border: 1px solid #766;
			background-color: #d22;
			padding: 1em 3em;
		}
	</style>
	<?php
}

function stats_admin_page() {
	global $plugin_page;

	$options = stats_get_options();
	?>
	<div class="wrap">
		<h2><?php _e('WordPress.com Stats'); ?></h2>
		<div class="narrow">
<?php if ( !empty($options['error']) ) : ?>
			<div id='statserror'>
				<h3><?php _e('Error from last API Key attempt:'); ?></h3>
				<p><?php echo $options['error']; ?></p>
			</div>
<?php $options['error'] = false; stats_set_options($options); endif; ?>

<?php if ( empty( $options['blog_id'] ) ) : ?>
			<p><?php _e('The WordPress.com Stats Plugin is not working because it needs to be linked to a WordPress.com account.'); ?></p>

<?php	if ( empty( $GLOBALS['stats_wpcom_api_key'] ) ) : ?>
			<form action="plugins.php?page=<?php echo $plugin_page; ?>" method="post">
				<p><?php _e('Enter your WordPress.com API key to link this blog to your WordPress.com account. Be sure to use your own API key! Using any other key will lock you out of your stats. (<a href="http://wordpress.com/profile/">Get your key here.</a>)'); ?></p>
				<label for="api_key"><?php _e('API Key:'); ?> <input type="text" name="api_key" id="api_key" value="<?php echo $api_key; ?>" /></label>
				<input type="hidden" name="action" value="get_blog_id" />
				<p class="submit"><input type="submit" value="<?php _e('Save &raquo;'); ?>" /></p>
			</form>
<?php	else : ?>
			<p><?php _e('An API Key is present in the source code but it did not work.') ?></p>
<?php	endif; ?>

<?php else : ?>
			<p><?php _e('The WordPress.com Stats Plugin is configured and working.'); ?></p>
			<p><?php _e('Visitors who are logged in are not counted. (This means you.)'); ?></p>
			<p><?php printf(__('Visit <a href="%s">your Dashboard</a> to see your blog stats.'), 'index.php?page=stats'); ?></p>
<?php endif; ?>

		</div>
	</div>

	<?php
	stats_set_options( $options );
}

function stats_xmlrpc_methods( $methods ) {
	$my_methods = array(
		'wpStats.get_posts' => 'stats_get_posts',
		'wpStats.get_blog' => 'stats_get_blog'
	);

	return array_merge( $methods, $my_methods );
}

function stats_get_posts( $args ) {
	list( $post_ids ) = $args;
	
	$post_ids = array_map( 'intval', (array) $post_ids );
	$r = 'include=' . join(',', $post_ids);
	$posts = get_posts( $r );
	$_posts = array();

	foreach ( $post_ids as $post_id )
		$_posts[$post_id] = stats_get_post($post_id);

	return $_posts;
}

function stats_get_blog( ) {
	$home = parse_url( get_option('home') );
	return array(
		'host' => $home['host'],
		'path' => $home['path'],
		'name' => get_option('blogname'),
		'description' => get_option('blogdescription'),
		'siteurl' => get_option('siteurl'),
		'version' => STATS_VERSION
	);
}

function stats_get_post( $post_id ) {
	$post = get_post( $post_id );
	if ( empty( $post ) )
		$post = get_page( $post_id );
	return array(
		'id' => $post->ID,
		'permalink' => get_permalink($post->ID),
		'title' => $post->post_title,
		'type' => $post->post_type
	);
}

function stats_client() {
	require_once( ABSPATH . WPINC . '/class-IXR.php' );
	$client = new IXR_ClientMulticall( STATS_XMLRPC_SERVER );
	return $client;
}

function stats_add_call() {
	global $stats_xmlrpc_client;
	if ( empty($stats_xmlrpc_client) ) {
		$stats_xmlrpc_client = stats_client();
		ignore_user_abort(true);
		add_action('shutdown', 'stats_multicall_query');
	}

	$args = func_get_args();

	call_user_method_array( 'addCall', $stats_xmlrpc_client, $args );
}

function stats_multicall_query() {
	global $stats_xmlrpc_client;

	$stats_xmlrpc_client->query();
}

function stats_update_bloginfo() {
	stats_add_call(
		'wpStats.update_bloginfo',
		stats_get_api_key(),
		stats_get_option('blog_id'),
		stats_get_blog()
	);
}

function stats_update_post( $post_id ) {
	stats_add_call(
		'wpStats.update_postinfo',
		stats_get_api_key(),
		stats_get_option('blog_id'),
		stats_get_post($post_id)
	);
}

function stats_flush_posts() {
	stats_add_call(
		'wpStats.flush_posts',
		stats_get_api_key(),
		stats_get_option('blog_id')
	);
}

function stats_activity() {
	$options = stats_get_options();

	if ( $options['blog_id'] ) {
		?>
		<h3><?php _e('WordPress.com Blog Stats'); ?></h3>
		<p><?php printf(__('Visit %s to see your blog stats.'), '<a href="http://dashboard.wordpress.com/wp-admin/index.php?page=stats&blog=' . $options['blog_id'] . '">' . __('your Global Dashboard') . '</a>'); ?></p>
		<?php
	}
}

function stats_get_blog_id($api_key) {
	$options = stats_get_options();

	require_once( ABSPATH . WPINC . '/class-IXR.php' );

	$client = new IXR_Client( STATS_XMLRPC_SERVER );

	extract( parse_url( get_option( 'home' ) ) );

	$path = rtrim( $path, '/' );

	if ( empty( $path ) )
		$path = '/';

	$client->query( 'wpStats.get_blog_id', $api_key, stats_get_blog() );

	if ( $client->isError() ) {
		if ( $client->getErrorCode() == -32300 )
			$options['error'] = __('Your blog was unable to connect to WordPress.com. Please ask your host for help. (' . $client->getErrorMessage() . ')');
		else
			$options['error'] = $client->getErrorMessage();
		stats_set_options( $options );
		return false;
	} else {
		$options['error'] = false;
	}

	$response = $client->getResponse();

	$blog_id = isset($response['blog_id']) ? (int) $response['blog_id'] : false;

	$options[ 'host' ] = $host;
	$options[ 'path' ] = $path;
	$options[ 'blog_id' ] = $blog_id;

	stats_set_options( $options );

	stats_set_api_key( $api_key );

	return $blog_id;
}

function stats_activate() {
	$options = stats_get_options();

	if ( empty($options['blog_id']) && $api_key = stats_get_api_key() )
		stats_get_blog_id($api_key);
}

function stats_deactivate() {
	delete_option('stats_options');
}

// Boooooooooooring init stuff
register_activation_hook(__FILE__, 'stats_activate');
register_deactivation_hook(__FILE__, 'stats_deactivate');
add_action( 'admin_menu', 'stats_admin_menu' );
add_action( 'activity_box_end', 'stats_activity', 1 );

// Plant the tracking code in the footer
add_action( 'wp_footer', 'stats_footer', 101 );

// Tell HQ about changed settings
add_action( 'update_option_home', 'stats_update_bloginfo' );
add_action( 'update_option_siteurl', 'stats_update_bloginfo' );
add_action( 'update_option_blogname', 'stats_update_bloginfo' );
add_action( 'update_option_blogdescription', 'stats_update_bloginfo' );

// Tell HQ about changed posts
add_action( 'save_post', 'stats_update_post', 10, 1 );

// Tell HQ to drop all post info for this blog
add_action( 'update_option_permalink_structure', 'stats_flush_posts' );

// Teach the XMLRPC server how to dance properly
add_filter( 'xmlrpc_methods', 'stats_xmlrpc_methods' );

define( 'STATS_VERSION', '1' );
define( 'STATS_XMLRPC_SERVER', 'http://wordpress.com/xmlrpc.php' );

?>
