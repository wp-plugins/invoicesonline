<?php
/*
Plugin Name: Woocommerce - Invoices Online Integration
Plugin URI: http://www.invoicesonline.co.za
Description: Provides integration between www.invoicesonline.co.za and the woocommerce wordpress plugin. This plugin allows invoices, pro-forma invoices and clients to be created on invoicesonline inside of wordpress. It provides full integration of the invoicesonline system for use with woocommerce.
Author: Invoices Online
Version: 1.8
Author URI: http://www.invoicesonline.co.za/
*/

include( plugin_dir_path(__FILE__) . 'assets/classes/io-api.class.php');

function myplugin_activate() {
    $file = WP_PLUGIN_DIR."/woo-io/assets/logs/logs.txt";
    if(!file_exists($file)) 
    { 
       $fp = fopen($file,"w");  
       fwrite($fp,"0");  
       fclose($fp); 
    }  
}
register_activation_hook( __FILE__, 'myplugin_activate' );

add_action('woocommerce_created_customer','io_create_customer');
add_action('woocommerce_checkout_update_order_meta','io_before_checkout');
add_action('woocommerce_after_checkout_validation','io_add_io_client');
add_action('woocommerce_checkout_order_processed','log_order');
add_filter('woocommerce_payment_successful_result','successfull_payment');
add_action('woocommerce_after_my_account','vot');

function vot() {
    session_start();
    echo '<h2>Invoices</h2>';
    
    $io = new InvoicesOnlineAPI();
    $io->username = get_option('io_api_username'); 
    $io->password = get_option('io_api_password');
    $io->BusinessID = get_option('io_business_id');
    
    $user_ID = get_current_user_id();
    $ioID = get_user_meta($user_ID,'invoices_online_id',true);
    $document = $io->GetAllDocumentsByType('invoices',$ioID);   
    
    echo '<table class="shop_table my_account_orders">
            <thead>
                <tr>
                    <th class="order-number"><span class="nobr">Order</span></th>
                    <th class="order-date"><span class="nobr">Date</span></th>
                    <th class="order-total"><span class="nobr">Total</span></th>
                    <th class="order-actions">&nbsp;</th>
                </tr>
            </thead>
            <tbody>';
    foreach($document as $doc){
        foreach($doc as $dc){
            echo '<tr class="order">
                    <td class="order-number"><a href="'.$dc['link'].'" target="_blank">'.$dc['invoice_nr'].'</a></td>
                    <td class="order-date">'.$dc['invoice_date'].'</td>
                    <td class="order-total"><span class="amount">'.$dc['total'].'</span></td>
                    <td class="order-actions"><a href="'.$dc['link'].'" target="_blank" class="button view">View</a></td>
                </tr>';
        }
    }
    
    echo '</tbody></table>';  
}

function login_log_details() {
    
    session_start();
    $user_ID = get_current_user_id();

    WC()->session->cid = user_ID;
    WC()->session->iocid = get_user_meta($user_ID,'invoices_online_id',true);
    
    $logs .= "Session Vars (".$user_ID.")\n";
    $logs .= "<pre>".print_r($_SESSION,true)."</pre>\n";
    $logs .= "<pre>".print_r(WC()->session,true)."</pre>\n";
    $logs .= "<pre>".get_user_meta($user_ID,'invoices_online_id',true)."</pre>\n";
    $logs .= "---------------------------------------------------\n\n";
   
    io_log_file($logs);
    
}

add_action( 'woocommerce_email_order_meta', 'woo_add_order_notes_to_email' );

function woo_add_order_notes_to_email() {

	global $woocommerce, $post;

        echo '<h2 style="color:#505050; display:block; font-family:Arial; font-size:30px; font-weight:bold; margin-top:10px; margin-right:0; margin-bottom:10px; margin-left:0; text-align:left; line-height:150%">Invoicee</h2>';
	echo 'Click here to view your Invoice : <a href="'.WC()->session->ioinvoiceurl.'"> View Invoice</a>';
}

function log_order($order) {
    
    $order_io_inv = get_post_meta($order,'io_proforma_invoice',true);
    
    WC()->session->ioOrder = $order_io_inv;
    
}

