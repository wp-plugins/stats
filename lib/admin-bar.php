<?php
/**
 * WordPress.com Admin Bar
 *
 * This code handles the building and rendering of the WordPress.com admin bar, visible
 * across all WordPress.com blogs.
 */
 
/**
 * wpcom_adminbar_init()
 *
 * Instantiate the admin bar class and set it up as a global for access elsewhere.
 */
function wpcom_adminbar_init() {
	global $current_user, $pagenow, $wpcom_adminbar;
	
	/* Set the protocol constant used throughout this code */
	if ( !defined( 'PROTO' ) )
		if ( is_ssl() ) define( 'PROTO', 'https://' ); else define( 'PROTO', 'http://' );

	/* Don't load the admin bar if the user is not logged in, or we are using press this */
	if ( !is_user_logged_in() || 'press-this.php' ==  $pagenow )
		return false;

	/* Set up the settings we need to render menu items */
	if ( !is_object( $current_user ) )
		$current_user = wp_get_current_user();

	/* Enqueue the JS files for the admin bar. */
	if ( is_user_logged_in() )
		wp_enqueue_script( 'jquery', false, false, false, true );
	
	/* Load the admin bar class code ready for instantiation */
	require( constant( 'WP_CONTENT_DIR' ) . '/mu-plugins/admin-bar/admin-bar-class.php' );

	/* Only load super admin menu code if the logged in user is a super admin */
	if ( is_super_admin() ) {
		require( constant( 'WP_CONTENT_DIR' ) . '/mu-plugins/admin-bar/admin-bar-debug.php' );
		require( constant( 'WP_CONTENT_DIR' ) . '/mu-plugins/admin-bar/admin-bar-superadmin.php' );
	}
	
	/* Initialize the admin bar */
	$wpcom_adminbar = new WPCOM_Admin_Bar();
}
add_action( 'init', 'wpcom_adminbar_init' );


/**
 * wpcom_adminbar_render()
 *
 * Render the admin bar to the page based on the $wpcom_adminbar->menu member var.
 * This is called very late on the footer actions so that it will render after anything else being
 * added to the footer.
 *
 * It includes the action "wpcom_before_admin_bar_render" which should be used to hook in and
 * add new menus to the admin bar. That way you can be sure that you are adding at most optimal point,
 * right before the admin bar is rendered. This also gives you access to the $post global, among others.
 */
function wpcom_adminbar_render() {
	global $wpcom_adminbar;

	if ( !is_object( $wpcom_adminbar ) )
		return false;
		
	$wpcom_adminbar->load_user_locale_translations();

	do_action( 'wpcom_before_admin_bar_render' );

	$wpcom_adminbar->render();

	do_action( 'wpcom_after_admin_bar_render' );
	
	$wpcom_adminbar->unload_user_locale_translations();	
}
add_action( 'wp_footer', 'wpcom_adminbar_render', 1000 );
add_action( 'admin_footer', 'wpcom_adminbar_render', 1000 );


/**
 * wpcom_adminbar_me_separator()
 *
 * Show the logged in user's gravatar as a separator.
 */
function wpcom_adminbar_me_separator() {
	global $wpcom_adminbar, $current_user;

	if ( !is_object( $wpcom_adminbar ) )
		return false;

	$wpcom_adminbar->add_menu( array( 'id' => 'me', 'title' => avatar_by_id( $current_user->ID, 16 ), 'href' => $wpcom_adminbar->user->account_domain . 'wp-admin/profile.php' ) );
}
add_action( 'wpcom_before_admin_bar_render', 'wpcom_adminbar_me_separator', 10 );


/**
 * wpcom_adminbar_my_account_menu()
 *
 * Use the $wpcom_adminbar global to add the "My Account" menu and all submenus.
 */
