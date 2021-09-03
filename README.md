# Find/Replace Textformatter module 

**Applies find/replace patterns to formatted text or markup at runtime.
The patterns may be simple text or more complex regular expressions.**

This module can be handy if you perform a text replacement on all output
from a particular field, without having to manually make the change on 
all instances where it might appear. For instance, maybe you need to 
insert a trademark symbol ™ after every appearance of a brand name, or 
maybe your website hostname has changed and you need to replace all 
references to it, or perhaps you need to replace all `<h1>` headlines
with `<h2>` headlines. These are just a few examples of any number of 
possibilities. See the *Examples* section of this page for how you
might accompish these particular replacements. 

## Install

1. Copy the files for this module to:
   `/site/modules/TextformatterFindReplace/`
   
2. In your admin go to *Modules > Refresh*.    

3. Click “Install” for *Textformatter > Find/Replace*. This will put you
   on the module configuration screen. See the *Usage* section below for
   details. 

4. Go to *Setup > Fields > [any text or textarea field]*, and on the 
  “Details” tab select this module for the “Textformatters”. Save.

5. Edit a page using the field and test that your find/replace patterns
   work how you expect them to. 

## Usage 

This section describes how to use the find/replace match patterns present
on the module configuration screen. You may use simple find/replace 
patterns or regular expression patterns. This module detects which type 
you are using on each line automatically. 

### Simple find/replace

- **Usage:** Enter one per line of: `find=replace` where `find` is the text 
  to find and `replace` is the text to replace it with. 

