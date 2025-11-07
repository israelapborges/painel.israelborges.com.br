<div id="createNewModal" class="modal-backdrop">
  <div class="modal-content" style="max-width: 500px;">
    
    <div class="modal-header">
      <h2 id="create-new-title">Criar Novo...</h2>
      <button class="modal-close-btn" id="closeCreateNewModalBtn">×</button>
    </div>
    
    <form id="createNewForm">
        <div style="padding: 0 25px 25px 25px;">
        
            <p style="font-size: 0.9em; margin-top: 0; color: var(--text-muted);">
                A criar em: <br>
                <strong id="create-new-parent-dir" style="color: var(--text-color); word-break: break-all;">/</strong>
            </p>
            
            <input type="hidden" name="parent_dir" id="create_new_parent_dir_input">
            <input type="hidden" name="type" id="create_new_type_input"> <div class="form-group">
                <label for="new_name_input" id="create-new-label">Nome:</label>
                <input type="text" name="name" id="create_new_name_input" required 
                       placeholder="ex: index.php ou nova_pasta"
                       style="font-family: monospace; font-size: 1.1em;">
            </div>
            
            <button type="submit" class="btn-primary" id="create-new-submit-btn" style="width: 100%; margin-top: 15px;">
                Criar
            </button>
            
            <div id="create-new-feedback" style="margin-top: 15px; font-size: 0.9em; text-align: center;"></div>
        </div>
    </form>

  </div>
</div>