<?php
/**
 * Private cPanel Git deployment. This script lives in the clone outside the web root.
 * The two immutable Hostico package pairs decide the complete set of managed files.
 */
if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

function release_check( bool $ok, string $message ): void {
	if ( ! $ok ) {
		throw new RuntimeException( $message );
	}
}

function release_git( array $arguments ): string {
	$process = proc_open( array_merge( array( 'git' ), $arguments ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'file', '/dev/null', 'a' ) ), $pipes );
	release_check( is_resource( $process ), 'Git CLI indisponibil.' );
	$out = stream_get_contents( $pipes[1], 33 * 1024 * 1024 );
	fclose( $pipes[1] );
	release_check( 0 === proc_close( $process ) && false !== $out && strlen( $out ) < 33 * 1024 * 1024, 'Comandă Git nereușită. Verifică istoricul local, commitul și pachetele.' );
	return $out;
}

function release_sha( mixed $sha ): string {
	release_check( is_string( $sha ) && 1 === preg_match( '/^[0-9a-f]{40}$/D', $sha ), 'Introdu un SHA Git complet, de 40 de caractere.' );
	return $sha;
}

function release_dir( mixed $path ): string {
	release_check( is_string( $path ) && '/' !== $path && realpath( $path ) === $path && is_dir( $path ), 'Verifică directoarele absolute. Aliasurile și linkurile simbolice nu sunt acceptate.' );
	return $path;
}

function release_inside( string $path, string $parent ): bool {
	return $path === $parent || str_starts_with( $path, $parent . '/' );
}

function release_mkdir( string $path, int $mode = 0700 ): void {
	if ( ! is_dir( $path ) ) {
		release_check( @mkdir( $path, $mode ) && chmod( $path, $mode ), 'Crearea directorului a eșuat.' );
	}
	release_dir( $path );
}

function release_json( string $path ): array {
	release_check( realpath( $path ) === $path && is_file( $path ), 'Lipsește fișierul privat de aprobare sau stare.' );
	$result = json_decode( file_get_contents( $path ), true, 32, JSON_THROW_ON_ERROR );
	release_check( is_array( $result ), 'Fișierul JSON trebuie să conțină un obiect.' );
	return $result;
}

function release_write( string $file, string $bytes, string $private, int $mode = 0600 ): void {
	$tmp = tempnam( $private, 'write-' );
	release_check( false !== $tmp && dirname( $tmp ) === $private, 'Nu pot pregăti fișierul temporar privat.' );
	try {
		release_check( strlen( $bytes ) === file_put_contents( $tmp, $bytes ) && hash( 'sha256', $bytes ) === hash_file( 'sha256', $tmp ) && chmod( $tmp, $mode ), 'Scriere incompletă. Verifică spațiul și permisiunile.' );
		release_check( stat( $tmp )['dev'] === stat( dirname( $file ) )['dev'], 'Backup-ul și destinația trebuie să fie pe același sistem de fișiere pentru înlocuire atomică.' );
		release_check( rename( $tmp, $file ), 'Înlocuirea atomică a eșuat.' );
	} finally {
		if ( is_file( $tmp ) ) {
			unlink( $tmp );
		}
	}
}

