<?php
/**
 * Weeve Official Integration
 *
 * Weeve Official Integration
 *
 * Plugin Name: Weeve Official Integration
 * Plugin URI: https://weeve.tt
 * Version: 0.1
 * Author: Weeve
 * Description: Weeve Official Integration for Merchants
 * Text Domain: weeve
 *
 * @author      Weeve
 * @version     v.1.0 (06/06/21)
 * @copyright   Copyright (c) 2021
 */

$points = 0;

class WPM_Rewards {

    // Variables for use in functions
    public $user_id;
    public $table_name;
    public $user_scores;
    public $points;
    public $user_rank;
    public $settings;
    public $categories;
    public $vouchers;
    public $rewards;

    public $user_name;
    public $phone;

    /**
     * WPM Rewards constructor.
     */
    public function __construct()
    {
        // Create DB Table
        register_activation_hook( __FILE__, [$this, 'sports_bench_create_db']);

        // Register styles
        wp_register_style('wpm-rewards-styles', plugins_url('templates/assets/styles.css', __FILE__), false, '1.0.0', 'all');
        wp_enqueue_style('wpm-rewards-styles');

		// Create Menu
		add_action('admin_menu', [$this, 'register_menu']);

        // Orders and cart
        add_action('woocommerce_order_status_completed', [$this, 'add_points_to_user'], 10, 1);
        add_action('woocommerce_before_calculate_totals', [$this, 'set_cart_items_price']);
        add_action('woocommerce_checkout_create_order', [$this, 'reset_session'], 20, 1);

        // Change price on shop and product and cart
        //add_filter('woocommerce_product_get_price', [$this, 'set_rank_price_products']);
        //add_filter('woocommerce_product_variation_get_price', [$this, 'set_rank_price_products']);
		
        // Add new table to WooCommerce product
        add_filter('woocommerce_product_data_tabs', [$this, 'discount_rates']);
        add_action('woocommerce_product_data_panels', [$this, 'discounts_ranks_tab_contents']);

        // Show how much Points user will get
        add_action( 'woocommerce_cart_totals_after_order_total', [$this, 'table_points_in_cart_checkout']);
        add_action( 'woocommerce_review_order_after_order_total', [$this, 'table_points_in_cart_checkout']);

        // User profile
        add_action('woocommerce_account_dashboard', [$this, 'rewards_dashboard_api']);

        // Init functions
        add_action('init', [$this, 'save_rewards_data']);
        add_action('init', [$this, 'load_user_points']);
        add_action('init', [$this, 'voucher_submit']);
        add_action('init', [$this, 'rewards_use']);

        // Register scripts
        wp_register_script('wpm-rewards-script', plugins_url('templates/assets/js/script.js', __FILE__), array('jquery'), '1.0.5', 'all');
        wp_enqueue_script('wpm-rewards-script');
        wp_localize_script('wpm-rewards-script', 'admin',
            array(
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
            )
        );

        // AJAX Block
        add_action('wp_ajax_wpm_get_user_balance', [$this, 'get_user_api_points']);
        add_action('wp_ajax_nopriv_wpm_get_user_balance', [$this, 'get_user_api_points']);
        add_action('wp_ajax_wpm_get_discount_api', [$this, 'get_discount_api'] );
        add_action('wp_ajax_nopriv_wpm_get_discount_api', [$this, 'get_discount_api']);

        add_action('wp_ajax_wpm_get_sku_api', [$this, 'get_discount_sku_api']);
        add_action('wp_ajax_nopriv_wpm_get_sku_api', [$this, 'get_discount_sku_api']);

        add_action('show_user_profile', [$this, 'show_points_history_profile_user']);
        add_action('edit_user_profile', [$this, 'show_points_history_profile_user']);
        add_action('woocommerce_cart_calculate_fees', [$this, 'add_fees_to_checkout']);
    }

    public function add_fees_to_checkout($cart) {
        $is_discounted = WC()->session->get('discount-set');

        if($is_discounted == 1) {
            $discount = WC()->session->get('discount-price');
            $cart->add_fee(__('Discount Voucher', 'weeeve'), -$discount);
        }
    }

