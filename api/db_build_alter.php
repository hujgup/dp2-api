<?php
	require_once("db_core.php");
	require_once("db_build_clauses.php");

	class UpdateQueryBuilder extends SqlQueryBuilder {
		private $_db = null;
		private $_table = null;
		private $_columns = null;
		private $_values = [];
		private $_whereClause = null;
		public function __construct(Database &$db) {
			$this->_db = $db;
		}
		public function setTable($table) {
			$this->validateTableName($table);
			$this->_table = $table;
			return $this;
		}
		public function setColumns($columns) {
			$this->validateColumnSet($columns);
			$this->_columns = $columns;
			return $this;
		}
		public function pushValues($values) {
			$this->validateSet($values);
			foreach ($values as &$value) {
				$this->_values[] = $this->formatValue($value);
			}
			return $this;
		}
		public function where() {
			$this->_whereClause = new WhereClauseBuilder($this);
			return $this->_whereClause;
		}
		public function getDb() {
			return $this->_db;
		}
		public function build() {
			if ($this->_table === null) {
				throw new UnexpectedValueException("Table was not set.");
			} elseif ($this->_columns === null) {
				throw new UnexpectedValueException("Columns were not set.");
			} elseif ($this->_whereClause === null) {
				throw new UnexpectedValueException("Where clause was not set.");
			}
			$countVals = count($this->_values);
			if ($countVals <= 0) {
				throw new UnexpectedValueException("Cannot insert zero values.");
			}
			$countCols = count($this->_columns);
			if ($countVals !== $countCols) {
				throw new UnexpectedValueException("Number of values to be insert must be the same as the number of columns defined (expected ".$countCols." but was ".$countVals.").");
			}
			$res = "UPDATE ".$this->_table." SET ".$this->_columns[0]."=".$this->_values[0];
			for ($i = 1; $i < $countCols; $i++) {
				$res .= ",".$this->_columns[$i]."=".$this->_values[$i];
			}
			$res .= " ".$this->_whereClause->build();
			return $res;
		}
	}
?>