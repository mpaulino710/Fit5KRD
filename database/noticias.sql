-- Tabla para el módulo de Noticias / Blog
CREATE TABLE IF NOT EXISTS `noticias` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `titulo` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL UNIQUE,
  `resumen` TEXT NULL,
  `contenido` LONGTEXT NOT NULL,
  `imagen_destacada` VARCHAR(255) NULL,
  `posicion_imagen` VARCHAR(50) DEFAULT 'center center',
  `video_url` VARCHAR(255) NULL,
  `youtube_id` VARCHAR(50) NULL,
  `estado` ENUM('borrador', 'publicado', 'archivado') DEFAULT 'publicado',
  `autor_id` INT NULL,
  `visitas` INT DEFAULT 0,
  `fecha_publicacion` DATETIME NULL,
  `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`autor_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
