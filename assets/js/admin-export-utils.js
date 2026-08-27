/**
 * assets/js/admin-export-utils.js
 * Utilities for Export and Print functionality in Admin Panel
 */

function exportData(type, format) {
    if (format === 'pdf') {
        window.print();
        return;
    }

    // Get current URL parameters to respect filters
    const params = new URLSearchParams(window.location.search);

    // Add type and format parameters
    params.set('type', type);
    params.set('format', format);

    // Redirect to export script
    // We assume the export script is located at modules/admin/export.php
    const siteUrl = window.location.origin;

    // Check if we are already in modules/admin context or close to it
    // Safest bet for relative path from modules/admin/*.php is:
    window.location.href = 'export.php?' + params.toString();
}
