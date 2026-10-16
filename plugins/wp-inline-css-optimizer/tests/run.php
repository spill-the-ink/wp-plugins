<?php
/**
 * Dependency-free test runner for the CSS Minifier.
 *
 * Usage: php tests/run.php
 *
 * These tests exercise only the Minifier, which has no WordPress dependency
 * when constructed with an explicit aggressiveness flag.
 *
 * @since 0.1.0
 */

define( 'ABSPATH', __DIR__ );

require_once __DIR__ . '/../includes/Optimizer/Minifier.php';

use WpInlineCssOptimizer\Optimizer\Minifier;

$failures = 0;
$passed   = 0;

function wico_assert( bool $condition, string $message ): void {
	global $failures, $passed;

	if ( $condition ) {
		$passed++;
		echo "  ok   {$message}\n";
		return;
	}

	$failures++;
	echo "  FAIL {$message}\n";
}

function wico_assert_equals( $expected, $actual, string $message ): void {
	global $failures, $passed;

	if ( $expected === $actual ) {
		$passed++;
		echo "  ok   {$message}\n";
		return;
	}

	$failures++;
	echo "  FAIL {$message}\n";
	echo "       expected: " . var_export( $expected, true ) . "\n";
	echo "       actual:   " . var_export( $actual, true ) . "\n";
}

$minifier = new Minifier( true );
$passive  = new Minifier( false );

echo "Minifier\n";

// 1. Collapses whitespace and strips indentation.
$input    = "  .a {\n    color: red;\n    margin: 0 ;\n  }\n";
$expected = ".a{color:red;margin:0;}";
wico_assert_equals( $expected, $minifier->minify( $input ), 'collapses whitespace and strips indentation' );

// 2. Idempotency.
$samples = array(
	$input,
	'.a { color: red; }',
	'@media (min-width: 600px) { .a { color: red } }',
	'a, b , c { color: #fff }',
	'.x::before { content: "hello : world"; }',
);
foreach ( $samples as $index => $sample ) {
	$once  = $minifier->minify( $sample );
	$twice = $minifier->minify( $once );
	wico_assert_equals( $once, $twice, "minify() is idempotent (sample {$index})" );
}

// 3. Licence/banner comments preserved byte-for-byte.
$banner = '/*! tailwindcss v4.3.3 | MIT License | https://tailwindcss.com */';
$out    = $minifier->minify( $banner . "\n.a { color: red; }" );
wico_assert( false !== strpos( $out, $banner ), 'preserves /*! licence banner' );

// 4. Regular comments removed.
$out = $minifier->minify( '.a { color: red; /* drop me */ }' );
wico_assert( false === strpos( $out, 'drop me' ), 'removes regular comments' );

// 5. calc() spacing preserved.
$out = $minifier->minify( '.a { width: calc(100% - 2rem); }' );
wico_assert( false !== strpos( $out, 'calc(100% - 2rem)' ), 'preserves calc() operator spacing' );

// 6. Quoted strings preserved (including inner spaces and colons).
$out = $minifier->minify( '.a { content: "a   b : c"; }' );
wico_assert( false !== strpos( $out, '"a   b : c"' ), 'preserves quoted string contents' );

// 7. url() preserved.
$out = $minifier->minify( '.a { background: url( "my image.png" ) no-repeat; }' );
wico_assert( false !== strpos( $out, 'url( "my image.png" )' ), 'preserves url() contents' );

// 8. sourceMappingURL comments stripped.
$out = $minifier->minify( ".a{color:red}\n/*# sourceMappingURL=app.css.map */" );
wico_assert( false === strpos( $out, 'sourceMappingURL' ), 'strips sourceMappingURL comments' );

// 9. Malformed CSS (unbalanced braces) is returned unchanged.
$broken = '.a { color: red;';
wico_assert_equals( $broken, $minifier->minify( $broken ), 'returns malformed CSS unchanged' );

// 10. UTF-8 content preserved.
$out = $minifier->minify( '.a { content: "→"; }' );
wico_assert( false !== strpos( $out, '"→"' ), 'preserves multibyte UTF-8 content' );

// 11. Reduction is positive for indented input.
wico_assert( strlen( $minifier->minify( $input ) ) < strlen( $input ), 'reduces byte size for indented input' );

// 12. Passive mode keeps single spaces but still strips comments.
$out = $passive->minify( "a {\n  color: red;\n}\n" );
wico_assert_equals( 'a { color: red; }', $out, 'passive mode collapses to single spaces' );

