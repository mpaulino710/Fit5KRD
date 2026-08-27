<?php
// modules/events/certificates.php
require_once '../../config/config.php';
require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Obtener todas las inscripciones del usuario y sus detalles de eventos
$query = "SELECT i.id, i.asistencia, i.numero_corredor, i.categoria, i.modalidad, 
                 e.id as id_evento, e.nombre as evento_nombre, e.fecha_evento, e.ubicacion, e.distancia
          FROM inscripciones i
          JOIN eventos e ON i.id_evento = e.id
          WHERE i.id_usuario = ?
          ORDER BY e.fecha_evento DESC";

$stmt = $db->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$my_inscriptions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$certificates_data = [];

foreach ($my_inscriptions as $insc) {
    $event_certs = [];
    
    // 1. Verificar si el usuario asistió y tiene certificado
    if (isset($insc['asistencia']) && $insc['asistencia'] == 1) {
        $event_certs[] = [
            'id' => $insc['id'],
            'tipo' => 'Titular',
            'nombre' => $_SESSION['user_name'] ?? 'Mi Certificado',
            'numero_corredor' => $insc['numero_corredor'] ?: 'N/A',
            'categoria' => $insc['categoria'],
            'modalidad' => $insc['modalidad'] ?: 'General'
        ];
    }
    
    // 2. Buscar invitados asociados a esta inscripción que asistieron
    $guests_query = "SELECT i.id, i.nombre, i.apellido, i.numero_corredor, i.categoria, i.modalidad,
                            u.nombre as reg_nombre, u.apellido as reg_apellido
                     FROM inscripciones i
                     LEFT JOIN usuarios u ON i.id_usuario = u.id
                     WHERE i.parent_id = ? AND i.asistencia = 1";
                     
    $stmt_guests = $db->prepare($guests_query);
    $stmt_guests->bind_param("i", $insc['id']);
    $stmt_guests->execute();
    $guests_result = $stmt_guests->get_result();
    
    while ($guest = $guests_result->fetch_assoc()) {
        $guest_name = $guest['reg_nombre'] 
            ? ($guest['reg_nombre'] . ' ' . $guest['reg_apellido']) 
            : ($guest['nombre'] . ' ' . $guest['apellido']);
            
        if (empty(trim($guest_name))) {
            $guest_name = "Invitado";
        }
        
        $event_certs[] = [
            'id' => $guest['id'],
            'tipo' => 'Invitado',
            'nombre' => $guest_name,
            'numero_corredor' => $guest['numero_corredor'] ?: 'N/A',
            'categoria' => $guest['categoria'],
            'modalidad' => $guest['modalidad'] ?: 'General'
        ];
    }
    $stmt_guests->close();
    
    // Solo mostrar el evento si hay al menos un certificado disponible
    if (!empty($event_certs)) {
        $certificates_data[] = [
            'evento_nombre' => $insc['evento_nombre'],
            'fecha_evento' => $insc['fecha_evento'],
            'ubicacion' => $insc['ubicacion'],
            'distancia' => $insc['distancia'],
            'certificados' => $event_certs
        ];
    }
}

include '../../includes/header.php';
?>

