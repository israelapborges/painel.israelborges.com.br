document.addEventListener('DOMContentLoaded', () => {
  const btnAddSite = document.getElementById('btnAddSite');
  const modal = document.getElementById('addSiteModal');
  const closeModalBtn = document.getElementById('closeModalBtn');
  const form = document.getElementById('addSiteForm');
  const tableBody = document.getElementById('sites-table-body');

  // ---------- Abrir e fechar modal ----------
  btnAddSite.addEventListener('click', () => {
    modal.classList.add('show');
  });

  closeModalBtn.addEventListener('click', () => {
    modal.classList.remove('show');
  });

  window.addEventListener('click', (e) => {
    if (e.target === modal) modal.classList.remove('show');
  });

  // ---------- Carregar lista ----------
  async function loadSites() {
    try {
      const res = await fetch('api/list_websites.php');
      const data = await res.json();

      tableBody.innerHTML = '';
      if (!data.sites || data.sites.length === 0) {
        tableBody.innerHTML = `<tr><td colspan="4" style="text-align:center;">Nenhum site encontrado.</td></tr>`;
        return;
      }

      data.sites.forEach(site => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${site.domain}</td>
          <td>${site.webserver}</td>
          <td>${site.status}</td>
          <td><button class="btn-primary">Editar</button></td>
        `;
        tableBody.appendChild(tr);
      });
    } catch (err) {
      console.error(err);
      tableBody.innerHTML = `<tr><td colspan="4" style="color:red;text-align:center;">Erro ao carregar sites.</td></tr>`;
    }
  }

  // ---------- Enviar formulário ----------
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    try {
      const res = await fetch('api/create_website.php', { method: 'POST', body: fd });
      if (!res.ok) throw new Error('Erro na requisição');
      modal.classList.remove('show');
      form.reset();
      loadSites();
    } catch (err) {
      alert('Falha ao salvar site.');
      console.error(err);
    }
  });

  loadSites();
});
