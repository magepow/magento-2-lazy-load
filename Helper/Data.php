<?php
/**
 * @Author: nguyen
 * @Date:   2020-02-12 14:01:01
 * @Last Modified by:   Alex Dong
 * @Last Modified time: 2020-03-15 15:38:55
 */

namespace Magepow\Lazyload\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    const CONFIG_MODULE = 'magepow_lazyload';

    /**
     * @var string
     */
    protected $pageConfig;

    /**
     * @var array
     */
    protected $configModule;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\View\Page\Config $pageConfig
    )
    {
        parent::__construct($context);
        $this->pageConfig = $pageConfig;
        $this->configModule = $this->getConfig(self::CONFIG_MODULE);
    }

    public function getConfig($cfg='')
    {
        if($cfg) return $this->scopeConfig->getValue( $cfg, \Magento\Store\Model\ScopeInterface::SCOPE_STORE );
        return $this->scopeConfig;
    }

    public function getConfigModule($cfg='', $value=null)
    {
        $config = explode('/', $cfg);
        if( !$cfg ) $value = $this->configModule;
        foreach ($config as $key) {
            if(isset($value[$key])) $value = $value[$key]; 
        }
        return $value;
    }

    public function addBodyClass($class)
    {
        $this->pageConfig->addBodyClass($class);
    }

    /**
     * @return string
     */
    public function getPageLayout()
    {
        return $this->pageConfig->getPageLayout();
    }

}
