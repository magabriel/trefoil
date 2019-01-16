## HtmlTweaksPlugin

This plugin provides a way to tweak the book HTML after it has been generated.
It works modifying the HTML code produced by the Markdown processor or by the 
theme templates, so the final HTML code can meet specific requirements.

### Availability

This plugin is available for all editions.

### Usage

~~~.yaml
# <book-dir>/config.yml
book:
    editions:
        <edition-name>
            plugins:
                enabled: [ HtmlTweaks ]            
~~~

The actual tweaks definitions are read from a separate `yaml` file:

~~~.yaml
# <book-dir>/Contents/html-tweaks.yml
#  or
# <current-theme>/<format>/Config/html-tweaks.yml
#  or
# <current-theme>/Common/Config/html-tweaks.yml
tweaks:
    # replacements to be made at onPreParse time
    onPreParse:
        tweak-name-free:                 # just a name
            tag: 'tag name'              # tag name to find
            class: 'class-name'          # OPTIONAL class name of tag

            # either one of 'insert', 'surround' or 'replace' operations

            insert:    # insert HTML code inside the tag (surround content)
                open:  'some html text'  # opening text
                close: 'more html text'  # closing text

            surround:  # surround tag with HTML code (surround the tag)
                open:  'some html text'  # opening text
                close: 'more html text'  # closing text

            replace:   # replace tag with another one
                tag:  'another html tag' # replacement tag

    # replacements to be made at onPostParse time
    onPostParse:        
        another-tweak-name:
            tag: 'tag name'
            # same options than onPreParse
            # ...
~~~

### Description

The Markdown processor generates fixed HTML structures for each Markdown tag 
that cannot be configured or parameterized. In occasisons could be desireable 
to customize the generated HTML to meet certain requierements.

Examples:

- Surround all `<pre>...</pre>` tags with `<div class="box">...</div>`.
- Append `<hr>` tag to each `<h2>` tag.

Instead of having to write a dedicated plugin for that task, this plugin provides
the funcionality to resolve some simple cases.

N> ##### Note
N> This plugin uses a separate configuration file `html-tweaks.yml` to provide the 
N> tweaks' definitions. This file can be loaded from the theme in use or from the 
N> book contents directory.   

#### The configuration file

The tweaks' definitions are read from file `html-tweaks.yml`, that can be located:

- In the book `/Contents` directory
- In the theme `/<format>/Config` directory
- In the theme `/Common/Config` directory
 
The first one found will be used. This allows distributing the tweaks as part of the
theme, or customizing them for an specific book.
 
The `onPreParse` tweaks will be made before the Markdown parser has processed the item
(so they can easyly pick any raw HTML embedded into the Markdown text), while the `onPostParse`
tweaks will work on the HTML produced by the Markdown processor.

#### Example

~~~.yaml
# <book-dir>/Contents/html-tweaks.yml
#  or
# <current-theme>/<format>/Config/html-tweaks.yml
#  or
# <current-theme>/Common/Config/html-tweaks.yml
tweaks:
    onPreParse:
        # enclose contents of all divs of class "one" with box1
        tweak-div-one:                                 
            tag: 'div'
            class: 'one'   
            insert:
                open:  '<div class="box1" markdown="1">'
                close: '</div>'
                
        # surround all divs of class "two" with box2
        tweak-div-two:                                 
            tag: 'div'
            class: 'two'   
            surround: 
                open:  '<div class="box2">'
                close: '</div>'
                
        # replace all spans with divs
        tweak-span-three: 
            tag: 'span'
            replace: 
                tag:  'div'                  
                
    onPostParse:
        # enclose contents of 'pre' tags between lines
        tweak-pre:                                 
            tag: 'pre'   
            insert:
                open:  '======\n'
                close: '\n------'
                
        # surround tables with box1
        tweak-table:
            tag: 'table'
            surround: 
                open:  '<div class="box1">'
                close: '</div>'
                
        # convert all unordered lists into ordered lists
        tweak-ul:
            tag: 'ul'
            replace: 
                tag:  'ol'                
~~~
