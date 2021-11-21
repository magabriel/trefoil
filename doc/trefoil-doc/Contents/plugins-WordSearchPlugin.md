## WordSearchPlugin

This plugin allows generating _wordsearch puzzles_ automatically, like the following one:

[comment]: <> (@formatter:off)
{@ wordsearch( 1, {rows:10, cols:10} ) @}
[comment]: <> (@formatter:on)

{@ wordsearch_wordlist( 1 ) @}

<div class="clearfix"></div>

### Availability

This plugin is available for all editions.

### Usage

~~~.yaml
# <book-dir>/config.yml
book:
    editions:
        <edition-name>
            plugins:
                enabled: [ WordSearch ]       
                options:
                    WordSearch:
                        grid_size: 20
                        solution_grid_size: 10
                        highlight_type: shadow
                        word_files:
                          - { label: my_label_1, name: my/words/file/path/and/name_1.txt }
                          - { label: my_label_n, name: my/words/file/path/and/name_n.txt }
                        default:
                          filler: "ABCDEFGHIJKLMNOPQRSTUVWXYZ"
                          difficulty: "medium"
                        strings:
                          title: ""
                          solution_title: ""
                          text: "" 
                          text2: ""
                          difficulty:
                            easy: "Dificulty: Easy"
                            medium: "Dificulty: Medium"
                            hard: "Dificulty: Hard"
                            very-hard: "Dificulty: Very Hard"

~~~

#### Plugin options

This is a complex plugin with lots of options to allow extensive customization. All of them are optional and have
sensible defaults that are shown in the previous YAML block.

- `grid_size`: Size of the puzzle grid in pixels.
- `solution_grid_size`: Size of the solution grid in pixels.
- `highlight_type`: The highlight type of the solution. Either `line` or `shadow`.
- `word_files`: Labels for the word files to use with the automatic word selection feature. The name can include a path
  and its relative to the book's `Content` directory.
- `default`: Some default values to use.
    - `filler`: A string with all the letters or signs to use as filler for empty puzzle places.
    - `difficulty`: The puzzle difficulty. Either `easy`, `medium`, `hard`, or `very-hard`.
- `string`: Some string used on the puzzle and solution rendering.
    - `easy`: Text for easy puzzles.
    - `medium`: Text for medium puzzles.
    - `hard`: Text for hard puzzles.
    - `very-hard`: Text for very hard puzzles.

This feature uses several *trefoil markers* to achieve it complex functionality.

{{ fragment('note-trefoil-markers.md') }}

#### Case 1: Word search with explicit words

~~~.html
{@ wordsearch_begin(id, {options} ) @}

   ...list of words...
   
{@ wordsearch_end() @}

{@ wordsearch_wordlist(id, {options} ) @}    

{@ wordsearch_solution(id, {options} ) @}
~~~

**Arguments for `wordsearch_begin()`**

- `id`: A numeric identifier for a particular puzzle. It is used to link all the other trefoil marker calls together.
- `options`: A list of argument in the form {key:value, ... ,key:value}. They are:
    - `rows`: The number of rows of the puzzle.
    - `cols`: The number of columns of the puzzle.
    - `filler`: A string with all the letters or signs to use as filler for empty puzzle places.
    - `title`: The title of the puzzle. 
      - It can include HTML markup.
      - The puzzle id can be included one or two times using sprintf syntax.
      - Example: `<span>%s</span>Puzzle<span>%s</span>`
    - `text`: A text to show before the puzzle. It can include HTML markup.
    - `text2`: Another text. It can include HTML markup.
    - `difficulty`: Difficulty of the puzzle. Either `easy`, `medium`, `hard`, or `very-hard`.
    - `seed`: The seed used for the pseudo-random numbers generator (see explanation below). If not provided, the
      pluting will generate a different puzzle on each run, so it is better to set a specific seed to get repeatable
      results.

- `list of words` is a list with the words to use in the puzzle.

[comment]: <> (@formatter:off)
N> ##### Puzzle difficulty
N> The puzzle difficulty can be graduated as follows:
N> 
N> - `easy`: Not very long words, never reversed.
N> 
N> - `medium`: Not very long words, with a few of them reversed.
N> 
N> - `hard`: Longer words, with a few of them reversed.
N> 
N> - `very-hard`: Longer words, always reversed.
[comment]: <> (@formatter:on)

**Arguments for `wordsearch_wordlist()`**

- `id`: The id of the puzzle to show the wordlist.
- `options` A list of argument in the form {key:value, ... ,key:value}. They are:
    - `sorted`: Whether the list should be shown sorted or unsorted. Default is unsorted.
    - `chunks`: The number of parts that the wordlist should be divided into. Useful for styling the list in columns.
      Default is 1.

