<?php
	require_once("db_core.php");
	require_once("db_build_select.php");
	require_once("api_filters.php");

	class ApiRequestCreationException extends Exception {
		public $subcode = null;
		public function __construct($msg,$subcode) {
			parent::__construct($msg,0,null);
			$this->subcode = $subcode;
		}
		public function __toString() {
			return __CLASS__.": [".$this->code."]: ".$this->message."\n";
		}
	}

	function array_peek(&$arr) {
		$count = count($arr);
		if ($count <= 0) {
			throw new InvalidArgumentException("Array must have elements.");
		}
		return $arr[$count - 1];
	}

	abstract class ApiRecord {
		const PRODUCT_KEY = "product";
		const QUANTITY_KEY = "quantity";
		const DATE_TIME_KEY = "dateTime";
		public $product = null;
		public $quantity = null;
		public $dateTime = null;
		protected function getNotFound(&$json) {
			$notFound = [];
			ApiRequest::verifyExists(self::PRODUCT_KEY,$json,$notFound);
			ApiRequest::verifyExists(self::QUANTITY_KEY,$json,$notFound);
			ApiRequest::verifyExists(self::DATE_TIME_KEY,$json,$notFound);
			return $notFound;
		}
		protected function setProduct(&$json,&$stack,$typeErrCode) {
			$this->product = $json[self::PRODUCT_KEY];
			if (!is_int($this->product)) {
				ApiRequest::createTypeError(self::PRODUCT_KEY,"integer",$this->product,$stack,$rangeErrCode);
			}
		}
		protected function setQuantity(&$json,&$stack,$typeErrCode,$rangeErrCode) {
			$this->quantity = $json[self::QUANTITY_KEY];
			if (!is_int($this->quantity)) {
				ApiRequest::createTypeError(self::QUANTITY_KEY,"integer",$this->quantity,$stack,$typeErrCode);
			} elseif ($this->quantity <= 0) {
				ApiRequest::createRangeError(self::QUANTITY_KEY,"1 or higher",$this->quantity,$stack,$rangeErrCode);
			}
		}
		protected function setDateTime(&$json,&$stack,$typeErrCode,$invalidErrCode) {
			$dateTime = $json[self::DATE_TIME_KEY];
			if (!is_string($dateTime)) {
				ApiRequest::createTypeError(self::DATE_TIME_KEY,"string",$dateTime,$stack,$typeErrCode);
			}
			try {
				$this->dateTime = new UtcDateTime($dateTime);
			} catch (Exception $e) {
				ApiRequest::createError("Date/Time: ".$e->getMessage(),$stack,$invalidErrCode);
			}
		}
	}

	abstract class ApiRequest {
		private static $_reqTypeMap = [];
		private static function createRequest(&$json,&$stack) {
			$res = null;
			if (self::isJsonObject($json)) {
				if (array_key_exists("type",$json)) {
					$type = $json["type"];
					if (is_string($type)) {
						foreach (self::$_reqTypeMap as $key => $ctor) {
							if ($key === $type) {
								$res = CtorInvoker::invoke($ctor,[$json,$stack]);
								break;
							}
						}
						if ($res === null) {
							self::createError("Undefined request type \"".$type."\".",$stack,1);
						}
					} else {
						self::createTypeError("type","string",$type,$stack,2);
					}
				} else {
					self::createUndefError("type",$stack,3);
				}
			} else {
				self::createNotObjectError(array_peek($stack),$stack,4);
			}
			return $res;
		}
		public static function registerReqType($reqTypeKey,$ctor) {
			if (array_key_exists($reqTypeKey,self::$_reqTypeMap)) {
				throw new InvalidArgumentException("Request type key \"".$reqTypeKey."\" is already defined.");
			}
			self::$_reqTypeMap[$reqTypeKey] = $ctor;
		}
		public static function isJsonArray(&$arr) {
			$res = is_array($arr);
			if ($res) {
				// Credit to http://stackoverflow.com/a/173479
				$res = array_keys($arr) === range(0,count($arr) - 1);
			}
			return $res;
		}
		public static function isJsonObject(&$obj) {
			return count($obj) > 0 ? !isset($obj[0]) : true;
		}
		public static function createError($msg,&$stack,$code) {
			$res = "Request creation error: ".$msg;
			if (count($stack) > 0) {
				$res .= "\nStack trace: [".implode(" -> ",$stack)."]";
			}
			throw new ApiRequestCreationException($res,$code);
		}
		public static function createTypeError($key,$expectedType,&$value,&$stack,$code) {
			self::createError("Key \"".$key."\" incorrect type: expected ".$expectedType.", was ".gettype($value).".",$stack,$code);
		}
		public static function createUndefError($key,&$stack,$code) {
			self::createError("Required key \"".$key."\" is undefined.",$stack,$code);
		}
		public static function createUndefSetError(&$keySet,&$stack,$code) {
			self::createError("Required keys [".implode(", ",$keySet)."] are undefined.",$stack,$code);
		}
		public static function createNotArrayError($key,&$stack,$code) {
			self::createError("Key \"".$key."\" value must be an array.",$stack,$code);
		}
		public static function createNotObjectError($key,&$stack,$code) {
			self::createError("Key \"".$key."\" value must be an object.",$stack,$code);
		}
		public static function createRangeError($key,$desc,&$value,&$stack,$code) {
			self::createError("Key \"".$key."\" out of range: expected ".$desc.", was ".$value.".",$stack,$code);
		}
		public static function authent(&$json,Database &$db) {
			$res = false;
			if (array_key_exists("authent",$json)) {
				$authent = $json["authent"];
				if (array_key_exists("username",$authent) && array_key_exists("password",$authent)) {
					$select = new SelectQueryBuilder($db);
					$where = $select->setColumnsWildcard()
						->setPrimaryTable(Database::AUTHENT)
						->where();
					$where->component()
						->column("username")
						->equals()
						->valueString($authent["username"])
						->parent()
						->cmbAnd()
						->component()
						->column("password")
						->equals()
						->valueString($authent["password"]);
					$result = $select->query();
					$res = count($result) > 0;
				}
			}
			return $res;
		}
		public static function create(&$json) {
			$res = null;
			$stack = [];
			if (self::isJsonObject($json)) {
				if (array_key_exists("requests",$json)) {
					$requests = $json["requests"];
					if (self::isJsonArray($requests)) {
						$res = [];
						$stack[] = "requests";
						$res2 = null;
						foreach ($requests as $i => $request) {
							$stack[] = $i;
							$res2 = self::createRequest($request,$stack);
							if ($res2 === null) {
								self::createError("Request creator returned null.",$stack,5);
							} else {
								$res[] = $res2;
							}
							array_pop($stack);
						}
					} else {
						self::createNotArrayError("requests",$stack,6);
					}
				} else {
					self::createUndefError("requests",$stack,7);
				}
			} else {
				self::createNotObjectError("ROOT",$stack,8);
			}
			return $res;
		}
		public static function verifyExists($key,&$json,&$notFound) {
			if (!array_key_exists($key,$json)) {
				$notFound[] = $key;	
			}
		}
		abstract public function invoke(Database &$db);
	}

	class ApiDataRetriever extends ApiRequest {
		private $_filter = null;
		public function __construct(&$json,&$stack) {
			if (array_key_exists("filter",$json)) {
				$stack[] = "filter";
				$filter = $json["filter"];
				$this->_filter = FilterMap::createFilter($filter,$stack);
				array_pop($stack);
			}
		}
		public function invoke(Database &$db) {
			$result = $db->getAllRows(true);
			if ($this->_filter !== null) {
				$res2 = [];
				foreach ($result as &$row) {
					if ($this->_filter->passes($row)) {
						$res2[] = $row;
					}
				}
				$result = $res2;
			}
			return $result;
		}
	}
?>