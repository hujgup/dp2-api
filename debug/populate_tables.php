<?php
	$c = new mysqli("127.0.0.1","dp2","","dp2");
	$query = "INSERT INTO Authent (username,password) VALUES ('feferi','0413');";
	$c->query($query);
	$query = "INSERT INTO Products (name,value) VALUES "
		// All the product values are in cents
		."('Chocolate',500),"
		."('Muffin',390),"
		."('Pen',299),"
		."('Globe',8999),"
		."('Lollipop',55),"
		."('Grand Piano',1499900),"
		."('US Foreign Debt',606000000000000),"
		."('Novelty Cake',30000),"
		."('BMW',10322500),"
		."('Bottled Water',350),"
		."('Poster',2299)";
	$c->query($query);
	$c->close();
?>