// 13. !important preserved.
$out = $minifier->minify( '.a { color: red !important; }' );
wico_assert( false !== strpos( $out, '!important' ), 'preserves !important' );

// 14. Empty input returned as-is.
wico_assert_equals( '', $minifier->minify( '' ), 'returns empty input unchanged' );

// ---------------------------------------------------------------------------
// Optimizer inline-value shape (regression: WordPress only prints array data).
// ---------------------------------------------------------------------------

require_once __DIR__ . '/../includes/Optimizer/Optimizer.php';

echo "\nOptimizer\n";

wico_assert_equals(
	array( '.a{color:red}' ),
	\WpInlineCssOptimizer\Optimizer\Optimizer::format_inline_value( array( '.a { color: red; }' ), '.a{color:red}' ),
	'array inline data is rewritten as an array (WP_Styles prints only arrays)'
);

wico_assert_equals(
	'.a{color:red}',
	\WpInlineCssOptimizer\Optimizer\Optimizer::format_inline_value( '.a { color: red; }', '.a{color:red}' ),
	'string inline data stays a string'
);

// ---------------------------------------------------------------------------
// Reporter (Developer mode) smoke test with minimal WordPress stubs.
// ---------------------------------------------------------------------------

if ( ! defined( 'WP_INLINE_CSS_OPTIMIZER_VERSION' ) ) {
	define( 'WP_INLINE_CSS_OPTIMIZER_VERSION', '0.1.0' );
}

function apply_filters( $tag, $value ) {
	return $value;
}
function wp_inline_css_optimizer_get_option( $key, $default = null ) {
	return 'debug' === $key ? 1 : $default;
}
function wp_inline_css_optimizer_get_options() {
	return array( 'debug' => 1 );
}
function is_user_logged_in() {
	return ! isset( $GLOBALS['wico_test_logged_in'] ) || (bool) $GLOBALS['wico_test_logged_in'];
}
function current_user_can( $cap ) {
	return ! isset( $GLOBALS['wico_test_can_manage'] ) || (bool) $GLOBALS['wico_test_can_manage'];
}
function is_admin() {
	return false;
}
function home_url( $path = '' ) {
	return 'https://example.test/blocks/';
}
function add_query_arg( $args ) {
	return $args;
}
function number_format_i18n( $number ) {
	return number_format( (float) $number );
}

require_once __DIR__ . '/../includes/Debug/Reporter.php';

// Analysis layer (required before Reporter::render(), which builds suggestions).
require_once __DIR__ . '/../includes/Analysis/Finding.php';
require_once __DIR__ . '/../includes/Analysis/Check.php';
require_once __DIR__ . '/../includes/Analysis/Context.php';
require_once __DIR__ . '/../includes/Analysis/Collector.php';
require_once __DIR__ . '/../includes/Analysis/Advisor.php';
require_once __DIR__ . '/../includes/Analysis/Checks/Resource_Hints_Check.php';
require_once __DIR__ . '/../includes/Analysis/Checks/Preconnect_Check.php';
require_once __DIR__ . '/../includes/Analysis/Checks/Font_Preload_Check.php';
require_once __DIR__ . '/../includes/Analysis/Checks/Inline_Bypass_Check.php';
require_once __DIR__ . '/../includes/Analysis/Checks/Script_Strategy_Check.php';
require_once __DIR__ . '/../includes/Analysis/Checks/Render_Blocking_Styles_Check.php';
require_once __DIR__ . '/../includes/Analysis/Checks/Emoji_Check.php';
require_once __DIR__ . '/../includes/Analysis/Checks/Minification_Check.php';

echo "\nReporter\n";

$reporter = new \WpInlineCssOptimizer\Debug\Reporter();
$reporter->mark_run( true );
$reporter->set_scanned( 3 );
$reporter->record_handle( 'winden-compiled-css', 156887, 123221 );
$reporter->record_handle( 'evil--><style>x', 1000, 400 );
$reporter->record_cache( true );
$reporter->record_cache( false );
$reporter->record_buffer( 2, 5000, 3000 );
$reporter->record_file( 156887, 123221 );
$reporter->log( 'wp_print_styles@20: test event' );

ob_start();
$reporter->render();
$out = (string) ob_get_clean();

