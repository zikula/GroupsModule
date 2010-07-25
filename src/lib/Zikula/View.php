<?php
/**
 * Copyright Zikula Foundation 2009 - Zikula Application Framework
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license GNU/LGPLv3 (or at your option, any later version).
 * @package Zikula_View
 *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */

/**
 * Zikula_View class.
 */
class Zikula_View extends Smarty implements Zikula_Translatable
{
    /**
     * Module name.
     *
     * @var string
     */
    public $module;

    /**
     * Top level module.
     *
     * @var string
     */
    public $toplevelmodule;

    /**
     * Module info.
     *
     * @var array
     */
    public $modinfo;

    /**
     * Theme name.
     *
     * @var string
     */
    public $theme;

    /**
     * Theme info.
     *
     * @var array
     */
    public $themeinfo;

    /**
     * Language.
     *
     * @var string
     */
    public $language;

    /**
     * Base Url.
     *
     * @var string
     */
    public $baseurl;

    /**
     * Base Uri.
     *
     * @var string
     */
    public $baseuri;

    /**
     * Cache Id.
     *
     * @var string
     */
    public $cache_id;

    /**
     * Set if Theme is an active module and templates stored in database.
     *
     * @var boolean
     */
    public $userdb;

    /**
     * Whether or not to expose template.
     *
     * @var boolean
     */
    public $expose_template;

    /**
     * Translation domain of the calling module.
     *
     * @var string
     */
    public $domain;

    /**
     * @var Zikula_ServiceManager
     */
    protected $serviceManager;

    /**
     * @var Zikula_EventManager
     */
    protected $eventManager;

    /**
     * Constructor.
     *
     * @param string       $module  Module name ("zikula" for system plugins).
     * @param boolean|null $caching Whether or not to cache (boolean) or use config variable (null).
     */
    public function __construct($module = '', $caching = null)
    {
        $this->serviceManager = ServiceUtil::getManager();
        $this->eventManager = EventUtil::getManager();

        // set the error reporting level
        $this->error_reporting = isset($GLOBALS['ZConfig']['Debug']['error_reporting']) ? $GLOBALS['ZConfig']['Debug']['error_reporting'] : E_ALL;
        $this->allow_php_tag = true;

        // Initialize the module property with the name of
        // the topmost module. For Hooks, Blocks, API Functions and others
        // you need to set this property to the name of the respective module!
        $this->toplevelmodule = ModUtil::getName();
        if (!$module) {
            $module = $this->toplevelmodule;
        }
        $this->module = array($module => ModUtil::getInfoFromName($module));

        // initialise environment vars
        $this->language = ZLanguage::getLanguageCode();
        $this->baseurl = System::getBaseUrl();
        $this->baseuri = System::getBaseUri();

        //---- Plugins handling -----------------------------------------------
        // add plugin paths
        $this->themeinfo = ThemeUtil::getInfo(ThemeUtil::getIDFromName(UserUtil::getTheme()));
        $this->theme = $theme = $this->themeinfo['directory'];

        $this->modinfo = ModUtil::getInfoFromName($module);

        $modpath = ($this->module[$module]['type'] == ModUtil::TYPE_SYSTEM) ? 'system' : 'modules';
        switch ($this->module[$module]['type'])
        {
            case ModUtil::TYPE_MODULE :
                $mpluginPath = "modules/" . $this->module[$module]['directory'] . "/templates/plugins";
                $mpluginPathOld = "modules/" . $this->module[$module]['directory'] . "/pntemplates/plugins";
                break;
            case ModUtil::TYPE_SYSTEM :
                $mpluginPath = "system/" . $this->module[$module]['directory'] . "/templates/plugins";
                $mpluginPathOld = "system/" . $this->module[$module]['directory'] . "/pntemplates/plugins";
                break;
            default:
                $mpluginPath = "system/" . $this->module[$module]['directory'] . "/templates/plugins";
                $mpluginPathOld = "system/" . $this->module[$module]['directory'] . "/pntemplates/plugins";
        }

        $pluginpaths[] = 'lib/view/plugins';
        if (System::isLegacyMode()) {
            $pluginpaths[] = 'lib/legacy/plugins';
        }
        $pluginpaths[] = 'config/plugins';
        $pluginpaths[] = "themes/$theme/templates/modules/$module/plugins";
        $pluginpaths[] = "themes/$theme/plugins";
        $pluginpaths[] = $mpluginPath;
        if (System::isLegacyMode()) {
            $pluginpaths[] = $mpluginPathOld;
        }

        foreach ($pluginpaths as $pluginpath) {
            $this->addPluginDir($pluginpath);
        }

        // check if the recent 'type' parameter in the URL is admin and if yes,
        // include system/Admin/templates/plugins to the plugins_dir array
        $type = FormUtil::getPassedValue('type', null, 'GETPOST');
        if ($type === 'admin') {
            $this->addPluginDir('system/Admin/templates/plugins');
            $this->load_filter('output', 'admintitle');
        }

        //---- Cache handling -------------------------------------------------
        if (isset($caching) && is_bool($caching)) {
            $this->caching = $caching;
        } else {
            $this->caching = ModUtil::getVar('Theme', 'render_cache');
        }

        if (isset($_POST) && count($_POST) != 0) {
            // write actions should not be cached or weird things happen
            $this->caching = false;
        }

        $this->cache_lifetime = ModUtil::getVar('Theme', 'render_lifetime');
        $this->cache_dir = CacheUtil::getLocalDir() . '/view_cache';
        $this->compile_check = ModUtil::getVar('Theme', 'render_compile_check');
        $this->force_compile = ModUtil::getVar('Theme', 'render_force_compile');

        $this->compile_dir = CacheUtil::getLocalDir() . '/view_compiled';
        $this->compile_id = '';
        $this->cache_id = '';
        $this->expose_template = (ModUtil::getVar('Theme', 'render_expose_template') == true) ? true : false;
        $this->register_block('nocache', 'Zikula_View_block_nocache', false);

        // register resource type 'z' this defines the way templates are searched
        // during {include file='my_template.tpl'} this enables us to store selected module
        // templates in the theme while others can be kept in the module itself.
        $this->register_resource('z', array('z_get_template',
                                            'z_get_timestamp',
                                            'z_get_secure',
                                            'z_get_trusted'));

        // set 'z' as default resource type
        $this->default_resource_type = 'z';

        // For ajax requests we use the short urls filter to 'fix' relative paths
        if ((System::getStages() & System::CORE_STAGES_AJAX) && System::getVar('shorturls')) {
            $this->load_filter('output', 'shorturls');
        }

        // register prefilters
        $this->register_prefilter('z_prefilter_add_literal');

        if ($GLOBALS['ZConfig']['System']['legacy_prefilters']) {
            $this->register_prefilter('z_prefilter_legacy');
        }

        $this->register_prefilter('z_prefilter_gettext_params');

        // Assign some useful theme settings
        //$this->assign(ThemeUtil::getVar()); // TODO A [investigate - this appears to always be empty and causes loops] (drak)
        $this->assign('baseurl', $this->baseurl);
        $this->assign('baseuri', $this->baseuri);
        $this->assign('themepath', $this->baseurl . 'themes/' . $theme);
        $this->assign('stylepath', $this->baseurl . 'themes/' . $theme . '/style');
        $this->assign('scriptpath', $this->baseurl . 'themes/' . $theme . '/javascript');
        $this->assign('imagepath', $this->baseurl . 'themes/' . $theme . '/images');
        $this->assign('imagelangpath', $this->baseurl . 'themes/' . $theme . '/images/' . $this->language);

        // for {gt} template plugin to detect gettext domain
        if ($this->module[$module]['type'] == ModUtil::TYPE_MODULE) {
            $this->domain = ZLanguage::getModuleDomain($this->module[$module]['name']);
        }

        // make render object available to modifiers
        parent::assign('zikula_view', $this);

        // Add ServiceManager and EventManager to all templates
        parent::assign('serviceManager', $this->serviceManager);
        parent::assign('eventManager', $this->eventManager);

        // add some useful data
        $this->assign(array('module' => $module,
                            'modinfo' => $this->modinfo,
                            'themeinfo' => $this->themeinfo));

        // This event sends $this as the subject so you can modify as required:
        // e.g.  $event->getSubject()->register_prefilter('foo');
        $event = new Zikula_Event('view.init', $this);
        $this->eventManager->notify($event);
    }

