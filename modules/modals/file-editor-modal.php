<link rel="stylesheet" href="modules/css/editor.css?t=<?php echo time(); ?>">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/dracula.min.css">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/display/autorefresh.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/matchbrackets.min.js"></script>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/clike/clike.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/php/php.min.js"></script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.css">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/search.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/searchcursor.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.js"></script>

<div id="fileEditorModal" class="modal-backdrop">
  <div class="modal-content" style="max-width: 800px;">
    
    <div class="modal-header">
        <h2 id="file-editor-title">Editando Arquivo: N/A</h2>
        <button class="modal-close-btn" id="closeFileEditorBtn">×</button>
    </div>

    <form id="fileEditorForm">
        <div style="padding: 0 25px;">
            <input type="hidden" name="file_path_to_save" id="file_path_to_save">
            
            <div class="form-group">
                <label id="editor-status-label" style="font-size: 0.8em; color: var(--text-muted); display: block; margin-bottom: 5px;">
                    Carregando conteúdo...
                </label>
                <textarea 
                    name="file_content" 
                    id="file_content_editor" 
                    rows="25" 
                    style="width: 100%; 
                           background: var(--bg-color); 
                           border: 1px solid var(--border-color); 
                           color: var(--text-color); 
                           font-family: monospace; 
                           padding: 10px; 
                           box-sizing: border-box; 
                           font-size: 0.9em;"></textarea>
            </div>
        </div>

        <div style="padding: 15px 25px 25px; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
        
            <div style="display: flex; gap: 10px; flex-wrap: wrap;"> <button type="submit" class="btn-primary" id="saveFileContentBtn">Salvar Alterações</button>
                
                <button type="button" class="action-btn" id="btnBackupFileEditor" 
                        style="margin: 0; padding: 10px 15px; font-weight: 500;">
                    Fazer Backup
                </button>
                
                <button type="button" class="action-btn" id="btnFindCode" 
                        style="margin: 0; padding: 10px 15px; font-weight: 500; background-color: var(--card-bg);">
                    Pesquisar
                </button>
                <button type="button" class="action-btn" id="btnReplaceCode" 
                        style="margin: 0; padding: 10px 15px; font-weight: 500; background-color: var(--card-bg);">
                    Substituir
                </button>
                </div>

            <div style="display: flex; gap: 10px; align-items: center;">
                <span id="save-feedback" style="color: #f44336; font-size: 0.9em;"></span>
                
                <button type="button" class="btn-secondary" id="btnEditorCloseFooter">
                    Fechar
                </button>
            </div>
        </div>
    </form>

  </div>
</div>