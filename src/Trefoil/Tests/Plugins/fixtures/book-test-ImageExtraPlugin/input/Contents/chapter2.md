# ImageExtraPlugin tests

## Relative image path

**Image with standard syntax: `image.name`**
![ Lorem ipsum](php.jpg)

<div class="clearfix"></div> 

**Image with explicit path: `images/image.name`**
![ Lorem ipsum](images/php.jpg)


## Sizes and alignemnts

### Narrower left:

Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aliquam sit amet velit a libero congue molestie. Integer ipsum massa, posuere nec massa eu, dapibus volutpat justo. Nullam enim dolor, scelerisque non dui et, ornare ultricies enim. Mauris sed felis sem. Praesent aliquam quam nec diam mollis ultrices. Nunc mattis pretium tellus, et luctus augue commodo sed. Interdum et malesuada fames ac ante ipsum primis in faucibus. Ut orci tortor, malesuada ac mauris sit amet, ornare sollicitudin massa.

![ Lorem ipsum](php.jpg?class="narrower")

Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aliquam sit amet velit a libero congue molestie. Integer ipsum massa, posuere nec massa eu, dapibus volutpat justo. Nullam enim dolor, scelerisque non dui et, ornare ultricies enim. Mauris sed felis sem. Praesent aliquam quam nec diam mollis ultrices. Nunc mattis pretium tellus, et luctus augue commodo sed. Interdum et malesuada fames ac ante ipsum primis in faucibus. Ut orci tortor, malesuada ac mauris sit amet, ornare sollicitudin massa.

<div class="clearfix"></div> 

### Narrower 4 in a row:

![Lorem ipsum](php.jpg?class="narrower")
![Lorem ipsum](php.jpg?class="narrower")
![Lorem ipsum](php.jpg?class="narrower")
![Lorem ipsum](php.jpg?class="narrower")

<div class="clearfix"></div> 

### Narrower centered:

![ Lorem ipsum ](php.jpg?class="narrower")

<div class="clearfix"></div> 

### Narrow right:

Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aliquam sit amet velit a libero congue molestie. Integer ipsum massa, posuere nec massa eu, dapibus volutpat justo. Nullam enim dolor, scelerisque non dui et, ornare ultricies enim. Mauris sed felis sem. Praesent aliquam quam nec diam mollis ultrices. Nunc mattis pretium tellus, et luctus augue commodo sed. Interdum et malesuada fames ac ante ipsum primis in faucibus. Ut orci tortor, malesuada ac mauris sit amet, ornare sollicitudin massa.

![Lorem ipsum ](php.jpg?class="narrow")

Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aliquam sit amet velit a libero congue molestie. Integer ipsum massa, posuere nec massa eu, dapibus volutpat justo. Nullam enim dolor, scelerisque non dui et, ornare ultricies enim. Mauris sed felis sem. Praesent aliquam quam nec diam mollis ultrices. Nunc mattis pretium tellus, et luctus augue commodo sed. Interdum et malesuada fames ac ante ipsum primis in faucibus. Ut orci tortor, malesuada ac mauris sit amet, ornare sollicitudin massa.

<div class="clearfix"></div> 

### Narrow 3 in a row:

![Lorem ipsum](php.jpg?class="narrow") 
![Lorem ipsum](php.jpg?class="narrow")
![Lorem ipsum](php.jpg?class="narrow")

<div class="clearfix"></div> 

### Narrow centered:

![ Lorem ipsum ](php.jpg?class="narrow") 

<div class="clearfix"></div> 

### Half left:

Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aliquam sit amet velit a libero congue molestie. Integer ipsum massa, posuere nec massa eu, dapibus volutpat justo. Nullam enim dolor, scelerisque non dui et, ornare ultricies enim. Mauris sed felis sem. Praesent aliquam quam nec diam mollis ultrices. Nunc mattis pretium tellus, et luctus augue commodo sed. Interdum et malesuada fames ac ante ipsum primis in faucibus. Ut orci tortor, malesuada ac mauris sit amet, ornare sollicitudin massa.

![ Lorem ipsum](php.jpg?class="half")

Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aliquam sit amet velit a libero congue molestie. Integer ipsum massa, posuere nec massa eu, dapibus volutpat justo. Nullam enim dolor, scelerisque non dui et, ornare ultricies enim. Mauris sed felis sem. Praesent aliquam quam nec diam mollis ultrices. Nunc mattis pretium tellus, et luctus augue commodo sed. Interdum et malesuada fames ac ante ipsum primis in faucibus. Ut orci tortor, malesuada ac mauris sit amet, ornare sollicitudin massa.

<div class="clearfix"></div> 

### Half 2 in a row:

![Lorem ipsum](php.jpg?class="half")
![Lorem ipsum](php.jpg?class="half")

<div class="clearfix"></div> 

### Half centered

![ Lorem ipsum ](php.jpg?class="half")

<div class="clearfix"></div> 

### Wide:

Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aliquam sit amet velit a libero congue molestie. Integer ipsum massa, posuere nec massa eu, dapibus volutpat justo. Nullam enim dolor, scelerisque non dui et, ornare ultricies enim. Mauris sed felis sem. Praesent aliquam quam nec diam mollis ultrices. Nunc mattis pretium tellus, et luctus augue commodo sed. Interdum et malesuada fames ac ante ipsum primis in faucibus. Ut orci tortor, malesuada ac mauris sit amet, ornare sollicitudin massa.

![Lorem ipsum](php.jpg?class="wide")

Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aliquam sit amet velit a libero congue molestie. Integer ipsum massa, posuere nec massa eu, dapibus volutpat justo. Nullam enim dolor, scelerisque non dui et, ornare ultricies enim. Mauris sed felis sem. Praesent aliquam quam nec diam mollis ultrices. Nunc mattis pretium tellus, et luctus augue commodo sed. Interdum et malesuada fames ac ante ipsum primis in faucibus. Ut orci tortor, malesuada ac mauris sit amet, ornare sollicitudin massa.

### Class and style:

![Lorem ipsum](php.jpg?class="half my-class"&style="background: #cde; padding-right: 2em;")

<div class="clearfix"></div> 

Not processed within a code block:

    ![Lorem ipsum](php.jpg?class="half my-class"&style="background: #cde; padding-right: 2em;")