    public function getModule()
    {
        return $this->module;
    }

    public function getToplevelmodule()
    {
        return $this->toplevelmodule;
    }

    public function getModinfo()
    {
        return $this->modinfo;
    }

    public function getTheme()
    {
        return $this->theme;
    }

    public function getThemeinfo()
    {
        return $this->themeinfo;
    }

    public function getLanguage()
    {
        return $this->language;
    }

    public function getBaseurl() {
        return $this->baseurl;
    }

    public function getBaseuri()
    {
        return $this->baseuri;
    }

    public function getCache_id()
    {
        return $this->cache_id;
    }

    public function getUserdb()
    {
        return $this->userdb;
    }

    public function getExpose_template()
    {
        return $this->expose_template;
    }

    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * Get ServiceManager.
     *
     * @return Zikula_ServiceManager
     */
    public function getServiceManager()
    {
        return $this->serviceManager;
    }

    /**
     * Get EventManager.
     *
     * @return Zikula_Eventmanager
     */
    public function getEventManager()
    {
        return $this->eventManager;
    }

    /**
     * Set cache ID.
     *
     * @param string $id Cache ID.
     *
     * @return Zikula_View
     */
    public function setCache_Id($id)
    {
        $this->cache_id = $id;
        return $this;
    }

    /**
     * Set compile check.
     *
     * @param $boolean
     *
     * @retrun Zikula_View
     */
    public function setCompile_check($boolean)
    {
        $this->compile_check = $boolean;
        return $this;
    }

    /**
     * Add a plugin dir to the search path.
     *
     * Avoids adding duplicates.
     *
     * @return Zikula_View
     */
    public function addPluginDir($dir)
    {
        if (in_array($dir, $this->plugins_dir) || !is_dir($dir)) {
            return;
        }

        array_push($this->plugins_dir, $dir);
        return $this;
    }

    /**
     * Translate.
     *
     * @param string $msgid String to be translated.
     *
     * @return string
     */
    public function __($msgid)
    {
        return __($msgid, $this->domain);
    }

    /**
     * Translate with sprintf().
     *
     * @param string       $msgid  String to be translated.
     * @param string|array $params Args for sprintf().
     *
     * @return string
     */
    public function __f($msgid, $params)
    {
        return __f($msgid, $params, $this->domain);
    }

    /**
     * Translate plural string.
     *
     * @param string $singular Singular instance.
     * @param string $plural   Plural instance.
     * @param string $count    Object count.
     *
     * @return string Translated string.
     */
    public function _n($singular, $plural, $count)
    {
        return _n($singular, $plural, $count, $this->domain);
    }

