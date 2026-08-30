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
$stmt = $db->prepare("SELECT id, nombre, latitud, longitud, latitud_partida, longitud_partida, radio_geocerca, total_vueltas, distancia, categoria, estado, fecha_evento FROM eventos WHERE id = ? LIMIT 1");
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

// Total vueltas y radio de geocerca configurados en el evento
$total_vueltas = intval($event['total_vueltas'] ?? 1);
if ($total_vueltas <= 0) $total_vueltas = 1;

$geofence_radius_meters = intval($event['radio_geocerca'] ?? 30);
if ($geofence_radius_meters <= 0) $geofence_radius_meters = 30;

$latMeta = floatval($event['latitud'] ?? 18.4861);
$lngMeta = floatval($event['longitud'] ?? -69.9312);
$latSalida = floatval($event['latitud_partida'] ?? $latMeta);
$lngSalida = floatval($event['longitud_partida'] ?? $lngMeta);

echo json_encode([
    'success' => true,
    'data' => [
        'event_id' => intval($event['id']),
        'event_name' => $event['nombre'],
        'event_status' => $event['estado'],
        'event_date' => $event['fecha_evento'],
        'start_line_coordinates' => [
            'latitude' => $latSalida,
            'longitude' => $lngSalida
        ],
        'finish_line_coordinates' => [
            'latitude' => $latMeta,
            'longitude' => $lngMeta
        ],
        'is_circuit' => (abs($latSalida - $latMeta) < 0.00001 && abs($lngSalida - $lngMeta) < 0.00001),
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
