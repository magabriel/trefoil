# Illustrations test chapter 4

Illustrations markup should be ignored inside `code` blocks.

This is a Markdown code block containing an illustration. It should not be rendered.

- - -
~~~.markdown
{@ ========== illustration_begin("Illustration 1 in chapter 4 with class=border-blue", ".border-blue" @}

##### H5 heading inside the 1st illustration of chapter 4

Text:

Fusce fermentum sollicitudin finibus. Sed ut finibus nisi. 
Proin at maximus tellus. In tempor quam non elementum consectetur. 
 
{@ ==========  illustration_end() @}
~~~
- - -

This is a normal code block:

- - -
~~~.php
<?php
// trefoil\src\Trefoil\Plugins\AwesomePlugin.php
namespace Trefoil\Plugins;

use ...;

class AwesomePlugin extends BasePlugin implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        //....
    }

    //...
}
~~~
- - -
