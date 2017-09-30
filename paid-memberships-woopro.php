<?php
/*
Plugin Name: Paid Memberships WooPro
Description: Adaptation of Paid Membership Pro to work nicely with WooCommerce
Version: .1
Author: Dean Walker
*/

// execute after setting up a new user - restore the email address avoiding error from WordPress during user creation
function pmwoopro_setup_new_user($bool, $user_id, $new_user_array, $pmpro_level) {
	error_log( "restoring original email address " . $new_user_array["original_user_email"]);
	global $wpdb;
	$wpdb->update(
		$wpdb->users,
		array( 'user_email' => $new_user_array["original_user_email"] ),
		array( 'ID' => $user_id )
	);

	error_log( "restored original email address ");
	return true;
}

add_filter('pmpro_setup_new_user', 'pmwoopro_setup_new_user', 10, 4);

// execute before setting up a new user - modify the email address to ensure no error from WordPress if email is a duplicate
function pmwoopro_checkout_new_user_array($new_user_array) {
	error_log( "changing email address for new user. Original email address is " . $new_user_array["user_email"]);
	$new_user_array["original_user_email"] = $new_user_array["user_email"];
	$new_user_array["user_email"] = $new_user_array["user_email"] . pmpro_getDiscountCode();

	error_log( "temporary email address for new user is " . $new_user_array["user_email"]);
	return $new_user_array;
}

add_filter('pmpro_checkout_new_user_array', 'pmwoopro_checkout_new_user_array', 10, 1);


function pmwoopro_show_discount_code()
{
	$level_id = $_GET["level"];
	if ($level_id==3) {
		return false;
	}
	return true;
}

add_filter('pmpro_show_discount_code', 'pmwoopro_show_discount_code');


//some issue with PayPal standard where order cancelation is attempted
//believe that membership is being added to user twice, and second membership causes first to be cancelled???
		

function fudge_cancel_previous_subscriptions_false()
{
	return false;
}

add_filter('pmpro_cancel_previous_subscriptions', 'fudge_cancel_previous_subscriptions_false');

// useful for debugging PayPal Standard integration issues
function pmpwoopro_debug_order($pp_string, $order) {
	error_log( "debug order " . var_export($order, TRUE));
	error_log( "debug paypal string " . var_export($pp_string, TRUE));
	return $pp_string;
}
//add_filter('pmpro_paypal_standard_nvpstr', 'pmpwoopro_debug_order', 10, 2);

// remove login and password fields
function pmpwoopro_skip_account_fields()
{
	return true;
}

add_filter('pmpro_skip_account_fields', 'pmpwoopro_skip_account_fields');

// fix the second payment date
function pmpwoopro_set_next_payment_date($level) {

	$level_id = $level->id;
		
	if (($level_id==2 || $level_id==3) && $level->cycle_number) {
		$date1 = new DateTime("now");
	
		if ($level_id==2) {
			$date2 = new DateTime("2017-08-21");
		} else if ($level_id==3) {
			$date2 = new DateTime("2017-07-21");
		}
	
		$interval = $date1->diff($date2);
		$days_diff = $interval->days;
		if ($days_diff < 1) {
			//update number and period
			$level->cycle_number = '1';
			$level->cycle_period = "Day";	
		} else if ($days_diff > 90) {
			//update number and period
			$level->cycle_number = ceil($days_diff/7);
			$level->cycle_period = "Week";	
		} else {
			//update number and period
			$level->cycle_number = $days_diff;
			$level->cycle_period = "Day";	
		} 

		// if second payment date already passed then set it to tomorrow
		if ($weeks_diff < 1) {
			$$weeks_diff = 1;
		}
		
	}

	return $level;

}

add_filter("pmpro_checkout_level", "pmpwoopro_set_next_payment_date", 10, 1);

	
// allow email addresses to be used multiple times
// Need plugin Allow Multiple Accounts to be enabled also
function pmpwoopro_checkout_oldemail()
{
	return null;
}

