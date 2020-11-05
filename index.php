<?php

add_action( 'rcl_payments_gateway_init', 'rcl_add_advcash_gateway' );
function rcl_add_advcash_gateway() {
	rcl_gateway_register( 'advcash', 'Rcl_Advcash_Payment' );
}

class Rcl_Advcash_Payment extends Rcl_Gateway_Core {
	function __construct() {
		parent::__construct( array(
			'request'	 => 'ac_order_id',
			'name'		 => 'ADVCash',
			'submit'	 => __( 'Оплатить через ADVCash' ),
			'image'		 => rcl_addon_url( 'icon.jpg', __FILE__ )
		) );
	}

	function get_options() {

		return array(
			array(
				'type'	 => 'text',
				'slug'	 => 'ac_name',
				'title'	 => __( 'Название магазина или компании' ),
				'notice' => __( 'Название компании указанное в настройках на сайте Advcash' )
			),
			array(
				'type'	 => 'text',
				'slug'	 => 'ac_login',
				'title'	 => __( 'Логин аккаунта (E-mail)' )
			),
			array(
				'type'	 => 'text',
				'slug'	 => 'ac_secret',
				'title'	 => __( 'Секретный ключ' )
			)
		);
	}

	function get_form( $data ) {

		//получаем данные из настроек подключения
		$acLogin	 = rcl_get_commerce_option( 'ac_login' );
		$acName		 = rcl_get_commerce_option( 'ac_name' );
		$acSekret	 = rcl_get_commerce_option( 'ac_secret' );

		$sign = hash( 'sha256', implode( ':', array(
			$acLogin,
			$acName,
			$data->pay_summ,
			$data->currency,
			$acSekret,
			$data->pay_id,
			) ) );

		return parent::construct_form( [
				'action' => "https://wallet.advcash.com/sci/",
				'fields' => array(
					'ac_account_email'	 => $acLogin,
					'ac_sci_name'		 => $acName,
					'ac_amount'			 => $data->pay_summ,
					'ac_order_id'		 => $data->pay_id,
					'ac_currency'		 => $data->currency,
					'ac_sign'			 => hash( 'sha256', implode( ':', array(
						$acLogin,
						$acName,
						$data->pay_summ,
						$data->currency,
						$acSekret,
						$data->pay_id,
					) ) ),
					'ac_user_id'		 => $data->user_id,
					'ac_pay_type'		 => $data->pay_type,
					'ac_baggage'		 => $data->baggage_data,
					'ac_comments'		 => $data->description
				)
			] );
	}

	function result( $process ) {

		//формируем хеш, согласно алгоритма платежной системы
		$sign = hash( 'sha256', implode( ':', array(
			$_REQUEST['ac_transfer'],
			$_REQUEST['ac_start_date'],
			$_REQUEST['ac_sci_name'],
			$_REQUEST['ac_src_wallet'],
			$_REQUEST['ac_dest_wallet'],
			$_REQUEST['ac_order_id'],
			$_REQUEST['ac_amount'],
			$_REQUEST['ac_merchant_currency'],
			rcl_get_commerce_option( 'ac_secret' )
			) ) );

		//сверяем полученный хеш и присланный
		if ( $sign != $_REQUEST['ac_hash'] ) {
			rcl_mail_payment_error( $sign );
			exit;
		}

		//Проверяем наличие платежа
		//и если его нет, то произвоидим платеж
		if ( ! parent::get_payment( $_REQUEST['ac_order_id'] ) ) {
			parent::insert_payment( array(
				'pay_id'		 => $_REQUEST['ac_order_id'],
				'pay_summ'		 => $_REQUEST['ac_amount'],
				'user_id'		 => $_REQUEST["ac_user_id"],
				'pay_type'		 => $_REQUEST["ac_pay_type"],
				'baggage_data'	 => $_REQUEST["ac_baggage"],
			) );
		}
	}

	function success( $process ) {

		if ( parent::get_payment( $_REQUEST['ac_order_id'] ) ) {
			wp_redirect( get_permalink( $process->page_successfully ) );
			exit;
		} else {
			wp_die( 'Платеж не найден в базе данных!' );
		}
	}

}
