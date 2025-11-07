const os = require('os');
const pty = require('node-pty');
const WebSocket = require('ws');

// --- Configuração ---
const WS_PORT = 8081; // Porta interna. NÃO a exponha publicamente.
// O shell que o PTY irá abrir.
const shell = os.platform() === 'win32' ? 'powershell.exe' : 'bash';
// O CWD (Diretório) onde o terminal deve começar.
// ATENÇÃO: Corrigido para a sua raiz segura
const startDir = '/home/israelborges-painel/htdocs/painel.israelborges.com.br/';
// --------------------

// 1. Cria o Servidor WebSocket
const wss = new WebSocket.Server({ port: WS_PORT });

console.log(`Servidor PTY (WebSocket) a correr na porta ${WS_PORT}...`);
console.log(`O shell irá iniciar em: ${startDir}`);

// 2. Lida com Novas Conexões
wss.on('connection', (ws) => {
    console.log("Novo cliente conectado!");

    // 3. Cria o PTY (O Terminal Real)
    // Este PTY corre como o utilizador que iniciou o 'server.js' (israelborges-painel)
    const ptyProcess = pty.spawn(shell, [], {
        name: 'xterm-color',
        cols: 80, // Padrão, o cliente irá redimensionar
        rows: 30, // Padrão
        cwd: startDir,
        env: process.env
    });

    // 4. Fluxo de Dados: PTY -> WebSocket -> Cliente (O que o servidor diz)
    ptyProcess.onData((data) => {
        try {
            // Envia a saída do PTY (ls, top, etc.) de volta para o xterm.js
            ws.send(data);
        } catch (e) {
            console.log("Erro ao enviar dados PTY:", e.message);
        }
    });

    // 5. Fluxo de Dados: Cliente -> WebSocket -> PTY (O que o utilizador digita)
    ws.on('message', (message) => {
        // Envia o que o utilizador digitou (ex: 'ls -la\r') para o PTY
        ptyProcess.write(message);
    });

    // 6. Lida com o fecho da ligação
    ws.on('close', () => {
        ptyProcess.kill(); // Mata o processo 'bash' do PTY
        console.log("Cliente desconectado.");
    });
});
