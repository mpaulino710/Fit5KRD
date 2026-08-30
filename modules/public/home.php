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


<div class="hero-section" style="position: relative; overflow: hidden; padding: 0;">
    
    <?php if (!empty($fotos_collage)): ?>
    <!-- Background Collage -->
    <div class="hero-collage">
        <?php foreach ($fotos_collage as $foto): ?>
        <div class="collage-item">
            <img src="../../<?php echo htmlspecialchars($foto['url_foto']); ?>" alt="Momento Fit5K">
        </div>
        <?php endforeach; ?>
    </div>
    

    <style>
    .hero-collage {
        position: relative;
        width: 100%;
        height: 400px;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        grid-template-rows: 1fr;
        gap: 0;
        z-index: 1;
        opacity: 1;
    }
    .collage-item {
        width: 100%;
        height: 100%;
        overflow: hidden;
    }
    .collage-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    /* Mobile adjustment */
    @media (max-width: 768px) {
        .hero-collage {
            grid-template-columns: 1fr;
            grid-template-rows: repeat(3, 1fr);
            height: 600px; /* Taller for stacked images */
        }
    }
    </style>
    <?php endif; ?>

    <div class="hero-content" style="position: relative; z-index: 2; padding: 1rem 2rem;">
        <h1>¡Corre hacia tus metas con Fit5K!</h1>
        <p class="hero-description">
            Únete a la comunidad más grande de gente Fit!
            Participa en emocionantes eventos donde se integran actividades como:
            Zumba, Caminar, Correr, Charlas, Cursos y Talleres
        </p>
        <?php if (!isset($_SESSION['user_id'])): ?> 
        <a href="../auth/register.php" class="btn btn-primary hero-btn">
            Únete Ahora
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="container">
    <!-- Sección de Estadísticas con tarjetas -->
    <div class="stats-section">
        <h2 class="section-title">Nuestra Comunidad</h2>
        <div class="stats-grid">
           <!-- <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-running"></i>
                </div>
                <div class="stat-number"><?php echo number_format($total_corredores); ?></div>
                <div class="stat-label">Corredores Registrados</div>
            </div>
            -->
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-flag-checkered"></i>
                </div>
                <div class="stat-number"><?php echo number_format($total_eventos); ?></div>
                <div class="stat-label">Eventos Realizados</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-number"><?php echo number_format(count($eventos)); ?></div>
                <div class="stat-label">Próximos Eventos</div>
            </div>
        </div>
    </div>

    <!-- Sección de Eventos -->
    <div class="events-section">
        <h2 class="section-title">Próximos Eventos</h2>
        
        <?php if (!empty($eventos)): ?>
        <div class="events-grid">
            <?php foreach ($eventos as $evento): ?>
            <div class="event-card">
                <div class="event-header">
                    <div class="event-image">
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
                        <span class="event-initials"><i class="fas <?php echo $iconClass; ?>"></i></span>
                    </div>
                    <div class="event-basic-info">
                        <div class="event-type-badge" style="font-size: 0.8rem; text-transform: uppercase; color: var(--primary-color); font-weight: bold; margin-bottom: 5px;">
                            <?php echo isset($evento['tipo_evento']) ? ucfirst($evento['tipo_evento']) : 'Carrera'; ?>
                        </div>
                        <h3 class="event-title"><?php echo htmlspecialchars($evento['nombre']); ?></h3>
                        <div class="event-date">
                            <i class="fas fa-calendar-alt"></i>
                            <?php echo date('d/m/Y h:i A', strtotime($evento['fecha_evento'])); ?>
                        </div>
                    </div>
                </div>
                
                <div class="event-details">
                    <div class="detail-row">
                        <div class="detail-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo htmlspecialchars($evento['ubicacion']); ?></span>
                        </div>
                        <?php if ($evento['distancia'] > 0): ?>
                        <div class="detail-item">
                            <i class="fas fa-route"></i>
                            <span><?php echo $evento['distancia']; ?> km</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-item">
                            <i class="fas fa-users"></i>
                            <span>Cupos: Disponibles<!-- <?php echo $evento['cupo_disponible']; ?>/<?php echo $evento['cupo_maximo']; ?> --></span>
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
                    <a href="../events/view.php?id=<?php echo $evento['id']; ?>" class="btn btn-outline">
                        <i class="fas fa-eye"></i> Detalles
                    </a>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if (in_array($evento['id'], $inscripciones_usuario)): ?>
                        <span class="btn btn-secondary" style="background-color: #6c757d; cursor: default;">
                            <i class="fas fa-check-circle"></i> Inscrito
                        </span>
                        <?php else: ?>
                        <a href="../events/register.php?event_id=<?php echo $evento['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Únete
                        </a>
                        <?php endif; ?>
                    <?php else: ?>
                    <a href="../auth/login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Unete
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="no-events">
            <i class="fas fa-calendar-times"></i>
            <h3>No hay eventos próximos programados</h3>
            <p>Vuelve pronto para conocer nuestras próximas carreras.</p>
        </div>
        <?php endif; ?>


    <?php if (!empty($eventos)): ?>
        <div class="view-all-events">
            <a href="../events/index.php" class="btn btn-primary btn-large">
                <i class="fas fa-calendar-alt"></i> Ver Todos los Eventos
            </a>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($noticias_home)): ?>
    <!-- Sección de Novedades y Videos -->
    <div class="news-section" style="margin-top: 3rem; margin-bottom: 3rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 class="section-title" style="margin-bottom: 0.3rem; text-align: left;">Novedades & Videos Fit5K</h2>
                <p style="color: #6c757d; margin: 0;">Mantente al día con nuestros últimos artículos, rutinas y coberturas en video.</p>
            </div>
            <a href="news.php" class="btn btn-outline" style="border-radius: 20px; font-weight: 500;">
                Ver Todas las Noticias <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <div class="news-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
            <?php foreach ($noticias_home as $noticia): ?>
            <div class="news-card" style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.06); transition: transform 0.3s ease, box-shadow 0.3s ease; display: flex; flex-direction: column;">
                
                <div class="news-media" style="position: relative; height: 190px; overflow: hidden; background: #2a004a;">
                    <?php if (!empty($noticia['youtube_id'])): ?>
                        <iframe src="https://www.youtube.com/embed/<?php echo htmlspecialchars($noticia['youtube_id']); ?>" style="width: 100%; height: 100%; border: 0;" allowfullscreen></iframe>
                    <?php elseif (!empty($noticia['imagen_destacada'])): ?>
                        <img src="../../<?php echo htmlspecialchars($noticia['imagen_destacada']); ?>" alt="<?php echo htmlspecialchars($noticia['titulo']); ?>" style="width: 100%; height: 100%; object-fit: cover; object-position: <?php echo !empty($noticia['posicion_imagen']) ? htmlspecialchars($noticia['posicion_imagen']) : 'center center'; ?>;">
                    <?php else: ?>
                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #ffcc80;">
                            <i class="fas fa-newspaper fa-3x"></i>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($noticia['youtube_id'])): ?>
                        <span style="position: absolute; top: 10px; right: 10px; background: #ff0000; color: white; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; display: flex; align-items: center; gap: 4px; pointer-events: none;">
                            <i class="fab fa-youtube"></i> Video
                        </span>
                    <?php endif; ?>
                </div>

                <div class="news-body" style="padding: 1.2rem; display: flex; flex-direction: column; flex: 1;">
                    <div style="font-size: 0.8rem; color: #888; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 12px;">
                        <span><i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y', strtotime($noticia['creado_en'])); ?></span>
                        <span><i class="far fa-eye"></i> <?php echo number_format($noticia['visitas']); ?> lecturas</span>
                    </div>

                    <h3 style="font-size: 1.15rem; font-weight: 600; margin: 0 0 0.6rem 0; line-height: 1.4;">
                        <a href="view-news.php?slug=<?php echo htmlspecialchars($noticia['slug']); ?>" style="color: #2a004a; text-decoration: none; transition: color 0.2s;">
                            <?php echo htmlspecialchars($noticia['titulo']); ?>
                        </a>
                    </h3>

                    <?php if (!empty($noticia['resumen'])): ?>
                        <p style="color: #666; font-size: 0.9rem; line-height: 1.5; margin: 0 0 1.2rem 0; flex: 1; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                            <?php echo htmlspecialchars($noticia['resumen']); ?>
                        </p>
                    <?php endif; ?>

                    <div style="margin-top: auto; padding-top: 0.5rem; border-top: 1px solid #f0f0f0;">
                        <a href="view-news.php?slug=<?php echo htmlspecialchars($noticia['slug']); ?>" style="color: var(--primary-color, #6a0dad); text-decoration: none; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 6px;">
                            Leer Artículo Completo <i class="fas fa-arrow-right" style="font-size: 0.8rem;"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

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