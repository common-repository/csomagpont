<?php

class Csomagpont_Filter
{

    public function __construct()
    {
        // $this->csomagpont_settings_obj = new Csomagpont_Settings();
        add_action('restrict_manage_posts', array($this, 'not_exported_products_filter'));
        add_action('pre_get_posts', array($this, 'apply_not_exported_products_filter'));
        add_filter('manage_edit-shop_order_columns', array($this, 'add_order_group_code_column_header'), 20);
        add_action('manage_shop_order_posts_custom_column', array($this, 'add_order_group_code_column_content'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'add_order_packaging_column_content'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'add_order_item_weight_column_content'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'add_order_package_weight_column_content'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'add_order_package_material_weight_column_content'));
        add_filter('post_class', function ($classes) {
            $classes[] = 'no-link';
            return $classes;

        });
    }

    public function add_order_group_code_column_header($columns)
    {

        $new_columns = array();
        $index = 0;

        foreach ($columns as $column_name => $column_info) {
            $new_columns[$column_name] = $column_info;

            if ($index == 1) {
                $new_columns['group_code'] = 'Csomag azonosító';
                $new_columns['packaging_unit'] = __('Csomagolási egy.', 'csomagpont');
                $new_columns['item_weight'] = __('Termékek s. (g)', 'csomagpont');
                $new_columns['package_weight'] = __('Teljes csomag s. (g)', 'csomagpont');
                $new_columns['package_material_weight'] = __('Csomagoló anyag (g)', 'csomagpont');
            }

            $index += 1;
        }

        return $new_columns;
    }

    public function add_order_group_code_column_content($column)
    {
        global $post;

        if ($column == 'group_code') {
            $exported = get_metadata('post', $post->ID, '_csomagpont_exported', true);
            $group_code = get_metadata('post', $post->ID, '_group_code', true);
            $delivery = get_metadata('post', $post->ID, '_delivery', true);

            // Csomagpont szűrés Szathmári pluginnal
            $pont_metadata = get_metadata('post', $post->ID, 'wc_selected_pont', true);
            
            // var_dump($group_code, $delivery);
            if ($exported == 'true') {
                echo '<span class="csomagpont-cell" style="cursor:pointer;font-weight:600;color:#0073aa;" data-groupid="' . $group_code . '" data-dsmid="' . $delivery . '"> '.$group_code .'</span>';
                if ($delivery == CSP_SZALLMOD_MPL_PONT || $delivery == CSP_SZALLMOD_MPL_ALTER) {
                    echo '<img class="csp-mpl-sent" title="MPL feladójegyzék tartozhat ehhez a csomaghoz" src="' . CSOMAGPONT_DIR_URL . 'images/csomagpont-signature.png">';
                }
            } else if ($pont_metadata) {
                // Csomagpont beállítás Szathmári pluginnal
                $pont_type = explode('|', get_metadata('post', $post->ID, 'wc_selected_pont', true))[1];
                if ($pont_type === 'GLS CsomagPont') {
                    echo '<select name="csomagpont-'.$post->ID.'" disabled>';
                    echo '<option value="' . $post->ID . "-". CSP_SZALLMOD_GLS_PONT . '" selected>' . 'GLS csomagpont' . '</option>';
                    echo '</select>';
                } else if ($pont_type === 'PostaPont Postán maradó' || $pont_type === 'PostaPont (MOL, COOP, MediaMarkt stb.)' || $pont_type === 'PostaPont Csomagautomata') {
                    echo '<select name="csomagpont-'.$post->ID.'" disabled>';
                    echo '<option value="' . $post->ID . "-". CSP_SZALLMOD_MPL_PONT . '" selected>' . 'MPL csomagpont' . '</option>';
                    echo '</select>';
                } else if ($pont_type === 'Csomagküldő Magyarország' || $pont_type === 'Packeta Magyarország') {
                    echo '<select name="csomagpont-'.$post->ID.'" disabled>';
                    echo '<option value="' . $post->ID . "-". CSP_SZALLMOD_PACKETA . '" selected>' . 'Packeta Átvételi Pont' . '</option>';
                    echo '</select>';
                } else {
                    echo 'Nem támogatott csomagátvételi pont';
                }
            } else {
							$csomagpont_api = new Csomagpont_API(new Csomagpont_Settings());
							$shipping_options = $csomagpont_api->get_shipping_options();
                            $checkbox_value = $csomagpont_api->api_settings['checkbox_agreement']; 

                            
                            $sender_country_code = trim_and_lower($csomagpont_api->api_settings['sender_country_code']); //hu
                            $sender_zip = trim_and_lower($csomagpont_api->api_settings['sender_zip']);
                            $consignee_country = trim_and_lower(get_metadata('post', $post->ID, '_shipping_country', true)); //hu
                            $consignee_zip = trim_and_lower(get_metadata('post', $post->ID, '_shipping_postcode', true));
      
                $selected = '';
                echo '<select name="csomagpont-'.$post->ID.'" title="Válasszon szállítási módot">';
                foreach ($shipping_options as $option) {
                    if ($csomagpont_api->api_settings_obj->csomagpont_settings["delivery"] == $option->value) {
                        $selected = 'selected';
                    } else {
						$selected = '';
                    };
                    if($option->alias){
                        if( $option->value == '70' && compare_zip_and_country($sender_country_code, $sender_zip, $consignee_country, $consignee_zip) && $checkbox_value == 'checked'){
                            echo '<option value="' . $post->ID . "-". $option->value . '" ' . 'selected' . '>' . $option->alias . '</option>';    
                        } else {
                            echo '<option value="' . $post->ID . "-". $option->value . '" ' . $selected . '>' . $option->alias . '</option>';
                        }
                    } else {
                        echo '<option value="' . $post->ID . "-". $option->value . '" ' . $selected . '>' . $option->description . '</option>';
                    }
                }
                echo '</select>';
            }
        }
    }

