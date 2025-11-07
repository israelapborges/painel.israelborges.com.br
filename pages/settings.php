<div class="card" style="max-width: 600px;">
    <h2>Configurações da Conta</h2>
    
    <form id="change-password-form">
        <h3>Alterar Senha</h3>
        
        <div class="form-group">
            <label for="senha_antiga">Senha Antiga</label>
            <input type="password" id="senha_antiga" name="senha_antiga" required>
        </div>
        
        <div class="form-group">
            <label for="nova_senha">Nova Senha</label>
            <input type="password" id="nova_senha" name="nova_senha" required minlength="6">
            <small style="color:var(--text-muted); margin-top: 5px; display: block;">Mínimo de 6 caracteres.</small>
        </div>

        <div class="form-group">
            <label for="confirmar_senha">Confirmar Nova Senha</label>
            <input type="password" id="confirmar_senha" name="confirmar_senha" required minlength="6">
        </div>
        
        <button type="submit" id="change-pass-btn" class="btn-primary">Salvar Nova Senha</button>
        
        <p id="settings-feedback" style="margin-top: 15px; display: none;"></p>
    </form>
</div>