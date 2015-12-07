# TableExtra multiline test

## 1. Normal multiline cell

Column 1  | Column 2  | Column 3
----------|-----------|------------
One       | Two       | Three and        +
          |           | Three continued
One       | Two       | Three

## 2. Multiline cell with list

Column 1  | Column 2  | Column 3
----------|-----------|------------
One       | Two       | - Three and        +
          |           | - Three continued  +
          |           | - Three continued 2
One       | Two       | Three

## 3. Two multiline cells

Column 1  | Column 2  | Column 3
----------|-----------|------------
One 1     | Two 1     | - Three and        +
          |           | - Three continued  +
          |           | - Three continued 2
One 2     | Two 2 +   | Three 2
          | Two 2 2 + | 
          | Two 2 3 + |
          | Two 2 4   |          
One 3     | Two 3     | Three 3


## 4. Multiline cell with mixed content (text and list)

Column 1  | Column 2  | Column 3
----------|-----------|------------
One       | Two       | Tree: +
          |           | - Three            +
          |           | - Three continued  +          
          |           | - Three continued 2
One       | Two       | Three

## 5. Multiline cell with long text

Column 1  | Column 2  | Column 3
----------|-----------|------------
One       | Two       | Lorem ipsum dolor sit amet, consectetur adipiscing elit.+ 
          |           | Aenean quam nunc, accumsan et libero a, mattis egestas metus. Aliquam mattis laoreet magna sit amet lacinia.+ 
          |           | Mauris dictum tortor sit amet enim iaculis dapibus. Pellentesque malesuada diam sem, ac vestibulum mi eleifend in.+ 
          |           | Aenean pharetra diam vitae congue pulvinar. Sed diam purus, egestas ut lacinia congue, vulputate sit amet libero. Integer iaculis augue tellus, ut commodo elit finibus non.+
          |           | - Three            +
          |           | - Three continued  +          
          |           | - Three continued 2
One       | Two       | Three

## 6. Multiline and spanned cells

Column 1  | Column 2  | Column 3
----------|-----------|------------
One 1     | Two 1     | - Three and        +
          |           | - Three continued  +
          |           | - Three continued 2
One 2     | Two 2 +   | Three 2
          | Two 2 2 + | 
          | Two 2 3 + |
          | Two 2 4   |          
"         | Two 3     | "
