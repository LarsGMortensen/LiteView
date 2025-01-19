# LiteView
LiteView is a fast, minimal, and efficient PHP template engine designed for speed and simplicity. It provides an intuitive syntax inspired by modern template engines like Blade and Twig, while maintaining low overhead and full PHP 7.4+ compatibility.

🚀 Features:
✔ Ultra-Fast Compilation – Converts templates into optimized PHP code.
✔ Caching System – Reduces processing time by storing compiled templates.
✔ Blocks & Yield – Supports template inheritance for reusable layouts.
✔ Conditionals & Loops – Native {% if %}, {% foreach %}, and {% endif %} syntax.
✔ Safe Output – Escaping with {{{ variable }}} to prevent XSS attacks.
✔ Minimal Overhead – Designed for maximum performance with minimal dependencies.
✔ Flexible Configuration – Supports whitespace trimming and comment removal.

📌 Example Usage:
PHP:

php
Kopiér
Rediger
$engine = new liteview('default_theme', true, '/path/to/cache');
$engine->render('home.html', [
    'title' => 'Welcome!',
    'items' => ['Apple', 'Banana', 'Cherry']
]);
Template (home.html)

html
Kopiér
Rediger
{% extends "layout.html" %}

{% block title %}{{ title }}{% endblock %}

{% block content %}
    <h1>{{ title }}</h1>
    <ul>
        {% foreach (items as item) %}
        <li>{{ item }}</li>
        {% endforeach %}
    </ul>
{% endblock %}
🛠 Installation:
Simply include liteview.php in your project and start rendering templates!

💡 Why LiteView?
Unlike Twig or Smarty, LiteView prioritizes performance and has zero unnecessary dependencies. It is the perfect choice for developers who need a simple, flexible, and fast template engine for PHP applications.