function successfull_payment($text) {

    $logging = get_option('ioLogging');

    $io = new InvoicesOnlineAPI();

    $io->username = get_option('io_api_username'); 
    $io->password = get_option('io_api_password');
    $io->BusinessID = get_option('io_business_id');
    $proFormaInvNr = WC()->session->ioOrder;
    $clientID = WC()->session->ioclientid;
    
    $createInvoice = $io->ConvertProformaToInvoice(get_option('io_business_id'), $proFormaInvNr);   
    $createInvoice = json_decode($createInvoice);
    
    WC()->session->ioinvoiceurl = $createInvoice[2]->url;
    
    if($logging['errors'] == 'on'){
        $logs .= "Create New Invoice from Pro-Forma Invoice\n";
        $logs .= $createInvoice[2]->url."\n";
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
    
    return $text;
}

function io_create_customer($data) {
    
    session_start();
    
    $logging = get_option('ioLogging');

    $io = new InvoicesOnlineAPI();

    $io->username = get_option('io_api_username'); 
    $io->password = get_option('io_api_password');
    $io->BusinessID = get_option('io_business_id');
    
    $pdata .= "updatemeta\n";
    $pdata .= "Customer ID = ".$data." - ".$_SESSION['new_customer_id']."\n";
    
    $pdata .= "Session before update o\n";
    $pdata .= "<pre>".print_r($_SESSION,true)."</pre>\n";
    $pdata .= "---------------------------------------------------\n\n";   
    
    $addioid = update_user_meta($data, 'invoices_online_id', $_SESSION['new_customer_id']);
    
     $pdata .= "Session after update o\n";
    $pdata .= "<pre>".print_r($_SESSION,true)."</pre>\n";
    $pdata .= "---------------------------------------------------\n\n";
    
    if($addioid == true){
        $pdata .= "--- updatemeta\n";
    } else {
        $pdata .= "--- didnt updatemeta\n";
    }
    
    io_log_file($pdata);
    
}

function io_add_io_client() {
    session_start();
    
    unset($_SESSION['new_customer_id']);
    
    $logs .= "Session before o\n";
    $logs .= "<pre>".print_r($_SESSION,true)."</pre>\n";
    $logs .= "---------------------------------------------------\n\n";

    $logging = get_option('ioLogging');
    
    if($logging['errors'] == ''){
        $logs .= "Error logging disabled\n";
        $logs .= "---------------------------------------------------\n\n";
    }

    $io = new InvoicesOnlineAPI();

    $io->username = get_option('io_api_username'); 
    $io->password = get_option('io_api_password');
    $io->BusinessID = get_option('io_business_id');
    
    $cinfo = $_REQUEST;

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

    $ClientID = $io->CreateNewClient($ClientParams);
    
    $logs .= "Session after add o\n";
    $logs .= "<pre>".print_r($_SESSION,true)."</pre>\n";
    $logs .= "---------------------------------------------------\n\n";
  
    $_SESSION['new_customer_id'] = $ClientID;
    
    $logs .= "Session after set o\n";
    $logs .= "<pre>".print_r($_SESSION,true)."</pre>\n";
    $logs .= "---------------------------------------------------\n\n";
    
    io_log_file($logs);

}

function io_before_checkout($order_id) {
    
    session_start();

    $logging = get_option('ioLogging');
    $vatapplies = get_option('vatapplies');
    $amountsincludevat = get_option('amountsincludevat');
    
    if($logging['errors'] == ''){
        $logs .= "Error logging disabled\n";
        $logs .= "---------------------------------------------------\n\n";
    }

    $io = new InvoicesOnlineAPI();

    $io->username = get_option('io_api_username'); 
    $io->password = get_option('io_api_password');
    $io->BusinessID = get_option('io_business_id');

    $woocart = WC()->cart->get_cart();
    $wctax = new WC_Tax();
    $wctavr = $wctax->get_rates();

    $lines = array();

    foreach($woocart as $item){
        $product_info = wc_get_product($item['product_id']);
        
        $lines[0] = $item['product_id'];
        $lines[1] = $item['quantity'];
        $lines[2] = $item['data']->post->post_title;
        $lines[3] = $item['line_total'];
        $lines[4] = 'ZAR';
        if($amountsincludevat == 'yes'){
            $lines[5] = 1;
        } else {
            $lines[5] = 0;
        };
        if(empty($wctavr)){
            $lines[6] = '0';
        } else {
            $lines[6] = $wctavr[1]['rate'];
        } 
        if($vatapplies == 'yes'){
            $lines[7] = 1;
        } else {
            $lines[7] = 0;
        }
        $lines[8] = $_REQUEST['order_comments'];
    }
    
    $OrderNR = 0;
    
    WC()->session->iolines = $lines;
    WC()->session->iocarttotal = WC()->cart->total;
    WC()->session->ioclientid = $_SESSION['new_customer_id'];
    
    $addproforma = $io->CreateNewProformaInvoice($io->BusinessID, $_SESSION['new_customer_id'], $OrderNR, $lines);
    
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

    $file = WP_PLUGIN_DIR."/woo-io/assets/logs/logs.txt";

    $current = file_get_contents($file);

    $current .= $data."\n";

    $write = file_put_contents($file, $current);
    
}

add_action('admin_menu', 'io_create_menu');

function io_create_menu() {

	add_menu_page('Invoices Online Plugin Settings', 'Invoices Online', 'administrator', __FILE__, 'io_settings_page',plugins_url('/assets/images/favicon-16x16.png', __FILE__));

	add_action( 'admin_init', 'register_io_settings' );
}


function register_io_settings() {

	register_setting( 'io-settings-group', 'io_api_username' );
	register_setting( 'io-settings-group', 'io_api_password' );
	register_setting( 'io-settings-group', 'io_business_id' );
	register_setting( 'io-settings-group', 'ioLogging' );
        register_setting( 'io-settings-group', 'vatapplies' );
	register_setting( 'io-settings-group', 'amountsincludevat' );
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
            <tr valign="top">
                <th scope="row">Woocommerce Prices Include VAT?</th>
                <td>
                    <?php $vatapplies = get_option('vatapplies'); ?>
                    <select name="vatapplies">
                        <?php 
                        $arr = array('yes','no');
                        foreach($arr as $ar){
                            if($vatapplies == $ar){
                                echo '<option value="'.$ar.'" selected="selected">'.$ar.'</option>';
                            } else {
                                echo '<option value="'.$ar.'">'.$ar.'</option>';
                            }
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Must Invoices Online Apply vat to prices?</th>
                <td>
                    <?php $amountsincludevat = get_option('amountsincludevat'); ?>
                    <select name="amountsincludevat">
                        <?php 
                        $arr2 = array('yes','no');
                        foreach($arr2 as $ar2){
                            if($amountsincludevat == $ar2){
                                echo '<option value="'.$ar2.'" selected="selected">'.$ar2.'</option>';
                            } else {
                                echo '<option value="'.$ar2.'">'.$ar2.'</option>';
                            }
                        }
                        ?>
                    </select>
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

add_action( 'admin_head', 'io_head' );

function io_head() { ?>
    <style type="text/css">
        .io-tab-menu {

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
add_action( 'admin_footer', 'io_javascript' );

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

		$.post(ajaxurl, data, function(response) {
			alert('Cleared');
                        $('#io-error-log').html('');
		}); 
            });
            
            $('#refresh-io-log').on('click',function(){
               var data = {
			'action': 'refresh_error_log'
		};

		$.post(ajaxurl, data, function(response) {
			$('#io-error-log').html(response);
		}); 
            });
            
            $('.subsubsub li a').click(function(event){
                event.preventDefault();
            });
	});
	</script> <?php
}

