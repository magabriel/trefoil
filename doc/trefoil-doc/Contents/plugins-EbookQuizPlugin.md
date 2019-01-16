## EbookQuizPlugin

The quiz plugin allows adding interactive test functionality to an ebook 
(mainly for *epub* or *mobi* formats, read below note on compatibility).

### Availability

This plugin is available for all editions (read below note on compatibility).

### Usage

~~~.yaml
# <book-dir>/config.yml
book:
    editions:
        <edition-name>
            plugins:
                enabled: [ EbookQuiz ]                
                options:
                    EbookQuiz:
                        ynb:
                            yes: [ "Yes", "True" ]
                            no: [ "No", "False" ]
                            both: [ "Both" ]                 
~~~

### Description

This plugin allows creating an ebook with some interactive
functions, like a question-response test where the user is presented with a set
of questions, each one with a number of predefined responses in the form of 
hyperlinks. The user "cliks" on one response and the "system" tells him
if he has selected the right answer.

First of all, lets define the scope of our solution:

#### Target platforms

The solution MUST work on *Amazon Kindle* readers compatible with the new *KF8* format. 
It MAY also work in another platforms (even with reduced functionality), but the
*Amazon Kindle* readers are the main target.
 
The problem is that the *KF8* format does not allow any kind of interactivity 
other than plain-old hyperlinks (no javascript or another client-side language),
so we need to circumvent the limitation and find another way to do it.

#### Types of tests

A "Quiz" is an interactive test. There are two types of quizes:
 
- **Multiple questions / single answer**, like "A: first response", "B: second response"
  and so on. An especial subtype is a "True/False" test, where the only allowed
  responses are either "True" of "False" (with an optional "Both"). These types will be
  referred to as "ABC" and "YNB" respectively.
  
- **Questionnaire**, where the user is presented with a series of questions that 
  do not have a predefined set of responses but require elaboration or reasoning. 
  When the user has created its own response he can select an option to "reveal" the
  right answer.

#### Technical requirements

As the main target is the *Amazon Kindle* readers that use KF8 format (basically, a 
subset of EPUB3, more information here: <http://wiki.mobileread.com/wiki/KF8>) the 
solution must stick to be just HTML + CSS (without any scripting language). 

Another self-imposed constraint is that the solution must respect the *Markdown* spirit,
where the unparsed text is readable by humans. So no extraneous or made-up syntax will
be used, only standard *Markdown* features.

#### Implementation

All types of quizes will be implemented as standard *Markdown* syntax with a predefined
structure. This structure is also easily readable and just "makes sense", so even if
the book doesn't enable this plugin the resulting ebook will be valid (but without the
interactive features, of course).

#### Syntax of a quiz

The different quiz types share a common syntax, with the following remarks:

- The whole quiz markup is enclosed in an HTML `<div>` block. This is totally allowed
  by *Markdown* and allows the quiz to be parsed effectively by the plugin:
    
  `<div markdown="1" class="quiz-xxxxx" data-id="xxxxx">...</div>`
  
  - The `markdown="1"` attribute is needed in order for the contents to be processed by 
    the *Markdown* parser.
  
  - The `class="quiz-xxxxx"` tells the plugin parser the quiz type that follows. Allowed
    values are `quiz-activity` and `quiz-questionnaire`.  The parser will automatically 
    decide if a "quiz-activity" is of type "ABC" or "YBN" (more on that later).

  - The `data-id="xxxxx"` attribute assigns an unique id to this quiz. 
    
- The markup itself has a few optional parts. If not provided they will be left blank 
  in the quiz rendering or assigned sensible defaults.
 
- The questions are represented by an ordered list, and the responses with another 
  embedded ordered list. The correct response to each question must be denoted with 
  **bold** format.
 
- Please note that the quiz markup MUST be correctly parsable by *Markdown* or the plugin
  will be unable to process it.
  
- For the interactive solutions to be rendered, a book element of type "ebook-quiz-solution"
  must be present at the end of the book.
 
 
#### Compatibility notes

- The generated quizes will only work on *Amazon Kindle* devices or applications
  with KF8 capabilities (this includes the *Kindle 4* devices onwards).

- `KindleTweaksPlugin` interferes with this plugin. Do not enable it if you use
  this plugin. 

### Examples

#### ABC-type quiz syntax

~~~.markdown
<div markdown="1" class="quiz-activity" data-id="quiz-id-1">
##### Activity 1 heading
###### Activity subheading (optional).

