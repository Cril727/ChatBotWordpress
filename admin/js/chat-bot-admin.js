(function( $ ) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */
	$(function() {
		if ($.fn.wpColorPicker) {
			$('.chatbot-color-field').wpColorPicker({
				change: function() {
					$(this).trigger('input');
				},
				clear: function() {
					$(this).trigger('input');
				}
			});
		}

		const $preview = $('#chatbot-design-preview');
		if ($preview.length) {
			const $previewTitle = $preview.find('.chatbot-preview-title');
			const $previewButton = $preview.find('.chatbot-preview-button');
			const $previewInput = $preview.find('.chatbot-preview-input');

			function getFieldValue(selector, fallback) {
				const value = $(selector).val();
				return value !== undefined && value !== '' ? value : fallback;
			}

			function applyPreview() {
				const botName = getFieldValue('input[name="chatbot_bot_name"]', 'Chatbot');
				const buttonLabel = getFieldValue('input[name="chatbot_button_label"]', '💬');
				const theme = getFieldValue('select[name="chatbot_theme"]', 'light');
				const position = getFieldValue('select[name="chatbot_position"]', 'bottom-right');
				const primary = getFieldValue('input[name="chatbot_primary_color"]', '#10b981');
				const accent = getFieldValue('input[name="chatbot_accent_color"]', '#3b82f6');
				const width = getFieldValue('input[name="chatbot_widget_width"]', '');
				const height = getFieldValue('input[name="chatbot_widget_height"]', '');
				const font = getFieldValue('input[name="chatbot_font_family"]', '');

				$previewTitle.text(botName);
				$preview.attr('data-position', position);
				$preview.attr('data-theme', theme);

				$preview[0].style.setProperty('--chatbot-primary', primary);
				$preview[0].style.setProperty('--chatbot-accent', accent);

				if (width) {
					$preview[0].style.setProperty('--chatbot-width', width + 'px');
				} else {
					$preview[0].style.removeProperty('--chatbot-width');
				}

				if (height) {
					$preview[0].style.setProperty('--chatbot-height', height + 'px');
				} else {
					$preview[0].style.removeProperty('--chatbot-height');
				}

				if (font) {
					$preview[0].style.setProperty('--chatbot-font', font);
				} else {
					$preview[0].style.removeProperty('--chatbot-font');
				}

				if ($previewButton.length) {
					$previewButton.text(buttonLabel);
				}

				if ($previewInput.length) {
					$previewInput.text('Escribe tu mensaje...');
				}
			}

			$('.chatbot-settings').on('input change', 'input, select', function() {
				applyPreview();
			});

			$('.chatbot-color-preset').on('click', function() {
				const primary = $(this).data('primary');
				const accent = $(this).data('accent');
				const $primaryField = $('input[name="chatbot_primary_color"]');
				const $accentField = $('input[name="chatbot_accent_color"]');

				if ($primaryField.length) {
					$primaryField.wpColorPicker('color', primary);
				}
				if ($accentField.length) {
					$accentField.wpColorPicker('color', accent);
				}
			});

			applyPreview();
		}
	});
})( jQuery );
