<?php
// Este arquivo é carregado via AJAX, não precisa de <head> ou <body>
?>
<div class="dashboard">
        
    <div class="card">
        <h3>Smooth operation</h3>
        <div class="stat-value" id="load-percent">
            <span class="loading">...</span>
        </div>
        <div class="stat-label">
            <span id="load-avg">...</span> Load Avg / <span id="cpu-cores">...</span> Cores
        </div>
    </div>

    <div class="card">
        <h3>RAM Usage</h3>
        <div class="stat-value" id="ram-percent">
            <span class="loading">...</span>
        </div>
        <div class="stat-label">
            <span id="ram-used">...</span> MB / <span id="ram-total">...</span> MB
        </div>
    </div>

    <div class="card">
        <h3>Disk /</h3>
        <div class="stat-value" id="disk-percent">
            <span class="loading">...</span>
        </div>
        <div class="stat-label">
            <span id="disk-used">...</span> GB / <span id="disk-total">...</span> GB
        </div>
    </div>

    <div class="card">
        <h3>Overview</h3>
        <p>Sites: <strong id="overview-sites">...</strong></p>
        <p>FTP: <strong id="overview-ftp">...</strong></p>
        <p>DB: <strong id="overview-db">...</strong></p>
    </div>

</div>

<script>
    if (typeof initializeDashboard === 'function') {
        initializeDashboard();
    } else {
        console.error("Função initializeDashboard() não encontrada.");
    }
</script>