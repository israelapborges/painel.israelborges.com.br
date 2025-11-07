<?php
// Este arquivo é carregado via AJAX, não precisa de <head> ou <body>
?>
<div class="dashboard">

    <div class="card">
        <h3>CPU &amp; Load</h3>
        <div class="stat-value" id="load-percent">
            <span class="loading">...</span>
        </div>
        <div class="stat-label">
            <span id="load-avg">...</span> Load Avg / <span id="cpu-cores">...</span> Cores
        </div>
    </div>

    <div class="card">
        <h3>Memória RAM</h3>
        <div class="stat-value" id="ram-percent">
            <span class="loading">...</span>
        </div>
        <div class="stat-label">
            <span id="ram-used">...</span> MB / <span id="ram-total">...</span> MB
        </div>
    </div>

    <div class="card">
        <h3>Disco /</h3>
        <div class="stat-value" id="disk-percent">
            <span class="loading">...</span>
        </div>
        <div class="stat-label">
            <span id="disk-used">...</span> GB / <span id="disk-total">...</span> GB
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