    public function add_order_packaging_column_content($column)
    {
        global $post;

        $order = new WC_Order($post->ID);

        if ($column == 'packaging_unit') {
            $exported = get_metadata('post', $post->ID, '_csomagpont_exported', true);

            $packaging_unit = json_decode(get_option('csomagpont_settings'))->packaging_unit;
            if ($exported != 'true') {
                switch ((int)$packaging_unit) {
                    case 0:
                        echo "<input maxlength='2' style='max-width:60px; margin-left:40px' name='order[" . $post->ID . "][unit]' type='number' value='1'>";
                        break;
                    case 1:
                        echo "<input maxlength='2' style='max-width:60px; margin-left:40px' name='order[" . $post->ID . "][unit]' type='number' value='" . $order->get_item_count() . "'>";
                        
                        break;
                    default:
                        echo "<input maxlength='2' style='max-width:60px; margin-left:40px' type='number' value='1'>";
                        break;
                }
            }
        }
    }

    public function add_order_item_weight_column_content($column)
    {
        global $post;

        $order = new WC_Order($post->ID);

        $currentOrder = wc_get_order($order);

        $weightAll = 0;

        $multiplier = get_option('woocommerce_weight_unit') == 'g' ? 1 : 1000;

        foreach ($currentOrder->get_items() as $item_id => $item_data) {
          $product = $item_data->get_product();
          if (!$product || $product->is_virtual()) {
            continue;
          }
            $sku = $product->get_sku();
            
            for ($i = 0; $i < $item_data->get_quantity(); $i++) {
                $weight = is_nan(floatval($product->get_weight())) ? 0 : floatval($product->get_weight());
                $weightAll = $weightAll + $weight;
            }
            if ($column == 'item_weight') {
                $exported = get_metadata('post', $post->ID, '_csomagpont_exported', true);
    
                $item_weight = isset(json_decode(get_option('csomagpont_settings'))->weight) ? json_decode(get_option('csomagpont_settings'))->weight : 0;
            }
        }
        $weightAll *= $multiplier;

        if ($column == 'item_weight') {
            $exported = get_metadata('post', $post->ID, '_csomagpont_exported', true);

            $csomagpont_settings = json_decode(get_option('csomagpont_settings'));
            $item_weight = isset($csomagpont_settings->item_weight) ? $csomagpont_settings->item_weight : 0;

            if ($exported != 'true') {
                switch ((int)$item_weight) {
                    case 0:
                        echo "<input name='order[" . $post->ID . "][itemWeight]' type='number' value='". $weightAll ."' disabled>";
                        break;
                    default:
                        echo "<input type='number' value='1'>";
                        break;
                }
            }
        }
    }

