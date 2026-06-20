-- MySQL dump 10.13  Distrib 5.7.44, for Linux (x86_64)
--
-- Host: localhost    Database: crmguanlixit
-- ------------------------------------------------------
-- Server version	5.7.44-log

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
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL COMMENT '登录账号',
  `password` varchar(255) NOT NULL COMMENT '登录密码',
  `real_name` varchar(50) NOT NULL COMMENT '真实姓名',
  `phone` varchar(20) DEFAULT NULL COMMENT '手机号',
  `company_name` varchar(100) DEFAULT '高新科技产业园' COMMENT '所属公司',
  `role_id` int(11) NOT NULL DEFAULT '0',
  `parent_id` int(11) DEFAULT '0' COMMENT '直属主管ID',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1:正常 0:封禁',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `position` varchar(50) DEFAULT NULL COMMENT '岗位职位',
  `responsibility` text COMMENT '岗位职责',
  `last_login_time` datetime DEFAULT NULL COMMENT '最后活跃登录时间',
  `last_login_ip` varchar(50) DEFAULT NULL COMMENT '最后活跃登录IP',
  `department_id` int(11) DEFAULT '0' COMMENT '所属部门ID',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=105 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
INSERT INTO `admins` VALUES (1,'admin','e10adc3949ba59abbe56e057f20f883e','超级管理员',NULL,'高新科技产业园',1,0,1,'2026-01-01 09:00:00','总控中心指挥官','负责全园区 SaaS 平台底座所有宏观资产、配置和权限的最高控制权','2026-06-12 14:28:01','183.161.183.48',1),(2,'manager01','e10adc3949ba59abbe56e057f20f883e','招商总监-刘总',NULL,'高新科技产业园',2,0,1,'2026-01-02 10:00:00','招商部负责人','全面统筹客户线索、企业建档入驻契约、大厦去化热力图穿透流转',NULL,NULL,2),(3,'finance01','e10adc3949ba59abbe56e057f20f883e','财务主管-特特',NULL,'高新科技产业园',3,0,1,'2026-01-02 11:00:00','业财核销组长','统扣全园区租金、物业能耗账单催收，以及退租清算单的最终退款结清',NULL,NULL,3),(101,'13755667788','e10adc3949ba59abbe56e057f20f883e','张三',NULL,'高新科技产业园',4,0,1,'2026-01-05 08:30:00','安保专员','负责全园区安防网格防区定点巡更打卡，隐患即时上报中控调度室','2026-06-12 12:17:41','183.161.183.48',4),(102,'13899887766','e10adc3949ba59abbe56e057f20f883e','李四',NULL,'高新科技产业园',4,0,1,'2026-01-05 08:30:00','保洁组长','负责公共区域绿化清洁、生活垃圾定点消杀打卡监督','2026-06-12 13:50:58','183.161.183.48',4),(103,'13911223344','e10adc3949ba59abbe56e057f20f883e','王五',NULL,'高新科技产业园',4,0,1,'2026-01-05 08:30:00','工程维修','承接中控派发的所有强弱电、空调漏水、物理设施破坏的现场抢修打卡',NULL,NULL,4),(104,'13455667788','e10adc3949ba59abbe56e057f20f883e','荔湾三','1345566778','高新科技产业园',6,2,1,'2026-06-14 22:01:15',NULL,NULL,NULL,NULL,0);
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `buildings`
--

DROP TABLE IF EXISTS `buildings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `buildings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `property_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1:商业办公 2:长租公寓 3:商铺底商 4:产业园区',
  `total_floors` int(11) NOT NULL DEFAULT '1',
  `building_area` decimal(10,2) NOT NULL DEFAULT '0.00',
  `manager_name` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `buildings`
--

LOCK TABLES `buildings` WRITE;
/*!40000 ALTER TABLE `buildings` DISABLE KEYS */;
INSERT INTO `buildings` VALUES (1,'京东大厦',1,5,25000.00,'张经理','2026-01-01 08:00:00'),(2,'腾讯大厦',1,5,35000.00,'陈主管','2026-01-02 08:00:00'),(3,'测试',1,1,100.00,'','2026-06-12 14:16:04'),(4,'拓普大厦',1,18,36000.00,'湖先生','2026-06-20 00:14:03'),(5,'甬城公寓',2,8,16000.00,'湖湖先生','2026-06-20 00:39:06');
/*!40000 ALTER TABLE `buildings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `checkouts`
--

