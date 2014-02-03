# trefoil #

**trefoil** extends **easybook** (<http://easybook-project.org>) to provide 
additional features for publication of *ebooks*, both fiction and non-fiction.

## Installation ##

1.- Clone the trefoil repository:

```
$ mkdir trefoil
$ git clone http://github.com/magabriel/trefoil trefoil/
```

2.- Download the vendors and dependencies:

```
$ cd trefoil/
$ php composer.phar install
```

## Documentation ##

### HTML version

Go to <http://magabriel.github.io/trefoil-doc>.

### Epub and Mobi versions

You can find the source files in the `doc/trefoil-doc` directory. 

To create the documentation, go to the directory where trefoil is installed and run 
one of the following commands:

**To get the *epub* version:**
 
```
$ cd trefoil
$ book publish trefoil-doc ebook 
```

**To get the *kindle* version (please ensure you have the `kindlegen` application 
installed):**
 
```
$ cd trefoil
$ book publish trefoil-doc kindle
```


### License

> ##### Application Code
> 
> Copyright (c) 2014 Miguel Angel Gabriel <magabriel@gmail.com>
> 
> - - -
> 
> Permission is hereby granted, free of charge, to any person obtaining a copy of
> this software and associated documentation files (the "Software"), to deal in
> the Software without restriction, including without limitation the rights to
> use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
> of the Software, and to permit persons to whom the Software is furnished to do
> so, subject to the following conditions:
> 
> The above copyright notice and this permission notice shall be included in all
> copies or substantial portions of the Software.
> 
> THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
> IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
> FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
> AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
> LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
> OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
> SOFTWARE.

> ##### Libraries and packages used ##
> 
> See their own license files in `vendor/` directory
>
> ##### Resources used ##
>
> See their own license files in `app/Resources/` directory
