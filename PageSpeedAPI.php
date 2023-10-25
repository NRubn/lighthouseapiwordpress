<?php
/*
Plugin Name: BaL PageSpeed Lighthouse Test
Description: Überprüft die PageSpeed mit Lighthouse
Version: 1.0
Author: Bits and Likes (R K)
Author URI: www.bitsandlikes.de
*/

$API_KEY = "API KEY";
$URL_TO_TEST = "Enter Website";


add_action('admin_menu', 'bal_lighthouse_menu');
add_action('admin_init', 'bal_lighthouse_register_settings');
add_action('admin_post_run_pagespeed_test', 'runPageSpeedTest');
add_action('my_custom_pagespeed_event', 'runPageSpeedTest');

function bal_lighthouse_menu(){
    add_menu_page('BaL Lighthouse Settings', 'BaL Lighthouse', 'manage_options', 'bal-lighthouse-settings', 'bal_lighthouse_settings_page');
    add_submenu_page('bal-lighthouse-settings', 'PageSpeed Ergebnisse', 'PageSpeed Ergebnisse', 'manage_options', 'page-speed-results', 'custom_result_page');
}

function bal_lighthouse_register_settings(){
    register_setting('bal_lighthouse_settings_group', 'bal_lighthouse_settings');
}

function bal_lighthouse_settings_page(){
    $options = get_option('bal_lighthouse_settings');
    $api_key = isset($options['api_key']) ? $options['api_key'] : '';
    $url_to_test = isset($options['url_to_test']) ? $options['url_to_test'] : '';

    $first_contentful_paint_weight = '';
    $speed_index_weight = '';
    $largest_contentful_paint_weight = '';
    $total_blocking_time_weight = '';
    $cumulative_layout_shift_weight = '';
    $repeat_interval = '';
  
    ?>
    <div class="wrap">
        <h2>Bal Lighthouse Settings</h2>
        <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
           
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">API-KEY</th>
                    <td>
                        <input type="text" name="bal_lighthouse_settings[api_key]" value="<?php echo esc_attr($api_key); ?>" />
                    </td>
                </tr>
	       
		<tr valign="top">
                    <th scope="row">URL to test</th>
                    <td>
                        <input type="text" name="bal_lighthouse_settings[url_to_test]" value="<?php echo esc_attr($url_to_test); ?>" />
                    </td>
		</tr>

		<tr valign="top">
		    <th scope="row">First Contentful Paint Weight</th>
		    <td>
			<input type="number" name="bal_lighthouse_settings[first_contentful_paint_weight]" value="<?php echo esc_attr($first_contentful_paint_weight);?>" step="0.01" min="0" max="100" />
		   </td>
		</tr>
		
		 <tr valign="top">  
 		    <th scope="row">Speed Index Weight</th>
		    <td>
			<input type="number" name="bal_lighthouse_settings[speed_index_weight]" value="<?php echo esc_attr($speed_index_weight);?>" step="0.01" min="0" max="100" />
		   </td>
		</tr>
		
		<tr valign="top">
		    <th scope="row">Largest Contentful Paint Weight</th>
		    <td>
			<input type="number" name="bal_lighthouse_settings[largest_contentful_paint_weight]" value="<?php echo esc_attr($largest_contentful_paint_weight);?>" step="0.01" min="0" max="100" />
		   </td>
		</tr>
		
		<tr valign="top">
		    <th scope="row">Total Blocking Time Weight</th>
		    <td>
			<input type="number" name="bal_lighthouse_settings[total_blocking_time_weight]" value="<?php echo esc_attr($total_blocking_time_weight);?>" step="0.01" min="0" max="100" />
		   </td>
		</tr>

		<tr valign="top">
		    <th scope="row">Cumulative Layout Shift Weight</th>
		    <td>
			<input type="number" name="bal_lighthouse_settings[cumulative_layout_weight]" value="<?php echo esc_attr($cumulative_layout_shift_weight);?>" step="0.01" min="0" max="100" />
		   </td>
		</tr>
		
		<tr valign="top">
		   <th scope="row">Wiederholung in Minuten</th>
		   <td>
		   	<input type="number" name="bal_lighthouse_settings[repeat_interval]" value="<?php echo esc_attr($repeat_interval);?>" />
		   </td>
		</tr>

	    </table>
        <input type="hidden" name="action" value="run_pagespeed_test" />
            <?php submit_button('Start PageSpeed-Test'); ?>
        </form>
    </div>
    <?php
}

