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

declare(strict_types=1);

namespace LiteView;


/**
 * LiteView — Lightweight PHP template engine.
 *
 * LiteView is a minimal, single-file template compiler designed for high performance
 * and low overhead. It provides template inheritance, includes, variable output,
 * and basic control structures using a compact syntax.
 *
 * ## Key Features
 * - Zero dependencies: self-contained, PSR-4 compatible.
 * - Template inheritance: `{% extends "layout.html" %}`, `{% block name %}`, `{% yield name %}`.
 * - Includes: `{% include "snippet.html" %}` with recursion depth protection.
 * - Variable output:
 *   - Escaped: `{{ variable }}`
 *   - Raw: `{{{ variable }}}`
 *   - PHP expression: `{?= expr ?}`
 * - Control structures: `{% if %}`, `{% else %}`, `{% endif %}`, `{% foreach (...) %}`, `{% endforeach %}`.
 * - Comment removal:
 *   - Template comments `{# ... #}`
 *   - HTML comments `<!-- ... -->` (optional, safe for conditional comments).
 * - Whitespace trimming: optional, skips sensitive tags (<pre>, <code>, <textarea>, <script>, <style>).
 * - Caching:
 *   - Compiles templates to PHP files in a cache directory.
 *   - Dependency-aware cache invalidation (extends + includes).
 *   - Cache files guarded against direct web execution.
 *
 * ## Security Notes
 * - Templates are considered **trusted code**.
 *   - `{? code ?}` executes raw PHP. Disable in production by setting `$allowPhpTags = false`.
 *   - Variable output is HTML-escaped by default (`{{ }}`).
 * - Always place `cachePath` **outside webroot**, or restrict access (e.g., with `.htaccess`).
 *
 * ## Typical Usage
 * ```php
 * use LiteView\LiteView;
 *
 * // Echo output directly
 * LiteView::render(
 *     'home.html',
 *     __DIR__ . '/../templates',
 *     true,
 *     __DIR__ . '/../var/cache/liteview',
 *     true,
 *     true,
 *     ['title' => 'Hello World']
 * );
 *
 * // Capture output as string
 * $html = LiteView::renderToString(
 *     'page.html',
 *     __DIR__ . '/../templates',
 *     true,
 *     __DIR__ . '/../var/cache/liteview',
 *     false,
 *     false,
 *     ['user' => $user],
 *     false // disallow raw PHP blocks
 * );
 * ```
 *
 * ## API
 * - {@see LiteView::render()}         Echo template directly.
 * - {@see LiteView::renderToString()} Render and return as string.
 * - {@see LiteView::clearCache()}     Delete all compiled templates in cache.
 *
 * ## Limitations
 * - Not sandboxed: do not expose to untrusted template authors.
 * - Feature scope is deliberately minimal (no filters/tests/extensions).
 *   Intended for lightweight apps, not as a Twig replacement.
 *
 */
class LiteView {

	/** @var string Absolute path to the templates directory (with trailing slash) */
	private static string $templatePath;

	/** @var string Absolute path to the cache directory (with trailing slash) */
	private static string $cachePath;

	/** @var bool Whether caching is enabled */
	private static bool $cacheEnabled;

	/** @var bool Whether to trim unnecessary whitespace (safe mode) */
	private static bool $trimWhitespace;

	/** @var bool Whether to remove standard HTML comments (not conditional) */
	private static bool $removeHtmlComments;

	/** @var bool Whether to allow raw PHP execution via {? ... ?} tags */
	private static bool $allowPhpTags;

	/** Maximum include recursion depth (prevents circular includes). */
	private const MAX_INCLUDE_DEPTH = 16;



	/**
	 * Render a template directly (echo to output buffer).
	 *
	 * @param string $template Template relative path (e.g., 'home.html')
	 * @param string $templatePath Base dir for templates
	 * @param bool $cacheEnabled Enable compiled cache
	 * @param string $cachePath Base dir for compiled templates
	 * @param bool $trimWhitespace Collapse whitespace outside sensitive tags
	 * @param bool $removeHtmlComments Strip <!-- ... --> (not conditional)
	 * @param array $data Variables exposed to template
	 * @param bool $allowPhpTags Allow `{? ... ?}` execution blocks
	 */
	public static function render(string $template, string $templatePath, bool $cacheEnabled, string $cachePath, bool $trimWhitespace = false, bool $removeHtmlComments = false, array $data = [], bool $allowPhpTags = true): void {

		// Configure engine
		self::$templatePath = rtrim($templatePath, '/\\') . DIRECTORY_SEPARATOR;
		self::$cacheEnabled = $cacheEnabled;
		self::$cachePath = rtrim($cachePath, '/\\') . DIRECTORY_SEPARATOR;
		self::$trimWhitespace = $trimWhitespace;
		self::$removeHtmlComments = $removeHtmlComments;
		self::$allowPhpTags = $allowPhpTags;

		// Compile (if needed) and include
		$compiledFile = self::compile($template);

		// Extract variables
		extract($data, EXTR_SKIP);

		// Execute compiled template
		require $compiledFile;
	}


