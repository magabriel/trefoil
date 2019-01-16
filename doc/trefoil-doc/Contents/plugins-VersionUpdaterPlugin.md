## VersionUpdaterPlugin

This plugin updates an optional version string from `config.yml`.

The format of the version string must be `"<version>.<revision>"`, 
where each component is an integer and the separator is a single dot.

### Availability

This plugin is available for all editions.

### Usage
~~~.yaml
# <book-dir>/config.yml 
book:
    version: '1.0'  # This is the version string
    editions:
        <edition-name>
            plugins:
                enabled: [ VersionUpdater ]
                options:
                    VersionUpdater:
                        increment_ver: false # don't increment the version (default)
                        increment_rev: true  # increment the revision (default)
~~~

### Description

Having a version string in `book.yml` is useful because it can be used in 
the templates (for example in `title.twig`) to make it easy to distinguish 
between two different versions of the book. Also, if you have the 
`TwigExtensionPlugin` enabled, you can also use its contents in any content 
element.

This plugin performs an automatic update of the version string in `config.yml`
so you don't have to remember incrementing the revision after each time the 
book is generated. 

N> ##### Note
N> The version is updated **after** the book is generated. This way is easy 
N> to know the value of the next version of the book just looking into `config.yml`.  

The arguments allow selectively incrementing any of the components. 

When the `version` component is incremented, the `revision` component is reset to 0. 

T> ##### Tip
T> You could set a normal `ebook` edition that only increments the `revision` 
T> component each time you produce a new "work in progress" ebook, and another 
T> separate edition `ebook-ok` to increment the `version` part each time you want 
T> to generate a "ready to publish" ebook. 
T> Taking advantage of the editions inheritance make it even more convenient. 
 