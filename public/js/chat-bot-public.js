(function ($) {
	'use strict';

	$(function () {

		$('body').append(`
			<div class="chatbot-toggle" id="chatbot-toggle">💬</div>
			<div class="chatbot-widget" id="chatbot-widget">
				<div class="chatbot-header">
					Chatbot
					<button id="chatbot-close" style="float:right; background:none; border:none; color:white; font-size:20px;">×</button>
				</div>
				<div class="chatbot-messages" id="chatbot-messages"></div>
				<div class="chatbot-input-area">
					<input type="text" class="chatbot-input" id="chatbot-input" placeholder="Escribe tu mensaje...">
					<button class="chatbot-send" id="chatbot-send">Enviar</button>
				</div>
			</div>
		`);

		const $widget = $('#chatbot-widget');
		const $toggle = $('#chatbot-toggle');
		const $messages = $('#chatbot-messages');
		const $input = $('#chatbot-input');
		const $send = $('#chatbot-send');

		let enviando = false;

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

		$toggle.on('click', function () {
			$widget.show();
			$toggle.hide();
			$input.focus();
		});

		$('#chatbot-close').on('click', function () {
			$widget.hide();
			$toggle.show();
		});

		function sendMessage() {
			if (enviando) return;

			const rawMessage = $input.val().trim();
			if (!rawMessage) return;

			enviando = true;
			$send.prop('disabled', true);

			$messages.append(
				'<div class="chatbot-message user">' + escapeHTML(rawMessage) + '</div>'
			);

			$input.val('');
			scrollBottom();

			$messages.append(`
				<div class="chatbot-typing" id="chatbot-typing">
					<div class="dots"><span></span><span></span><span></span></div>
				</div>
			`);
			scrollBottom();

			const postId = (window.chatbot_post_id && chatbot_post_id.id)
				? chatbot_post_id.id
				: 0;

			$.ajax({
				url: chatbot_ajax.ajax_url,
				method: 'POST',
				dataType: 'json',
				data: {
					action: 'chatbot_send_message',
					message: rawMessage,
					post_id: postId,
					nonce: chatbot_ajax.nonce
				}
			})
			.done(function (response) {
				$('#chatbot-typing').remove();

				if (response && response.success && response.data) {
					const botText = response.data.response;
					const formatted = formatMessage(botText);

					$messages.append(
						'<div class="chatbot-message bot">' + formatted + '</div>'
					);
				} else {
					$messages.append(
						'<div class="chatbot-message bot">Error al procesar la respuesta.</div>'
					);
				}

				scrollBottom();
			})
			.fail(function () {
				$('#chatbot-typing').remove();
				$messages.append(
					'<div class="chatbot-message bot">Error de conexión con el servidor.</div>'
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
