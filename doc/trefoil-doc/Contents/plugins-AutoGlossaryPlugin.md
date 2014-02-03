## AutoGlossaryPlugin

The auto-glossary plugin handles the generation of a glossary of terms 
that are automatically hyperlinked to its definitions.

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
                enabled: [ AutoGlossary ]
                options:
                    AutoGlossary:
                        pagebreaks: true  # use pagebreaks between defined terms          
~~~ 

The terms' definitions are read from two files:

- **Global glossary:** Contains the terms to be replaced into the whole book.
- **Item glossary:** Contains the terms to be replaced only into certain book item.

The global glossary definition is called `auto-glossary.yml`:

~~~.yaml
# <book-dir>/Contents/auto-glossary.yml 
# Global glossary definitions
glossary:
    options: 
        # 'all': all ocurrences
        # 'item': (default) only first ocurrence 
        #         into an item (i.e. chapter)
        # 'first': first ocurrence in the whole book
        coverage: 'item'
        
        # Elements to process (default: "chapter")
        elements: ["chapter"]
    
    ####
    # Global definitions of terms:
    #
    #   "term": Term definition
    #
    # Variants are allowed (singular, plural)
    #
    #   "term[s]": Definition which is applied 
    #              to "term" and "terms"
    #   "term [one|two]": Definition which is 
    #              applied to "term one" and "term two"
    ####
    terms: 
        "term": Definition
~~~        

The glossary definitions for each book item are named after the item it applies to 
(so the glossary for `chapter1.md` item should be `chapter1-auto-glossary.yml`)

~~~.yaml
# <book-dir>/Contents/chapter1-auto-glossary.yml 
# Glossary definitions for chapter 1 
glossary:
    ####
    # Definitions of terms for chapter-1:
    #
    #   "term": Term definition
    #
    # Variants are allowed (singular, plural)
    #
    #   "term[s]": Definition which is applied 
    #              to "term" and "terms"
    #   "term [one|two]": Definition which is 
    #              applied to "term one" and "term two"
    ####
    terms: 
        "term": Definition
~~~        

### Description

Depending on the coverage options, each ocurrence (or only the first one) of a defined
term is replaced by an hyperlink to its definition. 

The glossary term definitions are of the form `"term": Term definition`, where each
"term" can be either a *literal expresion* or a *variant expression*:

~~~.yaml
glossary:
    terms: 
        # literal expression
        "Lorem ipsum": Pseudo-latin text used as filler or dummy text.
        
        # variant expressions 
        "car[s]": Definition which is applied to "car" and "cars"
        "orange [car|truck]": Definition which is applied to "orange car" and "orange truck"
~~~

Using variants is possible to create defintitions that cover several cases (like singular
and plural of a word or expression).

The **global glossary definitions** file also have an `options` section with several values
that affect how the glossary terms are processed:

- `glossary.options.coverage` defines how many ocurrences of a term will be converted into
  glossary items links:
    
    - `all`: all ocurrences in the book.
    - `item`: (default) only the first ocurrence inside a content item.
    - `first`: only the first ocurrence in the whole book.
    
- `elements` is a list of all element types to be processed. By default only `chapter` items
  are processed.      

**Example:**

- This paragraph contains the expressions "example term 1" and "example term 2" that should
  be converted into glossary terms.

N> ##### Note
N> While this autoglossary implementation can *potentially* work for every edition type, 
N> whether it really does work depends mostly on the *reader platform* capabilities:
N> 
N> - **Epub:**: Some readers can follow hyperliks, but most do not.
N> - **Kindle:** The Kindle readers can follow hyperlinks and do page breaks. It works OK.
N> - **PDF:** It depends. The official *Adobe Reader* is OK, but other implementations may vary.
N> - **HTML:** No problems.
N> 
N> But, as this implementation is mostly focused on **ebooks**, even if it produces a clickable 
N> autoglossary it may not give the best results on a printed edition.    

{% if app.edition('format') not in ['epub', 'mobi'] %}
N> ##### TODO
N> Create an adequate implementation for editions different from `epub` and `mobi` (like this one).
{% endif %}

### Output

The plugin will generate a report in the output directory called `report-AutoGlossaryPlugin.txt`
with a summary of terms processed and problems found.


