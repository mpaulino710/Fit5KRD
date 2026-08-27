<?php
// modules/admin/payments.php
require_once '../../config/config.php';
require_once '../../config/database.php';


// session_start(); removed as it's handled in config.php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

$db = getDB();

// Variables para filtros y paginación
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtro_metodo = isset($_GET['metodo']) ? $_GET['metodo'] : '';
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$por_pagina = 15;
$offset = ($pagina - 1) * $por_pagina;

// Construir consulta base
$query = "SELECT SQL_CALC_FOUND_ROWS
          p.*,
          i.numero_corredor,
          u.nombre as usuario_nombre,
          u.apellido as usuario_apellido,
          u.email as usuario_email,
          e.nombre as evento_nombre,
          promo.codigo as codigo_promocional
          FROM pagos p
          JOIN inscripciones i ON p.id_inscripcion = i.id
          JOIN usuarios u ON i.id_usuario = u.id
          JOIN eventos e ON i.id_evento = e.id
          LEFT JOIN promociones promo ON i.id_promocion = promo.id
          WHERE 1=1";

$params = [];
$types = '';

if ($filtro_estado) {
    $query .= " AND p.estado = ?";
    $params[] = $filtro_estado;
    $types .= 's';
}

if ($filtro_metodo) {
    $query .= " AND p.metodo_pago = ?";
    $params[] = $filtro_metodo;
    $types .= 's';
}

if ($fecha_inicio) {
    $query .= " AND DATE(p.fecha_pago) >= ?";
    $params[] = $fecha_inicio;
    $types .= 's';
}

if ($fecha_fin) {
    $query .= " AND DATE(p.fecha_pago) <= ?";
    $params[] = $fecha_fin;
    $types .= 's';
}

if ($busqueda) {
    $query .= " AND (u.nombre LIKE ? OR u.apellido LIKE ? OR u.email LIKE ? OR e.nombre LIKE ? OR p.transaccion_id LIKE ? OR p.referencia_pago LIKE ?)";
    $busqueda_like = "%$busqueda%";
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $types .= 'ssssss';
}

$filtro_promo = isset($_GET['promo_code']) ? trim($_GET['promo_code']) : '';
if ($filtro_promo) {
    if ($filtro_promo === 'ANY') {
        $query .= " AND i.id_promocion IS NOT NULL";
    } else {
        $query .= " AND promo.codigo LIKE ?";
        $promo_like = "%$filtro_promo%";
        $params[] = $promo_like;
        $types .= 's';
    }
}

$query .= " ORDER BY p.fecha_pago DESC LIMIT ? OFFSET ?";
$params[] = $por_pagina;
$params[] = $offset;
$types .= 'ii';

// Preparar consulta
$stmt = $db->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$pagos = $result->fetch_all(MYSQLI_ASSOC);

// Obtener total de resultados
$total_resultados = $db->query("SELECT FOUND_ROWS()")->fetch_row()[0];
$total_paginas = ceil($total_resultados / $por_pagina);

$stmt->close();

// Obtener estadísticas financieras
$stats_query = "SELECT
    COUNT(*) as total,
    SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completados,
    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
    SUM(CASE WHEN estado = 'fallido' THEN 1 ELSE 0 END) as fallidos,
    SUM(CASE WHEN estado = 'reembolsado' THEN 1 ELSE 0 END) as reembolsados,
    SUM(CASE WHEN estado = 'completado' THEN monto ELSE 0 END) as ingresos_totales,
    AVG(CASE WHEN estado = 'completado' THEN monto ELSE NULL END) as promedio_pago
FROM pagos";

$stats = $db->query($stats_query)->fetch_assoc();

// Estadísticas por método de pago
$metodos_query = "SELECT
    metodo_pago,
    COUNT(*) as cantidad,
    SUM(monto) as total,
    SUM(CASE WHEN estado = 'completado' THEN monto ELSE 0 END) as completados
FROM pagos
GROUP BY metodo_pago";

$metodos_stats = $db->query($metodos_query)->fetch_all(MYSQLI_ASSOC);

