# LiteView - A Lightweight & High-Performance PHP Template Engine

LiteView is a **fast, minimal, and efficient PHP template engine** designed for **speed and simplicity**.  
It provides an intuitive syntax inspired by modern template engines like Blade and Twig, while maintaining **low overhead** and **full PHP 7.4+ compatibility**.

## 🚀 Features  
✔ **Ultra-Fast Compilation** – Converts templates into optimized PHP code.  
✔ **Caching System** – Reduces processing time by storing compiled templates.  
✔ **Blocks & Yield** – Supports template inheritance for reusable layouts.  
✔ **Conditionals & Loops** – Native `{% if %}`, `{% foreach %}`, and `{% endif %}` syntax.  
✔ **Safe Output** – Escaping with `{{{ variable }}}` to prevent XSS attacks.  
✔ **Minimal Overhead** – Designed for maximum performance with minimal dependencies.  
✔ **Flexible Configuration** – Supports whitespace trimming and comment removal.  

---

## 📌 Example Usage  

### PHP  
```php
$liteview = new liteview('default_theme', '/path/to/templates', true, '/path/to/cache');
$liteview->render('home.html', [
    'title' => 'Home Page',
    'colors' => ['red', 'blue', 'green']
]);
```

### Template (`home.html`)  
```html
{% extends "layout.html" %}

{% block title %}{{ title }}{% endblock %}

{% block content %}
    <h1>Home</h1>
    <p>Welcome to the home page, list of colors:</p>
    {% if (isset(something) || isset(something["else"])) %}
        <div>Do something</div>
    {% endif %}
    <ul>
        {% foreach (colors as color) %}
        <li>{{ color }}</li>
        {% endforeach %}
    </ul>
{% endblock %}
```

---

## 🛠 Installation  
Simply include `liteview.php` in your project and start rendering templates!  

---

## 📖 User Guide  

### Secure Output  
You can escape output using the `htmlspecialchars` function by using triple curly brackets:  
```html
{{{ output }}}
```
This ensures special characters are not executed as HTML.

---

### Include Additional Templates  
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
{% foreach (colors as color) %}
    <li>{{ color }}</li>
{% endforeach %}
```

---

### Whitespace Trimming  
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

### Removing HTML Comments  
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

### Clearing the Cache  
To clear the cache, execute the following PHP-code:  
```php
$engine->clear_cache();
```

---

## 💡 Why LiteView?  
Unlike Twig or Smarty, LiteView **prioritizes performance** and has **zero unnecessary dependencies**.  
It is the perfect choice for developers who need a **simple, flexible, and fast** template engine for PHP applications.
