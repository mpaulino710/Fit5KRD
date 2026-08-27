<?php
// modules/admin/export.php
require_once '../../config/config.php';
require_once '../../config/database.php';


// session_start(); removed as it's handled in config.php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    die("Acceso denegado");
}

$db = getDB();
$type = $_GET['type'] ?? '';

if (!$type) {
    die("Tipo de exportación no especificado");
}

// Set headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=reporte_' . $type . '_' . date('Y-m-d') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

switch ($type) {
    case 'users':
        $busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
        $query = "SELECT id, nombre, apellido, email, telefono, tipo_usuario, estado, fecha_registro FROM usuarios WHERE 1=1";
        $params = [];
        $types = '';
        if ($busqueda) {
            $query .= " AND (nombre LIKE ? OR apellido LIKE ? OR email LIKE ?)";
            $busqueda_like = "%$busqueda%";
            $params[] = $busqueda_like;
            $params[] = $busqueda_like;
            $params[] = $busqueda_like;
            $types .= 'sss';
        }
        $query .= " ORDER BY fecha_registro DESC";
        fputcsv($output, ['ID', 'Nombre', 'Apellido', 'Email', 'Teléfono', 'Tipo', 'Estado', 'Fecha Registro']);
        $stmt = $db->prepare($query);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        break;

    case 'events':
        $query = "SELECT e.id, e.nombre, e.tipo_evento, e.fecha_evento, e.ubicacion, e.precio, e.cupo_disponible, 
                  (SELECT COUNT(*) FROM inscripciones WHERE id_evento = e.id AND estado = 'confirmada') as inscritos
                  FROM eventos e ORDER BY fecha_evento DESC";
        fputcsv($output, ['ID', 'Nombre', 'Tipo', 'Fecha', 'Ubicación', 'Precio', 'Cupo Disp.', 'Inscritos']);
        $stmt = $db->prepare($query);
        break;
        
    case 'promociones':
        $query = "SELECT p.id, p.codigo, p.porcentaje, p.cantidad, p.usados, p.fecha_inicio, p.fecha_fin, p.estado, e.nombre as evento
                  FROM promociones p 
                  LEFT JOIN eventos e ON p.id_evento = e.id 
                  ORDER BY p.created_at DESC";
        fputcsv($output, ['ID', 'Código', 'Porcentaje', 'Total', 'Usados', 'Inicio', 'Fin', 'Estado', 'Evento']);
        $stmt = $db->prepare($query);
        break;

    case 'inscriptions':
        $filtro_evento = isset($_GET['evento']) ? intval($_GET['evento']) : (isset($_GET['id']) ? intval($_GET['id']) : 0); // Support both 'evento' and 'id'
        $query = "SELECT i.id, i.numero_corredor, COALESCE(u.nombre, i.nombre) as nombre, COALESCE(u.apellido, i.apellido) as apellido, COALESCE(u.email, i.email) as email, COALESCE(u.telefono, i.telefono) as telefono, e.nombre as evento, i.categoria, i.talla_camiseta, i.estado, i.fecha_inscripcion, IF(i.es_invitado = 1, 'Sí', 'No') as es_invitado, IF(i.es_invitado = 1, CONCAT(u_main.nombre, ' ', u_main.apellido), 'N/A') as usuario_principal
                  FROM inscripciones i
                  LEFT JOIN usuarios u ON i.id_usuario = u.id
                  JOIN eventos e ON i.id_evento = e.id
                  LEFT JOIN inscripciones i_main ON i.parent_id = i_main.id
                  LEFT JOIN usuarios u_main ON i_main.id_usuario = u_main.id
                  WHERE 1=1";
        $params = [];
        $types = '';

        if ($filtro_evento) {
            $query .= " AND i.id_evento = ?";
            $params[] = $filtro_evento;
            $types .= 'i';
        }
        
        $query .= " ORDER BY i.fecha_inscripcion DESC";
        fputcsv($output, ['ID', 'Corredor #', 'Nombre', 'Apellido', 'Email', 'Teléfono', 'Evento', 'Categoría', 'Talla Camiseta', 'Estado', 'Fecha', 'Es Invitado', 'Usuario Principal']);
        $stmt = $db->prepare($query);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        break;

    case 'payments':
        // Replicate Payments Filter Logic
        $filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
        $filtro_metodo = isset($_GET['metodo']) ? $_GET['metodo'] : '';
        $fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
        $fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';
        $busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
        $filtro_promo = isset($_GET['promo_code']) ? trim($_GET['promo_code']) : '';

        $query = "SELECT p.id, u.nombre, u.apellido, e.nombre as evento, p.monto, p.metodo_pago, p.estado, p.fecha_pago, promo.codigo as promo
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
            $params[] = $busqueda_like; $params[] = $busqueda_like; $params[] = $busqueda_like;
            $params[] = $busqueda_like; $params[] = $busqueda_like; $params[] = $busqueda_like;
            $types .= 'ssssss';
        }
        if ($filtro_promo) {
             if ($filtro_promo === 'ANY') {
                $query .= " AND i.id_promocion IS NOT NULL";
            } else {
                $query .= " AND promo.codigo LIKE ?";
                $params[] = "%$filtro_promo%";
                $types .= 's';
            }
        }
        
        $query .= " ORDER BY p.fecha_pago DESC";
        fputcsv($output, ['ID', 'Nombre', 'Apellido', 'Evento', 'Monto', 'Método', 'Estado', 'Fecha Pago', 'Promo Code']);
        $stmt = $db->prepare($query);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        break;

    default:
        die("Tipo inválido");
}

if (isset($stmt)) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    $stmt->close();
}

fclose($output);
$db->close();
?>
