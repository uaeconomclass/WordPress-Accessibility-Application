<?php
/**
 * Minimal test runner with ANSI-coloured pass/fail output.
 *
 * Usage (no autoloader, no framework dependency):
 *   require_once __DIR__ . '/TestRunner.php';
 *   $t = new TestRunner();
 *   $t->suite('MyClass');
 *   $t->assert_equals('foo', $subject->method(), 'method returns foo');
 *   exit( $t->summary() ? 0 : 1 );
 */
class TestRunner {

    private int    $passed  = 0;
    private int    $failed  = 0;
    private string $suite   = '';
    private array  $failures = [];

    // -------------------------------------------------------------------------
    // Suite grouping
    // -------------------------------------------------------------------------

    public function suite( string $name ): void {
        $this->suite = $name;
        echo "\n" . $this->bold( "  {$name}" ) . "\n";
    }

    // -------------------------------------------------------------------------
    // Assertions
    // -------------------------------------------------------------------------

    public function assert_true( $value, string $desc ): void {
        $this->record( (bool) $value, $desc, 'expected true, got ' . var_export( $value, true ) );
    }

    public function assert_false( $value, string $desc ): void {
        $this->record( ! (bool) $value, $desc, 'expected false, got ' . var_export( $value, true ) );
    }

    public function assert_null( $value, string $desc ): void {
        $this->record( $value === null, $desc, 'expected null, got ' . var_export( $value, true ) );
    }

    public function assert_not_null( $value, string $desc ): void {
        $this->record( $value !== null, $desc, 'expected non-null' );
    }

    public function assert_equals( $expected, $actual, string $desc ): void {
        $ok = $expected === $actual;
        $hint = $ok ? '' : sprintf(
            "\n      expected: %s\n      actual:   %s",
            var_export( $expected, true ),
            var_export( $actual,   true )
        );
        $this->record( $ok, $desc, $hint );
    }

    public function assert_contains( string $needle, string $haystack, string $desc ): void {
        $ok = str_contains( $haystack, $needle );
        $hint = $ok ? '' : "needle '{$needle}' not found in: " . substr( $haystack, 0, 200 );
        $this->record( $ok, $desc, $hint );
    }

    public function assert_throws( callable $fn, string $class, string $desc ): void {
        try {
            $fn();
            $this->record( false, $desc, "expected {$class} to be thrown but no exception was raised" );
        } catch ( \Throwable $e ) {
            $this->record( $e instanceof $class, $desc, "expected {$class}, got " . get_class( $e ) );
        }
    }

    // -------------------------------------------------------------------------
    // Summary
    // -------------------------------------------------------------------------

    /**
     * Print totals. Returns true if all tests passed, false if any failed.
     */
    public function summary(): bool {
        $total = $this->passed + $this->failed;
        echo "\n" . str_repeat( '-', 60 ) . "\n";
        echo sprintf( "  %d/%d tests passed", $this->passed, $total );

        if ( $this->failed > 0 ) {
            echo "  " . $this->red( "({$this->failed} failed)" );
            echo "\n\n  Failures:\n";
            foreach ( $this->failures as $f ) {
                echo $this->red( "  ✖ " . $f['label'] ) . "\n";
                if ( $f['hint'] ) {
                    echo "    " . $f['hint'] . "\n";
                }
            }
        } else {
            echo "  " . $this->green( '(all passed)' );
        }

        echo "\n" . str_repeat( '-', 60 ) . "\n";
        return $this->failed === 0;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function record( bool $ok, string $desc, string $hint = '' ): void {
        $label = $this->suite ? "{$this->suite}: {$desc}" : $desc;
        if ( $ok ) {
            $this->passed++;
            echo $this->green( "  ✔ " ) . $desc . "\n";
        } else {
            $this->failed++;
            $this->failures[] = [ 'label' => $label, 'hint' => trim( $hint ) ];
            echo $this->red( "  ✖ " ) . $desc;
            if ( $hint ) {
                echo " — " . $hint;
            }
            echo "\n";
        }
    }

    private function green( string $s ): string {
        return "\033[32m{$s}\033[0m";
    }

    private function red( string $s ): string {
        return "\033[31m{$s}\033[0m";
    }

    private function bold( string $s ): string {
        return "\033[1m{$s}\033[0m";
    }
}
