<?php

function stats_footer() {
	global $wp_the_query, $current_user;

	$options = stats_get_options();

	echo "<!--stats_footer_test-->";

	if ( !$options['footer'] )
		stats_set_option('footer', true);

	if ( empty($options['blog_id']) )
		return;

	if ( !$options['reg_users'] && !empty($current_user->ID) )
		return;

	$a['blog'] = $options['blog_id'];
	$a['v'] = 'ext';
	if ( $wp_the_query->is_single || $wp_the_query->is_page )
		$a['post'] = $wp_the_query->get_queried_object_id();
	else
		$a['post'] = '0';

	$http = $_SERVER['HTTPS'] ? 'https' : 'http';
?>
<script src="<?php echo $http; ?>://stats.wordpress.com/e-<?php echo gmdate('YW'); ?>.js" type="text/javascript"></script>
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
	global $current_user;
	$roles = stats_get_option('roles');
	$cap = 'administrator';
	foreach ( $roles as $role ) {
		if ( current_user_can($role) ) {
			$cap = $role;
			break;
		}
	}
	if ( stats_get_option('blog_id') ) {
		$hook = add_submenu_page('index.php', __('Site Stats', 'stats'), __('Site Stats', 'stats'), $role, 'stats', 'stats_reports_page');
		add_action("load-$hook", 'stats_reports_load');
	}
	$parent = stats_admin_parent();
	$hook = add_submenu_page($parent, __('WordPress.com Stats Plugin', 'stats'), __('WordPress.com Stats', 'stats'), 'manage_options', 'wpstats', 'stats_admin_page');
	add_action("load-$hook", 'stats_admin_load');
	add_action("admin_head-$hook", 'stats_admin_head');
	add_action('admin_notices', 'stats_admin_notices');
}

function stats_admin_parent() {
	if ( function_exists('is_multisite') && is_multisite() ) {
		$menus = get_site_option( 'menu_items' );
		if ( isset($menus['plugins']) && $menus['plugins'] )
			return 'plugins.php';
		else
			return 'options-general.php';
	} else {
		return 'plugins.php';
	}
}

function stats_admin_path() {
	$parent = stats_admin_parent();
	return "$parent?page=wpstats";
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
	if ( isset( $_GET['dashboard'] ) )
		return stats_dashboard_widget_content();
	$blog_id = stats_get_option('blog_id');
	$key = stats_get_api_key();
	$day = isset( $_GET['day'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_GET['day'] ) ? $_GET['day'] : false;
	$q = array(
		'noheader' => 'true',
		'proxy' => '',
		'page' => 'stats',
		'key' => $key,
		'day' => $day,
		'blog' => $blog_id,
		'charset' => get_option('blog_charset'),
	);
	$args = array(
		'view' => array('referrers', 'postviews', 'searchterms', 'clicks', 'post', 'table'),
		'numdays' => 'int',
		'day' => 'date',
		'unit' => array(1, 7, 31),
		'summarize' => null,
		'post' => 'int',
		'width' => 'int',
		'height' => 'int',
		'data' => 'data',
	);
	foreach ( $args as $var => $vals ) {
		if ( ! isset($_GET[$var]) )
			continue;
		if ( is_array($vals) ) {
			if ( in_array($_GET[$var], $vals) )
				$q[$var] = $_GET[$var];
		} elseif ( $vals == 'int' ) {
			$q[$var] = intval($_GET[$var]);
		} elseif ( $vals == 'date' ) {
			if ( preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET[$var]) )
				$q[$var] = $_GET[$var];
		} elseif ( $vals == null ) {
			$q[$var] = '';
		} elseif ( $vals == 'data' ) {
			if ( substr($_GET[$var], 0, 9) == 'index.php' )
				$q[$var] = $_GET[$var];
		}
	}

	if ( isset( $_GET['chart'] ) ) {
		if ( preg_match('/^[a-z0-9-]+$/', $_GET['chart']) )
			$url = "https://dashboard.wordpress.com/wp-includes/charts/{$_GET['chart']}.php";
	} else {
		$url = "https://dashboard.wordpress.com/wp-admin/index.php";
	}

	$url = add_query_arg($q, $url);

	$get = wp_remote_get($url, array('timeout'=>300));

	if ( is_wp_error($get) || empty($get['body']) ) {
		$http = $_SERVER['HTTPS'] ? 'https' : 'http';
		$day = $day ? "&amp;day=$day" : '';
		echo "<iframe id='statsreport' frameborder='0' src='$http://dashboard.wordpress.com/wp-admin/index.php?page=estats&amp;blog=$blog_id&amp;noheader=true$day'></iframe>";
	} else {
		$body = convert_post_titles($get['body']);
		$body = convert_swf_urls($body);
		echo $body;
	}
	if ( isset( $_GET['noheader'] ) )
		die;
}

