<?php
// modules/admin/users.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/SMTPMailer.php';

// session_start(); removed as it's handled in config.php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

if ($_SESSION['user_type'] === 'organizador') {
    header('Location: dashboard.php');
    exit();
}

$db = getDB();

// Variables para filtros y paginación
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

// Construir consulta base
$query = "SELECT SQL_CALC_FOUND_ROWS * FROM usuarios WHERE 1=1";
$params = [];
$types = '';

// Por defecto no mostrar inactivos a menos que se filtre específicamente por ellos o se seleccione "todos" explícitamente (si implementáramos esa opción)
// En este caso, si NO hay filtro de estado, excluimos los inactivos para que el "eliminar" (desactivar) parezca que borra de la lista
if (!$filtro_estado) {
    $query .= " AND estado != 'inactivo'";
}

if ($filtro_estado) {
    $query .= " AND estado = ?";
    $params[] = $filtro_estado;
    $types .= 's';
}

if ($filtro_tipo) {
    $query .= " AND tipo_usuario = ?";
    $params[] = $filtro_tipo;
    $types .= 's';
}

if ($busqueda) {
    $query .= " AND (nombre LIKE ? OR apellido LIKE ? OR email LIKE ?)";
    $busqueda_like = "%$busqueda%";
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $types .= 'sss';
}

$query .= " ORDER BY fecha_registro DESC LIMIT ? OFFSET ?";
$params[] = $por_pagina;
$params[] = $offset;
$types .= 'ii';

// Preparar consulta
$stmt = $db->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$usuarios = $result->fetch_all(MYSQLI_ASSOC);

// Obtener total de resultados
$total_resultados = $db->query("SELECT FOUND_ROWS()")->fetch_row()[0];
$total_paginas = ceil($total_resultados / $por_pagina);

$stmt->close();

