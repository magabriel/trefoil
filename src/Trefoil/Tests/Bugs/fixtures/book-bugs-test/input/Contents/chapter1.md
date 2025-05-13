# BUG 001

**Bug:**

Internal links in PDF edition types appeared incomplete (without the `</a>` closing tag). 
Other types of editions were OK (i.e. epub).

**Expected behaviour:**

Internal links should be correctly rendered in PDF edition types.

NOTE: Links are colored in the following examples.

- External links must be BLUE.
- Internal liks must be RED.

<div class="bug-001" markdown="1">

**Example with Markdown links:**

`[This is a Markdown internal link](#the-link-to-test)`

[This is a Markdown internal link](#the-link-to-test)

**Example with HTML links:**

`<a href="#the-link-to-test">This is a Markdown internal link</a>`

<a href="#the-link-to-test">This is an HTML internal link</a>

`<a class="the-class" href="#the-link-to-test">This is an HTML internal link with a class</a>`

<a class="the-class" href="#the-link-to-test">This is an HTML internal link with a class</a>
 
**External links are not modified:**

`<a href="http://google.com">Link to Google</a>`

<a href="http://google.com">Link to Google</a>

<div class="page-break"></div>

## The link target 

<div id="the-link-to-test">This is the link target for #the-link-to-test</div>
<a href="#bug-001">Return</a>

</div>