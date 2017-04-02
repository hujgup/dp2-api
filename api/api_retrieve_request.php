<?php
	require_once("api_request.php");
	require_once("db_core.php");
	require_once("db_build_select.php");

	interface Filter {
		public function passes(&$row);
	}

	class ColumnValueFilter implements Filter {
		private $_name = null;
		private $_value = null;
		private $_equator = null;
		public function __construct(&$name,&$value,&$stack,$equator = null) {
			$this->_name = $name;
			$this->_value = $value;
			if ($equator == null) {
				$equator = self::stdEquator;
			} elseif (!is_callable($equator)) {
				ApiRequest::createError("Expected arg4 of ColumnValueFilter constructor to be callable, but was not.");
			}
			$this->_equator = $equator;
		}
		public function passes(&$row) {
			$eqn = $this->_equator; // PHP will think this is an instance method otherwise
			return $eqn($row[$this->_name],$this->_value);
		}
	}

	class Bound {
		private $_value = null;
		private $_inclusive = null;
		private $_comparer = null;
		public function __construct($value,$inclusive,$comparer = null) {
			$this->_value = $value;
			$this->_inclusive = $inclusive;
			if ($comparer === null) {
				$comparer = self::stdCompare;
			} elseif (!is_callable($comparer)) {
				throw new InvalidArgumentException("Comparer must be callable.");
			}
			$this->_comparer = $comparer;
		}
		public static function stdCompare($a,$b) {
			$res = 0;
			if ($a < $b) {
				$res = -1;
			} elseif ($a > $b) {
				$res = 1;
			}
			return $res;
		}
		public function argIsAbove($arg) {
			$cmp = $this->_compare($arg,$this->_value);
			return $this->_inclusive ? $cmp >= 0 : $cmp > 0;
		}
		public function argIsBelow($arg) {
			$cmp = $this->_compare($arg,$this->_value);
			return $this->_inclusive ? $cmp <= 0 : $cmp < 0;
		}
	}

	class Range {
		private $_lower = null;
		private $_upper = null;
		public function __construct(Bound $lower,Bound $upper) {
			$this->_lower = $lower;
			$this->_upper = $upper;
		}
		public function argInRange($arg) {
			return $this->_lower->argIsAbove($arg) && $this->_upper->argIsBelow($arg);
		}
		public function argNotInRange($arg) {
			return !$this->argInRange($arg);
		}
	}

	class ColumnRangeFilter implements Filter {
		private $_range = null;
		public function __construct(&$name,&$json,&$stack) {
			$type = Database::getColumnType($name);
			if ($type !== null) {
				if ($type !== "string") {
					$this->setupRange($json,$stack);
				} else {
					ApiRequest::createError("Column \"".$name."\" has type ".$type.", which is not comparable.",$stack);
				}
			} else {
				ApiRequest::createError("Column \"".$name."\" has no defined type.",$stack);
			}
		}
		private function verifyKeyExists($key,&$json,&$stack) {
			if (!array_key_exists($key,$json)) {
				ApiRequest::createUndefError($key,$stack);
			}
		}
		private function setupRange(&$json,&$stack) {
			$this->verifyKeyExists("low",$json,$stack);
			$this->verifyKeyExists("lowInclusive",$json,$stack);
			$this->verifyKeyExists("high",$json,$stack);
			$this->verifyKeyExists("highInclusive",$json,$stack);
			$low = Database::formatValue($this->_name,$json["low"]);
			$high = Database::formatValue($this->_name,$json["high"]);
			$lowInclusive = boolval($json["lowInclusive"]);
			$highInclusive = boolval($json["highInclusive"]);
			$this->_range = new Range(new Bound($low,$lowInclusive),new Bound($high,$highInclusive));
		}
		public function passes(&$row) {
			return $this->_range->argInRange($row[$this->_name]);
		}
	}

	class ColumnFilter implements Filter {
		private $_name = null;
		private $_filter = null;
		public function __construct(&$json,&$stack) {
			if (ApiRequest::isJsonObject($json)) {
				if (array_key_exists("name",$json)) {
					$name = $json["name"];
					if (is_string($name)) {
						if (Database::isValidColumnName($name)) {
							$this->_name = $name;
							$this->buildCondition($json,$stack);
						} else {
							ApiRequest::createError("Column name \"".$name."\" is not defined.",$stack);
						}
					} else {
						ApiRequect::createTypeError("name","string",$name,$stack);
					}
				} else {
					ApiRequest::createUndefError("name",$stack);
				}
			} else {
				ApiRequest::createNotObjectError(array_peek($stack),$stack);
			}
		}
		private function getComparer() {
			$res = null;
			$type = Database::getColumnType($this->_name);
			switch ($type) {
				case "int":
					$res = function($a,$b) {
						return Bound::stdCompare($a,$b); // Can't assign static functions to variables because PHP thinks it's a class constant and can't find it
					};
					break;
				case "datetime":
					$res = function($a,$b) {
						return UtcDateTime::compare($a,$b);
					};
					break;
				case "string":
					ApiRequest::createError("Type \"string\" is uncomparable (on column \"".$this->_name."\").");
					break;
				default:
					ApiRequest::createError("Column \"".$this->_name."\" type is undefined.");
			}
			return $res;
		}
		private function getEquator() {
			$res = null;
			$type = Database::getColumnType($this->_name);
			switch ($type) {
				case "int":
				case "datetime":
					$cmp = $this->getComparer();
					$res = function($a,$b) use (&$cmp) {
						return $cmp($a,$b) === 0;
					};
					break;
				case "string":
					$res = function($a,$b) {
						return $a === $b;
					};
					break;
				default:
					ApiRequest::createError("Column \"".$this->_name."\" type is undefined.");
			}
			return $res;
		}
		private function buildCondition(&$json,&$stack) {
			if (array_key_exists("value",$json)) {
				$this->_filter = new ColumnValueFilter($this->_name,$json["value"],$stack,$this->getEquator());
			} elseif (array_key_exists("inRange",$json)) {
				$stack[] = "inRange";
				$this->_filter = new ColumnRangeFilter($this->_name,$json["inRange"],$stack,$this->getComparer());
				array_pop($stack);
			} else {
				ApiRequest::createUndefError("value or inRange",$stack);
			}
		}
		public function passes(&$row) {
			return $this->_filter->passes($row);
		}
	}
	ApiRetrieveRequest::registerFilter("column","ColumnFilter");

	class LogicNotFilter implements Filter {
		private $_child = null;
		public function __construct(&$json,&$stack) {
			if (array_key_exists("child",$json)) {
				$stack[] = "child";
				$this->_child = ApiRetrieveRequest::createFilter($json["child"],$stack);
				array_pop($stack);
			} else {
				ApiRequest::createUndefError("child",$stack);
			}
		}
		public function passes(&$row) {
			return !$this->_child->passes($row);
		}
	}
	ApiRetrieveRequest::registerFilter("logicNot","LogicNotFilter");

	abstract class NaryLogicFilter implements Filter {
		private $_children = [];
		public function __construct(&$json,&$stack) {
			if (array_key_exists("children",$json)) {
				$stack[] = "children";
				$children = $json["children"];
				foreach ($children as $child) {
					$this->_children[] = ApiRetrieveRequest::createFilter($child,$stack);
				}
				array_pop($stack);
			} else {
				ApiRequest::createUndefError("children",$stack);
			}
		}
		abstract protected function evaluate($trueCount,$totalCount);
		public function passes(&$row) {
			$trueCount = 0;
			foreach ($this->_children as $child) {
				if ($child->passes($row)) {
					$trueCount++;
				}
			}
			return $this->evaluate($trueCount,count($this->_children));
		}
	}

	class LogicAndFilter extends NaryLogicFilter {
		public function __construct(&$json,&$stack) {
			super::__construct($json,$stack);
		}
		protected function evaluate($trueCount,$totalCount) {
			return $trueCount === $totalCount;
		}
	}
	ApiRetrieveRequest::registerFilter("logicAnd","LogicAndFilter");

	class LogicOrFilter extends NaryLogicFilter {
		public function __construct(&$json,&$stack) {
			super::__construct($json,$stack);
		}
		protected function evaluate($trueCount,$totalCount) {
			return $trueCount > 0;
		}
	}
	ApiRetrieveRequest::registerFilter("logicOr","LogicOrFilter");

	class LogicNandFilter extends NaryLogicFilter {
		public function __construct(&$json,&$stack) {
			super::__construct($json,$stack);
		}
		protected function evaluate($trueCount,$totalCount) {
			return $trueCount !== $totalCount;
		}
	}
	ApiRetrieveRequest::registerFilter("logicNand","LogicNandFilter");

	class LogicNorFilter extends NaryLogicFilter {
		public function __construct(&$json,&$stack) {
			super::__construct($json,$stack);
		}
		protected function evaluate($trueCount,$totalCount) {
			return $trueCount === 0;
		}
	}
	ApiRetrieveRequest::registerFilter("logicNor","LogicNorFilter");

	class LogicXorFilter extends NaryLogicFilter {
		public function __construct(&$json,&$stack) {
			super::__construct($json,$stack);
		}
		protected function evaluate($trueCount,$totalCount) {
			return $trueCount%2 !== 0;
		}
	}
	ApiRetrieveRequest::registerFilter("logicXor","LogicXorFilter");

	class LogicXnorFilter extends NaryLogicFilter {
		public function __construct(&$json,&$stack) {
			super::__construct($json,$stack);
		}
		protected function evaluate($trueCount,$totalCount) {
			return $trueCount%2 === 0;
		}
	}
	ApiRetrieveRequest::registerFilter("logicXnor","LogicXnorFilter");

	abstract class NaryLogicPivotFilter extends NaryLogicFilter {
		private $_pivot = null;
		public function __construct(&$json,&$stack) {
			if (array_key_exists("pivot",$json)) {
				$this->_pivot = $json["pivot"];
				parent::__construct($json,$stack);
			} else {
				ApiRequest::createUndefError("pivot",$stack);
			}
		}
		abstract protected function inRange($trueCount);
		protected function getPivot() {
			return $this->_pivot;
		}
		protected function evaluate($trueCount,$totalCount) {
			return $this->inRange($trueCount);
		}
	}

	class LogicLtFilter extends NaryLogicPivotFilter {
		public function __construct(&$json,&$stack) {
			parent::__construct($json,$stack);
		}
		protected function inRange($trueCount) {
			return $trueCount < $this->getPivot();
		}
	}
	ApiRetrieveRequest::registerFilter("logicLt","LogicLtFilter");

	class LogicGtFilter extends NaryLogicPivotFilter {
		public function __construct(&$json,&$stack) {
			parent::__construct($json,$stack);
		}
		protected function inRange($trueCount) {
			return $trueCount > $this->getPivot();
		}
	}
	ApiRetrieveRequest::registerFilter("logicGt","LogicGtFilter");

	class LogicLeFilter extends NaryLogicPivotFilter {
		public function __construct(&$json,&$stack) {
			parent::__construct($json,$stack);
		}
		protected function inRange($trueCount) {
			return $trueCount <= $this->getPivot();
		}
	}
	ApiRetrieveRequest::registerFilter("logicLe","LogicLeFilter");

	class LogicGeFilter extends NaryLogicPivotFilter {
		public function __construct(&$json,&$stack) {
			parent::__construct($json,$stack);
		}
		protected function inRange($trueCount) {
			return $trueCount >= $this->getPivot();
		}
	}
	ApiRetrieveRequest::registerFilter("logicGe","LogicGeFilter");

	class ApiRetrieveRequest extends ApiRequest {
		private static $_filterMap = [];
		private $_filter = null;
		public function __construct(&$json,&$stack) {
			if (array_key_exists("filter",$json)) {
				$stack[] = "filter";
				$filter = $json["filter"];
				$this->_filter = self::createFilter($filter,$stack);
				array_pop($stack);
			}
		}
		public static function createFilter(&$json,&$stack) {
			$res = null;
			if (ApiRequest::isJsonObject($json)) {
				if (array_key_exists("type",$json)) {
					$type = $json["type"];
					if (is_string($type)) {
						$stack[] = $type;
						foreach (self::$_filterMap as $key => $ctor) {
							if ($key === $type) {
								$res = CtorInvoker::invoke($ctor,[$json,$stack]);
								break;
							}
						}
						array_pop($stack);
						if ($res === null) {
							self::createError("Undefined filter type \"".$type."\".",$stack);
						}
					} else {
						self::createTypeError("type","string",$type,$stack);
					}
				} else {
					ApiRequest::createUndefError("type",$stack);
				}
			} else {
				ApiRequest::createNotObjectError(array_peek($stack),$stack);
			}
			return $res;
		}
		public static function registerFilter($filterKey,$ctor) {
			if (array_key_exists($filterKey,self::$_filterMap)) {
				throw new InvalidArgumentException("Filter key \"".$filterKey."\" is already defined.");
			}
			self::$_filterMap[$filterKey] = $ctor;
		}
		public function invoke(Database &$db) {
			$select = new SelectQueryBuilder($db);
			$select->setColumns([[Database::SALES,Database::SALES_ID,Database::SALES_ID],Database::SALES_PRODUCT,Database::SALES_QUANTITY,Database::SALES_DATETIME,Database::PRODUCTS_NAME,Database::PRODUCTS_VALUE])
				->setPrimaryTable(Database::SALES)
				->setJoinTables([Database::PRODUCTS])
				->setJoinColumns([[[Database::SALES,Database::SALES_PRODUCT],[Database::PRODUCTS,Database::PRODUCTS_ID]]]);
			$result = $select->query();
//			var_dump($result);
			if ($this->_filter !== null) {
				$res2 = [];
				foreach ($result as $row) {
					if ($this->_filter->passes($row)) {
						$res2[] = $row;
					}
				}
				$result = $res2;
			}
			return $result;
		}
	}
	ApiRequest::registerReqType("retrieve","ApiRetrieveRequest");
?>