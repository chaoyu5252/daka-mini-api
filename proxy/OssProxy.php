<?php
namespace Fichat\Proxy;

use Fichat\Common\DBManager;
use OSS\OssClient;
use OSS\Core\OssException;

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
define('OSS_BUCKET_VIDEO', 'video');

use Fichat\Utils\Utils;

class OssProxy
{
	
	/**
	 * 上传头像
	 *
	 */
	public static function uploadAvatar($di, $target, $type)
	{
		// 获取更新类别, 获取OSS存储空间
		switch ($type) {
			case 1:
				$file_id = $target->id;
				$oss_buss_type = UPLOAD_BUSS_AVATAR;
				$oss_bucket = OSS_BUCKET_UAVATAR;
				// 获取旧头像地址
				$old_uri = $target->user_avatar;
				break;
			case 2:
				$file_id = $target->group_id;
				$oss_buss_type = UPLOAD_BUSS_GROUP;
				$oss_bucket = OSS_BUCKET_GAVATAR;
				// 获取旧头像地址
				$old_uri = $target->assoc_avatar;
				break;
			default:
				return false;
		}
		// OSS配置
		$ossConfig = $di->get('config')->ossConfig;
		// OSS执行上传
		$uploadRS = self::ossUploadFile($di, $oss_bucket, $file_id, $oss_buss_type, 'avatar');
		// 检查是否成功
		if (!$uploadRS) {
			return false;
		}
		// 构建保存的图片资源信息
		$imgUrl = $oss_bucket . ';' . $uploadRS['oss-request-url'];
		if ($old_uri) {
			// 删除旧的头像
			$ossDelRs = self::deleteFile($ossConfig, $old_uri);
			// 如果删除失败了, 存入OSS失败队列, 留待以后处理
			if (!$ossDelRs) {
				// 删除失败的头像, 应该存入一个列表中, 后期维护该列表执行删除任务
				$ossFdelQueue = new OssFdelQueue();
				// 存储该条记录
				$ossFdelQueue->save(array('resource' => $old_uri));
			}
		}
		// 根据类别分别构建不同的返回
		if ($type == 1) {
			$target = DBManager::updateUser($di, $target, null, null, null, null, $imgUrl, $uploadRS['thumb']);
			$target->user_avatar = self::procOssPic($target->user_avatar);
			$data = [
				'avatar' => self::procOssPic($target->user_avatar),
				'thumb' => self::procOssThumb($target->user_thumb),
				'avatarKey' => 'userAvatar',
				'thumbKey' => 'userThumb'
			];
		} else {
			$target = DBManager::updateAssociationAvatar($target, $imgUrl, $uploadRS['thumb']);
			$target->assoc_avatar = self::procOssPic($target->assoc_avatar);
			$data = [
				'avatar' => self::procOssPic($target->assoc_avatar),
				'thumb' => self::procOssThumb($target->assoc_thumb),
				'avatarKey' => 'familyAvatar',
				'thumbKey' => 'familyThumb'
			];
		}
		return $data;
	}
	
