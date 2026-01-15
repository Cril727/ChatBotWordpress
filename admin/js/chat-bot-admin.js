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
			$('.chatbot-color-field').wpColorPicker();
		}

		function setActiveTab(tab) {
			$('.chatbot-nav-tab').removeClass('nav-tab-active');
			$('.chatbot-tab-panel').removeClass('is-active');

			$('.chatbot-nav-tab[data-tab="' + tab + '"]').addClass('nav-tab-active');
			$('#chatbot-tab-' + tab).addClass('is-active');
		}

		$('.chatbot-nav-tab').on('click', function(e) {
			e.preventDefault();
			const tab = $(this).data('tab');
			setActiveTab(tab);
			if (tab) {
				window.location.hash = 'chatbot-tab-' + tab;
			}
		});

		const hash = window.location.hash.replace('#', '');
		if (hash.indexOf('chatbot-tab-') === 0) {
			setActiveTab(hash.replace('chatbot-tab-', ''));
		}
	});
})( jQuery );