	/**
	 * Render a template and return the output as a string.
	 *
	 * @param string $template
	 * @param string $templatePath
	 * @param bool $cacheEnabled
	 * @param string $cachePath
	 * @param bool $trimWhitespace
	 * @param bool $removeHtmlComments
	 * @param array $data
	 * @param bool $allowPhpTags
	 * @return string
	 */
	public static function renderToString(string $template, string $templatePath, bool $cacheEnabled, string $cachePath, bool $trimWhitespace = false, bool $removeHtmlComments = false, array $data = [], bool $allowPhpTags = true): string {
		ob_start();
		
		try {
			self::render($template, $templatePath, $cacheEnabled, $cachePath, $trimWhitespace, $removeHtmlComments, $data, $allowPhpTags);
			return (string)ob_get_contents();
		
		} finally {
			// NOTE: Even though there's a "return" above, PHP will always execute "finally"
			// first (cleanup), then return the prepared value afterwards. This guarantees
			// ob_end_clean() runs before the function actually returns.
			ob_end_clean();
		}
	}


	/**
	 * Compile a template into a cached PHP file (dependency-aware).
	 *
	 * Cache invalidation strategy:
	 * - Uses file modification times (mtime) for the source template and all discovered
	 *   dependencies (extends + includes). If none are newer than the compiled cache,
	 *   the cached file is reused.
	 * Rationale:
	 * - mtime checks are O(1) and very fast; ideal for lightweight runtime environments.
	 * - In typical deployments (files overwritten or newly written), mtime reliably reflects changes.
	 * Caveats:
	 * - If deploy tooling preserves timestamps (e.g., cp -p/rsync --times), cache may not refresh.
	 *   Ensure deploys update mtimes or add a manual invalidation step.
	 *
	 * @param string $template Relative template path
	 * @return string Absolute path to compiled PHP file
	 * @throws \RuntimeException
	 */
	private static function compile(string $template): string {
		
		// Resolve source path
		$sourcePath = self::resolveTemplate($template);
		
		// Get the name of the cachefile
		$cacheFile = self::cacheFileName($template);

		// Load the raw source template from disk
		$code = file_get_contents($sourcePath);

		// Strip {# ... #} before scanning so commented {% include %}/{% extends %} don’t count as deps.
		$code = self::removeTemplateComments($code);

		// If cache is enabled and a compiled file already exists, we run a
		// dependency-aware freshness check (mtime-based).
		// We compute the most recent mtime across the source template and all discovered
		// dependencies (extends + includes). If the cache file is newer than that max mtime,
		// we can safely reuse it without recompiling.
		if (self::$cacheEnabled && is_file($cacheFile)) {
			// Collect all dependencies (parent layouts + included snippets) from stripped code
			$deps = self::collectDependencies($code);

			// Compute the freshest mtime across source + dependencies
			$maxMtime = filemtime($sourcePath);
			foreach ($deps as $rel) {
				$depPath = self::resolveTemplate($rel);
				$mt = filemtime($depPath);
				if ($mt > $maxMtime) $maxMtime = $mt;
			}

			// If cache is newer than everything we depend on; we reuse it
			if ($maxMtime <= filemtime($cacheFile)) {
				return $cacheFile;
			}
		}

		// Build compiled code (we already have $code loaded + comments stripped)
		$code = self::processExtends($code);   // Resolve inheritance: merges child blocks into parent yields
		$code = self::processIncludes($code);  // Inline {% include %} recursively (with depth guard)
		$code = self::compileSyntax($code);    // Compile (translate template tags to PHP)

		if (self::$removeHtmlComments) {
			$code = self::removeHtmlComments($code);
		}
		if (self::$trimWhitespace) {
			$code = self::safeTrimWhitespace($code);
		}

		// Prepend a tiny guard and write compiled file
		$compiled = "<?php class_exists('". __CLASS__ ."') or exit; ?>\n" . rtrim($code);

		// Generate a unique temporary filename inside the cache directory
		$tmp = self::$cachePath . uniqid('lv_', true) . '.tmp';

		// Write compiled content to a temporary file with an exclusive lock.
		// If cache dir is missing or not writable, this will return false.
		if (file_put_contents($tmp, $compiled, LOCK_EX) === false) {
			throw new \RuntimeException('LiteView: Cache directory is missing or not writable: ' . self::$cachePath);
		}

		// Set file permissions (optional, ensures consistent readability)
		@chmod($tmp, 0644);

		// Atomically rename the temporary file to the final cache filename
		// This guarantees that the cache file is always either old or new,
		// never a half-written intermediate state
		if (!@rename($tmp, $cacheFile)) {
			@unlink($cacheFile); // Windows may require removing the destination first
			if (!@rename($tmp, $cacheFile)) { // On Windows; rename may fail if target exists, so we unlink and retry.
				@unlink($tmp); // Cleanup temp if rename failed
				throw new \RuntimeException('LiteView: failed to move compiled cache file.');
			}
		}
		
		// If OPcache is enabled; proactively refresh it for this file
		if (function_exists('opcache_invalidate')) {
			@opcache_invalidate($cacheFile, true);
		}

		// Refresh PHP's stat cache for subsequent filemtime/exists checks
		clearstatcache(true, $cacheFile);

		return $cacheFile;
	}


