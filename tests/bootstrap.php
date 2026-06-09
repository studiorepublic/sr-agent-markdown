<?php

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once __DIR__ . '/stubs/wordpress.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/stubs/' );
}
