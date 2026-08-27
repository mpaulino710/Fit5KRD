<?php
// modules/events/generate-certificate.php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Verificar sesión de usuario
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

if (!isset($_GET['id'])) {
    die("ID de inscripción no especificado.");
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? 'user';
$inscripcion_id = intval($_GET['id']);

// Obtener datos de la inscripción, usuario y evento
$query = "SELECT
            i.*,
            u.nombre as usuario_nombre,
            u.apellido as usuario_apellido,
            u.email as usuario_email,
            e.nombre as evento_nombre,
            e.fecha_evento,
            e.ubicacion as evento_ubicacion,
            e.distancia as evento_distancia
          FROM inscripciones i
          LEFT JOIN usuarios u ON i.id_usuario = u.id
          JOIN eventos e ON i.id_evento = e.id
          WHERE i.id = ?";

$stmt = $db->prepare($query);
$stmt->bind_param("i", $inscripcion_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Inscripción no encontrada.");
}

$inscripcion = $result->fetch_assoc();
$stmt->close();

// Verificar asistencia
if (!isset($inscripcion['asistencia']) || $inscripcion['asistencia'] != 1) {
    die("El corredor no ha registrado asistencia al evento, por lo que no tiene certificado de participación disponible.");
}

// Control de Permisos de Seguridad:
// Si no es un usuario de tipo administrador o organizador, validar que tenga relación con la inscripción.
if ($user_type !== 'admin' && $user_type !== 'organizador') {
    $allowed = false;
    
    // Caso 1: Es el titular de la inscripción
    if ($inscripcion['id_usuario'] == $user_id) {
        $allowed = true;
    }
    // Caso 2: Es un invitado registrado por el usuario actual
    elseif ($inscripcion['parent_id'] > 0) {
        // Verificar si la inscripción padre pertenece al usuario logueado
        $parent_query = "SELECT id_usuario FROM inscripciones WHERE id = ?";
        $stmt_parent = $db->prepare($parent_query);
        $stmt_parent->bind_param("i", $inscripcion['parent_id']);
        $stmt_parent->execute();
        $parent_result = $stmt_parent->get_result();
        if ($parent_result->num_rows > 0) {
            $parent = $parent_result->fetch_assoc();
            if ($parent['id_usuario'] == $user_id) {
                $allowed = true;
            }
        }
        $stmt_parent->close();
    }
    
    if (!$allowed) {
        die("No tienes permisos para acceder a este certificado.");
    }
}

// Determinar el nombre a mostrar (usuario registrado o invitado)
$nombre_mostrar = $inscripcion['usuario_nombre'] 
    ? ($inscripcion['usuario_nombre'] . ' ' . $inscripcion['usuario_apellido']) 
    : ($inscripcion['nombre'] . ' ' . $inscripcion['apellido']);

if (empty(trim($nombre_mostrar))) {
    $nombre_mostrar = "Participante Destacado";
}

date_default_timezone_set('America/Santo_Domingo');
$fecha_evento = strtotime($inscripcion['fecha_evento']);
$meses = [
    'January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo', 'April' => 'Abril',
    'May' => 'Mayo', 'June' => 'Junio', 'July' => 'Julio', 'August' => 'Agosto',
    'September' => 'Septiembre', 'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'
];
$dia = date('d', $fecha_evento);
$mes_en = date('F', $fecha_evento);
$mes = $meses[$mes_en] ?? $mes_en;
$anio = date('Y', $fecha_evento);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificado - <?php echo htmlspecialchars($nombre_mostrar); ?></title>
    <!-- Google Fonts para un diseño premium -->
    <link href="https://fonts.googleapis.com/css2?family=Alex+Brush&family=Cinzel:wght@500;700;800&family=Montserrat:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- html2pdf.js para exportar PDF directamente en cliente -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        :root {
            --primary-dark: #1b1622;
            --primary-accent: #8b263e;
            --gold-light: #f3e5ab;
            --gold-main: #d4af37;
            --gold-dark: #aa7c11;
            --text-dark: #2c3e50;
            --text-muted: #7f8c8d;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Montserrat', sans-serif;
            background-color: #0f1016;
            color: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Controles de Acción (No se imprimen) */
        .action-bar {
            width: 100%;
            max-width: 1100px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 30px;
            border-radius: 0 0 12px 12px;
        }

        .bar-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--gold-light);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-group {
            display: flex;
            gap: 15px;
        }

        .btn {
            padding: 10px 22px;
            font-size: 0.95rem;
            font-weight: 600;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
        }

        .btn-secondary {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-secondary:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--gold-main), var(--gold-dark));
            color: #000;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(212, 175, 55, 0.5);
        }

        /* Contenedor del Certificado (Formato A4 Horizontal) */
        .certificate-wrapper {
            width: 100%;
            max-width: 1100px;
            padding: 20px;
            display: flex;
            justify-content: center;
            margin-bottom: 50px;
            overflow: visible;
        }

        .certificate {
            width: 1050px;
            height: 742px; /* Proporción A4 */
            background-color: #fff;
            background-image: 
                radial-gradient(circle at 100% 0%, rgba(243, 229, 171, 0.08) 0%, transparent 60%),
                radial-gradient(circle at 0% 100%, rgba(139, 38, 62, 0.08) 0%, transparent 60%);
            color: var(--text-dark);
            padding: 45px;
            position: relative;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.5);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            border-radius: 4px;
            overflow: hidden;
            transition: transform 0.2s ease-out;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Bordes Ornamentales Premium */
        .certificate-border-outer {
            position: absolute;
            top: 20px;
            bottom: 20px;
            left: 20px;
            right: 20px;
            border: 4px solid var(--primary-accent);
            pointer-events: none;
        }

        .certificate-border-inner {
            position: absolute;
            top: 28px;
            bottom: 28px;
            left: 28px;
            right: 28px;
            border: 2px solid var(--gold-main);
            pointer-events: none;
        }

        /* Esquinas Ornamentales */
        .corner-element {
            position: absolute;
            width: 45px;
            height: 45px;
            border: 2px solid var(--gold-main);
            pointer-events: none;
        }
        .corner-tl { top: 28px; left: 28px; border-right: none; border-bottom: none; }
        .corner-tr { top: 28px; right: 28px; border-left: none; border-bottom: none; }
        .corner-bl { bottom: 28px; left: 28px; border-right: none; border-top: none; }
        .corner-br { bottom: 28px; right: 28px; border-left: none; border-top: none; }

        /* Contenido del Certificado */
        .certificate-header {
            text-align: center;
            margin-top: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 12px;
        }

        .cert-logo {
            height: 60px;
            width: auto;
        }

        .cert-text-logo {
            height: 40px;
            width: auto;
        }

        .certificate-title {
            font-family: 'Cinzel', serif;
            font-size: 2.4rem;
            font-weight: 800;
            letter-spacing: 5px;
            margin: 0;
            background: linear-gradient(135deg, var(--gold-dark), var(--gold-main) 50%, var(--gold-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 1px 1px 1px rgba(0,0,0,0.05);
            text-transform: uppercase;
        }

        .certificate-subtitle {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--primary-accent);
            text-transform: uppercase;
            letter-spacing: 4px;
            margin-top: 5px;
            margin-bottom: 15px;
        }

        .certificate-body {
            text-align: center;
            max-width: 800px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .presentation-text {
            font-size: 1.1rem;
            font-weight: 300;
            color: var(--text-muted);
            margin: 0 0 10px 0;
            font-style: italic;
        }

        .runner-name {
            font-family: 'Cinzel', serif;
            font-size: 3.2rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin: 5px 0;
            border-bottom: 2px dashed rgba(139, 38, 62, 0.2);
            padding-bottom: 10px;
            width: 100%;
            max-width: 700px;
            word-wrap: break-word;
            text-shadow: 1px 1px 0px rgba(255, 255, 255, 0.8);
        }

        .achievement-text {
            font-size: 1.05rem;
            line-height: 1.6;
            color: var(--text-dark);
            margin: 15px 0 10px 0;
            max-width: 720px;
        }

        .event-name {
            font-weight: 700;
            color: var(--primary-accent);
        }

        .event-details-meta {
            margin-top: 15px;
            font-size: 0.95rem;
            color: var(--text-muted);
            font-weight: 500;
            display: flex;
            gap: 20px;
            justify-content: center;
        }

        .meta-tag {
            background: #f8f9fa;
            border: 1px solid #eee;
            padding: 5px 15px;
            border-radius: 20px;
            color: var(--text-dark);
        }

        .meta-tag i {
            color: var(--gold-dark);
            margin-right: 5px;
        }

        .certificate-footer {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: flex-end;
            padding: 0 40px;
            margin-bottom: 25px;
        }

        .signature-block {
            text-align: center;
            width: 300px;
        }

        .signature-line {
            border-top: 1px solid var(--text-muted);
            margin-top: 10px;
            padding-top: 5px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-dark);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .signature-title {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .handwritten-signature {
            font-family: 'Alex Brush', cursive;
            font-size: 2.2rem;
            color: #2c1b4d;
            height: 45px;
            line-height: 45px;
            margin-bottom: -5px;
            user-select: none;
        }

        /* Estilos de Impresión */
        @media print {
            body {
                background: #fff;
                color: #000;
                padding: 0;
                margin: 0;
                min-height: auto;
            }

            .action-bar {
                display: none !important;
            }

            .certificate-wrapper {
                padding: 0;
                margin: 0;
                width: 100%;
                max-width: 100%;
                display: block;
            }

            .certificate {
                width: 100%;
                height: 100vh;
                box-shadow: none;
                border: none;
                border-radius: 0;
                page-break-inside: avoid;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                background-color: #fff !important;
            }

            @page {
                size: landscape;
                margin: 0;
            }
        }
    </style>
</head>
<body>

    <!-- Barra de Control Flotante (Se oculta en la impresión) -->
    <div class="action-bar">
        <div class="bar-title">
            <i class="fa-solid fa-certificate"></i>
            <span>Certificado de Participación Oficial</span>
        </div>
        <div class="btn-group">
            <a href="certificates.php" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left"></i> Volver a Certificados
            </a>
            <button class="btn btn-primary" onclick="downloadPDF()">
                <i class="fa-solid fa-download"></i> Descargar PDF
            </button>
        </div>
    </div>

    <!-- Contenedor del Certificado -->
    <div class="certificate-wrapper">
        <div class="certificate">
            <!-- Bordes ornamentales -->
            <div class="certificate-border-outer"></div>
            <div class="certificate-border-inner"></div>
            <div class="corner-element corner-tl"></div>
            <div class="corner-element corner-tr"></div>
            <div class="corner-element corner-bl"></div>
            <div class="corner-element corner-br"></div>

            <!-- Cabecera -->
            <div class="certificate-header">
                <div class="logo-container">
                    <img src="../../assets/images/logo.png" alt="Logo" class="cert-logo">
                    <img src="../../assets/images/text.png" alt="FIT5K" class="cert-text-logo">
                </div>
                <h1 class="certificate-title">Certificado</h1>
                <div class="certificate-subtitle">de participación</div>
            </div>

            <!-- Cuerpo principal -->
            <div class="certificate-body">
                <p class="presentation-text">Se otorga con orgullo el presente documento a:</p>
                <div class="runner-name"><?php echo htmlspecialchars($nombre_mostrar); ?></div>
                
                <p class="achievement-text">
                    Por haber completado exitosamente y con espíritu deportivo el trayecto oficial del evento
                    <span class="event-name">"<?php echo htmlspecialchars($inscripcion['evento_nombre']); ?>"</span>,
                    demostrando perseverancia, disciplina y un fuerte compromiso con su bienestar físico y los valores de la comunidad deportiva.
                </p>

                <div class="event-details-meta">
                    <?php if (!empty($inscripcion['evento_distancia'])): ?>
                    <div class="meta-tag">
                        <i class="fa-solid fa-route"></i>
                        <span>Distancia: <?php echo floatval($inscripcion['evento_distancia']); ?>K</span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($inscripcion['tiempo_final'])): ?>
                    <div class="meta-tag">
                        <i class="fa-solid fa-stopwatch"></i>
                        <span>Tiempo Oficial: <?php echo htmlspecialchars($inscripcion['tiempo_final']); ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="meta-tag">
                        <i class="fa-solid fa-calendar-day"></i>
                        <span>Fecha: <?php echo "$dia de $mes de $anio"; ?></span>
                    </div>

                    <div class="meta-tag">
                        <i class="fa-solid fa-map-location-dot"></i>
                        <span>Lugar: <?php echo htmlspecialchars($inscripcion['evento_ubicacion']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Firmas y Sello -->
            <div class="certificate-footer">
                <div class="signature-block">
                    <div class="handwritten-signature">Comité Organizador</div>
                    <div class="signature-line">Comité Organizador</div>
                    <div class="signature-title">COMITÉ ORGANIZADOR</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 16, 22, 0.85); z-index: 9999; justify-content: center; align-items: center; flex-direction: column; color: #fff; font-family: 'Montserrat', sans-serif; backdrop-filter: blur(5px);">
        <div style="border: 4px solid rgba(255,255,255,0.1); border-top: 4px solid #d4af37; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin-bottom: 20px;"></div>
        <h3 style="margin: 0; font-weight: 600; color: #fff; font-size: 1.2rem; letter-spacing: 1px;">Generando PDF Oficial...</h3>
        <p style="margin: 10px 0 0 0; color: #7f8c8d; font-size: 0.9rem;">Por favor, espera un momento.</p>
    </div>

    <script>
        function adjustScale() {
            const wrapper = document.querySelector('.certificate-wrapper');
            const cert = document.querySelector('.certificate');
            if (!wrapper || !cert) return;
            
            const viewportWidth = document.documentElement.clientWidth;
            const padding = 30; // minimal padding
            const availableWidth = viewportWidth - padding;
            const certWidth = 1050;
            
            if (availableWidth < certWidth) {
                const scale = availableWidth / certWidth;
                cert.style.transform = `scale(${scale})`;
                cert.style.transformOrigin = 'top center';
                wrapper.style.height = `${(742 * scale) + 10}px`;
            } else {
                cert.style.transform = 'none';
                cert.style.transformOrigin = '';
                wrapper.style.height = 'auto';
            }
        }
        
        function downloadPDF() {
            const overlay = document.getElementById('loading-overlay');
            if (overlay) overlay.style.display = 'flex';
            
            const btn = document.querySelector('.btn-primary');
            const originalText = btn ? btn.innerHTML : '';
            if (btn) {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando PDF...';
                btn.disabled = true;
            }
            
            const element = document.querySelector('.certificate');
            
            // Save original transform and wrapper styles to restore them after generation
            const originalTransform = element.style.transform;
            const originalTransformOrigin = element.style.transformOrigin;
            
            const wrapper = document.querySelector('.certificate-wrapper');
            const originalWrapperHeight = wrapper ? wrapper.style.height : '';
            
            // Temporarily reset styles so html2canvas renders at full size without scaling
            element.style.transform = 'none';
            element.style.transformOrigin = 'unset';
            if (wrapper) wrapper.style.height = 'auto';
            
            const opt = {
                margin:       0,
                filename:     'Certificado_<?php echo preg_replace('/[^a-zA-Z0-9_-]/', '_', $nombre_mostrar); ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, logging: false },
                jsPDF:        { unit: 'px', format: [1050, 742], orientation: 'landscape' }
            };
            
            // Generate PDF directly from the visible element
            html2pdf().set(opt).from(element).save().then(() => {
                // Restore original styles
                element.style.transform = originalTransform;
                element.style.transformOrigin = originalTransformOrigin;
                if (wrapper) wrapper.style.height = originalWrapperHeight;
                
                // Recalculate scale
                adjustScale();
                
                if (overlay) overlay.style.display = 'none';
                if (btn) {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            }).catch(err => {
                console.error('Error generating PDF:', err);
                
                // Restore original styles
                element.style.transform = originalTransform;
                element.style.transformOrigin = originalTransformOrigin;
                if (wrapper) wrapper.style.height = originalWrapperHeight;
                
                adjustScale();
                
                if (overlay) overlay.style.display = 'none';
                if (btn) {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
                // Fallback to window.print if html2pdf fails
                window.print();
            });
        }

        window.addEventListener('resize', adjustScale);
        
        // Auto-download if 'download=1' is in URL
        window.addEventListener('load', () => {
            adjustScale();
            
            const urlParams = new URLSearchParams(window.location.search);
            const shouldDownload = urlParams.get('download') === '1' || urlParams.get('download') === 'true';
            
            if (shouldDownload) {
                if (document.fonts) {
                    document.fonts.ready.then(() => {
                        setTimeout(downloadPDF, 800);
                    });
                } else {
                    setTimeout(downloadPDF, 1000);
                }
            }
        });
    </script>

</body>
</html>
