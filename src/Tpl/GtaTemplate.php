<?php
/**
 * 模板引擎文件
 * @package        DedeCMS.Libraries
 * @license        http://help.dedecms.com/usersguide/license.html
 * @date           2018/5/16
 */

namespace yii\gta\tpl;

use Yii;
use yii\gta\tag\GtaAttribute;
use yii\gta\tag\GtaTag;

/**
 * 模板解析器
 * function C__GtaTemplate
 * @package          GtaTemplate
 */
include_once '../config.php';

class GtaTemplate
{
    var $tagMaxLen = 64;
    var $charToLow = true;
    var $isCache = true;
    var $isParse = false;
    var $isCompiler = true;
    var $templateDir = '';
    var $tempMkTime = 0;
    var $cacheFile = '';
    var $configFile = '';
    var $buildFile = '';
    var $refDir = '';
    var $cacheDir = '';
    var $templateFile = '';
    var $sourceString = '';
    var $cTags = array();

    //var $definedVars = array();
    var $count = -1;
    var $loopNum = 0;
    var $refObj = '';
    var $makeLoop = 0;
    var $tagStartWord = '{gta:';
    var $fullTagEndWord = '{/gta:';
    var $sTagEndWord = '/}';
    var $tagEndWord = '}';
    var $tpCfgs = array();


    /**
     *  构造函数
     * @param     string $templatedir 模板目录
     * @param     string $refDir      所属目录
     * @return    void
     */
    public function __construct($templatedir = '', $refDir = '')
    {
        //模版目录
        if ($templatedir == '') {
            $this->templateDir = '@template';
        } else {
            $this->templateDir = $templatedir;
        }

        //模板include目录
        if ($refDir == '') {
            $tplDf = Yii::$app->params['tpl']['df_style'];
            if (isset($tplDf)) {
                $this->refDir = $this->templateDir . '/' . $tplDf . '/';
            } else {
                $this->refDir = $this->templateDir;
            }
        }
        $this->cacheDir = '@runtime' . Yii::$app->params['tpl']['cache_dir'];
    }

    /**
     *  设定本类自身实例的类引用和使用本类的类实例(如果在类中使用本模板引擎，后一参数一般为$this)
     *
     * @access    public
     * @param     object $refObj 实例对象
     * @return    string
     */
    public function SetObject(&$refObj)
    {
        $this->refObj = $refObj;
    }

    /**
     *  设定Var的键值对
     *
     * @param     string $k 键
     * @param     string $v 值
     * @return    string
     */
    public function SetVar($k, $v)
    {
        $GLOBALS['_vars'][$k] = $v;
    }

    /**
     *  设定Var的键值对
     * @param     string $k 键
     * @param     string $v 值
     * @return    string
     */
    public function Assign($k, $v)
    {
        $GLOBALS['_vars'][$k] = $v;
    }

    /**
     *  设定数组
     * @param     string $k 键
     * @param     string $v 值
     * @return    string
     */
    public function SetArray($k, $v)
    {
        $GLOBALS[$k] = $v;
    }

    /**
     *  设置标记风格
     *
     * @param     string $ts    标签开始标记
     * @param     string $ftend 标签结束标记
     * @param     string $stend 标签尾部结束标记
     * @param     string $tend  结束标记
     * @return    void
     */
    public function SetTagStyle($ts = '{gta:', $ftend = '{/gta:', $stend = '/}', $tend = '}')
    {
        $this->tagStartWord   = $ts;
        $this->fullTagEndWord = $ftend;
        $this->sTagEndWord    = $stend;
        $this->tagEndWord     = $tend;
    }

    /**
     *  获得模板设定的config值
     *
     * @param     string $k 键名
     * @return    string
     */
    public function GetConfig($k)
    {
        return (isset($this->tpCfgs[$k]) ? $this->tpCfgs[$k] : '');
    }

