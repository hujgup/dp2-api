<?php
	// If you run this script, you'll need to call the API to recreate the tables, then run populate_tables.php to restore data
	$c = new mysqli("127.0.0.1","dp2","","dp2");
	$query = "DROP TABLE Sales;";
	$c->query($query);
	$query = "DROP TABLE Products;";
	$c->query($query);
	$query = "DROP TABLE Authent;";
	$c->query($query);
	$c->close();
?>