DROP TABLE IF EXISTS `checkouts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `checkouts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contract_id` int(11) NOT NULL COMMENT '关联的原合同ID',
  `enterprise_id` int(11) NOT NULL COMMENT '退租企业ID',
  `refund_deposit` decimal(10,2) DEFAULT '0.00' COMMENT '应退还的原始押金',
  `deduct_rent` decimal(10,2) DEFAULT '0.00' COMMENT '扣除的违约租金/水电',
  `deduct_damage` decimal(10,2) DEFAULT '0.00' COMMENT '扣除的物理破坏物损费',
  `actual_refund` decimal(10,2) DEFAULT '0.00' COMMENT '最终实际打款退还总额',
  `remark` text COMMENT '退租清算说明及扣款原因',
  `status` tinyint(1) DEFAULT '0' COMMENT '0: 待财务打款, 1: 财务已结清',
  `paid_time` datetime DEFAULT NULL COMMENT '财务实际打款结清时间',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `checkouts`
--

LOCK TABLES `checkouts` WRITE;
/*!40000 ALTER TABLE `checkouts` DISABLE KEYS */;
INSERT INTO `checkouts` VALUES (2,3,3,40000.00,1500.00,2000.00,36500.00,'测试使用',0,NULL,'2026-06-15 16:17:55'),(3,4,6,7620.00,0.00,0.00,7620.00,'',0,NULL,'2026-06-20 00:10:23'),(4,5,7,0.00,0.00,0.00,0.00,'',1,NULL,'2026-06-20 00:32:07');
/*!40000 ALTER TABLE `checkouts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contract_docs`
--

DROP TABLE IF EXISTS `contract_docs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contract_docs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contract_id` int(11) NOT NULL COMMENT '关联的底层契约ID',
  `elec_contract_url` varchar(255) DEFAULT NULL COMMENT '系统生成的电子合同物理路径',
  `paper_contract_url` varchar(255) DEFAULT NULL COMMENT '线下盖章并上传的纸质扫描件物理路径',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_contract_id` (`contract_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='合同文书与电子归档中心';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contract_docs`
--

LOCK TABLES `contract_docs` WRITE;
/*!40000 ALTER TABLE `contract_docs` DISABLE KEYS */;
INSERT INTO `contract_docs` VALUES (1,7,'/uploads/elec_ht_7.pdf',NULL,'2026-06-20 15:23:13','2026-06-20 15:23:13');
/*!40000 ALTER TABLE `contract_docs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contracts`
--

DROP TABLE IF EXISTS `contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contracts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT '0' COMMENT '父合同ID(0为首签，用于追溯扩缩租和搬迁历史)',
  `alteration_type` tinyint(1) DEFAULT '0' COMMENT '变更类型：0-新签 1-扩租 2-缩租 3-搬迁',
  `contract_no` varchar(50) NOT NULL COMMENT '合同编号',
  `enterprise_id` int(11) NOT NULL COMMENT '企业ID',
  `space_id` int(11) NOT NULL COMMENT '空间ID',
  `start_date` date NOT NULL,
  `billing_start_date` date DEFAULT NULL COMMENT '财务实际计费起始日(解耦物理入驻日，用于重叠免租期)',
  `end_date` date NOT NULL,
  `monthly_rent` decimal(10,2) NOT NULL DEFAULT '0.00',
  `deposit` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1:生效中 0:已退租',
  `scanned_file_url` varchar(255) DEFAULT NULL COMMENT '纸质合同/补充协议扫描件存档',
  `elec_contract_url` varchar(255) DEFAULT NULL COMMENT '电子合同路径',
  `paper_contract_url` varchar(255) DEFAULT NULL COMMENT '纸质合同扫描件',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `property_fee` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '每月物业费',
  `payment_cycle` int(11) DEFAULT '3' COMMENT '交租周期(月)',
  `next_bill_date` date DEFAULT NULL COMMENT '下期账单生成日',
  `vehicle_info` varchar(255) DEFAULT NULL COMMENT '车辆及车位信息备注',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contracts`
--

LOCK TABLES `contracts` WRITE;
/*!40000 ALTER TABLE `contracts` DISABLE KEYS */;
INSERT INTO `contracts` VALUES (1,0,0,'HT20260611001',1,1,'2026-01-15','2026-01-15','2027-01-14',15000.00,30000.00,0,NULL,NULL,NULL,'2026-01-15 15:00:00',1500.00,3,'2026-07-15','粤B12345固定车位','2026-06-20 15:19:24'),(2,0,0,'HT20260611002',2,2,'2026-02-01','2026-02-01','2027-01-31',12000.00,24000.00,1,NULL,NULL,NULL,'2026-01-20 10:00:00',1200.00,3,'2026-08-01','无',NULL),(3,0,0,'HT20260611003',3,4,'2026-03-01','2026-03-01','2028-02-28',20000.00,40000.00,0,NULL,NULL,NULL,'2026-02-15 14:00:00',2000.00,3,'2026-09-01','粤B99999',NULL),(4,0,0,'HT20260620000145',6,3,'2026-06-20','2026-06-20','2027-06-19',2240.00,7620.00,0,NULL,NULL,NULL,'2026-06-20 00:01:15',300.00,3,NULL,'',NULL),(5,0,0,'HT20260620000612',7,10,'2026-06-20','2026-06-20','2027-06-19',2800.00,0.00,0,NULL,NULL,NULL,'2026-06-20 00:06:27',300.00,3,NULL,'',NULL),(6,0,0,'HT20260620004553',8,34,'2026-06-20','2026-06-20','2026-07-19',900.00,900.00,1,NULL,NULL,NULL,'2026-06-20 00:45:57',0.00,3,NULL,'',NULL),(7,1,1,'HT20260611001-变更260620',1,1,'2026-01-15','2026-01-15','2027-01-14',2000.00,30000.00,1,'/uploads/cert_6a363e631e441.jpg',NULL,NULL,'2026-06-20 15:19:24',2500.00,3,'2026-07-15',NULL,NULL),(8,0,0,'HT202606201545443432',1,13,'2026-06-20',NULL,'2027-07-19',5000.00,200.00,0,'/uploads/cert_6a36452592990.jpg',NULL,NULL,'2026-06-20 15:45:44',500.00,3,'2026-09-20','','2026-06-20 17:45:04'),(9,8,3,'HT202606201545443432-变更260620',1,36,'2026-06-20','2026-06-20','2027-07-19',5000.00,200.00,1,'/uploads/cert_6a36611fde004.jpg',NULL,NULL,'2026-06-20 17:45:04',500.00,3,'2026-06-20',NULL,'2026-06-20 17:45:04');
/*!40000 ALTER TABLE `contracts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT '部门名称',
  `parent_id` int(11) DEFAULT '0' COMMENT '父级部门ID',
  `leader_id` int(11) DEFAULT '0' COMMENT '部门负责人(admin_id)',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` VALUES (1,'总经办/核心决策层',0,0,'2026-06-13 08:58:20'),(2,'招商运营部',0,0,'2026-06-13 08:58:20'),(3,'财务核算中心',0,0,'2026-06-13 08:58:20'),(4,'后勤安保物业部',0,0,'2026-06-13 08:58:20');
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enterprises`
--

DROP TABLE IF EXISTS `enterprises`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `enterprises` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `contact_person` varchar(50) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `password` varchar(32) DEFAULT 'e10adc3949ba59abbe56e057f20f883e' COMMENT '租户端登录密码',
  `industry` varchar(100) DEFAULT NULL COMMENT '所属行业',
  `last_login_time` datetime DEFAULT NULL COMMENT '最后活跃登录时间',
  `last_login_ip` varchar(50) DEFAULT NULL COMMENT '最后活跃登录IP',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enterprises`
--

LOCK TABLES `enterprises` WRITE;
/*!40000 ALTER TABLE `enterprises` DISABLE KEYS */;
INSERT INTO `enterprises` VALUES (1,'拓普检测技术有限公司','胡总','15888888888','2026-01-10 14:00:00','e10adc3949ba59abbe56e057f20f883e','检验检测',NULL,NULL),(2,'华为技术有限公司','任总','13055555555','2026-01-10 14:00:00','e10adc3949ba59abbe56e057f20f883e','硬核科技',NULL,NULL),(3,'滴滴出行华南分部','程维','13044444444','2026-01-10 14:00:00','e10adc3949ba59abbe56e057f20f883e','智能交通',NULL,NULL),(4,'美团科技有限公司','王兴','13033333333','2026-01-10 14:00:00','e10adc3949ba59abbe56e057f20f883e','本地生活',NULL,NULL),(5,'百度在线网络技术','李彦','13022222222','2026-01-10 14:00:00','e10adc3949ba59abbe56e057f20f883e','AI大模型',NULL,NULL),(6,'宁波双港科创贸易有限公司','朱总','15888158158','2026-06-20 00:01:15','e10adc3949ba59abbe56e057f20f883e','外贸',NULL,NULL),(7,'	 宁波双港科创贸易有限公司','胡总','15888058058','2026-06-20 00:06:27','e10adc3949ba59abbe56e057f20f883e','贸易',NULL,NULL),(8,'张三','李四','12312341234','2026-06-20 00:45:57','e10adc3949ba59abbe56e057f20f883e','自住',NULL,NULL);
/*!40000 ALTER TABLE `enterprises` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `export_audit_logs`
--

DROP TABLE IF EXISTS `export_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `export_audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL COMMENT '操作员ID',
  `admin_name` varchar(50) NOT NULL COMMENT '操作员姓名',
  `module_name` varchar(50) NOT NULL COMMENT '导出模块',
  `data_count` int(11) NOT NULL DEFAULT '0' COMMENT '导出条数',
  `ip_address` varchar(50) DEFAULT NULL COMMENT '操作IP',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `export_audit_logs`
--

LOCK TABLES `export_audit_logs` WRITE;
/*!40000 ALTER TABLE `export_audit_logs` DISABLE KEYS */;
INSERT INTO `export_audit_logs` VALUES (1,1,'未知人员','空间资产台账',2,'183.161.183.48','2026-06-11 20:57:46'),(2,1,'未知人员','中控调度工单池',0,'183.161.183.48','2026-06-11 20:58:12'),(3,1,'未知人员','空间资产台账',2,'115.213.60.209','2026-06-12 08:32:50'),(4,1,'未知人员','安防巡检网格配置',2,'183.161.183.48','2026-06-12 18:03:28'),(5,1,'未知人员','租务合同台账',3,'39.188.119.140','2026-06-12 19:38:32');
/*!40000 ALTER TABLE `export_audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lead_follow_ups`
--

DROP TABLE IF EXISTS `lead_follow_ups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lead_follow_ups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) NOT NULL,
  `operator_name` varchar(50) DEFAULT NULL COMMENT '跟进人',
  `content` text NOT NULL COMMENT '跟进内容',
  `intent_level` varchar(20) DEFAULT '中' COMMENT '意向度: 高, 中, 低, 无效',
  `next_follow_time` date DEFAULT NULL COMMENT '下次跟进时间',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lead_follow_ups`
--

LOCK TABLES `lead_follow_ups` WRITE;
/*!40000 ALTER TABLE `lead_follow_ups` DISABLE KEYS */;
INSERT INTO `lead_follow_ups` VALUES (1,101,'系统超管','初步电话沟通，客户对周边配套比较满意，但觉得租金单价略高于预算。','中','2026-06-15','2026-06-02 10:00:00'),(2,101,'张三','邀请客户现场勘察，实地查看了B座的几处空置房源，客户总监对挑高很满意，表示回去后上会讨论。','高','2026-06-20','2026-06-10 14:30:00'),(3,101,'业务员测试','客户对高层景观很满意，正在核对物业费明细，预计下周可带法务审合同。','高','2026-06-17','2026-06-13 21:49:39'),(4,102,'业务员测试','嫌租金略高，正在考虑周围的竞品园区，需要持续保持关系。','中','2026-06-16','2026-06-01 21:49:39'),(5,103,'业务员测试','客户出差了，电话没接，稍后再联系。','低','2026-06-21','2026-05-27 21:49:39'),(6,105,'主管测试','直接对接过老板，预算充足，安排这周末再次看场地。','高','2026-06-15','2026-06-12 21:49:39'),(7,104,'系统','客户有意向，但是还在考虑','中','2026-06-20','2026-06-15 21:10:14');
/*!40000 ALTER TABLE `lead_follow_ups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leads`
--

DROP TABLE IF EXISTS `leads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(100) NOT NULL,
  `contact_person` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `demand_area` decimal(10,2) DEFAULT '0.00',
  `source` varchar(50) DEFAULT NULL COMMENT '获客来源',
  `status` tinyint(1) DEFAULT '1' COMMENT '1:跟进中 2:已成单 3:已流失',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `industry` varchar(50) DEFAULT NULL COMMENT '所属行业',
  `tax_no` varchar(50) DEFAULT NULL COMMENT '统一社会信用代码',
  `owner_id` int(11) DEFAULT '0' COMMENT '归属业务员ID，0代表处于公共池',
  `audit_status` tinyint(1) DEFAULT '1' COMMENT '审核状态 0:待审核 1:已通过 2:已驳回',
  `last_follow_time` datetime DEFAULT NULL COMMENT '最后跟进或审核通过时间，用于15天倒计时计算',
  `admin_id` int(11) NOT NULL DEFAULT '0' COMMENT '负责人ID',
  `last_track_time` datetime DEFAULT NULL COMMENT '最后一次心跳跟进时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=106 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leads`
--

LOCK TABLES `leads` WRITE;
/*!40000 ALTER TABLE `leads` DISABLE KEYS */;
INSERT INTO `leads` VALUES (1,'大疆创新设备','采购部','15911112221',500.00,'线上获客',1,'2026-05-12 14:20:00','智能制造','91440300MA5EXXX001',0,1,NULL,0,NULL),(2,'元气森林研发','行政部','15911112222',300.00,'渠道中介',2,'2026-05-15 10:00:00','快消品','91440300MA5EXXX002',0,1,NULL,0,NULL),(101,'星动科技有限公司','马总','13800001111',500.00,'1',1,'2026-06-09 21:49:39',NULL,NULL,0,1,NULL,2,'2026-06-13 21:49:39'),(102,'云端创想文化传媒','林总监','13900002222',150.00,'2',1,'2026-05-25 21:49:39',NULL,NULL,0,1,NULL,2,'2026-06-01 21:49:39'),(103,'雷霆跨境电商贸易','陈经理','13700003333',300.00,'3',1,'2026-05-15 21:49:39',NULL,NULL,0,1,NULL,2,'2026-05-27 21:49:39'),(104,'极客时代软件研发','张工','13600004444',1000.00,'4',1,'2026-06-13 21:49:39',NULL,NULL,0,1,NULL,0,'2026-06-15 21:10:14'),(105,'绿洲生态农业发展','刘董','13500005555',800.00,'1',1,'2026-06-04 21:49:39',NULL,NULL,0,1,NULL,3,'2026-06-12 21:49:39');
/*!40000 ALTER TABLE `leads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `meters`
--

DROP TABLE IF EXISTS `meters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `meters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `space_id` int(11) NOT NULL,
  `meter_type` tinyint(1) NOT NULL COMMENT '1:水表 2:电表',
  `current_reading` decimal(10,2) NOT NULL,
  `record_month` varchar(20) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `meters`
--

LOCK TABLES `meters` WRITE;
/*!40000 ALTER TABLE `meters` DISABLE KEYS */;
INSERT INTO `meters` VALUES (1,110,2,123456.00,'2026-06','2026-06-10 23:09:18'),(2,108,2,100.00,'2026-11','2026-06-10 23:10:14'),(3,110,2,300.00,'2026-07','2026-06-10 23:10:50');
/*!40000 ALTER TABLE `meters` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `enterprise_id` int(11) NOT NULL COMMENT '接收消息的租户ID',
  `title` varchar(100) NOT NULL COMMENT '消息标题',
  `content` text NOT NULL COMMENT '消息正文',
  `is_read` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0:未读 1:已读',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_enterprise` (`enterprise_id`,`is_read`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COMMENT='租户消息触达通知表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,1,'打款凭证被驳回','您的账单(￥4500.00)凭证被财务驳回。原因：非打款截图。请重新上传。',1,'2026-06-19 21:16:20'),(2,1,'打款凭证被驳回','您的账单(￥45000.00)凭证被财务驳回。原因：截图不完整。请重新上传。',1,'2026-06-19 21:33:10'),(3,1,'打款凭证被驳回','您的账单(￥1500.00)凭证被财务驳回。原因：是不是传错了。请重新上传。',0,'2026-06-19 23:30:56');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `parking_vehicles`
--

DROP TABLE IF EXISTS `parking_vehicles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `parking_vehicles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plate_no` varchar(20) NOT NULL COMMENT '车牌号',
  `enterprise_id` int(11) NOT NULL COMMENT '所属企业ID',
  `parking_space_no` varchar(50) NOT NULL COMMENT '车位编号/区域',
  `card_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1: 月卡车辆, 2: 产权固定车',
  `start_date` date DEFAULT NULL COMMENT '有效期开始',
  `end_date` date DEFAULT NULL COMMENT '有效期结束',
  `monthly_fee` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '月租费/管理费',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1: 有效, 0: 停用/过期',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_plate` (`plate_no`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parking_vehicles`
--

LOCK TABLES `parking_vehicles` WRITE;
/*!40000 ALTER TABLE `parking_vehicles` DISABLE KEYS */;
INSERT INTO `parking_vehicles` VALUES (1,'粤B88881',1,'地下A-001',2,'2026-01-01','2026-12-31',500.00,1,'2026-01-01 11:00:00'),(3,'浙B888888',1,'地下车库',1,'2026-06-12','2027-07-11',200.00,1,'2026-06-12 08:42:19');
/*!40000 ALTER TABLE `parking_vehicles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `patrol_points`
--

DROP TABLE IF EXISTS `patrol_points`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `patrol_points` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `point_name` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `location` varchar(100) NOT NULL COMMENT '巡检物理点位名称',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=104 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patrol_points`
--

LOCK TABLES `patrol_points` WRITE;
/*!40000 ALTER TABLE `patrol_points` DISABLE KEYS */;
INSERT INTO `patrol_points` VALUES (101,'京东大厦 5F 顶层消防水箱防区','2026-01-10 10:00:00','京东大厦 5F 顶层消防水箱防区'),(102,'腾讯大厦 地下车库强电主控柜','2026-01-10 10:05:00','腾讯大厦 地下车库强电主控柜'),(103,'腾讯大厦909消防层消费设备','2026-06-16 19:32:51','腾讯大厦909消防层消费设备');
/*!40000 ALTER TABLE `patrol_points` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `patrol_records`
--

DROP TABLE IF EXISTS `patrol_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `patrol_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `point_id` int(11) NOT NULL,
  `operator_name` varchar(50) NOT NULL,
  `is_normal` tinyint(1) NOT NULL DEFAULT '1',
  `remark` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patrol_records`
--

LOCK TABLES `patrol_records` WRITE;
/*!40000 ALTER TABLE `patrol_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `patrol_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT '0' COMMENT '父级ID(0为顶级菜单)',
  `name` varchar(50) NOT NULL COMMENT '权限名称/菜单标题',
  `type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1:左侧菜单模块 2:页面按钮/接口动作',
  `path` varchar(100) DEFAULT NULL COMMENT '前端路由路径(菜单专用)',
  `icon` varchar(50) DEFAULT NULL COMMENT '前端图标组件名',
  `sort` int(11) DEFAULT '0' COMMENT '排序权重',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,0,'运营数据指挥舱',1,'/dashboard','Odometer',10),(2,0,'大厦与资产大盘',1,'/buildings','OfficeBuilding',20),(3,0,'房源资产精细库',1,'/spaces','School',30),(4,0,'车位月卡与收费',1,'/vehicles','Van',40),(5,0,'招商与线索中心',1,'/leads','User',50),(6,0,'企业户籍档案',1,'/enterprises','Memo',60),(7,0,'租务与合同中心',1,'/contracts','Document',70),(8,0,'业财一体化中心',1,'/finance','Money',80),(9,0,'智能安防巡检',1,'/patrol','Aim',90),(10,0,'基层服务人员管理',1,'/services','Service',100),(11,0,'报表与 BI 中心',1,'/reports','DataLine',110),(12,0,'系统与权限控制',1,'/system','Setting',999);
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `receivables`
--

DROP TABLE IF EXISTS `receivables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `receivables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `enterprise_id` int(11) NOT NULL,
  `space_id` int(11) NOT NULL,
  `bill_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1:租金 2:水费 3:电费 4:物业费 5:滞纳金',
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `due_date` date NOT NULL,
  `is_paid` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0:未核销 1:已核销',
  `reject_reason` varchar(255) DEFAULT NULL COMMENT '财务驳回原因',
  `paid_time` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `payment_method` varchar(32) DEFAULT NULL COMMENT '支付方式:wechat/alipay/unionpay/transfer',
  `receipt_url` varchar(255) DEFAULT NULL COMMENT '转账凭证截图URL',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `receivables`
--

LOCK TABLES `receivables` WRITE;
/*!40000 ALTER TABLE `receivables` DISABLE KEYS */;
INSERT INTO `receivables` VALUES (1,1,1,1,15000.00,'2026-04-20',1,NULL,'2026-04-12 10:00:00','2026-04-10 08:00:00',NULL,NULL),(2,1,1,4,1500.00,'2026-04-20',1,NULL,'2026-04-12 10:05:00','2026-04-10 08:00:00',NULL,NULL),(3,2,2,1,12000.00,'2026-05-20',1,NULL,'2026-05-15 14:00:00','2026-05-10 08:00:00',NULL,NULL),(4,2,2,3,850.00,'2026-05-20',1,NULL,'2026-05-15 14:00:00','2026-05-10 08:00:00',NULL,NULL),(5,3,4,1,20000.00,'2026-06-20',0,NULL,NULL,'2026-06-10 08:00:00',NULL,NULL),(6,3,4,4,2000.00,'2026-06-20',1,NULL,'2026-06-13 08:27:16','2026-06-10 08:00:00','wechat',''),(7,1,1,1,15000.00,'2026-06-20',1,NULL,'2026-06-12 19:38:19','2026-06-10 08:00:00',NULL,NULL),(8,1,0,4,2400.00,'2026-06-19',1,NULL,'2026-06-12 10:15:32','2026-06-12 08:42:34',NULL,NULL),(9,1,1,1,15000.00,'2026-07-20',0,NULL,NULL,'2026-06-12 20:47:25',NULL,NULL),(10,1,1,4,1500.00,'2026-07-20',3,'是不是传错了',NULL,'2026-06-12 20:47:25','bank_transfer','/uploads/cert_6a35606a46647.jpg'),(11,1,1,3,450.50,'2026-07-20',0,NULL,NULL,'2026-06-12 20:47:25',NULL,NULL),(12,1,1,1,45000.00,'2026-06-22',0,NULL,NULL,'2026-06-15 14:39:34',NULL,NULL),(13,1,1,4,4500.00,'2026-06-22',0,NULL,NULL,'2026-06-15 14:39:34',NULL,NULL),(14,2,2,1,36000.00,'2026-06-22',0,NULL,NULL,'2026-06-15 14:39:34',NULL,NULL),(15,2,2,4,3600.00,'2026-06-22',0,NULL,NULL,'2026-06-15 14:39:34',NULL,NULL),(16,3,4,1,60000.00,'2026-06-22',0,NULL,NULL,'2026-06-15 14:39:34',NULL,NULL),(17,3,4,4,6000.00,'2026-06-22',0,NULL,NULL,'2026-06-15 14:39:34',NULL,NULL),(18,1,1,1,45000.00,'2026-06-22',3,'截图不完整',NULL,'2026-06-15 15:08:59','bank_transfer','/uploads/cert_6a3544f8ac84b.png'),(19,1,1,4,4500.00,'2026-06-22',3,'非打款截图',NULL,'2026-06-15 15:08:59','bank_transfer','/uploads/cert_6a350d6b487cb.png'),(20,2,2,1,36000.00,'2026-06-22',0,NULL,NULL,'2026-06-15 15:08:59',NULL,NULL),(21,2,2,4,3600.00,'2026-06-22',0,NULL,NULL,'2026-06-15 15:08:59',NULL,NULL),(22,3,4,1,60000.00,'2026-06-22',0,NULL,NULL,'2026-06-15 15:08:59',NULL,NULL),(23,3,4,4,6000.00,'2026-06-22',0,NULL,NULL,'2026-06-15 15:08:59',NULL,NULL),(24,1,1,1,6000.00,'2026-06-27',0,NULL,NULL,'2026-06-20 15:28:52',NULL,NULL),(25,1,1,4,7500.00,'2026-06-27',0,NULL,NULL,'2026-06-20 15:28:52',NULL,NULL),(26,1,13,6,200.00,'2026-06-20',0,NULL,NULL,'2026-06-20 15:45:44',NULL,NULL),(27,1,1,1,6000.00,'2026-06-27',0,NULL,NULL,'2026-06-20 16:08:51',NULL,NULL),(28,1,1,4,7500.00,'2026-06-27',0,NULL,NULL,'2026-06-20 16:08:51',NULL,NULL),(29,1,13,1,15000.00,'2026-06-27',0,NULL,NULL,'2026-06-20 16:08:51',NULL,NULL),(30,1,13,4,1500.00,'2026-06-27',0,NULL,NULL,'2026-06-20 16:08:51',NULL,NULL);
/*!40000 ALTER TABLE `receivables` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),(1,11),(1,12),(2,1),(2,2),(2,3),(2,5),(2,6),(3,1),(3,6),(3,7),(3,8),(3,11),(6,2),(6,5),(7,5);
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL COMMENT '角色名称',
  `data_scope` tinyint(1) NOT NULL DEFAULT '1' COMMENT '数据权限 1:本人 2:部门 3:全局',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'超级管理员',3,'2026-06-10 15:20:37'),(2,'招商主管',2,'2026-06-10 15:20:37'),(3,'财务专员',3,'2026-06-10 15:20:37'),(4,'工程维修',1,'2026-06-10 15:20:37'),(5,'总经理',3,'2026-06-10 23:06:34'),(6,'招商业务员',1,'2026-06-14 21:58:45'),(7,'招商主管',2,'2026-06-14 21:59:07');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `spaces`
--

DROP TABLE IF EXISTS `spaces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `spaces` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `building_name` varchar(100) NOT NULL,
  `floor` int(11) NOT NULL,
  `room_number` varchar(50) NOT NULL,
  `area` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0:空置 1:在租 2:维修 3:装修',
  `enterprise_name` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `spaces`
--

LOCK TABLES `spaces` WRITE;
/*!40000 ALTER TABLE `spaces` DISABLE KEYS */;
INSERT INTO `spaces` VALUES (1,'京东大厦',1,'101',150.00,1,'拓普检测技术有限公司','2026-01-05 10:00:00'),(2,'京东大厦',1,'102',120.00,1,'华为技术有限公司','2026-01-05 10:00:00'),(3,'京东大厦',1,'103',158.00,0,NULL,'2026-01-05 10:00:00'),(4,'京东大厦',2,'201',158.00,0,NULL,'2026-01-05 10:00:00'),(5,'京东大厦',2,'202',1200.00,2,NULL,'2026-01-05 10:00:00'),(6,'腾讯大厦',1,'101',310.00,1,'美团科技有限公司','2026-01-05 10:00:00'),(7,'腾讯大厦',1,'102',180.00,1,'百度在线网络技术','2026-01-05 10:00:00'),(12,'测试',3,'302',158.00,0,NULL,'2026-06-20 00:11:44'),(13,'拓普大厦',1,'101',500.00,0,NULL,'2026-06-20 00:16:14'),(15,'拓普大厦',2,'202',2000.00,0,NULL,'2026-06-20 00:16:58'),(16,'拓普大厦',1,'102',1500.00,0,NULL,'2026-06-20 00:17:21'),(19,'拓普大厦',3,'301',2000.00,3,NULL,'2026-06-20 00:17:56'),(20,'拓普大厦',4,'401',200.00,2,NULL,'2026-06-20 00:18:08'),(21,'拓普大厦',4,'402',300.00,3,NULL,'2026-06-20 00:18:19'),(22,'拓普大厦',4,'403',400.00,0,NULL,'2026-06-20 00:18:32'),(23,'拓普大厦',4,'408',550.00,0,NULL,'2026-06-20 00:18:44'),(24,'拓普大厦',4,'405',550.00,0,NULL,'2026-06-20 00:19:00'),(25,'拓普大厦',5,'523',2000.00,0,NULL,'2026-06-20 00:28:19'),(26,'甬城公寓',1,'102',300.00,0,NULL,'2026-06-20 00:39:38'),(27,'甬城公寓',2,'2001',50.00,0,NULL,'2026-06-20 00:40:33'),(28,'甬城公寓',2,'2002',50.00,0,NULL,'2026-06-20 00:40:55'),(29,'甬城公寓',2,'2003',50.00,0,NULL,'2026-06-20 00:41:13'),(30,'甬城公寓',2,'2004',50.00,0,NULL,'2026-06-20 00:41:24'),(31,'甬城公寓',2,'2005',50.00,0,NULL,'2026-06-20 00:42:22'),(32,'甬城公寓',2,'2006',50.00,3,NULL,'2026-06-20 00:42:32'),(33,'甬城公寓',2,'2007',50.00,2,NULL,'2026-06-20 00:42:44'),(34,'甬城公寓',2,'2008',50.00,1,'张三','2026-06-20 00:42:58'),(35,'甬城公寓',2,'2009',50.00,0,NULL,'2026-06-20 00:43:07'),(36,'甬城公寓',2,'2010',50.00,1,'拓普检测技术有限公司','2026-06-20 00:43:18'),(37,'甬城公寓',2,'2011',50.00,0,NULL,'2026-06-20 00:43:30'),(38,'甬城公寓',1,'101',1700.00,0,NULL,'2026-06-20 00:43:57');
/*!40000 ALTER TABLE `spaces` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `work_orders`
--

DROP TABLE IF EXISTS `work_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `work_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `description` text,
  `reporter_name` varchar(50) NOT NULL,
  `handler_id` int(11) DEFAULT NULL COMMENT '处理人(维修工)ID',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1:待指派 2:处理中 3:待验收 4:已结单',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `work_orders`
--

LOCK TABLES `work_orders` WRITE;
/*!40000 ALTER TABLE `work_orders` DISABLE KEYS */;
INSERT INTO `work_orders` VALUES (1,'贝吉塔被打啦','\n\n【现场照片证物】: /uploads/cert_6a3137ef47cb8.jpg','拓普检测技术有限公司 (胡总)',NULL,1,'2026-06-16 19:48:12');
/*!40000 ALTER TABLE `work_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'crmguanlixit'
--

--
-- Dumping routines for database 'crmguanlixit'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-20 17:49:38
