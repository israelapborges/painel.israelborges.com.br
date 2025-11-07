<div id="restoreDatabaseModal" class="modal-backdrop">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Restaurar Backup do Computador</h2>
      <button class="modal-close-btn" id="closeRestoreModalBtn">×</button>
    </div>
    
    <form id="restoreDatabaseForm" enctype="multipart/form-data">
        <p style="font-size: 0.9em; margin-top: 0;">
            <strong>Atenção:</strong> Esta ação irá <strong>SOBRESCREVER</strong> o banco de dados selecionado.
        </p>
      
      <div class="form-group">
        <label>1. Banco de Dados (que será sobrescrito):</label>
        <select name="db_id" id="restore-db-select" required>
            <option value="">Carregando lista...</option>
        </select>
      </div>

      <div class="form-group">
        <label>2. Arquivo de Backup (.sql ou .sql.gz):</label>
        
        <div class="file-input-container">
            <input type="file" name="backup_file" id="restore-file-input" class="file-input" required accept=".sql,.gz">
            
            <label for="restore-file-input" class="btn-primary">Escolher arquivo</label>
            
            <span id="file-name-display">Nenhum arquivo escolhido</span>
        </div>
      </div>
      <button type="submit" class="btn-primary">Iniciar Upload e Restauração</button>
      
      <p id="restore-feedback" style="margin-top: 15px; display: none;"></p>
    </form>
  </div>
</div>