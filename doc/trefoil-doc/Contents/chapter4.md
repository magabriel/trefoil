Themes system enhancements
==========================

Themes functionality has been greatly enhanced in **trefoil**, 
making them more flexible and easy to customize. Also, **trefoil** 
themes can provide not only CCS styles and HTML templates but 
also images and fonts.

{{ itemtoc() }}

## Trefoil themes

**trefoil** comes with some standard themes:

- `TrefoilOne` is a classic-looking theme, suitable for novels, 
  essays and other literary works.

- `TrefoilTwo` is a modern theme. This book uses it. 

To use them, as always, just put the name of the theme in the 
book's `config.yml`:

~~~.yaml
# <book-dir>/config.yml 
book:
    ....
    editions:
        <edition-name>
            ...
            theme: TrefoilTwo
~~~

The theme definitions will be looked up into `<trefoil-dir>/app/Resources/Themes`. 
If the theme is not found there, **trefoil** will try to use an 
standard **easybook** theme of the same name. If that is also missing, 
the standard `Base` theme will be used, which is likely to cause 
problems if you activated some plugin that requires a non-standard template.

N> ##### Note
N> **trefoil** themes are backwards-compatible with **easybook** themes, 
N> meaning that any book that can be published by **easybook** can also be
N> published by **trefoil**. Of course, you will need to use an **trefoil**
N> theme to make use of its new features.

## Custom themes

**trefoil** allows creating custom themes. Their structure is the same than 
the standard **trefoil** themes but can be created anywhere in the file system, 
allowing the creation of a personal theme library without the need to modify 
the **trefoil** `app/Resources` directory.

To use a custom theme you just need to invoke the `book publish` command with 
the new optional argument `--themes_dir`:

~~~.bash
book publish my-book-slug my-edition --themes_dir=../my/themes/directory
~~~

Example:

~~~.bash
book publish the-origin-of-species ebook --themes_dir=~/themes/trefoil
~~~

## Structure of a theme

A theme, whether standard or custom, must follow this structure:

~~~
<themes-dir>
└─ <theme-name>
   ├─ Common      <== Common definitions
   │  ├─ Contents    
   │  ├─ Resources
   │  │  ├─ Fonts
   │  │  └─ images
   │  └─ Templates
   ├─ <edition-type1>  <== For edition type 1
   │  ├─ Contents
   │  ├─ Resources
   │  │  ├─ images
   │  │  └─ Translations
   │  └─ Templates
   │  ...
   └─ <edition-typeN>  <== For edition type N
      ├─ Contents
      ├─ Resources
      │  ├─ images
      │  └─ Translations
      └─ Templates
~~~
 
N> ##### Note
N> At the moment, standard **trefoil** themes only support edition types 
N> `epub` and `mobi`. But you are free to implement support for other edition
N> types in your custom themes.

## Components of a theme

### Contents

- `Common\Contents` directory contains default content for book items that 
  are common to all editions.

- `<edition-type>\Contents` directory contain content that is specific to 
  that edition.

If a content file is not found into one of these directories it will be 
looked up into the standard **easybook** content directories (most likely, 
the `Base` theme).

### Fonts

- `Common\Resources\Fonts` directory contains additional font files for the book.

There is no per-edition font directory, but you can select which fonts are
packed into the final book for a given edition with the following configuration:
 
~~~.yaml
book:
    editions:
        <edition-name>:
            include_fonts:  true
            fonts: 
                - Inconsolata-Regular
                - Inconsolata-Bold
~~~

Only the listed font files will be included into the final ebook (i.e. `book.epub`). 
This is a useful technique for limiting the final size of an edition output by only 
including the needed fonts. 

### Templates 

- `Common\Templates` directory contains templates that are common to all editions. 
- `<edition-type>\Templates` directory contain templates specific to that edition.

### Images

- `Common\Resources\images` directory contains images that are common to all editions. 
- `<edition-type>\Resources\images` directory contain images specific to that edition.

You can organize the image files into whatever subdirectories structure inside the 
`images` directories. Upon book publishing, all of them will be copied to a flat 
`images` files into the book contents (so beware of duplicated names). Images 
per-edition type overwrite common images of the same name.

### Translations

- `Common\Resources\Translations` directory contains label files that are common 
  to all editions.
 
- `<edition-type>\Resources\Translations` directory contain label files specific to 
  that edition.

As usual, per-edition translations overwrite common translations.

