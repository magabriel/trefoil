# TabularLists test wide table

## Source list

**Deep=3; 2 real categories, 3 attributes.**

<div class="tabularlist tabularlist-list" markdown="1">

- **Category heading 1**: Category 1=AAAA 
        
  - **Category heading 2**: Category 2=One
        
      - **Attribute heading 1**:  Attribute AAAA.1.1 
      - **Attribute heading 2**:  Attribute AAAA.1.2
      - **Attribute heading 3**:  Attribute AAAA.1.3
              
  - **Category heading 2**: Category 2=Two
        
      - **Attribute heading 1**:  Attribute AAAA.2.1 
      - **Attribute heading 2**:  Attribute AAAA.2.2
      - **Attribute heading 3**:  Attribute AAAA.2.3
    
  - **Category heading 2**: Category 2=Three
        
      - **Attribute heading 1**:  Attribute AAAA.3.1 
      - **Attribute heading 2**:  Attribute AAAA.3.2
      - **Attribute heading 3**:  Attribute AAAA.3.3
        
- **Category heading 1**: Category 1=BBBB 
    
  - **Category heading 2**: Category 2=Four
        
      - **Attribute heading 1**:  Attribute BBBB.1.1 
      - **Attribute heading 2**:  Attribute BBBB.1.2
      - **Attribute heading 3**:  Attribute BBBB.1.3

</div>

## As table (default columns)

Expected: A table with 5 columns (the 2 categories and the 3 attributes)

{@ tabularlist_begin() @}

- **Category heading 1**: Category 1=AAAA 
        
  - **Category heading 2**: Category 2=One
        
      - **Attribute heading 1**:  Attribute AAAA.1.1 
      - **Attribute heading 2**:  Attribute AAAA.1.2
      - **Attribute heading 3**:  Attribute AAAA.1.3
              
  - **Category heading 2**: Category 2=Two
        
      - **Attribute heading 1**:  Attribute AAAA.2.1 
      - **Attribute heading 2**:  Attribute AAAA.2.2
      - **Attribute heading 3**:  Attribute AAAA.2.3
    
  - **Category heading 2**: Category 2=Three
        
      - **Attribute heading 1**:  Attribute AAAA.3.1 
      - **Attribute heading 2**:  Attribute AAAA.3.2
      - **Attribute heading 3**:  Attribute AAAA.3.3
        
- **Category heading 1**: Category 1=BBBB 
    
  - **Category heading 2**: Category 2=Four
        
      - **Attribute heading 1**:  Attribute BBBB.1.1 
      - **Attribute heading 2**:  Attribute BBBB.1.2
      - **Attribute heading 3**:  Attribute BBBB.1.3

{@ tabularlist_end() @}

## As list (0 columns)

Expected: the list as is.

{@ tabularlist_begin({all:0}) @}

- **Category heading 1**: Category 1=AAAA 
        
  - **Category heading 2**: Category 2=One
        
      - **Attribute heading 1**:  Attribute AAAA.1.1 
      - **Attribute heading 2**:  Attribute AAAA.1.2
      - **Attribute heading 3**:  Attribute AAAA.1.3
              
  - **Category heading 2**: Category 2=Two
        
      - **Attribute heading 1**:  Attribute AAAA.2.1 
      - **Attribute heading 2**:  Attribute AAAA.2.2
      - **Attribute heading 3**:  Attribute AAAA.2.3
    
  - **Category heading 2**: Category 2=Three
        
      - **Attribute heading 1**:  Attribute AAAA.3.1 
      - **Attribute heading 2**:  Attribute AAAA.3.2
      - **Attribute heading 3**:  Attribute AAAA.3.3
        
- **Category heading 1**: Category 1=BBBB 
    
  - **Category heading 2**: Category 2=Four
        
      - **Attribute heading 1**:  Attribute BBBB.1.1 
      - **Attribute heading 2**:  Attribute BBBB.1.2
      - **Attribute heading 3**:  Attribute BBBB.1.3

{@ tabularlist_end() @}

