<?php
// includes/sidebar.php
// Sidebar para panel administrativo
?>

<div class="admin-sidebar">
    <div class="logo">
        <div style="display: flex; align-items: center; gap: 10px; padding: 0 20px;">
            <i class="fas fa-running" style="font-size: 2rem; color: #ffcc80;"></i>
            <h2>Fit<span style="color: #ffcc80;">5K</span> Admin</h2>
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
            <div style="
                color: rgba(255,255,255,0.5);
                font-size: 0.8rem;
                text-transform: uppercase;
                letter-spacing: 1px;
                padding: 15px 20px 5px;
                margin-top: 10px;
            ">
                Gestión
            </div>
        </li>
        
        <li>
            <a href="users.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Usuarios</span>
            </a>
        </li>
        
        <li>
            <a href="events.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'events.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i>
                <span>Eventos</span>
            </a>
        </li>
        
        <li>
            <a href="inscripciones.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'inscripciones.php' ? 'active' : ''; ?>">
                <i class="fas fa-running"></i>
                <span>Inscripciones</span>
            </a>
        </li>
        
        <li>
            <a href="pagos.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'pagos.php' ? 'active' : ''; ?>">
                <i class="fas fa-credit-card"></i>
                <span>Pagos</span>
            </a>
        </li>
        
        <li class="menu-section">
            <div style="
                color: rgba(255,255,255,0.5);
                font-size: 0.8rem;
                text-transform: uppercase;
                letter-spacing: 1px;
                padding: 15px 20px 5px;
                margin-top: 10px;
            ">
                Reportes
            </div>
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
        
        <li class="menu-section">
            <div style="
                color: rgba(255,255,255,0.5);
                font-size: 0.8rem;
                text-transform: uppercase;
                letter-spacing: 1px;
                padding: 15px 20px 5px;
                margin-top: 10px;
            ">
                Sistema
            </div>
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
        
        <li style="margin-top: auto;">
            <a href="../auth/logout.php" style="color: #ff6b6b;">
                <i class="fas fa-sign-out-alt"></i>
                <span>Cerrar Sesión</span>
            </a>
        </li>
    </ul>
</div>