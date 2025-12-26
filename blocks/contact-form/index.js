/**
 * Contact Form Block Editor
 */

(function () {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps, InspectorControls } = wp.blockEditor;
	const { PanelBody, TextControl } = wp.components;
	const { createElement: el } = wp.element;

	registerBlockType('scfb/contact-form', {
		edit: function (props) {
			const { attributes, setAttributes } = props;
			const { email } = attributes;

			return el(
				'div',
				useBlockProps(),
				[

					// Add email field.
					el(InspectorControls, { key: 'inspector' },
						el(PanelBody, { title: wp.i18n.__('Contact Form Settings', 'simple-contact-form-block') },
							el(TextControl, {
								label: wp.i18n.__('Recipient Email', 'simple-contact-form-block'),
								help: wp.i18n.__('Leave empty to use the site admin email.', 'simple-contact-form-block'),
								value: email || '',
								onChange: (value) => setAttributes({ email: value }),
								type: 'email',
								placeholder: wp.i18n.__('example@email.com', 'simple-contact-form-block'),
							})
						)
					),
					el(wp.serverSideRender, {
						key: 'render',
						block: 'scfb/contact-form',
						attributes: attributes
					})
				]
			);
		},
	});
})();

