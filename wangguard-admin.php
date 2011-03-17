<?php
/*
Plugin Name: WangGuard
Plugin URI: http://www.wangguard.com
Description: Stop Sploggers
Version: 1.0.1
Author: WangGuard
Author URI: http://www.wangguard.com
License: GPL2
*/
?>
<?php
/*  Copyright 2010  WangGuard (email : info@wangguard.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
?>
<?php
define('WANGGUARD_VERSION', '1.0.1');

//error_reporting(E_ALL);
//ini_set("display_errors", 1);

include_once 'wangguard-xml.php';

//Which file are we are getting called from?
$wuangguard_parent = basename($_SERVER['SCRIPT_NAME']);



/********************************************************************/
/*** INIT & INSTALL BEGINS ***/
/********************************************************************/

//Plugin init
function wangguard_init() {
	global $wangguard_api_key , $wangguard_api_port , $wangguard_api_host , $wangguard_rest_path;

	$wangguard_api_key = get_option('wangguard_api_key');

	$wangguard_api_host = 'rest.wangguard.com';
	$wangguard_rest_path = '/';

	$wangguard_api_port = 80;

	if (function_exists('load_plugin_textdomain')) {
		$plugin_dir = basename(dirname(__FILE__));
		load_plugin_textdomain('wangguard', false, $plugin_dir . "/languages/" );
	}

	wp_register_style( 'wangguardCSS', "/" . PLUGINDIR . '/wangguard/wangguard.css' );

	wp_enqueue_style('wangguard', "/" . PLUGINDIR . '/wangguard/wangguard.css');

	add_action('admin_menu', 'wangguard_config_page');
	wangguard_admin_warnings();
}
add_action('init', 'wangguard_init');



//Admin init function
function wangguard_admin_init() {
	wp_enqueue_style( 'wangguardCSS' );
}
add_action('admin_init', 'wangguard_admin_init');




function wangguard_install() {
	global $wpdb;

	$wangguard_db_version = "1.0";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	$table_name = $wpdb->prefix . "wangguardquestions";
	if($wpdb->get_var("show tables like '$table_name'") != $table_name) {

		$sql = "CREATE TABLE " . $table_name . " (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			Question VARCHAR(255) NOT NULL,
			Answer VARCHAR(50) NOT NULL,
			RepliedOK INT(11) DEFAULT 0 NOT NULL,
			RepliedWRONG INT(11) DEFAULT 0 NOT NULL,
			UNIQUE KEY id (id)
		);";

		dbDelta($sql);
	}

	$table_name = $wpdb->prefix . "wangguarduserstatus";
	if($wpdb->get_var("show tables like '$table_name'") != $table_name) {

		$sql = "CREATE TABLE " . $table_name . " (
			ID BIGINT(20) NOT NULL,
			user_status VARCHAR(20) NOT NULL,
			user_ip VARCHAR(15) NOT NULL,
			UNIQUE KEY ID (ID)
		);";

		dbDelta($sql);
	}




	$table_name = $wpdb->prefix . "wangguardsignupsstatus";
	if($wpdb->get_var("show tables like '$table_name'") != $table_name) {

		$sql = "CREATE TABLE " . $table_name . " (
			signup_username VARCHAR(60) NOT NULL,
			user_status VARCHAR(20) NOT NULL,
			user_ip VARCHAR(15) NOT NULL,
			UNIQUE KEY signup_username (signup_username)
		);";

		dbDelta($sql);
	}

	//stats array
	$stats = array("check"=>0 , "detected"=>0);
	add_option("wangguard_stats", $stats);

	//db version
	add_option("wangguard_db_version", $wangguard_db_version);
}
register_activation_hook(__FILE__,'wangguard_install');


//Add the Settings link on the plugins page
function wangguard_action_links( $links, $file ) {
    if ( $file == plugin_basename(__FILE__) )
		$newlink = array('<a href="' . admin_url( 'plugins.php?page=wangguard-key-config' ) . '">'.esc_html(__('Settings', 'wangguard')).'</a>');
	else
		$newlink = array();

    return array_merge($newlink , $links);
}
add_filter('plugin_action_links', 'wangguard_action_links', 10, 2);
/********************************************************************/
/*** INIT & INSTALL ENDS ***/
/********************************************************************/







/********************************************************************/
/*** HELPER FUNCS BEGINS ***/
/********************************************************************/
//Is multisite?
function wangguard_is_multisite() {
	if (function_exists('is_multisite')) {
		return is_multisite();
	}
	else {
		global $wpmu;
		if ($wpmu == 1)
			return true;
		else
			return false;
	}
}

if ( !function_exists('wp_nonce_field') ) {
	function wangguard_nonce_field($action = -1) { return; }
	$wangguard_nonce = -1;
} else {
	function wangguard_nonce_field($action = -1) { return wp_nonce_field($action); }
	$wangguard_nonce = 'wangguard-update-key';
}

//Extracts the domain part from an email address
function wangguard_extract_domain($email) {
	$emailArr = split("@" , $email);
	if (!is_array($emailArr)) {
		return "";
	}
	else {
		return $emailArr[1];
	}
}


//update the stats
function wangguard_stats_update($action) {
	$stats = get_option("wangguard_stats");
	if (!is_array($stats)) {
		$stats = array("check"=>0 , "detected"=>0);
	}
	$stats[$action] = $stats[$action] + 1;
	update_option("wangguard_stats", $stats);
}


function wangguard_report_users($wpusersRs , $scope="email" , $deleteUser = true) {
	global $wangguard_api_key;
	global $wpdb;

	$valid = wangguard_verify_key($wangguard_api_key);
	if ($valid == 'failed') {
		echo "-2";
		die();
	}
	else if ($valid == 'invalid') {
		echo "-1";
		die();
	}

	if (!$wpusersRs) {
		return "0";
	}

	$usersFlagged = array();
	foreach ($wpusersRs as $spuserID) {
		$user_object = new WP_User($spuserID);


		if ( current_user_can( 'delete_users' ) && !wangguard_is_admin($user_object) ) {
			if (!empty ($user_object->user_email)) {
				//Get the user's client IP from which he signed up
				$table_name = $wpdb->prefix . "wangguarduserstatus";
				$clientIP = $wpdb->get_var( $wpdb->prepare("select user_ip from $table_name where ID = %d" , $user_object->ID) );

				if ($scope == 'domain')
					$response = wangguard_http_post("wg=<in><apikey>$wangguard_api_key</apikey><domain>".wangguard_extract_domain($user_object->user_email)."</domain><ip>".$clientIP."</ip></in>", 'add-domain.php');
				else
					$response = wangguard_http_post("wg=<in><apikey>$wangguard_api_key</apikey><email>".$user_object->user_email."</email><ip>".$clientIP."</ip></in>", 'add-email.php');
			}


			if ($deleteUser) {

				if (wangguard_is_multisite () && function_exists("wpmu_delete_user"))
					wpmu_delete_user($spuserID);
				else
					wp_delete_user($spuserID);
			}
			else {
				global $wpdb;
				
				//Update the new status
				$table_name = $wpdb->prefix . "wangguarduserstatus";
				$wpdb->query( $wpdb->prepare("update $table_name set user_status = 'reported' where ID = '%d'" , $spuserID ) );
			}
			$usersFlagged[] = $spuserID;
		}
	}

	if (count($usersFlagged))
		return join (",", $usersFlagged);
	else
		return "0";
}

