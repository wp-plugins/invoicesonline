<?php

/*
Plugin Name: Woocommerce - Invoices Online Integration
Plugin URI: http://www.invoicesonline.co.za
Description: Provides integration between www.invoicesonline.co.za and the woocommerce wordpress plugin. This plugin allows invoices, pro-forma invoices and clients to be created on invoicesonline inside of wordpress. It provides full integration of the invoicesonline system for use with woocommerce.
Author: Ivoices Online
Version: 1.6
Author URI: http://www.invoicesonline.co.za/
*/

// Include Class File
include( plugin_dir_path(__FILE__) . 'assets/classes/io-api.class.php');

// Add Actions Hooks
add_action('woocommerce_checkout_update_order_meta','io_before_checkout');
add_action('woocommerce_created_customer','io_create_customer');
add_action('woocommerce_checkout_order_processed','log_order');
add_filter('woocommerce_payment_successful_result','successfull_payment');
add_action('woocommerce_after_my_account','vot');

function vot() {
    echo '<pre>'.print_r($_SESSION,true).'</pre>';
}

function log_order($order) {
    
    // Get Logging Settings
    $logging = get_option('ioLogging');
    
    
    $order_io_inv = get_post_meta($order,'io_proforma_invoice',true);
    
    // Moet die order nommer deur stuur sodat ek die pro-forma kan convert na invoice.
    WC()->session->ioOrder = $order_io_inv;
    
    //$pdata .= date('Y-m-d').' - Orderlogger '.$order.' : '.$order_io_inv.'<br/>';
    //io_log_file($pdata);
}

function successfull_payment($text) {
    
    // Get Logging Settings
    $logging = get_option('ioLogging');
    
    // Create new IO Object
    $io = new InvoicesOnlineAPI();
    
    // Set User API Details
    $io->username = get_option('io_api_username'); 
    $io->password = get_option('io_api_password');
    $io->BusinessID = get_option('io_business_id');
    $proFormaInvNr = WC()->session->ioOrder;
    $clientID = WC()->session->ioclientid;
    
    $createInvoice = $io->ConvertProformaToInvoice(get_option('io_business_id'), $proFormaInvNr);
    
    if($logging['errors'] == 'on'){
        $logs .= "Create New Invoice from Pro-Forma Invoice\n";
        $logs .= $createInvoice."\n";
        $logs .= "---------------------------------------------------\n\n";
    }
    
    $paymentopts['payment_method'] = $_REQUEST['payment_method'];
    $paymentopts['Description'] = '';
    $paymentopts['PaymentAmount'] = WC()->session->iocarttotal;
    $addPayment = $io->CreateNewPayment(get_option('io_business_id'), $clientID, $paymentopts);
    
    if($logging['errors'] == 'on'){
        $logs .= "Create New Payment for Invoice\n";
        $logs .= $addPayment."\n";
        $logs .= "---------------------------------------------------\n\n";
    }
    
    io_log_file($logs);
    
    return $text;
}

function io_create_customer($data) {
    
    // Get Logging Settings
    $logging = get_option('ioLogging');
    
    // Create new IO Object
    $io = new InvoicesOnlineAPI();
    
    // Set User API Details
    $io->username = get_option('io_api_username'); 
    $io->password = get_option('io_api_password');
    $io->BusinessID = get_option('io_business_id');
    
    $pdata .= '<pre>';
    $pdata .= print_r($_REQUEST,true);
    $pdata .= '</pre>';
    $pdata .= 'Customer ID = '.$data;
    
    // Lets set the client parameters
    $ClientParams['client_invoice_name'] = '';
    $ClientParams['client_phone_nr'] = '';
    $ClientParams['client_phone_nr2'] = '';
    $ClientParams['client_mobile_nr'] = '';
    $ClientParams['client_email'] = $_REQUEST['email'];
    $ClientParams['client_vat_nr'] = '';
    $ClientParams['client_fax_nr'] = '';
    $ClientParams['contact_name'] = '';
    $ClientParams['contact_surname'] = '';
    $ClientParams['client_postal_address1'] = '';
    $ClientParams['client_postal_address2'] = '';
    $ClientParams['client_postal_address3'] = '';
    $ClientParams['client_postal_address4'] = '';
    $ClientParams['client_physical_address1'] = '';
    $ClientParams['client_physical_address2'] = '';
    $ClientParams['client_physical_address3'] = '';
    $ClientParams['client_physical_address4'] = '';
    
    // Create the client and get new Client Id
    $ClientID = $io->CreateNewClient($ClientParams);
    
    update_user_meta($data, 'invoices_online_id', $ClientID);
    
    io_log_file($pdata);
}

