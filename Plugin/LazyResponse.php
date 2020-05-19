<?php
/**
 * @Author: nguyen
 * @Date:   2020-02-12 14:01:01
 * @Last Modified by:   Alex Dong
 * @Last Modified time: 2020-05-14 10:00:26
 */

namespace Magepow\Lazyload\Plugin;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http;
use Magepow\Lazyload\Helper\Data;

class LazyResponse
{
    public $request;

    public $helper;

    public $content;

    public $isJson;

    public $exclude = [];

    public $placeholder = 'data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22' . '$width' . '%22%20height%3D%22' . '$height' . '%22%20viewBox%3D%220%200%20225%20265%22%3E%3C%2Fsvg%3E';

    public function __construct(
        RequestInterface $request,
        Data $helper,
        array $data = []
    ) 
    {    
        $this->request = $request;
        $this->helper = $helper;

    }

    /**
     * @param Http $subject
     * @return void
     */
    public function beforeSendResponse(Http $response)
    {
        if( !$this->helper->getConfigModule('general/enabled') ) return;

        if($this->request->isXmlHttpRequest()){
            /* request is ajax */
            $lazyAjax = $this->helper->getConfigModule('general/lazy_ajax');
            if( !$lazyAjax ) return;
            $contentType = $response->getHeader('Content-Type');
            if( $contentType && $contentType->getMediaType() == 'application/json' ) {
                $this->isJson = true;
                // return; // break response type json
            }
        }

        $body = $response->getBody();
        $bodyClass = 'loading_img';
        $loadingBody = $this->helper->getConfigModule('general/loading_body');
        if($loadingBody)  $bodyClass .= ' loading_body';

        $body = $this->addBodyClass($body, $bodyClass);

        if(!$this->helper->getConfigModule('general/loading_img')) return;        

        $exclude = $this->helper->getConfigModule('general/exclude_img');
        // $exclude = 'product-image-photo';
        if($exclude){
            $exclude = str_replace(' ', '', $exclude);
            $this->exclude = explode(',', $exclude);
        }

        $placeholder = $this->helper->getConfigModule('general/placeholder');
        // $placeholder = false;
        $regex_block = $this->helper->getConfigModule('general/regex_block');
        // $regex_block = '';
        $body = $this->addLazyload($body, $placeholder, $regex_block );

        $body_includes = $this->helper->getConfigModule('general/body_includes');
        if($body_includes) $body = $this->addToBottomBody($body, $body_includes);

        $response->setBody($body);

    }

    public function addBodyClass( $content, $class )
    {
        // return preg_replace( '/<body([\s\S]*?)(?:class="(.*?)")([\s\S]*?)?([^>]*)>/', sprintf( '<body${1}class="%s ${2}"${3}>', $class ), $content );
        return preg_replace_callback(
            '/<body([\s\S]*?)(?:class="(.*?)")([\s\S]*?)?([^>]*)>/',
            function($match) use ($class) {
                if($match[2]){
                    return $lazy = str_replace('class="', 'class="' . $class . ' ', $match[0]); 
                }else {
                    return str_replace('<body ', '<body class="' . $class . '" ', $match[0]);
                }
            },
            $content
        );  
    }

    public function addLazyload( $content, $placeholder=false, $start=0 )
    {
        if($start && !is_numeric($start)) $start = strpos($content, $start);
        $html = '';
        if( $start ){
            $page = str_split($content, $start);
            $isFirst = true;
 
            foreach ($page as $key => $pg) {
                if(!$key){
                    $html .= $pg;
                }else {
                    if($placeholder) $pg = $this->addLazyloadPlaceholder($pg);
                    $html .= $this->addLazyloadAll($pg);
                }
                
            }
        }else {
            if($placeholder) $content = $this->addLazyloadPlaceholder($content);
            $html .= $this->addLazyloadAll($content);           
        }

        return $html;
    }