function wangguard_is_admin($user_object) {
	return $user_object->has_cap('administrator');
}
/********************************************************************/
/*** HELPER FUNCS ENDS ***/
/********************************************************************/







/********************************************************************/
/*** CONFIG BEGINS ***/
/********************************************************************/
//Add config link on the left menu
function wangguard_config_page() {
	if ( function_exists('add_submenu_page') )
		add_submenu_page('plugins.php', __('WangGuard Configuration', 'wangguard'), __('WangGuard Configuration', 'wangguard'), 'manage_options', 'wangguard-key-config', 'wangguard_conf');
}

//Configuration page
function wangguard_conf() {
	global $wpdb;
	global $wangguard_nonce, $wangguard_api_key;

	$key_status = "";

	if ( isset($_POST['submit']) ) {
		if ( function_exists('current_user_can') && !current_user_can('manage_options') )
			die(__('Cheatin&#8217; uh?', 'wangguard'));

		check_admin_referer( $wangguard_nonce );
		$key = preg_replace( '/[^a-h0-9]/i', '', $_POST['key'] );
		
		if ( empty($key) ) {
			$key_status = 'empty';
			$ms[] = 'new_key_empty';
			delete_option('wangguard_api_key');
		} else {
			$key_status = wangguard_verify_key( $key );
		}

		if ( $key_status == 'valid' ) {
			update_option('wangguard_api_key', $key);
			$ms[] = 'new_key_valid';
		} else if ( $key_status == 'invalid' ) {
			$ms[] = 'new_key_invalid';
		} else if ( $key_status == 'failed' ) {
			$ms[] = 'new_key_failed';
		}

	} elseif ( isset($_POST['check']) ) {

		wangguard_get_server_connectivity(0);

	} elseif ( isset($_POST['optssave']) ) {

			update_option('wangguard-expertmode', $_POST['wangguardexpertmode']=='1' ? 1 : 0 );

			echo "<div id='wangguard-warning' class='updated fade'><p><strong>".__('WangGuard settings has been saved.', 'wangguard')."</strong></p></div>";

	}


	if ( $key_status != 'valid' ) {
		$key = get_option('wangguard_api_key');
		if ( empty( $key ) ) {
			if ( $key_status != 'failed' ) {
				if ( wangguard_verify_key( '1234567890ab' ) == 'failed' )
					$ms[] = 'no_connection';
				else
					$ms[] = 'key_empty';
			}
			$key_status = 'empty';
		} else {
			$key_status = wangguard_verify_key( $key );
		}
		if ( $key_status == 'valid' ) {
			$ms[] = 'key_valid';
		} else if ( $key_status == 'invalid' ) {
			delete_option('wangguard_api_key');
			$ms[] = 'key_empty';
		} else if ( !empty($key) && $key_status == 'failed' ) {
			$ms[] = 'key_failed';
		}
	}


	$messages = array(
		'new_key_empty' => array('color' => 'aa0', 'text' => __('Your key has been cleared.', 'wangguard')),
		'new_key_valid' => array('color' => '2d2', 'text' => __('Your key has been verified!', 'wangguard')),
		'new_key_invalid' => array('color' => 'd22', 'text' => __('The key you entered is invalid. Please double-check it.', 'wangguard')),
		'new_key_failed' => array('color' => 'd22', 'text' => __('The key you entered could not be verified because a connection to wangguard.com could not be established. Please check your server configuration.', 'wangguard')),
		'no_connection' => array('color' => 'd22', 'text' => __('There was a problem connecting to the WangGuard server. Please check your server configuration.', 'wangguard')),
		'key_empty' => array('color' => 'aa0', 'text' => sprintf(__('Please enter an API key. (<a href="%s" style="color:#fff">Get your key here.</a>)', 'wangguard'), 'http://wangguard.com/getapikey')),
		'key_valid' => array('color' => '2d2', 'text' => __('This key is valid.', 'wangguard')),
		'key_failed' => array('color' => 'aa0', 'text' => __('The key below was previously validated but a connection to wangguard.com can not be established at this time. Please check your server configuration.', 'wangguard')));

?>


<?php if ( !empty($_POST['submit'] ) ) : ?>
<div id="message" class="updated fade"><p><strong><?php _e('Options saved.', 'wangguard') ?></strong></p></div>
<?php endif; ?>


<div class="wrap">
<h2><?php _e('WangGuard Configuration', 'wangguard'); ?></h2>
<div class="narrow">
<form action="" method="post" id="wangguard-conf" style="margin: auto; width: 500px; ">
	<p><?php printf(__('For many people, <a href="%1$s">WangGuard</a> will greatly reduce or even completely eliminate the Sploggers you get on your site. If one does happen to get through, simply mark it as Splogger on the Users screen. If you don\'t have an API key yet, <a href="%2$s" target="_new">get one here</a>.', 'wangguard'), 'http://wangguard.com/', 'http://wangguard.com/getapikey'); ?></p>

	<h3><label for="key"><?php _e('WangGuard API Key', 'wangguard'); ?></label></h3>
	<?php foreach ( $ms as $m ) : ?>
		<p style="padding: .5em; background-color: #<?php echo $messages[$m]['color']; ?>; color: #fff; font-weight: bold;"><?php echo $messages[$m]['text']; ?></p>
	<?php endforeach; ?>
	<p><input id="key" name="key" type="text" size="35" maxlength="32" value="<?php echo get_option('wangguard_api_key'); ?>" style="font-family: 'Courier New', Courier, mono; font-size: 1.5em;" /> (<?php _e('<a href="http://wangguard.com/faq" target="_new">What is this?</a>', 'wangguard'); ?>)</p>

	<?php if ( $invalid_key ) { ?>
		<h3><?php _e('Why might my key be invalid?', 'wangguard'); ?></h3>
		<p><?php _e('This can mean one of two things, either you copied the key wrong or that the plugin is unable to reach the WangGuard servers, which is most often caused by an issue with your web host around firewalls or similar.', 'wangguard'); ?></p>
	<?php } ?>


	<?php wangguard_nonce_field($wangguard_nonce) ?>

	<p class="submit"><input type="submit" name="submit" value="<?php _e('Update options &raquo;', 'wangguard'); ?>" /></p>
</form>

	
<div id="wangguard-questions" style="margin: auto; width: 500px;">

	<h3><?php _e('Security questions', 'wangguard'); ?></h3>
	<p><?php _e('Security questions are randomly asked on the registration form to prevent automated signups.', 'wangguard')?></p>
	<p><?php _e('Security questions are optional, it\'s up to you whether to use them or not.', 'wangguard')?></p>
	<p><?php _e('Create you own security questions from the form below, or delete the questions you don\'t want anymore.', 'wangguard')?></p>
	<?php
	$table_name = $wpdb->prefix . "wangguardquestions";
	$wgquestRs = $wpdb->get_results("select * from $table_name order by id");

	if (!empty ($wgquestRs)) {
		?><h4><?php _e('Existing security questions', 'wangguard')?></h4><?php
	}
	foreach ($wgquestRs as $question) {?>
		<div class="wangguard-question" id="wangguard-question-<?php echo $question->id?>">
		<?php _e("Question", 'wangguard')?>: <strong><?php echo $question->Question?></strong><br/>
		<?php _e("Answer", 'wangguard')?>: <strong><?php echo $question->Answer?></strong><br/>
		<?php _e("Replied OK / Wrong", 'wangguard')?>: <strong><?php echo $question->RepliedOK?> / <?php echo $question->RepliedWRONG?></strong><br/>
		<a href="javascript:void(0)" rel="<?php echo $question->id?>" class="wangguard-delete-question"><?php _e('delete question', 'wangguard')?></a>
		</div>
	<?php } ?>
	<div id="wangguard-new-question-container">
	</div>

	<h4><?php _e('Add a new security question', 'wangguard')?></h4>
	<?php _e("Question", 'wangguard')?><br/><input type="text" name="wangguardnewquestion" id="wangguardnewquestion" style="width: 500px; padding: 6px" maxlength="255" value="" />
	<br/><br style="line-height: 5px"/>
	<?php _e("Answer", 'wangguard')?><br/><input type="text" name="wangguardnewquestionanswer" id="wangguardnewquestionanswer" style="width: 500px; padding: 6px" maxlength="50" value="" />
	<div id="wangguardnewquestionerror">
		<?php _e('Fill in both the question and the answer fields to create a new security question', 'wangguard')?>
	</div>
	<p class="submit"><input type="button" id="wangguardnewquestionbutton" name="submit" value="<?php _e('Create question &raquo;', 'wangguard'); ?>" /></p>
</div>



<form action="" method="post" id="wangguard-settings" class="wangguard-sep" style="margin:30px auto 0 auto; width: 500px; ">
	<h3><?php _e("WangGuard settings", 'wangguard') ?></h3>
	<p>
		<input type="checkbox" name="wangguardexpertmode" id="wangguardexpertmode" value="1" <?php echo get_option("wangguard-expertmode")=='1' ? 'checked' : ''?> />
		<?php _e("<strong>Ninja mode.</strong><br/>By checking this option no confirmation message will be asked for report operations on the Users manager. Just remember that users gets deleted when reported, and when reporting a domain, users whose e-mail matches the reported domain gets deleted as well.", 'wangguard') ?>
	</p>

	<p class="submit"><input type="submit" name="optssave" value="<?php _e('Save options &raquo;', 'wangguard'); ?>" /></p>
	
</form>


<form action="" method="post" id="wangguard-connectivity" style="margin:30px auto 0 auto; width: 500px; ">

<h3><?php _e('Server Connectivity', 'wangguard'); ?></h3>
<?php
	if ( !function_exists('fsockopen') || !function_exists('gethostbynamel') ) {
		?>
			<p style="padding: .5em; background-color: #d22; color: #fff; font-weight:bold;"><?php _e('Network functions are disabled.', 'wangguard'); ?></p>
			<p><?php echo sprintf( __('Your web host or server administrator has disabled PHP\'s <code>fsockopen</code> or <code>gethostbynamel</code> functions.  <strong>WangGuard cannot work correctly until this is fixed.</strong>  Please contact your web host or firewall administrator.', 'wangguard')); ?></p>
		<?php
	} else {
		$servers = wangguard_get_server_connectivity();
		$fail_count = count($servers) - count( array_filter($servers) );
		if ( is_array($servers) && count($servers) > 0 ) {
			// some connections work, some fail
			if ( $fail_count > 0 && $fail_count < count($servers) ) { ?>
				<p style="padding: .5em; background-color: #aa0; color: #fff; font-weight:bold;"><?php _e('Unable to reach some WangGuard servers.', 'wangguard'); ?></p>
				<p><?php echo sprintf( __('A network problem or firewall is blocking some connections from your web server to WangGuard.com.  WangGuard is working but this may cause problems during times of network congestion.', 'wangguard')); ?></p>
			<?php
			// all connections fail
			} elseif ( $fail_count > 0 ) { ?>
				<p style="padding: .5em; background-color: #d22; color: #fff; font-weight:bold;"><?php _e('Unable to reach any WangGuard servers.', 'wangguard'); ?></p>
				<p><?php echo sprintf( __('A network problem or firewall is blocking all connections from your web server to WangGuard.com.  <strong>WangGuard cannot work correctly until this is fixed.</strong>', 'wangguard')); ?></p>
			<?php
			// all connections work
			} else { ?>
				<p style="padding: .5em; background-color: #2d2; color: #fff; font-weight:bold;"><?php  _e('All WangGuard servers are available.', 'wangguard'); ?></p>
				<p><?php _e('WangGuard is working correctly.  All servers are accessible.', 'wangguard'); ?></p>
			<?php
			}
		} else {
			?>
				<p style="padding: .5em; background-color: #d22; color: #fff; font-weight:bold;"><?php _e('Unable to find WangGuard servers.', 'wangguard'); ?></p>
				<p><?php echo sprintf( __('A DNS problem or firewall is preventing all access from your web server to wangguard.com.  <strong>WangGuard cannot work correctly until this is fixed.</strong>', 'wangguard')); ?></p>
			<?php
		}
	}

	if ( !empty($servers) ) {
	?>
		<table style="width: 100%;">
		<thead><th><?php _e('WangGuard server', 'wangguard'); ?></th><th><?php _e('Network Status', 'wangguard'); ?></th></thead>
		<tbody>
			<?php
			asort($servers);
			foreach ( $servers as $ip => $status ) {
				$color = ( $status ? '#2d2' : '#d22');?>
			<tr>
			<td><?php echo htmlspecialchars($ip); ?></td>
			<td style="padding: 0 .5em; font-weight:bold; color: #fff; background-color: <?php echo $color; ?>"><?php echo ($status ? __('No problems', 'wangguard') : __('Obstructed', 'wangguard') ); ?></td>

			<?php
			}
	}
	?>
	</tbody>
	</table>
	<p><?php if ( get_option('wangguard_connectivity_time') ) echo sprintf( __('Last checked %s ago.', 'wangguard'), human_time_diff( get_option('wangguard_connectivity_time') ) ); ?></p>
	<p class="submit"><input type="submit" name="check" value="<?php _e('Check network status &raquo;', 'wangguard'); ?>" /></p>
</form>

</div>
</div>
<?php
}
/********************************************************************/
/*** CONFIG ENDS ***/
/********************************************************************/








