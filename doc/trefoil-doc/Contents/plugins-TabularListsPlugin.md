## TabularListsPlugin

This plugin adds support for _tabular lists_, funcionality, which represents a list
that can be alternatively shown as a table without loosing information (and vice versa).
 
Its intended use is providing an adequate representation of tables in ebooks
(where wide tables are not appropriate) while maintaining the table as-is for wider
formats like PDF.

### Availability

This plugin is available for all editions.

### Usage

~~~.yaml
# <book-dir>/config.yml
book:
    editions:
        <edition-name>
            plugins:
                enabled: [ TabularLists ]       
~~~

To activate this feature for a certain list, it must be enclosed between
the following markup:

~~~.html
{@ ========== tabularlist_begin() @}
    
    ...the markdown list definition
    
{@ ========== tabularlist_end() @}
~~~

where `tabularlist_begin()` and `tabularlist_end()` are *trefoil markers*.

N> ##### What are Trefoil markers?
N> Trefoil markers are a kind of function call wich produces HTML code to be 
N> processed later. They are similar to `Twig` function calls but are enclosed 
N> between `{@ ... @}` delimiters.
N> 
N> The "======" series between the opening `{@` and the function name is an optional
N> delimiter to help visually identifiying the block in the text.

### Description

Tables are always problematic in ebooks because they do not work well in such a narrow 
screen (think of a table with 3 or more columns). The clasical solutions are:

- Convert the table to image. This is often far from ideal and it can also become 
work-intensive and tedious. On the plus side, the result will also work unmodified in
wider editions types, like PDF.

- Convert the table to a list ("linearize" the table). This will provide better usability
in an ebook reader but at the cost of losing readability on wider edition types. 

So the problem we want to solve is: How to render a table as a list or a list as a table
in such a way that it remains readable both in ebook (or HTML) and PDF editions?

The plugin uses the pragmatic approach of accepting the **list** version of the table
(which works in all formats _as is_) and providing the transformation to table to meet
the requirements of the format being rendered. 

The target table looks like that:

| CategoryA  | CategoryB  | Attribute 1 | Attribute 2 |
| ---------- | ---------- | ----------- | ----------- |
| A1         | B1         | A1.B1.1.1   | A1.B1.2.1   |
| '          | B2         | A1.B2.1.2   | A1.B2.2.2   |
| A2         | B3         | A2.B3.1.3   | A1.B3.2.3   |
| '          | B4         | A2.B4.1.4   | A1.B4.2.4   |

There are two categories and two attributes for each category. The representation
as HTML table uses rowspan and colspan to show the relations.

This can be linearized as:

- **CategoryA**: A1
    - **CategoryB**: B1
        - **Attribute 1**: A1.B1.1.1
        - **Attribute 2**: A1.B1.2.1
    - **CategoryB**: B2
        - **Attribute 1**: A1.B2.1.2
        - **Attribute 2**: A1.B2.2.2
- **CategoryA**: A2
    - **CategoryB**: B3
        - **Attribute 1**: A2.B3.1.3
        - **Attribute 2**: A2.B3.2.3
    - **CategoryB**: B4
        - **Attribute 1**: A2.B4.1.4
        - **Attribute 2**: A2.B4.2.4
    
We call the above list a **tabularlist**, because it is a list which can be 
_tabularized_ (converted to table) and get the following results:

{@ tabularlist_begin() @}

- **CategoryA**: A1
    - **CategoryB**: B1
        - **Attribute 1**: A1.B1.1.1
        - **Attribute 2**: A1.B1.2.1
    - **CategoryB**: B2
        - **Attribute 1**: A1.B2.1.2
        - **Attribute 2**: A1.B2.2.2
- **CategoryA**: A2
    - **CategoryB**: B3
        - **Attribute 1**: A2.B3.1.3
        - **Attribute 2**: A2.B3.2.3
    - **CategoryB**: B4
        - **Attribute 1**: A2.B4.1.4
        - **Attribute 2**: A2.B4.2.4

{@ tabularlist_end() @}

...wich is exactly the target table. But the source for the above rendering is just our input list
surrounded with the appropriate markup:

~~~.html
{@ tabularlist_begin() @}

- **CategoryA**: A1
    - **CategoryB**: B1
        - **Attribute 1**: A1.B1.1.1
        - **Attribute 2**: A1.B1.2.1
    - **CategoryB**: B2
        - **Attribute 1**: A1.B2.1.2
        - **Attribute 2**: A1.B2.2.2
- **CategoryA**: A2
    - **CategoryB**: B3
        - **Attribute 1**: A2.B3.1.3
        - **Attribute 2**: A2.B3.2.3
    - **CategoryB**: B4
        - **Attribute 1**: A2.B4.1.4
        - **Attribute 2**: A2.B4.2.4

{@ tabularlist_end() @}
~~~

