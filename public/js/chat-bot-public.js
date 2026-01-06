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
				<div class="chatbot-messages" id="chatbot-messages"></div>
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

		// Send message
		function sendMessage() {
			var message = $('#chatbot-input').val().trim();
			if (!message) return;

			// Add user message
			$('#chatbot-messages').append('<div class="chatbot-message user">' + message + '</div>');
			$('#chatbot-input').val('');
			$('#chatbot-messages').scrollTop($('#chatbot-messages')[0].scrollHeight);

			// Get current post ID
			var postId = typeof chatbot_post_id !== 'undefined' ? chatbot_post_id : 0;

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
					if (response.success) {
						$('#chatbot-messages').append('<div class="chatbot-message bot">' + response.data.response + '</div>');
						$('#chatbot-messages').scrollTop($('#chatbot-messages')[0].scrollHeight);
					} else {
						$('#chatbot-messages').append('<div class="chatbot-message bot">Error: ' + response.data + '</div>');
					}
				},
				error: function() {
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
