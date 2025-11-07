<div class="header-controls">
  <h2>Visualizador de Logs</h2>
  <button id="log-refresh-btn" class="btn-primary" title="Recarregar lista e log atual">Recarregar</button>
</div>

<div class="card">
    <div class="log-controls">
        <div class="form-group">
            <label for="log-selector">Selecionar Arquivo de Log:</label>
            <select id="log-selector" name="log-selector">
                <option value="">A carregar lista de logs...</option>
            </select>
        </div>
    </div>
    
    <div class="log-viewer-container">
        <pre id="log-content-area" class="loading">Por favor, selecione um arquivo de log para visualizar.</pre>
    </div>
</div>