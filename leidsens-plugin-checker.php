<?php
/*
Plugin Name: Leidsens Plugin Checker
Description: Plugin to list, active, deactive all installed plugins, Find and replace URLs, slug.
Author URI:  https://www.saffiretech.com
Author: Leidsens
Author URI: https://leidsens.com/
License: GPLv3
Stable Tag : 1.0.0
Requires at least: 5.0
Tested up to: 6.4.2
Requires PHP: 7.2
Text Domain: leidsens
Version: 1.0.0
*/

// namespace leidsens_plugin_checkers;
function refreshPage(){
	?>
	<script type="text/javascript">
		location.reload();
	</script>
<?php
}
if(!defined('PLUGIN_CHECKER_VERSION')){
    define('PLUGIN_CHECKER_VERSION', '1.0.0');
}

function load_custom_wp_admin_style($hook) {
	if(($hook == 'toplevel_page_leidsensplugin_checker') || ($hook == 'leidsens-plugin-checker_page_urlreplace') || ($hook == 'leidsens-plugin-checker_page_disableeditor')) {
		wp_enqueue_script( 'plugin_checker_default-js-lib', plugins_url('includes/assets/js/jquery.js', __FILE__), false, null, 'all' );
		wp_enqueue_style( 'plugin_checker_select_lib', plugins_url('includes/assets/css/select2.min.css', __FILE__), false, null, 'all');
		wp_enqueue_style( 'plugin_checker_style-admin', plugins_url('includes/assets/css/admin-style.css', __FILE__), false, null, 'all' );
		wp_enqueue_style( 'plugin_checker_style-admin-tab', plugins_url('includes/assets/css/tabStyle.css', __FILE__), false, null, 'all' );
		wp_enqueue_script( 'plugin_checker_select_lib', plugins_url('includes/assets/js/select2.min.js', __FILE__), false, null, 'all' );
	}else{
		return;
	}
}
add_action( 'admin_enqueue_scripts', 'load_custom_wp_admin_style' );

function create_leidsens_plugin_checker_database_table()
{
  	global $wpdb;
  	$table_name = $wpdb->prefix . "leidsens_plugin_checker_updates";
	$charset_collate = $wpdb->get_charset_collate();
	if($wpdb->get_var( "show tables like '$table_name'" ) != $table_name) 
    {
		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			plugin_name tinytext NOT NULL,
			version_text text NOT NULL,
			disable_status ENUM('Yes', 'No') NOT NULL,
			PRIMARY KEY  (id)
		)$charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}
}
register_activation_hook( __FILE__, 'create_leidsens_plugin_checker_database_table' );

/**
 * Register a custom menu page.
 */
