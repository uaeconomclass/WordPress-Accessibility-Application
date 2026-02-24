<?php
/**
 * Unit test runner — pure PHP, no WordPress required.
 *
 * Run from anywhere:
 *   php tests/unit/run.php
 *
 * Bootstraps ABSPATH so the classes don't early-exit, then loads the
 * three pure-PHP classes under test and runs each test file in sequence.
 */

// Bootstrap: the classes call `if ( ! defined('ABSPATH') ) exit;`
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '/' );
}

require_once __DIR__ . '/../TestRunner.php';
require_once __DIR__ . '/../../classes/BricksElementFinder.php';
require_once __DIR__ . '/../../classes/BricksPatchValidator.php';
require_once __DIR__ . '/../../classes/BricksPatchApplier.php';

// Silence error_log output during tests (BricksElementFinder logs to error_log on fallback paths).
ini_set( 'error_log', '/dev/null' );

$t = new TestRunner();

echo "\033[1m╔══════════════════════════════════════════════════════════╗\033[0m\n";
echo "\033[1m║         Accessibility Auditor — Unit Tests               ║\033[0m\n";
echo "\033[1m╚══════════════════════════════════════════════════════════╝\033[0m\n";

require_once __DIR__ . '/ElementFinderTest.php';
require_once __DIR__ . '/PatchValidatorTest.php';
require_once __DIR__ . '/PatchApplierTest.php';

$all_passed = $t->summary();
exit( $all_passed ? 0 : 1 );
