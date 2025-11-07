<div id="compressModal" class="modal-backdrop">
  <div class="modal-content" style="max-width: 500px;">
    
    <div class="modal-header">
      <h2 id="compress-modal-title">Comprimir</h2>
      <button class="modal-close-btn" id="closeCompressModalBtn">×</button>
    </div>
    
    <form id="compressForm">
        <div style="padding: 0 25px 25px 25px;">
        
            <p style="font-size: 0.9em; margin-top: 0; color: var(--text-muted);">
                A comprimir: <br>
                <strong id="compress-source-path" style="color: var(--text-color); word-break: break-all;">...</strong>
            </p>
            
            <input type="hidden" name="source_path" id="compress_source_path_input">
            <input type="hidden" name="type" id="compress_type_input">

            <div class="form-group" style="display: flex; gap: 10px;">
                <div style="flex-grow: 1;">
                    <label for="archive_name_input">Nome do Arquivo:</label>
                    <input type="text" name="archive_name" id="archive_name_input" required 
                           style="font-family: monospace; font-size: 1.1em;">
                </div>
                <div style="flex-shrink: 0; min-width: 100px;">
                    <label for="compress_format_select">Formato:</label>
                    <select id="compress_format_select" class="form-group">
                        <option value="zip">.zip</option>
                        <option value="tar.gz">.tar.gz</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" class="btn-primary" id="compress-submit-btn" style="width: 100%; margin-top: 15px;">
                Enfileirar Compressão
            </button>
            
            <div id="compress-feedback" style="margin-top: 15px; font-size: 0.9em; text-align: center;"></div>
        </div>
    </form>

  </div>
</div>