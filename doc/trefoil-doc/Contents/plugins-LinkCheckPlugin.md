## LinkCheckPlugin

This plugin checks all the internal and external links in the book.

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
                enabled: [ LinkCheckPlugin ]
                options:
                    LinkCheck:
                        check_external_links: true
              
~~~ 

### Description

**Internal links** are checked by looking for a valid link target, i.e. an html element
whith id="the-link-target" in the whole book. This is useful for manually inserted links
or anchors in the text (remember that you can freely insert pure HTML into Markdown).

This is valid:

~~~.markdown
#### My section {anchor-to-my-section}

I can link to [my section](#anchor-to-my-section).

<div id="other-anchor"></div>

Lorem ipsum...

And also can link to [other anchor](#other-anchor)
~~~

**External links** are (optionally) checked for existence by performing a network lookup.
This behaviour is off by default because could be very time consuming if the book
has a large number of external links. To turn it on, set the following option in
the book's `config.yml' .

### Output

The plugin will generate a report in the output directory called `report-LinkCheckPlugin.txt`.
