<?php
namespace Generateur\View\Helper;

use Zend\View\Helper\AbstractHelper;
use Generateur\Generateur\Moteur;

class MoteurViewHelper extends AbstractHelper
{
    public function __construct($api)
    {
      $this->api = $api;
    }

    public function __invoke($cache, $log)
    {
        return new Moteur($cache, $this->api, $log);
    }
}
