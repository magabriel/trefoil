# Different difficulties

{@ crosswords(11, {
    seed: 1,
    rows: 30,
    cols: 30,
    word_file: 'words_a' ,
    number_of_words: 30,
    difficulty: 'easy',
}) @}
{@ crosswords_wordlist(11, { chunks: 4 }) @}

{@ crosswords(12, {
    seed: 1,
    rows: 30,
    cols: 30,
    word_file: 'words_a' ,
    number_of_words: 30,
    difficulty: 'medium',
}) @}
{@ crosswords_wordlist(12, { chunks: 4 }) @}

{@ crosswords(13, {
    seed: 1,
    rows: 30,
    cols: 30,
    word_file: 'words_a' ,
    number_of_words: 30,
    difficulty: 'hard',
}) @}
{@ crosswords_wordlist(13, { chunks: 4 }) @}

{@ crosswords(14, {
    seed: 1,
    rows: 30,
    cols: 30,
    word_file: 'words_a' ,
    number_of_words: 30,
    difficulty: 'very-hard',
}) @}
{@ crosswords_wordlist(14, { chunks: 4 }) @}
