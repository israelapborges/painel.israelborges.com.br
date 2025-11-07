<div class="header-controls">
  <h2>Gerenciamento de Bancos de Dados</h2>
  <div class="header-actions">
    <button id="btnRestoreDatabase" class="btn-secondary">Restaurar do Computador</button>
    <button id="btnAddDatabase" class="btn-primary">Adicionar Banco</button>
  </div>
</div>

<div class="card">
  <div class="table-container-responsive">
    <table id="databases-table" class="responsive-table responsive-table--databases">
      <thead>
        <tr>
          <th>Nome do Banco (DB)</th>
          <th>Usuário</th>
          <th>Download</th>
          <th>Data</th>
          <th>Tamanho</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody id="databases-table-body">
        <tr><td colspan="6" style="text-align:center;">Carregando...</td></tr>
      </tbody>
    </table>
  </div>
</div>
