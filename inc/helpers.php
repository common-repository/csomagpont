<?php

/* Csomagpont helpers functions with unique prefixes: csp_ */

/* If the $key not exists return an empty string */
function csp_get_value( $key ) {
	return $key = isset( $key ) ? $key : '';
}

function csp_is_option_checked( $value ) {
	$checked = '';
	
	if ( $value == '1' ) {
		$checked = 'checked="checked"';
	}
	
	return $checked;
}

function csp_is_radio_checked( $value, $expected_value ) {
	$checked = '';
	
	if ( $value == $expected_value || ( empty( $value ) && $expected_value == 'felado' ) ) {
		$checked = 'checked="checked"';
	}
	
	return $checked;
}

function csp_is_selector_selected( $value, $expected_value ) {
	$selected = '';
	
	if ( $value == $expected_value ) {
		$selected = 'selected="selected"';
	}
	
	return $selected;
}

function csp_post_meta( $post_id, $meta_key, $default_value = '' ) {
	$meta_value = get_metadata( 'post', $post_id, $meta_key, true );

	if ( empty( $meta_value ) ) {
		$meta_value = $default_value;
	}

	return $meta_value;
}


function trim_and_lower ($string) {
	if(!isset($string)) {
		return;
	}
	return strtolower(trim($string));
}

function compare_zip_and_country ($sender_country_code, $sender_zip, $consignee_country, $consignee_zip) {
	if (!isset($sender_country_code) && !isset($sender_zip) && !isset($consignee_country) && !isset($consignee_zip)){
		return false;
	}
	if (($sender_country_code == 'hu' && $consignee_country == 'hu') && (substr($sender_zip, 0, 1) == '1' && substr($consignee_zip, 0, 1) == '1')) {
		return true;
	} else {
		return false;
	}
}
