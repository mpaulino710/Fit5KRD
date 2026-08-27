<?php
// includes/admin-sidebar.php
// Sidebar para panel de administración actualizado
?>

<div class="admin-sidebar">
    <div class="logo">
        <div style="display: flex; align-items: center; justify-content: center; gap: 10px; padding: 1rem 0;">
            <i class="fas fa-running" style="font-size: 2rem; color: #ffcc80;"></i>
            <h2 style="margin: 0; font-size: 1.5rem;">Fit<span style="color: #ffcc80;">5K</span> Admin</h2>
        </div>
    </div>

    <ul class="admin-menu">
        <li>
            <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <li class="menu-section">
            Gestión
        </li>

        <?php if ($_SESSION['user_type'] === 'admin'): ?>
        <li>
            <a href="users.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Usuarios</span>
            </a>
        </li>
        <?php endif; ?>

        <li>
            <a href="contacts.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'contacts.php' ? 'active' : ''; ?>">
                <i class="fas fa-envelope"></i>
                <span>Mensajes</span>
            </a>
        </li>

        <li>
            <a href="send-bulk-email.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'send-bulk-email.php' ? 'active' : ''; ?>">
                <i class="fas fa-paper-plane"></i>
                <span>Correo Masivo</span>
            </a>
        </li>

        <li>
            <a href="events.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'events.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i>
                <span>Eventos</span>
            </a>
        </li>

        <li>
            <a href="promociones.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'promociones.php' || basename($_SERVER['PHP_SELF']) === 'crear-promocion.php' ? 'active' : ''; ?>">
                <i class="fas fa-tags"></i>
                <span>Promociones</span>
            </a>
        </li>

        <li>
            <a href="news.php" class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['news.php', 'create-news.php', 'edit-news.php']) ? 'active' : ''; ?>">
                <i class="fas fa-newspaper"></i>
                <span>Noticias / Blog</span>
            </a>
        </li>

        <li>
            <a href="ads.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'ads.php' || basename($_SERVER['PHP_SELF']) === 'create-ad.php' || basename($_SERVER['PHP_SELF']) === 'edit-ad.php' ? 'active' : ''; ?>">
                <i class="fas fa-ad"></i>
                <span>Anuncios</span>
            </a>
        </li>

        <li>
            <a href="inscriptions.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'inscriptions.php' ? 'active' : ''; ?>">
                <i class="fas fa-running"></i>
                <span>Inscripciones</span>
            </a>
        </li>

        <li>
            <a href="kits.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'kits.php' ? 'active' : ''; ?>">
                <i class="fas fa-box-open"></i>
                <span>Entrega de Kits</span>
            </a>
        </li>

        <li>
            <a href="attendance-dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'attendance-dashboard.php' || basename($_SERVER['PHP_SELF']) === 'attendance.php' ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-list"></i>
                <span>Asistencia</span>
            </a>
        </li>

        <li>
            <a href="payments.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'payments.php' ? 'active' : ''; ?>">
                <i class="fas fa-credit-card"></i>
                <span>Pagos</span>
            </a>
        </li>

        <li>
            <a href="gallery.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'gallery.php' || strpos($_SERVER['PHP_SELF'], 'album') !== false ? 'active' : ''; ?>">
                <i class="fas fa-images"></i>
                <span>Galería</span>
            </a>
        </li>

        <?php if ($_SESSION['user_type'] === 'admin'): ?>
        <li class="menu-section">
            Reportes
        </li>

        <li>
            <a href="reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Reportes</span>
            </a>
        </li>

        <li>
            <a href="analytics.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'analytics.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i>
                <span>Analíticas</span>
            </a>
        </li>
        <?php endif; ?>
        <!--
        <li class="menu-section">
            Sistema
        </li>

        <li>
            <a href="settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>Configuración</span>
            </a>
        </li>

        <li>
            <a href="logs.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'logs.php' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i>
                <span>Registros</span>
            </a>
        </li>
-->
        <li class="menu-section">
            Login user
        </li>
        <li style="margin-top: auto;">
            <a href="../auth/logout.php" style="color: #ff6b6b;">
                <i class="fas fa-sign-out-alt"></i>
                <span>Cerrar Sesión</span>
            </a>
        </li>
    </ul>
</div>