function wpcom_adminbar_my_account_menu() {
	global $wpcom_adminbar, $current_user;

	if ( !is_object( $wpcom_adminbar ) )
		return false;
	
	/* Add the 'My Account' menu */
	$wpcom_adminbar->add_menu( array( 'id' => 'my-account', 'title' => __( 'My Account' ), 'href' => $wpcom_adminbar->user->account_domain . 'wp-admin/profile.php' ) );

	/* Add the "My Account" sub menus */
	$wpcom_adminbar->add_menu( array( 'parent' => 'my-account', 'title' => __( 'New QuickPress Post' ), 'href' => 'http://wordpress.com/quickpress/' ) );
	$wpcom_adminbar->add_menu( array( 'parent' => 'my-account', 'title' => __( 'Edit My Profile' ), 'href' => $wpcom_adminbar->user->account_domain . 'wp-admin/profile.php' ) );
	$wpcom_adminbar->add_menu( array( 'parent' => 'my-account', 'title' => __( 'Read Freshly Pressed' ), 'href' => 'http://wordpress.com/fresh/' ) );
	$wpcom_adminbar->add_menu( array( 'parent' => 'my-account', 'title' => __( 'Read Posts I Like' ), 'href' => 'http://wordpress.com/likes/' ) );
	$wpcom_adminbar->add_menu( array( 'parent' => 'my-account', 'title' => __( 'Track My Comments' ), 'href' => $wpcom_adminbar->user->account_domain . 'wp-admin/index.php?page=my-comments') );
	$wpcom_adminbar->add_menu( array( 'parent' => 'my-account', 'title' => __( 'Global Dashboard' ), 'href' => constant( 'PROTO' ) . 'dashboard.wordpress.com/wp-admin/' ) );
	$wpcom_adminbar->add_menu( array( 'parent' => 'my-account', 'title' => __( 'Get Support' ), 'href' => 'http://' . $wpcom_adminbar->user->locale . '.support.wordpress.com/' ) );
	$wpcom_adminbar->add_menu( array( 'parent' => 'my-account', 'title' => __( 'Log Out' ), 'href' => constant( 'PROTO' ) . 'wordpress.com/wp-login.php?action=logout&amp;redirect_to=' . constant( 'PROTO' ) . 'wordpress.com/' ) );
	
//	$wpcom_adminbar->add_menu( array( 'parent' => 'my-account', 'title' => __( 'Blog Surfer' ),  	 'href' => $wpcom_adminbar->user->account_domain . 'wp-admin/index.php?page=friend-surfer' ) );
//	$wpcom_adminbar->add_menu( array( 'parent' => 'my-account', 'title' => __( 'Tag Surfer' ), 		 'href' => $wpcom_adminbar->user->account_domain . 'wp-admin/index.php?page=tag-surfer' ) );
//	$wpcom_adminbar->add_menu( array( 'parent' => 'my-account', 'title' => __( 'WordPress.com' ), 	 'href' => constant( 'PROTO' ) . 'wordpress.com' ) );
}
add_action( 'wpcom_before_admin_bar_render', 'wpcom_adminbar_my_account_menu', 20 );


/**
 * wpcom_adminbar_my_blogs_menu()
 *
 * Use the $wpcom_adminbar global to add the "My Blogs/[Blog Name]" menu and all submenus.
 */