// Procesar acciones
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = $_POST['user_id'] ?? 0;
    
    if ($action === 'cambiar_estado' && $user_id) {
        $nuevo_estado = $_POST['nuevo_estado'];
        
        $stmt = $db->prepare("UPDATE usuarios SET estado = ? WHERE id = ?");
        $stmt->bind_param("si", $nuevo_estado, $user_id);
        
        if ($stmt->execute()) {
            $success = 'Estado del usuario actualizado correctamente';
        } else {
            $error = 'Error al actualizar el estado del usuario';
        }
        $stmt->close();
    }
    
    elseif ($action === 'eliminar_usuario' && $user_id) {
        $stmt = $db->prepare("UPDATE usuarios SET estado = 'inactivo' WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $success = 'Usuario desactivado correctamente';
        } else {
            $error = 'Error al desactivar el usuario';
        }
        $stmt->close();
    }
    
    elseif ($action === 'cambiar_rol' && $user_id) {
        $nuevo_rol = $_POST['nuevo_rol'];
        
        $stmt = $db->prepare("UPDATE usuarios SET tipo_usuario = ? WHERE id = ?");
        $stmt->bind_param("si", $nuevo_rol, $user_id);
        
        if ($stmt->execute()) {
            $success = 'Rol del usuario actualizado correctamente';
        } else {
            $error = 'Error al actualizar el rol del usuario';
        }
        $stmt->close();
    } elseif ($action === 'create_user') {
        $nombre = trim($_POST['nombre']);
        $apellido = trim($_POST['apellido']);
        $cedula = trim($_POST['cedula']);
        $telefono = !empty($_POST['telefono']) ? trim($_POST['telefono']) : NULL;
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $tipo_usuario = $_POST['tipo_usuario'];
        $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : NULL;
        $genero = !empty($_POST['genero']) ? $_POST['genero'] : NULL;
        
        // Validar email único
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "El email ya está registrado";
        } else {
            // Verificar si la constante PEPPER está definida
            $pepper = defined('PEPPER') ? PEPPER : '';
            $hash = password_hash($password . $pepper, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("INSERT INTO usuarios (nombre, apellido, cedula, telefono, email, password, tipo_usuario, fecha_nacimiento, genero, fecha_registro, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'activo')");
            $stmt->bind_param("sssssssss", $nombre, $apellido, $cedula, $telefono, $email, $hash, $tipo_usuario, $fecha_nacimiento, $genero);
            
            if ($stmt->execute()) {
                $success = "Usuario creado exitosamente";
            } else {
                $error = "Error al crear usuario: " . $db->error;
            }
        }
        $stmt->close();
    } elseif ($action === 'crear_inscripcion') {
        $id_usuario = intval($_POST['id_usuario'] ?? 0);
        $id_evento = intval($_POST['id_evento'] ?? 0);
        $categoria = !empty($_POST['categoria']) ? $_POST['categoria'] : '';
        $modalidad = !empty($_POST['modalidad']) ? $_POST['modalidad'] : NULL;
        $talla_camiseta = !empty($_POST['talla_camiseta']) ? $_POST['talla_camiseta'] : NULL;
        $contacto_emergencia = $_POST['contacto_emergencia'] ?? '';
        $telefono_emergencia = $_POST['telefono_emergencia'] ?? '';
        $notas_medicas = !empty($_POST['notas_medicas']) ? $_POST['notas_medicas'] : NULL;
        $metodo_pago = !empty($_POST['metodo_pago']) ? $_POST['metodo_pago'] : 'gratuito';
        
        if (!$id_usuario || !$id_evento || !$categoria) {
            $error = 'Faltan datos requeridos para la inscripción';
        } else {
            // Validar que el usuario no esté inscrito
            $stmt = $db->prepare("SELECT id FROM inscripciones WHERE id_usuario = ? AND id_evento = ? AND estado IN ('confirmada', 'pendiente')");
            $stmt->bind_param("ii", $id_usuario, $id_evento);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = 'El usuario ya está inscrito o tiene una inscripción pendiente en este evento.';
            }
            $stmt->close();
            
            if (!$error) {
                // Obtener datos del evento
                $stmt = $db->prepare("SELECT * FROM eventos WHERE id = ?");
                $stmt->bind_param("i", $id_evento);
                $stmt->execute();
                $evento_row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                if (!$evento_row || $evento_row['estado'] !== 'activo') {
                    $error = 'El evento no existe o no está activo.';
                } elseif ($evento_row['cupo_disponible'] <= 0 && $_SESSION['user_type'] !== 'admin') {
                    $error = 'No hay cupos disponibles para este evento.';
                } else {
                    $incrementar_cupo = ($evento_row['cupo_disponible'] <= 0);
                    // Calcular precio
                    $precio_final = floatval($evento_row['precio']);
                    if ($modalidad && !empty($evento_row['modalidades'])) {
                        $mods_data = json_decode($evento_row['modalidades'], true);
                        foreach ($mods_data as $m) {
                            if (is_array($m) && isset($m['nombre']) && $m['nombre'] === $modalidad) {
                                $precio_final = floatval($m['precio']);
                                break;
                            }
                        }
                    }
                    
                    $db->begin_transaction();
                    try {
                        // Obtener secuencia
                        $prefix = 'RUN' . $id_evento;
                        $stmt_seq = $db->prepare("SELECT numero_corredor FROM inscripciones WHERE id_evento = ? AND numero_corredor LIKE ? ORDER BY LENGTH(numero_corredor) DESC, numero_corredor DESC LIMIT 1 FOR UPDATE");
                        $like_pattern = $prefix . '%';
                        $stmt_seq->bind_param("is", $id_evento, $like_pattern);
                        $stmt_seq->execute();
                        $result_seq = $stmt_seq->get_result();
                        
                        $seq_count = 0;
                        if ($row_seq = $result_seq->fetch_assoc()) {
                            $last_num = $row_seq['numero_corredor'];
                            $seq_count = intval(substr($last_num, strlen($prefix)));
                        }
                        $stmt_seq->close();
                        
                        $numero_corredor = $prefix . str_pad($seq_count + 1, 4, '0', STR_PAD_LEFT);
                        
                        // Insertar inscripción
                        $stmt_ins = $db->prepare("INSERT INTO inscripciones (id_usuario, id_evento, numero_corredor, categoria, modalidad, talla_camiseta, estado, contacto_emergencia, telefono_emergencia, notas_medicas) VALUES (?, ?, ?, ?, ?, ?, 'confirmada', ?, ?, ?)");
                        $stmt_ins->bind_param("iisssssss", $id_usuario, $id_evento, $numero_corredor, $categoria, $modalidad, $talla_camiseta, $contacto_emergencia, $telefono_emergencia, $notas_medicas);
                        $stmt_ins->execute();
                        $id_inscripcion = $stmt_ins->insert_id;
                        $stmt_ins->close();
                        
                        // Actualizar cupo
                        if ($incrementar_cupo) {
                            $stmt_cupo = $db->prepare("UPDATE eventos SET cupo_maximo = cupo_maximo + 1 WHERE id = ?");
                        } else {
                            $stmt_cupo = $db->prepare("UPDATE eventos SET cupo_disponible = cupo_disponible - 1 WHERE id = ?");
                        }
                        $stmt_cupo->bind_param("i", $id_evento);
                        $stmt_cupo->execute();
                        $stmt_cupo->close();
                        
                        // Crear pago si aplica
                        if ($precio_final > 0 && $metodo_pago !== 'gratuito') {
                            $estado_pago = ($metodo_pago === 'efectivo') ? 'completado' : 'pendiente';
                            $stmt_pago = $db->prepare("INSERT INTO pagos (id_inscripcion, monto, metodo_pago, estado, fecha_vencimiento) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))");
                            $stmt_pago->bind_param("idss", $id_inscripcion, $precio_final, $metodo_pago, $estado_pago);
                            $stmt_pago->execute();
                            $stmt_pago->close();
                        }
                        
                        $db->commit();
                        $success = 'Usuario inscrito en el evento correctamente';
                        
                        // --- Enviar Correo Confirmación ---
                        try {
                            // Obtener correo del usuario
                            $stmt_user = $db->prepare("SELECT nombre, email FROM usuarios WHERE id = ?");
                            $stmt_user->bind_param("i", $id_usuario);
                            $stmt_user->execute();
                            $usr_data = $stmt_user->get_result()->fetch_assoc();
                            $stmt_user->close();
                            
                            if ($usr_data && !empty($usr_data['email'])) {
                                $mailer = new SMTPMailer();
                                $subject = "Confirmación de Inscripción - " . $evento_row['nombre'];
                                $message = "<h2>¡Inscripción Confirmada!</h2>";
                                $message .= "<p>Hola " . htmlspecialchars($usr_data['nombre']) . ",</p>";
                                $message .= "<p>Has sido registrado exitosamente al evento: <strong>" . htmlspecialchars($evento_row['nombre']) . "</strong> por un administrador.</p>";
                                
                                $message .= "<h3>Detalles de la Inscripción:</h3>";
                                $message .= "<ul>";
                                $message .= "<li># Corredor: $numero_corredor</li>";
                                if ($modalidad) $message .= "<li>Modalidad: " . htmlspecialchars($modalidad) . "</li>";
                                if ($talla_camiseta) $message .= "<li>Talla Camiseta: " . htmlspecialchars($talla_camiseta) . "</li>";
                                $message .= "</ul>";
                                
                                if ($precio_final > 0) {
                                     $message .= "<p><strong>Total a Pagar: $" . number_format($precio_final, 2) . "</strong> (" . ucfirst($metodo_pago) . ")</p>";
                                     $message .= "<p>Muy cordialmente te invitamos a realizar el pago de inscripción en nuestra cuenta via deposito o transferencia:</p>";
                                     $message .= "<ol>";
                                     $message .= "<li>Banco Popular: 849257019</li>";
                                     $message .= "<li>Nombre: Arlene B&aacute;ez</li>";
                                     $message .= "<li>Cedula: 031-0321517-8</li>";
                                     $message .= "</ol>";
                                     $message .= "<p>Incluir comentario con tu nombre para referencia de pago y enviar comprobante al Whatsapp 829-923-0124.</p>";
                                     $message .= "<p>Guarda el comprobante de inscripción para referencia de tu registro junto a tu comprobante de pago.</p>";
                                     $message .= "<p>Los kits del evento serán entregados a medida estén disponibles teniendo como fecha limite de entrega el 15 d&iacute;as antes del evento.</p>";
                                }
                                $message .= "<p>Unete a nuestro grupo de Whatsapp a través del siguiente link y mantente actualizado de las informaciones relacionadas con nuestro evento, haciendo click <a href='https://chat.whatsapp.com/GYrg8sAnWVE1OSgsnJsuZV'>aqu&iacute;</a></p>";
                                $message .= "<p>Saludos,<br>Equipo Fit5K<br>Siguenos en nuestras redes sociales:<br><a href='https://www.instagram.com/fit5kdr/'>Instagram: @fit5kdr</a><br><a href='https://www.facebook.com/fit5kdr/'>Facebook: Fit5KDR</a></p>";
                                $message .= "<p>Cualquier información adicional, no dudes en contactarnos.</p>";
                    
                                $mailer->send($usr_data['email'], $subject, $message);
                            }
                        } catch (Exception $mailEx) {
                            $error .= " (Nota: No se pudo enviar el correo de confirmación: " . $mailEx->getMessage() . ")";
                        }

                    } catch (Exception $e) {
                        $db->rollback();
                        $error = 'Error al registrar la inscripción: ' . $e->getMessage();
                    }
                }
            }
        }
    }
    
    if ($error) $_SESSION['error'] = $error;
    if ($success) $_SESSION['success'] = $success;

    // Recargar la página para mostrar cambios
    header("Location: users.php?page=$pagina" . 
           ($filtro_estado ? "&estado=$filtro_estado" : "") . 
           ($filtro_tipo ? "&tipo=$filtro_tipo" : "") . 
           ($busqueda ? "&busqueda=$busqueda" : ""));
    exit();
}