function io_before_checkout($order_id) {
    
    // Get Logging Settings
    $logging = get_option('ioLogging');
    
    if($logging['errors'] == ''){
        $logs .= "Error logging disabled\n";
        $logs .= "---------------------------------------------------\n\n";
    }
    
    // Create new IO Object
    $io = new InvoicesOnlineAPI();
    
    // Set User API Details
    $io->username = get_option('io_api_username'); 
    $io->password = get_option('io_api_password');
    $io->BusinessID = get_option('io_business_id');
    
    $cinfo = $_REQUEST;
    
    // Create the client first
    // Lets set the client parameters
    if($cinfo['billing_company'] == ''){
        $ClientParams['client_invoice_name'] = $cinfo['billing_first_name'].' '.$cinfo['billing_last_name'];
    } else {
        $ClientParams['client_invoice_name'] = $cinfo['billing_first_name'].' '.$cinfo['billing_last_name'].' ( '.$cinfo['billing_company'].' )';
    }
    $ClientParams['client_phone_nr'] = $cinfo['billing_phone'];
    $ClientParams['client_phone_nr2'] = '';
    $ClientParams['client_mobile_nr'] = '';
    $ClientParams['client_email'] = $cinfo['billing_email'];
    $ClientParams['client_vat_nr'] = '';
    $ClientParams['client_fax_nr'] = '';
    $ClientParams['contact_name'] = $cinfo['billing_first_name'];
    $ClientParams['contact_surname'] = $cinfo['billing_last_name'];
    $ClientParams['client_postal_address1'] = $cinfo['billing_address_1'];
    $ClientParams['client_postal_address2'] = $cinfo['billing_address_2'];
    $ClientParams['client_postal_address3'] = $cinfo['billing_city'].', '.$cinfo['billing_state'];
    $ClientParams['client_postal_address4'] = $cinfo['billing_postcode'];
    $ClientParams['client_physical_address1'] = $cinfo['shipping_address_1'];;
    $ClientParams['client_physical_address2'] = $cinfo['shipping_address_2'];
    $ClientParams['client_physical_address3'] = $cinfo['shipping_city'].', '.$cinfo['shipping_state'];
    $ClientParams['client_physical_address4'] = $cinfo['shipping_postcode'];
    
    // Create the client and get new Client Id
    $ClientID = $io->CreateNewClient($ClientParams);
    
    if($logging['errors'] == 'on'){
        $logs .= "Create New Client\n";
        $logs .= $ClientID."\n";
        $logs .= "---------------------------------------------------\n\n";
    }
    
    // Prepare data for submission to IO
    // I am using the WC object already called in the function the action ( hook ) is used
    $woocart = WC()->cart->get_cart();
    
    // Set empty array
    $lines = array();
    
    // Iterate through the cart items and add them to the array.
    foreach($woocart as $item){
        $lines[] = array (
            $item['product_id'],
            $item['quantity'],
            $item['data']->post->post_title,
            $item['line_total'],
            'ZAR',
            1,
            '14.00',
            0,
            $_REQUEST['order_comments']
        );
    }
    
    $OrderNR = 0;
    
    WC()->session->iolines = $lines;
    WC()->session->iocarttotal = WC()->cart->total;
    WC()->session->ioclientid = $ClientID;
    
    $addproforma = $io->CreateNewProformaInvoice($io->BusinessID, $ClientID, $OrderNR, $lines);
    
    if($logging['errors'] == 'on'){
        $logs .= "Create New Proforma Invoice\n";
        $logs .= $addproforma."\n";
        $logs .= "---------------------------------------------------\n\n";
    }
    
    $proformaid = json_decode($addproforma);
    
    update_post_meta($order_id,'io_proforma_invoice',$proformaid[2]->invoice_nr);
    
    io_log_file($logs);
}