    /**
     *  设定模板文件
     *
     * @param     string $tmpfile 模板文件
     * @return    void
     */
    public function LoadTemplate($tmpfile)
    {
        if (!file_exists($tmpfile)) {
            echo " Template Not Found! ";
            exit();
        }
        $tmpfile            = preg_replace("/[\\/]{1,}/", "/", $tmpfile);
        $tmpfiles           = explode('/', $tmpfile);
        $tmpfileOnlyName    = preg_replace("/(.*)\//", "", $tmpfile);
        $this->templateFile = $tmpfile;
        $this->refDir       = '';
        for ($i = 0; $i < count($tmpfiles) - 1; $i++) {
            $this->refDir .= $tmpfiles[$i] . '/';
        }
        if (!is_dir($this->cacheDir)) {
            $this->cacheDir = $this->refDir;
        }
        if ($this->cacheDir != '') {
            $this->cacheDir = $this->cacheDir . '/';
        }
        if (isset($GLOBALS['_DEBUG_CACHE'])) {
            $this->cacheDir = $this->refDir;
        }
        $this->cacheFile  = $this->cacheDir . preg_replace("/\.(wml|html|htm|php)$/", "_" . $this->GetEncodeStr($tmpfile) . '.inc', $tmpfileOnlyName);
        $this->configFile = $this->cacheDir . preg_replace("/\.(wml|html|htm|php)$/", "_" . $this->GetEncodeStr($tmpfile) . '_config.inc', $tmpfileOnlyName);

        //不开启缓存、当缓存文件不存在、及模板为更新的文件的时候才载入模板并进行解析
        if ($this->isCache == false || !file_exists($this->cacheFile) || filemtime($this->templateFile) > filemtime($this->cacheFile)) {
            $fp                 = fopen($this->templateFile, 'r');
            $this->sourceString = fread($fp, filesize($this->templateFile));
            fclose($fp);
            $this->ParseTemplate();
        } else {
            //如果存在config文件，则载入此文件，该文件用于保存 $this->tpCfgs的内容，以供扩展用途
            //模板中用{tag:config name='' value=''/}来设定该值
            if (file_exists($this->configFile)) {
                include($this->configFile);
            }
        }
    }

    /**
     *  载入模板字符串
     *
     * @access    public
     * @param     string $str 模板字符串
     * @return    void
     */
    public function LoadString($str = '')
    {
        $this->sourceString = $str;
        $hashcode           = md5($this->sourceString);
        $this->cacheFile    = $this->cacheDir . "/string_" . $hashcode . ".inc";
        $this->configFile   = $this->cacheDir . "/string_" . $hashcode . "_config.inc";
        $this->ParseTemplate();
    }

    /**
     *  调用此函数include一个编译后的PHP文件，通常是在最后一个步骤才调用本文件
     *
     * @access    public
     * @return    string
     */
    public function CacheFile()
    {
        global $gtmpfile;
        $this->WriteCache();

        return $this->cacheFile;
    }

    /**
     *  显示内容，由于函数中会重新解压一次$GLOBALS变量，所以在动态页中，应该尽量少用本方法，
     *  取代之是直接在程序中 include $tpl->CacheFile()，不过include $tpl->CacheFile()这种方式不能在类或函数内使用
     *
     * @access    public
     * @param     string
     * @return    void
     */
    public function Display()
    {
        global $gtmpfile;
        extract($GLOBALS, EXTR_SKIP);
        $this->WriteCache();
        include $this->cacheFile;
    }

    /**
     *  保存运行后的程序为文件
     *
     * @access    public
     * @param     string $savefile 保存到的文件目录
     * @return    void
     */
    public function SaveTo($savefile)
    {
        extract($GLOBALS, EXTR_SKIP);
        $this->WriteCache();
        ob_start();
        include $this->cacheFile;
        $okstr = ob_get_contents();
        ob_end_clean();
        $fp = @fopen($savefile, "w") or die(" Tag Engine Create File FALSE! ");
        fwrite($fp, $okstr);
        fclose($fp);
    }

    // ------------------------------------------------------------------------

