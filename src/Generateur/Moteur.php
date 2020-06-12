<?php
 /*
 * @author Samuel Szoniecky
 * @category   Zend
 * @package library\Flux\Outils
 * @license https://creativecommons.org/licenses/by-sa/2.0/fr/ CC BY-SA 2.0 FR
 * @version  $Id:$
 */
namespace Generateur\Generateur;

class Moteur {

    /**
     * @var array
     */
    var $temps = array(
        array('num'=>1,'lib'=>'indicatif présent'),
        array('num'=>2,'lib'=>'indicatif imparfait'),
        array('num'=>3,'lib'=>'passé simple'),
        array('num'=>4,'lib'=>'futur simple'),
        array('num'=>5,'lib'=>'conditionnel présent'),
        array('num'=>6,'lib'=>'subjonctif présent'),
        array('num'=>8,'lib'=>'participe présent'),
        array('num'=>9,'lib'=>'infinitif'),
    );
    /**
     * @var array
     */
    var $termType = array('a','v','m','s');
    /**
     * @var string
     */
    var $sepType = '-';
    /**
     * @var string
     */
    var $sepTerm = '_';
    /**
     * @var integer
     */
    var $maxNivReseau = 2;
    /**
     * @var o:Item
     */
    var $oItem;


    /**
     * Construct Moteur
     *
     * @param o:Item $oItem The item source of generate
     */
    public function __construct($oItem)
    {
        $this->oItem = $oItem;
    }

    /**
     * The set of all items in the library
     *
     * @param array $params
     * @return string
     */
    public function items(array $params = [])
    {
        return sprintf('%s%s?%s', self::URI, self::API, $this->getParams($params));
    }

    /**
     * The set of all items in the library via outliner
     *
     * @param array $params
     * @return string
     */
    public function itemsOutliner(array $params = [])
    {        
        return sprintf('%s?%s', self::URIOUT, $this->getParams($params));
    }

    /**
     * The URL to an item file.
     *
     * @param string $itemKey
     * @param array $params
     * @return string
     */
    public function itemFile($itemKey, array $params = [])
    {
        return sprintf('%s/%s/%s/items/%s/file%s', self::BASE, $this->type,
            $this->id, $itemKey, $this->getParams($params));
    }


    /**
     * Build and return a URL query string
     *
     * @param array $params
     * @return string
     */
    public function getParams(array $params)
    {
        $p = 'key='.$this->key.'&user='.$this->user;
        if (empty($params)) {
            return $p;
        }
        $params['key']=$this->key;
        $params['user']=$this->user;
        return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}