	/**
	 * 上传说说/红包封面
	 *
	 */
	public static function uploadMomentsCover($di, $uid, $file_prex = '')
	{
		// 存储空间名
		$oss_bucket = OSS_BUCKET_MOMENTS;
		// OSS上传
		$uploadRS = self::ossUploadFile($di, $oss_bucket, $uid, UPLOAD_BUSS_MOMENT, 'pri_url', $file_prex);
		// 检查是否成功
		if (!$uploadRS['error']) {
//		if (count($uploadRS) == count($_FILES['pri_url']['tmp_name'])) {
			// 构建原图与缩略样式的列表
			$url = $uploadRS['oss-request-url'];
			$thumb = $uploadRS['thumb'];
//			$data = ['url' => $oss_bucket . ';' . $url, 'thumb' => $thumb];
			if ($uploadRS['gif']) {
//				$data['gif'] = $uploadRS['gif'];
				$data = [
					'url' => OSS_BUCKET_VIDEO.';'. $url,
					'thumb' => '',
					'preview' => $uploadRS['gif']
				];
			} else {
				$data = ['url' => $oss_bucket . ';' . $url, 'thumb' => $thumb, 'preview' => ''];
			}
			// 返回结果
			return $data;
		} else {
			// 上传失败
			return $uploadRS;
		}
	}
	
	
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
	public static function ossUploadFile($di, $bucket_name, $id, $type, $file_key, $file_prex = '')
	{
		// 上传返回结果
		$uploadRs = array();
		try {
			$ossConfig = $di->get('config')['ossConfig'];
			$bucket = $ossConfig['bucket'][$bucket_name];
			// 初始化oss客户端对象
			$ossClient = self::ossClient($ossConfig, $bucket['end_point']);
			// 处理文件信息
			$file_info = self::uploadFileInfo($type, $id, $file_key, $file_prex);
//			// 检查空间是否存在
			if (!$ossClient->doesBucketExist($bucket['space'])) {
				return null;
			}
			/** 根据类型上传文件
			 * 文件会返回OSS缩略样式,OSS地址,以及上传前的文件名;
			 * 如果中间有哪一个上传失败, 那么这一次数据就不会添加进返回结果;
			 * 客户根据原文件名可以做比较, 看看是否都上传成功了.
			 */
			$uploadRs['file_name'] = $file_info['file_name'];
			$uploadRs['file_type'] = $file_info['file_type'];
			return $uploadRs;
		} catch (OssException $e) {
//			printf(__FUNCTION__, $e->getMessage());
//			printf($e->getMessage());
			return $uploadRs['error'] = 'E0084';
		}
	}

    /**
     * 将远程图片上传至OSS
     * @param $di
     * @param $bucket_name
     * @param $file_name    //文件名称
     * @param $url          //远程图片的路径
     * @return $uploadRes
     */
    public static function uploadRemoteImage($di, $bucket_name, $file_name, $url) {
        $ch=curl_init();
        $timeout=5;
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);
        $img = curl_exec($ch);

