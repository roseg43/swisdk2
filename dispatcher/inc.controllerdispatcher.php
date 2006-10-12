<?php
	/**
	*	Copyright (c) 2006, Moritz Zumb�hl <mail@momoetomo.ch>,
	*		Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	/**
	 * tries to find a file below CONTENT_ROOT which would be suitable to handle
	 * the incoming request
	 *
	 * Rules:
	 *
	 * Incoming: /request/path/
	 *
	 * Checks for (always prepend CONTENT_ROOT):
	 * 
	 * 1. request/path/Index_*
	 * 2. request/path/All_*
	 * 3. request/path_*
	 * 4. request/All_*
	 * 5. request_*
	 * 6. All_*
	 */
	class ControllerDispatcher extends ControllerDispatcherModule {
		public function collect_informations()
		{
			$tokens = explode('/', trim($this->input(), '/').'/');
			$t = $tokens;
			$matches = array();

			$path = CONTENT_ROOT . implode('/', $tokens);

			// try to find an Index_* controller/template only
			// for the full REQUEST_URI path
			if(!($matches = glob($path.'Index_*'))) {
				while(true) {
					if(($matches=glob($path.'All_*')) ||
							($matches=glob($path.'_*'))) {
						if(is_file($matches[0]))
							break;
					}

					if(!count($tokens))
						return false;

					array_pop($tokens);
					$path = CONTENT_ROOT.implode('/', $tokens);
				}
			}

			Swisdk::set_config_value('runtime.controller.url',
				preg_replace('/[\/]+/', '/', '/'.implode('/',$tokens).'/'));
			Swisdk::set_config_value('runtime.includefile', $matches[0] );
			Swisdk::set_config_value('runtime.arguments', array_slice(
				$t, count($tokens)));

			return true;
		}
	}

?>
