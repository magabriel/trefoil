# TwigExtension test

The title of this book is **{{ book.title }}**

{{ itemtoc() }}

## File function

### Just a level 3

Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas non venenatis turpis, nec bibendum urna. Curabitur vitae hendrerit elit. Quisque nec velit ut nunc pellentesque tincidunt.

{{ file('chapter1-1.md', {'variable1' : 'v1', 'variable2': 'v2'} ) }}

{{ file('chapter1-2.md', {'variable1' : 'v1', 'variable3': 'v3'} ) }}
