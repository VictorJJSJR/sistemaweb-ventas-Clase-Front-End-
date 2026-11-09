<?php
	session_start();
	unset($_SESSION['passwordus']);
	if (empty($_SESSION['active'])) {
		header('Location: ../');
		exit;
	}
	require '../php/conexionbd.php';

	$rowsPerPage = 20;
	$rawPage = isset($_GET['pagina']) && is_string($_GET['pagina']) ? $_GET['pagina'] : '1';
	$page = ctype_digit($rawPage) ? max(1, (int) $rawPage) : 1;
	$errorMessage = '';
	$entries = [];
	$totalEntries = 0;
	$totalPages = 1;

	$countStatement = sqlsrv_query($conn, 'SELECT COUNT_BIG(*) AS total FROM dbo.historial_ventas');
	$countRow = $countStatement !== false ? sqlsrv_fetch_array($countStatement, SQLSRV_FETCH_ASSOC) : false;
	if ($countStatement !== false) {
		sqlsrv_free_stmt($countStatement);
	}
	if ($countRow === false || $countRow === null) {
		error_log(print_r(sqlsrv_errors(), true));
		$errorMessage = 'No fue posible consultar el historial. Revisa que la actualización de la base de datos esté aplicada.';
	} else {
		$totalEntries = (int) $countRow['total'];
		$totalPages = max(1, (int) ceil($totalEntries / $rowsPerPage));
		$page = min($page, $totalPages);
		$offset = ($page - 1) * $rowsPerPage;
		$sql = 'SELECT id, venta_id, accion, CONVERT(char(19), fecha_cambio, 120) AS fecha_cambio, '
			. 'usuario, CONVERT(nvarchar(4000), datos_anteriores) AS datos_anteriores, '
			. 'CONVERT(nvarchar(4000), datos_nuevos) AS datos_nuevos '
			. 'FROM dbo.historial_ventas ORDER BY id DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY';
		$statement = sqlsrv_query($conn, $sql, [$offset, $rowsPerPage]);
		if ($statement === false) {
			error_log(print_r(sqlsrv_errors(), true));
			$errorMessage = 'No fue posible cargar los movimientos del historial.';
		} else {
			while (true) {
				$entry = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC);
				if ($entry === null) {
					break;
				}
				if ($entry === false) {
					error_log(print_r(sqlsrv_errors(), true));
					$errorMessage = 'Ocurrió un error al leer el historial.';
					$entries = [];
					break;
				}
				$entries[] = $entry;
			}
			sqlsrv_free_stmt($statement);
		}
	}

	function escapeHistorial($value)
	{
		return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	function resumenDatosVenta($json)
	{
		if (!is_string($json) || $json === '') {
			return '—';
		}
		$data = json_decode($json, true);
		if (!is_array($data)) {
			return escapeHistorial($json);
		}
		$parts = [];
		if (array_key_exists('num_ventas', $data)) {
			$parts[] = 'Número de ventas: ' . escapeHistorial($data['num_ventas'] ?? '—');
		}
		if (array_key_exists('total_ventas', $data)) {
			$parts[] = 'Total: $' . escapeHistorial($data['total_ventas'] ?? '—');
		}
		if (array_key_exists('fecha', $data)) {
			$parts[] = 'Fecha: ' . escapeHistorial($data['fecha'] ?? '—');
		}
		return $parts === [] ? '—' : implode(' · ', $parts);
	}

	$previousUrl = 'historial.php?' . http_build_query(['pagina' => max(1, $page - 1)]);
	$nextUrl = 'historial.php?' . http_build_query(['pagina' => min($totalPages, $page + 1)]);
?>
<!doctype html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Historial de ventas | Control de Ventas</title>
	<link rel="icon" href="../images/clipboard.png">
	<link rel="stylesheet" href="../styles/vendor/bootstrap.min.css">
	<link rel="stylesheet" href="../styles/ventas.css">
	<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.13.0/css/all.css">
</head>
<body class="ventas-page">
	<nav class="ventas-navbar" aria-label="Navegación principal">
		<ul class="ventas-nav-list">
			<li><a href="index.php" class="ventas-nav-link">Ver ventas</a></li>
			<li><a href="registrar.php" class="ventas-nav-link">Registrar</a></li>
			<li><a href="modificar.php" class="ventas-nav-link">Modificar</a></li>
			<li><a href="eliminar.php" class="ventas-nav-link">Eliminar</a></li>
			<li><a href="historial.php" class="ventas-nav-link is-active" aria-current="page">Historial</a></li>
			<li class="ventas-logout-item"><a href="../php/salir.php" class="ventas-nav-link ventas-logout">Cerrar sesión</a></li>
		</ul>
	</nav>

	<main class="container ventas-main">
		<header class="ventas-heading">
			<p class="ventas-eyebrow">Control de ventas</p>
			<h1>Historial de cambios</h1>
			<p class="ventas-description">Consulta cuándo y quién registró, modificó o eliminó cada venta.</p>
		</header>

		<section class="ventas-results" aria-labelledby="history-title">
			<div class="ventas-results-heading">
				<div>
					<h2 id="history-title">Movimientos registrados</h2>
					<p class="ventas-result-count"><?php echo number_format($totalEntries); ?> movimientos. El historial se conserva aunque una venta se elimine.</p>
				</div>
				<a class="btn btn-outline-secondary ventas-button" href="index.php">Volver a ventas</a>
			</div>

			<?php if ($errorMessage !== '') { ?>
				<div class="alert alert-danger ventas-alert" role="alert"><?php echo escapeHistorial($errorMessage); ?></div>
			<?php } else { ?>
				<div class="ventas-table-card">
					<div class="table-responsive">
						<table class="table table-striped align-middle mb-0 ventas-table">
							<thead>
								<tr>
									<th scope="col">Fecha y hora</th>
									<th scope="col">Acción</th>
									<th scope="col">Registro</th>
									<th scope="col">Usuario</th>
									<th scope="col">Antes</th>
									<th scope="col">Después</th>
								</tr>
							</thead>
							<tbody>
								<?php if ($entries === []) { ?>
									<tr><td colspan="6" class="ventas-empty-cell"><div class="ventas-empty-state"><span class="ventas-empty-icon" aria-hidden="true"><i class="fas fa-history"></i></span><strong>Aún no hay movimientos</strong><span>Los nuevos cambios en ventas aparecerán aquí.</span></div></td></tr>
								<?php } else { ?>
									<?php foreach ($entries as $entry) { ?>
										<tr>
											<td><?php echo escapeHistorial($entry['fecha_cambio']); ?></td>
											<td><span class="badge bg-secondary"><?php echo escapeHistorial($entry['accion']); ?></span></td>
											<td><span class="ventas-id">#<?php echo escapeHistorial($entry['venta_id']); ?></span></td>
											<td><?php echo escapeHistorial($entry['usuario'] ?? '—'); ?></td>
											<td><p class="ventas-history-details"><?php echo resumenDatosVenta($entry['datos_anteriores'] ?? null); ?></p></td>
											<td><p class="ventas-history-details"><?php echo resumenDatosVenta($entry['datos_nuevos'] ?? null); ?></p></td>
										</tr>
									<?php } ?>
								<?php } ?>
							</tbody>
						</table>
					</div>
				</div>
				<?php if ($totalPages > 1) { ?>
					<nav class="ventas-pagination" aria-label="Paginación del historial">
						<span>Mostrando <?php echo number_format(($page - 1) * $rowsPerPage + 1); ?>–<?php echo number_format(min($page * $rowsPerPage, $totalEntries)); ?> de <?php echo number_format($totalEntries); ?></span>
						<div class="ventas-pagination-actions">
							<?php if ($page > 1) { ?><a class="btn btn-outline-secondary ventas-button" href="<?php echo escapeHistorial($previousUrl); ?>">Anterior</a><?php } else { ?><span class="btn btn-outline-secondary ventas-button is-disabled" aria-disabled="true">Anterior</span><?php } ?>
							<span class="ventas-page-indicator">Página <?php echo number_format($page); ?> de <?php echo number_format($totalPages); ?></span>
							<?php if ($page < $totalPages) { ?><a class="btn btn-outline-secondary ventas-button" href="<?php echo escapeHistorial($nextUrl); ?>">Siguiente</a><?php } else { ?><span class="btn btn-outline-secondary ventas-button is-disabled" aria-disabled="true">Siguiente</span><?php } ?>
						</div>
					</nav>
				<?php } ?>
			<?php } ?>
		</section>
	</main>
</body>
</html>
