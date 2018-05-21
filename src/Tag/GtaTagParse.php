<?php
/**
 * 静态模板引擎
 * GtaTagParse Gta模板解析类
 * @package          DedeCMS.Libraries
 * @license          http://help.dedecms.com/usersguide/license.html
 * @version          $Id: dedetag.class.php 1 10:33 2010年7月6日Z tianya $
 * @date             2018/5/16
 */

namespace yii\gta\tag;

use Yii;

/**
 * Class GtaTagParse
 * @package GtaTpl\GtaTag
 */
class GtaTagParse
{
    /**
     * @var string 标记的名字空间
     */
    public $NameSpace = 'gta';

    /**
     * @var string 标记起始
     */
    public $TagStartWord = '{';

    /**
     * @var string 标记结束
     */
    public $TagEndWord = '}';

    /**
     * @var int 标记名称的最大值
     */
    public $TagMaxLen = 64;

    /**
     * @var bool TRUE表示对属性和标记名称不区分大小写
     */
    public $CharToLow = true;

    /**
     * @var bool 是否使用缓冲
     */
    public $IsCache = false;

    /**
     * @var int 缓存创建时间
     */
    public $TempMkTime = 0;

    /**
     * @var string
     */
    public $CacheFile = '';

    /**
     * @var string 模板字符串
     */
    public $SourceString = '';

    /**
     * @var array 标记集合
     */
    public $CTags = array();

    /**
     * @var int $Tags标记个数
     */
    public $Count = -1;

    /**
     * @var string 引用当前模板类的对象
     */
    public $refObj = '';

    /**
     * @var string
     */
    public $tagHashFile = '';

    public $cache = 'Y';

    public $cache_dir = '';

    /**
     * GtaTagParse constructor.
     */
    public function __construct()
    {
        if (Yii::$app->params['tpl']['cache'] == 'Y') {
            $this->IsCache = true;
        } else {
            $this->IsCache = false;
        }
        $this->NameSpace    = 'gta';
        $this->TagStartWord = '{';
        $this->TagEndWord   = '}';
        $this->TagMaxLen    = 64;
        $this->CharToLow    = true;
        $this->SourceString = '';
        $this->CTags        = array();
        $this->Count        = -1;
        $this->TempMkTime   = 0;
        $this->CacheFile    = '';
        $this->cache_dir    = '@runtime' . Yii::$app->params['tpl']['cache_dir'];
    }

    /**
     *  设置标记的命名空间，默认为Gta
     * @access    public
     * @param     string $str 字符串
     * @param     string $s   开始标记
     * @param     string $e   结束标记
     *
     * @return    void
     */
    public function SetNameSpace($str, $s = "{", $e = "}")
    {
        $this->NameSpace    = strtolower($str);
        $this->TagStartWord = $s;
        $this->TagEndWord   = $e;
    }

    /**
     * 清除默认设置
     */
    public function Clear()
    {
        $this->SetDefault();
    }

    /**
     * 重置成员变量或Clear
     * @access    public
     * @return    void
     */
    public function SetDefault()
    {
        $this->SourceString = '';
        $this->CTags        = array();
        $this->Count        = -1;
    }

    /**
     *  强制引用
     * @param     object $refObj 隶属对象
     * @return    void
     */
    public function SetRefObj(&$refObj)
    {
        $this->refObj = $refObj;
    }

    /**
     * 获取
     * @return int
     */
    public function GetCount()
    {
        return $this->Count + 1;
    }

    /**
     * 检查是否存在禁止的函数
     * @param        $str
     * @param string $errmsg
     * @return bool
     */
    public function CheckDisabledFunctions($str, &$errmsg = '')
    {
        $disFunc = Yii::$app->params['tpl']['disable_func'];
        $disFunc = isset($disFunc) ? $disFunc : 'phpinfo,eval,exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source,file_put_contents,fsockopen,fopen,fwrite';
        // 模板引擎增加disable_functions
        if (defined('GTADISFUN')) {
            $tokens             = token_get_all_nl('<?php' . $str . "\n\r?>");
            $disabled_functions = explode(',', $disFunc);
            foreach ($tokens as $token) {
                if (is_array($token)) {
                    if ($token[0] = '306' && in_array($token[1], $disabled_functions)) {
                        $errmsg = 'GtaCMS Error:function disabled "' . $token[1];

                        return false;
                    }
                }
            }
        }

        return true;
    }

