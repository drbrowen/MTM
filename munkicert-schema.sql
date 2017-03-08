-- MySQL dump 10.14  Distrib 5.5.52-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: munkicert
-- ------------------------------------------------------
-- Server version	5.5.52-MariaDB

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
-- Table structure for table `Certificate`
--

DROP TABLE IF EXISTS `Certificate`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Certificate` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `privatekey` varchar(8192) DEFAULT NULL,
  `signrequest` varchar(8192) DEFAULT NULL,
  `certificate` varchar(8192) DEFAULT NULL,
  `status` enum('V','R','I') NOT NULL DEFAULT 'I',
  `subject` varchar(255) DEFAULT NULL,
  `valid_from` datetime DEFAULT NULL,
  `valid_until` datetime DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `subject` (`subject`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Computer`
--

DROP TABLE IF EXISTS `Computer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Computer` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Repository_ID` int(11) NOT NULL,
  `Certificate_ID` int(11) DEFAULT NULL,
  `name` varchar(45) NOT NULL,
  `identifier` varchar(45) NOT NULL,
  `status` enum('ungenerated','issued','reopened','revoked','expired') NOT NULL DEFAULT 'ungenerated',
  `forced_clientidentifier` varchar(254) DEFAULT NULL,
  `rename_on_install` tinyint(1) DEFAULT '0',
  `window_start_date` datetime DEFAULT NULL,
  `window_close_date` datetime DEFAULT NULL,
  `description` varchar(4096) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `identifier_UNIQUE` (`identifier`),
  UNIQUE KEY `Certificate_ID_UNIQUE` (`Certificate_ID`),
  CONSTRAINT `fk_Computer_Certificate_ID1` FOREIGN KEY (`Certificate_ID`) REFERENCES `Certificate` (`ID`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf16;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `Repository`
--

DROP TABLE IF EXISTS `Repository`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `Repository` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(45) NOT NULL,
  `fullpath` varchar(1024) NOT NULL,
  `fileprefix` varchar(128) DEFAULT NULL,
  `description` varchar(4096) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `reponame` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf16;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ShibGroup`
--

