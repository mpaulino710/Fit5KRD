<?php
// Archivo: modules/public/home.php
require_once '../../config/config.php';
require_once '../../config/database.php';

$db = getDB();

// Obtener eventos próximos
$query = "SELECT * FROM eventos 
          WHERE fecha_evento > NOW() 
          AND estado = 'activo' 
          ORDER BY fecha_evento ASC 
          LIMIT 6";
$result = $db->query($query);
$eventos = $result->fetch_all(MYSQLI_ASSOC);

// Obtener Anuncios Activos
$query_ads = "SELECT * FROM anuncios 
              WHERE estado = 'activo' 
              AND fecha_inicio <= NOW() 
              AND (fecha_fin IS NULL OR fecha_fin >= NOW()) 
              ORDER BY creado_en DESC";
$anuncios = $db->query($query_ads)->fetch_all(MYSQLI_ASSOC);

// Obtener Fotos Aleatorias para Collage
$query_fotos = "SELECT url_foto FROM fotos ORDER BY RAND() LIMIT 3";
$fotos_collage = $db->query($query_fotos)->fetch_all(MYSQLI_ASSOC);

// Obtener últimas 3 noticias publicadas
$query_noticias = "SELECT * FROM noticias WHERE estado = 'publicado' ORDER BY fecha_publicacion DESC, creado_en DESC LIMIT 3";
$result_noticias = $db->query($query_noticias);
$noticias_home = $result_noticias ? $result_noticias->fetch_all(MYSQLI_ASSOC) : [];

// Obtener total de corredores registrados
$query_corredores = "SELECT COUNT(*) as total FROM usuarios WHERE tipo_usuario = 'corredor'";
$total_corredores = $db->query($query_corredores)->fetch_assoc()['total'];

// Obtener total de eventos realizados
$query_eventos = "SELECT COUNT(*) as total FROM eventos WHERE fecha_evento < NOW()";
$total_eventos = $db->query($query_eventos)->fetch_assoc()['total'];

// Obtener inscripciones del usuario si está logueado
$inscripciones_usuario = [];
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $query_inscripciones = "SELECT id_evento FROM inscripciones WHERE id_usuario = ? AND estado != 'cancelada'";
    $stmt = $db->prepare($query_inscripciones);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result_inscripciones = $stmt->get_result();
    while ($row = $result_inscripciones->fetch_assoc()) {
        $inscripciones_usuario[] = $row['id_evento'];
    }
    $stmt->close();
}

include '../../includes/header.php';
?>


