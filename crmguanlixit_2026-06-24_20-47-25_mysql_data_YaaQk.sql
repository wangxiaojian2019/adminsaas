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
  `tenant_id` int(11) NOT NULL DEFAULT '1' COMMENT '租户ID',
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
INSERT INTO `admins` VALUES (1,1,'admin','e10adc3949ba59abbe56e057f20f883e','超级管理员',NULL,'高新科技产业园',1,0,1,'2026-01-01 09:00:00','总控中心指挥官','负责全园区 SaaS 平台底座所有宏观资产、配置和权限的最高控制权','2026-06-24 20:42:45','223.215.60.73',1),(2,1,'manager01','e10adc3949ba59abbe56e057f20f883e','招商总监-刘总',NULL,'高新科技产业园',2,0,1,'2026-01-02 10:00:00','招商部负责人','全面统筹客户线索、企业建档入驻契约、大厦去化热力图穿透流转',NULL,NULL,2),(3,1,'finance01','e10adc3949ba59abbe56e057f20f883e','财务主管-特特',NULL,'高新科技产业园',3,0,1,'2026-01-02 11:00:00','业财核销组长','统扣全园区租金、物业能耗账单催收，以及退租清算单的最终退款结清',NULL,NULL,3),(101,1,'13755667788','e10adc3949ba59abbe56e057f20f883e','张三',NULL,'高新科技产业园',4,0,1,'2026-01-05 08:30:00','安保专员','负责全园区安防网格防区定点巡更打卡，隐患即时上报中控调度室','2026-06-12 12:17:41','183.161.183.48',4),(102,1,'13899887766','e10adc3949ba59abbe56e057f20f883e','李四',NULL,'高新科技产业园',4,0,1,'2026-01-05 08:30:00','保洁组长','负责公共区域绿化清洁、生活垃圾定点消杀打卡监督','2026-06-12 13:50:58','183.161.183.48',4),(103,1,'13911223344','e10adc3949ba59abbe56e057f20f883e','王五',NULL,'高新科技产业园',4,0,1,'2026-01-05 08:30:00','工程维修','承接中控派发的所有强弱电、空调漏水、物理设施破坏的现场抢修打卡',NULL,NULL,4),(104,1,'13455667788','e10adc3949ba59abbe56e057f20f883e','荔湾三','1345566778','高新科技产业园',6,2,1,'2026-06-14 22:01:15',NULL,NULL,NULL,NULL,0);
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
  `tenant_id` int(11) NOT NULL DEFAULT '1' COMMENT '租户ID',
  `name` varchar(100) NOT NULL,
  `property_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1:商业办公 2:长租公寓 3:商铺底商 4:产业园区',
  `total_floors` int(11) NOT NULL DEFAULT '1',
  `building_area` decimal(10,2) NOT NULL DEFAULT '0.00',
  `manager_name` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL COMMENT '软删除时间戳',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `buildings`
--

LOCK TABLES `buildings` WRITE;
/*!40000 ALTER TABLE `buildings` DISABLE KEYS */;
INSERT INTO `buildings` VALUES (1,1,'京东大厦',1,5,25000.00,'张经理','2026-01-01 08:00:00',NULL),(2,1,'腾讯大厦',1,5,35000.00,'陈主管','2026-01-02 08:00:00',NULL),(3,1,'测试',1,1,100.00,'','2026-06-12 14:16:04',NULL),(4,1,'拓普大厦',1,18,36000.00,'湖先生','2026-06-20 00:14:03',NULL),(5,1,'甬城公寓',2,8,16000.00,'湖湖先生','2026-06-20 00:39:06',NULL);
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
  `tenant_id` int(11) NOT NULL DEFAULT '1' COMMENT '租户ID',
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
INSERT INTO `checkouts` VALUES (2,1,3,3,40000.00,1500.00,2000.00,36500.00,'测试使用',0,NULL,'2026-06-15 16:17:55'),(3,1,4,6,7620.00,0.00,0.00,7620.00,'',0,NULL,'2026-06-20 00:10:23'),(4,1,5,7,0.00,0.00,0.00,0.00,'',1,NULL,'2026-06-20 00:32:07');
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COMMENT='合同文书与电子归档中心';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contract_docs`
--

LOCK TABLES `contract_docs` WRITE;
/*!40000 ALTER TABLE `contract_docs` DISABLE KEYS */;
INSERT INTO `contract_docs` VALUES (1,7,'/uploads/elec_ht_7.pdf',NULL,'2026-06-20 15:23:13','2026-06-20 15:23:13'),(2,10,'/uploads/elec_ht_10.pdf',NULL,'2026-06-21 22:06:01','2026-06-21 22:06:01');
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
  `tenant_id` int(11) NOT NULL DEFAULT '1' COMMENT '租户ID',
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
  `water_meter` decimal(10,2) DEFAULT '0.00' COMMENT '初始交房水表读数',
  `electric_meter` decimal(10,2) DEFAULT '0.00' COMMENT '初始交房电表读数',
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
  `deleted_at` datetime DEFAULT NULL COMMENT '软删除时间戳',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_contract` (`tenant_id`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contracts`
--

