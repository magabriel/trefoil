# TabularLists test simple table

## Source list

**Deep=2; 1 real category, 3 attributes.**

<div class="tabularlist tabularlist-list" markdown="1">

- **Category heading 1**: Category A. Lorem ipsum dolor sit amet, consectetuer adipiscing elit. 
    
    - **Attribute heading 1**:  Attribute A.1. Quisque volutpat mattis eros.
    - **Attribute heading 2**:  Attribute A.2. Donec odio. 
    - **Attribute heading 3**:  Attribute A.3. Lorem ipsum dolor sit amet, consectetuer adipiscing elit.
    
- **Category heading 1**: Category B. Donec odio. 
    
    - **Attribute heading 1**:  Attribute B.1. Quisque volutpat mattis eros.
    - **Attribute heading 2**:  Attribute B.2. Lorem ipsum dolor sit amet, consectetuer adipiscing elit.
    - **Attribute heading 3**:  Attribute B.3. Donec odio.

- **Category heading 1**: Category C. Quisque volutpat mattis eros. 
    
    - **Attribute heading 1**:  Attribute C.1. Donec odio.
    - **Attribute heading 2**:  Attribute C.2. Lorem ipsum dolor sit amet, consectetuer adipiscing elit.
    - **Attribute heading 3**:  Attribute C.3. Quisque volutpat mattis eros.

</div>

## As table (default number of columns)

Expected: 3 rows of 4 columns (the category and the 3 attributes).

{@ tabularlist_begin() @}

- **Category heading 1**: Category A. Lorem ipsum dolor sit amet, consectetuer adipiscing elit. 
    
    - **Attribute heading 1**:  Attribute A.1. Quisque volutpat mattis eros.
    - **Attribute heading 2**:  Attribute A.2. Donec odio. 
    - **Attribute heading 3**:  Attribute A.3. Lorem ipsum dolor sit amet, consectetuer adipiscing elit.
    
- **Category heading 1**: Category B. Donec odio. 
    
    - **Attribute heading 1**:  Attribute B.1. Quisque volutpat mattis eros.
    - **Attribute heading 2**:  Attribute B.2. Lorem ipsum dolor sit amet, consectetuer adipiscing elit.
    - **Attribute heading 3**:  Attribute B.3. Donec odio.

- **Category heading 1**: Category C. Quisque volutpat mattis eros. 
    
    - **Attribute heading 1**:  Attribute C.1. Donec odio.
    - **Attribute heading 2**:  Attribute C.2. Lorem ipsum dolor sit amet, consectetuer adipiscing elit.
    - **Attribute heading 3**:  Attribute C.3. Quisque volutpat mattis eros.

{@ tabularlist_end() @}

## As list (0 columns)

Expected: the list as is.

{@ tabularlist_begin({all:0}) @}

- **Category heading 1**: Category A. Lorem ipsum dolor sit amet, consectetuer adipiscing elit. 
    
    - **Attribute heading 1**:  Attribute A.1. Quisque volutpat mattis eros.
    - **Attribute heading 2**:  Attribute A.2. Donec odio. 
    - **Attribute heading 3**:  Attribute A.3. Lorem ipsum dolor sit amet, consectetuer adipiscing elit.
    
- **Category heading 1**: Category B. Donec odio. 
    
    - **Attribute heading 1**:  Attribute B.1. Quisque volutpat mattis eros.
    - **Attribute heading 2**:  Attribute B.2. Lorem ipsum dolor sit amet, consectetuer adipiscing elit.
    - **Attribute heading 3**:  Attribute B.3. Donec odio.

- **Category heading 1**: Category C. Quisque volutpat mattis eros. 
    
    - **Attribute heading 1**:  Attribute C.1. Donec odio.
    - **Attribute heading 2**:  Attribute C.2. Lorem ipsum dolor sit amet, consectetuer adipiscing elit.
    - **Attribute heading 3**:  Attribute C.3. Quisque volutpat mattis eros.

{@ tabularlist_end() @}

## As table (1 column)

