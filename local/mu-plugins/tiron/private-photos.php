<?php
/**
 * Private raster storage for customer photos. Requires local POSIX storage with flock().
 * Public cart/order data must never contain a filesystem path or an original filename.
 */
defined( 'ABSPATH' ) || exit;

function tiron_photo_limit_mb(): int {
	$limit = max( 1, min( 20, (int) get_option( 'tiron_photo_limit_mb', 10 ) ) );
	// Leave room for the multipart envelope if the PHP POST limit is the binding limit.
	$post_bytes = wp_convert_hr_to_bytes( ini_get( 'post_max_size' ) );
	$max_bytes  = wp_max_upload_size();
	if ( $post_bytes > 0 ) {
		$max_bytes = min( $max_bytes, max( 0, $post_bytes - 65536 ) );
	}
	return min( $limit, (int) floor( $max_bytes / MB_IN_BYTES ) );
}

function tiron_photo_failure( string $message = 'Fotografia nu a putut fi salvată. Încearcă din nou.' ): WP_Error {
	return new WP_Error( 'tiron_photo_error', $message );
}

/** Canonical dedicated directory, known to the web server operator, outside public roots. */
function tiron_photo_root() {
	if ( ! defined( 'TIRON_PRIVATE_PHOTO_DIR' ) || ! is_string( TIRON_PRIVATE_PHOTO_DIR ) ) {
		return false;
	}
	$configured = rtrim( TIRON_PRIVATE_PHOTO_DIR, '/' );
	if ( '' === $configured || '/' !== $configured[0] || str_contains( $configured, "\0" ) || is_link( $configured ) ) {
		return false;
	}
	$root = realpath( $configured );
	if ( ! $root || '/' === $root || ! is_dir( $root ) || ! is_readable( $root ) || ! is_writable( $root ) ) {
		return false;
	}
	$mode = fileperms( $root );
	if ( false === $mode || 0 !== ( $mode & 0077 ) ) {
		return false;
	}
	if ( function_exists( 'posix_geteuid' ) && fileowner( $root ) !== posix_geteuid() ) {
		return false;
	}
	$uploads = wp_get_upload_dir();
	$public  = array( ABSPATH, WP_CONTENT_DIR, WP_PLUGIN_DIR, WPMU_PLUGIN_DIR, $uploads['basedir'], $_SERVER['DOCUMENT_ROOT'] ?? '' );
	// Account for an installation with additional, externally configured web server aliases.
	if ( defined( 'TIRON_ADDITIONAL_PUBLIC_ROOTS' ) && is_array( TIRON_ADDITIONAL_PUBLIC_ROOTS ) ) {
		$public = array_merge( $public, TIRON_ADDITIONAL_PUBLIC_ROOTS );
	}
	foreach ( $public as $path ) {
		if ( ! is_string( $path ) || '' === $path ) {
			continue;
		}
		$resolved = realpath( $path );
		$webroot  = rtrim( $resolved ?: $path, '/' ) . '/';
		if ( str_starts_with( $root . '/', $webroot ) || str_starts_with( $webroot, $root . '/' ) ) {
			return false;
		}
	}
	return $root;
}

/** Bucket names are supplied only by this module. They contain no user data. */
function tiron_photo_bucket( string $root, string $name ) {
	$path = $root . '/' . $name;
	if ( ! is_dir( $path ) && ! @mkdir( $path, 0700 ) && ! is_dir( $path ) ) {
		return false;
	}
	return ! is_link( $path ) && $path === realpath( $path ) && 0 === ( fileperms( $path ) & 0077 ) ? $path : false;
}

/** Serialize brief ownership/rename/unlink operations, never customer upload or image decode. */
function tiron_with_photo_lock( callable $operation ) {
	$root = tiron_photo_root();
	if ( ! $root || is_link( $root . '/.lock' ) ) {
		return false;
	}
	$handle = @fopen( $root . '/.lock', 'c+b' );
	if ( ! $handle ) {
		return false;
	}
	try {
		if ( ! chmod( $root . '/.lock', 0600 ) || ! flock( $handle, LOCK_EX ) ) {
			return false;
		}
		return $operation( $root );
	} finally {
		fclose( $handle );
	}
}

function tiron_is_photo_id( $id ): bool {
	return is_string( $id ) && 1 === preg_match( '/\A[0-9a-f]{64}\z/D', $id );
}

function tiron_private_raster( string $path ) {
	return ! is_link( $path ) && $path === realpath( $path ) && is_file( $path ) && is_readable( $path ) ? $path : false;
}

function tiron_photo_path( $id ) {
	$root = tiron_photo_root();
	if ( ! $root || ! tiron_is_photo_id( $id ) ) {
		return false;
	}
	return tiron_private_raster( $root . '/orders/' . $id . '/photo.jpg' )
		?: tiron_private_raster( $root . '/staged/' . $id . '.jpg' );
}

