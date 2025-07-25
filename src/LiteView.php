<?php
/**
 * LiteView - Lightweight PHP template engine
 * 
 * Copyright (C) 2025 Lars Grove Mortensen. All rights reserved.
 * 
 * LiteView is a single-file PHP class for compiling and rendering lightweight 
 * template files with support for caching, includes, and template inheritance.
 * 
 * LiteView is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * LiteView is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with LiteView. If not, see <https://www.gnu.org/licenses/>.
 */


namespace LiteView;


class LiteView {
	
	/** @var string Path to the templates directory */
	private static string $template_path;
	
	/** @var string Path to the cache directory */
	private static string $cache_path;
	
	/** @var bool Whether caching is enabled */
	private static bool $cache_enabled;
	
	/** @var bool Whether to trim unnecessary whitespace */
	private static bool $trim_whitespace;
	
	/** @var bool Whether to remove HTML comments */
	private static bool $remove_html_comments;
	
	/** @var array Stores block contents */
	private static array $blocks = [];
	

	/**
	 * Renders a template with the given parameters and compiles it if necessary.
	 * 
	 * This method combines initialization and rendering in a single call.
	 * It sets up the template engine configuration, compiles the template (if needed),
	 * and includes the generated PHP file with the provided template data.
	 *
	 * @param string $template The template filename (e.g., 'home.html').
	 * @param string $template_path The base directory where templates are stored.
	 * @param bool $cache_enabled Whether to enable template caching.
	 * @param string $cache_path The directory where compiled templates are stored.
	 * @param bool $trim_whitespace Whether to remove unnecessary whitespace from the output.
	 * @param bool $remove_html_comments Whether to strip HTML comments (<!-- ... -->).
	 * @param array $data Associative array of variables to be available in the template.
	 */
	public static function render(string $template, string $template_path, bool $cache_enabled, string $cache_path, bool $trim_whitespace = false, bool $remove_html_comments = false, array $data = []): void {
		
		// Reset stored blocks for template inheritance
		self::$blocks = [];
		
		// Set up template engine configuration
		self::$template_path = rtrim($template_path, '/') . '/';
		self::$cache_enabled = $cache_enabled;
		self::$cache_path = rtrim($cache_path, '/') . '/';
		self::$trim_whitespace = $trim_whitespace;
		self::$remove_html_comments = $remove_html_comments;
		
		// Compile the template if necessary
		$compiled_file = self::compile($template);
		
		// Extract template variables
		extract($data, EXTR_SKIP);
		
		// Extract template variables safer (but slower)
		// foreach ($data as $key => $value) {
			// if (!isset($$key)) {
				// $$key = $value;
			// }
		// }
		
		// Include the compiled PHP file
		require $compiled_file;
	}	
	
	/**
	 * Compiles a template file into a cached PHP file.
	 *
	 * This method checks if the template has already been compiled and is up to date.
	 * If caching is enabled and the compiled version exists, it returns the cached file.
	 * Otherwise, it processes the template syntax, applies inheritance, includes, 
	 * and minifies content before saving the compiled version.
	 *
	 * @param string $template The template filename (e.g., 'home.html').
	 * @return string The full path to the compiled PHP file.
	 *
	 * @throws RuntimeException If the template file does not exist or cannot be read.
	 */
	private static function compile(string $template): string {
		
		// Full path to the source template file
		$source_path = self::$template_path . $template;
		
		// Generate a unique cache filename by replacing slashes and ".html" to avoid conflicts
		$cache_file = self::$cache_path . str_replace(['/', '.html'], ['_', ''], $template) . '.php';

		// Return cached file if it exists and is up-to-date
		if (self::$cache_enabled && file_exists($cache_file) && @filemtime($source_path) <= filemtime($cache_file)) {
			return $cache_file;
		}		

		// Load and process the template file
		$code = file_get_contents($source_path);

		// Processes template inheritance, allowing child templates to extend parent templates
		$code = self::process_extends($code);

		// Removes template comments ({# ... #}) from the code
		$code = self::remove_comments($code);

		// Includes external template files ({% include "file.html" %})
		$code = self::process_includes($code);

		// Compiles template syntax into PHP code
		$code = self::compile_syntax($code);

		// Removes HTML comments (<!-- ... -->) if enabled
		if (self::$remove_html_comments) {
			$code = self::remove_html_comments($code);
		}

		// Removes unnecessary whitespace to optimize output
		if (self::$trim_whitespace) {
			$code = preg_replace('/\s+/', ' ', $code);
		}

		// Save compiled file
		file_put_contents($cache_file, "<?php class_exists('" . __CLASS__ . "') or exit; ?>\n" . rtrim($code));
		return $cache_file;
	}

