## EpubUncompressPlugin

This plugin uncompresses the geneated `book.epub` file to make it easy looking 
at its contents.

This can be useful when debugging templates, to make it sure the final HTML 
inside the EPUB file is correct.  

### Availability

This plugin is available for `epub` editions.

### Usage

~~~.yaml
# <book-dir>/config.yml 
book:
    editions:
        <edition-name>
            plugins:
                enabled: [ EpubUncompress ]
~~~ 

### Description

The uncompressed contents are left into the same directory where the `book.epub` 
file is generated.
