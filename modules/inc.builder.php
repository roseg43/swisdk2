<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>,
	*		Moritz Zumbühl <mail@momoetomo.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT.'inc.form.php';
	require_once MODULE_ROOT.'inc.tableview.php';

	/**
	 * Form and TableView builder
	 *
	 * use informations from the database to automatically add FormItems resp.
	 * TableViewColumns
	 *
	 * NOTE! You can customize the Builders here, but it's probably easier to
	 * modify add your modifications in your own AdminModules or AdminComponents
	 *
	 * You can still use the autodetection features of the BuilderBase with
	 * Form::add_auto or TableView::append_auto
	 */

	abstract class BuilderBase {

		/**
		 * this function tries to handle everything that gets thrown at it
		 *
		 * it first searches the relations and then the field list for
		 * matches.
		 */
		public function create_field($field, $title = null)
		{
			$c_field = null;
			$c_type = null;

			$dbobj = $this->dbobj();
			$field_list = $dbobj->field_list();
			if(!isset($field_list[$field])) {
				if(isset($field_list[$tmp=$dbobj->name($field)])) {
					$c_field = $tmp;
					$c_type = $field_list[$c_field];
				} else if($dbobj instanceof DBObjectML) {
					$content_dbobj = $dbobj->content_dbobj();
					$field_list_c = $content_dbobj->field_list();
					if(isset($field_list_c[$tmp=$dbobj->name($field)])) {
						$c_field = $tmp;
						$c_type = $field_list_c[$c_field];
					} else if(isset($field_list_c[
							$tmp=$content_dbobj->name($field)])) {
						$c_field = $tmp;
						$c_type = $field_list_c[$c_field];
					}

					if($title === null)
						$title = $content_dbobj->pretty($c_field);
				}
			} else {
				$c_field = $field;
				$c_type = $field_list[$c_field];
			}

			if($title === null)
				$title = $dbobj->pretty($field);

			// field from main table
			switch($c_type) {
				case DB_FIELD_BOOL:
					return $this->create_bool($c_field, $title);
				case DB_FIELD_STRING:
				case DB_FIELD_INTEGER:
					return $this->create_text($c_field, $title);
				case DB_FIELD_LONGTEXT:
					return $this->create_textarea($c_field, $title);
				case DB_FIELD_DATE:
					return $this->create_date($c_field, $title);
				case DB_FIELD_DTTM:
					return $this->create_dttm($c_field, $title);
				case DB_FIELD_TIME:
					return $this->create_time($c_field, $title);
				case DB_FIELD_FLOAT:
					return $this->create_float($c_field, $title);
				case DB_FIELD_FOREIGN_KEY|(DB_REL_SINGLE<<10):
					$relations = $dbobj->relations();
					if(isset($relations[$c_field]))
						return $this->create_rel_single($c_field, $title,
							$relations[$c_field]['foreign_class']);
					else if($dbobj instanceof DBObjectML) {
						$relations_c = $dbobj->content_dbobj()->relations();
						if(isset($relations_c[$c_field]))
							return $this->create_rel_single($c_field, $title,
								$relations_c[$c_field]['foreign_class']);
					}
			}

			// field from a related table
			$relation = null;
			$relations = $dbobj->relations();
			if(isset($relations[$field]['type'])) {
				$relation = $relations[$field];
			} else if($dbobj instanceof DBObjectML) {
				$relations_c = $dbobj->content_dbobj()->relations();
				if(isset($relations_c[$field]['type']))
					$relation = $relations_c[$field];
			}

			switch($relation['type']) {
				case DB_REL_MANY:
					return $this->create_rel_many($field, $title,
						$relation['foreign_class']);
				case DB_REL_N_TO_M:
					return $this->create_rel_manytomany($field, $title,
						$relation['foreign_class']);
				case DB_REL_3WAY:
					return $this->create_rel_3way($field, $title,
						$relation['foreign_class'],
						$relation['foreign_primary']);
				case DB_REL_TAGS:
					return $this->create_rel_tags($field, $title);
			}
		}

		/**
		 * @return a DBObject instance of the correct class
		 */
		abstract public function dbobj();

		/**
		 * create a FormItem/Column for a relation of type has_a or
		 * belongs_to
		 */
		abstract public function create_rel_single($fname, $title, $class);

		/**
		 * create a FormItem/Column for a relation of type has_many
		 */
		abstract public function create_rel_many($fname, $title, $class);

		/**
		 * create a FormItem/Column for a relation of type n-to-m
		 */
		abstract public function create_rel_manytomany($fname, $title, $class);

		/**
		 * create a FormItem/Column for a relation of type 3way
		 */
		abstract public function create_rel_3way($fname, $title, $class, $field);

		/**
		 * create a FormItem/Column for tags
		 */
		abstract public function create_rel_tags($fname, $title);

		/**
		 * create a date widget
		 */
		abstract public function create_date($fname, $title);

		/**
		 * create a datetime widget
		 */
		abstract public function create_dttm($fname, $title);

		/**
		 * create a time widget
		 */
		abstract public function create_time($fname, $title);

		/**
		 * create a textarea widget (f.e. length-limited for TableView)
		 */
		abstract public function create_textarea($fname, $title);

		/**
		 * checkbox or true/false column
		 */
		abstract public function create_bool($fname, $title);

		/**
		 * float widget
		 */
		abstract public function create_float($fname, $title);

		/**
		 * everything else
		 */
		abstract public function create_text($fname, $title);
	}

	class FormBuilder extends BuilderBase {
		public function build(&$form)
		{
			if($form->dbobj() instanceof DBObjectML)
				return $this->build_ml($form);
			else
				return $this->build_simple($form);
		}

		/**
		 * this is used by FormBox::add_auto
		 */
		public function create_auto(&$form, $field, $title = null)
		{
			$this->form = $form;
			return $this->create_field($field, $title);
		}

		/**
		 * default builder function
		 */
		public function build_simple(&$form, $submitbtn = true)
		{
			$this->form = $form;
			$dbobj = $form->dbobj();
			$fields = array_keys($dbobj->field_list());
			$ninc_regex = '/^'.$dbobj->_prefix()
				.'(id|update_dttm|creation_dttm|creator_id|author_id)$/';
			foreach($fields as $fname)
				if(!preg_match($ninc_regex, $fname))
					$this->create_field($fname);

			$relations = $dbobj->relations();
			foreach($relations as $key => &$data) {
				if($data['type']==DB_REL_N_TO_M
						||$data['type']==DB_REL_3WAY
						||$data['type']==DB_REL_TAGS)
					$this->create_field($key);
			}
		}

		/**
		 * builder function for multilanguage forms
		 */
		public function build_ml(&$form)
		{
			$this->build_simple($form, false);

			$dbobj =& $form->dbobj();
			$box = null;
			$dbobjml = $dbobj->dbobj();
			if($dbobjml instanceof DBOContainer) {
				$languages = Swisdk::all_languages();

				foreach($languages as $lid => &$l) {
					$key = $l['language_key'];
					$box = $form->box('lang_'.$key);
					if(!isset($dbobjml[$lid]))
						$dbobjml[$lid] =
							DBObject::create($dbobj->_class().'Content');
					$dbo =& $dbobjml[$lid];
					$dbo->set_owner($dbobj);
					$dbo->language_id = $lid;
					$box->bind($dbo);
					$box->set_title($key);
					$this->form = $box;

					$fields = array_keys($dbo->field_list());
					$ninc_regex = '/^'.$dbo->_prefix()
						.'(id|creation_dttm|creator_id|author_id|language_id|'
						.$dbobj->primary().')$/';
					foreach($fields as $fname)
						if(!preg_match($ninc_regex, $fname))
							$this->create_field($fname);
				}
			} else {
				$box = $form->box('lang_'.$dbobj->language());
				$box->bind($dbobjml);

				// work on the language form box (don't need to keep a
				// reference to the main form around)
				$this->form = $box;

				$fields = array_keys($dbobjml->field_list());
				$ninc_regex = '/^'.$dbobjml->_prefix()
					.'(id|creation_dttm|creator_id|author_id|language_id|'
					.$dbobj->primary().')$/';
				foreach($fields as $fname)
					if(!preg_match($ninc_regex, $fname))
						$this->create_field($fname);
			}
		}

		public function dbobj()
		{
			return $this->form->dbobj();
		}

		public function create_rel_single($fname, $title, $class)
		{
			$obj = new DropdownInput();
			$obj->set_items(DBOContainer::find($dbo = DBObject::create($class)));
			if(strpos($fname, '_parent_id')==strlen($fname)-10)
				$obj->add_null_item();
			return $this->form->add($fname, $obj, $title);
		}

		public function create_rel_many($fname, $title, $class)
		{
			// nothing happens, has_many is not handled
		}

		/**
		 * you could also display a list of checkboxes here...
		 */
		public function create_rel_manytomany($fname, $title, $class)
		{
			$obj = new Multiselect();
			$obj->set_items(DBOContainer::find($class));
			return $this->form->add($fname, $obj, $title);
		}

		public function create_rel_3way($fname, $title, $class, $field)
		{
			return $this->form->add($fname, new ThreewayInput(), $title);
		}

		public function create_rel_tags($fname, $title)
		{
			return $this->form->add($fname, new TagInput(), $title);
		}

		public function create_date($fname, $title)
		{
			return $this->form->add($fname, new DateInput(), $title)
				->disable_time();
		}

		public function create_dttm($fname, $title)
		{
			return $this->form->add($fname, new DateInput(), $title);
		}

		public function create_time($fname, $title)
		{
			return $this->form->add($fname, new TimeInput(), $title);
		}

		public function create_textarea($fname, $title)
		{
			return $this->form->add($fname, new Textarea(), $title);
		}

		public function create_bool($fname, $title)
		{
			return $this->form->add($fname, new CheckboxInput(), $title);
		}

		public function create_float($fname, $title)
		{
			return $this->form->add($fname, new TextInput(), $title);
		}

		public function create_text($fname, $title)
		{
			return $this->form->add($fname, new TextInput(), $title);
		}
	}

	class TableViewBuilder extends BuilderBase {
		protected $tv;
		protected $dbobj;

		public function build(&$tableview, $finalize = false)
		{
			$this->tv = $tableview;
			$this->dbobj = $tableview->dbobj()->dbobj();

			if($tableview->dbobj()->dbobj() instanceof DBObjectML)
				return $this->build_ml($finalize);
			else
				return $this->build_simple($finalize);
		}

		/**
		 * mainly used by TableView::append_auto
		 */
		public function create_auto(&$tableview, $field, $title = null)
		{
			$this->tv = $tableview;
			$this->dbobj = $tableview->dbobj()->dbobj();
			return $this->create_field($field, $title);
		}

		public function build_simple($finalize = false)
		{
			$dbobj = $this->dbobj();
			$fields = array_keys($dbobj->field_list());
			$ninc_regex = '/^'.$dbobj->_prefix()
				.'(password)$/';
			foreach($fields as $fname)
				if(!preg_match($ninc_regex, $fname))
					$this->create_field($fname, null);

			$relations = $dbobj->relations();
			foreach($relations as $key => &$data) {
				if($data['type']==DB_REL_N_TO_M
						||$data['type']==DB_REL_3WAY
						||$data['type']==DB_REL_TAGS)
					$this->create_field($key, null);
			}

			if($finalize)
				$this->tv->append_column(new CmdsTableViewColumn(
					$this->tv->dbobj()->dbobj()->primary(),
					Swisdk::config_value('runtime.controller.url')));
		}

		public function build_ml($finalize = false)
		{
			$this->build_simple();

			$primary = $this->dbobj->primary();
			$this->dbobj = $this->dbobj->dbobj();
			if($this->dbobj instanceof DBOContainer)
				$this->dbobj = $this->dbobj->dbobj();

			$fields = array_keys($this->dbobj->field_list());
			$ninc_regex = '/^'.$this->dbobj->_prefix()
				.'(id|password|language_id|'.$primary.')$/';
			foreach($fields as $fname)
				if(!preg_match($ninc_regex, $fname))
					$this->create_field($fname);

			$relations = $this->dbobj->relations();
			foreach($relations as $key => &$data) {
				if($data['type']==DB_REL_N_TO_M
						||$data['type']==DB_REL_3WAY
						||$data['type']==DB_REL_TAGS)
					$this->create_field($key);
			}

			if($finalize)
				$this->tv->append_column(new CmdsTableViewColumn($primary,
					Swisdk::config_value('runtime.controller.url')));
		}

		public function dbobj()
		{
			return $this->dbobj;
		}

		public function create_rel_single($fname, $title, $class)
		{
			return $this->tv->append_column(new DBTableViewColumn(
				$fname, $title, $class, $this->dbobj));
		}

		public function create_rel_many($fname, $title, $class)
		{
			return $this->tv->append_column(new ManyDBTableViewColumn(
				$fname, $title, $class, $this->dbobj));
		}

		public function create_rel_manytomany($fname, $title, $class)
		{
			return $this->tv->append_column(new ManyToManyDBTableViewColumn(
				$fname, $title, $class, $this->dbobj));
		}

		public function create_rel_3way($fname, $title, $class, $field)
		{
			// TODO show information from choices ($field) too
			return $this->tv->append_column(new ManyToManyDBTableViewColumn(
				$fname, $title, $class, $this->dbobj));
		}

		public function create_rel_tags($fname, $title)
		{
			return $this->tv->append_column(new ManyToManyDBTableViewColumn(
				$fname, $title, 'Tag', $this->dbobj));
		}

		public function create_date($fname, $title)
		{
			return $this->tv->append_column(
				new DateTableViewColumn($fname, $title, '%d.%m.%Y'));
		}

		public function create_dttm($fname, $title)
		{
			return $this->tv->append_column(
				new DateTableViewColumn($fname, $title));
		}

		public function create_time($fname, $title)
		{
			return $this->tv->append_column(
				new TimeTableViewColumn($fname, $title));
		}

		public function create_textarea($fname, $title)
		{
			return $this->tv->append_column(
				new TextTableViewColumn($fname, $title, 40));
		}

		public function create_bool($fname, $title)
		{
			return $this->tv->append_column(
				new BoolTableViewColumn($fname, $title));
		}

		public function create_float($fname, $title)
		{
			return $this->tv->append_column(
				new NumberTableViewColumn($fname, $title));
		}

		public function create_text($fname, $title)
		{
			return $this->tv->append_column(
				new TextTableViewColumn($fname, $title, 40));
		}
	}

?>
