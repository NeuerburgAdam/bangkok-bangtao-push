/* =========================================================================
   AdaptExec Sales Hub
   A small hash-routed app. No build step, no framework — it has to be
   editable by whoever maintains the site next.
   ========================================================================= */
(function () {
	'use strict';

	var S = { boot: null, leads: [], lead: null, tasks: [], q: '', view: 'dash',
		listMode: window.matchMedia('(max-width: 860px)').matches };

	/* ------------------------------------------------------------- helpers */

	function el(id) { return document.getElementById(id); }
	function esc(s) {
		return String(s === null || s === undefined ? '' : s)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
	}
	function money(n) {
		n = Number(n) || 0;
		if (n >= 1000000) return '$' + (n / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
		if (n >= 1000) return '$' + Math.round(n / 1000) + 'K';
		return '$' + n.toLocaleString('en-US', { maximumFractionDigits: 0 });
	}
	function money2(n) { return '$' + (Number(n) || 0).toLocaleString('en-US', { maximumFractionDigits: 0 }); }
	/* Commission is paid to the cent, so it is shown to the cent. */
	function moneyC(n) {
		n = Number(n) || 0;
		return '$' + n.toLocaleString('en-US', { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 });
	}
	function pctTxt(r) { return String(Number(r) || 0).replace(/\.0+$/, '') + '%'; }
	/**
	 * A date in words. Handles the future as well as the past — follow-ups and
	 * commission due dates are both ahead of today, and reading "Yesterday"
	 * against a payment due next month is worse than showing no date at all.
	 */
	/* Server times are saved in UTC; show them in the viewer's own time zone.
	   A bare date, or a date at exactly midnight, is a calendar day someone picked
	   (a due date), so it stays on that day wherever the viewer is. */
	function srvDate(d) {
		var s = String(d || '').trim();
		if (!s) return new Date(NaN);
		if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return new Date(s + 'T12:00:00');
		var m = s.match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}(?::\d{2})?)$/);
		if (m) return /^00:00(?::00)?$/.test(m[2]) ? new Date(m[1] + 'T12:00:00') : new Date(m[1] + 'T' + m[2] + 'Z');
		return new Date(s.replace(' ', 'T'));
	}
	function isDay(d) { return /^\d{4}-\d{2}-\d{2}(?:[ T]00:00(?::00)?)?$/.test(String(d || '').trim()); }
	function when(d) {
		if (!d) return '';
		var t = srvDate(d);
		if (isNaN(t)) return '';

		function midnight(x) { return new Date(x.getFullYear(), x.getMonth(), x.getDate()).getTime(); }
		var days = Math.round((midnight(t) - midnight(new Date())) / 86400000);

		if (days === 0) return 'Today';
		if (days === 1) return 'Tomorrow';
		if (days === -1) return 'Yesterday';
		if (days > 1 && days < 7) return 'in ' + days + ' days';
		if (days < -1 && days > -7) return Math.abs(days) + ' days ago';

		var far = Math.abs(days) > 300;
		return t.toLocaleDateString('en-US', {
			month: 'short', day: 'numeric', year: far ? 'numeric' : undefined
		});
	}
	function icon(name, size) {
		var p = {
			home: '<path d="M3 11l9-8 9 8v9a2 2 0 0 1-2 2h-4v-6H9v6H5a2 2 0 0 1-2-2z"/>',
			board: '<rect x="3" y="4" width="5" height="16" rx="1"/><rect x="10" y="4" width="5" height="10" rx="1"/><rect x="17" y="4" width="4" height="13" rx="1"/>',
			users: '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M21.5 20a6.5 6.5 0 0 0-5-6.3"/>',
			doc: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h6"/>',
			play: '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>',
			check: '<path d="M20 6 9 17l-5-5"/>',
			clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
			chart: '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
			gear: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0a1.7 1.7 0 0 0-1.1-1.6 1.7 1.7 0 0 0-1.8.4l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.4-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
			flag: '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1zM4 22v-7"/>',
			brief: '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
			plus: '<path d="M12 5v14M5 12h14"/>',
			back: '<path d="M19 12H5M11 18l-6-6 6-6"/>',
			msg: '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.4A8 8 0 1 1 21 12z"/>',
			cal: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
			mail: '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>',
			phone: '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8.1 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.8 2z"/>',
			globe: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18z"/>',
			trash: '<path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>',
			left: '<path d="M15 18l-6-6 6-6"/>',
			right: '<path d="M9 18l6-6-6-6"/>',
			x: '<path d="M18 6 6 18M6 6l12 12"/>',
			spark: '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M18.4 5.6l-2.8 2.8M8.4 15.6l-2.8 2.8"/>',
			link: '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
			copy: '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
			qr: '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM19 19h2v2h-2M14 21h3"/>',
			eye: '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
			bell: '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/>',
			money: '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
			shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
			pen: '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
			file: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
			upload: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>',
			download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
			printer: '<path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
			lock: '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
			receipt: '<path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
			send: '<path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/>',
			box: '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/>'
		}[name] || '';
		size = size || 17;
		return '<svg viewBox="0 0 24 24" width="' + size + '" height="' + size + '" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round">' + p + '</svg>';
	}

	function toast(msg, kind) {
		var t = document.createElement('div');
		t.className = 'axh-toast' + (kind ? ' axh-toast--' + kind : '');
		t.textContent = msg;
		el('axh-toasts').appendChild(t);
		setTimeout(function () { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; }, 2600);
		setTimeout(function () { t.remove(); }, 3000);
	}

	/* ----------------------------------------------------------------- api */

	function api(path, opts) {
		opts = opts || {};
		return fetch(AXH.root + path, {
			method: opts.method || 'GET',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': AXH.nonce },
			body: opts.body ? JSON.stringify(opts.body) : undefined
		}).then(null, function () {
			// fetch() only rejects when the request never reached the server.
			var off = new Error(navigator.onLine === false ? 'You are offline. Check your connection and try again.' : 'Could not reach the Hub. Check your connection and try again.');
			off.code = 'axh_offline'; off.status = 0;
			throw off;
		}).then(function (r) {
			return r.json().catch(function () { return { message: 'The server sent an unexpected response (HTTP ' + r.status + '). Please try again.' }; }).then(function (j) {
				if (!r.ok) {
					var er = new Error(j && j.message ? j.message : 'Something went wrong.');
					er.code = j && j.code; er.data = j && j.data; er.status = r.status;
					if (er.code === 'rest_cookie_invalid_nonce' || er.code === 'rest_not_logged_in' || r.status === 401) {
						er.message = 'Your session has expired. Reload the Hub to continue (you may need to sign in again).';
						sessionExpired();
					}
					throw er;
				}
				return j;
			});
		});
	}

	/* A Hub tab left open overnight outlives its REST nonce (or its login).
	   Say so once, with a way back in, instead of failing every click. */
	function sessionExpired() {
		if (S.expiredShown) return;
		S.expiredShown = true;
		var bar = document.createElement('div');
		bar.className = 'axh-alert axh-expired';
		bar.setAttribute('role', 'alert');
		bar.style.cssText = 'position:fixed;left:12px;right:12px;bottom:12px;z-index:9999;display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap';
		bar.innerHTML = '<span><b>Your session has expired.</b> Reload to keep working \u2014 you may need to sign in again.</span>' +
			'<button type="button" class="axh-btn axh-btn--primary axh-btn--sm" onclick="location.reload()">Reload the Hub</button>';
		document.body.appendChild(bar);
	}

	/* -------------------------------------------------------------- modal */

	function modal(title, bodyHtml, footHtml) {
		var m = el('axh-modal');
		m.querySelector('.axh-modal__box').innerHTML =
			'<div class="axh-modal__hd"><h3>' + title + '</h3><button class="axh-x" data-close>' + icon('x', 18) + '</button></div>' +
			'<div class="axh-modal__bd">' + bodyHtml + '</div>' +
			(footHtml ? '<div class="axh-modal__ft">' + footHtml + '</div>' : '');
		m.hidden = false;
		m.onclick = function (e) { if (e.target === m || e.target.closest('[data-close]')) closeModal(); };
		var f = m.querySelector('input,textarea,select');
		if (f) f.focus();
		return m;
	}
	function closeModal() { el('axh-modal').hidden = true; }
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
	/* Pipeline cards open with Enter, like a link. */
	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Enter' || !e.target.classList || !e.target.classList.contains('axh-kc') || !e.target.getAttribute('data-open')) return;
		location.hash = '#/lead/' + e.target.getAttribute('data-open');
	});

	/* --------------------------------------------------------------- nav */

	function nav() {
		var m = S.boot.user.manager;
		var pend = (S.boot.summary.pending_reg || 0) + (S.boot.summary.pending_win || 0);
		var ob = S.boot.onboarding || {};
		var items = [
			['', 'Dashboard', 'home'],
			m ? null : ['onboarding', 'Onboarding', 'shield', ob.complete ? 0 : 3 - (ob.done || 0)],
			['leads', 'Pipeline', 'board'],
			['tasks', 'Follow-ups', 'check'],
			['present', 'Present', 'play'],
			['demos', 'Demo sites', 'globe'],
			['shares', 'Shared links', 'link'],
			['earnings', 'Earnings', 'chart'],
			m ? null : ['billing', 'Client billing', 'receipt'],
			['forms', 'Lead forms', 'mail'],
			['library', 'Sales kit', 'doc'],
			['styles', 'Style finder', 'spark'],
			['settings', 'Settings', 'gear']
		];
		var html = items.filter(Boolean).map(function (i) {
			var on = (location.hash.replace('#/', '').split('/')[0] || '') === i[0];
			return '<a href="#/' + i[0] + '" class="' + (on ? 'is-on' : '') + '">' + icon(i[2], 17) + i[1] +
				(i[3] ? '<span class="axh-nav__count">' + i[3] + '</span>' : '') + '</a>';
		}).join('');

		if (m) {
			html += '<div class="axh-nav__group">Administration</div>';
			var mi = [
				['manage', 'Team overview', 'chart'],
				['manage/team', 'Team', 'users'],
				['manage/registrations', 'Approvals', 'flag', pend],
				['manage/deals', 'Deals closed', 'check'],
				['manage/onboarding', 'Onboarding', 'shield', (ob.verify || 0)],
				['manage/payouts', 'Payouts', 'money'],
				['manage/briefs', 'Quote requests', 'brief']
			];
			var hpath = location.hash.replace('#/', '');
			html += mi.map(function (i) {
				var on = i[0] === 'manage' ? hpath === 'manage' : (hpath === i[0] || hpath.indexOf(i[0] + '/') === 0 || (i[0] === 'manage/team' && hpath.indexOf('manage/rep/') === 0));
				return '<a href="#/' + i[0] + '" class="' + (on ? 'is-on' : '') + '">' + icon(i[2], 17) + i[1] +
					(i[3] ? '<span class="axh-nav__count">' + i[3] + '</span>' : '') + '</a>';
			}).join('');
		}
		if (m) {
			var bh = location.hash.replace('#/', '');
			html += '<div class="axh-nav__group">Billing</div>' + [
				['billing', 'Overview', 'chart'], ['billing/docs', 'Documents', 'receipt'], ['billing/customers', 'Customers', 'users'],
				['billing/payments', 'Payments', 'money'], ['billing/items', 'Products & services', 'box'], ['billing/commissions', 'Commissions', 'check'], ['billing/settings', 'Billing settings', 'gear']
			].map(function (i) {
				var on = i[0] === 'billing' ? bh === 'billing' : (bh === i[0] || bh.indexOf(i[0] + '/') === 0 || (i[0] === 'billing/docs' && /^billing\/(doc|edit)\//.test(bh)) || (i[0] === 'billing/customers' && bh.indexOf('billing/customer/') === 0) || (i[0] === 'billing/commissions' && /^billing\/(batch|statement)\//.test(bh)) || (i[0] === 'billing/settings' && /^billing\/templates?/.test(bh)));
				return '<a href="#/' + i[0] + '" class="' + (on ? 'is-on' : '') + '">' + icon(i[2], 17) + i[1] + '</a>';
			}).join('');
		}
		el('axh-nav').innerHTML = html;
	}

	/* --------------------------------------------------------- dashboard */

	function viewDash() {
		var s = S.boot.summary, u = S.boot.user;
		var hour = new Date().getHours();
		var greet = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';

		var open = S.leads.filter(function (l) { return ['won', 'lost'].indexOf(l.stage) < 0; });
		var dl = p1DueList(), due = dl.due;
		var recent = S.leads.slice(0, 6);
		var needsReg = S.leads.filter(function (l) { return l.reg_status === 'draft'; });

		var html =
			'<div class="axh-page-head"><div><h1 class="thunder">' + greet + ', ' + esc(u.name.split(' ')[0]) + '.</h1>' +
			'<p>' + (open.length ? open.length + ' active opportunities worth ' + money2(s.pipeline) + ' in your pipeline.' : 'Your pipeline is empty — add your first prospect to get started.') + '</p></div>' +
			'<div class="axh-actions axh-only-sm"><a class="axh-btn axh-btn--ghost" href="#/present">' + icon('play', 15) + ' Present</a>' +
			'<button class="axh-btn axh-btn--primary" data-new>' + icon('plus', 15) + ' New lead</button></div></div>' +

			'<div class="axh-stats">' +
			stat('Open opportunities', open.length, s.pending_reg ? s.pending_reg + ' awaiting registration' : 'All registered', false) +
			stat('Pipeline value', money(s.pipeline), 'Your estimates added up, nothing discounted', true) +
			stat('Closed won', s.won_count, money2(s.won_value) + ' in contracts', false) +
			stat('Follow-ups due today', due.length, dl.overdue ? dl.overdue + ' overdue · <a href="#/tasks">work the queue</a>' : (due.length ? 'Next: ' + esc(due[0].title) : 'Nothing due today'), false) +
			'</div>';

		if (!u.manager && S.boot.onboarding && !S.boot.onboarding.complete) html = html.replace('<div class="axh-stats">', onbCard(S.boot.onboarding) + '<div class="axh-stats">');
		else if (!u.manager && S.boot.onboarding && S.boot.onboarding.complete) html = html.replace('<div class="axh-stats">', onbDoneStrip() + '<div class="axh-stats">');
		if (!u.manager && showChecklist()) html += checklistCard();

		var waitingWin = S.leads.filter(function (l) { return l.win_requested_at && l.stage !== 'won'; });
		if (waitingWin.length) {
			html += '<div class="axh-note" style="margin-bottom:16px"><b>Waiting on AdaptExec</b><p>' +
				waitingWin.map(function (l) { return esc(l.company); }).join(', ') +
				(waitingWin.length === 1 ? ' is' : ' are') + ' reported signed. Commission shows under Earnings once AdaptExec confirms Closed Won.</p></div>';
		}

		if (needsReg.length) {
			html += '<div class="axh-card" style="border-color:var(--o);background:var(--o-soft);margin-bottom:16px">' +
				'<div class="axh-card__h"><h3>Register these before you call</h3></div>' +
				'<p style="font-size:13.5px;margin-bottom:12px">Section 2.4 of your agreement: a prospect has to be registered and confirmed before your first substantive contact, or the commission is not protected.</p>' +
				'<div class="axh-chips">' + needsReg.slice(0, 8).map(function (l) {
					return '<button class="axh-chip" data-reg="' + l.id + '">' + esc(l.company) + ' →</button>';
				}).join('') + '</div></div>';
		}

		html += '<div class="axh-grid axh-grid--2">' +
			'<div class="axh-card"><div class="axh-card__h"><h3>Recent activity</h3><a href="#/leads">All leads</a></div>' +
			(recent.length ? '<ul class="axh-list">' + recent.map(function (l) {
				return '<li><span class="axh-avatar" style="width:28px;height:28px;font-size:11px;background:' + tone(l.stage) + '">' + initials(l.company) + '</span>' +
					'<div class="axh-list__t"><b>' + esc(l.company) + '</b><span>' + stageLabel(l.stage) + (l.city ? ' · ' + esc(l.city) : '') + '</span></div>' +
					'<a class="axh-list__r" href="#/lead/' + l.id + '">' + when(l.updated_at) + '</a></li>';
			}).join('') + '</ul>' : empty('No leads yet', 'Add a prospect and the pipeline starts filling itself.', 'New lead')) +
			'</div>' +

			'<div><div class="axh-card"><div class="axh-card__h"><h3>Due next</h3><a href="#/tasks">All follow-ups</a></div>' +
			(dl.next.length ? '<ul class="fu-list fu-list--lead">' + dl.next.map(function (t) { return fuRow(t, { noSelect: true, compact: true }); }).join('') + '</ul>'
				: '<p style="color:var(--gray);font-size:13.5px">Nothing scheduled. Add a follow-up from any lead.</p>') +
			'</div>' +

			'<div class="axh-card" style="margin-top:16px"><div class="axh-card__h"><h3>Pipeline by stage</h3></div>' +
			S.boot.stages.filter(function (st) { return ['won', 'lost'].indexOf(st.key) < 0; }).map(function (st) {
				var d = (s.by_stage && s.by_stage[st.key]) || { count: 0, value: 0 };
				var pct = s.pipeline ? Math.round(d.value / s.pipeline * 100) : 0;
				return '<div style="margin-bottom:10px"><div style="display:flex;justify-content:space-between;font-size:13px"><span>' + st.label + '</span>' +
					'<b>' + d.count + ' · ' + money(d.value) + '</b></div>' +
					'<div class="axh-ring__bar" style="margin-top:5px"><i style="width:' + Math.max(pct, d.count ? 4 : 0) + '%"></i></div></div>';
			}).join('') + '</div></div></div>';

		return html;
	}

	/* First-week checklist for a new rep. Two steps tick themselves; the
	   rest are links. Hidden once dismissed or once the rep is clearly up
	   and running. */
	function showChecklist() {
		var hidden = false;
		try { hidden = localStorage.getItem('axh_checklist_done') === '1'; } catch (e) {}
		return !hidden && S.leads.filter(function (l) { return l.reg_status !== 'draft'; }).length < 3;
	}
	function checklistCard() {
		var hasLead = S.leads.length > 0;
		var hasReg = S.leads.some(function (l) { return l.reg_status !== 'draft'; });
		var steps = [
			[!!(S.boot.onboarding && S.boot.onboarding.complete), 'Finish <a href="#/onboarding">onboarding</a>: sign your agreement, upload your W-9, set up payment', ''],
			[false, 'Read <a href="#/earnings">How you get paid</a>', ''],
			[false, 'Watch the 38-second promo and open the <a href="#/demos">demo site</a> for your first industry', ''],
			[hasLead, 'Add your first prospect', '<button class="axh-btn axh-btn--ghost axh-btn--sm" data-new>New lead</button>'],
			[hasReg, 'Register it before your first real conversation', ''],
			[false, 'Make your own <a href="#/forms">lead form</a> and save its link and QR code', ''],
			[false, 'Rehearse a deck in <a href="#/present">Present</a>, then share one as a link', ''],
			[false, 'Run discovery with a prospect using the questionnaire, then press Request a quote', '']
		];
		return '<div class="axh-card axh-firstweek" style="margin-bottom:16px"><div class="axh-card__h"><h3>Your first week</h3>' +
			'<button data-checklist-hide>Hide</button></div><ol class="axh-checklist">' +
			steps.map(function (x) {
				return '<li class="' + (x[0] ? 'is-done' : '') + '"><span class="axh-checklist__tick">' + (x[0] ? icon('check', 13) : '') + '</span>' +
					'<span class="axh-checklist__t">' + x[1] + '</span>' + x[2] + '</li>';
			}).join('') + '</ol></div>';
	}

	function stat(label, value, detail, accent) {
		return '<div class="axh-stat' + (accent ? ' axh-stat--accent' : '') + '">' +
			'<div class="axh-stat__l">' + label + '</div><div class="axh-stat__v">' + value + '</div>' +
			'<div class="axh-stat__d">' + detail + '</div></div>';
	}
	function empty(h, p, btn) {
		// btn is either a label (opens New lead) or ready-made HTML for a link.
		return '<div class="axh-empty">' + icon('board', 40) + '<h4>' + h + '</h4><p>' + p + '</p>' +
			(btn ? (btn.charAt(0) === '<' ? btn : '<button class="axh-btn axh-btn--primary" data-new>' + btn + '</button>') : '') + '</div>';
	}
	function initials(s) {
		return String(s || '?').trim().split(/\s+/).slice(0, 2).map(function (w) { return w[0]; }).join('').toUpperCase();
	}
	function stageLabel(k) {
		var s = S.boot.stages.filter(function (x) { return x.key === k; })[0];
		return s ? s.label : k;
	}
	function stageTone(k) {
		var s = S.boot.stages.filter(function (x) { return x.key === k; })[0];
		return s ? s.tone : 'gray';
	}
	function tone(k) {
		return { gray: '#8A929C', blue: '#046BD2', amber: '#B45309', green: '#15803D', red: '#B91C1C' }[stageTone(k)] || '#8A929C';
	}

	/* ------------------------------------------------------------- leads */

	function viewLeads() {
		var q = S.q.toLowerCase();
		var sources = {};
		S.leads.forEach(function (l) { var k = srcBase(l.source); sources[k] = (sources[k] || 0) + 1; });
		if (S.srcFilter && !sources[S.srcFilter]) S.srcFilter = '';
		var leads = S.leads.filter(function (l) {
			if (S.srcFilter && srcBase(l.source) !== S.srcFilter) return false;
			if (!q) return true;
			var qd = q.replace(/\D/g, '');
			if (qd.length >= 4 && String(l.phone || '').replace(/\D/g, '').indexOf(qd) > -1) return true;
			return (l.company + ' ' + l.contact_name + ' ' + l.city + ' ' + l.email + ' ' + (l.phone || '') + ' ' + (l.source || '')).toLowerCase().indexOf(q) > -1;
		});
		var srcKeys = Object.keys(sources).sort(function (a, b) { return sources[b] - sources[a] || a.localeCompare(b); });

		var mgr = S.boot.user.manager;
		var arch = (S.boot.summary && S.boot.summary.archived) || 0;
		var html = '<div class="axh-page-head"><div><h1 class="thunder">' + (S.showArchived ? 'Archived leads' : 'Pipeline') + '</h1>' +
			'<p>' + leads.length + ' ' + (leads.length === 1 ? 'lead' : 'leads') + (S.listMode ? ' · tap a row to open it' : ' · drag a card to move it forward') + '</p></div>' +
			'<div class="axh-actions">' +
			(mgr && (S.boot.assignees || []).length ? '<select class="axh-select" data-rep-filter aria-label="Show leads for">' +
				'<option value="">Everyone\u2019s leads</option>' +
				S.boot.assignees.map(function (a) {
					return '<option value="' + a.id + '"' + (String(S.repFilter || '') === String(a.id) ? ' selected' : '') + '>' + esc(a.name) + '</option>';
				}).join('') + '</select>' : '') +
			(srcKeys.length > 1 || S.srcFilter ? '<select class="axh-select" data-src-filter aria-label="Show leads from">' +
				'<option value="">All sources</option>' + srcKeys.map(function (k) {
					return '<option value="' + esc(k) + '"' + (S.srcFilter === k ? ' selected' : '') + '>' + esc(k) + ' (' + sources[k] + ')</option>';
				}).join('') + '</select>' : '') +
			((arch || S.showArchived) ? '<button class="axh-btn axh-btn--quiet" data-toggle-archived>' + (S.showArchived ? 'Back to pipeline' : 'Archived (' + arch + ')') + '</button>' : '') +
			(!S.listMode && !S.showArchived ? '<select class="axh-select" data-fp-sort aria-label="Sort cards">' + [['activity', 'Recent activity'], ['next', 'Next step due'], ['value', 'Highest value'], ['stage', 'Longest in stage']].map(function (o) {
				return '<option value="' + o[0] + '"' + (S.pl.sort === o[0] ? ' selected' : '') + '>Sort: ' + o[1] + '</option>'; }).join('') + '</select>' : '') +
			'<button class="axh-btn axh-btn--ghost" data-toggle-view>' + (S.listMode ? 'Board view' : 'List view') + '</button>' +
			'<button class="axh-btn axh-btn--primary axh-only-sm" data-new>' + icon('plus', 15) + ' New lead</button></div></div>';

		if (!leads.length) return html + '<div class="axh-card">' + (S.showArchived
			? empty('Nothing archived', 'Leads you archive leave the pipeline but stay on record here.', '')
			: empty('Nothing here yet', 'Add your first prospect — company name is all you need to start.', 'Add a lead')) + '</div>';

		if (!S.showArchived && S.flow.board) html += fpStrip(leads);
		if (S.listMode) {
			html += '<div class="axh-card"><table class="axh-table"><thead><tr><th>Company</th>' + (mgr ? '<th>Owner</th>' : '') + '<th>Stage</th><th>Registration</th><th>Source</th><th class="num">Value</th><th>Next action</th><th>Updated</th></tr></thead><tbody>' +
				leads.map(function (l) {
					return '<tr data-open="' + l.id + '"><td><div class="axh-co">' + esc(l.company) +
						'<span>' + esc(l.contact_name || '—') + (l.city ? ' · ' + esc(l.city) : '') + '</span></div></td>' +
						(mgr ? '<td>' + esc(l.rep_name || '') + '</td>' : '') +
						'<td><span class="axh-badge axh-badge--' + stageTone(l.stage) + '">' + stageLabel(l.stage) + '</span></td>' +
						'<td>' + regBadge(l.reg_status) + '</td>' +
						'<td style="color:var(--gray)">' + esc(srcBase(l.source)) + (l.has_tool_results ? ' ' + icon('spark', 12) : '') + '</td>' +
						'<td class="num">' + (Number(l.est_value) ? money2(l.est_value) : '—') + '</td>' +
						'<td>' + esc(l.next_action || '—') + '</td>' +
						'<td style="color:var(--gray)">' + when(l.updated_at) + '</td></tr>';
				}).join('') + '</tbody></table></div>';
			return html;
		}

		html += fpBoardHtml(leads, mgr);
		return html;
	}

	/* Where a lead came from: the form or tool's source tag, without the page suffix. */
	function srcBase(s) { s = String(s || '').split(' \u00b7 ')[0].trim(); return s || 'Added in the Hub'; }

	/* Free text with line breaks, bullets and numbered lists, made readable. */
	function richText(t) {
		var bul = /^\s*(?:[-*\u2022\u2013\u2014\u25aa\u2713\u2714]|\d+[.)])\s+/;
		var num = /^\s*\d+[.)]\s+/;
		var out = '', para = [], list = null;
		function flushP() { if (para.length) { out += '<p>' + para.map(esc).join('<br>') + '</p>'; para = []; } }
		function flushL() { if (list) { out += '<' + list.tag + ' class="axh-rt__l">' + list.items.map(function (x) { return '<li>' + esc(x) + '</li>'; }).join('') + '</' + list.tag + '>'; list = null; } }
		String(t || '').replace(/\r\n?/g, '\n').split('\n').forEach(function (line) {
			if (!line.trim()) { flushP(); flushL(); return; }
			if (bul.test(line)) {
				var tag = num.test(line) ? 'ol' : 'ul';
				if (para.length === 1 && /:\s*$/.test(para[0]) && !list) { out += '<p class="axh-rt__h">' + esc(para[0]) + '</p>'; para = []; }
				flushP();
				if (list && list.tag !== tag) flushL();
				if (!list) list = { tag: tag, items: [] };
				list.items.push(line.replace(bul, ''));
				return;
			}
			flushL();
			para.push(line);
		});
		flushP(); flushL();
		return out;
	}

	/* Structured results from a lead-capture tool, as a tidy key/value list. */
	function toolTree(v, depth) {
		depth = depth || 0;
		if (v === null || v === undefined) return '';
		if (Array.isArray(v)) {
			if (v.every(function (x) { return typeof x !== 'object' || x === null; })) {
				return '<ul class="axh-tr__l">' + v.map(function (x) { return '<li>' + richInline(x) + '</li>'; }).join('') + '</ul>';
			}
			return '<ol class="axh-tr__l axh-tr__l--n">' + v.map(function (x) { return '<li>' + toolTree(x, depth + 1) + '</li>'; }).join('') + '</ol>';
		}
		if (typeof v === 'object') {
			return '<dl class="axh-tr' + (depth ? ' axh-tr--sub' : '') + '">' + Object.keys(v).map(function (k) {
				var x = v[k];
				return '<div class="axh-tr__r' + (x && typeof x === 'object' ? ' is-nest' : '') + '"><dt>' + esc(k) + '</dt><dd>' + (x && typeof x === 'object' ? toolTree(x, depth + 1) : richInline(x)) + '</dd></div>';
			}).join('') + '</dl>';
		}
		return richInline(v);
	}
	function richInline(x) { return esc(String(x)).replace(/\n/g, '<br>'); }

	/* The answers from the form or tool that opened this lead. */
	function enquiryCard(d) {
		var sub = d.submission, tr = d.tool_results, l = d.lead;
		if (!sub && !tr) return '';
		var html = '';
		if (sub && sub.answers && sub.answers.length) {
			var skip = { name: 1, email: 1, phone: 1, company: 1 };
			var rows = sub.answers.filter(function (a) { return !skip[a.key]; });
			html += '<div class="axh-card axh-enq" style="margin-bottom:16px"><div class="axh-card__h"><h3>' + esc(sub.form || 'Website enquiry') + '</h3>' +
				'<span class="axh-badge axh-badge--blue">' + esc(srcBase(sub.source || l.source)) + '</span></div>' +
				'<p class="axh-hint" style="margin:-4px 0 10px">Sent ' + when(sub.at) + (sub.referrer ? ' from <a href="' + esc(sub.referrer) + '" target="_blank" rel="noopener">' + esc(sub.referrer.replace(/^https?:\/\/(www\.)?/, '').slice(0, 60)) + '</a>' : '') + '</p>' +
				(rows.length ? '<dl class="axh-enq__l">' + rows.map(function (a) {
					var isLong = a.value.length > 140 || a.value.indexOf('\n') > -1;
					return '<div class="axh-enq__r' + (isLong ? ' is-long' : '') + '"><dt>' + esc(a.label) + '</dt><dd>' + (isLong ? '<div class="axh-rt' + (a.value.length > 1200 ? ' is-clamped' : '') + '">' + richText(a.value) + '</div>' + (a.value.length > 1200 ? '<button class="axh-linkbtn" data-unclamp>Show all ' + a.value.length.toLocaleString() + ' characters</button>' : '') : esc(a.value)) + '</dd></div>';
				}).join('') + '</dl>' : '<p class="axh-hint">Only contact details were sent.</p>') + '</div>';
		}
		if (tr) {
			html += '<div class="axh-card axh-toolres" style="margin-bottom:16px"><div class="axh-card__h"><h3>' + icon('spark', 15) + ' Tool results</h3>' +
				'<span class="axh-badge axh-badge--orange">' + esc(srcBase(l.source)) + '</span></div>' + toolTree(tr) + '</div>';
		}
		return html;
	}

	function actBody(b) {
		b = String(b);
		if (b.length <= 700) return '<p class="axh-tl__b">' + esc(b) + '</p>';
		return '<details class="axh-tl__more"><summary><span class="axh-tl__b">' + esc(b.slice(0, 360).replace(/\s+\S*$/, '')) + '\u2026</span><em>Show all</em></summary><p class="axh-tl__b">' + esc(b) + '</p></details>';
	}

	function regBadge(s) {
		var map = {
			draft: ['orange', 'Register it'],
			pending: ['amber', 'Pending'],
			registered: ['green', 'Registered'],
			house: ['red', 'House account'],
			declined: ['red', 'Declined']
		}[s] || ['gray', s];
		return '<span class="axh-badge axh-badge--' + map[0] + '">' + map[1] + '</span>';
	}

	/* ------------------------------------------------------- lead detail */

	function viewLead(d) {
		var l = d.lead, disc = d.discovery || {};
		var pct = disc.progress || 0;
		var mgr = S.boot.user.manager;
		var won = l.stage === 'won';
		var pendingWin = !!l.win_requested_at && !won;

		// Section 2.4 protects whoever registered first, so a rep needs to know
		// about a clash before they invest a week in the prospect.
		var clash = (d.conflicts || []).length
			? '<div class="axh-warn"><b>' + icon('flag', 15) +
				' This prospect may already be claimed</b><p>' +
				esc(d.conflicts[0].company) + ' is registered to ' + esc(d.conflicts[0].rep) +
				' \u2014 ' + esc(d.conflicts[0].why) + '. Check with AdaptExec before you work it.</p></div>'
			: '';

		var html = '<a class="axh-btn axh-btn--quiet axh-btn--sm" href="#/leads" style="margin-bottom:14px">' + icon('back', 14) + ' Pipeline</a>' +
			'<div class="axh-lead-head"><div class="axh-lead-head__id">' +
			'<h1 class="thunder">' + esc(l.company) + '</h1>' +
			'<div class="axh-meta">' +
			(l.contact_name ? '<span>' + esc(l.contact_name) + (l.title ? ', ' + esc(l.title) : '') + '</span>' : '') +
			(l.email ? '<a href="mailto:' + esc(l.email) + '">' + icon('mail', 13) + ' ' + esc(l.email) + '</a>' : '') +
			(l.phone ? '<a href="tel:' + esc(l.phone) + '">' + icon('phone', 13) + ' ' + esc(l.phone) + '</a>' : '') +
			(l.website ? '<a href="' + esc(l.website) + '" target="_blank" rel="noopener">' + icon('globe', 13) + ' Website</a>' : '') +
			(l.city ? '<span>' + esc(l.city) + (l.state ? ', ' + esc(l.state) : '') + '</span>' : '') +
			'</div>' + (l.source ? '<div class="axh-lead-src">' + icon(d.tool_results ? 'spark' : 'link', 13) + ' From <b>' + esc(srcBase(l.source)) + '</b>' +
				(String(l.source).indexOf(' \u00b7 ') > -1 ? '<span>' + esc(String(l.source).split(' \u00b7 ').slice(1).join(' \u00b7 ')) + '</span>' : '') + '</div>' : '') + '</div>' +
			'<div class="axh-actions">' +
			(l.reg_status === 'draft' ? '<button class="axh-btn axh-btn--primary" data-register="' + l.id + '">' + icon('flag', 15) + ' Register prospect</button>' : '') +
			'<button class="axh-btn axh-btn--ghost" data-present-lead="' + l.id + '">' + icon('play', 15) + ' Present</button>' +
			'<button class="axh-btn axh-btn--ghost" data-style-open="' + l.id + '">' + icon('spark', 15) + ' Style finder</button>' +
			'<button class="axh-btn axh-btn--ghost" data-edit="' + l.id + '">Edit</button>' +
			(mgr ? '<button class="axh-btn axh-btn--ghost" data-assign="' + l.id + '">' + icon('users', 15) + ' Reassign</button>' : '') +
			'</div></div>';

		html += clash;

		if (l.archived_at) {
			html += '<div class="axh-note" style="margin-top:14px"><b>Archived ' + when(l.archived_at) + '</b>' +
				'<p>Out of your pipeline, still on record. <button class="axh-linkbtn" data-archive="' + l.id + '" data-on="0">Restore it</button></p></div>';
		}
		if (pendingWin) {
			html += '<div class="axh-note" style="margin-top:14px"><b>Closed Won requested · ' + moneyC(l.win_request_value) + '</b>' +
				'<p>' + (mgr ? esc(d.rep) + ' reports this deal signed ' + when(l.win_requested_at) + '.' : 'Sent to AdaptExec ' + when(l.win_requested_at) + '. It moves to Closed Won — and into Earnings — once they confirm it.') +
				(l.win_request_note ? '<br><i>' + esc(l.win_request_note) + '</i>' : '') + '</p>' +
				(mgr ? '<div style="display:flex;gap:8px;margin-top:10px"><button class="axh-btn axh-btn--primary axh-btn--sm" data-close-deal="' + l.id + '" data-value="' + esc(l.win_request_value) + '">Approve Closed Won</button>' +
					'<button class="axh-btn axh-btn--ghost axh-btn--sm" data-win-decline="' + l.id + '">Not yet</button></div>' : '') + '</div>';
		}

		html += '<div class="axh-stats" style="margin-top:18px">' +
			'<div class="axh-stat"><div class="axh-stat__l">Stage</div><div class="axh-stat__v" style="font-size:28px">' + stageLabel(l.stage) + '</div>' +
			'<div class="axh-stat__d">' + regBadge(l.reg_status) + '</div></div>' +
			(won
				? stat('Contract value', moneyC(l.contract_value), pctTxt(l.commission_rate) + ' commission · ' + moneyC(Number(l.contract_value) * Number(l.commission_rate) / 100), true)
				: stat('Estimated value', Number(l.est_value) ? money(l.est_value) : '—', l.industry ? esc(l.industry) : 'Set an industry', true)) +
			'<div class="axh-stat"><div class="axh-stat__l">Discovery</div><div class="axh-stat__v">' + pct + '%</div>' +
			'<div class="axh-stat__d">' + discStatusText(disc, pct) + '</div></div>' +
			stat('Next action', esc(l.next_action || '—'), l.next_action_at ? when(l.next_action_at) : 'No date set', false) +
			'</div>';

		html += '<div class="axh-grid axh-grid--2">' +

			'<div>' + enquiryCard(d) + '<div class="axh-card" style="border-left:4px solid var(--o)">' +
			'<div class="axh-card__h"><h3>Discovery questionnaire</h3>' + discBadge(disc.status) + '</div>' +
			'<div class="axh-ring"><div class="axh-ring__n">' + pct + '%</div><div class="axh-ring__b">' +
			'<strong style="font-size:13.5px;color:var(--ink)">' + discReadyLine(disc.readiness) + '</strong>' +
			'<div class="axh-ring__bar"><i style="width:' + pct + '%"></i></div></div>' +
			'<a class="axh-btn axh-btn--primary axh-btn--sm" href="#/discovery/' + l.id + '">' + (pct ? 'Continue' : 'Start') + '</a></div>' +
			'<p style="font-size:13px;color:var(--gray)">You run discovery: everything Adam needs to write the quote without a call — goals, tools, workflows, users, integrations, budget and timing. ' +
				(disc.status === 'submitted' || disc.status === 'reviewed' ? 'Quote requested ' + when(disc.submitted_at) + '. Share the proposal when it appears below.' : 'When the meter reaches the line, press <b>Request a quote</b>.') + '</p>' +
			'</div>' + styleCard(d) +

			'<div class="axh-card" style="margin-top:16px"><div class="axh-card__h"><h3>Activity</h3>' +
			'<button data-log="' + l.id + '">Log something</button></div>' +
			(d.activity && d.activity.length > 3 ? '<div class="p1-actf" role="group" aria-label="Show">' + [['all', 'All'], ['talk', 'Calls & emails'], ['views', 'Views & forms'], ['notes', 'Notes']].map(function (f, n) {
				return '<button type="button" data-act-f="' + f[0] + '" class="' + (n ? '' : 'is-on') + '">' + f[1] + '</button>'; }).join('') + '</div>' : '') +
			(d.activity && d.activity.length ? '<ul class="axh-tl p1-tl" data-show="all">' + d.activity.map(function (a) {
				return '<li class="' + (['note', 'stage'].indexOf(a.type) > -1 ? 'is-quiet' : '') + '" data-g="' + esc(ACT_GROUP[a.type] || 'other') + '">' + p1ActIcon(a.type) +
					'<b>' + esc(a.subject || a.type) + '</b><span>' + when(a.occurred_at) + (isDay(a.occurred_at) ? '' : ' · ' + p1Clock(a.occurred_at)) + '</span>' +
					(a.body ? actBody(a.body) : '') + '</li>';
			}).join('') + '</ul>' : '<p style="color:var(--gray);font-size:13.5px">Nothing logged yet. Every call and meeting you record here shows up in your admin\'s view too.</p>') +
			'</div></div>' +

			'<div><div class="axh-card"><div class="axh-card__h"><h3>Move the deal</h3></div>' + stageCard(l, mgr, won, pendingWin) + '</div>' +

			'<div class="axh-card" style="margin-top:16px"><div class="axh-card__h"><h3>Next steps</h3>' +
			'<button data-add-task="' + l.id + '">' + icon('plus', 13) + ' Schedule</button></div>' +
			fuLeadList(d.tasks, l.id, l.stage) +
			'</div>' +

			'<div data-p1-lead="' + l.id + '"></div>' +
			'<div class="axh-card" style="margin-top:16px"><div class="axh-card__h"><h3>Details</h3></div>' +
			'<dl class="axh-kv">' +
			kv('Industry', l.industry) + kv('Source', l.source) + kv('Owner', d.rep) +
			(won ? kv('Closed', when(l.closed_at)) : '') +
			kv('Registered', l.registered_at ? when(l.registered_at) : '—') +
			kv('Created', when(l.created_at)) +
			(l.decision_note ? kv('Admin note', l.decision_note) : '') +
			'</dl>' + (l.notes ? '<p style="margin-top:12px;font-size:13.5px;white-space:pre-wrap">' + esc(l.notes) + '</p>' : '') +
			'<div class="axh-danger-row">' + removeButton(l, mgr) + '</div>' +
			'</div></div></div>';

		html += '<div class="bx-leadslot" data-bill-lead="' + l.id + '"></div>';
		return html;
	}

	/* Stages a rep can move freely; Closed Won is AdaptExec's call (it sets
	   the contract value that drives commission), so reps request it. */
	function stageCard(l, mgr, won, pendingWin) {
		if (won && !mgr) {
			return '<p style="font-size:13.5px;margin:0 0 4px"><span class="axh-badge axh-badge--green">Closed Won</span></p>' +
				'<p class="axh-hint" style="margin:8px 0 0">Confirmed by AdaptExec and locked — commission is under <a href="#/earnings">Earnings</a>. ' +
				'If something needs to change, ask AdaptExec.</p>';
		}
		var chips = S.boot.stages.map(function (st) {
			if (st.key === 'won') {
				if (mgr) {
					return '<button class="axh-chip' + (won ? ' is-on' : '') + '" data-close-deal="' + l.id + '" data-value="' +
						esc(won ? l.contract_value : (Number(l.win_request_value) > 0 ? l.win_request_value : l.est_value)) + '">' + st.label + (won ? '' : '…') + '</button>';
				}
				return '';
			}
			return '<button class="axh-chip' + (st.key === l.stage ? ' is-on' : '') + '" data-stage="' + st.key + '" data-lead="' + l.id + '">' + st.label + '</button>';
		}).join('');
		var extra = '';
		if (!mgr) {
			extra = pendingWin
				? '<p class="axh-hint" style="margin-top:12px">' + icon('clock', 13) + ' Closed Won requested — waiting on AdaptExec.</p>'
				: '<button class="axh-btn axh-btn--primary axh-btn--sm" data-win-req="' + l.id + '" style="margin-top:12px">' + icon('check', 14) + ' Client signed? Request Closed Won</button>' +
					'<p class="axh-hint" style="margin-top:8px">AdaptExec confirms the contract value, then the deal and your commission appear under Earnings.</p>';
		} else if (won) {
			extra = '<p class="axh-hint" style="margin-top:12px">Contract ' + moneyC(l.contract_value) + ' at ' + pctTxt(l.commission_rate) +
				'. <button class="axh-linkbtn" data-close-deal="' + l.id + '" data-value="' + esc(l.contract_value) + '">Correct the value</button></p>';
		}
		return '<div class="axh-chips">' + chips + '</div>' + extra;
	}

	function removeButton(l, mgr) {
		if (l.archived_at) return '';
		if (mgr || (l.reg_status === 'draft' && l.stage !== 'won')) {
			return '<button class="axh-btn axh-btn--quiet axh-btn--sm" data-archive="' + l.id + '" data-on="1">Archive</button>' +
				'<button class="axh-btn axh-btn--danger axh-btn--sm" data-del-lead="' + l.id + '">' + (l.reg_status === 'draft' ? 'Delete draft' : 'Delete permanently') + '</button>';
		}
		return '<button class="axh-btn axh-btn--quiet axh-btn--sm" data-archive="' + l.id + '" data-on="1">Archive lead</button>' +
			'<span class="axh-hint">Registered leads are archived, not deleted.</span>';
	}
	function kv(k, v) { return '<dt>' + k + '</dt><dd>' + esc(v || '—') + '</dd>'; }

	/* --------------------------------------------------------- discovery */

	function viewDiscovery(d) {
		var l = d.lead, disc = d.discovery || { payload: {}, modules: {} };
		S.disc = {
			leadId: l.id,
			payload: disc.payload || {},
			modules: disc.modules || {},
			// The questionnaire belongs to the lead's trade, not to the session.
			full: d.schema || S.boot.schema || [],
			config: disc.config || {},
			shareUrl: disc.share_url || '',
			packet: !!d.packet,
			sources: disc.sources || {},
			status: disc.status || 'draft',
			quotedAt: disc.submitted_at || '',
			industry: l.industry || '',
			trim: false,
			section: S.disc && S.disc.leadId === l.id ? S.disc.section : 0
		};
		return renderDiscovery();
	}

	/* Sections and questions this client is actually asked. */
	function discSchema() {
		var cfg = S.disc.config || {}, offS = cfg.sections || {}, offQ = cfg.questions || {};
		return (S.disc.full || []).filter(function (s) {
			return offS[s.key] !== false;
		}).map(function (s) {
			var fields = s.fields.filter(function (f) { return !offQ[f.key]; });
			if (!fields.length) return null;
			var copy = {};
			for (var k in s) { if (Object.prototype.hasOwnProperty.call(s, k)) copy[k] = s[k]; }
			copy.fields = fields;
			return copy;
		}).filter(function (s) { return !!s; });
	}

	/* Discovery status, quote readiness and the hand-off to Adam (1.7.1).
	   Readiness mirrors axh_discovery_readiness() in includes/modules.php:
	   the share of key questions answered (70% unlocks Request a quote), or of
	   all questions for a trade packet without key questions (50%). Optional
	   sections (the on-site study) and internal pages never count. */
	function discBadge(st) {
		var m = { submitted: ['green', 'Quote requested'], reviewed: ['green', 'Reviewed by AdaptExec'], client_done: ['blue', 'Client finished'] }[st] || ['orange', 'Draft'];
		return '<span class="axh-badge axh-badge--' + m[0] + '">' + m[1] + '</span>';
	}
	function discStatusText(disc, pct) {
		if (disc.status === 'submitted') return 'Quote requested';
		if (disc.status === 'reviewed') return 'Reviewed by AdaptExec';
		if (disc.status === 'client_done') return 'Client finished · review it';
		return pct ? 'In progress' : 'Not started';
	}
	function discReadyLine(r) {
		if (!r || !r.total) return 'Not started';
		return 'Quote readiness ' + r.pct + '%' + (r.ready ? ' · ready' : ' · unlocks at ' + r.threshold + '%');
	}
	function discAnswered(f, v, modules) {
		if (f.type === 'modules') return Object.keys(modules || {}).length > 0;
		var ok = Array.isArray(v) ? v.some(function (x) {
			return x && typeof x === 'object' ? Object.keys(x).some(function (k) { return String(x[k] || '').trim(); }) : String(x == null ? '' : x).trim() !== '';
		}) : String(v == null ? '' : v).trim() !== '';
		return ok || (!!f.or_modules && Object.keys(modules || {}).length > 0);
	}
	function discReady() {
		var key = [], all = [];
		discSchema().forEach(function (sec) {
			if (sec.optional || sec.audience === 'internal') return;
			sec.fields.forEach(function (f) {
				var row = { label: String(f.label).replace(/&amp;/g, '&'), ok: discAnswered(f, val(sec.key, f.key), S.disc.modules) };
				all.push(row);
				if (f.essential) key.push(row);
			});
		});
		var set = key.length ? key : all, done = set.filter(function (r) { return r.ok; }).length;
		var pct = set.length ? Math.round(done / set.length * 100) : 0, need = key.length ? 70 : 50;
		return { pct: pct, filled: done, total: set.length, threshold: need, ready: set.length > 0 && pct >= need,
			missing: set.filter(function (r) { return !r.ok; }).map(function (r) { return r.label; }) };
	}
	function srcTag(sec, key) {
		var s = (S.disc && S.disc.sources || {})[sec + '.' + key];
		return s && s.by === 'client' ? ' <span class="axh-src">From the client</span>' : '';
	}
	function quoteModal() {
		var r = discReady(), again = S.disc.status === 'submitted' || S.disc.status === 'reviewed';
		var miss = r.missing.slice(0, 8).map(esc).join(' · ');
		var body = r.ready
			? '<p style="font-size:14px;margin-bottom:12px">Adam gets the brief in the Hub and by email, and writes the proposal from it in Billing. ' +
				'There is no discovery call unless he asks for one. Quote readiness: <b>' + r.pct + '%</b>.</p>' +
				(r.missing.length ? '<p class="axh-hint" style="margin-bottom:12px">Still blank: ' + miss + '</p>' : '') +
				'<div class="axh-field"><label for="q-note">Note for Adam (optional)</label><textarea id="q-note" data-quote-note placeholder="Anything you promised to find out, deadlines, who to contact…"></textarea></div>'
			: '<div class="axh-warn" style="margin-bottom:12px"><b>' + icon('lock', 14) + ' ' + r.pct + '% ready — Request a quote unlocks at ' + r.threshold + '%</b>' +
				'<p>Still needed: ' + miss + (r.missing.length > 8 ? ' and ' + (r.missing.length - 8) + ' more' : '') + '</p></div>' +
				'<p style="font-size:14px;margin-bottom:12px">Keep filling it in, or send their questionnaire link. If it can’t be answered yet, request the quote anyway and tell Adam what is missing and why.</p>' +
				'<div class="axh-field"><label for="q-note">Note for Adam (required to request it now)</label><textarea id="q-note" data-quote-note placeholder="e.g. Owner will send the software list Friday; budget not discussed yet."></textarea></div>';
		modal(again ? 'Send Adam an updated brief?' : 'Request a quote from Adam', body,
			'<button class="axh-btn axh-btn--ghost" data-close>Keep working</button>' +
			'<button class="axh-btn axh-btn--primary" data-quote-go' + (r.ready ? '' : ' data-override') + '>' + (r.ready ? 'Request a quote' : 'Request anyway with my note') + '</button>');
	}

	function renderDiscovery() {
		if (S.disc.trim) return renderDiscoveryTrim();
		var schema = discSchema(), i = Math.min(S.disc.section, Math.max(0, schema.length - 1)), sec = schema[i];
		if (!sec) return '<div class="axh-card">' + empty('Nothing to ask',
			'Every section is switched off. Choose what to ask first.', '') +
			'<button class="axh-btn axh-btn--primary" data-disc-trim>Choose sections</button></div>';
		S.disc.section = i;
		var pct = discProgress(), rd = discReady(), quoted = S.disc.status === 'submitted' || S.disc.status === 'reviewed';

		var html = '<a class="axh-btn axh-btn--quiet axh-btn--sm" href="#/lead/' + S.disc.leadId + '" style="margin-bottom:14px">' + icon('back', 14) + ' Back to lead</a>' +
			'<div class="axh-page-head"><div><h1 class="thunder">Discovery questionnaire</h1>' +
			'<p>You run discovery: fill this in with the prospect in your meetings, or send them their link and fill the gaps. Adam writes the quote from it, so the more you gather, the fewer questions come back.</p></div>' +
			'<div class="axh-actions">' +
			'<button class="axh-btn axh-btn--quiet" data-disc-trim>' + icon('check', 14) + ' Choose sections</button>' +
			'<a class="axh-btn axh-btn--quiet" href="' + esc(location.origin + '/d/preview/' + (S.disc.industry ? '?industry=' + encodeURIComponent(S.disc.industry) : '')) + '" target="_blank" rel="noopener">' + icon('eye', 14) + ' Preview client view</a>' +
			'<button class="axh-btn axh-btn--ghost" data-disc-share>' + icon('link', 14) + ' Send to client</button>' +
			'<button class="axh-btn axh-btn--ghost" data-disc-save>Save draft</button>' +
			'<button class="axh-btn axh-btn--primary' + (rd.ready || quoted ? '' : ' is-locked') + '" data-disc-submit>' + (rd.ready || quoted ? '' : icon('lock', 14) + ' ') + (quoted ? 'Update quote request' : 'Request a quote') + '</button></div></div>' +

			'<div class="axh-ring"><div class="axh-ring__n">' + pct + '%</div><div class="axh-ring__b">' +
			'<strong style="font-size:13.5px;color:var(--ink)">' + sec.name.replace(/&amp;/g, '&') + '</strong>' +
			'<div class="axh-ring__bar"><i style="width:' + pct + '%"></i></div></div>' +
			'<span class="axh-ring__of">' + schema.length + ' of ' + (S.disc.full || []).length + ' sections</span></div>' +
			'<div class="axh-quote' + (rd.ready ? ' is-ready' : '') + '"><div class="axh-quote__h"><b>Quote readiness ' + rd.pct + '%</b>' +
				'<span>' + (quoted ? 'Quote requested ' + when(S.disc.quotedAt) : (rd.ready ? 'Ready — press Request a quote' : 'Request a quote unlocks at ' + rd.threshold + '%')) + '</span></div>' +
				'<div class="axh-quote__bar" role="progressbar" aria-label="Quote readiness" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + rd.pct + '"><i style="width:' + rd.pct + '%"></i><s style="left:' + rd.threshold + '%"></s></div>' +
				(rd.missing.length ? '<p>Key questions still blank: ' + rd.missing.slice(0, 6).map(esc).join(' · ') + (rd.missing.length > 6 ? ' and ' + (rd.missing.length - 6) + ' more' : '') + '</p>' : '<p>Every key question is answered.</p>') + '</div>' +

			'<div class="axh-disc"><div class="axh-disc__nav">' + schema.map(function (s, n) {
				return '<button data-sec="' + n + '" class="' + (n === i ? 'is-on' : (sectionDone(s) ? 'is-done' : '')) + '">' +
					'<span class="axh-disc__num">' + (n + 1) + '</span>' + s.name + '</button>';
			}).join('') + '</div>' +

			'<div class="axh-card"><div class="axh-card__h"><h3>' + sec.name + '</h3></div>' +
			(sec.blurb ? '<p style="font-size:13.5px;color:var(--gray);margin-bottom:18px">' + sec.blurb + '</p>' : '') +
			groupedFields(sec) +
			'<div style="display:flex;justify-content:space-between;gap:8px;margin-top:22px;padding-top:16px;border-top:1px solid var(--line)">' +
			(i > 0 ? '<button class="axh-btn axh-btn--ghost" data-sec="' + (i - 1) + '">' + icon('left', 14) + ' Previous</button>' : '<span></span>') +
			(i < schema.length - 1 ? '<button class="axh-btn axh-btn--dark" data-sec="' + (i + 1) + '">Next: ' + schema[i + 1].name + ' ' + icon('right', 14) + '</button>' : '<button class="axh-btn axh-btn--primary" data-disc-submit>' + (quoted ? 'Update quote request' : 'Request a quote') + '</button>') +
			'</div></div></div>';
		return html;
	}

	/* The packet carries a group heading on each question; the general schema
	   does not. Where headings exist they are worth showing — 30 questions in
	   one undifferentiated run is hard to read. */
	function groupedFields(sec) {
		var out = '', last = null;
		sec.fields.forEach(function (f) {
			var g = f.group || '';
			if (g !== last) {
				if (g) out += '<div class="axh-disc__group">' + esc(g) + '</div>';
				last = g;
			}
			out += field(sec.key, f);
		});
		return out;
	}

	/* Choose what this client is asked, before sending it to them. */
	function renderDiscoveryTrim() {
		var full = S.disc.full || [], cfg = S.disc.config || {};
		var offS = cfg.sections || {}, offQ = cfg.questions || {};
		var on = full.filter(function (s) { return offS[s.key] !== false; }).length;
		var asked = discSchema().reduce(function (n, s) { return n + s.fields.length; }, 0);

		return '<button class="axh-btn axh-btn--quiet axh-btn--sm" data-disc-trim-done style="margin-bottom:14px">' +
			icon('back', 14) + ' Back to the questionnaire</button>' +
			'<div class="axh-page-head"><div><h1 class="thunder">Choose what to ask</h1>' +
			'<p>Switch off what this business does not need. It changes the form you fill in and the one they see.</p></div>' +
			'<div class="axh-actions">' +
			'<button class="axh-btn axh-btn--ghost" data-disc-preset="essential">The essentials</button>' +
			'<button class="axh-btn axh-btn--ghost" data-disc-preset="all">Everything</button>' +
			'<button class="axh-btn axh-btn--primary" data-disc-trim-done>Done</button></div></div>' +
			'<div class="axh-ring"><div class="axh-ring__n">' + on + '</div><div class="axh-ring__b">' +
			'<strong style="font-size:13.5px;color:var(--ink)">sections on, ' + asked + ' questions</strong>' +
			'<div class="axh-ring__bar"><i style="width:' + Math.round(on / Math.max(1, full.length) * 100) + '%"></i></div>' +
			'</div></div>' +
			'<div class="axh-card">' + full.map(function (s) {
				var secOn = offS[s.key] !== false;
				var qs = s.fields.filter(function (f) { return !offQ[f.key]; }).length;
				return '<div class="axh-trim' + (secOn ? '' : ' is-off') + '">' +
					'<label class="axh-trim__s"><input type="checkbox" data-trim-sec="' + s.key + '"' +
					(secOn ? ' checked' : '') + '><b>' + esc(s.name) + '</b>' +
					'<span>' + qs + ' of ' + s.fields.length + ' questions' +
					(s.tier === 'essential' ? ' · essential' : '') +
					(s.audience === 'internal' ? ' · not shown to the client' : '') + '</span></label>' +
					'<button class="axh-btn axh-btn--quiet axh-btn--sm" data-trim-open="' + s.key + '">Questions</button>' +
					'<div class="axh-trim__q" data-trim-body="' + s.key + '" hidden>' +
					s.fields.map(function (f) {
						return '<label><input type="checkbox" data-trim-q="' + f.key + '"' +
							(offQ[f.key] ? '' : ' checked') + '> ' + esc(f.label) + '</label>';
					}).join('') + '</div></div>';
			}).join('') + '</div>';
	}

	function saveDiscoveryConfig() {
		return api('/leads/' + S.disc.leadId + '/discovery/config', {
			method: 'POST', body: { config: S.disc.config }
		}).catch(function (e) { toast(e.message, 'bad'); });
	}

	function shareDiscovery() {
		return api('/leads/' + S.disc.leadId + '/discovery/share', { method: 'POST', body: {} })
			.then(function (r) {
				S.disc.shareUrl = r.share_url;
				modal(
					'Send it to them',
					'<p style="font-size:13.5px;color:var(--gray);margin-bottom:12px">' +
					'They answer the sections you left on. No login, and what they write comes ' +
					'straight back to this lead — the pages meant for us are never shown.</p>' +
					'<div class="axh-copyrow"><input type="text" readonly value="' + esc(r.share_url) + '"></div>',
					'<button class="axh-btn axh-btn--primary" data-copy="' + esc(r.share_url) + '">Copy link</button>'
				);
			}).catch(function (e) { toast(e.message, 'bad'); });
	}

	function val(sec, key) {
		return (S.disc.payload[sec] && S.disc.payload[sec][key] !== undefined) ? S.disc.payload[sec][key] : '';
	}
	function setVal(sec, key, v) {
		if (!S.disc.payload[sec]) S.disc.payload[sec] = {};
		S.disc.payload[sec][key] = v;
	}

	function field(sec, f) {
		var v = val(sec, f.key);
		var head = '<div class="axh-field"><label for="f-' + sec + '-' + f.key + '">' + f.label + srcTag(sec, f.key) + (f.essential ? ' <span class="axh-key" title="Key question for the quote">key</span>' : '') + '</label>' +
			(f.help ? '<div class="axh-field__help">' + f.help + '</div>' : '');
		var id = 'f-' + sec + '-' + f.key;
		var attr = 'id="' + id + '" data-sec="' + sec + '" data-key="' + f.key + '"';

		switch (f.type) {
			case 'textarea':
				return head + '<textarea ' + attr + ' data-in>' + esc(v) + '</textarea></div>';
			case 'number':
				return head + '<input type="number" ' + attr + ' data-in value="' + esc(v) + '"></div>';
			case 'money':
				return head + '<input type="text" inputmode="decimal" placeholder="$" ' + attr + ' data-in value="' + esc(v) + '"></div>';
			case 'date':
				return head + '<input type="date" ' + attr + ' data-in value="' + esc(v) + '"></div>';
			case 'select':
				return head + '<select ' + attr + ' data-in><option value="">Choose…</option>' +
					f.options.map(function (o) { return '<option' + (v === o ? ' selected' : '') + '>' + o + '</option>'; }).join('') + '</select></div>';
			case 'scale':
				// One tap, on one row. The packet asks 101 of these; as
				// dropdowns they would be the reason nobody finishes it.
				return '<div class="axh-field axh-field--scale"><div class="axh-field__q">' +
					'<label for="' + id + '">' + f.label + '</label>' +
					(f.help ? '<div class="axh-field__help">' + f.help + '</div>' : '') + '</div>' +
					'<div class="axh-seg" role="radiogroup" aria-label="' + esc(f.label) + '">' +
					f.options.map(function (o) {
						return '<button type="button" role="radio" aria-checked="' + (v === o) + '"' +
							(v === o ? ' class="is-on"' : '') +
							' data-scale="' + esc(o) + '" data-sec="' + sec + '" data-key="' + f.key + '">' +
							o + '</button>';
					}).join('') + '</div></div>';
			case 'rating':
				return head + '<div class="axh-chips">' + [1, 2, 3, 4, 5].map(function (n) {
					return '<button class="axh-chip' + (String(v) === String(n) ? ' is-must' : '') + '" data-rate="' + n + '" data-sec="' + sec + '" data-key="' + f.key + '">' + n + '</button>';
				}).join('') + '</div></div>';
			case 'checklist':
				var arr = Array.isArray(v) ? v : [];
				return head + '<div class="axh-chips">' + f.options.map(function (o) {
					return '<button class="axh-chip' + (arr.indexOf(o) > -1 ? ' is-on' : '') + '" data-check="' + esc(o) + '" data-sec="' + sec + '" data-key="' + f.key + '">' + o + '</button>';
				}).join('') + '</div></div>';
			case 'stack':
				var rows = Array.isArray(v) ? v : [];
				if (!rows.length) rows = [{ name: '', use: '', cost: '' }];
				return head + '<div data-stack="' + sec + '|' + f.key + '">' +
					rows.map(function (r, n) { return stackRow(r, n); }).join('') +
					'</div><button class="axh-btn axh-btn--ghost axh-btn--sm" data-stack-add="' + sec + '|' + f.key + '" style="margin-top:6px">' + icon('plus', 13) + ' Add software</button></div>';
			case 'modules':
				return head + modulePicker() + '</div>';
			default:
				return head + '<input type="text" ' + attr + ' data-in value="' + esc(v) + '"></div>';
		}
	}

	function stackRow(r, n) {
		return '<div class="axh-stack__row" data-row="' + n + '">' +
			'<input type="text" placeholder="Software name" data-field="name" value="' + esc(r.name || '') + '">' +
			'<input type="text" placeholder="What it does for them" data-field="use" value="' + esc(r.use || '') + '">' +
			'<input type="text" placeholder="$/month" data-field="cost" value="' + esc(r.cost || '') + '">' +
			'<button class="axh-btn axh-btn--quiet axh-btn--sm" data-row-del="' + n + '">' + icon('trash', 14) + '</button></div>';
	}

	function modulePicker() {
		var picked = S.disc.modules || {};
		var must = 0, nice = 0;
		Object.keys(picked).forEach(function (k) { picked[k] === 'must' ? must++ : nice++; });

		return '<div class="axh-modlegend">' +
			'<span><span class="axh-chip__dot" style="display:inline-block;margin-right:5px"></span>Running in production today</span>' +
			'<span><span class="axh-chip__dot axh-chip__dot--r" style="display:inline-block;margin-right:5px"></span>Built on request</span>' +
			'<span style="color:var(--o);font-weight:700">' + must + ' must-have · ' + nice + ' nice-to-have</span></div>' +
			'<p style="font-size:12.5px;color:var(--gray);margin-bottom:14px">Tap once for <b>must-have</b>, twice for <b>nice-to-have</b>, three times to clear.</p>' +
			S.boot.modules.map(function (cat) {
				return '<div class="axh-modcat"><div class="axh-modcat__h"><h4>' + cat.name + '</h4>' +
					'<span>' + cat.modules.length + ' modules</span></div><div class="axh-chips">' +
					cat.modules.map(function (m) {
						var lvl = picked[m.name];
						return '<button class="axh-chip' + (lvl === 'must' ? ' is-must' : lvl === 'nice' ? ' is-nice' : '') + '" data-mod="' + esc(m.name) + '">' +
							'<span class="axh-chip__dot' + (m.live ? '' : ' axh-chip__dot--r') + '"></span>' + m.name + '</button>';
					}).join('') + '</div></div>';
			}).join('');
	}

	function sectionDone(sec) {
		var n = 0;
		sec.fields.forEach(function (f) {
			if (f.type === 'modules') { if (Object.keys(S.disc.modules || {}).length) n++; return; }
			var v = val(sec.key, f.key);
			if (Array.isArray(v) ? v.length : String(v).trim()) n++;
		});
		return n >= Math.ceil(sec.fields.length / 2);
	}
	function discProgress() {
		var total = 0, filled = 0;
		// Against the form this client was sent, so a trimmed one can reach 100%.
		discSchema().forEach(function (sec) {
			if (sec.optional) return;
			sec.fields.forEach(function (f) {
				total++;
				if (f.type === 'modules') { if (Object.keys(S.disc.modules || {}).length) filled++; return; }
				var v = val(sec.key, f.key);
				if (Array.isArray(v) ? v.length : String(v).trim()) filled++;
			});
		});
		return total ? Math.round(filled / total * 100) : 0;
	}

	function saveDiscovery(submit, note) {
		return api('/leads/' + S.disc.leadId + '/discovery', {
			method: 'POST',
			body: { payload: S.disc.payload, modules: S.disc.modules, submit: !!submit, note: note || '' }
		}).then(function (r) {
			if (r && r.discovery) { S.disc.sources = r.discovery.sources || {}; S.disc.status = r.discovery.status || S.disc.status; S.disc.quotedAt = r.discovery.submitted_at || S.disc.quotedAt; }
			toast(submit ? 'Quote requested. Adam has the brief.' : 'Draft saved.', 'ok');
			if (submit) location.hash = '#/lead/' + S.disc.leadId;
			return r;
		}).catch(function (e) { toast(e.message, 'bad'); });
	}

	/* --------------------------------------------------------- present */

	var P = { deck: null, page: 1, pages: 1, sessionId: null, t0: 0, furthest: 1 };

	function viewPresent() {
		var decks = S.boot.slideDecks || [];
		if (!decks.length) {
			return '<div class="axh-page-head"><div><h1 class="thunder">Present</h1></div></div>' +
				'<div class="axh-card">' + empty('No decks installed yet',
					'Ask your administrator to add the presentation decks to the Hub.', '') + '</div>';
		}
		/* Decks from the content plugin carry a thumbnail, a group, a length and
		   a "best for" line; older decks without them still render as before. */
		function card(d) {
			var cover = d.thumb
				? '<div class="axh-deck__cover" style="height:auto;aspect-ratio:16/9;background:#0b0b0b;display:block">' +
					'<img src="' + esc(d.thumb) + '" alt="" loading="lazy" style="display:block;width:100%;height:100%;object-fit:cover">' +
					'<i style="background:' + esc(d.accent) + '"></i></div>'
				: '<div class="axh-deck__cover" style="background-image:linear-gradient(150deg,' +
					esc(d.accent) + '55,transparent 68%)">' +
					'<b>' + esc(d.title) + '</b><i style="background:' + esc(d.accent) + '"></i></div>';
			var meta = [d.count ? d.count + ' slides' : '', d.minutes ? '~' + d.minutes + ' min' : '',
				d.live ? 'Live demo inside' : (d.group === 'concept' ? 'Concept previews' : '')].filter(Boolean).join(' · ');
			return '<div class="axh-deck axh-deck--slides">' + cover +
				'<div class="axh-deck__b"><strong>' + esc(d.title) + '</strong>' +
				'<span>' + esc(meta || d.kicker || (d.count + ' slides')) + '</span>' +
				(d.best ? '<span style="display:block;margin-top:6px;line-height:1.45">Best for: ' + esc(d.best) + '</span>' : '') +
				'</div>' +
				'<div class="axh-deck__acts">' +
					'<button class="axh-btn axh-btn--primary axh-btn--sm" data-slides="' + esc(d.slug) + '">Present</button>' +
					'<button class="axh-btn axh-btn--ghost axh-btn--sm" data-share-deck="' + esc(d.slug) + '">Share a link</button>' +
				'</div></div>';
		}
		var groups = [
			['master', 'Start here', 'The full story: the subscription problem, how we work, all ' + ((S.boot.p1 && S.boot.p1.demoCount) || 19) + ' live demos, proof and next steps.'],
			['live', 'Industry decks with a live demo', 'Website, CRM, automations, dashboard and AI for one industry, with its live demo inside the deck.'],
			['concept', 'More industries', 'The same story with concept previews and sample CRM screens.']
		];
		var known = {};
		groups.forEach(function (g) { known[g[0]] = true; });
		var rest = decks.filter(function (d) { return !known[d.group || '']; });
		var html = '<div class="axh-page-head"><div><h1 class="thunder">Present</h1>' +
			'<p>Full screen slides, arrow keys or a clicker to move. Press P for presenter view (notes, next slide, timer) and N for notes. ' +
			'Share a deck as a link and you see every visit, the time spent on each slide and every click on your button.</p></div></div>';
		groups.forEach(function (g) {
			var list = decks.filter(function (d) { return (d.group || '') === g[0]; });
			if (!list.length) return;
			html += '<div class="axh-page-head" style="margin-top:18px"><div><h2 class="thunder">' + esc(g[1]) + '</h2><p>' + esc(g[2]) + '</p></div></div>' +
				'<div class="axh-decks">' + list.map(card).join('') + '</div>';
		});
		if (rest.length) html += (html.indexOf('axh-decks') > -1 ? '<div style="height:18px"></div>' : '') + '<div class="axh-decks">' + rest.map(card).join('') + '</div>';
		return html;
	}

	/* Live presenting runs the same player the prospect would see, signed in,
	   so nothing a rep rehearses counts against a share link's numbers. */
	function openSlides(slug, leadId) {
		var wrap = el('axh-present');
		var lead = leadId && S.lead && S.lead.lead && String(S.lead.lead.id) === String(leadId) ? S.lead.lead : null;
		wrap.hidden = false;
		wrap.className = 'axh-present';
		P.slidesMode = true; P.slides = 0; P.leadId = leadId || null;
		wrap.innerHTML =
			'<div class="axh-present__bar"><b>Presenting' + (lead ? ' to ' + esc(lead.company) : '') + '</b>' +
			'<span id="axh-p-pos" style="color:#8A929C"></span>' +
			'<div class="axh-present__spacer"></div>' +
			'<a class="axh-present__btn" href="' + esc((S.boot.deckBase || '/deck/') + slug + '/') + '" target="_blank" rel="noopener">Open in a new tab</a>' +
			'<button class="axh-present__btn axh-present__btn--o" data-p-close>Done</button></div>' +
			'<iframe class="axh-present__full" src="' + esc((S.boot.deckBase || '/deck/') + slug + '/') +
			'" title="Presentation" allow="fullscreen; autoplay" allowfullscreen></iframe>';
		api('/sessions', { method: 'POST', body: { deck: slug, lead_id: leadId || null } })
			.then(function (r) { P.sessionId = r.id; }).catch(function () {});
		P.t0 = Date.now();
		P.furthest = 1;
	}

	function openPresent(slug, leadId) {
		var deck = S.boot.decks.filter(function (d) { return d.slug === slug; })[0];
		if (!deck) return;
		P.deck = deck; P.page = 1; P.furthest = 1; P.t0 = Date.now();

		var wrap = el('axh-present');
		wrap.hidden = false;
		wrap.className = 'axh-present';
		wrap.innerHTML =
			'<div class="axh-present__bar">' +
			'<b>' + esc(deck.title) + '</b>' +
			'<span id="axh-p-sub" style="color:#8A929C">Loading…</span>' +
			'<div class="axh-present__spacer"></div>' +
			(deck.pdf ? '<a class="axh-present__btn" href="' + esc(deck.pdf) + '" target="_blank" rel="noopener">PDF</a>' : '') +
			'<button class="axh-present__btn" data-p-clean>Hide controls</button>' +
			'<button class="axh-present__btn" data-p-full>Full screen</button>' +
			'<button class="axh-present__btn axh-present__btn--o" data-p-close>Done</button></div>' +
			'<div class="axh-present__stage" id="axh-p-stage">' +
			'<iframe class="axh-present__frame" id="axh-p-frame" src="' + esc(deck.url) + '" title="' + esc(deck.title) + '"></iframe>' +
			'<button class="axh-present__nav axh-present__nav--prev" data-p-prev aria-label="Previous">' + icon('left', 22) + '</button>' +
			'<button class="axh-present__nav axh-present__nav--next" data-p-next aria-label="Next">' + icon('right', 22) + '</button>' +
			'<div class="axh-present__count" id="axh-p-count"></div></div>';

		var frame = el('axh-p-frame');
		frame.onload = function () { preparePages(frame); };

		api('/sessions', { method: 'POST', body: { deck: slug, lead_id: leadId || null } })
			.then(function (r) { P.sessionId = r.id; }).catch(function () {});

		document.addEventListener('keydown', presentKeys);
	}

	/**
	 * The decks are the packet HTML: fixed 8.5in x 11in .page blocks.
	 * Show one at a time and scale it to the stage.
	 */
	function preparePages(frame) {
		var doc;
		try { doc = frame.contentDocument; } catch (e) { return; }
		if (!doc) return;

		var style = doc.createElement('style');
		style.textContent = 'html,body{margin:0;padding:0;background:#fff;overflow:hidden}' +
			'.page{display:none!important;margin:0!important}' +
			'.page.axh-on{display:block!important}';
		doc.head.appendChild(style);

		P.pages = doc.querySelectorAll('.page').length || 1;
		showPage(1);
		fitFrame();
		window.addEventListener('resize', fitFrame);

		/* Keys and clicks land inside the deck once it has focus, so the frame
		   needs its own listeners or the arrows do nothing. */
		doc.addEventListener('keydown', presentKeys);
		doc.addEventListener('click', function () { showPage(P.page + 1); });
	}

	function showPage(n) {
		var frame = el('axh-p-frame');
		if (!frame) return;
		var doc = frame.contentDocument;
		if (!doc) return;
		var pages = doc.querySelectorAll('.page');
		if (!pages.length) return;
		n = Math.max(1, Math.min(P.pages, n));
		P.page = n;
		P.furthest = Math.max(P.furthest, n);
		for (var i = 0; i < pages.length; i++) pages[i].classList.toggle('axh-on', i === n - 1);
		var c = el('axh-p-count');
		if (c) c.textContent = n + ' / ' + P.pages;
		var sub = el('axh-p-sub');
		if (sub) sub.textContent = P.pages + ' pages';
	}

	function fitFrame() {
		var stage = el('axh-p-stage'), frame = el('axh-p-frame');
		if (!stage || !frame) return;
		var pw = 816, ph = 1056;                       /* 8.5in x 11in at 96dpi */
		var s = Math.max(0.2, Math.min((stage.clientWidth - 90) / pw, (stage.clientHeight - 30) / ph));
		frame.style.width = pw + 'px';
		frame.style.height = ph + 'px';
		frame.style.transform = 'scale(' + s + ')';
		frame.style.left = Math.round((stage.clientWidth - pw * s) / 2) + 'px';
		frame.style.top = Math.round((stage.clientHeight - ph * s) / 2) + 'px';
	}

	function presentKeys(e) {
		if (el('axh-present').hidden) return;
		if (e.key === 'ArrowRight' || e.key === ' ' || e.key === 'PageDown') { e.preventDefault(); showPage(P.page + 1); }
		if (e.key === 'ArrowLeft' || e.key === 'PageUp') { e.preventDefault(); showPage(P.page - 1); }
		if (e.key === 'Escape') closePresent();
		if (e.key === 'f') toggleFull();
	}

	function toggleFull() {
		var w = el('axh-present');
		if (!document.fullscreenElement) { (w.requestFullscreen && w.requestFullscreen()) || null; }
		else if (document.exitFullscreen) document.exitFullscreen();
	}

	function closePresent() {
		if (P.sessionId) {
			var logged = P.slidesMode && P.leadId;
			api('/sessions/' + P.sessionId, {
				method: 'POST',
				body: { furthest: P.furthest, seconds: Math.round((Date.now() - P.t0) / 1000), slides: P.slides || 0 }
			}).then(function () {
				if (logged) { toast('Logged on the deal: slide ' + P.furthest + (P.slides ? ' of ' + P.slides : '') + '.', 'ok'); if (/^#\/lead\//.test(location.hash)) route(); }
			}).catch(function () {});
			P.sessionId = null;
		}
		P.slidesMode = false; P.leadId = null;
		document.removeEventListener('keydown', presentKeys);
		window.removeEventListener('resize', fitFrame);
		if (document.fullscreenElement && document.exitFullscreen) document.exitFullscreen();
		var w = el('axh-present');
		w.hidden = true;
		w.innerHTML = '';
	}


	/* --------------------------------------------------------- forms */

	function viewForms(d) {
		var forms = d.forms || [];
		var html = '<div class="axh-page-head"><div><h1 class="thunder">Lead forms</h1>' +
			'<p>Share a link anywhere — a video description, a QR code on a flyer, a text message. Every submission lands in your pipeline tagged with where it came from.</p></div>' +
			'<button class="axh-btn axh-btn--primary" data-new-form>' + icon('plus', 15) + ' New form</button></div>';

		if (!forms.length) {
			return html + '<div class="axh-card">' + emptyForm() + '</div>';
		}

		var totalViews = forms.reduce(function (a, f) { return a + Number(f.views || 0); }, 0);
		var totalSubs = forms.reduce(function (a, f) { return a + Number(f.submissions || 0); }, 0);

		html += '<div class="axh-stats">' +
			stat('Live forms', forms.filter(function (f) { return Number(f.active); }).length, forms.length + ' total', false) +
			stat('Views', totalViews, 'across every form', false) +
			stat('Submissions', totalSubs, totalViews ? Math.round(totalSubs / totalViews * 100) + '% of views convert' : 'no views yet', true) +
			stat('Leads waiting', (d.submissions || []).length, 'from forms, most recent first', false) +
			'</div>';

		html += '<div class="axh-formgrid">' + forms.map(function (f) {
			var conv = Number(f.views) ? Math.round(f.submissions / f.views * 100) : 0;
			return '<div class="axh-formcard">' +
				'<div class="axh-formcard__top" style="background:' + esc(f.accent) + '">' +
				'<span class="axh-formcard__badge">' + (Number(f.active) ? 'Live' : 'Paused') + '</span>' +
				'<b>' + esc(f.headline || f.name) + '</b></div>' +
				'<div class="axh-formcard__b">' +
				'<strong>' + esc(f.name) + '</strong>' +
					'<span class="axh-formcard__src">' + (f.source_tag ? icon('link', 12) + ' ' + esc(f.source_tag) : 'No source tag') + '</span>' +
					(S.boot.user.manager && f.rep_name ? '<span class="axh-formcard__src">' + icon('users', 12) + ' Leads go to ' + esc(f.rep_name) + '</span>' : '') +
				'<div class="axh-formcard__stats"><span><b>' + f.views + '</b> views</span><span><b>' + f.submissions + '</b> leads</span><span><b>' + conv + '%</b></span></div>' +
				'<div class="axh-formcard__acts">' +
				'<button class="axh-btn axh-btn--primary axh-btn--sm" data-form="' + f.id + '">Open</button>' +
				'<button class="axh-btn axh-btn--ghost axh-btn--sm" data-copy="' + esc(f.url) + '">' + icon('copy', 13) + ' Copy link</button>' +
				'</div></div></div>';
		}).join('') + '</div>';

		if ((d.submissions || []).length) {
			// Hub admins can delete stored submissions (1.7.2); reps cannot.
			var subAdm = !!(S.boot.user && S.boot.user.manager);
			html += '<div class="axh-card" style="margin-top:16px"><div class="axh-card__h"><h3>Recent submissions</h3><span style="display:flex;gap:8px;align-items:center">' +
				'<a class="axh-btn axh-btn--ghost axh-btn--sm" href="#/submissions">See all · search · export</a>' +
				(subAdm ? '<button class="axh-btn axh-btn--danger axh-btn--sm" data-sub-bulk disabled>Delete selected</button>' : '') + '</span></div>' +
				'<table class="axh-table axh-table--fit' + (subAdm ? ' axh-subtable' : '') + '"><thead><tr>' +
				(subAdm ? '<th class="axh-subpick"><input type="checkbox" data-sub-all aria-label="Select all submissions"></th>' : '') +
				'<th>Who</th><th>Business</th><th>Source</th><th>When</th>' + (subAdm ? '<th><span class="axh-sr">Actions</span></th>' : '') + '</tr></thead><tbody>' +
				d.submissions.slice(0, 12).map(function (sb) {
					var pl = sb.payload || {};
					return '<tr' + (sb.lead_id ? ' data-open="' + sb.lead_id + '"' : '') + ' data-sub-row="' + sb.id + '">' +
						(subAdm ? '<td class="axh-subpick"><input type="checkbox" data-sub-pick="' + sb.id + '" aria-label="Select submission #' + sb.id + '"></td>' : '') +
						'<td><div class="axh-co">' + esc(pl.name || 'Someone') + '<span>' + esc(pl.email || '') + '</span></div></td>' +
						'<td>' + esc(pl.company || '—') + '</td>' +
						'<td><span class="axh-badge axh-badge--orange">' + esc(sb.source || 'form') + '</span></td>' +
						'<td style="color:var(--gray)">' + when(sb.created_at) + '</td>' +
						(subAdm ? '<td style="text-align:right"><button class="axh-btn axh-btn--ghost axh-btn--sm" data-sub-del="' + sb.id + '">Delete</button></td>' : '') + '</tr>';
				}).join('') + '</tbody></table></div>';
		}
		return html;
	}

	function subPicked() {
		var n = document.querySelectorAll('[data-sub-pick]:checked').length, b = document.querySelector('[data-sub-bulk]');
		var all = document.querySelector('[data-sub-all]'), tot = document.querySelectorAll('[data-sub-pick]').length;
		if (all) { all.checked = !!n && n === tot; all.indeterminate = !!n && n < tot; }
		if (b) { b.disabled = !n; b.textContent = n ? 'Delete selected (' + n + ')' : 'Delete selected'; }
	}
	function delSubs(ids) {
		var one = ids.length === 1;
		var m = modal(one ? 'Delete this submission?' : 'Delete ' + ids.length + ' submissions?',
			'<p style="font-size:13.5px">' + (one ? 'The stored answers are' : 'Their stored answers are') + ' permanently removed. Any lead ' + (one ? 'it' : 'they') + ' opened stays in the pipeline. This cannot be undone.</p>',
			'<button class="axh-btn axh-btn--ghost" data-close>Keep</button><button class="axh-btn axh-btn--danger" data-yes>Delete</button>');
		m.querySelector('[data-yes]').onclick = function () {
			(one ? api('/forms/submissions/' + ids[0], { method: 'DELETE' }) : api('/forms/submissions/delete', { method: 'POST', body: { ids: ids.map(Number) } }))
				.then(function () { closeModal(); toast(one ? 'Submission deleted.' : ids.length + ' submissions deleted.', 'ok'); route(); })
				.catch(function (er) { closeModal(); toast(er.message, 'bad'); });
		};
	}

	function emptyForm() {
		return '<div class="axh-empty">' + icon('link', 40) +
			'<h4>Make your first link</h4>' +
			'<p>A form takes a minute to build and works everywhere — in a video description, on a printed flyer as a QR code, or texted to someone who asked what you do.</p>' +
			'<button class="axh-btn axh-btn--primary" data-new-form>Create a form</button></div>';
	}

	function fInp(k, label, v, req, ph) {
		return '<div class="axh-field"><label>' + label + (req ? ' *' : '') + '</label>' +
			'<input type="text" data-ff="' + k + '" value="' + esc(v || '') + '" placeholder="' + esc(ph || '') + '"></div>';
	}

	function paintQR(url) {
		var box = el('axh-qr');
		if (!box || !window.AXHQR) return;
		try {
			box.innerHTML = AXHQR.svg(url, { quiet: 3 });
		} catch (e) {
			box.innerHTML = '<p style="font-size:12.5px;color:var(--gray)">Link is too long for a QR code.</p>';
		}
	}

	/* --------------------------------------------------------- library */

	/* Sales kit: onboarding, positioning, proof, and the tools reps send.
	   Edit KIT below to update the copy; commission rates themselves come
	   from AXH_Install::commission_tiers() on the Earnings page. */
	var KIT = {
		phone: '(602) 584-0614',
		email: 'support' + '@' + 'adaptexec.com',
		site: 'https://adaptexec.com',
		video: 'https://adaptexec.com/wp-content/uploads/2026/10/adaptexec-promo-websites-seo-crm.mp4',
		tools: [
			['Promo video (38 sec)', 'Websites, SEO and CRMs in under a minute. Text it before a first meeting.', 'https://adaptexec.com/wp-content/uploads/2026/10/adaptexec-promo-websites-seo-crm.mp4'],
			['Free SEO checker', 'Run their site through it together, then talk about what it finds.', 'https://adaptexec.com/search-engine-optimization/#seo-checker'],
			['CVI Energy Explorer', 'A quick, interactive intro to Core Values Index™ coaching.', 'https://adaptexec.com/cvi-coaching/#cvi-explorer'],
			['Why AdaptExec (no lock-in + savings estimator)', 'The no lock-in, you-own-it story. Have them run the subscription savings estimator with you.', 'https://adaptexec.com/why-adaptexec/#estimate'],
			['Live demo sites', 'All ' + ((S.boot && S.boot.p1 && S.boot.p1.demoCount) || 19) + ' live industry demos in one place, each a full website wired to a CRM.', 'https://adaptexec.com/demos/'],
			['Portfolio', 'Real client work to back up the demos.', 'https://adaptexec.com/portfolio/'],
			['Case study: Miliare Group & Virtuity', 'Financial services websites plus the custom CRM tools thousands of agents use daily.', 'https://adaptexec.com/portfolio/miliare-virtuity-crm/'],
			['Case study: Father’s Advocacy Network', 'Nonprofit website and family-support platform with a CRM behind it.', 'https://adaptexec.com/portfolio/fathers-advocacy-network/'],
			['Case study: HB Beauty', 'Refined, mobile-first medspa website with ongoing SEO.', 'https://adaptexec.com/portfolio/hb-beauty/'],
			['Contact page', 'Where a prospect can book a free consultation directly.', 'https://adaptexec.com/contact-us/']
		],
		services: [
			['Custom websites', 'Designed and built for the business — not a template.', 'https://adaptexec.com/website-design/'],
			['Custom CRMs & web apps', 'One system built around how they already work.', 'https://adaptexec.com/web-apps-and-custom-crm/'],
			['SEO', 'Built for local search, without a $4,000+/month retainer.', 'https://adaptexec.com/search-engine-optimization/'],
			['Marketing, branding & design', 'Logos, brand, print and digital marketing.', 'https://adaptexec.com/marketing-branding/'],
			['Core Values Index™ coaching', 'Adam Neuerburg and Bonnie Juma are Certified CVI™ Coaches through Hardwired Coaching, and travel nationwide.', 'https://adaptexec.com/cvi-coaching/'],
			['CVI™ for teams & sales', 'Hire, lead and sell knowing how people are wired. The page for sales leaders and team owners.', 'https://adaptexec.com/cvi-coaching/teams-and-sales/'],
			['All services', 'The full services overview, including business support.', 'https://adaptexec.com/services/']
		],
		clients: 'Virtuity · Miliare Group (CRM used by thousands of financial agents) · Nova the Beam Guy · ' +
			'Diamondback (dbackpros.com CRM) · Father’s Advocacy Network · PRC Park Rapids · HB Beauty · Land Cruiser Coach Sales'
	};

	/* Sales kit copy for new commission reps (1.6.1; deal flow 1.7.1: reps run
	   discovery with the questionnaire, Adam quotes without a call). Every fact here is
	   published on adaptexec.com; prices are only the approved talk track.
	   {rep} is replaced with the signed-in rep's name. Keep it truthful:
	   agreement 2.5 (pricing), 4.2 (no promises), 4.3 (independent rep),
	   5.2 (TCPA) and 5.3 (CAN-SPAM). */
	var SK = {
		cal: 'https://calendar.app.google/6WUYMnKKRydQSjhs7',
		week: [
			{ d: 'Day 1', t: 'Set up', time: '30–40 min', items: [
				'Accept the invite (the link lasts 7 days) and sign in at <b>adaptexec.com/hub/</b>.',
				'Finish <a href="#/onboarding">Onboarding</a>: sign the agreement (a 15–20 minute read), upload your W-9, enter where checks go.',
				'Add the Hub to your phone’s home screen.',
				'Read <b>Start here</b> and <b>Commission at a glance</b> in the Sales kit (Using the Hub).'] },
			{ d: 'Day 2', t: 'Learn the story', time: '', items: [
				'Present the “What we build” deck to yourself in <a href="#/present">Present</a> with the presenter notes (press P).',
				'Open the <a href="#/demos">demo site</a> and deck for your first two industries.',
				'Try the free SEO checker on adaptexec.com (scan only sites you own or are asked to review).',
				'Practice the <b>pricing answer</b> and the top objections out loud.',
				'Open a lead’s <b>Discovery questionnaire</b> and its <b>Preview client view</b>, and learn what Adam needs to write a quote without a call.'] },
			{ d: 'Day 3', t: 'Build your tools', time: '', items: [
				'Create your <a href="#/forms">lead form</a> and save its link and QR code.',
				'Make a share link for the main deck.',
				'Add your first 10 prospects and <b>register each one</b> before the first real conversation.'] },
			{ d: 'Day 4', t: 'Start conversations', time: '', items: [
				'Call or visit, log every contact and set a follow-up.',
				'Send one deck link and one style or discovery questionnaire link.'] },
			{ d: 'Day 5', t: 'Review', time: '', items: [
				'Clean up stages and check which <a href="#/shares">shared links</a> got views, then follow up.',
				'Do a 15-minute check-in with Adam.'] }
		],
		pricing: {
			track: 'Websites start around $900 and custom CRMs around $1,500. I’ll gather the details of what you need, and AdaptExec will send you a written proposal. There’s no required monthly plan. Anything I say is an estimate until you have that proposal.',
			app: 'Custom software (an app or portal that isn’t a CRM) starts around $2,500. Every quote is custom, based on the details I gather with you.',
			appSource: ['App Idea Scoper FAQ', 'https://adaptexec.com/tools/app-idea-scoper/'],
			sources: [
				['Website design FAQ', 'https://adaptexec.com/website-design/'],
				['Custom CRM FAQ', 'https://adaptexec.com/web-apps-and-custom-crm/'],
				['Contact page FAQ', 'https://adaptexec.com/contact-us/']
			],
			doList: [
				'Say the talk track word for word. Call every number an <b>estimate</b>.',
				'If they push for more, say: “I’ll gather the details, and Adam will send you a written proposal. That’s the real number.” Then work through the discovery questionnaire together.',
				'Mention that projects can be phased: start small, add features later.'
			],
			never: [
				'Never promise a final price, a discount, a timeline or a result (rankings, traffic, leads, revenue).',
				'Never quote SEO, branding, marketing or CVI prices. None are published. Send those questions to Adam.',
				'Never take a payment or sign anything. Clients pay AdaptExec directly.',
				'Anything beyond the talk track goes to Adam: (602) 584-0614 or support@adaptexec.com.'
			]
		},
		flow: [
			['Register the lead', 'Pipeline → New lead → <b>Submit for registration</b>. Wait for AdaptExec to confirm it before your first real sales conversation. That is what protects your commission (agreement 2.4).'],
			['Nurture it and run discovery yourself', 'You own the relationship: meetings, calls, demos and follow-ups. Work through the <b>discovery questionnaire</b> on the lead with them, or send their own link and fill the gaps from your notes. Answers you type are recorded as rep-entered. Adam does not take discovery calls on your leads.'],
			['Request a quote', 'When the <b>quote readiness</b> meter reaches the line (or you add a note explaining what is missing), press <b>Request a quote</b>. Adam gets the brief and builds the written proposal in Billing. If he needs something, he will ask you, or ask for a call; only then use <a href="https://calendar.app.google/6WUYMnKKRydQSjhs7" target="_blank" rel="noopener">Adam’s calendar</a>.'],
			['Share the proposal and follow up', 'The proposal shows on the lead. Send the link, walk them through it, answer questions and follow up until they accept. Only that proposal sets the price and terms.'],
			['Client accepts and pays', 'The client accepts online and pays AdaptExec directly, usually a deposit first and a final payment at the end.'],
			['Request Closed Won', 'Open the lead and press <b>Request Closed Won</b> with the signed contract value. AdaptExec confirms it and the deal moves to Closed Won.'],
			['Commission earned', 'One rate on the whole contract, from the Hub schedule. Half is earned when the deposit fully clears, half when the final payment fully clears. Each half is paid by check after that payment clears. There is no fixed pay date.']
		],
		onsite: {
			t: 'Optional add-on: on-site workflow study',
			p: 'For CRM, project-management and custom software jobs, Adam can travel to the client and watch their operation for a few days (usually 1–3) before he designs the system, so it fits how the work really flows. It is an additional cost, quoted separately, that covers travel, lodging and his time.',
			when: 'Offer it when the workflow is complex, spread across people or locations, or hard to explain in a meeting. Mark their interest in the questionnaire’s <b>On-site workflow study</b> section and Adam adds it to the proposal as an option. Never quote a price for it.'
		},
		examples: [
			['Starter website', 900],
			['Custom CRM', 6000]
		],
		scripts: [
			{ id: 'call', t: 'Cold call opener', when: 'Calling a business line. Register the business first. Dial by hand: no auto-dialers or recorded messages.',
				body: 'Hi, is this [owner’s name]? This is {rep}. I’m an independent sales rep for AdaptExec, a web design and custom CRM company based in Scottsdale. Did I catch you at an OK time for 30 seconds?\n\n[If yes] Thanks. I talk with [industry] businesses about two things: websites that bring in calls and bookings, and simple systems so no lead falls through the cracks. I’m not asking you to buy anything today.\n\nQuick question: when someone fills out the form on your site or calls after hours, what happens to that lead?\n\n[Listen. Ask one follow-up question.]\n\nThat’s helpful, thanks. AdaptExec built a live demo site for a [industry] business. Can I text or email you the link? If it looks useful, the next step is a short conversation with me about what you need, and AdaptExec sends you a written proposal.\n\n[Not a good time] No problem. Is there a better time, or should I just email the demo?\n\n[Not interested, or “don’t call”] Understood. I’ll take you off my list. Thanks for your time.' },
			{ id: 'vm', t: 'Voicemail', when: 'Keep it under 30 seconds. Leave one voicemail, then switch to email.',
				body: 'Hi [name], this is {rep}, an independent sales rep for AdaptExec in Scottsdale. I work with [industry] businesses on websites and lead follow-up, and AdaptExec has a live demo site built for a [industry] business that I think you’d like to see. You can reach me at [your number]. Again, it’s {rep}, [your number]. Thanks.' },
			{ id: 't1', t: 'Follow-up text: day 1', when: 'Only to someone who gave you their number and said a text is OK. Send each text yourself, never in bulk.',
				body: 'Hi [name], it’s {rep}, the independent rep for AdaptExec. Thanks for your time today. Here’s the [industry] demo I mentioned: [demo link]. Reply STOP and I won’t text again.' },
			{ id: 't3', t: 'Follow-up text: day 3', when: 'Skip it if they replied STOP or asked you not to text.',
				body: 'Hi [name], it’s {rep}, the independent rep for AdaptExec. Did you get a chance to look at the demo? If it looks useful, I’d be glad to set up 20 minutes to learn what you need, so AdaptExec can send you a written proposal. What day works?\n\nReply STOP to opt out.' },
			{ id: 't7', t: 'Follow-up text: day 7', when: 'The last text. After this, set a follow-up for next month or close the lead.',
				body: 'Hi [name], last check-in from me. If now isn’t the right time, no problem at all. If anything changes, I’m at [your number]. {rep}, independent rep for AdaptExec. Reply STOP to opt out.' },
			{ id: 'email', t: 'Follow-up email', when: 'Send it one at a time from your own email. No bought or scraped lists. Honest subject line, your mailing address, and stop if they ask.',
				body: 'Subject: The [industry] demo I mentioned\n\nHi [name],\n\nThanks for your time [today]. As promised, here are two quick things to look at:\n\n• A live demo site AdaptExec built for a [industry] business: [demo link]\n• A 38-second video on the websites and custom CRMs AdaptExec builds: https://adaptexec.com/wp-content/uploads/2026/10/adaptexec-promo-websites-seo-crm.mp4\n\nTwo things people usually ask:\n• Websites start around $900 and custom CRMs around $1,500. I’ll gather the details of what you need, and AdaptExec will send you a written proposal. Anything I say is an estimate until you have that proposal.\n• There’s no required monthly plan, and you own what AdaptExec builds.\n\nIf it’s worth a conversation, reply with a good day and time and I’ll set up 20 minutes to learn what you need. From there, AdaptExec sends you a written proposal. There’s no obligation.\n\nThanks,\n{rep}\nIndependent Sales Representative for AdaptExec\n[your phone] · [your email]\n[your mailing address]\n\nIf you’d rather not hear from me again, just reply and let me know.' },
			{ id: 'walk', t: 'Walk-in / in-person intro', when: 'Respect “No soliciting” signs. Visit when it’s slow, not at the lunch rush. Have the demo open on your phone.',
				body: 'Hi, I’m {rep}. I’m an independent sales rep for AdaptExec, a web design and custom CRM company in Scottsdale. Is the owner or manager around?\n\n[Not available] No problem. What’s the best way to reach them? I’ll leave my card.\n\n[With the owner] I’ll be quick. I help local businesses with their websites and the systems behind them, so calls and form leads don’t slip through. AdaptExec built a demo site for a [industry] business. Can I show you on my phone? It takes about a minute.\n\n[Show the demo.]\n\nIf any of that is useful, the next step is a short sit-down with me to learn what you actually need. Then AdaptExec sends you a written proposal. There’s no obligation and no required monthly plan. Would you be open to that?' },
			{ id: 'agenda', t: 'Discovery meeting agenda', when: 'For your discovery sit-down, about 30–45 minutes. You run it: open the lead’s questionnaire and fill it in as you go. Adam prices the project from what you gather, so ask for specifics.',
				body: '1. Thanks and timing: “I have us down for about 30 minutes. Does that still work?” Remind them you’re an independent rep for AdaptExec, and that Adam writes the proposal from what you gather today.\n2. Their business and goals: what they do, who their best customers are, and the top three things this project must achieve.\n3. Today’s setup: their website, the software and spreadsheets they use, what it costs every month, and what frustrates them.\n4. Walk the workflow step by step, from a new lead or request to getting paid. Who does each step, in which tool, and where it gets stuck.\n5. People and connections: who will log in and what each role needs, what it must connect to (QuickBooks, Google, church or industry software), and what records have to move over.\n6. Show it: the demo for their industry, plus one real client example.\n7. Must-haves vs nice-to-haves, the logo, photos and words they already have, and sites they like (send the Style Finder for websites). For photos, ask them to share a Google Drive or Dropbox folder with a subfolder for each service (plus team and location), so Adam knows what each picture shows, and add the link to the questionnaire.\n8. Timeline, budget range and who decides. Share only the pricing talk track.\n9. For a complex CRM or project-management build: Adam can come on site for a few days to study their workflow. It’s an optional add-on, quoted separately (travel, lodging and time).\n10. Next step: send their questionnaire link for anything left, then press Request a quote once the readiness meter says it’s ready. Log the meeting and set a follow-up.' },
			{ id: 'prop', t: 'Proposal follow-up', when: 'After AdaptExec sends the proposal. Check in once a few days later. Answer questions; never change the price or terms yourself.',
				body: 'Hi [name], it’s {rep}, the independent rep for AdaptExec. Your written proposal from AdaptExec is here: [proposal link]. Happy to walk through it with you, or pass any questions to Adam. Is there anything you’d like adjusted before you decide?\n\nReply STOP to opt out.' },
			{ id: 'ref', t: 'Referral ask', when: 'Ask after a good conversation or once a client is happy. Register every introduction before you reach out.',
				body: 'I’m glad this has been helpful. Most of my work comes from introductions. Do you know another business owner who’s frustrated with their website, or who’s juggling leads in spreadsheets?\n\n[If yes] Would you be comfortable introducing us by text or email? I’ll only reach out once they know to expect me, and I’ll mention that you suggested it.' }
		],
		scriptRules: [
			'<b>Say who you are.</b> “Independent sales rep for AdaptExec,” every time. Never employee, partner or owner.',
			'<b>Calls and texts (TCPA).</b> Dial by hand. No auto-dialers, robocalls or mass texting. Text only people who gave you their number and agreed to texts. Check the Do Not Call list before calling personal cell numbers.',
			'<b>Email (CAN-SPAM).</b> Honest subject lines, your name and mailing address, no bought or scraped lists, and stop when asked.',
			'<b>Opt-outs.</b> “Stop,” “don’t call” or “unsubscribe” means stop now. Note it on the lead and tell Adam.',
			'<b>Referral fees.</b> Don’t promise anyone a referral fee. If they ask, point them to adaptexec.com/referrals and let Adam handle it.'
		],
		objections: [
			{ q: '“It’s too expensive.”', a: 'That’s fair. Websites start around $900 and custom CRMs around $1,500, and there’s no required monthly plan. Many owners compare that to what they already pay every month for software and retainers. Adam can also phase a project: start small and add features as you grow. Want to run the savings estimator together? It adds up what you pay today. It isn’t a quote or a promise of savings.', show: ['Subscription savings estimator', 'https://adaptexec.com/why-adaptexec/#estimate'] },
			{ q: '“We already have Wix / Squarespace / GoDaddy.”', a: 'Those are fine for getting started, and AdaptExec’s own pricing guide says so. The difference: AdaptExec designs the site from scratch around your business, connects your forms to a CRM so leads don’t slip through, and you own the site instead of renting it. Want to compare your site with a demo in your industry?', show: ['Website cost guide', 'https://adaptexec.com/website-cost-phoenix/'] },
			{ q: '“My nephew / a friend does our website.”', a: 'Keep them. It’s great to have someone you trust. If you ever want a second opinion, the free SEO checker gives you a plain-English report in about a minute. AdaptExec can also handle just the CRM side and connect your website forms to it.', show: ['Free SEO checker', 'https://adaptexec.com/search-engine-optimization/#seo-checker'] },
			{ q: '“Do you charge monthly?”', a: 'There’s no required monthly plan. You pay for the project. If you want ongoing help with updates, SEO or hosting, AdaptExec can set up a plan, but it’s optional and never required to keep what was built. Every website has running costs like a domain and hosting, so I’ll ask Adam to list them in the proposal.', show: ['Why AdaptExec', 'https://adaptexec.com/why-adaptexec/'] },
			{ q: '“How long does it take?”', a: 'It depends on scope. A focused website usually takes a matter of weeks. A custom CRM or web app takes longer and is often delivered in phases, so you can start using it sooner. You’ll get a realistic timeline with the written proposal. I can’t promise a date.', show: ['Contact page FAQ', 'https://adaptexec.com/contact-us/'] },
			{ q: '“Who owns the site or the CRM?”', a: 'You do. Your website, brand and data belong to you, not to a platform or an agency. For custom software, the application is yours once the project is paid in full. AdaptExec keeps rights only to its reusable frameworks and tools, and you get a license to use them as part of your system.', show: ['Custom CRM FAQ', 'https://adaptexec.com/web-apps-and-custom-crm/'] },
			{ q: '“What if I want changes later?”', a: 'AdaptExec builds websites on a content management system, so you can update text, photos and pages yourself, or AdaptExec can make updates for you. New features can be added in phases. Ongoing help is there if you want it, never required.', show: ['Website design FAQ', 'https://adaptexec.com/website-design/'] },
			{ q: '“We already use HubSpot / another CRM.”', a: 'If it fits how you work, keep it. AdaptExec’s own guide explains when HubSpot or Salesforce is the better choice. A custom CRM makes sense when per-seat fees keep growing, your team keeps a spreadsheet next to the CRM, or the CRM has to run operations like jobs or client portals. AdaptExec can also connect your website forms to the CRM you already have, and in most cases can move your data if you switch.', show: ['Custom CRM vs HubSpot', 'https://adaptexec.com/custom-crm-vs-hubspot/'] },
			{ q: '“I need to think about it.”', a: 'Of course. So I send the right things, what are you weighing most: price, timing, or whether you need it at all? [Listen.] If real numbers would help, let’s finish the questionnaire together and AdaptExec will send you a written proposal. There’s no obligation. Then set a follow-up in the Hub for the day you agreed on.', show: ['Why AdaptExec', 'https://adaptexec.com/why-adaptexec/'] },
			{ q: '“Just send me some info.”', a: 'Happy to. So it’s useful and not just a brochure, which matters more right now: the website, following up on leads, or both? [Send the matching demo and the 38-second video.] Can I check back with you on [day]? Then log a follow-up.', show: ['Live demos', 'https://adaptexec.com/demos/'] },
			{ q: '“Are you local?”', a: 'AdaptExec is based in Scottsdale, Arizona and works with clients nationwide by phone, screen share and email. I’m an independent rep: I gather what you need, and Adam, the founder, writes the proposal himself. For complex CRM or software projects he can also come on site to study your workflow, as an optional add-on. For CVI coaching, AdaptExec travels to clients in person.', show: ['Contact page', 'https://adaptexec.com/contact-us/'] },
			{ q: '“Can you see how we actually work?”', a: 'Yes. Adam, AdaptExec’s founder, can come on site for a few days to watch how your team works, so your CRM or project-management system is built around your real workflow. It’s an optional add-on, quoted separately, and it covers travel, lodging and his time. Want me to note your interest in the questionnaire? Adam will include it in your proposal as an option.', show: ['Custom CRM page', 'https://adaptexec.com/web-apps-and-custom-crm/'] },
			{ q: '“Can you guarantee SEO results?”', a: 'No, and no honest SEO provider can, because search engines control their algorithms. What AdaptExec commits to is a clear strategy, transparent work and a focus on qualified traffic, leads and sales. AdaptExec’s site says most businesses see meaningful movement within 3–6 months. That’s typical, not a promise. No long-term contract is required.', show: ['SEO FAQ', 'https://adaptexec.com/search-engine-optimization/'] }
		],
		services: [
			{ t: 'Websites', url: 'https://adaptexec.com/website-design/', price: 'Starts around $900',
				what: 'Custom websites designed from scratch, built to load fast and win customers, and connected to the CRM behind them.',
				who: 'Local service businesses, nonprofits and churches whose site looks like a template or doesn’t bring in calls and bookings.',
				inc: ['Custom design, mobile-first', 'SEO-ready structure and fast pages', 'Easy to edit yourself', 'Forms that feed a CRM', 'You own everything'],
				show: [['Demo for their industry', 'https://adaptexec.com/demos/'], ['Style Finder', 'https://adaptexec.com/website-styles/'], ['Website Planner', 'https://adaptexec.com/tools/website-planner/']] },
			{ t: 'Custom CRM & web apps', url: 'https://adaptexec.com/web-apps-and-custom-crm/', price: 'CRMs start around $1,500 · custom software around $2,500',
				what: 'CRMs and web apps built around how the business already works: pipelines, client portals, automations, dashboards and practical AI.',
				who: 'Teams running on spreadsheets, or paying per-seat fees for software they work around.',
				inc: ['Lead management and pipelines', 'Customer portals and scheduling', 'Workflow automation and reporting', 'Integrations and data migration', 'No per-user fees; you own it once paid in full'],
				show: [['CRM Builder', 'https://adaptexec.com/tools/crm-builder/'], ['Lead Leak Calculator', 'https://adaptexec.com/tools/lead-leak-calculator/'], ['Miliare & Virtuity case study', 'https://adaptexec.com/portfolio/miliare-virtuity-crm/']] },
			{ t: 'SEO', url: 'https://adaptexec.com/search-engine-optimization/', price: 'Scoped after a review. No public price.',
				what: 'Custom SEO strategy covering technical SEO, local visibility and content, without prepackaged tiers.',
				who: 'Local businesses that want to be found nearby, plus national brands, online stores and nonprofits.',
				inc: ['Technical fixes to the site', 'Google Business Profile, citations and reviews', 'Content written for customers first', 'No long-term contract required', 'No ranking guarantees'],
				show: [['Free SEO checker', 'https://adaptexec.com/search-engine-optimization/#seo-checker'], ['HB Beauty case study', 'https://adaptexec.com/portfolio/hb-beauty/']] },
			{ t: 'Branding & marketing', url: 'https://adaptexec.com/marketing-branding/', price: 'Scoped per project. No public price.',
				what: 'Logos, brand identity, social graphics, print, pitch decks and proposals, and Google, Facebook and Instagram ad campaigns with matching landing pages.',
				who: 'Businesses that look less trustworthy than they are, or need a single campaign done right.',
				inc: ['Custom logo and brand guide', 'Social and print materials', 'Presentations and RFP responses', 'Ad creative or full campaigns', 'You own the brand files once paid'],
				show: [['12 brand identities (demos)', 'https://adaptexec.com/demos/'], ['Portfolio', 'https://adaptexec.com/portfolio/']] },
			{ t: 'CVI™ coaching', url: 'https://adaptexec.com/cvi-coaching/', price: 'Ask Adam. No public price.',
				what: 'The Core Values Index™ assessment (about 7–10 minutes), then a debrief and coaching with a Certified CVI™ Coach through Hardwired Coaching.',
				who: 'Business and sales teams, church and ministry leaders, couples and individuals.',
				inc: ['Online assessment', 'One-on-one results debriefs (virtual is fine)', 'Team workshops and leadership coaching', 'In-person sessions nationwide'],
				show: [['Energy Explorer', 'https://adaptexec.com/cvi-coaching/#cvi-explorer'], ['CVI for teams & sales', 'https://adaptexec.com/cvi-coaching/teams-and-sales/']] }
		],
		cvi: {
			who: [
				['Business teams', 'Leaders who manage everyone the same way and keep hitting the same friction. CVI gives the team a shared language for strengths, communication and conflict.'],
				['Sales teams', 'Every buyer runs on the same four core values. A Builder and a Banker can hear the same pitch and reach opposite conclusions. CVI helps reps read buyers and adjust.'],
				['Churches and leaders', 'Pastors, elders and ministry teams understanding how each person is designed to serve.']
			],
			opens: [
				'“Do you ever feel like your team is working hard but solving the same problem in different ways?”',
				'“Have you noticed some buyers want the bottom line and others want every detail? There’s a quick assessment that shows how each person is wired.”',
				'“The assessment takes about 7–10 minutes. A certified coach then walks the team through what it means.”'
			],
			next: 'Show the free Energy Explorer together, then send the teams & sales page. When they’re interested, register the lead, note the team size and what they want from it, and let Adam know. He and Bonnie Juma are the Certified CVI™ Coaches, and the coaches set the scope and price.'
		}
	};

	function kitRep() {
		var n = (S.boot && S.boot.user && S.boot.user.name) || '';
		return n || '[your name]';
	}
	function kitFill(s) { return String(s).split('{rep}').join(kitRep()); }
	function kitWeekKey() { return 'axh_kit_week_' + ((S.boot && S.boot.user && S.boot.user.id) || 0); }
	function kitWeekDone() {
		var v = {};
		try { v = JSON.parse(localStorage.getItem(kitWeekKey()) || '{}') || {}; } catch (e) { v = {}; }
		return v;
	}
	function kitExt(label, url, cls) {
		return '<a class="' + (cls || 'axh-kit-link') + '" href="' + esc(url) + '" target="_blank" rel="noopener">' + label + ' ' + icon('right', 12) + '</a>';
	}
	function kitSecHead(id, n, title, sub) {
		return '<div class="axh-kit-sec" id="kit-' + id + '"><span class="axh-kit-sec__n">' + n + '</span>' +
			'<div><h2 class="thunder">' + title + '</h2>' + (sub ? '<p>' + sub + '</p>' : '') + '</div></div>';
	}

	function kitWeek() {
		var done = kitWeekDone(), total = 0, ticked = 0;
		var days = SK.week.map(function (d, di) {
			return '<div class="axh-kit-day"><div class="axh-kit-day__h"><b>' + d.d + '</b><span>' + d.t + (d.time ? ' · ' + d.time : '') + '</span></div><ul>' +
				d.items.map(function (it, ii) {
					var k = di + '-' + ii, on = !!done[k];
					total++; if (on) ticked++;
					return '<li><button type="button" class="axh-kit-tick' + (on ? ' is-on' : '') + '" data-kit-tick="' + k + '" aria-pressed="' + (on ? 'true' : 'false') + '" aria-label="Mark step done">' + icon('check', 12) + '</button><span>' + it + '</span></li>';
				}).join('') + '</ul></div>';
		}).join('');
		return '<div class="axh-kit-week" id="kit-week"><div class="axh-kit-week__h"><div><span class="axh-kit-eyebrow">New here? Start with this</span>' +
			'<h2 class="thunder">Your first week</h2><p>Set up on day 1, real conversations by day 4. Tick steps off as you go (saved on this device).</p></div>' +
			'<span class="axh-kit-week__count" data-kit-count>' + ticked + ' / ' + total + '</span></div>' +
			'<div class="axh-kit-days">' + days + '</div></div>';
	}

	function kitPricing() {
		var p = SK.pricing;
		return kitSecHead('pricing', '01', 'Pricing: the approved answer', 'They will ask “how much?” on day 1. Say this, word for word.') +
			'<div class="axh-kit-price">' +
			'<div class="axh-kit-price__q"><span class="axh-kit-eyebrow">Approved talk track</span>' +
			'<blockquote id="kit-s-price">' + esc(p.track) + '</blockquote>' +
			'<div class="axh-kit-price__acts"><button type="button" class="axh-btn axh-btn--primary axh-btn--sm" data-kit-copy="price">' + icon('copy', 13) + ' Copy</button>' +
			'<span>Published on adaptexec.com: ' + p.sources.map(function (s) { return kitExt(esc(s[0]), s[1], 'axh-kit-link axh-kit-link--inv'); }).join(' · ') + '</span></div>' +
			'<p class="axh-kit-price__app"><b>Only if they ask about a custom app or portal:</b> “' + esc(p.app) + '” <span>(' + kitExt(esc(p.appSource[0]), p.appSource[1], 'axh-kit-link axh-kit-link--inv') + ')</span></p></div>' +
			'<div class="axh-kit-rules"><div class="axh-kit-rules__col"><h4>' + icon('check', 14) + ' Do</h4><ul>' + p.doList.map(function (x) { return '<li>' + x + '</li>'; }).join('') + '</ul></div>' +
			'<div class="axh-kit-rules__col axh-kit-rules__col--no"><h4>' + icon('x', 14) + ' Never</h4><ul>' + p.never.map(function (x) { return '<li>' + x + '</li>'; }).join('') + '</ul></div></div>' +
			'</div>';
	}

	function kitFlow() {
		var steps = SK.flow.map(function (s, i) {
			return '<li class="axh-kit-step"><span class="axh-kit-step__n">' + (i + 1) + '</span><div><b>' + s[0] + '</b><p>' + s[1] + '</p></div></li>';
		}).join('');
		var ex = SK.examples.map(function (e) {
			var r = tierFor(e[1]), c = Math.round(e[1] * r) / 100, half = Math.round(c * 50) / 100;
			return '<tr><td>' + esc(e[0]) + '</td><td class="num">' + moneyC(e[1]) + '</td><td class="num">' + r + '%</td><td class="num"><b>' + moneyC(c) + '</b></td><td class="num">2 × ' + moneyC(half) + '</td></tr>';
		}).join('');
		var os = SK.onsite;
		return kitSecHead('flow', '02', 'How a deal flows', 'From first contact to your commission check, in seven steps. You run discovery; Adam writes the proposal.') +
			'<div class="axh-kit-flow"><ol class="axh-kit-steps">' + steps + '</ol>' +
			'<div class="axh-kit-ex"><div class="axh-card__h"><h3>Worked examples</h3><a href="#/earnings">Earnings</a></div>' +
			'<div class="axh-kit-tablewrap"><table class="axh-kit-table"><thead><tr><th>Deal</th><th class="num">Contract</th><th class="num">Rate</th><th class="num">Commission</th><th class="num">Two checks</th></tr></thead><tbody>' + ex + '</tbody></table></div>' +
			'<p class="axh-kit-small">The rate comes from the contract size (schedule below). Each check goes out after the matching client payment fully clears. No commission on hosting, domains or other pass-through costs, or on monthly plans unless AdaptExec confirms them as a separate Closed Won deal. A $900 site pays $90, so small deals add up through volume.</p></div></div>' +
			'<div class="axh-kit-note axh-kit-onsite" id="kit-onsite"><b>' + icon('globe', 14) + ' ' + esc(os.t) + '</b><p>' + esc(os.p) + '</p><p>' + os.when + '</p></div>';
	}

	function kitScripts() {
		var cards = SK.scripts.map(function (s) {
			return '<div class="axh-kit-script"><div class="axh-kit-script__h"><h3>' + esc(s.t) + '</h3>' +
				'<button type="button" class="axh-btn axh-btn--dark axh-btn--sm" data-kit-copy="' + s.id + '">' + icon('copy', 13) + ' Copy</button></div>' +
				'<p class="axh-kit-script__w">' + esc(s.when) + '</p>' +
				'<pre id="kit-s-' + s.id + '">' + esc(kitFill(s.body)) + '</pre></div>';
		}).join('');
		return kitSecHead('scripts', '03', 'Scripts', 'Your name is filled in. Swap the [brackets], then sound like yourself, not like a script.') +
			'<div class="axh-kit-note"><b>Rules for every call, text and email</b><ul>' + SK.scriptRules.map(function (x) { return '<li>' + x + '</li>'; }).join('') + '</ul></div>' +
			'<div class="axh-kit-scripts">' + cards + '</div>';
	}

	function kitObjections() {
		return kitSecHead('objections', '04', 'Objections & FAQ', 'Honest answers, all backed by adaptexec.com. If you don’t know, say so and ask Adam.') +
			'<div class="axh-kit-faq">' + SK.objections.map(function (o, i) {
				return '<details class="axh-kit-qa"' + (i === 0 ? ' open' : '') + '><summary>' + esc(o.q) + icon('right', 14) + '</summary>' +
					'<div class="axh-kit-qa__a"><p>' + esc(o.a) + '</p>' + (o.show ? '<p class="axh-kit-qa__show">Show: ' + kitExt(esc(o.show[0]), o.show[1]) + '</p>' : '') + '</div></details>';
			}).join('') + '</div>';
	}

	function kitServices() {
		return kitSecHead('services', '05', 'Service one-pagers', 'What it is, who it’s for, and what to show.') +
			'<div class="axh-kit-svcs">' + SK.services.map(function (s) {
				return '<div class="axh-kit-svc"><div class="axh-kit-svc__h"><h3>' + esc(s.t) + '</h3><span class="axh-kit-svc__p">' + esc(s.price) + '</span></div>' +
					'<p>' + esc(s.what) + '</p><p class="axh-kit-svc__who"><b>For:</b> ' + esc(s.who) + '</p>' +
					'<ul>' + s.inc.map(function (x) { return '<li>' + esc(x) + '</li>'; }).join('') + '</ul>' +
					'<div class="axh-kit-svc__show"><span>Show:</span> ' + s.show.map(function (x) { return kitExt(esc(x[0]), x[1]); }).join(' ') + '</div>' +
					'<div class="axh-kit-svc__f">' + kitExt('Service page', s.url, 'axh-btn axh-btn--ghost axh-btn--sm') +
					'<button type="button" class="axh-btn axh-btn--ghost axh-btn--sm" data-copy="' + esc(s.url) + '">Copy link</button></div></div>';
			}).join('') + '</div>';
	}

	function kitCvi() {
		var c = SK.cvi;
		return kitSecHead('cvi', '06', 'The CVI™ coaching pitch', 'Core Values Index™ coaching: a different conversation from websites, often with the same owners.') +
			'<div class="axh-kit-cvi"><div class="axh-kit-cvi__who">' + c.who.map(function (w) { return '<div><b>' + esc(w[0]) + '</b><p>' + esc(w[1]) + '</p></div>'; }).join('') + '</div>' +
			'<div class="axh-kit-cvi__open"><h4>How to open it</h4><ul>' + c.opens.map(function (x) { return '<li>' + esc(x) + '</li>'; }).join('') + '</ul>' +
			'<p>' + esc(c.next) + '</p><div class="axh-kit-cvi__links">' +
			kitExt('CVI coaching', 'https://adaptexec.com/cvi-coaching/', 'axh-btn axh-btn--primary axh-btn--sm') +
			kitExt('CVI for teams & sales', 'https://adaptexec.com/cvi-coaching/teams-and-sales/', 'axh-btn axh-btn--ghost axh-btn--sm') +
			kitExt('Energy Explorer', 'https://adaptexec.com/cvi-coaching/#cvi-explorer', 'axh-btn axh-btn--ghost axh-btn--sm') + '</div></div></div>';
	}

	function kitJump() {
		var j = [['week', 'First week'], ['pricing', 'Pricing'], ['flow', 'Deal flow'], ['scripts', 'Scripts'], ['objections', 'Objections'],
			['services', 'Services'], ['cvi', 'CVI'], ['hub', 'Using the Hub'], ['paid', 'Getting paid'], ['links', 'Links & tools']];
		return '<nav class="axh-kit-jump" aria-label="Sales kit sections">' + j.map(function (x) {
			return '<button type="button" data-kit-jump="' + (x[0] === 'paid' ? 'paid' : 'kit-' + x[0]) + '">' + x[1] + '</button>';
		}).join('') + '</nav>';
	}

	function kitCopy(id) {
		var n = el('kit-s-' + id);
		if (!n) return;
		var txt = n.textContent;
		function done() { toast('Copied. Fill in the [brackets] before you send.', 'ok'); }
		function fallback() {
			var ta = document.createElement('textarea');
			ta.value = txt; ta.setAttribute('readonly', ''); ta.style.position = 'fixed'; ta.style.opacity = '0';
			document.body.appendChild(ta); ta.select();
			try { document.execCommand('copy'); done(); } catch (e) { toast('Could not copy. Select the text and copy it.', 'bad'); }
			ta.remove();
		}
		if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(txt).then(done, fallback);
		else fallback();
	}
	document.addEventListener('click', function (e) {
		var t = e.target, b;
		if (!t.closest) return;
		if ((b = t.closest('[data-kit-copy]'))) { kitCopy(b.getAttribute('data-kit-copy')); return; }
		if ((b = t.closest('[data-kit-jump]'))) {
			var target = el(b.getAttribute('data-kit-jump'));
			if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
			return;
		}
		if ((b = t.closest('[data-kit-tick]'))) {
			var v = kitWeekDone(), k = b.getAttribute('data-kit-tick');
			if (v[k]) delete v[k]; else v[k] = 1;
			try { localStorage.setItem(kitWeekKey(), JSON.stringify(v)); } catch (er) {}
			b.classList.toggle('is-on', !!v[k]);
			b.setAttribute('aria-pressed', v[k] ? 'true' : 'false');
			var cnt = document.querySelector('[data-kit-count]');
			if (cnt) cnt.textContent = document.querySelectorAll('.axh-kit-tick.is-on').length + ' / ' + document.querySelectorAll('.axh-kit-tick').length;
		}
	});

	function viewLibrary() {
		function linkRow(x) {
			return '<li><div class="axh-list__t"><b>' + esc(x[0]) + '</b><span>' + esc(x[1]) + '</span></div>' +
				'<div style="display:flex;gap:6px;flex-shrink:0">' +
				'<a class="axh-btn axh-btn--ghost axh-btn--sm" href="' + esc(x[2]) + '" target="_blank" rel="noopener">Open</a>' +
				'<button class="axh-btn axh-btn--ghost axh-btn--sm" data-copy="' + esc(x[2]) + '">Copy link</button></div></li>';
		}
		var tel = 'tel:' + KIT.phone.replace(/[^0-9]/g, '');

		var html = '<div class="axh-page-head"><div><h1 class="thunder">Sales kit</h1>' +
			'<p>Everything a new rep needs for the first conversation: the pricing answer, how a deal flows, scripts, objections and the links to send.</p></div></div>' +
			'<div class="axh-kit">' + kitJump() + kitWeek() + kitPricing() + kitFlow() + kitScripts() + kitObjections() + kitServices() + kitCvi() +
			kitSecHead('hub', '07', 'Using the Hub', 'How to work in the Hub, and what you earn.') +

			'<div class="axh-grid axh-grid--2">' +
			'<div class="axh-card"><div class="axh-card__h"><h3>Start here</h3></div>' +
			'<ol style="margin:0 0 0 18px;padding:0;font-size:14px;line-height:1.6">' +
				(S.boot.user.manager ? '' : '<li><b>Finish onboarding first.</b> Sign your agreement, upload your W-9 and set up payment under <a href="#/onboarding">Onboarding</a> — checks cannot go out until it is done.</li>') +
				'<li><b>This Hub is your CRM.</b> You see only your own leads, follow-ups, forms, shared links and earnings.</li>' +
				'<li><b>Register every prospect before your first real conversation</b> (New lead → Submit for registration). ' +
					'AdaptExec confirms it; if another rep registered the business first, you will see a warning.</li>' +
				'<li><b>Show, don’t tell.</b> Open a demo in their industry (Demo sites) and present the matching deck (Present). ' +
					'Shared deck links report views back to you.</li>' +
				'<li><b>Run discovery yourself.</b> Meet with the prospect and work through the discovery questionnaire on the lead together, or send them their own link and fill the gaps. ' +
					'Adam does not take discovery calls on your leads; he quotes from what you gather.</li>' +
				'<li><b>Request a quote.</b> When the quote readiness meter reaches the line, press <b>Request a quote</b>. Adam gets the brief and writes the proposal in Billing — there are no packages to quote. ' +
					'If a client wants Adam to study their workflow on site, mark it in the questionnaire; it is an add-on he quotes separately.</li>' +
				'<li><b>Share the proposal and follow up.</b> The proposal appears on the lead. Send it, walk them through it and follow up until they accept.</li>' +
				'<li><b>Close.</b> When the client signs, open the lead and press <b>Request Closed Won</b> with the contract value. AdaptExec confirms it, the deal moves to Closed Won and your commission appears under Earnings.</li>' +
				'<li><b>Website enquiries.</b> AdaptExec may assign you leads that came in through the website. You get an email, and they show up in your pipeline.</li>' +
				'<li><b>Questions?</b> Call or text Adam at <a href="' + tel + '">' + esc(KIT.phone) + '</a> or email ' +
					'<a href="mailto:' + esc(KIT.email) + '">' + esc(KIT.email) + '</a>.</li>' +
			'</ol></div>' +

			'<div class="axh-card"><div class="axh-card__h"><h3>Commission at a glance</h3><a href="#/earnings">Earnings</a></div>' +
			'<p style="font-size:14px;line-height:1.6;margin:0 0 10px">One rate for the whole contract, set by its size:</p>' +
			'<p style="font-size:14px;line-height:1.8;margin:0 0 10px"><b>10%</b> up to $5,000 · <b>12%</b> $5,001–$8,000 · ' +
				'<b>14%</b> $8,001–$10,000 · <b>15%</b> $10,001–$15,000 · <b>20%</b> over $15,000</p>' +
			'<p style="font-size:14px;line-height:1.6;margin:0">Example: a $12,000 contract earns 15% = $1,800 — $900 is earned when the client’s deposit clears ' +
				'and $900 when their final payment clears, each paid by check after the funds clear. The Earnings page tracks every half as waiting, payable or paid.</p></div>' +
			'</div>' + payoutCard() +

			kitSecHead('links', '08', 'Pitch, proof & links', 'The story, the proof and the tools to show and send.') +
			'<div class="axh-card"><div class="axh-card__h"><h3>The pitch</h3></div>' +
			'<p style="font-size:15px;line-height:1.6;margin:0 0 10px"><b>No lock-in. No required subscriptions.</b> ' +
				'Pay for what you need, take a monthly plan only if you want one, and you own your work. ' +
				'Agencies charge $4,000+ a month for SEO retainers and stack per-seat software on top; AdaptExec fits the work to the business instead of selling a package.</p>' +
		'<p style="font-size:14px;line-height:1.6;margin:0 0 10px">Send the story: ' +
			'<a href="https://adaptexec.com/why-adaptexec/" target="_blank" rel="noopener">Why AdaptExec</a> (no lock-in, you own it), ' +
			'then walk through the <a href="https://adaptexec.com/why-adaptexec/#estimate" target="_blank" rel="noopener">savings estimator</a> together. ' +
			'Back it up with a case study: <a href="https://adaptexec.com/portfolio/miliare-virtuity-crm/" target="_blank" rel="noopener">Miliare &amp; Virtuity</a> · ' +
			'<a href="https://adaptexec.com/portfolio/fathers-advocacy-network/" target="_blank" rel="noopener">Father’s Advocacy Network</a> · ' +
			'<a href="https://adaptexec.com/portfolio/hb-beauty/" target="_blank" rel="noopener">HB Beauty</a>.</p>' +
			'<p style="font-size:14px;line-height:1.6;margin:0"><b>Proof:</b> 10+ years · 347+ projects · 139+ customers. ' +
				'Clients include ' + esc(KIT.clients) + '.</p></div>' +

			'<div class="axh-grid axh-grid--2" style="margin-top:16px">' +
			'<div class="axh-card"><div class="axh-card__h"><h3>What we sell</h3></div><ul class="axh-list">' +
				KIT.services.map(linkRow).join('') + '</ul></div>' +
			'<div class="axh-card"><div class="axh-card__h"><h3>Tools to show and send</h3></div>' +
			'<video src="' + esc(KIT.video) + '" controls preload="none" playsinline ' +
				'style="width:100%;border-radius:10px;background:#0D0E10;aspect-ratio:16/9;margin-bottom:10px"></video>' +
			'<ul class="axh-list">' + KIT.tools.map(linkRow).join('') + '</ul></div>' +
			'</div>' +

			'<div class="axh-card" style="margin-top:16px"><div class="axh-card__h"><h3>AdaptExec contact</h3></div>' +
			'<p style="font-size:14px;line-height:1.7;margin:0">Phone <a href="' + tel + '">' + esc(KIT.phone) + '</a> · ' +
				'Email <a href="mailto:' + esc(KIT.email) + '">' + esc(KIT.email) + '</a> · ' +
				'Free consultation: <a href="https://adaptexec.com/contact-us/" target="_blank" rel="noopener">adaptexec.com/contact-us</a><br>' +
				'Referral program (for people who are not reps): warm, pre-sold introductions only — ' +
				'<a href="https://adaptexec.com/referrals/" target="_blank" rel="noopener">adaptexec.com/referrals</a></p></div></div>';

		if ((S.boot.decks || []).length) {
			html += '<div class="axh-page-head" style="margin-top:24px"><div><h2 class="thunder">PDF packets</h2>' +
				'<p>Send the industry packet before the meeting. Print or email the PDF.</p></div></div>' +
				'<div class="axh-decks">' + S.boot.decks.map(function (d) {
					return '<div class="axh-deck"><div class="axh-deck__cover"><b>' + esc(d.title) + '</b><i style="background:' + esc(d.accent) + '"></i></div>' +
						'<div class="axh-deck__b"><strong>' + esc(d.title) + '</strong><span>' + esc(d.subtitle || '') + '</span>' +
						'<div style="display:flex;gap:6px;margin-top:10px">' +
						'<button class="axh-btn axh-btn--primary axh-btn--sm" data-deck="' + esc(d.slug) + '">Present</button>' +
						(d.pdf ? '<a class="axh-btn axh-btn--ghost axh-btn--sm" href="' + esc(d.pdf) + '" target="_blank" rel="noopener">PDF</a>' : '') +
						'</div></div></div>';
				}).join('') + '</div>';
		}
		return html;
	}

	/* ---------------------------------------------------------- tasks */

	/* =================================================== follow-ups (1.8) */
	/* Typed follow-ups with times, priority, outcomes, snooze, bulk actions,
	   a work-through queue and "deals with no next step" — the same toolkit
	   reps know from Pipedrive, HubSpot and Close, wired to this Hub. */

	var FT = {
		call: { l: 'Call', i: 'phone', c: '#0E7C66', o: ['Connected', 'Left voicemail', 'No answer', 'Meeting booked', 'Not interested'] },
		email: { l: 'Email', i: 'mail', c: '#046BD2', o: ['Sent', 'Replied', 'No reply yet', 'Bounced'] },
		text: { l: 'Text', i: 'msg', c: '#7C3AED', o: ['Sent', 'Replied', 'No reply yet'] },
		meeting: { l: 'Meeting', i: 'cal', c: '#B45309', o: ['Held', 'Rescheduled', 'No-show', 'Proposal requested'] },
		todo: { l: 'To-do', i: 'check', c: '#475467', o: ['Done'] }
	};
	var FTK = ['call', 'email', 'text', 'meeting', 'todo'];
	S.fu = S.fu || { tab: 'focus', type: '', rep: '', q: '', sel: {} };
	S.flow = S.flow || { tasks: [], done: [], doneWeek: 0, board: null, ok: false };

	function ft(t) { return FT[t && t.type] || FT.todo; }
	function pad2(n) { return ('0' + n).slice(-2); }
	function ymd(d) { return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()); }
	function dayStart(d) { return new Date(d.getFullYear(), d.getMonth(), d.getDate()); }
	function addDays(d, n) { var x = new Date(d.getTime()); x.setDate(x.getDate() + n); return x; }
	function nextMonday() { var d = dayStart(new Date()), k = (8 - d.getDay()) % 7 || 7; return addDays(d, k); }
	function utcStamp(d) {
		return d.getUTCFullYear() + '-' + pad2(d.getUTCMonth() + 1) + '-' + pad2(d.getUTCDate()) + ' ' + pad2(d.getUTCHours()) + ':' + pad2(d.getUTCMinutes()) + ':00';
	}
	function fuDue(t) { return t && t.due_at ? srvDate(t.due_at) : null; }
	function fuTimed(t) { return !!(t && t.due_at && (Number(t.timed) || !isDay(t.due_at))); }
	function fuClock(d) { return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }); }

	/* Where a follow-up sits on the calendar, relative to now. */
	function fuBucket(t) {
		var d = fuDue(t);
		if (!d || isNaN(d)) return 'none';
		var today = dayStart(new Date()), day = dayStart(d), diff = Math.round((day - today) / 86400000);
		if (fuTimed(t) ? d < new Date() : diff < 0) return 'overdue';
		if (diff === 0) return 'today';
		if (diff === 1) return 'tomorrow';
		if (diff < 7) return 'week';
		return 'later';
	}
	function fuDueText(t) {
		var d = fuDue(t);
		if (!d || isNaN(d)) return 'No date';
		var b = fuBucket(t), clock = fuTimed(t) ? fuClock(d) : '';
		if (b === 'overdue') {
			var days = Math.round((dayStart(new Date()) - dayStart(d)) / 86400000);
			return days <= 0 ? 'Overdue · ' + clock : days + (days === 1 ? ' day' : ' days') + ' overdue';
		}
		return when(t.due_at) + (clock ? ' · ' + clock : '');
	}

	function fuLoad(withBoard) {
		var jobs = [api('/flow/tasks'), api('/flow/tasks?status=done')];
		if (withBoard) jobs.push(api('/flow/board'));
		return Promise.all(jobs).then(function (r) {
			S.flow.tasks = r[0].tasks || [];
			S.flow.doneWeek = r[0].done_week || 0;
			S.flow.done = r[1].tasks || [];
			if (withBoard) S.flow.board = r[2];
			S.flow.ok = true;
		}, function () {
			// The server module not answering should never cost a page: fall back to the basic list.
			S.flow.ok = false;
			S.flow.tasks = (S.tasks || []).filter(function (t) { return !t.done_at; });
			S.flow.done = (S.tasks || []).filter(function (t) { return t.done_at; });
		});
	}
	function fuBoard() {
		return api('/flow/board').then(function (b) { S.flow.board = b; }, function () { S.flow.board = null; });
	}

	function fuLeadOf(t) { return t.lead_id ? (S.leads || []).filter(function (l) { return String(l.id) === String(t.lead_id); })[0] || null : null; }

	function fuGcal(t) {
		var d = fuDue(t);
		if (!d || isNaN(d)) return '';
		var f = function (x) { return x.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, ''); };
		var dates = fuTimed(t) ? f(d) + '/' + f(new Date(d.getTime() + 30 * 60000)) : ymd(d).replace(/-/g, '') + '/' + ymd(addDays(d, 1)).replace(/-/g, '');
		var lead = fuLeadOf(t);
		var details = [t.notes || '', lead ? lead.company + (lead.contact_name ? ' · ' + lead.contact_name : '') + (lead.phone ? ' · ' + lead.phone : '') : '', location.origin + location.pathname + (t.lead_id ? '#/lead/' + t.lead_id : '#/tasks')].filter(Boolean).join('\n');
		return 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' + encodeURIComponent(ft(t).l + ': ' + t.title) +
			'&dates=' + dates + '&details=' + encodeURIComponent(details);
	}

	function fuTypeBadge(t, big) {
		var m = ft(t);
		return '<span class="fu-ty' + (big ? ' fu-ty--lg' : '') + '" style="--ty:' + m.c + '" title="' + m.l + '">' + icon(m.i, big ? 18 : 14) + '</span>';
	}

	function fuRow(t, opt) {
		opt = opt || {};
		var lead = fuLeadOf(t) || (t.company ? { id: t.lead_id, company: t.company, contact_name: t.contact_name, phone: t.phone, email: t.email, stage: t.stage } : null);
		var b = t.done_at ? 'done' : fuBucket(t);
		var mgr = S.boot.user.manager;
		var sel = !!S.fu.sel[t.id];
		var phone = lead && lead.phone, mail = lead && lead.email;
		return '<li class="fu-row is-' + b + (sel ? ' is-sel' : '') + (Number(t.priority) ? ' is-hi' : '') + '" data-fu-id="' + t.id + '">' +
			(opt.noSelect || t.done_at ? '' : '<label class="fu-pick" title="Select"><input type="checkbox" data-fu-pick="' + t.id + '"' + (sel ? ' checked' : '') + '></label>') +
			'<button class="fu-done' + (t.done_at ? ' is-on' : '') + '" data-fu-done="' + t.id + '" aria-label="' + (t.done_at ? 'Reopen' : 'Complete') + ': ' + esc(t.title) + '">' + icon('check', 13) + '</button>' +
			fuTypeBadge(t) +
			'<div class="fu-main" data-fu-edit="' + t.id + '">' +
				'<div class="fu-t">' + (Number(t.priority) ? '<span class="fu-flag" title="High priority">' + icon('flag', 12) + '</span>' : '') + esc(t.title) + '</div>' +
				'<div class="fu-s">' +
					(lead ? '<a href="#/lead/' + lead.id + '" class="fu-co">' + esc(lead.company) + '</a>' + (lead.contact_name ? '<span>' + esc(lead.contact_name) + '</span>' : '') +
						(lead.stage ? '<span class="fu-st">' + esc(stageLabel(lead.stage)) + '</span>' : '') : '<span>No deal linked</span>') +
					(mgr && t.rep_name ? '<span class="fu-own">' + icon('users', 11) + ' ' + esc(t.rep_name) + '</span>' : '') +
					(t.done_at && t.outcome ? '<span class="fu-out">' + esc(t.outcome) + '</span>' : '') +
				'</div>' +
				(t.notes && !opt.compact ? '<div class="fu-n">' + esc(String(t.notes).slice(0, 160)) + '</div>' : '') +
			'</div>' +
			'<div class="fu-when is-' + b + '">' + (t.done_at ? 'Done ' + whenLc(t.done_at) : esc(fuDueText(t))) + '</div>' +
			(t.done_at ? '' : '<div class="fu-acts">' +
				(phone ? '<a class="fu-ic" href="tel:' + esc(phone) + '" title="Call ' + esc(phone) + '">' + icon('phone', 14) + '</a>' : '') +
				(mail ? '<a class="fu-ic" href="mailto:' + esc(mail) + '?subject=' + encodeURIComponent(lead.company || '') + '" title="Email ' + esc(mail) + '">' + icon('mail', 14) + '</a>' : '') +
				'<button class="fu-ic" data-fu-snooze="' + t.id + '" title="Snooze">' + icon('clock', 14) + '</button>' +
				'<button class="fu-ic" data-fu-log="' + t.id + '" title="Complete and log the outcome">' + icon('pen', 14) + '</button>' +
			'</div>') +
			'</li>';
	}

	function fuFiltered(list) {
		var q = (S.fu.q || '').toLowerCase();
		return list.filter(function (t) {
			if (S.fu.type && (t.type || 'todo') !== S.fu.type) return false;
			if (S.fu.rep && String(t.rep_id) !== String(S.fu.rep)) return false;
			if (!q) return true;
			var l = fuLeadOf(t) || t;
			return (t.title + ' ' + (t.notes || '') + ' ' + (l.company || '') + ' ' + (l.contact_name || '')).toLowerCase().indexOf(q) > -1;
		});
	}

	function fuNoNext() {
		var has = {};
		S.flow.tasks.forEach(function (t) { if (t.lead_id) has[t.lead_id] = 1; });
		return (S.leads || []).filter(function (l) {
			return ['won', 'lost'].indexOf(l.stage) < 0 && !l.archived_at && !has[l.id] && (!S.fu.rep || String(l.rep_id) === String(S.fu.rep));
		}).sort(function (a, b) { return Number(b.est_value || 0) - Number(a.est_value || 0); });
	}

	function viewTasks() {
		var mgr = S.boot.user.manager;
		var open = fuFiltered(S.flow.tasks);
		var groups = { overdue: [], today: [], tomorrow: [], week: [], later: [], none: [] };
		open.forEach(function (t) { groups[fuBucket(t)].push(t); });
		var all = S.flow.tasks;
		var cnt = { overdue: 0, today: 0, soon: 0 };
		all.forEach(function (t) { var b = fuBucket(t); if (b === 'overdue') cnt.overdue++; else if (b === 'today') cnt.today++; else if (b === 'tomorrow' || b === 'week') cnt.soon++; });
		var queue = groups.overdue.concat(groups.today);
		var noNext = fuNoNext();
		var tab = S.fu.tab;

		var sections;
		if (tab === 'done') sections = [['Completed', fuFiltered(S.flow.done)]];
		else if (tab === 'upcoming') sections = [['Tomorrow', groups.tomorrow], ['Later this week', groups.week], ['Later', groups.later]];
		else if (tab === 'none') sections = [['No date set', groups.none]];
		else if (tab === 'all') sections = [['Overdue', groups.overdue], ['Today', groups.today], ['Tomorrow', groups.tomorrow], ['Later this week', groups.week], ['Later', groups.later], ['No date', groups.none]];
		else sections = [['Overdue', groups.overdue], ['Due today', groups.today]];

		var body = sections.filter(function (s) { return s[1].length; }).map(function (s) {
			return '<div class="fu-sec"><div class="fu-sec__h"><b>' + s[0] + '</b><span>' + s[1].length + '</span></div><ul class="fu-list">' + s[1].map(function (t) { return fuRow(t); }).join('') + '</ul></div>';
		}).join('');
		if (!body) {
			body = tab === 'focus'
				? '<div class="fu-clear">' + icon('check', 34) + '<h4>You’re all caught up.</h4><p>Nothing overdue or due today' + (groups.tomorrow.length ? ' — ' + groups.tomorrow.length + ' due tomorrow.' : '.') + '</p>' +
					(noNext.length ? '<button class="axh-btn axh-btn--primary axh-btn--sm" data-fu-tab="nonext">Plan next steps for ' + noNext.length + (noNext.length === 1 ? ' deal' : ' deals') + '</button>' : '') + '</div>'
				: '<div class="fu-clear"><h4>Nothing here.</h4><p>Try another view or filter.</p></div>';
		}

		var tabs = [['focus', 'Focus', cnt.overdue + cnt.today], ['upcoming', 'Upcoming', cnt.soon + all.filter(function (t) { return fuBucket(t) === 'later'; }).length], ['none', 'No date', all.filter(function (t) { return fuBucket(t) === 'none'; }).length], ['all', 'All open', all.length], ['done', 'Completed', null]];

		var html = '<div class="axh-page-head"><div><h1 class="thunder">Follow-ups</h1>' +
			'<p>' + (cnt.overdue ? '<b class="fu-red">' + cnt.overdue + ' overdue</b> · ' : '') + cnt.today + ' due today · ' + S.flow.doneWeek + ' done this week</p></div>' +
			'<div class="axh-actions">' +
			(queue.length ? '<button class="axh-btn axh-btn--ghost" data-fu-queue>' + icon('play', 15) + ' Start queue <span class="fu-pillc">' + queue.length + '</span></button>' : '') +
			'<button class="axh-btn axh-btn--primary" data-add-task="0">' + icon('plus', 15) + ' Add follow-up</button></div></div>';

		html += '<div class="fu-score">' +
			fuScore('overdue', 'Overdue', cnt.overdue, 'need attention now', 'focus') +
			fuScore('today', 'Due today', cnt.today, 'on today’s list', 'focus') +
			fuScore('soon', 'Next 7 days', cnt.soon, 'coming up', 'upcoming') +
			fuScore('done', 'Done this week', S.flow.doneWeek, 'completed', 'done') +
			fuScore('gap', 'No next step', noNext.length, noNext.length === 1 ? 'open deal' : 'open deals', 'nonext') +
			'</div>';

		html += '<div class="fu-bar"><div class="fu-tabs" role="tablist">' + tabs.map(function (t) {
			return '<button role="tab" class="' + (tab === t[0] ? 'is-on' : '') + '" data-fu-tab="' + t[0] + '">' + t[1] + (t[2] !== null ? '<span>' + t[2] + '</span>' : '') + '</button>';
		}).join('') + '</div><div class="fu-filters">' +
			'<div class="fu-types">' + ['', 'call', 'email', 'text', 'meeting', 'todo'].map(function (k) {
				return '<button class="' + (S.fu.type === k ? 'is-on' : '') + '" data-fu-type="' + k + '" title="' + (k ? FT[k].l : 'All types') + '">' + (k ? icon(FT[k].i, 13) + '<em>' + FT[k].l + '</em>' : 'All') + '</button>';
			}).join('') + '</div>' +
			(mgr && (S.boot.assignees || []).length ? '<select class="axh-select fu-rep" data-fu-rep aria-label="Owner"><option value="">Everyone</option>' + S.boot.assignees.map(function (a) {
				return '<option value="' + a.id + '"' + (String(S.fu.rep) === String(a.id) ? ' selected' : '') + '>' + esc(a.name) + '</option>'; }).join('') + '</select>' : '') +
			'<input class="fu-search" type="search" data-fu-q placeholder="Filter follow-ups…" value="' + esc(S.fu.q) + '"></div></div>';

		var side = '<div class="axh-card fu-gap"><div class="axh-card__h"><h3>Deals with no next step</h3><span class="axh-badge axh-badge--' + (noNext.length ? 'amber' : 'green') + '">' + noNext.length + '</span></div>' +
			'<p class="axh-hint">Every open deal should have a dated next step. These don’t — so nothing will remind you.</p>' +
			(noNext.length ? '<ul class="fu-gap__l">' + noNext.slice(0, 12).map(function (l) {
				return '<li><div><a href="#/lead/' + l.id + '">' + esc(l.company) + '</a><span>' + esc(stageLabel(l.stage)) + (Number(l.est_value) ? ' · ' + money(l.est_value) : '') + (mgr && l.rep_name ? ' · ' + esc(l.rep_name) : '') + '</span></div>' +
					'<button class="axh-btn axh-btn--ghost axh-btn--sm" data-add-task="' + l.id + '">' + icon('plus', 12) + ' Add</button></li>';
			}).join('') + '</ul>' + (noNext.length > 12 ? '<p class="axh-hint">+ ' + (noNext.length - 12) + ' more</p>' : '') : '<p class="fu-ok">' + icon('check', 14) + ' Every open deal has a next step.</p>') + '</div>';

		if (tab === 'nonext') {
			body = '<div class="fu-sec"><div class="fu-sec__h"><b>Open deals without a scheduled follow-up</b><span>' + noNext.length + '</span></div>' +
				(noNext.length ? '<ul class="fu-list">' + noNext.map(function (l) {
					return '<li class="fu-row"><span class="fu-ty" style="--ty:#B45309">' + icon('flag', 14) + '</span><div class="fu-main"><div class="fu-t"><a href="#/lead/' + l.id + '">' + esc(l.company) + '</a></div>' +
						'<div class="fu-s"><span>' + esc(l.contact_name || 'No contact') + '</span><span class="fu-st">' + esc(stageLabel(l.stage)) + '</span>' + (Number(l.est_value) ? '<span>' + money(l.est_value) + '</span>' : '') + (mgr && l.rep_name ? '<span class="fu-own">' + esc(l.rep_name) + '</span>' : '') + '</div></div>' +
						'<div class="fu-acts is-on"><button class="axh-btn axh-btn--primary axh-btn--sm" data-add-task="' + l.id + '">' + icon('plus', 12) + ' Schedule next step</button></div></li>';
				}).join('') + '</ul>' : '<div class="fu-clear">' + icon('check', 34) + '<h4>Every open deal has a next step.</h4></div>') + '</div>';
			side = '';
		}

		html += '<div class="fu-grid' + (side ? '' : ' is-wide') + '"><div class="axh-card fu-card">' + body + '</div>' + side + '</div>';
		html += fuBulkBar();
		if (!S.flow.ok) html += '<p class="axh-hint" style="margin-top:12px">Showing the basic list — the follow-up service did not answer.</p>';
		return html;
	}
	function fuScore(k, label, n, sub, tab) {
		return '<button class="fu-sc fu-sc--' + k + (n ? '' : ' is-zero') + '" data-fu-tab="' + tab + '"><span>' + label + '</span><b>' + n + '</b><em>' + sub + '</em></button>';
	}
	function fuBulkBar() {
		var ids = Object.keys(S.fu.sel).filter(function (k) { return S.fu.sel[k]; });
		return '<div class="fu-bulk' + (ids.length ? ' is-on' : '') + '" id="fu-bulk"><b>' + ids.length + ' selected</b>' +
			'<button class="axh-btn axh-btn--primary axh-btn--sm" data-fu-bulk="done">' + icon('check', 13) + ' Complete</button>' +
			'<button class="axh-btn axh-btn--ghost axh-btn--sm" data-fu-bulk="tomorrow">Move to tomorrow</button>' +
			'<button class="axh-btn axh-btn--ghost axh-btn--sm" data-fu-bulk="nextweek">Next week</button>' +
			'<button class="axh-btn axh-btn--danger axh-btn--sm" data-fu-bulk="delete">' + icon('trash', 13) + ' Delete</button>' +
			'<button class="axh-linkbtn" data-fu-bulk="clear">Clear</button></div>';
	}

	function fuFind(id) {
		return S.flow.tasks.concat(S.flow.done).filter(function (t) { return String(t.id) === String(id); })[0] ||
			((S.lead && S.lead.tasks) || []).filter(function (t) { return String(t.id) === String(id); })[0] || null;
	}
	function fuRefresh() {
		if (/^#\/tasks/.test(location.hash)) return fuLoad(false).then(function () { render(viewTasks()); });
		if (/^#\/leads/.test(location.hash)) return fuBoard().then(function () { render(viewLeads()); fpReopen(); });
		return route();
	}

	/* ---------- add / edit */
	function fuForm(t, leadId) {
		t = t || {};
		var mgr = S.boot.user.manager;
		var d = fuDue(t), timed = fuTimed(t);
		var dateV = d && !isNaN(d) ? ymd(d) : ymd(addDays(new Date(), 1));
		var timeV = timed && d ? pad2(d.getHours()) + ':' + pad2(d.getMinutes()) : '09:00';
		var lid = String(t.lead_id || leadId || '');
		var leads = (S.leads || []).filter(function (l) { return !l.archived_at && (['won', 'lost'].indexOf(l.stage) < 0 || String(l.id) === lid); })
			.sort(function (a, b) { return String(a.company).localeCompare(String(b.company)); });
		var type = t.type || 'call';
		return '<div class="fu-form">' +
			'<div class="fu-types fu-types--form">' + FTK.map(function (k) {
				return '<button type="button" class="' + (type === k ? 'is-on' : '') + '" data-fuf-type="' + k + '" style="--ty:' + FT[k].c + '">' + icon(FT[k].i, 15) + '<em>' + FT[k].l + '</em></button>';
			}).join('') + '</div><input type="hidden" data-fuf="type" value="' + type + '">' +
			'<div class="axh-field"><label>What’s the next step?</label><input type="text" data-fuf="title" value="' + esc(t.title || '') + '" placeholder="' + esc(FT[type].l === 'Call' ? 'Call to walk through the proposal' : 'Send the case study') + '" maxlength="191"></div>' +
			'<div class="axh-field"><label>Deal</label><select data-fuf="lead_id"><option value="">No deal</option>' + leads.map(function (l) {
				return '<option value="' + l.id + '"' + (String(l.id) === lid ? ' selected' : '') + '>' + esc(l.company) + (l.contact_name ? ' — ' + esc(l.contact_name) : '') + '</option>';
			}).join('') + '</select></div>' +
			'<div class="axh-field"><label>When</label><div class="fu-quick">' +
				[['Today', 0], ['Tomorrow', 1], ['In 2 days', 2], ['Next week', 7], ['In 2 weeks', 14]].map(function (q) {
					return '<button type="button" data-fuf-day="' + q[1] + '">' + q[0] + '</button>';
				}).join('') + '<button type="button" data-fuf-day="mon">Next Monday</button><button type="button" data-fuf-day="none">No date</button></div>' +
				'<div class="fu-when-in"><input type="date" data-fuf="date" value="' + (t.id && !t.due_at ? '' : dateV) + '">' +
				'<label class="fu-tog"><input type="checkbox" data-fuf="timed"' + (timed || (!t.id && (type === 'call' || type === 'meeting')) ? ' checked' : '') + '> At a time</label>' +
				'<input type="time" data-fuf="time" value="' + timeV + '" step="900"></div></div>' +
			'<div class="fu-form__row"><label class="fu-tog fu-tog--hi"><input type="checkbox" data-fuf="priority"' + (Number(t.priority) ? ' checked' : '') + '> ' + icon('flag', 13) + ' High priority</label>' +
			(mgr && (S.boot.assignees || []).length ? '<div class="axh-field fu-owner"><label>Owner</label><select data-fuf="rep_id"><option value="">' + (t.id ? 'Keep current' : 'Me') + '</option>' +
				S.boot.assignees.map(function (a) { return '<option value="' + a.id + '"' + (t.id && String(t.rep_id) === String(a.id) ? ' selected' : '') + '>' + esc(a.name) + '</option>'; }).join('') + '</select></div>' : '') + '</div>' +
			'<div class="axh-field"><label>Notes</label><textarea data-fuf="notes" rows="3" placeholder="Talking points, what they asked for, links…">' + esc(t.notes || '') + '</textarea></div>' +
			'</div>';
	}
	function fuRead(m) {
		var g = function (k) { var x = m.querySelector('[data-fuf="' + k + '"]'); return x ? (x.type === 'checkbox' ? x.checked : x.value) : ''; };
		var out = { title: String(g('title')).trim(), type: g('type') || 'todo', lead_id: g('lead_id') || null, priority: g('priority') ? 1 : 0, notes: g('notes') };
		var date = g('date'), timed = !!g('timed') && !!date;
		if (!date) { out.due_at = ''; out.timed = 0; }
		else if (timed) {
			var p = date.split('-'), tm = String(g('time') || '09:00').split(':');
			out.due_at = utcStamp(new Date(+p[0], +p[1] - 1, +p[2], +tm[0], +tm[1]));
			out.timed = 1;
		} else { out.due_at = date; out.timed = 0; }
		if (g('rep_id')) out.rep_id = g('rep_id');
		return out;
	}
	function fuWire(m) {
		m.querySelector('.axh-modal__box').classList.add('fu-modal');
		m.addEventListener('click', function (e) {
			var ty = e.target.closest('[data-fuf-type]');
			if (ty) {
				m.querySelectorAll('[data-fuf-type]').forEach(function (b) { b.classList.toggle('is-on', b === ty); });
				m.querySelector('[data-fuf="type"]').value = ty.getAttribute('data-fuf-type');
				var tg = m.querySelector('[data-fuf="timed"]');
				if (tg && !tg.dataset.touched) tg.checked = ['call', 'meeting'].indexOf(ty.getAttribute('data-fuf-type')) > -1;
				return;
			}
			var qd = e.target.closest('[data-fuf-day]');
			if (qd) {
				var v = qd.getAttribute('data-fuf-day'), inp = m.querySelector('[data-fuf="date"]');
				inp.value = v === 'none' ? '' : ymd(v === 'mon' ? nextMonday() : addDays(new Date(), +v));
				m.querySelectorAll('[data-fuf-day]').forEach(function (b) { b.classList.toggle('is-on', b === qd); });
			}
		});
		var tg = m.querySelector('[data-fuf="timed"]');
		if (tg) tg.addEventListener('change', function () { tg.dataset.touched = '1'; });
	}
	function fuOpenForm(t, leadId, after) {
		var editing = !!(t && t.id);
		var m = modal(editing ? 'Edit follow-up' : 'Schedule a follow-up', fuForm(t, leadId),
			(editing ? '<button class="axh-btn axh-btn--danger" data-fuf-del>' + icon('trash', 14) + '</button>' +
				(t.due_at ? '<a class="axh-btn axh-btn--quiet" href="' + esc(fuGcal(t)) + '" target="_blank" rel="noopener">' + icon('cal', 14) + ' Add to Google Calendar</a>' : '') : '') +
			'<span style="flex:1"></span><button class="axh-btn axh-btn--ghost" data-close>Cancel</button>' +
			'<button class="axh-btn axh-btn--primary" data-fuf-save>' + (editing ? 'Save' : 'Schedule') + '</button>');
		fuWire(m);
		var ti = m.querySelector('[data-fuf="title"]'); if (ti) ti.focus();
		m.querySelector('[data-fuf-save]').onclick = function () {
			var d = fuRead(m);
			if (!d.title) { toast('Say what the next step is.', 'bad'); ti.focus(); return; }
			var p = editing ? api('/flow/tasks/' + t.id, { method: 'POST', body: d }) : api('/flow/tasks', { method: 'POST', body: d });
			p.then(function () {
				closeModal();
				toast(editing ? 'Follow-up saved.' : 'Scheduled' + (d.due_at ? ' for ' + fuDueText(d).toLowerCase() : '') + '.', 'ok');
				(after || fuRefresh)();
			}).catch(function (er) { toast(er.message, 'bad'); });
		};
		var del = m.querySelector('[data-fuf-del]');
		if (del) del.onclick = function () {
			if (!del.getAttribute('data-armed')) { del.setAttribute('data-armed', '1'); del.textContent = 'Click again to delete'; setTimeout(function () { if (del.isConnected) { del.removeAttribute('data-armed'); del.innerHTML = icon('trash', 14); } }, 4000); return; }
			api('/flow/tasks/bulk', { method: 'POST', body: { ids: [t.id], action: 'delete' } })
				.then(function () { closeModal(); toast('Deleted.', 'ok'); (after || fuRefresh)(); })
				.catch(function (er) { toast(er.message, 'bad'); });
		};
		m.addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target.tagName === 'INPUT' && e.target.type === 'text') m.querySelector('[data-fuf-save]').click(); });
	}

	/* ---------- complete, log outcome, schedule the next one */
	function fuOutcomeHtml(t) {
		var m = ft(t), lead = fuLeadOf(t);
		var nextType = t.type === 'meeting' ? 'email' : (t.type || 'call');
		return '<div class="fu-form">' +
			'<div class="fu-ctx">' + fuTypeBadge(t, true) + '<div><b>' + esc(t.title) + '</b><span>' + (lead ? esc(lead.company) + (lead.contact_name ? ' · ' + esc(lead.contact_name) : '') : 'No deal linked') + '</span></div></div>' +
			'<div class="axh-field"><label>How did it go?</label><div class="fu-quick fu-quick--out">' + m.o.map(function (o, i) {
				return '<button type="button" data-fuo="' + esc(o) + '"' + (i === 0 && m.o.length === 1 ? ' class="is-on"' : '') + '>' + esc(o) + '</button>';
			}).join('') + '</div><input type="hidden" data-fuo-v value="' + (m.o.length === 1 ? esc(m.o[0]) : '') + '"></div>' +
			'<div class="axh-field"><label>Notes for the deal timeline</label><textarea data-fuo-note rows="3" placeholder="What did they say? Anything to remember next time?"></textarea></div>' +
			(t.lead_id ? '<div class="fu-next"><label class="fu-tog"><input type="checkbox" data-fun-on checked> Schedule the next step</label>' +
				'<div class="fu-next__b"><div class="fu-types fu-types--mini">' + FTK.map(function (k) {
					return '<button type="button" class="' + (nextType === k ? 'is-on' : '') + '" data-fun-type="' + k + '" title="' + FT[k].l + '" style="--ty:' + FT[k].c + '">' + icon(FT[k].i, 13) + '</button>';
				}).join('') + '</div><input type="text" data-fun-title value="' + esc('Follow up: ' + (lead ? lead.company : t.title)) + '">' +
				'<div class="fu-quick">' + [['Tomorrow', 1], ['2 days', 2], ['3 days', 3], ['Next week', 7], ['2 weeks', 14]].map(function (q, i) {
					return '<button type="button" data-fun-day="' + q[1] + '"' + (i === 1 ? ' class="is-on"' : '') + '>' + q[0] + '</button>';
				}).join('') + '</div><input type="hidden" data-fun-type-v value="' + nextType + '"><input type="hidden" data-fun-day-v value="2"></div></div>' : '') +
			'</div>';
	}
	function fuComplete(t, after) {
		var m = modal('Complete follow-up', fuOutcomeHtml(t),
			'<button class="axh-btn axh-btn--ghost" data-close>Cancel</button><button class="axh-btn axh-btn--primary" data-fuo-save>' + icon('check', 14) + ' Complete</button>');
		m.querySelector('.axh-modal__box').classList.add('fu-modal');
		m.addEventListener('click', function (e) {
			var o = e.target.closest('[data-fuo]');
			if (o) { m.querySelectorAll('[data-fuo]').forEach(function (b) { b.classList.toggle('is-on', b === o); }); m.querySelector('[data-fuo-v]').value = o.getAttribute('data-fuo'); return; }
			var nt = e.target.closest('[data-fun-type]');
			if (nt) { m.querySelectorAll('[data-fun-type]').forEach(function (b) { b.classList.toggle('is-on', b === nt); }); m.querySelector('[data-fun-type-v]').value = nt.getAttribute('data-fun-type'); return; }
			var nd = e.target.closest('[data-fun-day]');
			if (nd) { m.querySelectorAll('[data-fun-day]').forEach(function (b) { b.classList.toggle('is-on', b === nd); }); m.querySelector('[data-fun-day-v]').value = nd.getAttribute('data-fun-day'); }
		});
		m.querySelector('[data-fuo-save]').onclick = function () {
			var outcome = m.querySelector('[data-fuo-v]').value, note = m.querySelector('[data-fuo-note]').value;
			var on = m.querySelector('[data-fun-on]');
			var next = on && on.checked ? {
				title: m.querySelector('[data-fun-title]').value.trim() || 'Follow up',
				type: m.querySelector('[data-fun-type-v]').value,
				lead_id: t.lead_id,
				due_at: ymd(addDays(new Date(), +m.querySelector('[data-fun-day-v]').value)),
				timed: 0
			} : null;
			fuFinish(t, outcome, note, next).then(function () { closeModal(); (after || fuRefresh)(); });
		};
	}
	function fuFinish(t, outcome, note, next) {
		return api('/flow/tasks/' + t.id, { method: 'POST', body: { done: true, outcome: outcome || '', note: note || '', log: !!(outcome || note) } })
			.then(function () { return next ? api('/flow/tasks', { method: 'POST', body: next }) : null; })
			.then(function () { toast(next ? 'Done — next step scheduled for ' + fuDueText(next).toLowerCase() + '.' : 'Done.', 'ok'); })
			.catch(function (er) { toast(er.message, 'bad'); throw er; });
	}
	/* One tap: done now, with an undo and a shortcut to log it properly. */
	function fuQuickDone(t) {
		var row = document.querySelector('[data-fu-id="' + t.id + '"]');
		if (row) row.classList.add('is-leaving');
		api('/flow/tasks/' + t.id, { method: 'POST', body: { done: true } }).then(function () {
			fuUndoToast(t);
			setTimeout(fuRefresh, 260);
		}).catch(function (er) { if (row) row.classList.remove('is-leaving'); toast(er.message, 'bad'); });
	}
	function fuUndoToast(t) {
		var box = el('axh-toasts');
		if (!box) return;
		var n = document.createElement('div');
		n.className = 'axh-toast axh-toast--ok fu-toast';
		n.innerHTML = '<span>' + icon('check', 14) + ' Done: ' + esc(t.title) + '</span>' +
			(t.lead_id ? '<button data-fut="next">Schedule next</button>' : '') + '<button data-fut="undo">Undo</button>';
		box.appendChild(n);
		var kill = setTimeout(function () { n.remove(); }, 7000);
		n.addEventListener('click', function (e) {
			var b = e.target.closest('[data-fut]');
			if (!b) return;
			clearTimeout(kill); n.remove();
			if (b.getAttribute('data-fut') === 'undo') {
				api('/flow/tasks/' + t.id, { method: 'POST', body: { done: false } }).then(function () { toast('Restored.', 'ok'); fuRefresh(); });
			} else {
				fuOpenForm({ type: t.type, lead_id: t.lead_id, title: 'Follow up: ' + ((fuLeadOf(t) || {}).company || t.title) });
			}
		});
	}

	/* ---------- snooze */
	function fuSnoozeMenu(btn, ids) {
		var old = document.querySelector('.fu-menu');
		if (old) old.remove();
		var now = new Date();
		var at9 = function (d) { return utcStamp(new Date(d.getFullYear(), d.getMonth(), d.getDate(), 9, 0)); };
		var opts = [
			['In 1 hour', utcStamp(new Date(now.getTime() + 3600000)), 1],
			['In 3 hours', utcStamp(new Date(now.getTime() + 3 * 3600000)), 1],
			['Tomorrow, 9 AM', at9(addDays(now, 1)), 1],
			['In 2 days', ymd(addDays(now, 2)), 0],
			['Next Monday, 9 AM', at9(nextMonday()), 1],
			['Next week', ymd(addDays(now, 7)), 0]
		];
		var menu = document.createElement('div');
		menu.className = 'fu-menu';
		menu.innerHTML = '<b>Snooze until…</b>' + opts.map(function (o, i) { return '<button data-fus="' + i + '">' + o[0] + '</button>'; }).join('') + '<button data-fus="pick">Pick a date…</button>';
		document.body.appendChild(menu);
		var r = btn.getBoundingClientRect();
		menu.style.top = Math.min(window.innerHeight - menu.offsetHeight - 8, r.bottom + 6) + 'px';
		menu.style.left = Math.max(8, Math.min(window.innerWidth - menu.offsetWidth - 8, r.right - menu.offsetWidth)) + 'px';
		function close(e) { if (!e || !menu.contains(e.target)) { menu.remove(); document.removeEventListener('mousedown', close, true); } }
		setTimeout(function () { document.addEventListener('mousedown', close, true); }, 0);
		menu.addEventListener('click', function (e) {
			var b = e.target.closest('[data-fus]');
			if (!b) return;
			close();
			var k = b.getAttribute('data-fus');
			if (k === 'pick') { var t = fuFind(ids[0]); if (t) fuOpenForm(t); return; }
			var o = opts[+k];
			api('/flow/tasks/bulk', { method: 'POST', body: { ids: ids, action: 'snooze', due_at: o[1], timed: o[2] } })
				.then(function () { toast('Snoozed until ' + o[0].toLowerCase() + '.', 'ok'); S.fu.sel = {}; fuRefresh(); })
				.catch(function (er) { toast(er.message, 'bad'); });
		});
	}

	/* ---------- the queue: work through today's list one at a time */
	function fuQueue() {
		var list = S.flow.tasks.filter(function (t) { var b = fuBucket(t); return b === 'overdue' || b === 'today'; });
		list = fuFiltered(list).sort(function (a, b) { return (Number(b.priority) - Number(a.priority)) || (fuDue(a) - fuDue(b)); });
		if (!list.length) { toast('Nothing overdue or due today.', 'ok'); return; }
		var Q = { list: list, i: 0, done: 0 };
		var ov = document.createElement('div');
		ov.className = 'fu-q-ov';
		ov.setAttribute('role', 'dialog');
		document.body.appendChild(ov);
		document.body.classList.add('fu-q-open');

		function close() {
			ov.remove(); document.body.classList.remove('fu-q-open'); document.removeEventListener('keydown', keys);
			toast(Q.done ? 'Queue closed — ' + Q.done + ' completed.' : 'Queue closed.', 'ok');
			fuRefresh();
		}
		function keys(e) {
			if (e.target && /INPUT|TEXTAREA|SELECT/.test(e.target.tagName)) { if (e.key === 'Escape') e.target.blur(); return; }
			if (e.key === 'Escape') close();
			else if (e.key === 'Enter') { var b = ov.querySelector('[data-qa="done"]'); if (b) b.click(); }
			else if (e.key === 's' || e.key === 'S') { var k = ov.querySelector('[data-qa="skip"]'); if (k) k.click(); }
		}
		document.addEventListener('keydown', keys);

		function paintQ() {
			if (Q.i >= Q.list.length) {
				ov.innerHTML = '<div class="fu-q"><div class="fu-q__end">' + icon('check', 44) + '<h2 class="thunder">Queue cleared.</h2><p>' + Q.done + ' completed · ' + (Q.list.length - Q.done) + ' skipped or snoozed.</p>' +
					'<button class="axh-btn axh-btn--primary" data-qa="close">Back to follow-ups</button></div></div>';
				return;
			}
			var t = Q.list[Q.i], m = ft(t), lead = fuLeadOf(t) || (t.company ? { id: t.lead_id, company: t.company, contact_name: t.contact_name, phone: t.phone, email: t.email, stage: t.stage } : null);
			var pct = Math.round(Q.i / Q.list.length * 100);
			var nextType = t.type === 'meeting' ? 'email' : (t.type || 'call');
			ov.innerHTML = '<div class="fu-q">' +
				'<div class="fu-q__top"><div><b>Follow-up queue</b><span>' + (Q.i + 1) + ' of ' + Q.list.length + ' · ' + Q.done + ' done</span></div>' +
				'<div class="fu-q__bar"><i style="width:' + pct + '%"></i></div><button class="axh-x" data-qa="close" aria-label="Close queue">' + icon('x', 18) + '</button></div>' +
				'<div class="fu-q__body"><div class="fu-q__task">' +
					'<div class="fu-q__ty" style="--ty:' + m.c + '">' + icon(m.i, 16) + ' ' + m.l + (Number(t.priority) ? ' <span class="fu-flag">' + icon('flag', 12) + ' High priority</span>' : '') + '</div>' +
					'<h2 class="fu-q__t">' + esc(t.title) + '</h2>' +
					'<p class="fu-q__due is-' + fuBucket(t) + '">' + icon('clock', 13) + ' ' + esc(fuDueText(t)) + '</p>' +
					(t.notes ? '<div class="fu-q__notes">' + esc(t.notes) + '</div>' : '') +
					'<div class="axh-field"><label>Outcome</label><div class="fu-quick fu-quick--out">' + m.o.map(function (o) { return '<button type="button" data-qo="' + esc(o) + '">' + esc(o) + '</button>'; }).join('') + '</div></div>' +
					'<div class="axh-field"><label>Notes</label><textarea data-qn rows="3" placeholder="Logged to the deal’s timeline"></textarea></div>' +
					(t.lead_id ? '<div class="fu-next"><label class="fu-tog"><input type="checkbox" data-qx checked> Then schedule</label>' +
						'<div class="fu-types fu-types--mini">' + FTK.map(function (k) { return '<button type="button" class="' + (nextType === k ? 'is-on' : '') + '" data-qt="' + k + '" title="' + FT[k].l + '" style="--ty:' + FT[k].c + '">' + icon(FT[k].i, 13) + '</button>'; }).join('') + '</div>' +
						'<div class="fu-quick">' + [['Tomorrow', 1], ['2 days', 2], ['3 days', 3], ['Next week', 7]].map(function (q, i) { return '<button type="button" data-qd="' + q[1] + '"' + (i === 1 ? ' class="is-on"' : '') + '>' + q[0] + '</button>'; }).join('') + '</div></div>' : '') +
				'</div>' +
				'<div class="fu-q__deal">' + (lead ? '<div class="fu-q__co"><span class="fu-q__av">' + esc(initials(lead.company)) + '</span><div><h3>' + esc(lead.company) + '</h3><span>' + esc(lead.contact_name || 'No contact name') + (lead.stage ? ' · ' + esc(stageLabel(lead.stage)) : '') + '</span></div></div>' +
					'<div class="fu-q__cta">' + (lead.phone ? '<a class="axh-btn axh-btn--primary" href="tel:' + esc(lead.phone) + '">' + icon('phone', 15) + ' ' + esc(lead.phone) + '</a>' : '') +
					(lead.email ? '<a class="axh-btn axh-btn--ghost" href="mailto:' + esc(lead.email) + '">' + icon('mail', 15) + ' Email</a>' : '') +
					'<a class="axh-btn axh-btn--quiet" href="#/lead/' + lead.id + '" target="_blank" rel="noopener">' + icon('eye', 15) + ' Open deal</a></div>' +
					'<div class="fu-q__tl" data-qtl><p class="axh-hint">Loading recent activity…</p></div>' : '<div class="fu-q__co"><div><h3>No deal linked</h3><span>A general follow-up.</span></div></div>') + '</div></div>' +
				'<div class="fu-q__foot"><button class="axh-btn axh-btn--quiet" data-qa="skip">Skip <kbd>S</kbd></button>' +
				'<button class="axh-btn axh-btn--ghost" data-qa="snooze">' + icon('clock', 14) + ' Tomorrow 9 AM</button>' +
				'<button class="axh-btn axh-btn--primary" data-qa="done">' + icon('check', 15) + ' Complete &amp; next <kbd>Enter</kbd></button></div></div>';
			if (lead && lead.id) {
				api('/leads/' + lead.id).then(function (d) {
					var box = ov.querySelector('[data-qtl]');
					if (!box || Q.list[Q.i] !== t) return;
					var acts = (d.activity || []).slice(0, 5);
					box.innerHTML = '<h4>Recent activity</h4>' + (acts.length ? '<ul>' + acts.map(function (a) {
						return '<li><b>' + esc(a.subject || a.type) + '</b><span>' + when(a.occurred_at) + '</span></li>';
					}).join('') + '</ul>' : '<p class="axh-hint">Nothing logged yet.</p>') +
						((d.lead && d.lead.notes) ? '<h4>Notes</h4><p class="fu-q__ln">' + esc(String(d.lead.notes).slice(0, 400)) + '</p>' : '');
				}).catch(function () { var box = ov.querySelector('[data-qtl]'); if (box) box.innerHTML = ''; });
			}
		}
		ov.addEventListener('click', function (e) {
			var b = e.target.closest('[data-qo],[data-qt],[data-qd],[data-qa]');
			if (!b) return;
			var pick = function (attr) { ov.querySelectorAll('[' + attr + ']').forEach(function (x) { x.classList.toggle('is-on', x === b); }); };
			if (b.hasAttribute('data-qo')) return pick('data-qo');
			if (b.hasAttribute('data-qt')) return pick('data-qt');
			if (b.hasAttribute('data-qd')) return pick('data-qd');
			var a = b.getAttribute('data-qa'), t = Q.list[Q.i];
			if (a === 'close') return close();
			if (a === 'skip') { Q.i++; return paintQ(); }
			b.disabled = true;
			if (a === 'snooze') {
				var d = addDays(new Date(), 1);
				api('/flow/tasks/bulk', { method: 'POST', body: { ids: [t.id], action: 'snooze', due_at: utcStamp(new Date(d.getFullYear(), d.getMonth(), d.getDate(), 9, 0)), timed: 1 } })
					.then(function () { Q.i++; paintQ(); }).catch(function (er) { b.disabled = false; toast(er.message, 'bad'); });
				return;
			}
			if (a === 'done') {
				var o = ov.querySelector('[data-qo].is-on'), x = ov.querySelector('[data-qx]');
				var nt = ov.querySelector('[data-qt].is-on'), nd = ov.querySelector('[data-qd].is-on');
				var lead = fuLeadOf(t);
				var next = x && x.checked ? { title: 'Follow up: ' + (lead ? lead.company : t.company || t.title), type: nt ? nt.getAttribute('data-qt') : 'call', lead_id: t.lead_id, due_at: ymd(addDays(new Date(), nd ? +nd.getAttribute('data-qd') : 2)), timed: 0 } : null;
				fuFinish(t, o ? o.getAttribute('data-qo') : '', (ov.querySelector('[data-qn]') || {}).value || '', next)
					.then(function () { Q.done++; Q.i++; paintQ(); }, function () { b.disabled = false; });
			}
		});
		paintQ();
	}

	/* ---------- events (capture phase, ahead of the older handlers) */
	document.addEventListener('click', function (e) {
		var t = e.target, hit = function (s) { return t.closest ? t.closest(s) : null; };
		var b;
		if ((b = hit('[data-add-task]'))) {
			e.preventDefault(); e.stopPropagation();
			var lid = b.getAttribute('data-add-task');
			var lead = (S.leads || []).filter(function (l) { return String(l.id) === String(lid); })[0] || (S.lead && S.lead.lead && String(S.lead.lead.id) === String(lid) ? S.lead.lead : null);
			fuOpenForm(lid && lid !== '0' ? { type: 'call', lead_id: lid, title: lead ? 'Call ' + (lead.contact_name || lead.company) : '' } : null, lid !== '0' ? lid : null);
			return;
		}
		if ((b = hit('[data-fu-tab]'))) { e.stopPropagation(); S.fu.tab = b.getAttribute('data-fu-tab'); if (!/^#\/tasks/.test(location.hash)) { location.hash = '#/tasks'; return; } render(viewTasks()); return; }
		if ((b = hit('[data-fu-type]'))) { e.stopPropagation(); S.fu.type = b.getAttribute('data-fu-type'); render(viewTasks()); return; }
		if ((b = hit('[data-fu-queue]'))) { e.stopPropagation(); fuQueue(); return; }
		if ((b = hit('[data-fu-done]'))) {
			e.preventDefault(); e.stopPropagation();
			var tk = fuFind(b.getAttribute('data-fu-done'));
			if (!tk) return;
			if (tk.done_at) api('/flow/tasks/' + tk.id, { method: 'POST', body: { done: false } }).then(function () { toast('Reopened.', 'ok'); fuRefresh(); });
			else fuQuickDone(tk);
			return;
		}
		if ((b = hit('[data-fu-log]'))) { e.stopPropagation(); var tl = fuFind(b.getAttribute('data-fu-log')); if (tl) fuComplete(tl); return; }
		if ((b = hit('[data-fu-snooze]'))) { e.stopPropagation(); fuSnoozeMenu(b, [b.getAttribute('data-fu-snooze')]); return; }
		if (hit('[data-fu-pick]') || hit('.fu-pick')) { e.stopPropagation(); return; }
		if ((b = hit('[data-fu-edit]')) && !hit('a[href]')) { e.stopPropagation(); var te = fuFind(b.getAttribute('data-fu-edit')); if (te && !te.done_at) fuOpenForm(te); return; }
		if ((b = hit('[data-fu-bulk]'))) {
			e.stopPropagation();
			var act = b.getAttribute('data-fu-bulk');
			var ids = Object.keys(S.fu.sel).filter(function (k) { return S.fu.sel[k]; });
			if (act === 'clear' || !ids.length) { S.fu.sel = {}; render(viewTasks()); return; }
			var body = { ids: ids, action: act === 'done' || act === 'delete' ? act : 'snooze' };
			if (act === 'tomorrow') { body.due_at = ymd(addDays(new Date(), 1)); body.timed = 0; }
			if (act === 'nextweek') { body.due_at = ymd(nextMonday()); body.timed = 0; }
			var runBulk = function () {
				api('/flow/tasks/bulk', { method: 'POST', body: body }).then(function (r) {
					toast((r.count || ids.length) + ' updated.', 'ok'); S.fu.sel = {}; fuRefresh();
				}).catch(function (er) { toast(er.message, 'bad'); });
			};
			if (act === 'delete') ask({ title: 'Delete ' + ids.length + ' follow-up' + (ids.length === 1 ? '' : 's') + '?', body: 'They are removed for good. Anything already logged on the deals stays.', ok: 'Delete', danger: true }).then(runBulk);
			else runBulk();
			return;
		}
	}, true);
	document.addEventListener('change', function (e) {
		var t = e.target;
		if (t.hasAttribute && t.hasAttribute('data-fu-pick')) {
			S.fu.sel[t.getAttribute('data-fu-pick')] = t.checked;
			var row = t.closest('.fu-row'); if (row) row.classList.toggle('is-sel', t.checked);
			var bar = el('fu-bulk'); if (bar) bar.outerHTML = fuBulkBar();
		}
		if (t.hasAttribute && t.hasAttribute('data-fu-rep')) { S.fu.rep = t.value; render(viewTasks()); }
	});
	var fuQT;
	document.addEventListener('input', function (e) {
		var t = e.target;
		if (!(t.hasAttribute && t.hasAttribute('data-fu-q'))) return;
		clearTimeout(fuQT);
		fuQT = setTimeout(function () {
			S.fu.q = t.value; var pos = t.selectionStart;
			render(viewTasks());
			var n = document.querySelector('[data-fu-q]'); if (n) { n.focus(); try { n.setSelectionRange(pos, pos); } catch (x) {} }
		}, 220);
	});

	/* Follow-ups on a deal's own page, with types and times. */
	function fuLeadList(tasks, leadId, stage) {
		var open = (tasks || []).filter(function (t) { return !t.done_at; });
		var closed = stage === 'won' || stage === 'lost';
		var done = (tasks || []).filter(function (t) { return t.done_at; }).slice(0, 4);
		return (open.length ? '<ul class="fu-list fu-list--lead">' + open.map(function (t) { return fuRow(t, { noSelect: true, compact: true }); }).join('') + '</ul>'
			: (closed ? '<p class="axh-hint">' + (stage === 'won' ? 'Closed won — schedule check-ins or onboarding calls here.' : 'Closed lost — nothing scheduled.') + '</p>'
				: '<div class="fu-none">' + icon('flag', 14) + ' No next step scheduled. <button class="axh-linkbtn" data-add-task="' + leadId + '">Schedule one</button></div>')) +
			(done.length ? '<details class="fu-donebox"><summary>' + done.length + ' completed</summary><ul class="fu-list fu-list--lead">' + done.map(function (t) { return fuRow(t, { noSelect: true, compact: true }); }).join('') + '</ul></details>' : '');
	}

	/* ===================================================== pipeline (1.8) */

	S.pl = S.pl || { sort: 'activity', attention: false };
	function fpProb(stage) { var p = (S.flow.board && S.flow.board.probability) || {}; return p[stage] !== undefined ? Number(p[stage]) : ({ new: 10, contacted: 20, discovery: 35, scoping: 50, proposal: 70, won: 100, lost: 0 }[stage] || 0); }
	function fpMeta(l) { var m = S.flow.board && S.flow.board.meta; return (m && m[l.id]) || null; }
	function fpRot() { return (S.flow.board && S.flow.board.rot_days) || 14; }
	function fpDays(d) { var x = d ? srvDate(d) : null; return x && !isNaN(x) ? Math.max(0, Math.floor((Date.now() - x.getTime()) / 86400000)) : null; }
	function fpFlags(l) {
		var m = fpMeta(l), closed = ['won', 'lost'].indexOf(l.stage) > -1;
		var inStage = m ? fpDays(m.stage_at) : null, idle = m ? fpDays(m.last_at) : null;
		var next = m && m.next, nb = next ? fuBucket(next) : null;
		return {
			meta: m, closed: closed, inStage: inStage, idle: idle, next: next,
			overdue: !closed && nb === 'overdue',
			nonext: !closed && !!m && !next,
			rot: !closed && idle !== null && idle >= fpRot()
		};
	}
	function fpNeeds(f) { return f.overdue || f.nonext || f.rot; }

	function fpSort(list) {
		var k = S.pl.sort;
		return list.slice().sort(function (a, b) {
			var fa = fpFlags(a), fb = fpFlags(b);
			if (k === 'value') return Number(b.est_value || 0) - Number(a.est_value || 0);
			if (k === 'stage') return (fb.inStage || 0) - (fa.inStage || 0);
			if (k === 'next') {
				var da = fa.next && fa.next.due_at ? fuDue(fa.next).getTime() : Infinity, db = fb.next && fb.next.due_at ? fuDue(fb.next).getTime() : Infinity;
				return da - db;
			}
			return (fa.idle === null ? 1e9 : fa.idle) - (fb.idle === null ? 1e9 : fb.idle);
		});
	}

	function fpStrip(leads) {
		var open = leads.filter(function (l) { return ['won', 'lost'].indexOf(l.stage) < 0; });
		var total = 0, weighted = 0, need = 0, nonext = 0;
		open.forEach(function (l) {
			var v = Number(l.est_value || 0); total += v; weighted += v * fpProb(l.stage) / 100;
			var f = fpFlags(l); if (fpNeeds(f)) need++; if (f.nonext) nonext++;
		});
		var m0 = new Date(); m0 = new Date(m0.getFullYear(), m0.getMonth(), 1);
		var won = leads.filter(function (l) { return l.stage === 'won' && l.closed_at && srvDate(l.closed_at) >= m0; });
		var wonV = won.reduce(function (a, l) { return a + Number(l.contract_value || l.est_value || 0); }, 0);
		var closed = leads.filter(function (l) { return l.stage === 'won' || l.stage === 'lost'; });
		var rate = closed.length ? Math.round(leads.filter(function (l) { return l.stage === 'won'; }).length / closed.length * 100) : null;
		return '<div class="fp-strip">' +
			'<div class="fp-s"><span>Open pipeline</span><b>' + money(total) + '</b><em>' + open.length + ' open ' + (open.length === 1 ? 'deal' : 'deals') + '</em></div>' +
			'<div class="fp-s fp-s--acc"><span>Weighted forecast</span><b>' + money(weighted) + '</b><em>value × stage likelihood</em></div>' +
			'<button class="fp-s fp-s--warn' + (S.pl.attention ? ' is-on' : '') + (need ? '' : ' is-zero') + '" data-fp-attn><span>Needs attention</span><b>' + need + '</b><em>' + (need ? (nonext ? nonext + ' with no next step' : 'overdue or going cold') : 'every deal is moving') + '</em></button>' +
			'<div class="fp-s fp-s--ok"><span>Won this month</span><b>' + money(wonV) + '</b><em>' + won.length + (won.length === 1 ? ' deal' : ' deals') + (rate !== null ? ' · ' + rate + '% win rate' : '') + '</em></div>' +
			'</div>';
	}

	function fpCard(l, mgr) {
		var f = fpFlags(l), next = f.next;
		var nb = next ? fuBucket(next) : null;
		var cls = 'axh-kc fp-card' + (f.rot ? ' is-rot' : '') + (f.overdue ? ' is-due' : '') + (f.closed ? ' is-closed' : '');
		var stageChip = f.inStage !== null && !f.closed
			? '<span class="fp-age' + (f.inStage >= fpRot() * 2 ? ' is-bad' : f.inStage >= fpRot() ? ' is-warn' : '') + '" title="Days in this stage">' + icon('clock', 11) + ' ' + (f.inStage === 0 ? 'New in stage' : f.inStage + 'd in stage') + '</span>' : '';
		return '<div class="' + cls + '" draggable="true" data-id="' + l.id + '" data-fp-open="' + l.id + '" tabindex="0" role="button" aria-label="Preview ' + esc(l.company) + '">' +
			'<div class="fp-top"><div class="axh-kc__t">' + esc(l.company) + '</div><span class="axh-kc__v">' + (Number(l.est_value) ? money(l.est_value) : '') + '</span></div>' +
			'<div class="axh-kc__s">' + esc(l.contact_name || 'No contact yet') + (l.city ? ' · ' + esc(l.city) : '') + '</div>' +
			(mgr && l.rep_name ? '<div class="axh-kc__s fp-own"><span class="fp-av">' + esc(initials(l.rep_name)) + '</span>' + esc(l.rep_name) + '</div>' : '') +
			(f.closed ? '' : next
				? '<div class="fp-next is-' + nb + '">' + fuTypeBadge(next) + '<div><span>' + esc(next.title) + '</span><em>' + esc(fuDueText(next)) + '</em></div></div>'
				: (f.meta ? '<button class="fp-next fp-next--none" data-add-task="' + l.id + '">' + icon('flag', 12) + '<span>No next step</span><em>+ Schedule</em></button>' : '')) +
			'<div class="fp-foot">' + stageChip +
				(f.rot ? '<span class="fp-cold" title="No activity for ' + f.idle + ' days">Going cold · ' + f.idle + 'd</span>' : (f.idle !== null && !f.closed ? '<span class="fp-idle">Active ' + (f.idle === 0 ? 'today' : f.idle + 'd ago') + '</span>' : '')) +
				(l.reg_status === 'draft' ? '<button class="axh-kc__reg" data-register="' + l.id + '">' + icon('flag', 11) + ' Register</button>' : '') +
			'</div>' +
			'<div class="fp-quick">' +
				(l.phone ? '<a href="tel:' + esc(l.phone) + '" title="Call ' + esc(l.phone) + '">' + icon('phone', 13) + '</a>' : '') +
				(l.email ? '<a href="mailto:' + esc(l.email) + '" title="Email ' + esc(l.email) + '">' + icon('mail', 13) + '</a>' : '') +
				'<button data-add-task="' + l.id + '" title="Schedule a follow-up">' + icon('cal', 13) + '</button>' +
				'<a href="#/lead/' + l.id + '" title="Open the full record">' + icon('right', 13) + '</a>' +
			'</div></div>';
	}

	function fpBoardHtml(leads, mgr) {
		var list = S.pl.attention ? leads.filter(function (l) { return fpNeeds(fpFlags(l)); }) : leads;
		return '<div class="axh-board fp-board">' + S.boot.stages.map(function (st) {
			var inCol = fpSort(list.filter(function (l) { return l.stage === st.key; }));
			var val = inCol.reduce(function (a, l) { return a + Number(l.est_value || 0); }, 0);
			var closed = st.key === 'won' || st.key === 'lost';
			var prob = fpProb(st.key);
			return '<div class="axh-col fp-col' + (closed ? ' fp-col--closed fp-col--' + st.key : '') + '" data-stage="' + st.key + '">' +
				'<div class="fp-colh"><div class="fp-colh__t"><b>' + st.label + '</b><span class="fp-n">' + inCol.length + '</span>' +
					(closed ? '' : '<button class="fp-add" data-fp-add="' + st.key + '" title="Add a deal to ' + esc(st.label) + '">' + icon('plus', 13) + '</button>') + '</div>' +
				'<div class="fp-colh__v"><b>' + (val ? money(val) : '$0') + '</b>' + (closed ? '' : '<span title="Likelihood to close at this stage">' + prob + '% · ' + money(val * prob / 100) + ' weighted</span>') + '</div>' +
				'<div class="fp-prob"><i style="width:' + prob + '%"></i></div></div>' +
				'<div class="fp-cards">' + (inCol.length ? inCol.map(function (l) { return fpCard(l, mgr); }).join('') : '<div class="fp-empty">' + (closed ? 'Drop deals here' : 'No deals') + '</div>') + '</div></div>';
		}).join('') + '</div>';
	}

	/* ---------- quick look at a deal without leaving the board */
	function fpDrawer(id) {
		var l = (S.leads || []).filter(function (x) { return String(x.id) === String(id); })[0];
		if (!l) return;
		S.pl.open = id;
		var dr = el('fp-drawer');
		if (!dr) {
			dr = document.createElement('div');
			dr.id = 'fp-drawer';
			dr.innerHTML = '<div class="fp-dr__bg" data-fp-close></div><aside class="fp-dr" role="dialog" aria-label="Deal preview"></aside>';
			document.body.appendChild(dr);
		}
		var box = dr.querySelector('.fp-dr');
		box.innerHTML = fpDrawerHtml(l, null);
		dr.classList.add('is-on');
		api('/leads/' + id).then(function (d) {
			if (String(S.pl.open) !== String(id)) return;
			S.lead = d;
			box.innerHTML = fpDrawerHtml(d.lead || l, d);
		}).catch(function () {});
	}
	function fpClose() { var dr = el('fp-drawer'); if (dr) dr.classList.remove('is-on'); S.pl.open = null; }
	function fpReopen() { if (S.pl.open && el('fp-drawer') && el('fp-drawer').classList.contains('is-on')) fpDrawer(S.pl.open); }
	function fpDrawerHtml(l, d) {
		var f = fpFlags(l), mgr = S.boot.user.manager;
		var stages = S.boot.stages.filter(function (s) { return s.key !== 'won' || mgr; });
		return '<div class="fp-dr__h"><div><span class="fp-dr__k">' + esc(stageLabel(l.stage)) + (f.inStage !== null ? ' · ' + f.inStage + 'd in stage' : '') + '</span>' +
			'<h2 class="thunder">' + esc(l.company) + '</h2>' +
			'<p>' + esc(l.contact_name || 'No contact') + (l.title ? ', ' + esc(l.title) : '') + (l.city ? ' · ' + esc(l.city) : '') + '</p></div>' +
			'<button class="axh-x" data-fp-close aria-label="Close">' + icon('x', 18) + '</button></div>' +
			'<div class="fp-dr__cta">' + (l.phone ? '<a class="axh-btn axh-btn--primary axh-btn--sm" href="tel:' + esc(l.phone) + '">' + icon('phone', 14) + ' Call</a>' : '') +
				(l.email ? '<a class="axh-btn axh-btn--ghost axh-btn--sm" href="mailto:' + esc(l.email) + '">' + icon('mail', 14) + ' Email</a>' : '') +
				'<button class="axh-btn axh-btn--ghost axh-btn--sm" data-add-task="' + l.id + '">' + icon('cal', 14) + ' Follow-up</button>' +
				'<button class="axh-btn axh-btn--ghost axh-btn--sm" data-fp-log="' + l.id + '">' + icon('pen', 14) + ' Log</button>' +
				'<a class="axh-btn axh-btn--quiet axh-btn--sm" href="#/lead/' + l.id + '">Full record ' + icon('right', 13) + '</a></div>' +
			'<div class="fp-dr__kv"><div><span>Value</span><b>' + (Number(l.est_value) ? money(l.est_value) : '—') + '</b></div>' +
				'<div><span>Weighted</span><b>' + money(Number(l.est_value || 0) * fpProb(l.stage) / 100) + '</b></div>' +
				'<div><span>Last activity</span><b>' + (f.idle === null ? '—' : f.idle === 0 ? 'Today' : f.idle + 'd ago') + '</b></div>' +
				(mgr ? '<div><span>Owner</span><b>' + esc(l.rep_name || '—') + '</b></div>' : '<div><span>Source</span><b>' + esc(srcBase(l.source)) + '</b></div>') + '</div>' +
			(f.rot ? '<div class="fp-dr__warn">' + icon('clock', 14) + ' No activity for ' + f.idle + ' days — this deal is going cold.</div>' : '') +
			'<div class="fp-dr__sec"><h4>Move to</h4><div class="fp-stages">' + stages.map(function (s) {
				return '<button class="' + (s.key === l.stage ? 'is-on' : '') + '" data-fp-stage="' + s.key + '" data-lead="' + l.id + '">' + esc(s.label) + '</button>';
			}).join('') + '</div></div>' +
			'<div class="fp-dr__sec"><h4>Next steps <button class="axh-linkbtn" data-add-task="' + l.id + '">+ Add</button></h4>' +
				(d ? fuLeadList(d.tasks, l.id, l.stage) : '<p class="axh-hint">Loading…</p>') + '</div>' +
			'<div class="fp-dr__sec"><h4>Activity</h4>' + (d ? ((d.activity || []).length ? '<ul class="fp-tl">' + d.activity.slice(0, 8).map(function (a) {
				return '<li><b>' + esc(a.subject || a.type) + '</b><span>' + when(a.occurred_at) + '</span>' + (a.body ? '<p>' + esc(String(a.body).slice(0, 220)) + '</p>' : '') + '</li>';
			}).join('') + '</ul>' : '<p class="axh-hint">Nothing logged yet.</p>') : '<p class="axh-hint">Loading…</p>') + '</div>' +
			(l.notes ? '<div class="fp-dr__sec"><h4>Notes</h4><p class="fp-notes">' + esc(String(l.notes).slice(0, 800)) + '</p></div>' : '');
	}

	/* ---------- lost needs a reason; won goes through the existing approval */
	var LOST = ['Price', 'Went with someone else', 'No budget', 'Bad timing', 'Stopped responding', 'Doing it themselves', 'Not a fit'];
	function fpLost(id, done) {
		var m = modal('Why was it lost?', '<div class="fu-form"><div class="fu-quick fu-quick--out">' + LOST.map(function (r) { return '<button type="button" data-lr="' + esc(r) + '">' + esc(r) + '</button>'; }).join('') + '</div>' +
			'<div class="axh-field" style="margin-top:12px"><label>Details (optional)</label><textarea data-lr-note rows="3" placeholder="What would have won it?"></textarea></div>' +
			'<p class="axh-hint">Lost reasons show on the deal’s timeline and help spot patterns.</p></div>',
			'<button class="axh-btn axh-btn--ghost" data-close>Cancel</button><button class="axh-btn axh-btn--danger" data-lr-save>Mark as lost</button>');
		m.querySelector('.axh-modal__box').classList.add('fu-modal');
		var reason = '';
		m.addEventListener('click', function (e) { var b = e.target.closest('[data-lr]'); if (!b) return; reason = b.getAttribute('data-lr'); m.querySelectorAll('[data-lr]').forEach(function (x) { x.classList.toggle('is-on', x === b); }); });
		m.querySelector('[data-lr-save]').onclick = function () {
			if (!reason) { toast('Pick a reason.', 'bad'); return; }
			var note = m.querySelector('[data-lr-note]').value;
			api('/leads/' + id, { method: 'POST', body: { stage: 'lost' } })
				.then(function () { return api('/flow/log', { method: 'POST', body: { lead_id: id, type: 'lost', subject: 'Lost: ' + reason, body: note } }).catch(function () {}); })
				.then(function () { closeModal(); toast('Marked lost — ' + reason.toLowerCase() + '.', 'ok'); (done || fpRefresh)(); })
				.catch(function (er) { toast(er.message, 'bad'); });
		};
	}
	function fpRefresh() {
		return Promise.all([refresh(true), fuBoard()]).then(function () {
			if (/^#\/leads/.test(location.hash)) { render(viewLeads()); fpReopen(); } else route();
		});
	}
	function fpMove(id, stage) {
		if (stage === 'lost') return fpLost(id);
		if (stage === 'won') return; // handled by the existing Closed Won flow
		api('/leads/' + id, { method: 'POST', body: { stage: stage } }).then(function () {
			toast('Moved to ' + stageLabel(stage) + '.', 'ok');
			fpRefresh();
		}).catch(function (er) { toast(er.message, 'bad'); });
	}

	/* Quick log from the drawer: one line straight onto the timeline. */
	function fpQuickLog(id) {
		var m = modal('Log activity', '<div class="fu-form"><div class="fu-types fu-types--form">' + [['call', 'Call'], ['email', 'Email'], ['meeting', 'Meeting'], ['note', 'Note']].map(function (k, i) {
			var meta = FT[k[0]] || { i: 'pen', c: '#475467' };
			return '<button type="button" class="' + (i === 0 ? 'is-on' : '') + '" data-ql="' + k[0] + '" style="--ty:' + meta.c + '">' + icon(meta.i, 15) + '<em>' + k[1] + '</em></button>';
		}).join('') + '</div><div class="axh-field"><label>Summary</label><input type="text" data-ql-s placeholder="Talked through pricing; wants a start date in March" maxlength="191"></div>' +
			'<div class="axh-field"><label>Details</label><textarea data-ql-b rows="3"></textarea></div></div>',
			'<button class="axh-btn axh-btn--ghost" data-close>Cancel</button><button class="axh-btn axh-btn--primary" data-ql-save>Log it</button>');
		m.querySelector('.axh-modal__box').classList.add('fu-modal');
		var type = 'call';
		m.addEventListener('click', function (e) { var b = e.target.closest('[data-ql]'); if (!b) return; type = b.getAttribute('data-ql'); m.querySelectorAll('[data-ql]').forEach(function (x) { x.classList.toggle('is-on', x === b); }); });
		m.querySelector('[data-ql-save]').onclick = function () {
			var s = m.querySelector('[data-ql-s]').value.trim();
			if (!s) { toast('Add a one-line summary.', 'bad'); return; }
			var label = { call: 'Call', email: 'Email', meeting: 'Meeting', note: 'Note' }[type];
			api('/flow/log', { method: 'POST', body: { lead_id: id, type: type, subject: label + ': ' + s, body: m.querySelector('[data-ql-b]').value } })
				.then(function () { closeModal(); toast('Logged.', 'ok'); fpRefresh(); })
				.catch(function (er) { toast(er.message, 'bad'); });
		};
	}

	document.addEventListener('click', function (e) {
		var t = e.target, hit = function (s) { return t.closest ? t.closest(s) : null; };
		var b;
		if ((b = hit('[data-fp-close]'))) { e.stopPropagation(); fpClose(); return; }
		if ((b = hit('[data-stage="lost"][data-lead]')) && !hit('#fp-drawer')) { e.stopPropagation(); fpLost(b.getAttribute('data-lead'), route); return; }
		if ((b = hit('[data-fp-attn]'))) { e.stopPropagation(); S.pl.attention = !S.pl.attention; render(viewLeads()); return; }
		if ((b = hit('[data-fp-stage]'))) {
			e.stopPropagation();
			var st = b.getAttribute('data-fp-stage'), id = b.getAttribute('data-lead');
			if (st === 'won') { var won = document.querySelector('[data-close-deal="' + id + '"],[data-win-req="' + id + '"]'); if (won) won.click(); else location.hash = '#/lead/' + id; return; }
			fpMove(id, st);
			return;
		}
		if ((b = hit('[data-fp-log]'))) { e.stopPropagation(); fpQuickLog(b.getAttribute('data-fp-log')); return; }
		if ((b = hit('[data-fp-add]'))) {
			e.stopPropagation();
			var stage = b.getAttribute('data-fp-add');
			S.pl.newStage = stage;
			var nl = document.querySelector('[data-new]');
			if (nl) nl.click();
			return;
		}
		if ((b = hit('[data-fp-open]')) && !hit('a[href],button,input,select,label')) { e.stopPropagation(); fpDrawer(b.getAttribute('data-fp-open')); return; }
	}, true);
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && el('fp-drawer') && el('fp-drawer').classList.contains('is-on') && el('axh-modal').hidden) { fpClose(); return; }
		if (e.key === 'Enter' && e.target && e.target.hasAttribute && e.target.hasAttribute('data-fp-open')) { e.preventDefault(); e.stopPropagation(); fpDrawer(e.target.getAttribute('data-fp-open')); }
	}, true);
	window.addEventListener('hashchange', function () { if (!/^#\/leads/.test(location.hash)) fpClose(); });

	/* A deal added from a column's "+" lands in that column. */
	function fpAfterCreate(leadId) {
		var st = S.pl.newStage; S.pl.newStage = null;
		if (!st || st === 'new') return Promise.resolve();
		return api('/leads/' + leadId, { method: 'POST', body: { stage: st } }).catch(function () {});
	}

	/* --------------------------------------------------------- manage */

	/* Account status as a badge: where each rep is with getting started. */
	var STATUS = {
		active: ['green', 'Active'],
		invited: ['amber', 'Invited'],
		password_set: ['amber', 'Password set'],
		invite_failed: ['red', 'Email failed'],
		not_invited: ['gray', 'Not invited'],
		deactivated: ['gray', 'Access removed']
	};
	function statusBadge(r) {
		var s = STATUS[r.status] || ['gray', r.status];
		return '<span class="axh-badge axh-badge--' + s[0] + '">' + s[1] + '</span>';
	}
	function whenLc(d) { var w = when(d); return /^(Today|Tomorrow|Yesterday)$/.test(w) ? w.toLowerCase() : w; }
	function statusLine(r) {
		if (r.status === 'active') return r.last_login ? 'Last signed in ' + whenLc(r.last_login) : 'Signed in';
		if (r.status === 'invited') return 'Invited ' + whenLc(r.invited_at) + (r.invite_count > 1 ? ' (' + r.invite_count + ' times)' : '') + ' · not signed in yet';
		if (r.status === 'invite_failed') return 'The invitation email did not go out ' + whenLc(r.invite_failed) + '. Resend it or copy the link.';
		if (r.status === 'password_set') return 'Has a password · not signed in yet';
		if (r.status === 'not_invited') return 'Added ' + whenLc(r.registered) + ' · no invitation on record';
		return 'Access removed ' + whenLc(r.deactivated_at) + '. Leads and commission are kept.';
	}

	function bar(label, value, max, right) {
		var pct = max ? Math.round(value / max * 100) : 0;
		return '<div class="axh-barrow"><div class="axh-barrow__t"><span>' + label + '</span><b>' + right + '</b></div>' +
			'<div class="axh-ring__bar"><i style="width:' + Math.max(pct, value ? 3 : 0) + '%"></i></div></div>';
	}

	/* Team overview: the manager's dashboard. */
	function viewManage(d) {
		var s = d.summary, db = d.dashboard || {}, reps = d.reps || [];
		var pend = (s.pending_reg || 0) + (s.pending_win || 0);
		var notIn = reps.filter(function (r) { return r.active && r.status !== 'active'; });
		var com = db.commission || {};
		var board = (db.leaderboard || []).filter(function (b) { return b.won_value || b.pipeline || b.active; });

		var attention = [];
		if (pend) attention.push('<a href="#/manage/registrations">' + icon('flag', 15) + ' ' + [s.pending_reg ? s.pending_reg + ' registration' + (s.pending_reg === 1 ? '' : 's') : '', s.pending_win ? s.pending_win + ' Closed Won' : ''].filter(Boolean).join(' and ') + ' to decide</a>');
		if (com.due > 0) attention.push('<a href="#/manage/payouts">' + icon('chart', 15) + ' ' + moneyC(com.due) + ' commission ready to pay</a>');
		var obA = S.boot.onboarding || {};
		if (obA.verify) attention.push('<a href="#/manage/onboarding">' + icon('shield', 15) + ' ' + obA.verify + ' new payout address' + (obA.verify === 1 ? '' : 'es') + ' to verify before the next check</a>');
		if (obA.incomplete) attention.push('<a href="#/manage/onboarding">' + icon('pen', 15) + ' ' + obA.incomplete + ' rep' + (obA.incomplete === 1 ? ' is' : 's are') + ' still onboarding</a>');
		if (notIn.length) attention.push('<a href="#/manage/team">' + icon('users', 15) + ' ' + notIn.length + ' rep' + (notIn.length === 1 ? ' has' : 's have') + ' not signed in yet: ' + notIn.map(function (r) { return esc(r.name); }).join(', ') + '</a>');

		var stages = S.boot.stages.filter(function (st) { return ['won', 'lost'].indexOf(st.key) < 0; });
		var bs = db.by_stage || {};
		var maxStage = Math.max.apply(null, stages.map(function (st) { return (bs[st.key] || {}).value || 0; }).concat([1]));
		var maxBoard = Math.max.apply(null, board.map(function (b) { return b.won_value; }).concat([1]));

		return '<div class="axh-page-head"><div><h1 class="thunder">Team overview</h1>' +
			'<p>How the whole team is doing, and what is waiting on you.</p></div>' +
			'<div class="axh-actions"><a class="axh-btn axh-btn--ghost" href="#/manage/deals">Deals closed</a>' +
			'<button class="axh-btn axh-btn--primary" data-rep-new>' + icon('plus', 15) + ' Add representative</button></div></div>' +
			(attention.length ? '<div class="axh-attn">' + attention.join('') + '</div>' : '') +
			'<div class="axh-stats">' +
			stat('Open pipeline', money(s.pipeline), s.open_count + ' open opportunities', true) +
			stat('Weighted forecast', money(db.forecast || 0), 'Open pipeline × stage likelihood', false) +
			stat('Won this quarter', money(db.won_quarter || 0), money2(db.won_month || 0) + ' this month · ' + s.won_count + ' all time', false) +
			stat('Win rate', db.win_rate === null || db.win_rate === undefined ? '—' : db.win_rate + '%', 'Closed Won ÷ all closed deals', false) +
			'</div>' +
			'<div class="axh-grid axh-grid--2">' +
			'<div class="axh-card"><div class="axh-card__h"><h3>Leaderboard</h3><a href="#/manage/team">Team</a></div>' +
			(board.length ? '<table class="axh-table axh-table--stack"><thead><tr><th>Rep</th><th class="num">Won</th><th class="num">This quarter</th><th class="num">Pipeline</th><th class="num">Win rate</th></tr></thead><tbody>' +
				board.map(function (b, i) {
					return '<tr><td data-th="Company"><div class="axh-co">' + (b.won_value ? '<i class="axh-rank">' + (i + 1) + '</i>' : '') + '<a href="#/manage/rep/' + b.rep_id + '">' + esc(b.name) + '</a><span>' + b.won_count + ' deal' + (b.won_count === 1 ? '' : 's') + ' · ' + b.open_count + ' open</span></div>' +
						'<div class="axh-ring__bar" style="margin-top:6px"><i style="width:' + Math.round(b.won_value / maxBoard * 100) + '%"></i></div></td>' +
						'<td data-th="Won" class="num">' + money2(b.won_value) + '</td><td data-th="This quarter" class="num">' + money2(b.won_quarter) + '</td>' +
						'<td data-th="Pipeline" class="num">' + money2(b.pipeline) + '</td><td data-th="Win rate" class="num">' + (b.win_rate === null ? '—' : b.win_rate + '%') + '</td></tr>';
				}).join('') + '</tbody></table>'
				: empty('No deals yet', 'Once reps start adding prospects, the leaderboard fills in here.', '')) + '</div>' +
			'<div><div class="axh-card"><div class="axh-card__h"><h3>Pipeline by stage</h3><a href="#/leads">Pipeline</a></div>' +
			stages.map(function (st) {
				var x = bs[st.key] || { count: 0, value: 0 };
				return bar(st.label + ' <span class="axh-hint">· ' + Math.round(((db.weights || {})[st.key] || 0) * 100) + '% likely</span>', x.value, maxStage, x.count + ' · ' + money(x.value));
			}).join('') +
			'<p class="axh-hint" style="margin-top:8px">The forecast multiplies each open deal’s estimate by how likely its stage is to close. It is a rough guide, not a promise.</p></div>' +
			'<div class="axh-card" style="margin-top:16px"><div class="axh-card__h"><h3>Commission</h3><a href="#/manage/payouts">Payouts</a></div>' +
			'<div class="axh-kv"><span>Earned by the team</span><b>' + moneyC(com.earned || 0) + '</b></div>' +
			'<div class="axh-kv"><span>Paid out</span><b>' + moneyC(com.paid || 0) + '</b></div>' +
			'<div class="axh-kv"><span>Ready to pay (client paid, funds cleared)</span><b class="' + (com.due > 0 ? 'axh-warn' : '') + '">' + moneyC(com.due || 0) + '</b></div>' +
			'<div class="axh-kv"><span>Waiting on a client payment</span><b>' + moneyC(com.upcoming || 0) + '</b></div></div></div></div>' +
			'<div class="axh-card" style="margin-top:16px"><div class="axh-card__h"><h3>Routing inbound leads</h3><a href="#/forms">Lead forms</a></div>' +
			'<p style="font-size:13.5px;line-height:1.6;margin:0">A form’s submissions land with the form’s owner. To send website enquiries to a rep, open the form under ' +
			'<b>Lead forms</b> and change <b>Leads go to</b>. To move a single lead, open it and use <b>Reassign</b> — the new owner is notified.</p></div>';
	}

	/* The team: every rep, where they are with signing in, and what they have. */
	function viewTeam(d) {
		var reps = d.reps || [];
		var active = reps.filter(function (r) { return r.active; });
		var pending = active.filter(function (r) { return r.status !== 'active'; });
		return '<div class="axh-page-head"><div><h1 class="thunder">Team</h1>' +
			'<p>' + active.length + ' active representative' + (active.length === 1 ? '' : 's') + (pending.length ? ' · ' + pending.length + ' not signed in yet' : '') + '</p></div>' +
			'<button class="axh-btn axh-btn--primary" data-rep-new>' + icon('plus', 15) + ' Add representative</button></div>' +
			(pending.length ? '<div class="axh-note" style="margin-bottom:16px"><b>Waiting to sign in</b><p>' + pending.map(function (r) { return esc(r.name); }).join(', ') +
				(pending.length === 1 ? ' has' : ' have') + ' not signed in yet. Use <b>Resend invite</b> to email a fresh link, or <b>Copy link</b> to text it yourself.</p></div>' : '') +
			'<div class="axh-card">' + (reps.length
				? '<table class="axh-table axh-table--stack axh-table--team"><thead><tr><th>Representative</th><th>Status</th><th class="num">Open</th><th class="num">Pipeline</th><th class="num">Won</th><th></th></tr></thead><tbody>' +
					reps.map(function (r) {
						return '<tr class="' + (r.active ? '' : 'is-off') + '"><td data-th="Company"><div class="axh-co"><a href="#/manage/rep/' + r.id + '">' + esc(r.name) + '</a><span>' + esc(r.email) + (r.territory ? ' · ' + esc(r.territory) : '') + '</span></div></td>' +
							'<td data-th="Status">' + statusBadge(r) + '<div class="axh-hint" style="margin-top:4px">' + statusLine(r) + '</div></td>' +
							'<td data-th="Open" class="num">' + r.open_count + '</td><td data-th="Pipeline" class="num">' + money2(r.pipeline) + '</td>' +
							'<td data-th="Won" class="num">' + r.won_count + ' · ' + money2(r.won_value) + '</td>' +
							'<td class="axh-row-acts">' +
							(r.active && r.status !== 'active' ? '<button class="axh-btn axh-btn--primary axh-btn--sm" data-rep-do="invite" data-rep-id="' + r.id + '" data-rep-name="' + esc(r.name) + '">' + (r.invite_count ? 'Resend invite' : 'Send invite') + '</button>' : '') +
							'<a class="axh-btn axh-btn--ghost axh-btn--sm" href="#/manage/rep/' + r.id + '">Manage</a></td></tr>';
					}).join('') + '</tbody></table>'
				: empty('No representatives yet', 'Add one and they get an email with a link to set their password, the Hub address and their first-week checklist.', '<button class="axh-btn axh-btn--primary" data-rep-new>Add representative</button>')) +
			'</div>';
	}

	/* One rep, everything about them. */
	function viewRep(d) {
		var r = d.rep, e = d.earnings || { totals: {} }, t = e.totals || {};
		var leads = d.leads || [], acts = d.activity || [], pays = d.payouts || [];
		var open = leads.filter(function (l) { return ['won', 'lost'].indexOf(l.stage) < 0 && !l.archived_at; });
		var actions = r.active
			? (r.status !== 'active' ? '<button class="axh-btn axh-btn--primary" data-rep-do="invite" data-rep-id="' + r.id + '" data-rep-name="' + esc(r.name) + '">' + icon('mail', 15) + ' ' + (r.invite_count ? 'Resend invite' : 'Send invite') + '</button>' : '<button class="axh-btn axh-btn--ghost" data-rep-do="signin" data-rep-id="' + r.id + '" data-rep-name="' + esc(r.name) + '">' + icon('mail', 15) + ' Email a sign-in link</button>') +
				'<button class="axh-btn axh-btn--ghost" data-rep-edit="' + r.id + '">Edit</button>' +
				'<button class="axh-btn axh-btn--ghost" data-rep-more="' + r.id + '" data-rep-name="' + esc(r.name) + '">More…</button>'
			: '<button class="axh-btn axh-btn--primary" data-rep-do="reactivate" data-rep-id="' + r.id + '" data-rep-name="' + esc(r.name) + '">Reactivate</button>';

		return '<p class="axh-crumb"><a href="#/manage/team">' + icon('back', 14) + ' Team</a></p>' +
			'<div class="axh-page-head"><div><h1 class="thunder">' + esc(r.name) + '</h1>' +
			'<p>' + statusBadge(r) + ' &nbsp;' + statusLine(r) + '</p></div><div class="axh-actions">' + actions + '</div></div>' +
			'<div class="axh-stats">' +
			stat('Open', open.length, money2(r.pipeline) + ' pipeline', true) +
			stat('Closed won', r.won_count, money2(r.won_value) + ' in contracts', false) +
			stat('Commission earned', moneyC(t.earned || 0), moneyC(t.paid || 0) + ' paid', false) +
			stat('Due to them now', moneyC(t.due || 0), t.due > 0 ? '<a href="#/manage/payouts">Record a payout</a>' : 'Nothing outstanding', false) +
			'</div>' +
			'<div class="axh-grid axh-grid--2"><div>' +
			'<div class="axh-card"><div class="axh-card__h"><h3>Profile</h3><button class="axh-linkbtn" data-rep-edit="' + r.id + '">Edit</button></div>' +
			'<div class="axh-kv"><span>Email</span><b><a href="mailto:' + esc(r.email) + '">' + esc(r.email) + '</a></b></div>' +
			'<div class="axh-kv"><span>Phone</span><b>' + (r.phone ? '<a href="tel:' + esc(r.phone.replace(/[^0-9+]/g, '')) + '">' + esc(r.phone) + '</a>' : '—') + '</b></div>' +
			'<div class="axh-kv"><span>Username</span><b>' + esc(r.login) + '</b></div>' +
			'<div class="axh-kv"><span>Territory</span><b>' + (r.territory ? esc(r.territory) : '—') + '</b></div>' +
			'<div class="axh-kv"><span>Industries</span><b>' + (r.industries && r.industries.length ? r.industries.map(esc).join(', ') : 'Any') + '</b></div>' +
			'<div class="axh-kv"><span>Commission</span><b>' + (Number(r.rate_override) > 0 ? pctTxt(r.rate_override) + ' flat (override)' : 'Standard schedule') + '</b></div>' +
			'<div class="axh-kv"><span>Onboarding</span><b><a href="#/manage/onboarding/rep/' + r.id + '">Agreement, W-9 &amp; payout address</a></b></div>' +
			'<div class="axh-kv"><span>Added</span><b>' + when(r.registered) + '</b></div>' +
			'<div class="axh-kv"><span>First sign-in</span><b>' + (r.activated_at ? when(r.activated_at) : '—') + '</b></div>' +
			'<div class="axh-kv"><span>Last sign-in</span><b>' + (r.last_login ? when(r.last_login) : 'Never') + '</b></div>' +
			'<div class="axh-kv"><span>Last activity</span><b>' + (r.last_activity ? when(r.last_activity) : 'None yet') + '</b></div>' +
			(r.admin_note ? '<p class="axh-hint" style="margin-top:10px;white-space:pre-wrap">' + esc(r.admin_note) + '</p>' : '') + '</div>' +
			'<div class="axh-card" style="margin-top:16px"><div class="axh-card__h"><h3>Recent activity</h3></div>' +
			(acts.length ? '<ul class="axh-list">' + acts.slice(0, 15).map(function (a) {
				return '<li><div class="axh-list__t"><b>' + esc(a.subject || a.type) + '</b><span>' + esc(a.company || '') + '</span></div>' +
					'<a class="axh-list__r" href="#/lead/' + a.lead_id + '">' + when(a.occurred_at) + '</a></li>';
			}).join('') + '</ul>' : '<p class="axh-hint">Nothing logged yet.</p>') + '</div></div>' +
			'<div><div class="axh-card"><div class="axh-card__h"><h3>Pipeline</h3><span class="axh-hint">' + leads.length + ' leads</span></div>' +
			(leads.length ? '<table class="axh-table axh-table--stack"><thead><tr><th>Company</th><th>Stage</th><th class="num">Value</th><th>Updated</th></tr></thead><tbody>' +
				leads.slice(0, 60).map(function (l) {
					return '<tr><td data-th="Company"><div class="axh-co"><a href="#/lead/' + l.id + '">' + esc(l.company) + '</a><span>' + esc(l.city || '') + (l.archived_at ? ' · archived' : '') + '</span></div></td>' +
						'<td data-th="Stage"><span class="axh-badge axh-badge--' + stageTone(l.stage) + '">' + stageLabel(l.stage) + '</span></td>' +
						'<td data-th="Value" class="num">' + money2(l.stage === 'won' ? l.contract_value : l.est_value) + '</td>' +
						'<td data-th="Updated" style="color:var(--gray)">' + when(l.updated_at) + '</td></tr>';
				}).join('') + '</tbody></table>' : '<p class="axh-hint">No leads yet.</p>') + '</div>' +
			'<div class="axh-card" style="margin-top:16px"><div class="axh-card__h"><h3>Payouts</h3><a href="#/manage/payouts">All payouts</a></div>' +
			(pays.length ? '<ul class="axh-list">' + pays.slice(0, 10).map(function (p) {
				return '<li><div class="axh-list__t"><b>' + moneyC(p.amount) + ' · ' + esc(p.company || '') + '</b><span>' + (p.leg === 'deposit' ? 'Deposit half' : 'Final half') + (p.method ? ' · ' + esc(p.method) : '') + (p.reference ? ' · ' + esc(p.reference) : '') + '</span></div>' +
					'<span class="axh-list__r">' + when(p.paid_at) + '</span></li>';
			}).join('') + '</ul>' : '<p class="axh-hint">No payouts recorded yet.</p>') + '</div></div></div>';
	}

	/* ------------------------------------------------------------ the team */

	function industryChecks(sel) {
		sel = sel || [];
		return '<div class="axh-chips">' + S.boot.industries.map(function (i) {
			return '<label class="axh-chip' + (sel.indexOf(i) > -1 ? ' is-on' : '') + '"><input type="checkbox" data-ind value="' + esc(i) + '"' + (sel.indexOf(i) > -1 ? ' checked' : '') + ' hidden>' + esc(i) + '</label>';
		}).join('') + '</div>';
	}
	function repFields(r) {
		r = r || {};
		return '<div class="axh-form-grid">' +
			'<div class="axh-field"><label for="axh-rn">Name *</label><input id="axh-rn" type="text" data-rf="name" value="' + esc(r.name || '') + '" placeholder="First and last name" autocomplete="off"></div>' +
			'<div class="axh-field"><label for="axh-re">Email *</label><input id="axh-re" type="email" data-rf="email" value="' + esc(r.email || '') + '" placeholder="name@example.com" autocomplete="off"></div>' +
			'<div class="axh-field"><label for="axh-rp">Phone</label><input id="axh-rp" type="tel" data-rf="phone" value="' + esc(r.phone || '') + '"></div>' +
			'<div class="axh-field"><label for="axh-rt">Territory</label><input id="axh-rt" type="text" data-rf="territory" value="' + esc(r.territory || '') + '" placeholder="e.g. East Valley, AZ"></div>' +
			'<div class="axh-field axh-span"><label>Industries they focus on <span class="axh-hint">(optional)</span></label>' + industryChecks(r.industries) + '</div>' +
			'<div class="axh-field"><label for="axh-rr">Commission override (%)</label><input id="axh-rr" type="text" inputmode="decimal" data-rf="rate_override" value="' + esc(r.rate_override || '') + '" placeholder="Blank = standard schedule">' +
			'<div class="axh-field__help">Only if their signed agreement says something different. Applies to deals approved from now on.</div></div>' +
			'<div class="axh-field axh-span"><label for="axh-ra">Private note <span class="axh-hint">(only AdaptExec sees this)</span></label><textarea id="axh-ra" data-rf="admin_note" rows="2">' + esc(r.admin_note || '') + '</textarea></div>' +
			'</div>';
	}
	function readRep(m) {
		var out = {};
		m.querySelectorAll('[data-rf]').forEach(function (i) { out[i.getAttribute('data-rf')] = i.type === 'checkbox' ? i.checked : i.value.trim(); });
		out.industries = [].slice.call(m.querySelectorAll('[data-ind]:checked')).map(function (i) { return i.value; });
		return out;
	}
	function bindChips(m) {
		m.querySelectorAll('label.axh-chip input[data-ind]').forEach(function (i) {
			i.addEventListener('change', function () { i.parentNode.classList.toggle('is-on', i.checked); });
		});
	}
	function linkBox(link, days) {
		return '<div class="axh-field" style="margin-top:12px"><label>Set-password link</label>' +
			'<div class="axh-copyrow"><input type="text" readonly value="' + esc(link) + '" data-linkval>' +
			'<button class="axh-btn axh-btn--ghost axh-btn--sm" data-copy-link>' + icon('copy', 14) + ' Copy</button></div>' +
			'<div class="axh-field__help">Text or email it to them yourself. It works once and lasts ' + (days || 7) + ' day' + (days === 1 ? '' : 's') + '. Making a new link cancels this one.</div></div>';
	}

	function repNewModal() {
		var m = modal('Add a representative', repFields() +
			'<label class="axh-check"><input type="checkbox" data-rf="invite" checked> <span><b>Email them an invitation now</b><br><span class="axh-hint">A link to set their password (good for 7 days), the Hub address, their first-week checklist and how they get paid. You can preview it after adding them.</span></span></label>' +
			'<div data-rep-result></div>',
			'<button class="axh-btn axh-btn--quiet" data-close>Cancel</button>' +
			'<button class="axh-btn axh-btn--primary" data-rep-save>Add representative</button>');
		bindChips(m);
		m.querySelector('[data-rep-save]').onclick = function () {
			var d = readRep(m), btn = this;
			if (!d.name) { toast('Enter their name.', 'bad'); m.querySelector('[data-rf="name"]').focus(); return; }
			if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(d.email)) { toast('Enter a valid email address.', 'bad'); m.querySelector('[data-rf="email"]').focus(); return; }
			btn.disabled = true; btn.textContent = 'Adding…';
			api('/manage/team', { method: 'POST', body: d }).then(function (r) {
				if (r.invited && !r.emailed && r.link) {
					m.querySelector('[data-rep-result]').innerHTML = '<div class="axh-alert">Added, but the invitation email could not be sent — your mail server refused it. Send them this link yourself.</div>' + linkBox(r.link, 7);
					m.querySelector('.axh-modal__ft').innerHTML = '<a class="axh-btn axh-btn--primary" href="#/manage/rep/' + r.id + '" data-close>Done</a>';
					return;
				}
				closeModal();
				toast(r.emailed ? 'Added. ' + d.name + ' has their invitation.' : 'Added. Send the invitation from their page when you are ready.', 'ok');
				location.hash = '#/manage/rep/' + r.id;
			}).catch(function (e) { btn.disabled = false; btn.textContent = 'Add representative'; toast(e.message, 'bad'); });
		};
	}

	function repEditModal(r) {
		var m = modal('Edit ' + esc(r.name), repFields(r),
			'<button class="axh-btn axh-btn--quiet" data-close>Cancel</button><button class="axh-btn axh-btn--primary" data-rep-save>Save</button>');
		bindChips(m);
		m.querySelector('[data-rep-save]').onclick = function () {
			var d = readRep(m), btn = this;
			if (!d.name) { toast('Enter their name.', 'bad'); return; }
			d.do = 'edit'; btn.disabled = true;
			api('/manage/team/' + r.id, { method: 'POST', body: d })
				.then(function () { closeModal(); toast('Saved.', 'ok'); route(); })
				.catch(function (e) { btn.disabled = false; toast(e.message, 'bad'); });
		};
	}

	/* The invitation, the copy-able link, a password, or removing access. */
	function repMoreModal(id, name, status) {
		var m = modal(esc(name) + ': sign-in & access',
			'<div class="axh-optlist">' +
			'<button class="axh-opt" data-x="preview"><b>Preview the invitation</b><span>See exactly what they receive.</span></button>' +
			'<button class="axh-opt" data-x="test"><b>Send me a test copy</b><span>Goes to your own email, with a link that does nothing.</span></button>' +
			'<button class="axh-opt" data-x="link"><b>Copy a set-password link</b><span>Make a link to text them yourself. Cancels any earlier link.</span></button>' +
			'<button class="axh-opt" data-x="password"><b>Set a password for them</b><span>Takes effect at once and signs out their other sessions.</span></button>' +
			'<button class="axh-opt axh-opt--bad" data-x="deactivate"><b>Remove access</b><span>They can no longer sign in. Their leads, history and commission stay.</span></button>' +
			'</div><div data-more-out></div>',
			'<button class="axh-btn axh-btn--quiet" data-close>Close</button>');
		var out = m.querySelector('[data-more-out]');
		var act = function (body) { return api('/manage/team/' + id, { method: 'POST', body: body }); };
		m.querySelectorAll('[data-x]').forEach(function (b) {
			b.onclick = function () {
				var x = b.getAttribute('data-x');
				if (x === 'preview') {
					act({ do: status === 'active' ? 'signin' : 'invite', mode: 'preview' }).then(function (r) {
						out.innerHTML = '<p class="axh-hint" style="margin:12px 0 6px">Subject: <b>' + esc(r.subject) + '</b></p><iframe class="axh-mailprev" title="Invitation preview"></iframe>';
						out.querySelector('iframe').srcdoc = r.html;
					}).catch(function (e) { toast(e.message, 'bad'); });
				} else if (x === 'test') {
					b.disabled = true;
					act({ do: status === 'active' ? 'signin' : 'invite', mode: 'test' }).then(function (r) {
						b.disabled = false;
						toast(r.emailed ? 'Test copy sent to ' + r.to + '.' : 'Your mail server refused the test email. Check WP Mail SMTP.', r.emailed ? 'ok' : 'bad');
					}).catch(function (e) { b.disabled = false; toast(e.message, 'bad'); });
				} else if (x === 'link') {
					act({ do: 'link' }).then(function (r) { out.innerHTML = linkBox(r.link, r.days); }).catch(function (e) { toast(e.message, 'bad'); });
				} else if (x === 'password') {
					out.innerHTML = '<div class="axh-field" style="margin-top:12px"><label for="axh-np">New password</label><div class="axh-copyrow">' +
						'<input id="axh-np" type="text" autocomplete="off" placeholder="12 characters or more"><button class="axh-btn axh-btn--primary axh-btn--sm" data-np>Set</button></div>' +
						'<div class="axh-field__help">Tell them yourself — nothing is emailed.</div></div>';
					out.querySelector('#axh-np').focus();
					out.querySelector('[data-np]').onclick = function () {
						var v = out.querySelector('#axh-np').value;
						if (v.length < 12) { toast('Use at least 12 characters.', 'bad'); return; }
						act({ do: 'password', password: v }).then(function () { closeModal(); toast('Password set for ' + name + '.', 'ok'); route(); })
							.catch(function (e) { toast(e.message, 'bad'); });
					};
				} else if (x === 'deactivate') {
					out.innerHTML = '<div class="axh-alert" style="margin-top:12px">Remove Hub access for ' + esc(name) + '? They are signed out everywhere. You can reactivate them later.' +
						'<div style="margin-top:10px;display:flex;gap:8px"><button class="axh-btn axh-btn--danger axh-btn--sm" data-yes>Remove access</button></div></div>';
					out.querySelector('[data-yes]').onclick = function () {
						act({ do: 'deactivate' }).then(function () { closeModal(); toast('Access removed for ' + name + '.', 'ok'); route(); })
							.catch(function (e) { toast(e.message, 'bad'); });
					};
				}
			};
		});
	}

	/* Send the invitation or a sign-in link from a list or the rep's page. */
	function repDo(btn) {
		var id = btn.getAttribute('data-rep-id'), name = btn.getAttribute('data-rep-name'), x = btn.getAttribute('data-rep-do');
		var label = btn.innerHTML;
		btn.disabled = true; btn.textContent = x === 'reactivate' ? 'Reactivating…' : 'Sending…';
		api('/manage/team/' + id, { method: 'POST', body: { do: x } }).then(function (r) {
			btn.disabled = false; btn.innerHTML = label;
			if (x === 'reactivate') { toast(name + ' can sign in again.', 'ok'); route(); return; }
			if (r.emailed) { toast((x === 'invite' ? 'Invitation' : 'Sign-in link') + ' emailed to ' + name + '.', 'ok'); route(); return; }
			modal('The email could not be sent',
				'<p style="font-size:13.5px">Your mail server refused the message (WP Mail SMTP → Tools → Debug Events has the reason). Send ' + esc(name) + ' this link yourself:</p>' + linkBox(r.link, x === 'invite' ? 7 : 1),
				'<button class="axh-btn axh-btn--primary" data-close>Done</button>');
			route();
		}).catch(function (e) { btn.disabled = false; btn.innerHTML = label; toast(e.message, 'bad'); });
	}

	/* ------------------------------------------------------- deals & money */

	function csv(name, rows) {
		var text = rows.map(function (r) {
			return r.map(function (c) {
				c = c === null || c === undefined ? '' : String(c);
				return /[",\n]/.test(c) ? '"' + c.replace(/"/g, '""') + '"' : c;
			}).join(',');
		}).join('\r\n');
		var a = document.createElement('a');
		a.href = URL.createObjectURL(new Blob(['﻿' + text], { type: 'text/csv;charset=utf-8' }));
		a.download = name;
		document.body.appendChild(a); a.click();
		setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500);
	}
	function d10(s) { return s ? String(s).slice(0, 10) : ''; }

	function repOptions(sel, all) {
		return '<option value="">' + (all || 'All reps') + '</option>' + (S.boot.assignees || []).map(function (a) {
			return '<option value="' + a.id + '"' + (String(sel) === String(a.id) ? ' selected' : '') + '>' + esc(a.name) + '</option>';
		}).join('');
	}

	function viewDeals(d) {
		var f = S.dealFilter || (S.dealFilter = { status: 'won' });
		var deals = d.deals || [], t = d.totals || {};
		S.dealRows = deals;
		var label = { won: 'Closed Won', lost: 'Closed Lost', pending: 'Reported signed (awaiting approval)', all: 'All closed' }[f.status || 'won'];
		return '<div class="axh-page-head"><div><h1 class="thunder">Deals closed</h1><p>' + esc(label) + ' across the team.</p></div>' +
			'<button class="axh-btn axh-btn--ghost" data-csv="deals"' + (deals.length ? '' : ' disabled') + '>' + icon('doc', 15) + ' Export CSV</button></div>' +
			'<div class="axh-card axh-filters">' +
			'<div class="axh-field"><label for="axh-df-s">Showing</label><select id="axh-df-s" data-df="status">' +
			[['won', 'Closed Won'], ['pending', 'Awaiting approval'], ['lost', 'Closed Lost'], ['all', 'All']].map(function (o) { return '<option value="' + o[0] + '"' + (f.status === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select></div>' +
			'<div class="axh-field"><label for="axh-df-r">Rep</label><select id="axh-df-r" data-df="rep_id">' + repOptions(f.rep_id) + '</select></div>' +
			'<div class="axh-field"><label for="axh-df-f">From</label><input id="axh-df-f" type="date" data-df="from" value="' + esc(f.from || '') + '"></div>' +
			'<div class="axh-field"><label for="axh-df-t">To</label><input id="axh-df-t" type="date" data-df="to" value="' + esc(f.to || '') + '"></div>' +
			'<div class="axh-field"><label for="axh-df-q">Search</label><input id="axh-df-q" type="search" data-df="search" value="' + esc(f.search || '') + '" placeholder="Company or contact"></div>' +
			'</div>' +
			'<div class="axh-stats axh-stats--3">' +
			stat('Deals', t.count || 0, esc(label), true) +
			stat('Contract value', money2(t.contracts || 0), 'Closed Won only', false) +
			stat('Commission', moneyC(t.commission || 0), 'At the locked rates', false) + '</div>' +
			'<div class="axh-card">' + (deals.length
				? '<table class="axh-table axh-table--stack"><thead><tr><th>Client</th><th>Rep</th><th>Stage</th><th class="num">Value</th><th class="num">Rate</th><th class="num">Commission</th><th>Date</th><th>Website style</th></tr></thead><tbody>' +
					deals.map(function (x) {
						return '<tr><td data-th="Company"><div class="axh-co"><a href="#/lead/' + x.id + '">' + esc(x.company) + '</a><span>' + esc([x.industry, x.city].filter(Boolean).join(' · ')) + '</span></div></td>' +
							'<td data-th="Rep"><a href="#/manage/rep/' + x.rep_id + '">' + esc(x.rep_name) + '</a></td>' +
							'<td data-th="Stage">' + (x.stage === 'won' || x.stage === 'lost' ? '<span class="axh-badge axh-badge--' + stageTone(x.stage) + '">' + stageLabel(x.stage) + '</span>' : '<span class="axh-badge axh-badge--amber">Awaiting approval</span>') + '</td>' +
							'<td data-th="Value" class="num">' + moneyC(x.value) + '</td>' +
							'<td data-th="Rate" class="num">' + (x.stage === 'won' ? pctTxt(x.commission_rate) : '—') + '</td>' +
							'<td data-th="Commission" class="num">' + (x.stage === 'won' ? moneyC(x.commission) : '—') + '</td>' +
							'<td data-th="Date" style="color:var(--gray)">' + when(x.closed_at || x.win_requested_at) + '</td>' +
							'<td data-th="Style">' + (x.styles ? esc(x.styles) : '<span style="color:var(--gray)">—</span>') + '</td></tr>';
					}).join('') + '</tbody></table>'
				: empty('Nothing matches', 'Try a wider date range, another rep, or a different status.', '')) + '</div>';
	}
	function dealsCsv() {
		csv('adaptexec-deals-' + new Date().toISOString().slice(0, 10) + '.csv', [['Client', 'Contact', 'Rep', 'Stage', 'Industry', 'City', 'State', 'Source', 'Contract value', 'Rate %', 'Commission', 'Closed', 'Client deposit', 'Client final', 'Deposit half paid', 'Final half paid', 'Website style picks']]
			.concat((S.dealRows || []).map(function (x) {
				return [x.company, x.contact_name, x.rep_name, x.stage === 'won' || x.stage === 'lost' ? stageLabel(x.stage) : 'Awaiting approval', x.industry, x.city, x.state, x.source,
					x.value, x.stage === 'won' ? x.commission_rate : '', x.stage === 'won' ? x.commission : '', d10(x.closed_at || x.win_requested_at), d10(x.deposit_at), d10(x.final_at), d10(x.deposit_paid_at), d10(x.final_paid_at), x.styles || ''];
			})));
	}

	function viewPayouts(d) {
		S.payData = d;
		var owed = d.owed || [], legs = d.legs || [], hist = d.history || [], t = d.totals || {};
		var unpaid = legs.filter(function (l) { return l.state !== 'paid' && l.state !== 'cancelled'; })
			.sort(function (a, b) { var o = { overdue: 0, due: 1, waiting: 2 }; return (o[a.state] - o[b.state]) || String(a.due_at || '9').localeCompare(String(b.due_at || '9')); });
		return '<div class="axh-page-head"><div><h1 class="thunder">Commission & payouts</h1>' +
			'<p>What each rep is owed, who each check goes to, and every payment made. Commission is payable once the client\u2019s payment has fully cleared.</p></div>' +
			'<div class="axh-actions"><a class="axh-btn axh-btn--ghost" href="#/billing/commissions">' + icon('money', 15) + ' Payout batches</a><button class="axh-btn axh-btn--ghost" data-csv="payouts"' + (hist.length ? '' : ' disabled') + '>' + icon('doc', 15) + ' Export payments</button></div></div>' +
			'<div class="axh-stats">' +
			stat('Ready to pay', moneyC(t.due || 0), 'Client payment cleared', true) +
			stat('Waiting on clients', moneyC(t.upcoming || 0), 'Not yet triggered', false) +
			stat('Paid out', moneyC(t.paid || 0), (function (n) { return n + (n === 1 ? ' payment' : ' payments'); })(hist.filter(function (h) { return !h.voided_at; }).length), false) +
			stat('Earned in total', moneyC(t.earned || 0), money2(t.contracts || 0) + ' in contracts', false) + '</div>' +
			(t.overpaid > 0 ? '<div class="axh-alert" style="margin-bottom:16px"><b>' + moneyC(t.overpaid) + ' paid on deals that were later reduced, refunded or cancelled.</b> ' +
				'Under section 3.6 of the agreement it can be recovered or offset against future commission: ' +
				owed.filter(function (o) { return o.overpaid > 0; }).map(function (o) { return esc(o.name) + ' ' + moneyC(o.overpaid); }).join(' · ') + '.</div>' : '') +
			'<div class="axh-card"><div class="axh-card__h"><h3>Owed by rep</h3></div>' + (owed.length
				? '<table class="axh-table axh-table--stack"><thead><tr><th>Rep</th><th>Checks to</th><th class="num">Ready 30+ days</th><th class="num">Ready to pay</th><th>30-day mark</th><th class="num">Waiting on client</th><th class="num">Paid</th></tr></thead><tbody>' +
					owed.map(function (o) {
						return '<tr><td data-th="Company"><a href="#/manage/rep/' + o.rep_id + '">' + esc(o.name) + '</a>' + payReady(o.readiness, o.rep_id) + '</td>' +
							'<td data-th="Checks to">' + payeeBlock(o.payee, o.rep_id) + '</td>' +
							'<td data-th="Ready 30+ days" class="num">' + (o.overdue ? '<b class="axh-bad">' + moneyC(o.overdue) + '</b>' : '—') + '</td>' +
							'<td data-th="Ready to pay" class="num">' + (o.due ? '<b>' + moneyC(o.due) + '</b>' : '—') + '</td>' +
							'<td data-th="30-day mark">' + (o.next_due ? when(o.next_due) : '—') + '</td>' +
							'<td data-th="Waiting" class="num">' + moneyC(o.upcoming) + '</td><td data-th="Paid" class="num">' + moneyC(o.paid) + '</td></tr>';
					}).join('') + '</tbody></table>'
				: empty('No commission yet', 'Commission appears here once you confirm a deal Closed Won.', '')) + '</div>' +
			'<div class="axh-card" style="margin-top:16px"><div class="axh-card__h"><h3>To pay</h3><span class="axh-hint">Record when the client\u2019s payment has fully cleared, then mail the check and mark that half paid.</span></div>' + (unpaid.length
				? '<table class="axh-table axh-table--stack"><thead><tr><th>Client</th><th>Rep · checks to</th><th>Half</th><th class="num">Amount</th><th>Status</th><th></th></tr></thead><tbody>' +
					unpaid.map(function (l) {
						return '<tr><td data-th="Company"><div class="axh-co"><a href="#/lead/' + l.lead_id + '">' + esc(l.company) + '</a><span>' + moneyC(l.value) + ' at ' + pctTxt(l.rate) + '</span></div></td>' +
							'<td data-th="Rep"><b>' + esc(l.rep_name) + '</b>' + payeeBlock(l.payee, l.rep_id) + payReady(l.readiness, l.rep_id) + '</td><td data-th="Half">' + (l.key === 'deposit' ? 'Deposit' : 'Final') + '</td>' +
							'<td data-th="Amount" class="num"><b>' + moneyC(l.amount) + '</b></td>' +
							'<td data-th="Status">' + payState(l) + '<div class="axh-hint">' + (l.trigger ? 'Cleared ' + when(l.trigger) : 'Client has not paid yet') + '</div></td>' +
							'<td class="axh-row-acts">' + (l.hold ? '<span class="axh-badge axh-badge--amber" title="' + esc(l.hold.reason) + '">On hold</span>' : l.state === 'waiting'
								? '<button class="axh-btn axh-btn--ghost axh-btn--sm" data-client-paid="' + l.lead_id + '" data-leg="' + l.key + '">Payment cleared…</button>'
								: '<button class="axh-btn axh-btn--primary axh-btn--sm" data-mark-paid="' + l.lead_id + '" data-leg="' + l.key + '">Mark paid…</button>') + '</td></tr>';
					}).join('') + '</tbody></table>'
				: '<p class="axh-hint">Nothing waiting to be paid.</p>') + '</div>' +
			'<div class="axh-card" style="margin-top:16px"><div class="axh-card__h"><h3>Payment history</h3></div>' + (hist.length
				? '<table class="axh-table axh-table--stack"><thead><tr><th>Paid</th><th>Rep</th><th>Client</th><th>Half</th><th class="num">Amount</th><th>Method</th><th></th></tr></thead><tbody>' +
					hist.map(function (h) {
						return '<tr class="' + (h.voided_at ? 'is-off' : '') + '"><td data-th="Paid">' + when(h.paid_at) + (h.voided_at ? ' <span class="axh-badge axh-badge--gray">Void</span>' : '') + '</td>' +
							'<td data-th="Rep">' + esc(h.rep_name) + '</td><td data-th="Client"><a href="#/lead/' + h.lead_id + '">' + esc(h.company || '') + '</a></td>' +
							'<td data-th="Half">' + (h.leg === 'deposit' ? 'Deposit' : 'Final') + '</td><td data-th="Amount" class="num">' + moneyC(h.amount) + '</td>' +
							'<td data-th="Method">' + esc(h.method || '—') + (h.reference ? '<div class="axh-hint">' + esc(h.reference) + '</div>' : '') + '</td>' +
							'<td class="axh-row-acts">' + (h.voided_at ? '' : '<button class="axh-linkbtn" data-void-pay="' + h.id + '">Void</button>') + '</td></tr>';
					}).join('') + '</tbody></table>'
				: '<p class="axh-hint">No payments recorded yet.</p>') + '</div>';
	}
	function payoutsCsv() {
		var d = S.payData || {};
		csv('adaptexec-payouts-' + new Date().toISOString().slice(0, 10) + '.csv', [['Paid on', 'Rep', 'Client', 'Half', 'Amount', 'Method', 'Reference', 'Note', 'Voided']]
			.concat((d.history || []).map(function (h) { return [d10(h.paid_at), h.rep_name, h.company, h.leg, h.amount, h.method, h.reference, h.note, d10(h.voided_at)]; })));
	}
	function findLeg(leadId, leg) {
		return ((S.payData && S.payData.legs) || []).filter(function (l) { return String(l.lead_id) === String(leadId) && l.key === leg; })[0];
	}
	function markPaidModal(leadId, legKey) {
		var l = findLeg(leadId, legKey) || {};
		var today = new Date().toISOString().slice(0, 10);
		var rd = l.readiness || { ready: true, missing: [] };
		var m = modal('Mark commission paid',
			'<p class="axh-hint" style="margin-bottom:12px"><b>' + esc(l.company || '') + '</b> · ' + esc(l.rep_name || '') + ' · ' + (legKey === 'deposit' ? 'deposit' : 'final') + ' half</p>' +
			(l.payee ? '<div class="axh-paybox">' + icon('mail', 15) + '<div><span>Make the check payable to</span><b>' + esc(l.payee.payable_to) + '</b><span>' + l.payee.lines.map(esc).join('<br>') + '</span>' +
				(l.payee.verify ? '<em class="axh-bad">This address was changed recently and is not verified yet.</em>' : '') + '</div></div>' : '') +
			(rd.ready ? '' : '<div class="axh-warn" style="margin-bottom:14px"><b>' + icon('flag', 15) + ' Onboarding incomplete</b><p>Missing: ' + rd.missing.map(esc).join(', ') + '. Paying before you have these is not recommended.</p>' +
				'<label class="axh-consent axh-consent--warn" style="margin:10px 0 0"><input type="checkbox" data-pp-override><span><b>Record it anyway.</b> I have this covered another way. (The override is logged.)</span></label></div>') +
			'<div class="axh-form-grid">' +
			'<div class="axh-field"><label for="axh-pa">Amount ($)</label><input id="axh-pa" type="text" inputmode="decimal" data-pp="amount" value="' + esc(l.amount || '') + '"></div>' +
			'<div class="axh-field"><label for="axh-pd">Date paid</label><input id="axh-pd" type="date" data-pp="paid_at" value="' + today + '" max="' + today + '"></div>' +
			'<div class="axh-field"><label for="axh-pm">Method</label><select id="axh-pm" data-pp="method">' + ((S.payData && S.payData.methods) || []).map(function (x) { return '<option>' + esc(x) + '</option>'; }).join('') + '</select></div>' +
			'<div class="axh-field"><label for="axh-pr">Reference</label><input id="axh-pr" type="text" data-pp="reference" placeholder="Check #, transfer ID…"></div>' +
			'<div class="axh-field axh-span"><label for="axh-pn">Note</label><input id="axh-pn" type="text" data-pp="note"></div></div>' +
			'<p class="axh-hint">' + esc((l.rep_name || 'The rep').split(' ')[0]) + ' is notified (in the Hub, and by email if they have that on).</p>',
			'<button class="axh-btn axh-btn--quiet" data-close>Cancel</button><button class="axh-btn axh-btn--primary" data-pp-save>Record payment</button>');
		m.querySelector('[data-pp-save]').onclick = function () {
			var b = { lead_id: leadId, leg: legKey }, btn = this;
			m.querySelectorAll('[data-pp]').forEach(function (i) { b[i.getAttribute('data-pp')] = i.value; });
			var ov = m.querySelector('[data-pp-override]');
			if (ov && !ov.checked) { toast('Onboarding is incomplete. Tick "Record it anyway" to override.', 'bad'); return; }
			if (ov) b.override = true;
			if (!(Number(b.amount) > 0)) { toast('Enter the amount paid.', 'bad'); return; }
			if (!b.paid_at) { toast('Enter the date it was paid.', 'bad'); return; }
			btn.disabled = true;
			api('/manage/payouts', { method: 'POST', body: b })
				.then(function () { closeModal(); toast('Payment recorded.', 'ok'); route(); })
				.catch(function (e) { btn.disabled = false; toast(e.message, 'bad'); });
		};
	}
	function clientPaidModal(leadId, legKey) {
		var l = findLeg(leadId, legKey) || {};
		var today = new Date().toISOString().slice(0, 10);
		var m = modal(legKey === 'deposit' ? 'Client deposit cleared' : 'Client final payment cleared',
			'<p class="axh-hint" style="margin-bottom:12px"><b>' + esc(l.company || '') + '</b>. Record the date the client\u2019s payment <b>fully cleared</b> — for a check, once your bank shows the funds as final, not the day you deposit it. The rep\u2019s ' + (legKey === 'deposit' ? 'deposit' : 'final') + ' half (' + moneyC(l.amount) + ') then shows as ready to pay.</p>' +
			'<div class="axh-field"><label for="axh-cp">Date the payment cleared</label><input id="axh-cp" type="date" value="' + today + '" max="' + today + '"></div>',
			'<button class="axh-btn axh-btn--quiet" data-close>Cancel</button><button class="axh-btn axh-btn--primary" data-cp-save>Save</button>');
		m.querySelector('[data-cp-save]').onclick = function () {
			var v = m.querySelector('#axh-cp').value;
			if (!v) { toast('Enter the date.', 'bad'); return; }
			api('/leads/' + leadId + '/payment', { method: 'POST', body: { field: legKey === 'deposit' ? 'deposit_at' : 'final_at', when: v } })
				.then(function () { closeModal(); toast('Saved. That half is ready to pay.', 'ok'); route(); })
				.catch(function (e) { toast(e.message, 'bad'); });
		};
	}

	/* --------------------------------------------------- notifications */

	var NOTE_ICON = { lead_assigned: 'users', form_submission: 'mail', registration_decision: 'flag', win_decision: 'check', payout_recorded: 'chart', onboarding_update: 'shield', onboarding_admin: 'shield',
		discovery_completed: 'doc', note_on_lead: 'doc', followups_digest: 'clock', registration_request: 'flag', win_request: 'check',
		discovery_submitted: 'brief', rep_activated: 'users', team_summary: 'chart', billing_viewed: 'eye', billing_decision: 'check', billing_paid: 'money',
		share_viewed: 'eye', share_cta: 'spark' };

	function setUnread(n) {
		S.unread = n;
		var b = el('axh-bell-n');
		if (!b) return;
		b.hidden = !n;
		b.textContent = n > 99 ? '99+' : n;
		el('axh-bell').setAttribute('aria-label', n ? 'Notifications, ' + n + ' unread' : 'Notifications');
	}
	function loadNotes() {
		return api('/notifications').then(function (r) { S.notes = r.items || []; setUnread(r.unread || 0); return r; });
	}
	function paintNotes() {
		var box = el('axh-notes'), items = S.notes || [];
		var anyRead = items.some(function (n) { return !!n.read_at; });
		box.innerHTML = '<div class="axh-notes__h"><b>Notifications</b><span class="axh-notes__acts">' +
			(S.unread ? '<button class="axh-linkbtn" data-notes-all>Mark all read</button>' : '') +
			(anyRead ? '<button class="axh-linkbtn" data-notes-clear>Clear all read</button>' : '') + '</span></div>' +
			(items.length ? '<ul class="axh-notes__list">' + items.map(function (n) {
				return '<li><a href="' + esc(n.link || '#/') + '" data-note="' + n.id + '" class="' + (n.read_at ? '' : 'is-new') + '">' +
					'<span class="axh-notes__i">' + icon(NOTE_ICON[n.type] || 'spark', 16) + '</span>' +
					'<span class="axh-notes__t"><b>' + esc(n.title) + '</b>' + (n.body ? '<span>' + esc(String(n.body).split('\n')[0]).slice(0, 140) + '</span>' : '') +
					'<em>' + when(n.created_at) + '</em></span></a>' +
					'<button type="button" class="axh-notes__x" data-note-del="' + n.id + '" aria-label="Delete this notification" title="Delete">×</button></li>';
			}).join('') + '</ul>' : '<div class="axh-notes__empty">' + icon('check', 22) + '<p>You are all caught up.</p></div>') +
			'<div class="axh-notes__f"><a href="#/settings">Notification settings</a></div>';
	}
	function toggleNotes(open) {
		var box = el('axh-notes'), bell = el('axh-bell');
		if (!box) return;
		open = open === undefined ? box.hidden : open;
		box.hidden = !open;
		bell.setAttribute('aria-expanded', open ? 'true' : 'false');
		if (open) {
			paintNotes();
			loadNotes().then(paintNotes).catch(function () {});
		}
	}
	function startNotes() {
		setUnread(S.boot.unread || 0);
		setInterval(function () {
			if (document.visibilityState === 'visible') loadNotes().then(function () { if (!el('axh-notes').hidden) paintNotes(); }).catch(function () {});
		}, 60000);
	}

	/* ---------------------------------------------------------- settings */

	function viewSettings(d) {
		var prefs = d.prefs || {};
		var keys = Object.keys(prefs);
		return '<div class="axh-page-head"><div><h1 class="thunder">Settings</h1><p>Your details and what you hear about.</p></div></div>' +
			'<div class="axh-grid axh-grid--2"><div class="axh-card"><div class="axh-card__h"><h3>Your details</h3></div>' +
			'<div class="axh-field"><label for="axh-mn">Name</label><input id="axh-mn" type="text" data-me="name" value="' + esc(d.name) + '"></div>' +
			'<div class="axh-field"><label for="axh-mp">Phone</label><input id="axh-mp" type="tel" data-me="phone" value="' + esc(d.phone || '') + '" placeholder="So AdaptExec can reach you"></div>' +
			'<div class="axh-field"><label for="axh-mb">Booking link</label><input id="axh-mb" type="text" inputmode="url" data-me="booking" value="' + esc(d.booking || '') + '" placeholder="https://calendly.com/your-name">' +
				'<div class="axh-field__help">Your Calendly, Google or Microsoft booking page. Your forms can show a “Pick a time with you” button and put it in the instant reply.</div></div>' +
			'<div class="axh-field"><label>Email</label><input type="text" value="' + esc(d.email) + '" disabled><div class="axh-field__help">To change your email, ask ' + esc((S.boot.contact || {}).name || 'AdaptExec') + '.</div></div>' +
			'<div class="axh-field"><label>Username</label><input type="text" value="' + esc(d.login) + '" disabled></div>' +
			'<div style="display:flex;gap:8px;flex-wrap:wrap"><button class="axh-btn axh-btn--primary" data-me-save>Save</button>' +
			'<a class="axh-btn axh-btn--ghost" href="' + esc(S.boot.lostPasswordUrl || '/wp-login.php?action=lostpassword') + '">Change my password</a>' +
			'<a class="axh-btn axh-btn--quiet" href="' + esc(S.boot.logoutUrl) + '">Sign out</a></div></div>' +
			'<div class="axh-card"><div class="axh-card__h"><h3>Notifications</h3></div>' +
			'<p class="axh-hint" style="margin-bottom:12px">Everything shows under the bell ' + icon('bell', 13) + ' at the top of the Hub. Choose which ones are also emailed to ' + esc(d.email) + '.</p>' +
			'<ul class="axh-prefs">' + keys.map(function (k) {
				return '<li><label class="axh-switch"><input type="checkbox" data-pref="' + k + '"' + (prefs[k].email ? ' checked' : '') + '><span class="axh-switch__ui" aria-hidden="true"></span>' +
					'<span>' + esc(prefs[k].label) + '</span></label></li>';
			}).join('') + '</ul>' +
			(d.manager && d.cron ? '<p class="axh-hint" style="margin-top:12px">Morning digests run at about 7am' + (d.cron.next ? ' (next: ' + esc(d.cron.next) + ')' : '') + (d.cron.disabled ? '. WP-Cron is switched off on this site, so a server cron job must call wp-cron.php.' : '. WordPress runs them on the first site visit after that time.') + '</p>' : '') +
			'</div></div>';
	}

	function viewRegistrations(d) {
		var regs = d.registrations || [], wins = d.win_requests || [];
		return '<div class="axh-page-head"><div><h1 class="thunder">Approvals</h1>' +
			'<p>Prospect registrations and signed deals waiting on you. The rep is emailed your decision.</p></div></div>' +

			'<div class="axh-card"><div class="axh-card__h"><h3>Lead registrations</h3><span class="axh-hint">' + regs.length + ' waiting</span></div>' + (regs.length ?
				'<table class="axh-table axh-table--stack"><thead><tr><th>Company</th><th>Rep</th><th>Contact</th><th>Submitted</th><th style="width:330px">Decision</th></tr></thead><tbody>' +
				regs.map(function (l) {
					return '<tr><td data-th="Company"><div class="axh-co"><a href="#/lead/' + l.id + '">' + esc(l.company) + '</a><span>' + esc(l.city || '') + (l.industry ? ' · ' + esc(l.industry) : '') + '</span></div></td>' +
						'<td data-th="Rep">' + esc(l.rep_name || '') + '</td>' +
						'<td data-th="Contact">' + esc(l.contact_name || '—') + '</td><td data-th="Submitted" style="color:var(--gray)">' + when(l.registered_at) + '</td>' +
						'<td><div style="display:flex;gap:6px;flex-wrap:wrap">' +
						'<button class="axh-btn axh-btn--primary axh-btn--sm" data-decide="registered" data-lead="' + l.id + '">Register</button>' +
						'<button class="axh-btn axh-btn--ghost axh-btn--sm" data-decide="declined" data-lead="' + l.id + '">Decline</button>' +
						'<button class="axh-btn axh-btn--danger axh-btn--sm" data-decide="house" data-lead="' + l.id + '">House account</button>' +
						'</div></td></tr>';
				}).join('') + '</tbody></table>'
				: empty('Nothing waiting', 'When a rep submits a prospect it lands here for your decision.', '')) + '</div>' +

			'<div class="axh-card" style="margin-top:16px"><div class="axh-card__h"><h3>Closed Won to confirm</h3><span class="axh-hint">' + wins.length + ' waiting</span></div>' + (wins.length ?
				'<table class="axh-table axh-table--stack"><thead><tr><th>Company</th><th>Rep</th><th class="num">Reported value</th><th>Note</th><th style="width:250px">Decision</th></tr></thead><tbody>' +
				wins.map(function (l) {
					return '<tr><td data-th="Company"><div class="axh-co"><a href="#/lead/' + l.id + '">' + esc(l.company) + '</a><span>Reported ' + when(l.win_requested_at) + '</span></div></td>' +
						'<td data-th="Rep">' + esc(l.rep_name || '') + '</td>' +
						'<td data-th="Value" class="num">' + moneyC(l.win_request_value) + '</td>' +
						'<td data-th="Note">' + esc(l.win_request_note || '—') + '</td>' +
						'<td><div style="display:flex;gap:6px;flex-wrap:wrap">' +
						'<button class="axh-btn axh-btn--primary axh-btn--sm" data-close-deal="' + l.id + '" data-rep="' + l.rep_id + '" data-value="' + esc(l.win_request_value) + '">Approve…</button>' +
						'<button class="axh-btn axh-btn--ghost axh-btn--sm" data-win-decline="' + l.id + '">Not yet</button>' +
						'</div></td></tr>';
				}).join('') + '</tbody></table>'
				: empty('Nothing to confirm', 'When a rep reports a signed deal it lands here. Approving sets the contract value and starts their commission.', '')) + '</div>';
	}

	function viewBriefs(d) {
		var b = d.briefs || [];
		return '<div class="axh-page-head"><div><h1 class="thunder">Quote requests</h1>' +
			'<p>Questionnaires reps handed off for a written proposal. Open one for the printable brief, then start the proposal.</p></div></div>' +
			'<div class="axh-card">' + (b.length ?
				'<table class="axh-table"><thead><tr><th>Company</th><th>Rep</th><th>Industry</th><th class="num">Ready</th><th>Requested</th><th>Status</th></tr></thead><tbody>' +
				b.map(function (r) {
					return '<tr data-brief="' + r.lead_id + '"><td><div class="axh-co">' + esc(r.company) + '<span>' + esc(r.city || '') + '</span></div></td>' +
						'<td>' + esc(r.rep) + '</td><td>' + esc(r.industry || '—') + '</td>' +
						'<td class="num">' + (Number(r.quote_ready) || r.progress) + '%' + (r.quote_note ? ' ' + icon('flag', 12) : '') + '</td><td style="color:var(--gray)">' + when(r.submitted_at) + '</td>' +
						'<td><span class="axh-badge axh-badge--' + (r.status === 'reviewed' ? 'green' : 'amber') + '">' + (r.status === 'reviewed' ? 'Reviewed' : 'Needs a quote') + '</span></td></tr>';
				}).join('') + '</tbody></table>'
				: empty('No quote requests yet', 'When a rep finishes discovery and presses Request a quote, it lands here.', '')) + '</div>';
	}

	/* ------------------------------------------------------- demo sites */

	function viewDemos() {
		var sites = S.boot.demoSites || [];
		if (!sites.length) {
			return '<div class="axh-page-head"><div><h1 class="thunder">Demo sites</h1></div></div>' +
				'<div class="axh-card">' + empty('No demo sites installed yet',
					'Ask your administrator to add the demo websites to the Hub.', '') + '</div>';
		}
		return '<div class="axh-page-head"><div><h1 class="thunder">Demo sites</h1>' +
			'<p>Open one in front of a prospect, or send them the link \u2014 no login needed. Lead with their own industry. ' +
			'<b>Live demo</b> sites are real pages on adaptexec.com; <b>Concept</b> previews are earlier static mockups ' +
			'(live versions are on the way). Every live demo is also listed at ' +
			'<a href="https://adaptexec.com/demos/" target="_blank" rel="noopener">adaptexec.com/demos</a>.</p></div></div>' +
			'<div class="axh-demos">' + sites.map(function (d) {
				return '<div class="axh-demo">' +
					'<a class="axh-demo__shot" href="' + esc(d.url) + '" target="_blank" rel="noopener" ' +
						'style="border-color:' + esc(d.accent) + '33">' +
						(d.thumb ? '<img src="' + esc(d.thumb) + '" alt="' + esc(d.title) + '" loading="lazy">'
							: '<span class="axh-demo__ph" style="background:' + esc(d.accent) + '22"></span>') +
						'<span class="axh-demo__open">Open site \u2197</span></a>' +
					'<div class="axh-demo__b">' +
						'<span class="axh-demo__tag" style="color:' + esc(d.accent) + '">' + esc(d.industry) + '</span>' +
						'<strong>' + esc(d.title) + '</strong>' +
						'<p>' + esc(d.line) + '</p>' +
						'<div class="axh-demo__acts">' +
							'<a class="axh-btn axh-btn--primary axh-btn--sm" href="' + esc(d.url) + '" target="_blank" rel="noopener">Open</a>' +
							'<button class="axh-btn axh-btn--ghost axh-btn--sm" data-copy="' + esc(d.url) + '">Copy link</button>' +
						'</div></div></div>';
			}).join('') + '</div>';
	}

	/* -------------------------------------------------------- earnings */

	/* How commission actually reaches the rep. Deliberately says nothing the
	   signed agreement does not: the agreement link and the payment method
	   come from AdaptExec Hub → Settings. */
	function payoutCard() {
		var b = S.boot || {};
		var ob = b.onboarding || {}, signed = ob.steps && ob.steps.agreement && ob.steps.agreement.state !== 'todo';
		var agree = b.user && b.user.manager ? 'Reps sign it in the Hub under Onboarding.'
			: signed ? '<a href="#/onboarding/agreement">Open your signed Independent Contractor Agreement</a>'
			: b.agreementUrl ? '<a href="' + esc(b.agreementUrl) + '" target="_blank" rel="noopener">Open your agreement</a> · <a href="#/onboarding/agreement">Sign it in Onboarding</a>'
			: '<a href="#/onboarding/agreement">Read and sign it in Onboarding</a>.';
		return '<div class="axh-card" id="paid" style="margin-top:16px"><div class="axh-card__h"><h3>How you get paid</h3></div>' +
			'<ul class="axh-paylist">' +
			'<li><b>One rate on the whole contract</b>, set by its size — the table above. The rate locks when AdaptExec confirms the deal Closed Won.</li>' +
			'<li><b>Two payouts.</b> Half is earned when the client\u2019s deposit has fully cleared, half when their final payment has fully cleared. Each half shows here as waiting on the client, payable or paid.</li>' +
			'<li><b>Paid by check after the funds clear.</b> Checks go to the address in your <a href="#/onboarding/payment">payment setup</a> within a reasonable time after the client\u2019s payment clears — there is no fixed pay date. Payments that are refunded, reversed, charged back or bounce earn no commission (agreement, section 3).</li>' +
			'<li><b>You are an independent contractor</b>, not an employee. Upload your <b>Form W-9</b> in <a href="#/onboarding/w9">Onboarding</a> before your first payout; where required, AdaptExec sends you a <b>Form 1099</b>-NEC each January. Nothing is withheld for taxes, so set some aside.</li>' +
			'<li><b>Payment method:</b> ' + (b.payoutNote ? esc(b.payoutNote) : 'check, mailed to the address in your payment setup.') + '</li>' +
			'<li><b>The agreement governs.</b> Everything here summarizes your Independent Contractor Agreement. If anything here and the agreement differ, the agreement wins. ' + agree + '</li>' +
			'</ul><p class="axh-hint" style="margin-top:10px">Questions about a payment? Call or text Adam at ' + esc(KIT.phone) + ' or email ' + esc(KIT.email) + '.</p></div>';
	}

	/* Reps see waiting / payable / paid and never a date (agreement 3.4). The
	   admin's Payouts screen also flags a cleared half waiting 30+ days. */
	function payState(leg) {
		var mgr = S.boot && S.boot.user && S.boot.user.manager;
		var tone = { paid: 'green', due: 'amber', overdue: mgr ? 'red' : 'amber', waiting: 'gray', cancelled: 'gray' }[leg.state];
		var text = mgr ? { paid: 'Paid', due: 'Ready to pay', overdue: 'Ready 30+ days', waiting: 'Waiting on client', cancelled: 'Nothing due' }[leg.state]
			: { paid: 'Paid', due: 'Payable', overdue: 'Payable', waiting: 'Waiting on client', cancelled: 'Nothing due' }[leg.state];
		return '<span class="axh-badge axh-badge--' + tone + '">' + text + '</span>';
	}
	/* Who the check goes to, on the Payouts screen. */
	function payeeBlock(p, repId) {
		if (!p) return '<div class="axh-payee axh-payee--none"><a href="#/manage/onboarding/rep/' + repId + '">No payout address yet</a></div>';
		return '<div class="axh-payee"><span>' + esc(p.payable_to) + '</span><span>' + p.lines.map(esc).join(' · ') + '</span>' +
			(p.verify ? '<a class="axh-badge axh-badge--red" href="#/manage/onboarding/rep/' + repId + '">Verify new address</a>' : '') + '</div>';
	}
	function payReady(r, repId) {
		if (!r || r.ready) return '';
		return '<div><a class="axh-badge axh-badge--amber" href="#/manage/onboarding/rep/' + repId + '" title="' + esc('Missing: ' + r.missing.join(', ')) + '">Missing ' + esc(r.missing.map(function (x) { return x.replace(/ \(.*$/, ''); }).join(', ')) + '</a></div>';
	}

	function viewEarnings(d) {
		var t = d.totals, deals = d.deals || [];
		var manager = !!d.manager;

		var html = '<div class="axh-page-head"><div><h1 class="thunder">Earnings</h1>' +
			'<p>' + (manager ? 'Commission across the team. A half becomes payable once the client\u2019s payment has fully cleared.' : 'Commission on your closed business. Half is earned when your client\u2019s deposit has fully cleared and half when their final payment has, each paid by check after the funds clear.') + '</p></div>' +
			(manager ? '<a class="axh-btn axh-btn--ghost" href="#/billing/commissions">' + icon('money', 15) + ' Commissions & batches</a>' : '<a class="axh-btn axh-btn--ghost" href="#/statements">' + icon('doc', 15) + ' Statements</a>') + '</div>' +
			'<div class="axh-stats">' +
			stat('Earned to date', moneyC(t.earned), (function (n) { return n + (n === 1 ? ' closed deal' : ' closed deals'); })(deals.filter(function (x) { return !x.reversed; }).length), true) +
			stat(manager ? 'Paid out' : 'Paid to you', moneyC(t.paid), 'Cleared', false) +
			stat(manager ? 'Ready to pay' : 'Payable', moneyC(t.due), t.due > 0 ? (manager ? 'Client payments cleared' : 'Client payment cleared · check to follow') : 'Nothing outstanding', false) +
			stat('Still to come', moneyC(t.upcoming), 'Waiting on a client payment', false) +
			'</div>' +
			(t.overpaid > 0 ? '<div class="axh-alert" style="margin-bottom:16px"><b>' + moneyC(t.overpaid) + ' was paid on ' + (manager ? 'deals' : 'deals of yours') + ' that were later reduced, refunded or cancelled.</b> ' +
				(manager ? 'See Payouts for who owes what.' : 'Under section 3.6 of your agreement this is usually offset against your next commission. Questions? Talk to Adam.') + '</div>' : '');

		/* The schedule is shown even before a first deal, so a new rep can see
		   exactly how they get paid. */
		var schedule = '<div class="axh-card"><div class="axh-card__hd"><h3>How it is worked out</h3></div>' +
			'<table class="axh-table axh-table--fit"><thead><tr><th>Contract size</th><th class="num">Rate</th></tr></thead><tbody>' +
			(d.tiers || []).map(function (tier) {
				return '<tr><td>' + esc(tier.label) + '</td><td class="num">' + tier.rate + '%</td></tr>';
			}).join('') + '</tbody></table>' +
			'<p class="axh-hint" style="padding:14px 18px 4px">One rate applies to the whole contract, set by its size ' +
			'(e.g. a $12,000 contract earns 15% = $1,800). It is earned in two halves: the first when the client\u2019s deposit ' +
			'has fully cleared, the second when their final payment has, and each is paid by check after the funds clear. The rate is fixed when the deal closes, ' +
			'so a later change to the schedule never alters commission you have already earned. Figures here are a record of ' +
			'what the agreement provides for, not a statement of account.</p></div>' + payoutCard();

		if (!deals.length) {
			return html + '<div class="axh-card">' + empty('No closed business yet',
				'When the client signs, use <b>Request Closed Won</b> on the lead. Once AdaptExec confirms the contract value, the commission appears here.', '') +
				'<p style="text-align:center;margin:-6px 0 18px"><a class="axh-btn axh-btn--primary" href="#/leads">Open your pipeline</a></p></div>' +
				schedule;
		}

		html += '<div class="axh-card"><div class="axh-card__hd"><h3>Deal by deal</h3></div>' +
			'<table class="axh-table axh-table--earn axh-table--stack"><thead><tr><th>Client</th>' + (manager ? '<th>Rep</th>' : '') + '<th class="num">Contract</th>' +
			'<th class="num">Rate</th><th class="num">Commission</th><th>Deposit half</th><th>Final half</th>' +
			(manager ? '<th></th>' : '') + '</tr></thead><tbody>';

		deals.forEach(function (x) {
			function legCell(leg) {
				return '<td data-th="' + (leg.key === 'deposit' ? 'Deposit half' : 'Final half') + '"><div class="axh-leg"><b>' + moneyC(leg.amount) + '</b>' + payState(leg) +
					'<span>' + (leg.paid_at ? 'Paid ' + when(leg.paid_at) + (leg.method ? ' · ' + esc(leg.method) : '') + (leg.reference ? ' · ' + esc(leg.reference) : '')
						: leg.state === 'due' || leg.state === 'overdue' ? (manager ? 'Cleared ' + when(leg.trigger) : 'Client payment cleared · check to follow')
						: leg.state === 'cancelled' ? (x.reversed ? 'Deal reversed' : 'Already covered by the other half')
						: 'Awaiting the client') + '</span></div></td>';
			}
			html += '<tr><td data-th="Company"><div class="axh-co"><a href="#/lead/' + x.lead_id + '">' + esc(x.company) + '</a>' +
				'<span>' + (x.reversed ? 'Reversed (refunded or cancelled)' : 'Closed ' + when(x.closed_at)) + (x.overpaid > 0 ? ' · ' + moneyC(x.overpaid) + ' paid above what was earned' : '') + '</span></div></td>' +
				(manager ? '<td data-th="Rep">' + esc(x.rep_name || '') + '</td>' : '') +
				'<td data-th="Contract" class="num">' + moneyC(x.value) + '</td>' +
				'<td data-th="Rate" class="num">' + pctTxt(x.rate) + '</td>' +
				'<td data-th="Commission" class="num"><strong>' + moneyC(x.commission) + '</strong></td>' +
				legCell(x.legs[0]) + legCell(x.legs[1]);
			if (manager) {
				html += '<td class="axh-row-acts">' +
					'<button class="axh-btn axh-btn--ghost axh-btn--sm" data-pay="' + x.lead_id + '">Record</button></td>';
			}
			html += '</tr>';
		});

		html += '</tbody></table></div>';

		html += schedule;
		return html;
	}

	function paymentModal(leadId, deal) {
		var rows = [
			['deposit_at', 'Client deposit cleared', deal ? deal.legs[0].trigger : ''],
			['deposit_paid_at', 'We paid your deposit half', deal ? deal.legs[0].paid_at : ''],
			['final_at', 'Client final payment cleared', deal ? deal.legs[1].trigger : ''],
			['final_paid_at', 'We paid your final half', deal ? deal.legs[1].paid_at : '']
		];
		var m = modal('Record a payment',
			'<p class="axh-hint" style="margin-bottom:14px">Dates drive the schedule. Clearing a date undoes it.</p>' +
			rows.map(function (r) {
				return '<div class="axh-field"><label>' + r[1] + '</label>' +
					'<input type="date" data-pf="' + r[0] + '" value="' + (r[2] ? String(r[2]).slice(0, 10) : '') + '"></div>';
			}).join(''),
			'<button class="axh-btn axh-btn--quiet" data-close>Cancel</button>' +
			'<button class="axh-btn axh-btn--primary" data-save-pay="' + leadId + '">Save</button>');

		m.querySelector('[data-save-pay]').onclick = function () {
			var fields = [].slice.call(m.querySelectorAll('[data-pf]'));
			var chain = Promise.resolve();
			fields.forEach(function (f) {
				chain = chain.then(function () {
					var body = { field: f.getAttribute('data-pf'), when: f.value };
					return api('/leads/' + leadId + '/payment', { method: 'POST', body: body }).catch(function (er) {
						// Paying a rep whose onboarding is incomplete needs an explicit yes.
						if (er.code !== 'axh_onboarding_incomplete' || !window.confirm(er.message + '\n\nRecord this payment anyway?')) throw er;
						body.override = true;
						return api('/leads/' + leadId + '/payment', { method: 'POST', body: body });
					});
				});
			});
			chain.then(function () { closeModal(); toast('Recorded.', 'ok'); route(); })
				.catch(function (e) { toast(e.message, 'bad'); });
		};
	}

	/* ----------------------------------------------------------- brief */

	function briefAnswer(f, v) {
		if (f.type === 'stack') {
			var rows = Array.isArray(v) ? v.filter(function (r) { return r && (r.name || r.cost); }) : [];
			if (!rows.length) return '<em class="axh-none">Not answered</em>';
			return '<table class="axh-mini"><thead><tr><th>Software</th><th>What it does</th><th class="num">Cost</th></tr></thead><tbody>' +
				rows.map(function (r) {
					return '<tr><td>' + esc(r.name) + '</td><td>' + esc(r.use || '') + '</td>' +
						'<td class="num">' + esc(r.cost || '') + '</td></tr>';
				}).join('') + '</tbody></table>';
		}
		if (f.type === 'checklist') {
			var arr = Array.isArray(v) ? v : [];
			if (!arr.length) return '<em class="axh-none">Nothing selected</em>';
			return '<div class="axh-chips axh-chips--static">' + arr.map(function (o) {
				return '<span class="axh-chip is-on">' + esc(o) + '</span>';
			}).join('') + '</div>';
		}
		if (f.type === 'scale') {
			// Reads as the answer it is, not as a bare word in a paragraph.
			if (!String(v || '').trim()) return '<em class="axh-none">Not answered</em>';
			return '<span class="axh-chip is-on">' + esc(v) + '</span>';
		}
		if (f.type === 'rating') {
			var n = Number(v) || 0;
			if (!n) return '<em class="axh-none">Not answered</em>';
			var dots = '';
			for (var i = 1; i <= 5; i++) dots += '<i class="' + (i <= n ? 'on' : '') + '"></i>';
			return '<div class="axh-rate axh-rate--static">' + dots + '<b>' + n + ' of 5</b></div>';
		}
		var text = String(v === undefined || v === null ? '' : v).trim();
		if (!text) return '<em class="axh-none">Not answered</em>';
		return '<p class="axh-answer">' + esc(text).replace(/\n/g, '<br>') + '</p>';
	}

	function viewBrief(d) {
		var l = d.lead, disc = d.discovery || { payload: {}, modules: {} };
		var picked = disc.modules || {};
		var must = [], nice = [];
		(d.modules || []).forEach(function (cat) {
			cat.modules.forEach(function (m) {
				if (picked[m.name] === 'must') must.push([cat.name, m.name]);
				else if (picked[m.name] === 'nice') nice.push([cat.name, m.name]);
			});
		});

		var html = '<div class="axh-page-head axh-page-head--brief"><div>' +
			'<a class="axh-back" href="#/manage/briefs">' + icon('back', 15) + ' All briefs</a>' +
			'<h1 class="thunder">' + esc(l.company) + '</h1>' +
			'<p>' + [esc(d.rep), esc(l.industry || ''), esc([l.city, l.state].filter(Boolean).join(', '))]
				.filter(Boolean).join(' · ') + '</p></div>' +
			'<div class="axh-actions">' +
			'<button class="axh-btn axh-btn--ghost" data-print-brief>' + icon('printer', 15) + ' Print</button>' +
			'<button class="axh-btn axh-btn--ghost" data-bx-fromlead="' + l.id + '" data-then="proposal">' + icon('plus', 15) + ' Start proposal</button>' +
			(disc.status === 'reviewed' ? '' :
				'<button class="axh-btn axh-btn--primary" data-review="' + l.id + '">Mark reviewed</button>') +
			'</div></div>';

		html += '<div class="axh-stats">' +
			stat('Estimated value', Number(l.est_value) ? money(l.est_value) : '—', 'Rep estimate', true) +
			stat('Quote readiness', ((disc.readiness && disc.readiness.pct) || 0) + '%', 'Questionnaire ' + (disc.progress || 0) + '% complete', false) +
			stat('Must have', must.length, 'modules', false) +
			stat('Nice to have', nice.length, 'modules', false) +
			'</div>';

		if (disc.status === 'submitted' || disc.status === 'reviewed') {
			html += '<div class="axh-note"><b>Quote requested' + (disc.quote_by_name ? ' by ' + esc(disc.quote_by_name) : '') + ' · ' + when(disc.submitted_at) +
				(Number(disc.quote_ready) ? ' · ' + Number(disc.quote_ready) + '% ready' : '') + '</b><p>' + (disc.quote_note ? esc(disc.quote_note).replace(/\n/g, '<br>') : 'No note from the rep.') + '</p></div>';
		}
		var os = (disc.payload.onsite || {});
		if (os.interest === 'Yes' || os.interest === 'Maybe') {
			html += '<div class="axh-note axh-note--onsite"><b>' + icon('globe', 14) + ' On-site workflow study: ' + esc(os.interest) + '</b><p>' +
				[os.days, os.location, os.dates].filter(Boolean).map(esc).join(' · ') + (os.observe ? '<br>Observe: ' + esc(os.observe) : '') +
				'<br>Add the optional “On-site workflow study” add-on to the proposal and price it for this job (travel, lodging and time).</p></div>';
		}
		if (disc.review_note) {
			html += '<div class="axh-note"><b>Scoping note</b><p>' + esc(disc.review_note) + '</p></div>';
		}

		(d.schema || []).forEach(function (sec) {
			var answered = sec.fields.some(function (f) {
				if (f.type === 'modules') return must.length || nice.length;
				var v = (disc.payload[sec.key] || {})[f.key];
				return Array.isArray(v) ? v.length : String(v || '').trim();
			});
			html += '<div class="axh-card axh-brief' + (answered ? '' : ' is-empty') + '">' +
				'<div class="axh-card__hd"><h3>' + esc(String(sec.name).replace(/&amp;/g, '&')) + '</h3>' +
				(sec.key === 'rep' || sec.audience === 'internal'
					? '<span class="axh-badge axh-badge--gray">Internal — not shown to the client</span>' : '') +
				'</div><div class="axh-brief__b">';
			sec.fields.forEach(function (f) {
				if (f.type === 'modules') {
					html += '<div class="axh-qa"><h4>Module selection</h4>' + moduleSummary(must, nice) + '</div>';
					return;
				}
				var v = (disc.payload[sec.key] || {})[f.key], src = (disc.sources || {})[sec.key + '.' + f.key];
				html += '<div class="axh-qa"><h4>' + esc(String(f.label).replace(/&amp;/g, '&')) + (src ? ' <span class="axh-src' + (src.by === 'rep' ? ' axh-src--rep' : '') + '">' + (src.by === 'rep' ? 'Rep-entered' : 'From the client') + '</span>' : '') + '</h4>' + briefAnswer(f, v) + '</div>';
			});
			html += '</div></div>';
		});
		return html;
	}

	function moduleSummary(must, nice) {
		function group(list) {
			var by = {};
			list.forEach(function (r) { (by[r[0]] = by[r[0]] || []).push(r[1]); });
			return Object.keys(by).map(function (cat) {
				return '<div class="axh-modgrp"><h5>' + esc(cat) + '</h5><ul>' +
					by[cat].map(function (n) { return '<li>' + esc(n) + '</li>'; }).join('') + '</ul></div>';
			}).join('');
		}
		if (!must.length && !nice.length) return '<em class="axh-none">No modules marked yet</em>';
		return '<div class="axh-mods">' +
			'<div><div class="axh-modhd axh-modhd--must">Must have · ' + must.length + '</div>' +
				(must.length ? group(must) : '<em class="axh-none">None</em>') + '</div>' +
			'<div><div class="axh-modhd">Nice to have · ' + nice.length + '</div>' +
				(nice.length ? group(nice) : '<em class="axh-none">None</em>') + '</div></div>';
	}

	/* ------------------------------------------------------- style finder */

	var SF_SRC = { rep: 'in the Hub', client: 'on their style link', discovery: 'in the questionnaire', form: 'on a lead form' };
	function hasPicker() { return !!window.AXStylePicker; }

	/* The "Website style" card on a lead. */
	function styleCard(d) {
		var l = d.lead, v = d.style;
		var body;
		if (!hasPicker()) {
			body = '<p class="axh-hint">The style finder could not load. Refresh the page.</p>';
		} else if (v && ((v.shortlist && v.shortlist.length) || (v.top && v.top.length) || v.notes)) {
			body = window.AXStylePicker.thumbs(v, { live: true }) +
				(v.notes ? '<p class="axh-hint" style="margin-top:10px;white-space:pre-wrap"><b>Notes:</b> ' + esc(v.notes) + '</p>' : '') +
				'<p class="axh-hint" style="margin-top:8px">Picked by ' + esc(v.by || 'someone') + ' ' + (SF_SRC[v.source] || '') + (v.at ? ' · ' + when(v.at) : '') +
				(v.shortlist && v.shortlist.length && v.top && v.top.length ? ' · top quiz match: ' + esc(window.AXStylePicker.names([v.top[0].slug])[0] || '') + ' (' + v.top[0].pct + '%)' : '') + '</p>';
		} else {
			body = '<p style="font-size:13.5px;color:var(--gray)">No style picked yet. Run the Style Finder with ' + esc(l.contact_name || 'the client') +
				', or send them their own link and their picks land here.</p>';
		}
		return '<div class="axh-card" style="margin-top:16px" id="axh-stylecard"><div class="axh-card__h"><h3>Website style</h3>' +
			(v ? '<button data-style-clear="' + l.id + '">Clear</button>' : '') + '</div>' + body +
			'<div class="axh-sf-acts"><button class="axh-btn axh-btn--primary axh-btn--sm" data-style-open="' + l.id + '">' + icon('spark', 14) + ' ' + (v ? 'Open / edit picks' : 'Run the Style Finder') + '</button>' +
			'<button class="axh-btn axh-btn--ghost axh-btn--sm" data-style-link="' + l.id + '">' + icon('link', 14) + ' Client style link</button></div>' +
			'<div data-style-linkbox>' + (d.style_url ? styleLinkBox(d.style_url) : '') + '</div></div>';
	}
	function styleLinkBox(url) {
		return '<div class="axh-field" style="margin:12px 0 0"><label>Client style link</label><div class="axh-copyrow"><input type="text" readonly value="' + esc(url) + '" data-linkval>' +
			'<button class="axh-btn axh-btn--ghost axh-btn--sm" data-copy-link>' + icon('copy', 14) + ' Copy</button></div>' +
			'<div class="axh-field__help">No login needed. When they press <b>Send my picks</b>, they land on this lead and you are notified.</div></div>';
	}

	/* The Style Finder page: run it with a client, or send a link. */
	function viewStyles() {
		var open = (S.leads || []).filter(function (l) { return !l.archived_at; });
		var opts = '<option value="">No lead — just browsing</option>' + open.map(function (l) {
			return '<option value="' + l.id + '">' + esc(l.company) + (l.contact_name ? ' · ' + esc(l.contact_name) : '') + '</option>';
		}).join('');
		var n = hasPicker() ? window.AXStylePicker.data.styles.length : 0;
		return '<div class="axh-page-head"><div><h1 class="thunder">Style finder</h1>' +
			'<p>' + n + ' website styles with live mini-mockups and a quick quiz. Run it with a client, or send them their own link — their picks land on the lead.</p></div>' +
			'<button class="axh-btn axh-btn--primary" data-style-open="0">' + icon('play', 15) + ' Present full screen</button></div>' +
			'<div class="axh-grid axh-grid--2" style="margin-bottom:16px">' +
			'<div class="axh-card"><div class="axh-card__h"><h3>Run it with a client</h3></div>' +
			'<p style="font-size:13.5px;margin-bottom:12px">Full screen, made for a laptop or tablet across the table. Take the quiz together, shortlist what they react to, jot notes, and save it to their lead.</p>' +
			'<div class="axh-field"><label for="axh-sf-lead">Save picks to</label><select id="axh-sf-lead" data-sf-lead>' + opts + '</select></div>' +
			'<button class="axh-btn axh-btn--primary" data-style-open="sel">' + icon('play', 15) + ' Start full screen</button></div>' +
			'<div class="axh-card"><div class="axh-card__h"><h3>Send a client their own link</h3></div>' +
			'<p style="font-size:13.5px;margin-bottom:12px">They do the quiz and shortlist on their own time, no login. You get a notification when they send their picks.</p>' +
			'<div class="axh-field"><label for="axh-sf-linklead">For</label><select id="axh-sf-linklead" data-sf-linklead>' + opts.replace('No lead — just browsing', 'Choose a lead…') + '</select></div>' +
			'<button class="axh-btn axh-btn--ghost" data-style-link="sel">' + icon('link', 15) + ' Get the link</button><div data-style-linkbox></div></div></div>' +
			'<div class="axh-card"><div class="axh-card__h"><h3>Browse the styles</h3><a href="https://adaptexec.com/website-styles/" target="_blank" rel="noopener">Public style guide</a></div><div id="axh-sf-inline"></div></div>';
	}
	function mountInlineStyles() {
		var box = el('axh-sf-inline');
		if (box && hasPicker()) window.AXStylePicker.mount(box, { start: 'browse' });
	}

	/* Full-screen, client-facing Style Finder. */
	function openStyleFinder(leadId) {
		if (!hasPicker()) { toast('The style finder could not load. Refresh the page.', 'bad'); return; }
		var leadsP = S.leads && S.leads.length ? Promise.resolve() : refresh();
		var startP = leadId ? api('/leads/' + leadId) : Promise.resolve(null);
		Promise.all([leadsP, startP]).then(function (r) {
			var d = r[1], lead = d ? d.lead : null;
			var ov = document.createElement('div');
			ov.className = 'axh-sf';
			ov.setAttribute('role', 'dialog');
			ov.setAttribute('aria-modal', 'true');
			ov.setAttribute('aria-label', 'Style finder');
			var opts = '<option value="">Not saving — choose a lead…</option>' + (S.leads || []).filter(function (l) { return !l.archived_at || (lead && String(l.id) === String(lead.id)); }).map(function (l) {
				return '<option value="' + l.id + '"' + (lead && String(l.id) === String(lead.id) ? ' selected' : '') + '>' + esc(l.company) + '</option>';
			}).join('');
			if (lead && !(S.leads || []).some(function (l) { return String(l.id) === String(lead.id); })) opts += '<option value="' + lead.id + '" selected>' + esc(lead.company) + '</option>';
			ov.innerHTML = '<div class="axh-sf__bar"><span class="axh-sf__brand">ADAPT<b>EXEC</b> <small>Website styles</small></span>' +
				'<label class="axh-sf__lead"><span>Saving to</span><select data-sf-to>' + opts + '</select></label>' +
				'<button class="axh-sf__x" data-sf-close aria-label="Close the style finder">' + icon('x', 20) + '</button></div>' +
				'<div class="axh-sf__body"><div class="axh-sf__intro"><h2>' + (lead ? 'Let’s find the look for ' + esc(lead.company) : 'Let’s find your website’s look') + '</h2>' +
				'<p>Answer a few quick questions, or browse every style. Tap the heart on anything you like.</p></div><div data-sf-picker></div></div>';
			document.body.appendChild(ov);
			document.documentElement.classList.add('axh-sf-open');
			var to = ov.querySelector('[data-sf-to]');
			var picker = window.AXStylePicker.mount(ov.querySelector('[data-sf-picker]'), {
				present: true,
				notes: true,
				value: d && d.style ? d.style : {},
				saveLabel: lead ? 'Save to ' + lead.company : 'Save to lead',
				onSave: function (v) {
					if (!to.value) { to.focus(); return Promise.reject(new Error('Choose the lead to save to (top of the screen).')); }
					return api('/leads/' + to.value + '/styles', { method: 'POST', body: { picks: v } }).then(function () {
						S.styleSaved = true;
						return 'Saved to ' + to.options[to.selectedIndex].text + '.';
					});
				}
			});
			function close() {
				if (picker.isDirty() && !confirm('Close without saving these picks?')) return;
				document.removeEventListener('keydown', onKey);
				ov.remove();
				document.documentElement.classList.remove('axh-sf-open');
				if (S.styleSaved) { S.styleSaved = false; route(); }
			}
			function onKey(e) { if (e.key === 'Escape' && el('axh-modal').hidden) close(); }
			document.addEventListener('keydown', onKey);
			ov.querySelector('[data-sf-close]').onclick = close;
			to.onchange = function () {
				var b = ov.querySelector('.axsp-save');
				if (b && to.value) b.textContent = 'Save to ' + to.options[to.selectedIndex].text;
			};
			try { ov.querySelector('[data-sf-close]').focus(); } catch (e) {}
		}).catch(function (e) { toast(e.message, 'bad'); });
	}

	function styleLink(leadId, box) {
		if (!leadId) { toast('Choose a lead first.', 'bad'); return; }
		api('/leads/' + leadId + '/styles/link', { method: 'POST' }).then(function (r) {
			if (box) box.innerHTML = styleLinkBox(r.url);
			toast('Link ready. Copy it and send it to the client.', 'ok');
		}).catch(function (e) { toast(e.message, 'bad'); });
	}

	/* ====================================================== onboarding (1.6) */

	/* A new rep's three steps. The server decides what is done; this is the
	   copy that explains each step and why AdaptExec needs it. */
	var ONB_STEPS = [
		{ key: 'agreement', title: 'Sign your contractor agreement', short: 'Agreement', href: '#/onboarding/agreement', icon: 'pen', time: '15–20 min',
			why: 'Your Independent Contractor Agreement sets out how you work with AdaptExec and exactly how commission is earned and paid. Read it, then sign it right here.' },
		{ key: 'w9', title: 'Upload your W-9', short: 'W-9', href: '#/onboarding/w9', icon: 'file', time: '3 min',
			why: 'The IRS requires a W-9 before AdaptExec can pay you, so your 1099-NEC is right each January. Download the blank form, fill it in, upload it. It is encrypted the moment it arrives.' },
		{ key: 'payee', title: 'Set up payment', short: 'Payment', href: '#/onboarding/payment', icon: 'money', time: '2 min',
			why: 'Commission is paid by check. Tell us exactly who to make checks out to and where to mail them.' }
	];
	var US_STATES = ['AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'DC', 'FL', 'GA', 'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY', 'PR', 'GU', 'VI', 'AS', 'MP', 'AA', 'AE', 'AP'];

	/* [done, label, badge tone] for one step. */
	function onbStep(st, key) {
		var s = ((st || {}).steps || {})[key] || { state: 'todo' };
		var map = {
			agreement: { todo: [false, 'Not signed', 'gray'], signed: [true, 'Signed', 'green'], resign: [false, 'Re-sign needed', 'amber'] },
			w9: { todo: [false, 'Not uploaded', 'gray'], on_file: [true, 'On file', 'green'], rejected: [false, 'New one needed', 'red'] },
			payee: { todo: [false, 'Not set up', 'gray'], set: [true, 'Set up', 'green'], verify: [true, 'Being verified', 'amber'] }
		}[key][s.state] || [false, s.state, 'gray'];
		return { done: map[0], label: map[1], tone: map[2], raw: s };
	}
	function onbNext(st) {
		for (var i = 0; i < ONB_STEPS.length; i++) { if (!onbStep(st, ONB_STEPS[i].key).done) return ONB_STEPS[i]; }
		return null;
	}
	function onbPct(st) { return Math.round(((st && st.done) || 0) / 3 * 100); }
	function onbProgress(st, dark) {
		return '<div class="axh-onb-prog' + (dark ? ' axh-onb-prog--dark' : '') + '" role="progressbar" aria-valuemin="0" aria-valuemax="3" aria-valuenow="' + ((st && st.done) || 0) + '" aria-label="Onboarding progress">' +
			ONB_STEPS.map(function (x) { var o = onbStep(st, x.key); return '<i class="' + (o.done ? 'is-done' : '') + '"></i>'; }).join('') + '</div>';
	}

	/* The dashboard card, until onboarding is complete. */
	function onbCard(st) {
		var next = onbNext(st);
		return '<div class="axh-onb-hero">' +
			'<div class="axh-onb-hero__l"><span class="axh-onb-eyebrow">Welcome to AdaptExec</span>' +
			'<h2 class="thunder">Finish your onboarding</h2>' +
			'<p>Three steps, about 20–25 minutes (the agreement is a 15–20 minute read). Commission checks can go out as soon as they are done.</p>' +
			onbProgress(st, true) + '<span class="axh-onb-hero__n">' + (st.done || 0) + ' of 3 done</span></div>' +
			'<ol class="axh-onb-mini">' + ONB_STEPS.map(function (x, i) {
				var o = onbStep(st, x.key);
				return '<li><a href="' + x.href + '" class="' + (o.done ? 'is-done' : '') + '"><span class="axh-onb-mini__n">' + (o.done ? icon('check', 13) : (i + 1)) + '</span>' +
					'<span class="axh-onb-mini__t">' + x.title + '</span><span class="axh-badge axh-badge--' + o.tone + '">' + o.label + '</span></a></li>';
			}).join('') + '</ol>' +
			(next ? '<a class="axh-btn axh-btn--primary axh-btn--pill" href="' + next.href + '">' + (st.done ? 'Continue: ' : 'Start: ') + next.title + ' ' + icon('right', 15) + '</a>' : '') +
			'</div>';
	}
	function onbDoneStrip() {
		var hide = false;
		try { hide = localStorage.getItem('axh_onb_done_hide') === '1'; } catch (e) {}
		if (hide) return '';
		return '<div class="axh-onb-strip"><span class="axh-onb-strip__i">' + icon('check', 15) + '</span>' +
			'<span><b>Onboarding complete.</b> <a href="#/onboarding/agreement">Your signed agreement</a> · <a href="#/onboarding/payment">Payment details</a> · <a href="#/onboarding/w9">W-9</a></span>' +
			'<button class="axh-x" data-onb-strip-hide aria-label="Hide">' + icon('x', 15) + '</button></div>';
	}

	/* ------------------------------------------------- the overview page */

	function viewOnboarding(d) {
		var st = d.status, next = onbNext(st);
		var html = '<div class="axh-page-head"><div><h1 class="thunder">Onboarding</h1>' +
			'<p>' + (st.complete ? 'You are all set. Everything below stays here for whenever you need it.' : 'Three steps to get paid, about 20–25 minutes in all. The agreement is a 15–20 minute read.') + '</p></div></div>';

		html += '<div class="axh-onb-top' + (st.complete ? ' is-complete' : '') + '">' +
			'<div class="axh-onb-top__l">' + (st.complete
				? '<span class="axh-onb-done-badge">' + icon('check', 26) + '</span><div><h2 class="thunder">Onboarding complete</h2><p>Thanks, ' + esc(S.boot.user.name.split(' ')[0]) + '. Your agreement, W-9 and payment details are on file' + (st.steps.payee.state === 'verify' ? ' (your new address is being verified)' : '') + '.</p></div>'
				: '<div><span class="axh-onb-eyebrow">' + (st.done || 0) + ' of 3 done</span><h2 class="thunder">' + (next ? esc(next.title) : 'Almost there') + '</h2><p>' + (next ? esc(next.why) : '') + '</p></div>') +
			'</div>' + onbProgress(st, true) +
			(next ? '<a class="axh-btn axh-btn--primary axh-btn--pill" href="' + next.href + '">' + (st.done ? 'Continue' : 'Start') + ' ' + icon('right', 15) + '</a>' : '') + '</div>';

		html += '<div class="axh-onb-grid">' + ONB_STEPS.map(function (x, i) {
			var o = onbStep(st, x.key), sub = '';
			if (x.key === 'agreement' && o.raw.state !== 'todo') sub = (o.raw.state === 'resign' ? 'You signed version ' + esc(o.raw.version) + ' — ' + (o.raw.reason === 'requested' ? 'AdaptExec asked you to sign again.' : 'there is an updated version to sign.') : 'Signed ' + when(o.raw.at) + ' · version ' + esc(o.raw.version) + (o.raw.countersigned ? ' · countersigned' : ''));
			if (x.key === 'w9' && o.raw.state === 'on_file') sub = 'Uploaded ' + when(o.raw.at);
			if (x.key === 'w9' && o.raw.state === 'rejected') sub = 'AdaptExec needs a new one' + (o.raw.note ? ': ' + esc(o.raw.note) : '.');
			if (x.key === 'payee' && o.raw.state !== 'todo') sub = (d.payee ? 'Checks to ' + esc(d.payee.payable_to) + ', ' + esc(d.payee.city) + ', ' + esc(d.payee.state) : '') + (o.raw.state === 'verify' ? ' · new address being verified' : '');
			var cta = o.done ? (x.key === 'agreement' ? 'View agreement' : x.key === 'w9' ? 'View or replace' : 'Update') : (o.raw.state === 'todo' ? 'Start' : 'Fix this');
			return '<a class="axh-onb-step' + (o.done ? ' is-done' : '') + '" href="' + x.href + '">' +
				'<div class="axh-onb-step__top"><span class="axh-onb-step__n">' + (o.done ? icon('check', 16) : (i + 1)) + '</span>' +
				'<span class="axh-badge axh-badge--' + o.tone + '">' + o.label + '</span></div>' +
				'<span class="axh-onb-step__i">' + icon(x.icon, 22) + '</span>' +
				'<h3>' + x.title + '</h3><p>' + x.why + '</p>' +
				(sub ? '<p class="axh-onb-step__sub">' + sub + '</p>' : '<p class="axh-onb-step__sub">' + icon('clock', 13) + ' About ' + x.time + '</p>') +
				'<span class="axh-onb-step__cta">' + cta + ' ' + icon('right', 14) + '</span></a>';
		}).join('') + '</div>';

		html += '<div class="axh-grid axh-grid--2" style="margin-top:18px">' +
			'<div class="axh-card"><div class="axh-card__h"><h3>How your information is protected</h3></div>' +
			'<ul class="axh-onb-list">' +
			'<li>' + icon('lock', 15) + '<span><b>Encrypted at rest.</b> Your W-9, signature and signed agreement are encrypted the moment they are saved, in a private folder the web cannot reach.</span></li>' +
			'<li>' + icon('shield', 15) + '<span><b>Only you and AdaptExec.</b> Other reps can never see your paperwork or address. Every view and download is logged.</span></li>' +
			'<li>' + icon('mail', 15) + '<span><b>Never by email.</b> Do not email or text your W-9 to anyone — upload it here. AdaptExec will never ask for your SSN by email.</span></li>' +
			'</ul></div>' +
			'<div class="axh-card"><div class="axh-card__h"><h3>Questions?</h3></div>' +
			'<p style="font-size:14px;line-height:1.6;margin:0 0 10px">Call or text ' + esc((S.boot.contact || {}).name || 'Adam') + ' at <a href="tel:' + esc(String((S.boot.contact || {}).phone || '').replace(/[^0-9]/g, '')) + '">' + esc((S.boot.contact || {}).phone || '') + '</a> or email <a href="mailto:' + esc((S.boot.contact || {}).email || '') + '">' + esc((S.boot.contact || {}).email || '') + '</a>.</p>' +
			'<p class="axh-hint" style="margin:0">Once you are done, read <a href="#/earnings">How you get paid</a> under Earnings.</p></div></div>';
		return html;
	}

	/* --------------------------------------------- the agreement + e-sign */

	/* The agreement's light markup ("## Heading", "3.4 Lead-in. text") as HTML. */
	function agreementHtml(text) {
		var out = '', n = 0, toc = [];
		String(text || '').trim().split(/\n\s*\n/).forEach(function (block) {
			block.split('\n').forEach(function (line) {
				line = line.trim();
				if (!line) return;
				if (line.indexOf('## ') === 0) {
					n++;
					toc.push({ id: 'agr-s' + n, t: line.slice(3) });
					out += '<h2 id="agr-s' + n + '" data-agr-sec="' + esc(line.slice(3)) + '">' + esc(line.slice(3)) + '</h2>';
					return;
				}
				if (line.indexOf('- ') === 0) { out += '<ul><li>' + esc(line.slice(2)) + '</li></ul>'; return; }
				var m = line.match(/^(\d+\.\d+)\s+([^.]{2,90}\.)\s+([\s\S]*)$/);
				out += m ? '<p><b>' + esc(m[1] + ' ' + m[2]) + '</b> ' + esc(m[3]) + '</p>' : '<p>' + esc(line) + '</p>';
			});
		});
		return { html: out, toc: toc };
	}

	function signedPanel(d) {
		var s = d.signed, st = d.status.steps.agreement;
		return '<div class="axh-signed">' +
			'<div class="axh-signed__i">' + icon('check', 26) + '</div>' +
			'<div class="axh-signed__b"><h3 class="thunder">Signed</h3>' +
			'<p>You signed version ' + esc(s.version) + ' as <b>' + esc(s.legal_name) + '</b>' + (s.business_name ? ' (' + esc(s.business_name) + ')' : '') + ' on ' + esc(fmtDate(s.signed_at, true)) + '.' +
			(st.countersigned ? ' Countersigned by ' + esc(s.countersign_name) + ' ' + when(s.countersigned_at) + '.' : ' AdaptExec’s countersignature is optional; the agreement is in effect now.') + '</p>' +
			'<div class="axh-actions"><button class="axh-btn axh-btn--dark axh-btn--pill" data-onb-copy="' + s.id + '">' + icon('eye', 15) + ' View signed copy</button>' +
			'<button class="axh-btn axh-btn--ghost axh-btn--pill" data-onb-print="' + s.id + '">' + icon('printer', 15) + ' Print / Save as PDF</button>' +
			'<button class="axh-btn axh-btn--ghost axh-btn--pill" data-onb-dl="' + s.id + '">' + icon('download', 15) + ' Download</button></div>' +
			'<p class="axh-hint" style="margin-top:10px">Document AXH-ICA-' + ('0000' + s.id).slice(-5) + ' · text SHA-256 <code>' + esc(String(s.text_hash).slice(0, 16)) + '…</code></p></div></div>';
	}

	function viewAgreement(d, a) {
		var st = d.status.steps.agreement, signedNow = st.state === 'signed';
		var doc = agreementHtml(a.text);
		var u = S.boot.user;
		var html = '<p class="axh-crumb"><a href="#/onboarding">' + icon('back', 14) + ' Onboarding</a></p>' +
			'<div class="axh-page-head"><div><h1 class="thunder">Contractor agreement</h1>' +
			'<p>' + esc(a.title) + ' · Version ' + esc(a.version) + ' · Effective ' + esc(fmtDate(a.effective)) + '</p></div>' +
			'<div class="axh-actions"><span class="axh-badge axh-badge--' + onbStep(d.status, 'agreement').tone + '">' + onbStep(d.status, 'agreement').label + '</span></div></div>';

		if (st.state === 'resign') {
			html += '<div class="axh-note" style="margin-bottom:16px"><b>' + (st.reason === 'requested' ? 'Please sign again' : 'Updated agreement') + '</b><p>' +
				(st.reason === 'requested' ? 'AdaptExec asked you to review and sign the agreement again.' : st.reason === 'details_changed' ? 'AdaptExec updated its company details in the agreement. Please review and sign the current text.' : 'There is a new version of the agreement (' + esc(a.version) + '). Please review it and sign below.') +
				' Your earlier signed copy stays on record.' + (d.signed ? ' <button class="axh-linkbtn" data-onb-copy="' + d.signed.id + '">View your earlier copy</button>' : '') + '</p></div>';
		}
		if (signedNow) html += signedPanel(d);

		html += '<div class="axh-agr">' +
			'<aside class="axh-agr__toc"><div class="axh-agr__tochd">Contents</div><ol>' +
			doc.toc.map(function (t) { return '<li><a href="#' + t.id + '" data-agr-jump="' + t.id + '">' + esc(t.t.replace(/^\d+\.\s*/, '')) + '</a></li>'; }).join('') +
			'</ol></aside>' +
			'<div class="axh-agr__main">' +
			'<div class="axh-agr__bar" data-agr-bar><div class="axh-agr__bart"><span data-agr-cur>' + esc(doc.toc.length ? doc.toc[0].t : '') + '</span><b data-agr-pct>0%</b></div><div class="axh-agr__track"><i data-agr-fill></i></div></div>' +
			'<article class="axh-agr__doc" data-agr-doc>' +
			'<header class="axh-agr__head"><div class="axh-agr__mark">ADAPT<span>EXEC</span></div><h2>' + esc(a.title) + '</h2>' +
			'<p>Version ' + esc(a.version) + ' · Effective ' + esc(fmtDate(a.effective)) + ' · Between ' + esc(a.company) + ' and you</p></header>' +
			doc.html +
			'<div class="axh-agr__sched"><h2>Commission Schedule (shown in the Hub today)</h2><table class="axh-table axh-table--fit"><tbody>' +
			(a.tiers || []).map(function (t) { return '<tr><td>' + esc(t.label) + '</td><td class="num"><b>' + esc(pctTxt(t.rate)) + '</b></td></tr>'; }).join('') +
			'</tbody></table>' + (Number(S.boot.rateOverride) > 0 ? '<p class="axh-hint">AdaptExec has set a ' + pctTxt(S.boot.rateOverride) + ' rate for you, which applies instead (section 3.1).</p>' : '') + '</div>' +
			'<div class="axh-agr__end" data-agr-end>' + icon('check', 15) + ' End of agreement</div>' +
			'</article>';

		if (!signedNow) {
			html += '<section class="axh-sign" id="axh-sign">' +
				'<div class="axh-sign__hd"><span class="axh-onb-eyebrow">Step 1 of 3</span><h3 class="thunder">Sign the agreement</h3>' +
				'<p>Your signature is legally binding, just like signing on paper. You will get a copy by email, and it stays here in the Hub.</p></div>' +
				'<div class="axh-sign__lock" data-sign-lock>' + icon('lock', 15) + '<span>Scroll to the end of the agreement to unlock signing — or <label class="axh-inline-check"><input type="checkbox" data-read-confirm> I have read the entire agreement</label></span></div>' +
				'<fieldset class="axh-sign__f" data-sign-fields disabled>' +
				'<div class="axh-form-grid">' +
				'<div class="axh-field"><label for="axh-sg-n">Full legal name *</label><input id="axh-sg-n" type="text" autocomplete="name" data-sign-name value="' + esc(u.name.indexOf(' ') > 0 ? u.name : '') + '" placeholder="First and last name"><div class="axh-field__help" data-sign-namehelp>As it appears on your W-9 and ID.</div></div>' +
				'<div class="axh-field"><label for="axh-sg-b">Business name <span class="axh-hint">(optional)</span></label><input id="axh-sg-b" type="text" autocomplete="organization" data-sign-biz placeholder="Only if you work through an LLC or company"></div>' +
				'</div>' +
				'<div class="axh-field"><label>Your signature *</label>' +
				'<div class="axh-seg axh-sigtabs" role="tablist"><button type="button" class="is-on" data-sig-tab="draw" role="tab" aria-selected="true">' + icon('pen', 14) + ' Draw</button><button type="button" data-sig-tab="type" role="tab" aria-selected="false">Aa Type</button></div>' +
				'<div class="axh-sigpad" data-sig-draw><canvas data-sigpad aria-label="Signature pad: draw your signature"></canvas><span class="axh-sigpad__x">×</span><span class="axh-sigpad__line"></span>' +
				'<span class="axh-sigpad__hint" data-sig-hint>Sign here with your finger, stylus or mouse</span>' +
				'<button type="button" class="axh-btn axh-btn--quiet axh-btn--sm axh-sigpad__clear" data-sig-clear>Clear</button></div>' +
				'<div class="axh-sigtype" data-sig-type hidden><input type="text" data-sig-typed placeholder="Type your full name" autocomplete="name" aria-label="Type your signature">' +
				'<div class="axh-sigtype__pv"><span class="axh-sigpad__x">×</span><span data-sig-preview>Your signature</span></div></div>' +
				'</div>' +
				'<label class="axh-consent"><input type="checkbox" data-sign-consent><span><b>I agree to sign electronically.</b> I agree to use electronic records and signatures. I understand that my electronic signature, drawn or typed, is legally binding just like a handwritten signature, and that I can download or print this agreement and ask for a paper copy at any time.</span></label>' +
				'<div class="axh-sign__go"><button type="button" class="axh-btn axh-btn--primary axh-btn--pill axh-btn--lg" data-sign-submit>' + icon('pen', 16) + ' Sign agreement</button>' +
				'<p class="axh-hint">You are signing version ' + esc(a.version) + ' (SHA-256 <code>' + esc(a.hash.slice(0, 12)) + '…</code>). We record the date and time, your IP address and your browser.</p></div>' +
				'</fieldset></section>';
		}
		html += '</div></div>';
		return html;
	}

	function fmtDate(d, withTime) {
		if (!d) return '';
		// A bare date is a calendar day, not UTC midnight (which shows as the day before in the US).
		var t = srvDate(d);
		if (isNaN(t)) return String(d);
		var o = { month: 'long', day: 'numeric', year: 'numeric' };
		if (withTime && !isDay(d)) { o.hour = 'numeric'; o.minute = '2-digit'; }
		return t.toLocaleString('en-US', o);
	}

	/* Name check, same rule as the server: share a word with the account name. */
	function nameTokens(s) {
		return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z\s'-]/g, ' ').split(/[\s'-]+/).filter(function (w) { return w.length >= 2; });
	}
	function nameMatches(legal) {
		var have = nameTokens(S.boot.user.name);
		if (have.length < 2) return true;
		return nameTokens(legal).some(function (t) { return have.indexOf(t) > -1; });
	}

	/* A signature pad: pointer events cover mouse, pen and touch. Points are
	   kept as fractions of the pad so a resize (rotating a phone) keeps them. */
	function sigPad(cv, onChange) {
		var strokes = [], cur = null, ctx = cv.getContext('2d'), w = 0, h = 0, locked = true;
		function size() {
			var r = cv.getBoundingClientRect(), dpr = Math.max(1, window.devicePixelRatio || 1);
			w = r.width; h = r.height;
			cv.width = Math.round(w * dpr); cv.height = Math.round(h * dpr);
			ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
			draw(ctx, w, h, 2.4);
		}
		function draw(c, W, H, lw) {
			c.clearRect(0, 0, W, H);
			c.lineCap = 'round'; c.lineJoin = 'round'; c.strokeStyle = '#0b0b0b'; c.lineWidth = lw;
			strokes.forEach(function (s) {
				if (!s.length) return;
				c.beginPath();
				c.moveTo(s[0][0] * W, s[0][1] * H);
				if (s.length === 1) { c.lineTo(s[0][0] * W + .1, s[0][1] * H + .1); }
				for (var i = 1; i < s.length - 1; i++) {
					var mx = (s[i][0] + s[i + 1][0]) / 2 * W, my = (s[i][1] + s[i + 1][1]) / 2 * H;
					c.quadraticCurveTo(s[i][0] * W, s[i][1] * H, mx, my);
				}
				if (s.length > 1) c.lineTo(s[s.length - 1][0] * W, s[s.length - 1][1] * H);
				c.stroke();
			});
		}
		function pt(e) { var r = cv.getBoundingClientRect(); return [Math.min(1, Math.max(0, (e.clientX - r.left) / r.width)), Math.min(1, Math.max(0, (e.clientY - r.top) / r.height))]; }
		cv.addEventListener('pointerdown', function (e) {
			if (locked) return;
			e.preventDefault();
			try { cv.setPointerCapture(e.pointerId); } catch (er) {}
			cur = [pt(e)]; strokes.push(cur); draw(ctx, w, h, 2.4);
		});
		cv.addEventListener('pointermove', function (e) {
			if (!cur) return;
			e.preventDefault();
			var evs = e.getCoalescedEvents ? e.getCoalescedEvents() : [e];
			(evs.length ? evs : [e]).forEach(function (x) { cur.push(pt(x)); });
			draw(ctx, w, h, 2.4);
		});
		function end() { if (cur) { cur = null; onChange && onChange(); } }
		cv.addEventListener('pointerup', end);
		cv.addEventListener('pointercancel', end);
		cv.addEventListener('pointerleave', function (e) { if (e.pointerType === 'mouse') end(); });
		size();
		return {
			resize: size,
			lock: function (on) { locked = on; },
			clear: function () { strokes = []; draw(ctx, w, h, 2.4); onChange && onChange(); },
			length: function () {
				var L = 0;
				strokes.forEach(function (s) { for (var i = 1; i < s.length; i++) { L += Math.hypot((s[i][0] - s[i - 1][0]) * w, (s[i][1] - s[i - 1][1]) * h); } });
				return L;
			},
			png: function () {
				var W = 900, H = Math.round(900 * (h / (w || 1))) || 240, c = document.createElement('canvas');
				c.width = W; c.height = Math.max(160, Math.min(600, H));
				draw(c.getContext('2d'), W, c.height, 2.4 * W / (w || 1));
				return c.toDataURL('image/png');
			}
		};
	}

	function loadSigFont() {
		if (document.getElementById('axh-sigfont')) return;
		var l = document.createElement('link');
		l.id = 'axh-sigfont'; l.rel = 'stylesheet';
		l.href = 'https://fonts.googleapis.com/css2?family=Allura&display=swap';
		document.head.appendChild(l);
	}
	var SIG_FONT = '"Allura", "Segoe Script", "Snell Roundhand", "Brush Script MT", cursive';
	function typedPng(name) {
		var go = function () {
			var c = document.createElement('canvas'); c.width = 900; c.height = 260;
			var x = c.getContext('2d'), size = 120;
			x.font = size + 'px ' + SIG_FONT;
			while (x.measureText(name).width > 820 && size > 40) { size -= 4; x.font = size + 'px ' + SIG_FONT; }
			x.fillStyle = '#0b0b0b'; x.textBaseline = 'alphabetic';
			x.fillText(name, 40, 180);
			return c.toDataURL('image/png');
		};
		if (!document.fonts || !document.fonts.load) return Promise.resolve(go());
		return Promise.race([document.fonts.load('120px "Allura"'), new Promise(function (r) { setTimeout(r, 1800); })]).then(go, go);
	}

	/* After the agreement renders: reading progress, unlocking, signing. */
	function bindAgreement() {
		var docEl = document.querySelector('[data-agr-doc]');
		if (!docEl) return;
		var fill = document.querySelector('[data-agr-fill]'), pctEl = document.querySelector('[data-agr-pct]'), curEl = document.querySelector('[data-agr-cur]');
		var heads = [].slice.call(docEl.querySelectorAll('h2[data-agr-sec]'));
		var tocLinks = [].slice.call(document.querySelectorAll('[data-agr-jump]'));
		var form = document.querySelector('[data-sign-fields]');
		S.agrRead = S.agrRead && S.agrRead.hash === S.agr.hash ? S.agrRead : { hash: S.agr.hash, how: '' };

		function onScroll() {
			if (!document.body.contains(docEl)) return;
			var r = docEl.getBoundingClientRect(), vh = window.innerHeight;
			var p = Math.max(0, Math.min(1, (vh - r.top - 80) / Math.max(1, r.height)));
			fill.style.width = Math.round(p * 100) + '%';
			pctEl.textContent = Math.round(p * 100) + '%';
			var on = heads[0];
			heads.forEach(function (h) { if (h.getBoundingClientRect().top < 140) on = h; });
			if (on) {
				curEl.textContent = on.getAttribute('data-agr-sec');
				tocLinks.forEach(function (a) { a.classList.toggle('is-on', a.getAttribute('data-agr-jump') === on.id); });
			}
			if (p >= .995 && !S.agrRead.how) unlock('scrolled');
		}
		S.agrScroll = onScroll;
		window.addEventListener('scroll', onScroll, { passive: true });
		window.addEventListener('resize', onScroll, { passive: true });
		var end = document.querySelector('[data-agr-end]');
		if ('IntersectionObserver' in window && end) {
			var io = new IntersectionObserver(function (en) { if (en[0].isIntersecting) { unlock('scrolled'); io.disconnect(); } }, { threshold: 1 });
			io.observe(end);
		}
		onScroll();

		tocLinks.forEach(function (a) {
			a.addEventListener('click', function (e) {
				e.preventDefault();
				var t = document.getElementById(a.getAttribute('data-agr-jump'));
				if (t) window.scrollTo({ top: t.getBoundingClientRect().top + window.scrollY - 128, behavior: 'smooth' });
			});
		});

		if (!form) return;
		var pad = sigPad(document.querySelector('[data-sigpad]'), function () {
			var hint = document.querySelector('[data-sig-hint]');
			if (hint) hint.hidden = pad.length() > 0;
		});
		S.sigPad = pad;
		pad.lock(form.disabled);
		var onResize = function () { if (document.body.contains(form)) pad.resize(); };
		window.addEventListener('resize', onResize);
		S.agrResize = onResize;
		var mode = 'draw';

		function unlock(how) {
			if (!form || !form.disabled) { if (how && !S.agrRead.how) S.agrRead.how = how; return; }
			S.agrRead.how = how;
			form.disabled = false;
			if (pad) pad.lock(false);
			var lock = document.querySelector('[data-sign-lock]');
			if (lock) { lock.classList.add('is-open'); lock.innerHTML = icon('check', 15) + '<span>' + (how === 'scrolled' ? 'You have reached the end of the agreement.' : 'Thanks for confirming you have read it.') + ' You can sign now.</span>'; }
		}
		if (S.agrRead.how) unlock(S.agrRead.how);
		var rc = document.querySelector('[data-read-confirm]');
		if (rc) rc.addEventListener('change', function () { if (rc.checked) unlock('confirmed'); });

		[].slice.call(document.querySelectorAll('[data-sig-tab]')).forEach(function (b) {
			b.addEventListener('click', function () {
				mode = b.getAttribute('data-sig-tab');
				document.querySelectorAll('[data-sig-tab]').forEach(function (x) { x.classList.toggle('is-on', x === b); x.setAttribute('aria-selected', x === b ? 'true' : 'false'); });
				document.querySelector('[data-sig-draw]').hidden = mode !== 'draw';
				document.querySelector('[data-sig-type]').hidden = mode !== 'type';
				if (mode === 'type') {
					loadSigFont();
					var ti = document.querySelector('[data-sig-typed]'), nm = document.querySelector('[data-sign-name]');
					if (!ti.value && nm.value) { ti.value = nm.value; ti.dispatchEvent(new Event('input')); }
					ti.focus();
				} else {
					pad.resize();
				}
			});
		});
		var typed = document.querySelector('[data-sig-typed]'), pv = document.querySelector('[data-sig-preview]');
		typed.addEventListener('input', function () { pv.textContent = typed.value.trim() || 'Your signature'; pv.classList.toggle('is-empty', !typed.value.trim()); });
		document.querySelector('[data-sig-clear]').addEventListener('click', function () { pad.clear(); });
		var nameIn = document.querySelector('[data-sign-name]'), help = document.querySelector('[data-sign-namehelp]');
		nameIn.addEventListener('input', function () {
			var v = nameIn.value.trim(), ok = !v || nameMatches(v);
			help.textContent = ok ? 'As it appears on your W-9 and ID.' : 'This does not match your Hub account name (' + S.boot.user.name + '). Use your full legal name.';
			help.classList.toggle('axh-bad', !ok);
		});

		document.querySelector('[data-sign-submit]').addEventListener('click', function () {
			var btn = this;
			var name = nameIn.value.trim().replace(/\s+/g, ' ');
			if (!S.agrRead.how) { toast('Read to the end of the agreement first, or tick "I have read the entire agreement".', 'bad'); return; }
			if (!/\S+\s+\S+/.test(name)) { toast('Type your full legal name — first and last.', 'bad'); nameIn.focus(); return; }
			if (!nameMatches(name)) { toast('Your name does not match your Hub account name. Use your full legal name.', 'bad'); nameIn.focus(); return; }
			if (mode === 'draw' && pad.length() < 40) { toast('Please draw your signature in the box.', 'bad'); return; }
			if (mode === 'type' && typed.value.trim().length < 3) { toast('Type your name to create your signature.', 'bad'); typed.focus(); return; }
			if (!document.querySelector('[data-sign-consent]').checked) { toast('Tick the box to agree to sign electronically.', 'bad'); return; }
			btn.disabled = true; btn.innerHTML = '<span class="axh-spin"></span> Signing…';
			var img = mode === 'draw' ? Promise.resolve(pad.png()) : typedPng(typed.value.trim());
			img.then(function (png) {
				return api('/onboarding/agreement/sign', { method: 'POST', body: {
					version: S.agr.version, hash: S.agr.hash, legal_name: name, business_name: document.querySelector('[data-sign-biz]').value.trim(),
					method: mode, typed_name: mode === 'type' ? typed.value.trim() : '', signature: png, consent: true, read: S.agrRead.how
				} });
			}).then(function (r) {
				S.boot.onboarding = r.state.status;
				S.onb = r.state;
				render(viewSignedDone(r.state));
				window.scrollTo(0, 0);
			}).catch(function (er) {
				btn.disabled = false; btn.innerHTML = icon('pen', 16) + ' Sign agreement';
				toast(er.message, 'bad');
				if (er.code === 'axh_changed') setTimeout(route, 1500);
			});
		});
	}

	function viewSignedDone(d) {
		var next = onbNext(d.status);
		return '<div class="axh-onb-success">' +
			'<div class="axh-onb-success__i">' + icon('check', 40) + '</div>' +
			'<h1 class="thunder">Agreement signed</h1>' +
			'<p>Thanks, ' + esc(S.boot.user.name.split(' ')[0]) + '. Your signed copy is on its way to ' + esc(S.boot.user.email) + ', and it is always here in the Hub.</p>' +
			'<div class="axh-actions" style="justify-content:center"><button class="axh-btn axh-btn--ghost axh-btn--pill" data-onb-copy="' + d.signed.id + '">' + icon('eye', 15) + ' View signed copy</button>' +
			'<button class="axh-btn axh-btn--ghost axh-btn--pill" data-onb-print="' + d.signed.id + '">' + icon('printer', 15) + ' Print / Save as PDF</button></div>' +
			onbProgress(d.status) +
			(next ? '<a class="axh-btn axh-btn--primary axh-btn--pill axh-btn--lg" href="' + next.href + '">Next: ' + next.title + ' ' + icon('right', 15) + '</a>'
				: '<a class="axh-btn axh-btn--primary axh-btn--pill axh-btn--lg" href="#/onboarding">See your onboarding ' + icon('right', 15) + '</a>') +
			'</div>';
	}

	/* ------------------------------------------------------------- W-9 */

	function sizeTxt(n) { n = Number(n) || 0; return n > 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB'; }

	function viewW9(d) {
		var st = d.status.steps.w9, cur = (d.w9 || []).filter(function (x) { return x.status === 'current'; })[0];
		var older = (d.w9 || []).filter(function (x) { return x !== cur; });
		var html = '<p class="axh-crumb"><a href="#/onboarding">' + icon('back', 14) + ' Onboarding</a></p>' +
			'<div class="axh-page-head"><div><h1 class="thunder">W-9</h1><p>The IRS form that lets AdaptExec pay you and send your 1099-NEC.</p></div>' +
			'<div class="axh-actions"><span class="axh-badge axh-badge--' + onbStep(d.status, 'w9').tone + '">' + onbStep(d.status, 'w9').label + '</span></div></div>';

		if (cur) {
			html += '<div class="axh-signed"><div class="axh-signed__i">' + icon('check', 26) + '</div><div class="axh-signed__b"><h3 class="thunder">W-9 on file</h3>' +
				'<p>Uploaded ' + esc(fmtDate(cur.created_at, true)) + ' · ' + esc(String(cur.mime).replace('application/', '').replace('image/', '').toUpperCase()) + ' · ' + sizeTxt(cur.size) + '. Encrypted, and visible only to you and AdaptExec.</p>' +
				'<div class="axh-actions"><button class="axh-btn axh-btn--ghost axh-btn--pill" data-onb-w9="' + cur.id + '">' + icon('download', 15) + ' Download my W-9</button>' +
				'<button class="axh-btn axh-btn--ghost axh-btn--pill" data-w9-replace>Replace it</button></div></div></div>';
		} else if (st.state === 'rejected') {
			html += '<div class="axh-warn"><b>' + icon('flag', 15) + ' AdaptExec needs a new W-9</b><p>' + (st.note ? esc(st.note) : 'Please upload a new, complete and signed W-9.') + '</p></div>';
		}

		html += '<div class="axh-grid axh-grid--2"' + (cur ? ' data-w9-steps hidden' : ' data-w9-steps') + '><div class="axh-card axh-w9">' +
			'<ol class="axh-w9__steps">' +
			'<li><span class="axh-w9__n">1</span><div><h4>Download the blank form</h4><p>The official IRS Form W-9 (' + esc(d.irs.rev) + '), straight from irs.gov, so it is always the current one.</p>' +
			'<div class="axh-actions"><a class="axh-btn axh-btn--dark axh-btn--pill" href="' + esc(d.irs.pdf) + '" target="_blank" rel="noopener" data-w9-blank>' + icon('download', 15) + ' Download blank W-9 (PDF)</a>' +
			'<a class="axh-btn axh-btn--quiet axh-btn--sm" href="' + esc(d.irs.info) + '" target="_blank" rel="noopener">IRS instructions ↗</a></div></div></li>' +
			'<li><span class="axh-w9__n">2</span><div><h4>Fill it in and sign it</h4><ul class="axh-w9__tips">' +
			'<li><b>Line 1:</b> your name exactly as on your tax return.</li>' +
			'<li><b>Line 2:</b> your business name, only if you work through an LLC or company.</li>' +
			'<li><b>Line 3a:</b> most reps tick <i>Individual/sole proprietor</i>.</li>' +
			'<li><b>Part I:</b> your SSN — or your business EIN if you entered a business on line 2.</li>' +
			'<li><b>Part II:</b> sign and date it. Unsigned forms cannot be accepted.</li></ul>' +
			'<p class="axh-hint">Fill the PDF on your computer, or print it, sign it and take a clear photo.</p></div></li>' +
			'<li><span class="axh-w9__n">3</span><div><h4>Upload it here</h4>' +
			'<label class="axh-drop" data-drop><input type="file" data-w9-file accept=".pdf,.jpg,.jpeg,.png,.heic,.heif,application/pdf,image/jpeg,image/png,image/heic,image/heif">' +
			'<span class="axh-drop__i">' + icon('upload', 26) + '</span><b>Drop your completed W-9 here</b><span>or <u>choose a file</u> · PDF, JPG, PNG or HEIC · up to ' + d.max_mb + ' MB</span></label>' +
			'<div class="axh-drop__file" data-w9-picked hidden></div></div></li>' +
			'</ol></div>' +
			'<div><div class="axh-card axh-card--dark"><div class="axh-card__h"><h3>' + icon('lock', 16) + ' Private by design</h3></div>' +
			'<ul class="axh-onb-list axh-onb-list--dark">' +
			'<li>' + icon('check', 14) + '<span>Encrypted the moment it is uploaded, with a key only this site holds.</span></li>' +
			'<li>' + icon('check', 14) + '<span>Stored in a private folder the web cannot reach — never in a public link.</span></li>' +
			'<li>' + icon('check', 14) + '<span>Only you and AdaptExec can open it, and every download is logged.</span></li>' +
			'<li>' + icon('check', 14) + '<span>Never emailed. Please do not email or text your W-9 to anyone.</span></li></ul></div>' +
			'<div class="axh-card" style="margin-top:16px"><div class="axh-card__h"><h3>Good to know</h3></div>' +
			'<dl class="axh-faq"><dt>Do I send it to the IRS?</dt><dd>No. The W-9 stays with AdaptExec. The IRS gets the 1099-NEC AdaptExec files for you.</dd>' +
			'<dt>Is anything withheld from my checks?</dt><dd>No. You are an independent contractor, so set aside money for your own taxes.</dd>' +
			'<dt>My details changed.</dt><dd>Upload a new W-9 here any time. Your earlier one is kept on record.</dd></dl></div></div></div>';

		if (older.length) {
			html += '<div class="axh-card" style="margin-top:16px"><div class="axh-card__h"><h3>Earlier uploads</h3><span class="axh-hint">Kept on record, encrypted</span></div><ul class="axh-list">' +
				older.map(function (x) {
					return '<li><div class="axh-list__t"><b>' + esc(fmtDate(x.created_at, true)) + '</b><span>' + esc(x.status === 'rejected' ? 'Sent back by AdaptExec' : 'Replaced') + ' · ' + sizeTxt(x.size) + '</span></div>' +
						'<button class="axh-btn axh-btn--ghost axh-btn--sm" data-onb-w9="' + x.id + '">' + icon('download', 14) + ' Download</button></li>';
				}).join('') + '</ul></div>';
		}
		return html;
	}

	var W9_EXT = { pdf: 1, jpg: 1, jpeg: 1, png: 1, heic: 1, heif: 1 };
	function bindW9() {
		var inp = document.querySelector('[data-w9-file]'), drop = document.querySelector('[data-drop]'), box = document.querySelector('[data-w9-picked]');
		if (!inp) return;
		var rep = document.querySelector('[data-w9-replace]');
		if (rep) rep.addEventListener('click', function () { var s = document.querySelector('[data-w9-steps]'); s.hidden = false; s.scrollIntoView({ behavior: 'smooth', block: 'start' }); });
		function pick(f) {
			if (!f) return;
			var ext = String(f.name.split('.').pop() || '').toLowerCase();
			var max = (S.onb && S.onb.max_mb ? S.onb.max_mb : 10) * 1048576;
			if (!W9_EXT[ext]) { toast('Upload a PDF, JPG, PNG or HEIC file.', 'bad'); return; }
			if (f.size > max) { toast('That file is ' + sizeTxt(f.size) + '. The limit is ' + (S.onb && S.onb.max_mb ? S.onb.max_mb : 10) + ' MB — try a PDF or a smaller photo.', 'bad'); return; }
			if (!f.size) { toast('That file is empty.', 'bad'); return; }
			box.hidden = false;
			box.innerHTML = '<span class="axh-drop__fi">' + icon('file', 18) + '</span><div class="axh-drop__fn"><b>' + esc(f.name) + '</b><span>' + sizeTxt(f.size) + '</span>' +
				'<div class="axh-upbar" hidden><i></i></div></div>' +
				'<button type="button" class="axh-btn axh-btn--primary axh-btn--pill" data-w9-go>' + icon('lock', 14) + ' Upload securely</button>';
			box.querySelector('[data-w9-go]').onclick = function () { upload(f, this); };
		}
		function upload(f, btn) {
			var bar = box.querySelector('.axh-upbar'), fill = bar.querySelector('i');
			btn.disabled = true; btn.textContent = 'Uploading…'; bar.hidden = false;
			var fd = new FormData(); fd.append('file', f, f.name);
			var x = new XMLHttpRequest();
			x.open('POST', AXH.root + '/onboarding/w9');
			x.setRequestHeader('X-WP-Nonce', AXH.nonce);
			x.upload.onprogress = function (e) { if (e.lengthComputable) fill.style.width = Math.round(e.loaded / e.total * 100) + '%'; };
			x.onload = function () {
				var j = {}; try { j = JSON.parse(x.responseText); } catch (er) {}
				if (x.status >= 200 && x.status < 300 && j.ok) {
					S.boot.onboarding = j.state.status; S.onb = j.state;
					toast('W-9 uploaded and encrypted. Thank you!', 'ok');
					render(viewW9(j.state)); bindW9();
					var next = onbNext(j.state.status);
					if (next) setTimeout(function () { toast('Next: ' + next.title, 'ok'); }, 900);
				} else {
					btn.disabled = false; btn.innerHTML = icon('lock', 14) + ' Upload securely'; bar.hidden = true;
					toast(j.message || (x.status === 413 ? 'That file is too large for the server.' : 'The upload did not finish. Please try again.'), 'bad');
				}
			};
			x.onerror = function () { btn.disabled = false; btn.innerHTML = icon('lock', 14) + ' Upload securely'; bar.hidden = true; toast('The upload did not finish — check your connection and try again.', 'bad'); };
			x.send(fd);
		}
		inp.addEventListener('change', function () { pick(inp.files && inp.files[0]); });
		['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-over'); }); });
		['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-over'); }); });
		drop.addEventListener('drop', function (e) { pick(e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]); });
	}

	/* ------------------------------------------------------ payment setup */

	function payeeLines(p) {
		if (!p) return [];
		return [p.street + (p.unit ? ', ' + p.unit : ''), p.city + ', ' + p.state + ' ' + p.zip].filter(function (x) { return x && x.replace(/[ ,]/g, ''); });
	}
	function envelope(p, label) {
		var lines = payeeLines(p);
		return '<div class="axh-env"><div class="axh-env__stamp"></div><span class="axh-env__l">' + (label || 'Checks are mailed to') + '</span>' +
			'<div class="axh-env__a"><b data-pv="payable_to">' + esc(p && p.payable_to ? p.payable_to : 'Your name') + '</b>' +
			'<span data-pv="l1">' + esc(lines[0] || 'Street address') + '</span><span data-pv="l2">' + esc(lines[1] || 'City, ST 00000') + '</span></div></div>';
	}

	function viewPayment(d) {
		var p = d.payee, editing = !p || S.payEdit, status = d.payee_status;
		var html = '<p class="axh-crumb"><a href="#/onboarding">' + icon('back', 14) + ' Onboarding</a></p>' +
			'<div class="axh-page-head"><div><h1 class="thunder">Payment setup</h1><p>Commission is paid by check, mailed after your client’s payment has fully cleared.</p></div>' +
			'<div class="axh-actions"><span class="axh-badge axh-badge--' + onbStep(d.status, 'payee').tone + '">' + onbStep(d.status, 'payee').label + '</span></div></div>';

		var how = '<div class="axh-card" style="margin-top:16px"><div class="axh-card__h"><h3>How you’ll be paid</h3></div><ul class="axh-onb-list">' +
			'<li>' + icon('money', 15) + '<span><b>By check</b>, mailed to the address here.</span></li>' +
			'<li>' + icon('clock', 15) + '<span><b>After the funds clear.</b> Commission is earned once the client’s payment has fully cleared, and paid within a reasonable time after that — there is no fixed pay date.</span></li>' +
			'<li>' + icon('shield', 15) + '<span><b>Changes are verified.</b> If you change this address, you get an email and AdaptExec confirms it before the next check goes out.</span></li>' +
			'<li>' + icon('file', 15) + '<span><b>Keep your W-9 current</b> if your name or business changes.</span></li></ul></div>';

		if (!editing) {
			html += '<div class="axh-grid axh-grid--2"><div>' +
				(status === 'verify' ? '<div class="axh-note"><b>New address being verified</b><p>You changed your payout address on ' + esc(fmtDate(d.status.steps.payee.at, true)) + '. AdaptExec will confirm it with you before mailing the next check.</p></div>' : '') +
				'<div class="axh-card"><div class="axh-card__h"><h3>Your payment details</h3><button data-pay-edit>Update</button></div>' +
				envelope(p) +
				'<dl class="axh-kv" style="margin-top:16px"><dt>Legal name</dt><dd>' + esc(p.legal_name) + '</dd>' +
				(p.business_name ? '<dt>Business</dt><dd>' + esc(p.business_name) + '</dd>' : '') +
				'<dt>Checks payable to</dt><dd>' + esc(p.payable_to) + '</dd><dt>Phone</dt><dd>' + esc(p.phone) + '</dd><dt>Payment emails</dt><dd>' + esc(p.email) + '</dd></dl>' +
				'<div class="axh-actions" style="margin-top:16px"><button class="axh-btn axh-btn--ghost axh-btn--pill" data-pay-edit>Update payment details</button></div></div></div>' +
				'<div>' + how + '</div></div>';
			return html;
		}

		var v = p || {};
		var name = v.legal_name || (S.boot.user.name.indexOf(' ') > 0 ? S.boot.user.name : '');
		var f = function (k, label, val, attrs, help) {
			return '<div class="axh-field" data-pf-wrap="' + k + '"><label for="axh-py-' + k + '">' + label + '</label><input id="axh-py-' + k + '" type="text" data-pay="' + k + '" value="' + esc(val || '') + '" ' + (attrs || '') + '>' +
				'<div class="axh-field__err" data-pf-err="' + k + '"></div>' + (help ? '<div class="axh-field__help">' + help + '</div>' : '') + '</div>';
		};
		html += (p ? '<div class="axh-note"><b>Changing your payout details</b><p>For your protection, a change to where checks go is emailed to ' + esc(S.boot.user.email) + ' and verified by AdaptExec before the next check is mailed.</p></div>' : '') +
			'<div class="axh-grid axh-grid--2"><div class="axh-card axh-payform"><div class="axh-card__h"><h3>' + (p ? 'Update payment details' : 'Where should we send your checks?') + '</h3></div>' +
			'<div class="axh-form-grid">' +
			f('legal_name', 'Payee legal name *', name, 'autocomplete="name"', 'Your name as it appears on your W-9.') +
			f('business_name', 'Business name <span class="axh-hint">(optional)</span>', v.business_name, 'autocomplete="organization"') +
			'</div>' +
			'<div class="axh-field"><label for="axh-py-payable_to">Make checks payable to *</label>' +
			'<div class="axh-seg axh-payto" data-payto>' +
			'<button type="button" data-payto-pick="legal_name">My name</button><button type="button" data-payto-pick="business_name">My business</button></div>' +
			'<input id="axh-py-payable_to" type="text" data-pay="payable_to" value="' + esc(v.payable_to || name) + '"><div class="axh-field__err" data-pf-err="payable_to"></div>' +
			'<div class="axh-field__help">Exactly as it should be printed on the check. Your bank must accept checks in this name.</div></div>' +
			'<div class="axh-form-grid">' +
			f('street', 'Street address *', v.street, 'autocomplete="address-line1" placeholder="123 Main St"') +
			f('unit', 'Apt, suite or unit', v.unit, 'autocomplete="address-line2" placeholder="Optional"') +
			f('city', 'City *', v.city, 'autocomplete="address-level2"') +
			'<div class="axh-form-grid axh-form-grid--tight">' +
			'<div class="axh-field" data-pf-wrap="state"><label for="axh-py-state">State *</label><select id="axh-py-state" data-pay="state" autocomplete="address-level1"><option value="">—</option>' +
			US_STATES.map(function (s) { return '<option' + (v.state === s ? ' selected' : '') + '>' + s + '</option>'; }).join('') + '</select><div class="axh-field__err" data-pf-err="state"></div></div>' +
			f('zip', 'ZIP code *', v.zip, 'autocomplete="postal-code" inputmode="numeric" maxlength="10" placeholder="85251"') +
			'</div>' +
			f('phone', 'Phone *', v.phone || '', 'type="tel" autocomplete="tel" inputmode="tel" placeholder="(602) 555-0100"') +
			f('email', 'Email for payment notices *', v.email || S.boot.user.email, 'autocomplete="email" inputmode="email"') +
			'</div>' +
			'<p class="axh-hint" style="margin:-6px 0 14px">United States addresses only. Country: United States.</p>' +
			'<label class="axh-consent"><input type="checkbox" data-pay-confirm><span><b>This is correct.</b> I confirm the name and mailing address in the preview are exactly how my commission checks should be made out and where they should be mailed.</span></label>' +
			(p ? '<label class="axh-consent axh-consent--warn"><input type="checkbox" data-pay-change><span><b>Yes, change where my checks go.</b> I understand AdaptExec will email me about this change and may verify it before the next check is mailed.</span></label>' : '') +
			'<div class="axh-actions"><button class="axh-btn axh-btn--primary axh-btn--pill axh-btn--lg" data-pay-save>' + (p ? 'Save changes' : 'Save payment details') + '</button>' +
			(p ? '<button class="axh-btn axh-btn--quiet" data-pay-cancel>Cancel</button>' : '') + '</div></div>' +
			'<div><div class="axh-card axh-card--sticky"><div class="axh-card__h"><h3>Your check, as entered</h3></div>' +
			'<div class="axh-check-pv"><div class="axh-check-pv__top"><span>ADAPT<b>EXEC</b></span><span>Preview</span></div>' +
			'<div class="axh-check-pv__pay"><span>Pay to the order of</span><b data-pv="payable_to2">' + esc(v.payable_to || name || 'Your name') + '</b></div>' +
			'<div class="axh-check-pv__memo"><span>Memo</span><i>Commission</i></div></div>' +
			envelope(v.street ? v : { payable_to: v.payable_to || name }, 'Mailed to') +
			'</div>' + how + '</div></div>';
		return html;
	}

	function bindPayment() {
		var form = document.querySelector('.axh-payform');
		if (!form) return;
		var get = function (k) { var x = form.querySelector('[data-pay="' + k + '"]'); return x ? x.value.trim() : ''; };
		var payTouched = !!(S.onb && S.onb.payee);
		function paint() {
			var p = { payable_to: get('payable_to') || get('legal_name'), street: get('street'), unit: get('unit'), city: get('city'), state: get('state'), zip: get('zip') };
			var lines = payeeLines(p);
			document.querySelectorAll('[data-pv="payable_to"]').forEach(function (x) { x.textContent = p.payable_to || 'Your name'; });
			document.querySelectorAll('[data-pv="payable_to2"]').forEach(function (x) { x.textContent = p.payable_to || 'Your name'; });
			var l1 = document.querySelector('[data-pv="l1"]'), l2 = document.querySelector('[data-pv="l2"]');
			if (l1) l1.textContent = p.street ? lines[0] : 'Street address';
			if (l2) l2.textContent = p.city || p.state || p.zip ? (p.city || 'City') + ', ' + (p.state || 'ST') + ' ' + (p.zip || '00000') : 'City, ST 00000';
		}
		form.addEventListener('input', function (e) {
			var k = e.target.getAttribute('data-pay');
			if (k === 'payable_to') payTouched = true;
			if (k === 'legal_name' && !payTouched) form.querySelector('[data-pay="payable_to"]').value = e.target.value;
			if (k) { var er = form.querySelector('[data-pf-err="' + k + '"]'); if (er) er.textContent = ''; }
			paint();
		});
		form.addEventListener('change', paint);
		form.querySelectorAll('[data-payto-pick]').forEach(function (b) {
			b.addEventListener('click', function () {
				var v = get(b.getAttribute('data-payto-pick'));
				if (!v) { toast(b.getAttribute('data-payto-pick') === 'business_name' ? 'Enter your business name first.' : 'Enter your legal name first.', 'bad'); return; }
				form.querySelector('[data-pay="payable_to"]').value = v; payTouched = true; paint();
			});
		});
		var cancel = form.querySelector('[data-pay-cancel]');
		if (cancel) cancel.onclick = function () { S.payEdit = false; render(viewPayment(S.onb)); bindPayment(); };
		form.querySelector('[data-pay-save]').onclick = function () {
			var btn = this, body = {};
			['legal_name', 'business_name', 'payable_to', 'street', 'unit', 'city', 'state', 'zip', 'phone', 'email'].forEach(function (k) { body[k] = get(k); });
			var errs = {};
			if (body.legal_name.length < 3) errs.legal_name = 'Enter your full legal name.';
			if (body.street.length < 3) errs.street = 'Enter the street address.';
			if (body.city.length < 2) errs.city = 'Enter the city.';
			if (!body.state) errs.state = 'Choose a state.';
			if (!/^\d{5}(-\d{4})?$/.test(body.zip)) errs.zip = 'Enter a 5-digit ZIP (or ZIP+4).';
			var dg = body.phone.replace(/\D/g, ''); if (dg.length === 11 && dg[0] === '1') dg = dg.slice(1);
			if (dg.length !== 10) errs.phone = 'Enter a 10-digit phone number.';
			if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(body.email)) errs.email = 'Enter a valid email.';
			Object.keys(errs).forEach(function (k) { var x = form.querySelector('[data-pf-err="' + k + '"]'); if (x) x.textContent = errs[k]; });
			if (Object.keys(errs).length) { toast(errs[Object.keys(errs)[0]], 'bad'); var first = form.querySelector('[data-pay="' + Object.keys(errs)[0] + '"]'); if (first) first.focus(); return; }
			if (!form.querySelector('[data-pay-confirm]').checked) { toast('Check the preview, then tick "This is correct".', 'bad'); return; }
			var ch = form.querySelector('[data-pay-change]');
			if (ch && !ch.checked) { toast('Tick the box to confirm you want to change where your checks go.', 'bad'); return; }
			body.confirm = true; body.confirm_change = !!(ch && ch.checked);
			btn.disabled = true; btn.textContent = 'Saving…';
			api('/onboarding/payee', { method: 'POST', body: body }).then(function (r) {
				S.payEdit = false; S.onb = r.state; S.boot.onboarding = r.state.status;
				toast(r.unchanged ? 'Nothing changed.' : (r.changed && r.changed.length ? 'Saved. We emailed you about the change.' : 'Payment details saved.'), 'ok');
				render(viewPayment(r.state)); bindPayment(); window.scrollTo(0, 0);
			}).catch(function (er) {
				btn.disabled = false; btn.textContent = S.onb && S.onb.payee ? 'Save changes' : 'Save payment details';
				var fl = er.data && er.data.fields;
				if (fl) Object.keys(fl).forEach(function (k) { var x = form.querySelector('[data-pf-err="' + k + '"]'); if (x) x.textContent = fl[k]; });
				toast(er.message, 'bad');
			});
		};
		paint();
	}

	/* ------------------------------------------- files: view, print, save */

	function fileName(cd) {
		var m = /filename\*=UTF-8''([^;]+)/i.exec(cd || '') || /filename="([^"]+)"/i.exec(cd || '');
		try { return m ? decodeURIComponent(m[1]) : 'download'; } catch (e) { return m ? m[1] : 'download'; }
	}
	/* Files come through the REST API with the nonce in a header, never in a
	   URL, and are handed to the browser as a blob. */
	function fetchFile(path) {
		return fetch(AXH.root + path, { credentials: 'same-origin', cache: 'no-store', headers: { 'X-WP-Nonce': AXH.nonce } }).then(function (r) {
			if (!r.ok) return r.json().then(function (j) { throw new Error(j.message || 'Could not open that file.'); }, function () { throw new Error('Could not open that file.'); });
			return r.blob().then(function (b) { return { blob: b, name: fileName(r.headers.get('Content-Disposition')) }; });
		});
	}
	function saveFile(path) {
		return fetchFile(path).then(function (f) {
			var a = document.createElement('a');
			a.href = URL.createObjectURL(f.blob); a.download = f.name;
			document.body.appendChild(a); a.click();
			setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 4000);
		}).catch(function (e) { toast(e.message, 'bad'); });
	}
	function openFile(path, print) {
		var w = window.open('', '_blank');
		if (w) { try { w.document.write('<p style="font:15px system-ui;padding:24px;color:#555">Opening your document…</p>'); } catch (e) {} }
		return fetchFile(path).then(function (f) {
			var url = URL.createObjectURL(f.blob);
			if (!w || w.closed) { location.href = url; return; }
			w.location.href = url;
			if (print) {
				var tries = 0, t = setInterval(function () {
					tries++;
					try { if (w.document && w.document.readyState === 'complete' && w.location.href === url) { clearInterval(t); w.focus(); w.print(); } } catch (e) {}
					if (tries > 60) clearInterval(t);
				}, 150);
			}
			setTimeout(function () { URL.revokeObjectURL(url); }, 120000);
		}).catch(function (e) { if (w) w.close(); toast(e.message, 'bad'); });
	}

	/* ------------------------------------------------------- admin views */

	function onbChip(st, key) { var o = onbStep(st, key); return '<span class="axh-badge axh-badge--' + o.tone + '">' + o.label + '</span>'; }

	function viewOnbManage(d) {
		var reps = (d.reps || []).filter(function (r) { return r.active; }), off = (d.reps || []).filter(function (r) { return !r.active; });
		var n = function (f) { return reps.filter(f).length; };
		var verify = reps.filter(function (r) { return r.onboarding.steps.payee.state === 'verify'; });
		var cs = reps.filter(function (r) { return r.onboarding.steps.agreement.state === 'signed' && !r.onboarding.steps.agreement.countersigned; });
		var attn = [];
		if (verify.length) attn.push('<a href="#/manage/onboarding/rep/' + verify[0].id + '">' + icon('shield', 15) + ' Verify the new payout address' + (verify.length > 1 ? 'es' : '') + ' for ' + verify.map(function (r) { return esc(r.name); }).join(', ') + ' before the next check</a>');
		if (cs.length) attn.push('<a href="#/manage/onboarding/rep/' + cs[0].id + '">' + icon('pen', 15) + ' ' + cs.length + ' signed agreement' + (cs.length === 1 ? '' : 's') + ' to countersign (optional)</a>');
		function row(r) {
			var st = r.onboarding, a = st.steps.agreement, w = st.steps.w9, p = st.steps.payee;
			return '<tr data-onb-rep="' + r.id + '" class="' + (r.active ? '' : 'is-off') + '"><td data-th="Company"><div class="axh-co"><a href="#/manage/onboarding/rep/' + r.id + '">' + esc(r.name) + '</a><span>' + esc(r.email) + '</span></div>' +
				'<div class="axh-onb-rowprog">' + onbProgress(st) + '<span>' + (st.complete ? (st.override && st.done < 3 ? 'Marked complete' : 'Complete') : st.done + ' of 3') + '</span></div></td>' +
				'<td data-th="Agreement">' + onbChip(st, 'agreement') + (a.version ? '<div class="axh-hint">v' + esc(a.version) + (a.countersigned ? ' · countersigned' : '') + '</div>' : '') + '</td>' +
				'<td data-th="W-9">' + onbChip(st, 'w9') + (w.at ? '<div class="axh-hint">' + when(w.at) + '</div>' : '') + '</td>' +
				'<td data-th="Payout">' + (p.state === 'verify' ? '<span class="axh-badge axh-badge--red">Verify new address</span>' : onbChip(st, 'payee')) + (r.payee ? '<div class="axh-hint">' + esc(r.payee.payable_to) + ' · ' + esc(r.payee.lines[r.payee.lines.length - 1] || '') + '</div>' : '') + '</td>' +
				'<td data-th="Last activity" style="color:var(--gray)">' + (r.last ? when(r.last) : '—') + '</td>' +
				'<td class="axh-row-acts"><a class="axh-btn axh-btn--ghost axh-btn--sm" href="#/manage/onboarding/rep/' + r.id + '">Manage</a></td></tr>';
		}
		return '<div class="axh-page-head"><div><h1 class="thunder">Onboarding</h1><p>Each rep’s agreement, W-9 and payout address. Agreement v' + esc(d.agreement.version) + ', effective ' + esc(fmtDate(d.agreement.effective)) + '.</p></div>' +
			'<div class="axh-actions"><a class="axh-btn axh-btn--ghost" href="#/manage/onboarding/settings">' + icon('gear', 15) + ' Settings & agreement</a></div></div>' +
			(attn.length ? '<div class="axh-attn">' + attn.join('') + '</div>' : '') +
			'<div class="axh-stats">' +
			stat('Fully onboarded', n(function (r) { return r.onboarding.complete; }) + '<span class="axh-stat__of">/' + reps.length + '</span>', 'Active reps', true) +
			stat('Agreements signed', n(function (r) { return r.onboarding.steps.agreement.state === 'signed'; }), n(function (r) { return r.onboarding.steps.agreement.state === 'resign'; }) + ' need to re-sign', false) +
			stat('W-9s on file', n(function (r) { return r.onboarding.steps.w9.state === 'on_file'; }), 'Encrypted · never emailed', false) +
			stat('Addresses to verify', verify.length, verify.length ? 'Before the next check' : 'All clear', false) + '</div>' +
			'<div class="axh-card">' + (reps.length || off.length
				? '<table class="axh-table axh-table--stack axh-table--onb"><thead><tr><th>Representative</th><th>Agreement</th><th>W-9</th><th>Payout address</th><th>Last activity</th><th></th></tr></thead><tbody>' +
					reps.map(row).join('') + off.map(row).join('') + '</tbody></table>'
				: empty('No representatives yet', 'Add a rep under Team and their onboarding shows up here.', '<a class="axh-btn axh-btn--primary" href="#/manage/team">Team</a>')) + '</div>';
	}

	var AUDIT_LABEL = { agreement_signed: 'Signed the agreement', agreement_viewed: 'Opened the signed copy', agreement_countersigned: 'Countersigned', resign_requested: 'Asked to re-sign',
		w9_uploaded: 'Uploaded a W-9', w9_downloaded: 'W-9 downloaded', w9_rejected: 'Asked for a new W-9', payee_set: 'Payout address added', payee_changed: 'Payout details changed',
		payee_verified: 'Payout address verified', reminder_sent: 'Reminder sent', onboarding_override: 'Marked complete', onboarding_override_removed: 'Override removed', payout_override: 'Paid with onboarding incomplete' };

	function viewOnbRep(d) {
		var r = d.rep, st = d.status, rd = d.readiness || { ready: false, missing: [] };
		var sigs = d.signatures || [], w9 = d.w9 || [], p = d.payee;
		var hist = (d.audit || []).filter(function (x) { return x.action === 'payee_set' || x.action === 'payee_changed'; });
		var html = '<p class="axh-crumb"><a href="#/manage/onboarding">' + icon('back', 14) + ' Onboarding</a></p>' +
			'<div class="axh-page-head"><div><h1 class="thunder">' + esc(r.name) + '</h1><p>' + esc(r.email) + (r.phone ? ' · ' + esc(r.phone) : '') + ' · <a href="#/manage/rep/' + r.id + '">Rep profile</a></p>' +
			'<div class="axh-onb-rowprog" style="margin-top:8px">' + onbProgress(st) + '<span>' + (st.complete ? (st.override && st.done < 3 ? 'Marked complete by ' + esc(st.override.by) : 'Onboarding complete') : st.done + ' of 3 done') + '</span></div></div>' +
			'<div class="axh-actions">' + (st.done < 3 ? '<button class="axh-btn axh-btn--ghost" data-onb-do="remind" data-rep="' + r.id + '">' + icon('mail', 15) + ' Send reminder</button>' : '') +
			(st.override ? '<button class="axh-btn axh-btn--quiet" data-onb-do="uncomplete" data-rep="' + r.id + '">Undo "complete"</button>' : (st.done < 3 ? '<button class="axh-btn axh-btn--ghost" data-onb-do="complete" data-rep="' + r.id + '">Mark complete</button>' : '')) + '</div></div>' +
			(d.reminded ? '<p class="axh-hint" style="margin:-10px 0 14px">Last reminder ' + when(d.reminded) + '.</p>' : '') +
			(rd.ready ? '<div class="axh-onb-ok">' + icon('check', 15) + ' Ready to be paid: agreement signed, W-9 on file, payout address verified.</div>'
				: '<div class="axh-warn"><b>' + icon('flag', 15) + ' Not ready for a check yet</b><p>Missing: ' + rd.missing.map(esc).join(', ') + '. "Mark paid" on the Payouts screen asks you to confirm before paying.</p></div>');

		html += '<div class="axh-grid axh-grid--2"><div>' +
			'<div class="axh-card"><div class="axh-card__h"><h3>Contractor agreement</h3>' + onbChip(st, 'agreement') + '</div>' +
			(sigs.length ? '<ul class="axh-list">' + sigs.map(function (s, i) {
				return '<li class="axh-onb-sig"><div class="axh-list__t"><b>Version ' + esc(s.version) + ' · ' + esc(s.legal_name) + (s.business_name ? ' (' + esc(s.business_name) + ')' : '') + (i ? ' <span class="axh-badge axh-badge--gray">Earlier</span>' : '') + '</b>' +
					'<span>Signed ' + esc(fmtDate(s.signed_at, true)) + ' · ' + (s.method === 'draw' ? 'drawn' : 'typed') + ' · IP ' + esc(s.ip || '—') + '</span>' +
					'<span>' + (s.countersigned_at ? icon('check', 12) + ' Countersigned by ' + esc(s.countersign_name) + ' ' + when(s.countersigned_at) : 'Not countersigned') + '</span></div>' +
					'<div class="axh-onb-btns"><button class="axh-btn axh-btn--ghost axh-btn--sm" data-onb-copy="' + s.id + '">View</button>' +
					'<button class="axh-btn axh-btn--ghost axh-btn--sm" data-onb-dl="' + s.id + '">' + icon('download', 13) + '</button>' +
					(!s.countersigned_at && !i ? '<button class="axh-btn axh-btn--primary axh-btn--sm" data-onb-cs="' + s.id + '">Countersign</button>' : '') + '</div></li>';
			}).join('') + '</ul>' : '<p class="axh-hint">Not signed yet.</p>') +
			(sigs.length ? '<div class="axh-danger-row"><button class="axh-btn axh-btn--quiet axh-btn--sm" data-onb-do="resign" data-rep="' + r.id + '">Ask them to re-sign</button><span class="axh-hint">Their earlier signature stays on record.</span></div>' : '') + '</div>' +
			'<div class="axh-card" style="margin-top:16px"><div class="axh-card__h"><h3>W-9</h3>' + onbChip(st, 'w9') + '</div>' +
			(w9.length ? '<ul class="axh-list">' + w9.map(function (x) {
				return '<li><span class="axh-onb-fi">' + icon('file', 16) + '</span><div class="axh-list__t"><b>' + esc(fmtDate(x.created_at, true)) + ' <span class="axh-badge axh-badge--' + ({ current: 'green', replaced: 'gray', rejected: 'red' }[x.status] || 'gray') + '">' + ({ current: 'Current', replaced: 'Replaced', rejected: 'Sent back' }[x.status] || esc(x.status)) + '</span></b>' +
					'<span>' + esc(String(x.mime).split('/')[1].toUpperCase()) + ' · ' + sizeTxt(x.size) + (x.orig_name ? ' · ' + esc(x.orig_name) : '') + '</span></div>' +
					'<button class="axh-btn axh-btn--ghost axh-btn--sm" data-onb-w9="' + x.id + '">' + icon('download', 13) + ' Download</button></li>';
			}).join('') + '</ul>' : '<p class="axh-hint">No W-9 uploaded yet.</p>') +
			(st.steps.w9.state === 'on_file' ? '<div class="axh-danger-row"><button class="axh-btn axh-btn--quiet axh-btn--sm" data-onb-do="reject_w9" data-rep="' + r.id + '">Ask for a new W-9</button><span class="axh-hint">For an unsigned, blurry or outdated form.</span></div>' : '') +
			'<p class="axh-hint" style="margin-top:10px">' + icon('lock', 12) + ' Stored encrypted. Downloads are logged. Never forward a W-9 by email.</p></div></div>' +

			'<div><div class="axh-card"><div class="axh-card__h"><h3>Payout address</h3>' + (st.steps.payee.state === 'verify' ? '<span class="axh-badge axh-badge--red">Verify before next check</span>' : onbChip(st, 'payee')) + '</div>' +
			(p ? envelope(p) + '<dl class="axh-kv" style="margin-top:14px"><dt>Legal name</dt><dd>' + esc(p.legal_name) + '</dd>' + (p.business_name ? '<dt>Business</dt><dd>' + esc(p.business_name) + '</dd>' : '') +
				'<dt>Payable to</dt><dd>' + esc(p.payable_to) + '</dd><dt>Phone</dt><dd><a href="tel:' + esc(p.phone.replace(/[^0-9]/g, '')) + '">' + esc(p.phone) + '</a></dd><dt>Payment email</dt><dd>' + esc(p.email) + '</dd>' +
				(d.verified && d.verified.at ? '<dt>Verified</dt><dd>' + esc(d.verified.by) + ', ' + when(d.verified.at) + '</dd>' : '') + '</dl>' +
				(st.steps.payee.state === 'verify' ? '<div class="axh-note" style="margin:14px 0 0"><b>Changed ' + when(st.steps.payee.at) + '</b><p>Confirm the change with ' + esc(r.name.split(' ')[0]) + ' by phone, or at an email you already trust, before you mail the next check.</p>' +
					'<button class="axh-btn axh-btn--primary axh-btn--sm" style="margin-top:10px" data-onb-do="verify_payee" data-rep="' + r.id + '">' + icon('check', 14) + ' I verified it</button></div>' : '')
				: '<p class="axh-hint">No payout address yet.</p>') +
			(hist.length ? '<h4 class="axh-onb-sub">Address history</h4><ul class="axh-list axh-onb-hist">' + hist.map(function (h) {
				var a = (h.data && h.data.address) || {};
				return '<li><div class="axh-list__t"><b>' + esc(when(h.created_at)) + ' · ' + esc((h.data && h.data.event) || AUDIT_LABEL[h.action]) + '</b><span>' + esc(a.payable_to || '') + ' · ' + esc(payeeLines(a).join(', ')) + '</span>' +
					(h.data && h.data.changed ? '<span>Changed: ' + esc(h.data.changed.join(', ').replace(/_/g, ' ')) + ' · IP ' + esc(h.ip || '—') + '</span>' : '<span>IP ' + esc(h.ip || '—') + '</span>') + '</div></li>';
			}).join('') + '</ul>' : '') + '</div>' +
			'<div class="axh-card" style="margin-top:16px"><div class="axh-card__h"><h3>Audit log</h3><span class="axh-hint">' + (d.audit || []).length + ' events</span></div>' +
			((d.audit || []).length ? '<ul class="axh-tl axh-onb-audit">' + d.audit.slice(0, 60).map(function (x) {
				return '<li class="is-quiet"><b>' + esc(AUDIT_LABEL[x.action] || x.action.replace(/_/g, ' ')) + '</b><span>' + esc(fmtDate(x.created_at, true)) + ' · ' + esc(x.actor) + (x.ip ? ' · ' + esc(x.ip) : '') + '</span>' +
					(x.detail ? '<p class="axh-tl__b">' + esc(x.detail) + '</p>' : '') + '</li>';
			}).join('') + '</ul>' : '<p class="axh-hint">Nothing yet.</p>') + '</div></div></div>';
		return html;
	}

	function viewOnbSettings(d) {
		var s = d.settings, a = d.agreement, pv = d.private || {};
		return '<p class="axh-crumb"><a href="#/manage/onboarding">' + icon('back', 14) + ' Onboarding</a></p>' +
			'<div class="axh-page-head"><div><h1 class="thunder">Onboarding settings</h1><p>Who the agreement is with, the agreement itself, reminders and security.</p></div></div>' +
			'<div class="axh-grid axh-grid--2"><div>' +
			'<div class="axh-card"><div class="axh-card__h"><h3>Company details in the agreement</h3></div>' +
			'<div class="axh-field"><label for="axh-os-n">Company legal name</label><input id="axh-os-n" type="text" data-os="company_legal_name" value="' + esc(s.company_legal_name) + '">' +
			'<div class="axh-field__help">The exact legal name of the business reps contract with (for example "AdaptExec LLC"). Shown as the party to the agreement.</div></div>' +
			'<div class="axh-field"><label for="axh-os-a">Company mailing address <span class="axh-hint">(optional)</span></label><textarea id="axh-os-a" data-os="company_address" rows="2" placeholder="Leave blank to keep it private">' + esc(s.company_address) + '</textarea>' +
			'<div class="axh-field__help">Appears in the agreement’s notices clause, which every rep sees. Leave it blank and notices go by email. Use a business or PO Box address, never a home address.</div></div>' +
			'<div class="axh-field"><label for="axh-os-s">Signer name and title</label><input id="axh-os-s" type="text" data-os="signer" value="' + esc(s.signer) + '">' +
			'<div class="axh-field__help">Used on signed copies and when you countersign.</div></div>' +
			'<label class="axh-switch" style="margin:4px 0 14px"><input type="checkbox" data-os="reminders"' + (s.reminders ? ' checked' : '') + '><span class="axh-switch__ui" aria-hidden="true"></span><span>Email a rep a daily reminder if onboarding is still incomplete 3 days after they were added (at most once a day, up to 10). Only for reps added after this update.</span></label>' +
			(a.signed_current ? '<p class="axh-alert" style="background:var(--warn-soft);color:var(--warn)">' + a.signed_current + ' rep' + (a.signed_current === 1 ? ' has' : 's have') + ' signed the current text. Changing the company name or address changes the agreement text, so they would be asked to sign again.</p>' : '') +
			'<button class="axh-btn axh-btn--primary" data-os-save>Save settings</button></div>' +

			'<div class="axh-card" style="margin-top:16px"><div class="axh-card__h"><h3>The agreement</h3><button data-os-preview>Preview</button></div>' +
			'<dl class="axh-kv"><dt>Version</dt><dd>' + esc(a.version) + '</dd><dt>Effective</dt><dd>' + esc(fmtDate(a.effective)) + '</dd>' +
			'<dt>Text SHA-256</dt><dd><code class="axh-hash">' + esc(a.hash) + '</code></dd><dt>Signed current text</dt><dd>' + a.signed_current + ' rep' + (a.signed_current === 1 ? '' : 's') + '</dd></dl>' +
			'<details class="axh-os-edit"><summary>Edit the text (publishes a new version)</summary>' +
			'<p class="axh-hint" style="margin:10px 0">Plain text. <code>## 3. Heading</code> starts a section; <code>3.4 Lead-in.</code> starts a clause; <code>{{company}}</code> and <code>{{notice_clause}}</code> are filled in from the settings above. Publishing asks every rep to review and sign the new version; earlier signed copies are kept.</p>' +
			'<div class="axh-field"><textarea data-os-text rows="16" spellcheck="true" style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12.5px">' + esc(a.template) + '</textarea></div>' +
			'<div class="axh-form-grid"><div class="axh-field"><label>Version number</label><input type="text" data-os-ver placeholder="Next: automatic"></div>' +
			'<div class="axh-field"><label>What changed <span class="axh-hint">(internal)</span></label><input type="text" data-os-note placeholder="e.g. Attorney edits to section 9"></div></div>' +
			'<button class="axh-btn axh-btn--dark" data-os-publish>Publish new version</button></details>' +
			(d.versions && d.versions.length > 1 ? '<h4 class="axh-onb-sub">Versions</h4><ul class="axh-list">' + d.versions.map(function (v) { return '<li><div class="axh-list__t"><b>Version ' + esc(v.version) + '</b><span>Effective ' + esc(fmtDate(v.effective)) + (v.note ? ' · ' + esc(v.note) : '') + '</span></div></li>'; }).join('') + '</ul>' : '') +
			'</div></div>' +

			'<div><div class="axh-card axh-card--amber"><div class="axh-card__h"><h3>' + icon('flag', 16) + ' Before reps sign: attorney review</h3></div>' +
			'<p style="font-size:13.5px;line-height:1.6;margin:0 0 10px">Only administrators see this. The agreement is a careful, plain-English starting point written for how the Hub works — it is not legal advice. Have an Arizona attorney review it before your first rep signs, then publish any edits as a new version.</p>' +
			'<ul class="axh-paylist" style="font-size:13px"><li>Confirm the <b>company legal name</b> above (LLC? DBA?).</li>' +
			'<li>Section 3.3–3.8: commission earned only on <b>cleared</b> funds; clawback, offset and holds.</li>' +
			'<li>Section 3.4: <b>no fixed pay date</b> ("within a reasonable time").</li>' +
			'<li>Section 2.4: registrations may be released after <b>90 days</b> without activity.</li>' +
			'<li>Section 3.11: no commission on renewals or recurring plans unless confirmed.</li>' +
			'<li>Section 9: <b>12-month</b> non-solicitation (no non-compete); Section 13.1: Arizona law, Maricopa County courts.</li>' +
			'<li>Reps in other states (for example California) may need state-specific terms.</li></ul></div>' +
			'<div class="axh-card" style="margin-top:16px"><div class="axh-card__h"><h3>' + icon('lock', 16) + ' Security</h3></div>' +
			'<ul class="axh-onb-checks">' +
			'<li class="' + (pv.sodium ? 'is-ok' : 'is-bad') + '">' + icon(pv.sodium ? 'check' : 'x', 14) + ' Encryption (libsodium) ' + (pv.sodium ? 'available' : 'missing') + '</li>' +
			'<li class="' + (pv.finfo ? 'is-ok' : 'is-bad') + '">' + icon(pv.finfo ? 'check' : 'x', 14) + ' File-type checking (fileinfo) ' + (pv.finfo ? 'available' : 'missing') + '</li>' +
			'<li class="' + (pv.writable ? 'is-ok' : 'is-bad') + '">' + icon(pv.writable ? 'check' : 'x', 14) + ' Private folder ' + (pv.writable ? 'ready' : 'not writable') + '</li>' +
			'<li data-os-reach>' + icon('shield', 14) + ' Web access to the private folder: <b>not checked yet</b></li></ul>' +
			'<button class="axh-btn axh-btn--ghost axh-btn--sm" data-os-selftest>Run access check</button>' +
			'<p class="axh-hint" style="margin-top:10px">Writes a harmless test file to the private folder, tries to open it from the web (it should be blocked), then deletes it. Files are encrypted with a key derived from this site’s wp-config salts: keep those salts if you move the site, or the files cannot be opened.</p></div></div></div>';
	}

	/* A short note for an admin action, in a modal. */
	function onbNoteModal(title, intro, placeholder, cta, cb) {
		var m = modal(title, '<p class="axh-hint" style="margin-bottom:12px">' + intro + '</p><div class="axh-field"><label for="axh-onb-note">Note <span class="axh-hint">(optional)</span></label><textarea id="axh-onb-note" rows="3" placeholder="' + esc(placeholder) + '"></textarea></div>',
			'<button class="axh-btn axh-btn--quiet" data-close>Cancel</button><button class="axh-btn axh-btn--primary" data-onb-yes>' + cta + '</button>');
		m.querySelector('[data-onb-yes]').onclick = function () { this.disabled = true; cb(m.querySelector('#axh-onb-note').value.trim()); };
	}

	function onbRepDo(btn) {
		var id = btn.getAttribute('data-rep'), x = btn.getAttribute('data-onb-do');
		var go = function (note) {
			btn.disabled = true;
			return api('/manage/onboarding/rep/' + id, { method: 'POST', body: { do: x, note: note || '' } }).then(function (r) {
				closeModal();
				toast({ verify_payee: 'Marked verified.', remind: 'Reminder emailed.', complete: 'Marked complete.', uncomplete: 'Override removed.', resign: 'They have been asked to re-sign.', reject_w9: 'They have been asked for a new W-9.' }[x] || 'Done.', 'ok');
				refreshOnbAttention();
				render(viewOnbRep(r.state));
			}).catch(function (er) { btn.disabled = false; closeModal(); toast(er.message, 'bad'); });
		};
		if (x === 'resign') return onbNoteModal('Ask to re-sign', 'They will be asked to review and sign the agreement again. Their current signature stays on record.', 'e.g. Please sign again with your full legal name', 'Ask to re-sign', go);
		if (x === 'reject_w9') return onbNoteModal('Ask for a new W-9', 'The current W-9 is kept on record but marked as sent back, and they are asked to upload a new one.', 'e.g. Part II is not signed', 'Ask for a new W-9', go);
		if (x === 'complete') return onbNoteModal('Mark onboarding complete', 'Use this when you have their paperwork some other way. Payouts still check for a W-9, agreement and verified address.', 'e.g. Signed paper agreement on file', 'Mark complete', go);
		if (x === 'verify_payee') return onbNoteModal('Payout address verified', 'Confirm you checked the new address with the rep (by phone, or at an email you already trust).', 'e.g. Called Ben 10/25, confirmed', 'Mark verified', go);
		return go('');
	}
	function refreshOnbAttention() {
		api('/bootstrap').then(function (b) { S.boot.onboarding = b.onboarding; nav(); }).catch(function () {});
	}

	/* Admin and rep onboarding clicks. Kept apart from the main handler. */
	document.addEventListener('click', function (e) {
		var t = e.target, hit = function (sel) { return t.closest ? t.closest(sel) : null; };
		var b;
		if ((b = hit('[data-onb-copy]'))) { openFile('/onboarding/copy/' + b.getAttribute('data-onb-copy')); return; }
		if ((b = hit('[data-onb-print]'))) { openFile('/onboarding/copy/' + b.getAttribute('data-onb-print'), true); return; }
		if ((b = hit('[data-onb-dl]'))) { saveFile('/onboarding/copy/' + b.getAttribute('data-onb-dl') + '?download=1'); return; }
		if ((b = hit('[data-onb-w9]'))) { saveFile('/onboarding/w9/' + b.getAttribute('data-onb-w9')); return; }
		if (hit('[data-onb-strip-hide]')) { try { localStorage.setItem('axh_onb_done_hide', '1'); } catch (er) {} var sp = t.closest('.axh-onb-strip'); if (sp) sp.remove(); return; }
		if (hit('[data-pay-edit]')) { S.payEdit = true; render(viewPayment(S.onb)); bindPayment(); return; }
		if ((b = hit('[data-onb-do]'))) { onbRepDo(b); return; }
		if ((b = hit('tr[data-onb-rep]')) && !hit('a,button')) { location.hash = '#/manage/onboarding/rep/' + b.getAttribute('data-onb-rep'); return; }
		if ((b = hit('[data-onb-cs]'))) {
			var sid = b.getAttribute('data-onb-cs');
			var m = modal('Countersign', '<p style="font-size:14px;line-height:1.6">Countersign this agreement as <b>' + esc((S.onbSigner || 'AdaptExec')) + '</b>? The signed copy is regenerated with both signatures and the date, and the rep is notified.</p>',
				'<button class="axh-btn axh-btn--quiet" data-close>Cancel</button><button class="axh-btn axh-btn--primary" data-yes>' + icon('pen', 14) + ' Countersign</button>');
			m.querySelector('[data-yes]').onclick = function () {
				this.disabled = true;
				api('/manage/onboarding/sig/' + sid + '/countersign', { method: 'POST' }).then(function () { closeModal(); toast('Countersigned.', 'ok'); refreshOnbAttention(); route(); })
					.catch(function (er) { closeModal(); toast(er.message, 'bad'); });
			};
			return;
		}
		if (hit('[data-os-save]')) {
			var body = {};
			document.querySelectorAll('[data-os]').forEach(function (i) { body[i.getAttribute('data-os')] = i.type === 'checkbox' ? i.checked : i.value; });
			api('/manage/onboarding/settings', { method: 'POST', body: body }).then(function (r) { S.onbSet = r; S.onbSigner = r.settings.signer; toast('Settings saved.', 'ok'); render(viewOnbSettings(r)); })
				.catch(function (er) { toast(er.message, 'bad'); });
			return;
		}
		if (hit('[data-os-preview]')) {
			var ag = (S.onbSet || {}).agreement || {};
			modal('Agreement v' + esc(ag.version || ''), '<div class="axh-agr__doc axh-agr__doc--modal">' + agreementHtml(ag.text || '').html + '</div>', '<button class="axh-btn axh-btn--primary" data-close>Close</button>');
			return;
		}
		if ((b = hit('[data-os-publish]'))) {
			var txt = document.querySelector('[data-os-text]').value, pb = b;
			var pver = document.querySelector('[data-os-ver]').value.trim(), pnote = document.querySelector('[data-os-note]').value.trim();
			ask({ title: 'Publish a new version?', body: 'Every rep will be asked to review and sign it again.', ok: 'Publish' }).then(function () {
				pb.disabled = true;
				api('/manage/onboarding/agreement', { method: 'POST', body: { template: txt, version: pver, note: pnote } })
					.then(function (r) { S.onbSet = r; toast('Version ' + r.agreement.version + ' published.', 'ok'); render(viewOnbSettings(r)); })
					.catch(function (er) { pb.disabled = false; toast(er.message, 'bad'); });
			});
			return;
		}
		if ((b = hit('[data-os-selftest]'))) {
			var li = document.querySelector('[data-os-reach]');
			b.disabled = true; li.innerHTML = '<span class="axh-spin"></span> Checking…';
			api('/manage/onboarding/selftest', { method: 'POST', body: { do: 'create' } }).then(function (r) {
				if (!r.url) { li.className = 'is-ok'; li.innerHTML = icon('check', 14) + ' Private folder is outside the web root (custom location).'; return null; }
				return Promise.all([
					fetch(r.url, { cache: 'no-store', credentials: 'omit' }).then(function (x) { return x.status; }, function () { return 0; }),
					fetch(r.dir, { cache: 'no-store', credentials: 'omit' }).then(function (x) { return x.status; }, function () { return 0; })
				]).then(function (codes) {
					return api('/manage/onboarding/selftest', { method: 'POST', body: { do: 'cleanup', name: r.name } }).then(function () { return codes; });
				});
			}).then(function (codes) {
				b.disabled = false;
				if (!codes) return;
				var blocked = codes[0] === 403 || codes[0] === 404 || codes[0] === 401;
				li.className = blocked ? 'is-ok' : 'is-bad';
				li.innerHTML = icon(blocked ? 'check' : 'x', 14) + (blocked ? ' Blocked from the web (test file returned ' + codes[0] + ', folder ' + codes[1] + '). Test file removed.' : ' <b>Reachable from the web</b> (HTTP ' + codes[0] + '). Files are still encrypted, but ask your host to deny access to wp-content/uploads/axh-private/.');
			}).catch(function (er) { b.disabled = false; li.innerHTML = icon('x', 14) + ' ' + esc(er.message); });
			return;
		}
	});

	/* =====================================================================
	   BILLING (1.7.0) — proposals, estimates, invoices, customers, payments,
	   reports and commission payouts. AdaptExec creates and edits; reps see
	   their own clients' documents and can copy or send the customer link.
	   ===================================================================== */

	var B = { boot: null, f: { type: '', status: '', q: '', rep: '' }, cf: { q: '' }, pf: { q: '', method: '', uncleared: 0 }, ctab: 'reps', sel: {} };
	var BTL = { proposal: 'Proposal', estimate: 'Estimate', invoice: 'Invoice' };
	var BST = { draft: 'Draft', sent: 'Sent', viewed: 'Viewed', accepted: 'Accepted', declined: 'Declined', expired: 'Expired', partial: 'Partially paid', paid: 'Paid', overdue: 'Overdue', void: 'Void' };
	var BLEG = { deposit: 'Deposit half', final: 'Final half', full: 'Both halves', '': 'Not tied to commission' };

	function bootBilling(force) {
		if (B.boot && !force) return Promise.resolve(B.boot);
		return api('/billing/boot').then(function (b) { B.boot = b; return b; });
	}
	function bm(n) { n = Math.round((Number(n) || 0) * 100) / 100; return (n < 0 ? '−' : '') + '$' + Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
	function bm0(n) { n = Math.round((Number(n) || 0) * 100) / 100; return (n < 0 ? '−' : '') + '$' + Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 }); }
	function bk(n) { n = Number(n) || 0; return Math.abs(n) >= 10000 ? '$' + (n / 1000).toFixed(n >= 100000 ? 0 : 1).replace(/\.0$/, '') + 'K' : bm0(n); }
	function bdate(d, yr) {
		if (!d) return '—';
		var t = new Date(String(d).slice(0, 10) + 'T12:00:00');
		if (isNaN(t)) return '—';
		return t.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: yr === false ? undefined : 'numeric' });
	}
	function today() { var t = new Date(); return t.getFullYear() + '-' + ('0' + (t.getMonth() + 1)).slice(-2) + '-' + ('0' + t.getDate()).slice(-2); }
	function bpill(s) { return '<span class="bx-pill bx-pill--' + esc(s) + '">' + esc(BST[s] || s) + '</span>'; }
	function btype(t) { return '<span class="bx-type bx-type--' + esc(t) + '">' + esc(BTL[t] || t) + '</span>'; }
	function bdl(r) {
		var a = document.createElement('a');
		a.href = URL.createObjectURL(new Blob([(r.mime === 'text/csv' ? '﻿' : '') + r.content], { type: (r.mime || 'text/plain') + ';charset=utf-8' }));
		a.download = r.filename || 'export.txt';
		document.body.appendChild(a); a.click();
		setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500);
	}
	function copyText(t) {
		if (navigator.clipboard && window.isSecureContext !== false) return navigator.clipboard.writeText(t).catch(function () { return fallbackCopy(t); });
		return fallbackCopy(t);
	}
	function fallbackCopy(t) {
		var x = document.createElement('textarea'); x.value = t; x.setAttribute('readonly', ''); x.style.position = 'fixed'; x.style.opacity = '0';
		document.body.appendChild(x); x.select();
		try { document.execCommand('copy'); } catch (e) {}
		x.remove(); return Promise.resolve();
	}
	function bfail(e) { toast((e && e.message) || 'Something went wrong.', 'bad'); }
	function bhead(title, sub, acts, back) {
		return (back ? '<a class="axh-btn axh-btn--quiet axh-btn--sm bx-back" href="' + back[0] + '">' + icon('back', 14) + ' ' + esc(back[1]) + '</a>' : '') +
			'<div class="axh-page-head bx-head"><div><h1 class="thunder">' + title + '</h1>' + (sub ? '<p>' + sub + '</p>' : '') + '</div>' +
			(acts ? '<div class="axh-actions">' + acts + '</div>' : '') + '</div>';
	}
	function bstat(label, value, detail, cls) {
		return '<div class="bx-kpi' + (cls ? ' bx-kpi--' + cls : '') + '"><span class="bx-kpi__l">' + label + '</span><b class="bx-kpi__v">' + value + '</b>' + (detail ? '<span class="bx-kpi__d">' + detail + '</span>' : '') + '</div>';
	}
	function bempty(h, p, btn) {
		return '<div class="bx-empty"><div class="bx-empty__art">' + icon('receipt', 30) + '</div><h4>' + h + '</h4><p>' + p + '</p>' + (btn || '') + '</div>';
	}
	function custName(c) { return c ? (c.company || c.contact_name || 'Customer') : ''; }
	function repSelect(attr, sel) {
		return '<select ' + attr + '><option value="0">No rep (house account)</option>' + (S.boot.assignees || []).map(function (a) {
			return '<option value="' + a.id + '"' + (String(sel) === String(a.id) ? ' selected' : '') + '>' + esc(a.name) + '</option>';
		}).join('') + '</select>';
	}

	/* ------------------------------------------------------------ router */

	function billingRoute(p, render) {
		var mgr = S.boot.user.manager, v = p[0] || '';
		return bootBilling().then(function () {
			if (v === 'doc' && p[1]) return api('/billing/docs/' + p[1]).then(function (d) { B.cur = d; if (render(viewBillDoc(d))) afterBillDoc(d); });
			if (v === 'customer' && p[1]) return api('/billing/customers/' + p[1]).then(function (d) { B.cust = d; render(viewBillCustomer(d)); });
			if (!mgr && v !== '' && v !== 'docs') { location.hash = '#/billing'; return; }
			if (v === '' && !mgr) return billDocsLoad(render, true);
			if (v === 'docs') return billDocsLoad(render, false);
			if (v === 'edit' && p[1]) {
				return Promise.all([api('/billing/docs/' + p[1]), api('/billing/customers')]).then(function (r) {
					B.customers = r[1].customers || [];
					if (r[0].doc.status === 'accepted' || r[0].doc.status === 'void' || (r[0].doc.type === 'invoice' && r[0].doc.amount_paid > 0)) { location.hash = '#/billing/doc/' + p[1]; toast('This document is locked. Duplicate it to make changes.'); return; }
					edStart(r[0], false); if (render(viewEditor())) edBind();
				});
			}
			if (v === 'template') {
				var t = (B.boot.templates || []).filter(function (x) { return x.id === p[1]; })[0];
				edStartTemplate(t || null); if (render(viewEditor())) edBind();
				return;
			}
			if (v === 'customers') return api('/billing/customers' + (B.cf.q ? '?q=' + encodeURIComponent(B.cf.q) : '')).then(function (d) { B.customers = d.customers || []; render(viewBillCustomers()); });
			if (v === 'payments') return billPaymentsLoad(render);
			if (v === 'items') return api('/billing/items').then(function (d) { B.items = d.items || []; render(viewBillItems()); });
			if (v === 'templates') return bootBilling(true).then(function () { render(viewBillTemplates()); });
			if (v === 'settings') return api('/billing/settings').then(function (d) { B.set = d; render(viewBillSettings(d)); });
			if (v === 'commissions') return api('/billing/commissions').then(function (d) { B.com = d; if (render(viewCommissions(d))) bindCommissions(); });
			if (v === 'batch' && p[1]) return api('/billing/batches/' + p[1]).then(function (d) { B.batch = d; render(viewBatch(d)); });
			if (v === 'statement' && p[1] && p[2]) return api('/billing/statements/' + p[1] + '/' + p[2]).then(function (d) { render(viewStatement(d, true)); });
			return api('/billing/dashboard').then(function (d) { B.dash = d; if (render(viewBillDash(d))) bindChart(); });
		});
	}

	/* --------------------------------------------------------- dashboard */

	function viewBillDash(d) {
		var k = d.kpis || {};
		var html = bhead('Billing', 'Every proposal, invoice and payment in one place. Numbers update the moment a payment is recorded.',
			'<div class="bx-menu"><button class="axh-btn axh-btn--ghost" data-bx-menu>' + icon('download', 15) + ' Export</button><div class="bx-menu__pop" hidden>' +
			[['invoices', 'csv', 'Invoices (CSV)'], ['estimates', 'csv', 'Proposals & estimates (CSV)'], ['payments', 'csv', 'Payments (CSV)'], ['customers', 'csv', 'Customers (CSV)'], ['aging', 'csv', 'A/R aging (CSV)'], ['qbo', 'csv', 'QuickBooks invoice import (CSV)'], ['qbo', 'json', 'QuickBooks entities (JSON)']]
				.map(function (x) { return '<button data-bx-export="' + x[0] + '" data-fmt="' + x[1] + '">' + x[2] + '</button>'; }).join('') + '</div></div>' +
			'<button class="axh-btn axh-btn--ghost" data-bx-new="invoice">' + icon('plus', 15) + ' Invoice</button>' +
			'<button class="axh-btn axh-btn--primary" data-bx-new="proposal">' + icon('plus', 15) + ' New proposal</button>');
		html += '<div class="bx-kpis">' +
			bstat('Billed this month', bm0(k.billed_month), 'Quarter ' + bk(k.billed_quarter) + ' · Year ' + bk(k.billed_year), 'accent') +
			bstat('Collected this month', bm0(k.collected_month), 'Quarter ' + bk(k.collected_quarter) + ' · Year ' + bk(k.collected_year)) +
			bstat('Outstanding A/R', bm0(k.outstanding), (k.open_count || 0) + ' open invoice' + (k.open_count === 1 ? '' : 's')) +
			bstat('Overdue', bm0(k.overdue), (k.overdue_count || 0) + ' invoice' + (k.overdue_count === 1 ? '' : 's') + ' past due', k.overdue > 0 ? 'bad' : '') +
			bstat('Average days to pay', k.avg_days_to_pay === null ? '—' : k.avg_days_to_pay + '<small> days</small>', 'Issue date to paid in full') +
			bstat('Proposal win rate', k.win_rate === null ? '—' : k.win_rate + '<small>%</small>', (k.won || 0) + ' won · ' + (k.lost || 0) + ' declined or expired') +
			bstat('Open proposals', bm0(k.pipeline), (k.pipeline_count || 0) + ' awaiting a decision') +
			bstat('Customer credit', bm0(k.credit), 'From overpayments, ready to apply') + '</div>';
		var months = d.monthly || [], max = 0;
		months.forEach(function (m) { max = Math.max(max, m.billed, m.collected); });
		html += '<div class="bx-grid2"><div class="axh-card bx-card"><div class="bx-card__h"><div><h3>Billed vs collected</h3><span>Last 12 months</span></div>' +
			'<div class="bx-legend"><span><i class="bx-sw bx-sw--b"></i>Billed</span><span><i class="bx-sw bx-sw--c"></i>Collected</span></div></div>' +
			(max ? barChart(months, max) : bempty('Nothing billed yet', 'Send your first invoice and this chart starts filling in.', '')) +
			(max ? '<details class="bx-tableview"><summary>View as table</summary><table class="axh-table"><thead><tr><th>Month</th><th class="num">Billed</th><th class="num">Collected</th></tr></thead><tbody>' +
				months.map(function (m) { return '<tr><td>' + esc(m.month) + '</td><td class="num">' + bm(m.billed) + '</td><td class="num">' + bm(m.collected) + '</td></tr>'; }).join('') + '</tbody></table></details>' : '') + '</div>';
		var ag = d.aging || {}, keys = ['current', 'd30', 'd60', 'd90', 'd90p'], agTot = 0;
		keys.forEach(function (x) { agTot += (ag[x] || {}).amount || 0; });
		html += '<div class="axh-card bx-card"><div class="bx-card__h"><div><h3>A/R aging</h3><span>' + bm0(agTot) + ' outstanding</span></div><button class="bx-link" data-bx-export="aging" data-fmt="csv">Export</button></div>' +
			(agTot ? '<div class="bx-aging">' + keys.map(function (x, i) {
				var b = ag[x] || { amount: 0, count: 0, label: x }, pct = agTot ? b.amount / agTot * 100 : 0;
				return '<div class="bx-aging__row" data-tip="' + esc(b.label + '|' + bm(b.amount) + '|' + b.count + ' invoice' + (b.count === 1 ? '' : 's')) + '"><span class="bx-aging__l">' + esc(b.label) + '</span>' +
					'<span class="bx-aging__bar"><i class="bx-age' + i + '" style="width:' + Math.max(pct, b.amount ? 2 : 0).toFixed(1) + '%"></i></span><b>' + bm0(b.amount) + '</b><em>' + b.count + '</em></div>';
			}).join('') + '</div>' +
			'<ul class="bx-mini">' + [].concat.apply([], keys.slice(1).map(function (x) { return (ag[x] || { items: [] }).items; })).slice(0, 5).map(function (it) {
				return '<li><a href="#/billing/doc/' + it.id + '"><b>' + esc(it.number) + '</b> ' + esc(it.customer) + '</a><span class="bx-bad">' + it.days + ' days late</span><b>' + bm(it.balance) + '</b></li>';
			}).join('') + '</ul>' : bempty('Nothing outstanding', 'Every invoice is paid. Nicely done.', '')) + '</div></div>';
		var cats = d.categories || [], cmax = 0; cats.forEach(function (c) { cmax = Math.max(cmax, c.amount); });
		var reps = d.reps || [], rmax = 0; reps.forEach(function (r) { rmax = Math.max(rmax, r.billed); });
		html += '<div class="bx-grid3"><div class="axh-card bx-card"><div class="bx-card__h"><div><h3>Revenue by service</h3><span>Billed, all time</span></div></div>' +
			(cats.length ? '<div class="bx-hbars">' + cats.map(function (c) { return hbar(c.category, c.amount, cmax); }).join('') + '</div>' : '<p class="axh-hint">Appears once invoices are sent.</p>') + '</div>' +
			'<div class="axh-card bx-card"><div class="bx-card__h"><div><h3>Revenue by rep</h3><span>Billed · collected</span></div><a class="bx-link" href="#/billing/commissions">Commissions</a></div>' +
			(reps.length ? '<div class="bx-hbars">' + reps.map(function (r) { return hbar(r.name, r.billed, rmax, bm0(r.collected) + ' collected'); }).join('') + '</div>' : '<p class="axh-hint">Appears once invoices are sent.</p>') + '</div>' +
			'<div class="axh-card bx-card"><div class="bx-card__h"><div><h3>Top customers</h3><span>By amount billed</span></div><a class="bx-link" href="#/billing/customers">All</a></div>' +
			((d.customers || []).length ? '<ol class="bx-top">' + d.customers.map(function (c, i) {
				return '<li><span class="bx-top__n">' + (i + 1) + '</span><a href="#/billing/customer/' + c.customer_id + '">' + esc(c.name) + '</a><b>' + bm0(c.billed) + '</b>' + (c.balance > 0 ? '<em>' + bm0(c.balance) + ' open</em>' : '<em class="bx-ok">Paid up</em>') + '</li>';
			}).join('') + '</ol>' : '<p class="axh-hint">Appears once invoices are sent.</p>') + '</div></div>';
		html += '<div class="axh-card bx-card"><div class="bx-card__h"><div><h3>Recent activity</h3><span>Sends, views, acceptances and payments</span></div><a class="bx-link" href="#/billing/docs">All documents</a></div>' +
			((d.recent || []).length ? '<ul class="bx-feed">' + d.recent.map(feedItem).join('') + '</ul>' : '<p class="axh-hint">Nothing yet. Create a proposal to get started.</p>') + '</div>';
		return '<div class="bx">' + html + '<div class="bx-tip" id="bx-tip" hidden></div></div>';
	}
	var FEED_ICON = { sent: 'mail', reminder: 'clock', viewed: 'eye', accepted: 'check', declined: 'x', payment: 'money', refund: 'back', void: 'trash', converted: 'receipt', created: 'plus', copied: 'link', updated: 'pen' };
	function feedItem(e) {
		return '<li class="bx-feed--' + esc(e.type) + '"><span class="bx-feed__i">' + icon(FEED_ICON[e.type] || 'spark', 14) + '</span><div><b>' + (e.number ? '<i>' + esc(e.number) + '</i>' : '') + esc(e.detail) + '</b><span>' + esc(e.actor) + ' · ' + when(e.created_at) + '</span></div>' +
			(e.doc_id ? '<a href="#/billing/doc/' + e.doc_id + '" aria-label="Open document">' + icon('right', 14) + '</a>' : '') + '</li>';
	}
	function hbar(label, v, max, sub) {
		var pct = max ? v / max * 100 : 0;
		return '<div class="bx-hbar" data-tip="' + esc(label + '|' + bm(v) + (sub ? '|' + sub : '')) + '"><span class="bx-hbar__l">' + esc(label) + '</span><span class="bx-hbar__t"><i style="width:' + Math.max(pct, v ? 2 : 0).toFixed(1) + '%"></i></span><b>' + bk(v) + '</b></div>';
	}
	function barChart(months, max) {
		// Drawn at (about) its real width so the axis text stays legible on a phone.
		var vw = (el('axh-view') && el('axh-view').clientWidth) || 760;
		var W = Math.max(300, Math.min(760, vw > 1100 ? Math.round(vw * 0.55) : vw - 72)), H = 240, pl = 44, pb = 26, pt = 10, cw = (W - pl) / months.length, bw = Math.min(16, cw / 3.2), every = cw < 34 ? 2 : 1;
		var nice = Math.pow(10, Math.floor(Math.log10(max))), top = Math.ceil(max / nice) * nice;
		var y = function (v) { return pt + (H - pt - pb) * (1 - v / top); };
		var s = '<svg class="bx-bars" viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="none" role="img" aria-label="Billed and collected by month">';
		for (var g = 0; g <= 4; g++) {
			var gv = top * g / 4, gy = y(gv);
			s += '<line x1="' + pl + '" x2="' + W + '" y1="' + gy.toFixed(1) + '" y2="' + gy.toFixed(1) + '" class="bx-grid"/><text x="' + (pl - 8) + '" y="' + (gy + 4).toFixed(1) + '" class="bx-ax" text-anchor="end">' + bk(gv) + '</text>';
		}
		months.forEach(function (m, i) {
			var x = pl + i * cw + cw / 2, yb = y(m.billed), yc = y(m.collected), base = y(0);
			s += '<g class="bx-bar" data-tip="' + esc(m.month + '|Billed ' + bm(m.billed) + '|Collected ' + bm(m.collected)) + '">' +
				'<rect class="bx-hit" x="' + (pl + i * cw) + '" y="' + pt + '" width="' + cw + '" height="' + (H - pt - pb) + '"/>' +
				(m.billed ? '<path class="bx-b" d="' + barPath(x - bw - 1, yb, bw, base) + '"/>' : '') +
				(m.collected ? '<path class="bx-c" d="' + barPath(x + 1, yc, bw, base) + '"/>' : '') +
				(i % every === (months.length - 1) % every ? '<text x="' + x + '" y="' + (H - 7) + '" class="bx-ax" text-anchor="middle">' + esc(m.label) + '</text>' : '') + '</g>';
		});
		return '<div class="bx-chart">' + s + '</svg></div>';
	}
	function barPath(x, y, w, base) {
		var h = base - y, r = Math.min(4, w / 2, h);
		if (h <= 0.5) return 'M' + x + ' ' + base + 'h' + w;
		return 'M' + x + ' ' + base + 'V' + (y + r) + 'Q' + x + ' ' + y + ' ' + (x + r) + ' ' + y + 'H' + (x + w - r) + 'Q' + (x + w) + ' ' + y + ' ' + (x + w) + ' ' + (y + r) + 'V' + base + 'Z';
	}
	function bindChart() {
		var tip = el('bx-tip');
		if (!tip) return;
		var view = el('axh-view');
		view.onpointermove = function (e) {
			var t = e.target.closest && e.target.closest('[data-tip]');
			if (!t) { tip.hidden = true; return; }
			var parts = t.getAttribute('data-tip').split('|');
			tip.textContent = '';
			var h = document.createElement('b'); h.textContent = parts[0]; tip.appendChild(h);
			parts.slice(1).forEach(function (x) { var s = document.createElement('span'); s.textContent = x; tip.appendChild(s); });
			tip.hidden = false;
			var r = view.getBoundingClientRect(), tx = e.clientX - r.left + 14, ty = e.clientY - r.top + 14;
			if (tx + 200 > r.width) tx -= 220;
			tip.style.transform = 'translate(' + tx + 'px,' + ty + 'px)';
		};
		view.onpointerleave = function () { tip.hidden = true; };
	}

	/* --------------------------------------------------------- documents */

	function billDocsLoad(render, repHome) {
		var f = B.f, q = [];
		['type', 'status', 'q'].forEach(function (k) { if (f[k]) q.push(k + '=' + encodeURIComponent(f[k])); });
		if (f.rep) q.push('rep_id=' + encodeURIComponent(f.rep));
		return api('/billing/docs' + (q.length ? '?' + q.join('&') : '')).then(function (d) { B.docs = d.docs || []; render(viewBillDocs(repHome)); });
	}
	function viewBillDocs(repHome) {
		var mgr = S.boot.user.manager, f = B.f, docs = B.docs || [];
		var sort = B.sort || 'new';
		docs = docs.slice().sort(function (a, b) {
			if (sort === 'amount') return b.total - a.total;
			if (sort === 'due') return String(a.due_date || a.expiry_date || '9').localeCompare(String(b.due_date || b.expiry_date || '9'));
			if (sort === 'customer') return String(a.customer).localeCompare(String(b.customer));
			return b.id - a.id;
		});
		var tot = 0, bal = 0;
		docs.forEach(function (x) { tot += x.total; bal += x.balance; });
		var html = bhead(mgr ? 'Documents' : 'Client billing', mgr ? 'Proposals, estimates and invoices. Click any row to open it.' : 'Proposals, estimates and invoices AdaptExec has sent to your clients. Copy a link to send it yourself, or email it from here.',
			mgr ? '<button class="axh-btn axh-btn--ghost" data-bx-new="estimate">' + icon('plus', 15) + ' Estimate</button><button class="axh-btn axh-btn--ghost" data-bx-new="invoice">' + icon('plus', 15) + ' Invoice</button><button class="axh-btn axh-btn--primary" data-bx-new="proposal">' + icon('plus', 15) + ' New proposal</button>'
				: '<a class="axh-btn axh-btn--ghost" href="#/statements">' + icon('doc', 15) + ' My statements</a>');
		html += '<div class="bx-tabs" role="tablist">' + [['', 'All'], ['proposal', 'Proposals'], ['estimate', 'Estimates'], ['invoice', 'Invoices']].map(function (t) {
			return '<button role="tab" aria-selected="' + (f.type === t[0]) + '" class="' + (f.type === t[0] ? 'is-on' : '') + '" data-bf="type" data-v="' + t[0] + '">' + t[1] + '</button>';
		}).join('') + '</div>';
		html += '<div class="bx-filters"><input type="search" class="axh-input" placeholder="Search number, title or customer" data-bf-q value="' + esc(f.q) + '" aria-label="Search documents">' +
			'<select class="axh-input" data-bf="status" aria-label="Status"><option value="">Any status</option>' + [['open', 'Open (unpaid / undecided)'], ['draft', 'Draft'], ['sent', 'Sent'], ['viewed', 'Viewed'], ['accepted', 'Accepted'], ['declined', 'Declined'], ['expired', 'Expired'], ['partial', 'Partially paid'], ['overdue', 'Overdue'], ['paid', 'Paid'], ['void', 'Void']].map(function (s) {
				return '<option value="' + s[0] + '"' + (f.status === s[0] ? ' selected' : '') + '>' + s[1] + '</option>'; }).join('') + '</select>' +
			(mgr ? '<select class="axh-input" data-bf="rep" aria-label="Rep">' + repOptions(f.rep, 'All reps') + '</select>' : '') +
			'<select class="axh-input" data-bsort aria-label="Sort"><option value="new"' + (sort === 'new' ? ' selected' : '') + '>Newest first</option><option value="due"' + (sort === 'due' ? ' selected' : '') + '>Due / expiry date</option><option value="amount"' + (sort === 'amount' ? ' selected' : '') + '>Amount</option><option value="customer"' + (sort === 'customer' ? ' selected' : '') + '>Customer</option></select></div>';
		if (!docs.length) {
			return '<div class="bx">' + html + '<div class="axh-card">' + bempty(f.q || f.status || f.type ? 'Nothing matches' : (mgr ? 'Create your first proposal' : 'Nothing here yet'),
				f.q || f.status || f.type ? 'Try a different filter.' : (mgr ? 'Start from a template (Website, Custom CRM, SEO…), make it yours, and send a link that looks like a high-end agency made it. Because one did.' : 'When AdaptExec sends a proposal or invoice to one of your clients, it shows up here.'),
				mgr && !(f.q || f.status || f.type) ? '<button class="axh-btn axh-btn--primary" data-bx-new="proposal">' + icon('plus', 15) + ' New proposal</button>' : '') + '</div></div>';
		}
		html += '<div class="axh-card bx-card bx-card--flush"><table class="axh-table axh-table--stack bx-docs"><thead><tr><th>Number</th><th>Customer</th><th>Status</th><th>Date</th><th>Due / valid</th><th class="num">Total</th><th class="num">Balance</th><th></th></tr></thead><tbody>' +
			docs.map(function (x) {
				return '<tr data-href="#/billing/doc/' + x.id + '"><td data-th="Number"><div class="bx-num">' + btype(x.type) + '<b>' + esc(x.number) + '</b></div><span class="bx-sub">' + esc(x.title || '') + '</span></td>' +
					'<td data-th="Customer"><b class="bx-strong">' + esc(x.customer) + '</b>' + (mgr && x.rep_name ? '<span class="bx-sub">' + esc(x.rep_name) + '</span>' : '') + '</td>' +
					'<td data-th="Status">' + bpill(x.display_status) + (x.view_count ? '<span class="bx-sub">' + icon('eye', 12) + ' ' + x.view_count + '</span>' : '') + '</td>' +
					'<td data-th="Date">' + bdate(x.issue_date) + '</td><td data-th="Due">' + bdate(x.type === 'invoice' ? x.due_date : x.expiry_date) + '</td>' +
					'<td data-th="Total" class="num"><b>' + bm(x.total) + '</b></td><td data-th="Balance" class="num">' + (x.type === 'invoice' ? (x.balance > 0 ? '<b class="' + (x.display_status === 'overdue' ? 'bx-bad' : '') + '">' + bm(x.balance) + '</b>' : '<span class="bx-ok">Paid</span>') : '—') + '</td>' +
					'<td class="axh-row-acts"><button class="axh-btn axh-btn--quiet axh-btn--sm" data-bx-copy="' + esc(x.url) + '" data-id="' + x.id + '" title="Copy customer link" aria-label="Copy customer link for ' + esc(x.number) + '">' + icon('link', 14) + '</button></td></tr>';
			}).join('') + '</tbody><tfoot><tr><td colspan="5">' + docs.length + ' document' + (docs.length === 1 ? '' : 's') + '</td><td class="num"><b>' + bm(tot) + '</b></td><td class="num"><b>' + bm(bal) + '</b></td><td></td></tr></tfoot></table></div>';
		return '<div class="bx">' + html + '</div>';
	}

	/* New document: pick the customer (and a template for proposals). */
	function newDocModal(type, preset) {
		preset = preset || {};
		Promise.all([api('/billing/customers'), bootBilling()]).then(function (r) {
			B.customers = r[0].customers || [];
			var tpls = (B.boot.templates || []);
			var m = modal('New ' + BTL[type].toLowerCase(),
				'<div class="axh-field"><label for="bx-nc">Customer</label><div class="bx-row"><select id="bx-nc" data-nd-cust>' +
				'<option value="">Choose a customer…</option>' + B.customers.map(function (c) { return '<option value="' + c.id + '"' + (String(preset.customer_id) === String(c.id) ? ' selected' : '') + '>' + esc(custName(c)) + (c.company && c.contact_name ? ' — ' + esc(c.contact_name) : '') + '</option>'; }).join('') +
				'</select><button type="button" class="axh-btn axh-btn--ghost" data-nd-newcust>' + icon('plus', 14) + ' New</button></div></div>' +
				(type === 'proposal' ? '<div class="axh-field"><label>Start from</label><div class="bx-tpls">' +
					tpls.map(function (t, i) { return '<label class="bx-tpl' + (i === 0 ? ' is-on' : '') + '"><input type="radio" name="bx-tpl" value="' + esc(t.id) + '"' + (i === 0 ? ' checked' : '') + '><b>' + esc(t.name) + '</b><span>' + ((t.content.sections || []).length) + ' sections · ' + ((t.content.options || []).length) + ' option' + ((t.content.options || []).length === 1 ? '' : 's') + '</span></label>'; }).join('') +
					'<label class="bx-tpl"><input type="radio" name="bx-tpl" value=""><b>Blank</b><span>Start from scratch</span></label></div></div>' : '') +
				'<p class="axh-hint">It starts as a draft. Nothing goes to the customer until you send it.</p>',
				'<button class="axh-btn axh-btn--quiet" data-close>Cancel</button><button class="axh-btn axh-btn--primary" data-nd-go>Create ' + BTL[type].toLowerCase() + '</button>');
			m.querySelectorAll('.bx-tpl input').forEach(function (i) { i.onchange = function () { m.querySelectorAll('.bx-tpl').forEach(function (x) { x.classList.toggle('is-on', x.contains(i) && i.checked); }); }; });
			m.querySelector('[data-nd-newcust]').onclick = function () { customerModal(null, function (c) { newDocModal(type, { customer_id: c.id, lead_id: preset.lead_id }); }); };
			m.querySelector('[data-nd-go]').onclick = function () {
				var cid = m.querySelector('[data-nd-cust]').value, tp = m.querySelector('input[name="bx-tpl"]:checked');
				if (!cid) { toast('Choose a customer first (or add a new one).', 'bad'); return; }
				this.disabled = true;
				var body = { type: type, customer_id: Number(cid) };
				if (tp && tp.value) body.template = tp.value;
				if (type === 'proposal' && !(tp && tp.value)) body.content = { sections: [{ kind: 'text', title: 'Overview', body: '' }, { kind: 'scope', title: 'Scope & deliverables', body: '' }, { kind: 'investment', title: 'Investment', body: '' }, { kind: 'terms', title: 'Terms', body: '' }], schedule: B.boot.schedule };
				api('/billing/docs', { method: 'POST', body: body }).then(function (d) { closeModal(); location.hash = '#/billing/edit/' + d.doc.id; }).catch(function (e) { bfail(e); m.querySelector('[data-nd-go]').disabled = false; });
			};
		}).catch(bfail);
	}

	/* ------------------------------------------- placeholders (1.7.3) */

	var PH_RE = /\[(?=[^\]\n]*[A-Za-z0-9])[^\]\[\n<>]{1,80}\](?!\()/g;
	function bxPh(x) {
		var out = [], c = (x && x.content) || {};
		function add(where, t) { if (typeof t === 'string') (t.match(PH_RE) || []).forEach(function (p) { out.push({ where: where, text: p }); }); }
		add('Title', x.title);
		if (x.type === 'proposal' || x.type == null) {
			var cv = c.cover || {}; add('Cover', cv.title); add('Cover', cv.subtitle); add('Cover', cv.intro);
			(c.sections || []).forEach(function (sc) {
				if (sc.on === false) return;
				var w = sc.title || 'Section'; add(w, sc.title); add(w, sc.body);
				(sc.items || []).forEach(function (it) { add(w, it.title); add(w, it.when); add(w, it.detail); });
			});
			(c.options || []).forEach(function (o) { var w = 'Option: ' + (o.name || ''); add(w, o.name); add(w, o.tagline); add(w, o.desc); (o.lines || []).forEach(function (l) { add(w, l.name); add(w, l.desc); }); });
			(c.addons || []).forEach(function (a) { add('Add-on: ' + (a.name || ''), a.name); add('Add-on: ' + (a.name || ''), a.desc); });
		}
		(x.lines || []).forEach(function (l) { add('Line items', l.name); add('Line items', l.desc); });
		(c.schedule || []).forEach(function (sc) { add('Payment schedule', sc.label); add('Payment schedule', sc.due); });
		add('Notes', x.notes); add('Terms', x.terms_text);
		return out;
	}
	function bxPhList(ph) {
		var seen = {};
		return '<ul class="bx-phlist">' + ph.filter(function (p) { var k = p.where + '|' + p.text; if (seen[k]) return false; seen[k] = 1; return true; })
			.map(function (p) { return '<li><mark>' + esc(p.text) + '</mark><span>' + esc(p.where) + '</span></li>'; }).join('') + '</ul>';
	}
	/* Ask before a customer gets a document that still has [placeholders] in it. */
	function bxPhGate(x, verb, go) {
		var ph = bxPh(x);
		if (!ph.length) { go(); return; }
		var m = modal(ph.length + ' unfilled placeholder' + (ph.length === 1 ? '' : 's'),
			'<p class="axh-hint" style="margin-bottom:10px">' + esc(x.number) + ' still has template blanks in square brackets. Your customer would see them, highlighted, on their page and in the PDF.</p>' + bxPhList(ph) +
			'<label class="axh-check bx-phok"><input type="checkbox" data-ph-ok> I know — ' + esc(verb.toLowerCase()) + ' it anyway</label>',
			'<button class="axh-btn axh-btn--quiet" data-close>Cancel</button>' + (S.boot.user.manager ? '<a class="axh-btn axh-btn--ghost" href="#/billing/edit/' + x.id + '" data-close>' + icon('pen', 14) + ' Fill them in</a>' : '') +
			'<button class="axh-btn axh-btn--primary" data-ph-go disabled>' + esc(verb) + ' anyway</button>');
		var ok = m.querySelector('[data-ph-ok]'), b = m.querySelector('[data-ph-go]');
		ok.onchange = function () { b.disabled = !ok.checked; };
		b.onclick = function () { closeModal(); go(); };
	}
	/* Download PDF: print the customer page itself (the preview frame, or a new tab), named after the document. */
	function bxDocTitle(x) {
		var c = (B.cur && B.cur.customer) || {};
		return BTL[x.type] + ' ' + x.number + (custName(c) ? ' — ' + custName(c) : '') + ' — AdaptExec';
	}
	function bxPrintDoc(x) {
		var fr = document.querySelector('.bx-frame iframe'), w = null;
		try { w = fr && fr.contentWindow && fr.contentWindow.document && fr.contentWindow.document.readyState === 'complete' ? fr.contentWindow : null; } catch (e) { w = null; }
		if (w) {
			var old = document.title; document.title = bxDocTitle(x);
			try { w.focus(); w.print(); } finally { setTimeout(function () { document.title = old; }, 1500); }
			return;
		}
		var nw = window.open(x.url, '_blank');
		if (nw) nw.addEventListener('load', function () { setTimeout(function () { nw.print(); }, 600); });
		else toast('Allow pop-ups to download the PDF.', 'bad');
	}
	document.addEventListener('keydown', function (e) {
		if ((e.metaKey || e.ctrlKey) && !e.shiftKey && !e.altKey && (e.key === 'p' || e.key === 'P') && B.cur && /^#\/billing\/doc\//.test(location.hash) && document.querySelector('.bx-docgrid .bx-frame iframe')) {
			e.preventDefault(); bxPrintDoc(B.cur.doc);
		}
	});

	/* --------------------------------------------------------- doc view */

	function viewBillDoc(d) {
		var x = d.doc, mgr = S.boot.user.manager, c = d.customer || {}, inv = x.type === 'invoice';
		var locked = x.status === 'accepted' || x.status === 'void' || (inv && x.amount_paid > 0);
		var decided = x.status === 'accepted' || x.status === 'declined';
		var acts = '<a class="axh-btn axh-btn--ghost" href="' + esc(x.url) + '" target="_blank" rel="noopener">' + icon('eye', 15) + ' Open page</a>' +
			'<button class="axh-btn axh-btn--ghost" data-bx-pdf="' + x.id + '">' + icon('download', 15) + ' Download PDF</button>' +
			'<button class="axh-btn axh-btn--ghost" data-bx-copy="' + esc(x.url) + '" data-id="' + x.id + '">' + icon('link', 15) + ' Copy link</button>';
		if (x.status !== 'void') acts += '<button class="axh-btn axh-btn--primary" data-bx-send="' + x.id + '">' + icon('send', 15) + (x.sent_at ? ' Send again' : ' Send to customer') + '</button>';
		if (mgr) {
			acts = (locked ? '' : '<a class="axh-btn axh-btn--ghost" href="#/billing/edit/' + x.id + '">' + icon('pen', 15) + ' Edit</a>') + acts +
				'<div class="bx-menu"><button class="axh-btn axh-btn--ghost" data-bx-menu aria-label="More actions">More</button><div class="bx-menu__pop" hidden>' +
				'<button data-bx-dup="' + x.id + '">Duplicate</button>' +
				(x.type !== 'invoice' ? '<button data-bx-dup="' + x.id + '" data-type="invoice">Copy as invoice</button>' : '<button data-bx-dup="' + x.id + '" data-type="estimate">Copy as estimate</button>') +
				(x.status === 'draft' ? '<button data-bx-status="sent">Mark as sent (no email)</button>' : '') +
				(!inv && !decided && x.status !== 'void' ? '<button data-bx-status="accepted">Mark accepted (signed offline)</button><button data-bx-status="declined">Mark declined</button>' : '') +
				(inv && x.balance > 0.004 && ['sent', 'viewed', 'partial'].indexOf(x.status) > -1 ? '<button data-bx-remind="' + x.id + '">Send payment reminder</button>' : '') +
				'<button data-bx-print="' + esc(x.url) + '">Download PDF</button>' +
				'<button data-bx-relink="regenerate">Regenerate customer link</button>' +
				(x.status === 'void' ? '<button data-bx-status="draft">Reopen as draft</button>' : '<button data-bx-status="void" class="bx-danger">Void…</button>') +
				(x.status === 'draft' && !x.sent_at ? '<button data-bx-del="' + x.id + '" class="bx-danger">Delete draft</button>' : '') +
				'</div></div>';
		} else if (inv && x.balance > 0.004 && ['sent', 'viewed', 'partial'].indexOf(x.status) > -1) {
			acts += '<button class="axh-btn axh-btn--ghost" data-bx-remind="' + x.id + '">' + icon('clock', 15) + ' Send reminder</button>';
		}
		var head = (mgr ? '<a class="axh-btn axh-btn--quiet axh-btn--sm bx-back" href="#/billing/docs">' + icon('back', 14) + ' Documents</a>' : '<a class="axh-btn axh-btn--quiet axh-btn--sm bx-back" href="#/billing">' + icon('back', 14) + ' Client billing</a>') +
			'<div class="bx-dochead"><div class="bx-dochead__id">' + btype(x.type) + bpill(x.display_status) + '<h1 class="thunder">' + esc(x.number) + (x.title ? ' <span>' + esc(x.title) + '</span>' : '') + '</h1>' +
			'<p>' + (c.id ? (mgr ? '<a href="#/billing/customer/' + c.id + '">' + esc(custName(c)) + '</a>' : '<a href="#/billing/customer/' + c.id + '">' + esc(custName(c)) + '</a>') : '') + (x.rep_name ? ' · Rep: ' + esc(x.rep_name) : '') + (d.parent ? ' · From <a href="#/billing/doc/' + d.parent.id + '">' + esc(d.parent.number) + '</a>' : '') + '</p></div>' +
			'<div class="axh-actions bx-dochead__acts">' + acts + '</div></div>';
		var amt = inv ? (x.status === 'paid' ? 'Paid in full' : 'Balance due') : 'Total';
		var phs = x.status === 'void' || decided || x.status === 'paid' ? [] : bxPh(x);
		var side = (phs.length ? '<div class="axh-card bx-card bx-phcard"><h3>' + icon('flag', 16) + ' ' + phs.length + ' unfilled placeholder' + (phs.length === 1 ? '' : 's') + '</h3><p>Template blanks in [square brackets] are still in this ' + x.type + '. They are highlighted on the customer page and in the PDF.</p>' + bxPhList(phs) +
			(mgr && !locked ? '<a class="axh-btn axh-btn--primary axh-btn--sm" href="#/billing/edit/' + x.id + '">' + icon('pen', 13) + ' Fill them in</a>' : '') + '</div>' : '') +
			'<div class="axh-card bx-card bx-sum"><div class="bx-sum__big"><span>' + amt + '</span><b class="thunder">' + bm(inv && x.status !== 'paid' ? Math.max(0, x.balance) : x.total) + '</b></div><dl class="bx-dl">' +
			(inv ? '<dt>Total</dt><dd>' + bm(x.total) + '</dd><dt>Paid</dt><dd>' + bm(x.amount_paid) + '</dd><dt>Due</dt><dd>' + bdate(x.due_date) + '</dd>' : '<dt>Valid until</dt><dd>' + bdate(x.expiry_date) + '</dd>') +
			'<dt>Issued</dt><dd>' + bdate(x.issue_date) + '</dd><dt>Sent</dt><dd>' + (x.sent_at ? when(x.sent_at) : 'Not yet') + '</dd>' +
			'<dt>Viewed</dt><dd>' + (x.view_count ? x.view_count + '× · last ' + when(x.last_viewed_at) : 'Not yet') + '</dd>' +
			(inv ? '<dt>Commission</dt><dd>' + esc(BLEG[x.milestone || '']) + '</dd>' : '') +
			(x.accepted_at ? '<dt>Accepted</dt><dd>' + esc(x.accepted_name) + ' · ' + when(x.accepted_at) + '</dd>' : '') +
			(x.declined_at ? '<dt>Declined</dt><dd>' + when(x.declined_at) + (x.decline_reason ? '<br><i>' + esc(x.decline_reason) + '</i>' : '') + '</dd>' : '') +
			'</dl></div>';
		if (!inv && x.status === 'accepted' && mgr) {
			var sch = x.content.schedule || [];
			side += '<div class="axh-card bx-card bx-callout"><h3>' + icon('receipt', 16) + ' Next: invoice it</h3>' + (d.children.length
				? '<p>Converted to:</p><ul class="bx-mini">' + d.children.map(function (k) { return '<li><a href="#/billing/doc/' + k.id + '"><b>' + esc(k.number) + '</b> ' + esc(BLEG[k.milestone] || '') + '</a>' + bpill(k.status) + '<b>' + bm(k.total) + '</b></li>'; }).join('') + '</ul>'
				: '<p>' + (sch.length ? 'Creates ' + sch.map(function (s) { return esc(s.label) + ' (' + s.pct + '%) ' + bm(Math.round(x.total * s.pct) / 100); }).join(' + ') + ', each tied to the matching commission half.' : 'Creates one invoice for the full amount.') + '</p><button class="axh-btn axh-btn--primary axh-btn--block" data-bx-convert="' + x.id + '">Convert to invoice' + (sch.length > 1 ? 's' : '') + '</button>') +
				(d.lead && d.lead.stage !== 'won' ? '<p class="axh-hint" style="margin-top:12px">The deal is not Closed Won yet. <button class="axh-linkbtn" data-close-deal="' + d.lead.id + '" data-value="' + x.total + '">Mark Closed Won at ' + bm0(x.total) + '</button> so commission starts tracking.</p>' : '') + '</div>';
		}
		if (inv) side += payCard(d);
		if (inv && x.milestone && d.lead) {
			var legs = x.milestone === 'full' ? ['deposit', 'final'] : [x.milestone];
			var on = legs.every(function (lg) { return d.lead[lg + '_at']; });
			side += '<div class="axh-card bx-card"><div class="bx-card__h"><div><h3>Commission link</h3></div></div><p class="bx-p">Pays for the <b>' + esc(BLEG[x.milestone].toLowerCase()) + '</b> of the commission on <a href="#/lead/' + d.lead.id + '">' + esc(d.lead.company) + '</a>' +
				(d.lead.stage === 'won' ? ' (' + bm0(d.lead.contract_value) + ' at ' + d.lead.commission_rate + '%)' : ' (not Closed Won yet)') + '.</p>' +
				'<p class="bx-p">' + (on ? '<span class="bx-ok">' + icon('check', 13) + ' Customer payment cleared</span> — that half is payable.' : 'Becomes payable when this invoice is paid in full and every payment on it has cleared.') + '</p></div>';
		}
		if (mgr) side += '<div class="axh-card bx-card"><div class="bx-card__h"><div><h3>Internal notes</h3><span>Only AdaptExec sees these</span></div></div><textarea class="axh-input bx-notes" data-bx-inotes="' + x.id + '" rows="3" placeholder="Context for your team…">' + esc(x.internal_notes || '') + '</textarea></div>';
		var feed = '<div class="axh-card bx-card"><div class="bx-card__h"><div><h3>Activity & send log</h3><span>Who did what, and when</span></div></div>' + ((d.events || []).length ? '<ul class="bx-feed">' + d.events.map(function (e) { return feedItem(Object.assign({}, e, { number: '', doc_id: 0 })); }).join('') + '</ul>' : '<p class="axh-hint">No activity yet.</p>') + '</div>';
		return '<div class="bx">' + head + '<div class="bx-print-note"><b>' + esc(x.number) + '</b> — this printout is the Hub screen, not the document. Use <b>Download PDF</b> (or Ctrl/⌘ + P) on the document page to save the full ' + esc(x.type) + ' as a PDF.</div><div class="bx-docgrid"><div class="bx-frame"><div class="bx-frame__bar"><i></i><i></i><i></i><span>' + esc(x.url.replace(/^https?:\/\//, '').replace(/[a-f0-9]{40}\/?$/, '…')) + '</span><a href="' + esc(x.url) + '" target="_blank" rel="noopener">' + icon('eye', 13) + ' Full page</a></div><iframe title="Customer page preview" src="' + esc(x.url) + '" loading="lazy"></iframe></div>' +
			'<div class="bx-side">' + side + feed + '</div></div></div>';
	}
	function payCard(d) {
		var x = d.doc, mgr = S.boot.user.manager, ps = d.payments || [];
		var h = '<div class="axh-card bx-card"><div class="bx-card__h"><div><h3>Payments</h3><span>' + (ps.filter(function (p) { return !p.voided_at; }).length || 'None') + ' recorded</span></div>' +
			(mgr && x.status !== 'void' ? '<button class="axh-btn axh-btn--primary axh-btn--sm" data-bx-pay="' + x.id + '">' + icon('plus', 13) + ' Record payment</button>' : '') + '</div>';
		if (mgr && d.credit > 0 && x.balance > 0.004) h += '<div class="bx-credit">' + icon('money', 14) + ' <span>' + esc(custName(d.customer)) + ' has <b>' + bm(d.credit) + '</b> credit.</span><button class="axh-linkbtn" data-bx-credit="' + x.id + '">Apply it</button></div>';
		if (ps.length) {
			h += '<ul class="bx-pays">' + ps.map(function (p) {
				var lab = p.kind === 'refund' ? 'Refund' : p.kind === 'credit' ? 'Credit applied' : p.method_label;
				return '<li class="' + (p.voided_at ? 'is-void' : '') + '"><div><b>' + (p.kind === 'refund' ? '−' : '') + bm(p.amount) + '</b> <span>' + esc(lab) + (p.reference ? ' · ' + esc(p.reference) : '') + '</span>' +
					'<em>' + bdate(p.received_at) + (p.voided_at ? ' · voided' : '') + (p.credit > 0 && p.kind === 'payment' ? ' · ' + bm(p.credit) + ' to credit' : '') + '</em></div>' +
					(p.kind === 'payment' && !p.voided_at ? (p.cleared ? '<span class="bx-chip bx-chip--ok">' + icon('check', 12) + ' Cleared ' + bdate(p.cleared_at, false) + '</span>' : '<span class="bx-chip bx-chip--warn">Not cleared</span>') : '') +
					(mgr && !p.voided_at ? '<div class="bx-pays__acts">' + (p.kind === 'payment' ? (p.cleared ? '<button class="axh-linkbtn" data-bx-pact="unclear" data-pid="' + p.id + '">Mark not cleared</button>' : '<button class="axh-linkbtn" data-bx-pact="clear" data-pid="' + p.id + '">Mark cleared</button>') + '<button class="axh-linkbtn" data-bx-pact="refund" data-pid="' + p.id + '">Refund</button>' : '') + '<button class="axh-linkbtn bx-danger" data-bx-pact="void" data-pid="' + p.id + '">Void</button></div>' : '') + '</li>';
			}).join('') + '</ul>';
		} else h += '<p class="axh-hint">No payments yet. The customer page shows them how to pay' + (B.boot.gateways && B.boot.gateways.length ? ' (card payments are on)' : ' — check, Zelle or the instructions in Billing settings') + '.</p>';
		return h + '</div>';
	}
	function afterBillDoc(d) {
		document.title = bxDocTitle(d.doc);
		var ta = document.querySelector('[data-bx-inotes]');
		if (ta) ta.onchange = function () { api('/billing/docs/' + d.doc.id, { method: 'POST', body: { internal_notes: ta.value } }).then(function () { toast('Note saved.', 'ok'); }).catch(bfail); };
	}

	function sendModal(id, reminder) {
		var d = B.cur && B.cur.doc.id === Number(id) ? B.cur : null;
		var go = function (d) {
			var x = d.doc, c = d.customer || {}, first = (c.contact_name || '').split(' ')[0];
			var msg = reminder ? 'Hi ' + (first || 'there') + ',\n\nA friendly reminder that invoice ' + x.number + ' (' + bm(x.balance) + ') was due ' + bdate(x.due_date) + '. The link below shows the balance and how to pay. If you have already sent it, thank you, and please ignore this.'
				: x.type === 'invoice' ? (x.amount_paid > 0 ? 'Here is invoice ' + x.number + '. Thank you for your payment so far; the remaining balance is ' + bm(x.balance) + '.' : 'Here is invoice ' + x.number + ' for ' + bm(x.total) + '.') + ' The link shows the details and how to pay.' + (x.due_date ? ' It is due ' + bdate(x.due_date) + '.' : '')
				: 'Your ' + x.type + ' is ready. It covers what we heard, what we will build and the investment. You can choose an option and accept online in a minute.';
			var m = modal(reminder ? 'Send a payment reminder' : 'Send ' + x.type + ' ' + esc(x.number),
				'<div class="axh-form-grid"><div class="axh-field axh-span"><label for="bx-to">To</label><input id="bx-to" type="email" data-sd="to" value="' + esc(c.email || '') + '" placeholder="customer@example.com"></div>' +
				'<div class="axh-field axh-span"><label for="bx-cc">Cc (optional)</label><input id="bx-cc" type="text" data-sd="cc" value="' + esc(c.cc_emails || '') + '" placeholder="Comma-separated"></div>' +
				'<div class="axh-field axh-span"><label for="bx-msg">Message</label><textarea id="bx-msg" data-sd="message" rows="6">' + esc(msg) + '</textarea></div></div>' +
				'<div class="bx-mailprev">' + icon('mail', 15) + '<div><b>The customer gets a branded email</b><span>with a big “' + (x.type === 'invoice' ? 'View invoice &amp; how to pay' : 'Review your ' + x.type) + '” button to their private page. ' + (S.boot.user.manager ? 'Replies go to your reply-to address.' : 'Replies come to you, and the send is logged under your name.') + '</span></div></div>',
				'<button class="axh-btn axh-btn--quiet" data-close>Cancel</button><button class="axh-btn axh-btn--primary" data-sd-go>' + icon('send', 14) + ' Send</button>');
			m.querySelector('[data-sd-go]').onclick = function () {
				var b = { kind: reminder ? 'reminder' : 'send' }, btn = this;
				m.querySelectorAll('[data-sd]').forEach(function (i) { b[i.getAttribute('data-sd')] = i.value; });
				if (!/^\S+@\S+\.\S+$/.test(b.to)) { toast('Enter the customer’s email address.', 'bad'); return; }
				if (!reminder && !(Number(x.total) > 0) && !window.confirm('This ' + x.type + ' totals ' + bm(x.total || 0) + '. Send it anyway?')) return;
				btn.disabled = true; btn.innerHTML = '<span class="axh-spin"></span> Sending…';
				api('/billing/docs/' + id + '/send', { method: 'POST', body: b }).then(function () { closeModal(); toast(reminder ? 'Reminder sent.' : 'Sent to ' + b.to + '.', 'ok'); route(); })
					.catch(function (e) { btn.disabled = false; btn.innerHTML = icon('send', 14) + ' Send'; bfail(e); });
			};
		};
		var gate = function (d) { if (reminder) go(d); else bxPhGate(d.doc, 'Send', function () { go(d); }); };
		if (d) gate(d); else api('/billing/docs/' + id).then(function (r) { B.cur = r; gate(r); }).catch(bfail);
	}

	function payModal(id) {
		var x = B.cur.doc, mt = B.boot.methods || {};
		var m = modal('Record a payment',
			'<p class="axh-hint" style="margin-bottom:12px"><b>' + esc(x.number) + '</b> · balance ' + bm(x.balance) + '</p><div class="axh-form-grid">' +
			'<div class="axh-field"><label for="bx-pa">Amount ($)</label><input id="bx-pa" type="text" inputmode="decimal" data-pm="amount" value="' + (x.balance > 0 ? x.balance.toFixed(2) : '') + '"></div>' +
			'<div class="axh-field"><label for="bx-pd">Date received</label><input id="bx-pd" type="date" data-pm="received_at" value="' + today() + '" max="' + today() + '"></div>' +
			'<div class="axh-field"><label for="bx-pm">Method</label><select id="bx-pm" data-pm="method">' + Object.keys(mt).map(function (k) { return '<option value="' + k + '">' + esc(mt[k]) + '</option>'; }).join('') + '</select></div>' +
			'<div class="axh-field"><label for="bx-pr">Reference</label><input id="bx-pr" type="text" data-pm="reference" placeholder="Check #, Zelle confirmation…"></div>' +
			'<div class="axh-field axh-span bx-clear"><label class="axh-check"><input type="checkbox" data-pm-cleared> Funds have cleared</label><input type="date" data-pm="cleared_at" value="' + today() + '" max="' + today() + '" aria-label="Date cleared"></div>' +
			'<div class="axh-field axh-span"><label for="bx-pn">Note (internal)</label><input id="bx-pn" type="text" data-pm="note"></div></div>' +
			'<p class="axh-hint" data-pm-hint></p>',
			'<button class="axh-btn axh-btn--quiet" data-close>Cancel</button><button class="axh-btn axh-btn--primary" data-pm-go>Record payment</button>');
		var meth = m.querySelector('[data-pm="method"]'), clr = m.querySelector('[data-pm-cleared]'), amt = m.querySelector('[data-pm="amount"]'), hint = m.querySelector('[data-pm-hint]');
		var sync = function () {
			var a = Number(String(amt.value).replace(/[^0-9.]/g, '')) || 0, over = a - x.balance;
			hint.innerHTML = (over > 0.004 ? icon('money', 13) + ' ' + bm(over) + ' over the balance is kept as credit on the customer’s account. ' : '') +
				(clr.checked ? '' : 'Commission tied to this invoice stays pending until you mark the payment cleared.');
			m.querySelector('[data-pm="cleared_at"]').hidden = !clr.checked;
		};
		meth.onchange = function () { clr.checked = meth.value !== 'check'; sync(); };
		clr.onchange = sync; amt.oninput = sync;
		clr.checked = meth.value !== 'check'; sync();
		m.querySelector('[data-pm-go]').onclick = function () {
			var b = { doc_id: x.id, cleared: clr.checked }, btn = this;
			m.querySelectorAll('[data-pm]').forEach(function (i) { b[i.getAttribute('data-pm')] = i.value; });
			if (!(Number(String(b.amount).replace(/[^0-9.]/g, '')) > 0)) { toast('Enter the amount received.', 'bad'); return; }
			btn.disabled = true;
			api('/billing/payments', { method: 'POST', body: b }).then(function () { closeModal(); toast('Payment recorded.', 'ok'); route(); }).catch(function (e) { btn.disabled = false; bfail(e); });
		};
	}
	function payAction(pid, act) {
		if (act === 'clear') {
			var m = modal('Mark payment cleared', '<p class="axh-hint" style="margin-bottom:12px">For a check, the day your bank shows the funds as final, not the day you deposited it. Commission tied to this invoice becomes payable once it is paid in full and cleared.</p><div class="axh-field"><label for="bx-cd">Date cleared</label><input id="bx-cd" type="date" value="' + today() + '" max="' + today() + '"></div>',
				'<button class="axh-btn axh-btn--quiet" data-close>Cancel</button><button class="axh-btn axh-btn--primary" data-go>Mark cleared</button>');
			m.querySelector('[data-go]').onclick = function () { api('/billing/payments/' + pid, { method: 'POST', body: { action: 'clear', date: m.querySelector('#bx-cd').value } }).then(function () { closeModal(); toast('Marked cleared.', 'ok'); route(); }).catch(bfail); };
			return;
		}
		if (act === 'refund') {
			var p = (B.cur.payments || []).filter(function (x) { return String(x.id) === String(pid); })[0] || {};
			var m2 = modal('Record a refund', '<div class="axh-form-grid"><div class="axh-field"><label for="bx-ra">Amount ($)</label><input id="bx-ra" type="text" inputmode="decimal" data-rf="amount" value="' + (p.amount || '') + '"></div>' +
				'<div class="axh-field"><label for="bx-rd">Date</label><input id="bx-rd" type="date" data-rf="refunded_at" value="' + today() + '"></div>' +
				'<div class="axh-field axh-span"><label for="bx-rr">Reference</label><input id="bx-rr" type="text" data-rf="reference"></div>' +
				'<div class="axh-field axh-span"><label for="bx-rn">Note</label><input id="bx-rn" type="text" data-rf="note"></div></div>' +
				'<p class="axh-hint">If the rep’s commission on this half was already paid, a clawback opens automatically and is deducted from their next payout batch.</p>',
				'<button class="axh-btn axh-btn--quiet" data-close>Cancel</button><button class="axh-btn axh-btn--primary" data-go>Record refund</button>');
			m2.querySelector('[data-go]').onclick = function () { var b = { action: 'refund' }; m2.querySelectorAll('[data-rf]').forEach(function (i) { b[i.getAttribute('data-rf')] = i.value; }); api('/billing/payments/' + pid, { method: 'POST', body: b }).then(function () { closeModal(); toast('Refund recorded.', 'ok'); route(); }).catch(bfail); };
			return;
		}
		if (act === 'void') {
			var m3 = modal('Void this entry?', '<p class="axh-hint" style="margin-bottom:12px">Use this for a payment recorded by mistake (a bounced check, a typo). It stays on record, marked void, and the invoice balance goes back up.</p><div class="axh-field"><label for="bx-vr">Reason</label><input id="bx-vr" type="text" placeholder="Check bounced"></div>',
				'<button class="axh-btn axh-btn--quiet" data-close>Cancel</button><button class="axh-btn axh-btn--danger" data-go>Void it</button>');
			m3.querySelector('[data-go]').onclick = function () { api('/billing/payments/' + pid, { method: 'POST', body: { action: 'void', reason: m3.querySelector('#bx-vr').value } }).then(function () { closeModal(); toast('Voided.', 'ok'); route(); }).catch(bfail); };
			return;
		}
		api('/billing/payments/' + pid, { method: 'POST', body: { action: act } }).then(function () { toast('Updated.', 'ok'); route(); }).catch(bfail);
	}
	function statusModal(status) {
		var x = B.cur.doc;
		if (status === 'sent') { bxPhGate(x, 'Mark as sent', function () { api('/billing/docs/' + x.id + '/status', { method: 'POST', body: { status: status } }).then(function () { toast('Updated.', 'ok'); route(); }).catch(bfail); }); return; }
		if (status === 'draft') { api('/billing/docs/' + x.id + '/status', { method: 'POST', body: { status: status } }).then(function () { toast('Updated.', 'ok'); route(); }).catch(bfail); return; }
		var title = { void: 'Void ' + x.number + '?', accepted: 'Mark accepted', declined: 'Mark declined' }[status];
		var help = { void: 'The document stays on record, marked void, and its link shows it was withdrawn.', accepted: 'For a ' + x.type + ' the customer accepted outside the page (signed paper, email). It locks the document so you can convert it to invoices.', declined: 'Record that the customer said no.' }[status];
		var m = modal(title, '<p class="axh-hint" style="margin-bottom:12px">' + help + '</p><div class="axh-field"><label for="bx-sn">' + (status === 'accepted' ? 'Who accepted, and how' : 'Reason') + '</label><input id="bx-sn" type="text" placeholder="' + (status === 'accepted' ? 'Jane Doe, signed by email' : '') + '"></div>',
			'<button class="axh-btn axh-btn--quiet" data-close>Cancel</button><button class="axh-btn ' + (status === 'void' ? 'axh-btn--danger' : 'axh-btn--primary') + '" data-go>Confirm</button>');
		m.querySelector('[data-go]').onclick = function () { api('/billing/docs/' + x.id + '/status', { method: 'POST', body: { status: status, note: m.querySelector('#bx-sn').value } }).then(function () { closeModal(); toast('Updated.', 'ok'); route(); }).catch(bfail); };
	}

	/* --------------------------------------------------------- customers */

	function viewBillCustomers() {
		var mgr = S.boot.user.manager, cs = B.customers || [];
		var html = bhead('Customers', 'Who you bill: balances, lifetime totals and every document, QuickBooks-style.', mgr ? '<button class="axh-btn axh-btn--ghost" data-bx-export="customers" data-fmt="csv">' + icon('download', 15) + ' Export</button><button class="axh-btn axh-btn--primary" data-bx-newcust>' + icon('plus', 15) + ' New customer</button>' : '');
		html += '<div class="bx-filters"><input type="search" class="axh-input" placeholder="Search company, contact, email or phone" data-cf-q value="' + esc(B.cf.q) + '" aria-label="Search customers"></div>';
		if (!cs.length) return '<div class="bx">' + html + '<div class="axh-card">' + bempty(B.cf.q ? 'No matches' : 'No customers yet', 'Add one here, or open any lead and choose <b>Create billing customer</b> — it fills in from the lead in one click.', mgr && !B.cf.q ? '<button class="axh-btn axh-btn--primary" data-bx-newcust>' + icon('plus', 15) + ' New customer</button>' : '') + '</div></div>';
		var t = { bal: 0, billed: 0 };
		cs.forEach(function (c) { t.bal += c.money.balance; t.billed += c.money.billed; });
		html += '<div class="axh-card bx-card bx-card--flush"><table class="axh-table axh-table--stack"><thead><tr><th>Customer</th><th>Rep</th><th class="num">Open balance</th><th class="num">Overdue</th><th class="num">Billed</th><th class="num">Paid</th><th>Last activity</th></tr></thead><tbody>' +
			cs.map(function (c) {
				return '<tr data-href="#/billing/customer/' + c.id + '"><td data-th="Customer"><div class="bx-cust"><span class="bx-av">' + esc(initials(custName(c))) + '</span><div><b class="bx-strong">' + esc(custName(c)) + '</b><span class="bx-sub">' + esc([c.company ? c.contact_name : '', c.email].filter(Boolean).join(' · ')) + '</span></div></div></td>' +
					'<td data-th="Rep">' + esc(c.rep_name || 'House') + '</td><td data-th="Open balance" class="num">' + (c.money.balance > 0 ? '<b>' + bm(c.money.balance) + '</b>' : '—') + '</td>' +
					'<td data-th="Overdue" class="num">' + (c.money.overdue > 0 ? '<b class="bx-bad">' + bm(c.money.overdue) + '</b>' : '—') + '</td><td data-th="Billed" class="num">' + bm(c.money.billed) + '</td><td data-th="Paid" class="num">' + bm(c.money.paid) + '</td><td data-th="Last activity">' + when(c.last) + '</td></tr>';
			}).join('') + '</tbody><tfoot><tr><td colspan="2">' + cs.length + ' customer' + (cs.length === 1 ? '' : 's') + '</td><td class="num"><b>' + bm(t.bal) + '</b></td><td></td><td class="num"><b>' + bm(t.billed) + '</b></td><td colspan="2"></td></tr></tfoot></table></div>';
		return '<div class="bx">' + html + '</div>';
	}
	function viewBillCustomer(d) {
		var c = d.customer, m = c.money, mgr = S.boot.user.manager;
		var addr = [c.addr1, c.addr2, [c.city, c.state].filter(Boolean).join(', ') + (c.zip ? ' ' + c.zip : '')].filter(function (x) { return String(x).trim(); });
		var html = '<a class="axh-btn axh-btn--quiet axh-btn--sm bx-back" href="#/billing/' + (mgr ? 'customers' : '') + '">' + icon('back', 14) + (mgr ? ' Customers' : ' Client billing') + '</a>' +
			'<div class="bx-custhead"><span class="bx-av bx-av--lg">' + esc(initials(custName(c))) + '</span><div><h1 class="thunder">' + esc(custName(c)) + '</h1><p>' +
			[c.company && c.contact_name ? esc(c.contact_name) : '', c.email ? '<a href="mailto:' + esc(c.email) + '">' + esc(c.email) + '</a>' : '', c.phone ? '<a href="tel:' + esc(c.phone) + '">' + esc(c.phone) + '</a>' : '', c.rep_name ? 'Rep: ' + esc(c.rep_name) : 'House account', d.lead ? '<a href="#/lead/' + d.lead.id + '">' + icon('board', 12) + ' Lead in pipeline</a>' : ''].filter(Boolean).join(' · ') + '</p>' +
			(addr.length ? '<p class="bx-sub">' + addr.map(esc).join(', ') + '</p>' : '') + '</div>' +
			(mgr ? '<div class="axh-actions"><button class="axh-btn axh-btn--ghost" data-bx-editcust="' + c.id + '">' + icon('pen', 14) + ' Edit</button><button class="axh-btn axh-btn--ghost" data-bx-new="invoice" data-cust="' + c.id + '">' + icon('plus', 14) + ' Invoice</button><button class="axh-btn axh-btn--ghost" data-bx-new="estimate" data-cust="' + c.id + '">' + icon('plus', 14) + ' Estimate</button><button class="axh-btn axh-btn--primary" data-bx-new="proposal" data-cust="' + c.id + '">' + icon('plus', 14) + ' Proposal</button></div>' : '') + '</div>';
		html += '<div class="bx-kpis bx-kpis--6">' + bstat('Balance due', bm(m.balance), (m.open || 0) + ' open invoice' + (m.open === 1 ? '' : 's'), m.balance > 0 ? 'accent' : '') + bstat('Overdue', bm(m.overdue), m.overdue > 0 ? 'Past the due date' : 'Nothing late', m.overdue > 0 ? 'bad' : '') +
			bstat('Lifetime billed', bm(m.billed), '') + bstat('Lifetime paid', bm(m.paid), '') + bstat('Credit', bm(m.credit), m.credit > 0 ? 'Apply it on an open invoice' : 'None') +
			bstat('Avg. days to pay', d.avg_days === null ? '—' : d.avg_days, 'Paid invoices') + '</div>';
		var docs = d.docs || [];
		html += '<div class="bx-grid2"><div class="axh-card bx-card bx-card--flush"><div class="bx-card__h bx-pad"><div><h3>Documents</h3><span>' + docs.length + ' total</span></div></div>' + (docs.length ? '<table class="axh-table axh-table--stack"><thead><tr><th>Number</th><th>Status</th><th>Date</th><th class="num">Total</th><th class="num">Balance</th></tr></thead><tbody>' +
			docs.map(function (x) { return '<tr data-href="#/billing/doc/' + x.id + '"><td data-th="Number"><div class="bx-num">' + btype(x.type) + '<b>' + esc(x.number) + '</b></div><span class="bx-sub">' + esc(x.title || '') + '</span></td><td data-th="Status">' + bpill(x.display_status) + '</td><td data-th="Date">' + bdate(x.issue_date) + '</td><td data-th="Total" class="num">' + bm(x.total) + '</td><td data-th="Balance" class="num">' + (x.type === 'invoice' ? bm(x.balance) : '—') + '</td></tr>'; }).join('') + '</tbody></table>' : '<p class="axh-hint bx-pad">No documents yet.</p>') + '</div>' +
			'<div><div class="axh-card bx-card"><div class="bx-card__h"><div><h3>Payments</h3></div></div>' + ((d.payments || []).length ? '<ul class="bx-pays">' + d.payments.map(function (p) { return '<li class="' + (p.voided_at ? 'is-void' : '') + '"><div><b>' + (p.kind === 'refund' ? '−' : '') + bm(p.amount) + '</b> <span>' + esc(p.kind === 'payment' ? p.method_label : p.kind) + ' · <a href="#/billing/doc/' + p.doc_id + '">' + esc(p.number) + '</a></span><em>' + bdate(p.received_at) + (p.voided_at ? ' · voided' : '') + '</em></div>' + (p.kind === 'payment' && !p.voided_at ? (p.cleared ? '<span class="bx-chip bx-chip--ok">Cleared</span>' : '<span class="bx-chip bx-chip--warn">Not cleared</span>') : '') + '</li>'; }).join('') + '</ul>' : '<p class="axh-hint">No payments yet.</p>') +
			((d.legacy || []).length ? '<div class="bx-legacy"><b>Recorded on the deal before Billing</b>' + d.legacy.map(function (l) { return '<span>' + esc(l.label) + ' · ' + bdate(l.at) + '</span>'; }).join('') + '</div>' : '') + '</div>' +
			(mgr && c.notes ? '<div class="axh-card bx-card"><div class="bx-card__h"><div><h3>Notes</h3><span>Internal</span></div></div><p class="bx-p" style="white-space:pre-wrap">' + esc(c.notes) + '</p></div>' : '') +
			'<div class="axh-card bx-card"><div class="bx-card__h"><div><h3>Timeline</h3></div></div>' + ((d.timeline || []).length ? '<ul class="bx-feed">' + d.timeline.slice(0, 30).map(feedItem).join('') + '</ul>' : '<p class="axh-hint">Nothing yet.</p>') + '</div></div></div>';
		return '<div class="bx">' + html + '</div>';
	}
	function customerModal(c, done) {
		c = c || {};
		var f = function (k, label, type, span, ph) { return '<div class="axh-field' + (span ? ' axh-span' : '') + '"><label for="bx-c-' + k + '">' + label + '</label><input id="bx-c-' + k + '" type="' + (type || 'text') + '" data-cu="' + k + '" value="' + esc(c[k] || '') + '"' + (ph ? ' placeholder="' + esc(ph) + '"' : '') + '></div>'; };
		var m = modal(c.id ? 'Edit customer' : 'New customer',
			'<div class="axh-form-grid">' + f('company', 'Company') + f('contact_name', 'Contact name') + f('email', 'Email', 'email') + f('phone', 'Phone', 'tel') +
			f('cc_emails', 'Also email (cc)', 'text', true, 'Comma-separated, optional') + f('addr1', 'Billing address', 'text', true, 'Street') + f('addr2', 'Address line 2', 'text', true) + f('city', 'City') + f('state', 'State') + f('zip', 'ZIP') +
			'<div class="axh-field"><label for="bx-c-rep">Rep</label>' + repSelect('id="bx-c-rep" data-cu="rep_id"', c.rep_id || 0) + '</div>' +
			'<div class="axh-field axh-span"><label for="bx-c-notes">Notes (internal)</label><textarea id="bx-c-notes" data-cu="notes" rows="3">' + esc(c.notes || '') + '</textarea></div>' +
			f('external_id', 'QuickBooks customer ID', 'text', true, 'Filled in when synced; leave blank') + '</div>' +
			(c.lead_id ? '<p class="axh-hint">Linked to a lead in the pipeline; the rep follows that lead.</p>' : ''),
			'<button class="axh-btn axh-btn--quiet" data-close>Cancel</button><button class="axh-btn axh-btn--primary" data-cu-go>' + (c.id ? 'Save' : 'Add customer') + '</button>');
		m.querySelector('[data-cu-go]').onclick = function () {
			var b = {}, btn = this;
			m.querySelectorAll('[data-cu]').forEach(function (i) { b[i.getAttribute('data-cu')] = i.value; });
			if (c.lead_id) delete b.rep_id;
			if (!b.company.trim() && !b.contact_name.trim()) { toast('Enter a company or a contact name.', 'bad'); return; }
			btn.disabled = true;
			api('/billing/customers' + (c.id ? '/' + c.id : ''), { method: 'POST', body: b }).then(function (r) { closeModal(); toast(c.id ? 'Saved.' : 'Customer added.', 'ok'); if (done) done(r); else route(); }).catch(function (e) { btn.disabled = false; bfail(e); });
		};
	}

	/* On a lead: its billing customer and documents. */
	function billLeadCard(leadId) {
		var box = document.querySelector('[data-bill-lead="' + leadId + '"]');
		if (!box) return;
		var mgr = S.boot.user.manager;
		api('/billing/docs?lead_id=' + leadId).then(function (d) {
			var docs = d.docs || [];
			if (!docs.length && !mgr) { box.remove(); return; }
			box.innerHTML = '<div class="axh-card bx-card bx-leadcard"><div class="bx-card__h"><div><h3>' + icon('receipt', 16) + ' Billing</h3><span>Proposals, estimates and invoices for this client</span></div>' +
				(mgr ? '<div class="bx-row"><button class="axh-btn axh-btn--ghost axh-btn--sm" data-bx-fromlead="' + leadId + '" data-then="invoice">Invoice</button><button class="axh-btn axh-btn--primary axh-btn--sm" data-bx-fromlead="' + leadId + '" data-then="proposal">' + icon('plus', 13) + ' Proposal</button></div>' : '') + '</div>' +
				(docs.length ? '<ul class="bx-mini">' + docs.map(function (x) { return '<li><a href="#/billing/doc/' + x.id + '">' + btype(x.type) + ' <b>' + esc(x.number) + '</b> ' + esc(x.title || '') + '</a>' + bpill(x.display_status) + '<b>' + bm(x.total) + '</b><button class="axh-btn axh-btn--quiet axh-btn--sm" data-bx-copy="' + esc(x.url) + '" data-id="' + x.id + '" aria-label="Copy link">' + icon('link', 13) + '</button></li>'; }).join('') + '</ul>'
					: '<p class="axh-hint">Nothing yet. <button class="axh-linkbtn" data-bx-fromlead="' + leadId + '" data-then="customer">Create billing customer</button> from this lead in one click, or start a proposal.</p>') + '</div>';
		}).catch(function () { box.remove(); });
	}

	/* ---------------------------------------------------------- payments */

	function billPaymentsLoad(render) {
		var f = B.pf, q = [];
		Object.keys(f).forEach(function (k) { if (f[k]) q.push(k + '=' + encodeURIComponent(f[k])); });
		return api('/billing/payments' + (q.length ? '?' + q.join('&') : '')).then(function (d) { B.pays = d.payments || []; render(viewBillPayments()); });
	}
	function viewBillPayments() {
		var ps = B.pays || [], f = B.pf, mt = B.boot.methods || {}, tot = 0, unc = 0;
		ps.forEach(function (p) { if (!p.voided_at) { tot += p.kind === 'refund' ? -p.amount : (p.kind === 'payment' ? p.amount : 0); if (p.kind === 'payment' && !p.cleared) unc += p.amount; } });
		var html = bhead('Payments', 'Money received, refunds and credits. Mark checks cleared once your bank shows the funds as final; commission waits for that.', '<button class="axh-btn axh-btn--ghost" data-bx-export="payments" data-fmt="csv">' + icon('download', 15) + ' Export</button>');
		html += '<div class="bx-kpis bx-kpis--3">' + bstat('Received (net of refunds)', bm(tot), ps.length + ' entries shown', 'accent') + bstat('Not cleared yet', bm(unc), unc > 0 ? 'Mostly checks in transit' : 'Everything has cleared', unc > 0 ? 'warn' : '') + bstat('Methods', Object.keys(ps.reduce(function (a, p) { a[p.method] = 1; return a; }, {})).length, 'Check, Zelle, ACH…') + '</div>';
		html += '<div class="bx-filters"><input type="search" class="axh-input" placeholder="Search customer, invoice or reference" data-pf-q value="' + esc(f.q) + '" aria-label="Search payments">' +
			'<select class="axh-input" data-pf="method" aria-label="Method"><option value="">Any method</option>' + Object.keys(mt).map(function (k) { return '<option value="' + k + '"' + (f.method === k ? ' selected' : '') + '>' + esc(mt[k]) + '</option>'; }).join('') + '</select>' +
			'<label class="axh-check bx-inline"><input type="checkbox" data-pf="uncleared"' + (f.uncleared ? ' checked' : '') + '> Not cleared only</label></div>';
		if (!ps.length) return '<div class="bx">' + html + '<div class="axh-card">' + bempty('No payments yet', 'Open an invoice and choose <b>Record payment</b> when money arrives.', '') + '</div></div>';
		html += '<div class="axh-card bx-card bx-card--flush"><table class="axh-table axh-table--stack"><thead><tr><th>Received</th><th>Customer</th><th>Invoice</th><th>Method</th><th class="num">Amount</th><th>Cleared</th><th>Recorded by</th></tr></thead><tbody>' +
			ps.map(function (p) {
				return '<tr class="' + (p.voided_at ? 'is-off' : '') + '" data-href="#/billing/doc/' + p.doc_id + '"><td data-th="Received">' + bdate(p.received_at) + (p.voided_at ? ' <span class="axh-badge axh-badge--gray">Void</span>' : '') + '</td><td data-th="Customer"><b class="bx-strong">' + esc(p.customer) + '</b></td><td data-th="Invoice">' + esc(p.number) + '</td>' +
					'<td data-th="Method">' + esc(p.kind === 'payment' ? p.method_label : (p.kind === 'refund' ? 'Refund · ' + p.method_label : 'Credit applied')) + (p.reference ? '<span class="bx-sub">' + esc(p.reference) + '</span>' : '') + '</td>' +
					'<td data-th="Amount" class="num"><b>' + (p.kind === 'refund' ? '−' : '') + bm(p.amount) + '</b>' + (p.credit > 0 && p.kind === 'payment' ? '<span class="bx-sub">' + bm(p.credit) + ' to credit</span>' : '') + '</td>' +
					'<td data-th="Cleared">' + (p.kind !== 'payment' || p.voided_at ? '—' : p.cleared ? '<span class="bx-chip bx-chip--ok">' + bdate(p.cleared_at, false) + '</span>' : '<button class="axh-btn axh-btn--ghost axh-btn--sm" data-bx-pact="clear" data-pid="' + p.id + '">Mark cleared</button>') + '</td><td data-th="By">' + esc(p.recorded_by) + '</td></tr>';
			}).join('') + '</tbody></table></div>';
		return '<div class="bx">' + html + '</div>';
	}

	/* ----------------------------------------------------------- catalog */

	function viewBillItems() {
		var it = B.items || [], cats = {};
		it.forEach(function (i) { (cats[i.category] = cats[i.category] || []).push(i); });
		var html = bhead('Products & services', 'Your catalog. Pick an item on any document and its description and rate fill in. Starter items are defaults: edit them to your real prices.', '<button class="axh-btn axh-btn--primary" data-bx-item="0">' + icon('plus', 15) + ' New item</button>');
		html += Object.keys(cats).map(function (cat) {
			return '<div class="bx-cat"><h3 class="bx-cat__h">' + esc(cat) + '<span>' + cats[cat].length + '</span></h3><div class="bx-items">' + cats[cat].map(function (i) {
				return '<button class="bx-item' + (i.active ? '' : ' is-off') + '" data-bx-item="' + i.id + '"><span class="bx-item__top"><b>' + esc(i.name) + '</b>' + (i.is_default ? '<span class="axh-badge axh-badge--orange">Starter default · edit me</span>' : '') + (i.active ? '' : '<span class="axh-badge axh-badge--gray">Hidden</span>') + '</span>' +
					'<span class="bx-item__d">' + esc(i.description) + '</span><span class="bx-item__p"><b>' + (i.rate ? bm0(i.rate) : 'Set a rate') + '</b> / ' + esc(i.unit) + (i.taxable ? ' · taxable' : '') + (i.qbo_item ? ' · QBO: ' + esc(i.qbo_item) : '') + '</span></button>';
			}).join('') + '</div></div>';
		}).join('');
		return '<div class="bx">' + html + '</div>';
	}
	function itemModal(id) {
		var i = (B.items || []).filter(function (x) { return String(x.id) === String(id); })[0] || { active: true, unit: 'project', category: 'Websites' };
		var m = modal(i.id ? 'Edit item' : 'New item',
			'<div class="axh-form-grid"><div class="axh-field axh-span"><label for="bx-i-n">Name</label><input id="bx-i-n" data-it="name" value="' + esc(i.name || '') + '"></div>' +
			'<div class="axh-field axh-span"><label for="bx-i-d">Description (shown on documents)</label><textarea id="bx-i-d" data-it="description" rows="3">' + esc(i.description || '') + '</textarea></div>' +
			'<div class="axh-field"><label for="bx-i-r">Default rate ($)</label><input id="bx-i-r" inputmode="decimal" data-it="rate" value="' + (i.rate || '') + '"></div>' +
			'<div class="axh-field"><label for="bx-i-u">Unit</label><select id="bx-i-u" data-it="unit">' + B.boot.units.map(function (u) { return '<option' + (u === i.unit ? ' selected' : '') + '>' + u + '</option>'; }).join('') + '</select></div>' +
			'<div class="axh-field"><label for="bx-i-c">Category</label><select id="bx-i-c" data-it="category">' + B.boot.categories.map(function (c) { return '<option' + (c === i.category ? ' selected' : '') + '>' + esc(c) + '</option>'; }).join('') + '</select></div>' +
			'<div class="axh-field"><label for="bx-i-q">QuickBooks item name</label><input id="bx-i-q" data-it="qbo_item" value="' + esc(i.qbo_item || '') + '" placeholder="Same as name"></div>' +
			'<div class="axh-field axh-span"><label class="axh-check"><input type="checkbox" data-it-b="taxable"' + (i.taxable ? ' checked' : '') + '> Taxable (when a document has tax switched on)</label><label class="axh-check"><input type="checkbox" data-it-b="active"' + (i.active ? ' checked' : '') + '> Show in the item picker</label></div></div>',
			'<button class="axh-btn axh-btn--quiet" data-close>Cancel</button><button class="axh-btn axh-btn--primary" data-go>Save item</button>');
		m.querySelector('[data-go]').onclick = function () {
			var b = {};
			m.querySelectorAll('[data-it]').forEach(function (x) { b[x.getAttribute('data-it')] = x.value; });
			m.querySelectorAll('[data-it-b]').forEach(function (x) { b[x.getAttribute('data-it-b')] = x.checked; });
			api('/billing/items' + (i.id ? '/' + i.id : ''), { method: 'POST', body: b }).then(function () { closeModal(); B.boot = null; toast('Saved.', 'ok'); route(); }).catch(bfail);
		};
	}

	/* ---------------------------------------------------------- settings */

	function viewBillSettings(d) {
		var s = d.settings, biz = d.business;
		var f = function (k, label, help, ph, type) { return '<div class="axh-field"><label for="bx-s-' + k + '">' + label + '</label>' + (help ? '<div class="axh-field__help">' + help + '</div>' : '') + '<input id="bx-s-' + k + '" type="' + (type || 'text') + '" data-bs="' + k + '" value="' + esc(s[k] === undefined ? '' : s[k]) + '"' + (ph ? ' placeholder="' + esc(ph) + '"' : '') + '></div>'; };
		var ta = function (k, label, help, rows, ph) { return '<div class="axh-field"><label for="bx-s-' + k + '">' + label + '</label>' + (help ? '<div class="axh-field__help">' + help + '</div>' : '') + '<textarea id="bx-s-' + k + '" data-bs="' + k + '" rows="' + (rows || 4) + '"' + (ph ? ' placeholder="' + esc(ph) + '"' : '') + '>' + esc(s[k] || '') + '</textarea></div>'; };
		var tog = function (k, label) { return '<label class="axh-check"><input type="checkbox" data-bs-b="' + k + '"' + (Number(s[k]) ? ' checked' : '') + '> ' + label + '</label>'; };
		var html = bhead('Billing settings', 'What appears on every proposal, estimate and invoice, how customers pay, and how documents are numbered.', '<a class="axh-btn axh-btn--ghost" href="#/billing/templates">' + icon('doc', 15) + ' Proposal templates</a><a class="axh-btn axh-btn--ghost" href="#/billing/items">' + icon('box', 15) + ' Products & services</a>');
		html += '<div class="bx-settings">' +
			'<section class="axh-card bx-card"><h3 class="bx-h3">Your business</h3>' +
			f('doc_name', 'Name on documents', 'Leave blank to use the legal name from Onboarding settings.', d.onboarding_name) +
			'<div class="axh-form-grid">' + f('email', 'Billing email', 'Shown on documents.', biz.email, 'email') + f('phone', 'Phone', '', biz.phone) + '</div>' +
			f('website', 'Website') +
			ta('address', 'Business mailing address', 'Printed on invoices and used for mailed checks. <b>Leave it empty until you have a business address</b> (a P.O. box or registered agent address works). Check instructions stay hidden until it is set.', 3, 'Empty: no address is shown') +
			'<div class="axh-form-grid">' + f('logo', 'Logo URL', 'Optional. Blank uses the AdaptExec logo.', 'https://…') + '<div class="axh-field"><label for="bx-s-accent">Brand accent</label><div class="axh-field__help">Buttons and highlights on customer pages.</div><input id="bx-s-accent" type="color" data-bs="accent" value="' + esc(s.accent || '#EF5D0F') + '"></div></div>' +
			'<button type="button" class="axh-btn axh-btn--ghost axh-btn--sm" data-bx-logo>' + icon('upload', 13) + ' Choose logo from the media library</button></section>' +
			'<section class="axh-card bx-card"><h3 class="bx-h3">How customers pay</h3><p class="axh-hint" style="margin-bottom:14px">Shown on every invoice under “How to pay”. Payments are recorded by hand; a card gateway (Stripe) can be added later without changing invoices.</p>' +
			f('check_payable', 'Make checks payable to', 'Defaults to the name on documents.', biz.name) +
			ta('pay_zelle', 'Zelle instructions', 'For example: “Send to billing@… (AdaptExec LLC). Put the invoice number in the memo.” Leave blank to hide.', 3) +
			ta('pay_other', 'Other ways to pay', 'ACH details, Venmo, in person… Leave blank to hide.', 3) + '</section>' +
			'<section class="axh-card bx-card"><h3 class="bx-h3">Defaults for new documents</h3>' +
			ta('notes_text', 'Note to the customer', '', 3) + ta('terms_text', 'Terms', 'One per line. Shown on proposals and estimates (Terms section) and invoices.', 6) +
			'<div class="axh-form-grid"><div class="axh-field"><label for="bx-s-due">Invoice terms</label><select id="bx-s-due" data-bs="due_terms">' + Object.keys(B.boot.terms).filter(function (k) { return k !== 'custom'; }).map(function (k) { return '<option value="' + k + '"' + (s.due_terms === k ? ' selected' : '') + '>' + esc(B.boot.terms[k].label) + '</option>'; }).join('') + '</select></div>' +
			f('expiry_days', 'Proposals & estimates valid for (days)', '', '', 'number') + '</div></section>' +
			'<section class="axh-card bx-card"><h3 class="bx-h3">Numbering</h3><div class="bx-numbers">' + ['proposal', 'estimate', 'invoice'].map(function (t) {
				return '<div><b>' + BTL[t] + 's</b><div class="axh-form-grid"><div class="axh-field"><label>Prefix</label><input type="text" data-bs-n="prefix.' + t + '" value="' + esc(s.prefix[t]) + '"></div><div class="axh-field"><label>Next number</label><input type="number" min="1" data-bs-n="next.' + t + '" value="' + esc(s.next[t]) + '"></div></div><span class="bx-sub">Next: ' + esc(s.prefix[t] + s.next[t]) + '</span></div>';
			}).join('') + '</div></section>' +
			'<section class="axh-card bx-card"><h3 class="bx-h3">Sales tax</h3>' + tog('tax_on', 'Add a tax line to new documents by default (off: most of AdaptExec’s services are not taxed)') +
			'<div class="axh-form-grid">' + f('tax_rate', 'Rate (%)', '', '0', 'number') + f('tax_label', 'Label', '', 'Sales tax') + '</div><p class="axh-hint">Each document can switch tax on or off, and only items marked taxable are taxed.</p></section>' +
			'<section class="axh-card bx-card"><h3 class="bx-h3">Reminders & email</h3>' + tog('remind_on', 'Email automatic reminders for overdue invoices (off by default)') +
			'<div class="axh-form-grid bx-g3">' + f('remind_after', 'First reminder (days after due)', '', '3', 'number') + f('remind_every', 'Then every (days)', '', '7', 'number') + f('remind_max', 'At most', '', '3', 'number') + '</div>' +
			tog('notify_views', 'Notify me (and the rep) the first time a customer opens a document') +
			f('reply_to', 'Reply-to for customer emails', 'Customers who reply reach this address. Blank uses the billing email. (A rep sending a link gets the replies themselves.)', biz.email, 'email') +
			'<button type="button" class="axh-btn axh-btn--ghost axh-btn--sm" data-bx-runrem>Run overdue reminders now</button></section>' +
			'</div><div class="bx-savebar"><span class="axh-hint">Changes apply to customer pages immediately.</span><button class="axh-btn axh-btn--primary" data-bx-saveset>Save settings</button></div>';
		return '<div class="bx">' + html + '</div>';
	}
	function saveBillSettings() {
		var b = { prefix: {}, next: {} };
		document.querySelectorAll('[data-bs]').forEach(function (i) { b[i.getAttribute('data-bs')] = i.value; });
		document.querySelectorAll('[data-bs-b]').forEach(function (i) { b[i.getAttribute('data-bs-b')] = i.checked ? 1 : 0; });
		document.querySelectorAll('[data-bs-n]').forEach(function (i) { var k = i.getAttribute('data-bs-n').split('.'); b[k[0]][k[1]] = i.value; });
		return api('/billing/settings', { method: 'POST', body: b }).then(function () { B.boot = null; toast('Billing settings saved.', 'ok'); route(); }).catch(bfail);
	}

	/* --------------------------------------------- images (cover / logo) */

	function mediaRoot() { return AXH.root.replace(/axh\/v1\/?$/, ''); }
	function imagePicker(onPick) {
		var demos = (S.boot.demoSites || []).filter(function (d) { return d.thumb; });
		var m = modal('Choose an image',
			'<div class="bx-tabs bx-tabs--sm"><button class="is-on" data-ip="demos">Portfolio & demos</button><button data-ip="media">Media library</button><button data-ip="url">Paste a link</button></div>' +
			'<div data-ip-pane="demos"><div class="bx-imgs">' + (demos.length ? demos.map(function (d) { return '<button class="bx-img" data-ip-pick="' + esc(d.thumb) + '"><img src="' + esc(d.thumb) + '" alt="" loading="lazy"><span>' + esc(d.title) + '</span></button>'; }).join('') : '<p class="axh-hint">No demo screenshots are installed. Use the media library.</p>') + '</div></div>' +
			'<div data-ip-pane="media" hidden><div class="bx-imgs" data-ip-media><p class="axh-hint"><span class="axh-spin"></span> Loading…</p></div></div>' +
			'<div data-ip-pane="url" hidden><div class="axh-field"><label for="bx-ipu">Image URL</label><input id="bx-ipu" type="url" placeholder="https://adaptexec.com/wp-content/uploads/…"></div><button class="axh-btn axh-btn--primary" data-ip-url>Use this image</button></div>', '');
		var loaded = false;
		m.querySelectorAll('[data-ip]').forEach(function (b) {
			b.onclick = function () {
				var k = b.getAttribute('data-ip');
				m.querySelectorAll('[data-ip]').forEach(function (x) { x.classList.toggle('is-on', x === b); });
				m.querySelectorAll('[data-ip-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-ip-pane') !== k; });
				if (k === 'media' && !loaded) {
					loaded = true;
					fetch(mediaRoot() + 'wp/v2/media?media_type=image&per_page=40&_fields=id,source_url,alt_text,media_details,title', { credentials: 'same-origin', headers: { 'X-WP-Nonce': AXH.nonce } })
						.then(function (r) { return r.json(); }).then(function (list) {
							var box = m.querySelector('[data-ip-media]');
							if (!Array.isArray(list) || !list.length) { box.innerHTML = '<p class="axh-hint">No images found.</p>'; return; }
							box.innerHTML = list.map(function (x) {
								var sz = x.media_details && x.media_details.sizes, th = sz && (sz.medium_large || sz.medium || sz.large);
								return '<button class="bx-img" data-ip-pick="' + esc(x.source_url) + '"><img src="' + esc(th ? th.source_url : x.source_url) + '" alt="" loading="lazy"><span>' + esc((x.title && x.title.rendered || '').replace(/<[^>]+>/g, '')) + '</span></button>';
							}).join('');
						}).catch(function () { m.querySelector('[data-ip-media]').innerHTML = '<p class="axh-hint">Could not load the media library.</p>'; });
				}
			};
		});
		m.addEventListener('click', function (e) {
			var p = e.target.closest('[data-ip-pick]');
			if (p) { closeModal(); onPick(p.getAttribute('data-ip-pick')); }
		});
		m.querySelector('[data-ip-url]').onclick = function () { var u = m.querySelector('#bx-ipu').value.trim(); if (!/^https:\/\//.test(u)) { toast('Use an https:// image link.', 'bad'); return; } closeModal(); onPick(u); };
	}

	/* ------------------------------------------------------------ editor */

	var ED = null;
	function edStart(d, tpl) {
		var x = JSON.parse(JSON.stringify(d.doc));
		ED = { id: x.id, type: x.type, doc: x, number: x.number, status: x.display_status, tpl: false, dirty: false, saving: false, mobile: 'edit', open: { cover: true, sections: true, invest: true, details: true, notes: false } };
	}
	function edStartTemplate(t) {
		var c = t ? JSON.parse(JSON.stringify(t.content)) : { cover: { title: '', subtitle: 'Proposal', intro: '', image: '' }, sections: [], options: [], addons: [], schedule: B.boot.schedule };
		ED = { id: 0, type: 'proposal', tpl: true, tplId: t ? t.id : 'tpl' + Date.now().toString(36), tplName: t ? t.name : 'New template', doc: { type: 'proposal', title: t ? t.title : '', content: c, lines: [], discount_type: 'pct', discount_value: 0, tax_on: false, tax_rate: 0, notes: '', terms_text: '' }, dirty: false, mobile: 'edit', open: { cover: true, sections: true, invest: true, details: false, notes: false } };
	}
	function edGet(path) { var o = ED.doc; path.split('.').forEach(function (k) { o = o == null ? undefined : o[k]; }); return o; }
	function edSet(path, v) {
		var ks = path.split('.'), o = ED.doc;
		for (var i = 0; i < ks.length - 1; i++) { if (o[ks[i]] == null) o[ks[i]] = /^\d+$/.test(ks[i + 1]) ? [] : {}; o = o[ks[i]]; }
		o[ks[ks.length - 1]] = v;
	}
	function edLineNet(l) { var a = Math.round((Number(l.qty) || 0) * (Number(l.rate) || 0) * 100) / 100, d = l.disc_type === 'amt' ? Math.min(a, Number(l.disc) || 0) : Math.round(a * (Number(l.disc) || 0)) / 100; return Math.round((a - d) * 100) / 100; }
	function edCalc(lines) {
		var sub = 0, tb = 0, x = ED.doc;
		lines.forEach(function (l) { var n = edLineNet(l); sub += n; if (l.taxable) tb += n; });
		sub = Math.round(sub * 100) / 100;
		var dv = Number(x.discount_value) || 0, disc = x.discount_type === 'amt' ? Math.min(sub, dv) : Math.round(sub * Math.min(100, dv)) / 100, f = sub > 0 ? (sub - disc) / sub : 0;
		var tax = x.tax_on ? Math.round(tb * f * (Number(x.tax_rate) || 0)) / 100 : 0;
		return { sub: sub, disc: disc, tax: tax, total: Math.round((sub - disc + tax) * 100) / 100 };
	}
	function edCurrentLines() {
		var x = ED.doc, c = x.content || {}, ls = (x.lines || []).slice();
		if (ED.type === 'proposal') {
			(c.options || []).forEach(function (o) { if (o.id === c.selected) ls = ls.concat(o.lines || []); });
			(c.addons || []).forEach(function (a) { if (a.on) ls.push(a); });
		}
		return ls;
	}
	function edUid() { return Math.random().toString(36).slice(2, 9); }

	function viewEditor() {
		var x = ED.doc, isP = ED.type === 'proposal';
		var title = ED.tpl ? 'Template · ' + esc(ED.tplName) : esc(BTL[ED.type]) + ' ' + esc(ED.number);
		var head = '<div class="bx-edhead"><a class="axh-btn axh-btn--quiet axh-btn--sm" href="' + (ED.tpl ? '#/billing/templates' : '#/billing/doc/' + ED.id) + '">' + icon('back', 14) + (ED.tpl ? ' Templates' : ' Back') + '</a>' +
			'<h1 class="thunder">' + title + '</h1>' + (ED.tpl ? '' : bpill(ED.status)) + '<span class="bx-edstate" data-ed-state>' + (ED.tpl ? '' : 'All changes saved') + '</span><button type="button" class="bx-edph" data-ed-ph hidden></button><span class="axh-top__spacer"></span>' +
			'<div class="bx-seg bx-only-sm"><button class="' + (ED.mobile === 'edit' ? 'is-on' : '') + '" data-ed-mob="edit">Edit</button><button class="' + (ED.mobile === 'prev' ? 'is-on' : '') + '" data-ed-mob="prev">Preview</button></div>' +
			(ED.tpl ? '<button class="axh-btn axh-btn--primary" data-ed-save>Save template</button>' : '<button class="axh-btn axh-btn--ghost" data-ed-save>Save</button><button class="axh-btn axh-btn--primary" data-ed-done>Done</button>') + '</div>';
		return '<div class="bx bx-ed" data-mob="' + ED.mobile + '">' + head + '<div class="bx-edgrid"><div class="bx-edform" id="bx-edform">' + edForm() + '</div>' +
			'<div class="bx-edprev"><div class="bx-frame bx-frame--live"><div class="bx-frame__bar"><i></i><i></i><i></i><span>Live preview · exactly what the customer sees</span><button class="bx-link" data-ed-refresh>' + icon('spark', 12) + ' Refresh</button></div><iframe id="bx-prev" title="Live preview"></iframe></div></div></div></div>';
	}
	function edCard(key, title, sub, body) {
		var open = ED.open[key];
		return '<section class="bx-edcard' + (open ? ' is-open' : '') + '"><button type="button" class="bx-edcard__h" data-ed-toggle="' + key + '" aria-expanded="' + !!open + '"><b>' + title + '</b><span>' + sub + '</span>' + icon(open ? 'x' : 'plus', 14) + '</button>' + (open ? '<div class="bx-edcard__b">' + body + '</div>' : '') + '</section>';
	}
	function edF(path, label, opts) {
		opts = opts || {};
		var v = edGet(path); v = v == null ? '' : v;
		var input = opts.area ? '<textarea data-ed="' + path + '" rows="' + (opts.rows || 4) + '"' + (opts.ph ? ' placeholder="' + esc(opts.ph) + '"' : '') + '>' + esc(v) + '</textarea>'
			: '<input type="' + (opts.type || 'text') + '" data-ed="' + path + '"' + (opts.num ? ' data-num inputmode="decimal"' : '') + ' value="' + esc(v) + '"' + (opts.ph ? ' placeholder="' + esc(opts.ph) + '"' : '') + '>';
		return '<div class="axh-field' + (opts.span ? ' axh-span' : '') + (opts.cls ? ' ' + opts.cls : '') + '"><label>' + label + '</label>' + (opts.help ? '<div class="axh-field__help">' + opts.help + '</div>' : '') + (opts.md ? mdBar(path) : '') + input + '</div>';
	}
	function mdBar(path) {
		return '<div class="bx-mdbar" role="toolbar" aria-label="Formatting">' + [['b', '<b>B</b>', 'Bold'], ['i', '<i>I</i>', 'Italic'], ['ul', '• List', 'Bulleted list'], ['ol', '1. List', 'Numbered list'], ['h', 'H', 'Heading'], ['a', icon('link', 12), 'Link']]
			.map(function (b) { return '<button type="button" data-md="' + b[0] + '" data-md-for="' + path + '" title="' + b[2] + '" aria-label="' + b[2] + '">' + b[1] + '</button>'; }).join('') + '<span class="bx-mdbar__hint">Markdown: **bold**, - list</span></div>';
	}
	function edForm() {
		var x = ED.doc, c = x.content || {}, isP = ED.type === 'proposal', inv = ED.type === 'invoice', h = '';
		if (ED.tpl) {
			h += '<section class="bx-edcard is-open"><div class="bx-edcard__b"><div class="axh-field"><label>Template name</label><input data-ed-tplname value="' + esc(ED.tplName) + '"></div>' + edF('title', 'Default document title') + '</div></section>';
		} else {
			var cust = (B.customers || []);
			h += edCard('details', 'Details', 'Customer, dates' + (inv ? ', terms' : ''),
				'<div class="axh-form-grid"><div class="axh-field axh-span"><label>Customer</label><div class="bx-row"><select data-ed="customer_id" data-int>' + cust.map(function (k) { return '<option value="' + k.id + '"' + (Number(x.customer_id) === k.id ? ' selected' : '') + '>' + esc(custName(k)) + '</option>'; }).join('') + '</select><button type="button" class="axh-btn axh-btn--ghost" data-ed-newcust>' + icon('plus', 13) + '</button></div></div>' +
				edF('title', 'Title', { span: true, ph: isP ? 'Website redesign' : 'What is this for?' }) +
				edF('issue_date', 'Issue date', { type: 'date' }) +
				(inv ? '<div class="axh-field"><label>Terms</label><select data-ed="terms">' + Object.keys(B.boot.terms).map(function (k) { return '<option value="' + k + '"' + (x.terms === k ? ' selected' : '') + '>' + esc(B.boot.terms[k].label) + '</option>'; }).join('') + '</select></div>' +
					(x.terms === 'custom' ? edF('terms_days', 'Due in (days)', { num: true }) : '') + edF('due_date', 'Due date', { type: 'date' }) +
					'<div class="axh-field axh-span"><label>Commission</label><div class="axh-field__help">When this invoice is paid in full and cleared, the matching half of the rep’s commission on this client’s deal becomes payable.</div><select data-ed="milestone">' + Object.keys(BLEG).map(function (k) { return '<option value="' + k + '"' + ((x.milestone || '') === k ? ' selected' : '') + '>' + esc(BLEG[k]) + '</option>'; }).join('') + '</select></div>'
					: edF('expiry_date', 'Valid until', { type: 'date' })) + '</div>');
		}
		if (isP) {
			var cv = c.cover || {};
			h += edCard('cover', 'Cover', 'The first thing they see',
				'<div class="axh-form-grid">' + edF('content.cover.title', 'Headline', { span: true, ph: 'A website built around your business' }) + edF('content.cover.subtitle', 'Kicker', { ph: 'Proposal' }) +
				'<div class="axh-field"><label>Hero image</label><div class="bx-hero">' + (cv.image ? '<img src="' + esc(cv.image) + '" alt=""><button type="button" class="axh-linkbtn" data-ed-img="clear">Remove</button>' : '') + '<button type="button" class="axh-btn axh-btn--ghost axh-btn--sm" data-ed-img="pick">' + icon('upload', 13) + (cv.image ? ' Change' : ' Choose image') + '</button></div></div>' +
				edF('content.cover.intro', 'Intro', { area: true, rows: 3, span: true, md: true }) + '</div>');
			var secs = c.sections || [];
			h += edCard('sections', 'Sections', secs.length + ' section' + (secs.length === 1 ? '' : 's') + ' · drag order with the arrows',
				'<div class="bx-secs">' + secs.map(function (s, i) { return edSection(s, i, secs.length); }).join('') + '</div>' +
				'<div class="bx-addsec"><select data-ed-addsec-kind>' + [['text', 'Text'], ['scope', 'Scope & deliverables (cards)'], ['timeline', 'Timeline'], ['investment', 'Investment (options & total)'], ['included', 'What’s included (checklist)'], ['why', 'Why AdaptExec (feature cards)'], ['next', 'Next steps (numbered)'], ['terms', 'Terms']].map(function (k) { return '<option value="' + k[0] + '">' + k[1] + '</option>'; }).join('') + '</select><button type="button" class="axh-btn axh-btn--ghost axh-btn--sm" data-ed-addsec>' + icon('plus', 13) + ' Add section</button></div>');
		}
		var inv2 = '';
		if (isP) {
			var opts = c.options || [];
			inv2 += '<h4 class="bx-edsub">Options <span>The customer picks one. Mark one recommended.</span></h4>' +
				opts.map(function (o, i) {
					return '<div class="bx-opt"><div class="bx-opt__h"><label class="bx-radio"><input type="radio" name="bx-rec" data-ed-rec="' + i + '"' + (o.recommended ? ' checked' : '') + '> Recommended</label><label class="bx-radio"><input type="radio" name="bx-sel" data-ed-selopt="' + esc(o.id) + '"' + (c.selected === o.id ? ' checked' : '') + '> Pre-selected</label><span class="axh-top__spacer"></span><button type="button" class="axh-linkbtn bx-danger" data-ed-delopt="' + i + '">Remove</button></div>' +
						'<div class="axh-form-grid">' + edF('content.options.' + i + '.name', 'Option name') + edF('content.options.' + i + '.tagline', 'Tagline') + '</div>' +
						edLines(o.lines || [], 'content.options.' + i + '.lines') + '</div>';
				}).join('') + (opts.length < 4 ? '<button type="button" class="axh-btn axh-btn--ghost axh-btn--sm" data-ed-addopt>' + icon('plus', 13) + ' Add option</button>' : '') +
				'<h4 class="bx-edsub">Optional add-ons <span>Toggles the customer can switch on.</span></h4>' + edLines(c.addons || [], 'content.addons', true) +
				'<h4 class="bx-edsub">Included in every option <span>Optional base lines.</span></h4>' + edLines(x.lines || [], 'lines');
		} else {
			inv2 += edLines(x.lines || [], 'lines');
		}
		inv2 += '<div class="bx-adj"><div class="axh-field"><label>Discount</label><div class="bx-row"><input data-ed="discount_value" data-num inputmode="decimal" value="' + esc(x.discount_value || '') + '" placeholder="0"><select data-ed="discount_type"><option value="pct"' + (x.discount_type !== 'amt' ? ' selected' : '') + '>%</option><option value="amt"' + (x.discount_type === 'amt' ? ' selected' : '') + '>$</option></select></div></div>' +
			'<div class="axh-field"><label class="axh-check"><input type="checkbox" data-ed-b="tax_on"' + (x.tax_on ? ' checked' : '') + '> Tax line</label>' + (x.tax_on ? '<div class="bx-row"><input data-ed="tax_rate" data-num value="' + esc(x.tax_rate || '') + '" placeholder="Rate %"><input data-ed="tax_label" value="' + esc(x.tax_label || 'Sales tax') + '"></div>' : '') + '</div>' +
			'<div class="bx-totals" data-ed-totals></div></div>';
		if (!inv) {
			var sc = c.schedule || [], sum = 0; sc.forEach(function (s) { sum += Number(s.pct) || 0; });
			inv2 += '<h4 class="bx-edsub">Payment schedule <span>Becomes the invoices when accepted. Deposit and final map to the rep’s commission halves.</span></h4>' +
				'<div class="bx-sched">' + sc.map(function (s, i) {
					return '<div class="bx-sched__r"><input data-ed="content.schedule.' + i + '.label" value="' + esc(s.label) + '" aria-label="Label"><span class="bx-pct"><input data-ed="content.schedule.' + i + '.pct" data-num value="' + esc(s.pct) + '" aria-label="Percent">%</span>' +
						'<select data-ed="content.schedule.' + i + '.leg" aria-label="Commission half">' + [['deposit', 'Deposit half'], ['final', 'Final half'], ['full', 'Both halves'], ['', 'None']].map(function (k) { return '<option value="' + k[0] + '"' + ((s.leg || '') === k[0] ? ' selected' : '') + '>' + k[1] + '</option>'; }).join('') + '</select>' +
						'<input data-ed="content.schedule.' + i + '.due" value="' + esc(s.due || '') + '" placeholder="When it’s due" aria-label="When due"><button type="button" class="bx-x" data-ed-delsched="' + i + '" aria-label="Remove">' + icon('x', 13) + '</button></div>';
				}).join('') + '</div><div class="bx-row bx-row--wrap"><button type="button" class="axh-btn axh-btn--ghost axh-btn--sm" data-ed-addsched>' + icon('plus', 13) + ' Add payment</button><button type="button" class="axh-linkbtn" data-ed-preset="5050">50% / 50%</button><button type="button" class="axh-linkbtn" data-ed-preset="100">100% up front</button>' +
				'<span class="bx-sub' + (sc.length && Math.abs(sum - 100) > 0.01 ? ' bx-bad' : '') + '" data-ed-schedsum>' + (sc.length ? 'Adds up to ' + Math.round(sum * 100) / 100 + '%' : 'No schedule: one invoice for the full amount') + '</span></div>';
		}
		h += edCard('invest', isP ? 'Investment' : 'Line items', isP ? 'Options, add-ons, schedule' : 'Items, discount, tax', inv2);
		h += edCard('notes', 'Notes & terms', 'Customer note, terms' + (ED.tpl ? '' : ', internal notes'),
			edF('notes', 'Note to the customer', { area: true, rows: 3, md: true }) + edF('terms_text', 'Terms', { area: true, rows: 5, md: true, help: 'Shown in the Terms section and at the bottom of estimates and invoices.' }) +
			(ED.tpl ? '' : edF('internal_notes', 'Internal notes', { area: true, rows: 3, help: 'Only AdaptExec sees these.' })));
		return h;
	}
	function edSection(s, i, n) {
		var p = 'content.sections.' + i, kinds = { text: 'Text', scope: 'Scope', timeline: 'Timeline', investment: 'Investment', included: 'Included', why: 'Why us', next: 'Next steps', terms: 'Terms' };
		var body = s.kind === 'investment' ? edF(p + '.body', 'Intro line (optional)', { area: true, rows: 2, md: true, help: 'Options, add-ons and the live total are added automatically from the Investment card below.' })
			: s.kind === 'terms' ? edF(p + '.body', 'Extra terms (optional)', { area: true, rows: 2, md: true, help: 'The document’s Terms (Notes & terms card) appear here automatically.' })
			: edF(p + '.body', 'Content', { area: true, rows: s.kind === 'why' || s.kind === 'scope' ? 6 : 4, md: true, help: { scope: 'Each bullet becomes a card.', included: 'Each bullet becomes a check.', why: 'Each bullet becomes a feature card: start it with **a bold title.**', next: 'Use a numbered list: 1. … 2. …' }[s.kind] || '' });
		if (s.kind === 'timeline') {
			body += '<div class="bx-tl">' + (s.items || []).map(function (it, j) {
				return '<div class="bx-tl__r"><input data-ed="' + p + '.items.' + j + '.when" value="' + esc(it.when) + '" placeholder="Week 1" aria-label="When"><input data-ed="' + p + '.items.' + j + '.title" value="' + esc(it.title) + '" placeholder="Milestone" aria-label="Milestone"><input data-ed="' + p + '.items.' + j + '.detail" value="' + esc(it.detail) + '" placeholder="Detail (optional)" aria-label="Detail"><button type="button" class="bx-x" data-ed-deltl="' + i + '.' + j + '" aria-label="Remove">' + icon('x', 13) + '</button></div>';
			}).join('') + '<button type="button" class="axh-btn axh-btn--ghost axh-btn--sm" data-ed-addtl="' + i + '">' + icon('plus', 13) + ' Milestone</button></div>';
		}
		return '<div class="bx-sec' + (s.on === false ? ' is-off' : '') + '"><div class="bx-sec__h"><span class="bx-sec__k">' + esc(kinds[s.kind] || s.kind) + '</span><input class="bx-sec__t" data-ed="' + p + '.title" value="' + esc(s.title) + '" aria-label="Section title">' +
			'<label class="bx-sw" title="Show this section"><input type="checkbox" data-ed-secon="' + i + '"' + (s.on === false ? '' : ' checked') + '><span></span></label>' +
			'<button type="button" class="bx-x" data-ed-move="' + i + '" data-dir="-1"' + (i === 0 ? ' disabled' : '') + ' aria-label="Move up">' + icon('left', 13).replace('M15 18l-6-6 6-6', 'M18 15l-6-6-6 6') + '</button>' +
			'<button type="button" class="bx-x" data-ed-move="' + i + '" data-dir="1"' + (i === n - 1 ? ' disabled' : '') + ' aria-label="Move down">' + icon('left', 13).replace('M15 18l-6-6 6-6', 'M6 9l6 6 6-6') + '</button>' +
			'<button type="button" class="bx-x" data-ed-delsec="' + i + '" aria-label="Remove section">' + icon('trash', 13) + '</button></div>' + body + '</div>';
	}
	function edLines(lines, path, addon) {
		var items = B.boot.items || [];
		var opts = '<option value="">+ From catalog…</option>' + items.map(function (it) { return '<option value="' + it.id + '">' + esc(it.category + ' · ' + it.name) + (it.rate ? ' (' + bm0(it.rate) + ')' : '') + '</option>'; }).join('');
		return '<div class="bx-lines">' + lines.map(function (l, i) {
			var p = path + '.' + i;
			return '<div class="bx-line">' + (addon ? '<label class="bx-sw bx-line__on" title="On by default"><input type="checkbox" data-ed-lb="' + p + '.on"' + (l.on ? ' checked' : '') + '><span></span></label>' : '') +
				'<div class="bx-line__main"><input class="bx-line__n" data-ed="' + p + '.name" value="' + esc(l.name || '') + '" placeholder="Item name" aria-label="Item name"><input class="bx-line__d" data-ed="' + p + '.desc" value="' + esc(l.desc || '') + '" placeholder="Description (optional)" aria-label="Description"></div>' +
				'<div class="bx-line__nums"><label><span>Qty</span><input data-ed="' + p + '.qty" data-num inputmode="decimal" value="' + esc(l.qty === undefined ? 1 : l.qty) + '"></label>' +
				'<label><span>Rate</span><input data-ed="' + p + '.rate" data-num inputmode="decimal" value="' + esc(l.rate || 0) + '"></label>' +
				'<label><span>Unit</span><select data-ed="' + p + '.unit">' + B.boot.units.map(function (u) { return '<option' + ((l.unit || 'each') === u ? ' selected' : '') + '>' + u + '</option>'; }).join('') + '</select></label>' +
				'<label><span>Disc.</span><span class="bx-row"><input data-ed="' + p + '.disc" data-num inputmode="decimal" value="' + esc(l.disc || '') + '" placeholder="0"><select data-ed="' + p + '.disc_type" aria-label="Discount type"><option value="pct"' + (l.disc_type !== 'amt' ? ' selected' : '') + '>%</option><option value="amt"' + (l.disc_type === 'amt' ? ' selected' : '') + '>$</option></select></span></label>' +
				'<b class="bx-line__amt" data-ed-amt="' + p + '">' + bm(edLineNet(l)) + '</b></div>' +
				'<div class="bx-line__x"><select data-ed="' + p + '.category" aria-label="Category">' + B.boot.categories.map(function (k) { return '<option' + ((l.category || 'Other') === k ? ' selected' : '') + '>' + esc(k) + '</option>'; }).join('') + '</select>' +
				(ED.doc.tax_on ? '<label class="bx-tiny"><input type="checkbox" data-ed-lb="' + p + '.taxable"' + (l.taxable ? ' checked' : '') + '> Taxable</label>' : '') +
				'<button type="button" class="bx-x" data-ed-delline="' + p + '" aria-label="Remove line">' + icon('trash', 13) + '</button></div></div>';
		}).join('') + '<div class="bx-addline"><select data-ed-cat="' + path + '" aria-label="Add from catalog">' + opts + '</select><button type="button" class="axh-btn axh-btn--ghost axh-btn--sm" data-ed-addline="' + path + '">' + icon('plus', 13) + ' Custom line</button></div></div>';
	}
	function edPhScan() {
		var root = el('bx-edform'); if (!root) return;
		root.querySelectorAll('input[data-ed], textarea[data-ed], input[data-ed-l], textarea[data-ed-l]').forEach(function (i) { PH_RE.lastIndex = 0; i.classList.toggle('bx-ph', typeof i.value === 'string' && PH_RE.test(i.value)); });
		PH_RE.lastIndex = 0;
		var ph = bxPh(Object.assign({ type: ED.tpl ? 'proposal' : ED.type }, ED.doc)), b = document.querySelector('[data-ed-ph]');
		if (!b) return;
		b.hidden = !ph.length;
		b.innerHTML = icon('flag', 13) + ' ' + ph.length + ' placeholder' + (ph.length === 1 ? '' : 's') + ' to fill';
		b.title = ph.map(function (p) { return p.text + ' — ' + p.where; }).join('\n');
	}
	function edTotals() {
		edPhScan();
		var box = document.querySelector('[data-ed-totals]');
		if (!box) return;
		var t = edCalc(edCurrentLines());
		box.innerHTML = '<div><span>Subtotal</span><b>' + bm(t.sub) + '</b></div>' + (t.disc ? '<div><span>Discount</span><b>−' + bm(t.disc) + '</b></div>' : '') + (ED.doc.tax_on ? '<div><span>Tax</span><b>' + bm(t.tax) + '</b></div>' : '') +
			'<div class="bx-totals__t"><span>' + (ED.type === 'proposal' ? 'Total (pre-selected)' : 'Total') + '</span><b>' + bm(t.total) + '</b></div>';
		document.querySelectorAll('[data-ed-amt]').forEach(function (b) { var l = edGet(b.getAttribute('data-ed-amt')); if (l) b.textContent = bm(edLineNet(l)); });
		var ss = document.querySelector('[data-ed-schedsum]');
		if (ss) { var sum = 0, sc = (ED.doc.content.schedule || []); sc.forEach(function (s) { sum += Number(s.pct) || 0; }); ss.textContent = sc.length ? 'Adds up to ' + Math.round(sum * 100) / 100 + '%' : 'No schedule: one invoice for the full amount'; ss.classList.toggle('bx-bad', sc.length && Math.abs(sum - 100) > 0.01); }
	}
	function edRerender() {
		var f = el('bx-edform'); if (!f) return;
		var y = f.scrollTop, wy = window.scrollY;
		f.innerHTML = edForm(); f.scrollTop = y; window.scrollTo(0, wy);
		edTotals();
	}
	function edPayload() {
		var x = ED.doc;
		var b = { title: x.title, content: x.content, lines: x.lines, discount_type: x.discount_type, discount_value: x.discount_value, tax_on: !!x.tax_on, tax_rate: x.tax_rate, tax_label: x.tax_label, notes: x.notes, terms_text: x.terms_text };
		if (!ED.tpl) {
			b.customer_id = x.customer_id; b.issue_date = x.issue_date; b.internal_notes = x.internal_notes;
			if (ED.type === 'invoice') { b.terms = x.terms; b.terms_days = x.terms_days; b.due_date = x.due_date; b.milestone = x.milestone || ''; } else b.expiry_date = x.expiry_date;
		}
		return b;
	}
	function edChanged(structural) {
		ED.dirty = true;
		var st = document.querySelector('[data-ed-state]'); if (st && !ED.tpl) st.textContent = 'Unsaved changes…';
		if (structural) edRerender(); else edTotals();
		clearTimeout(ED.pt); ED.pt = setTimeout(edPreview, 650);
		if (!ED.tpl) { clearTimeout(ED.st); ED.st = setTimeout(function () { edSave(); }, 2200); }
	}
	function edPreview() {
		var fr = el('bx-prev'); if (!fr) return;
		var b = edPayload(); b.id = ED.id; if (ED.tpl) b.type = 'proposal';
		var y = 0; try { y = fr.contentWindow ? fr.contentWindow.scrollY : 0; } catch (e) {}
		var seq = ED.pseq = (ED.pseq || 0) + 1;
		api('/billing/preview', { method: 'POST', body: b }).then(function (r) {
			if (seq !== ED.pseq || !el('bx-prev')) return;
			fr.onload = function () { try { fr.contentWindow.scrollTo(0, y); } catch (e) {} };
			fr.srcdoc = r.html;
		}).catch(function () {});
	}
	function edSave(done) {
		if (ED.tpl) {
			var nm = document.querySelector('[data-ed-tplname]');
			return api('/billing/templates', { method: 'POST', body: { id: ED.tplId, name: nm ? nm.value : ED.tplName, title: ED.doc.title, content: ED.doc.content } })
				.then(function (r) { B.boot.templates = r.templates; ED.dirty = false; toast('Template saved.', 'ok'); if (done) done(); }).catch(bfail);
		}
		if (ED.saving) { ED.again = true; return Promise.resolve(); }
		ED.saving = true; clearTimeout(ED.st);
		var st = document.querySelector('[data-ed-state]'); if (st) st.textContent = 'Saving…';
		return api('/billing/docs/' + ED.id, { method: 'POST', body: edPayload() }).then(function (r) {
			ED.saving = false; ED.dirty = false;
			var due = r.doc.due_date, exp = r.doc.expiry_date;
			if (ED.doc.due_date !== due || ED.doc.expiry_date !== exp) { ED.doc.due_date = due; ED.doc.expiry_date = exp; var di = document.querySelector('[data-ed="due_date"]'); if (di && document.activeElement !== di) di.value = due || ''; }
			var st2 = document.querySelector('[data-ed-state]'); if (st2) { st2.textContent = 'All changes saved'; st2.classList.remove('bx-bad'); }
			if (ED.again) { ED.again = false; edSave(); }
			if (done) done();
		}).catch(function (e) {
			ED.saving = false;
			var st3 = document.querySelector('[data-ed-state]'); if (st3) { st3.textContent = 'Not saved: ' + e.message; st3.classList.add('bx-bad'); }
			if (done) bfail(e);
		});
	}
	function mdApply(btn) {
		var ta = document.querySelector('[data-ed="' + btn.getAttribute('data-md-for') + '"]');
		if (!ta) return;
		var k = btn.getAttribute('data-md'), s = ta.selectionStart, e = ta.selectionEnd, v = ta.value, sel = v.slice(s, e), rep;
		if (k === 'b') rep = '**' + (sel || 'bold text') + '**';
		else if (k === 'i') rep = '*' + (sel || 'italic text') + '*';
		else if (k === 'ul') rep = (sel || 'List item').split('\n').map(function (l) { return '- ' + l.replace(/^[-*]\s+/, ''); }).join('\n');
		else if (k === 'ol') rep = (sel || 'Step').split('\n').map(function (l, i) { return (i + 1) + '. ' + l.replace(/^\d+[.)]\s+/, ''); }).join('\n');
		else if (k === 'h') rep = '### ' + (sel || 'Heading');
		else { var u = prompt('Link address (https://…)', 'https://'); if (!u || !/^(https?:|mailto:|tel:)/.test(u)) return; rep = '[' + (sel || 'link text') + '](' + u + ')'; }
		var pre = (k === 'ul' || k === 'ol' || k === 'h') && s > 0 && v[s - 1] !== '\n' ? '\n' : '';
		ta.value = v.slice(0, s) + pre + rep + v.slice(e);
		ta.focus(); ta.selectionStart = s + pre.length; ta.selectionEnd = s + pre.length + rep.length;
		ta.dispatchEvent(new Event('input', { bubbles: true }));
	}
	function edBind() {
		var root = document.querySelector('.bx-ed');
		if (!root) return;
		edTotals(); edPreview();
		root.addEventListener('input', function (e) {
			var t = e.target, p = t.getAttribute && t.getAttribute('data-ed');
			if (t.hasAttribute('data-ed-tplname')) { ED.tplName = t.value; return; }
			if (!p) return;
			var v = t.value;
			if (t.hasAttribute('data-num')) v = v === '' ? '' : Number(String(v).replace(/[^0-9.\-]/g, '')) || 0;
			if (t.hasAttribute('data-int')) v = Number(v);
			edSet(p, v);
			edChanged(t.tagName === 'SELECT' && (p === 'terms' || p === 'discount_type'));
		});
		root.addEventListener('change', function (e) {
			var t = e.target;
			if (t.hasAttribute('data-ed-b')) { edSet(t.getAttribute('data-ed-b'), t.checked); edChanged(true); return; }
			if (t.hasAttribute('data-ed-lb')) { edSet(t.getAttribute('data-ed-lb'), t.checked); edChanged(false); return; }
			if (t.hasAttribute('data-ed-secon')) { ED.doc.content.sections[+t.getAttribute('data-ed-secon')].on = t.checked; edChanged(true); return; }
			if (t.hasAttribute('data-ed-rec')) { (ED.doc.content.options || []).forEach(function (o, i) { o.recommended = i === +t.getAttribute('data-ed-rec'); }); edChanged(false); return; }
			if (t.hasAttribute('data-ed-selopt')) { ED.doc.content.selected = t.getAttribute('data-ed-selopt'); edChanged(false); return; }
			if (t.hasAttribute('data-ed-cat')) {
				var it = (B.boot.items || []).filter(function (x) { return String(x.id) === t.value; })[0];
				if (it) {
					var arr = edGet(t.getAttribute('data-ed-cat')) || []; if (!edGet(t.getAttribute('data-ed-cat'))) edSet(t.getAttribute('data-ed-cat'), arr);
					arr.push({ id: edUid(), item_id: it.id, name: it.name, desc: it.description, qty: 1, rate: it.rate, unit: it.unit, category: it.category, taxable: it.taxable, disc_type: 'pct', disc: 0, on: false });
					edChanged(true);
				}
				return;
			}
		});
		root.addEventListener('click', function (e) {
			var t = e.target.closest('button,[data-ed-toggle]');
			if (!t) return;
			var c = ED.doc.content = ED.doc.content || {};
			if (t.hasAttribute('data-md')) { mdApply(t); return; }
			if (t.hasAttribute('data-ed-toggle')) { var k = t.getAttribute('data-ed-toggle'); ED.open[k] = !ED.open[k]; edRerender(); return; }
			if (t.hasAttribute('data-ed-mob')) { ED.mobile = t.getAttribute('data-ed-mob'); root.setAttribute('data-mob', ED.mobile); root.querySelectorAll('[data-ed-mob]').forEach(function (b) { b.classList.toggle('is-on', b === t); }); if (ED.mobile === 'prev') edPreview(); return; }
			if (t.hasAttribute('data-ed-refresh')) { edPreview(); return; }
			if (t.hasAttribute('data-ed-save')) { edSave(); return; }
			if (t.hasAttribute('data-ed-done')) { edSave(function () { location.hash = '#/billing/doc/' + ED.id; }); return; }
			if (t.hasAttribute('data-ed-newcust')) { customerModal(null, function (cu) { api('/billing/customers').then(function (r) { B.customers = r.customers || []; ED.doc.customer_id = cu.id; edChanged(true); }); }); return; }
			if (t.hasAttribute('data-ed-img')) {
				if (t.getAttribute('data-ed-img') === 'clear') { c.cover.image = ''; edChanged(true); return; }
				imagePicker(function (u) { c.cover = c.cover || {}; c.cover.image = u; edChanged(true); }); return;
			}
			if (t.hasAttribute('data-ed-addsec')) {
				var kind = root.querySelector('[data-ed-addsec-kind]').value, titles = { text: 'Overview', scope: 'Scope & deliverables', timeline: 'Timeline', investment: 'Investment', included: 'What’s included', why: 'Why AdaptExec', next: 'Next steps', terms: 'Terms' };
				(c.sections = c.sections || []).push({ id: kind + edUid().slice(0, 4), kind: kind, title: titles[kind], body: '', on: true, items: kind === 'timeline' ? [{ when: 'Week 1', title: 'Kickoff', detail: '' }] : undefined });
				edChanged(true); return;
			}
			if (t.hasAttribute('data-ed-delsec')) { if (!confirm('Remove this section?')) return; c.sections.splice(+t.getAttribute('data-ed-delsec'), 1); edChanged(true); return; }
			if (t.hasAttribute('data-ed-move')) { var i = +t.getAttribute('data-ed-move'), j = i + (+t.getAttribute('data-dir')); var s = c.sections; if (j < 0 || j >= s.length) return; var tmp = s[i]; s[i] = s[j]; s[j] = tmp; edChanged(true); return; }
			if (t.hasAttribute('data-ed-addtl')) { var sec = c.sections[+t.getAttribute('data-ed-addtl')]; (sec.items = sec.items || []).push({ when: '', title: '', detail: '' }); edChanged(true); return; }
			if (t.hasAttribute('data-ed-deltl')) { var pr = t.getAttribute('data-ed-deltl').split('.'); c.sections[+pr[0]].items.splice(+pr[1], 1); edChanged(true); return; }
			if (t.hasAttribute('data-ed-addopt')) { var n = (c.options = c.options || []); n.push({ id: 'opt' + edUid().slice(0, 4), name: 'Option ' + (n.length + 1), tagline: '', recommended: !n.length, lines: [{ name: '', desc: '', qty: 1, rate: 0, unit: 'project', category: 'Websites', disc_type: 'pct', disc: 0 }] }); if (!c.selected) c.selected = n[n.length - 1].id; edChanged(true); return; }
			if (t.hasAttribute('data-ed-delopt')) { if (!confirm('Remove this option?')) return; var removed = c.options.splice(+t.getAttribute('data-ed-delopt'), 1)[0]; if (removed && c.selected === removed.id) c.selected = c.options[0] ? c.options[0].id : ''; edChanged(true); return; }
			if (t.hasAttribute('data-ed-addline')) { var ap = t.getAttribute('data-ed-addline'); if (!edGet(ap)) edSet(ap, []); edGet(ap).push({ id: edUid(), name: '', desc: '', qty: 1, rate: 0, unit: 'each', category: 'Other', disc_type: 'pct', disc: 0, on: false }); edChanged(true); setTimeout(function () { var ins = root.querySelectorAll('[data-ed$=".name"]'); if (ins.length) ins[ins.length - 1].focus(); }, 30); return; }
			if (t.hasAttribute('data-ed-delline')) { var lp = t.getAttribute('data-ed-delline').split('.'), idx = +lp.pop(); edGet(lp.join('.')).splice(idx, 1); edChanged(true); return; }
			if (t.hasAttribute('data-ed-addsched')) { (c.schedule = c.schedule || []).push({ label: 'Payment', pct: 0, leg: '', due: '' }); edChanged(true); return; }
			if (t.hasAttribute('data-ed-delsched')) { c.schedule.splice(+t.getAttribute('data-ed-delsched'), 1); edChanged(true); return; }
			if (t.hasAttribute('data-ed-preset')) { c.schedule = t.getAttribute('data-ed-preset') === '100' ? [{ label: 'Payment in full', pct: 100, leg: 'full', due: 'Due when you accept' }] : JSON.parse(JSON.stringify(B.boot.schedule)); edChanged(true); return; }
		});
		root.addEventListener('keydown', function (e) { if ((e.metaKey || e.ctrlKey) && e.key === 's') { e.preventDefault(); edSave(); } });
	}

	/* ----------------------------------------------------------- templates */

	function viewBillTemplates() {
		var t = B.boot.templates || [];
		var html = bhead('Proposal templates', 'Starting points for new proposals. Edit the wording once and every new proposal starts from it.', '<a class="axh-btn axh-btn--primary" href="#/billing/template/new">' + icon('plus', 15) + ' New template</a>', ['#/billing/settings', 'Billing settings']);
		html += '<div class="bx-tplgrid">' + t.map(function (x) {
			var c = x.content || {};
			return '<div class="bx-tplcard"><div class="bx-tplcard__top"><span class="thunder">' + esc(x.name) + '</span>' + (x.edited ? '<span class="axh-badge axh-badge--blue">Edited</span>' : (x.builtin ? '<span class="axh-badge axh-badge--gray">Built in</span>' : '<span class="axh-badge axh-badge--orange">Yours</span>')) + '</div>' +
				'<p>' + esc((c.cover && c.cover.title) || x.title || '') + '</p><span class="bx-sub">' + (c.sections || []).length + ' sections · ' + (c.options || []).length + ' option(s) · ' + (c.addons || []).length + ' add-on(s)</span>' +
				'<div class="bx-row"><a class="axh-btn axh-btn--ghost axh-btn--sm" href="#/billing/template/' + esc(x.id) + '">' + icon('pen', 13) + ' Edit</a>' +
				(x.edited ? '<button class="axh-linkbtn" data-bx-tpl="reset" data-id="' + esc(x.id) + '">Reset to built-in</button>' : '') + '<button class="axh-linkbtn bx-danger" data-bx-tpl="delete" data-id="' + esc(x.id) + '">' + (x.builtin ? 'Hide' : 'Delete') + '</button></div></div>';
		}).join('') + '</div>';
		return '<div class="bx">' + html + '</div>';
	}

	/* ---------------------------------------------------- commissions */

	function viewCommissions(d) {
		var tab = B.ctab, reps = d.reps || [], t = d.totals || {};
		var ready = 0, held = 0, claw = 0, ytd = 0;
		reps.forEach(function (r) { ready += r.ready; held += r.held; claw += r.clawback; ytd += r.paid_ytd; });
		var html = bhead('Commissions & payouts', 'Every deal’s commission tied to its invoices and cleared payments. Select what is ready, create a check batch, mark it paid, and each rep gets a statement.',
			'<a class="axh-btn axh-btn--ghost" href="#/manage/payouts">' + icon('money', 15) + ' Single payments</a>');
		html += '<div class="bx-kpis bx-kpis--5">' + bstat('Ready to pay', bm(ready), 'Client payment cleared, not on hold', 'accent') + bstat('Waiting on clients', bm(t.upcoming || 0), 'Invoice not paid or not cleared') +
			bstat('On hold', bm(held), held > 0 ? 'Dispute or fraud review' : 'Nothing held', held > 0 ? 'warn' : '') + bstat('Clawbacks open', bm(claw), claw > 0 ? 'Deducted from the next batch' : 'None', claw > 0 ? 'bad' : '') +
			bstat('Paid ' + d.year, bm(ytd), 'Gross commission, all reps') + '</div>';
		html += '<div class="bx-tabs" role="tablist">' + [['reps', 'By rep & deal'], ['batches', 'Payout batches'], ['adjust', 'Holds & clawbacks'], ['1099', '1099 summary']].map(function (x) {
			return '<button role="tab" aria-selected="' + (tab === x[0]) + '" class="' + (tab === x[0] ? 'is-on' : '') + '" data-ctab="' + x[0] + '">' + x[1] + '</button>'; }).join('') + '</div>';
		if (tab === 'batches') html += batchesTab(d);
		else if (tab === 'adjust') html += adjustTab(d);
		else if (tab === '1099') html += '<div data-1099><div class="axh-card"><p class="axh-hint"><span class="axh-spin"></span> Loading…</p></div></div>';
		else html += repsTab(d);
		return '<div class="bx">' + html + '</div>';
	}
	function legState(l) {
		if (l.hold && ['due', 'overdue'].indexOf(l.state) > -1) return '<span class="bx-chip bx-chip--warn" title="' + esc(l.hold.reason) + '">On hold</span>';
		return { paid: '<span class="bx-chip bx-chip--ok">Paid ' + bdate(l.paid_at, false) + '</span>', waiting: '<span class="bx-chip">Waiting on client</span>', due: '<span class="bx-chip bx-chip--o">Ready</span>', overdue: '<span class="bx-chip bx-chip--bad">Ready 30+ days</span>', cancelled: '<span class="bx-chip">Nothing due</span>' }[l.state] || esc(l.state);
	}
	function repsTab(d) {
		var reps = d.reps || [], deals = d.deals || [];
		if (!deals.length) return '<div class="axh-card">' + bempty('No commission yet', 'Commission appears once you mark a deal Closed Won. Invoices tagged Deposit or Final then drive when each half is payable.', '') + '</div>';
		var h = '<div class="bx-repcards">' + reps.map(function (r) {
			return '<div class="bx-repcard"><div class="bx-repcard__h"><span class="bx-av">' + esc(initials(r.name)) + '</span><div><b>' + esc(r.name) + '</b><span>' + r.deals + ' deal' + (r.deals === 1 ? '' : 's') + (r.readiness && !r.readiness.ready ? ' · <em class="bx-bad">Onboarding: ' + esc(r.readiness.missing.join(', ')) + '</em>' : '') + '</span></div></div>' +
				'<dl class="bx-repcard__dl"><div><dt>Ready</dt><dd>' + bm(r.ready) + '</dd></div><div><dt>Waiting</dt><dd>' + bm(r.waiting) + '</dd></div><div><dt>Held</dt><dd>' + bm(r.held) + '</dd></div><div><dt>Clawback</dt><dd' + (r.clawback > 0 ? ' class="bx-bad"' : '') + '>' + bm(r.clawback) + '</dd></div><div><dt>Paid YTD</dt><dd>' + bm(r.paid_ytd) + '</dd></div></dl>' +
				(r.payee ? '<div class="bx-repcard__payee">' + icon('mail', 13) + ' Checks to <b>' + esc(r.payee.payable_to) + '</b>' + (r.payee.verify ? ' <em class="bx-bad">(address changed, verify)</em>' : '') + '</div>' : '<div class="bx-repcard__payee bx-bad">No payout address yet</div>') + '</div>';
		}).join('') + '</div>';
		h += '<div class="axh-card bx-card bx-card--flush"><table class="axh-table axh-table--stack bx-comm"><thead><tr><th class="bx-ck"></th><th>Deal</th><th>Rep</th><th>Half</th><th>Invoice</th><th class="num">Commission</th><th>Status</th><th></th></tr></thead><tbody>' +
			deals.map(function (dl) {
				return dl.legs.map(function (l, i) {
					var can = ['due', 'overdue'].indexOf(l.state) > -1 && !l.hold && !l.in_batch && l.amount > 0, key = dl.lead_id + ':' + l.key;
					return '<tr class="' + (i ? 'bx-leg2' : '') + '"><td class="bx-ck">' + (can ? '<input type="checkbox" data-csel="' + key + '" data-amt="' + l.amount + '" data-rep="' + dl.rep_id + '"' + (B.sel[key] ? ' checked' : '') + ' aria-label="Select ' + esc(dl.company + ' ' + l.key) + '">' : '') + '</td>' +
						'<td data-th="Deal">' + (i ? '' : '<a href="#/lead/' + dl.lead_id + '"><b class="bx-strong">' + esc(dl.company) + '</b></a><span class="bx-sub">' + bm0(dl.value) + ' at ' + dl.rate + '%' + (dl.reversed ? ' · reversed' : '') + '</span>') + '</td>' +
						'<td data-th="Rep">' + (i ? '' : esc(dl.rep_name)) + '</td><td data-th="Half">' + (l.key === 'deposit' ? 'Deposit' : 'Final') + '</td>' +
						'<td data-th="Invoice">' + (l.invoice ? '<a href="#/billing/doc/' + l.invoice.id + '">' + esc(l.invoice.number) + '</a> ' + bpill(l.invoice.status) : '<span class="bx-sub">' + (l.trigger ? 'Recorded on deal ' + bdate(l.trigger, false) : 'No invoice yet') + '</span>') + '</td>' +
						'<td data-th="Commission" class="num"><b>' + bm(l.amount) + '</b></td><td data-th="Status">' + legState(l) + (l.in_batch ? ' <a class="bx-chip" href="#/billing/batch/' + l.in_batch + '">Batch ' + l.in_batch + '</a>' : '') + '</td>' +
						'<td class="axh-row-acts">' + (l.state !== 'paid' && l.state !== 'cancelled' ? (l.hold ? '<button class="axh-linkbtn" data-adj="' + l.hold.id + '" data-act="release">Release hold</button>' : '<button class="axh-linkbtn" data-hold="' + dl.lead_id + '" data-leg="' + l.key + '">Hold…</button>') : '') + '</td></tr>';
				}).join('');
			}).join('') + '</tbody></table></div>' +
			'<div class="bx-selbar" data-selbar hidden><span data-seltxt></span><button class="axh-btn axh-btn--quiet axh-btn--sm" data-selclear>Clear</button><button class="axh-btn axh-btn--primary" data-mkbatch>' + icon('money', 15) + ' Create payout batch</button></div>';
		return h;
	}
	function bindCommissions() {
		var upd = function () {
			var bar = document.querySelector('[data-selbar]'); if (!bar) return;
			var n = 0, amt = 0, reps = {};
			document.querySelectorAll('[data-csel]').forEach(function (c) { if (c.checked) { n++; amt += Number(c.getAttribute('data-amt')); reps[c.getAttribute('data-rep')] = 1; } });
			bar.hidden = !n;
			var t = bar.querySelector('[data-seltxt]'); if (t) t.innerHTML = '<b>' + n + '</b> selected · <b>' + bm(amt) + '</b> to ' + Object.keys(reps).length + ' rep' + (Object.keys(reps).length === 1 ? '' : 's') + ' (before clawbacks)';
		};
		var v = el('axh-view');
		v.onchange = function (e) { if (e.target.hasAttribute('data-csel')) { B.sel[e.target.getAttribute('data-csel')] = e.target.checked; upd(); } };
		upd();
		if (B.ctab === '1099') load1099(B.y1099 || B.com.year);
	}
	function batchModal() {
		var d = B.com, legs = [], reps = {};
		document.querySelectorAll('[data-csel]:checked').forEach(function (c) { var k = c.getAttribute('data-csel').split(':'); legs.push({ lead_id: Number(k[0]), leg: k[1] }); reps[c.getAttribute('data-rep')] = (reps[c.getAttribute('data-rep')] || 0) + Number(c.getAttribute('data-amt')); });
		if (!legs.length) return;
		var rinfo = function (id) { return (d.reps || []).filter(function (r) { return String(r.rep_id) === String(id); })[0] || {}; };
		var notReady = Object.keys(reps).filter(function (id) { var r = rinfo(id); return r.readiness && !r.readiness.ready; });
		var m = modal('Create payout batch',
			'<p class="axh-hint" style="margin-bottom:12px">One check per rep. Open clawbacks are deducted automatically. Nothing is marked paid until you press <b>Mark paid</b> on the batch.</p>' +
			'<div class="bx-batchreps">' + Object.keys(reps).map(function (id) {
				var r = rinfo(id), net = Math.max(0, reps[id] - (r.clawback || 0));
				return '<div class="bx-batchrep"><div><b>' + esc(r.name || 'Rep') + '</b><span>' + bm(reps[id]) + (r.clawback > 0 ? ' − ' + bm(Math.min(r.clawback, reps[id])) + ' clawback' : '') + ' = <b>' + bm(net) + '</b></span>' + (r.payee ? '<span class="bx-sub">To ' + esc(r.payee.payable_to) + '</span>' : '<span class="bx-sub bx-bad">No payout address</span>') + '</div>' +
					'<input class="axh-input" data-chk="' + id + '" placeholder="Check #" aria-label="Check number for ' + esc(r.name || '') + '"></div>';
			}).join('') + '</div><div class="axh-form-grid"><div class="axh-field"><label for="bx-bd">Pay date</label><input id="bx-bd" type="date" data-bt="pay_date" value="' + today() + '"></div>' +
			'<div class="axh-field"><label for="bx-bm">Method</label><select id="bx-bm" data-bt="method">' + (d.methods || ['Check']).map(function (x) { return '<option>' + esc(x) + '</option>'; }).join('') + '</select></div>' +
			'<div class="axh-field axh-span"><label for="bx-bn">Note</label><input id="bx-bn" type="text" data-bt="note" placeholder="October commissions"></div></div>' +
			(notReady.length ? '<div class="axh-warn"><b>' + icon('flag', 14) + ' Onboarding incomplete</b><p>' + notReady.map(function (id) { var r = rinfo(id); return esc(r.name) + ': ' + esc(r.readiness.missing.join(', ')); }).join('<br>') + '</p><label class="axh-consent axh-consent--warn" style="margin:10px 0 0"><input type="checkbox" data-bt-ovr><span><b>Pay anyway.</b> I have this covered another way. (Logged.)</span></label></div>' : ''),
			'<button class="axh-btn axh-btn--quiet" data-close>Cancel</button><button class="axh-btn axh-btn--primary" data-go>Create batch</button>');
		m.querySelector('[data-go]').onclick = function () {
			var b = { legs: legs, checks: {} }, btn = this;
			m.querySelectorAll('[data-bt]').forEach(function (i) { b[i.getAttribute('data-bt')] = i.value; });
			m.querySelectorAll('[data-chk]').forEach(function (i) { b.checks[i.getAttribute('data-chk')] = i.value; });
			var ov = m.querySelector('[data-bt-ovr]'); if (ov) { if (!ov.checked) { toast('Tick “Pay anyway” to continue.', 'bad'); return; } b.override = true; }
			btn.disabled = true;
			api('/billing/batches', { method: 'POST', body: b }).then(function (r) { closeModal(); B.sel = {}; toast('Batch ' + r.id + ' created.', 'ok'); location.hash = '#/billing/batch/' + r.id; }).catch(function (e) { btn.disabled = false; bfail(e); });
		};
	}
	function batchesTab(d) {
		var bs = d.batches || [];
		if (!bs.length) return '<div class="axh-card">' + bempty('No batches yet', 'In <b>By rep &amp; deal</b>, tick the commissions that are ready and press <b>Create payout batch</b>.', '') + '</div>';
		return '<div class="axh-card bx-card bx-card--flush"><table class="axh-table axh-table--stack"><thead><tr><th>Batch</th><th>Status</th><th>Pay date</th><th>Method</th><th class="num">Reps</th><th class="num">Gross</th><th class="num">Deductions</th><th class="num">Net</th></tr></thead><tbody>' +
			bs.map(function (b) { return '<tr data-href="#/billing/batch/' + b.id + '"><td data-th="Batch"><b class="bx-strong">' + esc(b.label) + '</b><span class="bx-sub">' + esc(b.note || '') + '</span></td><td data-th="Status">' + batchPill(b.status) + '</td><td data-th="Pay date">' + bdate(b.pay_date) + '</td><td data-th="Method">' + esc(b.method) + '</td><td data-th="Reps" class="num">' + b.reps + '</td><td data-th="Gross" class="num">' + bm(b.gross) + '</td><td data-th="Deductions" class="num">' + (b.deductions ? '−' + bm(b.deductions) : '—') + '</td><td data-th="Net" class="num"><b>' + bm(b.total) + '</b></td></tr>'; }).join('') + '</tbody></table></div>';
	}
	function batchPill(s) { return '<span class="bx-pill bx-pill--' + (s === 'paid' ? 'paid' : s === 'void' ? 'void' : 'sent') + '">' + (s === 'draft' ? 'Ready to pay' : s === 'paid' ? 'Paid' : 'Void') + '</span>'; }
	function adjustTab(d) {
		var claw = d.clawbacks || [], holds = d.holds || [], over = d.overpaid || [];
		var h = '';
		if (over.length) h += '<div class="axh-alert bx-alert"><b>Overpaid on reduced or reversed deals (agreement 3.6):</b> ' + over.map(function (o) { return esc(o.company) + ' · ' + esc(o.rep_name) + ' ' + bm(o.amount) + ' <button class="axh-linkbtn" data-claw-over="' + o.lead_id + '" data-amt="' + o.amount + '">Add to clawbacks</button>'; }).join(' · ') + '</div>';
		h += '<div class="bx-grid2"><div class="axh-card bx-card"><div class="bx-card__h"><div><h3>Clawback ledger</h3><span>Refunds and voided client payments after commission was paid open these automatically.</span></div><button class="axh-btn axh-btn--ghost axh-btn--sm" data-claw-new>' + icon('plus', 13) + ' Add</button></div>' +
			(claw.length ? '<ul class="bx-ledger">' + claw.slice().reverse().map(function (c) {
				return '<li><div><b>' + bm(c.amount) + '</b> · ' + esc(c.rep_name) + (c.company ? ' · ' + esc(c.company) : '') + '<span>' + esc(c.reason) + '</span><em>' + when(c.created_at) + (c.batch_id ? ' · deducted in <a href="#/billing/batch/' + c.batch_id + '">Batch ' + c.batch_id + '</a>' : '') + '</em></div>' +
					'<span class="bx-chip ' + (c.status === 'open' ? 'bx-chip--bad' : 'bx-chip--ok') + '">' + esc({ open: 'Open', applied: 'Deducted', recovered: 'Recovered', waived: 'Waived' }[c.status] || c.status) + '</span>' +
					(c.status === 'open' ? '<span class="bx-row"><button class="axh-linkbtn" data-adj="' + c.id + '" data-act="recover">Recovered</button><button class="axh-linkbtn" data-adj="' + c.id + '" data-act="waive">Waive</button></span>' : '') + '</li>';
			}).join('') + '</ul>' : '<p class="axh-hint">No clawbacks.</p>') + '</div>' +
			'<div class="axh-card bx-card"><div class="bx-card__h"><div><h3>Holds</h3><span>Commission on hold can’t be batched or marked paid.</span></div></div>' +
			(holds.length ? '<ul class="bx-ledger">' + holds.slice().reverse().map(function (x) {
				return '<li><div><b>' + esc(x.company || 'Deal') + '</b> · ' + esc(x.rep_name) + ' · ' + esc(x.leg === 'both' ? 'both halves' : x.leg + ' half') + '<span>' + esc(x.reason) + '</span><em>' + when(x.created_at) + ' by ' + esc(x.by) + '</em></div>' +
					'<span class="bx-chip ' + (x.status === 'open' ? 'bx-chip--warn' : '') + '">' + (x.status === 'open' ? 'On hold' : 'Released') + '</span>' + (x.status === 'open' ? '<button class="axh-linkbtn" data-adj="' + x.id + '" data-act="release">Release</button>' : '') + '</li>';
			}).join('') + '</ul>' : '<p class="axh-hint">Nothing on hold. Use <b>Hold…</b> next to any unpaid half in By rep &amp; deal.</p>') + '</div></div>';
		return h;
	}
	function load1099(year) {
		B.y1099 = year;
		Promise.all([api('/billing/1099?year=' + year)]).then(function (r) {
			var s = r[0], box = document.querySelector('[data-1099]'); if (!box) return;
			var y0 = new Date().getFullYear(), yrs = [y0, y0 - 1, y0 - 2];
			box.innerHTML = '<div class="axh-card bx-card"><div class="bx-card__h"><div><h3>1099-NEC worksheet</h3><span>Commission paid to each rep in the calendar year. Reporting threshold for ' + s.year + ': $' + Number(s.threshold).toLocaleString() + (s.year >= 2026 ? ' (raised from $600 for payments from 2026; confirm with your tax preparer)' : '') + '.</span></div>' +
				'<div class="bx-row"><select class="axh-input" data-y1099 aria-label="Year">' + yrs.map(function (y) { return '<option' + (y === s.year ? ' selected' : '') + '>' + y + '</option>'; }).join('') + '</select><button class="axh-btn axh-btn--ghost axh-btn--sm" data-bx-export="1099" data-fmt="csv" data-year="' + s.year + '">' + icon('download', 13) + ' Export CSV</button></div></div>' +
				(s.rows.length ? '<table class="axh-table axh-table--stack"><thead><tr><th>Rep</th><th>Payee</th><th>W-9</th><th class="num">Gross paid</th><th class="num">Deductions</th><th class="num">Net</th><th>1099</th></tr></thead><tbody>' +
					s.rows.map(function (x) { return '<tr><td data-th="Rep"><b class="bx-strong">' + esc(x.legal_name || x.name) + '</b>' + (x.business_name ? '<span class="bx-sub">' + esc(x.business_name) + '</span>' : '') + '</td><td data-th="Payee">' + esc(x.payable_to || '—') + '<span class="bx-sub">' + esc(x.address) + '</span></td><td data-th="W-9">' + (x.w9 ? '<span class="bx-chip bx-chip--ok">On file</span>' : '<span class="bx-chip bx-chip--bad">Missing</span>') + '</td><td data-th="Gross" class="num">' + bm(x.gross) + '</td><td data-th="Deductions" class="num">' + (x.deduct ? '−' + bm(x.deduct) : '—') + '</td><td data-th="Net" class="num"><b>' + bm(x.net) + '</b></td><td data-th="1099">' + (x.over ? '<span class="bx-chip bx-chip--o">File</span>' : '<span class="bx-chip">Under threshold</span>') + '</td></tr>'; }).join('') + '</tbody></table>'
					: '<p class="axh-hint">No commission was paid in ' + s.year + '.</p>') + '<p class="axh-hint" style="margin-top:12px">Taxpayer ID numbers are never shown here. They stay in each rep’s encrypted W-9 under Onboarding.</p></div>';
			var sel = box.querySelector('[data-y1099]'); if (sel) sel.onchange = function () { load1099(Number(sel.value)); };
		}).catch(bfail);
	}
	function viewBatch(b) {
		var html = bhead(esc(b.label), batchPill(b.status) + ' · ' + esc(b.method) + ' · ' + bdate(b.pay_date) + (b.note ? ' · ' + esc(b.note) : ''),
			(b.status === 'draft' ? '<button class="axh-btn axh-btn--ghost" data-batch-void="' + b.id + '">Void batch</button><button class="axh-btn axh-btn--primary" data-batch-pay="' + b.id + '">' + icon('check', 15) + ' Mark paid</button>' : '') +
			(b.status === 'paid' ? '<button class="axh-btn axh-btn--ghost" data-batch-void="' + b.id + '">Void batch…</button>' : ''), ['#/billing/commissions', 'Commissions']);
		html += '<div class="bx-kpis bx-kpis--3">' + bstat('Gross commission', bm(b.gross), b.legs + ' half' + (b.legs === 1 ? '' : 'ves')) + bstat('Deductions', b.deductions ? '−' + bm(b.deductions) : bm(0), 'Clawbacks applied') + bstat('Net to pay', bm(b.total), b.reps + ' check' + (b.reps === 1 ? '' : 's'), 'accent') + '</div>';
		html += (b.items || []).map(function (r) {
			return '<div class="axh-card bx-card bx-batchcard"><div class="bx-card__h"><div><h3>' + esc(r.name) + '</h3><span>' + (r.payee ? 'Payable to ' + esc(r.payee.payable_to) + ' · ' + r.payee.lines.map(esc).join(', ') : 'No payout address on file') + '</span></div>' +
				'<div class="bx-row">' + (b.status === 'draft' ? '<input class="axh-input bx-chkin" data-bchk="' + r.rep_id + '" value="' + esc(r.check_no || '') + '" placeholder="Check #" aria-label="Check number">' : (r.check_no ? '<span class="bx-chip">Check #' + esc(r.check_no) + '</span>' : '')) +
				'<a class="axh-btn axh-btn--ghost axh-btn--sm" href="#/billing/statement/' + b.id + '/' + r.rep_id + '">' + icon('doc', 13) + ' Statement</a></div></div>' +
				'<table class="axh-table axh-table--stack"><thead><tr><th>Client</th><th>Half</th><th>Client payment cleared</th><th class="num">Contract</th><th class="num">Rate</th><th class="num">Amount</th></tr></thead><tbody>' +
				r.legs.map(function (l) { return '<tr><td data-th="Client"><b class="bx-strong">' + esc(l.company) + '</b>' + (l.invoice ? '<span class="bx-sub">' + esc(l.invoice) + '</span>' : '') + '</td><td data-th="Half">' + (l.leg === 'deposit' ? 'Deposit' : 'Final') + '</td><td data-th="Cleared">' + bdate(l.trigger) + '</td><td data-th="Contract" class="num">' + bm0(l.value) + '</td><td data-th="Rate" class="num">' + l.rate + '%</td><td data-th="Amount" class="num">' + bm(l.amount) + '</td></tr>'; }).join('') +
				(r.deductions || []).map(function (x) { return '<tr class="bx-ded"><td colspan="5" data-th="Deduction">Less: ' + esc(x.reason) + '</td><td class="num">−' + bm(x.amount) + '</td></tr>'; }).join('') +
				'</tbody><tfoot><tr><td colspan="5">Net</td><td class="num"><b>' + bm(r.net) + '</b></td></tr></tfoot></table></div>';
		}).join('');
		return '<div class="bx">' + html + '</div>';
	}

	/* A printable statement (rep or AdaptExec). */
	function viewStatement(s, mgr) {
		var b = s.business, r = s.rep;
		var back = mgr && S.boot.user.manager ? ['#/billing/batch/' + s.batch.id, s.batch.label] : ['#/statements', 'Statements'];
		return '<div class="bx">' + bhead('Commission statement', esc(s.batch.label) + ' · paid ' + bdate(s.batch.pay_date), '<button class="axh-btn axh-btn--primary" data-bx-printpage>' + icon('printer', 15) + ' Print / Save PDF</button>', back) +
			'<article class="bx-stmt"><header class="bx-stmt__h"><div><img src="' + esc(AXH.assets + 'img/logo-dark.svg') + '" alt="AdaptExec" height="34"><address><b>' + esc(b.name) + '</b>' + (b.address ? '<br>' + esc(b.address).replace(/\n/g, '<br>') : '') + '<br>' + esc(b.email) + '</address></div>' +
			'<div class="bx-stmt__t"><span class="thunder">Commission statement</span><dl><dt>Statement</dt><dd>' + esc(s.batch.label) + '</dd><dt>Paid</dt><dd>' + bdate(s.batch.pay_date) + '</dd><dt>Method</dt><dd>' + esc(s.batch.method) + (s.check_no ? ' #' + esc(s.check_no) : '') + '</dd></dl></div></header>' +
			'<div class="bx-stmt__to"><div><span>Paid to</span><b>' + esc(r.payee ? r.payee.payable_to : r.legal_name) + '</b>' + (r.payee ? r.payee.lines.map(esc).join('<br>') : '') + '</div><div><span>Representative</span><b>' + esc(r.legal_name) + '</b>' + (r.business_name ? esc(r.business_name) : '') + '</div><div><span>Net this statement</span><b class="thunder bx-stmt__net">' + bm(s.net) + '</b></div></div>' +
			'<table class="bx-stmt__tb"><thead><tr><th>Client</th><th>Half</th><th>Client payment cleared</th><th class="num">Contract</th><th class="num">Rate</th><th class="num">Commission</th></tr></thead><tbody>' +
			s.lines.map(function (l) { return '<tr><td><b>' + esc(l.company) + '</b>' + (l.invoice ? ' <span>' + esc(l.invoice) + '</span>' : '') + '</td><td>' + (l.leg === 'deposit' ? 'Deposit' : 'Final') + '</td><td>' + bdate(l.trigger) + '</td><td class="num">' + bm(l.value) + '</td><td class="num">' + l.rate + '%</td><td class="num">' + bm(l.amount) + '</td></tr>'; }).join('') +
			'</tbody></table><div class="bx-stmt__sum"><div><span>Gross commission</span><b>' + bm(s.gross) + '</b></div>' + (s.deductions || []).map(function (x) { return '<div class="bx-ded"><span>Less: ' + esc(x.reason) + '</span><b>−' + bm(x.amount) + '</b></div>'; }).join('') +
			'<div class="bx-stmt__nett"><span>Net paid</span><b>' + bm(s.net) + '</b></div></div>' +
			'<div class="bx-stmt__ytd"><span class="bx-h3">' + s.year + ' year to date</span><div><span>Gross</span><b>' + bm(s.ytd.gross) + '</b></div><div><span>Deductions</span><b>' + (s.ytd.deduct ? '−' + bm(s.ytd.deduct) : bm(0)) + '</b></div><div><span>Net paid</span><b>' + bm(s.ytd.net) + '</b></div></div>' +
			'<p class="bx-stmt__fine">Commission is earned in two halves under the Independent Contractor Sales Representative Agreement: one when the client’s deposit has fully cleared and one when their final payment has. Deductions are recoveries under section 3.6. Questions: ' + esc(b.email) + '.</p></article></div>';
	}
	function viewStatements(d) {
		var ss = d.statements || [];
		return '<div class="bx">' + bhead('Statements', 'Every commission payment AdaptExec has made to you, with what it covered.', '', ['#/earnings', 'Earnings']) +
			(ss.length ? '<div class="axh-card bx-card bx-card--flush"><table class="axh-table axh-table--stack"><thead><tr><th>Statement</th><th>Paid</th><th>Method</th><th class="num">Halves</th><th class="num">Gross</th><th class="num">Deductions</th><th class="num">Net</th></tr></thead><tbody>' +
				ss.map(function (x) { return '<tr data-href="#/statements/' + x.batch_id + '"><td data-th="Statement"><b class="bx-strong">' + esc(x.label) + '</b></td><td data-th="Paid">' + bdate(x.pay_date) + '</td><td data-th="Method">' + esc(x.method) + (x.check_no ? ' #' + esc(x.check_no) : '') + '</td><td data-th="Halves" class="num">' + x.legs + '</td><td data-th="Gross" class="num">' + bm(x.gross) + '</td><td data-th="Deductions" class="num">' + (x.deduct ? '−' + bm(x.deduct) : '—') + '</td><td data-th="Net" class="num"><b>' + bm(x.net) + '</b></td></tr>'; }).join('') + '</tbody></table></div>'
				: '<div class="axh-card">' + bempty('No statements yet', 'When AdaptExec pays your commission in a batch, the statement appears here.', '') + '</div>') + '</div>';
	}

	/* ------------------------------------------------------------ events */

	document.addEventListener('click', function (e) {
		var t = e.target.closest('[data-bx-new],[data-bx-pdf],[data-bx-copy],[data-bx-send],[data-bx-remind],[data-bx-menu],[data-bx-export],[data-bx-dup],[data-bx-status],[data-bx-relink],[data-bx-del],[data-bx-convert],[data-bx-pay],[data-bx-pact],[data-bx-credit],[data-bx-print],[data-bx-printpage],[data-bx-newcust],[data-bx-editcust],[data-bx-fromlead],[data-bx-item],[data-bx-saveset],[data-bx-runrem],[data-bx-logo],[data-bx-tpl],[data-bf],[data-ctab],[data-mkbatch],[data-selclear],[data-hold],[data-adj],[data-claw-new],[data-claw-over],[data-batch-pay],[data-batch-void],tr[data-href]');
		document.querySelectorAll('.bx-menu__pop:not([hidden])').forEach(function (p) { if (!t || !p.parentNode.contains(t)) p.hidden = true; });
		if (!t) return;
		var a = function (n) { return t.getAttribute(n); };
		if (t.matches('tr[data-href]')) { if (e.target.closest('a,button,input,select,label')) return; location.hash = a('data-href'); return; }
		if (t.hasAttribute('data-bx-menu')) { var pop = t.parentNode.querySelector('.bx-menu__pop'); pop.hidden = !pop.hidden; return; }
		if (t.hasAttribute('data-bx-new')) { newDocModal(a('data-bx-new'), { customer_id: a('data-cust') }); return; }
		if (t.hasAttribute('data-bx-copy')) {
			e.preventDefault();
			copyText(a('data-bx-copy')).then(function () {
				toast('Customer link copied.', 'ok');
				api('/billing/docs/' + a('data-id') + '/link', { method: 'POST', body: { action: 'copied' } }).then(function (r) { if (B.cur && B.cur.doc.id === r.doc.id && B.cur.doc.status !== r.doc.status) route(); }).catch(function () {});
			});
			return;
		}
		if (t.hasAttribute('data-bx-send')) { sendModal(a('data-bx-send'), false); return; }
		if (t.hasAttribute('data-bx-remind')) { sendModal(a('data-bx-remind'), true); return; }
		if (t.hasAttribute('data-bx-export')) {
			t.disabled = true;
			api('/billing/export?kind=' + a('data-bx-export') + '&format=' + (a('data-fmt') || 'csv') + (a('data-year') ? '&year=' + a('data-year') : '')).then(function (r) { t.disabled = false; bdl(r); toast('Downloaded ' + r.filename + '.', 'ok'); }).catch(function (er) { t.disabled = false; bfail(er); });
			return;
		}
		if (t.hasAttribute('data-bx-dup')) { api('/billing/docs/' + a('data-bx-dup') + '/duplicate', { method: 'POST', body: { type: a('data-type') || '' } }).then(function (r) { toast('Copied to ' + r.doc.number + '.', 'ok'); location.hash = '#/billing/edit/' + r.doc.id; }).catch(bfail); return; }
		if (t.hasAttribute('data-bx-status')) { statusModal(a('data-bx-status')); return; }
		if (t.hasAttribute('data-bx-relink')) { var rlid = B.cur.doc.id; ask({ title: 'Make a new private link?', body: 'The old link stops working immediately.', ok: 'Make a new link' }).then(function () { api('/billing/docs/' + rlid + '/link', { method: 'POST', body: { action: 'regenerate' } }).then(function () { toast('New link created.', 'ok'); route(); }).catch(bfail); }); return; }
		if (t.hasAttribute('data-bx-del')) { var bdid = a('data-bx-del'); ask({ title: 'Delete this draft?', body: 'This cannot be undone.', ok: 'Delete draft', danger: true }).then(function () { api('/billing/docs/' + bdid, { method: 'DELETE' }).then(function () { toast('Draft deleted.', 'ok'); location.hash = '#/billing/docs'; }).catch(bfail); }); return; }
		if (t.hasAttribute('data-bx-convert')) { t.disabled = true; api('/billing/docs/' + a('data-bx-convert') + '/convert', { method: 'POST', body: {} }).then(function (r) { toast(r.invoices.length + ' invoice' + (r.invoices.length === 1 ? '' : 's') + ' created as drafts.', 'ok'); location.hash = '#/billing/doc/' + r.invoices[0]; }).catch(function (er) { t.disabled = false; bfail(er); }); return; }
		if (t.hasAttribute('data-bx-pay')) { payModal(a('data-bx-pay')); return; }
		if (t.hasAttribute('data-bx-pact')) { payAction(a('data-pid'), a('data-bx-pact')); return; }
		if (t.hasAttribute('data-bx-credit')) { api('/billing/credit', { method: 'POST', body: { doc_id: Number(a('data-bx-credit')) } }).then(function () { toast('Credit applied.', 'ok'); route(); }).catch(bfail); return; }
		if (t.hasAttribute('data-bx-print')) { if (B.cur && B.cur.doc && document.querySelector('.bx-docgrid .bx-frame iframe')) { bxPrintDoc(B.cur.doc); return; } var w = window.open(a('data-bx-print'), '_blank'); if (w) w.addEventListener('load', function () { setTimeout(function () { w.print(); }, 600); }); return; }
		if (t.hasAttribute('data-bx-printpage')) { window.print(); return; }
		if (t.hasAttribute('data-bx-pdf')) { bxPrintDoc(B.cur.doc); return; }
		if (t.hasAttribute('data-bx-newcust')) { customerModal(null, function (c) { location.hash = '#/billing/customer/' + c.id; }); return; }
		if (t.hasAttribute('data-bx-editcust')) { customerModal(B.cust.customer); return; }
		if (t.hasAttribute('data-bx-fromlead')) {
			var then = a('data-then');
			api('/billing/customers/from-lead/' + a('data-bx-fromlead'), { method: 'POST', body: {} }).then(function (c) {
				if (then === 'customer') { toast('Billing customer ready.', 'ok'); location.hash = '#/billing/customer/' + c.id; } else newDocModal(then, { customer_id: c.id });
			}).catch(bfail);
			return;
		}
		if (t.hasAttribute('data-bx-item')) { itemModal(a('data-bx-item')); return; }
		if (t.hasAttribute('data-bx-saveset')) { t.disabled = true; saveBillSettings().then(function () { t.disabled = false; }); return; }
		if (t.hasAttribute('data-bx-runrem')) { api('/billing/reminders/run', { method: 'POST', body: {} }).then(function (r) { toast(r.sent + ' reminder' + (r.sent === 1 ? '' : 's') + ' sent.', 'ok'); }).catch(bfail); return; }
		if (t.hasAttribute('data-bx-logo')) { imagePicker(function (u) { var i = document.querySelector('[data-bs="logo"]'); if (i) i.value = u; }); return; }
		if (t.hasAttribute('data-bx-tpl')) {
			var act = a('data-bx-tpl');
			var body = { id: a('data-id') }; body[act] = true;
			ask({ title: act === 'reset' ? 'Reset this template?' : 'Remove this template?', body: act === 'reset' ? 'It goes back to the built-in version.' : 'It is taken off the list.', ok: act === 'reset' ? 'Reset' : 'Remove', danger: act !== 'reset' }).then(function () {
				api('/billing/templates', { method: 'POST', body: body }).then(function (r) { B.boot.templates = r.templates; toast('Done.', 'ok'); route(); }).catch(bfail);
			});
			return;
		}
		if (t.hasAttribute('data-bf')) { if (t.tagName === 'SELECT') return; B.f[a('data-bf')] = a('data-v'); route(); return; }
		if (t.hasAttribute('data-ctab')) { B.ctab = a('data-ctab'); paint(viewCommissions(B.com)); bindCommissions(); return; }
		if (t.hasAttribute('data-mkbatch')) { batchModal(); return; }
		if (t.hasAttribute('data-selclear')) { B.sel = {}; document.querySelectorAll('[data-csel]').forEach(function (c) { c.checked = false; c.dispatchEvent(new Event('change', { bubbles: true })); }); return; }
		if (t.hasAttribute('data-hold')) {
			var m = modal('Put commission on hold', '<p class="axh-hint" style="margin-bottom:12px">A held half can’t be batched or marked paid until you release it. Use it for a disputed charge, a chargeback, or anything that looks wrong.</p>' +
				'<div class="axh-field"><label for="bx-hl">Which half</label><select id="bx-hl"><option value="' + a('data-leg') + '">' + (a('data-leg') === 'deposit' ? 'Deposit' : 'Final') + ' half</option><option value="both">Both halves</option></select></div><div class="axh-field"><label for="bx-hr">Reason</label><input id="bx-hr" type="text" placeholder="Client disputed the card charge"></div>',
				'<button class="axh-btn axh-btn--quiet" data-close>Cancel</button><button class="axh-btn axh-btn--primary" data-go>Put on hold</button>');
			var lid = a('data-hold');
			m.querySelector('[data-go]').onclick = function () { api('/billing/holds', { method: 'POST', body: { lead_id: Number(lid), leg: m.querySelector('#bx-hl').value, reason: m.querySelector('#bx-hr').value } }).then(function () { closeModal(); toast('On hold.', 'ok'); route(); }).catch(bfail); };
			return;
		}
		if (t.hasAttribute('data-adj')) { api('/billing/adjust/' + a('data-adj'), { method: 'POST', body: { action: a('data-act') } }).then(function () { toast('Updated.', 'ok'); route(); }).catch(bfail); return; }
		if (t.hasAttribute('data-claw-over')) { api('/billing/clawbacks', { method: 'POST', body: { lead_id: Number(a('data-claw-over')), amount: Number(a('data-amt')), reason: 'Overpaid on a reduced or reversed deal (agreement 3.6)' } }).then(function () { toast('Added to the clawback ledger.', 'ok'); route(); }).catch(bfail); return; }
		if (t.hasAttribute('data-claw-new')) {
			var m2 = modal('Add a clawback', '<div class="axh-form-grid"><div class="axh-field"><label for="bx-cr">Rep</label>' + repSelect('id="bx-cr"', 0) + '</div><div class="axh-field"><label for="bx-ca">Amount ($)</label><input id="bx-ca" inputmode="decimal"></div><div class="axh-field axh-span"><label for="bx-cn">Reason</label><input id="bx-cn" type="text" placeholder="Client refund on…"></div></div>',
				'<button class="axh-btn axh-btn--quiet" data-close>Cancel</button><button class="axh-btn axh-btn--primary" data-go>Add</button>');
			m2.querySelector('[data-go]').onclick = function () { api('/billing/clawbacks', { method: 'POST', body: { rep_id: Number(m2.querySelector('#bx-cr').value), amount: m2.querySelector('#bx-ca').value, reason: m2.querySelector('#bx-cn').value } }).then(function () { closeModal(); toast('Added.', 'ok'); route(); }).catch(bfail); };
			return;
		}
		if (t.hasAttribute('data-batch-pay')) {
			var checks = {}; document.querySelectorAll('[data-bchk]').forEach(function (i) { checks[i.getAttribute('data-bchk')] = i.value; });
			var bpid = a('data-batch-pay');
			ask({ title: 'Mark this batch paid?', body: 'Each commission half is recorded as paid and each rep is notified.', ok: 'Mark paid' }).then(function () {
				t.disabled = true;
				api('/billing/batches/' + bpid + '/pay', { method: 'POST', body: { checks: checks } }).then(function (r) { toast(r.errors && r.errors.length ? 'Paid, with problems: ' + r.errors.join('; ') : 'Batch marked paid.', r.errors && r.errors.length ? 'bad' : 'ok'); route(); }).catch(function (er) { t.disabled = false; bfail(er); });
			});
			return;
		}
		if (t.hasAttribute('data-batch-void')) {
			var bvid = a('data-batch-void');
			ask({ title: 'Void this batch?', body: 'Payments recorded by it are voided and clawbacks reopened.', ok: 'Void batch', danger: true, input: { label: 'Reason', placeholder: 'e.g. wrong check numbers' } }).then(function (why) {
				api('/billing/batches/' + bvid + '/void', { method: 'POST', body: { reason: why } }).then(function () { toast('Batch voided.', 'ok'); route(); }).catch(bfail);
			});
		}
	});
	document.addEventListener('change', function (e) {
		var t = e.target;
		if (!t.closest || !t.closest('.bx')) return;
		if (t.hasAttribute('data-bf') && t.tagName === 'SELECT') { B.f[t.getAttribute('data-bf')] = t.value; route(); return; }
		if (t.hasAttribute('data-bsort')) { B.sort = t.value; paint(viewBillDocs(!S.boot.user.manager)); return; }
		if (t.hasAttribute('data-pf')) { B.pf[t.getAttribute('data-pf')] = t.type === 'checkbox' ? (t.checked ? 1 : 0) : t.value; route(); }
	});
	var bxTimer;
	document.addEventListener('input', function (e) {
		var t = e.target;
		if (!t.closest || !t.closest('.bx')) return;
		var k = t.hasAttribute('data-bf-q') ? 'f' : t.hasAttribute('data-cf-q') ? 'cf' : t.hasAttribute('data-pf-q') ? 'pf' : '';
		if (!k) return;
		clearTimeout(bxTimer);
		bxTimer = setTimeout(function () { B[k].q = t.value; route().then(function () { var i = document.querySelector('[data-' + k + '-q]'); if (i) { i.focus(); i.setSelectionRange(i.value.length, i.value.length); } }); }, 300);
	});
	window.addEventListener('beforeunload', function (e) { if (ED && ED.dirty && location.hash.indexOf('#/billing/edit') === 0) { e.preventDefault(); e.returnValue = ''; } });

	/* --------------------------------------------------------- routing */

	function paint(html) { el('axh-view').innerHTML = html; nav(); if (window.AXStylePicker) window.AXStylePicker.watch(el('axh-view')); return true; }
	function render(html) { return paint(html); }

	/* Every page load ends in a page: if a request fails (offline, expired
	   session, server error) show the reason and a retry instead of leaving
	   the "Loading..." spinner up forever. */
	function route() {
		if (S.boot && S.boot.user) document.title = 'Hub · ' + S.boot.user.name;
		var seq = (S.routeSeq || 0) + 1;
		var p;
		try { p = routeView(); } catch (e) { p = Promise.reject(e); }
		if (p && typeof p.then === 'function') {
			p.then(null, function (e) {
				if (seq !== S.routeSeq) return;
				paint('<div class="axh-card">' + empty('Could not load this', esc((e && e.message) || 'Something went wrong.'),
					'<button class="axh-btn axh-btn--primary" onclick="location.reload()">Try again</button>') + '</div>');
			});
		}
		return p;
	}

	function routeView() {
		// A slow response for a page the user already left must not paint over
		// the page they are on now: each navigation gets a number, and only the
		// latest one may render.
		var seq = S.routeSeq = (S.routeSeq || 0) + 1;
		var render = function (html) { return seq === S.routeSeq ? paint(html) : false; };
		var h = (location.hash || '#/').replace('#/', '');
		var parts = h.split('/');
		var v = el('axh-view');
		if (S.agrScroll) { window.removeEventListener('scroll', S.agrScroll); window.removeEventListener('resize', S.agrScroll); S.agrScroll = null; }
		if (S.agrResize) { window.removeEventListener('resize', S.agrResize); S.agrResize = null; }
		v.innerHTML = '<div class="axh-boot"><span class="axh-spin"></span> Loading…</div>';
		window.scrollTo(0, 0);

		if (parts[0] === 'billing') return billingRoute(parts.slice(1), render);
		if (parts[0] === 'statements') {
			if (parts[1]) return api('/billing/statements/' + parts[1] + '/' + S.boot.user.id).then(function (d) { render(viewStatement(d, false)); });
			return api('/billing/statements').then(function (d) { render(viewStatements(d)); });
		}
		if (parts[0] === 'lead' && parts[1]) {
			return api('/leads/' + parts[1]).then(function (d) { S.lead = d; if (render(viewLead(d))) { billLeadCard(d.lead.id); p1LeadCard(d.lead.id); } })
				.catch(function (e) { render('<div class="axh-card">' + empty('Not found', e.message, '') + '</div>'); });
		}
		if (parts[0] === 'discovery' && parts[1]) {
			return api('/leads/' + parts[1] + '?schema=1').then(function (d) { S.lead = d; render(viewDiscovery(d)); })
				.catch(function (e) { render('<div class="axh-card">' + empty('Not found', e.message, '') + '</div>'); });
		}
		if (parts[0] === 'styles') return refresh().then(function () { if (render(viewStyles())) mountInlineStyles(); });
		if (parts[0] === 'settings') return api('/me').then(function (d) { S.me = d; render(viewSettings(d)); });
		if (parts[0] === 'onboarding') {
			if (S.boot.user.manager) { location.hash = '#/manage/onboarding'; return; }
			var ofail = function (e) { render('<div class="axh-card">' + empty('Could not load this', esc(e.message), '<button class="axh-btn axh-btn--primary" onclick="location.reload()">Try again</button>') + '</div>'); };
			var keep = function (d) { S.onb = d; S.boot.onboarding = d.status; return d; };
			if (parts[1] === 'agreement') {
				return Promise.all([api('/onboarding'), api('/onboarding/agreement')]).then(function (r) { keep(r[0]); S.agr = r[1]; if (render(viewAgreement(r[0], r[1]))) bindAgreement(); }).catch(ofail);
			}
			if (parts[1] === 'w9') return api('/onboarding').then(function (d) { keep(d); if (render(viewW9(d))) bindW9(); }).catch(ofail);
			if (parts[1] === 'payment') return api('/onboarding').then(function (d) { keep(d); if (render(viewPayment(d))) bindPayment(); }).catch(ofail);
			return api('/onboarding').then(function (d) { keep(d); render(viewOnboarding(d)); }).catch(ofail);
		}
		if (parts[0] === 'manage' && !S.boot.user.manager) { location.hash = '#/'; return; }
		if (parts[0] === 'manage') {
			var fail = function (e) { render('<div class="axh-card">' + empty('Could not load this', esc(e.message), '<button class="axh-btn axh-btn--primary" onclick="location.reload()">Try again</button>') + '</div>'); };
			if (parts[1] === 'onboarding') {
				if (parts[2] === 'rep' && parts[3]) return api('/manage/onboarding/rep/' + parts[3]).then(function (d) { S.onbRep = d; S.onbSigner = d.signer; render(viewOnbRep(d)); }).catch(fail);
				if (parts[2] === 'settings') return api('/manage/onboarding/settings').then(function (d) { S.onbSet = d; S.onbSigner = d.settings.signer; render(viewOnbSettings(d)); }).catch(fail);
				return api('/manage/onboarding').then(function (d) { S.onbSigner = d.settings.signer; render(viewOnbManage(d)); }).catch(fail);
			}
			if (parts[1] === 'briefs') return api('/manage/discovery').then(function (d) { render(viewBriefs(d)); }).catch(fail);
			if (parts[1] === 'team') return api('/manage/team').then(function (d) { S.team = d.reps; render(viewTeam(d)); }).catch(fail);
			if (parts[1] === 'rep' && parts[2]) return api('/manage/team/' + parts[2]).then(function (d) { S.repView = d; render(viewRep(d)); }).catch(fail);
			if (parts[1] === 'deals') {
				var f = S.dealFilter || (S.dealFilter = { status: 'won' }), q = [];
				Object.keys(f).forEach(function (k) { if (f[k]) q.push(k + '=' + encodeURIComponent(f[k])); });
				return api('/manage/deals?' + q.join('&')).then(function (d) { render(viewDeals(d)); }).catch(fail);
			}
			if (parts[1] === 'payouts') return api('/manage/payouts').then(function (d) { render(viewPayouts(d)); }).catch(fail);
			return api('/manage/overview').then(function (d) {
				S.boot.summary = d.summary || S.boot.summary;
				render(parts[1] === 'registrations' ? viewRegistrations(d) : viewManage(d));
			}).catch(fail);
		}
		if (parts[0] === 'form' && parts[1]) {
			return api('/forms/' + parts[1]).then(function (d) {
				S.form = d;
				S.formFields = (d.form.fields || []).slice();
				S.formAccent = d.form.accent;
				if (!S.boot.formCatalog) {
					return api('/forms').then(function (l) {
						S.boot.formCatalog = l.catalog;
						render(viewForm(d));
						paintQR(d.form.url);
					});
				}
				render(viewForm(d));
				paintQR(d.form.url);
			}).catch(function (e) { render('<div class="axh-card">' + empty('Not found', e.message, '') + '</div>'); });
		}
		if (parts[0] === 'forms') {
			return api('/forms').then(function (d) {
				S.boot.formCatalog = d.catalog;
				S.boot.formDefaults = d.defaults;
				render(viewForms(d));
			});
		}
		if (parts[0] === 'earnings') {
			return api('/earnings').then(function (d) { S.earn = d; render(viewEarnings(d)); });
		}
		if (parts[0] === 'brief' && parts[1]) {
			return api('/manage/brief/' + parts[1]).then(function (d) { render(viewBrief(d)); })
				.catch(function (e) { render('<div class="axh-card">' + empty('Not found', e.message, '') + '</div>'); });
		}
		if (parts[0] === 'demos') return render(viewDemos());
		if (parts[0] === 'present') return render(viewPresent());
		if (parts[0] === 'shares') {
			return api('/p1/shares').then(function (d) { render(viewShares(d)); });
		}
		if (parts[0] === 'link' && parts[1]) {
			return Promise.all([api('/p1/shares/' + parts[1]), S.shareForms ? null : api('/shares').then(function (d) { S.shareDecks = d.decks; S.shareForms = d.forms; })])
				.then(function (r) { render(viewLink(r[0])); })
				.catch(function (e) { render('<div class="axh-card">' + empty('Not found', esc(e.message), '<a class="axh-btn axh-btn--primary" href="#/shares">Shared links</a>') + '</div>'); });
		}
		if (parts[0] === 'submissions') {
			if (S.p1.subForm !== (parts[1] || '')) { S.p1.subForm = parts[1] || ''; S.p1.subPage = 1; S.p1.subQ = ''; }
			return p1SubRoute(parts[1] || '', render);
		}
		if (parts[0] === 'library') return render(viewLibrary());
		if (parts[0] === 'tasks') return refresh().then(function () { return fuLoad(false); }).then(function () { render(viewTasks()); });
		if (parts[0] === 'leads') return Promise.all([refresh(true), fuBoard()]).then(function () { render(viewLeads()); });
		return Promise.all([refresh(), fuLoad(false)]).then(function () { render(viewDash()); });
	}

	function refresh(pipeline) {
		// The archive and the per-rep filter belong to the Pipeline page only;
		// the dashboard and follow-ups always read the live pipeline.
		var q = [];
		if (pipeline && S.showArchived) q.push('archived=1');
		if (pipeline && S.repFilter) q.push('rep_id=' + encodeURIComponent(S.repFilter));
		return Promise.all([api('/leads' + (q.length ? '?' + q.join('&') : '')), api('/tasks')]).then(function (r) {
			S.leads = r[0].leads || [];
			S.boot.summary = r[0].summary || S.boot.summary;
			S.tasks = r[1].tasks || [];
		});
	}

	/* ----------------------------------------------------------- events */

	function leadForm(l) {
		l = l || {};
		return '<div class="axh-form-grid">' +
			inp('company', 'Company', l.company, true) + inp('contact_name', 'Contact name', l.contact_name) +
			inp('email', 'Email', l.email) + inp('phone', 'Phone', l.phone) +
			inp('city', 'City', l.city) + inp('state', 'State', l.state) +
			'<div class="axh-field"><label>Industry</label><select data-f="industry"><option value="">Choose…</option>' +
			S.boot.industries.map(function (i) { return '<option' + (l.industry === i ? ' selected' : '') + '>' + i + '</option>'; }).join('') +
			'</select></div>' +
			inp('est_value', 'Estimated value', l.est_value) +
			'<div class="axh-field axh-span"><label>Website</label><input type="text" data-f="website" value="' + esc(l.website || '') + '"></div>' +
			'<div class="axh-field axh-span"><label>Next action</label><input type="text" data-f="next_action" value="' + esc(l.next_action || '') + '" placeholder="Call Tuesday about the estimate"></div>' +
			'<div class="axh-field axh-span"><label>Notes</label><textarea data-f="notes">' + esc(l.notes || '') + '</textarea></div>' +
			'</div>';
	}
	function inp(k, label, v, req) {
		return '<div class="axh-field"><label>' + label + (req ? ' *' : '') + '</label><input type="text" data-f="' + k + '" value="' + esc(v || '') + '"' + (req ? ' required' : '') + '></div>';
	}
	function readForm(box) {
		var out = {};
		box.querySelectorAll('[data-f]').forEach(function (i) { out[i.getAttribute('data-f')] = i.value; });
		return out;
	}

	/* Commission preview, from the same tiers the server uses. */
	function tierFor(v) {
		var tiers = (S.earn && S.earn.tiers) || [
			{ max: 5000, rate: 10 }, { max: 8000, rate: 12 }, { max: 10000, rate: 14 }, { max: 15000, rate: 15 }, { max: 0, rate: 20 }];
		for (var i = 0; i < tiers.length; i++) { if (tiers[i].max && v <= tiers[i].max) return Number(tiers[i].rate); }
		return 20;
	}
	/* A per-rep rate set by AdaptExec replaces the schedule. */
	function repRate(repId) {
		if (!S.boot.user.manager) return Number(S.boot.rateOverride) || 0;
		var a = (S.boot.assignees || []).filter(function (x) { return String(x.id) === String(repId); })[0];
		return a ? Number(a.rate) || 0 : 0;
	}
	function commissionLine(v, rate) {
		v = Number(String(v).replace(/[^0-9.]/g, '')) || 0;
		if (!v) return 'Enter the signed contract value.';
		var r = Number(rate) || tierFor(v), c = Math.round(v * r) / 100;
		return pctTxt(r) + ' of ' + moneyC(v) + ' = <b>' + moneyC(c) + '</b> commission: ' + moneyC(Math.round(c * 50) / 100) + ' is earned when the client\u2019s deposit clears and the rest when their final payment clears.';
	}

	/* Manager: Closed Won at a value (or a correction to one already won). */
	function closeDealModal(id, value, repId) {
		var rate = repRate(repId);
		var m = modal('Mark Closed Won',
			'<div class="axh-field"><label for="axh-cv">Signed contract value ($)</label>' +
			'<input id="axh-cv" type="text" inputmode="decimal" data-cv value="' + esc(Number(value) > 0 ? value : '') + '" placeholder="12000"></div>' +
			'<p class="axh-hint" data-cv-out>' + commissionLine(value, rate) + '</p>' +
			'<p class="axh-hint">' + (rate ? 'This rep has a ' + pctTxt(rate) + ' rate set on their profile.' : 'The rate is set from the whole contract value.') + ' The rep is notified when a deal is first marked won.</p>',
			'<button class="axh-btn axh-btn--quiet" data-close>Cancel</button>' +
			'<button class="axh-btn axh-btn--primary" data-cv-save>Save as Closed Won</button>');
		var inpEl = m.querySelector('[data-cv]');
		inpEl.oninput = function () { m.querySelector('[data-cv-out]').innerHTML = commissionLine(inpEl.value, rate); };
		m.querySelector('[data-cv-save]').onclick = function () {
			var v = Number(String(inpEl.value).replace(/[^0-9.]/g, ''));
			if (!v) { toast('Enter the signed contract value.', 'bad'); inpEl.focus(); return; }
			this.disabled = true;
			api('/leads/' + id + '/close', { method: 'POST', body: { contract_value: v } })
				.then(function () { closeModal(); toast('Closed Won at ' + moneyC(v) + '.', 'ok'); route(); })
				.catch(function (e) { toast(e.message, 'bad'); route(); });
		};
	}

	/* Rep: "the client signed" — AdaptExec confirms it. */
	function winRequestModal(id, est) {
		var m = modal('Request Closed Won',
			'<p class="axh-hint" style="margin-bottom:12px">Tell AdaptExec the client signed. They confirm the contract value, and then the deal moves to Closed Won and your commission appears under Earnings.</p>' +
			'<div class="axh-field"><label for="axh-wv">Signed contract value ($)</label>' +
			'<input id="axh-wv" type="text" inputmode="decimal" data-wv value="' + esc(Number(est) > 0 ? est : '') + '"></div>' +
			'<p class="axh-hint" data-wv-out>' + commissionLine(est, repRate()) + '</p>' +
			'<div class="axh-field"><label for="axh-wn">Anything AdaptExec should know</label>' +
			'<textarea id="axh-wn" data-wn placeholder="Signed Oct 14 — deposit invoice requested"></textarea></div>',
			'<button class="axh-btn axh-btn--quiet" data-close>Cancel</button>' +
			'<button class="axh-btn axh-btn--primary" data-wv-save>Send to AdaptExec</button>');
		var inpEl = m.querySelector('[data-wv]');
		inpEl.oninput = function () { m.querySelector('[data-wv-out]').innerHTML = commissionLine(inpEl.value, repRate()); };
		m.querySelector('[data-wv-save]').onclick = function () {
			var v = Number(String(inpEl.value).replace(/[^0-9.]/g, ''));
			if (!v) { toast('Enter the signed contract value.', 'bad'); inpEl.focus(); return; }
			this.disabled = true;
			api('/leads/' + id + '/win-request', { method: 'POST', body: { contract_value: v, note: m.querySelector('[data-wn]').value } })
				.then(function () { closeModal(); toast('Sent. AdaptExec will confirm it.', 'ok'); route(); })
				.catch(function (e) { toast(e.message, 'bad'); route(); });
		};
	}

	/* Manager: who owns this lead. */
	function assignModal(id, current) {
		var opts = (S.boot.assignees || []).map(function (a) {
			return '<option value="' + a.id + '"' + (String(a.id) === String(current) ? ' selected' : '') + '>' + esc(a.name) + (a.manager ? ' (admin)' : '') + '</option>';
		}).join('');
		var m = modal('Reassign lead',
			'<div class="axh-field"><label for="axh-as">Owner</label><select id="axh-as" data-as>' + opts + '</select></div>' +
			'<p class="axh-hint">The new owner gets the lead, its questionnaire and its open follow-ups, and is emailed the details. The change is recorded on the timeline.' +
			(S.lead && S.lead.lead && S.lead.lead.stage === 'won' ? ' <b>This deal is Closed Won — its commission moves to the new owner\u2019s Earnings.</b>' : '') + '</p>',
			'<button class="axh-btn axh-btn--quiet" data-close>Cancel</button>' +
			'<button class="axh-btn axh-btn--primary" data-as-save>Reassign</button>');
		m.querySelector('[data-as-save]').onclick = function () {
			var who = m.querySelector('[data-as]').value;
			this.disabled = true;
			api('/leads/' + id + '/assign', { method: 'POST', body: { rep_id: who } })
				.then(function () { closeModal(); toast('Reassigned.', 'ok'); route(); })
				.catch(function (e) { toast(e.message, 'bad'); route(); });
		};
	}

	function openLeadModal(l) {
		var m = modal(l ? 'Edit lead' : 'New lead', leadForm(l),
			'<button class="axh-btn axh-btn--ghost" data-close>Cancel</button>' +
			'<button class="axh-btn axh-btn--primary" data-save>' + (l ? 'Save' : 'Add lead') + '</button>');
		m.querySelector('[data-save]').onclick = function () {
			var data = readForm(m);
			if (!data.company) { toast('A company name is required.', 'bad'); return; }
			var p = l ? api('/leads/' + l.id, { method: 'POST', body: data }) : api('/leads', { method: 'POST', body: data });
			p.then(function (r) {
				closeModal();
				toast(l ? 'Saved.' : 'Lead added.', 'ok');
				if (l) route(); else fpAfterCreate(r.lead.id).then(function () { location.hash = '#/lead/' + r.lead.id; });
			}).catch(function (e) { toast(e.message, 'bad'); });
		};
	}

	document.addEventListener('click', function (e) {
		var t = e.target;
		var hit = function (sel) { return t.closest(sel); };

		if (hit('[data-new]')) { openLeadModal(null); return; }
		if (hit('#axh-new-lead')) { openLeadModal(null); return; }

		var edit = hit('[data-edit]');
		if (edit) {
			var eid = String(edit.getAttribute('data-edit'));
			var cur = S.lead && S.lead.lead && String(S.lead.lead.id) === eid ? S.lead.lead : null;
			openLeadModal(cur || (S.leads || []).filter(function (x) { return String(x.id) === eid; })[0] || (S.lead && S.lead.lead));
			return;
		}

		// Submissions list (Hub admins): pick, delete one, delete selected. Handled
		// before [data-open] so ticking a row does not open its lead.
		if (hit('[data-sub-pick]') || hit('[data-sub-all]') || hit('.axh-subpick')) {
			var sall = hit('[data-sub-all]');
			if (sall) document.querySelectorAll('[data-sub-pick]').forEach(function (c) { c.checked = sall.checked; });
			subPicked();
			return;
		}
		var sdel = hit('[data-sub-del]');
		if (sdel) { delSubs([sdel.getAttribute('data-sub-del')]); return; }
		if (hit('[data-sub-bulk]')) {
			var sids = [];
			document.querySelectorAll('[data-sub-pick]:checked').forEach(function (c) { sids.push(c.getAttribute('data-sub-pick')); });
			if (sids.length) delSubs(sids);
			return;
		}

		var open = hit('[data-open]');
		if (open && hit('[data-register], [data-reg], a[href]')) open = null;
		if (open) { location.hash = '#/lead/' + open.getAttribute('data-open'); return; }

		var cd = hit('[data-close-deal]');
		if (cd) { closeDealModal(cd.getAttribute('data-close-deal'), cd.getAttribute('data-value'), cd.getAttribute('data-rep') || (S.lead && S.lead.lead ? S.lead.lead.rep_id : '')); return; }

		var wr = hit('[data-win-req]');
		if (wr) { winRequestModal(wr.getAttribute('data-win-req'), S.lead && S.lead.lead ? S.lead.lead.est_value : ''); return; }

		var wd = hit('[data-win-decline]');
		if (wd) {
			var wdid = wd.getAttribute('data-win-decline');
			ask({ title: 'Send it back to the rep?', ok: 'Send back', input: { label: 'Why not yet? The rep sees this note (optional).', multiline: true } }).then(function (why) {
				api('/leads/' + wdid + '/close', { method: 'POST', body: { decline: true, note: why } })
					.then(function () { toast('Request sent back to the rep.', 'ok'); route(); })
					.catch(function (er) { toast(er.message, 'bad'); });
			});
			return;
		}

		var as = hit('[data-assign]');
		if (as) { assignModal(as.getAttribute('data-assign'), S.lead && S.lead.lead ? S.lead.lead.rep_id : ''); return; }

		var ar = hit('[data-archive]');
		if (ar) {
			var on = ar.getAttribute('data-on') === '1';
			api('/leads/' + ar.getAttribute('data-archive') + '/archive', { method: 'POST', body: { archived: on } })
				.then(function () { toast(on ? 'Archived. Find it under Pipeline → Archived.' : 'Restored to your pipeline.', 'ok'); route(); })
				.catch(function (er) { toast(er.message, 'bad'); });
			return;
		}

		var dl = hit('[data-del-lead]');
		if (dl) {
			var mdl = modal('Delete this lead?',
				'<p style="font-size:13.5px">This permanently removes the lead, its timeline, follow-ups, questionnaire, the form submission that opened it, deck links made for it and everyone\'s notifications about it. A billing customer made from it is kept. It cannot be undone.</p>',
				'<button class="axh-btn axh-btn--ghost" data-close>Keep it</button><button class="axh-btn axh-btn--danger" data-yes>Delete</button>');
			mdl.querySelector('[data-yes]').onclick = function () {
				api('/leads/' + dl.getAttribute('data-del-lead'), { method: 'DELETE' })
					.then(function () { closeModal(); toast('Lead deleted.', 'ok'); location.hash = '#/leads'; })
					.catch(function (er) { closeModal(); toast(er.message, 'bad'); });
			};
			return;
		}

		if (hit('[data-checklist-hide]')) {
			try { localStorage.setItem('axh_checklist_done', '1'); } catch (er) {}
			var card = t.closest('.axh-firstweek'); if (card) card.remove();
			return;
		}

		if (hit('[data-toggle-archived]')) {
			S.showArchived = !S.showArchived;
			refresh(true).then(function () { render(viewLeads()); });
			return;
		}

		var reg = hit('[data-register], [data-reg]');
		if (reg) {
			var rid = reg.getAttribute('data-register') || reg.getAttribute('data-reg');
			api('/leads/' + rid + '/register', { method: 'POST' })
				.then(function (r) {
					if (r.conflicts && r.conflicts.length) {
						toast('Submitted, but it clashes with ' + r.conflicts[0].company +
							' \u2014 already registered by ' + r.conflicts[0].rep + '.', 'bad');
					} else {
						toast('Submitted. You will hear back before you contact them.', 'ok');
					}
					route();
				})
				.catch(function (er) { toast(er.message, 'bad'); });
			return;
		}

		var decide = hit('[data-decide]');
		if (decide) {
			var dec = decide.getAttribute('data-decide');
			var dlead = decide.getAttribute('data-lead');
			var sendDecision = function (dnote) {
				decide.disabled = true;
				api('/leads/' + dlead, {
					method: 'POST',
					body: { reg_status: dec, decision_note: dnote || '' }
				}).then(function () { toast('Decision recorded. The rep has been emailed.', 'ok'); route(); })
					.catch(function (er) { decide.disabled = false; toast(er.message, 'bad'); });
			};
			if (dec === 'registered') sendDecision('');
			else ask({ title: dec === 'house' ? 'Make it a house account?' : 'Decline this registration?', ok: dec === 'house' ? 'House account' : 'Decline', danger: true,
				input: { label: dec === 'house' ? 'Note for the rep (optional)' : 'Why is it declined? The rep sees this note.', placeholder: dec === 'house' ? 'e.g. Existing client since 2024' : '', multiline: true } }).then(sendDecision);
			return;
		}

		var st = hit('[data-stage][data-lead]');
		if (st) {
			api('/leads/' + st.getAttribute('data-lead'), { method: 'POST', body: { stage: st.getAttribute('data-stage') } })
				.then(function () { toast('Stage updated.', 'ok'); route(); })
				.catch(function (er) { toast(er.message, 'bad'); });
			return;
		}

		var task = hit('[data-task]');
		if (task) {
			var done = !task.classList.contains('is-on');
			api('/tasks/' + task.getAttribute('data-task'), { method: 'POST', body: { done: done } })
				.then(route).catch(function (er) { toast(er.message, 'bad'); });
			return;
		}

		var addTask = hit('[data-add-task]');
		if (addTask) {
			var lid = addTask.getAttribute('data-add-task');
			var m = modal('Add a follow-up',
				'<div class="axh-field"><label>What needs doing?</label><input type="text" data-f="title" placeholder="Call about the proposal"></div>' +
				'<div class="axh-field"><label>When</label><input type="date" data-f="due_at"></div>',
				'<button class="axh-btn axh-btn--ghost" data-close>Cancel</button><button class="axh-btn axh-btn--primary" data-save>Add</button>');
			m.querySelector('[data-save]').onclick = function () {
				var d = readForm(m);
				if (!d.title) { toast('Give it a name.', 'bad'); return; }
				d.lead_id = lid !== '0' ? lid : null;
				api('/tasks', { method: 'POST', body: d }).then(function () { closeModal(); toast('Added.', 'ok'); route(); })
					.catch(function (er) { toast(er.message, 'bad'); });
			};
			return;
		}

		var log = hit('[data-log]');
		if (log) {
			var leadId = log.getAttribute('data-log');
			var m2 = modal('Log activity',
				'<div class="axh-field"><label>Type</label><select data-f="type">' +
				['call', 'email', 'meeting', 'note'].map(function (o) { return '<option value="' + o + '">' + o.charAt(0).toUpperCase() + o.slice(1) + '</option>'; }).join('') +
				'</select></div>' +
				'<div class="axh-field"><label>Summary</label><input type="text" data-f="subject" placeholder="Discovery meeting with the owner"></div>' +
				'<div class="axh-field"><label>Notes</label><textarea data-f="body"></textarea></div>',
				'<button class="axh-btn axh-btn--ghost" data-close>Cancel</button><button class="axh-btn axh-btn--primary" data-save>Log it</button>');
			m2.querySelector('[data-save]').onclick = function () {
				var act = readForm(m2);
				if (!act.subject && !act.body) { toast('Add a summary or a note.', 'bad'); return; }
				api('/leads/' + leadId + '/activity', { method: 'POST', body: act })
					.then(function () { closeModal(); toast('Logged.', 'ok'); route(); })
					.catch(function (er) { toast(er.message, 'bad'); });
			};
			return;
		}

		var brief = hit('[data-brief]');
		if (brief) { location.hash = '#/brief/' + brief.getAttribute('data-brief'); return; }

		var pay = hit('[data-pay]');
		if (pay) {
			var pid = pay.getAttribute('data-pay');
			var deal = ((S.earn && S.earn.deals) || []).filter(function (x) {
				return String(x.lead_id) === String(pid);
			})[0];
			paymentModal(pid, deal);
			return;
		}

		if (hit('[data-print-brief]')) { window.print(); return; }

        var rev = hit('[data-review]');
		if (rev) {
			var rvid = rev.getAttribute('data-review');
			ask({ title: 'Mark this brief reviewed?', ok: 'Mark reviewed', input: { label: 'Scoping note (optional)', multiline: true } }).then(function (note) {
				api('/manage/discovery/' + rvid, {
					method: 'POST', body: { note: note || '', status: 'reviewed' }
				}).then(function () { toast('Marked reviewed.', 'ok'); route(); })
					.catch(function (e) { toast(e.message, 'bad'); });
			});
			return;
		}

		var slides = hit('[data-slides]');
		if (slides) { openSlides(slides.getAttribute('data-slides')); return; }

		var sqr = hit('[data-share-qr]');
		if (sqr) {
			var u = sqr.getAttribute('data-share-qr');
			var mm = modal('QR code', '<div class="axh-qrbox" id="axh-share-qr"></div>' +
				'<p class="axh-hint">' + esc(u) + '</p>',
				'<button class="axh-btn axh-btn--primary" data-close>Done</button>');
			try {
				mm.querySelector('#axh-share-qr').innerHTML = AXHQR.svg(u, { quiet: 3 });
			} catch (er) {
				mm.querySelector('#axh-share-qr').innerHTML = '<p class="axh-hint">Link is too long for a QR code.</p>';
			}
			return;
		}

		var deck = hit('[data-deck]');
		if (deck) {
			var dslug = deck.getAttribute('data-deck');
			if ((S.boot.slideDecks || []).some(function (x) { return x.slug === dslug; })) openSlides(dslug, null);
			else openPresent(dslug, null);
			return;
		}

		if (hit('[data-p-close]')) { closePresent(); return; }
		if (hit('[data-p-prev]')) { showPage(P.page - 1); return; }
		if (hit('[data-p-next]')) { showPage(P.page + 1); return; }
		if (hit('[data-p-full]')) { toggleFull(); return; }
		if (hit('[data-p-clean]')) { el('axh-present').classList.toggle('is-clean'); return; }


		if (hit('[data-new-form]')) {
			var m4 = modal('New lead form',
				'<div class="axh-field"><label>What is this form for? *</label><input type="text" data-f="name" placeholder="Spring HVAC campaign"></div>' +
				'<div class="axh-field"><label>Where will you share it?</label><input type="text" data-f="source_tag" placeholder="youtube-video, qr-flyer, text-message"></div>' +
				'<div class="axh-field"><label>Headline people will see</label><input type="text" data-f="headline" placeholder="Stop paying for six apps that don\'t talk."></div>',
				'<button class="axh-btn axh-btn--ghost" data-close>Cancel</button><button class="axh-btn axh-btn--primary" data-save>Create form</button>');
			m4.querySelector('[data-save]').onclick = function () {
				var d2 = readForm(m4);
				if (!d2.name) { toast('Give the form a name.', 'bad'); return; }
				d2.fields = S.boot.formDefaults || ['name', 'email', 'phone', 'company'];
				d2.active = true;
				api('/forms', { method: 'POST', body: d2 }).then(function (r) {
					closeModal();
					toast('Form created.', 'ok');
					location.hash = '#/form/' + r.form.id;
				}).catch(function (er) { toast(er.message, 'bad'); });
			};
			return;
		}

		var openForm = hit('[data-form]');
		if (openForm) { location.hash = '#/form/' + openForm.getAttribute('data-form'); return; }

		var fld = hit('[data-fld]');
		if (fld && S.formFields) {
			var k2 = fld.getAttribute('data-fld');
			var ix2 = S.formFields.indexOf(k2);
			if (ix2 > -1) S.formFields.splice(ix2, 1); else S.formFields.push(k2);
			fld.classList.toggle('is-must');
			return;
		}

		var acc = hit('[data-accent]');
		if (acc) {
			S.formAccent = acc.getAttribute('data-accent');
			document.querySelectorAll('[data-accent]').forEach(function (b) { b.classList.remove('is-on'); });
            acc.classList.add('is-on');
			return;
		}

		var saveForm = hit('[data-save-form]');
		if (saveForm) {
			api('/forms/' + saveForm.getAttribute('data-save-form'), { method: 'POST', body: readForm2() })
				.then(function () { toast('Saved.', 'ok'); route(); })
				.catch(function (er) { toast(er.message, 'bad'); });
			return;
		}

		var delForm = hit('[data-del-form]');
		if (delForm) {
			var mm = modal('Delete this form?',
				'<p style="font-size:13.5px">The link will stop working immediately. Leads already collected stay in your pipeline.</p>',
				'<button class="axh-btn axh-btn--ghost" data-close>Keep it</button><button class="axh-btn axh-btn--danger" data-yes>Delete</button>');
			mm.querySelector('[data-yes]').onclick = function () {
				api('/forms/' + delForm.getAttribute('data-del-form'), { method: 'DELETE' })
					.then(function () { closeModal(); toast('Form deleted.', 'ok'); location.hash = '#/forms'; })
					.catch(function (er) { closeModal(); toast(er.message, 'bad'); });
			};
			return;
		}

		var cp = hit('[data-copy]');
		if (cp) {
			var url = cp.getAttribute('data-copy');
			if (navigator.clipboard) navigator.clipboard.writeText(url).then(function () { toast('Link copied.', 'ok'); });
			else {
				var tmp = document.createElement('input');
				tmp.value = url; document.body.appendChild(tmp); tmp.select();
				document.execCommand('copy'); tmp.remove(); toast('Link copied.', 'ok');
			}
			return;
		}

		var qd = hit('[data-qr-download]');
		if (qd) {
			var box = el('axh-qr');
			if (!box || !box.innerHTML) return;
			var blob = new Blob([box.innerHTML], { type: 'image/svg+xml' });
			var a = document.createElement('a');
			a.href = URL.createObjectURL(blob);
            a.download = 'adaptexec-' + qd.getAttribute('data-qr-download') + '-qr.svg';
			a.click();
			setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
			return;
		}

		if (hit('[data-qr-print]')) {
			var qbox = el('axh-qr');
			var link = document.getElementById('axh-form-url');
			var w = window.open('', '_blank');
			if (!w) { toast('Allow pop-ups to print.', 'bad'); return; }
			w.document.write('<!DOCTYPE html><title>QR sheet</title><style>body{font-family:system-ui;text-align:center;padding:60px}' +
				'svg{width:340px;height:340px}h1{font-size:26px;margin:24px 0 6px}p{color:#666;font-size:15px}</style>' +
				qbox.innerHTML + '<h1>Scan to get started</h1><p>' + (link ? link.value : '') + '</p>');
			w.document.close();
			w.focus();
			setTimeout(function () { w.print(); }, 400);
			return;
		}

		if (hit('[data-toggle-view]')) { S.listMode = !S.listMode; render(viewLeads()); return; }

		// ---- discovery interactions
		var sec = hit('[data-sec]:not([data-key])');
		if (sec && S.disc) {
			collectInputs();
			S.disc.section = parseInt(sec.getAttribute('data-sec'), 10);
			render(renderDiscovery());
			return;
		}
		var mod = hit('[data-mod]');
		if (mod && S.disc) {
			var name = mod.getAttribute('data-mod');
			var cur = S.disc.modules[name];
			if (!cur) S.disc.modules[name] = 'must';
			else if (cur === 'must') S.disc.modules[name] = 'nice';
			else delete S.disc.modules[name];
			collectInputs();
			render(renderDiscovery());
			return;
		}
		var chk = hit('[data-check]');
		if (chk && S.disc) {
			var s2 = chk.getAttribute('data-sec'), k2 = chk.getAttribute('data-key'), o = chk.getAttribute('data-check');
			var arr = val(s2, k2);
			arr = Array.isArray(arr) ? arr.slice() : [];
			var ix = arr.indexOf(o);
			if (ix > -1) arr.splice(ix, 1); else arr.push(o);
			setVal(s2, k2, arr);
			chk.classList.toggle('is-on');
			return;
		}
		var scale = hit('[data-scale]');
		if (scale && S.disc) {
			var s3 = scale.getAttribute('data-sec'), k3 = scale.getAttribute('data-key');
			var pick = scale.getAttribute('data-scale');
			// Tapping the chosen answer again clears it — nothing is required.
			var next = val(s3, k3) === pick ? '' : pick;
			setVal(s3, k3, next);
			[].forEach.call(scale.parentNode.children, function (b) {
				var on = next && b === scale;
				b.classList.toggle('is-on', !!on);
				b.setAttribute('aria-checked', String(!!on));
			});
			return;
		}
		var trimOpen = hit('[data-trim-open]');
		if (trimOpen) {
			var body = document.querySelector('[data-trim-body="' + trimOpen.getAttribute('data-trim-open') + '"]');
			if (body) body.hidden = !body.hidden;
			return;
		}
		if (hit('[data-disc-trim]') && S.disc) { S.disc.trim = true; render(renderDiscovery()); return; }
		if (hit('[data-disc-trim-done]') && S.disc) {
			S.disc.trim = false;
			saveDiscoveryConfig().then(function () { render(renderDiscovery()); });
			return;
		}
		var preset = hit('[data-disc-preset]');
		if (preset && S.disc) {
			var kind = preset.getAttribute('data-disc-preset');
			var sections = {};
			(S.disc.full || []).forEach(function (s) {
				if (kind === 'essential' && s.tier && s.tier !== 'essential') sections[s.key] = false;
			});
			S.disc.config = { sections: sections, questions: {} };
			saveDiscoveryConfig().then(function () { render(renderDiscovery()); });
			return;
		}
		if (hit('[data-disc-share]') && S.disc) { shareDiscovery(); return; }
		if (hit('[data-rep-new]')) { repNewModal(); return; }
		var rdo = hit('[data-rep-do]');
		if (rdo) { repDo(rdo); return; }
		var red = hit('[data-rep-edit]');
		if (red && S.repView) { repEditModal(S.repView.rep); return; }
		var rmo = hit('[data-rep-more]');
		if (rmo && S.repView) { repMoreModal(rmo.getAttribute('data-rep-more'), rmo.getAttribute('data-rep-name'), S.repView.rep.status); return; }
		var cpl = hit('[data-copy-link]');
		if (cpl) {
			var lv = cpl.parentNode.querySelector('[data-linkval]');
			lv.select();
			(navigator.clipboard ? navigator.clipboard.writeText(lv.value) : Promise.reject()).then(function () { toast('Link copied.', 'ok'); })
				.catch(function () { try { document.execCommand('copy'); toast('Link copied.', 'ok'); } catch (er) { toast('Select the link and copy it.', 'bad'); } });
			return;
		}
		var csvB = hit('[data-csv]');
		if (csvB) { if (csvB.getAttribute('data-csv') === 'deals') dealsCsv(); else payoutsCsv(); return; }
		var mp = hit('[data-mark-paid]');
		if (mp) { markPaidModal(mp.getAttribute('data-mark-paid'), mp.getAttribute('data-leg')); return; }
		var cpd = hit('[data-client-paid]');
		if (cpd) { clientPaidModal(cpd.getAttribute('data-client-paid'), cpd.getAttribute('data-leg')); return; }
		var vp = hit('[data-void-pay]');
		if (vp) {
			var mv = modal('Void this payment?', '<p style="font-size:13.5px">Use this when a payment was recorded by mistake. It stays in the history marked void, and that half shows as unpaid again.</p>' +
				'<div class="axh-field"><label for="axh-vr">Reason</label><input id="axh-vr" type="text" placeholder="Entered twice"></div>',
				'<button class="axh-btn axh-btn--quiet" data-close>Keep it</button><button class="axh-btn axh-btn--danger" data-yes>Void payment</button>');
			mv.querySelector('[data-yes]').onclick = function () {
				api('/manage/payouts/' + vp.getAttribute('data-void-pay') + '/void', { method: 'POST', body: { reason: mv.querySelector('#axh-vr').value } })
					.then(function () { closeModal(); toast('Payment voided.', 'ok'); route(); })
					.catch(function (er) { toast(er.message, 'bad'); });
			};
			return;
		}
		var so = hit('[data-style-open]');
		if (so) {
			var sid = so.getAttribute('data-style-open');
			if (sid === 'sel') { var sel = document.querySelector('[data-sf-lead]'); sid = sel ? sel.value : ''; }
			openStyleFinder(sid && sid !== '0' ? sid : null);
			return;
		}
		var slk = hit('[data-style-link]');
		if (slk) {
			var lid = slk.getAttribute('data-style-link');
			if (lid === 'sel') { var s2 = document.querySelector('[data-sf-linklead]'); lid = s2 ? s2.value : ''; }
			styleLink(lid, slk.closest('.axh-card').querySelector('[data-style-linkbox]'));
			return;
		}
		var unc = hit('[data-unclamp]');
		if (unc) {
			var box = unc.previousElementSibling;
			if (box) box.classList.toggle('is-clamped');
			unc.textContent = box && box.classList.contains('is-clamped') ? 'Show all' : 'Show less';
			return;
		}
		var scl = hit('[data-style-clear]');
		if (scl) {
			var sclid = scl.getAttribute('data-style-clear');
			ask({ title: 'Clear the saved style picks?', body: 'The picks saved on this lead are removed. The client can pick again from their link.', ok: 'Clear picks', danger: true }).then(function () {
				api('/leads/' + sclid + '/styles', { method: 'POST', body: { picks: {} } })
					.then(function () { toast('Style picks cleared.', 'ok'); route(); }).catch(function (er) { toast(er.message, 'bad'); });
			});
			return;
		}
		if (hit('#axh-bell')) { toggleNotes(); return; }
		var ndel = hit('[data-note-del]');
		if (ndel) {
			var dnid = ndel.getAttribute('data-note-del');
			api('/notifications/' + dnid, { method: 'DELETE' }).then(function (r) {
				S.notes = (S.notes || []).filter(function (n) { return String(n.id) !== dnid; });
				setUnread(r.unread || 0); paintNotes();
			}).catch(function (er) { toast(er.message, 'bad'); });
			return;
		}
		if (hit('[data-notes-clear]')) {
			api('/notifications/clear', { method: 'POST', body: {} }).then(function (r) {
				S.notes = (S.notes || []).filter(function (n) { return !n.read_at; });
				setUnread(r.unread || 0); paintNotes();
				toast(r.cleared ? 'Cleared ' + r.cleared + ' read notification' + (r.cleared === 1 ? '' : 's') + '.' : 'Nothing to clear.', 'ok');
			}).catch(function (er) { toast(er.message, 'bad'); });
			return;
		}
		if (hit('[data-notes-all]')) {
			api('/notifications/read', { method: 'POST', body: { all: true } }).then(function (r) { setUnread(r.unread); (S.notes || []).forEach(function (n) { n.read_at = n.read_at || 'now'; }); paintNotes(); });
			return;
		}
		var nt = hit('[data-note]');
		if (nt) {
			var nid = nt.getAttribute('data-note');
			(S.notes || []).forEach(function (n) { if (String(n.id) === nid && !n.read_at) { n.read_at = 'now'; setUnread(Math.max(0, (S.unread || 1) - 1)); } });
			api('/notifications/read', { method: 'POST', body: { ids: [nid] } }).catch(function () {});
			toggleNotes(false);
			return;
		}
		if (el('axh-notes') && !el('axh-notes').hidden && !hit('#axh-notes')) toggleNotes(false);
		if (hit('[data-me-save]')) {
			var mb = { prefs: {} };
			document.querySelectorAll('[data-me]').forEach(function (i) { mb[i.getAttribute('data-me')] = i.value; });
			document.querySelectorAll('[data-pref]').forEach(function (i) { mb.prefs[i.getAttribute('data-pref')] = i.checked; });
			api('/me', { method: 'POST', body: mb }).then(function (r) { if (S.boot.p1) { S.boot.p1.bookingUrl = r.booking || ''; S.boot.p1.phone = r.phone || ''; } toast('Saved.', 'ok'); }).catch(function (er) { toast(er.message, 'bad'); });
			return;
		}
		var rate = hit('[data-rate]');
		if (rate && S.disc) {
			setVal(rate.getAttribute('data-sec'), rate.getAttribute('data-key'), rate.getAttribute('data-rate'));
			collectInputs();
			render(renderDiscovery());
			return;
		}
		var sadd = hit('[data-stack-add]');
		if (sadd && S.disc) {
			var parts2 = sadd.getAttribute('data-stack-add').split('|');
			var rows = val(parts2[0], parts2[1]);
			rows = Array.isArray(rows) ? rows.slice() : [];
			collectInputs();
			rows = val(parts2[0], parts2[1]);
			rows = Array.isArray(rows) ? rows.slice() : [];
			rows.push({ name: '', use: '', cost: '' });
			setVal(parts2[0], parts2[1], rows);
			render(renderDiscovery());
			return;
		}
		var sdel = hit('[data-row-del]');
		if (sdel && S.disc) {
			collectInputs();
			var box = sdel.closest('[data-stack]');
			var p3 = box.getAttribute('data-stack').split('|');
			var rr = val(p3[0], p3[1]);
			rr = Array.isArray(rr) ? rr.slice() : [];
			rr.splice(parseInt(sdel.getAttribute('data-row-del'), 10), 1);
			setVal(p3[0], p3[1], rr);
			render(renderDiscovery());
			return;
		}
		if (hit('[data-disc-save]')) { collectInputs(); saveDiscovery(false); return; }
		if (hit('[data-disc-submit]')) {
			collectInputs();
			quoteModal();
			return;
		}
		var qgo = hit('[data-quote-go]');
		if (qgo && S.disc) {
			var qn = document.querySelector('[data-quote-note]'), qtxt = qn ? qn.value.trim() : '';
			if (qgo.hasAttribute('data-override') && qtxt.length < 10) { toast('Add a short note for Adam (what is missing and why) to request it now.', 'bad'); if (qn) qn.focus(); return; }
			qgo.disabled = true; closeModal();
			saveDiscovery(true, qtxt);
			return;
		}

		if (hit('#axh-burger')) { el('axh-app').classList.add('is-open'); el('axh-scrim').hidden = false; return; }
		if (hit('#axh-scrim')) { el('axh-app').classList.remove('is-open'); el('axh-scrim').hidden = true; return; }
		if (t.closest('.axh-nav a')) { el('axh-app').classList.remove('is-open'); el('axh-scrim').hidden = true; }
	});

	/** Pull every visible questionnaire input into state before re-rendering. */
	function collectInputs() {
		if (!S.disc) return;
		document.querySelectorAll('[data-in]').forEach(function (i) {
			setVal(i.getAttribute('data-sec'), i.getAttribute('data-key'), i.value);
		});
		document.querySelectorAll('[data-stack]').forEach(function (box) {
			var p = box.getAttribute('data-stack').split('|');
			var rows = [];
			box.querySelectorAll('[data-row]').forEach(function (r) {
				var o = {};
				r.querySelectorAll('[data-field]').forEach(function (f) { o[f.getAttribute('data-field')] = f.value; });
				if (o.name || o.use || o.cost) rows.push(o);
			});
			setVal(p[0], p[1], rows);
		});
	}

	/* ------------------------------------------------------ drag & drop */

	document.addEventListener('dragstart', function (e) {
		var c = e.target.closest ? e.target.closest('.axh-kc') : null;
		if (!c) return;
		c.classList.add('is-drag');
		e.dataTransfer.setData('text/plain', c.getAttribute('data-id'));
		e.dataTransfer.effectAllowed = 'move';
	});
	document.addEventListener('dragend', function (e) {
		var c = e.target.closest ? e.target.closest('.axh-kc') : null;
		if (c) c.classList.remove('is-drag');
		document.querySelectorAll('.axh-col').forEach(function (col) { col.classList.remove('is-over'); });
	});
	document.addEventListener('dragover', function (e) {
		var col = e.target.closest ? e.target.closest('.axh-col') : null;
		if (!col) return;
		e.preventDefault();
		col.classList.add('is-over');
	});
	document.addEventListener('dragleave', function (e) {
		var col = e.target.closest ? e.target.closest('.axh-col') : null;
		if (col) col.classList.remove('is-over');
	});
	document.addEventListener('drop', function (e) {
		var col = e.target.closest ? e.target.closest('.axh-col') : null;
		if (!col) return;
		e.preventDefault();
		col.classList.remove('is-over');
		var id = e.dataTransfer.getData('text/plain');
		var stage = col.getAttribute('data-stage');
		if (!id) return;
		if (stage === 'won') {
			// Closed Won carries a contract value: ask for it rather than
			// dropping a deal into Earnings at $0.
			var ld = S.leads.filter(function (x) { return String(x.id) === String(id); })[0] || {};
			if (ld.stage === 'won') return;
			if (S.boot.user.manager) closeDealModal(id, Number(ld.win_request_value) > 0 ? ld.win_request_value : ld.est_value);
			else if (ld.win_requested_at) toast('Already sent to AdaptExec — waiting on their confirmation.', 'ok');
			else { S.lead = { lead: ld }; winRequestModal(id, ld.est_value); }
			return;
		}
		var cur = S.leads.filter(function (x) { return String(x.id) === String(id); })[0];
		if (cur && cur.stage === stage) return;
		fpMove(id, stage);
	});

	/* --------------------------------------------------------- search */

	var searchTimer;
	/* Trimming the questionnaire. Handled on change, not click, so the box has
	   already toggled by the time its state is read. */
	document.addEventListener('change', function (e) {
		var t = e.target;
		if (!S.disc || !t || t.type !== 'checkbox') return;

		var secKey = t.getAttribute('data-trim-sec');
		if (secKey) {
			S.disc.config = S.disc.config || {};
			S.disc.config.sections = S.disc.config.sections || {};
			if (t.checked) delete S.disc.config.sections[secKey];
			else S.disc.config.sections[secKey] = false;
			saveDiscoveryConfig().then(function () { render(renderDiscovery()); });
			return;
		}

		var qKey = t.getAttribute('data-trim-q');
		if (qKey) {
			S.disc.config = S.disc.config || {};
			S.disc.config.questions = S.disc.config.questions || {};
			if (t.checked) delete S.disc.config.questions[qKey];
			else S.disc.config.questions[qKey] = true;
			saveDiscoveryConfig();
		}
	});

	/* Deals closed filters: any change reloads the list. */
	var dealTimer;
	function dealFilterChanged(t) {
		S.dealFilter = S.dealFilter || {};
		S.dealFilter[t.getAttribute('data-df')] = t.value;
		clearTimeout(dealTimer);
		dealTimer = setTimeout(function () {
			var f = S.dealFilter, q = [];
			Object.keys(f).forEach(function (k) { if (f[k]) q.push(k + '=' + encodeURIComponent(f[k])); });
			api('/manage/deals?' + q.join('&')).then(function (d) {
				var focus = document.activeElement && document.activeElement.getAttribute('data-df');
				render(viewDeals(d));
				if (focus) { var x = document.querySelector('[data-df="' + focus + '"]'); if (x) { x.focus(); if (x.type === 'search') x.setSelectionRange(x.value.length, x.value.length); } }
			}).catch(function (er) { toast(er.message, 'bad'); });
		}, t.type === 'search' ? 300 : 0);
	}
	document.addEventListener('change', function (e) {
		if (e.target && e.target.hasAttribute && e.target.hasAttribute('data-df') && e.target.type !== 'search') dealFilterChanged(e.target);
	});
	document.addEventListener('input', function (e) {
		if (e.target && e.target.hasAttribute && e.target.hasAttribute('data-df') && e.target.type === 'search') dealFilterChanged(e.target);
	});

	document.addEventListener('change', function (e) {
		if (e.target && e.target.hasAttribute && e.target.hasAttribute('data-src-filter')) {
			S.srcFilter = e.target.value;
			render(viewLeads());
			return;
		}
		if (e.target && e.target.hasAttribute && e.target.hasAttribute('data-fp-sort')) { S.pl.sort = e.target.value; render(viewLeads()); return; }
		if (e.target && e.target.hasAttribute && e.target.hasAttribute('data-rep-filter')) {
			S.repFilter = e.target.value;
			refresh(true).then(function () { render(viewLeads()); });
		}
	});

	document.addEventListener('input', function (e) {
		if (e.target.id === 'axh-search') {
			clearTimeout(searchTimer);
			searchTimer = setTimeout(function () {
				S.q = e.target.value;
				if (location.hash.indexOf('#/leads') !== 0) location.hash = '#/leads';
				else render(viewLeads());
			}, 220);
		}
	});

	/* ======================================================= 1.9.0 (p1) */
	/* Honest link analytics with alerts, a link page with time per slide,
	   presenting that logs to the deal, a full lead-form editor (own
	   questions, instant reply, booking button, consent), every submission
	   with CSV export, and dialogs in place of the browser's prompt boxes. */

	S.p1 = S.p1 || {};

	/* ---------- dialogs */
	function ask(o) {
		return new Promise(function (resolve) {
			var inp = o.input
				? '<div class="axh-field" style="margin:12px 0 0"><label for="p1-ask">' + esc(o.input.label || '') + '</label>' +
					(o.input.multiline
						? '<textarea id="p1-ask" data-ask-in rows="3" placeholder="' + esc(o.input.placeholder || '') + '">' + esc(o.input.value || '') + '</textarea>'
						: '<input id="p1-ask" type="text" data-ask-in value="' + esc(o.input.value || '') + '" placeholder="' + esc(o.input.placeholder || '') + '">') +
					(o.input.help ? '<div class="axh-field__help">' + esc(o.input.help) + '</div>' : '') + '</div>'
				: '';
			var m = modal(esc(o.title || 'Are you sure?'),
				(o.body ? '<p style="font-size:13.5px;line-height:1.55;margin:0">' + o.body + '</p>' : '') + inp,
				'<button class="axh-btn axh-btn--ghost" data-close>' + esc(o.cancel || 'Cancel') + '</button>' +
				'<button class="axh-btn ' + (o.danger ? 'axh-btn--danger' : 'axh-btn--primary') + '" data-ask-ok>' + esc(o.ok || 'OK') + '</button>');
			var go = function () {
				var i = m.querySelector('[data-ask-in]');
				var v = i ? i.value.trim() : true;
				if (o.input && o.input.required && !v) { toast(o.input.requiredMsg || 'Please fill this in.', 'bad'); i.focus(); return; }
				closeModal();
				resolve(o.input ? v : true);
			};
			m.querySelector('[data-ask-ok]').onclick = go;
			var i2 = m.querySelector('input[data-ask-in]');
			if (i2) i2.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); go(); } });
		});
	}

	/* ---------- small helpers */
	function p1Dur(sec) {
		sec = Math.max(0, Math.round(Number(sec) || 0));
		if (sec < 60) return sec + 's';
		var m = Math.floor(sec / 60), s = sec % 60;
		if (m < 60) return m + 'm' + (s ? ' ' + pad2(s) + 's' : '');
		return Math.floor(m / 60) + 'h ' + pad2(m % 60) + 'm';
	}
	function p1Clock(d) { var x = srvDate(d); return isNaN(x) ? '' : x.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }); }
	function p1Ago(d) {
		var x = srvDate(d);
		if (!d || isNaN(x)) return '';
		var mins = Math.round((Date.now() - x.getTime()) / 60000);
		if (mins < 1) return 'just now';
		if (mins < 60) return mins + ' min ago';
		var h = Math.round(mins / 60);
		if (h < 24) return h + (h === 1 ? ' hour ago' : ' hours ago');
		return whenLc(d);
	}
	function p1DeckTitle(slug) {
		var d = (S.boot.slideDecks || []).filter(function (x) { return x.slug === slug; })[0];
		return d ? d.title : slug;
	}
	function p1Who(x) { return x.lead_company || x.prospect || x.title || 'Untitled link'; }
	function p1Download(name, text, type) {
		var blob = new Blob([text], { type: type || 'text/plain' });
		var a = document.createElement('a');
		a.href = URL.createObjectURL(blob);
		a.download = name;
		document.body.appendChild(a);
		a.click();
		setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1500);
	}
	/* A QR code as a 1200px PNG with a white border, for print shops. */
	function p1QrPng(url, name) {
		var svg;
		try { svg = AXHQR.svg(url, { quiet: 4 }); } catch (e) { toast('That link is too long for a QR code.', 'bad'); return; }
		if (svg.indexOf('xmlns=') < 0) svg = svg.replace('<svg', '<svg xmlns="http://www.w3.org/2000/svg"');
		var img = new Image();
		img.onload = function () {
			var c = document.createElement('canvas'); c.width = c.height = 1200;
			var g = c.getContext('2d'); g.fillStyle = '#fff'; g.fillRect(0, 0, 1200, 1200);
			g.imageSmoothingEnabled = false;
			g.drawImage(img, 0, 0, 1200, 1200);
			c.toBlob(function (b) {
				var a = document.createElement('a'); a.href = URL.createObjectURL(b); a.download = name; document.body.appendChild(a); a.click();
				setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1500);
			}, 'image/png');
		};
		img.onerror = function () { toast('Could not draw the QR code.', 'bad'); };
		img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
	}
	/* A one-page flyer with the QR code, the headline and the rep. */
	function p1PrintKit(url, headline, sub) {
		var svg;
		try { svg = AXHQR.svg(url, { quiet: 2 }); } catch (e) { toast('That link is too long for a QR code.', 'bad'); return; }
		var w = window.open('', '_blank');
		if (!w) { toast('Allow pop-ups to print.', 'bad'); return; }
		var me = S.boot.user, ph = (S.boot.p1 && S.boot.p1.phone) || '';
		w.document.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>Print kit</title><style>' +
			'@page{size:letter;margin:0}*{box-sizing:border-box}body{margin:0;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#14181E}' +
			'.pg{width:8.5in;height:11in;padding:.7in;display:flex;flex-direction:column;align-items:center;text-align:center}' +
			'.bar{width:100%;height:10px;background:#EF5D0F;border-radius:6px;margin-bottom:.45in}' +
			'.lg{font-weight:800;letter-spacing:.08em;font-size:22px}.lg b{color:#EF5D0F}' +
			'h1{font-size:42px;line-height:1.1;margin:.45in 0 .14in;max-width:6.6in}p.s{font-size:18px;color:#475467;margin:0 0 .35in;max-width:6in;line-height:1.45}' +
			'.qr{width:3.6in;height:3.6in;padding:.12in;border:3px solid #14181E;border-radius:18px}.qr svg{width:100%;height:100%}' +
			'.cta{margin-top:.25in;font-size:20px;font-weight:700}.u{margin-top:6px;font-size:13px;color:#69727D;word-break:break-all}' +
			'.rep{margin-top:auto;font-size:15px;color:#334155}.rep b{display:block;font-size:18px;color:#14181E}' +
			'.cut{display:grid;grid-template-columns:repeat(3,1fr);gap:.2in;width:100%;margin-top:.3in}' +
			'</style></head><body><div class="pg"><div class="bar"></div><div class="lg">ADAPT<b>EXEC</b></div>' +
			'<h1>' + esc(headline || 'Let’s build something that fits.') + '</h1>' +
			(sub ? '<p class="s">' + esc(sub) + '</p>' : '<p class="s">Websites, custom CRMs and the systems behind them, built around how your business runs.</p>') +
			'<div class="qr">' + svg + '</div><div class="cta">Scan with your phone camera</div><div class="u">' + esc(url) + '</div>' +
			'<div class="rep"><b>' + esc(me.name) + '</b>Your AdaptExec representative' + (ph ? ' · ' + esc(ph) : '') + '</div></div></body></html>');
		w.document.close();
		w.focus();
		setTimeout(function () { w.print(); }, 450);
	}

	/* ---------- shared links: list */
	function viewShares(d) {
		var shares = d.shares || [];
		S.shareDecks = d.decks; S.shareForms = d.forms;
		var html = '<div class="axh-page-head"><div><h1 class="thunder">Shared links</h1>' +
			'<p>Send a deck as a link. You see who opened it, how long they spent on each slide, whether they finished and whether they clicked your button, and you get a ping when they do.</p></div>' +
			'<button class="axh-btn axh-btn--primary" data-share-deck="">' + icon('plus', 15) + ' New link</button></div>';
		if (!shares.length) {
			return html + '<div class="axh-card">' + empty('No shared links yet',
				'Pick a deck and share it as a link. Put it in an email, a text, or a video description, and you will see every visit here.',
				'<button class="axh-btn axh-btn--primary" data-share-deck="">Share a deck</button>') + '</div>';
		}
		var tot = { visits: 0, people: 0, cta: 0, hot: 0, live: 0 };
		shares.forEach(function (x) {
			var st = x.stats || {};
			tot.visits += Math.max(Number(x.views) || 0, st.visits || 0);
			tot.people += st.people || 0;
			tot.cta += st.cta || 0;
			tot.hot += st.hot ? 1 : 0;
			tot.live += Number(x.active) ? 1 : 0;
		});
		html += '<div class="axh-stats">' +
			stat('Live links', tot.live, shares.length + ' in total', false) +
			stat('Visits', tot.visits, tot.people ? tot.people + (tot.people === 1 ? ' person' : ' people') + ' (by device)' : 'Real browsers only', true) +
			stat('Clicked your button', tot.cta, 'Ready to talk', false) +
			stat('Hot right now', tot.hot, tot.hot ? 'Viewed again today, or clicked' : 'Nothing hot yet', false) + '</div>';
		html += '<div class="axh-card p1-links"><table class="axh-table axh-table--stack"><thead><tr><th>Link</th>' +
			(S.boot.user.manager ? '<th>Rep</th>' : '') +
			'<th class="num">Visits</th><th class="num">People</th><th class="num">Avg time</th><th class="num">Finished</th><th class="num">Clicked</th><th>Last viewed</th><th><span class="axh-sr">Actions</span></th></tr></thead><tbody>' +
			shares.map(function (x) {
				var st = x.stats || {}, visits = Math.max(Number(x.views) || 0, st.visits || 0);
				return '<tr class="p1-row" data-link="' + x.id + '"><td data-th="Link"><div class="axh-co"><b>' + esc(p1Who(x)) +
					(st.hot ? ' <span class="p1-hot">' + icon('spark', 11) + ' Hot</span>' : '') +
					(Number(x.active) ? '' : ' <span class="axh-badge">Paused</span>') + '</b>' +
					'<span>' + esc(x.deck_title || p1DeckTitle(x.deck)) + (x.lead_id ? ' · on a deal' : '') + '</span></div></td>' +
					(S.boot.user.manager ? '<td data-th="Rep">' + esc(x.rep_name || '') + '</td>' : '') +
					'<td data-th="Visits" class="num">' + visits + '</td>' +
					'<td data-th="People" class="num">' + (st.people || 0) + '</td>' +
					'<td data-th="Avg time" class="num">' + (st.visits ? p1Dur(st.avg_seconds) : '—') + '</td>' +
					'<td data-th="Finished" class="num">' + (st.visits ? st.finished : (x.finishes || 0)) + '</td>' +
					'<td data-th="Clicked" class="num">' + (st.visits ? st.cta : (x.cta_clicks || 0)) + '</td>' +
					'<td data-th="Last viewed">' + (x.last_viewed ? esc(p1Ago(x.last_viewed)) : '<span class="axh-sub">Not opened yet</span>') + '</td>' +
					'<td class="axh-row-acts"><button class="axh-btn axh-btn--ghost axh-btn--sm" data-copy="' + esc(x.url) + '">' + icon('copy', 13) + ' Copy</button>' +
					'<button class="axh-btn axh-btn--quiet axh-btn--sm" data-link-send="' + x.id + '">' + icon('send', 13) + ' Send</button></td></tr>';
			}).join('') + '</tbody></table></div>';
		return html;
	}

	/* ---------- shared links: one link */
	function viewLink(d) {
		var x = d.share, st = d.stats || { visits: 0, people: 0, finished: 0, cta: 0, avg_seconds: 0 };
		var visits = d.visits || [], slides = d.slides || [];
		S.p1.link = d;
		var who = x.lead ? x.lead.company : (x.prospect || x.title || 'Untitled link');
		var html = '<a class="axh-btn axh-btn--quiet axh-btn--sm" href="#/shares" style="margin-bottom:14px">' + icon('back', 14) + ' Shared links</a>' +
			'<div class="axh-page-head"><div><h1 class="thunder">' + esc(who) + (st.hot ? ' <span class="p1-hot p1-hot--lg">' + icon('spark', 13) + ' Hot</span>' : '') + '</h1>' +
			'<p>' + esc(x.deck_title) + ' · created ' + whenLc(x.created_at) + (Number(x.active) ? '' : ' · <b>paused</b>') +
			(x.lead ? ' · <a href="#/lead/' + x.lead.id + '">Open the deal</a>' : '') + '</p></div>' +
			'<div class="axh-actions">' +
			'<button class="axh-btn axh-btn--primary" data-link-send="' + x.id + '">' + icon('send', 15) + ' Send it</button>' +
			'<button class="axh-btn axh-btn--ghost" data-copy="' + esc(x.url) + '">' + icon('copy', 15) + ' Copy</button>' +
			'<a class="axh-btn axh-btn--ghost" href="' + esc(x.url) + '" target="_blank" rel="noopener">' + icon('eye', 15) + ' Preview</a>' +
			'<button class="axh-btn axh-btn--ghost" data-share-qr="' + esc(x.url) + '">' + icon('qr', 15) + ' QR</button>' +
			'<button class="axh-btn axh-btn--ghost" data-link-edit="' + x.id + '">Edit</button>' +
			'<button class="axh-btn axh-btn--ghost" data-link-pause="' + x.id + '" data-on="' + (Number(x.active) ? 0 : 1) + '">' + (Number(x.active) ? 'Pause' : 'Turn back on') + '</button>' +
			'<button class="axh-btn axh-btn--quiet" data-del-share="' + x.id + '">Delete</button></div></div>';

		html += '<div class="axh-stats">' +
			stat('Visits', st.visits + (d.legacy ? d.legacy : 0), st.visits ? (st.people || 0) + (st.people === 1 ? ' person' : ' people') + ' · last ' + esc(p1Ago(st.last_at)) : 'Not opened yet', true) +
			stat('Average visit', st.visits ? p1Dur(st.avg_seconds) : '—', 'Time with the deck on screen', false) +
			stat('Watched to the end', st.visits ? st.finished + ' of ' + st.visits : '—', st.visits ? Math.round(st.finished / st.visits * 100) + '% of visits' : 'No visits yet', false) +
			stat('Clicked your button', st.cta || 0, x.form ? 'Goes to ' + esc(x.form.name) : 'Goes to the AdaptExec contact page', false) + '</div>';

		var maxAvg = Math.max.apply(null, slides.map(function (s) { return s.avg; }).concat([1]));
		var n = st.visits || 0;
		html += '<div class="axh-grid axh-grid--2"><div class="axh-card"><div class="axh-card__h"><h3>Where they spent time</h3><span class="axh-hint">Average per visit</span></div>' +
			(n ? '<ol class="p1-slides">' + slides.map(function (s, i) {
				var reach = n ? Math.round(s.reached / n * 100) : 0;
				return '<li class="' + (s.avg >= maxAvg * 0.6 && s.avg > 5 ? 'is-top' : '') + (s.reached ? '' : ' is-none') + '"><span class="p1-sn">' + (i + 1) + '</span>' +
					'<div class="p1-sb"><div class="p1-st" title="' + esc(s.title) + '">' + esc(s.title) + '</div>' +
					'<div class="p1-bar"><i style="width:' + Math.round(s.avg / maxAvg * 100) + '%"></i></div></div>' +
					'<div class="p1-sv"><b>' + (s.avg ? p1Dur(s.avg) : '—') + '</b><span>' + reach + '% saw it</span></div></li>';
			}).join('') + '</ol>'
				: '<p class="axh-hint">Once someone opens the link you will see how long they spent on each slide and how far they got. Send it, then check back.</p>') + '</div>';

		html += '<div class="axh-card"><div class="axh-card__h"><h3>Every visit</h3><span class="axh-hint">' + visits.length + (visits.length === 1 ? ' visit' : ' visits') + '</span></div>' +
			(visits.length ? '<ul class="p1-visits">' + visits.map(function (v) {
				var pct = v.slides ? Math.round(v.furthest / v.slides * 100) : 0;
				var person = v.person !== null && v.person !== undefined ? 'Visitor ' + String.fromCharCode(65 + (v.person % 26)) : 'Visitor';
				return '<li><span class="p1-dev" title="' + esc(v.device || 'unknown device') + '">' + icon(v.device === 'phone' ? 'phone' : (v.device === 'tablet' ? 'doc' : 'play'), 15) + '</span>' +
					'<div class="p1-vm"><b>' + esc(when(v.started_at)) + ' · ' + esc(p1Clock(v.started_at)) + '</b><span>' + person + ' · ' + p1Dur(v.seconds) + ' · got to slide ' + v.furthest + (v.slides ? ' of ' + v.slides : '') + '</span>' +
					'<div class="p1-bar p1-bar--thin"><i style="width:' + pct + '%"></i></div></div>' +
					'<div class="p1-vb">' + (Number(v.finished) ? '<span class="axh-badge axh-badge--green">Finished</span>' : '') +
					(Number(v.cta) ? '<span class="axh-badge axh-badge--orange">Clicked</span>' : '') + '</div></li>';
			}).join('') + '</ul>' : '<p class="axh-hint">No visits yet. Your own clicks on the link are never counted, so you can check it freely.</p>') +
			(d.legacy ? '<p class="axh-hint" style="margin-top:10px">' + d.legacy + ' earlier ' + (d.legacy === 1 ? 'view was' : 'views were') + ' counted before visit detail existed, so ' + (d.legacy === 1 ? 'it has' : 'they have') + ' no breakdown.</p>' : '') +
			'</div></div>';
		return html;
	}

	/* ---------- shared links: create / edit / send */
	function p1Leads() {
		if (S.leads && S.leads.length) return Promise.resolve(S.leads);
		return api('/leads').then(function (r) { S.leads = r.leads || []; return S.leads; }).catch(function () { return []; });
	}
	function p1LeadOptions(leads, sel) {
		var open = (leads || []).filter(function (l) { return ['won', 'lost'].indexOf(l.stage) < 0 && !l.archived_at; });
		return '<option value="">Not linked to a deal</option>' + open.map(function (l) {
			return '<option value="' + l.id + '"' + (String(l.id) === String(sel || '') ? ' selected' : '') + '>' + esc(l.company) + (l.contact_name ? ' — ' + esc(l.contact_name) : '') + '</option>';
		}).join('');
	}
	function shareModal(deck, decks, forms, leadId) {
		p1Leads().then(function (leads) {
			var lead = leadId ? leads.filter(function (l) { return String(l.id) === String(leadId); })[0] : null;
			if (!deck && lead && lead.industry) {
				var match = decks.filter(function (d) { return d.industry && d.industry.toLowerCase() === String(lead.industry).toLowerCase(); })[0];
				if (match) deck = match.slug;
			}
			var deckOpts = decks.map(function (d) {
				return '<option value="' + esc(d.slug) + '"' + (d.slug === deck ? ' selected' : '') + '>' + esc(d.title) + '</option>';
			}).join('');
			var formOpts = '<option value="">AdaptExec contact page</option>' + forms.filter(function (f) { return Number(f.active); }).map(function (f) {
				return '<option value="' + f.id + '">' + esc(f.name) + '</option>';
			}).join('');
			var m = modal('Share a deck',
				'<div class="axh-field"><label for="p1-sd">Deck</label><select id="p1-sd" data-s="deck">' + deckOpts + '</select></div>' +
				'<div class="axh-field"><label for="p1-sl">Deal</label><select id="p1-sl" data-s="lead_id">' + p1LeadOptions(leads, leadId) + '</select>' +
				'<div class="axh-field__help">Linked links put every visit on the deal’s timeline and name the company in your alerts.</div></div>' +
				'<div class="axh-field"><label for="p1-sp">Who is it for?</label><input id="p1-sp" type="text" data-s="prospect" value="' + esc(lead ? lead.company : '') + '" placeholder="Summit Ridge Automotive"></div>' +
				'<div class="axh-field"><label for="p1-sf">The button on every slide goes to</label><select id="p1-sf" data-s="form_id">' + formOpts + '</select>' +
				'<div class="axh-field__help">Pick one of your lead forms and their answers arrive as a lead with this link as the source.</div></div>' +
				'<div class="axh-field"><label for="p1-sc">Button wording</label><input id="p1-sc" type="text" data-s="cta_label" placeholder="Tell us about your business"></div>',
				'<button class="axh-btn axh-btn--quiet" data-close>Cancel</button><button class="axh-btn axh-btn--primary" data-make-share>Create the link</button>');
			var ls = m.querySelector('[data-s="lead_id"]'), pr = m.querySelector('[data-s="prospect"]');
			ls.onchange = function () {
				var l = leads.filter(function (z) { return String(z.id) === ls.value; })[0];
				if (l && (!pr.value || pr.getAttribute('data-auto'))) { pr.value = l.company; pr.setAttribute('data-auto', '1'); }
			};
			m.querySelector('[data-make-share]').onclick = function () {
				var d = {};
				m.querySelectorAll('[data-s]').forEach(function (f) { d[f.getAttribute('data-s')] = f.value; });
				d.title = d.prospect;
				d.lead_id = d.lead_id ? Number(d.lead_id) : null;
				d.form_id = d.form_id ? Number(d.form_id) : null;
				api('/shares', { method: 'POST', body: d }).then(function (r) {
					closeModal();
					toast('Link created.', 'ok');
					shareDone(r.share, leads);
					if (/^#\/(shares|lead\/)/.test(location.hash)) route();
				}).catch(function (e) { toast(e.message, 'bad'); });
			};
		});
	}

	function shareDone(share, leads) {
		var m = modal('Your link is live',
			'<div class="axh-sharebox"><input type="text" readonly value="' + esc(share.url) + '" data-share-url>' +
			'<button class="axh-btn axh-btn--dark" data-copy="' + esc(share.url) + '">Copy</button></div>' +
			'<div class="axh-qrbox" id="axh-share-qr"></div>' +
			'<p class="axh-hint">Anyone who opens it sees the slides, never the Hub. You get a ping when they open it, come back to it, or click your button. Opening it yourself is never counted.</p>',
			'<button class="axh-btn axh-btn--ghost" data-close>Done</button><button class="axh-btn axh-btn--primary" data-link-send="' + share.id + '">' + icon('send', 14) + ' Write the message</button>');
		var box = m.querySelector('#axh-share-qr');
		try { box.innerHTML = AXHQR.svg(share.url, { quiet: 3 }); } catch (e) { box.innerHTML = '<p class="axh-hint">Link is too long for a QR code.</p>'; }
		S.p1.lastShare = share;
	}

	/* Ready-to-send wording for a link: text, email and a social DM. */
	function p1SendModal(share) {
		var lead = share.lead || null;
		if (!lead && share.lead_id && S.leads) {
			var l0 = S.leads.filter(function (z) { return String(z.id) === String(share.lead_id); })[0];
			if (l0) lead = l0;
		}
		var first = lead && lead.contact_name ? String(lead.contact_name).split(' ')[0] : '';
		var hi = first ? 'Hi ' + first + ',' : 'Hi,';
		var me = S.boot.user.name, ph = (S.boot.p1 && S.boot.p1.phone) || '';
		var deck = share.deck_title || p1DeckTitle(share.deck), url = share.url;
		var t = {
			text: hi + ' it’s ' + me.split(' ')[0] + ' with AdaptExec. Here’s the short walkthrough I mentioned, with a live demo inside: ' + url + ' — takes about 5 minutes. Happy to answer anything.',
			email: hi + '\n\nThanks for your time. Here’s a short walkthrough of what AdaptExec builds for businesses like yours, including a live demo you can click around in:\n\n' + url + '\n\nIt takes about five minutes. If anything in it looks useful, reply with a good day and time and I’ll set up 20 minutes to learn what you need. There’s no obligation.\n\nThanks,\n' + me + '\nIndependent Sales Representative for AdaptExec' + (ph ? '\n' + ph : ''),
			dm: hi + ' I put together a short walkthrough of what we build for ' + (lead && lead.industry ? String(lead.industry).toLowerCase() + ' businesses' : 'businesses like yours') + ', with a live demo inside: ' + url + ' — would love your take.'
		};
		var subject = deck + ' — a 5-minute walkthrough';
		var mail = 'mailto:' + (lead && lead.email ? encodeURIComponent(lead.email) : '') + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(t.email);
		var sms = 'sms:' + (lead && lead.phone ? String(lead.phone).replace(/[^\d+]/g, '') : '') + '?&body=' + encodeURIComponent(t.text);
		var m = modal('Send the link',
			'<div class="p1-tabs" role="tablist"><button class="is-on" data-p1-tab="text">Text</button><button data-p1-tab="email">Email</button><button data-p1-tab="dm">Social DM</button></div>' +
			'<textarea class="p1-msg" data-p1-msg rows="9">' + esc(t.text) + '</textarea>' +
			'<p class="axh-hint" data-p1-note>Short and personal works best. Edit anything before you copy it.</p>',
			'<a class="axh-btn axh-btn--ghost" data-p1-open href="' + esc(sms) + '">' + icon('msg', 14) + ' Open in Messages</a>' +
			'<button class="axh-btn axh-btn--primary" data-p1-copy>' + icon('copy', 14) + ' Copy message</button>');
		var ta = m.querySelector('[data-p1-msg]'), op = m.querySelector('[data-p1-open]');
		m.querySelectorAll('[data-p1-tab]').forEach(function (b) {
			b.onclick = function () {
				m.querySelectorAll('[data-p1-tab]').forEach(function (x) { x.classList.toggle('is-on', x === b); });
				var k = b.getAttribute('data-p1-tab');
				ta.value = t[k];
				if (k === 'email') { op.href = mail; op.innerHTML = icon('mail', 14) + ' Open in email'; op.hidden = false; }
				else if (k === 'text') { op.href = sms; op.innerHTML = icon('msg', 14) + ' Open in Messages'; op.hidden = false; }
				else op.hidden = true;
			};
		});
		m.querySelector('[data-p1-copy]').onclick = function () {
			var v = ta.value;
			(navigator.clipboard ? navigator.clipboard.writeText(v) : Promise.reject()).then(function () { toast('Copied. Paste it into your message.', 'ok'); }, function () { ta.select(); document.execCommand('copy'); toast('Copied.', 'ok'); });
		};
	}

	function p1EditLink(x) {
		p1Leads().then(function (leads) {
			var forms = S.shareForms || [];
			var formOpts = '<option value="">AdaptExec contact page</option>' + forms.map(function (f) {
				return '<option value="' + f.id + '"' + (String(f.id) === String(x.form_id || '') ? ' selected' : '') + '>' + esc(f.name) + '</option>';
			}).join('');
			var m = modal('Edit link',
				'<div class="axh-field"><label for="p1-ep">Who is it for?</label><input id="p1-ep" type="text" data-e="prospect" value="' + esc(x.prospect || x.title || '') + '"></div>' +
				'<div class="axh-field"><label for="p1-el">Deal</label><select id="p1-el" data-e="lead_id">' + p1LeadOptions(leads, x.lead_id) + '</select></div>' +
				'<div class="axh-field"><label for="p1-ef">The button goes to</label><select id="p1-ef" data-e="form_id">' + formOpts + '</select></div>' +
				'<div class="axh-field"><label for="p1-ec">Button wording</label><input id="p1-ec" type="text" data-e="cta_label" value="' + esc(x.cta_label || '') + '" placeholder="Tell us about your business"></div>',
				'<button class="axh-btn axh-btn--quiet" data-close>Cancel</button><button class="axh-btn axh-btn--primary" data-e-save>Save</button>');
			m.querySelector('[data-e-save]').onclick = function () {
				var b = {};
				m.querySelectorAll('[data-e]').forEach(function (f) { b[f.getAttribute('data-e')] = f.value; });
				b.title = b.prospect;
				b.lead_id = b.lead_id ? Number(b.lead_id) : null;
				b.form_id = b.form_id ? Number(b.form_id) : null;
				api('/shares/' + x.id, { method: 'POST', body: b }).then(function () { closeModal(); toast('Saved.', 'ok'); route(); }).catch(function (e) { toast(e.message, 'bad'); });
			};
		});
	}

	/* ---------- lead page extras: links, consent, commission */
	function p1LeadCard(id) {
		var slot = document.querySelector('[data-p1-lead="' + id + '"]');
		if (!slot) return;
		api('/p1/lead/' + id).then(function (d) {
			slot = document.querySelector('[data-p1-lead="' + id + '"]');
			if (!slot) return;
			var html = '';
			var p = d.pays || {}, mgr = S.boot.user.manager;
			if (p.value > 0 && p.rate > 0) {
				html += '<div class="p1-pays"><span>' + icon('money', 16) + '</span><div><b>' + (mgr ? 'Rep commission ' : 'This deal pays you ') + (p.basis === 'locked' ? '' : 'about ') + moneyC(p.amount) + '</b>' +
					'<em>' + pctTxt(p.rate) + ' of ' + money2(p.value) + (p.basis === 'locked' ? ' contract, locked when it closed' : ' estimated value · final figure comes from the signed contract') + '</em></div></div>';
			}
			html += '<div class="axh-card" style="margin-top:16px"><div class="axh-card__h"><h3>Deck links</h3><button data-share-deck="" data-share-lead="' + id + '">' + icon('plus', 13) + ' Share a deck</button></div>' +
				(d.links && d.links.length ? '<ul class="p1-ll">' + d.links.map(function (k) {
					var st = k.stats || {};
					return '<li><a href="#/link/' + k.id + '"><b>' + esc(k.deck_title) + (st.hot ? ' <span class="p1-hot">' + icon('spark', 11) + ' Hot</span>' : '') + (k.active ? '' : ' <span class="axh-badge">Paused</span>') + '</b>' +
						'<span>' + (st.visits ? st.visits + (st.visits === 1 ? ' visit' : ' visits') + ' · ' + p1Dur(st.avg_seconds) + ' avg' + (st.finished ? ' · finished' : '') + (st.cta ? ' · clicked' : '') + ' · ' + esc(p1Ago(st.last_at)) : 'Not opened yet · sent ' + whenLc(k.created_at)) + '</span></a>' +
						'<button class="fu-ic" data-copy="' + esc(k.url) + '" title="Copy link">' + icon('copy', 14) + '</button></li>';
				}).join('') + '</ul>' : '<p class="axh-hint">Send them a deck as a link and you will see here when they open it, how long they spend, and whether they click your button.</p>') + '</div>';
			slot.innerHTML = html;
			// Call/text consent from the form they sent, next to the phone number.
			var meta = document.querySelector('.axh-lead-head .axh-meta');
			if (meta && d.consent && !meta.querySelector('.p1-consent')) {
				var c = document.createElement('span');
				c.className = 'p1-consent is-' + d.consent;
				c.innerHTML = d.consent === 'yes' ? icon('check', 12) + ' OK to call/text' : icon('flag', 12) + ' No call/text consent — email first';
				c.title = d.consent === 'yes' ? 'They ticked the consent box on your form.' : 'They left the call/text consent box unticked on your form.';
				meta.appendChild(c);
			}
		}).catch(function () {});
	}

	/* ---------- presenting a deck to a deal */
	function p1PresentPicker(lead) {
		var decks = (S.boot.slideDecks || []).slice();
		var ind = String(lead.industry || '').toLowerCase();
		decks.sort(function (a, b) {
			var am = a.industry && a.industry.toLowerCase() === ind ? 0 : 1, bm = b.industry && b.industry.toLowerCase() === ind ? 0 : 1;
			return am - bm || (a.group === 'master' ? -1 : 0) - (b.group === 'master' ? -1 : 0);
		});
		var m = modal('Present to ' + esc(lead.company),
			'<p style="font-size:13.5px;color:var(--gray);margin:0 0 12px">How far you get and how long it takes go on the deal’s timeline when you finish.</p>' +
			'<div class="p1-decks">' + decks.map(function (d) {
				var hit2 = ind && d.industry && d.industry.toLowerCase() === ind;
				return '<button class="p1-deck' + (hit2 ? ' is-match' : '') + '" data-pick-slides="' + esc(d.slug) + '">' +
					(d.thumb ? '<img src="' + esc(d.thumb) + '" alt="" loading="lazy">' : '<i style="background:' + esc(d.accent) + '"></i>') +
					'<b>' + esc(d.title) + '</b>' + (hit2 ? '<em>Their industry</em>' : (d.minutes ? '<em>~' + d.minutes + ' min</em>' : '')) + '</button>';
			}).join('') + '</div>',
			'<button class="axh-btn axh-btn--ghost" data-share-deck="" data-share-lead="' + lead.id + '">' + icon('link', 14) + ' Send as a link instead</button>');
		m.querySelectorAll('[data-pick-slides]').forEach(function (b) {
			b.onclick = function () { closeModal(); openSlides(b.getAttribute('data-pick-slides'), lead.id); };
		});
	}
	window.addEventListener('message', function (e) {
		if (e.origin !== location.origin || !e.data || e.data.axd !== 'pos' || !P.slidesMode) return;
		P.furthest = Math.max(P.furthest || 1, Number(e.data.i) || 1);
		P.slides = Number(e.data.n) || P.slides || 0;
		var c = el('axh-p-pos');
		if (c) c.textContent = 'Slide ' + e.data.i + ' of ' + e.data.n;
	});

	/* ---------- forms: editor */
	var MERGE = [['{first_name}', 'First name'], ['{company}', 'Business'], ['{rep_name}', 'Your name'], ['{rep_phone}', 'Your phone'], ['{booking_link}', 'Booking link']];
	function viewForm(d) {
		var f = d.form, cat = S.boot.formCatalog || [], on = f.fields || [];
		var x = f.extra || {};
		S.formCustom = (f.custom || []).map(function (c) { return { label: c.label, type: c.type || 'text', required: !!c.required, options: (c.options || []).join('\n') }; });
		S.formDirty = false;
		var conv = Number(f.views) ? Math.round(f.submissions / f.views * 100) : 0;
		var booking = S.boot.p1 && S.boot.p1.bookingUrl;
		var html = '<a class="axh-btn axh-btn--quiet axh-btn--sm" href="#/forms" style="margin-bottom:14px">' + icon('back', 14) + ' All forms</a>' +
			'<div class="axh-page-head"><div><h1 class="thunder">' + esc(f.name) + '</h1>' +
			'<p>' + f.views + ' views · ' + f.submissions + ' submissions · ' + conv + '% convert</p></div>' +
			'<div class="axh-actions">' +
			'<a class="axh-btn axh-btn--ghost" href="' + esc(f.url) + '" target="_blank" rel="noopener">' + icon('eye', 15) + ' Open live form</a>' +
			'<button class="axh-btn axh-btn--ghost" data-form-phone="' + esc(f.url) + '">' + icon('phone', 15) + ' Phone preview</button>' +
			'<button class="axh-btn axh-btn--primary" data-save-form="' + f.id + '">Save changes</button></div></div>';

		html += '<div class="axh-grid axh-grid--2 p1-formed" data-form-editor="' + f.id + '">';
		html += '<div><div class="axh-card"><div class="axh-card__h"><h3>What people see</h3></div>' +
			'<div class="axh-form-grid">' +
			fInp('name', 'Form name (internal)', f.name, true) +
			fInp('source_tag', 'Source tag', f.source_tag, true, 'youtube-video, spring-flyer…') +
			(S.boot.user.manager && (S.boot.assignees || []).length
				? '<div class="axh-field axh-span"><label for="axh-fo">Leads go to</label><select id="axh-fo" data-ff="rep_id">' +
					S.boot.assignees.map(function (a) {
						return '<option value="' + a.id + '"' + (String(a.id) === String(f.rep_id) ? ' selected' : '') + '>' + esc(a.name) + (a.manager ? ' (admin)' : '') + '</option>';
					}).join('') + '</select><div class="axh-field__help">Every submission becomes a lead owned by this person, and they are emailed about it.</div></div>'
				: '') +
			'<div class="axh-field axh-span"><label for="p1-fh">Headline</label><input id="p1-fh" type="text" data-ff="headline" value="' + esc(f.headline) + '" placeholder="Let\'s build something that fits."></div>' +
			'<div class="axh-field axh-span"><label for="p1-fs">Sub-headline</label><textarea id="p1-fs" data-ff="subhead" style="min-height:64px">' + esc(f.subhead) + '</textarea></div>' +
			'<div class="axh-field"><label for="p1-fi">Industry</label><select id="p1-fi" data-ff="industry"><option value="">General</option>' +
			S.boot.industries.map(function (i) { return '<option' + (f.industry === i ? ' selected' : '') + '>' + i + '</option>'; }).join('') + '</select></div>' +
			'<div class="axh-field"><label>Accent color</label><div class="axh-swatches">' +
			['#EF5D0F', '#046BD2', '#0E7490', '#B4536B', '#0F766E', '#6D28D9', '#B91C1C', '#14181E'].map(function (c) {
				return '<button type="button" class="axh-sw' + (f.accent.toLowerCase() === c.toLowerCase() ? ' is-on' : '') + '" data-accent="' + c + '" style="background:' + c + '" aria-label="Accent ' + c + '"></button>';
			}).join('') + '</div></div>' +
			'</div></div>';

		html += '<div class="axh-card" style="margin-top:16px"><div class="axh-card__h"><h3>What you collect</h3>' +
			'<span class="mini" style="font-size:11.5px;color:var(--gray)">' + on.length + ' fields on</span></div>' +
			'<p style="font-size:12.5px;color:var(--gray);margin-bottom:14px">More questions means better-qualified leads but fewer submissions. Name and email are always on.</p>' +
			cat.map(function (step) {
				return '<div class="axh-modcat"><div class="axh-modcat__h"><h4>' + step.name + '</h4></div><div class="axh-chips">' +
					step.fields.map(function (fl) {
						var locked = fl.key === 'name' || fl.key === 'email';
						return '<button type="button" class="axh-chip' + (on.indexOf(fl.key) > -1 ? ' is-must' : '') + (locked ? ' is-locked' : '') + '" data-fld="' + fl.key + '"' + (locked ? ' disabled' : '') + '>' + fl.label + '</button>';
					}).join('') + '</div></div>';
			}).join('') + '</div>';

		html += '<div class="axh-card" style="margin-top:16px"><div class="axh-card__h"><h3>Your own questions</h3><span class="axh-hint">Up to 6</span></div>' +
			'<div data-custom-box>' + p1CustomRows() + '</div></div>';

		html += '<div class="axh-card" style="margin-top:16px"><div class="axh-card__h"><h3>After they send it</h3></div>' +
			'<div class="axh-form-grid">' +
			'<div class="axh-field axh-span"><label for="p1-th">Thank-you headline</label><input id="p1-th" type="text" data-ff="thanks_headline" value="' + esc(f.thanks_headline) + '" placeholder="Thank you — we have got it."></div>' +
			'<div class="axh-field axh-span"><label for="p1-tb">Thank-you message</label><textarea id="p1-tb" data-ff="thanks_body" style="min-height:64px">' + esc(f.thanks_body) + '</textarea></div></div>' +
			'<label class="axh-toggle"><input type="checkbox" data-fx="booking"' + (x.booking ? ' checked' : '') + '><span></span><div><strong>Show a “Pick a time with ' + esc(S.boot.user.name.split(' ')[0]) + '” button</strong>' +
				'<em>' + (booking ? 'Opens your booking link: ' + esc(booking) : 'Add your booking link (Calendly, Google, Microsoft) under <a href="#/settings">Settings</a> first.') + '</em></div></label>' +
			'<label class="axh-toggle" style="margin-top:12px"><input type="checkbox" data-fx="autoreply"' + (x.autoreply ? ' checked' : '') + '><span></span><div><strong>Send an instant reply</strong>' +
				'<em>Emails them the moment they submit, from you, so they know a real person has it. Replies come to your inbox.</em></div></label>' +
			'<div class="p1-ar" data-ar-box' + (x.autoreply ? '' : ' hidden') + '>' +
				'<div class="axh-field"><label for="p1-as">Subject</label><input id="p1-as" type="text" data-fx-text="ar_subject" value="' + esc(x.ar_subject || '') + '"></div>' +
				'<div class="axh-field"><label for="p1-ab">Message</label><textarea id="p1-ab" data-fx-text="ar_body" rows="8">' + esc(x.ar_body || '') + '</textarea>' +
				'<div class="p1-merge">Insert: ' + MERGE.map(function (k) { return '<button type="button" data-merge="' + k[0] + '">' + k[1] + '</button>'; }).join('') + '</div>' +
				'<div class="axh-field__help">A line with {booking_link} is left out automatically if you have no booking link.</div></div></div>' +
			'<label class="axh-toggle" style="margin-top:12px"><input type="checkbox" data-fx="consent"' + (x.consent !== false ? ' checked' : '') + '><span></span><div><strong>Ask for call/text consent</strong>' +
				'<em>Adds a tick box under the phone number. Whether they ticked it shows on the lead, so you know when to call or text and when to email first.</em></div></label>' +
			'<div class="axh-field" style="margin-top:14px"><label for="p1-rd">Send them to a page instead (optional)</label><input id="p1-rd" type="text" data-ff="redirect_url" value="' + esc(f.redirect_url || '') + '" placeholder="https://…">' +
				'<div class="axh-field__help">Leave blank to show the thank-you message above. A web address here replaces it.</div></div>' +
			'</div></div>';

		// ---- right column
		html += '<div><div class="axh-card axh-share"><div class="axh-card__h"><h3>Share it</h3></div>' +
			'<div class="axh-linkbox"><input type="text" id="axh-form-url" readonly value="' + esc(f.url) + '">' +
			'<button class="axh-btn axh-btn--dark axh-btn--sm" data-copy="' + esc(f.url) + '">' + icon('copy', 13) + ' Copy</button></div>' +
			'<div class="axh-qr" id="axh-qr"></div>' +
			'<div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">' +
			'<button class="axh-btn axh-btn--ghost axh-btn--sm" data-qr-png="' + esc(f.url) + '" data-name="' + esc(f.slug) + '">' + icon('download', 13) + ' QR (PNG)</button>' +
			'<button class="axh-btn axh-btn--ghost axh-btn--sm" data-qr-download="' + esc(f.slug) + '">' + icon('download', 13) + ' QR (SVG)</button>' +
			'<button class="axh-btn axh-btn--ghost axh-btn--sm" data-print-kit="' + esc(f.url) + '">' + icon('printer', 13) + ' Print a flyer</button></div>' +
			'<div class="p1-track"><label for="p1-src">Tracked link for one place</label><div class="axh-linkbox"><input id="p1-src" type="text" data-src-in placeholder="e.g. truck-door, facebook-group, chamber-event">' +
			'<button class="axh-btn axh-btn--ghost axh-btn--sm" data-src-copy>' + icon('copy', 13) + ' Copy</button></div>' +
			'<div class="axh-field__help" data-src-out>Each place gets its own link, so the lead shows exactly where it came from.</div></div>' +
			'</div>' +

			'<div class="axh-card" style="margin-top:16px"><div class="axh-card__h"><h3>Status</h3></div>' +
			'<label class="axh-toggle"><input type="checkbox" data-ff-check="active"' + (Number(f.active) ? ' checked' : '') + '><span></span>' +
			'<div><strong>' + (Number(f.active) ? 'Live and accepting submissions' : 'Paused') + '</strong>' +
			'<em>Paused forms show a friendly "link expired" page.</em></div></label>' +
			'<label class="axh-toggle" style="margin-top:12px"><input type="checkbox" data-ff-check="style_picker"' + (Number(f.style_picker) ? ' checked' : '') + '><span></span>' +
			'<div><strong>Include the style picker</strong>' +
			'<em>Adds an optional "Pick a look you like" step with live website style previews. Their picks land on the lead and in your notification.</em></div></label>' +
			'<dl class="axh-kv" style="margin-top:14px">' + kv('Link', '/f/' + f.slug) + kv('Created', when(f.created_at)) +
			kv('Views', f.views) + kv('Submissions', f.submissions) + '</dl>' +
			'<button class="axh-btn axh-btn--danger axh-btn--sm" data-del-form="' + f.id + '" style="margin-top:14px">Delete form</button></div>';

		html += '<div class="axh-card" style="margin-top:16px"><div class="axh-card__h"><h3>Submissions</h3><a href="#/submissions/' + f.id + '">See all</a></div>' +
			((d.submissions || []).length ? '<ul class="axh-list">' +
				d.submissions.slice(0, 8).map(function (sb) {
					var pl = sb.payload || {};
					return '<li><div class="axh-list__t"><b>' + esc(pl.name || 'Someone') + '</b><span>' + esc(pl.company || pl.email || '') + ' · ' + when(sb.created_at) + '</span></div>' +
						(sb.lead_id ? '<a class="axh-list__r" href="#/lead/' + sb.lead_id + '">Open</a>' : '') + '</li>';
				}).join('') + '</ul>' : '<p class="axh-hint">Nothing yet. Share the link and submissions land here and in your pipeline.</p>') + '</div>';

		html += '</div></div>' +
			'<div class="p1-savebar" data-savebar hidden><span>' + icon('pen', 14) + ' Unsaved changes</span><button class="axh-btn axh-btn--ghost axh-btn--sm" data-form-discard>Discard</button>' +
			'<button class="axh-btn axh-btn--primary axh-btn--sm" data-save-form="' + f.id + '">Save changes</button></div>';
		return html;
	}

	function p1CustomRows() {
		var rows = S.formCustom || [];
		return (rows.length ? '<ol class="p1-cq">' + rows.map(function (c, i) {
			return '<li data-cq="' + i + '"><div class="p1-cq__h"><span>' + (i + 1) + '</span>' +
				'<input type="text" data-cq-f="label" value="' + esc(c.label) + '" placeholder="Question, e.g. How many trucks do you run?" aria-label="Question ' + (i + 1) + '">' +
				'<div class="p1-cq__b"><button type="button" class="fu-ic" data-cq-up="' + i + '" title="Move up"' + (i ? '' : ' disabled') + '>' + icon('left', 13).replace('<svg', '<svg style="transform:rotate(90deg)"') + '</button>' +
				'<button type="button" class="fu-ic" data-cq-del="' + i + '" title="Remove">' + icon('trash', 13) + '</button></div></div>' +
				'<div class="p1-cq__o"><select data-cq-f="type" aria-label="Answer type">' +
				[['text', 'Short answer'], ['textarea', 'Paragraph'], ['select', 'Pick one']].map(function (o) { return '<option value="' + o[0] + '"' + (c.type === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select>' +
				'<label class="p1-req"><input type="checkbox" data-cq-f="required"' + (c.required ? ' checked' : '') + '> Required</label></div>' +
				(c.type === 'select' ? '<textarea data-cq-f="options" rows="3" placeholder="One choice per line">' + esc(c.options) + '</textarea>' : '') +
				'</li>';
		}).join('') + '</ol>' : '<p class="axh-hint" style="margin-top:0">Ask anything the standard fields do not cover. Answers show on the lead and in your alert email.</p>') +
			(rows.length < 6 ? '<button type="button" class="axh-btn axh-btn--ghost axh-btn--sm" data-cq-add>' + icon('plus', 13) + ' Add a question</button>' : '');
	}
	function p1ReadCustom() {
		document.querySelectorAll('[data-cq]').forEach(function (li) {
			var i = +li.getAttribute('data-cq'), c = S.formCustom[i];
			if (!c) return;
			li.querySelectorAll('[data-cq-f]').forEach(function (inp) {
				var k = inp.getAttribute('data-cq-f');
				c[k] = inp.type === 'checkbox' ? inp.checked : inp.value;
			});
		});
	}
	function p1PaintCustom() { var b = document.querySelector('[data-custom-box]'); if (b) b.innerHTML = p1CustomRows(); }

	function readForm2() {
		var out = {};
		document.querySelectorAll('[data-ff]').forEach(function (i) { out[i.getAttribute('data-ff')] = i.value; });
		var act = document.querySelector('[data-ff-check="active"]');
		out.active = act ? act.checked : true;
		var sp = document.querySelector('[data-ff-check="style_picker"]');
		out.style_picker = sp ? sp.checked : false;
		out.accent = S.formAccent || (S.form && S.form.form.accent) || '#EF5D0F';
		out.fields = S.formFields || [];
		if (document.querySelector('[data-custom-box]')) {
			p1ReadCustom();
			out.custom = (S.formCustom || []).filter(function (c) { return String(c.label || '').trim(); }).map(function (c) {
				return { label: c.label, type: c.type, required: !!c.required, options: c.type === 'select' ? c.options : '' };
			});
		}
		var x = {};
		document.querySelectorAll('[data-fx]').forEach(function (i) { x[i.getAttribute('data-fx')] = i.checked; });
		document.querySelectorAll('[data-fx-text]').forEach(function (i) { x[i.getAttribute('data-fx-text')] = i.value; });
		if (Object.keys(x).length) out.extra = x;
		return out;
	}
	function p1Dirty(on) {
		S.formDirty = on;
		var b = document.querySelector('[data-savebar]');
		if (b) b.hidden = !on;
	}
	function p1SaveForm(id, body, quiet) {
		return api('/forms/' + id, { method: 'POST', body: body }).then(function (r) {
			if (!quiet) toast('Saved.', 'ok');
			return r;
		});
	}

	/* ---------- every submission */
	function viewSubmissions(d, formId) {
		var subs = d.submissions || [], forms = S.p1.subForms || [];
		var html = '<a class="axh-btn axh-btn--quiet axh-btn--sm" href="' + (formId ? '#/form/' + formId : '#/forms') + '" style="margin-bottom:14px">' + icon('back', 14) + ' ' + (formId ? 'Back to the form' : 'Lead forms') + '</a>' +
			'<div class="axh-page-head"><div><h1 class="thunder">Submissions</h1><p>' + d.total + ' in total' + (formId && forms.length ? ' for ' + esc((forms.filter(function (f) { return String(f.id) === String(formId); })[0] || {}).name || 'this form') : ' across your forms') + '. Click one to see every answer.</p></div>' +
			'<div class="axh-actions"><button class="axh-btn axh-btn--ghost" data-sub-csv="' + (formId || '') + '">' + icon('download', 15) + ' Export CSV</button></div></div>' +
			'<div class="axh-card"><div class="p1-subbar">' +
			(forms.length > 1 ? '<select data-sub-form aria-label="Form"><option value="">All forms</option>' + forms.map(function (f) { return '<option value="' + f.id + '"' + (String(f.id) === String(formId || '') ? ' selected' : '') + '>' + esc(f.name) + '</option>'; }).join('') + '</select>' : '') +
			'<input type="search" data-sub-q placeholder="Search names, emails, answers…" value="' + esc(S.p1.subQ || '') + '"></div>' +
			(subs.length ? '<table class="axh-table axh-table--stack"><thead><tr><th>Who</th><th>Business</th><th>Form</th><th>Source</th><th>When</th><th></th></tr></thead><tbody>' +
				subs.map(function (s) {
					return '<tr class="p1-row" data-sub-open="' + s.id + '"><td data-th="Who"><div class="axh-co"><b>' + esc(s.name || 'Someone') + '</b><span>' + esc([s.email, s.phone].filter(Boolean).join(' · ')) + '</span></div></td>' +
						'<td data-th="Business">' + esc(s.company || '—') + '</td><td data-th="Form">' + esc(s.form_name) + '</td>' +
						'<td data-th="Source"><span class="axh-badge axh-badge--orange">' + esc(s.source || 'form') + '</span></td>' +
						'<td data-th="When" style="color:var(--gray)">' + when(s.created_at) + ' · ' + p1Clock(s.created_at) + '</td>' +
						'<td>' + (s.lead_id ? '<a class="axh-btn axh-btn--ghost axh-btn--sm" href="#/lead/' + s.lead_id + '">Lead</a>' : '') + '</td></tr>';
				}).join('') + '</tbody></table>' : '<div style="padding:12px 0">' + empty('Nothing here', S.p1.subQ ? 'No submission matches that search.' : 'Share a form and submissions land here.', '') + '</div>') +
			(d.pages > 1 ? '<div class="p1-pager"><button class="axh-btn axh-btn--ghost axh-btn--sm" data-sub-page="' + (d.page - 1) + '"' + (d.page > 1 ? '' : ' disabled') + '>Newer</button><span>Page ' + d.page + ' of ' + d.pages + '</span>' +
				'<button class="axh-btn axh-btn--ghost axh-btn--sm" data-sub-page="' + (d.page + 1) + '"' + (d.page < d.pages ? '' : ' disabled') + '>Older</button></div>' : '') +
			'</div>';
		S.p1.subs = subs;
		return html;
	}
	function p1SubRoute(formId, render) {
		var q = ['page=' + (S.p1.subPage || 1)];
		if (formId) q.push('form_id=' + encodeURIComponent(formId));
		if (S.p1.subQ) q.push('q=' + encodeURIComponent(S.p1.subQ));
		var jobs = [api('/p1/submissions?' + q.join('&'))];
		if (!S.p1.subForms) jobs.push(api('/forms').then(function (r) { S.p1.subForms = r.forms || []; }));
		return Promise.all(jobs).then(function (r) { render(viewSubmissions(r[0], formId)); });
	}

	/* ---------- dashboard: follow-ups from the real queue */
	function p1DueList() {
		var open = (S.flow.tasks || []).filter(function (t) { return !t.done_at; });
		var due = open.filter(function (t) { var b = fuBucket(t); return b === 'overdue' || b === 'today'; });
		var order = { overdue: 0, today: 1, tomorrow: 2, week: 3, later: 4, none: 5 };
		var next = open.slice().sort(function (a, b) {
			var ba = order[fuBucket(a)], bb = order[fuBucket(b)];
			if (ba !== bb) return ba - bb;
			var da = fuDue(a), db = fuDue(b);
			return (da && !isNaN(da) ? da.getTime() : 9e15) - (db && !isNaN(db) ? db.getTime() : 9e15);
		}).slice(0, 6);
		return { due: due, next: next, overdue: open.filter(function (t) { return fuBucket(t) === 'overdue'; }).length };
	}

	/* ---------- activity timeline: icons and filters */
	var ACT_ICON = { call: 'phone', email: 'mail', text: 'msg', meeting: 'cal', note: 'pen', stage: 'board', form: 'doc', share: 'link', presentation: 'play', registration: 'flag', discovery: 'brief', style: 'spark', lost: 'x', won: 'check' };
	var ACT_GROUP = { call: 'talk', email: 'talk', text: 'talk', meeting: 'talk', note: 'notes', share: 'views', presentation: 'views', form: 'views' };
	function p1ActIcon(type) { return '<span class="p1-ai p1-ai--' + esc(ACT_GROUP[type] || 'other') + '">' + icon(ACT_ICON[type] || 'spark', 13) + '</span>'; }

	/* ---------- clicks */
	document.addEventListener('click', function (e) {
		var t = e.target;
		if (!t || !t.closest) return;
		var hit = function (sel) { return t.closest(sel); };
		var b;
		if ((b = hit('[data-link]')) && !hit('a,button,input')) { location.hash = '#/link/' + b.getAttribute('data-link'); return; }
		if ((b = hit('[data-link-send]'))) {
			e.stopPropagation();
			var sid = b.getAttribute('data-link-send');
			var one = (S.p1.link && String(S.p1.link.share.id) === sid) ? S.p1.link.share : (S.p1.lastShare && String(S.p1.lastShare.id) === sid ? S.p1.lastShare : null);
			if (one) { p1Leads().then(function () { p1SendModal(one); }); return; }
			api('/p1/shares/' + sid).then(function (d) { p1Leads().then(function () { p1SendModal(d.share); }); }).catch(function (er) { toast(er.message, 'bad'); });
			return;
		}
		if ((b = hit('[data-link-edit]'))) { e.stopPropagation(); if (S.p1.link) p1EditLink(S.p1.link.share); return; }
		if ((b = hit('[data-link-pause]'))) {
			e.stopPropagation();
			var onv = b.getAttribute('data-on') === '1';
			api('/shares/' + b.getAttribute('data-link-pause'), { method: 'POST', body: { active: onv } })
				.then(function () { toast(onv ? 'Link is live again.' : 'Paused. Anyone opening it sees a "no longer live" page.', 'ok'); route(); })
				.catch(function (er) { toast(er.message, 'bad'); });
			return;
		}
		if ((b = hit('[data-del-share]'))) {
			e.stopPropagation();
			var did = b.getAttribute('data-del-share');
			ask({ title: 'Delete this link?', body: 'Anyone who still has it will see a “no longer live” page, and its visit history is removed from this list. To stop it for now, pause it instead.', ok: 'Delete link', danger: true })
				.then(function () {
					api('/shares/' + did, { method: 'DELETE' }).then(function () { toast('Link deleted.', 'ok'); if (/^#\/link\//.test(location.hash)) location.hash = '#/shares'; else route(); })
						.catch(function (er) { toast(er.message, 'bad'); });
				});
			return;
		}
		if ((b = hit('[data-share-deck]'))) {
			e.stopPropagation();
			var want = b.getAttribute('data-share-deck'), forLead = b.getAttribute('data-share-lead');
			if (hit('#axh-modal')) closeModal();
			var open = function () { shareModal(want, S.shareDecks, S.shareForms || [], forLead); };
			if (S.shareDecks) { open(); return; }
			api('/shares').then(function (d) { S.shareDecks = d.decks; S.shareForms = d.forms; open(); }).catch(function (er) { toast(er.message, 'bad'); });
			return;
		}
		if ((b = hit('[data-present-lead]'))) { e.stopPropagation(); if (S.lead && S.lead.lead) p1PresentPicker(S.lead.lead); return; }
		if ((b = hit('[data-qr-png]'))) { e.stopPropagation(); p1QrPng(b.getAttribute('data-qr-png'), 'adaptexec-' + (b.getAttribute('data-name') || 'form') + '-qr.png'); return; }
		if ((b = hit('[data-print-kit]'))) { e.stopPropagation(); var ff = S.form && S.form.form; p1PrintKit(b.getAttribute('data-print-kit'), ff && (ff.headline || ff.name), ff && ff.subhead); return; }
		if ((b = hit('[data-qr-print]'))) { e.stopPropagation(); var f2 = S.form && S.form.form; if (f2) p1PrintKit(f2.url, f2.headline || f2.name, f2.subhead); return; }
		if ((b = hit('[data-form-phone]'))) {
			e.stopPropagation();
			modal('Phone preview', '<div class="p1-phone"><iframe src="' + esc(b.getAttribute('data-form-phone')) + '" title="Form on a phone"></iframe></div>' +
				'<p class="axh-hint" style="text-align:center">This is the live form. Save your changes first to see them here. Your own visits are not counted as views.</p>', '');
			return;
		}
		if ((b = hit('[data-src-copy]'))) {
			e.stopPropagation();
			var si = document.querySelector('[data-src-in]'), fu = S.form && S.form.form;
			var tag = si ? si.value.trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') : '';
			if (!tag || !fu) { toast('Type where this link will go first, e.g. truck-door.', 'bad'); if (si) si.focus(); return; }
			var u = fu.url + (fu.url.indexOf('?') > -1 ? '&' : '?') + 'src=' + encodeURIComponent(tag);
			var out = document.querySelector('[data-src-out]');
			if (out) out.innerHTML = esc(u) + ' <button type="button" class="axh-linkbtn" data-qr-png="' + esc(u) + '" data-name="' + esc(fu.slug + '-' + tag) + '">QR for this link</button>';
			(navigator.clipboard ? navigator.clipboard.writeText(u) : Promise.reject()).then(function () { toast('Copied the tracked link.', 'ok'); }, function () { toast('Link ready below. Copy it from there.', 'ok'); });
			return;
		}
		if ((b = hit('[data-merge]'))) {
			e.stopPropagation();
			var ta = document.querySelector('[data-fx-text="ar_body"]');
			if (ta) {
				var s0 = ta.selectionStart || ta.value.length, s1 = ta.selectionEnd || s0, tok = b.getAttribute('data-merge');
				ta.value = ta.value.slice(0, s0) + tok + ta.value.slice(s1);
				ta.focus(); ta.selectionStart = ta.selectionEnd = s0 + tok.length;
				p1Dirty(true);
			}
			return;
		}
		if (hit('[data-cq-add]')) { e.stopPropagation(); p1ReadCustom(); S.formCustom.push({ label: '', type: 'text', required: false, options: '' }); p1PaintCustom(); p1Dirty(true); var li = document.querySelectorAll('[data-cq] input[type="text"]'); if (li.length) li[li.length - 1].focus(); return; }
		if ((b = hit('[data-cq-del]'))) { e.stopPropagation(); p1ReadCustom(); S.formCustom.splice(+b.getAttribute('data-cq-del'), 1); p1PaintCustom(); p1Dirty(true); return; }
		if ((b = hit('[data-cq-up]'))) { e.stopPropagation(); p1ReadCustom(); var k = +b.getAttribute('data-cq-up'); if (k > 0) { var tmp = S.formCustom[k - 1]; S.formCustom[k - 1] = S.formCustom[k]; S.formCustom[k] = tmp; } p1PaintCustom(); p1Dirty(true); return; }
		if ((b = hit('[data-save-form]'))) {
			e.stopPropagation();
			var fid = b.getAttribute('data-save-form');
			p1SaveForm(fid, readForm2()).then(function () { p1Dirty(false); route(); }).catch(function (er) { toast(er.message, 'bad'); });
			return;
		}
		if (hit('[data-form-discard]')) { e.stopPropagation(); p1Dirty(false); route(); return; }
		if (hit('[data-del-form]')) S.formDirty = false;
		if (hit('[data-form-editor] [data-fld], [data-form-editor] [data-accent]')) { setTimeout(function () { p1Dirty(true); }, 0); }
		if ((b = hit('[data-sub-open]')) && !hit('a')) {
			var sub = (S.p1.subs || []).filter(function (x) { return String(x.id) === b.getAttribute('data-sub-open'); })[0];
			if (sub) {
				modal(esc(sub.name || 'Submission'), '<p class="axh-hint" style="margin-top:0">' + esc(sub.form_name) + ' · ' + when(sub.created_at) + ' ' + p1Clock(sub.created_at) + (sub.source ? ' · ' + esc(sub.source) : '') + '</p>' +
					'<dl class="axh-kv">' + sub.answers.map(function (a) { return '<dt>' + esc(a.label) + '</dt><dd style="white-space:pre-wrap">' + esc(a.value) + '</dd>'; }).join('') + '</dl>',
					sub.lead_id ? '<a class="axh-btn axh-btn--primary" href="#/lead/' + sub.lead_id + '" data-close>Open the lead</a>' : '');
			}
			return;
		}
		if ((b = hit('[data-sub-page]'))) { S.p1.subPage = Math.max(1, +b.getAttribute('data-sub-page')); route(); return; }
		if ((b = hit('[data-sub-csv]'))) {
			var fq = b.getAttribute('data-sub-csv');
			api('/p1/submissions?csv=1' + (fq ? '&form_id=' + encodeURIComponent(fq) : '') + (S.p1.subQ ? '&q=' + encodeURIComponent(S.p1.subQ) : ''))
				.then(function (r) { p1Download('submissions-' + new Date().toISOString().slice(0, 10) + '.csv', '﻿' + r.csv, 'text/csv'); toast(r.count + ' exported.', 'ok'); })
				.catch(function (er) { toast(er.message, 'bad'); });
			return;
		}
		if ((b = hit('[data-act-f]'))) {
			var g = b.getAttribute('data-act-f'), ul = document.querySelector('.axh-tl');
			document.querySelectorAll('[data-act-f]').forEach(function (x) { x.classList.toggle('is-on', x === b); });
			if (ul) ul.setAttribute('data-show', g);
			return;
		}
	}, true);

	document.addEventListener('input', function (e) {
		var t = e.target;
		if (!t || !t.closest) return;
		if (t.closest('[data-form-editor]')) p1Dirty(true);
		if (t.hasAttribute('data-sub-q')) {
			clearTimeout(S.p1.subT);
			S.p1.subT = setTimeout(function () { S.p1.subQ = t.value.trim(); S.p1.subPage = 1; route().then(function () { var q = document.querySelector('[data-sub-q]'); if (q) { q.focus(); q.selectionStart = q.selectionEnd = q.value.length; } }); }, 300);
		}
		if (S.disc && t.closest('.axh-disc')) p1DiscDirty();
	});
	document.addEventListener('change', function (e) {
		var t = e.target;
		if (!t || !t.closest) return;
		if (t.closest('[data-form-editor]')) {
			p1Dirty(true);
			if (t.getAttribute('data-cq-f') === 'type') { p1ReadCustom(); p1PaintCustom(); }
			if (t.getAttribute('data-fx') === 'autoreply') { var bx = document.querySelector('[data-ar-box]'); if (bx) bx.hidden = !t.checked; }
		}
		if (t.hasAttribute('data-sub-form')) { location.hash = t.value ? '#/submissions/' + t.value : '#/submissions'; }
		if (S.disc && t.closest('.axh-disc')) p1DiscDirty();
	});

	/* Leaving a form with unsaved edits: offer to save them. */
	window.addEventListener('hashchange', function (e) {
		if (!S.formDirty) return;
		var from = String(e.oldURL || '').split('#')[1] || '';
		var m = from.match(/^\/form\/(\d+)/);
		if (!m || ('#' + from) === location.hash) return;
		var body = S.p1.formPending || null;
		S.formDirty = false;
		if (!body) return;
		ask({ title: 'Save your form changes?', body: 'You left the form editor with changes that are not saved yet.', ok: 'Save them', cancel: 'Discard' })
			.then(function () { p1SaveForm(m[1], body).catch(function (er) { toast(er.message, 'bad'); }); });
	}, true);
	/* Keep a copy of the unsaved edits so the question above can save them. */
	document.addEventListener('input', function (e) { if (e.target && e.target.closest && e.target.closest('[data-form-editor]')) { try { S.p1.formPending = readForm2(); } catch (er) {} } });
	document.addEventListener('change', function (e) { if (e.target && e.target.closest && e.target.closest('[data-form-editor]')) { try { S.p1.formPending = readForm2(); } catch (er) {} } });
	document.addEventListener('click', function (e) { if (e.target && e.target.closest && e.target.closest('[data-form-editor] [data-fld], [data-form-editor] [data-accent], [data-cq-add], [data-cq-del], [data-cq-up]')) { setTimeout(function () { try { S.p1.formPending = readForm2(); } catch (er) {} }, 0); } });
	window.addEventListener('beforeunload', function (e) {
		if (S.formDirty || (S.disc && S.disc.dirty)) { e.preventDefault(); e.returnValue = ''; }
	});

	/* ---------- discovery autosave */
	function p1DiscStatus(txt) {
		var s = document.querySelector('[data-disc-status]');
		if (!s) {
			var acts = document.querySelector('.axh-page-head .axh-actions');
			if (!acts || !document.querySelector('[data-disc-save]')) return;
			s = document.createElement('span');
			s.className = 'p1-autosave';
			s.setAttribute('data-disc-status', '');
			s.setAttribute('aria-live', 'polite');
			acts.insertBefore(s, acts.firstChild);
		}
		s.textContent = txt;
	}
	function p1DiscDirty() {
		if (!S.disc) return;
		S.disc.dirty = true;
		p1DiscStatus('Unsaved changes');
		clearTimeout(S.p1.discT);
		S.p1.discT = setTimeout(p1DiscSave, 2500);
	}
	function p1DiscSave() {
		if (!S.disc || !S.disc.dirty || !document.querySelector('.axh-disc')) return Promise.resolve();
		collectInputs();
		S.disc.dirty = false;
		p1DiscStatus('Saving…');
		var id = S.disc.leadId;
		return api('/leads/' + id + '/discovery', { method: 'POST', body: { payload: S.disc.payload, modules: S.disc.modules, submit: false, note: '' } })
			.then(function (r) {
				if (S.disc && S.disc.leadId === id && r && r.discovery) { S.disc.sources = r.discovery.sources || S.disc.sources; }
				p1DiscStatus('Saved ' + new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }));
			}, function () {
				if (S.disc && S.disc.leadId === id) S.disc.dirty = true;
				p1DiscStatus('Not saved — retrying');
				clearTimeout(S.p1.discT);
				S.p1.discT = setTimeout(p1DiscSave, 8000);
			});
	}
	/* Clicks that change answers (ratings, rows, modules) count as edits too. */
	document.addEventListener('click', function (e) {
		if (!S.disc || !e.target || !e.target.closest) return;
		if (e.target.closest('.axh-disc [data-rate], .axh-disc [data-stack-add], .axh-disc [data-stack-del], .axh-disc [data-mod], .axh-disc .axh-chip')) setTimeout(p1DiscDirty, 0);
		if (e.target.closest('[data-sec]:not([data-key])') && S.disc.dirty) setTimeout(p1DiscSave, 0);
	});
	window.addEventListener('hashchange', function (e) {
		if (S.disc && S.disc.dirty && /#\/discovery\//.test(String(e.oldURL || ''))) {
			// This runs before the next page paints, so the inputs are still here.
			if (document.querySelector('.axh-disc')) collectInputs();
			clearTimeout(S.p1.discT);
			S.disc.dirty = false;
			var id = S.disc.leadId;
			api('/leads/' + id + '/discovery', { method: 'POST', body: { payload: S.disc.payload, modules: S.disc.modules, submit: false, note: '' } })
				.then(function () { toast('Discovery answers saved.', 'ok'); }, function (er) { toast('Could not save the discovery answers: ' + er.message, 'bad'); });
		}
	}, true);

	/* ----------------------------------------------------------- boot */

	window.addEventListener('hashchange', route);

	api('/bootstrap').then(function (b) {
		S.boot = b;
		document.title = 'Hub · ' + b.user.name;
		startNotes();
		route();
	}).catch(function (e) {
		el('axh-view').innerHTML = '<div class="axh-card"><div class="axh-empty"><h4>Could not load the Hub</h4><p>' + esc(e.message) + '</p></div></div>';
	});
})();