    //===========================载入解析===========================

    /**
     *  载入模板文件
     * @param     string $filename 文件名称
     * @return    string
     */
    public function LoadTemplate($filename)
    {
        $this->SetDefault();
        if (!file_exists($filename)) {
            $this->SourceString = " $filename Not Found! ";
            $this->ParseTemplate();
        } else {
            $fp = @fopen($filename, "r");
            while ($line = fgets($fp, 1024)) {
                $this->SourceString .= $line;
            }
            fclose($fp);
            if ($this->LoadCache($filename)) {
                return '';
            } else {
                $this->ParseTemplate();
            }
        }
    }

    /**
     *  解析模板
     * @return    string
     */
    public function ParseTemplate()
    {
        $TagStartWord     = $this->TagStartWord;
        $TagEndWord       = $this->TagEndWord;
        $sPos             = 0;
        $ePos             = 0;
        $FullTagStartWord = $TagStartWord . $this->NameSpace . ":";
        $sTagEndWord      = $TagStartWord . "/" . $this->NameSpace . ":";
        $eTagEndWord      = "/" . $TagEndWord;
        $tsLen            = strlen($FullTagStartWord);
        $sourceLen        = strlen($this->SourceString);

        if ($sourceLen <= ($tsLen + 3)) {
            return;
        }
        $cAtt            = new GtaAttributeParse();
        $cAtt->charToLow = $this->CharToLow;
        //遍历模板字符串，请取标记及其属性信息
        for ($i = 0; $i < $sourceLen; $i++) {
            $tTagName = '';
            //如果不进行此判断，将无法识别相连的两个标记
            if ($i - 1 >= 0) {
                $ss = $i - 1;
            } else {
                $ss = 0;
            }
            $sPos  = strpos($this->SourceString, $FullTagStartWord, $ss);
            $isTag = $sPos;
            if ($i == 0) {
                $headerTag = substr($this->SourceString, 0, strlen($FullTagStartWord));
                if ($headerTag == $FullTagStartWord) {
                    $isTag = true;
                    $sPos  = 0;
                }
            }
            if ($isTag === false) {
                break;
            }
            //判断是否已经到倒数第三个字符(可能性几率极小，取消此逻辑)
            /*
                if($sPos > ($sourceLen-$tsLen-3) )
                {
                    break;
                }
*/
            for ($j = ($sPos + $tsLen); $j < ($sPos + $tsLen + $this->TagMaxLen); $j++) {
                if ($j > ($sourceLen - 1)) {
                    break;
                } else if (preg_match("/[\/ \t\r\n]/", $this->SourceString[$j]) || $this->SourceString[$j] == $this->TagEndWord) {
                    break;
                } else {
                    $tTagName .= $this->SourceString[$j];
                }
            }
            if ($tTagName != '') {
                $i                  = $sPos + $tsLen;
                $endPos             = -1;
                $fullTagEndWordThis = $sTagEndWord . $tTagName . $TagEndWord;
                $e1                 = strpos($this->SourceString, $eTagEndWord, $i);
                $e2                 = strpos($this->SourceString, $FullTagStartWord, $i);
                $e3                 = strpos($this->SourceString, $fullTagEndWordThis, $i);
                //$eTagEndWord = /} $FullTagStartWord = {tag: $fullTagEndWordThis = {/tag:xxx]
                $e1 = trim($e1);
                $e2 = trim($e2);
                $e3 = trim($e3);
                $e1 = ($e1 == '' ? '-1' : $e1);
                $e2 = ($e2 == '' ? '-1' : $e2);
                $e3 = ($e3 == '' ? '-1' : $e3);
                //not found '{/tag:'
                if ($e3 == -1) {
                    $endPos = $e1;
                    $elen   = $endPos + strlen($eTagEndWord);
                } //not found '/}'
                else if ($e1 == -1) {
                    $endPos = $e3;
                    $elen   = $endPos + strlen($fullTagEndWordThis);
                } //found '/}' and found '{/gta:'
                else {
                    //if '/}' more near '{Gta:'、'{/Gta:' , end tag is '/}', else is '{/gta:'
                    if ($e1 < $e2 && $e1 < $e3) {
                        $endPos = $e1;
                        $elen   = $endPos + strlen($eTagEndWord);
                    } else {
                        $endPos = $e3;
                        $elen   = $endPos + strlen($fullTagEndWordThis);
                    }
                }
                //not found end tag , error
                if ($endPos == -1) {
                    echo "Tag Character postion $sPos, '$tTagName' Error！<br />\r\n";
                    break;
                }
                $i    = $elen;
                $ePos = $endPos;

                //分析所找到的标记位置等信息
                $attStr     = '';
                $innerText  = '';
                $startInner = 0;
                for ($j = ($sPos + $tsLen); $j < $ePos; $j++) {
                    if ($startInner == 0 && ($this->SourceString[$j] == $TagEndWord && $this->SourceString[$j - 1] != "\\")) {
                        $startInner = 1;
                        continue;
                    }
                    if ($startInner == 0) {
                        $attStr .= $this->SourceString[$j];
                    } else {
                        $innerText .= $this->SourceString[$j];
                    }
                }
                $cAtt->SetSource($attStr);
                if ($cAtt->cAttributes->GetTagName() != '') {
                    $this->Count++;
                    $CDTag                     = new GtaTag();
                    $CDTag->TagName            = $cAtt->cAttributes->GetTagName();
                    $CDTag->StartPos           = $sPos;
                    $CDTag->EndPos             = $i;
                    $CDTag->CAttribute         = $cAtt->cAttributes;
                    $CDTag->IsReplace          = false;
                    $CDTag->TagID              = $this->Count;
                    $CDTag->InnerText          = $innerText;
                    $this->CTags[$this->Count] = $CDTag;
                }
            } else {
                $i = $sPos + $tsLen;
                break;
            }
        }
        //结束遍历模板字符串

        if ($this->IsCache) {
            $this->SaveCache();
        }
    }

