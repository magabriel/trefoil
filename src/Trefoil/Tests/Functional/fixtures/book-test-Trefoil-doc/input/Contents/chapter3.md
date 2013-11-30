# The Trefoil plugins

A lot of the functionality of **trefoil** is implemented into the included plugins. 
 
{{ itemtoc() }}

## DropCapsPlugin

This plugin handles adding drop caps to the book, either automatically or manually.

It provides three working modes:

1. Automatic dropcaps.
2. Manual dropcaps HTML markup.
3. Markdown-like manual dropcaps. 


### Automatic drop caps

Drop caps can be automatically added to the first paragraph after a heading. This behaviour can be influenced on a per-edition base setting options in the `plugins` configuration section inside the book's `config.yml`.
 
~~~.yaml
book:
    ....
    editions:
        <edition-name>
            plugins:
                ...
                options:
                    DropCaps:
                        levels:     [1]           
                        mode:       letter        
                        length:     1             
                        coverage:   ['chapter']   
~~~ 

where the options are:

- `levels`: The heading levels to add drop caps to. Values 1 to 6 (default: 1).
- `mode`: Values `letter` or `word` (default: `letter`).
- `length`: Number of letters or words to highlight (default: 1).
- `coverage`: Array of book elements to process (default: ['chapter']).
    
   
        
### Manual drop caps HTML markup

HTML can be freely mixed into Markdown. So a way to manually add drop caps to the text is:

~~~.html
<span class="dropcaps">T</span>his is a paragraph that starts with a manually-added dropcap.
~~~

*trefoil* will produce the following HTML code that can be easily styled:

~~~.html
<p class="has-dropcaps"><span class="dropcaps">T</span>his is a paragraph that starts with a manually-added dropcap.</p>
~~~


### Markdown-like manual drop caps

Besides adding the HTML markup directly, a Markdown-like markup is provided for greater convenience.

~~~.markdown
[[T]]his text has first-letter drop caps.

[[But]] this text has first-word drop caps.
~~~

will produce the following HTML:

~~~.html
<p class="has-dropcaps"><span class="dropcaps">T</span>his text has first-letter drop caps.</p>
<p class="has-dropcaps"><span class="dropcaps">But</span> this text has first-word drop caps.</p>
~~~