LOCK TABLES `contracts` WRITE;
/*!40000 ALTER TABLE `contracts` DISABLE KEYS */;
INSERT INTO `contracts` VALUES (1,1,0,0,'HT20260611001',1,1,'2026-01-15','2026-01-15','2027-01-14',15000.00,30000.00,0.00,0.00,0,NULL,NULL,NULL,'2026-01-15 15:00:00',1500.00,3,'2026-07-15','粤B12345固定车位','2026-06-20 15:19:24',NULL),(2,1,0,0,'HT20260611002',2,2,'2026-02-01','2026-02-01','2027-01-31',12000.00,24000.00,0.00,0.00,1,NULL,NULL,NULL,'2026-01-20 10:00:00',1200.00,3,'2026-08-01','无',NULL,NULL),(3,1,0,0,'HT20260611003',3,4,'2026-03-01','2026-03-01','2028-02-28',20000.00,40000.00,0.00,0.00,0,NULL,NULL,NULL,'2026-02-15 14:00:00',2000.00,3,'2026-09-01','粤B99999',NULL,NULL),(4,1,0,0,'HT20260620000145',6,3,'2026-06-20','2026-06-20','2027-06-19',2240.00,7620.00,0.00,0.00,0,NULL,NULL,NULL,'2026-06-20 00:01:15',300.00,3,NULL,'',NULL,NULL),(5,1,0,0,'HT20260620000612',7,10,'2026-06-20','2026-06-20','2027-06-19',2800.00,0.00,0.00,0.00,0,NULL,NULL,NULL,'2026-06-20 00:06:27',300.00,3,NULL,'',NULL,NULL),(6,1,0,0,'HT20260620004553',8,34,'2026-06-20','2026-06-20','2026-07-19',900.00,900.00,0.00,0.00,1,NULL,NULL,NULL,'2026-06-20 00:45:57',0.00,3,NULL,'',NULL,NULL),(7,1,1,1,'HT20260611001-变更260620',1,1,'2026-01-15','2026-01-15','2027-01-14',2000.00,30000.00,0.00,0.00,1,'/uploads/cert_6a363e631e441.jpg',NULL,NULL,'2026-06-20 15:19:24',2500.00,3,'2026-07-15',NULL,NULL,NULL),(8,1,0,0,'HT202606201545443432',1,13,'2026-06-20',NULL,'2027-07-19',5000.00,200.00,0.00,0.00,0,'/uploads/cert_6a36452592990.jpg',NULL,NULL,'2026-06-20 15:45:44',500.00,3,'2026-09-20','','2026-06-20 17:45:04',NULL),(9,1,8,3,'HT202606201545443432-变更260620',1,36,'2026-06-20','2026-06-20','2027-07-19',5000.00,200.00,0.00,0.00,1,'/uploads/cert_6a36611fde004.jpg',NULL,NULL,'2026-06-20 17:45:04',500.00,3,'2026-09-20',NULL,'2026-06-20 17:45:04',NULL),(10,1,0,0,'HT20260621220574',9,38,'2026-06-23',NULL,'2027-06-22',6000.00,3000.00,0.00,0.00,1,NULL,NULL,NULL,'2026-06-21 22:05:24',800.00,3,NULL,'',NULL,NULL);
/*!40000 ALTER TABLE `contracts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `decorations`
--

DROP TABLE IF EXISTS `decorations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `decorations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `apply_no` varchar(50) NOT NULL COMMENT '报备单号',
  `enterprise_id` int(11) NOT NULL COMMENT '关联入驻企业ID(enterprises.id)',
  `space_id` int(11) NOT NULL COMMENT '关联物理空间ID(spaces.id)',
  `start_date` date NOT NULL COMMENT '进场日期',
  `end_date` date NOT NULL COMMENT '预计完工日期',
  `total_days` int(11) NOT NULL COMMENT '核准工期天数',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0:待审核 1:施工中 2:延期审核 3:已完工 4:已驳回',
  `deposit` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '应缴押金',
  `manager` varchar(50) DEFAULT NULL COMMENT '现场负责人',
  `delay_reason` text COMMENT '二次延期说明',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_enterprise_id` (`enterprise_id`),
  KEY `idx_space_id` (`space_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='装修报备工期强关联管理表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `decorations`
--

LOCK TABLES `decorations` WRITE;
/*!40000 ALTER TABLE `decorations` DISABLE KEYS */;
/*!40000 ALTER TABLE `decorations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT '1' COMMENT '租户ID',
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
INSERT INTO `departments` VALUES (1,1,'总经办/核心决策层',0,0,'2026-06-13 08:58:20'),(2,1,'招商运营部',0,0,'2026-06-13 08:58:20'),(3,1,'财务核算中心',0,0,'2026-06-13 08:58:20'),(4,1,'后勤安保物业部',0,0,'2026-06-13 08:58:20');
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
  `tenant_id` int(11) NOT NULL DEFAULT '1' COMMENT '租户ID',
  `customer_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1:B2B企业租户 2:B2C个人租客',
  `name` varchar(100) NOT NULL,
  `contact_person` varchar(50) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `id_card_no` varchar(30) DEFAULT NULL COMMENT '个人租客身份证号',
  `emergency_contact` varchar(50) DEFAULT NULL COMMENT '紧急联系人',
  `emergency_phone` varchar(20) DEFAULT NULL COMMENT '紧急联系电话',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `password` varchar(32) DEFAULT 'e10adc3949ba59abbe56e057f20f883e' COMMENT '租户端登录密码',
  `industry` varchar(100) DEFAULT NULL COMMENT '所属行业',
  `last_login_time` datetime DEFAULT NULL COMMENT '最后活跃登录时间',
  `last_login_ip` varchar(50) DEFAULT NULL COMMENT '最后活跃登录IP',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enterprises`
--

LOCK TABLES `enterprises` WRITE;
/*!40000 ALTER TABLE `enterprises` DISABLE KEYS */;
INSERT INTO `enterprises` VALUES (1,1,1,'拓普检测技术有限公司','胡总','15888888888',NULL,NULL,NULL,'2026-01-10 14:00:00','e10adc3949ba59abbe56e057f20f883e','检验检测',NULL,NULL),(2,1,1,'华为技术有限公司','任总','13055555555',NULL,NULL,NULL,'2026-01-10 14:00:00','e10adc3949ba59abbe56e057f20f883e','硬核科技',NULL,NULL),(3,1,1,'滴滴出行华南分部','程维','13044444444',NULL,NULL,NULL,'2026-01-10 14:00:00','e10adc3949ba59abbe56e057f20f883e','智能交通',NULL,NULL),(4,1,1,'美团科技有限公司','王兴','13033333333',NULL,NULL,NULL,'2026-01-10 14:00:00','e10adc3949ba59abbe56e057f20f883e','本地生活',NULL,NULL),(5,1,1,'百度在线网络技术','李彦','13022222222',NULL,NULL,NULL,'2026-01-10 14:00:00','e10adc3949ba59abbe56e057f20f883e','AI大模型',NULL,NULL),(6,1,1,'宁波双港科创贸易有限公司','朱总','15888158158',NULL,NULL,NULL,'2026-06-20 00:01:15','e10adc3949ba59abbe56e057f20f883e','外贸',NULL,NULL),(7,1,1,'	 宁波双港科创贸易有限公司','胡总','15888058058',NULL,NULL,NULL,'2026-06-20 00:06:27','e10adc3949ba59abbe56e057f20f883e','贸易',NULL,NULL),(8,1,1,'张三','李四','12312341234',NULL,NULL,NULL,'2026-06-20 00:45:57','e10adc3949ba59abbe56e057f20f883e','自住',NULL,NULL),(9,1,1,'哆啦A梦有限公司','野比大雄','13766554433',NULL,NULL,NULL,'2026-06-21 22:05:24','e10adc3949ba59abbe56e057f20f883e','设计',NULL,NULL);
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
-- Table structure for table `fee_items`
--

DROP TABLE IF EXISTS `fee_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fee_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT '1' COMMENT '租户ID',
  `name` varchar(50) NOT NULL COMMENT '费项名称（如：办公房租、公寓租金、公摊电费、保洁费）',
  `calc_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '计算类型 1:固定金额 2:按面积 3:按表计抄表 4:阶梯计费',
  `default_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '默认单价',
  `unit` varchar(20) DEFAULT '元/月' COMMENT '计费单位',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COMMENT='灵活计费项目字典表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fee_items`
