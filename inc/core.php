<?php

/*
 * MapPress Core
 */

class MapPress {

	var $directory = '';

	var $directory_uri = '';

	var $map = false;

	var $mapgroup_id = false;

	var $map_count = 0;

	var $has_main_map = false;

	var $mapped_post_types = false;

	var $options = array();

	function __construct() {
		add_action('init', array($this, 'setup'));
		$this->plugin_fixes();
	}

	function setup() {
		$this->setup_directories();
		$this->setup_options();
		$this->setup_scripts();
		$this->setup_post_types();
		$this->setup_query();
		$this->setup_pre_get_map();
		$this->setup_ajax();
		$this->setup_canonical();
		$this->init();
	}

	function init() {
		do_action('mappress_init');
	}

	function setup_directories() {
		$this->directory = apply_filters('mappress_directory', TEMPLATEPATH . '/inc');
		$this->directory_uri = apply_filters('mappress_directory_uri', get_template_directory_uri());
	}

	function setup_options() {
		$options = get_option('mappress_settings');
		if($options && isset($options['mappress_settings'])) {
			$this->options = $options['mappress_settings'];
		} else {
			$this->options = false;
		}
		return $this->options;
	}

	function setup_scripts() {
		add_action('wp_enqueue_scripts', array($this, 'scripts'), 2);
		add_action('admin_footer', array($this, 'scripts'));
	}

