<?php
// modules/admin/view-user.php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Verificar sesión de administrador
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header('Location: users.php');
    exit();
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header('Location: users.php');
    exit();
}

include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <div class="admin-header">
        <h1><i class="fas fa-user"></i> Perfil de Usuario</h1>
        <div class="admin-header-actions">
            <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="admin-btn admin-btn-primary">
                <i class="fas fa-edit"></i> Editar
            </a>
            <a href="users.php" class="admin-btn admin-btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <!-- Detalles del Usuario -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3>Información Personal</h3>
            <?php 
            $estado_colors = [
                'activo' => 'success',
                'inactivo' => 'secondary',
                'suspendido' => 'danger'
            ];
            $color = $estado_colors[$user['estado']] ?? 'secondary';
            ?>
            <span class="badge badge-<?php echo $color; ?>">
                <?php echo ucfirst($user['estado']); ?>
            </span>
        </div>
        
        <div style="display: flex; gap: 2rem; padding: 1rem; flex-wrap: wrap;">
            <!-- Avatar y Rol -->
            <div style="flex: 0 0 150px; text-align: center;">
                 <?php if ($user['foto_perfil'] && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $user['foto_perfil'])): ?>
                    <img src="<?php echo '/' . $user['foto_perfil']; ?>" 
                         alt="<?php echo htmlspecialchars($user['nombre']); ?>"
                         style="width: 150px; height: 150px; object-fit: cover; border-radius: 50%; border: 3px solid var(--admin-primary); box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <?php else: ?>
                    <div style="width: 150px; height: 150px; background: linear-gradient(135deg, var(--admin-primary), var(--admin-primary-light)); 
                                border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem; margin: 0 auto; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        <?php echo strtoupper(substr($user['nombre'], 0, 1) . substr($user['apellido'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
                
                <div style="margin-top: 1rem;">
                    <?php 
                    $tipo_colors = [
                        'admin' => 'danger',
                        'organizador' => 'warning',
                        'corredor' => 'success'
                    ];
                    $tcolor = $tipo_colors[$user['tipo_usuario']] ?? 'secondary';
                    ?>
                    <span class="badge badge-<?php echo $tcolor; ?>" style="font-size: 1rem; padding: 0.5rem 1rem;">
                        <?php echo ucfirst($user['tipo_usuario']); ?>
                    </span>
                </div>
            </div>
            
            <!-- Datos -->
            <div style="flex: 1; display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1.5rem; align-content: start;">
                <div>
                    <strong style="color: var(--admin-text-light); display: block; margin-bottom: 0.25rem; font-size: 0.9rem;">ID DE USUARIO</strong>
                    <span style="font-size: 1.1rem; font-family: monospace; background: #f0f0f0; padding: 2px 6px; border-radius: 4px;">#<?php echo $user['id']; ?></span>
                </div>
                
                <div>
                    <strong style="color: var(--admin-text-light); display: block; margin-bottom: 0.25rem; font-size: 0.9rem;">NOMBRE COMPLETO</strong>
                    <span style="font-size: 1.1rem; font-weight: 500;"><?php echo htmlspecialchars($user['nombre'] . ' ' . $user['apellido']); ?></span>
                </div>
                
                <div>
                    <strong style="color: var(--admin-text-light); display: block; margin-bottom: 0.25rem; font-size: 0.9rem;">EMAIL</strong>
                    <span style="font-size: 1.1rem;">
                        <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>" style="color: var(--admin-primary); text-decoration: none;">
                            <?php echo htmlspecialchars($user['email']); ?>
                        </a>
                    </span>
                </div>
                
                <div>
                    <strong style="color: var(--admin-text-light); display: block; margin-bottom: 0.25rem; font-size: 0.9rem;">TELÉFONO</strong>
                    <span style="font-size: 1.1rem;">
                        <?php if($user['telefono']): ?>
                            <a href="tel:<?php echo htmlspecialchars($user['telefono']); ?>" style="color: var(--admin-text); text-decoration: none;">
                                <?php echo htmlspecialchars($user['telefono']); ?>
                            </a>
                        <?php else: ?>
                            <em class="text-muted">No registrado</em>
                        <?php endif; ?>
                    </span>
                </div>
                
                <div>
                    <strong style="color: var(--admin-text-light); display: block; margin-bottom: 0.25rem; font-size: 0.9rem;">FECHA DE NACIMIENTO</strong>
                    <span style="font-size: 1.1rem;">
                        <?php 
                        if ($user['fecha_nacimiento']) {
                            $date = new DateTime($user['fecha_nacimiento']);
                            echo $date->format('d/m/Y');
                            
                            // Calcular edad
                            $now = new DateTime();
                            $age = $now->diff($date);
                            echo " <small class='text-muted'>({$age->y} años)</small>";
                        } else {
                            echo '<em class="text-muted">No registrada</em>';
                        }
                        ?>
                    </span>
                </div>
                
                <div>
                    <strong style="color: var(--admin-text-light); display: block; margin-bottom: 0.25rem; font-size: 0.9rem;">GÉNERO</strong>
                    <span style="font-size: 1.1rem;">
                        <?php 
                        $generos = ['M' => 'Masculino', 'F' => 'Femenino', 'O' => 'Otro'];
                        echo isset($generos[$user['genero']]) ? $generos[$user['genero']] : '<em class="text-muted">No especificado</em>';
                        ?>
                    </span>
                </div>
                
                <div>
                    <strong style="color: var(--admin-text-light); display: block; margin-bottom: 0.25rem; font-size: 0.9rem;">FECHA DE REGISTRO</strong>
                    <span style="font-size: 1.1rem;"><?php echo date('d/m/Y H:i', strtotime($user['fecha_registro'])); ?></span>
                </div>
                
                <div style="grid-column: 1 / -1;">
                    <strong style="color: var(--admin-text-light); display: block; margin-bottom: 0.25rem; font-size: 0.9rem;">DIRECCIÓN</strong>
                    <div style="font-size: 1.1rem; background: #f8f9fa; padding: 10px; border-radius: 4px; border-left: 3px solid var(--admin-primary);">
                        <?php echo $user['direccion'] ? nl2br(htmlspecialchars($user['direccion'])) : '<em class="text-muted">No registrada</em>'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Estadísticas o Información Adicional (Placeholder) -->
    <div class="row" style="margin-top: 1.5rem;">
        <div class="col-md-6" style="padding-right: 0.75rem;">
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3><i class="fas fa-running"></i> Eventos Inscritos</h3>
                </div>
                <div style="padding: 2rem; text-align: center; color: var(--admin-text-light);">
                    <i class="fas fa-clipboard-list" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <p>Próximamente: Historial de eventos del usuario.</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-6" style="padding-left: 0.75rem;">
             <div class="admin-card">
                <div class="admin-card-header">
                    <h3><i class="fas fa-history"></i> Actividad Reciente</h3>
                </div>
                <div style="padding: 2rem; text-align: center; color: var(--admin-text-light);">
                    <i class="fas fa-clock" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <p>No hay actividad reciente registrada.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/admin-footer.php'; ?>
