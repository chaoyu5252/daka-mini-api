<?php

namespace Fichat\Common;

use Fichat\Constants\ErrorConstantsManager;
use Fichat\Models\Moments;
use Fichat\Models\MomentsLike;
use Fichat\Models\MomentsReply;
use Fichat\Models\RedPacket;
use Fichat\Models\RewardTask;
use Fichat\Models\SystemHot;
use Fichat\Proxy\OssProxy;
use Fichat\Utils\Emoji;
use Fichat\Utils\OssApi;
use Fichat\Utils\RedisClient;
use Fichat\Utils\Utils;
use Phalcon\Mvc\Model\Query;

define('KAKA_AVATAR', 'http://dakaapp-group.oss-cn-beijing.aliyuncs.com/kaka_group.png');

class ReturnMessageManager {
	
	public static function buildReturnMessage($errorCode, $data = null){
		$returnMessage['error_code'] = $errorCode;
		if($errorCode != 'E0000'){
			$returnMessage['message'] = ErrorConstantsManager::$errorMessageList[$errorCode];
		}
		if($data){ $returnMessage['data'] = $data; }
//		var_dump($returnMessage);
//		return json_encode($returnMessage, JSON_UNESCAPED_UNICODE);
        return $returnMessage;
	}

	/*
	 * TODO 人物相关
	 */ 
	
	// 处理用户详细信息
	public static function buildUserInfo($user, $token, $userAssociation, $fansAndFollowsNum, $momentsNum, $giftNum) {;
		$data = $user->toArray();
		$data['userId'] = $user->id;
		$data['displayId'] = $user->account_id;
		$data['token'] = $token ? $token : '';
		$data['title'] = $user->Title->name;
//		$data['group_id'] = $userAssociation ? $userAssociation->association->group_id : 0;
//		$data['association_id'] = $userAssociation ? $userAssociation->association->group_id : 0;
		$data['phone'] = $user->phone;
		$data['userAvatar'] = OssProxy::procOssPic($user->user_avatar);
//        $data['user_thumb'] = OssApi::procOssThumb($user->user_thumb);
		$data['fansNum'] = $fansAndFollowsNum['fansNum'];
		$data['followsNum'] = $fansAndFollowsNum['followsNum'];
		$data['momentsNum'] = $momentsNum;
        $data['giftNum'] = isset($giftNum[0]) ? $giftNum[0]['sumatory'] : '0';
        $data['background'] = OssProxy::procOssPic($user->background);
        $data['background_thumb'] = OssProxy::procOssThumb($user->background_thumb);
        $data['title_pri_thumb'] = OssProxy::procOssThumb($user->Title->pri_thumb);
		// 检查是否已经过实名认证
		$data['realNameValid'] = 0;
		if ($data['id_code']) {
			$data['realNameValid'] = 1;
		}
		
		// 获取当前用户等级信息
        $userAttrList = DBManager::getUserAttrMoreThanCurrentLevel($user->level);
		$maxLevelIndex = count($userAttrList) - 1;
		if($user->level < $userAttrList[$maxLevelIndex]['level']) {
            $maxUserLevelExp = $userAttrList[1]['exp'];
            // 经验百分比
            $expPercent = sprintf("%.2f", ($user->exp / $maxUserLevelExp) * 100);
        } else {
            $maxUserLevelExp = $userAttrList[$maxLevelIndex]['exp'];
            $expPercent = '100';
        }
		$data['expPercent'] = $expPercent;
		$data['exp'] = $user->exp;
		$data['userLevel'] = $user->level;
		$data['levelMaxExp'] = $maxUserLevelExp;
        // 获取用户所有红包余额
		$data['redPacketSumBalance'] = DBManager::getUserRedPacketSumBalance($user->id);

        unset($data['id']);
		unset($data['name']);
		unset($data['id_code']);
		unset($data['level']);
		unset($data['account_id']);
		unset($data['user_avatar']);
		unset($data['user_thumb']);
		return $data;
	}

	// 处理用户详细数据(别人的)
    public static function buildTargetInfo($user)
    {
        $data = $user->toArray();
        $data['userId'] = $user->id;
        $data['displayId'] = $user->account_id;
        $data['title'] = $user->Title->name;
        $data['phone'] = $user->phone;
        $data['userAvatar'] = OssProxy::procOssPic($user->user_avatar);
        $data['background'] = OssProxy::procOssPic($user->background);
        $data['background_thumb'] = OssProxy::procOssThumb($user->background_thumb);
        $data['title_pri_thumb'] = OssProxy::procOssThumb($user->Title->pri_thumb);
        /** 去除隐私数据 */
        unset($data['id']);
        unset($data['exp']);
        unset($data['balance']);
        unset($data['id_code']);
	    unset($data['name']);
        unset($data['account_id']);
        unset($data['user_avatar']);
        // 获取当前用户等级信息
        $userAttrList = DBManager::getUserAttrMoreThanCurrentLevel($user->level);
        $maxLevelIndex = count($userAttrList) - 1;
        if($user->level < $userAttrList[$maxLevelIndex]['level']) {
            $maxUserLevelExp = $userAttrList[1]['exp'];
            // 经验百分比
            $expPercent = sprintf("%.2f", ($user->exp / $maxUserLevelExp) * 100);
        } else {
            $maxUserLevelExp = $userAttrList[$maxLevelIndex]['exp'];
            $expPercent = '100';
        }
	    $data['userLevel'] = $user->level;
	    $data['expPercent'] = $expPercent;
	    $data['exp'] = $user->exp;
	    $data['levelMaxExp'] = $maxUserLevelExp;
	    $data['redPacketSumBalance'] = DBManager::getUserRedPacketSumBalance($user->id);
        /** 返回结果 */
        return $data;
    }
	
	// 处理用户数据
	public static function buildUser($user) {
		$data = array();
		$data['userId'] = $user->id;
		$data['nickname'] = $user->nickname;
		$data['userAvatar'] = OssProxy::procOssPic($user->user_avatar);
//        $data['user_thumb'] = OssApi::procOssThumb($user->user_thumb);
		$data['verify'] = $user->verify;
	    $data['gender'] = $user->gender;
	    $data['userLevel'] = $user->level;
	    $data['title'] = $user->Title->name;

		return $data;
	}

	// 处理搜索结果
    public static function buildSearchResult($userList = null, $familyList = null, $familyMembers = null) {
        $data = array();
        $i = 0;
        // 处理用户
        if($userList) {
            foreach ($userList as $user) {
                $data[$i]['userId'] = $user->id;
                $data[$i]['nickname'] = $user->nickname;
                $data[$i]['userAvatar'] = OssProxy::procOssPic($user->user_avatar);
                $data[$i]['verify'] = $user->verify;
                $data[$i]['gender'] = $user->gender;
                $data[$i]['userLevel'] = $user->level;
                $data[$i]['title'] = $user->Title->name;
                $i++;
            }
        }
        // 处理家族
        if($familyList) {
            foreach ($familyList as $association) {
                $data[$i]['id'] = $association->group_id;
                $data[$i]['nickname'] = $association->nickname;
                $data[$i]['owner_nickname'] = $association->user->nickname;
                $data[$i]['familyLevel'] = $association->level;
                $data[$i]['familyAvatar'] = OssApi::procOssPic($association->assoc_avatar);
//		$data[$i]['assoc_thumb'] = OssApi::procOssThumb($association->assoc_thumb);
                $data[$i]['bulletin'] = $association->bulletin;
                $data[$i]['current_number'] = $association->current_number;
                $data[$i]['confirm'] = $association->confirm;
                $data[$i]['info'] = $association->info;
                $i++;
            }
        }
        // 处理家族成员
        if($familyMembers) {
            foreach ($familyMembers as $member) {
                $data[$i]['userId'] = $member->user->id;
                $data[$i]['nickname'] = $member->user->nickname;
                $data[$i]['userLevel'] = $member->user->level;
                $data[$i]['userAvatar'] = OssProxy::procOssPic($member->user->user_avatar);
                $data[$i]['gender'] = $member->user->gender;
//                    $data[$i]['user_thumb'] = OssApi::procOssThumb($member->user->user_thumb);
                $data[$i]['userType'] = $member->user_type;
                $data[$i]['addTime'] = $member->add_time;
                $data[$i]['phone'] = $member->user->phone;
                $data[$i]['familyMemberLevel'] = $member->level;
                $data[$i]['familyMemberTitle'] = $member->member_title->title;
                $i++;
            }
        }
        return $data;
    }

	// 处理用户称号
	public static function buildUserTitle($titles, $user) {
		$data = array();
		$i = 0;
		foreach($titles as $title){
			$data[$i]['id'] = $title->id;
			$data[$i]['name'] = $title->name;
			$data[$i]['demand'] = $title->demand;
            $data[$i]['pri_url'] = OssApi::procOssPic($title->pri_url);
            $data[$i]['pri_thumb'] = OssApi::procOssThumb($title->pri_thumb);
			// 判断是否使用
			if($title->id == $user->title_id){
				$data[$i]['is_use'] = 1;
				$myTitle['id'] = $title->id;
				$myTitle['name'] = $title->name;
				$myTitle['demand'] = $title->demand;
                $myTitle['pri_url'] = $data[$i]['pri_url'];
                $myTitle['pri_thumb'] = $data[$i]['pri_thumb'];
                $myTitle['is_use'] = 1;
				$myTitle['is_own'] = 1;
			} else {
                $data[$i]['is_use'] = 2;

            }

            // 判断是否拥有
			if($title->demand <= $user->level){
				$data[$i]['is_own'] = 1;
			} else {
                $data[$i]['is_own'] = 2;

            }
				
			$i++;

		}
	
		return ['titleList' => $data, 'myTitle' => $myTitle];
	}
	
	/*
	 *  TODO 好友相关
	 */ 

	// 处理随机用户数据
	public static function buildRandomPlayerData($randomPeopleData) {
		$peopleData = array();
		$i = 0;
		foreach($randomPeopleData as $randomPeople){
			$peopleData[$i]['id'] = $randomPeople->id;
			$peopleData[$i]['nickname'] = $randomPeople->nickname;
			$peopleData[$i]['gender'] = $randomPeople->gender;
//			$peopleData[$i]['title'] = $randomPeople->Title->name;
			$peopleData[$i]['userAvatar'] = OssProxy::procOssPic($randomPeople->user_avatar);
//            $peopleData[$i]['user_thumb'] = OssApi::procOssThumb($randomPeople->user_thumb);
			$peopleData[$i]['userLevel'] = $randomPeople->level;
//			$peopleData[$i]['phone'] = $randomPeople->phone;
//			$peopleData[$i]['verify'] = $randomPeople->verify;
			$i++;
		}
	
		return $peopleData;
	}

	// 处理好友列表数据3:好友列表,4:被关注列表,5:关注列表
	public static function builGetFriendsData($friendsObj, $type) {
		$data = array();
		$i = 0;
		foreach($friendsObj as $friend){
            if($type == 3){
            	if ($friend->friend->nickname){
		            $data[$i]['userId'] = $friend->friend_id;
		            $data[$i]['nickname'] = $friend->friend->nickname;
		            $data[$i]['gender'] = $friend->friend->gender;
		            $data[$i]['userAvatar'] = OssProxy::procOssPic($friend->friend->user_avatar);
//		            $data[$i]['user_thumb'] = OssApi::procOssThumb($friend->friend->user_thumb);
		            $data[$i]['userLevel'] = $friend->friend->level;
	            }
//				$data[$i]['level'] = $friend->friend->level;
//				$data[$i]['phone'] = $friend->friend->phone;
//				$data[$i]['confirm'] = $friend->confirm;
//				$data[$i]['disturb'] = $friend->disturb;
//				$data[$i]['relationship'] = 3;
			}else if($type == 4){
	            if ($friend->user->nickname) {
		            $data[$i]['userId'] = $friend->user_id;
		            $data[$i]['nickname'] = $friend->user->nickname;
		            $data[$i]['gender'] = $friend->user->gender;
//				$data[$i]['title'] = $friend->user->Title->name;
		            $data[$i]['userAvatar'] = OssProxy::procOssPic($friend->user->user_avatar);
//		            $data[$i]['user_thumb'] = OssApi::procOssThumb($friend->user->user_thumb);
		            $data[$i]['mutual_fans'] = $friend->confirm;
		            $data[$i]['is_new'] = $friend->is_new;
		            $data[$i]['userLevel'] = $friend->user->level;
	            }
//				$data[$i]['phone'] = $friend->user->phone;
//				$data[$i]['relationship'] = 4;
			}else if($type == 5){
				$data[$i]['userId'] = $friend->target_id;
				$data[$i]['nickname'] = $friend->attention->nickname;
				$data[$i]['gender'] = $friend->attention->gender;
//				$data[$i]['title'] = $friend->attention->Title->name;
                $data[$i]['userAvatar'] = OssProxy::procOssPic($friend->attention->user_avatar);
//                $data[$i]['user_thumb'] = OssApi::procOssThumb($friend->attention->user_thumb);
                $data[$i]['mutual_fans'] = $friend->confirm;
				$data[$i]['userLevel'] = $friend->attention->level;
//				$data[$i]['phone'] = $friend->attention->phone;
//				$data[$i]['status'] = $friend->confirm;
//				$data[$i]['relationship'] = 5;
			}
			$i++;
		}

        $result = self::sortByFirstName($data);
		return $result;
	}

