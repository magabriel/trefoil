# Other funcionality

{{ itemtoc() }}

## Content filtering
 
For books with several editions may be convenient to have a way to include
some content in just one of them and not in the others, or to include
an item based on the format of the edition.
 
Example:

- Item `ebook-usage-instructions.md` must only be included in `ebook` and 
  `kindle` edition.

- Item `pdf-print-instruccions.md` must be excluded from all editions whose
  output format is not `pdf`, regarding of the edition name.
  
To achieve that we can use *content filtering*. Consider the following book
definition:

~~~ 
# config.yml
book:
    . . .
    contents:
        - { element: edition }
        - { element: toc }
        - { element: usage-instructions, content: usage-instructions.md, editions: [ebook, kindle] }
        - { element: print-instructions, content: print-instructions.md, formats: [pdf] }
        - { element: chapter,     number:   1,  content: chapter1.md }
        . . .
~~~ 

#### Syntax

For a content item, the following options can be added:

- `editions`: an array of the edition names which will contain that item.

- `formats`: an array of the allowed formats for that item. Only editions with these formats will
   contain the item.
   
Prefixing the edition or format value witn `!` will negate it. 

Example:

- `editions: [ebook, kindle]` ==> only `ebook` and `kindle` editions.
- `editions: [!print]` ==> all editions except `print`.
- `formats: [epub, mobi]` ==> only editions with `epub` or `mobi` format.
- `formats: [!pdf]` ==> all editions except those with `pdf` format.

If both `editions` and `formats` are used in the same item, it will only be included
in editions that fulfill both sets of conditions.


