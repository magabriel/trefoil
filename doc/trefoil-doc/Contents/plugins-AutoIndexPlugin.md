## AutoIndexPlugin

The auto-index plugin handles the generation of an index of terms 
which are automatically hyperlinked to its occurrences in the text.
It also allows manual index entries.

### Availability

This plugin is available for all editions.

### Usage

~~~.yaml
# <book-dir>/config.yml 
book:
    editions:
        <edition-name>
            plugins:
                enabled: [ AutoIndex ]
~~~        

### Description

This plugin replicates the "back of the book index" functionality of written books 
(an alphabetized, cross-referenced list of meaningful terms which helps the reader
finding where in the book a topic is written about).

The index definition file is called `auto-index.yml` and has the following format.

~~~.yaml
# <book-dir>/Contents/auto-index.yml
index:
    options: # optional section
         # items where the auto index terms should be applied
         elements: [chapter] # default

    # definitions for automatically-added terms
    terms:
        # A simple term definition:
        term: text (optional)

        # A complex term definition (group):
        term:
            text: "Term text" (optional)
            terms:
                "subterm 1": "subterm 1 text" (optional)
                ...
                "subterm n": "subterm n text" (optional)

    # definitions for manually-added terms
    manual-terms:
        # A simple term definition:
        term-key-1: text (optional)

        #A complex term definition (group):
        group-name:
            text: "Term text for group" (optional)
            terms:
                subterm-key-1: "subterm 1 text" (optional)
                # ...
                subterm-key-n: "subterm n text" (optional)
~~~

The **index definitions** has the following sections:

#### Options

Values that affect how the index terms are processed:

- `elements` is a list of all element types to be processed. By default only `chapter` items
  are processed.   

#### Terms (automatically-marked index entries)

The terms definitions to be included automatically in the index whenever they are found in
the book text (contolled by the `options.elements` value).

##### Simple terms

A simple term has the form:

~~~.yaml
terms:
    term: some text
~~~

where `term` is the word or expression to be indexed if found in the book text, and 
`some text` is the optional text to be used in the index entry as a replacement for the `term`.  

Example:
    
~~~.yaml
terms:
    JSON:
    yaml: YAML file format
~~~
    
- Each ocurrence of "JSON" (capitalization does not matter) will be indexed under the 
"JSON" entry. 
- Each ocurrence of "yaml" will be indexed under the "YAML file format" entry. 

Terms can include **variants** in their definitions, which makes it possible to create 
index entries that cover several cases (like singular and plural forms of a word or expression).

~~~.yaml
terms:
    telephone[s]: # "telephone" or "telephones"
    media [file|document]: # "media file" or "media document"
~~~
 
##### Complex terms (groups)

A complex term or group has the following form:

~~~.yaml
terms:
    term:
        text: "Term text" # optional
        terms:
            "subterm 1": "subterm 1 text" # optional
            # ...
            "subterm n": "subterm n text" # optional
~~~

Each term groups other subterms which will be indexed as part of the main term.

#### Manual terms (manually-marked index entries)

It is possible to add index entries by marking them explicitly 
(a.k.a. "the tradditional way"). To achiveve this there are two methods:

<br/>

**Method 1.- Using an index mark**

~~~.markdown
This is a **markdown**|@| text where the previous occurrence of the word 'markdown' is marked for indexing.
~~~

The term to index is marked by inserting `|@|` next to it.

Rules for manual indexing:

- The index mark `|@|` must be immediately following the term to index.
- The term can be:
    - A single word.
    - One or more words delimited by double quotes ("the term"), single qoutes ('the term') or emphasis marks 
      (`**the term**` or `_the term_`). The mark must be placed _outside_ the closing mark.

**Method 2.- Enclosing the term**

~~~.markdown
This is a |markdown text| where the previous occurrence of the words 'markdown text' are marked for indexing.
~~~

The term to index is marked by enclosing it between `|`. 

Both methods can potentially render the same results.

N> ##### Note
N> If the indexed term exists in the `manual-terms` section of the `auto-index.yml` file, its definition will be
N> taken into account when creating the index. If the term does not exist, it will be indexed with default values.

##### The `manual-terms` definition section

The definition file can **optionally** contain something similar to:

~~~.yaml
# manual terms definition
manual-terms:
    term1: This is a term
     #A complex term definition (group):
    term2-group:
        text: "Term text for group" (optional)
        terms:
            subterm-key-1: "subterm 1 text" (optional)
            # ...
            subterm-key-n: "subterm n text" (optional)
~~~

**Example:**

The following paragraphs contain example index terms. You can go directly to the [index section](#index)
to check it out, and then click on the term link to return here.

1. This paragraph contains the expressions "example index term 1" and "example index term 2" which 
  should be automatically converted into index entries.
  
2. This paragraph contains "example index term 3"|@| which is manually marked as index term.

3. This paragraph has **example index term 4**|@| which is manually marked as index term. 
   And also, a second time bold marking: the |example index term 4|.

N> ##### Note
N> While this autoindex implementation can *potentially* work for every edition type, 
N> whether it really does work depends mostly on the *reader platform* capabilities:
N> 
N> - **Epub:**: Some readers can follow hyperliks, but most do not.
N> - **Kindle:** The Kindle readers can follow hyperlinks. It works OK.
N> - **PDF:** It depends. The official *Adobe Reader* is OK, but other implementations may vary.
N> - **HTML:** No problems.
N> 
N> But, as this implementation is mostly focused on **ebooks**, even if it produces a clickable 
N> autoindex it may not give the best results on a printed edition.    

### Output

The plugin will generate a report in the output directory called `report-AutoIndexPlugin.txt`
with a summary of terms processed and problems found.

