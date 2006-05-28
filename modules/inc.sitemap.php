<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class SwisdkSitemap {
		public static function page($url, $site = 'default')
		{
			$sitemap = SwisdkSitemap::sitemap();
			$ref =& $sitemap[$site];
			if(array_keys($ref)==array(''))
				$ref =& reset($ref);
			$tokens = explode('/', substr($url, 1));
			foreach($tokens as $t) {
				if(!$t)
					continue;
				if(isset($ref['pages'][$t]))
					$ref =& $ref['pages'][$t];
				else
					return false;
			}

			return $ref;
		}

		public static function sitemap()
		{
			static $sitemap = null;
			if($sitemap === null) {
				$xmlfile = WEBAPP_ROOT.'sitemap.xml';
				$xslfile = SWISDK_ROOT.'lib/sitemap-php.xsl';
				$phpfile = CACHE_ROOT.'sitemap.php';

				global $_swisdk2_sitemap;

				$regenerate = false;
				if(file_exists($phpfile)) {
					$xmls = stat($xmlfile);
					$xsls = stat($xslfile);
					$phps = stat($phpfile);
					if(max($xmls['mtime'],$xsls['mtime'])>$phps['mtime'])
						$regenerate = true;
				} else
					$regenerate = true;

				if($regenerate) {
					$prc = new XSLTProcessor();
					$xsl = new DOMDocument();
					$xml = new DOMDocument();
					$xml->load($xmlfile);
					$xsl->load($xslfile);
					$prc->importStyleSheet($xsl);
					file_put_contents($phpfile, $prc->transformToXML($xml));
					require_once $phpfile;
				}

				require_once $phpfile;

				if(!isset($_swisdk2_sitemap['processed'])) {
					foreach($_swisdk2_sitemap as &$site) {
						foreach($site as $language => &$page) {
							SwisdkSitemap::loop_pages($page, '/'.($language?$language.'/':''));
						}
					}
					$_swisdk2_sitemap['processed'] = true;
					file_put_contents($phpfile,
						'<?php $_swisdk2_sitemap='.var_export($_swisdk2_sitemap, true).'?>');
				}
				$sitemap = $_swisdk2_sitemap;
			}
			
			return $sitemap;
		}

		protected static function loop_pages(&$pages, $prefix)
		{
			foreach($pages['pages'] as $id => &$page) {
				if(!isset($page['url']))
					$page['url'] = $prefix.$id;
				if(!isset($page['title']))
					$page['title'] = ucwords(preg_replace('/[ _]+/', ' ', $id));
				if(isset($page['pages']))
					SwisdkSitemap::loop_pages($page, $prefix.$id.'/');
				$page['parent_title'] = $pages['title'];
				$page['parent_url'] = $pages['url'];
			}
		}
	}

?>