/** GD builds a new raster. No EXIF, GPS, original file chunks, or filename are copied. */
function tiron_decode_photo( string $source, string $mime, int $width, int $height, int $bytes ) {
	$decoders = array( 'image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng', 'image/webp' => 'imagecreatefromwebp' );
	$decoder  = $decoders[ $mime ];
	if ( ! function_exists( $decoder ) || ! function_exists( 'imagejpeg' ) ) {
		return tiron_photo_failure( 'Procesarea acestui format este momentan indisponibilă.' );
	}
	$orientation = 1;
	if ( 'image/jpeg' === $mime && function_exists( 'exif_read_data' ) ) {
		$exif        = @exif_read_data( $source, 'IFD0' );
		$orientation = (int) ( $exif['Orientation'] ?? 1 );
		unset( $exif );
	}
	// A rotation/alpha flatten needs two rasters. Native GD allocations may exceed PHP accounting.
	$raster_bytes = 'image/jpeg' !== $mime || $orientation > 2 ? 11 : 6;
	$required     = $width * $height * $raster_bytes + $bytes + 12 * MB_IN_BYTES;
	$memory_cap   = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
	if ( $required > 288 * MB_IN_BYTES || ( $memory_cap > 0 && $required > $memory_cap - memory_get_usage( true ) ) ) {
		return tiron_photo_failure( 'Rezoluția fotografiei este prea mare pentru procesare. Alege o copie redimensionată.' );
	}

	$warning          = false;
	$old_jpeg_setting = ini_get( 'gd.jpeg_ignore_warning' );
	set_error_handler( static function () use ( &$warning ) {
		$warning = true;
		return true;
	}, E_WARNING );
	try {
		ini_set( 'gd.jpeg_ignore_warning', '0' );
		$image = $decoder( $source );
	} finally {
		if ( false !== $old_jpeg_setting ) {
			ini_set( 'gd.jpeg_ignore_warning', $old_jpeg_setting );
		}
		restore_error_handler();
	}
	if ( ! $image || $warning || imagesx( $image ) !== $width || imagesy( $image ) !== $height ) {
		return tiron_photo_failure( 'Fișierul nu poate fi citit ca fotografie. Deschide-l și exportă-l din nou.' );
	}

	if ( 'image/jpeg' !== $mime ) {
		// JPEG has no alpha. Composite transparency over white for a predictable lithophane input.
		$flat = imagecreatetruecolor( $width, $height );
		if ( ! $flat || ! imagefill( $flat, 0, 0, imagecolorallocate( $flat, 255, 255, 255 ) ) || ! imagecopy( $flat, $image, 0, 0, 0, 0, $width, $height ) ) {
			return tiron_photo_failure();
		}
		return $flat;
	}
	if ( in_array( $orientation, array( 2, 4, 5, 7 ), true ) ) {
		if ( ! imageflip( $image, 4 === $orientation ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL ) ) {
			return tiron_photo_failure();
		}
	}
	// GD angles are counterclockwise. Orientations 5 and 7 also need the horizontal flip above.
	$rotations = array( 3 => 180, 5 => 90, 6 => -90, 7 => -90, 8 => 90 );
	if ( isset( $rotations[ $orientation ] ) ) {
		$image = imagerotate( $image, $rotations[ $orientation ], 0 );
	}
	return $image ?: tiron_photo_failure();
}

