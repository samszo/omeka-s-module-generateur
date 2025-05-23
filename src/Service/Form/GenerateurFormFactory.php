<?php
namespace Generateur\Service\Form;

use Generateur\Form\GenerateurForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class GenerateurFormFactory implements FactoryInterface
{    
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new GenerateurForm(null, !$options ? [] : $options);
        $form->setApi($services->get('ViewHelperManager')->get('api'));
        return $form;
    }
}
