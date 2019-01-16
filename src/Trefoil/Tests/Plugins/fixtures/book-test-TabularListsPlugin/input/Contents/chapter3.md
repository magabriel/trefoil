# TabularLists test complex table

## Source list

**Deep=5; 4 real categories, 3 attributes.**

<div class="tabularlist tabularlist-list" markdown="1">

- **CATH 1**: catv 1 
        
    - **CATH 2**: catv 2.1
        
        - **CATH 3**: catv 3.1
            
            - **CATH 4**:  catv 4.1
                
                - **ATTRH 2**:  catv 1.2-1.3-1.4-1.1 
                - **ATTRH 2**:  catv 1.2-1.3-1.4-1.2
                - **ATTRH 2**:  catv 1.2-1.3-1.4-1.3
            
            - **CATH 4**:  catv 4.2
                
                - **ATTRH 2**:  attrv 1.2-1.3-1.4-2.1 
                - **ATTRH 2**:  attrv 1.2-1.3-1.4-2.2
                - **ATTRH 2**:  attrv 1.2-1.3-1.4-2.3
            
        - **CATH 3**: catv 3.2
            
            - **CATH 4**:  catv 4.3
                
                - **ATTRH 2**:  attrv 1.2-1.3-2.4-3.1 
                - **ATTRH 2**:  attrv 1.2-1.3-2.4-3.2
                - **ATTRH 2**:  attrv 1.2-1.3-2.4-3.3
            
    - **CATH 2**: catv 2.2
        
        - **CATH 3**: catv 3.3
                
            - **CATH 4**:  catv 4.4
                
                - **ATTRH 2**:  attrv 1.2-2.3-3.4-4.1 
                - **ATTRH 2**:  attrv 1.2-2.3-3.4-4.2
                - **ATTRH 2**:  attrv 1.2-2.3-3.4-4.3
    
</div>

## As table (default)

Expected: a table with all categories and attributes as columns.

{@ tabularlist_begin() @}

- **CATH 1**: catv 1 
        
    - **CATH 2**: catv 2.1
        
        - **CATH 3**: catv 3.1
            
            - **CATH 4**:  catv 4.1
                
                - **ATTRH 2**:  catv 1.2-1.3-1.4-1.1 
                - **ATTRH 2**:  catv 1.2-1.3-1.4-1.2
                - **ATTRH 2**:  catv 1.2-1.3-1.4-1.3
            
            - **CATH 4**:  catv 4.2
                
                - **ATTRH 2**:  attrv 1.2-1.3-1.4-2.1 
                - **ATTRH 2**:  attrv 1.2-1.3-1.4-2.2
                - **ATTRH 2**:  attrv 1.2-1.3-1.4-2.3
            
        - **CATH 3**: catv 3.2
            
            - **CATH 4**:  catv 4.3
                
                - **ATTRH 2**:  attrv 1.2-1.3-2.4-3.1 
                - **ATTRH 2**:  attrv 1.2-1.3-2.4-3.2
                - **ATTRH 2**:  attrv 1.2-1.3-2.4-3.3
            
    - **CATH 2**: catv 2.2
        
        - **CATH 3**: catv 3.3
                
            - **CATH 4**:  catv 4.4
                
                - **ATTRH 2**:  attrv 1.2-2.3-3.4-4.1 
                - **ATTRH 2**:  attrv 1.2-2.3-3.4-4.2
                - **ATTRH 2**:  attrv 1.2-2.3-3.4-4.3
    
{@ tabularlist_end() @}

## As list (0 columns)

Expected: Expected: the list as is.

{@ tabularlist_begin({all:0}) @}

- **CATH 1**: catv 1 
        
    - **CATH 2**: catv 2.1
        
        - **CATH 3**: catv 3.1
            
            - **CATH 4**:  catv 4.1
                
                - **ATTRH 2**:  catv 1.2-1.3-1.4-1.1 
                - **ATTRH 2**:  catv 1.2-1.3-1.4-1.2
                - **ATTRH 2**:  catv 1.2-1.3-1.4-1.3
            
            - **CATH 4**:  catv 4.2
                
                - **ATTRH 2**:  attrv 1.2-1.3-1.4-2.1 
                - **ATTRH 2**:  attrv 1.2-1.3-1.4-2.2
                - **ATTRH 2**:  attrv 1.2-1.3-1.4-2.3
            
        - **CATH 3**: catv 3.2
            
            - **CATH 4**:  catv 4.3
                
                - **ATTRH 2**:  attrv 1.2-1.3-2.4-3.1 
                - **ATTRH 2**:  attrv 1.2-1.3-2.4-3.2
                - **ATTRH 2**:  attrv 1.2-1.3-2.4-3.3
            
    - **CATH 2**: catv 2.2
        
        - **CATH 3**: catv 3.3
                
            - **CATH 4**:  catv 4.4
                </div>
                - **ATTRH 2**:  attrv 1.2-2.3-3.4-4.1 
                - **ATTRH 2**:  attrv 1.2-2.3-3.4-4.2
                - **ATTRH 2**:  attrv 1.2-2.3-3.4-4.3
    