/********************************************************************/
/*** KEY FUNCS BEGINS ***/
/********************************************************************/
//Return WangGuard stored API KEY
function wangguard_get_key() {
	global $wangguard_api_key;
	if ( !empty($wangguard_api_key) )
		return $wangguard_api_key;
	return get_option('wangguard_api_key');
}

//Checks the API KEY against wangguard service
function wangguard_verify_key( $key, $ip = null ) {
	global $wangguard_api_key;
	if ( $wangguard_api_key )
		$key = $wangguard_api_key;

	$response = wangguard_http_post("wg=<in><apikey>$key</apikey></in>", 'verify-key.php' , $ip);


	$responseArr = XML_unserialize($response);

	if ( !is_array($responseArr))
		return 'failed';
	elseif ($responseArr['out']['cod'] != '0')
		return 'invalid';
	else
		return "valid";
}
/********************************************************************/
/*** KEY FUNCS ENDS ***/
/********************************************************************/














/********************************************************************/
/*** NETWORKING FUNCTIONS BEGINS ***/
/********************************************************************/

// Check connectivity between the WordPress blog and wangguard's servers.
// Returns an associative array of server IP addresses, where the key is the IP address, and value is true (available) or false (unable to connect).
function wangguard_check_server_connectivity() {
	global $wangguard_api_host;

	// Some web hosts may disable one or both functions
	if ( !function_exists('fsockopen') || !function_exists('gethostbynamel') )
		return array();

	$ips = gethostbynamel($wangguard_api_host);
	if ( !$ips || !is_array($ips) || !count($ips) )
		return array();

	$servers = array();
	foreach ( $ips as $ip ) {
		$response = wangguard_verify_key( wangguard_get_key(), $ip );
		// even if the key is invalid, at least we know we have connectivity
		if ( $response == 'valid' || $response == 'invalid' )
			$servers[$ip] = true;
		else
			$servers[$ip] = false;
	}

	return $servers;
}


