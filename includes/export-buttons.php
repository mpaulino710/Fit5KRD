<?php
// includes/export-buttons.php
// Requires $export_type to be set
?>
<!-- Export Data Card -->
<div class="admin-card export-card">
    <div class="admin-card-header">
        <h3><i class="fas fa-download"></i> Exportar Datos</h3>
    </div>
    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
        <button onclick="exportData('<?php echo isset($export_type) ? $export_type : ''; ?>', 'csv')" class="admin-btn admin-btn-primary">
            <i class="fas fa-file-csv"></i> Exportar CSV
        </button>
        <button onclick="exportData('<?php echo isset($export_type) ? $export_type : ''; ?>', 'excel')" class="admin-btn admin-btn-success">
            <i class="fas fa-file-excel"></i> Exportar Excel
        </button>
        <button onclick="exportData('<?php echo isset($export_type) ? $export_type : ''; ?>', 'pdf')" class="admin-btn admin-btn-danger">
            <i class="fas fa-file-pdf"></i> Exportar PDF
        </button>
    </div>
</div>
