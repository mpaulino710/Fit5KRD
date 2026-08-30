<?php
// modules/api/sync_laps.php
require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido. Utilice POST.']);
    exit();
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payload JSON inválido.']);
    exit();
}

// Permite procesar tanto un solo objeto como un array de vueltas
$laps = isset($data[0]) ? $data : [$data];
$processed = [];
$errors = [];

$db = getDB();

foreach ($laps as $lap) {
    $userId = isset($lap['user_id']) ? intval($lap['user_id']) : 0;
    $eventId = isset($lap['event_id']) ? intval($lap['event_id']) : 0;
    $lapNumber = isset($lap['lap_number']) ? intval($lap['lap_number']) : 0;
    $crossedAt = isset($lap['crossed_at']) ? trim($lap['crossed_at']) : '';
    $durationSeconds = isset($lap['lap_duration_seconds']) ? intval($lap['lap_duration_seconds']) : 0;

    if ($userId <= 0 || $eventId <= 0 || $lapNumber <= 0 || empty($crossedAt)) {
        $errors[] = [
            'lap_number' => $lapNumber,
            'message' => 'Datos incompletos para procesar la vuelta.'
        ];
        continue;
    }

    // 1. Obtener la inscripción del usuario en este evento
    $stmtInsc = $db->prepare("SELECT id, numero_corredor, categoria FROM inscripciones WHERE id_usuario = ? AND id_evento = ? LIMIT 1");
    $stmtInsc->bind_param("ii", $userId, $eventId);
    $stmtInsc->execute();
    $inscripcion = $stmtInsc->get_result()->fetch_assoc();

    if (!$inscripcion) {
        $errors[] = [
            'lap_number' => $lapNumber,
            'message' => "El usuario {$userId} no cuenta con una inscripción activa en el evento {$eventId}."
        ];
        continue;
    }

    $idInscripcion = intval($inscripcion['id']);

    // Convertir crossed_at a formato MySQL DATETIME
    $crossedAtDateTime = date('Y-m-d H:i:s', strtotime($crossedAt));

    // 2. Insertar o actualizar la vuelta en tiempos_vueltas de forma idempotente
    $stmtInsert = $db->prepare("
        INSERT INTO tiempos_vueltas (id_inscripcion, id_usuario, id_evento, numero_vuelta, tiempo_cruce, duracion_segundos, fecha_sincronizacion)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            tiempo_cruce = VALUES(tiempo_cruce),
            duracion_segundos = VALUES(duracion_segundos),
            fecha_sincronizacion = NOW()
    ");
    $stmtInsert->bind_param("iiiisi", $idInscripcion, $userId, $eventId, $lapNumber, $crossedAtDateTime, $durationSeconds);

    if ($stmtInsert->execute()) {
        $processed[] = [
            'lap_number' => $lapNumber,
            'status' => 'synced',
            'lap_id' => $stmtInsert->insert_id ?: true
        ];

        // 3. Verificar si el evento concluyó para registrar el resultado final acumulado
        $stmtEvent = $db->prepare("SELECT distancia FROM eventos WHERE id = ? LIMIT 1");
        $stmtEvent->bind_param("i", $eventId);
        $stmtEvent->execute();
        $eventData = $stmtEvent->get_result()->fetch_assoc();
        
        $distanciaKm = floatval($eventData['distancia'] ?? 5.0);
        $totalVueltasEsperadas = max(1, intval(ceil($distanciaKm / 5.0)));

        // Si completó la última vuelta, calculamos el tiempo total oficial
        if ($lapNumber >= $totalVueltasEsperadas) {
            // Sumar duraciones de todas las vueltas de este corredor
            $stmtSum = $db->prepare("SELECT SUM(duracion_segundos) as tiempo_total FROM tiempos_vueltas WHERE id_inscripcion = ?");
            $stmtSum->bind_param("i", $idInscripcion);
            $stmtSum->execute();
            $sumRes = $stmtSum->get_result()->fetch_assoc();
            $tiempoTotalSegundos = intval($sumRes['tiempo_total'] ?? $durationSeconds);

            // Formatear H:i:s
            $horas = floor($tiempoTotalSegundos / 3600);
            $minutos = floor(($tiempoTotalSegundos % 3600) / 60);
            $segundos = $tiempoTotalSegundos % 60;
            $tiempoOficialFormatted = sprintf('%02d:%02d:%02d', $horas, $minutos, $segundos);

            // Actualizar tiempo_final en inscripciones
            $stmtUpdateInsc = $db->prepare("UPDATE inscripciones SET tiempo_final = ?, asistencia = 1, fecha_asistencia = NOW() WHERE id = ?");
            $stmtUpdateInsc->bind_param("si", $tiempoOficialFormatted, $idInscripcion);
            $stmtUpdateInsc->execute();

            // Insertar o actualizar tabla de resultados
            $stmtRes = $db->prepare("
                INSERT INTO resultados (id_inscripcion, tiempo_oficial, fecha_registro)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    tiempo_oficial = VALUES(tiempo_oficial),
                    fecha_registro = NOW()
            ");
            $stmtRes->bind_param("is", $idInscripcion, $tiempoOficialFormatted);
            $stmtRes->execute();
        }
    } else {
        $errors[] = [
            'lap_number' => $lapNumber,
            'message' => 'Error al persistir en base de datos: ' . $stmtInsert->error
        ];
    }
}

echo json_encode([
    'success' => count($errors) === 0,
    'processed_count' => count($processed),
    'processed' => $processed,
    'errors' => $errors
]);
