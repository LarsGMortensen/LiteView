<?php
/**
 * LiteView - A Lightweight PHP Template Engine
 * 
 * Copyright (C) 2025 Lars Grove Mortensen. All rights reserved.
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

namespace liteview;

/**
 * Template engine for processing and rendering templates with caching support.
 */
class liteview {
	/** @var string Path to the templates directory */
	private $template_path;
	/** @var string Path to the cache directory */
	private $cache_path;
	/** @var bool Whether caching is enabled */
	private $cache_enabled;
	/** @var string Theme name */
	private $theme;
	/** @var bool Whether to trim unnecessary whitespace */
	private $trim_whitespace;
	/** @var bool Whether to remove template comments */
	private $remove_comments;
	/** @var bool Whether to remove HTML comments */
	private $remove_html_comments;
	/** @var array Stores block contents */
	private $blocks = [];

	/**
	 * Constructor
	 * 
	 * @param string $theme The theme name
	 * @param bool $cache_enabled Enable or disable caching
	 * @param string $cache_path Path to cache directory
	 * @param bool $trim_whitespace Whether to trim whitespace
	 * @param bool $remove_comments Whether to remove template comments
	 * @param bool $remove_html_comments Whether to remove HTML comments
	 */
	public function __construct(string $theme, string $template_path, bool $cache_enabled, string $cache_path, bool $trim_whitespace = false, bool $remove_html_comments = false) {
		$this->theme = $theme;
		$this->template_path = rtrim($template_path, '/') . '/';
		$this->cache_enabled = $cache_enabled;
		$this->cache_path = rtrim($cache_path, '/') . '/';
		$this->trim_whitespace = $trim_whitespace;
		$this->remove_html_comments = $remove_html_comments;
	}

	/**
	 * Renders a template by compiling it and including the generated file.
	 * 
	 * @param string $template Template filename
	 * @param array $data Associative array of template variables
	 */
	public function render(string $template, array $data = []): void {
		$compiled_file = $this->compile($template);
		extract($data, EXTR_SKIP);
		require $compiled_file;
	}

	/**
	 * Compiles a template into a cached PHP file.
	 * 
	 * @param string $template Template filename
	 * @return string Path to the compiled PHP file
	 */
	private function compile(string $template): string {
		$source_path = $this->template_path . $this->theme . '/' . $template;
		$cache_file = $this->cache_path . str_replace(['/', '.html'], ['_', ''], $template) . '.php';
		
		// Return cached file if it exists and is up-to-date
		if ($this->cache_enabled && file_exists($cache_file) && @filemtime($source_path) <= filemtime($cache_file)) {
			return $cache_file;
		}

		// Load and process the template file
		$code = file_get_contents($source_path);

		// Processes template inheritance, allowing child templates to extend parent templates
		$code = $this->process_extends($code);		

		// Removes template comments ({# ... #}) from the code
		$code = $this->remove_comments($code);

		// Includes external template files ({% include "file.html" %})
		$code = $this->process_includes($code);

		// Compiles template syntax into PHP code
		$code = $this->compile_syntax($code);

		// Removes HTML comments (<!-- ... -->) if enabled
		if ($this->remove_html_comments) {
			$code = $this->remove_html_comments($code);			
		}
		
		// Removes unnecessary whitespace to optimize output
		if ($this->trim_whitespace) {
			$code = preg_replace('/\s+/', ' ', $code);
		}

		// Save compiled file
		file_put_contents($cache_file, "<?php class_exists('" . __CLASS__ . "') or exit; ?>\n" . rtrim($code));
		return $cache_file;
	}

	/**
	 * Removes the `{% extends ... %}` directive.
	 * 
	 * @param string $code Template code
	 * @return string Processed template code
	 */
	private function process_extends(string $code): string {
		if (preg_match('/{% extends "(.*?)" %}/', $code, $match)) {
			$layout_file = $this->template_path . $this->theme . '/' . $match[1];
			if (file_exists($layout_file)) {
				$layout_code = file_get_contents($layout_file);
				return $this->merge_blocks($layout_code, $code);
			}
		}
		return $code;
	}

	/**
	 * Merge child template blocks into the parent layout.
	 */
	private function merge_blocks(string $layout_code, string $child_code): string {
		preg_match_all('/{%\s*block\s*(\w+)\s*%}(.*?){%\s*endblock\s*%}/s', $child_code, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$block_name = $match[1];
			$block_content = trim($match[2]);
			$layout_code = preg_replace('/{%\s*yield\s*' . $block_name . '\s*%}/', $block_content, $layout_code);
		}
		return $layout_code;
	}

	/**
	 * Processes `{% include ... %}` directives and inserts the included file content.
	 * 
	 * @param string $code Template code
	 * @return string Processed template code
	 */
	private function process_includes(string $code): string {
		return preg_replace_callback('/{% include "(.*?)" %}/i', function ($matches) {
			//return file_get_contents(CITOMNI_SYSTEM_PATH . "/app/views/templates/{$this->theme}/{$matches[1]}");
			return file_get_contents($this->template_path . $this->theme . '/' . $matches[1]);
		}, $code);
	}

	/**
	 * Compiles template syntax into executable PHP code.
	 * 
	 * @param string $code Template code
	 * @return string Compiled PHP code
	 */
	private function compile_syntax(string $code): string {
		$patterns = [
			'/{%\s*block\s+([\w-]+)\s*%}/' => '<?php ob_start(); $this->blocks["$1"] = ""; ?>',
			'/{%\s*endblock\s*%}/' => '<?php $this->blocks[array_key_last($this->blocks)] = ob_get_clean(); ?>',
			'/{%\s*yield\s*(\w+)\s*%}/' => '<?php echo $this->blocks["$1"] ?? ""; ?>',
			'/\{{{\s*(.+?)\s*}}}/'  => '<?php echo htmlentities($1, ENT_QUOTES, "UTF-8"); ?>',
			'/\{{\s*(.+?)\s*}}/'    => '<?php echo $1; ?>',
			'/{%\s*if\s*(.+?)\s*%}/' => '<?php if ($1): ?>',
			'/{%\s*elseif\s*(.+?)\s*%}/' => '<?php elseif ($1): ?>',
			'/{%\s*else\s*%}/' => '<?php else: ?>',
			'/{%\s*endif\s*%}/' => '<?php endif; ?>',			
			'/{%\s*foreach\s*\((.+?)\)\s*%}/' => '<?php foreach ($1): ?>',
			'/{%\s*endforeach\s*%}/' => '<?php endforeach; ?>',
		];
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
	function remove_comments(string $code): string {
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
	 * Removes `<!-- ... -->` HTML comments.
	 */
	public function remove_html_comments(string $code): string {
		// return preg_replace('/<!--.*?-->/s', '', $code);
		return preg_replace('/<!--(?!<!)[^\[>].*?-->/s', '', $code);
	}

	/**
	 * Clears all cached templates.
	 */
	public function clear_cache(): void {
		array_map('unlink', glob($this->cache_path . "*.php"));
	}
}