wico_assert( 0 === strpos( ltrim( $out ), '<!--' ), 'report is emitted as an HTML comment' );
wico_assert( false !== strpos( $out, 'Developer Mode' ), 'report has a header' );
wico_assert( false !== strpos( $out, 'winden-compiled-css' ), 'report lists minified handles' );
wico_assert( false !== strpos( $out, 'TOTAL' ), 'report has a total line' );
wico_assert( false !== strpos( $out, '123,221' ), 'report shows byte counts' );
wico_assert( false === strpos( $out, '--><style>' ), 'handle names cannot terminate the HTML comment' );
wico_assert( false !== strpos( $out, '-->' ), 'report closes the HTML comment' );
wico_assert( false !== strpos( $out, 'console.log(' ), 'report is also emitted to the browser console' );
wico_assert( false === strpos( $out, '</script><' ), 'console payload cannot break out of the script tag' );

// R1: developer mode must have no effect for non-admins (logged-out or unprivileged).
$GLOBALS['wico_test_can_manage'] = false;
$anon                            = new \WpInlineCssOptimizer\Debug\Reporter();
wico_assert( ! $anon->is_visible(), 'non-admin: developer mode is not visible' );
wico_assert( ! $anon->is_enabled(), 'non-admin: HTML report is disabled' );
wico_assert( ! $anon->is_logging(), 'non-admin: debug logging is disabled' );
wico_assert( ! $anon->is_active(), 'non-admin: developer mode is entirely inactive' );
$anon->mark_run( true );
$anon->record_handle( 'winden-compiled-css', 156000, 123000 );
$anon->log( 'should not be recorded' );
ob_start();
$anon->render();
$anon_out = (string) ob_get_clean();
wico_assert_equals( '', $anon_out, 'non-admin: no report is emitted' );

$GLOBALS['wico_test_logged_in'] = false;
$GLOBALS['wico_test_can_manage'] = true;
$guest                           = new \WpInlineCssOptimizer\Debug\Reporter();
wico_assert( ! $guest->is_active(), 'logged-out guest: developer mode is entirely inactive' );

unset( $GLOBALS['wico_test_logged_in'], $GLOBALS['wico_test_can_manage'] );

// ---------------------------------------------------------------------------
// Analysis layer (Developer mode suggestions).
// ---------------------------------------------------------------------------

use WpInlineCssOptimizer\Analysis\Advisor;
use WpInlineCssOptimizer\Analysis\Checks;
use WpInlineCssOptimizer\Analysis\Collector;
use WpInlineCssOptimizer\Analysis\Context;
use WpInlineCssOptimizer\Analysis\Finding;

echo "\nAnalysis\n";

wico_assert(
	Finding::rank( Finding::OPPORTUNITY ) < Finding::rank( Finding::NOTICE )
		&& Finding::rank( Finding::NOTICE ) < Finding::rank( Finding::INFO ),
	'finding severities rank opportunity < notice < info'
);
wico_assert_equals( Finding::INFO, ( new Finding( 'x', 'bogus', 't' ) )->severity, 'unknown severity falls back to info' );

$hinted_context = new Context(
	array(),
	array(),
	array(
		'preconnect'   => array( 'https://cdn.example.com' ),
		'dns-prefetch' => array( '//cdn.example.com' ),
	),
	0,
	array(),
	array(),
	array(),
	array(),
	'example.test'
);
wico_assert( count( ( new Checks\Preconnect_Check() )->run( $hinted_context ) ) === 0, 'preconnect check is quiet when host is already hinted' );

$unhinted_context = new Context(
	array( array( 'handle' => 'foo', 'external' => true, 'host' => 'cdn.other.com', 'inline_bytes' => 0, 'processed' => true ) ),
	array( array( 'handle' => 'bar', 'external' => true, 'host' => 'cdn.other.com', 'in_footer' => false, 'strategy' => '' ) ),
	array(),
	0,
	array(),
	array(),
	array(),
	array(),
	'example.test'
);
$preconnect = ( new Checks\Preconnect_Check() )->run( $unhinted_context );
wico_assert( count( $preconnect ) === 1, 'preconnect check flags an unhinted external host' );
wico_assert( false !== strpos( $preconnect[0]->title, 'cdn.other.com' ), 'preconnect finding names the host' );

$font_context = new Context( array(), array(), array(), 2, array( 'https://example.test/a.woff2' ), array(), array(), array(), 'example.test' );
wico_assert( count( ( new Checks\Font_Preload_Check() )->run( $font_context ) ) === 1, 'font preload check suggests preloading self-hosted fonts' );