/** The only customer input entry point. Always requires a native PHP HTTP multipart upload. */
function tiron_store_photo( array $file ) {
	$error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
	if ( in_array( $error, array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) ) {
		return tiron_photo_failure( 'Fișierul depășește limita de încărcare.' );
	}
	if ( UPLOAD_ERR_OK !== $error || ! isset( $file['name'], $file['tmp_name'], $file['size'] )
		|| ! is_string( $file['name'] ) || ! is_string( $file['tmp_name'] ) || ! is_int( $file['size'] )
		|| ! is_uploaded_file( $file['tmp_name'] ) ) {
		return tiron_photo_failure( 'Selectează o fotografie și încearcă să o încarci din nou.' );
	}
	$source = $file['tmp_name'];
	$bytes  = filesize( $source );
	$limit  = tiron_photo_limit_mb();
	if ( ! $bytes || $bytes !== $file['size'] || $bytes > $limit * MB_IN_BYTES ) {
		return tiron_photo_failure( sprintf( 'Fotografia trebuie să aibă cel mult %d MB.', $limit ) );
	}

	$types = array( 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' );
	$mime  = $types[ strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) ] ?? '';
	$info  = @getimagesize( $source );
	if ( ! $mime || ! $info || ( $info['mime'] ?? '' ) !== $mime || ! class_exists( 'finfo' ) || ( new finfo( FILEINFO_MIME_TYPE ) )->file( $source ) !== $mime ) {
		return tiron_photo_failure( 'Formatul real al fotografiei trebuie să corespundă extensiei JPG, PNG sau WebP.' );
	}
	list( $width, $height ) = $info;
	if ( $width < 1 || $height < 1 || $width > 10000 || $height > 10000 || $width * $height > 24000000 ) {
		return tiron_photo_failure( 'Alege o fotografie de cel mult 24 megapixeli și 10.000 px pe latură.' );
	}

	$root  = tiron_photo_root();
	$stage = $root ? tiron_photo_bucket( $root, 'staged' ) : false;
	if ( ! $stage ) {
		return tiron_photo_failure( 'Stocarea privată este momentan indisponibilă. Încearcă mai târziu.' );
	}
	$handle = null;
	$temp   = null;
	try {
		$image = tiron_decode_photo( $source, $mime, $width, $height, $bytes );
		if ( is_wp_error( $image ) ) {
			return $image;
		}
		$id     = bin2hex( random_bytes( 32 ) );
		$temp   = $stage . '/' . $id . '.part';
		$handle = @fopen( $temp, 'x+b' );
		if ( ! $handle ) {
			$temp = null; // An existing path was never ours.
			return tiron_photo_failure();
		}
		if ( ! flock( $handle, LOCK_EX ) || ! chmod( $temp, 0600 ) || ! imagejpeg( $image, $handle, 93 ) || ! fflush( $handle ) ) {
			return tiron_photo_failure();
		}
		unset( $image );
		$written = fstat( $handle )['size'];
		$output  = @getimagesize( $temp );
		if ( $written < 1 || $written > 40 * MB_IN_BYTES || ! $output || IMAGETYPE_JPEG !== $output[2] ) {
			return tiron_photo_failure();
		}
		$ready = tiron_with_photo_lock( static function ( $root ) use ( $stage, $temp, $id ) {
			$final = $stage . '/' . $id . '.jpg';
			return ! file_exists( $final ) && ! file_exists( $root . '/orders/' . $id ) && rename( $temp, $final );
		} );
		return $ready ? $id : tiron_photo_failure();
	} catch ( Throwable $error ) {
		return tiron_photo_failure();
	} finally {
		if ( $temp && is_file( $temp ) ) {
			@unlink( $temp );
		}
		if ( is_resource( $handle ) ) {
			fclose( $handle );
		}
	}
}

/**
 * Each new order line owns a fresh immutable key, even on a checkout retry.
 * Keep a lease until request shutdown so GC can never race the initial WC database save.
 */
function tiron_copy_photo_for_order( $staged_id ) {
	static $active_leases = array();
	if ( ! tiron_is_photo_id( $staged_id ) ) {
		return tiron_photo_failure( 'Selectează din nou fotografia pentru acest produs.' );
	}
	$result = tiron_with_photo_lock( static function ( $root ) use ( $staged_id, &$active_leases ) {
		$source = tiron_private_raster( $root . '/staged/' . $staged_id . '.jpg' );
		$orders = tiron_photo_bucket( $root, 'orders' );
		if ( ! $source || ! $orders ) {
			return false;
		}
		$id   = bin2hex( random_bytes( 32 ) );
		$path = $orders . '/' . $id;
		if ( file_exists( $root . '/staged/' . $id . '.jpg' ) || ! @mkdir( $path, 0700 ) ) {
			return false;
		}
		$lease = @fopen( $path . '/created', 'x+b' );
		$ok    = $lease && chmod( $path . '/created', 0600 ) && flock( $lease, LOCK_SH )
			&& ( @link( $source, $path . '/photo.jpg' ) || copy( $source, $path . '/photo.jpg' ) )
			&& chmod( $path . '/photo.jpg', 0600 );
		if ( $ok ) {
			$active_leases[] = $lease;
			return $id;
		}
		if ( $lease ) {
			fclose( $lease );
		}
		tiron_remove_photo_record( $path );
		return false;
	} );
	return $result ?: tiron_photo_failure( 'Fotografia nu mai este disponibilă. Încarcă fotografia din nou.' );
}

/** Remove known record components only. A failed removal leaves its directory eligible for retry. */
function tiron_remove_photo_record( string $path ): bool {
	if ( is_link( $path ) || ! is_dir( $path ) || $path !== realpath( $path ) ) {
		return false;
	}
	foreach ( array( 'photo.jpg', 'created' ) as $name ) {
		$file = $path . '/' . $name;
		if ( ( is_file( $file ) || is_link( $file ) ) && ! @unlink( $file ) ) {
			return false;
		}
	}
	return @rmdir( $path );
}

