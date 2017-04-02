<?php
	require_once("db_core.php");

	class InsertQueryBuilder extends SqlQueryBuilder {
		private $_db = null;
		private $_table = null;
		private $_columns = null;
		private $_values = [];
		public function __construct(Database &$db) {
			$this->_db = $db;
		}
		private function formatValues(&$values) {
			$res = "(";
			$isFirst = true;
			foreach ($values as $value) {
				if ($isFirst) {
					$isFirst = false;
				} else {
					$res .= ",";
				}
				if (is_numeric($value)) {
					$res .= $value;
				} else {
					$res .= $this->_db->escapeString($value);
				}
			}
			$res .= ")";
			return $res;
		}
		public function setTable($table) {
			$this->validateTableName($table);
			$this->_table = $table;
			return $this;
		}
		public function setColumns($columns) {
			$this->validateColumnSet($columns);
			$this->_columns = "(".implode(",",$columns).")";
			return $this;
		}
		public function pushValues($values) {
			$this->validateSet($values);
			$this->_values[] = $this->formatValues($values);
			return $this;
		}
		public function getDb() {
			return $this->_db;
		}
		public function build() {
			if ($this->_table === null) {
				throw new UnexpectedValueException("Table was not set.");
			} elseif ($this->_columns === null) {
				throw new UnexpectedValueException("Columns were not set.");
			}
			$count = count($this->_values);
			if ($count <= 0) {
				throw new UnexpectedValueException("Cannot insert zero values.");
			}
			$res = "INSERT INTO ".$this->_table." ".$this->_columns." VALUES ".$this->_values[0];
			for ($i = 1; $i < $count; $i++) {
				$res .= ",".$this->_values[$i];
			}
			return $res;
		}
	}
?>