// Check the server connectivity and store the results in an option.
// Cached results will be used if not older than the specified timeout in seconds; use $cache_timeout = 0 to force an update.
// Returns the same associative array as wangguard_check_server_connectivity()
function wangguard_get_server_connectivity( $cache_timeout = 86400 ) {
	$servers = get_option('wangguard_available_servers');
	if ( (time() - get_option('wangguard_connectivity_time') < $cache_timeout) && $servers !== false )
		return $servers;

	// There's a race condition here but the effect is harmless.
	$servers = wangguard_check_server_connectivity();
	update_option('wangguard_available_servers', $servers);
	update_option('wangguard_connectivity_time', time());
	return $servers;
}

// Returns true if server connectivity was OK at the last check, false if there was a problem that needs to be fixed.
function wangguard_server_connectivity_ok() {
	// skip the check on WPMU because the status page is hidden
	global $wangguard_api_key;
	if ( $wangguard_api_key )
		return true;
	$servers = wangguard_get_server_connectivity();
	return !( empty($servers) || !count($servers) || count( array_filter($servers) ) < count($servers) );
}


function wangguard_get_host($host) {
	// if all servers are accessible, just return the host name.
	// if not, return an IP that was known to be accessible at the last check.
	if ( wangguard_server_connectivity_ok() ) {
		return $host;
	} else {
		$ips = wangguard_get_server_connectivity();
		// a firewall may be blocking access to some wangguard IPs
		if ( count($ips) > 0 && count(array_filter($ips)) < count($ips) ) {
			// use DNS to get current IPs, but exclude any known to be unreachable
			$dns = (array)gethostbynamel( rtrim($host, '.') . '.' );
			$dns = array_filter($dns);
			foreach ( $dns as $ip ) {
				if ( array_key_exists( $ip, $ips ) && empty( $ips[$ip] ) )
					unset($dns[$ip]);
			}
			// return a random IP from those available
			if ( count($dns) )
				return $dns[ array_rand($dns) ];

		}
	}
	// if all else fails try the host name
	return $host;
}


// Returns the server's response body
function wangguard_http_post($request, $op , $ip=null) {
	global $wp_version;
	global $wangguard_api_port , $wangguard_api_host , $wangguard_rest_path;

	$wangguard_version = constant('WANGGUARD_VERSION');

	$http_request  = "POST {$wangguard_rest_path}{$op} HTTP/1.0\r\n";
	$http_request .= "Host: $wangguard_api_host\r\n";
	$http_request .= "Content-Type: application/x-www-form-urlencoded; charset=" . get_option('blog_charset') . "\r\n";
	$http_request .= "Content-Length: " . strlen($request) . "\r\n";
	$http_request .= "User-Agent: WordPress/$wp_version | WangGuard/$wangguard_version\r\n";
	$http_request .= "\r\n";
	$http_request .= $request;

	if (!empty ($ip))
		$http_host = $ip;
	else
		$http_host = wangguard_get_host($wangguard_api_host);

	//Init response buffer
	$response = '';


	/*fsock connection*/
	if( false != ( $fs = @fsockopen($http_host, $wangguard_api_port, $errno, $errstr, 5) ) ) {
		fwrite($fs, $http_request);

		while ( !feof($fs) )
			$response .= fgets($fs, 1100);
		fclose($fs);
	}
	/*fsock connection*/


	$response = str_replace("\r", "", $response);
	$response = substr($response, strpos($response, "\n\n")+2);

	return $response;
}
/********************************************************************/
/*** NETWORKING FUNCTIONS END ***/
/********************************************************************/










/********************************************************************/
/*** NOTICES & RIGHT NOW BEGINS ***/
/********************************************************************/
//Shows admin warnings if any
function wangguard_admin_warnings() {
	global $wangguard_api_key;

	if ( !get_option('wangguard_api_key') && !$wangguard_api_key && !isset($_POST['submit']) ) {
		function wangguard_warning() {
			echo "
			<div id='wangguard-warning' class='updated fade'><p><strong>".__('WangGuard is almost ready.', 'wangguard')."</strong> ".sprintf(__('You must <a href="%1$s">enter your WangGuard API key</a> for it to work.', 'wangguard'), "plugins.php?page=wangguard-key-config")."</p></div>
			";
		}
		add_action('admin_notices', 'wangguard_warning');
		return;
	} elseif ( get_option('wangguard_connectivity_time') && empty($_POST) && is_admin() && !wangguard_server_connectivity_ok() ) {
		function wangguard_warning() {
			echo "
			<div id='wangguard-warning' class='updated fade'><p><strong>".__('WangGuard has detected a problem.', 'wangguard')."</strong> ".sprintf(__('A server or network problem is preventing WangGuard from working correctly.  <a href="%1$s">Click here for more information</a> about how to fix the problem.', 'wangguard'), "plugins.php?page=wangguard-key-config")."</p></div>
			";
		}
		add_action('admin_notices', 'wangguard_warning');
		return;
	}
}


//dashboard right now activity
function wangguard_rightnow() {
	$stats = get_option("wangguard_stats");
	if (!is_array($stats)) {
		$stats = array("check"=>0 , "detected"=>0);
	}

	$rightnow = sprintf(__('WangGuard has checked %d users, and detected %d Sploggers.' , 'wangguard') , $stats['check'] , $stats['detected']);

	echo "<p class='wangguard-right-now'>$rightnow</p>\n";
}
add_action('rightnow_end', 'wangguard_rightnow');
/********************************************************************/
/*** NOTICES & RIGHT NOW ENDS ***/
/********************************************************************/












