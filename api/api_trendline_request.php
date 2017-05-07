<?php
	require_once("api_request.php");
	require_once("db_core.php");
	require_once("linear_eqn.php");

	class ApiTrendlineRequest extends ApiDataRetriever {
		const LINEAR_FIT = "linear";
		const Y_REVENUE = "revenue";
		const Y_UNITS_SOLD = "unitsSold";
		private static $_VALID_FITS = [self::LINEAR_FIT];
		private static $_VALID_Y = [self::Y_REVENUE,self::Y_UNITS_SOLD];
		private $_fit = null;
		private $_y = null;
		private $_cumul = null;
		private $_gran = null;
		public function __construct(&$json,&$stack) {
			if (array_key_exists("fit",$json)) {
				$this->_fit = $json["fit"];
				if (in_array($this->_fit,self::$_VALID_FITS)) {
					if (array_key_exists("y",$json)) {
						$this->_y = $json["y"];
						if (in_array($this->_y,self::$_VALID_Y)) {
							if (array_key_exists("cumulative",$json)) {
								$this->_cumul = $json["cumulative"];
								if (is_bool($this->_cumul)) {
									if (array_key_exists("granularity",$json)) {
										$this->_gran = $json["granularity"];
										if (gettype($this->_gran) !== "integer") {
											if ($this->_gran >= 1) {
												parent::__construct($json,$stack);
											} else {
												ApiRequest::createRangeError("granularity","[1, Infinity)",$this->_gran,$stack,51);
											}
										} else {
											ApiRequest::createTypeError("granularity","integer",$this->_gran,$stack,50);
										}
									} else {
										$this->_gran = 0;
										parent::__construct($json,$stack);
									}
								} else {
									ApiRequest::createTypeError("cumulative","boolean",$this->_cumul,53);
								}
							} else {
								ApiRequest::createUndefError("cumulative",$stack,52);
							}
						} else {
							ApiRequest::createRangeError("y","one of \{".implode(", ",self::$_VALID_Y)."\}",$this->_y,$stack,49);
						}
					} else {
						ApiRequest::createUndefError("y",$stack,48);
					}
				} else {
					ApiRequest::createRangeError("fit","one of \{".implode(", ",self::$_VALID_FITS)."\}",$this->_fit,$stack,47);
				}
			} else {
				ApiRequest::createUndefError("fit",$stack,46);
			}
		}
		private function getY(&$row) {
			$res = null;
			switch ($this->_y) {
				case self::Y_REVENUE:
					$res = intval($row[Database::PRODUCTS_VALUE])*intval($row[Database::SALES_QUANTITY]);
					break;
				case self::Y_UNITS_SOLD:
					$res = intval($row[Database::SALES_QUANTITY]);
					break;
				default:
					throw new InvalidStateException("Y value was modified after object construction.");
			}
			return $res;
		}
		private function getTimeBlock(UtcDateTime &$dt) {
			$res = $dt->getUnix();
			if ($this->_gran > 0) {
				// gran = 2: 0, 1 -> 0, 2, 3 -> 2, etc.
				$res = $res - $res%$this->_gran;
			}
			return $res;
		}
		private function linearFit(&$result) {
			$points = [];
			$minKey = 0;
			$maxKey = 0;
			foreach ($result as &$row) {
				$dt = $row[Database::SALES_DATETIME];
				$dt = $this->getTimeBlock($dt);
				if (array_key_exists($dt,$points)) {
					$points[$dt] += $this->getY($row);
				} else {
					$points[$dt] = $this->getY($row);
				}
				$minKey = min($minKey,$dt);
				$maxKey = max($maxKey,$dt);
			}
			if ($this->_gran > 0) {
				$last = $this->_cumul ? $points[$minKey] : 0;
				for ($i = $minKey + $this->_gran; $i < $maxKey; $i += $this->_gran) {
					if (!array_key_exists($i,$points)) {
						$add = $this->_cumul ? $points[$i] : 0;
						$points[$i] = $last;
						$last += $add;
					}
				}
			} elseif ($this->_cumul) {
				ksort($points);
				$last = 0;
				foreach ($points as &$value) {
					$value += $last;
					$last = $value;
				}
			}
			return LinearEquation::fitAssoc($points);
		}
		public function invoke(Database &$db) {
			$result = parent::invoke($db);
			switch ($this->_fit) {
				case self::LINEAR_FIT:
					$result = $this->linearFit($result);
					break;
				default:
					throw new InvalidStateException("Fit value was modified after object construction.");
			}
			return $result->jsonSerialize();
		}
	}
	ApiRequest::registerReqType("trendline","ApiTrendlineRequest");	
?>