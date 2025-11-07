<div id="restoreSiteModal" class="modal-backdrop">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Restaurar Backup do Site (do Computador)</h2>
      <button class="modal-close-btn" id="closeRestoreSiteModalBtn">×</button>
    </div>
    <form id="restoreSiteForm" enctype="multipart/form-data">
        <p style="font-size: 0.9em; margin-top: 0;">
            <strong>Atenção:</strong> Esta ação irá <strong>APAGAR TODOS OS ARQUIVOS</strong> do site selecionado antes de restaurar o backup.
        </p>
      
      <div class="form-group">
        <label>1. Site que será sobrescrito:</label>
        <select name="site_domain" id="restore-site-select" required>
            <option value="">Carregando lista de sites...</option>
        </select>
        <input type="hidden" name="site_root_path" id="restore-site-root-path">
      </div>

      <div class="form-group">
        <label>2. Arquivo de Backup (.tar.gz):</label>
        <div class="file-input-container">
            <input type="file" name="backup_file" id="restore-site-file-input" class="file-input" required accept=".tar.gz,.gz">
            <label for="restore-site-file-input" class="btn-primary">Escolher arquivo</label>
            <span id="restore-site-file-name">Nenhum arquivo escolhido</span>
        </div>
      </div>
      
      <button type="submit" class="btn-primary">Iniciar Upload e Restauração</button>
      <p id="restore-site-feedback" style="margin-top: 15px; display: none;"></p>
    </form>
  </div>
</div>