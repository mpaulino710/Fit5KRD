-- Migración para soporte de vueltas y telemetría de la app móvil Fit5K

CREATE TABLE IF NOT EXISTS `tiempos_vueltas` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_inscripcion` INT NOT NULL,
  `id_usuario` INT NOT NULL,
  `id_evento` INT NOT NULL,
  `numero_vuelta` INT NOT NULL,
  `tiempo_cruce` DATETIME NOT NULL,
  `duracion_segundos` INT NOT NULL,
  `fecha_sincronizacion` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_usuario_evento_vuelta` (`id_usuario`, `id_evento`, `numero_vuelta`),
  KEY `idx_inscripcion` (`id_inscripcion`),
  KEY `idx_evento` (`id_evento`),
  CONSTRAINT `fk_vueltas_inscripcion` FOREIGN KEY (`id_inscripcion`) REFERENCES `inscripciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vueltas_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vueltas_evento` FOREIGN KEY (`id_evento`) REFERENCES `eventos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
