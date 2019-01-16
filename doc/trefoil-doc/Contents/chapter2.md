# Plugin system enhancements

Plugins are the core of **easybook** 
[extensibilty](https://github.com/javiereguiluz/easybook/blob/master/doc/easybook-doc-en/Contents/09-plugins.md). 
But considerably more flexibility was needed in order to allow implementation 
of all the needed functionality, so the first task was to extend the plugins 
system.  
 
The first things to be enhanced were: 

- There are no way of reusing user-created plugins other than copying
  the code from one book to another.

- Plugins are not namespaced, precluding autoloading and extensibility.

{{ itemtoc() }}

## Namespaces for plugins

All of `trefoil` plugins are namespaced. The *optional* plugins (more on
that later) are under the namespace (you guess) `Trefoil\Plugins\Optional`. 

So a typical plugin now looks like:

~~~ .php
<?php
// trefoil\src\Trefoil\Plugins\Optional\AwesomePlugin.php
namespace Trefoil\Plugins\Optional;

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

`BasePlugin` is a base class that provides some utility methods and
properties to plugins:

~~~ .php
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
~~~

The `init()` method defines a bunch of useful properties to make them
available for the plugins.


## Selectively enabling optional plugins

**trefoil** introduces the concept of *optional* plugins: each book's 
edition can enable only certain plugins (**easybook** standard plugins,
on the other hand, are always enabled).

~~~.yaml
book:
    ....
    editions:
        <edition-name>
            plugins:
                enabled: [ plugin1, plugin2, ...]
                options:
                    ... 
~~~ 

`enabled` is a list of all the enabled plugins without the `Plugin` suffix,
so if you wanted to enable plugins `DropCapsPlugin` and `TableExtraPlugin` 
for edition `ebook` you would write:

~~~.yaml
book:
    ....
    editions:
        ebook:
            plugins:
                enabled: [ DropCaps, TableExtra ]
~~~ 
 