    /**
     * 检测模板缓存
     * @param     string $filename 文件名称
     * @return    string
     */
    public function LoadCache($filename)
    {
        if (!$this->IsCache) {
            return false;
        }
        $cdir             = dirname($filename);
        $ckfile           = str_replace($cdir, '', $filename) . substr(md5($filename), 0, 16) . '.inc';
        $ckfullfile       = $this->cache_dir . $ckfile;
        $ckfullfile_t     = $this->cache_dir . $ckfile . '.txt';
        $this->CacheFile  = $ckfullfile;
        $this->TempMkTime = filemtime($filename);
        if (!file_exists($ckfullfile) || !file_exists($ckfullfile_t)) {
            return false;
        }

        //检测模板最后更新时间
        $fp        = fopen($ckfullfile_t, 'r');
        $time_info = trim(fgets($fp, 64));
        fclose($fp);
        if ($time_info != $this->TempMkTime) {
            return false;
        }
        //引入缓冲数组
        include $this->CacheFile;
        //把缓冲数组内容读入类
        if (isset($z) && is_array($z)) {
            foreach ($z as $k => $v) {
                $this->Count++;
                $ctag             = new GtaTag();
                $ctag->CAttribute = new GtaAttribute();
                $ctag->IsReplace  = false;
                $ctag->TagName    = $v[0];
                $ctag->InnerText  = $v[1];
                $ctag->StartPos   = $v[2];
                $ctag->EndPos     = $v[3];
                $ctag->TagValue   = '';
                $ctag->TagID      = $k;
                if (isset($v[4]) && is_array($v[4])) {
                    $ctag->CAttribute->Items = array();
                    foreach ($v[4] as $k => $v) {
                        $ctag->CAttribute->Count++;
                        $ctag->CAttribute->Items[$k] = $v;
                    }
                }
                $this->CTags[$this->Count] = $ctag;
            }
        } else {
            //模板没有缓冲数组
            $this->CTags = '';
            $this->Count = -1;
        }

        return true;
    }

