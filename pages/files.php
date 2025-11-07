<?php
// Este arquivo é carregado via AJAX
?>
<div class="card">
    
    <div class="header-controls">
        <h2 id="files-page-title">Arquivos</h2>
        <div style="display: flex; gap: 10px; align-items: center;">
            <button id="btnUploadFile" class="btn-primary">Upload 🚀</button>
            
            <button id="btnToggleFileView" class="action-btn" title="Mudar Visualização" style="font-size: 1.2em; padding: 8px 10px; margin: 0;">
                <span id="view-icon-list">📄</span>
                <span id="view-icon-grid" style="display: none;">🔲</span>
            </button>
        </div>
    </div>

    <div class="breadcrumb-bar-container">
        <div id="file-breadcrumbs" class="breadcrumbs-container">
            <span class="loading">A carregar...</span>
        </div>
        
        <button id="btnRefreshFiles" class="action-btn" title="Atualizar Lista (F5)">
            🔄
        </button>
    </div>

    <div class="table-container-responsive" id="files-view-details">
        <table id="files-table">
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

    <div id="files-view-icons" class="icon-view-container" style="display: none;">
        </div>

</div>

<style>
    /* Alinha a barra de migalhas e o botão de atualizar */
    .breadcrumb-bar-container {
        display: flex;
        align-items: stretch;
        gap: 10px;
        margin-bottom: 20px;
    }
    .breadcrumb-bar-container .breadcrumbs-container {
        flex-grow: 1;
        margin-bottom: 0;
    }
    #btnRefreshFiles {
        flex-shrink: 0;
        padding: 8px 12px;
        font-size: 1.1em;
        line-height: 1.2;
        margin-top: 0;
        margin-bottom: 0;
        margin-right: 0;
    }

    /* O Container dos Ícones */
    .icon-view-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 20px;
        padding: 10px;
    }
    /* Cada Item (Ícone + Nome) */
    .icon-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 10px;
        border-radius: 8px;
        cursor: pointer;
        transition: background 0.2s;
        border: 1px solid transparent;
    }
    .icon-item:hover {
        background: var(--bg-color);
        border-color: var(--border-color);
    }
    /* O Ícone (Emoji) */
    .icon-item-img {
        font-size: 3.5rem;
    }
    /* O Nome do Ficheiro */
    .icon-item-name {
        font-size: 0.9em;
        color: var(--text-color);
        font-weight: 500;
        text-align: center;
        word-break: break-word;
        margin-top: 5px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .icon-item-name a {
        color: var(--text-color);
        text-decoration: none;
    }
    .icon-item-name a:hover {
        text-decoration: underline;
    }

    /* CSS do Breadcrumb (que já existia) */
    .breadcrumbs-container {
        padding: 10px 15px;
        background: var(--bg-color);
        border: 1px solid var(--border-color);
        border-radius: 5px;
        font-size: 0.9em;
        word-wrap: break-word; 
    }
    .breadcrumb-link {
        color: var(--accent-color);
        text-decoration: none;
        cursor: pointer;
    }
    .breadcrumb-link:hover {
        text-decoration: underline;
    }
    .breadcrumb-separator {
        color: var(--text-muted);
        margin: 0 5px;
    }
    .file-link, .dir-link {
        color: var(--text-color);
        text-decoration: none;
        font-weight: 500;
        cursor: pointer;
    }
    .dir-link {
        color: var(--accent-color);
    }
    .file-link:hover, .dir-link:hover {
        text-decoration: underline;
    }
</style>