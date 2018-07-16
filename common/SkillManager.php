<?php
namespace Fichat\Common;

class SkillManager
{
    /**
     * 技能10-名称(对敌人造成伤害时会对敌人周边的所有敌人造成伤害[伤害量=攻击力*30%])
     *
     * @param $AI           // 攻击顺序
     * @param $userPet
     * @param $targetPet
     * @param $userPetList
     * @param $targetPetList
     * @param $attackerType // 1 攻击者,2 被攻击者
     * @return array
     */
	public static function skill10($AI, $userPet, $targetPet, $userPetList, $targetPetList, $attackerType) {
		// 计算伤害
		$damage = AttackProbability::damage($userPet->atk, $targetPet->def);
		// 技能属性
		$appendAtk = ceil($damage * 0.3);
		// 更新宠物血量
        $targetPet = DBManager::updatePetHp($damage, $targetPet);

        // 造成总伤害
        $attackerDamage = $damage;
        // 普攻伤害描述
        $generalDamageDescription = DBManager::buildDamageDescription($AI, $userPet, $targetPet, $damage);

        // 检测是否有反弹伤害
        $reboundDescription = DBManager::checkoutReboundSkill($AI, $userPet, $targetPet, $damage);
        $generalDamageDescription = array_merge($generalDamageDescription, $reboundDescription['description']);
        // 反弹伤害
        $attackedDamage = $reboundDescription['damage'];
        $attackedPetName = '';
        $skillDamage = '';
        $skillDamageDescription = array();
        $reboundDescription = array();
        // 处理技能伤害
        foreach($targetPetList as $pet){
            if($pet != 0 && $pet->blood > 0 && $pet->number != $targetPet->number){
                $attackedPetName .=  $pet->Pet->name . ',';
                $skillDamage .= $appendAtk . ',';
                $attackerDamage += $appendAtk;
                // 检测是否有反弹伤害
                $rebound = DBManager::checkoutReboundSkill($AI, $userPet, $pet, $appendAtk);
                $reboundDescription = array_merge($reboundDescription, $rebound['description']);
                $attackedDamage += $rebound['damage'];
            }
        }
        // 减少技能伤害血量
        DBManager::batchReducePetBlood($targetPet, $targetPetList, $appendAtk);

        if ($attackedPetName) {
            // 技能伤害描述
            $attackedPetName = rtrim($attackedPetName , ',');
            $skillDamage = rtrim($skillDamage, ',');
            $skillDamageDescription = DBManager::buildSkillDamageDescription($AI, $userPet, $targetPet, $attackedPetName, $skillDamage);
            // 追加反弹技能伤害描述
            $skillDamageDescription = array_merge($skillDamageDescription, $reboundDescription);
        }
        // 判断宠物血量,提高宠物防御力
        DBManager::checkoutUpDefSkill($userPetList, $targetPetList);
        // 处理战斗描述
        $record = DBManager::buildDescription($userPetList, $targetPetList, $attackerDamage, $attackedDamage, $generalDamageDescription, $skillDamageDescription, $attackerType, $AI);
        return $record;

//        $damages = array();
//        $damages[0] = array(
//            'pet_index' => $targetPet->number - 1,
//            'pet_name' => $targetPet->Pet->name,
//            'number' => $damage,
//            'HP' => round(($targetPet->blood / $targetPet->max_blood), 2)
//        );
//
//		$i = 1;
//		$totalDamage = $damage;
//		foreach($targetPetList as $pet){
//			if($pet != 0 && $pet->blood > 0 && $pet->number != $targetPet->number){
//				$damages[$i]['pet_index'] = $pet->number - 1;
//				$damages[$i]['pet_name'] = $pet->Pet->name;
//				$damages[$i]['number'] = intval($appendAtk);
//				$damages[$i]['HP'] = round(($pet->blood / $pet->max_blood), 2);
//                $totalDamage += $appendAtk;
//				$i++;
//			}
//		}

        // 攻击记录
//		$record = DBManager::jointBattleRecord($AI, $attackerType, $userPet->number, $userPet->Pet->name, $userPet->Pet->petSkill3->name, $totalDamage, null, $damages, null, null, null, null, null);
//		return $record;
	}
	
