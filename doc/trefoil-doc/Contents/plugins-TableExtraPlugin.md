## TableExtraPlugin

This plugin provides extra functionality to Markdown tables, adding the ability to "colspan" or "rowspan" cells.

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
                enabled: [ TableExtra ]
~~~ 

    
### Description

Markdown syntax for tables is pretty limited:

~~~
Column 1  | Column 2  | Column 3
----------|-----------|------------
1.One     | 1.Two     | 1.Three
2.One     | 2.Two     | 2.Three
~~~

will render as:

Column 1  | Column 2  | Column 3
----------|-----------|------------
1.One     | 1.Two     | 1.Three
2.One     | 2.Two     | 2.Three


#### Adding rowspanned cells

A cell containig only `"` (a double quote) will be joined with the above cell in the previous row:
 
~~~
Column 1  | Column 2  | Column 3
----------|-----------|------------
1.One     | 1.Two     | 1.Three
  "       | 2.Two     | 2.Three
~~~

This will render as:

Column 1  | Column 2  | Column 3
----------|-----------|------------
1.One     | 1.Two     | 1.Three
  "       | 2.Two     | 2.Three
 

#### Adding colpanned cells

An empty cell will be joined whith the preceding cell in the same row:

~~~
Column 1  | Column 2  | Column 3
----------|-----------|------------
1.One                || 1.Three
2.One     | 2.Two     | 2.Three
~~~

This will render as:

Column 1  | Column 2  | Column 3
----------|-----------|------------
1.One                || 1.Three
2.One     | 2.Two     | 2.Three

T> ##### Tip
T> To avoid a blank cell to be interpreted as colspanned, enter `&nbsp;` as its contents, as in the following example.

Column 1  | Column 2  | Column 3
----------|-----------|------------
1.One     |   &nbsp;  | 1.Three
2.One     | 2.Two     | 2.Three


#### A more complex example

The following example mixes rowspanned and colspanned cells and makes use of table column alignment syntax:

~~~
Column 1  | Column 2  | Column 3
---------:|:----------|:----------:
1.One                || 1.Three
"                    || 2.Three
3.One     | 3.Two     | 3.Three
4.One     | 4.Two     | 4.Three
~~~

The rendered result will be:

Column 1  | Column 2  | Column 3
---------:|:----------|:----------:
1.One                || 1.Three
"                    || 2.Three
3.One     | 3.Two     | 3.Three
4.One     | 4.Two     | 4.Three

 