    /**
     * Translate plural string with sprintf().
     *
     * @param string       $sin    Singular instance.
     * @param string       $plu    Plural instance.
     * @param string       $n      Object count.
     * @param string|array $params Sprintf() arguments.
     *
     * @return string
     */
    public function _fn($sin, $plu, $n, $params)
    {
        return _fn($sin, $plu, $n, $params, $this->domain);
    }

    /**
     * Setup the current instance of the Zikula_View class and return it back to the module.
     *
     * @param string       $module        Module name.
     * @param boolean|null $caching       Whether or not to cache (boolean) or use config variable (null).
     * @param string       $cache_id      Cache Id.
     * @param boolean      $add_core_data Add core data to render data.
     *
     * @return Zikula_View
     */
    public static function getInstance($module = null, $caching = null, $cache_id = null, $add_core_data = false)
    {
        if (is_null($module)) {
            $module = ModUtil::getName();
        }

        $sm = ServiceUtil::getManager();
        $serviceId = strtolower(sprintf('zikula.render.%s', $module));
        if (!$sm->hasService($serviceId)) {
            $view = new self($module, $caching);
            $sm->attachService($serviceId, $view);
        } else {
            $view = $sm->getService($serviceId);
        }

        if (!is_null($caching)) {
            $view->caching = $caching;
        }

        if (!is_null($cache_id)) {
            $view->cache_id = $cache_id;
        }

        if ($module === null) {
            $module = $view->toplevelmodule;
        }

        if (!array_key_exists($module, $view->module)) {
            $view->module[$module] = ModUtil::getInfoFromName($module);
            //$instance->modinfo = ModUtil::getInfoFromName($module);
            $view->_add_plugins_dir($module);
        }

        if ($add_core_data) {
            $view->add_core_data();
        }

        // for {gt} template plugin to detect gettext domain
        if ($view->module[$module]['type'] == ModUtil::TYPE_MODULE) {
            $view->domain = ZLanguage::getModuleDomain($view->module[$module]['name']);
        }

        if (System::isLegacyMode()) {
            // load the usemodules configuration if exists
            $modpath = ($view->module[$module]['type'] == ModUtil::TYPE_SYSTEM) ? 'system' : 'modules';
            $usepath = "$modpath/" . $view->module[$module]['directory'] . '/templates/config';
            $usepathOld = "$modpath/" . $view->module[$module]['directory'] . '/pntemplates/config';
            $usemod_confs = array();
            $usemod_confs[] = "$usepath/usemodules.txt";
            $usemod_confs[] = "$usepathOld/usemodules.txt";
            $usemod_confs[] = "$usepath/usemodules"; // backward compat for < 1.2 // TODO A depreciate from 1.4
            // load the config file
            foreach ($usemod_confs as $usemod_conf) {
                if (is_readable($usemod_conf) && is_file($usemod_conf)) {
                    $additionalmodules = file($usemod_conf);
                    if (is_array($additionalmodules)) {
                        foreach ($additionalmodules as $addmod) {
                            $view->_add_plugins_dir(trim($addmod));
                        }
                    }
                }
            }
        }

        return $view;
    }

    /**
     * Get module plugin Zikula_View_Plugin instance.
     *
     * @param string       $modName       Module name.
     * @param string       $pluginName    Plugin name.
     * @param boolean|null $caching       Whether or not to cache (boolean) or use config variable (null).
     * @param string       $cache_id      Cache Id.
     * @param boolean      $add_core_data Add core data to render data.
     *
     * @return Zikula_View_Plugin
     */
    public static function getModulePluginInstance($modName, $pluginName, $caching = null, $cache_id = null, $add_core_data = false)
    {
        return Zikula_View_Plugin::getInstance($modName, $pluginName, $caching, $cache_id, $add_core_data);
    }

    /**
     * Get system plugin Zikula_View_Plugin instance.
     *
     * @param string       $pluginName    Plugin name.
     * @param boolean|null $caching       Whether or not to cache (boolean) or use config variable (null).
     * @param string       $cache_id      Cache Id.
     * @param boolean      $add_core_data Add core data to render data.
     *
     * @return Zikula_View_Plugin
     */
    public static function getSystemPluginInstance($pluginName, $caching = null, $cache_id = null, $add_core_data = false)
    {
        $modName = 'zikula';
        return Zikula_View_Plugin::getInstance($modName, $pluginName, $caching, $cache_id, $add_core_data);
    }

    /**
     * Checks whether requested template exists.
     *
     * @param string $template Template name.
     *
     * @return boolean
     */
    public function template_exists($template)
    {
        return (bool)$this->get_template_path($template);
    }

