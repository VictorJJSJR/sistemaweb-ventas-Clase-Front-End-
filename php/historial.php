<?php
	function obtenerVentaParaHistorial($conn, $idVenta)
	{
		$sql = 'SELECT id, num_ventas, CONVERT(varchar(40), total_ventas) AS total_ventas, '
			. 'CONVERT(char(10), fecha, 23) AS fecha FROM dbo.ventas WHERE id = ?';
		$statement = sqlsrv_query($conn, $sql, [(int) $idVenta]);
		if ($statement === false) {
			error_log(print_r(sqlsrv_errors(), true));
			return false;
		}

		$row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC);
		sqlsrv_free_stmt($statement);
		if ($row === false) {
			error_log(print_r(sqlsrv_errors(), true));
			return false;
		}
		if ($row === null) {
			return null;
		}

		return [
			'id' => (int) $row['id'],
			'num_ventas' => $row['num_ventas'] === null ? null : (int) $row['num_ventas'],
			'total_ventas' => $row['total_ventas'] === null ? null : number_format((float) $row['total_ventas'], 2, '.', ''),
			'fecha' => $row['fecha'] === null ? null : (string) $row['fecha'],
		];
	}

	function registrarHistorialVenta($conn, $accion, $idVenta, $datosAnteriores, $datosNuevos)
	{
		try {
			$jsonAnterior = $datosAnteriores === null
				? '{}'
				: json_encode($datosAnteriores, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
			$jsonNuevo = $datosNuevos === null
				? '{}'
				: json_encode($datosNuevos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
		} catch (JsonException $exception) {
			error_log('No se pudieron preparar los datos del historial: ' . $exception->getMessage());
			return false;
		}

		$usuarioId = isset($_SESSION['idus']) ? (int) $_SESSION['idus'] : 0;
		$usuario = isset($_SESSION['usernameus']) && $_SESSION['usernameus'] !== ''
			? (string) $_SESSION['usernameus']
			: 'desconocido';
		$sql = 'INSERT INTO dbo.historial_ventas '
			. '(venta_id, accion, usuario_id, usuario, datos_anteriores, datos_nuevos) '
			. 'VALUES (?, ?, ?, ?, ?, ?)';
		$statement = sqlsrv_query($conn, $sql, [(int) $idVenta, $accion, $usuarioId, $usuario, $jsonAnterior, $jsonNuevo]);
		if ($statement === false) {
			error_log(print_r(sqlsrv_errors(), true));
			return false;
		}

		sqlsrv_free_stmt($statement);
		return true;
	}
?>
