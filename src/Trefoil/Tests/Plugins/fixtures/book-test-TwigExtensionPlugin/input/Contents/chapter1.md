# TwigExtension test

The title of this book is **{{ book.title }}**

## ItemToc

{{ itemtoc() }}

## File function

{{ file('chapter1-1.md', {'variable1' : 'v1', 'variable2': 'v2'} ) }}

{{ file('chapter1-2.md', {'variable1' : 'v1', 'variable3': 'v3'} ) }}
