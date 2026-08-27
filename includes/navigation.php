<?php
// includes/navigation.php
// Navegación principal del sitio

// NOTA: Ya no actualizamos la BD aquí para evitar problemas de rutas
// El último acceso se puede actualizar en el login o en páginas específicas
?>

<nav class="navbar">
    <div class="logo">
	<img src="/assets/images/logo.png" alt="Fit5K RD" width="60" height="60" >
        <a href="/modules/public/home.php" style="color: white; text-decoration: none;">
            <span><img src="/assets/images/text.png" alt="Fit5K" width="50%" height="50%" ></span>
        </a>
    </div>

    <button class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <ul class="nav-links">
        <li>
            <a href="/modules/public/home.php" 
               class="<?php echo basename($_SERVER['PHP_SELF']) === 'home.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Inicio
            </a>
        </li>
        
        <li>
            <a href="/modules/events/index.php" 
               class="<?php echo basename($_SERVER['PHP_SELF']) === 'index.php' && strpos($_SERVER['PHP_SELF'], 'events') !== false ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i> Eventos
            </a>
        </li>
        
        <li>
            <a href="/modules/public/news.php" 
               class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['news.php', 'view-news.php']) ? 'active' : ''; ?>">
                <i class="fas fa-newspaper"></i> Noticias
            </a>
        </li>
        



        <?php if (isset($_SESSION['user_id'])): ?>
        <li>
            <a href="/modules/gallery/index.php" 
               class="<?php echo basename($_SERVER['PHP_SELF']) === 'index.php' && strpos($_SERVER['PHP_SELF'], 'gallery') !== false ? 'active' : ''; ?>">
                <i class="fas fa-images"></i> Galería
            </a>
        </li>
        <?php endif; ?>
        
        <li>
            <a href="/modules/public/about.php" 
               class="<?php echo basename($_SERVER['PHP_SELF']) === 'about.php' ? 'active' : ''; ?>">
                <i class="fas fa-info-circle"></i> Nosotros
            </a>
        </li>
        
        <li>
            <a href="/modules/public/contact.php" 
               class="<?php echo basename($_SERVER['PHP_SELF']) === 'contact.php' ? 'active' : ''; ?>">
                <i class="fas fa-envelope"></i> Contacto
            </a>
        </li>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <?php if ($_SESSION['user_type'] === 'admin'): ?>
            <li>
                <a href="/modules/admin/dashboard.php" 
                   class="<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i> Admin
                </a>
            </li>
            <?php elseif ($_SESSION['user_type'] === 'organizador'): ?>
            <li>
                <a href="/modules/admin/dashboard.php">
                    <i class="fas fa-user-tie"></i> Organizador
                </a>
            </li>
            <?php endif; ?>
        <?php endif; ?>
    </ul>
    
    <div class="user-menu">
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="user-dropdown">
                <button class="user-dropdown-toggle" id="userDropdown">
                    <div class="user-avatar">
                        <?php 
                        // Verificar si el usuario tiene foto de perfil
                        if (isset($_SESSION['user_photo']) && !empty($_SESSION['user_photo']) && 
                            file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $_SESSION['user_photo'])): 
                        ?>
                            <img src="<?php echo '/' . $_SESSION['user_photo']; ?>" 
                                 alt="<?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Usuario'; ?>"
                                 class="user-avatar-img">
                        <?php else: 
                            // Mostrar iniciales si no hay foto
                            if (isset($_SESSION['user_name']) && is_string($_SESSION['user_name'])) {
                                $name_parts = explode(' ', $_SESSION['user_name']);
                                $initials = '';
                                
                                if (count($name_parts) > 0) {
                                    // Tomar la primera letra del primer nombre
                                    $initials .= strtoupper(substr($name_parts[0], 0, 1));
                                    
                                    // Si hay apellido, tomar la primera letra
                                    if (count($name_parts) > 1) {
                                        $initials .= strtoupper(substr($name_parts[1], 0, 1));
                                    }
                                } else {
                                    // Si no hay partes, usar las primeras 2 letras del nombre
                                    $initials = strtoupper(substr($_SESSION['user_name'], 0, 2));
                                }
                                
                                echo $initials;
                            } else {
                                // Valor por defecto si no hay nombre
                                echo "U";
                            }
                        endif; 
                        ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Usuario'; ?></div>
                        <div class="user-role">
                            <?php echo isset($_SESSION['user_type']) ? ucfirst($_SESSION['user_type']) : 'Usuario'; ?>
                        </div>
                    </div>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </button>
                
                <div class="user-dropdown-menu" id="userDropdownMenu">
                    <a href="/modules/auth/profile.php" class="dropdown-item">
                        <i class="fas fa-user-circle"></i> Mi Perfil
                    </a>
                    <a href="/modules/events/certificates.php" class="dropdown-item">
                        <i class="fas fa-certificate"></i> Mis Certificados
                    </a>
                    <a href="/modules/auth/logout.php" class="dropdown-item logout">
                        <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="auth-buttons">
                <a href="/modules/auth/login.php" class="btn btn-secondary">
                    <i class="fas fa-sign-in-alt"></i> Ingresar
                </a>
                <a href="/modules/auth/register.php" class="btn btn-primary">
                    Registrarse
                </a>
            </div>
        <?php endif; ?>
    </div>
</nav>

<script>
// Toggle dropdown functionality
document.addEventListener('DOMContentLoaded', function() {
    const userDropdown = document.getElementById('userDropdown');
    const userDropdownContainer = document.querySelector('.user-dropdown');
    
    // Mobile menu functionality
    const menuToggle = document.getElementById('menuToggle');
    const navLinks = document.querySelector('.nav-links');
    
    if (menuToggle && navLinks) {
        menuToggle.addEventListener('click', function() {
            navLinks.classList.toggle('active');
            
            // Change icon
            const icon = menuToggle.querySelector('i');
            if (navLinks.classList.contains('active')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });
    }
    
    if (userDropdown && userDropdownContainer) {
        // Toggle dropdown on click
        userDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdownContainer.classList.toggle('active');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!userDropdownContainer.contains(e.target)) {
                userDropdownContainer.classList.remove('active');
            }
        });
        
        // Close dropdown on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                userDropdownContainer.classList.remove('active');
            }
        });
        
        // Close dropdown when clicking on a dropdown item
        document.querySelectorAll('.dropdown-item').forEach(item => {
            item.addEventListener('click', function() {
                userDropdownContainer.classList.remove('active');
            });
        });
    }
    
    // Close dropdown on window resize (for responsive)
    window.addEventListener('resize', function() {
        if (userDropdownContainer) {
            userDropdownContainer.classList.remove('active');
        }
    });
});
</script>
