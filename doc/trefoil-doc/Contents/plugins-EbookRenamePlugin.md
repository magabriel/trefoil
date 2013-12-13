## EbookRenamePlugin

This plugin copies the generated file `book.<ext>` to `<new-name>.<ext>`. 
The original `book.<ext>` file can be optionally kept.

### Availability

This plugin is available for `epub` and `mobi`editions.

### Usage

~~~.yaml
# <book-dir>/config.yml 
book:
    ....
    editions:
        <edition-name>
            plugins:
                enabled: [ EbookRename ]
                options:
                    EbookRename:
                        schema:         '{publishing.book.slug}-{book.version}' 
                        keep_original:  true                
~~~ 

### Description

The naming schema is derived from Twig syntax but with single curly brackets.

Allowed variables are:

- `publishing.book.slug` (the book slug).
- any `book` or `edition` config variable.

