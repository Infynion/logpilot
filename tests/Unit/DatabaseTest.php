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
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_insert_new_log() {
		// Mock sanitize and absint
		Functions\expect( 'sanitize_text_field' )->andReturnFirstArg();
		Functions\expect( 'absint' )->andReturnFirstArg();
		Functions\expect( 'current_time' )->andReturn( '2023-01-01 00:00:00' );

		// Mock WPDB
		global $wpdb;
		$wpdb = \Mockery::mock();
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'prepare' )->andReturn( 'prepared statement' );
		
		// Simulate update fails (0 rows updated)
		$wpdb->shouldReceive( 'query' )->once()->andReturn( 0 );
		
		// Simulate insert succeeds
		$wpdb->shouldReceive( 'insert' )->once()->andReturn( 1 );
		$wpdb->insert_id = 123;

		global $wpdb;

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
}
