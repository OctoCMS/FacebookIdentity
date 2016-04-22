<?php

namespace Octo\FacebookIdentity;

class Module extends \Octo\Module
{
    protected function getName()
    {
        return 'FacebookIdentity';
    }

    protected function getPath()
    {
        return dirname(__FILE__) . '/';
    }

    public function init()
    {
        $app = $this->config->get('Octo');
        //$app['bypass_auth']['facebook-identity'] = ['auth'];
        $this->config->set('Octo', $app);

        return parent::init();
    }
}
