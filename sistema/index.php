<?php
	require '../php/conexionbd.php';

	session_start();
	unset($_SESSION['passwordus']);
	if (empty($_SESSION['active'])) {
		header('Location: ../');
		exit;
	}

	$fieldLabels = [
		'todos' => 'Todos los campos',
		'id' => 'Número de registro',
		'num_ventas' => 'Número de ventas',
		'total_ventas' => 'Total en ventas',
		'fecha' => 'Fecha',
	];
	$inputTypes = [
		'todos' => 'search',
		'id' => 'number',
		'num_ventas' => 'number',
		'total_ventas' => 'number',
		'fecha' => 'date',
	];
	$inputModes = [
		'todos' => 'search',
		'id' => 'numeric',
		'num_ventas' => 'numeric',
		'total_ventas' => 'decimal',
		'fecha' => 'numeric',
	];
	$inputSteps = [
		'id' => '1',
		'num_ventas' => '1',
		'total_ventas' => '0.01',
	];
	$inputMins = [
		'id' => '0',
		'num_ventas' => '-2147483648',
	];
	$inputMaxes = [
		'id' => '2147483647',
		'num_ventas' => '2147483647',
	];
	$placeholders = [
		'todos' => 'Ej. 2020, 6790 o 10',
		'id' => 'Ej. 3',
		'num_ventas' => 'Ej. 20',
		'total_ventas' => 'Ej. 12790.00',
		'fecha' => 'AAAA-MM-DD',
	];

	$rawField = isset($_GET['campo']) && is_string($_GET['campo']) ? $_GET['campo'] : 'todos';
	$field = array_key_exists($rawField, $fieldLabels) ? $rawField : 'todos';
	$rawSearch = $_GET['q'] ?? '';
	$searchValue = is_string($rawSearch) ? trim($rawSearch) : '';
	$errorMessage = '';
	$sales = [];
	$characterCount = preg_match_all('/./us', $searchValue, $matchedCharacters);
	$searchLength = function_exists('mb_strlen')
		? mb_strlen($searchValue, 'UTF-8')
		: ($characterCount === false ? strlen($searchValue) : $characterCount);

	if ($rawField !== $field) {
		$errorMessage = 'El tipo de búsqueda seleccionado no es válido.';
	} elseif (!is_string($rawSearch)) {
		$errorMessage = 'Escribe un valor de búsqueda válido.';
	} elseif ($searchLength > 100) {
		$errorMessage = 'La búsqueda no puede superar los 100 caracteres.';
	}

	$sql = 'SELECT id, num_ventas, total_ventas, CONVERT(char(10), fecha, 23) AS fecha FROM dbo.ventas';
	$params = [];

	if ($errorMessage === '' && $searchValue !== '') {
		switch ($field) {
			case 'id':
			case 'num_ventas':
				$allowNegative = $field === 'num_ventas';
				$integerPattern = $allowNegative ? '/^-?\d+$/D' : '/^\d+$/D';
				$isNegative = isset($searchValue[0]) && $searchValue[0] === '-';
				$integerDigits = $isNegative ? substr($searchValue, 1) : $searchValue;
				$normalizedDigits = ltrim($integerDigits, '0');
				if ($normalizedDigits === '') {
					$normalizedDigits = '0';
				}
				$integerLimit = $isNegative ? '2147483648' : '2147483647';
				if (
					!preg_match($integerPattern, $searchValue)
					|| strlen($normalizedDigits) > strlen($integerLimit)
					|| (strlen($normalizedDigits) === strlen($integerLimit) && strcmp($normalizedDigits, $integerLimit) > 0)
				) {
					$errorMessage = 'Ingresa un número entero válido para este filtro.';
					break;
				}
				$sql .= ' WHERE ' . $field . ' = ?';
				$params[] = (int) (($isNegative && $normalizedDigits !== '0' ? '-' : '') . $normalizedDigits);
				break;

			case 'total_ventas':
				if (!preg_match('/^-?\d{1,13}(?:[.,]\d{1,2})?$/D', $searchValue)) {
					$errorMessage = 'Ingresa un importe válido con hasta dos decimales.';
					break;
				}
				$sql .= ' WHERE total_ventas = CONVERT(decimal(15,2), ?)';
				$params[] = str_replace(',', '.', $searchValue);
				break;

			case 'fecha':
				$parsedDate = DateTime::createFromFormat('!Y-m-d', $searchValue);
				if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $searchValue) {
					$errorMessage = 'Selecciona una fecha válida.';
					break;
				}
				$sql .= ' WHERE fecha = CONVERT(date, ?, 23)';
				$params[] = $searchValue;
				break;

			case 'todos':
				$escapedSearch = str_replace(
					['\\', '%', '_', '['],
					['\\\\', '\\%', '\\_', '\\['],
					$searchValue
				);
				$likeValue = '%' . $escapedSearch . '%';
				$sql .= " WHERE (CONVERT(varchar(20), id) LIKE ? ESCAPE '\\'"
					. " OR CONVERT(varchar(20), num_ventas) LIKE ? ESCAPE '\\'"
					. " OR CONVERT(varchar(40), total_ventas) LIKE ? ESCAPE '\\'"
					. " OR CONVERT(char(10), fecha, 23) LIKE ? ESCAPE '\\')";
				$params = [$likeValue, $likeValue, $likeValue, $likeValue];
				break;
		}
	}

	$rowsPerPage = 20;
	$rawPage = isset($_GET['pagina']) && is_string($_GET['pagina']) ? $_GET['pagina'] : '1';
	$page = ctype_digit($rawPage) ? max(1, (int) $rawPage) : 1;
	$totalRecords = 0;
	$totalPages = 1;
	$sumSales = 0;
	$sumAmount = '0.00';
	$startRecord = 0;
	$endRecord = 0;
	$exportCsv = isset($_GET['exportar']) && $_GET['exportar'] === 'csv';

	$fromPosition = strpos($sql, ' FROM dbo.ventas');
	$fromWhereSql = $fromPosition === false ? ' FROM dbo.ventas' : substr($sql, $fromPosition);
	$summarySql = 'SELECT COUNT_BIG(*) AS total_registros, '
		. 'COALESCE(SUM(CONVERT(bigint, num_ventas)), 0) AS suma_num_ventas, '
		. 'COALESCE(SUM(total_ventas), CONVERT(decimal(38,2), 0)) AS total_importe'
		. $fromWhereSql;

	if ($errorMessage === '') {
		$summaryStatement = $params === []
			? sqlsrv_query($conn, $summarySql)
			: sqlsrv_query($conn, $summarySql, $params);
		$summaryRow = $summaryStatement !== false
			? sqlsrv_fetch_array($summaryStatement, SQLSRV_FETCH_ASSOC)
			: false;
		if ($summaryStatement !== false) {
			sqlsrv_free_stmt($summaryStatement);
		}

		if ($summaryRow === false || $summaryRow === null) {
			error_log(print_r(sqlsrv_errors(), true));
			$errorMessage = 'No fue posible consultar el resumen de ventas. Intenta nuevamente.';
		} else {
			$totalRecords = (int) $summaryRow['total_registros'];
			$sumSales = (int) $summaryRow['suma_num_ventas'];
			$sumAmount = (string) $summaryRow['total_importe'];
			$totalPages = max(1, (int) ceil($totalRecords / $rowsPerPage));
			$page = min($page, $totalPages);
			$offset = ($page - 1) * $rowsPerPage;
			$startRecord = $totalRecords === 0 ? 0 : $offset + 1;
			$endRecord = min($offset + $rowsPerPage, $totalRecords);

			if ($exportCsv) {
				$exportSql = $sql . ' ORDER BY id DESC';
				$exportStatement = $params === []
					? sqlsrv_query($conn, $exportSql)
					: sqlsrv_query($conn, $exportSql, $params);
				if ($exportStatement === false) {
					error_log(print_r(sqlsrv_errors(), true));
					$errorMessage = 'No fue posible preparar la descarga. Intenta nuevamente.';
				} else {
					header('Content-Type: text/csv; charset=UTF-8');
					header('Content-Disposition: attachment; filename="ventas-' . date('Ymd-His') . '.csv"');
					$output = fopen('php://output', 'wb');
					fwrite($output, "\xEF\xBB\xBF");
					fputcsv($output, ['Número de registro', 'Número de ventas', 'Total en ventas', 'Fecha'], ',', '"', '');
					while (($sale = sqlsrv_fetch_array($exportStatement, SQLSRV_FETCH_ASSOC)) !== null && $sale !== false) {
						fputcsv($output, [
							$sale['id'],
							$sale['num_ventas'],
							$sale['total_ventas'],
							$sale['fecha'],
						], ',', '"', '');
					}
					sqlsrv_free_stmt($exportStatement);
					fclose($output);
					exit;
				}
			}

			if ($errorMessage === '') {
				$pageSql = $sql . ' ORDER BY id DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY';
				$pageParams = array_merge($params, [$offset, $rowsPerPage]);
				$statement = sqlsrv_query($conn, $pageSql, $pageParams);
				if ($statement === false) {
					error_log(print_r(sqlsrv_errors(), true));
					$errorMessage = 'No fue posible consultar las ventas. Intenta nuevamente.';
				} else {
					while (true) {
						$sale = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC);
						if ($sale === null) {
							break;
						}
						if ($sale === false) {
							error_log(print_r(sqlsrv_errors(), true));
							$errorMessage = 'Ocurrió un error al leer los resultados de la búsqueda.';
							$sales = [];
							break;
						}
						$sales[] = $sale;
					}
					sqlsrv_free_stmt($statement);
				}
			}
		}
	}

	function escapeHtml($value) {
		return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	function formatCurrency($value) {
		$amount = trim((string) $value);
		if (!preg_match('/^(-?)(\d+)(?:\.(\d+))?$/D', $amount, $parts)) {
			return '$' . escapeHtml($amount);
		}

		$whole = ltrim($parts[2], '0');
		if ($whole === '') {
			$whole = '0';
		}
		$whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole);
		$cents = str_pad(substr(isset($parts[3]) ? $parts[3] : '', 0, 2), 2, '0');

		$currencySign = $parts[1] === '-' ? '-$' : '$';
		return $currencySign . $whole . '.' . $cents;
	}

	$inputType = $errorMessage === '' ? $inputTypes[$field] : 'search';
	$inputMode = $inputModes[$field];
	$placeholder = $placeholders[$field];
	$displaySearchValue = $field === 'total_ventas' && $errorMessage === ''
		? str_replace(',', '.', $searchValue)
		: $searchValue;
	$isSearching = $searchValue !== '' && $errorMessage === '';
	$paginationUrl = function ($targetPage) use ($field, $searchValue) {
		return 'index.php?' . http_build_query([
			'campo' => $field,
			'q' => $searchValue,
			'pagina' => $targetPage,
		]);
	};
	$exportUrl = 'index.php?' . http_build_query([
		'campo' => $field,
		'q' => $searchValue,
		'exportar' => 'csv',
	]);
