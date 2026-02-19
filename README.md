# Stripe Payment Plugin for WordPress

A beautiful, fully-featured Stripe payment plugin for WordPress with shortcode support and admin settings.

## Features

- ✅ Admin settings page for Stripe API keys (Test/Live mode)
- ✅ Shortcode support with customizable amount
- ✅ Beautiful, modern payment form design
- ✅ Stripe Elements integration for secure card processing
- ✅ Responsive design for mobile and desktop
- ✅ Test (Sandbox) and Live mode support
- ✅ AJAX-based payment processing

## Installation

1. Upload the entire `stripe-payment-plugin` folder to `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Stripe Payment to configure your API keys

## Configuration

### Getting Your Stripe API Keys

1. Log in to your [Stripe Dashboard](https://dashboard.stripe.com/)
2. For **Test Mode**:
   - Go to Developers > API keys (Test mode)
   - Copy your **Publishable key** (starts with `pk_test_`)
   - Copy your **Secret key** (starts with `sk_test_`)
3. For **Live Mode**:
   - Toggle to Live mode in Stripe Dashboard
   - Go to Developers > API keys
   - Copy your **Publishable key** (starts with `pk_live_`)
   - Copy your **Secret key** (starts with `sk_live_`)

### Setting Up in WordPress

1. Navigate to **Settings > Stripe Payment** in WordPress admin
2. Select your **Payment Mode** (Test or Live)
3. Enter your **Test Mode API Keys** (for testing)
4. Enter your **Live Mode API Keys** (for production)
5. Click **Save Settings**

## Usage

### Basic Shortcode

Display the payment form with default amount ($1.00):

```
[stripe_payment]
```

### Subscription Shortcode (Required IDs)
For subscriptions to work with Zapier, you MUST include the Stripe Price ID from your Stripe Dashboard:

```
[stripe_payment subscription="true" price_id="price_123456789"]
```

### Shortcode with Custom Amount

Display the payment form with a custom amount:

```
[stripe_payment amount="25.00"]
```

### Shortcode with Amount and Currency

Display the payment form with custom amount and currency:

```
[stripe_payment amount="25.00" currency="usd"]
```

## Shortcode Parameters

- `amount` (optional): Payment amount in dollars. Default: `1.00`
- `currency` (optional): Currency code. Default: `usd`
- `description` (optional): Payment description (for future use)

## File Structure

```
stripe-payment-plugin/
├── stripe-payment-plugin.php    # Main plugin file
├── includes/
│   └── admin-settings.php       # Admin settings page
├── templates/
│   └── payment-form.php         # Payment form template
├── assets/
│   ├── css/
│   │   └── payment.css          # Stylesheet
│   └── js/
│       └── payment.js           # JavaScript for Stripe integration
└── README.md                    # This file
```

## Testing

### Test Mode

1. Set Payment Mode to **Test** in plugin settings
2. Use Stripe test card numbers:
   - **Success**: `4242 4242 4242 4242`
   - **Decline**: `4000 0000 0000 0002`
   - Use any future expiry date (e.g., 12/25)
   - Use any 3-digit CVC

### Live Mode

1. Set Payment Mode to **Live** in plugin settings
2. Use real credit cards (charges will be processed)

## Support

For Stripe API documentation, visit: https://stripe.com/docs/api

## Security Notes

- Never share your Secret keys
- Always use Test mode during development
- Keep your WordPress and plugin updated
- Use HTTPS on your website for secure payment processing

