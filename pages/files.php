<?php
// Este arquivo é carregado via AJAX
?>
<div class="card">
    
    <div class="header-controls">
        <h2 id="files-page-title">Arquivos</h2>
        <div class="header-actions">
            <button id="btnUploadFile" class="btn-primary">Upload 🚀</button>
            <button id="btnToggleFileView" class="btn-icon" title="Mudar Visualização">
                <span id="view-icon-list">📄</span>
                <span id="view-icon-grid" style="display: none;">🔲</span>
            </button>
        </div>
    </div>

    <div class="breadcrumb-bar">
        <div id="file-breadcrumbs" class="breadcrumbs-container">
            <span class="loading">A carregar...</span>
        </div>

        <button id="btnRefreshFiles" class="btn-icon" title="Atualizar Lista (F5)">
            🔄
        </button>
    </div>

    <div class="table-container-responsive" id="files-view-details">
        <table id="files-table" class="responsive-table responsive-table--files">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Tamanho</th>
                    <th>Owner</th>
                    <th>Permissões</th>
                    <th>Modificado</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="files-table-body">
                <tr>
                    <td colspan="6" class="loading" data-label="Status">A carregar lista de arquivos...</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div id="files-view-icons" class="icon-view-container" style="display: none;"></div>

</div>
