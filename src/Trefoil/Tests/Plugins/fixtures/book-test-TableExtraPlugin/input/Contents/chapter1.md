# TableExtra alignment and spans

## 1. Normal table

Column 1  | Column 2  | Column 3
----------|-----------|------------
One       | Two       | Three
One       | Two       | Three

## 2. With aligment

Column 1  | Column 2  | Column 3
---------:|:----------|:----------:
One       | Two       | Three
One       | Two       | Three

## 3. Rowspan

Column 1  | Column 2  | Column 3
----------|-----------|------------
One       | Two       | Three
"         | Two       | Three
One       | Two       | Three
"         | Two       | Three

## 3.1 Rowspan x3

Column 1  | Column 2  | Column 3
----------|-----------|------------
One       | Two       | Three
"         | Two       | Three
"         | Two       | Three
One       | Two       | Three
"         | Two       | Three

## 4. Rowspan in the middle

Column 1  | Column 2  | Column 3
---------:|:----------|:----------:
One       | Two       | Three
One       | "         | Three
One       | Two       | Three
One       | Two       | "

## 5. Colspan

Column 1  | Column 2  | Column 3
---------:|:----------|:----------:
One                  || Three
One       | Two       | Three
One                  || Three

## 5.1 Colspan x3

Column 1  | Column 2  | Column 3
:--------:|:----------|:----------:
One                             ||| 
One       | Two       | Three
One                  || Three

## 5.2 Colspan edge case: cell containing "0" must trigger colspan

Cell 2 of 1st row contains a "0". 

Column 1  | Column 2  | Column 3
---------:|:----------|:----------:
One       | 0         | Three
One       | Two       | Three
One                  || Three

## 6. Rowspan + colspan

Column 1  | Column 2  | Column 3
---------:|:----------|:----------:
One                  || Three
"                    || Three
One       | Two       | Three
One       | Two       | Three

## 7. Rowspan + colspan with top alignment

Column 1  | Column 2  | Column 3
:---------|:----------|:----------:
One                  || Three 0
'                    || Three 1
'                    || Three 2
One       | Two       | Three 3
One       | Two       | Three 4