    /**
     * CheckDisabledFunctions
     *
     * COMMENT : CheckDisabledFunctions : 检查是否存在禁止的函数
     *
     * @access    public
     * @param    string
     * @return    bool
     */
    public function CheckDisabledFunctions($str, &$errmsg = '')
    {
        $disFunc = Yii::$app->params['tpl']['disable_func'];
        $disFunc = isset($disFunc) ? $disFunc : 'phpinfo,eval,exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source,file_put_contents,fsockopen,fopen,fwrite';
        // 模板引擎增加disable_functions
        if (!defined('GTADISFUN')) {
            $tokens             = token_get_all_nl($str);
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

    /**
     *  解析模板并写缓存文件
     *
     * @access    public
     * @param     string $ctype 缓存类型
     * @return    void
     */
    public function WriteCache($ctype = 'all')
    {
        if (!file_exists($this->cacheFile) || $this->isCache == false || (file_exists($this->templateFile) && (filemtime($this->templateFile) > filemtime($this->cacheFile)))) {
            if (!$this->isParse) {
                $this->ParseTemplate();
            }
            $fp = fopen($this->cacheFile, 'w') or dir("Write Cache File Error! ");
            flock($fp, 3);
            $result = trim($this->GetResult());
            $errmsg = '';
            //var_dump($result);exit();
            if (!$this->CheckDisabledFunctions($result, $errmsg)) {
                fclose($fp);
                @unlink($this->cacheFile);
                die($errmsg);
            }
            fwrite($fp, $result);
            fclose($fp);
            if (count($this->tpCfgs) > 0) {
                $fp = fopen($this->configFile, 'w') or dir("Write Config File Error! ");
                flock($fp, 3);
                fwrite($fp, '<' . '?php' . "\r\n");
                foreach ($this->tpCfgs as $k => $v) {
                    $v = str_replace("\"", "\\\"", $v);
                    $v = str_replace("\$", "\\\$", $v);
                    fwrite($fp, "\$this->tpCfgs['$k']=\"$v\";\r\n");
                }
                fwrite($fp, '?' . '>');
                fclose($fp);
            }
        }
        /*
        if(!file_exists($this->cacheFile) || $this->isCache==FALSE
        || ( file_exists($this->templateFile) && (filemtime($this->templateFile) > filemtime($this->cacheFile)) ) )
        {
            if($ctype!='config')
            {
                if(!$this->isParse)
                {
                    $this->ParseTemplate();
                }
                $fp = fopen($this->cacheFile,'w') or dir("Write Cache File Error! ");
                flock($fp,3);
                fwrite($fp,trim($this->GetResult()));
                fclose($fp);

            }
            else
            {
                if(count($this->tpCfgs) > 0)
                {
                    $fp = fopen($this->configFile,'w') or dir("Write Config File Error! ");
                    flock($fp,3);
                    fwrite($fp,'<'.'?php'."\r\n");
                    foreach($this->tpCfgs as $k=>$v)
                    {
                        $v = str_replace("\"","\\\"",$v);
                        $v = str_replace("\$","\\\$",$v);
                        fwrite($fp,"\$this->tpCfgs['$k']=\"$v\";\r\n");
                    }
                    fwrite($fp,'?'.'>');
                    fclose($fp);
                }
            }
        }
        else
        {
            if($ctype=='config' && count($this->tpCfgs) > 0 )
            {
                $fp = fopen($this->configFile,'w') or dir("Write Config File Error! ");
                flock($fp,3);
                fwrite($fp,'<'.'?php'."\r\n");
                foreach($this->tpCfgs as $k=>$v)
                {
                    $v = str_replace("\"","\\\"",$v);
                    $v = str_replace("\$","\\\$",$v);
                    fwrite($fp,"\$this->tpCfgs['$k']=\"$v\";\r\n");
                }
                fwrite($fp,'?'.'>');
                fclose($fp);
            }
        }
        */
    }

    /**
     *  获得模板文件名的md5字符串
     *
     * @access    public
     * @param     string $tmpfile 模板文件
     * @return    string
     */
    public function GetEncodeStr($tmpfile)
    {
        //$tmpfiles = explode('/',$tmpfile);
        $encodeStr = substr(md5($tmpfile), 0, 24);

        return $encodeStr;
    }

    /**
     *  解析模板
     *
     * @access    public
     * @return    void
     */
    public function ParseTemplate()
    {
        if ($this->makeLoop > 5) {
            return;
        }
        $this->count    = -1;
        $this->cTags    = array();
        $this->isParse  = true;
        $sPos           = 0;
        $ePos           = 0;
        $tagStartWord   = $this->tagStartWord;
        $fullTagEndWord = $this->fullTagEndWord;
        $sTagEndWord    = $this->sTagEndWord;
        $tagEndWord     = $this->tagEndWord;
        $startWordLen   = strlen($tagStartWord);
        $sourceLen      = strlen($this->sourceString);
        if ($sourceLen <= ($startWordLen + 3)) {
            return;
        }
        $cAtt            = new TagAttributeParse();
        $cAtt->CharToLow = true;

        //遍历模板字符串，请取标记及其属性信息
        $t                        = 0;
        $preTag                   = '';
        $tswLen                   = strlen($tagStartWord);
        $cAtt->cAttributes->items = array();
        for ($i = 0; $i < $sourceLen; $i++) {
            $ttagName = '';

            //如果不进行此判断，将无法识别相连的两个标记
            if ($i - 1 >= 0) {
                $ss = $i - 1;
            } else {
                $ss = 0;
            }
            $tagPos = strpos($this->sourceString, $tagStartWord, $ss);

            //判断后面是否还有模板标记
            if ($tagPos == 0 && ($sourceLen - $i < $tswLen || substr($this->sourceString, $i, $tswLen) != $tagStartWord)) {
                $tagPos = -1;
                break;
            }

            //获取TAG基本信息
            for ($j = $tagPos + $startWordLen; $j < $tagPos + $startWordLen + $this->tagMaxLen; $j++) {
                if (preg_match("/[ >\/\r\n\t\}\.]/", $this->sourceString[$j])) {
                    break;
                } else {
                    $ttagName .= $this->sourceString[$j];
                }
            }
            if ($ttagName != '') {
                $i      = $tagPos + $startWordLen;
                $endPos = -1;

                //判断  '/}' '{tag:下一标记开始' '{/tag:标记结束' 谁最靠近
                $fullTagEndWordThis = $fullTagEndWord . $ttagName . $tagEndWord;

                $e1 = strpos($this->sourceString, $sTagEndWord, $i);
                $e2 = strpos($this->sourceString, $tagStartWord, $i);
                $e3 = strpos($this->sourceString, $fullTagEndWordThis, $i);

                $e1 = trim($e1);
                $e2 = trim($e2);
                $e3 = trim($e3);
                $e1 = ($e1 == '' ? '-1' : $e1);
                $e2 = ($e2 == '' ? '-1' : $e2);
                $e3 = ($e3 == '' ? '-1' : $e3);
                if ($e3 == -1) {
                    //不存在'{/tag:标记'
                    $endPos = $e1;
                    $elen   = $endPos + strlen($sTagEndWord);
                } else if ($e1 == -1) {
                    //不存在 '/}'
                    $endPos = $e3;
                    $elen   = $endPos + strlen($fullTagEndWordThis);
                } //同时存在 '/}' 和 '{/tag:标记'
                else {
                    //如果 '/}' 比 '{tag:'、'{/tag:标记' 都要靠近，则认为结束标志是 '/}'，否则结束标志为 '{/tag:标记'
                    if ($e1 < $e2 && $e1 < $e3) {
                        $endPos = $e1;
                        $elen   = $endPos + strlen($sTagEndWord);
                    } else {
                        $endPos = $e3;
                        $elen   = $endPos + strlen($fullTagEndWordThis);
                    }
                }

                //如果找不到结束标记，则认为这个标记存在错误
                if ($endPos == -1) {
                    echo "Tpl Character postion $tagPos, '$ttagName' Error！<br />\r\n";
                    break;
                }
                $i = $elen;

                //分析所找到的标记位置等信息
                $attStr     = '';
                $innerText  = '';
                $startInner = 0;
                for ($j = $tagPos + $startWordLen; $j < $endPos; $j++) {
                    if ($startInner == 0) {
                        if ($this->sourceString[$j] == $tagEndWord) {
                            $startInner = 1;
                            continue;
                        } else {
                            $attStr .= $this->sourceString[$j];
                        }
                    } else {
                        $innerText .= $this->sourceString[$j];
                    }
                }
                $ttagName = strtolower($ttagName);

                //if、php标记，把整个属性串视为属性
                if (preg_match("/^if[0-9]{0,}$/", $ttagName)) {
                    $cAtt->cAttributes                     = new GtaAttribute();
                    $cAtt->cAttributes->count              = 2;
                    $cAtt->cAttributes->items['tagname']   = $ttagName;
                    $cAtt->cAttributes->items['condition'] = preg_replace("/^if[0-9]{0,}[\r\n\t ]/", "", $attStr);
                    $innerText                             = preg_replace("/\{else\}/i", '<' . "?php\r\n}\r\nelse{\r\n" . '?' . '>', $innerText);
                } else if ($ttagName == 'php') {
                    $cAtt->cAttributes                   = new GtaAttribute();
                    $cAtt->cAttributes->count            = 2;
                    $cAtt->cAttributes->items['tagname'] = $ttagName;
                    $cAtt->cAttributes->items['code']    = '<' . "?php\r\n" . trim(preg_replace("/^php[0-9]{0,}[\r\n\t ]/", "", $attStr)) . "\r\n?" . '>';
                } else {
                    //普通标记，解释属性
                    $cAtt->SetSource($attStr);
                }
                $this->count++;
                $cTag                      = new GtaTag();
                $cTag->tagName             = $ttagName;
                $cTag->startPos            = $tagPos;
                $cTag->endPos              = $i;
                $cTag->cAtt                = $cAtt->cAttributes;
                $cTag->isCompiler          = false;
                $cTag->tagID               = $this->count;
                $cTag->innerText           = $innerText;
                $this->cTags[$this->count] = $cTag;
            } else {
                $i = $tagPos + $startWordLen;
                break;
            }
        }//结束遍历模板字符串
        if ($this->count > -1 && $this->isCompiler) {
            $this->CompilerAll();
        }
    }


    /**
     *  把模板标记转换为PHP代码
     *
     * @access    public
     * @return    void
     */
    public function CompilerAll()
    {
        $this->loopNum++;
        if ($this->loopNum > 10) {
            return; //限制最大递归深度为 10 以防止因标记出错等可能性导致死循环
        }
        $ResultString = '';
        $nextTagEnd   = 0;
        for ($i = 0; isset($this->cTags[$i]); $i++) {
            $ResultString .= substr($this->sourceString, $nextTagEnd, $this->cTags[$i]->startPos - $nextTagEnd);
            $ResultString .= $this->CompilerOneTag($this->cTags[$i]);
            $nextTagEnd   = $this->cTags[$i]->endPos;
        }
        $slen = strlen($this->sourceString);
        if ($slen > $nextTagEnd) {
            $ResultString .= substr($this->sourceString, $nextTagEnd, $slen - $nextTagEnd);
        }
        $this->sourceString = $ResultString;
        $this->ParseTemplate();
    }


    /**
     *  获得最终结果
     *
     * @access    public
     * @return    string
     */
    public function GetResult()
    {
        if (!$this->isParse) {
            $this->ParseTemplate();
        }
        $addset = '';
        $addset .= '<' . '?php' . "\r\n" . 'if(!isset($GLOBALS[\'_vars\'])) $GLOBALS[\'_vars\'] = array(); ' . "\r\n" . '$fields = array();' . "\r\n" . '?' . '>';

        return preg_replace("/\?" . ">[ \r\n\t]{0,}<" . "\?php/", "", $addset . $this->sourceString);
    }

    /**
     *  编译单个标记
     *
     * @access    public
     * @param     string $cTag 标签
     * @return    string
     */
    public function CompilerOneTag(&$cTag)
    {
        $cTag->isCompiler = true;
        $tagname          = $cTag->tagName;
        $varname          = $cTag->GetAtt('name');
        $rsvalue          = "";

        //用于在模板中设置一个变量以提供作扩展用途
        //此变量直接提交到 this->tpCfgs 中，并会生成与模板对应的缓存文件 ***_config.php 文件
        if ($tagname == 'config') {
            $this->tpCfgs[$varname] = $cTag->GetAtt('value');
        } else if ($tagname == 'global') {
            $cTag->tagValue = $this->CompilerArrayVar('global', $varname);
            if ($cTag->GetAtt('function') != '') {
                $cTag->tagValue = $this->CompilerFunction($cTag->GetAtt('function'), $cTag->tagValue);
            }
            $cTag->tagValue = '<' . '?php echo ' . $cTag->tagValue . '; ?' . '>';
        } else if ($tagname == 'cfg') {
            $cTag->tagValue = '$GLOBALS[\'cfg_' . $varname . '\']'; //处理函数
            if ($cTag->GetAtt('function') != '') {
                $cTag->tagValue = $this->CompilerFunction($cTag->GetAtt('function'), $cTag->tagValue);
            }
            $cTag->tagValue = '<' . '?php echo ' . $cTag->tagValue . '; ?' . '>';
        } else if ($tagname == 'name') {
            $cTag->tagValue = '$' . $varname; //处理函数
            if ($cTag->GetAtt('function') != '') {
                $cTag->tagValue = $this->CompilerFunction($cTag->GetAtt('function'), $cTag->tagValue);
            }
            $cTag->tagValue = '<' . '?php echo ' . $cTag->tagValue . '; ?' . '>';
        } else if ($tagname == 'object') {
            list($_obs, $_em) = explode('->', $varname);
            $cTag->tagValue = "\$GLOBALS['{$_obs}']->{$_em}"; //处理函数
            if ($cTag->GetAtt('function') != '') {
                $cTag->tagValue = $this->CompilerFunction($cTag->GetAtt('function'), $cTag->tagValue);
            }
            $cTag->tagValue = '<' . '?php echo ' . $cTag->tagValue . '; ?' . '>';
        } else if ($tagname == 'var') {
            $cTag->tagValue = $this->CompilerArrayVar('var', $varname);

            if ($cTag->GetAtt('function') != '') {
                $cTag->tagValue = $this->CompilerFunction($cTag->GetAtt('function'), $cTag->tagValue);
            }
            // 增加默认空值处理
            if ($cTag->GetAtt('default') != '') {
                $cTag->tagValue = '<' . '?php echo empty(' . $cTag->tagValue . ')? \'' . addslashes($cTag->GetAtt('default')) . '\':' . $cTag->tagValue . '; ?' . '>';
            } else {
                $cTag->tagValue = '<' . '?php echo ' . $cTag->tagValue . '; ?' . '>';
            }
        } else if ($tagname == 'field') {
            $cTag->tagValue = '$fields[\'' . $varname . '\']';
            if ($cTag->GetAtt('function') != '') {
                $cTag->tagValue = $this->CompilerFunction($cTag->GetAtt('function'), $cTag->tagValue);
            }
            $cTag->tagValue = '<' . '?php echo ' . $cTag->tagValue . '; ?' . '>';
        } else if (preg_match("/^key[0-9]{0,}/", $tagname) || preg_match("/^value[0-9]{0,}/", $tagname)) {
            if (preg_match("/^value[0-9]{0,}/", $tagname) && $varname != '') {
                $cTag->tagValue = '<' . '?php echo ' . $this->CompilerArrayVar($tagname, $varname) . '; ?' . '>';
            } else {
                $cTag->tagValue = '<' . '?php echo $' . $tagname . '; ?' . '>';
            }
        } else if (preg_match("/^if[0-9]{0,}$/", $tagname)) {
            $cTag->tagValue = $this->CompilerIf($cTag);
        } else if ($tagname == 'echo') {
            if (trim($cTag->GetInnerText()) == '') {
                $cTag->tagValue = $cTag->GetAtt('code');
            } else {
                $cTag->tagValue = '<' . "?php echo $" . trim($cTag->GetInnerText()) . " ;?" . '>';
            }
        } else if ($tagname == 'php') {
            if (trim($cTag->GetInnerText()) == '') {
                $cTag->tagValue = $cTag->GetAtt('code');
            } else {
                $cTag->tagValue = '<' . "?php\r\n" . trim($cTag->GetInnerText()) . "\r\n?" . '>';
            }
        } //遍历数组
        else if (preg_match("/^array[0-9]{0,}/", $tagname)) {
            $kk = '$key';
            $vv = '$value';
            if ($cTag->GetAtt('key') != '') {
                $kk = '$key' . $cTag->GetAtt('key');
            }
            if ($cTag->GetAtt('value') != '') {
                $vv = '$value' . $cTag->GetAtt('value');
            }
            $addvar = '';
            if (!preg_match("/\(/", $varname)) {
                $varname = '$GLOBALS[\'' . $varname . '\']';
            } else {
                $addvar  = "\r\n" . '$myarrs = $pageClass->' . $varname . ";\r\n";
                $varname = ' $myarrs ';
            }
            $rsvalue        = '<' . '?php ' . $addvar . ' foreach(' . $varname . ' as ' . $kk . '=>' . $vv . '){ ?' . ">";
            $rsvalue        .= $cTag->GetInnerText();
            $rsvalue        .= '<' . '?php  }    ?' . ">\r\n";
            $cTag->tagValue = $rsvalue;
        } //include 文件
        else if ($tagname == 'include') {
            $filename = $cTag->GetAtt('file');
            if ($filename == '') {
                $filename = $cTag->GetAtt('filename');
            }
            $cTag->tagValue = $this->CompilerInclude($filename, false);
            if ($cTag->tagValue == 0) {
                $cTag->tagValue = '';
            }
            $cTag->tagValue = '<' . '?php include $this->CompilerInclude("' . $filename . '");' . "\r\n" . ' ?' . '>';
        } else if ($tagname == 'label') {
            $bindFunc       = $cTag->GetAtt('bind');
            $rsvalue        = 'echo ' . $bindFunc . ";\r\n";
            $rsvalue        = '<' . '?php  ' . $rsvalue . '  ?' . ">\r\n";
            $cTag->tagValue = $rsvalue;
        } else if ($tagname == 'datalist') {
            //生成属性数组
            foreach ($cTag->cAtt->items as $k => $v) {
                $v       = $this->TrimAtts($v);
                $rsvalue .= '$atts[\'' . $k . '\'] = \'' . str_replace("'", "\\'", $v) . "';\r\n";
            }
            $rsvalue        = '<' . '?php' . "\r\n" . '$atts = array();' . "\r\n" . $rsvalue;
            $rsvalue        .= '$blockValue = $this->refObj->GetArcList($atts,$this->refObj,$fields); ' . "\r\n";
            $rsvalue        .= 'if(is_array($blockValue)){' . "\r\n";
            $rsvalue        .= 'foreach( $blockValue as $key=>$fields )' . "\r\n{\r\n" . '?' . ">";
            $rsvalue        .= $cTag->GetInnerText();
            $rsvalue        .= '<' . '?php' . "\r\n}\r\n}" . '?' . '>';
            $cTag->tagValue = $rsvalue;
        } else if ($tagname == 'pagelist') {
            //生成属性数组
            foreach ($cTag->cAtt->items as $k => $v) {
                $v       = $this->TrimAtts($v);
                $rsvalue .= '$atts[\'' . $k . '\'] = \'' . str_replace("'", "\\'", $v) . "';\r\n";
            }
            $rsvalue        = '<' . '?php' . "\r\n" . '$atts = array();' . "\r\n" . $rsvalue;
            $rsvalue        .= ' echo $this->refObj->GetPageList($atts,$this->refObj,$fields); ' . "\r\n" . '?' . ">\r\n";
            $cTag->tagValue = $rsvalue;
        } else {
            $bindFunc = $cTag->GetAtt('bind');
            $bindType = $cTag->GetAtt('bindtype');
            $rstype   = ($cTag->GetAtt('resulttype') == '' ? $cTag->GetAtt('rstype') : $cTag->GetAtt('resulttype'));
            $rstype   = strtolower($rstype);

            //生成属性数组
            foreach ($cTag->cAtt->items as $k => $v) {
                if (preg_match("/(bind|bindtype)/i", $k)) {
                    continue;
                }
                $v       = $this->TrimAtts($v);
                $rsvalue .= '$atts[\'' . $k . '\'] = \'' . str_replace("'", "\\'", $v) . "';\r\n";
            }
            $rsvalue = '<' . '?php' . "\r\n" . '$atts = array();' . "\r\n" . $rsvalue;

            //绑定到默认函数还是指定函数(datasource属性指定)
            if ($bindFunc == '') {
                $rsvalue .= '$blockValue = MakePublicTag($atts,$this->refObj,$fields); ' . "\r\n";
            } else {
                //自定义绑定函数如果不指定 bindtype，则指向$this->refObj->绑定函数名，即是默认指向被引用的类对象
                if ($bindType == '') {
                    $rsvalue .= '$blockValue = $this->refObj->' . $bindFunc . '($atts,$this->refObj,$fields); ' . "\r\n";
                } else {
                    $rsvalue .= '$blockValue = ' . $bindFunc . '($atts,$this->refObj,$fields); ' . "\r\n";
                }
            }

            //返回结果类型：默认为 array 是一个二维数组，string 是字符串
            if ($rstype == 'string') {
                $rsvalue .= 'echo $blockValue;' . "\r\n" . '?' . ">";
            } else {
                $rsvalue .= 'if(is_array($blockValue) && count($blockValue) > 0){' . "\r\n";
                $rsvalue .= 'foreach( $blockValue as $key=>$fields )' . "\r\n{\r\n" . '?' . ">";
                $rsvalue .= $cTag->GetInnerText();
                $rsvalue .= '<' . '?php' . "\r\n}\r\n}\r\n" . '?' . '>';
            }
            $cTag->tagValue = $rsvalue;
        }

        return $cTag->tagValue;
    }

    /**
     *  编译可能为数组的变量
     *
     * @access    public
     * @param     string $vartype 变量类型
     * @param     string $varname 变量名称
     * @return    string
     */
    public function CompilerArrayVar($vartype, $varname)
    {
        $okvalue = '';

        if (!preg_match("/\[/", $varname)) {
            if (preg_match("/^value/", $vartype)) {
                $varname = $vartype . '.' . $varname;
            }
            $varnames = explode('.', $varname);
            if (isset($varnames[1])) {
                $varname = $varnames[0];
                for ($i = 1; isset($varnames[$i]); $i++) {
                    $varname .= "['" . $varnames[$i] . "']";
                }
            }
        }

        if (preg_match("/\[/", $varname)) {
            $varnames = explode('[', $varname);
            $arrend   = '';
            for ($i = 1; isset($varnames[$i]); $i++) {
                $arrend .= '[' . $varnames[$i];
            }
            if (!preg_match("/[\"']/", $arrend)) {
                $arrend = str_replace('[', '', $arrend);
                $arrend = str_replace(']', '', $arrend);
                $arrend = "['{$arrend}']";
            }
            if ($vartype == 'var') {
                $okvalue = '$GLOBALS[\'_vars\'][\'' . $varnames[0] . '\']' . $arrend;
            } else if (preg_match("/^value/", $vartype)) {
                $okvalue = '$' . $varnames[0] . $arrend;
            } else if ($vartype == 'field') {
                $okvalue = '$fields[\'' . $varnames[0] . '\']' . $arrend;
            } else {
                $okvalue = '$GLOBALS[\'' . $varnames[0] . '\']' . $arrend;
            }
        } else {
            if ($vartype == 'var') {
                $okvalue = '$GLOBALS[\'_vars\'][\'' . $varname . '\']';
            } else if (preg_match("/^value/", $vartype)) {
                $okvalue = '$' . $vartype;
            } else if ($vartype == 'field') {
                $okvalue = '$' . str_replace($varname);
            } else {
                $okvalue = '$GLOBALS[\'' . $varname . '\']';
            }
        }

        return $okvalue;
    }

    /**
     *  编译if标记
     *
     * @access    public
     * @param     string $cTag 标签
     * @return    string
     */
    public function CompilerIf($cTag)
    {
        $condition = trim($cTag->GetAtt('condition'));
        if ($condition == '') {
            $cTag->tagValue = '';

            return '';
        }
        if (version_compare(PHP_VERSION, '5.5.0', '>=')) {
            $condition = preg_replace_callback("/((var\.|field\.|cfg\.|global\.|key[0-9]{0,}\.|value[0-9]{0,}\.)[\._a-z0-9]+)/is", "private_rt", $condition);
        } else {
            $condition = preg_replace("/((var\.|field\.|cfg\.|global\.|key[0-9]{0,}\.|value[0-9]{0,}\.)[\._a-z0-9]+)/ies", "private_rt('\\1')", $condition);
        }
        $rsvalue = '<' . '?php if(' . $condition . '){ ?' . '>';
        $rsvalue .= $cTag->GetInnerText();
        $rsvalue .= '<' . '?php } ?' . '>';

        return $rsvalue;
    }

    /**
     *  处理block区块传递的atts属性的值
     *
     * @access    public
     * @param     string $v 值
     * @return    string
     */
    public function TrimAtts($v)
    {
        $v = str_replace('<' . '?', '&lt;?', $v);
        $v = str_replace('?' . '>', '?&gt;', $v);

        return $v;
    }

    /**
     *  函数 function 语法处理
     *
     * @access    public
     * @param     string $funcstr 函数字符串
     * @param     string $nvalue  函数值
     * @return    string
     */
    public function CompilerFunction($funcstr, $nvalue)
    {
        $funcstr = str_replace('@quote', '"', $funcstr);
        $funcstr = str_replace('@me', $nvalue, $funcstr);

        return $funcstr;
    }

    /**
     *  引入文件 include 语法处理
     *
     * @access    public
     * @param     string $filename 文件名
     * @param     string $isload   是否载入
     * @return    string
     */
    public function CompilerInclude($filename, $isload = true)
    {
        $okfile = '';
        if (@file_exists($filename)) {
            $okfile = $filename;
        } else if (@file_exists($this->refDir . $filename)) {
            $okfile = $this->refDir . $filename;
        } else if (@file_exists($this->refDir . "../" . $filename)) {
            $okfile = $this->refDir . "../" . $filename;
        }
        if ($okfile == '') {
            return 0;
        }
        if (!$isload) {
            return 1;
        }
        $itpl          = new GtaTemplate($this->templateDir);
        $itpl->isCache = $this->isCache;
        $itpl->SetObject($this->refObj);
        $itpl->LoadTemplate($okfile);

        return $itpl->CacheFile();
    }
}
