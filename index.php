<?php
	$alert = '';
	session_start();
	unset($_SESSION['passwordus']);
	if (!empty($_SESSION['active'])) {
		header('Location: sistema/');
		exit;
	}

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$user = isset($_POST['username1']) && is_string($_POST['username1']) ? trim($_POST['username1']) : '';
		$psw = isset($_POST['password1']) && is_string($_POST['password1']) ? $_POST['password1'] : '';

		if ($user === '' || $psw === '') {
			$alert = 'Ingrese su usuario y contraseña';
		} else {
			require 'php/conexionbd.php';
			$query = sqlsrv_query($conn, 'SELECT TOP (1) id, username, [password] FROM dbo.usuarios WHERE username = ?', [$user]);
			$data = $query !== false ? sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC) : false;
			if ($query !== false) {
				sqlsrv_free_stmt($query);
			}

			if ($query === false) {
				error_log(print_r(sqlsrv_errors(), true));
				$alert = 'No fue posible validar el acceso. Intenta nuevamente.';
			} elseif ($data !== false && $data !== null) {
				$storedPassword = (string) $data['password'];
				$passwordInfo = password_get_info($storedPassword);
				$isHashedPassword = isset($passwordInfo['algoName']) && $passwordInfo['algoName'] !== 'unknown';
				$isValidPassword = $isHashedPassword
					? password_verify($psw, $storedPassword)
					: hash_equals($storedPassword, $psw);

				if ($isValidPassword) {
					if (!$isHashedPassword || password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
						$newPasswordHash = password_hash($psw, PASSWORD_DEFAULT);
						$updatePassword = sqlsrv_query(
							$conn,
							'UPDATE dbo.usuarios SET [password] = ? WHERE id = ?',
							[$newPasswordHash, (int) $data['id']]
						);
						if ($updatePassword === false) {
							error_log(print_r(sqlsrv_errors(), true));
						}
					}

					session_regenerate_id(true);
					$_SESSION['active'] = true;
					$_SESSION['idus'] = (int) $data['id'];
					$_SESSION['usernameus'] = (string) $data['username'];
					header('Location: sistema/');
					exit;
				}
				$alert = 'Usuario y/o contraseña incorrecto';
			} else {
				$alert = 'Usuario y/o contraseña incorrecto';
			}
		}
	}
?>
<!doctype html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Iniciar sesión | Control de Ventas</title>
	<link rel="icon" href="images/open-book.png">
	<link rel="stylesheet" href="styles/vendor/bootstrap.min.css">
	<link rel="stylesheet" href="styles/ventas.css">
	<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.13.0/css/all.css">
</head>
<body class="ventas-page ventas-login-page">
	<main class="ventas-login-shell">
		<section class="ventas-card ventas-login-card" aria-labelledby="login-title">
			<div class="ventas-card-heading">
				<div class="ventas-search-icon" aria-hidden="true"><i class="fas fa-chart-line"></i></div>
				<div>
					<p class="ventas-eyebrow">Control de ventas</p>
					<h1 id="login-title" class="ventas-login-title">Iniciar sesión</h1>
					<p class="ventas-login-description">Ingresa tus datos para continuar.</p>
				</div>
			</div>

			<form action="" method="POST" class="ventas-form">
				<div class="mb-3">
					<label for="username" class="form-label">Usuario</label>
					<div class="input-group">
						<span class="input-group-text" aria-hidden="true"><i class="fas fa-user"></i></span>
						<input class="form-control" id="username" type="text" name="username1" autocomplete="username" required>
					</div>
				</div>
				<div class="mb-3">
					<label for="password" class="form-label">Contraseña</label>
					<div class="input-group">
						<span class="input-group-text" aria-hidden="true"><i class="fas fa-lock"></i></span>
						<input class="form-control" id="password" type="password" name="password1" autocomplete="current-password" required>
					</div>
				</div>
				<?php if ($alert !== '') { ?>
					<div class="alert alert-danger ventas-alert" role="alert"><?php echo htmlspecialchars($alert, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
				<?php } ?>
				<div class="ventas-form-actions">
					<button class="btn btn-primary ventas-button" type="submit">
						<i class="fas fa-sign-in-alt me-2" aria-hidden="true"></i>Iniciar sesión
					</button>
				</div>
			</form>
		</section>
	</main>
</body>
</html>