    /**
     *  写入缓存
     * @return    string
     */
    public function SaveCache()
    {
        $_dirpath = dirname($this->CacheFile);
        while (!is_dir($_dirpath)) {
            if (@mkdir($_dirpath, 0755, true)) {
                break;
            }
        }
        $fp = fopen($this->CacheFile . '.txt', "w+");
        fwrite($fp, $this->TempMkTime . "\n");
        fclose($fp);
        $fp = fopen($this->CacheFile, "w");
        flock($fp, 3);
        fwrite($fp, '<' . '?php' . "\r\n");
        $err_msg = '';
        if (is_array($this->CTags)) {
            foreach ($this->CTags as $tid => $ctag) {
                $arrayValue = 'Array("' . $ctag->TagName . '",';
                if (!$this->CheckDisabledFunctions($ctag->InnerText, $err_msg)) {
                    fclose($fp);
                    @unlink($this->tagHashFile);
                    @unlink($this->CacheFile);
                    @unlink($this->CacheFile . '.txt');
                    die($err_msg);
                }
                $arrayValue .= '"' . str_replace('$', '\$', str_replace("\r", "\\r", str_replace("\n", "\\n", str_replace('"', '\"', str_replace("\\", "\\\\", $ctag->InnerText))))) . '"';
                $arrayValue .= ",{$ctag->StartPos},{$ctag->EndPos});";
                fwrite($fp, "\$z[$tid]={$arrayValue}\n");
                if (is_array($ctag->CAttribute->Items)) {
                    fwrite($fp, "\$z[$tid][4]=array();\n");
                    foreach ($ctag->CAttribute->Items as $k => $v) {
                        $v = str_replace("\\", "\\\\", $v);
                        $v = str_replace('"', "\\" . '"', $v);
                        $v = str_replace('$', '\$', $v);
                        $k = trim(str_replace("'", "", $k));
                        if ($k == "") {
                            continue;
                        }
                        if ($k != 'tagname') {
                            fwrite($fp, "\$z[$tid][4]['$k']=\"$v\";\n");
                        }
                    }
                }
            }
        }
        fwrite($fp, "\n" . '?' . '>');
        fclose($fp);
    }

    /**
     *  载入模板字符串
     * @param     string $str 字符串
     * @return    void
     */
    public function LoadSource($str)
    {
        $filename          = $this->cache_dir . '/' . md5($str) . '.inc';
        $this->tagHashFile = $filename;
        if (!is_file($filename)) {
            file_put_contents($filename, $str);
        }
        $this->LoadTemplate($filename);
    }

    /**
     * @param $str
     */
    public function LoadString($str)
    {
        $this->LoadSource($str);
    }

    //===========================获取Tag===========================

    /**
     * 获得指定名称的Tag的ID(如果有多个同名的Tag,则取没有被取代为内容的第一个Tag)
     * @param     string $str 字符串
     * @return    int
     */
    public function GetTagID($str)
    {
        if ($this->Count == -1) {
            return -1;
        }
        if ($this->CharToLow) {
            $str = strtolower($str);
        }
        foreach ($this->CTags as $id => $CTag) {
            if ($CTag->TagName == $str && !$CTag->IsReplace) {
                return $id;
                break;
            }
        }

        return -1;
    }

    /**
     * 获得指定名称的CTag数据类(如果有多个同名的Tag,则取没有被分配内容的第一个Tag)
     * @param     string $str 字符串
     * @return    string
     */
    public function GetTag($str)
    {
        if ($this->Count == -1) {
            return '';
        }
        if ($this->CharToLow) {
            $str = strtolower($str);
        }
        foreach ($this->CTags as $id => $CTag) {
            if ($CTag->TagName == $str && !$CTag->IsReplace) {
                return $CTag;
                break;
            }
        }

        return '';
    }

    /**
     * 通过名称获取标记
     * @param     string $str 字符串
     * @return    string
     */
    public function GetTagByName($str)
    {
        return $this->GetTag($str);
    }

    /**
     * 获得指定ID的CTag数据类
     * @param     string  标签id
     * @return    string
     */
    public function GetTagByID($id)
    {
        if (isset($this->CTags[$id])) {
            return $this->CTags[$id];
        } else {
            return '';
        }
    }

