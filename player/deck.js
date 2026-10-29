/* =========================================================================
   AdaptExec deck player v2
   window.AXD = {
     deck | deckUrl, slug, mode: 'rep' | 'share', token, ping, prospect,
     assets (base url for relative images), logo, cta: { url, label, rep,
     repLine, phone, email, contact }
   }
   Keys: arrows / space / PageUp-Down (clickers), Home, End, O overview,
   F full screen, P presenter (rep), N notes (rep), B black, Esc.
   ========================================================================= */
(function () {
	'use strict';

	var C = window.AXD || {};
	var REP = C.mode !== 'share';
	var PRES = REP && /presenter/.test(location.hash);
	var D = null, N = 0, cur = 0, frag = 0;
	var main = null, pvCur = null, pvNext = null;
	var portrait = false;
	var auto = null;
	var chan = null, quiet = false;
	var pinged = {};

	/* ------------------------------------------------------------ helpers */
	function esc(s) {
		return String(s === null || s === undefined ? '' : s)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	}
	/* Deck copy may carry <em>, <strong> and <br>; everything else is text. */
	function rich(s) {
		return esc(s).replace(/&lt;(\/?)(em|strong|b)&gt;/g, '<$1$2>').replace(/&lt;br\s*\/?&gt;/g, '<br>');
	}
	function src(p) {
		if (!p) return '';
		if (/^(https?:)?\/\//.test(p) || p.charAt(0) === '/' || /^data:/.test(p)) return p;
		return (C.assets || '') + p;
	}
	function $(sel, root) { return (root || document).querySelector(sel); }
	function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
	function el(tag, cls, html) {
		var n = document.createElement(tag);
		if (cls) n.className = cls;
		if (html !== undefined) n.innerHTML = html;
		return n;
	}
	function list(a) { return Array.isArray(a) ? a : []; }
	/* Motion: muted, looping clips that sit over a still image and only load and play on the slide that is showing. */
	var RM = false;
	try { RM = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches); } catch (e) {}
	function mv(u, lite) {
		return (u && !lite) ? '<video class="axd-mv" muted loop playsinline preload="none" aria-hidden="true" tabindex="-1" data-src="' + esc(src(u)) + '"></video>' : '';
	}

	var P = {
		globe: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
		form: '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
		user: '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
		users: '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c1-3.5 3.5-5.5 6.5-5.5s5.5 2 6.5 5.5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5c2 .8 3.2 2.6 3.8 5.5"/>',
		bolt: '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
		chart: '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
		bell: '<path d="M6 16V11a6 6 0 1 1 12 0v5l2 2H4z"/><path d="M10 21h4"/>',
		msg: '<path d="M4 5h16v11H9l-5 4z"/>',
		cal: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
		check: '<path d="m5 12 4.5 4.5L19 7"/>',
		star: '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
		home: '<path d="M3 11 12 4l9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>',
		car: '<path d="M5 16V11l2-5h10l2 5v5"/><path d="M3 16h18v3H3z"/><circle cx="7.5" cy="16" r="1.5"/><circle cx="16.5" cy="16" r="1.5"/>',
		wrench: '<path d="M14.5 6.5a4 4 0 0 0 5 5L12 19a2.1 2.1 0 0 1-3-3l7.5-7.5a4 4 0 0 0-2-2z"/>',
		scissors: '<circle cx="6" cy="7" r="3"/><circle cx="6" cy="17" r="3"/><path d="M8.5 8.5 20 19M8.5 15.5 20 5"/>',
		scale: '<path d="M12 3v18M5 21h14M4 7h16"/><path d="m4 7-2.5 6a3 3 0 0 0 5 0zM20 7l-2.5 6a3 3 0 0 0 5 0z"/>',
		dumbbell: '<path d="M3 9v6M6 6v12M18 6v12M21 9v6M6 12h12"/>',
		food: '<path d="M7 3v8M5 3v5a2 2 0 0 0 4 0V3M7 11v10M17 3c-2 1.5-3 4-3 7h3v11"/>',
		drop: '<path d="M12 3s7 7.5 7 12a7 7 0 0 1-14 0c0-4.5 7-12 7-12z"/>',
		ai: '<path d="M12 3v3M12 18v3M3 12h3M18 12h3M6 6l2 2M16 16l2 2M6 18l2-2M16 8l2-2"/><circle cx="12" cy="12" r="3.5"/>',
		spark: '<path d="M12 3c.6 4.2 2.8 6.4 7 7-4.2.6-6.4 2.8-7 7-.6-4.2-2.8-6.4-7-7 4.2-.6 6.4-2.8 7-7z"/><path d="M19 15c.3 1.7 1.2 2.6 3 3-1.8.4-2.7 1.3-3 3-.3-1.7-1.2-2.6-3-3 1.8-.4 2.7-1.3 3-3z"/>',
		lock: '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
		phone: '<path d="M5 3h4l2 5-2.5 1.5a11 11 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2 2A17 17 0 0 1 3 5a2 2 0 0 1 2-2z"/>',
		mail: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
		dollar: '<path d="M12 2v20M17 6H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
		map: '<path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
		clip: '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V3h6v1M9 10h6M9 14h6M9 18h3"/>',
		link: '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
		sync: '<path d="M20 7h-9a6 6 0 0 0 0 12h1M4 17h9a6 6 0 0 0 0-12h-1"/><path d="m17 4 3 3-3 3M7 20l-3-3 3-3"/>',
		shield: '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
		eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
		clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		file: '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 13h6M9 17h6"/>',
		camera: '<path d="M4 7h3l2-3h6l2 3h3v13H4z"/><circle cx="12" cy="13" r="3.5"/>',
		heart: '<path d="M12 20s-8-4.7-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 9c0 6.3-8 11-8 11z"/>',
		sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5 19 19M5 19l1.5-1.5M17.5 6.5 19 5"/>',
		building: '<path d="M4 21V5l8-2v18M12 7l8 3v11M2 21h20M7 8h2M7 12h2M7 16h2M15 13h2M15 17h2"/>',
		church: '<path d="M12 2v5M10 4h4M6 21v-8l6-5 6 5v8M3 21h18M10 21v-4h4v4"/>',
		key: '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M17 6l3 3M14 9l2 2"/>',
		truck: '<path d="M2 6h12v10H2zM14 10h4l4 4v2h-8"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>',
		play: '<path d="M7 4v16l13-8z" fill="currentColor"/>',
		left: '<path d="m15 18-6-6 6-6"/>',
		right: '<path d="m9 18 6-6-6-6"/>',
		grid: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
		full: '<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/>',
		print: '<path d="M6 9V3h12v6M6 18H4v-7h16v7h-2"/><rect x="6" y="14" width="12" height="7"/>',
		notes: '<path d="M5 4h14v16H5z"/><path d="M9 9h6M9 13h6M9 17h4"/>',
		screen: '<rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/>',
		ext: '<path d="M14 4h6v6M20 4l-9 9M18 14v6H4V6h6"/>',
		hand: '<path d="M8 13V5.5a1.5 1.5 0 0 1 3 0V12M11 11V4.5a1.5 1.5 0 0 1 3 0V12M14 11.5V6a1.5 1.5 0 0 1 3 0v8c0 4-2.5 7-6 7-2.5 0-4-1-5.5-3L3 14.5a1.5 1.5 0 0 1 2.3-2L8 15"/>',
		x: '<path d="M6 6l12 12M18 6 6 18"/>',
		arrow: '<path d="M4 12h16M14 6l6 6-6 6"/>',
		route: '<circle cx="6" cy="18" r="2.5"/><circle cx="18" cy="6" r="2.5"/><path d="M8.5 18H15a3 3 0 0 0 0-6H9a3 3 0 0 1 0-6h6.5"/>',
		tag: '<path d="M3 12V3h9l9 9-9 9z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
		repeat: '<path d="M17 2l4 4-4 4"/><path d="M3 11V9a3 3 0 0 1 3-3h15M7 22l-4-4 4-4"/><path d="M21 13v2a3 3 0 0 1-3 3H3"/>',
		gift: '<rect x="3" y="8" width="18" height="5"/><path d="M5 13v8h14v-8M12 8v13M12 8S10.5 3 8 3.5 7.5 8 12 8zM12 8s1.5-5 4-4.5S16.5 8 12 8z"/>',
		pin: '<path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
		sparkle: '<path d="M12 3c.6 4.2 2.8 6.4 7 7-4.2.6-6.4 2.8-7 7-.6-4.2-2.8-6.4-7-7 4.2-.6 6.4-2.8 7-7z"/>'
	};
	function ico(name) {
		var p = P[name] || P.check;
		return '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + p + '</svg>';
	}

	function k(s) { return s.k ? '<p class="k rv">' + esc(s.k) + '</p>' : ''; }
	function h2(s, cls) { return s.h ? '<h2 class="h2 rv' + (cls ? ' ' + cls : '') + '">' + rich(s.h) + '</h2>' : ''; }
	function lede(s) { return s.body ? '<p class="lede rv">' + rich(s.body) + '</p>' : ''; }
	function notesHtml(t) {
		if (!t) return '<p style="opacity:.5">No notes for this slide.</p>';
		var out = '', inList = false;
		String(t).split('\n').forEach(function (line) {
			line = line.trim();
			if (!line) return;
			if (/^[-*] /.test(line)) {
				if (!inList) { out += '<ul>'; inList = true; }
				out += '<li>' + rich(line.slice(2)) + '</li>';
				return;
			}
			if (inList) { out += '</ul>'; inList = false; }
			if (/^Q:/.test(line)) out += '<p class="q">' + rich(line) + '</p>';
			else out += '<p>' + rich(line) + '</p>';
		});
		if (inList) out += '</ul>';
		return out;
	}

	/* ------------------------------------------------------------- slides */
	var T = {};

	T.cover = function (s, lite) {
		var prep = '';
		if (C.prospect) {
			prep = '<i></i><span>Prepared for <b>' + esc(C.prospect) + '</b>' +
				(C.cta && C.cta.rep ? ' by <b>' + esc(C.cta.rep) + '</b>' : '') + '</span>';
		} else if (s.by) {
			prep = '<i></i><span>' + rich(s.by) + '</span>';
		}
		var media = '';
		if (s.collage) {
			media = '<div class="cv-collage n' + Math.min(4, list(s.collage).length) + '">' + list(s.collage).slice(0, 4).map(function (u, j) {
				var m = list(s.motion)[j];
				return '<div class="cv-c cv-c' + j + '"><div class="win-bar"><i></i><i></i><i></i></div>' + (m && !lite ? '<div class="mvw">' : '') + '<img src="' + esc(src(u)) + '" alt="">' + (m && !lite ? mv(m, lite) + '</div>' : '') + '</div>';
			}).join('') + '</div>';
		} else if (s.image) {
			media = '<div class="cv-media"><img src="' + esc(src(s.image)) + '" alt=""></div><div class="cv-shade"></div>';
		}
		return media +
			'<div class="cv-body">' +
			(C.logo ? '<img class="logo rv" src="' + esc(C.logo) + '" alt="AdaptExec">' : '') +
			k(s) + '<h1 class="h1 rv">' + rich(s.h) + '</h1>' + lede(s) +
			(s.chips ? '<div class="chips rv">' + list(s.chips).map(function (c) { return '<span class="chip">' + rich(c) + '</span>'; }).join('') + '</div>' : '') +
			(prep ? '<div class="prep rv">' + prep + '</div>' : '') +
			'</div>';
	};

	function points(arr) {
		arr = list(arr);
		if (!arr.length) return '';
		return '<div class="grid g' + Math.min(4, arr.length) + ' pts">' + arr.map(function (p) {
			return '<div class="pt rv">' + (p.ic ? '<div class="ic">' + ico(p.ic) + '</div>' : '') +
				(p.n ? '<div class="n">' + esc(p.n) + '</div>' : '') +
				'<h3>' + rich(p.t) + '</h3>' + (p.d ? '<p>' + rich(p.d) + '</p>' : '') + '</div>';
		}).join('') + '</div>';
	}

	T.statement = function (s) {
		return k(s) + h2(s) + lede(s) + points(s.points) +
			(s.chips ? '<div class="chips rv">' + list(s.chips).map(function (c) { return '<span class="chip">' + rich(c) + '</span>'; }).join('') + '</div>' : '');
	};

	T.big = function (s) {
		return k(s) + '<p class="big rv">' + rich(s.h) + '</p>' + lede(s);
	};

	T.versus = function (s) {
		function col(c, cls) {
			c = c || {};
			return '<div class="vs-col ' + cls + ' rv"><h3><span>' + rich(c.title) + '</span>' +
				(c.tag ? '<span class="tag' + (cls === 'good' ? '' : ' n') + '">' + esc(c.tag) + '</span>' : '') + '</h3><ul>' +
				list(c.items).map(function (r) { return '<li><span>' + rich(r[0]) + '</span>' + (r[1] ? '<i>' + esc(r[1]) + '</i>' : '') + '</li>'; }).join('') +
				'</ul>' + (c.foot ? '<div class="vs-foot"><span>' + rich(c.foot[0]) + '</span><b>' + rich(c.foot[1]) + '</b></div>' : '') + '</div>';
		}
		return k(s) + h2(s, 'sm') + lede(s) + '<div class="vs">' + col(s.left, 'bad') + col(s.right, 'good') + '</div>';
	};

	T.cards = function (s) {
		var cs = list(s.cards);
		var cols = s.cols || (cs.length === 4 || cs.length === 8 ? 4 : cs.length === 2 ? 2 : 3);
		return k(s) + h2(s, s.small ? 'sm' : '') + lede(s) +
			'<div class="grid g' + cols + ' cards">' + cs.map(function (c) {
				return '<div class="card rv' + (c.ai ? ' ai' : '') + '">' + (c.tag ? '<span class="tag">' + esc(c.tag) + '</span>' : '') +
					'<div class="ic">' + ico(c.ic || (c.ai ? 'spark' : 'check')) + '</div><h3>' + rich(c.t) + '</h3>' +
					(c.d ? '<p>' + rich(c.d) + '</p>' : '') + '</div>';
			}).join('') + '</div>' +
			(s.note ? '<p class="note rv">' + ico('shield') + '<span>' + rich(s.note) + '</span></p>' : '');
	};

	T.flow = function (s) {
		var st = list(s.steps), n = st.length || 1;
		var inset = (50 / n) + '%';
		return k(s) + h2(s, 'sm') + lede(s) +
			'<div class="flow rv" data-n="' + n + '"><div class="flow-track" style="grid-template-columns:repeat(' + n + ',1fr)">' +
			'<div class="flow-line" style="left:' + inset + ';right:' + inset + '"><b></b></div><div class="flow-dot" style="left:' + inset + '"></div>' +
			st.map(function (x, j) {
				return '<button class="flow-node" data-step="' + j + '"><span class="bub">' + ico(x.ic) + '</span>' +
					'<span class="lab">' + esc(x.lab || ('Step ' + (j + 1))) + '</span><span class="tt">' + rich(x.t) + '</span>' +
					'<span class="desc">' + rich(x.d) + '</span></button>';
			}).join('') + '</div>' +
			'<div class="flow-detail">' + st.map(function (x, j) {
				return '<div class="flow-card" data-card="' + j + '"><div class="num">' + (j + 1) + '</div><div>' +
					'<span class="tag where">' + esc(x.lab || '') + '</span><h3 style="margin-top:14px">' + rich(x.t) + '</h3><p>' + rich(x.d) + '</p></div></div>';
			}).join('') + '</div></div>';
	};

	T.sync = function (s) {
		var site = s.site || {}, crm = s.crm || {};
		var fields = list(site.fields).map(function (f) {
			return '<div class="sy-f"><label>' + esc(f[0]) + '</label><div><span>' + esc(f[1]) + '</span></div></div>';
		}).join('');
		var rf = list(crm.fields).map(function (f) {
			return '<div><label>' + esc(f[0]) + '</label><p>' + rich(f[1]) + '</p></div>';
		}).join('');
		return k(s) + h2(s, 'sm') +
			'<div class="sync stx rv">' +
			'<div class="win sy-site"><div class="win-bar"><i></i><i></i><i></i><span>' + esc(site.url || 'yourbusiness.com') + '</span></div>' +
			'<div class="win-b"><h4>' + rich(site.title || 'Request a quote') + '</h4>' + fields +
			'<div class="sy-btn" style="--ac2:' + esc(site.color || '#111') + '">' + esc(site.button || 'Send') + '</div></div></div>' +
			'<div class="sy-mid">' + ico('bolt') + '<div class="sy-beam"><b></b></div><small>' + rich(s.mid || 'Instantly, no copy and paste') + '</small></div>' +
			'<div class="win dark sy-crm"><div class="win-bar"><i></i><i></i><i></i><span>' + esc(crm.url || 'Your CRM') + '</span></div>' +
			'<div class="win-b"><div class="rec"><div class="rec-h"><div class="av">' + esc(crm.initials || 'NL') + '</div><div><b>' + esc(crm.name || 'New lead') + '</b>' +
			'<span>' + esc(crm.sub || '') + '</span></div></div><div class="rec-f">' + rf + '</div></div></div></div>' +
			'<div class="autos">' + list(s.autos).map(function (a) { return '<div class="auto">' + ico(a[0]) + '<span>' + rich(a[1]) + '</span></div>'; }).join('') + '</div>' +
			'</div>' +
			'<div class="sync-steps rv"><button data-sync="0" aria-label="Visitor fills the form"></button><button data-sync="1" aria-label="Submitted"></button><button data-sync="2" aria-label="CRM record"></button><button data-sync="3" aria-label="Automations"></button></div>';
	};

	T.demo = function (s, lite) {
		var concept = !!s.concept;
		var host = String(s.url || '').replace(/^https?:\/\//, '').replace(/^\//, (location.host || 'adaptexec.com') + '/').replace(/\/index\.html$/, '/').replace(/\/$/, '');
		function lst(cls, title, icn, arr) {
			arr = list(arr);
			if (!arr.length) return '';
			return '<div class="dm-l ' + cls + '"><h4>' + ico(icn) + esc(title) + '</h4><ul>' +
				arr.slice(0, 3).map(function (x) { return '<li>' + rich(x) + '</li>'; }).join('') + '</ul></div>';
		}
		return '<div class="dm"><div class="dm-frame rv"><div class="dm-win"><div class="win-bar"><i></i><i></i><i></i><span>' + esc(host) + '</span></div>' +
			'<span class="dm-live' + (concept ? ' concept' : '') + '"><i></i>' + (concept ? 'Concept preview' : 'Live demo') + '</span>' +
			'<div class="dm-view">' + (s.poster ? '<img src="' + esc(src(s.poster)) + '" alt="' + esc(s.name) + '">' : '') +
			(lite ? '' : '<iframe data-src="' + esc(s.url) + '" title="' + esc(s.name) + ' live demo" tabindex="-1"></iframe>') + mv(s.motion, lite) +
			(lite ? '' : '<button class="dm-shield" data-act="try"><span>' + ico('hand') + 'Click to try the live site</span></button>' +
				'<button class="dm-pill" data-act="release">' + ico('x') + 'Back to the slides</button>') +
			'</div></div>' +
			'<div class="dm-acts"><button class="btn" data-act="full" data-url="' + esc(s.url) + '" data-name="' + esc(s.name) + '">' + ico('full') + 'Open full screen</button>' +
			'<a class="btn ghost" href="' + esc(s.url) + '" target="_blank" rel="noopener">' + ico('ext') + 'New tab</a></div></div>' +
			'<div class="dm-side">' + k(s) + '<h2 class="h2 rv">' + rich(s.h || s.name) + '</h2>' +
			(s.ind ? '<p class="dm-ind rv">' + rich(s.ind) + '</p>' : '') +
			'<div class="dm-lists rv">' + lst('', 'On the website', 'globe', s.site) + lst('', 'In the CRM', 'chart', s.crm) + lst('ai', 'AI possibilities', 'spark', s.ai) + '</div>' +
			'</div></div>';
	};

	T.shot = function (s, lite) {
		var img = '<img class="shot-img" src="' + esc(src(s.image)) + '" alt="' + esc(s.alt || '') + '">';
		if (s.motion && !lite) img = '<div class="mvw">' + img + mv(s.motion, lite) + '</div>';
		var media = s.win
			? '<div class="win' + (s.dark ? ' dark' : '') + '"><div class="win-bar"><i></i><i></i><i></i><span>' + esc(s.win) + '</span></div>' + img + '</div>'
			: img;
		return '<div class="shot' + (s.flip ? ' flip' : '') + (s.tall ? ' tall' : '') + '">' +
			'<div class="shot-txt">' + k(s) + h2(s, 'sm') + (s.body ? '<p class="body rv">' + rich(s.body) + '</p>' : '') +
			(s.callouts ? '<div class="callouts">' + list(s.callouts).map(function (c, j) {
				return '<div class="callout rv"><b>' + (j + 1) + '</b><div><h4>' + rich(c[0]) + '</h4>' + (c[1] ? '<p>' + rich(c[1]) + '</p>' : '') + '</div></div>';
			}).join('') + '</div>' : '') + '</div>' +
			'<div class="shot-media rv">' + media +
			(s.phone ? '<div class="shot-phone"><img src="' + esc(src(s.phone)) + '" alt="">' + mv(s.phoneMotion, lite) + '</div>' : '') +
			(s.cap ? '<p class="shot-cap">' + rich(s.cap) + '</p>' : '') + '</div></div>';
	};

	function mock(m) {
		m = m || {};
		var top = '<div class="cm-top"><i></i><i></i><i></i><span>' + esc(m.bar || 'Your Hub') + '</span><em>Sample data</em></div>';
		var b = '';
		if (m.kind === 'pipeline') {
			var cols = list(m.cols);
			b = '<div class="cm-cols" style="grid-template-columns:repeat(' + cols.length + ',1fr)">' + cols.map(function (c) {
				var cards = list(c.cards);
				return '<div><div class="cm-colh"><span>' + esc(c.name) + '</span><b>' + cards.length + '</b></div>' + cards.map(function (x) {
					return '<div class="cm-card' + (x.hot ? ' hot' : '') + '"><b>' + esc(x.t) + '</b>' + (x.s ? '<span>' + esc(x.s) + '</span>' : '') +
						(x.u ? '<u class="' + esc(x.tone || '') + '">' + esc(x.u) + '</u>' : '') + '</div>';
				}).join('') + '</div>';
			}).join('') + '</div>';
		} else if (m.kind === 'dash') {
			b = '<div class="cm-kpis">' + list(m.kpis).map(function (x) {
				return '<div class="cm-kpi' + (x[3] ? ' hi' : '') + '"><label>' + esc(x[0]) + '</label><b>' + esc(x[1]) + '</b><span>' + esc(x[2] || '') + '</span></div>';
			}).join('') + '</div><div class="cm-split">' +
				'<div class="cm-panel"><h5><span>' + esc((m.bars || {}).title || 'This week') + '</span><span>' + esc((m.bars || {}).note || '') + '</span></h5><div class="cm-bars">' +
				list((m.bars || {}).items).map(function (x) { return '<div><i data-h="' + (+x[1] || 0) + '"></i><small>' + esc(x[0]) + '</small></div>'; }).join('') + '</div></div>' +
				'<div class="cm-panel"><h5><span>' + esc((m.feed || {}).title || 'Live activity') + '</span></h5><div class="cm-feed">' +
				list((m.feed || {}).items).map(function (x) {
					return '<div><i class="' + (x[3] ? 'ai' : '') + '">' + esc(x[0]) + '</i><p>' + rich(x[1]) + (x[2] ? '<span>' + esc(x[2]) + '</span>' : '') + '</p></div>';
				}).join('') + '</div></div></div>';
		} else if (m.kind === 'record') {
			var r = m.rec || {};
			b = '<div class="cm-rec"><div><div class="rec-h"><div class="av">' + esc(r.initials || '') + '</div><div><b style="color:#fff">' + esc(r.name || '') + '</b><span>' + esc(r.sub || '') + '</span></div></div>' +
				'<div class="rec-f">' + list(r.fields).map(function (f) { return '<div><label>' + esc(f[0]) + '</label><p>' + rich(f[1]) + '</p></div>'; }).join('') + '</div>' +
				(m.ai ? '<div class="cm-ai"><b>AI suggestion:</b> ' + rich(m.ai) + '</div>' : '') + '</div>' +
				'<div class="cm-panel cm-tl"><h5><span>Timeline</span><span>auto-logged</span></h5>' +
				list(m.tl).map(function (x) { return '<div class="' + esc(x[2] || '') + '"><b>' + rich(x[0]) + '</b><span>' + esc(x[1] || '') + '</span></div>'; }).join('') +
				'</div></div>';
		}
		return '<div class="cm">' + top + '<div class="cm-b">' + b + '</div></div>';
	}

	T.crm = function (s) {
		return '<div class="crm' + (s.wide ? ' wide' : '') + '"><div>' + k(s) + h2(s, 'sm') + (s.body ? '<p class="body rv">' + rich(s.body) + '</p>' : '') +
			(s.callouts ? '<div class="callouts">' + list(s.callouts).map(function (c, j) {
				return '<div class="callout rv"><b>' + (j + 1) + '</b><div><h4>' + rich(c[0]) + '</h4>' + (c[1] ? '<p>' + rich(c[1]) + '</p>' : '') + '</div></div>';
			}).join('') + '</div>' : '') + '</div>' +
			'<div class="rv">' + mock(s.mock) + '<p class="sample">Illustrative mock · sample data · built to order</p></div></div>';
	};

	T.timeline = function (s) {
		return k(s) + h2(s, 'sm') + lede(s) + '<div class="tl">' + list(s.items).map(function (x) {
			return '<div class="tl-i rv' + (x.hi ? ' hi' : '') + '"><time>' + esc(x.when) + '</time><span class="dot">' + ico(x.ic || 'bolt') + '</span>' +
				'<div class="tx"><h4>' + rich(x.t) + '</h4><p>' + rich(x.d) + '</p></div></div>';
		}).join('') + '</div>';
	};

	T.video = function (s, lite) {
		return '<div class="vid"><div>' + k(s) + h2(s, 'sm') + (s.body ? '<p class="body rv">' + rich(s.body) + '</p>' : '') +
			(s.chips ? '<div class="chips rv">' + list(s.chips).map(function (c) { return '<span class="chip">' + rich(c) + '</span>'; }).join('') + '</div>' : '') +
			'</div><div class="vid-box rv"><img src="' + esc(src(s.poster)) + '" alt="">' +
			(lite ? '' : '<video playsinline controls preload="none" poster="' + esc(src(s.poster)) + '" data-src="' + esc(src(s.src)) + '"></video>') +
			'<button class="vid-play" data-act="play" aria-label="Play video"><span>' + ico('play') + '</span></button>' +
			(s.len ? '<span class="vid-len">' + esc(s.len) + '</span>' : '') + '</div></div>';
	};

	T.proof = function (s) {
		return k(s) + h2(s, 'sm') + lede(s) +
			'<div class="stats">' + list(s.stats).map(function (x) {
				var m = String(x[0]).match(/^(\d+)(.*)$/);
				return '<div class="stat rv"><b' + (m ? ' class="cu" data-to="' + m[1] + '" data-suf="' + esc(m[2]) + '"' : '') + '>' + esc(x[0]) + '</b><span>' + rich(x[1]) + '</span></div>';
			}).join('') + '</div>' +
			(s.clients ? '<div class="clients rv">' + list(s.clients).map(function (c) { return '<span>' + esc(c) + '</span>'; }).join('') + '</div>' : '');
	};

	T.cases = function (s) {
		return k(s) + h2(s, 'sm') + lede(s) + '<div class="cases">' + list(s.items).map(function (c) {
			return '<a class="case rv" href="' + esc(c.url) + '" target="_blank" rel="noopener"><img src="' + esc(src(c.img)) + '" alt=""><div>' +
				(c.tag ? '<span class="tag">' + esc(c.tag) + '</span>' : '') + '<h3>' + rich(c.t) + '</h3><p>' + rich(c.d) + '</p></div></a>';
		}).join('') + '</div>';
	};

	T.process = function (s) {
		return k(s) + h2(s, 'sm') + lede(s) + '<div class="proc">' + list(s.steps).map(function (x, j) {
			return '<div class="proc-i rv"><div class="n">' + (j + 1) + '</div><h3>' + rich(x[0]) + '</h3><p>' + rich(x[1]) + '</p></div>';
		}).join('') + '</div>' + (s.note ? '<p class="note rv" style="margin-top:56px">' + ico('lock') + '<span>' + rich(s.note) + '</span></p>' : '');
	};

	T.questions = function (s) {
		return k(s) + h2(s, 'sm') + lede(s) + '<ol class="qs">' + list(s.qs).map(function (q) {
			return '<li class="rv">' + rich(q) + '</li>';
		}).join('') + '</ol>';
	};

	T.cta = function (s) {
		var c = C.cta || {};
		var btns = '';
		if (c.url) {
			btns += '<a class="btn" href="' + esc(c.url) + '" data-cta>' + esc(c.label || 'Tell us about your business') + ico('arrow') + '</a>';
		} else {
			btns += '<a class="btn" href="' + esc(c.contact || 'https://adaptexec.com/contact-us/') + '" target="_blank" rel="noopener" data-cta>' +
				esc(s.button || 'Book a free consultation') + ico('arrow') + '</a>';
		}
		if (c.phone) btns += '<a class="btn ghost" href="tel:' + esc(String(c.phone).replace(/[^\d+]/g, '')) + '">' + ico('phone') + esc(c.phone) + '</a>';
		var rep = c.rep
			? '<div class="cta-rep rv"><div class="av">' + esc(c.rep.charAt(0)) + '</div><div><b>' + esc(c.rep) + '</b><span>' + esc(c.repLine || 'AdaptExec') + '</span></div></div>'
			: '';
		var meta = '<div class="cta-meta rv">' + (c.email ? '<a href="mailto:' + esc(c.email) + '">' + esc(c.email) + '</a>' : '') +
			'<a href="https://adaptexec.com/" target="_blank" rel="noopener">adaptexec.com</a>' +
			'<span>Scottsdale, AZ · clients nationwide</span></div>';
		return k(s) + '<h1 class="h1 rv">' + rich(s.h) + '</h1>' + lede(s) + '<div class="btnrow rv">' + btns + '</div>' + rep + meta;
	};

	T.tour = function (s) {
		return k(s) + h2(s, 'sm') + lede(s) + '<div class="tour' + (list(s.items).length > 8 ? ' t5' : '') + '">' + list(s.items).map(function (x) {
			return '<button class="tour-i rv" data-go="' + esc(x.go || '') + '"><img src="' + esc(src(x.img)) + '" alt=""><div><b>' + esc(x.t) + '</b><span>' + esc(x.tag) + '</span></div></button>';
		}).join('') + (s.more ? '<div class="tour-i more rv"><b>' + rich(s.more.t) + '</b><span>' + rich(s.more.d) + '</span></div>' : '') + '</div>';
	};

	var THEME = { cover: 'dark', big: 'orange', cta: 'orange' };
	var FRAGS = {
		flow: function (s) { return Math.max(0, list(s.steps).length - 1); },
		sync: function () { return 3; }
	};
	function fragsOf(n) { var s = D.slides[n]; return s && FRAGS[s.type] ? FRAGS[s.type](s) : 0; }

	function slideNode(s, n, lite) {
		var fn = T[s.type] || T.statement;
		var theme = s.theme || THEME[s.type] || 'dark';
		var node = el('section', 'axd-slide s-' + s.type + ' t-' + theme + (s.center ? ' center' : '') +
			(s.type === 'cover' && !s.image && !s.collage ? ' no-media' : '') + (s.cls ? ' ' + s.cls : ''));
		var html = '';
		if (s.glow !== false && s.type !== 'cover') html += '<div class="axd-glow"></div>';
		html += fn(s, lite);
		if (D.foot && s.type !== 'cover') html += '<div class="axd-foot">' + esc(D.foot) + '</div>';
		node.innerHTML = html;
		node.setAttribute('data-n', n);
		$$('.rv', node).forEach(function (r, j) { r.style.setProperty('--i', j); });
		return node;
	}

	/* -------------------------------------------------------------- stage */
	function Stage(host, lite) {
		this.host = host;
		this.lite = !!lite;
		this.el = el('div', 'axd-stage');
		this.nodes = [];
		this.w = 1920; this.h = 1080;
		var self = this;
		D.slides.forEach(function (s, n) {
			var nd = slideNode(s, n, self.lite);
			self.nodes.push(nd);
			self.el.appendChild(nd);
		});
		host.appendChild(this.el);
		this.i = -1; this.f = 0;
	}
	Stage.prototype.fit = function (W, H, pt) {
		this.w = pt ? 900 : 1920;
		this.h = pt ? Math.round(900 * H / W) : 1080;
		this.el.classList.toggle('is-pt', !!pt);
		this.el.style.width = this.w + 'px';
		this.el.style.height = this.h + 'px';
		var s = Math.min(W / this.w, H / this.h);
		this.el.style.transform = 'translate(' + Math.round((W - this.w * s) / 2) + 'px,' + Math.round((H - this.h * s) / 2) + 'px) scale(' + s + ')';
	};
	Stage.prototype.show = function (i, f) {
		var prev = this.i;
		var self = this;
		this.nodes.forEach(function (nd, j) {
			nd.classList.toggle('is-on', j === i);
			nd.classList.toggle('is-past', j < i);
		});
		if (prev !== i) {
			if (prev >= 0 && this.nodes[prev]) leave(this.nodes[prev], D.slides[prev]);
			enter(this.nodes[i], D.slides[i], this);
		}
		this.i = i;
		setFrag(this.nodes[i], D.slides[i], f, prev !== i);
		this.f = f;
		void self.el.offsetHeight;
	};

	/* --------------------------------------------- per-type enter / frags */
	function enter(node, s) {
		if (!node) return;
		node.scrollTop = 0;
		if (s.type === 'proof') {
			$$('.cu', node).forEach(function (b) {
				var to = +b.getAttribute('data-to'), suf = b.getAttribute('data-suf') || '', t0 = null;
				function step(ts) {
					if (!t0) t0 = ts;
					var p = Math.min(1, (ts - t0) / 1400);
					b.textContent = Math.round(to * (1 - Math.pow(1 - p, 3))) + suf;
					if (p < 1 && node.classList.contains('is-on')) requestAnimationFrame(step); else b.textContent = to + suf;
				}
				b.textContent = '0' + suf;
				requestAnimationFrame(step);
			});
		}
		if (s.type === 'crm') {
			$$('.cm-bars i', node).forEach(function (b) { b.style.height = '0'; });
			setTimeout(function () { $$('.cm-bars i', node).forEach(function (b) { b.style.height = b.getAttribute('data-h') + '%'; }); }, 500);
		}
	}
	function leave(node, s) {
		$$('video', node).forEach(function (v) { try { v.pause(); } catch (e) {} });
		$$('.dm-view.live', node).forEach(function (v) { v.classList.remove('live'); });
		if (s && s.type === 'sync') { var sy = $('.sync', node); if (sy) sy.className = 'sync stx rv'; }
	}
	function setFrag(node, s, f, fresh) {
		if (!node || !s) return;
		if (s.type === 'flow') {
			var n = list(s.steps).length || 1;
			$$('.flow-node', node).forEach(function (b, j) { b.classList.toggle('on', j === f); b.classList.toggle('done', j < f); });
			$$('.flow-card', node).forEach(function (c, j) { c.classList.toggle('on', j === f); });
			var line = $('.flow-line b', node), dot = $('.flow-dot', node);
			if (line) line.style.width = (n > 1 ? f / (n - 1) * 100 : 100) + '%';
			if (dot) dot.style.left = (50 / n + f * 100 / n) + '%';
		}
		if (s.type === 'sync') {
			var sy = $('.sync', node);
			if (!sy) return;
			if (fresh && f === 0) {
				sy.className = 'sync stx rv';
				void sy.offsetWidth;
				setTimeout(function () { if (sy.classList.contains('stx')) { sy.classList.remove('stx'); sy.classList.add('st0'); } }, 450);
			} else {
				sy.className = 'sync rv st' + f;
			}
			$$('.sy-f', node).forEach(function (x) { x.classList.add('typing'); });
			$$('.sync-steps button', node).forEach(function (b, j) { b.classList.toggle('on', j <= f); });
		}
	}

	/* ------------------------------------------------------- navigation */
	function stopAuto() { if (auto) { clearTimeout(auto); auto = null; } }
	function startAuto() {
		stopAuto();
		var s = D.slides[cur];
		if (!s || !FRAGS[s.type]) return;
		var delays = s.type === 'sync' ? [4300, 1500, 2200] : [];
		function tick() {
			var max = fragsOf(cur);
			if (frag >= max) { auto = null; return; }
			var d = s.type === 'sync' ? (delays[frag] || 2000) : (s.auto || 2900);
			auto = setTimeout(function () {
				if (D.slides[cur] !== s) return;
				frag++;
				render(false, true);
				tick();
			}, d);
		}
		tick();
	}

	function render(fromRemote, fromAuto) {
		if (main) main.show(cur, frag);
		if (pvCur) {
			pvCur.show(cur, frag);
			var nx = frag < fragsOf(cur) ? [cur, frag + 1] : (cur + 1 < N ? [cur + 1, 0] : null);
			if (nx) { pvNext.show(nx[0], nx[1]); pvNext.el.style.visibility = ''; } else pvNext.el.style.visibility = 'hidden';
			var nt = $('.pv-notes');
			if (nt) nt.innerHTML = '<h5>Notes · slide ' + (cur + 1) + '</h5>' + notesHtml(D.slides[cur].notes);
			var lb = $('.pv-cur .pv-lbl');
			if (lb) lb.textContent = 'Now · ' + (cur + 1) + ' / ' + N + (fragsOf(cur) ? ' · step ' + (frag + 1) + '/' + (fragsOf(cur) + 1) : '');
			var lb2 = $('.pv-next .pv-lbl');
			if (lb2) lb2.textContent = nx ? (nx[0] === cur ? 'Next · same slide, step ' + (nx[1] + 1) : 'Next · ' + (nx[0] + 1)) : 'End of deck';
		}
		var cnt = $$('.axd-cnt');
		cnt.forEach(function (c) { c.textContent = (cur + 1) + ' / ' + N; });
		var pg = $('.axd-progress i');
		if (pg) pg.style.width = ((cur + 1) / N * 100) + '%';
		var pv = $('.axd-nav.prev'), nx2 = $('.axd-nav.next');
		if (pv) pv.disabled = cur === 0 && frag === 0;
		if (nx2) nx2.disabled = cur === N - 1 && frag >= fragsOf(cur);
		var no = $('.axd-notes');
		if (no) no.innerHTML = '<h5>Speaker notes · ' + (cur + 1) + '</h5><div class="nt">' + notesHtml(D.slides[cur].notes) + '</div>';
		if (main) { frames(); motion(); }
		if (cur === N - 1) ping('finish');
		if (REP && !PRES) { try { history.replaceState(null, '', '#' + (cur + 1)); } catch (e) {} }
		if (!fromRemote && !fromAuto) send({ t: 'go', i: cur, f: frag });
	}

	function go(i, f, fromRemote) {
		i = Math.max(0, Math.min(N - 1, i));
		var changed = i !== cur;
		cur = i;
		frag = Math.max(0, Math.min(fragsOf(i), f || 0));
		render(fromRemote);
		if (changed && frag === 0) startAuto(); else if (!fromRemote) stopAuto();
	}
	function next() {
		stopAuto();
		if (frag < fragsOf(cur)) { frag++; render(); } else if (cur < N - 1) go(cur + 1, 0);
	}
	function prev() {
		stopAuto();
		if (frag > 0) { frag--; render(); } else if (cur > 0) go(cur - 1, fragsOf(cur - 1));
	}
	function idOf(id) {
		for (var j = 0; j < N; j++) if (D.slides[j].id === id) return j;
		return -1;
	}

	/* --------------------------------------------------------- sync / ping */
	function send(m) {
		if (!chan || quiet) return;
		try { chan.postMessage(m); } catch (e) {}
	}
	function onMsg(e) {
		var m = e.data || {};
		if (m.t === 'go') { stopAuto(); go(m.i, m.f, true); }
		else if (m.t === 'hello' && !PRES) send({ t: 'state', i: cur, f: frag });
		else if (m.t === 'state' && PRES) go(m.i, m.f, true);
		else if (m.t === 'black') document.body.classList.toggle('blackout', !!m.on);
	}
	function ping(event) {
		if (!C.ping || !C.token || pinged[event]) return;
		pinged[event] = true;
		try {
			var body = JSON.stringify({ token: C.token, event: event });
			if (navigator.sendBeacon) navigator.sendBeacon(C.ping, new Blob([body], { type: 'application/json' }));
			else fetch(C.ping, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body });
		} catch (e) { /* reporting never breaks the deck */ }
	}
	function toast(t) {
		var n = $('.axd-toast');
		if (!n) { n = el('div', 'axd-toast'); document.body.appendChild(n); }
		n.textContent = t;
		n.classList.add('on');
		clearTimeout(n._t);
		n._t = setTimeout(function () { n.classList.remove('on'); }, 2200);
	}

	/* ----------------------------------------------------- live demo frames */
	function frames() {
		main.nodes.forEach(function (nd, j) {
			var f = $('iframe[data-src]', nd);
			if (!f) return;
			var near = Math.abs(j - cur) <= 1 && !portrait;
			if (near && !f.getAttribute('src')) {
				f.onload = function () {
					if (f.getAttribute('src')) f.classList.add('ready');
					try {
						f.contentWindow.addEventListener('keydown', function (e) {
							if (e.key === 'Escape') { var v = f.closest('.dm-view'); if (v) v.classList.remove('live'); window.focus(); }
						});
					} catch (e) { /* cross origin: Escape still works once focus returns */ }
				};
				f.setAttribute('src', f.getAttribute('data-src'));
			} else if (!near && Math.abs(j - cur) > 2 && f.getAttribute('src')) {
				f.classList.remove('ready');
				f.removeAttribute('src');
			}
		});
	}
	function motion() {
		main.nodes.forEach(function (nd, j) {
			$$('video.axd-mv', nd).forEach(function (v) {
				var d = j - cur;
				if (d === 0 && !RM) {
					if (!v._w) { v._w = 1; v.addEventListener('playing', function () { v.classList.add('on'); }); }
					if (!v.getAttribute('src')) v.setAttribute('src', v.getAttribute('data-src'));
					if (!v._on) { v._on = 1; try { if (v.readyState) v.currentTime = 0; } catch (e) {} }
					var p = v.play();
					if (p && p.catch) p.catch(function () {});
				} else {
					v._on = 0;
					try { v.pause(); } catch (e) {}
					if (Math.abs(d) === 1 && !RM && !v.getAttribute('src')) { v.preload = 'auto'; v.setAttribute('src', v.getAttribute('data-src')); }
					else if (Math.abs(d) > 2 && v.getAttribute('src')) { v.classList.remove('on'); v.removeAttribute('src'); try { v.load(); } catch (e) {} }
				}
			});
		});
	}
	function openModal(url, name) {
		var m = $('.axd-modal');
		if (!m) {
			m = el('div', 'axd-modal');
			m.innerHTML = '<div class="axd-modal-bar"><b></b><a target="_blank" rel="noopener">' + ico('ext') + '<span>New tab</span></a>' +
				'<button class="x" data-act="close-modal">' + ico('x') + '<span>Close</span></button></div><iframe title="Live demo"></iframe>';
			document.body.appendChild(m);
		}
		$('b', m).innerHTML = '<i>' + (/concept|index\.html/.test(url) ? 'Concept preview' : 'Live demo') + '</i>' + esc(name || '');
		$('a', m).href = url;
		var f = $('iframe', m);
		f.onload = function () {
			try { f.contentWindow.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); }); } catch (e) {}
		};
		f.src = url;
		m.classList.add('on');
		stopAuto();
	}
	function closeModal() {
		var m = $('.axd-modal');
		if (!m) return;
		m.classList.remove('on');
		$('iframe', m).src = 'about:blank';
		window.focus();
	}

	/* -------------------------------------------------------------- chrome */
	function full() {
		if (document.fullscreenEnabled && document.documentElement.requestFullscreen) {
			if (!document.fullscreenElement) document.documentElement.requestFullscreen().catch(function () {});
			else document.exitFullscreen();
		} else {
			window.open(location.href.split('#')[0] + '#' + (cur + 1), '_blank');
		}
	}
	function presenter() {
		var w = window.open(location.href.split('#')[0] + '#presenter', 'axd-presenter-' + (C.slug || ''), 'width=1280,height=800');
		if (!w) toast('Allow pop-ups to open the presenter view');
		else toast('Presenter view opened. Drag this window to the projector and press F.');
	}
	function printDeck(withNotes) {
		closeAll();
		document.body.classList.toggle('print-notes', !!withNotes);
		$$('.axd-pnote').forEach(function (n) { n.remove(); });
		if (withNotes && main) {
			main.nodes.forEach(function (nd, j) {
				var p = el('div', 'axd-pnote', '<h5>Slide ' + (j + 1) + ' · speaker notes</h5>' + notesHtml(D.slides[j].notes));
				nd.parentNode.insertBefore(p, nd.nextSibling);
			});
		}
		setTimeout(function () { window.print(); }, 80);
	}
	function closeAll() {
		var ov = $('.axd-ov');
		if (ov) ov.classList.remove('on');
		var mn = $('.axd-menu');
		if (mn) mn.remove();
	}
	function overview() {
		var ov = $('.axd-ov');
		if (!ov) {
			ov = el('div', 'axd-ov');
			ov.innerHTML = '<h2><span>' + esc(D.title) + '</span><button data-act="ov-close">Close (Esc)</button></h2><div class="axd-ov-g"></div>';
			var g = $('.axd-ov-g', ov);
			D.slides.forEach(function (s, j) {
				var b = el('button', 'axd-ov-i');
				b.setAttribute('data-goto', j);
				var mini = el('div', 'mini');
				var nd = slideNode(s, j, true);
				nd.classList.add('is-on');
				mini.appendChild(nd);
				b.appendChild(mini);
				b.appendChild(el('u', '', String(j + 1)));
				g.appendChild(b);
			});
			document.body.appendChild(ov);
		}
		$$('.axd-ov-i', ov).forEach(function (b, j) { b.classList.toggle('on', j === cur); });
		ov.classList.toggle('on');
		if (ov.classList.contains('on')) {
			requestAnimationFrame(function () {
				$$('.axd-ov-i .mini', ov).forEach(function (m) {
					var nd = m.firstChild;
					nd.style.transform = 'scale(' + (m.clientWidth / 1920) + ')';
				});
				var on = $('.axd-ov-i.on', ov);
				if (on && on.scrollIntoView) on.scrollIntoView({ block: 'center' });
			});
		}
	}

	function bar() {
		var b = el('div', 'axd-bar');
		var h = '<button data-act="prev" aria-label="Previous">' + ico('left') + '</button><span class="cnt axd-cnt"></span>' +
			'<button data-act="next" aria-label="Next">' + ico('right') + '</button><span class="sep"></span>' +
			'<button data-act="ov" title="All slides (O)">' + ico('grid') + '<span class="lbl">Slides</span></button>';
		if (REP) {
			h += '<button data-act="notes" title="Speaker notes (N)">' + ico('notes') + '<span class="lbl">Notes</span></button>' +
				'<button data-act="pres" title="Presenter view (P)">' + ico('screen') + '<span class="lbl">Presenter</span></button>';
		}
		h += '<button data-act="print" title="Print or save as PDF">' + ico('print') + '<span class="lbl">PDF</span></button>' +
			'<button data-act="full" title="Full screen (F)">' + ico('full') + '<span class="lbl">Full screen</span></button>';
		b.innerHTML = h;
		document.body.appendChild(b);
		var np = el('button', 'axd-nav prev', ico('left')); np.setAttribute('data-act', 'prev'); np.setAttribute('aria-label', 'Previous slide');
		var nn = el('button', 'axd-nav next', ico('right')); nn.setAttribute('data-act', 'next'); nn.setAttribute('aria-label', 'Next slide');
		document.body.appendChild(np); document.body.appendChild(nn);
		document.body.appendChild(el('div', 'axd-progress', '<i></i>'));
		document.body.appendChild(el('div', 'axd-black'));
		if (REP) document.body.appendChild(el('div', 'axd-notes'));
		var idle;
		function wake() {
			b.classList.remove('is-idle');
			document.body.classList.add('show-ui');
			clearTimeout(idle);
			idle = setTimeout(function () {
				if (!portrait && !b.matches(':hover')) { b.classList.add('is-idle'); document.body.classList.remove('show-ui'); }
			}, 2800);
		}
		document.addEventListener('mousemove', wake);
		document.addEventListener('touchstart', wake, { passive: true });
		wake();
	}

	function printMenu() {
		var mn = $('.axd-menu');
		if (mn) { mn.remove(); return; }
		if (!REP) { printDeck(false); return; }
		mn = el('div', 'axd-notes on axd-menu');
		mn.style.bottom = '76px'; mn.style.left = '50%'; mn.style.right = 'auto'; mn.style.transform = 'translateX(-50%)'; mn.style.width = '300px';
		mn.innerHTML = '<h5>Print or save as PDF</h5>' +
			'<p><button class="btn" style="padding:12px 18px;font-size:15px;width:100%;justify-content:center" data-act="print-slides">Slides only</button></p>' +
			'<p style="margin:10px 0 0"><button class="btn ghost" style="padding:12px 18px;font-size:15px;width:100%;justify-content:center;color:#fff!important" data-act="print-notes">Slides with speaker notes</button></p>' +
			'<p style="margin:12px 0 0;color:#999;font-size:13px">Choose “Save as PDF” in the print dialog. Landscape is set for you.</p>';
		document.body.appendChild(mn);
	}

	function act(a, t) {
		switch (a) {
			case 'prev': prev(); break;
			case 'next': next(); break;
			case 'ov': overview(); break;
			case 'ov-close': closeAll(); break;
			case 'notes': var n = $('.axd-notes:not(.axd-menu)'); if (n) n.classList.toggle('on'); break;
			case 'pres': presenter(); break;
			case 'print': printMenu(); break;
			case 'print-slides': printDeck(false); break;
			case 'print-notes': printDeck(true); break;
			case 'full': if (t && t.getAttribute('data-url')) openModal(t.getAttribute('data-url'), t.getAttribute('data-name')); else full(); break;
			case 'close-modal': closeModal(); break;
			case 'try':
				var v = t.closest('.dm-view');
				if (v) { v.classList.add('live'); stopAuto(); toast('You are on the live site. Press Esc or “Back to the slides” when done.'); }
				break;
			case 'release': var v2 = t.closest('.dm-view'); if (v2) v2.classList.remove('live'); window.focus(); break;
			case 'play':
				var box = t.closest('.vid-box'), vd = box && $('video', box);
				if (vd) {
					if (!vd.getAttribute('src')) vd.setAttribute('src', vd.getAttribute('data-src'));
					box.classList.add('playing');
					vd.onerror = function () { box.classList.remove('playing'); toast('This video could not play in this browser.'); };
					stopAuto();
					var p = vd.play();
					if (p && p.catch) p.catch(function () {});
				}
				break;
		}
	}

	function clicks(root) {
		root.addEventListener('click', function (e) {
			var t = e.target.closest('[data-act]');
			if (t) { e.preventDefault(); act(t.getAttribute('data-act'), t); return; }
			var g = e.target.closest('[data-goto]');
			if (g) { closeAll(); go(+g.getAttribute('data-goto'), 0); return; }
			var tg = e.target.closest('[data-go]');
			if (tg) { var j = idOf(tg.getAttribute('data-go')); if (j >= 0) go(j, 0); return; }
			var st = e.target.closest('[data-step]');
			if (st) { stopAuto(); frag = +st.getAttribute('data-step'); render(); return; }
			var sy = e.target.closest('[data-sync]');
			if (sy) { stopAuto(); frag = +sy.getAttribute('data-sync'); render(); return; }
			if (e.target.closest('[data-cta]')) { ping('cta'); return; }
			if (e.target.closest('a, button, input, textarea, select, video, iframe, .dm-view, .axd-bar, .axd-notes, .axd-ov, .axd-modal')) return;
			if (portrait || PRES) return;
			if (window.getSelection && String(window.getSelection())) return;
			if (e.clientX < window.innerWidth * 0.2) prev(); else next();
		});
	}

	function keys(e) {
		if (e.target && /input|textarea|select/i.test(e.target.tagName)) return;
		if (e.metaKey || e.ctrlKey || e.altKey) return;
		var m = $('.axd-modal.on');
		if (e.key === 'Escape') {
			if (m) { closeModal(); e.preventDefault(); return; }
			if ($('.axd-ov.on') || $('.axd-menu')) { closeAll(); e.preventDefault(); return; }
			var lv = $('.dm-view.live'); if (lv) { lv.classList.remove('live'); return; }
			if (document.body.classList.contains('blackout')) { black(false); return; }
			return;
		}
		if (m) return;
		var kk = e.key;
		if (kk === 'ArrowRight' || kk === 'ArrowDown' || kk === ' ' || kk === 'PageDown' || kk === 'Enter') { e.preventDefault(); next(); }
		else if (kk === 'ArrowLeft' || kk === 'ArrowUp' || kk === 'PageUp' || kk === 'Backspace') { e.preventDefault(); prev(); }
		else if (kk === 'Home') { e.preventDefault(); stopAuto(); go(0, 0); }
		else if (kk === 'End') { e.preventDefault(); stopAuto(); go(N - 1, 0); }
		else if (kk === 'o' || kk === 'O' || kk === 'g' || kk === 'G') { e.preventDefault(); overview(); }
		else if (kk === 'f' || kk === 'F') { e.preventDefault(); full(); }
		else if ((kk === 'p' || kk === 'P') && REP && !PRES) { e.preventDefault(); presenter(); }
		else if ((kk === 'n' || kk === 'N') && REP && !PRES) { e.preventDefault(); act('notes'); }
		else if (kk === 'b' || kk === 'B' || kk === '.') { e.preventDefault(); black(!document.body.classList.contains('blackout')); }
	}
	function black(on) {
		document.body.classList.toggle('blackout', on);
		send({ t: 'black', on: on });
	}

	function swipe(root) {
		var x0 = null, y0 = null, t0 = 0;
		root.addEventListener('touchstart', function (e) {
			if (e.touches.length !== 1) { x0 = null; return; }
			x0 = e.touches[0].clientX; y0 = e.touches[0].clientY; t0 = Date.now();
		}, { passive: true });
		root.addEventListener('touchend', function (e) {
			if (x0 === null) return;
			var dx = e.changedTouches[0].clientX - x0, dy = e.changedTouches[0].clientY - y0;
			x0 = null;
			if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.3 && Date.now() - t0 < 900) {
				if (dx < 0) next(); else prev();
			}
		}, { passive: true });
	}

	/* ---------------------------------------------------------------- boot */
	function layout() {
		if (!main) return;
		var W = window.innerWidth, H = window.innerHeight;
		var pt = W < 760 && H > W * 1.05;
		if (pt !== portrait) { portrait = pt; frames(); }
		main.fit(W, H, portrait);
	}

	function startAudience() {
		document.body.className = 'axd';
		var vp = el('div', 'axd-vp');
		document.body.appendChild(vp);
		main = new Stage(vp, false);
		bar();
		layout();
		window.addEventListener('resize', layout);
		window.addEventListener('orientationchange', function () { setTimeout(layout, 200); });
		window.addEventListener('beforeprint', function () { if (main) { main.el.classList.remove('is-pt'); } });
		window.addEventListener('afterprint', function () { $$('.axd-pnote').forEach(function (n) { n.remove(); }); layout(); });
		clicks(document.body);
		swipe(vp);
		try { window.focus(); } catch (e) {}
		/* Focus or scrollIntoView can scroll an overflow:hidden box and shift a
		   slide sideways; nothing inside the stage scrolls except a slide on a
		   phone in portrait, and that one only vertically. */
		document.addEventListener('scroll', function (e) {
			var t = e.target;
			if (!t || !t.closest || !t.closest('.axd-vp')) return;
			if (t.scrollLeft) t.scrollLeft = 0;
			if (t.scrollTop && !(portrait && t.classList.contains('axd-slide'))) t.scrollTop = 0;
		}, true);
		document.addEventListener('keydown', keys);
		var h = parseInt((location.hash || '').replace('#', ''), 10);
		cur = -1;
		go(!isNaN(h) && h > 0 ? h - 1 : 0, 0, true);
		if (REP) send({ t: 'state', i: cur, f: frag });
	}

	function startPresenter() {
		document.body.className = 'axd axd-pres';
		document.title = 'Presenter · ' + D.title;
		var pv = el('div', 'pv');
		pv.innerHTML = '<div class="pv-top"><b>' + esc(D.title) + '</b><span style="color:#777">Presenter view</span><span class="sp"></span>' +
			'<span class="pv-clock"></span><span class="pv-timer">00:00</span><button data-pv="pause">Pause</button><button data-pv="reset">Reset</button></div>' +
			'<div class="pv-cur"><span class="pv-lbl or"></span><div class="pv-box" id="pv-a"></div></div>' +
			'<div class="pv-right"><div class="pv-next"><span class="pv-lbl"></span><div class="pv-box" id="pv-b"></div></div><div class="pv-notes"></div></div>' +
			'<div class="pv-bot"><button data-act="prev">' + '&larr; Back</button><button class="or" data-act="next">Next &rarr;</button>' +
			'<span class="cnt axd-cnt"></span><span class="sp"></span><span class="pv-hint">Arrows / clicker move both screens · B blacks out the audience · keep this window on your laptop</span>' +
			'<button data-pv="aud">Open audience window</button></div>';
		document.body.appendChild(pv);
		pvCur = new Stage($('#pv-a'), true);
		pvNext = new Stage($('#pv-b'), true);
		function fit() {
			[pvCur, pvNext].forEach(function (st) {
				var w = st.host.clientWidth, h2 = st.host.clientHeight;
				st.fit(w, h2, false);
			});
		}
		window.addEventListener('resize', fit);
		var t0 = Date.now(), paused = 0, pauseAt = 0;
		setInterval(function () {
			var ms = (pauseAt || Date.now()) - t0 - paused;
			var s = Math.max(0, Math.floor(ms / 1000));
			$('.pv-timer').textContent = (s >= 3600 ? Math.floor(s / 3600) + ':' : '') + ('0' + Math.floor(s / 60) % 60).slice(-2) + ':' + ('0' + s % 60).slice(-2);
			var d = new Date();
			$('.pv-clock').textContent = d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
		}, 500);
		pv.addEventListener('click', function (e) {
			var b = e.target.closest('[data-pv]');
			if (!b) return;
			var w = b.getAttribute('data-pv');
			if (w === 'pause') {
				if (pauseAt) { paused += Date.now() - pauseAt; pauseAt = 0; b.textContent = 'Pause'; }
				else { pauseAt = Date.now(); b.textContent = 'Resume'; }
			} else if (w === 'reset') { t0 = Date.now(); paused = 0; if (pauseAt) pauseAt = Date.now(); }
			else if (w === 'aud') { window.open(location.href.split('#')[0] + '#' + (cur + 1), 'axd-audience-' + (C.slug || '')); }
		});
		clicks(pv);
		document.addEventListener('keydown', keys);
		cur = -1;
		go(0, 0, true);
		fit();
		setTimeout(fit, 60);
		send({ t: 'hello' });
	}

	function start(deck) {
		D = deck;
		D.slides = list(D.slides).filter(function (s) { return s && s.type; });
		N = D.slides.length;
		if (!N) throw new Error('empty deck');
		document.title = (D.title || 'Presentation') + ' · AdaptExec';
		if (D.accent) document.documentElement.style.setProperty('--ac-deck', D.accent);
		var ld = $('.axd-loading');
		if (ld) ld.remove();
		try { chan = new BroadcastChannel('axd-' + (C.slug || D.slug || 'deck')); chan.onmessage = onMsg; } catch (e) { chan = null; }
		if (!REP) chan = null;
		if (PRES) startPresenter(); else startAudience();
	}

	window.AXDeck = {
		start: start,
		go: function (i, f) { go(i, f || 0); },
		next: next,
		prev: prev,
		state: function () { return { slide: cur + 1, step: frag, slides: N, portrait: portrait }; }
	};

	function fail() {
		document.body.innerHTML = '<div style="padding:40px;font:16px system-ui;color:#fff;background:#0b0b0b;min-height:100vh">This presentation could not be loaded. Please refresh the page.</div>';
	}
	try {
		if (C.deck && C.deck.slides) start(C.deck);
		else if (C.deckUrl) fetch(C.deckUrl, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(start).catch(fail);
	} catch (e) { fail(); if (window.console) console.warn(e); }
})();
