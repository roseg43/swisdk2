<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch> and
	*			    Moritz Zumbühl <mail@momoetomo.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	/**
	* redirects the client browser to the new location
	*/
	function redirect($url)
	{
		if(strpos($url, "\n")===false) {
			session_write_close();
			header('Location: '.$url);
		} else
			SwisdkError::handle(new FatalError(
				'Invalid location specification: '.$url));
	}

	/**
	* Returns the GET or POST value given by $parameter (if both exists the post
	* values is returned)
	*/
	function getInput($var, $default=null, $cleanInput = true)
	{
		$value = $default;
		if(isset($_POST[$var]))
			$value = $_POST[$var];
		else if(isset($_GET[$var]))
			$value = $_GET[$var];
		if($value && $cleanInput && !is_numeric($value)) {
			if(is_array($value))
				array_walk_recursive($value, 'cleanInputRef');
			else
				cleanInputRef($value);
		}
		return $value;
	}

	/**
	 * Clean HTML, hopefully disabling XSS attack vectors
	 */
	function cleanInput($value)
	{
		require_once SWISDK_ROOT.'lib/contrib/externalinput.php';
		return popoon_classes_externalinput::basicClean($value);
	}

	function cleanInputRef(&$var)
	{
		$var = cleanInput($var);
	}

	/**
	* Generates a string of random numbers and characters. 
	*/
	function randomKeys($length)
  	{
		$pattern = '1234567890abcdefghijklmnopqrstuvwxyz';
		for($i=0; $i<$length; $i++)
		     $key .= $pattern{rand(0,35)};
		return $key;
	}

	/**
	 * generates a unique ID which may be used to guard against CSRF attacks
	 *
	 * http://en.wikipedia.org/wiki/Cross-site_request_forgery
	 */
	function guardToken($token = null)
	{
		return sha1(session_id().Swisdk::config_value('core.token').$token);
	}

?>
