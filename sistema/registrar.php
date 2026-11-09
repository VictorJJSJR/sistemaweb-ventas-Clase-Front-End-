<?php
	$alert = '';
	$alertType = 'success';
	session_start();
	unset($_SESSION['passwordus']);
	if (empty($_SESSION['active'])) {
		header('Location: ../');
		exit;
	}

	require '../php/conexionbd.php';
	require '../php/historial.php';

	if ($_SERVER['REQUEST_METHOD'] === 'POST'
		&& isset($_POST['numventas'], $_POST['totventas'], $_POST['fecventas'])
		&& $_POST['numventas'] !== '' && $_POST['totventas'] !== '' && $_POST['fecventas'] !== '') {
		$numv = $_POST['numventas'];
		$totv = $_POST['totventas'];
		$fecv = $_POST['fecventas'];

		if (!sqlsrv_begin_transaction($conn)) {
			error_log(print_r(sqlsrv_errors(), true));
			$alert = 'No fue posible iniciar el registro. Intenta nuevamente.';
			$alertType = 'danger';
		} else {
			$sql = 'INSERT INTO dbo.ventas (num_ventas, total_ventas, fecha) OUTPUT INSERTED.id VALUES (?, ?, ?)';
			$statement = sqlsrv_query($conn, $sql, [$numv, $totv, $fecv]);
			$newRecord = $statement !== false ? sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC) : false;
			if ($statement !== false && $newRecord === false) {
				error_log(print_r(sqlsrv_errors(), true));
			}
			if ($statement !== false) {
				sqlsrv_free_stmt($statement);
			}

			$idVenta = $newRecord !== false && $newRecord !== null ? (int) $newRecord['id'] : 0;
			$datosNuevos = $idVenta > 0 ? obtenerVentaParaHistorial($conn, $idVenta) : false;
			$guardado = $idVenta > 0 && $datosNuevos !== false
				&& $datosNuevos !== null
				&& registrarHistorialVenta($conn, 'CREAR', $idVenta, null, $datosNuevos);

			if ($guardado && sqlsrv_commit($conn)) {
				$alert = 'La venta se registró correctamente.';
		} else {
				sqlsrv_rollback($conn);
				if ($statement === false) {
					error_log(print_r(sqlsrv_errors(), true));
				}
				$alert = 'No fue posible guardar la venta y su historial. Revisa los datos e intenta nuevamente.';
				$alertType = 'danger';
			}
		}
	}
?>
<!doctype html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Registrar venta | Control de Ventas</title>
	<link rel="icon" href="../images/check.png">
	<link rel="stylesheet" href="../styles/vendor/bootstrap.min.css">
	<link rel="stylesheet" href="../styles/ventas.css">
	<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.13.0/css/all.css">
</head>
<body class="ventas-page">
	<nav class="ventas-navbar" aria-label="Navegación principal">
		<ul class="ventas-nav-list">
			<li><a href="index.php" class="ventas-nav-link">Ver ventas</a></li>
			<li><a href="registrar.php" class="ventas-nav-link is-active" aria-current="page">Registrar</a></li>
			<li><a href="modificar.php" class="ventas-nav-link">Modificar</a></li>
			<li><a href="eliminar.php" class="ventas-nav-link">Eliminar</a></li>
			<li><a href="historial.php" class="ventas-nav-link">Historial</a></li>
			<li class="ventas-logout-item"><a href="../php/salir.php" class="ventas-nav-link ventas-logout">Cerrar sesión</a></li>
		</ul>
	</nav>

	<main class="container ventas-main">
		<header class="ventas-heading">
			<p class="ventas-eyebrow">Control de ventas</p>
			<h1>Registrar venta</h1>
			<p class="ventas-description">Agrega los datos de una nueva venta.</p>
		</header>

		<section class="ventas-card ventas-form-card" aria-labelledby="register-title">
			<div class="ventas-card-heading">
				<div class="ventas-search-icon" aria-hidden="true"><i class="fas fa-plus"></i></div>
				<div>
					<h2 id="register-title">Datos de la venta</h2>
					<p>Completa los tres campos para guardar el registro.</p>
				</div>
			</div>

			<form action="" method="POST" class="ventas-form">
				<div class="mb-3">
					<label for="numventas" class="form-label">Número de ventas</label>
					<div class="input-group">
						<span class="input-group-text" aria-hidden="true"><i class="fas fa-clipboard-check"></i></span>
						<input class="form-control" id="numventas" type="text" name="numventas" placeholder="Ej. 20" autocomplete="off" required>
					</div>
				</div>
				<div class="mb-3">
					<label for="totventas" class="form-label">Total en ventas</label>
					<div class="input-group">
						<span class="input-group-text" aria-hidden="true"><i class="fas fa-dollar-sign"></i></span>
						<input class="form-control" id="totventas" type="text" name="totventas" placeholder="Ej. 12790.00" autocomplete="off" required>
					</div>
				</div>
				<div class="mb-3">
					<label for="fecventas" class="form-label">Fecha</label>
					<div class="input-group">
						<span class="input-group-text" aria-hidden="true"><i class="fas fa-calendar-alt"></i></span>
						<input class="form-control" id="fecventas" type="text" name="fecventas" placeholder="AAAA-MM-DD" autocomplete="off" required>
					</div>
				</div>
				<?php if ($alert !== '') { ?>
					<div class="alert alert-<?php echo htmlspecialchars($alertType, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> ventas-alert" role="alert">
						<?php echo htmlspecialchars($alert, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
					</div>
				<?php } ?>
				<div class="ventas-form-actions">
					<button class="btn btn-primary ventas-button" type="submit"><i class="fas fa-save me-2" aria-hidden="true"></i>Registrar venta</button>
					<a class="btn btn-outline-secondary ventas-button" href="index.php">Cancelar</a>
				</div>
			</form>
		</section>
	</main>
</body>
</html>