	// 技能11-名称(当杀死一名敌人后可以再攻击一次)
	public static function skill11($AI, $userPet, $targetPet, $userPetList, $targetPetList, $attackerType) {
		// 计算伤害
		$damage = AttackProbability::damage($userPet->atk, $targetPet->def);
        // 更新血量
        $targetPet = DBManager::updatePetHp($damage, $targetPet);
        // 造成总伤害
        $attackerDamage = $damage;

        // 普攻伤害描述
        $generalDamageDescription = DBManager::buildDamageDescription($AI, $userPet, $targetPet, $damage);
        // 检测是否有反弹伤害
        $reboundDescription = DBManager::checkoutReboundSkill($AI, $userPet, $targetPet, $damage);
        $generalDamageDescription = array_merge($generalDamageDescription, $reboundDescription['description']);
        // 反弹伤害
        $attackedDamage = $reboundDescription['damage'];
        // 技能伤害描述
        $skillDamageDescription = array();
        // 反弹伤害描述
        $reboundDescription = array();
        // 处理技能伤害
		if($targetPet->blood <= 0){
			foreach($targetPetList as $pet){
				if($pet != 0 && $pet->blood > 0 && $pet->number != $targetPet->number){
					$addDamage = AttackProbability::damage($userPet->atk, $pet->def);
					DBManager::updatePetHp($addDamage, $pet);
                    $attackedPetName = $pet->Pet->name;
                    $skillDamage = $addDamage;
                    $attackerDamage += $addDamage;
                    // 检测是否有反弹伤害
                    $rebound = DBManager::checkoutReboundSkill($AI, $userPet, $pet, $addDamage);
                    $reboundDescription = array_merge($reboundDescription, $rebound['description']);
                    $attackedDamage += $rebound['damage'];
                    // 技能伤害描述
                    $skillDamageDescription = DBManager::buildSkillDamageDescription($AI, $userPet, $targetPet, $attackedPetName, $skillDamage);
                    // 追加反弹技能伤害描述
                    $skillDamageDescription = array_merge($skillDamageDescription, $reboundDescription);
                    break;
                }
            }
        }
        // 判断宠物血量,提高宠物防御力
        DBManager::checkoutUpDefSkill($userPetList, $targetPetList);
        // 处理战斗描述
        $record = DBManager::buildDescription($userPetList, $targetPetList, $attackerDamage, $attackedDamage, $generalDamageDescription, $skillDamageDescription, $attackerType, $AI);
        return $record;

//        $damages = array();
//        $damages[0] = array(
//				'pet_index' => $targetPet->number - 1,
//				'pet_name' => $targetPet->Pet->name,
//				'number' => $damage,
//                'HP' => round(($targetPet->blood / $targetPet->max_blood),2)
//
//
//        );
//        $addData = array();
//		if($targetPet->blood <= 0){
//			foreach($targetPetList as $pet){
//				if($pet != 0 && $pet->blood > 0 && $pet->number != $targetPet->number){
//					$addDamage = AttackProbability::damage($userPet, $pet->def);
//					DBManager::updatePetHp($addDamage, $pet);
//					$damages[1]['pet_index'] = $pet->number - 1;
//					$damages[1]['pet_name'] = $pet->Pet->name;
//					$damages[1]['number'] = $addDamage;
//                    $damages[1]['HP'] = round(($pet->blood / $pet->max_blood), 2);
//                    $totalDamage += $addDamage;
//					break;
//				}
//			}
//		}
//
//
//		// 攻击记录
//		$record = DBManager::jointBattleRecord($AI, $attackerType, $userPet->number, $userPet->Pet->name, $userPet->Pet->petSkill3->name, $totalDamage, null, $damages, null, null, null, null, null);
//
//		return $record;
	}
	
