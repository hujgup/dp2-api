<?php
	$c = new mysqli("127.0.0.1","dp2","","dp2");
	$query = "SELECT * FROM Products;";
	$rs = $c->query($query);
	echo "<table border='1'><tr><th>id</th><th>name</th><th>value</th><th>value in $</th></tr>";
	while ($rw = $rs->fetch_assoc()) {
		echo "<tr>";
		foreach ($rw as $k => $v) {
			echo "<td>".$v."</td>";
		}
		echo "<td>$".(floatval($rw["value"])/100)."</td>";
		echo "</tr>";
	}
	echo "</table>";
?>