    /**
     * 屏蔽群组列表数据
     *
     * @param $groupList
     * @return array
     */
    public function buildGetGroupChatMessageFreeList($groupList)
    {
        $data = array();
        foreach ($groupList as $key => $groupChat) {
            array_push($data, $groupChat->association->group_id);
//            $data[$key]['id'] = $groupChat->association->group_id;
//            $data[$key]['nickname'] = $groupChat->nickname;
//            $data[$key]['familyAvatar'] = OssProxy::procOssPic($groupChat->association->assoc_avatar);
//            $data[$key]['familyThumb'] = OssProxy::procOssThumb($groupChat->association->assoc_thumb);
//            $data[$key]['familyLevel'] = $groupChat->association->level;
        }
        return $data;
	}

    /**
     * 按首字母排序
     *
     * @param $userList
     * @return array
     */
    public static function sortByFirstName($userList)
    {
        $result = array();
        foreach ($userList as $key =>$value) {
            $nickname = $value['nickname'];
            $first = '';
            // 获取拼音首字母
            for ($i = 0; $i< mb_strlen($nickname); $i++) {
                $firstStr = mb_substr($nickname, $i, 1);
                $first .= self::_getFirstCharter($firstStr);
            }
            $result[] = $first;
        }
        array_multisort($result,SORT_ASC, SORT_STRING, $userList);
        return $userList;
    }
	
    /**
     * 获取首字母
     * 
     * @param $str
     * @return null|string
     */
    public static function _getFirstCharter($str){
        if (empty($str)) {
            return '';
        }
        $fchar = ord($str{0});
        if ($fchar >= ord('A') && $fchar <= ord('z')) return strtoupper($str{0});
        $s1 = iconv('UTF-8', 'gb2312', $str);
        $s2 = iconv('gb2312', 'UTF-8', $s1);
        $s = $s2 == $str ? $s1 : $str;
        $asc = ord($s{0}) * 256 + ord($s{1}) - 65536;
        if ($asc >= -20319 && $asc <= -20284) return 'A';
        if ($asc >= -20283 && $asc <= -19776) return 'B';
        if ($asc >= -19775 && $asc <= -19219) return 'C';
        if ($asc >= -19218 && $asc <= -18711) return 'D';
        if ($asc >= -18710 && $asc <= -18527) return 'E';
        if ($asc >= -18526 && $asc <= -18240) return 'F';
        if ($asc >= -18239 && $asc <= -17923) return 'G';
        if ($asc >= -17922 && $asc <= -17418) return 'H';
        if ($asc >= -17417 && $asc <= -16475) return 'J';
        if ($asc >= -16474 && $asc <= -16213) return 'K';
        if ($asc >= -16212 && $asc <= -15641) return 'L';
        if ($asc >= -15640 && $asc <= -15166) return 'M';
        if ($asc >= -15165 && $asc <= -14923) return 'N';
        if ($asc >= -14922 && $asc <= -14915) return 'O';
        if ($asc >= -14914 && $asc <= -14631) return 'P';
        if ($asc >= -14630 && $asc <= -14150) return 'Q';
        if ($asc >= -14149 && $asc <= -14091) return 'R';
        if ($asc >= -14090 && $asc <= -13319) return 'S';
        if ($asc >= -13318 && $asc <= -12839) return 'T';
        if ($asc >= -12838 && $asc <= -12557) return 'W';
        if ($asc >= -12556 && $asc <= -11848) return 'X';
        if ($asc >= -11847 && $asc <= -11056) return 'Y';
        if ($asc >= -11055 && $asc <= -10247) return 'Z';
        return null;
    }


	// 请求好友列表
	public static function buildFriendRequestList($friendRequestList, $invitList, $members) {
		$friend = array();
		$i = 0;
		foreach($friendRequestList as $friendRequest){
			$friend[$i]['id'] = $friendRequest->user_id;
			$friend[$i]['nickname'] = $friendRequest->user->nickname;
			$friend[$i]['title'] = $friendRequest->user->Title->name;
			$friend[$i]['userAvatar'] = OssProxy::procOssPic($friendRequest->user->user_avatar);
//            $friend[$i]['user_thumb'] = OssApi::procOssThumb($friendRequest->user->user_thumb);
			$friend[$i]['userLevel'] = $friendRequest->user->level;
			$friend[$i]['phone'] = $friendRequest->user->phone;
			$friend[$i]['message'] = $friendRequest->message;
			$friend[$i]['status'] = $friendRequest->status;
			$friend[$i]['group_id'] = ' ';
			$friend[$i]['type'] = 1;
			$i++;
		}
	
		$invitAssociation = array();
		$j = 0;
		foreach($invitList as $invit){
			$invitAssociation[$j]['id'] = $invit->inviter_id;
			$invitAssociation[$j]['nickname'] = $invit->inviter->nickname;
			$invitAssociation[$j]['title'] = $invit->inviter->Title->name;
			$invitAssociation[$j]['userAvatar'] = OssProxy::procOssPic($invit->inviter->user_avatar);
//            $invitAssociation[$j]['user_thumb'] = OssApi::procOssThumb($invit->inviter->user_thumb);
			$invitAssociation[$j]['userLevel'] = $invit->inviter->level;
			$invitAssociation[$j]['phone'] = $invit->inviter->phone;
			$invitAssociation[$j]['message'] = $invit->message;
			$invitAssociation[$j]['status'] = $invit->status;
			$invitAssociation[$j]['group_id'] = $invit->association->group_id;
			$invitAssociation[$j]['type'] = 2;
			$j++;
		}
	
		$associationApply = array();
		if($members){
			$k = 0;
			foreach($members as $member){
				$associationApply[$k]['id'] = $member->user_id;
				$associationApply[$k]['nickname'] = $member->user->nickname;
				$associationApply[$k]['title'] = $member->user->Title->name;
				$associationApply[$k]['userAvatar'] = OssProxy::procOssPic($member->user->user_avatar);
//                $associationApply[$k]['user_thumb'] = OssApi::procOssThumb($member->user->user_thumb);
				$associationApply[$k]['userLevel'] = $member->user->level;
				$associationApply[$k]['phone'] = $member->user->phone;
				$associationApply[$k]['message'] = $member->message;
				$associationApply[$k]['status'] = $member->status;
				$associationApply[$k]['group_id'] = $member->association->group_id;
				$associationApply[$k]['name'] = $member->association->nickname;
				$associationApply[$k]['type'] = 3;
				$k++;;
			}
		}

		$list = array_merge($friend, $invitAssociation, $associationApply);

		return $list;
	}

    /**
     * 组装申请列表信息
     *
     * @param $requestList  // 请求列表
     * @return array
     */
    public static function buildApplyListInfo($type, $requestList) {
        // 申请列表
        $applyList = array();
        // 索引
        $i = 0;
        // 好友列表
        switch ($type) {
            case 1:
                foreach($requestList as $friendRequest){
                    $applyList[$i]['messageType'] = 1;
                    $applyList[$i]['requestId'] = $friendRequest->id;
                    $applyList[$i]['userId'] = $friendRequest->user_id;
                    $applyList[$i]['nickname'] = $friendRequest->user->nickname;
                    $applyList[$i]['userLevel'] = $friendRequest->user->level;
                    $applyList[$i]['userAvatar'] = OssProxy::procOssPic($friendRequest->user->user_avatar);
                    $applyList[$i]['message'] = $friendRequest->message;
                    $applyList[$i]['status'] = $friendRequest->status;
                    $i++;
                }
                break;
            case 2:
                foreach($requestList as $familyRequest){
                    $applyList[$i]['messageType'] = $familyRequest->inviter_id == 0 ? 2 : 3;
                    $applyList[$i]['requestId'] = $familyRequest->id;
                    $applyList[$i]['userId'] = $familyRequest->inviter_id == 0 ? $familyRequest->user_id : $familyRequest->inviter_id;
                    $applyList[$i]['nickname'] = $familyRequest->inviter_id == 0 ? $familyRequest->user->nickname : $familyRequest->inviter->nickname;
                    $applyList[$i]['userLevel'] = $familyRequest->inviter_id == 0 ? $familyRequest->user->level : $familyRequest->inviter->level;
                    $applyList[$i]['userAvatar'] = $familyRequest->inviter_id == 0 ? OssProxy::procOssPic($familyRequest->user->user_avatar) : OssProxy::procOssPic($familyRequest->inviter->user_avatar);
                    $applyList[$i]['message'] = '';
                    $applyList[$i]['status'] = $familyRequest->status;
                    $applyList[$i]['familyId'] = $familyRequest->association->group_id;
                    $applyList[$i]['familyName'] = $familyRequest->association->nickname;
                    $i++;
                }
                break;
        }
        return $applyList;
    }
    
    /**
     * 推荐好友
     *
     */
    public static function buildRcmdUserList($di, $users)
    {
    	$redis = RedisClient::create($di->get('config')['redis']);
    	$return = [];
        foreach($users as $user) {
        	$activity = RedisManager::getUserActivity($redis, $user->u->id);
            array_push($return, [
	            "userId" => $user->u->id,
		        "userAvatar" => OssProxy::procOssPic($user->u->user_avatar),
//		        "userThumb" => OssProxy::procOssThumb($user->u->user_thumb),
		        "userLevel" => $user->u->level,
		        "nickname" => $user->u->nickname,
		        "gender" => $user->u->gender,
		        "activeDegree" => $activity,
		        "sumBalance" => $user->sum_balance
            ]);
        }
        $redis->close();
        return $return;
    }

	/*
	 *  TODO 家族群聊相关
	 */
	
	// 处理公会数据
	public static function buildAssociation($association) {
		$data = array ();
		$data['id'] = $association->group_id;
		$data['nickname'] = $association->nickname;
		$data['owner_nickname'] = $association->user->nickname;
		$data['familyLevel'] = $association->level;
		$data['familyAvatar'] = OssApi::procOssPic($association->assoc_avatar);
//		$data['assoc_thumb'] = OssApi::procOssThumb($association->assoc_thumb);
		$data['bulletin'] = $association->bulletin;
		$data['current_number'] = $association->current_number;
		$data['max_number'] = $association->max_number;
        $data['confirm'] = $association->confirm;
        $data['shut_up'] = $association->shut_up;
        $data['info'] = $association->info;
		return ['familyInfo' => $data];
	}
	
	// 处理公会全部信息
	public static function buildAssociationMember($association, $associationMember, $userNickname) {
		$data = array();
		$i = 0;
		$j = 0;
		foreach($associationMember as $member){
            if ($member->user_type != 3) {
                $data['admin'][$i]['familyId'] = $association->group_id;
                $data['admin'][$i]['userId'] = $member->user->id;
                $data['admin'][$i]['nickname'] = $member->user->nickname;
                $data['admin'][$i]['userLevel'] = $member->user->level;
                $data['admin'][$i]['userAvatar'] = OssProxy::procOssPic($member->user->user_avatar);
                if($association->type == 1) {
                    $data['admin'][$i]['gender'] = $member->user->gender;
//                    $data['admin'][$i]['user_thumb'] = OssApi::procOssThumb($member->user->user_thumb);
                    $data['admin'][$i]['userType'] = $member->user_type;
                    $data['admin'][$i]['addTime'] = $member->add_time;
                    $data['admin'][$i]['phone'] = $member->user->phone;
                    $data['admin'][$i]['familyMemberLevel'] = $member->level;
                    $data['admin'][$i]['familyMemberTitle'] = $member->member_title->title;
                }
                $i++;
            } else {
                $data['members'][$j]['familyId'] = $association->group_id;
                $data['members'][$j]['userId'] = $member->user->id;
                $data['members'][$j]['nickname'] = $member->user->nickname;
                $data['members'][$j]['userLevel'] = $member->user->level;
                $data['members'][$j]['userAvatar'] = OssProxy::procOssPic($member->user->user_avatar);
                if($association->type == 1) {
                    $data['members'][$j]['gender'] = $member->user->gender;
//                    $data['members'][$j]['user_thumb'] = OssApi::procOssThumb($member->user->user_thumb);
                    $data['members'][$j]['userType'] = $member->user_type;
                    $data['members'][$j]['addTime'] = $member->add_time;
                    $data['members'][$j]['phone'] = $member->user->phone;
                    $data['members'][$j]['familyMemberLevel'] = $member->level;
                    $data['members'][$j]['familyMemberTitle'] = $member->member_title->title;
                }
                $j++;
            }
		}
		
		return $data;
	}

