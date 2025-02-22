/**
 * This script is used to handle the checkout page
 * @author Fez Delivery
 * @version 1.0.0
 */
jQuery(document).ready(function ($) {
	/**
	 * Get delivery cost
	 * @param {string} delivery_state
	 *
	 */
	const getDeliveryCost = () => {
		//get #ship-to-different-address-checkbox
		const shipToDifferentAddressCheckbox = jQuery(
			"#ship-to-different-address-checkbox",
		);
		//get billing state
		var deliveryState = jQuery("#billing_state").val();
		//check if the checkbox is checked
		if (shipToDifferentAddressCheckbox.is(":checked")) {
			//get the delivery state
			deliveryState = jQuery("#shipping_state").val();
		}

		var form = jQuery("form[name='checkout']");

		//ajax
		jQuery.ajax({
			url: fez_delivery_frontend.ajax_url,
			type: "POST",
			data: {
				action: "get_fez_delivery_cost",
				deliveryState: deliveryState,
				nonce: fez_delivery_frontend.nonce,
			},
			beforeSend: function () {
				//block form name="checkout"
				form.block({
					message: null,
					overlayCSS: {
						backgroundColor: "#fff",
						opacity: 0.5,
						cursor: "wait",
						overlayCSS: {
							backgroundColor: "#fff",
							opacity: 0.5,
							cursor: "wait",
						},
					},
				});
			},
			success: function (response) {
				//unblock form name="checkout"
				form.unblock();
				console.log(response);
			},
			error: function (response) {
				//unblock form name="checkout"
				form.unblock();
				console.log(response);
			},
		});
	};

	/**
	 * Init on page load for checkout
	 *
	 *
	 */
	const initCheckout = () => {
		//check if element with id="ship-to-different-address-checkbox" exists
		if (jQuery("#ship-to-different-address-checkbox").length > 0) {
			//get delivery cost
			getDeliveryCost();
		}
	};

	// initCheckout();

	//on change of #ship-to-different-address-checkbox
	jQuery("#ship-to-different-address-checkbox").on("change", function () {
		//get delivery cost
		getDeliveryCost();
	});
});
