<?php
// modules/admin/edit-event.php
require_once '../../config/config.php';
require_once '../../config/database.php';

// session_start(); // Ya iniciado en config.php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

$db = getDB();
$error = '';
$success = '';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: events.php');
    exit();
}

$event_id = intval($_GET['id']);

// Obtener datos del evento
$stmt = $db->prepare("SELECT * FROM eventos WHERE id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: events.php');
    exit();
}

$evento = $result->fetch_assoc();
$stmt->close();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    $nombre = trim($_POST['nombre'] ?? '');
    $tipo_evento = $_POST['tipo_evento'] ?? 'carrera';
    $descripcion = trim($_POST['descripcion'] ?? '');
    $fecha_evento = $_POST['fecha_evento'] ?? '';
    $hora_evento = $_POST['hora_evento'] ?? '';
    $fecha_limite = $_POST['fecha_limite'] ?? '';
    $hora_limite = $_POST['hora_limite'] ?? '';
    $ubicacion = trim($_POST['ubicacion'] ?? '');
    $latitud = !empty($_POST['latitud']) ? floatval($_POST['latitud']) : NULL;
    $longitud = !empty($_POST['longitud']) ? floatval($_POST['longitud']) : NULL;
    $latitud_partida = !empty($_POST['latitud_partida']) ? floatval($_POST['latitud_partida']) : $latitud;
    $longitud_partida = !empty($_POST['longitud_partida']) ? floatval($_POST['longitud_partida']) : $longitud;
    $radio_geocerca = !empty($_POST['radio_geocerca']) ? intval($_POST['radio_geocerca']) : 30;
    $total_vueltas = !empty($_POST['total_vueltas']) ? intval($_POST['total_vueltas']) : 1;
    $distancia = floatval($_POST['distancia'] ?? 0);
    $precio = floatval($_POST['precio'] ?? 0);
    $cupo_maximo = intval($_POST['cupo_maximo'] ?? 0);
    $incluye_camiseta = isset($_POST['incluye_camiseta']) ? 1 : 0;
    $estado = $_POST['estado'] ?? 'activo';
    
    // Procesar modalidades
    $modalidades_nombres = $_POST['modalidades_nombres'] ?? [];
    $modalidades_precios = $_POST['modalidades_precios'] ?? [];
    
    $modalidades = [];
    $precio_minimo = null;
    
    for ($i = 0; $i < count($modalidades_nombres); $i++) {
        $nombre_modalidad = trim($modalidades_nombres[$i]);
        if (!empty($nombre_modalidad)) {
            $precio_mod = floatval($modalidades_precios[$i] ?? 0);
            $modalidades[] = [
                'nombre' => $nombre_modalidad,
                'precio' => $precio_mod
            ];
            
            if ($precio_minimo === null || $precio_mod < $precio_minimo) {
                $precio_minimo = $precio_mod;
            }
        }
    }
    
    $modalidades_json = !empty($modalidades) ? json_encode($modalidades) : NULL;
    
    // Si hay modalidades, el precio base es el mínimo de las modalidades
    if (!empty($modalidades)) {
        $precio = $precio_minimo;
    }
    
    // Procesar métodos de pago
    $metodos_seleccionados = isset($_POST['metodos_pago']) ? $_POST['metodos_pago'] : [];
    $metodo_pago_str = !empty($metodos_seleccionados) ? implode(',', $metodos_seleccionados) : '';

    $organizador_id = intval($_POST['organizador_id'] ?? 0);
    
    // Validaciones
    if (empty($nombre) || empty($fecha_evento) || empty($ubicacion) || $cupo_maximo <= 0) {
        $response['message'] = 'Por favor completa todos los campos obligatorios.';
    } elseif ($precio > 0 && empty($metodos_seleccionados)) {
        $response['message'] = 'Si el evento tiene costo, debes seleccionar al menos un método de pago.';
    } else {
        if ($precio <= 0 && empty($metodos_seleccionados)) {
            $metodo_pago_str = 'gratuito'; 
        }
        
        // Manejo de imagen
        $imagen_url = $evento['imagen_url']; // Mantener imagen anterior por defecto
        
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../uploads/events/'; // Use absolute path
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $new_filename = uniqid('event_') . '.' . $file_extension;
                $target_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['imagen']['tmp_name'], $target_path)) {
                    $imagen_url = 'uploads/events/' . $new_filename;
                } else {
                    $response['message'] = 'Error al subir la imagen.';
                }
            } else {
                $response['message'] = 'Formato de imagen no válido. Solo JPG, PNG y WebP.';
            }
        }
        
        if (empty($response['message'])) {
            // Combinar fecha y hora
            $fecha_completa = $fecha_evento . ' ' . $hora_evento . ':00';
            $fecha_limite_completa = !empty($fecha_limite) ? ($fecha_limite . ' ' . ($hora_limite ?: '23:59') . ':00') : NULL;
            
            if ($organizador_id === 0) {
                $organizador_id = $_SESSION['user_id'];
            }
            
            // Actualizar en base de datos
            $query = "UPDATE eventos SET 
                      nombre = ?, tipo_evento = ?, descripcion = ?, fecha_evento = ?, fecha_limite_inscripcion = ?, 
                      ubicacion = ?, latitud = ?, longitud = ?, latitud_partida = ?, longitud_partida = ?, radio_geocerca = ?, total_vueltas = ?, distancia = ?, precio = ?, cupo_maximo = ?, 
                      imagen_url = ?, id_organizador = ?, estado = ?, 
                      incluye_camiseta = ?, metodo_pago = ?, modalidades = ? 
                      WHERE id = ?";
            
            $stmt = $db->prepare($query);
            $stmt->bind_param("ssssssddddiiddsisisssi", 
                $nombre, $tipo_evento, $descripcion, $fecha_completa, $fecha_limite_completa, 
                $ubicacion, $latitud, $longitud, $latitud_partida, $longitud_partida, $radio_geocerca, $total_vueltas, $distancia, $precio, $cupo_maximo, 
                $imagen_url, $organizador_id, $estado, 
                $incluye_camiseta, $metodo_pago_str, $modalidades_json, $event_id
            );
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Evento actualizado exitosamente.';
                $response['redirect'] = 'events.php';
            } else {
                $response['message'] = 'Error al actualizar el evento: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
    
    ob_clean();
    echo json_encode($response);
    exit;
}

