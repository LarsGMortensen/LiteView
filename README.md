# LiteView - A Lightweight & High-Performance PHP Template Engine

LiteView is a **fast, minimal, efficient, single-file PHP template engine** focused on **runtime performance** and **developer ergonomics**. 
It offers a very **low overhead** and an intuitive syntax inspired by other modern template engines while staying dependency-free and easy to embed in any codebase (**PHP 8.1+**).

---

## 🚀 Features that make LiteView stand out

- **Blazing-fast compilation** – Templates are turned into pure, optimized PHP with near-zero overhead.
- **Smart caching** – Dependency-aware cache with atomic writes and OPcache refresh ensures instant, safe performance boosts.
- **Elegant inheritance and includes** – {% extends %}, {% block %}, {% yield %} and {% include %} for clean, DRY layouts – with strict fail-fast validation to catch mistakes early.
- **Modern syntax** – Write templates using {% if %}, {% foreach %}, {{ variable }}, {{{ raw }}}, and {?= expr ?} – simple, powerful, familiar.
- **Conditionals & Loops** – Native `{% if %}`, `{% foreach %}`, and `{% endif %}` syntax.
- **Safe Output** – Escaping with `{{ variable }}` to prevent XSS attacks.
- **Secure by default** – Automatic HTML-escaping prevents XSS, while trusted raw output is still available when needed.
- **Lightweight by design** – Single file, zero dependencies, fully static API. Perfect for performance-critical apps.
- **Configurable flexibility** – Toggle whitespace trimming, HTML comment removal, and PHP block support.
- **Production-ready** – Strict error handling, safe path resolution, and reliable cache invalidation built-in.
- **Minimal Overhead** – Designed for maximum performance with no dependencies.
- **Static API** – Fully static, no instantiation required.
- **Developer friendly** – No bootstrapping, no learning curve – just drop in and start rendering.

---

## 📦 Installation

Via Composer (recommended):

```bash
composer require larsgmortensen/liteview
````

Or manually add the class file (e.g. `src/LiteView/LiteView.php`) and load it via Composer’s autoloader or a `require_once`:

```php
use LiteView\LiteView;
require_once __DIR__.'/src/LiteView/LiteView.php';
```

> **Tip:** Place your **cache directory outside webroot** (or deny direct access).

---

## 🚀 Quick Start

```php
use LiteView\LiteView;