    // 处理家族、群聊信息
    public static function buildFamilyDetailInfo($userId, $association, $userAssociation = null, $associationMember = null) {
        $data = array();
        // 默认是非成员
        $user_type = 4;
        // 检查用户ID是否和家族创建者
	    $showAdminPerm = false;
        if ($userId == $association->owner_id) {
	        $showAdminPerm = true;
        }
        // 检查当前用户的家族内发言模式
        $speakMode = $association->speak_mode;
        if($userAssociation && $userAssociation->shut_up == 1) {
            $speakMode = 3;
        }
        $perm = '00000000';
        $groupName = "";
        $groupAvatar = "";
        if ($associationMember) {
            $i = 0;
            $j = 0;
            foreach($associationMember as $key => $member) {
            	// 检查成员ID是否和操作ID相同
                if ($member->member_id == $userId) {
                    $data['familyInfo']['avoidStatus'] = $member->confirm;
	                $user_type = $member->user_type;
                	if ($association->type == 2 && $user_type == 2) {
		                $user_type = 3;
	                }
	                $perm = $member->perm;
                }

                if ($member->user_type != 3) {
                    $data['admin'][$i]['familyId'] = $association->group_id;
                    $data['admin'][$i]['userId'] = $member->user->id;
                    $data['admin'][$i]['nickname'] = $member->user->nickname;
                    $data['admin'][$i]['userLevel'] = $member->user->level;
                    $data['admin'][$i]['userAvatar'] = OssProxy::procOssPic($member->user->user_avatar);
                    if($association->type == 1) {
                        $data['admin'][$i]['gender'] = $member->user->gender;
//                    $data['admin'][$i]['user_thumb'] = OssApi::procOssThumb($member->user->user_thumb);
                        $data['admin'][$i]['userType'] = $member->user_type;
                        $data['admin'][$i]['addTime'] = $member->add_time;
                        $data['admin'][$i]['phone'] = $member->user->phone;
                        $data['admin'][$i]['familyMemberLevel'] = $member->level;
                        $data['admin'][$i]['familyMemberTitle'] = $member->member_title->title;
                        $data['admin'][$i]['isShutUp'] = $member->shut_up;
                    }
                    if ($showAdminPerm) {
                        $data['admin'][$i]['memberPerm'] = $member->perm;
                    }
                    $i++;
                } else {
                    $data['members'][$j]['familyId'] = $association->group_id;
                    $data['members'][$j]['userId'] = $member->user->id;
                    $data['members'][$j]['nickname'] = $member->user->nickname;
                    $data['members'][$j]['userLevel'] = $member->user->level;
                    $data['members'][$j]['userAvatar'] = OssProxy::procOssPic($member->user->user_avatar);
                    if($association->type == 1) {
                        $data['members'][$j]['gender'] = $member->user->gender;
//                    $data['members'][$j]['user_thumb'] = OssApi::procOssThumb($member->user->user_thumb);
                        $data['members'][$j]['userType'] = $member->user_type;
                        $data['members'][$j]['addTime'] = $member->add_time;
                        $data['members'][$j]['phone'] = $member->user->phone;
                        $data['members'][$j]['familyMemberLevel'] = $member->level;
                        $data['members'][$j]['familyMemberTitle'] = $member->member_title->title;
                        $data['members'][$j]['isShutUp'] = $member->shut_up;
                    }
                    $j++;
                }

                //如果是群组，获取群组前三名用户的用户名和头像
                if($association->type == 2 && $key < 3) {
                    $groupName .= $key == 2 ? $member->user->nickname : $member->user->nickname . "、";
                    // $groupAvatar .= $key == 2 ? OssProxy::procOssPic($member->user->user_avatar) : OssProxy::procOssPic($member->user->user_avatar) . ",";
                }
            }
        }
	    
	    // 获取用户等级经验信息
	    $assocLevel = DBManager::getAssocLevel($association->level);
        $maxLevelIndex = count($assocLevel) - 1;
        if($association->level < $assocLevel[$maxLevelIndex]['level']) {
            $maxAssocLevelExp = (int)$assocLevel[1]['exp'];
            // 经验百分比
            $expPercent = sprintf("%.2f", ($association->exp / $maxAssocLevelExp) * 100);
        } else {
            $maxAssocLevelExp = (int)$assocLevel[$maxLevelIndex]['exp'];
            $expPercent = 100.00;
        }

        // 获取家族所有悬赏任务的余额
	    $taskSumBalance = DBManager::getAssocRewardTaskSumBalance($association->group_id);
        
        // 构建家族详细信息
        $data['familyInfo']['id'] = $association->id;
        $data['familyInfo']['familyId'] = $association->group_id;
        $data['familyInfo']['displayFamilyId'] = $association->assoc_id;
        $data['familyInfo']['familyName'] = $groupName;
        $data['familyInfo']['familyAvatar'] = OssApi::procOssPic($association->assoc_avatar);
        $data['familyInfo']['type'] = $association->type;
        $data['familyInfo']['userType'] = $user_type;
        $data['familyInfo']['currentNumber'] = $association->current_number;
        $data['familyInfo']['maxNumber'] = $association->max_number;
        if ($association->type == 1) {
            $data['familyInfo']['familyName'] = $association->nickname;
            $data['familyInfo']['owner_id'] = $association->owner_id;
            $data['familyInfo']['owner_nickname'] = $association->user->nickname;
            $data['familyInfo']['group_id'] = $association->group_id;
            $data['familyInfo']['type'] = $association->type;
            $data['familyInfo']['expPercent'] = $expPercent;
            $data['familyInfo']['exp'] = $association->exp;
            $data['familyInfo']['levelMaxExp'] = $maxAssocLevelExp;
            $data['familyInfo']['bulletin'] = $association->bulletin;
            $data['familyInfo']['familyLevel'] = $association->level;
//        $data['familyInfo']['assoc_thumb'] = OssApi::procOssThumb($association->assoc_thumb);
            $data['familyInfo']['create_time'] = $association->create_time;
            $data['familyInfo']['confirm'] = $association->confirm;
            $data['familyInfo']['rewardSumBalance'] = $taskSumBalance;
            $data['familyInfo']['info'] = $association->info;
            $data['familyInfo']['memberPerm'] = $perm;
            $data['familyInfo']['speakMode'] = $speakMode;
            $data['familyInfo']['speakTimeInterval'] = $speakMode == 2 ? $association->speak_time_interval : 0;
        }
        return $data;
    }
	
	// 处理公会列表
	public static function buildAssociationList($associationList, $applyAssociation) {
		$data = array();
		$i = 0;
		foreach($associationList as $key => $association){
			$data[$i]['id'] = $association->group_id;
			if($applyAssociation[$key]){
				$data[$i]['is_apply'] = 1;
			}else{
				$data[$i]['is_apply'] = 2;
        }
			$data[$i]['owner_id'] = $association->owner_id;
			$data[$i]['owner_nickname'] = $association->user->nickname;
			$data[$i]['group_id'] = $association->group_id;
			$data[$i]['name'] = $association->nickname;
			$data[$i]['level'] = $association->level;
			$data[$i]['bulletin'] = $association->bulletin;
			$data[$i]['familyAvatar'] = OssApi::procOssPic($association->assoc_avatar);
//            $data[$i]['assoc_thumb'] = OssApi::procOssThumb($association->assoc_thumb);
			$data[$i]['familyLevel'] = $association->level;
			$data[$i]['current_number'] = $association->current_number;
			$data[$i]['max_number'] = $association->max_number;
			$data[$i]['shut_up'] = $association->shut_up;
			$i++;
		}

		return $data;
	}

    /**
     * 拼接家族列表信息
     *
     * @param $myAssociationList
     * @param $strangerAssociationList
     * @param $applyAssociation
     * @return array
     */
    public static function buildMyAssociationList($myAssociationList, $recommandAssocList)
    {
        $myAssList = array();
        $k = 0;
        // 我的家族列表
        foreach ($myAssociationList as $myAssociation) {
            if ( $myAssociation->association->type == 1) {
                $myAssList[$k]['familyId'] = $myAssociation->association->group_id;
                $myAssList[$k]['familyName'] = $myAssociation->association->nickname;
                $myAssList[$k]['familyLevel'] = $myAssociation->association->level;
                $myAssList[$k]['familyAvatar'] = OssApi::procOssPic($myAssociation->association->assoc_avatar);
                $myAssList[$k]['current_number'] = $myAssociation->association->current_number;
                $myAssList[$k]['max_number'] = $myAssociation->association->max_number;
                $myAssList[$k]['type'] = $myAssociation->association->type;
                $k++;
            }
//            $myAssList[$k]['owner_id'] = $myAssociation->association->owner_id;
//            $myAssList[$k]['owner_nickname'] = $myAssociation->association->user->nickname;
//            $myAssList[$k]['group_id'] = $myAssociation->association->group_id;
//            $myAssList[$k]['confirm'] = $myAssociation->association->confirm;
//            $myAssList[$k]['create_time'] = $myAssociation->association->create_time;
//            $myAssList[$k]['shut_up'] = $myAssociation->association->shut_up;
//            $myAssList[$k]['user_type'] = 1;
//            $myAssList[$k]['bulletin'] = $myAssociation->association->bulletin;
//            $myAssList[$k]['assoc_thumb'] = OssApi::procOssThumb($myAssociation->association->assoc_thumb);
        }
        $rcmdAssociationList = array();
        // 推荐家族
        foreach ($recommandAssocList as $key => $rcmdAssociation) {
            $rcmdAssociationList[$key]['familyId'] = $rcmdAssociation->group_id;
            $rcmdAssociationList[$key]['familyName'] = $rcmdAssociation->nickname;
            $rcmdAssociationList[$key]['familyLevel'] = $rcmdAssociation->level;
            $rcmdAssociationList[$key]['familyAvatar'] = OssApi::procOssPic($rcmdAssociation->assoc_avatar);
            $rcmdAssociationList[$key]['current_number'] = $rcmdAssociation->current_number;
            $rcmdAssociationList[$key]['max_number'] = $rcmdAssociation->max_number;
            $rcmdAssociationList[$key]['confirm'] = $rcmdAssociation->confirm;
            $rcmdAssociationList[$key]['type'] = $rcmdAssociation->type;
        }

        // 返回数据
        return [
            'myFamilies' => $myAssList,
            'recommendFamilies' => $rcmdAssociationList,
        ];
	}


	// 处理添加群组成员
	public static function buildClusterMemberList($cluster, $clusterMemberList) {
		$data = array();
//		$data['groupInfo'] = $cluster->toArray();
		$data['groupInfo']['groupId'] = $cluster->group_id;
//		$data['groupInfo']['familyLevel'] = $cluster->level;
//		$data['groupInfo']['familyAvatar'] = $cluster->pri_url;
//		$data['groupInfo']['familyThumb'] = $cluster->pri_thumb;
		
        unset($data['groupInfo']['pri_url']);
		unset($data['groupInfo']['pri_thumb']);
        unset($data['groupInfo']['level']);

        $i = 0;
		foreach($clusterMemberList as $clusterMember){
			$data['clusterMember'][$i]['id'] = $clusterMember->member_id;
			$data['clusterMember'][$i]['nickname'] = $clusterMember->nickname;
			$data['clusterMember'][$i]['userLevel'] = $clusterMember->user->level;
			$data['clusterMember'][$i]['userAvatar'] = OssProxy::procOssPic($clusterMember->user->user_avatar);
//            $data['clusterMember'][$i]['user_thumb'] = OssApi::procOssThumb($clusterMember->user->user_thumb);
			$data['clusterMember'][$i]['userType'] = $clusterMember->user_type;
			$data['clusterMember'][$i]['addTime'] = $clusterMember->add_time;
			$data['clusterMember'][$i]['phone'] = $clusterMember->user->phone;
			$i++;
		}
		
		return $data;
	}
	
	// 处理公会、群聊成员信息
//	public static function buildGroupMembers($members) {
//		$data = array();
//		$i = 0;
//		foreach($members as $member){
//			$data[$i]['id'] = $member->member_id;
//			$data[$i]['nickname'] = $member->nickname;
//			$data[$i]['userLevel'] = $member->level;
//			$data[$i]['userAvatar'] = OssProxy::procOssPic($member->user->user_avatar);
////            $data[$i]['user_thumb'] = OssApi::procOssThumb($member->user->user_thumb);
//			$data[$i]['user_type'] = $member->user_type;
//			$data[$i]['add_time'] = $member->add_time;
//			$data[$i]['phone'] = $member->user->phone;
//			$i++;
//		}
//
//		return $data;
//	}


	/*
	 *  TODO 朋友圈相关
	 */
	
