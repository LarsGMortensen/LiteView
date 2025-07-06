# LiteView - A Lightweight & High-Performance PHP Template Engine

LiteView is a **fast, minimal, and efficient PHP template engine** designed for **speed and simplicity**.  
It provides an intuitive syntax inspired by modern template engines like Blade and Twig, while maintaining **low overhead** and **full PHP 7.4+ compatibility**.

## 🚀 Features  
✔ **Ultra-Fast Compilation** – Converts templates into optimized PHP code.  
✔ **Caching System** – Reduces processing time by storing compiled templates.  
✔ **Blocks & Yield** – Supports template inheritance for reusable layouts.  
✔ **Conditionals & Loops** – Native `{% if %}`, `{% foreach %}`, and `{% endif %}` syntax.  
✔ **Safe Output** – Escaping with `{{ variable }}` to prevent XSS attacks.  
✔ **Minimal Overhead** – Designed for maximum performance with no dependencies.  
✔ **Flexible Configuration** – Supports whitespace trimming and comment removal.  
✔ **Static API** – Fully static, no instantiation required.

---

## 🔧 Installation  
Simply include `liteview.php` in your project and start rendering templates!  
```php
require_once 'LiteView.php';
```
---

## 📌 Usage  

### PHP  
```php
LiteView::render(
    'index.html',             // Template filename
    '/path/to/templates/',    // Template directory
    true,                     // Enable caching
    '/path/to/cache/',        // Cache directory
    true,                     // Trim whitespace
    true,                     // Remove HTML comments
    ['meta_title' => 'Home Page']  // Template variables
);
```

### Layout template (`layout.html`)
```html
<html>
<head>
    <title>{% yield title %}</title>
</head>
<body>
    {% yield content %}
</body>
</html>
```

### Page template (`index.html`)  
```html
{% extends "layout.html" %}

{% block title %}{{ $meta_title }}{% endblock %}

{% block content %}
    <h1>Home</h1>
    <p>Welcome to the home page, list of colors:</p>
    {% if (isset(something) || isset(something["else"])) %}
        <div>Do something</div>
    {% endif %}
    <ul>
        {% foreach ($colors as $color) %}
        <li>{{ $color }}</li>
        {% endforeach %}
    </ul>
{% endblock %}
```



## 📖 User guide  

### Secure output  
You can escape output using the `htmlentities` function by using double curly brackets:
```html
{{ output }}
```
This ensures special characters are not executed as HTML and prevents XSS attacks.

If you need to output raw (unescaped) HTML (for example, when inserting trusted markup), use triple curly brackets:
```html
{{{ output }}}
```
**Warning:** Only use triple curly brackets for trusted content!

---

### Include additional templates  
```html
{% include "forms.html" %}
```
**Note:** You must always use double quotes (`"..."`) in `{% extends %}` and `{% include %}`.

---

### Comments  
To comment out one or more lines of code (or part of a line), use `{# #}` syntax:  
```html
{# This will not be included in the output #}
```

---

### Blocks & Yield  
Blocks allow reusable sections in templates. `{% yield %}` is used to insert block content.

#### **layout.html**
```html
<html>
<head>
    <title>{% yield title %}</title>
</head>
<body>
    {% yield content %}
</body>
</html>
```

#### **about.html**
```html
{% extends "layout.html" %}

{% block title %}About Us{% endblock %}

{% block content %}
    <h1>About Us</h1>
    <p>Welcome to our page.</p>
{% endblock %}
```

---

### Conditionals  
```html
{% if (user == "admin") %}
    Admin Panel
{% else %}
    User Panel
{% endif %}
```

---

### Loops  
```html
{% foreach ($colors as $color) %}
    <li>{{ $color }}</li>
{% endforeach %}
```

---

### Whitespace trimming  
If whitespace trimming is enabled, unnecessary spaces, tabs, and new lines are removed automatically.  
#### **Before:**
```html
<p>    This    is    a    test.  </p>
```
#### **After (trimmed output):**
```html
<p>This is a test.</p>
```

---

### Removing HTML comments  
If enabled, all `<!-- HTML comments -->` are removed from the output.  
#### **Before:**
```html
<!-- This comment will be removed -->
<p>Hello</p>
```
#### **After (clean output):**
```html
<p>Hello</p>
```

---

### Embedding PHP  
You can embed raw PHP using `{% %}` syntax:  
```html
{% echo strtoupper("hello world"); %}
```
#### **Output:**
```html
HELLO WORLD
```

---

## 🔥 Clearing the cache

To clear all compiled template files from the cache directory, use:
```php
LiteView::clear_cache();
```
This will delete all cached PHP files, forcing templates to be recompiled on the next request.

---

## 💡 Why LiteView?

Unlike Twig or Smarty, LiteView **prioritizes performance** and has **zero unnecessary dependencies**.  
It is the perfect choice for developers who need a **simple, flexible, and fast** template engine for PHP applications.  
Now **fully static**, ensuring maximum performance and simplicity!

NOTE: LiteView follows standard template escaping: `{{ ... }}` is always HTML-escaped, `{{{ ... }}}` is unescaped/raw.

## License
LiteView is released under the **GNU General Public License v3.0**. See [LICENSE](LICENSE) for details.

## Contributing
Contributions are welcome! Feel free to fork this repository, submit issues, or open a pull request.

## Author
Developed by **Lars Grove Mortensen** © 2025. Feel free to reach out or contribute!

---

🌟 **If you find this library useful, give it a star on GitHub!** 🌟
