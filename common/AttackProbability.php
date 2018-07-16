<?php
namespace Fichat\Common;

use Fichat\Utils\Normdist;

class AttackProbability {
	// 计算命中
	public static function calculateHit($attackerAtk, $defenderDef) {
		$defenderDef = $defenderDef / 2;
		$possibility = $attackerAtk / ($attackerAtk + $defenderDef);
		$possibility = $possibility * 100;
		return mt_rand(1, 100) < $possibility;
	}
	
	// 计算伤害
	public static function damage($atk, $def) {
		$minDamage = 1;
		$maxDamage = $atk;
		$reverse = false;
		$lucky = false;
		if($atk != $def){
			$possibility = $atk > $def ? ($atk - $def) / $atk : $atk / ($atk + $def + $def - $atk) ;
			$possibility = $possibility * $possibility * 100;
			$def = $def / 2;
			$randomNumber = mt_rand(1, 100);
			if($randomNumber < $possibility){
				$lucky = true;
				$minDamage = $atk > $def ? $atk - $def + 1 : $atk / 2;
				$maxDamage = $atk;
				$reverse = true;
			}else {
				$minDamage = 1;
				$maxDamage = $atk > $def ? $atk - $def : $atk;
				$reverse = $atk <= $def;
			}
		} else {
			$reverse = true;
		}
		
		$sigma = ($def - $atk) / ($def + $atk) / 5;
		return self::getFinalDamage($minDamage, $maxDamage, $reverse, $lucky, $sigma);
	}
	
	private static function getFinalDamage($minDamage,$maxDamage, $reverse, $lucky, $sigma) {
		if ($minDamage == $maxDamage) return $minDamage;
		$list = array();
		$length = ($maxDamage - $minDamage) * 2 + 1;
		$index = 0;
		for ($i = $minDamage; $i <= $maxDamage; $i++) {
			$list[$index] = $i;
			$list[$length - $index - 1] = $maxDamage * 2 - $minDamage - $index;
			$index++;
		}
		$normdist;
		if ($lucky) {
			$normdist = new Normdist($list, 0.2 - $sigma);
		} else {
			$normdist = new Normdist($list, 0.25 - $sigma);
		}
		$randNumber = mt_rand(1, 100);
		$resultDamage = $minDamage;
		for ($i = $minDamage; $i <= $maxDamage; $i++) {
			if ($randNumber < $normdist->getFichatCdf($i)) {
				break;
			} else {
				$resultDamage = $i;
			}
		}
		if ($reverse) {
			$resultDamage = $maxDamage + $minDamage - $resultDamage;
		} else {
			$resultDamage++;
		}
		return ceil($resultDamage);
	}
	
	// 计算瓶盖掉落比例
	public static function capFallPossibility($user, $fullHp, $damage) {
		$possibility = ((intval($damage) / intval($fullHp)) + (1 - intval($user->hp) / intval($fullHp))) / 2;
		return $possibility;
	}
	
	// 获取攻击者拾取瓶盖数量
	public static function calculateCapFallNumber($damage, $attk, $afterCapNumber) {
		$capNumber = $damage / $attk * $afterCapNumber;
		return floor($capNumber);
	}
	
	// 计算经验值
	public static function calculateExp($damage, $attk) {
		if (2 * $damage > $attk) {
			return mt_rand(3, 5);
		} else {
			return mt_rand(1, 3);
		}
	}

	// 计算宠物级别
	public static function calculatePetLevel($userLevel) {
		$minLevel = $userLevel - 6;
		$maxLevel = $userLevel + 4;
		$list = array();
		$index = 0;
		for ($i = $minLevel; $i <= $maxLevel; $i++) {
			$list[$index] = $i;
			$index++;
		}
		
		$normdist = new Normdist($list, 0.5);
		$randNumber = mt_rand(1, 100);
		$resultLevel = $minLevel;
		for ($i = $minLevel; $i <= $maxLevel; $i++) {
			if ($randNumber < $normdist->getPetCdf($i)) {
				break;
			} else {
				$resultLevel = $i;
			}
		}
		$resultLevel = $resultLevel > 0 ? $resultLevel : 1;
		return $resultLevel;
	}
}