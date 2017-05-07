<?php
	$c = new mysqli("127.0.0.1","dp2","","dp2");
	$query = "INSERT INTO Sales (product,quantity,dateTime) VALUES "
		."(4,6,'20620413T071815Z'),"
		."(5,1,'20630612T180756Z'),"
		."(1,3,'20630814T005608Z'),"
		."(2,1,'20631111T111100Z'),"
		."(1,5,'20720501T055512Z');";
	$c->query($query);
	$c->close();
?>