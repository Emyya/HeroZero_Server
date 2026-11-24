-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: herozero
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
-- Current Database: `herozero`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `herozero` /*!40100 DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci */;

USE `herozero`;

--
-- Table structure for table `bank_inventory`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `character_id` int(11) NOT NULL,
  `max_bank_index` int(11) NOT NULL,
  `bank_item1_id` int(11) NOT NULL DEFAULT 0,
  `bank_item2_id` int(11) NOT NULL DEFAULT 0,
  `bank_item3_id` int(11) NOT NULL DEFAULT 0,
  `bank_item4_id` int(11) NOT NULL DEFAULT 0,
  `bank_item5_id` int(11) NOT NULL DEFAULT 0,
  `bank_item6_id` int(11) NOT NULL DEFAULT 0,
  `bank_item7_id` int(11) NOT NULL DEFAULT 0,
  `bank_item8_id` int(11) NOT NULL DEFAULT 0,
  `bank_item9_id` int(11) NOT NULL DEFAULT 0,
  `bank_item10_id` int(11) NOT NULL DEFAULT 0,
  `bank_item11_id` int(11) NOT NULL DEFAULT 0,
  `bank_item12_id` int(11) NOT NULL DEFAULT 0,
  `bank_item13_id` int(11) NOT NULL DEFAULT 0,
  `bank_item14_id` int(11) NOT NULL DEFAULT 0,
  `bank_item15_id` int(11) NOT NULL DEFAULT 0,
  `bank_item16_id` int(11) NOT NULL DEFAULT 0,
  `bank_item17_id` int(11) NOT NULL DEFAULT 0,
  `bank_item18_id` int(11) NOT NULL DEFAULT 0,
  `bank_item19_id` int(11) NOT NULL DEFAULT 0,
  `bank_item20_id` int(11) NOT NULL DEFAULT 0,
  `bank_item21_id` int(11) NOT NULL DEFAULT 0,
  `bank_item22_id` int(11) NOT NULL DEFAULT 0,
  `bank_item23_id` int(11) NOT NULL DEFAULT 0,
  `bank_item24_id` int(11) NOT NULL DEFAULT 0,
  `bank_item25_id` int(11) NOT NULL DEFAULT 0,
  `bank_item26_id` int(11) NOT NULL DEFAULT 0,
  `bank_item27_id` int(11) NOT NULL DEFAULT 0,
  `bank_item28_id` int(11) NOT NULL DEFAULT 0,
  `bank_item29_id` int(11) NOT NULL DEFAULT 0,
  `bank_item30_id` int(11) NOT NULL DEFAULT 0,
  `bank_item31_id` int(11) NOT NULL DEFAULT 0,
  `bank_item32_id` int(11) NOT NULL DEFAULT 0,
  `bank_item33_id` int(11) NOT NULL DEFAULT 0,
  `bank_item34_id` int(11) NOT NULL DEFAULT 0,
  `bank_item35_id` int(11) NOT NULL DEFAULT 0,
  `bank_item36_id` int(11) NOT NULL DEFAULT 0,
  `bank_item37_id` int(11) NOT NULL DEFAULT 0,
  `bank_item38_id` int(11) NOT NULL DEFAULT 0,
  `bank_item39_id` int(11) NOT NULL DEFAULT 0,
  `bank_item40_id` int(11) NOT NULL DEFAULT 0,
  `bank_item41_id` int(11) NOT NULL DEFAULT 0,
  `bank_item42_id` int(11) NOT NULL DEFAULT 0,
  `bank_item43_id` int(11) NOT NULL DEFAULT 0,
  `bank_item44_id` int(11) NOT NULL DEFAULT 0,
  `bank_item45_id` int(11) NOT NULL DEFAULT 0,
  `bank_item46_id` int(11) NOT NULL DEFAULT 0,
  `bank_item47_id` int(11) NOT NULL DEFAULT 0,
  `bank_item48_id` int(11) NOT NULL DEFAULT 0,
  `bank_item49_id` int(11) NOT NULL DEFAULT 0,
  `bank_item50_id` int(11) NOT NULL DEFAULT 0,
  `bank_item51_id` int(11) NOT NULL DEFAULT 0,
  `bank_item52_id` int(11) NOT NULL DEFAULT 0,
  `bank_item53_id` int(11) NOT NULL DEFAULT 0,
  `bank_item54_id` int(11) NOT NULL DEFAULT 0,
  `bank_item55_id` int(11) NOT NULL DEFAULT 0,
  `bank_item56_id` int(11) NOT NULL DEFAULT 0,
  `bank_item57_id` int(11) NOT NULL DEFAULT 0,
  `bank_item58_id` int(11) NOT NULL DEFAULT 0,
  `bank_item59_id` int(11) NOT NULL DEFAULT 0,
  `bank_item60_id` int(11) NOT NULL DEFAULT 0,
  `bank_item61_id` int(11) NOT NULL DEFAULT 0,
  `bank_item62_id` int(11) NOT NULL DEFAULT 0,
  `bank_item63_id` int(11) NOT NULL DEFAULT 0,
  `bank_item64_id` int(11) NOT NULL DEFAULT 0,
  `bank_item65_id` int(11) NOT NULL DEFAULT 0,
  `bank_item66_id` int(11) NOT NULL DEFAULT 0,
  `bank_item67_id` int(11) NOT NULL DEFAULT 0,
  `bank_item68_id` int(11) NOT NULL DEFAULT 0,
  `bank_item69_id` int(11) NOT NULL DEFAULT 0,
  `bank_item70_id` int(11) NOT NULL DEFAULT 0,
  `bank_item71_id` int(11) NOT NULL DEFAULT 0,
  `bank_item72_id` int(11) NOT NULL DEFAULT 0,
  `bank_item73_id` int(11) NOT NULL DEFAULT 0,
  `bank_item74_id` int(11) NOT NULL DEFAULT 0,
  `bank_item75_id` int(11) NOT NULL DEFAULT 0,
  `bank_item76_id` int(11) NOT NULL DEFAULT 0,
  `bank_item77_id` int(11) NOT NULL DEFAULT 0,
  `bank_item78_id` int(11) NOT NULL DEFAULT 0,
  `bank_item79_id` int(11) NOT NULL DEFAULT 0,
  `bank_item80_id` int(11) NOT NULL DEFAULT 0,
  `bank_item81_id` int(11) NOT NULL DEFAULT 0,
  `bank_item82_id` int(11) NOT NULL DEFAULT 0,
  `bank_item83_id` int(11) NOT NULL DEFAULT 0,
  `bank_item84_id` int(11) NOT NULL DEFAULT 0,
  `bank_item85_id` int(11) NOT NULL DEFAULT 0,
  `bank_item86_id` int(11) NOT NULL DEFAULT 0,
  `bank_item87_id` int(11) NOT NULL DEFAULT 0,
  `bank_item88_id` int(11) NOT NULL DEFAULT 0,
  `bank_item89_id` int(11) NOT NULL DEFAULT 0,
  `bank_item90_id` int(11) NOT NULL DEFAULT 0,
  `bank_item91_id` int(11) NOT NULL DEFAULT 0,
  `bank_item92_id` int(11) NOT NULL DEFAULT 0,
  `bank_item93_id` int(11) NOT NULL DEFAULT 0,
  `bank_item94_id` int(11) NOT NULL DEFAULT 0,
  `bank_item95_id` int(11) NOT NULL DEFAULT 0,
  `bank_item96_id` int(11) NOT NULL DEFAULT 0,
  `bank_item97_id` int(11) NOT NULL DEFAULT 0,
  `bank_item98_id` int(11) NOT NULL DEFAULT 0,
  `bank_item99_id` int(11) NOT NULL DEFAULT 0,
  `bank_item100_id` int(11) NOT NULL DEFAULT 0,
  `bank_item101_id` int(11) NOT NULL DEFAULT 0,
  `bank_item102_id` int(11) NOT NULL DEFAULT 0,
  `bank_item103_id` int(11) NOT NULL DEFAULT 0,
  `bank_item104_id` int(11) NOT NULL DEFAULT 0,
  `bank_item105_id` int(11) NOT NULL DEFAULT 0,
  `bank_item106_id` int(11) NOT NULL DEFAULT 0,
  `bank_item107_id` int(11) NOT NULL DEFAULT 0,
  `bank_item108_id` int(11) NOT NULL DEFAULT 0,
  `bank_item109_id` int(11) NOT NULL DEFAULT 0,
  `bank_item110_id` int(11) NOT NULL DEFAULT 0,
  `bank_item111_id` int(11) NOT NULL DEFAULT 0,
  `bank_item112_id` int(11) NOT NULL DEFAULT 0,
  `bank_item113_id` int(11) NOT NULL DEFAULT 0,
  `bank_item114_id` int(11) NOT NULL DEFAULT 0,
  `bank_item115_id` int(11) NOT NULL DEFAULT 0,
  `bank_item116_id` int(11) NOT NULL DEFAULT 0,
  `bank_item117_id` int(11) NOT NULL DEFAULT 0,
  `bank_item118_id` int(11) NOT NULL DEFAULT 0,
  `bank_item119_id` int(11) NOT NULL DEFAULT 0,
  `bank_item120_id` int(11) NOT NULL DEFAULT 0,
  `bank_item121_id` int(11) NOT NULL DEFAULT 0,
  `bank_item122_id` int(11) NOT NULL DEFAULT 0,
  `bank_item123_id` int(11) NOT NULL DEFAULT 0,
  `bank_item124_id` int(11) NOT NULL DEFAULT 0,
  `bank_item125_id` int(11) NOT NULL DEFAULT 0,
  `bank_item126_id` int(11) NOT NULL DEFAULT 0,
  `bank_item127_id` int(11) NOT NULL DEFAULT 0,
  `bank_item128_id` int(11) NOT NULL DEFAULT 0,
  `bank_item129_id` int(11) NOT NULL DEFAULT 0,
  `bank_item130_id` int(11) NOT NULL DEFAULT 0,
  `bank_item131_id` int(11) NOT NULL DEFAULT 0,
  `bank_item132_id` int(11) NOT NULL DEFAULT 0,
  `bank_item133_id` int(11) NOT NULL DEFAULT 0,
  `bank_item134_id` int(11) NOT NULL DEFAULT 0,
  `bank_item135_id` int(11) NOT NULL DEFAULT 0,
  `bank_item136_id` int(11) NOT NULL DEFAULT 0,
  `bank_item137_id` int(11) NOT NULL DEFAULT 0,
  `bank_item138_id` int(11) NOT NULL DEFAULT 0,
  `bank_item139_id` int(11) NOT NULL DEFAULT 0,
  `bank_item140_id` int(11) NOT NULL DEFAULT 0,
  `bank_item141_id` int(11) NOT NULL DEFAULT 0,
  `bank_item142_id` int(11) NOT NULL DEFAULT 0,
  `bank_item143_id` int(11) NOT NULL DEFAULT 0,
  `bank_item144_id` int(11) NOT NULL DEFAULT 0,
  `bank_item145_id` int(11) NOT NULL DEFAULT 0,
  `bank_item146_id` int(11) NOT NULL DEFAULT 0,
  `bank_item147_id` int(11) NOT NULL DEFAULT 0,
  `bank_item148_id` int(11) NOT NULL DEFAULT 0,
  `bank_item149_id` int(11) NOT NULL DEFAULT 0,
  `bank_item150_id` int(11) NOT NULL DEFAULT 0,
  `bank_item151_id` int(11) NOT NULL DEFAULT 0,
  `bank_item152_id` int(11) NOT NULL DEFAULT 0,
  `bank_item153_id` int(11) NOT NULL DEFAULT 0,
  `bank_item154_id` int(11) NOT NULL DEFAULT 0,
  `bank_item155_id` int(11) NOT NULL DEFAULT 0,
  `bank_item156_id` int(11) NOT NULL DEFAULT 0,
  `bank_item157_id` int(11) NOT NULL DEFAULT 0,
  `bank_item158_id` int(11) NOT NULL DEFAULT 0,
  `bank_item159_id` int(11) NOT NULL DEFAULT 0,
  `bank_item160_id` int(11) NOT NULL DEFAULT 0,
  `bank_item161_id` int(11) NOT NULL DEFAULT 0,
  `bank_item162_id` int(11) NOT NULL DEFAULT 0,
  `bank_item163_id` int(11) NOT NULL DEFAULT 0,
  `bank_item164_id` int(11) NOT NULL DEFAULT 0,
  `bank_item165_id` int(11) NOT NULL DEFAULT 0,
  `bank_item166_id` int(11) NOT NULL DEFAULT 0,
  `bank_item167_id` int(11) NOT NULL DEFAULT 0,
  `bank_item168_id` int(11) NOT NULL DEFAULT 0,
  `bank_item169_id` int(11) NOT NULL DEFAULT 0,
  `bank_item170_id` int(11) NOT NULL DEFAULT 0,
  `bank_item171_id` int(11) NOT NULL DEFAULT 0,
  `bank_item172_id` int(11) NOT NULL DEFAULT 0,
  `bank_item173_id` int(11) NOT NULL DEFAULT 0,
  `bank_item174_id` int(11) NOT NULL DEFAULT 0,
  `bank_item175_id` int(11) NOT NULL DEFAULT 0,
  `bank_item176_id` int(11) NOT NULL DEFAULT 0,
  `bank_item177_id` int(11) NOT NULL DEFAULT 0,
  `bank_item178_id` int(11) NOT NULL DEFAULT 0,
  `bank_item179_id` int(11) NOT NULL DEFAULT 0,
  `bank_item180_id` int(11) NOT NULL DEFAULT 0,
  `bank_item181_id` int(11) NOT NULL DEFAULT 0,
  `bank_item182_id` int(11) NOT NULL DEFAULT 0,
  `bank_item183_id` int(11) NOT NULL DEFAULT 0,
  `bank_item184_id` int(11) NOT NULL DEFAULT 0,
  `bank_item185_id` int(11) NOT NULL DEFAULT 0,
  `bank_item186_id` int(11) NOT NULL DEFAULT 0,
  `bank_item187_id` int(11) NOT NULL DEFAULT 0,
  `bank_item188_id` int(11) NOT NULL DEFAULT 0,
  `bank_item189_id` int(11) NOT NULL DEFAULT 0,
  `bank_item190_id` int(11) NOT NULL DEFAULT 0,
  `bank_item191_id` int(11) NOT NULL DEFAULT 0,
  `bank_item192_id` int(11) NOT NULL DEFAULT 0,
  `bank_item193_id` int(11) NOT NULL DEFAULT 0,
  `bank_item194_id` int(11) NOT NULL DEFAULT 0,
  `bank_item195_id` int(11) NOT NULL DEFAULT 0,
  `bank_item196_id` int(11) NOT NULL DEFAULT 0,
  `bank_item197_id` int(11) NOT NULL DEFAULT 0,
  `bank_item198_id` int(11) NOT NULL DEFAULT 0,
  `bank_item199_id` int(11) NOT NULL DEFAULT 0,
  `bank_item200_id` int(11) NOT NULL DEFAULT 0,
  `bank_item201_id` int(11) NOT NULL DEFAULT 0,
  `bank_item202_id` int(11) NOT NULL DEFAULT 0,
  `bank_item203_id` int(11) NOT NULL DEFAULT 0,
  `bank_item204_id` int(11) NOT NULL DEFAULT 0,
  `bank_item205_id` int(11) NOT NULL DEFAULT 0,
  `bank_item206_id` int(11) NOT NULL DEFAULT 0,
  `bank_item207_id` int(11) NOT NULL DEFAULT 0,
  `bank_item208_id` int(11) NOT NULL DEFAULT 0,
  `bank_item209_id` int(11) NOT NULL DEFAULT 0,
  `bank_item210_id` int(11) NOT NULL DEFAULT 0,
  `bank_item211_id` int(11) NOT NULL DEFAULT 0,
  `bank_item212_id` int(11) NOT NULL DEFAULT 0,
  `bank_item213_id` int(11) NOT NULL DEFAULT 0,
  `bank_item214_id` int(11) NOT NULL DEFAULT 0,
  `bank_item215_id` int(11) NOT NULL DEFAULT 0,
  `bank_item216_id` int(11) NOT NULL DEFAULT 0,
  `bank_item217_id` int(11) NOT NULL DEFAULT 0,
  `bank_item218_id` int(11) NOT NULL DEFAULT 0,
  `bank_item219_id` int(11) NOT NULL DEFAULT 0,
  `bank_item220_id` int(11) NOT NULL DEFAULT 0,
  `bank_item221_id` int(11) NOT NULL DEFAULT 0,
  `bank_item222_id` int(11) NOT NULL DEFAULT 0,
  `bank_item223_id` int(11) NOT NULL DEFAULT 0,
  `bank_item224_id` int(11) NOT NULL DEFAULT 0,
  `bank_item225_id` int(11) NOT NULL DEFAULT 0,
  `bank_item226_id` int(11) NOT NULL DEFAULT 0,
  `bank_item227_id` int(11) NOT NULL DEFAULT 0,
  `bank_item228_id` int(11) NOT NULL DEFAULT 0,
  `bank_item229_id` int(11) NOT NULL DEFAULT 0,
  `bank_item230_id` int(11) NOT NULL DEFAULT 0,
  `bank_item231_id` int(11) NOT NULL DEFAULT 0,
  `bank_item232_id` int(11) NOT NULL DEFAULT 0,
  `bank_item233_id` int(11) NOT NULL DEFAULT 0,
  `bank_item234_id` int(11) NOT NULL DEFAULT 0,
  `bank_item235_id` int(11) NOT NULL DEFAULT 0,
  `bank_item236_id` int(11) NOT NULL DEFAULT 0,
  `bank_item237_id` int(11) NOT NULL DEFAULT 0,
  `bank_item238_id` int(11) NOT NULL DEFAULT 0,
  `bank_item239_id` int(11) NOT NULL DEFAULT 0,
  `bank_item240_id` int(11) NOT NULL DEFAULT 0,
  `bank_item241_id` int(11) NOT NULL DEFAULT 0,
  `bank_item242_id` int(11) NOT NULL DEFAULT 0,
  `bank_item243_id` int(11) NOT NULL DEFAULT 0,
  `bank_item244_id` int(11) NOT NULL DEFAULT 0,
  `bank_item245_id` int(11) NOT NULL DEFAULT 0,
  `bank_item246_id` int(11) NOT NULL DEFAULT 0,
  `bank_item247_id` int(11) NOT NULL DEFAULT 0,
  `bank_item248_id` int(11) NOT NULL DEFAULT 0,
  `bank_item249_id` int(11) NOT NULL DEFAULT 0,
  `bank_item250_id` int(11) NOT NULL DEFAULT 0,
  `bank_item251_id` int(11) NOT NULL DEFAULT 0,
  `bank_item252_id` int(11) NOT NULL DEFAULT 0,
  `bank_item253_id` int(11) NOT NULL DEFAULT 0,
  `bank_item254_id` int(11) NOT NULL DEFAULT 0,
  `bank_item255_id` int(11) NOT NULL DEFAULT 0,
  `bank_item256_id` int(11) NOT NULL DEFAULT 0,
  `bank_item257_id` int(11) NOT NULL DEFAULT 0,
  `bank_item258_id` int(11) NOT NULL DEFAULT 0,
  `bank_item259_id` int(11) NOT NULL DEFAULT 0,
  `bank_item260_id` int(11) NOT NULL DEFAULT 0,
  `bank_item261_id` int(11) NOT NULL DEFAULT 0,
  `bank_item262_id` int(11) NOT NULL DEFAULT 0,
  `bank_item263_id` int(11) NOT NULL DEFAULT 0,
  `bank_item264_id` int(11) NOT NULL DEFAULT 0,
  `bank_item265_id` int(11) NOT NULL DEFAULT 0,
  `bank_item266_id` int(11) NOT NULL DEFAULT 0,
  `bank_item267_id` int(11) NOT NULL DEFAULT 0,
  `bank_item268_id` int(11) NOT NULL DEFAULT 0,
  `bank_item269_id` int(11) NOT NULL DEFAULT 0,
  `bank_item270_id` int(11) NOT NULL DEFAULT 0,
  `bank_item271_id` int(11) NOT NULL DEFAULT 0,
  `bank_item272_id` int(11) NOT NULL DEFAULT 0,
  `bank_item273_id` int(11) NOT NULL DEFAULT 0,
  `bank_item274_id` int(11) NOT NULL DEFAULT 0,
  `bank_item275_id` int(11) NOT NULL DEFAULT 0,
  `bank_item276_id` int(11) NOT NULL DEFAULT 0,
  `bank_item277_id` int(11) NOT NULL DEFAULT 0,
  `bank_item278_id` int(11) NOT NULL DEFAULT 0,
  `bank_item279_id` int(11) NOT NULL DEFAULT 0,
  `bank_item280_id` int(11) NOT NULL DEFAULT 0,
  `bank_item281_id` int(11) NOT NULL DEFAULT 0,
  `bank_item282_id` int(11) NOT NULL DEFAULT 0,
  `bank_item283_id` int(11) NOT NULL DEFAULT 0,
  `bank_item284_id` int(11) NOT NULL DEFAULT 0,
  `bank_item285_id` int(11) NOT NULL DEFAULT 0,
  `bank_item286_id` int(11) NOT NULL DEFAULT 0,
  `bank_item287_id` int(11) NOT NULL DEFAULT 0,
  `bank_item288_id` int(11) NOT NULL DEFAULT 0,
  `bank_item289_id` int(11) NOT NULL DEFAULT 0,
  `bank_item290_id` int(11) NOT NULL DEFAULT 0,
  `bank_item291_id` int(11) NOT NULL DEFAULT 0,
  `bank_item292_id` int(11) NOT NULL DEFAULT 0,
  `bank_item293_id` int(11) NOT NULL DEFAULT 0,
  `bank_item294_id` int(11) NOT NULL DEFAULT 0,
  `bank_item295_id` int(11) NOT NULL DEFAULT 0,
  `bank_item296_id` int(11) NOT NULL DEFAULT 0,
  `bank_item297_id` int(11) NOT NULL DEFAULT 0,
  `bank_item298_id` int(11) NOT NULL DEFAULT 0,
  `bank_item299_id` int(11) NOT NULL DEFAULT 0,
  `bank_item300_id` int(11) NOT NULL DEFAULT 0,
  `bank_item301_id` int(11) NOT NULL DEFAULT 0,
  `bank_item302_id` int(11) NOT NULL DEFAULT 0,
  `bank_item303_id` int(11) NOT NULL DEFAULT 0,
  `bank_item304_id` int(11) NOT NULL DEFAULT 0,
  `bank_item305_id` int(11) NOT NULL DEFAULT 0,
  `bank_item306_id` int(11) NOT NULL DEFAULT 0,
  `bank_item307_id` int(11) NOT NULL DEFAULT 0,
  `bank_item308_id` int(11) NOT NULL DEFAULT 0,
  `bank_item309_id` int(11) NOT NULL DEFAULT 0,
  `bank_item310_id` int(11) NOT NULL DEFAULT 0,
  `bank_item311_id` int(11) NOT NULL DEFAULT 0,
  `bank_item312_id` int(11) NOT NULL DEFAULT 0,
  `bank_item313_id` int(11) NOT NULL DEFAULT 0,
  `bank_item314_id` int(11) NOT NULL DEFAULT 0,
  `bank_item315_id` int(11) NOT NULL DEFAULT 0,
  `bank_item316_id` int(11) NOT NULL DEFAULT 0,
  `bank_item317_id` int(11) NOT NULL DEFAULT 0,
  `bank_item318_id` int(11) NOT NULL DEFAULT 0,
  `bank_item319_id` int(11) NOT NULL DEFAULT 0,
  `bank_item320_id` int(11) NOT NULL DEFAULT 0,
  `bank_item321_id` int(11) NOT NULL DEFAULT 0,
  `bank_item322_id` int(11) NOT NULL DEFAULT 0,
  `bank_item323_id` int(11) NOT NULL DEFAULT 0,
  `bank_item324_id` int(11) NOT NULL DEFAULT 0,
  `bank_item325_id` int(11) NOT NULL DEFAULT 0,
  `bank_item326_id` int(11) NOT NULL DEFAULT 0,
  `bank_item327_id` int(11) NOT NULL DEFAULT 0,
  `bank_item328_id` int(11) NOT NULL DEFAULT 0,
  `bank_item329_id` int(11) NOT NULL DEFAULT 0,
  `bank_item330_id` int(11) NOT NULL DEFAULT 0,
  `bank_item331_id` int(11) NOT NULL DEFAULT 0,
  `bank_item332_id` int(11) NOT NULL DEFAULT 0,
  `bank_item333_id` int(11) NOT NULL DEFAULT 0,
  `bank_item334_id` int(11) NOT NULL DEFAULT 0,
  `bank_item335_id` int(11) NOT NULL DEFAULT 0,
  `bank_item336_id` int(11) NOT NULL DEFAULT 0,
  `bank_item337_id` int(11) NOT NULL DEFAULT 0,
  `bank_item338_id` int(11) NOT NULL DEFAULT 0,
  `bank_item339_id` int(11) NOT NULL DEFAULT 0,
  `bank_item340_id` int(11) NOT NULL DEFAULT 0,
  `bank_item341_id` int(11) NOT NULL DEFAULT 0,
  `bank_item342_id` int(11) NOT NULL DEFAULT 0,
  `bank_item343_id` int(11) NOT NULL DEFAULT 0,
  `bank_item344_id` int(11) NOT NULL DEFAULT 0,
  `bank_item345_id` int(11) NOT NULL DEFAULT 0,
  `bank_item346_id` int(11) NOT NULL DEFAULT 0,
  `bank_item347_id` int(11) NOT NULL DEFAULT 0,
  `bank_item348_id` int(11) NOT NULL DEFAULT 0,
  `bank_item349_id` int(11) NOT NULL DEFAULT 0,
  `bank_item350_id` int(11) NOT NULL DEFAULT 0,
  `bank_item351_id` int(11) NOT NULL DEFAULT 0,
  `bank_item352_id` int(11) NOT NULL DEFAULT 0,
  `bank_item353_id` int(11) NOT NULL DEFAULT 0,
  `bank_item354_id` int(11) NOT NULL DEFAULT 0,
  `bank_item355_id` int(11) NOT NULL DEFAULT 0,
  `bank_item356_id` int(11) NOT NULL DEFAULT 0,
  `bank_item357_id` int(11) NOT NULL DEFAULT 0,
  `bank_item358_id` int(11) NOT NULL DEFAULT 0,
  `bank_item359_id` int(11) NOT NULL DEFAULT 0,
  `bank_item360_id` int(11) NOT NULL DEFAULT 0,
  `bank_item361_id` int(11) NOT NULL DEFAULT 0,
  `bank_item362_id` int(11) NOT NULL DEFAULT 0,
  `bank_item363_id` int(11) NOT NULL DEFAULT 0,
  `bank_item364_id` int(11) NOT NULL DEFAULT 0,
  `bank_item365_id` int(11) NOT NULL DEFAULT 0,
  `bank_item366_id` int(11) NOT NULL DEFAULT 0,
  `bank_item367_id` int(11) NOT NULL DEFAULT 0,
  `bank_item368_id` int(11) NOT NULL DEFAULT 0,
  `bank_item369_id` int(11) NOT NULL DEFAULT 0,
  `bank_item370_id` int(11) NOT NULL DEFAULT 0,
  `bank_item371_id` int(11) NOT NULL DEFAULT 0,
  `bank_item372_id` int(11) NOT NULL DEFAULT 0,
  `bank_item373_id` int(11) NOT NULL DEFAULT 0,
  `bank_item374_id` int(11) NOT NULL DEFAULT 0,
  `bank_item375_id` int(11) NOT NULL DEFAULT 0,
  `bank_item376_id` int(11) NOT NULL DEFAULT 0,
  `bank_item377_id` int(11) NOT NULL DEFAULT 0,
  `bank_item378_id` int(11) NOT NULL DEFAULT 0,
  `bank_item379_id` int(11) NOT NULL DEFAULT 0,
  `bank_item380_id` int(11) NOT NULL DEFAULT 0,
  `bank_item381_id` int(11) NOT NULL DEFAULT 0,
  `bank_item382_id` int(11) NOT NULL DEFAULT 0,
  `bank_item383_id` int(11) NOT NULL DEFAULT 0,
  `bank_item384_id` int(11) NOT NULL DEFAULT 0,
  `bank_item385_id` int(11) NOT NULL DEFAULT 0,
  `bank_item386_id` int(11) NOT NULL DEFAULT 0,
  `bank_item387_id` int(11) NOT NULL DEFAULT 0,
  `bank_item388_id` int(11) NOT NULL DEFAULT 0,
  `bank_item389_id` int(11) NOT NULL DEFAULT 0,
  `bank_item390_id` int(11) NOT NULL DEFAULT 0,
  `bank_item391_id` int(11) NOT NULL DEFAULT 0,
  `bank_item392_id` int(11) NOT NULL DEFAULT 0,
  `bank_item393_id` int(11) NOT NULL DEFAULT 0,
  `bank_item394_id` int(11) NOT NULL DEFAULT 0,
  `bank_item395_id` int(11) NOT NULL DEFAULT 0,
  `bank_item396_id` int(11) NOT NULL DEFAULT 0,
  `bank_item397_id` int(11) NOT NULL DEFAULT 0,
  `bank_item398_id` int(11) NOT NULL DEFAULT 0,
  `bank_item399_id` int(11) NOT NULL DEFAULT 0,
  `bank_item400_id` int(11) NOT NULL DEFAULT 0,
  `bank_item401_id` int(11) NOT NULL DEFAULT 0,
  `bank_item402_id` int(11) NOT NULL DEFAULT 0,
  `bank_item403_id` int(11) NOT NULL DEFAULT 0,
  `bank_item404_id` int(11) NOT NULL DEFAULT 0,
  `bank_item405_id` int(11) NOT NULL DEFAULT 0,
  `bank_item406_id` int(11) NOT NULL DEFAULT 0,
  `bank_item407_id` int(11) NOT NULL DEFAULT 0,
  `bank_item408_id` int(11) NOT NULL DEFAULT 0,
  `bank_item409_id` int(11) NOT NULL DEFAULT 0,
  `bank_item410_id` int(11) NOT NULL DEFAULT 0,
  `bank_item411_id` int(11) NOT NULL DEFAULT 0,
  `bank_item412_id` int(11) NOT NULL DEFAULT 0,
  `bank_item413_id` int(11) NOT NULL DEFAULT 0,
  `bank_item414_id` int(11) NOT NULL DEFAULT 0,
  `bank_item415_id` int(11) NOT NULL DEFAULT 0,
  `bank_item416_id` int(11) NOT NULL DEFAULT 0,
  `bank_item417_id` int(11) NOT NULL DEFAULT 0,
  `bank_item418_id` int(11) NOT NULL DEFAULT 0,
  `bank_item419_id` int(11) NOT NULL DEFAULT 0,
  `bank_item420_id` int(11) NOT NULL DEFAULT 0,
  `bank_item421_id` int(11) NOT NULL DEFAULT 0,
  `bank_item422_id` int(11) NOT NULL DEFAULT 0,
  `bank_item423_id` int(11) NOT NULL DEFAULT 0,
  `bank_item424_id` int(11) NOT NULL DEFAULT 0,
  `bank_item425_id` int(11) NOT NULL DEFAULT 0,
  `bank_item426_id` int(11) NOT NULL DEFAULT 0,
  `bank_item427_id` int(11) NOT NULL DEFAULT 0,
  `bank_item428_id` int(11) NOT NULL DEFAULT 0,
  `bank_item429_id` int(11) NOT NULL DEFAULT 0,
  `bank_item430_id` int(11) NOT NULL DEFAULT 0,
  `bank_item431_id` int(11) NOT NULL DEFAULT 0,
  `bank_item432_id` int(11) NOT NULL DEFAULT 0,
  `bank_item433_id` int(11) NOT NULL DEFAULT 0,
  `bank_item434_id` int(11) NOT NULL DEFAULT 0,
  `bank_item435_id` int(11) NOT NULL DEFAULT 0,
  `bank_item436_id` int(11) NOT NULL DEFAULT 0,
  `bank_item437_id` int(11) NOT NULL DEFAULT 0,
  `bank_item438_id` int(11) NOT NULL DEFAULT 0,
  `bank_item439_id` int(11) NOT NULL DEFAULT 0,
  `bank_item440_id` int(11) NOT NULL DEFAULT 0,
  `bank_item441_id` int(11) NOT NULL DEFAULT 0,
  `bank_item442_id` int(11) NOT NULL DEFAULT 0,
  `bank_item443_id` int(11) NOT NULL DEFAULT 0,
  `bank_item444_id` int(11) NOT NULL DEFAULT 0,
  `bank_item445_id` int(11) NOT NULL DEFAULT 0,
  `bank_item446_id` int(11) NOT NULL DEFAULT 0,
  `bank_item447_id` int(11) NOT NULL DEFAULT 0,
  `bank_item448_id` int(11) NOT NULL DEFAULT 0,
  `bank_item449_id` int(11) NOT NULL DEFAULT 0,
  `bank_item450_id` int(11) NOT NULL DEFAULT 0,
  `locked_item_ids` text NOT NULL DEFAULT ('[]'),
  PRIMARY KEY (`id`),
  KEY `character_id` (`character_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `battle`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `battle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ts_creation` int(11) NOT NULL,
  `profile_a_stats` text NOT NULL,
  `profile_b_stats` text NOT NULL,
  `winner` varchar(1) NOT NULL,
  `rounds` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `character`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `character` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(32) NOT NULL,
  `gender` enum('m','f') NOT NULL,
  `game_currency` int(11) unsigned NOT NULL DEFAULT 0,
  `xp` int(11) unsigned NOT NULL DEFAULT 0,
  `level` int(11) unsigned NOT NULL DEFAULT 1,
  `description` varchar(255) NOT NULL,
  `note` varchar(512) NOT NULL,
  `ts_last_action` int(11) NOT NULL,
  `score_honor` int(11) NOT NULL DEFAULT 10,
  `score_level` bigint(20) NOT NULL DEFAULT 10,
  `stat_points_available` mediumint(9) NOT NULL,
  `stat_base_stamina` smallint(6) NOT NULL DEFAULT 10,
  `stat_base_strength` smallint(6) NOT NULL DEFAULT 10,
  `stat_base_critical_rating` smallint(6) NOT NULL DEFAULT 10,
  `stat_base_dodge_rating` smallint(6) NOT NULL DEFAULT 10,
  `stat_bought_stamina` mediumint(8) NOT NULL,
  `stat_bought_strength` mediumint(8) NOT NULL,
  `stat_bought_critical_rating` mediumint(8) NOT NULL,
  `stat_bought_dodge_rating` mediumint(8) NOT NULL,
  `active_quest_booster_id` varchar(25) NOT NULL,
  `ts_active_quest_boost_expires` int(11) NOT NULL,
  `active_stats_booster_id` varchar(25) NOT NULL,
  `ts_active_stats_boost_expires` int(11) NOT NULL,
  `active_work_booster_id` varchar(25) NOT NULL,
  `ts_active_work_boost_expires` int(11) NOT NULL,
  `ts_active_sense_boost_expires` int(11) NOT NULL,
  `active_league_booster_id` varchar(32) NOT NULL,
  `ts_active_league_boost_expires` int(11) NOT NULL,
  `ts_active_multitasking_boost_expires` int(11) NOT NULL,
  `ts_active_hideout_boost_started` int(11) NOT NULL DEFAULT 0,
  `active_hideout_booster_id` varchar(32) NOT NULL DEFAULT '',
  `ts_active_hideout_boost_expires` int(11) NOT NULL DEFAULT 0,
  `max_quest_stage` smallint(6) NOT NULL DEFAULT 1,
  `current_quest_stage` smallint(6) NOT NULL DEFAULT 1,
  `quest_energy` smallint(6) NOT NULL DEFAULT 100,
  `max_quest_energy` smallint(6) NOT NULL DEFAULT 100,
  `ts_last_quest_energy_refill` int(11) NOT NULL,
  `quest_energy_refill_amount_today` smallint(6) NOT NULL,
  `quest_reward_training_sessions_rewarded_today` smallint(6) NOT NULL,
  `honor` mediumint(8) unsigned NOT NULL DEFAULT 100,
  `ts_last_duel` int(11) NOT NULL,
  `active_duel_id` int(11) NOT NULL DEFAULT 0,
  `duel_stamina` smallint(6) NOT NULL DEFAULT 100,
  `max_duel_stamina` smallint(6) NOT NULL DEFAULT 100,
  `ts_last_duel_stamina_change` int(11) NOT NULL,
  `ts_last_duel_enemies_refresh` int(11) NOT NULL,
  `current_work_offer_id` varchar(32) NOT NULL DEFAULT 'work1',
  `stat_trained_stamina` mediumint(8) NOT NULL,
  `stat_trained_strength` mediumint(8) NOT NULL,
  `stat_trained_critical_rating` mediumint(8) NOT NULL,
  `stat_trained_dodge_rating` mediumint(8) NOT NULL,
  `training_progress_value_stamina` smallint(8) NOT NULL,
  `training_progress_value_strength` mediumint(8) NOT NULL,
  `training_progress_value_critical_rating` mediumint(8) NOT NULL,
  `training_progress_value_dodge_rating` mediumint(8) NOT NULL,
  `training_progress_end_stamina` smallint(6) NOT NULL DEFAULT 3,
  `training_progress_end_strength` smallint(6) NOT NULL DEFAULT 3,
  `training_progress_end_critical_rating` smallint(6) NOT NULL DEFAULT 3,
  `training_progress_end_dodge_rating` smallint(6) NOT NULL DEFAULT 3,
  `ts_last_training` int(11) NOT NULL,
  `ts_last_training_finished` int(11) NOT NULL DEFAULT 0,
  `training_pool_items` varchar(255) NOT NULL DEFAULT '',
  `training_count` smallint(6) NOT NULL DEFAULT 10,
  `max_training_count` smallint(6) NOT NULL DEFAULT 10,
  `training_energy` smallint(6) NOT NULL DEFAULT 30,
  `max_training_energy` smallint(6) NOT NULL DEFAULT 30,
  `ts_last_training_energy_change` int(11) NOT NULL DEFAULT 0,
  `active_worldboss_attack_id` int(11) NOT NULL,
  `active_dungeon_quest_id` int(11) NOT NULL,
  `ts_last_dungeon_quest_fail` int(11) NOT NULL,
  `max_dungeon_index` int(11) NOT NULL,
  `appearance_skin_color` tinyint(3) NOT NULL,
  `appearance_hair_color` tinyint(3) NOT NULL,
  `appearance_hair_type` tinyint(3) NOT NULL,
  `appearance_head_type` tinyint(3) NOT NULL,
  `appearance_eyes_type` tinyint(3) NOT NULL,
  `appearance_eyebrows_type` tinyint(3) NOT NULL,
  `appearance_nose_type` tinyint(3) NOT NULL,
  `appearance_mouth_type` tinyint(3) NOT NULL,
  `appearance_facial_hair_type` tinyint(3) NOT NULL,
  `appearance_decoration_type` tinyint(3) NOT NULL DEFAULT 1,
  `show_mask` tinyint(1) NOT NULL DEFAULT 1,
  `tutorial_flags` text NOT NULL,
  `guild_id` int(11) NOT NULL,
  `guild_rank` tinyint(2) NOT NULL,
  `ts_guild_joined` int(11) NOT NULL,
  `finished_guild_battle_attack_id` int(11) NOT NULL,
  `finished_guild_battle_defense_id` int(11) NOT NULL,
  `finished_guild_dungeon_battle_id` int(11) NOT NULL,
  `guild_donated_game_currency` int(11) NOT NULL,
  `guild_donated_premium_currency` int(11) NOT NULL,
  `worldboss_event_id` int(11) NOT NULL,
  `worldboss_event_attack_count` smallint(6) NOT NULL,
  `ts_last_wash_item` int(11) NOT NULL,
  `ts_last_daily_login_bonus` int(11) NOT NULL,
  `daily_login_bonus_day` tinyint(3) NOT NULL DEFAULT 1,
  `pending_tournament_rewards` int(11) NOT NULL,
  `ts_last_shop_refresh` int(11) NOT NULL,
  `shop_refreshes` smallint(6) NOT NULL,
  `event_quest_id` int(11) NOT NULL,
  `friend_data` varchar(32) NOT NULL,
  `pending_resource_requests` smallint(6) NOT NULL,
  `unused_resources` varchar(255) NOT NULL DEFAULT '{"1":4,"2":1}',
  `used_resources` varchar(255) NOT NULL DEFAULT '{}',
  `league_points` int(11) NOT NULL,
  `league_group_id` int(11) NOT NULL,
  `active_league_fight_id` int(11) NOT NULL,
  `ts_last_league_fight` int(11) NOT NULL,
  `league_fight_count` int(11) NOT NULL,
  `league_opponents` varchar(32) NOT NULL,
  `ts_last_league_opponents_refresh` int(11) NOT NULL,
  `league_stamina` smallint(6) NOT NULL DEFAULT 20,
  `max_league_stamina` smallint(6) NOT NULL DEFAULT 20,
  `ts_last_league_stamina_change` int(11) NOT NULL,
  `league_stamina_cost` int(11) NOT NULL DEFAULT 20,
  `herobook_objectives_renewed_today` int(11) NOT NULL,
  `slotmachine_spin_count` int(11) NOT NULL,
  `ts_last_slotmachine_refill` int(11) NOT NULL,
  `new_user_voucher_ids` varchar(32) NOT NULL,
  `current_energy_storage` int(11) NOT NULL,
  `current_training_storage` int(11) NOT NULL,
  `received_sidekick` tinyint(1) NOT NULL DEFAULT 0,
  `role` tinyint(1) NOT NULL DEFAULT 0,
  `herobook_objectives_finished` int(11) NOT NULL DEFAULT 0,
  `goal_stats` text DEFAULT NULL,
  `owned_item_templates` mediumtext DEFAULT NULL,
  `collected_item_pattern` mediumtext DEFAULT NULL,
  `current_item_pattern_values` mediumtext DEFAULT NULL,
  `active_quest_id` int(11) NOT NULL DEFAULT 0,
  `story_dungeon_state` text DEFAULT NULL,
  `casino_state` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `guild_id` (`guild_id`),
  KEY `user_id` (`user_id`),
  KEY `honor` (`honor`),
  KEY `level` (`level`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `collected_goals`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `collected_goals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `character_id` int(11) NOT NULL,
  `goal_name` varchar(100) NOT NULL,
  `milestone_value` int(11) NOT NULL,
  `collected_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_goal_milestone` (`character_id`,`goal_name`,`milestone_value`),
  KEY `idx_character` (`character_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `duel`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `duel` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ts_creation` int(11) NOT NULL,
  `battle_id` int(11) NOT NULL,
  `character_a_id` int(11) NOT NULL,
  `character_b_id` int(11) NOT NULL,
  `character_a_status` tinyint(1) NOT NULL DEFAULT 1,
  `character_b_status` tinyint(1) NOT NULL DEFAULT 1,
  `character_a_rewards` text NOT NULL,
  `character_b_rewards` text NOT NULL,
  `unread` enum('true','false','','') NOT NULL DEFAULT 'true',
  PRIMARY KEY (`id`),
  KEY `attackerduel` (`character_a_id`,`character_a_status`),
  KEY `defenderduel` (`character_b_id`,`character_b_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dungeon_quests`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dungeon_quests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `character_id` int(11) NOT NULL,
  `character_level` tinyint(3) NOT NULL DEFAULT 0,
  `identifier` varchar(32) NOT NULL,
  `status` tinyint(3) NOT NULL DEFAULT 1,
  `battle_id` int(11) NOT NULL,
  `rewards` varchar(200) NOT NULL,
  `mode` tinyint(3) NOT NULL,
  `dungeon_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `dungeon_quests` (`character_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dungeons`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dungeons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `character_id` int(11) NOT NULL,
  `identifier` varchar(32) NOT NULL,
  `status` tinyint(3) NOT NULL DEFAULT 1,
  `current_dungeon_quest_id` int(11) NOT NULL,
  `progress_index` tinyint(3) NOT NULL DEFAULT 1,
  `mode` tinyint(3) NOT NULL,
  `ts_last_complete` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `dungeons` (`character_id`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `email_log`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `to_email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `template` varchar(64) NOT NULL DEFAULT '',
  `status` enum('sent','failed') NOT NULL,
  `error` text DEFAULT NULL,
  `ts_sent` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_ts_sent` (`ts_sent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `email_queue`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `to_email` varchar(255) NOT NULL,
  `to_name` varchar(100) NOT NULL DEFAULT '',
  `subject` varchar(255) NOT NULL,
  `body_html` text NOT NULL,
  `body_text` text NOT NULL,
  `template` varchar(64) NOT NULL DEFAULT '',
  `priority` tinyint(3) NOT NULL DEFAULT 5,
  `status` enum('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',
  `attempts` tinyint(3) NOT NULL DEFAULT 0,
  `max_attempts` tinyint(3) NOT NULL DEFAULT 3,
  `last_error` text DEFAULT NULL,
  `ts_created` int(11) NOT NULL,
  `ts_scheduled` int(11) NOT NULL DEFAULT 0,
  `ts_sent` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status_priority` (`status`,`priority`,`ts_scheduled`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `event_quests`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_quests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `character_id` int(11) NOT NULL,
  `identifier` varchar(100) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `end_date` varchar(30) NOT NULL DEFAULT '',
  `objective1_value` int(11) NOT NULL DEFAULT 0,
  `objective2_value` int(11) NOT NULL DEFAULT 0,
  `objective3_value` int(11) NOT NULL DEFAULT 0,
  `objective4_value` int(11) NOT NULL DEFAULT 0,
  `objective5_value` int(11) NOT NULL DEFAULT 0,
  `objective6_value` int(11) NOT NULL DEFAULT 0,
  `rewards` text NOT NULL,
  `reward_item1_id` int(11) NOT NULL DEFAULT 0,
  `reward_item2_id` int(11) NOT NULL DEFAULT 0,
  `reward_item3_id` int(11) NOT NULL DEFAULT 0,
  `ts_creation` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_character` (`character_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `goal_pending_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `goal_pending_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `character_id` int(11) NOT NULL,
  `goal_identifier` varchar(100) NOT NULL,
  `goal_value` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `created_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_goal_pending` (`character_id`,`goal_identifier`,`goal_value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guild`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guild` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ts_creation` int(11) NOT NULL,
  `initiator_character_id` int(11) NOT NULL,
  `leader_character_id` int(11) NOT NULL,
  `name` varchar(64) NOT NULL,
  `description` text NOT NULL,
  `note` text NOT NULL,
  `forum_page` varchar(128) NOT NULL,
  `premium_currency` int(11) NOT NULL,
  `game_currency` int(11) NOT NULL DEFAULT 500,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `accept_members` tinyint(1) NOT NULL,
  `honor` int(11) NOT NULL DEFAULT 1000,
  `artifact_ids` text NOT NULL,
  `missiles` int(11) NOT NULL DEFAULT 15,
  `auto_joins` tinyint(1) NOT NULL,
  `battles_attacked` int(11) NOT NULL,
  `battles_defended` int(11) NOT NULL,
  `battles_won` int(11) NOT NULL,
  `battles_lost` int(11) NOT NULL,
  `artifacts_won` int(11) NOT NULL,
  `artifacts_lost` int(11) NOT NULL,
  `artifacts_owned_max` int(11) NOT NULL DEFAULT 0,
  `artifacts_owned_current` int(11) NOT NULL,
  `ts_last_artifact_released` int(11) NOT NULL,
  `missiles_fired` int(11) NOT NULL,
  `auto_joins_used` tinyint(1) NOT NULL,
  `dungeon_battles_fought` int(11) NOT NULL,
  `dungeon_battles_won` int(11) NOT NULL,
  `stat_points_available` int(11) NOT NULL,
  `stat_guild_capacity` int(11) NOT NULL DEFAULT 10,
  `stat_character_base_stats_boost` int(11) NOT NULL DEFAULT 1,
  `stat_quest_xp_reward_boost` int(11) NOT NULL DEFAULT 1,
  `stat_quest_game_currency_reward_boost` int(11) NOT NULL DEFAULT 1,
  `arena_background` smallint(3) NOT NULL DEFAULT 1,
  `emblem_background_shape` tinyint(3) NOT NULL DEFAULT 1,
  `emblem_background_color` tinyint(3) NOT NULL DEFAULT 2,
  `emblem_background_border_color` tinyint(3) NOT NULL,
  `emblem_icon_shape` tinyint(3) NOT NULL DEFAULT 1,
  `emblem_icon_color` tinyint(3) NOT NULL DEFAULT 4,
  `emblem_icon_size` smallint(3) NOT NULL DEFAULT 100,
  `use_missiles_attack` tinyint(1) NOT NULL DEFAULT 1,
  `use_missiles_defense` tinyint(1) NOT NULL DEFAULT 1,
  `use_missiles_dungeon` tinyint(1) NOT NULL DEFAULT 1,
  `use_auto_joins_attack` tinyint(1) NOT NULL DEFAULT 1,
  `use_auto_joins_defense` tinyint(1) NOT NULL DEFAULT 1,
  `use_auto_joins_dungeon` tinyint(1) NOT NULL DEFAULT 1,
  `pending_leader_vote_id` int(11) NOT NULL,
  `min_apply_level` int(11) NOT NULL,
  `min_apply_honor` int(11) NOT NULL,
  `guild_battle_tactics_attack_order` int(11) NOT NULL DEFAULT 1,
  `guild_battle_tactics_attack_tactic` int(11) NOT NULL DEFAULT 10,
  `guild_battle_tactics_defense_order` int(11) NOT NULL DEFAULT 1,
  `guild_battle_tactics_defense_tactic` int(11) NOT NULL DEFAULT 10,
  `active_training_booster_id` varchar(40) NOT NULL,
  `ts_active_training_boost_expires` int(11) NOT NULL,
  `active_quest_booster_id` varchar(40) NOT NULL,
  `ts_active_quest_boost_expires` int(11) NOT NULL,
  `active_duel_booster_id` varchar(40) NOT NULL,
  `ts_active_duel_boost_expires` int(11) NOT NULL,
  `locale` varchar(10) NOT NULL DEFAULT 'pt_BR',
  `ts_last_synergy_calculation` int(11) NOT NULL DEFAULT 0,
  `ts_next_synergy_calculation` int(11) NOT NULL DEFAULT 0,
  `min_apply_hideout_level` int(11) NOT NULL DEFAULT 0,
  `min_apply_gem_level` int(11) NOT NULL DEFAULT 0,
  `apply_open` tinyint(1) NOT NULL DEFAULT 1,
  `apply_open_mail` tinyint(1) NOT NULL DEFAULT 1,
  `officer_note` text NOT NULL,
  `pending_guild_battle_attack_id` int(11) NOT NULL DEFAULT 0,
  `pending_guild_battle_defense_id` int(11) NOT NULL DEFAULT 0,
  `pending_guild_dungeon_battle_attack_id` int(11) NOT NULL DEFAULT 0,
  `battles_fought` int(11) NOT NULL DEFAULT 0,
  `unlocked_rewards` text NOT NULL,
  `guild_competition_reward_boost_factor` int(11) NOT NULL DEFAULT 0,
  `ts_guild_competition_reward_boost_expires` int(11) NOT NULL DEFAULT 0,
  `guildbook_objectives_renewed_today` int(11) NOT NULL DEFAULT 0,
  `synergy_energy` int(11) NOT NULL DEFAULT 0,
  `synergy_energy_storage` int(11) NOT NULL DEFAULT 0,
  `synergy_energy_storage_temp` int(11) NOT NULL DEFAULT 0,
  `synergy_energy_storage_max` int(11) NOT NULL DEFAULT 0,
  `active_synergy_energy` int(11) NOT NULL DEFAULT 0,
  `synergy_upgrades` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `name` (`name`),
  KEY `honor` (`honor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guild_battle`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guild_battle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status` int(11) NOT NULL,
  `battle_time` tinyint(1) NOT NULL,
  `ts_attack` int(11) NOT NULL,
  `guild_attacker_id` int(11) NOT NULL,
  `guild_defender_id` int(11) NOT NULL,
  `attacker_character_ids` text NOT NULL,
  `defender_character_ids` text NOT NULL,
  `guild_winner_id` int(11) NOT NULL,
  `attacker_character_profiles` text NOT NULL,
  `defender_character_profiles` text NOT NULL,
  `rounds` text NOT NULL,
  `attacker_rewards` text NOT NULL,
  `defender_rewards` text NOT NULL,
  `initiator_character_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `attackerbattle` (`guild_attacker_id`,`status`),
  KEY `defenderbattle` (`guild_defender_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guild_battle_rewards`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guild_battle_rewards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `guild_battle_id` int(11) NOT NULL,
  `character_id` int(111) NOT NULL,
  `game_currency` int(11) NOT NULL,
  `xp` int(11) NOT NULL DEFAULT 0,
  `item_id` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `type` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `attackerreward` (`character_id`,`type`),
  KEY `battlereward` (`guild_battle_id`,`character_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guild_dungeon`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guild_dungeon` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `guild_id` int(11) NOT NULL,
  `npc_team_identifier` varchar(10) NOT NULL,
  `npc_team_character_profiles` text NOT NULL,
  `settings` text NOT NULL,
  `ts_unlock` int(11) NOT NULL,
  `locking_character_name` varchar(30) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `guild_id` (`guild_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guild_dungeon_battle`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guild_dungeon_battle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status` int(11) NOT NULL,
  `battle_time` tinyint(1) NOT NULL,
  `ts_attack` int(11) NOT NULL,
  `guild_id` int(11) NOT NULL,
  `npc_team_identifier` varchar(10) NOT NULL,
  `settings` text NOT NULL,
  `character_ids` text NOT NULL,
  `joined_character_profiles` text NOT NULL,
  `npc_team_character_profiles` text NOT NULL,
  `rounds` text NOT NULL,
  `rewards` text NOT NULL,
  `initiator_character_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guild_invites`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guild_invites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `character_id` int(11) NOT NULL,
  `guild_id` int(11) NOT NULL,
  `ts_creation` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `character_id` (`character_id`),
  KEY `guild_id` (`guild_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guild_leader_votes`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guild_leader_votes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `guild_id` int(11) NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 1,
  `ts_creation` int(11) NOT NULL,
  `initiator_character_id` int(11) NOT NULL DEFAULT 0,
  `current_leader_character_id` int(11) NOT NULL,
  `new_leader_character_id` int(11) NOT NULL DEFAULT 0,
  `allowed_character_ids` text NOT NULL,
  `vote_results` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_guild_status` (`guild_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guild_logs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guild_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `guild_id` int(11) NOT NULL,
  `character_id` int(11) NOT NULL,
  `character_name` varchar(32) NOT NULL,
  `type` int(11) NOT NULL,
  `value1` varchar(64) NOT NULL,
  `value2` varchar(64) NOT NULL,
  `value3` varchar(64) NOT NULL,
  `timestamp` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `log` (`guild_id`,`timestamp`,`character_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guild_messages`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guild_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `guild_id` int(11) NOT NULL,
  `character_from_id` int(11) NOT NULL,
  `character_from_name` varchar(32) NOT NULL,
  `character_to_id` int(11) NOT NULL,
  `is_officer` tinyint(1) NOT NULL,
  `is_private` tinyint(1) NOT NULL,
  `message` text NOT NULL,
  `timestamp` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tsmessage` (`guild_id`,`timestamp`,`character_from_id`,`character_to_id`,`is_officer`,`is_private`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `herobook_objectives`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `herobook_objectives` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `character_id` int(11) NOT NULL,
  `identifier` varchar(100) NOT NULL DEFAULT '',
  `type` int(11) NOT NULL DEFAULT 0,
  `duration_type` tinyint(1) NOT NULL DEFAULT 1,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `current_value` int(11) NOT NULL DEFAULT 0,
  `max_value` int(11) NOT NULL DEFAULT 0,
  `ts_end` int(11) NOT NULL DEFAULT 0,
  `objective_index` int(11) NOT NULL DEFAULT 0,
  `rewards` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_character_status` (`character_id`,`status`),
  KEY `idx_character_duration` (`character_id`,`duration_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventory`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `character_id` int(11) NOT NULL,
  `mask_item_id` int(11) NOT NULL,
  `cape_item_id` int(11) NOT NULL,
  `suit_item_id` int(11) NOT NULL,
  `belt_item_id` int(11) NOT NULL,
  `boots_item_id` int(11) NOT NULL,
  `weapon_item_id` int(11) NOT NULL,
  `gadget_item_id` int(11) NOT NULL,
  `missiles_item_id` int(11) NOT NULL,
  `missiles1_item_id` int(11) NOT NULL DEFAULT -1,
  `missiles2_item_id` int(11) NOT NULL DEFAULT -1,
  `missiles3_item_id` int(11) NOT NULL DEFAULT -1,
  `missiles4_item_id` int(11) NOT NULL DEFAULT -1,
  `sidekick_id` int(11) NOT NULL,
  `bag_item1_id` int(11) NOT NULL,
  `bag_item2_id` int(11) NOT NULL,
  `bag_item3_id` int(11) NOT NULL,
  `bag_item4_id` int(11) NOT NULL,
  `bag_item5_id` int(11) NOT NULL,
  `bag_item6_id` int(11) NOT NULL,
  `bag_item7_id` int(11) NOT NULL,
  `bag_item8_id` int(11) NOT NULL,
  `bag_item9_id` int(11) NOT NULL,
  `bag_item10_id` int(11) NOT NULL,
  `bag_item11_id` int(11) NOT NULL,
  `bag_item12_id` int(11) NOT NULL,
  `bag_item13_id` int(11) NOT NULL,
  `bag_item14_id` int(11) NOT NULL,
  `bag_item15_id` int(11) NOT NULL,
  `bag_item16_id` int(11) NOT NULL,
  `bag_item17_id` int(11) NOT NULL,
  `bag_item18_id` int(11) NOT NULL,
  `shop_item1_id` int(11) NOT NULL,
  `shop_item2_id` int(11) NOT NULL,
  `shop_item3_id` int(11) NOT NULL,
  `shop_item4_id` int(11) NOT NULL,
  `shop_item5_id` int(11) NOT NULL,
  `shop_item6_id` int(11) NOT NULL,
  `shop_item7_id` int(11) NOT NULL,
  `shop_item8_id` int(11) NOT NULL,
  `shop_item9_id` int(11) NOT NULL,
  `shop2_item1_id` int(11) NOT NULL,
  `shop2_item2_id` int(11) NOT NULL,
  `shop2_item3_id` int(11) NOT NULL,
  `shop2_item4_id` int(11) NOT NULL,
  `shop2_item5_id` int(11) NOT NULL,
  `shop2_item6_id` int(11) NOT NULL,
  `shop2_item7_id` int(11) NOT NULL,
  `shop2_item8_id` int(11) NOT NULL,
  `shop2_item9_id` int(11) NOT NULL,
  `item_set_data` varchar(64) NOT NULL,
  `sidekick_data` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `character_id` (`character_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `character_id` int(11) NOT NULL,
  `identifier` varchar(100) NOT NULL,
  `type` tinyint(3) NOT NULL,
  `quality` tinyint(3) NOT NULL,
  `required_level` smallint(6) NOT NULL,
  `charges` tinyint(4) NOT NULL,
  `item_level` smallint(6) NOT NULL,
  `ts_availability_start` int(11) NOT NULL,
  `ts_availability_end` int(11) NOT NULL,
  `premium_item` tinyint(1) NOT NULL DEFAULT 0,
  `buy_price` mediumint(8) NOT NULL,
  `sell_price` mediumint(8) NOT NULL,
  `stat_stamina` mediumint(8) NOT NULL,
  `stat_strength` mediumint(8) NOT NULL,
  `stat_critical_rating` mediumint(8) NOT NULL,
  `stat_dodge_rating` mediumint(8) NOT NULL,
  `stat_weapon_damage` mediumint(8) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `character_id` (`character_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `league_fight`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `league_fight` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ts_creation` int(11) NOT NULL,
  `battle_id` int(11) NOT NULL,
  `character_a_id` int(11) NOT NULL,
  `character_b_id` int(11) NOT NULL,
  `character_a_status` tinyint(1) NOT NULL DEFAULT 1,
  `character_b_status` tinyint(1) NOT NULL DEFAULT 1,
  `character_a_rewards` text NOT NULL,
  `character_b_rewards` text NOT NULL,
  `unread` enum('true','false','','') NOT NULL DEFAULT 'false',
  PRIMARY KEY (`id`),
  KEY `attackerduel` (`character_a_id`,`character_a_status`),
  KEY `defenderduel` (`character_b_id`,`character_b_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `league_season`
-- Epoca da liga: UMA linha (id=1) com o fim da epoca atual. League::ensureSeason()
-- cria a linha se falta e, quando expira, roda o rolamento (pontos de cada
-- divisao voltam ao entry_points) e agenda a semana seguinte.
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `league_season` (
  `id` int(11) NOT NULL,
  `ts_end` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `message_ignored_characters`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `message_ignored_characters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `character_id` int(11) NOT NULL,
  `ignored_character_id` int(11) NOT NULL,
  `ts_creation` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_unique` (`character_id`,`ignored_character_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `messages`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `character_from_id` int(11) NOT NULL,
  `character_to_ids` mediumtext NOT NULL,
  `subject` varchar(80) NOT NULL,
  `message` text NOT NULL,
  `flag` varchar(64) NOT NULL,
  `flag_value` varchar(64) NOT NULL,
  `ts_creation` int(11) NOT NULL,
  `readed` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `character_id` (`character_from_id`),
  FULLTEXT KEY `character_to_id` (`character_to_ids`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_reset_tokens`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `ts_created` int(11) NOT NULL,
  `ts_expires` int(11) NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_token` (`token`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pattern_items`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pattern_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `character_id` int(11) NOT NULL DEFAULT 0,
  `identifier` varchar(100) NOT NULL DEFAULT '',
  `pattern_identifier` text DEFAULT NULL,
  `type` tinyint(4) NOT NULL DEFAULT 0,
  `quality` tinyint(4) NOT NULL DEFAULT 0,
  `required_level` smallint(6) NOT NULL DEFAULT 0,
  `charges` tinyint(4) NOT NULL DEFAULT 0,
  `item_level` smallint(6) NOT NULL DEFAULT 0,
  `ts_availability_start` int(11) NOT NULL DEFAULT 0,
  `ts_availability_end` int(11) NOT NULL DEFAULT 0,
  `premium_item` tinyint(1) NOT NULL DEFAULT 0,
  `buy_price` mediumint(9) NOT NULL DEFAULT 0,
  `sell_price` mediumint(9) NOT NULL DEFAULT 0,
  `stat_stamina` mediumint(9) NOT NULL DEFAULT 0,
  `stat_strength` mediumint(9) NOT NULL DEFAULT 0,
  `stat_critical_rating` mediumint(9) NOT NULL DEFAULT 0,
  `stat_dodge_rating` mediumint(9) NOT NULL DEFAULT 0,
  `stat_weapon_damage` mediumint(9) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_character_pattern` (`character_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `quests`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `character_id` int(11) NOT NULL,
  `identifier` varchar(32) NOT NULL,
  `type` tinyint(3) NOT NULL,
  `stage` tinyint(3) NOT NULL,
  `level` mediumint(8) NOT NULL,
  `status` tinyint(3) NOT NULL DEFAULT 1,
  `duration_type` tinyint(3) NOT NULL DEFAULT 1,
  `duration_raw` smallint(6) NOT NULL,
  `duration` smallint(6) NOT NULL,
  `ts_complete` int(11) NOT NULL DEFAULT 0,
  `energy_cost` smallint(6) NOT NULL,
  `fight_difficulty` tinyint(3) NOT NULL DEFAULT 0,
  `fight_npc_identifier` varchar(60) NOT NULL,
  `fight_battle_id` int(11) NOT NULL DEFAULT 0,
  `used_resources` tinyint(3) NOT NULL DEFAULT 0,
  `rewards` varchar(200) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `quests` (`character_id`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sidekicks`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sidekicks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_improvement_id` int(11) NOT NULL DEFAULT 0,
  `identifier` varchar(128) NOT NULL,
  `character_id` int(11) NOT NULL,
  `name` varchar(32) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `quality` tinyint(1) NOT NULL DEFAULT 1,
  `level` int(11) NOT NULL DEFAULT 1,
  `xp` int(11) NOT NULL DEFAULT 0,
  `stat_base_stamina` mediumint(9) NOT NULL,
  `stat_base_strength` mediumint(9) NOT NULL,
  `stat_base_critical_rating` mediumint(9) NOT NULL,
  `stat_base_dodge_rating` mediumint(9) NOT NULL,
  `stat_stamina` mediumint(9) NOT NULL,
  `stat_strength` mediumint(9) NOT NULL,
  `stat_critical_rating` mediumint(9) NOT NULL,
  `stat_dodge_rating` mediumint(9) NOT NULL,
  `stage1_skill_id` mediumint(5) NOT NULL,
  `stage2_skill_id` mediumint(5) NOT NULL,
  `stage3_skill_id` mediumint(5) NOT NULL,
  `sewed_identifier` varchar(128) NOT NULL DEFAULT '',
  `sewed_level` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `sidekicks` (`character_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `slotmachines`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `slotmachines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `character_id` int(10) unsigned NOT NULL,
  `slotmachine_reward_quality` tinyint(3) unsigned NOT NULL,
  `slotmachine_slot1` tinyint(3) unsigned NOT NULL,
  `slotmachine_slot2` tinyint(3) unsigned NOT NULL,
  `slotmachine_slot3` tinyint(3) unsigned NOT NULL,
  `slot` tinyint(1) NOT NULL DEFAULT 0,
  `won` tinyint(1) NOT NULL DEFAULT 0,
  `reward` text NOT NULL,
  `history` tinyint(1) NOT NULL DEFAULT 0,
  `timestamp` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `character_id` (`character_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tournament_rewards`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tournament_rewards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) NOT NULL DEFAULT 0,
  `character_id` int(11) NOT NULL DEFAULT 0,
  `week` int(11) NOT NULL DEFAULT 0,
  `rewards` text NOT NULL,
  `claimed` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reward_character` (`tournament_id`,`character_id`),
  KEY `idx_character_claimed` (`character_id`,`claimed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tournament_snapshots`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tournament_snapshots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) NOT NULL DEFAULT 0,
  `character_id` int(11) NOT NULL DEFAULT 0,
  `guild_id` int(11) NOT NULL DEFAULT 0,
  `xp_start` int(11) NOT NULL DEFAULT 0,
  `honor_start` int(11) NOT NULL DEFAULT 0,
  `guild_honor_start` int(11) NOT NULL DEFAULT 0,
  `stat_base_start` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_snapshot_character` (`tournament_id`,`character_id`),
  KEY `idx_tournament_character` (`tournament_id`,`character_id`),
  KEY `idx_tournament_guild` (`tournament_id`,`guild_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tournaments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tournaments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` tinyint NULL DEFAULT NULL,
  `week` int(11) NOT NULL DEFAULT 0,
  `ts_start` int(11) NOT NULL DEFAULT 0,
  `ts_end` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '1=active, 2=processing, 3=finished, 4=cancelled',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_type_start` (`type`,`ts_start`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `training`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `training` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `character_id` int(11) NOT NULL,
  `status` tinyint(1) NOT NULL,
  `stat_type` tinyint(1) NOT NULL,
  `ts_creation` int(11) NOT NULL,
  `ts_complete` int(11) NOT NULL,
  `ts_end` int(11) NOT NULL DEFAULT 0,
  `iterations` tinyint(1) NOT NULL,
  `used_resources` int(11) NOT NULL,
  `training_quest_id` bigint NOT NULL DEFAULT 0,
  `reward_item_id` int(11) NOT NULL DEFAULT 0,
  `bonus_roll` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `training` (`character_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_source` varchar(64) NOT NULL DEFAULT 'ref=;subid=;lp=default_newCharacter_25M;',
  `registration_ip` varchar(45) DEFAULT NULL,
  `ts_creation` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `email_new` varchar(100) NOT NULL DEFAULT '',
  `password_hash` varchar(255) NOT NULL,
  `last_login_ip` varchar(45) NOT NULL,
  `login_count` int(11) NOT NULL,
  `ts_last_login` int(11) NOT NULL,
  `session_id` varchar(32) NOT NULL,
  `session_id_cache1` varchar(32) NOT NULL,
  `session_id_cache2` varchar(32) NOT NULL,
  `session_id_cache3` varchar(32) NOT NULL,
  `session_id_cache4` varchar(32) NOT NULL,
  `session_id_cache5` varchar(32) NOT NULL,
  `premium_currency` int(11) NOT NULL DEFAULT 0,
  `locale` varchar(6) NOT NULL DEFAULT 'pl_PL',
  `network` varchar(10) NOT NULL,
  `geo_country_code` varchar(3) NOT NULL DEFAULT 'PL',
  `geo_country_code3` varchar(3) NOT NULL,
  `geo_country_name` varchar(16) NOT NULL DEFAULT 'Poland',
  `geo_continent_code` varchar(3) NOT NULL DEFAULT 'EU',
  `settings` varchar(250) NOT NULL DEFAULT '{"tos_sep2015":true}',
  `ts_banned` int(11) NOT NULL,
  `trusted` tinyint(1) NOT NULL DEFAULT 0,
  `confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `email_notifications` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `login` (`email`,`password_hash`),
  KEY `autologin` (`id`,`session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `voucher_redemptions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `voucher_redemptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `voucher_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `character_id` int(11) NOT NULL DEFAULT 0,
  `ts_redeemed` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_unique` (`voucher_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vouchers`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vouchers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `rewards` text NOT NULL,
  `uses_max` int(11) NOT NULL DEFAULT 1,
  `uses_current` int(11) NOT NULL DEFAULT 0,
  `min_level` int(11) NOT NULL DEFAULT 0,
  `locale` varchar(10) NOT NULL DEFAULT '',
  `user_id` int(11) NOT NULL DEFAULT 0,
  `ts_start` int(11) NOT NULL DEFAULT 0,
  `ts_end` int(11) NOT NULL DEFAULT 0,
  `ts_creation` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `work`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `work` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `character_id` int(11) NOT NULL,
  `work_offer_id` varchar(64) NOT NULL,
  `status` smallint(3) NOT NULL,
  `duration` int(11) NOT NULL,
  `ts_complete` int(11) NOT NULL,
  `rewards` mediumtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `work` (`character_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `worldboss_attack`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `worldboss_attack` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `worldboss_event_id` int(11) NOT NULL DEFAULT 0,
  `character_id` int(11) NOT NULL DEFAULT 0,
  `battle_id` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(4) NOT NULL DEFAULT 0,
  `ts_complete` int(11) NOT NULL DEFAULT 0,
  `duration` int(11) NOT NULL DEFAULT 0,
  `duration_raw` int(11) NOT NULL DEFAULT 0,
  `total_damage` int(11) NOT NULL DEFAULT 0,
  `iterations` int(11) NOT NULL DEFAULT 1,
  `battle_ids` text DEFAULT NULL,
  `coin_reward` int(11) NOT NULL DEFAULT 0,
  `xp_reward` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_wba_event` (`worldboss_event_id`),
  KEY `idx_wba_char` (`character_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `worldboss_event`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `worldboss_event` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `identifier` varchar(255) NOT NULL DEFAULT '',
  `status` tinyint(4) NOT NULL DEFAULT 0,
  `stage` int(11) NOT NULL DEFAULT 1,
  `min_level` int(11) NOT NULL DEFAULT 1,
  `max_level` int(11) NOT NULL DEFAULT 999,
  `npc_identifier` varchar(255) NOT NULL DEFAULT '',
  `npc_hitpoints_total` bigint(20) NOT NULL DEFAULT 0,
  `npc_hitpoints_current` bigint(20) NOT NULL DEFAULT 0,
  `npc_strength_percent` smallint DEFAULT NULL,
  `npc_critical_percent` smallint DEFAULT NULL,
  `npc_dodge_percent` smallint DEFAULT NULL,
  `attack_count` int(11) NOT NULL DEFAULT 0,
  `ts_end` int(11) NOT NULL DEFAULT 0,
  `top_attacker_character_id` int(11) NOT NULL DEFAULT 0,
  `top_attacker_name` varchar(255) NOT NULL DEFAULT '',
  `top_attacker_count` int(11) NOT NULL DEFAULT 0,
  `winning_attacker_name` varchar(255) NOT NULL DEFAULT '',
  `reward_top_rank_item_identifier` varchar(255) NOT NULL DEFAULT '',
  `reward_top_pool_item_identifier` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_wb_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `worldboss_reward`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `worldboss_reward` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `worldboss_event_id` int(11) NOT NULL DEFAULT 0,
  `character_id` int(11) NOT NULL DEFAULT 0,
  `game_currency` int(11) NOT NULL DEFAULT 0,
  `xp` int(11) NOT NULL DEFAULT 0,
  `item_id` int(11) NOT NULL DEFAULT 0,
  `sidekick_item_id` int(11) NOT NULL DEFAULT 0,
  `quest_energy` int(11) NOT NULL DEFAULT 0,
  `training_sessions` int(11) NOT NULL DEFAULT 0,
  `rewards` text DEFAULT NULL,
  `claimed` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_worldboss_reward_owner` (`worldboss_event_id`,`character_id`),
  KEY `idx_wbr_event` (`worldboss_event_id`),
  KEY `idx_wbr_char` (`character_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-12 12:43:28
