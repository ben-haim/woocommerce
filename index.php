<?php
/**
 * Plugin Name: GatePay
 * Plugin URI: https://www.gatepay.co
 * Description: Create Invoices and process through GatePay. 
 * Author: GatePay
 * Author URI: mailto:support@gatepay.co?subject=GateWay
 */
if (!defined('ABSPATH')): exit;endif;
define( 'GATEPAY__PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GATEWAY__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
global $current_user;
require_once("includes.php");
#autoloader
function GPC_autoloader($class)
{
    if (strpos($class, 'GPC_') !== false):
        if (!class_exists('lib/' . $class, false)):
            #doesnt exist so include it
            include 'lib/' . $class . '.php';
        endif;
    endif;
}

function GPC_Logger($msg, $type = null, $isJson = false, $error = false)
{
    $gatepay_checkout_options = get_option('woocommerce_gatepay_checkout_gateway_settings');
    $transaction_log = plugin_dir_path(__FILE__) . 'logs/' . date('Ymd') . '_transactions.log';
    $error_log = plugin_dir_path(__FILE__) . 'logs/' . date('Ymd') . '_error.log';

    $header = PHP_EOL . '======================' . $type . '===========================' . PHP_EOL;
    $footer = PHP_EOL . '=================================================' . PHP_EOL;

    if ($error):
        error_log($header, 3, $error_log);
        error_log($msg, 3, $error_log);
        error_log($footer, 3, $error_log);
    else:
        if ($gatepay_checkout_options['gatepay_log_mode'] == 1):
            error_log($header, 3, $transaction_log);
            if ($isJson):
                error_log(print_r($msg, true), 3, $transaction_log);
            else:
                error_log($msg, 3, $transaction_log);
            endif;
            error_log($footer, 3, $transaction_log);
        endif;
    endif;
}

spl_autoload_register('GPC_autoloader');

#check and see if requirements are met for turning on plugin
function _isCurl2()
{
    return function_exists('curl_version');
}

function gatepay_checkout_woocommerce_gatepay_failed_requirements()
{
    global $wp_version;
    global $woocommerce;
    $errors = array();

    // WooCommerce required
    if (true === empty($woocommerce)) {
        $errors[] = 'The WooCommerce plugin for WordPress needs to be installed and activated. Please contact your web server administrator for assistance.';
    } elseif (true === version_compare($woocommerce->version, '2.2', '<')) {
        $errors[] = 'Your WooCommerce version is too old. The GatePay payment plugin requires WooCommerce 2.2 or higher to function. Your version is ' . $woocommerce->version . '. Please contact your web server administrator for assistance.';
    } elseif (!_isCurl2()) {
        $errors[] = 'cUrl needs to be installed/enabled for GatePay Checkout to function';
    }
    if (empty($errors)):
        return false;
    else:
        return implode("<br>\n", $errors);
    endif;
}

add_action('plugins_loaded', 'wc_gatepay_checkout_gateway_init', 11);
#create the table if it doesnt exist
function gatepay_checkout_plugin_setup()
{

    $failed = gatepay_checkout_woocommerce_gatepay_failed_requirements();
    $plugins_url = admin_url('plugins.php');
	if( !wp_next_scheduled( 'gatewapyjob' ) ) {  
	   wp_schedule_event( time(), 'every_teen_seconds', 'gatewapyjob' );  
	}
    if ($failed === false) {

        global $wpdb;
        $table_name = '_gatepay_checkout_transactions';

        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table_name(
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `order_id` int(11) NOT NULL,
        `transaction_id` varchar(255) NOT NULL,
        `transaction_status` varchar(50) NOT NULL DEFAULT 'new',
        `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        #check out of date plugins
        $plugins = get_plugins();
        foreach ($plugins as $file => $plugin) {
            if ('GatePay Woocommerce' === $plugin['Name'] && true === is_plugin_active($file)) {
                deactivate_plugins(plugin_basename(__FILE__));
                wp_die('GatePay for WooCommerce requires that the old plugin, <b>GatePay Woocommerce</b>, is deactivated and deleted.<br><a href="' . $plugins_url . '">Return to plugins screen</a>');
            }
        }

    } else {

        // Requirements not met, return an error message
        wp_die($failed . '<br><a href="' . $plugins_url . '">Return to plugins screen</a>');

    }

}
register_activation_hook(__FILE__, 'gatepay_checkout_plugin_setup');



function gatepay_checkout_update_order_note($order_id = null, $transaction_id = null, $transaction_status = null)
{
    global $wpdb;
    $table_name = '_gatepay_checkout_transactions';
    if ($order_id != null && $transaction_id != null && $transaction_status != null):
        $wpdb->update($table_name, array('transaction_status' => $transaction_status), array("order_id" => $order_id, 'transaction_id' => $transaction_id));
    else:
        GPC_Logger('Missing values' . PHP_EOL . 'order id: ' . $order_id . PHP_EOL . 'transaction id: ' . $transaction_id . PHP_EOL . 'transaction status: ' . $transaction . PHP_EOL, 'error', false, true);
    endif;
}

function gatepay_checkout_get_order_transaction($order_id, $transaction_id)
{
    global $wpdb;
    $table_name = '_gatepay_checkout_transactions';
    $rowcount = $wpdb->get_var("SELECT COUNT(order_id) FROM $table_name WHERE order_id = '$order_id'
    AND transaction_id = '$transaction_id' LIMIT 1");
    return $rowcount;

}

function gatepay_checkout_delete_order_transaction($order_id)
{
    global $wpdb;
    $table_name = '_gatepay_checkout_transactions';
    $wpdb->query("DELETE FROM $table_name WHERE order_id = '$order_id'");

}

function wc_gatepay_checkout_gateway_init()
{
    if (class_exists('WC_Payment_Gateway')) {
        class WC_Gateway_GatePay extends WC_Payment_Gateway
        {

            public function __construct()
            {
                $gatepay_checkout_options = get_option('woocommerce_gatepay_checkout_gateway_settings');

                $this->id = 'gatepay_checkout_gateway';
                $this->icon = $this->get_option('logo') ;

                $this->has_fields = true;
                $this->method_title = __(GPC_getBitPayVersionInfo($clean = true), 'wc-gatepay');
                $this->method_label = __('GatePay', 'wc-gatepay');
                $this->method_description = __('Expand your payment options by accepting instant BTC payments without risk or price fluctuations.', 'wc-gatepay');

                if (empty($_GET['woo-gatepay-return'])) {
                    $this->order_button_text = __('Pay with GatePay', 'woocommerce-gateway-gatepay_checkout_gateway');

                }
                // Load the settings.
                $this->init_form_fields();
                $this->init_settings();

                // Define user set variables
                $this->title = $this->get_option('title');
                $this->description = $this->get_option('description') . '<br>';
                $this->instructions = $this->get_option('instructions', $this->description);

                // Actions
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

                // Customer Emails
                add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
            }
            public function email_instructions($order, $sent_to_admin, $plain_text = false)
            {
                if ($this->instructions && !$sent_to_admin && 'gatepay_checkout_gateway' === $order->get_payment_method() && $order->has_status('processing')) {
                    echo wp_kses_post(wpautop(wptexturize($this->instructions)) . PHP_EOL);
                }
            }
            public function init_form_fields()
            {
				//gatewapyjob_function();
				global $Network_Cryptocurrencies;
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Enable/Disable', 'woocommerce'),
                        'label' => __('Enable GatePay', 'woocommerce'),
                        'type' => 'checkbox',
                        'description' => '',
                        'default' => 'no',
                    ),
                  

                    'gatepay_checkout_merchant_info' => array(
                        'description' => __('If you have not created a GatePay Merchant Token, you can create one on your GatePay Dashboard.<br>', 'woocommerce'),
                        'type' => 'title',
                    ),

                    'gatepay_checkout_tier_info' => array(
                        'description' => __('<em><b>*** </b>If you are having trouble creating GatePay invoices, verify your Tier settings on your <a href = "https://www.gatepay.co/" target = "_blank">GatePay Dashboard</a>.</em>', 'woocommerce'),
                        'type' => 'title',
                    ),
                    'title' => array(
                        'title' => __('Title', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                        'default' => __('GatePay', 'woocommerce'),

                    ),
                    'description' => array(
                        'title' => __('Description', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('This is the message box that will appear on the <b>checkout page</b> when they select GatePay.', 'woocommerce'),
                        'default' => 'Pay with GatePay using one of the supported cryptocurrencies',

                    ),
					 'logo' => array(
                        'title' => __('Logo', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('icon of the payment gateway.', 'woocommerce'),
                        'default' =>  'https://static.wixstatic.com/media/209249_43f4129315444d7ca8b6cc344de50fc0~mv2.png/v1/fill/w_139,h_39,al_c,q_80,usm_0.66_1.00_0.01/gpy.webp'

                    ),
                     'gatepay_checkout_account_id' => array(
                        'title' => __('Account ID', 'woocommerce'),
                        'label' => __('Account ID', 'woocommerce'),
                        'type' => 'text',
                        'description' => 'Your <b>account ID</b>',
                        'default' => '',

                    ),
                    'gatepay_checkout_token' => array(
                        'title' => __('Token', 'woocommerce'),
                        'label' => __('Token', 'woocommerce'),
                        'type' => 'text',
                        'description' => 'Your <b>token </b>',
                        'default' => '',

                    ),
					
					'BTC_GatePay' => array(
                        'title' => __('Network Cryptocurrency', 'woocommerce'),
                        'label' => __('BTC (Bitcoin Mainnet)', 'woocommerce'),
                        'type' => 'checkbox',
                        'description' => '',
                        'default' => '1',
                    ),
                   'BCH_GatePay' => array(
                        'title' => "",
                        'label' => __('BCH (Bitcoin Cash Mainnet)', 'woocommerce'),
                        'type' => 'checkbox',
                        'description' => '',
                        'default' => '0',
                    ),
					 'ETH_GatePay' => array(
                        'title' => "",
                        'label' => __('ETH (Ethereum Mainnet)', 'woocommerce'),
                        'type' => 'checkbox',
                        'description' => '',
                        'default' => '0',
                    ),
					 'LTC_GatePay' => array(
                        'title' => "",
                        'label' => __('LTC (Litecoin Mainnet)', 'woocommerce'),
                        'type' => 'checkbox',
                        'description' => '',
                        'default' => '0',
                    ),
					 'DASH_GatePay' => array(
                        'title' => "",
                        'label' => __('DASH (Dash Mainnet)', 'woocommerce'),
                        'type' => 'checkbox',
                        'description' => '',
                        'default' => '0',
                    ),
					 'DOGE_GatePay' => array(
                        'title' => "",
                        'label' => __('DOGE (Dogecoin Mainnet)', 'woocommerce'),
                        'type' => 'checkbox',
                        'description' => '',
                        'default' => '0',
                    ),
					 'BSV_GatePay' => array(
                        'title' => "",
                        'label' => __('BSV (Bitcoin SV Mainnet)', 'woocommerce'),
                        'type' => 'checkbox',
                        'description' => '',
                        'default' => '0',
                    ),
					
				
              
                  

                    'gatepay_checkout_checkout_message' => array(
                        'title' => __('Checkout Message', 'woocommerce'),
                        'type' => 'textarea',
                        'description' => __('Insert your custom message for the <b>Order Received</b> page, so the customer knows that the order will not be completed until GatePay releases the funds.', 'woocommerce'),
                        'default' => 'To confirm your order, please send the exact amount of <strong>BTC</strong> to the given address',
                    ),
					'woocommerce_gatepay_checkout_gateway_thank_you' => array(
                        'title' => __('Thank you Message', 'woocommerce'),
                        'type' => 'textarea',
                        'description' => __('Payment message after order is completed.', 'woocommerce'),
                        'default' => '<h4>Payment Method:<strong> GatePay</strong></h4><br><p>We received your crypto payment.</p>',
                    ),

                    'gatepay_checkout_order_paid' => array(
                        'title' => __('Paid Status', 'woocommerce'),
                        'type' => 'select',
                        'description' => __('Customize the order status when a user intially <b>Pays</b> the invoice.  This step is not yet confirmed on the blockchain, but has received a crytpocurrency payment.  Defaults to <b>Processing</b>.', 'woocommerce'),
                        'options' => $this->GPC_getOrderStatus(),
                        'default' => 'wc-processing',
                    ),
                    'gatepay_checkout_order_confirmed' => array(
                        'title' => __('Confirmed Status', 'woocommerce'),
                        'type' => 'select',
                        'description' => __('The payment has been <b></b>confirmed</b> based on transaction speeds.  Defaults to <b>Completed</b>.', 'woocommerce'),
                        'options' => $this->GPC_getOrderStatus(),
                        'default' => 'wc-completed',
                    ),
					 'gatepay_checkout_expire_minutes' => array(
                        'title' => __('Order expired after (Minutes)', 'woocommerce'),
                        
                        'type' => 'text',
                        'description' => 'Number of minutes before an unpaid order become expired',
                        'default' => '10',

                    ),
                    'gatepay_checkout_auto_delete' => array(
                        'title' => __('Auto-Delete Expired Invoices/Orders', 'woocommerce'),
                        'type' => 'select',
                        'description' => __('If the invoice has <b>expired</b>, ie a user started the checkout but never completed payment, should the order be automatically removed during the IPN notification?  If set to <b>Yes</b> then the order will be completely removed.  If set to <b>No</b> then the status will be set to <b>Cancelled</b>.  Defaults to <b>Yes</b>.', 'woocommerce'),
                        'options' => array(
                            '1' => 'Yes',
                            '0' => 'No',

                        ),
                        'default' => '1',
                    ),

                    'gatepay_log_mode' => array(
                        'title' => __('Developer Logging', 'woocommerce'),
                        'type' => 'select',
                        'description' => __('Errors will be logged to the plugin <b>log</b> directory automatically.  Set to <b>Enabled</b> to also log transactions, ie invoices and IPN updates', 'woocommerce'),
                        'options' => array(
                            '0' => 'Disabled',
                            '1' => 'Enabled',
                        ),
                        'default' => '1',
                    ),

                );
            }
            function GPC_getOrderStatus()
            {
                $order_statuses = wc_get_order_statuses();
                return apply_filters('wc_order_statuses', $order_statuses);

            }
            function process_payment($order_id)
            {
				
                #this is the one that is called intially when someone checks out
                global $woocommerce;
                $order = new WC_Order($order_id);
                // Return thankyou redirect
                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order),
                );
            }
        } // end \WC_Gateway_Offline class
    } //end check for class existence
    else {
            global $wpdb;
            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $plugins_url = admin_url('plugins.php');

            $plugins = get_plugins();
            foreach ($plugins as $file => $plugin) {

                if ('GatePay Checkout for WooCommerce' === $plugin['Name'] && true === is_plugin_active($file)) {

                    deactivate_plugins(plugin_basename(__FILE__));
                    wp_die('WooCommerce needs to be installed and activated before GatePay Checkout for WooCommerce can be activated.<br><a href="' . $plugins_url . '">Return to plugins screen</a>');

                }
            }

        }

    }

//this is an error message incase a token isnt set
    add_action('admin_notices', 'gatepay_checkout_check_token');
    function gatepay_checkout_check_token()
{
	return true;

        if ($_GET['section'] == 'gatepay_checkout_gateway' && $_POST && is_admin()):
            //lookup the token based on the environment
            $gatepay_checkout_options = get_option('woocommerce_gatepay_checkout_gateway_settings');
            //dev or prod token

            $gatepay_checkout_token = GPC_getBitPayToken($gatepay_checkout_options['gatepay_checkout_endpoint']);
            $gatepay_checkout_endpoint = $gatepay_checkout_options['gatepay_checkout_endpoint'];
            if (empty($gatepay_checkout_token)): ?>
		            <?php _e('There is no token set for your <b>' . strtoupper($gatepay_checkout_endpoint) . '</b> environment.  <b>GatePay</b> will not function if this is not set.');?>
		            <?php
        ##check and see if the token is valid
        else:
            if ($_POST && !empty($gatepay_checkout_token) && !empty($gatepay_checkout_endpoint)) {
                if (!GPC_isValidBitPayToken($gatepay_checkout_token, $gatepay_checkout_endpoint)): ?>
		            <div class="error notice">
		                <p>
		                    <?php _e('The token for <b>' . strtoupper($gatepay_checkout_endpoint) . '</b> is invalid.  Please verify your settings.');?>
		                </p>
		            </div>
		            <?php endif;
        }

    endif;

    endif;
}

#http://<host>/wp-json/gatepay/ipn/status
add_action('rest_api_init', function () { 
    register_rest_route('gatepay/ipn', '/status', array(
        'methods' => 'POST,GET',
        'callback' => 'gatepay_checkout_ipn',
    ));
    register_rest_route('gatepay/cartfix', '/restore', array(
        'methods' => 'POST,GET',
        'callback' => 'gatepay_checkout_cart_restore',
    ));
});

function gatepay_checkout_cart_restore(WP_REST_Request $request)
{
    WC()->frontend_includes();
    WC()->cart = new WC_Cart();
    WC()->session = new WC_Session_Handler();
    WC()->session->init();
    $data = $request->get_params();
    $order_id = $data['orderid'];
    $order = new WC_Order($order_id);
    $items = $order->get_items();

    GPC_Logger('User canceled order: ' . $order_id . ', removing from WooCommerce', 'USER CANCELED ORDER', true);

    //clear the cart first so things dont double up
    WC()->cart->empty_cart();
    foreach ($items as $item) {
        //now insert for each quantity
        $item_count = $item->get_quantity();
        for ($i = 0; $i < $item_count; $i++):
            WC()->cart->add_to_cart($item->get_product_id());
        endfor;
    }
    //delete the previous order
    wp_delete_post($order_id, true);
    gatepay_checkout_delete_order_transaction($order_id);
    setcookie("gatepay-invoice-id", "", time() - 3600);
}

function gatepay_checkout_ipn_status($status)
{
	
    global $woocommerce;
    $gatepay_checkout_options = get_option('woocommerce_gatepay_checkout_gateway_settings');
    switch ($status) {
        case 'invoice_confirmed':
            if ($gatepay_checkout_options['gatepay_checkout_order_confirmed'] == ''):
                return 'completed';
            else:
                return str_replace('wc-', '', $gatepay_checkout_options['gatepay_checkout_order_confirmed']);
            endif;
            break;
        case 'invoice_paidInFull':
            if ($gatepay_checkout_options['gatepay_checkout_order_paid'] == ''):
                return 'processing';
            else:
                return str_replace('wc-', '', $gatepay_checkout_options['gatepay_checkout_order_paid']);
            endif;
            break;

    }
}
//http://<host>/wp-json/gatepay/ipn/status
function gatepay_checkout_ipn(WP_REST_Request $request)
{

    global $woocommerce;
    WC()->frontend_includes();
    WC()->cart = new WC_Cart();
    WC()->session = new WC_Session_Handler();
    WC()->session->init();
    #$hash_key = $_REQUEST['hash_key'];
    $data = $request->get_body();

    $data = json_decode($data);
    $event = $data->event;
    $data = $data->data;

    $orderid = $data->orderId;
    $order_status = $data->status;
    $invoiceID = $data->id;
    GPC_Logger($data, 'INCOMING IPN', true);

    #check the hash to make sure it comes from the right place

    #verify the ipn matches the status of the actual invoice

    if (gatepay_checkout_get_order_transaction($orderid, $invoiceID)):
       $account_id=$gatepay_checkout_options['gatepay_checkout_account_id'];
		$token_id=$gatepay_checkout_options['gatepay_checkout_token'];
        //dev or prod token
		
        $gatepay_checkout_token = GPC_getBitPayToken($gatepay_checkout_options['gatepay_checkout_endpoint']);
        $config = new GPC_Configuration($account_id, $token_id);
        

        #verify the hash before moving on
        #disable this for awhile so new orders can start creating them
        #if(!$config->GPC_checkHash($orderid,$hash_key)):
        #    die();
        #endif;

        $params = new stdClass();
        $params->extension_version = GPC_getBitPayVersionInfo();
        $params->invoiceID = $invoiceID;

        $item = new GPC_Item($config, $params);

        $invoice = new GPC_Invoice($item); //this creates the invoice with all of the config params
        $orderStatus = json_decode($invoice->GPC_checkInvoiceStatus($invoiceID));
        #update the lookup table
        gatepay_checkout_update_order_note($orderid, $invoiceID, $order_status);
        switch ($event->name) {
            case 'invoice_confirmed':
                #if ($orderStatus->data->status == 'confirmed'):
                $order = new WC_Order($orderid);
                //private order note with the invoice id
                $order->add_order_note('GatePay Invoice ID: <a target = "_blank" href = "' . GPC_getBitPayDashboardLink($gatepay_checkout_endpoint, $invoiceID) . '">' . $invoiceID . '</a> processing has been completed.');

                $order_status = gatepay_checkout_ipn_status('invoice_confirmed');
                $order->update_status($order_status, __('GatePay payment ' . $order_status, 'woocommerce'));
                // Reduce stock levels
                $order->reduce_order_stock();

                // Remove cart
                WC()->cart->empty_cart();
                #endif;
                break;

            case 'invoice_paidInFull': #pending
                # if ($orderStatus->data->status == 'paid'):
                $order = new WC_Order($orderid);
                //private order note with the invoice id
                $order->add_order_note('GatePay Invoice ID: <a target = "_blank" href = "' . GPC_getBitPayDashboardLink($gatepay_checkout_endpoint, $invoiceID) . '">' . $invoiceID . '</a> is processing.');

                $order_status = gatepay_checkout_ipn_status('invoice_paidInFull');
                $order->update_status($order_status, __('GatePay payment ' . $order_status, 'woocommerce'));
                # endif;
                break;

            case 'invoice_failedToConfirm':
                if ($orderStatus->data->status == 'invalid'):
                    $order = new WC_Order($orderid);
                    //private order note with the invoice id
                    $order->add_order_note('GatePay Invoice ID: <a target = "_blank" href = "' . GPC_getBitPayDashboardLink($gatepay_checkout_endpoint, $invoiceID) . '">' . $invoiceID . '</a> has become invalid because of network congestion.  Order will automatically update when the status changes.');

                    $order->update_status('failed', __('GatePay payment invalid', 'woocommerce'));
                endif;
                break;
            case 'invoice_expired':
                #if ($orderStatus->data->status == 'expired'):
                //delete the previous order
                $order = new WC_Order($orderid);
                if ($gatepay_checkout_options['gatepay_checkout_auto_delete'] == 1):
                    wp_delete_post($orderid, true);
                else:
                    $order->update_status('cancelled', __('GatePay cancelled the order.', 'woocommerce'));
                endif;
                #endif;
                break;

            case 'invoice_refundComplete':
                $order = new WC_Order($orderid);
                //private order note with the invoice id
                $order->add_order_note('GatePay Invoice ID: <a target = "_blank" href = "' . GPC_getBitPayDashboardLink($gatepay_checkout_endpoint, $invoiceID) . '">' . $invoiceID . ' </a> has been refunded.');

                $order->update_status('refunded', __('GatePay payment refunded', 'woocommerce'));
                break;
        }
        die();
    endif;
}

add_action('template_redirect', 'woo_custom_redirect_after_purchase2');
function woo_custom_redirect_after_purchase2()
{
	
    global $wp,$woocommerce;
    $gatepay_checkout_options = get_option('woocommerce_gatepay_checkout_gateway_settings');
    $account_id=$gatepay_checkout_options['gatepay_checkout_account_id'];
	$token_id=$gatepay_checkout_options['gatepay_checkout_token'];
	
    if (is_checkout() && !empty($wp->query_vars['order-received'])) {

        $order_id = $wp->query_vars['order-received'];
		
        try {
            $order = new WC_Order($order_id);

            //this means if the user is using gatepay AND this is not the redirect
            $show_gatepay = true;

            if (isset($_GET['redirect']) && $_GET['redirect'] == 'false'):
                $show_gatepay = false;
                $invoiceID = $_COOKIE['gatepay-invoice-id'];

                //clear the cookie
                setcookie("gatepay-invoice-id", "", time() - 3600);
            endif;

            if ($order->payment_method == 'gatepay_checkout_gateway' && $show_gatepay == true):
				unset($wp->query_vars['order-received']);
                $config = new GPC_Configuration($account_id, $token_id);
				
                //sample values to create an item, should be passed as an object'
                $params = new stdClass();
                $current_user = wp_get_current_user();
                #$params->fullNotifications = 'true';
                $params->extension_version = GPC_getBitPayVersionInfo();
                $params->price = $order->total;
                $params->currency = $order->currency; //set as needed
                if ($gatepay_checkout_options['gatepay_checkout_capture_email'] == 1):
                    $current_user = wp_get_current_user();

                    if ($current_user->user_email):
                        $buyerInfo = new stdClass();
                        $buyerInfo->name = $current_user->display_name;
                        $buyerInfo->email = $current_user->user_email;
                        $params->buyer = $buyerInfo;
                    endif;
                endif;

                //orderid
                $params->orderId = trim($order_id);
                //redirect and ipn stuff
                $checkout_slug = $gatepay_checkout_options['gatepay_checkout_slug'];
                if (empty($checkout_slug)):
                    $checkout_slug = 'checkout';
                endif;
                $params->redirectURL = get_home_url() . '/' . $checkout_slug . '/order-received/' . $order_id . '/?key=' . $order->order_key . '&redirect=false';

                #create a hash for the ipn
                $hash_key = $config->GPC_generateHash($params->orderId);

                $params->notificationURL = get_home_url() . '/wp-json/gatepay/ipn/status';
                #http://<host>/wp-json/gatepay/ipn/status
                $params->extendedNotifications = true;
				
                $item = new GPC_Item($config, $params);
                $invoice = new GPC_Invoice($item);
                //this creates the invoice with all of the config params from the item
                $invoice->GPC_createInvoice();
				global $Network_Cryptocurrencies;
				$allowedNetwors=array();
				$nb_allowed=0;
				foreach($Network_Cryptocurrencies as $crypt=>$val)
				{
					if(isset($gatepay_checkout_options['gatepay_checkout_'.$crypt])&& $gatepay_checkout_options['gatepay_checkout_'.$crypt]==1)
					{
						$nb_allowed++;
						$allowedNetwors[]=array($crypt=>$val);
						
					}
				}
				
				
                 $account_id= $gatepay_checkout_options['gatepay_checkout_account_id']; 
				$keyID= $gatepay_checkout_options['gatepay_checkout_token'];
                #GPC_Logger(json_decode($invoice->GPC_getInvoiceData()), 'NEW BITPAY INVOICE',true);


               
				
				
                #insert into the database
                //gatepay_checkout_insert_order_note($order_id, $invoiceID);
				$checkout_msg = $gatepay_checkout_options['gatepay_checkout_checkout_message'];
				$nb_minutes = $gatepay_checkout_options['gatepay_checkout_expire_minutes'];
				
				require_once("order.php");
				exit();
                
               
                 
            endif;
        } catch (Exception $e) {
            global $woocommerce;
            $cart_url = $woocommerce->cart->get_cart_url();
            wp_redirect($cart_url);
            exit;
        }
    }
}

// Replacing the Place order button when total volume exceed 68 m3
add_filter('woocommerce_order_button_html', 'gatepay_checkout_replace_order_button_html', 10, 2);
function gatepay_checkout_replace_order_button_html($order_button, $override = false)
{
    if ($override):
        return;
    else:
        return $order_button;
    endif;
}

function GPC_getBitPayVersionInfo($clean = null)
{
    $plugin_data = get_file_data(__FILE__, array('Version' => 'Version', 'Plugin_Name' => 'Plugin Name'), false);
    $plugin_name = $plugin_data['Plugin_Name'];
    if ($clean):
        $plugin_version = $plugin_name . ' ' . $plugin_data['Version'];
    else:
        $plugin_name = str_replace(" ", "_", $plugin_name);
        $plugin_name = str_replace("_for_", "_", $plugin_name);
        $plugin_version = $plugin_name . '_' . $plugin_data['Version'];
    endif;

    return $plugin_version;
}

#retrieves the invoice token based on the endpoint
function GPC_getBitPayDashboardLink($endpoint, $invoiceID)
{ //dev or prod token
    switch ($endpoint) {
        case 'test':
        default:
            return '//test.gatepay.com/dashboard/payments/' . $invoiceID;
            break;
        case 'production':
            return '//gatepay.com/dashboard/payments/' . $invoiceID;
            break;
    }
}

function GPC_getBitPayBrandOptions()
{

    if (is_admin() && $_GET['section'] == 'gatepay_checkout_gateway'):

        $buttonObj = new GPC_Buttons;
        $buttons = json_decode($buttonObj->GPC_getButtons());
        $output = [];
        foreach ($buttons->data as $key => $b):

            $names = preg_split('/(?=[A-Z])/', $b->name);
            $names = implode(" ", $names);
            $names = ucwords($names);
            if (strpos($names, "Donate") === 0):
                continue;
            else:
                $names = str_replace(" Button", "", $names);
                $output['//' . $b->url] = $names;
            endif;
        endforeach;
        #add a 'no image' option
        $output['-'] = 'No Image';

        return $output;
    endif;
}

#brand returned from API
function GPC_getBitPayBrands()
{
    if (is_admin() && $_GET['section'] == 'gatepay_checkout_gateway'):
        $buttonObj = new GPC_Buttons;
        $buttons = json_decode($buttonObj->GPC_getButtons());
        $brand = '<div>';
        foreach ($buttons->data as $key => $b):
            $names = preg_split('/(?=[A-Z])/', $b->name);
            $names = implode(" ", $names);
            $names = ucwords($names);

            if (strpos($names, "Donate") === 0):
                continue;
            else:
                $names = str_replace(" Button", "", $names);
                $brand .= '<figure style = "float:left;"><img src = "//' . $b->url . '"  style = "width:150px;padding:1px;">';
                $brand .= '<figcaption style = "text-align:left;font-style:italic"><b>' . $names . '</b><br>' . $b->description . '</figcaption>';
                $brand .= '</figure>';
            endif;
        endforeach;

        $brand .= '</div>';
        return $brand;
    endif;

}

function GPC_getBitPayLogo($endpoint = null)
{
    if (is_admin() && $_GET['section'] == 'gatepay_checkout_gateway'):
        $buttonObj = new GPC_Buttons;
        $buttons = $buttonObj->GPC_getButtons();
        $gatepay_checkout_options = get_option('woocommerce_gatepay_checkout_gateway_settings');
        $brand = $gatepay_checkout_options['gatepay_checkout_brand'];
        if ($brand == '-'):
            return null;
        elseif ($brand == ''):
            return $buttons[0];
        else:
            return $brand;
        endif;
    endif;

}

function GPC_getBitPayToken($endpoint)
{ 
    $gatepay_checkout_options = get_option('woocommerce_gatepay_checkout_gateway_settings');
    //dev or prod token
    switch ($gatepay_checkout_options['gatepay_checkout_endpoint']) {
        case 'test':
        default:
            return $gatepay_checkout_options['gatepay_checkout_token_dev'];
            break;
        case 'production':
            return $gatepay_checkout_options['gatepay_checkout_token_prod'];
            break;
    }

}

function GPC_isValidBitPayToken($gatepay_checkout_token, $gatepay_checkout_endpoint)
{
	
    $api_test = new GPC_Token($gatepay_checkout_endpoint, $gatepay_checkout_token);
    $api_response = json_decode($api_test->GPC_checkToken());

    if ($api_response->error == 'Object not found'):
        #valid token, no invoice
        return true;
    endif;
    GPC_Logger('Invalid token: ' . $gatepay_checkout_token . ' for ' . $gatepay_checkout_endpoint . ' environment', 'token', false, true);

    return false;
}

//hook into the order recieved page and re-add to cart of modal canceled
add_action('woocommerce_thankyou', 'gatepay_checkout_thankyou_page', 10, 1);
function gatepay_checkout_thankyou_page($order_id)
{
	
    global $woocommerce;
    $order = new WC_Order($order_id);

    $gatepay_checkout_options = get_option('woocommerce_gatepay_checkout_gateway_settings');
    $use_modal = intval($gatepay_checkout_options['gatepay_checkout_flow']);
    $gatepay_checkout_test_mode = $gatepay_checkout_options['gatepay_checkout_endpoint'];
    $restore_url = get_home_url() . '/wp-json/gatepay/cartfix/restore';
    $cart_url = get_home_url() . '/cart';
    $test_mode = false;
    if ($gatepay_checkout_test_mode == 'test'):
        $test_mode = true;
    endif;

    #use the modal
    if ($order->payment_method == 'gatepay_checkout_gateway' && $use_modal == 1):
        $invoiceID = $_COOKIE['gatepay-invoice-id'];
		
        ?>
        <script type='text/javascript'>
		
		my_url="<?php echo GATEPAY__PLUGIN_URL;?>assets/js/gatepay.js";
		</script>
							       
					<script type='text/javascript'>
									jQuery("#primary").hide()
									var payment_status = null;
							        var is_paid = false
									window.addEventListener("message", function(event) {
									    payment_status = event.data.status;
	                                    console.log('payment_status',event.data)

							            if(payment_status == 'paid'){
							                is_paid = true
							            }
									}, false);
									//hide the order info
									gatepay.onModalWillEnter(function() {
									    jQuery("primary").hide()
									});
									//show the order info
									gatepay.onModalWillLeave(function() {

									    if (is_paid == true) {
									        jQuery("#primary").fadeIn("slow");
									    } else {
									        var myKeyVals = {
									            orderid: '<?php echo $order_id; ?>'
									        }
							                console.log('payment_status leave 2',payment_status)
									        var redirect = '<?php echo $cart_url; ?>';
									        var api = '<?php echo $restore_url; ?>';
									        var saveData = jQuery.ajax({
									            type: 'POST',
									            url: api,
									            data: myKeyVals,
									            dataType: "text",
									            success: function(resultData) {
									                window.location = redirect;
									            }
									        });
									    }
									});
									//show the modal
							        <?php if ($test_mode): ?>
									gatepay.enableTestMode()
							        <?php endif;?>
		gatepay.showInvoice('<?php echo $invoiceID; ?>');
		</script>
		<?php
endif;
}

#custom info for GatePay
add_action('woocommerce_thankyou', 'gatepay_checkout_custom_message');
function gatepay_checkout_custom_message($order_id)
{
    $order = new WC_Order($order_id);
    if ($order->payment_method == 'gatepay_checkout_gateway'):
        $gatepay_checkout_options = get_option('woocommerce_gatepay_checkout_gateway_settings');
        $checkout_message = $gatepay_checkout_options['woocommerce_gatepay_checkout_gateway_thank_you'];
        if ($checkout_message != ''):
            echo '<hr><b>' . $checkout_message . '</b><br><br><hr>';
        endif;
    endif;
}

#gatepay image on payment page
function GPC_getBitPaymentIcon()
{
    $gatepay_checkout_options = get_option('woocommerce_gatepay_checkout_gateway_settings');
    $brand = $gatepay_checkout_options['gatepay_checkout_brand'];
    if ($brand != '-'):
        $icon = $brand . '" class="gatepay_logo"';
        return $icon;
    endif;
}

#add the gatway to woocommerce
add_filter('woocommerce_payment_gateways', 'wc_gatepay_checkout_add_to_gateways'); 
function wc_gatepay_checkout_add_to_gateways($gateways)
{
    $gateways[] = 'WC_Gateway_GatePay';
    return $gateways;
}
function runCurl_Json($url)
{

	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$result = curl_exec($ch);
	curl_close($ch);
	
	
	return $result;
}
add_filter( 'cron_schedules', 'isa_add_every_teen_seconds' );
function isa_add_every_teen_seconds( $schedules ) {
    $schedules['every_teen_seconds'] = array(
            'interval'  => 10,
            'display'   => __( 'Every 10 Seconds', 'textdomain' )
    );
    return $schedules;
}

function gatewapyjob_function() {
	
	require_once("includes.php");
	global $db;
	$gatepay_checkout_options = get_option('woocommerce_gatepay_checkout_gateway_settings');
	$nb_minutes = $gatepay_checkout_options['gatepay_checkout_expire_minutes'];
	$delete_order = $gatepay_checkout_options['gatepay_checkout_auto_delete']; //1 =>yes
	$completed=str_replace("wp-","",$gatepay_checkout_options['gatepay_checkout_order_confirmed']);	
		
	
	
	$trans=$db->db_select("_gatepay_checkout_transactions","id,transaction_id,order_id,date_added,transaction_status","transaction_status!='expired' ");
	
	foreach($trans as $tr)
	{
		
		if($tr["transaction_status"]=="new")
		{
			
			$url="https://gatepay.xyz/api/check.php?invoice_id=".$tr["transaction_id"];
			$res=runCurl_Json($url);
			$invoiceData = json_decode($res);
			
			if(!isset($invoiceData->result) || $invoiceData->result!="success")
				die($res);
		}
			
		if($tr["transaction_status"]=="paid" || (isset($invoiceData->payment_status)&& $invoiceData->payment_status=="confirmed"))
		{
			$exist=wc_get_order($tr["order_id"]);
			if($exist!==false)
			{
				$order = new WC_Order($tr["order_id"]);
				
				$order->update_status($completed);
				$db->db_query("update _gatepay_checkout_transactions set transaction_status='completed' where id=".$tr["id"]);
			}
			else
			$db->db_query("delete from _gatepay_checkout_transactions where id=".$tr["id"]);
			
		}
	}
	
	$trans=$db->db_select("_gatepay_checkout_transactions","id,transaction_id,order_id,date_added","transaction_status='new' and date_added < (NOW() - INTERVAL ".$nb_minutes." MINUTE)");
	
	foreach($trans as $tr)
	{
		
		/*if($delete_order==1)
			wp_delete_post($tr["order_id"],true);   
		else
		{*/
			$order = new WC_Order($tr["order_id"]);
			$order->update_status('cancelled', 'expired bitcoin payment');
		//}
		$db->db_query("update _gatepay_checkout_transactions set transaction_status='expired' where id=".$tr["id"]);
		
	}
	
}
 

add_action ('gatewapyjob', 'gatewapyjob_function');
register_deactivation_hook (__FILE__, 'gatepay_hire_cron_deactivate');
function gatepay_hire_cron_deactivate() {
	global $db;
	
	
	$timestamp = wp_next_scheduled ('gatewapyjob');	
	wp_unschedule_event ($timestamp, 'gatewapyjob');
} 
