<?php

class Csomagpont_API
{

    public function __construct($api_settings_obj)
    {
        $this->api_settings_obj = $api_settings_obj;
        $this->api_settings = $this->api_settings_obj->get_csomagpont_settings();

        $this->licence = CSP_LICENCE;
        $this->api_key = $this->api_settings['api_key'];
        $this->api_url = 'https://api.deliveo.eu/[TYPE]?licence=[LICENCE]&api_key=[API_KEY]';

        $this->api_package_post_url = $this->set_api_url('package/create');
        $this->api_shipping_options_url = 'http://szallitasimod.csomagpont.com/';
        $this->result_message = '';
        $this->admin_orders_url = get_bloginfo('url') . '/wp-admin/edit.php?post_type=shop_order';

        add_action('admin_init', array($this, 'session_start'));
    }

    public function send_order_items($order_id, $order, $export_allowed)
    {
        // var_dump($order);
        // die();
        $response = wp_remote_post($this->api_package_post_url, array(
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(),
            'body' => $order,
            'cookies' => array(),
        )
        );
        $resp = json_decode($response['body']);
        $str = $resp->msg;
        $pattern = "/phone/";
        $result = preg_match($pattern, $str); // Outputs 1

        // var_dump($resp);
        // die();

        if (isset($resp->data[0])) {
            update_post_meta($order_id, '_csomagpont_exported', 'true');
            update_post_meta($order_id, '_group_code', $resp->data[0]);
            update_post_meta($order_id, '_delivery', $order['delivery']);

            $rsp = $order_id . ' csoportkódja: ' . $resp->data[0] . "<br>";
            $_SESSION['csomagpont_message'][] = array(
                'type' => 'ok',
                'message' => $rsp
            );
            $this->api_settings_obj->set_delivered_order_status($order_id);

        } else if ($result == '1'){
            
            $rsp = '#'. $order_id . ' Beküldés sikertelen! A telefonszám formátuma nem megfelelő <strong style="color:red;font-size:20px;"> Kérlek használd a helyes formátumot: +36201234567</strong><br>';
            $_SESSION['csomagpont_message'][] = array(
                'type' => 'error',
                'message' => $rsp
            ); 
        } else {
            $rsp = '#'. $order_id . ' azonosítójú rendelést nem sikerült feladni! <strong>' . ' ' . $resp->msg . $result . '</strong><br>';
            $_SESSION['csomagpont_message'][] = array(
                'type' => 'error',
                'message' => $rsp
            ); 
        }
        
        return $rsp;
    }

    /** Get Shipping options by csomagpont API */
    public function get_shipping_options()
    {
        $shipping_options = false;

        $result = json_decode(wp_remote_fopen($this->api_shipping_options_url));

        if (isset($result->data) && $result->type == 'success') {
            $shipping_options = $result->data;
        }

        return $shipping_options;
    }

    private function set_api_url($type)
    {
        $api_url = $this->api_url;

        return str_replace(array('[TYPE]', '[LICENCE]', '[API_KEY]'), array($type, $this->licence, $this->api_key), $api_url);
    }

    private function set_exported_metas($order_details)
    {

        if (isset($order_details['group_code'])) {
            update_post_meta($order_id, '_csomagpont_exported', 'true');
            update_post_meta($order_id, '_group_code', $order_details['group_code']);
        }

        $this->api_settings_obj->set_delivered_order_status($order_id);
    }

    public function session_start()
    {
        session_start();
    }

}
