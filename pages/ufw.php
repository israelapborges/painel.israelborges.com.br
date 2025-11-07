<?php
// (O index.php já trata a sessão)
?>
<div class="header-controls">
    <h2><span id="ufw-status-indicator" class="status-chip">(…)</span> Firewall UFW</h2>
    <div class="header-actions">
        <button class="btn-warning" id="btn-ufw-sync">
            Sincronizar com Servidor 🔄
        </button>
        <button class="btn-primary" id="btn-ufw-add-rule">Adicionar Regra</button>
        <button id="btn-ufw-refresh" class="btn-icon" title="Atualizar Lista">🔄</button>
    </div>
</div>

<div class="dashboard">
    <div class="card" id="ufw-status-card">
        <h3>Status do Servidor UFW</h3>
        <p id="ufw-status-text" class="loading">Carregando...</p>
        <div class="action-group action-group--spaced">
             <button class="btn-success" id="btn-ufw-enable">Ativar</button>
             <button class="btn-danger" id="btn-ufw-disable">Desativar</button>
        </div>
    </div>
</div>

<div class="card">
    <h3>Regras Salvas no Banco de Dados</h3>
    <div class="table-container-responsive">
        <table id="ufw-rules-table" class="responsive-table responsive-table--ufw">
            <thead>
                <tr>
                    <th>Ação</th>
                    <th>Porta</th>
                    <th>Protocolo</th>
                    <th>Origem</th>
                    <th>Comentário</th>
                    <th>Gerenciar</th>
                </tr>
            </thead>
            <tbody id="ufw-rules-table-body">
                <tr>
                    <td colspan="6" class="loading" data-label="Status">Carregando regras...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
