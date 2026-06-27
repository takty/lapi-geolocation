<?php
/**
 *
 * Access Control
 *
 * @author Takuto Yanagida
 * @version 2026-06-27
 *
 */

function is_request_allowed(array $allowed_hosts): bool {
	$orig = $_SERVER['HTTP_ORIGIN'] ?? null;
	if ($orig !== null && $orig !== '') {
		return is_origin_allowed($orig, $allowed_hosts);
	}
	return is_origin_allowed($_SERVER['HTTP_REFERER'] ?? null, $allowed_hosts);
}

function is_origin_allowed(?string $orig, array $allowed_hosts): bool {
	if ($orig === null || $orig === '') {
		return false;
	}
	$u = parse_url($orig);
	if ($u === false) {
		return false;
	}
	return ($u['scheme'] ?? '') === 'https' && in_array($u['host'] ?? '', $allowed_hosts, true);
}

function is_user_agent_expected(array $expected_uas): bool {
	$ps = explode(' ', $_SERVER['HTTP_USER_AGENT'] ?? '');
	$ms = 0;
	foreach ($ps as $p) {
		foreach ($expected_uas as $e) {
			if (strpos($p, $e) === 0) $ms += 1;
		}
	}
	return $ms === count($expected_uas);
}

function send_cors_headers() {
	$orig = $_SERVER['HTTP_ORIGIN'] ?? null;

	if ($orig !== null && $orig !== '') {
		header("Access-Control-Allow-Origin: $orig");
		header("Vary: Origin");
		header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, X-CSRF-Token");
		header("Access-Control-Allow-Credentials: true");
	}
	if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
		http_response_code(204);
		exit;
	}
}
