<?php
    $username = "bnt"; 
    $password = "cs410project";   
    $host = "cs410project.cukigpbmy72c.us-west-2.rds.amazonaws.com";
    $database="cs410project";
    
    $server = mysql_connect($host, $username, $password);
    $connection = mysql_select_db($database, $server);

    $myquery = "SELECT  * FROM  `cs410`";
    $query = mysql_query($myquery);
    
    if ( ! $query ) {
        echo mysql_error();
        die;
    }
    
    $data = array();
    
    for ($x = 0; $x < mysql_num_rows($query); $x++) {
        $data[] = mysql_fetch_assoc($query);
    }
    
    echo json_encode($data);     
     
    mysql_close($server);

?>