function io_log_file($data) {
    //$file = 'test.xml';
    $file = WP_PLUGIN_DIR."/woo-io/assets/logs/logs.txt";
    // Open the file to get existing content
    $current = file_get_contents($file);
    // Append a new person to the file
    $current .= $data."\n";
    // Write the contents back to the file
    $write = file_put_contents($file, $current);
    if($write){
        //echo 'written'.$write;
    } else {
        //echo 'not written'.$write;
    };
    
}




/***********************************************/
/*              PLUGIN OPTIONS                 */
/***********************************************/

// create custom plugin settings menu
add_action('admin_menu', 'io_create_menu');

function io_create_menu() {

	//create new top-level menu
	add_menu_page('Invoices Online Plugin Settings', 'Invoices Online', 'administrator', __FILE__, 'io_settings_page',plugins_url('/assets/images/favicon-16x16.png', __FILE__));

	//call register settings function
	add_action( 'admin_init', 'register_io_settings' );
}


function register_io_settings() {
	//register our settings
	register_setting( 'io-settings-group', 'io_api_username' );
	register_setting( 'io-settings-group', 'io_api_password' );
	register_setting( 'io-settings-group', 'io_business_id' );
	register_setting( 'io-settings-group', 'ioLogging' );
}

function io_settings_page() {
?>
<div class="wrap">
<h2>Invoices Online Settings</h2>
<img class="io-logo" src="<?php echo plugins_url('/assets/images/logo.png', __FILE__); ?>">
<form method="post" action="options.php">
    <?php settings_fields( 'io-settings-group' ); ?>
    <?php do_settings_sections( 'io-settings-group' ); ?>
    <ul class="io-tab-menu subsubsub">
        <li class="io-tab active" id="io-settings"><a href="#">Settings</a> | </li><li class="io-tab" id="io-reporting"><a href="#">Reporting | </a></li><li class="io-tab" id="io-faq"><a href="#">Support & FAQ</a></li>
    </ul>
    <div class="io-tab-holder active" id="io-settings-tab">
        <!--<table class="io-table-form">-->
        <table class="wp-list-table widefat plugins">
            <tr>
                <td colspan="3">
                    API Information is available on <a href="http://www.invoicesonline.co.za" target="_blank">www.invoicesonline.co.za</a> -> Settings -> API Access
                </td>
            </tr>
            <tr valign="top">
            <th scope="row">API Username</th>
            <td><input type="text" name="io_api_username" value="<?php echo esc_attr( get_option('io_api_username') ); ?>" /></td>
            <td class="io-side-note"></td>
            </tr>
            <tr valign="top">
            <th scope="row">API Password</th>
            <td><input type="text" name="io_api_password" value="<?php echo esc_attr( get_option('io_api_password') ); ?>" /></td>
            <td class="io-side-note"></td>
            </tr>
            <tr valign="top">
            <th scope="row">Business ID</th>
            <td><input type="text" name="io_business_id" value="<?php echo esc_attr( get_option('io_business_id') ); ?>" /></td>
            <td class="io-side-note"></td>
            </tr>
            <tr valign="top">
                <th scope="row">Logging Options</th>
                <td>
                    <?php $logging = get_option('ioLogging'); ?>
                    Errors: <input type="checkbox" name="ioLogging[errors]" <?php echo ($logging['errors'] == 'on' ? 'checked="checked"' : '' ); ?> style="margin-right:10px"/>
                </td>
            </tr>
        </table>
    </div>
    <div class="io-tab-holder" id="io-reporting-tab">
        <table class="wp-list-table widefat plugins">
            <tr>
                <td colspan="2">
                    <textarea id="io-error-log" cols="80" rows="20">
                    <?php
                        $file = WP_PLUGIN_DIR."/woo-io/assets/logs/logs.txt";
                        // Open the file to get existing content
                        $current = file_get_contents($file);
                        echo $current;
                    ?>
                </textarea>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span id="clear-io-log" class="button button-secondary">Clear Log</span>
                    <span id="refresh-io-log" class="button button-secondary">Refresh Log</span>
                </td>
            </tr>
        </table>
    </div>
    <div class="io-tab-holder" id="io-faq-tab">
        <table class="wp-list-table widefat plugins">
            <tr valign="top">
            <th scope="row">Support Email:</th>
            <td>support@invoicesonline.co.za</td>
            </tr>
            <tr valign="top">
            <th scope="row">Website:</th>
            <td><a href="http://www.invoicesonline.co.za">www.invoiceonline.co.za</a></td>
            </tr>
        </table>
    </div>
    <?php submit_button(); ?>

</form>
</div>
<?php }

