# WordSearch test

{@ wordsearch(1, { rows:12, cols: 30 }) @}
{@ wordsearch_wordlist(1) @}

{@ wordsearch(2, { rows:15, cols: 15 }) @}
{@ wordsearch_wordlist(2) @}

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