DROP TABLE IF EXISTS `ShibGroup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ShibGroup` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `ad_path` varchar(512) NOT NULL,
  `shib_path` varchar(512) DEFAULT NULL,
  `description` varchar(4096) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `idx_ad_path` (`ad_path`(191)),
  KEY `idx_shib_path` (`shib_path`(191))
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf16;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ShibGroup_in_UserGroup`
--

DROP TABLE IF EXISTS `ShibGroup_in_UserGroup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ShibGroup_in_UserGroup` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `ShibGroup_ID` int(11) NOT NULL,
  `UserGroup_ID` int(11) NOT NULL,
  `description` varchar(4096) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `fk_ShibGroup_in_UserGroup_ShibGroup1` (`ShibGroup_ID`),
  KEY `fk_ShibGroup_in_UserGroup_UserGroup1` (`UserGroup_ID`),
  CONSTRAINT `fk_ShibGroup_in_UserGroup_ShibGroup1` FOREIGN KEY (`ShibGroup_ID`) REFERENCES `ShibGroup` (`ID`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_ShibGroup_in_UserGroup_UserGroup1` FOREIGN KEY (`UserGroup_ID`) REFERENCES `UserGroup` (`ID`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf16;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `User`
--

DROP TABLE IF EXISTS `User`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `User` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(45) NOT NULL,
  `description` varchar(4096) DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf16;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `UserGroup`
--

DROP TABLE IF EXISTS `UserGroup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `UserGroup` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(45) NOT NULL,
  `description` varchar(4096) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `ID_UNIQUE` (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf16;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `UserGroup_has_Repository_Permission`
--

DROP TABLE IF EXISTS `UserGroup_has_Repository_Permission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `UserGroup_has_Repository_Permission` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `UserGroup_ID` int(11) NOT NULL,
  `Repository_ID` int(11) NOT NULL,
  `portal_permission` int(10) unsigned DEFAULT NULL,
  `repository_permission` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `fk_UserGroup_has_Repository_Repository1` (`Repository_ID`),
  KEY `fk_UserGroup_has_UserGroup_UserGroup1` (`UserGroup_ID`),
  CONSTRAINT `fk_UserGroup_has_Repository_Repository1` FOREIGN KEY (`Repository_ID`) REFERENCES `Repository` (`ID`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_UserGroup_has_Repository_UserGroup1` FOREIGN KEY (`UserGroup_ID`) REFERENCES `UserGroup` (`ID`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf16;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `UserGroup_has_UserGroup_Permission`
--

DROP TABLE IF EXISTS `UserGroup_has_UserGroup_Permission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `UserGroup_has_UserGroup_Permission` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Acting_UserGroup_ID` int(11) NOT NULL,
  `Target_UserGroup_ID` int(11) NOT NULL,
  `portal_permission` int(10) unsigned DEFAULT NULL,
  `description` varchar(4096) DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `ID_UNIQUE` (`ID`),
  KEY `fk_UserGroup_has_UserGroup_Permission_Target_UserGroup1` (`Target_UserGroup_ID`),
  KEY `fk_UserGroup_has_UserGroup_Permission_Acting_UserGroup1` (`Acting_UserGroup_ID`),
  CONSTRAINT `fk_UserGroup_has_UserGroup_Permission_Acting_UserGroup1` FOREIGN KEY (`Acting_UserGroup_ID`) REFERENCES `UserGroup` (`ID`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_UserGroup_has_UserGroup_Permission_Target_UserGroup1` FOREIGN KEY (`Target_UserGroup_ID`) REFERENCES `UserGroup` (`ID`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf16;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `UserPermission`
--

DROP TABLE IF EXISTS `UserPermission`;
/*!50001 DROP VIEW IF EXISTS `UserPermission`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE TABLE `UserPermission` (
  `User_ID` tinyint NOT NULL,
  `User_name` tinyint NOT NULL,
  `portal_permission` tinyint NOT NULL,
  `repository_permission` tinyint NOT NULL,
  `Repository_ID` tinyint NOT NULL,
  `Repository_name` tinyint NOT NULL,
  `Repository_fullpath` tinyint NOT NULL
) ENGINE=MyISAM */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `User_has_UserGroup_Permission`
--

DROP TABLE IF EXISTS `User_has_UserGroup_Permission`;
/*!50001 DROP VIEW IF EXISTS `User_has_UserGroup_Permission`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE TABLE `User_has_UserGroup_Permission` (
  `User_ID` tinyint NOT NULL,
  `User_name` tinyint NOT NULL,
  `portal_permission` tinyint NOT NULL,
  `Acting_UserGroup_ID` tinyint NOT NULL,
  `Target_UserGroup_ID` tinyint NOT NULL,
  `Acting_UserGroup_Name` tinyint NOT NULL,
  `Target_UserGroup_Name` tinyint NOT NULL
) ENGINE=MyISAM */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `User_in_UserGroup`
--

DROP TABLE IF EXISTS `User_in_UserGroup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `User_in_UserGroup` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `User_ID` int(11) NOT NULL,
  `UserGroup_ID` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `ID_UNIQUE` (`ID`),
  KEY `fk_Owner_has_UserGroup_UserGroup1` (`UserGroup_ID`),
  KEY `fk_Owner_has_UserGroup_Owner1` (`User_ID`),
  CONSTRAINT `fk_Owner_has_UserGroup_Owner1` FOREIGN KEY (`User_ID`) REFERENCES `User` (`ID`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_Owner_has_UserGroup_UserGroup1` FOREIGN KEY (`UserGroup_ID`) REFERENCES `UserGroup` (`ID`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf16;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Final view structure for view `UserPermission`
--

/*!50001 DROP TABLE IF EXISTS `UserPermission`*/;
/*!50001 DROP VIEW IF EXISTS `UserPermission`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8 */;
/*!50001 SET character_set_results     = utf8 */;
/*!50001 SET collation_connection      = utf8_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`munkiuser`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `UserPermission` AS select `u`.`ID` AS `User_ID`,`u`.`name` AS `User_name`,`ughrp`.`portal_permission` AS `portal_permission`,`ughrp`.`repository_permission` AS `repository_permission`,`r`.`ID` AS `Repository_ID`,`r`.`name` AS `Repository_name`,`r`.`fullpath` AS `Repository_fullpath` from ((((`User` `u` join `User_in_UserGroup` `uiug` on((`u`.`ID` = `uiug`.`User_ID`))) join `UserGroup` `ug` on((`uiug`.`UserGroup_ID` = `ug`.`ID`))) join `UserGroup_has_Repository_Permission` `ughrp` on((`ughrp`.`UserGroup_ID` = `ug`.`ID`))) join `Repository` `r` on((`ughrp`.`Repository_ID` = `r`.`ID`))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `User_has_UserGroup_Permission`
--

/*!50001 DROP TABLE IF EXISTS `User_has_UserGroup_Permission`*/;
/*!50001 DROP VIEW IF EXISTS `User_has_UserGroup_Permission`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8 */;
/*!50001 SET character_set_results     = utf8 */;
/*!50001 SET collation_connection      = utf8_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `User_has_UserGroup_Permission` AS select `User`.`ID` AS `User_ID`,`User`.`name` AS `User_name`,`UserGroup_has_UserGroup_Permission`.`portal_permission` AS `portal_permission`,`UserGroup_has_UserGroup_Permission`.`Acting_UserGroup_ID` AS `Acting_UserGroup_ID`,`UserGroup_has_UserGroup_Permission`.`Target_UserGroup_ID` AS `Target_UserGroup_ID`,`AUG`.`name` AS `Acting_UserGroup_Name`,`TUG`.`name` AS `Target_UserGroup_Name` from ((((`User` join `User_in_UserGroup` on((`User`.`ID` = `User_in_UserGroup`.`User_ID`))) join `UserGroup` `AUG` on((`User_in_UserGroup`.`UserGroup_ID` = `AUG`.`ID`))) join `UserGroup_has_UserGroup_Permission` on((`AUG`.`ID` = `UserGroup_has_UserGroup_Permission`.`Acting_UserGroup_ID`))) join `UserGroup` `TUG` on((`UserGroup_has_UserGroup_Permission`.`Target_UserGroup_ID` = `TUG`.`ID`))) */;
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

-- Dump completed on 2017-03-07 20:48:17