	// 处理发表说说返回数据
	public static function buildMoments($moments) {
		$data = array();
        // 说说图片
//        $priInfo = Utils::evoMomentPri($moments);

		$data['id'] = $moments->id;
		$data['user_id'] = $moments->user_id;
		$data['content'] = $moments->content;
		$data['create_time'] = $moments->create_time;
		$data['pri_url'] = OssApi::procOssPic($moments->pri_url);;
        $data['pri_thumb'] = OssApi::procOssThumb($moments->pri_thumb);
		$data['pri_preview'] = $moments->pri_preview;
  
		return $data;
	}
	
	// 处理单个说说全部信息(说说内容、评论、点赞)
	public static function buildMomentsOnceData($moments, $allLike, $allReply, $like) {
//		unset($moments['pri_url']);
        // 说说图片
//        $priInfo = Utils::evoMomentPri($moments);

		$data = array();
		$data['isLike'] = $like;
		$data['moments'] = $moments;
		$data['moments']['pri_url'] = OssApi::procOssPic($moments->pri_url);
        $data['moments']['pri_thumb'] = OssApi::procOssThumb($moments->pri_thumb);
        $data['moments']['pri_preview'] = $moments->pri_preview;
		// 点赞数据
		$data['like'] = array();
		$i = 0;
		foreach($allLike as $like){
			$data['like'][$i]['id'] = $like->id;
			$data['like'][$i]['moments_id'] = $like->moments_id;
			$data['like'][$i]['user_id'] = $like->user_id;
			$data['like'][$i]['user_name'] = $like->user->nickname;
			$i++;
		}
		
		// 评论数据
		$data['reply'] = array();
		$j = 0;
		foreach($allReply as $reply){
			$data['reply'][$j]['id'] = $reply->id;
			$data['reply'][$j]['moments_id'] = $reply->moments_id;
			$data['reply'][$j]['user_id'] = $reply->user_id;
			$data['reply'][$j]['user_name'] = $reply->user->nickname;
			$data['reply'][$j]['replyer_id'] = $reply->replyer_id;
			$data['reply'][$j]['replyer_name'] = $reply->replyer->nickname != null ? $reply->replyer->nickname : ' ';
			$data['reply'][$j]['content'] = $reply->content;
			$j++;
		}
		
		return $data;
	}
	
	// 处理好友、关注朋友圈数据
	public static function buildAllMomentsData($userId, $momentsList, $momentsAllReply, $momentsAllLike, $momentsAllGive, $nickName, $contentUser = true) {
		$data = array();
		$time = array();
		$i = 0;
		foreach($momentsList as $key => $moments) {
		    // 说说图片
//		    $priInfo = Utils::evoMomentPri($moments);

            $data[$i]['id'] = $moments->id;
            if ($contentUser) {
                $data[$i]['user_id'] = $moments->user->id;
	            $data[$i]['userLevel'] = $moments->user->level;
                $data[$i]['userAvatar'] = OssProxy::procOssPic($moments->user->user_avatar);
//                $data[$i]['user_thumb'] = OssApi::procOssThumb($moments->user->user_thumb);
                $data[$i]['nickname'] = $nickName[$key]->nickname;
            }
            $data[$i]['content'] = $moments->content;
//            $data[$i]['cover_pic_url'] = OssApi::procOssPic($moments->pri_url);
//            $data[$i]['cover_pic_thumb_url'] = OssApi::procOssThumb($moments->pri_thumb);
//            $data[$i]['cover_pic_preview'] = $moments->pri_preview;
			
			$data[$i]['coverPic'] = OssApi::procOssPic($moments->pri_url);
			$data[$i]['coverThumb'] = OssApi::procOssThumb($moments->pri_thumb);
			$data[$i]['coverPreview'] = $moments->pri_preview;
			
            $data[$i]['like'] = count($momentsAllLike[$key]);
            $data[$i]['reply'] = count($momentsAllReply[$key]);
            $data[$i]['reward'] = count($momentsAllGive[$key]);
            $data[$i]['isLike'] = 2;
            $data[$i]['redPacket_id'] = $moments->red_packet_id ? $moments->red_packet_id : '0';
            $data[$i]['is_time_red_packet'] = $moments->redPacket->start_time > $moments->redPacket->create_time ? '1' : '0';
            $data[$i]['red_packet_start_time'] = $moments->redPacket->start_time ? $moments->redPacket->start_time : '';
            $data[$i]['create_time'] = $moments->create_time;
            $data[$i]['isGrab'] = DBManager::getUserGrabRedPacket($moments->user_id, $moments->red_packet_id) ? '1' : '0';

            // 是否点赞
            foreach($momentsAllLike[$key] as $like){
				if($userId == $like->user_id){
					$data[$i]['isLike'] = 1;
				}
			}
			$i++;
            // 判断红包发送时间和红包开始抢时间
            if ($moments->redPacket->start_time > $moments->redPacket->create_time && $moments->redPacket->start_time < date('Y-m-d H:i:s')) {
                $time[] = $moments->redPacket->start_time;
            } else {
                $time[] = $moments->create_time;
            }
		}
		// 根据红包开始时间和发送时间排序
        array_multisort($time,SORT_DESC, $data);
		return $data;
	}
	
	// 构建用户作品列表
	public static function buildUserProduction($userGrabRedpackRecords, $momentsList)
	{
		$data = [];
		foreach($momentsList as $moment) {
            // 检查红包状态 0：无红包； 1：有但已经领取；2：有且可以领取；3：有且不可以领取
			$isGrab = 0;
            if($moment -> red_packet_id && $moment -> red_packet_id != 0) {
                if(array_key_exists($moment -> red_packet_id, $userGrabRedpackRecords)) {
                    $isGrab = 1;
                } else {
                    $isGrab = 2;
                    $redPacket = DBManager::getRedPacketInfo($moment -> red_packet_id);
                    if($redPacket->balance == 0) {
                        $isGrab = 3;
                    }
                }
            }

			array_push($data, [
				"momentId" => $moment ->id,
		        "coverPic" => OssApi::procOssPic($moment->pri_url),
		        "coverThumb" => OssApi::procOssThumb($moment->pri_thumb),
		        "coverPreview" => $moment->pri_preview,
		        "releaseTime" => date("Ym", strtotime($moment->create_time)),
		        "redPacketId" => $moment->red_packet_id,
				"isGrab" => $isGrab
			]);
		}
		return $data;
	}

	// 处理分页查询的点赞
    public static function buildMomentsLike($momentsLikeList) {
        $likeList = array();
        $j = 0;
        foreach($momentsLikeList as $like){
            $likeList[$j]['id'] = $like->id;
            $likeList[$j]['userId'] = $like->user_id;
            $likeList[$j]['nickname'] = $like->user->nickname;
            $likeList[$j]['userLevel'] = $like->user->level;
            $likeList[$j]['userAvatar'] = OssProxy::procOssPic($like->user->user_avatar);
//            $likeList[$j]['user_thumb'] = OssApi::procOssThumb($like->user->user_thumb);
            $likeList[$j]['time'] = $like->like_time;
            $j++;
        }
        return $likeList;
    }
	
	// 处理单个说说详情
	public static function buildUserMoments($user, $moments, $momentsReply, $momentsReplyTopThree, $momentsAllReply, $momentsLike, $momentsAllLike, $momentsGive, $like) {
	    // 说说图片
//        $priInfo = Utils::evoMomentPri($moments);

		$data = array();
		$data['moments']['id'] = $moments->id;
		$data['moments']['userId'] = $moments->user_id;
		$data['moments']['nickname'] = $moments->user->nickname;
		$data['moments']['userLevel'] = $moments->user->level;
		$data['moments']['userAvatar'] = OssProxy::procOssPic($moments->user->user_avatar);
		$data['moments']['content'] = $moments->content;
		$data['moments']['coverPic'] = OssApi::procOssPic($moments->pri_url);;
		$data['moments']['coverThumb'] = OssApi::procOssThumb($moments->pri_thumb);
		$data['moments']['coverPreview'] = $moments->pri_preview;
		$data['moments']['create_time'] = $moments->create_time;
		$data['moments']['isLike'] = $like;
		$data['moments']['reward'] = count($momentsGive);
		$data['moments']['replyNumber'] = count($momentsAllReply);
		$data['moments']['likeNumber'] = count($momentsAllLike);
        $data['moments']['redPacketId'] = $moments->red_packet_id;
        //检查红包状态
        $isGrab = 0;
        if($moments -> red_packet_id && $moments -> red_packet_id != 0) {
            $redPacketRecord = DBManager::getUserGrabRedPacketRecord($user->id, $moments -> red_packet_id);
            if($redPacketRecord) {
                $isGrab = 1;
            } else {
                $isGrab = 2;
                $redPacket = DBManager::getRedPacketInfo($moments -> red_packet_id);
                if($redPacket->balance == 0) {
                    $isGrab = 3;
                }
            }
        }
        $data['moments']['isGrab'] = $isGrab;

        //热门评论
        if($momentsReplyTopThree) {
            $m = 0;
            foreach($momentsReplyTopThree as $reply){
                $data['hotReplyList'][$m]['id'] = $reply->id;
                $data['hotReplyList'][$m]['userId'] = $reply->user_id;
                $data['hotReplyList'][$m]['nickname'] = $reply->user->nickname;
                $data['hotReplyList'][$m]['userLevel'] = $reply->user->level;
                $data['hotReplyList'][$m]['userAvatar'] = OssProxy::procOssPic($reply->user->user_avatar);
//            $data['hotReplyList'][$m]['user_thumb'] = OssApi::procOssPic($reply->user->user_thumb);
                $data['hotReplyList'][$m]['content'] = $reply->status == 0 ? $reply->content : '该评论已删除！';
                $data['hotReplyList'][$m]['time'] = $reply->reply_time;
                $data['hotReplyList'][$m]['likeCount'] = $reply->like_count;
                $data['hotReplyList'][$m]['originReplyId'] = $reply->parent_id;
                $data['hotReplyList'][$m]['originReplyUserId'] = $reply->parent_id != 0 ? $reply->parentReply->user_id : '';
                $data['hotReplyList'][$m]['originReplyNickname'] = $reply->parent_id != 0 ? $reply->parentReply->user->nickname : '';
                $data['hotReplyList'][$m]['originReplyUserLevel'] = $reply->parent_id != 0 ? $reply->parentReply->user->level : 1;
                $data['hotReplyList'][$m]['originReplyUserAvatar'] = $reply->parent_id != 0 ? $reply->parentReply->user->user_avatar : '';
//            $data['hotReplyList'][$m]['originReplyUserThumb'] = $reply->replyer_id != '' ? $reply->replyer->user_thumb : '';
                if($reply->parent_id != 0) {
                    $data['hotReplyList'][$m]['originReplyContent'] = $reply->parentReply->status == 0 ? $reply->parentReply->content : '该评论已删除！';
                } else {
                    $data['hotReplyList'][$m]['originReplyContent'] = '';
                }

                // 检查当前用户是否对该评论点赞
                $momentsReplyLike = DBManager::getMomentsReplyLike($user->id, $reply->id);
                if($momentsReplyLike){
                    $isLike = 1; //已点赞
                }else{
                    $isLike = 2; //未点赞
                }
                $data['hotReplyList'][$m]['isLike'] = $isLike;
                $m++;
            }
        }

        //普通评论
        $i = 0;
        foreach($momentsReply as $reply){
            $data['replyList'][$i]['id'] = $reply->id;
            $data['replyList'][$i]['userId'] = $reply->user_id;
            $data['replyList'][$i]['nickname'] = $reply->user->nickname;
            $data['replyList'][$i]['userLevel'] = $reply->user->level;
            $data['replyList'][$i]['userAvatar'] = OssProxy::procOssPic($reply->user->user_avatar);
//            $data['replyList'][$i]['user_thumb'] = OssApi::procOssPic($reply->user->user_thumb);
            $data['replyList'][$i]['content'] = $reply->status == 0 ? $reply->content : '该评论已删除！';
            $data['replyList'][$i]['time'] = $reply->reply_time;
            $data['replyList'][$i]['likeCount'] = $reply->like_count;
            $data['replyList'][$i]['originReplyId'] = $reply->parent_id;
            $data['replyList'][$i]['originReplyUserId'] = $reply->parent_id != 0 ? $reply->parentReply->user_id : '';
            $data['replyList'][$i]['originReplyNickname'] = $reply->parent_id != 0 ? $reply->parentReply->user->nickname : '';
            $data['replyList'][$i]['originReplyUserLevel'] = $reply->parent_id != 0 ? $reply->parentReply->user->level : 1;
            $data['replyList'][$i]['originReplyUserAvatar'] = $reply->parent_id != 0 ? $reply->parentReply->user->user_avatar : '';
//            $data['replyList'][$i]['originReplyUserThumb'] = $reply->replyer_id != '' ? $reply->replyer->user_thumb : '';
            if($reply->parent_id != 0) {
                $data['replyList'][$i]['originReplyContent'] = $reply->parentReply->status == 0 ? $reply->parentReply->content : '该评论已删除！';
            } else {
                $data['replyList'][$i]['originReplyContent'] = '';
            }
            // 检查当前用户是否对该评论点赞
            $momentsReplyLike = DBManager::getMomentsReplyLike($user->id, $reply->id);
            if($momentsReplyLike){
                $isLike = 1; //已点赞
            }else{
                $isLike = 2; //未点赞
            }
            $data['replyList'][$i]['isLike'] = $isLike;
            $i++;
        }

        //点赞
		$j = 0;
		foreach($momentsLike as $like){
			$data['likeList'][$j]['id'] = $like->id;
            $data['likeList'][$j]['userId'] = $like->user_id;
			$data['likeList'][$j]['nickname'] = $like->user->nickname;
			$data['likeList'][$j]['userLevel'] = $like->user->level;
			$data['likeList'][$j]['userAvatar'] = OssProxy::procOssPic($like->user->user_avatar);
//            $data['likeList'][$j]['user_thumb'] = OssApi::procOssThumb($like->user->user_thumb);
			$data['likeList'][$j]['time'] = $like->like_time;
			$j++;
		}
		
		$k = 0;
		foreach($momentsGive as $give){
			$data['rewardList'][$k]['id'] = $give->user_id;
			$data['rewardList'][$k]['nickname'] = $give->user->nickname;
			$data['rewardList'][$k]['userLevel'] = $give->user->level;
			$data['rewardList'][$k]['userAvatar'] = OssProxy::procOssPic($give->user->user_avatar);
//            $data['rewardList'][$k]['user_thumb'] = OssApi::procOssThumb($give->user->user_thumb);
			$data['rewardList'][$k]['amount'] = $give->amount;
			$data['rewardList'][$k]['time'] = $give->give_time;
			$k++;
		}
		
		return $data;		
	}