add_action( 'wp_ajax_clear_error_log', 'clear_error_log' );
add_action( 'wp_ajax_refresh_error_log', 'refresh_error_log' );

function clear_error_log() {
    
	$file = WP_PLUGIN_DIR."/woo-io/assets/logs/logs.txt";
        $current = file_get_contents($file);
        $current = "";
        file_put_contents($file, $current);       
	die();
}

function refresh_error_log() {

        $file = WP_PLUGIN_DIR."/woo-io/assets/logs/logs.txt";
        $current = file_get_contents($file);        
        echo $current;       
	die();
}

add_action('admin_notices', 'io_admin_notice');

function io_admin_notice() {
	global $current_user ;
        $user_id = $current_user->ID;
	if ( ! get_user_meta($user_id, 'io_ignore_notice') ) {
        echo '<div class="error"><p>'; 
        printf(__('Please set your tax settings in the Invoices Online Settings page | <a href="%1$s">Hide Notice</a>'), '?io_nag_ignore=0');
        echo "</p></div>";
	}
}

add_action('admin_init', 'io_nag_ignore');

function io_nag_ignore() {
	global $current_user;
        $user_id = $current_user->ID;
        if ( isset($_GET['io_nag_ignore']) && '0' == $_GET['io_nag_ignore'] ) {
             add_user_meta($user_id, 'io_ignore_notice', 'true', true);
	}
}

?>