<div class="home-hero-layout">
    
    <!-- LATERAL IZQUIERDO: Próximos Eventos -->
    <aside class="home-hero-sidebar home-sidebar-left">
        <div class="sidebar-header">
            <div class="sidebar-title-group">
                <div class="sidebar-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div>
                    <h2 class="sidebar-title">Próximos Eventos</h2>
                    <span class="sidebar-subtitle">Inscríbete y participa</span>
                </div>
            </div>
            <?php if (!empty($eventos)): ?>
            <a href="../events/index.php" class="sidebar-header-link" title="Ver todos">
                Ver todos <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>

        <?php if (!empty($eventos)): ?>
        <div class="sidebar-events-list">
            <?php foreach ($eventos as $evento): ?>
            <div class="sidebar-event-card">
                <div class="sidebar-event-top">
                    <div class="sidebar-event-badge-row">
                        <span class="badge-cat-type">
                            <?php 
                            $iconClass = 'fa-running';
                            if (isset($evento['tipo_evento'])) {
                                switch($evento['tipo_evento']) {
                                    case 'zumba': $iconClass = 'fa-music'; break;
                                    case 'caminata': $iconClass = 'fa-walking'; break;
                                    case 'charla': $iconClass = 'fa-microphone'; break;
                                    case 'curso': $iconClass = 'fa-graduation-cap'; break;
                                    case 'taller': $iconClass = 'fa-tools'; break;
                                    default: $iconClass = 'fa-running';
                                }
                            }
                            ?>
                            <i class="fas <?php echo $iconClass; ?>"></i>
                            <?php echo isset($evento['tipo_evento']) ? ucfirst($evento['tipo_evento']) : 'Carrera'; ?>
                        </span>
                        <span class="badge-date-info">
                            <i class="far fa-clock"></i>
                            <?php echo date('d/m h:i A', strtotime($evento['fecha_evento'])); ?>
                        </span>
                    </div>
                    <h3 class="sidebar-event-title">
                        <a href="../events/view.php?id=<?php echo $evento['id']; ?>">
                            <?php echo htmlspecialchars($evento['nombre']); ?>
                        </a>
                    </h3>
                </div>

                <div class="sidebar-event-meta">
                    <div class="meta-line">
                        <i class="fas fa-map-marker-alt"></i>
                        <span title="<?php echo htmlspecialchars($evento['ubicacion']); ?>">
                            <?php echo htmlspecialchars($evento['ubicacion']); ?>
                        </span>
                    </div>
                    <div class="meta-line-flex">
                        <?php if ($evento['distancia'] > 0): ?>
                        <span><i class="fas fa-route"></i> <?php echo $evento['distancia']; ?> km</span>
                        <?php endif; ?>
                        <span class="meta-price">
                            <?php 
                                $modalidades = !empty($evento['modalidades']) ? json_decode($evento['modalidades'], true) : [];
                                $mostrar_desde = false;
                                foreach ($modalidades as $mod) {
                                    if (is_array($mod) && isset($mod['precio']) && $mod['precio'] > $evento['precio']) {
                                        $mostrar_desde = true;
                                        break;
                                    }
                                }
                                echo ($mostrar_desde ? 'Desde ' : '') . '$' . number_format($evento['precio'], 2); 
                            ?>
                        </span>
                    </div>
                </div>

                <div class="sidebar-event-actions">
                    <a href="../events/view.php?id=<?php echo $evento['id']; ?>" class="btn-sidebar btn-outline-sidebar">
                        Detalles
                    </a>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if (in_array($evento['id'], $inscripciones_usuario)): ?>
                        <span class="btn-sidebar btn-enrolled">
                            <i class="fas fa-check"></i> Inscrito
                        </span>
                        <?php else: ?>
                        <a href="../events/register.php?event_id=<?php echo $evento['id']; ?>" class="btn-sidebar btn-primary-sidebar">
                            Únete
                        </a>
                        <?php endif; ?>
                    <?php else: ?>
                    <a href="../auth/login.php" class="btn-sidebar btn-primary-sidebar">
                        Únete
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="sidebar-footer-cta">
            <a href="../events/index.php" class="btn-view-all">
                <i class="fas fa-calendar-alt"></i> Ver Todos los Eventos (<?php echo count($eventos); ?>)
            </a>
        </div>
        <?php else: ?>
        <div class="sidebar-empty-state">
            <i class="fas fa-calendar-times"></i>
            <p>No hay eventos próximos programados por el momento.</p>
        </div>
        <?php endif; ?>
    </aside>

    <!-- CENTRO: PANTALLA PRINCIPAL / HERO -->
    <section class="home-hero-center">
        <div class="hero-main-card">
            <?php if (!empty($fotos_collage)): ?>
            <div class="hero-collage-wrapper">
                <div class="hero-collage-grid">
                    <?php foreach ($fotos_collage as $foto): ?>
                    <div class="hero-collage-cell">
                        <img src="../../<?php echo htmlspecialchars($foto['url_foto']); ?>" alt="Momento Fit5K">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="hero-center-body">
                <div class="hero-tagline"><i class="fas fa-bolt"></i> Comunidad Deportiva & Fitness</div>
                <h1 class="hero-main-title">¡Corre hacia tus metas con Fit5K!</h1>
                <p class="hero-main-desc">
                    Únete a la comunidad más grande de gente Fit. Participa en emocionantes actividades integradas como
                    <strong>Zumba, Caminatas, Carreras, Charlas, Cursos y Talleres</strong> diseñadas para llevar tu bienestar al siguiente nivel.
                </p>
                <div class="hero-center-cta">
                    <?php if (!isset($_SESSION['user_id'])): ?> 
                    <a href="../auth/register.php" class="btn btn-primary hero-main-btn">
                        <i class="fas fa-user-plus"></i> ¡Únete Ahora!
                    </a>
                    <?php else: ?>
                    <a href="../events/index.php" class="btn btn-primary hero-main-btn">
                        <i class="fas fa-calendar-alt"></i> Explorar Todos los Eventos
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Estadísticas integradas en la base del hero central -->
            <div class="hero-center-stats">
                <div class="hero-stat-box">
                    <div class="hero-stat-ico">
                        <i class="fas fa-flag-checkered"></i>
                    </div>
                    <div class="hero-stat-text">
                        <span class="hero-stat-val"><?php echo number_format($total_eventos); ?></span>
                        <span class="hero-stat-lbl">Eventos Realizados</span>
                    </div>
                </div>

                <div class="hero-stat-sep"></div>

                <div class="hero-stat-box">
                    <div class="hero-stat-ico">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="hero-stat-text">
                        <span class="hero-stat-val"><?php echo number_format(count($eventos)); ?></span>
                        <span class="hero-stat-lbl">Próximos Eventos</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- LATERAL DERECHO: Novedades & Videos Fit5K -->
    <aside class="home-hero-sidebar home-sidebar-right">
        <div class="sidebar-header header-news">
            <div class="sidebar-title-group">
                <div class="sidebar-icon icon-news">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div>
                    <h2 class="sidebar-title">Novedades & Videos</h2>
                    <span class="sidebar-subtitle">Rutinas y coberturas</span>
                </div>
            </div>
            <?php if (!empty($noticias_home)): ?>
            <a href="news.php" class="sidebar-header-link" title="Ver todas">
                Ver todas <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>

        <?php if (!empty($noticias_home)): ?>
        <div class="sidebar-news-list">
            <?php foreach ($noticias_home as $noticia): ?>
            <article class="sidebar-news-card">
                <div class="sidebar-news-media">
                    <?php if (!empty($noticia['youtube_id'])): ?>
                        <iframe src="https://www.youtube.com/embed/<?php echo htmlspecialchars($noticia['youtube_id']); ?>" 
                                title="<?php echo htmlspecialchars($noticia['titulo']); ?>" 
                                loading="lazy" 
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                allowfullscreen>
                        </iframe>
                        <span class="badge-yt-video">
                            <i class="fab fa-youtube"></i> Video
                        </span>
                    <?php elseif (!empty($noticia['imagen_destacada'])): ?>
                        <a href="view-news.php?slug=<?php echo htmlspecialchars($noticia['slug']); ?>">
                            <img src="../../<?php echo htmlspecialchars($noticia['imagen_destacada']); ?>" 
                                 alt="<?php echo htmlspecialchars($noticia['titulo']); ?>" 
                                 style="object-position: <?php echo !empty($noticia['posicion_imagen']) ? htmlspecialchars($noticia['posicion_imagen']) : 'center center'; ?>;">
                        </a>
                    <?php else: ?>
                        <div class="sidebar-news-ph">
                            <i class="fas fa-newspaper"></i>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="sidebar-news-body">
                    <div class="sidebar-news-meta">
                        <span><i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y', strtotime($noticia['creado_en'])); ?></span>
                        <span><i class="far fa-eye"></i> <?php echo number_format($noticia['visitas']); ?> lecturas</span>
                    </div>

                    <h3 class="sidebar-news-title">
                        <a href="view-news.php?slug=<?php echo htmlspecialchars($noticia['slug']); ?>">
                            <?php echo htmlspecialchars($noticia['titulo']); ?>
                        </a>
                    </h3>

                    <?php if (!empty($noticia['resumen'])): ?>
                    <p class="sidebar-news-excerpt">
                        <?php echo htmlspecialchars($noticia['resumen']); ?>
                    </p>
                    <?php endif; ?>

                    <div class="sidebar-news-footer">
                        <a href="view-news.php?slug=<?php echo htmlspecialchars($noticia['slug']); ?>" class="news-more-link">
                            Leer Más <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <div class="sidebar-footer-cta">
            <a href="news.php" class="btn-view-all btn-view-all-news">
                <i class="fas fa-newspaper"></i> Ver Todas las Noticias y Videos
            </a>
        </div>
        <?php else: ?>
        <div class="sidebar-empty-state">
            <i class="fas fa-newspaper"></i>
            <p>No hay novedades publicadas aún.</p>
        </div>
        <?php endif; ?>
    </aside>

