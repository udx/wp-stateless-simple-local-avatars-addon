<?php

namespace WPSL\SimpleLocalAvatars;

use PHPUnit\Framework\TestCase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use wpCloud\StatelessMedia\WPStatelessStub;

/**
 * Class ClassSimpleLocalAvatarsTest
 */

class ClassSimpleLocalAvatarsTest extends TestCase {
  const TEST_URL = 'https://test.test';
  const UPLOADS_URL = self::TEST_URL . '/uploads';
  const TEST_FILE = 'avatar.png';
  const SRC_URL = self::UPLOADS_URL . '/' . self::TEST_FILE;
  const DST_URL = WPStatelessStub::TEST_GS_HOST . '/' . self::TEST_FILE;
  const TEST_UPLOAD_DIR = [
    'baseurl' => self::UPLOADS_URL,
    'basedir' => '/var/www/uploads'
  ];

  // Adds Mockery expectations to the PHPUnit assertions count.
  use MockeryPHPUnitIntegration;

  public function setUp(): void {
		parent::setUp();
		Monkey\setUp();

    // WP mocks
    Functions\when('wp_get_upload_dir')->justReturn( self::TEST_UPLOAD_DIR );
        
    // WP_Stateless mocks
    Filters\expectApplied('wp_stateless_file_name')->andReturn( self::TEST_FILE );

    Functions\when('ud_get_stateless_media')->justReturn( WPStatelessStub::instance() );
  }
	
  public function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

  public function testShouldInitHooks() {
    $simpleLocalAvatars = new SimpleLocalAvatars();

    $simpleLocalAvatars->module_init([ 'mode' => 'cdn' ]);

    self::assertNotFalse( has_filter('get_user_metadata', [ $simpleLocalAvatars, 'get_user_metadata' ]) );
  }

  public function testShouldNotInitHooks() {
    $simpleLocalAvatars = new SimpleLocalAvatars();

    $simpleLocalAvatars->module_init([ 'mode' => 'backup' ]);

    self::assertFalse( has_filter('get_user_metadata', [ $simpleLocalAvatars, 'get_user_metadata' ]) );
  }

  public function testShouldGetUserMetadata() {
    $simpleLocalAvatars = new SimpleLocalAvatars();

    // Mocks
    $metaData = [
      [ self::SRC_URL ],
    ];

    Functions\when('get_user_meta')->justReturn( $metaData );
    Filters\expectApplied('wp_stateless_bucket_link')->andReturn( WPStatelessStub::TEST_GS_HOST );

    self::assertEquals(
      json_encode( [ [ self::DST_URL ] ] ), 
      json_encode( $simpleLocalAvatars->get_user_metadata(null, 15, 'simple_local_avatar', null) )
    );

    self::assertNotFalse( has_filter('get_user_metadata', [ $simpleLocalAvatars, 'get_user_metadata' ]) );
  }

  public function testShouldNotGetUserMetadata() {
    $simpleLocalAvatars = new SimpleLocalAvatars();

    self::assertEquals(
      null, 
      $simpleLocalAvatars->get_user_metadata(null, 15, 'something-else', null)
    );
  }
}
