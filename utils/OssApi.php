<?php

// 命名空间
namespace Fichat\Utils;

use OSS\OssClient;

use OSS\Core\OssException;

use Fichat\Common\ReturnMessageManager;


// 上传业务类型 =======================================

define('UPLOAD_BUSS_AVATAR', 1);
define('UPLOAD_BUSS_MOMENT', 2);
define('UPLOAD_BUSS_GROUP', 3);
define('UPLOAD_BUSS_BG', 4);
define('UPLOAD_BUSS_COMMON', 5);
define('UPLOAD_BUSS_RTCOVER', 6);

// 上传图片显示宏 =====================================
define('UPLOAD_PIC_UAVATAR', '?x-oss-process=style/thumb_u_avatar');
define('UPLOAD_PIC_GAVATAR', '?x-oss-process=style/thumb_g_avatar');
define('UPLOAD_PIC_MRESIZE', '?x-oss-process=style/thumb_resize');
define('UPLOAD_PIC_MRECLIP', '?x-oss-process=style/thumb_recrop');
define('UPLOAD_PIC_RTCOVER', '?x-oss-process=style/thumb_u_avatar');
define('UPLOAD_PIC_BG', '?x-oss-process=style/thumb_bg_resizecrop');

define('UPLOAD_PIC_MXWD', 256);
define('UPLOAD_PIC_MXHT', 384);

define('UPLOAD_BG_MXWD', 540);
define('UPLOAD_BG_MXHT', 270);

define('UPLOAD_RTCOVER_MXWD', 540);
define('UPLOAD_RTCOVER_MXHT', 270);

define('OSS_BUCKET_UAVATAR', 'uavatar');
define('OSS_BUCKET_MOMENTS', 'moments');
define('OSS_BUCKET_RTCOVER', 'rtcover');
define('OSS_BUCKET_GAVATAR', 'gavatar');
define('OSS_BUCKET_BG', 'background');
define('OSS_BUCKET_PUBLIC', 'public');


class OssApi {

    // 返回ossClient
    /**
     * @params $ossConfig   OSS配置文件
     */
    public static function ossClient($ossConfig, $end_point)
    {
        try {
            $client = new OssClient($ossConfig['appKey'], $ossConfig['appSecret'], $end_point);
        } catch (OssException $e) {
            return null;
        }
        return $client;
    }

    // 上传操作
    /**
     * @params $ossConfig   OSS配置参数，用来创建OSS_CLIENT
     * @params $bucketKey   定义文件的存储空间
     * @params $id          用户或圈子ID
     * @params $type        业务类型
     * @params $file_key    文件字段
     */
    public static function ossUploadFile($ossConfig, $bucket_name, $id, $type, $file_key, $file_prex = '') {
        try {
            $bucket = $ossConfig['bucket'][$bucket_name];
            // 初始化oss客户端对象
            $ossClient = OssApi::ossClient($ossConfig, $bucket['end_point']);
            // 处理文件信息
            $files_info = OssApi::uploadFileInfo($type, $id, $file_key, $file_prex);
            // 检查空间是否存在
            if (!$ossClient->doesBucketExist($bucket['space'])) {
                return null;
            }
            // 上传返回结果
            $uploadRs = array();
            /** 根据类型上传文件
             * 文件会返回OSS缩略样式,OSS地址,以及上传前的文件名;
             * 如果中间有哪一个上传失败, 那么这一次数据就不会添加进返回结果;
             * 客户根据原文件名可以做比较, 看看是否都上传成功了.
             */
            foreach ($files_info as $file_info) {
                $file_name = $file_info['file_name'];
                $ossRs =  $ossClient -> uploadFile($bucket['space'], $file_name, $file_info['upload_content']);
                if ($ossRs) {
                    $ossRs['thumb'] = $file_info['thumb_style'];
                    array_push($uploadRs, $ossRs);
                }
            }
            return $uploadRs;
        } catch(OssException $e) {
            printf(__FUNCTION__, $e->getMessage());
            printf($e->getMessage());
            return null;
        }
    }

