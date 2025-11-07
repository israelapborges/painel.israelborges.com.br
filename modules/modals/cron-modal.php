<link rel="stylesheet" href="modules/css/cron-modal.css?t=<?php echo time(); ?>">

<div id="cronModal" class="modal-backdrop">
  <div class="modal-content" style="max-width: 600px;">
    
    <div class="modal-header">
      <h2 id="cron-modal-title">Adicionar Tarefa Agendada</h2>
      <button class="modal-close-btn" id="closeCronModalBtn">×</button>
    </div>
    
    <form id="cronForm">
        <div style="padding: 0 25px 25px 25px;">
        
            <input type="hidden" id="cron_job_id" value="">
            <!-- novo hidden para indicar gravar como root (1) ou não (0) -->
            <input type="hidden" id="cron_as_root" value="1">
      
            <div class="form-group">
                <label>Predefinições Comuns:</label>
                <select id="cron-presets" class="form-group">
                    <option value="custom">-- Agendamento Manual --</option>
                    <option value="* * * * *">A cada minuto</option>
                    <option value="*/5 * * * *">A cada 5 minutos</option>
                    <option value="*/15 * * * *">A cada 15 minutos</option>
                    <option value="0 * * * *">A cada hora (no minuto 0)</option>
                    <option value="0 0 * * *">Uma vez por dia (meia-noite)</option>
                    <option value="0 0 * * 0">Uma vez por semana (Domingo)</option>
                    <option value="0 0 1 * *">Uma vez por mês (Dia 1)</option>
                </select>
            </div>

            <p style="text-align: center; color: var(--text-muted);">OU</p>

            <div class="cron-schedule-grid">
                <div class="form-group">
                    <label for="cron_min">Minuto</label>
                    <input type="text" id="cron_min" value="*">
                </div>
                <div class="form-group">
                    <label for="cron_hour">Hora</label>
                    <input type="text" id="cron_hour" value="*">
                </div>
                <div class="form-group">
                    <label for="cron_day">Dia (Mês)</label>
                    <input type="text" id="cron_day" value="*">
                </div>
                <div class="form-group">
                    <label for="cron_month">Mês</label>
                    <input type="text" id="cron_month" value="*">
                </div>
                <div class="form-group">
                    <label for="cron_weekday">Dia (Sem)</label>
                    <input type="text" id="cron_weekday" value="*">
                </div>
            </div>

            <!-- Campo Título (novo, opcional) -->
            <div class="form-group" style="margin-top:12px;">
                <label for="cron_title">Título (opcional)</label>
                <input type="text" id="cron_title" class="form-control" placeholder="Ex.: MyPanel Worker">
            </div>
            
            <div class="form-group" style="margin-top: 20px;">
                <label for="cron_command">Comando a ser executado:</label>
                <input type="text" id="cron_command" required 
                       placeholder="/usr/bin/php /home/user/meusite/artisan schedule:run"
                       style="font-family: monospace; font-size: 1.1em; width:100%;">
            </div>
            
            <button type="submit" class="btn-primary" id="cron-submit-btn" style="width: 100%; margin-top: 15px;">
                Salvar Tarefa
            </button>
            
            <div id="cron-feedback" style="margin-top: 15px; font-size: 0.9em; text-align: center;"></div>
        </div>
    </form>

  </div>
</div>