    public function add_order_package_weight_column_content($column)
    {
        global $post;

        $order = new WC_Order($post->ID);

        $currentOrder = wc_get_order($order);

        $multiplier = get_option('woocommerce_weight_unit') == 'g' ? 1 : 1000;

        
        $csomagpont_settings = json_decode(get_option('csomagpont_settings'));
        
       
        $package_weight = (isset($csomagpont_settings->packaging_weight) && $csomagpont_settings->packaging_weight != "" && $csomagpont_settings->packaging_weight != 0) ? $csomagpont_settings->packaging_weight : 100;

        $itemWeights = array();

        $exported = get_metadata('post', $post->ID, '_csomagpont_exported', true);

        foreach ($currentOrder->get_items() as $item_id => $item_data) {
          $product = $item_data->get_product();
          if (!$product || $product->is_virtual()) {
            continue;
          }
            $sku = $product->get_sku();
            for ($i = 0; $i < $item_data->get_quantity(); $i++) {
                $weight = is_nan(floatval($product->get_weight())) ? 0 : floatval($product->get_weight()) * $multiplier;
                array_push($itemWeights,$weight);
            }
        }
        if ($column == 'package_weight') {
            $exported = get_metadata('post', $post->ID, '_csomagpont_exported', true);
            $itemsAndPackageSum = array_sum($itemWeights) + $package_weight;
            
            // var_dump($itemsAndPackageSum);

            if ($exported != 'true') {
                switch ((int)$package_weight) {
                    default:
                        echo "<input disabled style='max-width:100px; name='order[" . $post->ID . "][weight]' type='number' value='". $itemsAndPackageSum ."'>";
                        break;
                }
            }
        }
    }

    public function add_order_package_material_weight_column_content($column)
    {
        global $post;

        $order = new WC_Order($post->ID);

        $currentOrder = wc_get_order($order);

        $multiplier = get_option('woocommerce_weight_unit') == 'g' ? 1 : 1000;

        
        $csomagpont_settings = json_decode(get_option('csomagpont_settings'));
        
       
        $package_weight = (isset($csomagpont_settings->packaging_weight) && $csomagpont_settings->packaging_weight != "" && $csomagpont_settings->packaging_weight != 0) ? $csomagpont_settings->packaging_weight : 100;

        $itemWeights = array();

        $exported = get_metadata('post', $post->ID, '_csomagpont_exported', true);

        foreach ($currentOrder->get_items() as $item_id => $item_data) {
          $product = $item_data->get_product();
          if (!$product || $product->is_virtual()) {
            continue;
          }
            $sku = $product->get_sku();
            for ($i = 0; $i < $item_data->get_quantity(); $i++) {
                $weight = is_nan(floatval($product->get_weight())) ? 0 : floatval($product->get_weight()) * $multiplier;
                array_push($itemWeights,$weight);
            }
        }
        if ($column == 'package_material_weight') {
            $exported = get_metadata('post', $post->ID, '_csomagpont_exported', true);
            $itemsAndPackageSum = array_sum($itemWeights) + $package_weight;
            
            // var_dump($itemsAndPackageSum);

            if ($exported != 'true') {
                switch ((int)$package_weight) {
                    default:
                        echo "<input style='max-width:100px; name='order[" . $post->ID . "][weight]' type='number' value='". $package_weight ."'>";
                        break;
                }
            }

        }
    }

    /* Order filter functions */
    public function not_exported_products_filter($post_type)
    {
        if (isset($_GET['post_type'])) {
            $post_type = sanitize_text_field($_GET['post_type']);
        }

        $selected_1 = '';
        $selected_2 = '';

        if (isset($_GET['deliveo_exported'])) {
            switch (sanitize_text_field($_GET['deliveo_exported'])) {
                case 'not_exported':$selected_1 = 'selected';
                    break;
                case 'exported':$selected_2 = 'selected';
                    break;
            }
        }

        if ($post_type == 'shop_order') {
            echo '
			<select name="deliveo_exported">
				<option value="">' . __('Csomagpont szűrés: mind', 'csomagpont') . '</option>
				<option value="not_exported" ' . $selected_1 . '>' . __('Feladatlan', 'csomagpont') . '</option>
				<option value="exported" ' . $selected_2 . '>' . __('Feladott', 'csomagpont') . '</option>
			</select>';
        }

        // Plusz funkció: Töltőképernyő
        echo '<div class="csp-loader"><img src="' . plugin_dir_url(__DIR__) . 'images/loader.gif"></div>';

    }

    /* In the Orders admin page when the csomagpont filter was applied, this query filter will working  */
    public function apply_not_exported_products_filter($query)
    {
        global $pagenow;

        $meta_key_query = array();

        if ($query->is_admin && $pagenow == 'edit.php' && isset($_GET['deliveo_exported']) && sanitize_text_field($_GET['deliveo_exported']) != '' && sanitize_text_field($_GET['post_type']) == 'shop_order') {
            switch (sanitize_text_field($_GET['deliveo_exported'])) {
                case 'not_exported':
                    $query_filters = array(
                        'key' => '_csomagpont_exported',
                        'compare' => 'NOT EXISTS',
                    );
                    break;

                case 'exported':
                    $query_filters = array(
                        'key' => '_csomagpont_exported',
                        'value' => 'true',
                    );
                    break;
            }

            $meta_key_query = array($query_filters);

            if (count($meta_key_query > 0)) {
                $query->set('meta_query', $meta_key_query);
            }
        }
    }

}