{@ tabularlist_end() @}

## As table (1 column)

Expected: a table with 1 column (the 1st category) and only 1 cell per row.

{@ tabularlist_begin({all:1}) @}

- **CATH 1**: catv 1 
        
    - **CATH 2**: catv 2.1
        
        - **CATH 3**: catv 3.1
            
            - **CATH 4**:  catv 4.1
                
                - **ATTRH 2**:  catv 1.2-1.3-1.4-1.1 
                - **ATTRH 2**:  catv 1.2-1.3-1.4-1.2
                - **ATTRH 2**:  catv 1.2-1.3-1.4-1.3
            
            - **CATH 4**:  catv 4.2
                
                - **ATTRH 2**:  attrv 1.2-1.3-1.4-2.1 
                - **ATTRH 2**:  attrv 1.2-1.3-1.4-2.2
                - **ATTRH 2**:  attrv 1.2-1.3-1.4-2.3
            
        - **CATH 3**: catv 3.2
            
            - **CATH 4**:  catv 4.3
                
                - **ATTRH 2**:  attrv 1.2-1.3-2.4-3.1 
                - **ATTRH 2**:  attrv 1.2-1.3-2.4-3.2
                - **ATTRH 2**:  attrv 1.2-1.3-2.4-3.3
            
    - **CATH 2**: catv 2.2
        
        - **CATH 3**: catv 3.3
                
            - **CATH 4**:  catv 4.4
                
                - **ATTRH 2**:  attrv 1.2-2.3-3.4-4.1 
                - **ATTRH 2**:  attrv 1.2-2.3-3.4-4.2
                - **ATTRH 2**:  attrv 1.2-2.3-3.4-4.3
    
{@ tabularlist_end() @}

## As table (2 columns)

Expected: a table with 2 columns (the 1st and 2nd categories).

{@ tabularlist_begin({all:2}) @}

- **CATH 1**: catv 1 
        
    - **CATH 2**: catv 2.1
        
        - **CATH 3**: catv 3.1
            
            - **CATH 4**:  catv 4.1
                
                - **ATTRH 2**:  catv 1.2-1.3-1.4-1.1 
                - **ATTRH 2**:  catv 1.2-1.3-1.4-1.2
                - **ATTRH 2**:  catv 1.2-1.3-1.4-1.3
            
            - **CATH 4**:  catv 4.2
                
                - **ATTRH 2**:  attrv 1.2-1.3-1.4-2.1 
                - **ATTRH 2**:  attrv 1.2-1.3-1.4-2.2
                - **ATTRH 2**:  attrv 1.2-1.3-1.4-2.3
            
        - **CATH 3**: catv 3.2
            
            - **CATH 4**:  catv 4.3
                
                - **ATTRH 2**:  attrv 1.2-1.3-2.4-3.1 
                - **ATTRH 2**:  attrv 1.2-1.3-2.4-3.2
                - **ATTRH 2**:  attrv 1.2-1.3-2.4-3.3
            
    - **CATH 2**: catv 2.2
        
        - **CATH 3**: catv 3.3
                
            - **CATH 4**:  catv 4.4
                
                - **ATTRH 2**:  attrv 1.2-2.3-3.4-4.1 
                - **ATTRH 2**:  attrv 1.2-2.3-3.4-4.2
                - **ATTRH 2**:  attrv 1.2-2.3-3.4-4.3

{@ tabularlist_end() @}

## As table (3 columns)

Expected: a table with 3 columns (the 1st, 2nd and 3rd categories).

{@ tabularlist_begin({all:3}) @}

