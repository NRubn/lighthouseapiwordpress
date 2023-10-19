<?php
/*
Plugin Name: PageSpeed Lighthouse Test
Description: Überprüft die PageSpeed mit Lighthouse
Version: 1.0
Author: Ihr Name
Author URI: Ihre Autoren-URI
*/

$API_KEY = "API KEY";
$URL_TO_TEST = "Enter Website";


add_action('admin_menu', 'my_plugin_menu');
add_action('admin_init', 'my_plugin_register_settings');
add_action('admin_post_run_pagespeed_test', 'runPageSpeedTest');

function my_plugin_menu(){
    add_menu_page('My Plugin Settings', 'My Plugin', 'manage_options', 'my-plugin-settings', 'my_plugin_settings_page');
    add_submenu_page('my-plugin-settings', 'PageSpeed Ergebnisse', 'PageSpeed Ergebnisse', 'manage_options', 'page-speed-results', 'custom_result_page');
}

function my_plugin_register_settings(){
    register_setting('my_plugin_settings_group', 'my_plugin_settings');
}

function my_plugin_settings_page(){
    $options = get_option('my_plugin_settings');
    $api_key = isset($options['api_key']) ? $options['api_key'] : '';
    $url_to_test = isset($options['url_to_test']) ? $options['url_to_test'] : '';

    ?>
    <div class="wrap">
        <h2>My Plugin Settings</h2>
        <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
           
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">API-KEY</th>
                    <td>
                        <input type="text" name="my_plugin_settings[api_key]" value="<?php echo esc_attr($api_key); ?>" />
                    </td>
                </tr>
	       
		<tr valign="top">
                    <th scope="row">URL to test</th>
                    <td>
                        <input type="text" name="my_plugin_settings[url_to_test]" value="<?php echo esc_attr($url_to_test); ?>" />
                    </td>
		</tr>

		<tr valign="top">
		    <th scope="row">First Contentful Paint Weight</th>
		    <td>
			<input type="number" name="my_plugin_settings[first_contentful_paint_weight]" value="<?php echo esc_attr($first_contentful_paint_weight);?>" step="0.01" min="0" max="100" />
		   </td>
		</tr>
		
		 <tr valign="top">  
 		    <th scope="row">Speed Index Weight</th>
		    <td>
			<input type="number" name="my_plugin_settings[speed_index_weight]" value="<?php echo esc_attr($first_contentful_paint_weight);?>" step="0.01" min="0" max="100" />
		   </td>
		</tr>
		
		<tr valign="top">
		    <th scope="row">Largest Contentful Paint Weight</th>
		    <td>
			<input type="number" name="my_plugin_settings[largest_contentful_paint_weight]" value="<?php echo esc_attr($first_contentful_paint_weight);?>" step="0.01" min="0" max="100" />
		   </td>
		</tr>
		
		<tr valign="top">
		    <th scope="row">Total Blocking Time Weight</th>
		    <td>
			<input type="number" name="my_plugin_settings[total_blocking_time_weight]" value="<?php echo esc_attr($first_contentful_paint_weight);?>" step="0.01" min="0" max="100" />
		   </td>
		</tr>

		<tr valign="top">
		    <th scope="row">Cumulative Layout Shift Weight</th>
		    <td>
			<input type="number" name="my_plugin_settings[cumulative_layout_weight]" value="<?php echo esc_attr($first_contentful_paint_weight);?>" step="0.01" min="0" max="100" />
		   </td>
		</tr>
		/*
		<tr valign="top">
		   <th scope="row">Wiederholung in Minuten</th>
		   <td>
		   	<input type="number" name"my_plugin_settings[repeat_interval]" value="<?php echo esc_attr($repeat_interval);?>" />
		   </td>
		</tr>
*/
	    </table>
        <input type="hidden" name="action" value="run_pagespeed_test" />
            <?php submit_button('Start PageSpeed-Test'); ?>
        </form>
    </div>
    <?php
}

function create_custom_table(){
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'my_plugin_data';
    $charset_collate = $wpdb->get_charset_collate();
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
	    first_contentful_paint varchar(255) NOT NULL,
	    speed_index varchar(255) NOT NULL,
	    largest_contentful_paint varchar(255) NOT NULL,
	    total_blocking_time varchar(255) NOT NULL,
	    cumulative_layout_shift varchar(255) NOT NULL,
	    page_speed_score varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	    dbDelta($sql);
    }
    
}

register_activation_hook(__FILE__, 'my_plugin_activate');

