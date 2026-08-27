<?php
// modules/admin/view-event.php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Verificar sesión de administrador
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

$db = getDB();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: events.php');
    exit();
}

$event_id = intval($_GET['id']);

// Obtener información del evento
$query = "SELECT e.*, u.nombre as organizador_nombre, u.apellido as organizador_apellido,
          (SELECT COUNT(*) FROM inscripciones i WHERE i.id_evento = e.id AND i.estado = 'confirmada') as total_confirmados,
          (SELECT COUNT(*) FROM inscripciones i WHERE i.id_evento = e.id AND i.estado = 'pendiente') as total_pendientes,
          (SELECT IFNULL(SUM(p.monto), 0) FROM pagos p JOIN inscripciones i ON p.id_inscripcion = i.id WHERE i.id_evento = e.id AND p.estado = 'aprobado') as total_ingresos
          FROM eventos e 
          LEFT JOIN usuarios u ON e.id_organizador = u.id 
          WHERE e.id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: events.php');
    exit();
}

$evento = $result->fetch_assoc();
$stmt->close();

// Procesar modalidades
$modalidades = !empty($evento['modalidades']) ? json_decode($evento['modalidades'], true) : [];
$tiene_modalidades = !empty($modalidades);

include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <div class="admin-header">
        <div>
            <h1 style="margin-bottom: 0.5rem;"><i class="fas fa-eye"></i> Detalles del Evento</h1>
            <p style="margin: 0; color: var(--text-light);">Visualizando información completa del evento</p>
        </div>
        <div class="admin-header-actions">
             <a href="edit-event.php?id=<?php echo $evento['id']; ?>" class="admin-btn admin-btn-secondary">
                <i class="fas fa-edit"></i> Editar
            </a>
            <a href="events.php" class="admin-btn admin-btn-outline">
                <i class="fas fa-arrow-left"></i> Volver a Lista
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Columna Izquierda: Información Principal -->
        <div class="col-md-8">
            <div class="admin-card">
                <div style="position: relative; margin-bottom: 1.5rem;">
                    <?php if ($evento['imagen_url']): ?>
                    <img src="../../<?php echo htmlspecialchars($evento['imagen_url']); ?>" 
                         alt="<?php echo htmlspecialchars($evento['nombre']); ?>"
                         style="width: auto; height: 400px; object-fit: cover; border-radius: 8px;">
                    <?php else: ?>
                    <div style="width: 100%; height: 200px; background: linear-gradient(135deg, var(--admin-primary), var(--admin-primary-light)); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white;">
                        <i class="fas fa-image" style="font-size: 4rem; opacity: 0.5;"></i>
                    </div>
                    <?php endif; ?>
                    
                    <div style="position: absolute; bottom: -20px; right: 20px;">
                        <?php 
                        $badge_class = '';
                        switch($evento['estado']) {
                            case 'activo': $badge_class = 'badge-success'; break;
                            case 'cancelado': $badge_class = 'badge-danger'; break;
                            case 'completado': $badge_class = 'badge-info'; break;
                            case 'pendiente': $badge_class = 'badge-warning'; break;
                            default: $badge_class = 'badge-secondary';
                        }
                        ?>
                        <span class="badge <?php echo $badge_class; ?>" style="font-size: 1rem; padding: 0.5rem 1rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                            <?php echo ucfirst($evento['estado']); ?>
                        </span>
                    </div>
                </div>

                <h2 style="margin-top: 1rem; color: var(--text-dark);"><?php echo htmlspecialchars($evento['nombre']); ?></h2>
                
                <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                    <span class="badge badge-info"><i class="fas fa-tag"></i> <?php echo ucfirst($evento['tipo_evento']); ?></span>
                    <?php if ($evento['distancia'] > 0): ?>
                    <span class="badge badge-secondary"><i class="fas fa-route"></i> <?php echo $evento['distancia']; ?> km</span>
                    <?php endif; ?>
                    <span class="badge badge-secondary"><i class="fas fa-user-tie"></i> Org: <?php echo htmlspecialchars($evento['organizador_nombre'] . ' ' . $evento['organizador_apellido']); ?></span>
                </div>

                <div style="margin-bottom: 2rem;">
                    <h4 style="border-bottom: 1px solid #eee; padding-bottom: 0.5rem; margin-bottom: 1rem;">Descripción</h4>
                    <div style="color: var(--text-light); line-height: 1.6;">
                        <?php echo nl2br(htmlspecialchars($evento['descripcion'])); ?>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div style="background: var(--bg-light); padding: 1rem; border-radius: 8px;">
                            <h5 style="margin-bottom: 1rem; color: var(--admin-primary);"><i class="fas fa-map-marker-alt"></i> Ubicación</h5>
                            <p style="margin-bottom: 0; font-weight: 500;"><?php echo htmlspecialchars($evento['ubicacion']); ?></p>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div style="background: var(--bg-light); padding: 1rem; border-radius: 8px;">
                            <h5 style="margin-bottom: 1rem; color: var(--admin-primary);"><i class="fas fa-clock"></i> Fecha y Hora</h5>
                            <p style="margin-bottom: 0; font-weight: 500;">
                                <?php echo date('d/m/Y', strtotime($evento['fecha_evento'])); ?> a las 
                                <?php echo date('h:i A', strtotime($evento['fecha_evento'])); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modalidades y Precios -->
            <div class="admin-card">
                 <h3 style="margin-bottom: 1.5rem;"><i class="fas fa-list-ul"></i> Modalidades y Precios</h3>
                 <?php if ($tiene_modalidades): ?>
                 <div class="table-responsive">
                     <table class="table table-bordered">
                         <thead>
                             <tr>
                                 <th>Modalidad</th>
                                 <th>Precio</th>
                             </tr>
                         </thead>
                         <tbody>
                             <?php foreach ($modalidades as $mod): 
                                 $nombre = is_array($mod) ? $mod['nombre'] : $mod;
                                 $precio = is_array($mod) ? $mod['precio'] : $evento['precio'];
                             ?>
                             <tr>
                                 <td><?php echo htmlspecialchars($nombre); ?></td>
                                 <td>$<?php echo number_format($precio, 2); ?></td>
                             </tr>
                             <?php endforeach; ?>
                         </tbody>
                     </table>
                 </div>
                 <?php else: ?>
                 <div style="display: flex; align-items: center; justify-content: space-between; background: var(--bg-light); padding: 1rem; border-radius: 8px;">
                     <span>Precio Único General</span>
                     <span style="font-weight: bold; font-size: 1.2rem;">$<?php echo number_format($evento['precio'], 2); ?></span>
                 </div>
                 <?php endif; ?>
            </div>
        </div>

        <!-- Columna Derecha: Estadísticas y Acciones -->
        <div class="col-md-4">
            <div class="admin-card">
                <h3 style="margin-bottom: 1.5rem;"><i class="fas fa-chart-pie"></i> Estadísticas</h3>
                
                <div style="margin-bottom: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span>Inscritos Confirmados</span>
                        <strong><?php echo $evento['total_confirmados']; ?></strong>
                    </div>
                    <div class="progress" style="height: 10px; background: #eee; border-radius: 5px; overflow: hidden;">
                        <?php 
                        $porcentaje = ($evento['cupo_maximo'] > 0) ? ($evento['total_confirmados'] / $evento['cupo_maximo']) * 100 : 0;
                        ?>
                        <div style="width: <?php echo $porcentaje; ?>%; background: var(--success); height: 100%;"></div>
                    </div>
                    <small class="text-muted"><?php echo $evento['total_confirmados']; ?> de <?php echo $evento['cupo_maximo']; ?> cupos utilizados</small>
                </div>

                <div style="margin-bottom: 1.5rem;">
                     <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span>Inscripciones Pendientes</span>
                        <span class="badge badge-warning"><?php echo $evento['total_pendientes']; ?></span>
                    </div>
                </div>

                <div style="border-top: 1px dashed #ddd; padding-top: 1rem; margin-top: 1rem;">
                    <h5 style="color: var(--text-light); font-size: 0.9rem;">Ingresos Totales (Estimado)</h5>
                    <h2 style="color: var(--success); margin: 0;">$<?php echo number_format($evento['total_ingresos'], 2); ?></h2>
                </div>
            </div>

            <div class="admin-card">
                <h3 style="margin-bottom: 1.5rem;"><i class="fas fa-cogs"></i> Acciones Rápidas</h3>
                
                <a href="event-inscriptions.php?id=<?php echo $evento['id']; ?>" class="admin-btn admin-btn-primary" style="display: block; text-align: center; margin-bottom: 1rem;">
                    <i class="fas fa-users"></i> Ver Inscritos
                </a>
                
                <a href="edit-event.php?id=<?php echo $evento['id']; ?>" class="admin-btn admin-btn-secondary" style="display: block; text-align: center; margin-bottom: 1rem;">
                    <i class="fas fa-edit"></i> Editar Evento
                </a>

                <?php if ($evento['estado'] === 'activo'): ?>
                <a href="cancel-event.php?id=<?php echo $evento['id']; ?>" class="admin-btn admin-btn-danger" style="display: block; text-align: center;" onclick="return confirm('¿Seguro que deseas cancelar este evento?');">
                    <i class="fas fa-times-circle"></i> Cancelar Evento
                </a>
                <?php endif; ?>
            </div>
            
            <div class="admin-card">
                 <h3 style="margin-bottom: 1rem;"><i class="fas fa-info-circle"></i> Metadata</h3>
                 <ul style="list-style: none; padding: 0; font-size: 0.9rem; color: var(--text-light);">
                     <li style="margin-bottom: 0.5rem;"><strong>Creado:</strong> <?php echo $evento['creado_en']; ?></li>
                     <li style="margin-bottom: 0.5rem;"><strong>Actualizado:</strong> <?php echo $evento['actualizado_en']; ?></li>
                     <li style="margin-bottom: 0.5rem;"><strong>Incluye Camiseta:</strong> <?php echo $evento['incluye_camiseta'] ? 'Sí' : 'No'; ?></li>
                     <li><strong>Métodos Pago:</strong> <?php echo $evento['metodo_pago']; ?></li>
                 </ul>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/admin-footer.php'; ?>