// Obtener lista de organizadores
$organizadores = [];
$result = $db->query("SELECT id, nombre, apellido FROM usuarios WHERE tipo_usuario = 'admin' OR tipo_usuario = 'organizador' ORDER BY nombre");
if ($result) {
    $organizadores = $result->fetch_all(MYSQLI_ASSOC);
}

// Separar fecha y hora para el formulario
$fecha_evento_val = date('Y-m-d', strtotime($evento['fecha_evento']));
$hora_evento_val = date('H:i', strtotime($evento['fecha_evento']));
$fecha_limite_val = !empty($evento['fecha_limite_inscripcion']) ? date('Y-m-d', strtotime($evento['fecha_limite_inscripcion'])) : '';
$hora_limite_val = !empty($evento['fecha_limite_inscripcion']) ? date('H:i', strtotime($evento['fecha_limite_inscripcion'])) : '';
$metodos_pago_val = explode(',', $evento['metodo_pago']);
$modalidades_val = !empty($evento['modalidades']) ? json_decode($evento['modalidades'], true) : [];

include '../../includes/admin-header.php';
?>
<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

<div class="admin-main">
    <div class="admin-header">
        <h1><i class="fas fa-edit"></i> Editar Evento</h1>
        <div class="admin-header-actions">
            <a href="events.php" class="admin-btn admin-btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="admin-alert admin-alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
    <div class="admin-alert admin-alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
    </div>
    <?php endif; ?>

    <div class="admin-card">
        <form method="POST" action="" enctype="multipart/form-data" class="admin-form">
            <div class="form-section">
                <h3>Información General</h3>
                <div class="row">
                    <div class="form-group col-md-8" style="margin-bottom: 1.5rem;">
                        <label for="nombre">Nombre del Evento *</label>
                        <input type="text" id="nombre" name="nombre" class="form-control" required
                               value="<?php echo htmlspecialchars($evento['nombre']); ?>">
                    </div>
                    
                    <div class="form-group col-md-4" style="margin-bottom: 1.5rem;">
                        <label for="tipo_evento">Tipo de Evento *</label>
                        <select id="tipo_evento" name="tipo_evento" class="form-control" onchange="toggleEventFields()">
                            <option value="carrera" <?php echo $evento['tipo_evento'] == 'carrera' ? 'selected' : ''; ?>>Carrera</option>
                            <option value="zumba" <?php echo $evento['tipo_evento'] == 'zumba' ? 'selected' : ''; ?>>Zumba</option>
                            <option value="caminata" <?php echo $evento['tipo_evento'] == 'caminata' ? 'selected' : ''; ?>>Caminata</option>
                            <option value="charla" <?php echo $evento['tipo_evento'] == 'charla' ? 'selected' : ''; ?>>Charla</option>
                            <option value="curso" <?php echo $evento['tipo_evento'] == 'curso' ? 'selected' : ''; ?>>Curso</option>
                            <option value="taller" <?php echo $evento['tipo_evento'] == 'taller' ? 'selected' : ''; ?>>Taller</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                     <div class="form-group col-md-8" style="margin-bottom: 1.5rem;">
                        <label for="organizador_id">Organizador</label>
                        <select id="organizador_id" name="organizador_id" class="form-control">
                            <option value="0">Yo (Administrador)</option>
                            <?php foreach ($organizadores as $org): ?>
                            <option value="<?php echo $org['id']; ?>" <?php echo $evento['id_organizador'] == $org['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($org['nombre'] . ' ' . $org['apellido']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group col-md-4" style="margin-bottom: 1.5rem;">
                        <label for="estado">Estado</label>
                        <select id="estado" name="estado" class="form-control">
                            <option value="activo" <?php echo $evento['estado'] == 'activo' ? 'selected' : ''; ?>>Activo</option>
                            <option value="pendiente" <?php echo $evento['estado'] == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="cancelado" <?php echo $evento['estado'] == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                            <option value="completado" <?php echo $evento['estado'] == 'completado' ? 'selected' : ''; ?>>Completado</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" name="descripcion" class="form-control" rows="4"><?php echo htmlspecialchars($evento['descripcion']); ?></textarea>
                </div>
            </div>

            <div class="form-section">
                <h3>Detalles del Evento</h3>
                <div class="row">
                    <div class="form-group col-md-6" style="margin-bottom: 1.5rem;">
                        <label for="fecha_evento">Fecha *</label>
                        <input type="date" id="fecha_evento" name="fecha_evento" class="form-control" required
                               value="<?php echo $fecha_evento_val; ?>">
                    </div>
                    
                    <div class="form-group col-md-6" style="margin-bottom: 1.5rem;">
                        <label for="hora_evento">Hora *</label>
                        <input type="time" id="hora_evento" name="hora_evento" class="form-control" required
                               value="<?php echo $hora_evento_val; ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="form-group col-md-6" style="margin-bottom: 1.5rem;">
                        <label for="fecha_limite">Fecha Límite Inscripción (Opcional)</label>
                        <input type="date" id="fecha_limite" name="fecha_limite" class="form-control"
                               value="<?php echo $fecha_limite_val; ?>">
                        <small class="form-text text-muted">Evita que se registren después de este día.</small>
                    </div>
                    
                    <div class="form-group col-md-6" style="margin-bottom: 1.5rem;">
                        <label for="hora_limite">Hora Límite Inscripción (Opcional)</label>
                        <input type="time" id="hora_limite" name="hora_limite" class="form-control"
                               value="<?php echo $hora_limite_val; ?>">
                        <small class="form-text text-muted">Hora del día límite.</small>
                    </div>
                </div>
                
                <div class="row">
                    <div class="form-group col-md-8">
                        <label for="ubicacion">Ubicación *</label>
                        <input type="text" id="ubicacion" name="ubicacion" class="form-control" required
                               value="<?php echo htmlspecialchars($evento['ubicacion']); ?>">

                        <!-- Map & GPS Tracking Container -->
                        <div id="map-container" style="margin-top: 1.2rem; background: #fdfafc; border: 1px solid #e0d0f0; border-radius: 8px; padding: 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.8rem; flex-wrap: wrap; gap: 0.5rem;">
                                <label style="font-weight: 700; color: #2a004a; margin: 0;">
                                    <i class="fas fa-satellite-dish" style="color: #6a0dad;"></i> Telemetría y Coordenadas GPS (App Móvil)
                                </label>
                                <div class="custom-control custom-checkbox" style="display: flex; align-items: center; gap: 6px;">
                                    <input type="checkbox" id="mismo_punto_circuito" checked onchange="toggleMismoPunto(this.checked)">
                                    <label for="mismo_punto_circuito" style="font-size: 0.85rem; font-weight: 600; color: #6a0dad; margin: 0; cursor: pointer;">
                                        Circuito (Salida y Meta en el mismo punto)
                                    </label>
                                </div>
                            </div>

                            <div class="row" style="margin-bottom: 0.8rem;">
                                <!-- Start Point -->
                                <div class="col-md-6" id="grupo_salida" style="margin-bottom: 0.5rem;">
                                    <div style="background: #e8f5e9; border: 1px solid #c8e6c9; border-radius: 6px; padding: 0.6rem;">
                                        <div style="font-size: 0.82rem; font-weight: 700; color: #2e7d32; margin-bottom: 0.3rem;">
                                            🟢 Punto de Partida / Salida
                                        </div>
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px;">
                                            <div>
                                                <small style="color: #666; font-size: 0.72rem;">Latitud:</small>
                                                <input type="number" step="any" id="latitud_partida" name="latitud_partida" class="form-control form-control-sm" placeholder="18.4861" value="<?php echo isset($evento['latitud_partida']) ? $evento['latitud_partida'] : ($evento['latitud'] ?? ''); ?>" oninput="syncManualInputs()">
                                            </div>
                                            <div>
                                                <small style="color: #666; font-size: 0.72rem;">Longitud:</small>
                                                <input type="number" step="any" id="longitud_partida" name="longitud_partida" class="form-control form-control-sm" placeholder="-69.9312" value="<?php echo isset($evento['longitud_partida']) ? $evento['longitud_partida'] : ($evento['longitud'] ?? ''); ?>" oninput="syncManualInputs()">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Finish Point -->
                                <div class="col-md-6" id="grupo_meta" style="margin-bottom: 0.5rem;">
                                    <div style="background: #fce4ec; border: 1px solid #f8bbd0; border-radius: 6px; padding: 0.6rem;">
                                        <div style="font-size: 0.82rem; font-weight: 700; color: #c2185b; margin-bottom: 0.3rem;">
                                            🏁 Punto de Meta / Llegada
                                        </div>
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px;">
                                            <div>
                                                <small style="color: #666; font-size: 0.72rem;">Latitud:</small>
                                                <input type="number" step="any" id="latitud" name="latitud" class="form-control form-control-sm" placeholder="18.4861" value="<?php echo isset($evento['latitud']) ? $evento['latitud'] : ''; ?>" oninput="syncManualInputs()">
                                            </div>
                                            <div>
                                                <small style="color: #666; font-size: 0.72rem;">Longitud:</small>
                                                <input type="number" step="any" id="longitud" name="longitud" class="form-control form-control-sm" placeholder="-69.9312" value="<?php echo isset($evento['longitud']) ? $evento['longitud'] : ''; ?>" oninput="syncManualInputs()">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Geofence & Laps params -->
                            <div class="row" style="margin-bottom: 0.8rem;">
                                <div class="col-md-6">
                                    <label for="radio_geocerca" style="font-size: 0.82rem; font-weight: 600; color: #444; margin-bottom: 0.2rem;">
                                        <i class="fas fa-bullseye" style="color: #e91e63;"></i> Radio de Geocerca (Metros de tolerancia)
                                    </label>
                                    <input type="number" id="radio_geocerca" name="radio_geocerca" class="form-control form-control-sm" min="10" max="500" value="<?php echo isset($evento['radio_geocerca']) ? $evento['radio_geocerca'] : '30'; ?>" oninput="updateGeofenceCircle(this.value)">
                                    <small style="color: #777; font-size: 0.72rem;">Distancia alrededor de la meta donde la app registra el cruce (Recomendado: 25 - 40m).</small>
                                </div>
                                <div class="col-md-6">
                                    <label for="total_vueltas" style="font-size: 0.82rem; font-weight: 600; color: #444; margin-bottom: 0.2rem;">
                                        <i class="fas fa-sync-alt" style="color: #00bcd4;"></i> Total de Vueltas al Circuito
                                    </label>
                                    <input type="number" id="total_vueltas" name="total_vueltas" class="form-control form-control-sm" min="1" max="100" value="<?php echo isset($evento['total_vueltas']) ? $evento['total_vueltas'] : '1'; ?>">
                                    <small style="color: #777; font-size: 0.72rem;">Número de cruces requeridos para completar la carrera.</small>
                                </div>
                            </div>

                            <!-- Map Canvas -->
                            <div id="map" style="height: 320px; width: 100%; border-radius: 6px; border: 1px solid #ced4da;"></div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.4rem; font-size: 0.75rem; color: #666;">
                                <span><i class="fas fa-info-circle"></i> Arrastra los marcadores en el mapa para ajustar la posición exacta.</span>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="getCurrentLocationForMap()" style="padding: 2px 8px; font-size: 0.75rem;">
                                    <i class="fas fa-crosshairs"></i> Mi Ubicación
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group col-md-4" id="distancia-group">
                        <label>Modalidades y Precios</label>
                        <div id="modalidades-container">
                            <?php 
                            if (!empty($modalidades_val)): 
                                foreach ($modalidades_val as $mod): 
                                    $mName = is_array($mod) ? $mod['nombre'] : $mod;
                                    $mPrice = is_array($mod) ? $mod['precio'] : 0;
                            ?>
                            <div class="input-group mb-2" style="display: flex; gap: 5px;">
                                <input type="text" name="modalidades_nombres[]" class="form-control" placeholder="Nombre (Ej: General)" value="<?php echo htmlspecialchars($mName); ?>" oninput="checkModalities()">
                                <input type="number" name="modalidades_precios[]" class="form-control" placeholder="Precio" step="0.01" min="0" style="max-width: 100px;" value="<?php echo htmlspecialchars($mPrice); ?>">
                                <button type="button" class="btn btn-danger" onclick="this.parentElement.remove(); checkModalities();" style="padding: 0 10px;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <?php 
                                endforeach;
                            else:
                            ?>
                            <div class="input-group mb-2" style="display: flex; gap: 5px;">
                                <input type="text" name="modalidades_nombres[]" class="form-control" placeholder="Nombre (Ej: General)" oninput="checkModalities()">
                                <input type="number" name="modalidades_precios[]" class="form-control" placeholder="Precio" step="0.01" min="0" style="max-width: 100px;">
                            </div>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary mt-1" onclick="addModalidad()">
                            <i class="fas fa-plus"></i> Agregar Modalidad
                        </button>
                        
                        <div style="margin-top: 15px;">
                            <label for="distancia">Distancia Principal (km)</label>
                            <input type="number" id="distancia" name="distancia" class="form-control" step="0.1" min="0"
                                   value="<?php echo $evento['distancia']; ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Inscripción e Imagen</h3>
                 <div class="row">
                    <div class="form-group col-md-6" id="precio-base-group">
                        <label for="precio">Precio Base / Único ($)</label>
                        <input type="number" id="precio" name="precio" class="form-control" step="0.01" min="0"
                               value="<?php echo $evento['precio']; ?>">
                        <small class="text-muted">Si no hay modalidades, este será el precio único.</small>
                    </div>

                    <div class="form-group col-md-6" id="precio-modalidades-info" style="display: none;">
                        <label>Precio</label>
                        <div class="alert alert-info" style="margin-bottom: 0; padding: 0.5rem 1rem;">
                            <i class="fas fa-info-circle"></i> El precio será determinado por cada modalidad.
                        </div>
                    </div>
                    
                    <div class="form-group col-md-6">
                        <label for="cupo_maximo">Cupo Máximo *</label>
                        <input type="number" id="cupo_maximo" name="cupo_maximo" class="form-control" required min="1"
                               value="<?php echo $evento['cupo_maximo']; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="imagen">Imagen del Evento</label>
                    <div class="image-upload-wrapper">
                         <?php if ($evento['imagen_url']): ?>
                        <div style="margin-bottom: 10px;">
                            <img src="../../<?php echo htmlspecialchars($evento['imagen_url']); ?>" alt="Actual" style="height: 100px; border-radius: 4px;">
                        </div>
                        <?php endif; ?>
                        <input type="file" id="imagen" name="imagen" class="form-control" accept="image/*" onchange="previewImage(this)">
                        <div id="image-preview" class="mt-2" style="display: none;">
                            <img src="" alt="Vista previa" style="max-height: 200px; border-radius: var(--border-radius);">
                        </div>
                    </div>
                    <small class="form-text text-muted">Formatos permitidos: JPG, PNG, WebP. Máx 2MB. Dejar vacío para mantener la actual.</small>
                </div>
            </div>

             <div class="form-section">
                <h3>Opciones Adicionales y Pago</h3>
                <div class="row">
                    <div class="form-group col-md-6">
                        <label>Métodos de Pago Aceptados</label>
                        <div style="background: var(--bg-light); padding: 15px; border-radius: 4px;">
                            <div class="custom-control custom-checkbox mb-2" style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" class="custom-control-input" id="pago_tarjeta" name="metodos_pago[]" value="tarjeta" style="margin-top: 0;" <?php echo in_array('tarjeta', $metodos_pago_val) ? 'checked' : ''; ?>>
                                <label class="custom-control-label" for="pago_tarjeta" style="margin-bottom: 0; padding-top: 2px;">Tarjeta de Crédito/Débito</label>
                            </div>
                            <div class="custom-control custom-checkbox mb-2" style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" class="custom-control-input" id="pago_transferencia" name="metodos_pago[]" value="transferencia" style="margin-top: 0;" <?php echo in_array('transferencia', $metodos_pago_val) ? 'checked' : ''; ?>>
                                <label class="custom-control-label" for="pago_transferencia" style="margin-bottom: 0; padding-top: 2px;">Transferencia Bancaria</label>
                            </div>
                            <div class="custom-control custom-checkbox" style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" class="custom-control-input" id="pago_efectivo" name="metodos_pago[]" value="efectivo" style="margin-top: 0;" <?php echo in_array('efectivo', $metodos_pago_val) ? 'checked' : ''; ?>>
                                <label class="custom-control-label" for="pago_efectivo" style="margin-bottom: 0; padding-top: 2px;">Efectivo</label>
                            </div>
                        </div>
                        <small class="form-text text-muted">Selecciona uno o más métodos. Obligatorio si el precio es mayor a 0.</small>
                    </div>
                    
                    <div class="form-group col-md-6" style="display: flex; align-items: center;" id="camiseta-group">
                        <div class="custom-control custom-checkbox" style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" class="custom-control-input" id="incluye_camiseta" name="incluye_camiseta" value="1" style="margin-top: 0;" <?php echo $evento['incluye_camiseta'] ? 'checked' : ''; ?>>
                            <label class="custom-control-label" for="incluye_camiseta" style="margin-bottom: 0; padding-top: 2px;">Incluye Camiseta</label>
                        </div>
                   </div>
                </div>

                <div class="admin-alert admin-alert-info" id="pago-info-alert" style="display: none; margin-top: 15px;">
                    <i class="fas fa-info-circle"></i> Has seleccionado un método de pago. Asegúrate de que el precio sea mayor a 0.
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="admin-btn admin-btn-primary" style="padding: 1rem 2rem; font-size: 1.1rem;">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        
        reader.onload = function(e) {
            const preview = document.getElementById('image-preview');
            const img = preview.querySelector('img');
            img.src = e.target.result;
            preview.style.display = 'block';
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

function toggleEventFields() {
    // Mostrar siempre el grupo de modalidades/distancia
    document.getElementById('distancia-group').style.display = 'block';
    checkModalities();
}

function checkModalities() {
    const container = document.getElementById('modalidades-container');
    const inputs = container.querySelectorAll('input[name="modalidades_nombres[]"]');
    const precioGroup = document.getElementById('precio-base-group');
    const precioInfo = document.getElementById('precio-modalidades-info');
    
    let hasModalities = false;
    
    // Verificar si hay inputs con valor
    inputs.forEach(input => {
        if (input.value.trim() !== '') hasModalities = true;
    });
    
    // Lógica para mostrar/ocultar precio base
    if (hasModalities) {
         precioGroup.style.display = 'none';
         precioInfo.style.display = 'block';
         // No borramos el valor aquí para no perderlo accidentalmente al editar,
         // el usuario sabrá que se usa el de modalidades
    } else {
         precioGroup.style.display = 'block';
         precioInfo.style.display = 'none';
    }
}

function addModalidad() {
    const container = document.getElementById('modalidades-container');
    const div = document.createElement('div');
    div.className = 'input-group mb-2';
    div.style.display = 'flex';
    div.style.gap = '5px';
    
    div.innerHTML = `
        <input type="text" name="modalidades_nombres[]" class="form-control" placeholder="Nombre (Ej: General)" oninput="checkModalities()">
        <input type="number" name="modalidades_precios[]" class="form-control" placeholder="Precio" step="0.01" min="0" style="max-width: 100px;">
        <button type="button" class="btn btn-danger" onclick="this.parentElement.remove(); checkModalities();" style="padding: 0 10px;">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(div);
    checkModalities();
}

document.addEventListener('DOMContentLoaded', function() {
    toggleEventFields();
    initMap();
});

let map, startMarker, finishMarker, geofenceCircle;

// Inicializar Mapa con Soporte para Salida, Meta y Geocerca
function initMap() {
    let latFinish = parseFloat(document.getElementById('latitud').value) || 18.4861;
    let lngFinish = parseFloat(document.getElementById('longitud').value) || -69.9312;
    let latStart = parseFloat(document.getElementById('latitud_partida').value) || latFinish;
    let lngStart = parseFloat(document.getElementById('longitud_partida').value) || lngFinish;
    let radius = parseInt(document.getElementById('radio_geocerca').value, 10) || 30;

    map = L.map('map').setView([latFinish, lngFinish], 15);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);

    // Iconos personalizados con HTML/SVG
    const startIcon = L.divIcon({
        className: 'custom-map-icon',
        html: '<div style="background:#2e7d32; color:white; width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 6px rgba(0,0,0,0.4); border:2px solid white; font-weight:bold; font-size:14px;"><i class="fas fa-play" style="font-size:10px;"></i></div>',
        iconSize: [30, 30],
        iconAnchor: [15, 15]
    });

    const finishIcon = L.divIcon({
        className: 'custom-map-icon',
        html: '<div style="background:#c2185b; color:white; width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 6px rgba(0,0,0,0.4); border:2px solid white; font-weight:bold; font-size:14px;"><i class="fas fa-flag-checkered"></i></div>',
        iconSize: [30, 30],
        iconAnchor: [15, 15]
    });

    // Marcador de Partida
    startMarker = L.marker([latStart, lngStart], {
        draggable: true,
        icon: startIcon
    }).addTo(map).bindPopup('<b>🟢 Punto de Partida / Salida</b>');

    // Marcador de Meta
    finishMarker = L.marker([latFinish, lngFinish], {
        draggable: true,
        icon: finishIcon
    }).addTo(map).bindPopup('<b>🏁 Punto de Meta / Llegada</b>');

    // Círculo de Geocerca en la Meta
    geofenceCircle = L.circle([latFinish, lngFinish], {
        color: '#c2185b',
        fillColor: '#f8bbd0',
        fillOpacity: 0.35,
        radius: radius
    }).addTo(map);

    // Eventos de arrastre
    startMarker.on('drag', function() {
        const pos = startMarker.getLatLng();
        document.getElementById('latitud_partida').value = pos.lat.toFixed(7);
        document.getElementById('longitud_partida').value = pos.lng.toFixed(7);
        
        const isSame = document.getElementById('mismo_punto_circuito').checked;
        if (isSame) {
            finishMarker.setLatLng(pos);
            geofenceCircle.setLatLng(pos);
            document.getElementById('latitud').value = pos.lat.toFixed(7);
            document.getElementById('longitud').value = pos.lng.toFixed(7);
        }
    });

    finishMarker.on('drag', function() {
        const pos = finishMarker.getLatLng();
        document.getElementById('latitud').value = pos.lat.toFixed(7);
        document.getElementById('longitud').value = pos.lng.toFixed(7);
        geofenceCircle.setLatLng(pos);

        const isSame = document.getElementById('mismo_punto_circuito').checked;
        if (isSame) {
            startMarker.setLatLng(pos);
            document.getElementById('latitud_partida').value = pos.lat.toFixed(7);
            document.getElementById('longitud_partida').value = pos.lng.toFixed(7);
        }
    });

    // Si los puntos guardados son distintos, desmarcar el checkbox
    if (Math.abs(latStart - latFinish) > 0.0001 || Math.abs(lngStart - lngFinish) > 0.0001) {
        document.getElementById('mismo_punto_circuito').checked = false;
    }
}

function updateGeofenceCircle(val) {
    const radius = parseInt(val, 10) || 30;
    if (geofenceCircle) {
        geofenceCircle.setRadius(radius);
    }
}

function toggleMismoPunto(checked) {
    if (checked && startMarker && finishMarker) {
        const pos = finishMarker.getLatLng();
        startMarker.setLatLng(pos);
        document.getElementById('latitud_partida').value = pos.lat.toFixed(7);
        document.getElementById('longitud_partida').value = pos.lng.toFixed(7);
    }
}

function syncManualInputs() {
    const latF = parseFloat(document.getElementById('latitud').value);
    const lngF = parseFloat(document.getElementById('longitud').value);
    const latS = parseFloat(document.getElementById('latitud_partida').value);
    const lngS = parseFloat(document.getElementById('longitud_partida').value);

    if (!isNaN(latF) && !isNaN(lngF) && finishMarker) {
        finishMarker.setLatLng([latF, lngF]);
        geofenceCircle.setLatLng([latF, lngF]);
    }
    if (!isNaN(latS) && !isNaN(lngS) && startMarker) {
        startMarker.setLatLng([latS, lngS]);
    }
}

function getCurrentLocationForMap() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(pos) {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            
            document.getElementById('latitud').value = lat.toFixed(7);
            document.getElementById('longitud').value = lng.toFixed(7);
            document.getElementById('latitud_partida').value = lat.toFixed(7);
            document.getElementById('longitud_partida').value = lng.toFixed(7);
            
            finishMarker.setLatLng([lat, lng]);
            startMarker.setLatLng([lat, lng]);
            geofenceCircle.setLatLng([lat, lng]);
            map.setView([lat, lng], 16);
        }, function(err) {
            alert('No se pudo obtener la ubicación actual: ' + err.message);
        });
    }
}
</script>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<?php include '../../includes/admin-footer.php'; ?>