add_filter('pmpro_checkout_oldemail', 'pmpwoopro_checkout_oldemail');

// only email address is a required user field
function pmpwoopro_required_user_fields($required_fields)
{
	$fields = array();
	$fields[] = "bemail";
	return $fields;
}

add_filter('pmpro_required_user_fields', 'pmpwoopro_required_user_fields', 10, 1);

//woocommerce bit 

// skip the cart page and go straight to checkout
//add_filter ('add_to_cart_redirect', 'redirect_to_checkout');

//function redirect_to_checkout() {
//  global $woocommerce;
  // Remove the default `Added to cart` message
//  $woocommerce->clear_messages();
  // Redirect to checkout
//  $url = $woocommerce->cart->get_checkout_url();
//  return $url;
//}

// repeat payments for Choirs products
//add_filter( 'woocommerce_paypal_args' , 'add_repeat_payment_args' );
//function add_repeat_payment_args( $paypal_args ) {
	//$paypal_args['business'] = 'info@ocweb.pro';
	//print_r( $paypal_args['business'] );
//	$paypal_args['a3']=10;
//	$paypal_args['p3']=6;
//	$paypal_args['t3']=M;
//	$paypal_args['src']=1;
//	$paypal_args['srt']=1;
//	$paypal_args['cmd']='_cart';
//	$paypal_args['cmd']='_xclick-subscriptions';

//	return $paypal_args;
//}

// change text of notes field
//add_filter( 'woocommerce_checkout_fields' , 'custom_override_checkout_fields' );
//function custom_override_checkout_fields( $fields ) {
//     $fields['order']['order_comments']['label'] = 'Singing Experience (Optional)';
//     $fields['order']['order_comments']['placeholder'] = 'Please tells us about any singing experience you have (if any), the part or section in the choir where you\'d prefer to sing (if known), or anything else you feel may be relevant';
//     return $fields;
//}

/*function my_init_email_as_username()
{
  //check for level as well to make sure we're on checkout page
  if(empty($_REQUEST['level']))
    return;
  
  if(!empty($_REQUEST['bemail']))
    $_REQUEST['username'] = $_REQUEST['bemail'];
    
  if(!empty($_POST['bemail']))
    $_POST['username'] = $_POST['bemail'];
    
  if(!empty($_GET['bemail']))
    $_GET['username'] = $_GET['bemail'];
}
add_action('init', 'my_init_email_as_username');
*/

function mod_cost_text($astring, $level)
{	
	$level_id = $level->id;
	if ($level_id==2) {
    	return "The price for membership is £50.00 now to cover the summer term rehearsal and a further £50.00 in August to cover the Autumn term rehearsals, or £25 now, and £25 in August, if you are in full time education.<br/>If you are in full time education, please use the discount code ‘Mozart’ to receive the reduced fee. You may be required to show proof of student status.<br/>Payments are made securely through PayPal. Your second payment will be processed automatically in August, unless you cancel your subscription in advance via your PayPal account.";
	} else if ($level_id==3 || $level_id==4) {
		return "The deposit for Voices Summer School is £30.00 and the remaining £40.00 is payable in July.<br/>Payments are made securely through PayPal and your second payment will be taken automatically, unless you cancel in advance via your PayPal account.";
	}
	return $astring;
}

add_filter("pmpro_level_cost_text", "mod_cost_text", 10, 2);
 

function add_cc_to_admin_emails($headers, $email)
{		
	//cc emails going to admin email
    if($email->email == get_bloginfo("admin_email"))
	{
		//add cc
		$headers[] = "Cc: carol@prowse.org.uk";		
	}
 
	return $headers;
}
add_filter("pmpro_email_headers", "add_cc_to_admin_emails", 10, 2);

function checkout_extra_fields_header($defaultString) {
    return "";
}

