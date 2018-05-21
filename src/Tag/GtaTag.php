<?php
/**
 * GTA模板类
 * @package        DedeCMS.Libraries
 * @license        http://help.dedecms.com/usersguide/license.html
 * @version        $Id: dedetag.class.php 1 10:33 2010年7月6日Z tianya $
 */

namespace yii\gta\tag;

/**
 * class GtaTag 标记的数据结构描述
 * @package GtaTag
 */
class GtaTag
{
    /**
     * @var bool 标记是否已被替代，供解析器使用
     */
    public $IsReplace = false;

    /**
     * @var string 标记名称
     */
    public $TagName = "";

    /**
     * @var string 标记之间的文本
     */
    public $InnerText = "";

    /**
     * @var int 标记起始位置
     */
    public $StartPos = 0;

    /**
     * @var int 标记结束位置
     */
    public $EndPos = 0;

    /**
     * @var string 标记属性描述,即是class GtaAttribute
     */
    public $CAttribute = "";

    /**
     * @var string 标记的值
     */
    public $TagValue = "";

    /**
     * @var int
     */
    public $TagID = 0;

    /**
     *  获取标记的名称和值
     *
     * @access    public
     * @return    string
     */
    function GetName()
    {
        return strtolower($this->TagName);
    }

    /**
     *  获取值
     *
     * @access    public
     * @return    string
     */
    function GetValue()
    {
        return $this->TagValue;
    }

    /**
     * 判断属性是否存在
     * @param $str
     * @return mixed
     */
    function IsAttribute($str)
    {
        return $this->CAttribute->IsAttribute($str);
    }

    /**
     * 获取标记的指定属性
     * @param $str
     * @return mixed
     */
    function GetAttribute($str)
    {
        return $this->CAttribute->GetAttribute($str);
    }

    /**
     * 获取标记的指定属性
     * @param $str
     * @return mixed
     */
    function GetAtt($str)
    {
        return $this->CAttribute->GetAttribute($str);
    }

    /**
     * 获取标记之间的文本
     * @return string
     */
    function GetInnerText()
    {
        return $this->InnerText;
    }
}