    //===========================保存===========================

    /**
     *  把分析模板输出到一个字符串中,并返回
     *
     * @access    public
     * @return    string
     */
    public function GetResult()
    {
        $ResultString = '';
        if ($this->Count == -1) {
            return $this->SourceString;
        }
        $this->AssignSysTag();
        $nextTagEnd = 0;
        $strok      = "";
        for ($i = 0; $i <= $this->Count; $i++) {
            $ResultString .= substr($this->SourceString, $nextTagEnd, $this->CTags[$i]->StartPos - $nextTagEnd);
            $ResultString .= $this->CTags[$i]->GetValue();
            $nextTagEnd   = $this->CTags[$i]->EndPos;
        }
        $slen = strlen($this->SourceString);
        if ($slen > $nextTagEnd) {
            $ResultString .= substr($this->SourceString, $nextTagEnd, $slen - $nextTagEnd);
        }

        return $ResultString;
    }

    /**
     * 把分析模板输出到一个字符串中
     * 不替换没被处理的值
     *
     * @access    public
     * @return    string
     */
    public function GetResultNP()
    {
        $ResultString = '';
        if ($this->Count == -1) {
            return $this->SourceString;
        }
        $this->AssignSysTag();
        $nextTagEnd = 0;
        $strok      = "";
        for ($i = 0; $i <= $this->Count; $i++) {
            if ($this->CTags[$i]->GetValue() != "") {
                if ($this->CTags[$i]->GetValue() == '#@Delete@#') {
                    $this->CTags[$i]->TagValue = "";
                }
                $ResultString .= substr($this->SourceString, $nextTagEnd, $this->CTags[$i]->StartPos - $nextTagEnd);
                $ResultString .= $this->CTags[$i]->GetValue();
                $nextTagEnd   = $this->CTags[$i]->EndPos;
            }
        }
        $slen = strlen($this->SourceString);
        if ($slen > $nextTagEnd) {
            $ResultString .= substr($this->SourceString, $nextTagEnd, $slen - $nextTagEnd);
        }

        return $ResultString;
    }

    /**
     * 直接输出解析模板
     * @return    void
     */
    public function Display()
    {
        echo $this->GetResult();
    }

    /**
     *  把解析模板输出为文件
     * @param     string $filename 要保存到的文件
     * @return    string
     */
    public function SaveTo($filename)
    {
        $fp = @fopen($filename, "w") or die("GtaTag Engine Create File False");
        fwrite($fp, $this->GetResult());
        fclose($fp);
    }

    //===========================赋值===========================

    /**
     *  给_vars数组传递一个元素
     * @param     string $vName  标签名
     * @param     string $vValue 标签值
     * @return    string
     */
    public function AssignVar($vName, $vValue)
    {
        if (!isset($_sys_globals['define'])) {
            $_sys_globals['define'] = 'yes';
        }
        $_sys_globals[$vName] = $vValue;
    }

    /**
     * 分配指定ID的标记的值
     * @param     string $i       标签id
     * @param     string $str     字符串
     * @param     bool   $runFunc 运行函数
     * @return    void
     */
    public function Assign($i, $str, $runFunc = true)
    {
        if (isset($this->CTags[$i])) {
            $this->CTags[$i]->IsReplace = true;
            $this->CTags[$i]->TagValue  = $str;
            if ($this->CTags[$i]->GetAtt('function') != '' && $runFunc) {
                $this->CTags[$i]->TagValue = $this->EvalFunc($str, $this->CTags[$i]->GetAtt('function'), $this->CTags[$i]);
            }
        }
    }

    /**
     * 分配指定名称的标记的值，如果标记包含属性，请不要用此函数
     * @param     string $tagName 标签名称
     * @param     string $str     字符串
     * @return    void
     */
    public function AssignName($tagName, $str)
    {
        foreach ($this->CTags as $id => $CTag) {
            if ($CTag->TagName == $tagName) {
                $this->Assign($id, $str);
            }
        }
    }