- **Note:** The simple find/replace is handled by PHP’s
  [str_replace](https://www.php.net/manual/en/function.str-replace.php)
  function. 
  
### Regular expression find/replace  

- **Usage:** Enter one per line of `/find/=replace` where `/find/` is a PCRE 
  regular expression pattern to match and `replace` is the text to replace 
  it with.

- **Delimiters:** The slashes `/` in the `/find/` refer to the open/close 
  regular expression delimiters. Other supported delimiters include `!`, 
  `%` and `@`, if you prefer them for one case or another, i.e.
  `!find!=replace`.
  
- **Modifiers:** The closing delimiter may be followed by one or more PCRE 
  [pattern modifiers](https://www.php.net/manual/en/reference.pcre.pattern.modifiers.php).
  For instance `i` is a modifier that makes the match case-insensitive. 
  So the pattern `/find/i` would match `FIND`, `find`, `FiNd`, etc.

- **Capturing:** If using a `/find/` regular expression with capturing 
  parenthesis like `/(this|that)/` then the `replace` portion may include 
  the portion matched in the parenthesis by specifying `$1` where it should 
  go. If there were a second set of capturing parenthesis, then `$2` would 
  refer to that match, and so on. 
  
- **Optimization:** If you want to check that some text is present before
  attempting to perform a more expensive regular expression match, you can
  do so. Please see the last example in the “Examples” section of this
  document, “Using the check option…” for details. 

- **Note**: The regular expression find/replace is handled by PHP’s 
  [preg_replace](https://www.php.net/manual/en/function.preg-replace.php)
  function. Most of this section just reiterates how that function works.
  
  
### Other usage notes

- You can include a comment on a line by appending `// your comment` to the
  line. The comment portion will be excluded from the match. 

- If any part of your pattern needs to include a literal equals sign `=` 
  then it should be escaped with a backslash, i.e. `\=` so that this module
  does not confuse it with the boundary between find and replace patterns.

## Examples

### Domain/hostname change

Let’s say you decided to change your website domain from `foo-bar-baz.com`
to `foobarbaz.com`. You want to update any appearances of the old domain
to instead use the new domain, whether it appears on its own or as part of
a link in the markup. This can be done with a simple replacement:
~~~
foo-bar-baz.com=foobarbaz.com
~~~

### Append a trademark symbol ™ to a brand name

Let’s say that the brand name is “FooBarBaz” and your boss has told you 
that there needs to be a TM everywhere that it appears, i.e. as “FooBarBaz™”.
This could be handled with the following simple replacement pattern:
~~~
FooBarBaz=FooBarBaz™
~~~
But wait, in some instances there is already a ™ manually added to the brand
name, so our simple replacement would convert it to “FooBarBaz™™”, which is
not what we want. So for this case, we would be better off with a regular 
expression to perform the replacement, enabling us to find/replace only 
instances that do not already have the trademark symbol ™:
~~~
/FooBarBaz(?!™)/ = FooBarBaz™
~~~
The above expression essentially says to match and replace only the term 
“FooBarBaz” if it is not already followed by a ™.

### Replacing headlines of one type with another

The SEO company just informed you that you need to stop using `<h1>` tags 
the body copy because they are diluting the `<h1>` tags in the page’s 
actual headline. So you need to convert all the `<h1>` tags in the body
copy to `<h2>` tags. Here’s how we might do that with a simple replacement:
~~~
h1>=h2>
~~~
Note that we are excluding the `<` part of the tag so that it will match 
both opening and closing tags (`<h1>` and `</h1>`). 

We’ve run up against a bit of an issue though. Some of those `<h1>` tags 
have attributes that need to be retained in the `<h2>`, like `<h1 class="foo">` 
needs to convert to `<h2 class="foo">`, as one example. To do that, we’ll 
match the opening and closing tags separately, so that the attributes in the
opening tag remain. For the closing tag, those won’t have attributes, so we 
can just replace the entire closing tag `</h1>` with `</h2>`. 
~~~
<h1 = <h2
</h1> = </h2>
~~~
Looking good so far, but 
for whatever reason, the SEO company has now told us that the converted 
`<h2>` tags also need to have a class of `primary`, i.e. 
`<h2 class="primary">`. But some of the h1 tags also have other classes
which we need to keep as well (like `class="foo"`). So we’ll need to capture 
existing classes while also inserting the new one. To do that, we’ll need
to use a regular expression match first: 
~~~~
!<h1 class\="([^"]+)"! = <h2 class\="primary $1" 
<h1 = <h2 class\="primary"
</h1> = </h2>
~~~~
A few things to note above: 

- We are using `!` as our regular expression delimiter rather than `/`. I 
  prefer this when matching in HTML/markup since `/` can often be part of 
  markup, like in closing tags. Though for this case it wouldn’t matter 
  what regular expression delimiter you used. 
  
- We are escaping the equals `=` in `class=` with a blackslash, i.e. `class\=` 
  so that this module doesn’t confuse it as the boundary between the find 
  and replace pattern. 
  
- We’re using capturing parenthesis `"([^"]+)"` in the regular expression
  to match everything in the existing class attribute (everything in the
  "quotes"), and then we’re inserting that part into the replacement via 
  the `$1`. 
  
- We are prepending the new class name `primary` to the existing class 
  attribute in the regular expression match (line 1). 

- The second line matches all the h1 tags that don’t have an existing 
  class attribute, or maybe have some other attribute(s). 
  
- The last line matches and replaces all the closing `</h1>` tags since
  that part is not handled by either of the prior lines. 
  
*Continue with the next example for an optimization to the above.*  

### Using the “check” option as an optimization for regex matches

Regular expressions can be complex and thus can also take more time to 
process than a simple replacement. Though they are still quite fast. But 
you might decide that you only want the regular expression to attempt a
match if some other condition is met. 

Think of it like this: we don’t want to take the time to walk to the store 
unless we know they have what we need to buy. So an optimization would be
that we can call them first, and then only walk there after we’ve confirmed
they have what we need. 

The same can be done with a regular expression match by specifying a “check”
value first. This is done by specifying `check=find=replace` rather than 
just `find=replace`. 

To use our previous example where we convert h1 headlines to h2 headlines
while retaining attributes and adding a class, we would like to first check
that there is an `<h1 class` present in the markup before we let the 
regular expression attempt to match. After all, if `<h1 class` isn’t present
then it would be impossible for the regular expression to match. Here’s 
how we can do that:
~~~
<h1 class = !<h1 class\="([^"]+)"! = <h2 class\="primary $1" 
~~~
This is the same match as in the previous example except that it now has the
leading `<h1 class` at the beginning, which is our “check” value. The module
can confirm if the check value is present very quickly and without overhead,
enabling you to run the slower regular expression only when it’ll be useful.

*Note that the “check” portion of a pattern is not case sensitive.*

---
Copyright 2021 by Ryan Cramer
