<?php
// ATENÇÃO: Use uma NOVA SENHA. Esta foi exposta.
$db_host = '127.0.0.1'; // ou 'localhost'
$db_name = 'painel';
$db_user = 'painel';
$db_pass = 'senha'; // <- MUDE ISSO IMEDIATAMENTE
$charset = 'utf8mb4';

$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (\PDOException $e) {
     // Em produção, você não deve exibir o erro, apenas logar.
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>