	//处理分页查询评论列表的返回结果
	public static function buildMomentsReplyByPage($user, $momentsReply) {
        $data = array();
        $i = 0;
        foreach($momentsReply as $reply){
            $data['replyList'][$i]['id'] = $reply->id;
            $data['replyList'][$i]['userId'] = $reply->user_id;
            $data['replyList'][$i]['nickname'] = $reply->user->nickname;
            $data['replyList'][$i]['userLevel'] = $reply->user->level;
            $data['replyList'][$i]['userAvatar'] = OssProxy::procOssPic($reply->user->user_avatar);
            $data['replyList'][$i]['content'] = $reply->status == 0 ? $reply->content : '该评论已删除！';
            $data['replyList'][$i]['time'] = $reply->reply_time;
            $data['replyList'][$i]['likeCount'] = $reply->like_count;
            $data['replyList'][$i]['originReplyId'] = $reply->parent_id;
            $data['replyList'][$i]['originReplyUserId'] = $reply->parent_id != 0 ? $reply->parentReply->user_id : '';
            $data['replyList'][$i]['originReplyNickname'] = $reply->parent_id != 0 ? $reply->parentReply->user->nickname : '';
            $data['replyList'][$i]['originReplyUserLevel'] = $reply->parent_id != 0 ? $reply->parentReply->user->level : 1;
            $data['replyList'][$i]['originReplyUserAvatar'] = $reply->parent_id != 0 ? $reply->parentReply->user->user_avatar : '';
            if($reply->parent_id != 0) {
                $data['replyList'][$i]['originReplyContent'] = $reply->parentReply->status == 0 ? $reply->parentReply->content: '该评论已删除！';
            } else {
                $data['replyList'][$i]['originReplyContent'] = '';
            }

            // 检查当前用户是否对该评论点赞
            $momentsReplyLike = DBManager::getMomentsReplyLike($user->id, $reply->id);
            if($momentsReplyLike){
                $isLike = 1; //已点赞
            }else{
                $isLike = 2; //未点赞
            }
            $data['replyList'][$i]['isLike'] = $isLike;
            $i++;
        }
        return $data;
    }

    //处理单条评论的返回结果
    public static function buildMomentsReply($user, $reply) {
        $data = array();
        $data['id'] = $reply->id;
        $data['userId'] = $reply->user_id;
        $data['nickname'] = $reply->user->nickname;
        $data['userLevel'] = $reply->user->level;
        $data['userAvatar'] = OssProxy::procOssPic($reply->user->user_avatar);
        $data['content'] = $reply->status == 0 ? $reply->content : '该评论已删除！';
        $data['time'] = $reply->reply_time;
        $data['likeCount'] = $reply->like_count;
        $data['originReplyId'] = $reply->parent_id ? $reply->parent_id : '0';
        $data['originReplyUserId'] = $reply->parent_id != 0 ? $reply->parentReply->user_id : '';
        $data['originReplyNickname'] = $reply->parent_id != 0 ? $reply->parentReply->user->nickname : '';
        $data['originReplyUserLevel'] = $reply->parent_id != 0 ? $reply->parentReply->user->level : 1;
        $data['originReplyUserAvatar'] = $reply->parent_id != 0 ? $reply->parentReply->user->user_avatar : '';
        if($reply->parent_id != 0) {
            $data['originReplyContent'] = $reply->parentReply->status == 0 ? $reply->parentReply->content: '该评论已删除！';
        } else {
            $data['originReplyContent'] = '';
        }

        // 检查当前用户是否对该评论点赞
        $momentsReplyLike = DBManager::getMomentsReplyLike($user->id, $reply->id);
        if($momentsReplyLike){
            $isLike = 1; //已点赞
        }else{
            $isLike = 2; //未点赞
        }
        $data['isLike'] = $isLike;

        return $data;
    }

    /**
     * 获取用户朋友圈四张最新照片数据
     *
     * @param $momentsList
     * @return array
     */
    public static function buildUserMomentsFourPri($momentsList)
    {
        //获取全部图片,用|拼接
        $priArr = '';
        foreach ($momentsList as $moments)
        {
            // 判断是否有图片
            if (!$moments->pri_thumb) {
                continue;
            }
            // 获取每条说说的第一条
            $pri_urls = explode('|', $moments->pri_url);
            $thumbs = explode('|', $moments->pri_thumb);
            // 拼接缩略图地址
            $pic = OssApi::procOssPic($pri_urls[0]) . $thumbs[0] . '|';
            $priArr .= $pic;
        }
        //拼接数组，去除空数据
        $data = explode('|', $priArr);
        array_pop($data);

        //返回4条数据
        return array_slice($data,0,4);
    }


    /**
     * 处理支付宝订单信息
     *
     * @param $orderId
     * @param $orderInfo
     * @return array
     */
    public static function buildAliPayOrder($orderId, $orderInfo)
    {
        $data = array();
        $data['aliPayOrder']['orderId'] = $orderId;
        $data['aliPayOrder']['orderInfo'] = $orderInfo;
        return $data;
    }

    /**
     * 处理交易记录数据
     *
     * @param $rechargeRecord
     * @return array
     *
     */
    public static function buildRechargeRecord($rechargeRecord)
    {
        $data = array();
        foreach ($rechargeRecord as $key => $rechargeInfo) {
            $data[$key]['orderId'] = $rechargeInfo['order_num'];
            $data[$key]['orderAmount'] = $rechargeInfo['amount'];
            // 判断充值状态  0:失败,1:成功,2:进行中
            if ($rechargeInfo['status'] == 1 && $rechargeInfo['callback_data'] != NULL) {
                $data[$key]['orderStatus'] = 1;
            } else if (($rechargeInfo['status'] == 1 && $rechargeInfo['callback_data'] == NULL) || ($rechargeInfo['status'] != 1 && $rechargeInfo['callback_data'] != NULL)) {
                $data[$key]['orderStatus'] = 2;
            } else {
                $data[$key]['orderStatus'] = 0;
            }
            $data[$key]['orderType'] = $rechargeInfo['consum_type'];
            $data[$key]['orderTime'] = $rechargeInfo['create_date'];
            $data[$key]['orderBalance'] = $rechargeInfo['balance'];
            // 订单备注
            $data[$key]['orderRemarks'] = $rechargeInfo['remark'];
        }
        return $data;
    }

    /**
     * 处理钱包流水
     * @param $balanceFlowList
     * @return array
     */
    public static function buildBalanceFlow($balanceFlowList) {
        $data = array();
        foreach ($balanceFlowList as $key => $balanceFlow) {
            $data[$key]['orderId'] = $balanceFlow['id'];
            $data[$key]['orderType'] = $balanceFlow['op_type'];
            $data[$key]['orderAmount'] = $balanceFlow['op_amount'];
            $data[$key]['orderTime'] = $balanceFlow['create_time'];
        }
        return $data;
    }

    /**
     * 处理订单信息
     *
     * @param $orderInfo
     * @return array
     */
    public static function buildUserOrderInfo($orderInfo)
    {
        $data = array();
        $data['orderInfo']['orderId'] = $orderInfo->order_num;
        // 订单状态 0：失败,1：成功
        $data['orderInfo']['orderState'] = $orderInfo->status;
        $data['orderInfo']['orderAmount'] = $orderInfo->amount;
        $data['orderInfo']['orderType'] = $orderInfo->consum_type;
        // $data['orderInfo']['rechargeType'] = $orderInfo->recharge_type;
        $data['orderInfo']['pay_channel'] = $orderInfo->pay_channel;
        if ($orderInfo->pay_account) {
            $data['orderInfo']['withdrawalsAccount'] = $orderInfo->pay_account;
        } else {
            $data['orderInfo']['withdrawalsAccount'] = "";
        }
        $data['orderInfo']['remark'] = $orderInfo->remark;
        if ($orderInfo->consum_type == 3 || $orderInfo->consum_type == 4) {
            // 获取红包信息
            $redPacket = DBManager::getRedPacketInfo($orderInfo->red_packet_gift_id);
            $data['redPacket']['id'] = $redPacket->id;
            $data['redPacket']['isOrder'] = $redPacket->password ? 1 : 0;
            $data['redPacket']['describe'] = $redPacket->des;
            $data['redPacket']['create_time'] = $redPacket->create_time;
            $data['redPacket']['start_time'] = $redPacket->start_time;
        }
//        if ($orderInfo->consum_type == 4 && $orderInfo->red_packet_gift_id) {
//
//            // 获取礼物记录信息
//            $giftRecord = DBManager::getGiftRecordById($orderInfo->red_packet_gift_id);
//            // 获取目标用户
//            $receiver_user = DBManager::getUserById($giftRecord->target_id);
//            $data['giftInfo']['id'] = $giftRecord->gift_id;
//            $data['giftInfo']['name'] = $giftRecord->gift->name;
//            $data['giftInfo']['describe'] = $giftRecord->gift->des;
//            $data['giftInfo']['price'] = $giftRecord->gift->price;
//            $data['giftInfo']['receiver_name'] = $receiver_user->nickname;
//        }
        return $data;
    }

    /**
     * 查询用户余额信息
     *
     * @param $user
     * @return array
     */
    public static function buildUserBalanceInfo($user, $systemConfig)
    {
        $data = array();
        $data['balanceInfo']['amount'] = $user->balance;
        $data['balanceInfo']['diamond'] = $user->diamond;
        $data['balanceInfo']['serviceCharge'] = (float)$systemConfig->withdraw_service_charge * 100;
        $data['balanceInfo']['maxAmount'] = $systemConfig->withdraw_day_limit;
        $data['balanceInfo']['minAmount'] = $systemConfig->withdraw_min_amount;
        $data['balanceInfo']['withdrawals'] = number_format($user->balance - $user->balance * $systemConfig->withdraw_service_charge, 2, ".","");
        return $data;
    }


    /**
     * 礼物列表
     *
     * @param $giftInfo
     * @return mixed
     */
    public static function buildGiftInfo($giftInfo)
    {
        $data = array();
        foreach ($giftInfo as $key => $value){
            $data[$key]['id'] = $value->id;
            $data[$key]['name'] = $value->name;
            $data[$key]['picture'] = $value->picture;
            $data[$key]['price'] = $value->price;
            $data[$key]['describe'] = $value->des;
        }
        return $data;
    }

    /**
     * 个人赠送礼物记录
     *
     * @param $giftRecord
     * @return array
     */
    public static function buildGiveGiftRecord($giftRecord)
    {
        $data = array();
        foreach ($giftRecord as $key => $value) {
            $data[$key]['id'] = $value->id;
            $data[$key]['user_id'] = $value->target_id;
            $data[$key]['user_nickname'] = $value->target->nickname;
            $data[$key]['userAvatar'] = OssProxy::procOssPic($value->target->user_avatar);
	        $data[$key]['userLevel'] = $value->level;
//            $data[$key]['user_thumb'] = OssApi::procOssThumb($value->target->user_thumb);
            $data[$key]['gift_id'] = $value->gift_id;
            $data[$key]['gift_name'] = $value->gift->name;
            $data[$key]['gift_picture'] = $value->gift->picture;
            $data[$key]['gift_number'] = $value->number;
            $data[$key]['gift_amount'] = $value->amount;
            $data[$key]['create_time'] = $value->create_time;
        }
        return $data;
    }

