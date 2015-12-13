# TwigExtension test

The title of this book is **{{ book.title }}**

{{ itemtoc() }}

## File function (DEPRECATED)

### Just a level 3

Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas non venenatis turpis, nec bibendum urna. Curabitur vitae hendrerit elit. Quisque nec velit ut nunc pellentesque tincidunt.

{{ file('chapter1-1.md', {'variable1' : 'v1', 'variable2': 'v2'} ) }}

{{ file('chapter1-2.md', {'variable1' : 'v1', 'variable3': 'v3'} ) }}


## Fragment function

### Without tags

Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas non venenatis turpis, nec bibendum urna. Curabitur vitae hendrerit elit. Quisque nec velit ut nunc pellentesque tincidunt.

{{ fragment('chapter1-1.md', {'variable1' : 'v1', 'variable2': 'v2'} ) }}

{{ fragment('chapter1-2.md', {'variable1' : 'v1', 'variable3': 'v3'} ) }}

### With tags

Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas non venenatis turpis, nec bibendum urna. Curabitur vitae hendrerit elit. Quisque nec velit ut nunc pellentesque tincidunt.


{{ fragment('chapter1-1.md', {'variable1' : 'v1 in blockquote', 'variable2': 'v2 no class'}, {'tag' : 'blockquote'} ) }}

{{ fragment('chapter1-1.md', {'variable1' : 'v1 in blockquote', 'variable2': 'v2 class shaded'}, {'tag' : 'blockquote', 'class' : 'shaded'} ) }}

{{ fragment('chapter1-2.md', {'variable1' : 'v1 in implied div', 'variable3': 'v3 class bordered'}, {'class' : 'bordered'} ) }}

{{ fragment('chapter1-2.md', {'variable1' : 'v1 in div', 'variable3': 'v3 class shaded and bordered'}, {'class' : 'shaded bordered'} ) }}
