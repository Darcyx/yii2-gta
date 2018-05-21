<?php
/**
 *
 *
 * @version        $Id: dedetemplate.class.php 3 15:44 2010年7月6日Z tianya $
 * @package        DedeCMS.Libraries
 * @copyright      Copyright (c) 2007 - 2010, DesDev, Inc.
 * @license        http://help.dedecms.com/usersguide/license.html
 * @link           http://www.dedecms.com
 */

/**
 *  这个函数用于定义任意名称的块使用的接口
 *  返回值应是一个二维数组
 *  块调用对应的文件为 include/taglib/plus_blockname.php
 *  ----------------------------------------------------------------
 *  由于标记一般存在默认属性，在编写块函数时，应该在块函数中进行给属性赋省缺值处理，如：
 *  $attlist = "titlelen=30,catalogid=0,modelid=0,flag=,addon=,row=8,ids=,orderby=id,orderway=desc,limit=,subday=0";
 *  给属性赋省缺值
 *  FillAtts($atts,$attlist);
 *  处理属性中使用的系统变量 var、global、field 类型(不支持多维数组)
 *  FillFields($atts,$fields,$refObj);
 *
 * @access    public
 * @param     array  $atts   属性
 * @param     object $refObj 所属对象
 * @param     array  $fields 字段
 * @return    string
 */
function MakePublicTag($atts = array(), $refObj = '', $fields = array())
{
    $atts['tagname'] = preg_replace("/[0-9]{1,}$/", "", $atts['tagname']);
    $plusfile        = DEDEINC . '/tpllib/plus_' . $atts['tagname'] . '.php';
    if (!file_exists($plusfile)) {
        if (isset($atts['rstype']) && $atts['rstype'] == 'string') {
            return '';
        } else {
            return array();
        }
    } else {
        include_once($plusfile);
        $func = 'plus_' . $atts['tagname'];

        return $func($atts, $refObj, $fields);
    }
}

/**
 *  设定属性的默认值
 *
 * @access    public
 * @param     array $atts    属性
 * @param     array $attlist 属性列表
 * @return    void
 */
function FillAtts(&$atts, $attlist)
{
    $attlists = explode(',', $attlist);
    foreach ($attlists as $att) {
        list($k, $v) = explode('=', $att);
        if (!isset($atts[$k])) {
            $atts[$k] = $v;
        }
    }
}

/**
 *  把上级的fields传递给atts
 *
 * @access    public
 * @param     array  $atts   属性
 * @param     object $refObj 所属对象
 * @param     array  $fields 字段
 * @return    string
 */
function FillFields(&$atts, &$refObj, &$fields)
{
    global $_vars;
    foreach ($atts as $k => $v) {
        if (preg_match('/^field\./i', $v)) {
            $key = preg_replace('/^field\./i', '', $v);
            if (isset($fields[$key])) {
                $atts[$k] = $fields[$key];
            }
        } else if (preg_match('/^var\./i', $v)) {
            $key = preg_replace('/^var\./i', '', $v);
            if (isset($_vars[$key])) {
                $atts[$k] = $_vars[$key];
            }
        } else if (preg_match('/^global\./i', $v)) {
            $key = preg_replace('/^global\./i', '', $v);
            if (isset($GLOBALS[$key])) {
                $atts[$k] = $GLOBALS[$key];
            }
        }
    }
}


/**
 *  私有标签编译,主要用于if标签内的字符串解析
 *
 * @access    public
 * @param     string $str 需要编译的字符串
 * @return    string
 */
function private_rt($str)
{
    if (is_array($str)) {
        $arr = explode('.', $str[0]);
    } else {
        $arr = explode('.', $str);
    }

    $rs = '$GLOBALS[\'';
    if ($arr[0] == 'cfg') {
        return $rs . 'cfg_' . $arr[1] . "']";
    } elseif ($arr[0] == 'var') {
        $arr[0] = '_vars';
        $rs     .= implode('\'][\'', $arr);
        $rs     .= "']";

        return $rs;
    } elseif ($arr[0] == 'global') {
        unset($arr[0]);
        $rs .= implode('\'][\'', $arr);
        $rs .= "']";

        return $rs;
    } else {
        if ($arr[0] == 'field') {
            $arr[0] = 'fields';
        }
        $rs = '$' . $arr[0] . "['";
        unset($arr[0]);
        $rs .= implode('\'][\'', $arr);
        $rs .= "']";

        return $rs;
    }
}
