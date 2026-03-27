<?php
namespace Infynion\Logpilot\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Infynion\Logpilot\Database;
use Infynion\Logpilot\Logger;
use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_register_hooks() {
		Functions\expect( 'get_option' )->once()->with( 'logpilot_enable', 1 )->andReturn( 1 );
		Functions\expect( 'add_action' )->times( 3 );

		$database = \Mockery::mock( Database::class );
		$logger   = new Logger( $database );

		$logger->register_hooks();
		$this->assertTrue( true ); // assertion via expects()
	}

	public function test_log_payload() {
		Functions\expect( 'wp_json_encode' )->once()->andReturn( '{"message":"Test"}' );

		$database = \Mockery::mock( Database::class );
		$database->shouldReceive( 'insert_or_increment' )->once()->with( \Mockery::on( function ( $arg ) {
			return $arg['type'] === 'test_error' && $arg['message'] === base64_encode( '{"message":"Test"}' );
		} ) );

		$logger = new Logger( $database );
		$logger->log( [ 'message' => 'Test' ], 'test_error' );
		
		$this->assertTrue( true );
	}
}
