-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: fit5k_db
-- ------------------------------------------------------
-- Server version	8.0.46-0ubuntu0.24.04.2

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `albumes`
--

DROP TABLE IF EXISTS `albumes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `albumes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_evento` int NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `descripcion` text,
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `estado` enum('publico','privado','archivado') DEFAULT 'publico',
  PRIMARY KEY (`id`),
  KEY `id_evento` (`id_evento`),
  CONSTRAINT `albumes_ibfk_1` FOREIGN KEY (`id_evento`) REFERENCES `eventos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `anuncios`
--

DROP TABLE IF EXISTS `anuncios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `anuncios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `imagen_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `enlace_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `estado` enum('activo','inactivo') COLLATE utf8mb4_unicode_ci DEFAULT 'activo',
  `clicks` int DEFAULT '0',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bloqueos_ip`
--

DROP TABLE IF EXISTS `bloqueos_ip`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bloqueos_ip` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `intentos` int NOT NULL DEFAULT '1',
  `bloqueado_hasta` datetime DEFAULT NULL,
  `ultimo_intento` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip_address` (`ip_address`),
  KEY `idx_ip` (`ip_address`),
  KEY `idx_bloqueado_hasta` (`bloqueado_hasta`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `categorias_evento`
--

DROP TABLE IF EXISTS `categorias_evento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorias_evento` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_evento` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text,
  `precio` decimal(10,2) DEFAULT '0.00',
  `cupo_maximo` int DEFAULT NULL,
  `cupo_disponible` int DEFAULT NULL,
  `edad_minima` int DEFAULT '0',
  `edad_maxima` int DEFAULT '100',
  PRIMARY KEY (`id`),
  KEY `idx_evento` (`id_evento`),
  CONSTRAINT `categorias_evento_ibfk_1` FOREIGN KEY (`id_evento`) REFERENCES `eventos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `configuraciones`
--

DROP TABLE IF EXISTS `configuraciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `configuraciones` (
  `clave` varchar(100) NOT NULL,
  `valor` text,
  `tipo` enum('string','number','boolean','json','array') DEFAULT 'string',
  `descripcion` text,
  `categoria` varchar(50) DEFAULT NULL,
  `editable` tinyint(1) DEFAULT '1',
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contactos`
--

DROP TABLE IF EXISTS `contactos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contactos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `asunto` varchar(200) NOT NULL,
  `mensaje` text NOT NULL,
  `estado` varchar(50) DEFAULT 'nuevo',
  `fecha` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `respuesta` text,
  `fecha_respuesta` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_fecha` (`fecha`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `email_logs`
--

DROP TABLE IF EXISTS `email_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `recipient` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text,
  `status` enum('sent','failed') DEFAULT 'sent',
  `error_message` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_recipient` (`recipient`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=729 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `equipo`
--

DROP TABLE IF EXISTS `equipo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `equipo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `cargo` varchar(100) NOT NULL,
  `descripcion` text,
  `imagen_url` varchar(255) DEFAULT NULL,
  `orden` int DEFAULT '0',
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eventos`
--

DROP TABLE IF EXISTS `eventos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `eventos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) NOT NULL,
  `tipo_evento` enum('carrera','zumba','caminata','charla','curso','taller') DEFAULT 'carrera',
  `descripcion` text,
  `fecha_evento` datetime NOT NULL,
  `fecha_limite_inscripcion` datetime DEFAULT NULL,
  `ubicacion` varchar(255) NOT NULL,
  `latitud` decimal(10,8) DEFAULT NULL,
  `longitud` decimal(11,8) DEFAULT NULL,
  `latitud_partida` decimal(10,8) DEFAULT NULL,
  `longitud_partida` decimal(11,8) DEFAULT NULL,
  `radio_geocerca` int DEFAULT '30',
  `total_vueltas` int DEFAULT '1',
  `distancia` decimal(5,2) NOT NULL,
  `modalidades` text,
  `cupo_maximo` int NOT NULL,
  `cupo_disponible` int NOT NULL,
  `precio` decimal(10,2) DEFAULT '0.00',
  `imagen_url` varchar(500) DEFAULT NULL,
  `estado` enum('activo','pendiente','cancelado','completado') DEFAULT 'activo',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `id_organizador` int DEFAULT NULL,
  `categoria` enum('5k','10k','media_maraton','maraton','carrera_obstaculos','trail') DEFAULT '5k',
  `nivel_dificultad` enum('principiante','intermedio','avanzado') DEFAULT 'principiante',
  `incluye_camiseta` tinyint(1) DEFAULT '0',
  `metodo_pago` text,
  PRIMARY KEY (`id`),
  KEY `idx_fecha_evento` (`fecha_evento`),
  KEY `idx_estado` (`estado`),
  KEY `idx_categoria` (`categoria`),
  KEY `idx_organizador` (`id_organizador`),
  CONSTRAINT `eventos_ibfk_1` FOREIGN KEY (`id_organizador`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `eventos_patrocinadores`
--

DROP TABLE IF EXISTS `eventos_patrocinadores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `eventos_patrocinadores` (
  `id_evento` int NOT NULL,
  `id_patrocinador` int NOT NULL,
  PRIMARY KEY (`id_evento`,`id_patrocinador`),
  KEY `id_patrocinador` (`id_patrocinador`),
  CONSTRAINT `eventos_patrocinadores_ibfk_1` FOREIGN KEY (`id_evento`) REFERENCES `eventos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `eventos_patrocinadores_ibfk_2` FOREIGN KEY (`id_patrocinador`) REFERENCES `patrocinadores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fotos`
--

DROP TABLE IF EXISTS `fotos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fotos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_album` int NOT NULL,
  `url_foto` varchar(255) NOT NULL,
  `titulo` varchar(100) DEFAULT NULL,
  `fecha_subida` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_album` (`id_album`),
  CONSTRAINT `fotos_ibfk_1` FOREIGN KEY (`id_album`) REFERENCES `albumes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inscripciones`
--

DROP TABLE IF EXISTS `inscripciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inscripciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parent_id` int DEFAULT NULL,
  `es_invitado` tinyint(1) DEFAULT '0',
  `nombre` varchar(100) DEFAULT NULL,
  `apellido` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `genero` varchar(20) DEFAULT NULL,
  `id_usuario` int DEFAULT NULL,
  `id_evento` int NOT NULL,
  `id_promocion` int DEFAULT NULL,
  `fecha_inscripcion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `numero_corredor` varchar(20) DEFAULT NULL,
  `tiempo_final` time DEFAULT NULL,
  `estado` enum('confirmada','pendiente','cancelada','ausente') DEFAULT 'confirmada',
  `categoria` enum('principiante','intermedio','avanzado','elite','general') DEFAULT 'general',
  `modalidad` varchar(50) DEFAULT NULL,
  `contacto_emergencia` varchar(100) DEFAULT NULL,
  `telefono_emergencia` varchar(20) DEFAULT NULL,
  `notas_medicas` text,
  `talla_camiseta` enum('Kid 8','Kid 10','Kid 12','Kid 14','XS','S','M','L','XL','XXL') DEFAULT NULL,
  `metodo_pago` enum('tarjeta','transferencia','efectivo','gratuito') DEFAULT NULL,
  `kit_entregado` tinyint(1) DEFAULT '0',
  `fecha_entrega_kit` timestamp NULL DEFAULT NULL,
  `asistencia` tinyint(1) DEFAULT '0',
  `fecha_asistencia` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_inscripcion` (`id_usuario`,`id_evento`),
  UNIQUE KEY `numero_corredor` (`numero_corredor`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_evento` (`id_evento`),
  KEY `idx_estado` (`estado`),
  KEY `idx_numero_corredor` (`numero_corredor`),
  KEY `fk_inscripciones_promocion` (`id_promocion`),
  KEY `fk_inscripcion_parent` (`parent_id`),
  CONSTRAINT `fk_inscripcion_parent` FOREIGN KEY (`parent_id`) REFERENCES `inscripciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inscripciones_promocion` FOREIGN KEY (`id_promocion`) REFERENCES `promociones` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inscripciones_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inscripciones_ibfk_2` FOREIGN KEY (`id_evento`) REFERENCES `eventos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=342 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `kits_corredor`
--

DROP TABLE IF EXISTS `kits_corredor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kits_corredor` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_evento` int NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `descripcion` text,
  `incluye` text,
  `fecha_entrega` datetime DEFAULT NULL,
  `lugar_entrega` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_evento` (`id_evento`),
  CONSTRAINT `kits_corredor_ibfk_1` FOREIGN KEY (`id_evento`) REFERENCES `eventos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `logs_sistema`
--

DROP TABLE IF EXISTS `logs_sistema`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `logs_sistema` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int DEFAULT NULL,
  `accion` varchar(100) NOT NULL,
  `modulo` varchar(50) DEFAULT NULL,
  `descripcion` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `fecha` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fecha` (`fecha`),
  KEY `idx_accion` (`accion`),
  KEY `idx_usuario` (`id_usuario`),
  CONSTRAINT `logs_sistema_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=114 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notificaciones`
--

DROP TABLE IF EXISTS `notificaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notificaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int DEFAULT NULL,
  `titulo` varchar(200) NOT NULL,
  `mensaje` text NOT NULL,
  `tipo` enum('info','success','warning','error','evento') DEFAULT 'info',
  `leido` tinyint(1) DEFAULT '0',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_lectura` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_leido` (`leido`),
  CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pagos`
--

DROP TABLE IF EXISTS `pagos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pagos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_inscripcion` int NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `metodo_pago` enum('tarjeta','transferencia','efectivo') NOT NULL,
  `estado` enum('completado','pendiente','fallido','reembolsado') DEFAULT 'pendiente',
  `fecha_pago` timestamp NULL DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `transaccion_id` varchar(100) DEFAULT NULL,
  `referencia_pago` varchar(100) DEFAULT NULL,
  `detalles` text,
  PRIMARY KEY (`id`),
  KEY `id_inscripcion` (`id_inscripcion`),
  KEY `idx_estado` (`estado`),
  KEY `idx_transaccion` (`transaccion_id`),
  KEY `idx_fecha_pago` (`fecha_pago`),
  CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`id_inscripcion`) REFERENCES `inscripciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=281 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_token` (`token`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `patrocinadores`
--

DROP TABLE IF EXISTS `patrocinadores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `patrocinadores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) NOT NULL,
  `logo_url` varchar(500) DEFAULT NULL,
  `sitio_web` varchar(500) DEFAULT NULL,
  `descripcion` text,
  `nivel` enum('principal','oro','plata','bronce','colaborador') DEFAULT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `promociones`
