<?php

/**
 * Fez Core
 *
 * @package Fez_Delivery
 * @since 1.0.0
 * @author Fez Team (https://www.fezdelivery.co)
 * @copyright (c) 2025, Fez Team (https://www.fezdelivery.co)
 */

namespace Fez_Delivery\Admin;

use Fez_Delivery\Base;
use Mpdf\Mpdf;

use BaconQrCode\Common\ErrorCorrectionLevel as CommonErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Color\Color;

//check for security
if (!defined('ABSPATH')) {
	exit("You are not allowed to access this file.");
}

class Fez_Shipping_Label extends Base
{
	/**
	 * Generate QR code data URI
	 *
	 * @param string $data
	 * @return string
	 */
	private function generate_qr_code($data)
	{
		try {
			$qr = QrCode::create($data)
				->setSize(150)
				->setMargin(10)
				->setForegroundColor(new Color(0, 0, 0))
				->setBackgroundColor(new Color(255, 255, 255));

			$writer = new PngWriter();
			$result = $writer->write($qr);

			// Return data URI
			return $result->getDataUri();
		} catch (\Exception $e) {
			error_log('QR Code Generation Error: ' . $e->getMessage());
			return '';
		}
	}

	/**
	 * Delete temp files
	 * @return void
	 */
	private function delete_temp_files()
	{
		try {
			//empty temp directory
			exec('rm -rf ' . wp_upload_dir()['basedir'] . '/fez-delivery/mpdf/*');
		} catch (\Exception $e) {
			error_log('Temp File Deletion Error: ' . $e->getMessage());
		}
	}


	/**
	 * Add style to mpdf
	 *
	 * @param Mpdf $mpdf
	 * @return string
	 */
	private function add_style_to_mpdf(Mpdf $mpdf)
	{
		ob_start();
?>

		.fez-delivery-shipping-label-container {
		border: 1px solid #000;
		padding: 12px;
		width: 350px;
		font-family: Arial, sans-serif;
		position: relative;
		}

		.fez-delivery-shipping-label-container::before {
		content: '';
		position: absolute;
		top: 0;
		left: 0;
		right: 0;
		bottom: 0;
		background-image: url('<?php echo FEZ_DELIVERY_ASSETS_URL; ?>img/fez_logo.svg');
		background-size: contain;
		background-position: center;
		background-repeat: no-repeat;
		opacity: 0.1;
		z-index: 0;
		}

		.fez-delivery-shipping-label-container>* {
		position: relative;
		z-index: 1;
		}

		.fez-delivery-shipping-label-container .logo {
		display: flex;
		align-items: center;
		font-size: 24px;
		font-weight: bold;
		color: #000;
		}

		.fez-delivery-shipping-label-container .qr-barcode {
		text-align: center;
		margin: 10px 0;
		display: flex;
		border-bottom: 1px solid #868686;
		padding-bottom: 10px;
		border-bottom-style: dashed;
		}

		.fez-delivery-shipping-label-container .info {
		font-size: 14px;
		margin-bottom: 10px;
		display: flex;
		flex-direction: column;
		gap: 12px;
		border-bottom: 1px solid #868686;
		padding-bottom: 10px;
		border-bottom-style: dashed;
		}

		.fez-delivery-shipping-label-container .info strong {
		font-weight: bold;
		}

		.fez-delivery-shipping-label-container .barcode {
		text-align: center;
		font-size: 14px;
		display: flex;
		flex-direction: column;
		margin-top: 3rem;
		}

	<?php
		$style = ob_get_clean();
		return $style;
	}

	/**
	 * Generate shipping label
	 *
	 * @param string $fez_delivery_order_nos
	 * @return void
	 */
	public function generate_shipping_label($fez_delivery_order_nos)
	{
		//check if fez delivery order nos is not empty
		if (empty($fez_delivery_order_nos)) {
			return;
		}

		//delete temp files
		$this->delete_temp_files();

		//init mpdf
		$mpdf = new Mpdf([
			'mode' => 'utf-8',
			'format' => [120, 180],
			'margin_left' => 5,
			'margin_right' => 5,
			'margin_top' => 5,
			'margin_bottom' => 5,
			'tempDir' => wp_upload_dir()['basedir'] . '/fez-delivery/mpdf'
		]);

		// Enable background images and CSS styles
		$mpdf->SetDisplayMode('fullpage');
		$mpdf->showImageErrors = true;
		$mpdf->list_indent_first_level = 0;

		$mpdf->WriteHTML($this->add_style_to_mpdf($mpdf), \Mpdf\HTMLParserMode::HEADER_CSS);


		//ob start
		ob_start();

		//init fez
		$fez_core = Fez_Core::instance();
		//get fez delivery order details
		$response = $fez_core->getFezDeliveryOrderDetails(0, $fez_delivery_order_nos);
		//get order detail
		$order_detail = $response['data']['order_detail'];
	?>
		<div>
			<div class="fez-delivery-shipping-label-container">
				<div class="logo">
					<img src="<?php echo FEZ_DELIVERY_ASSETS_URL; ?>img/fez_logo.svg" width="50" alt="Fez Logo">
				</div>

				<div class="qr-barcode">
					<img src="<?php echo $this->generate_qr_code($order_detail->orderNo); ?>" alt="QR Code">
				</div>

				<div class="info">
					<div class="info-item">
						<strong>Destination:</strong> <span><?php echo $order_detail->manifest->pickUpState ?> - <?php echo $order_detail->manifest->dropOffState ?></span>
					</div>
					<div class="info-item">
						<strong>Order ID:</strong> <span><?php echo $order_detail->orderNo ?></span>
					</div>
					<div class="info-item">
						<strong>Recipient Name:</strong> <span><?php echo $order_detail->manifest->recipientName ?></span>
					</div>
					<div class="info-item">
						<strong>Recipient Phone:</strong> <span><?php echo $order_detail->manifest->recipientPhone ?></span>
					</div>
					<div class="info-item">
						<strong>Recipient Address:</strong> <span><?php echo $order_detail->manifest->recipientAddress ?></span>
					</div>
					<div class="info-item">
						<strong>Content Description:</strong> <span><?php echo $order_detail->manifest->description ?></span>
					</div>
					<div class="info-item">
						<strong>Sender Name:</strong> <span><?php echo $order_detail->manifest->sendersName ?></span>
					</div>
				</div>

				<div class="barcode">
					<img src="https://barcode.tec-it.com/barcode.ashx?data=<?php echo $order_detail->orderNo ?>&code=Code128" alt="Barcode">
					<br> <?php echo $order_detail->orderNo ?>
				</div>
			</div>
		</div>
<?php
		//get the output
		$output = ob_get_clean();

		// Generate PDF
		$mpdf->WriteHTML($output);
		//download pdf
		$mpdf->Output('shipping_label.pdf', 'D');
	}
}