	/**
	 * Merge parent/child with `{% extends "layout.html" %}` (strict mode).
	 *
	 * Strict guarantees:
	 * - Throws if the child defines a `{% block %}` that is not yielded in the parent.
	 * - Throws if the parent contains any `{% yield %}` that the child did not provide.
	 * - Throws if the child defines the same block name more than once.
	 *
	 * Implementation notes:
	 * - Operates at compile-time: child blocks are spliced into the parent where `{% yield %}` appears.
	 * - After this merge the block/yield markers are removed (compile-time only; no runtime block stack).
	 *
	 * @param string $code Child template code (already comment-stripped upstream)
	 * @return string Merged code (parent with yields replaced)
	 * @throws \RuntimeException On missing yields, missing child blocks for yields, or duplicate blocks.
	 */
	private static function processExtends(string $code): string {
		// Fast path: no extends → return child code unchanged
		if (strpos($code, '{% extends') === false) {
			return $code;
		}
		// Extract single parent path; if malformed, skip
		if (!preg_match('/{%\s*extends\s+["\'](.*?)["\']\s*%}/', $code, $m)) {
			return $code;
		}

		// Load and sanitize parent (layout)
		$layoutFile = self::resolveTemplate($m[1]);
		$layoutCode = file_get_contents($layoutFile);
		$layoutCode = self::removeTemplateComments($layoutCode);

		// Find all child blocks: {% block name %} ... {% endblock %}
		preg_match_all('/{%\s*block\s+([\w-]+)\s*%}(.*?){%\s*endblock\s*%}/s', $code, $matches, PREG_SET_ORDER);

		// Track seen block names to detect duplicates early (fail fast)
		$seen = [];

		// Replace each yielded slot in the parent with the corresponding child block content
		foreach ($matches as $match) {
			$name    = $match[1];              // Block name (e.g. "content")
			$content = trim($match[2]);        // Block content without surrounding whitespace
			$quoted  = preg_quote($name, '/'); // Escape block name for safe regex usage

			if (isset($seen[$name])) {
				// Duplicate child block definitions are ambiguous → fail fast
				throw new \RuntimeException("LiteView: duplicate block '$name' in child template.");
			}
			$seen[$name] = true;

			// Replace all {% yield name %} occurrences in the parent
			$replaced = preg_replace('/{%\s*yield\s*' . $quoted . '\s*%}/', $content, $layoutCode, -1, $count);

			// preg_replace() may return null on internal regex error (rare) → fail fast
			if ($replaced === null) {
				throw new \RuntimeException("LiteView: regex replace failed for block '$name'.");
			}
			$layoutCode = $replaced;

			// Strict: child provided a block that the parent never yielded → fail fast
			if ($count === 0) {
				throw new \RuntimeException("LiteView: block '$name' not yielded in parent template: " . $layoutFile);
			}
		}

		// Strict: any remaining {% yield name %} in the parent means the child missed a required block
		if (preg_match_all('/{%\s*yield\s*([\w-]+)\s*%}/', $layoutCode, $leftover) && !empty($leftover[1])) {
			$missing = array_values(array_unique($leftover[1]));
			$list    = "'" . implode("', '", $missing) . "'";
			throw new \RuntimeException("LiteView: missing child blocks for yields {$list} in parent template: " . $layoutFile);
		}

		return $layoutCode;
	}


