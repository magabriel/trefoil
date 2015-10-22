# KindleTweaks test

## Paragraphs inside lists

### List without blank lines between elements
 
- Lorem ipsum dolor sit amet, consectetuer adipiscing elit. 
- Donec odio. 
- Quisque volutpat mattis eros. 

### List with blank lines between elements
  
- Lorem ipsum dolor sit amet, consectetuer adipiscing elit. 

- Donec odio. 

- Quisque volutpat mattis eros. 
    
  This is a second parapgrah inside a list element.
  
  This is a third paragraph inside a list element.

### Embedded lists
  
- A1 Lorem ipsum dolor sit amet, consectetuer adipiscing elit. 

- A2 Donec odio. 

- A3.1 Quisque volutpat mattis eros. 
  
  A3.2 second parapgrah.
  
  A3.3 third paragraph.
    
  - B1.1 First element in sublist.    
      
      B1.2 Second paragraph in first element in sublist.
    
      B1.3 Third paragraph in first element in sublist.

  - B2 Second element in sublist.
  
### Footnotes to create links

Lorem[^1] ipsum[^2] dolor sit amet, consectetuer adipiscing elit. Quisque volutpat mattis eros. 

[^1]: This footnote is rendered as a list element with an id attribute that should be preserved.

[^2]: Other footnote with an embedded list:
    
    - One.
    
    - Two.

## Table cell alignment

| One     | Two      | Three      | Four
|--------:|:---------|:----------:|---------
| a1      | a2       | a3         | a4
| b1      | b2       | b3         | b4      

