<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	/**
	 * Form
	 *
	 * Object-oriented form building package
	 *
	 * Form strongly depends on DBObject for its inner workings (you don't
	 * necessarily need a database table for every form, though!)
	 */

	require_once MODULE_ROOT . 'inc.data.php';
	require_once MODULE_ROOT . 'inc.layout.php';

	class Form implements Iterator {

		/**
		 * array of formboxes (always at least one)
		 */
		protected $boxes = array();

		/**
		 * main form title
		 */
		protected $title;

		/**
		 * form id. Should be unique for the whole page
		 */
		protected $form_id;

		/**
		 * should the FormItem's names be mangled so that they are _really_
		 * unique (f.e. edit multiple records of the same type on one page)
		 */
		protected $unique = false;

		public function __construct($dbobj=null)
		{
			if($dbobj)
				$this->bind($dbobj);
		}

		public function title() { return $this->title; }
		public function set_title($title=null) { $this->title = $title; }
		public function enable_unique() { $this->unique = true; }

		public function id()
		{
			if(!$this->form_id)
				$this->generate_form_id();
			return $this->form_id;
		}

		/**
		 * generate an id for this form
		 *
		 * the id is used to track which form has been submitted if there
		 * were multiple forms on one page. See also is_valid()
		 */
		public function generate_form_id()
		{
			$this->form_id = Form::to_form_id($this->dbobj());
		}

		/**
		 * take a DBObject and return a form id
		 *
		 * XXX is it really necessary to take anything else but DBObjects?
		 */
		public static function to_form_id($tok, $id=0)
		{
			if($tok instanceof DBObject) {
				$id = $tok->id();
				return '__swisdk_form_'.$tok->table().'_'.($id?$id:0);
			}
			return '__swisdk_form_'.$tok.'_'.($id?$id:0);
		}

		/**
		 * return the FormBox with the given ID (this ID has no further
		 * meaning)
		 *
		 * This function should also be used to create FormBoxes (FormML
		 * returns FormMLBox)
		 */
		public function &box($id=0)
		{
			if(!$id && count($this->boxes))
				return reset($this->boxes);

			if(!isset($this->boxes[$id])) {
				$this->boxes[$id] = new FormBox();
				if($this->unique)
					$this->boxes[$id]->enable_unique();
				if($obj = $this->dbobj())
					$this->boxes[$id]->bind($obj);
			}
			return $this->boxes[$id];
		}

		/**
		 * easy form usage:
		 *
		 * forward these calls to the default FormBox
		 */
		public function dbobj()
		{
			return $this->box()->dbobj();
		}

		public function bind($dbobj)
		{
			$this->box()->bind($dbobj);
		}

		public function add()
		{
			$args = func_get_args();
			return call_user_func_array(array($this->box(), 'add'), $args);
		}

		public function add_auto()
		{
			$args = func_get_args();
			return call_user_func_array(array($this->box(), 'add_auto'), $args);
		}

		public function add_rule()
		{
			$args = func_get_args();
			return call_user_func_array(array($this->box(), 'add_rule'), $args);
		}

		/**
		 * search and return a FormItem
		 */
		public function item($name)
		{
			foreach($this->boxes as &$box)
				if($item =& $box->item($name))
					return $item;
			return null;
		}

		/**
		 * @return the Form html
		 */
		public function html($arg = 'FormRenderer')
		{
			$renderer = null;
			if($arg instanceof FormRenderer)
				$renderer = $arg;
			else if(class_exists($arg))
				$renderer = new $arg;
			else
				SwisdkError::handle(new FatalError(
					'Invalid renderer specification: '.$arg));
			$this->accept($renderer);

			return $renderer->html();
		}

		/**
		 * accept the FormRenderer
		 */
		public function accept($renderer)
		{
			$this->add(new HiddenInput($this->id()))->set_value(1);

			$renderer->visit($this);
			foreach($this->boxes as &$box)
				$box->accept($renderer);
		}

		/**
		 * validate the form
		 */
		public function is_valid()
		{
			// has this form been submitted (or was it another form on the same page)
			if(!isset($_REQUEST[$this->id()]))
				return false;

			$valid = true;
			// loop over all FormBox es
			foreach($this->boxes as &$box)
				if(!$box->is_valid())
					$valid = false;
			return $valid;
		}

		/**
		 * Iterator implementation (see PHP Object Iteration)
		 */

		public function rewind()	{ return reset($this->boxes); }
		public function current()	{ return current($this->boxes); }
		public function key()		{ return key($this->boxes); }
		public function next()		{ return next($this->boxes); }
		public function valid()		{ return $this->current() !== false; }
	}

	/**
	 * The FormBox is the basic grouping block of a Form
	 *
	 * There may be 1-n FormBoxes in one Form
	 */
	class FormBox implements Iterator, ArrayAccess {

		/**
		 * the title of this Box
		 */
		protected $title;

		/**
		 * holds all FormItems and FormBoxes that are part of this
		 * FormBox
		 */
		protected $items = array();

		/**
		 * holds references to all FormBoxes which are stored in the
		 * $items array
		 */
		protected $boxrefs = array();

		/**
		 * holds the DBObject bound to this FormBox
		 */
		protected $dbobj;

		/**
		 * same as comment form Form::$unique
		 */
		protected $unique = false;

		/**
		 * validation message
		 */
		protected $message;

		/**
		 * Form and FormBox rules
		 */
		protected $rules = array();

		/**
		 * FormBox Id
		 */
		protected $formbox_id;

		/**
		 * @param $dbobj: the DBObject bound to the Form
		 */
		public function __construct($dbobj=null)
		{
			if($dbobj)
				$this->bind($dbobj);
		}

		public function enable_unique() { $this->unique = true; }
		public function set_title($title=null) { $this->title = $title; }
		public function title() { return $this->title; }

		/**
		 * @param dbobj: a DBObject
		 */
		public function bind($dbobj)
		{
			$this->formbox_id = Form::to_form_id($dbobj);
			$this->dbobj = $dbobj;
		}

		/**
		 * @return the bound DBObject
		 */
		public function &dbobj()
		{
			return $this->dbobj;
		}

		public function id()
		{
			return $this->formbox_id;
		}

		/**
		 * add a validation message to the FormBox (will be displayed after
		 * everything else)
		 */
		public function message()		{ return $this->message; }
		public function set_message($message)	{ $this->message = $message; }
		public function add_message($message)
		{
			if($this->message)
				$this->message .= "\n<br />".$message;
			else
				$this->message = $message;
		}

		public function add_rule(FormRule $rule)
		{
			$this->rules[] = $rule;
		}

		/**
		 * add a new element to this FormBox
		 *
		 * add(field) // default FormItem is TextInput
		 * add(field, FormItem)
		 * add(field, FormItem, title)
		 * add(relspec, title)
		 * add(FormItem)
		 * add(FormBox)
		 *
		 * returns the newly added FormItem
		 */
		public function add()
		{
			$args = func_get_args();

			if(count($args)<2) {
				if($args[0] instanceof FormBox) {
					$this->items[] = $args[0];
					$this->boxrefs[] = $args[0];
					return $args[0];
				} else if($args[0] instanceof FormItem) {
					return $this->add_initialized_obj($args[0]);
				} else {
					return $this->add_obj($args[0],
						new TextInput());
				}
			} else if($args[1] instanceof FormItem) {
				return call_user_func_array(
					array(&$this, 'add_obj'),
					$args);
			} else {
				return call_user_func_array(
					array(&$this, 'add_dbobj_ref'),
					$args);
			}
		}

		/**
		 * add an element to the form
		 *
		 * Usage example (these might "just do the right thing"):
		 *
		 * $form->add_auto('start_dttm', 'Publication date');
		 * $form->add_auto('title');
		 *
		 * NOTE! The bound DBObject MUST point to a valid table if
		 * you want to use this function.
		 */
		public function add_auto($field, $title=null)
		{
			require_once MODULE_ROOT.'inc.builder.php';
			static $builder = null;
			if($builder===null)
				$builder = new FormBuilder();
			return $builder->create_auto($this, $field, $title);
		}

		/**
		 * return a prettyfied title for this formitem name
		 *
		 * Examples:
		 *
		 * DBObject class: News
		 *
		 * news_title => Title
		 * news_creation_dttm => Creation
		 * news_description => Description
		 * news_xy_zx => Xy Zx
		 */
		protected function pretty_title($fname)
		{
			return ucwords(str_replace('_', ' ',
				preg_replace('/^('.$this->dbobj()->_prefix()
					.')?(.*?)(_id|_dttm)?$/', '\2', $fname)));
		}

		/**
		 * handle add(FormItem) case
		 */
		protected function add_initialized_obj($obj)
		{
			$obj->set_preinitialized();
			$obj->set_form_box($this);
			if($this->unique)
				$obj->enable_unique();
			$obj->init_value($this->dbobj());
			if($obj->name())
				$this->items[$obj->name()] =& $obj;
			else
				$this->items[] =& $obj;
			return $obj;
		}

		/**
		 * handle add(field, FormItem) and add(field, FormItem, title) cases
		 */
		protected function add_obj($field, $obj, $title=null)
		{
			$dbobj = $this->dbobj();

			if($title===null)
				$title = $this->pretty_title($field);

			$obj->set_title($title);
			$obj->set_name($field);
			$obj->set_form_box($this);
			if($this->unique)
				$obj->enable_unique();
			$obj->init_value($dbobj);

			$this->items[$field] = $obj;

			return $obj;
		}

		/**
		 * handle add(relspec, title) case
		 */
		protected function add_dbobj_ref($relspec, $title=null)
		{
			$relations = $this->dbobj()->relations();
			if(isset($relations[$relspec])) {
				switch($relations[$relspec]['type']) {
					case DB_REL_SINGLE:
						$f = $this->add_obj($title, new DropdownInput(),
							$relations[$relspec]['field']);
						$dc = DBOContainer::find(
							$relations[$relspec]['class']);
						$choices = array();
						foreach($dc as $o) {
							$items[$o->id()] = $o->title();
						}
						$f->set_items($items);
						$f->set_form_box($this);
						break;
					case DB_REL_MANYTOMANY:
						$f = $this->add_obj($title, new Multiselect(),
							$relations[$relspec]['field']);
						$dc = DBOContainer::find(
							$relations[$relspec]['class']);
						$items = array();
						foreach($dc as $o) {
							$items[$o->id()] = $o->title();
						}
						$f->set_items($items);
						$f->set_form_box($this);
						break;
					case DB_REL_MANY:
						SwisdkError::handle(new BasicSwisdkError(
							'Cannot edit relation of type DB_REL_MANY!'
							.' relspec: '.$relspec));
					default:
						SwisdkError::handle(new BasicSwisdkError(
							'Oops. Unknown relation type '.$relspec));
				}
			}
		}

		/**
		 * @return the FormBox html
		 *
		 * NOTE! This is not used when calling Form::html()
		 */
		public function html($arg = 'FormRenderer')
		{
			$renderer = null;
			if($arg instanceof FormRenderer)
				$renderer = $arg;
			else if(class_exists($arg))
				$renderer = new $arg;
			else
				SwisdkError::handle(new FatalError(
					'Invalid renderer specification: '.$arg));
			$this->accept($renderer);

			return $renderer->html();
		}

		/**
		 * accept the FormRenderer
		 */
		public function accept($renderer)
		{
			$renderer->visit($this);
			foreach($this->items as &$item)
				$item->accept($renderer);
		}

		/**
		 * validate the form
		 */
		public function is_valid()
		{
			$valid = true;
			// loop over FormRules
			foreach($this->rules as &$rule)
				if(!$rule->is_valid($this))
					$valid = false;
			// loop over all Items
			foreach($this->items as &$item)
				if(!$item->is_valid())
					$valid = false;
			return $valid;
		}
		
		/**
		 * @return the formitem with name $name
		 */
		public function item($name)
		{
			if(isset($this->items[$name]))
				return $this->items[$name];
			foreach($this->boxrefs as &$box)
				if($item =& $box->item($name))
					return $item;
			return null;
		}

		/**
		 * Iterator implementation (see PHP Object Iteration)
		 */

		public function rewind()	{ return reset($this->items); }
		public function current()	{ return current($this->items); }
		public function key()		{ return key($this->items); }
		public function next()		{ return next($this->items); }
		public function valid()		{ return $this->current() !== false; }

		/**
		 * ArrayAccess implementation (see PHP SPL)
		 */
		
		public function offsetExists($offset) { return isset($this->items[$offset]); }
		public function offsetGet($offset) { return $this->items[$offset]; }
		public function offsetSet($offset, $value)
		{
			if($offset===null)
				$this->items[] = $value;
			else
				$this->items[$offset] = $value;
		}
		public function offsetUnset($offset) { unset($this->items[$offset]); }
	}

	/**
	 * Multi-language forms are implemented by binding the parent DBObject and the
	 * translation DBObject to two FormBoxes, which are both part of the main
	 * Form
	 */

	class FormML extends Form {
		public function &box($id=0)
		{
			if(!isset($this->boxes[$id])) {
				$this->boxes[$id] = new FormMLBox();
				if($this->unique)
					$this->boxes[$id]->enable_unique();
			}
			return $this->boxes[$id];
		}

	}

	class FormMLBox extends FormBox {
		// this is only a type marker for the FormBuilder
	}

	/**
	 * base class of all form items
	 */
	class FormItem {

		/**
		 * the name (DBObject field name)
		 */
		protected $name;

		/**
		 * user-readable title
		 */
		protected $title;

		/**
		 * message (f.e. validation errors)
		 */
		protected $message;

		/**
		 * the value (ooh!)
		 */
		protected $value;

		/**
		 * validation rule objects
		 */
		protected $rules = array();

		/**
		 * additional html attributes
		 */
		protected $attributes = array();

		/**
		 * This gets prepended to the name of every FormItem so that
		 * every FormItem's name is unique on the page.
		 */
		protected $box_name = null;

		/**
		 * is this FormItem part of a Form/FormBox with unique-ness
		 * enabled?
		 */
		protected $unique = false;

		/**
		 * has this element beed added to the FormBox with
		 * add_initialized_obj ? If yes, do not mangle the name even
		 * if $unique is true
		 */
		protected $preinitialized = false;

		public function __construct($name=null)
		{
			if($name)
				$this->name = $name;
		}

		/**
		 * accessors and mutators
		 */
		public function value()			{ return $this->value; }
		public function set_value($value)	{ $this->value = $value; } 
		public function name()			{ return $this->name; }
		public function set_name($name)		{ $this->name = $name; } 
		public function title()			{ return $this->_stripit($this->title); }
		public function set_title($title)	{ $this->title = $title; } 
		public function message()		{ return $this->message; }
		public function set_message($message)	{ $this->message = $message; }
		public function add_message($message)
		{
			if($this->message)
				$this->message .= "\n<br />".$message;
			else
				$this->message = $message;
		}
		public function enable_unique() { $this->unique = true; }
		public function set_preinitialized() { $this->preinitialized = true; }
		public function set_default_value($value)
		{
			if(!$this->value)
				$this->value = $value;
		}


		/**
		 * return a unique name for this FormItem
		 */
		public function iname() {
			return ((!$this->preinitialized&&$this->unique)?
				$this->box_name:'').$this->name;
		}

		/**
		 * get some informations from the FormBox containing this
		 * FormItem
		 */
		public function set_form_box(&$box)
		{
			$this->box_name = $box->id().'_';
		}

		/**
		 * internal hack, implementation detail of MLForm that found its
		 * way into the standard form code... I hate it. But it works.
		 * And the user does not havel to care.
		 *
		 * This strips the part of the FormItem name that makes it possible
		 * to display multiple FormItems of the same fields in the same form.
		 */
		protected function _stripit($str)
		{
			return preg_replace('/__language([0-9]+)_/', '', $str);
		}

		/**
		 * get an array of html attributes
		 */
		public function attributes()
		{
			return $this->attributes;
		}

		public function set_attributes($attributes)
		{
			$this->attributes = array_merge($this->attributes, $attributes); 
		}

		/**
		 * helper function which composes a html-compatible attribute
		 * string
		 */
		public function attribute_html()
		{
			$html = ' ';
			foreach($this->attributes as $k => $v)
				$html .= $k.'="'.htmlspecialchars($v).'" ';
			return $html;
		}

		/**
		 * get the value from the user and store it in this FormItem
		 * and also in the corresponding field in the bound DBObject
		 */
		public function init_value($dbobj)
		{
			$name = $this->name();
			$sname = $this->_stripit($name);

			if(($val = getInput($this->iname()))!==null) {
				if(is_array($val))
					$dbobj->set($sname, $val);
				else
					$dbobj->set($sname, stripslashes($val));
			}

			$this->set_value($dbobj->get($sname));
		}

		/**
		 * add a FormItem validation rule
		 */
		public function add_rule(FormItemRule $rule)
		{
			$this->rules[] = $rule;
		}

		public function is_valid()
		{
			$valid = true;
			foreach($this->rules as &$rule)
				if(!$rule->is_valid($this))
					$valid = false;
			return $valid;
		}

		public function accept($renderer)
		{
			$renderer->visit($this);
		}
	}

	/**
	 * base class for several simple input fields
	 */
	abstract class SimpleInput extends FormItem {
		protected $type = '#INVALID';
		public function type()
		{
			return $this->type;
		}
	}

	class TextInput extends SimpleInput {
		protected $type = 'text';
		protected $attributes = array('size' => 60);
	}

	/**
	 * hidden fields get special treatment (see also FormBox::html())
	 */
	class HiddenInput extends TextInput {
		protected $type = 'hidden';
	}

	class PasswordInput extends SimpleInput {
		protected $type = 'password';
		protected $attributes = array('size' => 60);
	}

	/**
	 * CheckboxInput uses another hidden input field to verify if
	 * the Checkbox was submitted at all.
	 */
	class CheckboxInput extends FormItem {
		protected $type = 'checkbox';

		public function init_value($dbobj)
		{
			$name = $this->iname();
			$sname = $this->_stripit($this->name());

			if(isset($_POST['__check_'.$name])) {
				if(getInput($name))
					$dbobj->set($sname, 1);
				else
					$dbobj->set($sname, 0);
			}

			$this->set_value($dbobj->get($sname));
		}
	}

	/**
	 * true, false and i-don't know!
	 *
	 * (or, more accurately, checked, unchecked and mixed)
	 */
	class TristateInput extends FormItem {
	}

	class Textarea extends FormItem {
		protected $attributes = array('rows' => 12, 'cols' => 60);
	}

	/**
	 * Textarea with all the Wysiwyg-Bling!
	 */
	class RichTextarea extends FormItem {
		protected $attributes = array('style' => 'width:800px;height:300px;');
	}

	/**
	 * base class for all FormItems which offer a choice between several items
	 */
	class SelectionFormItem extends FormItem {
		public function set_items($items)
		{
			$this->items = $items;
		}

		public function items()
		{
			return $this->items;
		}

		protected $items=array();
	}

	class DropdownInput extends SelectionFormItem {
	}

	class Multiselect extends SelectionFormItem {
		public function value()
		{
			$val = parent::value();
			if(!$val)
				return array();
			return $val;
		}
	}

	/**
	 * display all enum choices for a given SQL field
	 */
	class EnumInput extends Multiselect {

		/**
		 * ATTENTION! $table _cannot_ be escaped
		 */
		public function __construct($table, $field)
		{
			$fs = DBObject::db_get_array('SHOW COLUMNS FROM '
				.$table, 'Field');
			$field = $fs[$field];
			$array = explode('\',\'', substr($field['Type'], 6,
				strlen($field['Type'])-8));
			$this->set_items(array_combine($array, $array));
		}
	}

	/**
	 * base class for all FormItems which want to occupy a whole
	 * line (no title, no message)
	 */
	class FormBar extends FormItem {
	}

	class SubmitButton extends FormBar {
		protected $attributes = array('value' => 'Submit');

		public function init_value($dbobj)
		{
			// i have no value
		}
	}

	class DateInput extends FormItem {
	}

	abstract class FormRule {
		public function __construct($message=null)
		{
			if($message)
				$this->message = $message;
		}

		public function is_valid(&$form)
		{
			if($this->is_valid_impl($form))
				return true;
			$form->add_message($this->message);
			return false;
		}

		protected function is_valid_impl(&$form)
		{
			return false;
		}

		protected $message;
	}

	class EqualFieldsRule extends FormRule {
		protected $message = 'The two related fields are not equal';

		public function __construct($field1, $field2, $message = null)
		{
			$this->field1 = $field1;
			$this->field2 = $field2;
			parent::__construct($message);
		}

		protected function is_valid_impl(&$form)
		{
			$dbobj = $form->dbobj();
			return $dbobj->get($this->field1) == $dbobj->get($this->field2);
		}

		protected $field1;
		protected $field2;
	}


	abstract class FormItemRule {
		public function __construct($message=null)
		{
			if($message)
				$this->message = $message;
		}

		public function is_valid(FormItem &$item)
		{
			if($this->is_valid_impl($item))
				return true;
			$item->add_message($this->message);
			return false;
		}

		protected function is_valid_impl(FormItem &$item)
		{
			return false;
		}

		protected $message;
	}

	class RequiredRule extends FormItemRule {
		protected $message = 'Value required';

		protected function is_valid_impl(FormItem &$item)
		{
			return $item->value()!='';
		}
	}

	/**
	 * the visitor user (default: user id 1) is not a valid user
	 * if you use this rule.
	 *
	 * It will still be displayed in the DropdownInput (or whatever)!
	 */
	class UserRequiredRule extends RequiredRule {
		protected $message = 'User required';

		protected function is_valid_impl(FormItem &$item)
		{
			require_once MODULE_ROOT.'inc.session.php';
			$value = $item->value();
			return $value!='' && $value!=SWISDK2_VISITOR;
		}
	}

	class NumericRule extends FormItemRule {
		protected $message = 'Value must be numeric';

		protected function is_valid_impl(FormItem &$item)
		{
			return is_numeric($item->value());
		}
	}

	class RegexRule extends FormItemRule {
		protected $message = 'Value does not validate';

		public function __construct($regex, $message = null)
		{
			$this->regex = $regex;
			parent::__construct($message);
		}
		
		protected function is_valid_impl(FormItem &$item)
		{
			return preg_match($this->regex, $item->value());
		}

		protected $regex;
	}

	class EmailRule extends RegexRule {
		public function __construct($message = null)
		{
			parent::__construct(
'/^((\"[^\"\f\n\r\t\v\b]+\")|([\w\!\#\$\%\&\'\*\+\-\~\/\^\`\|\{\}]+(\.'
. '[\w\!\#\$\%\&\'\*\+\-\~\/\^\`\|\{\}]+)*))@((\[(((25[0-5])|(2[0-4][0-9])'
. '|([0-1]?[0-9]?[0-9]))\.((25[0-5])|(2[0-4][0-9])|([0-1]?[0-9]?[0-9]))\.'
. '((25[0-5])|(2[0-4][0-9])|([0-1]?[0-9]?[0-9]))\.((25[0-5])|(2[0-4][0-9])'
. '|([0-1]?[0-9]?[0-9])))\])|(((25[0-5])|(2[0-4][0-9])|([0-1]?[0-9]?[0-9]))'
. '\.((25[0-5])|(2[0-4][0-9])|([0-1]?[0-9]?[0-9]))\.((25[0-5])|(2[0-4][0-9])'
. '|([0-1]?[0-9]?[0-9]))\.((25[0-5])|(2[0-4][0-9])|([0-1]?[0-9]?[0-9])))|'
. '((([A-Za-z0-9\-])+\.)+[A-Za-z\-]+))$/',
				$message);
		}
	}

	class CallbackRule extends FormItemRule {
		public function __construct($callback, $message = null)
		{
			$this->callback = $callback;
			parent::__construct($message);
		}

		protected function is_valid_impl(FormItem &$item)
		{
			return call_user_func($this->callback, $item);
		}

		protected $callback;
	}

	class EqualsRule extends FormItemRule {
		protected $message = 'Value does not validate';

		public function __construct($compare_value, $message = null)
		{
			$this->compare_value = $compare_value;
			parent::__construct($message);
		}

		protected function is_valid_impl(FormItem &$item)
		{
			return $this->compare_value == $item->value();
		}

		protected $compare_value;
	}

	class MD5EqualsRule extends EqualsRule {
		protected function is_valid_impl(FormItem &$item)
		{
			return $this->compare_value == md5($item->value());
		}
	}

	class FormRenderer {

		protected $grid;
		protected $html_start = '';
		protected $html_end = '';

		public function __construct()
		{
			$this->grid = new Layout_Grid();
		}

		public function html()
		{
			return $this->html_start.$this->grid->html().$this->html_end;
		}

		public function add_html($html)
		{
			$this->html_start .= $html;
		}

		public function add_html_start($html)
		{
			$this->html_start = $html.$this->html_start;
		}

		public function add_html_end($html)
		{
			$this->html_end .= $html;
		}

		/**
		 * handle the passed object
		 *
		 * it first tries to find a method named visit_ObjectClass , and if
		 * not successful, walks the inheritance ancestry to find a matching
		 * visit method.
		 *
		 * That way, you can derive your own FormItems without necessarily
		 * needing to extend the FormRenderer
		 */
		public function visit($obj)
		{
			$class = get_class($obj);
			if($obj instanceof Form)
				$class = 'Form';
			else if($obj instanceof FormBox)
				$class = 'FormBox';

			$method = 'visit_'.$class;
			if(method_exists($this, $method)) {
				call_user_func(array($this, $method), $obj);
				return;
			} else {
				$parents = class_parents($class);
				foreach($parents as $p) {
					$method = 'visit_'.$p;
					if(method_exists($this, $method)) {
						call_user_func(array($this, $method),
							$obj);
						return;
					}
				}
			}

			echo "oops.";
			return;

			SwisdkError::handle(new FatalError(
				'FormRenderer::visit: Cannot visit '.$class));
		}

		public function visit_Form($obj)
		{
			$this->add_html_start(
				'<form method="post" action="'.$_SERVER['REQUEST_URI']
				.'" name="'.$obj->id()."\">\n");
			$this->add_html_end('</form>');
			if($title = $obj->title())
				$this->_render_bar($obj,
					'<big><strong>'.$title.'</strong></big>');
		}

		public function visit_FormBox($obj)
		{
			if($message = $obj->message())
				$this->add_html_end('<span style="color:red">'
					.$message.'</span>');
			if($title = $obj->title())
				$this->_render_bar($obj, '<strong>'.$title.'</strong>');
		}

		public function visit_HiddenInput($obj)
		{
			$this->add_html($this->_simpleinput_html($obj));
		}

		public function visit_SimpleInput($obj)
		{
			$this->_render($obj, $this->_simpleinput_html($obj));
		}

		public function visit_CheckboxInput($obj)
		{
			$name = $obj->iname();
			$this->_render($obj, sprintf(
				'<input type="checkbox" name="%s" id="%s" %s value="1" />'
				.'<input type="hidden" name="__check_'.$name
				.'" value="1" />',
				$name, $name,
				($obj->value()?'checked="checked" ':' ')
				.$obj->attribute_html()));
		}

		public function visit_TristateInput($obj)
		{
			static $js = "
<script type=\"text/javascript\">
function formitem_tristate(elem)
{
	var value = document.getElementById(elem.id.replace(/^__cont_/, ''));
	var cb = document.getElementById('__cb_'+value.id);

	switch(value.value) {
		case 'checked':
			cb.checked = false;
			value.value = 'unchecked';
			break;
		case 'unchecked':
			cb.checked = true;
			cb.disabled = true;
			value.value = 'mixed';
			break;
		case 'mixed':
		default:
			cb.checked = true;
			cb.disabled = false;
			value.value = 'checked';
			break;
	}

	return false;
}
</script>";
			$name = $obj->iname();
			$value = $obj->value();
			$cb_html = '';
			if($value=='mixed')
				$cb_html = 'checked="checked" disabled="disabled"';
			else if($value=='checked')
				$cb_html = 'checked="checked"';
			$this->_render($obj, $js.sprintf(
'<span style="position:relative;">
	<div style="position:absolute;top:0;left:0;width:20px;height:20px;"
		id="__cont_%s" onclick="formitem_tristate(this)"></div>
	<input type="checkbox" name="__cb_%s" id="__cb_%s" %s />
	<input type="hidden" name="%s" id="%s" value="%s" />
</span>', $name, $name, $name, $cb_html, $name, $name, $value));

			// only send the javascript once
			$js = '';
		}

		public function visit_Textarea($obj)
		{
			$name = $obj->iname();
			$this->_render($obj, sprintf(
				'<textarea name="%s" id="%s" %s>%s</textarea>',
				$name, $name, $obj->attribute_html(),
				$obj->value()));
		}

		public function visit_RichTextarea($obj)
		{
			$name = $obj->iname();
			$value = $obj->value();
			$attributes = $obj->attribute_html();
			$html = <<<EOD
<textarea name="$name" id="$name" $attributes>$value</textarea>
<script type="text/javascript" src="/scripts/util.js"></script>
<script type="text/javascript" src="/scripts/fckeditor/fckeditor.js"></script>
<script type="text/javascript">
function load_editor_$name(){
var oFCKeditor = new FCKeditor('$name');
oFCKeditor.BasePath = '/scripts/fckeditor/';
oFCKeditor.Height = 450;
oFCKeditor.Width = 750;
oFCKeditor.ReplaceTextarea();
}
add_event(window,'load',load_editor_$name);
</script>
EOD;
			$this->_render($obj, $html);
		}

		public function visit_DropdownInput($obj)
		{
			$name = $obj->iname();
			$html = '<select name="'.$name.'" id="'.$name.'"'
				.$obj->attribute_html().'>';
			$value = $obj->value();
			$items = $obj->items();
			foreach($items as $k => $v) {
				$html .= '<option ';
				if($value==$k)
					$html .= 'selected="selected" ';
				$html .= 'value="'.$k.'">'.$v.'</option>';
			}
			$html .= '</select>';
			$this->_render($obj, $html);
		}

		public function visit_Multiselect($obj)
		{
			$name = $obj->iname();
			$html = '<select name="'.$name.'[]" id="'.$name
				.'" multiple="multiple"'.$obj->attribute_html().'>';
			$value = $obj->value();
			if(!$value)
				$value = array();
			$items = $obj->items();
			foreach($items as $k => $v) {
				$html .= '<option ';
				if(in_array($k,$value))
					$html .= 'selected="selected" ';
				$html .= 'value="'.$k.'">'.$v.'</option>';
			}
			$html .= '</select>';
			$this->_render($obj, $html);
		}

		public function visit_DateInput($obj)
		{
			$html = '';
			static $js_sent = false;
			if(!$js_sent) {
				$js_sent = true;
				$html.=<<<EOD
<link rel="stylesheet" type="text/css" media="all"
	href="/scripts/calendar/calendar-win2k-1.css" title="win2k-cold-1" />
<script type="text/javascript" src="/scripts/calendar/calendar.js"></script>
<script type="text/javascript" src="/scripts/calendar/calendar-en.js"></script>
<script type="text/javascript" src="/scripts/calendar/calendar-setup.js"></script>
EOD;
			}

			$name = $obj->iname();
			$span_name = $name.'_span';
			$trigger_name = $name.'_trigger';
			$value = intval($obj->value());
			if(!$value)
				$value = time();
			// TODO use iname

			$display_value = strftime("%d. %B %Y : %H:%M", $value);

			$html.=<<<EOD
<input type="hidden" name="$name" id="$name" value="$value" />
<span id="$span_name">$display_value</span> <img src="/scripts/calendar/img.gif"
	id="$trigger_name"
	style="cursor: pointer; border: 1px solid red;" title="Date selector"
	onmouseover="this.style.background='red';" onmouseout="this.style.background=''" />
<script type="text/javascript">
Calendar.setup({
	inputField  : "$name",
	ifFormat    : "%s",
	displayArea : "$span_name",
	daFormat    : "%d. %B %Y : %H:%M",
	button      : "$trigger_name",
	singleClick : true,
	showsTime   : true,
	step        : 1
});
</script>
EOD;
			$this->_render($obj, $html);
		}

		public function visit_SubmitButton($obj)
		{
			$this->_render_bar($obj,
				'<input type="submit" '.$obj->attribute_html().'/>');
		}

		/**
		 * when you extend the FormRenderer, you can (and should) use
		 * the functions below here to render your FormItems.
		 *
		 * If you only use those helpers, it will be very easy to
		 * completely change the way some form is displayed.
		 */

		protected function _render($obj, $field_html)
		{
			$y = $this->grid->height();
			$this->grid->add_item(0, $y, $this->_title_html($obj));
			$this->grid->add_item(1, $y, $field_html.$this->_message_html($obj));
		}

		protected function _render_bar($obj, $html)
		{
			$this->grid->add_item(0, $this->grid->height(), $html, 2, 1);
		}

		protected function _title_html($obj)
		{
			return '<label for="'.$obj->iname().'">'.$obj->title().'</label>';
		}

		protected function _message_html($obj)
		{
			$msg = $obj->message();
			return $msg?'<br /><span style="color:red">'.$msg.'</span>':'';
		}

		protected function _simpleinput_html($obj)
		{
			$name = $obj->iname();
			return sprintf(
				'<input type="%s" name="%s" id="%s" value="%s" %s />',
				$obj->type(), $name, $name, $obj->value(),
				$obj->attribute_html());
		}
	}

?>
