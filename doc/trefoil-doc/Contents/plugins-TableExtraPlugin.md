## TableExtraPlugin

This plugin provides extra functionality to Markdown tables: 

- Ability to "colspan" or "rowspan" cells.

- Multiline cells.

- Automatic header cells for headless tables.

### Availability

This plugin is available for all editions.


### Usage

~~~.yaml
# <book-dir>/config.yml 
book:
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

A cell containig only `"` or `'` (a double or single quote) will be joined with the 
above cell in the previous row:
 
~~~
Column 1  | Column 2  | Column 3
----------|-----------|------------
1.One     | 1.Two     | 1.Three
  "       | 2.Two     | 2.Three
1.One     | 1.Two     | 1.Three
  '       | 2.Two     | 2.Three  
~~~

This will render as:

Column 1  | Column 2  | Column 3
----------|-----------|------------
1.One     | 1.Two     | 1.Three
  "       | 2.Two     | 2.Three
1.One top | 1.Two     | 1.Three
  '       | 2.Two     | 2.Three
 
Usin *single quote* instead of *double quote* will make the rowspanned cell contents 
align to the cell top of instead than the middle.

#### Adding colspanned cells

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
T> To avoid a blank cell to be interpreted as colspanned, enter `&nbsp;` 
T> as its contents, as in the following example.

Column 1  | Column 2  | Column 3
----------|-----------|------------
1.One     |   &nbsp;  | 1.Three
2.One     | 2.Two     | 2.Three


#### A more complex example

The following example mixes rowspanned and colspanned cells and makes use 
of table column alignment syntax:

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


#### Multiline cells

This is a table with multiline cells:

~~~
Column 1  | Column 2  | Column 3
----------|-----------|------------
One       | Two       | Tree: +
          |           | - Three            +
          |           | - Three continued  +          
          |           | - Three continued 2
One       | Two       | Three
~~~

Which will be rendered as:

Column 1  | Column 2  | Column 3
----------|-----------|------------
One       | Two       | Tree: +
          |           | - Three            +
          |           | - Three continued  +          
          |           | - Three continued 2
One       | Two       | Three

#### Automatic header cells for headless tables

This is a headless table:

~~~
 | | |
----------|-----------|------------
One 1     | Two 1     | Three 1
One 2     | Two 2     | Three 2
~~~

Normally rendered as:

 | | |
----------|-----------|------------
One 1     | Two 1     | Three 1
One 2     | Two 2     | Three 2

By adding strong emphasis to some cells we have:

~~~
 | | |
----------|-----------|------------
**One 1** | Two 1     | Three 1
One 2     | Two 2     | Three 2
**One 3** | Two 3     | Three 3
~~~

 | | |
----------|-----------|------------
**One 1** | Two 1     | Three 1
One 2     | Two 2     | Three 2
**One 3** | Two 3     | Three 3

This can lead to interesting effects:

~~~
 | | |
----------|-----------|------------
**One 1** | | 
One 2     | Two 2     | Three 2
**One 3** | |
One 4     | Two 4     | Three 4
~~~

 | | |
----------|-----------|------------
**One 1** | | 
One 2     | Two 2     | Three 2
**One 3** | |
One 4     | Two 4     | Three 4