function create_custom_table(){
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'bal_lighthouse_data';
    $charset_collate = $wpdb->get_charset_collate();
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $sql = "CREATE TABLE $table_name (
	    id int(11) NOT NULL AUTO_INCREMENT,
	    url_to_test varchar(255) NOT NULL,
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

register_activation_hook(__FILE__, 'bal_lighthouse_activate');


function save_json_data_to_db($json_data){
    global $wpdb;

    $table_name = $wpdb->prefix . 'bal_lighthouse_data';
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
 
    $table_name = $wpdb->prefix . 'bal_lighthouse_data';
    return $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC", 'ARRAY_A');
    if ($results) {
        return $results[0];
    }
    return false;
}


function runPageSpeedTest() {
    global $API_KEY, $URL_TO_TEST;


    if (isset($_POST['bal_lighthouse_settings'])) {
        $options = $_POST['bal_lighthouse_settings'];
        $API_KEY = $options['api_key'];
        $URL_TO_TEST = $options['url_to_test'];
    }

    try {
        $response = file_get_contents("https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=$URL_TO_TEST&key=$API_KEY");
	$data = json_decode($response, true);

	$options = get_option('bal_lighthouse_settings');
    	$first_contentful_paint_weight = isset($options['first_contentful_paint_weight']) ? $options['first_contentful_paint_weight'] / 100 : 0.1; 
    	$speed_index_weight = isset($options['speed_index_weight']) ? $options['speed_index_weight'] / 100 : 0.1;
    	$largest_contentful_paint_weight = isset($options['largest_contentful_paint_weight']) ? $options['largest_contentful_paint_weight'] / 100 : 0.25; 
    	$total_blocking_time_weight = isset($options['total_blocking_time_weight']) ? $options['total_blocking_time_weight'] / 100 : 0.3;
    	$cumulative_layout_shift_weight = isset($options['cumulative_layout_weight']) ? $options['cumulative_layout_weight'] / 100 : 0.25;
	
	$first_contentful_paint = floatval($data['lighthouseResult']['audits']['first-contentful-paint']['displayValue'] ?? 0);
	$speed_index = floatval($data['lighthouseResult']['audits']['speed-index']['displayValue'] ?? 0);
	$largest_contentful_paint = floatval($data['lighthouseResult']['audits']['largest-contentful-paint']['displayValue'] ?? 0);
	$total_blocking_time = floatval($data['lighthouseResult']['audits']['total-blocking-time']['displayValue'] ?? 0);
	$cumulative_layout_shift = floatval($data['lighthouseResult']['audits']['cumulative-layout-shift']['displayValue'] ?? 0);

	
	$page_speed_score=(
		$first_contentful_paint * $first_contentful_paint_weight +
		$speed_index * $speed_index_weight +
		$largest_contentful_paint * $largest_contentful_paint_weight +
		$total_blocking_time * $total_blocking_time_weight +
		$cumulative_layout_shift * $cumulative_layout_shift_weight
	) * 100;

	global $wpdb;
	$table_name = $wpdb->prefix . 'bal_lighthouse_data';

	$result = $wpdb->insert($table_name, array(
		'url_to_test' => $URL_TO_TEST,
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


function schedulePageSpeedTestRepeating(){
	$options = get_option('bal_lighthouse_settings');
	$repeat_interval = isset($options['repeat_interval']) ? interval($options['$repeat_interval']) : 0; 

	if($repeat_interval > 0){
		wp_schedule_event(time() + $repeat_interval * 60, 'bal_lighthouse_pagespeed_event','run_pagespeed_test');
	}	
}


function bal_lighthouse_activate(){
	create_custom_table();
	schedulePageSpeedTestRepeating();
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