    /**
     * Checks which path to use for required template.
     *
     * @param string $template Template name.
     *
     * @return string Template path.
     */
    public function get_template_path($template)
    {
        static $cache = array();

        if (isset($cache[$template])) {
            return $cache[$template];
        }

        // the current module
        $modname = ModUtil::getName();

        foreach ($this->module as $module => $modinfo) {
            // prepare the values for OS
            $module = $modinfo['name'];

            $os_modname = DataUtil::formatForOS($modname);
            $os_module = DataUtil::formatForOS($module);
            $os_theme = DataUtil::formatForOS($this->theme);
            $os_dir = $modinfo['type'] == ModUtil::TYPE_MODULE ? 'modules' : 'system';

            $ostemplate = DataUtil::formatForOS($template);

            // check the module for which we're looking for a template is the
            // same as the top level mods. This limits the places to look for
            // templates.
            if ($module == $modname) {
                $search_path = array(
                        "themes/$os_theme/templates/modules/$os_module", // themepath
                        "config/templates/$os_module", //global path
                        "$os_dir/$os_module/templates", // modpath
                        "$os_dir/$os_module/pntemplates", // modpath old
                );
            } else {
                $search_path = array("themes/$os_theme/templates/modules/$os_module/$os_modname", // themehookpath
                        "themes/$os_theme/templates/modules/$os_module", // themepath
                        "config/templates/$os_module/$os_modname", //globalhookpath
                        "config/templates/$os_module", //global path
                        "$os_dir/$os_module/templates/$os_modname", //modhookpath
                        "$os_dir/$os_module/templates", // modpath
                        "$os_dir/$os_module/pntemplates/$os_modname", // modhookpathold
                        "$os_dir/$os_module/pntemplates", // modpath old
                );
            }

            foreach ($search_path as $path) {
                if (is_readable("$path/$ostemplate")) {
                    $cache[$template] = $path;
                    return $path;
                }
            }
        }

        // when we arrive here, no path was found
        return false;
    }

    /**
     * Executes & returns the template results.
     *
     * This returns the template output instead of displaying it.
     * Supply a valid template name.
     * As an optional second parameter, you can pass a cache id.
     * As an optional third parameter, you can pass a compile id.
     *
     * @param string  $template   The name of the template.
     * @param string  $cache_id   The cache ID (optional).
     * @param string  $compile_id The compile ID (optional).
     * @param boolean $display    Whether or not to display directly (optional).
     * @param boolean $reset      Reset singleton defaults (optional).
     *
     * @return string The template output.
     */
    public function fetch($template, $cache_id = null, $compile_id = null, $display = false, $reset = true)
    {
        $this->_setup_template($template);

        if (is_null($cache_id)) {
            $cache_id = $this->cache_id;
        }

        if (is_null($compile_id)) {
            $compile_id = $this->compile_id;
        }

        $output = parent::fetch($template, $cache_id, $compile_id, $display);

        if ($this->expose_template == true) {
            $template = DataUtil::formatForDisplay($template);
            $output = "\n<!-- Start " . $this->template_dir . "/$template -->\n" . $output . "\n<!-- End " . $this->template_dir . "/$template -->\n";
        }

        // now we've got our output from this module reset our instance
        if ($reset) {
            //$this->module = $this->toplevelmodule;
        }

        return $output;
    }

    /**
     * Executes & displays the template results.
     *
     * This displays the template.
     * Supply a valid template name.
     * As an optional second parameter, you can pass a cache id.
     * As an optional third parameter, you can pass a compile id.
     *
     * @param string $template   The name of the template.
     * @param string $cache_id   The cache ID (optional).
     * @param string $compile_id The compile ID (optional).
     *
     * @return boolean
     */
    public function display($template, $cache_id = null, $compile_id = null)
    {
        echo $this->fetch($template, $cache_id, $compile_id);
        return true;
    }

     /**
     * returns an auto_id for auto-file-functions
     *
     * @param string $cache_id
     * @param string $compile_id
     * @return string|null
     */
    function _get_auto_id($cache_id=null, $compile_id=null) {
        if (isset($cache_id)) {
            $auto_id = (isset($compile_id) && !empty($compile_id)) ? $cache_id . '_' . $compile_id  : $cache_id;
        }
        elseif (isset($compile_id)) {
            $auto_id = $compile_id;
        }
        else {
            $auto_id = null;
        }

        return md5($auto_id);
    }

    /**
     * get a concrete filename for automagically created content
     *
     * @param string $auto_base
     * @param string $auto_source
     * @param string $auto_id
     * @return string
     * @staticvar string|null
     * @staticvar string|null
     */
    function _get_auto_filename($auto_base, $auto_source = null, $auto_id = null)
    {
        $path = $auto_base.'/';

        $multilingual = System::getVar('multilingual');

        if ($multilingual == 1) {
            $path .= $this->language.'/';
        }

        if ($this instanceof Zikula_View_Theme) {
            //$path .= 'themes/';
            $path .= $this->themeinfo['directory'] . '/';
//        } elseif ($this instanceof Zikula_View_Plugin) {
//            //$path .= 'themes/';
//            $path .= $this->modinfo['directory'] . '/' . $this->pluginName['directory'] . '/';
        } else {
            //$path .= 'modules/';
            $path .= $this->modinfo['directory'].'/';
        }

        //echo '<p>'.$path.'</p>';

        if (!file_exists($path)) {
            mkdir($path, $this->serviceManager['system.chmod_dir'], true);
        }

        if (isset($auto_id) && !empty($auto_id)) {
            $file = $auto_id.'_'.$auto_source;
        } else {
            $file = $auto_source;
        }

        return $path.$file;
    }

    /**
     * Finds out if a template is already cached.
     *
     * This returns true if there is a valid cache for this template.
     * Right now, we are just passing it to the original Smarty function.
     * We might introduce a function to decide if the cache is in need
     * to be refreshed...
     *
     * @param string $template   The name of the template.
     * @param string $cache_id   The cache ID (optional).
     * @param string $compile_id The compile ID (optional).
     *
     * @return boolean
     */
    public function is_cached($template, $cache_id = null, $compile_id = null)
    {
        if (is_null($cache_id)) {
            $cache_id = $this->cache_id;
        }

        if (is_null($compile_id)) {
            $compile_id = $this->compile_id;
        }

        return parent::is_cached($template, $cache_id, $compile_id);
    }

