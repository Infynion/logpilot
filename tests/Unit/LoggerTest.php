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
		
		// Common generic mocks for WP functions
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\expect( 'get_bloginfo' )->andReturn( 'Test Blog' )->zeroOrMoreTimes();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// 1. Hooks Registration
	public function test_register_hooks_aborts_when_disabled() {
		$database = \Mockery::mock( Database::class );
		$logger   = new Logger( $database );

		Functions\expect( 'get_option' )->once()->with( 'logpilot_enable', 1 )->andReturn( 0 );
		Functions\expect( 'add_action' )->never();

		$logger->register_hooks();
		$this->assertTrue( true );
	}

	public function test_register_hooks_adds_actions_when_enabled() {
		$database = \Mockery::mock( Database::class );
		$logger   = new Logger( $database );

		Functions\expect( 'get_option' )->once()->with( 'logpilot_enable', 1 )->andReturn( 1 );
		Functions\expect( 'add_action' )->times( 3 );

		$logger->register_hooks();
		$this->assertTrue( true );
	}

	// 2. PHP Error Handlers mapping
	public function test_register_error_handlers() {
		Functions\expect( 'set_error_handler' )->once();
		Functions\expect( 'set_exception_handler' )->once();
		Functions\expect( 'register_shutdown_function' )->once();

		$database = \Mockery::mock( Database::class );
		$logger   = new Logger( $database );

		$logger->register_error_handlers();
		$this->assertTrue( true );
	}

	public function test_handle_php_error_triggers_log() {
		$database = \Mockery::mock( Database::class );
		$logger   = \Mockery::mock( Logger::class . '[log]', [ $database ] );
		
		$logger->shouldReceive( 'log' )->once()->with(
			[ 'message' => 'Test error message', 'file' => 'test.php', 'line' => 42 ],
			'php_error'
		);

		$result = $logger->handle_php_error( E_WARNING, 'Test error message', 'test.php', 42 );
		$this->assertFalse( $result ); // Should return false to allow native handler
	}

	public function test_handle_exception_triggers_log() {
		$database = \Mockery::mock( Database::class );
		$logger   = \Mockery::mock( Logger::class . '[log]', [ $database ] );
		
		$exception = new \Exception( "Test exception", 123 );
		
		$logger->shouldReceive( 'log' )->once()->with(
			\Mockery::on( function( $arg ) {
				return $arg['message'] === 'Test exception' && $arg['file'] === __FILE__;
			}),
			'exception'
		);

		$logger->handle_exception( $exception );
		$this->assertTrue( true );
	}

	// 3. Fatal Error interceptor
	public function test_handle_shutdown_fatal_logs_error() {
		Functions\when( 'error_get_last' )->justReturn( [
			'type'    => E_ERROR,
			'message' => 'Fatal memory exhausted',
			'file'    => 'index.php',
			'line'    => 10,
		] );

		$database = \Mockery::mock( Database::class );
		$logger   = \Mockery::mock( Logger::class . '[log]', [ $database ] );
		$logger->shouldReceive( 'log' )->once()->with(
			[ 'message' => 'Fatal memory exhausted', 'file' => 'index.php', 'line' => 10 ],
			'fatal'
		);

		$logger->handle_shutdown_fatal();
		$this->assertTrue( true );
	}

	public function test_handle_shutdown_fatal_skips_non_fatals() {
		Functions\when( 'error_get_last' )->justReturn( [
			'type'    => E_NOTICE,
			'message' => 'Undefined index',
			'file'    => 'index.php',
			'line'    => 10,
		] );

		$database = \Mockery::mock( Database::class );
		$logger   = \Mockery::mock( Logger::class . '[log]', [ $database ] );
		$logger->shouldReceive( 'log' )->never();

		$logger->handle_shutdown_fatal();
		$this->assertTrue( true );
	}

	// 4. Manual Payload Insertion
	public function test_log_payload_inserts_into_database() {
		$database = \Mockery::mock( Database::class );
		$expected_encrypted = base64_encode( json_encode( [ 'message' => 'Manual error' ] ) );

		$database->shouldReceive( 'insert_or_increment' )->once()->with( \Mockery::on( function ( $arg ) use ( $expected_encrypted ) {
			return $arg['type'] === 'manual_error' && $arg['message'] === $expected_encrypted;
		} ) )->andReturn( [ 'is_new' => true, 'log_id' => 1 ] );

		Functions\when( 'get_option' )->alias( function( $attr, $default = false ) {
			return $default; // Disables notifications naturally for this test
		});

		$logger = new Logger( $database );
		$logger->log( [ 'message' => 'Manual error' ], 'manual_error' );
		
		$this->assertTrue( true );
	}

	// 5. Notifications
	public function test_log_sends_email_notification_on_new_error() {
		$database = \Mockery::mock( Database::class );
		$database->shouldReceive( 'insert_or_increment' )->once()->andReturn( [ 'is_new' => true, 'log_id' => 1 ] );

		Functions\when( 'get_option' )->alias( function( $attr, $default = false ) {
			if ( $attr === 'logpilot_notify' ) return 1;
			if ( $attr === 'logpilot_notify_emails' ) return 'admin@test.local';
			return $default;
		});

		// Expect wp_mail to be called
		Functions\expect( 'wp_mail' )->once()->with(
			'admin@test.local',
			\Mockery::type('string'),
			\Mockery::type('string')
		);

		$logger = new Logger( $database );
		$logger->log( [ 'message' => 'Fatal crash', 'file' => 'crash.php', 'line' => 1 ], 'fatal' );
		
		$this->assertTrue( true );
	}

	public function test_log_skips_email_notification_for_existing_error() {
		$database = \Mockery::mock( Database::class );
		// Simulating an existing error (is_new = false)
		$database->shouldReceive( 'insert_or_increment' )->once()->andReturn( [ 'is_new' => false, 'log_id' => 1 ] );

		Functions\when( 'get_option' )->alias( function( $attr, $default = false ) {
			if ( $attr === 'logpilot_notify' ) return 1;
			return $default;
		});
		
		// wp_mail should NOT be called
		Functions\expect( 'wp_mail' )->never();

		$logger = new Logger( $database );
		$logger->log( [ 'message' => 'Repeated error' ], 'warning' );
		
		$this->assertTrue( true );
	}

	// 6. HTTP API Interception Scenarios
	public function test_intercept_http_errors_logs_on_fail() {
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 500 );
		Functions\expect( 'wp_remote_retrieve_body' )->andReturn( 'Server Error' );

		$database = \Mockery::mock( Database::class );
		$logger   = \Mockery::mock( Logger::class . '[log]', [ $database ] );
		
		$logger->shouldReceive( 'log' )->once()->with(
			\Mockery::on( function($arg) {
				return $arg['status'] === 500 && $arg['url'] === 'https://api.test';
			}),
			'http_error'
		);

		// Trigger interception
		$logger->intercept_http_errors( [ 'body' => 'Server Error' ], 'context', 'class', [], 'https://api.test' );
		$this->assertTrue( true );
	}

	public function test_intercept_http_errors_skips_if_ajax() {
		Functions\expect( 'wp_doing_ajax' )->once()->andReturn( true );
		Functions\expect( 'wp_remote_retrieve_response_code' )->never();

		$database = \Mockery::mock( Database::class );
		$logger   = \Mockery::mock( Logger::class . '[log]', [ $database ] );
		$logger->shouldReceive( 'log' )->never();

		$logger->intercept_http_errors( [], 'context', 'class', [], 'https://api.test' );
		$this->assertTrue( true );
	}

	public function test_intercept_http_errors_skips_successful_requests() {
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );

		$database = \Mockery::mock( Database::class );
		$logger   = \Mockery::mock( Logger::class . '[log]', [ $database ] );
		$logger->shouldReceive( 'log' )->never();

		$logger->intercept_http_errors( [ 'body' => 'Success' ], 'context', 'class', [], 'https://api.test' );
		$this->assertTrue( true );
	}
}