--

DROP TABLE IF EXISTS `promociones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `promociones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo` enum('cupon','oferta') DEFAULT 'cupon',
  `nombre` varchar(150) DEFAULT NULL,
  `codigo` varchar(50) DEFAULT NULL,
  `porcentaje` decimal(5,2) NOT NULL,
  `cantidad` int NOT NULL DEFAULT '0' COMMENT 'Total allowed uses',
  `usados` int NOT NULL DEFAULT '0' COMMENT 'Current uses count',
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime NOT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `id_evento` int DEFAULT NULL COMMENT 'NULL for global, or event ID',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `id_evento` (`id_evento`),
  CONSTRAINT `fk_promociones_evento` FOREIGN KEY (`id_evento`) REFERENCES `eventos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `resultados`
--

DROP TABLE IF EXISTS `resultados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resultados` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_inscripcion` int NOT NULL,
  `tiempo_oficial` time NOT NULL,
  `posicion_general` int DEFAULT NULL,
  `posicion_categoria` int DEFAULT NULL,
  `posicion_genero` int DEFAULT NULL,
  `ritmo_promedio` varchar(10) DEFAULT NULL,
  `puntos_obtenidos` int DEFAULT '0',
  `certificado_url` varchar(500) DEFAULT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_resultado` (`id_inscripcion`),
  KEY `idx_posicion_general` (`posicion_general`),
  KEY `idx_posicion_categoria` (`posicion_categoria`),
  CONSTRAINT `resultados_ibfk_1` FOREIGN KEY (`id_inscripcion`) REFERENCES `inscripciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `cedula` varchar(20) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `genero` enum('M','F','O') DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `direccion` text,
  `tipo_usuario` enum('admin','organizador','corredor') DEFAULT 'corredor',
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `estado` enum('activo','inactivo','suspendido') DEFAULT 'activo',
  `foto_perfil` varchar(500) DEFAULT NULL,
  `ultimo_acceso` timestamp NULL DEFAULT NULL,
  `welcome_seen` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_tipo_usuario` (`tipo_usuario`),
  KEY `idx_estado` (`estado`)
) ENGINE=InnoDB AUTO_INCREMENT=317 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary view structure for view `vista_eventos_detallados`
--

