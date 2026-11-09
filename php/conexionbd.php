<?php
	$serverName = "DESKTOP-NJOK690\\SQLSERVERPAQUETE";
	$connectionOptions = [
		"Database" => "sistemaweb",
		"CharacterSet" => "UTF-8",
		"Encrypt" => true,
		"TrustServerCertificate" => true
	];

	if (!function_exists('sqlsrv_connect')) {
		http_response_code(500);
		exit('Falta instalar y habilitar el controlador SQLSRV de PHP.');
	}

	$conn = sqlsrv_connect($serverName, $connectionOptions);

	if ($conn === false) {
		error_log(print_r(sqlsrv_errors(), true));
		http_response_code(500);
		exit('No se pudo conectar con SQL Server. Revisa el controlador y los permisos de Windows.');
	}
?>
