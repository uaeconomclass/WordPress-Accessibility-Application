<?php
/**
 * Plugin Name: Accessibility Auditor
 * Description: Run accessibility scans inside Bricks editor preview using axe-core. (Development build)
 * Version: 0.1.0
 * Author: whttmr
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'AA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once AA_PLUGIN_DIR . 'classes/Loader.php';

Accessibility_Auditor\Loader::init();