function leidsens_register_checker_menu_page() { 
  	add_menu_page( 
		'Leidsens Plugin Checker',
		'Leidsens Plugin Checker',
		'manage_options',
		'leidsensplugin_checker',
		'leidsens_plugin_checker',
		plugins_url( 'leidsens-plugin-checker/icons/i_1.png' ),
		6
    );

  	add_submenu_page(
  		'leidsensplugin_checker',
		'Leidsens Url replace Checker',
		'URL Replace',
		'manage_options',
		'urlreplace',
		'leidsens_urlreplace_checker'
    );

    add_submenu_page(
  		'leidsensplugin_checker',
		'Leidsens Disable Editor',
		'Disable/Enable Editor',
		'manage_options',
		'disableeditor',
		'leidsens_disable_editor'
    );
}
add_action('admin_menu', 'leidsens_register_checker_menu_page');

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Plugins_List extends WP_List_Table {
	/** Class constructor */
	public function __construct() {
		parent::__construct( [
			'singular' => 'leidsens_plugin_link', //singular name of the listed records
			'plural' => 'leidsens_plugin_links', //plural name of the listed records
			'ajax' => false //should this table support ajax?
		] );
	}

	/**
	 * Define the columns that are going to be used in the table
	 * @return array $columns, the array of columns to use with the table
	 */
	function get_columns() {
	   	return $columns= array(
	      	'col_plugin_id'=>__('ID'),
	      	'col_plugin_name'=>__('Plugin Name'),
	      	'col_plugin_description'=>__('Description')
	   	);
	}

	/**
	 * Decide which columns to activate the sorting functionality on
	 * @return array $sortable, the array of columns that can be sorted by the user
	*/
	public function get_sortable_columns() {
	   	return $sortable = array(
	      	'col_plugin_id'=>'plugin_id',
	      	'col_plugin_name'=>'plugin_name'
	   	);
	}
	/**
	 * Prepare the table with different parameters, pagination, columns and table elements
	 */
	function prepare_items() {
	   	global $wpdb, $_wp_column_headers;
	   	$screen = get_current_screen();

	   	if ( ! function_exists( 'get_plugins' ) ) {
		    require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all_plugins = get_plugins();
	   	$totalitems = count($all_plugins);
        //How many to display per page?
        $perpage = 5;
        //Which page is this?
        $paged = !empty($_GET["paged"]) ? mysqli_real_escape_string($wpdb, htmlspecialchars(sanitize_text_field($_GET["paged"]))) : '';
        //Page Number
        if(empty($paged) || !is_numeric($paged) || $paged<=0 ){ $paged=1; }

        //How many pages do we have in total?
        $totalpages = ceil($totalitems/$perpage);
        //adjust the query to take pagination into account
        if(!empty($paged) && !empty($perpage)){
        	$offset=($paged-1)*$perpage;
        	$query.=' LIMIT '.(int)$offset.','.(int)$perpage;
        }
        /* -- Register the pagination -- */
        $this->set_pagination_args( array(
	        "total_items" => $totalitems,
	        "total_pages" => $totalpages,
	        "per_page" => $perpage,
	    ) );
	      //The pagination links are automatically built according to those parameters

	   	/* -- Register the Columns -- */
		$columns = $this->get_columns();
		$_wp_column_headers[$screen->id]=$columns;

	   	/* -- Fetch the items -- */
	   	$this->items = $all_plugins;
	}

	/**
	 * Display the rows of records in the table
	 * @return string, echo the markup of the rows
	 */
	function display_rows() {
	   	//Get the records registered in the prepare_items method
	   	$records = $this->items;
	   	//Get the columns registered in the get_columns and get_sortable_columns methods
	   	list( $columns, $hidden ) = $this->get_column_info();

	   	//Loop for each record
	   	if(!empty($records)){
			foreach($records as $rec){
				//Open the line
				$leidhtml = '< tr id="record_'.$rec->plugin_id.'">';
				foreach ( $columns as $column_name => $column_display_name ) {
					//Style attributes for each col
					$class = "class='$column_name column-$column_name'";
					$style = "";
					if ( in_array( $column_name, $hidden ) ) $style = ' style="display:none;"';
					$attributes = $class . $style;

					//edit link
					$editlink  = '/wp-admin/link.php?action=edit&plugin_id='.(int)$rec->plugin_id;

					//Display the cell
					switch ( $column_name ) {
						case "col_plugin_id":  $leidhtml .= '< td '.$attributes.'>'.stripslashes($rec->plugin_id).'< /td>';   break;
						case "col_plugin_name": $leidhtml .= '< td '.$attributes.'>'.stripslashes($rec->plugin_name).'< /td>'; break;
						case "col_plugin_description": $leidhtml .= '< td '.$attributes.'>'.$rec->plugin_description.'< /td>'; break;
					}
				}
				//Close the line
				$leidhtml .= '< /tr>';
				echo esc_html($leidhtml);
			}
		}
	}
}

/**
 * Display a custom menu page
 */
function leidsens_plugin_checker(){
	$redirects = 1;
	if (isset($_POST['leidsens_disable_plugin'])) {
		foreach ($_POST['plugins_to'] as $value) {
			$sanVal = sanitize_text_field($value);
			$escapeVal = htmlspecialchars($sanVal);
			deactivate_plugins( '/'.$escapeVal, true);
			// do_action( "deactivate_".$escapeVal, true );
			
		}
		$redirects = 2;
	}

	if (isset($_POST['leidsens_enable_plugin'])) {
		foreach ($_POST['act_plugins_to'] as $value) {
			$sanVal = sanitize_text_field($value);
			$escapeVal = htmlspecialchars($sanVal);
			activate_plugins( '/'.$escapeVal, false);
		}
		$redirects = 2;
	}

	if (isset($_POST['leidsens_disableupdate_plugin'])) {
		global $wpdb;
		$table = $wpdb->prefix . "leidsens_plugin_checker_updates";
		foreach ($_POST['upd_plugins_to'] as $value) {
			$sanVal = sanitize_text_field($value);
			$escapeVal = htmlspecialchars($sanVal);
			$pluginArr = explode("_", $escapeVal);
			
			$data = array('time' => date('Y-m-d H:i:s'), 'plugin_name' => $pluginArr[0], 'version_text' => $pluginArr[1], 'disable_status' => 'Yes');
			$format = array('%s','%s','%s','%s');
			$wpdb->insert($table,$data,$format);
			$my_id = $wpdb->insert_id;
		}
		$redirects = 2;
	}

	if (isset($_POST['leidsens_enableupdate_plugin'])) {
		global $wpdb;
		$table = $wpdb->prefix . "leidsens_plugin_checker_updates";
		foreach ($_POST['en_upd_plugins_to'] as $value) {
			$sanVal = sanitize_text_field($value);
			$escapeVal = htmlspecialchars($sanVal);			
			$pluginArr = explode("_", $escapeVal);
			$wpdb->query( $wpdb->prepare("DELETE FROM $table WHERE plugin_name = %s AND version_text = %s", $pluginArr[0], $pluginArr[1]));
		}
		$redirects = 2;
	}
	if ($redirects == 2) {
		refreshPage();
	}

    esc_html_e( 'Leidsens Plugin Checker', 'leidsens' );
    global $wpdb;
	$table = $wpdb->prefix . "leidsens_plugin_checker_updates";
	$plugin_name = $wpdb->get_results("SELECT plugin_name FROM $table WHERE (disable_status = 'Yes')");
	
	$apl=get_option('active_plugins');
	$all_plugins=get_plugins();

	$activated_plugins=array();
	$deactivated_plugins=array();
	foreach ($apl as $p){
	    if(isset($all_plugins[$p])){
	    	if ($p != 'leidsens-plugin-checker/leidsens-plugin-checker.php') {
	    		$activated_plugins[$p] = $all_plugins[$p];
	    	}
	    }else{
	    	$deactivated_plugins[$p] = $all_plugins[$p];
	    }
	}

	foreach ($all_plugins as $key=>$p){
	    if(in_array($key, $apl)){
	    }else{
	    	$deactivated_plugins[$key] = $p;
	    }
	}

	$enabled_plugins=array();
	$disabled_plugins=array();
	foreach ($plugin_name as $plg){
	    if(array_key_exists($plg->plugin_name,$all_plugins)){
	    	$disabled_plugins[$plg->plugin_name] = $all_plugins[$plg->plugin_name];
	    }
	}

	foreach ($all_plugins as $key=>$plg){
		$flg = 0;
		foreach ($plugin_name as $value) {
	    	if ($value->plugin_name == $key) {
	    		$flg = 1;
	    	}
	    }
	    if($flg == 0){
	    	if ($key != 'leidsens-plugin-checker/leidsens-plugin-checker.php') {
	    		$enabled_plugins[$key] = $plg;
	    	}
	    }
	} 
	
	?>
	
  	<section class="lsbodyWrap">
	  	<div class="lsBrand">  
	  		<h2>Plugins Checker Form</h2>
		</div>
		<div class="leidsens_tabWrap">
			<!-- Tab links -->
			<div class="lstabMenu" id="plugin_checker_tabMenu">
				<button class="tablinks" data-open="disable_plugins" >Deactivate Plugins</button>
				<button class="tablinks" data-open="enable_plugins">Activate Plugins</button>
				<button class="tablinks" data-open="dis_update">Disable Update</button>
				<button class="tablinks" data-open="ena_update">Enable Update</button>
			</div>

			<!-- Tab content -->
			<div class="containerWrpa">
				<div id="disable_plugins" class="lsTabContent">
					<div class="lsTabContent__header">
					<svg id="Layer_1" height="512" viewBox="0 0 64 64" width="512" xmlns="http://www.w3.org/2000/svg" data-name="Layer 1"><path d="m20 19h-1.031a5 5 0 1 0 -5.938 0h-1.031a5.006 5.006 0 0 0 -5 5v11a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-11a5.006 5.006 0 0 0 -5-5zm-7-4a3 3 0 1 1 3 3 3 3 0 0 1 -3-3zm10 19h-14v-10a3 3 0 0 1 3-3h8a3 3 0 0 1 3 3z"/><path d="m57 31.624v-24.624a3 3 0 0 0 -3-3h-50a3 3 0 0 0 -3 3v38a3 3 0 0 0 3 3h21.5v5h-6.5a1 1 0 0 0 -.781.375l-4 5a1 1 0 0 0 .781 1.625h28a1 1 0 0 0 .781-1.625l-4-5a1 1 0 0 0 -.781-.375h-6.5v-5h8.124a11.99 11.99 0 1 0 16.376-16.376zm-18.48 23.376 2.4 3h-23.839l2.4-3zm-11.02-2v-5h3v5zm-23.5-7a1 1 0 0 1 -1-1v-3h36a11.922 11.922 0 0 0 .7 4zm35.181-6h-36.181v-33a1 1 0 0 1 1-1h50a1 1 0 0 1 1 1v23.7a11.949 11.949 0 0 0 -15.819 9.3zm11.819 12a10 10 0 1 1 10-10 10.011 10.011 0 0 1 -10 10z"/><path d="m29 15h16v2h-16z"/><path d="m29 21h22v2h-22z"/><path d="m29 27h11v2h-11z"/><path d="m55.243 36.343-4.243 4.243-4.243-4.243-1.414 1.414 4.243 4.243-4.243 4.243 1.414 1.414 4.243-4.243 4.243 4.243 1.414-1.414-4.243-4.243 4.243-4.243z"/></svg>
						<h3>Deactivate Plugins</h3>
					</div>
					
					<form action="" method="post">
						<div class="lsrow">
							<div class="pluginChecker_multiSelect">
								<label>Select Plugins:</label>
								<select name="plugins_to[]" id="plugins_to" class="plugins_to" multiple="multiple" width="300px">
									<option>Select here...</option><?php foreach ($activated_plugins as $key=>$value) { ?>
											<option value="<?php echo esc_html($key); ?>"><?php echo esc_html($value['Name']); ?></option><?php
											} ?>
								</select>
								<div class="pluginChecker_downArrow">
									<svg xmlns="http://www.w3.org/2000/svg" height="20" width="18" viewBox="0 0 448 512"><path opacity="1" fill="#1E3050" d="M201.4 342.6c12.5 12.5 32.8 12.5 45.3 0l160-160c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L224 274.7 86.6 137.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l160 160z"/></svg>
								</div>
							</div>
							<div class="">
								<input name="leidsens_disable_plugin" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Disable All' ); ?>" />
							</div>						
						</div>						
					</form>
				</div>

				<div id="enable_plugins" class="lsTabContent">
					<div class="lsTabContent__header">
						<svg id="Layer_1" height="512" viewBox="0 0 64 64" width="512" xmlns="http://www.w3.org/2000/svg" data-name="Layer 1"><path d="m20 19h-1.031a5 5 0 1 0 -5.938 0h-1.031a5.006 5.006 0 0 0 -5 5v11a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-11a5.006 5.006 0 0 0 -5-5zm-7-4a3 3 0 1 1 3 3 3 3 0 0 1 -3-3zm10 19h-14v-10a3 3 0 0 1 3-3h8a3 3 0 0 1 3 3z"/><path d="m57 31.624v-24.624a3 3 0 0 0 -3-3h-50a3 3 0 0 0 -3 3v38a3 3 0 0 0 3 3h21.5v5h-6.5a1 1 0 0 0 -.781.375l-4 5a1 1 0 0 0 .781 1.625h28a1 1 0 0 0 .781-1.625l-4-5a1 1 0 0 0 -.781-.375h-6.5v-5h8.124a11.99 11.99 0 1 0 16.376-16.376zm-18.48 23.376 2.4 3h-23.839l2.4-3zm-11.02-2v-5h3v5zm-23.5-7a1 1 0 0 1 -1-1v-3h36a11.922 11.922 0 0 0 .7 4zm35.181-6h-36.181v-33a1 1 0 0 1 1-1h50a1 1 0 0 1 1 1v23.7a11.949 11.949 0 0 0 -15.819 9.3zm11.819 12a10 10 0 1 1 10-10 10.011 10.011 0 0 1 -10 10z"/><path d="m29 15h16v2h-16z"/><path d="m29 21h22v2h-22z"/><path d="m29 27h11v2h-11z"/><path d="m55.243 36.343-4.243 4.243-4.243-4.243-1.414 1.414 4.243 4.243-4.243 4.243 1.414 1.414 4.243-4.243 4.243 4.243 1.414-1.414-4.243-4.243 4.243-4.243z"/></svg>
						<h3>Activate Plugins</h3>
					</div>
					<form action="" method="post">
						<div class="lsrow">
							<div class="pluginChecker_multiSelect">
								<label>Select Plugins:</label>
								<select name="act_plugins_to[]" id="act_plugins_to" class="act_plugins_to" multiple="multiple">
									<option>Select here...</option><?php foreach ($deactivated_plugins as $key=>$value) { ?><option value="<?php echo esc_html($key); ?>"><?php echo esc_html($value['Name']); ?></option><?php } ?>
								</select>
								<div class="pluginChecker_downArrow">
									<svg xmlns="http://www.w3.org/2000/svg" height="20" width="18" viewBox="0 0 448 512"><path opacity="1" fill="#1E3050" d="M201.4 342.6c12.5 12.5 32.8 12.5 45.3 0l160-160c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L224 274.7 86.6 137.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l160 160z"/></svg>
								</div>
							</div>
							<div class="">
								<input name="leidsens_enable_plugin" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Enable All' ); ?>" />
							</div>						
						</div>						
					</form>
				</div>

				<div id="dis_update" class="lsTabContent">
					<div class="lsTabContent__header">
						<svg id="Layer_1" height="512" viewBox="0 0 64 64" width="512" xmlns="http://www.w3.org/2000/svg" data-name="Layer 1"><path d="m20 19h-1.031a5 5 0 1 0 -5.938 0h-1.031a5.006 5.006 0 0 0 -5 5v11a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-11a5.006 5.006 0 0 0 -5-5zm-7-4a3 3 0 1 1 3 3 3 3 0 0 1 -3-3zm10 19h-14v-10a3 3 0 0 1 3-3h8a3 3 0 0 1 3 3z"/><path d="m57 31.624v-24.624a3 3 0 0 0 -3-3h-50a3 3 0 0 0 -3 3v38a3 3 0 0 0 3 3h21.5v5h-6.5a1 1 0 0 0 -.781.375l-4 5a1 1 0 0 0 .781 1.625h28a1 1 0 0 0 .781-1.625l-4-5a1 1 0 0 0 -.781-.375h-6.5v-5h8.124a11.99 11.99 0 1 0 16.376-16.376zm-18.48 23.376 2.4 3h-23.839l2.4-3zm-11.02-2v-5h3v5zm-23.5-7a1 1 0 0 1 -1-1v-3h36a11.922 11.922 0 0 0 .7 4zm35.181-6h-36.181v-33a1 1 0 0 1 1-1h50a1 1 0 0 1 1 1v23.7a11.949 11.949 0 0 0 -15.819 9.3zm11.819 12a10 10 0 1 1 10-10 10.011 10.011 0 0 1 -10 10z"/><path d="m29 15h16v2h-16z"/><path d="m29 21h22v2h-22z"/><path d="m29 27h11v2h-11z"/><path d="m55.243 36.343-4.243 4.243-4.243-4.243-1.414 1.414 4.243 4.243-4.243 4.243 1.414 1.414 4.243-4.243 4.243 4.243 1.414-1.414-4.243-4.243 4.243-4.243z"/></svg>
						<h3>Close Update for Plugins</h3>
					</div>
					<form action="" method="post">
						<div class="lsrow">
							<div class="pluginChecker_multiSelect">
								<label>Select Plugins:</label>
								<select name="upd_plugins_to[]" id="upd_plugins_to" class="upd_plugins_to" multiple="multiple">
									<option>Select here...</option><?php foreach ($enabled_plugins as $key=>$value) { ?>
											<option value="<?php echo esc_html($key); ?>_<?php echo esc_attr($value['Version']); ?>"><?php echo esc_html($value['Name']); ?></option><?php } ?>
								</select>
								<div class="pluginChecker_downArrow">
									<svg xmlns="http://www.w3.org/2000/svg" height="20" width="18" viewBox="0 0 448 512"><path opacity="1" fill="#1E3050" d="M201.4 342.6c12.5 12.5 32.8 12.5 45.3 0l160-160c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L224 274.7 86.6 137.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l160 160z"/></svg>
								</div>
							</div>
							<div class="">
								<input name="leidsens_disableupdate_plugin" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Disable Update All' ); ?>" />
							</div>						
						</div>						
					</form>
				</div>

				<div id="ena_update" class="lsTabContent">
					<div class="lsTabContent__header">
						<svg id="Layer_1" height="512" viewBox="0 0 64 64" width="512" xmlns="http://www.w3.org/2000/svg" data-name="Layer 1"><path d="m20 19h-1.031a5 5 0 1 0 -5.938 0h-1.031a5.006 5.006 0 0 0 -5 5v11a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-11a5.006 5.006 0 0 0 -5-5zm-7-4a3 3 0 1 1 3 3 3 3 0 0 1 -3-3zm10 19h-14v-10a3 3 0 0 1 3-3h8a3 3 0 0 1 3 3z"/><path d="m57 31.624v-24.624a3 3 0 0 0 -3-3h-50a3 3 0 0 0 -3 3v38a3 3 0 0 0 3 3h21.5v5h-6.5a1 1 0 0 0 -.781.375l-4 5a1 1 0 0 0 .781 1.625h28a1 1 0 0 0 .781-1.625l-4-5a1 1 0 0 0 -.781-.375h-6.5v-5h8.124a11.99 11.99 0 1 0 16.376-16.376zm-18.48 23.376 2.4 3h-23.839l2.4-3zm-11.02-2v-5h3v5zm-23.5-7a1 1 0 0 1 -1-1v-3h36a11.922 11.922 0 0 0 .7 4zm35.181-6h-36.181v-33a1 1 0 0 1 1-1h50a1 1 0 0 1 1 1v23.7a11.949 11.949 0 0 0 -15.819 9.3zm11.819 12a10 10 0 1 1 10-10 10.011 10.011 0 0 1 -10 10z"/><path d="m29 15h16v2h-16z"/><path d="m29 21h22v2h-22z"/><path d="m29 27h11v2h-11z"/><path d="m55.243 36.343-4.243 4.243-4.243-4.243-1.414 1.414 4.243 4.243-4.243 4.243 1.414 1.414 4.243-4.243 4.243 4.243 1.414-1.414-4.243-4.243 4.243-4.243z"/></svg>
						<h3>Show Update for Plugins</h3>
					</div>
					<form action="" method="post">
						<div class="lsrow">
							<div class="pluginChecker_multiSelect">
								<label>Select Plugins:</label>
								<select name="en_upd_plugins_to[]" id="en_upd_plugins_to" class="en_upd_plugins_to" multiple="multiple">
									<option>Select here...</option><?php foreach ($disabled_plugins as $key=>$value) { ?>
											<option value="<?php echo esc_html($key); ?>_<?php echo esc_html($value['Version']); ?>"><?php echo esc_html( $value['Name'] ); ?></option><?php } ?>
								</select>
								<div class="pluginChecker_downArrow">
									<svg xmlns="http://www.w3.org/2000/svg" height="20" width="18" viewBox="0 0 448 512"><path opacity="1" fill="#1E3050" d="M201.4 342.6c12.5 12.5 32.8 12.5 45.3 0l160-160c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L224 274.7 86.6 137.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l160 160z"/></svg>
								</div>
							</div>
							<div class="">
								<input name="leidsens_enableupdate_plugin" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Enable Update All' ); ?>" />
							</div>						
						</div>						
					</form>
				</div>
			</div>
		</div>
	</section>
	<div class="lpcBrand">
		<img src="<?php echo esc_url( plugins_url( 'images/leidsenslogo.png', __FILE__ ) ); ?>" alt="">
	</div>
	<script type="text/javascript" async>
		jQuery(document).ready(function() {
			jQuery('#plugin_checker_tabMenu > button').on('click', function(){
				var serInx = jQuery(this).data('open');
				jQuery(this).addClass('active').siblings().removeClass('active');
				jQuery('#'+serInx).show().siblings().hide();
			});
			jQuery('#plugin_checker_tabMenu > button').first().trigger('click');

		    jQuery('.plugins_to').select2();
		    jQuery('.act_plugins_to').select2();
		    jQuery('.upd_plugins_to').select2();
		    jQuery('.en_upd_plugins_to').select2();
		});
	</script><?php
	
}
function leidsens_urlreplace_checker() { ?>
	<section class="lsbodyWrap">
		<div class="lsBrand">
			<h2>Url and String replace</h2>
		</div>
		<div class="lsbdRepls"><?php
			global $wpdb;
			require_once( ABSPATH . 'wp-load.php' );
			require_once( ABSPATH . 'wp-includes/pluggable.php' );
			$page_args = array(
				'post_type' => 'page',
				'post_status' => 'publish',
			);
			$my_posts = new WP_Query( $page_args );


			$blog_args = array(
				'post_type' => 'post',
				'post_status' => 'publish',
			);
			$blog_posts = new WP_Query( $blog_args );
			?>
			<form action="" method="post" id="lsdbReplce">
				<div class="lsdbRow">
					<div class="">
						<label>Target Url / Text:</label>
						<input type="text" name="leidsens_target_string" class="" required="required">					
					</div>
					<div class="">
						<label>Replace with intended Url / Text:</label>
						<input type="text" name="leidsens_replace_string" class="" required="required">					
					</div>
				</div>
				<div class="lsdbRow">
					<div class="marginB">Select Modify For:</div>
					<input type="radio" id="leidsens_page_url" name="leidsens_replace_for" value="leidsens_pages">
					<label for="leidsens_page_url">PAGE</label>				
					<input type="radio" id="leidsens_post_url" name="leidsens_replace_for" value="leidsens_posts">
					<label for="leidsens_post_url">POST</label>				
					<input type="radio" id="leidsens_home_url" name="leidsens_replace_for" value="leidsens_homes">
					<label for="leidsens_home_url">HOME URL/SITE URL</label>
				</div>
				<div class="lsdbRow" id="leidsens_pages_div">
					<div class="col-md-6 pluginChecker_multiSelect_urlReplace">
						<label>Select Pages:</label>
						<select name="leidsens_pages[]" id="leidsens_pages" class="leidsens_pages" multiple="multiple"><?php if ( $my_posts->have_posts() ) {
									// Load pages loop.
									while ( $my_posts->have_posts() ) {
										$my_posts->the_post();
										?>
											<option value="<?php echo get_the_id(); ?>"><?php echo get_the_title(); ?></option><?php 		
									}
									wp_reset_postdata(); 
								}
							?>
						</select>
						<div class="pluginChecker_downArrow">
							<svg xmlns="http://www.w3.org/2000/svg" height="20" width="18" viewBox="0 0 448 512"><path opacity="1" fill="#1E3050" d="M201.4 342.6c12.5 12.5 32.8 12.5 45.3 0l160-160c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L224 274.7 86.6 137.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l160 160z"/></svg>
						</div>
					</div>
				</div>
				<div class="lsdbRow" id="leidsens_posts_div">
					<div class="col-md-6 pluginChecker_multiSelect_urlReplace">
						<label>Select Posts:</label>
						<select name="leidsens_posts[]" id="leidsens_posts" class="leidsens_posts" multiple="multiple"><?php if ( $blog_posts->have_posts() ) {
									// Load pages loop.
									while ( $blog_posts->have_posts() ) {
										$blog_posts->the_post();
										?>
											<option value="<?php echo get_the_id(); ?>"><?php echo get_the_title(); ?></option><?php 	
									}
									wp_reset_postdata();
								}
							?>
						</select>
						<div class="pluginChecker_downArrow">
							<svg xmlns="http://www.w3.org/2000/svg" height="20" width="18" viewBox="0 0 448 512"><path opacity="1" fill="#1E3050" d="M201.4 342.6c12.5 12.5 32.8 12.5 45.3 0l160-160c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L224 274.7 86.6 137.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l160 160z"/></svg>
						</div>
					</div>
				</div>
				<div class="lsdbRow">
					<div class="">
						<input name="leidsens_replace" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Replace All' ); ?>" />
					</div>
				</div>			
			</form>
		</div>
	</section>	
	<div class="lpcBrand">
		<img src="<?php echo esc_url( plugins_url( 'images/leidsenslogo.png', __FILE__ ) ); ?>" alt="">
	</div>
	<script type="text/javascript" async>
		jQuery(document).ready(function() {
		    jQuery('.leidsens_pages').select2();
		    jQuery('.leidsens_posts').select2();
		    leidsens_posts_div = document.getElementById("leidsens_posts_div");
		    leidsens_pages_div = document.getElementById("leidsens_pages_div");
		    leidsens_posts_div.style.display = "none";
		    leidsens_pages_div.style.display = "none";

		    jQuery( "input[type=radio]" ).on( "click", function() {
		    	leidsens_posts_div.style.display = "none";
		    	leidsens_pages_div.style.display = "none";
				var div = jQuery( this ).val();
				var leidsens_div = document.getElementById(div+"_div");
				// alert(jQuery( this ).val());
				leidsens_div.style.display = "block";
			});
		});
	</script><?php
	if (isset($_POST['leidsens_replace'])) {
		$table_name  = $wpdb->prefix."posts";
		$searchStr = addslashes(htmlspecialchars(sanitize_text_field($_POST['leidsens_target_string'])));
		$replaceStr = addslashes(htmlspecialchars(sanitize_text_field($_POST['leidsens_replace_string'])));
		$leidsens_replace_for = htmlspecialchars(sanitize_text_field($_POST['leidsens_replace_for']));
		if ($leidsens_replace_for == 'leidsens_homes') {
			$wpdb->query( $wpdb->prepare("UPDATE ".$wpdb->prefix."options SET option_value = replace(option_value, '$searchStr', '$replaceStr') WHERE option_name = 'home' OR option_name = 'siteurl'") );
			$wpdb->query( $wpdb->prepare("UPDATE ".$wpdb->prefix."posts SET guid = replace(guid, '$searchStr','$replaceStr')") );
			$wpdb->query( $wpdb->prepare("UPDATE ".$wpdb->prefix."posts SET post_content = replace(post_content, '$searchStr', '$replaceStr')") );
			$wpdb->query( $wpdb->prepare("UPDATE ".$wpdb->prefix."postmeta SET meta_value = replace(meta_value,'$searchStr','$replaceStr')"));
		}else{
			foreach ($_POST[$leidsens_replace_for] as $value) {
				$sanVal = sanitize_text_field($value);
				$escapeVal = htmlspecialchars($sanVal);
			    $wpdb->query( $wpdb->prepare("UPDATE $table_name
			    SET post_content = replace(post_content,'$searchStr','$replaceStr') WHERE ID = %d", $escapeVal));
			}
		}
	}
}

function leidsens_disable_editor(){
	?>
	<section class="lsbodyWrap">
	  	<div class="lsBrand">
	  		<h2>Disable Theme and Plugin Editor</h2>
		</div><?php
		$btn_val = 'Deactivate';
		$warningtxt = 'Deactivating the editor will make all the wordpress editors to be disabled. Make sure you\'re well aware of this.';
		$strings = file(ABSPATH . "wp-config.php");
		foreach($strings as $line) {
			$line = trim($line);
			if($line == "define( 'DISALLOW_FILE_EDIT', true );") {
				$btn_val = 'Activate';
			}
		}
		if (isset($_POST['leidsence_act_deact_btn'])) {
			$a = ABSPATH . 'wp-config.php';
   			$b = file_get_contents(ABSPATH . 'wp-config.php');
			
			if (htmlspecialchars(sanitize_text_field($_POST['leidsence_act_deact_value'])) == 'Deactivate') {
				$c = preg_replace('/\'DISALLOW_FILE_EDIT\', false/', '\'DISALLOW_FILE_EDIT\', true', $b, 1, $count);
				if ($count) {
					file_put_contents($a, $c);
					$btn_val = 'Activate';
					$warningtxt = 'Successfully deactivated the Theme and Plugins editors.';
				}else{
					$fp = fopen(ABSPATH . 'wp-config.php', 'a');//opens file in append mode
					fwrite($fp, "\ndefine( 'DISALLOW_FILE_EDIT', true );" );
					fclose($fp);
					$btn_val = 'Activate';
					$warningtxt = 'Successfully deactivated the Theme and Plugins editors.';
				}
			}else{
				$c = preg_replace('/\'DISALLOW_FILE_EDIT\', true/', '\'DISALLOW_FILE_EDIT\', false', $b, 1, $count);
				file_put_contents($a, $c);
				$btn_val = 'Deactivate';
				$warningtxt = 'Successfully Activated the Theme and Plugin editors.';
			}
		}
		?>
		<h3 style="text-align: center;"><?php echo esc_html($warningtxt) ?></h3>
		<div>
			<form action="" method="post" class="leidsens_disable_form">
				<div class="lsdbRow" style="margin-left: 38%;">
					<input type="hidden" name="leidsence_act_deact_value" value="<?php echo esc_html($btn_val); ?>">
					<input type="submit" name="leidsence_act_deact_btn" class="leidsence_act_deact_btn" id="leidsence_act_deact_btn" value="<?php echo esc_html($btn_val); ?> Theme and Plugin Editor">
				</div>
			</form>
		</div>
	</section>
	<div class="lpcBrand">
		<img src="<?php echo esc_url( plugins_url( 'images/leidsenslogo.png', __FILE__ ) ); ?>" alt="">
	</div><?php
}
function filter_plugin_checker_updates( $value ) {
	global $wpdb;
  	$table_name = $wpdb->prefix . "leidsens_plugin_checker_updates";
    $plugin_name = $wpdb->get_results("SELECT plugin_name FROM $table_name WHERE (disable_status = 'Yes')");
	foreach ($plugin_name as $plugin) {
		unset( $value->response[$plugin->plugin_name] );
	}
    return $value;
}
add_filter( 'site_transient_update_plugins', 'filter_plugin_checker_updates' ); ?>