    /**
     * 个人收礼物记录
     *
     * @param $giftRecord
     * @return array
     */
    public static function buildReceiveGiftRecord($giftRecord)
    {
        $data = array();
        foreach ($giftRecord as $key => $value) {
            $data[$key]['id'] = $value->id;
            $data[$key]['user_id'] = $value->user_id;
            $data[$key]['user_nickname'] = $value->user->nickname;
            $data[$key]['userAvatar'] = OssProxy::procOssPic($value->user->user_avatar);
	        $data[$key]['userLevel'] = $value->level;
//            $data[$key]['user_thumb'] = OssApi::procOssThumb($value->user->user_thumb);
            $data[$key]['gift_id'] = $value->gift_id;
            $data[$key]['gift_name'] = $value->gift->name;
            $data[$key]['gift_picture'] = $value->gift->picture;
            $data[$key]['gift_number'] = $value->number;
            $data[$key]['gift_amount'] = $value->amount;
            $data[$key]['create_time'] = $value->create_time;
        }
        return $data;
    }

    /**
     * 组装赠送礼物记录
     *
     * @param $giveRecord
     * @return mixed
     */
    public static function buildGiveGiftInfo($giveRecord)
    {
        $data['id'] = $giveRecord->gift_id;
        $data['name'] = $giveRecord->gift->name;
        $data['describe'] = $giveRecord->gift->des;
        $data['price'] = $giveRecord->gift->price;
        $data['receiver_name'] = $giveRecord->target->nickname;

        return $data;
    }


    /*
     *  TODO 排行相关
     */

    /**
     * 组装等级排行信息
     *
     * @param $userList
     * @param $userId
     * @return array
     */
    public static function buildRank($rankInfo, $userId, $type, $result, $rankKey)
    {
        $data = array();
        $idx = 0;
	    $sameCount = 1;             // 相同值得数量是0
        $preValue = -1;
        switch ($type)
        {
            case RANK_TYPE_REDPACK:
                foreach ($rankInfo as $rankId => $value) {
	                $rank = $idx + 1;
	                // 处理相同的排行值
//	                $procRankData = Self::procSameScoreRank($idx, $value, $preValue, $sameCount, $data);
//	                $sameCount = $procRankData['same_count'];
//	                $data = $procRankData['data'];
//	                $preValue = $procRankData['pre_value'];
                    $rankId = 99999999999 - $rankId;
                    $user = DBManager::getUserById($rankId);
                    $data[$idx]['user_id'] = $rankId;
                    $data[$idx]['nickname'] = $user->nickname;
                    $data[$idx]['gender'] = $user->gender;
                    $data[$idx]['userAvatar'] =  OssProxy::procOssPic($user->user_avatar);
	                $data[$idx]['userLevel'] = $user->level;
//                    $data[$idx]['user_thumb'] =  OssApi::procOssThumb($user->user_thumb);
                    $data[$idx]['sumAmount'] = round($value, 2) * 100;
                    $data[$idx]['rank'] = $rank;

                    // 获取本人的排行
                    if ($rankId == $userId) {
	                    $result['myRedPacketRank'] = array();
	
	                    $result['myRedPacketRank']['user_id'] = $user->id;
	                    $result['myRedPacketRank']['nickname'] = $user->nickname;
	                    $result['myRedPacketRank']['gender'] = $user->gender;
	                    $result['myRedPacketRank']['userAvatar'] = OssProxy::procOssPic($user->user_avatar);
	                    $result['myRedPacketRank']['userLevel'] = $user->level;
	                    $result['myRedPacketRank']['sumAmount'] = round($value, 2) * 100;
	                    $result['myRedPacketRank']['rank'] = $rank;
                    }
                    $idx ++;
                }
                // 检查是否存在排行
                if (!$result['myRedPacketRank']) {
                    $user = DBManager::getUserById($userId);
                    $result['myRedPacketRank']['user_id'] = $user->id;
                    $result['myRedPacketRank']['nickname'] = $user->nickname;
                    $result['myRedPacketRank']['gender'] = $user->gender;
                    $result['myRedPacketRank']['userAvatar'] = OssProxy::procOssPic($user->user_avatar);
                    $result['myRedPacketRank']['userLevel'] = $user->level;
                    $result['myRedPacketRank']['sumAmount'] = 0;
                    $result['myRedPacketRank']['rank'] = 0;
                }
                break;
            case RANK_TYPE_ASSOCIATION:
                foreach ($rankInfo as $rankId => $value) {
	                $rank = $idx + 1;
	                // 处理相同的排行值
//	                $procRankData = Self::procSameScoreRank($idx, $value, $preValue, $sameCount, $data);
//	                $sameCount = $procRankData['same_count'];
//	                $data = $procRankData['data'];
//	                $preValue = $procRankData['pre_value'];
	                
                    $association = DBManager::getAssociationByGroupId($rankId);
                    if ($association->type == 1) {
                        $data[$idx]['family_id'] = $rankId;
                        $data[$idx]['family_name'] = $association->nickname;
                        $data[$idx]['familyAvatar'] = OssProxy::procOssPic($association->assoc_avatar);
//                    $data[$idx]['assoc_thumb'] = $association->assoc_thumb;
                        $data[$idx]['familyLevel'] = $association->level;
                        $data[$idx]['rank'] = $rank;
                        $idx ++;
                    }
                }
                break;
            case RANK_TYPE_USERLEV:
                foreach ($rankInfo as $rankId => $value) {
	                $rank = $idx + 1;
	                // 处理相同的排行值
//	                $procRankData = Self::procSameScoreRank($idx, $value, $preValue, $sameCount, $data);
//                    $sameCount = $procRankData['same_count'];
//                    $data = $procRankData['data'];
//	                $preValue = $procRankData['pre_value'];
                    $rankId = 99999999999 - $rankId;
                    $user = DBManager::getUserById($rankId);
                    $data[$idx]['user_id'] = $rankId;
                    $data[$idx]['nickname'] = $user->nickname;
                    $data[$idx]['gender'] = $user->gender;
                    $data[$idx]['userAvatar'] =  OssProxy::procOssPic($user->user_avatar);
//                    $data[$idx]['user_thumb'] =  OssApi::procOssThumb($user->user_thumb);
                    $data[$idx]['userLevel'] = $user->level;
                    $data[$idx]['exp'] = $user->exp;
                    $data[$idx]['rank'] = $rank;

                    // 获取本人的排行
                    if ($rankId == $userId) {
						$result['myUserLevRank'] = array();
 	                    $result['myUserLevRank']['user_id'] = $user->id;
	                    $result['myUserLevRank']['nickname'] = $user->nickname;
	                    $result['myUserLevRank']['gender'] = $user->gender;
	                    $result['myUserLevRank']['userAvatar'] = OssProxy::procOssPic($user->user_avatar);
//	                    $result['myUserLevRank']['user_thumb'] =  $data[$idx]['user_thumb'];
	                    $result['myUserLevRank']['userLevel'] = $user->level;
	                    $result['myUserLevRank']['exp'] = $user->exp;
	                    $result['myUserLevRank']['rank'] = $rank;
                    }
                    $idx ++;
                }
                // 检查是否存在排行
                if (!$result['myUserLevRank']) {
                    $user = DBManager::getUserById($userId);
                    $result['myUserLevRank']['user_id'] = $user->id;
                    $result['myUserLevRank']['nickname'] = $user->nickname;
                    $result['myUserLevRank']['gender'] = $user->gender;
                    $result['myUserLevRank']['userAvatar'] = OssProxy::procOssPic($user->user_avatar);
                    $result['myUserLevRank']['userLevel'] = $user->level;
                    $result['myUserLevRank']['exp'] = $user->exp;
                    $result['myUserLevRank']['rank'] = 0;
                }
                break;
        }
        // 排行数据数据
        $result[$rankKey] = $data;
        return $result;
    }
    

    /**
     * 组装等级排行信息
     *
     * @param $userList
     * @param $userId
     * @return array
     */
    public static function buildUserLevelRank($rankInfo, $userId)
    {

        $data = array();
        $myInfo = array();
        $idx = 0;
        foreach ($rankInfo as $rankUserId => $value) {
            $user = DBManager::getUserById($rankUserId);
            $data[$idx]['user_id'] = $user->id;
            $data[$idx]['nickname'] = $user->nickname;
            $data[$idx]['gender'] = $user->gender;
            $data[$idx]['userAvatar'] =  OssProxy::procOssPic($user->user_avatar);
//            $data[$idx]['user_thumb'] =  OssApi::procOssThumb($user->user_thumb);
            $data[$idx]['userLevel'] = $user->level;
            $data[$idx]['exp'] = $user->exp;
            $data[$idx]['rank'] = $idx + 1;
            // 获取本人的排行
            if ($rankUserId == $userId) {
                $myInfo['user_id'] = $user->id;
                $myInfo['nickname'] = $user->nickname;
                $myInfo['gender'] = $user->gender;
                $myInfo['userAvatar'] = OssProxy::procOssPic($data[$idx]['user_avatar']);
//                $myInfo['user_thumb'] =  $data[$idx]['user_thumb'];
                $myInfo['userLevel'] = $user->level;
                $myInfo['exp'] = $user->exp;
                $myInfo['rank'] = $idx + 1;
            }
            $idx ++;
        }
        //
        
        return ['rank' => $data, 'myRank' => $myInfo];
    }
    /**
     * 组装红包排行信息
     *
     * @param $redPacketRecord
     * @param $userInfo
     * @return array
     */
    public static function buildRedPacketRank($rankInfo, $userInfo)
    {
        $data = array();
        $idx = 0;
        foreach ($rankInfo as $userId => $redPackAmount)
        {

            // 获取用户数据
            $user = DBManager::getUserById($userId);
            $data[$idx]['user_id'] = $user->id;
            $data[$idx]['nickname'] = $user->nickname;
            $data[$idx]['gender'] = $user->gender;
            $data[$idx]['userAvatar'] =  OssProxy::procOssPic($user->user_avatar);
	        $data[$idx]['userLevel'] =  $user->level;
//            $data[$idx]['user_thumb'] = OssApi::procOssThumb($user->user_thumb);
            $data[$idx]['sumAmount'] = $redPackAmount;
            $data[$idx]['rank'] = $idx + 1;

            // 获取本人的排行
            if ($userId == $userInfo->id) {
                $myInfo['user_id'] = $user->id;
                $myInfo['nickname'] = $user->nickname;
                $myInfo['gender'] = $user->gender;
                $myInfo['userAvatar'] = OssProxy::procOssPic($data[$idx]['user_avatar']);
	            $myInfo['userLevel'] = $data[$idx]['level'];
//                $myInfo['user_thumb'] = $data[$idx]['user_thumb'];
                $myInfo['sumAmount'] = $redPackAmount;
                $myInfo['rank'] = $idx + 1;
            }
            $idx ++;
        }
        // 检查是否有玩家的数据
        if (empty($myInfo)) {
            $myInfo['user_id'] = $userInfo->id;
            $myInfo['nickname'] = $userInfo->nickname;
            $myInfo['gender'] = $userInfo->gender;
            $myInfo['userAvatar'] =  OssProxy::procOssPic($userInfo->user_avatar);
            $myInfo['userLevel'] = $userInfo->level;
//            $myInfo['user_thumb'] =  OssApi::procOssThumb($userInfo->user_thumb);
            $myInfo['sumAmount'] = 0;
            $myInfo['rank'] = 0;
        }
        return ['rank' => $data, 'myRank' => $myInfo];
    }

    /**
     * 组装送礼物排行信息
     *
     * @params $redPacketRecord
     * @params $userInfo
     * @return array
     */
    public static function buildGiveGiftRank($giveGiftRecord, $userInfo)
    {
        $data = array();
        $myInfo = array();
        foreach ($giveGiftRecord as $key => $value) {
            $user = DBManager::getUserById($value->user_id);
            $data[$key]['user_id'] = $user->id;
            $data[$key]['nickname'] = $user->nickname;
            $data[$key]['gender'] = $user->gender;
            $data[$key]['userAvatar'] = OssProxy::procOssPic($user->user_avatar);
	        $data[$key]['userLevel'] = $user->level;
            $data[$key]['sumAmount'] = $value->sumatory;
            $data[$key]['rank'] = $key + 1;
            // 获取本人的排行
            if ($value->user_id == $userInfo->id) {
                $myInfo['user_id'] = $user->id;
                $myInfo['nickname'] = $user->nickname;
                $myInfo['gender'] = $user->gender;
                $myInfo['userAvatar'] = $data[$key]['user_avatar'];
	            $myInfo['userLevel'] = $data[$key]['level'];
//                $myInfo['user_thumb'] = $data[$key]['user_thumb'];
                $myInfo['sumAmount'] = $value->sumatory;
                $myInfo['rank'] = $key + 1;
            }
        }
        if (empty($myInfo)) {
            $myInfo['user_id'] = $userInfo->id;
            $myInfo['nickname'] = $userInfo->nickname;
            $myInfo['gender'] = $userInfo->gender;
            $myInfo['userAvatar'] =  OssProxy::procOssPic($userInfo->user_avatar);
            $myInfo['userLevel'] = $userInfo->level;
//            $myInfo['user_thumb'] = OssApi::procOssThumb($userInfo->user_thumb);
            $myInfo['sumAmount'] = 0;
            $myInfo['rank'] = 0;
        }
        return ['rank' => $data, 'myRank' => $myInfo];
    }