- **CATH 1**: catv 1 
        
    - **CATH 2**: catv 2.1
        
        - **CATH 3**: catv 3.1
            
            - **CATH 4**:  catv 4.1
                
                - **ATTRH 2**:  catv 1.2-1.3-1.4-1.1 
                - **ATTRH 2**:  catv 1.2-1.3-1.4-1.2
                - **ATTRH 2**:  catv 1.2-1.3-1.4-1.3
            
            - **CATH 4**:  catv 4.2
                
                - **ATTRH 2**:  attrv 1.2-1.3-1.4-2.1 
                - **ATTRH 2**:  attrv 1.2-1.3-1.4-2.2
                - **ATTRH 2**:  attrv 1.2-1.3-1.4-2.3
            
        - **CATH 3**: catv 3.2
            
            - **CATH 4**:  catv 4.3
                
                - **ATTRH 2**:  attrv 1.2-1.3-2.4-3.1 
                - **ATTRH 2**:  attrv 1.2-1.3-2.4-3.2
                - **ATTRH 2**:  attrv 1.2-1.3-2.4-3.3
            
    - **CATH 2**: catv 2.2
        
        - **CATH 3**: catv 3.3
                
            - **CATH 4**:  catv 4.4
                
                - **ATTRH 2**:  attrv 1.2-2.3-3.4-4.1 
                - **ATTRH 2**:  attrv 1.2-2.3-3.4-4.2
                - **ATTRH 2**:  attrv 1.2-2.3-3.4-4.3

{@ tabularlist_end() @}

## As table (4 columns)

Expected: a table with 4 columns (the 4 categories).

{@ tabularlist_begin({all:4}) @}

- **CATH 1**: catv 1 
        
    - **CATH 2**: catv 2.1
        
        - **CATH 3**: catv 3.1
            
            - **CATH 4**:  catv 4.1
                
                - **ATTRH 2**:  catv 1.2-1.3-1.4-1.1 
                - **ATTRH 2**:  catv 1.2-1.3-1.4-1.2
                - **ATTRH 2**:  catv 1.2-1.3-1.4-1.3
            
            - **CATH 4**:  catv 4.2
                
                - **ATTRH 2**:  attrv 1.2-1.3-1.4-2.1 
                - **ATTRH 2**:  attrv 1.2-1.3-1.4-2.2
                - **ATTRH 2**:  attrv 1.2-1.3-1.4-2.3
            
        - **CATH 3**: catv 3.2
            
            - **CATH 4**:  catv 4.3
                
                - **ATTRH 2**:  attrv 1.2-1.3-2.4-3.1 
                - **ATTRH 2**:  attrv 1.2-1.3-2.4-3.2
                - **ATTRH 2**:  attrv 1.2-1.3-2.4-3.3
            
    - **CATH 2**: catv 2.2
        
        - **CATH 3**: catv 3.3
                
            - **CATH 4**:  catv 4.4
                
                - **ATTRH 2**:  attrv 1.2-2.3-3.4-4.1 
                - **ATTRH 2**:  attrv 1.2-2.3-3.4-4.2
                - **ATTRH 2**:  attrv 1.2-2.3-3.4-4.3

{@ tabularlist_end() @}

## As table (5 columns) - Same as default

Expected: a table with all categories and attributes as columns.

{@ tabularlist_begin({all:5}) @}

- **CATH 1**: catv 1 
        
    - **CATH 2**: catv 2.1
        
        - **CATH 3**: catv 3.1
            
            - **CATH 4**:  catv 4.1
                
                - **ATTRH 2**:  catv 1.2-1.3-1.4-1.1 
                - **ATTRH 2**:  catv 1.2-1.3-1.4-1.2
                - **ATTRH 2**:  catv 1.2-1.3-1.4-1.3
            
            - **CATH 4**:  catv 4.2
                
                - **ATTRH 2**:  attrv 1.2-1.3-1.4-2.1 
                - **ATTRH 2**:  attrv 1.2-1.3-1.4-2.2
                - **ATTRH 2**:  attrv 1.2-1.3-1.4-2.3
            
        - **CATH 3**: catv 3.2
            
            - **CATH 4**:  catv 4.3
                
                - **ATTRH 2**:  attrv 1.2-1.3-2.4-3.1 
                - **ATTRH 2**:  attrv 1.2-1.3-2.4-3.2
                - **ATTRH 2**:  attrv 1.2-1.3-2.4-3.3
            
    - **CATH 2**: catv 2.2
        
        - **CATH 3**: catv 3.3
                
            - **CATH 4**:  catv 4.4
                
                - **ATTRH 2**:  attrv 1.2-2.3-3.4-4.1 
                - **ATTRH 2**:  attrv 1.2-2.3-3.4-4.2
                - **ATTRH 2**:  attrv 1.2-2.3-3.4-4.3

{@ tabularlist_end() @}