    /**
     * Clears the cache for a specific template.
     *
     * This returns true if there is a valid cache for this template.
     * Right now, we are just passing it to the original Smarty function.
     * We might introduce a function to decide if the cache is in need
     * to be refreshed...
     *
     * @param string $template   The name of the template.
     * @param string $cache_id   The cache ID (optional).
     * @param string $compile_id The compile ID (optional).
     * @param string $expire     Minimum age in sec. the cache file must be before it will get cleared (optional).
     *
     * @return  boolean
     */
    public function clear_cache($template = null, $cache_id = null, $compile_id = null, $expire = null)
    {
        /*
        if ($cache_id) {
            $cache_id = $this->baseurl . '_' . $this->toplevelmodule . '_' . $cache_id;
        } else {
            $cache_id = $this->baseurl . '_' . $this->toplevelmodule . '_' . $this->cache_id;
        }

        return parent::clear_cache($template, $cache_id, $compile_id, $expire);
        */

        $cache_dir = $this->cache_dir;

        $cached_files = FileUtil::getFiles($cache_dir, true, false, array('tpl'), null, false);

        if ($template == null) {
            if ($expire == null) {
                foreach($cached_files as $cf) {
                    unlink(realpath($cf));
                }
            } else {
                // actions for when $exp_time is not null
            }
        } else {
            if ($expire == null) {
                foreach($cached_files as $cf) {
                    if (strpos($cf, $template) !== false) {
                        unlink(realpath($cf));
                    }
                }
            } else {
                // actions for when $expire is not null
            }
        }

        return true;
    }

    /**
     * Clear all cached templates.
     *
     * @param string $exp_time Expire time.
     *
     * @return boolean Results of {@link smarty_core_rm_auto()}.
     */
    public function clear_all_cache($exp_time = null)
    {
        return $this->clear_cache(null, null, null, $exp_time);
    }

    /**
     * Clear all compiled templates.
     *
     * @param string $exp_time Expire time.
     *
     * @return boolean Results of {@link smarty_core_rm_auto()}.
     */
    public function clear_compiled($exp_time = null)
    {
        $compile_dir = $this->compile_dir;

        $compiled_files = FileUtil::getFiles($compile_dir, true, false, array('php', 'inc'), null, false);

        if ($exp_time == null) {
            foreach($compiled_files as $cf) {
                unlink(realpath($cf));
            }
        } else {
            // actions for when $exp_time is not null
        }

        return true;
    }

    /**
     * Assign variable to template.
     *
     * @param string $key Variable name.
     * @param mixed  $value   Value.
     *
     * @return Zikula_View
     */
    function assign($key, $value = null)
    {
        $this->_assign_check($key);
        parent::assign($key, $value);
        return $this;
    }

    /**
     * Assign variable to template by reference.
     *
     * @param string $key   Variable name.
     * @param mixed  $value Value.
     *
     * @return Zikula_View
     */
    function assign_by_ref($key, &$value)
    {
        $this->_assign_check($key);
        parent::assign_by_ref($key, $value);
        return $this;
    }

    /**
     * Prevent certain variables from being overwritten.
     *
     * @return void
     */
    protected function _assign_check($key)
    {
        if (is_array($key)) {
            foreach ($key as $v) {
                self::_assign_check($v);
            }
            return;
        }
        switch (strtolower($key))
        {
            case 'zikula_view':
            case 'servicemanager':
            case 'eventmanager':
                $this->trigger_error(__f('%s is a protected template variable and may not be assigned', $key));
                break;
        }
    }

    /**
     * Set Caching.
     *
     * @param boolean $boolean True or false.
     *
     * @return Zikula_View
     */
    public function setCaching($boolean)
    {
        $this->caching = (bool)$boolean;
        return $this;
    }

    /**
     * Set cache lifetime.
     *
     * @param integer $time Lifetime in seconds.
     *
     * @return Zikula_View
     */
    public function setCache_lifetime($time)
    {
        $this->cache_lifetime = $time;
        return $this;
    }

    /**
     * Set up paths for the template.
     *
     * This function sets the template and the config path according
     * to where the template is found (Theme or Module directory)
     *
     * @param string $template The template name.
     *
     * @return void
     */
    public function _setup_template($template)
    {
        // default directory for templates
        $this->template_dir = $this->get_template_path($template);
        //echo $this->template_dir . '<br>';
        $this->config_dir = $this->template_dir . '/config';
    }

    /**
     * add a plugins dir to _plugin_dir array
     *
     * This function takes  module name and adds two path two the plugins_dir array
     * when existing
     *
     * @param string $module Well known module name.
     *
     * @return void
     */
    private function _add_plugins_dir($module)
    {
        if (empty($module)) {
            return;
        }

        $modinfo = ModUtil::getInfoFromName($module);
        if (!$modinfo) {
            return;
        }

        $modpath = ($modinfo['type'] == ModUtil::TYPE_SYSTEM) ? 'system' : 'modules';
        $this->addPluginDir("$modpath/$modinfo[directory]/templates/plugins");

        if (System::isLegacyMode()) {
            $this->addPluginDir("$modpath/$modinfo[directory]/pntemplates/plugins");
        }
    }

