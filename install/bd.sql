-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Tempo de geração: 06/11/2025 às 19:52
-- Versão do servidor: 8.0.36-28
-- Versão do PHP: 8.1.32

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `painel`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `backup_sites`
--

CREATE TABLE `backup_sites` (
  `id` int NOT NULL,
  `conf_name` varchar(255) NOT NULL,
  `root_path` varchar(512) NOT NULL,
  `linked_db_id` int DEFAULT NULL,
  `last_backup_file` varchar(255) DEFAULT NULL,
  `last_backup_date` datetime DEFAULT NULL,
  `last_backup_size` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `backup_sites`
--

INSERT INTO `backup_sites` (`id`, `conf_name`, `root_path`, `linked_db_id`, `last_backup_file`, `last_backup_date`, `last_backup_size`) VALUES

-- --------------------------------------------------------

--
-- Estrutura para tabela `crontab`
--

CREATE TABLE `crontab` (
  `id` int NOT NULL,
  `schedule` varchar(100) NOT NULL,
  `command` varchar(1024) NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `crontab`
--

INSERT INTO `crontab` (`id`, `schedule`, `command`, `title`, `is_active`) VALUES
(5, '* * * * *', '/usr/bin/php /home/seu-usuario-painel/htdocs/painel.seusite.com.br/worker.php >> /var/log/mypanel_worker.log 2>&1', 'MyPanel', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `managed_databases`
--

CREATE TABLE `managed_databases` (
  `id` int NOT NULL,
  `db_name` varchar(100) NOT NULL,
  `db_user` varchar(100) NOT NULL,
  `last_backup_file` varchar(255) DEFAULT NULL,
  `last_backup_date` datetime DEFAULT NULL,
  `last_backup_size` bigint DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `managed_databases`
--

INSERT INTO `managed_databases` (`id`, `db_name`, `db_user`, `last_backup_file`, `last_backup_date`, `last_backup_size`, `created_at`) VALUES

-- --------------------------------------------------------

--
-- Estrutura para tabela `panel_settings`
--

CREATE TABLE `panel_settings` (
  `setting_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `panel_settings`
--

INSERT INTO `panel_settings` (`setting_name`, `setting_value`) VALUES
('panel_version', '1.0.0');

-- --------------------------------------------------------

--
-- Estrutura para tabela `pending_tasks`
--

CREATE TABLE `pending_tasks` (
  `id` int NOT NULL,
  `task_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `payload` json NOT NULL,
  `status` enum('pending','processing','complete','failed') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `log` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `pending_tasks`
--

INSERT INTO `pending_tasks` (`id`, `task_type`, `payload`, `status`, `log`, `created_at`) VALUES

-- --------------------------------------------------------

--
-- Estrutura para tabela `ufw`
--

CREATE TABLE `ufw` (
  `id` int NOT NULL,
  `action` varchar(10) NOT NULL DEFAULT 'allow',
  `port` varchar(50) NOT NULL,
  `protocol` varchar(10) NOT NULL DEFAULT 'any',
  `source` varchar(50) NOT NULL DEFAULT 'any',
  `comment` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `ufw`
--

INSERT INTO `ufw` (`id`, `action`, `port`, `protocol`, `source`, `comment`) VALUES

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int NOT NULL,
  `login` varchar(100) NOT NULL,
  `senha_hash` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `login`, `senha_hash`) VALUES
(1, 'admin', '$sdfsdfd$10$sdfdsfdsfdsfdsfsdfsdfsd.dsfsdfdsf');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `backup_sites`
--
ALTER TABLE `backup_sites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `conf_name` (`conf_name`),
  ADD KEY `linked_db_id` (`linked_db_id`);

--
-- Índices de tabela `crontab`
--
ALTER TABLE `crontab`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `managed_databases`
--
ALTER TABLE `managed_databases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `db_name` (`db_name`),
  ADD UNIQUE KEY `db_user` (`db_user`);

--
-- Índices de tabela `panel_settings`
--
ALTER TABLE `panel_settings`
  ADD PRIMARY KEY (`setting_name`);

--
-- Índices de tabela `pending_tasks`
--
ALTER TABLE `pending_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status` (`status`);

--
-- Índices de tabela `ufw`
--
ALTER TABLE `ufw`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `login` (`login`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `backup_sites`
--
ALTER TABLE `backup_sites`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112217;

--
-- AUTO_INCREMENT de tabela `crontab`
--
ALTER TABLE `crontab`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `managed_databases`
--
ALTER TABLE `managed_databases`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de tabela `pending_tasks`
--
ALTER TABLE `pending_tasks`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=148;

--
-- AUTO_INCREMENT de tabela `ufw`
--
ALTER TABLE `ufw`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `backup_sites`
--
ALTER TABLE `backup_sites`
  ADD CONSTRAINT `backup_sites_ibfk_1` FOREIGN KEY (`linked_db_id`) REFERENCES `managed_databases` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
