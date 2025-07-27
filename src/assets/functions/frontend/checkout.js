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
		//get country value
		const country = jQuery("#billing_country").val();
		//check if country is not NG
		if (country != "NG") {
			//trigger change event on country
			jQuery("#billing_country").trigger("change");
			//return
			return;
		}
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

		var locationId = "";
		var weightId = "";

		//get country mode
		var countryMode = jQuery(element).data("country-mode");
		//check if country mode is true
		if (countryMode) {
			//get location id
			locationId = jQuery(element).data("location-id");
			//get weight id
			weightId = jQuery(element).data("weight-id");
		}
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
				location_id: locationId,
				weight_id: weightId,
				country_mode: countryMode,
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
	 * @param {string} safe_locker_id
	 *
	 */
	const getDeliveryCost = (safe_locker_id = "none") => {
		//reset delivery cost
		resetDeliveryCost();

		//add delay for 2 seconds
		setTimeout(() => {
			//get country value
			const country = jQuery("#billing_country").val();

			//get #ship-to-different-address-checkbox
			const shipToDifferentAddressCheckbox = jQuery(
				"#ship-to-different-address-checkbox",
			);

			//billing state elem
			const billingStateElement = jQuery("#billing_state");

			//get billing state
			var deliveryState =
				billingStateElement.length > 0 ? billingStateElement.val() : "";
			//check if the checkbox is checked
			if (
				shipToDifferentAddressCheckbox.length > 0 &&
				shipToDifferentAddressCheckbox.is(":checked")
			) {
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
					country: country,
					safe_locker_id: safe_locker_id,
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
						showDeliveryCost(
							response.data.cost.cost,
							response.data,
						);
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
		}, 2000);
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
		//get country value
		const country = jQuery("#billing_country").val();
		//check if country is not NG
		if (country != "NG") {
			//return
			return;
		}

		if (window.fez_delivery_frontend.enable_fez_safe_locker == "yes") {
			//trigger safe locker checks
			triggerSafeLockerChecks();
			return;
		}

		//get delivery cost
		getDeliveryCost();
	});

	//on change of #billing_country
	jQuery("#billing_country").on("change", function () {
		//get country value
		const country = jQuery("#billing_country").val();

		//check if country is not NG
		if (country == "NG") {
			//do nothing
			return;
		}

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
							data-country-mode='${response_data.country_mode}'
							data-delivery-cost='${delivery_cost}' ${
								response_data.country_mode
									? `data-location-id='${response_data.location_id}' data-weight-id='${response_data.weight_id}'`
									: ""
							}>Select Delivery</a>
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
					//get country value
					const country = jQuery("#billing_country").val();
					//check if country is not NG
					if (country != "NG") {
						//trigger change event on country
						jQuery("#billing_country").trigger("change");
					} else {
						//trigger change event on state
						jQuery("#billing_state").trigger("change");
					}
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

	window.handleSafeLockerChange = (element, event) => {
		//check if local storage has fez-safe-locker-ignore
		if (localStorage.getItem("fez-safe-locker-ignore") == "true") {
			//return
			return;
		}
		//get selected option
		const selectedOption = jQuery(element).find("option:selected");
		//check if selected option is not empty
		var safe_locker_id = selectedOption.val();
		//check if safe locker id is not none
		if (safe_locker_id != "none") {
			//get delivery cost
			getDeliveryCost(safe_locker_id);
		}
	};

	window.handleIgnoreSafeLockerChange = (element, event) => {
		//get the checkbox
		const checkbox = jQuery(element);
		//check if the checkbox is checked
		if (checkbox.is(":checked")) {
			//save local storage
			localStorage.setItem("fez-safe-locker-ignore", "true");
			//fez_safe_locker_html_checker
			fez_safe_locker_html_checker();
			//get delivery cost
			getDeliveryCost();
		} else {
			//trigger fez_safe_locker_html_checker
			fez_safe_locker_html_checker(false);
			//remove local storage
			localStorage.removeItem("fez-safe-locker-ignore");
		}
	};

	window.fez_safe_locker_html_checker = (blockInput = true) => {
		//check if local storage has fez-safe-locker-ignore
		if (localStorage.getItem("fez-safe-locker-ignore") == "true") {
			//get element with class 'fez-safe-locker-content'
			var content = jQuery(".fez-safe-locker-content");
			//check if blockInput is true
			if (blockInput) {
				//check if content exists
				if (content.length > 0) {
					//ignore safe locker
					jQuery("#fez-safe-locker-ignore").prop("checked", true);
					//find select2-container and block it
					content.find(".select2-container").block({
						message: null,
						overlayCSS: {
							backgroundColor: "#fff",
							opacity: 0.5,
							cursor: "not-allowed",
						},
						css: {
							border: "1px solid #ccc",
							padding: "10px",
							backgroundColor: "#f9f9f9",
							borderRadius: "5px",
						},
					});
					//find "blockUI blockOverlay" and remove the ::before pseudo element
					content
						.find(".blockUI.blockOverlay")
						.addClass("fez-safe-locker-ignore-before");
				}
			} else {
				//ignore safe locker
				jQuery("#fez-safe-locker-ignore").prop("checked", false);
				//find select2-container and unblock it
				content.find(".select2-container").unblock();
				//log
				console.log("unblocked");
			}
		}
	};

	/**
	 * Trigger safe locker checks
	 *
	 */
	window.triggerSafeLockerChecks = () => {
		try {
			//get country value
			const country = jQuery("#billing_country").val();
			//check if country is not NG
			if (country != "NG") {
				//return
				return;
			}

			//get billing state
			const billingState = jQuery("#billing_state").val();
			//check if billing state is not empty
			if (billingState == "") {
				//return
				return;
			}

			var form = jQuery("form[name='checkout']");

			//get safe locker content from server
			jQuery.ajax({
				url: fez_delivery_frontend.ajax_url,
				type: "GET",
				data: {
					action: "get_safe_locker_content",
					nonce: fez_delivery_frontend.nonce,
					country: country,
					billing_state: billingState,
				},
				beforeSend: function () {
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
						//get safe lockers
						var safeLockers = response.data.data.Lockers;
						//check if safe lockers is not empty
						if (safeLockers.length > 0) {
							//inner html ".fez-safe-locker-content"
							jQuery(".fez-safe-locker-content").html(
								`
								<select name="fez-safe-locker" id="fez-safe-locker" onchange="handleSafeLockerChange(this, event)" class="fez-safe-locker-select" style="width: 100%;">
									<option value="none">Select Safe Locker</option>
									${safeLockers.map(
										(safeLocker) =>
											`<option value="${safeLocker.lockerID}">${safeLocker.lockerAddress}</option>`,
									)}
								</select>
								<div class="fez-safe-locker-ignore-container">
									<input type="checkbox" name="fez-safe-locker-ignore" id="fez-safe-locker-ignore" onclick="handleIgnoreSafeLockerChange(this, event)">
									<label for="fez-safe-locker-ignore">Ignore Safe Locker</label>
								</div>
								`,
							);
							//show .fez-safe-locker
							jQuery(".fez-safe-locker").show();
							//init select2
							jQuery(".fez-safe-locker-select").select2();
							//check if local storage has fez-safe-locker-ignore
							fez_safe_locker_html_checker();
						} else {
							//hide .fez-safe-locker
							jQuery(".fez-safe-locker").hide();
							//skip and get delivery cost
							getDeliveryCost();
						}
					} else {
						//hide .fez-safe-locker
						jQuery(".fez-safe-locker").hide();
						//skip and get delivery cost
						getDeliveryCost();
					}
				},
				error: function (response) {
					//unblock form name="checkout"
					form.unblock();
					//log
					console.log(response);
				},
			});
		} catch (error) {
			console.log(error);
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

	/**
	 * Check if fez safe locker is enabled
	 *
	 */
	window.checkIfFezSafeLockerIsEnabled = () => {
		//get fez_delivery_frontend
		const fez_delivery_frontend = window.fez_delivery_frontend;
		//check if fez_delivery_frontend.enable_fez_safe_locker is true
		if (fez_delivery_frontend.enable_fez_safe_locker == "yes") {
			//check country
			const country = jQuery("#billing_country").val();
			//check if country is not NG
			if (country != "NG") {
				//return
				return;
			}

			//check if .fez-safe-locker exists
			if (jQuery(".fez-safe-locker").length > 0) {
				//return
				return;
			}
			//append fez safe locker to the checkout before #billing_state_field
			jQuery("#billing_state_field").after(`
				<p class="form-row form-row-wide fez-safe-locker" data-priority="100">
					<label for="billing_phone" class="required_field">Fez Safe Locker</label>
					<span class="woocommerce-input-wrapper fez-safe-locker-content">
						<span class="fez-safe-locker-content-text">Checking safe locker...</span>
					</span>
				</p>
			`);
		}
	};

	//init checkIfFezSafeLockerIsEnabled
	setInterval(checkIfFezSafeLockerIsEnabled, 1000);
});
