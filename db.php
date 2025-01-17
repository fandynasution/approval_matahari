<?php
$serverName = "10.112.1.110\SQL2022"; //serverName\instanceName
$connectionInfo = array( "Database"=>"Matahari_Train", "UID"=>"mgr", "PWD"=>"mgr");
$conn = sqlsrv_connect( $serverName, $connectionInfo);

if( $conn ) {
     echo "Connection established.<br />";
}else{
     echo "Connection could not be established.<br />";
     die( print_r( sqlsrv_errors(), true));
}
?>