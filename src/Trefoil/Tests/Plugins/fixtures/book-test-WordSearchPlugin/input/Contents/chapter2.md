# WordSearch test words from file

## Puzzle 21

{@ wordsearch(21, { 
    seed: 9,
    rows: 30, 
    cols: 30, 
    word_file: 'words_a' ,
    number_of_words: 30,
    difficulty: 'easy',
    title: 'Ese es el puzzle nº <b>21</b>',
    text: 'Encuentre las palabras ocultas entre las letras.'
}) @}

{@ wordsearch_wordlist(21, { chunks: 4 }) @}

## Puzzle 22 with custom words

{@ wordsearch_begin(22, {
    seed: 1,
    rows: 20,
    cols: 20, 
}) @}

- Lunes
- Martes
- Miércoles
- Jueves
- Viernes
- Sábado
- Domingo
- Añadido

{@ wordsearch_end() @}

{@ wordsearch_wordlist(22) @}

