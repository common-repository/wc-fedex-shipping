<?php
/**
 * Plugin Name: Advanced FedEx Shipping - Live Rates & Address Validation for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/wc-fedex-shipping/
 * Description: Displays live FedEx shipping rates at cart and checkout pages and validates address before allowing to place an order
 * Text Domain: wc-fedex-shipping
 * Version: 1.2.7
 * Tested up to: 6.6
 * Author: OneTeamSoftware
 * Author URI: http://oneteamsoftware.com/
 * Developer: OneTeamSoftware
 * Developer URI: http://oneteamsoftware.com/
 * Text Domain: wc-fedex-shipping
 * Domain Path: /languages
 *
 * Copyright: © 2021 FlexRC, Canada.
 */

/*********************************************************************/
/*  PROGRAM          FlexRC                                          */
/*  PROPERTY         3-7170 Ash Cres                                 */
/*  OF               Vancouver BC   V6P 3K7                          */
/*  				 Voice 604 800-7879                              */
/*                                                                   */
/*  Any usage / copying / extension or modification without          */
/*  prior authorization is prohibited                                */
/*********************************************************************/

namespace OneTeamSoftware\WooCommerce\Shipping;

defined('ABSPATH') || exit;

require_once(__DIR__ . '/includes/autoloader.php');
	
(new Plugin(
		__FILE__, 
		'Fedex', 
		sprintf('<div class="notice notice-info inline"><p>%s<br/><li><a href="%s" target="_blank">%s</a><br/><li><a href="%s" target="_blank">%s</a></p></div>', 
			__('Real-time FedEx shipping rates and address validation', 'wc-fedex-shipping'),
			'https://1teamsoftware.com/contact-us/',
			__('Do you have any questions or requests?', 'wc-fedex-shipping'),
			'https://wordpress.org/plugins/wc-fedex-shipping/', 
			__('Do you like our plugin and can recommend to others?', 'wc-fedex-shipping')),
		'1.2.7'
	)
)->register();