Expected: a table with 1 column (the category) and only 1 cell per row

{@ tabularlist_begin({all:1}) @}

- **Category heading 1**: Category A. Lorem ipsum dolor sit amet, consectetuer adipiscing elit. 
    
    - **Attribute heading 1**:  Attribute A.1. Quisque volutpat mattis eros.
    - **Attribute heading 2**:  Attribute A.2. Donec odio. 
    - **Attribute heading 3**:  Attribute A.3. Lorem ipsum dolor sit amet, consectetuer adipiscing elit.
    
- **Category heading 1**: Category B. Donec odio. 
    
    - **Attribute heading 1**:  Attribute B.1. Quisque volutpat mattis eros.
    - **Attribute heading 2**:  Attribute B.2. Lorem ipsum dolor sit amet, consectetuer adipiscing elit.
    - **Attribute heading 3**:  Attribute B.3. Donec odio.

- **Category heading 1**: Category C. Quisque volutpat mattis eros. 
    
    - **Attribute heading 1**:  Attribute C.1. Donec odio.
    - **Attribute heading 2**:  Attribute C.2. Lorem ipsum dolor sit amet, consectetuer adipiscing elit.
    - **Attribute heading 3**:  Attribute C.3. Quisque volutpat mattis eros.
    
{@ tabularlist_end() @}

## As table (2 columns)

Expected: 2 columns, but the second one is the 1st attribute. Heading is **Attribute heading 1**.

{@ tabularlist_begin({all:2}) @}

- **Category heading 1**: Category A. Lorem ipsum dolor sit amet, consectetuer adipiscing elit. 
    
    - **Attribute heading 1**:  Attribute A.1. Quisque volutpat mattis eros.
    - **Attribute heading 2**:  Attribute A.2. Donec odio. 
    - **Attribute heading 3**:  Attribute A.3. Lorem ipsum dolor sit amet, consectetuer adipiscing elit.
    
- **Category heading 1**: Category B. Donec odio. 
    
    - **Attribute heading 1**:  Attribute B.1. Quisque volutpat mattis eros.
    - **Attribute heading 2**:  Attribute B.2. Lorem ipsum dolor sit amet, consectetuer adipiscing elit.
    - **Attribute heading 3**:  Attribute B.3. Donec odio.

- **Category heading 1**: Category C. Quisque volutpat mattis eros. 
    
    - **Attribute heading 1**:  Attribute C.1. Donec odio.
    - **Attribute heading 2**:  Attribute C.2. Lorem ipsum dolor sit amet, consectetuer adipiscing elit.
    - **Attribute heading 3**:  Attribute C.3. Quisque volutpat mattis eros.

{@ tabularlist_end() @}

## As table (3 columns) - Same as default

Expected: a table with all categories and attributes as columns.

{@ tabularlist_begin({all:3}) @}

- **Category heading 1**: Category A. Lorem ipsum dolor sit amet, consectetuer adipiscing elit. 
    
    - **Attribute heading 1**:  Attribute A.1. Quisque volutpat mattis eros.
    - **Attribute heading 2**:  Attribute A.2. Donec odio. 
    - **Attribute heading 3**:  Attribute A.3. Lorem ipsum dolor sit amet, consectetuer adipiscing elit.
    
- **Category heading 1**: Category B. Donec odio. 
    
    - **Attribute heading 1**:  Attribute B.1. Quisque volutpat mattis eros.
    - **Attribute heading 2**:  Attribute B.2. Lorem ipsum dolor sit amet, consectetuer adipiscing elit.
    - **Attribute heading 3**:  Attribute B.3. Donec odio.

- **Category heading 1**: Category C. Quisque volutpat mattis eros. 
    
    - **Attribute heading 1**:  Attribute C.1. Donec odio.
    - **Attribute heading 2**:  Attribute C.2. Lorem ipsum dolor sit amet, consectetuer adipiscing elit.
    - **Attribute heading 3**:  Attribute C.3. Quisque volutpat mattis eros.

{@ tabularlist_end() @}
