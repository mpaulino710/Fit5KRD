<?php
// modules/admin/migrate_events_coordinates.php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    die('Acceso denegado. Solo administradores pueden ejecutar migraciones.');
}

$db = getDB();

echo "<h1>Migración de Base de Datos: Coordenadas de Eventos</h1>";

// Check if columns exist
$result = $db->query("SHOW COLUMNS FROM eventos LIKE 'latitud'");
if ($result->num_rows == 0) {
    // Add latitud column
    $sql = "ALTER TABLE eventos ADD COLUMN latitud DECIMAL(10, 8) DEFAULT NULL AFTER ubicacion";
    if ($db->query($sql)) {
        echo "<p style='color: green;'>Columna 'latitud' agregada exitosamente.</p>";
    } else {
        echo "<p style='color: red;'>Error al agregar columna 'latitud': " . $db->error . "</p>";
    }
} else {
    echo "<p style='color: orange;'>La columna 'latitud' ya existe.</p>";
}

$result = $db->query("SHOW COLUMNS FROM eventos LIKE 'longitud'");
if ($result->num_rows == 0) {
    // Add longitud column
    $sql = "ALTER TABLE eventos ADD COLUMN longitud DECIMAL(11, 8) DEFAULT NULL AFTER latitud";
    if ($db->query($sql)) {
        echo "<p style='color: green;'>Columna 'longitud' agregada exitosamente.</p>";
    } else {
        echo "<p style='color: red;'>Error al agregar columna 'longitud': " . $db->error . "</p>";
    }
} else {
    echo "<p style='color: orange;'>La columna 'longitud' ya existe.</p>";
}

echo "<p>Migración completada. <a href='events.php'>Volver a Eventos</a></p>";
?>
