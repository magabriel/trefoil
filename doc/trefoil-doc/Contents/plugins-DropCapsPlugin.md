## DropCapsPlugin

This plugin handles adding drop caps to the book, either automatically or manually.

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
                enabled: [ DropCaps ]
                options:
                    DropCaps:
                        levels:     [1]           
                        mode:       letter        
                        length:     1             
                        coverage:   ['chapter']   
~~~ 

where the options are:

- `levels`: The heading levels to add drop caps after. Values 1 to 6 (default: 1).
- `mode`: Values `letter` or `word` (default: `letter`).
- `length`: Number of letters or words to turn into drop caps (default: 1).
- `coverage`: Array of book elements to process (default: ['chapter']).   
    
    
### Description

There are several ways to add drop caps to the book:

1. Automatic dropcaps.
2. Manual dropcaps HTML markup.
3. Markdown-like manual dropcaps. 


#### Automatic drop caps

Drop caps can be automatically added to the first paragraph after a heading. 
This behaviour can be influenced on a per-edition base setting options in 
the `plugins` configuration section inside the book's `config.yml`.
 
N> ##### Note
N> This book has automatic drop caps applied with the default options.

        
#### Manual drop caps HTML markup

HTML can be freely mixed into Markdown. So a way to manually add drop caps 
to the text is:

~~~.html
<span class="dropcaps">T</span>his is a paragraph that starts with a manually-added drop cap.
~~~

**trefoil** will produce the following HTML code that can be easily styled:

~~~.html
<p class="has-dropcaps"><span class="dropcaps">T</span>his is a paragraph that starts with a manually-added drop cap.</p>
~~~


#### Markdown-like manual drop caps

Besides adding the HTML markup directly, a Markdown-like markup is provided for 
greater convenience.

~~~.markdown
[[T]]his text has first-letter drop caps.

[[But]] this text has first-word drop caps.
~~~

will produce the following HTML:

~~~.html
<p class="has-dropcaps"><span class="dropcaps">T</span>his text has first-letter drop caps.</p>

<p class="has-dropcaps"><span class="dropcaps">But</span> this text has first-word drop caps.</p>
~~~

and will be shown in the book as (with some dummy text added):

[[T]]his text has first-letter drop caps. Lorem ipsum dolor sit amet, consetetur 
sadipscing elitr, sed diam nonumyeirmod tempor invidunt ut labore et dolore 
magna aliquyam erat, sed diamvoluptua. 

[[But]] this text has first-word drop caps. Lorem ipsum dolor sit amet, consetetur 
sadipscing elitr, sed diam nonumyeirmod tempor invidunt ut labore et dolore magna 
aliquyam erat, sed diamvoluptua. 