    /**
     * TODO 红包相关
     */


    /**
     * 组装抢红包返回数据
     * @param $redPacketId
     * @param $grabRedPacket
     * @return mixed
     */
    public static function buildGrabRedPacketInfo($redPacketId, $grabRedPacketRecord, $redPacket)
    {
        if ($grabRedPacketRecord) {
            $data['id'] = $grabRedPacketRecord->red_packet_id;
            $data['grab_amount'] = number_format(floatval($grabRedPacketRecord->amount), 2);
            $data['status'] = $redPacket->status;
        } else {
            $data['id'] = $redPacketId;
            $data['grab_amount'] = number_format(0, 2);
            $data['status'] = $redPacket->status;
        }
        return $data;
    }

    /**
     * 组装发个人/群红包返回数据
     *
     * @param $redPacketInfo
     * @return mixed
     */
    public static function buildGiveRedPacketInfo($redPacketInfo)
    {
        $data['id'] = $redPacketInfo->id;
        // 是否是口令红包
        if ($redPacketInfo->password) {
            $data['isOrder'] = 1;
        } else {
            $data['isOrder'] = 0;
        }
        $data['describe'] = $redPacketInfo->des;
        $data['create_time'] = $redPacketInfo->create_time;
        $data['start_time'] = $redPacketInfo->start_time;

        return ['redPacket' => $data];
    }

    /**
     * 组装红包详情
     *
     * @param $userId
     * @param $redPacket
     * @param $redPacketRecord
     * @param $redPacketNum
     * @return array
     */

    public static function buildRedPacketRecord($userId, $redPacket, $redPacketRecord, $redPacketNum)
    {
        $redPacketInfo = array();
        $data = array();
        $redPacketInfo['id'] = $redPacket->id;
        $redPacketInfo['userNickname'] = $redPacket->User->nickname;
        $redPacketInfo['userAvatar'] = OssProxy::procOssPic($redPacket->User->user_avatar);
//        $redPacketInfo['user_thumb'] = OssApi::procOssThumb($redPacket->User->user_thumb);
	    $redPacketInfo['userLevel'] = $redPacket->User->level;
        $redPacketInfo['grab_amount'] = 0;
        $redPacketInfo['password'] = Utils::procNull($redPacket->password, '');
        $redPacketInfo['describe'] = $redPacket->des;
        $redPacketInfo['amount'] = $redPacket->amount;
        $redPacketInfo['receive'] = number_format($redPacket->amount - $redPacket->balance, 2);
        $redPacketInfo['total_number'] = $redPacket->number;
        $redPacketInfo['receive_number'] = $redPacketNum;
        $redPacketInfo['status'] = $redPacket->status;
        $redPacketInfo['invalid'] = $redPacket->invalid;
        $redPacketInfo['create_time'] = Utils::procNull($redPacket->create_time);
        $redPacketInfo['start_time'] = $redPacket->start_time;
        // 判断口令是否失效,
        if ($redPacket->password) {
            $redPacketInfo['isOrder'] = 1;
            if (strtotime(date('Y-m-d H:i:s')) - strtotime($redPacket->start_time) > 86400) {
                $redPacketInfo['orderFailed'] = 1;
            } else {
                $redPacketInfo['orderFailed'] = 0;
            }
        } else {
            $redPacket->password = '';
            $redPacketInfo['isOrder'] = 0;
        }

        // 抢红包记录
        foreach ($redPacketRecord as $key => $value) {
            // 抢到金额
            if ($userId == $value->grab_user_id) {
                $redPacketInfo['grab_amount'] = $value->amount;
            }
            $data[$key]['grabUserNickname'] = $value->grabUser->nickname;
            $data[$key]['grabUserAvatar'] = OssApi::procOssPic($value->grabUser->user_avatar);
//            $data[$key]['grab_user_thumb'] = OssApi::procOssThumb($value->grabUser->user_thumb);
	        $data[$key]['grabUserLevel'] = $value->grabUser->level;
            $data[$key]['amount'] = $value->amount;
            $data[$key]['create_time'] = $value->create_time;
        }
        return ['redPacket' => $redPacketInfo, 'record' => $data];
    }

    /**
     * 组装红包信息
     *
     * @param $redPacketInfo
     * @param $redPacketRecord
     * @return array
     */
    public static function buildRedPacketInfo($redPacketInfo, $redPacketRecord, $uid)
    {
        $data['id'] = $redPacketInfo->id;
        $data['userId'] = $redPacketInfo->user_id;
        $data['user_nickname'] = $redPacketInfo->User->nickname;
        $data['userAvatar'] = OssProxy::procOssPic($redPacketInfo->User->user_avatar);
//        $data['user_thumb'] = OssApi::procOssPic($redPacketInfo->User->user_thumb);
        $data['userLevel'] = $redPacketInfo->User->level;
        // 判断是否抢过红包
        if ($redPacketRecord) {
            $data['isGrabbed'] = '1';
            $data['grab_amount'] = $redPacketRecord->amount;
        } else {
            $data['isGrabbed'] = '0';
            $data['grab_amount'] = '0';
        }
        // 判断是否是口令红包
        if ($redPacketInfo->password) {
            $data['isOrder'] = '1';
        } else {
            $redPacketInfo->password = '';
            $data['isOrder'] = '0';
        }
        $data['password'] = $redPacketInfo->password;
        $data['status'] = $redPacketInfo->status;
        $data['describe'] = $redPacketInfo->des;
        $data['create_time'] = $redPacketInfo->create_time;
        $data['start_time'] = $redPacketInfo->start_time;
        $data['invalid'] = $redPacketInfo->invalid;
        // 获取用户与动态作者的关系 0：无关系 1：有关系
        $isHaveRelation = 1;
        if($uid != $redPacketInfo->user_id) {
            $userRelationPerm = DBManager::getUserRelationPerm($uid, $redPacketInfo->user_id);
            if(!$userRelationPerm || $userRelationPerm->rtype == 0) {
                $isHaveRelation = 0;
            }
        }
        $data['isHaveRelation'] = $isHaveRelation;
        return ['redPacket' => $data];
    }

    /**
     * 组装新消息数量数据
     *
     * @params $userId
     * @params $newFriendNum
     * @params $newFansNum
     * @return mixed
     */
    public static function buildNewMessageNum($userId, $newApplyData, $newFansNum)
    {
        $data['id'] = $userId;
        $data['new_fans'] = $newFansNum;
        $data['new_friends'] = $newApplyData['new_friends'];
        $data['new_families'] = $newApplyData['new_families'];
        return $data;
    }

    public static function buildRewardTaskData($rewardTask)
    {
        $data['taskId'] = $rewardTask->id;
        $data['coverPic'] = $rewardTask->cover_pic;
        $data['coverThumb'] = $rewardTask->cover_thumb;
        $data['taskTitle'] = $rewardTask->title;
        $data['taskContent'] = $rewardTask->content;
        $data['rewardAmount'] = $rewardTask->reward_amount;
        $data['clickReward'] = $rewardTask->click_reward;
        $data['shareReward'] = $rewardTask->share_reward;
        $data['taskLink'] = $rewardTask->link;
        $data['status'] = $rewardTask->status;
        $data['endTime'] = $rewardTask->end_time;
        return $data;
    }
	
	/**
	 * TODO 悬赏任务相关
	 *
	 */
	public static function buildRewardTasks($rewardTasks)
	{
		$dataList = array();
		foreach($rewardTasks as $rewardTask )
		{
			$data = [
				"taskId" => $rewardTask['id'],
				"coverPic" => $rewardTask['cover_pic'],
				"coverThumb" => $rewardTask['cover_thumb'],
				"taskTitle" => $rewardTask['title'],
				"taskContent" => $rewardTask['content'],
				"rewardAmount" => $rewardTask['reward_amount'],
				"clickReward" => $rewardTask['click_reward'],
				"shareReward" => $rewardTask['share_reward'],
				"taskLink" => $rewardTask['link'],
				"status" => $rewardTask['status'],
				"endTime" => $rewardTask['end_time']
			];
			array_push($dataList, $data);
		}
		return $dataList;
	}
	
	public static function buildRewardTask($rewardTask)
	{
		return [
			"taskId" => $rewardTask['id'],
			"coverPic" => $rewardTask['cover_pic'],
			"coverThumb" => $rewardTask['cover_thumb'],
			"taskTitle" => $rewardTask['title'],
			"taskContent" => $rewardTask['content'],
			"rewardAmount" => $rewardTask['reward_amount'],
            "balance" => round((float)$rewardTask['balance'],2),
			"clickReward" => $rewardTask['click_reward'],
			"shareReward" => $rewardTask['share_reward'],
			"clickedNumber" => $rewardTask['click_count'],
			"sharedNumber" => $rewardTask['share_count'],
			"totalClickedNumber" => $rewardTask['total_click_count'],
			"totalSharedNumber" => $rewardTask['total_share_count'],
			"taskLink" => $rewardTask['link'],
			"status" => $rewardTask['status'],
			"amountUsed" => sprintf("%.2f",$rewardTask['reward_amount'] - $rewardTask['balance']),
			"endTime" => $rewardTask['end_time'],
            "userAmount" => $rewardTask['user_amount']
		];
	}
	
	public static function buildCollectRewardUsers($collectRewardUsers)
	{
		$return = array();
		foreach ($collectRewardUsers as $collectRewardUser ) {
			array_push($return, [
				"opTime" => $collectRewardUser['op_time'],
                "opType" => $collectRewardUser['op_type'],
                "userId" => $collectRewardUser['user_id'],
                "nickname" => $collectRewardUser['nickname'],
                "userAvatar" => OssProxy::procOssPic($collectRewardUser['user_avatar']),
//                "user_thumb" => $collectRewardUser['user_thumb'],
				"userLevel" => $collectRewardUser['level']
			]);
		}
		return $return;
	}
	
	public static function buildRcmdAssociationList($rcmdAssociations, $assocTaskBalanceData)
	{
		$return = [];
		foreach($rcmdAssociations as $rcmdAssociation) {
			$groupId = $rcmdAssociation->a->group_id;
			if ($assocTaskBalanceData[$groupId]) {
				$sumBalance = $assocTaskBalanceData[$groupId];
			} else {
				$sumBalance = 0;
			}
			array_push($return, [
				'familyId' => $groupId,
				'familyAvatar' => OssApi::procOssPic($rcmdAssociation->a->assoc_avatar),
//				'familyThumb' => OssApi::procOssThumb($rcmdAssociation->a->assoc_thumb),
				'familyLevel' => $rcmdAssociation->a->level,
				'familyName' => $rcmdAssociation->a->nickname,
				'currentNumber' => $rcmdAssociation->member_count,
				'maxNumber' => $rcmdAssociation->member_limit,
				'rewardSumBalance' => $sumBalance
			]);
		}
		return $return;
	}
	
