Plugins
=======

Plugins are the core of `easybook` extensibilty. But I found its implementation a bit lacking on the side of flexibylity, so the first task was to get a more flexible plugin's system. And then, implement as many new features as possible in the form of new plugins. 

{{ itemtoc() }}

Plugin system enhancements
--------------------------

The things that were troubling me most and I wanted to fix (or, rather, "enhance") were: 

- There is no way of reusing user-created plugins other than copying the code from one book to another.
- Plugins are not namespaced, precluding autoloading and extensibility.

### Namespaces for plugins

All of `trefoil` plugins are namespaced, in the namespace (you guess) `Trefoil\Plugins`. 

So a typical plugin now looks like:

~~~~~~~~~~~~~~~~~~~~ .php
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

~~~~~~~~~~~~~~~~~~~~

`BasePlugin` is a base class that provides some utility methods to other plugins:

~~~~~~~~~~~~~~~~~~~~ .php
<?php
// trefoil\src\Trefoil\Plugins\BasePlugin.php
namespace Trefoil\Plugins;

use Trefoil\Util\Toolkit;
use Easybook\Events\BaseEvent;

/**
 * Base class for all plugins
 *
 */
abstract class BasePlugin
{
    protected $app;
    protected $output;
    protected $edition;
    protected $format;
    protected $theme;
    protected $item;

    /**
     * Do some initialization tasks.
     * Must be called explicitly for each plugin at the begining
     * of each event handler method.
     *
     * @param BaseEvent $event
     */
    public function init(BaseEvent $event)
    {
        $this->event = $event;
        $this->app = $event->app;
        $this->output = $this->app->get('console.output');
        $this->edition = $this->app['publishing.edition'];
        $this->format = Toolkit::getCurrentFormat($this->app);
        $this->theme = ucfirst($this->app->edition('theme'));
        $this->item = $event->getItem();
    }

    //... more methods

}

~~~~~~~~~~~~~~~~~~~~

In the `init()` method a bunch of useful properties are defined so they are available for the plugins.


The Trefoil plugins
-------------------

Suspendisse et pretium tellus. Nam suscipit ut lectus quis consequat. Sed ut neque odio. Phasellus nec erat id sem fermentum ornare id vel libero. Mauris sed felis in est tincidunt imperdiet nec vel velit. Sed dictum tincidunt nisi sed rutrum. Quisque in dapibus neque. Sed et nisl quis justo sagittis sodales euismod sed mi. Aenean accumsan sit amet erat eu facilisis. Proin facilisis, mi in imperdiet varius, libero tortor eleifend sem, pellentesque fringilla metus tellu sed erat. Ut id sapien vestibulum, posuere turpis non, semper diam. 
