<?php
	$c = new mysqli("127.0.0.1","dp2","","dp2");
	$query = "SELECT * FROM Authent;";
	$rs = $c->query($query);
	echo "<table border='1'><tr><th>username</th><th>password</th></tr>";
	while ($rw = $rs->fetch_assoc()) {
		echo "<tr>";
		foreach ($rw as $k => $v) {
			echo "<td>".$v."</td>";
		}
		echo "</tr>";
	}
	echo "</table>";
?>