	function scripts() {	
		/*
		 * Libraries
		 */
		wp_register_script('imagesloaded', get_template_directory_uri() . '/lib/jquery.imagesloaded.min.js', array('jquery'));
		wp_register_script('underscore', get_template_directory_uri() . '/lib/underscore-min.js', array(), '1.4.3');
		wp_register_script('mapbox-js', get_template_directory_uri() . '/lib/mapbox.js', array(), '0.6.7');
		wp_enqueue_style('mapbox', get_template_directory_uri() . '/lib/mapbox.css', array(), '0.6.7');
		wp_register_script('d3js', get_template_directory_uri() . '/lib/d3.v2.min.js', array('jquery'), '3.0.5');

		/*
		 * Local
		 */
		wp_enqueue_script('mappress', get_template_directory_uri() . '/inc/js/mappress.js', array('mapbox-js', 'underscore', 'jquery'), '0.2.1');
		wp_enqueue_script('mappress.groups', get_template_directory_uri() . '/inc/js/groups.js', array('mappress', 'underscore'), '0.1.16');

		wp_enqueue_script('mappress.hash', get_template_directory_uri() . '/inc/js/hash.js', array('mappress', 'underscore'), '0.0.4');

		wp_enqueue_script('mappress.geocode', get_template_directory_uri() . '/inc/js/geocode.js', array('mappress', 'd3js', 'underscore'), '0.0.4');
		wp_enqueue_script('mappress.filterLayers', get_template_directory_uri() . '/inc/js/filter-layers
			.js', array('mappress', 'underscore'), '0.0.8.1');
		wp_enqueue_script('mappress.ui', get_template_directory_uri() . '/inc/js/ui.js', array('mappress'), '0.0.7');
		wp_enqueue_style('mappress', get_template_directory_uri() . '/inc/css/mappress.css', array(), '0.0.1.2');

		wp_localize_script('mappress', 'mappress_localization', array(
			'ajaxurl' => admin_url('admin-ajax.php'),
			'more_label' => __('More', 'mappress')
		));

		wp_localize_script('mappress.geocode', 'mappress_labels', array(
			'search_placeholder' => __('Find a location', 'mappress'),
			'results_title' => __('Results', 'mappress'),
			'clear_search' => __('Close search', 'mappress'),
			'not_found' => __('Nothing found, try something else.', 'mappress')
		));

		wp_localize_script('mappress.groups', 'mappress_groups', array(
			'ajaxurl' => admin_url('admin-ajax.php'),
			'more_label' => __('More', 'mappress')
		));

		wp_localize_script('mappress.hash', 'mappress_hash', array(
			'enable' => $this->use_hash()
		));
	}

	function setup_post_types() {
		$this->register_post_types();
		$this->mapped_post_types();
		add_action('admin_menu', array($this, 'admin_menu'));
	}

	function register_post_types() {
		/*
		 * Map
		 */
		$labels = array( 
			'name' => __('Maps', 'mappress'),
			'singular_name' => __('Map', 'mappress'),
			'add_new' => __('Add new map', 'mappress'),
			'add_new_item' => __('Add new map', 'mappress'),
			'edit_item' => __('Edit map', 'mappress'),
			'new_item' => __('New map', 'mappress'),
			'view_item' => __('View map'),
			'search_items' => __('Search maps', 'mappress'),
			'not_found' => __('No map found', 'mappress'),
			'not_found_in_trash' => __('No map found in the trash', 'mappress'),
			'menu_name' => __('Maps', 'mappress')
		);

		$args = array(
			'labels' => $labels,
			'hierarchical' => true,
			'description' => __('MapPress Maps', 'mappress'),
			'supports' => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes'),
			'rewrite' => array('slug' => 'maps'),
			'public' => true,
			'show_in_menu' => true,
			'menu_position' => 4,
			'has_archive' => true,
			'exclude_from_search' => true,
			'capability_type' => 'page'
		);

		register_post_type('map', $args);

		/*
		 * Map group
		 */
		$labels = array( 
			'name' => __('Map groups', 'mappress'),
			'singular_name' => __('Map group', 'mappress'),
			'add_new' => __('Add new map group', 'mappress'),
			'add_new_item' => __('Add new map group', 'mappress'),
			'edit_item' => __('Edit map group', 'mappress'),
			'new_item' => __('New map group', 'mappress'),
			'view_item' => __('View map group', 'mappress'),
			'search_items' => __('Search map group', 'mappress'),
			'not_found' => __('No map group found', 'mappress'),
			'not_found_in_trash' => __('No map group found in the trash', 'mappress'),
			'menu_name' => __('Map groups', 'mappress')
		);

		$args = array( 
			'labels' => $labels,
			'hierarchical' => true,
			'description' => __('MapPress maps group', 'mappress'),
			'supports' => array( 'title'),
			'public' => true,
			'show_ui' => true,
			'show_in_menu' => false,
			'exclude_from_search' => true,
			'rewrite' => array('slug' => 'mapgroup', 'with_front' => false),
			'capability_type' => 'page'
		);

		register_post_type('map-group', $args);
	}

	function admin_menu() {
		add_submenu_page('edit.php?post_type=map', __('Map groups', 'mappress'), __('Map groups', 'mappress'), 'edit_posts', 'edit.php?post_type=map-group');
		add_submenu_page('edit.php?post_type=map', __('Add new group', 'mappress'), __('Add new map group', 'mappress'), 'edit_posts', 'post-new.php?post_type=map-group');
	}

	function mapped_post_types() {
		$custom = get_post_types(array('public' => true, '_builtin' => false));
		$this->mapped_post_types = $custom + array('post');
		unset($this->mapped_post_types['map']);
		unset($this->mapped_post_types['map-group']);
		return apply_filters('mappress_mapped_post_types', $this->mapped_post_types);
	}

	function setup_query() {
		if($this->use_the_query()) {
			add_filter('query_vars', array($this, 'query_vars'));
			add_action('parse_query', array($this, 'parse_query'), 5, 1);
			add_filter('posts_clauses', array($this, 'posts_clauses'), 5, 2);
		}
	}

	function use_the_query() {
		$options = $this->get_options();
		if(isset($options['map']['use_map_query']))
			$use_query = $options['map']['use_map_query'];
		else
			$use_query = true;
		return apply_filters('mappress_use_map_query', $use_query);
	}

	function use_hash() {
		$options = $this->get_options();
		if(isset($options['map']))
			$use_hash = $options['map']['use_hash'] ? true : false;
		else
			$use_hash = true;
		return apply_filters('mappress_use_hash', $use_hash);
	}

	function query_vars($vars) {
		$vars[] = 'map_id';
		return $vars;
	}

	function posts_clauses($clauses, $query) {

		if(is_admin() && !(defined('DOING_AJAX') && DOING_AJAX))
			return $clauses;

		global $wpdb;

		if($this->map) {

			$map_id = $this->map->ID;

			if(get_post_type($map_id) != 'map')
				return $clauses;

			$join = "
				LEFT JOIN {$wpdb->postmeta} AS m_has_maps ON ({$wpdb->posts}.ID = m_has_maps.post_id AND m_has_maps.meta_key = 'has_maps')
				INNER JOIN {$wpdb->postmeta} m_maps ON ({$wpdb->posts}.ID = m_maps.post_id)
				";

			$where = "
				AND (
					(
						m_maps.meta_key = 'maps'
						AND CAST(m_maps.meta_value AS CHAR) = '{$map_id}'
					)
					OR m_has_maps.post_id IS NULL
				) ";

			$groupby = '';
			if(!$clauses['groupby'])
				$groupby = " {$wpdb->posts}.ID ";

			// hooks
			$join = apply_filters('mappress_posts_clauses_join', $join, $clauses, $query);
			$where = apply_filters('mappress_posts_clauses_where', $where, $clauses, $query);
			$groupby = apply_filters('mappress_posts_clauses_groupby', $groupby, $clauses, $query);

			$clauses['join'] .= $join;
			$clauses['where'] .= $where;
			$clauses['groupby'] .= $groupby;

		}

		return $clauses;
	}

	function parse_query($query) {

		if(is_admin() && !(defined('DOING_AJAX') && DOING_AJAX))
			return $query;

		if($query->get('map_id')) {
			$map_id = $query->get('map_id');
			$this->set_map(get_post($map_id));

		} else {

			if($query->is_main_query()) {
				if(is_home() && !$this->map) {
					$this->set_map($this->featured());
				} elseif($query->get('map') || $query->get('map-group')) {
					if($query->get('map'))
						$type = 'map';
					elseif($query->get('map-group'))
						$type = 'map-group';
					$this->set_map(get_page_by_path($query->get($type), 'OBJECT', $type));
				}
			}

		}

		return $query;
	}

	/*
	 * Allow search box inside map page (disable `s` argument for the map query)
	 */

	function set_map($post) {
		$this->map = $post;
		return $this->map;
	}

	function setup_pre_get_map() {
		add_action('pre_get_posts', array($this, 'pre_get_map'));
	}
	function pre_get_map($query) {
		if($query->get('map')) {
			if(isset($_GET['s']))
				$query->set('s', null);
			do_action('mappress_pre_get_map', $query);
		}
	}

	function get_id() {
		return $this->map->ID . '_' . $this->map_count;
	}

	function get_the_ID() {
		return $this->map->ID;
	}

	function featured_map_type() {
		return apply_filters('mappress_featured_map_type', array('map', 'map-group'));
	}

	function featured($post_type = false) {
		$post_type = $post_type ? $post_type : $this->featured_map_type();

		if(isset($this->options['front_page']) && $this->options['front_page']['featured_map'])
			$featured_id = $this->options['front_page']['featured_map'];

		if(!$featured_id) {
			$featured = $this->latest($post_type);
		} else {
			$featured = get_post($featured_id);
		}
		return $featured;
	}

	function latest($post_type = false) {
		$post_type = $post_type ? $post_type : $this->featured_map_type();
		$latest_map = get_posts(array('post_type' => $post_type, 'posts_per_page' => 1));
		if($latest_map)
			$map = array_shift($latest_map);

		return $map;
	}

	function is_map($map_id = false) {
		global $post;
		$map_id = $map_id ? $map_id : $post->ID;
		if(get_post_type($map_id) == 'map' || get_post_type($map_id) == 'map-group')
			return true;

		return false;
	}

	/*
	 * Display maps
	 */

	function get_map($map_id = false, $main_map = true, $force = false) {

		global $post;
		if(is_single()) {
			if(!$this->is_map() && !mappress_has_marker_location() && !$force) {
				return;
			} else {
				$single_post_maps_id = get_post_meta($post->ID, 'maps');
				if($single_post_maps_id && !$map_id)
					$map_id = array_shift($single_post_maps_id);
			}
		}

		if($map_id)
			$this->set_map(get_post($map_id));
		else
			$map_id = $this->map->ID;

		if($main_map) add_filter('mappress_map_conf', array($this, 'set_main'));
		get_template_part('content', get_post_type($map_id));
		if($main_map) remove_filter('mappress_map_conf', array($this, 'set_main'));

		$map_js_id = 'map_' . $map_id . '_' . $this->map_count;

		$this->map_count++;

		return $map_js_id;
	}

	// display featured map
	function get_featured($main_map = true, $force = false) {
		$featured_id = $this->featured()->ID;
		if(!$featured_id && current_user_can('edit_posts')) {
			return $this->create_map_message();
		}
		return $this->get_map($this->featured()->ID, $main_map, $force);
	}

	function create_map_message() {
		?>
		<div id="first-map-message">
			<h2><?php _e('You haven\'t created any maps!', 'mappress'); ?></h2>
			<h3><a href="<?php echo admin_url('/post-new.php?post_type=map'); ?>"><?php _e('Click here to create your first', 'mappress'); ?></a></h3>
		</div>
		<?php
	}

	function set_main($conf) {
		$this->has_main_map = true;
		$conf['mainMap'] = true;
		return $conf;
	}

	function map_conf() {
		return json_encode($this->get_map_conf());
	}

	function get_map_conf() {
		global $post;
		$conf = array(
			'postID' => $this->map->ID,
			'count' => $this->map_count
		); // default
		if(is_post_type_archive('map')) {
			$conf['disableMarkers'] = true;
			$conf['disableHash'] = true;
			$conf['disableInteraction'] = true;
		}
		return apply_filters('mappress_map_conf', $conf, $this->map, $post);
	}

	function mapgroup_conf() {
		return json_encode($this->get_mapgroup_conf());
	}

	function get_mapgroup_conf() {
		global $post;
		$conf = array(
			'postID' => $this->map->ID,
			'count' => $this->map_count
		); // default
		if(is_post_type_archive('map-group')) {
			$conf['disableMarkers'] = true;
			$conf['disableHash'] = true;
			$conf['disableInteraction'] = true;
		}
		return apply_filters('mappress_mapgroup_conf', $conf, $this->map, $post);
	}

	function get_map_data($map_id = false) {
		$map_id = $map_id ? $map_id : $this->map->ID;
		if(get_post_type($map_id) != 'map')
			return;
		$post = get_post($map_id);
		setup_postdata($post);
		$data = get_post_meta($map_id, 'map_data', true);
		$data['dataReady'] = true;
		$data['postID'] = $map_id;
		$data['title'] = get_the_title($map_id);
		$data['legend'] = $this->get_map_legend($map_id);
		if($post->post_content)
			$data['legend_full'] = '<h2>' . $data['title'] . '</h2>' . apply_filters('the_content', $post->post_content);
		wp_reset_postdata();
		return apply_filters('mappress_map_data', $data, $post);
	}

	function get_map_layers($map_id = false) {
		$map_id = $map_id ? $map_id : $this->map->ID;
		$map_data = $this->get_map_data($map_id);
		return $map_data['layers'];
	}

	function get_map_center($map_id = false) {
		$map_id = $map_id ? $map_id : $this->map->ID;
		$map_data = $this->get_map_data($map_id);
		return $map_data['center'];
	}

	function get_map_zoom($map_id = false) {
		$map_id = $map_id ? $map_id : $this->map->ID;
		$map_data = $this->get_map_data($map_id);
		return $map_data['zoom'];
	}

	function get_mapbox_image($map_id = false, $width = 200, $height = 200, $lat = false, $lng = false, $zoom = false) {

		$map_id = $map_id ? $map_id : $this->map->ID;

		if(get_post_type($map_id) == 'map-group') {
			$mapgroup = $this->get_mapgroup_data($map_id);
			$map = array_shift($mapgroup['maps']);
			$map_id = $map['postID'];
		}

		$layers = $this->get_map_layers($map_id);
		$layers_ids = array();
		foreach($layers as $layer) {
			if($layer['opts']['filtering'] == 'fixed') {
				$layers_ids[] = $layer['id'];
			}
		}

		$zoom = $zoom ? $zoom : $this->get_map_zoom($map_id);

		if(!$zoom)
			$zoom = 1;

		$center = $this->get_map_center($map_id);
		$lat = $lat ? $lat : $center['lat'];
		$lng = $lng ? $lng : $center['lon'];

		if(!$lat)
			$lat = 0;
		if(!$lng)
			$lng = 0;

		return 'http://api.tiles.mapbox.com/v3/' . implode(',', $layers_ids) . '/' . $lng . ',' . $lat . ',' . $zoom . '/' . $width . 'x' . $height . '.png';
	}

	function get_mapgroup_data($group_id = false) {
		$group_id = $group_id ? $group_id : $this->map->ID;
		$data = array();
		if(get_post_type($group_id) != 'map-group')
			return;
		$group_data = get_post_meta($group_id, 'mapgroup_data', true);
		foreach($group_data['maps'] as $map) {
			$map_id = $map['id'];
			$data['maps'][$map_id] = $map;
			$data['maps'][$map_id] += $this->get_map_data($map['id']);
		}
		return apply_filters('mappress_mapgroup_data', $data, $post);
	}

	function get_map_legend($map_id = false) {
		$map_id = $map_id ? $map_id : $this->map->ID;
		return apply_filters('mappress_map_legend', get_post_meta($map_id, 'legend', true), $this->map);
	}

	function get_options() {
		return $this->options;
	}

	/*
	 * Ajax
	 */

	function setup_ajax() {
		add_action('wp_ajax_nopriv_mapgroup_data', array($this, 'get_mapgroup_json_data'));
		add_action('wp_ajax_mapgroup_data', array($this, 'get_mapgroup_json_data'));
		add_action('wp_ajax_nopriv_map_data', array($this, 'get_map_json_data'));
		add_action('wp_ajax_map_data', array($this, 'get_map_json_data'));
	}

	function get_mapgroup_json_data($group_id = false) {
		$group_id = $group_id ? $group_id : $_REQUEST['group_id'];
		$data = json_encode($this->get_mapgroup_data($group_id));
		header('Content Type: application/json');
		echo $data;
		exit;
	}

	function get_map_json_data($map_id = false) {
		$map_id = $map_id ? $map_id : $_REQUEST['map_id'];
		$data = json_encode($this->get_map_data($map_id));
		header('Content Type: application/json');
		echo $data;
		exit;
	}

	/*
	 * Disable canonical redirect on map/map-group post type for stories pagination
	 */
	function setup_canonical() {
		add_filter('redirect_canonical', array($this, 'disable_canonical'));
	}
	function disable_canonical($redirect_url) {
		if(is_singular('map') || is_singular('map-group'))
			return false;
	}

	/*
	 * Plugin fixes
	 */

	function plugin_fixes() {
		$this->fix_qtranslate();
	}

	function fix_qtranslate() {
		if(function_exists('qtrans_getLanguage')) {
			add_filter('get_the_date', array($this, 'qtranslate_get_the_date'), 10, 2);
			add_filter('admin_url', array($this, 'qtranslate_admin_url'), 10, 2);
			add_action('post_type_archive_link', 'qtrans_convertURL');
		}
	}

	// enable custom format date
	function qtranslate_get_the_date($date, $format) {
		if($format != '') {
			$post = get_post();
			$date = mysql2date($format, $post->post_date);
		}
		return $date;
	}

	// send lang to ajax requests
	function qtranslate_admin_url($url, $path) {
		if($path == 'admin-ajax.php' && function_exists('qtrans_getLanguage'))
			$url .= '?lang=' . qtrans_getLanguage();

		return $url;
	}
}

$mappress = new MapPress();

require_once(TEMPLATEPATH . '/inc' . '/markers.php');
require_once(TEMPLATEPATH . '/inc' . '/ui.php');
// GeoJSON API
require_once(TEMPLATEPATH . '/inc' . '/api.php');
// Embed functionality
require_once(TEMPLATEPATH . '/inc' . '/embed.php');
// Metaboxes
require_once(TEMPLATEPATH . '/metaboxes/metaboxes.php');
require_once(TEMPLATEPATH . '/inc' . '/featured.php');

/*
 * MapPress functions api
 */

function mappress_the_query($query) {
	global $mappress;
	return $mappress->the_query($query);	
}

// mapped post types
function mappress_get_mapped_post_types() {
	global $mappress;
	return $mappress->mapped_post_types();
}

function mappress_set_map($post) {
	global $mappress;
	return $mappress->set_map($post);
}

// get the main map post
function mappress_the_map() {
	global $mappress;
	return $mappress->map;
}


// get the featured map post
function mappress_map_featured($post_type = false) {
	global $mappress;
	return $mappress->featured($post_type);
}


// get the latest map post
function mappress_map_latest($post_type = false) {
	global $mappress;
	return $mappress->latest($post_type);
}

// if post is map
function mappress_is_map($map_id = false) {
	global $mappress;
	return $mappress->is_map($map_id);
}

// display the featured map
function mappress_featured($main_map = true, $force = false) {
	global $mappress;
	return $mappress->get_featured($main_map, $force);
}

// display map
function mappress_map($map_id = false, $main_map = true, $force = false) {
	global $mappress;
	return $mappress->get_map($map_id, $main_map, $force = false);
}

// get JSON map conf
function mappress_map_conf() {
	global $mappress;
	return $mappress->map_conf();
}

// get ARRAY map conf
function mappress_get_map_conf($map_id = false) {
	global $mappress;
	return $mappress->get_map_conf();
}

// get the map conf
function mappress_mapgroup_conf() {
	global $mappress;
	return $mappress->mapgroup_conf();
}

// get the main map id
function mappress_get_map_id() {
	global $mappress;
	return $mappress->get_id();
}

// get the main map id
function mappress_get_the_ID() {
	global $mappress;
	return $mappress->get_the_ID();
}

function mappress_get_mapgroup_data($map_id = false) {
	global $mappress;
	return $mappress->get_mapgroup_data($map_id);
}

// get the map formatted data
function mappress_get_map_data($map_id = false) {
	global $mappress;
	return $mappress->get_map_data($map_id);
}

function mappress_get_map_layers($map_id = false) {
	global $mappress;
	return $mappress->get_map_layers($map_id);
}

function mappress_get_map_center($map_id = false) {
	global $mappress;
	return $mappress->get_map_center($map_id);
}

function mappress_get_mapbox_image($map_id = false, $width = 200, $height = 200, $lat = false, $lng = false, $zoom = false) {
	global $mappress;
	return $mappress->get_mapbox_image($map_id, $width, $height, $lat, $lng, $zoom);
}

function mappress_get_map_zoom($map_id = false) {
	global $mappress;
	return $mappress->get_map_zoom($map_id);
}

function mappress_get_options() {
	global $mappress;
	return $mappress->get_options();
}

function mappress_has_main_map() {
	global $mappress;
	return $mappress->has_main_map;
}

?>