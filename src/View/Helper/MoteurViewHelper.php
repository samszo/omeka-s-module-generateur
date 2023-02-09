<?php declare(strict_types=1);
namespace Generateur\View\Helper;

use Generateur\Generateur\Moteur;
use Laminas\View\Helper\AbstractHelper;

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
