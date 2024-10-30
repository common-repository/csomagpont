<?php

class Csomagpont
{

    public function __construct($csomagpont_settings_obj)
    {
        $this->csomagpont_settings_obj = $csomagpont_settings_obj;
        $this->csomagpont_settings = $this->csomagpont_settings_obj->get_csomagpont_settings();
        $this->export_details = array();
        $this->export_details_for_api = array();
        $this->export_allowed = false;
        $this->admin_orders_url = get_bloginfo('url') . '/wp-admin/edit.php?post_type=shop_order';

        add_action('init', array($this, 'export_when_logged_in'));
        add_action('admin_enqueue_scripts', array($this, 'footer_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'header_scripts'));
        add_action('wp_ajax_label_download', array($this, 'label_download'));
        add_action('wp_ajax_view_signature', array($this, 'view_signature'));
        add_action('wp_ajax_mpl_sending_download', array($this, 'mpl_sending_download'));
        add_action('wp_ajax_view_package_log', array($this, 'view_package_log'));
        add_action('wp_ajax_package_details', array($this, 'package_details'));
        add_action('wp_ajax_api_custom_send', array($this, 'api_custom_send'));
        add_action('wp_ajax_csp_api_label', array($this, 'download_label_from_csp_api'));
        add_action('wp_ajax_csp_api_mpl_sending', array($this, 'mpl_sending_download_multiple'));

        add_action('admin_notices', array($this, 'csomagpont_sent_notice'));

        // Dokumentumok rendszeres törlése
        add_action( 'csp_delete_cron_hook', array($this, 'csp_delete_cron_exec' ));
        if ( ! wp_next_scheduled( 'csp_delete_cron_hook' ) ) {
            wp_schedule_event( time(), 'hourly', 'csp_delete_cron_hook' );
        }

    }

    public function csp_delete_cron_exec() {
        $upload_dir = wp_get_upload_dir()['path'] . '/';
        $files = scandir($upload_dir);

        foreach ($files as $file) {
            if (substr_compare($file, '-cspfeladojegyzek.pdf', -21) === 0 ||
            substr_compare($file, '-cspcimke.pdf', -13) === 0 ) {
                $now = time();
                if ($now - filemtime($upload_dir . $file) >= 60 * 30) {
                    wp_delete_file($upload_dir . $file);
                }
            }
        }
    }

    public function footer_scripts($hook)
    {
        $post_type = get_query_var('post_type', '');
        wp_register_script('csomagpont-admin-js', CSOMAGPONT_DIR_URL . 'js/admin.js', array(), false, true);
        wp_enqueue_script('csomagpont-admin-js');

        if ($hook == 'woocommerce_page_deliveo-settings') {
            wp_register_script('csomagpont-validation-js', CSOMAGPONT_DIR_URL . 'js/validation.js', array(), false, true);
            wp_enqueue_script('csomagpont-validation-js');

            wp_register_script('csomagpont-admin-js', CSOMAGPONT_DIR_URL . 'js/admin.js', array(), false, true);
            wp_enqueue_script('csomagpont-admin-js');
        }

        if ($hook == 'edit.php' && $post_type == 'shop_order') {
            wp_register_script('csomagpont-export-js', CSOMAGPONT_DIR_URL . 'js/export.js', array(), false, true);
            wp_enqueue_script('csomagpont-export-js');
        }

    }

    public function header_scripts()
    {
        wp_register_style('csomagpont-admin-css', CSOMAGPONT_DIR_URL . 'css/csomagpont.css', false, '1.0.0');
        wp_enqueue_style('csomagpont-admin-css');
    }

    public function generate_csv()
    {

        if (isset($_GET['generate_csomagpont_csv'])) {
            $vars = explode(',', sanitize_text_field($_GET['generate_csomagpont_csv']));
            $orders = '';
            foreach ($vars as $var) {
                $orders .= explode('-', $var)[0];
            }

            $order_items = $this->get_export_details($orders);

            $csv_builder = new Csomagpont_Builder($order_items);
            $csv_content = $csv_builder->build_csv();

            $csv_export = new Csomagpont_Export();
            $csv_export->export($csv_content);
        }
    }

    public function send_by_api()
    {
        $settings = $this->csomagpont_settings;
        $export_allowed = $this->export_allowed;

        $errors = 0;

        if (!isset($_SESSION)) {
            session_start();
        }
        $csomagpont_sent_message = '';

        $result = '1';
        if (isset($_GET['csomagpont_api_send'])) {
            // var_dump(get_option('csomagpont_settings'));
            
            // $itemWeight = json_decode(get_option('csomagpont_settings'))->item_weight;
            // $itemWeight = 1000;
            // var_dump($itemWeight);
            // die();
            $orders = explode(',', sanitize_text_field($_GET['csomagpont_api_send']));
            foreach ($orders as $order) {
                $order_id = explode('-', $order)[0];

                // var_dump(!get_metadata('post', $order_id, '_goup_code', true));
                if (get_metadata('post', $order_id, '_csomagpont_exported', true) != "true") {
                    // echo "<h1>TEST</h1>";
                    $shipping = explode('-', $order)[1] ?: $settings['delivery'];
                    $unit = explode('-', $order)[2];
                    $itemWeight = explode('-', $order)[3];
                    $packageWeight = explode('-', $order)[4];
                    $packageMaterialWeight = explode('-', $order)[5];
                    $cod = $this->get_cod($order_id);
                    $currentOrder = wc_get_order($order_id);
                    $cartProducts = array();
                    $comment = $currentOrder->get_customer_note() . ' ';

                    foreach ($currentOrder->get_items() as $item_id => $item_data) {
                        $product = $item_data->get_product();
                        if (!$product || $product->is_virtual()) {
                          continue;
                        }
                        $sku = $product->get_sku();
                        for ($i = 0; $i < $item_data->get_quantity(); $i++) {
                            $divider = get_option('woocommerce_weight_unit') == 'g' ? 1000 : 1;
                            $weight = $product->get_weight() / $divider;
                            if ($weight == '') {
                                $weight = 0;
                            }
                            $x = $product->get_width();
                            if ($x == '') {
                                $x = $settings['x'];
                            }
                            $y = $product->get_height();
                            if ($y == '') {
                                $y = $settings['y'];
                            }
                            $z = $product->get_length();
                            if ($z == '') {
                                $z = $settings['z'];
                            }

                            $cartProducts[] = array(
                                "x" => $x ?: 1,
                                "y" => $y ?: 1,
                                "z" => $z ?: 1,
                                "weight" => $weight ?: 0,
                                "item_no" => $sku ?: '',
                                // "item_no" => '',

                            );
                        }
                    }

                    if ( $packageWeight < $itemWeight ){
                        echo '<script> alert("#'. $order_id.': Az egész csomag súlyánál [ '. $packageWeight .'g ] megadott adat kisebb a termék súlyánál [ '. $itemWeight .'g ]! Kérjük, ellenőrizze, hogy helyesen adta meg az egész csomag súlyát!")</script>';
                        $errors = $errors + 1;
                        continue;
                    }

                    if ( $packageWeight < 10 ) {
                      echo '<script> alert("#'. $order_id.': A teljes csomag súlyának legalább 10g-nak kell lennie.")</script>';
                        $errors = $errors + 1;
                        continue;
                    }

                   //wp_die(var_dump($packageWeight));

                    if ($packageWeight > 0) {
                        $csomagpont_settings = json_decode(get_option('csomagpont_settings'));
                        if (isset($packageMaterialWeight)) {
                            $package_weight = $packageMaterialWeight;
                        } else {
                            $package_weight = (isset($csomagpont_settings->packaging_weight) && $csomagpont_settings->packaging_weight != "" && $csomagpont_settings->packaging_weight != 0) ? $csomagpont_settings->packaging_weight : 100;
                        }

                        $divider = 1000;
                        // $dividedWeight = $packageWeight / $divider;
                        // $dividedItemWeight = $itemWeight / $divider;
                        $cartProducts[] = array(
                            "weight" => $package_weight / $divider,
                            "customcode" => 'csomagoloanyag',
                        );
                    }

                    $shop_id = '';
                    // Csomagátvevőhelyek (Szathmári plugin) TESZT
                    if (is_plugin_active('wc-pont/pont.php')) {
                        $pont_metadata = get_metadata('post', $order_id, 'wc_selected_pont', true);
                        if ($pont_metadata) {
                            $pont_metadata_arr = explode('|', $pont_metadata);
                            $pont_type = $pont_metadata_arr[1];
                            global $csp_enabled_pont_types;
                            if (in_array($pont_type, $csp_enabled_pont_types)) {
                                $shop_id = $pont_metadata_arr[2];
                            }
                        }
                        
                        // [pontcíme]|[szolgáltató neve]|[átvevőpont azonosítója]
                    }

                    $package = array(
                        'sender' => $settings['sender'],
                        'sender_country' => $settings['sender_country_code'],
                        'sender_zip' => $settings['sender_zip'],
                        'sender_city' => $settings['sender_city'],
                        'sender_address' => $settings['sender_address'],
                        'sender_apartment' => $settings['sender_apartment'],
                        'sender_phone' => $settings['sender_phone'],
                        'sender_email' => $settings['sender_email'],
                        'consignee' => get_metadata('post', $order_id, '_shipping_first_name', true) . ' ' . get_metadata('post', $order_id, '_shipping_last_name', true),
                        'consignee_country' => get_metadata('post', $order_id, '_shipping_country', true) ? get_metadata('post', $order_id, '_shipping_country', true) : 'HU',
                        'consignee_zip' => get_metadata('post', $order_id, '_shipping_postcode', true),
                        'consignee_city' => get_metadata('post', $order_id, '_shipping_city', true),
                        'consignee_address' => get_metadata('post', $order_id, '_shipping_address_1', true),
                        'consignee_apartment' => get_metadata('post', $order_id, '_shipping_address_2', true),
                        'consignee_phone' => get_metadata('post', $order_id, '_billing_phone', true),
                        'consignee_email' => get_metadata('post', $order_id, '_billing_email', true),
                        'delivery' => $shipping,
                        'priority' => 0,
                        'saturday' => 0,
                        'insurance' => 0,
                        'referenceid' => $this->get_reference_id($order_id),
                        'cod' => $cod,
                        'freight' => 'felado',
                        'tracking' => $order_id,
                        'comment' => $comment,
                        'shop_id' => $shop_id,
                        'packages' => $cartProducts,
                        'packaging_unit' => $unit
                    );

                    $csomagpont_api = new Csomagpont_API($this->csomagpont_settings_obj);
                    $csomagpont_progress = $csomagpont_api->send_order_items($order_id, $package, $export_allowed);
                    $csomagpont_sent_message .= $csomagpont_progress . "\n";
                }

                // Lassítjuk a kérések küldését
                usleep(300);
            }
            //die;

            if ($errors == 0){
                $_SESSION['csomagpont_sent_message'] = $csomagpont_sent_message;
                header('Location:' . $this->admin_orders_url . '&deliveo_ok');
            }
        }
    }

    function csomagpont_sent_notice() {
        if (isset($_SESSION['csomagpont_sent_message']) && $_SESSION['csomagpont_sent_message']) {
            ?>
            <div class="notice notice-info is-dismissible">
                <p><?php echo $_SESSION['csomagpont_sent_message'] ?></p>
            </div>
            <?php
            $_SESSION['csomagpont_sent_message'] = '';
        }
        
    }

    public function export_when_logged_in()
    {
        if (is_user_logged_in()) {
            $this->generate_csv();
            $this->send_by_api();
        }
    }

    /* Details for exports by order ids. If the second param is true, the data structure is for API call */
    public function get_export_details($order_ids, $for_api = false)
    {
        global $wpdb;

        $order_ids = explode(',', $order_ids);

        foreach ($order_ids as $order_id) {

            $this->set_order_item_details($order_id);
        }
        if ($for_api) {
            return $this->export_details_for_api;
        } else {
            return $this->export_details;
        }
    }

    public function init_export_details_for_api($order_id)
    {

        $allSelected = explode(',', sanitize_text_field($_GET['generate_csomagpont_csv']));
        $shipping = '';

        foreach ($allSelected as $selected) {
            $details = explode('-', $selected);
            if ($details[0] == $order_id) {
                $shipping = $details[1];
            }
        }

        $settings = $this->csomagpont_settings;
        $cod = $this->get_cod($order_id);
        $insurance = (int) $settings['insurance'];

        if ($insurance == 1) {
            $insurance = get_metadata('post', $order_id, '_order_total', true);
        }

        $this->export_details_for_api['order_id_' . $order_id] = array(
            'sender' => $settings['sender'],
            'sender_country' => $settings['sender_country_code'],
            'sender_zip' => $settings['sender_zip'],
            'sender_city' => $settings['sender_city'],
            'sender_address' => $settings['sender_address'],
            'sender_apartment' => $settings['sender_apartment'],
            'sender_phone' => $settings['sender_phone'],
            'sender_email' => $settings['sender_email'],
            'consignee' => get_metadata('post', $order_id, '_shipping_first_name', true) . ' ' . get_metadata('post', $order_id, '_shipping_last_name', true),
            'consignee_country' => get_metadata('post', $order_id, '_shipping_country', true),
            'consignee_zip' => get_metadata('post', $order_id, '_shipping_postcode', true),
            'consignee_city' => get_metadata('post', $order_id, '_shipping_city', true),
            'consignee_address' => get_metadata('post', $order_id, '_shipping_address_1', true),
            'consignee_apartment' => get_metadata('post', $order_id, '_shipping_address_2', true),
            'consignee_phone' => get_metadata('post', $order_id, '_billing_phone', true),
            'consignee_email' => get_metadata('post', $order_id, '_billing_email', true),
            'delivery' => $shipping, //int(10) 'Szállítási opció',
            'priority' => $settings['priority'], //int(1) 'Elsőbbségi kézbesítés',
            'saturday' => $settings['saturday'], //int(1) 'Szombati késbesítés',
            'insurance' => $insurance,
            'referenceid' => $this->get_reference_id($order_id),
            'cod' => $cod, //decimal(10,2) 'Utánvét összege',
            'freight' => $settings['freight'],
            'comment' => ' ',
            'packages' => array(),
        );
    }

    /* Set the details for CSV and API */
    public function set_order_item_details($order_id)
    {
        global $wpdb;

        $order_lines = array();
        $csomagpont_settings = $this->csomagpont_settings;

        $query = 'SELECT * FROM ' . $wpdb->prefix . 'woocommerce_order_items woi
			JOIN ' . $wpdb->prefix . 'woocommerce_order_itemmeta woim
			ON (woi.order_item_id = woim.order_item_id)
			JOIN ' . $wpdb->prefix . 'posts p
			ON (woim.meta_value = p.ID)
			WHERE woi.order_id = "' . $order_id . '" AND woim.meta_key = "_product_id"';

        $results = $wpdb->get_results($query);
        $exported = get_metadata('post', $order_id, '_csomagpont_exported', true);
        $comment = $this->get_order_comment($order_id) . ' ';

        if ($exported != 'true') {
            $this->init_export_details_for_api($order_id);

            foreach ($results as $result) {
                $weight = csp_post_meta($result->ID, '_weight', 1);
                $x = csp_post_meta($result->ID, '_length', $csomagpont_settings['x']);
                $y = csp_post_meta($result->ID, '_width', $csomagpont_settings['y']);
                $z = csp_post_meta($result->ID, '_height', $csomagpont_settings['z']);
                $item_no = get_metadata('post', $result->ID, '_sku', true);
                $cod = $this->get_cod($order_id[0]);
                $qty = (int) wc_get_order_item_meta($result->order_item_id, '_qty', true);

                $this->export_allowed = true;

                for ($i = 0; $i < $qty; $i++) {
                    //Do not modify the order of this array item list. This array match for CSV header
                    $this->export_details[] = array(
                        'saturday' => $csomagpont_settings['saturday'],
                        'referenceid' => $this->get_reference_id($order_id[0]),
                        'cod' => $cod,
                        'sender_id' => '',
                        'sender' => $csomagpont_settings['sender'],
                        'sender_country_code' => $csomagpont_settings['sender_country_code'],
                        'sender_zip' => $csomagpont_settings['sender_zip'],
                        'sender_city' => $csomagpont_settings['sender_city'],
                        'sender_address' => $csomagpont_settings['sender_address'],
                        'sender_apartment' => $csomagpont_settings['sender_apartment'],
                        'sender_phone' => $csomagpont_settings['sender_phone'],
                        'sender_email' => $csomagpont_settings['sender_email'],
                        'consignee_id' => '',
                        'consignee' => get_metadata('post', $order_id[0], '_shipping_first_name', true) . ' ' . get_metadata('post', $order_id[0], '_shipping_last_name', true),
                        'consignee_zip' => get_metadata('post', $order_id[0], '_shipping_postcode', true),
                        'consignee_city' => get_metadata('post', $order_id[0], '_shipping_city', true),
                        'consignee_address' => get_metadata('post', $order_id[0], '_shipping_address_1', true),
                        'consignee_apartment' => get_metadata('post', $order_id[0], '_shipping_address_2', true),
                        'consignee_phone' => get_metadata('post', $order_id[0], '_billing_phone', true),
                        'consignee_email' => get_metadata('post', $order_id[0], '_billing_email', true),
                        'weight' => $weight,
                        'comment' => $comment,
                        'group_id' => '',
                        'pick_up_point' => '',
                        'x' => $x,
                        'y' => $y,
                        'z' => $z,
                        'customcode' => '',
                        'item_no' => $item_no,
                    );

                    $this->export_details_for_api['order_id_' . $order_id[0]]['comment'] = $comment;
                    $this->export_details_for_api['order_id_' . $order_id[0]]['packages'][] = array(
                        'weight' => $weight,
                        'x' => $x,
                        'y' => $y,
                        'z' => $z,
                        'item_no' => $item_no,
                    );
                }
            }
        }
    }

    /* if payment type is COD the cost will be the payment total */
    private function get_cod($order_id)
    {
        $payment_type = get_metadata('post', $order_id, '_payment_method', true);
        $cod = 0;

        if ($payment_type == 'cod') {
            $cod = get_metadata('post', $order_id, '_order_total', true);
        }
        
        if ($payment_type == 'cheque') {
            $cod = 0;
        }

        return $cod;
    }

    /** If user check the reference id equal to order id on Csomagpont settings, the function return with order id as reference id */
    private function get_reference_id($order_id)
    {
        $settings = $this->csomagpont_settings;
        $reference_id_is_order_id = 0;
        $reference_id = '';

        if ($reference_id_is_order_id) {
            $reference_id = '#' . $order_id;
        }

        return $reference_id;
    }

    /** Get order comment */
    private function get_order_comment($order_id)
    {
        global $wpdb;

        $query = 'SELECT post_excerpt FROM ' . $wpdb->prefix . 'posts WHERE post_type = "shop_order" AND ID = "' . $order_id . '"';
        $result = $wpdb->get_row($query);

        return '';
        // return $result->post_excerpt;
    }

    public function label_download()
    {     
        $settings = $this->csomagpont_settings;
        $label_url = "https://api.deliveo.eu/label/" . sanitize_text_field($_POST["group_id"]) . "?licence=" . CSP_LICENCE . "&api_key=" . $settings["api_key"];
        $package_url = "https://api.deliveo.eu/package/" . sanitize_text_field($_POST["group_id"]) . "?licence=" . CSP_LICENCE . "&api_key=" . $settings["api_key"];

        $tmpfile = download_url($label_url, $timeout = 300);

        $check_signature = json_decode(wp_remote_fopen($package_url), true);
        if ($check_signature['data'][0]['group_id'] == sanitize_text_field($_POST["group_id"])) {
            $permfile = $_POST['group_id'] . '.pdf';
            $destfile = wp_get_upload_dir()['path'] . "/" . sanitize_text_field($_POST['group_id']) . '-cspcimke.pdf';
            $dest_url = wp_get_upload_dir()['url'] . "/" . sanitize_text_field($_POST['group_id']) . '-cspcimke.pdf';
            copy($tmpfile, $destfile);
            unlink($tmpfile);
            $package =
                '<a target="blank" href="' . $dest_url . '"><img title="Csomagcímke letöltése ehhez a csoportkódhoz: ' . sanitize_text_field($_POST['group_id']) . '"  style="vertical-align:middle;height:36px;" src="' . CSOMAGPONT_DIR_URL . 'images/csomagpont-package-barcode.png" ></a>';
            echo $package;
        } else {
            $package =
                '<img title="Csomagcímke nem található ehhez a csoportkódhoz: ' . sanitize_text_field($_POST['group_id']) . '"  style="vertical-align:middle;height:36px;filter:grayscale(100%);" src="' . CSOMAGPONT_DIR_URL . 'images/csomagpont-package-barcode.png" >';
            echo $package;
        }

        wp_die();
    }

    public function mpl_sending_download()
    {
        $settings = $this->csomagpont_settings;
        $label_url = "https://api.deliveo.eu/mpl_sending?licence=" . CSP_LICENCE . "&api_key=" . $settings["api_key"];
        $package_url = "https://api.deliveo.eu/package/" . sanitize_text_field($_POST["group_id"]) . "?licence=" . CSP_LICENCE . "&api_key=" . $settings["api_key"];

        $post_data = array(
            "group_ids" => array(
                0   =>  sanitize_text_field($_POST["group_id"])
            )
        );

        $tmpfile = csp_download_url_post($label_url, $post_data, $timeout = 300);

        $check_signature = json_decode(wp_remote_fopen($package_url), true);
        if ($check_signature['data'][0]['group_id'] == $_POST["group_id"]
        && ($check_signature['data'][0]['delivery_id'] == 2 
        || $check_signature['data'][0]['delivery_id'] == 19) ) {
            $permfile = sanitize_text_field($_POST['group_id']) . '.pdf';
            $destfile = wp_get_upload_dir()['path'] . "/" . sanitize_text_field($_POST['group_id']) . '-cspfeladojegyzek.pdf';
            $dest_url = wp_get_upload_dir()['url'] . "/" . sanitize_text_field($_POST['group_id']) . '-cspfeladojegyzek.pdf';
            copy($tmpfile, $destfile);
            unlink($tmpfile);
            $package =
                '<a target="blank" href="' . $dest_url . '"><img title="MPL feladójegyzék letöltése ehhez a csoportkódhoz: ' . sanitize_text_field($_POST['group_id']) . '"  style="vertical-align:middle;height:26px;" src="' . CSOMAGPONT_DIR_URL . 'images/csomagpont-signature.png" ></a>';
            echo $package;
        } else {
            $package =
                '<img title="MPL feladójegyzék nem található ehhez a csoportkódhoz: ' . sanitize_text_field($_POST['group_id']) . '"  style="vertical-align:middle;height:26px;filter:grayscale(100%);" src="' . CSOMAGPONT_DIR_URL . 'images/csomagpont-signature.png" >';
            echo $package;
        }

        wp_die();
    }

    public function label_download_multiple() {

        $settings = $this->csomagpont_settings;
        $url_arr = array();
        $group_ids = explode(",", sanitize_text_field($_POST['group_ids']));

        foreach ($group_ids as $group_id) {
            $label_url = "https://api.deliveo.eu/label/" . $group_id . "?licence=" . CSP_LICENCE . "&api_key=" . $settings["api_key"];
            $tmpfile = download_url($label_url, $timeout = 300);
            $permfile = $group_id . '.pdf';
            $destfile = wp_get_upload_dir()['path'] . "/" . $group_id . '-cspcimke.pdf';
            $dest_url = wp_get_upload_dir()['url'] . "/" . $group_id . '-cspcimke.pdf';
            copy($tmpfile, $destfile);
            unlink($tmpfile);
            array_push($url_arr, $dest_url);
        }

        $url_string = implode(',', $url_arr);

        
        echo "$url_string";
        wp_die();
    }

    public function download_label_from_csp_api() {
        $settings = $this->csomagpont_settings;

        $label_url = "http://cimke.csomagpont.com/label";
        $post_data = array(
            "packages"  => $_POST['packages'],
            "apiKey"    => $settings["api_key"]
        );

        $tmpfile = csp_download_url_post($label_url, $post_data, $timeout = 300);
        $permfile = time() . '.pdf';
        $destfile = wp_get_upload_dir()['path'] . "/" . time() . '-cspcimke.pdf';
        $dest_url = wp_get_upload_dir()['url'] . "/" . time() . '-cspcimke.pdf';
        copy($tmpfile, $destfile);
        unlink($tmpfile);

        echo "$dest_url";
        wp_die();
    }

    public function mpl_sending_download_multiple() {
        $settings = $this->csomagpont_settings;
        $label_url = "https://api.deliveo.eu/mpl_sending?licence=" . CSP_LICENCE . "&api_key=" . $settings["api_key"];

        $group_ids = explode(",", sanitize_text_field($_POST['group_ids']));
        $post_data = array(
            "group_ids" => $group_ids
        );

        $tmpfile = csp_download_url_post($label_url, $post_data, $timeout = 300);
        $permfile = time() . '.pdf';
        $destfile = wp_get_upload_dir()['path'] . "/" . time() . '-cspfeladojegyzek.pdf';
        $dest_url = wp_get_upload_dir()['url'] . "/" . time() . '-cspfeladojegyzek.pdf';
        copy($tmpfile, $destfile);
        unlink($tmpfile);

        echo "$dest_url";
        wp_die();
    }

    // Ez nem használt
    public function view_signature()
    {
        $settings = $this->csomagpont_settings;
        $signature_url = "https://api.deliveo.eu/signature/" . sanitize_text_field($_POST["group_id"]) . "?licence=" . CSP_LICENCE . "&api_key=" . $settings["api_key"];

        $tmpfile = download_url($signature_url, $timeout = 300);

        $check_signature = json_decode(wp_remote_fopen($signature_url), true);
        if ($check_signature['type'] == 'error') {
            $response = array(
                "error" => "no_signature",
                "img" => '<span></span>',
            );
            echo json_encode($response);
        } else {
            $permfile = sanitize_text_field($_POST['group_id']) . '_sign.pdf';
            $destfile = wp_get_upload_dir()['path'] . "/" . sanitize_text_field($_POST['group_id']) . '_sign.pdf';
            $dest_url = wp_get_upload_dir()['url'] . "/" . sanitize_text_field($_POST['group_id']) . '_sign.pdf';
            copy($tmpfile, $destfile);
            unlink($tmpfile);
            $package = array(
                'url' => $dest_url,
                'img' => '<span></span>',
            );
            echo json_encode($package);
        }
        wp_die();
    }

    public function view_package_log()
    {
        $settings = $this->csomagpont_settings;
        $package_log_url = "https://api.deliveo.eu/package_log/" . sanitize_text_field($_POST["group_id"]) . "?licence=" . CSP_LICENCE . "&api_key=" . $settings["api_key"];

        $package_log = json_decode(wp_remote_fopen($package_log_url), true)['data'];
        if (isset($package_log[0])) {
            $row = '<h4>Csomagnapló</h4>';
        } else {
            $row = '<h4 style="background-color:red">A csomagnapló nem található</h4>';

        }

        foreach ($package_log as $entry) {

            $status =
            $row .= '<div class="row">';
            $row .= '<div class="timestamp">' . date('Y-m-d H:i', $entry['timestamp']) . '</div>';
            $row .= '<div class="status">' . $this->displayStatus($entry['status'], $entry['status_text']) . '</div>';

            $row .= '</div>';
            // unset($row);
            $row .= '</div>';
        }
        echo $row;

        wp_die();
    }

    public function displayStatus($status, $status_text)
    {
        switch ($status) {
            case 'rogzitve':
                return "<strong>Rögzítve</strong>";
                break;

            case 'feladva':
                return "<strong>Feladva</strong>";
                break;

            case 'kezbesitesi_kiserlet':
                return '<strong>' . $status_text . '</strong>';
                break;

            case 'sikeres':
                return "<strong>Sikeres kézbesítés</strong> (" . $status_text . ")";
                break;

            case 'rendszamhoz_rendel':
                return "<strong>Rendszámhoz rendelt</strong> (" . $status_text . ")";
                break;

            case 'futar_felvette':
                return "<strong>Futár felvette</strong> (" . $status_text . ")";
                break;

            default:
                return $status;
                break;
        }
    }

    public function package_details()
    {
        $settings = $this->csomagpont_settings;
        $package_log_url = "https://api.deliveo.eu/package/" . sanitize_text_field($_POST["group_id"]) . "?licence=" . CSP_LICENCE . "&api_key=da07b2262a4a4083964097691a95fc0da70a3643a2a2e4f4f0";

        $package_details = json_decode(wp_remote_fopen($package_log_url), true)['data'];
        if ($package_details[0]['dropped_off'] != null) {
            $delivered = 'Átvette (' . $package_details[0]['dropped_off'] . ')';
        } else {
            $delivered = 'A csomag átvétele még nem történt meg!';
        }
        if (isset($package_details[0])) {

            echo '<div class="content">' . $delivered . '<br></div>';
            echo '<div class="group_info" style="background-image:url(' . CSOMAGPONT_DIR_URL . 'images/csomagpont-icon.png' . ')">';

            echo '<h4>Címzett</h4>';
            echo '<div class="content">[' . $package_details[0]['consignee_zip'] . '] ' . $package_details[0]['consignee_city'] . '</div>';
            echo '<div class="content">' . $package_details[0]['consignee_address'] . ' ' . $package_details[0]['consignee_apartment'] . '</div>';
            echo '<div class="content">' . $package_details[0]['consignee_phone'] . ' | <a href="mailto:' . $package_details[0]['consignee_email'] . '">' . $package_details[0]['consignee_email'] . '</a></div>';

            echo '</div>';
            echo '</div>';
        } else {
            echo '<div class="group_info" style="background-image:url(' . CSOMAGPONT_DIR_URL . 'images/csomagpont-icon.png' . ')">';
            echo '<h4>A csomag nem található a Csomagpont rendszerben!</h4>';
            echo '</div>';

        }
        die;

        wp_die();
    }

}



// TODO máshova tenni

function csp_download_url_post( $url, $post_body, $timeout = 300, $signature_verification = false ) {
    //WARNING: The file is not automatically deleted, The script must unlink() the file.
    if ( ! $url ) {
        return new WP_Error( 'http_no_url', __( 'Invalid URL Provided.' ) );
    }
 
    $url_filename = basename( parse_url( $url, PHP_URL_PATH ) );
 
    $tmpfname = wp_tempnam( $url_filename );
    if ( ! $tmpfname ) {
        return new WP_Error( 'http_no_file', __( 'Could not create Temporary file.' ) );
    }
 
    $response = wp_safe_remote_post(
        $url,
        array(
            'body' => $post_body,
            'timeout'  => $timeout,
            'stream'   => true,
            'filename' => $tmpfname,
        )
    );
 
    if ( is_wp_error( $response ) ) {
        unlink( $tmpfname );
        return $response;
    }
 
    $response_code = wp_remote_retrieve_response_code( $response );
 
    if ( 200 != $response_code ) {
        $data = array(
            'code' => $response_code,
        );
 
        // Retrieve a sample of the response body for debugging purposes.
        $tmpf = fopen( $tmpfname, 'rb' );
        if ( $tmpf ) {
            /**
             * Filters the maximum error response body size in `download_url()`.
             *
             * @since 5.1.0
             *
             * @see download_url()
             *
             * @param int $size The maximum error response body size. Default 1 KB.
             */
            $response_size = apply_filters( 'download_url_error_max_body_size', KB_IN_BYTES );
            $data['body']  = fread( $tmpf, $response_size );
            fclose( $tmpf );
        }
 
        unlink( $tmpfname );
        return new WP_Error( 'http_404', trim( wp_remote_retrieve_response_message( $response ) ), $data );
    }
 
    $content_md5 = wp_remote_retrieve_header( $response, 'content-md5' );
    if ( $content_md5 ) {
        $md5_check = verify_file_md5( $tmpfname, $content_md5 );
        if ( is_wp_error( $md5_check ) ) {
            unlink( $tmpfname );
            return $md5_check;
        }
    }
 
    // If the caller expects signature verification to occur, check to see if this URL supports it.
    if ( $signature_verification ) {
        /**
         * Filters the list of hosts which should have Signature Verification attempteds on.
         *
         * @since 5.2.0
         *
         * @param array List of hostnames.
         */
        $signed_hostnames       = apply_filters( 'wp_signature_hosts', array( 'wordpress.org', 'downloads.wordpress.org', 's.w.org' ) );
        $signature_verification = in_array( parse_url( $url, PHP_URL_HOST ), $signed_hostnames, true );
    }
 
    // Perform signature valiation if supported.
    if ( $signature_verification ) {
        $signature = wp_remote_retrieve_header( $response, 'x-content-signature' );
        if ( ! $signature ) {
            // Retrieve signatures from a file if the header wasn't included.
            // WordPress.org stores signatures at $package_url.sig
 
            $signature_url = false;
            $url_path      = parse_url( $url, PHP_URL_PATH );
            if ( substr( $url_path, -4 ) == '.zip' || substr( $url_path, -7 ) == '.tar.gz' ) {
                $signature_url = str_replace( $url_path, $url_path . '.sig', $url );
            }
 
            /**
             * Filter the URL where the signature for a file is located.
             *
             * @since 5.2.0
             *
             * @param false|string $signature_url The URL where signatures can be found for a file, or false if none are known.
             * @param string $url                 The URL being verified.
             */
            $signature_url = apply_filters( 'wp_signature_url', $signature_url, $url );
 
            if ( $signature_url ) {
                $signature_request = wp_safe_remote_get(
                    $signature_url,
                    array(
                        'limit_response_size' => 10 * 1024, // 10KB should be large enough for quite a few signatures.
                    )
                );
 
                if ( ! is_wp_error( $signature_request ) && 200 === wp_remote_retrieve_response_code( $signature_request ) ) {
                    $signature = explode( "\n", wp_remote_retrieve_body( $signature_request ) );
                }
            }
        }
 
        // Perform the checks.
        $signature_verification = verify_file_signature( $tmpfname, $signature, basename( parse_url( $url, PHP_URL_PATH ) ) );
    }
 
    if ( is_wp_error( $signature_verification ) ) {
        if (
            /**
             * Filters whether Signature Verification failures should be allowed to soft fail.
             *
             * WARNING: This may be removed from a future release.
             *
             * @since 5.2.0
             *
             * @param bool   $signature_softfail If a softfail is allowed.
             * @param string $url                The url being accessed.
             */
            apply_filters( 'wp_signature_softfail', true, $url )
        ) {
            $signature_verification->add_data( $tmpfname, 'softfail-filename' );
        } else {
            // Hard-fail.
            unlink( $tmpfname );
        }
 
        return $signature_verification;
    }
 
    return $tmpfname;
}
