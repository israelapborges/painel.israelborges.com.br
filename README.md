 (cd "$(git rev-parse --show-toplevel)" && git apply --3way <<'EOF' 
diff --git a/README.md b/README.md
new file mode 100644
index 0000000000000000000000000000000000000000..278824fc4fba21f523f4b00e32259c69d0f68183
--- /dev/null
+++ b/README.md
@@ -0,0 +1,41 @@
+# Painel Israel Borges
+
+Este repositório contém o código do painel administrativo utilizado no site Israel Borges. O projeto é composto por uma aplicação PHP com módulos, páginas e APIs para gerenciamento de conteúdo.
+
+## Requisitos
+
+- PHP 8+
+- Composer
+- Node.js 18+
+- MySQL/MariaDB
+
+## Instalação
+
+1. Clone este repositório.
+2. Configure o arquivo `config/config.php` com as credenciais de banco de dados e demais variáveis de ambiente.
+3. Instale as dependências PHP via Composer:
+   ```bash
+   composer install
+   ```
+4. Instale as dependências JavaScript:
+   ```bash
+   npm install
+   ```
+5. Execute as migrações ou scripts de banco conforme necessário.
+
+## Desenvolvimento
+
+- `npm run build`: gera os ativos front-end otimizados.
+- `npm run watch`: observa alterações nos arquivos front-end.
+- Configure o servidor web apontando para o diretório do projeto.
+
+## Estrutura de Pastas
+
+- `api/`: Endpoints e serviços REST.
+- `modules/`: Módulos de funcionalidades do painel.
+- `pages/`: Páginas principais da aplicação.
+- `css/`: Recursos de estilo utilizados no painel.
+
+## Licença
+
+Este projeto é privado e não deve ser distribuído sem autorização.
 
EOF
)
