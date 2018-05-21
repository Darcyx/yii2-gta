<?php
/**
 * 属性解析器(本版本中已经支持使用\'这种语法,和用.间隔表示name属性,如 field.body)
 *
 * @package        DedeCMS.Libraries
 * @license        http://help.dedecms.com/usersguide/license.html
 * @version        $Id: dedetag.class.php 1 10:33 2010年7月6日Z tianya $
 * @date           2018/5/16
 */

namespace yii\gta\tag;

class GtaAttributeParse
{
    public $sourceString = "";
    public $sourceMaxSize = 1024;
    public $cAttributes = "";
    public $charToLow = true;

    /**
     * @param string $str
     */
    public function SetSource($str = '')
    {
        $this->cAttributes  = new GtaAttribute();
        $this->sourceString = trim(preg_replace("/[ \r\n\t]{1,}/", " ", $str));

        //为了在function内能使用数组，这里允许对[ ]进行转义使用
        $this->sourceString = str_replace('\]', ']', $this->sourceString);
        $this->sourceString = str_replace('[', '[', $this->sourceString);
        /*
        $this->sourceString = str_replace('\>','>',$this->sourceString);
        $this->sourceString = str_replace('<','>',$this->sourceString);
        $this->sourceString = str_replace('{','{',$this->sourceString);
        $this->sourceString = str_replace('\}','}',$this->sourceString);
        */

        $strLen = strlen($this->sourceString);
        if ($strLen > 0 && $strLen <= $this->sourceMaxSize) {
            $this->ParseAttribute();
        }
    }


    /**
     * 解析属性
     * @return bool
     */
    public function ParseAttribute()
    {
        $tmpatt                   = '';
        $tmpvalue                 = '';
        $startdd                  = -1;
        $ddtag                    = '';
        $hasAttribute             = false;
        $strLen                   = strlen($this->sourceString);
        $this->cAttributes->Items = array();

        // 获得Tag的名称，解析到 cAtt->GetAtt('tagname') 中
        for ($i = 0; $i < $strLen; $i++) {
            if ($this->sourceString[$i] == ' ') {
                $this->cAttributes->Count++;
                $tmpvalues                           = explode('.', $tmpvalue);
                $this->cAttributes->Items['tagname'] = ($this->charToLow ? strtolower($tmpvalues[0]) : $tmpvalues[0]);
                if (isset($tmpvalues[1]) && $tmpvalues[1] != '') {
                    $this->cAttributes->Items['name'] = $tmpvalues[1];
                }
                $tmpvalue     = '';
                $hasAttribute = true;
                break;
            } else {
                $tmpvalue .= $this->sourceString[$i];
            }
        }

        //不存在属性列表的情况
        if (!$hasAttribute) {
            $this->cAttributes->Count++;
            $tmpvalues                           = explode('.', $tmpvalue);
            $this->cAttributes->Items['tagname'] = ($this->charToLow ? strtolower($tmpvalues[0]) : $tmpvalues[0]);
            if (isset($tmpvalues[1]) && $tmpvalues[1] != '') {
                $this->cAttributes->Items['name'] = $tmpvalues[1];
            }

            return false;
        }
        $tmpvalue = '';
        //如果字符串含有属性值，遍历源字符串,并获得各属性
        for ($i; $i < $strLen; $i++) {
            $d = $this->sourceString[$i];
            //查找属性名称
            if ($startdd == -1) {
                if ($d != '=') {
                    $tmpatt .= $d;
                } else {
                    if ($this->charToLow) {
                        $tmpatt = strtolower(trim($tmpatt));
                    } else {
                        $tmpatt = trim($tmpatt);
                    }
                    $startdd = 0;
                }
            } //查找属性的限定标志
            else if ($startdd == 0) {
                switch ($d) {
                    case ' ':
                        break;
                    case '"':
                        $ddtag   = '"';
                        $startdd = 1;
                        break;
                    case '\'':
                        $ddtag   = '\'';
                        $startdd = 1;
                        break;
                    default:
                        $tmpvalue .= $d;
                        $ddtag    = ' ';
                        $startdd  = 1;
                        break;
                }
            } else if ($startdd == 1) {
                if ($d == $ddtag && (isset($this->sourceString[$i - 1]) && $this->sourceString[$i - 1] != "\\")) {
                    $this->cAttributes->Count++;
                    $this->cAttributes->Items[$tmpatt] = trim($tmpvalue);
                    $tmpatt                            = '';
                    $tmpvalue                          = '';
                    $startdd                           = -1;
                } else {
                    $tmpvalue .= $d;
                }
            }
        }
        //最后一个属性的给值
        if ($tmpatt != '') {
            $this->cAttributes->Count++;
            $this->cAttributes->Items[$tmpatt] = trim($tmpvalue);
        }
    }
}