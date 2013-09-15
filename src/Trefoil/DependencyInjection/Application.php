<?php

namespace Trefoil\DependencyInjection;

use Easybook\DependencyInjection\Application as EasybookApplication;

class Application extends EasybookApplication
{
    const MY_VERSION = '0.1 DEV';

    public function __construct()
    {
        parent::__construct();

        // -- global generic parameters ---------------------------------------
        $this['app.signature'] = substr($this['app.signature'], 0, -1)
            ."  <comment>+ trefoil</comment>\n";

    }

    public final function getMyVersion()
    {
        return static::MY_VERSION;
    }
}
