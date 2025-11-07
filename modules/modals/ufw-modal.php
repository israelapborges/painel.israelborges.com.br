<script src="modules/js/ufw.js?t=<?php echo time(); ?>"></script>

<div class="modal-backdrop" id="ufwRuleModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="ufw-modal-title">Adicionar Regra de Firewall</h2>
            <button class="modal-close-btn" id="closeUfwModalBtn">&times;</button>
        </div>
        
        <form id="ufwRuleForm" class="modal-tab-content active" style="padding-top: 10px;">
            
            <div class="form-group">
                <label for="ufw_action">Ação:</label>
                <select id="ufw_action" name="action" required>
                    <option value="allow" selected>ALLOW (Permitir)</option>
                    <option value="deny">DENY (Bloquear)</option>
                    <option value="reject">REJECT (Rejeitar)</option>
                    <option value="limit">LIMIT (Limitar conexões)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="ufw_port">Porta:</label>
                <input type="text" id="ufw_port" name="port" placeholder="ex: 22, 80, 443, 8080:8090" required>
            </div>

            <div class="form-group">
                <label for="ufw_protocol">Protocolo:</label>
                <select id="ufw_protocol" name="protocol">
                    <option value="any" selected>Any (TCP/UDP)</option>
                    <option value="tcp">TCP</option>
                    <option value="udp">UDP</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="ufw_source">Origem (IP):</label>
                <input type="text" id="ufw_source" name="source" placeholder="any (padrão) ou 192.168.1.100">
            </div>

            <div class="form-group">
                <label for="ufw_comment">Comentário (Opcional):</label>
                <input type="text" id="ufw_comment" name="comment" placeholder="ex: Acesso SSH Admin">
            </div>
            
            <p id="ufw-form-feedback" style="text-align: center; margin-top: 15px;"></p>

            <div style="text-align: right; margin-top: 20px;">
                <button type="submit" id="ufw-submit-btn" class="btn-primary">Adicionar Regra</button>
            </div>
        </form>
    </div>
</div>