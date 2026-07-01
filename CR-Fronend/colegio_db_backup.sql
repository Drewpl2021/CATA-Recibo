-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: colegio_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `colegio_db`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `colegio_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `colegio_db`;

--
-- Table structure for table `areas`
--

DROP TABLE IF EXISTS `areas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `areas` (
  `id` char(36) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `areas`
--

LOCK TABLES `areas` WRITE;
/*!40000 ALTER TABLE `areas` DISABLE KEYS */;
INSERT INTO `areas` VALUES ('286c0b6a-2b51-4fe7-9496-88982cd3cca5','Arte',NULL,'2026-06-12 05:50:56','2026-06-12 05:50:56'),('47f8b2ff-0dae-4fcb-bf0c-659325c24ea9','Religión',NULL,'2026-06-12 05:50:56','2026-06-12 05:50:56'),('53623be5-211c-44ff-b141-2f52d89ff5f8','Comunicación',NULL,'2026-06-12 05:50:56','2026-06-12 05:50:56'),('9ff60862-b173-46b8-b099-513dafeefd88','Educación Física',NULL,'2026-06-12 05:50:56','2026-06-12 05:50:56'),('a3a2f969-16b2-4db7-9fee-290791dee466','Inglés',NULL,'2026-06-12 05:50:56','2026-06-12 05:50:56'),('cbd5c959-b721-4572-974a-8dfa85b7ab44','Matemáticas',NULL,'2026-06-12 05:50:56','2026-06-12 05:50:56'),('e4533713-9ed8-484a-a3e7-1fae71cc6641','Historia',NULL,'2026-06-12 05:50:56','2026-06-12 05:50:56'),('ed91d3f3-1c13-4802-a6e2-d8c50f1baf72','Ciencias',NULL,'2026-06-12 05:50:56','2026-06-12 05:50:56');
/*!40000 ALTER TABLE `areas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cargos`
--

DROP TABLE IF EXISTS `cargos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cargos` (
  `id` char(36) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cargos`
--

LOCK TABLES `cargos` WRITE;
/*!40000 ALTER TABLE `cargos` DISABLE KEYS */;
INSERT INTO `cargos` VALUES ('1e56a07f-d2b6-47ba-879c-851eac9692c3','Subdirector',NULL,'2026-06-12 05:50:56','2026-06-12 05:50:56'),('341b8ef5-2ef7-4978-88a3-f9692d8948c6','Secretaria',NULL,'2026-06-12 05:50:56','2026-06-12 05:50:56'),('3c0b2323-a66e-4b48-a0bf-1e0a5a06b451','Auxiliar',NULL,'2026-06-12 05:50:56','2026-06-12 05:50:56'),('4a112e82-8794-4395-8fdb-268ffcfd1633','Administrativo',NULL,'2026-06-12 05:50:56','2026-06-12 05:50:56'),('5a19a648-0837-487f-b6d5-1485b436f2eb','Docente',NULL,'2026-06-12 05:50:56','2026-06-12 05:50:56'),('c9302889-a4b2-4690-8f4b-e9c9115aaa34','Director',NULL,'2026-06-12 05:50:56','2026-06-12 05:50:56'),('d6d93541-5bb0-474d-aa48-6aab4957a08f','Psicólogo',NULL,'2026-06-12 05:50:56','2026-06-12 05:50:56');
/*!40000 ALTER TABLE `cargos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `descuentos`
--

DROP TABLE IF EXISTS `descuentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `descuentos` (
  `id` char(36) NOT NULL,
  `empleado_id` char(36) NOT NULL,
  `tipo` varchar(100) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `mes` int(11) NOT NULL,
  `anio` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `estado_registro` varchar(255) NOT NULL DEFAULT 'activo',
  PRIMARY KEY (`id`),
  KEY `descuentos_empleado_id_foreign` (`empleado_id`),
  CONSTRAINT `descuentos_empleado_id_foreign` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `descuentos`
--

LOCK TABLES `descuentos` WRITE;
/*!40000 ALTER TABLE `descuentos` DISABLE KEYS */;
/*!40000 ALTER TABLE `descuentos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documentos`
--

DROP TABLE IF EXISTS `documentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documentos` (
  `id` char(36) NOT NULL,
  `empleado_id` char(36) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `archivo` varchar(255) NOT NULL,
  `firmado_por` varchar(100) DEFAULT NULL,
  `codigo_firma` varchar(100) DEFAULT NULL,
  `fecha_firma` timestamp NULL DEFAULT NULL,
  `estado_firma` enum('pendiente','visto','firmado') NOT NULL DEFAULT 'pendiente',
  `planilla_id` char(36) DEFAULT NULL,
  `fecha_visto` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `estado_registro` varchar(255) NOT NULL DEFAULT 'activo',
  PRIMARY KEY (`id`),
  KEY `documentos_empleado_id_foreign` (`empleado_id`),
  KEY `documentos_planilla_id_foreign` (`planilla_id`),
  CONSTRAINT `documentos_empleado_id_foreign` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `documentos_planilla_id_foreign` FOREIGN KEY (`planilla_id`) REFERENCES `planilla` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documentos`
--

LOCK TABLES `documentos` WRITE;
/*!40000 ALTER TABLE `documentos` DISABLE KEYS */;
INSERT INTO `documentos` VALUES ('16f0294f-5d31-48f8-9f09-b6bd2579d355','9260f4bc-604b-4bd0-830f-c5ad103cb3e8','boleta','boleta_12345678_12_2026.pdf',NULL,NULL,NULL,'pendiente','a08a5b5a-10de-4eed-8151-26f3e96058b8',NULL,'2026-06-16 08:39:44','2026-06-16 08:39:44','activo'),('179552b0-6aab-4ee3-8814-d62fd75eb236','9260f4bc-604b-4bd0-830f-c5ad103cb3e8','boleta','boleta_12345678_9_2026.pdf','Test Usuario','TGK4IBGS-1781581117','2026-06-16 08:38:37','firmado','0b0623b5-729f-44fc-8732-b671cadb5be7',NULL,'2026-06-16 08:35:44','2026-06-16 08:38:37','activo'),('1bfa0a02-5959-4666-ab78-da7eb8b7f2bf','a27deca9-b5b1-4e3d-a603-e8e98a2e990a','boleta','boleta_60065632_7_2026.pdf',NULL,NULL,NULL,'pendiente','adce7f54-6975-4288-90d6-bc93ca756948',NULL,'2026-06-30 08:33:36','2026-06-30 08:33:36','activo'),('1cc34350-8483-43f6-a9bb-f737aef95665','aff7e515-32fd-4d35-a885-8d074a41b490','boleta','boleta_81577364_7_2026.pdf','Administrador Apellidos','RUEU0CET-1782698251','2026-06-29 06:57:31','firmado','00382a52-22f0-49e1-90a4-5c2b1b8113f8',NULL,'2026-06-29 06:44:42','2026-06-29 06:57:31','activo'),('45433968-0825-4a95-a3a8-5b4611791ecc','9260f4bc-604b-4bd0-830f-c5ad103cb3e8','boleta','boleta_12345678_6_2026.pdf','Test Usuario','N3OPLF3I-1781580037','2026-06-16 08:20:37','firmado','1c3134c0-c522-4608-8fef-59c62b37e81d',NULL,'2026-06-16 08:19:41','2026-06-16 08:20:37','activo'),('bc86bff3-e970-4e3d-937b-1d689a0e3cc1','9260f4bc-604b-4bd0-830f-c5ad103cb3e8','boleta','boleta_12345678_4_2026.pdf','Test Usuario','W9BYAO3E-1781580887','2026-06-16 08:34:47','firmado','619201a6-429f-4bc1-8606-7569fb3e9a28',NULL,'2026-06-16 08:34:01','2026-06-16 08:34:47','activo'),('c1265800-b3c8-44a6-a09b-59a90216dc60','9260f4bc-604b-4bd0-830f-c5ad103cb3e8','boleta','boleta_12345678_10_2026.pdf',NULL,NULL,NULL,'pendiente','1491aa49-e9dd-49ad-a24a-4d2c2328a624',NULL,'2026-06-16 08:38:53','2026-06-16 08:38:53','activo'),('d9c0a773-9012-4838-85ac-654b7a4985c0','9260f4bc-604b-4bd0-830f-c5ad103cb3e8','boleta','boleta_12345678_7_2026.pdf','Test Usuario','AVAVY9J8-1782691840','2026-06-29 05:10:40','firmado','87613f4c-f491-489c-8431-d8b7304a9cfa',NULL,'2026-06-29 05:09:53','2026-06-29 05:10:40','activo'),('dd530d5c-5de2-4bf8-a2cc-dae1d4a45c6c','9260f4bc-604b-4bd0-830f-c5ad103cb3e8','boleta','boleta_12345678_4_2025.pdf',NULL,NULL,NULL,'pendiente','93374779-b768-4fa1-84d1-9c238a343cb5',NULL,'2026-06-24 08:35:36','2026-06-24 08:35:36','activo'),('e6cf1a0d-6e2c-48a5-9563-63dc432239f9','9260f4bc-604b-4bd0-830f-c5ad103cb3e8','boleta','boleta_12345678_3_2026.pdf','Test Usuario','SZ5LPKVQ-1782692537','2026-06-29 05:22:17','firmado','ffaa1dcc-0520-47fe-9110-e359af72c2f2',NULL,'2026-06-17 01:12:06','2026-06-29 05:22:17','activo');
/*!40000 ALTER TABLE `documentos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `empleados`
--

DROP TABLE IF EXISTS `empleados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `empleados` (
  `id` char(36) NOT NULL,
  `dni` varchar(8) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `cargo_id` char(36) DEFAULT NULL,
  `area_id` char(36) DEFAULT NULL,
  `telefono` varchar(15) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `fecha_ingreso` date NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'activo',
  `sistema_pensiones` enum('AFP','ONP') NOT NULL DEFAULT 'ONP',
  `afp` enum('Habitat','Integra','Prima','Profuturo') DEFAULT NULL,
  `cuspp` varchar(20) DEFAULT NULL,
  `sueldo_base` decimal(10,2) DEFAULT NULL,
  `tipo_contrato` enum('por_hora','necesidad_servicio','indeterminado') DEFAULT NULL,
  `forma_pago` enum('banco','efectivo','otro') DEFAULT NULL,
  `sede_id` char(36) DEFAULT NULL,
  `entidad_financiera` varchar(100) DEFAULT NULL,
  `numero_cuenta` varchar(50) DEFAULT NULL,
  `tiene_hijos` tinyint(1) NOT NULL DEFAULT 0,
  `firma_imagen` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `empleados_dni_unique` (`dni`),
  KEY `empleados_area_id_foreign` (`area_id`),
  KEY `empleados_cargo_id_foreign` (`cargo_id`),
  KEY `empleados_sede_id_foreign` (`sede_id`),
  CONSTRAINT `empleados_area_id_foreign` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `empleados_cargo_id_foreign` FOREIGN KEY (`cargo_id`) REFERENCES `cargos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `empleados_sede_id_foreign` FOREIGN KEY (`sede_id`) REFERENCES `sedes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `empleados`
--

LOCK TABLES `empleados` WRITE;
/*!40000 ALTER TABLE `empleados` DISABLE KEYS */;
INSERT INTO `empleados` VALUES ('9260f4bc-604b-4bd0-830f-c5ad103cb3e8','12345678','Test','Usuario',NULL,NULL,'999999999','Lima','2024-01-01','activo','ONP',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'firmas/firma_12345678.png','2026-05-15 19:56:32','2026-06-29 05:09:13'),('a27deca9-b5b1-4e3d-a603-e8e98a2e990a','60065632','Deivi','Apaza Lucana','3c0b2323-a66e-4b48-a0bf-1e0a5a06b451','47f8b2ff-0dae-4fcb-bf0c-659325c24ea9','111111111','Jr. Gonzales Prada','2025-05-25','Activo','ONP',NULL,NULL,3000.00,NULL,NULL,'b57ae45b-a27f-48b1-95a0-397c0e653fd4',NULL,NULL,0,NULL,'2026-06-24 07:56:19','2026-06-24 07:56:19'),('aff7e515-32fd-4d35-a885-8d074a41b490','81577364','Administrador','Apellidos',NULL,NULL,NULL,NULL,'2026-06-16','activo','ONP',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,'2026-06-16 07:36:04','2026-06-16 07:36:04'),('ce3a057b-90bc-4da6-b930-2d69703bbc67','81577382','Recursos Humanos','Apellidos',NULL,NULL,NULL,NULL,'2026-06-16','activo','ONP',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,'2026-06-16 07:36:20','2026-06-16 07:36:20');
/*!40000 ALTER TABLE `empleados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` smallint(5) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_05_03_225949_create_empleados_table',1),(5,'2026_05_03_225954_create_vacaciones_table',1),(6,'2026_05_03_225958_create_planilla_table',1),(7,'2026_05_03_230001_create_documentos_table',1),(8,'2026_05_03_230855_create_personal_access_tokens_table',1),(9,'2026_05_06_164507_add_rol_empleado_to_users_table',1),(10,'2026_05_12_014934_create_descuentos_table',1),(11,'2026_05_12_024107_change_firmado_por_in_documentos_table',1),(12,'2026_05_19_220148_create_areas_table',2),(13,'2026_05_19_220311_create_cargos_table',2),(14,'2026_05_19_220340_create_roles_table',2),(15,'2026_05_19_220508_create_periodos_table',2),(16,'2026_05_19_220553_create_payment_concepts_table',2),(17,'2026_05_19_220657_create_payroll_detalles_table',2),(18,'2026_05_27_020135_add_rol_id_to_users_table',2),(19,'2026_05_27_020216_add_area_cargo_id_to_empleados_table',2),(20,'2026_05_27_020344_add_periodo_id_to_planilla_and_vacaciones_table',2),(21,'2026_05_27_020916_add_fk_to_documentos_descuentos_vacaciones_table',2),(22,'2026_06_05_040908_remove_rol_add_fk_empleado_to_users_table',3),(23,'2026_06_08_000001_add_estado_firma_to_documentos_table',3),(24,'2026_06_11_000001_add_pension_fields_to_empleados_table',3),(25,'2026_06_15_000001_drop_old_cargo_area_columns_from_empleados_table',3),(26,'2026_06_19_001539_create_sedes_table',4),(27,'2026_06_19_001559_add_sueldo_contrato_sede_to_empleados_table',4),(28,'2026_06_19_060250_create_modulo_padre_table',4),(29,'2026_06_19_060255_create_modulos_table',4),(30,'2026_06_19_060301_create_rol_modulo_table',4),(31,'2026_06_20_045856_add_estado_registro_to_vacaciones_table',4),(32,'2026_06_20_045902_add_estado_registro_to_descuentos_table',4),(33,'2026_06_20_045907_add_estado_registro_to_documentos_table',4),(34,'2026_06_20_045911_add_estado_registro_to_planilla_table',4),(35,'2026_06_20_045917_add_estado_registro_to_users_table',4),(36,'2026_06_20_045921_add_estado_registro_to_modulo_padre_table',4),(37,'2026_06_20_045926_add_estado_registro_to_modulos_table',4),(38,'2026_06_26_055306_add_firma_imagen_to_empleados_table',5);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `modulo_padre`
--

DROP TABLE IF EXISTS `modulo_padre`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `modulo_padre` (
  `id` char(36) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `icono` varchar(255) DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `estado_registro` varchar(255) NOT NULL DEFAULT 'activo',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `modulo_padre`
--

LOCK TABLES `modulo_padre` WRITE;
/*!40000 ALTER TABLE `modulo_padre` DISABLE KEYS */;
INSERT INTO `modulo_padre` VALUES ('3cf2218e-b5c1-4745-b04d-ce3a7b409789','Configuración','settings',2,'2026-06-24 05:41:57','2026-06-24 05:41:57','activo'),('937666c9-bf28-4297-bc0a-bf99c1624747','Boletas y Finanzas','receipt',1,'2026-06-24 05:41:57','2026-06-24 05:41:57','activo'),('fb165534-dcba-4471-8213-7d4b640d4ccd','Mi Espacio','person',3,'2026-06-24 05:41:57','2026-06-24 05:41:57','activo');
/*!40000 ALTER TABLE `modulo_padre` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `modulos`
--

DROP TABLE IF EXISTS `modulos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `modulos` (
  `id` char(36) NOT NULL,
  `modulo_padre_id` char(36) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `ruta` varchar(255) DEFAULT NULL,
  `icono` varchar(255) DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `estado_registro` varchar(255) NOT NULL DEFAULT 'activo',
  PRIMARY KEY (`id`),
  KEY `modulos_modulo_padre_id_foreign` (`modulo_padre_id`),
  CONSTRAINT `modulos_modulo_padre_id_foreign` FOREIGN KEY (`modulo_padre_id`) REFERENCES `modulo_padre` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `modulos`
--

LOCK TABLES `modulos` WRITE;
/*!40000 ALTER TABLE `modulos` DISABLE KEYS */;
INSERT INTO `modulos` VALUES ('024df9fd-4f81-456e-9df4-1f13af40a733','fb165534-dcba-4471-8213-7d4b640d4ccd','Mis Boletas','/mis-boletas','receipt_long',1,'2026-06-24 05:41:57','2026-06-24 05:41:57','activo'),('08c3da59-be59-4cd2-b71e-b9036d5a2f77','937666c9-bf28-4297-bc0a-bf99c1624747','Boletas','/boletas','description',3,'2026-06-24 05:41:57','2026-06-24 05:41:57','activo'),('0d8741ac-9006-49b4-b4df-2c2ffa0a6294','3cf2218e-b5c1-4745-b04d-ce3a7b409789','Módulos','/modulos','view_module',6,'2026-06-24 05:41:57','2026-06-24 05:41:57','activo'),('3cf413e0-4f1d-47ea-b863-0e0a714e4d16','3cf2218e-b5c1-4745-b04d-ce3a7b409789','Roles','/roles','admin_panel_settings',3,'2026-06-24 05:41:57','2026-06-24 05:41:57','activo'),('474a84bb-1213-482b-9796-6b7b7237bc30','fb165534-dcba-4471-8213-7d4b640d4ccd','Mis Documentos','/mis-documentos','folder_shared',2,'2026-06-24 05:41:57','2026-06-24 05:41:57','activo'),('66bebccf-18fc-4e09-8a34-96393a569f2b','3cf2218e-b5c1-4745-b04d-ce3a7b409789','Periodos','/periodos','date_range',4,'2026-06-24 05:41:57','2026-06-24 05:41:57','activo'),('6ef2e7f1-114e-4bdd-97a8-b41089daf58b','3cf2218e-b5c1-4745-b04d-ce3a7b409789','Cargos','/cargos','badge',2,'2026-06-24 05:41:57','2026-06-24 05:41:57','activo'),('7f21cd09-01d6-4888-9516-3561be60d1da','3cf2218e-b5c1-4745-b04d-ce3a7b409789','Módulos Padre','/modulos-padre','folder_open',7,'2026-06-24 05:41:57','2026-06-24 05:41:57','activo'),('a2a90838-b904-4277-aa13-7891f00d4e09','937666c9-bf28-4297-bc0a-bf99c1624747','Descuentos','/descuentos','remove_circle',4,'2026-06-24 05:41:57','2026-06-24 05:41:57','activo'),('befba05f-125f-465a-85c6-1711885977d5','937666c9-bf28-4297-bc0a-bf99c1624747','Planillas','/planillas','table_chart',2,'2026-06-24 05:41:57','2026-06-24 05:41:57','activo'),('c9223f34-70c2-48cd-842e-f5b1f4f0a2b2','937666c9-bf28-4297-bc0a-bf99c1624747','Documentos','/documentos','folder',5,'2026-06-24 05:41:57','2026-06-24 05:41:57','activo'),('d16c5226-7f7e-4e15-982b-b39c0a52ac0e','3cf2218e-b5c1-4745-b04d-ce3a7b409789','Áreas','/areas','domain',1,'2026-06-24 05:41:57','2026-06-24 05:41:57','activo'),('d56bf542-ee3e-4f1f-a306-b15b31ea201c','3cf2218e-b5c1-4745-b04d-ce3a7b409789','Sedes','/sedes','location_on',5,'2026-06-24 05:41:57','2026-06-24 05:41:57','activo'),('e961daf3-9f7b-4de3-86b8-9d8e8e771a6a','937666c9-bf28-4297-bc0a-bf99c1624747','Empleados','/empleados','people',1,'2026-06-24 05:41:57','2026-06-24 05:41:57','activo');
/*!40000 ALTER TABLE `modulos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_concepts`
--

DROP TABLE IF EXISTS `payment_concepts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_concepts` (
  `id` char(36) NOT NULL,
  `nombre` varchar(45) NOT NULL,
  `tipo` varchar(45) NOT NULL,
  `calculo` varchar(45) DEFAULT NULL,
  `valor` decimal(10,2) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_concepts`
--

LOCK TABLES `payment_concepts` WRITE;
/*!40000 ALTER TABLE `payment_concepts` DISABLE KEYS */;
INSERT INTO `payment_concepts` VALUES ('09118d84-e175-4b93-b349-8cb32c1f5b4e','Descuento seguro','descuento','porcentaje',4.00,NULL,'2026-06-12 05:50:56','2026-06-12 05:50:56'),('7dbd4289-41f3-4f25-a22a-0026aee3ff55','Descuento AFP','descuento','porcentaje',10.00,NULL,'2026-06-12 05:50:56','2026-06-12 05:50:56'),('8fd1eb3d-38cb-4cc6-8708-eaed1309e482','Bonificación por puntualidad','bonificacion','fijo',100.00,NULL,'2026-06-12 05:50:56','2026-06-12 05:50:56'),('b06ab41e-9500-41d8-8fe1-52391ad2a3d9','Descuento por falta','descuento','fijo',50.00,NULL,'2026-06-12 05:50:56','2026-06-12 05:50:56'),('f76dcc12-2da5-4114-a8a6-8bbfb0dab8ec','Descuento por tardanza','descuento','fijo',10.00,NULL,'2026-06-12 05:50:56','2026-06-12 05:50:56');
/*!40000 ALTER TABLE `payment_concepts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_detalles`
--

DROP TABLE IF EXISTS `payroll_detalles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payroll_detalles` (
  `id` char(36) NOT NULL,
  `planilla_id` char(36) NOT NULL,
  `payment_concept_id` char(36) NOT NULL,
  `monto_calculado` decimal(10,2) NOT NULL,
  `estado` varchar(45) NOT NULL DEFAULT 'activo',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payroll_detalles_planilla_id_foreign` (`planilla_id`),
  KEY `payroll_detalles_payment_concept_id_foreign` (`payment_concept_id`),
  CONSTRAINT `payroll_detalles_payment_concept_id_foreign` FOREIGN KEY (`payment_concept_id`) REFERENCES `payment_concepts` (`id`),
  CONSTRAINT `payroll_detalles_planilla_id_foreign` FOREIGN KEY (`planilla_id`) REFERENCES `planilla` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_detalles`
--

LOCK TABLES `payroll_detalles` WRITE;
/*!40000 ALTER TABLE `payroll_detalles` DISABLE KEYS */;
/*!40000 ALTER TABLE `payroll_detalles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `periodos`
--

DROP TABLE IF EXISTS `periodos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `periodos` (
  `id` char(36) NOT NULL,
  `nombre` varchar(45) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `periodos`
--

LOCK TABLES `periodos` WRITE;
/*!40000 ALTER TABLE `periodos` DISABLE KEYS */;
/*!40000 ALTER TABLE `periodos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=97 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
INSERT INTO `personal_access_tokens` VALUES (48,'App\\Models\\User',3,'auth_token','cbf9e4e8ed4efcbb47fca5c67e92c77ba8afe5b949007e468081d2439b61346d','[\"*\"]','2026-06-16 08:39:04',NULL,'2026-06-16 08:39:02','2026-06-16 08:39:04'),(94,'App\\Models\\User',1,'auth_token','5acbdc5f4dbfc8288521836de2d546fb4bd2a254ca64b648da623fbccef0966f','[\"*\"]','2026-06-30 08:32:47',NULL,'2026-06-30 08:32:46','2026-06-30 08:32:47'),(96,'App\\Models\\User',2,'auth_token','e4aa92a9fb7ee2750c08d350b6eee6d55a51b14ac2b6eab7ee3936854a95a011','[\"*\"]','2026-06-30 08:44:45',NULL,'2026-06-30 08:44:23','2026-06-30 08:44:45');
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `planilla`
--

DROP TABLE IF EXISTS `planilla`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `planilla` (
  `id` char(36) NOT NULL,
  `empleado_id` char(36) NOT NULL,
  `mes` int(11) NOT NULL,
  `anio` int(11) NOT NULL,
  `periodo_id` char(36) DEFAULT NULL,
  `sueldo_base` decimal(10,2) NOT NULL,
  `bonificaciones` decimal(10,2) NOT NULL DEFAULT 0.00,
  `descuentos` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `estado_registro` varchar(255) NOT NULL DEFAULT 'activo',
  PRIMARY KEY (`id`),
  KEY `planilla_periodo_id_foreign` (`periodo_id`),
  CONSTRAINT `planilla_periodo_id_foreign` FOREIGN KEY (`periodo_id`) REFERENCES `periodos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `planilla`
--

LOCK TABLES `planilla` WRITE;
/*!40000 ALTER TABLE `planilla` DISABLE KEYS */;
INSERT INTO `planilla` VALUES ('00382a52-22f0-49e1-90a4-5c2b1b8113f8','aff7e515-32fd-4d35-a885-8d074a41b490',7,2026,NULL,0.00,0.00,0.00,0.00,'2026-06-29 06:44:42','2026-06-29 06:44:42','activo'),('0b0623b5-729f-44fc-8732-b671cadb5be7','9260f4bc-604b-4bd0-830f-c5ad103cb3e8',9,2026,NULL,2500.00,240.81,184.26,2556.55,'2026-09-28 20:16:55','2026-09-25 20:16:55','activo'),('1491aa49-e9dd-49ad-a24a-4d2c2328a624','9260f4bc-604b-4bd0-830f-c5ad103cb3e8',10,2026,NULL,2500.00,379.11,150.31,2728.80,'2026-10-27 20:16:55','2026-10-25 20:16:55','activo'),('1956c3ae-cb9f-4db3-9cf7-50890d96dec9','9260f4bc-604b-4bd0-830f-c5ad103cb3e8',2,2026,NULL,2500.00,283.86,64.43,2719.43,'2026-02-28 20:16:55','2026-02-27 20:16:55','activo'),('1c3134c0-c522-4608-8fef-59c62b37e81d','9260f4bc-604b-4bd0-830f-c5ad103cb3e8',6,2026,NULL,2500.00,251.54,154.98,2596.56,'2026-06-28 20:16:55','2026-06-24 08:10:37','activo'),('619201a6-429f-4bc1-8606-7569fb3e9a28','9260f4bc-604b-4bd0-830f-c5ad103cb3e8',4,2026,NULL,2500.00,307.09,60.07,2747.02,'2026-04-25 20:16:55','2026-04-25 20:16:55','activo'),('87613f4c-f491-489c-8431-d8b7304a9cfa','9260f4bc-604b-4bd0-830f-c5ad103cb3e8',7,2026,NULL,2500.00,257.44,129.79,2627.65,'2026-07-27 20:16:55','2026-07-25 20:16:55','activo'),('93374779-b768-4fa1-84d1-9c238a343cb5','9260f4bc-604b-4bd0-830f-c5ad103cb3e8',4,2025,NULL,0.00,0.00,0.00,0.00,'2026-06-24 08:35:36','2026-06-24 08:35:36','activo'),('a08a5b5a-10de-4eed-8151-26f3e96058b8','9260f4bc-604b-4bd0-830f-c5ad103cb3e8',12,2026,NULL,2500.00,187.92,51.39,2636.53,'2026-12-26 20:16:55','2026-12-25 20:16:55','activo'),('adce7f54-6975-4288-90d6-bc93ca756948','a27deca9-b5b1-4e3d-a603-e8e98a2e990a',7,2026,NULL,3000.00,0.00,0.00,3000.00,'2026-06-30 08:33:36','2026-06-30 08:33:36','activo'),('d5c9a89f-a691-4e3e-baae-76b532d7bdcf','9260f4bc-604b-4bd0-830f-c5ad103cb3e8',1,2026,NULL,2500.00,417.66,119.31,2798.35,'2026-01-28 20:16:55','2026-01-26 20:16:55','activo'),('dd87d047-02c5-41df-b696-0d4070bd9cc6','9260f4bc-604b-4bd0-830f-c5ad103cb3e8',5,2026,NULL,2500.00,261.59,56.65,2704.94,'2026-05-25 20:16:55','2026-05-25 20:16:55','activo'),('eb766797-8ad5-4294-86d0-ca0b9f95b8eb','9260f4bc-604b-4bd0-830f-c5ad103cb3e8',11,2026,NULL,2500.00,314.21,71.44,2742.77,'2026-11-27 20:16:55','2026-11-26 20:16:55','activo'),('f7c802f1-b0e9-45b2-b45a-7e07eb7a6f5f','9260f4bc-604b-4bd0-830f-c5ad103cb3e8',8,2026,NULL,2500.00,489.61,200.16,2789.45,'2026-08-27 20:16:55','2026-08-26 20:16:55','activo'),('ffaa1dcc-0520-47fe-9110-e359af72c2f2','9260f4bc-604b-4bd0-830f-c5ad103cb3e8',3,2026,NULL,2500.00,178.94,136.65,2542.29,'2026-03-27 20:16:55','2026-03-26 20:16:55','activo');
/*!40000 ALTER TABLE `planilla` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rol_modulo`
--

DROP TABLE IF EXISTS `rol_modulo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rol_modulo` (
  `rol_id` char(36) NOT NULL,
  `modulo_id` char(36) NOT NULL,
  PRIMARY KEY (`rol_id`,`modulo_id`),
  KEY `rol_modulo_modulo_id_foreign` (`modulo_id`),
  CONSTRAINT `rol_modulo_modulo_id_foreign` FOREIGN KEY (`modulo_id`) REFERENCES `modulos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rol_modulo_rol_id_foreign` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rol_modulo`
--

LOCK TABLES `rol_modulo` WRITE;
/*!40000 ALTER TABLE `rol_modulo` DISABLE KEYS */;
INSERT INTO `rol_modulo` VALUES ('a5a58226-9ee3-4bdf-ba4e-d49eb5f88c26','024df9fd-4f81-456e-9df4-1f13af40a733'),('a5a58226-9ee3-4bdf-ba4e-d49eb5f88c26','474a84bb-1213-482b-9796-6b7b7237bc30'),('f384b998-3f8b-4af2-a71a-144ac28cabd1','024df9fd-4f81-456e-9df4-1f13af40a733'),('f384b998-3f8b-4af2-a71a-144ac28cabd1','08c3da59-be59-4cd2-b71e-b9036d5a2f77'),('f384b998-3f8b-4af2-a71a-144ac28cabd1','0d8741ac-9006-49b4-b4df-2c2ffa0a6294'),('f384b998-3f8b-4af2-a71a-144ac28cabd1','3cf413e0-4f1d-47ea-b863-0e0a714e4d16'),('f384b998-3f8b-4af2-a71a-144ac28cabd1','474a84bb-1213-482b-9796-6b7b7237bc30'),('f384b998-3f8b-4af2-a71a-144ac28cabd1','66bebccf-18fc-4e09-8a34-96393a569f2b'),('f384b998-3f8b-4af2-a71a-144ac28cabd1','6ef2e7f1-114e-4bdd-97a8-b41089daf58b'),('f384b998-3f8b-4af2-a71a-144ac28cabd1','7f21cd09-01d6-4888-9516-3561be60d1da'),('f384b998-3f8b-4af2-a71a-144ac28cabd1','a2a90838-b904-4277-aa13-7891f00d4e09'),('f384b998-3f8b-4af2-a71a-144ac28cabd1','befba05f-125f-465a-85c6-1711885977d5'),('f384b998-3f8b-4af2-a71a-144ac28cabd1','c9223f34-70c2-48cd-842e-f5b1f4f0a2b2'),('f384b998-3f8b-4af2-a71a-144ac28cabd1','d16c5226-7f7e-4e15-982b-b39c0a52ac0e'),('f384b998-3f8b-4af2-a71a-144ac28cabd1','d56bf542-ee3e-4f1f-a306-b15b31ea201c'),('f384b998-3f8b-4af2-a71a-144ac28cabd1','e961daf3-9f7b-4de3-86b8-9d8e8e771a6a'),('f759f2cd-9351-4ed8-b37c-e1957f5e0095','024df9fd-4f81-456e-9df4-1f13af40a733'),('f759f2cd-9351-4ed8-b37c-e1957f5e0095','08c3da59-be59-4cd2-b71e-b9036d5a2f77'),('f759f2cd-9351-4ed8-b37c-e1957f5e0095','474a84bb-1213-482b-9796-6b7b7237bc30'),('f759f2cd-9351-4ed8-b37c-e1957f5e0095','a2a90838-b904-4277-aa13-7891f00d4e09'),('f759f2cd-9351-4ed8-b37c-e1957f5e0095','befba05f-125f-465a-85c6-1711885977d5'),('f759f2cd-9351-4ed8-b37c-e1957f5e0095','c9223f34-70c2-48cd-842e-f5b1f4f0a2b2'),('f759f2cd-9351-4ed8-b37c-e1957f5e0095','e961daf3-9f7b-4de3-86b8-9d8e8e771a6a');
/*!40000 ALTER TABLE `rol_modulo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` char(36) NOT NULL,
  `nombre` varchar(45) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES ('a5a58226-9ee3-4bdf-ba4e-d49eb5f88c26','empleado',NULL,'2026-06-12 05:50:56','2026-06-12 05:50:56'),('f384b998-3f8b-4af2-a71a-144ac28cabd1','admin',NULL,'2026-06-12 05:50:56','2026-06-12 05:50:56'),('f759f2cd-9351-4ed8-b37c-e1957f5e0095','rrhh',NULL,'2026-06-12 05:50:56','2026-06-12 05:50:56');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sedes`
--

DROP TABLE IF EXISTS `sedes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sedes` (
  `id` char(36) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(15) DEFAULT NULL,
  `estado` varchar(255) NOT NULL DEFAULT 'activo',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sedes`
--

LOCK TABLES `sedes` WRITE;
/*!40000 ALTER TABLE `sedes` DISABLE KEYS */;
INSERT INTO `sedes` VALUES ('a6433483-3411-4044-b3f9-b50bff919424','Jerusalen','Sede Anexa',NULL,'activo','2026-06-24 06:24:09','2026-06-24 06:24:09'),('b57ae45b-a27f-48b1-95a0-397c0e653fd4','CATA','Sede Principal',NULL,'activo','2026-06-24 06:24:09','2026-06-24 06:24:09');
/*!40000 ALTER TABLE `sedes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('I5pGFrylEQI5ZsbgwRtjXp1azb7PeKyGFM4mezwW',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJzOXcxMlhlS2ZMcUZIMVFEY05FUm5KWmlMcFJUNU1neWgzY2NMekY3IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwIiwicm91dGUiOm51bGx9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1779328443),('Jh5LAe902dWraisoWqbKKubAZSQH7IybahkSgg6v',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJkMkpEQkV6NzMxNklEUlk4Nmg4aFcwSDBaaGR0Tmt6RU5DMHExeHFxIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwIiwicm91dGUiOm51bGx9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1782790222),('pE09nJb5DcZhTenxeWEtyRfk0LtPbWX2JQGO4nFk',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJZQWVJUVgwWlJyNWlmTVV0a3pPTEI0NnY4UWJMRTJJZTJYYm5JRkwzIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwIiwicm91dGUiOm51bGx9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1781225081),('rPnMCCML4nstmRE4LOMxCuHI3Hu6eaOLiHUZ9uC2',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJNeVJxRWpsWk1tTXRGRVIzWnZuak5OOWI1UHlRMXdZaGJhRzBOY1IxIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwIiwicm91dGUiOm51bGx9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1782261272),('vl7r5QgMnLMhLnQB49fG8e9H0DqHEfzgYEArLUOw',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJyYTVhcUFyY3F5dkJBVEozNjNJdkkxOHQ3N1E0V21EY0cyMVpxdFUzIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwIiwicm91dGUiOm51bGx9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1782690636),('Y0ieZFN9Cc87RHJwDSQXtyZFNQTGrfgS3D78tCOu',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJhOUIzMWlsSGptWlNZYlJGSzZPQ2Y5OTZhSDZaM3FreWg3UUpoem5BIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwIiwicm91dGUiOm51bGx9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1781571371),('ZuydljHrpgKx8USe82tXZbeTmeGVkGVj6YoPoIM1',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT; Windows NT 10.0; es-PE) WindowsPowerShell/5.1.26100.8737','eyJfdG9rZW4iOiJ3NXdBMzdmdXk0bmROUGd2Rm1SUVZZSjJ6cTRmM0libjRycTZrN2t4IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwIiwicm91dGUiOm51bGx9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19',1782690547);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `rol_id` char(36) DEFAULT NULL,
  `empleado_id` char(36) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `estado_registro` varchar(255) NOT NULL DEFAULT 'activo',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_rol_id_foreign` (`rol_id`),
  KEY `users_empleado_id_foreign` (`empleado_id`),
  CONSTRAINT `users_empleado_id_foreign` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_rol_id_foreign` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Test Usuario','test@colegio.com',NULL,'$2y$12$5fufpVvzo5M0nFwFj38GF.TqvGOIl.vE7LnoTbpavBggWzRBnaS82',NULL,'9260f4bc-604b-4bd0-830f-c5ad103cb3e8',NULL,'2026-05-15 19:56:32','2026-06-12 05:51:20','activo'),(2,'Administrador','admin@colegio.com',NULL,'$2y$12$MvfH3UTTvisJm3erz6r/Pu77s62ox7Mhtd4u.F9CXo4gmqm3azzd6','f384b998-3f8b-4af2-a71a-144ac28cabd1','aff7e515-32fd-4d35-a885-8d074a41b490',NULL,'2026-06-12 05:51:07','2026-06-16 07:36:04','activo'),(3,'Recursos Humanos','rrhh@colegio.com',NULL,'$2y$12$nJxXLW8WZU8Jwl1odVWk/eCCd02Bx1XWjlyBQ8F1Ux4lXPLzp9XIG','f759f2cd-9351-4ed8-b37c-e1957f5e0095','ce3a057b-90bc-4da6-b930-2d69703bbc67',NULL,'2026-06-12 05:51:07','2026-06-16 07:36:20','activo');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vacaciones`
--

DROP TABLE IF EXISTS `vacaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vacaciones` (
  `id` char(36) NOT NULL,
  `empleado_id` char(36) NOT NULL,
  `periodo_id` char(36) DEFAULT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `dias_solicitados` int(11) NOT NULL,
  `motivo` text DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'pendiente',
  `aprobado_por` char(36) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `estado_registro` varchar(255) NOT NULL DEFAULT 'activo',
  PRIMARY KEY (`id`),
  KEY `vacaciones_periodo_id_foreign` (`periodo_id`),
  KEY `vacaciones_empleado_id_foreign` (`empleado_id`),
  CONSTRAINT `vacaciones_empleado_id_foreign` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vacaciones_periodo_id_foreign` FOREIGN KEY (`periodo_id`) REFERENCES `periodos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vacaciones`
--

LOCK TABLES `vacaciones` WRITE;
/*!40000 ALTER TABLE `vacaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `vacaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'colegio_db'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-29 22:50:08
