<?php
	require '../php/conexionbd.php';
	$alert = '';
	$notice = isset($_GET['estado']) && $_GET['estado'] === 'modificado'
		? 'La venta se modificó correctamente y el cambio quedó registrado en el historial.'
		: '';
	session_start();
	unset($_SESSION['passwordus']);
	if (empty($_SESSION['active'])) {
		header('location: ../');
	}
?>
<!doctype html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Modificar ventas | Control de Ventas</title>
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
			<h1>Modificar ventas</h1>
			<p class="ventas-description">Elige un registro para actualizar sus datos.</p>
		</header>
		<?php if ($notice !== '') { ?>
			<div class="alert alert-success ventas-alert" role="status"><?php echo htmlspecialchars($notice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
		<?php } ?>

		<section class="ventas-results" aria-labelledby="results-title">
			<div class="ventas-results-heading">
				<div>
					<h2 id="results-title">Registros disponibles</h2>
					<p class="ventas-result-count">Selecciona “Modificar” en la venta que quieras editar.</p>
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
								<th scope="col">Acción</th>
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
									<td>
										<a class="btn btn-outline-secondary ventas-button ventas-table-action" href="mod.php?id=<?php echo (int) $ventas['id']; ?>">
											<i class="fas fa-pen me-2" aria-hidden="true"></i>Modificar
										</a>
									</td>
								</tr>
							<?php } ?>
						</tbody>
					</table>
				</div>
			</div>
		</section>
	</main>
</body>
</html>
