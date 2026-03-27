<?php
namespace Infynion\Logpilot\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Infynion\Logpilot\Plugin;

class PluginTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_get_instance_returns_singleton() {
		$instance1 = Plugin::get_instance();
		$instance2 = Plugin::get_instance();
		
		$this->assertInstanceOf( Plugin::class, $instance1 );
		$this->assertSame( $instance1, $instance2 );
	}

	public function test_init_registers_hooks_and_cron() {
		Functions\when( 'get_option' )->justReturn( 0 ); 
		Functions\expect( 'add_action' )->zeroOrMoreTimes(); 
		Functions\expect( 'wp_next_scheduled' )->once()->with( 'logpilot_daily_cleanup' )->andReturn( false );
		Functions\expect( 'wp_schedule_event' )->once();

		$plugin = Plugin::get_instance();
		Functions\expect( 'is_admin' )->once()->andReturn( false );

		$plugin->init();
		
		$this->assertTrue( true );
	}
}
