-- Programa de Fidelidade Swing Motel
-- Execute este script no MySQL/MariaDB do projeto.

SET NAMES utf8mb4;

-- Movimentação de pontos (extrato): valores positivos = crédito, negativos = débito
CREATE TABLE IF NOT EXISTS `fidelidade_movimentacao` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_usuario` INT UNSIGNED NOT NULL,
  `pontos` INT NOT NULL COMMENT 'Positivo crédito, negativo débito',
  `tipo` VARCHAR(40) NOT NULL COMMENT 'credito_reserva_app, debito_resgate_suite, debito_resgate_alimento, debito_resgate_bebida, ajuste_admin',
  `descricao` VARCHAR(255) DEFAULT NULL,
  `id_reserva` INT UNSIGNED DEFAULT NULL COMMENT 'Reserva que originou crédito (idempotência)',
  `id_resgate` INT UNSIGNED DEFAULT NULL COMMENT 'Vínculo com pedido de resgate',
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fidelidade_mov_usuario` (`id_usuario`),
  KEY `idx_fidelidade_mov_criado` (`criado_em`),
  KEY `idx_fidelidade_mov_reserva` (`id_reserva`),
  UNIQUE KEY `uq_credito_reserva_app` (`id_reserva`, `tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pedidos de resgate feitos pelo app (controle operacional no motel)
CREATE TABLE IF NOT EXISTS `fidelidade_resgate` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_usuario` INT UNSIGNED NOT NULL,
  `tipo` ENUM('suite','alimento','bebida') NOT NULL,
  `pontos` INT UNSIGNED NOT NULL,
  `id_suite` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('pendente','atendido','cancelado') NOT NULL DEFAULT 'pendente',
  `observacao` VARCHAR(500) DEFAULT NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fidelidade_resgate_usuario` (`id_usuario`),
  KEY `idx_fidelidade_resgate_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
