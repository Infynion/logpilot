<?php
namespace Infynion\Logpilot\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Infynion\Logpilot\Admin\UI;
use Infynion\Logpilot\Database;
use Infynion\Logpilot\Logger;

class UITest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->alias( function($a) { return $a; } );
		Functions\when( 'esc_html__' )->alias( function($a) { return $a; } );
		Functions\when( 'esc_html_e' )->alias( function($a) { echo $a; } );
		Functions\when( 'esc_html' )->alias( function($a) { return $a; } );
		Functions\when( 'esc_attr' )->alias( function($a) { return $a; } );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_register_hooks() {
		$database = \Mockery::mock( Database::class );
		$logger   = \Mockery::mock( Logger::class );
		$ui       = new UI( $database, $logger );

		Functions\expect( 'add_action' )->times( 2 );

		$ui->register_hooks();
		$this->assertTrue( true );
	}

	public function test_add_menu_page() {
		$database = \Mockery::mock( Database::class );
		$logger   = \Mockery::mock( Logger::class );
		$ui       = new UI( $database, $logger );

		Functions\expect( 'add_management_page' )->once();

		$ui->add_menu_page();
		$this->assertTrue( true );
	}

	public function test_register_settings() {
		$database = \Mockery::mock( Database::class );
		$logger   = \Mockery::mock( Logger::class );
		$ui       = new UI( $database, $logger );

		Functions\expect( 'register_setting' )->times( 4 );

		$ui->register_settings();
		$this->assertTrue( true );
	}

	public function test_render_page_denies_access_without_capabilities() {
		$database = \Mockery::mock( Database::class );
		$logger   = \Mockery::mock( Logger::class );
		$ui       = new UI( $database, $logger );

		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( false );
		Functions\expect( 'wp_die' )->once();

		$ui->render_page();
		$this->assertTrue( true );
	}

	public function test_render_single_log_outputs_log_details() {
		$database = \Mockery::mock( Database::class );
		$logger   = \Mockery::mock( Logger::class );

		$database->shouldReceive( 'get_log' )->once()->with( 5 )->andReturn( [
			'id'            => 5,
			'type'          => 'fatal',
			'occurrences'   => 10,
			'last_occurred' => '2023-01-01 00:00:00',
			'file'          => 'test.php',
			'line'          => 123,
			'message'       => base64_encode( '{"message":"test"}' )
		] );

		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

		$ui = new UI( $database, $logger );
		
		$method = new \ReflectionMethod( UI::class, 'render_single_log' );
		$method->setAccessible( true );

		ob_start();
		$method->invoke( $ui, 5 );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Log Details', $output );
		$this->assertStringContainsString( 'test.php:123', $output );
	}

	public function test_render_single_log_outputs_not_found_on_invalid_id() {
		$database = \Mockery::mock( Database::class );
		$logger   = \Mockery::mock( Logger::class );

		$database->shouldReceive( 'get_log' )->once()->with( 99 )->andReturn( null );
		
		$ui = new UI( $database, $logger );
		
		$method = new \ReflectionMethod( UI::class, 'render_single_log' );
		$method->setAccessible( true );

		ob_start();
		$method->invoke( $ui, 99 );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Log not found.', $output );
	}

	public function test_render_config() {
		$database = \Mockery::mock( Database::class );
		$logger   = \Mockery::mock( Logger::class );

		Functions\expect( 'get_option' )->zeroOrMoreTimes()->andReturn( '' );
		Functions\expect( 'checked' )->zeroOrMoreTimes();
		Functions\expect( 'settings_fields' )->once();
		Functions\expect( 'submit_button' )->once();
		Functions\expect( 'sanitize_email' )->zeroOrMoreTimes()->andReturn( 'test@local.com' );

		$ui = new UI( $database, $logger );
		
		$method = new \ReflectionMethod( UI::class, 'render_config' );
		$method->setAccessible( true );

		ob_start();
		$method->invoke( $ui );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<form method="post"', $output );
	}
}
