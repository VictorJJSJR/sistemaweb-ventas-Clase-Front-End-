<?php
	session_start();
	unset($_SESSION['passwordus']);
	if (empty($_SESSION['active'])) {
		header('Location: ../');
		exit;
	}
	require '../php/conexionbd.php';
	require '../php/historial.php';

	$alert = '';
	$alertType = 'danger';
	if (isset($_GET['estado']) && $_GET['estado'] === 'eliminada') {
		$alert = 'La venta se eliminó correctamente y quedó registrada en el historial.';
		$alertType = 'success';
	}

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$idVenta = isset($_POST['idreg']) && is_string($_POST['idreg'])
			? filter_var($_POST['idreg'], FILTER_VALIDATE_INT)
			: false;
		if ($idVenta === false || $idVenta < 1) {
			$alert = 'Escribe un número de registro válido.';
		} elseif (!sqlsrv_begin_transaction($conn)) {
			error_log(print_r(sqlsrv_errors(), true));
			$alert = 'No fue posible iniciar la eliminación. Intenta nuevamente.';
		} else {
			$datosAnteriores = obtenerVentaParaHistorial($conn, $idVenta);
			$deleted = $datosAnteriores !== false && $datosAnteriores !== null
				? sqlsrv_query($conn, 'DELETE FROM dbo.ventas WHERE id = ?', [(int) $idVenta])
				: false;
			$deleteSucceeded = $deleted !== false && sqlsrv_rows_affected($deleted) === 1;
			if ($deleted === false) {
				error_log(print_r(sqlsrv_errors(), true));
			}
			if ($deleted !== false) {
				sqlsrv_free_stmt($deleted);
			}
			$guardado = $deleteSucceeded
				&& registrarHistorialVenta($conn, 'ELIMINAR', $idVenta, $datosAnteriores, null);

			if ($guardado && sqlsrv_commit($conn)) {
				header('Location: eliminar.php?estado=eliminada');
				exit;
			}
			sqlsrv_rollback($conn);
			$alert = $datosAnteriores === null
				? 'No se encontró una venta con ese número de registro.'
				: 'No fue posible eliminar la venta y guardar su historial. Intenta nuevamente.';
		}
	}
?>
<!doctype html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Eliminar venta | Control de Ventas</title>
	<link rel="icon" href="../images/cross.png">
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
			<li><a href="eliminar.php" class="ventas-nav-link is-active" aria-current="page">Eliminar</a></li>
			<li><a href="historial.php" class="ventas-nav-link">Historial</a></li>
			<li class="ventas-logout-item"><a href="../php/salir.php" class="ventas-nav-link ventas-logout">Cerrar sesión</a></li>
		</ul>
	</nav>

	<main class="container ventas-main">
		<header class="ventas-heading">
			<p class="ventas-eyebrow">Control de ventas</p>
			<h1>Eliminar venta</h1>
			<p class="ventas-description">Escribe el número de registro que deseas eliminar.</p>
		</header>

		<section class="ventas-card ventas-form-card" aria-labelledby="delete-title">
			<div class="ventas-card-heading">
				<div class="ventas-search-icon" aria-hidden="true"><i class="fas fa-trash-alt"></i></div>
				<div>
					<h2 id="delete-title">Eliminar por número de registro</h2>
					<p>Revisa la tabla para confirmar el número antes de continuar.</p>
				</div>
			</div>

			<form action="" method="POST" class="ventas-form" data-confirm-message="¿Confirmas que deseas eliminar la venta #{id}? Esta acción no se puede deshacer.">
				<div class="mb-3">
					<label for="idreg" class="form-label">Número de registro</label>
					<div class="input-group">
						<span class="input-group-text" aria-hidden="true"><i class="fas fa-hashtag"></i></span>
						<input class="form-control" id="idreg" type="number" min="1" step="1" placeholder="Ej. 3" name="idreg" autocomplete="off" required>
					</div>
				</div>
				<?php if ($alert !== '') { ?>
					<div class="alert alert-<?php echo htmlspecialchars($alertType, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> ventas-alert" role="alert">
						<?php echo htmlspecialchars($alert, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
					</div>
				<?php } ?>
				<div class="ventas-form-actions">
					<button class="btn btn-outline-danger ventas-button" type="submit"><i class="fas fa-trash-alt me-2" aria-hidden="true"></i>Eliminar venta</button>
					<a class="btn btn-outline-secondary ventas-button" href="index.php">Cancelar</a>
				</div>
			</form>
		</section>

		<section class="ventas-results" aria-labelledby="results-title">
			<div class="ventas-results-heading">
				<div>
					<h2 id="results-title">Ventas registradas</h2>
					<p class="ventas-result-count">Consulta el número de registro en la primera columna.</p>
				</div>
			</div>
			<div class="ventas-table-card">
				<div class="table-responsive">
					<table class="table table-striped align-middle mb-0 ventas-table">
						<thead>
							<tr>
								<th scope="col">Núm. registro</th>
								<th scope="col">Número de ventas</th>
								<th scope="col">Total en ventas</th>
								<th scope="col">Fecha</th>
							</tr>
						</thead>
						<tbody>
							<?php
								$sql = "SELECT id, num_ventas, total_ventas, CONVERT(char(10), fecha, 23) AS fecha FROM dbo.ventas ORDER BY id DESC";
								$result = sqlsrv_query($conn, $sql);
								while ($result !== false && ($ventas = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) !== null && $ventas !== false) {
							?>
								<tr>
									<td><span class="ventas-id">#<?php echo htmlspecialchars((string) $ventas['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span></td>
									<td><?php echo htmlspecialchars((string) $ventas['num_ventas'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
									<td class="ventas-amount">$<?php echo htmlspecialchars((string) $ventas['total_ventas'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
									<td><?php echo htmlspecialchars((string) $ventas['fecha'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
								</tr>
							<?php } ?>
						</tbody>
					</table>
				</div>
			</div>
		</section>
	</main>
	<script src="../scripts/ventas.js" defer></script>
</body>
</html>
