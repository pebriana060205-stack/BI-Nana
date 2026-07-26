// ============================================================
//  app.js — Global JavaScript untuk Online Retail BI
// ============================================================

document.addEventListener('DOMContentLoaded', function () {

    // ---------------------------------------------------------
    //  Aktifkan nav-item berdasarkan URL parameter
    // ---------------------------------------------------------
    const params  = new URLSearchParams(window.location.search);
    const curPage = params.get('page') || 'dashboard';
    const navLink = document.getElementById('nav-' + curPage);
    if (navLink) navLink.classList.add('active');

    // ---------------------------------------------------------
    //  Chart.js Global Defaults (Dark Mode)
    // ---------------------------------------------------------
    if (window.Chart) {
        Chart.defaults.color          = '#94a3b8';
        Chart.defaults.font.family    = "'Inter', sans-serif";
        Chart.defaults.font.size      = 12;
        Chart.defaults.plugins.tooltip.backgroundColor = '#1e2535';
        Chart.defaults.plugins.tooltip.borderColor      = '#2a3349';
        Chart.defaults.plugins.tooltip.borderWidth      = 1;
        Chart.defaults.plugins.tooltip.titleColor       = '#f1f5f9';
        Chart.defaults.plugins.tooltip.bodyColor        = '#94a3b8';
        Chart.defaults.plugins.tooltip.padding          = 10;
    }

    // ---------------------------------------------------------
    //  DataTables Global Style Fix (Dark Mode override)
    // ---------------------------------------------------------
    if (window.$.fn && window.$.fn.dataTable) {
        const style = document.createElement('style');
        style.textContent = `
            .dataTables_wrapper .dataTables_filter input,
            .dataTables_wrapper .dataTables_length select {
                background: var(--bg-primary) !important;
                border: 1px solid var(--border-color) !important;
                color: var(--text-primary) !important;
                border-radius: 6px !important;
                padding: 6px 10px !important;
                margin-left: 6px !important;
            }
            .dataTables_wrapper .dataTables_info,
            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter { color: var(--text-muted) !important; font-size: .78rem !important; }
            .dataTables_wrapper .dataTables_paginate .paginate_button {
                color: var(--text-secondary) !important;
                background: var(--bg-card) !important;
                border: 1px solid var(--border-color) !important;
                border-radius: 6px !important;
                margin: 0 2px !important;
                padding: 4px 10px !important;
                cursor: pointer !important;
            }
            .dataTables_wrapper .dataTables_paginate .paginate_button.current,
            .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
                background: var(--accent-teal) !important;
                color: white !important;
                border-color: var(--accent-teal) !important;
            }
            table.dataTable thead th { border-bottom: 1px solid var(--border-color) !important; }
            table.dataTable tbody tr.odd  { background: transparent !important; }
            table.dataTable tbody tr.even { background: rgba(255,255,255,.01) !important; }
        `;
        document.head.appendChild(style);
    }

    // ---------------------------------------------------------
    //  Toast Notification System
    // ---------------------------------------------------------
    window.showToast = function(message, type = 'info') {
        const toast = document.createElement('div');
        const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
        const colors = {
            success: 'rgba(16,185,129,.15)',
            error:   'rgba(244,63,94,.15)',
            warning: 'rgba(245,158,11,.15)',
            info:    'rgba(99,102,241,.15)',
        };
        toast.style.cssText = `
            position:fixed;bottom:24px;right:24px;z-index:9999;
            background:${colors[type]};border:1px solid var(--border-color);
            color:var(--text-primary);padding:14px 20px;border-radius:12px;
            font-size:.875rem;box-shadow:var(--shadow-lg);
            transform:translateY(20px);opacity:0;
            transition:all .3s cubic-bezier(.4,0,.2,1);
            display:flex;align-items:center;gap:10px;
            backdrop-filter:blur(10px);max-width:360px;
        `;
        toast.innerHTML = `<span>${icons[type]}</span><span>${message}</span>`;
        document.body.appendChild(toast);
        requestAnimationFrame(() => {
            toast.style.transform = 'translateY(0)';
            toast.style.opacity   = '1';
        });
        setTimeout(() => {
            toast.style.transform = 'translateY(20px)';
            toast.style.opacity   = '0';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    };

    // ---------------------------------------------------------
    //  Format angka currency di halaman
    // ---------------------------------------------------------
    window.formatGBP = (val) => {
        if (val >= 1e6) return '£' + (val/1e6).toFixed(2) + 'M';
        if (val >= 1000) return '£' + (val/1000).toFixed(1) + 'K';
        return '£' + val.toFixed(2);
    };

    // ---------------------------------------------------------
    //  Animasi angka KPI saat halaman load
    // ---------------------------------------------------------
    const kpiValues = document.querySelectorAll('.kpi-value');
    kpiValues.forEach(el => {
        const text = el.textContent.trim();
        // Hanya animasi jika konten berupa angka sederhana
        const num = parseFloat(text.replace(/[£,KM%]/g, ''));
        if (!isNaN(num) && num > 0) {
            let start = 0;
            const duration = 800;
            const startTime = performance.now();
            const update = (time) => {
                const elapsed  = time - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const eased    = 1 - Math.pow(1 - progress, 3);
                el.textContent = text.replace(num.toString(), Math.round(num * eased).toLocaleString());
                if (progress < 1) requestAnimationFrame(update);
                else el.textContent = text;
            };
            requestAnimationFrame(update);
        }
    });

});