	/**
	 * Process `{% include "..." %}` recursively (with depth guard).
	 *
	 * @param string $code
	 * @param int $depth
	 * @return string
	 */
	private static function processIncludes(string $code, int $depth = 0): string {
		if (strpos($code, '{% include') === false) {
			return $code;
		}
		if ($depth >= self::MAX_INCLUDE_DEPTH) {
			throw new \RuntimeException('LiteView: include depth exceeded.');
		}
		return preg_replace_callback('/{%\s*include\s+["\'](.*?)["\']\s*%}/i', function(array $m) use ($depth) {
			$incPath = self::resolveTemplate($m[1]);
			$incCode = file_get_contents($incPath);
			$incCode = self::removeTemplateComments($incCode);
			$incCode = self::processIncludes($incCode, $depth + 1);
			return $incCode;
		}, $code);
	}


	/**
	 * Compile template tags to PHP.
	 *
	 * Supported:
	 * - {?= expr ?}      echo expression
	 * - {? code ?}       raw PHP code (if allowed)
	 * - {{ expr }}       escaped echo
	 * - {{{ expr }}}     raw echo (unescaped)
	 * - {% if/elseif/else/endif %}, {% foreach(... ) %}/{% endforeach %},
	 * // - {% block name %} ... {% endblock %}, {% yield name %}
	 *
	 * NOTE: Block/Yield syntax stripped to no-op after processExtends().
	 * Old code was:
	 * '/{%\s*block\s+([\w-]+)\s*%}/'     => '<?php ob_start(); ?>',
	 * '/{%\s*endblock\s*%}/'             => '<?php self::$blocks[isset($block_name)?$block_name:array_key_last(self::$blocks)] = isset($block_name)?ob_get_clean():ob_get_clean(); ?>',
	 * '/{%\s*yield\s*([\w-]+)\s*%}/'     => '<?php echo self::$blocks["$1"] ?? ""; ?>',
	 * If you ever want to restore old behavior: Remember to add "private static array $blocks = [];" at the beginning of the class and "self::$blocks = [];" in the beginning of render()
	 *
	 * @param string $code
	 * @return string
	 */
	private static function compileSyntax(string $code): string {
		$patterns = [
			
			// Echo expression
			'/{\?=\s*(.+?)\s*\?}/s' => '<?php echo $1; ?>',
			
			// Raw PHP block (optional)
			'/{\?(.+?)\?}/s'        => self::$allowPhpTags ? '<?php $1 ?>' : ' ',

			// Blocks/yields (runtime block store)
			// NOTE: Block/ yield markers are resolved at compile-time in processExtends().
			// Thus, at this stage they are redundant, so we strip them to avoid double behavior.
			// This keeps runtime fast, predictable, and free of ob_start/ob_get_clean overhead.
			// In short: blocks already merged -> so markers removed (no runtime block system).
			'/{%\s*block\s+([\w-]+)\s*%}/'     => '',
			'/{%\s*endblock\s*%}/'             => '',
			'/{%\s*yield\s*([\w-]+)\s*%}/'     => '',

			// Escaped vs raw echo
			'/\{\{\{\s*(.+?)\s*\}\}\}/s'       => '<?php echo $1; ?>',
			'/\{\{\s*(.+?)\s*\}\}/s'           => '<?php echo htmlspecialchars($1 ?? "", ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>',

			// Conditionals
			'/{%\s*if\s*(.+?)\s*%}/'           => '<?php if ($1): ?>',
			'/{%\s*elseif\s*(.+?)\s*%}/'       => '<?php elseif ($1): ?>',
			'/{%\s*else\s*%}/'                 => '<?php else: ?>',
			'/{%\s*endif\s*%}/'                => '<?php endif; ?>',

			// Loops
			'/{%\s*foreach\s*\((.+?)\)\s*%}/'  => '<?php foreach ($1): ?>',
			'/{%\s*endforeach\s*%}/'           => '<?php endforeach; ?>',
		];

		$compiled = preg_replace(array_keys($patterns), array_values($patterns), $code);

		return $compiled;
	}


