# AutoIndexPlugin test compatibilty

## Compatibilty with code highlighting

This is some code with terms which should be ignored:

~~~~~~~~~~~~~~~~~~~~ .php

<?php
// trefoil\src\Trefoil\Plugins\AwesomePlugin.php
namespace Trefoil\Plugins;

use ...;

// term0, term1

class AwesomePlugin extends BasePlugin implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        //....
    }

    //...
}

~~~~~~~~~~~~~~~~~~~~ 

