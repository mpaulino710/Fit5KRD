<?php
// modules/api/validate_promo.php
require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Get data
$input = json_decode(file_get_contents('php://input'), true);
$codigo = isset($input['codigo']) ? trim($input['codigo']) : '';
$event_id = isset($input['event_id']) ? intval($input['event_id']) : 0;
$monto_original = isset($input['monto']) ? floatval($input['monto']) : 0;

if (empty($codigo) || $event_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

$db = getDB();

// Validar código
// Debe estar activo, fecha vigente, tener stock, y coincidir con el evento (o ser global)
$query = "SELECT * FROM promociones 
          WHERE codigo = ? 
          AND estado = 'activo' 
          AND (id_evento IS NULL OR id_evento = ?)
          AND fecha_inicio <= NOW() 
          AND fecha_fin >= NOW()
          LIMIT 1";

$stmt = $db->prepare($query);
$stmt->bind_param("si", $codigo, $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Código no válido o expirado']);
    exit();
}

$promo = $result->fetch_assoc();

// Check stock
if ($promo['cantidad'] > 0 && $promo['usados'] >= $promo['cantidad']) {
    echo json_encode(['success' => false, 'message' => 'Este código ha agotado su límite de usos']);
    exit();
}

// Calculate discount
$porcentaje = floatval($promo['porcentaje']);
$descuento = ($monto_original * $porcentaje) / 100;
$nuevo_total = $monto_original - $descuento;

echo json_encode([
    'success' => true,
    'message' => 'Código aplicado correctamente',
    'porcentaje' => $porcentaje,
    'descuento' => $descuento,
    'nuevo_total' => max(0, $nuevo_total),
    'codigo' => $promo['codigo']
]);

$stmt->close();
$db->close();
?>
