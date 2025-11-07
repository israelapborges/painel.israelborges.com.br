<?php
// (O index.php já trata a sessão)
?>
<div class="header-controls">
    <h2><span id="ufw-status-indicator" style="font-size: 0.8em;">(…)</span> Firewall UFW</h2>
    <div style="display: flex; gap: 10px;">
        
        <button class="btn-primary" id="btn-ufw-sync" style="background-color: #ff9800; color: #fff;">
            Sincronizar com Servidor 🔄
        </button>
        
        <button class="btn-primary" id="btn-ufw-add-rule">Adicionar Regra</button>
        
        <button id="btn-ufw-refresh" title="Atualizar Lista" 
                style="font-size: 1.5rem; 
                       background: var(--card-bg); 
                       border: 1px solid var(--border-color); 
                       color: var(--text-color); 
                       padding: 8px 12px; 
                       border-radius: 8px; 
                       cursor: pointer;">🔄</button>
    </div>
</div>

<div class="dashboard" style="margin-bottom: 20px;">
    <div class="card" id="ufw-status-card">
        <h3>Status do Servidor UFW</h3>
        <p id="ufw-status-text" class="loading">Carregando...</p>
        <div class="header-controls" style="margin-top: 15px;">
             <button class="action-btn" id="btn-ufw-enable" style="background: #4CAF50; color: #fff;">Ativar</button>
             <button class="action-btn" id="btn-ufw-disable" style="background: #f44336; color: #fff;">Desativar</button>
        </div>
    </div>
</div>

<div class="card">
    <h3>Regras Salvas no Banco de Dados</h3>
    <div class="table-container-responsive">
        <table id="ufw-rules-table">
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