# CrossWords test

{@ crosswords(1, { rows:20, cols: 30 }) @}
{@ crosswords_wordlist(1) @}

{@ crosswords(2, { rows:15, cols: 15 }) @}
{@ crosswords_wordlist(2) @}

{@ crosswords(3, {
    seed: 9,
    rows: 30,
    cols: 30,
    word_file: 'words_a' ,
    number_of_words: 30,
    difficulty: 'easy',
    title: 'Ese es el puzzle nº <b>21</b>',
    text: 'Coloque las palabras en el tablero.'
}) @}

{@ crosswords_wordlist(3, { chunks: 4 }) @}

{@ crosswords_begin(4, {
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

{@ crosswords_end() @}

{@ crosswords_wordlist(4) @}

{@ crosswords(5, {
seed: 3,
rows: 30,
cols: 30,
word_file: 'words_c' ,
number_of_words: 25,
difficulty: 'easy',
}) @}
{@ crosswords_wordlist(5) @}