--

LOCK TABLES `fee_items` WRITE;
/*!40000 ALTER TABLE `fee_items` DISABLE KEYS */;
INSERT INTO `fee_items` VALUES (1,1,'园区厂房租金',2,0.00,'元/㎡/月','2026-06-23 15:02:16'),(2,1,'长租公寓租金',1,0.00,'元/月','2026-06-23 15:02:16'),(3,1,'商业用电费',3,1.20,'元/度','2026-06-23 15:02:16'),(4,1,'民用住宅电费',3,0.68,'元/度','2026-06-23 15:02:16'),(5,1,'物业综合管理费',2,5.00,'元/㎡/月','2026-06-23 15:02:16'),(6,1,'网络宽带增值服务',1,100.00,'元/月','2026-06-23 15:02:16');
/*!40000 ALTER TABLE `fee_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_items`
--

DROP TABLE IF EXISTS `inventory_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sku_code` varchar(50) NOT NULL COMMENT '物料编码',
  `name` varchar(100) NOT NULL COMMENT '物料名称',
  `category` varchar(50) DEFAULT NULL COMMENT '分类',
  `qty` int(11) NOT NULL DEFAULT '0' COMMENT '当前结余库存',
  `unit` varchar(20) NOT NULL COMMENT '单位',
  `avg_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '加权平均单价(动态计算)',
  `min_stock` int(11) NOT NULL DEFAULT '10' COMMENT '安全预警阈值',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_sku` (`sku_code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COMMENT='升级版库存资产台账表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_items`
--

LOCK TABLES `inventory_items` WRITE;
/*!40000 ALTER TABLE `inventory_items` DISABLE KEYS */;
INSERT INTO `inventory_items` VALUES (1,'WL-001','LED长条平板灯','照明五金',45,'套',22.50,10,'2026-06-23 18:54:47',NULL),(2,'WL-002','加厚防水生料带','水暖管材',8,'卷',1.20,20,'2026-06-23 18:54:47',NULL),(3,'WL-003','A4打印纸(500张)','办公耗材',15,'包',18.00,10,'2026-06-23 18:54:47',NULL);
/*!40000 ALTER TABLE `inventory_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_logs`
--

DROP TABLE IF EXISTS `inventory_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` tinyint(1) NOT NULL COMMENT '1:采购入库 2:工单领料出库',
  `sku_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL COMMENT '变动数量',
  `price` decimal(10,2) NOT NULL COMMENT '当时核算的单价',
  `total_cost` decimal(10,2) NOT NULL COMMENT '总成本/货值',
  `work_order_no` varchar(50) DEFAULT NULL COMMENT '关联工单号',
  `worker` varchar(50) DEFAULT NULL COMMENT '领料人',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='出入库流水核销表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_logs`
--

LOCK TABLES `inventory_logs` WRITE;
/*!40000 ALTER TABLE `inventory_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_records`
--

DROP TABLE IF EXISTS `inventory_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT '1' COMMENT '租户ID',
  `item_id` int(11) NOT NULL,
  `action_type` tinyint(1) NOT NULL COMMENT '1-采购入库 2-领用/消耗 3-借出 4-归还',
  `quantity` int(11) NOT NULL COMMENT '操作数量',
  `related_person` varchar(100) DEFAULT NULL COMMENT '关联人(外勤师傅/企业租户)',
  `expected_return_date` date DEFAULT NULL COMMENT '预计归还日期(借出时填写)',
  `remark` varchar(255) DEFAULT NULL COMMENT '事由备注或关联工单号',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COMMENT='出入库与借还台账';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_records`
--

LOCK TABLES `inventory_records` WRITE;
/*!40000 ALTER TABLE `inventory_records` DISABLE KEYS */;
INSERT INTO `inventory_records` VALUES (1,1,2,1,10,'系统管理员',NULL,'系统期初建账录入','2026-06-21 11:26:00'),(2,1,1,1,10,'张',NULL,'','2026-06-21 11:28:28'),(3,1,2,2,1,'[领用师傅] 王五',NULL,'','2026-06-21 11:47:50'),(4,1,1,3,1,'[领用师傅] 李四','2026-06-23','维修405灯泡','2026-06-21 14:52:34'),(5,1,2,2,1,'[领用师傅] 李四',NULL,'拖地使用','2026-06-21 14:57:45'),(6,1,1,3,1,'[企业主体] 拓普检测技术有限公司','2026-06-25','挂企业横幅需要','2026-06-21 15:18:08'),(7,1,2,2,1,'[领用师傅] 李四',NULL,'','2026-06-21 15:26:28');
/*!40000 ALTER TABLE `inventory_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `iot_command_logs`
--

DROP TABLE IF EXISTS `iot_command_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `iot_command_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `device_sn` varchar(100) NOT NULL,
  `command_type` varchar(50) NOT NULL COMMENT '指令特征码: open_door, power_off, reset',
  `operator_id` int(11) NOT NULL COMMENT '下发指令的控制台操作员ID',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0:请求已下发 1:设备已响应成功 2:执行失败/超时',
  `response_payload` text COMMENT '设备底层回执报文',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sn_status` (`device_sn`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='IoT指令审计控制台';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iot_command_logs`
--

LOCK TABLES `iot_command_logs` WRITE;
/*!40000 ALTER TABLE `iot_command_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `iot_command_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `iot_devices`
--

DROP TABLE IF EXISTS `iot_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `iot_devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT '1' COMMENT '租户ID',
  `space_id` int(11) NOT NULL DEFAULT '0' COMMENT '关联物理空间ID',
  `device_sn` varchar(100) NOT NULL COMMENT '设备唯一序列号/MAC地址',
  `device_type` tinyint(1) NOT NULL COMMENT '1:智能电表 2:智能水表 3:智能门锁',
  `brand` varchar(50) DEFAULT NULL COMMENT '设备厂商/品牌',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0:离线 1:在线 2:故障',
  `last_heartbeat` datetime DEFAULT NULL COMMENT '最后心跳时间',
  `extra_attributes` text COMMENT '额外配置参数（门锁动态密码、水电表当前读数缓存等，使用 JSON 文本存储）',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_device_sn` (`device_sn`),
  KEY `idx_tenant_space` (`tenant_id`,`space_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COMMENT='IoT 智能网联设备台账表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iot_devices`
--

LOCK TABLES `iot_devices` WRITE;
/*!40000 ALTER TABLE `iot_devices` DISABLE KEYS */;
INSERT INTO `iot_devices` VALUES (1,1,1,'MAC-00-1A-2B-3C-4D-5E',1,'正泰智能电表',1,NULL,NULL,'2026-06-23 19:02:13'),(2,1,1,'MAC-00-1A-2B-3C-4D-5F',2,'海康智能水表',1,NULL,NULL,'2026-06-23 19:02:13'),(3,1,2,'MAC-A1-B2-C3-D4-E5-F6',3,'大华闸机门禁',0,NULL,NULL,'2026-06-23 19:02:13'),(4,1,3,'MAC-FF-EE-DD-CC-BB-AA',1,'正泰智能电表',2,NULL,NULL,'2026-06-23 19:02:13');
/*!40000 ALTER TABLE `iot_devices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `iot_telemetry_logs`
--

DROP TABLE IF EXISTS `iot_telemetry_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `iot_telemetry_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `device_sn` varchar(100) NOT NULL COMMENT '设备SN码',
  `data_payload` json NOT NULL COMMENT '遥测核心数据包(JSON格式)',
  `reported_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '硬件实际上报时间',
  PRIMARY KEY (`id`),
  KEY `idx_device_time` (`device_sn`,`reported_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='IoT设备遥测时序归档表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iot_telemetry_logs`
--

LOCK TABLES `iot_telemetry_logs` WRITE;
/*!40000 ALTER TABLE `iot_telemetry_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `iot_telemetry_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lead_follow_ups`
--

DROP TABLE IF EXISTS `lead_follow_ups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lead_follow_ups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT '1' COMMENT '租户ID',
  `lead_id` int(11) NOT NULL,
  `operator_name` varchar(50) DEFAULT NULL COMMENT '跟进人',
  `content` text NOT NULL COMMENT '跟进内容',
  `intent_level` varchar(20) DEFAULT '中' COMMENT '意向度: 高, 中, 低, 无效',
  `next_follow_time` date DEFAULT NULL COMMENT '下次跟进时间',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lead_follow_ups`
--

LOCK TABLES `lead_follow_ups` WRITE;
/*!40000 ALTER TABLE `lead_follow_ups` DISABLE KEYS */;
INSERT INTO `lead_follow_ups` VALUES (1,1,101,'系统超管','初步电话沟通，客户对周边配套比较满意，但觉得租金单价略高于预算。','中','2026-06-15','2026-06-02 10:00:00'),(2,1,101,'张三','邀请客户现场勘察，实地查看了B座的几处空置房源，客户总监对挑高很满意，表示回去后上会讨论。','高','2026-06-20','2026-06-10 14:30:00'),(3,1,101,'业务员测试','客户对高层景观很满意，正在核对物业费明细，预计下周可带法务审合同。','高','2026-06-17','2026-06-13 21:49:39'),(4,1,102,'业务员测试','嫌租金略高，正在考虑周围的竞品园区，需要持续保持关系。','中','2026-06-16','2026-06-01 21:49:39'),(5,1,103,'业务员测试','客户出差了，电话没接，稍后再联系。','低','2026-06-21','2026-05-27 21:49:39'),(6,1,105,'主管测试','直接对接过老板，预算充足，安排这周末再次看场地。','高','2026-06-15','2026-06-12 21:49:39'),(7,1,104,'系统','客户有意向，但是还在考虑','中','2026-06-20','2026-06-15 21:10:14'),(8,1,104,'13455667788','客户说还在考虑','中',NULL,'2026-06-22 15:57:11');
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
  `tenant_id` int(11) NOT NULL DEFAULT '1' COMMENT '租户ID',
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
  `last_owner_id` int(11) DEFAULT '0' COMMENT '上一任负责人ID(用于防囤积冷却)',
  `drop_time` datetime DEFAULT NULL COMMENT '掉入公海的时间戳',
  `audit_status` tinyint(1) DEFAULT '1' COMMENT '审核状态 0:待审核 1:已通过 2:已驳回',
  `last_follow_time` datetime DEFAULT NULL COMMENT '最后跟进或审核通过时间，用于15天倒计时计算',
  `admin_id` int(11) NOT NULL DEFAULT '0' COMMENT '负责人ID',
  `last_track_time` datetime DEFAULT NULL COMMENT '最后一次心跳跟进时间',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_owner` (`tenant_id`,`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=106 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leads`
--

LOCK TABLES `leads` WRITE;
/*!40000 ALTER TABLE `leads` DISABLE KEYS */;
INSERT INTO `leads` VALUES (1,1,'大疆创新设备','采购部','15911112221',500.00,'线上获客',1,'2026-05-12 14:20:00','智能制造','91440300MA5EXXX001',0,0,NULL,1,NULL,0,NULL),(2,1,'元气森林研发','行政部','15911112222',300.00,'渠道中介',2,'2026-05-15 10:00:00','快消品','91440300MA5EXXX002',0,0,NULL,1,NULL,0,NULL),(101,1,'星动科技有限公司','马总','13800001111',500.00,'1',1,'2026-06-09 21:49:39',NULL,NULL,0,0,NULL,1,NULL,2,'2026-06-13 21:49:39'),(102,1,'云端创想文化传媒','林总监','13900002222',150.00,'2',1,'2026-05-25 21:49:39',NULL,NULL,0,0,NULL,1,NULL,2,'2026-06-01 21:49:39'),(103,1,'雷霆跨境电商贸易','陈经理','13700003333',300.00,'3',1,'2026-05-15 21:49:39',NULL,NULL,0,0,NULL,1,NULL,2,'2026-05-27 21:49:39'),(104,1,'极客时代软件研发','张工','13600004444',1000.00,'4',1,'2026-06-13 21:49:39',NULL,NULL,0,0,NULL,1,'2026-06-22 15:57:11',104,'2026-06-15 21:10:14'),(105,1,'绿洲生态农业发展','刘董','13500005555',800.00,'1',1,'2026-06-04 21:49:39',NULL,NULL,0,0,NULL,1,NULL,3,'2026-06-12 21:49:39');
/*!40000 ALTER TABLE `leads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `meeting_bookings`
--