// Obtener lista de eventos para el modal
$eventos_query = "SELECT id, nombre FROM eventos WHERE fecha_evento > NOW() ORDER BY fecha_evento DESC";
$eventos = $db->query($eventos_query)->fetch_all(MYSQLI_ASSOC);

$eventos_admin_data = [];
foreach ($eventos as $e) {
    $detalles_query = "SELECT tipo_evento, incluye_camiseta, precio, modalidades, metodo_pago FROM eventos WHERE id = " . $e['id'];
    $detalles_row = $db->query($detalles_query)->fetch_assoc();
    if($detalles_row) {
        $eventos_admin_data[$e['id']] = $detalles_row;
        $eventos_admin_data[$e['id']]['nombre'] = $e['nombre'];
    }
}

// Obtener estadísticas
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN estado = 'activo' THEN 1 ELSE 0 END) as activos,
    SUM(CASE WHEN estado = 'inactivo' THEN 1 ELSE 0 END) as inactivos,
    SUM(CASE WHEN estado = 'suspendido' THEN 1 ELSE 0 END) as suspendidos,
    SUM(CASE WHEN tipo_usuario = 'admin' THEN 1 ELSE 0 END) as admins,
    SUM(CASE WHEN tipo_usuario = 'organizador' THEN 1 ELSE 0 END) as organizadores,
    SUM(CASE WHEN tipo_usuario = 'corredor' THEN 1 ELSE 0 END) as corredores