function wpcom_adminbar_my_blogs_menu() {
	global $wpdb, $wpcom_adminbar;

	if ( !is_object( $wpcom_adminbar ) )
		return false;
	
	/* Remove the global dashboard */
	foreach ( (array) $wpcom_adminbar->user->blogs as $key => $blog ) {
		if ( 'dashboard.wordpress.com' == $blog->domain )
			unset( $wpcom_adminbar->user->blogs[$key] );
	}

	/* Add the 'My Dashboards' menu if the user has more than one blog. */
	if ( count( $wpcom_adminbar->user->blogs ) > 1 ) {
		$wpcom_adminbar->add_menu( array( 'id' => 'my-blogs', 'title' => __( 'My Blogs' ), 'href' => $wpcom_adminbar->user->account_domain ) );

		$default = staticize_subdomain( constant( 'PROTO' ) . 'en.wordpress.com/i/wpmini-blue.png' );

		$counter = 2;
		foreach ( (array) $wpcom_adminbar->user->blogs as $blog ) {
			$blogdomain = ( strstr( $blog->domain, '.wordpress.com' ) ) ? preg_replace( '!^https?://!', '', $blog->domain ) : preg_replace( '!^https?://!', '', $blog->siteurl );
			$blavatar = '<img src="' . esc_url( blavatar_url( blavatar_domain( $blog->siteurl ), 'img', 16, $default ) ) . '" alt="Blavatar" width="16" height="16" />';
			
			$marker = '';
			if ( strlen($blog->blogname) > 35 )
				$marker = '...';
				
			if ( empty( $blog->blogname ) )
				$blogname = $blog->domain;
			else
				$blogname = substr( $blog->blogname, 0, 35 ) . $marker;

			if ( !isset( $blog->visible ) || $blog->visible === true ) {
				$wpcom_adminbar->add_menu( array( 'parent' => 'my-blogs', 'id' => 'blog-' . $blog->userblog_id, 'title' => $blavatar . $blogname, 'href' => constant( 'PROTO' ) . $blogdomain . '/wp-admin/' ) );
				$wpcom_adminbar->add_menu( array( 'parent' => 'blog-' . $blog->userblog_id, 'id' => 'blog-' . $blog->userblog_id . '-d', 'title' => __( 'Dashboard' ), 'href' => constant( 'PROTO' ) . $blogdomain . '/wp-admin/' ) );
				$wpcom_adminbar->add_menu( array( 'parent' => 'blog-' . $blog->userblog_id, 'id' => 'blog-' . $blog->userblog_id . '-n', 'title' => __( 'New Post' ), 'href' => constant( 'PROTO' ) . $blogdomain . '/wp-admin/post-new.php' ) );
				$wpcom_adminbar->add_menu( array( 'parent' => 'blog-' . $blog->userblog_id, 'id' => 'blog-' . $blog->userblog_id . '-s', 'title' => __( 'Blog Stats' ), 'href' => constant( 'PROTO' ) . $blogdomain . '/wp-admin/index.php?page=stats' ) );
				$wpcom_adminbar->add_menu( array( 'parent' => 'blog-' . $blog->userblog_id, 'id' => 'blog-' . $blog->userblog_id . '-c', 'title' => __( 'Manage Comments' ), 'href' => constant( 'PROTO' ) . $blogdomain . '/wp-admin/edit-comments.php' ) );
				$wpcom_adminbar->add_menu( array( 'parent' => 'blog-' . $blog->userblog_id, 'id' => 'blog-' . $blog->userblog_id . '-v', 'title' => __( 'Read Blog' ), 'href' => constant( 'PROTO' ) . $blogdomain ) );
			}
			$counter++;
		}
		
		/* Add the "Manage Blogs" menu item */
		$wpcom_adminbar->add_menu( array( 'parent' => 'my-blogs', 'id' => 'manage-blogs', 'title' => __( 'Manage Blogs' ), 'href' => constant( 'PROTO' ) . 'dashboard.wordpress.com/wp-admin/index.php?page=my-blogs' ) );

	/* Add the 'My Dashboard' menu if the user only has one blog. */
	} else {
		$wpcom_adminbar->add_menu( array( 'id' => 'my-blogs', 'title' => __( 'My Blog' ), 'href' => $wpcom_adminbar->user->account_domain ) );

		$wpcom_adminbar->add_menu( array( 'parent' => 'my-blogs', 'id' => 'blog-1-d', 'title' => __( 'Dashboard' ), 'href' => $wpcom_adminbar->user->account_domain . 'wp-admin/' ) );
		$wpcom_adminbar->add_menu( array( 'parent' => 'my-blogs', 'id' => 'blog-1-n', 'title' => __( 'New Post' ), 'href' => $wpcom_adminbar->user->account_domain . 'wp-admin/post-new.php' ) );
		$wpcom_adminbar->add_menu( array( 'parent' => 'my-blogs', 'id' => 'blog-1-s', 'title' => __( 'Blog Stats' ), 'href' => $wpcom_adminbar->user->account_domain . 'wp-admin/index.php?page=stats' ) );
		$wpcom_adminbar->add_menu( array( 'parent' => 'my-blogs', 'id' => 'blog-1-c', 'title' => __( 'Manage Comments' ), 'href' => $wpcom_adminbar->user->account_domain . 'wp-admin/edit-comments.php' ) );
		$wpcom_adminbar->add_menu( array( 'parent' => 'my-blogs', 'id' => 'blog-1-v', 'title' => __( 'Read Blog' ), 'href' => $wpcom_adminbar->user->account_domain ) );
	}
}
add_action( 'wpcom_before_admin_bar_render', 'wpcom_adminbar_my_blogs_menu', 30 );


