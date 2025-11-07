<div class="header-controls">
  <h2>Gerenciamento de Full Backups (Sites + BD)</h2>
  <div>
    <button id="btnBackupAllSites" class="btn-primary" style="background-color: #4CAF50; margin-right: 10px;">Backup de Todos os Sites</button>
    <button id="btnGenerateMasterArchive" class="btn-primary" style="background-color: #00bcd4; margin-right: 10px;">Gerar Arquivo Mestre</button>
    <button id="btnRestoreSiteUpload" class="btn-primary" style="background-color: #555e7d; margin-right: 10px;">Restaurar do Computador</button>
  </div>
</div>

<div class="card" id="master-archive-card" style="display:none; margin-bottom: 20px; background-color: var(--bg-color);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div>
            <h3 style="margin: 0 0 5px 0;">Arquivo Mestre (Download)</h3>
            <span id="master-archive-info" style="color: var(--text-muted); font-size: 0.9em;"></span>
        </div>
        <div> 
            <a href="#" id="btnDownloadMasterArchive" class="action-link" style="padding: 10px 15px; font-size: 1em;">Download</a>
            <button id="btnDeleteMasterArchive" class="action-btn" style="color: #f44336; padding: 10px 15px; font-size: 1em;">Excluir</button>
        </div>
    </div>
</div>

<div class="card">
  <div class="table-container-responsive">
    <table id="full-backups-table">
      <thead>
        <tr>
          <th>Nome do Site</th>
          <th>Banco de Dados Linkado</th>
          <th>Download</th>
          <th>Data</th>
          <th>Tamanho</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody id="full-backups-table-body">
        <tr><td colspan="6" style="text-align:center;">Analisando sites e backups...</td></tr>
      </tbody>
    </table>
  </div>
</div>