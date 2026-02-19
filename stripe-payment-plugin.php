<?php
/**
 * Plugin Name: Stripe Payment Plugin
 * Plugin URI: https://www.dynamicdreamz.com/
 * Description: A beautiful Stripe payment plugin with shortcode support and admin settings
 * Version: 1.0.1
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
define('STRIPE_PAYMENT_PLUGIN_VERSION', '1.0.1');
define('STRIPE_PAYMENT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('STRIPE_PAYMENT_PLUGIN_URL', plugin_dir_url(__FILE__));

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
            'annual_amount' => ''
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
                'amount_cents' => $no_trial ? $annual_amount_cents : $trial_amount_cents
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
                'currency' => $currency
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
        
        $trial_amount = isset($_POST['trial_amount']) ? intval($_POST['trial_amount']) : 0;
        $annual_amount = isset($_POST['annual_amount']) ? intval($_POST['annual_amount']) : 0;
        $trial_days = isset($_POST['trial_days']) ? intval($_POST['trial_days']) : 30;
        $currency = isset($_POST['currency']) ? sanitize_text_field($_POST['currency']) : 'usd';
        
        // Trial amount may be 0 (free trial). Annual amount must be > 0.
        if ($trial_amount < 0 || $annual_amount <= 0) {
            wp_send_json_error(array('message' => 'Invalid subscription amounts'));
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
        
        // First, create a product
        $product_response = wp_remote_post('https://api.stripe.com/v1/products', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'name' => 'Annual Subscription',
                'description' => 'Annual subscription with ' . $trial_days . ' day trial'
            ),
        ));
        
        if (is_wp_error($product_response)) {
            wp_send_json_error(array('message' => $product_response->get_error_message()));
            return;
        }
        
        $product_body = json_decode(wp_remote_retrieve_body($product_response), true);
        
        if (isset($product_body['error'])) {
            wp_send_json_error(array('message' => $product_body['error']['message']));
            return;
        }
        
        $product_id = $product_body['id'];
        
        // Create price for annual subscription
        $price_response = wp_remote_post('https://api.stripe.com/v1/prices', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'product' => $product_id,
                'unit_amount' => $annual_amount,
                'currency' => $currency,
                'recurring[interval]' => 'year',
            ),
        ));
        
        if (is_wp_error($price_response)) {
            wp_send_json_error(array('message' => $price_response->get_error_message()));
            return;
        }
        
        $price_body = json_decode(wp_remote_retrieve_body($price_response), true);
        
        if (isset($price_body['error'])) {
            wp_send_json_error(array('message' => $price_body['error']['message']));
            return;
        }
        
        $price_id = $price_body['id'];
        
        // Create setup intent for subscription with trial
        $setup_intent_response = wp_remote_post('https://api.stripe.com/v1/setup_intents', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'payment_method_types[]' => 'card',
                'usage' => 'off_session',
            ),
        ));
        
        if (is_wp_error($setup_intent_response)) {
            wp_send_json_error(array('message' => $setup_intent_response->get_error_message()));
            return;
        }
        
        $setup_intent_body = json_decode(wp_remote_retrieve_body($setup_intent_response), true);
        
        if (isset($setup_intent_body['error'])) {
            wp_send_json_error(array('message' => $setup_intent_body['error']['message']));
            return;
        }
        
        wp_send_json_success(array(
            'setupIntentClientSecret' => $setup_intent_body['client_secret'],
            'priceId' => $price_id,
            'trialAmount' => $trial_amount,
            'trialDays' => $trial_days,
            'annualAmount' => $annual_amount,
            'currency' => $currency
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
            $subscription_body_params['payment_behavior'] = 'default_incomplete';
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
        
        // Build customer data array
        $customer_data = array(
            'email' => $customer_email,
            'name' => $customer_name,
        );
        
        // Add phone (required field) - always include if provided
        if (!empty($customer_phone)) {
            $customer_data['phone'] = $customer_phone;
        }
        
        // Build address object if address fields are provided
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
        
        // Create customer
        $customer_response = wp_remote_post('https://api.stripe.com/v1/customers', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => $customer_data,
        ));
        
        if (is_wp_error($customer_response)) {
            return null;
        }
        
        $customer_body = json_decode(wp_remote_retrieve_body($customer_response), true);
        
        if (isset($customer_body['error']) || !isset($customer_body['id'])) {
            return null;
        }
        
        $customer_id = $customer_body['id'];
        
        // Always update customer to ensure phone and address are saved (even if already set during creation)
        $update_data = array();
        
        if (!empty($customer_phone)) {
            $update_data['phone'] = $customer_phone;
        }
        
        if (!empty($address_line1) && !empty($address_city) && !empty($address_state) && !empty($address_postal_code) && !empty($address_country)) {
            $update_data['address[line1]'] = $address_line1;
            if (!empty($address_line2)) {
                $update_data['address[line2]'] = $address_line2;
            }
            $update_data['address[city]'] = $address_city;
            $update_data['address[state]'] = $address_state;
            $update_data['address[postal_code]'] = $address_postal_code;
            $update_data['address[country]'] = $address_country;
        }
        
        // Update customer to ensure phone number is saved
        if (!empty($update_data)) {
            $update_response = wp_remote_post('https://api.stripe.com/v1/customers/' . $customer_id, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $secret_key,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ),
                'body' => $update_data,
            ));
            
            // Log if update fails (but don't fail the whole process)
            if (is_wp_error($update_response)) {
                error_log('Failed to update customer phone/address: ' . $update_response->get_error_message());
            }
        }
        
        return $customer_id;
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