Activity description (optional). Lorem ipsum dolor sit amet,
consetetur sadipscing elitr, sed diam non umyeirmod.

1. First question.
    
    1. **First response.**
    2. Second response.
    3. Third response.

2. Second question.
    
    1. First response.
    2. **Second response**.
        
        This is an optional explanation.
        
        Lorem ipsum dolor sit amet, consetetur
        sadipscing elitr, sed diam nonumyeirmod
        tempor invidunt ut labore et dolore magna
        aliquyam erat, sed diamvoluptua.
    
    3. Third response.
    4. Fourth response.
  
</div>
~~~

#### YNB-type quiz syntax

~~~.markdown
<div markdown="1" class="quiz-activity" data-id="quiz-id-2">
##### Activity 2 heading
###### Activity subheading (optional).

Activity description (optional). Lorem ipsum dolor sit amet, 
consetetur sadipscing elitr, sed diam non umyeirmod.

1. First question.

    1. **True**
    
        This is the correct response (true).
    
    2. False

2. Second question.

    1. True
    2. **False**
        
        This is the correct response (false).
    
</div>
~~~

#### Questionnaire-type quiz syntax

~~~.markdown
<div markdown="1" class="quiz-questionnaire" data-id="quiz-id-3" >
##### Questionnaire 1 heading
###### Questionnaire subheading (optional).

Questionnaire description (optional). Lorem ipsum dolor sit amet, 
consetetur sadipscing elitr, sed diam non umyeirmod.

1. First question.

  ###### Solution
    
    The solution to the first question (optional).

2. Second question.

3. Third question.

  ###### Solution
    
    The solution to the third question (optional).

</div>
~~~


### Rendered examples

The quizes shown above as examples will render as follows:

{% if app.edition('format') in ['epub', 'mobi'] %}

N> ##### NOTE
N> The following rendered version of the examples uses plain HTML
N> to implement the user interactions. No Javascript or other
N> scripting language are allowed by the edition format.
N> 
N> To get the full funcionality of the rendered quizes you need an
N> ebook reader whith supoort for page breaks. If you are reading 
N> this in a reader which does not support page breaks the examples 
N> **will not render properly**.

{% else %}

N> ##### NOTE
N> The following rendered version of the examples is not the same
N> than the one you will get for an `epub` or `mobi` ebook 
N> because of the limited capabilities of the format. 
N> Main differences are:
N> 
N> - The `epub` or `mobi` versions are rendered using only HTML.
N> - This version uses Javascript to implement the user interactions.
N> 
N> Please generate the `epub` or `mobi` versions of this documentation
N> to fully appreciate the differences.

{% endif %}

#### ABC-type quiz syntax

<div markdown="1" class="quiz-activity" data-id="quiz-id-1">
##### Activity 1 heading
###### Activity subheading (optional).

Activity description (optional). Lorem ipsum dolor sit amet, 
consetetur sadipscing elitr, sed diam non umyeirmod.

1. First question.

    1. **First response.**
    2. Second response.
    3. Third response.

2. Second question.

    1. First response.
    2. **Second response**.
        
        This is an optional explanation.
        
        Lorem ipsum dolor sit amet, consetetur 
        sadipscing elitr, sed diam nonumyeirmod 
        tempor invidunt ut labore et dolore magna 
        aliquyam erat, sed diamvoluptua. 

    3. Third response.
    4. Fourth response.
  
</div>


#### YNB-type quiz syntax

<div markdown="1" class="quiz-activity" data-id="quiz-id-2">
##### Activity 2 heading
###### Activity subheading (optional).

Activity description (optional). Lorem ipsum dolor sit amet, 
consetetur sadipscing elitr, sed diam non umyeirmod.

1. First question.

    1. **True**
    
        This is the correct response (true).
    
    2. False

2. Second question.

    1. True
    2. **False**
        
        This is the correct response (false).
    
</div>

#### Questionnaire-type quiz syntax

<div markdown="1" class="quiz-questionnaire" data-id="quiz-id-3" >
##### Questionnaire 1 heading
###### Questionnaire subheading (optional).

Questionnaire description (optional). Lorem ipsum dolor sit amet, 
consetetur sadipscing elitr, sed diam non umyeirmod.

1. First question.

  ###### Solution
    
    The solution to the first question (optional).

2. Second question.

3. Third question.

  ###### Solution
    
    The solution to the third question (optional).

</div>