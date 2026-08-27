<?php
// modules/events/register.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/SMTPMailer.php';


if (!isset($_SESSION['user_id'])) {
    header('Location: /modules/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if (!isset($_GET['event_id']) || !is_numeric($_GET['event_id'])) {
    header('Location: index.php');
    exit();
}

$event_id = intval($_GET['event_id']);

// Obtener información del evento
$query = "SELECT * FROM eventos WHERE id = ?";
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

// Verificar si el evento está activo y tiene cupo
if ($evento['estado'] !== 'activo') {
    $error = 'Este evento no está disponible para inscripción';
} elseif ($evento['cupo_disponible'] <= 0) {
    $error = 'No hay cupos disponibles para este evento';
} elseif (!empty($evento['fecha_limite_inscripcion']) && strtotime($evento['fecha_limite_inscripcion']) < time()) {
    $error = 'La fecha límite de inscripción para este evento ha pasado';
}

// --- Buscar Oferta Temporal Activa ---
$active_offer = null;
$query_offer = "SELECT * FROM promociones WHERE tipo = 'oferta' AND estado = 'activo' AND (id_evento IS NULL OR id_evento = ?) AND fecha_inicio <= NOW() AND fecha_fin >= NOW() ORDER BY id_evento DESC, porcentaje DESC LIMIT 1";
$stmt_offer = $db->prepare($query_offer);
$stmt_offer->bind_param("i", $event_id);
$stmt_offer->execute();
$res_offer = $stmt_offer->get_result();
if ($res_offer->num_rows > 0) {
    $active_offer = $res_offer->fetch_assoc();
}
$stmt_offer->close();

// Verificar si el usuario ya está inscrito
$query = "SELECT id, estado FROM inscripciones WHERE id_usuario = ? AND id_evento = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("ii", $user_id, $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $inscripcion = $result->fetch_assoc();
    if ($inscripcion['estado'] === 'confirmada') {
        $error = 'Ya estás inscrito en este evento';
    } elseif ($inscripcion['estado'] === 'pendiente') {
        $error = 'Ya tienes una inscripción pendiente para este evento';
    }
}
$stmt->close();

// Obtener información del usuario
$query = "SELECT * FROM usuarios WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();
$stmt->close();