**Arguments for `wordsearch_solution()`**

- `id`: The id of the puzzle to show the wordlist.
- `options` A list of argument in the form {key:value, ... ,key:value}. They are:
    - `title`: The title of the solution.
      - It can include HTML markup.
      - The puzzle id can be included one or two times using sprintf syntax.
      - Example: `Soluton <span>%s</span>`
    - `text`: A text to show before the solution.
      - It can include HTML markup.
      
[comment]: <> (@formatter:off)
N> ##### About pseudo-random numbers generation
N> This plugin uses a pseudo-random number generator to generate all the needed variations.
N> The difference with a true random numbers generator is that, while the true random generator
N> will generate a true random sequence, the pseudo-random one will generate a repeatable sequence
N> that is only apparently random.  
N> Pseudo-random number generators use a "seed" to start the sequence. Two sequences using the
N> same seed will be identical.
[comment]: <> (@formatter:on)

**Example**

~~~.html
{@ wordsearch_begin( 100, {
        seed: 1,
        rows: 10,
        cols: 10,
        title: 'This is the puzzle',
        text: 'Days of the week in Spanish.',
        text2: 'Find the words hidden between the letters.',
        difficulty: 'hard'
}) @}

- Lunes
- Martes
- Miércoles
- Jueves
- Viernes
- Sábado
- Domingo

{@ wordsearch_end() @}

{@ wordsearch_wordlist( 100 ) @}

{@ wordsearch_solution( 100, { title: 'This is the solution'} ) @}
~~~

This will render as follows:

[comment]: <> (@formatter:off)
{@ wordsearch_begin( 100, { 
    seed: 1, 
    rows: 10, 
    cols: 10, 
    title: 'This is the puzzle',
    text: 'Days of the week in Spanish.',
    text2: 'Find the words hidden between the letters.',
    difficulty: 'hard'
}) @}

- Lunes
- Martes
- Miércoles
- Jueves
- Viernes
- Sábado
- Domingo

{@ wordsearch_end() @}
[comment]: <> (@formatter:on)

{@ wordsearch_wordlist( 100 ) @}

<div class="clearfix"></div>

{@ wordsearch_solution( 100, { title: 'This is the solution'} ) @}

<div class="clearfix"></div>

#### Case 2: Word search with random words from file

~~~.html
{@ wordsearch(id, {options} ) @}

{@ wordsearch_wordlist(id, {options} ) @}    

{@ wordsearch_solution(id, {options} ) @}
~~~

**Arguments for `wordsearch()`**

This marker accepts the same arguments as `wordsearch_begin()` but needs additional arguments:

- `options`:
    - `word_file`: The label of a file with words to choose from, as specified in the plugin options. If no file is
      specified the marker will generate a puzzle with a default content (useful for quick testing);
    - `number_of_words` : The number of words to randomly select from the file.

**Arguments for `wordsearch_wordlist()`**

Same usage as in the previous case.

**Arguments for `wordsearch_solution()`**

Same usage as in the previous case.

**Example**

Assuming that `config.yml` contains:

~~~.yaml
# <book-dir>/config.yml
book:
    ...
    plugins:
        ...
        options:
            WordSearch:
                ...
                word_files:
                    - { label: word_file_1, name: word_file_1.txt }
                ...
~~~

And the following code:

~~~.html
{@ wordsearch( 101, {
        seed: 1,
        rows: 10,
        cols: 10,
        title: 'This is the puzzle number %s',
        text: 'Days of the week in Spanish.',
        text2: 'Find the words hidden between the letters.',
        difficulty: 'hard',
        word_file: 'word_file_1',
        number_of_words: 10
        }) @}

{@ wordsearch_wordlist( 101 ) @}

{@ wordsearch_solution( 101, { title: 'Solution %s'} ) @}
~~~

This will render as follows:

[comment]: <> (@formatter:off)
{@ wordsearch( 101, {
    seed: 1,
    rows: 10,
    cols: 10,
    title: 'This is the puzzle number %s',
    text: 'Days of the week in Spanish.',
    text2: 'Find the words hidden between the letters.',
    difficulty: 'hard',
    word_file: 'word_file_1',
    number_of_words: 10
}) @}
[comment]: <> (@formatter:on)

{@ wordsearch_wordlist( 101 ) @}

<div class="clearfix"></div>

{@ wordsearch_solution( 101, { title: 'Solution %s'} ) @}

<div class="clearfix"></div>