/********************************************************************/
/*** USER SCREEN ACTION & COLS BEGINS ***/
/********************************************************************/
//Add the WangGuard status column
function wangguard_add_status_column($columns) {
	$columns['wangguardstatus'] = __("WangGuard Status", 'wangguard');
	return $columns;
}
function wangguard_wpmu_custom_columns($column_name , $userid) {
	wangguard_user_custom_columns('' , $column_name , $userid , true);
}
function wangguard_user_custom_columns($dummy , $column_name , $userid , $echo = false ) {
	global $wpdb;

	$html = "";

	if ($column_name == 'wangguardstatus' ) {
		$table_name = $wpdb->prefix . "wangguarduserstatus";
		$status = $wpdb->get_var( $wpdb->prepare("select user_status from $table_name where ID = %d" , $userid) );

		if (empty ($status)) {
			$html = '<span class="wangguard-status-no-status wangguardstatus-'.$userid.'">'. __('No status', 'wangguard') .'</span>';
		}
		elseif ($status == 'not-checked') {
			$html = '<span class="wangguard-status-not-checked wangguardstatus-'.$userid.'">'. __('Not checked', 'wangguard') .'</span>';
		}
		elseif ($status == 'reported') {
			$html = '<span class="wangguard-status-splogguer wangguardstatus-'.$userid.'">'. __('Reported as Splogger', 'wangguard') .'</span>';
		}
		elseif ($status == 'autorep') {
			$html = '<span class="wangguard-status-splogguer wangguardstatus-'.$userid.'">'. __('Automatically reported as Splogger', 'wangguard') .'</span>';
		}
		elseif ($status == 'checked') {
			$html = '<span class="wangguard-status-checked wangguardstatus-'.$userid.'">'. __('Checked', 'wangguard') .'</span>';
		}
		elseif (substr($status , 0 , 5) == 'error') {
			$html = '<span class="wangguard-status-error wangguardstatus-'.$userid.'">'. __('Error', 'wangguard') . " - " . substr($status , 6) . '</span>';
		}
		else {
			$html = '<span class="wangguardstatus-'.$userid.'">'. $status . '</span>';
		}

		$user_object = new WP_User($userid);

		$Domain = split("@",$user_object->user_email);
		$Domain = $Domain[1];

		$html .= "<br/><div class=\"row-actions\">";
		if ( current_user_can( 'delete_users' ) && !wangguard_is_admin($user_object) ) {
			$html .= '<a href="javascript:void(0)" rel="'.$user_object->ID.'" class="wangguard-splogger">'.esc_html(__('Splogger', 'wangguard')).'</a> | ';
			//$html .= '<a href="javascript:void(0)" rel="'.$user_object->ID.'" class="wangguard-domain">'.esc_html(__('Report Domain', 'wangguard')).'</a> | ';
			$html .= '<a href="javascript:void(0)" rel="'.$user_object->ID.'" class="wangguard-recheck">'.esc_html(__('Recheck', 'wangguard')).'</a> | ';
			$html .= '<a href="http://'.$Domain.'" target="_new">'.esc_html(__('Open Web', 'wangguard')).'</a>';
		}
		$html .= "</div>";
   	}

	if ($echo)
		echo $html;
	else
		return $html;
}
add_filter('manage_users_columns', 'wangguard_add_status_column');
add_filter('wpmu_users_columns', 'wangguard_add_status_column');

//If called from ms-admin, call wpmu handler (2 params), else the 3 params func
if (($wuangguard_parent == 'ms-users.php') || ($wuangguard_parent == 'wpmu-users.php'))
	add_action('manage_users_custom_column', 'wangguard_wpmu_custom_columns', 10, 2);
else
	add_action('manage_users_custom_column', 'wangguard_user_custom_columns', 10, 3);
/********************************************************************/
/*** USER SCREEN ACTION & COLS ENDS ***/
/********************************************************************/












/********************************************************************/
/*** ADD & VALIDATE SECURITY QUESTIONS ON REGISTER BEGINS ***/
/********************************************************************/

// for wp regular
add_action('register_form','wangguard_register_add_question');
add_action('register_post','wangguard_signup_validate',10,3);

// for buddypress 1.1 only
add_action('bp_before_registration_submit_buttons', 'wangguard_register_add_question_bp11');
add_action('bp_signup_validate', 'wangguard_signup_validate_bp11' );

// for wpmu and (buddypress versions before 1.1)
add_action('signup_extra_fields', 'wangguard_register_add_question_mu' );
add_filter('wpmu_validate_user_signup', 'wangguard_wpmu_signup_validate_mu');


//*********** WPMU ***********
//Adds a security question if any exists
function wangguard_register_add_question_mu($errors) {
	global $wpdb;

	$table_name = $wpdb->prefix . "wangguardquestions";

	//Get one random question from the question table
	$qrs = $wpdb->get_row("select * from $table_name order by RAND() LIMIT 1");

	if (!is_null($qrs)) {
		$question = $qrs->Question;
		$questionID = $qrs->id;

		$html = '
			<label for="wangguardquestansw">' . $question . '</label>';
		echo $html;

		if ( $errmsg = $errors->get_error_message('wangguardquestansw') ) {
			echo '<p class="error">'.$errmsg.'</p>';
		}

		$html = '
			<input type="text" name="wangguardquestansw" id="wangguardquestansw" class="wangguard-mu-register-field" value="" maxlength="50" />
			<input type="hidden" name="wangguardquest" value="'.$questionID.'" />
		';
		echo $html;
	}
}

//Validates security question
function wangguard_wpmu_signup_validate_mu($param) {
	global $wangguard_bp_validated;

	//BP1.1+ calls the new BP filter first (wangguard_signup_validate_bp11) and then the legacy MU filters (this one), if the BP new 1.1+ filter has been already called, silently return
	if ($wangguard_bp_validated)
		return $param;

	$answerOK = wangguard_question_repliedOK();

	$errors = $param['errors'];

	//If at least a question exists on the questions table, then check the provided answer
	if (!$answerOK)
	    $errors->add('wangguardquestansw', addslashes( __('<strong>ERROR</strong>: The answer to the security question is invalid.', 'wangguard')));
	else {

		$reported = wangguard_is_email_reported_as_sp($param['user_email'] , $_SERVER['REMOTE_ADDR']);

		if ($reported) 
			$errors->add('user_email',  addslashes( __('<strong>ERROR</strong>: Banned by WangGuard <a href="http://www.wangguard.com/faq" target="_new">Is a mistake?</a>.', 'wangguard')));
	}
	return $param;
}
//*********** WPMU ***********




//*********** BP1.1+ ***********
//Adds a security question if any exists
function wangguard_register_add_question_bp11(){
	global $wpdb;

	$table_name = $wpdb->prefix . "wangguardquestions";

	//Get one random question from the question table
	$qrs = $wpdb->get_row("select * from $table_name order by RAND() LIMIT 1");

	if (!is_null($qrs)) {
		$question = $qrs->Question;
		$questionID = $qrs->id;

		$html = '
			<div id="wangguard-bp-register-form" class="register-section">
			<label for="wangguardquestansw">' . $question . '</label>';
		echo $html;

		do_action( 'bp_wangguardquestansw_errors' );
		
		$html = '
			<input type="text" name="wangguardquestansw" id="wangguardquestansw" value="" maxlength="50" />
			<input type="hidden" name="wangguardquest" value="'.$questionID.'" />
			</div>
		';
		echo $html;
	}
}

//Validates security question
function wangguard_signup_validate_bp11() {
	global $bp;
	global $wangguard_bp_validated;

	$wangguard_bp_validated = true;

	$answerOK = wangguard_question_repliedOK();

	//If at least a question exists on the questions table, then check the provided answer
	if (!$answerOK)
		$bp->signup->errors['wangguardquestansw'] = addslashes (__('<strong>ERROR</strong>: The answer to the security question is invalid.', 'wangguard'));
	else {

		$reported = wangguard_is_email_reported_as_sp($_REQUEST['signup_email'] , $_SERVER['REMOTE_ADDR']);

		if ($reported)
			$bp->signup->errors['signup_email'] = addslashes (__('<strong>ERROR</strong>: Banned by WangGuard <a href="http://www.wangguard.com/faq" target="_new">Is a mistake?</a>.', 'wangguard'));
	}
}
//*********** BP1.1+ ***********





//*********** WP REGULAR ***********
//Adds a security question if any exists
function wangguard_register_add_question(){
	global $wpdb;

	$table_name = $wpdb->prefix . "wangguardquestions";

	//Get one random question from the question table
	$qrs = $wpdb->get_row("select * from $table_name order by RAND() LIMIT 1");

	if (!is_null($qrs)) {
		$question = $qrs->Question;
		$questionID = $qrs->id;

		$html = '
			<div width="100%">
			<p>
			<label style="display: block; margin-bottom: 5px;">' . $question . '
			<input type="text" name="wangguardquestansw" id="wangguardquestansw" class="input wpreg-wangguardquestansw" value="" size="20" maxlength="50" tabindex="26" />
			</label>
			<input type="hidden" name="wangguardquest" value="'.$questionID.'" />
			</p>
			</div>
		';
		echo $html;
	}
}