        $ossConfig = $di->get('config')['ossConfig'];
        $bucket = $ossConfig['bucket'][$bucket_name];
        // 初始化oss客户端对象
        $ossClient = self::ossClient($ossConfig, $bucket['end_point']);
        $file_name .= '.png';
        $uploadRes = $ossClient->putObject($bucket['space'], $file_name, $img);
        curl_close($ch);
        return $uploadRes;
    }
	
	// 删除文件
	
	/**
	 * @params  $url:   oss-request-url 地址
	 */
	public static function deleteFile($ossConfig, $url)
	{
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
				$ossClient = self::ossClient($ossConfig, $bucket['end_point']);
				// 删除文件
				$ossRs = $ossClient->deleteObject($bucket['space'], $oss_key);
				return $ossRs;
			} catch (OssException $e) {
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
	public static function copyFiles($ossConfig, $url_str, $preview_str = '')
	{
//		$bucket = $ossConfig['bucket'][$bucket_name];
		// 初始化oss客户端对象
//		$ossClient = self::ossClient($ossConfig, $bucket['end_point']);
		$pri_url = '';
		$file_info = explode(';', $url_str);
		$copyFiles = array();
		if ($preview_str) {
//			Utils::echo_debug('111pre_str:'.$preview_str);
			array_push($copyFiles, ['bucket' => OSS_BUCKET_VIDEO, 'name' => $preview_str, 'key' => 'pri_preview']);
		}
		if ($file_info && count($file_info) == 2) {
			array_push($copyFiles, ['bucket' => $file_info[0], 'name' => $file_info[1], 'key' => 'pri_url']);
		}
//		var_dump($copyFiles);
		// 复制旧文件到新的地址中
		foreach ($copyFiles as $copyFile) {
//			var_dump($copyFile);
			$bucket_name = $copyFile['bucket'];
//			Utils::echo_debug('bucket:'.$copyFile['bucket']);
			// 存储空间
			$bucket = $ossConfig['bucket'][$bucket_name];
			// 初始化oss客户端对象
			$ossClient = self::ossClient($ossConfig, $bucket['end_point']);
			// 旧的文件信息
			$oldFileInfo = explode('/', $copyFile['name']);
			$oldFileName = $oldFileInfo[count($oldFileInfo) - 1];
			$newFileName = preg_replace('/tmp_/', '', $oldFileName, 1);
			$fullOldFileName = $oldFileName;
			$fullNewFileName = $newFileName;
			if ($copyFile['key'] == 'pri_preview') {
				$fullOldFileName = 'gif/'.$oldFileName;
				$fullNewFileName = 'gif/'.$newFileName;
			}
			
			// 拷贝对象
			if ($ossClient->copyObject($bucket['space'], $fullOldFileName, $bucket['space'], $fullNewFileName)) {
//				var_dump($r);
//				var_dump([
//					'on' => $copyFile['name'],
//					'of' => $oldFileName,
//					'nf' => $newFileName
//				]);
				$newSaveFileName = preg_replace('/' . $oldFileName . '/', $newFileName, $copyFile['name'], 1);
//				Utils::echo_debug('new_file:'.$newFileName);
				if ($copyFile['key'] == 'pri_url') {
					$pri_url = $bucket_name . ';' . $newSaveFileName;
				} else {
					$pri_preview = $newSaveFileName;
					
				}
			}
		}
		
//		if ($file_info[0] == $bucket_name) {
//			if ($file_info[0] && $file_info[1]) {
//				$bucket_name = $file_info[0];
//				$oldFileInfo = explode('/', $file_info[1]);
//				$oldFileName = $oldFileInfo[count($oldFileInfo) - 1];
//				$newFileName = preg_replace('/tmp_/', '', $oldFileName, 1);
//				if ($ossClient->copyObject($bucket['space'], $oldFileName, $bucket['space'], $newFileName)) {
//					$newSaveFileName = preg_replace('/' . $oldFileName . '/', $newFileName, $file_info[1], 1);
//					if ($pri_url == '') {
//						$pri_url = $bucket_name . ';' . $newSaveFileName;
//					} else {
//						$pri_url = $pri_url . '|' . $bucket_name . ';' . $newSaveFileName;
//					}
//				}
//			}
//		} else {
//			$pri_url = $url_str;
//		}
		// 拷贝预览图
		if (!$pri_preview) {
			$oldPreviewInfo = explode('/', $file_info[1]);
//			$oldPreviewFileName = $oldPreviewInfo[count($oldPreviewInfo) - 1];
//			$newPreviewFileName = preg_replace('/tmp_/', '', $oldFileName, 1);
//		} else {
			$pri_preview = '';
		}
		return ['url' => $pri_url, 'preview' => $pri_preview];
	}
	
	
	// 获取上传信息
	public static function uploadFileInfo($type, $id, $file_key, $file_prex)
	{
//		Utils::echo_debug(33);
		// 检查是否是数组
		if (is_array($_FILES[$file_key]['tmp_name'])) {
			$tmpFileInfo = array($file_key => array(
				'name' => $_FILES[$file_key]['name'][0],
				'type' => $_FILES[$file_key]['type'][0],
				'tmp_name' => $_FILES[$file_key]['tmp_name'][0],
				'error' => $_FILES[$file_key]['error'][0],
				'size' => $_FILES[$file_key]['size'][0]
			));
			$_FILES = $tmpFileInfo;
		}
		// 获取文件扩展名
		$file_ext_info = self::getExtName($_FILES[$file_key]['type']);
		$file_ext = $file_ext_info[0];
		$file_type = $file_ext_info[1];
		$upload_content = ['data' => $_FILES[$file_key]['tmp_name'], 'name' => $_FILES[$file_key]['name'], 'ext' => $file_ext];
		// 循环处理上传的数据
		$ts = time();
		
		// 获取图片尺寸
		$upload_file_info = getimagesize($upload_content['data']);
		$pic_wd = $upload_file_info[0];
		$pic_ht = $upload_file_info[1];
		
		// 构建尺寸数组
		$pic_size = array('width' => $pic_wd, 'height' => $pic_ht);
		// 根据类型获取文件名前缀和缩略图样式
		$thumbData = self::getThumbStyleBySize($type, $pic_size);
		// 文件名的前缀
		$prefix = $thumbData[0];
		// 缩略图样式
		$thumb_style = $thumbData[1];
		
		// 时间戳
		$file_id = $file_prex . $prefix . $id . 'I' . $ts;
		$file_name = $file_id . $upload_content['ext'];
		
		// 保存数据
		$returnUploadRs = [
			'file_id' => $file_id,
			'file_name' => $file_name,
			'origin_file_name' => $upload_content['name'],
			'upload_content' => $upload_content['data'],
			'ext' => $upload_content['ext'],
			'file_type' => $file_type
		];
//		array_push($returnUploadRs, [
//			'file_name' => $file_name,
//			'origin_file_name' => $upload_content['name'],
//			'upload_content' => $upload_content['data'],
//			'thumb_style' => $thumb_style,
//			'ext' => $upload_content['ext']
//		]);
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
			'http://dakaapp-background.oss-cn-beijing.aliyuncs.com/redpack/5.png'
		);
		// 获取原始
		$originUrl = OSS_BUCKET_BG . ';' . $redpackBgPics[$randBgIndex - 1];
		// 返回结果
		return array('originUrl' => $originUrl, 'thumb' => UPLOAD_PIC_BG);
	}
	
	
	// 根据图片尺寸获取图片的缩略图样式
	private static function getThumbStyleBySize($type, $pic_size)
	{
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
				$prefix = "G";
				$thumb_style = UPLOAD_PIC_GAVATAR;
				break;
			case 4:
				$prefix = "BG";
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
//		Utils::echo_debug($file_type);
		if ($file_type == 'image/jpeg' || $file_type == 'image/jpg') {
			return ['.jpg', FILE_TYPE_JPG];
		} else if ($file_type == 'image/gif') {
			return ['.gif', FILE_TYPE_GIF];
		} else if ($file_type == 'image/png') {
			return ['.png', FILE_TYPE_PNG];
		} else {
			Utils::throwErrorCode('E0304');
		}
	}
	
	/**
	 * 创建视频缩略文件
	 *
	 */
	public static function createVideoPreview($di, $fileInfo)
	{
		$previewFileId = $fileInfo['file_id'].'.gif';
		$previewFile = self::getTmpDir($fileInfo['upload_content']).'/'.$previewFileId;
		$videoFile = $fileInfo['upload_content'];
		// 视频缩略配置'
		$videoThumbConf = $di->get('config')['videoThumb'];
		// 命令
		$cmd = 'ffmpeg -t '.$videoThumbConf['duration'].' -r '.$videoThumbConf['frameRate'].
			   ' -i '.$videoFile.' -ss '.$videoThumbConf['startTime'].' ' . $previewFile;
//		Utils::echo_debug('cmd:'.$cmd);
//		var_dump(file_get_contents($fileInfo['upload_content']));
		// 转成生成动图预览
		exec($cmd);
		if (file_exists($previewFile)) {
			// 处理结果
			return ['previewFile' => $previewFile, 'previewFileId' => $previewFileId];
		} else {
			return false;
		}
	}
	
	/**
	 * 获取临时文件存放路径
	 *
	 */
	public static function getTmpDir($tmpFile)
	{
		$tmpDirInfo = explode('/', $tmpFile);
		array_pop($tmpDirInfo);
		return implode('/', $tmpDirInfo);
	}
	
	
	
//	/**
//	 * 上传的数组转成单个文件,取第一个
//	 *
//	 */
//	public static function

}