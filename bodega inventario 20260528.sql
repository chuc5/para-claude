-- MySQL dump 10.13  Distrib 8.0.45, for macos15 (arm64)
--
-- Host: 10.60.118.154    Database: apoyo_combustibles
-- ------------------------------------------------------
-- Server version	8.3.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `aprobacionesgerencia`
--

DROP TABLE IF EXISTS `aprobacionesgerencia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `aprobacionesgerencia` (
  `idAprobacionesGerencia` int NOT NULL AUTO_INCREMENT,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Descripción del cierre o período que se envía a aprobación',
  `estadoaprobacionid` int NOT NULL COMMENT 'Estado actual de la aprobación',
  `enviado_por` char(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Usuario de contabilidad que envía la solicitud',
  `fecha_envio` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora en que se envió la solicitud',
  `revisado_por` char(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Usuario de gerencia que aprueba o rechaza',
  `fecha_revision` timestamp NULL DEFAULT NULL COMMENT 'Fecha y hora de la decisión de gerencia',
  `motivo_rechazo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Motivo del rechazo, se limpia cuando se reenvía',
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Notas adicionales del área que envía',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idAprobacionesGerencia`),
  KEY `idx_aprobacionesgerencia_estado` (`estadoaprobacionid`),
  KEY `idx_aprobacionesgerencia_fecha` (`fecha_envio`),
  KEY `idx_aprobacionesgerencia_enviado` (`enviado_por`),
  KEY `idx_aprobacionesgerencia_revisado` (`revisado_por`),
  CONSTRAINT `fk_aprobacionesgerencia_estadoid` FOREIGN KEY (`estadoaprobacionid`) REFERENCES `estadosaprobacion` (`idEstadosAprobacion`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `aprobacionesgerencia`
--

LOCK TABLES `aprobacionesgerencia` WRITE;
/*!40000 ALTER TABLE `aprobacionesgerencia` DISABLE KEYS */;
INSERT INTO `aprobacionesgerencia` VALUES (1,'mes de abril',2,'01JSFRXWDXJSARB23SPPQ6EW89','2026-04-27 23:58:03','01JSFRXWDXJSARB23SPPQ6EW89','2026-04-27 23:58:22',NULL,NULL,'2026-04-27 23:55:38','2026-04-28 14:21:17'),(2,'abril 2',2,'01JSFRXWDXJSARB23SPPQ6EW89','2026-04-28 15:45:08','01JSFRXWDXJSARB23SPPQ6EW89','2026-04-28 15:45:32',NULL,NULL,'2026-04-28 15:42:31','2026-04-28 15:45:32'),(3,'cierre abril',2,'01JSFRXWDXJSARB23SPPQ6EW89','2026-04-28 16:23:08','01JSFRXWDXJSARB23SPPQ6EW89','2026-04-28 16:23:19',NULL,NULL,'2026-04-28 16:19:59','2026-04-28 16:23:19'),(4,'esta solicitud',2,'01JSFRXWDXJSARB23SPPQ6EW89','2026-05-05 14:20:20','01JSFRXWDXJSARB23SPPQ6EW89','2026-05-05 14:44:44',NULL,'esta observacion','2026-05-05 14:20:20','2026-05-05 14:44:44');
/*!40000 ALTER TABLE `aprobacionesgerencia` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `aprobacionesgerencialog`
--

DROP TABLE IF EXISTS `aprobacionesgerencialog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `aprobacionesgerencialog` (
  `idAprobacionesGerenciaLog` int NOT NULL AUTO_INCREMENT,
  `aprobaciongerenciaid` int NOT NULL COMMENT 'Aprobación a la que pertenece este cambio',
  `estadoaprobacion_anterior` int DEFAULT NULL COMMENT 'Estado anterior de la aprobación',
  `estadoaprobacion_nuevo` int NOT NULL COMMENT 'Nuevo estado de la aprobación',
  `usuarioid` char(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Usuario que realizó el cambio de estado',
  `motivo_rechazo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Copia del motivo en el momento del rechazo',
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Observaciones adicionales del cambio',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora del cambio de estado',
  PRIMARY KEY (`idAprobacionesGerenciaLog`),
  KEY `idx_aprobgerencialog_aprobacion` (`aprobaciongerenciaid`),
  KEY `idx_aprobgerencialog_fecha` (`created_at`),
  KEY `idx_aprobgerencialog_usuario` (`usuarioid`),
  KEY `fk_aprobgerencialog_estadoanterior` (`estadoaprobacion_anterior`),
  KEY `fk_aprobgerencialog_estadonuevo` (`estadoaprobacion_nuevo`),
  CONSTRAINT `fk_aprobgerencialog_aprobacionid` FOREIGN KEY (`aprobaciongerenciaid`) REFERENCES `aprobacionesgerencia` (`idAprobacionesGerencia`) ON DELETE CASCADE,
  CONSTRAINT `fk_aprobgerencialog_estadoanterior` FOREIGN KEY (`estadoaprobacion_anterior`) REFERENCES `estadosaprobacion` (`idEstadosAprobacion`),
  CONSTRAINT `fk_aprobgerencialog_estadonuevo` FOREIGN KEY (`estadoaprobacion_nuevo`) REFERENCES `estadosaprobacion` (`idEstadosAprobacion`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `aprobacionesgerencialog`
--

LOCK TABLES `aprobacionesgerencialog` WRITE;
/*!40000 ALTER TABLE `aprobacionesgerencialog` DISABLE KEYS */;
INSERT INTO `aprobacionesgerencialog` VALUES (1,1,NULL,1,'01JSFRXWDXJSARB23SPPQ6EW89',NULL,NULL,'2026-04-27 23:55:38'),(2,1,1,3,'01JSFRXWDXJSARB23SPPQ6EW89','NO concuerda el dato de los faltantes',NULL,'2026-04-27 23:57:46'),(3,1,1,3,'01JSFRXWDXJSARB23SPPQ6EW89','NO concuerda el dato de los faltantes',NULL,'2026-04-27 23:57:46'),(4,1,3,1,NULL,NULL,NULL,'2026-04-27 23:58:03'),(5,1,3,1,'01JSFRXWDXJSARB23SPPQ6EW89',NULL,'Reenvío tras rechazo','2026-04-27 23:58:03'),(6,1,1,2,'01JSFRXWDXJSARB23SPPQ6EW89',NULL,NULL,'2026-04-27 23:58:22'),(7,1,1,2,'01JSFRXWDXJSARB23SPPQ6EW89',NULL,NULL,'2026-04-27 23:58:22'),(8,1,2,1,'01JSFRXWDXJSARB23SPPQ6EW89',NULL,NULL,'2026-04-28 00:07:21'),(9,1,1,3,'01JSFRXWDXJSARB23SPPQ6EW89',NULL,NULL,'2026-04-28 00:07:31'),(10,2,NULL,1,'01JSFRXWDXJSARB23SPPQ6EW89',NULL,NULL,'2026-04-28 15:42:31'),(11,2,1,3,'01JSFRXWDXJSARB23SPPQ6EW89','no quedo bien',NULL,'2026-04-28 15:43:25'),(12,2,3,1,'01JSFRXWDXJSARB23SPPQ6EW89',NULL,'Reenvío tras rechazo','2026-04-28 15:43:36'),(13,2,1,3,'01JSFRXWDXJSARB23SPPQ6EW89','falta liquidacion',NULL,'2026-04-28 15:44:02'),(14,2,3,1,'01JSFRXWDXJSARB23SPPQ6EW89',NULL,'Reenvío tras rechazo','2026-04-28 15:45:08'),(15,2,1,2,'01JSFRXWDXJSARB23SPPQ6EW89',NULL,NULL,'2026-04-28 15:45:32'),(16,3,NULL,1,'01JSFRXWDXJSARB23SPPQ6EW89',NULL,NULL,'2026-04-28 16:19:59'),(17,3,1,3,'01JSFRXWDXJSARB23SPPQ6EW89','por esto',NULL,'2026-04-28 16:22:12'),(18,3,3,1,'01JSFRXWDXJSARB23SPPQ6EW89',NULL,'Reenvío tras rechazo','2026-04-28 16:23:08'),(19,3,1,2,'01JSFRXWDXJSARB23SPPQ6EW89',NULL,NULL,'2026-04-28 16:23:19'),(20,4,NULL,1,'01JSFRXWDXJSARB23SPPQ6EW89',NULL,NULL,'2026-05-05 14:20:20'),(21,4,1,2,'01JSFRXWDXJSARB23SPPQ6EW89',NULL,'esta observacion','2026-05-05 14:44:44');
/*!40000 ALTER TABLE `aprobacionesgerencialog` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `archivosautorizacion`
--

DROP TABLE IF EXISTS `archivosautorizacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `archivosautorizacion` (
  `idArchivosAutorizacion` int NOT NULL AUTO_INCREMENT,
  `solicitudautorizacionid` int NOT NULL,
  `drive_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_en_drive` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_mime` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamano_bytes` bigint DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `subido_por` char(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_subida` datetime DEFAULT CURRENT_TIMESTAMP,
  `estado` enum('activo','eliminado') COLLATE utf8mb4_unicode_ci DEFAULT 'activo',
  PRIMARY KEY (`idArchivosAutorizacion`),
  KEY `idx_archivosautorizacion_solicitud` (`solicitudautorizacionid`),
  KEY `idx_archivosautorizacion_estado` (`estado`),
  CONSTRAINT `fk_archivosautorizacion_solicitudid` FOREIGN KEY (`solicitudautorizacionid`) REFERENCES `solicitudesautorizacion` (`idSolicitudesAutorizacion`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Archivos de respaldo para autorizaciones almacenados en Drive';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `archivosautorizacion`
--

LOCK TABLES `archivosautorizacion` WRITE;
/*!40000 ALTER TABLE `archivosautorizacion` DISABLE KEYS */;
INSERT INTO `archivosautorizacion` VALUES (1,2,'1wJ4ZzdP3eDmONxGfttOKwWlhQl6SSTQm','excepcion_transferencia_146_1749162438.pdf','autorizacion_2_1766531420','application/pdf',289893,NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2025-12-23 17:09:23','activo'),(2,5,'1mr9ZZpMeuvXrbOiG_HuymX1aDJPEwEVv','1.-Manual-de-usuario-FEL-Emision-DTE.pdf','autorizacion_5_1767111964','application/pdf',561913,NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2025-12-30 10:25:07','activo'),(3,6,'1_yk504PbkYUlrRGlssiFZPRd7zTQAJOH','6_5_2025_AB01262000024CGT.pdf','autorizacion_6_1767116746','application/pdf',56603,NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2025-12-30 11:44:49','activo');
/*!40000 ALTER TABLE `archivosautorizacion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comprobantescontables`
--

DROP TABLE IF EXISTS `comprobantescontables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comprobantescontables` (
  `idComprobantesContables` int NOT NULL AUTO_INCREMENT,
  `numero_comprobante` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('lote','liquidacion') COLLATE utf8mb4_unicode_ci DEFAULT 'lote',
  `fecha_comprobante` date NOT NULL,
  `mes_anio_comprobante` date DEFAULT NULL,
  `monto_total` decimal(12,2) NOT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `archivo_adjunto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registrado_por` char(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idComprobantesContables`),
  UNIQUE KEY `uk_comprobantescontables_numero` (`numero_comprobante`),
  KEY `idx_comprobantescontables_numero` (`numero_comprobante`),
  KEY `idx_comprobantescontables_fecha` (`fecha_comprobante`),
  KEY `idx_comprobantes_numero` (`numero_comprobante`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Comprobantes contables asociados a lotes o liquidaciones';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comprobantescontables`
--

LOCK TABLES `comprobantescontables` WRITE;
/*!40000 ALTER TABLE `comprobantescontables` DISABLE KEYS */;
INSERT INTO `comprobantescontables` VALUES (1,'Comp-12 45c','liquidacion','2026-01-21','2026-01-01',10216.45,'Este es el comprobante general',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-01-21 23:58:34','2026-03-09 15:34:28'),(2,'Comprobante 2','liquidacion','2026-02-21','2026-02-01',1775.00,'Este es el segundo comprobante',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-01-21 23:59:26','2026-04-10 22:54:50'),(3,'Comprobante','liquidacion','2026-03-16','2026-03-01',100.00,'Esta nota',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-02-16 19:59:11','2026-04-10 22:54:50'),(4,'454564564','liquidacion','2026-04-02','2026-04-01',385.00,NULL,NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-03-02 21:27:50','2026-04-10 22:54:50'),(5,'COMP-2026-02','liquidacion','2026-06-28','2026-05-01',6.00,NULL,NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-03-03 15:00:11','2026-04-10 22:54:50'),(6,'456456','liquidacion','2026-04-28','2026-04-01',191.00,'hbehbhhd',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-04-28 14:21:32','2026-04-28 14:21:32'),(7,'456123','liquidacion','2026-04-28','2026-04-01',140.00,NULL,NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-04-28 15:56:16','2026-04-28 15:56:16');
/*!40000 ALTER TABLE `comprobantescontables` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `configuracion_sistema`
--

DROP TABLE IF EXISTS `configuracion_sistema`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `configuracion_sistema` (
  `idConfiguracion` int NOT NULL AUTO_INCREMENT,
  `modulo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'liquidaciones',
  `clave` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_dato` enum('boolean','number','string','date') COLLATE utf8mb4_unicode_ci DEFAULT 'string',
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idConfiguracion`),
  UNIQUE KEY `uk_modulo_clave` (`modulo`,`clave`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `configuracion_sistema`
--

LOCK TABLES `configuracion_sistema` WRITE;
/*!40000 ALTER TABLE `configuracion_sistema` DISABLE KEYS */;
INSERT INTO `configuracion_sistema` VALUES (1,'liquidaciones','permitir_nuevas_liquidaciones','1','boolean','Cambio realizado desde el panel de configuración',1,'2026-04-28 19:22:36','2026-05-05 14:15:45');
/*!40000 ALTER TABLE `configuracion_sistema` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `configuraciondiashabiles`
--

DROP TABLE IF EXISTS `configuraciondiashabiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `configuraciondiashabiles` (
  `idConfiguracionDiasHabiles` int NOT NULL AUTO_INCREMENT,
  `cantidad_dias` int NOT NULL DEFAULT '5' COMMENT 'Cantidad de días hábiles permitidos para liquidar (5, 6, 7, etc)',
  `modificado_por` char(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Usuario que realizó la configuración',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idConfiguracionDiasHabiles`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cantidad de días hábiles permitidos - El último registro es el activo';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `configuraciondiashabiles`
--

LOCK TABLES `configuraciondiashabiles` WRITE;
/*!40000 ALTER TABLE `configuraciondiashabiles` DISABLE KEYS */;
INSERT INTO `configuraciondiashabiles` VALUES (1,5,'SYSTEM','Configuración inicial del sistema',0,'2025-12-01 15:40:02'),(2,8,'01JSFRXWDXJSARB23SPPQ6EW89','son 8 dias',1,'2025-12-09 18:27:33');
/*!40000 ALTER TABLE `configuraciondiashabiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `configuraciondiashabilesdetalle`
--

DROP TABLE IF EXISTS `configuraciondiashabilesdetalle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `configuraciondiashabilesdetalle` (
  `idConfiguracionDiasHabilesDetalle` int NOT NULL AUTO_INCREMENT,
  `configuraciondiashabilesid` int NOT NULL,
  `dia_semana` tinyint NOT NULL COMMENT '1=Lunes, 2=Martes, ..., 7=Domingo',
  `es_habil` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=día hábil, 0=no hábil',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idConfiguracionDiasHabilesDetalle`),
  UNIQUE KEY `uk_confdiashab_det_conf_dia` (`configuraciondiashabilesid`,`dia_semana`),
  CONSTRAINT `fk_confdiashab_det_confid` FOREIGN KEY (`configuraciondiashabilesid`) REFERENCES `configuraciondiashabiles` (`idConfiguracionDiasHabiles`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detalle de configuración de días hábiles por día de la semana';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `configuraciondiashabilesdetalle`
--

LOCK TABLES `configuraciondiashabilesdetalle` WRITE;
/*!40000 ALTER TABLE `configuraciondiashabilesdetalle` DISABLE KEYS */;
INSERT INTO `configuraciondiashabilesdetalle` VALUES (1,1,1,1,'2025-12-01 15:40:02'),(2,1,2,1,'2025-12-01 15:40:02'),(3,1,3,1,'2025-12-01 15:40:02'),(4,1,4,1,'2025-12-01 15:40:02'),(5,1,5,1,'2025-12-01 15:40:02'),(6,1,6,0,'2025-12-01 15:40:02'),(7,1,7,0,'2025-12-01 15:40:02'),(22,2,1,1,'2025-12-11 23:43:16'),(23,2,2,1,'2025-12-11 23:43:16'),(24,2,3,1,'2025-12-11 23:43:16'),(25,2,4,1,'2025-12-11 23:43:16'),(26,2,5,1,'2025-12-11 23:43:16'),(27,2,6,0,'2025-12-11 23:43:16'),(28,2,7,0,'2025-12-11 23:43:16');
/*!40000 ALTER TABLE `configuraciondiashabilesdetalle` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `configuraciongeneral`
--

DROP TABLE IF EXISTS `configuraciongeneral`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `configuraciongeneral` (
  `idConfiguracionGeneral` int NOT NULL AUTO_INCREMENT,
  `dia_corte_mensual` tinyint NOT NULL COMMENT 'Día de corte mensual (ej: 28)',
  `modificado_por` char(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Usuario que realizó la configuración',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) DEFAULT '1' COMMENT '1 = configuración activa, 0 = histórica',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idConfiguracionGeneral`),
  KEY `idx_configuraciongeneral_activo` (`activo`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Parámetros generales del sistema - Se usa el último registro activo';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `configuraciongeneral`
--

LOCK TABLES `configuraciongeneral` WRITE;
/*!40000 ALTER TABLE `configuraciongeneral` DISABLE KEYS */;
INSERT INTO `configuraciongeneral` VALUES (1,28,'SYSTEM','Configuración inicial del día de corte mensual',0,'2025-12-01 15:40:02','2025-12-09 16:08:31'),(2,27,'01JSFRXWDXJSARB23SPPQ6EW89','Esta es la observación general',1,'2025-12-09 16:08:31','2025-12-09 16:08:31');
/*!40000 ALTER TABLE `configuraciongeneral` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `configuracionporcentajeanual`
--

DROP TABLE IF EXISTS `configuracionporcentajeanual`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `configuracionporcentajeanual` (
  `idConfiguracionPorcentajeAnual` int NOT NULL AUTO_INCREMENT,
  `porcentaje` decimal(5,2) NOT NULL COMMENT 'Porcentaje de sobrante anual a devolver (ej: 20.00)',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `modificado_por` char(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Usuario que realizó la configuración',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`idConfiguracionPorcentajeAnual`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configuración de porcentaje de devolución anual - El último registro es el activo';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `configuracionporcentajeanual`
--

LOCK TABLES `configuracionporcentajeanual` WRITE;
/*!40000 ALTER TABLE `configuracionporcentajeanual` DISABLE KEYS */;
INSERT INTO `configuracionporcentajeanual` VALUES (1,20.00,'Configuración inicial del sistema','SYSTEM','2025-12-01 15:40:02',0),(2,25.00,'Esta es el pocentaje','01JSFRXWDXJSARB23SPPQ6EW89','2025-12-09 22:25:10',1);
/*!40000 ALTER TABLE `configuracionporcentajeanual` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `devolucionesanuales`
--

DROP TABLE IF EXISTS `devolucionesanuales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `devolucionesanuales` (
  `idDevolucionesAnuales` int NOT NULL AUTO_INCREMENT,
  `usuarioid` char(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ID del usuario del sistema de seguridad',
  `agenciaid` int NOT NULL COMMENT 'Agencia a la que pertenece el usuario en el año',
  `anio` int NOT NULL COMMENT 'Año del cálculo de sobrante',
  `sobrante_calculado` decimal(12,2) NOT NULL COMMENT 'Sobrante anual calculado',
  `porcentaje_aplicado` decimal(5,2) NOT NULL COMMENT 'Porcentaje aplicado (ej: 20.00)',
  `monto_devolucion` decimal(12,2) NOT NULL COMMENT 'Monto que se devolverá al usuario',
  `fecha_calculo` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `comprobantecontableid` int DEFAULT NULL COMMENT 'Comprobante contable asociado al pago de devolución',
  `calculado_por` char(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Usuario que realizó el cálculo',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idDevolucionesAnuales`),
  UNIQUE KEY `uk_devolucionesanuales_usuario_anio` (`usuarioid`,`anio`),
  KEY `fk_devolucionesanuales_comprobanteid` (`comprobantecontableid`),
  KEY `idx_devolucionesanuales_agencia_anio` (`agenciaid`,`anio`),
  CONSTRAINT `fk_devolucionesanuales_comprobanteid` FOREIGN KEY (`comprobantecontableid`) REFERENCES `comprobantescontables` (`idComprobantesContables`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de devoluciones de sobrante anual por usuario';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `devolucionesanuales`
--

LOCK TABLES `devolucionesanuales` WRITE;
/*!40000 ALTER TABLE `devolucionesanuales` DISABLE KEYS */;
/*!40000 ALTER TABLE `devolucionesanuales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `diasdescanso`
--

DROP TABLE IF EXISTS `diasdescanso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `diasdescanso` (
  `idDiasDescanso` int NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `descripcion` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modificado_por` char(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Usuario que realizó la configuración',
  `activo` tinyint(1) DEFAULT '1' COMMENT '1 = configuración activa, 0 = histórica',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idDiasDescanso`),
  UNIQUE KEY `uk_diasdescanso_fecha` (`fecha`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Días feriados y de descanso';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `diasdescanso`
--

LOCK TABLES `diasdescanso` WRITE;
/*!40000 ALTER TABLE `diasdescanso` DISABLE KEYS */;
INSERT INTO `diasdescanso` VALUES (1,'2025-12-08','Por descanso de inicio de año','01JSFRXWDXJSARB23SPPQ6EW89',1,'2025-12-09 19:31:04','2025-12-09 19:31:26'),(2,'2025-12-05','Por descanso de feria','01JSFRXWDXJSARB23SPPQ6EW89',0,'2025-12-09 19:54:23','2025-12-09 21:10:48'),(3,'2025-12-24','Noche buena','01JSFRXWDXJSARB23SPPQ6EW89',1,'2025-12-30 21:36:46','2025-12-30 21:36:46'),(4,'2025-12-25','Navidad','01JSFRXWDXJSARB23SPPQ6EW89',1,'2025-12-30 21:36:57','2025-12-30 21:36:57'),(5,'2026-01-01','Año nuevo','01JSFRXWDXJSARB23SPPQ6EW89',1,'2026-01-02 14:51:18','2026-01-02 14:51:18');
/*!40000 ALTER TABLE `diasdescanso` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estadosaprobacion`
--

DROP TABLE IF EXISTS `estadosaprobacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `estadosaprobacion` (
  `idEstadosAprobacion` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Código único del estado (pendiente, aprobada, rechazada)',
  `nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre descriptivo del estado',
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Descripción detallada del estado',
  `activo` tinyint(1) DEFAULT '1' COMMENT '1 = activo, 0 = inactivo',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idEstadosAprobacion`),
  UNIQUE KEY `uk_estadosaprobacion_codigo` (`codigo`),
  KEY `idx_estadosaprobacion_activo` (`activo`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estadosaprobacion`
--

LOCK TABLES `estadosaprobacion` WRITE;
/*!40000 ALTER TABLE `estadosaprobacion` DISABLE KEYS */;
INSERT INTO `estadosaprobacion` VALUES (1,'pendiente','Pendiente de revisión','La solicitud está esperando aprobación de gerencia',1,'2026-02-17 23:32:04','2026-02-17 23:32:04'),(2,'aprobada','Aprobada','Gerencia aprobó las liquidaciones del lote',1,'2026-02-17 23:32:04','2026-02-17 23:32:04'),(3,'rechazada','Rechazada','Gerencia rechazó y solicita correcciones a contabilidad',1,'2026-02-17 23:32:04','2026-02-17 23:32:04');
/*!40000 ALTER TABLE `estadosaprobacion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `historial_liquidaciones`
--

DROP TABLE IF EXISTS `historial_liquidaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `historial_liquidaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `liquidacionid` int NOT NULL,
  `estado_anterior` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado_nuevo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motivo` text COLLATE utf8mb4_unicode_ci,
  `revisado_por` char(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_cambio` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historial_liquidaciones`
--

LOCK TABLES `historial_liquidaciones` WRITE;
/*!40000 ALTER TABLE `historial_liquidaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `historial_liquidaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `liquidaciones`
--

DROP TABLE IF EXISTS `liquidaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `liquidaciones` (
  `idLiquidaciones` int NOT NULL AUTO_INCREMENT,
  `usuarioid` char(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ID del usuario del sistema de seguridad',
  `numero_factura` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Número de factura del sistema externo',
  `vehiculoid` int DEFAULT NULL,
  `tipoapoyoid` int NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Descripción del gasto',
  `detalle` text COLLATE utf8mb4_unicode_ci COMMENT 'Detalle adicional según tipo de apoyo',
  `monto` decimal(10,2) NOT NULL,
  `monto_factura` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Monto específico a liquidar (puede ser menor al total de factura)',
  `estado` enum('enviada','aprobada','rechazada','devuelta','corregida','de_baja','en_lote','eliminada') COLLATE utf8mb4_unicode_ci DEFAULT 'enviada',
  `fecha_liquidacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `solicitudautorizacionid` int DEFAULT NULL COMMENT 'Si requirió autorización',
  `revisado_por` char(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID del usuario de contabilidad',
  `fecha_revision` timestamp NULL DEFAULT NULL,
  `motivo_rechazo` text COLLATE utf8mb4_unicode_ci,
  `fecha_pago` timestamp NULL DEFAULT NULL,
  `comprobantecontableid` int DEFAULT NULL,
  `aprobaciongerenciaid` int DEFAULT NULL COMMENT 'Aprobación de gerencia a la que pertenece esta liquidación',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idLiquidaciones`),
  UNIQUE KEY `uk_liquidaciones_usuario_factura` (`usuarioid`,`numero_factura`,`estado`),
  KEY `fk_liquidaciones_vehiculoid` (`vehiculoid`),
  KEY `fk_liquidaciones_solicitudautorizacionid` (`solicitudautorizacionid`),
  KEY `idx_liquidaciones_usuarioid` (`usuarioid`),
  KEY `idx_liquidaciones_estado` (`estado`),
  KEY `idx_liquidaciones_fecha` (`fecha_liquidacion`),
  KEY `idx_liquidaciones_tipoapoyo` (`tipoapoyoid`),
  KEY `idx_liquidaciones_factura` (`numero_factura`),
  KEY `idx_liquidaciones_usuarioid_fecha` (`usuarioid`,`fecha_liquidacion`),
  KEY `idx_liquidaciones_usuarioid_estado` (`usuarioid`,`estado`),
  KEY `fk_liquidaciones_comprobantecontableid` (`comprobantecontableid`),
  KEY `idx_liquidaciones_aprobaciongerenciaid` (`aprobaciongerenciaid`),
  CONSTRAINT `fk_liquidaciones_aprobaciongerenciaid` FOREIGN KEY (`aprobaciongerenciaid`) REFERENCES `aprobacionesgerencia` (`idAprobacionesGerencia`) ON DELETE SET NULL,
  CONSTRAINT `fk_liquidaciones_comprobantecontableid` FOREIGN KEY (`comprobantecontableid`) REFERENCES `comprobantescontables` (`idComprobantesContables`),
  CONSTRAINT `fk_liquidaciones_solicitudautorizacionid` FOREIGN KEY (`solicitudautorizacionid`) REFERENCES `solicitudesautorizacion` (`idSolicitudesAutorizacion`),
  CONSTRAINT `fk_liquidaciones_tipoapoyoid` FOREIGN KEY (`tipoapoyoid`) REFERENCES `tiposapoyo` (`idTiposApoyo`),
  CONSTRAINT `fk_liquidaciones_vehiculoid` FOREIGN KEY (`vehiculoid`) REFERENCES `vehiculos` (`idVehiculos`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Liquidaciones de gastos de colaboradores';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `liquidaciones`
--

LOCK TABLES `liquidaciones` WRITE;
/*!40000 ALTER TABLE `liquidaciones` DISABLE KEYS */;
INSERT INTO `liquidaciones` VALUES (1,'01JSFRXWDXJSARB23SPPQ6EW89','123',2,2,'Liquidación de gastos de mantenimiento vehicular 2',NULL,100.00,0.00,'eliminada','2025-12-23 16:03:55',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2025-12-23 16:03:55','2025-12-30 22:13:32'),(5,'01JSFRXWDXJSARB23SPPQ6EW89','123',2,2,'Liquidación de gastos de mantenimiento vehicular',NULL,100.00,0.00,'aprobada','2025-12-23 17:44:06',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2025-12-30 18:54:27',NULL,NULL,1,NULL,'2025-12-23 17:44:06','2025-12-30 18:54:27'),(6,'01JSFRXWDXJSARB23SPPQ6EW89','123456789',2,2,'Liquidación de gastos de mantenimiento vehicular',NULL,20.00,0.00,'rechazada','2025-12-30 16:32:04',5,'01JSFRXWDXJSARB23SPPQ6EW89','2025-12-30 18:56:18','Por este motivo lo rechazo',NULL,NULL,NULL,'2025-12-30 16:32:04','2025-12-30 18:56:18'),(7,'01JSFRXWDXJSARB23SPPQ6EW89','159545-78',NULL,3,'ghjghfjghfjghfjghf',NULL,100.00,0.00,'eliminada','2025-12-30 17:34:39',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2025-12-30 17:34:39','2025-12-30 22:31:11'),(8,'01JSFRXWDXJSARB23SPPQ6EW89','159545-78',2,1,'Liquidación de combustible para vehículo asignado',NULL,100.00,0.00,'aprobada','2025-12-30 17:59:44',6,'01JSFRXWDXJSARB23SPPQ6EW89','2026-01-06 19:26:35',NULL,NULL,1,NULL,'2025-12-30 17:59:44','2026-01-06 19:26:35'),(9,'01JSFRXWDXJSARB23SPPQ6EW89','3309715868',3,2,'Liquidación de gastos de mantenimiento vehicular',NULL,9586.45,0.00,'eliminada','2025-12-30 22:09:56',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2025-12-30 22:09:56','2025-12-30 22:13:32'),(10,'01K3RPAATKGW9VZSS8HXAW4C25','3309715868',3,2,'Liquidación de gastos de mantenimiento vehicular',NULL,9586.45,0.00,'aprobada','2026-01-02 15:21:33',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-01-06 19:26:37',NULL,NULL,1,NULL,'2026-01-02 15:21:33','2026-01-06 19:26:37'),(11,'01JSFRXWDXJSARB23SPPQ6EW89','3527165774',3,1,'Liquidación de combustible para vehículo asignado',NULL,100.00,0.00,'aprobada','2026-01-06 19:08:06',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-01-06 19:26:41',NULL,NULL,1,NULL,'2026-01-06 19:08:06','2026-01-06 19:26:41'),(12,'01JSFRXWDXJSARB23SPPQ6EW89','746147317',2,2,'Liquidación de gastos de mantenimiento vehicular',NULL,300.00,0.00,'eliminada','2026-01-06 19:33:08',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-01-06 19:33:08','2026-01-12 19:16:26'),(13,'01K3RPAATKGW9VZSS8HXAW4C25','846874757',2,1,'Liquidación de combustible para vehículo asignado','por prospeccion',1775.00,0.00,'aprobada','2026-01-06 21:12:18',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-01-21 23:58:46',NULL,NULL,2,NULL,'2026-01-06 21:12:18','2026-01-14 15:03:11'),(14,'01JSFRXWDXJSARB23SPPQ6EW89','1803895669',3,3,'COMPRA DE ACCESORIOS DE VEHÍCULO POR TRABAJO DE CAMPO RELACIONADOS A MI ÁREA',NULL,120.00,0.00,'eliminada','2026-01-12 18:56:14',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-01-12 18:56:14','2026-01-12 19:08:00'),(15,'01JSFRXWDXJSARB23SPPQ6EW89','1803895669',2,3,'COMPRA DE ACCESORIOS DE VEHÍCULO POR TRABAJO DE CAMPO RELACIONADOS A MI ÁREA','LLANTA, PEDAL, COMPRA DE ACCESORIOS DE VEHÍCULO POR TRABAJO DE CAMPO RELACIONADOS A MI ÁREA           D',100.00,120.00,'aprobada','2026-01-12 19:08:26',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-01-22 15:38:03',NULL,NULL,3,NULL,'2026-01-12 19:08:26','2026-01-22 15:37:46'),(16,'01JSFRXWDXJSARB23SPPQ6EW89','746147317',2,2,'MANTENIMIENTO DE VEHÍCULO POR TRABAJO DE CAMPO RELACIONADO DE MI ÁREA',NULL,255.00,300.00,'aprobada','2026-01-12 19:16:36',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-01-21 15:19:15',NULL,NULL,1,NULL,'2026-01-12 19:16:36','2026-01-14 17:04:51'),(17,'01JSFRXWDXJSARB23SPPQ6EW89','4272703358',3,1,'COMPRA DE COMBUSTIBLE DE VEHÍCULO','COBROS EN EL AREA DE COSTA SUR',75.00,85.03,'aprobada','2026-01-13 15:30:43',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-01-14 14:18:38',NULL,NULL,1,NULL,'2026-01-13 15:30:43','2026-01-13 14:18:38'),(18,'01JSFRXWDXJSARB23SPPQ6EW89','2544060990',3,1,'MANTENIMIENTO DE VEHÍCULO POR TRABAJO DE CAMPO RELACIONADO DE MI ÁREA','COBROS DEL ÁREA DE QUETZALTENANGO',315.00,315.00,'aprobada','2026-01-22 17:15:26',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-02-17 15:47:33',NULL,NULL,4,NULL,'2026-01-22 17:15:26','2026-02-26 15:47:28'),(19,'01JSFRXWDXJSARB23SPPQ6EW89','3833285636',4,1,'COMPRA DE COMBUSTIBLE DE VEHÍCULO','GFDGDF GFD GDF',20.00,20.00,'aprobada','2026-02-23 23:59:28',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-02-23 23:59:56',NULL,NULL,4,NULL,'2026-02-23 23:59:28','2026-02-23 23:59:28'),(20,'01JSFRXWE1SP6YWT9WJ09VT0YN','101793844',5,2,'MANTENIMIENTO DE VEHÍCULO POR TRABAJO DE CAMPO RELACIONADO DE MI ÁREA',NULL,50.00,50.00,'aprobada','2026-02-10 14:50:56',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-02-24 14:53:11',NULL,NULL,4,NULL,'2026-02-24 14:50:56','2026-02-24 15:10:37'),(21,'01JSFRXWDXJSARB23SPPQ6EW89','394412957',3,1,'COMPRA DE COMBUSTIBLE DE VEHÍCULO','VCXZV CXZ V VSD',6.00,6.00,'aprobada','2026-03-02 17:58:53',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-03-09 14:38:12',NULL,NULL,5,NULL,'2026-03-02 17:58:53','2026-03-02 17:58:53'),(22,'01JSFRXWDXJSARB23SPPQ6EW89','1026769774',4,1,'MANTENIMIENTO DE VEHÍCULO POR TRABAJO DE CAMPO RELACIONADO DE MI ÁREA','FDSFDSAF',10.00,13.00,'de_baja','2026-03-03 15:46:27',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-03-09 15:34:40',NULL,NULL,NULL,NULL,'2026-03-03 15:46:27','2026-03-20 15:32:42'),(23,'01JSFRXWDXJSARB23SPPQ6EW89','3901703348',3,2,'MANTENIMIENTO DE VEHÍCULO POR TRABAJO DE CAMPO RELACIONADO DE MI ÁREA',NULL,72.00,72.00,'aprobada','2026-03-16 19:41:58',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-03-20 15:27:53',NULL,NULL,6,1,'2026-03-16 19:41:58','2026-03-20 15:32:31'),(24,'01JSFRXWDXJSARB23SPPQ6EW89','3881058339',2,2,'MANTENIMIENTO DE VEHÍCULO POR TRABAJO DE CAMPO RELACIONADO DE MI ÁREA',NULL,300.00,300.00,'de_baja','2026-03-20 15:19:06',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-03-20 15:19:47',NULL,NULL,NULL,NULL,'2026-03-20 15:19:06','2026-03-20 15:19:06'),(25,'01JSFRXWDXJSARB23SPPQ6EW89','3058582346',5,1,'MANTENIMIENTO DE VEHÍCULO POR TRABAJO DE CAMPO RELACIONADO DE MI ÁREA','FDSFASDFDS',40.00,40.00,'aprobada','2026-04-01 19:51:37',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-04-08 17:50:52',NULL,NULL,6,1,'2026-04-01 19:51:37','2026-04-01 17:54:10'),(26,'01JSFRXWDXJSARB23SPPQ6EW89','4103359640',2,2,'MANTENIMIENTO DE VEHÍCULO POR TRABAJO DE CAMPO RELACIONADO DE MI ÁREA',NULL,79.00,79.00,'aprobada','2026-04-21 00:03:24',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-04-24 14:17:10',NULL,NULL,6,1,'2026-04-21 00:03:24','2026-04-21 00:03:24'),(27,'01JSFRXWDXJSARB23SPPQ6EW89','2610712486',4,2,'MANTENIMIENTO DE VEHÍCULO POR TRABAJO DE CAMPO RELACIONADO DE MI ÁREA',NULL,93.00,93.00,'aprobada','2026-04-28 14:26:47',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-04-28 14:27:00',NULL,NULL,7,2,'2026-04-28 14:26:47','2026-04-28 14:26:47'),(28,'01JSFRXWDXJSARB23SPPQ6EW89','310725465',2,2,'MANTENIMIENTO DE VEHÍCULO POR TRABAJO DE CAMPO RELACIONADO DE MI ÁREA',NULL,47.00,47.00,'aprobada','2026-04-28 15:44:35',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-04-28 15:44:39',NULL,NULL,7,2,'2026-04-28 15:44:35','2026-04-28 15:44:35'),(29,'01JSFRXWDXJSARB23SPPQ6EW89','3453437033',3,2,'MANTENIMIENTO DE VEHÍCULO POR TRABAJO DE CAMPO RELACIONADO DE MI ÁREA',NULL,47.00,47.00,'aprobada','2026-04-28 15:47:42',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-04-28 15:47:46',NULL,NULL,NULL,3,'2026-04-28 15:47:42','2026-04-28 15:47:42'),(30,'01JSFRXWDXJSARB23SPPQ6EW89','16861860',4,2,'MANTENIMIENTO DE VEHÍCULO POR TRABAJO DE CAMPO RELACIONADO DE MI ÁREA',NULL,163.00,163.00,'aprobada','2026-05-05 14:15:50',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-05-05 14:16:01',NULL,NULL,NULL,4,'2026-05-05 14:15:50','2026-05-05 14:15:50'),(31,'01JSFRXWDXJSARB23SPPQ6EW89','2915584040',3,2,'MANTENIMIENTO DE VEHÍCULO POR TRABAJO DE CAMPO RELACIONADO DE MI ÁREA',NULL,150.00,150.00,'aprobada','2026-05-05 19:23:32',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-05-15 14:25:50',NULL,NULL,NULL,NULL,'2026-05-05 19:23:32','2026-05-05 19:23:32'),(32,'01JSFRXWDXJSARB23SPPQ6EW89','584E8A83-0036',3,2,'MANTENIMIENTO DE VEHÍCULO POR TRABAJO DE CAMPO RELACIONADO DE MI ÁREA',NULL,13478.86,13478.86,'eliminada','2026-05-05 19:25:28',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-05 19:25:28','2026-05-05 19:33:33'),(33,'01JSFRXWDXJSARB23SPPQ6EW89','2880589686',4,2,'MANTENIMIENTO DE VEHÍCULO POR TRABAJO DE CAMPO RELACIONADO DE MI ÁREA',NULL,50.00,50.00,'eliminada','2026-05-05 19:29:06',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-05 19:29:06','2026-05-05 19:33:25'),(35,'01JSFRXWDXJSARB23SPPQ6EW89','2316848472',4,2,'MANTENIMIENTO DE VEHÍCULO POR TRABAJO DE CAMPO RELACIONADO DE MI ÁREA',NULL,0.81,0.81,'aprobada','2026-05-05 19:50:01',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-05-15 14:25:50',NULL,NULL,NULL,NULL,'2026-05-05 19:50:01','2026-05-05 19:50:01');
/*!40000 ALTER TABLE `liquidaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `liquidacioneslog`
--

DROP TABLE IF EXISTS `liquidacioneslog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `liquidacioneslog` (
  `idLiquidacionesLog` int NOT NULL AUTO_INCREMENT,
  `liquidacionid` int NOT NULL,
  `estado_anterior` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado_nuevo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `usuarioid` char(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID del usuario que realizó el cambio',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idLiquidacionesLog`),
  KEY `idx_liquidacioneslog_liquidacion` (`liquidacionid`),
  KEY `idx_liquidacioneslog_fecha` (`created_at`),
  CONSTRAINT `fk_liquidacioneslog_liquidacionid` FOREIGN KEY (`liquidacionid`) REFERENCES `liquidaciones` (`idLiquidaciones`)
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial de cambios de estado de liquidaciones';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `liquidacioneslog`
--

LOCK TABLES `liquidacioneslog` WRITE;
/*!40000 ALTER TABLE `liquidacioneslog` DISABLE KEYS */;
INSERT INTO `liquidacioneslog` VALUES (1,1,'enviada','de_baja',NULL,NULL,'2025-12-23 16:18:54'),(2,7,'enviada','de_baja',NULL,NULL,'2025-12-30 17:41:56'),(3,5,'enviada','aprobada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2025-12-30 18:54:27'),(4,6,'enviada','rechazada','01JSFRXWDXJSARB23SPPQ6EW89','Por este motivo lo rechazo','2025-12-30 18:56:18'),(5,8,'enviada','devuelta','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2025-12-30 18:57:49'),(6,8,'devuelta','corregida','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2025-12-30 19:09:41'),(7,8,'corregida','devuelta','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2025-12-30 21:16:12'),(8,9,'enviada','de_baja',NULL,NULL,'2025-12-30 22:12:30'),(9,9,'de_baja','eliminada',NULL,NULL,'2025-12-30 22:13:32'),(10,1,'de_baja','eliminada',NULL,NULL,'2025-12-30 22:13:32'),(11,7,'de_baja','eliminada',NULL,NULL,'2025-12-30 22:31:11'),(12,8,'devuelta','corregida','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2025-12-30 23:26:59'),(13,8,'corregida','devuelta','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2025-12-30 23:27:42'),(14,8,'devuelta','corregida','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2025-12-30 23:33:28'),(15,8,'corregida','aprobada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-01-06 19:26:35'),(16,10,'enviada','aprobada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-01-06 19:26:37'),(17,11,'enviada','aprobada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-01-06 19:26:41'),(18,14,'enviada','eliminada',NULL,NULL,'2026-01-12 19:08:00'),(19,12,'enviada','eliminada',NULL,NULL,'2026-01-12 19:16:26'),(20,15,'enviada','devuelta','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-01-14 14:18:27'),(21,17,'enviada','aprobada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-01-14 14:18:38'),(22,15,'devuelta','corregida','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-01-14 14:20:45'),(23,16,'enviada','devuelta','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-01-14 17:03:08'),(24,16,'devuelta','enviada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-01-14 17:04:28'),(25,16,'enviada','devuelta','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-01-14 17:04:38'),(26,16,'devuelta','corregida','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-01-14 17:04:51'),(27,15,'corregida','devuelta','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-01-21 14:36:40'),(28,16,'corregida','aprobada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-01-21 15:19:15'),(29,13,'enviada','aprobada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-01-21 23:58:46'),(30,15,'devuelta','corregida','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-01-22 14:04:42'),(31,15,'corregida','devuelta','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-01-22 14:23:45'),(32,15,'devuelta','corregida','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-01-22 15:37:49'),(33,15,'corregida','aprobada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-01-22 15:38:03'),(34,18,'enviada','devuelta','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-01-22 17:15:41'),(35,18,'devuelta','corregida','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-01-22 17:22:29'),(36,18,'corregida','devuelta','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-01-22 19:23:27'),(37,18,'devuelta','corregida','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-01-22 19:24:09'),(38,18,'corregida','devuelta','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-01-22 21:41:46'),(39,18,'devuelta','aprobada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-02-17 15:47:33'),(40,18,'aprobada','de_baja','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-02-18 17:19:46'),(41,18,'de_baja','aprobada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-02-18 17:24:06'),(42,18,'aprobada','de_baja','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-02-19 17:44:30'),(43,18,'de_baja','aprobada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-02-19 17:44:56'),(44,18,'aprobada','de_baja','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-02-19 17:55:18'),(45,18,'de_baja','aprobada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-02-19 17:57:34'),(46,19,'enviada','aprobada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-02-23 23:59:56'),(47,20,'enviada','aprobada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-02-24 14:53:11'),(48,20,'aprobada','de_baja','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-02-24 15:10:25'),(49,20,'de_baja','aprobada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-02-24 15:10:37'),(50,18,'aprobada','de_baja','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-02-26 15:47:20'),(51,18,'de_baja','aprobada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-02-26 15:47:28'),(52,21,'enviada','aprobada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-03-09 14:38:12'),(53,22,'enviada','aprobada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-03-09 15:34:40'),(54,23,'enviada','devuelta','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-03-16 19:42:18'),(55,24,'enviada','de_baja','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-03-20 15:19:47'),(56,23,'devuelta','de_baja','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-03-20 15:27:53'),(57,23,'de_baja','aprobada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-03-20 15:32:31'),(58,22,'aprobada','de_baja','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-03-20 15:32:42'),(59,25,'enviada','aprobada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-04-08 17:50:52'),(60,26,'enviada','aprobada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-04-21 18:49:01'),(61,26,'aprobada','enviada',NULL,NULL,'2026-04-24 14:17:03'),(62,26,'enviada','aprobada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-04-24 14:17:10'),(63,27,'enviada','aprobada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-04-28 14:27:00'),(64,28,'enviada','aprobada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-04-28 15:44:39'),(65,29,'enviada','aprobada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-04-28 15:47:46'),(66,30,'enviada','aprobada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-05-05 14:16:01'),(67,33,'enviada','eliminada',NULL,NULL,'2026-05-05 19:33:25'),(68,32,'enviada','eliminada',NULL,NULL,'2026-05-05 19:33:33'),(69,31,'enviada','aprobada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-05-15 14:25:50'),(70,35,'enviada','aprobada','01JSFRXWDXJSARB23SPPQ6EW89',NULL,'2026-05-15 14:25:50');
/*!40000 ALTER TABLE `liquidacioneslog` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `periodos_descartados`
--

DROP TABLE IF EXISTS `periodos_descartados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `periodos_descartados` (
  `idPeriodoDescartado` int unsigned NOT NULL AUTO_INCREMENT,
  `ucfid` int unsigned NOT NULL COMMENT 'FK → usuarioscontrolfechas',
  `usuarioid` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `motivo` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Motivo del descarte (obligatorio en UI)',
  `descartado_por` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'idUsuarios del que descarta',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `restaurado_at` timestamp NULL DEFAULT NULL,
  `restaurado_por` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=descartado, 0=restaurado',
  PRIMARY KEY (`idPeriodoDescartado`),
  UNIQUE KEY `uq_ucfid_activo` (`ucfid`,`activo`),
  KEY `idx_usuarioid` (`usuarioid`),
  KEY `idx_activo` (`activo`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Períodos de prueba descartados manualmente del módulo de candidatos';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `periodos_descartados`
--

LOCK TABLES `periodos_descartados` WRITE;
/*!40000 ALTER TABLE `periodos_descartados` DISABLE KEYS */;
INSERT INTO `periodos_descartados` VALUES (1,5,'01JSFRXWDYSDK4HYX1BXPQN08Y','SE REALIZÓ PORQUE EL USUARIO SE RETIRO','01JSFRXWDXJSARB23SPPQ6EW89','2026-04-27 17:13:49','2026-04-27 17:26:04','01JSFRXWDXJSARB23SPPQ6EW89',0),(2,5,'01JSFRXWDYSDK4HYX1BXPQN08Y','POR MOTIVO DE BAJA','01JSFRXWDXJSARB23SPPQ6EW89','2026-04-27 17:26:20',NULL,NULL,1);
/*!40000 ALTER TABLE `periodos_descartados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `presupuestogeneral`
--

DROP TABLE IF EXISTS `presupuestogeneral`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `presupuestogeneral` (
  `idPresupuestoGeneral` int NOT NULL AUTO_INCREMENT,
  `agenciaid` int NOT NULL COMMENT 'ID de agencia del sistema externo',
  `puestoid` int NOT NULL COMMENT 'ID de puesto del sistema de planilla',
  `anio` int NOT NULL COMMENT 'Año del presupuesto',
  `monto_anual` decimal(10,2) GENERATED ALWAYS AS ((`monto_mensual` * 12)) STORED COMMENT 'Presupuesto total anual',
  `monto_mensual` decimal(10,4) DEFAULT NULL,
  `monto_diario` decimal(10,2) GENERATED ALWAYS AS ((case when ((((`anio` % 4) = 0) and ((`anio` % 100) <> 0)) or ((`anio` % 400) = 0)) then (`monto_anual` / 366) else (`monto_anual` / 365) end)) STORED COMMENT 'Monto por día (considera años bisiestos)',
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idPresupuestoGeneral`),
  UNIQUE KEY `uk_presupuestogeneral_agencia_puesto_anio` (`agenciaid`,`puestoid`,`anio`),
  KEY `idx_presupuestogeneral_anio` (`anio`),
  KEY `idx_presupuestogeneral_agencia` (`agenciaid`),
  KEY `idx_presupuestogeneral_activo` (`activo`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Presupuesto anual general - El cálculo mensual es dinámico según días laborados';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `presupuestogeneral`
--

LOCK TABLES `presupuestogeneral` WRITE;
/*!40000 ALTER TABLE `presupuestogeneral` DISABLE KEYS */;
INSERT INTO `presupuestogeneral` (`idPresupuestoGeneral`, `agenciaid`, `puestoid`, `anio`, `monto_mensual`, `observaciones`, `activo`, `created_at`, `updated_at`) VALUES (1,8,30,2025,2083.3333,'Este es el presupuesto asignado',1,'2025-12-10 23:34:43','2025-12-10 23:34:43'),(2,1,5,2025,4166.6667,'Presupuesto aprobado',1,'2025-12-10 23:46:51','2025-12-10 23:46:51'),(3,1,10,2025,3041.6667,NULL,1,'2025-12-10 23:46:51','2025-12-10 23:46:51'),(4,2,8,2024,3333.3333,'Incluye bonificación',0,'2025-12-10 23:46:51','2025-12-11 15:58:36'),(5,99,56,2025,3041.6667,NULL,1,'2025-12-22 23:55:34','2025-12-22 23:55:34'),(6,99,56,2026,1000.0000,'Presupuesto del área de programacion',1,'2026-01-02 14:29:25','2026-03-27 21:40:49'),(7,1,30,2026,1200.0000,'Esta es la observacion',1,'2026-02-23 23:55:14','2026-04-29 15:43:10'),(8,8,30,2026,1000.0000,NULL,1,'2026-02-24 14:54:41','2026-03-12 17:34:19'),(9,6,30,2026,1500.0000,NULL,1,'2026-03-12 18:11:11','2026-05-19 18:47:00'),(10,12,30,2026,800.0000,'Presupuesto aprobado',1,'2026-03-12 18:26:25','2026-05-19 22:55:35'),(11,1,30,2025,1000.0000,NULL,1,'2026-04-28 23:41:36','2026-04-28 23:41:36');
/*!40000 ALTER TABLE `presupuestogeneral` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `registros_extraordinarios`
--

DROP TABLE IF EXISTS `registros_extraordinarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `registros_extraordinarios` (
  `idRegistroExtraordinario` int NOT NULL AUTO_INCREMENT,
  `usuarioid` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ucfid` int NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `descripcion` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'complemento_prueba',
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aprobada',
  `comprobantecontableid` int DEFAULT NULL,
  `registrado_por` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`idRegistroExtraordinario`),
  KEY `idx_usuario` (`usuarioid`),
  KEY `idx_ucf` (`ucfid`),
  KEY `idx_estado` (`estado`),
  CONSTRAINT `registros_extraordinarios_ibfk_1` FOREIGN KEY (`ucfid`) REFERENCES `usuarioscontrolfechas` (`idUsuariosControlFechas`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `registros_extraordinarios`
--

LOCK TABLES `registros_extraordinarios` WRITE;
/*!40000 ALTER TABLE `registros_extraordinarios` DISABLE KEYS */;
INSERT INTO `registros_extraordinarios` VALUES (1,'01JSFRXWDXJSARB23SPPQ6EW89',4,100.00,'COMPROBANTE 2','complemento_prueba','aprobada',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-04-10 19:05:31','2026-04-27 19:24:47',1),(2,'01JSFRXWDXJSARB23SPPQ6EW89',4,100.00,'COMPROBANTE 1','complemento_prueba','cancelado',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-04-10 19:10:02','2026-04-13 19:23:47',1),(3,'01JSFRXWDXJSARB23SPPQ6EW89',4,10.00,'CAMPO GREGADO','complemento_prueba','aprobada',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-04-13 19:24:07','2026-04-27 19:24:47',1),(4,'01JSFRXWE1SP6YWT9WJ09VT0YN',3,950.00,'COMPLEMENTO PERÍODO DE PRUEBA','complemento_prueba','cancelado',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-04-27 17:36:14','2026-04-27 17:36:26',1),(5,'01JSFRXWE1SP6YWT9WJ09VT0YN',3,950.00,'COMPLEMENTO PERÍODO DE PRUEBA','complemento_prueba','pendiente',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-04-29 16:46:51','2026-04-29 16:46:51',1),(6,'01JSFRXWDXJSARB23SPPQ6EW89',6,100.16,'COMPLEMENTO PERÍODO DE PRUEBA','complemento_prueba','cancelado',NULL,'01JSFRXWDXJSARB23SPPQ6EW89','2026-04-29 16:46:51','2026-04-29 16:49:19',1);
/*!40000 ALTER TABLE `registros_extraordinarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `solicitudesautorizacion`
--

DROP TABLE IF EXISTS `solicitudesautorizacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `solicitudesautorizacion` (
  `idSolicitudesAutorizacion` int NOT NULL AUTO_INCREMENT,
  `usuarioid` char(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ID del usuario del sistema de seguridad',
  `numero_factura` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Número de factura del sistema externo',
  `dias_habiles_excedidos` int NOT NULL,
  `justificacion` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` enum('pendiente','aprobada','rechazada') COLLATE utf8mb4_unicode_ci DEFAULT 'pendiente',
  `fecha_solicitud` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_respuesta` timestamp NULL DEFAULT NULL,
  `autorizado_por` char(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID del usuario de gerencia que autorizó/rechazó',
  `motivo_rechazo` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`idSolicitudesAutorizacion`),
  UNIQUE KEY `uk_solicitudesautorizacion_usuario_factura` (`usuarioid`,`numero_factura`,`fecha_solicitud`),
  KEY `idx_solicitudesautorizacion_estado` (`estado`),
  KEY `idx_solicitudesautorizacion_fecha` (`fecha_solicitud`),
  KEY `idx_solicitudesautorizacion_factura` (`numero_factura`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Solicitudes de autorización para facturas fuera de plazo';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `solicitudesautorizacion`
--

LOCK TABLES `solicitudesautorizacion` WRITE;
/*!40000 ALTER TABLE `solicitudesautorizacion` DISABLE KEYS */;
INSERT INTO `solicitudesautorizacion` VALUES (2,'01JSFRXWDXJSARB23SPPQ6EW89','123456789',152,'Es por este motivo la tardanza jajajja','rechazada','2025-12-23 23:09:16','2025-12-30 16:22:29','01JSFRXWDXJSARB23SPPQ6EW89','Por esto lo rechazo'),(5,'01JSFRXWDXJSARB23SPPQ6EW89','123456789',157,'ahora si subi el archivo jajajajaja','aprobada','2025-12-30 16:25:03','2025-12-30 16:25:38','01JSFRXWDXJSARB23SPPQ6EW89',NULL),(6,'01JSFRXWDXJSARB23SPPQ6EW89','159545-78',3,'por este motivo me retarde por eso la solicitud','aprobada','2025-12-30 17:44:45','2025-12-30 17:54:56','01JSFRXWDXJSARB23SPPQ6EW89',NULL);
/*!40000 ALTER TABLE `solicitudesautorizacion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `solicitudescorreccion`
--

DROP TABLE IF EXISTS `solicitudescorreccion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `solicitudescorreccion` (
  `idSolicitudesCorreccion` int NOT NULL AUTO_INCREMENT,
  `liquidacionid` int NOT NULL,
  `solicitado_por` char(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Usuario de contabilidad que solicita la corrección',
  `motivo` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Descripción de lo que debe corregirse',
  `estado` enum('pendiente','realizada','cancelada') COLLATE utf8mb4_unicode_ci DEFAULT 'pendiente',
  `fecha_solicitud` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_correccion` timestamp NULL DEFAULT NULL COMMENT 'Cuando el usuario realizó la corrección',
  `observaciones_correccion` text COLLATE utf8mb4_unicode_ci COMMENT 'Comentarios del usuario al corregir',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idSolicitudesCorreccion`),
  KEY `idx_solicitudescorreccion_liquidacion` (`liquidacionid`),
  KEY `idx_solicitudescorreccion_estado` (`estado`),
  KEY `idx_solicitudescorreccion_fecha` (`fecha_solicitud`),
  CONSTRAINT `fk_solicitudescorreccion_liquidacionid` FOREIGN KEY (`liquidacionid`) REFERENCES `liquidaciones` (`idLiquidaciones`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial de solicitudes de corrección en liquidaciones';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `solicitudescorreccion`
--

LOCK TABLES `solicitudescorreccion` WRITE;
/*!40000 ALTER TABLE `solicitudescorreccion` DISABLE KEYS */;
INSERT INTO `solicitudescorreccion` VALUES (1,8,'01JSFRXWDXJSARB23SPPQ6EW89','Favor de corregir la descripcion','realizada','2025-12-30 18:57:49','2025-12-30 19:09:41',NULL,'2025-12-30 18:57:49','2025-12-30 19:09:41'),(2,8,'01JSFRXWDXJSARB23SPPQ6EW89','Cambio Número 2','realizada','2025-12-30 21:16:12','2025-12-30 23:26:59',NULL,'2025-12-30 21:16:12','2025-12-30 23:26:59'),(3,8,'01JSFRXWDXJSARB23SPPQ6EW89','Correcion 3','realizada','2025-12-30 23:27:42','2025-12-30 23:33:28',NULL,'2025-12-30 23:27:42','2025-12-30 23:33:28'),(4,15,'01JSFRXWDXJSARB23SPPQ6EW89','Favor de corregir el dato','realizada','2026-01-14 14:18:27','2026-01-14 14:20:45',NULL,'2026-01-14 14:18:27','2026-01-14 14:20:45'),(5,16,'01JSFRXWDXJSARB23SPPQ6EW89','Corregir','realizada','2026-01-14 17:03:08',NULL,NULL,'2026-01-14 17:03:08','2026-01-14 17:30:37'),(6,16,'01JSFRXWDXJSARB23SPPQ6EW89','correcion 2','realizada','2026-01-14 17:04:38','2026-01-14 17:04:51',NULL,'2026-01-14 17:04:38','2026-01-14 17:04:51'),(7,15,'01JSFRXWDXJSARB23SPPQ6EW89','de vuelta','realizada','2026-01-21 14:36:40',NULL,NULL,'2026-01-21 14:36:40','2026-01-22 14:04:42'),(8,15,'01JSFRXWDXJSARB23SPPQ6EW89','Necesito que se cambie la descripción','realizada','2026-01-22 14:23:45',NULL,NULL,'2026-01-22 14:23:45','2026-01-22 15:37:49'),(9,18,'01JSFRXWDXJSARB23SPPQ6EW89','favor de corregir','realizada','2026-01-22 17:15:41',NULL,NULL,'2026-01-22 17:15:41','2026-01-22 19:18:51'),(10,18,'01JSFRXWDXJSARB23SPPQ6EW89','Favor de corregir 2','realizada','2026-01-22 19:23:27',NULL,NULL,'2026-01-22 19:23:27','2026-01-22 19:24:14'),(11,18,'01JSFRXWDXJSARB23SPPQ6EW89','Corregir','pendiente','2026-01-22 21:41:46',NULL,NULL,'2026-01-22 21:41:46','2026-01-22 21:41:46'),(12,18,'01JSFRXWDXJSARB23SPPQ6EW89','correcion 2','pendiente','2026-01-22 21:42:55',NULL,NULL,'2026-01-22 21:42:55','2026-01-22 21:42:55'),(13,23,'01JSFRXWDXJSARB23SPPQ6EW89','Favor de reparar','pendiente','2026-03-16 19:42:18',NULL,NULL,'2026-03-16 19:42:18','2026-03-16 19:42:18'),(14,23,'01JSFRXWDXJSARB23SPPQ6EW89','solicitud 2','pendiente','2026-03-16 19:43:30',NULL,NULL,'2026-03-16 19:43:30','2026-03-16 19:43:30'),(15,23,'01JSFRXWDXJSARB23SPPQ6EW89','esto jajaja','pendiente','2026-03-16 19:48:19',NULL,NULL,'2026-03-16 19:48:19','2026-03-16 19:48:19');
/*!40000 ALTER TABLE `solicitudescorreccion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suspensiones`
--

DROP TABLE IF EXISTS `suspensiones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suspensiones` (
  `idSuspensiones` int NOT NULL AUTO_INCREMENT,
  `usuarioid` char(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ID del usuario del sistema de seguridad',
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `motivo` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dias_suspension` int GENERATED ALWAYS AS (((to_days(`fecha_fin`) - to_days(`fecha_inicio`)) + 1)) STORED,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idSuspensiones`),
  KEY `idx_suspensiones_usuarioid` (`usuarioid`),
  KEY `idx_suspensiones_fechas` (`fecha_inicio`,`fecha_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Periodos de suspensión de colaboradores';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suspensiones`
--

LOCK TABLES `suspensiones` WRITE;
/*!40000 ALTER TABLE `suspensiones` DISABLE KEYS */;
/*!40000 ALTER TABLE `suspensiones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tiposapoyo`
--

DROP TABLE IF EXISTS `tiposapoyo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tiposapoyo` (
  `idTiposApoyo` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `aplica_limite_mensual` tinyint(1) DEFAULT '0' COMMENT '1=combustible tiene límite mensual',
  `descripcion_predefinida` text COLLATE utf8mb4_unicode_ci,
  `requiere_detalle` tinyint DEFAULT '0',
  `titulo_detalle` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `placeholder_detalle` text COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idTiposApoyo`),
  UNIQUE KEY `uk_tiposapoyo_codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tipos: combustible, mantenimiento, accesorios';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tiposapoyo`
--

LOCK TABLES `tiposapoyo` WRITE;
/*!40000 ALTER TABLE `tiposapoyo` DISABLE KEYS */;
INSERT INTO `tiposapoyo` VALUES (1,'COMBUSTIBLE','COMBUSTIBLE',1,'COMPRA DE COMBUSTIBLE DE VEHÍCULO',1,'Para','Ingrese para que el uso',1,'2025-12-01 15:40:02','2026-01-13 14:05:39'),(2,'MANTENIMIENTO','MANTENIMIENTO',0,'MANTENIMIENTO DE VEHÍCULO POR TRABAJO DE CAMPO RELACIONADO DE MI ÁREA',0,'','',1,'2025-12-01 15:40:02','2026-01-13 14:05:39'),(3,'ACCESORIOS','ACCESORIOS',0,'COMPRA DE ACCESORIOS DE VEHÍCULO POR TRABAJO DE CAMPO RELACIONADOS A MI ÁREA',1,'Listar Accesorios','Listar el o los accesorios',1,'2025-12-01 15:40:02','2026-01-13 14:05:39');
/*!40000 ALTER TABLE `tiposapoyo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tiposvehiculo`
--

DROP TABLE IF EXISTS `tiposvehiculo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tiposvehiculo` (
  `idTiposVehiculo` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idTiposVehiculo`),
  UNIQUE KEY `uk_tiposvehiculo_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tipos: moto, carro, pickup, etc.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tiposvehiculo`
--

LOCK TABLES `tiposvehiculo` WRITE;
/*!40000 ALTER TABLE `tiposvehiculo` DISABLE KEYS */;
INSERT INTO `tiposvehiculo` VALUES (1,'MOTOCICLETA',1,'2025-12-01 15:40:02'),(2,'AUTOMÓVIL',1,'2025-12-01 15:40:02'),(3,'PICKUP',1,'2025-12-01 15:40:02'),(4,'CAMIONETA',1,'2025-12-01 15:40:02'),(5,'OTRO',1,'2025-12-01 15:40:02'),(6,'SEDAN',0,'2025-12-09 17:28:33'),(7,'HATCHBACK',1,'2026-01-13 16:13:39');
/*!40000 ALTER TABLE `tiposvehiculo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarioscontrolfechas`
--

DROP TABLE IF EXISTS `usuarioscontrolfechas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarioscontrolfechas` (
  `idUsuariosControlFechas` int NOT NULL AUTO_INCREMENT,
  `usuarioid` char(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ID del usuario del sistema de seguridad',
  `puestoid` int NOT NULL COMMENT 'ID del puesto en este período',
  `agenciaid` int NOT NULL COMMENT 'ID de la agencia en este período',
  `fecha_ingreso` date NOT NULL,
  `fecha_egreso` date DEFAULT NULL,
  `fecha_fin_prueba` date DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `es_nuevo` int DEFAULT NULL,
  `porcentaje_presupuesto` int DEFAULT NULL,
  `dias_presupuesto` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idUsuariosControlFechas`),
  KEY `idx_usuarioscontrolfechas_usuarioid` (`usuarioid`),
  KEY `idx_usuarioscontrolfechas_activo` (`activo`),
  KEY `idx_usuarioscontrolfechas_puesto` (`puestoid`),
  KEY `idx_usuarioscontrolfechas_agencia` (`agenciaid`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Control de fechas de ingreso/egreso para cálculo de presupuesto; permite múltiples periodos por usuario';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarioscontrolfechas`
--

LOCK TABLES `usuarioscontrolfechas` WRITE;
/*!40000 ALTER TABLE `usuarioscontrolfechas` DISABLE KEYS */;
INSERT INTO `usuarioscontrolfechas` VALUES (3,'01JSFRXWE1SP6YWT9WJ09VT0YN',30,8,'2026-02-01',NULL,'2026-03-31',1,1,50,59,'2026-02-24 14:40:51','2026-04-20 19:13:08'),(4,'01JSFRXWDXJSARB23SPPQ6EW89',56,99,'2026-03-15',NULL,'2026-05-14',0,1,50,61,'2026-02-25 16:42:36','2026-05-11 18:01:05'),(5,'01JSFRXWDYSDK4HYX1BXPQN08Y',30,1,'2026-01-13',NULL,'2026-03-12',1,1,50,59,'2026-03-12 23:43:26','2026-03-12 23:43:26'),(6,'01JSFRXWDXJSARB23SPPQ6EW89',30,1,'2025-12-15','2026-03-14','2026-02-14',1,1,50,62,'2026-04-16 22:55:09','2026-05-05 19:46:54');
/*!40000 ALTER TABLE `usuarioscontrolfechas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `validacionesanuales`
--

DROP TABLE IF EXISTS `validacionesanuales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `validacionesanuales` (
  `idValidacionesAnuales` int NOT NULL AUTO_INCREMENT,
  `agenciaid` int NOT NULL COMMENT 'ID de agencia del sistema externo',
  `anio` int NOT NULL,
  `validado_por` char(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID del usuario del área financiera que validó',
  `fecha_validacion` timestamp NULL DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idValidacionesAnuales`),
  UNIQUE KEY `uk_validacionesanuales_agencia_anio` (`agenciaid`,`anio`),
  KEY `idx_validacionesanuales_anio` (`anio`),
  KEY `idx_validacionesanuales_fecha` (`fecha_validacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Validación anual del área financiera sobre lo procesado por contabilidad';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `validacionesanuales`
--

LOCK TABLES `validacionesanuales` WRITE;
/*!40000 ALTER TABLE `validacionesanuales` DISABLE KEYS */;
/*!40000 ALTER TABLE `validacionesanuales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehiculos`
--

DROP TABLE IF EXISTS `vehiculos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehiculos` (
  `idVehiculos` int NOT NULL AUTO_INCREMENT,
  `usuarioid` char(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ID del usuario del sistema de seguridad',
  `tipovehiculoid` int NOT NULL,
  `placa` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `marca` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idVehiculos`),
  UNIQUE KEY `uk_vehiculos_usuarioid_placa` (`usuarioid`,`placa`),
  KEY `fk_vehiculos_tipovehiculoid` (`tipovehiculoid`),
  KEY `idx_vehiculos_usuarioid` (`usuarioid`),
  CONSTRAINT `fk_vehiculos_tipovehiculoid` FOREIGN KEY (`tipovehiculoid`) REFERENCES `tiposvehiculo` (`idTiposVehiculo`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Vehículos registrados por colaboradores';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehiculos`
--

LOCK TABLES `vehiculos` WRITE;
/*!40000 ALTER TABLE `vehiculos` DISABLE KEYS */;
INSERT INTO `vehiculos` VALUES (1,'juan.chuc',1,'PO1575JMC','Toyota 2003',1,'2025-12-10 19:07:54','2025-12-10 19:08:38'),(2,'01JSFRXWDXJSARB23SPPQ6EW89',2,'PO226JMC','TOYOTA',1,'2025-12-23 16:02:56','2026-01-13 21:32:47'),(3,'01JSFRXWDXJSARB23SPPQ6EW89',3,'PO456JMC','TOYOTA',1,'2025-12-30 21:22:24','2026-01-13 16:02:09'),(4,'01JSFRXWDXJSARB23SPPQ6EW89',4,'PO560GHJ','HONDA',1,'2026-01-13 15:58:19','2026-04-22 22:22:15'),(5,'01JSFRXWDXJSARB23SPPQ6EW89',3,'CO123QWE','FERRARI',1,'2026-01-13 15:59:58','2026-01-13 21:32:29');
/*!40000 ALTER TABLE `vehiculos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'apoyo_combustibles'
--

--
-- Dumping routines for database 'apoyo_combustibles'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-28  9:14:37