DROP TABLE IF EXISTS `meeting_bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `meeting_bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_no` varchar(50) NOT NULL,
  `enterprise_id` int(11) NOT NULL COMMENT '强关联入驻企业ID',
  `room_id` int(11) NOT NULL COMMENT '关联会议室ID',
  `date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `duration` decimal(4,1) NOT NULL,
  `cost` decimal(10,2) NOT NULL DEFAULT '0.00',
  `topic` varchar(200) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0待审核 1已通过 2驳回 3已取消',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_enterprise` (`enterprise_id`),
  KEY `idx_room_date` (`room_id`,`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会议室预订防冲突强关联表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `meeting_bookings`
--

LOCK TABLES `meeting_bookings` WRITE;
/*!40000 ALTER TABLE `meeting_bookings` DISABLE KEYS */;
/*!40000 ALTER TABLE `meeting_bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `meeting_rooms`
--

DROP TABLE IF EXISTS `meeting_rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `meeting_rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT '1' COMMENT '租户ID',
  `name` varchar(100) NOT NULL,
  `capacity` int(11) NOT NULL,
  `free_hours` int(11) NOT NULL DEFAULT '0' COMMENT '每次预订免费时长(小时)',
  `price_per_hour` decimal(10,2) NOT NULL DEFAULT '0.00',
  `has_projector` tinyint(1) DEFAULT '0',
  `has_whiteboard` tinyint(1) DEFAULT '0',
  `has_video_conf` tinyint(1) DEFAULT '0',
  `status` varchar(20) DEFAULT 'idle',
  `deleted_at` datetime DEFAULT NULL COMMENT '软删除时间戳',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COMMENT='会议室资产物理表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `meeting_rooms`
--

LOCK TABLES `meeting_rooms` WRITE;
/*!40000 ALTER TABLE `meeting_rooms` DISABLE KEYS */;
INSERT INTO `meeting_rooms` VALUES (1,1,'V01 极客董事局',20,2,200.00,1,1,1,'idle',NULL),(2,1,'M02 创客洽谈室',8,1,50.00,0,1,0,'idle',NULL),(3,1,'M03 敏捷作战室',12,0,100.00,1,1,0,'active',NULL);
/*!40000 ALTER TABLE `meeting_rooms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `meters`
--

DROP TABLE IF EXISTS `meters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `meters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT '1' COMMENT '租户ID',
  `space_id` int(11) NOT NULL,
  `meter_type` tinyint(1) NOT NULL COMMENT '1:水表 2:电表',
  `current_reading` decimal(10,2) NOT NULL,
  `last_reading` decimal(10,2) DEFAULT '0.00' COMMENT '上次读数',
  `usage_amount` decimal(10,2) DEFAULT '0.00' COMMENT '本期用量差值',
  `is_billed` tinyint(1) DEFAULT '0' COMMENT '是否已生成财务账单',
  `record_month` varchar(20) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `meters`
--

LOCK TABLES `meters` WRITE;
/*!40000 ALTER TABLE `meters` DISABLE KEYS */;
INSERT INTO `meters` VALUES (1,1,110,2,123456.00,0.00,0.00,0,'2026-06','2026-06-10 23:09:18'),(2,1,108,2,100.00,0.00,0.00,0,'2026-11','2026-06-10 23:10:14'),(3,1,110,2,300.00,0.00,0.00,0,'2026-07','2026-06-10 23:10:50'),(4,1,36,2,39996.00,0.00,0.00,0,'2026-06','2026-06-21 22:12:07'),(5,1,36,2,47650.00,0.00,0.00,0,'2026-06','2026-06-21 23:25:05'),(6,1,2,2,999.00,0.00,0.00,0,'2026-06','2026-06-21 23:59:27'),(7,1,36,2,47659.00,0.00,0.00,0,'2026-06','2026-06-22 00:10:00');
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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COMMENT='租户消息触达通知表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,1,'打款凭证被驳回','您的账单(￥4500.00)凭证被财务驳回。原因：非打款截图。请重新上传。',1,'2026-06-19 21:16:20'),(2,1,'打款凭证被驳回','您的账单(￥45000.00)凭证被财务驳回。原因：截图不完整。请重新上传。',1,'2026-06-19 21:33:10'),(3,1,'打款凭证被驳回','您的账单(￥1500.00)凭证被财务驳回。原因：是不是传错了。请重新上传。',0,'2026-06-19 23:30:56'),(4,1,'仓库物资出借通知','园区后勤向您出借了 1 个 【扶梯】。 请注意于 2026-06-25 前归还。如有疑问请联系物业中心。',0,'2026-06-21 15:18:08'),(5,1,'新账单出账提醒','您的能耗费账单(￥47995.2)已生成，请在截止日期前进入服务门户处理。',0,'2026-06-21 22:12:07'),(6,1,'新账单出账提醒','您的能耗费账单(￥9184.8)已生成，请在截止日期前进入服务门户处理。',0,'2026-06-21 23:25:05'),(7,2,'新账单出账提醒','您的能耗费账单(￥1198.8)已生成，请在截止日期前进入服务门户处理。',0,'2026-06-21 23:59:27'),(8,1,'新账单出账提醒','您的能耗费账单(￥10.8)已生成，请在截止日期前进入服务门户处理。',0,'2026-06-22 00:10:00'),(9,0,'【SLA违规告警】紧急工单响应超时','工单 [京东大厦C座电梯困人应急响应] 已超出 15 分钟响应时效，目前已由外勤人员接单，请后勤主管介入复盘效能。',0,'2026-06-23 02:28:43');
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
  `tenant_id` int(11) NOT NULL DEFAULT '1' COMMENT '租户ID',
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
INSERT INTO `parking_vehicles` VALUES (1,1,'粤B88881',1,'地下A-001',2,'2026-01-01','2026-12-31',500.00,1,'2026-01-01 11:00:00'),(3,1,'浙B888888',1,'地下车库',1,'2026-06-12','2027-07-11',200.00,1,'2026-06-12 08:42:19');
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
  `tenant_id` int(11) NOT NULL DEFAULT '1' COMMENT '租户ID',
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
INSERT INTO `patrol_points` VALUES (101,1,'京东大厦 5F 顶层消防水箱防区','2026-01-10 10:00:00','京东大厦 5F 顶层消防水箱防区'),(102,1,'腾讯大厦 地下车库强电主控柜','2026-01-10 10:05:00','腾讯大厦 地下车库强电主控柜'),(103,1,'腾讯大厦909消防层消费设备','2026-06-16 19:32:51','腾讯大厦909消防层消费设备');
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
-- Table structure for table `payment_transactions`
--

DROP TABLE IF EXISTS `payment_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT '1',
  `receivable_id` int(11) NOT NULL COMMENT '关联应收账单ID',
  `enterprise_id` int(11) NOT NULL COMMENT '打款企业',
  `pay_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '本次打款金额',
  `receipt_url` varchar(255) DEFAULT NULL COMMENT '转账凭证截图',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0待审核 1已通过 2已驳回',
  `reject_reason` varchar(255) DEFAULT NULL COMMENT '驳回原因',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `audit_time` datetime DEFAULT NULL COMMENT '财务审核时间',
  PRIMARY KEY (`id`),
  KEY `idx_receivable` (`receivable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='财务打款核销流水表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_transactions`
--

LOCK TABLES `payment_transactions` WRITE;
/*!40000 ALTER TABLE `payment_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_transactions` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,0,'运营数据指挥舱',1,'/dashboard','Odometer',10),(2,0,'大厦与资产大盘',1,'/buildings','OfficeBuilding',20),(3,0,'房源资产精细库',1,'/spaces','School',30),(4,0,'车位月卡与收费',1,'/vehicles','Van',40),(5,0,'招商与线索中心',1,'/leads','User',50),(6,0,'企业户籍档案',1,'/enterprises','Memo',60),(7,0,'租务与合同中心',1,'/contracts','Document',70),(8,0,'业财一体化中心',1,'/finance','Money',80),(9,0,'智能安防巡检',1,'/patrol','Aim',90),(10,0,'基层服务人员管理',1,'/services','Service',100),(11,0,'报表与 BI 中心',1,'/reports','DataLine',110),(12,0,'系统与权限控制',1,'/system','Setting',999),(13,0,'仓库与物料',1,'/inventory','Box',120),(14,0,'外勤工单大盘',1,'/workOrder','Document',130),(15,0,'IoT 智能网联中心',1,'/iot','Cpu',95),(16,0,'计费策略配置',1,'/fee-config','Calculator',85),(17,0,'装修报备管理',1,'/decoration','Tools',135),(18,0,'共享会议室管网',1,'/meeting','Calendar',136);
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
  `tenant_id` int(11) NOT NULL DEFAULT '1' COMMENT '租户ID',
  `enterprise_id` int(11) NOT NULL,
  `space_id` int(11) NOT NULL,
  `bill_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1:租金 2:水费 3:电费 4:物业费 5:滞纳金',
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `paid_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '已安全核销的累计金额',
  `due_date` date NOT NULL,
  `is_paid` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0:未核销 1:已核销',
  `reject_reason` varchar(255) DEFAULT NULL COMMENT '财务驳回原因',
  `paid_time` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `payment_method` varchar(32) DEFAULT NULL COMMENT '支付方式:wechat/alipay/unionpay/transfer',
  `receipt_url` varchar(255) DEFAULT NULL COMMENT '转账凭证截图URL',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_paid` (`tenant_id`,`is_paid`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `receivables`
--

LOCK TABLES `receivables` WRITE;
/*!40000 ALTER TABLE `receivables` DISABLE KEYS */;
INSERT INTO `receivables` VALUES (1,1,1,1,1,15000.00,0.00,'2026-04-20',1,NULL,'2026-04-12 10:00:00','2026-04-10 08:00:00',NULL,NULL),(2,1,1,1,4,1500.00,0.00,'2026-04-20',1,NULL,'2026-04-12 10:05:00','2026-04-10 08:00:00',NULL,NULL),(3,1,2,2,1,12000.00,0.00,'2026-05-20',1,NULL,'2026-05-15 14:00:00','2026-05-10 08:00:00',NULL,NULL),(4,1,2,2,3,850.00,0.00,'2026-05-20',1,NULL,'2026-05-15 14:00:00','2026-05-10 08:00:00',NULL,NULL),(5,1,3,4,1,20000.00,0.00,'2026-06-20',0,NULL,NULL,'2026-06-10 08:00:00',NULL,NULL),(6,1,3,4,4,2000.00,0.00,'2026-06-20',1,NULL,'2026-06-13 08:27:16','2026-06-10 08:00:00','wechat',''),(7,1,1,1,1,15000.00,0.00,'2026-06-20',1,NULL,'2026-06-12 19:38:19','2026-06-10 08:00:00',NULL,NULL),(8,1,1,0,4,2400.00,0.00,'2026-06-19',1,NULL,'2026-06-12 10:15:32','2026-06-12 08:42:34',NULL,NULL),(9,1,1,1,1,15000.00,0.00,'2026-07-20',0,NULL,NULL,'2026-06-12 20:47:25',NULL,NULL),(10,1,1,1,4,1500.00,0.00,'2026-07-20',3,'是不是传错了',NULL,'2026-06-12 20:47:25','bank_transfer','/uploads/cert_6a35606a46647.jpg'),(11,1,1,1,3,450.50,0.00,'2026-07-20',0,NULL,NULL,'2026-06-12 20:47:25',NULL,NULL),(12,1,1,1,1,45000.00,0.00,'2026-06-22',0,NULL,NULL,'2026-06-15 14:39:34',NULL,NULL),(13,1,1,1,4,4500.00,0.00,'2026-06-22',0,NULL,NULL,'2026-06-15 14:39:34',NULL,NULL),(14,1,2,2,1,36000.00,0.00,'2026-06-22',0,NULL,NULL,'2026-06-15 14:39:34',NULL,NULL),(15,1,2,2,4,3600.00,0.00,'2026-06-22',0,NULL,NULL,'2026-06-15 14:39:34',NULL,NULL),(16,1,3,4,1,60000.00,0.00,'2026-06-22',0,NULL,NULL,'2026-06-15 14:39:34',NULL,NULL),(17,1,3,4,4,6000.00,0.00,'2026-06-22',0,NULL,NULL,'2026-06-15 14:39:34',NULL,NULL),(18,1,1,1,1,45000.00,0.00,'2026-06-22',3,'截图不完整',NULL,'2026-06-15 15:08:59','bank_transfer','/uploads/cert_6a3544f8ac84b.png'),(19,1,1,1,4,4500.00,0.00,'2026-06-22',3,'非打款截图',NULL,'2026-06-15 15:08:59','bank_transfer','/uploads/cert_6a350d6b487cb.png'),(20,1,2,2,1,36000.00,0.00,'2026-06-22',0,NULL,NULL,'2026-06-15 15:08:59',NULL,NULL),(21,1,2,2,4,3600.00,0.00,'2026-06-22',0,NULL,NULL,'2026-06-15 15:08:59',NULL,NULL),(22,1,3,4,1,60000.00,0.00,'2026-06-22',0,NULL,NULL,'2026-06-15 15:08:59',NULL,NULL),(23,1,3,4,4,6000.00,0.00,'2026-06-22',0,NULL,NULL,'2026-06-15 15:08:59',NULL,NULL),(24,1,1,1,1,6000.00,0.00,'2026-06-27',0,NULL,NULL,'2026-06-20 15:28:52',NULL,NULL),(25,1,1,1,4,7500.00,0.00,'2026-06-27',0,NULL,NULL,'2026-06-20 15:28:52',NULL,NULL),(26,1,1,13,6,200.00,0.00,'2026-06-20',0,NULL,NULL,'2026-06-20 15:45:44',NULL,NULL),(27,1,1,1,1,6000.00,0.00,'2026-06-27',0,NULL,NULL,'2026-06-20 16:08:51',NULL,NULL),(28,1,1,1,4,7500.00,0.00,'2026-06-27',0,NULL,NULL,'2026-06-20 16:08:51',NULL,NULL),(29,1,1,13,1,15000.00,0.00,'2026-06-27',0,NULL,NULL,'2026-06-20 16:08:51',NULL,NULL),(30,1,1,13,4,1500.00,0.00,'2026-06-27',0,NULL,NULL,'2026-06-20 16:08:51',NULL,NULL),(31,1,1,36,1,15000.00,0.00,'2026-06-28',0,NULL,NULL,'2026-06-21 00:00:09',NULL,NULL),(32,1,1,36,4,1500.00,0.00,'2026-06-28',0,NULL,NULL,'2026-06-21 00:00:09',NULL,NULL),(33,1,1,36,3,47995.20,0.00,'2026-06-28',0,NULL,NULL,'2026-06-21 22:12:07',NULL,NULL),(34,1,1,36,3,9184.80,0.00,'2026-06-28',0,NULL,NULL,'2026-06-21 23:25:05',NULL,NULL),(35,1,2,2,3,1198.80,0.00,'2026-06-28',0,NULL,NULL,'2026-06-21 23:59:27',NULL,NULL),(36,1,1,36,3,10.80,0.00,'2026-06-29',0,NULL,NULL,'2026-06-22 00:10:00',NULL,NULL);
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
INSERT INTO `role_permissions` VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),(1,11),(1,12),(1,13),(1,14),(1,15),(1,16),(1,17),(1,18),(2,1),(2,2),(2,3),(2,5),(2,6),(3,1),(3,6),(3,7),(3,8),(3,11),(6,2),(6,5),(7,5);
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
  `tenant_id` int(11) NOT NULL DEFAULT '1' COMMENT '租户ID',
  `role_name` varchar(50) NOT NULL COMMENT '角色名称',
  `data_scope` tinyint(1) NOT NULL DEFAULT '1' COMMENT '数据权限 1:本人 2:部门 3:全局',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `menu_ids` text COMMENT '分配的菜单ID组合',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,1,'超级管理员',3,'2026-06-10 15:20:37','1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18'),(2,1,'招商主管',2,'2026-06-10 15:20:37',NULL),(3,1,'财务专员',3,'2026-06-10 15:20:37',NULL),(4,1,'工程维修',1,'2026-06-10 15:20:37',NULL),(5,1,'总经理',3,'2026-06-10 23:06:34',NULL),(6,1,'招商业务员',1,'2026-06-14 21:58:45','2,5'),(7,1,'招商主管',2,'2026-06-14 21:59:07',NULL);
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
  `tenant_id` int(11) NOT NULL DEFAULT '1' COMMENT '租户ID',
  `building_name` varchar(100) NOT NULL,
  `floor` int(11) NOT NULL,
  `room_number` varchar(50) NOT NULL,
  `area` decimal(10,2) NOT NULL DEFAULT '0.00',
  `water_meter` decimal(10,2) DEFAULT '0.00' COMMENT '当前水表底数',
  `electric_meter` decimal(10,2) DEFAULT '0.00' COMMENT '当前电表底数',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0:空置 1:在租 2:维修 3:装修',
  `enterprise_name` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL COMMENT '软删除时间戳',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_space` (`tenant_id`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `spaces`
--

LOCK TABLES `spaces` WRITE;
/*!40000 ALTER TABLE `spaces` DISABLE KEYS */;
INSERT INTO `spaces` VALUES (1,1,'京东大厦',1,'101',150.00,0.00,0.00,1,'拓普检测技术有限公司','2026-01-05 10:00:00',NULL),(2,1,'京东大厦',1,'102',120.00,0.00,0.00,1,'华为技术有限公司','2026-01-05 10:00:00',NULL),(3,1,'京东大厦',1,'103',158.00,0.00,0.00,0,NULL,'2026-01-05 10:00:00',NULL),(4,1,'京东大厦',2,'201',158.00,0.00,0.00,0,NULL,'2026-01-05 10:00:00',NULL),(5,1,'京东大厦',2,'202',1200.00,0.00,0.00,2,NULL,'2026-01-05 10:00:00',NULL),(6,1,'腾讯大厦',1,'101',310.00,0.00,0.00,1,'美团科技有限公司','2026-01-05 10:00:00',NULL),(7,1,'腾讯大厦',1,'102',180.00,0.00,0.00,1,'百度在线网络技术','2026-01-05 10:00:00',NULL),(12,1,'测试',3,'302',158.00,0.00,0.00,0,NULL,'2026-06-20 00:11:44',NULL),(13,1,'拓普大厦',1,'101',500.00,0.00,0.00,0,NULL,'2026-06-20 00:16:14',NULL),(15,1,'拓普大厦',2,'202',2000.00,0.00,0.00,0,NULL,'2026-06-20 00:16:58',NULL),(16,1,'拓普大厦',1,'102',1500.00,0.00,0.00,0,NULL,'2026-06-20 00:17:21',NULL),(19,1,'拓普大厦',3,'301',2000.00,0.00,0.00,3,NULL,'2026-06-20 00:17:56',NULL),(20,1,'拓普大厦',4,'401',200.00,0.00,0.00,2,NULL,'2026-06-20 00:18:08',NULL),(21,1,'拓普大厦',4,'402',300.00,0.00,0.00,3,NULL,'2026-06-20 00:18:19',NULL),(22,1,'拓普大厦',4,'403',400.00,0.00,0.00,0,NULL,'2026-06-20 00:18:32',NULL),(23,1,'拓普大厦',4,'408',550.00,0.00,0.00,0,NULL,'2026-06-20 00:18:44',NULL),(24,1,'拓普大厦',4,'405',550.00,0.00,0.00,0,NULL,'2026-06-20 00:19:00',NULL),(25,1,'拓普大厦',5,'523',2000.00,0.00,0.00,0,NULL,'2026-06-20 00:28:19',NULL),(26,1,'甬城公寓',1,'102',300.00,0.00,0.00,0,NULL,'2026-06-20 00:39:38',NULL),(27,1,'甬城公寓',2,'2001',50.00,0.00,0.00,0,NULL,'2026-06-20 00:40:33',NULL),(28,1,'甬城公寓',2,'2002',50.00,0.00,0.00,0,NULL,'2026-06-20 00:40:55',NULL),(29,1,'甬城公寓',2,'2003',50.00,0.00,0.00,0,NULL,'2026-06-20 00:41:13',NULL),(30,1,'甬城公寓',2,'2004',50.00,0.00,0.00,0,NULL,'2026-06-20 00:41:24',NULL),(31,1,'甬城公寓',2,'2005',50.00,0.00,0.00,0,NULL,'2026-06-20 00:42:22',NULL),(32,1,'甬城公寓',2,'2006',50.00,0.00,0.00,3,NULL,'2026-06-20 00:42:32',NULL),(33,1,'甬城公寓',2,'2007',50.00,0.00,0.00,2,NULL,'2026-06-20 00:42:44',NULL),(34,1,'甬城公寓',2,'2008',50.00,0.00,0.00,1,'张三','2026-06-20 00:42:58',NULL),(35,1,'甬城公寓',2,'2009',50.00,0.00,0.00,0,NULL,'2026-06-20 00:43:07',NULL),(36,1,'甬城公寓',2,'2010',50.00,0.00,0.00,1,'拓普检测技术有限公司','2026-06-20 00:43:18',NULL),(37,1,'甬城公寓',2,'2011',50.00,0.00,0.00,0,NULL,'2026-06-20 00:43:30',NULL),(38,1,'甬城公寓',1,'101',1700.00,0.00,0.00,1,'哆啦A梦有限公司','2026-06-20 00:43:57',NULL);
/*!40000 ALTER TABLE `spaces` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sys_attachments`
--

