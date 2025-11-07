<?php
// Este arquivo é carregado via AJAX, não precisa de <head> ou <body>
?>
<div class="dashboard">

    <div class="card system-card">
        <h3>Identidade do Servidor</h3>
        <dl class="stat-list">
            <div class="stat-list-row">
                <dt>Hostname</dt>
                <dd id="system-hostname"><span class="loading">...</span></dd>
            </div>
            <div class="stat-list-row">
                <dt>Uptime</dt>
                <dd id="system-uptime"><span class="loading">...</span></dd>
            </div>
            <div class="stat-list-row">
                <dt>Kernel</dt>
                <dd id="system-kernel"><span class="loading">...</span></dd>
            </div>
            <div class="stat-list-row">
                <dt>Arquitetura</dt>
                <dd id="system-architecture"><span class="loading">...</span></dd>
            </div>
            <div class="stat-list-row">
                <dt>Último boot</dt>
                <dd id="system-last-boot"><span class="loading">...</span></dd>
            </div>
            <div class="stat-list-row">
                <dt>Última leitura</dt>
                <dd id="system-updated"><span class="loading">...</span></dd>
            </div>
        </dl>
    </div>

    <div class="card metric-card">
        <h3>CPU &amp; Load</h3>
        <div class="stat-value" id="load-percent">
            <span class="loading">...</span>
        </div>
        <div class="stat-label">
            <span id="load-avg">...</span> Load Avg / <span id="cpu-cores">...</span> Cores
        </div>
    </div>

    <div class="card metric-card">
        <h3>Memória RAM</h3>
        <div class="stat-value" id="ram-percent">
            <span class="loading">...</span>
        </div>
        <div class="stat-label">
            <span id="ram-used">...</span> MB usados de <span id="ram-total">...</span> MB
        </div>
        <div class="stat-meta-grid">
            <span>Disponível: <strong id="ram-free">...</strong> MB</span>
            <span>Swap: <strong id="swap-used">...</strong> / <strong id="swap-total">...</strong> MB (<span id="swap-percent">...</span>)</span>
        </div>
    </div>

    <div class="card metric-card">
        <h3>Disco /</h3>
        <div class="stat-value" id="disk-percent">
            <span class="loading">...</span>
        </div>
        <div class="stat-label">
            <span id="disk-used">...</span> GB usados de <span id="disk-total">...</span> GB
        </div>
    </div>

    <div class="card temperature-card">
        <h3>Temperaturas &amp; Sensores</h3>
        <ul id="temperature-list" class="temperature-list">
            <li class="temp-item muted">Coletando dados térmicos...</li>
        </ul>
    </div>

    <div class="card network-card">
        <h3>Rede</h3>
        <table class="network-table">
            <thead>
                <tr>
                    <th>Interface</th>
                    <th>Recebido</th>
                    <th>Enviado</th>
                </tr>
            </thead>
            <tbody id="network-table-body">
                <tr><td colspan="3" class="muted">Aguardando leitura...</td></tr>
            </tbody>
        </table>
        <div class="stat-meta-grid">
            <span>Total RX: <strong id="network-total-rx">...</strong></span>
            <span>Total TX: <strong id="network-total-tx">...</strong></span>
        </div>
    </div>

    <div class="card process-card">
        <h3>Processos em Destaque</h3>
        <table class="process-table">
            <thead>
                <tr>
                    <th>PID</th>
                    <th>Comando</th>
                    <th>CPU</th>
                    <th>Memória</th>
                </tr>
            </thead>
            <tbody id="process-table-body">
                <tr><td colspan="4" class="muted">Capturando processos...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="card filesystem-card">
        <h3>Volumes Montados</h3>
        <div id="filesystem-list" class="filesystem-list">
            <div class="muted">Mapeando volumes...</div>
        </div>
    </div>

    <div class="card overview-card">
        <h3>Resumo Geral</h3>
        <p>Dados atualizados em: <strong id="dashboard-overview-updated">...</strong></p>
        <div id="dashboard-overview-alerts" class="overview-alert muted">Nenhum alerta.</div>

        <div class="module-summary-grid">
            <section class="module-summary">
                <header>
                    <span>🌐 Websites</span>
                    <a class="module-link" href="#websites">Abrir</a>
                </header>
                <div class="module-metrics">
                    <span>Total: <strong id="websites-total">...</strong></span>
                    <span>Ativos: <strong id="websites-active">...</strong></span>
                    <span>Inativos: <strong id="websites-inactive">...</strong></span>
                </div>
            </section>

            <section class="module-summary">
                <header>
                    <span>💾 Backups de Sites</span>
                    <a class="module-link" href="#backups">Abrir</a>
                </header>
                <div class="module-metrics">
                    <span>Monitorados: <strong id="backups-tracked">...</strong></span>
                    <span>Com backup: <strong id="backups-with-backup">...</strong></span>
                    <span>Sem backup: <strong id="backups-without-backup">...</strong></span>
                    <span>Desatualizados: <strong id="backups-stale">...</strong></span>
                    <span>Último site: <strong id="backups-latest-site">...</strong></span>
                    <span>Quando: <strong id="backups-latest-date">...</strong></span>
                    <span>Tamanho: <strong id="backups-latest-size">...</strong></span>
                </div>
            </section>

            <section class="module-summary">
                <header>
                    <span>🗄️ Bancos de Dados</span>
                    <a class="module-link" href="#databases">Abrir</a>
                </header>
                <div class="module-metrics">
                    <span>Gerenciados: <strong id="databases-total">...</strong></span>
                    <span>Com backup: <strong id="databases-with-backup">...</strong></span>
                    <span>Sem backup: <strong id="databases-without-backup">...</strong></span>
                    <span>Desatualizados: <strong id="databases-stale">...</strong></span>
                    <span>Último DB: <strong id="databases-latest-name">...</strong></span>
                    <span>Quando: <strong id="databases-latest-date">...</strong></span>
                    <span>Tamanho: <strong id="databases-latest-size">...</strong></span>
                </div>
            </section>

            <section class="module-summary">
                <header>
                    <span>🚀 Full Backup</span>
                    <a class="module-link" href="#full_backups">Abrir</a>
                </header>
                <div class="module-metrics">
                    <span>Status: <strong id="full-backup-status">...</strong></span>
                    <span>Arquivo: <strong id="full-backup-filename">...</strong></span>
                    <span>Atualizado: <strong id="full-backup-updated">...</strong></span>
                    <span>Tamanho: <strong id="full-backup-size">...</strong></span>
                </div>
            </section>

            <section class="module-summary">
                <header>
                    <span>⏱️ Cron</span>
                    <a class="module-link" href="#cron">Abrir</a>
                </header>
                <div class="module-metrics">
                    <span>Total: <strong id="cron-total">...</strong></span>
                    <span>Ativas: <strong id="cron-active">...</strong></span>
                    <span>Inativas: <strong id="cron-inactive">...</strong></span>
                </div>
            </section>

            <section class="module-summary">
                <header>
                    <span>🛡️ Segurança (UFW)</span>
                    <a class="module-link" href="#ufw">Abrir</a>
                </header>
                <div class="module-metrics">
                    <span>Regras: <strong id="security-total-rules">...</strong></span>
                    <span>Permitir: <strong id="security-allow">...</strong></span>
                    <span>Negar: <strong id="security-deny">...</strong></span>
                </div>
            </section>

            <section class="module-summary">
                <header>
                    <span>📜 Logs</span>
                    <a class="module-link" href="#logs">Abrir</a>
                </header>
                <div class="module-metrics">
                    <span>Total: <strong id="logs-total">...</strong></span>
                    <span>Worker: <strong id="logs-task">...</strong></span>
                    <span>Arquivos: <strong id="logs-file">...</strong></span>
                </div>
            </section>

            <section class="module-summary">
                <header>
                    <span>🧰 Fila de Tarefas</span>
                    <a class="module-link" href="#terminal">Abrir</a>
                </header>
                <div class="module-metrics">
                    <span>Pendentes: <strong id="queue-pending">...</strong></span>
                    <span>Processando: <strong id="queue-processing">...</strong></span>
                    <span>Concluídas: <strong id="queue-complete">...</strong></span>
                    <span>Falhas: <strong id="queue-failed">...</strong></span>
                    <span>Arquivos pend.: <strong id="queue-file-pending">...</strong></span>
                    <span>Mais antiga: <strong id="queue-oldest">...</strong></span>
                </div>
            </section>

            <section class="module-summary">
                <header>
                    <span>⚙️ Ajustes</span>
                    <a class="module-link" href="#settings">Abrir</a>
                </header>
                <div class="module-metrics">
                    <span>Versão: <strong id="settings-version">...</strong></span>
                    <span>Coleta: <strong id="settings-generated">...</strong></span>
                </div>
            </section>
        </div>
    </div>

</div>

<script>
    if (typeof initializeDashboard === 'function') {
        initializeDashboard();
    } else {
        console.error("Função initializeDashboard() não encontrada.");
    }
</script>