	/**
	 * Processes `{% extends "parent.html" %}` directives for template inheritance.
	 *
	 * If a template extends another, this method loads the parent template
	 * and merges the child template's blocks into it.
	 *
	 * @param string $code The child template content.
	 * @return string The final merged template content.
	 */
	private static function process_extends(string $code): string {
		
		// Check if the template contains an `{% extends "..." %}` directive
		if (preg_match('/{% extends "(.*?)" %}/', $code, $match)) {
			
			// Construct the full path to the parent (layout) template
			$layout_file = self::$template_path . $match[1];
			
			// Ensure the layout file exists before proceeding
			if (file_exists($layout_file)) {
				
				// Load the parent template content
				$layout_code = file_get_contents($layout_file);
				
				// Merge the child template's blocks into the parent layout
				return self::merge_blocks($layout_code, $code);
			}
		}
		
		// If no `{% extends %}` is found, return the original template unchanged
		return $code;
	}

	/**
	 * Merges child template blocks into the parent layout.
	 * 
	 * This method allows template inheritance by replacing `{% yield block_name %}`
	 * in the parent layout with content from `{% block block_name %}...{% endblock %}` in the child template.
	 *
	 * @param string $layout_code The content of the parent template.
	 * @param string $child_code The content of the child template.
	 * @return string The merged template code with blocks replaced.
	 */
	private static function merge_blocks(string $layout_code, string $child_code): string {
		
		// Find all `{% block block_name %} ... {% endblock %}` sections in the child template
		preg_match_all('/{%\s*block\s*(\w+)\s*%}(.*?){%\s*endblock\s*%}/s', $child_code, $matches, PREG_SET_ORDER);
		
		// Loop through all matched blocks and replace `{% yield block_name %}` in the layout
		foreach ($matches as $match) {
			$block_name = $match[1]; // Block name (e.g., "content")
			$block_content = trim($match[2]); // Block content
			
			// Replace `{% yield block_name %}` in the parent layout with the block content from the child template
			$layout_code = preg_replace('/{%\s*yield\s*' . $block_name . '\s*%}/', $block_content, $layout_code);
		}
		
		// Return the processed layout with blocks merged
		return $layout_code;
	}	

	/**
	 * Processes `{% include "filename" %}` directives and replaces them 
	 * with the contents of the specified template file.
	 * 
	 * This allows templates to reuse common components like headers, footers, or widgets.
	 *
	 * @param string $code The template code containing `{% include "file.html" %}` directives.
	 * @return string Processed template code with included files inserted.
	 */
	private static function process_includes(string $code): string {
		
		// Search for `{% include "filename" %}` and replace it with the actual file contents
		return preg_replace_callback('/{% include "(.*?)" %}/i', function ($matches) {
			
			// Load and return the content of the included template file
			return file_get_contents(self::$template_path . $matches[1]);
			
		}, $code);
	}

	/**
	 * Compiles template syntax into executable PHP code.
	 *
	 * This method converts template-specific syntax (e.g., `{% if %}`, `{{ variable }}`) 
	 * into native PHP code, allowing templates to be compiled and executed efficiently.
	 *
	 * @param string $code Template code containing custom syntax.
	 * @return string Compiled PHP code.
	 */
	private static function compile_syntax(string $code): string {
		$patterns = [
		
			// Executes and outputs (echo) the result of a PHP expression: {?= ... ?}
			'/{\?=\s*(.+?)\s*\?}/s' => '<?php echo $1; ?>',
		
			// Execute PHP code (no output): {? ... ?}
			'/{\?(.+?)\?}/s' => '<?php$1?>',
		
			// Block definition: {% block name %}
			'/{%\s*block\s+([\w-]+)\s*%}/' => '<?php ob_start(); self::$blocks["$1"] = ""; ?>',
			
			// Block end: {% endblock %}
			'/{%\s*endblock\s*%}/' => '<?php self::$blocks[array_key_last(self::$blocks)] = ob_get_clean(); ?>',
			
			// Block yield (output block content): {% yield name %}
			'/{%\s*yield\s*(\w+)\s*%}/' => '<?php echo self::$blocks["$1"] ?? ""; ?>',
			
			// Unescaped variable output: {{{ variable }}}
			'/\{{{\s*(.+?)\s*}}}/'  => '<?php echo $1; ?>',			
			
			// Escaped variable output: {{ variable }} (HTML-escaped)
			'/\{{\s*(.+?)\s*}}/'    => '<?php echo htmlspecialchars($1 ?? "", ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>',
			
			// Conditional statements:
			'/{%\s*if\s*(.+?)\s*%}/' => '<?php if ($1): ?>',
			'/{%\s*elseif\s*(.+?)\s*%}/' => '<?php elseif ($1): ?>',
			'/{%\s*else\s*%}/' => '<?php else: ?>',
			'/{%\s*endif\s*%}/' => '<?php endif; ?>',
			
			// Looping constructs:
			'/{%\s*foreach\s*\((.+?)\)\s*%}/' => '<?php foreach ($1): ?>',
			'/{%\s*endforeach\s*%}/' => '<?php endforeach; ?>',
		];
		
		// Apply all regex replacements to the template code
		return preg_replace(array_keys($patterns), array_values($patterns), $code);
	}
		
