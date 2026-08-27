<?php
// modules/public/about.php
require_once '../../config/config.php';
require_once '../../config/database.php';

$db = getDB();

include '../../includes/header.php';
?>

<div class="container">
    <!-- Hero Section -->
    <div style="
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        padding: 4rem 2rem;
        border-radius: var(--border-radius);
        text-align: center;
        margin-bottom: 3rem;
    ">
        <h1 style="font-size: 3rem; margin-bottom: 1rem;">
            <i class="fas fa-heartbeat"></i> Sobre Fit5K
        </h1>
        <p style="font-size: 1.2rem; max-width: 800px; margin: 0 auto;">
            Conectamos personas con experiencias inolvidables.<br>
            Más que una plataforma somos una comunidad apasionada por la práctica de actividades que nos
            permitan una vida más saludable
        </p>
    </div>
    
    <!-- Nuestra Historia -->
    <div class="about-intro-grid">
        <div>
            <h2 style="color: var(--primary-color); margin-bottom: 1.5rem;">
                <i class="fas fa-history"></i> Nosotros
            </h2>
            <p style="color: var(--text-light); line-height: 1.7; margin-bottom: 1.5rem;">
                En Fit5K fomentamos el bienestar físico y mental a través de la actividad física y el aprendizaje. 
                Nuestros eventos están diseñados para unir a la comunidad fitness en un ambiente positivo, inclusivo y lleno de energía, 
                donde cada participante es protagonista de su propio logro.
            </p>
            <h2 style="color: var(--primary-color); margin-bottom: 1.5rem;">
                <i class="fas fa-history"></i> Nuestra Historia
            </h2>
            <p style="color: var(--text-light); line-height: 1.7; margin-bottom: 1.5rem;">
                Fit5K nace con el propósito de inspirar a personas de todas las edades a moverse, cuidarse y disfrutar del ejercicio como un estilo de vida. 
                Hemos sido el resultado de diferentes transformaciones desde el año 2022, a través de las cuales hemos aprendido en el camino y cada año llevándonos la satisfacción de cientos de personas felices. 
                Creemos que cada paso cuenta y que no se trata sólo de llegar a la meta, sino de atreverse a empezar. Somos una comunidad que promueve la salud, la disciplina y la motivación a través del deporte. 
                Hoy somos una plataforma dedicada a la gestión de eventos deportivos en la región, conectando talentos humanos bajo un mismo propósito.
            </p>
            <!--
            <div class="about-stats-grid">
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
                    <div style="color: var(--text-dark); font-weight: bold; font-size: 1.5rem;">100+</div>
                    <div style="color: var(--text-light);">Eventos</div>
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
                        <i class="fas fa-users"></i>
                    </div>
                    <div style="color: var(--text-dark); font-weight: bold; font-size: 1.5rem;">10K+</div>
                    <div style="color: var(--text-light);">Corredores</div>
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
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div style="color: var(--text-dark); font-weight: bold; font-size: 1.5rem;">25+</div>
                    <div style="color: var(--text-light);">Ciudades</div>
                </div>
            </div>
-->
        </div>
        
        <div style="
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        ">
            <img src="../../assets/images/running-team.jpg" alt="Equipo Fit5K" 
                 style="width: 100%; border-radius: var(--border-radius); margin-bottom: 1.5rem;">
            <div style="color: var(--text-light); font-style: italic; text-align: center;">
                "Correr no es solo un deporte, es un estilo de vida que nos une a todos"
            </div>
        </div>
    </div>
    
    <!-- Nuestra Misión -->
    <div style="
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        padding: 4rem 2rem;
        border-radius: var(--border-radius);
        margin-bottom: 4rem;
        text-align: center;
    ">
        <h2 style="color: var(--primary-color); margin-bottom: 3rem;">
            <i class="fas fa-bullseye"></i> Nuestra Misión
        </h2>
        <p>Organizar una experiencia deportiva inclusiva y solidaria que motive a personas de todas las edades a
        adoptar hábitos saludables, fortalecer la unión comunitaria y contribuir al bienestar social mediante el
        apoyo a causas que generan un impacto positivo en la vida de niños y familias.</p>
        <div style="
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        ">
            <img src="../../assets/images/foto_nos.jpeg" alt="Equipo Fit5K" 
                 style="width: 100%; border-radius: var(--border-radius); margin-bottom: 1.5rem;">
            <!--
            <div style="color: var(--text-light); font-style: italic; text-align: center;">
                "Correr no es solo un deporte, es un estilo de vida que nos une a todos"
            </div>
            -->
        </div>
       <!-- <div class="about-mission-grid">
            <div style="
                background: white;
                padding: 2rem;
                border-radius: var(--border-radius);
                box-shadow: var(--box-shadow);
            ">
                <div style="
                    background: var(--primary-color);
                    color: white;
                    width: 70px;
                    height: 70px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 1.5rem;
                    font-size: 2rem;
                ">
                    <i class="fas fa-handshake"></i>
                </div>
                <h3 style="color: var(--text-dark); margin-bottom: 1rem;">Conectar</h3>
                <p style="color: var(--text-light);">
                    <i class="fas fa-tools"></i> En construccion <i class="fas fa-hard-hat"></i>
                </p>
            </div>
            
            <div style="
                background: white;
                padding: 2rem;
                border-radius: var(--border-radius);
                box-shadow: var(--box-shadow);
            ">
                <div style="
                    background: var(--primary-color);
                    color: white;
                    width: 70px;
                    height: 70px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 1.5rem;
                    font-size: 2rem;
                ">
                    <i class="fas fa-rocket"></i>
                </div>
                <h3 style="color: var(--text-dark); margin-bottom: 1rem;">Simplificar</h3>
                <p style="color: var(--text-light);">
                    <i class="fas fa-tools"></i> En construccion <i class="fas fa-hard-hat"></i>
                </p>
            </div>
            
            <div style="
                background: white;
                padding: 2rem;
                border-radius: var(--border-radius);
                box-shadow: var(--box-shadow);
            ">
                <div style="
                    background: var(--primary-color);
                    color: white;
                    width: 70px;
                    height: 70px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 1.5rem;
                    font-size: 2rem;
                ">
                    <i class="fas fa-heart"></i>
                </div>
                <h3 style="color: var(--text-dark); margin-bottom: 1rem;">Inspirar</h3>
                <p style="color: var(--text-light);">
                    <i class="fas fa-tools"></i> En construccion <i class="fas fa-hard-hat"></i>
                </p>
            </div>
        </div>
