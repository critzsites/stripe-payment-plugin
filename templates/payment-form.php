<?php
/**
 * Payment Form Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$currency_symbol = '$';
$formatted_amount = number_format($amount, 2);
?>

<div class="stripe-payment-wrapper">
    <div class="stripe-payment-container">
        <!-- Left Column: Details -->
        <div class="payment-details-column">
            <div class="payment-section">
                <div class="checklist-header">
                    <img src="https://corymuscara.com/wp-content/uploads/2026/01/lets-icons_check-fill.png" width="26" height="26" alt="Check" class="check-icon">
                    <span>What's included</span>
                </div>
            </div>
            
            <div class="payment-section checklist-items">
                <div class="checklist-item">
                    <img src="https://corymuscara.com/wp-content/uploads/2026/01/check.png" width="12" height="12" alt="Check" class="check-small">
                    <span>Daily morning texts from Cory (Mon-Fri)</span>
                </div>
                <div class="checklist-item">
                    <img src="https://corymuscara.com/wp-content/uploads/2026/01/check.png" width="12" height="12" alt="Check" class="check-small">
                    <span>Written by a human, for humans</span>
                </div>
                <div class="checklist-item">
                    <img src="https://corymuscara.com/wp-content/uploads/2026/01/check.png" width="12" height="12" alt="Check" class="check-small">
                    <span>A personalized birthday thread</span>
                </div>
                <div class="checklist-item">
                    <img src="https://corymuscara.com/wp-content/uploads/2026/01/check.png" width="12" height="12" alt="Check" class="check-small">
                    <span>Priority access to local retreats & events</span>
                </div>
                <div class="checklist-item">
                    <img src="https://corymuscara.com/wp-content/uploads/2026/01/check.png" width="12" height="12" alt="Check" class="check-small">
                    <span>A sense of calm to start your day</span>
                </div>
            </div>
            
            <div class="payment-section pricing-info">
                <div class="two-col-wrapper">
                    <div class="inner-col">
                        <?php if (isset($is_subscription) && $is_subscription): ?>
                            <?php if (isset($no_trial) && $no_trial): ?>
                                <p>Annual subscription</p>
                                <p class="price-text"><?php echo esc_html($currency_symbol . number_format($annual_amount, 2)); ?></p>
                            <?php else: ?>
                                <p>Trial period (<?php echo esc_html($trial_days); ?> days)</p>
                                <p class="price-text"><?php echo esc_html($currency_symbol . number_format($trial_amount, 2)); ?></p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p>Payment Amount</p>
                            <p class="price-text"><?php echo esc_html($currency_symbol . $formatted_amount); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if (isset($is_subscription) && $is_subscription): ?>
                    <div class="inner-col">
                        <p class="font-10">
                            <?php echo (isset($no_trial) && $no_trial) ? 'Annual subscription' : 'Annual subscription (starts after trial)'; ?>
                        </p>
                        <p><span class="small-font"><?php echo esc_html($currency_symbol . number_format($annual_amount, 2)); ?>/year</span></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="payment-section total-section">
                <div class="two-col-wrapper">
                    <div class="inner-col">
                        <p>Total Due Today</p>
                        <p class="total-text"><?php echo esc_html($currency_symbol . $formatted_amount); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column: Payment Form -->
        <div class="payment-form-column">
            <div class="payment-section">
                <h3 class="express-checkout-title">EXPRESS CHECKOUT</h3>
            </div>

            <div class="payment-section express-checkout-buttons" id="express-checkout-wrapper" style="display: none;">
                <div id="payment-request-button"></div>
            </div>
            
            <div class="payment-section">
                <form class="stripe-payment-form" id="stripe-payment-form">
                    <div class="divider">
                        <span>OR PAY WITH CARD</span>
                    </div>
                    
                    <div class="form-row first-row">
                        <div class="form-field">
                            <label for="full-name">Full Name</label>
                            <input type="text" id="full-name" name="full_name" placeholder="John Doe" required>
                        </div>
                        
                        <div class="form-field">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" placeholder="john@example.com" required>
                        </div>
                    </div>
                    
                    <div class="form-field">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" placeholder="+1 (000) 000-0000" required>
                    </div>
                    
                    <div class="billing-address-section">
                        <h4 class="billing-address-title">Billing Address</h4>
                        
                        <div class="form-field">
                            <label for="address-line1">Street Address</label>
                            <input type="text" id="address-line1" name="address_line1" placeholder="123 Main Street" required>
                        </div>
                        
                        <div class="form-field">
                            <label for="address-line2">Apartment, suite, etc. (optional)</label>
                            <input type="text" id="address-line2" name="address_line2" placeholder="Apt 4B">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-field">
                                <label for="city">City</label>
                                <input type="text" id="city" name="city" placeholder="New York" required>
                            </div>
                            
                            <div class="form-field">
                                <label for="state">State/Province</label>
                                <input type="text" id="state" name="state" placeholder="NY" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-field">
                                <label for="postal-code">ZIP/Postal Code</label>
                                <input type="text" id="postal-code" name="postal_code" placeholder="10001" required>
                            </div>
                            
                            <div class="form-field">
                                <label for="country">Country</label>
                                <select id="country" name="country" required>
                                    <option value="">Select Country</option>
                                    <option value="US" selected>United States</option>
                                    <option value="CA">Canada</option>
                                    <option value="GB">United Kingdom</option>
                                    <option value="AU">Australia</option>
                                    <option value="DE">Germany</option>
                                    <option value="FR">France</option>
                                    <option value="IT">Italy</option>
                                    <option value="ES">Spain</option>
                                    <option value="NL">Netherlands</option>
                                    <option value="BE">Belgium</option>
                                    <option value="CH">Switzerland</option>
                                    <option value="AT">Austria</option>
                                    <option value="SE">Sweden</option>
                                    <option value="NO">Norway</option>
                                    <option value="DK">Denmark</option>
                                    <option value="FI">Finland</option>
                                    <option value="IE">Ireland</option>
                                    <option value="PT">Portugal</option>
                                    <option value="GR">Greece</option>
                                    <option value="PL">Poland</option>
                                    <option value="CZ">Czech Republic</option>
                                    <option value="HU">Hungary</option>
                                    <option value="RO">Romania</option>
                                    <option value="BG">Bulgaria</option>
                                    <option value="HR">Croatia</option>
                                    <option value="SK">Slovakia</option>
                                    <option value="SI">Slovenia</option>
                                    <option value="EE">Estonia</option>
                                    <option value="LV">Latvia</option>
                                    <option value="LT">Lithuania</option>
                                    <option value="LU">Luxembourg</option>
                                    <option value="MT">Malta</option>
                                    <option value="CY">Cyprus</option>
                                    <option value="JP">Japan</option>
                                    <option value="CN">China</option>
                                    <option value="IN">India</option>
                                    <option value="BR">Brazil</option>
                                    <option value="MX">Mexico</option>
                                    <option value="AR">Argentina</option>
                                    <option value="CL">Chile</option>
                                    <option value="CO">Colombia</option>
                                    <option value="PE">Peru</option>
                                    <option value="ZA">South Africa</option>
                                    <option value="NZ">New Zealand</option>
                                    <option value="SG">Singapore</option>
                                    <option value="HK">Hong Kong</option>
                                    <option value="MY">Malaysia</option>
                                    <option value="TH">Thailand</option>
                                    <option value="ID">Indonesia</option>
                                    <option value="PH">Philippines</option>
                                    <option value="VN">Vietnam</option>
                                    <option value="KR">South Korea</option>
                                    <option value="TW">Taiwan</option>
                                    <option value="IL">Israel</option>
                                    <option value="AE">United Arab Emirates</option>
                                    <option value="SA">Saudi Arabia</option>
                                    <option value="TR">Turkey</option>
                                    <option value="RU">Russia</option>
                                    <option value="UA">Ukraine</option>
                                    <option value="EG">Egypt</option>
                                    <option value="NG">Nigeria</option>
                                    <option value="KE">Kenya</option>
                                    <option value="GH">Ghana</option>
                                    <option value="TZ">Tanzania</option>
                                    <option value="UG">Uganda</option>
                                    <option value="ET">Ethiopia</option>
                                    <option value="MA">Morocco</option>
                                    <option value="DZ">Algeria</option>
                                    <option value="TN">Tunisia</option>
                                    <option value="LY">Libya</option>
                                    <option value="SD">Sudan</option>
                                    <option value="IQ">Iraq</option>
                                    <option value="IR">Iran</option>
                                    <option value="PK">Pakistan</option>
                                    <option value="BD">Bangladesh</option>
                                    <option value="LK">Sri Lanka</option>
                                    <option value="NP">Nepal</option>
                                    <option value="MM">Myanmar</option>
                                    <option value="KH">Cambodia</option>
                                    <option value="LA">Laos</option>
                                    <option value="BN">Brunei</option>
                                    <option value="FJ">Fiji</option>
                                    <option value="PG">Papua New Guinea</option>
                                    <option value="NC">New Caledonia</option>
                                    <option value="PF">French Polynesia</option>
                                    <option value="GU">Guam</option>
                                    <option value="AS">American Samoa</option>
                                    <option value="MP">Northern Mariana Islands</option>
                                    <option value="VI">U.S. Virgin Islands</option>
                                    <option value="PR">Puerto Rico</option>
                                    <option value="DO">Dominican Republic</option>
                                    <option value="JM">Jamaica</option>
                                    <option value="TT">Trinidad and Tobago</option>
                                    <option value="BB">Barbados</option>
                                    <option value="BS">Bahamas</option>
                                    <option value="BZ">Belize</option>
                                    <option value="CR">Costa Rica</option>
                                    <option value="PA">Panama</option>
                                    <option value="GT">Guatemala</option>
                                    <option value="HN">Honduras</option>
                                    <option value="NI">Nicaragua</option>
                                    <option value="SV">El Salvador</option>
                                    <option value="EC">Ecuador</option>
                                    <option value="BO">Bolivia</option>
                                    <option value="PY">Paraguay</option>
                                    <option value="UY">Uruguay</option>
                                    <option value="VE">Venezuela</option>
                                    <option value="GY">Guyana</option>
                                    <option value="SR">Suriname</option>
                                    <option value="GF">French Guiana</option>
                                    <option value="FK">Falkland Islands</option>
                                    <option value="GS">South Georgia and the South Sandwich Islands</option>
                                    <option value="BV">Bouvet Island</option>
                                    <option value="HM">Heard Island and McDonald Islands</option>
                                    <option value="TF">French Southern Territories</option>
                                    <option value="AQ">Antarctica</option>
                                    <option value="IO">British Indian Ocean Territory</option>
                                    <option value="CX">Christmas Island</option>
                                    <option value="CC">Cocos (Keeling) Islands</option>
                                    <option value="NF">Norfolk Island</option>
                                    <option value="PN">Pitcairn</option>
                                    <option value="SH">Saint Helena, Ascension and Tristan da Cunha</option>
                                    <option value="PM">Saint Pierre and Miquelon</option>
                                    <option value="TC">Turks and Caicos Islands</option>
                                    <option value="VG">British Virgin Islands</option>
                                    <option value="AI">Anguilla</option>
                                    <option value="AG">Antigua and Barbuda</option>
                                    <option value="AW">Aruba</option>
                                    <option value="BH">Bahrain</option>
                                    <option value="BM">Bermuda</option>
                                    <option value="BT">Bhutan</option>
                                    <option value="BW">Botswana</option>
                                    <option value="BN">Brunei</option>
                                    <option value="BF">Burkina Faso</option>
                                    <option value="BI">Burundi</option>
                                    <option value="CV">Cape Verde</option>
                                    <option value="KY">Cayman Islands</option>
                                    <option value="CF">Central African Republic</option>
                                    <option value="TD">Chad</option>
                                    <option value="KM">Comoros</option>
                                    <option value="CG">Congo</option>
                                    <option value="CD">Congo, the Democratic Republic of the</option>
                                    <option value="CK">Cook Islands</option>
                                    <option value="CI">Côte d'Ivoire</option>
                                    <option value="DJ">Djibouti</option>
                                    <option value="DM">Dominica</option>
                                    <option value="GQ">Equatorial Guinea</option>
                                    <option value="ER">Eritrea</option>
                                    <option value="SZ">Eswatini</option>
                                    <option value="FO">Faroe Islands</option>
                                    <option value="GA">Gabon</option>
                                    <option value="GM">Gambia</option>
                                    <option value="GE">Georgia</option>
                                    <option value="GI">Gibraltar</option>
                                    <option value="GL">Greenland</option>
                                    <option value="GD">Grenada</option>
                                    <option value="GP">Guadeloupe</option>
                                    <option value="GG">Guernsey</option>
                                    <option value="GN">Guinea</option>
                                    <option value="GW">Guinea-Bissau</option>
                                    <option value="HT">Haiti</option>
                                    <option value="VA">Holy See (Vatican City State)</option>
                                    <option value="IS">Iceland</option>
                                    <option value="IM">Isle of Man</option>
                                    <option value="JE">Jersey</option>
                                    <option value="JO">Jordan</option>
                                    <option value="KZ">Kazakhstan</option>
                                    <option value="KI">Kiribati</option>
                                    <option value="KP">Korea, Democratic People's Republic of</option>
                                    <option value="KW">Kuwait</option>
                                    <option value="KG">Kyrgyzstan</option>
                                    <option value="LB">Lebanon</option>
                                    <option value="LS">Lesotho</option>
                                    <option value="LR">Liberia</option>
                                    <option value="LI">Liechtenstein</option>
                                    <option value="MO">Macao</option>
                                    <option value="MG">Madagascar</option>
                                    <option value="MW">Malawi</option>
                                    <option value="MV">Maldives</option>
                                    <option value="ML">Mali</option>
                                    <option value="MH">Marshall Islands</option>
                                    <option value="MQ">Martinique</option>
                                    <option value="MR">Mauritania</option>
                                    <option value="MU">Mauritius</option>
                                    <option value="YT">Mayotte</option>
                                    <option value="FM">Micronesia, Federated States of</option>
                                    <option value="MD">Moldova, Republic of</option>
                                    <option value="MC">Monaco</option>
                                    <option value="MN">Mongolia</option>
                                    <option value="ME">Montenegro</option>
                                    <option value="MS">Montserrat</option>
                                    <option value="MZ">Mozambique</option>
                                    <option value="NA">Namibia</option>
                                    <option value="NR">Nauru</option>
                                    <option value="NE">Niger</option>
                                    <option value="NU">Niue</option>
                                    <option value="MK">North Macedonia</option>
                                    <option value="OM">Oman</option>
                                    <option value="PW">Palau</option>
                                    <option value="PS">Palestine, State of</option>
                                    <option value="QA">Qatar</option>
                                    <option value="RE">Réunion</option>
                                    <option value="RW">Rwanda</option>
                                    <option value="BL">Saint Barthélemy</option>
                                    <option value="KN">Saint Kitts and Nevis</option>
                                    <option value="LC">Saint Lucia</option>
                                    <option value="MF">Saint Martin (French part)</option>
                                    <option value="VC">Saint Vincent and the Grenadines</option>
                                    <option value="WS">Samoa</option>
                                    <option value="SM">San Marino</option>
                                    <option value="ST">Sao Tome and Principe</option>
                                    <option value="SN">Senegal</option>
                                    <option value="RS">Serbia</option>
                                    <option value="SC">Seychelles</option>
                                    <option value="SL">Sierra Leone</option>
                                    <option value="SB">Solomon Islands</option>
                                    <option value="SO">Somalia</option>
                                    <option value="SS">South Sudan</option>
                                    <option value="SJ">Svalbard and Jan Mayen</option>
                                    <option value="TJ">Tajikistan</option>
                                    <option value="TL">Timor-Leste</option>
                                    <option value="TG">Togo</option>
                                    <option value="TK">Tokelau</option>
                                    <option value="TO">Tonga</option>
                                    <option value="TM">Turkmenistan</option>
                                    <option value="TV">Tuvalu</option>
                                    <option value="UM">United States Minor Outlying Islands</option>
                                    <option value="UZ">Uzbekistan</option>
                                    <option value="VU">Vanuatu</option>
                                    <option value="WF">Wallis and Futuna</option>
                                    <option value="EH">Western Sahara</option>
                                    <option value="YE">Yemen</option>
                                    <option value="ZM">Zambia</option>
                                    <option value="ZW">Zimbabwe</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-field card-field-wrapper">
                        <label for="card-element">Card Number</label>
                        <div id="card-element" class="card-element">
                            <!-- Stripe Elements will create form elements here -->
                        </div>
                        <div class="card-icons">
                            <img src="https://corymuscara.com/wp-content/uploads/2026/02/visa-icon.png" alt="Visa">
                            <img src="https://corymuscara.com/wp-content/uploads/2026/02/mastercard-icon.png" alt="Mastercard">
                        </div>
                        <div id="card-errors" role="alert" class="card-errors"></div>
                    </div>
                    
                    <input type="hidden" id="payment-amount" value="<?php echo esc_attr($amount_cents); ?>">
                    <input type="hidden" id="payment-currency" value="<?php echo esc_attr($currency); ?>">
                    <input type="hidden" id="stripe-product-id" value="<?php echo esc_attr($product_id); ?>">
                    <input type="hidden" id="stripe-price-id" value="<?php echo esc_attr($price_id); ?>">
                    <input type="hidden" id="is-subscription" value="<?php echo isset($is_subscription) && $is_subscription ? '1' : '0'; ?>">
                    <?php if (isset($is_subscription) && $is_subscription): ?>
                    <input type="hidden" id="no-trial" value="<?php echo (isset($no_trial) && $no_trial) ? '1' : '0'; ?>">
                    <input type="hidden" id="trial-amount" value="<?php echo esc_attr($trial_amount_cents); ?>">
                    <input type="hidden" id="trial-days" value="<?php echo esc_attr($trial_days); ?>">
                    <input type="hidden" id="annual-amount" value="<?php echo esc_attr($annual_amount_cents); ?>">
                    <?php endif; ?>
                    <input type="hidden" id="custom-redirect-url" value="<?php echo esc_attr(isset($redirect_url) ? $redirect_url : ''); ?>">
                    
                    <button type="submit" id="submit-button" class="submit-button">
                        <span class="button-text">Process Payment</span>
                        <span class="button-spinner" style="display: none;"></span>
                    </button>
                    
                    <div id="payment-message" class="payment-message" style="display: none;"></div>
                </form>
            </div>
        </div>
    </div>
</div>

