# LatexPlugin test 2

## Same as test 1

This has the same content as test 1 but in different order, to test the correct
generation of latex formula files without name collisions.

### Displayed (centered) formulas

Displayed formula with block delimiters (`A = 4 * \sqrt{x^2+1}`):
\[
A = 4 * \sqrt{x^2+1}
\]

Multiline formula with split (`A & = \frac{\pi r^2}{2} \\ & = \frac{1}{2} \pi r^2`):
\[ 
\begin{split}
A & = \frac{\pi r^2}{2} \\
  & = \frac{1}{2} \pi r^2
\end{split}
\]

### Inline formulas

Inline formula with inline delimiters (`u = \pi\cdot d`): \( u = \pi\cdot d \) consectetur adipisici elit.


## Additional to test 1

And this is an additional formula not found in test 1. 
\[
\begin{split}
p(x) = 3x^6 + 14x^5y + 590x^4y^2 & + 19x^3y^3\\ 
            & - 12x^2y^4 - 12xy^5 + 2y^6 - a^3b^3
\end{split}
\]