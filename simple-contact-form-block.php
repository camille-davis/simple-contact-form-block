<?php
/**
 * Plugin Name: Simple Contact Form Block
 * Plugin URI: https://github.com/camilledavis/simple-contact-form-block
 * Description: A simple contact form block for WordPress Gutenberg.
 * Version: 1.0.0
 * Author: Camille Davis
 * License: GPL-2.0-or-later
 * Text Domain: simple-contact-form-block
 *
 * @package SimpleContactFormBlock
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load the main class.
require_once __DIR__ . '/includes/class-contact-form.php';

// Initialize the plugin.
new SCFB_Contact_Form();