/**
 * wpcom_adminbar_blog_separator()
 *
 * Show the blavatar of the current blog as a separator.
 */
function wpcom_adminbar_blog_separator() {
	global $wpcom_adminbar, $current_user, $current_blog;

	if ( !is_object( $wpcom_adminbar ) || 1 == $current_blog->blog_id )
		return false;

	$default = staticize_subdomain( constant( 'PROTO' ) . 'en.wordpress.com/i/wpmini-blue.png' );

	$wpcom_adminbar->add_menu( array( 'id' => 'blog', 'title' => '<img class="avatar" src="' . esc_url( blavatar_url( blavatar_domain( 'http://' . $current_blog->domain ), 'img', 16, $default ) ) . '" alt="' . __( 'Current blog avatar' ) . '" width="16" height="16" />', 'href' => constant( 'PROTO' ) . $current_blog->domain ) );
}
add_action( 'wpcom_before_admin_bar_render', 'wpcom_adminbar_blog_separator', 40 );


/**
 * wpcom_adminbar_bloginfo_menu()
 *
 * Use the $wpcom_adminbar global to add a menu for blog info, accessable to all users.
 */
function wpcom_adminbar_bloginfo_menu() {
	global $wpcom_adminbar, $current_blog, $current_user;

	if ( !is_object( $wpcom_adminbar ) || 1 == $current_blog->blog_id )
		return false;

	/* Add the Blog Info menu */
	$wpcom_adminbar->add_menu( array( 'id' => 'bloginfo', 'title' => __( 'Blog Info' ), 'href' => '' ) );

	/* Add the submenu items */
	$wpcom_adminbar->add_menu( array( 'parent' => 'bloginfo', 'title' => __( 'Random Post' ), 'href' => '/?random' ) );

	$blogname = str_replace( '.wordpress.com', '', $current_blog->domain );
	$wpcom_adminbar->add_menu( array( 'parent' => 'bloginfo', 'title' => __( 'Get Shortlink' ), 'href' => '', 'meta' => array( 'onclick' => 'javascript:function wpcomshort() { var url=document.location;var links=document.getElementsByTagName(&#39;link&#39;);var found=0;for(var i = 0, l; l = links[i]; i++){if(l.getAttribute(&#39;rel&#39;)==&#39;shortlink&#39;) {found=l.getAttribute(&#39;href&#39;);break;}}if (!found) {for (var i = 0; l = document.links[i]; i++) {if (l.getAttribute(&#39;rel&#39;) == &#39;shortlink&#39;) {found = l.getAttribute(&#39;href&#39;);break;}}}if (found) {prompt(&#39;URL:&#39;, found);} else {alert(&#39;No shortlink available for this page&#39;); } return false; } wpcomshort();' ) ) );
	$wpcom_adminbar->add_menu( array( 'parent' => 'bloginfo', 'title' => __( 'Report as spam' ), 'href' => "http://wordpress.com/report-spam/?url={$current_blog->domain}" ) );
	$wpcom_adminbar->add_menu( array( 'parent' => 'bloginfo', 'title' => __( 'Report as mature' ), 'href' => "http://wordpress.com/report-mature/?url={$current_blog->domain}" ) );
}
add_action( 'wpcom_before_admin_bar_render', 'wpcom_adminbar_bloginfo_menu', 50 );

/**
 * wpcom_adminbar_edit_menu()
 *
 * Use the $wpcom_adminbar global to add the "Edit Post" menu when viewing a single post.
 */
function wpcom_adminbar_edit_menu() {
	global $post, $wpcom_adminbar;
	
	if ( !is_object( $wpcom_adminbar ) || 1 == $current_blog->blog_id )
		return false;

	if ( !is_single() && !is_page() )
		return false;

	if ( !$post_type_object = get_post_type_object( $post->post_type ) )
		return false;

	if ( !current_user_can( $post_type_object->cap->edit_post, $post->ID ) )
		return false;

	remove_filter( 'option_siteurl', 'override_siteurl' );

	$wpcom_adminbar->add_menu( array( 'id' => 'edit', 'title' => __( 'Edit' ), 'href' => get_edit_post_link( $post->ID ) ) );

	add_filter( 'option_siteurl', 'override_siteurl' );
}
add_action( 'wpcom_before_admin_bar_render', 'wpcom_adminbar_edit_menu', 100 );


