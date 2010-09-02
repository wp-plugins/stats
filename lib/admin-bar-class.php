<?php
class WPCOM_Admin_Bar {
	var $user;
	var $menu;

	function WPCOM_Admin_Bar() {
		global $current_user;

		$this->user = new stdClass;
		$this->menu = new stdClass;

		/* Populate settings we need for the menu based on the current user. */
		$this->user->blogs = get_ordered_blogs_of_user( $current_user->id );
		$this->user->active_blog = get_active_blog_for_user( $current_user->id );
		$this->user->domain = ( $this->user->active_blog == 'username only' ) ? constant( 'PROTO' ) . 'dashboard.wordpress.com/' : trailingslashit( constant( 'PROTO' ) . $this->user->active_blog->domain . $this->user->active_blog->path );
		$this->user->account_domain = ( strpos( $this->user->domain, 'wordpress.com' ) ) ? $this->user->domain : constant( 'PROTO' ) . 'dashboard.wordpress.com/';
		$this->user->locale = ( '' != get_user_lang_code() ) ? get_user_lang_code() : 'en';
	}

	function add_menu( $args = array() ) {
		$defaults = array(
			'title' => false,
			'href' => false,
			'parent' => false, // false for a root menu, pass the ID value for a submenu of that menu.
			'id' => false, // defaults to a sanitized title value.
			'meta' => false // array of any of the following options: array( 'html' => '', 'class' => '', 'onclick' => '', target => '' );
		);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		if ( empty( $title ) )
			return false;

		/* Make sure we have a valid ID */
		if ( empty( $id ) )
			$id = esc_attr( sanitize_title( trim( $title ) ) );

		if ( !empty( $parent ) ) {
			/* Add the menu to the parent item */
			$child = array(
				'id' => $id,
				'title' => $title,
				'href' => $href
			);

			if ( !empty( $meta ) )
				$child['meta'] = $meta;

			$this->add_node( $parent, $this->menu, $child );
		} else {
			/* Add the menu item */
			$this->menu->{$id} = array(
				'title' => $title,
				'href' => $href
			);

			if ( !empty( $meta ) )
				$this->menu->{$id}['meta'] = $meta;
		}
	}

	function remove_menu( $id ) {
		return $this->remove_node( $id, $this->menu );
	}
	
	function render() {
	?>
		<div id="wpcombar" class="snap_nopreview no-grav">
			<div class="quicklinks">
				<ul>
					<?php foreach ( (array) $this->menu as $id => $menu_item ) : ?>
						<?php $this->recursive_render( $id, $menu_item ) ?>
					<?php endforeach; ?>
				</ul>
			</div>

			<div id="adminbarsearch-wrap">
				<form action="http://<?php echo $this->user->locale ?>.search.wordpress.com/" method="get" id="adminbarsearch">
					<input class="adminbar-input" name="q" id="q" type="text" value="<?php _e( 'Search WordPress.com' ); ?>" maxlength="150" onfocus="this.value=(this.value=='<?php _e( 'Search WordPress.com' ); ?>') ? '' : this.value;" onblur="this.value=(this.value=='') ? '<?php _e( 'Search WordPress.com' ); ?>' : this.value;" /> <button type="submit" class="adminbar-button"><span><?php _e('Search'); ?></span></button>
				</form>
			</div>
		</div>

		<?php
		/* Wipe the menu, might reduce memory usage, but probably not. */
		$this->menu = null;
	}

	/* Helpers */
	function recursive_render( $id, &$menu_item ) { ?>
		<?php $menuclass = ( !empty( $menu_item['children'] ) ) ? 'menupop ' : ''; ?>

		<li class="<?php echo $menuclass . "ab-$id" ?><?php if ( !empty( $menu_item['meta']['class'] ) ) : ?><?php echo ' ' . $menu_item['meta']['class'] ?><?php endif; ?>">
			<a href="<?php echo strip_tags( $menu_item['href'] ) ?>"<?php if ( !empty( $menu_item['meta']['onclick'] ) ) :?>onclick="<?php echo $menu_item['meta']['onclick'] ?>"<?php endif; ?><?php if ( !empty( $menu_item['meta']['target'] ) ) :?>target="<?php echo $menu_item['meta']['target'] ?>"<?php endif; ?>><?php if ( !empty( $menuclass ) ) : ?><span><?php endif; ?><?php echo $menu_item['title'] ?><?php if ( !empty( $menuclass ) ) : ?></span><?php endif; ?></a>

			<?php if ( !empty( $menu_item['children'] ) ) : ?>
			<ul>
				<?php foreach ( $menu_item['children'] as $child_id => $child_menu_item ) : ?>
					<?php $this->recursive_render( $child_id, $child_menu_item ); ?>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>

			<?php if ( !empty( $menu_item['meta']['html'] ) ) : ?>
				<?php echo $menu_item['meta']['html']; ?>
			<?php endif; ?>
		</li><?php
	}

	function add_node( $parent_id, &$menu, $child ) {
		foreach( $menu as $id => &$menu_item ) {
			if ( $parent_id == $id ) {
				$menu->{$parent_id}['children']->{$child['id']} = $child;
				$child = null;
				return true;
			}

			if ( !empty( $menu->{$id}['children'] ) )
				$this->add_node( $parent_id, $menu->{$id}['children'], $child );
		}
		$child = null;

		return false;
	}

	function remove_node( $id, &$menu ) {
		foreach( $menu as $menu_item_id => &$menu_item ) {
			if ( $menu_item_id == $id ) {
				$menu_item = null;
				return true;
			}

			if ( !empty( $menu->{$menu_item_id}['children'] ) )
				$this->remove_node( $id, $menu->{$menu_item_id}['children'] );
		}

		return false;
	}
	
	function load_user_locale_translations() {
		$this->need_to_change_locale = ( get_locale() != $this->user->locale );
		if ( !$this->need_to_change_locale ) return;
		$this->previous_translations = get_translations_for_domain( 'default' );
		$this->adminbar_locale_filter = lambda( '$_', '$GLOBALS["wpcom_adminbar"]->user->locale;' );
		unload_textdomain( 'default' );
		add_filter( 'locale', $this->adminbar_locale_filter );
		load_default_textdomain();
		$this->changed_locale = true;
	}

	function unload_user_locale_translations() {
		global $l10n;
		if ( !$this->changed_locale ) return;
		remove_filter( 'locale', $this->adminbar_locale_filter );
		$l10n['default'] = &$this->previous_translations;

	}
}
?>