DROP TABLE IF EXISTS `sys_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sys_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT '1',
  `uploader_type` varchar(20) NOT NULL COMMENT '上传端：admin/tenant/worker',
  `uploader_id` int(11) NOT NULL DEFAULT '0' COMMENT '上传者ID',
  `original_name` varchar(255) NOT NULL COMMENT '原始文件名',
  `file_url` varchar(500) NOT NULL COMMENT '物理或云端访问路径',
  `file_size` int(11) NOT NULL COMMENT '文件大小(Byte)',
  `file_ext` varchar(20) NOT NULL COMMENT '文件后缀',
  `mime_type` varchar(100) DEFAULT NULL COMMENT 'MIME类型',
  `storage_driver` varchar(20) NOT NULL DEFAULT 'local' COMMENT '存储驱动：local/aliyun/tencent',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_driver` (`tenant_id`,`storage_driver`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='SaaS 全局附件与云存储资源中枢表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sys_attachments`
--

LOCK TABLES `sys_attachments` WRITE;
/*!40000 ALTER TABLE `sys_attachments` DISABLE KEYS */;
/*!40000 ALTER TABLE `sys_attachments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_configs`
--

DROP TABLE IF EXISTS `system_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_configs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(50) NOT NULL,
  `config_value` text,
  `remark` varchar(100) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_key` (`config_key`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='全局系统配置表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_configs`
--

LOCK TABLES `system_configs` WRITE;
/*!40000 ALTER TABLE `system_configs` DISABLE KEYS */;
INSERT INTO `system_configs` VALUES (1,'fee_config','{\"waterPrice\":5.5,\"electricityPrice\":1.2,\"billMode\":\"fixed\",\"lateFeeRate\":0.1,\"autoCutoff\":true}','计费策略全局配置','2026-06-23 19:15:08');
/*!40000 ALTER TABLE `system_configs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tenants`
--

DROP TABLE IF EXISTS `tenants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(100) NOT NULL COMMENT '商户/运营方公司名称',
  `contact_person` varchar(50) DEFAULT NULL COMMENT '主要联系人',
  `phone` varchar(20) DEFAULT NULL COMMENT '联系电话',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1:正常 0:停用',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='SaaS 租户/商户主表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenants`
--

LOCK TABLES `tenants` WRITE;
/*!40000 ALTER TABLE `tenants` DISABLE KEYS */;
INSERT INTO `tenants` VALUES (1,'高新科技产业园运营方','系统管理员','13800000000',1,'2026-06-23 15:02:14');
/*!40000 ALTER TABLE `tenants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `work_orders`
--

DROP TABLE IF EXISTS `work_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `work_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT '1' COMMENT '租户ID',
  `title` varchar(100) NOT NULL,
  `description` text,
  `reporter_name` varchar(50) NOT NULL,
  `handler_id` int(11) DEFAULT NULL COMMENT '处理人(维修工)ID',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1:待指派 2:处理中 3:待验收 4:已结单',
  `sla_breached` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'SLA是否违规超时(0:正常 1:接单超时 2:到场超时)',
  `priority` tinyint(1) DEFAULT '0' COMMENT '优先级：0-普通，1-紧急(P0)',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `accepted_at` datetime DEFAULT NULL COMMENT '接单时间戳',
  `arrived_at` datetime DEFAULT NULL COMMENT '到场打卡时间戳',
  `resolved_at` datetime DEFAULT NULL COMMENT '结单时间戳',
  `content` text COMMENT '工单详情/现场说明',
  `material_cost` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '工单消耗的物料总成本',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `work_orders`
--

LOCK TABLES `work_orders` WRITE;
/*!40000 ALTER TABLE `work_orders` DISABLE KEYS */;
INSERT INTO `work_orders` VALUES (1,1,'腾讯大厦3楼男厕漏水','【现场照片证物】: /uploads/cert_6a35606a46647.jpg\n水管爆裂，漫出洗手间，请求紧急支援','腾讯技术部-陈主管',0,1,0,1,'2026-06-23 02:19:36',NULL,NULL,NULL,NULL,0.00),(2,1,'京东大厦C座电梯困人应急响应','监控室发现C座货梯卡在4-5楼之间，需立即进行安抚与物理救援\n[结单备注]: H5移动端打卡完工','中控室自动预警',102,4,0,1,'2026-06-23 01:39:36','2026-06-23 02:28:43',NULL,'2026-06-23 02:28:56',NULL,0.00),(3,1,'拓普检测实验室空调不制冷','内机出风口滴水，显示E4故障码','拓普检测-胡总',103,2,0,0,'2026-06-23 00:24:36','2026-06-23 00:34:36','2026-06-23 00:54:36',NULL,NULL,0.00),(4,1,'园区南门道闸被外来车辆撞损','货车倒车碰坏道闸栏杆，车辆已被门岗拦截，需定损','门岗室-老李',101,2,0,1,'2026-06-22 23:24:36','2026-06-23 00:24:36','2026-06-23 00:29:36',NULL,NULL,0.00),(5,1,'甬城公寓2层走廊呕吐物清理','周末租客醉酒，走廊地毯需进行深度清洁除味\n[结单备注]: H5移动端打卡完工，已使用洗地机清洗完毕并喷洒除味剂','公寓管家-小王',102,4,0,0,'2026-06-22 02:24:36','2026-06-22 03:24:36','2026-06-22 04:24:36','2026-06-22 05:24:36',NULL,0.00);
/*!40000 ALTER TABLE `work_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `worker_notifications`
--

DROP TABLE IF EXISTS `worker_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `worker_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `worker_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COMMENT='外勤员工专属消息通道';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `worker_notifications`
--

LOCK TABLES `worker_notifications` WRITE;
/*!40000 ALTER TABLE `worker_notifications` DISABLE KEYS */;
INSERT INTO `worker_notifications` VALUES (1,102,'物资发放入账提醒','您已从仓库成功登记发放 1 个 【拖把】。',0,'2026-06-21 15:26:28'),(7,102,'调度中心新任务派发','任务大厅有新下发的工单 [京东大厦C座电梯困人应急响应] 已指定由您负责，请前往现场处置并在 H5 完工打卡。',1,'2026-06-23 02:28:43');
/*!40000 ALTER TABLE `worker_notifications` ENABLE KEYS */;
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

-- Dump completed on 2026-06-24 20:47:25
