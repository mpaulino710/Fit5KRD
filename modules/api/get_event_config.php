<?php
// modules/api/get_event_config.php
require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($event_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Parámetro event_id requerido.'
    ]);
    exit();
}

$db = getDB();

// 1. Obtener detalles del evento
$stmt = $db->prepare("SELECT id, nombre, latitud, longitud, distancia, categoria, estado, fecha_evento FROM eventos WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();

if (!$event) {
    echo json_encode([
        'success' => false,
        'message' => 'Evento no encontrado.'
    ]);
    exit();
}

// 2. Verificar inscripción si se envía user_id
$inscripcion = null;
if ($user_id > 0) {
    $stmtInsc = $db->prepare("SELECT id, numero_corredor, categoria, modalidad, estado, tiempo_final FROM inscripciones WHERE id_usuario = ? AND id_evento = ? LIMIT 1");
    $stmtInsc->bind_param("ii", $user_id, $event_id);
    $stmtInsc->execute();
    $inscripcion = $stmtInsc->get_result()->fetch_assoc();
}

// Calcular vueltas según distancia (ej. circuitos estándar o distancia directa)
$distancia_km = floatval($event['distancia']);
$total_vueltas = 1;
if ($distancia_km > 0) {
    // Si la modalidad define vueltas o distancia por vuelta (ej. circuito de 2.5km para 5k = 2 vueltas)
    $total_vueltas = max(1, intval(ceil($distancia_km / 5.0))); // Por defecto 1 o según circuito
}

// Radio de tolerancia estándar en metros para la geocerca de la meta
$geofence_radius_meters = 25; 

echo json_encode([
    'success' => true,
    'data' => [
        'event_id' => intval($event['id']),
        'event_name' => $event['nombre'],
        'event_status' => $event['estado'],
        'event_date' => $event['fecha_evento'],
        'finish_line_coordinates' => [
            'latitude' => floatval($event['latitud'] ?? 18.4861),
            'longitude' => floatval($event['longitud'] ?? -69.9312)
        ],
        'geofence_radius_meters' => $geofence_radius_meters,
        'total_laps' => $total_vueltas,
        'registration' => $inscripcion ? [
            'registration_id' => intval($inscripcion['id']),
            'runner_number' => $inscripcion['numero_corredor'],
            'category' => $inscripcion['categoria'],
            'status' => $inscripcion['estado'],
            'finished' => !empty($inscripcion['tiempo_final'])
        ] : null
    ]
]);
