/**
 * Contact Form Block Editor
 */

(function () {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps, InspectorControls } = wp.blockEditor;
	const { PanelBody, TextControl } = wp.components;
	const { createElement: el } = wp.element;
	const { SVG, Path } = wp.primitives;

	// Email icon SVG.
	const emailIcon = el(SVG, {
		viewBox: '0 0 24 24',
		xmlns: 'http://www.w3.org/2000/svg',
		width: 24,
		height: 24,
		'aria-hidden': true,
		focusable: false,
	}, el(Path, {
		d: 'M3 7c0-1.1.9-2 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Zm2-.5h14c.3 0 .5.2.5.5v1L12 13.5 4.5 7.9V7c0-.3.2-.5.5-.5Zm-.5 3.3V17c0 .3.2.5.5.5h14c.3 0 .5-.2.5-.5V9.8L12 15.4 4.5 9.8Z',
		fillRule: 'evenodd',
		clipRule: 'evenodd',
	}));

	registerBlockType('scfb/contact-form', {
		icon: emailIcon,
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