<style>
    .certificates-container {
        max-width: 900px;
        margin: 0 auto;
    }
    .cert-event-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        margin-bottom: 25px;
        overflow: hidden;
        border: 1px solid #eef0f3;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .cert-event-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }
    .cert-event-header {
        background: linear-gradient(135deg, #2c1b4d, #4a0e4e);
        padding: 20px 25px;
        color: #fff;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    .cert-event-title {
        margin: 0;
        font-size: 1.35rem;
        font-weight: 600;
        letter-spacing: 0.5px;
        color: #fff;
    }
    .cert-event-meta {
        font-size: 0.85rem;
        opacity: 0.9;
        display: flex;
        gap: 15px;
        margin-top: 5px;
    }
    .cert-event-meta span {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .cert-badge-distance {
        background: rgba(255,255,255,0.2);
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    .cert-list {
        padding: 15px 25px;
    }
    .cert-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 0;
        border-bottom: 1px solid #f0f2f5;
        gap: 15px;
        flex-wrap: wrap;
    }
    .cert-item:last-child {
        border-bottom: none;
    }
    .cert-item-info {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    .cert-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: #f0ecfa;
        color: #583f99;
        display: flex;
        justify-content: center;
        align-items: center;
        font-weight: 700;
        font-size: 1.1rem;
    }
    .cert-participant-name {
        font-weight: 600;
        color: #2c3e50;
        margin: 0;
        font-size: 1.05rem;
    }
    .cert-participant-meta {
        font-size: 0.8rem;
        color: #7f8c8d;
        margin: 2px 0 0 0;
    }
    .cert-type-badge {
        font-size: 0.75rem;
        padding: 2px 8px;
        border-radius: 10px;
        font-weight: 600;
        margin-left: 8px;
        text-transform: uppercase;
    }
    .badge-titular {
        background-color: #e3f2fd;
        color: #0d47a1;
    }
    .badge-invitado {
        background-color: #efebe9;
        color: #4e342e;
    }
    .btn-download-cert {
        background: linear-gradient(135deg, #d4af37, #aa7c11);
        color: #000;
        font-weight: 700;
        padding: 8px 20px;
        border-radius: 6px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9rem;
        box-shadow: 0 4px 10px rgba(212, 175, 55, 0.2);
        transition: all 0.2s ease;
        border: none;
        cursor: pointer;
    }
    .btn-download-cert:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 15px rgba(212, 175, 55, 0.35);
        color: #000;
    }
    .cert-empty-state {
        text-align: center;
        padding: 50px 30px;
        background: #fff;
        border-radius: 12px;
        border: 1px solid #eef0f3;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }
    .cert-empty-icon {
        font-size: 3.5rem;
        color: #bdc3c7;
        margin-bottom: 20px;
    }
    .cert-empty-state h3 {
        margin: 0 0 10px 0;
        color: #2c3e50;
    }
    .cert-empty-state p {
        color: #7f8c8d;
        max-width: 400px;
        margin: 0 auto 20px auto;
        font-size: 0.95rem;
    }
</style>

<div class="container py-4">
    <div class="certificates-container">
        <h1 class="text-center mb-4">
            <i class="fas fa-certificate text-warning"></i> Mis Certificados de Participación
        </h1>
        <p class="text-center text-muted mb-5">
            Aquí encontrarás tus certificados oficiales y los de tus invitados de los eventos en los que registraste asistencia.
        </p>

        <?php if (!empty($certificates_data)): ?>
            <?php foreach ($certificates_data as $data): 
                $date_obj = strtotime($data['fecha_evento']);
            ?>
                <div class="cert-event-card">
                    <div class="cert-event-header">
                        <div>
                            <h3 class="cert-event-title"><?php echo htmlspecialchars($data['evento_nombre']); ?></h3>
                            <div class="cert-event-meta">
                                <span><i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y', $date_obj); ?></span>
                                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($data['ubicacion']); ?></span>
                            </div>
                        </div>
                        <?php if (!empty($data['distancia'])): ?>
                            <span class="cert-badge-distance"><?php echo floatval($data['distancia']); ?>K</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="cert-list">
                        <?php foreach ($data['certificados'] as $cert): 
                            // Obtener iniciales para el avatar
                            $initials = '';
                            $parts = explode(' ', trim($cert['nombre']));
                            if (count($parts) >= 2) {
                                $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
                            } else {
                                $initials = strtoupper(substr($parts[0], 0, 2));
                            }
                            if (empty($initials)) $initials = "P";
                        ?>
                            <div class="cert-item">
                                <div class="cert-item-info">
                                    <div class="cert-avatar"><?php echo $initials; ?></div>
                                    <div>
                                        <h4 class="cert-participant-name">
                                            <?php echo htmlspecialchars($cert['nombre']); ?>
                                            <span class="cert-type-badge badge-<?php echo strtolower($cert['tipo']); ?>">
                                                <?php echo $cert['tipo'] === 'Titular' ? 'Titular' : 'Invitado'; ?>
                                            </span>
                                        </h4>
                                        <p class="cert-participant-meta">
                                            Dorsal: <strong><?php echo htmlspecialchars($cert['numero_corredor']); ?></strong> | 
                                            Categoría: <?php echo htmlspecialchars(ucfirst($cert['categoria'])); ?> | 
                                            Modalidad: <?php echo htmlspecialchars($cert['modalidad']); ?>
                                        </p>
                                    </div>
                                </div>
                                <div>
                                    <a href="generate-certificate.php?id=<?php echo $cert['id']; ?>&download=1" 
                                       class="btn-download-cert" target="_blank">
                                        <i class="fas fa-download"></i> Descargar PDF
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="cert-empty-state">
                <div class="cert-empty-icon">
                    <i class="fas fa-award"></i>
                </div>
                <h3>No tienes certificados disponibles</h3>
                <p>
                    Aún no cuentas con certificados de participación emitidos. Los certificados están disponibles únicamente para los eventos en los que confirmaste tu asistencia.
                </p>
                <a href="/modules/events/my-inscriptions.php" class="btn btn-primary">
                    <i class="fas fa-clipboard-list"></i> Ver mis inscripciones
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
