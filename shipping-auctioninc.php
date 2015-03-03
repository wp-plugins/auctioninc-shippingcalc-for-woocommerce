<?php
/*
  Plugin Name: AuctionInc ShippingCalc for WooCommerce
  Plugin URI: http://www.auctioninc.com/info/page/woocommerce_api_module
  Description: Obtain shipping rates dynamically via the AuctionInc Shipping API for your orders.
  Version: 1.5
  Author: Paid, Inc.
  Author URI: http://www.auctioninc.com

  Copyright: 2014 Paid, Inc.
  License: GNU General Public License v3.0
  License URI: http://www.gnu.org/licenses/gpl-3.0.html

  http://www.auctioninc.com/info/page/shipping_engine
 */

/**
 * Required functions
 */
if (!function_exists('woothemes_queue_update')) {
    require_once( 'woo-includes/woo-functions.php' );
}

if (!class_exists('ShipRateAPI')) {
    require_once('classes/shiprateapi/ShipRateAPI.inc');
}

require_once('woo-includes/product-functions.php');
require_once('admin/order/order-meta.php');

/**
 * Plugin updates
 */
//woothemes_queue_update(plugin_basename(__FILE__), '83d1524e8f5f1913e58889f83d442c32', '18657');

/**
 * Plugin activation check
 */
function wc_auctioninc_activation_check() {

    // Fsockopen check
    if (!function_exists('fsockopen')) {
        deactivate_plugins(basename(__FILE__));
        wp_die("Sorry, but you can't run this plugin, it requires the fsockopen library installed on your server/hosting to function.");
    }

    // Expat XML parser check
    if (!function_exists('xml_parser_free')) {
        deactivate_plugins(basename(__FILE__));
        wp_die("Sorry, but you can't run this plugin, it requires the Expat XML parser library installed on your server/hosting to function.");
    }
}

register_activation_hook(__FILE__, 'wc_auctioninc_activation_check');

/**
 * Localisation
 */
load_plugin_textdomain('wc_auctioninc', false, dirname(plugin_basename(__FILE__)) . '/languages/');

/**
 * Plugin page links
 */
function wc_auctioninc_plugin_links($links) {

    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&section=wc_shipping_auctioninc') . '">' . __('Settings', 'wc_auctioninc') . '</a>',
        '<a href="http://auctioninc.helpserve.com">' . __('Support', 'wc_auctioninc') . '</a>',
        '<a href="http://www.auctioninc.com/info/page/woocommerce_api_module">' . __('Docs', 'wc_auctioninc') . '</a>',
    );

    return array_merge($plugin_links, $links);
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wc_auctioninc_plugin_links');

/**
 * Check if WooCommerce is active
 */