## As table (1 column)

Expected: a table with 1 column (the category) and only 1 cell per row.

{@ tabularlist_begin({all:1}) @}

- **Category heading 1**: Category 1=AAAA 
        
  - **Category heading 2**: Category 2=One
        
      - **Attribute heading 1**:  Attribute AAAA.1.1 
      - **Attribute heading 2**:  Attribute AAAA.1.2
      - **Attribute heading 3**:  Attribute AAAA.1.3
              
  - **Category heading 2**: Category 2=Two
        
      - **Attribute heading 1**:  Attribute AAAA.2.1 
      - **Attribute heading 2**:  Attribute AAAA.2.2
      - **Attribute heading 3**:  Attribute AAAA.2.3
    
  - **Category heading 2**: Category 2=Three
        
      - **Attribute heading 1**:  Attribute AAAA.3.1 
      - **Attribute heading 2**:  Attribute AAAA.3.2
      - **Attribute heading 3**:  Attribute AAAA.3.3
        
- **Category heading 1**: Category 1=BBBB 
    
  - **Category heading 2**: Category 2=Four
        
      - **Attribute heading 1**:  Attribute BBBB.1.1 
      - **Attribute heading 2**:  Attribute BBBB.1.2
      - **Attribute heading 3**:  Attribute BBBB.1.3

{@ tabularlist_end() @}

## As table (2 columns)

Expected: 2 columns, but the second one contins the second category and the attributes as list.

{@ tabularlist_begin({all:2}) @}

- **Category heading 1**: Category 1=AAAA 
        
  - **Category heading 2**: Category 2=One
        
      - **Attribute heading 1**:  Attribute AAAA.1.1 
      - **Attribute heading 2**:  Attribute AAAA.1.2
      - **Attribute heading 3**:  Attribute AAAA.1.3
              
  - **Category heading 2**: Category 2=Two
        
      - **Attribute heading 1**:  Attribute AAAA.2.1 
      - **Attribute heading 2**:  Attribute AAAA.2.2
      - **Attribute heading 3**:  Attribute AAAA.2.3
    
  - **Category heading 2**: Category 2=Three
        
      - **Attribute heading 1**:  Attribute AAAA.3.1 
      - **Attribute heading 2**:  Attribute AAAA.3.2
      - **Attribute heading 3**:  Attribute AAAA.3.3
        
- **Category heading 1**: Category 1=BBBB 
    
  - **Category heading 2**: Category 2=Four
        
      - **Attribute heading 1**:  Attribute BBBB.1.1 
      - **Attribute heading 2**:  Attribute BBBB.1.2
      - **Attribute heading 3**:  Attribute BBBB.1.3

{@ tabularlist_end() @}

## As table (3 columns) - Same as default

Expected: a table with all categories and attributes as columns.

{@ tabularlist_begin({all:3}) @}

- **Category heading 1**: Category 1=AAAA 
        
  - **Category heading 2**: Category 2=One
        
      - **Attribute heading 1**:  Attribute AAAA.1.1 
      - **Attribute heading 2**:  Attribute AAAA.1.2
      - **Attribute heading 3**:  Attribute AAAA.1.3
              
  - **Category heading 2**: Category 2=Two
        
      - **Attribute heading 1**:  Attribute AAAA.2.1 
      - **Attribute heading 2**:  Attribute AAAA.2.2
      - **Attribute heading 3**:  Attribute AAAA.2.3
    
  - **Category heading 2**: Category 2=Three
        
      - **Attribute heading 1**:  Attribute AAAA.3.1 
      - **Attribute heading 2**:  Attribute AAAA.3.2
      - **Attribute heading 3**:  Attribute AAAA.3.3
        
- **Category heading 1**: Category 1=BBBB 
    
  - **Category heading 2**: Category 2=Four
        
      - **Attribute heading 1**:  Attribute BBBB.1.1 
      - **Attribute heading 2**:  Attribute BBBB.1.2
      - **Attribute heading 3**:  Attribute BBBB.1.3

{@ tabularlist_end() @}
