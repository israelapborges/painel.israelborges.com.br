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
        <table id="cron-table">
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

<style>
/* =========================
   Tabela Desktop
========================= */
#cron-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.95rem;
    color: #ddd;
}

#cron-table thead th {
    text-align: left;
    padding: 10px 14px;
    font-weight: 600;
    color: #aab6c4;
    border-bottom: 1px solid #2b2f3b;
}

#cron-table tbody td {
    padding: 10px 0px;
    vertical-align: middle;
    border-bottom: 1px solid #2b2f3b;
}

/* Coluna: Título */
#cron-table td:nth-child(1),
#cron-table th:nth-child(1) {
    font-weight: 600;
    white-space: nowrap;
    min-width: 140px;
}

/* Coluna: Agenda */
#cron-table td:nth-child(2),
#cron-table th:nth-child(2) {
    font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, Courier, monospace;
    font-size: 0.9em;
    color: var(--accent-color);
    text-align: center;
    min-width: 160px;
}

/* Coluna: Comando */
#cron-table td:nth-child(3),
#cron-table th:nth-child(3) {
    font-family: monospace;
    font-size: 0.9em;
    white-space: normal;
    word-break: break-word;
    line-height: 1.4em;
}

/* Coluna: Ações */
#cron-table td:nth-child(4),
#cron-table th:nth-child(4) {
    text-align: right;
    min-width: 140px;
}

/* Hover */
#cron-table tbody tr:hover {
    background-color: rgba(255, 255, 255, 0.03);
    transition: 0.2s;
}

/* =========================
   Estrutura base
========================= */
.card {
    background: #1f2230;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

.header-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.header-controls h2 {
    margin: 0;
    font-size: 1.2rem;
    color: #fff;
    border-bottom: 1px solid #2b2f3b;
    padding-bottom: 5px;
}

/* Botões */
.header-controls button {
    padding: 8px 14px;
    border-radius: 6px;
    border: none;
    color: #fff;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.2s;
}
.header-controls button:hover {
    opacity: 0.9;
}

/* =========================
   Mobile View (Cards)
========================= */
@media (max-width: 768px) {
    /* Esconde o cabeçalho da tabela */
    #cron-table thead {
        display: none;
    }

    /* Cada linha vira um card */
    #cron-table,
    #cron-table tbody,
    #cron-table tr,
    #cron-table td {
        display: block;
    }

    #cron-table tr {
        margin-bottom: 18px;
        background-color: #232637;
        border-radius: 10px;
        padding: 14px 16px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
    }

    #cron-table td {
        border: none;
        padding: 6px 0;
        word-break: break-word;
    }

    /* Rótulo acima do valor */
    #cron-table td::before {
        display: block;
        font-weight: 600;
        color: #9ca3af;
        font-size: 0.9em;
        margin-bottom: 3px;
        content: attr(data-label);
    }

    /* Rótulos */
    #cron-table td:nth-child(1)::before { content: "Título"; }
    #cron-table td:nth-child(2)::before { content: "Agenda"; }
    #cron-table td:nth-child(3)::before { content: "Comando"; }
    #cron-table td:nth-child(4)::before { content: "Ações"; }

    /* Valores */
    #cron-table td:nth-child(1),
    #cron-table td:nth-child(2),
    #cron-table td:nth-child(3) {
        color: #e6e6e6;
        line-height: 1.4em;
    }

    /* Título quebra normalmente */
    #cron-table td:nth-child(1) {
        font-weight: 600;
        white-space: normal;
        margin-bottom: 6px;
    }

    /* Agenda com boa leitura */
    #cron-table td:nth-child(2) {
        font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, Courier, monospace;
        color: var(--accent-color);
        font-size: 0.9em;
        word-spacing: 6px;
        white-space: nowrap;
    }

    /* Comando ocupa largura total */
    #cron-table td:nth-child(3) {
        font-family: monospace;
        font-size: 0.85em;
        white-space: normal;
        word-break: break-word;
        line-height: 1.4em;
        margin-top: 6px;
    }

    /* Ações centralizadas */
    #cron-table td:nth-child(4) {
        text-align: center;
        margin-top: 10px;
    }

    /* Header flex em coluna */
    .header-controls {
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
    }

    .header-controls button {
        width: 100%;
    }
}

</style>