The above tabularlist is using a default number of columns (by not specifying it). The plugin
will infer the number of columns by adding the list _deep_ (the number of nested lists, 3 in 
the example) to the number of items in the most inner list (2 in the example) minus one. 
So the default number of columns in the example will be **4**.

The tabularlist can also be rendered with any number of columns between 0 and the maximum of 4.
This is achieved passing an optional argument to the `tabularlist_begin()` with a list of pairs
of the form `{key:value, ... ,key:value}`, where each key can be:
 
- The name of an _edition_ or _format_.
- `all` to represent all the cases. 

And the `value` is the number of columns to use. Example: 

- `{@ tabularlist_begin( {all:2} ) @}`: The table is rendered with two columns for all the 
  editions and formats.

- `{@ tabularlist_begin( {ebook:1, pdf:3} ) @}`: The table is rendered with 1 column for the 
  `ebook` editions and three columns for all the editions with `pdf` format. All other cases
  are rendered with the default number of columns.
  
- `{@ tabularlist_begin( {ebook:1, pdf:3, all:2} ) @}`: The table is rendered with 1 column for the 
  `ebook` editions and three columns for all the editions with `pdf` format. All other cases
  are rendered with two columns.

- - -

Rendering examples:

**0 columns**: 

{@ tabularlist_begin( {all:0} ) @}

- **CategoryA**: A1
    - **CategoryB**: B1
        - **Attribute 1**: A1.B1.1.1
        - **Attribute 2**: A1.B1.2.1
    - **CategoryB**: B2
        - **Attribute 1**: A1.B2.1.2
        - **Attribute 2**: A1.B2.2.2
- **CategoryA**: A2
    - **CategoryB**: B3
        - **Attribute 1**: A2.B3.1.3
        - **Attribute 2**: A2.B3.2.3
    - **CategoryB**: B4
        - **Attribute 1**: A2.B4.1.4
        - **Attribute 2**: A2.B4.2.4

{@ tabularlist_end() @}

**1 column**: 

{@ tabularlist_begin( {all:1} ) @}

- **CategoryA**: A1
    - **CategoryB**: B1
        - **Attribute 1**: A1.B1.1.1
        - **Attribute 2**: A1.B1.2.1
    - **CategoryB**: B2
        - **Attribute 1**: A1.B2.1.2
        - **Attribute 2**: A1.B2.2.2
- **CategoryA**: A2
    - **CategoryB**: B3
        - **Attribute 1**: A2.B3.1.3
        - **Attribute 2**: A2.B3.2.3
    - **CategoryB**: B4
        - **Attribute 1**: A2.B4.1.4
        - **Attribute 2**: A2.B4.2.4

{@ tabularlist_end() @}

**2 columns**: 

{@ tabularlist_begin( {all:2} ) @}

- **CategoryA**: A1
    - **CategoryB**: B1
        - **Attribute 1**: A1.B1.1.1
        - **Attribute 2**: A1.B1.2.1
    - **CategoryB**: B2
        - **Attribute 1**: A1.B2.1.2
        - **Attribute 2**: A1.B2.2.2
- **CategoryA**: A2
    - **CategoryB**: B3
        - **Attribute 1**: A2.B3.1.3
        - **Attribute 2**: A2.B3.2.3
    - **CategoryB**: B4
        - **Attribute 1**: A2.B4.1.4
        - **Attribute 2**: A2.B4.2.4

{@ tabularlist_end() @}

**3 columns**: 

{@ tabularlist_begin( {all:3} ) @}

- **CategoryA**: A1
    - **CategoryB**: B1
        - **Attribute 1**: A1.B1.1.1
        - **Attribute 2**: A1.B1.2.1
    - **CategoryB**: B2
        - **Attribute 1**: A1.B2.1.2
        - **Attribute 2**: A1.B2.2.2
- **CategoryA**: A2
    - **CategoryB**: B3
        - **Attribute 1**: A2.B3.1.3
        - **Attribute 2**: A2.B3.2.3
    - **CategoryB**: B4
        - **Attribute 1**: A2.B4.1.4
        - **Attribute 2**: A2.B4.2.4

{@ tabularlist_end() @}

**4 columns, same as the default**: 

{@ tabularlist_begin( {all:4} ) @}

- **CategoryA**: A1
    - **CategoryB**: B1
        - **Attribute 1**: A1.B1.1.1
        - **Attribute 2**: A1.B1.2.1
    - **CategoryB**: B2
        - **Attribute 1**: A1.B2.1.2
        - **Attribute 2**: A1.B2.2.2
- **CategoryA**: A2
    - **CategoryB**: B3
        - **Attribute 1**: A2.B3.1.3
        - **Attribute 2**: A2.B3.2.3
    - **CategoryB**: B4
        - **Attribute 1**: A2.B4.1.4
        - **Attribute 2**: A2.B4.2.4

{@ tabularlist_end() @}

The plugin tries its best to produce a readable table which is consistent with the 
input tabularlist.