add_action( 'admin_head', 'io_head' ); // Write our JS below here

function io_head() { ?>
    <style type="text/css">
        .io-tab-menu {
/*            padding-bottom:0px;
            margin-bottom:0px;
            margin-top: 35px;*/
        }
        
        .subsubsub span {
            line-height: 2;
            padding: .2em;
            text-decoration: none;
        }
        
        .io-tab-holder {
            display:none;
            padding:10px 0px;
            margin-top:35px;
        }
        
        .io-tab-holder.active {
            display:block;
        }
        
/*        .io-tab:first-child {
            border-top-left-radius:5px;
            -o-border-top-left-radius:5px;
            -moz-border-top-left-radius:5px;
            -webkit-border-top-left-radius:5px;
        }
        
        .io-tab:last-child {
            border-top-right-radius:5px;
            -o-border-top-right-radius:5px;
            -moz-border-top-right-radius:5px;
            -webkit-border-top-right-radius:5px;
        }
        
        .io-tab {
            background: url(<?php echo plugins_url('/assets/images/iotabbg.jpg', __FILE__); ?>) repeat-x;
            display: inline-block;
            padding: 11px;
            color: #ffffff;
            margin:0px;
            cursor:pointer;
            border-left:#89c2fc 1px solid;
            border-right:#418fdd 1px solid;
        }*/
        
        .io-tab.active {
            color:#000000;
        }
        
        .io-logo {
            position: absolute;
            right: 40px;
            top: 20px;
        }
        
        .io-table-form { }
        .io-table-form tr { }
        .io-table-form tr th { padding:9px; }
        .io-table-form tr td { padding:9px; }
        .io-table-form tr td input[type="text"] { border: solid 1px #efefef; background: #ffffff; box-shadow: none; font-size: 13px; color: #777777; }
    </style>
    <?php
}
add_action( 'admin_footer', 'io_javascript' ); // Write our JS below here

function io_javascript() { ?>
	<script type="text/javascript" >
	jQuery(document).ready(function($) {
            $('.io-tab').on('click',function(){
               iotab = $(this).attr('id');
               $('.io-tab').removeClass('active');
               $(this).addClass('active');
               $('.io-tab-holder').removeClass('active');
               $('#'+iotab+'-tab').addClass('active');
            });
            
            $('#clear-io-log').on('click',function(){
               var data = {
			'action': 'clear_error_log'
		};

		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		$.post(ajaxurl, data, function(response) {
			alert('Cleared');
                        $('#io-error-log').html('');
		}); 
            });
            
            $('#refresh-io-log').on('click',function(){
               var data = {
			'action': 'refresh_error_log'
		};

		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		$.post(ajaxurl, data, function(response) {
			$('#io-error-log').html(response);
		}); 
            });
            
            $('.subsubsub li a').click(function(event){
                event.preventDefault();
                //do whatever
            });
	});
	</script> <?php
}

add_action( 'wp_ajax_clear_error_log', 'clear_error_log' );
add_action( 'wp_ajax_refresh_error_log', 'refresh_error_log' );

function clear_error_log() {
    
	$file = WP_PLUGIN_DIR."/woo-io/assets/logs/logs.txt";
        // Open the file to get existing content
        $current = file_get_contents($file);
        // Append a new person to the file
        $current = "";
        // Write the contents back to the file
        file_put_contents($file, $current);
        
	die(); // this is required to terminate immediately and return a proper response
}

function refresh_error_log() {
    
        // Open the file to get existing content
        $file = WP_PLUGIN_DIR."/woo-io/assets/logs/logs.txt";
        
        // Open the file to get existing content
        $current = file_get_contents($file);
        
        echo $current;
        
	die(); // this is required to terminate immediately and return a proper response
}

?>
