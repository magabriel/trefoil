# WordFillIn test

{@ wordfillin(1, { rows:20, cols: 30 }) @}
{@ wordfillin_wordlist(1) @}

{@ wordfillin(2, { rows:15, cols: 15 }) @}
{@ wordfillin_wordlist(2) @}

{@ wordfillin(3, {
    seed: 9,
    rows: 30,
    cols: 30,
    word_file: 'words_a' ,
    number_of_words: 30,
    difficulty: 'easy',
    title: 'Ese es el puzzle nº <b>21</b>',
    text: 'Coloque las palabras en el tablero.'
}) @}

{@ wordfillin_wordlist(3, { chunks: 4 }) @}

{@ wordfillin_begin(4, {
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

{@ wordfillin_end() @}

{@ wordfillin_wordlist(4) @}

{@ wordfillin(5, {
seed: 3,
rows: 30,
cols: 30,
word_file: 'words_c' ,
number_of_words: 25,
difficulty: 'easy',
}) @}
{@ wordfillin_wordlist(5) @}