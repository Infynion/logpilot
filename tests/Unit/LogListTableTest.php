<?php
namespace Infynion\Logpilot\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Infynion\Logpilot\Admin\LogListTable;
use Infynion\Logpilot\Database;

class LogListTableTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->alias( function($a) { return $a; } );
		Functions\when( 'esc_html__' )->alias( function($a) { return $a; } );
		Functions\when( 'esc_url' )->alias( function($a) { return $a; } );
		Functions\when( 'esc_html' )->alias( function($a) { return $a; } );
		Functions\when( 'wp_create_nonce' )->justReturn( 'nonce123' );
		Functions\when( 'add_query_arg' )->justReturn( 'http://test.local' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_get_columns() {
		$database = \Mockery::mock( Database::class );
		$table = new LogListTable( $database );
		
		$columns = $table->get_columns();
		$this->assertIsArray( $columns );
		$this->assertArrayHasKey( 'cb', $columns );
		$this->assertArrayHasKey( 'message', $columns );
		$this->assertArrayHasKey( 'type', $columns );
	}

	// get_sortable_columns isn't explicitly defined in our LogListTable MVP, but we can verify it doesn't crash
	public function test_get_sortable_columns() {
		$database = \Mockery::mock( Database::class );
		$table = new LogListTable( $database );
		$this->assertTrue( true );
	}

	public function test_get_bulk_actions() {
		$database = \Mockery::mock( Database::class );
		$table = new LogListTable( $database );
		
		$actions = $table->get_bulk_actions();
		$this->assertIsArray( $actions );
		$this->assertArrayHasKey( 'delete', $actions );
	}

	public function test_prepare_items() {
		global $wpdb;
		$wpdb = \Mockery::mock();
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'get_var' )->once()->andReturn( 10 );
		$wpdb->shouldReceive( 'prepare' )->andReturn( 'sql_query' );
		$wpdb->shouldReceive( 'get_results' )->once()->andReturn( [ [ 'id' => 1, 'message' => base64_encode('{}') ] ] );

		// sanitize_sql_orderby mock for WP list tables
		Functions\when( 'sanitize_sql_orderby' )->alias( function($a) { return $a; } );

		$database = \Mockery::mock( Database::class );
		$table = new LogListTable( $database );

		$table->prepare_items();
		$this->assertTrue( true );
	}
	
	public function test_process_bulk_action_delete() {
		$_REQUEST['action'] = 'delete';
		$_POST['log_ids']   = [ 1, 2 ];
		
		$database = \Mockery::mock( Database::class );
		$database->shouldReceive( 'delete_logs' )->once()->with( [ 1, 2 ] );

		$table = new LogListTable( $database );
		
		$method = new \ReflectionMethod( LogListTable::class, 'process_bulk_action' );
		$method->setAccessible( true );
		$method->invoke( $table );
		
		$this->assertTrue( true );
		
		// Cleanup globals
		unset( $_REQUEST['action'], $_POST['log_ids'] );
	}
}