DROP TABLE IF EXISTS `vista_eventos_detallados`;
/*!50001 DROP VIEW IF EXISTS `vista_eventos_detallados`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vista_eventos_detallados` AS SELECT 
 1 AS `id`,
 1 AS `nombre`,
 1 AS `descripcion`,
 1 AS `fecha_evento`,
 1 AS `ubicacion`,
 1 AS `latitud`,
 1 AS `longitud`,
 1 AS `distancia`,
 1 AS `cupo_maximo`,
 1 AS `cupo_disponible`,
 1 AS `precio`,
 1 AS `imagen_url`,
 1 AS `estado`,
 1 AS `fecha_creacion`,
 1 AS `fecha_actualizacion`,
 1 AS `id_organizador`,
 1 AS `categoria`,
 1 AS `nivel_dificultad`,
 1 AS `organizador_nombre`,
 1 AS `organizador_apellido`,
 1 AS `total_inscritos`,
 1 AS `total_patrocinadores`,
 1 AS `dias_restantes`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `vista_inscripciones_completas`
--

DROP TABLE IF EXISTS `vista_inscripciones_completas`;
/*!50001 DROP VIEW IF EXISTS `vista_inscripciones_completas`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vista_inscripciones_completas` AS SELECT 
 1 AS `id`,
 1 AS `id_usuario`,
 1 AS `id_evento`,
 1 AS `fecha_inscripcion`,
 1 AS `numero_corredor`,
 1 AS `tiempo_final`,
 1 AS `estado`,
 1 AS `categoria`,
 1 AS `contacto_emergencia`,
 1 AS `telefono_emergencia`,
 1 AS `notas_medicas`,
 1 AS `talla_camiseta`,
 1 AS `metodo_pago`,
 1 AS `usuario_nombre`,
 1 AS `usuario_apellido`,
 1 AS `usuario_email`,
 1 AS `usuario_genero`,
 1 AS `evento_nombre`,
 1 AS `fecha_evento`,
 1 AS `evento_ubicacion`,
 1 AS `evento_distancia`,
 1 AS `tiempo_formateado`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `vista_usuarios_completa`
--

DROP TABLE IF EXISTS `vista_usuarios_completa`;
/*!50001 DROP VIEW IF EXISTS `vista_usuarios_completa`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vista_usuarios_completa` AS SELECT 
 1 AS `id`,
 1 AS `nombre`,
 1 AS `apellido`,
 1 AS `email`,
 1 AS `password`,
 1 AS `fecha_nacimiento`,
 1 AS `genero`,
 1 AS `telefono`,
 1 AS `direccion`,
 1 AS `tipo_usuario`,
 1 AS `fecha_registro`,
 1 AS `estado`,
 1 AS `foto_perfil`,
 1 AS `ultimo_acceso`,
 1 AS `total_inscripciones`,
 1 AS `carreras_completadas`,
 1 AS `mejor_tiempo_5k`*/;
