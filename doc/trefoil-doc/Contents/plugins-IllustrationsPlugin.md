## Illustrations Plugin

This plugin will manage the illustrations in the book.

An _illustration_ is a delimited block which can be styled and automatically numbered. 

N> ##### Note 
N> When enabled, this plugin will take over the normal "tables" numbering
N> and listing of `easybook`. If a table is needed as an illustration it will
N> need to be done with this new markup.
N> Ordinary tables (outside an illustration markup) will be ignored and just
N> parsed as Markdown tables, not `easybook` tables

### Availability

This plugin is available for all editions.

### Usage

~~~.yaml
# <book-dir>/config.yml 
book:
    editions:
        <edition-name>
            plugins:
                enabled: [ Illustrations ]       
                options:
                    FootnotesExtend:
                        type: end  # (end, inject, item, inline)  
~~~ 

### Description

Out of the box, `easybook` provides the functionality to automatically number 
figures (images) and tables (real Markdown tables). But sometimes it would be
preferrable to use some text as a figure (instead of an image) or to mix a 
table with some text (as a caption, for example). This plugin provides such 
functionality with a simple syntax:

~~~.markdown
<< ========= "This is the illustration caption" ========= {.optional-class}
. . . whatever Markdown or HTML content
<</ =================
~~~

where:

- `<<` and `<</` marks are delimiters for the begining and end of the illustration.

- `=` in the opening and closing block marks are optional, just to visually
  delimit the illustration.
  
- `.optional-class`is one or several CSS classes to be applied to style the illustration.

ATX-style headers can be used inside the illustration content and
will not be parsed by easybook (i.e. not added labels and ignored in the TOC).

The rendering of illustrations can be customized with the `illustration.twig` template, 
but the plugin will apply a default rendering if the template is not present.

### Example

~~~.markdown
<< ========= "The example illustration" ========= {.class1 .class2}

A list:

- One.
- Two.
- Three.

And a table:

| Header 1  | Header 2  
| --------- | ----------
| One       | One text.
| Two       | Two text.

<</ =================
~~~

Will be rendered as:

<< ========= "The example illustration." ========= {.class1 .class2}

A list:

- One.
- Two.
- Three.

And a table:

| Header 1  | Header 2  
| --------- | ----------
| One       | One text.
| Two       | Two text.

<</ =================

