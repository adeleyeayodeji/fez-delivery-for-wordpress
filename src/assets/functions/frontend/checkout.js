/**
 * This script is used to handle the checkout page
 * @author Fez Delivery
 * @version 1.0.0
 */
jQuery(document).ready(function ($) {
	/**
	 * resetFezDeliveryCost
	 *
	 */
	window.resetFezDeliveryCost = () => {
		//trigger change event on state
		jQuery("#billing_state").trigger("change");
	};

	/**
	 * Reset delivery cost
	 */
	window.resetDeliveryCost = () => {
		var form = jQuery("form[name='checkout']");
		//ajax call to reset delivery cost
		jQuery.ajax({
			url: fez_delivery_frontend.ajax_url,
			type: "POST",
			data: {
				action: "fez_reset_cost_data",
				nonce: fez_delivery_frontend.nonce,
			},
			beforeSend: function () {
				//clear any existing errors
				$(".woocommerce-error-fez-delivery").remove();
				//block form name="checkout"
				form.block({
					message: null,
					overlayCSS: {
						backgroundColor: "#fff",
					},
				});
			},
			success: function (response) {
				//unblock form name="checkout"
				form.unblock();
				//update checkout
				$(document.body).trigger("update_checkout");
			},
			error: function (response) {
				//unblock form name="checkout"
				form.unblock();
				//show error
				showFezDeliveryError(response.data.message);
			},
		});
	};

	//init resetDeliveryCost
	resetDeliveryCost();

	/**
	 * Show fez delivery error
	 * @param {string} message
	 */
	window.showFezDeliveryError = (message) => {
		//check if message matched "Invalid session" then ignore it
		if (message.includes("Invalid session")) {
			return;
		}
		//create a div for woocommerce notice
		var notice = jQuery(
			"<div class='woocommerce-error woocommerce-error-fez-delivery'>" +
				message +
				"</div>",
		);
		//remove any existing notice
		jQuery(".woocommerce-error-fez-delivery").remove();
		//prepend notice to form name="checkout"
		var form = jQuery("form[name='checkout']");
		form.prepend(notice);
		//scroll to the checkout form
		jQuery("html, body").animate(
			{
				scrollTop: jQuery("form[name='checkout']").offset().top - 100,
			},
			"slow",
		);
	};

	/**
	 * Apply delivery cost
	 * @param {object} element
	 * @param {object} event
	 */
	window.applyDeliveryCost = (element, event) => {
		event.preventDefault();
		//get form
		var form = jQuery("form[name='checkout']");
		//get delivery cost
		var deliveryCost = jQuery(element).data("delivery-cost");
		var deliveryStateLabel = jQuery(element).data("delivery-state-label");
		var pickupStateLabel = jQuery(element).data("pickup-state-label");
		var totalWeight = jQuery(element).data("total-weight");

		//ajax call to apply delivery cost
		jQuery.ajax({
			url: fez_delivery_frontend.ajax_url,
			type: "POST",
			data: {
				action: "apply_fez_delivery_cost",
				delivery_cost: deliveryCost,
				delivery_state_label: deliveryStateLabel,
				pickup_state_label: pickupStateLabel,
				total_weight: totalWeight,
				nonce: fez_delivery_frontend.nonce,
			},
			beforeSend: function () {
				//clear any existing errors
				$(".woocommerce-error-fez-delivery").remove();
				//block form name="checkout"
				form.block({
					message: null,
					overlayCSS: {
						backgroundColor: "#fff",
						opacity: 0.5,
						cursor: "wait",
					},
				});
			},
			success: function (response) {
				//unblock form name="checkout"
				form.unblock();
				//check if response is successful
				if (response.success) {
					//update woocommerce
					$(document.body).trigger("update_checkout");
					//remove .fez-delivery-cost
					$(".fez-delivery-cost").remove();
				} else {
					//show error
					showFezDeliveryError(response.data.message);
				}
			},
			error: function (response) {
				//unblock form name="checkout"
				form.unblock();
				//show error
				showFezDeliveryError(response.data.message);
			},
		});
	};

	/**
	 * Realtime check for delivery cost element
	 *
	 *
	 */
	window.checkForDeliveryCostElement = () => {
		//get .fez-delivery-cost-value
		var deliveryCostValue = jQuery(".fez-delivery-cost-value");
		//check if delivery cost value exists
		if (!deliveryCostValue.length) {
			//check if #shipping_method exists
			var shippingMethod = jQuery("#shipping_method");
			if (shippingMethod.length > 0) {
				//find label with text "Fez Delivery"
				var label = shippingMethod.find(
					"label:contains('Fez Delivery')",
				);
				//check if label exists
				if (label.length > 0) {
					//check if .fez-delivery-try-again exists
					var tryAgain = label.find(".fez-delivery-try-again");
					if (!tryAgain.length) {
						//show try again link
						label.append(`
							<p class='fez-delivery-try-again'>
							<a href='javascript:void(0)' class='fez-delivery-try-again-link' onclick='resetFezDeliveryCost()'>
							Reload Delivery Cost
							</a>
							</p>
						`);
					}
				}
			}
		} else {
			//remove .fez-delivery-try-again
			$(".fez-delivery-try-again").remove();
		}
	};

	//set interval
	setInterval(checkForDeliveryCostElement, 3000);

	/**
	 * Get delivery cost
	 * @param {string} delivery_state
	 *
	 */
	const getDeliveryCost = () => {
		//reset delivery cost
		resetDeliveryCost();
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
				//clear any existing errors
				$(".woocommerce-error-fez-delivery").remove();
				//block form name="checkout"
				form.block({
					message: null,
					overlayCSS: {
						backgroundColor: "#fff",
						opacity: 0.5,
						cursor: "wait",
					},
				});
			},
			success: function (response) {
				//unblock form name="checkout"
				form.unblock();
				//check if response is successful
				if (response.success) {
					//show delivery cost
					showDeliveryCost(response.data.cost.cost, response.data);
					//set timeout
					setTimeout(() => {
						//trigger click on fez-delivery-cta-button
						jQuery(".fez-delivery-cta-button").trigger("click");
					}, 1000);
				} else {
					//show error
					showFezDeliveryError(response.data.message);
				}
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
	jQuery("#billing_state, #shipping_state").on("change", function () {
		//get delivery cost
		getDeliveryCost();
	});

	/**
	 * Show delivery cost
	 * @param {float} delivery_cost
	 * @param {mixed} response_data
	 */
	const showDeliveryCost = (delivery_cost, response_data) => {
		//check if #shipping_method exists
		var shippingMethod = jQuery("#shipping_method");
		if (shippingMethod.length > 0) {
			//find label with text "Fez Delivery"
			var label = shippingMethod.find("label:contains('Fez Delivery')");
			//check if label exists
			if (label.length > 0) {
				//format delivery cost
				var formattedDeliveryCost = Number(
					delivery_cost,
				).toLocaleString("en-US", {
					currency: "NGN",
					minimumFractionDigits: 0,
				});
				//remove .fez-delivery-try-again
				$(".fez-delivery-try-again").remove();

				var content = `
						<div class='fez-delivery-cost-label'>Delivery Cost:</div>
						<div class='fez-delivery-cost-value'>
							<span class='fez-delivery-cost-value-amount'>${formattedDeliveryCost}</span>
							<span class='fez-delivery-cost-value-currency'>NGN</span>
						</div>
						<div class='fez-delivery-cta'>
							<a href='javascript:void(0)' class='fez-delivery-cta-button' onclick='applyDeliveryCost(this, event)'
							data-delivery-state-label='${response_data.delivery_state_label}'
							data-pickup-state-label='${response_data.pickup_state_label}'
							data-total-weight='${response_data.total_weight}'
							data-delivery-cost='${delivery_cost}'>Select Delivery</a>
						</div>
				`;

				//check if .fez-delivery-cost exists
				if (label.find(".fez-delivery-cost").length > 0) {
					//replace content
					label.find(".fez-delivery-cost").html(content);
					//return
					return;
				}
				//show delivery cost
				label.append(`<div class='fez-delivery-cost'>${content}</div>`);
			}
		}
	};

	/**
	 * Confirm fez delivery
	 * @param {object} element
	 * @param {object} event
	 */
	window.confirmFezDelivery = (element, event) => {
		//check if #shipping_method exists
		var shippingMethod = jQuery("#shipping_method");
		if (shippingMethod.length > 0) {
			//find label with text "Fez Delivery"
			var label = shippingMethod.find("label:contains('Fez Delivery')");
			//check if label exists
			if (label.length > 0) {
				//check if label has woocommerce-Price-amount
				if (!label.find(".woocommerce-Price-amount").length) {
					//show error
					showFezDeliveryError(
						"Please select a valid delivery option to continue",
					);
					//return
					return;
				} else {
					//submit form
					jQuery("form[name='checkout']").submit();
				}
			}
		}
	};

	/**
	 * Manipulate the checkout button id="place_order"
	 *
	 *
	 */
	window.manipulateCheckoutButton = () => {
		//remove the event listener on #place_order
		jQuery("#place_order").off("click");
		//change button type to button
		jQuery("#place_order").attr("type", "button");
		//add onclick attribute to #place_order
		jQuery("#place_order").attr(
			"onclick",
			"confirmFezDelivery(this, event);",
		);
	};

	//init
	setInterval(manipulateCheckoutButton, 1000);
});
