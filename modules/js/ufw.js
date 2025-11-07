// --- MÓDULO UFW (Atualizado com Sincronização DB) ---

let ufwRuleModal, closeUfwModalBtn, ufwRuleForm, ufwFormFeedback, ufwSubmitBtn;

// 1. Função de Inicialização (chamada pelo roteador)
function initializeUfwPage() {
    loadUfwStatus(); // Carrega o status do servidor E as regras do DB

    // Mapeia os elementos do Modal
    ufwRuleModal = document.getElementById('ufwRuleModal');
    closeUfwModalBtn = document.getElementById('closeUfwModalBtn');
    ufwRuleForm = document.getElementById('ufwRuleForm');
    ufwFormFeedback = document.getElementById('ufw-form-feedback');
    ufwSubmitBtn = document.getElementById('ufw-submit-btn');

    // Listeners do Modal
    if (closeUfwModalBtn) closeUfwModalBtn.addEventListener('click', closeUfwModal);
    if (ufwRuleModal) ufwRuleModal.addEventListener('click', (e) => { if (e.target === ufwRuleModal) closeUfwModal(); });
    
    // Listener do Formulário (agora salva no DB)
    if (ufwRuleForm) ufwRuleForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        ufwSubmitBtn.disabled = true;
        ufwSubmitBtn.textContent = 'Aguarde...';
        ufwFormFeedback.textContent = 'Salvando no banco de dados...';
        ufwFormFeedback.style.color = 'var(--text-muted)';

        const formData = new FormData(ufwRuleForm);
        const payload = {
            action: 'add_db_rule', // MUDOU AQUI
            rule_action: formData.get('action'),
            port: formData.get('port'),
            protocol: formData.get('protocol'),
            source: formData.get('source') || 'any',
            comment: formData.get('comment') || null
        };

        try {
            const response = await fetch('./api/ufw_variaveis.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            if(response.status === 403) { window.location.href = 'login.php'; return; }
            
            const result = await response.json();
            if (result.success) {
                ufwFormFeedback.style.color = '#4CAF50';
                ufwFormFeedback.textContent = result.message;
                setTimeout(() => {
                    closeUfwModal();
                    loadUfwStatus(); // Atualiza a lista de regras
                }, 1500);
            } else {
                throw new Error(result.error);
            }
        } catch (error) {
            ufwFormFeedback.style.color = '#f44336';
            ufwFormFeedback.textContent = 'Erro: ' + error.message;
            ufwSubmitBtn.disabled = false;
            ufwSubmitBtn.textContent = 'Adicionar Regra';
        }
    });
}

// 2. Funções de Abrir/Fechar Modal (Sem alteração)
function openUfwModal() {
    if (ufwRuleModal) {
        ufwRuleForm.reset();
        ufwFormFeedback.textContent = '';
        ufwSubmitBtn.disabled = false;
        ufwSubmitBtn.textContent = 'Adicionar Regra';
        ufwRuleModal.classList.add('show');
        document.getElementById('ufw_port').focus();
    }
}
function closeUfwModal() {
    if (ufwRuleModal) ufwRuleModal.classList.remove('show');
}

// 3. Função para Carregar Status (Servidor) e Regras (DB)
async function loadUfwStatus() {
    const tableBody = document.getElementById('ufw-rules-table-body');
    const statusCard = document.getElementById('ufw-status-card');
    const statusText = document.getElementById('ufw-status-text');
    const statusIndicator = document.getElementById('ufw-status-indicator');

    if (!tableBody || !statusText) return;

    // Feedback de carregamento
    // MUDANÇA: Adicionada class="table-placeholder-row" ao <tr>
    tableBody.innerHTML = `<tr class="table-placeholder-row"><td colspan="6" class="loading" data-label="Status">Carregando...</td></tr>`;
    statusText.textContent = 'Carregando...';
    statusText.className = 'loading';
    statusCard.className = 'card';
    statusIndicator.textContent = '(…)';

    try {
        // MUDOU AQUI: agora chama 'get_db_rules'
        const response = await fetch(`./api/ufw_variaveis.php?action=get_db_rules&t=${new Date().getTime()}`);
        if(response.status === 403) { window.location.href = 'login.php'; return; }
        
        const data = await response.json();
        if (!data.success) throw new Error(data.error);

        // Atualiza o Card de Status (do Servidor)
        if (data.status === 'active') {
            statusText.textContent = 'ATIVO';
            statusCard.classList.add('status-active');
            statusIndicator.textContent = '🟢';
        } else if (data.status === 'inactive') {
            statusText.textContent = 'INATIVO';
            statusCard.classList.add('status-inactive');
            statusIndicator.textContent = '🔴';
        } else {
            statusText.textContent = 'Desconhecido';
            statusIndicator.textContent = '⚪';
        }

        // Popula a Tabela de Regras (do DB)
        tableBody.innerHTML = '';
        if (data.rules.length === 0) {
            // MUDANÇA: Adicionada class="table-placeholder-row" ao <tr>
            tableBody.innerHTML = `<tr class="table-placeholder-row"><td colspan="6" style="text-align: center;">Nenhuma regra salva no banco de dados.</td></tr>`;
        } else {
            data.rules.forEach(rule => {
                const statusClass = `rule-status-${String(rule.action).toUpperCase()}`;
                // Este <tr> (o de dados reais) NÃO tem a classe
                const row = `
                    <tr>
                        <td data-label="Ação"><span class="${statusClass}">${String(rule.action).toUpperCase()}</span></td>
                        <td data-label="Porta">${rule.port}</td>
                        <td data-label="Protocolo">${rule.protocol}</td>
                        <td data-label="Origem">${rule.source}</td>
                        <td data-label="Comentário">${rule.comment || '--'}</td>
                        <td data-label="Gerenciar">
                            <button class="action-btn btn-delete-ufw-rule" 
                                    data-id="${rule.id}" 
                                    style="color: #f44336;">Excluir</button>
                        </td>
                    </tr>
                `;
                tableBody.innerHTML += row;
            });
        }

    } catch (error) {
        console.error('Erro ao carregar UFW:', error);
        tableBody.innerHTML = `<tr><td colspan="6" style="color:red;text-align:center;">Erro ao carregar: ${error.message}</td></tr>`;
        statusText.textContent = 'Erro!';
    }
}

