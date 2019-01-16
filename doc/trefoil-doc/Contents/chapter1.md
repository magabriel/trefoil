# About this project

This project aims to provide a great tool for creating ebooks.
It does that by extending the already superb **easybook**
(<https://easycorp.io/EasyBook>) to provide additional features
specifically targeted towards generation of ebooks.

{{ itemtoc() }}

## Installing

1.- Clone the trefoil repository:

~~~.bash
mkdir trefoil
git clone http://github.com/magabriel/trefoil trefoil/
~~~

2.- Download the vendors and dependencies:

~~~.bash
cd trefoil/
php composer.phar install
~~~

## Usage

N> ##### TIP
N> You should _really_ be familiar with the **easybook** documentation 
N> <https://easycorp.io/EasyBook> to understand what's going on here ;)

The basic usage is the same as **easybook**: 

~~~.bash
book publish my-book-slug my-edition
~~~

**trefoil** adds a new optional argument `--themes_dir` that allows using
custom themes stored in whatever location in the file system:

~~~.bash
book publish my-book-slug my-edition --themes_dir=../my/themes/directory
~~~

Example:

~~~.bash
book publish the-origin-of-species ebook --themes_dir=~/themes/trefoil
~~~

## Extending Easybook

If you are reading this, it is assumed that you are familiar with **easybook**
documentation. If you are not, please go to <http://easybook-project.org> and 
read it. You can even install a test version of **easybook** and play with it
a little to gain first-hand knowledge of its capabilities. When you are done
with it you should return to this document and continue reading.

N> ##### NOTICE
N> **trefoil** is not a fork of **easybook** but an extension. 

### Compatibility

**trefoil** has been developed as an extension of **easybook**, so it is fully 
backwards compatible with **easybook**. That means that any book prepared to be 
published by **easybook** should publish under **trefoil** without alterations.


### Motivation

**easybook** is a wonderful project that really eases auto-publishing work,
making possible to get professional results with limited knowledge or resources.
But it is primarily focused on the technical writer, lacking some features 
needed by non-technical works, both fiction and non-fiction.
While these features could of course be implemented into **easybook** itself, the
*extension* approach was chosen because of the progressive nature of the
development (the features were being developed as needed, during preparation
of a real book that was eventually published to *Amazon Kindle Store*). 
As such, it would have been highĺy impractical trying to influence **easybook** 
project to go the way we needed (and cope with the numerous mistakes that were 
made all over the road).

Looking at the *final* result, it becomes evident that some features *could*  (or 
even *may* or *should*) be integrated into **easybook**. As on any other open 
source project, only time will tell.
 