    /**
     * Add core data to the template.
     *
     * This function adds some basic data to the template depending on the
     * current user and the Zikula settings.
     *
     * @return Zikula_View
     */
    public function add_core_data()
    {
        $core = array();
        $core['version_num'] = System::VERSION_NUM;
        $core['version_id'] = System::VERSION_ID;
        $core['version_sub'] = System::VERSION_SUB;
        $core['logged_in'] = UserUtil::isLoggedIn();
        $core['language'] = $this->language;
        $core['themeinfo'] = $this->themeinfo;

        // add userdata
        $core['user'] = UserUtil::getVars(SessionUtil::getVar('uid'));

        // add modvars of current modules
        foreach ($this->module as $module => $dummy) {
            $core[$module] = ModUtil::getVar($module);
        }

        // add mod vars of all modules supplied as parameter
        $modulenames = func_get_args();
        foreach ($modulenames as $modulename) {
            // if the modulename is empty do nothing
            if (!empty($modulename) && !is_array($modulename) && !array_key_exists($modulename, $this->module)) {
                // check if user wants to have config
                if ($modulename == ModUtil::CONFIG_MODULE) {
                    $ZConfig = ModUtil::getVar(ModUtil::CONFIG_MODULE);
                    foreach ($ZConfig as $key => $value) {
                        // gather all config vars
                        $core['ZConfig'][$key] = $value;
                    }
                } else {
                    $core[$modulename] = ModUtil::getVar($modulename);
                }
            }
        }

        // Module vars
        $this->assign('zcore', $core);
        if (System::isLegacyMode()) {
            $this->assign('pncore', $core);
        }
        return $this;
    }

    public function getTemplate_dir()
    {
        return $this->template_dir;
    }

    public function setTemplate_dir($template_dir)
    {
        $this->template_dir = $template_dir;
    }

    public function getCompile_dir()
    {
        return $this->compile_dir;
    }

    public function setCompile_dir($compile_dir)
    {
        $this->compile_dir = $compile_dir;
    }

    public function getConfig_dir()
    {
        return $this->config_dir;
    }

    public function setConfig_dir($config_dir)
    {
        $this->config_dir = $config_dir;
    }

    public function getPlugins_dir()
    {
        return $this->plugins_dir;
    }

    public function setPlugins_dir($plugins_dir)
    {
        $this->plugins_dir = $plugins_dir;
    }

    public function getDebugging()
    {
        return $this->debugging;
    }

    public function setDebugging($debugging)
    {
        $this->debugging = $debugging;
    }

    public function getError_reporting()
    {
        return $this->error_reporting;
    }

    public function setError_reporting($error_reporting)
    {
        $this->error_reporting = $error_reporting;
    }

    public function getDebug_tpl()
    {
        return $this->debug_tpl;
    }

    public function setDebug_tpl($debug_tpl)
    {
        $this->debug_tpl = $debug_tpl;
    }

    public function getDebugging_ctrl()
    {
        return $this->debugging_ctrl;
    }

    public function setDebugging_ctrl($debugging_ctrl)
    {
        $this->debugging_ctrl = $debugging_ctrl;
    }

    public function getCompile_check()
    {
        return $this->compile_check;
    }

    public function getForce_compile()
    {
        return $this->force_compile;
    }

    public function setForce_compile($force_compile)
    {
        $this->force_compile = $force_compile;
    }

    public function getCaching()
    {
        return $this->caching;
    }

    public function getCache_dir()
    {
        return $this->cache_dir;
    }

    public function setCache_dir($cache_dir)
    {
        $this->cache_dir = $cache_dir;
    }

    public function getCache_lifetime()
    {
        return $this->cache_lifetime;
    }

     public function getCache_modified_check()
    {
        return $this->cache_modified_check;
    }

    public function setCache_modified_check($cache_modified_check)
    {
        $this->cache_modified_check = $cache_modified_check;
    }

    public function getPhp_handling()
    {
        return $this->php_handling;
    }

    public function setPhp_handling($php_handling)
    {
        $this->php_handling = $php_handling;
    }

    public function getSecurity()
    {
        return $this->security;
    }

    public function setSecurity($security)
    {
        $this->security = $security;
    }

    public function getSecure_dir()
    {
        return $this->secure_dir;
    }

    public function setSecure_dir($secure_dir)
    {
        $this->secure_dir = $secure_dir;
    }

    public function getSecurity_settings()
    {
        return $this->security_settings;
    }

    public function setSecurity_settings($security_settings)
    {
        $this->security_settings = $security_settings;
    }

    public function getTrusted_dir()
    {
        return $this->trusted_dir;
    }

    public function setTrusted_dir($trusted_dir)
    {
        $this->trusted_dir = $trusted_dir;
    }

    public function getLeft_delimiter()
    {
        return $this->left_delimiter;
    }

    public function setLeft_delimiter($left_delimiter)
    {
        $this->left_delimiter = $left_delimiter;
    }

    public function getRight_delimiter()
    {
        return $this->right_delimiter;
    }

    public function setRight_delimiter($right_delimiter)
    {
        $this->right_delimiter = $right_delimiter;
    }

    public function getRequest_vars_order()
    {
        return $this->request_vars_order;
    }

    public function setRequest_vars_order($request_vars_order)
    {
        $this->request_vars_order = $request_vars_order;
    }

    public function getRequest_use_auto_globals()
    {
        return $this->request_use_auto_globals;
    }

    public function setRequest_use_auto_globals($request_use_auto_globals)
    {
        $this->request_use_auto_globals = $request_use_auto_globals;
    }

