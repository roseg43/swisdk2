<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	abstract class Swisdk_SimpleForm_Entry {
		public function __construct($name, $label, $error=null)
		{
			if($label instanceof Swisdk_Layout_Item) {
				$this->label = $label;
			} else {
				$this->label = new Swisdk_Form_Label('<label for="' . $name . '">' . $label . '</label>');
			}
			
			if($error instanceof Swisdk_Layout_Item) {
				$this->error = $error;
			} else {
				$this->error = new Swisdk_Form_Label($error);
			}
		}
		
		public function &label()
		{
			return $this->label;
		}
		
		public function &entry()
		{
			return $this->entry;
		}
		
		public function &error()
		{
			return $this->error;
		}
		
		public function render(&$grid)
		{
			$y = $grid->height();
			$grid->add_item(0, $y, $this->label);
			$grid->add_item(1, $y, $this->entry);
			$grid->add_item(2, $y, $this->error);
		}
		
		public function add_rule($rule, $failmessage, $args=null)
		{
			$this->rules[] = array('rule' => $rule, 'failmessage' => $failmessage, 'args' => $args);
		}
		
		public function is_valid()
		{
			foreach($this->rules as &$rule)
			{
				if($rule['rule'] instanceof Swisdk_SimpleForm_Validation_Rule) {
					if(!$rule['rule']->is_valid($this, $rule['args'])) {
						$this->error->set_text($rule['failmessage']);
						return false;
					}
				} else {
					$obj = Swisdk_SimpleForm_Validation::create($rule['rule']);
					if(!$obj->is_valid($this, $rule['args'])) {
						$this->error->set_text($rule['failmessage']);
						return false;
					}
				}
			}
			return true;
		}
		
		protected $label;
		protected $entry;
		protected $error;
		
		protected $rules = array();
	}
	
	class Swisdk_SimpleForm_HiddenEntry extends Swisdk_SimpleForm_Entry {
		public function __construct($name, $label, $value/*, $args*/)
		{
			$this->entry = new Swisdk_Form_HiddenEntry($name, $value);
		}
	}
	
	class Swisdk_SimpleForm_TextEntry extends Swisdk_SimpleForm_Entry {
		public function __construct($name, $label, $value/*, $args*/)
		{
			$this->entry = new Swisdk_Form_TextEntry($name, $value);
			parent::__construct($name, $label);
		}
	}

	class Swisdk_SimpleForm_TextareaEntry extends Swisdk_SimpleForm_Entry {
		public function __construct($name, $label, $value/*, $args*/)
		{
			$this->entry = new Swisdk_Form_TextareaEntry($name, $value);
			parent::__construct($name, $label);
		}
	}
	
	class Swisdk_SimpleForm_PasswordEntry extends Swisdk_SimpleForm_Entry {
		public function __construct($name, $label, $value/*, $args*/)
		{
			$this->entry = new Swisdk_Form_PasswordEntry($name, $value);
			parent::__construct($name, $label);
		}
	}
	
	class Swisdk_SimpleForm_CheckBox extends Swisdk_SimpleForm_Entry {
		public function __construct($name, $label, $value/*, $args*/)
		{
			$this->entry = new Swisdk_Form_CheckBoxEntry($name, $value);
			parent::__construct($name, $label);
		}
	}
	
	class Swisdk_SimpleForm_ComboBox extends Swisdk_SimpleForm_Entry {
		public function __construct($name, $label, $value, $args)
		{
			$this->entry = new Swisdk_Form_ComboBoxEntry($name, $value, $args);
			parent::__construct($name, $label);
		}
	}

	class Swisdk_SimpleForm_Multiselect extends Swisdk_SimpleForm_Entry {
		public function __construct($name, $label, $value, $args)
		{
			$this->entry = new Swisdk_Form_Multiselect($name, $value, $args);
			parent::__construct($name, $label);
		}
	}
	
	class Swisdk_SimpleForm_SubmitButton extends Swisdk_SimpleForm_Entry {
		public function __construct($name, $label, $value/*, $args*/)
		{
			$this->entry = new Swisdk_Form_SubmitButton($name, $value);
			parent::__construct($name, $label);
		}

		public function render(&$grid)
		{
			$y = $grid->height();
			$grid->add_item(0, $y, $this->entry(), 3);
		}
	}
	
?>
