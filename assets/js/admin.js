// Fit5K - Admin JavaScript File

document.addEventListener('DOMContentLoaded', function() {
    
    // Inicializar datatables
    initDataTables();
    
    // Manejo de formularios administrativos
    initAdminForms();
    
    // Gráficos y estadísticas
    initCharts();
    
    // Sistema de notificaciones
    initNotifications();
    
    // Gestión de archivos
    initFileUploads();
    
    // Filtros avanzados
    initAdvancedFilters();
    
    // Exportación de datos
    initExportFunctions();
});

// DataTables
function initDataTables() {
    const tables = document.querySelectorAll('.admin-table');
    
    tables.forEach(table => {
        // Agregar funcionalidad básica de ordenamiento
        const headers = table.querySelectorAll('th[data-sortable]');
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', function() {
                const column = this.cellIndex;
                const isAsc = this.classList.contains('sort-asc');
                
                // Remover clases de ordenamiento
                headers.forEach(h => {
                    h.classList.remove('sort-asc', 'sort-desc');
                });
                
                // Aplicar nueva clase
                this.classList.toggle('sort-asc', !isAsc);
                this.classList.toggle('sort-desc', isAsc);
                
                // Ordenar tabla
                sortTable(table, column, !isAsc);
            });
        });
    });
}

function sortTable(table, column, ascending = true) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        const aValue = a.cells[column].textContent.trim();
        const bValue = b.cells[column].textContent.trim();
        
        // Intentar convertir a número si es posible
        const aNum = parseFloat(aValue.replace(/[^0-9.-]+/g, ''));
        const bNum = parseFloat(bValue.replace(/[^0-9.-]+/g, ''));
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return ascending ? aNum - bNum : bNum - aNum;
        }
        
        // Ordenar como texto
        return ascending 
            ? aValue.localeCompare(bValue)
            : bValue.localeCompare(aValue);
    });
    
    // Reordenar filas
    rows.forEach(row => tbody.appendChild(row));
}

// Formularios administrativos
function initAdminForms() {
    // Validación avanzada
    const adminForms = document.querySelectorAll('.admin-form');
    
    adminForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (validateAdminForm(this)) {
                // Mostrar loading
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
                submitBtn.disabled = true;
                
                // Enviar formulario
                const formData = new FormData(this);
                
                fetch(this.action, {
                    method: this.method,
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAdminNotification(data.message || 'Guardado exitosamente', 'success');
                        if (data.redirect) {
                            setTimeout(() => {
                                window.location.href = data.redirect;
                            }, 1500);
                        }
                    } else {
                        showAdminNotification(data.message || 'Error al guardar', 'error');
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                })
                .catch(error => {
                    showAdminNotification('Error de conexión', 'error');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
            }
        });
    });
    
    // Selectores de fecha y hora
    const dateTimeInputs = document.querySelectorAll('.datetime-picker');
    dateTimeInputs.forEach(input => {
        flatpickr(input, {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            time_24hr: true,
            locale: "es"
        });
    });
}

function validateAdminForm(form) {
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            showFieldError(field, 'Este campo es obligatorio');
            isValid = false;
        } else {
            clearFieldError(field);
        }
        
        // Validación específica por tipo
        if (field.type === 'email' && field.value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(field.value)) {
                showFieldError(field, 'Email inválido');
                isValid = false;
            }
        }
        
        if (field.type === 'number' && field.value) {
            const min = parseFloat(field.min);
            const max = parseFloat(field.max);
            const value = parseFloat(field.value);
            
            if (!isNaN(min) && value < min) {
                showFieldError(field, `El valor mínimo es ${min}`);
                isValid = false;
            }
            
            if (!isNaN(max) && value > max) {
                showFieldError(field, `El valor máximo es ${max}`);
                isValid = false;
            }
        }
    });
    
    return isValid;
}

function showFieldError(field, message) {
    field.classList.add('is-invalid');
    let errorDiv = field.nextElementSibling;
    
    if (!errorDiv || !errorDiv.classList.contains('invalid-feedback')) {
        errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback';
        field.parentNode.appendChild(errorDiv);
    }
    
    errorDiv.textContent = message;
}