</div>

<style>
/* ===== EXPANDIR CONTENEDOR PRINCIPAL PARA 3 COLUMNAS ===== */
main.container {
    max-width: 1560px !important;
    width: 98% !important;
    padding: 0 0.5rem !important;
}

/* Layout de 3 columnas de la pantalla principal */
.home-hero-layout {
    display: grid;
    grid-template-columns: minmax(290px, 320px) minmax(0, 1fr) minmax(290px, 320px);
    gap: 1.25rem;
    align-items: start;
    margin: 0.5rem auto 2.5rem auto;
}

/* ===== ESTILOS DE LOS LATERALES (IZQUIERDO Y DERECHO) ===== */
.home-hero-sidebar {
    background: #ffffff;
    border-radius: 16px;
    padding: 1.1rem;
    border: 1px solid #eaedf1;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.04);
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.sidebar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #f1f3f6;
    position: relative;
}

.sidebar-header::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 45px;
    height: 2px;
    background: var(--primary-color, #6a0dad);
}

.sidebar-header.header-news::after {
    background: #e53935;
}

.sidebar-title-group {
    display: flex;
    align-items: center;
    gap: 0.7rem;
}

.sidebar-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--primary-color, #6a0dad), var(--primary-light, #8a2be2));
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    box-shadow: 0 4px 10px rgba(106, 13, 173, 0.22);
    flex-shrink: 0;
}