<script>
(function(){
  var modal = document.getElementById('cronModal');
  var form = document.getElementById('cronForm');
  var preset = document.getElementById('cron-presets');
  var minEl = document.getElementById('cron_min');
  var hourEl = document.getElementById('cron_hour');
  var dayEl = document.getElementById('cron_day');
  var monthEl = document.getElementById('cron_month');
  var weekdayEl = document.getElementById('cron_weekday');
  var commandEl = document.getElementById('cron_command');
  var titleEl = document.getElementById('cron_title');
  var jobId = document.getElementById('cron_job_id');
  var asRoot = document.getElementById('cron_as_root');
  var feedback = document.getElementById('cron-feedback');
  var closeBtn = document.getElementById('closeCronModalBtn');

  // abre o modal (caso você chame de index.js)
  window.openCronModal = window.openCronModal || function(opts){
    opts = opts || {};
    jobId.value = opts.job_id || '';
    if (opts.preset && opts.preset !== 'custom') {
      preset.value = opts.preset;
      var parts = (opts.preset || '').trim().split(/\s+/);
      if (parts.length === 5) {
        minEl.value = parts[0]; hourEl.value = parts[1];
        dayEl.value = parts[2]; monthEl.value = parts[3]; weekdayEl.value = parts[4];
      }
    } else {
      preset.value = 'custom';
      minEl.value = opts.minute || '*';
      hourEl.value = opts.hour || '*';
      dayEl.value = opts.day_month || '*';
      monthEl.value = opts.month || '*';
      weekdayEl.value = opts.day_week || '*';
    }
    commandEl.value = opts.command || '';
    titleEl.value = opts.title || '';
    asRoot.value = (typeof opts.as_root === 'undefined') ? '1' : (opts.as_root ? '1':'0');
    feedback.textContent = '';
    modal.style.display = 'block';
    setTimeout(function(){ try{ commandEl.focus(); }catch(e){} },50);
  };

  closeBtn.addEventListener('click', function(){ modal.style.display='none'; });

  preset.addEventListener('change', function(){
    var v = this.value.trim();
    if (!v || v === 'custom') return;
    var parts = v.split(/\s+/);
    if (parts.length === 5) {
      minEl.value = parts[0];
      hourEl.value = parts[1];
      dayEl.value = parts[2];
      monthEl.value = parts[3];
      weekdayEl.value = parts[4];
    }
  });

  function hasRedirection(cmd) {
    if (!cmd) return false;
    // heurística simples: qualquer '>' ou '>>' ou N>&M
    return /(^|\s)[<>]{1,2}\s*[^ \t]+/.test(cmd) || /\d+>&\d+/.test(cmd);
  }

  function normalizeCommand(cmd) {
    cmd = (cmd||'').trim();
    if (!cmd) return '';
    if (hasRedirection(cmd)) return cmd;
    return cmd + ' > /dev/null 2>&1';
  }

  form.addEventListener('submit', function(ev){
    ev.preventDefault();
    feedback.style.color = '';
    feedback.textContent = '';

    var cmdRaw = commandEl.value.trim();
    if (!cmdRaw) {
      feedback.style.color = '#e74c3c';
      feedback.textContent = 'Informe o comando a ser executado.';
      commandEl.focus();
      return;
    }

    var payload = {
      minute: (minEl.value||'*').trim(),
      hour: (hourEl.value||'*').trim(),
      day_month: (dayEl.value||'*').trim(),
      month: (monthEl.value||'*').trim(),
      day_week: (weekdayEl.value||'*').trim(),
      command: normalizeCommand(cmdRaw),
      title: (titleEl.value||'').trim(),
      as_root: (asRoot.value === '1' ? 1 : 0),
      preset: (preset.value && preset.value !== 'custom') ? preset.value : '',
      job_id: jobId.value || ''
    };

    var btn = document.getElementById('cron-submit-btn');
    btn.disabled = true;
    btn.textContent = 'Salvando...';

    fetch('/api/save_cron_job.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/json; charset=utf-8'},
      body: JSON.stringify(payload)
    }).then(function(resp){
      btn.disabled = false;
      btn.textContent = 'Salvar Tarefa';
      if (!resp.ok) return resp.text().then(function(t){ throw new Error('Erro: '+resp.status+' '+t); });
      return resp.json();
    }).then(function(json){
      if (!json || !json.success) {
        var msg = (json && json.error) ? json.error : 'Resposta inválida do servidor';
        feedback.style.color = '#e74c3c';
        feedback.textContent = msg;
        return;
      }
      feedback.style.color = '#2ecc71';
      feedback.textContent = json.message || 'Agendamento salvo com sucesso.';
      setTimeout(function(){
        modal.style.display = 'none';
        try { if (typeof loadCronList === 'function') loadCronList(); } catch(e){}
      }, 600);
    }).catch(function(err){
      btn.disabled = false;
      btn.textContent = 'Salvar Tarefa';
      feedback.style.color = '#e74c3c';
      feedback.textContent = (err && err.message) ? err.message : 'Falha ao salvar.';
      console.error('save cron error', err);
    });
  });

})();
</script>