?>
<!doctype html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Ventas registradas | Control de Ventas</title>
	<link rel="icon" href="../images/clipboard.png">
	<link rel="stylesheet" href="../styles/style.css">
	<link rel="stylesheet" href="../styles/vendor/bootstrap.min.css">
	<link rel="stylesheet" href="../styles/ventas.css">
	<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.13.0/css/all.css">
</head>
<body class="ventas-page">
	<nav class="ventas-navbar" aria-label="Navegación principal">
		<ul class="ventas-nav-list">
			<li><a href="index.php" class="ventas-nav-link is-active" aria-current="page">Ver ventas</a></li>
			<li><a href="registrar.php" class="ventas-nav-link">Registrar</a></li>
			<li><a href="modificar.php" class="ventas-nav-link">Modificar</a></li>
			<li><a href="eliminar.php" class="ventas-nav-link">Eliminar</a></li>
			<li><a href="historial.php" class="ventas-nav-link">Historial</a></li>
			<li class="ventas-logout-item"><a href="../php/salir.php" class="ventas-nav-link ventas-logout">Cerrar sesión</a></li>
		</ul>
	</nav>

	<main class="container ventas-main">
		<header class="ventas-heading">
			<p class="ventas-eyebrow">Control de ventas</p>
			<h1>Ventas registradas</h1>
			<p class="ventas-description">Consulta y filtra los registros guardados en el sistema.</p>
		</header>

		<section class="ventas-card" aria-labelledby="search-title">
			<div class="ventas-card-heading">
				<div class="ventas-search-icon" aria-hidden="true"><i class="fas fa-search"></i></div>
				<div>
					<h2 id="search-title">Buscar ventas</h2>
					<p>Elige un campo o busca un valor en todos los datos.</p>
				</div>
			</div>

			<form action="index.php" method="get" class="ventas-search-form">
				<div class="row g-3 align-items-end">
					<div class="col-12 col-md-4">
						<label for="search-field" class="form-label">Buscar en</label>
						<select class="form-select" id="search-field" name="campo">
							<?php foreach ($fieldLabels as $value => $label) { ?>
								<option value="<?php echo escapeHtml($value); ?>" <?php echo $field === $value ? 'selected' : ''; ?>>
									<?php echo escapeHtml($label); ?>
								</option>
							<?php } ?>
						</select>
					</div>
					<div class="col-12 col-md-5">
						<label for="search-value" class="form-label">Valor</label>
						<div class="input-group ventas-input-group">
							<span class="input-group-text" aria-hidden="true"><i class="fas fa-search"></i></span>
							<input
								class="form-control"
								id="search-value"
								name="q"
								type="<?php echo escapeHtml($inputType); ?>"
								inputmode="<?php echo escapeHtml($inputMode); ?>"
								placeholder="<?php echo escapeHtml($placeholder); ?>"
								value="<?php echo escapeHtml($displaySearchValue); ?>"
								<?php if (isset($inputSteps[$field])) { ?>step="<?php echo escapeHtml($inputSteps[$field]); ?>"<?php } ?>
								<?php if (isset($inputMins[$field])) { ?>min="<?php echo escapeHtml($inputMins[$field]); ?>"<?php } ?>
								<?php if (isset($inputMaxes[$field])) { ?>max="<?php echo escapeHtml($inputMaxes[$field]); ?>"<?php } ?>
								maxlength="100"
								autocomplete="off"
								aria-describedby="search-help"
							>
						</div>
						<div id="search-help" class="form-text">“Todos los campos” busca coincidencias parciales; los filtros específicos buscan el valor exacto.</div>
					</div>
					<div class="col-12 col-md-3">
						<div class="ventas-form-actions">
							<button type="submit" class="btn btn-primary ventas-button">
								<i class="fas fa-search me-2" aria-hidden="true"></i>Buscar
							</button>
							<a href="index.php" class="btn btn-outline-secondary ventas-button ventas-reset">
								<i class="fas fa-undo me-2" aria-hidden="true"></i>Limpiar
							</a>
						</div>
					</div>
				</div>
			</form>
		</section>

		<?php if ($errorMessage === '') { ?>
			<section class="ventas-summary-grid" aria-label="Resumen de ventas filtradas">
				<article class="ventas-summary-card">
					<span>Registros encontrados</span>
					<strong><?php echo number_format($totalRecords); ?></strong>
				</article>
				<article class="ventas-summary-card">
					<span>Ventas acumuladas</span>
					<strong><?php echo number_format($sumSales); ?></strong>
				</article>
				<article class="ventas-summary-card">
					<span>Importe acumulado</span>
					<strong><?php echo formatCurrency($sumAmount); ?></strong>
				</article>
			</section>
		<?php } ?>

		<section class="ventas-results" aria-labelledby="results-title">
			<div class="ventas-results-heading">
				<div>
					<h2 id="results-title">Resultados</h2>
					<?php if ($errorMessage === '') { ?>
						<p class="ventas-result-count" aria-live="polite">
							<?php if ($isSearching) { ?>
								<?php echo number_format($totalRecords); ?> <?php echo $totalRecords === 1 ? 'coincidencia' : 'coincidencias'; ?>
								para “<?php echo escapeHtml($searchValue); ?>”
							<?php } else { ?>
								<?php echo number_format($totalRecords); ?> <?php echo $totalRecords === 1 ? 'registro de venta' : 'registros de venta'; ?> en total
							<?php } ?>
						</p>
					<?php } ?>
				</div>
				<?php if ($errorMessage === '') { ?>
					<a class="btn btn-outline-secondary ventas-button" href="<?php echo escapeHtml($exportUrl); ?>">
						<i class="fas fa-file-csv me-2" aria-hidden="true"></i>Exportar CSV
					</a>
				<?php } ?>
			</div>

			<?php if ($errorMessage !== '') { ?>
				<div class="alert alert-danger ventas-alert" role="alert">
					<i class="fas fa-exclamation-circle me-2" aria-hidden="true"></i><?php echo escapeHtml($errorMessage); ?>
				</div>
			<?php } else { ?>
				<p class="ventas-sort-note">Pulsa un encabezado para ordenar visualmente los resultados de esta página. Esto no cambia los datos guardados.</p>
				<div class="ventas-table-card">
					<div class="table-responsive">
						<table class="table table-striped align-middle mb-0 ventas-table">
							<thead>
								<tr>
									<th scope="col" aria-sort="none"><button type="button" class="ventas-sort-button" data-sort-type="integer">Núm. registro <span aria-hidden="true">↕</span></button></th>
									<th scope="col" aria-sort="none"><button type="button" class="ventas-sort-button" data-sort-type="integer">Número de ventas <span aria-hidden="true">↕</span></button></th>
									<th scope="col" aria-sort="none"><button type="button" class="ventas-sort-button" data-sort-type="decimal">Total en ventas <span aria-hidden="true">↕</span></button></th>
									<th scope="col" aria-sort="none"><button type="button" class="ventas-sort-button" data-sort-type="date">Fecha <span aria-hidden="true">↕</span></button></th>
								</tr>
							</thead>
							<tbody>
								<?php if (count($sales) > 0) { ?>
									<?php foreach ($sales as $sale) { ?>
										<tr>
											<td data-sort-value="<?php echo escapeHtml($sale['id']); ?>"><span class="ventas-id">#<?php echo escapeHtml($sale['id']); ?></span></td>
											<td data-sort-value="<?php echo $sale['num_ventas'] === null ? '' : escapeHtml($sale['num_ventas']); ?>"><?php echo $sale['num_ventas'] === null ? '—' : escapeHtml($sale['num_ventas']); ?></td>
											<td class="ventas-amount" data-sort-value="<?php echo $sale['total_ventas'] === null ? '' : escapeHtml($sale['total_ventas']); ?>">
												<?php echo $sale['total_ventas'] === null ? '—' : formatCurrency($sale['total_ventas']); ?>
											</td>
											<td data-sort-value="<?php echo $sale['fecha'] === null ? '' : escapeHtml($sale['fecha']); ?>"><?php echo $sale['fecha'] === null ? '—' : escapeHtml($sale['fecha']); ?></td>
										</tr>
									<?php } ?>
								<?php } else { ?>
									<tr>
										<td colspan="4" class="ventas-empty-cell">
											<div class="ventas-empty-state">
												<span class="ventas-empty-icon" aria-hidden="true"><i class="fas fa-inbox"></i></span>
												<strong><?php echo $isSearching ? 'No se encontraron coincidencias' : 'Aún no hay ventas registradas'; ?></strong>
												<span>
													<?php echo $isSearching
														? 'Prueba con otro valor o cambia el campo de búsqueda.'
														: 'Cuando registres una venta, aparecerá en esta tabla.'; ?>
												</span>
											</div>
										</td>
									</tr>
								<?php } ?>
							</tbody>
						</table>
					</div>
				</div>
				<?php if ($totalPages > 1) { ?>
					<nav class="ventas-pagination" aria-label="Paginación de ventas">
						<span>Mostrando <?php echo number_format($startRecord); ?>–<?php echo number_format($endRecord); ?> de <?php echo number_format($totalRecords); ?></span>
						<div class="ventas-pagination-actions">
							<?php if ($page > 1) { ?>
								<a class="btn btn-outline-secondary ventas-button" href="<?php echo escapeHtml($paginationUrl($page - 1)); ?>">Anterior</a>
							<?php } else { ?>
								<span class="btn btn-outline-secondary ventas-button is-disabled" aria-disabled="true">Anterior</span>
							<?php } ?>
							<span class="ventas-page-indicator">Página <?php echo number_format($page); ?> de <?php echo number_format($totalPages); ?></span>
							<?php if ($page < $totalPages) { ?>
								<a class="btn btn-outline-secondary ventas-button" href="<?php echo escapeHtml($paginationUrl($page + 1)); ?>">Siguiente</a>
							<?php } else { ?>
								<span class="btn btn-outline-secondary ventas-button is-disabled" aria-disabled="true">Siguiente</span>
							<?php } ?>
						</div>
					</nav>
				<?php } ?>
			<?php } ?>
		</section>
	</main>

	<script src="../scripts/ventas.js" defer></script>
</body>
</html>
