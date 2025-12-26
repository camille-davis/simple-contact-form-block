/**
 * Contact Form AJAX Handler
 *
 * Handles form submission, validation feedback, and error display.
 */

(function ($) {
	$(document).on('submit', '.scfb-contact-form', function (e) {
		e.preventDefault();

		const $form = $(this);
		const $msg = $form.closest('.scfb-contact-form-wrapper').find('.form-message');
		const $btn = $form.find('button[type="submit"]');
		const btnText = $btn.text();
		const postId = $form.data('post-id') || '';

		$msg.hide();
		$btn.prop('disabled', true).text('Sending...');

		$.ajax({
			url: scfbContactForm.ajaxUrl,
			type: 'POST',
			data: {
				action: 'scfb_contact_form',
				nonce: scfbContactForm.nonce,
				name: $form.find('input[name="name"]').val(),
				email: $form.find('input[name="email"]').val(),
				message: $form.find('textarea[name="message"]').val(),
				website: $form.find('input[name="website"]').val() || '', // Honeypot field (hidden).
				post_id: postId,
			},
			success: (r) => {
				$msg.text(r.data.message)
					.css('opacity', 0)
					.slideDown(200)
					.animate({ opacity: 1 }, { queue: false, duration: 200 });

				if (r.success) {
					$form[0].reset();
				}
			},
			error: () => {
				$msg.text('An error occurred. Please try again later.').fadeIn();
			},
			complete: () => {
				$btn.prop('disabled', false).text(btnText);
			},
		});
	});
})(jQuery);

