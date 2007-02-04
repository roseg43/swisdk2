<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT . 'inc.data.php';

	define('SWISDK2_VISITOR', 1);

	DBObject::n_to_m('User', 'UserGroup');

	class SessionHandler {
		protected $user = null;

		protected function __construct()
		{
			if(!session_id()) {
				session_start();
			}

			if(isset($_REQUEST['login_username'])
					&& isset($_REQUEST['login_password'])
					&& !isset($_SESSION['swisdk2']['user_id'])) {
				$user = DBObject::find('User', array(
					'user_login=' => $_REQUEST['login_username'],
					'user_password=' => md5($_REQUEST['login_password'])));
				if($user) {
					$_SESSION['swisdk2']['user_id'] = $user->id;
					$this->user = $user;
				}
			}

			if(isset($_REQUEST['logout'])) {
				if(isset($_COOKIE[session_name()]))
					setcookie(session_name(), '', time()-42000, '/');
				$_SESSION = array();
				session_destroy();
				redirect('/');
			}

			if(isset($_SESSION['swisdk2']['user_id']) && !$this->user)
				$this->user = DBObject::find('User',
					$_SESSION['swisdk2']['user_id']);

			if(!$this->user) {
				$this->user = DBObject::find('User', SWISDK2_VISITOR);
			} else if(Swisdk::config_value('core.user_meta')) {
				$time = time();
				DBObject::db_query('INSERT INTO tbl_user_meta'
					.'(user_meta_user_id,user_meta_key,'
						.'user_meta_value,user_meta_creation_dttm)'
					.' VALUES ('.$this->user->id().',\'activity\','.$time.','.$time.')'
					.' ON DUPLICATE KEY UPDATE user_meta_value='.$time);
			}
		}

		public static function &instance()
		{
			static $instance = null;
			if( $instance === null ) {
				$instance = new SessionHandler();
			}

			return $instance;
		}

		public static function authenticated()
		{
			return SessionHandler::instance()->user->id()!=SWISDK2_VISITOR;
		}

		public static function &user()
		{
			return SessionHandler::instance()->user;
		}
	}

?>