// Procesar inscripción
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    // --- Recopilar datos del Usuario Principal ---
    $categoria_main = $_POST['categoria'];
    $modalidad_main = isset($_POST['modalidad']) ? $_POST['modalidad'] : NULL;
    $talla_main = isset($_POST['talla_camiseta']) ? $_POST['talla_camiseta'] : NULL;
    $emergency_contact_main = trim($_POST['emergency_contact']);
    $emergency_phone_main = trim($_POST['emergency_phone']);
    $medical_notes_main = trim($_POST['medical_notes']);
    $metodo_pago_seleccionado = isset($_POST['metodo_pago']) ? $_POST['metodo_pago'] : 'gratuito';
    $promo_code = isset($_POST['promo_code']) ? trim($_POST['promo_code']) : '';

    // --- Recopilar Invitados ---
    $guests = [];
    if (isset($_POST['guest_nombre']) && is_array($_POST['guest_nombre'])) {
        for ($i = 0; $i < count($_POST['guest_nombre']); $i++) {
            $guests[] = [
                'nombre' => trim($_POST['guest_nombre'][$i]),
                'apellido' => trim($_POST['guest_apellido'][$i]),
                'email' => trim($_POST['guest_email'][$i]),
                'telefono' => trim($_POST['guest_telefono'][$i]),
                'fecha_nacimiento' => $_POST['guest_fecha_nacimiento'][$i],
                'genero' => $_POST['guest_genero'][$i],
                'categoria' => $_POST['guest_categoria'][$i],
                'modalidad' => isset($_POST['guest_modalidad'][$i]) ? $_POST['guest_modalidad'][$i] : NULL,
                'talla_camiseta' => isset($_POST['guest_talla_camiseta'][$i]) ? $_POST['guest_talla_camiseta'][$i] : NULL,
                'contacto_emergencia' => $_POST['guest_emergency_contact'][$i],
                'telefono_emergencia' => $_POST['guest_emergency_phone'][$i],
                'notas_medicas' => $_POST['guest_medical_notes'][$i]
            ];
        }
    }

    // --- Validar Cupo Total ---
    $total_pax = 1 + count($guests);
    if ($evento['cupo_disponible'] < $total_pax) {
        $error = "Lo sentimos, solo quedan " . $evento['cupo_disponible'] . " cupos disponibles. Estás intentando registrar " . $total_pax . " personas.";
    }

    if (empty($error)) {
        // --- Calcular Precios ---
        $modalidades_data = !empty($evento['modalidades']) ? json_decode($evento['modalidades'], true) : [];
        
        // Helper para obtener precio
        function getPriceForModality($mod_name, $event_price, $mods_data) {
            if ($mod_name && !empty($mods_data)) {
                foreach ($mods_data as $m) {
                    if (is_array($m) && isset($m['nombre']) && $m['nombre'] === $mod_name) {
                        return floatval($m['precio']);
                    }
                }
            }
            return floatval($event_price);
        }

        $precio_total = getPriceForModality($modalidad_main, $evento['precio'], $modalidades_data);
        
        foreach ($guests as $g) {
            $precio_total += getPriceForModality($g['modalidad'], $evento['precio'], $modalidades_data);
        }

        // --- Aplicar Promoción (Prioridad: Cupón > Oferta Automática) ---
        $descuento_aplicado = 0;
        $id_promocion = NULL;
        
        // 1. Intentar validar Cupón (si fue enviado)
        if (!empty($promo_code) && $precio_total > 0) {
            $stmt_promo = $db->prepare("SELECT * FROM promociones WHERE codigo = ? AND estado = 'activo' AND (id_evento IS NULL OR id_evento = ?) AND fecha_inicio <= NOW() AND fecha_fin >= NOW() LIMIT 1");
            $stmt_promo->bind_param("si", $promo_code, $event_id);
            $stmt_promo->execute();
            $res_promo = $stmt_promo->get_result();
            
            if ($res_promo->num_rows > 0) {
                $promo = $res_promo->fetch_assoc();
                if ($promo['cantidad'] > 0 && $promo['usados'] < $promo['cantidad']) {
                    $descuento_aplicado = ($precio_total * $promo['porcentaje']) / 100;
                    $id_promocion = $promo['id'];
                    $db->query("UPDATE promociones SET usados = usados + 1 WHERE id = " . $promo['id']);
                }
            }
            $stmt_promo->close();
        }
        
        // 2. Si no hay cupón aplicado, verificar Oferta Automática
        if ($descuento_aplicado == 0 && $active_offer && $precio_total > 0) {
            // Re-validar límites de la oferta
            // Nota: Aquí usamos $active_offer que ya consultamos arriba.
            // Para ser estrictos deberíamos refrescar o confiar en la consulta anterior.
            // Verificamos cantidad
            if ($active_offer['cantidad'] > 0 && $active_offer['usados'] < $active_offer['cantidad']) {
                 $descuento_aplicado = ($precio_total * $active_offer['porcentaje']) / 100;
                 $id_promocion = $active_offer['id'];
                 $db->query("UPDATE promociones SET usados = usados + 1 WHERE id = " . $active_offer['id']);
            }
        }
        
        $precio_final = max(0, $precio_total - $descuento_aplicado);

        // --- Iniciar Transacción ---
        $db->begin_transaction();
        try {
            // Obtenemos la última secuencia para generar la siguiente
            $prefix = 'RUN' . $event_id;
            $stmt_seq = $db->prepare("SELECT numero_corredor FROM inscripciones WHERE id_evento = ? AND numero_corredor LIKE ? ORDER BY LENGTH(numero_corredor) DESC, numero_corredor DESC LIMIT 1 FOR UPDATE");
            $like_pattern = $prefix . '%';
            $stmt_seq->bind_param("is", $event_id, $like_pattern);
            $stmt_seq->execute();
            $result_seq = $stmt_seq->get_result();
            
            $seq_count = 0;
            if ($row_seq = $result_seq->fetch_assoc()) {
                $last_num = $row_seq['numero_corredor'];
                $seq_count = intval(substr($last_num, strlen($prefix)));
            }
            $stmt_seq->close();

            $current_sequence = $seq_count + 1;

            // 1. Insertar Main User
            // Formato: RUN + event_id + 4 digitos (secuencia)
            $numero_corredor_main = 'RUN' . $event_id . str_pad($current_sequence, 4, '0', STR_PAD_LEFT);
            $current_sequence++;
            
            $stmt = $db->prepare("INSERT INTO inscripciones (id_usuario, id_evento, numero_corredor, categoria, estado, contacto_emergencia, telefono_emergencia, notas_medicas, id_promocion, modalidad, talla_camiseta) VALUES (?, ?, ?, ?, 'confirmada', ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssssiss", $user_id, $event_id, $numero_corredor_main, $categoria_main, $emergency_contact_main, $emergency_phone_main, $medical_notes_main, $id_promocion, $modalidad_main, $talla_main);
            $stmt->execute();
            $main_inscription_id = $stmt->insert_id;
            $stmt->close();

            // 2. Insertar Guests
            foreach ($guests as $idx => $g) {
                // Generar numero de corredor para el guest siguiendo la secuencia
                $num_corredor_guest = 'RUN' . $event_id . str_pad($current_sequence, 4, '0', STR_PAD_LEFT);
                $current_sequence++;

                $es_invitado = 1;
                // Insert guest with EXTRA fields
                // SQL debe coincidir con la migración
                $stmt_g = $db->prepare("INSERT INTO inscripciones (id_usuario, id_evento, numero_corredor, categoria, estado, contacto_emergencia, telefono_emergencia, notas_medicas, id_promocion, modalidad, parent_id, es_invitado, nombre, apellido, email, telefono, fecha_nacimiento, genero, talla_camiseta) VALUES (NULL, ?, ?, ?, 'confirmada', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt_g->bind_param("isssssisiisssssss", 
                    $event_id, $num_corredor_guest, $g['categoria'], 
                    $g['contacto_emergencia'], $g['telefono_emergencia'], $g['notas_medicas'], 
                    $id_promocion, $g['modalidad'], $main_inscription_id, $es_invitado,
                    $g['nombre'], $g['apellido'], $g['email'], $g['telefono'], $g['fecha_nacimiento'], $g['genero'],
                    $g['talla_camiseta']
                );
                $stmt_g->execute();
                $stmt_g->close();
            }

            // 3. Actualizar Cupo
            $stmt_cupo = $db->prepare("UPDATE eventos SET cupo_disponible = cupo_disponible - ? WHERE id = ?");
            $stmt_cupo->bind_param("ii", $total_pax, $event_id);
            $stmt_cupo->execute();
            $stmt_cupo->close();

            // 4. Crear Pago Único
            if ($precio_final > 0 && $metodo_pago_seleccionado !== 'gratuito') {
                $stmt_pago = $db->prepare("INSERT INTO pagos (id_inscripcion, monto, metodo_pago, estado, fecha_vencimiento) VALUES (?, ?, ?, 'pendiente', DATE_ADD(NOW(), INTERVAL 7 DAY))");
                $stmt_pago->bind_param("ids", $main_inscription_id, $precio_final, $metodo_pago_seleccionado);
                $stmt_pago->execute();
                $stmt_pago->close();
            }

            $db->commit();
            $success = "¡Inscripción exitosa! Se han registrado " . $total_pax . " participante(s).";
            
            // --- Enviar Correo Confirmación ---
            $mailer = new SMTPMailer();
            $subject = "Confirmación de Inscripción - " . $evento['nombre'];
            $message = "<h2>¡Inscripción Confirmada!</h2>";
            $message .= "<p>Hola " . $usuario['nombre'] . ",</p>";
            $message .= "<p>Has registrado exitosamente " . $total_pax . " persona(s) al evento: <strong>" . $evento['nombre'] . "</strong></p>";
            
            // Detalle Principal
            $message .= "<h3>Participante Principal: " . htmlspecialchars($usuario['nombre']) . "</h3>";
            $message .= "<ul><li># Corredor: $numero_corredor_main</li><li>Modalidad: " . ($modalidad_main ?? 'N/A') . "</li></ul>";
            
            // Detalle Guests
            if (!empty($guests)) {
                $message .= "<h3>Invitados:</h3><ul>";
                foreach ($guests as $g) {
                    $message .= "<li><strong>" . htmlspecialchars($g['nombre'] . ' ' . $g['apellido']) . "</strong> - Mod: " . ($g['modalidad'] ?? 'N/A') . "</li>";
                }
                $message .= "</ul>";
            }
            
            if ($precio_final > 0) {
                 $message .= "<p><strong>Total a Pagar: $" . number_format($precio_final, 2) . "</strong> (" . ucfirst($metodo_pago_seleccionado) . ")</p>";
                 $message .= "<p>Muy cordialmente te invitamos a realizar el pago de inscripción en nuestra cuenta via deposito o transferencia:</p";
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

            $mailer->send($usuario['email'], $subject, $message);

            // Redireccionar a mis inscripciones después de 3 segundos
            header('refresh:4;url=/modules/events/my-inscriptions.php');
            // exit(); // Removed to allow rendering of success message
            
        } catch (Exception $e) {
            $db->rollback();
            $error = "Error en la transacción: " . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<div class="container">
    <div style="width: 100%; margin: 0 auto;">
        <!-- Breadcrumb -->
        <div style="margin-bottom: 2rem;">
            <a href="view.php?id=<?php echo $event_id; ?>" style="color: var(--primary-color); text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Volver al evento
            </a>
        </div>
        
        <h1 style="color: var(--primary-color); margin-bottom: 2rem; text-align: center;">
            <i class="fas fa-user-plus"></i> Inscripción al Evento
        </h1>
        
        <?php if ($error): ?>
        <div style="
            background: #ffebee;
            color: #c62828;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 15px;
        ">
            <i class="fas fa-exclamation-circle" style="font-size: 1.5rem;"></i>
            <div>
                <h3 style="margin: 0 0 0.5rem 0;">Error en la inscripción</h3>
                <p style="margin: 0;"><?php echo $error; ?></p>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 2rem;">
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-running"></i> Ver otros eventos
            </a>
        </div>
        
        <?php else: ?>
        
        <?php if (!$success): ?>
        <form method="POST" action="" class="form-container">
        <?php endif; ?>

        <div class="registration-grid">
            <!-- Resumen del Evento -->
            <div style="
                background: white;
                border-radius: var(--border-radius);
                padding: 2rem;
                box-shadow: var(--box-shadow);
            ">
                <h3 style="color: var(--primary-color); margin-bottom: 1.5rem; border-bottom: 2px solid var(--primary-light); padding-bottom: 0.5rem;">
                    <i class="fas fa-calendar-check"></i> Resumen del Evento
                </h3>
                
                <div style="margin-bottom: 1.5rem;">
                    <h2 style="color: var(--text-dark); margin-bottom: 0.5rem;">
                        <?php echo htmlspecialchars($evento['nombre']); ?>
                    </h2>
                    <p style="color: var(--text-light);">
                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($evento['ubicacion']); ?>
                    </p>
                </div>
                
                <div style="
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                    gap: 1rem;
                    margin-bottom: 1.5rem;
                ">
                    <div style="text-align: center;">
                        <div style="
                            background: var(--primary-light);
                            color: white;
                            width: 60px;
                            height: 60px;
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin: 0 auto 0.5rem;
                            font-size: 1.5rem;
                        ">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div style="color: var(--text-dark); font-weight: 500;">Fecha</div>
                        <div style="color: var(--text-light); font-size: 0.9rem;">
                            <?php echo date('d/m/Y', strtotime($evento['fecha_evento'])); ?>
                        </div>
                    </div>
                    
                    <div style="text-align: center;">
                        <div style="
                            background: var(--primary-light);
                            color: white;
                            width: 60px;
                            height: 60px;
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin: 0 auto 0.5rem;
                            font-size: 1.5rem;
                        ">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div style="color: var(--text-dark); font-weight: 500;">Hora</div>
                        <div style="color: var(--text-light); font-size: 0.9rem;">
                            <?php echo date('h:i A', strtotime($evento['fecha_evento'])); ?>
                        </div>
                    </div>
                    
                    <?php if ($evento['distancia'] > 0): ?>
                    <div style="text-align: center;">
                        <div style="
                            background: var(--primary-light);
                            color: white;
                            width: 60px;
                            height: 60px;
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin: 0 auto 0.5rem;
                            font-size: 1.5rem;
                        ">
                            <i class="fas fa-route"></i>
                        </div>
                        <div style="color: var(--text-dark); font-weight: 500;">Distancia</div>
                        <div style="color: var(--text-light); font-size: 0.9rem;">
                            <?php echo $evento['distancia']; ?> km
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($evento['precio'] > 0): ?>
                <div style="
                    background: #fff8e1;
                    border-left: 4px solid var(--warning);
                    padding: 1rem;
                    border-radius: 4px;
                    margin-bottom: 1.5rem;
                ">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="color: var(--text-dark);">Costo de inscripción:</strong>
                             <div style="color: var(--text-light); margin: 0.5rem 0 0 0; font-size: 0.9rem;">
                                Métodos de pago disponibles: 
                                <?php 
                                    $metodos = explode(',', $evento['metodo_pago']);
                                    $metodos_fmt = array_map('ucfirst', $metodos);
                                    echo '<strong>' . implode(', ', $metodos_fmt) . '</strong>';
                                ?>
                            </div>
                        </div>
                        <div style="
                            background: var(--warning);
                            color: white;
                            padding: 0.5rem 1rem;
                            border-radius: 20px;
                            font-weight: bold;
                            font-size: 1.2rem;
                        ">
                            $<?php echo number_format($evento['precio'], 2); ?>
                        </div>
                    </div>
                    
                    <!-- Promo Code Input -->
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed #ccc;">
                        <label for="promo_code_input" style="display: block; font-size: 0.9rem; margin-bottom: 0.5rem; color: var(--text-dark);">
                            <i class="fas fa-tag"></i> ¿Tienes un código promocional?
                        </label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" id="promo_code_input" class="form-control" placeholder="Ingresa tu código" style="text-transform: uppercase;" onkeydown="return event.key !== 'Enter';">
                            <button type="button" id="apply_promo_btn" class="btn btn-secondary" style="white-space: nowrap;">
                                Aplicar
                            </button>
                        </div>
                        <div id="promo_message" style="font-size: 0.85rem; margin-top: 5px;"></div>
                        <input type="hidden" name="promo_code" id="promo_code_hidden">
                    </div>
                </div>
                <?php else: ?>
                <div style="
                    background: #e8f5e9;
                    border-left: 4px solid var(--success);
                    padding: 1rem;
                    border-radius: 4px;
                    margin-bottom: 1.5rem;
                ">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: var(--success);"></i>
                        <span style="color: var(--text-dark); font-weight: 500;">¡Evento gratuito!</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Columna Derecha: Formulario o Success Message -->
            <div>
                <?php if ($success): ?>
                <div style="
                    background: #e8f5e9;
                    color: #2e7d32;
                    padding: 2rem;
                    border-radius: var(--border-radius);
                    text-align: center;
                    box-shadow: var(--box-shadow);
                ">
                    <i class="fas fa-check-circle" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <h2 style="margin-bottom: 1rem;">¡Inscripción Exitosa!</h2>
                    <p style="font-size: 1.1rem; margin-bottom: 1rem;"><?php echo $success; ?></p>
                    <p style="color: var(--text-light);">
                        Serás redirigido a tu perfil en 3 segundos...
                    </p>
                    <a href="/modules/auth/profile.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-user-circle"></i> Ir a Mi Perfil
                    </a>
                </div>
                <?php else: ?>
                
                <div style="background: white; border-radius: var(--border-radius); padding: 2rem; box-shadow: var(--box-shadow);">
                    <h3 style="color: var(--primary-color); margin-bottom: 1.5rem; border-bottom: 2px solid var(--primary-light); padding-bottom: 0.5rem;">
                        <i class="fas fa-edit"></i> Completa tu Inscripción
                    </h3>
                    
                    <div class="form-group">
                        <label for="categoria">
                            <i class="fas fa-<?php echo ($evento['tipo_evento'] == 'carrera') ? 'running' : 'user-tag'; ?>"></i> Categoría *
                        </label>
                        <select id="categoria" name="categoria" class="form-control" required>
                            <?php if (in_array($evento['tipo_evento'], ['carrera', 'caminata'])): ?>
                            <option value="">Selecciona tu categoría</option>
                            <option value="principiante">Principiante (Primera carrera 5K)</option>
                            <option value="intermedio">Intermedio (1-5 carreras completadas)</option>
                            <option value="avanzado">Avanzado (6+ carreras completadas)</option>
                            <option value="elite">Élite (Atleta competitivo)</option>
                            <?php else: ?>
                            <option value="general" selected>Participante General</option>
                            <?php endif; ?>
                        </select>
                        <?php if (in_array($evento['tipo_evento'], ['carrera', 'caminata'])): ?>
                        <small style="color: var(--text-light);">Selecciona la categoría que mejor describa tu nivel</small>
                        <?php endif; ?>
                    </div>
                    
                    <?php 
                    $modalidades = isset($evento['modalidades']) && !empty($evento['modalidades']) ? json_decode($evento['modalidades'], true) : [];
                    if (!empty($modalidades)): 
                    ?>
                    <div class="form-group">
                        <label>
                            <i class="fas fa-route"></i> Distancia / Modalidad *
                        </label>
                        <div style="background: var(--bg-light); padding: 15px; border-radius: 4px;">
                            <?php 
                                $first_mod = true;
                                foreach ($modalidades as $mod): 
                                $nombre = is_array($mod) ? $mod['nombre'] : $mod;
                                $precio = is_array($mod) ? $mod['precio'] : $evento['precio'];
                                $checked = $first_mod ? 'checked' : '';
                                $first_mod = false;
                            ?>
                            <div class="custom-control custom-radio mb-2" style="display: flex; align-items: center; gap: 8px;">
                                <input type="radio" id="mod_<?php echo md5($nombre); ?>" name="modalidad" class="custom-control-input modality-radio" 
                                       value="<?php echo htmlspecialchars($nombre); ?>" 
                                       data-price="<?php echo $precio; ?>"
                                       required style="margin-top: 0;" <?php echo $checked; ?>>
                                <label class="custom-control-label" for="mod_<?php echo md5($nombre); ?>" style="align-items: center; margin-bottom: 0; padding-top: 2px; width: 100%; display: flex; justify-content: space-between;">
                                    <span><?php echo htmlspecialchars($nombre); ?></span>
                                    <?php if ($precio > 0): ?>
                                    <span class="badge badge-light" style="font-size: 0.9em;">$<?php echo number_format($precio, 2); ?></span>
                                    <?php endif; ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <small style="color: var(--text-light);">Selecciona la distancia que deseas recorrer.</small>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($evento['incluye_camiseta']): ?>
                    <div class="form-group">
                        <label for="talla_camiseta">
                            <i class="fas fa-tshirt"></i> Talla de Camiseta *
                        </label>
                        <select id="talla_camiseta" name="talla_camiseta" class="form-control" required>
                            <option value="">Selecciona tu talla</option>
                            <option value="Kid 8">Kid 8</option>
                            <option value="Kid 10">Kid 10</option>
                            <option value="Kid 12">Kid 12</option>
                            <option value="Kid 14">Kid 14</option>
                            <option value="XS">XS - Extra Pequeña</option>
                            <option value="S">S - Pequeña</option>
                            <option value="M">M - Mediana</option>
                            <option value="L">L - Grande</option>
                            <option value="XL">XL - Extra Grande</option>
                            <option value="XXL">XXL - Doble Extra Grande</option>
                        </select>
                        <small style="color: var(--text-light);">El evento incluye camiseta. Selecciona tu talla preferida.</small>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($evento['precio'] > 0): ?>
                    <div class="form-group">
                        <label>
                            <i class="fas fa-credit-card"></i> Método de Pago *
                        </label>
                        <div style="background: var(--bg-light); padding: 15px; border-radius: 4px;">
                            <?php 
                                $metodos_disponibles = explode(',', $evento['metodo_pago']);
                                // Pre-seleccionar Transferencia si existe, sino el primero
                                $default_payment = in_array('transferencia', $metodos_disponibles) ? 'transferencia' : $metodos_disponibles[0];
                                
                                foreach ($metodos_disponibles as $metodo): 
                                    $checked = ($metodo === $default_payment) ? 'checked' : '';
                            ?>
                            <div class="custom-control custom-radio mb-2" style="display: flex; align-items: center; gap: 8px;">
                                <input type="radio" id="pago_<?php echo $metodo; ?>" name="metodo_pago" class="custom-control-input" value="<?php echo $metodo; ?>" required style="margin-top: 0;" <?php echo $checked; ?>>
                                <label class="custom-control-label" for="pago_<?php echo $metodo; ?>" style="margin-bottom: 0; padding-top: 2px;">
                                    <?php 
                                        switch($metodo) {
                                            case 'tarjeta': echo 'Tarjeta de Crédito/Débito'; break;
                                            case 'transferencia': echo 'Transferencia Bancaria'; break;
                                            case 'efectivo': echo 'Efectivo'; break;
                                            default: echo ucfirst($metodo);
                                        }
                                    ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <small style="color: var(--text-light);">Selecciona cómo realizarás el pago de tu inscripción.</small>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$success): ?>

            <div style="
                background: #e3f2fd;
                border-left: 4px solid var(--primary-color);
                padding: 1rem;
                border-radius: 4px;
                margin-bottom: 1.5rem;
            ">
                <h4 style="color: var(--primary-color); margin-bottom: 0.5rem;">
                    <i class="fas fa-heartbeat"></i> Información Médica y de Emergencia
                </h4>
                <p style="color: var(--text-light); font-size: 0.9rem; margin-bottom: 1rem;">
                    Esta información es confidencial y solo será usada en caso de emergencia durante el evento.
                </p>
                
                <div class="medical-info-grid">
                    <div class="form-group">
                        <label for="emergency_contact">Contacto de emergencia</label>
                        <input type="text" id="emergency_contact" name="emergency_contact" class="form-control"
                               placeholder="Nombre completo">
                    </div>
                    
                    <div class="form-group">
                        <label for="emergency_phone">Teléfono de emergencia</label>
                        <input type="tel" id="emergency_phone" name="emergency_phone" class="form-control"
                               placeholder="Número telefónico">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="medical_notes">Notas médicas importantes</label>
                    <textarea id="medical_notes" name="medical_notes" class="form-control" rows="3"
                              placeholder="Alergias, condiciones médicas, medicamentos, etc."></textarea>
                </div>
            </div>
            
            <!-- Invitados Section -->
            <div style="margin-bottom: 2rem; border-top: 1px dashed #ccc; padding-top: 1.5rem;">
                <h4 style="color: var(--primary-color); margin-bottom: 1rem;">
                    <i class="fas fa-users"></i> Participantes Adicionales / Invitados
                </h4>
                <div id="guests-container"></div>
                
                <button type="button" class="btn btn-outline-primary" id="add-guest-btn" style="width: 100%;">
                    <i class="fas fa-plus-circle"></i> Agregar Invitado
                </button>
            </div>

<style>
    /* Force Scrollbar Visibility */
    .terms-box::-webkit-scrollbar {
        -webkit-appearance: none;
        width: 12px;
        display: block;
        background-color: #f1f1f1;
    }
    .terms-box::-webkit-scrollbar-thumb {
        background-color: #888; 
        border-radius: 6px;
        border: 2px solid #f1f1f1;
    }
    .terms-box::-webkit-scrollbar-thumb:hover {
        background-color: #555;
    }
    .terms-box::-webkit-scrollbar-track {
        background-color: #f1f1f1; 
        display: block;
    }
    /* Firefox */
    .terms-box {
        scrollbar-width: auto;
        scrollbar-color: #888 #f1f1f1;
    }
</style>

            <!-- Términos y Condiciones -->
            <div style="margin-bottom: 1.5rem;">
                <div style="position: relative;">
                    <div class="terms-box" style="
                        background: var(--bg-light);
                        padding: 1rem;
                        border-radius: var(--border-radius);
                        max-height: 200px;
                        overflow-y: scroll;
                        margin-bottom: 0.5rem;
                        border: 1px solid #dee2e6;
                    ">
                        <h4 style="color: var(--text-dark); margin-bottom: 0.5rem;">Términos y Condiciones</h4>
                    <div style="color: var(--text-light); font-size: 0.9rem;">
                        <h5 style="color: var(--text-dark); margin-top: 1rem; font-weight: 600;">CONSENTIMIENTO DE INFORMACIÓN Y MATERIAL AUDIOVISUAL</h5>
                        <ul style="padding-left: 1.5rem; margin-bottom: 1rem;">
                            <li>Al ingresar tu información personal estás a la vez acordándonos el derecho de captar esa información, colocar la orden, y organizar la entrega. Dicha información solo será utilizada por nuestra empresa para la razón por la cual nos la proporcionas. Esta misma información será utilizada para mantenerte actualizado de los próximos eventos a través de nuestra página y/o encuestas de satisfacción de nuestros eventos. En caso de usar tu información personal fuera de nuestro portal siempre obtendremos tu consentimiento.</li>
                            <li>Con la participación en los eventos de Fit5k, proporciono autorización para que dentro del evento y actividades relacionadas con el mismo (entrega de kits, rueda de prensa, otros) sean tomadas fotografías, videos y cualquier otro medio de registro o reproducción de imágenes. Además autorizo a que los patrocinadores del evento puedan usar estas imágenes para promoción de sus productos en temas relacionados con el evento. En todos los casos mencionados, sin que violente las normas generales, la integridad o moral del participante tanto para eventos futuros como por parte de nuestros patrocinadores.</li>
                        </ul>

                        <h5 style="color: var(--text-dark); margin-top: 1rem; font-weight: 600;">SOBRE LA SALUD Y EL BIENESTAR</h5>
                        <ul style="padding-left: 1.5rem; margin-bottom: 1rem;">
                            <li>El participante acepta estar consciente de las actividades a realizar (Zumba, Correr o Caminar) siendo responsable y consciente de que se encuentra en las condiciones de salud adecuadas que le permitan realizar estas actividades, por lo que no somos responsables de incumplimiento de normas por condiciones de salud que impidan la realización de estas actividades.</li>
                        </ul>

                        <h5 style="color: var(--text-dark); margin-top: 1rem; font-weight: 600;">PARTICIPACIÓN DE MENORES DE EDAD</h5>
                        <ul style="padding-left: 1.5rem; margin-bottom: 1rem;">
                            <li>En caso de que el evento lo permita, puede acompañarse de menores de edad, siempre y cuando estén totalmente monitoreados por un adulto responsable del mismo (padre, madre o tutor). Nuestro personal no se hace responsable del control de niños menores de edad por lo que el adulto responsable debe siempre mantener el control sobre el menor.</li>
                            <li>Otras consideraciones en caso de participación de menores de edad:
                                <ul style="padding-left: 1.5rem; list-style-type: circle; margin-top: 0.5rem;">
                                    <li>Niños a partir de 8 años reciben kit por lo que tienen costo igual a los adultos.</li>
                                    <li>Niños menores de 7años pueden asistir en calidad de acompañante y no reciben medallas. Si desea que obtenga medalla debe de inscribirlo como participante y el costo será igual al kit de adultos.</li>
                                </ul>
                            </li>
                        </ul>

                        <h5 style="color: var(--text-dark); margin-top: 1rem; font-weight: 600;">PAGOS</h5>
                        <ul style="padding-left: 1.5rem; margin-bottom: 1rem;">
                            <li>Con tu registro reservas tu cupo y la elaboración del material correspondiente por lo que valoramos puedas realizar los pagos vía transferencia o depósito a la cuenta siguiente:
                                <br><strong>Banco Popular</strong>
                                <br>Cuenta Número: <strong>849257019</strong>
                                <br>Nombre: <strong>Arlene Báez</strong>
                                <br>Cédula: <strong>031-0321517-8</strong>
                            </li>
                            <li>Nuestro sitio Web no conserva ninguna información relacionada con tus cuentas o métodos de pago.</li>
                            <li>El pago debe de ser realizado vía transferencia o depósito directo dentro de la fecha establecida para que apliquen los descuentos. La tarifa válida es la del momento de realizar el pago.</li>
                            <li>No somos responsables de situaciones fuera de su alcance después de realizado el pago por lo que no hay devoluciones.</li>
                            <li>Entrega de kits según disponibilidad y/o orden de inscripción desde disponibilidad de kits hasta la fecha límite establecida.</li>
                            <li>Para procesar su inscripción debe de haber leído y aceptado las condiciones.</li>
                        </ul>

                        <p>A través de este documento declaro formal y expresamente que he leído y estoy de acuerdo con las reglas anteriormente presentadas para este evento.</p>
                        <p>En este mismo sentido, declaro de manera formal y expresamente que mi participación en este evento se realiza de forma voluntaria y por lo tanto libero a los organizadores de cualquier responsabilidad en situaciones relacionadas con accidentes, lesiones personales o cualquier perjuicio en el tiempo de realización del evento independiente a la voluntad de los organizadores.</p>
                        <p>Nos reservamos el derecho de realizar cambios en el evento en caso de que sea requerido por causas fuera del alcance de nuestro proceso de planificación.</p>
                        <p>El participante reconoce que las informaciones y los datos que ha suministrado son correctos y concuerdan con la persona que se describe.</p>

                        <h5 style="color: var(--text-dark); margin-top: 1rem; font-weight: 600;">DESCARGO DE RESPONSABILIDAD</h5>
                        <ol style="padding-left: 1.5rem; margin-bottom: 1rem;">
                            <li><strong>Asunción de Riesgo:</strong> Entiendo que participar en actividades físicas como Zumba, caminar o correr conlleva riesgos inherentes, que incluyen, entre otros: lesiones musculares, caídas, fatiga extrema, deshidratación y complicaciones cardiovasculares. Al inscribirme, certifico que me encuentro en condiciones físicas óptimas para realizar estas actividades y que no he sido advertido de lo contrario por un profesional médico.</li>
                            <li><strong>Liberación de Responsabilidad:</strong> Por la presente, libero y deslindo de toda responsabilidad a Fit5k, staff, colaboradores, voluntarios, patrocinadores y relacionados, de cualquier reclamo, demanda o causa de acción que surja por lesiones personales, daños a la propiedad o muerte accidental relacionados con mi participación en este evento.</li>
                            <li><strong>Atención Médica:</strong> En caso de lesión o emergencia médica durante el evento, autorizo al personal organizador a buscar asistencia médica o transporte a un centro hospitalario si fuera necesario. Acepto que cualquier costo derivado de dicha atención médica será de mi exclusiva responsabilidad.</li>
                            <li><strong>Derechos de Imagen:</strong> Autorizo el uso de fotografías o videos capturados durante el evento donde aparezca mi imagen, para fines promocionales, publicitarios o informativos en redes sociales, sitios web y otros medios de comunicación, sin derecho a compensación económica.</li>
                            <li><strong>Consentimiento y Firma:</strong> He leído detenidamente este documento y comprendo plenamente sus términos. Al marcar la casilla de aceptación acepto voluntariamente estos términos.</li>
                        </ol>

                        <h5 style="color: var(--text-dark); margin-top: 1rem; font-weight: 600;">POLÍTICAS DE DEVOLUCIÓN Y ENVÍO</h5>
                        <ol style="padding-left: 1.5rem; margin-bottom: 1rem;">
                            <li>No realizamos devolución de inscripciones a nuestros eventos por motivos de coordinación logística.</li>
                            <li>La venta de participación se realiza a través del registro en nuestro formulario y no contamos con punto de venta física.</li>
                            <li>De no recibir su correo de confirmación de inscripción favor de escribirnos a uno de los siguientes contactos: Whatsapp 829-923-0124 y/o Correo Electrónico: arlenebaez27@hotmail.com</li>
                            <li>El envío/entrega de su kit y/o material para el evento será comunicado a sus medios de contacto desde la disponibilidad hasta la fecha límite de entrega.</li>
                            <li>Los costos de envío corren por cuenta del participante.</li>
                        </ol>

                        <h5 style="color: var(--text-dark); margin-top: 1rem; font-weight: 600;">POLÍTICAS DE CANCELACIONES</h5>
                        <p>De ocurrir la necesidad de cancelación será informado a los participantes por uno de los medios autorizados por parte de organización del evento tomándose como acción una de las siguientes según aplique de acuerdo a las condiciones: reasignar fecha, hora, reembolso, cupo para un evento siguiente.</p>

                        <h5 style="color: var(--text-dark); margin-top: 1rem; font-weight: 600;">SECCIÓN 1 - INFORMACIÓN</h5>
                        <p>Al registrarte en nuestra página web, capturamos tu información personal, tal como tu nombre, fecha de nacimiento, contactos de emergencia y correo electrónico, como parte del proceso de registro en un evento. Esta información es manejada con alta confidencialidad, por lo cual nunca es revelada ni compartida con terceros. Al navegar en nuestra página web, capturamos tu dirección IP, tu navegador, y tu sistema operativo de forma automática, lo cual permite optimizar nuestra interacción contigo. Adicionalmente, aplicamos cookies para mejorar tu experiencia con nuestra página web. Con tu autorización, te enviamos correos sobre nuestros eventos y otras actualizaciones que realizamos.</p>
                        
                        <h6 style="color: var(--text-dark); margin-top: 1rem; font-weight: bold;">Políticas de Seguridad / WEBSITE</h6>
                        <p>Tomamos todas las medidas y precauciones razonables para proteger tu información personal y seguimos las mejores prácticas de la industria para asegurar que tu información no sea utilizada de manera inapropiada, alterada o destruida.</p>
                    </div>
                </div>
                <div style="text-align: right; color: var(--text-light); font-size: 0.8rem; margin-bottom: 1rem;">
                    <i class="fas fa-arrow-down"></i> Desliza para leer todo el contenido
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                        <input type="checkbox" id="terms" name="terms" required style="margin-top: 3px;">
                        <span style="color: var(--text-dark);">
                            He leído y acepto los términos y condiciones del evento *
                        </span>
                    </label>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 2rem;">
                <button type="submit" class="btn btn-success" style="padding: 1rem 3rem; font-size: 1.1rem;">
                    <i class="fas fa-check-circle"></i> Confirmar Inscripción
                </button>
            </div>
        </form>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>
</div>

<script>
// Validación adicional del formulario y Promo Code logic
document.addEventListener('DOMContentLoaded', function() {
    const promoInput = document.getElementById('promo_code_input');
    const applyBtn = document.getElementById('apply_promo_btn');
    const promoMessage = document.getElementById('promo_message');
    const promoHidden = document.getElementById('promo_code_hidden');
    const priceContainer = document.querySelector('div[style*="background: var(--warning)"]');
    
    // Configuración Inicial
    const eventPrice = <?php echo floatval($evento['precio']); ?>;
    const activeOffer = <?php echo $active_offer ? json_encode($active_offer) : 'null'; ?>;
    
    let basePrice = eventPrice; // Precio del Main User actual
    let guestPrices = []; // Array de precios de invitados
    let manualPromoApplied = false; // Indica si se aplicó un cupón manual
    let manualPromoData = null; // Datos del cupón manual
    
    // Función paramétrica para calcular el total
    function calculateTotal() {
        let total = basePrice;
        guestPrices.forEach(p => total += p);
        return total;
    }
    
    // Función para recalcular y actualizar UI globalmente
    function refreshPriceDisplay() {
        const total = calculateTotal();
        let finalTotal = total;
        let discountMsg = '';
        
        if (manualPromoApplied && manualPromoData) {
            // Prioridad: Cupón Manual
            const discount = (total * manualPromoData.porcentaje) / 100;
            finalTotal = total - discount;
            discountMsg = `<span style="color: green;"><i class="fas fa-check"></i> Cupón del ${manualPromoData.porcentaje}% aplicado (-$${discount.toFixed(2)})</span>`;
            
            // Actualizar mensaje de promo message div si existe
            if(document.getElementById('promo_message')) {
                // Ya se actualiza en el fetch, pero sync aquí si re-calculamos.
                // En realidad, el fetch ya seteó el mensaje.
            }
            
            updateTotalDisplay(total, finalTotal, 'cupón');
            
        } else if (activeOffer) {
            // Prioridad: Oferta Automática
            const discount = (total * activeOffer.porcentaje) / 100;
            finalTotal = total - discount;
            // discountMsg = `<span style="color: var(--primary-color); font-weight: bold;"><i class="fas fa-certificate"></i> Oferta "${activeOffer.nombre}": -${activeOffer.porcentaje}%</span>`;
            
            // Mostrar mensaje de oferta de alguna manera?
            // Podríamos inyectarlo cerca del precio
            
            updateTotalDisplay(total, finalTotal, 'oferta');
        } else {
             updateTotalDisplay(total);
        }
    }

    // --- Lógica Promo Code ---
    if (applyBtn && promoInput) {
        applyBtn.addEventListener('click', function() {
            const code = promoInput.value.trim();
            if(!code) return;
            
            applyBtn.disabled = true;
            applyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            // Usamos el total actual para validar
            const currentTotal = calculateTotal();

            fetch('../../modules/api/validate_promo.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    codigo: code,
                    event_id: <?php echo $evento['id']; ?>,
                    monto: currentTotal
                })
            })
            .then(response => response.json())
            .then(data => {
                applyBtn.disabled = false;
                applyBtn.textContent = 'Aplicar';
                if (data.success) {
                    promoMessage.innerHTML = `<span style="color: green;"><i class="fas fa-check"></i> Descuento de ${data.porcentaje}% aplicado (-$${data.descuento.toFixed(2)})</span>`;
                    promoHidden.value = code;
                    
                    // Set manual promo state
                    manualPromoApplied = true;
                    manualPromoData = { porcentaje: data.porcentaje };
                    
                    refreshPriceDisplay();
                    
                    // Lock input logic...
                    promoInput.disabled = true;
                    applyBtn.style.display = 'none';
                    promoMessage.innerHTML += ' <a href="#" id="remove_promo" style="color: red; font-size: 0.8rem;">(Quitar)</a>';
                    document.getElementById('remove_promo').addEventListener('click', function(e) {
                         e.preventDefault();
                         promoInput.disabled = false;
                         promoInput.value = '';
                         promoHidden.value = '';
                         promoMessage.innerHTML = '';
                         applyBtn.style.display = 'block';
                         
                         manualPromoApplied = false;
                         manualPromoData = null;
                         
                         refreshPriceDisplay(); // Restore normal price or offer
                    });
                } else {
                    promoMessage.innerHTML = `<span style="color: red;">${data.message}</span>`;
                    promoHidden.value = '';
                    refreshPriceDisplay();
                }
            })
            .catch(error => {
                applyBtn.disabled = false;
                applyBtn.textContent = 'Aplicar';
                console.error(error);
            });
        });
    }

    // --- Función para actualizar UI de precios ---
    function updateTotalDisplay(total, discountedTotal = null, type = null) {
        if (!priceContainer) return;
        
        let html = `<div style="display:flex; flex-direction:column; align-items:flex-end;">`;
        
        if (discountedTotal !== null && discountedTotal < total) {
            html += `<span style="font-size: 0.9rem; text-decoration: line-through; opacity: 0.7; color: #666;">$${total.toFixed(2)}</span>`;
            html += `<span style="color: #2e7d32; font-size: 1.3rem;">$${discountedTotal.toFixed(2)}</span>`;
            
            if (type === 'oferta' && activeOffer) {
                html += `<small style="color: var(--primary-color); font-size: 0.75rem;"><i class="fas fa-tags"></i> ${activeOffer.nombre} (-${activeOffer.porcentaje}%)</small>`;
            }
        } else {
             html += `<span style="font-size: 1.2rem;">$${total.toFixed(2)}</span>`;
        }
        
        html += `</div>`;
        priceContainer.innerHTML = html;
    }

    function updateMainPrice(price) {
        basePrice = price;
        // Si hay promo manual, la quitamos al cambiar precios base para obligar a re-validar (o simplemente refrescamos)
        // La logica anterior quitaba el promo. Mantengamos eso.
        if (manualPromoApplied && document.getElementById('remove_promo')) {
            document.getElementById('remove_promo').click();
        }
        refreshPriceDisplay();
    }
    
    // Init display
    refreshPriceDisplay();
    
    // --- Lógica Modalidades Main User ---
    const modalityRadios = document.querySelectorAll('.modality-radio');
    if (modalityRadios.length > 0) {
        modalityRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                updateMainPrice(parseFloat(this.getAttribute('data-price')));
            });
        });
        // Init default
        const selected = document.querySelector('.modality-radio:checked');
        if (selected) updateMainPrice(parseFloat(selected.getAttribute('data-price')));
    }

    // --- Lógica Guest ---
    const addGuestBtn = document.getElementById('add-guest-btn');
    const guestsContainer = document.getElementById('guests-container');
    let guestCount = 0;

    // Template para Guests
    // Nota: Replicamos los campos de categoría, modalidad, camiseta...
    // Necesitamos los datos de modalidades desde PHP
    const modalidadesData = <?php echo !empty($evento['modalidades']) ? $evento['modalidades'] : '[]'; ?>;
    const tipoEvento = "<?php echo $evento['tipo_evento']; ?>";
    const incluyeCamiseta = <?php echo $evento['incluye_camiseta'] ? 'true' : 'false'; ?>;
    
    // Generar opciones de modalidad HTML
    let modOptionsHtml = '';
    if (modalidadesData.length > 0) {
        modOptionsHtml += '<div class="form-group"><label>Distancia / Modalidad *</label><div class="p-3 bg-light rounded">';
        modalidadesData.forEach((mod, idx) => {
             const mName = mod.nombre || mod;
             const mPrice = mod.precio !== undefined ? mod.precio : eventPrice;
             // Unique name for radio group per guest
             modOptionsHtml += `
             <div class="custom-control custom-radio mb-2" style="display: flex; align-items: center; gap: 8px;">
                <input type="radio" id="g_mod_${mName}_INDEX" name="guest_modalidad[INDEX]" class="custom-control-input guest-mod-radio" value="${mName}" data-price="${mPrice}" data-index="INDEX" required ${idx===0?'checked':''}>
                <label class="custom-control-label" for="g_mod_${mName}_INDEX" style="width: 100%; display: flex; justify-content: space-between; align-items: center; margin-bottom: 0;">
                    <span>${mName}</span>
                    ${mPrice > 0 ? `<span class="badge badge-light">$${mPrice.toFixed(2)}</span>` : ''}
                </label>
             </div>`;
        });
        modOptionsHtml += '</div></div>';
    }

    if (addGuestBtn) {
        addGuestBtn.addEventListener('click', function() {
            guestCount++;
            const guestIndex = guestCount - 1; // 0-based for arrays
            
            // Build Modality HTML with Index replacement
            let currentModHtml = modOptionsHtml.replace(/INDEX/g, guestIndex);
            
            // Build Category Options
            let catOptions = '';
            if (['carrera', 'caminata'].includes(tipoEvento)) {
                catOptions = `
                    <option value="">Selecciona categoría</option>
                    <option value="principiante">Principiante</option>
                    <option value="intermedio">Intermedio</option>
                    <option value="avanzado">Avanzado</option>
                    <option value="elite">Élite</option>
                `;
            } else {
                catOptions = `<option value="general" selected>Participante General</option>`;
            }

            // Build TShirt HTML
            let tshirtHtml = '';
            if (incluyeCamiseta) {
                tshirtHtml = `
                    <div class="form-group col-md-6">
                        <label>Talla Camiseta *</label>
                        <select name="guest_talla_camiseta[]" class="form-control" required>
                             <option value="">Selecciona talla</option>
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
                `;
            }

            const card = document.createElement('div');
            card.className = "card mb-3 guest-card";
            card.style.border = "1px solid #ddd";
            card.innerHTML = `
                <div class="card-header d-flex justify-content-between align-items-center" style="background: #f8f9fa;">
                    <h5 class="mb-0 text-primary">Invitado #${guestCount}</h5>
                    <button type="button" class="btn btn-sm btn-danger remove-guest-btn" data-index="${guestIndex}" title="Eliminar participante">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="card-body">
                    <div class="form-row row">
                        <div class="form-group col-md-6">
                            <label>Nombre *</label>
                            <input type="text" name="guest_nombre[]" class="form-control" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Apellido *</label>
                            <input type="text" name="guest_apellido[]" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row row">
                        <div class="form-group col-md-6">
                            <label>Email *</label>
                            <input type="email" name="guest_email[]" class="form-control" required>
                        </div>
                        <div class="form-group col-md-6">
                             <label>Teléfono *</label>
                             <input type="tel" name="guest_telefono[]" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row row">
                        <div class="form-group col-md-6">
                            <label>Fecha Nacimiento *</label>
                            <input type="date" name="guest_fecha_nacimiento[]" class="form-control" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Género *</label>
                            <select name="guest_genero[]" class="form-control" required>
                                <option value="M">Masculino</option>
                                <option value="F">Femenino</option>
                                <option value="O">Otro</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row row">
                        <div class="form-group col-md-6">
                             <label>Categoría *</label>
                             <select name="guest_categoria[]" class="form-control" required>
                                ${catOptions}
                             </select>
                        </div>
                        ${tshirtHtml}
                    </div>

                    ${currentModHtml}

                    <div style="background: #e3f2fd; padding: 10px; border-radius: 4px; border-left: 3px solid #2196f3;">
                        <small class="d-block mb-2"><strong><i class="fas fa-notes-medical"></i> Emergencia</strong></small>
                        <div class="form-row row">
                             <div class="form-group col-md-6 mb-2">
                                 <input type="text" name="guest_emergency_contact[]" class="form-control form-control-sm" placeholder="Contacto urgencia" required>
                             </div>
                             <div class="form-group col-md-6 mb-2">
                                 <input type="tel" name="guest_emergency_phone[]" class="form-control form-control-sm" placeholder="Tel. Urgencia" required>
                             </div>
                             <div class="col-12">
                                 <textarea name="guest_medical_notes[]" class="form-control form-control-sm" rows="1" placeholder="Alergias/Condiciones"></textarea>
                             </div>
                        </div>
                    </div>
                </div>
            `;
            
            guestsContainer.appendChild(card);
            
            // Add price for default selected modality of this guest
            const defaultRadio = card.querySelector('.guest-mod-radio:checked');
            let initialGuestPrice = 0;
            if (defaultRadio) {
                initialGuestPrice = parseFloat(defaultRadio.dataset.price);
            } else {
                // If no modalities, maybe base `eventPrice`?
                // Logic assumes if `modalidades` exists, uses radios. If not, uses single event price.
                if (modalidadesData.length === 0) initialGuestPrice = eventPrice;
            }
            guestPrices[guestIndex] = initialGuestPrice;
            
            // Attach listeners to new radios
            const newRadios = card.querySelectorAll('.guest-mod-radio');
            newRadios.forEach(r => {
                r.addEventListener('change', function() {
                    const idx = parseInt(this.dataset.index);
                    guestPrices[idx] = parseFloat(this.dataset.price);
                     // Remove promo if applied
                    if (document.getElementById('remove_promo')) document.getElementById('remove_promo').click();
                    refreshPriceDisplay();
                });
            });

             // Remove promo if applied because count changed
            if (document.getElementById('remove_promo')) document.getElementById('remove_promo').click();
            refreshPriceDisplay();
            
            // Handle Remove
            card.querySelector('.remove-guest-btn').addEventListener('click', function() {
                card.remove();
                guestPrices[guestIndex] = 0; // Set to 0 (simplest way, though array grows)
                if (document.getElementById('remove_promo')) document.getElementById('remove_promo').click();
                refreshPriceDisplay();
            });
        });
    }

    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const terms = document.getElementById('terms');
            if (!terms.checked) {
                e.preventDefault();
                alert('Debes aceptar los términos y condiciones para continuar');
                terms.focus();
                return;
            }
            
            <?php if ($evento['precio'] > 0): ?>
            // Verificamos el precio visible actual
            let currentPrice = calculateTotal();
            // Check logic inside refreshPriceDisplay simulation
            if (manualPromoApplied && manualPromoData) {
                 currentPrice = currentPrice - (currentPrice * manualPromoData.porcentaje / 100);
            } else if (activeOffer) {
                 currentPrice = currentPrice - (currentPrice * activeOffer.porcentaje / 100);
            }
            if (priceContainer) {
                // Intentamos leer el precio visualmente por si hay descuento
                const text = priceContainer.innerText;
                const match = text.match(/\$([\d\.]+)/);
                if (match) currentPrice = parseFloat(match[1]);
            }
            
            // Si el precio a pagar es > 0, exigimos método de pago
            if (currentPrice > 0) {
                const metodoPago = document.querySelector('input[name="metodo_pago"]:checked');
                if (!metodoPago) {
                    e.preventDefault();
                    alert('Por favor, selecciona un método de pago para el total de $' + currentPrice.toFixed(2));
                    return;
                }
            }
            <?php endif; ?>
            
            const categoria = document.getElementById('categoria');
            if (!categoria.value) {
                e.preventDefault();
                alert('Por favor, selecciona tu categoría (Participante Principal)');
                categoria.focus();
                return;
            }
            
            const talla = document.getElementById('talla_camiseta');
            if (talla && !talla.value) {
                e.preventDefault();
                alert('Por favor, selecciona tu talla de camiseta (Participante Principal)');
                talla.focus();
                return;
            }
        });
    }
});
</script>

<?php include '../../includes/footer.php'; ?>