// remove double entry fields from checkout form
add_filter("pmpro_checkout_confirm_password", "__return_false");
add_filter("pmpro_checkout_confirm_email", "__return_false");
add_filter("pmprorh_section_header", "checkout_extra_fields_header", 10, 1);

global $pmprorh_options;
//$pmprorh_options["register_redirect_url"] = home_url("/tools/rq/");
//$pmprorh_options["use_email_for_login"] = true;
//$pmprorh_options["directory_page"] = "/directory/";
//$pmprorh_options["profile_page"] = "/profile/";


function add_billing_fields_to_paypal_standard($namevaluestring, $order) {
    //$user = $order -> getUser();
    $namevaluestring = add_pair($namevaluestring, "first_name", $_REQUEST["bfirstname"] );
    $namevaluestring = add_pair($namevaluestring, "last_name", $_REQUEST["blastname"] );
    $namevaluestring = add_pair($namevaluestring, "address1", $_REQUEST['billing_address_1'] );
    $namevaluestring = add_pair($namevaluestring, "address2", $_REQUEST['billing_address_2'] );
    $namevaluestring = add_pair($namevaluestring, "city", $_REQUEST['billing_city'] );
    //$namevaluestring = add_pair($namevaluestring, "city", $_REQUEST['billing_state'] );
    $namevaluestring = add_pair($namevaluestring, "country", "GB" );
    $namevaluestring = add_pair($namevaluestring, "zip", $_REQUEST["billing_postcode"] );
    $namevaluestring = add_pair($namevaluestring, "night_phone_a", $_REQUEST["44"] );
    $namevaluestring = add_pair($namevaluestring, "night_phone_b", $_REQUEST["billing_phone"] );

    //$namevaluestring = add_pair($namevaluestring, "email", $_REQUEST['bemail'] );
    return $namevaluestring;
}

function add_pair($astring, $aname, $avalue) {
    $astring = $astring . "&" . $aname . "=" . $avalue;
    return $astring;
}

//add_filter( 'pmpro_paypal_standard_nvpstr', 'add_billing_fields_to_paypal_standard', 20, 2 ); 

function set_UK() {
    return "GB";
}
add_action("pmpro_default_country", "set_UK");


// always show billing details on membership check out page so we can collect customer name and address and phone number
function filter_pmpro_include_billing_address_fields( $return_false ) {  
    return true; 
}; 
             
// add the filter 
//add_filter( 'pmpro_include_billing_address_fields', 'filter_pmpro_include_billing_address_fields', 99, 1 ); 

//override checkout page template
function my_pmpro_pages_shortcode_checkout($content)
{
	ob_start();
	include(plugin_dir_path(__FILE__) . "pages/checkout.php");
	$temp_content = ob_get_contents();
	ob_end_clean();
	return $temp_content;
}
//add_filter("pmpro_pages_shortcode_checkout", "my_pmpro_pages_shortcode_checkout");

function my_pmpro_pages_shortcode_confirmation($content)
{
	ob_start();
	include(plugin_dir_path(__FILE__) . "pages/confirmation.php");
	$temp_content = ob_get_contents();
	ob_end_clean();
	return $temp_content;
}
//add_filter("pmpro_pages_shortcode_confirmation", "my_pmpro_pages_shortcode_confirmation");


/*function load_override_language_file($domain, $mofile)
{
    // Note: the plugin directory check is needed to prevent endless function nesting
    // since the new load_textdomain() call will apply the same hooks again.
    if ('pmpro' === $domain && plugin_dir_path($mofile) === WP_PLUGIN_DIR.'/pmpro/languages/')
    {
        load_textdomain('pmpro', dirname(__FILE__).'/pmpro-'.get_locale().'.mo');
    }
}*/
//add_action('load_textdomain', 'load_override_language_file', 10, 2);

