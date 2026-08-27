<?php
// modules/admin/edit-inscription.php
require_once '../../config/config.php';
require_once '../../config/database.php';


// session_start(); removed as it's handled in config.php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: inscriptions.php');
    exit();
}

$db = getDB();
$inscripcion_id = intval($_GET['id']);

// Obtener información de la inscripción
$query = "SELECT
            i.*,
            u.nombre as usuario_nombre,
            u.apellido as usuario_apellido,
            e.nombre as evento_nombre,
            e.fecha_evento
          FROM inscripciones i
          JOIN usuarios u ON i.id_usuario = u.id
          JOIN eventos e ON i.id_evento = e.id
          WHERE i.id = ?";

$stmt = $db->prepare($query);
$stmt->bind_param("i", $inscripcion_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: inscriptions.php');
    exit();
}

$inscripcion = $result->fetch_assoc();
$stmt->close();

// Procesar actualización
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $estado = $_POST['estado'] ?? $inscripcion['estado'];
    $categoria = $_POST['categoria'] ?? $inscripcion['categoria'];
    $talla_camiseta = $_POST['talla_camiseta'] ?? $inscripcion['talla_camiseta'];
    $notas_medicas = $_POST['notas_medicas'] ?? $inscripcion['notas_medicas'];
    $contacto_emergencia = $_POST['contacto_emergencia'] ?? $inscripcion['contacto_emergencia'];
    $telefono_emergencia = $_POST['telefono_emergencia'] ?? $inscripcion['telefono_emergencia'];
    $tiempo_final = $_POST['tiempo_final'] ?? $inscripcion['tiempo_final'];

    // Validar tiempo si se proporciona
    if ($tiempo_final && !preg_match('/^\d{2}:\d{2}:\d{2}$/', $tiempo_final)) {
        $error = 'Formato de tiempo inválido. Use HH:MM:SS';
    } else {
        $stmt = $db->prepare("UPDATE inscripciones SET
            estado = ?,
            categoria = ?,
            talla_camiseta = ?,
            notas_medicas = ?,
            contacto_emergencia = ?,
            telefono_emergencia = ?,
            tiempo_final = ?
            WHERE id = ?");

        $stmt->bind_param("sssssssi",
            $estado,
            $categoria,
            $talla_camiseta,
            $notas_medicas,
            $contacto_emergencia,
            $telefono_emergencia,
            $tiempo_final,
            $inscripcion_id
        );

        if ($stmt->execute()) {
            $success = 'Inscripción actualizada correctamente';

            // Registrar en logs
            $log_desc = "Actualizó inscripción #{$inscripcion_id}";
            $db->query("INSERT INTO logs_sistema (id_usuario, accion, modulo, descripcion)
                       VALUES ({$_SESSION['user_id']}, 'actualizar_inscripcion', 'inscripciones', '$log_desc')");

            // Actualizar datos locales
            $inscripcion['estado'] = $estado;
            $inscripcion['categoria'] = $categoria;
            $inscripcion['talla_camiseta'] = $talla_camiseta;
            $inscripcion['notas_medicas'] = $notas_medicas;
            $inscripcion['contacto_emergencia'] = $contacto_emergencia;
            $inscripcion['telefono_emergencia'] = $telefono_emergencia;
            $inscripcion['tiempo_final'] = $tiempo_final;
        } else {
            $error = 'Error al actualizar la inscripción: ' . $stmt->error;
        }
        $stmt->close();
    }
}

include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <!-- Header -->
    <div class="admin-header">
        <div>
            <h1><i class="fas fa-edit"></i> Editar Inscripción</h1>
            <p class="inscription-subtitle">
                ID: #<?php echo $inscripcion['id']; ?> •
                Corredor: <?php echo htmlspecialchars($inscripcion['usuario_nombre'] . ' ' . $inscripcion['usuario_apellido']); ?>
            </p>
        </div>
        <div class="admin-header-actions">
            <a href="view-inscription.php?id=<?php echo $inscripcion_id; ?>" class="admin-btn admin-btn-secondary">
                <i class="fas fa-eye"></i> Ver Detalles
            </a>
            <a href="inscriptions.php" class="admin-btn admin-btn-outline">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
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

    <form method="POST" action="" class="edit-form">
        <div class="dashboard-grid">
            <!-- Información básica -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h2><i class="fas fa-info-circle"></i> Información Básica</h2>
                </div>

                <div class="form-section">
                    <div class="form-group">
                        <label for="estado"><i class="fas fa-filter"></i> Estado *</label>
                        <select id="estado" name="estado" class="form-control" required>
                            <option value="confirmada" <?php echo $inscripcion['estado'] === 'confirmada' ? 'selected' : ''; ?>>Confirmada</option>
                            <option value="pendiente" <?php echo $inscripcion['estado'] === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="cancelada" <?php echo $inscripcion['estado'] === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                            <option value="ausente" <?php echo $inscripcion['estado'] === 'ausente' ? 'selected' : ''; ?>>Ausente</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="categoria"><i class="fas fa-tag"></i> Categoría *</label>
                        <select id="categoria" name="categoria" class="form-control" required>
                            <option value="general" <?php echo $inscripcion['categoria'] === 'general' ? 'selected' : ''; ?>>General</option>
                            <option value="principiante" <?php echo $inscripcion['categoria'] === 'principiante' ? 'selected' : ''; ?>>Principiante</option>
                            <option value="intermedio" <?php echo $inscripcion['categoria'] === 'intermedio' ? 'selected' : ''; ?>>Intermedio</option>
                            <option value="avanzado" <?php echo $inscripcion['categoria'] === 'avanzado' ? 'selected' : ''; ?>>Avanzado</option>
                            <option value="elite" <?php echo $inscripcion['categoria'] === 'elite' ? 'selected' : ''; ?>>Elite</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="tiempo_final"><i class="fas fa-clock"></i> Tiempo Final (HH:MM:SS)</label>
                        <input type="text" id="tiempo_final" name="tiempo_final"
                               class="form-control" placeholder="00:00:00"
                               value="<?php echo htmlspecialchars($inscripcion['tiempo_final'] ?? ''); ?>"
                               pattern="^\d{2}:\d{2}:\d{2}$">
                        <small class="form-text">Formato: HH:MM:SS</small>
                    </div>
                </div>
            </div>

            <!-- Kit del corredor -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h2><i class="fas fa-tshirt"></i> Kit del Corredor</h2>
                </div>

                <div class="form-section">
                    <div class="form-group">
                        <label for="talla_camiseta"><i class="fas fa-tshirt"></i> Talla de Camiseta</label>
                        <select name="talla_camiseta" id="talla_camiseta" class="form-control">
                            <option value="">No aplica / No especificada</option>
                            <option value="Kid 8" <?php echo $inscripcion['talla_camiseta'] === 'Kid 8' ? 'selected' : ''; ?>>Kid 8</option>
                            <option value="Kid 10" <?php echo $inscripcion['talla_camiseta'] === 'Kid 10' ? 'selected' : ''; ?>>Kid 10</option>
                            <option value="Kid 12" <?php echo $inscripcion['talla_camiseta'] === 'Kid 12' ? 'selected' : ''; ?>>Kid 12</option>
                            <option value="Kid 14" <?php echo $inscripcion['talla_camiseta'] === 'Kid 14' ? 'selected' : ''; ?>>Kid 14</option>
                            <option value="XS" <?php echo $inscripcion['talla_camiseta'] === 'XS' ? 'selected' : ''; ?>>XS</option>
                            <option value="S" <?php echo $inscripcion['talla_camiseta'] === 'S' ? 'selected' : ''; ?>>S</option>
                            <option value="M" <?php echo $inscripcion['talla_camiseta'] === 'M' ? 'selected' : ''; ?>>M</option>
                            <option value="L" <?php echo $inscripcion['talla_camiseta'] === 'L' ? 'selected' : ''; ?>>L</option>
                            <option value="XL" <?php echo $inscripcion['talla_camiseta'] === 'XL' ? 'selected' : ''; ?>>XL</option>
                            <option value="XXL" <?php echo $inscripcion['talla_camiseta'] === 'XXL' ? 'selected' : ''; ?>>XXL</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información de seguridad -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h2><i class="fas fa-shield-alt"></i> Información de Seguridad</h2>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="contacto_emergencia"><i class="fas fa-user-md"></i> Contacto de Emergencia</label>
                    <input type="text" id="contacto_emergencia" name="contacto_emergencia"
                           class="form-control" placeholder="Nombre completo"
                           value="<?php echo htmlspecialchars($inscripcion['contacto_emergencia'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="telefono_emergencia"><i class="fas fa-phone-alt"></i> Teléfono de Emergencia</label>
                    <input type="tel" id="telefono_emergencia" name="telefono_emergencia"
                           class="form-control" placeholder="+1234567890"
                           value="<?php echo htmlspecialchars($inscripcion['telefono_emergencia'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <!-- Notas médicas -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h2><i class="fas fa-heartbeat"></i> Notas Médicas</h2>
            </div>

            <div class="form-group">
                <label for="notas_medicas"><i class="fas fa-stethoscope"></i> Información Médica Relevante</label>
                <textarea id="notas_medicas" name="notas_medicas" class="form-control"
                          rows="4" placeholder="Alergias, condiciones médicas, medicamentos, etc."><?php echo htmlspecialchars($inscripcion['notas_medicas'] ?? ''); ?></textarea>
                <small class="form-text">Esta información es confidencial y solo para uso médico en caso de emergencia.</small>
            </div>
        </div>

        <!-- Resumen -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h2><i class="fas fa-file-alt"></i> Resumen</h2>
            </div>

            <div class="summary-info">
                <div class="summary-item">
                    <span class="summary-label">Corredor:</span>
                    <span class="summary-value"><?php echo htmlspecialchars($inscripcion['usuario_nombre'] . ' ' . $inscripcion['usuario_apellido']); ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Evento:</span>
                    <span class="summary-value"><?php echo htmlspecialchars($inscripcion['evento_nombre']); ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Fecha Evento:</span>
                    <span class="summary-value"><?php echo date('d/m/Y H:i', strtotime($inscripcion['fecha_evento'])); ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Número Corredor:</span>
                    <span class="summary-value"><?php echo $inscripcion['numero_corredor'] ?: 'Pendiente'; ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Fecha Inscripción:</span>
                    <span class="summary-value"><?php echo date('d/m/Y H:i', strtotime($inscripcion['fecha_inscripcion'])); ?></span>
                </div>
            </div>
        </div>

        <!-- Acciones -->
        <div class="form-actions">
            <button type="submit" class="admin-btn admin-btn-primary">
                <i class="fas fa-save"></i> Guardar Cambios
            </button>
            <a href="view-inscription.php?id=<?php echo $inscripcion_id; ?>" class="admin-btn admin-btn-secondary">
                <i class="fas fa-times"></i> Cancelar
            </a>
            <a href="inscriptions.php" class="admin-btn admin-btn-outline">
                <i class="fas fa-arrow-left"></i> Volver a Inscripciones
            </a>
        </div>
    </form>
</div>

<script>
// Validación del formulario
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.edit-form');
    const tiempoInput = document.getElementById('tiempo_final');

    form.addEventListener('submit', function(e) {
        const tiempo = tiempoInput.value.trim();

        if (tiempo && !/^\d{2}:\d{2}:\d{2}$/.test(tiempo)) {
            e.preventDefault();
            alert('Formato de tiempo inválido. Use HH:MM:SS');
            tiempoInput.focus();
            return false;
        }

        return true;
    });

    // Máscara para tiempo
    tiempoInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');

        if (value.length > 6) {
            value = value.substr(0, 6);
        }

        if (value.length >= 2) {
            value = value.substr(0, 2) + ':' + value.substr(2);
        }
        if (value.length >= 5) {
            value = value.substr(0, 5) + ':' + value.substr(5);
        }

        e.target.value = value;
    });
});
</script>

<?php include '../../includes/admin-footer.php'; ?>
