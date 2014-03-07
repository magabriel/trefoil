## ManualTitleLabelsPlugin

This plugin allows adding manual title labels to book items.

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
                enabled: [ ManualTitleLabels ]
~~~ 
    
### Description

Item labels are rendered with different markup than the title, creating a nice effect,
like `Chapter 1 - The first chapter title` (where `Chapter 1` is the label while `The 
first chapter title` is the title).

Automatic labels can be added to item titles by easybook using its labeling mechanism, 
but sometimes it could be useful having a way to manually specify labels using markup
in the source file.

This plugin provides simple markup to achieve just this, just enclosing the label part
in `[[..]]`.

Example:

~~~.markdown
# [[Chapter 1]] The first chapter title
~~~