//we have to put everything in a function called on init, so we are sure Register Helper is loaded
function add_extra_fields_to_checkout()
{
    //don't break if Register Helper is not loaded
    if(!function_exists("pmprorh_add_registration_field"))
    {
        return false;
    }

	$chorus_fields = add_chorus_fields();
	$school_fields = add_summer_school_fields();

    //add the fields to checkout page
    foreach($chorus_fields as $field)
        pmprorh_add_registration_field(
            "checkout_boxes", // location on checkout page
            $field            // PMProRH_Field object
        );
    foreach($school_fields as $field)
        pmprorh_add_registration_field(
            "checkout_boxes", // location on checkout page
            $field            // PMProRH_Field object
        );
    //that's it. see the PMPro Register Helper readme for more information and examples.

}

function add_chorus_fields() {
    //define the fields
	$levels=array(1,2);

    $fields[] = new PMProRH_Field(
        "bfirstname",            // input name, will also be used as meta key
        "text",                 // type of field
        array(
            "label"=>"First Name",
            "size"=>30,         // input size
            "memberslistcsv"=>true,
			"showrequired"=>false,
            "profile"=>false,    // show in user profile
            "required"=>true,    // make this field required
			"levels"=>$levels
        ));
    $fields[] = new PMProRH_Field(
        "blastname",            // input name, will also be used as meta key
        "text",                 // type of field
        array(
            "label"=>"Surname",
            "size"=>30,         // input size
            "memberslistcsv"=>true,
			"showrequired"=>false,
            "profile"=>false,    // show in user profile
            "required"=>true,    // make this field required
			"levels"=>$levels
        ));
	//Your email address *
    $fields[] = new PMProRH_Field(
        "bemail",        // input name, will also be used as meta key
        "text",                         // type of field
        array(
            "label"=>"Your Email Address",
            "size"=>30,                 // input size
			"showrequired"=>false,
            "memberslistcsv"=>true,
            "profile"=>false,            // show in user profile
            "required"=>true,            // make this field required
			"levels"=>$levels
        ));
    $fields[] = new PMProRH_Field(
        "billing_phone",        // input name, will also be used as meta key
        "text",                         // type of field
        array(
            "label"=>"Home Phone Number",
            "size"=>30,                 // input size
            "memberslistcsv"=>true,
			"showrequired"=>false,
            "profile"=>false,            // show in user profile
            "required"=>true,            // make this field required
			"levels"=>$levels
        ));

    $fields[] = new PMProRH_Field(
        "billing_phone_mobile",        // input name, will also be used as meta key
        "text",                         // type of field
        array(
            "label"=>"Mobile Number",
            "size"=>30,                 // input size
            "memberslistcsv"=>true,
			"showrequired"=>false,
            "profile"=>false,            // show in user profile
            "required"=>true,            // make this field required
			"levels"=>$levels
        ));
    $fields[] = new PMProRH_Field(
        "billing_address_1",          // input name, will also be used as meta key
        "text",                   // type of field
        array(
            "label"=>"Address",
            "size"=>30,
            "memberslistcsv"=>true,
			"showrequired"=>false,
            "profile"=>false,            // show in user profile
            "required"=>true,            // make this field required
			"levels"=>$levels
        ));
    $fields[] = new PMProRH_Field(
        "billing_address_2",          // input name, will also be used as meta key
        "text",                   // type of field
        array(
            "label"=>" ",
            "size"=>30,
            "memberslistcsv"=>true,
			"showrequired"=>false,
            "profile"=>false,            // show in user profile
            "required"=>false,           // make this field required
			"levels"=>$levels
        ));    
    $fields[] = new PMProRH_Field(
        "billing_city",          // input name, will also be used as meta key
        "text",                   // type of field
        array(
            "label"=>" ",
            "size"=>30,
            "memberslistcsv"=>true,
			"showrequired"=>false,
            "profile"=>false,            // show in user profile
            "required"=>false,           // make this field required
			"levels"=>$levels
        ));    
    $fields[] = new PMProRH_Field(
        "billing_state",          // input name, will also be used as meta key
        "text",                   // type of field
        array(
            "label"=>" ",
            "size"=>30,
            "memberslistcsv"=>true,
			"showrequired"=>false,
            "profile"=>false,            // show in user profile
            "required"=>false,           // make this field required
			"levels"=>$levels
        )); 
    $fields[] = new PMProRH_Field(
        "billing_postcode",          // input name, will also be used as meta key
        "text",                   // type of field
        array(
            "label"=>"Postcode",
            "size"=>30,
            "memberslistcsv"=>true,
			"showrequired"=>false,
            "profile"=>false,            // show in user profile
            "required"=>true,            // make this field required
			"levels"=>$levels
        ));

    $fields[] = new PMProRH_Field(
        "singing_experience",
        "textarea",                   // type of field
        array(
            "label"=> "Please tell us about any singing experience you have (if any), the part or section in the choir where you'd prefer to sing (if known) i.e. soprano , mezzo, tenor, baritone, or anything else you feel may be relevant . If unsure please put not known.",
            "cols"=>35,
            "rows"=>16,
            "memberslistcsv"=>true,
			"showrequired"=>false,
            "profile"=>true,          // show in user profile
            "required"=>true,         // make this field required
			"levels"=>$levels
        ));

    $fields[] = new PMProRH_Field(
        "choir_connection",
        "text",                   // type of field
        array(
            "label"=> "Please indicate if you have any connection to the Kinder Children’s Choirs. i.e. Messiah Chorus Member, Parent (or ex) Patron, audience supporter etc",
            "size"=>30,
            "memberslistcsv"=>true,
			"showrequired"=>false,
            "profile"=>true,          // show in user profile
            "required"=>false,        // make this field required
			"levels"=>$levels
        ));
	return $fields;
}