LiteView::render(
    'home.html',                  // Template file (relative to $templatePath)
    '/path/to/templates/',        // Template directory
    true,                         // Enable cache
    '/path/to/cache/',            // Cache directory
    true,                         // Trim whitespace
    true,                         // Remove HTML comments
    ['title' => 'Hello World'],   // Data/template variables
    true                          // Allow raw PHP blocks ({? ... ?})
);
```

Capture output as a string:

```php
$html = LiteView::renderToString(
    'page.html',
    '/path/to/templates/',
    true,
    '/path/to/cache/',
    false,
    false,
    ['user' => $user],
    false // disallow raw PHP tags
);
```

### Layout (`layout.html`)

```html
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>{% yield title %}</title>
</head>
<body>
  <header>{% include "partials/header.html" %}</header>
  <main>
    {% yield content %}
  </main>
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

    {% if (isset($something) || isset($something["else"])) %}
        <div>Do something</div>
    {% endif %}

    <ul>
        {% foreach ($colors as $color) %}
        <li>{{ $color }}</li>
        {% endforeach %}
    </ul>
{% endblock %}
```

---

## 🔤 Template Syntax

| Feature                        | Syntax                                                     | Notes                                                                      |
| ------------------------------ | ---------------------------------------------------------- | -------------------------------------------------------------------------- |
| **Escaped output**             | `{{ expr }}`                                               | Encoded with `htmlspecialchars(..., ENT_QUOTES \| ENT_SUBSTITUTE, 'UTF-8')`. |
| **Raw output**                 | `{{{ expr }}}`                                             | No escaping. Only for **trusted** HTML.                                    |
| **Raw PHP echo**               | `{?= expr ?}`                                              | Emits the evaluated expression directly.                                   |
| **Raw PHP block**              | `{? /* php */ ?}`                                          | Executes only if `allowPhpTags=true`.                                      |
| **If / Elseif / Else / Endif** | `{% if (...) %}...{% elseif (...) %}{% else %}{% endif %}` | Pure PHP conditions.                                                       |
| **Foreach / Endforeach**       | `{% foreach ($items as $i) %}...{% endforeach %}`          | Pure PHP foreach.                                                          |
| **Extends**                    | `{% extends "layout.html" %}`                              | One parent per template.                                                   |
| **Block / Endblock**           | `{% block name %}...{% endblock %}`                        | Defines content for a named yield.                                         |
| **Yield**                      | `{% yield name %}`                                         | Replaced with the child’s matching block.                                  |
| **Include**                    | `{% include "file.html" %}`                                | Recurses with depth guard (16).                                            |
| **Template comments**          | `{# ... #}`                                                | **Nested** comments supported. Removed before parsing.                     |

> **Quotes:** Always use **double quotes** in `{% extends %}` and `{% include %}`.

---

## 📖 User Guide

LiteView is designed to be **simple, fast, and intuitive**. Below you’ll find examples of all supported features so you can get productive immediately.

---

### 🔒 Secure Output

Escape variables safely with `{{ ... }}`:

```html
<p>{{ $username }}</p>
```

This prevents XSS by applying `htmlspecialchars()`.

For raw output (no escaping), use triple braces:

```html
<p>{{{ $trustedHtml }}}</p>
```

⚠ Only use raw output with trusted content.

---

### 📂 Template Inheritance

Define reusable layouts with `{% extends %}`, `{% block %}`, and `{% yield %}`.

#### layout.html

```html
<html>
<head>
    <title>{% yield title %}</title>
</head>
<body>
    <header>{% yield header %}</header>
    <main>{% yield content %}</main>
</body>
</html>
```

#### index.html

```html
{% extends "layout.html" %}

{% block title %}Home{% endblock %}

{% block header %}<h1>Welcome</h1>{% endblock %}

{% block content %}
    <p>Welcome to the home page, list of colors:</p>
    {% if (isset($something) || isset($something["else"])) %}
        <div>Do something</div>
    {% endif %}

    <ul>
        {% foreach ($colors as $color) %}
        <li>{{ $color }}</li>
        {% endforeach %}
    </ul>
{% endblock %}
```

LiteView enforces **strict fail-fast validation**:

* Child blocks must match parent yields.
* Duplicate or missing blocks will throw exceptions at compile-time.

---

### 📦 Includes

Insert reusable snippets with `{% include "..." %}`:

```html
{% include "partials/footer.html" %}
```

Includes are recursive and protected against infinite loops with a depth guard.

---

### ❌ Comments

Comment out code with `{# ... #}`:

```html
{# This is a comment and will be removed #}
```

Supports **nested comments** safely.

---

### ➡️ Nested comments

LiteView supports **nested `{# ... #}` comments**, something most template engines do not.

This means you can safely comment out larger blocks of code without worrying about inner `{# ... #}` markers breaking the parser:

```html
{# Outer comment start
   <div>
      {# Inner comment #}
      <p>Content hidden</p>
   </div>
#}
```

The entire block is removed cleanly, leaving no stray `#}` in the output.

---

### 🔀 Conditionals

Use native PHP expressions:

```html
{% if ($user == "admin") %}
    <p>Admin Panel</p>
{% else %}
    <p>User Panel</p>
{% endif %}
```

---

### 🔁 Loops

Iterate with `foreach`:

```html
<ul>
    {% foreach ($items as $item) %}
        <li>{{ $item }}</li>
    {% endforeach %}
</ul>
```

---

### ✂ Whitespace Trimming

If enabled, LiteView removes redundant whitespace outside `<pre>`, `<code>`, `<textarea>`, `<script>`, and `<style>`:

```html
<p>    Hello     World   </p>
```

➡ becomes:

```html
<p>Hello World</p>
```

---

### 🗑 HTML Comment Removal

If enabled, HTML comments are removed (except conditional IE comments):

```html
<!-- This is removed -->
<p>Hello</p>
```

➡ becomes:

```html
<p>Hello</p>
```

---

### 🐘 Embedding PHP

Raw PHP blocks are allowed (configurable via `$allowPhpTags`):

```html
{? echo strtoupper("hello world"); ?}
```

If disabled, these tags are ignored.

---

### 🔥 Clearing the Cache

Force templates to recompile by clearing the cache:

```php
LiteView::clearCache();
```

Deletes all compiled `.php` templates in the cache directory.

---

## 🧰 API

```php
LiteView::render(
    string $template,
    string $templatePath,
    bool   $cacheEnabled,
    string $cachePath,
    bool   $trimWhitespace = false,
    bool   $removeHtmlComments = false,
    array  $data = [],
    bool   $allowPhpTags = true
): void

LiteView::renderToString(...): string

LiteView::clearCache(): void
```

**Parameters**

* **\$templatePath**: Template root (must resolve via `realpath` under this root).
* **\$cachePath**: Directory for compiled templates. Must be writable.
* **\$allowPhpTags**: If `false`, `{? ... ?}` blocks are stripped (safe mode). `{?= ... ?}` remains a direct echo transform.
* **\$trimWhitespace**: Collapses runs of whitespace **outside** sensitive tags: `<pre>`, `<code>`, `<textarea>`, `<script>`, `<style>`.
* **\$removeHtmlComments**: Removes standard HTML comments while preserving IE conditional comments.

---

## 💡 Why LiteView?

LiteView exists for developers who want **performance and simplicity** without the overhead of large engines like Twig or Smarty.

✨ **Blazing Fast** – Compiles to lean PHP with minimal overhead.

✨ **Fail-Fast Safety** – Strict inheritance validation prevents silent template bugs.

✨ **Zero Dependencies** – Pure PHP, fully static API, no instantiation.

✨ **Full PHP Power** – Use real PHP expressions in conditionals and loops.

✨ **Lightweight by Design** – Single-file engine, ideal for micro-frameworks and high-traffic apps.

✨ **Flexible & Configurable** – Toggle whitespace trimming, HTML comment removal, and raw PHP execution.

LiteView is the perfect balance of **developer productivity** and **runtime performance**. 🚀

---

## 🧬 Strict Inheritance (Fail-Fast)

LiteView resolves blocks **at compile time** and enforces invariants:

* **Missing yield:** If a child defines `{% block name %}` that the parent doesn’t yield, **compilation fails**.
* **Missing block:** If the parent contains `{% yield name %}` that the child doesn’t define, **compilation fails**.
* **Duplicate block:** If the child defines the same block twice, **compilation fails**.

This catches layout mistakes early and keeps runtime hot paths minimal (no runtime block stack).

---

## 🗜️ Caching & Invalidation

* Compiled PHP is written to the cache directory using **atomic rename**.
* Invalidation compares the cache mtime against the **max mtime** of the source template and **all discovered dependencies** (`extends` + recursive `include`).
* On successful write, LiteView calls `opcache_invalidate($file, true)` when available and clears PHP’s stat cache for the file.

**Deploy note:** If your deployment preserves file mtimes (`cp -p`, `rsync --times`), changes might not trigger recompilation. Ensure mtimes update or call `LiteView::clearCache()` post-deploy.

---

## 🔐 Security Notes

* Templates are considered **trusted code**. Avoid granting authoring access to untrusted users.
* `{? code ?}` executes raw PHP. You can disable it by setting `$allowPhpTags = false`.
* `{?= expr ?}` (expression output) **always works** and cannot be disabled.
* Variable output is HTML-escaped by default (`{{ variable }}`). Use `{{{ ... }}}` only with trusted HTML.
* Always place `cachePath` **outside webroot**, or restrict access (e.g., with `.htaccess`).

---

## 🧪 Troubleshooting

* **"illegal template path"** -> The resolved file is outside the template root; check relative paths and root.
* **"duplicate block" / "not yielded" / "missing child blocks"** -> Inheritance contract violated. Align parent yields and child blocks.
* **No recompilation after deploy** -> Ensure mtimes change or run `clearCache()`.
* **Windows rename failure** -> LiteView unlinks the destination and retries automatically.

---

## 📈 Performance Tips

* Enable caching in production.
* Keep templates lean and modular – heavy logic belongs in PHP, not templates.
* Place cache outside webroot (or protect with `.htaccess`).
* OPcache integration is automatic – LiteView invalidates cached PHP files as needed.
* Avoid excessive includes – each include adds a dependency check.
* Enable whitespace trimming and HTML comment removal for production builds.
* Disable `$allowPhpTags` in production unless you explicitly need raw `{? ... ?}` blocks.

---

## 📦 Packagist

LiteView on Packagist:
👉 [https://packagist.org/packages/larsgmortensen/liteview](https://packagist.org/packages/larsgmortensen/liteview)

---

## 📜 License

LiteView is released under the **GNU General Public License v3.0**. See [LICENSE](LICENSE) for details.

## 🤝 Contributing

Contributions are welcome! Feel free to fork this repository, submit issues, or open a pull request.

## ✍️ Author

Developed by **Lars Grove Mortensen** © 2025. Feel free to reach out or contribute!

---

LiteView – the single-file engine that gives you speed, safety, and simplicity 🚀

---

🌟 **If you find this library useful, give it a star on GitHub!** 🌟
