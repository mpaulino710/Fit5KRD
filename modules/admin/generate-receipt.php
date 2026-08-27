<?php
// modules/admin/generate-receipt.php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Check session
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

if (!isset($_GET['id'])) {
    die("ID de pago no especificado.");
}

$db = getDB();
$pago_id = intval($_GET['id']);

$query = "SELECT
            p.*,
            i.numero_corredor,
            i.estado as inscripcion_estado,
            u.nombre as usuario_nombre,
            u.apellido as usuario_apellido,
            u.email as usuario_email,
            u.telefono as usuario_telefono,
            e.nombre as evento_nombre,
            e.fecha_evento,
            e.ubicacion as evento_ubicacion,
            e.precio as evento_precio,
            e.modalidades as evento_modalidades,
            i.modalidad as inscripcion_modalidad
          FROM pagos p
          JOIN inscripciones i ON p.id_inscripcion = i.id
          JOIN usuarios u ON i.id_usuario = u.id
          JOIN eventos e ON i.id_evento = e.id
          WHERE p.id = ?";

$stmt = $db->prepare($query);
$stmt->bind_param("i", $pago_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Pago no encontrado.");
}

$pago = $result->fetch_assoc();
$stmt->close();

// Obtener Invitados
$invitados = [];
$query_invitados = "SELECT nombre, apellido, email, modalidad FROM inscripciones WHERE parent_id = ?";
$stmt_inv = $db->prepare($query_invitados);
$stmt_inv->bind_param("i", $pago['id_inscripcion']);
$stmt_inv->execute();
$result_inv = $stmt_inv->get_result();
while ($inv = $result_inv->fetch_assoc()) {
    $invitados[] = $inv;
}
$stmt_inv->close();

// Helper Precios
$modalidades_data = !empty($pago['evento_modalidades']) ? json_decode($pago['evento_modalidades'], true) : [];
$evento_precio_base = floatval($pago['evento_precio']);

function getPrice($mod_name, $base_price, $mods_data) {
    if ($mod_name && !empty($mods_data)) {
        foreach ($mods_data as $m) {
            $mName = is_array($m) ? ($m['nombre'] ?? '') : $m;
            $mPrice = is_array($m) ? ($m['precio'] ?? $base_price) : $base_price;
            if ($mName === $mod_name) {
                return floatval($mPrice);
            }
        }
    }
    return $base_price;
}

$breakdown = [];
$breakdown[] = [
    'nombre' => $pago['usuario_nombre'] . ' ' . $pago['usuario_apellido'],
    'tipo' => 'Principal',
    'modalidad' => $pago['inscripcion_modalidad'] ?: 'General',
    'precio' => getPrice($pago['inscripcion_modalidad'], $evento_precio_base, $modalidades_data)
];

foreach ($invitados as $inv) {
    $guest_name = $inv['nombre'] . ' ' . $inv['apellido'];
    if (empty(trim($guest_name))) $guest_name = 'Invitado';
    
    $breakdown[] = [
        'nombre' => $guest_name,
        'tipo' => 'Invitado',
        'modalidad' => $inv['modalidad'] ?: 'General',
        'precio' => getPrice($inv['modalidad'], $evento_precio_base, $modalidades_data)
    ];
}
$total_calc = 0;
foreach ($breakdown as $item) {
    $total_calc += $item['precio'];
}

