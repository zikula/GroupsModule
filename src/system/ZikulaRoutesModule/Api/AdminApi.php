<?php
/**
 * Routes.
 *
 * @copyright Zikula contributors (Zikula)
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 * @author Zikula contributors <support@zikula.org>.
 * @link http://www.zikula.org
 * @link http://zikula.org
 * @version Generated by ModuleStudio 0.6.2 (http://modulestudio.de).
 */

namespace Zikula\RoutesModule\Api;

use ModUtil;
use SecurityUtil;
use Zikula\RoutesModule\Api\Base\AdminApi as BaseAdminApi;

/**
 * This is the Admin api helper class.
 */
class AdminApi extends BaseAdminApi
{
    public function getLinks()
    {
        $links = parent::getLinks();

        if (SecurityUtil::checkPermission($this->name . ':Route:', '::', ACCESS_ADMIN)) {
            $links[] = array('url' => $this->serviceManager->get('router')->generate('zikularoutesmodule_route_reload', array('lct' => 'admin')),
                'text' => $this->__('Reload routes'),
                'title' => $this->__('Reload routes'));
            $links[] = array('url' => $this->serviceManager->get('router')->generate('zikularoutesmodule_route_renew', array('lct' => 'admin')),
                'text' => $this->__('Reload multilingual routing settings'),
                'title' => $this->__('Reload multilingual routing settings'));
            $links[] = array('url' => $this->serviceManager->get('router')->generate('zikularoutesmodule_route_dumpjsroutes', array('lct' => 'admin')),
                'text' => $this->__('Dump exposed js routes to file'),
                'title' => $this->__('Dump exposed js routes to file'));
        }

        return $links;
    }

    /**
     * Reloads the multilingual routing settings by reading system variables and checking installed languages.
     *
     * @param array $args No arguments available.
     *
     * @return bool
     */
    public function reloadMultilingualRoutingSettings($args)
    {
        unset($args);

        $defaultLocale = \System::getVar('language_i18n'); //$this->getContainer()->getParameter('locale');
        $installedLanguages = \ZLanguage::getInstalledLanguages();
        $isRequiredLangParameter = \System::getVar('languageurl', 0);
        $configDumper = $this->get('zikula.dynamic_config_dumper');
        $configDumper->setConfiguration('jms_i18n_routing',
            array(
                'default_locale' => $defaultLocale,
                'locales'        => $installedLanguages,
                'strategy'       => $isRequiredLangParameter ? 'prefix' : 'prefix_except_default'
            )
        );

        $cacheClearer = $this->get('zikula.cache_clearer');
        $cacheClearer->clear('symfony.config');

        return true;
    }
}