//Validates security question
function wangguard_signup_validate($user_name , $user_email,$errors){
	$answerOK = wangguard_question_repliedOK();

	//If at least a question exists on the questions table, then check the provided answer
	if (!$answerOK)
		$errors->add('wangguard_error',__('<strong>ERROR</strong>: The answer to the security question is invalid.', 'wangguard'));
	else {

		$reported = wangguard_is_email_reported_as_sp($_REQUEST['user_email'] , $_SERVER['REMOTE_ADDR'] , true);

		if ($reported)
			$errors->add('wangguard_error',__('<strong>ERROR</strong>: Banned by WangGuard <a href="http://www.wangguard.com/faq" target="_new">Is a mistake?</a>.', 'wangguard'));
	}
}
//*********** WP REGULAR ***********





//Verify the email against WangGuard service
//$callingFromRegularWPHook regular WP hook sends true on this param
function wangguard_is_email_reported_as_sp($email , $clientIP , $callingFromRegularWPHook = false) {
	global $wpdb;
	global $wangguard_api_key;
	global $wangguard_user_check_status;


	$wangguard_user_check_status = "not-checked";

	$response = wangguard_http_post("wg=<in><apikey>$wangguard_api_key</apikey><email>".$email."</email><ip>".$clientIP."</ip></in>", 'query-email.php');
	$responseArr = XML_unserialize($response);

	wangguard_stats_update("check");

	if ( is_array($responseArr)) {
		if (($responseArr['out']['cod'] == '10') || ($responseArr['out']['cod'] == '11')) {
			wangguard_stats_update("detected");
			return true;
		}
		else {
			if ($responseArr['out']['cod'] == '20')
				$wangguard_user_check_status = 'checked';
			else
				$wangguard_user_check_status = 'error:'.$responseArr['out']['cod'];
		}
	}

	return false;
}



//Verify the security question, used from the WP, WPMU and BP validation functions
function wangguard_question_repliedOK() {
	global $wpdb;

	$table_name = $wpdb->prefix . "wangguardquestions";

	//How many questions are created?
	$questionCount = $wpdb->get_col("select count(*) as q from $table_name");

	$answerOK = true;

	//If at least a question exists on the questions table, then check the provided answer
	if ($questionCount[0]) {
		$questionID = intval($_REQUEST['wangguardquest']);
		$answer = $_REQUEST['wangguardquestansw'];

		$qrs = $wpdb->get_row( $wpdb->prepare("select * from $table_name where id = %d" , $questionID));
		if (!is_null($qrs)) {
			if (mb_strtolower( $_REQUEST['wangguardquestansw'] ) == mb_strtolower( $qrs->Answer ) ) {
				$wpdb->query( $wpdb->prepare("update $table_name set RepliedOK = RepliedOK + 1 where id = %d" , $questionID ) );
			}
			else {
				$answerOK = false;
				$wpdb->query( $wpdb->prepare("update $table_name set RepliedWRONG = RepliedWRONG + 1 where id = %d" , $questionID ) );
			}
		}
		else {
			$answerOK = false;
			$wpdb->query( $wpdb->prepare("update $table_name set RepliedWRONG = RepliedWRONG + 1 where id = %d" , $questionID ) );
		}
	}

	return $answerOK;
}

/********************************************************************/
/*** ADD & VALIDATE SECURITY QUESTIONS ON REGISTER ENDS ***/
/********************************************************************/








/********************************************************************/
/*** USER REGISTATION & DELETE FILTERS BEGINS ***/
/********************************************************************/
// user register and delete actions
add_action('user_register','wangguard_plugin_user_register');
add_action('bp_complete_signup','wangguard_plugin_bp_complete_signup');
add_action('bp_core_activated_user','wangguard_bp_core_activated_user' , 10 , 3);
add_action('wpmu_activate_user','wangguard_wpmu_activate_user' , 10 , 3);

add_action('delete_user','wangguard_plugin_user_delete');
add_action('wpmu_delete_user','wangguard_plugin_user_delete');
add_action('make_spam_user','wangguard_make_spam_user');
add_action('bp_core_action_set_spammer_status','wangguard_bp_core_action_set_spammer_status' , 10 , 2);


//Save the status of the verification upon BP signsups
function wangguard_plugin_bp_complete_signup() {
	global $wpdb;
	global $wangguard_user_check_status;
	
	$table_name = $wpdb->prefix . "wangguardsignupsstatus";

	//delete just in case a previous record from a user which didn't activate the account is there
	$wpdb->query( $wpdb->prepare("delete from $table_name where signup_username = '%s'" , $_POST['signup_username']));

	//Insert the new signup record
	$wpdb->query( $wpdb->prepare("insert into $table_name(signup_username , user_status , user_ip) values ('%s' , '%s' , '%s')" , $_POST['signup_username'] , $wangguard_user_check_status , $_SERVER['REMOTE_ADDR'] ) );
}


//Account activated on BP
function wangguard_bp_core_activated_user($userid, $key, $user) {
	global $wpdb;
	global $wangguard_api_key;
	global $wangguard_user_check_status;

	wangguard_plugin_user_register($userid);
}

//Account activated on WPMU
function wangguard_wpmu_activate_user($userid, $password, $meta) {
	global $wpdb;
	global $wangguard_api_key;
	global $wangguard_user_check_status;

	wangguard_plugin_user_register($userid);
}

//Save the status of the verification against WangGuard service upon user registration
function wangguard_plugin_user_register($userid) {
	global $wpdb;
	global $wangguard_user_check_status;


	if (empty ($wangguard_user_check_status)) {
		$user = new WP_User($userid);
		$table_name = $wpdb->prefix . "wangguardsignupsstatus";

		//if there a status on the signups table?
		$user_status = $wpdb->get_var( $wpdb->prepare("select user_status from $table_name where signup_username = '%s'" , $user->user_login));

		//delete the signup status
		$wpdb->query( $wpdb->prepare("delete from $table_name where signup_username = '%s'" , $user->user_login));

		//If not empty, overrides the status with the signup status
		if (!empty ($user_status))
			$wangguard_user_check_status = $user_status;
	}


	$table_name = $wpdb->prefix . "wangguarduserstatus";
	$wpdb->query( $wpdb->prepare("insert into $table_name(ID , user_status , user_ip) values (%d , '%s' , '%s')" , $userid , $wangguard_user_check_status , $_SERVER['REMOTE_ADDR'] ) );
}


//Delete the status of a user from the WangGuard status tracking table
function wangguard_plugin_user_delete($userid) {
	global $wpdb;

	$user = new WP_User($userid);
	$table_name = $wpdb->prefix . "wangguardsignupsstatus";
	//delete the signup status, just in case it's there
	$wpdb->query( $wpdb->prepare("delete from $table_name where signup_username = '%s'" , $user->user_login));
	

	$table_name = $wpdb->prefix . "wangguarduserstatus";
	$wpdb->query( $wpdb->prepare("delete from $table_name where ID = %d" , $userid ) );
}


//User has been reported as spam, send to WangGuard
function wangguard_make_spam_user($userid) {
	global $wpdb;

	//flag a user
	//get the recordset of the user to flag
	$wpusersRs = $wpdb->get_col( $wpdb->prepare("select ID from $wpdb->users where ID = %d" , $userid ) );

	wangguard_report_users($wpusersRs , "email" , false);
}

