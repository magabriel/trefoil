## PaperdlePlugin

This plugin allows generating _Paperdle puzzles_ automatically, like the following one:

{@ paperdle(1, {word:"PAPER"} ) @}

* * *

{@ paperdle_letters(1) @}

* * *

{@ paperdle_solution(1) @}

* * *

{@ paperdle_randomizer( 1 ) @}

* * *

{@ paperdle_decoder(1) @}

* * *

{@ paperdle_solution_decoder(1) @}

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
                enabled: [ Paperdle ]       
                options:
                    Paperdle:
                        alphabet: "ABCDEFGHIJKLMNOPQRSTUVWXYZ"
                        tries: 6
                        markers: "=?#"
                        strings:
                            board:
                                title: "Puzzle %s"
                                text: "Guess the word in the grid"
                                text2: >
                                    Use Table 1 and Table 2 to evaluate each letter in your guess.<br>
                                    Draw a circle over the correct letters, and a line over the misplaced letters.
                            letters:
                                title: "Letters"
                                text: "Cross out the used letters"
                            randomizer:
                                title: "Table 1"
                                text: "Look up each letter in your guess to get the key for Table 2"
                            decoder:
                                title: "Table 2"
                                text: "Look up the key you got from Table 1 to get the letter's evaluation"
                                text2: >
                                    O ➔ Letter is correct<br>
                                    / ➔ Letter is misplaced<br>
                                    # ➔ Wrong letter
                            solution:
                                title: "Solution"
                                text: "The solution is encoded. Use Table 3 to decode it."
                            solution-decoder:
                                title: "Table 3"
                                text: "Look up each component of the encoded solution to decode it"
~~~

#### Plugin options

This plugin provides several options for customization. All of them are optional and have sensible defaults:

- `alphabet`: The alphabet to use for the puzzle. Default is `"ABCDEFGHIJKLMNOPQRSTUVWXYZ"`.
- `tries`: The number of attempts allowed for the player. Default is `6`.
- `markers`: The symbols used for evaluation. Default is `"=?#"`.
- `strings`: Customizable strings for different parts of the puzzle.

This feature uses several *trefoil markers* to achieve its complex functionality.

{{ fragment('note-trefoil-markers.md') }}

~~~.html
{@ paperdle(id, {options} ) @}

{@ paperdle_letters(id) @}

{@ paperdle_solution(id) @}

{@ paperdle_randomizer(id) @}

{@ paperdle_decoder(id) @}

{@ paperdle_solution_decoder(id) @}
~~~

**Arguments for `paperdle()`**

- `id`: A numeric identifier for a particular puzzle. It is used to link all the other trefoil marker calls together.
- `options`: A list of arguments in the form `{key:value, ... ,key:value}`. They are:
    - `word`: The word to guess in the puzzle.
    - `alphabet`: The alphabet to use for the puzzle.
    - `tries`: The number of attempts allowed for the player.
    - `markers`: The symbols used for evaluation.
    - `title`: The title of the puzzle. It can include HTML markup.
    - `text`: A text to show before the puzzle. It can include HTML markup.
    - `text2`: Another text. It can include HTML markup.

**Arguments for `paperdle_randomizer()`**

- `id`: The id of the puzzle to show the randomizer table.
- `options`: A list of arguments in the form `{key:value, ... ,key:value}`. They are:
    - `title`: The title of the randomizer table.
    - `text`: A text to show before the randomizer table.
    - `text2`: Another text. It can include HTML markup.

**Arguments for `paperdle_decoder()`**

- `id`: The id of the puzzle to show the decoder table.
- `options`: A list of arguments in the form `{key:value, ... ,key:value}`. They are:
    - `title`: The title of the decoder table.
    - `text`: A text to show before the decoder table.
    - `text2`: Another text. It can include HTML markup.

**Arguments for `paperdle_solution()`**

- `id`: The id of the puzzle to show the solution.
- `options`: A list of arguments in the form `{key:value, ... ,key:value}`. They are:
    - `title`: The title of the solution.
    - `text`: A text to show before the solution.
    - `text2`: Another text. It can include HTML markup.

**Arguments for `paperdle_solution_decoder()`**

- `id`: The id of the puzzle to show the solution decoder table.
- `options`: A list of arguments in the form `{key:value, ... ,key:value}`. They are:
    - `title`: The title of the solution decoder table.
    - `text`: A text to show before the solution decoder table.
    - `text2`: Another text. It can include HTML markup.

**Example**

~~~.html
{@ paperdle( 100, {
        word: "PAPER",
        alphabet: "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
        tries: 6,
        markers: "=?#",
        title: "This is the puzzle",
        text: "Guess the word!",
        text2: "Use the randomizer and decoder tables to evaluate your guesses."
}) @}

{@ paperdle_randomizer( 100 ) @}

{@ paperdle_decoder( 100 ) @}

{@ paperdle_solution( 100, { title: "This is the solution"} ) @}

{@ paperdle_solution_decoder( 100, { title: "Solution Decoder"} ) @}
~~~