/**
 * wpcom_adminbar_freshly_pressed_bump_menu()
 *
 * Some WordPress.com members are designated Freshly Pressed bumpers who bump posts
 * to Freshly Pressed but are not site admins. This will add the menu for those users.
 */
function wpcom_adminbar_freshly_pressed_bump_menu() {
	global $wpcom_adminbar, $current_user, $site_editors;

	if ( !is_object( $wpcom_adminbar ) )
		return false;

	if ( is_super_admin() || !in_array( $current_user->user_login, (array) $site_editors ) )
		return false;
		
	require_once ( constant( 'WP_CONTENT_DIR' ) . '/mu-plugins/admin-bar/admin-bar-superadmin.php' );
	wpcom_adminbar_superadmin_bumppost_menu( true ); // pass true to render on top level of admin bar.
}
add_action( 'wpcom_before_admin_bar_render', 'wpcom_adminbar_freshly_pressed_bump_menu', 80 );


/**
 * wpcom_adminbar_css()
 *
 * Load up the CSS needed to render the admin bar nice and pretty.
 */
function wpcom_adminbar_css() {
	global $pagenow;

	if ( !is_user_logged_in() )
		return false;

	if ( 'press-this.php' == $pagenow )
		return;

	/* Wish we could use wp_enqueue_style() here, but it will not let us pass GET params to the stylesheet correctly. */
	?>
	<link rel="stylesheet" href="<?php echo staticize_subdomain( content_url() ) . '/mu-plugins/admin-bar/admin-bar-css.php?t=' . get_current_theme() . '&amp;a=' . is_admin() . '&amp;p=' . is_ssl() . '&amp;sa=' . is_super_admin() . '&amp;td=' . get_user_text_direction() ?>" type="text/css" />
	<!--[if IE 6]><style type="text/css">#wpcombar, #wpcombar .menupop a span, #wpcombar .menupop ul li a:hover, #wpcombar .myaccount a, .quicklinks a:hover,#wpcombar .menupop:hover { background-image: none !important; } #wpcombar .myaccount a { margin-left:0 !important; padding-left:12px !important;}</style><![endif]-->
	<style type="text/css" media="print">#wpcombar { display:none; }</style><?php
}
add_action( 'wp_head', 'wpcom_adminbar_css' );
add_action( 'admin_head', 'wpcom_adminbar_css' );

/**
 * wpcom_adminbar_js()
 *
 * Load up the JS needed to allow the admin bar to function correctly.
 */
