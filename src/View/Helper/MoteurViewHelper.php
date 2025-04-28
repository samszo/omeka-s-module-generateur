<?php
namespace Generateur\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Generateur\Generateur\Moteur;

class MoteurViewHelper extends AbstractHelper
{
  protected $api;
  protected $logger;
  protected $sql;

    public function __construct($api, $logger, $sql)
    {
        $this->logger = $logger;
        $this->api = $api;
        $this->sql = $sql;
    }
    
    public function __invoke($cache,$log)
    {
        return new Moteur(true, $this->api, $this->logger, $this->sql);
    }
}