	// 技能12-名称(当杀死一名敌人后可以对随机两名敌人再造成伤害[只能触发一次][伤害量=攻击力*50%])
	public static function skill12($AI, $userPet, $targetPet, $userPetList, $targetPetList, $attackerType) {
		// 计算伤害
		$damage = AttackProbability::damage($userPet->atk, $targetPet->def);
		
		$addDamage = ceil($userPet->atk * 0.5);
		
		// 更新宠物血量
		$targetPet = DBManager::updatePetHp($damage, $targetPet);
		
		// 技能属性
		$randomTwoPet = DBManager::randomTwoPet($targetPet, $targetPetList);
        // 总伤害
        $attackerDamage = $damage;

        // 普攻伤害描述
        $generalDamageDescription = DBManager::buildDamageDescription($AI, $userPet, $targetPet, $damage);

        // 检测是否有反弹伤害
        $reboundDescription = DBManager::checkoutReboundSkill($AI, $userPet, $targetPet, $damage);
        $generalDamageDescription = array_merge($generalDamageDescription, $reboundDescription['description']);
        // 反弹伤害
        $attackedDamage = $reboundDescription['damage'];
        // 反弹伤害描述
        $reboundDescription = array();
        $attackedPetName = '';
        $skillDamage = '';

        // 处理技能伤害
        foreach($randomTwoPet as $pet) {
            DBManager::updatePetHp($addDamage, $pet);
            $attackedPetName .=  $pet->Pet->name . ',';
            $skillDamage .= $addDamage . ',';
            $attackerDamage += $addDamage;
            // 技能伤害描述
            $attackedPetName = rtrim($attackedPetName , ',');
            $skillDamage = rtrim($skillDamage, ',');

            // 检测是否有反弹伤害
            $rebound = DBManager::checkoutReboundSkill($AI, $userPet, $pet, $addDamage);
            $reboundDescription = array_merge($reboundDescription, $rebound['description']);
            $attackedDamage = $rebound['attackedDamage'];
        }
        $skillDamageDescription = DBManager::buildSkillDamageDescription($AI, $userPet, $targetPet, $attackedPetName, $skillDamage);
        // 追加反弹技能伤害描述
        $skillDamageDescription = array_merge($skillDamageDescription, $reboundDescription);
        // 判断宠物血量,提高宠物防御力
        DBManager::checkoutUpDefSkill($userPetList, $targetPetList);
        $record = DBManager::buildDescription($userPetList, $targetPetList, $attackerDamage, $attackedDamage, $generalDamageDescription, $skillDamageDescription, $attackerType, $AI);
        return $record;

//        $damages = array();
//		$damages[0] = array(
//				'pet_index' => $targetPet->number - 1,
//				'pet_name' => $targetPet->Pet->name,
//				'number' => $damage,
//                'HP' => round(($targetPet->blood / $targetPet->max_blood),2)
//
//        );
//		$i = 1;
//		foreach($randomTwoPet as $pet){
//			DBManager::updatePetHp($addDamage, $pet);
//			$damages[$i]['pet_index'] = $pet->number - 1;
//			$damages[$i]['pet_name'] = $pet->Pet->name;
//			$damages[$i]['number'] = $addDamage;
//            $damages[$i]['HP'] = round(($pet->blood / $pet->max_blood), 2);
//            $i++;
//			$totalDamage += $addDamage;
//		}
//
//		// 攻击记录
//		$record = DBManager::jointBattleRecord($AI, $attackerType, $userPet->number, $userPet->Pet->name, $userPet->Pet->petSkill3->name, $totalDamage, null, $damages, null, null, null, null, null);
//
//		return $record;
	}
	
	// 技能13-名称(对敌人造成伤害时会对敌人所在横排的其他所有敌人造成伤害[伤害量=攻击力*40%])
	public static function skill13($AI, $userPet, $targetPet, $userPetList, $targetPetList, $attackerType) {
		// 计算伤害
		$damage = AttackProbability::damage($userPet->atk, $targetPet->def);
		
		// 更新宠物血量
		$targetPet = DBManager::updatePetHp($damage, $targetPet);
		
		// 技能属性
		$addDamage = ceil($userPet->atk * 0.4);
		$otherPetList = DBManager::getRowPetList($targetPet, $targetPetList);
        // 总伤害
        $totalDamage = $damage;

        // 普攻伤害描述
        $generalDamageDescription = DBManager::buildDamageDescription($AI, $userPet, $targetPet, $damage);
        // 检测是否有反弹伤害
        $reboundDescription = DBManager::checkoutReboundSkill($AI, $userPet, $targetPet, $damage);
        $generalDamageDescription = array_merge($generalDamageDescription, $reboundDescription['description']);
        // 反弹伤害
        $attackedDamage = $reboundDescription['damage'];
        // 反弹伤害描述
        $reboundDescription = array();
        $attackedPetName = '';
        $skillDamage = '';
        // 处理技能伤害
        foreach($otherPetList as $pet){
			if($pet != 0 && $pet->blood > 0) {
                DBManager::updatePetHp($addDamage, $pet);
                $attackedPetName .=  $pet->Pet->name . ',';
                $skillDamage .= $addDamage . ',';
                $totalDamage += $addDamage;
                // 检测是否有反弹伤害
                $rebound = DBManager::checkoutReboundSkill($AI, $userPet, $pet, $addDamage);
                $reboundDescription = array_merge($reboundDescription, $rebound['description']);
                $attackedDamage += $rebound['damage'];
            }
        }
        $attackedPetName = rtrim($attackedPetName , ',');
        $skillDamage = rtrim($skillDamage, ',');
        // 技能伤害描述
        $skillDamageDescription = DBManager::buildSkillDamageDescription($AI, $userPet, $targetPet, $attackedPetName, $skillDamage);
        // 追加反弹技能伤害描述
        $skillDamageDescription = array_merge($skillDamageDescription, $reboundDescription);
        // 判断宠物血量,提高宠物防御力
        DBManager::checkoutUpDefSkill($userPetList, $targetPetList);
        $record = DBManager::buildDescription($userPetList, $targetPetList, $totalDamage, $attackedDamage, $generalDamageDescription, $skillDamageDescription, $attackerType, $AI);
        return $record;



//        $damages = array();
//		$damages[0] = array(
//				'pet_index' => $targetPet->number - 1,
//				'pet_name' => $targetPet->Pet->name,
//				'number' => $damage,
//                'HP' => round(($targetPet->blood / $targetPet->max_blood),2)
//        );
//		$i = 1;
//		foreach($otherPetList as $pet){
//			if($pet != 0 && $pet->blood > 0){
//				DBManager::updatePetHp($addDamage, $pet);
//				$damages[$i]['pet_index'] = $pet->number - 1;
//				$damages[$i]['pet_name'] = $pet->Pet->name;
//				$damages[$i]['number'] = $addDamage;
//                $damages[$i]['HP'] = round(($pet->blood / $pet->max_blood), 2);
//                $totalDamage += $addDamage;
//				$i++;
//			}
//		}
//
//		// 攻击记录
//		$record = DBManager::jointBattleRecord($AI, $attackerType, $userPet->number, $userPet->Pet->name, $userPet->Pet->petSkill3->name, $totalDamage, null, $damages, null, null, null, null, null);
//
//		return $record;
	}
	
