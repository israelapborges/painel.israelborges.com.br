<div id="addSiteModal" class="modal-backdrop">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Adicionar Site</h2>
      <button class="modal-close-btn" id="closeModalBtn">×</button>
    </div>
    
    <form id="addSiteForm">
      
      <div class="form-group">
        <label>Domínio</label>
        <input type="text" name="domain" required placeholder="www.exemplo.com.br">
      </div>

      <div class="form-group">
        <label>Webserver</label>
        <select name="webserver" required>
          <option value="" disabled selected>Selecione um servidor...</option>
          <option value="nginx">Nginx</option>
          <option value="apache">Apache</option>
          <option value="openlitespeed">OpenLiteSpeed</option>
        </select>
      </div>

      <div class="form-group">
        <label>Status</label>
        <div class="toggle-switch-container">
          <input type="radio" id="status_ativo" name="status" value="Ativo" checked>
          <label for="status_ativo">Ativo</label>
          
          <input type="radio" id="status_inativo" name="status" value="Inativo">
          <label for="status_inativo">Inativo</label>
        </div>
      </div>
      
      <button type="submit" class="btn-primary">Salvar</button>
    </form>
  </div>
</div>