<?php
namespace Fichat\Proxy;

use Fichat\Models\AccessToken;

class HxChatProxy{
	//环信 用户注册逻辑
	public static function registerIM($username, $pwd, $hxConfig){
		$data=array();
		$url=$hxConfig->url . 'users';
		$headers=array(
				'Content-Type:application/json',
				'Authorization:Bearer '.self::getAccessToken($hxConfig),
		);
		
		$data['username']=$username;
		$data['password']=$pwd;
		$postData=json_encode($data);
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT,5);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
		curl_exec($ch);
		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		return $http_status == 200;
	}
	
	// 修改用户昵称
	public static function updateNickname($hxConfig, $username, $nickname) {
		$data=array();
		$url=$hxConfig->url . 'users/' . $username;
		$headers=array(
				'Authorization:Bearer '.self::getAccessToken($hxConfig),
		);
		
		$data['nickname'] = $nickname;
		$postData=json_encode($data);
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT,5);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
		$res = curl_exec($ch);
		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		return $http_status == 200;
	}
	
	private static function getAccessToken($hxConfig){
		$token=AccessToken::findFirst();
		if(!$token || self::checkTokenExpiration($token)){ //如果数据库里不存在
			$newTokenData = self::requestToken($hxConfig);
			$token = self::saveToken($newTokenData, $token);
		}
		return $token->token;
	}
	
	// 判断token是否过期 如果过期返回true
	private static function checkTokenExpiration($token) {
		$expiresIn=intval($token->expires_in); //当前使用token的有效时间
		$getTime=intval($token->get_time); //当前使用token的获取时间
		$tokenTime = $expiresIn + $getTime; //得到当前token的到期时间
		$now = time(); //当前时间
		return $now > $tokenTime;
	}
	
	// 请求新token
	private static function requestToken($hxConfig) {
		$data=array();
		$data['grant_type']='client_credentials';
		$data['client_id']=$hxConfig->clientId;
		$data['client_secret']=$hxConfig->clientSecret;
		$url=$hxConfig->url . 'token';
		$postData=json_encode($data);
			
		$ch = curl_init();//初始化curl
		curl_setopt($ch, CURLOPT_URL,$url);//抓取指定网页
		curl_setopt($ch, CURLOPT_HEADER, 0);//设置header
		curl_setopt($ch, CURLOPT_TIMEOUT,5);//设置header
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
		curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
		$data = curl_exec($ch);//运行curl
		curl_close($ch);
		$tokenData=json_decode($data);
		return $tokenData;
	}
	
	// 保存token
	private static function saveToken($newTokenData, $oldToken){
		if (!$oldToken) {
			$oldToken = new AccessToken();
		}

		if(!$newTokenData){
			return $oldToken;
		}
		
		$oldToken->token=$newTokenData->access_token;
		$oldToken->expires_in=$newTokenData->expires_in;
		$oldToken->application=$newTokenData->application;
		if ($oldToken->save()) {
			return $oldToken;
		} else {
			throw new \RuntimeException($oldToken->getMessages());
		}
	}

	// 检测环信用户是否存在
	public static function getHxUserInfo($hxUserName, $hxConfig) {
		$url = $hxConfig->url . 'users/' . $hxUserName;
		$headers=array(
				'Authorization:Bearer '.self::getAccessToken($hxConfig),
		);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT,5);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$data = curl_exec($ch);
		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		return $http_status == 200;
	}
	
	//重置用户密码
	public static function resetUserPassword($hxUserName, $newPassword, $hxConfig){
		$url = $hxConfig->url . 'users/' . $hxUserName.'/password';
		$headers=array(
				'Authorization:Bearer '.self::getAccessToken($hxConfig),
		);
		
		$postData = array();
		$postData['newpassword'] = $newPassword;
		$postData=json_encode($postData);
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT,5);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
		$res = curl_exec($ch);
		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		return $http_status == 200;
	}
	
	// 添加好友
	public static function addFriend($userAccountInfo, $friendAccountInfo, $hxConfig) {
		// 获取用户手机号或openid
		$hxUserName = $userAccountInfo->phone != ' ' ? $userAccountInfo->phone : $userAccountInfo->uid ;
		$hxFriendName = $friendAccountInfo->phone != ' ' ? $friendAccountInfo->phone : $friendAccountInfo->uid ;
		
		$user = self::getHxUserInfo($hxUserName, $hxConfig);
		$friend = self::getHxUserInfo($hxFriendName, $hxConfig);
		
		if($user && $friend){
			$url = $hxConfig->url . 'users/' . $hxUserName . '/contacts/users/' . $hxFriendName;
			$headers=array(
					'Content-Type:application/json',
					'Authorization:Bearer '.self::getAccessToken($hxConfig),
			);
			$postData = null;
			$ch = curl_init();//初始化curl
			curl_setopt($ch, CURLOPT_URL,$url);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_TIMEOUT,5);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
			$data = curl_exec($ch);
			$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
			return $http_status == 200;
		}else{
			throw new \InvalidArgumentException('环信中不存在该用户');
		}
	}
	
	// 删除好友
	public static function delFriend($userAccountInfo, $friendAccountInfo, $hxConfig) {
		// 获取用户手机号或openid
		$hxUserName = $userAccountInfo->phone != ' ' ? $userAccountInfo->phone : $userAccountInfo->uid ;
		$hxFriendName = $friendAccountInfo->phone != ' ' ? $friendAccountInfo->phone : $friendAccountInfo->uid ;
		
		// 检查环信用户是否存在
		$user = self::getHxUserInfo($hxUserName, $hxConfig);
		$friend = self::getHxUserInfo($hxFriendName, $hxConfig);
		
		// 执行删除环信好友
		if($user && $friend){
			$url = $hxConfig->url . 'users/' . $hxUserName . '/contacts/users/' . $hxFriendName;
			$headers=array(
					'Authorization:Bearer '.self::getAccessToken($hxConfig),
			);
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_TIMEOUT,5);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
			curl_exec($ch);
			$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
			return $http_status == 200;
		}else{
			throw new \InvalidArgumentException('环信中不存在该用户');
		}
	}
	
	//聊天：发送消息
	public static function sendMessages($userName,$targetName,$message,$hxConfig){
		$url = $hxConfig->url . 'messages';
		$headers=array(
				'Content-Type:application/json',
				'Authorization:Bearer '.self::getAccessToken($hxConfig),
		);
		
		//组合消息数据
		$data['target_type']='users';
		$data['target']=array($targetName);
		$data['msg']=array( 'type'=>'txt', 'msg'=>$message, );
		$data['from']=$userName;
		$postData=json_encode($data);
		//发送
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT,5);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
		curl_exec($ch);
		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		return $http_status == 200;
	}
	
	//聊天：发送消息
	public static function sendGroupMessages($userName,$targetName,$message,$hxConfig){
		$url = $hxConfig->url . 'messages';
		$headers=array(
			'Content-Type:application/json',
			'Authorization:Bearer '.self::getAccessToken($hxConfig),
		);
		
		//组合消息数据
		$data['target_type']='chatgroups';
		$data['target']=array($targetName);
		$data['msg']=array( 'type'=>'txt', 'msg'=>$message, );
		$data['from']=$userName;
		$postData=json_encode($data);
		//发送
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT,5);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
		curl_exec($ch);
		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		return $http_status == 200;
	}
	
	// 创建环信群组
	public static function createGroup($username, $roomName, $members, $hxConfig) {
		$url = $hxConfig->url . 'chatgroups';
		$description = '床前明月光 疑是地上霜 举头望明月 低头思故乡';
		$headers=array(
				'Authorization:Bearer '.self::getAccessToken($hxConfig),
		);
		$postData = array(
				'groupname' => "$roomName",
				'desc' => "$description",
				'public' => true,
				'maxusers' => 2000,
				'approval' => false,
				'owner' => "$username",
		);
		if($members){
			$postData['members'] = $members;
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT,5);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
		$result = curl_exec($ch);
		$data = json_decode($result, true);
		curl_close($ch);
		if(!$result){ return false; }
		return $data['data']['groupid'];
	}
	
	// 环信群组添加成员
	public static function addGroupMember($groupId, $username, $hxConfig) {
		if(is_array($username)){
			$url = $hxConfig->url . 'chatgroups/'. $groupId . '/users';
			$postData['usernames'] = $username;
		}else{
			$url = $hxConfig->url . 'chatgroups/'. $groupId . '/users/' . $username;
		}
		$headers=array(
				'Authorization:Bearer '.self::getAccessToken($hxConfig),
		);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT,5);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, 1);
		if(is_array($username)){
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$result = curl_exec($ch);
		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		return $http_status == 200;
	}
	
	// 更新环信群组群主
	public static function updateGroupMaster($groupId, $username, $hxConfig) {
		$url = $hxConfig->url . 'chatgroups/' . $groupId;
		$headers=array(
				'Content-Type:application/json',
				'Authorization:Bearer '.self::getAccessToken($hxConfig),
		);
		$postData = array('newowner' => $username);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT,5);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
		$result = curl_exec($ch);
		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		return $http_status == 200;
	}
	
	// 更新环信群组昵称
	public static function updateGroupName($groupId, $name, $hxConfig) {
		$url = $hxConfig->url . 'chatgroups/' . $groupId;
		$headers=array(
				'Authorization:Bearer '.self::getAccessToken($hxConfig),
		);
		$postData = array('groupname' => $name);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT,5);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
		$result = curl_exec($ch);
		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		return $http_status == 200;
	}
	
	// 退出环信群组
	public static function quitGroup($groupId, $username, $hxConfig) {
		$url = $hxConfig->url . 'chatgroups/'. $groupId . '/users/' . $username;
		$headers=array(
				'Authorization:Bearer '.self::getAccessToken($hxConfig),
		);
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT,5);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        $result = curl_exec($ch);
		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		return $http_status == 200;
	}
	
	// 解散环信群组
	public static function delGroup($groupId, $hxConfig) {
		$url = $hxConfig->url . 'chatgroups/' . $groupId;
		$headers=array(
				'Authorization:Bearer '.self::getAccessToken($hxConfig),
		);
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT,5);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
		curl_exec($ch);
		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		return $http_status == 200;
	}
	
	// 发送透传消息
	public static function sendSilenceMessage($targets, $action, $hxConfig) {
		$url = $hxConfig->url . 'messages';
		$headers=array(
			'Authorization:Bearer '.self::getAccessToken($hxConfig),
		);
		
		$postData = array(
			'target_type' => 'users',
			'target' => $targets,
			'msg' => array('type' => 'cmd', 'action' => $action),
		);
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT,5);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
		curl_exec($ch);
		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		return $http_status == 200;
	}
	
	// 发送扩展透传消息
	public static function sendSilenceExtMessage($targets, $action, $extMessage, $hxConfig) {
		$url = $hxConfig->url . 'messages';
		$headers=array(
			'Authorization:Bearer '.self::getAccessToken($hxConfig),
		);
		// 发送数据
		$postData = array (
			'target_type' => 'users',
			'target' => $targets,
			'msg' => array (
				'type' => 'cmd',
				'action' => $action
			),
			'ext' => $extMessage
		);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT,5);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
		curl_exec($ch);
		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		return $http_status == 200;
	}

    // 发送扩展消息
    public static function sendExtMessages($userName, $targetName, $message, $extMessage, $hxConfig){
        $url = $hxConfig->url . 'messages';
        $headers=array(
            'Content-Type:application/json',
            'Authorization:Bearer '.self::getAccessToken($hxConfig),
        );

        //组合消息数据
        $data['target_type']='users';
        $data['target']= is_array($targetName) ? $targetName : array($targetName);
        $data['msg']=array( 'type'=>'txt', 'msg'=>$message, );
        $data['from']=$userName;
        $data['ext'] = $extMessage;

        $postData=json_encode($data);

        //发送
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT,5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        $result = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $http_status == 200;
    }
}