FROM usuarios
WHERE estado != 'inactivo'";

$stats = $db->query($stats_query)->fetch_assoc();

include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <!-- Header -->
    <div class="admin-header">
        <h1><i class="fas fa-users"></i> Gestión de Usuarios</h1>
        <div class="admin-header-actions">
            <!-- Export Buttons Removed -->
            <button onclick="document.getElementById('modal-usuario').style.display='block'" class="admin-btn admin-btn-primary">
                <i class="fas fa-plus"></i> Nuevo Usuario
            </button>
        </div>
    </div>

    <!-- Notificaciones -->
    <?php if ($error): ?>
    <div class="admin-alert admin-alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo $error; ?></span>
    </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
    <div class="admin-alert admin-alert-success">
        <i class="fas fa-check-circle"></i>
        <span><?php echo $success; ?></span>
    </div>
    <?php endif; ?>

    <!-- Estadísticas -->
    <div class="admin-stats">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
            <div class="stat-label">Usuarios Totales</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['activos']); ?></div>
            <div class="stat-label">Usuarios Activos</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-user-tie"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['admins']); ?></div>
            <div class="stat-label">Administradores</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-running"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['corredores']); ?></div>
            <div class="stat-label">Corredores</div>
        </div>
    </div>

    <!-- Filtros y Búsqueda -->
    <div class="admin-filters">
        <form method="GET" action="">
            <div class="filter-row">
                <div class="form-group">
                    <label for="busqueda"><i class="fas fa-search"></i> Buscar usuario</label>
                    <input type="text" id="busqueda" name="busqueda" class="form-control" 
                           placeholder="Nombre, apellido o email" 
                           value="<?php echo htmlspecialchars($busqueda); ?>">
                </div>
                
                <div class="form-group">
                    <label for="estado"><i class="fas fa-filter"></i> Estado</label>
                    <select id="estado" name="estado" class="form-control">
                        <option value="">Todos los estados</option>
                        <option value="activo" <?php echo $filtro_estado === 'activo' ? 'selected' : ''; ?>>Activo</option>
                        <option value="inactivo" <?php echo $filtro_estado === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                        <option value="suspendido" <?php echo $filtro_estado === 'suspendido' ? 'selected' : ''; ?>>Suspendido</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="tipo"><i class="fas fa-user-tag"></i> Tipo de usuario</label>
                    <select id="tipo" name="tipo" class="form-control">
                        <option value="">Todos los tipos</option>
                        <option value="admin" <?php echo $filtro_tipo === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                        <option value="organizador" <?php echo $filtro_tipo === 'organizador' ? 'selected' : ''; ?>>Organizador</option>
                        <option value="corredor" <?php echo $filtro_tipo === 'corredor' ? 'selected' : ''; ?>>Corredor</option>
                    </select>
                </div>
                
                <div>
                    <button type="submit" class="admin-btn admin-btn-primary">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    <a href="users.php" class="admin-btn admin-btn-secondary">
                        <i class="fas fa-redo"></i> Limpiar
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabla de Usuarios -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2><i class="fas fa-list"></i> Lista de Usuarios</h2>
            <span class="badge badge-primary">
                <?php echo number_format($total_resultados); ?> usuarios encontrados
            </span>
        </div>
        
        <?php if (!empty($usuarios)): ?>
        <div style="overflow-x: auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                    <tr>
                        <td><strong>#<?php echo $usuario['id']; ?></strong></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 40px; height: 40px; background: linear-gradient(135deg, var(--admin-primary), var(--admin-primary-light)); 
                                            border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                    <?php echo strtoupper(substr($usuario['nombre'], 0, 1) . substr($usuario['apellido'], 0, 1)); ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?></strong><br>
                                    <small style="color: var(--admin-text-light);">
                                        <?php echo $usuario['telefono'] ?: 'Sin teléfono'; ?>
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                        <td>
                            <?php 
                            $tipo_colors = [
                                'admin' => 'danger',
                                'organizador' => 'warning',
                                'corredor' => 'success'
                            ];
                            $color = $tipo_colors[$usuario['tipo_usuario']] ?? 'secondary';
                            ?>
                            <span class="badge badge-<?php echo $color; ?>">
                                <?php echo ucfirst($usuario['tipo_usuario']); ?>
                            </span>
                            
                            <!-- Formulario para cambiar rol -->
                            <form method="POST" action="" style="margin-top: 5px;">
                                <input type="hidden" name="action" value="cambiar_rol">
                                <input type="hidden" name="user_id" value="<?php echo $usuario['id']; ?>">
                                <select name="nuevo_rol" class="form-control" style="padding: 3px 8px; font-size: 12px;"
                                        onchange="if(confirm('¿Cambiar rol de este usuario?')) this.form.submit()" 
                                        <?php echo $usuario['id'] == $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                    <option value="corredor" <?php echo $usuario['tipo_usuario'] == 'corredor' ? 'selected' : ''; ?>>Corredor</option>
                                    <option value="organizador" <?php echo $usuario['tipo_usuario'] == 'organizador' ? 'selected' : ''; ?>>Organizador</option>
                                    <option value="admin" <?php echo $usuario['tipo_usuario'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </form>
                        </td>
                        <td>
                            <?php 
                            $estado_colors = [
                                'activo' => 'success',
                                'inactivo' => 'secondary',
                                'suspendido' => 'danger'
                            ];
                            $color = $estado_colors[$usuario['estado']] ?? 'secondary';
                            ?>
                            <span class="badge badge-<?php echo $color; ?>">
                                <?php echo ucfirst($usuario['estado']); ?>
                            </span>
                            
                            <!-- Formulario para cambiar estado -->
                            <form method="POST" action="" style="margin-top: 5px;">
                                <input type="hidden" name="action" value="cambiar_estado">
                                <input type="hidden" name="user_id" value="<?php echo $usuario['id']; ?>">
                                <select name="nuevo_estado" class="form-control" style="padding: 3px 8px; font-size: 12px;"
                                        onchange="if(confirm('¿Cambiar estado de este usuario?')) this.form.submit()" 
                                        <?php echo $usuario['id'] == $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                    <option value="activo" <?php echo $usuario['estado'] == 'activo' ? 'selected' : ''; ?>>Activo</option>
                                    <option value="inactivo" <?php echo $usuario['estado'] == 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                                    <option value="suspendido" <?php echo $usuario['estado'] == 'suspendido' ? 'selected' : ''; ?>>Suspendido</option>
                                </select>
                            </form>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($usuario['fecha_registro'])); ?></td>
                        <td>
                            <div class="actions">
                                <a href="view-user.php?id=<?php echo $usuario['id']; ?>" 
                                   class="admin-btn admin-btn-outline" title="Ver perfil" style="padding: 0.5rem;">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit-user.php?id=<?php echo $usuario['id']; ?>" 
                                   class="admin-btn admin-btn-secondary" title="Editar" style="padding: 0.5rem;">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="#" onclick="openInscriptionModal(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars(addslashes($usuario['nombre'] . ' ' . $usuario['apellido'])); ?>'); return false;" 
                                   class="admin-btn admin-btn-primary" title="Inscribir en Evento" style="padding: 0.5rem; margin-left: 2px;">
                                    <i class="fas fa-plus-circle"></i>
                                </a>
                                <form method="POST" action="" style="display: inline;" 
                                      onsubmit="return confirm('¿Estás seguro de desactivar este usuario?');">
                                    <input type="hidden" name="action" value="eliminar_usuario">
                                    <input type="hidden" name="user_id" value="<?php echo $usuario['id']; ?>">
                                    <button type="submit" class="admin-btn admin-btn-danger" title="Desactivar"
                                            style="padding: 0.5rem;" <?php echo $usuario['id'] == $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <?php if ($total_paginas > 1): ?>
        <div class="admin-pagination">
            <?php if ($pagina > 1): ?>
            <a href="?pagina=<?php echo $pagina - 1; ?>&estado=<?php echo $filtro_estado; ?>&tipo=<?php echo $filtro_tipo; ?>&busqueda=<?php echo urlencode($busqueda); ?>" 
               class="pagination-link">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>

            <?php 
            $inicio = max(1, $pagina - 2);
            $fin = min($total_paginas, $pagina + 2);
            
            for ($i = $inicio; $i <= $fin; $i++): 
            ?>
            <a href="?pagina=<?php echo $i; ?>&estado=<?php echo $filtro_estado; ?>&tipo=<?php echo $filtro_tipo; ?>&busqueda=<?php echo urlencode($busqueda); ?>" 
               class="pagination-link <?php echo $i == $pagina ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>

            <?php if ($pagina < $total_paginas): ?>
            <a href="?pagina=<?php echo $pagina + 1; ?>&estado=<?php echo $filtro_estado; ?>&tipo=<?php echo $filtro_tipo; ?>&busqueda=<?php echo urlencode($busqueda); ?>" 
               class="pagination-link">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div style="margin-top: 1rem; color: var(--admin-text-light); font-size: 0.9rem; text-align: center;">
            Mostrando <?php echo count($usuarios); ?> de <?php echo $total_resultados; ?> usuarios
        </div>

        <?php else: ?>
        <div style="text-align: center; padding: 3rem;">
            <i class="fas fa-users-slash" style="font-size: 4rem; color: var(--admin-text-lighter); margin-bottom: 1rem;"></i>
            <h3 style="color: var(--admin-text-light); margin-bottom: 1rem;">No se encontraron usuarios</h3>
            <p>No hay usuarios que coincidan con tu búsqueda.</p>
            <a href="users.php" class="admin-btn admin-btn-primary mt-3">
                <i class="fas fa-redo"></i> Ver todos los usuarios
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal Nuevo Usuario -->
    <div id="modal-usuario" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5);">
        <div class="modal-content" style="background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0;">Nuevo Usuario</h2>
                <span onclick="document.getElementById('modal-usuario').style.display='none'" style="color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_user">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Nombre *</label>
                        <input type="text" name="nombre" class="form-control" required style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    
                    <div class="form-group">
                        <label>Apellido *</label>
                        <input type="text" name="apellido" class="form-control" required style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    
                    <div class="form-group">
                        <label>Cédula *</label>
                        <input type="text" name="cedula" class="form-control" required style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" name="telefono" class="form-control" style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>

                    <div class="form-group">
                        <label>Fecha de Nacimiento</label>
                        <input type="date" name="fecha_nacimiento" class="form-control" style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>

                    <div class="form-group">
                        <label>Género</label>
                        <select name="genero" class="form-control" style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="">Seleccionar</option>
                            <option value="M">Masculino</option>
                            <option value="F">Femenino</option>
                            <option value="O">Otro</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <label>Email *</label>
                    <input type="email" name="email" class="form-control" required style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <label>Contraseña *</label>
                    <input type="password" name="password" class="form-control" required minlength="6" style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                
                <div class="form-group" style="margin-top: 15px; margin-bottom: 20px;">
                    <label>Tipo de Usuario *</label>
                    <select name="tipo_usuario" class="form-control" required style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="corredor">Corredor</option>
                        <option value="organizador">Organizador</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                
                <div style="text-align: right;">
                    <button type="button" onclick="document.getElementById('modal-usuario').style.display='none'" class="admin-btn admin-btn-secondary" style="margin-right: 10px;">Cancelar</button>
                    <button type="submit" class="admin-btn admin-btn-primary">Crear Usuario</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Nueva Inscripción -->
    <div id="modalNuevaInscripcion" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5);">
        <div class="modal-content" style="background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0;"><i class="fas fa-user-plus"></i> Inscribir a Evento</h2>
                <span onclick="document.getElementById('modalNuevaInscripcion').style.display='none'" style="color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
            </div>
            <form method="POST" action="" id="formNuevaInscripcion">
                <input type="hidden" name="action" value="crear_inscripcion">
                <input type="hidden" name="id_usuario" id="id_usuario_inscripcion" value="">
                
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <div class="form-group">
                        <label>Usuario Seleccionado</label>
                        <input type="text" id="nombre_usuario_inscripcion" class="form-control" readonly style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px; background-color: #f5f5f5; cursor: not-allowed;">
                    </div>

                    <div class="form-group">
                        <label for="id_evento_nuevo">Evento *</label>
                        <select name="id_evento" id="id_evento_nuevo" class="form-control" required onchange="actualizarCamposEvento()" style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="">Seleccione un evento...</option>
                            <?php foreach ($eventos as $e): ?>
                            <option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" id="container_categoria" style="display: none;">
                        <label for="categoria_nueva">Categoría *</label>
                        <select name="categoria" id="categoria_nueva" class="form-control" required style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="">Seleccione...</option>
                            <!-- Llenado dinámicamente -->
                        </select>
                    </div>

                    <div class="form-group" id="container_modalidad" style="display: none;">
                        <label for="modalidad_nueva">Modalidad *</label>
                        <select name="modalidad" id="modalidad_nueva" class="form-control" style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="">Seleccione...</option>
                        </select>
                    </div>

                    <div class="form-group" id="container_camiseta" style="display: none;">
                        <label for="talla_camiseta_nueva">Talla Camiseta *</label>
                        <select name="talla_camiseta" id="talla_camiseta_nueva" class="form-control" style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="">Seleccione...</option>
                            <option value="Kid 8">Kid 8</option>
                            <option value="Kid 10">Kid 10</option>
                            <option value="Kid 12">Kid 12</option>
                            <option value="Kid 14">Kid 14</option>
                            <option value="XS">XS</option>
                            <option value="S">S</option>
                            <option value="M">M</option>
                            <option value="L">L</option>
                            <option value="XL">XL</option>
                            <option value="XXL">XXL</option>
                        </select>
                    </div>

                    <div class="form-row" style="display: flex; gap: 15px;">
                        <div class="form-group" style="flex: 1;">
                            <label for="contacto_emergencia">Contacto Emergencia *</label>
                            <input type="text" name="contacto_emergencia" id="contacto_emergencia" class="form-control" required style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="telefono_emergencia">Teléfono Emergencia *</label>
                            <input type="tel" name="telefono_emergencia" id="telefono_emergencia" class="form-control" required style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="notas_medicas">Notas Médicas (Opcional)</label>
                        <textarea name="notas_medicas" id="notas_medicas" class="form-control" rows="2" style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                    </div>

                    <div class="form-group" id="container_pago" style="display: none;">
                        <label for="metodo_pago_nuevo">Método de Pago *</label>
                        <select name="metodo_pago" id="metodo_pago_nuevo" class="form-control" style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="">Seleccione...</option>
                        </select>
                    </div>

                </div>
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="admin-btn admin-btn-secondary" onclick="document.getElementById('modalNuevaInscripcion').style.display='none'" style="margin-right: 10px;">Cancelar</button>
                    <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-save"></i> Guardar Inscripción</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Exportar datos -->
    <!-- Exportar datos -->
    <?php $export_type = 'users'; include '../../includes/export-buttons.php'; ?>
