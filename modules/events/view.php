<?php
// modules/events/view.php
require_once '../../config/config.php';
require_once '../../config/database.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$db = getDB();
$event_id = intval($_GET['id']);

// Obtener información del evento
$query = "SELECT e.*, u.nombre as organizador_nombre, u.apellido as organizador_apellido 
          FROM eventos e 
          LEFT JOIN usuarios u ON e.id_organizador = u.id 
          WHERE e.id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: index.php');
    exit();
}

$evento = $result->fetch_assoc();
$stmt->close();

$fecha_limite = !empty($evento['fecha_limite_inscripcion']) ? $evento['fecha_limite_inscripcion'] : null;
$inscripcion_abierta = true;
if ($fecha_limite && strtotime($fecha_limite) < time()) {
    $inscripcion_abierta = false;
}

// Procesar modalidades para la vista
$modalidades = !empty($evento['modalidades']) ? json_decode($evento['modalidades'], true) : [];
$tiene_modalidades = !empty($modalidades);
$mostrar_desde = false;

if ($tiene_modalidades) {
    foreach ($modalidades as $mod) {
        if (is_array($mod) && isset($mod['precio']) && $mod['precio'] > $evento['precio']) {
            $mostrar_desde = true;
            break;
        }
    }
}

// Verificar si el usuario está inscrito
$inscrito = false;
$inscripcion_id = null;
$detalles_inscripcion = [];
$invitados = [];
$pago_info = null;

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    // Fetch Main Inscription
    $query = "SELECT * FROM inscripciones WHERE id_usuario = ? AND id_evento = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("ii", $user_id, $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $inscripcion = $result->fetch_assoc();
        $inscrito = true;
        $inscripcion_id = $inscripcion['id'];
        $estado_inscripcion = $inscripcion['estado'];
        $detalles_inscripcion = $inscripcion;
        
        // Fetch Guests
        $query_guests = "SELECT * FROM inscripciones WHERE parent_id = ?";
        $stmt_g = $db->prepare($query_guests);
        $stmt_g->bind_param("i", $inscripcion_id);
        $stmt_g->execute();
        $res_guests = $stmt_g->get_result();
        while($guest = $res_guests->fetch_assoc()) {
            $invitados[] = $guest;
        }
        $stmt_g->close();
        
        // Fetch Payment Info
        $query_pago = "SELECT * FROM pagos WHERE id_inscripcion = ? ORDER BY id DESC LIMIT 1";
        $stmt_p = $db->prepare($query_pago);
        $stmt_p->bind_param("i", $inscripcion_id);
        $stmt_p->execute();
        $res_pago = $stmt_p->get_result();
        if ($res_pago->num_rows > 0) {
            $pago_info = $res_pago->fetch_assoc();
        }
        $stmt_p->close();
    }
    $stmt->close();
}

// Obtener total de inscritos
$query = "SELECT COUNT(*) as total FROM inscripciones WHERE id_evento = ? AND estado = 'confirmada'";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();
$total_inscritos = $result->fetch_assoc()['total'];
$stmt->close();

// Obtener comentarios/reviews
$query = "SELECT c.*, u.nombre, u.apellido 
          FROM comentarios c 
          JOIN usuarios u ON c.id_usuario = u.id 
          WHERE c.id_evento = ? 
          ORDER BY c.fecha DESC 
          LIMIT 10";
// Nota: Necesitarías crear la tabla comentarios si quieres esta funcionalidad

include '../../includes/header.php';
?>
<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