	/**
	 * Removes `{# ... #}` comments from the template while preserving the correct structure.
	 *
	 * This function efficiently removes comments from the template without affecting the surrounding
	 * content. It correctly handles nested comments and ensures that no duplicated content appears.
	 *
	 * - Uses a single pass (`O(n)`) for optimal performance.
	 * - Supports nested `{# ... #}` comments.
	 * - Ensures that non-comment content is preserved exactly as in the original template.
	 *
	 * @param string $code The template code containing `{# ... #}` comments.
	 * @return string The cleaned template code with all comments removed.
	 */
	private static function remove_comments(string $code): string {
		// Early exit: If no `{#` is found, return immediately (no comments to remove)
		if (strpos($code, '{#') === false) {
			return $code;
		}

		$len = strlen($code); // Get the length of the template
		$depth = 0;           // Tracks the nesting level of comments
		$start = 0;           // Start position for capturing content outside comments
		$output = '';         // Buffer to store the final cleaned template

		for ($i = 0; $i < $len; $i++) {
			// Detect the start of a comment block `{#`
			if ($i < $len - 1 && $code[$i] === '{' && $code[$i + 1] === '#') {
				if ($depth === 0) {
					// Append content before the comment starts
					$output .= substr($code, $start, $i - $start);
				}
				$depth++; // Increase nesting level
				$i++;     // Skip the next character (`#`)
				continue;
			}

			// Detect the end of a comment block `#}`
			if ($i < $len - 1 && $code[$i] === '#' && $code[$i + 1] === '}' && $depth > 0) {
				$depth--;  // Decrease nesting level
				$i++;      // Skip the next character (`}`)
				$start = $i + 1; // Update the start position to resume capturing content
				continue;
			}
		}

		// Append any remaining content after the last comment
		if ($depth === 0 && $start < $len) {
			$output .= substr($code, $start);
		}

		return $output;
	}

	/**
	 * Removes `<!-- ... -->` HTML comments from the template.
	 * 
	 * This method ensures that only standard HTML comments are removed, while keeping 
	 * conditional comments (e.g., `<!--[if IE]> ... <![endif]-->`) intact.
	 *
	 * @param string $code The template content.
	 * @return string The cleaned template content without standard HTML comments.
	 */
	private static function remove_html_comments(string $code): string {
		// return preg_replace('/<!--.*?-->/s', '', $code);
		
		// Regular expression explanation:
		// - `<!--(?!<!)` ? Match `<!--` only if it's NOT followed by `<`, preserving conditional comments.
		// - `[^\[>].*?` ? Match everything inside the comment, ensuring it's not an IE conditional comment.
		// - `-->` ? Ensure it ends with `-->`.
		// - `s` modifier ? Allows `.` to match newlines, so multi-line comments are removed properly.
		return preg_replace('/<!--(?!<!)[^\[>].*?-->/s', '', $code);
	}

	/**
	 * Clears all cached templates.
	 */
	public static function clear_cache(): void {
		
		// Get all compiled template files (*.php) in the cache directory
		// If glob() returns false (no matches), use an empty array to avoid errors
		$files = glob(self::$cache_path . "*.php") ?: [];
		
		// Delete each cached file
		array_map('unlink', $files);
	}
}
