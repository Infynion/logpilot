<?php
namespace Infynion\Logpilot\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Infynion\Logpilot\Database;

class DatabaseTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		
		Functions\when( 'sanitize_text_field' )->alias( function( $a ) { return $a; } );
		Functions\when( 'absint' )->alias( function( $a ) { return (int) $a; } );
		Functions\when( 'current_time' )->justReturn( '2023-01-01 00:00:00' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_create_table_executes_dbdelta() {
		global $wpdb;
		$wpdb = \Mockery::mock();
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'get_charset_collate' )->once()->andReturn( 'CHARSET utf8mb4' );

		Functions\expect( 'dbDelta' )->once()->with(
			\Mockery::on( function($sql) {
				return strpos( $sql, 'CREATE TABLE IF NOT EXISTS wp_logpilot_errors' ) !== false;
			})
		);

		Database::create_table();
		$this->assertTrue( true );
	}

	public function test_insert_new_log() {
		global $wpdb;
		$wpdb = \Mockery::mock();
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'prepare' )->andReturn( 'prepared statement' );
		
		// Simulate update fails (0 rows updated) means new log
		$wpdb->shouldReceive( 'query' )->once()->andReturn( 0 );
		
		// Simulate insert succeeds
		$wpdb->shouldReceive( 'insert' )->once()->andReturn( 1 );
		$wpdb->insert_id = 123;

		$database = new Database();
		$result = $database->insert_or_increment( [
			'message' => 'Test message',
			'file'    => 'test.php',
			'line'    => 10,
			'type'    => 'error'
		] );

		$this->assertTrue( $result['is_new'] );
		$this->assertEquals( 123, $result['log_id'] );
	}
	
	public function test_increment_existing_log() {
		global $wpdb;
		$wpdb = \Mockery::mock();
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'prepare' )->andReturn( 'prepared statement' );
		
		// Simulate update succeeds (1 or more rows updated)
		$wpdb->shouldReceive( 'query' )->once()->andReturn( 1 );
		
		// Simulate fetch to get the existing log_id
		$wpdb->shouldReceive( 'get_var' )->once()->andReturn( 456 );

		$database = new Database();
		$result = $database->insert_or_increment( [
			'message' => 'Existing message',
			'file'    => 'test.php',
			'line'    => 10,
			'type'    => 'error'
		] );

		$this->assertFalse( $result['is_new'] );
		$this->assertEquals( 456, $result['log_id'] );
	}

	public function test_get_log_retrieves_row() {
		global $wpdb;
		$wpdb = \Mockery::mock();
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'prepare' )->once()->with( "SELECT * FROM wp_logpilot_errors WHERE id = %d", 5 )->andReturn( 'prepared sql' );
		$wpdb->shouldReceive( 'get_row' )->once()->with( 'prepared sql', ARRAY_A )->andReturn( [ 'id' => 5, 'message' => 'test' ] );

		$database = new Database();
		$result = $database->get_log( 5 );

		$this->assertEquals( 'test', $result['message'] );
	}

	public function test_update_resolve_state_executes_query() {
		global $wpdb;
		$wpdb = \Mockery::mock();
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'prepare' )->once()->with( "UPDATE wp_logpilot_errors SET resolved = %d WHERE id IN (%d,%d)", 1, 4, 5 )->andReturn( 'prepared update sql' );
		$wpdb->shouldReceive( 'query' )->once()->with( 'prepared update sql' )->andReturn( 2 );

		$database = new Database();
		$database->update_resolve_state( [ 4, 5 ], 1 );

		$this->assertTrue( true );
	}

	public function test_update_resolve_state_aborts_on_empty_ids() {
		global $wpdb;
		$wpdb = \Mockery::mock(); // strict mock, should not receive any calls
		$database = new Database();
		$database->update_resolve_state( [], 1 );
		$this->assertTrue( true );
	}

	public function test_delete_logs_executes_query() {
		global $wpdb;
		$wpdb = \Mockery::mock();
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'prepare' )->once()->with( "DELETE FROM wp_logpilot_errors WHERE id IN (%d,%d)", 4, 5 )->andReturn( 'prepared delete sql' );
		$wpdb->shouldReceive( 'query' )->once()->with( 'prepared delete sql' )->andReturn( 2 );

		$database = new Database();
		$database->delete_logs( [ 4, 5 ] );

		$this->assertTrue( true );
	}

	public function test_delete_logs_aborts_on_empty_ids() {
		global $wpdb;
		$wpdb = \Mockery::mock(); 
		$database = new Database();
		$database->delete_logs( [] );
		$this->assertTrue( true );
	}

	// 4. Cron functionalities
	public function test_delete_old_logs_executes_query_when_enabled() {
		global $wpdb;
		$wpdb = \Mockery::mock();
		$wpdb->prefix = 'wp_';
		
		// Expect database query to run
		$wpdb->shouldReceive( 'prepare' )->once()->with(
			\Mockery::on( function($sql) { 
				return strpos( $sql, 'DELETE FROM wp_logpilot_errors WHERE last_occurred < %s' ) !== false; 
			} ),
			\Mockery::type('string')
		)->andReturn( 'prepared delete old sql' );
		
		$wpdb->shouldReceive( 'query' )->once()->with( 'prepared delete old sql' )->andReturn( 1 );

		// Enable expiration to 30 days
		Functions\expect( 'get_option' )->once()->with( 'logpilot_expire', 7 )->andReturn( 30 );

		$database = new Database();
		$database->delete_old_logs();
		$this->assertTrue( true ); // Assertion via Mockery expectations
	}

	public function test_delete_old_logs_skips_when_disabled() {
		global $wpdb;
		// Strict mock will throw an error if any undeclared method is called, verifying WPDB doesn't execute
		$wpdb = \Mockery::mock(); 
		
		// Disable expiration by returning 0
		Functions\expect( 'get_option' )->once()->with( 'logpilot_expire', 7 )->andReturn( 0 );

		$database = new Database();
		$database->delete_old_logs();
		
		$this->assertTrue( true );
	}
}
