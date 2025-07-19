# Other funcionality

{{ itemtoc() }}
<!-- TODO: Document WeasyPrint as pdf_engine -->
Content exclusion
-----------------

Some kinds of contents do not make sense for certain edition formats. 
They will be automatically excluded:

- `epub` ebooks usually do not include a visible table of contents (HTML TOC), 
  as reader apps and devices heavily rely on the navigational TOC. But it could 
  make sense to include it on certain situations, so a `config.yml` parameter
  could be added to explicitly request it:

~~~.yaml
# config.yml
book:
    editions:
        ebook:
            format: epub
            include_html_toc: true # An HTML TOC will be included
~~~  

Content filtering
-----------------
 
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

~~~.yaml
# config.yml
book:
    contents:
        - { element: edition }
        - { element: toc }
        - { element: usage-instructions, content: usage-instructions.md, editions: [ebook, kindle] }
        - { element: print-instructions, content: print-instructions.md, formats: [pdf] }
        - { element: chapter, number: 1, content: chapter1.md }
~~~

#### Syntax

For a content item, the following options can be added:

- `editions`: an array of the edition names which will contain that item.

- `formats`: an array of the allowed formats for that item. Only editions with these formats will
   contain the item.
   
Prefixing the edition or format value with `not-` will negate it. 

N> ##### Note
N> In previous versions the negation prefix was '!'. This was changed because of a conflict
N> with the new "custom tags" feature of the Yaml parser.

Example:

- `editions: [ebook, kindle]` ==> only `ebook` and `kindle` editions.
- `editions: [not-print]` ==> all editions except `print`.
- `formats: [epub, mobi]` ==> only editions with `epub` or `mobi` format.
- `formats: [not-pdf]` ==> all editions except those with `pdf` format.

If both `editions` and `formats` are used in the same item, it will only be included
in editions that fulfill both sets of conditions.


Config import
-------------

In some cases may be convenient to being able to somehow "import" some book config 
definitions into our book config, to avoid repeating common values.
 
Example:

- Assume we are producing a series of ten books (i.e. lessons of a course) and all of them 
  will have the same editions: 'ebook', 'kindle' and 'print'.

- We want to avoid defining again and again the same editions' definitions in all of
  ten books' `config.yml`. 

This functionality allows just that:

~~~.yaml
# my_series/config.yml
easybook:
    # all of easybook parameters
book:
    editions:
        ebook:
            # definition of ebook edition
        kindle:
            # definition of kindle edition
        print:
            # definition of print edition   
~~~

And then:

~~~.yaml
# my_series/book1/config.yml
import:
    - ".."
      
book:
    title: '...'
    
    # no 'editions' definitions!
~~~

The `import` value is an array of all the directories where a suitable `config.yml` file will 
be looked up for inclusion. The first one will be used, and used as the base definition
in which the "local" definitions will be merged in. 

WeasyPrint as PDF rendering engine
----------------------------------

**WeasyPrint** <https://weasyprint.org/> can be used as an alternative PDF rendering engine
instead of **PrinceXML**. Unlike it, **WeasyPrint** is an open source project that can be 
used for free. It offers a comparable feature set to **PrinceXML**, but be aware that 
some things still don't work.

To use it, install it following the instructions from <https://doc.courtbouillon.org/weasyprint/stable/first_steps.html> and activate it using the following configuration option:

~~~.yaml
# <book-dir>/config.yml 

easybook:
  parameters:
    weasyprint:
      path: /usr/bin/weasyprint      

book:
    editions:
      <edition-name>
         pdf_engine: weasyprint
~~~
