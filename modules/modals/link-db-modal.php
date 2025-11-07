<div id="linkDbModal" class="modal-backdrop">
  <div class="modal-content">
    <div class="modal-header">
      <h2 id="link-db-modal-title">Linkar Banco de Dados</h2>
      <button class="modal-close-btn" id="closeLinkDbModalBtn">×</button>
    </div>
    <form id="linkDbForm">
        <p style="margin-top:0;">Selecione o banco de dados que pertence a este site. Isto é necessário para o "Full Backup".</p>
        
        <input type="hidden" name="link_conf_name" id="link_conf_name">
        
        <div class="form-group">
            <label>Banco de Dados Disponível:</label>
            <select name="link_db_id" id="link-db-select" required>
                <option value="">Carregando bancos...</option>
            </select>
        </div>
        
        <button type="submit" class="btn-primary">Salvar Link</button>
    </form>
  </div>
</div>