<div class="container">
    <!-- Breadcrumb -->
    <div style="margin-bottom: 2rem;">
        <a href="index.php" style="color: var(--primary-color); text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Volver a Eventos
        </a>
    </div>
    
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
        <!-- Información Principal del Evento -->
        <div>
            <div style="
                background: white;
                border-radius: var(--border-radius);
                padding: 2rem;
                box-shadow: var(--box-shadow);
                margin-bottom: 2rem;
            ">
                <!-- Imagen del Evento -->
                <div style="
                    height: 300px;
                    width: 300px;
                    background: <?php echo $evento['imagen_url'] ? 'url(\'../../' . htmlspecialchars($evento['imagen_url']) . '\')' : 'linear-gradient(135deg, var(--primary-color), var(--primary-dark))'; ?>;
                    background-size: cover;
                    background-position: center;
                    border-radius: var(--border-radius);
                    margin-bottom: 2rem;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-size: 2rem;
                    font-weight: bold;
                    text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
                ">
                    <?php if (!$evento['imagen_url']): ?>
                        <?php echo substr($evento['nombre'], 0, 2); ?>
                    <?php endif; ?>
                </div>
                
                <h1 style="color: var(--primary-color); margin-bottom: 1rem;">
                    <?php echo htmlspecialchars($evento['nombre']); ?>
                </h1>
                
                <div style="
                    display: flex;
                    gap: 1rem;
                    margin-bottom: 1.5rem;
                    flex-wrap: wrap;
                ">
                    <?php if ($evento['distancia'] > 0): ?>
                    <span style="
                        background: var(--primary-light);
                        color: white;
                        padding: 0.5rem 1rem;
                        border-radius: 20px;
                        font-size: 0.9rem;
                        display: inline-flex;
                        align-items: center;
                        gap: 5px;
                    ">
                        <i class="fas fa-route"></i>
                        <?php echo $evento['distancia']; ?> km
                    </span>
                    <?php endif; ?>
                    
                    <span style="
                        background: #6f42c1;
                        color: white;
                        padding: 0.5rem 1rem;
                        border-radius: 20px;
                        font-size: 0.9rem;
                        display: inline-flex;
                        align-items: center;
                        gap: 5px;
                    ">
                        <i class="fas fa-tag"></i>
                        <?php echo isset($evento['tipo_evento']) ? ucfirst($evento['tipo_evento']) : 'Carrera'; ?>
                    </span>
                    
                    <?php if ($evento['precio'] > 0): ?>
                    <span style="
                        background: var(--warning);
                        color: white;
                        padding: 0.5rem 1rem;
                        border-radius: 20px;
                        font-size: 0.9rem;
                        display: inline-flex;
                        align-items: center;
                        gap: 5px;
                    ">
                        <i class="fas fa-tag"></i>
                        <?php echo ($mostrar_desde ? 'Desde ' : '') . '$' . number_format($evento['precio'], 2); ?>
                    </span>
                    <?php endif; ?>
                    
                    <span style="
                        background: <?php echo $evento['estado'] === 'activo' ? 'var(--success)' : ($evento['estado'] === 'cancelado' ? 'var(--danger)' : 'var(--secondary)'); ?>;
                        color: white;
                        padding: 0.5rem 1rem;
                        border-radius: 20px;
                        font-size: 0.9rem;
                        display: inline-flex;
                        align-items: center;
                        gap: 5px;
                    ">
                        <i class="fas fa-<?php echo $evento['estado'] === 'activo' ? 'check-circle' : ($evento['estado'] === 'cancelado' ? 'times-circle' : 'calendar-check'); ?>"></i>
                        <?php echo ucfirst($evento['estado']); ?>
                    </span>
                </div>
                
                <?php if ($evento['descripcion']): ?>
                <div style="margin-bottom: 2rem;">
                    <h3 style="color: var(--text-dark); margin-bottom: 0.5rem;">
                        <i class="fas fa-info-circle"></i> Descripción
                    </h3>
                    <p style="color: var(--text-light); line-height: 1.6; white-space: pre-line;">
                        <?php echo nl2br(htmlspecialchars($evento['descripcion'])); ?>
                    </p>
                </div>
                <?php endif; ?>

                <?php if ($tiene_modalidades): ?>
                <div style="margin-bottom: 2rem;">
                    <h3 style="color: var(--text-dark); margin-bottom: 0.5rem;">
                        <i class="fas fa-list-ul"></i> Opciones Disponibles
                    </h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                        <?php foreach ($modalidades as $mod): 
                            $nombre = is_array($mod) ? $mod['nombre'] : $mod;
                            $precio = is_array($mod) ? $mod['precio'] : $evento['precio'];
                        ?>
                        <div style="
                            background: var(--bg-light);
                            border: 1px solid #eee;
                            padding: 0.8rem 1.2rem;
                            border-radius: 8px;
                            display: flex;
                            align-items: center;
                            gap: 10px;
                        ">
                            <i class="fas fa-check" style="color: var(--success); font-size: 0.8rem;"></i>
                            <span style="font-weight: 500;"><?php echo htmlspecialchars($nombre); ?></span>
                            <?php if ($precio > 0): ?>
                            <span style="
                                background: white;
                                padding: 2px 8px;
                                border-radius: 12px;
                                font-size: 0.85rem;
                                border: 1px solid #ddd;
                                color: var(--text-light);
                            ">$<?php echo number_format($precio, 2); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div style="
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 1.5rem;
                    margin-bottom: 2rem;
                ">
                    <div style="
                        background: var(--bg-light);
                        padding: 1rem;
                        border-radius: var(--border-radius);
                    ">
                        <div style="color: var(--primary-color); margin-bottom: 0.5rem;">
                            <i class="fas fa-calendar-alt"></i>
                            <strong>Fecha y Hora</strong>
                        </div>
                        <div style="color: var(--text-dark);">
                            <?php echo date('d/m/Y h:i A', strtotime($evento['fecha_evento'])); ?>
                        </div>
                    </div>
                    
                    <div style="
                        background: var(--bg-light);
                        padding: 1rem;
                        border-radius: var(--border-radius);
                    ">
                        <div style="color: var(--primary-color); margin-bottom: 0.5rem;">
                            <i class="fas fa-map-marker-alt"></i>
                            <strong>Ubicación</strong>
                        </div>
                        <div style="color: var(--text-dark);">
                            <?php echo htmlspecialchars($evento['ubicacion']); ?>
                        </div>
                    </div>
                    
                    <!-- <div style="
                        background: var(--bg-light);
                        padding: 1rem;
                        border-radius: var(--border-radius);
                    ">
                        <div style="color: var(--primary-color); margin-bottom: 0.5rem;">
                            <i class="fas fa-users"></i>
                            <strong>Participantes</strong>
                        </div>
                        <div style="color: var(--text-dark);">
                            <?php echo $total_inscritos; ?> / <?php echo $evento['cupo_maximo']; ?> inscritos
                        </div>
                    </div> -->
                </div>
                
                <?php if ($evento['organizador_nombre']): ?>
                <div style="
                    background: var(--bg-light);
                    padding: 1rem;
                    border-radius: var(--border-radius);
                    margin-bottom: 2rem;
                ">
                    <div style="color: var(--primary-color); margin-bottom: 0.5rem;">
                        <i class="fas fa-user-tie"></i>
                        <strong>Organizado por</strong>
                    </div>
                    <div style="color: var(--text-dark);">
                        <?php echo htmlspecialchars($evento['organizador_nombre'] . ' ' . $evento['organizador_apellido']); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Mapa -->
            <?php if (!empty($evento['latitud']) && !empty($evento['longitud'])): ?>
            <div style="
                background: white;
                border-radius: var(--border-radius);
                padding: 2rem;
                box-shadow: var(--box-shadow);
                margin-bottom: 2rem;
            ">
                <h3 style="color: var(--primary-color); margin-bottom: 1rem;">
                    <i class="fas fa-map"></i> Ubicación en el Mapa
                </h3>
                <div id="map" style="
                    height: 300px;
                    width: 100%;
                    border-radius: var(--border-radius);
                    z-index: 1;
                " data-lat="<?php echo $evento['latitud']; ?>" data-lng="<?php echo $evento['longitud']; ?>"></div>
            </div>
            <?php else: ?>
            <div style="
                background: white;
                border-radius: var(--border-radius);
                padding: 2rem;
                box-shadow: var(--box-shadow);
                margin-bottom: 2rem;
            ">
                 <h3 style="color: var(--primary-color); margin-bottom: 1rem;">
                    <i class="fas fa-map"></i> Ubicación en el Mapa
                </h3>
                <div style="
                    height: 300px;
                    background: #f5f5f5;
                    border-radius: var(--border-radius);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: var(--text-light);
                ">
                    <i class="fas fa-map-marker-alt" style="font-size: 2rem; margin-right: 1rem;"></i>
                    <span>Mapa no disponible</span>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Panel Lateral -->
        <div>
            <!-- Acciones -->
            <div style="
                background: white;
                border-radius: var(--border-radius);
                padding: 2rem;
                box-shadow: var(--box-shadow);
                margin-bottom: 2rem;
                text-align: center;
            ">
                <?php if ($evento['estado'] === 'activo'): ?>
                    <?php if (isset($_SESSION['user_id']) && $inscrito): ?>
                        <?php if ($estado_inscripcion === 'confirmada'): ?>
                        <div style="margin-bottom: 1.5rem;">
                            <div style="
                                background: var(--success);
                                color: white;
                                padding: 1rem;
                                border-radius: var(--border-radius);
                                margin-bottom: 1rem;
                            ">
                                <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                                <h3>¡Ya estás inscrito!</h3>
                            </div>
                        <a href="register.php?action=manage&id=<?php echo $inscripcion_id; ?>" 
                               class="btn btn-primary" style="width: 100%; margin-bottom: 1rem;">
                                <i class="fas fa-edit"></i> Gestionar Inscripción
                            </a>
                            
                            <!-- Detalles del Registro -->
                            <div style="text-align: left; background: #f8f9fa; padding: 1rem; border-radius: 4px; font-size: 0.9rem;">
                                <h5 style="color: var(--primary-color); border-bottom: 1px solid #ddd; padding-bottom: 0.5rem; margin-bottom: 0.5rem;">
                                    Detalles de tu Registro
                                </h5>
                                
                                <div style="margin-bottom: 0.5rem;">
                                    <strong>Participante Principal:</strong><br>
                                    <?php echo htmlspecialchars($detalles_inscripcion['numero_corredor']); ?> <br>
                                    <span class="badge badge-info"><?php echo htmlspecialchars($detalles_inscripcion['modalidad'] ?: 'General'); ?></span>
                                </div>
                                
                                <?php if (!empty($invitados)): ?>
                                <div style="margin-bottom: 0.5rem;">
                                    <strong>Invitados (<?php echo count($invitados); ?>):</strong>
                                    <ul style="padding-left: 1.2rem; margin-bottom: 0;">
                                        <?php foreach ($invitados as $inv): ?>
                                        <li>
                                            <?php echo htmlspecialchars($inv['nombre'] . ' ' . $inv['apellido']); ?>
                                            <small class="text-muted">(<?php echo htmlspecialchars($inv['modalidad'] ?: 'General'); ?>)</small>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($pago_info): ?>
                                <div style="margin-top: 1rem; border-top: 1px dashed #ccc; padding-top: 0.5rem;">
                                    <div style="display: flex; justify-content: space-between;">
                                        <strong>Total:</strong>
                                        <span>$<?php echo number_format($pago_info['monto'], 2); ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.3rem;">
                                        <strong>Estado Pago:</strong>
                                        <?php 
                                        $estado_pago = $pago_info['estado'];
                                        $badge_class = 'badge-secondary';
                                        if ($estado_pago == 'aprobado' || $estado_pago == 'pagado') $badge_class = 'badge-success';
                                        elseif ($estado_pago == 'pendiente') $badge_class = 'badge-warning';
                                        elseif ($estado_pago == 'rechazado') $badge_class = 'badge-danger';
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($estado_pago); ?></span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php elseif ($estado_inscripcion === 'pendiente'): ?>
                        <div style="margin-bottom: 1.5rem;">
                            <div style="
                                background: var(--warning);
                                color: white;
                                padding: 1rem;
                                border-radius: var(--border-radius);
                                margin-bottom: 1rem;
                            ">
                                <i class="fas fa-clock" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                                <h3>Inscripción Pendiente</h3>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php elseif (!$inscripcion_abierta): ?>
                        <div style="
                            background: var(--danger);
                            color: white;
                            padding: 1.5rem 1rem;
                            border-radius: var(--border-radius);
                            margin-bottom: 1rem;
                            text-align: center;
                        ">
                            <i class="fas fa-times-circle" style="font-size: 2.5rem; margin-bottom: 0.5rem;"></i>
                            <h3 style="margin-bottom: 0.5rem;">Inscripción Cerrada</h3>
                            <p style="margin-bottom: 0; font-size: 0.95rem;">La fecha límite para inscribirse ha pasado (<?php echo date('d/m/Y h:i A', strtotime($fecha_limite)); ?>)</p>
                        </div>
                    <?php else: ?>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php if ($evento['cupo_disponible'] > 0): ?>
                            <div style="margin-bottom: 1.5rem;">
                                <div style="
                                    background: var(--primary-light);
                                    color: white;
                                    padding: 1rem;
                                    border-radius: var(--border-radius);
                                    margin-bottom: 1rem;
                                ">
                                    <i class="fas fa-running" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                                    <h3>¡Únete a esta carrera!</h3>
                                    <p><?php echo $evento['cupo_disponible']; ?> cupos disponibles</p>
                                </div>
                                <a href="register.php?event_id=<?php echo $evento['id']; ?>" 
                                   class="btn btn-success" style="width: 100%; margin-bottom: 1rem;">
                                    <i class="fas fa-user-plus"></i> Inscribirme Ahora
                                </a>
                            </div>
                            <?php else: ?>
                            <div style="
                                background: var(--danger);
                                color: white;
                                padding: 1rem;
                                border-radius: var(--border-radius);
                                margin-bottom: 1rem;
                            ">
                                <i class="fas fa-times-circle" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                                <h3>¡Evento Lleno!</h3>
                                <p>No hay cupos disponibles</p>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div style="margin-bottom: 1.5rem;">
                                <div style="
                                    background: var(--primary-color);
                                    color: white;
                                    padding: 1rem;
                                    border-radius: var(--border-radius);
                                    margin-bottom: 1rem;
                                ">
                                    <i class="fas fa-sign-in-alt" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                                    <h3>Regístrate para participar</h3>
                                </div>
                                <a href="../auth/login.php" class="btn btn-primary" style="width: 100%; margin-bottom: 0.5rem;">
                                    <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                                </a>
                                <a href="../auth/register.php" class="btn btn-secondary" style="width: 100%;">
                                    <i class="fas fa-user-plus"></i> Crear Cuenta
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php elseif ($evento['estado'] === 'cancelado'): ?>
                <div style="
                    background: var(--danger);
                    color: white;
                    padding: 1rem;
                    border-radius: var(--border-radius);
                ">
                    <i class="fas fa-times-circle" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                    <h3>Evento Cancelado</h3>
                </div>
                <?php else: ?>
                <div style="
                    background: var(--secondary);
                    color: white;
                    padding: 1rem;
                    border-radius: var(--border-radius);
                ">
                    <i class="fas fa-flag-checkered" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                    <h3>Evento Completado</h3>
                </div>
                <?php endif; ?>
                
                <!-- Compartir -->
                <div style="border-top: 1px solid #eee; padding-top: 1.5rem;">
                    <h4 style="color: var(--text-dark); margin-bottom: 1rem;">Compartir evento</h4>
                    <div style="display: flex; gap: 0.5rem; justify-content: center;">
                        <button onclick="shareEvent('facebook')" style="
                            background: #3b5998;
                            color: white;
                            border: none;
                            width: 40px;
                            height: 40px;
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            cursor: pointer;
                        ">
                            <i class="fab fa-facebook-f"></i>
                        </button>
                        <button onclick="shareEvent('twitter')" style="
                            background: #1da1f2;
                            color: white;
                            border: none;
                            width: 40px;
                            height: 40px;
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            cursor: pointer;
                        ">
                            <i class="fab fa-twitter"></i>
                        </button>
                        <button onclick="shareEvent('whatsapp')" style="
                            background: #25d366;
                            color: white;
                            border: none;
                            width: 40px;
                            height: 40px;
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            cursor: pointer;
                        ">
                            <i class="fab fa-whatsapp"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Información Importante -->
            <div style="
                background: white;
                border-radius: var(--border-radius);
                padding: 2rem;
                box-shadow: var(--box-shadow);
                margin-bottom: 2rem;
            ">
                <h3 style="color: var(--primary-color); margin-bottom: 1rem;">
                    <i class="fas fa-info-circle"></i> Información Importante
                </h3>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 0.8rem; display: flex; gap: 10px;">
                        <i class="fas fa-clock" style="color: var(--primary-color);"></i>
                        <span>Registro: 20 minutos antes del evento </span>
                    </li>
                    <li style="margin-bottom: 0.8rem; display: flex; gap: 10px;">
                        <i class="fas fa-medal" style="color: var(--primary-color);"></i>
                        <span>Medallas para todos los participantes</span>
                    </li>
                    <li style="margin-bottom: 0.8rem; display: flex; gap: 10px;">
                        <i class="fas fa-tint" style="color: var(--primary-color);"></i>
                        <span>Estaciones de hidratación</span>
                    </li>
                    <li style="display: flex; gap: 10px;">
                        <i class="fas fa-ambulance" style="color: var(--primary-color);"></i>
                        <span>Servicio médico emergencias</span>
                    </li>
                </ul>
            </div>
            
            <!-- Contador Regresivo -->
            <?php if ($evento['estado'] === 'activo'): ?>
            <div style="
                background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
                color: white;
                border-radius: var(--border-radius);
                padding: 2rem;
                box-shadow: var(--box-shadow);
                text-align: center;
            ">
                <h3 style="margin-bottom: 1rem;">
                    <i class="fas fa-hourglass-half"></i> El evento comienza en:
                </h3>
                <div id="countdown" class="event-countdown" 
                     data-date="<?php echo $evento['fecha_evento']; ?>"
                     style="
                        display: flex;
                        justify-content: center;
                        gap: 1rem;
                        margin-bottom: 1rem;
                     ">
                </div>
                <p style="font-size: 0.9rem; opacity: 0.9;">
                    <?php echo date('d/m/Y h:i A', strtotime($evento['fecha_evento'])); ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Contador regresivo