function my_plugin_activate(){
  create_custom_table();
}

function save_json_data_to_db($json_data){
    global $wpdb;

    $table_name = $wpdb->prefix . 'my_plugin_data';
    $result = $wpdb->insert($table_name, array (
        'data_json' => $json_data,
        'created_at' => current_time('mysql'),
    ));

    if($result === false){
        echo 'Fehler beim Einfügen in die Datenbank: ' . $wpdb->last_error;
    }
}

function get_data_from_db(){
    global $wpdb;
 
    $table_name = $wpdb->prefix . 'my_plugin_data';
    return $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC", 'ARRAY_A');
    if ($results) {
        return $results[0];
    }
    return false;
}

function runPageSpeedTest() {
    global $API_KEY, $URL_TO_TEST;


    if (isset($_POST['my_plugin_settings'])) {
        $options = $_POST['my_plugin_settings'];
        $API_KEY = $options['api_key'];
        $URL_TO_TEST = $options['url_to_test'];
    }

    try {
        $response = file_get_contents("https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=$URL_TO_TEST&key=$API_KEY");
	$data = json_decode($response, true);

	$options = get_option('my_plugin_settings');
    	$first_contentful_paint_weight = isset($options['first_contentful_paint_weight']) ? $options['first_contentful_paint_weight'] / 100 : 0.1; 
    	$speed_index_weight = isset($options['speed_index_weight']) ? $options['speed_index_weight'] / 100 : 0.1;
    	$largest_contentful_paint_weight = isset($options['largest_contentful_paint_weight']) ? $options['largest_contentful_paint_weight'] / 100 : 0.25; 
    	$total_blocking_time_weight = isset($options['total_blocking_time_weight']) ? $options['total_blocking_time_weight'] / 100 : 0.3;
    	$cumulative_layout_shift_weight = isset($options['cumulative_layout_weight']) ? $options['cumulative_layout_weight'] / 100 : 0.25;
	$repeat_interval = isset($options['repeat_interval']) ? interval($options['$repeat_interval']) : 0; 
	
	$first_contentful_paint = floatval($data['lighthouseResult']['audits']['first-contentful-paint']['displayValue']) ?? 0;
	$speed_index = floatval($data['lighthouseResult']['audits']['speed-index']['displayValue']) ?? 0;
	$largest_contentful_paint = floatval($data['lighthouseResult']['audits']['largest-contentful-paint']['displayValue']) ?? 0;
	$total_blocking_time = floatval($data['lighthouseResult']['audits']['total-blocking-time']['displayValue']) ?? 0;
	$cumulative_layout_shift = floatval($data['lighthouseResult']['audits']['cumulative-layout-shift']['displayValue']) ?? 0;

	if($repeat_interval > 0){
		wp_schdule_event(time() + $repeat_interval * 60, 'my_custom_pagespeed_event','run_pagespeed_test');
	}	
	
	$page_speed_score=(
		$first_contentful_paint * $first_contentful_paint_weight +
		$speed_index * $speed_index_weight +
		$largest_contentful_paint * $largest_contentful_paint_weight +
		$total_blocking_time * $total_blocking_time_weight +
		$cumulative_layout_shift * $cumulative_layout_shift_weight
	) * 100;

	global $wpdb;
	$table_name = $wpdb->prefix . 'my_plugin_data';

	$result = $wpdb->insert($table_name, array(
		'first_contentful_paint' => $first_contentful_paint,
		'speed_index' => $speed_index,
		'largest_contentful_paint' => $largest_contentful_paint,
		'total_blocking_time' => $total_blocking_time,
		'cumulative_layout_shift' => $cumulative_layout_shift,
		'page_speed_score' => $page_speed_score,
		'created_at' => current_time('mysql'),
	));

	if ($result === false){
		echo 'Fehler beim Einfuegen in die Datenbank: ' . $wpdb->last_error;
	} else {	
        	wp_redirect(admin_url('admin.php?page=page-speed-results'));
	}
    } catch (Exception $error) {
        echo 'Fehler: ' . $error->getMessage() . PHP_EOL;
    }
}

function custom_result_page(){
    echo '<div class="wrap">';
    echo '<h2>PageSpeed Test Ergebnisse</h2>';

    $data = get_data_from_db();

    if ($data !== false) {
        echo '<pre>';
        print_r($data);
    } else {
        echo 'Keine Ergebnisse verfuegbar';
    }
    echo '</div>';
}








