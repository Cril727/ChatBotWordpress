(function( $ ) {
	'use strict';

	$(function() {
		// Add chat widget to body
		$('body').append(`
			<div class="chatbot-toggle" id="chatbot-toggle">💬</div>
			<div class="chatbot-widget" id="chatbot-widget">
				<div class="chatbot-header">
					Chatbot
					<button id="chatbot-close" style="float:right; background:none; border:none; color:white; font-size:20px;">×</button>
				</div>
				<div class="chatbot-messages" id="chatbot-messages">
					<div class="chatbot-typing" id="chatbot-typing">
						<div class="dots">
							<span></span>
							<span></span>
							<span></span>
						</div>
					</div>
				</div>
				<div class="chatbot-input-area">
					<input type="text" class="chatbot-input" id="chatbot-input" placeholder="Escribe tu mensaje...">
					<button class="chatbot-send" id="chatbot-send">Enviar</button>
				</div>
			</div>
		`);

		// Toggle chat
		$('#chatbot-toggle').on('click', function() {
			$('#chatbot-widget').toggle();
			$(this).hide();
		});

		$('#chatbot-close').on('click', function() {
			$('#chatbot-widget').hide();
			$('#chatbot-toggle').show();
		});

		// Format message text
		function formatMessage(text) {
			// Replace **bold** with <strong>bold</strong>
			text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
			// Replace line breaks with <br>
			text = text.replace(/\n/g, '<br>');
			// Replace - or * at start of line with bullet
			text = text.replace(/^[-*]\s+(.*)$/gm, '<li>$1</li>');
			// Wrap consecutive <li> in <ul>
			text = text.replace(/(<li>.*<\/li>\s*)+/g, '<ul>$&</ul>');
			return text;
		}

		// Send message
		function sendMessage() {
			var message = $('#chatbot-input').val().trim();
			if (!message) return;

			// Add user message
			$('#chatbot-messages').append('<div class="chatbot-message user">' + message + '</div>');
			$('#chatbot-input').val('');
			$('#chatbot-messages').scrollTop($('#chatbot-messages')[0].scrollHeight);

			// Show typing indicator
			$('#chatbot-typing').show();
			$('#chatbot-messages').scrollTop($('#chatbot-messages')[0].scrollHeight);

			// Get current post ID
			var postId = typeof chatbot_post_id !== 'undefined' ? chatbot_post_id.id : 0;

			// Send to server
			$.ajax({
				url: chatbot_ajax.ajax_url,
				type: 'POST',
				data: {
					action: 'chatbot_send_message',
					message: message,
					post_id: postId,
					nonce: chatbot_ajax.nonce
				},
				success: function(response) {
					// Hide typing indicator
					$('#chatbot-typing').hide();
					if (response.success) {
						var formattedResponse = formatMessage(response.data.response);
						$('#chatbot-messages').append('<div class="chatbot-message bot">' + formattedResponse + '</div>');
						$('#chatbot-messages').scrollTop($('#chatbot-messages')[0].scrollHeight);
					} else {
						$('#chatbot-messages').append('<div class="chatbot-message bot">Error: ' + response.data + '</div>');
					}
				},
				error: function() {
					// Hide typing indicator
					$('#chatbot-typing').hide();
					$('#chatbot-messages').append('<div class="chatbot-message bot">Error al enviar mensaje.</div>');
				}
			});
		}

		$('#chatbot-send').on('click', sendMessage);
		$('#chatbot-input').on('keypress', function(e) {
			if (e.which == 13) sendMessage();
		});
	});

})( jQuery );
