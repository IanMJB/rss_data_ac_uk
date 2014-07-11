<?php

$con = mysqli_connect("localhost", "ian", "password", "newsthing");

if(mysqli_connect_errno())
{
	echo "Failed to connect to MySQL: ".mysqli_connect_error()."\n";
}

mysqli_query($con, "INSERT INTO crawl_info VALUES ('University of Southampton', 'www.soton.ac.uk', 'ECS Master Race', 'www.ecs.soton.ac.uk', '2014-07-11 10:00:00')");

mysqli_close($con);