function convert_swf_urls($html) {
	$swf_url = trailingslashit( plugins_url( '', STATS_FILE ) ) . 'open-flash-chart.swf?data=';
	$html = preg_replace('!(<param name="movie" value="|<embed src=")(.+?)&data=!', "$1$swf_url", $html);
	return $html;
}

function convert_post_titles($html) {
	global $wpdb, $stats_posts;
	$pattern = "<span class='post-(\d+)-link'>.*?</span>";
	if ( ! preg_match_all("!$pattern!", $html, $matches) )
		return $html;
	$posts = get_posts(array(
		'include' => implode(',', $matches[1]),
		'post_type' => 'any',
		'numberposts' => -1,
	));
	foreach ( $posts as $post )
		$stats_posts[$post->ID] = $post;
	$html = preg_replace_callback("!$pattern!", 'convert_post_title', $html);
	return $html;
}

function convert_post_title($matches) {
	global $stats_posts;
	$post_id = $matches[1];
	if ( isset($stats_posts[$post_id]) )
		return '<a href="'.get_permalink($post_id).'" target="_blank">'.get_the_title($post_id).'</a>';
	return $matches[0];
}

function stats_admin_load() {
	if ( ! empty( $_POST['action'] ) && $_POST['_wpnonce'] == wp_create_nonce('stats') ) {
		switch( $_POST['action'] ) {
			case 'reset' :
				stats_set_options(array());
				wp_redirect( stats_admin_path() );
				exit;

			case 'enter_key' :
				stats_check_key( $_POST['api_key'] );
				wp_redirect( stats_admin_path() );
				exit;

			case 'add_or_replace' :
				$key_check = stats_get_option('key_check');
				stats_set_api_key($key_check[0]);
				if ( isset($_POST['add']) ) {
					stats_get_blog_id($key_check[0]);
				} else {
					extract( parse_url( get_option( 'home' ) ) );
					$path = rtrim( $path, '/' );
					if ( empty( $path ) )
						$path = '/';
					$options = stats_get_options();
					if ( isset($_POST['recover']) )
						$options['blog_id'] = intval($_POST['recoverblog']);
					else
						$options['blog_id'] = intval($_POST['blog_id']);
					$options['api_key'] = $key_check[0];
					$options['host'] = $host;
					$options['path'] = $path;
					stats_set_options($options);
					stats_update_bloginfo();
				}
				if ( stats_get_option('blog_id') )
					stats_set_option('key_check', false);
				wp_redirect( stats_admin_path() );
				exit;

			case 'save_options' :
				$options = stats_get_options();
				$options['wp_me'] = isset($_POST['wp_me']) && $_POST['wp_me'];
				$options['reg_users'] = isset($_POST['reg_users']) && $_POST['reg_users'];

				$options['roles'] = array('administrator');
				foreach ( get_editable_roles() as $role => $details )
					if ( isset($_POST["role_$role"]) && $_POST["role_$role"] )
						$options['roles'][] = $role;

				stats_set_options($options);
				wp_redirect( stats_admin_path() );
				exit;
		}
	}

	$options = stats_get_options();
	if ( empty( $options['blog_id']) && empty( $options['key_check'] ) && stats_get_api_key() )
		stats_check_key( stats_get_api_key() );
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
	$options = stats_get_options();
	?>
	<div class="wrap">
		<h2><?php _e('WordPress.com Stats', 'stats'); ?></h2>
		<div class="narrow">
<?php if ( !empty($options['error']) ) : ?>
			<div id='statserror'>
				<h3><?php _e('Error from last API Key attempt:', 'stats'); ?></h3>
				<p><?php echo $options['error']; ?></p>
			</div>
<?php $options['error'] = false; stats_set_options($options); endif; ?>

<?php if ( empty($options['blog_id']) && !empty($options['key_check']) ) : ?>
			<p><?php printf(__('The API key "%1$s" belongs to the WordPress.com account "%2$s". If you want to use a different account, please <a href="%3$s">enter the correct API key</a>.', 'stats'), $options['key_check'][0], $options['key_check'][1], wp_nonce_url('?page=wpstats&action=reset', 'stats')); ?></p>
			<p><?php _e('Note: the API key you use determines who will be registered as the "owner" of this blog in the WordPress.com database. Please choose your key accordingly. Do not use a temporary key.', 'stats'); ?></p>

<?php	if ( !empty($options['key_check'][2]) ) : ?>
			<form method="post">
			<?php wp_nonce_field('stats'); ?>
			<input type="hidden" name="action" value="add_or_replace" />
<?php
		$domainpath = preg_replace('|.*://|', '', get_bloginfo('siteurl'));
		foreach ( $options['key_check'][2] as $blog ) {
			if ( trailingslashit("{$blog[domain]}{$blog[path]}") == trailingslashit($domainpath) )
				break;
			else
				unset($blog);
		}
?>

			<h3><?php _e('Recommended Action', 'stats'); ?></h3>
<?php		if ( isset($blog) ) : ?>
			<input type='hidden' name='recoverblog' value='<?php echo $blog['userblog_id']; ?>' />
			<p><?php _e('It looks like you have installed Stats on a blog with this URL before. You can recover the stats history from that blog here.', 'stats'); ?></p>
			<p><input type="submit" name="recover" value="<?php echo js_escape(__('Recover stats', 'stats')); ?>" /></p>
<?php		else : ?>
			<p><?php _e('It looks like this blog has never had stats before. There is no record of its URL in the WordPress.com database.', 'stats'); ?></p>
			<p><input type="submit" name="add" value="<?php echo js_escape(__('Add this blog to my WordPress.com account', 'stats')); ?>" /></p>
<?php		endif; ?>

			<h3><?php _e('Recover other stats', 'stats'); ?></h3>
			<p><?php _e("Have you relocated this blog from a different URL? You may opt to have this blog take over the stats history from any other self-hosted blog associated with your WordPress.com account. This is appropriate if this blog had a different URL in the past. The WordPress.com database will rename its records to match this blog's URL.", 'stats'); ?></p>
			<p>
			<select name="blog_id">
				<option selected="selected" value="0"><?php _e('Select a blog', 'stats'); ?></option>
<?php		foreach ( $options['key_check'][2] as $blog ) : ?>
				<option value="<?php echo $blog['userblog_id']; ?>"><?php echo $blog['domain'] . $blog['path']; ?></option>
<?php		endforeach; ?>
			</select>
			<input type="submit" name="replace" value="<?php echo js_escape(__('Take over stats history', 'stats')); ?>" />
			</p>
			</form>

<?php	else : ?>
			<form method="post">
			<?php wp_nonce_field('stats'); ?>
			<input type="hidden" name="action" value="add_or_replace" />
			<h3><?php _e('Add blog to WordPress.com account', 'stats'); ?></h3>
			<p><?php _e("This blog will be added to your WordPress.com account. You will be able to allow other WordPress.com users to see your stats if you like.", 'stats'); ?></p>
			<p><input type="submit" name="add" value="<?php echo esc_attr(__('Add blog to WordPress.com', 'stats')); ?>" /></p>
			</form>
<?php	endif; ?>

<?php elseif ( empty( $options['blog_id'] ) ) : ?>
			<p><?php _e('The WordPress.com Stats Plugin is not working because it needs to be linked to a WordPress.com account.', 'stats'); ?></p>

			<form action="<?php echo stats_admin_path() ?>" method="post">
				<?php wp_nonce_field('stats'); ?>
				<p><?php _e('Enter your WordPress.com API key to link this blog to your WordPress.com account. Be sure to use your own API key! Using any other key will lock you out of your stats. (<a href="http://wordpress.com/profile/">Get your key here.</a>)', 'stats'); ?></p>
				<label for="api_key"><?php _e('API Key:', 'stats'); ?> <input type="text" name="api_key" id="api_key" value="" /></label>
				<input type="hidden" name="action" value="enter_key" />
				<p class="submit"><input type="submit" value="<?php _e('Save &raquo;', 'stats'); ?>" /></p>
			</form>
<?php else : ?>
			<p><?php printf(__('Visit <a href="%s">your Dashboard</a> to see your site stats.', 'stats'), 'index.php?page=stats'); ?></p>
			<p><?php printf(__('You can also see your stats, plus grant access for others to see them, on <a href="https://dashboard.wordpress.com/wp-admin/index.php?page=stats&blog=%s">your WordPress.com dashboard</a>.', 'stats'), $options['blog_id']); ?></p>
			<h3><?php _e('Options', 'stats'); ?></h3>
			<form action="<?php echo stats_admin_path() ?>" method="post">
			<input type='hidden' name='action' value='save_options' />
			<?php wp_nonce_field('stats'); ?>
			<table id="menu" class="form-table">
			<tr valign="top"><th scope="row"><label for="wp_me"><?php _e( 'Registered users' , 'stats'); ?></label></th>
			<td><label><input type='checkbox'<?php checked($options['reg_users']); ?> name='reg_users' id='reg_users' /> <?php _e("Count the page views of registered users who are logged in.", 'stats'); ?></label></td>
			<tr valign="top"><th scope="row"><label for="wp_me"><?php _e( 'Shortlinks' , 'stats'); ?></label></th>
			<td><label><input type='checkbox'<?php checked($options['wp_me']); ?> name='wp_me' id='wp_me' /> <?php _e("Publish WP.me <a href='http://wp.me/sf2B5-shorten'>shortlinks</a> as metadata. This is a free service from WordPress.com.", 'stats'); ?></label></td>
			</tr>
			<tr valign="top"><th scope="row"><?php _e( 'Report visibility' , 'stats'); ?></th>
			<td>
				<?php _e('Select the roles that will be able to view stats reports.', 'stats'); ?><br/>
<?php	$stats_roles = stats_get_option('roles');
	foreach ( get_editable_roles() as $role => $details ) : ?>
				<label><input type='checkbox' <?php if ( $role == 'administrator' ) echo "disabled='disabled' "; ?>name='role_<?php echo $role; ?>'<?php checked($role == 'administrator' || in_array($role, $stats_roles)); ?> /> <?php echo translate_user_role($details['name']); ?></label><br/>
<?php	endforeach; ?>
			</tr>
			</table>
			<p class="submit"><input type='submit' class='button-primary' value='<?php echo esc_attr(__('Save options', 'stats')); ?>' /></p>
			</form>
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
	$blog = array(
		'host' => $home['host'],
		'path' => $home['path'],
		'name' => get_option('blogname'),
		'description' => get_option('blogdescription'),
		'siteurl' => get_option('siteurl'),
		'gmt_offset' => get_option('gmt_offset'),
		'version' => STATS_VERSION
	);
	return array_map('esc_html', $blog);
}

