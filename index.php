<?php
/*
 *	Plugin Name: WP Limit Logins
 *	Plugin URI: https://github.com/EAzv/wp-limit-logins
 *	Description: Limits the number of concurrent logins per user 
 *	Version: 0.1
 *	Author: Eduardo Azevedo
 *	Author URI: http://eazv.net/
 */

defined('ABSPATH') or die('Never here');

require_once "wp_limit_logins.php";


//
Limit_Logins::Initialize();


add_filter('init', function (){
	// setup vars
	Limit_Logins::SetUpVars(
		get_option('wp_limit_logins_number'),
		get_option('wp_limit_logins_hours'),
		false,
		get_option('wp_limit_logins_message'),
		'max_session_reached'
	);
});

//
add_filter('authenticate', function ($user, $username, $password){
	return	Limit_Logins::Execute($user, $username, $password);
}, 30, 3);


/**
 * Add the plugin link in settings on the side bar
 * and setup the setup form
 */
add_action('admin_menu', function (){
	add_options_page(
		'WP Limit Logins',
		'Limit Logins',
		'manage_options',
		'wp-limit-logins',
		function () { //
			require "settings.phtml";
		}
	);
});

// DB fields
add_action('admin_init', function (){
	register_setting('wp_limit_logins_options', 'wp_limit_logins_number');
	register_setting('wp_limit_logins_options', 'wp_limit_logins_hours');
	register_setting('wp_limit_logins_options', 'wp_limit_logins_message');
});

// notification messages
add_action('admin_notices', function (){
	if (isset($_GET['settings-updated'])) :
		unset($_GET['settings-updated']);
		?> <div class="notice notice-success is-dismissible"> <p> Saved settings </p> </div> <?php
	endif;
});



