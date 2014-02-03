## TwigExtensionPlugin

This plugin extends Twig to provide some useful functionalities:

### Availability

This plugin is available for all editions.

### Usage

~~~.yaml
# <book-dir>/config.yml 
book:
    ....
    editions:
        <edition-name>
            plugins:
                enabled: [ TwigExtension ]
                options:
                    TwigExtension: 
                        itemtoc:
                            deep: 2  # defaults to <edition>.toc.deep + 1               
~~~ 

### Description

The funcionalities provided are:

- Twig in content.
- Configuration options in content.
- Local TOC (table of contents).
- Include files into content.

#### Twig in content

**trefoil** adds the ability to write Twig code in content:

<div class="code"><pre>
&#123;# this is a Twig comment inside a content item #&#125;
**Lorem ipsum** dolor sit amen. 

&#123;% if 1 == 2 %&#125; 
**Awesome,** it seems that 1 equals 2!!!
&#123;% else %&#125;
No, definitely 1 does not equal 2.
&#123;% endif %&#125;
</pre></div>

The above piece of text will render as:


{# this is a Twig comment inside a content item #}
> **Lorem ipsum** dolor sit amen. 
 
{% if 1 == 2 %} 
> **Awesome,** it seems that 1 equals 2!!!
{% else %}
> No, definitely 1 does not equal 2.
{% endif %}


But why in the world would you want to mix Twig code or functions with 
your content? 
Some complex pieces of content could benefit from having some logic
applied (example: using some book configuration option value to hide or 
show some section). And the ability to mix Twig comments in the text is
definitely a good thing (example: to write some TODO note so you remember
to change some part of the text in a future revision).   

 
#### Configuration options in content

**easybook** only allows configuration options in templates but not in content.
But thanks to the "Twig in content" functonality now you can do it: 

<div class="code"><pre>
**This will not work without this plugin:**

> The title of this book is "*&#123;&#123; book.title &#125;&#125;*".
</pre></div>

This plugin provides such functionality, allowing the use of any configuration option 
in the content.

With this plugin activated, it works:

> The title of this book is "*{{ book.title }}*".

#### Local TOC

Large ebooks with lots of sections and subsections could benefit from having a table of contents
at the begining of each chapter (think of a text book, where each lesson is a book chapter).

This plugin provides a Twig function `itemtoc()` that can be inserted anywhere in the item content 
(thanks to the "configuration options in content" functionality).

It uses de `itemtoc.twig` template that must be available either as a local or global template.

Usage:

<div class="code"><pre>
&#123;&#123; itemtoc() &#125;&#125;
</pre></div>

N> ##### Note
N> You can see an example at the begining of each chapter of the `epub` or `kindle` editions 
N> of this book.

#### Include files into content

Sometimes it could be useful to divide a large chapter into smaller pieces, and then including
all the pieces into the chapter's content.

This plugin provides a `file()` Twig function that works pretty much like PHP's `include()`function, 
allowing the inclusion of another file. 

Usage: 

<div class="code"><pre>
&#123;&#123; file(filename, variables, options) &#125;&#125;
</pre></div>

where "variables" and "options" are optional hash tables where you can pass variables to the 
included file as in `{'variable': 'value'}`, or options that affect the rendering. 

The available options are:

- `{'nopagebreak': false}`: do not insert a page break after the included file (`true` by default)
 
N> ##### Note
N> You can see an example in the source text of this chapter. Pagebreaks will only work on `Kindle`
N> readers and certain `epub` readers.
 
 
 
 
 