	// 技能14-名称(对敌人造成伤害时会对敌人所在纵排的其他所有敌人造成伤害[伤害量=攻击力*60%])
	public static function skill14($AI, $userPet, $targetPet, $userPetList, $targetPetList, $attackerType) {
		// 计算伤害
		$damage = AttackProbability::damage($userPet->atk, $targetPet->def);
		
		// 更新宠物血量
		$targetPet = DBManager::updatePetHp($damage, $targetPet);

        // 普攻伤害描述
        $generalDamageDescription = DBManager::buildDamageDescription($AI, $userPet, $targetPet, $damage);

        // 检测是否有反弹伤害
        $reboundDescription = DBManager::checkoutReboundSkill($AI, $userPet, $targetPet, $damage);
        $generalDamageDescription = array_merge($generalDamageDescription, $reboundDescription['description']);
        // 反弹伤害
        $attackedDamage = $reboundDescription['damage'];
        // 反弹伤害描述
        $reboundDescription = array();
		// 技能属性
		$totalDamage = $damage;
		$addDamage = ceil($userPet->atk * 0.6);

        $attackedPetName = '';
        $skillDamage = '';
        $skillDamageDescription = array();
		$otherPet = DBManager::getColPetList($targetPet, $targetPetList);
		if($otherPet != 0 && $otherPet->blood > 0){
			DBManager::updatePetHp($addDamage, $otherPet);
            $attackedPetName .=  $otherPet->Pet->name . ',';
            $skillDamage .= $addDamage . ',';
            $totalDamage += $addDamage;
            $attackedPetName = rtrim($attackedPetName , ',');
            $skillDamage = rtrim($skillDamage, ',');
            // 检测是否有反弹伤害
            $rebound = DBManager::checkoutReboundSkill($AI, $userPet, $otherPet, $addDamage);
            $reboundDescription = array_merge($reboundDescription, $rebound['description']);
            $attackedDamage += $rebound['damage'];
            // 技能伤害描述
            $skillDamageDescription = DBManager::buildSkillDamageDescription($AI, $userPet, $targetPet, $attackedPetName, $skillDamage);

            // 追加反弹技能伤害描述
            $skillDamageDescription = array_merge($skillDamageDescription, $reboundDescription);
        }
        // 判断宠物血量,提高宠物防御力
        DBManager::checkoutUpDefSkill($userPetList, $targetPetList);
        $record = DBManager::buildDescription($userPetList, $targetPetList, $totalDamage, $attackedDamage, $generalDamageDescription, $skillDamageDescription, $attackerType, $AI);
        return $record;
//		// 攻击记录
//		$record = DBManager::jointBattleRecord($AI, $attackerType, $userPet->number, $userPet->Pet->name, $userPet->Pet->petSkill3->name, $totalDamage, null, $damages, null, null, null, null, null);
//
//		return $record;
	}
	