    /**
     *  处理特殊标记
     *
     * @access    public
     * @return    void
     */
    public function AssignSysTag()
    {
        //global $_sys_globals;
        for ($i = 0; $i <= $this->Count; $i++) {
            $CTag = $this->CTags[$i];
            $str  = '';
            //获取一个外部变量
            if ($CTag->TagName == 'global') {
                $str = $this->GetGlobals($CTag->GetAtt('name'));
                if ($this->CTags[$i]->GetAtt('function') != '') {
                    //$str = $this->EvalFunc( $this->CTags[$i]->TagValue, $this->CTags[$i]->GetAtt('function'),$this->CTags[$i] );
                    $str = $this->EvalFunc($str, $this->CTags[$i]->GetAtt('function'), $this->CTags[$i]);
                }
                $this->CTags[$i]->IsReplace = true;
                $this->CTags[$i]->TagValue  = $str;
            } //引入静态文件
            else if ($CTag->TagName == 'include') {
                $filename                   = ($CTag->GetAtt('file') == '' ? $CTag->GetAtt('filename') : $CTag->GetAtt('file'));
                $str                        = $this->IncludeFile($filename, $CTag->GetAtt('ismake'));
                $this->CTags[$i]->IsReplace = true;
                $this->CTags[$i]->TagValue  = $str;
            } //循环一个普通数组
            else if ($CTag->TagName == 'foreach') {
                $arr = $this->CTags[$i]->GetAtt('array');
                if (isset($GLOBALS[$arr])) {
                    foreach ($GLOBALS[$arr] as $k => $v) {
                        $istr = '';
                        $istr .= preg_replace("/\[field:key([\r\n\t\f ]+)\/\]/is", $k, $this->CTags[$i]->InnerText);
                        $str  .= preg_replace("/\[field:value([\r\n\t\f ]+)\/\]/is", $v, $istr);
                    }
                }
                $this->CTags[$i]->IsReplace = true;
                $this->CTags[$i]->TagValue  = $str;
            } //设置/获取变量值
            else if ($CTag->TagName == 'var') {
                $vname = $this->CTags[$i]->GetAtt('name');
                if ($vname == '') {
                    $str = '';
                } else if ($this->CTags[$i]->GetAtt('value') != '') {
                    $_vars[$vname] = $this->CTags[$i]->GetAtt('value');
                } else {
                    $str = (isset($_vars[$vname]) ? $_vars[$vname] : '');
                }
                $this->CTags[$i]->IsReplace = true;
                $this->CTags[$i]->TagValue  = $str;
            }

            //运行PHP接口
            if ($CTag->GetAtt('runphp') == 'yes') {
                $this->RunPHP($CTag, $i);
            }
            if (is_array($this->CTags[$i]->TagValue)) {
                $this->CTags[$i]->TagValue = 'array';
            }
        }
    }

    /**
     * 获得一个外部变量
     * @param     string $varName 变量名称
     * @return    string
     */
    public function GetGlobals($varName)
    {
        $varName = trim($varName);
        //禁止在模板文件读取数据库密码
        if ($varName == "dbuserpwd" || $varName == "cfg_dbpwd") {
            return "";
        }
        //正常情况
        if (isset($GLOBALS[$varName])) {
            return $GLOBALS[$varName];
        } else {
            return "";
        }
    }

    /**
     *  引入文件
     * @param     string $fileName 文件名
     * @param     string $isMake   是否需要编译
     * @return    string
     */
    public function IncludeFile($fileName, $isMake = 'no')
    {
        $cfg_df_style = Yii::$app->params['tpl']['df_style'];
        $restr        = '';
        if ($fileName == '') {
            return '';
        }
        if (file_exists('@templates/' . $fileName)) {
            $okfile = "@templates/" . $fileName;
        } else if (file_exists('@templates/' . $cfg_df_style . '/' . $fileName)) {
            $okfile = '@templates/' . $cfg_df_style . '/' . $fileName;
        } else {
            return "无法在这个位置找到： $fileName";
        }

        //编译
        if ($isMake != "no") {
            $dtp = new GtaTagParse();
            $dtp->LoadTemplate($okfile);
            $this->MakeOneTag($dtp, $this->refObj);
            $restr = $dtp->GetResult();
        } else {
            $fp = @fopen($okfile, "r");
            while ($line = fgets($fp, 1024)) {
                $restr .= $line;
            }
            fclose($fp);
        }

        return $restr;
    }

