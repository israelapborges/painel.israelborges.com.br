<div id="addDatabaseModal" class="modal-backdrop">
  <div class="modal-content">
    
    <div class="modal-tabs">
        <button class="modal-tab-btn active" data-tab="create-db-tab">Criar Novo</button>
        <button class="modal-tab-btn" data-tab="import-db-tab">Registrar Existente</button>
    </div>

    <div id="create-db-tab" class="modal-tab-content active">
        <div class="modal-header">
          <h2>Adicionar Banco de Dados</h2>
          <button class="modal-close-btn" id="closeDbModalBtn">×</button>
        </div>
        <form id="addDatabaseForm">
          <div class="form-group">
            <label>Nome do Banco (ex: meu_site_db)</label>
            <input type="text" name="db_name" required>
          </div>
          <div class="form-group">
            <label>Nome do Usuário (ex: meu_site_user)</label>
            <input type="text" name="db_user" required>
          </div>
          <div class="form-group">
            <label>Senha do Usuário</label>
            <div style="display: flex; gap: 10px;">
                <input type="text" name="db_pass" id="db_pass_input" required>
                <button type="button" class="btn-primary" id="db-generate-pass-btn" style="flex-shrink: 0;">Gerar</button>
            </div>
          </div>
          <button type="submit" class="btn-primary">Criar Banco e Usuário</button>
        </form>
    </div>
    
    <div id="import-db-tab" class="modal-tab-content">
        <div class="modal-header">
          <h2>Registrar Banco Existente</h2>
          <button class="modal-close-btn" id="closeDbModalBtn_import">×</button>
        </div>
        <form id="importDatabaseForm">
            <p style="font-size: 0.9em; margin-top: 0;">
                Use isto para adicionar um banco de dados e usuário que <strong>já existem</strong> no servidor, 
                mas que não foram criados pelo painel.
            </p>
          <div class="form-group">
            <label>Nome do Banco (Existente)</label>
            <input type="text" name="db_name_existing" required>
          </div>
          <div class="form-group">
            <label>Nome do Usuário (Existente)</label>
            <input type="text" name="db_user_existing" required>
          </div>
          <button type="submit" class="btn-primary">Registrar Banco no Painel</button>
        </form>
    </div>

  </div>
</div>