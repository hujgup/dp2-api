<?php
	require_once("db_core.php");
	require_once("api_request.php");

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
				throw new InvalidArgumentException("Expected arg4 of ColumnValueFilter constructor to be callable, but was not.");
			}
			$this->_equator = $equator;
		}
		public function passes(&$row) {
			$eqn = $this->_equator; // PHP will think this is an instance method otherwise
			return $eqn($row[$this->_name],$this->_value);
		}
	}

	function std_compare($a,$b) {
		$res = 0;
		if ($a < $b) {
			$res = -1;
		} elseif ($a > $b) {
			$res = 1;
		}
		return $res;
	}

	class Bound {
		private $_value = null;
		private $_inclusive = null;
		private $_comparer = null;
		public function __construct($value,$inclusive,$comparer = null) {
			$this->_value = $value;
			$this->_inclusive = $inclusive;
			if ($comparer === null) {
				$comparer = "std_compare";
			} elseif (!is_callable($comparer)) {
				throw new InvalidArgumentException("Comparer must be callable.");
			}
			$this->_comparer = $comparer;
		}
		private function compare(&$a,&$b) {
			return call_user_func($this->_comparer,$a,$b);
		}
		public function getValue() {
			return $this->_value;
		}
		public function isInclusive() {
			return $this->_inclusive;
		}
		public function getComparer() {
			return $this->_comparer;
		}
		public function argIsAbove($arg) {
			$cmp = $this->compare($arg,$this->_value);
//			var_dump("above(".$arg.", ".$this->_value.") -> ".$cmp);
			return $this->_inclusive ? $cmp >= 0 : $cmp > 0;
		}
		public function argIsBelow($arg) {
			$cmp = $this->compare($arg,$this->_value);
//			var_dump("below(".$arg.", ".$this->_value.") -> ".$cmp);
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
		public function __toString() {
			$res = $this->_lower->isInclusive() ? "[" : "(";
			$res .= $this->_lower->getValue();
			$res .= ", ";
			$res .= $this->_upper->getValue();
			$res .= $this->_upper->isInclusive() ? "]" : ")";
			return $res;
		}
	}

	class ColumnRangeFilter implements Filter {
		private $_name = null;
		private $_range = null;
		public function __construct(&$name,&$json,&$stack) {
			$this->_name = $name;
			$type = Database::getColumnType($name);
			if ($type !== null) {
				if ($type !== "string") {
					$this->setupRange($type,$json,$stack);
				} else {
					ApiRequest::createError("Column \"".$name."\" has type ".$type.", which is not comparable.",$stack,33);
				}
			} else {
				throw new InvalidArgumentException("Column \"".$name."\" has no defined type.");
			}
		}
		private function verifyKeyExists($key,&$json,&$stack) {
			if (!array_key_exists($key,$json)) {
				ApiRequest::createUndefError($key,$stack,35);
			}
		}
		private function setupRange(&$type,&$json,&$stack) {
			$this->verifyKeyExists("low",$json,$stack);
			$this->verifyKeyExists("lowInclusive",$json,$stack);
			$this->verifyKeyExists("high",$json,$stack);
			$this->verifyKeyExists("highInclusive",$json,$stack);
			$low = Database::formatValue($this->_name,$json["low"]);
			$high = Database::formatValue($this->_name,$json["high"]);
			$lowInclusive = boolval($json["lowInclusive"]);
			$highInclusive = boolval($json["highInclusive"]);
			$cmp = $type === "datetime" ? [UtcDateTime::class,"compare"] : null;
			$this->_range = new Range(new Bound($low,$lowInclusive,$cmp),new Bound($high,$highInclusive,$cmp));
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
							ApiRequest::createError("Column name \"".$name."\" is not defined.",$stack,36);
						}
					} else {
						ApiRequect::createTypeError("name","string",$name,$stack,37);
					}
				} else {
					ApiRequest::createUndefError("name",$stack,38);
				}
			} else {
				ApiRequest::createNotObjectError(array_peek($stack),$stack,39);
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
					ApiRequest::createError("Type \"string\" is uncomparable (on column \"".$this->_name."\").",40);
					break;
				default:
					ApiRequest::createError("Column \"".$this->_name."\" type is undefined.",41);
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
					ApiRequest::createError("Column \"".$this->_name."\" type is undefined.",42);
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
				ApiRequest::createUndefError("value or inRange",$stack,43);
			}
		}
		public function passes(&$row) {
			return $this->_filter->passes($row);
		}
	}
	FilterMap::registerFilter("column","ColumnFilter");

	class LogicNotFilter implements Filter {
		private $_child = null;
		public function __construct(&$json,&$stack) {
			if (array_key_exists("child",$json)) {
				$stack[] = "child";
				$this->_child = FilterMap::createFilter($json["child"],$stack);
				array_pop($stack);
			} else {
				ApiRequest::createUndefError("child",$stack,44);
			}
		}
		public function passes(&$row) {
			return !$this->_child->passes($row);
		}
	}
	FilterMap::registerFilter("logicNot","LogicNotFilter");

	abstract class NaryLogicFilter implements Filter {
		private $_children = [];
		public function __construct(&$json,&$stack) {
			if (array_key_exists("children",$json)) {
				$children = $json["children"];
				if (is_array($children)) {
					$stack[] = "children";
					foreach ($children as $child) {
						$this->_children[] = FilterMap::createFilter($child,$stack);
					}
					array_pop($stack);
				} else {
					ApiRequest::createNotArrayError("children",$stack,46);
				}
			} else {
				ApiRequest::createUndefError("children",$stack,45);
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
			parent::__construct($json,$stack);
		}
		protected function evaluate($trueCount,$totalCount) {
			return $trueCount === $totalCount;
		}
	}
	FilterMap::registerFilter("logicAnd","LogicAndFilter");

	class LogicOrFilter extends NaryLogicFilter {
		public function __construct(&$json,&$stack) {
			parent::__construct($json,$stack);
		}
		protected function evaluate($trueCount,$totalCount) {
			return $trueCount > 0;
		}
	}
	FilterMap::registerFilter("logicOr","LogicOrFilter");

	class LogicNandFilter extends NaryLogicFilter {
		public function __construct(&$json,&$stack) {
			parent::__construct($json,$stack);
		}
		protected function evaluate($trueCount,$totalCount) {
			return $trueCount !== $totalCount;
		}
	}
	FilterMap::registerFilter("logicNand","LogicNandFilter");

	class LogicNorFilter extends NaryLogicFilter {
		public function __construct(&$json,&$stack) {
			parent::__construct($json,$stack);
		}
		protected function evaluate($trueCount,$totalCount) {
			return $trueCount === 0;
		}
	}
	FilterMap::registerFilter("logicNor","LogicNorFilter");

	class LogicXorFilter extends NaryLogicFilter {
		public function __construct(&$json,&$stack) {
			parent::__construct($json,$stack);
		}
		protected function evaluate($trueCount,$totalCount) {
			return $trueCount%2 !== 0;
		}
	}
	FilterMap::registerFilter("logicXor","LogicXorFilter");

	class LogicXnorFilter extends NaryLogicFilter {
		public function __construct(&$json,&$stack) {
			parent::__construct($json,$stack);
		}
		protected function evaluate($trueCount,$totalCount) {
			return $trueCount%2 === 0;
		}
	}
	FilterMap::registerFilter("logicXnor","LogicXnorFilter");

	abstract class NaryLogicPivotFilter extends NaryLogicFilter {
		private $_pivot = null;
		public function __construct(&$json,&$stack) {
			if (array_key_exists("pivot",$json)) {
				$this->_pivot = $json["pivot"];
				if (is_int($this->_pivot)) {
					parent::__construct($json,$stack);
				} else {
					ApiRequest::createTypeError("pivot","integer",$this->_pivot,$stack,32);
				}
			} else {
				ApiRequest::createUndefError("pivot",$stack,31);
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
	FilterMap::registerFilter("logicLt","LogicLtFilter");

	class LogicGtFilter extends NaryLogicPivotFilter {
		public function __construct(&$json,&$stack) {
			parent::__construct($json,$stack);
		}
		protected function inRange($trueCount) {
			return $trueCount > $this->getPivot();
		}
	}
	FilterMap::registerFilter("logicGt","LogicGtFilter");

	class LogicLeFilter extends NaryLogicPivotFilter {
		public function __construct(&$json,&$stack) {
			parent::__construct($json,$stack);
		}
		protected function inRange($trueCount) {
			return $trueCount <= $this->getPivot();
		}
	}
	FilterMap::registerFilter("logicLe","LogicLeFilter");

	class LogicGeFilter extends NaryLogicPivotFilter {
		public function __construct(&$json,&$stack) {
			parent::__construct($json,$stack);
		}
		protected function inRange($trueCount) {
			return $trueCount >= $this->getPivot();
		}
	}
	FilterMap::registerFilter("logicGe","LogicGeFilter");

	class FilterMap {
		private static $_filterMap = [];
		private function __construct() {
			// Pure static class
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
							ApiRequest::createError("Undefined filter type \"".$type."\".",$stack,27);
						}
					} else {
						ApiRequest::createTypeError("type","string",$type,$stack,28);
					}
				} else {
					ApiRequest::createUndefError("type",$stack,29);
				}
			} else {
				ApiRequest::createNotObjectError(array_peek($stack),$stack,19);
			}
			return $res;
		}
		public static function registerFilter($filterKey,$ctor) {
			if (array_key_exists($filterKey,self::$_filterMap)) {
				throw new InvalidArgumentException("Filter key \"".$filterKey."\" is already defined.");
			}
			self::$_filterMap[$filterKey] = $ctor;
		}
	}
?>