    /* Placeholder so keep layout original while loading */
    public function addLazyloadPlaceholder( $content, $addJs=false ) 
    {
        $placeholder = $this->placeholder;

        $content = preg_replace_callback_array(
            [
                '/<img([^>]+?)width=[\'"]?([^\'"\s>]+)[\'"]([^>]+?)height=[\'"]?([^\'"\s>]+)[\'"]?([^>]*)>/' => function ($match) use ($placeholder) {
                    $holder = str_replace(['$width', '$height'], [$match[2], $match[4]], $placeholder);
                    return $this->addLazyloadImage($match[0], $holder);
                },
                '/<img([^>]+?)height=[\'"]?([^\'"\s>]+)[\'"]([^>]+?)width=[\'"]?([^\'"\s>]+)[\'"]?([^>]*)>/' => function ($match) use ($placeholder) {
                    $holder = str_replace(['$width', '$height'], [$match[4], $match[2]], $placeholder);
                    return $this->addLazyloadImage($match[0], $holder);
                }
            ],
            $content
        );

        if($addJs) $content = $this->addLazyLoadJs($content);

        return $content;
    }

    public function isExclude($class)
    {
        if(is_string($class)) $class = explode(' ', $class);
        $excludeExist = array_intersect($this->exclude, $class);
        return !empty($excludeExist);
    }

    public function addLazyloadImage($content, $placeholder)
    {
        if($this->isJson) return  $this->addLazyloadImageJson($content, $placeholder);
        return preg_replace_callback(
            '/<img\s*.*?(?:class="(.*?)")?([^>]*)>/',
            function($match) use ($placeholder) {

                if(stripos($match[0], ' data-src="') !== false) return $match[0];

                if(stripos($match[0], ' class="') !== false){
                    if( $this->isExclude($match[1]) ) return $match[0];
                    $lazy = str_replace(' class="', ' class="lazyload ', $match[0]); 
                }else {
                    $lazy = str_replace('<img ', '<img class="lazyload" ', $match[0]);
                }

                /* break if exist data-src */
                // if(strpos($lazy, ' data-src="')) return $lazy;

                return str_replace(' src="', ' src="' .$placeholder. '" data-src="', $lazy);
            },
            $content
        );        
    }

    public function addLazyloadImageJson($content, $placeholder)
    {
        $placeholder = addslashes($placeholder); 
        return preg_replace_callback(
            '/<img\s*.*?(?:class=\\\"(.*?)\\\")?([^>]*)>/',
            function($match) use ($placeholder) {
                
                if(stripos($match[0], ' data-src=\"') !== false) return $match[0];

                if(stripos($match[0], ' class="') !== false){
                    if( $this->isExclude($match[1]) ) return $match[0];
                    $lazy = str_replace(' class=\"', ' class=\"lazyload ', $match[0]); 
                }else {
                    $lazy = str_replace('<img ', '<img class=\"lazyload\" ', $match[0]);
                }

                /* break if exist data-src */
                // if(strpos($lazy, ' data-src=\"')) return $lazy;

                return str_replace(' src=\"', ' src=\"' . $placeholder . '\" data-src=\"', $lazy);
            },
            $content
        );        
    }

    /* Not Placeholder so can't keep layout original while loading */
    public function addLazyloadAll( $content, $addJs=false ) 
    {
        $placeholder = str_replace(['$width', '$height'], [1, 1], $this->placeholder);

        $content = $this->addLazyloadImage($content, $placeholder);

        if($addJs) $content = $this->addLazyLoadJs($content);

        return $content;
    }

    /* Insert to Top body */
    public function addToTopBody( $content, $insert)
    {
        return $content = preg_replace_callback(
            '/<body([\s\S]*?)?([^>]*)>/',
            function($match) use ($insert) {
                return $match[0] . $insert;
            },
            $content
        );      
    }

    /* Insert to Bottom body */
    public function addToBottomBody( $content, $insert)
    {
        $content = str_ireplace('</body>', $insert . '</body>', $content);
        return $content;         
    }

    /* Add js to content */
    public function addLazyLoadJs( $content, $selector='img', $exclude='.loaded' )
    {
        $script = '<script type="text/javascript"> require(["jquery", "magepow/lazyload", "domReady!"], function($, lazyload){$(' . $selector .').not("' . $exclude . '").lazyload();});</script>';
        return $this->addToBottomBody($content, $script);
    }
}
