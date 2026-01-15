(function ($) {
	'use strict';

	$(function () {

		const settings = window.chatbot_ui_settings || {};
		const botName = settings.bot_name || 'Chatbot';
		const buttonLabel = settings.button_label || '💬';
		const position = settings.position || 'bottom-right';
		const themeClass = settings.theme === 'dark' ? 'neurorag-theme-dark' : '';

		const styleVars = [];
		if (settings.primary_color) {
			styleVars.push('--chatbot-primary: ' + settings.primary_color);
		}
		if (settings.accent_color) {
			styleVars.push('--chatbot-accent: ' + settings.accent_color);
		}
		if (settings.widget_width) {
			styleVars.push('--chatbot-width: ' + settings.widget_width + 'px');
		}
		if (settings.widget_height) {
			styleVars.push('--chatbot-height: ' + settings.widget_height + 'px');
		}
		if (settings.font_family) {
			styleVars.push('--chatbot-font: ' + settings.font_family);
		}
		const styleAttr = styleVars.length ? ' style="' + styleVars.join('; ') + '"' : '';

		$('body').append(`
			<div id="neurorag-bot-widget" class="neurorag-bot-pos-${position} ${themeClass}"${styleAttr}>
				<button id="neurorag-bot-button">${buttonLabel}</button>
				<div id="neurorag-bot-container" style="display: none;">
					<div id="neurorag-bot-header">
						<span id="neurorag-bot-title">${botName}</span>
						<div id="neurorag-bot-controls">
							<button id="neurorag-bot-theme-toggle">🌙</button>
							<button id="neurorag-bot-close">×</button>
						</div>
					</div>
					<div id="neurorag-bot-messages"></div>
					<div id="neurorag-bot-input-area">
						<input type="text" id="neurorag-bot-input" placeholder="Escribe tu mensaje...">
						<button id="neurorag-bot-send">
							<svg viewBox="0 0 24 24">
								<path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
							</svg>
						</button>
					</div>
				</div>
			</div>
		`);

		const $widget = $('#neurorag-bot-widget');
		const $button = $('#neurorag-bot-button');
		const $container = $('#neurorag-bot-container');
		const $messages = $('#neurorag-bot-messages');
		const $input = $('#neurorag-bot-input');
		const $send = $('#neurorag-bot-send');
		const $themeToggle = $('#neurorag-bot-theme-toggle');

		let enviando = false;

		if (settings.theme === 'dark') {
			$themeToggle.text('☀️');
		}

		function escapeHTML(text) {
			return $('<div>').text(text).html();
		}

		function scrollBottom() {
			setTimeout(function () {
				$messages.scrollTop($messages[0].scrollHeight);
			}, 30);
		}

		function formatMessage(text) {
			if (typeof text !== 'string') return 'Respuesta inválida del servidor';

			text = escapeHTML(text);

			text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
			text = text.replace(/\n/g, '<br>');
			text = text.replace(/^[-*]\s+(.*)$/gm, '<li>$1</li>');

			if (!text.includes('<ul>')) {
				text = text.replace(/(<li>.*<\/li>)/gs, '<ul>$1</ul>');
			}

			return text;
		}

		$button.on('click', function () {
			$container.show();
			$button.hide();
			$input.focus();
		});

		$('#neurorag-bot-close').on('click', function () {
			$container.hide();
			$button.show();
		});

		$themeToggle.on('click', function () {
			$widget.toggleClass('neurorag-theme-dark');
			const isDark = $widget.hasClass('neurorag-theme-dark');
			$themeToggle.text(isDark ? '☀️' : '🌙');
		});

		function sendMessage() {
			if (enviando) return;

			const rawMessage = $input.val().trim();
			if (!rawMessage) return;

			enviando = true;
			$send.prop('disabled', true);

			$messages.append(
				'<div class="neurorag-bot-message user"><div class="neurorag-bot-message-content">' + escapeHTML(rawMessage) + '</div></div>'
			);

			$input.val('');
			scrollBottom();

			$messages.append(`
				<div class="neurorag-bot-message bot">
					<div class="neurorag-bot-typing" id="neurorag-bot-typing">
						<span></span><span></span><span></span>
					</div>
				</div>
			`);
			scrollBottom();

			const postId = (window.chatbot_post_id && chatbot_post_id.id)
				? chatbot_post_id.id
				: 0;

			const currentUrl = (window.chatbot_current_url && chatbot_current_url.url)
				? chatbot_current_url.url
				: '';

			$.ajax({
				url: chatbot_ajax.ajax_url,
				method: 'POST',
				dataType: 'json',
				data: {
					action: 'chatbot_send_message',
					message: rawMessage,
					post_id: postId,
					current_url: currentUrl,
					nonce: chatbot_ajax.nonce
				}
			})
			.done(function (response) {
				$('#neurorag-bot-typing').parent().remove();

				if (response && response.success && response.data) {
					const botText = response.data.response;
					const formatted = formatMessage(botText);

					$messages.append(
						'<div class="neurorag-bot-message bot"><div class="neurorag-bot-message-content">' + formatted + '</div></div>'
					);
				} else {
					$messages.append(
						'<div class="neurorag-bot-message bot"><div class="neurorag-bot-message-content">Error al procesar la respuesta.</div></div>'
					);
				}

				scrollBottom();
			})
			.fail(function () {
				$('#neurorag-bot-typing').parent().remove();
				$messages.append(
					'<div class="neurorag-bot-message bot"><div class="neurorag-bot-message-content">Error de conexión con el servidor.</div></div>'
				);
				scrollBottom();
			})
			.always(function () {
				enviando = false;
				$send.prop('disabled', false);
				$input.focus();
			});
		}

		$send.on('click', sendMessage);

		$input.on('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				sendMessage();
			}
		});

	});
})(jQuery);
