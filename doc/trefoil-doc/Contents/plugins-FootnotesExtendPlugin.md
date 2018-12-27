## FootnotesExtendPlugin

This plugin extends footnotes to support several formats.

### Availability

This plugin is available for all editions.

### Usage

~~~.yaml
# <book-dir>/config.yml 
book:
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

A **footnote** is a short clarification text wich is associated to a certain position in
the main text but without interrupting the normal reading flow. 

The footnote text will be shown in another part of the text (normally the page _footer_,
hence its name) but it could also be shown at the end of the chapter or even the end 
of the book.

#### Footnotes in Markdown

A footnote can be created in Markdown by inserting `[^note1]` at the desired 
text position, where _note1_ is just a key[^note1] which will not be shown to 
the user and that it is only used to associate the footnote to its text.

The footnote's text can be defined[^note2] with a paragraph containing:

~~~.markdown
[^note1]: This the footnote's text.
~~~

which can be placed anywhere in the same document.

The Markdown parser converts footnotes into hyperlinks, where the link target 
is the note text at the bottom of the document (for `easybook` and `trefoil` 
books that means the end of the _edition item_). The user can "click" in the 
footnote and the footnote will be show. The user needs to "click" in another
link to return to the footnote's position and continue reading.

#### The problem to solve

While the default Markdown parser's footnotes implementation could be adequate 
in certain situations, several problems arise specific to the edition format 
being produced:

- For ebooks (`epub` and `mobi` formats), the "footnotes at the end of the chapter" 
  approach does not work well because, in electronic reading devices or applications,
  the notes stand in the way of the normal reading flow.

- For PDF books (which normally will be printed), it would be desirable
  being able to use the traditional footnotes' rendering, while each footnote
  appears at the bottom of the page where it is referred. This type of rendering
  is called "inline footnotes" and is supported by *PrinceXML* rendering tool.
  
#### The solution

This plugin provides a comprehensive solution to these problems by defining
four types of footnotes:

- `end`: This is the normal Markdown-rendered footnotes. 
   They will be rendered at the end of each book item, separated by an `<hr/>` tag.
   This is the default.

- `inject`: This is a variant of type `end`, where each item's footnotes will be 
   injected at a certain injection point inside the item itself.
   Just write `<div class="footnotes"></div>` at the point where where the 
   footnotes should be injected.

- `item`: All the footnotes in the book will be collected and rendered in a separated 
   item called 'footnotes' that need to exist in the book.

- `inline`: *PrinceXML* supports inline footnotes, where the full text of the note must 
   be inlined into the text, instead of just a reference. *PrinceXML* will manage the 
   numbering.   

#### About PrinceXML inline footnotes
 
*PrinceXML* natively manages footnotes as:
 
~~~.htmml
some text<span class="fn">Text of the footnote</span> more text 
~~~

This plugin will take care of the rendering, but one limitation is that the footnote 
text cannot contain block elements (as paragraphs, tables, lists). The plugin overcomes 
this partially by replacing paragraph tags with `<br/>` tags.

#### Examples

N> ##### Tip
N> You can see an example of footnotes in this section (look at the source text).

[^note1]: This is a footnote just for showing an example.

[^note2]: Another footnote, also with dummy text just to have something to work with .

