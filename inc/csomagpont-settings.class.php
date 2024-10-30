<?php

class Csomagpont_Settings
{

    public function __construct()
    {
        $this->csomagpont_settings = $this->get_csomagpont_settings();
        add_action('admin_menu', array($this, 'settings_page'), 99);


        add_action('admin_notices', array($this, 'missing_csomagpont_settings_message'));
        add_action('wp_ajax_save_api_key', array($this, 'save_api_key'));

    }

    private function init_csomagpont_settings()
    {
        $init_settings = array(
            'api_key' => '',
            'licence_key' => CSP_LICENCE,
            'sender' => '',
            'sender_country_code' => '',
            'sender_zip' => '',
            'sender_city' => '',
            'sender_address' => '',
            'sender_apartment' => '',
            'sender_phone' => '',
            'sender_email' => '',
            'x' => '',
            'y' => '',
            'z' => '',
            'priority' => '0',
            'saturday' => '0',
            'insurance' => '0',
            'freight' => 'felado',
            'delivery' => '',
            'csomagpont_settings' => '',
            'reference_id_is_order_id' => '',
            'delivered_status' => '',
            'packaging_unit' => '',
            'checkbox_agreement' => 'checked',
        );

        $init_settings = json_encode($init_settings);
        update_option('csomagpont_settings', $init_settings);
        $this->csomagpont_settings = $init_settings;

        return $init_settings;
    }

    /* Add Csomagpont settings page Woocommerce submenu */
    public function settings_page()
    {
        add_submenu_page('woocommerce', 'Csomagpont', 'Csomagpont', 'manage_options', 'csomagpont-settings', array($this, 'settings_page_content'));
    }

