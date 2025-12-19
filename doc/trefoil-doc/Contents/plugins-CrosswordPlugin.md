## CrosswordPlugin

This plugin allows rendering _crossword puzzles_ from IPUZ files, like the following one:

{@ crossword(1, {
    ipuz_file: 'crossword_1_es.ipuz',
    title: 'Crossword %s',
    text: 'Solve the crossword puzzle.'
}) @}

**Clues across**

{@ crossword_clues_across(1) @}

**Clues down**

{@ crossword_clues_down(1) @}

<div class="clearfix"></div>

**Solution**

{@ crossword_solution(1) @}

**Anagram clue**

{@ crossword_anagram_clue(1) @}

**Anagram word**

{@ crossword_anagram_word(1) @}

### Availability

This plugin is available for all editions.

### Usage

~~~.yaml
# <book-dir>/config.yml
book:
    editions:
        <edition-name>
            plugins:
                enabled: [ Crossword ]       
                options:
                    Crossword:
                        grid_size: 20
                        solution_grid_size: 10
                        ipuz_folder: puzzles
                        strings:
                            title: "Crossword %s"
                            text: ""
                            solution_title: "Solution %s"
                            across_title: "Across"
                            down_title: "Down"
                            anagram_clue_title: "Anagram clue"
                            anagram_word_title: "Answer"

~~~

#### Plugin options

All options are optional and have sensible defaults that are shown in the previous YAML block.

- `grid_size`: Size of the puzzle grid cells in pixels.
- `solution_grid_size`: Size of the solution grid cells in pixels.
- `ipuz_folder`: The folder containing the IPUZ files, relative to the book's `Contents` directory.
- `strings`: Default strings used for the puzzle and solution rendering.
    - `title`: The default title for the puzzle. It can include HTML markup. The puzzle id can be included using sprintf syntax (e.g., `"Crossword %s"`).
    - `text`: A text to show before the puzzle. It can include HTML markup.
    - `solution_title`: The default title for the solution. It can include HTML markup and the puzzle id using sprintf syntax.
    - `across_title`: The title for the "Across" clues section.
    - `down_title`: The title for the "Down" clues section.
    - `anagram_clue_title`: The title for the anagram clue section (if the puzzle includes an anagram).
    - `anagram_word_title`: The title for the anagram word/answer section.

This feature uses several *trefoil markers* to achieve its complex functionality.

{{ fragment('note-trefoil-markers.md') }}

#### IPUZ File Format

The plugin uses IPUZ files to define the crossword puzzle. IPUZ is a standard format for representing 
puzzles in JSON. The plugin expects the IPUZ file to contain the following sections:

- `puzzle`: The grid definition with cell numbers and blocked cells (marked with `#`).
- `solution`: The solved grid with letters.
- `clues`: An object with `Across` and `Down` arrays containing the clue definitions.

Additionally, the plugin recognizes custom extensions for anagram puzzles:
- `cwgen:anagram-word`: The anagram word answer.
- `cwgen:anagram-clue`: The clue for the anagram.

Cells can also have styles, such as circles to indicate anagram letters:

~~~.json
{
  "cell": 6,
  "style": {
    "shapebg": "circle"
  }
}
~~~

#### Trefoil Markers

The plugin provides the following trefoil markers:

~~~.html
{@ crossword(id, {options}) @}

{@ crossword_clues_across(id, {options}) @}

{@ crossword_clues_down(id, {options}) @}

{@ crossword_solution(id, {options}) @}

{@ crossword_anagram_clue(id, {options}) @}

{@ crossword_anagram_word(id, {options}) @}
~~~

**Arguments for `crossword()`**

This is the main marker that renders the puzzle grid.

- `id`: A numeric identifier for the puzzle. It is used to link all the other trefoil marker calls together.
- `options`: A list of arguments in the form {key:value, ..., key:value}. They are:
    - `ipuz_file`: **(Required)** The filename of the IPUZ file containing the puzzle definition. The file is located in the folder specified by `ipuz_folder` in the plugin options.
    - `title`: The title of the puzzle. 
      - Overrides the default `strings.title` option.
      - It can include HTML markup.
      - The puzzle id can be included using sprintf syntax (e.g., `"Crossword <b>%s</b>"`).
    - `text`: A text to show before the puzzle. Overrides the default `strings.text` option.

