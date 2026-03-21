<?php
/**
* Plugin Name: Stripe Payment Plugin
* Plugin URI: https://www.dynamicdreamz.com/
* Description: A beautiful Stripe payment plugin with shortcode support and admin settings
* Version: 1.1.1
* Author: Dynamic Dreamz
* Author URI: https://www.dynamicdreamz.com/
* License: GPL v2 or later
* Text Domain: stripe-payment-plugin
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
exit;
}

// Define plugin constants
define('STRIPE_PAYMENT_PLUGIN_VERSION', '1.1.1');
define('STRIPE_PAYMENT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('STRIPE_PAYMENT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Increase WordPress HTTP timeout for Stripe API calls
add_filter('http_request_timeout', function($timeout) {
    return 15; // Give Stripe 15 seconds to respond instead of 5
});
add_filter('http_request_args', function($args) {
    $args['timeout'] = 15;
    return $args;
});

class Stripe_Payment_Plugin {

private static $instance = null;

public static function get_instance() {
    if (null === self::$instance) {
        self::$instance = new self();
    }
    return self::$instance;
}

private function __construct() {
    add_action('plugins_loaded', array($this, 'init'));
    register_activation_hook(__FILE__, array($this, 'activate'));
    register_deactivation_hook(__FILE__, array($this, 'deactivate'));
}

public function init() {
    // Load admin settings
    if (is_admin()) {
        require_once STRIPE_PAYMENT_PLUGIN_DIR . 'includes/admin-settings.php';
        new Stripe_Payment_Admin_Settings();
    }
    
    // Register shortcode
    add_shortcode('stripe_payment', array($this, 'payment_form_shortcode'));
    add_shortcode('stripe_upsell_logic', array($this, 'upsell_logic_shortcode'));
    add_shortcode('stripe_order_summary', array($this, 'order_summary_shortcode'));
    
    // Enqueue scripts and styles
    add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    
    // AJAX handlers
    add_action('wp_ajax_stripe_create_payment_intent', array($this, 'create_payment_intent'));
    add_action('wp_ajax_nopriv_stripe_create_payment_intent', array($this, 'create_payment_intent'));
    
    add_action('wp_ajax_stripe_create_subscription', array($this, 'create_subscription'));
    add_action('wp_ajax_nopriv_stripe_create_subscription', array($this, 'create_subscription'));
    
    add_action('wp_ajax_stripe_confirm_subscription', array($this, 'confirm_subscription'));
    add_action('wp_ajax_nopriv_stripe_confirm_subscription', array($this, 'confirm_subscription'));
    
    add_action('wp_ajax_stripe_confirm_payment', array($this, 'confirm_payment'));
    add_action('wp_ajax_nopriv_stripe_confirm_payment', array($this, 'confirm_payment'));

    add_action('wp_ajax_stripe_process_1_click_upsell', array($this, 'process_1_click_upsell'));
    add_action('wp_ajax_nopriv_stripe_process_1_click_upsell', array($this, 'process_1_click_upsell'));

    add_action('wp_ajax_check_kit_tag_for_upsell', array($this, 'check_kit_tag_for_upsell'));
    add_action('wp_ajax_nopriv_check_kit_tag_for_upsell', array($this, 'check_kit_tag_for_upsell'));
    
    add_action('wp_ajax_stripe_get_order_summary', array($this, 'get_order_summary'));
    add_action('wp_ajax_nopriv_stripe_get_order_summary', array($this, 'get_order_summary'));
}

public function activate() {
    // Set default options
    if (!get_option('stripe_payment_mode')) {
        update_option('stripe_payment_mode', 'test');
    }
}

public function deactivate() {
    // Cleanup if needed
}

public function enqueue_scripts() {
    // Only load on pages with the shortcode
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'stripe_payment')) {
        // Stripe.js
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), null, true);
        
        // Plugin scripts
        wp_enqueue_script(
            'stripe-payment-plugin',
            STRIPE_PAYMENT_PLUGIN_URL . 'assets/js/payment.js',
            array('jquery', 'stripe-js'),
            STRIPE_PAYMENT_PLUGIN_VERSION,
            true
        );
        
        // Plugin styles
        wp_enqueue_style(
            'stripe-payment-plugin',
            STRIPE_PAYMENT_PLUGIN_URL . 'assets/css/payment.css',
            array(),
            STRIPE_PAYMENT_PLUGIN_VERSION
        );
        
        // Localize script
        $mode = get_option('stripe_payment_mode', 'test');
        $publishable_key = ($mode === 'live') 
            ? get_option('stripe_payment_live_publishable_key', '')
            : get_option('stripe_payment_test_publishable_key', '');
        
        $thank_you_page = get_option('stripe_payment_thank_you_page', '');
        
        wp_localize_script('stripe-payment-plugin', 'stripePayment', array(
            'publishableKey' => $publishable_key,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('stripe_payment_nonce'),
            'thankYouPage' => $thank_you_page
        ));
    }
}

public function payment_form_shortcode($atts) {
    $atts = shortcode_atts(array(
        'amount' => '',
        'currency' => 'usd',
        'description' => 'Payment',
        'subscription' => 'false',
        'no_trial' => 'false',
        'trial_amount' => '',
        'trial_days' => '',
        'annual_amount' => '',
        'product_id' => '',
        'price_id' => '',
        'redirect_url' => ''
    ), $atts);
    
    // Check if subscription mode
    $is_subscription = ($atts['subscription'] === 'true' || $atts['subscription'] === '1');
    $no_trial = ($atts['no_trial'] === 'true' || $atts['no_trial'] === '1');
    
    if ($is_subscription) {
        // Subscription mode
        $trial_amount = !empty($atts['trial_amount']) ? floatval($atts['trial_amount']) : floatval(get_option('stripe_payment_subscription_trial_amount', '25.00'));
        $trial_days = !empty($atts['trial_days']) ? intval($atts['trial_days']) : intval(get_option('stripe_payment_subscription_trial_days', '30'));
        $annual_amount = !empty($atts['annual_amount']) ? floatval($atts['annual_amount']) : floatval(get_option('stripe_payment_subscription_annual_amount', '12.00'));
        
        if ($no_trial) {
            $trial_days = 0;
            $trial_amount = 0.0;
        }
        
        $trial_amount_cents = intval($trial_amount * 100);
        $annual_amount_cents = intval($annual_amount * 100);
        $currency = sanitize_text_field($atts['currency']);
        
        ob_start();
        $template_vars = array(
            'is_subscription' => true,
            'no_trial' => $no_trial,
            'trial_amount' => $trial_amount,
            'trial_amount_cents' => $trial_amount_cents,
            'trial_days' => $trial_days,
            'annual_amount' => $annual_amount,
            'annual_amount_cents' => $annual_amount_cents,
            'currency' => $currency,
            'amount' => $no_trial ? $annual_amount : $trial_amount,
            'amount_cents' => $no_trial ? $annual_amount_cents : $trial_amount_cents,
            'product_id' => sanitize_text_field($atts['product_id']),
            'price_id' => sanitize_text_field($atts['price_id']),
            'redirect_url' => esc_url_raw($atts['redirect_url'])
        );
        extract($template_vars);
        include STRIPE_PAYMENT_PLUGIN_DIR . 'templates/payment-form.php';
        return ob_get_clean();
    } else {
        // One-time payment mode
        $amount = !empty($atts['amount']) ? floatval($atts['amount']) : 1.00;
        $amount_cents = intval($amount * 100);
        $currency = sanitize_text_field($atts['currency']);
        
        ob_start();
        $template_vars = array(
            'is_subscription' => false,
            'amount' => $amount,
            'amount_cents' => $amount_cents,
            'currency' => $currency,
            'product_id' => sanitize_text_field($atts['product_id']),
            'price_id' => sanitize_text_field($atts['price_id']),
            'redirect_url' => esc_url_raw($atts['redirect_url'])
        );
        extract($template_vars);
        include STRIPE_PAYMENT_PLUGIN_DIR . 'templates/payment-form.php';
        return ob_get_clean();
    }
}

public function create_payment_intent() {
    check_ajax_referer('stripe_payment_nonce', 'nonce');
    
    $amount = isset($_POST['amount']) ? intval($_POST['amount']) : 0;
    $currency = isset($_POST['currency']) ? sanitize_text_field($_POST['currency']) : 'usd';
    $customer_email = isset($_POST['customer_email']) ? sanitize_email($_POST['customer_email']) : '';
    $customer_name = isset($_POST['customer_name']) ? sanitize_text_field($_POST['customer_name']) : '';
    $customer_phone = isset($_POST['customer_phone']) ? sanitize_text_field($_POST['customer_phone']) : '';
    $address_line1 = isset($_POST['address_line1']) ? sanitize_text_field($_POST['address_line1']) : '';
    $address_line2 = isset($_POST['address_line2']) ? sanitize_text_field($_POST['address_line2']) : '';
    $address_city = isset($_POST['address_city']) ? sanitize_text_field($_POST['address_city']) : '';
    $address_state = isset($_POST['address_state']) ? sanitize_text_field($_POST['address_state']) : '';
    $address_postal_code = isset($_POST['address_postal_code']) ? sanitize_text_field($_POST['address_postal_code']) : '';
    $address_country = isset($_POST['address_country']) ? sanitize_text_field($_POST['address_country']) : '';
    
    if ($amount <= 0) {
        wp_send_json_error(array('message' => 'Invalid amount'));
        return;
    }
    
    $mode = get_option('stripe_payment_mode', 'test');
    $secret_key = ($mode === 'live')
        ? get_option('stripe_payment_live_secret_key', '')
        : get_option('stripe_payment_test_secret_key', '');
    
    if (empty($secret_key)) {
        wp_send_json_error(array('message' => 'Stripe API key not configured'));
        return;
    }
    
    // Create customer with all details
    $customer_id = $this->create_or_update_customer(
        $secret_key,
        $customer_email,
        $customer_name,
        $customer_phone,
        $address_line1,
        $address_line2,
        $address_city,
        $address_state,
        $address_postal_code,
        $address_country
    );
    
    // Build payment intent body
    $payment_intent_body = array(
        'amount' => $amount,
        'currency' => $currency,
        'automatic_payment_methods[enabled]' => 'true',
    );
    
    // Attach customer to payment intent if we created one
    if (!empty($customer_id)) {
        $payment_intent_body['customer'] = $customer_id;
    }
    
    // Create payment intent via Stripe API
    $response = wp_remote_post('https://api.stripe.com/v1/payment_intents', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $secret_key,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ),
        'body' => $payment_intent_body,
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => $response->get_error_message()));
        return;
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (isset($body['error'])) {
        wp_send_json_error(array('message' => $body['error']['message']));
        return;
    }
    
    wp_send_json_success(array(
        'clientSecret' => $body['client_secret'],
        'customerId' => $customer_id
    ));
}

public function create_subscription() {
check_ajax_referer('stripe_payment_nonce', 'nonce');

$price_id = isset($_POST['price_id']) ? sanitize_text_field($_POST['price_id']) : '';

if (empty($price_id)) {
    wp_send_json_error(array('message' => 'Price ID is required for subscriptions.'));
    return;
}

$secret_key = self::get_stripe_secret_key();

// Create setup intent immediately using the existing Price ID
$response = wp_remote_post('https://api.stripe.com/v1/setup_intents', array(
    'headers' => array('Authorization' => 'Bearer ' . $secret_key),
    'body' => array('payment_method_types[]' => 'card', 'usage' => 'off_session'),
));

$body = json_decode(wp_remote_retrieve_body($response), true);

wp_send_json_success(array(
    'setupIntentClientSecret' => $body['client_secret'],
    'priceId' => $price_id,
    'trialAmount' => isset($_POST['trial_amount']) ? intval($_POST['trial_amount']) : 0,
    'trialDays' => isset($_POST['trial_days']) ? intval($_POST['trial_days']) : 30,
    'currency' => isset($_POST['currency']) ? sanitize_text_field($_POST['currency']) : 'usd'
));
}

public function confirm_subscription() {
    check_ajax_referer('stripe_payment_nonce', 'nonce');
    
    $payment_method_id = isset($_POST['payment_method_id']) ? sanitize_text_field($_POST['payment_method_id']) : '';
    $price_id = isset($_POST['price_id']) ? sanitize_text_field($_POST['price_id']) : '';
    $trial_amount = isset($_POST['trial_amount']) ? intval($_POST['trial_amount']) : 0;
    $trial_days = isset($_POST['trial_days']) ? intval($_POST['trial_days']) : 30;
    $currency = isset($_POST['currency']) ? sanitize_text_field($_POST['currency']) : 'usd';
    $no_trial = isset($_POST['no_trial']) ? (sanitize_text_field($_POST['no_trial']) === '1') : false;
    $customer_email = isset($_POST['customer_email']) ? sanitize_email($_POST['customer_email']) : '';
    $customer_name = isset($_POST['customer_name']) ? sanitize_text_field($_POST['customer_name']) : '';
    $customer_phone = isset($_POST['customer_phone']) ? sanitize_text_field($_POST['customer_phone']) : '';
    $address_line1 = isset($_POST['address_line1']) ? sanitize_text_field($_POST['address_line1']) : '';
    $address_line2 = isset($_POST['address_line2']) ? sanitize_text_field($_POST['address_line2']) : '';
    $address_city = isset($_POST['address_city']) ? sanitize_text_field($_POST['address_city']) : '';
    $address_state = isset($_POST['address_state']) ? sanitize_text_field($_POST['address_state']) : '';
    $address_postal_code = isset($_POST['address_postal_code']) ? sanitize_text_field($_POST['address_postal_code']) : '';
    $address_country = isset($_POST['address_country']) ? sanitize_text_field($_POST['address_country']) : '';
    
    if (empty($payment_method_id) || empty($price_id)) {
        wp_send_json_error(array('message' => 'Payment method and price ID required'));
        return;
    }
    
    $mode = get_option('stripe_payment_mode', 'test');
    $secret_key = ($mode === 'live')
        ? get_option('stripe_payment_live_secret_key', '')
        : get_option('stripe_payment_test_secret_key', '');
    
    if (empty($secret_key)) {
        wp_send_json_error(array('message' => 'Stripe API key not configured'));
        return;
    }
    
    // Create customer with all details
    $customer_id = $this->create_or_update_customer(
        $secret_key,
        $customer_email,
        $customer_name,
        $customer_phone,
        $address_line1,
        $address_line2,
        $address_city,
        $address_state,
        $address_postal_code,
        $address_country
    );
    
    if (empty($customer_id)) {
        wp_send_json_error(array('message' => 'Failed to create customer'));
        return;
    }

    // Check if this customer already has an active subscription to this product
    $subs_response = wp_remote_get('https://api.stripe.com/v1/subscriptions?customer=' . $customer_id . '&status=active', array(
        'headers' => array('Authorization' => 'Bearer ' . $secret_key)
    ));

    if (!is_wp_error($subs_response)) {
        $subs_body = json_decode(wp_remote_retrieve_body($subs_response), true);
        if (!empty($subs_body['data'])) {
            foreach ($subs_body['data'] as $sub) {
                foreach ($sub['items']['data'] as $item) {
                    if ($item['price']['id'] === $price_id) {
                        wp_send_json_error(array('message' => 'It looks like you are already subscribed! Please check your email or contact support.'));
                        return; // Stop the transaction immediately
                    }
                }
            }
        }
    }
    
    // Attach payment method to customer
    $attach_response = wp_remote_post('https://api.stripe.com/v1/payment_methods/' . $payment_method_id . '/attach', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $secret_key,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ),
        'body' => array(
            'customer' => $customer_id,
        ),
    ));
    
    // If attachment fails, log but continue (payment method might already be attached)
    if (is_wp_error($attach_response)) {
        error_log('Failed to attach payment method to customer: ' . $attach_response->get_error_message());
    } else {
        // Update payment method billing details with phone and address
        $billing_update = array();
        
        if (!empty($customer_phone)) {
            $billing_update['billing_details[phone]'] = $customer_phone;
        }
        
        if (!empty($customer_name)) {
            $billing_update['billing_details[name]'] = $customer_name;
        }
        
        if (!empty($customer_email)) {
            $billing_update['billing_details[email]'] = $customer_email;
        }
        
        if (!empty($address_line1) && !empty($address_city) && !empty($address_state) && !empty($address_postal_code) && !empty($address_country)) {
            $billing_update['billing_details[address][line1]'] = $address_line1;
            if (!empty($address_line2)) {
                $billing_update['billing_details[address][line2]'] = $address_line2;
            }
            $billing_update['billing_details[address][city]'] = $address_city;
            $billing_update['billing_details[address][state]'] = $address_state;
            $billing_update['billing_details[address][postal_code]'] = $address_postal_code;
            $billing_update['billing_details[address][country]'] = $address_country;
        }
        
        if (!empty($billing_update)) {
            $pm_update_response = wp_remote_post('https://api.stripe.com/v1/payment_methods/' . $payment_method_id, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $secret_key,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ),
                'body' => $billing_update,
            ));
            
            // Log if update fails (but don't fail the whole process)
            if (is_wp_error($pm_update_response)) {
                error_log('Failed to update payment method billing details: ' . $pm_update_response->get_error_message());
            }
        }
    }
    
    // Create subscription
    // - When no_trial=true: charge immediately via invoice PaymentIntent (SCA-safe)
    // - Otherwise: create subscription with a trial period and (optionally) a separate trial charge
    $subscription_body_params = array(
        'customer' => $customer_id,
        'items[0][price]' => $price_id,
        'default_payment_method' => $payment_method_id,
        'expand[]' => 'latest_invoice.payment_intent',
    );
    
    if ($no_trial || intval($trial_days) <= 0) {
        // Charge immediately; the client will confirm the invoice payment intent if required
        $subscription_body_params['payment_behavior'] = 'allow_incomplete';
        $subscription_body_params['payment_settings[save_default_payment_method]'] = 'on_subscription';
    } else {
        $subscription_body_params['trial_period_days'] = $trial_days;
    }
    
    $subscription_response = wp_remote_post('https://api.stripe.com/v1/subscriptions', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $secret_key,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ),
        'body' => $subscription_body_params,
    ));
    
    if (is_wp_error($subscription_response)) {
        wp_send_json_error(array('message' => $subscription_response->get_error_message()));
        return;
    }
    
    $subscription_body = json_decode(wp_remote_retrieve_body($subscription_response), true);
    
    if (isset($subscription_body['error'])) {
        wp_send_json_error(array('message' => $subscription_body['error']['message']));
        return;
    }
    
    // Optional: Charge a "trial amount" immediately ONLY when a trial exists and amount > 0
    if (!$no_trial && intval($trial_days) > 0 && intval($trial_amount) > 0) {
        $payment_intent_response = wp_remote_post('https://api.stripe.com/v1/payment_intents', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'amount' => $trial_amount,
                'currency' => $currency,
                'customer' => $customer_id,
                'payment_method' => $payment_method_id,
                'off_session' => 'true',
                'confirm' => 'true',
                'description' => 'Trial period payment',
            ),
        ));
        
        if (is_wp_error($payment_intent_response)) {
            error_log('Subscription created but trial payment failed: ' . $payment_intent_response->get_error_message());
        } else {
            $payment_intent_body = json_decode(wp_remote_retrieve_body($payment_intent_response), true);
            if (isset($payment_intent_body['error'])) {
                error_log('Subscription created but trial payment failed: ' . $payment_intent_body['error']['message']);
            }
        }
    }
    
    // Log successful subscription (you can extend this to save to database)
    do_action('stripe_subscription_success', $subscription_body['id'], $customer_id);
    
    $latest_invoice_pi_client_secret = '';
    if (isset($subscription_body['latest_invoice']['payment_intent']['client_secret'])) {
        $latest_invoice_pi_client_secret = $subscription_body['latest_invoice']['payment_intent']['client_secret'];
    }
    
    wp_send_json_success(array(
        'message' => 'Subscription created successfully!',
        'subscription_id' => $subscription_body['id'],
        'customer_id' => $customer_id,
        'invoicePaymentIntentClientSecret' => $latest_invoice_pi_client_secret
    ));
}

public function confirm_payment() {
    check_ajax_referer('stripe_payment_nonce', 'nonce');
    
    $payment_intent_id = isset($_POST['payment_intent_id']) ? sanitize_text_field($_POST['payment_intent_id']) : '';
    
    if (empty($payment_intent_id)) {
        wp_send_json_error(array('message' => 'Payment intent ID required'));
        return;
    }
    
    // Log successful payment (you can extend this to save to database)
    do_action('stripe_payment_success', $payment_intent_id);
    
    wp_send_json_success(array(
        'message' => 'Payment successful!',
        'payment_intent_id' => $payment_intent_id
    ));
}

/**
 * Create or update a Stripe customer with all details
 */