function stats_get_post( $post_id ) {
	$post = get_post( $post_id );
	if ( empty( $post ) )
		$post = get_page( $post_id );
	$_post = array(
		'id' => $post->ID,
		'permalink' => get_permalink($post->ID),
		'title' => $post->post_title,
		'type' => $post->post_type
	);
	return array_map('esc_html', $_post);
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
	if ( !in_array( get_post_type($post_id), array('post', 'page', 'attachment') ) )
		return;

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

function stats_check_key($api_key) {
	$options = stats_get_options();

	require_once( ABSPATH . WPINC . '/class-IXR.php' );

	$client = new IXR_Client( STATS_XMLRPC_SERVER );

	$client->query( 'wpStats.check_key', $api_key, stats_get_blog() );

	if ( $client->isError() ) {
		if ( $client->getErrorCode() == -32300 )
			$options['error'] = __('Your blog was unable to connect to WordPress.com. Please ask your host for help. (' . $client->getErrorMessage() . ')', 'stats');
		else
			$options['error'] = $client->getErrorMessage();
		stats_set_options( $options );
		return false;
	} else {
		$options['error'] = false;
	}

	$options['key_check'] = $client->getResponse();
	stats_set_options($options);

	return true;
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
			$options['error'] = __('Your blog was unable to connect to WordPress.com. Please ask your host for help. (' . $client->getErrorMessage() . ')', 'stats');
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

function stats_get_csv( $table, $args = null ) {
	$blog_id = stats_get_option('blog_id');
	$key = stats_get_api_key();

	if ( !$blog_id || !$key )
		return array();

	$defaults = array( 'end' => false, 'days' => false, 'limit' => 3, 'post_id' => false, 'summarize' => '' );

	$args = wp_parse_args( $args, $defaults );
	$args['table'] = $table;
	$args['blog_id'] = $blog_id;
	$args['api_key'] = $key;

	$stats_csv_url = add_query_arg( $args, 'http://stats.wordpress.com/csv.php' );

	$key = md5( $stats_csv_url );

	// Get cache
	$stats_cache = get_option( 'stats_cache' );
	if ( !$stats_cache || !is_array($stats_cache) )
		$stats_cache = array();

	// Return or expire this key
	if ( isset($stats_cache[$key]) ) {
		$time = key($stats_cache[$key]);
		if ( time() - $time < 300 )
			return $stats_cache[$key][$time];
		unset( $stats_cache[$key] );
	}

	$stats_rows = array();
	do {
		if ( !$stats = stats_get_remote_csv( $stats_csv_url ) )
			break;

		$labels = array_shift( $stats );

		if ( 0 === stripos( $labels[0], 'error' ) )
			break;

		$stats_rows = array();
		for ( $s = 0; isset($stats[$s]); $s++ ) {
			$row = array();
			foreach ( $labels as $col => $label )
				$row[$label] = $stats[$s][$col];
			$stats_rows[] = $row;
		}
	} while(0);

	// Expire old keys
	foreach ( $stats_cache as $k => $cache )
		if ( !is_array($cache) || 300 < time() - key($cache) )
			unset($stats_cache[$k]);

	// Set cache
	$stats_cache[$key] = array( time() => $stats_rows );
	update_option( 'stats_cache', $stats_cache );

	return $stats_rows;
}

function stats_get_remote_csv( $url ) {
	$url = clean_url( $url, null, 'url' );

	// Yay!
	if ( ini_get('allow_url_fopen') ) {
		$fp = @fopen($url, 'r');
		if ( $fp ) {
			//stream_set_timeout($fp, $timeout); // Requires php 4.3
			$data = array();
			while ( $remote_read = fgetcsv($fp, 1000) )
				$data[] = $remote_read;
			fclose($fp);
			return $data;
		}
	}

	// Boo - we need to use wp_remote_fopen for maximium compatibility
	if ( !$csv = wp_remote_fopen( $url ) )
		return false;

	return stats_str_getcsv( $csv );
}

// rather than parsing the csv and its special cases, we create a new file and do fgetcsv on it.
function stats_str_getcsv( $csv ) {
	if ( !$temp = tmpfile() ) // tmpfile() automatically unlinks
		return false;

	$data = array();

	fwrite($temp, $csv, strlen($csv));
	fseek($temp, 0);
	while ( false !== $row = fgetcsv($temp, 1000) )
		$data[] = $row;
	fclose($temp);

	return $data;
}

if ( !function_exists('number_format_i18n') ) {
	function number_format_i18n( $number, $decimals = null ) { return number_format( $number, $decimals ); }
}

if ( !function_exists('wpme_dec2sixtwo') ) {
	function wpme_dec2sixtwo( $num ) {
		$index = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$out = "";

		if ( $num < 0 ) {
			$out = '-';
			$num = abs($num);
		}

		for ( $t = floor( log10( $num ) / log10( 62 ) ); $t >= 0; $t-- ) {
			$a = floor( $num / pow( 62, $t ) );
			$out = $out . substr( $index, $a, 1 );
			$num = $num - ( $a * pow( 62, $t ) );
		}

		return $out;
	}
}

if ( ! function_exists('wpme_get_shortlink') ) :
function wpme_get_shortlink( $id = 0, $context = 'post', $allow_slugs = true ) {
	global $wp_query;

	$blog_id = stats_get_option('blog_id');

	if ( 'query' == $context ) {
		if ( is_singular() ) {
			$id = $wp_query->get_queried_object_id();
			$context = 'post';
		} elseif ( is_front_page() ) {
			$context = 'blog';
		} else {
			return '';
		}
	}

	if ( 'blog' == $context ) {
		if ( empty($id) )
			$id = $blog_id;
		return 'http://wp.me/' . wpme_dec2sixtwo($id);
	}

	$post = get_post($id);

	if ( empty($post) )
			return '';

	$post_id = $post->ID;
	$type = '';

	if ( $allow_slugs && 'publish' == $post->post_status && 'post' == $post->post_type && strlen($post->post_name) <= 8 && false === strpos($post->post_name, '%')
		&& false === strpos($post->post_name, '-') ) {
		$id = $post->post_name;
		$type = 's';
	} else {
		$id = wpme_dec2sixtwo($post_id);
		if ( 'page' == $post->post_type )
			$type = 'P';
		elseif ( 'post' == $post->post_type )
			$type = 'p';
		elseif ( 'attachment' == $post->post_type )
			$type = 'a';
	}

	if ( empty($type) )
		return '';

	return 'http://wp.me/' . $type . wpme_dec2sixtwo($blog_id) . '-' . $id;
}

function wpme_shortlink_wp_head() {
	global $wp_query;

	$shortlink = wpme_get_shortlink(0, 'query');
	echo '<link rel="shortlink" href="' . $shortlink . '" />';
}

function wpme_shortlink_header() {
	global $wp_query;

	if ( headers_sent() )
		return;

	$shortlink = wpme_get_shortlink(0, 'query');

	header('Link: <' . $shortlink . '>; rel=shortlink');
}

function wpme_get_shortlink_html($html, $post_id) {
	$url = wpme_get_shortlink($post_id);
	$html .= '<input id="shortlink" type="hidden" value="' . $url . '" /><a href="#" class="button" onclick="prompt(&#39;URL:&#39;, jQuery(\'#shortlink\').val()); return false;">' . __('Get Shortlink', 'stats') . '</a>';
	return $html;
}

function wpme_get_shortlink_handler($shortlink, $id, $context, $allow_slugs) {
	return wpme_get_shortlink($id, $context, $allow_slugs);
}

if ( stats_get_option('wp_me') ) {
	if ( ! function_exists('wp_get_shortlink') ) {
		// Register these only for WP < 3.0.
		add_action('wp_head', 'wpme_shortlink_wp_head');
		add_action('wp', 'wpme_shortlink_header');
		add_filter( 'get_sample_permalink_html', 'wpme_get_shortlink_html', 10, 2 );
	} else {
		// Register a shortlink handler for WP >= 3.0.
		add_filter('get_shortlink', 'wpme_get_shortlink_handler', 10, 4);
	}
}

endif;

if ( !function_exists( 'esc_html' ) ):
	function esc_html( $string ) {
		return wp_specialchars( $string );
	}
endif;

function stats_load_translations() {
	load_plugin_textdomain( 'stats', null, basename( dirname( __FILE__ ) ) . '/languages' );
}

