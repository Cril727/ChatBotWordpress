(function ($) {
	'use strict';

	$(function () {

		$('body').append(`
			<div id="neurorag-bot-widget" class="neurorag-bot-pos-bottom-right">
				<button id="neurorag-bot-button">💬</button>
				<div id="neurorag-bot-container" style="display: none;">
					<div id="neurorag-bot-header">
						<span id="neurorag-bot-title">Chatbot</span>
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
		const $title = $('#neurorag-bot-title');

		const sessionKey = 'chatbot_session_id';
		let sessionId = '';
		try {
			sessionId = window.localStorage ? localStorage.getItem(sessionKey) : '';
		} catch (e) {
			sessionId = '';
		}
		if (!sessionId) {
			sessionId = 'cb_' + Math.random().toString(36).slice(2, 10) + Date.now().toString(36);
			try {
				if (window.localStorage) {
					localStorage.setItem(sessionKey, sessionId);
				}
			} catch (e) {
				// Ignore storage errors.
			}
		}

		let enviando = false;

		function setTheme(isDark) {
			$widget.toggleClass('neurorag-theme-dark', isDark);
			$widget.attr('data-theme', isDark ? 'dark' : 'light');
			$themeToggle.text(isDark ? '☀️' : '🌙');
		}

		function applyButtonIcon(buttonLabel, buttonIcon, botName) {
			if (buttonIcon) {
				$button.empty().append($('<img>', {
					src: buttonIcon,
					alt: buttonLabel || botName || 'Chatbot'
				}));
				$button.attr('aria-label', buttonLabel || botName || 'Chatbot');
			} else {
				$button.text(buttonLabel);
				$button.removeAttr('aria-label');
			}
		}

		function getUiSettings() {
			if (window.chatbot_ui_settings) {
				return window.chatbot_ui_settings;
			}
			if (window.chatbot_ajax && chatbot_ajax.ui_settings) {
				return chatbot_ajax.ui_settings;
			}
			return {};
		}

		function applyUiSettings() {
			const settings = getUiSettings();
			const botName = settings.bot_name || 'Chatbot';
			const buttonLabel = settings.button_label || '💬';
			const buttonIcon = settings.button_icon || '';
			const position = settings.position || 'bottom-right';
			const theme = settings.theme || 'light';

			$title.text(botName);
			applyButtonIcon(buttonLabel, buttonIcon, botName);

			$widget.removeClass('neurorag-bot-pos-bottom-right neurorag-bot-pos-bottom-left neurorag-bot-pos-top-right neurorag-bot-pos-top-left');
			$widget.addClass('neurorag-bot-pos-' + position);

			setTheme(theme === 'dark');

			if (settings.primary_color) {
				$widget[0].style.setProperty('--chatbot-primary', settings.primary_color);
			}
			if (settings.accent_color) {
				$widget[0].style.setProperty('--chatbot-accent', settings.accent_color);
			}
			if (settings.widget_width) {
				$widget[0].style.setProperty('--chatbot-width', settings.widget_width + 'px');
			}
			if (settings.widget_height) {
				$widget[0].style.setProperty('--chatbot-height', settings.widget_height + 'px');
			}
			if (settings.font_family) {
				$widget[0].style.setProperty('--chatbot-font', settings.font_family);
			}
		}

		applyUiSettings();

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
			const isDark = !$widget.hasClass('neurorag-theme-dark');
			setTheme(isDark);
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
					session_id: sessionId,
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
