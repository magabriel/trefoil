## QualityControlPlugin

This plugin checks the book for common problems or mistakes.

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
                enabled: [ QualityControl ]
~~~ 

### Description

The checks performed are:

- **Unused images:** Any unused image in the final book can cause problems, like adding extra
  size to the generated file or even failing a validation (like the `EpubCheck` utility for
  `epub` editions).
  
- **Emphasis marks not processed:** The Markdown makes and excelent work but sometimes it can
  get confused with some edge cases, leaving emphasis marks slip unprocessed into the final
  book text.

Consider the following example, where the writer intended to add bold emphasis to word "dolor"
but left an extra espace after the word inside the closing "\*\*" (word "sadipscing", on the
other hand, is correctly emphasized):

~~~.markdown
Lorem ipsum **dolor ** sit amet, consetetur **sadipscing** elitr.
~~~

This will be rendered as:

> Lorem ipsum **dolor ** sit amet, consetetur **sadipscing** elitr

Instead of the intended:

> Lorem ipsum **dolor** sit amet, consetetur **sadipscing** elitr

The plugin will report the problem so it can be easily spotted and fixed.

  
### Output

The plugin will generate a report in the output directory called `report-QualityCheckPlugin.txt`.  

