<div id="uploadFileModal" class="modal-backdrop">
  <div class="modal-content">
    
    <div class="modal-header">
      <h2 id="upload-modal-title">Upload de Arquivo</h2>
      <button class="modal-close-btn" id="closeUploadModalBtn">×</button>
    </div>
    
    <form id="uploadFileForm" enctype="multipart/form-data">
        <div style="padding: 0 25px 25px 25px;">
        
            <p style="font-size: 0.9em; margin-top: 0; color: var(--text-muted);">
                O arquivo será enviado para: <br>
                <strong id="upload-path-display" style="color: var(--text-color); word-break: break-all;">/</strong>
            </p>
            
            <input type="hidden" name="path" id="upload_dest_path" value="/">
            
            <div class="form-group">
                <label for="file_to_upload">Selecione o arquivo:</label>
                
                <div class="file-input-container">
                    <input type="file" name="file_to_upload" id="file_to_upload" class="file-input" required>
                    <label for="file_to_upload" class="btn-primary">Escolher...</label>
                    <span id="upload-file-name" style="color: var(--text-muted); font-size: 0.9em;">Nenhum arquivo escolhido</span>
                </div>
            </div>
            
            <button type="submit" class="btn-primary" id="upload-submit-btn" style="width: 100%; margin-top: 15px;">Iniciar Upload</button>
            
            <div id="upload-feedback" style="margin-top: 15px; font-size: 0.9em; text-align: center;"></div>
        </div>
    </form>

  </div>
</div>