    public function getCompile_id()
    {
        return $this->compile_id;
    }

    public function setCompile_id($compile_id)
    {
        $this->compile_id = $compile_id;
    }

    public function getUse_sub_dirs()
    {
        return $this->use_sub_dirs;
    }

    public function setUse_sub_dirs($use_sub_dirs)
    {
        $this->use_sub_dirs = $use_sub_dirs;
    }

    public function getDefault_modifiers()
    {
        return $this->default_modifiers;
    }

    public function setDefault_modifiers($default_modifiers)
    {
        $this->default_modifiers = $default_modifiers;
    }

    public function getDefault_resource_type()
    {
        return $this->default_resource_type;
    }

    public function setDefault_resource_type($default_resource_type)
    {
        $this->default_resource_type = $default_resource_type;
    }

    public function getCache_handler_func()
    {
        return $this->cache_handler_func;
    }

    public function setCache_handler_func($cache_handler_func)
    {
        $this->cache_handler_func = $cache_handler_func;
    }

    public function getAutoload_filters()
    {
        return $this->autoload_filters;
    }

    public function setAutoload_filters($autoload_filters)
    {
        $this->autoload_filters = $autoload_filters;
    }

    public function getConfig_overwrite()
    {
        return $this->config_overwrite;
    }

    public function setConfig_overwrite($config_overwrite)
    {
        $this->config_overwrite = $config_overwrite;
    }

    public function getConfig_booleanize()
    {
        return $this->config_booleanize;
    }

    public function setConfig_booleanize($config_booleanize)
    {
        $this->config_booleanize = $config_booleanize;
    }

    public function getConfig_read_hidden()
    {
        return $this->config_read_hidden;
    }

    public function setConfig_read_hidden($config_read_hidden)
    {
        $this->config_read_hidden = $config_read_hidden;
    }

    public function getConfig_fix_newlines()
    {
        return $this->config_fix_newlines;
    }

    public function setConfig_fix_newlines($config_fix_newlines)
    {
        $this->config_fix_newlines = $config_fix_newlines;
    }

    public function getDefault_template_handler_func()
    {
        return $this->default_template_handler_func;
    }

    public function setDefault_template_handler_func($default_template_handler_func)
    {
        $this->default_template_handler_func = $default_template_handler_func;
    }

    public function getCompiler_file()
    {
        return $this->compiler_file;
    }

    public function setCompiler_file($compiler_file)
    {
        $this->compiler_file = $compiler_file;
    }

    public function getCompiler_class()
    {
        return $this->compiler_class;
    }

    public function setCompiler_class($compiler_class)
    {
        $this->compiler_class = $compiler_class;
    }

    public function get_tpl_vars()
    {
        return $this->_tpl_vars;
    }

    public function set_tpl_vars($_tpl_vars)
    {
        $this->_tpl_vars = $_tpl_vars;
    }

    public function get_compile_id()
    {
        return $this->_compile_id;
    }

    public function set_compile_id($_compile_id)
    {
        $this->_compile_id = $_compile_id;
    }

    public function get_cache_info()
    {
        return $this->_cache_info;
    }

    public function set_cache_info($_cache_info)
    {
        $this->_cache_info = $_cache_info;
    }

    public function get_file_perms()
    {
        return $this->_file_perms;
    }

    public function set_file_perms($_file_perms)
    {
        $this->_file_perms = $_file_perms;
    }

    public function get_dir_perms()
    {
        return $this->_dir_perms;
    }

    public function set_dir_perms($_dir_perms)
    {
        $this->_dir_perms = $_dir_perms;
    }

    public function get_reg_objects()
    {
        return $this->_reg_objects;
    }

    public function set_reg_objects($_reg_objects)
    {
        $this->_reg_objects = $_reg_objects;
    }

    public function get_plugins()
    {
        return $this->_plugins;
    }

    public function set_plugins($_plugins)
    {
        $this->_plugins = $_plugins;
    }

    public function get_cache_serials()
    {
        return $this->_cache_serials;
    }

    public function set_cache_serials($_cache_serials)
    {
        $this->_cache_serials = $_cache_serials;
    }

    public function get_cache_include()
    {
        return $this->_cache_include;
    }

    public function set_cache_include($_cache_include)
    {
        $this->_cache_include = $_cache_include;
    }

    public function get_cache_including()
    {
        return $this->_cache_including;
    }

    public function set_cache_including($_cache_including)
    {
        $this->_cache_including = $_cache_including;
    }
}

/**
 * Smarty block function to prevent template parts from being cached
 *
 * @param array       $param   Tag parameters.
 * @param string      $content Block content.
 * @param Zikula_View $view Reference to smarty instance.
 *
 * @return string
 **/
function Zikula_View_block_nocache($param, $content, $view)
{
    return $content;
}

/**
 * Smarty resource function to determine correct path for template inclusion.
 *
 * For more information about parameters see http://smarty.php.net/manual/en/template.resources.php.
 *
 * @param string      $tpl_name    Template name.
 * @param string      &$tpl_source Template source.
 * @param Zikula_View $view     Reference to Smarty instance.
 *
 * @access private
 * @return boolean
 */
