<?php
	class CtorInvoker {
		private static $_map = [];
		public static function invoke($className,$args = null) {
			$r = null;
			if (array_key_exists($className,self::$_map)) {
				$r = self::$_map[$className];
			} else {
				$r = new ReflectionClass($className);
				self::$_map[$className] = $r;
			}
			return $args !== null ? $r->newInstanceArgs($args) : $r->newInstance();
		}
	}

	class UtcDateTime implements JsonSerializable {
		const STR_REGEX = "/^(\\d{4})(\\d{2})(\\d{2})T(\\d{2})(\\d{2})(\\d{2})Z$/";
		public $iso8601 = null;
		public $year = null;
		public $month = null;
		public $day = null;
		public $hour = null;
		public $minute = null;
		public $second = null;
		public $unix = null;
		public function __construct($str) {
			if (is_string($str)) {
				$this->iso8601 = $str;
				$matches = [];
				if (preg_match(self::STR_REGEX,$str,$matches) === 1) {
					$this->year = intval($matches[1]);
					$this->month = intval($matches[2]);
					$this->day = intval($matches[3]);
					$this->hour = intval($matches[4]);
					$this->minute = intval($matches[5]);
					$this->second = intval($matches[6]);
					$this->validateFields();
					$this->buildUnixTime();
				} else {
					throw new InvalidArgumentException("String did not match regex \"".self::STR_REGEX."\".");
				}
			} else {
				throw new InvalidArgumentException("Must pass a string.");
			}
		}
		public static function compare(UtcDateTime $a,UtcDateTime $b) {
			$res = 0;
			if ($a->unix < $b->unix) {
				$res = -1;
			} elseif ($a->unix > $b->unix) {
				$res = 1;
			}
			return $res;
		}
		private function validateFields() {
			$this->validateRange($this->month,1,12,"Month");
			switch ($this->month) {
				case 2:
					// Leap year rules
					$endDay = 28;
					if ($this->year%400 === 0 || ($this->year%4 === 0 && $this->year%100 !== 0)) {
						$endDay = 29;
					}
					$this->validateRange($this->day,1,30,"Day in month ".$this->month." in year ".$this->year);
					break;
				case 4:
				case 6:
				case 9:
				case 11:
					$this->validateRange($this->day,1,30,"Day in month ".$this->month);
					break;
				default:
					$this->validateRange($this->day,1,31,"Day in month ".$this->month);
					break;
			}
			$this->validateRange($this->hour,0,24,"Hour");
			$this->validateRange($this->minute,0,60,"Minute");
			$this->validateRange($this->second,0,60,"Second");
		}
		private function validateRange(&$n,$lower,$upper,$name) {
			if ($n < $lower || $n > $upper) {
				throw new InvalidArgumentException($name." must be in range [".$lower.", ".$upper."].");
			}
		}
		private function buildUnixTime() {
			$this->unix = gmmktime($this->hour,$this->minute,$this->second,$this->month,$this->day,$this->year);
		}
		public function jsonSerialize() {
			return $this->iso8601;
		}
		public function __toString() {
			return $this->iso8601;
		}
	}

	abstract class SqlQueryBuilder {
		const IDENT_REGEX = "/[^a-zA-Z0-9\\\$_]/";
		protected function isValidIdentifier(&$ident) {
			if (preg_match(self::IDENT_REGEX,$ident)) {
				throw new InvalidArgumentException("Identifier \"".$ident."\" contains forbidden characters (permitted: A-Z, a-z, 0-9, $, _).");
			}
		}
		protected function validateColumnName(&$column) {
			if (is_string($column)) {
				if (strlen($column) > 0) {
					$this->isValidIdentifier($column);
				} else {
					throw new InvalidArgumentException("Column cannot be the empty string.");

				}
			} else {
				throw new InvalidArgumentException("Column must be a string.");
			}
		}
		protected function validateTableName(&$table) {
			if (is_string($table)) {
				if (strlen($table) > 0) {
					$this->isValidIdentifier($column);
				} else {
					throw new InvalidArgumentException("Table cannot be the empty string.");
				}
			} else {
				throw new InvalidArgumentException("Table must be a string.");
			}
		}
		protected function validateSet(&$set,$canBeZeroLength = false) {
			if (is_array($set)) {
				$count = count($set);
				if ($count < 0 || (!$canBeZeroLength && $count <= 0)) {
					throw new InvalidArgumentException("There must be at least one set member.");
				}
			} else {
				throw new InvalidArgumentException("Set must be an array.");
			}

		}
		protected function validateColumnSet(&$columns) {
			$this->validateSet($columns);
			foreach ($columns as $column) {
				if (is_array($column)) {
					$count = count($column);
					if ($count === 2) {
						$this->validateColumnName($column[0]);
						$this->validateColumnName($column[1]);
					} elseif ($count === 3) {
						$this->validateTableName($column[0]);
						$this->validateColumnName($column[1]);
						$this->validateColumnName($column[2]);
					} else {
						throw new InvalidArgumentException("Column alias pair must have length 2 or 3.");
					}
				} else {
					$this->validateColumnName($column);
				}
			}
		}
		protected function validateTableSet(&$tables) {
			$this->validateSet($tables,true);
			foreach ($tables as $table) {
				$this->validateTableName($table);
			}
		}
		protected function formatColumn(&$col,$twoJoiner,$threeJoiner = null) {
			if (is_array($col)) {
				if (count($col) === 2) {
					$col = implode($twoJoiner,$col);
				} elseif ($threeJoiner !== null) {
					$col = $col[0].$threeJoiner.$col[1].$twoJoiner.$col[2];
				}
			}
		}
		abstract public function getDb();
		abstract public function build();
		public function query() {
			return $this->getDb()->query($this);
		}
	}

	class DatabaseQueryException extends Exception {
		public function __construct(&$c,&$query) {
			parent::__construct("MySQL Error ".$c->errno.": ".$c->error."<br />Query string: ".$query,0,null);
		}
		public function __toString() {
			return __CLASS__.": [".$this->code."]: ".$this->message."\n";
		}
	}

	class Database {
		const AUTHENT = "Authent";
		const AUTHENT_USERNAME = "username";
		const AUTHENT_PASSWORD = "password";
		const PRODUCTS = "Products";
		const PRODUCTS_ID = "id";
		const PRODUCTS_NAME = "name";
		const PRODUCTS_VALUE = "value";
		const SALES = "Sales";
		const SALES_ID = "id";
		const SALES_PRODUCT = "product";
		const SALES_QUANTITY = "quantity";
		const SALES_DATETIME = "dateTime";
		private $_c = null;
		public function __construct() {
			$this->_c = new mysqli("127.0.0.1","dp2","","dp2");
			if ($this->_c->connect_error) {
				throw new UnexpectedValueException("MySQL Error ".$this->_c->connect_errno.": ".$this->_c->connect_error);
			}
			$this->createAuthentTable();
			$this->createProductTable();
			$this->createSalesTable();
		}
		public function __destruct() {
			$this->_c->close();
		}
		public static function isValidColumnName($colName) {
			$res = false;
			switch ($colName) {
				case self::PRODUCTS_ID:
				case self::PRODUCTS_NAME:
				case self::PRODUCTS_VALUE:
				case self::SALES_ID:
				case self::SALES_PRODUCT:
				case self::SALES_QUANTITY:
				case self::SALES_DATETIME:
					$res = true;
					break;
			}
			return $res;
		}
		public static function getColumnType($key) {
			$res = null;
			switch ($key) {
				case self::PRODUCTS_ID:
				case self::PRODUCTS_VALUE:
				case self::SALES_ID:
				case self::SALES_PRODUCT:
				case self::SALES_QUANTITY:
					$res = "int";
					break;
				case self::AUTHENT_USERNAME:
				case self::AUTHENT_PASSWORD:
				case self::PRODUCTS_NAME:
					$res = "string";
					break;
				case self::SALES_DATETIME:
					$res = "datetime";
					break;
			}
			return $res;
		}
		public static function formatValue($key,$value) {
			if ($value !== null) {
				$type = self::getColumnType($key);
				switch ($type) {
					case "int":
						$value = floatval($value); // PHP ints are only 32-bit but MySQL ints can be 64-bit
						break;
					case "string":
						$value = strval($value); // Should do nothing, but just in case someone passes an int for some reason
						break;
					case "datetime":
						$value = new UtcDateTime($value);
						break;
					default:
						throw new UnexpectedValueException("Column \"".$key."\" type is undefined.");
				}
			}
			return $value;
		}
		private function createAuthentTable() {
			$query = "CREATE TABLE IF NOT EXISTS ".self::AUTHENT." ("
				.self::AUTHENT_USERNAME." VARCHAR(32) NOT NULL PRIMARY KEY,"
				.self::AUTHENT_PASSWORD." VARCHAR(128) NOT NULL"
				.")";
			$this->queryRaw($query);
		}
		private function createProductTable() {
			$query = "CREATE TABLE IF NOT EXISTS ".self::PRODUCTS." ("
				.self::PRODUCTS_ID." INT NOT NULL AUTO_INCREMENT PRIMARY KEY,"
				.self::PRODUCTS_NAME." VARCHAR(64) NOT NULL,"
				.self::PRODUCTS_VALUE." BIGINT UNSIGNED NOT NULL"  // In cents
				.")";
			$this->queryRaw($query);
		}
		private function createSalesTable() {
			$query = "CREATE TABLE IF NOT EXISTS ".self::SALES." ("
				.self::SALES_ID." INT NOT NULL AUTO_INCREMENT PRIMARY KEY,"
				.self::SALES_PRODUCT." INT NOT NULL,"
				.self::SALES_QUANTITY." INT NOT NULL,"
				.self::SALES_DATETIME." CHAR(16) NOT NULL,"
				."CONSTRAINT fk_Product FOREIGN KEY (".self::SALES_PRODUCT.") REFERENCES ".self::PRODUCTS."(".self::PRODUCTS_ID.")"
				.")";
			$this->queryRaw($query);
		}
		private function queryRaw(&$query) {
			$result = $this->_c->query($query);
			if ($result !== false) {
				$res = null;
				if ($result !== true) {
					$res = $this->resultToJson($result);
					$result->free();
				}
				return $res;
			} else {
				throw new DatabaseQueryException($this->_c,$query);
			}
		}
		private function resultToJson($result) {
			$json = [];
			$resRow = null;
			while ($row = $result->fetch_assoc()) {
				$resRow = [];
				foreach ($row as $key => &$value) {
					$resRow[$key] = self::formatValue($key,$value);
				}
				$json[] = $resRow;
			}
			if (count($json) === 1) {
				$json = $json[0];
			}
			return $json;
		}
		public function escapeString($str) {
			return "'".$this->_c->real_escape_string($str)."'";
		}
		public function query(SqlQueryBuilder &$builder) {
			$query = $builder->build();
			return $this->queryRaw($query);
		}
	}
?>