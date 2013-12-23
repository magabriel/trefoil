## TypographyPlugin

This plugin replaces certain symbols with its typographic equivalents. It makes use of the `SmartyPants` library: <http://daringfireball.net/projects/smartypants/>

### Availability

This plugin is available for all editions.

### Usage

~~~.yaml
# <book-dir>/config.yml 
book:
    ....
    editions:
        <edition-name>
            plugins:
                enabled: [ TypographyPlugin ]
                options:
                    Typography:
                        checkboxes: true
                        fix_spanish_style_dialog: false
                           
~~~ 

### Description

The following symbols are replaced with its typographic equivalents whenever appropriate:

- Double quotes (`".."`) and *backtick* quotes (` `` `..`''`) are converted to typograhic double quotes ("..").
- Three dots (`...`) are converted to the ellipsis typographic symbol (...).
- Two dashes (`--`) are converted to em-dash (--).
- Two less-than or greater-than symbols (`<<` and `>>`) are converted to angle quotes (<<..>>).

Option `checkboxes` allows writing a checkbox-like sign, both unchecked and checked.

- Checkboxes can be writen as &#91; &#93; and &#91;/&#93; and will be converted to [ ] and [/].

Option `fix_spanish_style_dialog` fixes the usage of dash ('-') instead of em-dash ('--') for dialog 
transcription in Spanish.

N> ##### Note
N> 
N> The correct way of writing dialogs between characters in Spanish books is using the em-dash symbol 
N> ("—", called "raya" in Spanish) instead of using quotes like the English written dialogs. 
N> That is only the more obvious difference, but there are several other typographic conventions and
N> rules that must be followed when writing dialogs in Spanish books. 

#### Example of Spanish-style written dialog

The following example is extracted from "*The Adventures of Sherlock Holmes: A scandal in Bohemia*":

**Original dialog in English:**

> “Wedlock suits you,” he remarked. “I think, Watson, that you have put on seven and a half
> pounds since I saw you.”
> 
> “Seven!” I answered.

**Spanish translation:**

> --El matrimonio le sienta bien --comentó--. Yo diría, Watson, que ha engordado
> usted siete libras y media desde la última vez que le vi.
> 
> --Siete --respondí.