	/**
	 * Remove `{# ... #}` template comments using a single-pass depth parser (supports nesting).
	 *
	 * Implementation details:
	 * - Uses a single-pass parser (O(n)) with a depth counter, not regex.
	 * - Supports nested comments: each `{#` increments depth, each `#}` decrements it.
	 * - If a comment is left open (while depth > 0), everything after its start is discarded (fail-safe).
	 * - This guarantees that nested comment blocks are fully removed,
	 *   leaving no stray `#}` or partial fragments in the output.
	 *
	 * Example:
	 *   {# outer
	 *      {# inner #}
	 *   #}
	 *   -> (removed completely, yields empty string)
	 *
	 * @param string $code (template code containing `{# ... #}` comments)
	 * @return string (cleaned template code with all comments removed)
	 */
	private static function removeTemplateComments(string $code): string {
		// Fast path: no comment opener, nothing to do
		if (strpos($code, '{#') === false) {
			return $code;
		}

		$len = strlen($code);   // Input length in bytes
		$depth = 0;             // Current nesting depth of `{# ... #}` blocks
		$start = 0;             // Last copy position (outside comments)
		$out = '';              // Output buffer

		// Scan the string once, matching `{#` and `#}` pairs
		for ($i = 0; $i < $len; $i++) {
			// Detect comment start `{#}`
			if ($i < $len - 1 && $code[$i] === '{' && $code[$i + 1] === '#') {
				// Append non-comment segment before this opener (only at depth 0)
				if ($depth === 0) {
					$out .= substr($code, $start, $i - $start);
				}
				$depth++;   // Enter (or go deeper into) a comment block
				$i++;       // Skip the '#' in `{#`
				continue;
			}

			// Detect comment end `#}`
			if ($i < $len - 1 && $code[$i] === '#' && $code[$i + 1] === '}' && $depth > 0) {
				$depth--;              // Leave (or go up one level) of comment block
				$i++;                  // Skip the '}' in `#}`
				$start = $i + 1;       // Next non-comment text starts after this closer
				continue;
			}
		}

		// If we ended outside comments, append any trailing non-comment segment
		if ($depth === 0 && $start < $len) {
			$out .= substr($code, $start);
		}

		return $out;
	}


	/**
	 * Remove standard HTML comments while keeping conditional comments.
	 *
	 * @param string $code
	 * @return string
	 */
	private static function removeHtmlComments(string $code): string {
		return preg_replace('/<!--(?!<!)[^\[>].*?-->/s', '', $code);
	}


	/**
	 * Collapse redundant whitespace in HTML output while preserving sensitive tags.
	 *
	 * Sensitive tags are <pre>, <code>, <textarea>, <script>, and <style>.
	 * Inside those, whitespace must be preserved exactly.
	 *
	 * @param string $html (the compiled template HTML)
	 * @return string (optimized HTML with collapsed whitespace outside sensitive tags)
	 */
	private static function safeTrimWhitespace(string $html): string {
	
		// Split HTML into chunks: alternating between "safe zones" (outside) and "sensitive zones" (inside)
		$parts = preg_split(
			'#(<(?:pre|code|textarea|script|style)\b[^>]*>.*?</(?:pre|code|textarea|script|style)>)#si',
			$html,
			-1,
			PREG_SPLIT_DELIM_CAPTURE
		);

		// preg_split() can return false on failure → fall back to original HTML
		if ($parts === false) {
			return $html;
		}

		// Iterate through chunks
		foreach ($parts as $i => $chunk) {
			// Even indexes = outside sensitive tags → safe to collapse
			if (($i % 2) === 0) {
				// Replace runs of 2+ whitespace chars with a single space
				// This avoids breaking inline markup while reducing size
				$parts[$i] = preg_replace('/\s{2,}/', ' ', $chunk);
			}
			// Odd indexes = inside sensitive tags → leave untouched
		}

		// Recombine and return cleaned HTML
		return implode('', $parts);
	}


