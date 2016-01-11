# HtmlTweaks test: tags

## onPreParse changes

### INSERT: surround contents of div having class with box

<div class="one" markdown="1">

Lorem **ipsum** dolor sit amen.

One Two Three. 

</div>

### SURROUND: surround div having class with box

<div class="two" markdown="1">

Lorem **ipsum** dolor sit amen.

One Two Three. 

</div>

### REPLACE: replace span having class with div

<span class="box1" style="background:#cde" markdown="1">

Lorem **ipsum** dolor sit amen.

One Two Three. 

</span>

## onPostParse changes

### INSERT: "======" and "------' surrounding contents of pre tag  

    Lorem ipsum dolor sit amen.
    One Two Three. 

### SURROUND: Table surrounded by a box

| One     | Two      | Three      | Four
|--------:|:---------|:----------:|---------
| a1      | a2       | a3         | a4
| b1      | b2       | b3         | b4      

### REPLACE: Unordered list replaced by ordered list

- One.
- Two.
- Tree.

