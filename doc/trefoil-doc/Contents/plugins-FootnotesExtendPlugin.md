## FootnotesExtendPlugin

This plugin extends footnotes to support several formats.

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
                enabled: [ FootnotesExtend ]       
                options:
                    FootnotesExtend:
                        type: end  # (end, inject, item, inline)  
~~~ 

where the options are:

- `type`: Type of footnotes to render. 

    - `end`: Normal Markdown-rendered footnotes.
    - `inject`: Inject footnotes in text.
    - `item`: Use a separated `footnotes` book item.
    - `inline`: Use inline footnotes (i.e. for *PrinceXML*).   

### Description

The Markdown parser converts footnotes into hyperlinks, where the link target 
is the note text at the bottom of the document.

While this behavior[^note1a] could be adequate in certain situations, several
problems arise specific to the edition format being produced:

- For ebooks (`epub` and `mobi` formats), the "footnotes at the end of the chapter" 
  approach does not work well because, in electronic reading devices or applications,
  the notes stand in the way of the normal reading flow.

- For PDF books (which normally will be printed), it would be desirable
  being able to use the traditional footnotes' rendering, while each footnote
  appears at the bottom of the page where it is referred. This type of rendering
  is called "inline footnotes" and is supported by *PrinceXML* rendering tool.
  
This plugin provides a comprehensive solution to these problems by defining
4 types of footnotes:

- `end`: This is the normal Markdown-rendered footnotes. 
  They will be rendered at the end of each book item, separated by an `<hr/>` tag.
  This is the default.

- `inject`: This is a variant of type `end`, where each item's footnotes will be 
   injected to a certain injection point inside the item itself.
   Just write `<div class="footnotes"></div>` in the point where where the 
   footnotes should be injected.

- `item`: All the footnotes in the book will be collected and rendered in a separated 
   item called 'footnotes' that need to exist in the book.

- `inline`: *PrinceXML* supports inline footnotes, where the full text of the note must 
   be inlined into the text, instead of just a reference. *PrinceXML* will manage the 
   numbering.   

#### About inline footnotes
 
*PrinceXML* manages footnotes as:
 
~~~.htmml
some text<span class="fn">Text of the footnote</span> more text 
~~~
 
One limitation is that the footnote text cannot contain block elements (as paragraphs, 
tables, lists). The plugin overcomes this partially by replacing paragraph tags with 
`<br/>` tags.

N> ##### Tip
N> You can see an example of footnotes in this section (look at the source text).

{% if app.edition('format') not in ['epub', 'mobi'] %}
N> ##### TODO
N> Create an adequate implementation for editions different from `epub` and `mobi` (like this one).
{% endif %}

[^note1a]: This is a footnote just for showing an example.

[^note2a]: Another footnote, also with dummy text just to have something to work with .

