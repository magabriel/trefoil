## ImageExtraPlugin

This plugin provides an extended image syntax to allow more precise styling of images.

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
                enabled: [ ImageExtra ]
~~~ 

### Description

Features:

- Explicit image path.
- Extended image syntax.
- Extended styles in themes.

#### Explicit image path

Some Markdown editors provide a live preview of the rendered HTML result. Some of them even
provide live preview for images (like the fabulous [MdCharm](http://www.mdcharm.com/)), but 
for this to work the image path must point to the relative location of the image:

~~~.markdown
# This is the easybook way, 
# but the preview won't work:
![This is image 1](image1.jpeg)

# You can write it this way,
# an the preview will work:
![This is image 1](images/image1.jpeg)
~~~

Both ways will render exactly the same, but only the second one will produce a preview in
editors like *MdCharm*.  


#### Extended image syntax

**trefoil** extends the Markdown syntax for images providing a `class` and `style` arguments
with similar syntax than the HTML counterparts. 

- **class:** You can provide one or several classes to be applied to the image markup.
- **style:** You can even apply any CSS style specification.

N> ##### Tip
N> The included **trefoil** templates are ready to make use the extended markup.
N> The standard **easybook** templates, on the other hand, are not.
 
~~~.markdown
![caption](image.name?class="myclass"&style="any_css_style_specification")
~~~

Example:

~~~.markdown
![Lorem ipsum](php.jpg?class="half my-class"&style="background: #cde; padding-right: 2em;")
~~~

N> ##### Note
N> Standard **easybook** image alignment syntax still works:
N> 
N> `![ Left aligned](php.jpg?class="narrower")`
N> 
 

#### Extended styles in themes

**trefoil** themes come with some predefined image classes:

- **narrower:** 1/4 of the page width (up to 4 images side by side).   
- **narrow:** 1/3 of the page width (up to 3 images side by side).
- **half:** 1/2 of the page width (up to 2 images side by side).
- **wide:** full page width.   

#### Examples

##### Narrower right

![Lorem ipsum ](php.jpg?class="narrower")

Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aliquam sit amet velit a libero 
congue molestie. Integer ipsum massa, posuere nec massa eu, dapibus volutpat justo. 
Nullam enim dolor, scelerisque non dui et, ornare ultricies enim. Mauris sed felis sem. 
Praesent aliquam quam nec diam mollis ultrices. Nunc mattis pretium tellus, et luctus 
augue commodo sed. Interdum et malesuada fames ac ante ipsum primis in faucibus. 
Ut orci tortor, malesuada ac mauris sit amet, ornare sollicitudin massa.

<div class="clearfix"></div> 

##### Narrower 4 in a row

![Lorem ipsum](php.jpg?class="narrower")
![Lorem ipsum](php.jpg?class="narrower")
![Lorem ipsum](php.jpg?class="narrower")
![Lorem ipsum](php.jpg?class="narrower")

<div class="clearfix"></div> 

##### Narrower centered

![ Lorem ipsum ](php.jpg?class="narrower")

Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aliquam sit amet velit a libero congue molestie. Integer ipsum massa, posuere nec massa eu, dapibus volutpat justo. Nullam enim dolor, scelerisque non dui et, ornare ultricies enim. Mauris sed felis sem. Praesent aliquam quam nec diam mollis ultrices. Nunc mattis pretium tellus, et luctus augue commodo sed. Interdum et malesuada fames ac ante ipsum primis in faucibus. Ut orci tortor, malesuada ac mauris sit amet, ornare sollicitudin massa.

<div class="clearfix"></div> 

##### Narrow right

![Lorem ipsum ](php.jpg?class="narrow")

Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aliquam sit amet velit a libero congue molestie. Integer ipsum massa, posuere nec massa eu, dapibus volutpat justo. Nullam enim dolor, scelerisque non dui et, ornare ultricies enim. Mauris sed felis sem. Praesent aliquam quam nec diam mollis ultrices. Nunc mattis pretium tellus, et luctus augue commodo sed. Interdum et malesuada fames ac ante ipsum primis in faucibus. Ut orci tortor, malesuada ac mauris sit amet, ornare sollicitudin massa.

<div class="clearfix"></div> 

##### Narrow 3 in a row

![Lorem ipsum](php.jpg?class="narrow") 
![Lorem ipsum](php.jpg?class="narrow")
![Lorem ipsum](php.jpg?class="narrow")

<div class="clearfix"></div> 

##### Narrow centered

![ Lorem ipsum ](php.jpg?class="narrow") 

Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aliquam sit amet velit a libero congue molestie. Integer ipsum massa, posuere nec massa eu, dapibus volutpat justo. Nullam enim dolor, scelerisque non dui et, ornare ultricies enim. Mauris sed felis sem. Praesent aliquam quam nec diam mollis ultrices. Nunc mattis pretium tellus, et luctus augue commodo sed. Interdum et malesuada fames ac ante ipsum primis in faucibus. Ut orci tortor, malesuada ac mauris sit amet, ornare sollicitudin massa.

<div class="clearfix"></div> 

##### Half left

![ Lorem ipsum](php.jpg?class="half")

Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aliquam sit amet velit a libero congue molestie. Integer ipsum massa, posuere nec massa eu, dapibus volutpat justo. Nullam enim dolor, scelerisque non dui et, ornare ultricies enim. Mauris sed felis sem. Praesent aliquam quam nec diam mollis ultrices. Nunc mattis pretium tellus, et luctus augue commodo sed. Interdum et malesuada fames ac ante ipsum primis in faucibus. Ut orci tortor, malesuada ac mauris sit amet, ornare sollicitudin massa.

<div class="clearfix"></div> 

##### Half 2 in a row

![Lorem ipsum](php.jpg?class="half")
![Lorem ipsum](php.jpg?class="half")

<div class="clearfix"></div> 

##### Half centered

![ Lorem ipsum ](php.jpg?class="half")

Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aliquam sit amet velit a libero congue molestie. Integer ipsum massa, posuere nec massa eu, dapibus volutpat justo. Nullam enim dolor, scelerisque non dui et, ornare ultricies enim. Mauris sed felis sem. Praesent aliquam quam nec diam mollis ultrices. Nunc mattis pretium tellus, et luctus augue commodo sed. Interdum et malesuada fames ac ante ipsum primis in faucibus. Ut orci tortor, malesuada ac mauris sit amet, ornare sollicitudin massa.

<div class="clearfix"></div> 

##### Wide

![Lorem ipsum](php.jpg?class="wide")

<div class="clearfix"></div> 

##### Class and style

![Lorem ipsum](php.jpg?class="half my-class"&style="background: #cde; padding-right: 2em;")

<div class="clearfix"></div> 