    /* Settings page content (Save settings form) */
    public function settings_page_content()
    {
        $this->save_csomagpont_settings();

        $settings = $this->get_csomagpont_settings();
        $shipping_options_selector = $this->shipping_options_selector($settings);
        $order_status_selector = $this->order_status_selector($settings);

        /* Declare variables because of undefined index errors, or create a parser function */
        if ((strlen($shipping_options_selector) > 300)) {

            if (!isset($settings['checkbox_agreement'])) {
                $settings['checkbox_agreement'] = 'checked';
            }
            $packagesettings = '<tr>
			<td colspan="2"><h2>' . __('Feladó beállítása | <small>Az itt megadott paraméterek kerülnek a csomag adataiba mint "Feladó".', 'csomagpont') . '</small></h2></td>
		</tr>
		<tr>
			<td>' . __('Feladó neve', 'csomagpont') . '</td>
			<td><input type="text" name="sender" value="' . $settings['sender'] . '" class="required" data-message="' . __('Feladó név kötelező', 'csomagpont') . '" /></td>
		</tr>
		<tr>
			<td>' . __('Feladó országának kétjegyű kódja. Pl: "HU", "DE"', 'csomagpont') . '</td>
			<td><input type="text" name="sender_country_code" value="' . $settings['sender_country_code'] . '" class="required" data-message="' . __('Ország kód kötelező', 'csomagpont') . '" /></td>
		</tr>
		<tr>
			<td>' . __('Feladó településének az irányítószáma', 'csomagpont') . '</td>
			<td><input type="text" name="sender_zip" value="' . $settings['sender_zip'] . '" class="required" data-message="' . __('Feladó irányítószám kötelező', 'csomagpont') . '" /></td>
		</tr>
		<tr>
			<td>' . __('Feladó település neve', 'csomagpont') . '</td>
			<td><input type="text" name="sender_city" value="' . $settings['sender_city'] . '" class="required" data-message="' . __('Település neve kötelező', 'csomagpont') . '" /></td>
		</tr>
		<tr>
			<td>' . __('Feladó közterület neve, házszám', 'csomagpont') . '</td>
			<td><input type="text" name="sender_address" value="' . $settings['sender_address'] . '" class="required" data-message="' . __('Feladó közterület név kötelező', 'csomagpont') . '" /></td>
		</tr>
		<tr>
			<td>' . __('Feladó épület, lépcsőház, emelet, ajtó', 'csomagpont') . '</td>
			<td><input type="text" name="sender_apartment" value="' . $settings['sender_apartment'] . '" /></td>
		</tr>
		<tr>
			<td>' . __('Feladó telefonszám', 'csomagpont') . '</td>
			<td><input type="text" name="sender_phone" value="' . $settings['sender_phone'] . '" class="required" data-message="' . __('Feladó telefonszám kötelező', 'csomagpont') . '" /></td>
		</tr>
		<tr>
			<td>' . __('Feladó email', 'csomagpont') . '</td>
			<td><input type="text" name="sender_email" value="' . $settings['sender_email'] . '" class="required" data-message="' . __('Feladó email kötelező', 'csomagpont') . '" /></td>
		</tr>
		<tr>
			<td colspan="2"><h2>' . __('Alapértelmezett csomag méretek | <small>Ha hiányoznak a termék méretei, akkor ezeket használjuk feladáskor.', 'csomagpont') . '</small></h2></td>
		</tr>
		<tr>
			<td>' . __('X = szélesség, Y = magasság, Z = mélység (cm)', 'csomagpont') . '</td>
			<td>X: <input type="number" name="x" value="' . $settings['x'] . '" class="required" data-message="' . __('Alapértelmezett csomag magasság kötelező', 'csomagpont') . '" /> Y: <input type="number" name="y" value="' . $settings['y'] . '" class="required" data-message="' . __('Alapértelmezett csomag szélesség kötelező', 'csomagpont') . '" /> Z: <input type="number" name="z" value="' . $settings['z'] . '" class="required" data-message="' . __('Alapértelmezett csomag mélység kötelező', 'csomagpont') . '" /></td>
		</tr>
		<tr>
			<td colspan="2"><h2>Szállítási paraméterek | <small>Ezekkel a paraméterekkel lesz feladva minden csomag.</small></h2></td>
		</tr>
		<tr>
			<td>' . __('A feladottak állapotát erre módosítsa:', 'csomagpont') . '</td>
			<td>' . $order_status_selector . '</td>
        </tr>
        
		<tr>
			<td>' . __('Alapértelmezett szállítási opció:', 'csomagpont') . '</td>
			<td>' . $shipping_options_selector . ' </td>
        </tr>
		<tr>
			<td>' . __('Budapesti szállítások 5PL futarszolgálattal:', 'csomagpont') . '</td>
			<td>' .  '<input type="checkbox" id="checkbox_agreement" name="checkbox_agreement" '.$settings['checkbox_agreement'].'>' . '</td>
        </tr>
        <tr>
            <td colspan="2"><h2>Csomagolási egység</small></h2></td>
        </tr>
        <tr>
            <td>' . __('Csomagok alapértelmezett száma', 'mav-it') . '</td>
            <td><select name="packaging_unit">
                <option ' . ($settings['packaging_unit'] == 0 ? 'selected' : '') . ' value="0">Mindig egy</option>
                <option ' . ($settings['packaging_unit'] == 1 ? 'selected' : '') . ' value="1">Tételenként egy</option>
            </select>
            </td>
        </tr>
        <tr>
        <tr>
            <td colspan="2"><h2>Csomagolás súlya</small></h2></td>
        </tr>
        <tr>
            <td>' . __('Csomagolás alapértelmezett súlya', 'csomagpont') . '</td>
            <td> <input type="number" name="packaging_weight" value="' . $settings['packaging_weight'] . '" class="required" data-message="' . __('Alapértelmezett csomag súly kötelező', 'csomagpont') . '" /> g </td>
        </tr>
		<tr>
			<td></td>
			<td><button name="csomagpont_settings" type="submit" class="button button-primary csomagpont_settings_save">' . __('Mentés', 'csomagpont') . '</button></td>
		</tr>';
        } else {
            $this->admin_notice_api_error();

            $packagesettings = '';
        }
        $content = '
        <h1>' . __('Beállítások', 'csomagpont') . '</h1>

		<form action="" method="post" id="csomagpont-settings-form" class="csomagpont-settings-form">
			<div class="validation-messages hidden"></div>
			<table>
                <tr><td style="font-weight: bold;" colspan="2">' . __('Figyelem! Ez NEM az MPL-től, GLS-től, vagy más futárszolgálattól kapott API kulcs. A bővítmény használatához a Csomagpont Logisztika Kft-vel való szerződés szükséges.', 'csomagpont') . '
                </td></tr>
				<tr>
					<td>' . __('API kulcs (a Csomagpont Logisztika Kft. adja meg)', 'csomagpont') . '</td>
					<td>
					<input type="text" id="csomagpont-api-key" name="api_key" value="' . $settings['api_key'] . '" class="required" data-message="' . __('API kulcs kötelező', 'csomagpont') . '" />
					</td>
                </tr>
                <tr>
					<td>
						<button type="button" class="csomagpont-save-button" id="csomagpont-save-api" title="' . __('API kulcs mentés') . '"></button>
					</td>
				</tr>'
            . $packagesettings .
            '</table>

        </form>';

        echo $content;
    }

    /* Get Csomagpont settings from DB and parse to array */
    public function get_csomagpont_settings()
    {

        // $settings = $this->init_csomagpont_settings();
        $settings = get_option('csomagpont_settings', '');

        if (empty($settings)) {
            $settings = $this->init_csomagpont_settings();
        }

        return json_decode($settings, true);
    }