private function create_or_update_customer($secret_key, $customer_email, $customer_name, $customer_phone, $address_line1, $address_line2, $address_city, $address_state, $address_postal_code, $address_country) {
    if (empty($customer_email) || empty($customer_name)) {
        return null;
    }
    
    $customer_id = null;

    // 1. Search for existing customer by email
    $search_response = wp_remote_get('https://api.stripe.com/v1/customers?email=' . urlencode($customer_email) . '&limit=1', array(
        'headers' => array('Authorization' => 'Bearer ' . $secret_key)
    ));

    if (!is_wp_error($search_response)) {
        $search_body = json_decode(wp_remote_retrieve_body($search_response), true);
        if (!empty($search_body['data'])) {
            $customer_id = $search_body['data'][0]['id'];
        }
    }

    // 2. Build the data array (including the phone and metadata we added earlier!)
    $customer_data = array(
        'email' => $customer_email,
        'name' => $customer_name,
        'metadata' => array(
            'birthday' => isset($_POST['birthday']) ? sanitize_text_field($_POST['birthday']) : '',
            'gender'   => isset($_POST['gender']) ? sanitize_text_field($_POST['gender']) : '',
        )
    );

    if (!empty($customer_phone)) {
        $customer_data['phone'] = $customer_phone;
    }

    if (!empty($address_line1) && !empty($address_city) && !empty($address_state) && !empty($address_postal_code) && !empty($address_country)) {
        $customer_data['address[line1]'] = $address_line1;
        if (!empty($address_line2)) {
            $customer_data['address[line2]'] = $address_line2;
        }
        $customer_data['address[city]'] = $address_city;
        $customer_data['address[state]'] = $address_state;
        $customer_data['address[postal_code]'] = $address_postal_code;
        $customer_data['address[country]'] = $address_country;
    }

    // 3. Update existing or Create new
    if ($customer_id) {
        $update_response = wp_remote_post('https://api.stripe.com/v1/customers/' . $customer_id, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => $customer_data,
        ));
        return $customer_id;
    } else {
        $customer_response = wp_remote_post('https://api.stripe.com/v1/customers', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => $customer_data,
        ));

        if (is_wp_error($customer_response)) return null;
        $customer_body = json_decode(wp_remote_retrieve_body($customer_response), true);
        if (isset($customer_body['error']) || !isset($customer_body['id'])) return null;
        
        return $customer_body['id'];
    }
}

    public function process_1_click_upsell() {
        $customer_id = isset($_POST['customer_id']) ? sanitize_text_field($_POST['customer_id']) : '';
        $amount = isset($_POST['amount']) ? intval($_POST['amount']) : 0;
        $product_id = isset($_POST['product_id']) ? sanitize_text_field($_POST['product_id']) : '';
        $product_name = isset($_POST['product_name']) ? sanitize_text_field($_POST['product_name']) : 'Special Offer';
        
        if (empty($customer_id) || $amount <= 0) {
            wp_send_json_error(array('message' => 'Invalid data provided.'));
            return;
        }
        
        $secret_key = self::get_stripe_secret_key();
        
        $pm_response = wp_remote_get('https://api.stripe.com/v1/payment_methods?customer=' . $customer_id . '&type=card', array(
            'headers' => array('Authorization' => 'Bearer ' . $secret_key)
        ));
        
        if (is_wp_error($pm_response)) {
            wp_send_json_error(array('message' => 'Could not connect to Stripe.'));
            return;
        }
        
        $pm_body = json_decode(wp_remote_retrieve_body($pm_response), true);
        if (empty($pm_body['data'])) {
            wp_send_json_error(array('message' => 'No card on file for this customer.'));
            return;
        }
        
        $payment_method_id = $pm_body['data'][0]['id'];
        $description = '1-Click Upsell: ' . $product_name;
        
        $charge_response = wp_remote_post('https://api.stripe.com/v1/payment_intents', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'amount' => $amount,
                'currency' => 'usd',
                'customer' => $customer_id,
                'payment_method' => $payment_method_id,
                'off_session' => 'true', 
                'confirm' => 'true',
                'description' => $description,
                'metadata[product_id]' => $product_id
            ),
        ));
        
        $charge_body = json_decode(wp_remote_retrieve_body($charge_response), true);
        
        if (isset($charge_body['error'])) {
            wp_send_json_error(array('message' => $charge_body['error']['message']));
            return;
        }
        
        wp_send_json_success(array('message' => 'Purchase successful!'));
    }
    
    public function check_kit_tag_for_upsell() {
        $customer_id = isset($_POST['customer_id']) ? sanitize_text_field($_POST['customer_id']) : '';
        $tag_id = isset($_POST['tag_id']) ? trim(sanitize_text_field($_POST['tag_id'])) : '';
        
        $kit_api_secret = 'wCB8viFk7POOCo2lTvAvM7pXpsspvXWLwe0NNuEYAqs'; 
        
        if (empty($customer_id) || empty($tag_id)) {
            wp_send_json_error(array('message' => 'Missing customer ID or tag ID.'));
            return;
        }
        
        $stripe_secret_key = self::get_stripe_secret_key();
        
        // 1. Get email from Stripe
        $customer_response = wp_remote_get('https://api.stripe.com/v1/customers/' . $customer_id, array(
            'headers' => array('Authorization' => 'Bearer ' . $stripe_secret_key)
        ));
        
        if (is_wp_error($customer_response)) {
            wp_send_json_error(array('message' => 'Could not connect to Stripe.'));
            return;
        }
        
        $customer_body = json_decode(wp_remote_retrieve_body($customer_response), true);
        $email = isset($customer_body['email']) ? $customer_body['email'] : '';
        
        if (empty($email)) {
            wp_send_json_error(array('message' => 'No email found in Stripe for this customer.'));
            return;
        }
        
        // 2. Search Kit for the Subscriber ID
        $kit_search_url = 'https://api.convertkit.com/v3/subscribers?api_secret=' . $kit_api_secret . '&email_address=' . urlencode(trim(strtolower($email)));
        $kit_search_response = wp_remote_get($kit_search_url);
        
        if (is_wp_error($kit_search_response)) {
            wp_send_json_error(array('message' => 'Kit Search API Error: ' . $kit_search_response->get_error_message()));
            return;
        }
        
        $kit_search_body = json_decode(wp_remote_retrieve_body($kit_search_response), true);
        
        if (empty($kit_search_body['subscribers']) || !isset($kit_search_body['subscribers'][0]['id'])) {
            wp_send_json_success(array('has_tag' => false, 'debug_message' => 'User not found in Kit database.'));
            return;
        }
        
        $subscriber_id = $kit_search_body['subscribers'][0]['id'];
        
        // 3. Ask Kit for the specific tags belonging to this Subscriber ID
        $kit_tags_url = 'https://api.convertkit.com/v3/subscribers/' . $subscriber_id . '/tags?api_secret=' . $kit_api_secret;
        $kit_tags_response = wp_remote_get($kit_tags_url);
        
        if (is_wp_error($kit_tags_response)) {
            wp_send_json_error(array('message' => 'Kit Tags API Error: ' . $kit_tags_response->get_error_message()));
            return;
        }
        
        $kit_tags_body = json_decode(wp_remote_retrieve_body($kit_tags_response), true);
        
        $has_tag = false;
        $found_tags = array();
        
        // 4. Check if our target Tag ID is in their list of tags
        if (!empty($kit_tags_body['tags'])) {
            foreach ($kit_tags_body['tags'] as $tag) {
                $found_tags[] = (string)$tag['id']; // Log all tags they have for debugging
                if ((string)$tag['id'] === $tag_id) {
                    $has_tag = true;
                }
            }
        }
        
        // Return the final verdict plus the raw data so we can debug if it fails!
        wp_send_json_success(array(
            'has_tag' => $has_tag,
            'debug' => array(
                'email' => $email,
                'subscriber_id' => $subscriber_id,
                'searching_for_tag' => $tag_id,
                'tags_found_on_user' => $found_tags
            )
        ));
    }
    
    public function upsell_logic_shortcode($atts) {
        $atts = shortcode_atts(array(
            'amount' => '',
            'product_id' => '',
            'product_name' => '',
            'next_url' => '',
            'check_tag_id' => '', 
            'skip_url' => ''
        ), $atts);
        
        $amount_cents = intval(floatval($atts['amount']) * 100);
        
        ob_start();
?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        const customerId = urlParams.get('cus');
        const yesButton = document.getElementById('upsell-yes');
        const noButton = document.getElementById('upsell-no');
        
        const amount = "<?php echo esc_js($amount_cents); ?>";
        const productId = "<?php echo esc_js($atts['product_id']); ?>";
        const productName = "<?php echo esc_js($atts['product_name']); ?>";
        const nextUrl = "<?php echo esc_url_raw($atts['next_url']); ?>";
        const checkTagId = "<?php echo esc_js($atts['check_tag_id']); ?>";
        const skipUrl = "<?php echo esc_url_raw($atts['skip_url']); ?>";
        
        function routeUser(buttonElement, isPurchase) {
            if(!customerId) {
                alert("Session expired. Please return to the checkout page.");
                return;
            }
            
            const originalText = buttonElement.innerText;
            buttonElement.innerText = "Processing...";
            buttonElement.style.opacity = "0.7";
            buttonElement.style.pointerEvents = "none";
            
            let purchasePromise = Promise.resolve({ success: true }); 
            
            if (isPurchase) {
                const purchaseData = new FormData();
                purchaseData.append('action', 'stripe_process_1_click_upsell');
                purchaseData.append('customer_id', customerId);
                purchaseData.append('amount', amount);
                purchaseData.append('product_id', productId);
                purchaseData.append('product_name', productName);
                
                purchasePromise = fetch('/wp-admin/admin-ajax.php', { method: 'POST', body: purchaseData })
                .then(res => res.json());
            }
            
            purchasePromise.then(purchaseResult => {
                if(!purchaseResult.success) {
                    alert("Payment failed: " + (purchaseResult.data?.message || "Please check your card."));
                    buttonElement.innerText = originalText;
                    buttonElement.style.opacity = "1";
                    buttonElement.style.pointerEvents = "auto";
                    return;
                }
                
                // --- NEW CONFIRMATION LOGIC ---
                if (isPurchase) {
                    buttonElement.innerText = "🎉 Success! Loading next step...";
                    buttonElement.style.backgroundColor = "#28a745";
                    buttonElement.style.color = "#ffffff";
                    buttonElement.style.border = "none";
                    buttonElement.style.opacity = "1";
                } else {
                    buttonElement.innerText = "Loading next step...";
                }
                
                const redirect = (url) => {
                    const sep = url.includes('?') ? '&' : '?';
                    // Added a 1-second delay so they can actually read the success message!
                    setTimeout(() => {
                        window.location.href = url + sep + 'cus=' + customerId;
                    }, 1000); 
                };
                // ------------------------------
                
                if (!checkTagId || checkTagId === '') {
                    redirect(nextUrl);
                    return;
                }
                
                // Check ConvertKit tags
                const tagCheckData = new FormData();
                tagCheckData.append('action', 'check_kit_tag_for_upsell');
                tagCheckData.append('customer_id', customerId);
                tagCheckData.append('tag_id', checkTagId);
                
                fetch('/wp-admin/admin-ajax.php', { method: 'POST', body: tagCheckData })
                .then(res => res.json())
                .then(tagResult => {
                    if(!tagResult.success) {
                        console.error("API Error:", tagResult.data.message);
                        redirect(nextUrl); 
                        return;
                    }
                    
                    if(tagResult.data.has_tag === true) {
                        redirect(skipUrl); // Skip!
                    } else {
                        redirect(nextUrl); // Don't have it, go to offer
                    }
                })
                .catch(err => {
                    console.error("Network Error during tag check:", err);
                    redirect(nextUrl);
                });
            })
            .catch(err => {
                console.error(err);
                alert("A network error occurred.");
                buttonElement.innerText = originalText;
                buttonElement.style.opacity = "1";
                buttonElement.style.pointerEvents = "auto";
            });
        }
        
        if(noButton) {
            noButton.addEventListener('click', function(e) {
                e.preventDefault();
                routeUser(noButton, false); 
            });
        }
        if(yesButton) {
            yesButton.addEventListener('click', function(e) {
                e.preventDefault();
                routeUser(yesButton, true); 
            });
        }
    });
    </script>
    <?php
    return ob_get_clean();
    }

    public function get_order_summary() {
        $customer_id = isset($_POST['customer_id']) ? sanitize_text_field($_POST['customer_id']) : '';
        
        if (empty($customer_id)) {
            wp_send_json_error(array('message' => 'No customer ID provided.'));
            return;
        }

        $secret_key = self::get_stripe_secret_key();

        // 1. Get the Customer's Name and Email
        $customer_response = wp_remote_get('https://api.stripe.com/v1/customers/' . $customer_id, array(
            'headers' => array('Authorization' => 'Bearer ' . $secret_key)
        ));

        $customer_body = json_decode(wp_remote_retrieve_body($customer_response), true);
        
        if (isset($customer_body['error'])) {
            wp_send_json_error(array('message' => 'Customer not found.'));
            return;
        }

        $purchases = array();
        
        // NEW: Only look for purchases made in the last 2 hours (7200 seconds)
        $time_window = time() - 7200; 

        // 2. Get the Subscription (Filtered by time)
        $subs_url = 'https://api.stripe.com/v1/subscriptions?customer=' . $customer_id . '&status=active&created[gte]=' . $time_window;
        $subs_response = wp_remote_get($subs_url, array(
            'headers' => array('Authorization' => 'Bearer ' . $secret_key)
        ));
        $subs_body = json_decode(wp_remote_retrieve_body($subs_response), true);

        if (!empty($subs_body['data'])) {
            foreach ($subs_body['data'] as $sub) {
                $purchases[] = array(
                    'name' => 'Daily Texts Subscription',
                    'amount' => number_format($sub['plan']['amount'] / 100, 2),
                    'currency' => strtoupper($sub['plan']['currency'])
                );
            }
        }

        // 3. Get the One-Time Upsells (Filtered by time)
        $pi_url = 'https://api.stripe.com/v1/payment_intents?customer=' . $customer_id . '&created[gte]=' . $time_window;
        $pi_response = wp_remote_get($pi_url, array(
            'headers' => array('Authorization' => 'Bearer ' . $secret_key)
        ));
        $pi_body = json_decode(wp_remote_retrieve_body($pi_response), true);

        if (!empty($pi_body['data'])) {
            foreach ($pi_body['data'] as $pi) {
                // Only grab successful payments explicitly from our upsell funnels
                if ($pi['status'] === 'succeeded' && strpos($pi['description'], '1-Click Upsell') !== false) {
                    $purchases[] = array(
                        'name' => str_replace('1-Click Upsell: ', '', $pi['description']), 
                        'amount' => number_format($pi['amount'] / 100, 2),
                        'currency' => strtoupper($pi['currency'])
                    );
                }
            }
        }

        wp_send_json_success(array(
            'name' => isset($customer_body['name']) ? $customer_body['name'] : 'Awesome Human',
            'email' => isset($customer_body['email']) ? $customer_body['email'] : '',
            'purchases' => $purchases
        ));
    }

    public function order_summary_shortcode() {
        ob_start();
        ?>
        <style>
            .stripe-receipt-box {
                background: #fff; border: 1px solid #e5e5e5; border-radius: 12px;
                padding: 30px; max-width: 500px; margin: 0 auto; box-shadow: 0 4px 15px rgba(0,0,0,0.05);
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
            .stripe-receipt-box h3 { margin-top: 0; margin-bottom: 5px; color: #1a1a1a; font-size: 22px; }
            .stripe-receipt-box p.receipt-email { color: #666; font-size: 14px; margin-bottom: 25px; margin-top: 0; }
            .receipt-item { display: flex; justify-content: space-between; border-bottom: 1px dashed #e5e5e5; padding: 12px 0; font-size: 15px; color: #333; }
            .receipt-item:last-child { border-bottom: none; }
            .receipt-total { display: flex; justify-content: space-between; font-weight: bold; font-size: 18px; color: #1a1a1a; margin-top: 15px; padding-top: 15px; border-top: 2px solid #333; }
            #receipt-loader { text-align: center; color: #666; padding: 20px 0; }
        </style>

        <div class="stripe-receipt-box" id="stripe-receipt-wrapper">
            <div id="receipt-loader">Generating your receipt...</div>
            <div id="receipt-content" style="display: none;">
                <h3>Thanks, <span id="receipt-name"></span>!</h3>
                <p class="receipt-email">A confirmation has been sent to <span id="receipt-email-val"></span></p>
                
                <div style="font-weight:600; font-size:12px; text-transform:uppercase; color:#999; margin-bottom: 10px;">Your Purchases</div>
                <div id="receipt-items-container"></div>
                
                <div class="receipt-total">
                    <span>Total</span>
                    <span id="receipt-total-val">$0.00</span>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            const customerId = urlParams.get('cus');
            const wrapper = document.getElementById('stripe-receipt-wrapper');
            
            if(!customerId) {
                wrapper.style.display = 'none'; // Hide if they load the page directly with no ID
                return;
            }

            const formData = new FormData();
            formData.append('action', 'stripe_get_order_summary');
            formData.append('customer_id', customerId);

            fetch('/wp-admin/admin-ajax.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                document.getElementById('receipt-loader').style.display = 'none';
                
                if(data.success) {
                    document.getElementById('receipt-content').style.display = 'block';
                    document.getElementById('receipt-name').innerText = data.data.name.split(' ')[0]; // First name only
                    document.getElementById('receipt-email-val').innerText = data.data.email;
                    
                    const container = document.getElementById('receipt-items-container');
                    let total = 0;

                    if(data.data.purchases.length === 0) {
                        container.innerHTML = '<div class="receipt-item">No purchases found.</div>';
                    } else {
                        data.data.purchases.forEach(item => {
                            total += parseFloat(item.amount);
                            container.innerHTML += `
                                <div class="receipt-item">
                                    <span>${item.name}</span>
                                    <span>$${item.amount}</span>
                                </div>
                            `;
                        });
                        document.getElementById('receipt-total-val').innerText = '$' + total.toFixed(2);
                    }
                } else {
                    wrapper.innerHTML = '<p style="text-align:center; color:#666;">Could not load order details.</p>';
                }
            })
            .catch(err => {
                wrapper.style.display = 'none';
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

public static function get_stripe_secret_key() {
    $mode = get_option('stripe_payment_mode', 'test');
    return ($mode === 'live')
        ? get_option('stripe_payment_live_secret_key', '')
        : get_option('stripe_payment_test_secret_key', '');
}
}

// Initialize the plugin
Stripe_Payment_Plugin::get_instance();
