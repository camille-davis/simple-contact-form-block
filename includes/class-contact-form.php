<?php
/**
 * Contact Form Block Class
 *
 * @package SimpleContactFormBlock
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contact Form Block Class
 */
class SCFB_Contact_Form {

	/**
	 * Rate limit: maximum submissions per IP per hour.
	 *
	 * @var int
	 */
	private const RATE_LIMIT = 3;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private const VERSION = '1.0.0';

	/**
	 * Plugin directory path.
	 *
	 * @var string
	 */
	private $plugin_dir;

	/**
	 * Plugin URL.
	 *
	 * @var string
	 */
	private $plugin_url;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->plugin_dir = dirname( dirname( __FILE__ ) );
		$this->plugin_url = plugin_dir_url( dirname( __FILE__ ) );

		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'wp_ajax_scfb_contact_form', array( $this, 'handle_submission' ) );
		add_action( 'wp_ajax_nopriv_scfb_contact_form', array( $this, 'handle_submission' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	// ============================================================================
	// Block Registration
	// ============================================================================

	/**
	 * Registers the contact form block.
	 *
	 * @return void
	 */
	public function register_block() {
		// Register editor script.
		wp_register_script(
			'scfb-contact-form-block-editor',
			$this->plugin_url . 'blocks/contact-form/index.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-server-side-render', 'wp-element', 'wp-components', 'wp-i18n' ),
			self::VERSION,
			true
		);

		// Register the block.
		register_block_type(
			$this->plugin_dir . '/blocks/contact-form',
			array( 'editor_script' => 'scfb-contact-form-block-editor' )
		);
	}

	// ============================================================================
	// Form Submission Handler
	// ============================================================================

	/**
	 * Handles the contact form submission on the backend.
	 *
	 * @return void
	 */
	public function handle_submission() {
		// Security check.
		if ( ! $this->verify_security() ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'simple-contact-form-block' ) ) );
			return;
		}

		// Check rate limit.
		$rate_limit_check = $this->check_rate_limit();
		if ( is_wp_error( $rate_limit_check ) ) {
			wp_send_json_error( array( 'message' => $rate_limit_check->get_error_message() ) );
			return;
		}

		// Sanitize and validate input.
		$data = $this->sanitize_input();
		if ( is_wp_error( $data ) ) {
			wp_send_json_error( array( 'message' => $data->get_error_message() ) );
			return;
		}

		// Send email.
		if ( ! $this->send_email( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, there was an error sending your message. Please try again later or email us directly.', 'simple-contact-form-block' ) ) );
			return;
		}

		// Increment rate limit counter after successful email.
		$this->increment_rate_limit();

		wp_send_json_success( array( 'message' => __( 'Thank you for contacting us! We will get back to you soon.', 'simple-contact-form-block' ) ) );
	}

	// ============================================================================
	// Security & Validation
	// ============================================================================

	/**
	 * Verifies security nonce and honeypot field.
	 *
	 * @return bool True if security check passes.
	 */
	private function verify_security() {
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		$honeypot = ! empty( $_POST['website'] );

		return wp_verify_nonce( $nonce, 'scfb_contact_form_nonce' ) && ! $honeypot;
	}

	/**
	 * Sanitizes and validates form input.
	 *
	 * @return array|WP_Error Array with 'name', 'email', 'message' keys, or WP_Error on failure.
	 */
	private function sanitize_input() {
		$name    = sanitize_text_field( $_POST['name'] ?? '' );
		$email   = sanitize_email( $_POST['email'] ?? '' );
		$message = sanitize_textarea_field( $_POST['message'] ?? '' );

		// Validate required fields.
		if ( empty( $name ) || empty( $email ) || empty( $message ) ) {
			return new WP_Error( 'missing_fields', __( 'Please fill in all required fields with valid information.', 'simple-contact-form-block' ) );
		}

		// Validate email format.
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Please fill in all required fields with valid information.', 'simple-contact-form-block' ) );
		}

		// Check for URLs.
		if ( $this->contains_url( $name ) || $this->contains_url( $message ) ) {
			return new WP_Error( 'url_detected', __( 'Sorry, links are not allowed. Please remove them and resend your message.', 'simple-contact-form-block' ) );
		}

		// Prevent email header injection.
		if ( $this->contains_newlines( $name ) || $this->contains_newlines( $email ) ) {
			return new WP_Error( 'invalid_input', __( 'Invalid input detected.', 'simple-contact-form-block' ) );
		}

		return array(
			'name'    => $name,
			'email'   => $email,
			'message' => $message,
		);
	}

	/**
	 * Checks if text contains URLs.
	 *
	 * @param string $text Text to check.
	 * @return bool True if URL is detected.
	 */
	private function contains_url( $text ) {
		return ! empty( $text ) && ( strpos( $text, 'http://' ) !== false || strpos( $text, 'https://' ) !== false );
	}

	/**
	 * Checks if text contains newlines or carriage returns.
	 *
	 * @param string $text Text to check.
	 * @return bool True if newlines are detected.
	 */
	private function contains_newlines( $text ) {
		return false !== strpos( $text, "\n" ) || false !== strpos( $text, "\r" );
	}

	/**
	 * Gets the transient key for rate limiting based on client IP.
	 *
	 * @return string Transient key.
	 */
	private function get_rate_limit_key() {
		$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
		return 'scfb_contact_form_' . md5( $ip );
	}

	/**
	 * Gets the current submission count for the client IP.
	 *
	 * @return int Current submission count.
	 */
	private function get_submission_count() {
		$transient_key = $this->get_rate_limit_key();
		$submissions = get_transient( $transient_key );
		return $submissions === false ? 0 : $submissions;
	}

	/**
	 * Checks if the current IP has exceeded the rate limit.
	 *
	 * @return true|WP_Error True if allowed, WP_Error if limit exceeded.
	 */
	private function check_rate_limit() {
		if ( $this->get_submission_count() >= self::RATE_LIMIT ) {
			return new WP_Error( 'rate_limit_exceeded', __( 'Too many submissions. Please try again later.', 'simple-contact-form-block' ) );
		}

		return true;
	}

	/**
	 * Increments the rate limit counter for the current IP.
	 *
	 * @return void
	 */
	private function increment_rate_limit() {
		$transient_key = $this->get_rate_limit_key();
		$submissions = $this->get_submission_count();
		set_transient( $transient_key, $submissions + 1, HOUR_IN_SECONDS );
	}

	// ============================================================================
	// Email Handling
	// ============================================================================

	/**
	 * Sends the contact form email.
	 *
	 * @param array $data Form data with 'name', 'email', 'message' keys.
	 * @return bool True on success, false on failure.
	 */
	private function send_email( $data ) {
		$to = $this->get_recipient_email();
		$subject = sprintf( __( 'Contact Form Submission from %s', 'simple-contact-form-block' ), get_bloginfo( 'name' ) );
		$body = $this->build_email_body( $data );
		$headers = $this->build_email_headers( $data );

		return wp_mail( $to, $subject, $body, $headers );
	}

	/**
	 * Gets the recipient email address from block attributes.
	 * Never trusts POST data for recipient email to prevent email hijacking.
	 *
	 * @return string Email address.
	 */
	private function get_recipient_email() {
		// Default to admin email.
		$recipient_email = get_option( 'admin_email' );

		// Get post ID from POST or referer.
		$post_id = ! empty( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			// Fallback: try to get from referer.
			$referer = wp_get_referer();
			if ( $referer ) {
				$post_id = url_to_postid( $referer );
			}
		}

		// Retrieve recipient email from block attributes in post content.
		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$blocks = parse_blocks( $post->post_content );
				foreach ( $blocks as $block ) {
					if ( 'scfb/contact-form' === $block['blockName'] && ! empty( $block['attrs']['email'] ) ) {
						$block_email = sanitize_email( $block['attrs']['email'] );
						if ( is_email( $block_email ) ) {
							$recipient_email = $block_email;
							break;
						}
					}
				}
			}
		}

		return $recipient_email;
	}

	/**
	 * Builds the email body.
	 *
	 * @param array $data Form data.
	 * @return string Email body.
	 */
	private function build_email_body( $data ) {
		return sprintf(
			"%s: %s\n\n%s: %s\n\n%s:\n%s",
			__( 'Name', 'simple-contact-form-block' ),
			$data['name'],
			__( 'Email', 'simple-contact-form-block' ),
			$data['email'],
			__( 'Message', 'simple-contact-form-block' ),
			$data['message']
		);
	}

	/**
	 * Builds the email headers.
	 *
	 * @param array $data Form data.
	 * @return array Email headers.
	 */
	private function build_email_headers( $data ) {
		return array(
			'From: ' . $data['name'] . ' <' . $data['email'] . '>',
			'Reply-To: ' . $data['email'],
			'Content-Type: text/plain; charset=UTF-8',
		);
	}

	// ============================================================================
	// Assets
	// ============================================================================

	/**
	 * Enqueues contact form JavaScript functionality on the frontend.
	 * Only loads if the contact form block is present on the page.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! $this->has_block() ) {
			return;
		}

		wp_enqueue_script(
			'scfb-contact-form',
			$this->plugin_url . 'js/contact-form.js',
			array( 'jquery' ),
			self::VERSION,
			true
		);

		wp_localize_script(
			'scfb-contact-form',
			'scfbContactForm',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'scfb_contact_form_nonce' ),
			)
		);
	}

	/**
	 * Checks if the contact form block exists on the current page.
	 *
	 * @return bool True if block is present.
	 */
	private function has_block() {
		// Check if block exists in post content.
		$post = get_post();
		if ( $post && has_block( 'scfb/contact-form', $post ) ) {
			return true;
		}

		// Check widget content.
		$widgets = get_option( 'widget_block', array() );
		$widget_content = '';
		foreach ( $widgets as $widget ) {
			if ( ! empty( $widget['content'] ) ) {
				$widget_content .= $widget['content'];
			}
		}

		return ! empty( $widget_content ) && has_block( 'scfb/contact-form', $widget_content );
	}
}

