# WordSearch test words from file

## Puzzle 21

{@ wordsearch({
    id: 21, 
    seed: 9,
    rows: 30, 
    cols: 30, 
    word_file: 'words_a' ,
    number_of_words: 30,
    difficulty: 'easy'
}) @}

{@ wordsearch_wordlist({ 
    id: 21,
    chunks: 4
}) @}


## Puzzle 22 with explicit words

{@ wordsearch_begin({
    id: 22,
    seed: 1,
    rows: 20,
    cols: 20, 
    title: 'El puzzle 22',
    text: 'Encuentre las palabras ocultas entre las letras.'
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

{@ wordsearch_wordlist({ id: 22 }) @}