</div>

<script>
// Auto-ocultar notificaciones después de 5 segundos
setTimeout(() => {
    document.querySelectorAll('.admin-alert').forEach(notification => {
        notification.style.display = 'none';
    });
}, 5000);

// Exportar datos
function exportData(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('export', format);
    
    window.location.href = 'export_users.php?' + params.toString();
}

// Búsqueda con Enter
document.getElementById('busqueda')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        this.form.submit();
    }
});

function openInscriptionModal(userId, userName) {
    document.getElementById('id_usuario_inscripcion').value = userId;
    document.getElementById('nombre_usuario_inscripcion').value = userName;
    document.getElementById('modalNuevaInscripcion').style.display = 'block';
    
    // Reset form
    document.getElementById('id_evento_nuevo').value = '';
    actualizarCamposEvento();
}

const eventosData = <?php echo json_encode($eventos_admin_data); ?>;

function actualizarCamposEvento() {
    const id_evento = document.getElementById('id_evento_nuevo').value;
    const contCategoria = document.getElementById('container_categoria');
    const selectCategoria = document.getElementById('categoria_nueva');
    const contModalidad = document.getElementById('container_modalidad');
    const selectModalidad = document.getElementById('modalidad_nueva');
    const contCamiseta = document.getElementById('container_camiseta');
    const selectCamiseta = document.getElementById('talla_camiseta_nueva');
    const contPago = document.getElementById('container_pago');
    const selectPago = document.getElementById('metodo_pago_nuevo');
    
    if (!id_evento || !eventosData[id_evento]) {
        contCategoria.style.display = 'none';
        selectCategoria.required = false;
        contModalidad.style.display = 'none';
        selectModalidad.required = false;
        contCamiseta.style.display = 'none';
        selectCamiseta.required = false;
        contPago.style.display = 'none';
        selectPago.required = false;
        return;
    }

    const ev = eventosData[id_evento];

    // Categoría
    contCategoria.style.display = 'block';
    selectCategoria.required = true;
    selectCategoria.innerHTML = '<option value="">Seleccione...</option>';
    if(ev.tipo_evento === 'carrera' || ev.tipo_evento === 'caminata') {
        selectCategoria.innerHTML += `
            <option value="principiante">Principiante</option>
            <option value="intermedio">Intermedio</option>
            <option value="avanzado">Avanzado</option>
            <option value="elite">Élite</option>
        `;
    } else {
         selectCategoria.innerHTML += `<option value="general">Participante General</option>`;
         selectCategoria.value = 'general';
    }

    // Modalidad
    try {
        const modalidades = ev.modalidades ? JSON.parse(ev.modalidades) : [];
        if(modalidades.length > 0) {
            contModalidad.style.display = 'block';
            selectModalidad.required = true;
            selectModalidad.innerHTML = '<option value="">Seleccione...</option>';
            modalidades.forEach(m => {
                const nombre = m.nombre ? m.nombre : m;
                const precio = m.precio ? m.precio : ev.precio;
                selectModalidad.innerHTML += `<option value="${nombre}">${nombre} ($${precio})</option>`;
            });
        } else {
            contModalidad.style.display = 'none';
            selectModalidad.required = false;
        }
    } catch(e) {
        contModalidad.style.display = 'none';
        selectModalidad.required = false;
    }

    // Camiseta
    if(ev.incluye_camiseta == 1) {
        contCamiseta.style.display = 'block';
        selectCamiseta.required = true;
    } else {
        contCamiseta.style.display = 'none';
        selectCamiseta.required = false;
    }

    // Pago
    if(parseFloat(ev.precio) > 0) {
        contPago.style.display = 'block';
        selectPago.required = true;
        selectPago.innerHTML = '<option value="">Seleccione...</option>';
        const metodos = ev.metodo_pago ? ev.metodo_pago.split(',') : ['transferencia'];
        metodos.forEach(m => {
             let label = m;
             if(m === 'transferencia') label = 'Transferencia Bancaria';
             if(m === 'tarjeta') label = 'Tarjeta de Crédito/Débito';
             if(m === 'efectivo') label = 'Efectivo';
             selectPago.innerHTML += `<option value="${m}">${label}</option>`;
        });
    } else {
        contPago.style.display = 'none';
        selectPago.required = false;
    }
}
</script>

<?php include '../../includes/admin-footer.php'; ?>