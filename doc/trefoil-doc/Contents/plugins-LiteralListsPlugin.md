## LiteralLists Plugin

This plugin adds support for _literal lists_, a kind of ordered lists where
the list elements can be some literals instead of numbers.

### Availability

This plugin is available for all editions.

### Usage

~~~.yaml
# <book-dir>/config.yml 
book:
    editions:
        <edition-name>
            plugins:
                enabled: [ LiteralLists ]       
~~~ 

### Description

A literal list is an ordered list using literals other than numbers.

The list literal can be one of the following:

- "a)", "b)"... (a letter followed by a closing parenthesis)
- "I)", "II)"...(a latin numeral followed by a closing parenthesis)
- "1º", "2º"... (a number followed by the masculine sign)
- "1ª", "2ª"... (a number followed by the feminine sign)

The plugin detects the list by the type of starting literal of the
first item in an unordered list, and adds the class "list-literal"
to the `<ul>'  tag to alow styling.

At the HTML level, the following input:

~~~.html
<ul>
    <li>a) First item.</li>
    ...
    <li>x) Last item.</>
</ul>
~~~

Is transformed into the following output which can be CSS styled
to look like a real list with `a)...n)` literals.

~~~.html
<ul class="list-literal">
    <li>a) First item.</li>
    ...
    <li>x) Last item.</>
</ul>
~~~

### Example

**Type a)**

~~~.markdown
- a) The first item.
- b) The second item.
- c) The third item.
~~~

Will be rendered as:

- a) The first item.
- b) The second item.
- c) The third item.

**Type I)**

~~~.markdown
- I) The first item.
- II) The second item.
- II) The third item.
~~~

Will be rendered as:

- I) The first item.
- II) The second item.
- III) The third item.

**Type 1º**

~~~.markdown
- 1º The first item.
- 2º The second item.
- 3º The third item.
~~~

Will be rendered as:

- 1º The first item.
- 2º The second item.
- 3º The third item.

**Type 1ª**

~~~.markdown
- 1ª The first item.
- 2ª The second item.
- 3ª The third item.
~~~

Will be rendered as:

- 1ª The first item.
- 2ª The second item.
- 3ª The third item.
