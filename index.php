<?php
// INÍCIO DA PROTEÇÃO:
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
// FIM DA PROTEÇÃO

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'config/db.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyPanel - Dashboard</title>
    
    <link rel="stylesheet" href="css/style.css?t=<?php echo time(); ?>">
    <link rel="stylesheet" href="modules/lib/xterm/xterm.min.css">
    <link rel="stylesheet" href="modules/css/terminal.css?t=<?php echo time(); ?>">
    <link rel="stylesheet" href="modules/css/ufw.css?t=<?php echo time(); ?>">


    <style>
        .action-link {
            display: inline-block;
            background: var(--bg-color);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 5px 10px;
            border-radius: 4px;
            margin-right: 5px;
            text-decoration: none;
            transition: background 0.2s, color 0.2s;
            font-size: 0.9em;
        }
        .action-link:hover {
            background: var(--border-color);
            color: var(--text-color);
        }
        .action-link.disabled {
            color: var(--border-color);
            background: var(--bg-color);
            pointer-events: none;
            opacity: 0.5;
        }
        #files-table .action-btn,
        #files-table .action-link {
            min-width: 90px;  /* Define a largura mínima que você sugeriu */
            text-align: center; /* Centraliza o texto (Editar, Download) */
            box-sizing: border-box; /* Garante que o padding não afete a largura */
        }
    </style>
</head>
<body>

    <div class="layout-container">
        
        <aside class="sidebar">
            <div class="logo">MyPanel</div>
            <nav>
                <ul>
                    <li><a href="#dashboard" class="nav-link active" data-page="dashboard">🏠 Home</a></li>
                    <li><a href="#websites" class="nav-link" data-page="websites">🌐 Website</a></li>
                    <li><a href="#full_backups" class="nav-link" data-page="full_backups">🚀 Full Backup</a></li>
                    <li><a href="#backups" class="nav-link" data-page="backups">💾 Backups</a></li>
                    <li><a href="#databases" class="nav-link" data-page="databases">🗄️ Databases</a></li>
                    <li><a href="#monitor" class="nav-link" data-page="monitor">📈 Monitor</a></li>
                    <li><a href="#ufw" class="nav-link" data-page="ufw">🛡️ Security</a></li>
                    <li><a href="#files" class="nav-link" data-page="files">🗂️ Arquivos</a></li>
                    <li><a href="#terminal" class="nav-link" data-page="terminal">🖥️ Terminal</a></li>
                    <li><a href="#logs" class="nav-link" data-page="logs">📜 Logs</a></li>
                    <li><a href="#cron" class="nav-link" data-page="cron">⏱️ Cron</a></li>
                    <li><a href="#settings" class="nav-link" data-page="settings">⚙️ Ajustes</a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <a href="logout.php">🚪 Sair</a>
            </div>
        </aside>

        <div class="main-content-wrapper">
            
            <header class="header">
                <h1 id="page-title">Dashboard</h1> 
                <button id="mobile-menu-toggle" title="Abrir menu">☰</button>
                
                <div class="header-button-group" style="display: flex; gap: 10px;">
                    <button id="run-worker-btn" title="Forçar execução do Worker" 
                            style="font-size: 1.5rem; 
                                   background: var(--card-bg); 
                                   border: 1px solid var(--border-color); 
                                   color: var(--text-color); 
                                   padding: 8px 12px; 
                                   border-radius: 8px; 
                                   cursor: pointer;">⚡</button>
                                   
                    <button id="theme-toggle" title="Alternar tema">🌙</button>
                </div>
            </header>

            <main id="main-content">
                <span class="loading">Carregando...</span>
            </main>
            
        </div>
    </div>

    <?php
        // Inclui os modais
        if (file_exists('modules/modals/website-modal.php')) {
            require 'modules/modals/website-modal.php';
        }
        if (file_exists('modules/modals/nginx-config-modal.php')) {
            require 'modules/modals/nginx-config-modal.php';
        }
        if (file_exists('modules/modals/database-modal.php')) {
            require 'modules/modals/database-modal.php';
        }
		if (file_exists('modules/modals/restore-modal.php')) {
            require 'modules/modals/restore-modal.php';
        }
        if (file_exists('modules/modals/restore-site-modal.php')) {
            require 'modules/modals/restore-site-modal.php';
        }
        if (file_exists('modules/modals/link-db-modal.php')) {
            require 'modules/modals/link-db-modal.php';
        }
        if (file_exists('modules/modals/file-editor-modal.php')) {
    require 'modules/modals/file-editor-modal.php';
        }
        if (file_exists('modules/modals/upload-file-modal.php')) { // <-- ADICIONE ISTO
        require 'modules/modals/upload-file-modal.php';
        }
        if (file_exists('modules/modals/image-viewer-modal.php')) { // <-- ADICIONE ISTO
        require 'modules/modals/image-viewer-modal.php';
        }
        if (file_exists('modules/ui/context-menu.php')) {
            require 'modules/ui/context-menu.php';
        }
        if (file_exists('modules/modals/rename-modal.php')) {
        require 'modules/modals/rename-modal.php';
        }
        if (file_exists('modules/modals/compress-modal.php')) {
        require 'modules/modals/compress-modal.php';
        }
        if (file_exists('modules/modals/permissions-modal.php')) {
        require 'modules/modals/permissions-modal.php';
        }
        if (file_exists('modules/modals/create-new-modal.php')) {
        require 'modules/modals/create-new-modal.php';
        }
        if (file_exists('modules/modals/cron-modal.php')) {
        require 'modules/modals/cron-modal.php';
        }
        if (file_exists('modules/modals/ufw-modal.php')) {
                require 'modules/modals/ufw-modal.php';
        }
    ?>
    
    <div class="mobile-backdrop" id="mobile-backdrop"></div>

    <script>
        // --- 1. LÓGICA DO TEMA (GLOBAL) ---
        const themeToggle = document.getElementById('theme-toggle');
        const body = document.body;
        function applyTheme(theme) {
            if (theme === 'light') { body.classList.add('light-mode'); themeToggle.textContent = '☀️'; }
            else { body.classList.remove('light-mode'); themeToggle.textContent = '🌙'; }
        }
        themeToggle.addEventListener('click', () => {
            let newTheme = body.classList.contains('light-mode') ? 'dark' : 'light';
            localStorage.setItem('theme', newTheme);
            applyTheme(newTheme);
            if (fileEditorInstance) {
                fileEditorInstance.setOption('theme', newTheme === 'light' ? 'default' : 'dracula');
            }
        });

// --- LÓGICA DO BOTÃO DO WORKER (GLOBAL) ---
        const runWorkerBtn = document.getElementById('run-worker-btn');
        if (runWorkerBtn) {
            runWorkerBtn.addEventListener('click', async () => {
                if (!confirm('Deseja forçar a execução do worker agora?')) {
                    return;
                }
                
                runWorkerBtn.textContent = '⏱️'; // Feedback: "Aguarde"
                runWorkerBtn.disabled = true;

                try {
                    const response = await fetch('./api/run_worker_manually.php', { method: 'POST' });
                    const result = await response.json();
                    
                    if (result.success) {
                        alert(result.message);
                    } else {
                        throw new Error(result.error);
                    }
                    
                } catch (error) {
                    alert('Erro ao acionar o worker: ' + error.message);
                } finally {
                    // Retorna ao estado normal após 1 segundo
                    setTimeout(() => {
                        runWorkerBtn.textContent = '⚡';
                        runWorkerBtn.disabled = false;
                    }, 1000);
                }
            });
        }
        // --- FIM DO NOVO BLOCO ---
        
        // --- LÓGICA DO MENU MOBILE (HAMBÚRGUER) ---
        const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
        const mobileBackdrop = document.getElementById('mobile-backdrop');
        const sidebar = document.querySelector('.sidebar');
        const navLinksForMobile = document.querySelectorAll('.sidebar nav a');
        if (mobileMenuToggle && sidebar && mobileBackdrop) {
            mobileMenuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('open');
                mobileBackdrop.classList.toggle('show');
            });
            mobileBackdrop.addEventListener('click', () => {
                sidebar.classList.remove('open');
                mobileBackdrop.classList.remove('show');
            });
            navLinksForMobile.forEach(link => {
                link.addEventListener('click', () => {
                    if (sidebar.classList.contains('open')) {
                        sidebar.classList.remove('open');
                        mobileBackdrop.classList.remove('show');
                    }
                });
            });
        }
        
        // --- 2. LÓGICA DO MODAL (GLOBAL) ---

// --- Modal Cron ---
        const cronModal = document.getElementById('cronModal');
        const closeCronModalBtn = document.getElementById('closeCronModalBtn');
        const cronJobIdInput = document.getElementById('cron_job_id');
        const cronForm = document.getElementById('cronForm');
        const cronPresets = document.getElementById('cron-presets');
        const cronMin = document.getElementById('cron_min');
        const cronHour = document.getElementById('cron_hour');
        const cronDay = document.getElementById('cron_day');
        const cronMonth = document.getElementById('cron_month');
        const cronWeekday = document.getElementById('cron_weekday');
        const cronCommand = document.getElementById('cron_command');
        const cronTitle = document.getElementById('cron_title');
        const cronFeedback = document.getElementById('cron-feedback');
        const cronSubmitBtn = document.getElementById('cron-submit-btn');
        const cronModalTitle = document.getElementById('cron-modal-title');

        // Função para ABRIR o modal (para Adicionar)
function openCronModal() {
    if (!cronModal) return;

    cronForm.reset();
    cronPresets.value = 'custom';
    cronMin.value = '*';
    cronHour.value = '*';
    cronDay.value = '*';
    cronMonth.value = '*';
    cronWeekday.value = '*';
    cronFeedback.textContent = '';
    cronSubmitBtn.disabled = false;

    // --- ALTERAÇÕES ---
    cronModalTitle.textContent = 'Adicionar Tarefa Agendada';
    cronSubmitBtn.textContent = 'Salvar Tarefa';
    cronJobIdInput.value = ''; // Garante que está vazio
    // --- FIM ---

    cronModal.classList.add('show');
    cronCommand.focus();
}
// --- ADICIONE ESTA NOVA FUNÇÃO ---
// Função para ABRIR o modal (para Editar)
function openCronModalForEdit(id, schedule, command, title) {
    if (!cronModal) return;

    cronForm.reset();
    cronPresets.value = 'custom';
    cronFeedback.textContent = '';
    cronSubmitBtn.disabled = false;

    // Preenche os dados
    cronModalTitle.textContent = 'Editar Tarefa Agendada';
    cronSubmitBtn.textContent = 'Salvar Alterações';
    cronJobIdInput.value = id; // Preenche o ID
    cronCommand.value = command; // Preenche o comando
    document.getElementById('cron_title').value = title;

    // Preenche a agenda
    const parts = schedule.split(' ');
    cronMin.value = parts[0] || '*';
    cronHour.value = parts[1] || '*';
    cronDay.value = parts[2] || '*';
    cronMonth.value = parts[3] || '*';
    cronWeekday.value = parts[4] || '*';

    cronModal.style.display = '';
    cronModal.classList.add('show');
    cronCommand.focus();
}

        // Listener para FECHAR o modal
        if (closeCronModalBtn) closeCronModalBtn.addEventListener('click', () => cronModal.classList.remove('show'));
        if (cronModal) cronModal.addEventListener('click', (e) => { if (e.target === cronModal) cronModal.classList.remove('show'); });

        // Listener para as PREDEFINIÇÕES (Presets)
        if (cronPresets) {
            cronPresets.addEventListener('change', () => {
                const value = cronPresets.value;
                if (value === 'custom') return;
                
                const parts = value.split(' ');
                cronMin.value = parts[0];
                cronHour.value = parts[1];
                cronDay.value = parts[2];
                cronMonth.value = parts[3];
                cronWeekday.value = parts[4];
            });
        }

        // Listener para ENVIAR o formulário
        if (cronForm) {
            cronForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                cronSubmitBtn.disabled = true;
                cronFeedback.style.color = 'var(--text-muted)';
                cronFeedback.textContent = 'A salvar...';

                // Monta a agenda a partir dos campos
                const schedule = [
                    cronMin.value, cronHour.value, cronDay.value, 
                    cronMonth.value, cronWeekday.value
                ].join(' ');

                const payload = {
                    schedule: schedule,
                    command: cronCommand.value,
                    title: cronTitle.value || '',
                    id: cronJobIdInput.value || null
                };
                
                try {
                    const response = await fetch('./api/save_cron_job.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    
                    if(response.status === 403) { window.location.href = 'login.php'; return; }
                    const result = await response.json();

                    if (result.success) {
                        cronFeedback.style.color = '#4CAF50';
                        cronFeedback.textContent = result.message;
                        
                        setTimeout(() => {
                            cronModal.classList.remove('show');
                            loadCronList(); // Atualiza a lista
                        }, 1500); 
                        
                    } else {
                        throw new Error(result.error);
                    }
                    
                } catch (error) {
                    cronFeedback.style.color = '#f44336';
                    cronFeedback.textContent = 'Erro: ' + error.message;
                    cronSubmitBtn.disabled = false;
                }
            });
        }

// --- Modal Criar Novo (Arquivo/Pasta) ---
        const createNewModal = document.getElementById('createNewModal');
        const closeCreateNewModalBtn = document.getElementById('closeCreateNewModalBtn');
        const createNewForm = document.getElementById('createNewForm');
        const createNewParentDirInput = document.getElementById('create_new_parent_dir_input');
        const createNewTypeInput = document.getElementById('create_new_type_input');
        const createNewParentDirDisplay = document.getElementById('create-new-parent-dir');
        const createNewNameInput = document.getElementById('create_new_name_input');
        const createNewFeedback = document.getElementById('create-new-feedback');
        const createNewSubmitBtn = document.getElementById('create-new-submit-btn');
        const createNewTitle = document.getElementById('create-new-title');
        const createNewLabel = document.getElementById('create-new-label');

        // Função para ABRIR o modal
        function openCreateNewModal(parentDir, type) {
            if (!createNewModal) return;
            
            // Preenche os campos
            createNewParentDirInput.value = parentDir;
            createNewTypeInput.value = type;
            createNewParentDirDisplay.textContent = (parentDir === '/' || parentDir === '') ? '/ (Raiz)' : parentDir;

            // Adapta o modal para "Arquivo" ou "Pasta"
            if (type === 'file') {
                createNewTitle.textContent = 'Criar Novo Arquivo';
                createNewLabel.textContent = 'Nome do Arquivo:';
                createNewNameInput.placeholder = 'ex: index.php';
                createNewSubmitBtn.textContent = 'Criar Arquivo';
            } else {
                createNewTitle.textContent = 'Criar Nova Pasta';
                createNewLabel.textContent = 'Nome da Pasta:';
                createNewNameInput.placeholder = 'ex: nova_pasta';
                createNewSubmitBtn.textContent = 'Criar Pasta';
            }
            
            // Limpa o formulário
            createNewForm.reset(); // Limpa o nome
            createNewFeedback.textContent = '';
            createNewSubmitBtn.disabled = false;
            
            createNewModal.classList.add('show');
            createNewNameInput.focus();
        }

        // Listener para FECHAR o modal
        if (closeCreateNewModalBtn) closeCreateNewModalBtn.addEventListener('click', () => createNewModal.classList.remove('show'));
        if (createNewModal) createNewModal.addEventListener('click', (e) => { if (e.target === createNewModal) createNewModal.classList.remove('show'); });

        // Listener para ENVIAR o formulário
        if (createNewForm) {
            createNewForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                createNewSubmitBtn.disabled = true;
                createNewFeedback.style.color = 'var(--text-muted)';
                createNewFeedback.textContent = 'Aguarde...';

                const payload = {
                    parent_dir: createNewParentDirInput.value,
                    type: createNewTypeInput.value,
                    name: createNewNameInput.value
                };
                
                try {
                    const response = await fetch('./api/create_new.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    
                    if(response.status === 403) { window.location.href = 'login.php'; return; }
                    const result = await response.json();

                    if (result.success) {
                        createNewFeedback.style.color = '#4CAF50';
                        createNewFeedback.textContent = result.message;
                        
                        setTimeout(() => {
                            createNewModal.classList.remove('show');
                            loadFileList(currentFilePath); // Atualiza a lista
                        }, 1500); 
                        
                    } else {
                        throw new Error(result.error);
                    }
                    
                } catch (error) {
                    createNewFeedback.style.color = '#f44336';
                    createNewFeedback.textContent = 'Erro: ' + error.message;
                    createNewSubmitBtn.disabled = false;
                }
            });
        }

// --- Modal Permissões ---
        const permissionsModal = document.getElementById('permissionsModal');
        const closePermissionsModalBtn = document.getElementById('closePermissionsModalBtn');
        const permissionsForm = document.getElementById('permissionsForm');
        const permissionsPathInput = document.getElementById('permissions_path_input');
        const permissionsPathDisplay = document.getElementById('permissions-path-display');
        const permissionsNumericInput = document.getElementById('permissions_numeric_input');
        const permissionsFeedback = document.getElementById('permissions-feedback');
        const permissionsSubmitBtn = document.getElementById('permissions-submit-btn');
        const permCheckboxes = permissionsForm.querySelectorAll('.permissions-grid input[type="checkbox"]');

        // --- Funções de Binding (Ligação de dados) ---
