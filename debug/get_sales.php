<?php
	$c = new mysqli("127.0.0.1","dp2","","dp2");
	$query = "SELECT * FROM Sales;";
	$rs = $c->query($query);
	echo "<table border='1'><tr><th>id</th><th>product</th><th>quantity</th><th>dateTime</th></tr>";
	while ($rw = $rs->fetch_assoc()) {
		echo "<tr>";
		foreach ($rw as $k => $v) {
			echo "<td>".$v."</td>";
		}
		echo "</tr>";
	}
	echo "</table>";
?>