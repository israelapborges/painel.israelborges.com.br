let statsInterval;

async function fetchSystemStats() {
    try {
        const response = await fetch('./api/system_stats.php?t=' + new Date().getTime());
        const data = await response.json();
        if (document.getElementById('load-percent')) {
            document.getElementById('load-percent').textContent = data.load_percent + '%';
            document.getElementById('load-avg').textContent = data.load_avg;
            document.getElementById('cpu-cores').textContent = data.cpu_cores;
            document.getElementById('ram-percent').textContent = data.ram_percent + '%';
            document.getElementById('ram-used').textContent = data.ram_used_mb;
            document.getElementById('ram-total').textContent = data.ram_total_mb;
            document.getElementById('disk-percent').textContent = data.disk_root_percent + '%';
            document.getElementById('disk-used').textContent = data.disk_root_gb;
            document.getElementById('disk-total').textContent = data.disk_root_total_gb;
            document.getElementById('overview-sites').textContent = data.overview.sites;
            document.getElementById('overview-ftp').textContent = data.overview.ftp;
            document.getElementById('overview-db').textContent = data.overview.db;
        }
    } catch (error) {
        console.error('Erro ao buscar estatísticas do sistema:', error);
    }
}

// Esta será a função chamada pelo index.php
function initializeDashboard() {
    fetchSystemStats();
    if (statsInterval) clearInterval(statsInterval); 
    statsInterval = setInterval(fetchSystemStats, 5000);
}

// Esta será chamada pelo index.php ao sair da página
function clearDashboardInterval() {
    if (statsInterval) {
        clearInterval(statsInterval);
    }
}