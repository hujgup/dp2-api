<?php
	require_once("db_core.php");

	class WhereClauseComponent extends SqlQueryBuilder {
		private $_parent = null;
		private $_column = null;
		private $_operator = null;
		private $_value = null;
		private $_isStatement = null;
		public function __construct(SqlQueryBuilder &$parent) {
			$this->_parent = $parent;
		}
		public function parent() {
			return $this->_parent;
		}
		public function column($column) {
			$this->validateColumnName($column);
			$this->_column = $column;
			return $this;
		}
		public function equals() {
			$this->_operator = "=";
			$this->_isStatement = null;
			return $this;
		}
		public function notEquals() {
			$this->_operator = "!=";
			$this->_isStatement = null;
			return $this;
		}
		public function lessThan() {
			$this->_operator = "<";
			$this->_isStatement = null;
			return $this;
		}
		public function greaterThan() {
			$this->_operator = ">";
			$this->_isStatement = null;
			return $this;
		}
		public function lessThanOrEqualTo() {
			$this->_operator = "<=";
			$this->_isStatement = null;
			return $this;
		}
		public function greaterThanOrEqualTo() {
			$this->_operator = ">=";
			$this->_isStatement = null;
			return $this;
		}
		public function valueNumber($value) {
			if (is_numeric($value)) {
				$this->_value = $value;
			} else {
				throw new InvalidArgumentException("Value provided was not a number.");
			}
			$this->_isStatement = null;
			return $this;
		}
		public function valueString($value) {
			if (is_string($value)) {
				$this->_value = $this->getDb()->escapeString($value);
			} else {
				throw new InvalidArgumentException("Value provided was not a string.");
			}
			$this->_isStatement = null;
			return $this;
		}
		public function isNull() {
			$this->_isStatement = "IS NULL";
			return $this;
		}
		public function isNotNull() {
			$this->_isStatement = "IS NOT NULL";
			return $this;
		}
		public function getDb() {
			return $this->_parent->getDb();
		}
		public function build() {
			if ($this->_column === null) {
				throw new UnexpectedValueException("Column was not set.");
			} else {
				$res = $this->_column;
				if ($this->_isStatement !== null) {
					$res .= " ".$this->_isStatement;
				} else {
					if ($this->_operator === null) {
						throw new UnexpectedValueException("Operator was not set and is statement was not set.");
					} elseif ($this->_value === null) {
						throw new UnexpectedValueException("Value was not set and is statement was not set.");
					}
					$res .= $this->_operator.$this->_value;
				}
				return $res;
			}
		}
	}

	class CombinatorBuilder extends SqlQueryBuilder {
		private $_parent = null;
		private $_str = null;
		public function __construct(SqlQueryBuilder &$parent,$str) {
			$this->_parent = $parent;
			$this->_str = $str;
		}
		public function getDb() {
			return $this->_parent->getDb();
		}
		public function build() {
			return $this->_str;
		}
	}

	class WhereClauseBuilder extends SqlQueryBuilder {
		private $_parent = null;
		private $_components = [];
		private $_lastWasCombinator = false;
		public function __construct(SqlQueryBuilder &$parent) {
			$this->_parent = $parent;
		}
		private function validateCanCombine() {
			if ($this->_lastWasCombinator) {
				throw new UnexpectedValueException("Cannot put a combinator directly after another combinator - a component must be specified first.");
			}
		}
		public function parent() {
			return $this->_parent;
		}
		public function component() {
			if ($this->_lastWasCombinator || count($this->_components) === 0) {
				$c = new WhereClauseComponent($this);
				$this->_components[] = $c;
				$this->_lastWasCombinator = false;
				return $c;
			} else {
				throw new UnexpectedValueException("Cannot put a component directly after another component - a combinator must be specified first.");
			}
		}
		public function cmbAnd() {
			$this->validateCanCombine();
			$this->_components[] = new CombinatorBuilder($this,"AND");
			$this->_lastWasCombinator = true;
			return $this;
		}
		public function cmbOr() {
			$this->validateCanCombine();
			$this->_components[] = new CombinatorBuilder($this,"OR");
			$this->_lastWasCombinator = true;
			return $this;
		}
		public function getDb() {
			return $this->_parent->getDb();
		}
		public function build() {
			if ($this->_lastWasCombinator) {
				throw new UnexpectedValueException("Cannot end a where clause on a combinator.");
			} elseif (count($this->_components) <= 0) {
				throw new UnexpectedValueException("Where clause must have at least one component.");
			}
			$res = "WHERE";
			foreach ($this->_components as $c) {
				$res .= " ".$c->build();
			}
			return $res;
		}
	}
?>