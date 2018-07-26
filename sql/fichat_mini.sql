-- phpMyAdmin SQL Dump
-- version 4.7.7
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: 2018-07-26 06:58:36
-- 服务器版本： 5.7.21
-- PHP Version: 7.1.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `fichat_mini`
--
CREATE DATABASE IF NOT EXISTS `fichat_mini` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `fichat_mini`;

-- --------------------------------------------------------

--
-- 表的结构 `attention`
--

DROP TABLE IF EXISTS `attention`;
CREATE TABLE `attention` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT '用户id',
  `target_id` int(11) NOT NULL COMMENT '目标用户id',
  `confirm` int(11) DEFAULT '0' COMMENT '0粉丝   1互粉',
  `is_look` int(11) NOT NULL DEFAULT '1' COMMENT '是否查看关注圈  1:查看  2:不查看',
  `forbid_look` int(11) NOT NULL DEFAULT '1' COMMENT '禁止查看   1:不禁止  2:禁止',
  `is_new` tinyint(1) DEFAULT '1' COMMENT '是否新关注,1:是;0:否',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '关注的时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `badge`
--

DROP TABLE IF EXISTS `badge`;
CREATE TABLE `badge` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT '用户id',
  `funs` int(11) NOT NULL COMMENT '新粉丝数',
  `enemy` int(11) NOT NULL COMMENT '新敌人数',
  `friend` int(11) NOT NULL COMMENT '新好友请求、公会邀请、公会申请列表'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `balance_flow`
--