function wangguard_bp_core_action_set_spammer_status($userid , $is_spam) {
	if ($is_spam)
		wangguard_make_spam_user ($userid);
}
/********************************************************************/
/*** USER REGISTATION & DELETE FILTERS ENDS ***/
/********************************************************************/









/********************************************************************/
/*** AJAX HANDLERS BEGINS ***/
/********************************************************************/
add_action('admin_head', 'wangguard_ajax_setup');
add_action('wp_ajax_wangguard_ajax_handler', 'wangguard_ajax_callback');
add_action('wp_ajax_wangguard_ajax_recheck', 'wangguard_ajax_recheck_callback');
add_action('wp_ajax_wangguard_ajax_questionadd', 'wangguard_ajax_questionadd');
add_action('wp_ajax_wangguard_ajax_questiondelete', 'wangguard_ajax_questiondelete');

function wangguard_ajax_setup() {

	if (!current_user_can('level_10')) return;


?>
<script type="text/javascript" >
var wangguardBulkOpError = false;
jQuery(document).ready(function($) {
	jQuery("a.wangguard-splogger").click(function() {
		var userid = jQuery(this).attr("rel");
		wangguard_report(userid , false);
	});

	function wangguard_report(userid , frombulk) {
		var confirmed = true;
		<?php if (get_option ("wangguard-expertmode")!='1') {?>
			if (!frombulk)
				confirmed = confirm('<?php echo addslashes(__('Do you confirm to flag this user as Splogger? This operation is IRREVERSIBLE and will DELETE the user.', 'wangguard'))?>');
		<?php }?>

		if (confirmed) {
			data = {
				action	: 'wangguard_ajax_handler',
				scope	: 'email',
				userid	: userid
			};
			jQuery.post(ajaxurl, data, function(response) {
				if (response=='0') {
					alert('<?php echo addslashes(__('The selected user couldn\'t be found on the users table.', 'wangguard'))?>');
				}
				else if (response=='-1') {
					wangguardBulkOpError = true;
					alert('<?php echo addslashes(__('Your WangGuard API KEY is invalid.', 'wangguard'))?>');
				}
				else if (response=='-2') {
					wangguardBulkOpError = true;
					alert('<?php echo addslashes(__('There was a problem connecting to the WangGuard server. Please check your server configuration.', 'wangguard'))?>');
				}
				else {
					jQuery('td span.wangguardstatus-'+response).parent().parent().fadeOut();
				}
			});
		}
	}




	jQuery("a.wangguard-domain").click(function() {

		var confirmed = true;
		<?php if (get_option ("wangguard-expertmode")!='1') {?>
			confirmed = confirm('<?php echo addslashes(__('Do you confirm to flag this user domain as Splogger? This operation is IRREVERSIBLE and will DELETE the users that shares this domain.', 'wangguard'))?>');
		<?php }?>

		if (confirmed) {
			data = {
				action	: 'wangguard_ajax_handler',
				scope	: 'domain',
				userid	: jQuery(this).attr("rel")
			};
			jQuery.post(ajaxurl, data, function(response) {
				if (response=='0') {
					alert('<?php echo addslashes(__('The selected user couldn\'t be found on the users table.', 'wangguard'))?>');
				}
				else if (response=='-1') {
					alert('<?php echo addslashes(__('Your WangGuard API KEY is invalid.', 'wangguard'))?>');
				}
				else if (response=='-2') {
					alert('<?php echo addslashes(__('There was a problem connecting to the WangGuard server. Please check your server configuration.', 'wangguard'))?>');
				}
				else {
					var users = response.split(",");
					for (i=0;i<=users.length;i++)
						jQuery('td span.wangguardstatus-'+users[i]).parent().parent().fadeOut();
				}
			});
		}
	});

	<?
	global $wuangguard_parent;
	if (($wuangguard_parent == 'ms-users.php') || ($wuangguard_parent == 'wpmu-users.php') || ($wuangguard_parent == 'users.php')) {?>
	jQuery(document).ajaxError(function(e, xhr, settings, exception) {
		alert('<?php echo addslashes(__('There was a problem connecting to your WordPress server.', 'wangguard'))?>');
	});
	<?}?>


	jQuery("a.wangguard-recheck").click(function() {
		var userid = jQuery(this).attr("rel");
		wangguard_recheck(userid);
	});

	function wangguard_recheck(userid) {
		data = {
			action	: 'wangguard_ajax_recheck',
			userid	: userid
		};

		jQuery.post(ajaxurl, data, function(response) {
			if (response=='0') {
				alert('<?php echo addslashes(__('The selected user couldn\'t be found on the users table.', 'wangguard'))?>');
			}
			else if (response=='-1') {
				wangguardBulkOpError = true;
				alert('<?php echo addslashes(__('Your WangGuard API KEY is invalid.', 'wangguard'))?>');
			}
			else if (response=='-2') {
				wangguardBulkOpError = true;
				alert('<?php echo addslashes(__('There was a problem connecting to the WangGuard server. Please check your server configuration.', 'wangguard'))?>');
			}
			else {
				jQuery('td span.wangguardstatus-'+userid).fadeOut(500, function() {
					jQuery(this).html(response);
					jQuery(this).fadeIn(500);
				})
			}
		});
	}


	jQuery("a.wangguard-delete-question").live('click' , function() {

		<?php if (get_option ("wangguard-expertmode")=='1') {?>
			var confirmed = true;
		<?php }
		else {?>
			var confirmed = confirm('<?php echo addslashes(__('Do you confirm to delete this question?.', 'wangguard'))?>');
		<?php }?>

		if (confirmed) {
			var questid	= jQuery(this).attr("rel");
			data = {
				action	: 'wangguard_ajax_questiondelete',
				questid	: questid
			};
			jQuery.post(ajaxurl, data, function(response) {
				if (response!='0') {
					jQuery("#wangguard-question-"+questid).slideUp("fast");
				}
			});
		}
	});


	
	jQuery("#wangguardnewquestionbutton").click(function() {
		jQuery("#wangguardnewquestionerror").hide();

		var wgq = jQuery("#wangguardnewquestion").val();
		var wga = jQuery("#wangguardnewquestionanswer").val();
		if ((wgq=='') || wga=='') {
			jQuery("#wangguardnewquestionerror").slideDown();
			return;
		}
		
		data = {
			action	: 'wangguard_ajax_questionadd',
			q		: wgq,
			a		: wga
		};
		jQuery.post(ajaxurl, data, function(response) {
			if (response!='0') {
				var newquest = '<div class="wangguard-question" id="wangguard-question-'+response+'">';
				newquest += '<?php echo addslashes(__("Question", 'wangguard'))?>: <strong>'+wgq+'</strong><br/>';
				newquest += '<?php echo addslashes(__("Answer", 'wangguard'))?>: <strong>'+wga+'</strong><br/>';
				newquest += '<a href="javascript:void(0)" rel="'+response+'" class="wangguard-delete-question"><?php echo addslashes(__('delete question', 'wangguard'))?></a></div>';
				
				jQuery("#wangguard-new-question-container").append(newquest);

				jQuery("#wangguardnewquestion").val("");
				jQuery("#wangguardnewquestionanswer").val("");
			}
			else if (response=='0') {
				jQuery("#wangguardnewquestionerror").slideDown();
			}
		});
	});



	<?php
	global $wuangguard_parent;
	if (($wuangguard_parent == 'ms-users.php') || ($wuangguard_parent == 'wpmu-users.php') || ($wuangguard_parent == 'users.php')) {?>
		var wangguard_bulk = '';
		wangguard_bulk += '<input style="margin-right:15px" type="button" class="button-secondary action wangguardbulkcheckbutton" name="wangguardbulkcheckbutton" value="<?php echo addslashes(__('Bulk check Sploggers' , 'wangguard')) ?>">';
		wangguard_bulk += '<input type="button" class="button-secondary action wangguardbulkreportbutton" name="wangguardbulkreportbutton" value="<?php echo addslashes(__('Bulk report Sploggers' , 'wangguard')) ?>">';
		jQuery("div.tablenav div.alignleft:first").append(wangguard_bulk);
		jQuery("div.tablenav div.alignleft:last").append(wangguard_bulk);

		jQuery('input.wangguardbulkcheckbutton').live('click' , function () {
			var userscheck;
			userscheck = jQuery('input[name="users[]"]:checked');

			//Checkboxes name varies thru WP screens (users.php / ms-users.php / wpmu-users.php) and versions
			if (userscheck.length == 0)
				userscheck = jQuery('input[name="allusers[]"]:checked');

			//Checkboxes name varies thru WP screens (users.php / ms-users.php / wpmu-users.php) and versions
			if (userscheck.length == 0)
				userscheck = jQuery('th.check-column input[type="checkbox"]:checked');

			wangguardBulkOpError = false;

			userscheck.each(function() {

					if (wangguardBulkOpError) {
						return;
					}

					wangguard_recheck(jQuery(this).val());
			});

		});

		jQuery('input.wangguardbulkreportbutton').live('click' , function () {

			if (!confirm('<?php _e('Do you confirm to flag the selected users as Sploggers? This operation is IRREVERSIBLE and will DELETE the users.' , 'wangguard')?>'))
				return;

			var userscheck;
			userscheck = jQuery('input[name="users[]"]:checked');

			//Checkboxes name varies thru WP screens (users.php / ms-users.php / wpmu-users.php) and versions
			if (userscheck.length == 0)
				userscheck = jQuery('input[name="allusers[]"]:checked');

			//Checkboxes name varies thru WP screens (users.php / ms-users.php / wpmu-users.php) and versions
			if (userscheck.length == 0)
				userscheck = jQuery('th.check-column input[type="checkbox"]:checked');


			wangguardBulkOpError = false;

			userscheck.each(function() {

					if (wangguardBulkOpError) {
						return;
					}

					wangguard_report(jQuery(this).val() , true);
			});

			//document.location = document.location;
		});
	<?php }?>
});
</script>
<?php
}



