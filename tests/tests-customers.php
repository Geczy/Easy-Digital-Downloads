<?php

/**
 * @group edd_customers
 */
class Tests_Customers extends WP_UnitTestCase {

	protected $_post_id = null;

	protected $_user_id = null;

	protected $_customer_id = null;

	public function setUp() {
		parent::setUp();

		$this->_post_id = $this->factory->post->create( array( 'post_title' => 'Test Download', 'post_type' => 'download', 'post_status' => 'publish' ) );

		$_variable_pricing = array(
			array(
				'name' => 'Simple',
				'amount' => 20
			),
			array(
				'name' => 'Advanced',
				'amount' => 100
			)
		);

		$_download_files = array(
			array(
				'name' => 'File 1',
				'file' => 'http://localhost/file1.jpg',
				'condition' => 0
			),
			array(
				'name' => 'File 2',
				'file' => 'http://localhost/file2.jpg',
				'condition' => 'all'
			)
		);

		$meta = array(
			'edd_price' => '0.00',
			'_variable_pricing' => 1,
			'_edd_price_options_mode' => 'on',
			'edd_variable_prices' => array_values( $_variable_pricing ), 
			'edd_download_files' => array_values( $_download_files ),
			'_edd_download_limit' => 20,
			'_edd_hide_purchase_link' => 1,
			'edd_product_notes' => 'Purchase Notes',
			'_edd_product_type' => 'default',
			'_edd_download_earnings' => 129.43,
			'_edd_download_sales' => 59,
			'_edd_download_limit_override_1' => 1
		);
		foreach( $meta as $key => $value ) {
			update_post_meta( $this->_post_id, $key, $value );
		}

		/** Generate some sales */
		$this->_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$user = get_userdata( $this->_user_id );

		$user_info = array(
			'id' => $user->ID,
			'email' => 'testadmin@domain.com',
			'first_name' => $user->first_name,
			'last_name' => $user->last_name,
			'discount' => 'none'
		);

		$download_details = array(
			array(
				'id' => $this->_post_id,
				'options' => array(
					'price_id' => 1
				)
			)
		);

		$price = '100.00';

		$total = 0;

		$prices = get_post_meta($download_details[0]['id'], 'edd_variable_prices', true);
		$item_price = $prices[1]['amount'];

		$total += $item_price;

		$cart_details = array(
			array(
				'name' => 'Test Download',
				'id' => $this->_post_id,
				'item_number' => array(
					'id' => $this->_post_id,
					'options' => array(
						'price_id' => 1
					)
				),
				'price' =>  100,
				'quantity' => 1,
				'tax' => 0
			)
		);

		$purchase_data = array(
			'price' => number_format( (float) $total, 2 ),
			'date' => date( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
			'purchase_key' => strtolower( md5( uniqid() ) ),
			'user_email' => $user_info['email'],
			'user_info' => $user_info,
			'currency' => 'USD',
			'downloads' => $download_details,
			'cart_details' => $cart_details,
			'status' => 'pending',
			'tax'    => '0.00'
		);

		$_SERVER['REMOTE_ADDR'] = '10.0.0.0';
		$_SERVER['SERVER_NAME'] = 'edd_virtual';

		$payment_id = edd_insert_payment( $purchase_data );

		edd_update_payment_status( $payment_id, 'complete' );

	}

	public function tearDown() {
		parent::tearDown();
	}

	public function test_get_customer_columns() {
		$columns = array(
			'id'             => '%d',
			'user_id'        => '%d',
			'name'           => '%s',
			'email'          => '%s',
			'payment_ids'    => '%s',
			'purchase_value' => '%s',
			'purchase_count' => '%d',
			'notes'          => '%s',
			'date_created'   => '%s',
		);

		$this->assertEquals( $columns, EDD()->customers->get_columns() );
	}

	public function test_add_customer() {

		$args = array(
			'email' => 'testaccount@domain.com'
		);

		$this->_customer_id = EDD()->customers->add( $args );

		$this->assertInternalType( 'int', $this->_customer_id );

	}

	public function test_get_by() {

		$customer = EDD()->customers->get_by( 'email', 'testadmin@domain.com' );

		$this->assertInternalType( 'object', $customer );
		$this->assertObjectHasAttribute( 'email', $customer );

	}

	public function test_get_column_by() {

		$customer_id = EDD()->customers->get_column_by( 'id', 'email', 'testadmin@domain.com' );

		$this->assertGreaterThan( 0, $customer_id );

	}

	public function test_exists() {

		$this->assertTrue( EDD()->customers->exists( 'testadmin@domain.com' ) );

	}

	public function test_attach_payment() {

		$customer = EDD()->customers->get_by( 'email', 'testadmin@domain.com' );
		EDD()->customers->attach_payment( $customer->id, 5222222 );
		
		$payment_ids = EDD()->customers->get_column_by( 'payment_ids', 'email', 'testadmin@domain.com' );
		$payment_ids = array_map( 'absint', explode( ',', $payment_ids ) );

		$this->assertTrue( in_array( 5222222, $payment_ids ) );

	}

	public function test_remove_payment() {

		$customer = EDD()->customers->get_by( 'email', 'testadmin@domain.com' );
		EDD()->customers->attach_payment( $customer->id, 5222222 );

		$payment_ids = EDD()->customers->get_column_by( 'payment_ids', 'email', 'testadmin@domain.com' );
		$payment_ids = array_map( 'absint', explode( ',', $payment_ids ) );

		$this->assertTrue( in_array( 5222222, $payment_ids ) );

		EDD()->customers->remove_payment( $customer->id, 5222222 );

		$payment_ids = EDD()->customers->get_column_by( 'payment_ids', 'email', 'testadmin@domain.com' );
		$payment_ids = array_map( 'absint', explode( ',', $payment_ids ) );

		$this->assertFalse( in_array( 5222222, $payment_ids ) );
	}

	public function test_increment_stats() {

		$customer = EDD()->customers->get_by( 'email', 'testadmin@domain.com' );
		
		EDD()->customers->increment_stats( $customer->id, '10' );

		$updated_customer = EDD()->customers->get( $customer->id );

		$this->assertEquals( $customer->purchase_value, '100' );
		$this->assertEquals( $customer->purchase_count, '1' );

		$this->assertEquals( $updated_customer->purchase_value, '110' );
		$this->assertEquals( $updated_customer->purchase_count, '2' );

		$this->assertGreaterThan( $customer->purchase_value, $updated_customer->purchase_value );
		$this->assertGreaterThan( $customer->purchase_count, $updated_customer->purchase_count );

		$this->assertEquals( edd_count_purchases_of_customer( $this->_user_id ), '2' );
		$this->assertEquals( edd_purchase_total_of_user( $this->_user_id ), '110' );

	}

	public function test_decrement_stats() {

		$customer = EDD()->customers->get_by( 'email', 'testadmin@domain.com' );
		
		EDD()->customers->decrement_stats( $customer->id, '10' );

		$updated_customer = EDD()->customers->get( $customer->id );

		$this->assertEquals( $customer->purchase_value, '100' );
		$this->assertEquals( $customer->purchase_count, '1' );

		$this->assertEquals( $updated_customer->purchase_value, '90' );
		$this->assertEquals( $updated_customer->purchase_count, '0' );

		$this->assertLessThan( $customer->purchase_value, $updated_customer->purchase_value );
		$this->assertLessThan( $customer->purchase_count, $updated_customer->purchase_count );

		$this->assertEquals( edd_count_purchases_of_customer( $this->_user_id ), '0' );
		$this->assertEquals( edd_purchase_total_of_user( $this->_user_id ), '90' );

	}

	public function test_get_customers() {

		$customers = EDD()->customers->get_customers();

		$this->assertEquals( 1, count( $customers ) );

	}

	public function test_count_customers() {

		$this->assertEquals( 1, EDD()->customers->count() );

		$args = array(
			'date' => array(
				'start' => 'January 1 ' . date( 'Y' ) + 1,
				'end'   => 'January 1 ' . date( 'Y' ) + 2,
			)
		);

		$this->assertEquals( 0, EDD()->customers->count( $args ) );

	}

	public function test_users_purchases() {

		$out = edd_get_users_purchases( $this->_user_id );

		$this->assertInternalType( 'object', $out[0] );
		$this->assertEquals( 'edd_payment', $out[0]->post_type );
		$this->assertTrue( edd_has_purchases( $this->_user_id ) );
		$this->assertEquals( 1, edd_count_purchases_of_customer( $this->_user_id ) );

	}

	public function test_users_purchased_product() {
		
		$out2 = edd_get_users_purchased_products( $this->_user_id );
		
		$this->assertInternalType( 'array', $out2 );
		$this->assertEquals( 1, count( $out2 ) );
		$this->assertInternalType( 'object', $out2[0] );
		$this->assertEquals( $out2[0]->post_type, 'download' );

	}
		
	public function test_has_user_purchased() {
		
		$this->assertTrue( edd_has_user_purchased( $this->_user_id, array( $this->_post_id ), 1 ) );
		$this->assertFalse( edd_has_user_purchased( $this->_user_id, array( 888 ), 1 ) );
		$this->assertFalse( edd_has_user_purchased( 0, $this->_post_id ) );
		$this->assertFalse( edd_has_user_purchased( 0, 888 ) );

	}

	public function test_get_purchase_stats_by_user() {

		$purchase_stats = edd_get_purchase_stats_by_user( $this->_user_id );
		
		$this->assertInternalType( 'array', $purchase_stats );
		$this->assertEquals( 2, count( $purchase_stats ) );
		$this->assertTrue( isset( $purchase_stats['purchases'] ) );
		$this->assertTrue( isset( $purchase_stats['total_spent'] ) );

	}

	public function test_get_purchase_total_of_user() {
		
		$purchase_total = edd_purchase_total_of_user( $this->_user_id );
		
		$this->assertEquals( 100, $purchase_total );
	}

	public function test_validate_username() {
		$this->assertTrue( edd_validate_username( 'easydigitaldownloads' ) );
		$this->assertFalse( edd_validate_username( 'edd12345$%&+-!@£%^&()(*&^%$£@!' ) );
	}
}