DROP TABLE IF EXISTS `balance_flow`;
CREATE TABLE `balance_flow` (
  `id` int(10) UNSIGNED NOT NULL COMMENT 'ID',
  `op_type` int(4) UNSIGNED NOT NULL DEFAULT '0' COMMENT '1: 充值 2: 体现 3: 发红包 4: 抢红包 5. 退换红包 6: 发悬赏 7: 提取悬赏佣金 8: 点击悬赏 9: 分享悬赏',
  `op_amount` float(10,2) NOT NULL DEFAULT '0.00' COMMENT '操作总额',
  `target_id` int(10) NOT NULL DEFAULT '0' COMMENT '目标ID',
  `user_order_id` int(11) NOT NULL DEFAULT '0' COMMENT '用户订单ID',
  `uid` int(10) UNSIGNED NOT NULL COMMENT '用户ID',
  `create_time` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '创建时间戳'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `best_number`
--

DROP TABLE IF EXISTS `best_number`;
CREATE TABLE `best_number` (
  `id` int(11) NOT NULL,
  `type` int(2) NOT NULL COMMENT '类型 1:用户；2：家族',
  `display_id` int(11) NOT NULL,
  `is_use` int(11) NOT NULL COMMENT '是否使用',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='保留的ID号';

-- --------------------------------------------------------

--
-- 表的结构 `exchange_ka_mi_record`
--

DROP TABLE IF EXISTS `exchange_ka_mi_record`;
CREATE TABLE `exchange_ka_mi_record` (
  `id` int(11) NOT NULL,
  `code` varchar(60) CHARACTER SET utf8mb4 NOT NULL COMMENT '编号',
  `uid` int(11) NOT NULL COMMENT '用户ID',
  `amount` int(11) NOT NULL COMMENT '兑换数量',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='兑换咖米记录表';

-- --------------------------------------------------------

--
-- 表的结构 `files`
--

DROP TABLE IF EXISTS `files`;
CREATE TABLE `files` (
  `id` int(11) NOT NULL COMMENT 'id, 主键',
  `url` varchar(600) NOT NULL DEFAULT '',
  `type` tinyint(4) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0:无类型, 1:jpg, 2:png, 3:gif',
  `file_index` int(4) UNSIGNED NOT NULL DEFAULT '0' COMMENT '图片位置',
  `create_time` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `files`
--

INSERT INTO `files` (`id`, `url`, `type`, `file_index`, `create_time`, `update_time`) VALUES
(2, 'RTC1532415030020I1532415030.jpg', 1, 0, 1532414733, 1532414733),
(3, 'RTC1532415030020I1532415030.jpg', 1, 0, 1532414826, 1532414826),
(4, 'RTC1532415030020I1532415030.jpg', 1, 0, 1532415030, 1532415030),
(5, 'RTC1532415102331I1532415102.jpg', 1, 0, 1532415102, 1532415102),
(9, 'RTC1532415030020I1532415030.jpg', 1, 0, 1532338745, 1532338745);

-- --------------------------------------------------------

--
-- 表的结构 `friend`
--

DROP TABLE IF EXISTS `friend`;
CREATE TABLE `friend` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT '用户id',
  `friend_id` int(11) NOT NULL COMMENT 'friendId',
  `create_time` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `friend`
--

INSERT INTO `friend` (`id`, `user_id`, `friend_id`, `create_time`, `update_time`) VALUES
(1, 1, 2, 1532587130, 1532587130),
(2, 2, 1, 1532587130, 1532587130);

-- --------------------------------------------------------

--
-- 表的结构 `money_flow`
--

DROP TABLE IF EXISTS `money_flow`;
CREATE TABLE `money_flow` (
  `id` int(10) UNSIGNED NOT NULL COMMENT 'ID',
  `op_type` tinyint(4) UNSIGNED NOT NULL DEFAULT '0' COMMENT '操作类型, 1: 充值 2: 体现 3: 发红包 4: 抢红包 5. 退换红包 6: 发悬赏 7: 提取悬赏佣金 8: 点击悬赏 9: 分享悬赏 10: 提取悬赏佣金'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- 表的结构 `oss_fdel_queue`
--

DROP TABLE IF EXISTS `oss_fdel_queue`;
CREATE TABLE `oss_fdel_queue` (
  `id` int(11) NOT NULL COMMENT '主键, 索引ID',
  `resource` varchar(255) DEFAULT NULL COMMENT '图片资源地址'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='OSS删除失败的图片';

-- --------------------------------------------------------

--
-- 表的结构 `pay_order`
--

DROP TABLE IF EXISTS `pay_order`;
CREATE TABLE `pay_order` (
  `id` int(11) NOT NULL COMMENT '用户订单表',
  `user_id` int(11) NOT NULL COMMENT '用户id',
  `balance` decimal(10,2) NOT NULL COMMENT '当时余额',
  `pay_channel` tinyint(1) NOT NULL COMMENT '支付渠道:1，支付宝；2，微信',
  `amount` decimal(10,2) NOT NULL COMMENT '订单金额',
  `status` int(1) NOT NULL DEFAULT '0' COMMENT '订单状态',
  `order_num` varchar(60) CHARACTER SET utf8 NOT NULL COMMENT '订单号',
  `callback_data` text CHARACTER SET utf8 COMMENT '回调信息',
  `consum_type` tinyint(1) NOT NULL COMMENT '交易类型 1,充值；2,提现,3:发朋友圈红包, 4: 发聊天红包,7:发悬赏',
  `pay_account` varchar(25) DEFAULT NULL COMMENT '支付渠道账户',
  `fee` float(10,2) UNSIGNED NOT NULL DEFAULT '0.00' COMMENT '手续费, 默认为0',
  `remark` varchar(60) CHARACTER SET utf8 DEFAULT NULL COMMENT '备注',
  `create_date` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `red_packet_gift_id` int(10) UNSIGNED DEFAULT '0' COMMENT '红包或礼物ID'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户订单表';

-- --------------------------------------------------------

--
-- 表的结构 `report`
--

DROP TABLE IF EXISTS `report`;
CREATE TABLE `report` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT '举报人ID',
  `by_report_id` int(11) NOT NULL DEFAULT '0' COMMENT '被举报ID',
  `type` int(2) NOT NULL DEFAULT '0' COMMENT '举报类型  1：家族 2：用户 3：动态',
  `reason` varchar(50) NOT NULL COMMENT '举报原因',
  `content` varchar(255) DEFAULT '' COMMENT '举报详细原因',
  `is_act` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0:未处理；1:已处理；2.已忽略',
  `creat_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '举报时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='非法行为举报';

-- --------------------------------------------------------

--
-- 表的结构 `reward_task`
--

DROP TABLE IF EXISTS `reward_task`;
CREATE TABLE `reward_task` (
  `id` int(10) NOT NULL COMMENT '任务ID',
  `owner_id` int(10) DEFAULT '0' COMMENT '任务发布人的user_id',
  `cover_pic` int(11) UNSIGNED DEFAULT '0' COMMENT '封面图片',
  `task_amount` float(8,2) DEFAULT '0.00' COMMENT '悬赏金额',
  `click_price` float(8,2) DEFAULT '0.00' COMMENT '点击赏金',
  `share_price` float(8,2) DEFAULT '0.00' COMMENT '分享赏金',
  `content` varchar(420) DEFAULT NULL COMMENT '内容(最大140个字)',
  `end_time` int(11) DEFAULT '0' COMMENT '结束时间',
  `click_count` int(8) DEFAULT '0' COMMENT '点击次数',
  `share_count` int(8) DEFAULT '0' COMMENT '分享次数',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态, 1:未完成进行中, 2:已完成, -1: 移除不显示',
  `balance` float(8,2) DEFAULT '0.00' COMMENT '余额',
  `create_time` int(11) DEFAULT '0' COMMENT '创建时间',
  `total_click_count` int(8) NOT NULL DEFAULT '0' COMMENT '总点击次数',
  `total_share_count` int(8) NOT NULL DEFAULT '0' COMMENT '总分享次数',
  `share_join_count` int(6) UNSIGNED NOT NULL DEFAULT '10' COMMENT '单次分享参与人数'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `reward_task`
--

INSERT INTO `reward_task` (`id`, `owner_id`, `cover_pic`, `task_amount`, `click_price`, `share_price`, `content`, `end_time`, `click_count`, `share_count`, `status`, `balance`, `create_time`, `total_click_count`, `total_share_count`, `share_join_count`) VALUES
(1, 1, 9, 100.00, 0.02, 3.00, '', 1532500689, 50, 20, 1, 100.00, 0, 0, 0, 10),
(2, 1, 9, 100.00, 0.02, 3.00, '', 1532500732, 50, 20, 1, 100.00, 0, 0, 0, 10),
(3, 2, 9, 100.00, 0.02, 3.00, '', 1532500732, 60, 20, 1, 99.92, 0, 2, 1, 10);

-- --------------------------------------------------------

--
-- 表的结构 `reward_task_record`
--

DROP TABLE IF EXISTS `reward_task_record`;
CREATE TABLE `reward_task_record` (
  `id` int(10) NOT NULL COMMENT '记录ID',
  `task_id` int(10) NOT NULL COMMENT '任务ID',
  `op_type` tinyint(1) NOT NULL COMMENT '操作类型, 1: 点击, 2: 分享',
  `join_members` varchar(120) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '分享加入到任务的用户ID',
  `uid` int(10) NOT NULL COMMENT '操作人id',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态, 0:无奖励操作, 1:有奖励操作',
  `op_time` int(11) NOT NULL DEFAULT '0' COMMENT '时间戳, 操作时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `reward_task_record`
--

INSERT INTO `reward_task_record` (`id`, `task_id`, `op_type`, `join_members`, `uid`, `status`, `op_time`) VALUES
(1, 3, 1, '', 1, 1, 0),
(2, 3, 1, '', 1, 1, 0),
(3, 3, 1, '', 1, 1, 0),
(4, 3, 1, '', 1, 1, 0),
(5, 3, 1, '', 1, 1, 0),
(6, 3, 1, '', 1, 1, 0),
(7, 3, 1, '', 1, 1, 0),
(8, 3, 1, '', 1, 1, 0),
(9, 3, 1, '', 1, 1, 0),
(10, 3, 1, '', 1, 1, 0),
(11, 3, 2, '[\"3\",\"1\"]', 2, 1, 0),
(13, 3, 1, '', 1, 1, 0);

-- --------------------------------------------------------

--
-- 表的结构 `system_config`
--

DROP TABLE IF EXISTS `system_config`;
CREATE TABLE `system_config` (
  `id` int(11) NOT NULL,
  `sms_code_expire_time` int(11) NOT NULL COMMENT '短信验证码的有效时间',
  `task_push_min_amount` decimal(10,2) NOT NULL COMMENT '红包任务推送的最低金额',
  `redpacket_push_min_amount` decimal(10,2) NOT NULL COMMENT '红包推送的最低金额',
  `redpacket_max_expire_time` int(11) NOT NULL COMMENT '定时红包最大有效时间',
  `withdraw_service_charge` float NOT NULL COMMENT '提现手续费',
  `withdraw_min_amount` decimal(10,2) NOT NULL COMMENT '提现每次最少金额',
  `withdraw_day_limit` decimal(10,2) NOT NULL COMMENT '每天提现的最高金额',
  `is_verify_phone_code` int(11) NOT NULL COMMENT '是否验证手机验证码 0否1是'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 表的结构 `system_dyn`
--

DROP TABLE IF EXISTS `system_dyn`;
CREATE TABLE `system_dyn` (
  `id` int(10) UNSIGNED NOT NULL COMMENT '主键ID',
  `type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '类型',
  `trigger_id` bigint(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '触发ID(红包, 任务)',
  `group_id` bigint(20) UNSIGNED NOT NULL DEFAULT '0' COMMENT '家族ID',
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '用户ID',
  `create_time` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '时间戳'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='系统消息表';

-- --------------------------------------------------------

--
-- 表的结构 `system_hot`
--

DROP TABLE IF EXISTS `system_hot`;
CREATE TABLE `system_hot` (
  `id` int(10) UNSIGNED NOT NULL COMMENT '主键ID',
  `type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '类型',
  `trigger_id` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '触发ID(红包, 任务)',
  `expo_num` int(10) NOT NULL DEFAULT '0' COMMENT '曝光数',
  `hot_num` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '热度',
  `create_time` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '时间戳'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='系统消息表';

-- --------------------------------------------------------

--
-- 表的结构 `system_money_flow`
--

DROP TABLE IF EXISTS `system_money_flow`;
CREATE TABLE `system_money_flow` (
  `id` int(10) UNSIGNED NOT NULL COMMENT 'ID',
  `op_type` tinyint(4) UNSIGNED NOT NULL DEFAULT '0' COMMENT '1: 充值 2: 提现 3: 发布系统悬赏任务 4: 退还系统悬赏任务',
  `op_amount` float(10,2) NOT NULL DEFAULT '0.00' COMMENT '操作总额',
  `target_id` int(10) NOT NULL DEFAULT '0' COMMENT '目标ID',
  `pay_channel` tinyint(4) NOT NULL DEFAULT '0' COMMENT '支付渠道 1: 支付宝, 2: 微信',
  `user_order_id` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '用户订单ID',
  `uid` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '用户ID',
  `create_time` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '创建时间戳'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `system_notice`
--

DROP TABLE IF EXISTS `system_notice`;
CREATE TABLE `system_notice` (
  `id` int(10) UNSIGNED NOT NULL COMMENT '主键ID',
  `type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '类型',
  `platform` tinyint(1) NOT NULL DEFAULT '0' COMMENT '平台',
  `trigger_id` int(10) UNSIGNED NOT NULL COMMENT '触发ID(红包, 任务)',
  `msg_id` varchar(30) CHARACTER SET utf8 NOT NULL COMMENT '推送ID(百度云)',
  `data` varchar(600) CHARACTER SET utf8 NOT NULL COMMENT '发送的消息数据',
  `send_time` int(11) NOT NULL COMMENT '时间戳'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='系统消息表';

-- --------------------------------------------------------

--
-- 表的结构 `tag`
--

DROP TABLE IF EXISTS `tag`;
CREATE TABLE `tag` (
  `id` int(8) NOT NULL COMMENT 'id',
  `tag` varchar(30) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '标签',
  `parent_id` int(8) NOT NULL DEFAULT '0' COMMENT '父标签ID, 默认为0, 即无父标签',
  `sys_rcmd` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否系统推荐'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- 表的结构 `title`
--

DROP TABLE IF EXISTS `title`;
CREATE TABLE `title` (
  `id` int(2) NOT NULL,
  `name` varchar(60) NOT NULL COMMENT '称号名称',
  `demand` int(11) NOT NULL COMMENT '解锁称号条件'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `user`
--

DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `id` int(11) NOT NULL,
  `openid` varchar(32) NOT NULL DEFAULT '',
  `unionid` varchar(32) NOT NULL DEFAULT '',
  `phone` varchar(11) CHARACTER SET utf8 DEFAULT '' COMMENT '手机号',
  `nickname` varchar(60) NOT NULL DEFAULT '' COMMENT '昵称',
  `gender` int(11) NOT NULL DEFAULT '1' COMMENT '性别 1->男 2->女',
  `wx_avatar` varchar(255) CHARACTER SET utf8 DEFAULT '' COMMENT '微信头像原始地址',
  `level` int(11) NOT NULL DEFAULT '1' COMMENT '等级',
  `exp` int(11) NOT NULL DEFAULT '0' COMMENT '经验值',
  `balance` decimal(10,2) NOT NULL COMMENT '钱包余额',
  `task_income` float(8,2) UNSIGNED NOT NULL DEFAULT '0.00' COMMENT '任务收入',
  `diamond` int(11) NOT NULL COMMENT '钻石',
  `birthday` date NOT NULL DEFAULT '1993-12-26',
  `email` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '邮箱',
  `name` varchar(30) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '真实姓名',
  `token` varchar(32) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '操作Token',
  `session_key` varchar(32) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT 'session_key',
  `token_sign_time` int(11) NOT NULL DEFAULT '0' COMMENT 'token标记时间',
  `id_code` varchar(18) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `create_time` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '创建时间戳',
  `update_time` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '更新时间戳'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `user`
--

INSERT INTO `user` (`id`, `openid`, `unionid`, `phone`, `nickname`, `gender`, `wx_avatar`, `level`, `exp`, `balance`, `task_income`, `diamond`, `birthday`, `email`, `name`, `token`, `session_key`, `token_sign_time`, `id_code`, `create_time`, `update_time`) VALUES
(1, 'oFNkM5GVZgAHY0imzwxTML16CI5c', '', '', '胖胖', 1, 'https://wx.qlogo.cn/mmopen/vi_32/DYAIOgq83er8pEMibjoKicX2pYHuUZB5mCvRXlaGUj2TUwdtzTHBoDmgzUW6V9uzorIB6J2o5ZsChnZ4zW59JK3A/132', 1, 0, '99706.24', 0.00, 0, '1993-12-26', '', '', '8981f18a1b10535c73b46a6a4a185668', 'SFWS92mLQUF3ZND//3h8xQ==', 1532591360, '', 1531980525, 1532584160),
(2, 'oFNkM5GVZgAHY0imzwxTML16CI5c', '', '', '胖胖2', 1, 'https://wx.qlogo.cn/mmopen/vi_32/DYAIOgq83er8pEMibjoKicX2pYHuUZB5mCvRXlaGUj2TUwdtzTHBoDmgzUW6V9uzorIB6J2o5ZsChnZ4zW59JK3A/132', 1, 0, '99706.24', 0.00, 0, '1993-12-26', '', '', '857170f73ffd9f54fabd1f2ca8ec1f86', 'cZ2PNuMSjAbobwJaUvzMaA==', 1532582995, '', 1531980525, 1532575795),
(3, 'oFNkM5GVZgAHY0imzwxTML16CI5c', '', '', '胖胖3', 1, 'https://wx.qlogo.cn/mmopen/vi_32/DYAIOgq83er8pEMibjoKicX2pYHuUZB5mCvRXlaGUj2TUwdtzTHBoDmgzUW6V9uzorIB6J2o5ZsChnZ4zW59JK3A/132', 1, 0, '99706.24', 0.00, 0, '1993-12-26', '', '', '857170f73ffd9f54fabd1f2ca8ec1f86', 'cZ2PNuMSjAbobwJaUvzMaA==', 1532582995, '', 1531980525, 1532575795),
(4, 'oFNkM5GVZgAHY0imzwxTML16CI5c', '', '', '胖胖4', 1, 'https://wx.qlogo.cn/mmopen/vi_32/DYAIOgq83er8pEMibjoKicX2pYHuUZB5mCvRXlaGUj2TUwdtzTHBoDmgzUW6V9uzorIB6J2o5ZsChnZ4zW59JK3A/132', 1, 0, '99706.24', 0.00, 0, '1993-12-26', '', '', '857170f73ffd9f54fabd1f2ca8ec1f86', 'cZ2PNuMSjAbobwJaUvzMaA==', 1532582995, '', 1531980525, 1532575795);

-- --------------------------------------------------------

--
-- 表的结构 `user_attr`
--

DROP TABLE IF EXISTS `user_attr`;
CREATE TABLE `user_attr` (
  `id` int(11) NOT NULL,
  `level` int(11) NOT NULL COMMENT '用户等级',
  `headimg_border_color` int(11) NOT NULL COMMENT '头像边框颜色',
  `exp` int(11) NOT NULL COMMENT '用户升级所需经验',
  `friend_num` int(8) UNSIGNED NOT NULL DEFAULT '0' COMMENT '等级对应好友数',
  `assoc_num` int(8) UNSIGNED NOT NULL DEFAULT '0' COMMENT '等级对应家族数',
  `atten_num` int(8) NOT NULL COMMENT '等级对应关注数'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `user_grab_red_packet`
--

DROP TABLE IF EXISTS `user_grab_red_packet`;
CREATE TABLE `user_grab_red_packet` (
  `id` int(11) NOT NULL COMMENT '用户点击红包记录表',
  `user_id` int(11) NOT NULL COMMENT '用户id',
  `red_packet_id` int(11) NOT NULL COMMENT '红包id'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `user_moments_look`
--

DROP TABLE IF EXISTS `user_moments_look`;
CREATE TABLE `user_moments_look` (
  `id` int(11) UNSIGNED NOT NULL COMMENT '索引ID',
  `user_id` int(10) UNSIGNED NOT NULL COMMENT '用户ID',
  `look_user_id` int(10) UNSIGNED NOT NULL COMMENT '查看用户ID',
  `is_look` tinyint(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT '是否查看朋友圈或关注圈 1:查看 0:不查看'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='说说查看关系表';

-- --------------------------------------------------------

--
-- 表的结构 `user_msg`
--

DROP TABLE IF EXISTS `user_msg`;
CREATE TABLE `user_msg` (
  `id` int(11) NOT NULL COMMENT '索引ID',
  `user_id` int(10) NOT NULL DEFAULT '0' COMMENT '用户ID(收消息)',
  `from_id` bigint(20) NOT NULL DEFAULT '0' COMMENT '消息发送人ID',
  `type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '消息类型',
  `ext_params` varchar(600) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '扩展字段',
  `status` tinyint(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT '是否是新的消息, 0:不是, 1:是',
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间戳'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `user_order`
--

DROP TABLE IF EXISTS `user_order`;
CREATE TABLE `user_order` (
  `id` int(11) NOT NULL COMMENT '用户订单表',
  `user_id` int(11) NOT NULL COMMENT '用户id',
  `balance` decimal(10,2) NOT NULL COMMENT '当时余额',
  `amount` decimal(10,2) NOT NULL COMMENT '订单金额',
  `status` int(1) NOT NULL DEFAULT '0' COMMENT '订单状态',
  `order_num` varchar(60) CHARACTER SET utf8 NOT NULL COMMENT '订单号',
  `consum_type` tinyint(1) NOT NULL COMMENT '交易类型 1,充值；2,提现, 3: VIP',
  `fee` float(10,2) UNSIGNED NOT NULL DEFAULT '0.00' COMMENT '手续费, 默认为0',
  `remark` varchar(60) CHARACTER SET utf8 DEFAULT NULL COMMENT '备注',
  `create_time` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户订单表';

-- --------------------------------------------------------

--
-- 表的结构 `user_relation_perm`
--

DROP TABLE IF EXISTS `user_relation_perm`;
CREATE TABLE `user_relation_perm` (
  `id` int(11) UNSIGNED NOT NULL COMMENT '索引ID',
  `user_id` int(10) UNSIGNED NOT NULL COMMENT '用户ID',
  `target_id` int(10) UNSIGNED NOT NULL COMMENT '查看用户ID',
  `rtype` tinyint(4) UNSIGNED NOT NULL DEFAULT '0' COMMENT '关系类型, 0: 无关系; 1: 好友; 2: 关注; 3: 好友并关注',
  `is_look` tinyint(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT '是否查看朋友圈或关注圈 1:查看 0:不查看'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='说说查看关系表';

-- --------------------------------------------------------

--
-- 表的结构 `user_tag`
--

DROP TABLE IF EXISTS `user_tag`;
CREATE TABLE `user_tag` (
  `id` int(11) UNSIGNED NOT NULL COMMENT '索引',
  `uid` int(11) NOT NULL DEFAULT '0' COMMENT '用户ID',
  `tag_id` int(8) NOT NULL DEFAULT '0' COMMENT '标签ID'
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='用户标签表';

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attention`
--
ALTER TABLE `attention`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `target_id` (`target_id`);

--
-- Indexes for table `badge`
--
ALTER TABLE `badge`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `balance_flow`
--
ALTER TABLE `balance_flow`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `best_number`
--
ALTER TABLE `best_number`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `exchange_ka_mi_record`
--
ALTER TABLE `exchange_ka_mi_record`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `friend`
--
ALTER TABLE `friend`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `friend_id` (`friend_id`);

--
-- Indexes for table `money_flow`
--
ALTER TABLE `money_flow`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `oss_fdel_queue`
--
ALTER TABLE `oss_fdel_queue`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pay_order`
--
ALTER TABLE `pay_order`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `report`
--
ALTER TABLE `report`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reward_task`
--
ALTER TABLE `reward_task`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reward_task_record`
--
ALTER TABLE `reward_task_record`
  ADD PRIMARY KEY (`id`),
  ADD KEY `FK_ID` (`task_id`);

--
-- Indexes for table `system_config`
--
ALTER TABLE `system_config`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_dyn`
--
ALTER TABLE `system_dyn`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_hot`
--
ALTER TABLE `system_hot`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_money_flow`
--
ALTER TABLE `system_money_flow`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_notice`
--
ALTER TABLE `system_notice`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tag`
--
ALTER TABLE `tag`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `title`
--
ALTER TABLE `title`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_attr`
--
ALTER TABLE `user_attr`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_grab_red_packet`
--
ALTER TABLE `user_grab_red_packet`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_moments_look`
--
ALTER TABLE `user_moments_look`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_msg`
--
ALTER TABLE `user_msg`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_order`
--
ALTER TABLE `user_order`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_relation_perm`
--
ALTER TABLE `user_relation_perm`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_tag`
--
ALTER TABLE `user_tag`
  ADD PRIMARY KEY (`id`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `attention`
--
ALTER TABLE `attention`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `badge`
--
ALTER TABLE `badge`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `balance_flow`
--
ALTER TABLE `balance_flow`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID';

--
-- 使用表AUTO_INCREMENT `best_number`
--
ALTER TABLE `best_number`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `exchange_ka_mi_record`
--
ALTER TABLE `exchange_ka_mi_record`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `files`
--
ALTER TABLE `files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'id, 主键', AUTO_INCREMENT=10;

--
-- 使用表AUTO_INCREMENT `friend`
--
ALTER TABLE `friend`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- 使用表AUTO_INCREMENT `money_flow`
--
ALTER TABLE `money_flow`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID';

--
-- 使用表AUTO_INCREMENT `oss_fdel_queue`
--
ALTER TABLE `oss_fdel_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键, 索引ID';

--
-- 使用表AUTO_INCREMENT `pay_order`
--
ALTER TABLE `pay_order`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '用户订单表';

--
-- 使用表AUTO_INCREMENT `report`
--
ALTER TABLE `report`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `reward_task`
--
ALTER TABLE `reward_task`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT COMMENT '任务ID', AUTO_INCREMENT=4;

--
-- 使用表AUTO_INCREMENT `reward_task_record`
--
ALTER TABLE `reward_task_record`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT COMMENT '记录ID', AUTO_INCREMENT=14;

--
-- 使用表AUTO_INCREMENT `system_config`
--
ALTER TABLE `system_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `system_dyn`
--
ALTER TABLE `system_dyn`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID';

--
-- 使用表AUTO_INCREMENT `system_hot`
--
ALTER TABLE `system_hot`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID';

--
-- 使用表AUTO_INCREMENT `system_money_flow`
--
ALTER TABLE `system_money_flow`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID';

--
-- 使用表AUTO_INCREMENT `system_notice`
--
ALTER TABLE `system_notice`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID';

--
-- 使用表AUTO_INCREMENT `tag`
--
ALTER TABLE `tag`
  MODIFY `id` int(8) NOT NULL AUTO_INCREMENT COMMENT 'id';

--
-- 使用表AUTO_INCREMENT `title`
--
ALTER TABLE `title`
  MODIFY `id` int(2) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `user`
--
ALTER TABLE `user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- 使用表AUTO_INCREMENT `user_attr`
--
ALTER TABLE `user_attr`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `user_grab_red_packet`
--
ALTER TABLE `user_grab_red_packet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '用户点击红包记录表';

--
-- 使用表AUTO_INCREMENT `user_moments_look`
--
ALTER TABLE `user_moments_look`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '索引ID';

--
-- 使用表AUTO_INCREMENT `user_msg`
--
ALTER TABLE `user_msg`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '索引ID';

--
-- 使用表AUTO_INCREMENT `user_order`
--
ALTER TABLE `user_order`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '用户订单表';

--
-- 使用表AUTO_INCREMENT `user_relation_perm`
--
ALTER TABLE `user_relation_perm`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '索引ID';

--
-- 使用表AUTO_INCREMENT `user_tag`
--
ALTER TABLE `user_tag`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '索引';
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
