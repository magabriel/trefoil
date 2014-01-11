## EpubCheckPlugin

This plugin checks the generated epub ebook using the `EpubCheck` utility.
See <https://github.com/IDPF/epubcheck> for download options and more
information.

### Availability

This plugin is available for `epub` editions.

### Usage

~~~.yaml
# <book-dir>/config.yml 
easybook:
    parameters:
        ...
        epubcheck.path: '/path/to/epubcheck.jar'
        epubcheck.command_options: ''

book:
    ....
    editions:
        <edition-name>
            plugins:
                enabled: [ EpubCheck ]
~~~ 

### Description

`EpubCheck` is a tool to validate `epub` files (version 2.0 and later).  

To use it, download the relevant `.jar` file to some directory on your computer
and configure that path in the `epubcheck.path` option of `config.yml` as shown above.
You can also pass some options via the `epubcheck.command_options` option.

### Output

The plugin will generate a report in the output directory called `report-EpubCheckPlugin.txt`.