	/**
	 * 处理相同的排行值
	 *
	 */
	private static function procSameScoreRank($idx, $value, $preValue, $sameCount, $data)
	{
		if ($value != $preValue) {
			if ($sameCount > 1) {
				$preSameIdx = $idx - $sameCount;
				// 检查是否小于0, 正常是不会小于0的
				if ($preSameIdx < 0) {
					$preSameIdx = 0;
				}
				$sub_data = array_reverse(array_slice($data, $preSameIdx, $sameCount, true));
				for ($i = 0; $i < $sameCount; $i++) {
					$data[$preSameIdx] = $sub_data[$i];
					$preSameIdx ++;
				}
				$sameCount = 1;
			}
			$preValue = $value;
		} else {
			$sameCount++;
		}
		return ['same_count' => $sameCount, 'pre_value' => $preValue, 'data' => $data];
	}
	
	
//	public static function buildSysHotSysTaskList($taskList, $data, $type)
//	{
////		var_dump($taskList);
//		foreach($taskList as $task) {
//			array_push($data, [
////				'id' => $task->id,
////				'hot_type' => $type,
////				'amount' => $task->reward_amount,
////				'createTime' => date("Y-m-d H:i:s", $task->create_time),
////				'familyId' => 0,
////				'familyName' => '咖咖',
////				'familyAvatar' => KAKA_AVATAR
//
//				'id' => $task->r->id,
//				'hot_type' => $type,
//				'amount' => $task->r->reward_amount,
//				'link' => $task->r->link,
//				'clickReward' => $task->r->click_reward,
//				'shareReward' => floor($task->r->click_reward / $task->r->click_reward),
//				'content' => $task->r->content,
//				'coverPic' => OssApi::procOssPic($task->r->cover_pic),
//				'createTime' => date("Y-m-d H:i:s", $task->r->create_time),
//				'endTime' => date("Y-m-d H:i:s", $task->r->end_time),
//				'familyId' => 0,
//				'familyName' => '',
//				'familyAvatar' => '',
//				'familyLevel' => 0
//			]);
//		}
//	}
	
//	public static function buildSysHotTaskList($taskList, $data, $type)
//	{
////		var_dump($taskList);
//		foreach($taskList as $task) {
//			array_push($data, [
////				'id' => $task->id,
////				'hot_type' => $type,
////				'amount' => $task->reward_amount,
////				'createTime' => date("Y-m-d H:i:s", $task->create_time),
////				'familyId' => $task->familyId,
////				'familyName' => $task->familyName,
////				'familyAvatar' => OssApi::procOssPic($task->familyAvatar)
//				'id' => $task->r->id,
//				'hot_type' => $type,
//				'amount' => $task->r->reward_amount,
//				'link' => $task->r->link,
//				'clickReward' => $task->r->click_reward,
//				'shareReward' => floor($task->r->click_reward / $task->r->click_reward),
//				'content' => $task->r->content,
//				'coverPic' => OssProxy::procOssPic($task->r->cover_pic),
//				'createTime' => date("Y-m-d H:i:s", $task->r->create_time),
//				'endTime' => date("Y-m-d H:i:s", $task->r->end_time),
//				'familyId' => $task->familyId,
//				'familyName' => $task->familyName,
//				'familyAvatar' => OssProxy::procOssPic($task->familyAvatar),
//				'familyLevel' => $task->familyLevel
//			]);
//		}
//		return $data;
//	}
	
//	public static function buildSysRedpackList($di, $redPackList, $data, $type)
//	{
////		var_dump($redPackList);
//		foreach($redPackList as $redpack) {
//			$moment = Moments::findFirst("red_packet_id = ".$redpack->id);
//			// 是否点赞了
//			$like = MomentsLike::findFirst("moments_id = ".$moment->id.' AND user_id = '.$redpack->userId);
//			$isLike = 0;
//			if ($like) {
//				$isLike = 1;
//			}
//			
//			// 评论数
//			$sql = 'SELECT count(mr.id) as mrc, count(ml.id) as mlc '
//				.'FROM Fichat\Models\MomentsLike as ml, Fichat\Models\MomentsReply as mr '
//				.'WHERE ml.moments_id = '.$moment->id.' AND mr.moments_id='.$moment->id;
//			$query = new Query($sql, $di);
//			$rlcData = $query->execute();
//			
//			array_push($data, [
//				'id' => $moment->id,
//				'hot_type' => $type,
//				'amount' => $redpack->amount,
//				'createTime' => $redpack->create_time,
//				'userId' => $redpack->userId,
//				'nickname' => $redpack->nickname,
//				'userLevel' => $redpack->userLevel,
//				'userAvatar' => OssApi::procOssPic($redpack->userAvatar),
//				'replyNumber' => $rlcData[0]->mrc,
//				'likeNumber' => $rlcData[0]->mlc,
//				'redPacketId' => $redpack->id,
//				'content' => $moment->content,
//				'coverPic' => OssApi::procOssPic($moment->pri_url),
//				'createTime' => $moment->create_time,
//				'isLike' => $isLike
//			]);
//		}
//		return $data;
//	}
	
	public static function buildHotList($di, $uid, $hotList)
	{
		$data = [];
//		var_dump($hotList->toArray());
		foreach($hotList as $hotItem) {
		    //修改曝光数
            SystemHot::delExpoNum($hotItem->id);

			switch ($hotItem->type) {
				case 1:
					$taskId = $hotItem->trigger_id;
					$sql = 'SELECT r.*, a.id as familyId, a.nickname as familyName, a.assoc_avatar as familyAvatar, a.level as familyLevel '
						.'FROM Fichat\Models\RewardTask as r, Fichat\Models\Association as a '
						.'WHERE r.group_id = a.group_id AND r.id = '.$taskId;
					$query = new Query($sql, $di);
					$task = $query->execute();
					if ($task) {
						$task = $task[0];
						array_push($data, [
							'id' => $task->r->id,
							'signId' => $hotItem->id,
							'type' => 1,
							'amount' => $task->r->reward_amount,
							'link' => $task->r->link,
							'clickReward' => $task->r->click_reward,
							'shareReward' => floor($task->r->click_reward / $task->r->click_reward),
							'content' => $task->r->content,
							'coverPic' => OssApi::procOssPic($task->r->cover_pic),
							'createTime' => date("Y-m-d H:i:s", $task->r->create_time),
							'endTime' => date("Y-m-d H:i:s", $task->r->end_time),
							'familyId' => $task->familyId,
							'familyName' => $task->familyName,
							'familyAvatar' => OssProxy::procOssPic($task->familyAvatar),
							'familyLevel' => $task->familyLevel
						]);
					}
					break;
				case 2:
					$rid = $hotItem->trigger_id;
					$sql = 'SELECT r.*, u.id as uid, u.nickname as nickname, u.user_avatar as userAvatar, u.level as userLevel '
						. 'FROM Fichat\Models\RedPacket as r, Fichat\Models\User as u '
						. 'WHERE r.id = '.$rid. ' AND r.user_id = u.id';
					$query = new Query($sql, $di);
					$redpack = $query->execute();
					if ($redpack){
						$redpack = $redpack[0];
						$moment = Moments::findFirst("red_packet_id = ".$rid);
						if ($moment) {
							// 评论数
							$sql = 'SELECT count(mr.id) as mrc, count(ml.id) as mlc '
								.'FROM Fichat\Models\MomentsLike as ml, Fichat\Models\MomentsReply as mr '
								.'WHERE ml.moments_id = '.$moment->id.' AND mr.moments_id='.$moment->id;
							$query = new Query($sql, $di);
							$rlcData = $query->execute();
							// 是否点赞了
							$like = MomentsLike::findFirst("moments_id = ".$moment->id.' AND user_id = '.$redpack->r->user_id);
							$isLike = 0;
							if ($like) {
								$isLike = 1;
							}
							$pri_url = OssApi::procOssPic($moment->pri_url);
						} else {
							$rlcData = [
								'mrc' => 0,
								'mlc' => 0
							];
							$isLike = 0;
							$pri_url = '';
						}
						$content = $redpack->r->des;
						$amount = $redpack->r->amount;
						// 推入数据
						array_push($data, [
							'id' => $moment->id,
							'signId' => $hotItem->id,
							'type' => 2,
							'replyNumber' => $rlcData[0]->mrc,
							'likeNumber' => $rlcData[0]->mlc,
							'redPacketId' => $rid,
							'amount' => $amount,
							'content' => $content,
							'coverPic' => $pri_url,
							'createTime' => $redpack->r->create_time,
							'userId' => $redpack->uid,
							'nickname' => $redpack->nickname,
							'isLike' => $isLike,
							'userLevel' => $redpack->userLevel,
							'userAvatar' => OssProxy::procOssPic($redpack->userAvatar)
						]);
					}
					break;
				case 3:
					$mid = $hotItem->trigger_id;
					$sql = 'SELECT m.*, COUNT(DISTINCT ml.id) as likeCount, COUNT(DISTINCT mr.id) as replyCount'
						.' ,u.user_avatar as userAvatar, u.level as userLevel, u.nickname as userNickname FROM'
						.' Fichat\Models\Moments as m LEFT JOIN Fichat\Models\MomentsLike as ml'
						.' ON m.id = ml.moments_id LEFT JOIN Fichat\Models\MomentsReply as mr ON m.id = mr.moments_id and mr.status = 0'
						.' LEFT JOIN Fichat\Models\User as u ON m.user_id = u.id'
						.' WHERE m.id = '.$mid;
					$query = new Query($sql, $di);
					$moment = $query->execute();
					if ($moment) {
						$moment = $moment[0];
						// 是否点赞了
						$like = MomentsLike::findFirst("moments_id = ".$mid.' AND user_id = '.$uid);
						$isLike = 0;
						if ($like) {
							$isLike = 1;
						}

						// 检查红包状态
                        $isGrab = 0;
						if($moment->m->red_packet_id){
                            $redPacket = DBManager::getRedPacketInfo($moment->m->red_packet_id);
                            if($redPacket) {
                                $redPacketRecords = DBManager::checkRedPacketRecord($uid, $moment->m->red_packet_id);
                                if($redPacketRecords) {
                                    $isGrab = 1;
                                } else {
                                    $isGrab = $redPacket->balance == 0 ? 3 : 2;
                                }
                            }
                        }

						// 推入数据
						array_push($data, [
							'id' => $moment->m->id,
							'signId' => $hotItem->id,
							'type' => 3,
							'replyNumber' => $moment->replyCount != null ? $moment->replyCount : 0,
							'likeNumber' => $moment->likeCount != null ? $moment->likeCount : 0,
							'redPacketId' => $moment->m->red_packet_id != null ? $moment->m->red_packet_id : 0,
							'content' => $moment->m->content != null ? $moment->m->content : '',
							'coverPic' => OssProxy::procOssPic($moment->m->pri_url),
							'coverPreview' => $moment->m->pri_preview,
							'createTime' => $moment->m->create_time,
							'userId' => $moment->m->user_id,
							'nickname' => $moment->userNickname != null ? $moment->userNickname : '',
							'isLike' => $isLike,
							'userLevel' => $moment->userLevel != null ? $moment->userLevel : '',
							'userAvatar' => OssProxy::procOssPic($moment->userAvatar),
                            'isGrab' => $isGrab
						]);
					}
					break;
			}
		}
		return $data;
	}
	
	public static function buildDynList($uid, $moments, $likeMids)
	{
		$data = [];
		foreach($moments as $moment)
		{
			$mid = $moment->m->id;
			$amount = 0;
            $isGrab = 0;
			$content = $moment->m->content;
			if ($moment->m->red_packet_id) {
				$amount = $moment->r->amount;
                // 检查红包状态
                $redPacket = $moment->r;
                if($redPacket) {
                    $redPacketRecords = DBManager::checkRedPacketRecord($uid, $moment->m->red_packet_id);
                    if($redPacketRecords) {
                        $isGrab = 1;
                    } else {
                        $isGrab = $redPacket->balance == 0 ? 3 : 2;
                    }
                }
			}
			// 是否点赞了
			$isLike = 0;
			if (in_array($mid, $likeMids)) {
				$isLike = 1;
			}

			// 推入数据
			array_push($data, [
				'id' => $mid,
				'type' => 2,
				'replyNumber' => $moment->replyCount,
				'likeNumber' => $moment->likeCount,
				'redPacketId' => $moment->m->red_packet_id,
				'amount' => $amount,
				'content' => $content,
				'coverPic' => OssApi::procOssPic($moment->m->pri_url),
				'coverPreview' => $moment->m->pri_preview,
				'createTime' => $moment->m->create_time,
				'userId' => $moment->m->user_id,
				'nickname' => $moment->userNickname,
				'isLike' => $isLike,
				'userLevel' => $moment->userLevel,
				'userAvatar' => OssProxy::procOssPic($moment->userAvatar),
                'isGrab' => $isGrab
			]);
		}
		return $data;
	}
	
	/**
	 * 构建用户推荐标签返回
	 *
	 */
	public static function buildUserRcmdTags($selectUserTags, $tags)
	{
		$data = [];
		if ($tags) {
			foreach ($tags as $tag) {
				array_push($data, [
					'id' => (int)$tag['id'],
					'tag' => $tag['tag'],
					'status' => 0
				]);
			}
		}
		
		foreach ($selectUserTags as $id => $tag) {
			array_push($data, [
				'id' => $id,
				'tag' => $tag,
				'status' => 1
			]);
		}
		shuffle($data);
		return $data;
	}
	
	/**
	 * 构建用户消息返回
	 *
	 */
	public static function buildUserMsgs($userMsgs)
	{
		$data = [];
		foreach ($userMsgs as $userMsg )
		{
			$ext_params = unserialize($userMsg->ext_params);
			$dataItem = $ext_params;
			$dataItem['type'] = $userMsg->type;
			$dataItem['msgStatus'] = $userMsg->status;
			$dataItem['time'] = $userMsg->update_time;
			$dataItem['id'] = $userMsg->id;
			array_push($data, $dataItem);
		}
		return $data;
	}
	
}