    // 删除文件
    /**
     * @params  $url:   oss-request-url 地址
     */
    public static function deleteFile($ossConfig, $url) {
        $oss_info = explode(";", $url);
        if (count($oss_info) == 2) {
            $bucket_name = $oss_info[0];
            $oss_uri = explode("/", $oss_info[1]);
            // 获取 oss_key
            $oss_key = $oss_uri[count($oss_uri) - 1];
            // 获取OSS的配置项
            $bucket = $ossConfig['bucket'][$bucket_name];
            // 开始执行删除
            try {
                // 初始化客户端
                $ossClient = OssApi::ossClient($ossConfig, $bucket['end_point']);
                // 删除文件
                $ossRs = $ossClient -> deleteObject($bucket['space'], $oss_key);
                return $ossRs;
            } catch(OssException $e) {
                return null;
            }
        } else {
            // 不用删除, 类型不同
            return true;
        }
    }

    /**
     * 复制OSS对象
     * */
    public static function copyFiles($ossConfig, $bucket_name, $url_str, $thumb_str) {
        $bucket = $ossConfig['bucket'][$bucket_name];
        // 初始化oss客户端对象
        $ossClient = OssApi::ossClient($ossConfig, $bucket['end_point']);
        $files = explode('|', $url_str);
        $thumbs = explode('|', $thumb_str);
        $pri_url = '';
        $pri_thumb = '';
        foreach ($files as $key => $value) {
            $file_info = explode(';', $url_str);
            if ($file_info[0] == $bucket_name) {
                if ($file_info[0] && $file_info[1]) {
                    $bucket_name = $file_info[0];
                    $oldFileInfo = explode('/', $file_info[1]);
                    $oldFileName = $oldFileInfo[count($oldFileInfo) - 1];
                    $newFileName = preg_replace('/tmp_/', '', $oldFileName, 1);
                    if ($ossClient->copyObject($bucket['space'], $oldFileName, $bucket['space'], $newFileName)) {
                        $newSaveFileName = preg_replace('/'.$oldFileName.'/', $newFileName, $file_info[1], 1);
                        if ($pri_url == '') {
                            $pri_url = $bucket_name.';'.$newSaveFileName;
                            $pri_thumb = $thumbs[$key];
                        } else {
                            $pri_url = $pri_url . '|' . $bucket_name . ';' . $newSaveFileName;
                            $pri_thumb = $pri_thumb . '|' . $thumbs[$key];
                        }
                    }
                }
            } else {
                $pri_url = $url_str;
                $pri_thumb = $thumbs[$key];
            }
        }
	    return ['url' => $pri_url, 'thumb' => $pri_thumb];
//        return ['pri_url' => $pri_url, 'pri_thumb' => $pri_thumb];
    }

    // 获取上传信息
    public static function uploadFileInfo($type, $id, $file_key, $file_prex) {
        // 获取扩展名
        $upload_content = array();
        // 检查是否是数组
        if (is_array($_FILES[$file_key]['tmp_name'])){
            foreach ($_FILES[$file_key]['tmp_name'] as $key => $value) {
                // 获取文件扩展名
                $file_ext = OssApi::getExtName($_FILES[$file_key]['type'][$key]);
                array_push($upload_content, ['data' => $value, 'name' => $_FILES[$file_key]['name'][$key], 'ext' => $file_ext]);
            }
        } else {
            // 获取文件扩展名
            $file_ext = OssApi::getExtName($_FILES[$file_key]['type']);
            $upload_content = [['data' => $_FILES[$file_key]['tmp_name'], 'name' => $_FILES[$file_key]['name'], 'ext' => $file_ext]];
        }
        $returnUploadRs = array();
        // 循环处理上传的数据
        $index = 0;
        $ts = time();
        foreach ($upload_content as $value) {
            // 获取图片尺寸
            $upload_file_info = getimagesize($value['data']);
            $pic_wd = $upload_file_info[0];
            $pic_ht = $upload_file_info[1];

            // 构建尺寸数组
            $pic_size = array('width' => $pic_wd, 'height' => $pic_ht);
            // 根据类型获取文件名前缀和缩略图样式
            $thumbData = OssApi::getThumbStyleBySize($type, $pic_size);
            // 文件名的前缀
            $prefix = $thumbData[0];
            // 缩略图样式
            $thumb_style = $thumbData[1];

            // 时间戳
            $ts = $ts + $index;
            $file_name = $file_prex . $prefix. $id . 'I' . $ts . $value['ext'];
            // 保存数据
            array_push($returnUploadRs, [
                'file_name' => $file_name,
                'origin_file_name' => $value['name'],
                'upload_content' => $value['data'],
                'thumb_style' => $thumb_style
            ]);
            $index ++;
        }
        return $returnUploadRs;
    }