function release_save( string $file, array $data, string $private ): void {
	release_write( $file, json_encode( $data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n", $private );
}

function release_mu_path( string $name ): bool {
	static $entries = array(
		'tir-on-catalog.php', 'tir-on-hostico.php', 'tir-on-local-preview.php', 'tir-on-profit.php',
		'tiron/admin-photos.php', 'tiron/cart-orders.php', 'tiron/private-photos.php', 'tiron/test-payments.php',
		'tiron/atelier.php', 'tiron/atelier.css', 'tiron/profit-calculator.php', 'tiron/profit.css',
		'tiron/hostico/emails.php', 'tiron/hostico/seed.php',
	);
	return in_array( $name, $entries, true );
}

function release_member( string $name, bool $theme ): string {
	if ( $theme ) {
		release_check( 1 === preg_match( '~^tir-on/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_-]+\.(?:php|css|js|txt|webp|jpg|png|svg|woff2)$~D', $name ), 'Fișier nepermis în pachetul temei.' );
		return 'themes/' . $name;
	}
	release_check( release_mu_path( $name ), 'Fișier MU necunoscut. Este necesară revizuirea listei permise.' );
	return 'mu-plugins/' . $name;
}

function release_packages( string $sha, string $private ): array {
	$entries = array();
	$prefix = $sha . ':dist/hostico/';
	$checks = release_git( array( 'show', $prefix . 'SHA256SUMS' ) );
	$total = 0;
	foreach ( array( 'tir-on-theme.zip', 'tir-on-mu-plugins.zip' ) as $name ) {
		$data = release_git( array( 'show', $prefix . $name ) );
		release_check( 1 === preg_match( '/^' . hash( 'sha256', $data ) . '  ' . preg_quote( $name, '/' ) . '$/m', $checks ), 'Suma SHA-256 a arhivei este incorectă.' );
		$tmp = tempnam( $private, 'package-' );
		release_check( false !== $tmp && dirname( $tmp ) === $private, 'Nu pot pregăti arhiva în spațiul privat.' );
		try {
			release_check( strlen( $data ) === file_put_contents( $tmp, $data ), 'Arhivă incompletă pe disc.' );
			$zip = new ZipArchive();
			release_check( true === $zip->open( $tmp, ZipArchive::RDONLY | ZipArchive::CHECKCONS ) && $zip->numFiles <= 1024, 'Arhivă invalidă sau prea mare.' );
			try {
				for ( $i = 0; $i < $zip->numFiles; $i++ ) {
					$stat = $zip->statIndex( $i );
					release_check( is_array( $stat ), 'Intrare ZIP invalidă.' );
					$key = release_member( $stat['name'], 'tir-on-theme.zip' === $name );
					$total += $stat['size'];
					release_check( ! isset( $entries[$key] ) && $stat['size'] <= 16 * 1024 * 1024 && $total <= 32 * 1024 * 1024, 'Intrări ZIP duplicate sau peste limita de spațiu.' );
					release_check( $zip->getExternalAttributesIndex( $i, $os, $attr ) && ( ZipArchive::OPSYS_UNIX !== $os || 0100000 === ( ( $attr >> 16 ) & 0170000 ) ), 'Arhiva conține un fișier special.' );
					$bytes = $zip->getFromIndex( $i );
					release_check( is_string( $bytes ) && strlen( $bytes ) === $stat['size'], 'Conținut ZIP incomplet.' );
					$entries[$key] = $bytes;
				}
			} finally {
				$zip->close();
			}
		} finally {
			unlink( $tmp );
		}
	}
	foreach ( array( 'themes/tir-on/style.css', 'themes/tir-on/functions.php', 'mu-plugins/tir-on-hostico.php', 'mu-plugins/tir-on-profit.php', 'mu-plugins/tiron/atelier.php', 'mu-plugins/tiron/test-payments.php', 'mu-plugins/tiron/private-photos.php' ) as $required ) {
		release_check( isset( $entries[$required] ), 'Lipsește o componentă obligatorie din pachet.' );
	}
	return $entries;
}

// Check every existing component of the path, including dangling symlinks.
function release_target( string $content, string $key, bool $create = false ): string {
	if ( str_starts_with( $key, 'themes/' ) ) {
		release_member( substr( $key, 7 ), true );
	} else {
		release_check( str_starts_with( $key, 'mu-plugins/' ), 'Destinație nepermisă.' );
		release_member( substr( $key, 11 ), false );
	}
	$parts = explode( '/', $key );
	$file = array_pop( $parts );
	$dir = $content;
	foreach ( $parts as $part ) {
		$dir .= '/' . $part;
		release_check( ! is_link( $dir ), 'Un director gestionat este un link simbolic.' );
		if ( $create ) {
			release_mkdir( $dir, 0755 );
		} else {
			release_check( ! file_exists( $dir ) || is_dir( $dir ), 'O destinație nu este director.' );
		}
	}
	$dest = $dir . '/' . $file;
	release_check( ! is_link( $dest ) && ( ! file_exists( $dest ) || is_file( $dest ) ), 'O destinație este un link sau un fișier special.' );
	return $dest;
}

function release_digest( string $filename ): ?string {
	if ( ! file_exists( $filename ) ) {
		return null;
	}
	$hash = hash_file( 'sha256', $filename );
	release_check( is_string( $hash ), 'Nu pot verifica un fișier instalat.' );
	return $hash;
}

function release_plan( array $old, array $new, string $content ): array {
	$changes = array();
	foreach ( array_keys( $old + $new ) as $key ) {
		$from = isset( $old[$key] ) ? hash( 'sha256', $old[$key] ) : null;
		$to = isset( $new[$key] ) ? hash( 'sha256', $new[$key] ) : null;
		$file = release_target( $content, $key );
		release_check( release_digest( $file ) === $from, 'Instalarea diferă de versiunea declarată la ' . $key . '. Actualizarea a fost oprită.' );
		if ( $from !== $to ) {
			$changes[$key] = array( 'from' => $from, 'to' => $to, 'mode' => null === $from ? 0644 : ( fileperms( $file ) & 0777 ) );
		}
	}
	ksort( $changes );
	return $changes;
}

function release_backup_id( mixed $id ): string {
	release_check( is_string( $id ) && 1 === preg_match( '/^[0-9]{8}T[0-9]{6}-[a-f0-9]{12}$/D', $id ), 'Identificator de backup invalid.' );
	return $id;
}

function release_rollback( string $id, string $private, string $content ): void {
	$folder = release_dir( $private . '/backups/' . release_backup_id( $id ) );
	$journal = release_json( $folder . '/journal.json' );
	release_check( $journal['content'] === $content, 'Backup-ul aparține altei instalări.' );
	foreach ( $journal['changes'] as $key => $change ) {
		$file = release_target( $content, $key );
		$now = release_digest( $file );
		release_check( $change['from'] === $now || $change['to'] === $now, 'Un fișier diferă și de backup, și de versiunea aprobată: ' . $key );
		if ( null !== $change['from'] ) {
			release_check( $change['from'] === release_digest( $folder . '/' . $key ), 'Backup nevalid: ' . $key );
		}
	}
	release_save( $private . '/pending.json', array( 'backup' => $id ), $private );
	foreach ( array_reverse( $journal['changes'], true ) as $key => $change ) {
		$file = release_target( $content, $key );
		if ( $change['from'] === release_digest( $file ) ) {
			continue;
		}
		if ( null === $change['from'] ) {
			release_check( unlink( $file ), 'Nu pot retrage fișierul adăugat.' );
		} else {
			release_write( $file, file_get_contents( $folder . '/' . $key ), $private, $change['mode'] );
		}
		release_check( $change['from'] === release_digest( $file ), 'Restaurare incompletă: ' . $key );
	}
	release_save( $private . '/state.json', $journal['previous'], $private );
	release_check( unlink( $private . '/pending.json' ), 'Verifică jurnalul privat înainte de următoarea actualizare.' );
	echo "Revenire verificată. Commit instalat: " . $journal['previous']['commit'] . "\n";
}

function release_apply( array $plan, array $new, array $state, string $private ): void {
	$content = $plan['content'];
	$id = gmdate( 'Ymd\THis' ) . '-' . bin2hex( random_bytes( 6 ) );
	$folder = $private . '/backups/' . $id;
	release_mkdir( $private . '/backups' );
	release_mkdir( $folder );
	foreach ( $plan['changes'] as $key => $change ) {
		if ( null === $change['from'] ) {
			continue;
		}
		$bytes = file_get_contents( release_target( $content, $key ) );
		release_check( is_string( $bytes ) && $change['from'] === hash( 'sha256', $bytes ), 'Fișierul s-a modificat în timpul backup-ului.' );
		$segments = explode( '/', dirname( $key ) );
		$dir = $folder;
		foreach ( $segments as $segment ) {
			$dir .= '/' . $segment;
			release_mkdir( $dir );
		}
		release_write( $folder . '/' . $key, $bytes, $private );
	}
	release_save( $folder . '/journal.json', array_merge( $plan, array( 'previous' => $state ) ), $private );
	release_save( $private . '/pending.json', array( 'backup' => $id ), $private );
	echo 'Backup verificat: ' . $id . "\n";
	try {
		foreach ( $plan['changes'] as $key => $change ) {
			$file = release_target( $content, $key, null !== $change['to'] );
			release_check( $change['from'] === release_digest( $file ), 'Fișier modificat de un alt proces: ' . $key );
			if ( null === $change['to'] ) {
				release_check( unlink( $file ), 'Nu pot elimina fișierul vechi gestionat.' );
			} else {
				release_write( $file, $new[$key], $private, $change['mode'] );
			}
			release_check( $change['to'] === release_digest( $file ), 'Verificarea după instalare a eșuat.' );
		}
		release_save( $private . '/state.json', array( 'commit' => $plan['commit'], 'content' => $content, 'backup' => $id ), $private );
		release_check( unlink( $private . '/pending.json' ), 'Finalizare incompletă. Verifică starea privată.' );
	} catch ( Throwable $error ) {
		release_rollback( $id, $private, $content );
		throw $error;
	}
	echo 'Instalare verificată: ' . $plan['commit'] . "\n";
}

function release_run(): void {
	umask( 0077 );
	$home = release_dir( getenv( 'HOME' ) );
	$private = $home . '/tiron-release-control';
	// cPanel can invoke a deployment task on push. Missing one-use approval is a no-op.
	if ( ! is_file( $private . '/request.json' ) ) {
		echo "Nicio solicitare aprobată. Niciun fișier al magazinului modificat.\n";
		return;
	}
	release_dir( $private );
	release_check( 0 === ( fileperms( $private ) & 0077 ) && ( ! function_exists( 'posix_geteuid' ) || fileowner( $private ) === posix_geteuid() ), 'Directorul privat trebuie deținut de utilizatorul cPanel, cu permisiuni 0700.' );
	release_check( ! is_link( $private . '/release.lock' ), 'Lock privat invalid.' );
	$lock = fopen( $private . '/release.lock', 'c' );
	release_check( is_resource( $lock ) && flock( $lock, LOCK_EX | LOCK_NB ), 'Altă operațiune este în curs.' );
	try {
		$request = release_json( $private . '/request.json' );
		release_check( unlink( $private . '/request.json' ), 'Nu pot consuma solicitarea de unică folosință.' );
		$repo = release_dir( trim( release_git( array( 'rev-parse', '--show-toplevel' ) ) ) );
		release_check( getcwd() === $repo && dirname( __DIR__, 3 ) === $repo && release_inside( $repo, $home ), 'Rulează din copia privată cPanel a proiectului.' );
		$remote = trim( release_git( array( 'remote', 'get-url', 'origin' ) ) );
		release_check( in_array( $remote, array( 'git@github.com:tirawwwn-boop/tir-on-website.git', 'ssh://git@github.com/tirawwwn-boop/tir-on-website.git', 'https://github.com/tirawwwn-boop/tir-on-website.git' ), true ), 'Originea Git trebuie să fie repository-ul oficial.' );
		$head = release_sha( $request['approved_commit'] ?? '' );
		release_check( $head === trim( release_git( array( 'rev-parse', 'HEAD' ) ) ), 'HEAD local diferă de commitul aprobat.' );
		release_check( '' === trim( release_git( array( 'status', '--porcelain', '--untracked-files=no' ) ) ), 'Copia privată are modificări locale.' );
		$root = release_dir( $request['wordpress_root'] ?? null );
		release_check( release_inside( $root, $home ) && is_file( $root . '/wp-config.php' ) && is_file( $root . '/wp-includes/version.php' ), 'Verifică rădăcina instalării WordPress existente.' );
		$content = release_dir( $root . '/wp-content' );
		release_dir( $content . '/themes/tir-on' );
		release_dir( $content . '/mu-plugins' );
		$public = $request['public_roots'] ?? null;
		release_check( is_array( $public ) && in_array( $root, $public, true ), 'Enumeră rădăcina WordPress și toate celelalte rădăcini web din cPanel Domains.' );
		if ( is_dir( $home . '/public_html' ) ) {
			$public[] = realpath( $home . '/public_html' );
		}
		foreach ( $public as $path ) {
			$path = release_dir( $path );
			foreach ( array( $private, $repo ) as $protected ) {
				release_check( ! release_inside( $protected, $path ) && ! release_inside( $path, $protected ), 'Copia Git și backup-urile trebuie izolate de toate rădăcinile web.' );
			}
		}
		release_check( ! release_inside( $private, $repo ) && ! release_inside( $repo, $private ), 'Separă copia Git de directorul de backup.' );
		$action = $request['action'] ?? '';
		$pending = is_file( $private . '/pending.json' ) ? release_json( $private . '/pending.json' ) : null;
		$state = is_file( $private . '/state.json' ) ? release_json( $private . '/state.json' ) : null;
		release_check( ! $state || $state['content'] === $content, 'Un director de control deservește o singură instalare.' );
		if ( 'rollback' === $action ) {
			$id = release_backup_id( $request['backup_id'] ?? '' );
			release_check( $id === ( $pending['backup'] ?? $state['backup'] ?? null ), 'Backup-ul cerut nu corespunde instalării curente.' );
			release_rollback( $id, $private, $content );
			return;
		}
		release_check( ! $pending, 'Tranzacție întreruptă. Consultă pending.json și rulează rollback.' );
		release_check( in_array( $action, array( 'plan', 'apply' ), true ), 'Acțiune necunoscută.' );
		$base = release_sha( $request['installed_commit'] ?? '' );
		release_check( ! $state || $state['commit'] === $base, 'Commitul instalat diferă de starea salvată.' );
		release_git( array( 'merge-base', '--is-ancestor', $base, $head ) );
		$new = release_packages( $head, $private );
		$changes = release_plan( release_packages( $base, $private ), $new, $content );
		$plan = array( 'commit' => $head, 'base' => $base, 'content' => $content, 'changes' => $changes );
		if ( 'plan' === $action ) {
			release_save( $private . '/plan.json', $plan, $private );
			echo 'Plan verificat, ' . count( $changes ) . " fișiere. Consultă plan.json înainte de aprobare.\n";
			return;
		}
		release_check( $plan === release_json( $private . '/plan.json' ), 'Solicită un plan nou și verifică diferențele înainte de aplicare.' );
		release_check( unlink( $private . '/plan.json' ), 'Nu pot consuma planul aprobat.' );
		if ( empty( $changes ) && $head === $base ) {
			echo "Toate fișierele sunt deja verificate la acest commit.\n";
			return;
		}
		release_apply( $plan, $new, $state ?? array( 'commit' => $base, 'content' => $content, 'backup' => null ), $private );
	} finally {
		flock( $lock, LOCK_UN );
		fclose( $lock );
	}
}

try {
	release_run();
} catch ( Throwable $error ) {
	fwrite( STDERR, "TIR-ON: " . $error->getMessage() . "\n" );
	exit( 1 );
}