function updateCountdown() {
    const countdownElement = document.getElementById('countdown');
    if (!countdownElement) return;
    
    const eventDate = new Date(countdownElement.dataset.date).getTime();
    const now = new Date().getTime();
    const distance = eventDate - now;
    
    if (distance < 0) {
        countdownElement.innerHTML = '<span style="font-size: 1.2rem;">¡El evento ha comenzado!</span>';
        return;
    }
    
    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
    
    countdownElement.innerHTML = `
        <div style="text-align: center;">
            <div style="font-size: 2rem; font-weight: bold;">${days}</div>
            <div style="font-size: 0.8rem;">días</div>
        </div>
        <div style="text-align: center;">
            <div style="font-size: 2rem; font-weight: bold;">${hours}</div>
            <div style="font-size: 0.8rem;">horas</div>
        </div>
        <div style="text-align: center;">
            <div style="font-size: 2rem; font-weight: bold;">${minutes}</div>
            <div style="font-size: 0.8rem;">minutos</div>
        </div>
        <div style="text-align: center;">
            <div style="font-size: 2rem; font-weight: bold;">${seconds}</div>
            <div style="font-size: 0.8rem;">segundos</div>
        </div>
    `;
}

// Inicializar contador
if (document.getElementById('countdown')) {
    updateCountdown();
    setInterval(updateCountdown, 1000);
}

// Compartir evento
function shareEvent(platform) {
    const url = window.location.href;
    const title = document.title;
    const text = '¡Mira este evento de carrera 5K!';
    
    let shareUrl = '';
    
    switch(platform) {
        case 'facebook':
            shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
            break;
        case 'twitter':
            shareUrl = `https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}`;
            break;
        case 'whatsapp':
            shareUrl = `https://api.whatsapp.com/send?text=${encodeURIComponent(text + ' ' + url)}`;
            break;
    }
    
    if (shareUrl) {
        window.open(shareUrl, '_blank', 'width=600,height=400');
    }
}
// Leaflet JS
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const mapElement = document.getElementById('map');
    if (mapElement && mapElement.dataset.lat && mapElement.dataset.lng) {
        const lat = parseFloat(mapElement.dataset.lat);
        const lng = parseFloat(mapElement.dataset.lng);
        
        const map = L.map('map').setView([lat, lng], 13);
        
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);
        
        L.marker([lat, lng]).addTo(map)
            .bindPopup("<b><?php echo htmlspecialchars($evento['nombre']); ?></b><br><?php echo htmlspecialchars($evento['ubicacion']); ?>");
    }
});
</script>

<?php include '../../includes/footer.php'; ?>