$preloaded_context = new Context( array(), array(), array( 'preload' => array( array( 'as' => 'font', 'href' => '/a.woff2' ) ) ), 2, array( '/a.woff2' ), array(), array(), array(), 'example.test' );
wico_assert( count( ( new Checks\Font_Preload_Check() )->run( $preloaded_context ) ) === 0, 'font preload check is quiet when a font preload exists' );

$bypass_context = new Context( array(), array(), array(), 0, array(), array(), array(), array( array( 'handle' => 'global-styles-inline-css', 'bytes' => 16500 ) ), 'example.test' );
$bypass = ( new Checks\Inline_Bypass_Check() )->run( $bypass_context );
wico_assert( count( $bypass ) === 1, 'inline bypass check flags large unprocessed inline CSS' );
wico_assert( false !== strpos( $bypass[0]->title, 'global-styles-inline-css' ), 'bypass finding names the handle' );

$head_script_context = new Context( array(), array( array( 'handle' => 'foo', 'external' => true, 'host' => 'example.test', 'in_footer' => false, 'strategy' => '' ) ), array(), 0, array(), array(), array(), array(), 'example.test' );
wico_assert( count( ( new Checks\Script_Strategy_Check() )->run( $head_script_context ) ) === 1, 'script strategy check flags head scripts without defer/async' );
$footer_script_context = new Context( array(), array( array( 'handle' => 'foo', 'external' => true, 'host' => 'example.test', 'in_footer' => true, 'strategy' => '' ) ), array(), 0, array(), array(), array(), array(), 'example.test' );
wico_assert( count( ( new Checks\Script_Strategy_Check() )->run( $footer_script_context ) ) === 0, 'script strategy check ignores footer scripts' );

$many_styles = array();
for ( $i = 0; $i < 5; $i++ ) {
	$many_styles[] = array( 'handle' => "s{$i}", 'external' => true, 'host' => 'example.test', 'inline_bytes' => 0, 'processed' => true );
}
wico_assert( count( ( new Checks\Render_Blocking_Styles_Check() )->run( new Context( $many_styles, array() ) ) ) === 1, 'render-blocking check notices 5 external stylesheets' );
wico_assert( count( ( new Checks\Render_Blocking_Styles_Check() )->run( new Context( array(), array() ) ) ) === 0, 'render-blocking check is quiet with no external stylesheets' );

$emoji_context = new Context( array( array( 'handle' => 'wp-emoji-styles', 'external' => false, 'host' => '', 'inline_bytes' => 500, 'processed' => true ) ) );
wico_assert( count( ( new Checks\Emoji_Check() )->run( $emoji_context ) ) === 1, 'emoji check flags emoji assets' );

$minify_context = new Context( array(), array(), array(), 0, array(), array(), array( 'bytes_in' => 1000, 'bytes_out' => 700, 'handles' => 2 ) );
$minified_findings = ( new Checks\Minification_Check() )->run( $minify_context );
wico_assert( count( $minified_findings ) === 1, 'minification check summarizes saved bytes' );
wico_assert( false !== strpos( $minified_findings[0]->title, '30.0%' ), 'minification check reports the percentage saved' );

$formatted = Advisor::format( array( new Finding( 'a', Finding::NOTICE, 'Title', 'Detail', 'Do it' ) ) );
wico_assert_equals( array( '[notice] Title', '    Detail', '    -> Do it' ), $formatted, 'format() renders severity, detail and suggestion lines' );

$fixture = new Context(
	array(),
	array(),
	array(),
	0,
	array(),
	array(),
	array( 'bytes_in' => 1000, 'bytes_out' => 500, 'handles' => 1 ),
	array(),
	'example.test'
);
$fake_collector = new class( $fixture ) extends Collector {
	private Context $fixture;

	public function __construct( Context $fixture ) {
		$this->fixture = $fixture;
	}

	public function collect( array $processed_handles = array(), array $totals = array() ): Context {
		return $this->fixture;
	}
};
$advisor    = new Advisor( $fake_collector );
$findings   = $advisor->analyze();
$severities = array_map( static fn( Finding $f ) => Finding::rank( $f->severity ), $findings );
wico_assert( $severities === ( function ( array $s ) { sort( $s ); return $s; } )( $severities ), 'analyze() returns findings sorted by severity' );
wico_assert( count( $findings ) >= 1, 'analyze() runs the default check set' );

echo "\n{$passed} passed, {$failures} failed\n";

exit( $failures > 0 ? 1 : 0 );