	// 技能15-名称(当杀死一名敌人后可以对所有敌人再造成伤害[只能触发一次][伤害量=攻击力*20%])
	public static function skill15($AI, $userPet, $targetPet, $userPetList, $targetPetList, $attackerType) {
		// 计算伤害
		$damage = AttackProbability::damage($userPet->atk, $targetPet->def);
		
		// 更新宠物血量
		$targetPet = DBManager::updatePetHp($damage, $targetPet);

        // 普攻伤害描述
        $generalDamageDescription = DBManager::buildDamageDescription($AI, $userPet, $targetPet, $damage);
        // 检测是否有反弹伤害
        $reboundDescription = DBManager::checkoutReboundSkill($AI, $userPet, $targetPet, $damage);
        $generalDamageDescription = array_merge($generalDamageDescription, $reboundDescription['description']);
        // 反弹伤害
        $attackedDamage = $reboundDescription['damage'];
        // 反弹伤害描述
        $reboundDescription = array();
		// 技能属性
		$totalDamage = $damage;
        $skillDamageDescription = array();
		if($targetPet->blood == 0){
			$addDamage = ceil($userPet->atk * 0.2);
            $attackedPetName = '';
            $skillDamage = '';
			foreach($targetPetList as $pet){
				if($pet->blood > 0 && $pet != 0){
					DBManager::updatePetHp($addDamage, $pet);
                    $attackedPetName .=  $pet->Pet->name . ',';
                    $skillDamage .= $addDamage . ',';
                    $totalDamage += $addDamage;
                    // 检测是否有反弹伤害
                    $rebound = DBManager::checkoutReboundSkill($AI, $userPet, $pet, $addDamage);
                    $reboundDescription = array_merge($reboundDescription, $rebound['description']);
                    $attackedDamage += $rebound['damage'];
                }
            }
            $attackedPetName = rtrim($attackedPetName , ',');
            $skillDamage = rtrim($skillDamage, ',');
            // 技能伤害描述
            $skillDamageDescription = DBManager::buildSkillDamageDescription($AI, $userPet, $targetPet, $attackedPetName, $skillDamage);
            // 追加反弹技能伤害描述
            $skillDamageDescription = array_merge($skillDamageDescription, $reboundDescription);
        }
        // 判断宠物血量,提高宠物防御力
        DBManager::checkoutUpDefSkill($userPetList, $targetPetList);
        $record = DBManager::buildDescription($userPetList, $targetPetList, $totalDamage, $attackedDamage, $generalDamageDescription, $skillDamageDescription, $attackerType, $AI);
        return $record;
//		// 攻击记录
//		$record = DBManager::jointBattleRecord($AI, $attackerType, $userPet->number, $userPet->Pet->name, $userPet->Pet->petSkill3->name, $totalDamage, null, $damages, null, null, null, null, null);
//
//		return $record;
	}
	
	// 技能17-名称(受到伤害后可以把伤害量的15%反弹给攻击者[伤害无视防御])
	public static function skill17($AI, $userPet, $targetPet, $userPetList, $targetPetList, $attackerType) {
		// 计算伤害
		$damage = AttackProbability::damage($userPet->atk, $targetPet->def);
		
		$targetPet = DBManager::updatePetHp($damage, $targetPet);

        // 普攻伤害描述
        $generalDamageDescription = DBManager::buildDamageDescription($AI, $userPet, $targetPet, $damage);
        // 检测是否有反弹伤害
        $reboundDescription = DBManager::checkoutReboundSkill($AI, $userPet, $targetPet, $damage);
        $generalDamageDescription = array_merge($generalDamageDescription, $reboundDescription['description']);
        // 反弹伤害
        $attackedDamage = $reboundDescription['damage'];
        // 判断宠物血量,提高宠物防御力
        DBManager::checkoutUpDefSkill($userPetList, $targetPetList);
        $record = DBManager::buildDescription($userPetList, $targetPetList, $damage, $attackedDamage, $generalDamageDescription, array(), $attackerType, $AI);
        return $record;

	}
	
	// 技能19-名称(攻击时可以给血量最少的一名友方单位恢复生命[治疗量=攻击力*30%])
	public static function skill19($AI, $userPet, $targetPet, $userPetList, $targetPetList, $attackerType) {
		// 计算伤害
		$damage = AttackProbability::damage($userPet->atk, $targetPet->def);
		// 更新宠物血量
		$targetPet = DBManager::updatePetHp($damage, $targetPet);

        // 普攻伤害描述
        $generalDamageDescription = DBManager::buildDamageDescription($AI, $userPet, $targetPet, $damage);
        // 检测是否有反弹伤害
        $reboundDescription = DBManager::checkoutReboundSkill($AI, $userPet, $targetPet, $damage);
        $generalDamageDescription = array_merge($generalDamageDescription, $reboundDescription['description']);
        // 反弹伤害
        $attackedDamage = $reboundDescription['damage'];
		// 技能属性
		$addBlood = ceil($userPet->atk * 0.3);
		$tmp = $userPet;
		foreach($userPetList as $pet){
			if($tmp->blood > $pet->blood && $pet != 0){
				$tmp = $pet;
			}
		}
		$newBlood = $tmp->blood + $addBlood;
		DBManager::updatePetBlood($tmp, $newBlood);

		// 回血宠物名称,回复血量
        $restorePetName = $tmp->Pet->name;
        $skillRestoreBlood = (String)$addBlood;

		$totalDamage = $damage;

		$skillRestoreDescription = DBManager::buildSkillGainDescription($userPet, $restorePetName, $skillRestoreBlood, 1);
        // 判断宠物血量,提高宠物防御力
        DBManager::checkoutUpDefSkill($userPetList, $targetPetList);
        $record = DBManager::buildDescription($userPetList, $targetPetList, $totalDamage, $attackedDamage, $generalDamageDescription, $skillRestoreDescription, $attackerType, $AI);
        return $record;
		
//		// 攻击记录
//		$record = DBManager::jointBattleRecord($AI, $attackerType, $userPet->number, $userPet->Pet->name, $userPet->Pet->petSkill3->name, $totalDamage, null, $damages, $restores, null, null, null, null);
//
//		return $record;
	}
	
