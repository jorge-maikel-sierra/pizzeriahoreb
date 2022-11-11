<?php

namespace CreativeMail\Models;

final class CartData {

	/**
	 * Coupons array
	 *
	 * @var array<Coupon>
	 */
	public $coupons;

	/**
	 * Coupons currency
	 *
	 * @var string
	 */
	public $currency;

	/**
	 * Coupons currency symbol
	 *
	 * @var string
	 */
	public $currency_symbol;

	/**
	 * Cart items
	 *
	 * @var array<string,mixed>
	 */
	public $products;

	/**
	 * User data
	 *
	 * @var User
	 */
	public $user;

	/**
	 * Cart Shipping Total
	 *
	 * @var float
	 */
	public $shipping_total;

	/**
	 * Cart Shipping Taxes
	 *
	 * @var mixed
	 */
	public $shipping_taxes;
}