function wpcom_adminbar_js() {
	global $wpcom_adminbar;

	if ( !is_object( $wpcom_adminbar ) )
		return false;

	?>
	<script type="text/javascript">
/*	<![CDATA[ */
		function pressthis(step) {if (step == 1) {if(navigator.userAgent.indexOf('Safari') >= 0) {Q=getSelection();}else {if(window.getSelection)Q=window.getSelection().toString();else if(document.selection)Q=document.selection.createRange().text;else Q=document.getSelection().toString();}} else {location.href='<?php echo $wpcom_adminbar->user->account_domain; ?>wp-admin/post-new.php?text='+encodeURIComponent(Q.toString())+'&amp;popupurl='+encodeURIComponent(location.href)+'&amp;popuptitle='+encodeURIComponent(document.title);}}
		function toggle_query_list() { var querylist = document.getElementById( 'querylist' );if( querylist.style.display == 'block' ) {querylist.style.display='none';} else {querylist.style.display='block';}}

		jQuery( function() {
			(function(jq){jq.fn.hoverIntent=function(f,g){var cfg={sensitivity:7,interval:100,timeout:0};cfg=jq.extend(cfg,g?{over:f,out:g}:f);var cX,cY,pX,pY;var track=function(ev){cX=ev.pageX;cY=ev.pageY;};var compare=function(ev,ob){ob.hoverIntent_t=clearTimeout(ob.hoverIntent_t);if((Math.abs(pX-cX)+Math.abs(pY-cY))<cfg.sensitivity){jq(ob).unbind("mousemove",track);ob.hoverIntent_s=1;return cfg.over.apply(ob,[ev]);}else{pX=cX;pY=cY;ob.hoverIntent_t=setTimeout(function(){compare(ev,ob);},cfg.interval);}};var delay=function(ev,ob){ob.hoverIntent_t=clearTimeout(ob.hoverIntent_t);ob.hoverIntent_s=0;return cfg.out.apply(ob,[ev]);};var handleHover=function(e){var p=(e.type=="mouseover"?e.fromElement:e.toElement)||e.relatedTarget;while(p&&p!=this){try{p=p.parentNode;}catch(e){p=this;}}if(p==this){return false;}var ev=jQuery.extend({},e);var ob=this;if(ob.hoverIntent_t){ob.hoverIntent_t=clearTimeout(ob.hoverIntent_t);}if(e.type=="mouseover"){pX=ev.pageX;pY=ev.pageY;jq(ob).bind("mousemove",track);if(ob.hoverIntent_s!=1){ob.hoverIntent_t=setTimeout(function(){compare(ev,ob);},cfg.interval);}}else{jq(ob).unbind("mousemove",track);if(ob.hoverIntent_s==1){ob.hoverIntent_t=setTimeout(function(){delay(ev,ob);},cfg.timeout);}}};return this.mouseover(handleHover).mouseout(handleHover);};})(jQuery);
			;(function(jq){jq.fn.superfish=function(op){var sf=jq.fn.superfish,c=sf.c,jqarrow=jq([''].join('')),over=function(){var jqjq=jq(this),menu=getMenu(jqjq);clearTimeout(menu.sfTimer);jqjq.showSuperfishUl().siblings().hideSuperfishUl();},out=function(){var jqjq=jq(this),menu=getMenu(jqjq),o=sf.op;clearTimeout(menu.sfTimer);menu.sfTimer=setTimeout(function(){o.retainPath=(jq.inArray(jqjq[0],o.jqpath)>-1);jqjq.hideSuperfishUl();if(o.jqpath.length&&jqjq.parents(['li.',o.hoverClass].join('')).length<1){over.call(o.jqpath);}},o.delay);},getMenu=function(jqmenu){var menu=jqmenu.parents(['ul.',c.menuClass,':first'].join(''))[0];sf.op=sf.o[menu.serial];return menu;},addArrow=function(jqa){jqa.addClass(c.anchorClass).append(jqarrow.clone());};return this.each(function(){var s=this.serial=sf.o.length;var o=jq.extend({},sf.defaults,op);o.jqpath=jq('li.'+o.pathClass,this).slice(0,o.pathLevels).each(function(){jq(this).addClass([o.hoverClass,c.bcClass].join(' ')).filter('li:has(ul)').removeClass(o.pathClass);});sf.o[s]=sf.op=o;jq('li:has(ul)',this)[(jq.fn.hoverIntent&&!o.disableHI)?'hoverIntent':'hover'](over,out).each(function(){if(o.autoArrows)addArrow(jq('>a:first-child',this));}).not('.'+c.bcClass).hideSuperfishUl();var jqa=jq('a',this);jqa.each(function(i){var jqli=jqa.eq(i).parents('li');jqa.eq(i).focus(function(){over.call(jqli);}).blur(function(){out.call(jqli);});});o.onInit.call(this);}).each(function(){var menuClasses=[c.menuClass];if(sf.op.dropShadows&&!(jq.browser.msie&&jq.browser.version<7))menuClasses.push(c.shadowClass);jq(this).addClass(menuClasses.join(' '));});};var sf=jq.fn.superfish;sf.o=[];sf.op={};sf.IE7fix=function(){var o=sf.op;if(jq.browser.msie&&jq.browser.version>6&&o.dropShadows&&o.animation.opacity!=undefined) this.toggleClass(sf.c.shadowClass+'-off');};sf.c={bcClass:'sf-breadcrumb',menuClass:'sf-js-enabled',anchorClass:'sf-with-ul',arrowClass:'sf-sub-indicator',shadowClass:'sf-shadow'};sf.defaults={hoverClass:'sfHover',pathClass:'overideThisToUse',pathLevels:1,delay:600,animation:{opacity:'show'},speed:100,autoArrows:false,dropShadows:false,disableHI:false,onInit:function(){},onBeforeShow:function(){},onShow:function(){},onHide:function(){}};jq.fn.extend({hideSuperfishUl:function(){var o=sf.op,not=(o.retainPath===true)?o.jqpath:'';o.retainPath=false;var jqul=jq(['li.',o.hoverClass].join(''),this).add(this).not(not).removeClass(o.hoverClass).find('>ul').hide().css('visibility','hidden');o.onHide.call(jqul);return this;},showSuperfishUl:function(){var o=sf.op,sh=sf.c.shadowClass+'-off',jqul=this.addClass(o.hoverClass).find('>ul:hidden').css('visibility','visible');sf.IE7fix.call(jqul);o.onBeforeShow.call(jqul);jqul.animate(o.animation,o.speed,function(){sf.IE7fix.call(jqul);o.onShow.call(jqul);});return this;}});})(jQuery);

			<?php if ( is_single() ) : ?>
			if ( jQuery(this).width() < 1100 ) jQuery("#adminbarsearch").hide();
			<?php endif; ?>
				
			jQuery( '#wpcombar li.ab-my-account, #wpcombar li.ab-bloginfo' ).mouseover( function() {
				if ( jQuery(this).hasClass( 'ab-my-account' ) ) jQuery('#wpcombar li.ab-me > a').addClass('hover');
				if ( jQuery(this).hasClass( 'ab-bloginfo' ) ) jQuery('#wpcombar li.ab-blog > a').addClass('hover');
			});
			
			jQuery( '#wpcombar li.ab-my-account, #wpcombar li.ab-bloginfo' ).mouseout( function() {
				if ( jQuery(this).hasClass( 'ab-my-account' ) ) jQuery('#wpcombar li.ab-me > a').removeClass('hover');
				if ( jQuery(this).hasClass( 'ab-bloginfo' ) ) jQuery('#wpcombar li.ab-blog > a').removeClass('hover');
			});			
			
			<?php if ( is_single() ) : ?>
			jQuery(window).resize( function() {
				if ( jQuery(this).width() < 1100 )
					jQuery("#adminbarsearch").hide();
				
				if ( jQuery(this).width() > 1100 )
					jQuery("#adminbarsearch").show();
			});
			<?php endif; ?>
			
			jQuery( '#wpcombar ul ul li a' ).mouseover( function() {
				var root = jQuery(this).parents('div.quicklinks ul > li');
				var par = jQuery(this).parent();
				var children = par.children('ul');
				if ( root.hasClass('ab-sadmin') )
					jQuery(children[0]).css('<?php echo( 'rtl' == get_user_text_direction() ? 'left'  : 'right' ); ?>',par.parents('ul').width() - 1 +'px' );
				else
					jQuery(children[0]).css('<?php echo( 'rtl' == get_user_text_direction() ? 'right'  : 'left' ); ?>',par.parents('ul').width() +'px' );
				
				jQuery(children[0]).css('top', '0' );
			});
			
			<?php if ( is_user_logged_in() ) : // Hash links scroll 32px back so admin bar doesn't cover. ?>
				if ( window.location.hash ) window.scrollBy(0,-32);
			<?php endif; ?>
		
		});

		jQuery( function() { 
			jQuery('#wpcombar').appendTo('body'); 
			jQuery("#wpcombar ul").superfish();
		});

		/*	]]> */
	</script><?php
}
add_action( 'wp_footer', 'wpcom_adminbar_js' );
add_action( 'admin_footer', 'wpcom_adminbar_js' );


/**
 * wpcom_adminbar_ajax_render()
 *
 * Return a rendered admin bar via AJAX for use on pages that do not run inside the
 * WP environment. Used on bbPress forum pages to show the admin bar.
 */
function wpcom_adminbar_ajax_render() {
	global $wpcom_adminbar;
	
	wpcom_adminbar_js();
	wpcom_adminbar_css();
	wpcom_adminbar_render();
	die;
}
add_action( 'wp_ajax_adminbar_render', 'wpcom_adminbar_ajax_render' );

/* This is temporary until Yoav fixes up rtl support for the admin bar in the forums */
if ( !function_exists( 'get_user_text_direction' ) ) {
	function get_user_text_direction() { 
		return 'ltr'; 
	}
}
?>