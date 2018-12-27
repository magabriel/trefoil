## KindleTweaksPlugin

This plugin provides several tweaks to make the ebook more compatible with 
Kindle MOBI format. 

This is only needed for the *old* MOBI format, not the  *new* KF8 format, 
so if you are not targeting old Kindle devices you may not need this plugin.

### Availability

This plugin is available for `Epub` and `Mobi` editions.

N> ##### Note
N> The provided tweaks are specificaly targeted to Kindle readers, but could also
N> be useful for some EPUB reader devices or applications. That is the reason why 
N> the plugin is available also for `Epub` editions.

### Usage

~~~.yaml
# <book-dir>/config.yml 
book:
    editions:
        <edition-name>
            plugins:
                enabled: [ KindleTweaks ]
~~~ 

### Description

The plugin provides the following tweaks:

- Convert paragraphs inside list elements to line breaks.
- Explicit table cell alignment.

#### Convert paragraphs inside lists

The Markdown processor allows two kinds of markup to generate a list:

~~~.markdown
A list without blank lines between elements:

- One.
- Two.
- Three.

The same list but element "Two" has a second line:

- One.
- Two.
  
  This is second line of "Two" element.
    
- Three.
~~~  

The generated HTML will look like: 

~~~.html
<p>A list without blank lines between elements:</p>

<ul>
<li>One.</li>
<li>Two.</li>
<li>Three.</li>
</ul>

<p>The same list but element "Two" has a second line:</p>

<ul>
<li>One.</li>
<li><p>Two.</p>

<p>This is second line of "Two" element.</p></li>
<li><p>Three.</p></li>
</ul>
~~~

In the second list, elements "One" and "Three" are still rendered "as is", 
but element "Two" has paragraph tags enclosing each "paragraph". 
This could cause rendering issues with older Kindles, making element "Two"
look different than elements "One" and "Three".

When this plugin is activated all elements will be rendered without the extra
paragraph tags, and all internal paragraph tags will be replaced by a 
line break tag:

~~~.html
<p>The same list but element "Two" has a second line:</p>

<ul>
<li>One.</li>
<li>Two.<br/>

This is second line of "Two" element.</li>
<li>Three.</li>
</ul>
~~~

#### Explicit table cell alignment

Table cell alignment assigned via CSS classes doesn't work in older Kindles. 
This plugin will assign explicit alignment via HTML attributes to the table
cells.

 