	/**
	 * Collect all template dependencies (extends + recursive includes).
	 *
	 * Overview:
	 * - Parses the given (already comment-stripped) template code and finds:
	 *   - A single `{% extends "..." %}` parent (if present)
	 *   - Zero or more `{% include "..." %}` snippets
	 * - For each discovered dependency, the file is loaded, template comments are stripped,
	 *   and the function recurses to discover *its* dependencies.
	 *
	 * Cycle safety:
	 * - Uses a visited-set (by reference) to prevent infinite recursion on circular graphs,
	 *   e.g. A → B and B → A. Each relative path is processed at most once.
	 *
	 * Freshness checks:
	 * - This function only *collects* relative dependency paths. The caller (compile())
	 *   is responsible for comparing their mtimes, not this function.
	 *
	 * Input contract:
	 * - `$code` is expected to be the *comment-stripped* source of the current template
	 *   (so commented-out `{% include %}`/`{% extends %}` do not count).
	 *
	 * @param string $code     Template code to scan (ideally already comment-stripped)
	 * @param array<string, true> $visited Internal visited-set keyed by relative path (do not pass manually)
	 * @return array<int, string> Ordered, de-duplicated list of relative dependency paths
	 *
	 * @throws \RuntimeException If a resolved dependency path is outside the template root
	 *                           (bubbled up from resolveTemplate()).
	 */
	private static function collectDependencies(string $code, array &$visited = []): array {
		$deps = [];

		// --- Handle `{% extends "..." %}` (at most one by design) ------------------
		// We only look for the *first* extends directive; additional ones (if any)
		// would be ignored, mirroring typical template engines.
		if (preg_match('/{%\s*extends\s+["\'](.*?)["\']\s*%}/', $code, $m)) {
			$parentRel = $m[1];

			// Process the parent only once (cycle-safe)
			if (!isset($visited[$parentRel])) {
				$visited[$parentRel] = true;
				$deps[] = $parentRel;

				// Resolve absolute path under template root and load the file
				$parentAbs  = self::resolveTemplate($parentRel);
				$parentCode = file_get_contents($parentAbs);

				// Strip template comments so commented includes/extends aren't counted downstream
				$parentCode = self::removeTemplateComments($parentCode);

				// Recurse into the parent's dependencies
				$deps = array_merge($deps, self::collectDependencies($parentCode, $visited));
			}
		}

		// --- Handle `{% include "..." %}` (zero or more) ---------------------------
		if (preg_match_all('/{%\s*include\s+["\'](.*?)["\']\s*%}/i', $code, $matches)) {
			// $matches[1] is an array of all captured include paths
			foreach ($matches[1] as $incRel) {

				// Process this include only once (cycle-safe)
				if (isset($visited[$incRel])) {
					continue;
				}
				$visited[$incRel] = true;
				$deps[] = $incRel;

				// Resolve absolute path and load, then strip comments
				$incAbs  = self::resolveTemplate($incRel);
				$incCode = file_get_contents($incAbs);
				$incCode = self::removeTemplateComments($incCode);

				// Recurse into nested includes / further extends
				$deps = array_merge($deps, self::collectDependencies($incCode, $visited));
			}
		}

		// At this point `$deps` is already de-duplicated thanks to `$visited`.
		// Preserve discovery order for deterministic mtime comparisons.
		return $deps;
	}


	/**
	 * Resolve a template path safely relative to the configured template root.
	 *
	 * @param string $relative
	 * @return string Absolute, normalized path under template root
	 * @throws \RuntimeException
	 */
	private static function resolveTemplate(string $relative): string {
		$base = self::$templatePath;
		$root = realpath($base);
		if ($root === false) {
			throw new \RuntimeException('LiteView: invalid template root.');
		}

		$target = realpath($base . $relative);
		$rootWithSep = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

		// Require that the resolved path starts with "<root>/" to avoid
		// prefix false-positives like "/path/templatesX" matching "/path/templates".
		if ($target === false || strpos($target, $rootWithSep) !== 0) {
			throw new \RuntimeException('LiteView: illegal template path: ' . $relative);
		}
		return $target;
	}


	/**
	 * Generate a stable cache filename for a template.
	 *
	 * @param string $relative
	 * @return string
	 */
	private static function cacheFileName(string $relative): string {
		$slug = preg_replace('/[^A-Za-z0-9_]+/', '_', strtr($relative, ['\\' => '/', '/' => '_']));
		$hash = substr(sha1($relative), 0, 8);
		return self::$cachePath . $slug . '_' . $hash . '.php';
	}


	/**
	 * Clear all compiled templates in cache directory (*.php).
	 * Requires cache directory to be dedicated to compiled templates.
	 */
	public static function clearCache(): void {
		$glob = glob(self::$cachePath . '*.php');
		$files = $glob ?: [];
		array_map('unlink', $files);
	}
}
