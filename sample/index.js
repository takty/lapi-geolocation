/**
 * Script for Sample
 *
 * @author Takuto Yanagida
 * @version 2026-06-27
 */

document.addEventListener('DOMContentLoaded', () => {
	const opts = {
		enableHighAccuracy: false,
		timeout           : 8000,
		maximumAge        : 2000,
	};

	setGeolocationButton("fetch", "result", (res, rej) => {
		getCurrentPosition(res, rej);
	});

	setGeolocationButton("fetch-n", "result-n", (res, rej) => {
		navigator.geolocation.getCurrentPosition(res, rej);
	});

	function setGeolocationButton(btnId, outId, fn) {
		const btn = document.getElementById(btnId);
		const out = document.getElementById(outId);

		btn.addEventListener('click', async () => {
			try {
				const pos = await new Promise((res, rej) => {
					fn(res, rej, opts);
				});
				out.value = `latitude: ${pos.coords.latitude.toFixed(4)}, longitude: ${pos.coords.longitude.toFixed(4)}`;
			} catch (e) {
				throw new Error('Geolocation cannot be captured.');
			}
		});
	}
});

function getCurrentPosition(success, error, _) {
	fetch('https://takty.net/api/geolocation/v1/', {
		mode       : 'cors',
		cache      : 'no-cache',
		credentials: 'same-origin',
		headers    : { 'Content-Type': 'application/json; charset=utf-8', },
		referrer   : 'no-referrer',
	}).then(response => {
		return response.json();
	}).then(r => {
		success({
			coords: {
				latitude        : r.lat,
				longitude       : r.lon,
				altitude        : null,
				accuracy        : 0,
				altitudeAccuracy: null,
				heading         : null,
				speed           : null,
			},
			timestamp: null,
		})
	}).catch(e => {
		if (error) error({ code: 2, message: e.message });
	});
}