    /**
     * Show points history user
     */
    public function show_points_history_profile_user($user)
    {
        global $wpdb;

        // Settings and points
        $table = $wpdb->prefix.'wpm_rewards_points';
        $user_history = $wpdb->get_results("SELECT * FROM $table WHERE user_id = $user->ID AND DATE(date_expire) >= DATE(NOW()) ORDER BY id DESC");

        include('templates/profile_history.php');
    }

    /**
     * Reset session after CheckOut
     */
    public function reset_session()
    {
        WC()->session->set('discount-set', null);
        WC()->session->set('free-product', null);
    }

    /**
     * Get SKU product by API
     */
    public function get_discount_sku_api()
    {
        global $wpdb;

        $is_discounted = WC()->session->get('discount-set');

        $this->phone = $_POST['phone'];
        $this->user_name = $_POST['name'];

        $sku = $_POST['sku'];
        $api_data = $this->curlApi();

        if(!$is_discounted && $is_discounted != 1) {
            // Header data
            date_default_timezone_set("Asia/Karachi");
            $date = date("D j M Y h:i:s").'+5:30';
            $signature = $this->getApiSignature($this->phone, $date);
            $keyid = $this->settings['api_key'];

            // Signature
            $header_signature = 'Signature keyId="'.$keyid.'",algorithm="hmac-sha256",headers="currentdate accept content-type",signature="'.$signature.'"';
            $headers = ['accept: application/json', 'authorization: '.$header_signature,'currentdate: '.$date,'Content-Type: application/json'];

            $txn_id = $this->generateRandomString();

            /// Redeem Product on API
            if($sku > 0) {
                $data_string = [
                    'phone' => $this->phone,
                    'product' => $sku,
                    'requestId' => $txn_id
                ];

                $ch = curl_init("https://api.weeve.tt/api/v1/redeemProduct");
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_string));
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $result = curl_exec($ch);
                curl_close($ch);

                $res = json_decode($result, true);
            }
        }
    }

    /**
     * Get discount if balance is equal settings
     */
    public function get_discount_api()
    {
        global $wpdb;

        $is_discounted = WC()->session->get('discount-set');

        $this->phone = $_POST['phone'];
        $this->user_name = $_POST['name'];

        $points = $_POST['points'];
        $discount = $_POST['discount'];
        $sku = $_POST['sku'];

        $api_data = $this->curlApi();
        $user_balance = $api_data['balance'];

        if($user_balance >= $points && !$is_discounted && $is_discounted != 1) {
            // Header data
            date_default_timezone_set("Asia/Karachi");
            $date = date("D j M Y h:i:s").'+5:30';
            $signature = $this->getApiSignature($this->phone, $date);
            $keyid = $this->settings['api_key'];

            // Signature
            $header_signature = 'Signature keyId="'.$keyid.'",algorithm="hmac-sha256",headers="currentdate accept content-type",signature="'.$signature.'"';
            $headers = ['accept: application/json', 'authorization: '.$header_signature,'currentdate: '.$date,'Content-Type: application/json'];

            $txn_id = $this->generateRandomString();

            /// Redeem Product on API
            if($sku > 0) {
                // Check if Gift in the Cart or Add it
                $in_cart = null;
                foreach(WC()->cart->get_cart() as $value) {
                    $sku_product = $value['data']->get_sku();

                    if($sku == $sku_product) {
                        $in_cart = true;
                    }
                }

                if(!$in_cart) {
                    WC()->cart->add_to_cart(wc_get_product_id_by_sku($sku), 1);
                    WC()->session->set('free-product', $sku);
                }
            }

            if($discount > 0 || $sku > 0 && !$in_cart) {
                // Spend Points on API for Discount or SKU
                $data_string = [
                    "phone" => $this->phone,
                    "name" => $this->user_name,
                    "credits" => -$points,
                    "requestId" => $txn_id,
                    "receiptNumber" => rand(1000, 9999)
                ];

                $ch = curl_init("https://api.weeve.tt/api/v1/issuePoints");
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_string));
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $result = curl_exec($ch);
                curl_close($ch);

                $res = json_decode($result, true);

                $settings = $this->settings;
                $current_date = date('Y-m-d');
                $expire_days = $settings['expire'];

                // Add points to DB with zero order_id
                $wpdb->insert($this->table_name, array(
                    'user_id' => $this->user_id,
                    'order_id' => -2,
                    'points' => -$points,
                    'date_start' => $current_date,
                    'date_expire' => date('Y-m-d', strtotime($current_date. ' + '.$expire_days.' days')),
                ));
            }

            if($discount > 0) {
                WC()->session->set('discount-set', 1);
                WC()->session->set('discount-price', $discount);
            }
        }
    }

     /**
      * Points table in cart/checkout
      */
    public function table_points_in_cart_checkout()
    {
        // Cart total
        $cart_total = WC()->cart->subtotal;
        $settings = $this->settings;
        $is_discounted = WC()->session->get('discount-set');

        date_default_timezone_set("Asia/Karachi");
        $date = date("D j M Y h:i:s").'+5:30';
        $signature = $this->getApiSignature($this->phone, $date);
        $keyid = $this->settings['api_key'];

        // Signature
        $header_signature = 'Signature keyId="'.$keyid.'",algorithm="hmac-sha256",headers="currentdate accept content-type",signature="'.$signature.'"';
        $headers = ['accept: application/json', 'authorization: '.$header_signature,'currentdate: '.$date,'Content-Type: application/json'];

        // Add points to user
        $ch = curl_init("https://api.weeve.tt/api/v1/products");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        $res = json_decode($result, true);

        if(!$is_discounted || $is_discounted == 0) {
            foreach($res['results'] as $coupon) { if($this->points >= $coupon['redeemPoints'] && $coupon['status'] == 'active') { ?>
                <tr class="get-discount" style="display: none">
                    <th><?php if(isset($coupon['dollarValue'])) { echo '-'.$coupon['dollarValue'].get_woocommerce_currency_symbol().' discount'; } ?> <?php if(isset($coupon['SKU']) && isset($coupon['dollarValue'])) {echo '+';} ?> <?php if(isset($coupon['SKU'])) {echo 'GIFT';} ?> <span>(costs <?= $coupon['redeemPoints'] ?> points)</span></th>
                    <td><button class="discount-api" type="button" data-price="<?php if(isset($coupon['dollarValue'])) {echo $coupon['dollarValue'];} else {echo 0;} ?>" data-sku="<?php if(isset($coupon['SKU'])) {echo $coupon['SKU'];} else {echo 0;} ?>" data-points="<?php if(isset($coupon['redeemPoints'])) {echo $coupon['redeemPoints'];} else {echo 0;} ?>"><?php if(isset($coupon['SKU'])) {echo 'Redeem';} else {echo 'Get discount';} ?></button></td>
                </tr>
            <?php }} ?>
            <script>
                jQuery(document).ready(function($) {
                    $('.get-discount').insertBefore('.shop_table .cart-subtotal');
                });
            </script>
            <?php

        } else { ?>
            <script>
                jQuery(document).ready(function($) {
                    var phone = $('#billing_phone').val();
                    var name = $('#billing_first_name').val();

                    $.ajax({
                        type: "POST",
                        url: admin.ajaxurl,
                        data: {
                            action: "wpm_get_user_balance",
                            name: name,
                            phone: phone
                        },
                        success: function(response) {
                            if($(".points-api").length) {
                                $('.points-api').remove();
                            }
                            $('.woocommerce-checkout-review-order-table tfoot').prepend(response);
                            $('.get-discount').show();
                        }
                    });

                });
            </script>
        <?php }
    }

    /**
     * Convert data to Base64
     */
    public static function hex_to_base64($hex){
        $return = '';

        foreach(str_split($hex, 2) as $pair){
            $return .= chr(hexdec($pair));
        }

        return base64_encode($return);
    }

    /**
     * Get signature for API request
     */
    public function getApiSignature($phone, $date)
    {
		$salt      = "currentdate: ".$date."\naccept: application/json\ncontent-type: application/json";
		$hmac256   = hash_hmac('sha256',$salt, $this->settings['api_secret']);
		$signature = $this->hex_to_base64($hmac256);

        return $signature;
    }

    /**
     * Rewards dashboard in user account api version
     */
    public function rewards_dashboard_api()
    {
        global $wpdb;

        // Get settings and discounts
        $categories = $this->categories;
        $settings = $this->settings;
        $user_rank = $this->user_rank;
        $points = $this->points;
        $rewards = $this->rewards;
        $user_id = get_current_user_id();

        // Create variables
        $currency = get_woocommerce_currency_symbol();
        $price_points = 100;
        $discount = [];
        $min_reward = 0;

        // Find bigger discount on categories by ranks
        foreach($categories as $category) {
            foreach($settings['rank'] as $rank_id => $rank) {
                if($category[$rank_id] > $discount[$rank_id] || !isset($discount[$rank_id])) {
                    if($categories['global'][$rank_id] > 0) {
                        $discount[$rank_id] = $categories['global'][$rank_id];
                    } else {
                        $discount[$rank_id] = $category[$rank_id];
                    }
                }
            }
        }

        // Find smaller reward on categories by ranks
        /*foreach($categories as $category) {
            if($min_reward == 0) {
                $min_reward = $category[$user_rank['rank_id']];
            } elseif($category[$user_rank['rank_id']] < $min_reward && $min_reward > 0 && $category[$user_rank['rank_id']] > 0) {
                $min_reward = $category[$user_rank['rank_id']];
            }
        }*/

        // Check Used Vouchers
        $used_vouchers = [];
        foreach ($rewards['code'] as $item => $voucher) {
            if(get_user_meta($this->user_id, 'used_voucher_'.$voucher, true)) {
                $used_vouchers[$item] = 1;
            } else {
                $used_vouchers[$item] = 0;
            }
        }

        //$get_points = $price_points * ($discount[$user_rank['rank_id']] / 100);
        $get_points = $settings['percent'];

		include 'templates/user_rewards_dashboard.php';

        // Settings and points
        $table = $wpdb->prefix.'wpm_rewards_points';
        $user_history = $wpdb->get_results("SELECT * FROM $table WHERE user_id = $user_id AND DATE(date_expire) >= DATE(NOW()) ORDER BY id DESC");

        include('templates/profile_history.php');
    }

	/**
	* Ajax GET balance points from API
	*/
	public function get_user_api_points()
	{
        $this->phone = $_POST['phone'];
        $this->user_name = $_POST['name'];

        $api_data = $this->curlApi();

        include('templates/ajax_get_points.php');

		die;
	}

    /**
     * Check voucher is exist and not used to get points
     */
    public function voucher_submit()
    {
        if(isset($_POST) && isset($_POST['voucher'])) {
            global $wpdb;

            $vouchers = $this->vouchers;

            // Data which need before create points in DB
            $user_voucher = $_POST['voucher'];
            $settings = $this->settings;
            $current_date = date('Y-m-d');
            $expire_days = $settings['expire'];

            $result_message = "Voucher not found";

            if(isset($vouchers) && count($vouchers['code']) > 0) {
                foreach ($vouchers['code'] as $item => $voucher) {
                    if($voucher == $user_voucher && get_user_meta($this->user_id, 'used_voucher_'.$voucher, true) != 1 &&
                        strtotime($vouchers['date_expire'][$item]) > strtotime('now') && $vouchers['usings'][$item] > 0 && $vouchers['status'][$item] == 1) {

                        // Add points to DB with zero order_id
                        $wpdb->insert($this->table_name, array(
                            'user_id' => $this->user_id,
                            'order_id' => 0,
                            'points' => $vouchers['points'][$item],
                            'date_start' => $current_date,
                            'date_expire' => date('Y-m-d', strtotime($current_date. ' + '.$expire_days.' days')),
                        ));

                        // Save updated vouchers data
                        $vouchers['usings'][$item] = $vouchers['usings'][$item] - 1;
                        update_option('wpm_points_vouchers', json_encode($vouchers));

                        // Save log about using voucher
                        update_user_meta($this->user_id, 'used_voucher_'.$voucher, 1);

                        date_default_timezone_set("Asia/Karachi");
                        $date = date("D j M Y h:i:s").'+5:30';
                        $signature = $this->getApiSignature($this->phone, $date);
                        $keyid = $this->settings['api_key'];

                        // Signature
                        $header_signature = 'Signature keyId="'.$keyid.'",algorithm="hmac-sha256",headers="currentdate accept content-type",signature="'.$signature.'"';
                        $headers = ['accept: application/json', 'authorization: '.$header_signature,'currentdate: '.$date,'Content-Type: application/json'];

                        $txn_id = $this->generateRandomString();

                        // POST Body
                        $data_string = [
                            "phone" => $this->phone,
                            "name" => $this->user_name,
                            "credits" => intval($vouchers['points'][$item]),
                            "requestId" => $txn_id,
                            "receiptNumber" => rand(1000, 9999)
                        ];

                        // Add points to user
                        $ch = curl_init("https://api.weeve.tt/api/v1/issuePoints");
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_string));
                        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        $result = curl_exec($ch);
                        curl_close($ch);

                        $res = json_decode($result, true);

                        $result_message = 'Voucher is activated!';
                    } elseif($voucher == $user_voucher && get_user_meta($this->user_id, 'used_voucher_'.$voucher, true) == 1 &&
                        strtotime($vouchers['date_expire'][$item]) > strtotime('now') && $vouchers['usings'][$item] > 0 && $vouchers['status'][$item] == 1) {
                        $result_message = 'Voucher is already used';
                    }
                }
            }

            wp_redirect(get_permalink(wc_get_page_id('myaccount')).'?response='.$result_message);

            exit;
        }
    }

    /**
     * Check voucher rewards if its already used and add points to API
     */
    public function rewards_use()
    {
        if(isset($_GET) && isset($_GET['get-rewards'])) {
            global $wpdb;

            $vouchers = $this->rewards;
            $points = $this->points;

            // Data which need before create points in DB
            $user_voucher = $_GET['get-rewards'];
            $settings = $this->settings;
            $current_date = date('Y-m-d');
            $expire_days = $settings['expire'];

            $result_message = "Voucher not found";

            if(isset($vouchers) && count($vouchers['code']) > 0) {
                foreach ($vouchers['code'] as $item => $voucher) {
                    if($voucher == $user_voucher && get_user_meta($this->user_id, 'used_voucher_'.$voucher, true) != 1 && $vouchers['status'][$item] == 1 && $vouchers['need'][$item] <= $points) {

                        // Add points to DB with zero order_id
                        $wpdb->insert($this->table_name, array(
                            'user_id' => $this->user_id,
                            'order_id' => 0,
                            'points' => $vouchers['points'][$item],
                            'date_start' => $current_date,
                            'date_expire' => date('Y-m-d', strtotime($current_date. ' + '.$expire_days.' days')),
                        ));

                        // Save log about using voucher
                        update_user_meta($this->user_id, 'used_voucher_'.$voucher, 1);

                        date_default_timezone_set("Asia/Karachi");
                        $date = date("D j M Y h:i:s").'+5:30';
                        $signature = $this->getApiSignature($this->phone, $date);
                        $keyid = $this->settings['api_key'];

                        // Signature
                        $header_signature = 'Signature keyId="'.$keyid.'",algorithm="hmac-sha256",headers="currentdate accept content-type",signature="'.$signature.'"';
                        $headers = ['accept: application/json', 'authorization: '.$header_signature,'currentdate: '.$date,'Content-Type: application/json'];

                        $txn_id = $this->generateRandomString();

                        // POST Body
                        $data_string = [
                            "phone" => $this->phone,
                            "name" => $this->user_name,
                            "credits" => intval($vouchers['points'][$item]),
                            "requestId" => $txn_id,
                            "receiptNumber" => rand(1000, 9999)
                        ];

                        // Add points to user
                        $ch = curl_init("https://api.weeve.tt/api/v1/issuePoints");
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_string));
                        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        $result = curl_exec($ch);
                        curl_close($ch);

                        $res = json_decode($result, true);

                        $result_message = 'Voucher is activated!';
                    } elseif($voucher == $user_voucher && get_user_meta($this->user_id, 'used_voucher_'.$voucher, true) == 1 && $vouchers['status'][$item] == 1) {
                        $result_message = 'Voucher is already used';
                    } elseif($voucher == $user_voucher && $vouchers['need'][$item] > $points && $vouchers['status'][$item] == 1
                        && get_user_meta($this->user_id, 'used_voucher_'.$voucher, true) != 1) {
                        $result_message = 'Not enough points to take reward';
                    }
                }
            }

            wp_redirect(get_permalink(wc_get_page_id('myaccount')).'?response='.$result_message);

            exit;
        }
    }

    /**
     * Add features to product if user is have member VIP role
     */
    public function discount_rates($tabs)
    {
        $tabs['discounts_ranks'] = array(
            'label'    => 'Discount Ranks',
            'target'   => 'discounts_ranks_tab_content',
            'priority' => 15,
        );

        return $tabs;
    }

    /**
     * Content discounts table
     */
    public function discounts_ranks_tab_contents()
    {
        $settings = $this->settings;
        $product_discounts = json_decode(get_post_meta($_GET['post'], 'wpm_rewards_product_discount', true), true);

        include('templates/discounts_ranks_tab.php');
    }

    /**
     * Load all settings and scores if user logged
     */
    public function load_user_points()
    {
        if(is_user_logged_in()) {
            global $wpdb;

            $user_id = get_current_user_id();
            $this->phone = get_user_meta($user_id, 'billing_phone', true);
            $this->user_name = get_user_meta($user_id, 'billing_first_name', true);
            $this->table_name = $wpdb->prefix.'wpm_rewards_points';

            // Get settings WPM Rewards
            $this->settings = json_decode(get_option('wpm_points_settings'), true);
            $this->categories = json_decode(get_option('wpm_points_categories'), true);
            $this->vouchers = json_decode(get_option('wpm_points_vouchers'), true);
            $this->rewards = json_decode(get_option('rewards_vouchers'), true);

            // Get user Points from DB
            $this->user_id = $user_id;

            // User Rewards data
            $this->points = 0;
            $this->user_rank = [];

            // Get Scores
            if($this->phone && $this->user_name) {
                $api_data = $this->curlApi();
                $this->points = $api_data['balance'];
            }

            // Find user rank by scores
            foreach ($this->settings['rank'] as $item => $rank) {
                if($this->settings['points'][$item] <= $this->points) {
                    $this->user_rank = [
                        'rank' => $rank,
                        'rank_id' => $item
                    ];
                }
            }
        }
    }

    /**
     * Rewards dashboard in user account
     */
    public function set_rank_price_products($price)
    {
        if(is_user_logged_in()) {
            $categories = $this->categories;
            $user_rank = $this->user_rank;
            $discount = 0;

            // Get categories of product
            if(is_shop() || is_product_category() || is_product() || is_single()) {
                global $post;
                global $product;

                $product_id = $product->ID;

                if(!$product_id) {
                    $product_id = $post->ID;
                }

                $category = get_the_terms($product_id, 'product_tag');

                $product_discounts = json_decode(get_post_meta($product_id, 'wpm_rewards_product_discount', true), true);

                // Get Discount and calculate price
                if(isset($product_discounts['global']) && $product_discounts['global'] > 0) {
                    $price = $product_discounts['global'];
                } elseif(isset($product_discounts[$user_rank['rank_id']]) && $product_discounts[$user_rank['rank_id']] > 0) {
                    $discount = $product_discounts[$user_rank['rank_id']];
                } elseif(isset($categories['global'][$user_rank['rank_id']]) && $categories['global'][$user_rank['rank_id']] > 0) {
                    $discount = $categories['global'][$user_rank['rank_id']];
                } elseif(isset($categories[$category[0]->term_id][$user_rank['rank_id']])) {
                    $discount = $categories[$category[0]->term_id][$user_rank['rank_id']];
                }

                // Calculate result price
                if($discount > 0) {
                    $total =  $price - ($price * ($discount / 100));
                    $price = number_format($total, 2, '.', '');
                }
            }
        }

        return $price;
    }

    /**
     * Change price on products in cart by rank user
     */
    public function set_cart_items_price($cart_object)
    {
        $free_product = WC()->session->get('free-product');

        if($free_product != 0) {
            foreach ($cart_object->get_cart() as $hash => $value) {
                $sku = $value['data']->get_sku();

                if($sku == $free_product) {
                    $value['data']->set_price(0);
                }
            }
        }
    }

    /**
     * Rewards dashboard in user account
     */
    public function add_points_to_user($order_id)
    {
        global $wpdb;

        // Get order details
        $order = new WC_Order($order_id);
        $total = 0;

        // Get Settings
        $categories = $this->categories;
        $settings = $this->settings;
        $user_rank = $this->user_rank;

        // User Rewards data
        $points = 0;
        $user_rank = [];

        $user_id = $order->get_user_id();
        $this->phone = get_user_meta($user_id, 'billing_phone', true);
        $this->user_name = get_user_meta($user_id, 'billing_first_name', true);

        // Get Scores
        if($this->phone && $this->user_name) {
            $api_data = $this->curlApi();
            $this->points = $api_data['balance'];
        }

        // Find user rank by scores
        foreach ($settings['rank'] as $item => $rank) {
            if($settings['points'][$item] <= $this->points) {
                $user_rank = [
                    'rank' => $rank,
                    'rank_id' => $item
                ];
            }
        }

        foreach ($order->get_items() as $item_id => $item) {
            $price = $item->get_subtotal();
            $product = $item->get_product();

            /*$category = get_the_terms($product->get_id(), 'product_tag');
            if(isset($categories[$category[0]->term_id][$user_rank['rank_id']])) {
                $discount = $categories[$category[0]->term_id][$user_rank['rank_id']];
            }*/

            $discount = $settings['percent'];

            // Calculate result price
            if($discount > 0) {
                $price = $price - ($price - ($price * ($discount / 100)));
            }


            $total += number_format($price, 0, '.', '');
        }

        $current_date = date('Y-m-d');
        $expire_days = $settings['expire'];

        // Insert points to DB
        $table_name = $wpdb->prefix.'wpm_rewards_points';
        $wpdb->insert($table_name, array(
            'user_id' => $user_id,
            'order_id' => $order_id,
            'points' => $total,
            'date_start' => $current_date,
            'date_expire' => date('Y-m-d', strtotime($current_date. ' + '.$expire_days.' days')),
        ));

        // Header data
        date_default_timezone_set("Asia/Karachi");
        $date = date("D j M Y h:i:s").'+5:30';
        $signature = $this->getApiSignature($this->phone, $date);
        $keyid = $this->settings['api_key'];

        // Signature
        $header_signature = 'Signature keyId="'.$keyid.'",algorithm="hmac-sha256",headers="currentdate accept content-type",signature="'.$signature.'"';
        $headers = ['accept: application/json', 'authorization: '.$header_signature,'currentdate: '.$date,'Content-Type: application/json'];

        $txn_id = $this->generateRandomString();

        // POST Body
        $data_string = [
            "phone" => $order->get_billing_phone(),
            "name" => $order->get_billing_first_name(),
            "credits" => $total,
            "requestId" => $txn_id,
            "receiptNumber" => $order_id
        ];

        // Add points to user
        $ch = curl_init("https://api.weeve.tt/api/v1/issuePoints");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_string));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        $res = json_decode($result, true);
    }

    function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    /**
     * Rewards dashboard in user account
     */
    public function rewards_dashboard()
    {
		global $points;

        // Get settings and discounts
        $categories = $this->categories;
        $settings = $this->settings;
        $user_rank = $this->user_rank;
        $points = $this->points;

        // Create variables
        $currency = get_woocommerce_currency_symbol();
        $price_points = 100;
        $discount = [];

        // Find bigger discount on categories by ranks
        foreach ($categories as $category) {
            foreach ($settings['rank'] as $rank_id => $rank) {
                if($category[$rank_id] > $discount[$rank_id] || !isset($discount[$rank_id])) {
                    if($categories['global'][$rank_id] > 0) {
                        $discount[$rank_id] = $categories['global'][$rank_id];
                    } else {
                        $discount[$rank_id] = $category[$rank_id];
                    }
                }
            }
        }

        $get_points = $price_points * ($discount[$user_rank['rank_id']] / 100);

		include 'templates/user_rewards_dashboard.php';
    }

    /**
     * Create new menu and page in navigation
     */
    public function register_menu()
    {
        add_menu_page('Weeve', 'Weeve', 'edit_others_posts', 'wpm_points');
        add_submenu_page('wpm_points', 'Settings Rewards', 'Settings', 'manage_options', 'wpm_points', function () {
            $settings = $this->settings;

            include 'templates/settings_page.php';
        });
        /*add_submenu_page('wpm_points', 'Categories Settings', 'Categories', 'manage_options', 'wpm_points', function () {

            $args = array(
                'taxonomy'   => "product_tag",
                'hide_empty' => false
            );

            $product_categories = get_terms($args);
            $categories = $this->categories;
            $settings = $this->settings;

            include 'templates/categories_page.php';
        });*/

        add_submenu_page('wpm_points', 'Vouchers Points', 'Vouchers', 'manage_options', 'wpm_points_vouchers', function () {
            $vouchers = $this->vouchers;
            $vouchers_ranks = $this->rewards;

            include 'templates/vouchers_page.php';
        });
    }

    /**
     * Save data from WPM Rewards pages to DB Options
     */
    public function save_rewards_data()
    {
        if(isset($_POST) && isset($_POST['wpm_points_categories'])) {
            $data = $_POST['wpm_points_categories'];
            update_option('wpm_points_categories', json_encode($data));
        }

        if(isset($_POST) && isset($_POST['wpm_points_vouchers'])) {
            $data = $_POST['wpm_points_vouchers'];
            update_option('wpm_points_vouchers', json_encode($data));
        }

        if(isset($_POST) && isset($_POST['rewards_vouchers'])) {
            $data = $_POST['rewards_vouchers'];
            update_option('rewards_vouchers', json_encode($data));
        }

        if(isset($_POST) && isset($_POST['wpm_points_settings'])) {
            $data = $_POST['wpm_points_settings'];
            update_option('wpm_points_settings', json_encode($data));
        }

        if(isset($_POST) && isset($_POST['wpm_rewards_product_discount']) && isset($_POST['post_ID'])) {
            $data = $_POST['wpm_rewards_product_discount'];
            update_post_meta($_POST['post_ID'], 'wpm_rewards_product_discount', json_encode($data));
        }
    }

    /**
     * Connection to API for Register user or get Points
     */
    public function curlApi()
    {
        // Header data
        date_default_timezone_set("Asia/Karachi");
        $date = date("D j M Y h:i:s").'+5:30';
        $signature = $this->getApiSignature($this->phone, $date);
        $keyid = $this->settings['api_key'];

        // Signature
        $header_signature = 'Signature keyId="'.$keyid.'",algorithm="hmac-sha256",headers="currentdate accept content-type",signature="'.$signature.'"';
        $headers = ['accept: application/json', 'authorization: '.$header_signature,'currentdate: '.$date,'Content-Type: application/json'];

        // POST Body
        $data_string = [
            "phone" => $this->phone,
            "name" => $this->user_name
        ];

        // Try to add new user
        $ch = curl_init("https://api.weeve.tt/api/v1/users/");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_string));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        $res = json_decode($result, true);

        // Check if user exist - get balance
        if(isset($res['success']) && $res['success'] == "") {
            $ch = curl_init("https://api.weeve.tt/api/v1/users/?phone=".$this->phone."");
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            curl_close($ch);

            $res = json_decode($result, true);
        }

        return json_decode($result, true);
    }

    /**
     * Create DB Table with Points and Vouchers
     */
    public function sports_bench_create_db()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH.'wp-admin/includes/upgrade.php');

        // Save default settings to options
        $data = [
            'rank' => ['Beginner', 'Advanced', 'Legend'],
            'points' => [0, 100, 300],
            'percent' => 0,
            'expire' => 365
        ];

        update_option('wpm_points_settings', json_encode($data));

        // Create table Points
        $table_name = $wpdb->prefix.'wpm_rewards_points';
        $sql = "CREATE TABLE $table_name (
         id INTEGER NOT NULL AUTO_INCREMENT,
         user_id INTEGER(10) NOT NULL,
         order_id INTEGER(10) NOT NULL,
         points INTEGER(10) NOT NULL,
         date_start DATE NOT NULL,
         date_expire DATE NOT NULL,
         PRIMARY KEY (id)
        ) $charset_collate;";
            dbDelta($sql);
    }

}

new WPM_Rewards();