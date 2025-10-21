# WordFillIn test

## Default 1
{@ wordfillin(1, { rows:20, cols: 30 }) @}
{@ wordfillin_wordlist(1) @}

## Default 2
{@ wordfillin(2, { rows:15, cols: 15 }) @}
{@ wordfillin_wordlist(2) @}

## Wordlist unsorted
{@ wordfillin(3, {
    seed: 9,
    rows: 30,
    cols: 30,
    word_file: 'words_a' ,
    number_of_words: 30,
    difficulty: 'easy',
    title: 'Ese es el puzzle nº <b>3</b>',
    text: 'Coloque las palabras en el tablero.'
}) @}
{@ wordfillin_wordlist(3, { chunks: 4 }) @}

## Wordlist sorted
{@ wordfillin(4, {
    seed: 9,
    rows: 30,
    cols: 30,
    word_file: 'words_a' ,
    number_of_words: 30,
    difficulty: 'easy',
    title: 'Ese es el puzzle nº <b>4</b>',
    text: 'Coloque las palabras en el tablero.'
}) @}
{@ wordfillin_wordlist(4, { chunks: 4, sorted: true }) @}

## Wordlist by lengths
{@ wordfillin(5, {
    seed: 9,
    rows: 30,
    cols: 30,
    word_file: 'words_a' ,
    number_of_words: 30,
    difficulty: 'easy',
    title: 'Ese es el puzzle nº <b>5</b>',
    text: 'Coloque las palabras en el tablero.',
    by_length_text: '<b>%s letters</b>'
}) @}
{@ wordfillin_wordlist(5, { chunks: 4, by_length: true }) @}

## Wordlist with embedded word list
{@ wordfillin_begin(6, {
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

{@ wordfillin_wordlist(6) @}

## Words from "C" list
{@ wordfillin(7, {
seed: 3,
rows: 30,
cols: 30,
word_file: 'words_c' ,
number_of_words: 25,
difficulty: 'easy',
}) @}
{@ wordfillin_wordlist(7) @}