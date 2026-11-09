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
	$id = isset($_GET['id']) && is_scalar($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : false;
	if ($id === false || $id < 1) {
		header('Location: modificar.php');
		exit;
	}
	$id = (int) $id;

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$num = isset($_POST['numv']) && is_string($_POST['numv']) ? trim($_POST['numv']) : '';
		$tot = isset($_POST['totv']) && is_string($_POST['totv']) ? trim($_POST['totv']) : '';
		$fec = isset($_POST['fecv']) && is_string($_POST['fecv']) ? trim($_POST['fecv']) : '';

		if ($num === '' || $tot === '' || $fec === '') {
			$alert = 'Todos los campos son obligatorios.';
		} elseif (!sqlsrv_begin_transaction($conn)) {
			error_log(print_r(sqlsrv_errors(), true));
			$alert = 'No fue posible iniciar la modificación. Intenta nuevamente.';
		} else {
			$datosAnteriores = obtenerVentaParaHistorial($conn, $id);
			$updated = $datosAnteriores !== false && $datosAnteriores !== null
				? sqlsrv_query($conn, 'UPDATE dbo.ventas SET num_ventas = ?, total_ventas = ?, fecha = ? WHERE id = ?', [$num, $tot, $fec, $id])
				: false;
			$updateSucceeded = $updated !== false && sqlsrv_rows_affected($updated) === 1;
			if (!$updateSucceeded && $updated === false) {
				error_log(print_r(sqlsrv_errors(), true));
			}
			if ($updated !== false) {
				sqlsrv_free_stmt($updated);
			}
			$datosNuevos = $updateSucceeded ? obtenerVentaParaHistorial($conn, $id) : false;
			$guardado = $datosNuevos !== false && $datosNuevos !== null
				&& registrarHistorialVenta($conn, 'MODIFICAR', $id, $datosAnteriores, $datosNuevos);

			if ($guardado && sqlsrv_commit($conn)) {
				header('Location: modificar.php?estado=modificado');
				exit;
			}
			sqlsrv_rollback($conn);
			$alert = 'No fue posible guardar los cambios y su historial. Revisa los datos e intenta nuevamente.';
		}
	}

	$sql = sqlsrv_query($conn, 'SELECT id, num_ventas, total_ventas, CONVERT(char(10), fecha, 23) AS fecha FROM dbo.ventas WHERE id = ?', [$id]);
	$data = $sql !== false ? sqlsrv_fetch_array($sql, SQLSRV_FETCH_ASSOC) : false;
	if ($sql !== false) {
		sqlsrv_free_stmt($sql);
	}
	if ($data === false || $data === null) {
		header('Location: modificar.php');
		exit;
	}
	$num = $data['num_ventas'];
	$tot = $data['total_ventas'];
	$fec = $data['fecha'];
?>
<!doctype html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Modificar venta | Control de Ventas</title>
	<link rel="icon" href="../images/modif.png">
	<link rel="stylesheet" href="../styles/vendor/bootstrap.min.css">
	<link rel="stylesheet" href="../styles/ventas.css">
	<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.13.0/css/all.css">
</head>
<body class="ventas-page">
	<nav class="ventas-navbar" aria-label="Navegación principal">
		<ul class="ventas-nav-list">
			<li><a href="index.php" class="ventas-nav-link">Ver ventas</a></li>
			<li><a href="registrar.php" class="ventas-nav-link">Registrar</a></li>
			<li><a href="modificar.php" class="ventas-nav-link is-active" aria-current="page">Modificar</a></li>
			<li><a href="eliminar.php" class="ventas-nav-link">Eliminar</a></li>
			<li><a href="historial.php" class="ventas-nav-link">Historial</a></li>
			<li class="ventas-logout-item"><a href="../php/salir.php" class="ventas-nav-link ventas-logout">Cerrar sesión</a></li>
		</ul>
	</nav>

	<main class="container ventas-main">
		<header class="ventas-heading">
			<p class="ventas-eyebrow">Control de ventas</p>
			<h1>Modificar venta</h1>
			<p class="ventas-description">Actualiza la información del registro seleccionado.</p>
		</header>

		<section class="ventas-card ventas-form-card" aria-labelledby="edit-title">
			<div class="ventas-card-heading">
				<div class="ventas-search-icon" aria-hidden="true"><i class="fas fa-pen"></i></div>
				<div>
					<h2 id="edit-title">Registro #<?php echo htmlspecialchars((string) $id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h2>
					<p>Modifica los campos que necesites y guarda los cambios.</p>
				</div>
			</div>

			<form method="POST" class="ventas-form">
				<div class="mb-3">
					<label for="idv" class="form-label">Número de registro</label>
					<div class="input-group">
						<span class="input-group-text" aria-hidden="true"><i class="fas fa-hashtag"></i></span>
						<input class="form-control" id="idv" type="text" name="idv" value="<?php echo htmlspecialchars((string) $id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" disabled>
					</div>
				</div>
				<div class="mb-3">
					<label for="numv" class="form-label">Número de ventas</label>
					<div class="input-group">
						<span class="input-group-text" aria-hidden="true"><i class="fas fa-clipboard-check"></i></span>
						<input class="form-control" id="numv" type="text" name="numv" value="<?php echo htmlspecialchars((string) $num, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
					</div>
				</div>
				<div class="mb-3">
					<label for="totv" class="form-label">Total en ventas</label>
					<div class="input-group">
						<span class="input-group-text" aria-hidden="true"><i class="fas fa-dollar-sign"></i></span>
						<input class="form-control" id="totv" type="text" name="totv" value="<?php echo htmlspecialchars((string) $tot, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
					</div>
				</div>
				<div class="mb-3">
					<label for="fecv" class="form-label">Fecha</label>
					<div class="input-group">
						<span class="input-group-text" aria-hidden="true"><i class="fas fa-calendar-alt"></i></span>
						<input class="form-control" id="fecv" type="text" name="fecv" placeholder="AAAA-MM-DD" value="<?php echo htmlspecialchars((string) $fec, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
					</div>
				</div>
				<?php if ($alert !== '') { ?>
					<div class="alert alert-warning ventas-alert" role="alert"><?php echo htmlspecialchars($alert, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
				<?php } ?>
				<div class="ventas-form-actions">
					<button class="btn btn-primary ventas-button" type="submit"><i class="fas fa-save me-2" aria-hidden="true"></i>Guardar cambios</button>
					<a class="btn btn-outline-secondary ventas-button" href="modificar.php">Regresar</a>
				</div>
			</form>
		</section>
	</main>
</body>
</html>
