<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>,
	*		Moritz Zumbhl <mail@momoetomo.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT.'inc.component.php';
	require_once MODULE_ROOT.'inc.permission.php';
	require_once MODULE_ROOT.'inc.smarty.php';
	require_once SWISDK_ROOT.'site/inc.site.php';
	require_once MODULE_ROOT.'inc.form.php';
	require_once MODULE_ROOT.'inc.tableview.php';
	require_once MODULE_ROOT.'inc.dbtableview.php';
	require_once MODULE_ROOT.'inc.builder.php';

	class AdminModule extends Site {
		protected $dbo_class;
		protected $arguments;
		protected $multilanguage = false;

		protected function component_dispatch()
		{
			$args = Swisdk::config_value('runtime.arguments');
			$cmp = null;

			if(isset($args[0]) && $args[0]) {
				$cmp_class = 'AdminComponent_'.$this->dbo_class.$args[0];
				if(class_exists($cmp_class))
					$cmp = new $cmp_class;
				else if(class_exists($cmp_class = 'AdminComponent'
						.$args[0])) 
					$cmp = new $cmp_class;
				array_shift($args);
			}
			
			$this->arguments = $args;

			if(!$cmp)
				$cmp = new AdminComponent_index();

			$cmp->set_module($this);

			return $cmp;
		}

		public function run()
		{
			PermissionManager::check_throw();
			$cmp = $this->component_dispatch();
			$cmp->run();

			Swisdk::set_config_value('runtime.navigation.url',
				Swisdk::config_value('runtime.controller.url'));

			$sm = SmartyMaster::instance();
			$sm->add_html_handler('content', $cmp);
			$sm->display();
		}

		public function dbo_class()
		{
			return $this->dbo_class;
		}

		public function component_arguments()
		{
			return $this->arguments;
		}

		public function multilanguage()
		{
			return $this->multilanguage;
		}
	}

	class AdminComponent implements IHtmlComponent {
		protected $module_url;
		protected $dbo_class;
		protected $args;
		protected $html;
		protected $multilanguage;

		public function run()
		{
		}

		public function html()
		{
			return $this->html;
		}

		public function set_module(&$module)
		{
			$this->module_url = $module->url();
			$this->dbo_class = $module->dbo_class();
			$this->args = $module->component_arguments();
			$this->multilanguage = $module->multilanguage();
		}

		/**
		 * goto - i can't get started without you!
		 */
		public function goto($tok)
		{
			redirect($this->module_url.$tok);
		}


		public function form_builder()
		{
			$cmp_class = 'FormBuilder_'.$this->dbo_class;
			if(class_exists($cmp_class))
				return new $cmp_class();
			return new FormBuilder();
		}

		public function tableview_builder()
		{
			$cmp_class = 'TableViewBuilder_'.$this->dbo_class;
			if(class_exists($cmp_class))
				return new $cmp_class();
			return new TableViewBuilder();
		}
	}

	class AdminComponent_index extends AdminComponent {
		public function run()
		{
			$this->goto('_list');
		}
	}

	class AdminComponent_new extends AdminComponent {
		public function run()
		{
			$form = null;
			if($this->multilanguage) {
				$obj = DBObjectML::create($this->dbo_class);
				$obj->set_language(Swisdk::language());
				$form = new FormML($obj);
			} else
				$form = new Form(DBObject::create($this->dbo_class));
			$this->form_builder()->build($form);
			$form->set_title('New '.$this->dbo_class);
			if($form->is_valid()) {
				$form->dbobj()->store();
				$this->goto('_index');
			} else
				$this->html = $form->html();
		}
	}

	class AdminComponent_edit extends AdminComponent {
		public function run()
		{
			if($this->args[0]=='multiple')
				$this->edit_multiple();
			else
				$this->edit_single();
		}

		protected function edit_multiple()
		{
			$dbo = null;

			if($this->multilanguage)
				$dbo = DBObjectML::create($this->dbo_class);
			else
				$dbo = DBObject::create($this->dbo_class);
			$p = $dbo->primary();

			$dboc = DBOContainer::find($dbo, array(
				$p.' IN {list}' => array('list' => getInput($p))));

			$builder = $this->form_builder();
			$form = new Form();
			$form->enable_unique();
			foreach($dboc as $dbo) {
				$box = $form->box($dbo->id());
				$box->bind($dbo);
				$box->add(new HiddenInput($p.'[]'))->set_value($dbo->id());
				$builder->build($box);
			}

			if($form->is_valid()) {
				$dboc->store();
				$this->goto('_index');
			} else
				$this->html = $form->html();
				return;
		}

		protected function edit_single()
		{
			$dbo = null;
			if($this->multilanguage)
				$dbo = DBObjectML::find($this->dbo_class, $this->args[0]);
			else
				$dbo = DBObject::find($this->dbo_class, $this->args[0]);
			if(!$dbo)
				SwisdkError::handle(new FatalError(
					"AdminComponent_edit::run() - Can't find the data."
					." Class is: {$this->dbo_class} Argument is: "
					."{$this->args[0]}"));

			$form = null;
			if($this->multilanguage)
				$form = new FormML($dbo);
			else
				$form = new Form($dbo);
			$this->form_builder()->build($form);
			$form->set_title('Edit '.$this->dbo_class);
			if($form->is_valid()) {
				$dbo->store();
				$this->goto('_index');
			} else
				$this->html = $form->html();
		}
	}

	class AdminComponent_list extends AdminComponent {
		protected $tableview;

		public function run()
		{
			if($this->multilanguage)
				$this->tableview = new MultiDBTableView(
					DBObjectML::create($this->dbo_class),
					'DBTableViewForm');
			else
				$this->tableview = new MultiDBTableView(
					$this->dbo_class, 'DBTableViewForm');
			$this->tableview->set_target($this->module_url);
			if(class_exists($c = 'DBTableViewForm_'.$this->dbo_class))
				$this->tableview->set_form($c);
			$this->tableview->init();
			$this->tableview_builder()->build($this->tableview);
			$this->complete_columns();
			$this->html = $this->tableview->html();
		}

		public function complete_columns()
		{
		}
	}

	class AdminComponent_delete extends AdminComponent {
		public function run()
		{
			if($this->args[0]=='multiple')
				$this->delete_multiple();
			else
				$this->delete_single();
		}

		protected function delete_multiple()
		{
			$dbo = null;

			if($this->multilanguage)
				$dbo = DBObjectML::create($this->dbo_class);
			else
				$dbo = DBObject::create($this->dbo_class);
			$p = $dbo->primary();

			$dboc = DBOContainer::find($dbo, array(
				$p.' IN {list}' => array('list' => getInput($p))));

			$dboc->delete();
			$this->goto('_index');
		}

		protected function delete_single()
		{
			$dbo = null;
			if($this->multilanguage)
				$dbo = DBObjectML::find($this->dbo_class, $this->args[0]);
			else
				$dbo = DBObject::find($this->dbo_class, $this->args[0]);
			if(!$dbo)
				SwisdkError::handle(new FatalError(
					"AdminComponent_delete::run() - Can't find the data."
					." Class is: {$this->dbo_class} Argument is: "
					."{$this->args[0]}"));

			$dbo->delete();
			$this->goto('_index');
		}
	}

?>