**Arguments for `crossword_clues_across()`**

Renders the "Across" clues list.

- `id`: The id of the puzzle to show the clues for.
- `options`: A list of arguments in the form {key:value, ..., key:value}. They are:
    - `title`: The title for the clues section. Overrides the default `strings.across_title` option.

**Arguments for `crossword_clues_down()`**

Renders the "Down" clues list.

- `id`: The id of the puzzle to show the clues for.
- `options`: A list of arguments in the form {key:value, ..., key:value}. They are:
    - `title`: The title for the clues section. Overrides the default `strings.down_title` option.

**Arguments for `crossword_solution()`**

Renders the solved puzzle grid with all letters filled in.

- `id`: The id of the puzzle to show the solution for.
- `options`: A list of arguments in the form {key:value, ..., key:value}. They are:
    - `solution_title`: The title of the solution. 
      - Overrides the default `strings.solution_title` option.
      - It can include HTML markup.
      - The puzzle id can be included using sprintf syntax.

**Arguments for `crossword_anagram_clue()`**

Renders the anagram clue (if the puzzle includes one).

- `id`: The id of the puzzle.
- `options`: A list of arguments in the form {key:value, ..., key:value}. They are:
    - `title`: The title for the anagram clue section. Overrides the default `strings.anagram_clue_title` option.

**Arguments for `crossword_anagram_word()`**

Renders the anagram word/answer (typically used in the solutions section).

- `id`: The id of the puzzle.
- `options`: A list of arguments in the form {key:value, ..., key:value}. They are:
    - `title`: The title for the anagram word section. Overrides the default `strings.anagram_word_title` option.

[comment]: <> (@formatter:off)
N> ##### About anagrams
N> Some crossword puzzles include an anagram feature where certain cells in the grid
N> are marked with circles. The letters in these cells, when rearranged, form a word
N> that answers the anagram clue. Use `crossword_anagram_clue()` to show the clue
N> and `crossword_anagram_word()` to show the answer (usually in the solutions section).
[comment]: <> (@formatter:on)

**Example**

~~~.html
{@ crossword(1, {
    ipuz_file: 'crossword_1_es.ipuz',
    title: 'Crossword puzzle <b>%s</b>',
    text: 'Fill in the grid with the correct letters.'
}) @}

#### Clues

{@ crossword_clues_across(1, {title: 'Horizontal'}) @}
{@ crossword_clues_down(1, {title: 'Vertical'}) @}

#### Anagram
{@ crossword_anagram_clue(1) @}
~~~

And in a separate solutions chapter:

~~~.html
# Solutions

{@ crossword_solution(1, {solution_title: 'Solution for puzzle %s'}) @}

#### Anagram answer
{@ crossword_anagram_word(1) @}
~~~

#### Styling

The plugin generates HTML with specific CSS classes that can be styled. The main classes are:

- `.crossword-puzzle-container`: Container for the puzzle.
- `.crossword-puzzle`: The puzzle grid itself.
- `.crossword-title`: The puzzle title.
- `.crossword-text`: The puzzle description text.
- `.crossword-clues-across-container`: Container for across clues.
- `.crossword-clues-across-title`: Title for across clues.
- `.crossword-clues-across`: The across clues list.
- `.crossword-clues-down-container`: Container for down clues.
- `.crossword-clues-down-title`: Title for down clues.
- `.crossword-clues-down`: The down clues list.
- `.crossword-solution-container`: Container for the solution.
- `.crossword-solution`: The solution grid.
- `.crossword-anagram-clue-container`: Container for the anagram clue.
- `.crossword-anagram-clue`: The anagram clue text.
- `.crossword-anagram-word-container`: Container for the anagram word.
- `.crossword-anagram-word`: The anagram word/answer.
