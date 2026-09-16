document.addEventListener('DOMContentLoaded', () => {
	document.querySelectorAll('.dmbc-songlist-playlist-download').forEach((link) => {
		const rawUrls = link.dataset.playlistUrls;
		if (!rawUrls) {
			return;
		}

		let urls;
		try {
			urls = JSON.parse(rawUrls);
		} catch (_error) {
			return;
		}

		if (!Array.isArray(urls) || urls.length === 0) {
			return;
		}

		const playlistUrls = urls.map((url) => new URL(url, window.location.origin).href);
		const playlist = ['#EXTM3U', ...playlistUrls].join('\n') + '\n';
		const blob = new Blob([playlist], { type: 'audio/x-mpegurl;charset=utf-8' });
		link.href = URL.createObjectURL(blob);
	});
});