function updateNumericFromIndividuals() {
            let total = 0;
            permCheckboxes.forEach(cb => {
                if (cb.checked) {
                    total += parseInt(cb.dataset.value, 10);
                }
            });
            // Converte o número (ex: 493) para Octal String (ex: 755)
            let octal = total.toString(8);
            // Garante 4 dígitos (ex: 755 -> 0755)
            permissionsNumericInput.value = ('000' + octal).slice(-4);
        }
        // Função 1: Atualiza o input numérico (0755) quando os checkboxes mudam
        function updateNumericFromCheckboxes() {
            let total = 0;
            permCheckboxes.forEach(cb => {
                if (cb.checked) {
                    total += parseInt(cb.dataset.value, 10);
                }
            });
            // Converte o número (ex: 493) para Octal String (ex: 755)
            let octal = total.toString(8);
            // Garante 4 dígitos (ex: 755 -> 0755)
            permissionsNumericInput.value = ('000' + octal).slice(-4);
        }

        // Função 2: Atualiza os checkboxes quando o input numérico (0755) muda
        function updateCheckboxesFromNumeric() {
            let mode = permissionsNumericInput.value;
            // Converte o Octal String (ex: 0755 ou 755) para Inteiro (ex: 493)
            let total = parseInt(mode, 8); // O '8' é crucial (base 8)
            
            if (isNaN(total)) return;

            permCheckboxes.forEach(cb => {
                const val = parseInt(cb.dataset.value, 10);
                // (Bitwise AND) Verifica se o bit da permissão está "ligado" no total
                cb.checked = (total & val) === val;
            });
        }
        
        // Adiciona os listeners para o binding
        if(permissionsForm) {
            permCheckboxes.forEach(cb => cb.addEventListener('change', updateNumericFromCheckboxes));
            permissionsNumericInput.addEventListener('input', updateCheckboxesFromNumeric);
        }
        
        // --- Fim das Funções de Binding ---



        // Função para ABRIR o modal
        async function openPermissionsModal(path) {
            if (!permissionsModal) return;
            
            // Preenche os campos
            permissionsPathInput.value = path;
            permissionsPathDisplay.textContent = path;
            
            // Limpa o feedback
            permissionsFeedback.textContent = '';
            permissionsSubmitBtn.disabled = false;
            permissionsSubmitBtn.textContent = 'Salvar Permissões';
            permissionsModal.classList.add('show');
            tryLoadUsers();
            
            // Busca as permissões atuais
            permissionsNumericInput.value = "....";
            try {
                const response = await fetch(`./api/get_permissions.php?path=${encodeURIComponent(path)}&t=${new Date().getTime()}`);
                if(response.status === 403) { window.location.href = 'login.php'; return; }
                const result = await response.json();
                
                if (result.success) {
    // modo octal (API atual devolve mode_octal ou mode)
    const modeVal = result.mode_octal ?? result.mode ?? result.permissions ?? result.mode;
    permissionsNumericInput.value = String(modeVal ?? '').padStart(4, '0');
    updateCheckboxesFromNumeric(); // Atualiza os checkboxes

    // OWNER / GROUP populating (se a API devolveu owner/group)
    const ownerSelect = document.getElementById('permissions_owner_select');
    const ownerInput = document.getElementById('permissions_owner_input');
    const groupSelect = document.getElementById('permissions_group_select'); // (se implementar)
    const groupInput = document.getElementById('permissions_group_input');

    if (result.owner && ownerSelect) {
        // adiciona opção atual se não existir e seleciona
        if (![...ownerSelect.options].some(o => o.value === result.owner)) {
            const opt = document.createElement('option');
            opt.value = result.owner;
            opt.text = result.owner;
            ownerSelect.appendChild(opt);
        }
        ownerSelect.value = result.owner;
    }
    if (ownerInput) {
        ownerInput.value = result.owner ?? '';
    }

    if (result.group && groupSelect) {
        if (![...groupSelect.options].some(o => o.value === result.group)) {
            const opt = document.createElement('option');
            opt.value = result.group;
            opt.text = result.group;
            groupSelect.appendChild(opt);
        }
        groupSelect.value = result.group;
    }
    if (groupInput) {
        groupInput.value = result.group ?? '';
    }

} else {
    throw new Error(result.error);
}
            } catch (error) {
                permissionsFeedback.style.color = '#f44336';
                permissionsFeedback.textContent = 'Erro ao buscar permissões: ' + error.message;
                permissionsNumericInput.value = "Erro";
            }
        }

        // Listener para FECHAR o modal
        if (closePermissionsModalBtn) closePermissionsModalBtn.addEventListener('click', () => permissionsModal.classList.remove('show'));
        if (permissionsModal) permissionsModal.addEventListener('click', (e) => { if (e.target === permissionsModal) permissionsModal.classList.remove('show'); });

        // Listener para ENVIAR o formulário
(function () {
  const form = document.getElementById('permissionsForm');
  if (!form) return;

  form.addEventListener('submit', async function (ev) {
    ev.preventDefault();
    const feedback = document.getElementById('permissions-feedback');
    feedback.textContent = '';

    const path = document.getElementById('permissions_path_input').value;
    const modeEl = document.getElementById('permissions_numeric_input');
    const modeRaw = modeEl ? String(modeEl.value).trim() : '';

    // owner: prioriza select se existir, senão input livre
    const ownerSelect = document.getElementById('permissions_owner_select');
    const ownerInput  = document.getElementById('permissions_owner_input');
    let ownerVal = '';
    if (ownerSelect && ownerSelect.value) ownerVal = ownerSelect.value.trim();
    else if (ownerInput && ownerInput.value) ownerVal = ownerInput.value.trim();

    // normalizar mode para 4 dígitos (ex: '777' -> '0777')
    let mode = modeRaw;
    if (mode !== '' && !/^[0-7]{3,4}$/.test(mode)) {
      const cleaned = mode.replace(/[^0-7]/g, '');
      mode = cleaned.length === 3 ? '0' + cleaned : cleaned;
    }

    const payload = { path };
    if (mode) payload.mode = mode;
    if (ownerVal) payload.owner = ownerVal;

    // se houver campo group separado
    const groupSelect = document.getElementById('permissions_group_select');
    const groupInput  = document.getElementById('permissions_group_input');
    if (groupSelect && groupSelect.value) payload.group = groupSelect.value.trim();
    else if (groupInput && groupInput.value) payload.group = groupInput.value.trim();

    try {
      document.getElementById('permissions-submit-btn').disabled = true;
      feedback.textContent = 'Salvando...';

      const resp = await fetch('/api/change_permissions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const j = await resp.json().catch(() => null);
      if (!resp.ok) {
        const msg = (j && j.error) ? j.error : `HTTP ${resp.status}`;
        feedback.textContent = 'Erro: ' + msg;
        document.getElementById('permissions-submit-btn').disabled = false;
        return;
      }

      if (j && j.success) {
        feedback.textContent = 'OK: ' + (j.message || 'Permissões atualizadas.');

        // ---------- refresh da lista de arquivos ----------
        // try usar currentFilePath (se sua app define). fallback: parent do path.
        let parent = '/';
        try {
          if (typeof currentFilePath !== 'undefined' && currentFilePath) parent = currentFilePath;
          else parent = path.replace(/\/[^\/]+$/,'') || '/';
        } catch(e) {
          parent = '/';
        }

        // aguarda um pouquinho para o backend estabilizar (pouco delay)
        setTimeout(() => {
          if (typeof loadFileList === 'function') {
            try {
              loadFileList(parent);
            } catch (err) {
              console.warn('loadFileList falhou:', err);
            }
          }
          // fechar modal
          const modalClose = document.getElementById('closePermissionsModalBtn');
          if (modalClose) modalClose.click();
        }, 500);

      } else {
        feedback.textContent = 'Resposta inesperada do servidor.';
        console.log('change_permissions resp', j);
      }
    } catch (err) {
      console.error(err);
      feedback.textContent = 'Erro de comunicação: ' + err.message;
    } finally {
      document.getElementById('permissions-submit-btn').disabled = false;
    }
  });
})();


        
// --- Modal Comprimir (ATUALIZADO) ---
    const compressModal = document.getElementById('compressModal');
    const closeCompressModalBtn = document.getElementById('closeCompressModalBtn');
    const compressForm = document.getElementById('compressForm');
    const compressSourcePathInput = document.getElementById('compress_source_path_input');
    const compressTypeInput = document.getElementById('compress_type_input');
    const compressSourcePathDisplay = document.getElementById('compress-source-path');
    const archiveNameInput = document.getElementById('archive_name_input');
    const compressFormatSelect = document.getElementById('compress_format_select'); // NOVO
    const compressFeedback = document.getElementById('compress-feedback');
    const compressSubmitBtn = document.getElementById('compress-submit-btn');

    // Função para ABRIR o modal
    function openCompressModal(path, type) {
        if (!compressModal) return;

        const sourceName = path.split('/').pop();
        const defaultArchiveName = (sourceName.indexOf('.') > 0 ? sourceName.substring(0, sourceName.lastIndexOf('.')) : sourceName);

        compressForm.reset(); // Limpa o formulário
        compressFormatSelect.value = 'zip'; // Reseta o formato

        // Preenche os campos
        compressSourcePathInput.value = path;
        compressTypeInput.value = type;
        compressSourcePathDisplay.textContent = path;
        archiveNameInput.value = defaultArchiveName; // Nome sem extensão

        compressFeedback.textContent = '';
        compressSubmitBtn.disabled = false;
        compressSubmitBtn.textContent = 'Enfileirar Compressão';

        compressModal.classList.add('show');
        archiveNameInput.focus();
        archiveNameInput.select();
    }

    // Listener para FECHAR o modal
    if (closeCompressModalBtn) closeCompressModalBtn.addEventListener('click', () => compressModal.classList.remove('show'));
    if (compressModal) compressModal.addEventListener('click', (e) => { if (e.target === compressModal) compressModal.classList.remove('show'); });

    // Listener para ENVIAR o formulário
    if (compressForm) {
        compressForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            compressSubmitBtn.disabled = true;
            compressFeedback.style.color = 'var(--text-muted)';
            compressFeedback.textContent = 'Aguarde...';

            // Payload atualizado com 'format'
            const payload = {
                source_path: compressSourcePathInput.value,
                archive_name: archiveNameInput.value,
                format: compressFormatSelect.value // Envia 'zip' ou 'tar.gz'
            };

            try {
                const response = await fetch('./api/queue_compress_file.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                if(response.status === 403) { window.location.href = 'login.php'; return; }
                const result = await response.json();

                if (result.success) {
                    compressFeedback.style.color = '#4CAF50';
                    compressFeedback.textContent = result.message;

                    setTimeout(() => {
                        compressModal.classList.remove('show');
                        loadFileList(currentFilePath); // Atualiza a lista
                    }, 2000); 

                } else {
                    throw new Error(result.error);
                }

            } catch (error) {
                compressFeedback.style.color = '#f44336';
                compressFeedback.textContent = 'Erro: ' + error.message;
                compressSubmitBtn.disabled = false;
                compressSubmitBtn.textContent = 'Tentar Novamente';
            }
        });
    }
        
// --- Modal Renomear ---
        const renameModal = document.getElementById('renameModal');
        const closeRenameModalBtn = document.getElementById('closeRenameModalBtn');
        const renameForm = document.getElementById('renameForm');
        const renameOldPathInput = document.getElementById('rename_old_path_input');
        const renameTypeInput = document.getElementById('rename_type_input');
        const renameOldPathDisplay = document.getElementById('rename-old-path');
        const newNameInput = document.getElementById('new_name_input');
        const renameFeedback = document.getElementById('rename-feedback');
        const renameSubmitBtn = document.getElementById('rename-submit-btn');

        // Função para ABRIR o modal
        function openRenameModal(path, type) {
            if (!renameModal) return;
            
            const currentName = path.split('/').pop();
            
            // Preenche os campos
            renameOldPathInput.value = path;
            renameTypeInput.value = type;
            renameOldPathDisplay.textContent = path;
            newNameInput.value = currentName;
            
            // Limpa o feedback
            renameFeedback.textContent = '';
            renameSubmitBtn.disabled = false;
            renameSubmitBtn.textContent = 'Renomear';
            
            renameModal.classList.add('show');
            newNameInput.focus(); // Foca no campo de input
            newNameInput.select(); // Seleciona o nome antigo
        }

        // Listener para FECHAR o modal
        if (closeRenameModalBtn) closeRenameModalBtn.addEventListener('click', () => renameModal.classList.remove('show'));
        if (renameModal) renameModal.addEventListener('click', (e) => { if (e.target === renameModal) renameModal.classList.remove('show'); });

        // Listener para ENVIAR o formulário
        if (renameForm) {
            renameForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                renameSubmitBtn.disabled = true;
                renameSubmitBtn.textContent = 'Renomeando...';
                renameFeedback.style.color = 'var(--text-muted)';
                renameFeedback.textContent = 'Aguarde...';

                const payload = {
                    old_path: renameOldPathInput.value,
                    new_name: newNameInput.value
                };
                
                try {
                    const response = await fetch('./api/rename_file.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    
                    if(response.status === 403) { window.location.href = 'login.php'; return; }
                    const result = await response.json();

                    if (result.success) {
                        renameFeedback.style.color = '#4CAF50'; // Verde
                        renameFeedback.textContent = result.message;
                        
                        // Recarrega a lista de arquivos e fecha o modal
                        if (typeof loadFileList === 'function') {
                            loadFileList(currentFilePath); // Recarrega a pasta atual
                        }
                        setTimeout(() => {
                            renameModal.classList.remove('show');
                        }, 1500); // Fecha após 1.5 segundos
                        
                    } else {
                        throw new Error(result.error);
                    }
                    
                } catch (error) {
                    renameFeedback.style.color = '#f44336'; // Vermelho
                    renameFeedback.textContent = 'Erro: ' + error.message;
                    renameSubmitBtn.disabled = false;
                    renameSubmitBtn.textContent = 'Tentar Novamente';
                }
            });
        }
        
// --- Modal Visualizador de Imagem ---
        const imageViewerModal = document.getElementById('imageViewerModal');
        const closeImageViewerBtn = document.getElementById('closeImageViewerBtn');
        const imageViewerTag = document.getElementById('image-viewer-tag');
        const imageViewerTitle = document.getElementById('image-viewer-title');
        
        // Função para ABRIR o modal
        function openFileViewerModal(path) {
            if (!imageViewerModal) return;
            
            // Define o título e limpa a imagem antiga
            imageViewerTitle.textContent = `Visualizar: ${path.split('/').pop()}`;
            imageViewerTag.src = ''; // Limpa a fonte anterior
            imageViewerTag.alt = 'A carregar imagem...'; 
            
            // Define a nova fonte para a nossa API (adiciona timestamp para evitar cache)
            imageViewerTag.src = `./api/get_image.php?path=${encodeURIComponent(path)}&t=${new Date().getTime()}`;
            
            imageViewerModal.classList.add('show');
        }

        // Listener para FECHAR o modal
        if (closeImageViewerBtn) closeImageViewerBtn.addEventListener('click', () => imageViewerModal.classList.remove('show'));
        if (imageViewerModal) imageViewerModal.addEventListener('click', (e) => { 
            // Fecha se clicar no fundo (mas não na imagem)
            if (e.target === imageViewerModal || e.target.id === 'image-viewer-content') {
                imageViewerModal.classList.remove('show');
            }
        });

// --- Modal Upload de Arquivo ---
        const uploadFileModal = document.getElementById('uploadFileModal');
        const closeUploadModalBtn = document.getElementById('closeUploadModalBtn');
        const uploadFileForm = document.getElementById('uploadFileForm');
        const uploadDestPathInput = document.getElementById('upload_dest_path');
        const uploadPathDisplay = document.getElementById('upload-path-display');
        const uploadFeedback = document.getElementById('upload-feedback');
        const uploadFileInput = document.getElementById('file_to_upload');
        const uploadFileName = document.getElementById('upload-file-name');
        const uploadSubmitBtn = document.getElementById('upload-submit-btn');

        // Função para ABRIR o modal
        function openUploadModal(path) {
            if (!uploadFileModal) return;
            
            // Define o caminho de destino no formulário
            const displayPath = (path === '/' || path === '') ? '/ (Raiz)' : path;
            uploadDestPathInput.value = path;
            uploadPathDisplay.textContent = displayPath;
            
            // Limpa o formulário
            uploadFileForm.reset();
            uploadFileName.textContent = 'Nenhum arquivo escolhido';
            uploadFeedback.textContent = '';
            uploadSubmitBtn.disabled = false;
            uploadSubmitBtn.textContent = 'Iniciar Upload';
            
            uploadFileModal.classList.add('show');
        }

        // Listener para FECHAR o modal
        if (closeUploadModalBtn) closeUploadModalBtn.addEventListener('click', () => uploadFileModal.classList.remove('show'));
        if (uploadFileModal) uploadFileModal.addEventListener('click', (e) => { if (e.target === uploadFileModal) uploadFileModal.classList.remove('show'); });

        // Listener para mostrar o nome do arquivo selecionado
        if (uploadFileInput) {
            uploadFileInput.addEventListener('change', () => {
                if (uploadFileInput.files.length > 0) {
                    uploadFileName.textContent = uploadFileInput.files[0].name;
                } else {
                    uploadFileName.textContent = 'Nenhum arquivo escolhido';
                }
            });
        }
        
        // Listener para ENVIAR o formulário
        if (uploadFileForm) {
            uploadFileForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                uploadSubmitBtn.disabled = true;
                uploadSubmitBtn.textContent = 'Enviando...';
                uploadFeedback.style.color = 'var(--text-muted)';
                uploadFeedback.textContent = 'Aguarde, enviando arquivo...';

                // Usamos FormData para enviar arquivos via fetch
                const formData = new FormData(uploadFileForm);
                
                try {
                    const response = await fetch('./api/upload_file.php', {
                        method: 'POST',
                        body: formData 
                        // Não definimos 'Content-Type', o navegador faz isso por nós com FormData
                    });
                    
                    if(response.status === 403) { window.location.href = 'login.php'; return; }
                    
                    const result = await response.json();

                    if (result.success) {
                        uploadFeedback.style.color = '#4CAF50'; // Verde
                        uploadFeedback.textContent = result.message;
                        
                        // Recarrega a lista de arquivos e fecha o modal
                        if (typeof loadFileList === 'function') {
                            loadFileList(uploadDestPathInput.value); // Recarrega a pasta atual
                        }
                        setTimeout(() => {
                            uploadFileModal.classList.remove('show');
                        }, 2000); // Fecha após 2 segundos
                        
                    } else {
                        throw new Error(result.error);
                    }
                    
                } catch (error) {
                    uploadFeedback.style.color = '#f44336'; // Vermelho
                    uploadFeedback.textContent = 'Erro: ' + error.message;
                    uploadSubmitBtn.disabled = false;
                    uploadSubmitBtn.textContent = 'Tentar Novamente';
                }
            });
        }
        

