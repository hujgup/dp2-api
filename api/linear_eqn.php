<?php
	class LinearEquation {
		private $_m = null;
		private $_c = null;
		public function __construct($m,$c) {
			if (!is_numeric($m)) {
				throw new InvalidArgumentException("Gradient must be a number.");
			} elseif (!is_numeric($c)) {
				throw new InvalidArgumentException("Offset must be a number.");
			} elseif (is_nan($m)) {
				throw new InvalidArgumentException("Gradient cannot be NaN.");
			} elseif (is_nan($c)) {
				throw new InvalidArgumentException("Offset cannot be NaN.");
			}
			$this->_m = $m;
			$this->_c = $c;
		}
		private static function validatePointArray(&$points) {
			if ($points === null || !is_array($points)) {
				throw new InvalidArgumentException("Points must be an array.");
			}
		}
		private static function validatePoint(&$point) {
			if ($point === null || !is_array($point)) {
				throw new InvalidArgumentException("Points must be a 2D array.");
			} elseif (count($point) !== 2) {
				throw new InvalidArgumentException("Points must be a 2D array where each sub-array is of size 2.");
			}
		}
		public static function fit(&$points) {
			self::validatePointArray($points);
			$sumX = 0;
			$sumY = 0;
			$sumXSquared = 0;
			$sumXbyY = 0;
			foreach ($points as &$point) {
				self::validatePoint($point);
				$x = $point[0];
				$y = $point[1];
				$sumX += $x;
				$sumY += $y;
				$sumXSquared += $x*$x;
				$sumXbyY += $x*$y;
			}
			$count = count($points);
			$denom = ($count*$sumXSquared - $sumX*$sumX);
			if ($denom === 0) {
				throw new InvalidArgumentException("Linear regression requires at least two unique points and is undefined for a vertical line.");
			}
			$m = ($count*$sumXbyY - $sumX*$sumY)/$denom;
			$c = $sumY/$count - ($m*$sumX)/$count;
			if (is_nan($m) || is_nan($c)) {
				throw new InvalidArgumentException("Linear regression requires at least two unique points and is undefined for a vertical line.");
			}
			return new LinearEquation($m,$c);
		}
		public function getM() {
			return $this->_m;
		}
		public function getC() {
			return $this->_c;
		}
		public function invoke($x) {
			return $this->_m*$x + $this->_c;
		}
		public function getRSquared(&$points) {
			self::validatePointArray($points);
			$meanY = 0;
			$count = count($points);
			foreach ($points as &$point) {
				self::validatePoint($point);
				$meanY += $point[1]/$count;
			}
			$sumErrSquared = 0;
			$sumVarSquared = 0;
			foreach ($points as &$point) {
				$x = $point[0];
				$y = $point[1];
				$predictedY = $this->invoke($x);
				$err = $y - $predictedY;
				$sumErrSquared += $err*$err;
				$variation = $y - $meanY;
				$sumVarSquared += $variation*$variation;
			}
			return $sumVarSquared == 0 ? 1 : 1 - $sumErrSquared/$sumVarSquared;
		}
		public function jsonSerialize() {
			return [
				"m" => $this->_m,
				"c" => $this->_c
			];
		}
		public function __toString() {
			$res = null;
			if ($this->_m === 0) {
				$res = strval($this->_c);
			} else {
				$mNeg = $this->_m < 0;
				$cNeg = $this->_c < 0;
				if ($mNeg) {
					if ($cNeg) {
						$res = "-(".abs($this->_m)."x + ".abs($this->_c).")";
					} else {
						$res = $this->_c." - ".abs($this->_m)."x";
					}
				} else {
					$res = $this->_m."x ";
					if ($cNeg) {
						$res .= "- ".abs($this->_c);
					} else {
						$res .= "+ ".$this->_c;
					}
				}
			}
			return $res;
		}
	}
?>