    /**
     * 处理头像/背景图片的返回
     *
     */
    public static function procOssPic($pic)
    {
        $picInfo = explode(';', $pic);
        if (count($picInfo) == 2) {
            // 如果图片是空的, 则返回空字符串
            $pic = $picInfo[1] ? $picInfo[1] : '';
            return $pic;
        } else if ($pic) {
            return $pic;
        } else {
            return '';
        }
    }

    /**
     * 处理缩略图
     */
    public static function procOssThumb($thumb)
    {
        if ($thumb) {
            return $thumb;
        } else if ($thumb) {
            return $thumb;
        } else {
            return '';
        }
    }


    /**
     * 随机获取头像图片
     *
     */
    public static function getDefBg()
    {
        // 随机的背景索引
        $randBgIndex = rand(1, 4);
        $redpackBgPics = array(
            'http://dakaapp-background.oss-cn-beijing.aliyuncs.com/redpack/1.png',
            'http://dakaapp-background.oss-cn-beijing.aliyuncs.com/redpack/2.png',
            'http://dakaapp-background.oss-cn-beijing.aliyuncs.com/redpack/3.png',
            'http://dakaapp-background.oss-cn-beijing.aliyuncs.com/redpack/5.png',
        );
        // 获取原始
        $originUrl = OSS_BUCKET_BG.';'.$redpackBgPics[$randBgIndex - 1];
        // 返回结果
        return array('originUrl' => $originUrl, 'thumb' => UPLOAD_PIC_BG);
    }


    // 根据图片尺寸获取图片的缩略图样式
    private static function getThumbStyleBySize($type, $pic_size) {
        $wd = $pic_size['width'];
        $ht = $pic_size['height'];
        // 根据类型选择前缀
        switch ($type) {
            case 1:
                // 头像
                $prefix = "A";
                $thumb_style = UPLOAD_PIC_UAVATAR;
                break;
            case 2:
                // 说说
                $prefix = "M";
                // 获取宽度比例
                $mult_value = $wd / UPLOAD_PIC_MXWD;
                $new_ht = $ht / $mult_value;
                if ($new_ht > UPLOAD_PIC_MXHT) {
                    $thumb_style = UPLOAD_PIC_MRECLIP;
                } else {
                    $thumb_style = UPLOAD_PIC_MRESIZE;
                }
                break;
            case 3:
                // 群组
                $prefix  = "G";
                $thumb_style = UPLOAD_PIC_GAVATAR;
                break;
            case 4:
                $prefix  = "BG";
                $thumb_style = UPLOAD_PIC_BG;
                break;
            case 6:
                $prefix = "RTC";
                $thumb_style = UPLOAD_PIC_RTCOVER;
                break;
            default:
                // 默认
                $prefix = "C";
                $thumb_style = "";
                break;
        }
        // 检查图片的高度
        return [$prefix, $thumb_style];
    }


    /**
     * 根据文件类型返回扩展名
     *
     */
    public static function getExtName($file_type)
    {
        if ($file_type == 'image/jpeg') {
            return '.jpg';
        } else if($file_type == 'image/gif') {
            return '.gif';
        } else {
            return '.png';
        }
    }

}

