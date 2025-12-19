# Crossword test

This is a test of the Crossword plugin.

{@ crossword(1, {
    seed: 1,
    ipuz_file: 'crossword_1_es.ipuz' ,
    title: 'Este es el puzzle nº <b>1</b>',
    text: 'Resuelva el crucigrama.',
}) @} 

#### Anagram clue
{@ crossword_anagram_clue(1) @}

#### Clues
{@ crossword_clues_across(1) @}
{@ crossword_clues_down(1) @}


