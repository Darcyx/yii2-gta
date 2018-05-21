<?php
/**
 * 动态模板引擎
 * @package        DedeCMS.Libraries
 * @license        http://help.dedecms.com/usersguide/license.html
 * @date           2018/5/16
 */

namespace yii\gta\tpl;

namespace yii\gta\tag\GtaAttribute;

/**
 * 属性解析器
 * function C__TagAttributeParse();
 */
class TagAttributeParse
{
    public $sourceString = "";
    public $sourceMaxSize = 1024;
    public $cAttributes = array();
    public $charToLow = true;


    function SetSource($str = "")
    {
        $this->cAttributes  = new GtaAttribute();
        $this->sourceString = trim(preg_replace("/[ \r\n\t\f]{1,}/", " ", $str));
        $strLen             = strlen($this->sourceString);
        if ($strLen > 0 && $strLen <= $this->sourceMaxSize) {
            $this->ParseAttribute();
        }
    }

    /**
     *  解析属性
     *
     * @access    public
     * @return    void
     */
    function ParseAttribute()
    {
        $d                        = '';
        $tmpatt                   = '';
        $tmpvalue                 = '';
        $startdd                  = -1;
        $ddtag                    = '';
        $hasAttribute             = false;
        $strLen                   = strlen($this->sourceString);
        $this->cAttributes->items = array();

        // 获得Tag的名称，解析到 cAtt->GetAtt('tagname') 中
        for ($i = 0; $i < $strLen; $i++) {
            if ($this->sourceString[$i] == ' ') {
                $this->cAttributes->count++;
                $tmpvalues                           = explode('.', $tmpvalue);
                $this->cAttributes->items['tagname'] = ($this->charToLow ? strtolower($tmpvalues[0]) : $tmpvalues[0]);
                if (isset($tmpvalues[2])) {
                    $okname = $tmpvalues[1];
                    for ($j = 2; isset($tmpvalues[$j]); $j++) {
                        $okname .= "['" . $tmpvalues[$j] . "']";
                    }
                    $this->cAttributes->items['name'] = $okname;
                } else if (isset($tmpvalues[1]) && $tmpvalues[1] != '') {
                    $this->cAttributes->items['name'] = $tmpvalues[1];
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
            $this->cAttributes->count++;
            $tmpvalues                           = explode('.', $tmpvalue);
            $this->cAttributes->items['tagname'] = ($this->charToLow ? strtolower($tmpvalues[0]) : $tmpvalues[0]);
            if (isset($tmpvalues[2])) {
                $okname = $tmpvalues[1];
                for ($i = 2; isset($tmpvalues[$i]); $i++) {
                    $okname .= "['" . $tmpvalues[$i] . "']";
                }
                $this->cAttributes->items['name'] = $okname;
            } else if (isset($tmpvalues[1]) && $tmpvalues[1] != '') {
                $this->cAttributes->items['name'] = $tmpvalues[1];
            }

            return;
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
                    case '\'':
                        $ddtag   = '\'';
                        $startdd = 1;
                        break;
                    case '"':
                        $ddtag   = '"';
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
                    $this->cAttributes->count++;
                    $this->cAttributes->items[$tmpatt] = trim($tmpvalue);
                    $tmpatt                            = '';
                    $tmpvalue                          = '';
                    $startdd                           = -1;
                } else {
                    $tmpvalue .= $d;
                }
            }
        }//for

        //最后一个属性的给值
        if ($tmpatt != '') {
            $this->cAttributes->count++;
            $this->cAttributes->items[$tmpatt] = trim($tmpvalue);
        }//print_r($this->cAttributes->items);

    }// end func

}//End Class