/** Explicit admin erasure OR rollback of a failed add-to-cart, guarded by the caller. */
function tiron_delete_photo( $id ): bool {
	if ( ! tiron_is_photo_id( $id ) ) {
		return false;
	}
	return (bool) tiron_with_photo_lock( static function ( $root ) use ( $id ) {
		$order = $root . '/orders/' . $id;
		if ( is_dir( $order ) ) {
			return tiron_remove_photo_record( $order );
		}
		$stage = tiron_private_raster( $root . '/staged/' . $id . '.jpg' );
		return $stage && unlink( $stage );
	} );
}

/** Bounded memory, oldest candidates first, independent of filesystem iteration order. */
function tiron_due_photos( string $bucket, int $cutoff, int $limit ): array {
	$root = tiron_photo_root();
	$path = $root ? $root . '/' . $bucket : '';
	if ( ! $path || ! is_dir( $path ) || is_link( $path ) || $path !== realpath( $path ) || $limit < 1 ) {
		return array();
	}
	$queue = new SplPriorityQueue();
	foreach ( new DirectoryIterator( $path ) as $entry ) {
		if ( $entry->isDot() || $entry->isLink() ) {
			continue;
		}
		$name = $entry->getFilename();
		if ( 'staged' === $bucket ) {
			// .part records left by a killed encoder go through the same age and live-lock checks.
			if ( ! $entry->isFile() || ! preg_match( '/\A([a-f0-9]{64})\.(jpg|part)\z/D', $name, $match ) ) {
				continue;
			}
			$id = $match[1];
		} else {
			if ( ! $entry->isDir() || ! tiron_is_photo_id( $name ) ) {
				continue;
			}
			$id = $name;
		}
		$modified = @filemtime( $path . '/' . $name );
		if ( false !== $modified && $modified <= $cutoff ) {
			$queue->insert( $id, array( $modified, $id ) );
			if ( $queue->count() > min( 1000, $limit ) ) {
				$queue->extract();
			}
		}
	}
	$ids = array();
	while ( ! $queue->isEmpty() ) {
		$ids[] = $queue->extract();
	}
	return array_reverse( $ids );
}

function tiron_staged_photos_before( int $cutoff, int $limit = 100 ): array {
	return tiron_due_photos( 'staged', $cutoff, $limit );
}

function tiron_order_photos_before( int $cutoff, int $limit = 100 ): array {
	return tiron_due_photos( 'orders', $cutoff, $limit );
}

function tiron_delete_staged_photo( $id, int $cutoff ): bool {
	if ( ! tiron_is_photo_id( $id ) ) {
		return false;
	}
	return (bool) tiron_with_photo_lock( static function ( $root ) use ( $id, $cutoff ) {
		foreach ( array( 'jpg', 'part' ) as $suffix ) {
			$path = $root . '/staged/' . $id . '.' . $suffix;
			clearstatcache( true, $path );
			$time = @filemtime( $path );
			if ( ! tiron_private_raster( $path ) || false === $time || $time > $cutoff ) {
				continue;
			}
			$file = @fopen( $path, 'rb' );
			if ( ! $file ) {
				return false;
			}
			try {
				return flock( $file, LOCK_EX | LOCK_NB ) && unlink( $path );
			} finally {
				fclose( $file );
			}
		}
		return false;
	} );
}

/**
 * Check current order references via the supplied WooCommerce callback while holding the lock.
 * An interrupted copy lacking a creation marker ages from its container's timestamp.
 */
function tiron_delete_order_photo( $id, int $cutoff, callable $ownership_check ): bool {
	if ( ! tiron_is_photo_id( $id ) ) {
		return false;
	}
	return (bool) tiron_with_photo_lock( static function ( $root ) use ( $id, $cutoff, $ownership_check ) {
		$path = $root . '/orders/' . $id;
		clearstatcache( true, $path );
		$time = @filemtime( $path );
		if ( false === $time || $time > $cutoff || is_link( $path ) || $path !== realpath( $path ) || ! is_dir( $path ) ) {
			return false;
		}
		$marker = $path . '/created';
		$lease  = null;
		$born   = $time;
		if ( file_exists( $marker ) ) {
			if ( ! tiron_private_raster( $marker ) ) {
				return false;
			}
			$lease = @fopen( $marker, 'rb' );
			if ( ! $lease ) {
				return false;
			}
			$born = fstat( $lease )['mtime'];
		}
		try {
			if ( $lease && ! flock( $lease, LOCK_EX | LOCK_NB ) ) {
				return false;
			}
			if ( true !== $ownership_check( $id, $born ) ) {
				// Rotate live records behind today's other due work and retry them tomorrow.
				touch( $path, min( time(), $cutoff + DAY_IN_SECONDS ) );
				return false;
			}
			return tiron_remove_photo_record( $path );
		} finally {
			if ( $lease ) {
				fclose( $lease );
			}
		}
	} );
}
