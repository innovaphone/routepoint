-- MySQL dump 10.13  Distrib 5.5.10, for Win64 (x86)
--
-- Host: 172.16.0.30    Database: routepoint
-- ------------------------------------------------------
-- Server version	5.5.10

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
-- Current Database: `routepoint`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `routepoint` /*!40100 DEFAULT CHARACTER SET utf8 */;

USE `routepoint`;

--
-- Table structure for table `call`
--

DROP TABLE IF EXISTS `call`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `call` (
  `confid` char(36) NOT NULL COMMENT '''conference guid of call 2 dispatch''',
  `cmd` varchar(1024) DEFAULT NULL COMMENT '''last xml cmd sent to routepoint''',
  `done` int(1) DEFAULT '0' COMMENT 'true if cmd was received by routepoint',
  `cgpn` varchar(45) DEFAULT NULL COMMENT '''calling line id of original caller''',
  `cdpn` varchar(45) DEFAULT NULL COMMENT '''called line id''',
  `leg2` varchar(45) DEFAULT NULL COMMENT '''leg-info''',
  `agent_e164` varchar(45) DEFAULT NULL COMMENT '''last tried agent''',
  `cause` int(11) DEFAULT NULL COMMENT 'last cause code received from attempted transfer',
  `state` varchar(45) DEFAULT NULL COMMENT '''internal state''',
  `dtmf` tinytext COMMENT 'last dtmf seen from call',
  `cumulated_dtmf` varchar(45) DEFAULT '' COMMENT 'cumulated dtmf from call',
  `event` varchar(45) DEFAULT NULL COMMENT 'last event seen from call',
  `h323` varchar(45) DEFAULT NULL COMMENT 'calling h323-id',
  `calling_name` varchar(45) DEFAULT NULL COMMENT 'calling name',
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`confid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='table of calls to dispatch';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `call`
--

LOCK TABLES `call` WRITE;
/*!40000 ALTER TABLE `call` DISABLE KEYS */;
/*!40000 ALTER TABLE `call` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `log`
--

DROP TABLE IF EXISTS `log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `log` (
  `confid` char(36) NOT NULL,
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `query` varchar(512) DEFAULT NULL,
  `response` varchar(512) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `confid_idx` (`confid`),
  KEY `sorted` (`confid`,`timestamp`,`id`),
  CONSTRAINT `confid` FOREIGN KEY (`confid`) REFERENCES `call` (`confid`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `log`
--

LOCK TABLES `log` WRITE;
/*!40000 ALTER TABLE `log` DISABLE KEYS */;
/*!40000 ALTER TABLE `log` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2013-11-12 22:01:19
