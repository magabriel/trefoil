# QualityControl test

## Missing images

![This is image 1 (png)](image1.png)

![This is image 2 (jpg)](image2.jpg)

![This is image 3 (jpeg)](image3.jpeg)

## Emphasis marks not processed

No error: 

1. Lorem ipsum dolor sit amet, _consectetur_ adipiscing elit. Maecenas non venenatis turpis, nec bibendum urna. 

2. Curabitur **vitae** hendrerit elit. Quisque nec velit ut nunc pellentesque tincidunt. 

Errors:

1. Lorem ipsum dolor sit amet, consectetur _ adipiscing_ elit. Maecenas non venenatis turpis, nec bibendum urna. 

2. Curabitur** vitae hendrerit **elit. Quisque nec velit ut nunc*pellentesque tincidunt. 

More errors:

Donec ac fermentum eros._ Nulla_ quis sapien vitae mauris sagittis consequat. Phasellus lectus odio, fringilla eget leo et, venenatis sodales ante. 

_ Duis hendrerit molestie massa in euismod. Maecenas quis varius felis.


Code must not be flaged as error:

~~~ .php
<?php
// trefoil\src\Trefoil\Plugins\AwesomePlugin.php
namespace Trefoil\Plugins;

/* This is a comment */

use ...;

/** This is another comment 
 */

class AwesomePlugin extends BasePlugin implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        //....
    }

    //...
}
~~~