	// 技能20-名称(攻击时可以给周围的友方单位回复生命[治疗量=攻击力*10%])
	public static function skill20($AI, $userPet, $targetPet, $userPetList, $targetPetList, $attackerType) {
		// 计算伤害
		$damage = AttackProbability::damage($userPet->atk, $targetPet->def);
		// 更新宠物血量
		$targetPet = DBManager::updatePetHp($damage, $targetPet);

        // 普攻伤害描述
        $generalDamageDescription = DBManager::buildDamageDescription($AI, $userPet, $targetPet, $damage);
        // 检测是否有反弹伤害
        $reboundDescription = DBManager::checkoutReboundSkill($AI, $userPet, $targetPet, $damage);
        $generalDamageDescription = array_merge($generalDamageDescription, $reboundDescription['description']);
        // 反弹伤害
        $attackedDamage = $reboundDescription['damage'];
        $totalDamage = $damage;
        // 技能回血
        $addBlood = ceil($userPet->atk * 0.1);

        $restorePetName = '';
        $skillRestoreBlood = '';
        // 获取周围友方单位加血
        foreach($userPetList as $pet){
            if ($pet->pet_id != $userPet->pet_id
                && $pet != 0
                && $pet->blood > 0
                && (($userPet->number + 1 == $pet->number && $userPet->number != 3)
                    || ($userPet->number - 1 == $pet->number && $userPet->number != 4)
                    || ($userPet->number - 3 == $pet->number)
                    || ($userPet->number + 3 == $pet->number)
                )
            ) {
                $newBlood = $pet->blood + $addBlood;
                DBManager::updatePetBlood($pet, $newBlood);
                $restorePetName .= $pet->Pet->name . ',';
                $skillRestoreBlood .= $addBlood . ',';
            }
        }
        $restorePetName = rtrim($restorePetName , ',');
        $skillRestoreBlood = rtrim($skillRestoreBlood, ',');
        // 技能回血描述
        $skillRestoreDescription = DBManager::buildSkillGainDescription($userPet, $restorePetName, $skillRestoreBlood, 1);
        // 判断宠物血量,提高宠物防御力
        DBManager::checkoutUpDefSkill($userPetList, $targetPetList);
        $record = DBManager::buildDescription($userPetList, $targetPetList, $totalDamage, $attackedDamage, $generalDamageDescription, $skillRestoreDescription, $attackerType, $AI);
        return $record;

//		// 攻击记录
//		$record = DBManager::jointBattleRecord($AI, $attackerType, $userPet->number, $userPet->Pet->name, $userPet->Pet->petSkill3->name, $totalDamage, null, $damages, $restores, null, null, null, null);
//
//		return $record;
	}
	
	// 技能22-名称(提高全部友方单位攻击力10%)
	public static function skill22($AI, $userPet, $targetPet, $userPetList, $targetPetList, $attackerType) {
//		// 技能属性
//		$attacks = DBManager::batchAddPetAtk($userPetList, 0.1);

		// 计算伤害
		$damage = AttackProbability::damage($userPet->atk, $targetPet->def);
		
		$targetPet = DBManager::updatePetHp($damage, $targetPet);

        // 普攻伤害描述
        $generalDamageDescription = DBManager::buildDamageDescription($AI, $userPet, $targetPet, $damage);
        // 检测是否有反弹伤害
        $reboundDescription = DBManager::checkoutReboundSkill($AI, $userPet, $targetPet, $damage);
        $generalDamageDescription = array_merge($generalDamageDescription, $reboundDescription['description']);
        // 反弹伤害
        $attackedDamage = $reboundDescription['damage'];
		$totalDamage = $damage;

//        // 技能增益描述
//        $skillGainDescription = DBManager::buildSkillGainDescription($userPet, $attacks['petName'], $attacks['gainNumber'], 2);

        $record = DBManager::buildDescription($userPetList, $targetPetList, $totalDamage, $attackedDamage, $generalDamageDescription, array(), $attackerType, $AI);
        return $record;

//		// 攻击记录
//		$record = DBManager::jointBattleRecord($AI, $attackerType, $userPet->number, $userPet->Pet->name, $userPet->Pet->petSkill3->name, $totalDamage, null, $damages, null, $attacks, null, null, null);
//
//		return $record;
	}
	
