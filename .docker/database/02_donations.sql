-- MariaDB dump 10.19  Distrib 10.5.10-MariaDB, for Linux (x86_64)
--
-- Host: 172.16.89.70    Database: spenden
-- ------------------------------------------------------
-- Server version	10.4.13-MariaDB-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `spenden`
--

DROP TABLE IF EXISTS `spenden`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `spenden` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `name` varchar(250) DEFAULT NULL,
  `ort` varchar(250) DEFAULT NULL,
  `email` varchar(250) DEFAULT NULL,
  `info` tinyint(4) NOT NULL DEFAULT 0,
  `bescheinigung` tinyint(4) DEFAULT NULL,
  `eintrag` varchar(250) NOT NULL DEFAULT '',
  `betrag` varchar(250) DEFAULT NULL,
  `periode` smallint(6) NOT NULL DEFAULT 0,
  `zahlweise` char(3) NOT NULL DEFAULT 'BEZ',
  `kommentar` text NOT NULL,
  `ueb_code` varchar(32) NOT NULL DEFAULT '',
  `data` text DEFAULT NULL,
  `source` varchar(250) DEFAULT NULL,
  `remote_addr` varchar(250) NOT NULL DEFAULT '',
  `hash` varchar(250) DEFAULT NULL,
  `is_public` tinyint(4) NOT NULL DEFAULT 0,
  `dt_new` datetime NOT NULL,
  `dt_del` datetime DEFAULT NULL,
  `dt_exp` datetime DEFAULT NULL,
  `status` char(1) NOT NULL DEFAULT 'N',
  `dt_gruen` datetime DEFAULT NULL,
  `dt_backup` datetime DEFAULT NULL,
  `payment_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_3CBBD0454C3A3BB` (`payment_id`),
  KEY `d_dt_new` (`dt_new`,`is_public`),
  KEY `d_status` (`status`,`dt_new`),
  KEY `d_dt_gruen` (`dt_gruen`,`dt_del`),
  KEY `d_ueb_code` (`ueb_code`),
  KEY `d_dt_backup` (`dt_backup`),
  KEY `d_comment_list` (`is_public`,`dt_del`),
  KEY `d_zahlweise` (`zahlweise`,`dt_new`),
  FULLTEXT KEY `d_name` (`name`),
  FULLTEXT KEY `d_email` (`email`),
  FULLTEXT KEY `d_ort` (`ort`),
  CONSTRAINT `FK_3CBBD0454C3A3BB` FOREIGN KEY (`payment_id`) REFERENCES `donation_payment` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4233996 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `spenden`
--

