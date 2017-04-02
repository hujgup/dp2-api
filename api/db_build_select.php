<?php
	require_once("db_core.php");
	require_once("db_build_clauses.php");

	class SelectQueryBuilder extends SqlQueryBuilder {
		private $_db = null;
		private $_columns = null;
		private $_primaryTable = null;
		private $_joinTables = null;
		private $_joinOn = null;
		private $_whereClause = null;
		private $_joinOnExpected = 0;
		public function __construct(Database &$db) {
			$this->_db = $db;
		}
		public function setColumns($columns) {
			$this->validateColumnSet($columns);
			foreach ($columns as &$col) {
				$this->formatColumn($col," AS ",".");
			}
			$this->_columns = implode(",",$columns);
			return $this;
		}
		public function setColumnsWildcard() {
			$this->_columns = "*";
			return $this;
		}
		public function setPrimaryTable($table) {
			$this->validateTableName($table);
			$this->_primaryTable = $table;
			return $this;
		}
		public function setJoinTables($tables) {
			$this->validateTableSet($tables);
			if (count($tables) > 0) {
				$this->_joinTables = implode(",",$tables);
				$this->_joinOnExpected = count($tables);
			} else {
				$this->clearJoinTables();
			}
			return $this;
		}
		public function clearJoinTables() {
			$this->_joinTables = null;
			$this->_joinOnExpected = 0;
		}
		public function setJoinColumns($pairs) {
			if (count($pairs) === $this->_joinOnExpected) {
				foreach ($pairs as &$pair) {
					$this->validateColumnSet($pair);
					foreach ($pair as &$v) {
						if (is_array($v)) {
							$v = implode(".",$v);
						}
					}
					$pair = implode("=",$pair);
				}
				$this->_joinOn = $pairs;
			} else {
				throw new InvalidArgumentException("Expected ".$this->_joinOnExpected." join pairs, was ".count($pairs).".");
			}
		}
		public function where() {
			$this->_whereClause = new WhereClauseBuilder($this);
			return $this->_whereClause;
		}
		public function clearWhere() {
			$this->_whereClause = null;
			return $this;
		}
		public function getDb() {
			return $this->_db;
		}
		public function build() {
			if ($this->_columns === null) {
				throw new UnexpectedValueException("Columns were not set.");
			} elseif ($this->_primaryTable === null) {
				throw new UnexpectedValueException("Primary table was not set.");
			}
			$res = "SELECT ".$this->_columns." FROM ".$this->_primaryTable;
			if ($this->_joinTables !== null) {
				if ($this->_joinOn === null) {
					throw new UnexpectedValueException("Join tables were set but joining columns were not.");
				}
				$res .= " LEFT JOIN ".$this->_joinTables." ON (".implode(" AND ",$this->_joinOn).")";
			}
			if ($this->_whereClause !== null) {
				$res .= " ".$this->_whereClause->build();
			}
			return $res;
		}
	}
?>