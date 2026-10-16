=== WordPress Inline CSS Optimizer ===
Contributors: spilltheink
Tags: inline css, minify, performance, tailwind, winden
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Minifies inline CSS in the document head — including Tailwind/Winden compiled output — with transient caching and no external dependencies.

== Description ==

Many themes and plugins print large blocks of unminified CSS into the
document head. WordPress Inline CSS Optimizer minifies that CSS just before
it is printed, cutting bytes with no runtime cost on repeat requests.

The plugin intercepts inline CSS registered through the WordPress enqueue API
(anything added with `wp_add_inline_style()`), which includes the compiled
Tailwind output produced by the Winden plugin. It does not require Winden and
works with any theme or plugin that outputs inline styles the WordPress way.

Minification is conservative: licence banner comments (`/*! ... */`), quoted
strings, `url()` values and `calc()` expressions are always preserved.

= Features =

* Minifies inline CSS registered against `WP_Styles` handles.
* Transient cache keyed on the CSS content and plugin version.
* Optional explicit Winden integration, including save-time minification of
  `uploads/winden/output.css`.
* Optional head output-buffer fallback for raw `<style>` tags (WordPress 6.5+).
* Include/exclude lists and a minimum-size threshold.
* Developer mode: prints a per-handle before/after report as an HTML comment
  at the end of the page (visible to logged-in administrators, view source).
* Developer mode can also log every step (plus any fatal error and the final
  report) to the WordPress debug log for full-picture debugging.
* Developer mode suggestions: a read-only advisory list of low-risk
  opportunities (missing resource hints, un-preloaded self-hosted fonts,
  render-blocking styles/scripts, emoji assets, and inline CSS that bypassed
  the optimizer) added to the report. Nothing on the page is changed.
* Extensible through `wp_inline_css_optimizer_*` filters and actions.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wp-inline-css-optimizer`
   directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the Plugins screen.
3. Review the settings under Settings → Inline CSS.

== Frequently Asked Questions ==

= Does this require Winden? =

No. The Winden integration is optional and completely inert when Winden is not
active. All other inline CSS is still optimized.

= Will this break my JavaScript? =

No. Only inline `<style>` CSS is touched; `<script>` blocks and other markup
are never modified.

= Can it conflict with my caching plugin? =

Minification happens before the page is buffered by caching plugins. If they
further process CSS, they simply operate on already-minified output. The
optional head output-buffer fallback is off by default for this reason.

== Changelog ==

= 0.1.0 =
* Initial release.
