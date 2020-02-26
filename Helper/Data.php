<?php
/**
 * @Author: nguyen
 * @Date:   2020-02-12 14:01:01
 * @Last Modified by:   nguyen
 * @Last Modified time: 2020-02-17 19:42:18
 */

namespace Magepow\Lazyload\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var string
     */
    protected $pageConfig;

    /**
     * @var array
     */
    protected $lazyloadConfig;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\View\Page\Config $pageConfig
    )
    {
        parent::__construct($context);
        $this->pageConfig = $pageConfig;
        $this->lazyloadConfig = $this->getConfig('lazyload');
    }

    public function getConfig($cfg='')
    {
        if($cfg) return $this->scopeConfig->getValue( $cfg, \Magento\Store\Model\ScopeInterface::SCOPE_STORE );
        return $this->scopeConfig;
    }

    public function getLazyloadConfig($cfg='')
    {
        $config = explode('/', $cfg);
        $value = $this->lazyloadConfig;
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