// Procesar acciones
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pago_id = $_POST['pago_id'] ?? 0;

    if ($action === 'cambiar_estado' && $pago_id) {
        $nuevo_estado = $_POST['nuevo_estado'];

        $stmt = $db->prepare("UPDATE pagos SET estado = ?, fecha_pago = NOW() WHERE id = ?");
        $stmt->bind_param("si", $nuevo_estado, $pago_id);

        if ($stmt->execute()) {
            $success = 'Estado del pago actualizado correctamente';
            
            if ($nuevo_estado === 'completado') {
                // Obtener datos para correo
                 $query_info = "SELECT 
                    p.*, 
                    i.id as inscription_id,
                    i.numero_corredor,
                    u.nombre as usuario_nombre, 
                    u.email as usuario_email,
                    e.nombre as evento_nombre,
                    e.fecha_evento,
                    e.ubicacion
                  FROM pagos p
                  JOIN inscripciones i ON p.id_inscripcion = i.id
                  JOIN usuarios u ON i.id_usuario = u.id
                  JOIN eventos e ON i.id_evento = e.id
                  WHERE p.id = ?";
                $stmt_info = $db->prepare($query_info);
                $stmt_info->bind_param("i", $pago_id);
                $stmt_info->execute();
                $pago_data = $stmt_info->get_result()->fetch_assoc();
                $stmt_info->close();
                
                if ($pago_data) {
                    $db->query("UPDATE inscripciones SET estado = 'confirmada' WHERE id = " . $pago_data['inscription_id']);
                    
                    require_once '../../includes/SMTPMailer.php';
                    $mailer = new SMTPMailer();
                    $subject = "Pago Completado - " . $pago_data['evento_nombre'];
                    
                     // Generar QR Data (JSON)
                    $qrData = json_encode([
                        'id' => $pago_data['inscription_id'],
                        'e' => $pago_data['evento_nombre'],
                        'c' => $pago_data['usuario_nombre'],
                        'd' => $pago_data['numero_corredor'] ?: 'Pendiente'
                    ]);
                    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qrData);

                    $message = "
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
                            .header { background-color: #007bff; color: white; padding: 15px; text-align: center; border-radius: 5px 5px 0 0; }
                            .content { padding: 20px; text-align: center; }
                            .details { text-align: left; margin: 20px 0; background-color: #f9f9f9; padding: 15px; border-radius: 5px; }
                            .footer { margin-top: 20px; text-align: center; font-size: 0.8em; color: #777; }
                            .qr-code { margin: 20px 0; }
                            .qr-code img { border: 1px solid #ddd; padding: 5px; border-radius: 5px; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>¡Pago Completado!</h2>
                            </div>
                            <div class='content'>
                                <p>Hola <strong>{$pago_data['usuario_nombre']}</strong>,</p>
                                <p>Tu pago para el evento <strong>{$pago_data['evento_nombre']}</strong> ha sido procesado exitosamente.</p>
                                
                                <div class='qr-code'>
                                    <p>Aquí tienes tu código de acceso:</p>
                                    <img src='{$qrUrl}' alt='Código QR de Acceso'>
                                </div>
                                
                                <div class='details'>
                                    <p><strong>Evento:</strong> {$pago_data['evento_nombre']}</p>
                                    <p><strong>Fecha:</strong> " . date('d/m/Y h:i A', strtotime($pago_data['fecha_evento'])) . "</p>
                                    <p><strong>Ubicación:</strong> {$pago_data['ubicacion']}</p>
                                    <p><strong>Monto Pagado:</strong> $" . number_format($pago_data['monto'], 2) . "</p>
                                    <p><strong>Número de Corredor:</strong> " . ($pago_data['numero_corredor'] ?: 'Pendiente asignación') . "</p>
                                </div>
                                
                                <p>Por favor, presenta el código QR adjunto al llegar al evento.</p>
                            </div>
                            <div class='footer'>
                                <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
                                <p>&copy; " . date('Y') . " " . SITE_NAME . ". Todos los derechos reservados.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                    
                    $mailer->send($pago_data['usuario_email'], $subject, $message);
                }
            }
        } else {
            $error = 'Error al actualizar el estado del pago';
        }
        $stmt->close();
    }

    elseif ($action === 'registrar_reembolso' && $pago_id) {
        $stmt = $db->prepare("UPDATE pagos SET estado = 'reembolsado', fecha_pago = NOW() WHERE id = ? AND estado = 'completado'");
        $stmt->bind_param("i", $pago_id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $success = 'Reembolso registrado correctamente';
        } else {
            $error = 'No se pudo registrar el reembolso';
        }
        $stmt->close();
    }

    // Recargar la página para mostrar cambios
    header("Location: payments.php?page=$pagina" .
           ($filtro_estado ? "&estado=$filtro_estado" : "") .
           ($filtro_metodo ? "&metodo=$filtro_metodo" : "") .
           ($fecha_inicio ? "&fecha_inicio=$fecha_inicio" : "") .
           ($fecha_fin ? "&fecha_fin=$fecha_fin" : "") .
           ($busqueda ? "&busqueda=$busqueda" : "") . 
           ($filtro_promo ? "&promo_code=$filtro_promo" : ""));
    exit();
}

include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <!-- Header -->
    <div class="admin-header">
        <h1><i class="fas fa-credit-card"></i> Gestión de Pagos</h1>
        <div class="admin-header-actions">
            <a href="reports.php?tipo_reporte=financiero" class="admin-btn admin-btn-success">
                <i class="fas fa-chart-bar"></i> Reportes Financieros
            </a>
        </div>
    </div>

    <!-- Notificaciones -->
    <?php if ($error): ?>
    <div class="admin-alert admin-alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo $error; ?></span>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="admin-alert admin-alert-success">
        <i class="fas fa-check-circle"></i>
        <span><?php echo $success; ?></span>
    </div>
    <?php endif; ?>

    <!-- Estadísticas Financieras -->
    <div class="admin-stats">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-number">$<?php echo number_format($stats['ingresos_totales'], 2); ?></div>
            <div class="stat-label">Ingresos Totales</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon stat-icon-success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['completados']); ?></div>
            <div class="stat-label">Pagos Completados</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon stat-icon-warning">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['pendientes']); ?></div>
            <div class="stat-label">Pagos Pendientes</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon stat-icon-danger">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['fallidos']); ?></div>
            <div class="stat-label">Pagos Fallidos</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon stat-icon-secondary">
                <i class="fas fa-undo-alt"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['reembolsados']); ?></div>
            <div class="stat-label">Reembolsados</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-number">$<?php echo number_format($stats['promedio_pago'], 2); ?></div>
            <div class="stat-label">Promedio por Pago</div>
        </div>
    </div>

    <!-- Estadísticas por Método de Pago -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2><i class="fas fa-chart-pie"></i> Distribución por Método de Pago</h2>
        </div>
        <div class="metodos-grid">
            <?php foreach ($metodos_stats as $metodo):
                $porcentaje = $stats['total'] > 0 ? ($metodo['cantidad'] / $stats['total']) * 100 : 0;
                $porcentaje_ingresos = $stats['ingresos_totales'] > 0 ? ($metodo['completados'] / $stats['ingresos_totales']) * 100 : 0;
            ?>
            <div class="metodo-card">
                <div class="metodo-header">
                    <h3><?php echo ucfirst($metodo['metodo_pago']); ?></h3>
                    <span class="badge badge-primary"><?php echo round($porcentaje, 1); ?>%</span>
                </div>
                <div class="metodo-body">
                    <div class="metodo-stat">
                        <span class="metodo-label">Total Pagos:</span>
                        <span class="metodo-value"><?php echo $metodo['cantidad']; ?></span>
                    </div>
                    <div class="metodo-stat">
                        <span class="metodo-label">Ingresos:</span>
                        <span class="metodo-value">$<?php echo number_format($metodo['completados'], 2); ?></span>
                    </div>
                    <div class="metodo-stat">
                        <span class="metodo-label">Porcentaje Ingresos:</span>
                        <span class="metodo-value"><?php echo round($porcentaje_ingresos, 1); ?>%</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Filtros y Búsqueda -->
    <div class="admin-filters">
        <form method="GET" action="">
            <div class="filter-row">
                <div class="form-group">
                    <label for="busqueda"><i class="fas fa-search"></i> Buscar</label>
                    <input type="text" id="busqueda" name="busqueda" class="form-control"
                           placeholder="Nombre, email, evento o referencia"
                           value="<?php echo htmlspecialchars($busqueda); ?>">
                </div>

                <div class="form-group">
                    <label for="estado"><i class="fas fa-filter"></i> Estado</label>
                    <select id="estado" name="estado" class="form-control">
                        <option value="">Todos los estados</option>
                        <option value="completado" <?php echo $filtro_estado === 'completado' ? 'selected' : ''; ?>>Completado</option>
                        <option value="pendiente" <?php echo $filtro_estado === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="fallido" <?php echo $filtro_estado === 'fallido' ? 'selected' : ''; ?>>Fallido</option>
                        <option value="reembolsado" <?php echo $filtro_estado === 'reembolsado' ? 'selected' : ''; ?>>Reembolsado</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="metodo"><i class="fas fa-credit-card"></i> Método</label>
                    <select id="metodo" name="metodo" class="form-control">
                        <option value="">Todos los métodos</option>
                        <option value="tarjeta" <?php echo $filtro_metodo === 'tarjeta' ? 'selected' : ''; ?>>Tarjeta</option>
                        <option value="transferencia" <?php echo $filtro_metodo === 'transferencia' ? 'selected' : ''; ?>>Transferencia</option>
                        <option value="efectivo" <?php echo $filtro_metodo === 'efectivo' ? 'selected' : ''; ?>>Efectivo</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="promo_code"><i class="fas fa-tag"></i> Promo</label>
                    <input type="text" id="promo_code" name="promo_code" class="form-control"
                           placeholder="Código"
                           value="<?php echo isset($_GET['promo_code']) ? htmlspecialchars($_GET['promo_code']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="fecha_inicio"><i class="fas fa-calendar"></i> Fecha Inicio</label>
                    <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control"
                           value="<?php echo htmlspecialchars($fecha_inicio); ?>">
                </div>

                <div class="form-group">
                    <label for="fecha_fin"><i class="fas fa-calendar"></i> Fecha Fin</label>
                    <input type="date" id="fecha_fin" name="fecha_fin" class="form-control"
                           value="<?php echo htmlspecialchars($fecha_fin); ?>">
                </div>

                <div>
                    <button type="submit" class="admin-btn admin-btn-primary">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <a href="payments.php" class="admin-btn admin-btn-secondary">
                        <i class="fas fa-redo"></i> Limpiar
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabla de Pagos -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2><i class="fas fa-list"></i> Lista de Pagos</h2>
            <span class="badge badge-primary">
                <?php echo number_format($total_resultados); ?> pagos encontrados
            </span>
        </div>

        <?php if (!empty($pagos)): ?>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Evento</th>
                        <th>Promo</th>
                        <th>Monto</th>
                        <th>Método</th>
                        <th>Fecha Pago</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pagos as $pago):
                        $fecha_pago = $pago['fecha_pago'] ? strtotime($pago['fecha_pago']) : null;

                        $estado_colors = [
                            'completado' => 'success',
                            'pendiente' => 'warning',
                            'fallido' => 'danger',
                            'reembolsado' => 'secondary'
                        ];
                        $color = $estado_colors[$pago['estado']] ?? 'secondary';
                    ?>
                    <tr>
                        <td><strong>#<?php echo $pago['id']; ?></strong></td>
                        <td>
                            <div class="user-info">
                                <div class="user-avatar-small">
                                    <?php echo strtoupper(substr($pago['usuario_nombre'], 0, 1) . substr($pago['usuario_apellido'], 0, 1)); ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($pago['usuario_nombre'] . ' ' . $pago['usuario_apellido']); ?></strong><br>
                                    <small class="user-email"><?php echo htmlspecialchars($pago['usuario_email']); ?></small><br>
                                    <?php if ($pago['numero_corredor']): ?>
                                    <small class="runner-number">Corredor: <?php echo $pago['numero_corredor']; ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($pago['evento_nombre']); ?></strong>
                        </td>
                        <td>
                            <?php if ($pago['codigo_promocional']): ?>
                                <span class="badge badge-success" title="Código Promocional">
                                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($pago['codigo_promocional']); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #ccc;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="payment-amount">$<?php echo number_format($pago['monto'], 2); ?></div>
                            <?php if ($pago['transaccion_id']): ?>
                            <small class="transaction-id">ID: <?php echo $pago['transaccion_id']; ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-info payment-method">
                                <?php echo ucfirst($pago['metodo_pago']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($fecha_pago): ?>
                            <div class="payment-date"><?php echo date('d/m/Y', $fecha_pago); ?></div>
                            <small class="payment-time"><?php echo date('h:i A', $fecha_pago); ?></small>
                            <?php else: ?>
                            <span class="badge badge-secondary">Sin fecha</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $color; ?>">
                                <?php echo ucfirst($pago['estado']); ?>
                            </span>

                            <form method="POST" action="" class="status-form">
                                <input type="hidden" name="action" value="cambiar_estado">
                                <input type="hidden" name="pago_id" value="<?php echo $pago['id']; ?>">
                                <select name="nuevo_estado" class="form-control form-control-sm"
                                        onchange="if(confirm('¿Cambiar estado de este pago?')) this.form.submit()">
                                    <option value="completado" <?php echo $pago['estado'] == 'completado' ? 'selected' : ''; ?>>Completado</option>
                                    <option value="pendiente" <?php echo $pago['estado'] == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                    <option value="fallido" <?php echo $pago['estado'] == 'fallido' ? 'selected' : ''; ?>>Fallido</option>
                                    <option value="reembolsado" <?php echo $pago['estado'] == 'reembolsado' ? 'selected' : ''; ?>>Reembolsado</option>
                                </select>
                            </form>

                            <?php if ($pago['estado'] === 'completado'): ?>
                            <form method="POST" action="" class="refund-form"
                                  onsubmit="return confirm('¿Registrar reembolso de este pago?');">
                                <input type="hidden" name="action" value="registrar_reembolso">
                                <input type="hidden" name="pago_id" value="<?php echo $pago['id']; ?>">
                                <button type="submit" class="admin-btn admin-btn-warning btn-sm btn-block">
                                    <i class="fas fa-undo-alt"></i> Reembolsar
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="view-payment.php?id=<?php echo $pago['id']; ?>"
                                   class="admin-btn admin-btn-outline action-btn" title="Ver detalles">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($pago['estado'] === 'pendiente'): ?>
                                <a href="process-payment.php?id=<?php echo $pago['id']; ?>"
                                   class="admin-btn admin-btn-success action-btn" title="Procesar pago">
                                    <i class="fas fa-check"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <?php if ($total_paginas > 1): ?>
        <div class="admin-pagination">
            <?php if ($pagina > 1): ?>
            <a href="?pagina=<?php echo $pagina - 1; ?>&estado=<?php echo $filtro_estado; ?>&metodo=<?php echo $filtro_metodo; ?>&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&busqueda=<?php echo urlencode($busqueda); ?>&promo_code=<?php echo urlencode($filtro_promo); ?>"
               class="pagination-link">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>

            <?php
            $inicio = max(1, $pagina - 2);
            $fin = min($total_paginas, $pagina + 2);

            for ($i = $inicio; $i <= $fin; $i++):
            ?>
            <a href="?pagina=<?php echo $i; ?>&estado=<?php echo $filtro_estado; ?>&metodo=<?php echo $filtro_metodo; ?>&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&busqueda=<?php echo urlencode($busqueda); ?>&promo_code=<?php echo urlencode($filtro_promo); ?>"
               class="pagination-link <?php echo $i == $pagina ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>

            <?php if ($pagina < $total_paginas): ?>
            <a href="?pagina=<?php echo $pagina + 1; ?>&estado=<?php echo $filtro_estado; ?>&metodo=<?php echo $filtro_metodo; ?>&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&busqueda=<?php echo urlencode($busqueda); ?>&promo_code=<?php echo urlencode($filtro_promo); ?>"
               class="pagination-link">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="table-footer">
            Mostrando <?php echo count($pagos); ?> de <?php echo $total_resultados; ?> pagos
        </div>

        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-credit-card"></i>
            <h3>No se encontraron pagos</h3>
            <p>No hay pagos que coincidan con tu búsqueda</p>
            <a href="payments.php" class="admin-btn admin-btn-primary">
                <i class="fas fa-redo"></i> Ver todos los pagos
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Exportar datos -->
    <?php $export_type = 'payments'; include '../../includes/export-buttons.php'; ?>
</div>

<script>
// Auto-ocultar notificaciones después de 5 segundos
setTimeout(() => {
    document.querySelectorAll('.admin-alert').forEach(notification => {
        notification.style.display = 'none';
    });
}, 5000);

// Exportar pagos
function exportPagos() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');

    window.location.href = 'export_payments.php?' + params.toString();
}

// Búsqueda con Enter
document.getElementById('busqueda')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        this.form.submit();
    }
});

// Validar fechas
document.getElementById('fecha_inicio')?.addEventListener('change', function() {
    const fechaFin = document.getElementById('fecha_fin');
    if (this.value && fechaFin.value && this.value > fechaFin.value) {
        fechaFin.value = this.value;
    }
});

document.getElementById('fecha_fin')?.addEventListener('change', function() {
    const fechaInicio = document.getElementById('fecha_inicio');
    if (this.value && fechaInicio.value && this.value < fechaInicio.value) {
        fechaInicio.value = this.value;
    }
});
</script>

<?php include '../../includes/admin-footer.php'; ?>
