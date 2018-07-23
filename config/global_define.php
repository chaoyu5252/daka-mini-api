<?php

// 过期KEY
define('EXPIRE_TYPE_REDPACK', 1);
define('EXPIRE_TYPE_REWARDTASK', 2);

// 短信业务宏
define('SMSBUSS_TYPE_LOGIN', 1);
define('SMSBUSS_TYPE_PAYPASSWORD', 2);
define('SMSBUSS_TYPE_BINDPHONE', 3);
define('SMSBUSS_TYPE_WITHDRAW', 4);

define('PAY_CHANNEL_ALI', 1);       // 支付宝支付
define('PAY_CHANNEL_WX', 2);        // 微信支付
define('PAY_CHANNEL_BL', 3);        // 余额支付
define('PAY_CHANNEL_APPLE', 4);        // 苹果支付

define('PAYOP_TYPE_RECHARGE', 1);
define('PAYOP_TYPE_TAKE', 2);
define('PAYOP_TYPE_SEND_MOMENT_REDPACKET', 3);
define('PAYOP_TYPE_SEND_CHAT_REDPACKET', 4);
define('PAYOP_TYPE_GRAB_REDPACKET', 5);
define('PAYOP_TYPE_RETURN_REDPACKET', 6);
define('PAYOP_TYPE_REWARD_TASK', 7);
define('PAYOP_TYPE_TAKE_TASK_INCOME', 8);
define('PAYOP_TYPE_CLICK_TASK_GET', 9);
define('PAYOP_TYPE_SHARE_TASK_GET', 10);
define('PAYOP_TYPE_RETURN_TASK', 11);

// 支付宏 开始
define('BALOP_TYPE_ADD', 1);
define('BALOP_TYPE_REDUCE', 2);

// 分页宏
define('PAGE_SIZE', 50);

// 服务宏
define('SERVICE_TRANSACTION', 'transaction');
define('SERVICE_REDIS', 'redis');
define('SERVICE_CRYPT', 'crypt');
define('SERVICE_LOG', 'logger');
define('SERVICE_CONFIG', 'config');
define('SERVICE_GLOBAL_DATA', 'gd');

// 查看好友朋友圈状态
define('LOOK_UMOMENTS_YES', 1);
define('LOOK_UMOMENTS_NO', 0);

// 是否关注
define('USER_ATTENSION_YES', 1);
define('USER_ATTENSION_NO', 0);

// 用户说说查看关系
define('URP_TYPE_STRANGER', 0);    // 陌生人
define('URP_TYPE_FRIEND', 1);      // 好友
define('URP_TYPE_ATTENSION', 2);   // 关注
define('URP_TYPE_FAA', 3);         // 好友和关注

// 家族用户权限
define('FAMILY_PERM_OWNER', '11111111');
define('FAMILY_PERM_MEMBER', '00000000');

// 配置
define('CONFIG_KEY_BAIDU_OPENAPI', 'baidu_openapi');
define('CONFIG_KEY_BAIDU_PUSH', 'baidu_push');
define('CONFIG_KEY_REDIS', 'redis');
define('CONFIG_KEY_DB', 'database');
define('CONFIG_KEY_APP', 'application');
define('CONFIG_KEY_LOG', 'logger');
define('CONFIG_KEY_HX', 'hxConfig');
define('CONFIG_KEY_DEBUG', 'debug');
define('CONFIG_KEY_VIDEO_THUMB', 'videoThumb');
define('CONFIG_KEY_OSS', 'ossConfig');
define('CONFIG_KEY_SWOOLE', 'swoole');
define('CONFIG_KEY_WXMINI', 'wxminiapp');

define('TOKEN_MD5_KEY', "Ac9%98ZE");
define('TOKEN_KEEP', 7200);             // 微信token时间为2个小时

define('LOGIN_STATUS_LOGIN', 1);
define('LOGIN_STATUS_REG', 2);


define('FILE_TYPE_NONE', 0);
define('FILE_TYPE_JPG', 1);
define('FILE_TYPE_PNG', 2);
define('FILE_TYPE_GIF', 3);

/** 错误码 */
define('ERROR_SUCCESS', 'E0000');
define('ERROR_TOKEN', 'E0001');
define('ERROR_TOKEN_TIMEOUT', 'E0002');
define('ERROR_WX_DECRYPT', 'E0003');
define('ERROR_NO_USER', 'E0004');
define('ERROR_LOGIN_VERIFY', 'E0005');
define('ERROR_MONEY', 'E0006');
define('ERROR_TASK_CLICK_COUNT_LESS', 'E0007');
define('ERROR_TASK_CLICK_PRICE', 'E0008');
define('ERROR_TASK_SHARE_PRICE', 'E0009');
define('ERROR_TASK_CLICK_AND_SHARE_SUM_MORE', 'E0010');
define('ERROR_TASK_SHARE_COUNT_LESS', 'E0010');
define('ERROR_TASK_SHARE_COUNT_MORE', 'E0011');
define('ERROR_UPLOAD', 'E0303');
define('ERROR_UPLOAD_FILE_TYPE', 'E0304');
define('ERROR_LOGIC', 'E9999');

