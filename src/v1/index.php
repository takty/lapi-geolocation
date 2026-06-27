<?php
/**
 *
 * Geolocation API
 *
 * @author Takuto Yanagida
 * @version 2026-06-27
 *
 */

require "access-control.php";

$allowed_hosts = [
	'takty.net',
];

$expected_uas = [
	'Croqujs/',
	'Electron/',
];

const OWNER = 'takty';

$is_allowed_hosts = is_request_allowed($allowed_hosts);

if (!$is_allowed_hosts && !is_user_agent_expected($expected_uas)) {
	http_response_code(404);
	return;
}
if ($is_allowed_hosts) {
	send_cors_headers();
}


// -----------------------------------------------------------------------------


$ip = $_SERVER['REMOTE_ADDR'];

clean_cache();
$loc = read_cache($ip);
if ($loc === null) {
	$loc = get_location($ip);
	if ($loc !== null) {
		write_cache($ip, $loc);
	}
}

if ($loc === null) {
	http_response_code(502);
}
header('Content-Type: application/json; charset=UTF-8');
echo json_encode($loc);


// -----------------------------------------------------------------------------


function clean_cache(): void {
	$dir = __DIR__ . '/cache/';
	if (!file_exists($dir)) return;

	$today = new DateTime(date('Ymd'));
	$ps    = scandir($dir);
	if ($ps === false) return;

	foreach ($ps as $p) {
		if ($p[0] === '.') continue;
		if (!preg_match('/^\d{8}$/', $p)) continue;

		$d = $dir . $p;
		if (!is_dir($d)) continue;

		$date = DateTime::createFromFormat('Ymd', $p);
		if ($date === false) continue;

		$diff = $today->diff($date);
		if (7 < $diff->days) {
			remove_all($d);
		}
	}
}

function read_cache(string $ip): ?array {
	$dir = __DIR__ . '/cache/';
	if (!file_exists($dir)) return null;

	$fn = ip2hex($ip);
	if ($fn === null) return null;

	$ps = scandir($dir, SCANDIR_SORT_DESCENDING);
	if ($ps === false) return null;

	foreach ($ps as $p) {
		if ($p[0] === '.') continue;
		$d = $dir . $p . '/';

		if (file_exists($d . $fn)) {
			$c = file_get_contents($d . $fn);
			return json_decode($c, true);
		}
	}
	return null;
}

function write_cache(string $ip, array $loc): void {
	$today = new DateTime(date('Ymd'));
	$dir   = __DIR__ . '/cache/' . $today->format('Ymd');

	if (!file_exists($dir)) {
		$s = mkdir($dir, 0775, true);
		if ($s) {
			chmod($dir, 0775);
			chown($dir, OWNER);
		}
	}
	if (!file_exists($dir)) return;

	$fn = ip2hex($ip);
	if ($fn === null) return;

	$path = $dir . '/' . $fn;
	file_put_contents($path, json_encode($loc), LOCK_EX);
	chown($path, OWNER);
}


// -----------------------------------------------------------------------------


function get_location(string $ip): ?array {
	$url = "http://ip-api.com/json/$ip?fields=status,lat,lon";

	$cont = file_get_contents($url);
	if ($cont === false) return null;

	$raw = json_decode($cont, true);
	if ($raw === null) return null;

	if (!isset($raw['status']) || $raw['status'] !== 'success') {
		return null;
	}
	$res = [];
	$res['lat'] = round($raw['lat']);
	$res['lon'] = round($raw['lon']);
	return $res;
}

function ip2hex(string $ip): ?string {
	if (strpos($ip, ',') !== false) {
		$ts = explode(',', $ip);
		$ip = trim($ts[0]);
	}
	$is_v6 = false;
	$is_v4 = false;
	if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
		$is_v6 = true;
	} else if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
		$is_v4 = true;
	}
	if (!$is_v4 && !$is_v6) return null;

	if ($is_v4) {
		$ps = explode('.', $ip);
		for ($i = 0; $i < 4; $i += 1) {
			$ps[$i] = str_pad(dechex($ps[$i]), 2, '0', STR_PAD_LEFT);
		}
		$ip  = '::' . $ps[0] . $ps[1] . ':' . $ps[2] . $ps[3];
		$hex = join('', $ps);
	} else {
		$ps = explode(':', $ip);
		if (filter_var($ps[count($ps) - 1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
			$ps_v4 = explode('.', $ps[count($ps) - 1]);
			for ($i = 0; $i < 4; $i += 1) {
				$ps_v4[$i] = str_pad(dechex($ps_v4[$i]), 2, '0', STR_PAD_LEFT);
			}
			$ps[count($ps) - 1] = $ps_v4[0] . $ps_v4[1];
			$ps[] = $ps_v4[2] . $ps_v4[3];
		}
		$ps_ex = [];
		$is_expanded = false;
		foreach ($ps as $p) {
			if (!$is_expanded && $p == '') {
				for ($i = 0; $i <= (8 - count($ps)); $i += 1) $ps_ex[] = '0000';
				$is_expanded = true;
			} else {
				$ps_ex[] = $p;
			}
		}
		foreach ($ps_ex as &$p) {
			$p = str_pad($p, 4, '0', STR_PAD_LEFT);
		}
		$ip  = join(':', $ps_ex);
		$hex = join('', $ps_ex);
	}
	if (!filter_var($ip, FILTER_VALIDATE_IP)) return null;
	return strtolower(str_pad($hex, $is_v4 ? 8 : 32, '0', STR_PAD_LEFT));
}


// -----------------------------------------------------------------------------


function remove_all(string $dir): void {
	$ps = scandir($dir);
	if ($ps === false) return;

	foreach ($ps as $p) {
		if ($p[0] === '.') continue;
		if (is_dir($dir . '/' . $p)) {
			remove_all($dir . '/' . $p);
		} else {
			var_dump($dir . '/' . $p);
			unlink($dir . '/' . $p);
		}
	}
	rmdir($dir);
}