    /* Parse Csomagpont options array to json strings and save to DB */
    public function save_csomagpont_settings()
    {
        if (isset($_POST['csomagpont_settings'])) {
            
            //if the checkbox is checked
            if (isset($_POST['checkbox_agreement'])) {
                $_POST['checkbox_agreement'] = 'checked';
            } else {
                $_POST['checkbox_agreement'] = '';
                
            }


            $settings = json_encode($_POST);
            update_option('csomagpont_settings', $settings);
        }

        if (isset($_POST['action']) && sanitize_text_field($_POST['action']) == 'save_api_key') {
            $settings = $this->get_csomagpont_settings();

            $settings['api_key'] = sanitize_text_field($_POST['api_key']);

            $this->admin_notice__success();

            $settings = json_encode($settings);
            update_option('csomagpont_settings', $settings);
        }
    }

    /** Build a selector by Shipping options details from CSOMAGPONT API */
    public function shipping_options_selector($csomagpont_settings)
    {
        $csomagpont_api = new Csomagpont_API(new Csomagpont_Settings());
        $shipping_options = $csomagpont_api->get_shipping_options();

        $api_key = csp_get_value($csomagpont_settings['api_key']);
        $saved_shipping = csp_get_value($csomagpont_settings['delivery']);
        $selector = '';

        if (empty($api_key)) {
            $selector = '<input type="hidden" name="delivery" value="" />';
            $selector .= __('A szállítási opció beállításához kérjük előbb adja meg az API kulcsot és mentse el a beállításokat', 'csomagpont');
        } else {
            $selector = '
             <select name="delivery" class="required" data-message="' . __('Szállítási opció kötelező', 'csomagpont') . '">
                 <option value="">' . __('Válasszon szállítási opciót', 'csomagpont') . '</option>';

            foreach ($shipping_options as $shipping_option) {
                $selected = csp_is_selector_selected($shipping_option->value, $saved_shipping);

                $selector .= '
                <option value="' . $shipping_option->value . '" ' . $selected . '>' . $shipping_option->description . '</option>';
            }

            $selector .= '
            </select>';
        }

        return $selector;
    }

    public function order_status_selector($csomagpont_settings)
    {
        $status_types = wc_get_order_statuses();
        $delivered_status = $csomagpont_settings['delivered_status'];

        $selector = '
        <select name="delivered_status">
            <option value="">' . __('Nem változtat', 'csomagpont') . '</option>';

        foreach ($status_types as $status_key => $status_value) {
            $selected = '';

            if ($delivered_status == $status_key) {
                $selected = 'selected="selected"';
            }

            $selector .= '
            <option value="' . $status_key . '" ' . $selected . '>' . $status_value . '</option>';
        }

        $selector .= '
        </select>';

        return $selector;
    }

    /* Check if some important details are missing from csomagpont settings: API key, Licence key,  */
    public function csomagpont_setting_missing()
    {
        $settings = $this->csomagpont_settings;
        $setting_missing = false;

        foreach ($settings as $setting_key => $setting_value) {
            if (
                // kizárunk az ellenőrzésből pár paramétert
                $setting_key != 'csomagpont_settings' &&
                $setting_key != 'sender_apartment' &&
                $setting_key != 'delivered_status' &&

                //a többi értéke nem lehet 0
                strlen($setting_value) < 0) {
                $setting_missing = true;
            }
            // teszteléshez a mezők értékeinek kiíratása
            //echo $setting_key." - ".$setting_value."<br>";
        }

        return $setting_missing;
    }

    public function missing_csomagpont_settings_message()
    {
        $setting_missing = $this->csomagpont_setting_missing();

        if ($setting_missing) {
            $class = 'notice notice-warning';
            $message = __('Hiányzó Csomagpont beállítás! Az export használatához kérjük menjen a Woocommerce -> Csomagpont oldalra a hiányzó adatok megadásához', 'csomagpont');

            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
        }
    }

    public function set_delivered_order_status($order_id)
    {
        global $wpdb;

        $settings = $this->csomagpont_settings;
        $delivered_status = $settings['delivered_status'];
        $query = 'UPDATE ' . $wpdb->prefix . 'posts SET post_status = "' . $delivered_status . '" WHERE ID = "' . $order_id . '"';

        if (!empty($delivered_status)) {
            $wpdb->query($query);
        }
    }

    public function admin_notice__success()
    {

        $class = 'notice notice-success is-dismissible';
        $message = __('Beállítások mentve, authentikációs adatok módosításakor ellenőrizzük a szállítási opciókat!', 'sample-text-domain');

        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }

    public function admin_notice_api_error()
    {

        $class = 'notice notice-warning is-dismissible';
        $message = __('Sikertelen csatlakozás, kérjük ellenőrizze az API kulcsot!', 'sample-text-domain');

        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }

    public function save_api_key()
    {
        $this->save_csomagpont_settings();
        wp_die();
    }
}
