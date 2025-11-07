<div class="header-controls">
  <h2>Gerenciamento de Full Backups (Sites + BD)</h2>
  <div class="header-actions">
    <button id="btnBackupAllSites" class="btn-success">Backup de Todos os Sites</button>
    <button id="btnGenerateMasterArchive" class="btn-info">Gerar Arquivo Mestre</button>
    <button id="btnRestoreSiteUpload" class="btn-secondary">Restaurar do Computador</button>
  </div>
</div>

<div class="card card--surface card--compact" id="master-archive-card" style="display:none;">
  <div class="card-row">
    <div>
      <h3 class="card-title--inline">Arquivo Mestre (Download)</h3>
      <span id="master-archive-info" class="text-muted card-subtitle"></span>
    </div>
    <div class="card-row-actions action-group">
      <a href="#" id="btnDownloadMasterArchive" class="btn-primary">Download</a>
      <button id="btnDeleteMasterArchive" class="btn-danger">Excluir</button>
    </div>
  </div>
</div>

<div class="card">
  <div class="table-container-responsive">
    <table id="full-backups-table" class="responsive-table responsive-table--full-backups">
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
