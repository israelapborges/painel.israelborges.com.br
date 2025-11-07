<link rel="stylesheet" href="modules/css/permissions-modal.css?t=<?php echo time(); ?>">

<div id="permissionsModal" class="modal-backdrop">
  <div class="modal-content" style="max-width: 540px;">
    
    <div class="modal-header">
      <h2 id="permissions-modal-title">Alterar Permissões</h2>
      <button class="modal-close-btn" id="closePermissionsModalBtn">×</button>
    </div>
    
    <form id="permissionsForm">
      <div style="padding: 0 20px 20px 20px;">
        
        <p style="font-size: 0.9em; margin-top: 0; color: var(--text-muted);">
          Para: <strong id="permissions-path-display" style="color: var(--text-color); word-break: break-all;">...</strong>
        </p>

        <input type="hidden" name="path" id="permissions_path_input">

        <!-- OWNER / GROUP chooser (novo) -->
        <div style="margin: 12px 0 8px 0; display:flex; gap:10px; align-items:center;">
          <div style="flex:1;">
            <label style="font-size:0.85em; color:var(--text-muted); display:block; margin-bottom:6px;">Alterar proprietário</label>
            <div style="display:flex; gap:8px;">
              <!-- Select (populado dinamicamente com owner atual) -->
              <select id="permissions_owner_select" style="flex:0 0 220px; padding:6px;">
                <option value="">— Selecionar —</option>
              </select>
              <!-- free text (user or user:group) -->
              <input id="permissions_owner_input" placeholder="usuario ou usuario:grupo" style="flex:1; max-width:220px; padding:6px;" />
            </div>
            <small style="color:var(--text-muted);">Você pode digitar `usuario` ou `usuario:grupo`. Deixe em branco para não alterar.</small>
          </div>
        </div>

        <div style="height:8px;"></div>

<div class="permissions-grid">
    <!-- CABEÇALHO -->
    <div class="perm-header" style="display: flex; align-items: center; gap: 8px;">
        <span>Todos</span><input type="checkbox" id="perm_select_all" title="Selecionar/Desmarcar todos">
    </div>
    <div class="perm-header">Ler (r)</div>
    <div class="perm-header">Gravar (w)</div>
    <div class="perm-header">Exec (x)</div>
    
    <!-- DONO -->
    <div class="perm-label">Dono</div>
    <div><input type="checkbox" id="perm_owner_r" data-value="256" class="perm-checkbox"></div>
    <div><input type="checkbox" id="perm_owner_w" data-value="128" class="perm-checkbox"></div>
    <div><input type="checkbox" id="perm_owner_x" data-value="64" class="perm-checkbox"></div>

    <!-- GRUPO -->
    <div class="perm-label">Grupo</div>
    <div><input type="checkbox" id="perm_group_r" data-value="32" class="perm-checkbox"></div>
    <div><input type="checkbox" id="perm_group_w" data-value="16" class="perm-checkbox"></div>
    <div><input type="checkbox" id="perm_group_x" data-value="8" class="perm-checkbox"></div>

    <!-- OUTROS -->
    <div class="perm-label">Outros</div>
    <div><input type="checkbox" id="perm_other_r" data-value="4" class="perm-checkbox"></div>
    <div><input type="checkbox" id="perm_other_w" data-value="2" class="perm-checkbox"></div>
    <div><input type="checkbox" id="perm_other_x" data-value="1" class="perm-checkbox"></div>
</div>

        <div class="form-group" style="margin-top: 18px;">
            <label for="permissions_numeric_input">Valor Numérico (Octal):</label>
            <input type="text" name="mode" id="permissions_numeric_input" maxlength="4" value="0755"
                   style="font-family: monospace; font-size: 1.1em; max-width: 100px; padding:6px;">
        </div>

        <div style="margin-top:14px; display:flex; gap:8px;">
          <button type="submit" class="btn-primary" id="permissions-submit-btn" style="flex:1;">
            Salvar Permissões
          </button>
          <button type="button" id="permissions-cancel-btn" class="btn-secondary" style="flex:0 0 110px;">
            Cancelar
          </button>
        </div>
        
        <div id="permissions-feedback" style="margin-top: 12px; font-size: 0.9em; text-align: center;"></div>
      </div>
    </form>

  </div>
</div>