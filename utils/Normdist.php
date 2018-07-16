<?php

namespace Fichat\Utils;

/** 
 * php 实现excel的normdist函数
 *
 * 使用方法：
    $list = array(1.09,1.50,1.31,1.44);
    $normdist = new normdist($list);
    echo $normdist->getCdf($list[0]);
 */
class Normdist {
    
    public $list = array();
    public $mu;
    public $sigma;
    
    public function __construct($list, $sigmaParam)
    {
        $this->list  = $list;
        $this->mu    = $this->getMu($list); // 获取平均值
        $this->sigma = $this->getSigma($list, $sigmaParam); // 获取标准偏差
    }
    
    public function getFichatCdf($value) {
    	return 2 * $this->getCdf($value) * 100;
    }
    
    public function getPetCdf($value) {
    	return $this->getCdf($value) * 100;
    }
    
    /**
     * @name 正态分布的累积概率函数
     * @param string|integer $value
     * @return number
     */
    public function getCdf($value)
    {
        $mu = $this->mu;
        $sigma = $this->sigma;
        $t = $value - $mu;
        $y = 0.5 * $this->erfcc(-$t / ($sigma * sqrt(2.0)));
        if ($y > 1.0) $y = 1.0;
        
        return $y;
    }
     
    private function erfcc($x)
    {
        $z = abs($x);
        $t = 1. / (1. + 0.5 * $z);
        $r = 
            $t * exp(-$z*$z-1.26551223+
            $t*(1.00002368+
            $t*(.37409196+
            $t*(.09678418+
            $t*(-.18628806+
            $t*(.27886807+
            $t*(-1.13520398+
            $t*(1.48851587+
            $t*(-.82215223+
            $t*.17087277)))))))));
        if ($x >= 0.)
            return $r;
        else
            return 2 - $r;
    }

    /**
     * @name 获取平均值
     * @param array $list
     * @return number
     */
    private function getMu($list)
    {
        return array_sum($list) / count($list);
    }

    /**
     * @name 获取标准差
     * @param array $list
     * @return number
     * @beizhu 标准差  = 方差的平方根
     */
    private function getSigma($list, $sigmaParam)
    {
        $total_var = 0;
        $mu = $this->getMu($list);
        foreach ($list as $v) {
            $total_var += pow( ($v - $mu), 2);
        }
        return sqrt( $total_var / (count($list) - 1 )) * $sigmaParam; // 这里为什么数组元素个数要减去1
    }
}