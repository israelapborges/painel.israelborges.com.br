<?php
// Este arquivo é carregado via AJAX
?>
<div class="card">
    <div class="header-controls">
        <h2>Tarefas Agendadas (Cron)</h2>
        <div style="display: flex; gap: 10px;">
            <button id="btnSyncCron" class="btn-primary" style="background-color: var(--accent-color);">
                Sincronizar com Servidor 🔄
            </button>
            <button id="btnAddCronJob" class="btn-primary" style="background-color: #4CAF50;">
                Adicionar Tarefa ➕
            </button>
        </div>
    </div>

    <div class="table-container-responsive">
        <table id="cron-table" class="responsive-table responsive-table--cron">
            <thead>
                <tr>
                    <th>Título</th>
                    <th>Agenda (Min/Hora/Dia/Mês/Sem)</th>
                    <th>Comando</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="cron-table-body">
                <tr>
                    <td colspan="4" class="loading" data-label="Status">A carregar tarefas agendadas...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>


