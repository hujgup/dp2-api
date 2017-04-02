<?php
	$c = new mysqli("127.0.0.1","dp2","","dp2");
	$query = "DROP TABLE Sales;";
	$c->query($query);
	$c->close();
?>