// --- Modal Editor de Arquivos (com CodeMirror) ---
        const fileEditorModal = document.getElementById('fileEditorModal');
        const closeFileEditorBtn = document.getElementById('closeFileEditorBtn');
        const fileEditorForm = document.getElementById('fileEditorForm');
        const fileEditorTextarea = document.getElementById('file_content_editor'); // O textarea original
        const fileEditorPathInput = document.getElementById('file_path_to_save');
        const editorTitle = document.getElementById('file-editor-title');
        const editorStatusLabel = document.getElementById('editor-status-label');
        const saveFeedback = document.getElementById('save-feedback');
        
        // Função para obter o "modo" (syntax highlighting) pela extensão
        function getCodeMirrorMode(filePath) {
            const ext = filePath.substring(filePath.lastIndexOf('.'));
            switch (ext) {
                case '.php': return 'application/x-httpd-php';
                case '.js': return 'text/javascript';
                case '.json': return 'application/json';
                case '.css': return 'text/css';
                case '.html':
                case '.htm': return 'text/html';
                case '.xml': return 'application/xml';
                default: return 'text/plain';
            }
        }

        // Função para abrir e carregar o arquivo
        async function openFileEditorModal(path) {
            if (!fileEditorModal) return;
            
            // Limpa o estado
            const fileName = path.split('/').pop();
            editorTitle.textContent = `Editando Arquivo: ${fileName}`;
            editorStatusLabel.textContent = 'Buscando conteúdo do arquivo...';
            fileEditorPathInput.value = path;
            saveFeedback.textContent = '';
            
            fileEditorModal.classList.add('show');

            // --- INICIALIZAÇÃO DO CODEMIRROR ---
            if (!fileEditorInstance) {
                // Cria a instância do editor a partir do textarea
                fileEditorInstance = CodeMirror.fromTextArea(fileEditorTextarea, {
                    lineNumbers: true,       // <-- O SEU REQUISITO!
                    matchBrackets: true,     // Destaca parênteses
                    autoRefresh: true,       // Essencial para funcionar em modals
                    // O 'rows=25' do textarea define a altura
                });
            }
            
            // Define o tema e o modo (syntax highlighting)
            const currentTheme = document.body.classList.contains('light-mode') ? 'default' : 'dracula';
            const currentMode = getCodeMirrorMode(path);
            
            fileEditorInstance.setOption('theme', currentTheme);
            fileEditorInstance.setOption('mode', currentMode);
            fileEditorInstance.setValue('A carregar...'); // Valor temporário
            
            // O refresh é essencial após mostrar o modal e definir opções
            setTimeout(() => fileEditorInstance.refresh(), 100); 

            // --- FIM DA INICIALIZAÇÃO ---

            try {
                const response = await fetch(`./api/get_file_content.php?path=${encodeURIComponent(path)}&t=${new Date().getTime()}`);
                if(response.status === 403) { window.location.href = 'login.php'; return; }
                const result = await response.json();
                
                if (result.success) {
                    fileEditorInstance.setValue(result.content); // Usa setValue
                    editorStatusLabel.textContent = 'Conteúdo pronto para edição.';
                } else {
                    fileEditorInstance.setValue(`ERRO: Não foi possível carregar o arquivo.\n${result.error}`); // Usa setValue
                    editorStatusLabel.textContent = 'Erro ao carregar.';
                }
            } catch (error) {
                fileEditorInstance.setValue(`ERRO FATAL de conexão:\n${error.message}`); // Usa setValue
                editorStatusLabel.textContent = 'Erro fatal.';
            }
        }
        
// Listener para fechar o modal (COMBINADO)
        
        // 1. Pega o botão do rodapé que você adicionou
        const closeFileEditorFooterBtn = document.getElementById('btnEditorCloseFooter');

        // 2. Cria UMA função para fechar e limpar o editor
        function closeTheFileEditor() {
            if (fileEditorInstance) {
                fileEditorInstance.setValue(''); // Limpa o conteúdo
            }
            fileEditorModal.classList.remove('show');
        }

        // 3. Adiciona a função ao 'X' do cabeçalho
        if (closeFileEditorBtn) {
            closeFileEditorBtn.addEventListener('click', closeTheFileEditor);
        }

        // 4. Adiciona a MESMA função ao 'Fechar' do rodapé
        if (closeFileEditorFooterBtn) {
            closeFileEditorFooterBtn.addEventListener('click', closeTheFileEditor);
        }

        // 5. Adiciona a função ao clique no fundo (backdrop)
        if (fileEditorModal) {
            fileEditorModal.addEventListener('click', (e) => { 
                if (e.target === fileEditorModal) {
                    closeTheFileEditor();
                }
            });
        }
        
        // Listener para salvar o arquivo (MODIFICADO)
        if (fileEditorForm) {
            fileEditorForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                // --- ATUALIZAÇÃO IMPORTANTE ---
                // Temos de pedir o valor ao CodeMirror, não ao textarea
                fileEditorInstance.save(); // Copia o conteúdo para o textarea (caso necessário)
                const newContent = fileEditorInstance.getValue(); 
                // --- FIM DA ATUALIZAÇÃO ---

                const submitButton = document.getElementById('saveFileContentBtn');
                submitButton.disabled = true;
                // ... (resto do listener de submit, como estava antes) ...
                
                const payload = {
                    path: fileEditorPathInput.value,
                    content: newContent // <-- USA A NOVA VARIÁVEL
                };
                
                // ... (resto do 'try/catch' como estava antes) ...
                try {
                    const response = await fetch('./api/save_file_content.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    if(response.status === 403) { window.location.href = 'login.php'; return; }
                    const result = await response.json();
                    
                    if (result.success) {
                        saveFeedback.textContent = result.message;
                        saveFeedback.style.color = '#4CAF50'; // Verde
                    } else {
                        saveFeedback.textContent = 'Erro ao salvar: ' + result.error;
                        saveFeedback.style.color = '#f44336'; // Vermelho
                    }
                } catch (error) {
                    saveFeedback.textContent = 'Erro fatal na conexão com a API.';
                    saveFeedback.style.color = '#f44336';
                } finally {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Salvar Alterações';
                }
            });
        }

// --- ADICIONE ESTE NOVO BLOCO ---
    const btnBackupFileEditor = document.getElementById('btnBackupFileEditor');
    if (btnBackupFileEditor) {
        btnBackupFileEditor.addEventListener('click', async () => {
            const path = fileEditorPathInput.value;
            if (!path) {
                alert('Nenhum arquivo carregado para fazer backup.');
                return;
            }

            btnBackupFileEditor.disabled = true;
            btnBackupFileEditor.textContent = 'Aguarde...';
            saveFeedback.style.color = 'var(--text-muted)';
            saveFeedback.textContent = 'Criando backup...';

            try {
                const response = await fetch('./api/backup_file_editor.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ path: path })
                });

                if(response.status === 403) { window.location.href = 'login.php'; return; }
                const result = await response.json();

                if (result.success) {
                    saveFeedback.style.color = '#4CAF50'; // Verde
                    saveFeedback.textContent = 'Backup criado!';
                } else {
                    throw new Error(result.error);
                }
            } catch (error) {
                saveFeedback.style.color = '#f44336'; // Vermelho
                saveFeedback.textContent = 'Erro: ' + error.message;
            } finally {
                setTimeout(() => {
                    btnBackupFileEditor.disabled = false;
                    btnBackupFileEditor.textContent = 'Fazer Backup';
                    // Limpa a mensagem de feedback após 3s
                    if (saveFeedback.textContent === 'Backup criado!') {
                        saveFeedback.textContent = '';
                    }
                }, 3000);
            }
        });
    }