function add_summer_school_fields() {

	$levels=array(3,4);
    $fields = array();
	//Child’s Full name *
    $fields[] = new PMProRH_Field(
        "childs_name",            // input name, will also be used as meta key
        "text",                 // type of field
        array(
            "label"=>"Child’s Full Name",
            "size"=>35,         // input size
            "memberslistcsv"=>true,
			"showrequired"=>false,
            "profile"=>true,    // show in user profile
            "required"=>true,    // make this field required
			"levels"=>$levels
        ));

	//Child’s Date of Birth *
    $fields[] = new PMProRH_Field(
        "childs_dob",            // input name, will also be used as meta key
        "date",                 // type of field
        array(
            "label"=>"Child’s Date of Birth",
            "size"=>10,         // input size
            "memberslistcsv"=>true,
			"showrequired"=>false,
            "profile"=>true,    // show in user profile
            "required"=>true,    // make this field required
			"levels"=>$levels
        ));

	//Your name *
    $fields[] = new PMProRH_Field(
        "bfirstname",            // input name, will also be used as meta key
        "text",                 // type of field
        array(
            "label"=>"Your First Name",
            "size"=>35,         // input size
            "memberslistcsv"=>true,
			"showrequired"=>false,
            "profile"=>true,    // show in user profile
            "required"=>true,    // make this field required
			"levels"=>$levels
        ));
    $fields[] = new PMProRH_Field(
        "blastname",            // input name, will also be used as meta key
        "text",                 // type of field
        array(
            "label"=>"Your Surname",
            "size"=>35,         // input size
            "memberslistcsv"=>true,
			"showrequired"=>false,
            "profile"=>true,    // show in user profile
            "required"=>true,    // make this field required
			"levels"=>$levels
        ));
	//Your contact number *
    $fields[] = new PMProRH_Field(
        "billing_phone",        // input name, will also be used as meta key
        "text",                         // type of field
        array(
            "label"=>"Your Contact Number",
            "size"=>16,                 // input size
            "memberslistcsv"=>true,
			"showrequired"=>false,
            "profile"=>true,            // show in user profile
            "required"=>true,            // make this field required
			"levels"=>$levels
        ));
	//Your email address *
    $fields[] = new PMProRH_Field(
        "bemail",        // input name, will also be used as meta key
        "text",                         // type of field
        array(
            "label"=>"Your Email Address",
            "size"=>35,                 // input size
			"showrequired"=>false,
            "memberslistcsv"=>true,
            "profile"=>true,            // show in user profile
            "required"=>true,            // make this field required
			"levels"=>$levels
        ));

	//Your address *
    $fields[] = new PMProRH_Field(
        "billing_address_1",          // input name, will also be used as meta key
        "text",                   // type of field
        array(
            "label"=>"Your Address",
            "size"=>35,
            "memberslistcsv"=>true,
			"showrequired"=>false,
            "profile"=>false,            // show in user profile
            "required"=>true,            // make this field required
			"levels"=>$levels
        ));
    $fields[] = new PMProRH_Field(
        "billing_address_2",          // input name, will also be used as meta key
        "text",                   // type of field
        array(
            "label"=>" ",
            "size"=>35,
            "memberslistcsv"=>true,
			"showrequired"=>false,
            "profile"=>false,            // show in user profile
            "required"=>false,           // make this field required
			"levels"=>$levels
        ));    
    $fields[] = new PMProRH_Field(
        "billing_city",          // input name, will also be used as meta key
        "text",                   // type of field
        array(
            "label"=>" ",
            "size"=>35,
            "memberslistcsv"=>true,
			"showrequired"=>false,
            "profile"=>false,            // show in user profile
            "required"=>false,           // make this field required
			"levels"=>$levels
        ));    
    $fields[] = new PMProRH_Field(
        "billing_state",          // input name, will also be used as meta key
        "text",                   // type of field
        array(
            "label"=>" ",
            "size"=>35,
            "memberslistcsv"=>true,
			"showrequired"=>false,
            "profile"=>false,            // show in user profile
            "required"=>false,           // make this field required
			"levels"=>$levels
        )); 
    $fields[] = new PMProRH_Field(
        "billing_postcode",          // input name, will also be used as meta key
        "text",                   // type of field
        array(
            "label"=>"Postcode",
            "size"=>10,
            "memberslistcsv"=>true,
			"showrequired"=>false,
            "profile"=>false,            // show in user profile
            "required"=>true,            // make this field required
			"levels"=>$levels
        ));

	//Other emergency contact name*
    $fields[] = new PMProRH_Field(
        "contact_2_html_1",          // input name, will also be used as meta key
        "html",                   // type of field
        array(
            "label"=>"Other Emergency Contact:",
            "memberslistcsv"=>false,
			"showrequired"=>false,
            "profile"=>true,            // show in user profile
            "required"=>false,            // make this field required
			"levels"=>$levels
        ));
    $fields[] = new PMProRH_Field(
        "contact_2_name",          // input name, will also be used as meta key
        "text",                   // type of field
        array(
            "label"=>"Name",
            "size"=>35,
            "memberslistcsv"=>true,
			"showrequired"=>false,
            "profile"=>true,            // show in user profile
            "required"=>true,            // make this field required
			"levels"=>$levels
        ));
	//Phone number*
    $fields[] = new PMProRH_Field(
        "contact_2_number",          // input name, will also be used as meta key
        "text",                   // type of field
        array(
            "label"=>"Phone Number",
            "size"=>16,
            "memberslistcsv"=>true,
			"showrequired"=>false,
            "profile"=>true,            // show in user profile
            "required"=>true,            // make this field required
			"levels"=>$levels
        ));
	//Relationship to child*
    $fields[] = new PMProRH_Field(
        "contact_2_relationship",          // input name, will also be used as meta key
        "text",                   // type of field
        array(
            "label"=>"Relationship to child",
            "size"=>35,
            "memberslistcsv"=>true,
			"showrequired"=>false,
            "profile"=>true,            // show in user profile
            "required"=>true,            // make this field required
			"levels"=>$levels
        ));
	//Child’s school *
    $fields[] = new PMProRH_Field(
        "school",          // input name, will also be used as meta key
        "text",                   // type of field
        array(
            "label"=>"Child's School",
            "size"=>35,
            "memberslistcsv"=>true,
			"showrequired"=>false,
            "profile"=>true,            // show in user profile
            "required"=>true,            // make this field required
			"levels"=>$levels
        ));
	//Any relevant medical or dietary information*
    $fields[] = new PMProRH_Field(
        "medical",          // input name, will also be used as meta key
        "textarea",                   // type of field
        array(
            "label"=>"Any relevant medical or dietary information",
            "cols"=>40,
			"rows"=>4,
            "memberslistcsv"=>true,
			"showrequired"=>false,
            "profile"=>true,            // show in user profile
            "required"=>true,            // make this field required
			"levels"=>$levels
        ));
	//Digital Signature*
    $fields[] = new PMProRH_Field(
        "signature_html_1",          // input name, will also be used as meta key
        "html",                   // type of field
        array(
            "label"=>"I would like my child to attend the Voices Summer School.",
            "memberslistcsv"=>false,
			"divclass"=>"pmpro_html_text",
            "profile"=>false,            // show in user profile
            "required"=>false,            // make this field required
			"levels"=>$levels
        ));
    $fields[] = new PMProRH_Field(
        "signature_html_2",          // input name, will also be used as meta key
        "html",                   // type of field
        array(
            "label"=>"I understand that the full fee is £70, and include the deposit of £30 with this application form. (Please note that the deposit guarantees your child’s place, and is non-returnable.)",
            "memberslistcsv"=>false,
			"divclass"=>"pmpro_html_text",
            "profile"=>false,            // show in user profile
            "required"=>false,            // make this field required
			"levels"=>$levels
        ));
    $fields[] = new PMProRH_Field(
        "signature_html_3",          // input name, will also be used as meta key
        "html",                   // type of field
        array(
            "label"=>"This gives permission for use of image and sound recordings of course participants by Kinder Children’s Choirs.",
            "memberslistcsv"=>false,
			"divclass"=>"pmpro_html_text",
            "profile"=>false,            // show in user profile
            "required"=>false,            // make this field required
			"levels"=>$levels
        ));
    $fields[] = new PMProRH_Field(
        "signature",          // input name, will also be used as meta key
        "checkbox",                   // type of field
        array(
            "text"=>" ",
            "label"=>"Digital Signature *",
			"showrequired"=>false,
            "memberslistcsv"=>true,
            "profile"=>false,            // show in user profile
            "required"=>true,            // make this field required
			"levels"=>$levels
        ));

	return $fields;
}

/*
function add_experience_field_to_checkout()
{
    //don't break if Register Helper is not loaded
    if(!function_exists("pmprorh_add_registration_field"))
    {
        return false;
    }

    //define the fields
    $fields = array();

    $more_fields = array();
    $more_fields[] = new PMProRH_Field(
        "singing_experience",
        "textarea",                   // type of field
        array(
            "label"=>"Please tell us about any singing experience you have (if any), the part or section in the choir where you'd prefer to sing (if known) i.e. soprano , mezzo, tenor, baritone, or anything else you feel may be relevant . If unsure please put not known.",
            "cols"=>35,
            "rows"=>10,
            "memberslistcsv"=>true,
            "profile"=>true,          // show in user profile
            "required"=>false         // make this field required
        ));
    //add the fields to checkout page
    foreach($more_fields as $field)
        pmprorh_add_registration_field(
            "checkout_boxes", // location on checkout page
            $field            // PMProRH_Field object
        );

    //that's it. see the PMPro Register Helper readme for more information and examples.
}*/
add_action("init", "add_extra_fields_to_checkout");
