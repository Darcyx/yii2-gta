<?php
/**
 * Gta模板标记属性集合
 *
 * @package        DedeCMS.Libraries
 * @license        http://help.dedecms.com/usersguide/license.html
 * @version        $Id: dedetag.class.php 1 10:33 2010年7月6日Z tianya $
 * @date           2018/5/16
 */

namespace yii\gta\tag;

/**
 * Class GtaAttribute
 * @package GtaTpl\GtaTag
 */
class GtaAttribute
{
    /**
     * @var int 属性个数
     */
    public $Count = -1;

    /**
     * @var string 属性元素的集合
     */
    public $Items = "";

    /**
     * 获得某个属性
     * @param $str
     * @return string
     */
    function GetAtt($str)
    {
        return $str == "" ? '' : isset($this->Items[$str]) ? $this->Items[$str] : '';
    }

    /**
     * @param $str
     * @return string
     */
    function GetAttribute($str)
    {
        return $this->GetAtt($str);
    }

    /**
     * 判断属性是否存在
     * @param $str
     * @return bool
     */
    function IsAttribute($str)
    {
        return isset($this->Items[$str]) ? true : false;
    }

    /**
     * 获得标记名称
     * @return string
     */
    function GetTagName()
    {
        return $this->GetAtt("tagname");
    }

    /**
     * 获得属性个数
     * @return int
     */
    function GetCount()
    {
        return $this->Count + 1;
    }
}