function z_get_template($tpl_name, &$tpl_source, $view)
{
    // determine the template path and store the template source

    // get path, checks also if tpl_name file_exists and is_readable
    $tpl_path = $view->get_template_path($tpl_name);

    if ($tpl_path !== false) {
        $tpl_source = file_get_contents(DataUtil::formatForOS($tpl_path . '/' . $tpl_name));
        if ($tpl_source !== false) {
            return true;
        }
    }

    return LogUtil::registerError(__f('Error! The template [%1$s] is not available in the [%2$s] module.', array(
            $tpl_name,
            $view->toplevelmodule)));
}

/**
 * Get the timestamp of the last change of the $tpl_name file.
 *
 * @param string      $tpl_name       Template name.
 * @param string      &$tpl_timestamp Template timestamp.
 * @param Zikula_View $view           Reference to Smarty instance.
 *
 * @return boolean
 */
function z_get_timestamp($tpl_name, &$tpl_timestamp, $view)
{
    // get path, checks also if tpl_name file_exists and is_readable
    $tpl_path = $view->get_template_path($tpl_name);

    if ($tpl_path !== false) {
        $tpl_timestamp = filemtime(DataUtil::formatForOS($tpl_path . '/' . $tpl_name));
        if ($tpl_timestamp !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Checks whether or not a template is secure.
 *
 * @param string      $tpl_name Template name.
 * @param Zikula_View $view     Reference to Smarty instance.
 *
 * @return boolean
 */
function z_get_secure($tpl_name, $view)
{
    // assume all templates are secure
    return true;
}

/**
 * Whether or not the template is trusted.
 *
 * @param string      $tpl_name Template name.
 * @param Zikula_View $view     Reference to Smarty instance.
 *
 * @return void
 */
function z_get_trusted($tpl_name, $view)
{
    // not used for templates
    return;
}

/**
 * Callback function for preg_replace_callback.
 *
 * Allows the use of {{ and }} as delimiters within certain tags,
 * even if they use { and } as block delimiters.
 *
 * @param array $matches The $matches array from preg_replace_callback, containing the matched groups.
 *
 * @return string The replacement string for the match.
 */
function z_prefilter_add_literal_callback($matches)
{
    $tagOpen = $matches[1];
    $script = $matches[3];
    $tagClose = $matches[4];

    if (System::hasLegacyTemplates()) {
        $script = str_replace('<!--[', '{{', str_replace(']-->', '}}', $script));
    }
    $script = str_replace('{{', '{/literal}{', str_replace('}}', '}{literal}', $script));

    return $tagOpen . '{literal}' . $script . '{/literal}' . $tagClose;
}

/**
 * Prefilter for tags that might contain { or } as block delimiters.
 *
 * Such as <script> or <style>. Allows the use of {{ and }} as smarty delimiters,
 * even if the language uses { and } as block delimters. Adds {literal} and
 * {/literal} to the specified opening and closing tags, and converts
 * {{ and }} to {/literal}{ and }{literal}.
 *
 * Tags affected: <script> and <style>.
 *
 * @param string      $tpl_source The template's source prior to prefiltering.
 * @param Zikula_View $view       A reference to the Zikula_View object.
 *
 * @return string The prefiltered template contents.
 */
function z_prefilter_add_literal($tpl_source, $view)
{
    return preg_replace_callback('`(<(script|style)[^>]*>)(.*?)(</\2>)`s', 'z_prefilter_add_literal_callback', $tpl_source);
}

/**
 * Prefilter for gettext parameters.
 *
 * @param string      $tpl_source The template's source prior to prefiltering.
 * @param Zikula_View $view       A reference to the Zikula_View object.
 *
 * @return string The prefiltered template contents.
 */
function z_prefilter_gettext_params($tpl_source, $view)
{
    $tpl_source = (preg_replace_callback('#\{(.*?)\}#', create_function('$m', 'return z_prefilter_gettext_params_callback($m);'), $tpl_source));
    return $tpl_source;
}

/**
 * Callback function for self::z_prefilter_gettext_params().
 *
 * @param string $m Tag token.
 *
 * @return string
 */
function z_prefilter_gettext_params_callback($m)
{
    $m[1] = preg_replace('#__([a-zA-Z0-9]+=".*?(?<!\\\)")#', '$1|gt:$zikula_view', $m[1]);
    $m[1] = preg_replace('#__([a-zA-Z0-9]+=\'.*?(?<!\\\)\')#', '$1|gt:$zikula_view', $m[1]);
    return '{' . $m[1] . '}';
}

/**
 * Prefilter for legacy tag delemitters.
 *
 * @param string      $source The template's source prior to prefiltering.
 * @param Zikula_View $view   A reference to the Zikula_View object.
 *
 * @return string The prefiltered template contents.
 */
function z_prefilter_legacy($source, $view)
{
    // rewrite the old delimiters to new.
    $source = str_replace('<!--[', '{', str_replace(']-->', '}', $source));

    // handle old plugin names and return.
    return preg_replace_callback('#\{(.*?)\}#', create_function('$m', 'return z_prefilter_legacy_callback($m);'), $source);
}

/**
 * Callback function for self::z_prefilter_legacy().
 *
 * @param string $m Tag token.
 *
 * @return string
 */
function z_prefilter_legacy_callback($m)
{
    $m[1] = str_replace('|pndate_format', '|dateformat', $m[1]);
    $m[1] = str_replace('pndebug', 'zdebug', $m[1]);
    $m[1] = preg_replace('#^(\s*)(/{0,1})pn([a-zA-Z0-9_]+)(\s*|$)#', '$1$2$3$4', $m[1]);
    $m[1] = preg_replace('#\|pn#', '|', $m[1]);
    return "{{$m[1]}}";
}
