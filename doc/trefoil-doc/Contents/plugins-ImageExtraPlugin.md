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

This plugin brings support to extended image syntax:

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
 

#### Extended styles

**trefoil** themes come with some predefined image classes:

- **narrower:** 1/4 of the page width (up to 4 images side by side).   
- **narrow:** 1/3 of the page width (up to 3 images side by side).
- **half:** 1/2 of the page width (up to 2 images side by side).
- **wide:** full page width.   

#### Examples

##### Narrower right

![Lorem ipsum ](php.jpg?class="narrower")

Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aliquam sit amet velit a libero congue molestie. Integer ipsum massa, posuere nec massa eu, dapibus volutpat justo. Nullam enim dolor, scelerisque non dui et, ornare ultricies enim. Mauris sed felis sem. Praesent aliquam quam nec diam mollis ultrices. Nunc mattis pretium tellus, et luctus augue commodo sed. Interdum et malesuada fames ac ante ipsum primis in faucibus. Ut orci tortor, malesuada ac mauris sit amet, ornare sollicitudin massa.

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

##### Class and style

![Lorem ipsum](php.jpg?class="half my-class"&style="background: #cde; padding-right: 2em;")

<div class="clearfix"></div> 