.sidebar-icon.icon-news {
    background: linear-gradient(135deg, #e53935, #ff7043);
    box-shadow: 0 4px 10px rgba(229, 57, 53, 0.22);
}

.sidebar-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #2a004a;
    margin: 0;
    line-height: 1.2;
}

.sidebar-subtitle {
    font-size: 0.75rem;
    color: #888;
    display: block;
}

.sidebar-header-link {
    color: var(--primary-color, #6a0dad);
    font-size: 0.78rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 3px 8px;
    border-radius: 6px;
    background: rgba(106, 13, 173, 0.06);
    transition: all 0.2s ease;
}

.sidebar-header-link:hover {
    background: var(--primary-color, #6a0dad);
    color: #ffffff;
}

/* Listas de tarjetas */
.sidebar-events-list,
.sidebar-news-list {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
    max-height: 720px;
    overflow-y: auto;
    padding-right: 4px;
}

/* Scrollbar fina para las columnas */
.sidebar-events-list::-webkit-scrollbar,
.sidebar-news-list::-webkit-scrollbar {
    width: 4px;
}
.sidebar-events-list::-webkit-scrollbar-thumb,
.sidebar-news-list::-webkit-scrollbar-thumb {
    background: #e0e0e0;
    border-radius: 4px;
}

/* Tarjeta de evento en barra lateral */
.sidebar-event-card {
    background: #fbfbfd;
    border: 1px solid #eaedf1;
    border-radius: 12px;
    padding: 0.85rem;
    transition: all 0.25s ease;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.sidebar-event-card:hover {
    background: #ffffff;
    border-color: rgba(106, 13, 173, 0.3);
    box-shadow: 0 6px 16px rgba(106, 13, 173, 0.08);
    transform: translateY(-2px);
}

.sidebar-event-badge-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 4px;
    font-size: 0.72rem;
}

.badge-cat-type {
    background: rgba(106, 13, 173, 0.1);
    color: var(--primary-color, #6a0dad);
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 5px;
    text-transform: uppercase;
    font-size: 0.68rem;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.badge-date-info {
    color: #777;
    font-weight: 500;
}

.sidebar-event-title {
    font-size: 0.95rem;
    font-weight: 700;
    margin: 0.2rem 0;
    line-height: 1.3;
}

.sidebar-event-title a {
    color: #2a004a;
    text-decoration: none;
    transition: color 0.2s ease;
}

.sidebar-event-title a:hover {
    color: var(--primary-color, #6a0dad);
}

.sidebar-event-meta {
    font-size: 0.78rem;
    color: #666;
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.meta-line {
    display: flex;
    align-items: center;
    gap: 5px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.meta-line i, .meta-line-flex i {
    color: var(--primary-color, #6a0dad);
    font-size: 0.75rem;
    width: 14px;
}

.meta-line-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.meta-price {
    font-weight: 700;
    color: #28a745;
}

.sidebar-event-actions {
    display: flex;
    gap: 6px;
    margin-top: 0.25rem;
}

.btn-sidebar {
    flex: 1;
    text-align: center;
    padding: 5px 8px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
}

.btn-outline-sidebar {
    border: 1px solid #ced4da;
    color: #495057;
    background: #ffffff;
}

.btn-outline-sidebar:hover {
    border-color: var(--primary-color, #6a0dad);
    color: var(--primary-color, #6a0dad);
}

.btn-primary-sidebar {
    background: var(--primary-color, #6a0dad);
    color: #ffffff;
    border: 1px solid var(--primary-color, #6a0dad);
}

.btn-primary-sidebar:hover {
    background: var(--primary-dark, #4b0082);
    color: #ffffff;
}

.btn-enrolled {
    background: #6c757d;
    color: #ffffff;
    cursor: default;
}

/* Tarjeta de noticia en barra lateral */
.sidebar-news-card {
    background: #fbfbfd;
    border: 1px solid #eaedf1;
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.25s ease;
    display: flex;
    flex-direction: column;
}

.sidebar-news-card:hover {
    background: #ffffff;
    border-color: rgba(229, 57, 53, 0.3);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.06);
    transform: translateY(-2px);
}

.sidebar-news-media {
    position: relative;
    width: 100%;
    aspect-ratio: 16 / 9;
    background: #1a002c;
    overflow: hidden;
}

.sidebar-news-media iframe {
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
}

.sidebar-news-media img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.3s ease;
}

.sidebar-news-card:hover .sidebar-news-media img {
    transform: scale(1.03);
}

.badge-yt-video {
    position: absolute;
    top: 6px;
    right: 6px;
    background: #ff0000;
    color: #ffffff;
    padding: 2px 7px;
    border-radius: 10px;
    font-size: 0.68rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 3px;
    pointer-events: none;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
}

.sidebar-news-ph {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffcc80;
    font-size: 2rem;
}

.sidebar-news-body {
    padding: 0.8rem;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.sidebar-news-meta {
    font-size: 0.72rem;
    color: #888;
    display: flex;
    align-items: center;
    gap: 10px;
}

.sidebar-news-title {
    font-size: 0.92rem;
    font-weight: 700;
    margin: 0;
    line-height: 1.35;
}

.sidebar-news-title a {
    color: #2a004a;
    text-decoration: none;
    transition: color 0.2s ease;
}

.sidebar-news-title a:hover {
    color: #e53935;
}

.sidebar-news-excerpt {
    font-size: 0.8rem;
    color: #666;
    line-height: 1.4;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.sidebar-news-footer {
    margin-top: 0.3rem;
    padding-top: 0.4rem;
    border-top: 1px solid #f0f0f0;
}

.news-more-link {
    color: #e53935;
    font-size: 0.78rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.news-more-link:hover {
    color: #b71c1c;
    gap: 7px;
}

.sidebar-footer-cta {
    margin-top: 0.25rem;
}

.btn-view-all {
    display: block;
    width: 100%;
    text-align: center;
    padding: 0.65rem;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
    background: rgba(106, 13, 173, 0.08);
    color: var(--primary-color, #6a0dad);
    border: 1px solid rgba(106, 13, 173, 0.15);
    transition: all 0.2s ease;
}

.btn-view-all:hover {
    background: var(--primary-color, #6a0dad);
    color: #ffffff;
}

.btn-view-all-news {
    background: rgba(229, 57, 53, 0.08);
    color: #e53935;
    border: 1px solid rgba(229, 57, 53, 0.15);
}

.btn-view-all-news:hover {
    background: #e53935;
    color: #ffffff;
}

.sidebar-empty-state {
    text-align: center;
    padding: 2rem 1rem;
    color: #999;
}
.sidebar-empty-state i {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
    color: #ccc;
}
.sidebar-empty-state p {
    font-size: 0.85rem;
    margin: 0;
}

/* ===== CENTRO: PANTALLA PRINCIPAL (HERO) ===== */
.home-hero-center {
    display: flex;
    flex-direction: column;
}

.hero-main-card {
    background: #ffffff;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(106, 13, 173, 0.08);
    border: 1px solid #eaedf1;
    display: flex;
    flex-direction: column;
}

.hero-collage-wrapper {
    position: relative;
    width: 100%;
    height: 280px;
    overflow: hidden;
    border-bottom: 1px solid #eaedf1;
}

.hero-collage-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    width: 100%;
    height: 100%;
}

.hero-collage-cell {
    width: 100%;
    height: 100%;
    overflow: hidden;
}

.hero-collage-cell img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.35s ease;
}

.hero-collage-cell:hover img {
    transform: scale(1.04);
}

.hero-center-body {
    padding: 2rem 2.5rem;
    text-align: center;
    background: #ffffff;
}

.hero-tagline {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(106, 13, 173, 0.08);
    color: var(--primary-color, #6a0dad);
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 4px 14px;
    border-radius: 20px;
    margin-bottom: 1rem;
}

.hero-main-title {
    font-size: 2.4rem;
    font-weight: 800;
    color: #2a004a;
    line-height: 1.2;
    margin: 0 0 1.2rem 0;
    letter-spacing: -0.5px;
}

.hero-main-desc {
    font-size: 1.05rem;
    line-height: 1.65;
    color: #555;
    max-width: 620px;
    margin: 0 auto 1.75rem auto;
}

.hero-main-desc strong {
    color: var(--primary-color, #6a0dad);
}

.hero-center-cta {
    margin-bottom: 0.5rem;
}

.hero-main-btn {
    font-size: 1.1rem;
    font-weight: 700;
    padding: 0.85rem 2.5rem;
    border-radius: 50px;
    background: linear-gradient(135deg, var(--primary-color, #6a0dad), var(--primary-dark, #4b0082));
    border: none;
    box-shadow: 0 8px 20px rgba(106, 13, 173, 0.3);
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.hero-main-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 25px rgba(106, 13, 173, 0.4);
    color: #ffffff;
}

/* Estadísticas integradas en la base del hero */
.hero-center-stats {
    display: flex;
    justify-content: space-around;
    align-items: center;
    background: #fbfbfd;
    border-top: 1px solid #eaedf1;
    padding: 1.25rem 2rem;
}

.hero-stat-box {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.hero-stat-ico {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(106, 13, 173, 0.1), rgba(255, 204, 128, 0.25));
    color: var(--primary-color, #6a0dad);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
}

.hero-stat-text {
    display: flex;
    flex-direction: column;
}

.hero-stat-val {
    font-size: 1.7rem;
    font-weight: 800;
    color: var(--primary-dark, #4b0082);
    line-height: 1;
}

.hero-stat-lbl {
    font-size: 0.78rem;
    font-weight: 600;
    color: #777;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 2px;
}

.hero-stat-sep {
    width: 1px;
    height: 40px;
    background: #eaedf1;
}

/* ===== RESPONSIVIDAD ===== */
@media (max-width: 1200px) {
    .home-hero-layout {
        grid-template-columns: 280px minmax(0, 1fr) 280px;
        gap: 1rem;
    }
    .hero-main-title {
        font-size: 2rem;
    }
    .hero-center-body {
        padding: 1.75rem 1.5rem;
    }
}

@media (max-width: 992px) {
    .home-hero-layout {
        grid-template-columns: 1fr 1fr;
        gap: 1.25rem;
    }
    .home-hero-center {
        grid-column: 1 / -1;
        order: 1;
        margin-bottom: 0.5rem;
    }
    .home-sidebar-left {
        order: 2;
    }
    .home-sidebar-right {
        order: 3;
    }
    .sidebar-events-list,
    .sidebar-news-list {
        max-height: 550px;
    }
}

@media (max-width: 680px) {
    .home-hero-layout {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    .hero-collage-wrapper {
        height: 200px;
    }
    .hero-main-title {
        font-size: 1.6rem;
    }
    .hero-center-body {
        padding: 1.5rem 1rem;
    }
    .hero-center-stats {
        flex-direction: column;
        gap: 1rem;
        padding: 1rem;
    }
    .hero-stat-sep {
        display: none;
    }
}
</style>

<?php if (!empty($anuncios)): ?>
<!-- Ads Carousel Section (Full Width) -->
<div class="ads-section-wrapper" style="width: 100%; background: #f8f9fa; border-top: 1px solid #ddd; padding-top: 0.5rem;">
    <div style="max-width: 1200px; margin: 0 auto; padding-left: 10px;">
        <h6 style="color: #6c757d; margin-bottom: 5px; font-weight: 600; text-transform: uppercase; font-size: 0.8rem;">Nuestros aliados</h6>
    </div>
    <div class="ads-carousel-container">
        <div class="ads-track" id="adsTrack">
            <?php foreach ($anuncios as $ad): ?>
            <div class="ad-slide">
                <a href="<?php echo htmlspecialchars($ad['enlace_url']); ?>" target="_blank" class="ad-link" onclick="trackClick(<?php echo $ad['id']; ?>)">
                    <img src="../../<?php echo htmlspecialchars($ad['imagen_url']); ?>" alt="<?php echo htmlspecialchars($ad['titulo']); ?>">
                    <div class="ad-overlay">
                        <span>Ver más <i class="fas fa-external-link-alt"></i></span>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (count($anuncios) > 3): ?>
        <button class="ad-nav ad-prev" onclick="moveSlide(-1)"><i class="fas fa-chevron-left"></i></button>
        <button class="ad-nav ad-next" onclick="moveSlide(1)"><i class="fas fa-chevron-right"></i></button>
        <?php endif; ?>
    </div>
</div>

<style>
.ads-carousel-container {
    position: relative;
    width: 100%;
    margin: 0 auto;
    overflow: hidden;
    height: 150px;
}
.ads-track {
    display: flex;
    transition: transform 0.5s ease-in-out;
    height: 100%;
}
.ad-slide {
    flex: 0 0 33.3333%; /* 3 ads per view by default */
    max-width: 33.3333%;
    height: 100%;
    box-sizing: border-box;
    border-right: 1px solid #fff;
    position: relative;
}
.ad-link {
    display: flex; /* Changed from block to flex for centering */
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    position: relative;
    background: white; /* Optional: background for nice fit */
}
.ad-slide img {
    width: auto;      /* Allow proportional width */
    max-width: 100%;  /* But not exceeding container */
    max-height: 140px; /* Max height requested */
    height: auto;
    object-fit: contain; /* Ensure full image is visible */
    display: block;
}
.ad-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.3);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s;
}
.ad-slide:hover .ad-overlay {
    opacity: 1;
}

/* Responsive: 1 ad on mobile */
@media (max-width: 768px) {
    .ad-slide {
        flex: 0 0 100%;
        max-width: 100%;
    }
}

.ad-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0,0,0,0.5);
    color: white;
    border: none;
    width: 30px;
    height: 60px;
    cursor: pointer;
    font-size: 1rem;
    z-index: 10;
    transition: background 0.3s;
}
.ad-nav:hover { background: rgba(0,0,0,0.8); }
.ad-prev { left: 0; border-top-right-radius: 4px; border-bottom-right-radius: 4px; }
.ad-next { right: 0; border-top-left-radius: 4px; border-bottom-left-radius: 4px; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const track = document.getElementById('adsTrack');
    const slides = document.querySelectorAll('.ad-slide');
    
    if (slides.length === 0) return;

    let currentIndex = 0;
    const totalSlides = slides.length;
    let slideInterval;
    
    // Determine items per view based on screen width
    function getItemsPerView() {
        return window.innerWidth <= 768 ? 1 : 3;
    }

    window.moveSlide = function(direction) {
        currentIndex += direction;
        
        const itemsPerView = getItemsPerView();
        const maxIndex = totalSlides - itemsPerView;

        // Loop logic
        if (currentIndex > maxIndex) {
            currentIndex = 0;
        } else if (currentIndex < 0) {
            currentIndex = maxIndex > 0 ? maxIndex : 0;
        }
        
        updateCarousel();
        resetInterval();
    };

    function updateCarousel() {
        const itemsPerView = getItemsPerView();
        const percentage = 100 / itemsPerView;
        track.style.transform = `translateX(-${currentIndex * percentage}%)`;
    }

    function startInterval() {
        slideInterval = setInterval(() => moveSlide(1), 5000);
    }

    function resetInterval() {
        clearInterval(slideInterval);
        startInterval();
    }

    // Handle Resize
    window.addEventListener('resize', () => {
        // Reset to 0 on resize to avoid glitching
        currentIndex = 0;
        updateCarousel();
        resetInterval();
    });

    if (totalSlides > getItemsPerView()) {
        startInterval();
    }
});

function trackClick(id) {
    // Send async request to track click
    fetch('../../modules/api/track_ad_click.php?id=' + id);
}
</script>
<?php endif; ?>


<?php
// Logic to show welcome modal
$show_welcome_modal = false;
$next_events = [];

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Check if user has seen welcome modal
    $stmt = $db->prepare("SELECT welcome_seen FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result_welcome = $stmt->get_result();
    
    if ($row = $result_welcome->fetch_assoc()) {
        if ($row['welcome_seen'] == 0) {
            $show_welcome_modal = true;
            
            // Fetch next 3 events for the modal
            $query_next = "SELECT * FROM eventos 
                          WHERE fecha_evento > NOW() 
                          AND estado = 'activo' 
                          ORDER BY fecha_evento ASC 
                          LIMIT 3";
            $next_events = $db->query($query_next)->fetch_all(MYSQLI_ASSOC);
        }
    }
    $stmt->close();
}
?>

<?php if ($show_welcome_modal): ?>
<!-- Welcome Modal -->
<div id="welcomeModal" class="welcome-modal-overlay">
    <div class="welcome-modal-content">
        <div class="welcome-header">
            <h2>¡Bienvenido a Fit5K! <span style="font-size: 1.5rem;">👋</span></h2>
            <p>Estamos felices de tenerte aquí. Mira lo que tenemos preparado para ti.</p>
            <button class="close-modal" onclick="closeWelcomeModal()">&times;</button>
        </div>
        
        <div class="welcome-body">
            <h3 style="color: var(--primary-color); margin-bottom: 1rem; text-align: center;">Próximos Eventos Destacados</h3>
            
            <?php if (!empty($next_events)): ?>
            <div class="welcome-events-grid">
                <?php foreach ($next_events as $evento): ?>
                <div class="welcome-event-card">
                    <div class="welcome-event-icon">
                        <?php 
                        $iconClass = 'fa-running';
                        if (isset($evento['tipo_evento'])) {
                            switch($evento['tipo_evento']) {
                                case 'zumba': $iconClass = 'fa-music'; break;
                                case 'caminata': $iconClass = 'fa-walking'; break;
                                case 'charla': $iconClass = 'fa-microphone'; break;
                                case 'curso': $iconClass = 'fa-graduation-cap'; break;
                                case 'taller': $iconClass = 'fa-tools'; break;
                                default: $iconClass = 'fa-running';
                            }
                        }
                        ?>
                        <i class="fas <?php echo $iconClass; ?>"></i>
                    </div>
                    <div class="welcome-event-info">
                        <h4><?php echo htmlspecialchars($evento['nombre']); ?></h4>
                        <span class="welcome-date"><i class="far fa-calendar"></i> <?php echo date('d/m', strtotime($evento['fecha_evento'])); ?></span>
                    </div>
                    <a href="../events/view.php?id=<?php echo $evento['id']; ?>" class="btn btn-sm btn-outline">Ver</a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="text-align: center; color: #666;">No hay eventos próximos en este momento, ¡pero mantente atento!</p>
            <?php endif; ?>
            
            <div class="welcome-cta">
                <a href="../events/index.php" class="btn btn-primary btn-block" onclick="closeWelcomeModal()">Explorar Todos los Eventos</a>
            </div>
        </div>
    </div>
</div>

<style>
.welcome-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 9999;
    display: flex;
    justify-content: center;
    align-items: center;
    backdrop-filter: blur(5px);
    animation: fadeIn 0.3s ease-out;
}

.welcome-modal-content {
    background: white;
    width: 90%;
    max-width: 500px;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    animation: slideUp 0.4s ease-out;
}

.welcome-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    padding: 2rem 1.5rem;
    position: relative;
    text-align: center;
}

.welcome-header h2 {
    color: white;
    margin: 0 0 0.5rem 0;
    font-size: 1.8rem;
}

.welcome-header p {
    margin: 0;
    opacity: 0.9;
    font-size: 1rem;
}

.close-modal {
    position: absolute;
    top: 10px;
    right: 15px;
    background: none;
    border: none;
    color: white;
    font-size: 2rem;
    cursor: pointer;
    opacity: 0.7;
    transition: opacity 0.2s;
}

.close-modal:hover {
    opacity: 1;
}

.welcome-body {
    padding: 1.5rem;
}

.welcome-events-grid {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.welcome-event-card {
    display: flex;
    align-items: center;
    padding: 0.8rem;
    background: #f8f9fa;
    border-radius: 10px;
    border: 1px solid #eee;
    transition: transform 0.2s;
}

.welcome-event-card:hover {
    transform: translateY(-2px);
    border-color: var(--primary-color);
}

.welcome-event-icon {
    width: 40px;
    height: 40px;
    background: rgba(var(--primary-rgb), 0.1);
    color: var(--primary-color);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    font-size: 1.2rem;
}

.welcome-event-info {
    flex: 1;
}

.welcome-event-info h4 {
    margin: 0 0 0.2rem 0;
    font-size: 1rem;
    color: #333;
}

.welcome-date {
    font-size: 0.85rem;
    color: #666;
}

.welcome-cta {
    text-align: center;
}

.btn-block {
    display: block;
    width: 100%;
    text-align: center;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from { transform: translateY(50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
</style>

<script>
function closeWelcomeModal() {
    const modal = document.getElementById('welcomeModal');
    modal.style.opacity = '0';
    setTimeout(() => {
        modal.remove();
    }, 300);
    
    // Call API to update status
    fetch('../../modules/api/update_welcome_status.php', {
        method: 'POST'
    }).catch(err => console.error('Error updating welcome status:', err));
}
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>