function clearFieldError(field) {
    field.classList.remove('is-invalid');
    const errorDiv = field.nextElementSibling;
    if (errorDiv && errorDiv.classList.contains('invalid-feedback')) {
        errorDiv.textContent = '';
    }
}

// Gráficos
function initCharts() {
    const chartContainers = document.querySelectorAll('.chart-container');
    
    chartContainers.forEach(container => {
        const ctx = container.querySelector('canvas');
        if (!ctx) return;
        
        const chartType = container.dataset.chartType || 'line';
        const chartData = JSON.parse(container.dataset.chartData || '{}');
        
        new Chart(ctx.getContext('2d'), {
            type: chartType,
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });
    });
}

// Notificaciones administrativas
function initNotifications() {
    // Mostrar notificaciones pendientes
    checkPendingNotifications();
    
    // Sistema de notificaciones en tiempo real
    if (typeof EventSource !== 'undefined') {
        const eventSource = new EventSource('/admin/api/notifications/stream');
        
        eventSource.onmessage = function(event) {
            const notification = JSON.parse(event.data);
            showAdminNotification(notification.message, notification.type);
        };
        
        eventSource.onerror = function() {
            console.error('Error en conexión SSE');
        };
    }
}

function checkPendingNotifications() {
    fetch('/admin/api/notifications/pending')
        .then(response => response.json())
        .then(notifications => {
            notifications.forEach(notification => {
                showAdminNotification(notification.message, notification.type);
            });
        });
}

function showAdminNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `admin-notification admin-notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${getNotificationIcon(type)}"></i>
            <span>${message}</span>
            <button class="notification-close">&times;</button>
        </div>
    `;
    
    document.querySelector('.admin-main').appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    // Cerrar notificación
    notification.querySelector('.notification-close').addEventListener('click', () => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    });
    
    // Auto cerrar después de 5 segundos
    setTimeout(() => {
        if (notification.parentNode) {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }
    }, 5000);
}

function getNotificationIcon(type) {
    const icons = {
        'success': 'check-circle',
        'error': 'exclamation-circle',
        'warning': 'exclamation-triangle',
        'info': 'info-circle'
    };
    return icons[type] || 'info-circle';
}

// Upload de archivos
function initFileUploads() {
    const fileInputs = document.querySelectorAll('.file-upload');
    
    fileInputs.forEach(input => {
        const preview = input.nextElementSibling;
        
        input.addEventListener('change', function() {
            const file = this.files[0];
            if (!file) return;
            
            // Validar tipo de archivo
            const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            if (!validTypes.includes(file.type)) {
                showAdminNotification('Tipo de archivo no permitido', 'error');
                this.value = '';
                return;
            }
            
            // Validar tamaño (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                showAdminNotification('El archivo no debe superar 5MB', 'error');
                this.value = '';
                return;
            }
            
            // Mostrar preview para imágenes
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `
                        <img src="${e.target.result}" alt="Preview" style="max-width: 200px; max-height: 200px;">
                        <button type="button" class="btn btn-sm btn-danger mt-2 remove-image">
                            <i class="fas fa-trash"></i> Eliminar
                        </button>
                    `;
                    
                    preview.querySelector('.remove-image').addEventListener('click', () => {
                        input.value = '';
                        preview.innerHTML = '';
                    });
                };
                reader.readAsDataURL(file);
            }
        });
    });
}

// Filtros avanzados
function initAdvancedFilters() {
    const filterForms = document.querySelectorAll('.advanced-filter');
    
    filterForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            applyAdvancedFilters(this);
        });
        
        // Resetear filtros
        const resetBtn = form.querySelector('.reset-filters');
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                form.reset();
                applyAdvancedFilters(form);
            });
        }
    });
}

function applyAdvancedFilters(form) {
    const formData = new FormData(form);
    const params = new URLSearchParams();
    
    for (const [key, value] of formData.entries()) {
        if (value) params.append(key, value);
    }
    
    // Actualizar URL y recargar datos
    const url = new URL(window.location);
    url.search = params.toString();
    window.history.pushState({}, '', url);
    
    // Recargar datos de la tabla
    reloadTableData(params);
}