	// 技能23-名称(提高全部友方单位生命上限10%)
	public static function skill23($AI, $userPet, $targetPet, $userPetList, $targetPetList, $attackerType) {
		// 计算伤害
		$damage = AttackProbability::damage($userPet->atk, $targetPet->def);
		
		$targetPet = DBManager::updatePetHp($damage, $targetPet);

        // 普攻伤害描述
        $generalDamageDescription = DBManager::buildDamageDescription($AI, $userPet, $targetPet, $damage);
        // 检测是否有反弹伤害
        $reboundDescription = DBManager::checkoutReboundSkill($AI, $userPet, $targetPet, $damage);
        $generalDamageDescription = array_merge($generalDamageDescription, $reboundDescription['description']);
        // 反弹伤害
        $attackedDamage = $reboundDescription['damage'];
//        // 技能属性
//		$bloods = DBManager::batchAddPetMaxBlood($userPetList, 0.1);

		$totalDamage = $damage;

//        // 技能增益描述
//        $skillGainDescription = DBManager::buildSkillGainDescription($userPet, $bloods['petName'], $bloods['gainNumber'], 4);

        $record = DBManager::buildDescription($userPetList, $targetPetList, $totalDamage, $attackedDamage, $generalDamageDescription, array(), $attackerType, $AI);
        return $record;
//		// 攻击记录
//		$record = DBManager::jointBattleRecord($AI, $attackerType, $userPet->number, $userPet->Pet->name, $userPet->Pet->petSkill3->name, $totalDamage, null, $damages, null, null,  null, null, $bloods);
//
//		return $record;
	}
	
	// 技能24-名称(提高全部友方单位防御10%)
	public static function skill24($AI, $userPet, $targetPet, $userPetList, $targetPetList, $attackerType) {
//		// 技能属性
//		$defenses = DBManager::batchAddPetDef($userPetList, 0.1);
		
		// 计算伤害
		$damage = AttackProbability::damage($userPet->atk, $targetPet->def);
		
		$targetPet = DBManager::updatePetHp($damage, $targetPet);

        // 普攻伤害描述
        $generalDamageDescription = DBManager::buildDamageDescription($AI, $userPet, $targetPet, $damage);
        // 检测是否有反弹伤害
        $reboundDescription = DBManager::checkoutReboundSkill($AI, $userPet, $targetPet, $damage);
        $generalDamageDescription = array_merge($generalDamageDescription, $reboundDescription['description']);
        // 反弹伤害
        $attackedDamage = $reboundDescription['damage'];
		$totalDamage = $damage;

//        // 技能增益描述
//        $skillGainDescription = DBManager::buildSkillGainDescription($userPet, $defenses['petName'], $defenses['gainNumber'], 3);

        $record = DBManager::buildDescription($userPetList, $targetPetList, $totalDamage, $attackedDamage, $generalDamageDescription, array(), $attackerType, $AI);
        return $record;
//		// 攻击记录
//		$record = DBManager::jointBattleRecord($AI, $attackerType, $userPet->number, $userPet->Pet->name, $userPet->Pet->petSkill3->name, $totalDamage, null, $damages, null, null, $defenses, null, null);
//
//		return $record;
	}
	
	// 技能25-名称(攻击时可以治疗全部友方单位[治疗量=攻击力*10%])
	public static function skill25($AI, $userPet, $targetPet, $userPetList, $targetPetList, $attackerType) {
		// 计算伤害
		$damage = AttackProbability::damage($userPet->atk, $targetPet->def);
		
		$targetPet = DBManager::updatePetHp($damage, $targetPet);

        // 普攻伤害描述
        $generalDamageDescription = DBManager::buildDamageDescription($AI, $userPet, $targetPet, $damage);
        // 检测是否有反弹伤害
        $reboundDescription = DBManager::checkoutReboundSkill($AI, $userPet, $targetPet, $damage);
        $generalDamageDescription = array_merge($generalDamageDescription, $reboundDescription['description']);
        // 反弹伤害
        $attackedDamage = $reboundDescription['damage'];
		// 技能属性
		$addBlood = ceil($userPet->atk * 0.1);
        $restorePetName = '';
        $skillRestoreBlood = '';
		foreach($userPetList as $pet){
			if($pet != 0 && $pet->blood > 0){
				$newBlood = $pet->blood + $addBlood;
				DBManager::updatePetBlood($pet, $newBlood);
                $restorePetName .=  $pet->Pet->name . ',';
                $skillRestoreBlood .= $addBlood . ',';
			}
		}
		

		$totalDamage = $damage;

        $restorePetName = rtrim($restorePetName , ',');
        $skillRestoreBlood = rtrim($skillRestoreBlood, ',');
        // 技能回血描述
        $skillRestoreDescription = DBManager::buildSkillGainDescription($userPet, $restorePetName, $skillRestoreBlood, 1);
        $record = DBManager::buildDescription($userPetList, $targetPetList, $totalDamage, $attackedDamage, $generalDamageDescription, $skillRestoreDescription, $attackerType, $AI);
        return $record;

//		// 攻击记录
//		$record = DBManager::jointBattleRecord($AI, $attackerType, $userPet->number, $userPet->Pet->name, $userPet->Pet->petSkill3->name, $totalDamage, null, $damages, $restores, null, null, null, null);
//
//		return $record;
	}