-->
    </div>
    
    <!-- Valores -->
    <div style="margin-bottom: 4rem;">
        <h2 style="color: var(--primary-color); text-align: center; margin-bottom: 3rem;">
            <i class="fas fa-star"></i> Nuestros Valores
        </h2>
        
        <div class="about-values-grid">
            <div style="
                background: white;
                padding: 2rem;
                border-radius: var(--border-radius);
                box-shadow: var(--box-shadow);
                display: flex;
                gap: 1.5rem;
                align-items: flex-start;
            ">
                <div style="
                    background: var(--success);
                    color: white;
                    min-width: 50px;
                    height: 50px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 1.2rem;
                ">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <h3 style="color: var(--text-dark); margin-bottom: 0.5rem;">Salud y bienestar</h3>
                    <p style="color: var(--text-light); margin: 0;">
                        Promovemos la actividad física y los hábitos saludables como base para una mejor
                        calidad de vida en personas de todas las edades.
                    </p>
                </div>
            </div>
            
            <div style="
                background: white;
                padding: 2rem;
                border-radius: var(--border-radius);
                box-shadow: var(--box-shadow);
                display: flex;
                gap: 1.5rem;
                align-items: flex-start;
            ">
                <div style="
                    background: var(--info);
                    color: white;
                    min-width: 50px;
                    height: 50px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 1.2rem;
                ">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <h3 style="color: var(--text-dark); margin-bottom: 0.5rem;">Inclusión</h3>
                    <p style="color: var(--text-light); margin: 0;">
                        Creemos que todos pueden participar: corredores, caminantes, amantes del baile, familias y
                        principiantes, sin importar su nivel.
                    </p>
                </div>
            </div>
            
            <div style="
                background: white;
                padding: 2rem;
                border-radius: var(--border-radius);
                box-shadow: var(--box-shadow);
                display: flex;
                gap: 1.5rem;
                align-items: flex-start;
            ">
                <div style="
                    background: var(--warning);
                    color: white;
                    min-width: 50px;
                    height: 50px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 1.2rem;
                ">
                    <i class="fas fa-bullseye"></i>
                </div>
                <div>
                    <h3 style="color: var(--text-dark); margin-bottom: 0.5rem;">Solidaridad</h3>
                    <p style="color: var(--text-light); margin: 0;">
                        Cada paso suma para apoyar causas sociales que impactan positivamente a niños y familias de nuestra comunidad.
                    </p>
                </div>
            </div>
            
            <div style="
                background: white;
                padding: 2rem;
                border-radius: var(--border-radius);
                box-shadow: var(--box-shadow);
                display: flex;
                gap: 1.5rem;
                align-items: flex-start;
            ">
                <div style="
                    background: var(--danger);
                    color: white;
                    min-width: 50px;
                    height: 50px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 1.2rem;
                ">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div>
                    <h3 style="color: var(--text-dark); margin-bottom: 0.5rem;">Integridad</h3>
                    <p style="color: var(--text-light); margin: 0;">
                        Actuamos con transparencia, respeto y compromiso en cada acción del evento.
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Equipo -->
    <div style="margin-bottom: 4rem;">
        <h2 style="color: var(--primary-color); text-align: center; margin-bottom: 3rem;">
            <i class="fas fa-users"></i> Conoce a Nuestro Equipo
        </h2>
        
        <div class="about-team-grid">
            <?php
            // Auto-migration: Crear tabla y datos si no existen
            $db->query("CREATE TABLE IF NOT EXISTS equipo (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(100) NOT NULL,
                cargo VARCHAR(100) NOT NULL,
                descripcion TEXT,
                imagen_url VARCHAR(255) DEFAULT NULL,
                orden INT DEFAULT 0,
                activo TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            
            $count = $db->query("SELECT COUNT(*) as count FROM equipo")->fetch_assoc()['count'];
            if ($count == 0) {
                // Insertar datos iniciales
                 $team_members_init = [
                    [
                        'nombre' => 'Arlene Báez',
                        'cargo' => 'Lider Comite Organizador',
                        'descripcion' => 'Maratonista apasionado con 15+ años de experiencia en organización de eventos deportivos',
                        'orden' => 1
                    ],
                    [
                        'nombre' => 'Jan Carlos Fernández',
                        'cargo' => 'Coordinador de Logistica',
                        'descripcion' => 'Especialista en logística de eventos y gestión de comunidades deportivas',
                        'orden' => 2
                    ],
                    [
                        'nombre' => 'Juan Carlos Toribio',
                        'cargo' => 'Coord. Zumba y Relaciones Públicas',
                        'descripcion' => 'Desarrollador full-stack y corredor amateur, encargado de la plataforma tecnológica',
                        'orden' => 3
                    ],
                    [
                        'nombre' => 'Ana Espinal',
                        'cargo' => 'Instructor de Zumba',
                        'descripcion' => 'Desarrollador full-stack y corredor amateur, encargado de la plataforma tecnológica',
                        'orden' => 4
                    ],
                    [
                        'nombre' => 'Eduardo Espinal',
                        'cargo' => 'Instructor de Zumba',
                        'descripcion' => 'Desarrollador full-stack y corredor amateur, encargado de la plataforma tecnológica',
                        'orden' => 5
                    ]
                ];
                
                $stmt_insert = $db->prepare("INSERT INTO equipo (nombre, cargo, descripcion, orden) VALUES (?, ?, ?, ?)");
                foreach ($team_members_init as $member) {
                    $stmt_insert->bind_param("sssi", $member['nombre'], $member['cargo'], $member['descripcion'], $member['orden']);
                    $stmt_insert->execute();
                }
                $stmt_insert->close();
            }
            
            // Obtener equipo de la base de datos
            $equipo_query = "SELECT * FROM equipo WHERE activo = 1 ORDER BY orden ASC";
            $equipo_result = $db->query($equipo_query);
            
            if ($equipo_result && $equipo_result->num_rows > 0):
                while($miembro = $equipo_result->fetch_assoc()):
            ?>
            <div style="
                background: white;
                border-radius: var(--border-radius);
                overflow: hidden;
                box-shadow: var(--box-shadow);
                text-align: center;
            ">
                <div style="
                    height: 200px;
                    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-size: 4rem;
                ">
                    <?php if (!empty($miembro['imagen_url'])): ?>
                        <img src="" alt="<?php echo htmlspecialchars($miembro['nombre']); ?>" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                <div style="padding: 1.5rem;">
                    <h3 style="color: var(--text-dark); margin-bottom: 0.5rem;"><?php echo htmlspecialchars($miembro['nombre']); ?></h3>
                    <p style="color: var(--primary-color); margin-bottom: 1rem;"><?php echo htmlspecialchars($miembro['cargo']); ?></p>
                    <p style="color: var(--text-light); font-size: 0.9rem;">
                        <?php echo htmlspecialchars($miembro['descripcion']); ?>
                    </p>
                </div>
            </div>
            <?php 
                endwhile;
            else:
            ?>
                <p class="text-center w-100">No hay miembros del equipo registrados.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- CTA Final -->
     <?php if (!isset($_SESSION['user_id'])): ?> 
        
    <div style="
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        padding: 4rem 2rem;
        border-radius: var(--border-radius);
        text-align: center;
    ">
        <h2 style="font-size: 2.5rem; margin-bottom: 1rem;">
            ¡Únete a Nuestra Comunidad!
        </h2>
        <p style="font-size: 1.2rem; max-width: 600px; margin: 0 auto 2rem;">
            Ya seas un corredor principiante o un atleta experimentado, 
            en Fit5K encontrarás el evento perfecto para ti.
        </p>
        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <a href="../auth/register.php" class="btn" style="
                background: white;
                color: var(--primary-color);
                padding: 1rem 2rem;
                font-weight: bold;
            ">
                <i class="fas fa-user-plus"></i> Regístrate Gratis
            </a>
            <a href="../events/index.php" class="btn" style="
                background: transparent;
                border: 2px solid white;
                color: white;
                padding: 1rem 2rem;
            ">
                <i class="fas fa-running"></i> Ver Eventos
            </a>
        </div>
    </div>
    <?php endif; ?> 
    <br>
</div>

<?php include '../../includes/footer.php'; ?>