if (is_woocommerce_active()) {

    /**
     * woocommerce_init_shipping_table_rate function.
     *
     * @access public
     * @return void
     */
    function wc_auctioninc_init() {
        include_once('classes/class-wc-shipping-auctioninc.php');
    }

    add_action('woocommerce_shipping_init', 'wc_auctioninc_init');

    /**
     * wc_auctioninc_add_method function.
     *
     * @access public
     * @param mixed $methods
     * @return void
     */
    function wc_auctioninc_add_method($methods) {
        $methods[] = 'WC_Shipping_AuctionInc';
        return $methods;
    }

    add_filter('woocommerce_shipping_methods', 'wc_auctioninc_add_method');

    /**
     * wc_auctioninc_add_shipping_fields function.
     *
     * @access public
     * @return void
     */
    function wc_auctioninc_add_shipping_fields() {
        echo '<p><strong>' . __('AuctionInc Shipping Options', 'wc_auctioninc') . '</strong></p>';
        echo '<p><a href="http://www.auctioninc.com/info/page/auctioninc_shipping_settings" target="_blank">' . __('AuctionInc Help', 'wc_auctioninc') . '</a></p>';

        // Current Product
        global $post;

        // Default Values
        $auctioninc_settings = get_option('woocommerce_auctioninc_settings');

        $calc_method = get_post_meta($post->ID, 'auctioninc_calc_method', true);
        $calc_method = !empty($calc_method) ? $calc_method : $auctioninc_settings['calc_method'];

        $package = get_post_meta($post->ID, 'auctioninc_pack_method', true);
        $package = !empty($package) ? $package : $auctioninc_settings['package'];
        $package = !empty($package) ? $package : 'T';

        $insurable = get_post_meta($post->ID, 'auctioninc_insurable', true);
        $insurable = !empty($insurable) ? $insurable : $auctioninc_settings['insurance'];

        $fixed_mode = get_post_meta($post->ID, 'auctioninc_fixed_mode', true);
        $fixed_mode = !empty($fixed_mode) ? $fixed_mode : $auctioninc_settings['fixed_mode'];

        $fixed_code = get_post_meta($post->ID, 'auctioninc_fixed_code', true);
        $fixed_code = !empty($fixed_code) ? $fixed_code : $auctioninc_settings['fixed_code'];

        $fixed_fee_1 = get_post_meta($post->ID, 'auctioninc_fixed_fee_1', true);
        $fixed_fee_1 = !empty($fixed_fee_1) ? $fixed_fee_1 : $auctioninc_settings['fixed_fee_1'];

        $fixed_fee_2 = get_post_meta($post->ID, 'auctioninc_fixed_fee_1', true);
        $fixed_fee_2 = !empty($fixed_fee_2) ? $fixed_fee_2 : $auctioninc_settings['fixed_fee_2'];

        // Calculation Method
        woocommerce_wp_select(
                array(
                    'id' => 'auctioninc_calc_method',
                    'label' => __('Calculation Method', 'wc_auctioninc'),
                    'options' => array(
                        '' => __('-- Select -- ', 'wc_auctioninc'),
                        'C' => __('Carrier Rates', 'wc_auctioninc'),
                        'F' => __('Fixed Fee', 'wc_auctioninc'),
                        'N' => __('Free', 'wc_auctioninc')
                    ),
                    'value' => $calc_method,
                    'desc_tip' => 'true',
                    'description' => __('Select base calculation method. Please consult the AuctionInc Help Guide for more information.', 'wc_auctioninc')
                )
        );

        // Fixed Mode
        woocommerce_wp_select(
                array(
                    'id' => 'auctioninc_fixed_mode',
                    'label' => __('Fixed Mode', 'wc_auctioninc'),
                    'options' => array(
                        '' => __('-- Select -- ', 'wc_auctioninc'),
                        'code' => __('Code', 'wc_auctioninc'),
                        'fee' => __('Fee', 'wc_auctioninc')
                    ),
                    'value' => $fixed_mode
                )
        );

        // Fixed Fee Code
        woocommerce_wp_text_input(
                array(
                    'id' => 'auctioninc_fixed_code',
                    'label' => __('Fixed Fee Code', 'wc_auctioninc'),
                    'placeholder' => '',
                    'value' => $fixed_code,
                    'desc_tip' => 'true',
                    'description' => __('Enter your AuctionInc-configured fixed fee code.', 'wc_auctioninc')
                )
        );

        // Fixed Fee 1
        woocommerce_wp_text_input(
                array(
                    'id' => 'auctioninc_fixed_fee_1',
                    'label' => __('Fixed Fee 1', 'wc_auctioninc'),
                    'placeholder' => '0.00',
                    'type' => 'number',
                    'custom_attributes' => array(
                        'step' => '0.01',
                        'min' => '0'
                    ),
                    'value' => $fixed_fee_1,
                    'desc_tip' => 'true',
                    'description' => __('Enter fee for first item.', 'wc_auctioninc')
                )
        );

        // Fixed Fee 2
        woocommerce_wp_text_input(
                array(
                    'id' => 'auctioninc_fixed_fee_2',
                    'label' => __('Fixed Fee 2', 'wc_auctioninc'),
                    'placeholder' => '0.00',
                    'type' => 'number',
                    'custom_attributes' => array(
                        'step' => '0.01',
                        'min' => '0'
                    ),
                    'value' => $fixed_fee_2,
                    'desc_tip' => 'true',
                    'description' => __('Enter fee for additional items and quantities.', 'wc_auctioninc')
                )
        );

        // Packaging Method
        woocommerce_wp_select(
                array(
                    'id' => 'auctioninc_pack_method',
                    'label' => __('Package', 'wc_auctioninc'),
                    'options' => array(
                        'T' => __('Together', 'wc_auctioninc'),
                        'S' => __('Separately', 'wc_auctioninc')
                    ),
                    'value' => $package,
                    'desc_tip' => 'true',
                    'description' => __('Select "Together" for items that can be packed in the same box with other items from the same origin.', 'wc_auctioninc')
                )
        );

        // Insurable
        woocommerce_wp_checkbox(
                array(
                    'id' => 'auctioninc_insurable',
                    'label' => __('Insurable', 'wc_auctioninc'),
                    'desc_tip' => 'true',
                    'value' => $insurable,
					'desc_tip' => 'true',
                    'description' => __('Include product value for insurance calculation based on AuctionInc settings.', 'wc_auctioninc')
                )
        );

        // Origin Code
        woocommerce_wp_text_input(
                array(
                    'id' => 'auctioninc_origin_code',
                    'label' => __('Origin Code', 'wc_auctioninc'),
                    'placeholder' => __('default', 'wc_auctioninc'),
                    'desc_tip' => 'true',
                    'description' => __('If item is not shipped from your default AuctionInc location, enter your AuctionInc origin code here.', 'wc_auctioninc')
                )
        );

        // Supplemental Item Handling Mode
        woocommerce_wp_select(
                array(
                    'id' => 'auctioninc_supp_handling_mode',
                    'label' => __('Supplemental Item Handling Mode', 'wc_auctioninc'),
                    'options' => array(
                        '' => __('-- Select -- ', 'wc_auctioninc'),
                        'code' => __('Code', 'wc_auctioninc'),
                        'fee' => __('Fee', 'wc_auctioninc')
                    ),
                    'desc_tip' => 'true',
                    'description' => __('Supplements your AuctionInc-configured package and order handling for this item.', 'wc_auctioninc')
                )
        );

        // Supplemental Item Handling Code
        woocommerce_wp_text_input(
                array(
                    'id' => 'auctioninc_supp_handling_code',
                    'label' => __('Supplemental Item Handling Code', 'wc_auctioninc'),
                    'placeholder' => '',
                    'desc_tip' => 'true',
                    'description' => __('Enter your AuctionInc-configured Supplemental Handling Code.', 'wc_auctioninc')
                )
        );

        // Supplemental Item Handling Fee
        woocommerce_wp_text_input(
                array(
                    'id' => 'auctioninc_supp_handling_fee',
                    'label' => __('Supplemental Item Handling Fee', 'wc_auctioninc'),
                    'placeholder' => '0.00',
                    'type' => 'number',
                    'custom_attributes' => array(
                        'step' => '0.1',
                        'min' => '0'
                    )
                )
        );

        // On-Demand Service Codes
        woocommerce_wp_multi_select(
                array(
                    'id' => 'auctioninc_ondemand_codes',
                    'label' => __('On-Demand Service Codes', 'wc_auctioninc'),
                    'class' => 'select',
                    'options' => array(
                        'DHLWPE' => __('DHL Worldwide Priority Express', 'wc_auctioninc'),
                        'DHL9AM' => __('DHL Express 9 A.M.', 'wc_auctioninc'),
                        'DHL10AM' => __('DHL Express 10:30 A.M.', 'wc_auctioninc'),
                        'DHL12PM' => __('DHL Express 12 P.M.', 'wc_auctioninc'),
                        'FDX2D' => __('FedEx 2 Day', 'wc_auctioninc'),
                        'FDX2DAM' => __('FedEx 2 Day AM', 'wc_auctioninc'),
                        'FDXES' => __('FedEx Express Saver', 'wc_auctioninc'),
                        'FDXFO' => __('FedEx First Overnight', 'wc_auctioninc'),
                        'FDXPO' => __('FedEx Priority Overnight', 'wc_auctioninc'),
                        'FDXPOS' => __('FedEx Priority Overnight Saturday Delivery', 'wc_auctioninc'),
                        'FDXSO' => __('FedEx Standard Overnight', 'wc_auctioninc'),
                        'FDXGND' => __('FedEx Ground', 'wc_auctioninc'),
                        'FDXHD' => __('FedEx Home Delivery', 'wc_auctioninc'),
                        'FDXIGND' => __('FedEx International Ground', 'wc_auctioninc'),
                        'FDXIE' => __('FedEx International Economy', 'wc_auctioninc'),
                        'FDXIF' => __('FedEx International First', 'wc_auctioninc'),
                        'FDXIP' => __('FedEx International Priority', 'wc_auctioninc'),
                        'UPSNDA' => __('UPS Next Day Air', 'wc_auctioninc'),
                        'UPSNDE' => __('UPS Next Day Air Early AM', 'wc_auctioninc'),
                        'UPSNDAS' => __('UPS Next Day Air Saturday Delivery', 'wc_auctioninc'),
                        'UPSNDS' => __('UPS Next Day Air Saver', 'wc_auctioninc'),
                        'UPS2DE' => __('UPS 2 Day Air AM', 'wc_auctioninc'),
                        'UPS2ND' => __('UPS 2nd Day Air', 'wc_auctioninc'),
                        'UPS3DS' => __('UPS 3 Day Select', 'wc_auctioninc'),
                        'UPSGND' => __('UPS Ground', 'wc_auctioninc'),
                        'UPSCAN' => __('UPS Standard', 'wc_auctioninc'),
                        'UPSWEX' => __('UPS Worldwide Express', 'wc_auctioninc'),
                        'UPSWSV' => __('UPS Worldwide Saver', 'wc_auctioninc'),
                        'UPSWEP' => __('UPS Worldwide Expedited', 'wc_auctioninc'),
                        'USPFC' => __('USPS First-Class Mail', 'wc_auctioninc'),
                        'USPEXP' => __('USPS Priority Express', 'wc_auctioninc'),
                        'USPLIB' => __('USPS Library', 'wc_auctioninc'),
                        'USPMM' => __('USPS Media Mail', 'wc_auctioninc'),
                        'USPPM' => __('USPS Priority', 'wc_auctioninc'),
                        'USPPP' => __('USPS Standard Post', 'wc_auctioninc'),
                        'USPFCI' => __('USPS First Class International', 'wc_auctioninc'),
                        'USPPMI' => __('USPS Priority Mail International', 'wc_auctioninc'),
                        'USPEMI' => __('USPS Priority Express Mail International', 'wc_auctioninc'),
                        'USPGXG' => __('USPS Global Express Guaranteed', 'wc_auctioninc')
                    ),
                    'desc_tip' => 'true',
                    'description' => __('Select any AuctionInc configured on-demand services for which this item is eligible. Hold [Ctrl] key for multiple selections.', 'wc_auctioninc')
                )
        );

        // Special Accessorial Fees
        woocommerce_wp_multi_select(
                array(
                    'id' => 'auctioninc_access_fees',
                    'label' => __('Special Accessorial Fees', 'wc_auctioninc'),
                    'class' => 'select',
                    'options' => array(
                        'AddlHandling' => __('Additional Handling Charge, All Carriers', 'wc_auctioninc'),
                        'AddlHandlingUPS' => __('Additional Handling Charge, UPS', 'wc_auctioninc'),
                        'AddlHandlingDHL' => __('Additional Handling Charge, DHL', 'wc_auctioninc'),
                        'AddlHandlingFDX' => __('Additional Handling Charge, FedEx', 'wc_auctioninc'),
                        'Hazard' => __('Hazardous Charge, All Carriers', 'wc_auctioninc'),
                        'HazardUPS' => __('Hazardous Charge, UPS', 'wc_auctioninc'),
                        'HazardDHL' => __('Hazardous Charge, DHL', 'wc_auctioninc'),
                        'HazardFDX' => __('Hazardous Charge, FedEx', 'wc_auctioninc'),
                        'SignatureReq' => __('Signature Required Charge, All Carriers', 'wc_auctioninc'),
                        'SignatureReqUPS' => __('Signature Required Charge, UPS', 'wc_auctioninc'),
                        'SignatureReqDHL' => __('Signature Required Charge, DHL', 'wc_auctioninc'),
                        'SignatureReqFDX' => __('(Indirect) Signature Required  Charge, FedEx', 'wc_auctioninc'),
                        'SignatureReqUSP' => __('Signature Required Charge, USPS', 'wc_auctioninc'),
                        'UPSAdultSignature' => __('Adult Signature Required Charge, UPS', 'wc_auctioninc'),
                        'DHLAdultSignature' => __('Adult Signature Required Charge, DHL', 'wc_auctioninc'),
                        'FDXAdultSignature' => __('Adult Signature Required Charge, FedEx', 'wc_auctioninc'),
                        'DHLPrefSignature' => __('Signature Preferred Charge, DHL', 'wc_auctioninc'),
                        'FDXDirectSignature' => __('(Direct) Signature Required  Charge, FedEx', 'wc_auctioninc'),
                        'FDXHomeCertain' => __('Home Date Certain Charge, FedEx Home Delivery', 'wc_auctioninc'),
                        'FDXHomeEvening' => __('Home Date Evening Charge, FedEx Home Delivery', 'wc_auctioninc'),
                        'FDXHomeAppmnt' => __('Home Appmt. Delivery Charge, FedEx Home Delivery', 'wc_auctioninc'),
                        'Pod' => __('Proof of Delivery Charge, All Carriers', 'wc_auctioninc'),
                        'PodUPS' => __('Proof of Delivery Charge, UPS', 'wc_auctioninc'),
                        'PodDHL' => __('Proof of Delivery Charge, DHL', 'wc_auctioninc'),
                        'PodFDX' => __('Proof of Delivery Charge, FedEx', 'wc_auctioninc'),
                        'PodUSP' => __('Proof of Delivery Charge, USPS', 'wc_auctioninc'),
                        'UPSDelivery' => __('Delivery Confirmation Charge, UPS', 'wc_auctioninc'),
                        'USPCertified' => __('Certified Delivery Charge, USPS', 'wc_auctioninc'),
                        'USPRestricted' => __('Restricted Delivery Charge, USPS', 'wc_auctioninc'),
                        'USPDelivery' => __('Delivery Confirmation Charge, USPS', 'wc_auctioninc'),
                        'USPReturn' => __('Return Receipt Charge, USPS', 'wc_auctioninc'),
                        'USPReturnMerchandise' => __('Return Receipt for Merchandise Charge, USPS', 'wc_auctioninc'),
                        'USPRegistered' => __('Registered Mail Charge, USPS', 'wc_auctioninc'),
                        'IrregularUSP' => __('Irregular Package Discount,USPS', 'wc_auctioninc')
                    ),
                    'desc_tip' => 'true',
                    'description' => __('Add preferred special carrier fees. Hold [Ctrl] key for multiple selections.', 'wc_auctioninc')
                )
        );
    }

    add_action('woocommerce_product_options_shipping', 'wc_auctioninc_add_shipping_fields');

    /**
     * wc_auctioninc_shipping_fields_save function.
     *
     * @access public
     * @param int $post_id
     * @return void
     */
    function wc_auctioninc_shipping_fields_save($post_id) {

        // Calculation Method
        $auctioninc_calc_method = $_POST['auctioninc_calc_method'];
        if (!empty($auctioninc_calc_method)) {
            update_post_meta($post_id, 'auctioninc_calc_method', esc_attr($auctioninc_calc_method));
        }
		else {
			delete_post_meta($post_id, 'auctioninc_calc_method');
		}		

        // Fixed Fee Mode
        $auctioninc_fixed_mode = $_POST['auctioninc_fixed_mode'];
        if (!empty($auctioninc_fixed_mode)) {
            update_post_meta($post_id, 'auctioninc_fixed_mode', esc_attr($auctioninc_fixed_mode));
        }
		else {
			delete_post_meta($post_id, 'auctioninc_fixed_mode');
		}
		
        // Fixed Fee Code
        $auctioninc_fixed_code = $_POST['auctioninc_fixed_code'];
        if (!empty($auctioninc_fixed_code)) {
            update_post_meta($post_id, 'auctioninc_fixed_code', esc_attr($auctioninc_fixed_code));
        }
		else {
			delete_post_meta($post_id, 'auctioninc_fixed_code');
		}
		
        // Fixed Fee 1
        $auctioninc_fixed_fee_1 = $_POST['auctioninc_fixed_fee_1'];
        if (!empty($auctioninc_fixed_fee_1)) {
            update_post_meta($post_id, 'auctioninc_fixed_fee_1', esc_attr($auctioninc_fixed_fee_1));
        }
		else {
			delete_post_meta($post_id, 'auctioninc_fixed_fee_1');
		}
		
        // Fixed Fee 2
        $auctioninc_fixed_fee_2 = $_POST['auctioninc_fixed_fee_2'];
        if (!empty($auctioninc_fixed_fee_2)) {
            update_post_meta($post_id, 'auctioninc_fixed_fee_2', esc_attr($auctioninc_fixed_fee_2));
        }
		else {
			delete_post_meta($post_id, 'auctioninc_fixed_fee_2');
		}
		

        // Packaging Method
        $auctioninc_pack_method = $_POST['auctioninc_pack_method'];
        if (!empty($auctioninc_pack_method)) {
            update_post_meta($post_id, 'auctioninc_pack_method', esc_attr($auctioninc_pack_method));
        }
		else {
			delete_post_meta($post_id, 'auctioninc_pack_method');
		}

        // Insurable
        $auctioninc_insurable = isset($_POST['auctioninc_insurable']) ? 'yes' : 'no';
        update_post_meta($post_id, 'auctioninc_insurable', $auctioninc_insurable);

        // Origin Code
        $auctioninc_origin_code = $_POST['auctioninc_origin_code'];
        if (!empty($auctioninc_origin_code)) {
            update_post_meta($post_id, 'auctioninc_origin_code', esc_attr($auctioninc_origin_code));
        }
		else {
			delete_post_meta($post_id, 'auctioninc_origin_code');
		}

        // Supplemental Item Handling Mode
        $auctioninc_supp_handling_mode = $_POST['auctioninc_supp_handling_mode'];
        if (!empty($auctioninc_supp_handling_mode)) {
            update_post_meta($post_id, 'auctioninc_supp_handling_mode', esc_attr($auctioninc_supp_handling_mode));
        }
		else {
			delete_post_meta($post_id, 'auctioninc_supp_handling_mode');
		}
		
        // Supplemental Item Handling Code
        $auctioninc_supp_handling_code = $_POST['auctioninc_supp_handling_code'];
        if (!empty($auctioninc_supp_handling_code)) {
            update_post_meta($post_id, 'auctioninc_supp_handling_code', esc_attr($auctioninc_supp_handling_code));
        }
		else {
			delete_post_meta($post_id, 'auctioninc_supp_handling_code');
		}
		
        // Supplemental Item Handling Fee
        $auctioninc_supp_handling_fee = $_POST['auctioninc_supp_handling_fee'];
        if (!empty($auctioninc_supp_handling_fee)) {
            update_post_meta($post_id, 'auctioninc_supp_handling_fee', esc_attr($auctioninc_supp_handling_fee));
        }
		else {
			delete_post_meta($post_id, 'auctioninc_supp_handling_fee');
		}

        // On-Demand Service Codes
        $auctioninc_ondemand_codes = $_POST['auctioninc_ondemand_codes'];
        if (!empty($auctioninc_ondemand_codes)) {
            update_post_meta($post_id, 'auctioninc_ondemand_codes', $auctioninc_ondemand_codes);
        }
		else {
			delete_post_meta($post_id, 'auctioninc_ondemand_codes');
		}

        // Special Accessorial Fees
        $auctioninc_access_fees = $_POST['auctioninc_access_fees'];
        if (!empty($auctioninc_access_fees)) {
            update_post_meta($post_id, 'auctioninc_access_fees', $auctioninc_access_fees);
        }
		else {
			delete_post_meta($post_id, 'auctioninc_access_fees');
		}
    }

    add_action('woocommerce_process_product_meta', 'wc_auctioninc_shipping_fields_save');

    /**
     * wc_auctioninc_scripts function.
     *
     * @access public
     * @return void
     */
    function wc_auctioninc_scripts() {
        $screen = get_current_screen();
        if ($screen->base == 'post') {
            wp_enqueue_script('admin-auctioninc', plugins_url('admin/js/shipping-auctioninc-product.js', __FILE__), array('jquery'), null, true);
        } elseif ($screen->base == 'woocommerce_page_wc-settings') {
            wp_enqueue_script('admin-auctioninc', plugins_url('admin/js/shipping-auctioninc-settings.js', __FILE__), array('jquery'), null, true);
        }
    }

    add_action('admin_enqueue_scripts', 'wc_auctioninc_scripts');

    /**
     * wc_auctioninc_notify_api function.
     *
     * @access public
     * @return void
     */
    function wc_auctioninc_update_order($order_id) {

        global $woocommerce;

        // Get current order
        $current_order = new WC_Order($order_id);

        // Get order info for API call
        $total = $current_order->get_total();
        $shipping_total = $current_order->get_total_shipping();
        $shipping_method = $current_order->get_shipping_method();
        $shipping_country = !empty($current_order->shipping_country) ? $current_order->shipping_country : '';
        $shipping_postcode = !empty($current_order->shipping_postcode) ? $current_order->shipping_postcode : '';

        // Get shipping data from user's session
        $ship_rates = $woocommerce->session->auctioninc_response;
        foreach ($ship_rates['ShipRate'] as $ship_rate) {
            if ($ship_rate['ServiceName'] == $shipping_method) {
                update_post_meta($order_id, 'auctioninc_order_shipping_meta', $ship_rate);
                break;
            }
        }

        // Delete session data
        unset($woocommerce->session->auctioninc_response);

        // Module settings
        /*$auctioninc_settings = get_option('woocommerce_auctioninc_settings');

        // Send API call if shipping module is active
        if (!empty($auctioninc_settings['account_id']) && $auctioninc_settings['enabled'] == 'yes') {
            $post_data = array(
                'auctioninc_id' => $auctioninc_settings['account_id'],
                'order_id' => $order_id,
                'platform' => 'WooCommerce',
                'site_url' => site_url(),
                'order_total' => doubleval($total) - doubleval($shipping_total),
                'shipping_total' => doubleval($shipping_total),
                'shipping_service' => $shipping_method,
                'shipping_country' => $shipping_country,
                'shipping_postcode' => $shipping_postcode
            );

            error_log(print_r($post_data, true), 3, "/home2/projects/www/auctioninc/auction_log");

            $response = wp_remote_post('http://projects.45press.com/auctioninc-console/public/api/v1/order', array(
                'method' => 'POST',
                'headers' => array('Content-Type' => 'application/json', 'Authorization' => 'Basic ' . base64_encode('admin@45press.com:meatball360')),
                'body' => json_encode($post_data)
            ));

            error_log(print_r($response, true), 3, "/home2/projects/www/auctioninc/auction_log");
        }*/
    }

    add_action('woocommerce_checkout_order_processed', 'wc_auctioninc_update_order');	

    /**
     * wc_auctioninc_admin_notice function.
     *
     * @access public
     * @return void
     */
    function wc_auctioninc_admin_notice() {

        $auctioninc_settings = get_option('woocommerce_auctioninc_settings');

        if (empty($auctioninc_settings['account_id'])) {
            echo '<div class="error">
             <p>' . __('An') . ' <a href="http://www.auctioninc.com/info/page/woocommerce_api_module" target="_blank">' . __('AuctionInc', 'wc_auctioninc') . '</a> ' . __('account is required to use this plugin.', 'wc_auctioninc') . '</p>
         </div>';
        }
    }

    add_action('admin_notices', 'wc_auctioninc_admin_notice');
}