date_default_timezone_set('America/Santo_Domingo');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Pago #<?php echo $pago['id']; ?></title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            line-height: 1.5;
            margin: 0;
            padding: 20px;
            background: #f8f9fa;
        }
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo-area h1 {
            margin: 0;
            color: #2c3e50;
            font-size: 28px;
        }
        .logo-area p {
            margin: 5px 0 0;
            color: #7f8c8d;
            font-size: 14px;
        }
        .receipt-info {
            text-align: right;
        }
        .receipt-info h2 {
            margin: 0;
            color: #e74c3c;
            font-size: 24px;
            text-transform: uppercase;
        }
        .receipt-info p {
            margin: 5px 0 0;
            font-size: 14px;
            color: #555;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }
        .info-box {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 6px;
        }
        .info-box h3 {
            margin: 0 0 10px;
            font-size: 16px;
            color: #2c3e50;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .info-box p {
            margin: 5px 0;
            font-size: 14px;
        }
        .info-box strong {
            display: inline-block;
            width: 120px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
        }
        td.amount, th.amount {
            text-align: right;
        }
        .totals {
            width: 50%;
            margin-left: auto;
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
        }
        .totals-row.grand-total {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            border-bottom: 2px solid #2c3e50;
            border-top: 2px solid #2c3e50;
            background: #f8f9fa;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            color: #7f8c8d;
            font-size: 12px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        .print-btn {
            display: block;
            width: 250px;
            margin: 30px auto;
            padding: 12px 20px;
            background: #3498db;
            color: #fff;
            text-align: center;
            text-decoration: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            border: none;
        }
        .print-btn:hover {
            background: #2980b9;
        }
        @media print {
            body { 
                background: #fff;
                padding: 0;
            }
            .receipt-container {
                box-shadow: none;
                padding: 0;
                width: 100%;
                max-width: 100%;
            }
            .print-btn {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="receipt-container">
        <div class="header">
            <div class="logo-area">
                <h1>Sistema de Eventos</h1>
                <p>Comprobante de Pago Oficial</p>
            </div>
            <div class="receipt-info">
                <h2>RECIBO</h2>
                <p><strong>Recibo #:</strong> <?php echo str_pad($pago['id'], 6, '0', STR_PAD_LEFT); ?></p>
                <p><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($pago['fecha_pago'] ?? $pago['creado_en'])); ?></p>
                <p><strong>Estado:</strong> <?php echo strtoupper($pago['estado']); ?></p>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-box">
                <h3>Información del Cliente</h3>
                <p><strong>Nombre:</strong> <?php echo htmlspecialchars($pago['usuario_nombre'] . ' ' . $pago['usuario_apellido']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($pago['usuario_email']); ?></p>
                <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($pago['usuario_telefono'] ?: 'N/A'); ?></p>
            </div>
            <div class="info-box">
                <h3>Detalles de la Transacción</h3>
                <p><strong>Método de Pago:</strong> <?php echo ucfirst($pago['metodo_pago']); ?></p>
                <p><strong>Transacción ID:</strong> <?php echo htmlspecialchars($pago['transaccion_id'] ?: 'N/A'); ?></p>
                <p><strong>Referencia:</strong> <?php echo htmlspecialchars($pago['referencia_pago'] ?: 'N/A'); ?></p>
            </div>
            <div class="info-box" style="grid-column: 1 / -1;">
                <h3>Evento</h3>
                <p><strong>Nombre:</strong> <?php echo htmlspecialchars($pago['evento_nombre']); ?></p>
                <p><strong>Fecha Evento:</strong> <?php echo date('d/m/Y H:i', strtotime($pago['fecha_evento'])); ?></p>
                <p><strong>Ubicación:</strong> <?php echo htmlspecialchars($pago['evento_ubicacion']); ?></p>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Participante</th>
                    <th>Tipo</th>
                    <th>Modalidad</th>
                    <th class="amount">Importe</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($breakdown as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['nombre']); ?></td>
                    <td><?php echo $item['tipo']; ?></td>
                    <td><?php echo htmlspecialchars($item['modalidad']); ?></td>
                    <td class="amount">$<?php echo number_format($item['precio'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals">
            <div class="totals-row">
                <span>Subtotal:</span>
                <span>$<?php echo number_format($total_calc, 2); ?></span>
            </div>
            <?php 
            $ajustes = $pago['monto'] - $total_calc;
            if (abs($ajustes) > 0.01): 
            ?>
            <div class="totals-row">
                <span>Ajustes/Descuentos:</span>
                <span>$<?php echo number_format($ajustes, 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="totals-row grand-total">
                <span>Total Pagado:</span>
                <span>$<?php echo number_format($pago['monto'], 2); ?></span>
            </div>
        </div>

        <div class="footer">
            <p>Este es un recibo generado por el sistema. Si tiene alguna pregunta, contáctenos.</p>
            <p>&copy; <?php echo date('Y'); ?> Sistema de Eventos. Todos los derechos reservados.</p>
        </div>
    </div>

    <button class="print-btn" onclick="window.print()">
        🖨️ Imprimir / Guardar PDF
    </button>
    <script>
        // Auto-print prompt when opened.
        // Uncomment if you want to automatically pop up print dialog:
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>
