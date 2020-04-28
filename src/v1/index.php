<?php
/**
 *
 * Geolocation API
 *
 * @author Takuto Yanagida
 * @version 2020-04-28
 *
 */


if ( ! is_user_agent_ok( $_SERVER['HTTP_USER_AGENT'] ) ) {
	header("HTTP/1.0 404 Not Found");
	return;
}
$ip = $_SERVER['REMOTE_ADDR'];

clean_cache();
$loc = read_cache( $ip );
if ( $loc === null ) {
	$loc = get_location( $ip );
	write_cache( $ip, $loc );
}

header( 'Content-Type: text/html; charset=UTF-8' );
echo json_encode( $loc );


// -----------------------------------------------------------------------------


function clean_cache() {
	$today = new DateTime( date( 'Ymd' ) );
	$dir = __DIR__ . '/cache/';
	$ps = scandir( $dir );
	foreach ( $ps as $p ) {
		if ( $p[0] === '.' ) continue;
		$d = $dir . $p;
		$date = new DateTime( $p );
		$diff = $today->diff( $date );
		if ( 7 < $diff->days ) {
			remove_all( $d );
		}
	}
}

function read_cache( $ip ) {
	$fn = ip2hex( $ip );
	$today = new DateTime( date( 'Ymd' ) );
	$dir = __DIR__ . '/cache/';
	$ps = scandir( $dir, SCANDIR_SORT_DESCENDING );
	foreach ( $ps as $p ) {
		if ( $p[0] === '.' ) continue;
		$d = $dir . $p . '/';
		if ( file_exists( $d . $fn ) ) {
			$c = file_get_contents( $d . $fn );
			return json_decode( $c );
		}
	}
	return null;
}

function write_cache( $ip, $loc ) {
	$today = new DateTime( date( 'Ymd' ) );
	$dir = __DIR__ . '/cache/' . $today->format( 'Ymd' );
	if ( ! file_exists( $dir ) ) {
		$s = mkdir( $dir, 0775, true );
		if ( $s ) {
			chmod( $dir, 0775 );
			chown( $dir, 'laccolla' );
		}
	}
	if ( ! file_exists( $dir ) ) return false;

	$fn = ip2hex( $ip );
	$path = $dir . '/' . $fn;
	file_put_contents( $path, json_encode( $loc ), LOCK_EX );
	chown( $path, 'laccolla' );
}


// -----------------------------------------------------------------------------


function get_location( $ip ) {
	$url = "http://ip-api.com/json/$ip?fields=status,lat,lon";

	$cont = file_get_contents( $url );
	if ( $cont === false ) return null;
	$raw = json_decode( $cont, true );
	if ( $raw === null ) return null;
	if ( ! isset( $raw['status'] ) || $raw['status'] !== 'success' ) return null;

	$res = [];
	$res['lat'] = round( $raw['lat'] );
	$res['lon'] = round( $raw['lon'] );
	return $res;
}

function ip2hex( $ip ) {
	if ( strpos( $ip, ',' ) !== false ) {
		$ts = explode( ',', $ip );
		$ip = trim( $ts[0] );
	}
	$is_v6 = false;
	$is_v4 = false;
	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) !== false ) {
		$is_v6 = true;
	} else if( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) !== false ) {
		$is_v4 = true;
	}
	if ( ! $is_v4 && ! $is_v6 ) return false;

	if ( $is_v4 ) {
		$ps = explode( '.', $ip );
		for ( $i = 0; $i < 4; $i += 1 ) {
			$ps[ $i ] = str_pad( dechex( $ps[ $i ] ), 2, '0', STR_PAD_LEFT );
		}
		$ip = '::' . $ps[0] . $ps[1] . ':' . $ps[2] . $ps[3];
		$hex = join( '', $ps );
	} else {
		$ps = explode( ':', $ip );
		if ( filter_var( $ps[ count( $ps ) - 1 ], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) !== false ) {
			$ps_v4 = explode( '.', $ps[ count( $ps ) - 1 ] );
			for ( $i = 0; $i < 4; $i += 1 ) {
				$ps_v4[ $i ] = str_pad( dechex( $ps_v4[ $i ] ), 2, '0', STR_PAD_LEFT );
			}
			$ps[ count( $ps ) - 1 ] = $ps_v4[0] . $ps_v4[1];
			$ps[] = $ps_v4[2] . $ps_v4[3];
		}
		$ps_ex = [];
		$is_expanded = false;
		foreach ( $ps as $p ) {
			if ( ! $is_expanded && $p == '' ) {
				for ( $i = 0; $i <= ( 8 - count( $ps ) ); $i += 1 ) $ps_ex[] = '0000';
				$is_expanded = true;
			} else {
				$ps_ex[] = $p;
			}
		}
		foreach ( $ps_ex as &$p ) {
			$p = str_pad( $p, 4, '0', STR_PAD_LEFT );
		}
		$ip = join( ':', $ps_ex );
		$hex = join( '', $ps_ex );
	}
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) return false;
	return strtolower( str_pad( $hex, $is_v4 ? 8 : 32, '0', STR_PAD_LEFT ) );
}


// -----------------------------------------------------------------------------


function remove_all( $dir ) {
	$ps = scandir( $dir );
	foreach ( $ps as $p ) {
		if ( $p[0] === '.' ) continue;
		if ( is_dir( $dir . '/' . $p ) ) {
			remove_all( $dir . '/' . $p );
		} else {
			var_dump( $dir . '/' . $p );
			unlink( $dir . '/' . $p );
		}
	}
	rmdir( $dir );
}

function is_user_agent_ok( $ua ) {
	$ps = explode( ' ', $ua );
	$ms = 0;
	foreach ( $ps as $p ) {
		if ( strpos( $p, 'Croqujs/' ) === 0 ) $ms += 1;
		if ( strpos( $p, 'Electron/' ) === 0 ) $ms += 1;
	}
	return $ms === 2;
}
