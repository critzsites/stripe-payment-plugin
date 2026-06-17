<?php
/**
 * Admin Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

class Stripe_Payment_Admin_Settings {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    public function add_admin_menu() {
        add_options_page(
            'Stripe Payment Settings',
            'Stripe Payment',
            'manage_options',
            'stripe-payment-settings',
            array($this, 'render_settings_page')
        );
    }
    
    public function register_settings() {
        // Register settings
        register_setting('stripe_payment_settings', 'stripe_payment_mode');
        register_setting('stripe_payment_settings', 'stripe_payment_test_publishable_key');
        register_setting('stripe_payment_settings', 'stripe_payment_test_secret_key');
        register_setting('stripe_payment_settings', 'stripe_payment_live_publishable_key');
        register_setting('stripe_payment_settings', 'stripe_payment_live_secret_key');
        register_setting('stripe_payment_settings', 'stripe_payment_thank_you_page');
        register_setting('stripe_payment_settings', 'stripe_payment_subscription_trial_amount');
        register_setting('stripe_payment_settings', 'stripe_payment_subscription_trial_days');
        register_setting('stripe_payment_settings', 'stripe_payment_subscription_annual_amount');
        register_setting('stripe_payment_settings', 'stripe_payment_disable_duplicate_check');
        
        // Add settings sections
        add_settings_section(
            'stripe_payment_mode_section',
            'Payment Mode',
            array($this, 'mode_section_callback'),
            'stripe-payment-settings'
        );
        
        add_settings_section(
            'stripe_payment_test_section',
            'Test Mode API Keys',
            array($this, 'test_section_callback'),
            'stripe-payment-settings'
        );
        
        add_settings_section(
            'stripe_payment_live_section',
            'Live Mode API Keys',
            array($this, 'live_section_callback'),
            'stripe-payment-settings'
        );
        
        add_settings_section(
            'stripe_payment_redirect_section',
            'Redirect Settings',
            array($this, 'redirect_section_callback'),
            'stripe-payment-settings'
        );
        
        add_settings_section(
            'stripe_payment_subscription_section',
            'Subscription Settings',
            array($this, 'subscription_section_callback'),
            'stripe-payment-settings'
        );
        
        // Add settings fields
        add_settings_field(
            'stripe_payment_mode',
            'Payment Mode',
            array($this, 'mode_field_callback'),
            'stripe-payment-settings',
            'stripe_payment_mode_section'
        );
        
        add_settings_field(
            'stripe_payment_test_publishable_key',
            'Test Publishable Key',
            array($this, 'test_publishable_key_field_callback'),
            'stripe-payment-settings',
            'stripe_payment_test_section'
        );
        
        add_settings_field(
            'stripe_payment_test_secret_key',
            'Test Secret Key',
            array($this, 'test_secret_key_field_callback'),
            'stripe-payment-settings',
            'stripe_payment_test_section'
        );
        
        add_settings_field(
            'stripe_payment_live_publishable_key',
            'Live Publishable Key',
            array($this, 'live_publishable_key_field_callback'),
            'stripe-payment-settings',
            'stripe_payment_live_section'
        );
        
        add_settings_field(
            'stripe_payment_live_secret_key',
            'Live Secret Key',
            array($this, 'live_secret_key_field_callback'),
            'stripe-payment-settings',
            'stripe_payment_live_section'
        );
        
        add_settings_field(
            'stripe_payment_thank_you_page',
            'Thank You Page URL',
            array($this, 'thank_you_page_field_callback'),
            'stripe-payment-settings',
            'stripe_payment_redirect_section'
        );
        
        add_settings_field(
            'stripe_payment_subscription_trial_amount',
            'Trial Amount',
            array($this, 'subscription_trial_amount_field_callback'),
            'stripe-payment-settings',
            'stripe_payment_subscription_section'
        );
        
        add_settings_field(
            'stripe_payment_subscription_trial_days',
            'Trial Period (Days)',
            array($this, 'subscription_trial_days_field_callback'),
            'stripe-payment-settings',
            'stripe_payment_subscription_section'
        );
        
        add_settings_field(
            'stripe_payment_subscription_annual_amount',
            'Annual Subscription Amount',
            array($this, 'subscription_annual_amount_field_callback'),
            'stripe-payment-settings',
            'stripe_payment_subscription_section'
        );

        add_settings_field(
            'stripe_payment_disable_duplicate_check',
            'Duplicate Subscription Check',
            array($this, 'disable_duplicate_check_field_callback'),
            'stripe-payment-settings',
            'stripe_payment_subscription_section'
        );
    }
    
    public function mode_section_callback() {
        echo '<p>Choose between Test (Sandbox) or Live mode for processing payments.</p>';
    }
    
    public function test_section_callback() {
        echo '<p>Enter your Stripe Test API keys. You can find these in your <a href="https://dashboard.stripe.com/test/apikeys" target="_blank">Stripe Dashboard</a> under Developers > API keys (Test mode).</p>';
    }
    
    public function live_section_callback() {
        echo '<p>Enter your Stripe Live API keys. You can find these in your <a href="https://dashboard.stripe.com/apikeys" target="_blank">Stripe Dashboard</a> under Developers > API keys (Live mode).</p>';
    }
    
    public function redirect_section_callback() {
        echo '<p>Configure where users should be redirected after a successful payment. Leave empty to show success message on the same page.</p>';
    }
    
    public function subscription_section_callback() {
        echo '<p>Configure subscription settings for recurring payments with trial period. These settings are used when creating subscriptions.</p>';
    }
    
    public function mode_field_callback() {
        $mode = get_option('stripe_payment_mode', 'test');
        ?>
        <select name="stripe_payment_mode" id="stripe_payment_mode">
            <option value="test" <?php selected($mode, 'test'); ?>>Test (Sandbox)</option>
            <option value="live" <?php selected($mode, 'live'); ?>>Live</option>
        </select>
        <p class="description">Use Test mode for development and testing. Switch to Live mode when ready to accept real payments.</p>
        <?php
    }
    
    public function test_publishable_key_field_callback() {
        $value = get_option('stripe_payment_test_publishable_key', '');
        ?>
        <input type="text" name="stripe_payment_test_publishable_key" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="pk_test_...">
        <p class="description">Your Stripe Test Publishable Key (starts with pk_test_)</p>
        <?php
    }
    
    public function test_secret_key_field_callback() {
        $value = get_option('stripe_payment_test_secret_key', '');
        ?>
        <input type="password" name="stripe_payment_test_secret_key" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="sk_test_...">
        <p class="description">Your Stripe Test Secret Key (starts with sk_test_)</p>
        <?php
    }
    
    public function live_publishable_key_field_callback() {
        $value = get_option('stripe_payment_live_publishable_key', '');
        ?>
        <input type="text" name="stripe_payment_live_publishable_key" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="pk_live_...">
        <p class="description">Your Stripe Live Publishable Key (starts with pk_live_)</p>
        <?php
    }
    
    public function live_secret_key_field_callback() {
        $value = get_option('stripe_payment_live_secret_key', '');
        ?>
        <input type="password" name="stripe_payment_live_secret_key" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="sk_live_...">
        <p class="description">Your Stripe Live Secret Key (starts with sk_live_)</p>
        <?php
    }
    
    public function thank_you_page_field_callback() {
        $value = get_option('stripe_payment_thank_you_page', '');
        ?>
        <input type="url" name="stripe_payment_thank_you_page" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="https://yoursite.com/thank-you">
        <p class="description">Enter the full URL where users should be redirected after successful payment. Leave empty to show success message on the same page.</p>
        <?php
    }
    
    public function subscription_trial_amount_field_callback() {
        $value = get_option('stripe_payment_subscription_trial_amount', '25.00');
        ?>
        <input type="number" name="stripe_payment_subscription_trial_amount" value="<?php echo esc_attr($value); ?>" class="small-text" step="0.01" min="0" placeholder="25.00">
        <span>USD</span>
        <p class="description">Amount charged for the trial period (e.g., 25.00)</p>
        <?php
    }
    
    public function subscription_trial_days_field_callback() {
        $value = get_option('stripe_payment_subscription_trial_days', '30');
        ?>
        <input type="number" name="stripe_payment_subscription_trial_days" value="<?php echo esc_attr($value); ?>" class="small-text" min="0" placeholder="30">
        <span>days</span>
        <p class="description">Number of days for the trial period (e.g., 30)</p>
        <?php
    }
    
    public function subscription_annual_amount_field_callback() {
        $value = get_option('stripe_payment_subscription_annual_amount', '12.00');
        ?>
        <input type="number" name="stripe_payment_subscription_annual_amount" value="<?php echo esc_attr($value); ?>" class="small-text" step="0.01" min="0" placeholder="12.00">
        <span>USD/year</span>
        <p class="description">Annual subscription amount charged after trial period (e.g., 12.00)</p>
        <?php
    }

    public function disable_duplicate_check_field_callback() {
        $value = get_option('stripe_payment_disable_duplicate_check', '0');
        ?>
        <label>
            <input type="checkbox" name="stripe_payment_disable_duplicate_check" value="1" <?php checked($value, '1'); ?>>
            Disable duplicate subscription check (for testing)
        </label>
        <p class="description">When checked, customers will NOT be blocked from subscribing again even if they already have an active subscription to the same price. Leave unchecked in production.</p>
    
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Show success message
        if (isset($_GET['settings-updated'])) {
            add_settings_error('stripe_payment_messages', 'stripe_payment_message', 'Settings Saved', 'updated');
        }
        
        settings_errors('stripe_payment_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('stripe_payment_settings');
                do_settings_sections('stripe-payment-settings');
                submit_button('Save Settings');
                ?>
            </form>
            
            <hr>
            <h2>Shortcode Usage</h2>
            <p>Use the following shortcode to display the payment form on any page or post:</p>
            <code>[stripe_payment]</code>
            <p>For subscriptions to work with Zapier, you MUST include the Stripe 'Price ID' and 'Product ID' from your Stripe Dashboard:</p>
            <code>[stripe_payment subscription="true" product_id="prod_ABC" price_id="price_123"]</code>
            <p>Or with custom amount:</p>
            <code>[stripe_payment amount="25.00"]</code>
            <p>Or with custom amount and currency:</p>
            <code>[stripe_payment amount="25.00" currency="usd"]</code>
            <p>For subscription with trial period:</p>
            <code>[stripe_payment subscription="true"]</code>
            <p>Or with custom subscription amounts:</p>
            <code>[stripe_payment subscription="true" trial_amount="25.00" trial_days="30" annual_amount="12.00"]</code>
        </div>
        <?php
    }
}