SET character_set_client = @saved_cs_client;

--
-- Final view structure for view `vista_eventos_detallados`
--

/*!50001 DROP VIEW IF EXISTS `vista_eventos_detallados`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`zumba`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `vista_eventos_detallados` AS select `e`.`id` AS `id`,`e`.`nombre` AS `nombre`,`e`.`descripcion` AS `descripcion`,`e`.`fecha_evento` AS `fecha_evento`,`e`.`ubicacion` AS `ubicacion`,`e`.`latitud` AS `latitud`,`e`.`longitud` AS `longitud`,`e`.`distancia` AS `distancia`,`e`.`cupo_maximo` AS `cupo_maximo`,`e`.`cupo_disponible` AS `cupo_disponible`,`e`.`precio` AS `precio`,`e`.`imagen_url` AS `imagen_url`,`e`.`estado` AS `estado`,`e`.`fecha_creacion` AS `fecha_creacion`,`e`.`fecha_actualizacion` AS `fecha_actualizacion`,`e`.`id_organizador` AS `id_organizador`,`e`.`categoria` AS `categoria`,`e`.`nivel_dificultad` AS `nivel_dificultad`,`u`.`nombre` AS `organizador_nombre`,`u`.`apellido` AS `organizador_apellido`,(select count(0) from `inscripciones` `i` where ((`i`.`id_evento` = `e`.`id`) and (`i`.`estado` = 'confirmada'))) AS `total_inscritos`,(select count(distinct `p`.`id_patrocinador`) from `eventos_patrocinadores` `p` where (`p`.`id_evento` = `e`.`id`)) AS `total_patrocinadores`,(to_days(`e`.`fecha_evento`) - to_days(now())) AS `dias_restantes` from (`eventos` `e` left join `usuarios` `u` on((`e`.`id_organizador` = `u`.`id`))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vista_inscripciones_completas`
--

/*!50001 DROP VIEW IF EXISTS `vista_inscripciones_completas`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`zumba`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `vista_inscripciones_completas` AS select `i`.`id` AS `id`,`i`.`id_usuario` AS `id_usuario`,`i`.`id_evento` AS `id_evento`,`i`.`fecha_inscripcion` AS `fecha_inscripcion`,`i`.`numero_corredor` AS `numero_corredor`,`i`.`tiempo_final` AS `tiempo_final`,`i`.`estado` AS `estado`,`i`.`categoria` AS `categoria`,`i`.`contacto_emergencia` AS `contacto_emergencia`,`i`.`telefono_emergencia` AS `telefono_emergencia`,`i`.`notas_medicas` AS `notas_medicas`,`i`.`talla_camiseta` AS `talla_camiseta`,`i`.`metodo_pago` AS `metodo_pago`,`u`.`nombre` AS `usuario_nombre`,`u`.`apellido` AS `usuario_apellido`,`u`.`email` AS `usuario_email`,`u`.`genero` AS `usuario_genero`,`e`.`nombre` AS `evento_nombre`,`e`.`fecha_evento` AS `fecha_evento`,`e`.`ubicacion` AS `evento_ubicacion`,`e`.`distancia` AS `evento_distancia`,(case when (`i`.`tiempo_final` is not null) then concat(floor((hour(`i`.`tiempo_final`) / 60)),'h ',(hour(`i`.`tiempo_final`) % 60),'m ',second(`i`.`tiempo_final`),'s') else 'No registrado' end) AS `tiempo_formateado` from ((`inscripciones` `i` join `usuarios` `u` on((`i`.`id_usuario` = `u`.`id`))) join `eventos` `e` on((`i`.`id_evento` = `e`.`id`))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vista_usuarios_completa`
--

/*!50001 DROP VIEW IF EXISTS `vista_usuarios_completa`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`zumba`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `vista_usuarios_completa` AS select `u`.`id` AS `id`,`u`.`nombre` AS `nombre`,`u`.`apellido` AS `apellido`,`u`.`email` AS `email`,`u`.`password` AS `password`,`u`.`fecha_nacimiento` AS `fecha_nacimiento`,`u`.`genero` AS `genero`,`u`.`telefono` AS `telefono`,`u`.`direccion` AS `direccion`,`u`.`tipo_usuario` AS `tipo_usuario`,`u`.`fecha_registro` AS `fecha_registro`,`u`.`estado` AS `estado`,`u`.`foto_perfil` AS `foto_perfil`,`u`.`ultimo_acceso` AS `ultimo_acceso`,(select count(0) from `inscripciones` `i` where ((`i`.`id_usuario` = `u`.`id`) and (`i`.`estado` = 'confirmada'))) AS `total_inscripciones`,(select count(0) from `inscripciones` `i` where ((`i`.`id_usuario` = `u`.`id`) and (`i`.`estado` = 'confirmada') and (`i`.`tiempo_final` is not null))) AS `carreras_completadas`,(select min(`i`.`tiempo_final`) from (`inscripciones` `i` join `eventos` `e` on((`i`.`id_evento` = `e`.`id`))) where ((`i`.`id_usuario` = `u`.`id`) and (`i`.`tiempo_final` is not null) and (`e`.`distancia` = 5))) AS `mejor_tiempo_5k` from `usuarios` `u` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-14 14:14:24