async function reloadTableData(params) {
    const table = document.querySelector('.admin-table');
    if (!table) return;
    
    const loading = document.createElement('div');
    loading.className = 'table-loading';
    loading.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cargando...';
    table.parentNode.appendChild(loading);
    
    try {
        const response = await fetch(`/admin/api/data?${params}`);
        const data = await response.json();
        
        // Actualizar tabla con nuevos datos
        updateTable(data);
    } catch (error) {
        showAdminNotification('Error al cargar datos', 'error');
    } finally {
        loading.remove();
    }
}

function updateTable(data) {
    const tbody = document.querySelector('.admin-table tbody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    data.rows.forEach(row => {
        const tr = document.createElement('tr');
        row.forEach(cell => {
            const td = document.createElement('td');
            td.innerHTML = cell;
            tr.appendChild(td);
        });
        tbody.appendChild(tr);
    });
}

// Exportación
function initExportFunctions() {
    const exportBtns = document.querySelectorAll('.btn-export');
    
    exportBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const format = this.dataset.format || 'csv';
            const tableId = this.dataset.table;
            
            exportTableData(tableId, format);
        });
    });
}

function exportTableData(tableId, format) {
    const table = document.getElementById(tableId) || document.querySelector('.admin-table');
    if (!table) return;
    
    let data, mimeType, filename;
    
    if (format === 'csv') {
        data = tableToCSV(table);
        mimeType = 'text/csv';
        filename = 'datos.csv';
    } else if (format === 'excel') {
        data = tableToExcel(table);
        mimeType = 'application/vnd.ms-excel';
        filename = 'datos.xls';
    }
    
    const blob = new Blob([data], { type: mimeType });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function tableToCSV(table) {
    const rows = table.querySelectorAll('tr');
    const csv = [];
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('th, td');
        const rowData = [];
        
        cells.forEach(cell => {
            let cellData = cell.textContent.trim();
            
            // Escapar comillas y comas para CSV
            cellData = cellData.replace(/"/g, '""');
            if (cellData.includes(',') || cellData.includes('"') || cellData.includes('\n')) {
                cellData = `"${cellData}"`;
            }
            
            rowData.push(cellData);
        });
        
        csv.push(rowData.join(','));
    });
    
    return csv.join('\n');
}

function tableToExcel(table) {
    // Implementación básica para Excel
    return table.outerHTML;
}

// Funciones utilitarias administrativas
function confirmDelete(message = '¿Estás seguro de eliminar este registro?') {
    return confirm(message);
}

function toggleSidebar() {
    document.querySelector('.admin-sidebar').classList.toggle('collapsed');
    document.querySelector('.admin-main').classList.toggle('expanded');
}

// Estilos para admin
const adminStyle = document.createElement('style');
adminStyle.textContent = `
    .table-loading {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255,255,255,0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 100;
    }
    
    .admin-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transform: translateX(100%);
        transition: transform 0.3s;
        z-index: 10000;
        max-width: 350px;
    }
    
    .admin-notification.show {
        transform: translateX(0);
    }
    
    .notification-content {
        display: flex;
        align-items: center;
        padding: 15px;
        gap: 10px;
    }
    
    .notification-content i {
        font-size: 1.2rem;
    }
    
    .admin-notification-success .notification-content i {
        color: var(--admin-success);
    }
    
    .admin-notification-error .notification-content i {
        color: var(--admin-danger);
    }
    
    .admin-notification-warning .notification-content i {
        color: var(--admin-warning);
    }
    
    .admin-notification-info .notification-content i {
        color: var(--admin-info);
    }
    
    .notification-content span {
        flex: 1;
    }
    
    .notification-close {
        background: none;
        border: none;
        color: #999;
        cursor: pointer;
        font-size: 1.2rem;
        padding: 0;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .admin-sidebar.collapsed {
        width: 70px;
    }
    
    .admin-sidebar.collapsed .logo h2 span,
    .admin-sidebar.collapsed .admin-menu a span {
        display: none;
    }
    
    .admin-main.expanded {
        margin-left: 70px;
    }
    
    .is-invalid {
        border-color: var(--admin-danger) !important;
    }
    
    .is-valid {
        border-color: var(--admin-success) !important;
    }
    
    .invalid-feedback {
        color: var(--admin-danger);
        font-size: 0.875rem;
        margin-top: 0.25rem;
    }
`;
document.head.appendChild(adminStyle);