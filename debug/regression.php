<?php
	require_once("../api/linear_eqn.php");

	function test($f) {
		echo "<p>";
		try {
			echo $f();
		} catch (Exception $e) {
			echo $e->getMessage();
		}
		echo "</p>";
	}

	test(function() {
		// Not an array
		$ponts = 4;
		LinearEquation::fit($points);
		return "ERR: Expected exception, but none was thrown (not an array).";
	});
	test(function() {
		// Not a point array
		$points = [6,8,0];
		LinearEquation::fit($points);
		return "ERR: Expected exception, but none was thrown (not a point array).";
	});
	test(function() {
		// Not a 2D point
		$points = [[0,2],[9,2,1],[0]];
		LinearEquation::fit($points);
		return "ERR: Expected exception, but none was thrown (not a 2D point array).";
	});
	test(function() {
		// Not enough points
		$points = [[4,4]];
		LinearEquation::fit($points);
		return "ERR: Expected exception, but none was thrown (not enough points).";
	});
	test(function() {
		// Not enough unique points
		$points = [[3,9],[3,9],[3,9],[3,9],[3,9],[3,9]];
		LinearEquation::fit($points);
		return "ERR: Expected exception, but none was thrown (not enough unique points).";
	});
	test(function() {
		// Vertical line
		$points = [[7,4],[7,8]];
		LinearEquation::fit($points);
		return "ERR: Expected exception, but none was thrown (vertical line).";
	});
	test(function() {
		// Exact fit
		$points = [[2,5],[8,17]];
		// m = 12/6 = 2
		// c = 1
		$eqn = LinearEquation::fit($points);
		if ($eqn->getM() != 2) {
			return "ERR: Incorrect exact regression (m must be 2, but was ".$eqn->getM().").";
		} elseif ($eqn->getC() != 1) {
			return "ERR: Incorrect exact regression (c must be 1, but was ".$eqn->getC().").";
		}
		$r2 = $eqn->getRSquared($points);
		if ($r2 != 1) {
			return "ERR: Exact regression r^2 must be 1 (was ".$r2.").";
		}
	});
	test(function() {
		// Best fit
		$points = [[0,38],[1,47],[5,55],[-1,62],[6,48],[8,56],[10,48],[9,44],[9,43],[6,61],[9,56],[13,41],[12,56],[9,72],[11,72],[15,46],[20,62],[17,50],[17,52],[19,59],[20,67],[21,56],[21,52],[19,63],[23,61],[22,57],[24,77],[28,77],[25,77],[28,64]];
		$eqn = LinearEquation::fit($points);
		$m = round(100000*$eqn->getM());
		$c = round(100000*$eqn->getC());
		if ($m != 67942) {
			return "ERR: Incorrect best fit regression (m*10^5 must be 67947, was ".$m.").";
		} elseif ($c != 4765228) {
			return "ERR: Incorrect best fit regression (c*10^5 must be 4765228, was ".$m.").";
		}
		$r2 = round(100000*$eqn->getRSquared($points));
		if ($r2 != 26697) {
			return "ERR: Best fit r^2*10^5 must be 26697 (was ".$r2.").";
		}
	});
?>