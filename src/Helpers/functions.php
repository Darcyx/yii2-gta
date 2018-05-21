<?php
/**
 * 核心小助手
 * @version        $Id: util.helper.php 4 19:20 2010年7月6日Z tianya $
 * @package        DedeCMS.Helpers
 * @copyright      Copyright (c) 2007 - 2010, DesDev, Inc.
 * @license        http://help.dedecms.com/usersguide/license.html
 * @link           http://www.dedecms.com
 */

define('T_NEW_LINE', -1);

if (!function_exists('token_get_all_nl')) {
    function token_get_all_nl($source)
    {
        $new_tokens = array();

        // Get the tokens
        $tokens = token_get_all($source);

        // Split newlines into their own tokens
        foreach ($tokens as $token) {
            $token_name = is_array($token) ? $token[0] : null;
            $token_data = is_array($token) ? $token[1] : $token;

            // Do not split encapsed strings or multiline comments
            if ($token_name == T_CONSTANT_ENCAPSED_STRING || substr($token_data, 0, 2) == '/*') {
                $new_tokens[] = array($token_name, $token_data);
                continue;
            }

            // Split the data up by newlines
            $split_data = preg_split('#(\r\n|\n)#', $token_data, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

            foreach ($split_data as $data) {
                if ($data == "\r\n" || $data == "\n") {
                    // This is a new line token
                    $new_tokens[] = array(T_NEW_LINE, $data);
                } else {
                    // Add the token under the original token name
                    $new_tokens[] = is_array($token) ? array($token_name, $data) : $data;
                }
            }
        }

        return $new_tokens;
    }
}

if (!function_exists('token_name_nl')) {
    function token_name_nl($token)
    {
        if ($token === T_NEW_LINE) {
            return 'T_NEW_LINE';
        }

        return token_name($token);
    }
}

/**
 *  获得当前的脚本网址
 *
 * @return    string
 */
if (!function_exists('GetCurUrl')) {
    function GetCurUrl()
    {
        if (!empty($_SERVER["REQUEST_URI"])) {
            $scriptName = $_SERVER["REQUEST_URI"];
            $nowurl     = $scriptName;
        } else {
            $scriptName = $_SERVER["PHP_SELF"];
            if (empty($_SERVER["QUERY_STRING"])) {
                $nowurl = $scriptName;
            } else {
                $nowurl = $scriptName . "?" . $_SERVER["QUERY_STRING"];
            }
        }

        return $nowurl;
    }
}

/**
 *  获取用户真实地址
 *
 * @return    string  返回用户ip
 */
if (!function_exists('GetIP')) {
    function GetIP()
    {
        static $realip = null;
        if ($realip !== null) {
            return $realip;
        }
        if (isset($_SERVER)) {
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                /* 取X-Forwarded-For中第x个非unknown的有效IP字符? */
                foreach ($arr as $ip) {
                    $ip = trim($ip);
                    if ($ip != 'unknown') {
                        $realip = $ip;
                        break;
                    }
                }
            } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $realip = $_SERVER['HTTP_CLIENT_IP'];
            } else {
                if (isset($_SERVER['REMOTE_ADDR'])) {
                    $realip = $_SERVER['REMOTE_ADDR'];
                } else {
                    $realip = '0.0.0.0';
                }
            }
        } else {
            if (getenv('HTTP_X_FORWARDED_FOR')) {
                $realip = getenv('HTTP_X_FORWARDED_FOR');
            } elseif (getenv('HTTP_CLIENT_IP')) {
                $realip = getenv('HTTP_CLIENT_IP');
            } else {
                $realip = getenv('REMOTE_ADDR');
            }
        }
        preg_match("/[\d\.]{7,15}/", $realip, $onlineip);
        $realip = !empty($onlineip[0]) ? $onlineip[0] : '0.0.0.0';

        return $realip;
    }
}

/**
 *  生成一个随机字符
 *
 * @access    public
 * @param     string $ddnum
 * @return    string
 */
if (!function_exists('dd2char')) {
    function dd2char($ddnum)
    {
        $ddnum = strval($ddnum);
        $slen  = strlen($ddnum);
        $okdd  = '';
        $nn    = '';
        for ($i = 0; $i < $slen; $i++) {
            if (isset($ddnum[$i + 1])) {
                $n = $ddnum[$i] . $ddnum[$i + 1];
                if (($n > 96 && $n < 123) || ($n > 64 && $n < 91)) {
                    $okdd .= chr($n);
                    $i++;
                } else {
                    $okdd .= $ddnum[$i];
                }
            } else {
                $okdd .= $ddnum[$i];
            }
        }

        return $okdd;
    }
}

/**
 *  json_encode兼容函数
 *
 * @access    public
 * @param     string $data
 * @return    string
 */
if (!function_exists('json_encode')) {
    function format_json_value(&$value)
    {
        if (is_bool($value)) {
            $value = $value ? 'TRUE' : 'FALSE';
        } else if (is_int($value)) {
            $value = intval($value);
        } else if (is_float($value)) {
            $value = floatval($value);
        } else if (defined($value) && $value === null) {
            $value = strval(constant($value));
        } else if (is_string($value)) {
            $value = '"' . addslashes($value) . '"';
        }

        return $value;
    }

    function json_encode($data)
    {
        if (is_object($data)) {
            //对象转换成数组
            $data = get_object_vars($data);
        } else if (!is_array($data)) {
            // 普通格式直接输出
            return format_json_value($data);
        }
        // 判断是否关联数组
        if (empty($data) || is_numeric(implode('', array_keys($data)))) {
            $assoc = false;
        } else {
            $assoc = true;
        }
        // 组装 Json字符串
        $json = $assoc ? '{' : '[';
        foreach ($data as $key => $val) {
            if (!is_NULL($val)) {
                if ($assoc) {
                    $json .= "\"$key\":" . json_encode($val) . ",";
                } else {
                    $json .= json_encode($val) . ",";
                }
            }
        }
        if (strlen($json) > 1) {// 加上判断 防止空数组
            $json = substr($json, 0, -1);
        }
        $json .= $assoc ? '}' : ']';

        return $json;
    }
}

/**
 *  json_decode兼容函数
 *
 * @access    public
 * @param     string $json  json数据
 * @param     string $assoc 当该参数为 TRUE 时，将返回 array 而非 object
 * @return    string
 */
if (!function_exists('json_decode')) {
    function json_decode($json, $assoc = false)
    {
        // 目前不支持二维数组或对象
        $begin = substr($json, 0, 1);
        if (!in_array($begin, array('{', '['))) // 不是对象或者数组直接返回
        {
            return $json;
        }
        $parse = substr($json, 1, -1);
        $data  = explode(',', $parse);
        if ($flag = $begin == '{') {
            // 转换成PHP对象
            $result = new stdClass();
            foreach ($data as $val) {
                $item         = explode(':', $val);
                $key          = substr($item[0], 1, -1);
                $result->$key = json_decode($item[1], $assoc);
            }
            if ($assoc) {
                $result = get_object_vars($result);
            }
        } else {
            // 转换成PHP数组
            $result = array();
            foreach ($data as $val) {
                $result[] = json_decode($val, $assoc);
            }
        }

        return $result;
    }
}

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
    $plusfile        = DEDEINC . '@app/tplLib/plus_' . $atts['tagname'] . '.php';
    $plusfile        = '@app/tplLib/tpl/plus_' . $atts['tagname'] . '.php';
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
