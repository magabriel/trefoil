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
\frac{a^2 - b^2}{a + b} = a - b
\]