/**
 * Main js file
 *
 * @since 1.0.0
 */

jQuery(document).ready(function ($) {
	/**
	 * Save Fez Auth
	 *
	 * @param {Element} element
	 * @param {Event} event
	 */
	window.saveFezAuth = function (element, event) {
		event.preventDefault();
		//form
		var $form = $(element).closest("form");
		//validate user credentials
		$.ajax({
			type: "POST",
			url: fez_delivery_admin.ajax_url,
			data:
				$form.serialize() +
				"&action=save_fez_auth_woocommerce&nonce=" +
				fez_delivery_admin.nonce,
			dataType: "json",
			beforeSend: function () {
				//change text to "Validating..."
				$form.find("button[type='button']").text("Validating...");
				//add is-busy class
				$form.find("button[type='button']").addClass("is-busy");
			},
			success: function (response) {
				//check if response is successful
				if (response.success) {
					//change text to "Disconnect"
					$form.find("button[type='button']").text("Disconnect");
					//reload page
					location.reload();
				} else {
					//change text to "Save and Connect"
					$form
						.find("button[type='button']")
						.text("Save and Connect");
					//show error message
					alert(response.message);
				}
				//remove is-busy class
				$form.find("button[type='button']").removeClass("is-busy");
			},
			error: function (response) {
				//log
				console.log(response);
				//change text to "Save and Connect"
				$form.find("button[type='button']").text("Save and Connect");
				//remove is-busy class
				$form.find("button[type='button']").removeClass("is-busy");
			},
		});
	};

	/**
	 * Disconnect Fez Auth
	 *
	 * @param {Element} element
	 * @param {Event} event
	 */
	window.disconnectFezAuth = function (element, event) {
		event.preventDefault();
		//confirm
		if (!confirm("Are you sure you want to disconnect from Fez?")) {
			return;
		}
		//send ajax request to disconnect fez auth
		$.ajax({
			type: "POST",
			url: fez_delivery_admin.ajax_url,
			data: {
				action: "disconnect_fez_auth",
				nonce: fez_delivery_admin.nonce,
			},
			dataType: "json",
			beforeSend: function () {
				//change text to "Disconnecting..."
				$(element).text("Disconnecting...");
				$(element).attr("disabled", true);
				$(element).addClass("is-busy");
			},
			success: function (response) {
				//check if response is successful
				if (response.success) {
					//reload page
					location.reload();
				} else {
					//change text to "Disconnect"
					$(element).text("Disconnect");
					$(element).attr("disabled", false);
					$(element).removeClass("is-busy");
					//show error message
					alert(response.message);
				}
			},
			error: function (response) {
				//change text to "Disconnect"
				$(element).text("Disconnect");
				$(element).attr("disabled", false);
				$(element).removeClass("is-busy");
				//log
				console.log(response);
			},
		});
	};

	/**
	 * Fez Mode
	 *
	 * @var {Element} */
	var $fezMode = $("#woocommerce_fez_delivery_fez_mode");
	//check if element with #woocommerce_fez_delivery_fez_mode exists
	if ($fezMode.length > 0) {
		//add attr onlick="saveFezAuth(this)" to button type="submit"
		$fezMode
			.closest("form")
			.find('button[type="submit"]')
			.attr("onclick", "saveFezAuth(this, event)");
		//change text to "Save and Connect"
		$fezMode
			.closest("form")
			.find("button[type='submit']")
			.text("Save and Connect");
		//remove disabled attribute
		$fezMode
			.closest("form")
			.find("button[type='submit']")
			.attr("disabled", false);
		//set connection status
		$fezMode
			.closest("form")
			.find(".fez-connection-status")
			.after(fez_delivery_admin.connection_status.html);
		//check if connection status is connected
		if (
			fez_delivery_admin.connection_status.connection_status ===
			"connected"
		) {
			//change text to "Disconnect"
			$fezMode
				.closest("form")
				.find("button[type='submit']")
				.text("Disconnect")
				.attr("onclick", "disconnectFezAuth(this, event)");
		}

		//change type to button
		$fezMode
			.closest("form")
			.find("button[type='submit']")
			.attr("type", "button");

		//find .fez-password-hidden and hide it
		$fezMode
			.closest("form")
			.find(".fez-password-hidden")
			.closest("tr")
			.hide();
	}
});
