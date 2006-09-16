<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class TableView implements Iterator, ArrayAccess {

		/**
		 * TableViewColumn instances
		 */
		protected $columns = array();

		/**
		 * the data to be rendererd (nested array)
		 */
		protected $data = array();

		public function append_column(TableViewColumn $column)
		{
			$column->set_tableview($this);
			$this->columns[$column->name()] = $column;
		}

		public function prepend_column(TableViewColumn $column)
		{
			$name = $column->name();
			if(isset($this->columns[$name]))
				unset($this->columns[$name]);
			$this->columns = array_merge(
				array($name => $column),
				$this->columns);
		}

		public function html()
		{
			return $this->render_head()
				.$this->render_body()
				.$this->render_foot();
		}

		public function set_data($data)
		{
			$this->data = $data;
		}

		public function column_count()
		{
			return count($this->columns);
		}

		protected function render_head()
		{
			$html = "<table class=\"s-table\">\n<thead>\n<tr>\n";
			foreach($this->columns as &$col)
				$html .= '<th>'.$col->title()."</th>\n";
			$html .= "</tr>\n</thead>\n";
			return $html;
		}

		protected function render_body()
		{
			$html = "<tbody>\n";
			foreach($this->data as &$row)
				$html .= $this->render_row($row);
			$html .= "</tbody>\n";
			return $html;
		}

		protected function render_row(&$row)
		{
			$html = "<tr>\n";
			foreach($this->columns as &$col)
				$html .= '<td>'.$col->html($row)."</td>\n";
			$html .= "</tr>\n";
			return $html;
		}

		protected function render_foot()
		{
			return "</table>\n";
		}

		/**
		 * Iterator implementation (see PHP Object Iteration)
		 */
		public function rewind() { reset($this->columns); }
		public function current() { return current($this->columns); }
		public function key() { return key($this->columns); }
		public function next() { return next($this->columns); }
		public function valid() { return $this->current() !== false; }

		/**
		 * ArrayAccess implementation (see PHP SPL)
		 */
		public function offsetExists($offset) { return isset($this->columns[$offset]); }
		public function offsetGet($offset) { return $this->columns[$offset]; }
		public function offsetSet($offset, $value)
		{
			$value->set_tableview($this);
			if($offset===null)
				$this->columns[] = $value;
			else
				$this->columns[$offset] = $value;
		}
		public function offsetUnset($offset) { unset($this->columns[$offset]); }
	}

	abstract class TableViewColumn {
		public function __construct($column, $title=null)
		{
			$this->args = func_get_args();
			$this->column = array_shift($this->args);
			$this->title = array_shift($this->args);
		}

		abstract public function html(&$data);

		public function column()	{ return $this->column; }
		public function title()		{ return $this->title; }
		public function name()		{ return $this->column; }
		public function set_title($t)	{ $this->title = $t; }

		public function set_tableview(&$tableview)
		{
			$this->tableview = $tableview;
		}

		protected $column;
		protected $title;
		protected $args;
		protected $tableview;
	}

	/**
	 * TextTableViewColumn takes a third parameter: maximal string length
	 */
	class TextTableViewColumn extends TableViewColumn {
		public function html(&$data)
		{
			$str = strip_tags($data[$this->column]);
			if($ml = $this->args[0])
				return substr($str, 0, $ml).(strlen($str)>$ml?'&hellip;':'');
			return $data[$this->column];
		}
	}

	class BoolTableViewColumn extends TableViewColumn {
		public function html(&$data)
		{
			return $data[$this->column]?'true':'false';
		}
	}

	/**
	 * Example template:
	 * <a href="/overview/{item_id}">{item_title}</a>
	 */
	class TemplateTableViewColumn extends TableViewColumn {
		public function html(&$data)
		{
			if($this->vars === null) {
				$matches = array();
				preg_match_all('/\{([A-Za-z_0-9]+)}/', $this->args[0],
					$matches, PREG_PATTERN_ORDER);
				if(isset($matches[1]))
					$this->vars = $matches[1];
				foreach($this->vars as $v)
					$this->patterns[] = '/\{' . $v . '\}/';
			}

			$vals = array();
			foreach($this->vars as $v)
				$vals[] = $data[$v];

			return preg_replace($this->patterns, $vals, $this->args[0]);
		}

		protected $vars = null;
		protected $patterns = null;
	}

	/**
	 * third parameter: strftime(3)-formatted string
	 */
	class DateTableViewColumn extends TableViewColumn {
		public function html(&$data)
		{
			if($this->fmt === null) {
				if(isset($this->args[0]) && $this->args[0])
					$this->fmt = $this->args[0];
				else
					$this->fmt = '%d.%m.%Y : %H:%M';
			}
			return strftime($this->fmt, $data[$this->column]);
		}

		protected $fmt = null;
	}

	/**
	 * pass a callback instead of a field name
	 */
	class CallbackTableViewColumn extends TableViewColumn {
		public function html(&$data)
		{
			$method = $this->column;
			return call_user_func($method, $data);
		}
	}

	/**
	 * type hint for DBTableView
	 */
	abstract class NoDataTableViewColumn extends TableViewColumn {
		public function title()
		{
			return null;
		}
	}

	class CmdsTableViewColumn extends NoDataTableViewColumn {
		public function html(&$data)
		{
			$id = $data[$this->column];
			$gid = guardToken('delete');
			$html =<<<EOD
<a href="{$this->title}_edit/$id"><img src="/images/icons/database_edit.png" alt="edit" /></a>
<a href="{$this->title}_delete/$id" onclick="if(confirm('Really delete?')){this.href+='?guard=$gid';}else{this.parentNode.parentNode.onclick();return false;}">
	<img src="/images/icons/database_delete.png" alt="delete" />
</a>
EOD;
			return $html;
		}

		public function name()
		{
			return '__cmd_'.$this->column;
		}
	}

	/**
	 * pass a DBObject class as third parameter
	 */
	class DBTableViewColumn extends TableViewColumn {
		public function html(&$data)
		{
			if($this->db_class===null)
				$this->db_class = $this->args[0];
			$val = $data[$this->column];
			if($this->db_data===null) {
				$doc = DBOContainer::find($this->db_class);
				foreach($doc as $id => &$obj)
					$this->db_data[$id] = $obj->title();
			}

			if(is_array($val)) {
				if(isset($val[$this->column])) {
					if(isset($val[$this->args[1]])) {
						$key = $val[$this->args[1]];
						if(isset($this->db_data[$key]))
							return $this->db_data[$key];
					}
					return null;
				}
				$keys = array_keys($val);
				$vals = array();
				foreach($keys as $key)
					if(isset($this->db_data[$key]))
						$vals[] = $this->db_data[$key];
				return implode(', ', $vals);
			}

			if(!isset($this->db_data[$val]))
				return null;
			return $this->db_data[$val];
		}

		protected $db_data = null;
		protected $db_class = null;
	}

?>