function wangguard_ajax_callback() {
	global $wpdb;

	if (!current_user_can('level_10')) die();
	
	$userid = intval($_POST['userid']);
	$scope = $_POST['scope'];


	if ($scope == 'domain') {
		//flag domain
		$userDomain = new WP_User($userid);
		$domain = wangguard_extract_domain($userDomain->user_email);
		$domain = '%@' . str_replace(array("%" , "_"), array("\\%" , "\\_"), $domain);

		//get the recordset of the users to flag
		$wpusersRs = $wpdb->get_col( $wpdb->prepare("select ID from $wpdb->users where user_email LIKE '%s'" , $domain ) );
	}
	else {
		//flag a user
		//get the recordset of the user to flag
		$wpusersRs = $wpdb->get_col( $wpdb->prepare("select ID from $wpdb->users where ID = %d" , $userid ) );
	}

	echo wangguard_report_users($wpusersRs , $scope);
	die();
}

function wangguard_ajax_questionadd() {
	global $wpdb;

	if (!current_user_can('level_10')) die();

	$q = trim($_POST['q']);
	$a = trim($_POST['a']);


	if (get_magic_quotes_gpc()) {
		$q = stripslashes($q);
		$a = stripslashes($a);
	}

	if (empty ($q) || empty ($a)) {
		echo "0";
		die();
	}

	$table_name = $wpdb->prefix . "wangguardquestions";
	$wpdb->insert( $table_name , array( 'Question'=>$q  , "Answer"=>$a) , array('%s','%s') );

	echo $wpdb->insert_id;
	die();
}

function wangguard_ajax_questiondelete() {
	global $wpdb;

	if (!current_user_can('level_10')) die();

	$questid = intval($_POST['questid']);

	$table_name = $wpdb->prefix . "wangguardquestions";
	$wpdb->query( $wpdb->prepare("delete from $table_name where id = %d" , $questid) );

	echo $questid;
	die();
}

function wangguard_ajax_recheck_callback() {
	global $wpdb;
	global $wangguard_api_key;

	if (!current_user_can('level_10')) die();

	$userid = intval($_POST['userid']);

	$valid = wangguard_verify_key($wangguard_api_key);
	if ($valid == 'failed') {
		echo "-2";
		die();
	} 
	else if ($valid == 'invalid') {
		echo "-1";
		die();
	}

	$user_object = new WP_User($userid);
	if (empty ($user_object->user_email)) {
		echo "0";
		die();
	}


	if ( wangguard_is_admin($user_object) ) {
		echo '<span class="wangguard-status-no-status wangguardstatus-'.$userid.'">'. __('No status', 'wangguard') .'</span>';
		die();
	}

	$user_check_status = "not-checked";

	wangguard_stats_update("check");

	//Get the user's client IP from which he signed up
	$table_name = $wpdb->prefix . "wangguarduserstatus";
	$clientIP = $wpdb->get_var( $wpdb->prepare("select user_ip from $table_name where ID = %d" , $user_object->ID) );

	//Rechecks the user agains WangGuard service
	$response = wangguard_http_post("wg=<in><apikey>$wangguard_api_key</apikey><email>".$user_object->user_email."</email><ip>".$clientIP."</ip></in>", 'query-email.php');
	$responseArr = XML_unserialize($response);
	if ( is_array($responseArr)) {
		if (($responseArr['out']['cod'] == '10') || ($responseArr['out']['cod'] == '11')) {
			echo '<span class="wangguard-status-splogguer">'. __('Reported as Splogger', 'wangguard') .'</span>';
			$user_check_status = 'reported';
			wangguard_stats_update("detected");
		}
		else {
			if ($responseArr['out']['cod'] == '20') {
				$user_check_status = 'checked';
				echo '<span class="wangguard-status-checked">'. __('Checked', 'wangguard') .'</span>';
			}
			else {
				echo '<span class="wangguard-status-error">'. __('Error', 'wangguard') . " - " . $responseArr['out']['cod'] . '</span>';
				$user_check_status = 'error:'.$responseArr['out']['cod'];
			}
		}
	}
	else
		return '<span class="wangguard-status-not-checked">'. __('Not checked', 'wangguard') .'</span>';


	$table_name = $wpdb->prefix . "wangguarduserstatus";
	$tmpIP = $wpdb->get_var( $wpdb->prepare("select user_ip from $table_name where ID = %d" , $user_object->ID) );

	//There may be cases where OUR record for the user isn't there (DB migrations for example or manual inserts) so we just delete and re-insert the user
	$wpdb->query( $wpdb->prepare("delete from $table_name where ID = %d" , $user_object->ID ) );
	$wpdb->query( $wpdb->prepare("insert into $table_name(ID , user_status , user_ip) values (%d , '%s' , '%s')" , $user_object->ID , $user_check_status , $tmpIP ) );

	die();
}
/********************************************************************/
/*** AJAX HANDLERS ENDS ***/
/********************************************************************/
?>