// --- ADICIONE ESTE NOVO BLOCO PARA PESQUISA ---
    const btnFindCode = document.getElementById('btnFindCode');
    if (btnFindCode) {
        btnFindCode.addEventListener('click', () => {
            if (fileEditorInstance) {
                // Ativa a barra de pesquisa (find)
                fileEditorInstance.execCommand("find");
            }
        });
    }

    const btnReplaceCode = document.getElementById('btnReplaceCode');
    if (btnReplaceCode) {
        btnReplaceCode.addEventListener('click', () => {
            if (fileEditorInstance) {
                // Ativa a barra de pesquisa e substituição (replace)
                fileEditorInstance.execCommand("replace");
            }
        });
    }
    // --- FIM DO NOVO BLOCO ---
    
        // --- Modal Adicionar Site ---
        const modalBackdrop = document.getElementById('addSiteModal');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const addSiteForm = document.getElementById('addSiteForm');
        function openModal() { if (modalBackdrop) modalBackdrop.classList.add('show'); }
        function closeModal() { if (modalBackdrop) modalBackdrop.classList.remove('show'); }
        if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
        if (modalBackdrop) modalBackdrop.addEventListener('click', (e) => { if (e.target === modalBackdrop) closeModal(); });
        if (addSiteForm) { addSiteForm.addEventListener('submit', async (e) => { e.preventDefault(); const domain = addSiteForm.querySelector('input[name="domain"]').value; const root = ''; const submitButton = addSiteForm.querySelector('button[type="submit"]'); submitButton.disabled = true; submitButton.textContent = 'Enfileirando...'; try { const response = await fetch('./api/create_website.php', { method: 'POST', headers: { 'Content-Type': 'application/json', }, body: JSON.stringify({ domain: domain, root: root }) }); const result = await response.json(); if (result.success) { alert(result.message); closeModal(); if (typeof loadSitesList === 'function') loadSitesList(); } else { alert('Erro: ' + result.message); } } catch (error) { console.error('Erro ao criar site:', error); alert('Erro fatal ao conectar-se à API.'); } finally { submitButton.disabled = false; submitButton.textContent = 'Salvar'; addSiteForm.reset(); } }); }

        // --- Modal Config Nginx ---
        const nginxConfigModal = document.getElementById('nginxConfigModal');
        const closeNginxConfigBtn = document.getElementById('closeNginxConfigBtn');
        const nginxConfigForm = document.getElementById('nginxConfigForm');
        const nginxModalLoading = document.getElementById('nginx-modal-loading');
        const nginxModalTitle = document.getElementById('nginx-modal-title');
        if (closeNginxConfigBtn) closeNginxConfigBtn.addEventListener('click', () => nginxConfigModal.classList.remove('show'));
        if (nginxConfigForm) { nginxConfigForm.addEventListener('submit', async (e) => { e.preventDefault(); const submitButton = nginxConfigForm.querySelector('button[type="submit"]'); submitButton.disabled = true; submitButton.textContent = 'Enfileirando...'; const payload = { config_file_path: document.getElementById('config_file_path').value, server_name: document.getElementById('conf_server_name').value, root: document.getElementById('conf_root').value, index: document.getElementById('conf_index').value, php_socket: document.getElementById('conf_php_socket').value }; try { const response = await fetch('./api/queue_save_nginx_config.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }); if(response.status === 403) { window.location.href = 'login.php'; return; } const result = await response.json(); if (result.success) { alert(result.message); nginxConfigModal.classList.remove('show'); } else { alert('Erro ao salvar: ' + result.message); } } catch (error) { console.error('Erro ao salvar config Nginx:', error); alert('Erro fatal ao conectar-se à API.'); } finally { submitButton.disabled = false; submitButton.textContent = 'Salvar Alterações'; } }); }
        
        // --- Modal Adicionar Database ---
        const dbModalBackdrop = document.getElementById('addDatabaseModal');
        const closeDbModalBtn = document.getElementById('closeDbModalBtn');
        const closeDbModalBtnImport = document.getElementById('closeDbModalBtn_import');
        const addDbForm = document.getElementById('addDatabaseForm');
        const importDbForm = document.getElementById('importDatabaseForm');
        const dbPassInput = document.getElementById('db_pass_input');
        const dbGeneratePassBtn = document.getElementById('db-generate-pass-btn');
        function openDbModal() { if (dbModalBackdrop) dbModalBackdrop.classList.add('show'); }
        function closeDbModal() { if (dbModalBackdrop) dbModalBackdrop.classList.remove('show'); }
        if (closeDbModalBtn) closeDbModalBtn.addEventListener('click', closeDbModal);
        if (closeDbModalBtnImport) closeDbModalBtnImport.addEventListener('click', closeDbModal);
        if (dbModalBackdrop) dbModalBackdrop.addEventListener('click', (e) => { if (e.target === dbModalBackdrop) closeDbModal(); });
        const modalTabBtns = document.querySelectorAll('.modal-tab-btn');
        modalTabBtns.forEach(btn => { btn.addEventListener('click', () => { modalTabBtns.forEach(b => b.classList.remove('active')); document.querySelectorAll('.modal-tab-content').forEach(c => c.classList.remove('active')); btn.classList.add('active'); document.getElementById(btn.dataset.tab).classList.add('active'); }); });
        if (dbGeneratePassBtn) { dbGeneratePassBtn.addEventListener('click', () => { const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()'; let pass = ''; for (let i = 0; i < 16; i++) pass += chars.charAt(Math.floor(Math.random() * chars.length)); dbPassInput.value = pass; }); }
        if (addDbForm) { addDbForm.addEventListener('submit', async (e) => { e.preventDefault(); const submitButton = addDbForm.querySelector('button[type="submit"]'); submitButton.disabled = true; submitButton.textContent = 'Enfileirando...'; const payload = { db_name: addDbForm.querySelector('input[name="db_name"]').value, db_user: addDbForm.querySelector('input[name="db_user"]').value, db_pass: addDbForm.querySelector('input[name="db_pass"]').value, }; try { const response = await fetch('./api/queue_create_database.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }); if(response.status === 403) { window.location.href = 'login.php'; return; } const result = await response.json(); if (result.success) { alert(result.message); closeDbModal(); if (typeof loadDatabaseList === 'function') loadDatabaseList(); } else { alert('Erro: ' + result.message); } } catch (error) { alert('Erro fatal ao conectar-se à API.'); } finally { submitButton.disabled = false; submitButton.textContent = 'Criar Banco e Usuário'; addDbForm.reset(); } }); }
        if (importDbForm) { importDbForm.addEventListener('submit', async (e) => { e.preventDefault(); const submitButton = importDbForm.querySelector('button[type="submit"]'); submitButton.disabled = true; submitButton.textContent = 'A registrar...'; const payload = { db_name: importDbForm.querySelector('input[name="db_name_existing"]').value, db_user: importDbForm.querySelector('input[name="db_user_existing"]').value }; try { const response = await fetch('./api/register_database.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }); if(response.status === 403) { window.location.href = 'login.php'; return; } const result = await response.json(); if (result.success) { alert(result.message); closeDbModal(); if (typeof loadDatabaseList === 'function') loadDatabaseList(); } else { alert('Erro: ' + result.message); } } catch (error) { alert('Erro fatal ao conectar-se à API.'); } finally { submitButton.disabled = false; submitButton.textContent = 'Registrar Banco no Painel'; importDbForm.reset(); } }); }
        
        // --- Modal Restaurar Database (Upload) ---
        const restoreModalBackdrop = document.getElementById('restoreDatabaseModal');
        const closeRestoreModalBtn = document.getElementById('closeRestoreModalBtn');
        const restoreDbForm = document.getElementById('restoreDatabaseForm');
        const restoreDbSelect = document.getElementById('restore-db-select');
        const restoreFeedback = document.getElementById('restore-feedback');
        const restoreFileInput = document.getElementById('restore-file-input');
        const fileNameDisplay = document.getElementById('file-name-display');
        if (restoreFileInput && fileNameDisplay) { restoreFileInput.addEventListener('change', (e) => { if (e.target.files.length > 0) { fileNameDisplay.textContent = e.target.files[0].name; fileNameDisplay.style.color = 'var(--text-color)'; } else { fileNameDisplay.textContent = 'Nenhum arquivo escolhido'; fileNameDisplay.style.color = 'var(--text-muted)'; } }); }
        function openRestoreModal() { if (restoreModalBackdrop) { restoreDbSelect.innerHTML = '<option value="">Carregando lista...</option>'; fetch('./api/list_databases.php') .then(res => res.json()) .then(data => { restoreDbSelect.innerHTML = '<option value="">-- Selecione um banco para sobrescrever --</option>'; if (data.success && data.databases) { data.databases.forEach(db => { restoreDbSelect.innerHTML += `<option value="${db.id}">${db.db_name} (usuário: ${db.db_user})</option>`; }); } }); restoreFeedback.style.display = 'none'; restoreDbForm.reset(); restoreModalBackdrop.classList.add('show'); } }
        function closeRestoreModal() { if (restoreModalBackdrop) restoreModalBackdrop.classList.remove('show'); }
        if (closeRestoreModalBtn) closeRestoreModalBtn.addEventListener('click', closeRestoreModal);
        if (restoreModalBackdrop) restoreModalBackdrop.addEventListener('click', (e) => { if (e.target === restoreModalBackdrop) closeRestoreModal(); });
        if (restoreDbForm) { restoreDbForm.addEventListener('submit', async (e) => { e.preventDefault(); const submitButton = restoreDbForm.querySelector('button[type="submit"]'); submitButton.disabled = true; submitButton.textContent = 'Enviando arquivo...'; restoreFeedback.style.display = 'block'; restoreFeedback.style.color = 'var(--text-muted)'; restoreFeedback.textContent = 'Aguarde, enviando arquivo para o servidor...'; const formData = new FormData(restoreDbForm); try { const response = await fetch('./api/upload_and_queue_restore.php', { method: 'POST', body: formData }); if(response.status === 403) { window.location.href = 'login.php'; return; } const result = await response.json(); if (result.success) { restoreFeedback.style.color = '#4CAF50'; restoreFeedback.textContent = result.message; setTimeout(() => { closeRestoreModal(); if (typeof loadDatabaseList === 'function') loadDatabaseList(); }, 3000); } else { restoreFeedback.style.color = '#f44336'; restoreFeedback.textContent = 'Erro: ' + result.message; } } catch (error) { restoreFeedback.style.color = '#f44336'; restoreFeedback.textContent = 'Erro fatal no upload. O arquivo pode ser muito grande.'; } finally { submitButton.disabled = false; submitButton.textContent = 'Iniciar Upload e Restauração'; } }); }

        // --- Modal Restaurar Site (Upload) ---
        const restoreSiteModal = document.getElementById('restoreSiteModal');
        const closeRestoreSiteModalBtn = document.getElementById('closeRestoreSiteModalBtn');
        const restoreSiteForm = document.getElementById('restoreSiteForm');
        const restoreSiteSelect = document.getElementById('restore-site-select');
        const restoreSiteRootPath = document.getElementById('restore-site-root-path');
        const restoreSiteFeedback = document.getElementById('restore-site-feedback');
        const restoreSiteFileInput = document.getElementById('restore-site-file-input');
        const restoreSiteFileName = document.getElementById('restore-site-file-name');
        let allBackupableSites = []; // Cache para a lista de sites
        if (restoreSiteFileInput && restoreSiteFileName) {
            restoreSiteFileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    restoreSiteFileName.textContent = e.target.files[0].name;
                    restoreSiteFileName.style.color = 'var(--text-color)';
                } else {
                    restoreSiteFileName.textContent = 'Nenhum arquivo escolhido';
                    restoreSiteFileName.style.color = 'var(--text-muted)';
                }
            });
        }
        
        // ** INÍCIO DA CORREÇÃO DO ERRO DE SINTAXE (que você tinha) **
        function openRestoreSiteModal() {
            if (restoreSiteModal) {
                restoreSiteSelect.innerHTML = '<option value="">Carregando lista de sites...</option>';
                
                const populateSelect = (sites) => {
                    restoreSiteSelect.innerHTML = '<option value="">-- Selecione um site para sobrescrever --</option>';
                    sites.forEach(site => {
                        let clean_domain = site.conf_name.replace('.conf', '');
                        restoreSiteSelect.innerHTML += `<option value="${site.conf_name}">${clean_domain}</option>`;
                    });
                };
                
                // Busca a lista "fresca" toda vez (corrigido)
                fetch('./api/list_backup_sites.php')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.sites) {
                            allBackupableSites = data.sites; // Atualiza a cache
                            populateSelect(allBackupableSites);
                        }
                    });
                // ** O BLOCO DUPLICADO QUE CAUSAVA O ERRO FOI REMOVIDO DAQUI **
                
                restoreSiteFeedback.style.display = 'none';
                restoreSiteForm.reset();
                restoreSiteFileName.textContent = 'Nenhum arquivo escolhido';
                restoreSiteModal.classList.add('show');
            }
        }
        // ** FIM DA CORREÇÃO DO ERRO DE SINTAXE **
        
        if(restoreSiteSelect) {
            restoreSiteSelect.addEventListener('change', () => {
                // CORREÇÃO: Procura por 'conf_name' (o ID do site)
                const selectedSite = allBackupableSites.find(s => s.conf_name === restoreSiteSelect.value);
                if(selectedSite) {
                    restoreSiteRootPath.value = selectedSite.root_path;
                } else {
                    restoreSiteRootPath.value = '';
                }
            });
        }
        function closeRestoreSiteModal() { if (restoreSiteModal) restoreSiteModal.classList.remove('show'); }
        if (closeRestoreSiteModalBtn) closeRestoreSiteModalBtn.addEventListener('click', closeRestoreSiteModal);
        if (restoreSiteModal) restoreSiteModal.addEventListener('click', (e) => { if (e.target === restoreSiteModal) closeRestoreSiteModal(); });
        if (restoreSiteForm) { restoreSiteForm.addEventListener('submit', async (e) => { e.preventDefault(); const submitButton = restoreSiteForm.querySelector('button[type="submit"]'); submitButton.disabled = true; submitButton.textContent = 'Enviando arquivo...'; restoreSiteFeedback.style.display = 'block'; restoreSiteFeedback.style.color = 'var(--text-muted)'; restoreSiteFeedback.textContent = 'Aguarde, enviando arquivo...'; const formData = new FormData(restoreSiteForm); try { const response = await fetch('./api/upload_and_queue_site_restore.php', { method: 'POST', body: formData }); if(response.status === 403) { window.location.href = 'login.php'; return; } const result = await response.json(); if (result.success) { restoreSiteFeedback.style.color = '#4CAF50'; restoreSiteFeedback.textContent = result.message; setTimeout(() => { closeRestoreSiteModal(); if (typeof loadBackupList === 'function') loadBackupList(); }, 3000); } else { restoreSiteFeedback.style.color = '#f44336'; restoreSiteFeedback.textContent = 'Erro: ' + result.message; } } catch (error) { restoreSiteFeedback.style.color = '#f44336'; restoreSiteFeedback.textContent = 'Erro fatal no upload. O arquivo pode ser muito grande.'; } finally { submitButton.disabled = false; submitButton.textContent = 'Iniciar Upload e Restauração'; } }); }

        // --- Modal Linkar Database ---
        const linkDbModal = document.getElementById('linkDbModal');
        const closeLinkDbModalBtn = document.getElementById('closeLinkDbModalBtn');
        const linkDbForm = document.getElementById('linkDbForm');
        const linkDbSelect = document.getElementById('link-db-select');
        const linkConfNameInput = document.getElementById('link_conf_name');
        const linkDbModalTitle = document.getElementById('link-db-modal-title');
        let managedDatabasesList = []; // Cache para a lista de DBs
        function openLinkDbModal(conf_name, current_db_id) {
            if (linkDbModal) {
                linkConfNameInput.value = conf_name;
                linkDbModalTitle.textContent = `Linkar DB para: ${conf_name.replace('.conf', '')}`;
                linkDbSelect.innerHTML = '<option value="">Carregando...</option>';
                const populateDbSelect = (databases) => {
                    linkDbSelect.innerHTML = '';
                    linkDbSelect.innerHTML += `<option value="none">(Nenhum / Deslinkar)</option>`;
                    databases.forEach(db => {
                        linkDbSelect.innerHTML += `<option value="${db.id}">${db.db_name} (Usuário: ${db.db_user})</option>`;
                    });
                    linkDbSelect.value = current_db_id || 'none';
                };
                
                // CORREÇÃO: Busca a lista "fresca" toda vez
                fetch('./api/list_databases.php')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.databases) {
                            managedDatabasesList = data.databases; // Atualiza a cache
                            populateDbSelect(managedDatabasesList);
                        }
                    });
                // FIM DA CORREÇÃO
                
                linkDbModal.classList.add('show');
            }
        }
        function closeLinkDbModal() { if (linkDbModal) linkDbModal.classList.remove('show'); }
        if (closeLinkDbModalBtn) closeLinkDbModalBtn.addEventListener('click', closeLinkDbModal);
        if (linkDbModal) linkDbModal.addEventListener('click', (e) => { if (e.target === linkDbModal) closeLinkDbModal(); });
        if (linkDbForm) { linkDbForm.addEventListener('submit', async (e) => { e.preventDefault(); const submitButton = linkDbForm.querySelector('button[type="submit"]'); submitButton.disabled = true; const payload = { conf_name: linkConfNameInput.value, db_id: linkDbSelect.value }; try { const response = await fetch('./api/link_site_to_db.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }); const result = await response.json(); if (result.success) { closeLinkDbModal(); if (typeof loadFullBackupList === 'function') { loadFullBackupList(); } } else { alert('Erro: ' + result.message); } } catch (error) { alert('Erro fatal ao conectar-se à API.'); } finally { submitButton.disabled = false; } }); }


        // --- 3. LÓGICA DOS MÓDULOS (GLOBAL) ---
		
		// --- Helper para formatar Bytes ---
        function formatBytes(bytes, decimals = 2) {
            if (!+bytes) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
        }

        function formatDateToLocale(dateString) {
            if (!dateString) return '—';
            const parsed = new Date(dateString);
            if (Number.isNaN(parsed.getTime())) {
                return '—';
            }
            return parsed.toLocaleString('pt-BR');
        }

        function updateTextContent(id, value, fallback = '—') {
            const element = document.getElementById(id);
            if (!element) return;
            if (value === null || value === undefined || value === '') {
                element.textContent = fallback;
            } else {
                element.textContent = value;
            }
        }

        function updateWarningsList(warnings) {
            const container = document.getElementById('dashboard-overview-alerts');
            if (!container) return;

            if (!warnings || warnings.length === 0) {
                container.textContent = 'Nenhum alerta.';
                container.classList.remove('has-warning');
                return;
            }

            container.classList.add('has-warning');
            container.innerHTML = '';
            warnings.forEach((message) => {
                const span = document.createElement('span');
                span.textContent = message;
                container.appendChild(span);
            });
        }
        
        
        function loadScript(src, id) {
            return new Promise((resolve, reject) => {
                if (document.getElementById(id)) {
                    resolve(); // Já carregado
                    return;
                }
                const script = document.createElement('script');
                script.id = id;
                script.src = src;
                script.onload = () => resolve();
                script.onerror = () => reject(new Error(`Falha ao carregar script: ${src}`));
                document.head.appendChild(script);
            });
        }
        
        let statsInterval; // Timer para o Dashboard e Monitor
        let overviewInterval; // Timer para o overview do Dashboard
        let fileEditorInstance = null;
        let fileClipboard = { action: null, path: null };
        let currentFileViewMode = localStorage.getItem('fileViewMode') || 'details';

        // MÓDULO DO DASHBOARD e MONITOR
        async function fetchSystemStats() { try { const response = await fetch('./api/system_stats.php?t=' + new Date().getTime()); if(response.status === 403) { window.location.href = 'login.php'; return; } const data = await response.json(); if (document.getElementById('load-percent')) document.getElementById('load-percent').textContent = data.load_percent + '%'; if (document.getElementById('load-avg')) document.getElementById('load-avg').textContent = data.load_avg; if (document.getElementById('cpu-cores')) document.getElementById('cpu-cores').textContent = data.cpu_cores; if (document.getElementById('ram-percent')) document.getElementById('ram-percent').textContent = data.ram_percent + '%'; if (document.getElementById('ram-used')) document.getElementById('ram-used').textContent = data.ram_used_mb; if (document.getElementById('ram-total')) document.getElementById('ram-total').textContent = data.ram_total_mb; if (document.getElementById('disk-percent')) document.getElementById('disk-percent').textContent = data.disk_root_percent + '%'; if (document.getElementById('disk-used')) document.getElementById('disk-used').textContent = data.disk_root_gb; if (document.getElementById('disk-total')) document.getElementById('disk-total').textContent = data.disk_root_total_gb; } catch (error) { console.error('Erro ao buscar estatísticas do sistema:', error); if (statsInterval) clearInterval(statsInterval); } }
        async function fetchDashboardOverview() {
            try {
                const response = await fetch('./api/dashboard_overview.php?t=' + new Date().getTime());
                if(response.status === 403) { window.location.href = 'login.php'; return; }
                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.message || 'Falha ao obter overview do dashboard.');
                }

                const modules = data.modules || {};

                const websites = modules.websites || {};
                updateTextContent('websites-total', websites.total);
                updateTextContent('websites-active', websites.active);
                updateTextContent('websites-inactive', websites.inactive);

                const backups = modules.backups || {};
                updateTextContent('backups-tracked', backups.tracked_sites);
                updateTextContent('backups-with-backup', backups.with_backup);
                updateTextContent('backups-without-backup', backups.without_backup);
                updateTextContent('backups-stale', backups.stale_backups);
                if (backups.latest) {
                    updateTextContent('backups-latest-site', backups.latest.site);
                    updateTextContent('backups-latest-date', formatDateToLocale(backups.latest.date));
                    updateTextContent('backups-latest-size', Number.isFinite(backups.latest.size_bytes) ? formatBytes(backups.latest.size_bytes) : '—');
                } else {
                    updateTextContent('backups-latest-site', '—');
                    updateTextContent('backups-latest-date', '—');
                    updateTextContent('backups-latest-size', '—');
                }

                const databases = modules.databases || {};
                updateTextContent('databases-total', databases.total);
                updateTextContent('databases-with-backup', databases.with_backup);
                updateTextContent('databases-without-backup', databases.without_backup);
                updateTextContent('databases-stale', databases.stale_backups);
                if (databases.latest) {
                    updateTextContent('databases-latest-name', databases.latest.name);
                    updateTextContent('databases-latest-date', formatDateToLocale(databases.latest.date));
                    updateTextContent('databases-latest-size', Number.isFinite(databases.latest.size_bytes) ? formatBytes(databases.latest.size_bytes) : '—');
                } else {
                    updateTextContent('databases-latest-name', '—');
                    updateTextContent('databases-latest-date', '—');
                    updateTextContent('databases-latest-size', '—');
                }

                const fullBackups = modules.full_backups || {};
                updateTextContent('full-backup-status', fullBackups.has_archive ? 'Disponível' : 'Não encontrado');
                updateTextContent('full-backup-filename', fullBackups.filename);
                updateTextContent('full-backup-updated', fullBackups.last_modified ? formatDateToLocale(fullBackups.last_modified) : '—');
                updateTextContent('full-backup-size', fullBackups.has_archive && Number.isFinite(fullBackups.size_bytes) ? formatBytes(fullBackups.size_bytes) : '—');

                const cron = modules.cron || {};
                updateTextContent('cron-total', cron.total);
                updateTextContent('cron-active', cron.active);
                updateTextContent('cron-inactive', cron.inactive);

                const security = modules.security || {};
                updateTextContent('security-total-rules', security.total_rules);
                updateTextContent('security-allow', security.allow_rules);
                updateTextContent('security-deny', security.deny_rules);

                const logs = modules.logs || {};
                updateTextContent('logs-total', logs.total_options);
                updateTextContent('logs-task', logs.task_entries);
                updateTextContent('logs-file', logs.file_entries);

                const queue = modules.queue || {};
                updateTextContent('queue-pending', queue.pending);
                updateTextContent('queue-processing', queue.processing);
                updateTextContent('queue-complete', queue.complete);
                updateTextContent('queue-failed', queue.failed);
                updateTextContent('queue-file-pending', queue.file_operations_pending);
                updateTextContent('queue-oldest', queue.oldest_pending ? formatDateToLocale(queue.oldest_pending) : '—');

                const settings = modules.settings || {};
                updateTextContent('settings-version', settings.panel_version);
                const generatedSource = settings.generated_at || data.generated_at;
                updateTextContent('settings-generated', generatedSource ? formatDateToLocale(generatedSource) : '—');

                updateTextContent('dashboard-overview-updated', data.generated_at ? formatDateToLocale(data.generated_at) : '—');
                updateWarningsList(data.warnings || []);
            } catch (error) {
                console.error('Erro ao obter overview do dashboard:', error);
                updateWarningsList(['Erro ao obter overview do dashboard.']);
            }
        }

        function initializeDashboard() { fetchSystemStats(); fetchDashboardOverview(); if (statsInterval) clearInterval(statsInterval); statsInterval = setInterval(fetchSystemStats, 5000); if (overviewInterval) clearInterval(overviewInterval); overviewInterval = setInterval(fetchDashboardOverview, 60000); }
        function initializeMonitorPage() { fetchSystemStats(); if (statsInterval) clearInterval(statsInterval); statsInterval = setInterval(fetchSystemStats, 5000); if (overviewInterval) clearInterval(overviewInterval); }
        function clearStatsInterval() { if (statsInterval) clearInterval(statsInterval); if (overviewInterval) clearInterval(overviewInterval); }

        // MÓDULO WEBSITES
        async function loadSitesList() {
            const tableBody = document.getElementById('sites-table-body');
            if (!tableBody) return; 
            tableBody.innerHTML = '<tr><td colspan="4" style="text-align:center;">Carregando...</td></tr>';
            try {
                const response = await fetch('./api/list_websites.php?t=' + new Date().getTime());
                if(response.status === 403) { window.location.href = 'login.php'; return; }
                const data = await response.json();
                tableBody.innerHTML = ''; 
                if (data.error && (!data.sites || data.sites.length === 0)) {
                    tableBody.innerHTML = `<tr><td colspan="4" style="color: #f44336; text-align: center;">${data.error}</td></tr>`;
                    return;
                }
                if (!data.sites || data.sites.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="4" style="text-align: center;">Nenhum site encontrado.</td></tr>';
                    return;
                }
                data.sites.forEach(site => {
                    const statusClass = site.status === 'Ativo' ? 'status-active' : 'status-inactive';
                    const row = `
                        <tr>
                            <td data-label="Domínio">${site.domain}</td>
                            <td data-label="Servidor">${site.webserver}</td>
                            <td data-label="Status"><span class="${statusClass}">${site.status}</span></td>
                            <td data-label="Ações">
                                <button class="action-btn btn-config-nginx" 
                                        data-domain="${site.domain}" 
                                        data-path="${site.path}">Config</button>
                                <button class="action-btn btn-delete-site" 
                                        style="color: #f44336;"
                                        data-domain="${site.domain}" 
                                        data-path="${site.path}">Excluir</button>
                            </td>
                        </tr>
                    `;
                    tableBody.innerHTML += row;
                });
            } catch (error) {
                console.error('Erro ao carregar lista de sites:', error);
                tableBody.innerHTML = '<tr><td colspan="4" style="color: #f44336; text-align: center;">Erro ao carregar módulo. Verifique o console.</td></tr>';
            }
        }
        async function openNginxConfigModal(domain, path) { if (!nginxConfigModal) return; nginxConfigModal.classList.add('show'); nginxModalTitle.textContent = `Configurar: ${domain}`; nginxConfigForm.style.display = 'none'; nginxModalLoading.style.display = 'block'; nginxModalLoading.innerHTML = '<span class="loading">A ler arquivo de configuração...</span>'; nginxConfigForm.reset(); try { const response = await fetch(`./api/get_nginx_config.php?path=${encodeURIComponent(path)}`); if(response.status === 403) { window.location.href = 'login.php'; return; } const data = await response.json(); if (!data.success) { throw new Error(data.message); } document.getElementById('conf_server_name').value = data.config.server_name; document.getElementById('conf_root').value = data.config.root; document.getElementById('conf_index').value = data.config.index; document.getElementById('conf_php_socket').value = data.config.php_socket || 'disabled'; document.getElementById('config_file_path').value = data.file_path; nginxModalLoading.style.display = 'none'; nginxConfigForm.style.display = 'block'; } catch (error) { console.error('Erro ao carregar configuração Nginx:', error); nginxModalLoading.innerHTML = `<span style="color: #f44336;">Erro: ${error.message}</span>`; } }
        async function queueSiteDeletion(domain, path) { if (!confirm(`Tem a certeza que deseja excluir o site ${domain}?\n\nEsta ação não pode ser desfeita.`)) { return; } try { const response = await fetch('./api/queue_delete_website.php', { method: 'POST', headers: { 'Content-Type': 'application/json', }, body: JSON.stringify({ domain: domain, path: path }) }); if(response.status === 403) { window.location.href = 'login.php'; return; } const result = await response.json(); if (result.success) { alert(result.message); if (typeof loadSitesList === 'function') { loadSitesList(); } } else { alert('Erro: ' + result.message); } } catch (error) { console.error('Erro ao excluir site:', error); alert('Erro fatal ao conectar-se à API.'); } }
        
        // MÓDULO DATABASES
        async function loadDatabaseList() {
            const tableBody = document.getElementById('databases-table-body');
            if (!tableBody) return;
            tableBody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Carregando...</td></tr>';
            try {
                const response = await fetch('./api/list_databases.php?t=' + new Date().getTime());
                if(response.status === 403) { window.location.href = 'login.php'; return; }
                const data = await response.json();
                if (!data.success) throw new Error(data.message);
                tableBody.innerHTML = '';
                if (data.databases.length === 0) {
                     tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Nenhum banco de dados gerenciado.</td></tr>';
                     return;
                }
                data.databases.forEach(db => {
                    let downloadLink = '';
                    let restoreButton = '';
                    let backupSize = 'N/A';
                    let backupDate = 'N/A'; 
                    if (db.last_backup_file) {
                        downloadLink = `<a href="api/download_backup.php?db_id=${db.id}" class="action-link" title="${db.last_backup_file}" download>Download</a>`;
                        restoreButton = `<button class="action-btn btn-restore-database" 
                                            data-id="${db.id}" 
                                            data-name="${db.db_name}"
                                            title="Restaurar ${db.last_backup_file}">Restaurar</button>`;
                        backupSize = formatBytes(db.last_backup_size);
                        if (db.last_backup_date) {
                            const date = new Date(db.last_backup_date + ' UTC');
                            backupDate = date.toLocaleString('pt-BR');
                        }
                    } else {
                        downloadLink = `<a class="action-link disabled" title="Nenhum backup" href="#">Download</a>`;
                        restoreButton = `<button class="action-btn" disabled 
                                            title="Crie um backup primeiro">Restaurar</button>`;
                    }
                    const row = `
                        <tr>
                            <td data-label="Nome do Banco">${db.db_name}</td>
                            <td data-label="Usuário">${db.db_user}</td>
                            <td data-label="Download">${downloadLink}</td>
                            <td data-label="Data">${backupDate}</td>
                            <td data-label="Tamanho">${backupSize}</td>
                            <td data-label="Ações">
                                <button class="action-btn btn-backup-database" 
                                        data-id="${db.id}" 
                                        data-name="${db.db_name}">Backup</button>
                                ${restoreButton}
                                <button class="action-btn btn-delete-database" 
                                        data-id="${db.id}" 
                                        data-name="${db.db_name}"
                                        style="color: #f44336;">Excluir</button>
                            </td>
                        </tr>
                    `;
                    tableBody.innerHTML += row;
                });
            } catch (error) {
                console.error('Erro ao carregar lista de DBs:', error);
                tableBody.innerHTML = `<tr><td colspan="6" style="color:red;text-align:center;">Erro ao carregar: ${error.message}</td></tr>`;
            }
        }
        async function queueDatabaseDeletion(db_id, db_name) { if (!confirm(`Tem a certeza que deseja excluir o banco '${db_name}'?\n\nO banco E o usuário associado serão removidos.\nEsta ação não pode ser desfeita.`)) { return; } try { const response = await fetch('./api/queue_delete_database.php', { method: 'POST', headers: { 'Content-Type': 'application/json', }, body: JSON.stringify({ db_id: db_id }) }); if(response.status === 403) { window.location.href = 'login.php'; return; } const result = await response.json(); if (result.success) { alert(result.message); if (typeof loadDatabaseList === 'function') { loadDatabaseList(); } } else { alert('Erro: ' + result.message); } } catch (error) { console.error('Erro ao excluir DB:', error); alert('Erro fatal ao conectar-se à API.'); } }
        async function queueDatabaseBackup(db_id, db_name) { if (!confirm(`Deseja criar um novo backup para o banco '${db_name}' agora?\n\nO último backup (se houver) não será substituído.`)) return; const btn = document.querySelector(`.btn-backup-database[data-id="${db_id}"]`); if(btn) btn.disabled = true; try { const response = await fetch('./api/queue_backup_database.php', { method: 'POST', headers: { 'Content-Type': 'application/json', }, body: JSON.stringify({ db_id: db_id }) }); if(response.status === 403) { window.location.href = 'login.php'; return; } const result = await response.json(); alert(result.message); setTimeout(() => { if (typeof loadDatabaseList === 'function') loadDatabaseList(); }, 10000); } catch (error) { alert('Erro fatal ao conectar-se à API.'); if(btn) btn.disabled = false; } }
        async function queueDatabaseRestore(db_id, db_name) { const warning = `TEM A CERTEZA?\n\nVocê está prestes a SOBRESCREVER o banco de dados '${db_name}' com o último backup.\n\nTodos os dados atuais serão PERDIDOS.\n\nEsta ação não pode ser desfeita.`; if (!confirm(warning)) return; const btn = document.querySelector(`.btn-restore-database[data-id="${db_id}"]`); if(btn) btn.disabled = true; try { const response = await fetch('./api/queue_restore_database.php', { method: 'POST', headers: { 'Content-Type': 'application/json', }, body: JSON.stringify({ db_id: db_id }) }); if(response.status === 403) { window.location.href = 'login.php'; return; } const result = await response.json(); alert(result.message); setTimeout(() => { if (typeof loadDatabaseList === 'function') loadDatabaseList(); }, 5000); } catch (error) { alert('Erro fatal ao conectar-se à API.'); if(btn) btn.disabled = false; } }
        function initializeDatabasesPage() { if (typeof loadDatabaseList === 'function') { loadDatabaseList(); } }

        // MÓDULO SETTINGS
        function initializeSettingsPage() { const form = document.getElementById('change-password-form'); if (!form) return; const feedbackEl = document.getElementById('settings-feedback'); const submitBtn = document.getElementById('change-pass-btn'); form.addEventListener('submit', async (e) => { e.preventDefault(); submitBtn.disabled = true; submitBtn.textContent = 'A salvar...'; feedbackEl.style.display = 'none'; const formData = { senha_antiga: document.getElementById('senha_antiga').value, nova_senha: document.getElementById('nova_senha').value, confirmar_senha: document.getElementById('confirmar_senha').value }; try { const response = await fetch('./api/change_password.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(formData) }); if(response.status === 403) { window.location.href = 'login.php'; return; } const result = await response.json(); feedbackEl.textContent = result.message; feedbackEl.style.color = result.success ? '#4CAF50' : '#f44336'; feedbackEl.style.display = 'block'; if (result.success) form.reset(); } catch (error) { console.error('Erro ao alterar senha:', error); feedbackEl.textContent = 'Erro de conexão com a API.'; feedbackEl.style.color = '#f44336'; feedbackEl.style.display = 'block'; } finally { submitBtn.disabled = false; submitBtn.textContent = 'Salvar Nova Senha'; } }); }
        
        // MÓDULO LOGS
        function initializeLogsPage() { const logSelector = document.getElementById('log-selector'); const logContentArea = document.getElementById('log-content-area'); const logRefreshBtn = document.getElementById('log-refresh-btn'); if (!logSelector) return; async function loadLogContent(filename) { if (!filename) { logContentArea.textContent = 'Por favor, selecione um arquivo de log para visualizar.'; logContentArea.classList.add('loading'); return; } logContentArea.textContent = 'A ler arquivo de log...'; logContentArea.classList.add('loading'); try { const response = await fetch(`./api/get_log_content.php?file=${encodeURIComponent(filename)}`); if(response.status === 403) { window.location.href = 'login.php'; return; } const data = await response.json(); if (data.success) { logContentArea.textContent = data.content || '(Arquivo de log vazio)'; } else { logContentArea.textContent = `Erro: ${data.content}`; } } catch (error) { logContentArea.textContent = `Erro de conexão ao carregar log: ${error.message}`; } finally { logContentArea.classList.remove('loading'); } } async function loadLogList() { logSelector.disabled = true; logSelector.innerHTML = '<option value="">A carregar lista...</option>'; try { const response = await fetch('./api/list_logs.php'); if(response.status === 403) { window.location.href = 'login.php'; return; } const data = await response.json(); if (data.success && data.logs.length > 0) { logSelector.innerHTML = '<option value="">-- Selecione um log --</option>'; data.logs.forEach(logItem => { const option = document.createElement('option'); option.value = logItem.value; option.textContent = logItem.text; if (logItem.disabled) option.disabled = true; logSelector.appendChild(option); }); } else { logSelector.innerHTML = '<option value="">Nenhum log encontrado.</option>'; } } catch (error) { logSelector.innerHTML = '<option value="">Erro ao carregar lista.</option>'; } finally { logSelector.disabled = false; } } logSelector.addEventListener('change', () => loadLogContent(logSelector.value)); logRefreshBtn.addEventListener('click', () => { const currentFile = logSelector.value; loadLogList().then(() => { logSelector.value = currentFile; loadLogContent(currentFile); }); }); loadLogList(); }

        // MÓDULO BACKUPS DE SITE
        async function loadBackupList() {
            const tableBody = document.getElementById('backups-table-body');
            if (!tableBody) return;
            tableBody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Analisando sites e backups...</td></tr>';
            try {
                const response = await fetch('./api/list_backup_sites.php?t=' + new Date().getTime());
                if(response.status === 403) { window.location.href = 'login.php'; return; }
                const data = await response.json();
                if (!data.success) throw new Error(data.message);
                allBackupableSites = data.sites; 
                tableBody.innerHTML = '';
                if (data.sites.length === 0) {
                     tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Nenhum site encontrado. (O worker pode estar a sincronizar, aguarde 1 min).</td></tr>';
                     return;
                }
                data.sites.forEach(site => {
                    let clean_domain = site.conf_name.replace('.conf', '');
                    let downloadLink = ''; 
                    let backupDate = 'N/A';
                    let backupSize = 'N/A';
                    let restoreButton = `<button class="action-btn" disabled title="Crie um backup primeiro">Restaurar</button>`;
                    let deleteButton = `<button class="action-btn" disabled style="color: #f44336;">Excluir</button>`;
                    if (site.last_backup_file) {
                        downloadLink = `<a href="api/download_site_backup.php?conf_name=${site.conf_name}" class="action-link" title="${site.last_backup_file}" download>Download</a>`;
                        const date = new Date(site.last_backup_date + ' UTC');
                        backupDate = date.toLocaleString('pt-BR');
                        backupSize = formatBytes(site.last_backup_size);
                        restoreButton = `<button class="action-btn btn-restore-site" 
                                            data-domain="${site.conf_name}" 
                                            data-root="${site.root_path}"
                                            data-file="${site.last_backup_file}"
                                            title="Restaurar ${site.last_backup_file}">Restaurar</button>`;
                        deleteButton = `<button class="action-btn btn-delete-site-backup" 
                                            data-file="${site.last_backup_file}"
                                            data-domain="${site.conf_name}"
                                            style="color: #f44336;">Excluir</button>`;
                    } else {
                        downloadLink = `<a class="action-link disabled" title="Nenhum backup" href="#">Download</a>`;
                    }
                    const row = `
                        <tr>
                            <td data-label="Nome do Site">${clean_domain}</td>
                            <td data-label="Caminho (Root)">${site.root_path}</td>
                            <td data-label="Download">${downloadLink}</td>
                            <td data-label="Data">${backupDate}</td>
                            <td data-label="Tamanho">${backupSize}</td>
                            <td data-label="Ações">
                                <button class="action-btn btn-backup-site" 
                                        data-domain="${site.conf_name}" 
                                        data-root="${site.root_path}">Backup</button>
                                ${restoreButton}
                                ${deleteButton}
                            </td>
                        </tr>
                    `;
                    tableBody.innerHTML += row;
                });
            } catch (error) {
                console.error('Erro ao carregar lista de Backups:', error);
                tableBody.innerHTML = `<tr><td colspan="6" style="color:red;text-align:center;">Erro ao carregar: ${error.message}</td></tr>`;
            }
        }
        async function queueSiteBackup(domain, root_path) { if (!confirm(`Deseja criar um novo backup para o site '${domain}' agora?`)) return; const btn = document.querySelector(`.btn-backup-site[data-domain="${domain}"]`); if(btn) btn.disabled = true; try { const response = await fetch('./api/queue_backup_site.php', { method: 'POST', headers: { 'Content-Type': 'application/json', }, body: JSON.stringify({ domain: domain, root_path: root_path }) }); if(response.status === 403) { window.location.href = 'login.php'; return; } const result = await response.json(); alert(result.message); setTimeout(() => { if (typeof loadBackupList === 'function') loadBackupList(); }, 10000); } catch (error) { alert('Erro fatal ao conectar-se à API.'); if(btn) btn.disabled = false; } }
        async function queueSiteRestore(domain, root_path, backup_file) { const warning = `TEM A CERTEZA?\n\nVocê irá SOBRESCREVER o site '${domain}' com o backup '${backup_file}'.\n\nTodos os arquivos atuais do site serão APAGADOS.\n\nEsta ação não pode ser desfeita.`; if (!confirm(warning)) return; const btn = document.querySelector(`.btn-restore-site[data-domain="${domain}"]`); if(btn) btn.disabled = true; try { const response = await fetch('./api/queue_restore_site.php', { method: 'POST', headers: { 'Content-Type': 'application/json', }, body: JSON.stringify({ domain: domain, root_path: root_path, backup_file: backup_file }) }); if(response.status === 403) { window.location.href = 'login.php'; return; } const result = await response.json(); alert(result.message); setTimeout(() => { if (typeof loadBackupList === 'function') loadBackupList(); }, 5000); } catch (error) { alert('Erro fatal ao conectar-se à API.'); if(btn) btn.disabled = false; } }
        async function queueDeleteSiteBackup(backup_file, conf_name) { if (!confirm(`Tem a certeza que deseja excluir o arquivo de backup '${backup_file}'?\n\nEsta ação não pode ser desfeita.`)) return; const btn = document.querySelector(`.btn-delete-site-backup[data-file="${backup_file}"]`); if(btn) btn.disabled = true; try { const response = await fetch('./api/queue_delete_site_backup.php', { method: 'POST', headers: { 'Content-Type': 'application/json', }, body: JSON.stringify({ backup_file: backup_file, conf_name: conf_name }) }); if(response.status === 403) { window.location.href = 'login.php'; return; } const result = await response.json(); alert(result.message); if (typeof loadBackupList === 'function') loadBackupList(); } catch (error) { alert('Erro fatal ao conectar-se à API.'); if(btn) btn.disabled = false; } }
        function initializeBackupsPage() { if (typeof loadBackupList === 'function') { loadBackupList(); } }


// --- ADICIONE ESTA NOVA FUNÇÃO ---
        async function queueClipboardAction(action, sourcePath, destDir) {
            const actionType = (action === 'cut') ? 'mover' : 'copiar';
            const fileName = sourcePath.split('/').pop();
            const destPath = destDir === '/' ? `/${fileName}` : `${destDir}/${fileName}`;
            
            const apiEndpoint = (action === 'cut') ? './api/queue_move.php' : './api/queue_copy.php';

            if (!confirm(`Tem a certeza que quer ${actionType} "${sourcePath}" para "${destPath}"?`)) {
                return;
            }
            
            const payload = {
                source_path: sourcePath,
                dest_dir: destDir
            };

            try {
                const response = await fetch(apiEndpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                
                if(response.status === 403) { window.location.href = 'login.php'; return; }
                const result = await response.json();

                if (result.success) {
                    alert(result.message);
                    // Se foi "Cortar" (mover), limpa o clipboard
                    if (action === 'cut') {
                        fileClipboard = { action: null, path: null };
                    }
                    loadFileList(currentFilePath); // Atualiza a lista de arquivos
                } else {
                    throw new Error(result.error);
                }
            } catch (error) {
                alert('Erro: ' + error.message);
            }
        }


        // MÓDULO FULL BACKUPS
        async function loadFullBackupList() {
            const tableBody = document.getElementById('full-backups-table-body');
            if (!tableBody) return;
            tableBody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Analisando sites e backups...</td></tr>';
            try {
                const response = await fetch('./api/list_full_backup_sites.php?t=' + new Date().getTime());
                if(response.status === 403) { window.location.href = 'login.php'; return; }
                const data = await response.json();
                if (!data.success) throw new Error(data.message);
                allBackupableSites = data.sites; 
                tableBody.innerHTML = '';
                if (data.sites.length === 0) {
                     tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Nenhum site encontrado. (O worker pode estar a sincronizar, aguarde 1 min).</td></tr>';
                     return;
                }
                data.sites.forEach(site => {
                    let clean_domain = site.conf_name.replace('.conf', '');
                    let downloadLink = '';
                    let backupDate = 'N/A';
                    let backupSize = 'N/A';
                    let restoreButton = `<button class="action-btn" disabled title="Crie um backup primeiro">Restaurar</button>`;
                    let deleteButton = `<button class="action-btn" disabled style="color: #f44336;">Excluir</button>`;
                    let dbLinkUI = '';
                    if (site.linked_db_id && site.db_name) {
                        dbLinkUI = `<strong>${site.db_name}</strong><br><button class="action-btn btn-link-db" data-conf="${site.conf_name}" data-dbid="${site.linked_db_id}" style="margin-top: 5px;">Alterar</button>`;
                    } else {
                        dbLinkUI = `<span style="color: var(--text-muted)">Nenhum</span><br><button class="action-btn btn-link-db" data-conf="${site.conf_name}" data-dbid="" style="margin-top: 5px;">Linkar DB</button>`;
                    }
                    let backupButton = `<button class="action-btn btn-full-backup" 
                                            data-domain="${site.conf_name}" 
                                            data-root="${site.root_path}"
                                            data-dbid="${site.linked_db_id}">Backup</button>`;
                    if (!site.linked_db_id) {
                        backupButton = `<button class="action-btn" disabled title="Linke um banco de dados primeiro para fazer um Full Backup">Backup</button>`;
                    }
                    if (site.last_backup_file) {
                        downloadLink = `<a href="api/download_site_backup.php?conf_name=${site.conf_name}" class="action-link" title="${site.last_backup_file}" download>Download</a>`;
                        const date = new Date(site.last_backup_date + ' UTC');
                        backupDate = date.toLocaleString('pt-BR');
                        backupSize = formatBytes(site.last_backup_size);
                        restoreButton = `<button class="action-btn btn-restore-site" 
                                            data-domain="${site.conf_name}" 
                                            data-root="${site.root_path}"
                                            data-file="${site.last_backup_file}"
                                            title="Restaurar ${site.last_backup_file}">Restaurar</button>`;
                        deleteButton = `<button class="action-btn btn-delete-site-backup" 
                                            data-file="${site.last_backup_file}"
                                            data-domain="${site.conf_name}"
                                            style="color: #f44336;">Excluir</button>`;
                    } else {
                        downloadLink = `<a class="action-link disabled" title="Nenhum backup" href="#">Download</a>`;
                    }
                    const row = `
                        <tr>
                            <td data-label="Nome do Site">${clean_domain}</td>
                            <td data-label="Banco Linkado">${dbLinkUI}</td>
                            <td data-label="Download">${downloadLink}</td>
                            <td data-label="Data">${backupDate}</td>
                            <td data-label="Tamanho">${backupSize}</td>
                            <td data-label="Ações">
                                ${backupButton}
                                ${restoreButton}
                                ${deleteButton}
                            </td>
                        </tr>
                    `;
                    tableBody.innerHTML += row;
                });
            } catch (error) {
                console.error('Erro ao carregar lista de Full Backups:', error);
                tableBody.innerHTML = `<tr><td colspan="6" style="color:red;text-align:center;">Erro ao carregar: ${error.message}</td></tr>`;
            }
        }
        async function queueFullBackup(domain, root_path, db_id) { if (!confirm(`Deseja criar um novo "Full Backup" (Arquivos + Banco de Dados) para o site '${domain.replace('.conf','')}' agora?\n\nO backup anterior será sobrescrito.`)) return; const btn = document.querySelector(`.btn-full-backup[data-domain="${domain}"]`); if(btn) btn.disabled = true; try { const response = await fetch('./api/queue_full_backup.php', { method: 'POST', headers: { 'Content-Type': 'application/json', }, body: JSON.stringify({ domain: domain, root_path: root_path, db_id: db_id }) }); if(response.status === 403) { window.location.href = 'login.php'; return; } const result = await response.json(); alert(result.message); setTimeout(() => { if (typeof loadFullBackupList === 'function') loadFullBackupList(); }, 10000); } catch (error) { alert('Erro fatal ao conectar-se à API.'); if(btn) btn.disabled = false; } }
        async function queueBackupAllSites() { if (!confirm(`Deseja enfileirar um "Full Backup" para TODOS os sites que possuem um banco de dados linkado?\n\nIsso pode demorar e irá sobrescrever os backups existentes.`)) return; const btn = document.getElementById('btnBackupAllSites'); if(btn) btn.disabled = true; try { const response = await fetch('./api/queue_backup_all_sites.php', { method: 'POST' }); if(response.status === 403) { window.location.href = 'login.php'; return; } const result = await response.json(); alert(result.message); setTimeout(() => { if (typeof loadFullBackupList === 'function') loadFullBackupList(); }, 10000); } catch (error) { alert('Erro fatal ao conectar-se à API.'); } finally { if(btn) btn.disabled = false; } }
        async function queueGenerateMasterArchive() { if (!confirm(`Deseja criar um arquivo .tar.bz2 contendo TODOS os backups de sites (.tar.gz) existentes?\n\nIsso pode consumir muitos recursos e demorar.`)) return; const btn = document.getElementById('btnGenerateMasterArchive'); if(btn) btn.disabled = true; try { const response = await fetch('./api/queue_generate_master_archive.php', { method: 'POST' }); if(response.status === 403) { window.location.href = 'login.php'; return; } const result = await response.json(); alert(result.message + "\nO botão de download aparecerá quando estiver pronto."); } catch (error) { alert('Erro fatal ao conectar-se à API.'); } finally { if(btn) btn.disabled = false; } }
        async function checkMasterArchiveStatus() { const card = document.getElementById('master-archive-card'); const infoSpan = document.getElementById('master-archive-info'); const downloadBtn = document.getElementById('btnDownloadMasterArchive'); if (!card) return; try { const response = await fetch('./api/get_master_archive_status.php?t=' + new Date().getTime()); const data = await response.json(); if (data.success && data.file_exists) { const date = new Date(data.last_modified * 1000); const size = formatBytes(data.size_bytes); infoSpan.textContent = `Última geração: ${date.toLocaleString('pt-BR')} (${size})`; downloadBtn.href = `api/download_master_archive.php`; card.style.display = 'block'; } else { card.style.display = 'none'; } } catch (e) { card.style.display = 'none'; } }
        async function queueDeleteMasterArchive() { if (!confirm(`Tem a certeza que deseja excluir o Arquivo Mestre?\n\nEsta ação não pode ser desfeita e o arquivo terá que ser gerado novamente.`)) return; const btn = document.getElementById('btnDeleteMasterArchive'); if(btn) btn.disabled = true; try { const response = await fetch('./api/queue_delete_master_archive.php', { method: 'POST' }); if(response.status === 403) { window.location.href = 'login.php'; return; } const result = await response.json(); alert(result.message); const card = document.getElementById('master-archive-card'); if(card) card.style.display = 'none'; } catch (error) { alert('Erro fatal ao conectar-se à API.'); } finally { if(btn) btn.disabled = false; } }
        function initializeFullBackupsPage() { if (typeof loadFullBackupList === 'function') { loadFullBackupList(); } if (typeof checkMasterArchiveStatus === 'function') { checkMasterArchiveStatus(); } }

// --- MÓDULO ARQUIVOS (FILES) ---
        let currentFilePath = '/'; // Mantém o estado do caminho atual


// função genérica para deletar um path e lidar com diretório não-vazio
async function apiDeletePath(path) {
  const payload = { path: path, type: 'dir', force: false };
  try {
    const resp = await fetch('/api/delete_file.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    if (resp.status === 409) {
      // ler body pra extrair mensagem
      let bodyText = await resp.text();
      // tenta parsear JSON, senão usa texto cru
      let parsed;
      try { parsed = JSON.parse(bodyText); } catch(e){ parsed = { error: bodyText }; }

      // detecta o caso de diretório não vazio
      const msg = (parsed && parsed.error) ? parsed.error : bodyText;
      if (typeof msg === 'string' && msg.includes('ERR|dir_not_empty')) {
        // perguntar ao usuário (pode trocar por modal custom)
        const ok = confirm('O diretório não está vazio. Deseja forçar exclusão recursiva? Esta ação é irreversível.');
        if (!ok) return { success: false, cancelled: true };

        // reenvia com recursive = true
        const resp2 = await fetch('/api/delete_file.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ path: path, recursive: true })
        });
        const data2 = await resp2.json().catch(() => ({ success:false, error: 'Resposta inválida do servidor' }));
        if (resp2.ok && data2.success) {
          alert('Diretório removido.');
          return data2;
        } else {
          alert('Falha ao remover: ' + (data2.error || JSON.stringify(data2)));
          return data2;
        }
      } else {
        // outro 409
        alert('Erro: ' + (parsed.error || bodyText));
        return parsed;
      }
    }

    // código 200/201 etc
    const data = await resp.json().catch(() => ({ success:false, error: 'Resposta inválida do servidor' }));
    if (resp.ok && data.success) {
      alert('Removido com sucesso.');
      return data;
    } else {
      alert('Erro: ' + (data.error || JSON.stringify(data)));
      return data;
    }
  } catch (err) {
    console.error('Erro na request:', err);
    alert('Erro de rede ou interno: ' + err.message);
    return { success:false, error: err.message };
  }
}

// Substitua a função existente por esta versão.
// Ela lida com 409, reenvia com recursive:true se confirmado,
// e quando remover com sucesso recarrega O PAI do caminho removido
// para evitar abrir uma pasta inexistente (null).

async function queueFileDeletion(path, opts = {}) {
  // opts pode ter { type: 'file'|'dir', recursive: boolean, force: boolean }
  const type = opts.type || 'dir';
  const initialPayload = { path: path, type: type, recursive: !!opts.recursive, force: !!opts.force };

  console.log('[queueFileDeletion] iniciando', { path, opts });

  try {
    let resp = await fetch('/api/delete_file.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(initialPayload)
    });

    // Ler texto cru (para mensagens de erro não-JSON)
    const raw = await resp.text();
    let data;
    try { data = JSON.parse(raw); } catch (e) { data = { success: resp.ok, raw }; }

    if (resp.status === 409) {
      console.warn('[queueFileDeletion] 409 recebido, body:', raw);

      // detectar diretório não vazio (padrões conhecidos)
      const isDirNotEmpty = raw && (raw.indexOf('ERR|dir_not_empty') !== -1 || raw.toLowerCase().includes('não vazio') || (data && data.error && String(data.error).toLowerCase().includes('não vazio')));

      if (isDirNotEmpty) {
        const want = confirm('O diretório não está vazio. Deseja forçar exclusão recursiva (irreversível)?');
        if (!want) {
          console.log('[queueFileDeletion] usuário cancelou deleção recursiva');
          return { success: false, cancelled: true };
        }

        // reenvia com recursive = true + force
        const payload2 = { path: path, type: type, recursive: true, force: true };
        console.log('[queueFileDeletion] reenviando com recursive:true', payload2);

        const resp2 = await fetch('/api/delete_file.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload2)
        });
        const raw2 = await resp2.text();
        let data2;
        try { data2 = JSON.parse(raw2); } catch (e) { data2 = { success: resp2.ok, raw: raw2 }; }

        if (resp2.ok && data2.success) {
          console.log('[queueFileDeletion] remoção recursiva OK', data2);
          // recarrega o diretório pai (ver lógica abaixo)
          refreshAfterDelete(path, data2);
          return data2;
        } else {
          console.error('[queueFileDeletion] falha remoção recursiva', data2);
          alert('Falha ao remover recursivamente: ' + (data2.error || data2.raw || JSON.stringify(data2)));
          return data2;
        }
      } else {
        // outro 409 — exibir mensagem
        alert('Erro: ' + (data && data.error ? data.error : raw));
        return { success: false, error: data && data.error ? data.error : raw };
      }
    }

    // caso normal (200/ok ou outro)
    if (resp.ok && data && data.success) {
      console.log('[queueFileDeletion] removido com sucesso', data);
      refreshAfterDelete(path, data);
      return data;
    } else {
      console.error('[queueFileDeletion] erro inesperado', data);
      alert('Erro ao remover: ' + (data && data.error ? data.error : data.raw || JSON.stringify(data)));
      return data;
    }

  } catch (err) {
    console.error('[queueFileDeletion] exceção:', err);
    alert('Erro de rede/interno: ' + err.message);
    return { success: false, error: err.message };
  }
}

// Função auxiliar: decide qual diretório recarregar após remoção.
// path -> caminho informado originalmente (ex: 'adrefugiocristao-testes/1' ou '/adrefugiocristao-testes/1')
// data -> resposta do servidor (pode conter campo path absoluto/relativo)
function refreshAfterDelete(requestedPath, data) {
  try {
    // preferir o path retornado pelo servidor se houver
    let resolved = (data && data.path) ? String(data.path) : String(requestedPath || '');
    // remover leading slash(es)
    resolved = resolved.replace(/^\/+/, '');
    // normalizar
    resolved = resolved.replace(/\/+$/, '');

    // calcular parent
    const parts = resolved.split('/');
    // se o path for apenas 'foo' (arquivo ou pasta no root /home/foo), parent será 'foo' -> neste caso queremos carregar 'foo' pai = '' -> carregar root
    let parentParts = parts.slice(0, -1);
    let parent = parentParts.join('/');
    if (!parent) {
      // se não temos parent (ex: foi removida "/adrefugiocristao-testes" ou "/foo"), carregar root lógico (usar '' ou '/')
      parent = ''; // loadFileList('') ou loadFileList('/') conforme sua implementação
    }

    console.log('[queueFileDeletion] refreshAfterDelete, requested:', requestedPath, 'resolved:', resolved, 'parent:', parent);

    // tentar usar loadFileList(parent) se existir e aceitar um argumento
    if (typeof loadFileList === 'function') {
      try {
        // Alguns implementations esperam sem leading slash, outros com; tentamos sem e com fallback
        loadFileList(parent);
      } catch (e) {
        console.warn('[queueFileDeletion] loadFileList(parent) falhou, tentando com "/"', e);
        try { loadFileList('/' + parent); } catch (e2) { console.warn('[queueFileDeletion] fallback loadFileList falhou', e2); location.reload(); }
      }
    } else {
      // se não existir, recarrega a página inteira como fallback
      location.reload();
    }
  } catch (e) {
    console.warn('[queueFileDeletion] erro em refreshAfterDelete:', e);
    try { location.reload(); } catch (_) {}
  }
}



// --- NOVA FUNÇÃO DE EXTRAÇÃO ---
    async function queueExtract(path) {
        if (!confirm(`Tem a certeza que deseja extrair "${path}" para o diretório atual?\n\nFicheiros existentes poderão ser sobrescritos.`)) {
            return;
        }

        const btn = document.querySelector(`.cm-item-file-extract[data-path="${path}"]`);
        if(btn) btn.disabled = true;

        try {
            const response = await fetch('./api/queue_extract_file.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ path: path })
            });

            if(response.status === 403) { window.location.href = 'login.php'; return; }
            const result = await response.json();

            if (result.success) {
                alert(result.message);
                loadFileList(currentFilePath); // Atualiza a lista
            } else {
                throw new Error(result.error);
            }
        } catch (error) {
            alert('Erro: ' + error.message);
            if(btn) btn.disabled = false;
        }
    }

        // Função principal de inicialização
        function initializeFilesPage() {
            console.log("Módulo de Arquivos Inicializado");
            loadFileList(currentFilePath);

            // Delegação de evento para a tabela (para cliques em pastas)
            // IMPORTANTE: Tivemos que mover o listener para 'mainContent'
            // porque 'files-table-body' ainda não existe quando este script é lido.
            // O 'mainContent' é o container pai que existe sempre.
            const mainContent = document.getElementById('main-content');
            
            // Removemos eventuais listeners antigos para evitar duplicados
            // (Esta é uma boa prática, mas podemos refinar depois)
            
            // Adiciona o listener de clique
            mainContent.addEventListener('click', handleFilesTableClick);
            
            // Delegação de evento para as migalhas de pão
            mainContent.addEventListener('click', handleBreadcrumbsClick);
        }

        // --- MÓDULO CRON (ATUALIZADO PARA DB) ---
        function initializeCronPage() {
            loadCronList();
        }

        async function loadCronList() {
            const tableBody = document.getElementById('cron-table-body');
            if (!tableBody) return;
            tableBody.innerHTML = '<tr><td colspan="4" class="loading" data-label="Status">A carregar...</td></tr>';
            
            try {
                // 1. CHAMA A NOVA API (list_cron_db.php)
                const response = await fetch(`./api/list_cron_jobs.php?t=${new Date().getTime()}`);
                if (response.status === 403) { window.location.href = 'login.php'; return; }
                const data = await response.json();
                
                tableBody.innerHTML = '';
                if (!data.success) throw new Error(data.error);

                if (data.jobs.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="4" style="text-align: center;">Nenhuma tarefa encontrada no banco de dados.</td></tr>';
                    return;
                }

                data.jobs.forEach(job => {
                    const row = `
                        <tr>
                            <td data-label="Título">${job.title || '(Sem título)'}</td>
                            <td data-label="Agenda">${job.schedule}</td>
                            <td data-label="Comando">${job.command}</td>
                            <td data-label="Ações">
                                <button class="action-btn btn-edit-cron" 
                                        data-id="${job.id}" 
                                        data-schedule="${encodeURIComponent(job.schedule)}" 
                                        data-command="${encodeURIComponent(job.command)}"
                                        data-title="${encodeURIComponent(job.title || '')}">Editar</button> <button class="action-btn btn-delete-cron" data-id="${job.id}" style="color: #f44336;">Excluir</button>
                            </td>
                        </tr>
                    `;
                    tableBody.innerHTML += row;
                });
            } catch (error) {
                console.error('Erro ao carregar Cron:', error);
                tableBody.innerHTML = `<tr><td colspan="4" style="color:red;text-align:center;">Erro ao carregar tarefas: ${error.message}</td></tr>`;
            }
        }

// --- ADICIONE ESTA NOVA FUNÇÃO ---
        function tryLoadUsers() {
            // Encontra o <select> dentro do modal de permissões
            const sel = document.getElementById('permissions_owner_select');
            if (!sel) {
                console.warn('Elemento permissions_owner_select não encontrado.');
                return; 
            }
            
            var url = '/api/list_users.php';
            var controller = new AbortController();
            var timeout = setTimeout(function(){ controller.abort(); }, 2000);

            fetch(url, {signal: controller.signal, credentials: 'same-origin'})
              .then(function(resp){
                clearTimeout(timeout);
                if (!resp.ok) throw new Error('Falha ao carregar list_users.php');
                return resp.json();
              })
              .then(function(json){
                if (!Array.isArray(json)) return;
                
                // CORREÇÃO: Usa 'sel' (que definimos como o <select>)
                while (sel.options.length > 1) sel.remove(1); 
                json.forEach(function(u){
                  try {
                    var opt = document.createElement('option');
                    opt.value = String(u);
                    opt.textContent = String(u);
                    sel.appendChild(opt); // Adiciona o utilizador
                  } catch(e){}
                });
              })
              .catch(function(err){ 
                  console.warn("Não foi possível carregar a lista de utilizadores:", err.message);
              });
        }


// ===== SINCRONIZAÇÃO PERMISSIONS OWNER SELECT/INPUT =====
const permissionsOwnerSelect = document.getElementById('permissions_owner_select');
const permissionsOwnerInput = document.getElementById('permissions_owner_input');

if (permissionsOwnerSelect && permissionsOwnerInput) {
  // Listener: quando mudar o SELECT
  permissionsOwnerSelect.addEventListener('change', function() {
    const selectedValue = permissionsOwnerSelect.value;
    
    if (selectedValue && selectedValue !== '') {
      permissionsOwnerInput.value = selectedValue;
    } else {
      permissionsOwnerInput.value = '';
    }
  });

  // Listener: quando digitar no INPUT
  permissionsOwnerInput.addEventListener('input', function() {
    const inputValue = permissionsOwnerInput.value.trim();
    
    if (inputValue !== '') {
      permissionsOwnerSelect.value = '';
    }
  });
}
// ===== FIM DA SINCRONIZAÇÃO =====

// ===== CHECKBOX "SELECIONAR TODOS" PERMISSÕES =====
const permSelectAll = document.getElementById('perm_select_all');
const permNumericInput = document.getElementById('permissions_numeric_input');

if (permSelectAll && permNumericInput) {
    // Função para verificar se todos os checkboxes estão marcados
    function checkIfAllSelected() {
        const allCheckboxes = document.querySelectorAll('.perm-checkbox');
        const allChecked = Array.from(allCheckboxes).every(cb => cb.checked);
        permSelectAll.checked = allChecked;
    }
    
    // Listener no checkbox "Todos"
    permSelectAll.addEventListener('change', function() {
        const allCheckboxes = document.querySelectorAll('.perm-checkbox');
        const isChecked = permSelectAll.checked;
        
        // Marca/desmarca todos os checkboxes
        allCheckboxes.forEach(function(checkbox) {
            checkbox.checked = isChecked;
        });
        
        // Define o valor octal diretamente
        if (isChecked) {
            permNumericInput.value = '0777'; // Todos marcados = 0777
        } else {
            permNumericInput.value = '0000'; // Todos desmarcados = 0000
        }
    });
    
    // Verifica o estado inicial ao abrir o modal (se todos estão marcados)
    // Isso deve ser chamado quando o modal é aberto e os checkboxes são populados
    checkIfAllSelected();
}
              
              

(function () {
  // --- Helpers curtos
  const $  = (s) => document.querySelector(s);
  const $$ = (s) => Array.from(document.querySelectorAll(s));

  // Master "Todos"
  const master = $('#perm_all') || $('#permissions_all');

  // Campo Octal (exibe tipo 0777)
  const octalInput = $('#permissions_numeric_input') || $('#perm_numeric');

  // Escopos e bits padrão rwx
  const SCOPES = ['owner','group','other'];   // Dono, Grupo, Outros
  const BITS   = ['r','w','x'];

  // Tenta achar um checkbox por (scope, bit) em vários padrões de id
  function getBox(scope, bit) {
    return (
      document.querySelector(`[data-scope="${scope}"][data-bit="${bit}"]`) ||
      document.querySelector(`#${scope[0]}_${bit}`) ||                    // u_r, g_w, o_x
      document.querySelector(`#perm_${scope}_${bit}`) ||                  // perm_owner_r
      document.querySelector(`#${scope}_${bit}`)                          // owner_r
    );
  }

  // Todas as caixas rwx existentes (mesmo que falte alguma, segue robusto)
  function allBoxes() {
    const boxes = [];
    SCOPES.forEach(scope => {
      BITS.forEach(bit => {
        const el = getBox(scope, bit);
        if (el) boxes.push(el);
      });
    });
    return boxes;
  }

  // Calcula o dígito octal (0..7) para um escopo
  function digit(scope) {
    const r = getBox(scope, 'r')?.checked ? 4 : 0;
    const w = getBox(scope, 'w')?.checked ? 2 : 0;
    const x = getBox(scope, 'x')?.checked ? 1 : 0;
    return r + w + x; // sempre número, nunca NaN
  }

  // Atualiza o campo octal a partir das caixas
  function updateOctal() {
    const u = digit('owner');
    const g = digit('group');
    const o = digit('other');
    if (octalInput) {
      const str = `${u}${g}${o}`.padStart(3,'0');
      octalInput.value = '0' + str; // estilo 0XYZ
    }
  }

  // Define o estado do master com base nas caixas
  function updateMasterFromBoxes() {
    const boxes = allBoxes();
    if (master) master.checked = boxes.length > 0 && boxes.every(cb => cb.checked);
  }

  // Ao mudar o master: marca/desmarca todas as caixas
  function applyMaster() {
    const val = !!master.checked;
    allBoxes().forEach(cb => { cb.checked = val; });
    updateOctal();
  }

  // Permitir digitar no campo octal e refletir nas caixas (opcional, mas útil)
  function applyFromOctalInput() {
    if (!octalInput) return;
    let v = (octalInput.value || '').trim();
    v = v.replace(/^0+/, ''); // remove zeros à esquerda
    if (!/^[0-7]{3}$/.test(v)) {
      // Entrada inválida: apenas re-sincroniza com as caixas atuais (evita 0NaN)
      updateOctal();
      return;
    }
    const [u, g, o] = v.split('').map(n => parseInt(n, 10));
    function setBits(scope, val) {
      const r = getBox(scope,'r'); if (r) r.checked = !!(val & 4);
      const w = getBox(scope,'w'); if (w) w.checked = !!(val & 2);
      const x = getBox(scope,'x'); if (x) x.checked = !!(val & 1);
    }
    setBits('owner', u); setBits('group', g); setBits('other', o);
    updateMasterFromBoxes();
    updateOctal();
  }

  // Liga eventos
  if (master) master.addEventListener('change', applyMaster);

  allBoxes().forEach(cb => {
    cb.addEventListener('change', () => {
      // Se qualquer uma mudar, recalcula master e octal (sem NaN)
      updateMasterFromBoxes();
      updateOctal();
    });
  });

  if (octalInput) {
    // Use input para responder em tempo real; se preferir, troque por 'change'
    octalInput.addEventListener('input', applyFromOctalInput);
  }

  // Primeira sincronização
  updateMasterFromBoxes();
  updateOctal();
})();

            
// ===== FIM CHECKBOX SELECIONAR TODOS =====


// --- MÓDULO TERMINAL (CORRIGIDO PARA ATTACHADDON) ---
        let xtermInstance = null;
        let terminalResizeObserver = null;
        let ptyWebSocket = null; 

        function initializeTerminalPage() {
            const terminalContainer = document.getElementById('terminal-container');
            const terminalStatus = document.getElementById('terminal-status');
            
            if (!terminalContainer) return;
            
            // 1. Verifica se todas as bibliotecas corretas carregaram
            if (typeof Terminal !== 'function' || 
                typeof FitAddon?.FitAddon !== 'function' || 
                typeof AttachAddon?.AttachAddon !== 'function' // <-- VERIFICA 'AttachAddon'
            ) {
                terminalStatus.textContent = 'Erro fatal: Bibliotecas do Terminal (xterm, fit, attach) não carregaram.';
                console.error('Dependências do Terminal não encontradas. Verifique FitAddon/AttachAddon.');
                
                // Log de depuração
                console.log('typeof Terminal:', typeof Terminal);
                console.log('typeof FitAddon:', typeof FitAddon);
                console.log('typeof AttachAddon:', typeof AttachAddon);
                
                return;
            }

            // 2. Inicializa o xterm.js e o FitAddon
            xtermInstance = new Terminal({
                cursorBlink: true,
                fontFamily: 'monospace',
                fontSize: 14,
                convertEol: true,
                theme: document.body.classList.contains('light-mode') ? {} : {
                    background: '#000000', 
                    foreground: '#FFFFFF'
                }
            });
            
            const fitAddon = new FitAddon.FitAddon();
            xtermInstance.loadAddon(fitAddon);
            xtermInstance.open(terminalContainer);
            
            fitAddon.fit();
            
            // 3. Observador de Redimensionamento
            if (terminalResizeObserver) terminalResizeObserver.disconnect();
            terminalResizeObserver = new ResizeObserver(() => {
                try { fitAddon.fit(); } catch (e) {}
            });
            terminalResizeObserver.observe(terminalContainer);

            // 4. Conecta o WebSocket
            const wsProtocol = window.location.protocol === 'https:' ? 'wss://' : 'ws://';
            const wsUrl = wsProtocol + window.location.host + '/websocket/'; 

            try {
                ptyWebSocket = new WebSocket(wsUrl);
            } catch (e) {
                terminalStatus.textContent = 'Erro ao conectar ao WebSocket.';
                xtermInstance.writeln('Erro de Conexão: ' + e.message);
                return;
            }

            // 5. Liga o xterm.js ao WebSocket usando o ADDON CORRETO
            // Usa o 'AttachAddon.AttachAddon'
            const attachAddon = new AttachAddon.AttachAddon(ptyWebSocket);
            xtermInstance.loadAddon(attachAddon);
            
            // 6. Lida com o estado da ligação
            ptyWebSocket.onopen = () => {
                terminalStatus.textContent = 'Online (Conectado)';
                xtermInstance.focus();
            };
            
            ptyWebSocket.onclose = (e) => {
                terminalStatus.textContent = 'Desconectado';
                xtermInstance.writeln(`\r\n--- CONEXÃO PERDIDA (code: ${e.code}) ---`);
            };

            ptyWebSocket.onerror = (e) => {
                terminalStatus.textContent = 'Erro de Conexão';
                console.error('Erro no WebSocket:', e);
            };
        }


    // Função para limpar o xterm quando saímos da página
    function clearTerminalPage() {
        if (ptyWebSocket && ptyWebSocket.readyState === WebSocket.OPEN) {
            ptyWebSocket.close(); // Fecha a "chamada"
        }
        if (xtermInstance) {
            xtermInstance.dispose();
            xtermInstance = null;
        }
        if (terminalResizeObserver) {
            terminalResizeObserver.disconnect();
            terminalResizeObserver = null;
        }
        ptyWebSocket = null;
    }

        // Nova Função de Exclusão
        async function queueCronDeletion(id) {
            if (!confirm(`Tem a certeza que deseja excluir esta tarefa do banco de dados?\n\nDeverá sincronizar para aplicar a mudança.`)) {
                return;
            }
            const btn = document.querySelector(`.btn-delete-cron[data-id="${id}"]`);
            if(btn) btn.disabled = true;
            
            try {
                const response = await fetch('./api/delete_cron_db.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });
                
                if(response.status === 403) { window.location.href = 'login.php'; return; }
                const result = await response.json();
                if (result.success) {
                    alert(result.message);
                    loadCronList(); // Recarrega a lista
                } else {
                    throw new Error(result.error);
                }
            } catch (error) {
                alert('Erro: ' + error.message);
                if(btn) btn.disabled = false;
            }
        }
        
        // Nova Função de Sincronização
        async function syncCronToServer() {
            if (!confirm(`Deseja substituir o crontab do servidor pelas tarefas salvas no banco de dados?\n\nTarefas manuais (via SSH) serão perdidas.`)) {
                return;
            }
            const btn = document.getElementById('btnSyncCron');
            if(btn) {
                btn.disabled = true;
                btn.textContent = 'Sincronizando...';
            }
            
            try {
                const response = await fetch('./api/sync_cron_to_server.php', { method: 'POST' });
                if(response.status === 403) { window.location.href = 'login.php'; return; }
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message);
                } else {
                    throw new Error(result.error);
                }
            } catch (error) {
                alert('Erro na sincronização: ' + error.message);
            } finally {
                if(btn) {
                    btn.disabled = false;
                    btn.textContent = 'Sincronizar com Servidor 🔄';
                }
            }
        }
        
// Handler para cliques na tabela (ATUALIZADO para Vista de Ícones E Detalhes)
    function handleFilesTableClick(e) {
        let target = e.target;

        // --- Lógica de Clique Unificada ---

        // Alvo 1: O utilizador clicou no *wrapper* de um ÍCONE
        // (Isto trata cliques no emoji 📁/📄 ou no espaço de padding do ícone)
        const iconItemWrapper = target.closest('.icon-item');
        if (iconItemWrapper) {
            e.preventDefault();
            const path = iconItemWrapper.dataset.path;
            const type = iconItemWrapper.dataset.type;

            if (type === 'dir') {
                // Se for uma pasta, navega
                loadFileList(path);
            } else if (type === 'file') {
                // Se for um ficheiro, procuramos a sua ação principal
                const internalLink = iconItemWrapper.querySelector('.file-link'); 
                if (internalLink) {
                    const action = internalLink.dataset.action;

                    if (action === 'edit') {
                        openFileEditorModal(path); // Requisito 2
                    } else if (action === 'view') {
                        openFileViewerModal(path); // Requisito 3
                    } else if (internalLink.hasAttribute('download')) {
                        internalLink.click(); // Requisito 4
                    }
                }
            }
            return; // Ação da Vista de Ícones concluída
        }

        // Alvo 2: O utilizador clicou num link de Ação na VISTA DE DETALHES
        // (Isto cobre pastas, ficheiros editáveis, e ficheiros de imagem)
        const actionLink = target.closest('.dir-link, .file-link[data-action]');
        if (actionLink) {
            e.preventDefault();
            const path = actionLink.dataset.path;
            const action = actionLink.dataset.action;

            if (action === 'navigate') {
                loadFileList(path); // Requisito 1
            } else if (action === 'edit') {
                openFileEditorModal(path); // Requisito 2
            } else if (action === 'view') {
                openFileViewerModal(path); // Requisito 3
            }
            return; // Ação da Vista de Tabela concluída
        }

        // Requisito 4 (Download de Outros Ficheiros na Tabela) é tratado
        // automaticamente pelo navegador, pois o <a> tag (criado na
        // loadFileList) não é apanhado pelos 'if's
        // acima e o 'e.preventDefault()' não é chamado.

        // Requisito 5 (Menu de Contexto) é tratado pelo listener 'contextmenu'
        // e não é afetado por esta função.
    }
        
        // Handler para cliques nas migalhas de pão
        function handleBreadcrumbsClick(e) {
            let target = e.target;
            if (target && target.classList.contains('breadcrumb-link')) {
                e.preventDefault();
                const newPath = target.dataset.path;
                loadFileList(newPath);
            }
        }
        
        // Função para parar os listeners quando saímos da página
        function clearFilesPageListeners() {
             const mainContent = document.getElementById('main-content');
             mainContent.removeEventListener('click', handleFilesTableClick);
             mainContent.removeEventListener('click', handleBreadcrumbsClick);
        }

        // Função para carregar e renderizar a lista de arquivos
        async function loadFileList(path) {
            const tableBody = document.getElementById('files-table-body');
            const breadcrumbs = document.getElementById('file-breadcrumbs');
            const detailsContainer = document.getElementById('files-view-details');
            const iconsContainer = document.getElementById('files-view-icons');
            const viewToggleBtn_List = document.getElementById('view-icon-list');
            const viewToggleBtn_Grid = document.getElementById('view-icon-grid');
            currentFilePath = path; // Atualiza o estado global

            // Se os elementos não existirem (página errada?), sai.
            if (!tableBody || !breadcrumbs) return;

            // Mostra o feedback de "loading"
            tableBody.innerHTML = `<tr><td colspan="5" class="loading" data-label="Status">A carregar...</td></tr>`;
            breadcrumbs.innerHTML = `<span class="loading">A carregar...</span>`;

            try {
        const response = await fetch(`./api/list_files.php?path=${encodeURIComponent(path)}&t=${new Date().getTime()}`);
        if(response.status === 403) { window.location.href = 'login.php'; return; }
        const data = await response.json();
        if (!data.success) throw new Error(data.error);

        // 1. Renderizar Migalhas de Pão (sem alteração)
        renderBreadcrumbs(data.path);

        // 2. Limpar containers antigos
        tableBody.innerHTML = '';
        iconsContainer.innerHTML = '';

        // 3. Alternar a visibilidade
        if (currentFileViewMode === 'icons') {
            detailsContainer.style.display = 'none';
            iconsContainer.style.display = 'grid'; // 'grid' (do nosso CSS)
            viewToggleBtn_List.style.display = 'inline';
            viewToggleBtn_Grid.style.display = 'none';
        } else {
            detailsContainer.style.display = 'block';
            iconsContainer.style.display = 'none';
            viewToggleBtn_List.style.display = 'none';
            viewToggleBtn_Grid.style.display = 'inline';
        }

// 4. Adiciona o link "Voltar" (..) se não estivermos na raiz
            if (data.path !== '/' && data.path !== '') {
                const parentPath = data.path.substring(0, data.path.lastIndexOf('/')) || '/';
                
                if (currentFileViewMode === 'icons') {
                    // Renderiza "Voltar" como um ÍCONE
                    // CORRIGIDO: Remove 'onclick' e usa 'data-type' para o handler global
                    iconsContainer.innerHTML += `
                        <div class="icon-item context-menu-disabled" 
                             data-path="${parentPath}" 
                             data-type="dir">
                            <span class="icon-item-img" style="font-size: 3rem; color: var(--accent-color);">🔙</span>
                            <span class="icon-item-name">.. (Voltar)</span>
                        </div>
                    `;
                } else {
                    // Renderiza "Voltar" como uma LINHA DE TABELA
                    tableBody.innerHTML += `
                        <tr class="context-menu-disabled">
                            <td data-label="Nome">
                                <a href="#" class="dir-link" data-path="${parentPath}" data-action="navigate">
                                    <strong>.. (Voltar)</strong>
                                </a>
                            </td>
                            <td data-label="Tamanho">--</td>
                            <td data-label="Owner">--</td>
                            <td data-label="Permissões">--</td>
                            <td data-label="Modificado">--</td>
                            <td data-label="Ações">--</td>
                        </tr>
                    `;
                }
            }

        // Feedback de Pasta Vazia
        if (data.files.length === 0) {
             if (currentFileViewMode === 'icons') {
                 iconsContainer.innerHTML += '<p style="color: var(--text-muted); grid-column: 1 / -1; text-align: center;">Pasta vazia.</p>';
             } else {
                 tableBody.innerHTML += '<tr><td colspan="6" style="text-align: center;">Pasta vazia.</td></tr>';
             }
        }

        // 5. Renderizar os Ficheiros (Loop principal)
        data.files.forEach(item => {
            const date = new Date(item.modified * 1000).toLocaleString('pt-BR');
            const size = (item.type === 'dir') ? '--' : formatBytes(item.size);

            // --- Lógica de Extensões (sem alteração) ---
            const fileName = item.name.toLowerCase();
            const fileExt = fileName.substring(fileName.lastIndexOf('.'));
            const editableExtensions = ['.php', '.txt', '.htm', '.html', '.css', '.js', '.json', '.md', '.xml', '.ini', '.log', '.conf', '.env', '.sh', '.htaccess'];
            const viewableImageExtensions = ['.png', '.jpg', '.jpeg', '.gif', '.bmp', '.svg', '.webp'];

            // --- Lógica do 'nameLink' (sem alteração) ---
            let nameLink = '';
            let nameLinkClass = 'file-link'; // Padrão
            let nameLinkDataAction = '';
            let nameLinkStyle = '';
            let nameLinkTitle = '';
            let nameLinkTag = 'span';
            let nameLinkHref = `href="#"`;

            if (item.type === 'dir') {
                nameLinkClass = 'dir-link';
                nameLinkDataAction = `data-action="navigate"`; // Ação de navegar
                nameLink = `📁 <a href="#" class="dir-link" data-path="${item.path}" ${nameLinkDataAction}>${item.name}</a>`;
            } else if (editableExtensions.includes(fileExt)) {
                nameLinkClass = 'file-link';
                nameLinkDataAction = `data-action="edit"`;
                nameLinkStyle = `style="cursor: pointer; text-decoration: underline;"`;
                nameLinkTitle = `title="Editar ${item.name}"`;
                nameLink = `📄 <span class="${nameLinkClass}" data-path="${item.path}" ${nameLinkDataAction} ${nameLinkStyle} ${nameLinkTitle}>${item.name}</span>`;
            } else if (viewableImageExtensions.includes(fileExt)) {
                nameLinkClass = 'file-link';
                nameLinkDataAction = `data-action="view"`;
                nameLinkStyle = `style="cursor: pointer; text-decoration: underline;"`;
                nameLinkTitle = `title="Visualizar ${item.name}"`;
                nameLink = `📄 <span class="${nameLinkClass}" data-path="${item.path}" ${nameLinkDataAction} ${nameLinkStyle} ${nameLinkTitle}>${item.name}</span>`;
            } else {
                nameLinkTag = 'a';
                nameLinkClass = 'file-link';
                nameLinkHref = `href="./api/download_file.php?path=${encodeURIComponent(item.path)}"`;
                nameLinkStyle = `style="cursor: pointer;"`;
                nameLinkTitle = `title="Baixar ${item.name}"`;
                nameLink = `📄 <a href="./api/download_file.php?path=${encodeURIComponent(item.path)}" class="file-link" ${nameLinkStyle} ${nameLinkTitle} download>${item.name}</a>`;
            }

            // --- Lógica das 'actions' (sem alteração) ---
            let actions = '--';
            if (item.type === 'file') {
                const downloadLink = `<a href="./api/download_file.php?path=${encodeURIComponent(item.path)}" class="action-link" style="margin-top: 5px;" download>Download</a>`;
                const deleteButton = `<button class="action-btn btn-delete-file" data-path="${item.path}" data-type="${item.type}" style="color: #f44336; margin-top: 5px;" title="Excluir">Excluir</button>`;
                let primaryButton = '';
                if (editableExtensions.includes(fileExt)) {
                    primaryButton = `<button class="action-btn btn-edit-file" data-path="${item.path}" title="Editar Arquivo">Editar</button>`;
                    actions = primaryButton + downloadLink + deleteButton;
                } else if (viewableImageExtensions.includes(fileExt)) {
                    primaryButton = `<button class="action-btn btn-view-file" data-path="${item.path}" title="Visualizar Imagem">Visualizar</button>`;
                    actions = primaryButton + downloadLink + deleteButton;
                }
            }

            // --- 6. RENDERIZAÇÃO (A LÓGICA IF/ELSE) ---
            if (currentFileViewMode === 'icons') {
                // Renderiza como ÍCONE
                const iconEmoji = (item.type === 'dir') ? '📁' : '📄';

                iconsContainer.innerHTML += `
                    <div class="icon-item context-menu-target" 
                         data-path="${item.path}" 
                         data-type="${item.type}">

                        <span class="icon-item-img">${iconEmoji}</span>

                        <span class="icon-item-name">
                            ${nameLink.substring(2)} 
                        </span>
                    </div>
                `;
            } else {
                // Renderiza como LINHA DE TABELA
                const row = `
                    <tr>
                        <td data-label="Nome" 
                            class="context-menu-target" 
                            data-path="${item.path}" 
                            data-type="${item.type}">
                            ${nameLink}
                        </td>
                        <td data-label="Tamanho">${size}</td>
                        <td data-label="Owner">${item.owner}</td>
                        <td data-label="Permissões">${item.permissions}</td>
                        <td data-label="Modificado">${date}</td>
                        <td data-label="Ações">${actions}</td>
                    </tr>
                `;
                tableBody.innerHTML += row;
            }
        });

    } catch (error) {
        console.error('Erro ao carregar lista de arquivos:', error);
        const errorMsg = `<tr><td colspan="6" style="color: #f44336; text-align: center;">${error.message}</td></tr>`;
        tableBody.innerHTML = errorMsg;
        iconsContainer.innerHTML = errorMsg; // Mostra o erro na vista de ícones também
        breadcrumbs.innerHTML = `<span style="color: #f44336;">Erro ao carregar caminho</span>`;
    }
}
        
        // Função para renderizar as migalhas de pão
        function renderBreadcrumbs(path) {
            const breadcrumbs = document.getElementById('file-breadcrumbs');
            if (!breadcrumbs) return;
            
            breadcrumbs.innerHTML = ''; // Limpa
            
            // Link Raiz
            const rootLink = document.createElement('a');
            rootLink.href = '#';
            rootLink.className = 'breadcrumb-link';
            rootLink.dataset.path = '/';
            rootLink.textContent = 'Raiz';
            breadcrumbs.appendChild(rootLink);

            let pathAccumulator = '';
            const parts = path.split('/').filter(p => p.length > 0);

            parts.forEach((part, index) => {
                pathAccumulator += '/' + part;
                
                // Separador
                const separator = document.createElement('span');
                separator.className = 'breadcrumb-separator';
                separator.textContent = '>';
                breadcrumbs.appendChild(separator);

                if (index === parts.length - 1) {
                    // Última parte (não clicável)
                    const currentFolder = document.createElement('span');
                    currentFolder.textContent = part;
                    breadcrumbs.appendChild(currentFolder);
                } else {
                    // Link intermediário
                    const partLink = document.createElement('a');
                    partLink.href = '#';
                    partLink.className = 'breadcrumb-link';
                    partLink.dataset.path = pathAccumulator;
                    partLink.textContent = part;
                    breadcrumbs.appendChild(partLink);
                }
            });
        }


        // --- 4. ROTEADOR AJAX (CARREGADOR DE PÁGINA) ---
        const mainContent = document.getElementById('main-content');
        const navLinks = document.querySelectorAll('.nav-link');
        const pageTitle = document.getElementById('page-title');

// --- MÓDULO MENU DE CONTEXTO ---
        const fileContextMenu = document.getElementById('fileContextMenu');
        let currentContextItem = { path: null, type: null, ext: null };
        
        // Listener Global para FECHAR o menu
        window.addEventListener('click', () => {
            if (fileContextMenu.classList.contains('show')) {
                fileContextMenu.classList.remove('show');
            }
        });

// Listener Principal para ABRIR o menu (ATUALIZADO para Vista de Ícones)
        mainContent.addEventListener('contextmenu', (e) => {
            
            // --- LÓGICA DE DETECÇÃO CORRIGIDA ---
            
            // Caso 1: Verifica se clicou num item (seja <td> ou <div>, ambos têm a classe)
            const targetItem = e.target.closest('.context-menu-target');
            
            // Caso 2: Verifica se clicou no fundo da tabela
            const targetTableBody = e.target.closest('#files-table-body');
            
            // Caso 3: Verifica se clicou no fundo da grelha de ícones
            const targetIconContainer = e.target.closest('#files-view-icons');
            
            // --- FIM DA CORREÇÃO ---
            
            let contextPath, contextType, contextExt, isBackground;

            if (targetItem && !targetItem.classList.contains('context-menu-disabled')) {
                // --- CLICOU NUM ITEM (Ficheiro ou Pasta, em qualquer vista) ---
                e.preventDefault();
                contextPath = targetItem.dataset.path;
                contextType = targetItem.dataset.type;
                contextExt = (contextType === 'file') ? contextPath.substring(contextPath.lastIndexOf('.')).toLowerCase() : null;
                isBackground = false; 
                
            } else if (targetTableBody || (targetIconContainer && !targetItem)) {
                // --- CLICOU NO ESPAÇO VAZIO (em qualquer vista) ---
                // (A condição '!targetItem' impede que um clique num ícone seja contado como fundo)
                e.preventDefault();
                contextPath = currentFilePath; // O diretório atual
                contextType = 'dir';           // Trata o espaço vazio como um diretório
                contextExt = null;
                isBackground = true; 
                
            } else {
                // Clicou fora da área de ficheiros, não faz nada
                return;
            }
            
            // 1. Guarda os dados do item clicado (sem alteração)
            currentContextItem = { path: contextPath, type: contextType, ext: contextExt };

            // 2. Filtra os botões (sem alteração)
            filterContextMenu(contextType, contextExt, isBackground);
            
            // 3. Posiciona e Mostra o menu (sem alteração)
            const scrollTop = window.scrollY || document.documentElement.scrollTop;
            const scrollLeft = window.scrollX || document.documentElement.scrollLeft;
            const clickX = e.clientX + scrollLeft;
            const clickY = e.clientY + scrollTop;
            
            fileContextMenu.classList.add('show'); 
            
            const menuWidth = fileContextMenu.offsetWidth;
            const menuHeight = fileContextMenu.offsetHeight;
            const windowWidth = window.innerWidth;
            const windowHeight = window.innerHeight;
            
            let menuX = clickX;
            let menuY = clickY;

            if ((e.clientY + menuHeight) > windowHeight) {
                menuY = clickY - menuHeight;
            }
            if ((e.clientX + menuWidth) > windowWidth) {
                menuX = clickX - menuWidth;
            }
            if (menuY < scrollTop) {
                menuY = scrollTop + 5;
            }
            if (menuX < scrollLeft) {
                menuX = scrollLeft + 5;
            }

            fileContextMenu.style.left = `${menuX}px`;
            fileContextMenu.style.top = `${menuY}px`;
        });
        
        // Função para mostrar/ocultar itens do menu (ATUALIZADA)
        function filterContextMenu(type, ext, isBackground = false) {
            const allItems = fileContextMenu.querySelectorAll('a');
            allItems.forEach(item => item.classList.add('cm-item-hidden')); // Esconde tudo

            // Listas de extensões (copiadas de loadFileList)
            const editableExtensions = ['.php', '.txt', '.htm', '.html', '.css', '.js', '.json', '.md', '.xml', '.ini', '.log', '.conf', '.env', '.sh', '.htaccess'];
            const viewableImageExtensions = ['.png', '.jpg', '.jpeg', '.gif', '.bmp', '.svg', '.webp'];
            const extractableExtensions = ['.zip', '.tar.gz', '.tgz'];

            // --- LÓGICA DE EXIBIÇÃO ---

            if (isBackground) {
                // Caso 2: Clicou no FUNDO (Mostra apenas "Criar" e "Colar")
                fileContextMenu.querySelector('#cm-btn-create-file').classList.remove('cm-item-hidden');
                fileContextMenu.querySelector('#cm-btn-create-folder').classList.remove('cm-item-hidden');
                if (fileClipboard.action) {
                    fileContextMenu.querySelector('#cm-btn-paste').classList.remove('cm-item-hidden');
                }

            } else {
                // Caso 1: Clicou NUM ITEM (Ficheiro ou Pasta)
                
                // Mostra itens que servem para todos (Renomear, Comprimir, etc.)
                fileContextMenu.querySelectorAll('.cm-item-all').forEach(item => item.classList.remove('cm-item-hidden'));
                
                if (type === 'dir') {
                    // Clicou numa PASTA
                    fileContextMenu.querySelectorAll('.cm-item-dir').forEach(item => item.classList.remove('cm-item-hidden'));
                    if (fileClipboard.action) {
                        fileContextMenu.querySelector('#cm-btn-paste').classList.remove('cm-item-hidden');
                    }
                } else if (type === 'file') {
                    // Clicou num FICHEIRO
                    // (Itens de "Criar" e "Colar" permanecem escondidos, como esperado)
                    fileContextMenu.querySelectorAll('.cm-item-file').forEach(item => item.classList.remove('cm-item-hidden'));
                    
                    if (editableExtensions.includes(ext)) {
                        fileContextMenu.querySelectorAll('.cm-item-file-edit').forEach(item => item.classList.remove('cm-item-hidden'));
                    } else if (viewableImageExtensions.includes(ext)) {
                        fileContextMenu.querySelectorAll('.cm-item-file-view').forEach(item => item.classList.remove('cm-item-hidden'));
                    }
                    if (extractableExtensions.includes(ext)) {
                fileContextMenu.querySelector('#cm-btn-extract').classList.remove('cm-item-hidden');
            }
                }
            }
        }
        
        // Listener para AÇÕES do menu
        if (fileContextMenu) {
            fileContextMenu.addEventListener('click', (e) => {
                e.preventDefault(); // Impede o 'href=#'
                
                const targetId = e.target.closest('a').id;
                const { path, type } = currentContextItem;
                
                switch (targetId) {
                    case 'cm-btn-view':
                        openFileViewerModal(path);
                        break;
                    case 'cm-btn-edit':
                        openFileEditorModal(path);
                        break;
                    case 'cm-btn-download':
                        // O 'a' de download não funciona bem aqui, criamos um link dinâmico
                        const link = document.createElement('a');
                        link.href = `./api/download_file.php?path=${encodeURIComponent(path)}`;
                        link.download = path.split('/').pop();
                        link.click();
                        break;
                    case 'cm-btn-delete':
                        queueFileDeletion(path, type);
                        break;
                        
                    // Funções futuras
                    case 'cm-btn-open-dir':
                        loadFileList(path);
                        break;
                    case 'cm-btn-rename':
                        openRenameModal(path, type);
                        break;
                    case 'cm-btn-compress':
                        openCompressModal(path, type);
                        break;
                    case 'cm-btn-extract':
                        queueExtract(path);
                        break;    
                    case 'cm-btn-permissions':
                        openPermissionsModal(path);
                        break;
                    case 'cm-btn-create-file':
                        openCreateNewModal(path, 'file'); // 'path' aqui é o diretório pai
                        break;
                    case 'cm-btn-create-folder':
                        openCreateNewModal(path, 'folder'); // 'path' aqui é o diretório pai
                        break;    
                    case 'cm-btn-cut':
                        fileClipboard = { action: 'cut', path: path };
                        alert(`Cortado: ${path}\n(Clique com o botão direito numa pasta para colar)`);
                        break;
                    case 'cm-btn-copy':
                        fileClipboard = { action: 'copy', path: path };
                        alert(`Copiado: ${path}\n(Clique com o botão direito numa pasta para colar)`);
                        break;
                    case 'cm-btn-paste':
                        if (!fileClipboard.action) {
                            alert('Clipboard vazio.');
                            return;
                        }
                        // 'path' aqui é o diretório de destino (onde clicámos)
                        queueClipboardAction(fileClipboard.action, fileClipboard.path, path);
                        break;    
                }
                
                fileContextMenu.classList.remove('show'); // Esconde o menu após a ação
            });
        }
        
        
        async function loadPage(pageName) {
            if (pageName === 'logout') return; 
            if (pageName !== 'dashboard' && pageName !== 'monitor' && typeof clearStatsInterval === 'function') {
                clearStatsInterval();
            }
            if (typeof clearFilesPageListeners === 'function') {
                clearFilesPageListeners();
            }
            if (typeof clearTerminalPage === 'function') {
            clearTerminalPage();
            }
            let activeLink = null;
            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.dataset.page === pageName) {
                    link.classList.add('active');
                    activeLink = link;
                }
            });
            if(activeLink) { pageTitle.textContent = activeLink.textContent.substring(2); }
            mainContent.innerHTML = '<span class="loading">Carregando...</span>';
            try {
                if (pageName === 'terminal') {
                    await loadScript('modules/lib/xterm/xterm.js', 'xterm-js');
                    await loadScript('modules/lib/xterm/xterm-addon-fit.js', 'xterm-fit-js');
                    await loadScript('modules/lib/xterm/xterm-addon-attach.js', 'xterm-attach-js');
                }

                const response = await fetch(`./pages/${pageName}.php?t=${new Date().getTime()}`);
                if (!response.ok) throw new Error('Página não encontrada.');
                const pageHTML = await response.text();
                mainContent.innerHTML = pageHTML;

                // Roteador de Inicialização
                if (pageName === 'dashboard') initializeDashboard();
                else if (pageName === 'websites') loadSitesList();
                else if (pageName === 'settings') initializeSettingsPage();
                else if (pageName === 'monitor') initializeMonitorPage();
                else if (pageName === 'logs') initializeLogsPage();
                else if (pageName === 'databases') initializeDatabasesPage();
                else if (pageName === 'backups') initializeBackupsPage();
                else if (pageName === 'full_backups') initializeFullBackupsPage();
                else if (pageName === 'files') initializeFilesPage();
                else if (pageName === 'cron') initializeCronPage();
                else if (pageName === 'terminal') initializeTerminalPage();
                else if (pageName === 'ufw') initializeUfwPage();

            } catch (error) {
                console.warn(error);
                mainContent.innerHTML = `<div class="card">
                    <h2>Erro 404</h2>
                    <p>O módulo <strong>${pageName}</strong> ainda não foi criado.</p>
                </div>`;
            }
        }

        navLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const pageName = link.dataset.page;
                if (pageName === 'logout') return; 
                // CORREÇÃO: Usa '#' para o hash
                window.history.pushState(null, '', `#${pageName}`); 
                loadPage(pageName);
            });
        });

        // Delegação de evento
        mainContent.addEventListener('click', (e) => {
    const target = e.target; // O alvo original do clique

    // Variável separada para o botão de toggle
    const toggleBtnTarget = e.target.closest('#btnToggleFileView');
            
           if (toggleBtnTarget && toggleBtnTarget.id === 'btnToggleFileView') {
                // Troca o modo
                currentFileViewMode = (currentFileViewMode === 'details') ? 'icons' : 'details';
                // Salva a preferência
                localStorage.setItem('fileViewMode', currentFileViewMode);
                // Recarrega a lista de ficheiros com a nova vista
                loadFileList(currentFilePath);
            }
            
            // Botões do Módulo Website
            // CORREÇÃO: O ID do botão é 'add-site-btn'
            if (target.id === 'add-site-btn') openModal(); 
            if (target.classList.contains('btn-config-nginx')) {
                openNginxConfigModal(target.dataset.domain, target.dataset.path);
            }
            if (target.classList.contains('btn-delete-site')) {
                queueSiteDeletion(target.dataset.domain, target.dataset.path);
            }
            
            // Botões do Módulo Database
            if (target.id === 'btnAddDatabase') openDbModal();
            if (target.classList.contains('btn-delete-database')) {
                queueDatabaseDeletion(target.dataset.id, target.dataset.name);
            }
            if (target.classList.contains('btn-backup-database')) {
                queueDatabaseBackup(target.dataset.id, target.dataset.name);
            }
            if (target.classList.contains('btn-restore-database')) {
                queueDatabaseRestore(target.dataset.id, target.dataset.name);
            }
            if (target.id === 'btnRestoreDatabase') {
                openRestoreModal();
            }
            if (target.id === 'btnRestoreSiteUpload') {
                openRestoreSiteModal();
            }
            if (target.classList.contains('btn-backup-site')) {
                queueSiteBackup(target.dataset.domain, target.dataset.root);
            }
            if (target.classList.contains('btn-restore-site')) {
                queueSiteRestore(target.dataset.domain, target.dataset.root, target.dataset.file);
            }
            if (target.classList.contains('btn-delete-site-backup')) {
                queueDeleteSiteBackup(target.dataset.file, target.dataset.domain);
            }
            if (target.classList.contains('btn-link-db')) {
                openLinkDbModal(target.dataset.conf, target.dataset.dbid);
            }
            if (target.classList.contains('btn-full-backup')) {
                queueFullBackup(target.dataset.domain, target.dataset.root, target.dataset.dbid);
            }
            if (target.id === 'btnBackupAllSites') {
                queueBackupAllSites();
            }
            if (target.id === 'btnGenerateMasterArchive') {
                queueGenerateMasterArchive();
            }
            if (target.id === 'btnDeleteMasterArchive') {
                queueDeleteMasterArchive();
            }
            if (target.classList.contains('btn-edit-file')) {
            const filePath = target.dataset.path;
            openFileEditorModal(filePath);
        }
            if (target.id === 'btnUploadFile') {
                // currentFilePath é a variável global do módulo "Arquivos"
                openUploadModal(currentFilePath); 
        }
            if (target.classList.contains('btn-view-file')) {
                const filePath = target.dataset.path;
                openFileViewerModal(filePath);
        }
            if (target.classList.contains('btn-delete-file')) {
                queueFileDeletion(target.dataset.path, target.dataset.type);
        }
        if (target.id === 'btnRefreshFiles') {
            loadFileList(currentFilePath); 
        }
        if (target.id === 'btnAddCronJob') {
                openCronModal();
        }
        if (target.id === 'btnSyncCron') {
                syncCronToServer();
        }
        if (target.classList.contains('btn-delete-cron')) {
                queueCronDeletion(target.dataset.id);
        }
        if (target.classList.contains('btn-edit-cron')) {
            openCronModalForEdit(
                target.dataset.id,
                decodeURIComponent(target.dataset.schedule),
                decodeURIComponent(target.dataset.command),
                decodeURIComponent(target.dataset.title || '')
            );
        }
        if (target.id === 'btn-ufw-add-rule') {
                openUfwModal();
            }
            if (target.id === 'btn-ufw-enable') {
                toggleUfw('enable');
            }
            if (target.id === 'btn-ufw-disable') {
                toggleUfw('disable');
            }
            if (target.id === 'btn-ufw-refresh') {
                loadUfwStatus();
            }
            if (target.classList.contains('btn-delete-ufw-rule')) {
                deleteUfwRule(target.dataset.id);
            }
            if (target.id === 'btn-ufw-sync') {
                syncUfwToServer();
            }
        });

        // --- 5. INICIALIZAÇÃO DA PÁGINA ---
        document.addEventListener('DOMContentLoaded', () => {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            applyTheme(savedTheme);
            let initialPage = window.location.hash.substring(1) || 'dashboard';
            loadPage(initialPage);
        });
    </script>
</body>
</html>