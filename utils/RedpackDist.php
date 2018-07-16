<?php

namespace Fichat\Utils;

use Fichat\Common\RedisManager;
use Fichat\Common\ReturnMessageManager;


class RedpackDist {

    public static function makeRedpackDist($redis, $id, $type, $amount, $num, $min = 0.01, $sigma = 2)
    {
        if ($type == 2) {
            if ($num > 0) {
                if ($num < $sigma) {
                    $sigma = $num;
                }
                // 初始化红包数组
                $distAmountList = self::initDistAmountList($num, $min);
                $amount -= array_sum($distAmountList);
                // 循环处理
                $distAmountList = self::loopDistAmount($amount, $distAmountList, $num, $min, $sigma);
                // 保存分配金额到红包分配缓存表中
                self::saveRedpackDistList($redis, $id, $distAmountList);
                // 保存成功
                return true;
            } else {
                // 标准差值不能大于分配的总数量 或 不能等于0
                return ReturnMessageManager::buildReturnMessage('E0294');
            }
        } else {
            // 初始化红包数组
            $distAmountList = self::initDistAmountList($num, round($amount / $num, 2));
            // 保存分配金额到红包分配缓存表中
            self::saveRedpackDistList($redis, $id, $distAmountList);
            // 保存成功
            return true;
        }
    }

    private static function loopDistAmount($amount, $distAmountList, $num, $min, $sigma)
    {
        if ($amount < $min * $num) {
            $avg = $min;
        } else {
            $avg = round(($amount / $num), 2);
        }
        $max = round($avg * $sigma, 2);
        // 循环
        foreach($distAmountList as $idx => $money) {
            if ($amount > 0) {
                $dist_money = round(rand($min * 100, $max * 100) / 100, 2);
                if ($dist_money < $amount) {
                    $new_money = round($money + $dist_money, 2);
                    $amount = round($amount - $dist_money, 2);
                } else {
                    $new_money = round($money + $amount, 2);
                    $amount = 0;
                }
                $distAmountList[$idx] = $new_money;
            } else {
                break;
            }
        }
        if ($amount > 0) {
            $distAmountList = Self::loopDistAmount($amount, $distAmountList, $num, $min, $sigma);
        }
        shuffle($distAmountList);
        return $distAmountList;
    }

    /**
     * 初始化红包配额的初始值(最小值)
     * @param:  $num    红包数量
     * @param:  $min    红包最小金额
     * @return: $distAmountList    红包配额数组
     */
    private static function initDistAmountList($num, $min)
    {
        $distAmountList = [];
        for($i = 0; $i < $num; $i++) {
            array_push($distAmountList, $min);
        }
        return $distAmountList;
    }

    private static function saveRedpackDistList($redis, $id, $distAmountList)
    {
        // 构建红包分配Key
        $key = RedisClient::redpack_dist_key($id);
        // 将数据依次添加进列表中
        return RedisManager::saveRedPackDistAmount($redis, $key, $distAmountList);
    }
}