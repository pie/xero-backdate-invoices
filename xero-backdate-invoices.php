<?php
/*
Plugin Name: Xero: Backdate Invoices
Description: Add script and cron to generate Xero invoice for all orders that do not have one
Version: 0.1
Author: The team at PIE
Author URI: http://pie.co.de
License:     GPL3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/

/* PIE\XeroBackdateInvoices is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 2 of the License, or any later version.

PIE\XeroBackdateInvoices is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with PIE\XeroBackdateInvoices. If not, see https://www.gnu.org/licenses/gpl-3.0.en.html */

namespace PIE\XeroBackdateInvoices;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

register_activation_hook( __FILE__, __NAMESPACE__ . '\activate_xero_update' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\deactivate_xero_update' );

/**
* Load in JS for admin screen
*/
function enqueue_admin_scripts() {
	global $current_screen;
	if ( 'woocommerce_page_woocommerce_xero' === $current_screen->id ) {
		wp_enqueue_script( 'xero-backdate-invoices', plugins_url( '/js/xero-backdate-invoices.js', __FILE__ ), array( 'jquery' ), '0.1', true );
		wp_localize_script( 'xero-backdate-invoices', 'xero_backdate_invoices', array(
			'import_button_text' => __( 'Update Xero Invoices', 'xero-backdate-invoices' ),
			'importing_text'     => __( 'Updating...', 'xero-backdate-invoices' ),
		) );
	}
}
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_admin_scripts' );

/**
* Hook in AJAX requests
*/
function hook_up_ajax() {
	add_action( 'wp_ajax_xero_backdate_invoices_get_orders', __NAMESPACE__ . '\get_orders' );
	add_action( 'wp_ajax_xero_backdate_invoices_send_invoices', __NAMESPACE__ . '\update_orders' );
}
add_action( 'init', __NAMESPACE__ . '\hook_up_ajax' );

/**
* Add scheduled event on plugin activation
*/
function activate_xero_update() {
	if ( ! wp_next_scheduled( 'xero_backdate_invoices' ) ) {
		wp_schedule_event( time(), 'every_minute', 'xero_backdate_invoices' );
	}
}

/**
* Remove scheduled event on plugin deactivation
*/
function deactivate_xero_update() {
	wp_clear_scheduled_hook( 'xero_backdate_invoices' );
}

/**
* Get all completed orders without Xero invoices
*/
function get_orders() {
	$page = get_option( 'xero_backdate_invoices_page' ) ? get_option( 'xero_backdate_invoices_page' ) : 1;
	$args  = array(
		'post_type'      => 'shop_order',
		'post_status'    => 'wc-completed',
		'posts_per_page' => 2,
		'paged'           => $page,
		'fields'         => 'ids',
		'meta_query'     => array(
			'relation' => 'AND',
			array(
				'relation' => 'OR',
				array(
					'key'   => '_created_via',
					'value' => 'subscription',
				),
				array(
					'key'   => '_created_via',
					'value' => 'checkout',
				),
			),
			array(
				'key'     => '_xero_invoice_id',
				'compare' => 'NOT EXISTS',
				'value'   => '',
			),
		),
	);
	$query = new \WP_Query( $args );
	update_orders( $query->posts );
	$page++;
	update_option( 'xero_backdate_invoices_page', $page );
	wp_send_json_success( $query->posts );
}
add_action( 'xero_backdate_invoices', __NAMESPACE__ . '\get_orders' );

/**
* Send invoices for the given order IDs
*/
function update_orders( $orders ) {
	$settings        = new \WC_XR_Settings();
	$invoicer        = new \WC_XR_Invoice_Manager( $settings );
	$payment_manager = new \WC_XR_Payment_Manager( $settings );
	foreach ( $orders as $order_id ) {
		$invoicer->send_invoice( $order_id );
		$payment_manager->send_payment( $order_id );
	}
	wp_send_json_success( $_POST['orders'] );
}
