<?php

/**
 *
 */
class Limit_Logins 
{

	static $user = false;
	static $manager;

	static $session_limit;
	static $time_limit;
	static $logout_oldest;
	static $error_msg;
	static $error_code;

	static $session_count;

	/**
	 */
	static function Initialize ()
	{
		add_action('init', function (){
			if (!is_user_logged_in()) return false;
			Limit_Logins::setUser(get_current_user_id());
		});

		add_filter('attach_session_information', function ($session){
			$session['last_activity'] = time();
			return $session;
		});

		add_action('template_redirect', function (){
			// if the user isn't logged or the auth cookie is not valid
			if (!is_user_logged_in() || !$cookie_element = wp_parse_auth_cookie($_COOKIE[LOGGED_IN_COOKIE]))
				return ;
			
			// get the current session
			$_session = Limit_Logins::$manager->get($cookie_element['token']);

			if ($_session['expiration'] <= time() // only update if session is not expired
			|| ($_session['last_activity']+(5* MINUTE_IN_SECONDS)) > time()) // only update in every 5 min to reduce db load
				return ;

			$_session['last_activity'] = time();
			Limit_Logins::$manager->update($cookie_element['token'], $_session);
		});
	}

	/**
	 */
	static function SetUpVars ($session_limit, $time_limit, $logout_oldest=false, $error_msg='', $error_code='max_session_reached')
	{
		self::$session_limit = $session_limit;
		self::$time_limit    = $time_limit * HOUR_IN_SECONDS;
		self::$logout_oldest = $logout_oldest;
		self::$error_msg     = $error_msg;
		self::$error_code    = $error_code;


	}

	/**
	 */
	static function Execute ($user)
	{
		self::setUser($user);
		$oldest_session = self::getOldestSession();

		if (self::$session_count < self::$session_limit)
			return $user;

		// If the number of active sessions is greater than the sessions limit 
		// and has no valid oldest session or the oldest activity is in the defined time interval
		if ((self::$session_count >= self::$session_limit)
		 && (!$oldest_session || $oldest_session['last_activity']+self::$time_limit > time())):
			//if (!self::$logout_oldest)
				return new WP_Error(self::$error_code, self::$error_msg);
		endif;

		//
		self::destroySession(self::getVerifier($oldest_session));
		return $user;
	}

	/**
	 * return the number of active sessions for the current user
	 */
	static function getSessions ()
	{
		if (!self::$user)
			return false;
		return self::$manager->get_all();
	}

	/**
	 * Return the session key based on the session list of the current user
	 */
	static function getVerifier ($session)
	{
		if (!$sessions = get_user_meta(self::$user->ID, 'session_tokens', true))
			return false;

		$session_string = implode(',', $session);
		foreach($sessions as $verifier => $sess):
			if($session_string == implode(',', $sess))
				return $verifier;
		endforeach;

		return false;
	}

	/**
	 * Return the oldest active session of the current user
	 */
	static function getOldestSession ()
	{
		$_session = false;
		foreach (self::getSessions() as $session):
			if (!isset($session['last_activity'])) continue;
			if (!$_session){
				$_session = $session;
				continue;
			}
			if($_session['last_activity'] > $session['last_activity'])
				$_session = $session;
		endforeach;
		return $_session;
	}

	/**
	 * Destroys a session based on its verifier key
	 */
	static function destroySession ($verifier)
	{
		$sessions = get_user_meta(self::$user->ID, 'session_tokens', true);
		if(!isset($sessions[$verifier]))
			return true;

		unset($sessions[$verifier]);

		if(empty($sessions))
			delete_user_meta(self::$user->ID, 'session_tokens');
		else
			update_user_meta(self::$user->ID, 'session_tokens', $sessions);

		return true;
	}

	/**
	 * Set the current user based in its ID or Obj
	 */
	static function setUser ($user)
	{
		self::$user = is_int($user)? get_user_by('id', $user) : $user;
		self::$manager = WP_Session_Tokens::get_instance(self::$user->ID);
		self::$session_count = count(self::getSessions());
	}

}