    /**
     * 敌人血量越少造成伤害越高(每损失5%,伤害提高1%)
     *
     * @param $AI
     * @param $userPet
     * @param $targetPet
     * @param $userPetList
     * @param $targetPetList
     * @param $attackerType
     * @return mixed
     */
    public static function skill16($AI, $userPet, $targetPet, $userPetList, $targetPetList, $attackerType)
    {
        // 计算伤害
        $damage = AttackProbability::damage($userPet->atk, $targetPet->def);
        // 计算损失血量
        $lossBlood = round((1 - ($targetPet->blood / $targetPet->max_blood)),2);
        // 计算提升后的伤害
        $totalDamage = round((($lossBlood / 0.05) * 0.01) * $damage);

        $targetPet = DBManager::updatePetHp($totalDamage, $targetPet);

        // 普攻伤害描述
        $generalDamageDescription = DBManager::buildDamageDescription($AI, $userPet, $targetPet, $damage);
        // 检测是否有反弹伤害
        $reboundDescription = DBManager::checkoutReboundSkill($AI, $userPet, $targetPet, $damage);
        $generalDamageDescription = array_merge($generalDamageDescription, $reboundDescription['description']);
        // 反弹伤害
        $attackedDamage = $reboundDescription['damage'];

        $record = DBManager::buildDescription($userPetList, $targetPetList, $totalDamage, $attackedDamage, $generalDamageDescription, array(), $attackerType, $AI);
        return $record;
	}

    /**
     * 自身血量越低,伤害越高(每损失10%生命,伤害提高3%)
     *
     * @param $AI
     * @param $userPet
     * @param $targetPet
     * @param $userPetList
     * @param $targetPetList
     * @param $attackerType
     * @return mixed
     */
    public static function skill18($AI, $userPet, $targetPet, $userPetList, $targetPetList, $attackerType)
    {
        // 计算伤害
        $damage = AttackProbability::damage($userPet->atk, $targetPet->def);
        // 计算损失血量
        $lossBlood = round((1 - ($userPet->blood / $userPet->max_blood)),2);
        // 计算提升后的伤害
        $totalDamage = round((($lossBlood / 0.1) * 0.03) * $damage);

        $targetPet = DBManager::updatePetHp($totalDamage, $targetPet);

        // 普攻伤害描述
        $generalDamageDescription = DBManager::buildDamageDescription($AI, $userPet, $targetPet, $damage);
        // 检测是否有反弹伤害
        $reboundDescription = DBManager::checkoutReboundSkill($AI, $userPet, $targetPet, $damage);
        $generalDamageDescription = array_merge($generalDamageDescription, $reboundDescription['description']);
        // 反弹伤害
        $attackedDamage = $reboundDescription['damage'];

        $record = DBManager::buildDescription($userPetList, $targetPetList, $totalDamage, $attackedDamage, $generalDamageDescription, array(), $attackerType, $AI);
        return $record;
	}

    /**
     * 血量越少,防御越高(没损失10%,防御提高10%)
     * 攻击不触发
     *
     * @param $AI
     * @param $userPet
     * @param $targetPet
     * @param $userPetList
     * @param $targetPetList
     * @param $attackerType
     * @return mixed
     */
    public static function skill21($AI, $userPet, $targetPet, $userPetList, $targetPetList, $attackerType)
    {
        // 计算伤害
        $damage = AttackProbability::damage($userPet->atk, $targetPet->def);

        $targetPet = DBManager::updatePetHp($damage, $targetPet);

        // 普攻伤害描述
        $generalDamageDescription = DBManager::buildDamageDescription($AI, $userPet, $targetPet, $damage);
        // 检测是否有反弹伤害
        $reboundDescription = DBManager::checkoutReboundSkill($AI, $userPet, $targetPet, $damage);
        $generalDamageDescription = array_merge($generalDamageDescription, $reboundDescription['description']);
        // 反弹伤害
        $attackedDamage = $reboundDescription['damage'];

        $record = DBManager::buildDescription($userPetList, $targetPetList, $damage, $attackedDamage, $generalDamageDescription, array(), $attackerType, $AI);
        return $record;
    }

    /**
     * 被动技能,增加血量上限20%
     *
     * @param $pet
     * @return array
     */
    public static function skill1($pet)
    {
        $petMaxBlood = ceil($pet->blood * 1.2);
        return ['blood' => $petMaxBlood];
    }

    /**
     * 被动技能,增加防御力20%
     *
     * @param $pet
     * @return array
     */
    public static function skill4($pet)
    {
        $petDef = ceil($pet->def * 1.2);
        return ['def' => $petDef];
    }

    /**
     * 被动技能,增加攻击力20%
     *
     * @param $pet
     * @return array
     */
    public static function skill7($pet)
    {
        $petAtk = ceil($pet->atk * 1.2);
        return ['atk' => $petAtk];
    }
}