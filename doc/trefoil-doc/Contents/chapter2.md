# Plugin system enhancements

Plugins are the core of `easybook` [extensibilty](http://easybook-project.org/documentation/chapter-9.html). 
But its implementation is a bit lacking on the side of flexibility, so the first task was to get a more flexible plugin's system. And then, implement as many new features as possible in the form of new plugins. 
 
The first things to be fixed (or, rather, "enhanced") were: 

- There was no way of reusing user-created plugins other than copying the code from one book to another.
- Plugins are not namespaced, precluding autoloading and extensibility.

{{ itemtoc() }}

## Namespaces for plugins

All of `trefoil` plugins are namespaced, in the namespace (you guess) `Trefoil\Plugins`. 

So a typical plugin now looks like:

~~~ .php
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

`BasePlugin` is a base class that provides some utility methods and properties to plugins:

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

The `init()` method defines a bunch of useful properties to make them available for the plugins.



## Selectively enabling plugins

Plugins can be enabled for each book edition:

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

`enabled` is a list of all the enabled plugins without the `Plugin` suffix, so if you wanted to enable plugins `DropCapsPlugin` and `TableExtraPluigin` for edition `ebook` you would write:

~~~.yaml
book:
    ....
    editions:
        ebook:
            plugins:
                enabled: [ DropCaps, TableExtra ]
~~~ 
 


