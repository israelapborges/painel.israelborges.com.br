<div id="nginxConfigModal" class="modal-backdrop">
  <div class="modal-content">
    
    <div class="modal-header">
      <h2 id="nginx-modal-title">Configurar Nginx</h2>
      <button class="modal-close-btn" id="closeNginxConfigBtn">×</button>
    </div>

    <div id="nginx-modal-loading">
      <span class="loading">A ler ficheiro de configuração...</span>
    </div>

    <form id="nginxConfigForm" style="display:none;">
      
      <input type="hidden" name="config_file_path" id="config_file_path" value="">

      <div class="form-group">
        <label>Domínios (server_name)</label>
        <input type="text" name="server_name" id="conf_server_name" placeholder="www.exemplo.com exemplo.com">
      </div>

      <div class="form-group">
        <label>Caminho Raiz (root)</label>
        <input type="text" name="root" id="conf_root" placeholder="/home/user/public_html">
      </div>
      
      <div class="form-group">
        <label>Ficheiros de Índice (index)</label>
        <input type="text" name="index" id="conf_index" placeholder="index.php index.html">
      </div>

      <div class="form-group">
        <label>Socket PHP-FPM (fastcgi_pass)</label>
        <select name="php_socket" id="conf_php_socket">
          <option value="" disabled selected>A detetar...</option>
          <option value="unix:/var/run/php/php8.1-fpm.sock">PHP 8.1</option>
          <option value="unix:/var/run/php/php8.0-fpm.sock">PHP 8.0</option>
          <option value="unix:/var/run/php/php7.4-fpm.sock">PHP 7.4</option>
          <option value="disabled">Desativado</option>
        </select>
        <small style="color:var(--text-muted); margin-top: 5px; display: block;">Selecione "Desativado" para sites estáticos (só HTML/CSS).</small>
      </div>
      
      <button type="submit" class="btn-primary">Salvar Alterações</button>
    </form>
  </div>
</div>