// 4. Função para Ativar/Desativar (Controla o servidor, sem alteração)
async function toggleUfw(toggleAction) { // 'enable' or 'disable'
    const btn = (toggleAction === 'enable') ? document.getElementById('btn-ufw-enable') : document.getElementById('btn-ufw-disable');
    if (!btn) return;
    
    const confirmMsg = (toggleAction === 'enable') 
        ? 'Tem certeza que deseja ATIVAR o firewall?\n\nIsso NÃO sincroniza as regras, apenas ativa o UFW.'
        : 'Tem certeza que deseja DESATIVAR o firewall?';

    if (!confirm(confirmMsg)) return;

    btn.disabled = true;
    btn.textContent = 'Aguarde...';

    try {
        const payload = { action: 'toggle', toggle: toggleAction };
        const response = await fetch('./api/ufw_variaveis.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        if(response.status === 403) { window.location.href = 'login.php'; return; }
        
        const result = await response.json();
        if (result.success) {
            alert(result.message);
            loadUfwStatus(); // Atualiza o status
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        alert('Erro ao ' + toggleAction + ' o firewall: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.textContent = (toggleAction === 'enable') ? 'Ativar' : 'Desativar';
    }
}

// 5. Função para Excluir Regra (agora do DB)
async function deleteUfwRule(ruleId) {
    if (!confirm(`Tem certeza que deseja excluir a regra ID [${ruleId}] do banco de dados?\n\nSincronize o servidor para aplicar a mudança.`)) {
        return;
    }

    const btn = document.querySelector(`.btn-delete-ufw-rule[data-id="${ruleId}"]`);
    if(btn) btn.disabled = true;

    try {
        // MUDOU AQUI: 'delete_db_rule'
        const payload = { action: 'delete_db_rule', id: ruleId };
        const response = await fetch('./api/ufw_variaveis.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        if(response.status === 403) { window.location.href = 'login.php'; return; }
        
        const result = await response.json();
        if (result.success) {
            alert(result.message);
            loadUfwStatus(); // Atualiza a lista
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        alert('Erro ao excluir regra: ' + error.message);
        if(btn) btn.disabled = false;
    }
}

// 6. NOVA FUNÇÃO: Sincronizar DB -> Servidor
async function syncUfwToServer() {
    const confirmMsg = "TEM CERTEZA?\n\nIsso irá:\n1. RESETAR (apagar) TODAS as regras ativas no servidor.\n2. APLICAR todas as regras salvas no banco de dados.\n3. ATIVAR o firewall.\n\nÉ recomendado ter uma regra 'ALLOW 22/tcp' salva para não perder o acesso SSH.";
    if (!confirm(confirmMsg)) {
        return;
    }

    const btn = document.getElementById('btn-ufw-sync');
    if(btn) {
        btn.disabled = true;
        btn.textContent = 'Sincronizando...';
    }

    try {
        const payload = { action: 'sync_to_server' };
        const response = await fetch('./api/ufw_variaveis.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        if(response.status === 403) { window.location.href = 'login.php'; return; }
        
        const result = await response.json();
        if (result.success) {
            alert(result.message);
            console.log("Saída da Sincronização:", result.raw_output);
            loadUfwStatus(); // Recarrega o status do servidor
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        alert('Erro na Sincronização: ' + error.message);
    } finally {
        if(btn) {
            btn.disabled = false;
            btn.textContent = 'Sincronizar com Servidor 🔄';
        }
    }
}
// --- FIM DO MÓDULO UFW ---