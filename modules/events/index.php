<?php
// Archivo: modules/events/index.php
require_once '../../config/config.php';
require_once '../../config/database.php';

$db = getDB();

// Filtros
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'todos';
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

// Construir consulta base
$query = "SELECT e.*, u.nombre as organizador_nombre 
          FROM eventos e 
          LEFT JOIN usuarios u ON e.id_organizador = u.id 
          WHERE e.estado = 'activo'";

// Aplicar filtros
if ($filtro === 'proximos') {
    $query .= " AND e.fecha_evento > NOW()";
} elseif ($filtro === 'pasados') {
    $query .= " AND e.fecha_evento < NOW()";
}

if (!empty($busqueda)) {
    $busqueda_like = "%$busqueda%";
    $query .= " AND (e.nombre LIKE ? OR e.ubicacion LIKE ? OR e.descripcion LIKE ?)";
}

$query .= " ORDER BY e.fecha_evento ASC";

// Preparar y ejecutar consulta
if (!empty($busqueda)) {
    $stmt = $db->prepare($query);
    $stmt->bind_param("sss", $busqueda_like, $busqueda_like, $busqueda_like);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $db->query($query);
}

$eventos = $result->fetch_all(MYSQLI_ASSOC);
    
// Obtener inscripciones del usuario si está logueado
$inscripciones_usuario_ids = [];
$mis_inscripciones = [];

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // 1. Obtener IDs para verificar inscripción en el listado general
    $query_ids = "SELECT id_evento FROM inscripciones WHERE id_usuario = ? AND estado != 'cancelada'";
    $stmt = $db->prepare($query_ids);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result_ids = $stmt->get_result();
    while ($row = $result_ids->fetch_assoc()) {
        $inscripciones_usuario_ids[] = $row['id_evento'];
    }
    $stmt->close();

    // 2. Obtener detalles completos de las inscripciones para la pestaña "Mis Inscripciones"
    $inscripciones_query = "SELECT i.*, e.nombre as evento_nombre, e.fecha_evento, e.ubicacion, e.imagen_url,
                           p.estado as estado_pago, e.distancia, e.precio, i.numero_corredor
                           FROM inscripciones i 
                           JOIN eventos e ON i.id_evento = e.id 
                           LEFT JOIN pagos p ON p.id_inscripcion = i.id
                           WHERE i.id_usuario = ? 
                           ORDER BY i.fecha_inscripcion DESC";
    $stmt = $db->prepare($inscripciones_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $mis_inscripciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

include '../../includes/header.php';
?>

<style>
    /* Estilos para Tabs */
    .tabs-container {
        margin-bottom: 2rem;
    }
    .tabs-header {
        display: flex;
        justify-content: center;
        margin-bottom: 2rem;
        border-bottom: 2px solid #eee;
    }
    .tab-btn {
        padding: 1rem 2rem;
        font-size: 1.1rem;
        font-weight: 500;
        color: var(--text-light);
        background: none;
        border: none;
        border-bottom: 3px solid transparent;
        cursor: pointer;
        transition: all 0.3s;
        margin-bottom: -2px;
    }
    .tab-btn:hover {
        color: var(--primary-color);
    }
    .tab-btn.active {
        color: var(--primary-color);
        border-bottom-color: var(--primary-color);
        font-weight: 600;
    }
    .tab-content {
        display: none;
        animation: fadeIn 0.3s ease;
    }
    .tab-content.active {
        display: block;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* Estilos para Modal QR */
    .qr-modal {
        display: none; 
        position: fixed; 
        z-index: 1000; 
        left: 0;
        top: 0;
        width: 100%; 
        height: 100%; 
        background-color: rgba(0,0,0,0.5); 
        backdrop-filter: blur(5px);
    }
    .qr-modal-content {
        background-color: #fefefe;
        margin: 5% auto; 
        padding: 0;
        border: none;
        width: 90%;
        max-width: 450px;
        border-radius: 12px;
        position: relative;
        animation: slideIn 0.3s;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        overflow: hidden;
    }
    .qr-modal-header {
        background: var(--primary-color);
        color: white;
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .qr-modal-header h2 {
        margin: 0;
        font-size: 1.2rem;
        color: white;
    }
    .qr-modal-body {
        padding: 25px;
        text-align: center;
    }
    .qr-modal-footer {
        padding: 20px;
        background: #f8f9fa;
        text-align: center;
        display: flex;
        gap: 15px;
        justify-content: center;
        border-top: 1px solid #eee;
    }
    .close-modal {
        color: white;
        font-size: 24px;
        font-weight: bold;
        cursor: pointer;
        opacity: 0.8;
        transition: opacity 0.2s;
        background: none;
        border: none;
        padding: 0;
    }
    .close-modal:hover {
        opacity: 1;
    }
    #qrcode {
        margin: 20px auto;
        display: flex;
        justify-content: center;
        padding: 15px;
        background: white;
        border-radius: 8px;
        border: 1px solid #eee;
    }
    .ticket-info {
        text-align: left;
        margin-bottom: 20px;
        border-left: 4px solid var(--primary-color);
        padding: 15px;
        background: #f8f9fa;
        border-radius: 4px;
    }
    .ticket-info p {
        margin: 8px 0;
        font-size: 0.95rem;
        display: flex;
        align-items: flex-start;
    }
    .ticket-info i {
        color: var(--primary-color);
        width: 25px;
        margin-top: 3px;
    }
    .ticket-label {
        font-weight: 600;
        color: #333;
        margin-right: 5px;
    }
    @keyframes slideIn {
        from {top: -50px; opacity: 0;}
        to {top: 0; opacity: 1;}
    }
</style>

<div class="container">
    <h1 class="section-title" style="margin-bottom: 2rem; text-align: center;">Eventos</h1>
    
    <?php if (isset($_SESSION['user_id'])): ?>
        <!-- Sistema de Tabs para usuarios logueados -->
        <div class="tabs-container">
            <div class="tabs-header">
                <button class="tab-btn active" onclick="switchTab('proximos')">
                    <i class="fas fa-calendar-alt"></i> Próximos Eventos
                </button>
                <button class="tab-btn" onclick="switchTab('mis-inscripciones')">
                    <i class="fas fa-running"></i> Mis Inscripciones
                </button>
            </div>

            <!-- Tab 1: Próximos Eventos (Búsqueda + Grid) -->
            <div id="tab-proximos" class="tab-content active">
                <!-- Filtros y Búsqueda -->
                <div class="form-container" style="margin-bottom: 2rem;">
                    <form method="GET" action="" class="events-filter-form">
                        <input type="hidden" name="tab" value="proximos">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="busqueda">Buscar carreras</label>
                            <input type="text" id="busqueda" name="busqueda" class="form-control" 
                                   placeholder="Buscar por nombre, ubicación..." value="<?php echo htmlspecialchars($busqueda); ?>">
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="filtro">Filtrar por</label>
                            <select id="filtro" name="filtro" class="form-control">
                                <option value="todos" <?php echo $filtro === 'todos' ? 'selected' : ''; ?>>Todos los eventos</option>
                                <option value="proximos" <?php echo $filtro === 'proximos' ? 'selected' : ''; ?>>Próximos eventos</option>
                                <option value="pasados" <?php echo $filtro === 'pasados' ? 'selected' : ''; ?>>Eventos pasados</option>
                            </select>
                        </div>
                        
                        <div>
                            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;">
                                <i class="fas fa-search"></i> Buscar
                            </button>
                        </div>
                    </form>
                </div>

                <?php if (!empty($eventos)): ?>
                    <div class="events-grid">
                        <?php foreach ($eventos as $evento): 
                            $fecha_evento = strtotime($evento['fecha_evento']);
                            $es_pasado = $fecha_evento < time();
                        ?>
                        <div class="event-card <?php echo $es_pasado ? 'evento-pasado' : ''; ?>">
                            <div class="event-header">
                                <div class="event-image">
                                    <span class="event-initials"><?php echo substr($evento['nombre'], 0, 2); ?></span>
                                </div>
                                <div class="event-basic-info">
                                    <h3 class="event-title"><?php echo htmlspecialchars($evento['nombre']); ?></h3>
                                    <div class="event-date">
                                        <i class="fas fa-calendar-alt"></i>
                                        <?php echo date('d/m/Y h:i A', $fecha_evento); ?>
                                        <?php if ($es_pasado): ?>
                                            <span style="background: #6c757d; color: white; padding: 0.2rem 0.5rem; border-radius: 3px; font-size: 0.8rem; margin-left: 1rem;">
                                                Completado
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="event-details">
                                <div class="detail-row">
                                    <div class="detail-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo htmlspecialchars($evento['ubicacion']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-route"></i>
                                        <span><?php echo $evento['distancia']; ?> km</span>
                                    </div>
                                </div>
                                
                                <div class="detail-row">
                                    <div class="detail-item">
                                        <i class="fas fa-users"></i>
                                        <span>Cupos: Disponibles <!-- <?php echo $evento['cupo_disponible']; ?>/<?php echo $evento['cupo_maximo']; ?> --></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-tag"></i>
                                        <span><?php 
                                            $modalidades = !empty($evento['modalidades']) ? json_decode($evento['modalidades'], true) : [];
                                            $mostrar_desde = false;
                                            foreach ($modalidades as $mod) {
                                                if (is_array($mod) && isset($mod['precio']) && $mod['precio'] > $evento['precio']) {
                                                    $mostrar_desde = true;
                                                    break;
                                                }
                                            }
                                            echo ($mostrar_desde ? 'Desde ' : '') . '$' . number_format($evento['precio'], 2); 
                                        ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="event-actions">
                                <a href="view.php?id=<?php echo $evento['id']; ?>" class="btn btn-outline">
                                    <i class="fas fa-eye"></i> Detalles
                                </a>
                                <?php if (!$es_pasado && $evento['cupo_disponible'] > 0): ?>
                                    <?php if (in_array($evento['id'], $inscripciones_usuario_ids)): ?>
                                    <span class="btn btn-secondary" style="background-color: #6c757d; cursor: default;">
                                        <i class="fas fa-check-circle"></i> Inscrito
                                    </span>
                                    <?php else: ?>
                                    <a href="register.php?event_id=<?php echo $evento['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-user-plus"></i> Únete
                                    </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-events">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No se encontraron eventos</h3>
                        <p>No hay eventos que coincidan con tu búsqueda.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tab 2: Mis Inscripciones -->
            <div id="tab-mis-inscripciones" class="tab-content">
                <?php if (!empty($mis_inscripciones)): ?>
                    <div class="events-grid">
                        <?php foreach ($mis_inscripciones as $inscripcion): ?>
                        <div class="event-card" style="border-left: 4px solid var(--primary-color);">
                            <div class="event-header">
                                <div class="event-image">
                                    <span class="event-initials"><?php echo substr($inscripcion['evento_nombre'], 0, 2); ?></span>
                                </div>
                                <div class="event-basic-info">
                                    <h3 class="event-title"><?php echo htmlspecialchars($inscripcion['evento_nombre']); ?></h3>
                                    <div class="event-date">
                                        <i class="fas fa-calendar-alt"></i>
                                        <?php echo date('d/m/Y h:i A', strtotime($inscripcion['fecha_evento'])); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="event-details">
                                <div class="detail-row">
                                    <div class="detail-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo htmlspecialchars($inscripcion['ubicacion']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-hashtag"></i>
                                        <span>Dorsal: <?php echo $inscripcion['numero_corredor'] ?: 'Pendiente'; ?></span>
                                    </div>
                                </div>
                                
                                <div class="detail-row" style="margin-top: 10px;">
                                    <div class="detail-item">
                                        <?php
                                        $status_config = [
                                            'confirmada' => ['color' => '#28a745', 'text' => 'Confirmada'],
                                            'pendiente' => ['color' => '#ffc107', 'text' => 'Pendiente'],
                                            'cancelada' => ['color' => '#dc3545', 'text' => 'Cancelada']
                                        ];
                                        $est = $inscripcion['estado'];
                                        $conf = $status_config[$est] ?? $status_config['pendiente'];
                                        ?>
                                        <span style="color: <?php echo $conf['color']; ?>; font-weight: bold;">
                                            <i class="fas fa-info-circle"></i> <?php echo $conf['text']; ?>
                                        </span>
                                    </div>
                                    <div class="detail-item">
                                        <span style="color: <?php echo $inscripcion['estado_pago'] === 'completado' ? '#28a745' : '#ffc107'; ?>">
                                            <i class="fas fa-credit-card"></i> <?php echo ucfirst($inscripcion['estado_pago']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="event-actions" style="display: flex; gap: 10px; align-items: center;">
                                <a href="view.php?id=<?php echo $inscripcion['id_evento']; ?>" class="btn btn-outline" style="flex: 1; height: 45px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-eye" style="margin-right: 8px;"></i>Detalles
                                </a>
                                <?php if ($inscripcion['estado_pago'] === 'completado'): 
                                    $qrData = [
                                        'evento' => $inscripcion['evento_nombre'],
                                        'fecha' => date('d/m/Y h:i A', strtotime($inscripcion['fecha_evento'])),
                                        'ubicacion' => $inscripcion['ubicacion'],
                                        'corredor' => $_SESSION['user_name'],
                                        'dorsal' => $inscripcion['numero_corredor'] ?: 'Pendiente',
                                        'ticket_id' => $inscripcion['id']
                                    ];
                                ?>
                                <button type="button" class="btn btn-primary" style="flex: 1; height: 45px; display: flex; align-items: center; justify-content: center;" onclick='showQR(<?php echo json_encode($qrData, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                    <i class="fas fa-qrcode" style="margin-right: 8px;"></i>Cód. QR
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-events">
                        <i class="fas fa-running"></i>
                        <h3>No tienes inscripciones activas</h3>
                        <p>¡Inscríbete en nuestros eventos para verlos aquí!</p>
                        <button class="btn btn-primary" onclick="switchTab('proximos')">
                            Ver Eventos Disponibles
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
            function switchTab(tabName) {
                // Update buttons
                document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
                event.currentTarget.classList.add('active'); // Needs 'event' from onclick, or select by text
                
                // If called via JS (bottom button), find the button by onclick attribute to activate it visually
                if (!event.currentTarget.classList.contains('tab-btn')) {
                    document.querySelector(`button[onclick="switchTab('${tabName}')"]`).classList.add('active');
                }

                // Update content
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                document.getElementById('tab-' + tabName).classList.add('active');
            }
        </script>

    <?php else: ?>
        <!-- Vista estándar para usuarios NO logueados (Copia exacta de lo anterior sin tabs) -->
        <div class="form-container" style="margin-bottom: 2rem;">
            <form method="GET" action="" class="events-filter-form">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="busqueda">Buscar carreras</label>
                    <input type="text" id="busqueda" name="busqueda" class="form-control" 
                           placeholder="Buscar por nombre, ubicación..." value="<?php echo htmlspecialchars($busqueda); ?>">
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filtro">Filtrar por</label>
                    <select id="filtro" name="filtro" class="form-control">
                        <option value="todos" <?php echo $filtro === 'todos' ? 'selected' : ''; ?>>Todos los eventos</option>
                        <option value="proximos" <?php echo $filtro === 'proximos' ? 'selected' : ''; ?>>Próximos eventos</option>
                        <option value="pasados" <?php echo $filtro === 'pasados' ? 'selected' : ''; ?>>Eventos pasados</option>
                    </select>
                </div>
                
                <div>
                    <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>
            </form>
        </div>

        <?php if (!empty($eventos)): ?>
            <div class="events-grid">
                <?php foreach ($eventos as $evento): 
                    $fecha_evento = strtotime($evento['fecha_evento']);
                    $es_pasado = $fecha_evento < time();
                ?>
                <div class="event-card <?php echo $es_pasado ? 'evento-pasado' : ''; ?>">
                    <div class="event-header">
                        <div class="event-image">
                            <span class="event-initials"><?php echo substr($evento['nombre'], 0, 2); ?></span>
                        </div>
                        <div class="event-basic-info">
                            <h3 class="event-title"><?php echo htmlspecialchars($evento['nombre']); ?></h3>
                            <div class="event-date">
                                <i class="fas fa-calendar-alt"></i>
                                <span style="font-size: 0.9em;"><?php echo date('d/m/Y h:i A', $fecha_evento); ?></span>
                                <?php if ($es_pasado): ?>
                                    <span style="background: #6c757d; color: white; padding: 0.2rem 0.5rem; border-radius: 3px; font-size: 0.8rem; margin-left: 0.5rem;">
                                        Completado
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="event-details">
                        <div class="detail-row">
                            <div class="detail-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($evento['ubicacion']); ?></span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-route"></i>
                                <span><?php echo $evento['distancia']; ?> km</span>
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-item">
                                <i class="fas fa-users"></i>
                                <span>Cupos: <?php echo $evento['cupo_disponible']; ?>/<?php echo $evento['cupo_maximo']; ?></span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-tag"></i>
                                <span>$<?php echo number_format($evento['precio'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="event-actions">
                        <a href="view.php?id=<?php echo $evento['id']; ?>" class="btn btn-outline">
                            <i class="fas fa-eye"></i> Detalles
                        </a>
                        <!-- Botón de Inscribirse siempre visible para invitados -->
                        <a href="../auth/login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Unete
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-events">
                <i class="fas fa-calendar-times"></i>
                <h3>No se encontraron eventos</h3>
                <p>No hay eventos que coincidan con tu búsqueda.</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

<!-- Modal QR -->
<div id="qrModal" class="qr-modal">
    <div class="qr-modal-content" id="printableArea">
        <div class="qr-modal-header">
            <h2 id="modalEventTitle">Ticket de Evento</h2>
            <button class="close-modal" onclick="closeQRModal()">&times;</button>
        </div>
        <div class="qr-modal-body">
            <div class="ticket-info">
                <p><i class="fas fa-running"></i> <span><span class="ticket-label">Corredor:</span> <span id="modalRunner"></span></span></p>
                <p><i class="fas fa-hashtag"></i> <span><span class="ticket-label">Dorsal:</span> <span id="modalDorsal"></span></span></p>
                <p><i class="fas fa-calendar"></i> <span><span class="ticket-label">Fecha:</span> <span id="modalDate"></span></span></p>
                <p><i class="fas fa-map-marker-alt"></i> <span><span class="ticket-label">Lugar:</span> <span id="modalLocation"></span></span></p>
            </div>
            
            <div id="qrcode"></div>
            <p class="text-muted small">Presenta este código al ingresar al evento.</p>
        </div>
        <div class="qr-modal-footer" data-html2canvas-ignore="true">
            <button class="btn btn-secondary" onclick="closeQRModal()">Cerrar</button>
            <button class="btn btn-primary" onclick="downloadPDF()">
                <i class="fas fa-file-pdf"></i> Descargar PDF
            </button>
        </div>
    </div>
</div>

<!-- Librerías para QR y PDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
    // Variable global para datos del ticket actual
    let currentTicketData = null;

    function showQR(data) {
        currentTicketData = data;
        
        // Rellenar datos
        document.getElementById('modalEventTitle').textContent = data.evento;
        document.getElementById('modalRunner').textContent = data.corredor;
        document.getElementById('modalDorsal').textContent = data.dorsal;
        document.getElementById('modalDate').textContent = data.fecha;
        document.getElementById('modalLocation').textContent = data.ubicacion;
        
        // Limpiar QR anterior
        const qrContainer = document.getElementById('qrcode');
        qrContainer.innerHTML = '';
        
        // Generar nuevo QR
        // El contenido del QR podría ser un JSON verificable o simplemente el ID
        const qrContent = JSON.stringify({
            id: data.ticket_id,
            c: data.corredor,
            d: data.dorsal
        });
        
        new QRCode(qrContainer, {
            text: qrContent,
            width: 180,
            height: 180,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.L
        });
        
        // Mostrar modal
        document.getElementById('qrModal').style.display = 'block';
    }
    
    function closeQRModal() {
        document.getElementById('qrModal').style.display = 'none';
    }
    
    function downloadPDF() {
        const btn = document.querySelector('.qr-modal-footer .btn-primary');
        const originalText = btn ? btn.innerHTML : '';
        if (btn) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando...';
            btn.disabled = true;
        }

        try {
            // Verificar si jsPDF está expuesto de globalmente por html2pdf.js
            let jsPdfConstructor = window.jspdf ? window.jspdf.jsPDF : window.jsPDF;
            
            if (!jsPdfConstructor) {
                throw new Error("jsPDF no expuesto globalmente. Usando ventana de impresion de respaldo.");
            }

            const doc = new jsPdfConstructor({
                unit: 'mm',
                format: 'a5',
                orientation: 'portrait'
            });

            // Fondo blanco indispensable
            doc.setFillColor(255, 255, 255);
            doc.rect(0, 0, 148, 210, "F");

            // Cabecera del ticket
            doc.setTextColor(51, 51, 51);
            doc.setFontSize(22);
            doc.text("Ticket de Evento", 15, 25);
            
            // Subtitulo (Evento)
            doc.setTextColor(85, 85, 85);
            doc.setFontSize(14);
            const evtName = currentTicketData.evento.length > 45 ? currentTicketData.evento.substring(0, 42) + "..." : currentTicketData.evento;
            doc.text(evtName, 15, 33);
            
            // Linea divisora
            doc.setDrawColor(0, 123, 255);
            doc.setLineWidth(1);
            doc.line(15, 40, 133, 40);

            // Container Gris
            doc.setFillColor(248, 249, 250);
            doc.setDrawColor(238, 238, 238);
            doc.roundedRect(15, 48, 118, 55, 2, 2, 'FD');

            doc.setTextColor(0, 0, 0);
            doc.setFontSize(12);
            
            // Inyectar pares clave-valor
            doc.setFont("helvetica", "bold");
            doc.text("Corredor:", 20, 58);
            doc.setFont("helvetica", "normal");
            doc.text(currentTicketData.corredor || '', 45, 58);
            
            doc.setFont("helvetica", "bold");
            doc.text("Dorsal:", 20, 68);
            doc.setFont("helvetica", "normal");
            const dorsalStr = currentTicketData.dorsal != null ? currentTicketData.dorsal.toString() : '';
            doc.text(dorsalStr, 45, 68);
            
            doc.setFont("helvetica", "bold");
            doc.text("Fecha:", 20, 78);
            doc.setFont("helvetica", "normal");
            doc.text(currentTicketData.fecha || '', 45, 78);
            
            doc.setFont("helvetica", "bold");
            doc.text("Lugar:", 20, 88);
            doc.setFont("helvetica", "normal");
            let locText = currentTicketData.ubicacion || '';
            if (locText.length > 35) locText = locText.substring(0, 32) + "...";
            doc.text(locText, 45, 88);

            // Rescatar QR
            const qrcodeElement = document.getElementById('qrcode');
            let qrImageSrc = '';
            const qrCanvas = qrcodeElement.querySelector('canvas');
            if (qrCanvas) {
                qrImageSrc = qrCanvas.toDataURL("image/jpeg", 1.0);
            } else {
                const qrImg = qrcodeElement.querySelector('img');
                if (qrImg) qrImageSrc = qrImg.src;
            }

            if (qrImageSrc && qrImageSrc.indexOf('data:image') === 0) {
                const imgFormat = qrImageSrc.indexOf('image/png') > -1 ? 'PNG' : 'JPEG';
                // Centramos QR en X: (148 - 60) / 2 = 44
                doc.addImage(qrImageSrc, imgFormat, 44, 112, 60, 60);
            }

            // Pie de página
            doc.setTextColor(102, 102, 102);
            doc.setFontSize(10);
            doc.text("Presenta este código al ingresar al evento.", 74, 185, { align: "center" });

            doc.save(`Ticket-${currentTicketData.evento.replace(/\s+/g, '-')}.pdf`);

            if (btn) {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }

        } catch (e) {
            console.error("No se pudo construir vector PDF manual: ", e);
            
            const qrcodeElement = document.getElementById('qrcode');
            let qrImageSrc = '';
            const qrCanvas = qrcodeElement.querySelector('canvas');
            if (qrCanvas) qrImageSrc = qrCanvas.toDataURL("image/png");

            // Recurso defensivo: Forzar Print Nativo de SO
            const win = window.open('', '_blank');
            const htmlContent = `
                <html>
                    <head>
                        <title>Ticket de Evento - ${currentTicketData.evento}</title>
                        <style>body { font-family: Arial, sans-serif; padding: 20px; }</style>
                    </head>
                    <body>
                        <div style="max-width: 400px; margin: 0 auto; border: 1px solid #ccc; padding: 20px; border-radius: 8px;">
                            <h2 style="color: #333; margin-top: 0;">Ticket de Evento</h2>
                            <h3 style="color: #666;">${currentTicketData.evento}</h3>
                            <hr style="border:1px solid #007bff; margin-bottom: 20px;" />
                            <p><strong>Corredor:</strong> ${currentTicketData.corredor}</p>
                            <p><strong>Dorsal:</strong> ${currentTicketData.dorsal}</p>
                            <p><strong>Fecha:</strong> ${currentTicketData.fecha}</p>
                            <p><strong>Lugar:</strong> ${currentTicketData.ubicacion}</p>
                            <div style="text-align: center; margin-top: 30px;">
                                ${qrImageSrc ? `<img src="${qrImageSrc}" style="width: 180px; height: 180px;" />` : ''}
                                <p style="color: #666; font-size: 12px; margin-top: 15px;">Guarda este ticket para presentarlo el día del evento.</p>
                            </div>
                        </div>
                        <script>setTimeout(() => { window.print(); window.close(); }, 500);<\/script>
                    </body>
                </html>
            `;
            win.document.write(htmlContent);
            win.document.close();
            
            if (btn) {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }
    }
    
    // Cerrar modal al hacer clic fuera
    window.onclick = function(event) {
        const modal = document.getElementById('qrModal');
        if (event.target == modal) {
            closeQRModal();
        }
    }
</script>

<?php include '../../includes/footer.php'; ?>