let statsInterval;

function formatNumber(value, decimals = 0) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) {
        return '—';
    }
    return Number(value).toFixed(decimals);
}

function formatPercent(value) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) {
        return '—';
    }
    return `${formatNumber(value, 1)}%`;
}

function safeText(id, value, fallback = '—') {
    const el = document.getElementById(id);
    if (el) {
        el.textContent = value !== undefined && value !== null && value !== '' ? value : fallback;
    }
}

function formatDateTime(isoValue) {
    if (!isoValue) {
        return '—';
    }
    const date = new Date(isoValue);
    if (Number.isNaN(date.getTime())) {
        return isoValue;
    }
    return date.toLocaleString();
}

function renderTemperatures(temperatures) {
    const container = document.getElementById('temperature-list');
    if (!container) return;

    if (!temperatures || temperatures.length === 0) {
        container.innerHTML = '<li class="temp-item muted">Nenhum sensor térmico identificado.</li>';
        return;
    }

    const items = temperatures.map((temp) => {
        const value = formatNumber(temp.value, 1);
        const high = temp.high ? `${formatNumber(temp.high, 0)}ºC` : '—';
        const critical = temp.critical ? `${formatNumber(temp.critical, 0)}ºC` : '—';
        let level = 'ok';
        if (temp.value >= 95) {
            level = 'danger';
        } else if (temp.value >= 80) {
            level = 'warning';
        }

        return `
            <li class="temp-item ${level}">
                <div class="temp-header">
                    <span class="temp-label">${temp.label || temp.chip || 'Sensor'}</span>
                    <span class="temp-value">${value}ºC</span>
                </div>
                <div class="temp-meta">Alerta: ${high} • Crítico: ${critical}</div>
                <div class="temp-chip">${temp.chip || ''}</div>
            </li>
        `;
    }).join('');

    container.innerHTML = items;
}

function renderNetwork(network) {
    const tbody = document.getElementById('network-table-body');
    if (!tbody) return;

    if (!network || !network.interfaces || network.interfaces.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="muted">Sem interfaces ativas.</td></tr>';
    } else {
        const rows = network.interfaces.map((iface) => `
            <tr>
                <td>${iface.name}</td>
                <td>${iface.rx_human}</td>
                <td>${iface.tx_human}</td>
            </tr>
        `).join('');
        tbody.innerHTML = rows;
    }

    safeText('network-total-rx', network ? network.total_rx_human : null);
    safeText('network-total-tx', network ? network.total_tx_human : null);
}

function renderProcesses(processes) {
    const tbody = document.getElementById('process-table-body');
    if (!tbody) return;

    if (!processes || processes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="muted">Nenhum processo destacado.</td></tr>';
        return;
    }

    const rows = processes.map((proc) => `
        <tr>
            <td>${proc.pid}</td>
            <td>${proc.command}</td>
            <td>${formatPercent(proc.cpu)}</td>
            <td>${formatPercent(proc.memory)}</td>
        </tr>
    `).join('');
    tbody.innerHTML = rows;
}

function renderFilesystem(filesystems) {
    const container = document.getElementById('filesystem-list');
    if (!container) return;

    if (!filesystems || filesystems.length === 0) {
        container.innerHTML = '<div class="muted">Nenhum volume encontrado.</div>';
        return;
    }

    const blocks = filesystems.map((fs) => {
        const percent = Number(fs.percent ?? 0);
        return `
            <div class="filesystem-item${fs.is_root ? ' root' : ''}">
                <div class="filesystem-header">
                    <span>${fs.mount}</span>
                    <span>${formatPercent(percent)}</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-bar-inner" style="width:${Math.min(percent, 100)}%"></div>
                </div>
                <div class="filesystem-meta">
                    ${formatNumber(fs.used_gb, 2)} GB / ${formatNumber(fs.total_gb, 2)} GB
                </div>
            </div>
        `;
    }).join('');

    container.innerHTML = blocks;
}

function renderAlerts(alerts, timestamp) {
    const container = document.getElementById('dashboard-overview-alerts');
    if (!container) return;

    safeText('dashboard-overview-updated', timestamp ? formatDateTime(timestamp) : '—');

    if (!alerts || alerts.length === 0) {
        container.classList.remove('has-warning');
        container.classList.add('muted');
        container.innerHTML = 'Nenhum alerta ativo. Tudo sob controle.';
        return;
    }

    container.classList.remove('muted');
    const items = alerts.map((alert) => `<li>${alert.message}</li>`).join('');
    container.innerHTML = `<ul>${items}</ul>`;
    const hasDanger = alerts.some((alert) => alert.type === 'danger');
    container.classList.toggle('has-warning', hasDanger || alerts.some((alert) => alert.type === 'warning'));
}

async function fetchSystemStats() {
    try {
        const response = await fetch(`./api/system_stats.php?t=${Date.now()}`);
        const data = await response.json();

        safeText('load-percent', formatPercent(data.load_percent));
        safeText('load-avg', formatNumber(data.load_avg, 2));
        safeText('cpu-cores', data.cpu_cores);

        safeText('ram-percent', formatPercent(data.ram_percent));
        safeText('ram-used', formatNumber(data.ram_used_mb, 0));
        safeText('ram-total', formatNumber(data.ram_total_mb, 0));
        safeText('ram-free', formatNumber(data.ram_free_mb, 0));

        safeText('swap-percent', formatPercent(data.swap_percent));
        safeText('swap-used', formatNumber(data.swap_used_mb, 0));
        safeText('swap-total', formatNumber(data.swap_total_mb, 0));

        safeText('disk-percent', formatPercent(data.disk_root_percent));
        safeText('disk-used', formatNumber(data.disk_root_gb, 2));
        safeText('disk-total', formatNumber(data.disk_root_total_gb, 2));

        safeText('system-hostname', data.hostname);
        safeText('system-uptime', data.uptime_pretty);
        safeText('system-kernel', data.kernel);
        safeText('system-architecture', data.architecture);
        safeText('system-last-boot', data.last_boot);
        safeText('system-updated', data.timestamp ? formatDateTime(data.timestamp) : '—');

        safeText('network-total-rx', data.network ? data.network.total_rx_human : null);
        safeText('network-total-tx', data.network ? data.network.total_tx_human : null);

        safeText('overview-sites', data.overview?.sites);
        safeText('overview-ftp', data.overview?.ftp);
        safeText('overview-db', data.overview?.db);

        renderTemperatures(data.temperatures);
        renderNetwork(data.network);
        renderProcesses(data.top_processes);
        renderFilesystem(data.filesystem);
        renderAlerts(data.alerts, data.timestamp);
    } catch (error) {
        console.error('Erro ao buscar estatísticas do sistema:', error);
    }
}

function initializeDashboard() {
    fetchSystemStats();
    if (statsInterval) clearInterval(statsInterval);
    statsInterval = setInterval(fetchSystemStats, 5000);
}

function clearDashboardInterval() {
    if (statsInterval) {
        clearInterval(statsInterval);
    }
}
