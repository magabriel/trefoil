# TableExtra test

## 1. Normal table

Columna 1 | Columna 2 | Columna 3
----------|-----------|------------
Uno       | Dos       | Tres
Uno       | Dos       | Tres

## 2. With aligment

Columna 1 | Columna 2 | Columna 3
---------:|:----------|:----------:
Uno       | Dos       | Tres
Uno       | Dos       | Tres

## 3. Rowspan

Columna 1 | Columna 2 | Columna 3
----------|-----------|------------
Uno       | Dos       | Tres
"         | Dos       | Tres
Uno       | Dos       | Tres
"         | Dos       | Tres

## 3.1 Rowspan x3

Columna 1 | Columna 2 | Columna 3
----------|-----------|------------
Uno       | Dos       | Tres
"         | Dos       | Tres
"         | Dos       | Tres
Uno       | Dos       | Tres
"         | Dos       | Tres

## 4. Rowspan in the middle

Columna 1 | Columna 2 | Columna 3
---------:|:----------|:----------:
Uno       | Dos       | Tres
Uno       | "         | Tres
Uno       | Dos       | Tres
Uno       | Dos       | "

## 5. Colspan

Columna 1 | Columna 2 | Columna 3
---------:|:----------|:----------:
Uno                  || Tres
Uno       | Dos       | Tres
Uno                  || Tres

## 5.1 Colspan x3

Columna 1 | Columna 2 | Columna 3
:--------:|:----------|:----------:
Uno                             ||| 
Uno       | Dos       | Tres
Uno                  || Tres

## 6. Rowspan + colspan

Columna 1 | Columna 2 | Columna 3
---------:|:----------|:----------:
Uno                  || Tres
"                    || Tres
Uno       | Dos       | Tres
Uno       | Dos       | Tres

