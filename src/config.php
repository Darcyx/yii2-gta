<?php
/**
 * Config
 * @author  darcy <darcyonw@163.com>
 * @date    2018/5/16
 */
define('GTA_ENVIRONMENT', 'production');
if (GTA_ENVIRONMENT == 'production') {
    error_reporting(E_ALL || ~E_NOTICE);
} else {
    error_reporting(E_ALL);
}

define('GTA_INC', str_replace("\\", '/', dirname(__FILE__)));
define('GTA_ROOT', dirname(dirname(__DIR__)));        //项目根目录
define('GTA_RUNTIME', GTA_ROOT . '/runtime');               //运行目录
define('GTA_COMPILE', GTA_RUNTIME . '/tpl/compile');        //缓存目录
define('GTA_CACHE', GTA_RUNTIME . '/tpl/cache');            //缓存目录
define('GTA_TEMPLATE', GTA_ROOT . '/templates');            //模版目录
define('DEBUG_LEVEL', false);