    /**
     * 运行PHP代码
     * @param $refObj
     * @param $i
     */
    public function RunPHP(&$refObj, $i)
    {
        $GtaMeValue = '';
        if ($refObj->GetAtt('source') == 'value') {
            $phpCode = $this->CTags[$i]->TagValue;
        } else {
            $GtaMeValue = $this->CTags[$i]->TagValue;
            $phpCode    = $refObj->GetInnerText();
        }
        $phpCode = preg_replace("/'@me'|\"@me\"|@me/i", '$GtaMeValue', $phpCode);
        @eval($phpCode); //or die("<xmp>$phpCode</xmp>");
        $this->CTags[$i]->TagValue  = $GtaMeValue;
        $this->CTags[$i]->IsReplace = true;
    }

    /**
     *  处理某字段的函数
     *
     * @access    public
     *
     * @param     string $field_value 字段值
     * @param     string $funcName    函数名称
     * @param     object $refObj      隶属对象
     *
     * @return    string
     */
    public function EvalFunc($field_value, $funcName, &$refObj)
    {
        $GtaFieldValue = $field_value;
        $funcName      = str_replace("{\"", "[\"", $funcName);
        $funcName      = str_replace("\"}", "\"]", $funcName);
        $funcName      = preg_replace("/'@me'|\"@me\"|@me/i", '$GtaFieldValue', $funcName);
        $funcName      = "\$GtaFieldValue = " . $funcName;
        @eval($funcName . ";"); //or die("<xmp>$funcName</xmp>");
        if (empty($GtaFieldValue)) {
            return '';
        } else {
            return $GtaFieldValue;
        }
    }

    public function MakeOneTag(&$dtp, &$refObj, $parfield = 'Y')
    {
        $disable_tags = Yii::$app->params['tpl']['disable_tags'];
        $disable_tags = isset($disable_tags) ? $disable_tags : 'php';
        $disable_tags = explode(',', $disable_tags);

        $alltags = array();
        $dtp->setRefObj($refObj);
        //读取自由调用tag列表
        $dh = dir('@tplLib/tag');
        while ($filename = $dh->read()) {
            if (preg_match("/\.lib\./", $filename)) {
                $alltags[] = str_replace('.lib.php', '', $filename);
            }
        }
        $dh->Close();

        //遍历tag元素
        if (!is_array($dtp->CTags)) {
            return '';
        }
        foreach ($dtp->CTags as $tagid => $ctag) {
            $tagname = $ctag->GetName();
            if ($tagname == 'field' && $parfield == 'Y') {
                $vname = $ctag->GetAtt('name');
                if ($vname == 'array' && isset($refObj->Fields)) {
                    $dtp->Assign($tagid, $refObj->Fields);
                } else if (isset($refObj->Fields[$vname])) {
                    $dtp->Assign($tagid, $refObj->Fields[$vname]);
                } else if ($ctag->GetAtt('noteid') != '') {
                    if (isset($refObj->Fields[$vname . '_' . $ctag->GetAtt('noteid')])) {
                        $dtp->Assign($tagid, $refObj->Fields[$vname . '_' . $ctag->GetAtt('noteid')]);
                    }
                }
                continue;
            }

            //由于考虑兼容性，原来文章调用使用的标记别名统一保留，这些标记实际调用的解析文件为inc_arclist.php
            if (preg_match("/^(artlist|likeart|hotart|imglist|imginfolist|coolart|specart|autolist)$/", $tagname)) {
                $tagname = 'arclist';
            }
            if ($tagname == 'friendlink') {
                $tagname = 'flink';
            }
            if (in_array($tagname, $alltags)) {
                if (in_array($tagname, $disable_tags)) {
                    continue;
                }
                $filename = '@tplLib/tag/' . $tagname . '.lib.php';
                include_once $filename;
                $funcname = 'lib_' . $tagname;
                $dtp->Assign($tagid, $funcname($ctag, $refObj));
            }
        }
    }
}
