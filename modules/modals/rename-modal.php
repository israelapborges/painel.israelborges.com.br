<div id="renameModal" class="modal-backdrop">
  <div class="modal-content" style="max-width: 500px;">
    
    <div class="modal-header">
      <h2 id="rename-modal-title">Renomear</h2>
      <button class="modal-close-btn" id="closeRenameModalBtn">×</button>
    </div>
    
    <form id="renameForm">
        <div style="padding: 0 25px 25px 25px;">
        
            <p style="font-size: 0.9em; margin-top: 0; color: var(--text-muted);">
                A renomear: <br>
                <strong id="rename-old-path" style="color: var(--text-color); word-break: break-all;">...</strong>
            </p>
            
            <input type="hidden" name="old_path" id="rename_old_path_input">
            <input type="hidden" name="type" id="rename_type_input">

            <div class="form-group">
                <label for="new_name_input">Novo Nome:</label>
                <input type="text" name="new_name" id="new_name_input" required 
                       style="font-family: monospace; font-size: 1.1em;">
            </div>
            
            <button type="submit" class="btn-primary" id="rename-submit-btn" style="width: 100%; margin-top: 15px;">
                Renomear
            </button>
            
            <div id="rename-feedback" style="margin-top: 15px; font-size: 0.9em; text-align: center;"></div>
        </div>
    </form>

  </div>
</div>