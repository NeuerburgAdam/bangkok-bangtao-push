<?php
/**
 * The client's own discovery form at /d/<token>/ (rebuilt in 1.22.0).
 *
 * A full page: no theme header, no site menu, nothing to wander off into. The
 * questionnaire arrives already trimmed to what the rep chose to ask, in the
 * client's own wording, with the internal sections and our meeting notes
 * removed — this template renders whatever it is given and never decides what
 * a client may see.
 *
 * One section at a time, tailored to what they said they need. Every answer is
 * saved on its own as soon as it is given (typing pauses, a field is left, a
 * choice is tapped), kept on the device until the server confirms it, retried
 * when the connection drops and sent before the page closes, so nothing is
 * lost and they can stop and come back any time.
 *
 * @var array $config Token, REST URL, client schema, saved answers, company, rep.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="robots" content="noindex, nofollow">
	<meta name="theme-color" content="#121418">
	<title>Discovery · <?php echo esc_html( $config['company'] ? $config['company'] : 'AdaptExec' ); ?></title>
	<?php
	// A private client link: the preview card stays generic (no company or
	// personal details), and the page stays out of search (noindex above).
	AXH_Portal::share_tags(
		array(
			'title'       => 'Your project questionnaire · AdaptExec',
			'description' => 'A few questions about your business and what you need, so we can write your proposal.',
			'url'         => home_url( '/d/' . rawurlencode( $config['token'] ) . '/' ),
		)
	);
	?>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
	<style><?php echo axh_client_ui_css(); // phpcs:ignore WordPress.Security.EscapeOutput -- static CSS. ?></style>
</head>
<body>
<?php if ( ! empty( $config['preview'] ) ) : ?>
<div class="ax-pv"><b>Preview.</b> This is what a prospect sees on their questionnaire link. Nothing you type here is saved.</div>
<?php endif; ?>
<header class="ax-bar"><div class="ax-bar__in">
	<span class="ax-mark"><?php echo axh_logo_svg(); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span>
	<span class="ax-for"><i></i><span>Project questionnaire for</span> <b><?php echo esc_html( $config['company'] ? $config['company'] : 'your business' ); ?></b></span>
	<span class="ax-save" id="ax-save" role="status" aria-live="polite"><s></s><em>All changes saved</em></span>
</div></header>

<main class="ax-wrap" id="ax-app"></main>

<?php if ( ! empty( $config['styles'] ) ) { echo axh_style_assets(); } // phpcs:ignore WordPress.Security.EscapeOutput ?>
<script>
(function () {
	'use strict';
	var CFG = <?php echo wp_json_encode( $config ); ?>;
	var A = CFG.payload && typeof CFG.payload === 'object' && !Array.isArray(CFG.payload) ? CFG.payload : {};
	var app = document.getElementById('ax-app');
	var KEY = 'axh-disc-' + CFG.token;
	var CHECK = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
	var OK = '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
	var ARR = '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
	var BACK = '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>';
	var X = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>';

	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	/* Schema text may carry &amp; from PHP; show it as written. */
	function txt(s) { return esc(String(s == null ? '' : s).replace(/&amp;/g, '&').replace(/&#039;|&#39;/g, "'")); }
	function first(s) { return String(s || '').split(/\s+/)[0]; }

	/* --------------------------------------------------- device backup */
	function readLocal() { try { return JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) { return {}; } }
	function writeLocal(o) { try { localStorage.setItem(KEY, JSON.stringify(o)); } catch (e) {} }
	var pending = {};          // "section.question" -> value not yet confirmed by the server
	var local = readLocal();
	if (local.pending && typeof local.pending === 'object') {
		// Answers typed on this device that never reached the server (closed
		// tab, lost signal): put them back and send them now.
		Object.keys(local.pending).forEach(function (id) {
			var p = id.split('.');
			if (p.length !== 2) return;
			if (!A[p[0]] || typeof A[p[0]] !== 'object') A[p[0]] = {};
			A[p[0]][p[1]] = local.pending[id];
			pending[id] = local.pending[id];
		});
	}
	function persist() { writeLocal({ pending: pending, at: Date.now(), sec: S.sec }); }

	/* ------------------------------------------------------- the model */
	function val(sec, key) { return (A[sec] && A[sec][key] !== undefined) ? A[sec][key] : ''; }
	function needs() { var v = A.goals && A.goals.need; return Array.isArray(v) ? v : []; }
	function applies(item) {
		if (!item.show_if || !item.show_if.length) return true;
		var n = needs();
		return !n.length || item.show_if.some(function (x) { return n.indexOf(x) > -1; });
	}
	function isKey(f) {
		var e = f.essential;
		if (Array.isArray(e)) { var n = needs(); return !n.length || e.some(function (x) { return n.indexOf(x) > -1; }); }
		return !!e;
	}
	function answered(v) {
		if (Array.isArray(v)) return v.some(function (x) { return x && typeof x === 'object' ? Object.keys(x).some(function (k) { return String(x[k] || '').trim(); }) : String(x == null ? '' : x).trim() !== ''; });
		return String(v == null ? '' : v).trim() !== '';
	}
	function styleOn() {
		if (!CFG.styles || !window.AXStylePicker) return false;
		var n = needs();
		return !n.length || (CFG.needs || []).some(function (x) { return n.indexOf(x) > -1; });
	}
	/* The sections that apply to what they need, each with only its relevant questions. */
	function sections() {
		var out = [];
		(CFG.schema || []).forEach(function (s) {
			if (!applies(s)) return;
			var f = s.fields.filter(applies);
			if (!f.length) return;
			var c = {}; for (var k in s) { if (Object.prototype.hasOwnProperty.call(s, k)) c[k] = s[k]; }
			c.fields = f; out.push(c);
		});
		if (styleOn()) out.push({ key: '_style', name: 'Pick your website style', blurb: 'A few quick questions, or browse every style. It’s a starting point for the design, not a commitment.', optional: true, style: true, fields: [] });
		return out;
	}
	function secCount(s) {
		var n = 0; s.fields.forEach(function (f) { if (answered(val(s.key, f.key))) n++; });
		if (s.style) n = CFG.style && CFG.style.shortlist && CFG.style.shortlist.length ? 1 : 0;
		return { n: n, t: s.style ? 1 : s.fields.length };
	}
	function counts() {
		var t = 0, f = 0, kt = 0, kf = 0, miss = [];
		sections().forEach(function (s, i) {
			if (s.optional) return;
			s.fields.forEach(function (q) {
				var on = answered(val(s.key, q.key));
				t++; if (on) f++;
				if (isKey(q)) { kt++; if (on) kf++; else miss.push({ i: i, label: q.label, key: q.key }); }
			});
		});
		return { t: t, f: f, kt: kt, kf: kf, pct: t ? Math.round(f / t * 100) : 0, miss: miss };
	}

	/* ---------------------------------------------------------- saving */
	var S = { view: 'welcome', sec: 0 };
	var timer = null, inflight = null, again = false, retryN = 0, dead = false;
	var saveEl = document.getElementById('ax-save');
	function status(kind, msg) {
		saveEl.className = 'ax-save' + (kind === 'busy' ? ' is-busy' : kind === 'off' ? ' is-off' : '');
		saveEl.querySelector('em').textContent = msg;
	}
	function setVal(sec, key, v, delay) {
		if (!A[sec] || typeof A[sec] !== 'object') A[sec] = {};
		A[sec][key] = v;
		pending[sec + '.' + key] = v;
		persist();
		if (!CFG.preview && !dead) status('busy', 'Saving…');
		clearTimeout(timer);
		timer = setTimeout(flush, delay == null ? 800 : delay);
		meters();
	}
	function markSaved(id, err) {
		var el = app.querySelector('[data-ok="' + id + '"]');
		if (!el) return;
		el.className = 'ax-q__ok on' + (err ? ' err' : '');
		el.innerHTML = err ? 'Not saved yet' : OK + ' Saved';
		clearTimeout(el._t);
		if (!err) el._t = setTimeout(function () { el.className = 'ax-q__ok'; }, 1800);
	}
	function post(body, keep) {
		return fetch(CFG.rest, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), keepalive: !!keep, credentials: 'omit' });
	}
	function flush() {
		clearTimeout(timer);
		if (CFG.preview) { status('ok', 'Preview: nothing is saved'); return Promise.resolve(); }
		if (dead) return Promise.resolve();
		if (inflight) { again = true; return inflight; }
		var ids = Object.keys(pending);
		if (!ids.length) { status('ok', 'All changes saved'); return Promise.resolve(); }
		var batch = {}; ids.forEach(function (id) { batch[id] = pending[id]; });
		status('busy', 'Saving…');
		inflight = post({ changes: batch }).then(function (r) {
			if (r.status === 404) { dead = true; throw new Error('gone'); }
			if (!r.ok) throw new Error('save');
			return r.json();
		}).then(function () {
			ids.forEach(function (id) {
				if (JSON.stringify(pending[id]) === JSON.stringify(batch[id])) delete pending[id];
				markSaved(id);
			});
			persist(); retryN = 0;
			status('ok', 'All changes saved');
		}).catch(function () {
			if (dead) { status('off', 'This link is no longer active'); return; }
			ids.forEach(function (id) { markSaved(id, true); });
			retryN++;
			status('off', navigator.onLine === false ? 'Offline · saved on this device' : 'Saved on this device · retrying');
			timer = setTimeout(flush, [2000, 5000, 10000, 20000, 30000][Math.min(retryN - 1, 4)]);
		}).then(function () {
			inflight = null;
			if (again) { again = false; if (Object.keys(pending).length) return flush(); }
		});
		return inflight;
	}
	/* Leaving, switching apps or locking the phone: send what is waiting. */
	function sendNow() {
		if (CFG.preview || dead || !Object.keys(pending).length) return;
		try { post({ changes: pending }, true); } catch (e) {}
	}
	window.addEventListener('pagehide', sendNow);
	document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') sendNow(); });
	window.addEventListener('online', function () { if (Object.keys(pending).length) flush(); });

	/* ------------------------------------------------------- rendering */
	function ring(pct, size) {
		var r = (size - 8) / 2, c = 2 * Math.PI * r;
		return '<svg width="' + size + '" height="' + size + '" viewBox="0 0 ' + size + ' ' + size + '" aria-hidden="true">' +
			'<circle cx="' + size / 2 + '" cy="' + size / 2 + '" r="' + r + '" fill="none" stroke="#EFECE6" stroke-width="7"/>' +
			'<circle data-ring cx="' + size / 2 + '" cy="' + size / 2 + '" r="' + r + '" fill="none" stroke="#EF5D0F" stroke-width="7" stroke-linecap="round" stroke-dasharray="' + c.toFixed(1) + '" stroke-dashoffset="' + (c * (1 - pct / 100)).toFixed(1) + '" transform="rotate(-90 ' + size / 2 + ' ' + size / 2 + ')" style="transition:stroke-dashoffset .4s"/></svg>';
	}
	function option(id, o, on, multi, label) {
		return '<label class="ax-opt' + (multi ? '' : ' is-radio') + (on ? ' on' : '') + '"><input type="' + (multi ? 'checkbox' : 'radio') + '" name="' + esc(id) + '" value="' + esc(o) + '"' + (on ? ' checked' : '') + ' data-pick="' + esc(id) + '" data-multi="' + (multi ? 1 : 0) + '"><i>' + CHECK + '</i><span>' + txt(label || o) + '</span></label>';
	}
	function fieldHtml(sec, f) {
		var id = sec + '.' + f.key, v = val(sec, f.key), input = '';
		var labels = f.labels || {};
		if (f.type === 'checklist' || f.type === 'multiselect') {
			var arr = Array.isArray(v) ? v : (v ? [v] : []);
			input = '<div class="ax-opts">' + (f.options || []).map(function (o) { return option(id, o, arr.indexOf(o) > -1, true, labels[o]); }).join('') + '</div>';
		} else if (f.type === 'select' || f.type === 'scale') {
			var opts = f.options || [];
			if (opts.length <= 8) input = '<div class="ax-opts" role="radiogroup">' + opts.map(function (o) { return option(id, o, v === o, false, labels[o]); }).join('') + '</div>';
			else input = '<select class="ax-sel" data-in="' + esc(id) + '"><option value="">Choose one…</option>' + opts.map(function (o) { return '<option value="' + esc(o) + '"' + (v === o ? ' selected' : '') + '>' + txt(labels[o] || o) + '</option>'; }).join('') + '</select>';
		} else if (f.type === 'rating') {
			input = '<div class="ax-rate" role="radiogroup" aria-label="' + esc(f.label) + '">' + [1, 2, 3, 4, 5].map(function (n) {
				return '<button type="button" role="radio" aria-checked="' + (String(v) === String(n)) + '" class="' + (String(v) === String(n) ? 'on' : '') + '" data-rate="' + esc(id) + '" data-v="' + n + '">' + n + '</button>';
			}).join('') + '</div>';
		} else if (f.type === 'stack') {
			var rows = Array.isArray(v) && v.length ? v : [{ name: '', use: '', cost: '' }];
			input = '<div data-stack="' + esc(id) + '">' + rows.map(function (r, n) {
				return '<div class="ax-srow" data-row="' + n + '">' +
					'<input class="ax-in" type="text" placeholder="Software or app" aria-label="Software or app" data-sf="name" value="' + esc(r.name || '') + '">' +
					'<input class="ax-in" type="text" placeholder="What you use it for" aria-label="What you use it for" data-sf="use" value="' + esc(r.use || '') + '">' +
					'<input class="ax-in" type="text" inputmode="decimal" placeholder="$ / month" aria-label="Cost per month" data-sf="cost" value="' + esc(r.cost || '') + '">' +
					'<button type="button" data-row-del="' + esc(id) + '" data-n="' + n + '" aria-label="Remove this row">' + X + '</button></div>';
			}).join('') + '</div><button type="button" class="ax-add" data-stack-add="' + esc(id) + '">+ Add another</button>';
		} else if (f.type === 'textarea') {
			input = '<textarea class="ax-ta" data-in="' + esc(id) + '" placeholder="' + esc(f.placeholder || 'Type as much or as little as you like') + '" rows="4">' + esc(v) + '</textarea>';
		} else if (f.type === 'money') {
			input = '<div class="ax-money"><span>$</span><input class="ax-in" type="text" inputmode="decimal" data-in="' + esc(id) + '" value="' + esc(v) + '" placeholder="' + esc(f.placeholder || '') + '"></div>';
		} else if (f.type === 'number') {
			input = '<input class="ax-in" type="number" min="0" inputmode="numeric" data-in="' + esc(id) + '" value="' + esc(v) + '" placeholder="' + esc(f.placeholder || '') + '" style="max-width:220px">';
		} else if (f.type === 'date') {
			input = '<input class="ax-in" type="date" data-in="' + esc(id) + '" value="' + esc(v) + '" style="max-width:240px">';
		} else {
			var ph = f.placeholder || '';
			if (f.key === 'website_url' && CFG.website && !v) ph = CFG.website;
			input = '<input class="ax-in" type="text" data-in="' + esc(id) + '" value="' + esc(v) + '" placeholder="' + esc(ph) + '">' +
				(f.suggest ? '<div class="ax-sug">' + f.suggest.map(function (o) { return '<button type="button" data-sug="' + esc(id) + '" data-v="' + esc(o) + '">' + esc(o) + '</button>'; }).join('') + '</div>' : '');
		}
		return '<div class="ax-q" data-q="' + esc(id) + '"><div class="ax-q__l"><span>' + txt(f.label) + '</span>' + (isKey(f) ? '<em title="One of the questions that matters most for your proposal">Key</em>' : '') + '</div>' +
			(f.help ? '<div class="ax-q__h">' + txt(f.help) + '</div>' : '<div style="height:8px"></div>') + input +
			'<span class="ax-q__ok" data-ok="' + esc(id) + '" aria-hidden="true"></span></div>';
	}

	function rail(list, c) {
		return '<aside class="ax-rail"><div class="ax-card ax-rail__card">' +
			'<div class="ax-ring">' + ring(c.pct, 64) + '<div><b data-m="pct">' + c.pct + '%</b><span data-m="txt">' + c.f + ' of ' + c.t + ' answered' + (c.kt ? '<br>' + c.kf + ' of ' + c.kt + ' key questions' : '') + '</span></div></div>' +
			'<ul class="ax-nav">' + list.map(function (s, i) {
				var sc = secCount(s), on = S.view === 'sec' && i === S.sec, done = sc.n && sc.n >= sc.t, part = sc.n && !done;
				return '<li><button type="button" data-go="' + i + '" class="' + (on ? 'is-on ' : '') + (done ? 'is-done' : part ? 'is-part' : '') + '"' + (part ? ' style="--p:' + Math.round(sc.n / sc.t * 100) + '%"' : '') + '><i>' + (done ? CHECK : (i + 1)) + '</i><span>' + txt(s.name) + '</span>' +
					(s.optional ? '<small>Optional</small>' : '<small>' + sc.n + '/' + sc.t + '</small>') + '</button></li>';
			}).join('') +
			'<li><button type="button" data-review class="' + (S.view === 'review' ? 'is-on' : '') + '"><i>' + OK.replace('currentColor', '#68717D') + '</i><span>Review &amp; send</span></button></li></ul>' +
			'<div class="ax-rail__foot">Saves as you go. Close the page any time and come back to this link to pick up where you left off.</div>' +
			'</div></aside>';
	}
	function mob(list, c) {
		var s = list[S.sec], label = S.view === 'review' ? 'Review & send' : (s ? 'Step ' + (S.sec + 1) + ' of ' + list.length + ' · ' + txt(s.name).replace(/&amp;/g, '&') : '');
		return '<div class="ax-mob" data-review role="button" tabindex="0" aria-label="See all sections" style="cursor:pointer"><div class="ax-mob__bar"><div class="ax-mob__t">' + esc(label) + '</div><div class="ax-mob__p"><i data-m="bar" style="width:' + c.pct + '%"></i></div></div><b data-m="pct2" style="font-size:13px">' + c.pct + '%</b></div>';
	}

	function render() {
		var list = sections(), c = counts();
		if (S.view === 'welcome') return renderWelcome(c);
		if (S.view === 'done') return renderDone();
		if (S.sec >= list.length) S.sec = Math.max(0, list.length - 1);
		var main = S.view === 'review' ? reviewHtml(list, c) : sectionHtml(list, S.sec);
		app.innerHTML = mob(list, c) + '<div class="ax-layout">' + rail(list, c) + '<section class="ax-card ax-sec ax-fade" id="ax-main">' + main + '</section></div>';
		app.querySelectorAll('textarea.ax-ta').forEach(grow);
		if (S.view === 'sec' && list[S.sec] && list[S.sec].style) mountStyle();
	}
	function sectionHtml(list, i) {
		var s = list[i];
		if (!s) return '';
		var back = i > 0 ? '<button type="button" class="ax-btn ax-btn--ghost" data-go="' + (i - 1) + '">' + BACK + ' Back</button>' : '<button type="button" class="ax-btn ax-btn--ghost" data-welcome>' + BACK + ' Intro</button>';
		var next = i < list.length - 1
			? '<button type="button" class="ax-btn ax-btn--dark" data-go="' + (i + 1) + '">Next: ' + txt(list[i + 1].name) + ' ' + ARR + '</button>'
			: '<button type="button" class="ax-btn ax-btn--p" data-review>Review &amp; send ' + ARR + '</button>';
		var intro = S.back ? '<div class="ax-note">' + OK + '<span><b>Welcome back' + (CFG.contact ? ', ' + esc(CFG.contact) : '') + '.</b> Everything you entered is saved. Pick up right here.</span></div>' : '';
		S.back = false;
		var body = s.style
			? '<div id="axsp-disc" style="margin-top:18px"></div>'
			: s.fields.map(function (f) { return fieldHtml(s.key, f); }).join('');
		return intro + '<div class="ax-sec__h"><span class="ax-sec__n">' + String(i + 1).padStart(2, '0') + '</span><div><div class="ax-k" data-m="step">' + (s.optional ? 'Optional' : 'Step ' + (i + 1) + ' of ' + list.length) + '</div><h1 class="ax-h">' + txt(s.name) + '</h1></div></div>' +
			(s.blurb ? '<p class="ax-sec__b">' + txt(s.blurb) + '</p>' : '') + body +
			'<div class="ax-foot">' + back + '<span class="sp"></span>' + next + '</div>';
	}
	function reviewHtml(list, c) {
		var rows = list.map(function (s, i) {
			var sc = secCount(s);
			return '<button type="button" class="ax-opt" data-go="' + i + '" style="justify-content:space-between;width:100%"><span><b>' + txt(s.name) + '</b>' + (s.optional ? ' <span style="color:var(--soft)">· optional</span>' : '') + '</span><span style="color:' + (sc.n >= sc.t ? 'var(--ok)' : 'var(--mute)') + ';font-weight:600;white-space:nowrap">' + (sc.n >= sc.t ? 'Complete' : sc.n + ' of ' + sc.t) + '</span></button>';
		}).join('');
		var miss = c.miss.length
			? '<div class="ax-note" style="display:block"><b>A few key questions are still blank.</b> They help us price your project accurately, but you can send it anyway and ' + esc(first(CFG.rep)) + ' will follow up:<ul style="margin:8px 0 0 18px">' +
				c.miss.slice(0, 8).map(function (m) { return '<li><a href="#" data-go="' + m.i + '" data-focus="' + esc(m.key) + '">' + txt(m.label) + '</a></li>'; }).join('') + '</ul></div>'
			: '<div class="ax-note" style="background:var(--ok-wash);border-color:#BFE6CF;color:var(--ok)">' + OK + '<span><b>Every key question is answered.</b> That’s everything we need to write an accurate proposal.</span></div>';
		var sent = CFG.status === 'client_done' || CFG.status === 'submitted' || CFG.status === 'reviewed';
		return '<div class="ax-sec__h"><span class="ax-sec__n">' + OK.replace('13', '22').replace('13', '22') + '</span><div><div class="ax-k">Last step</div><h1 class="ax-h">Review &amp; send</h1></div></div>' +
			'<p class="ax-sec__b">You’ve answered <b>' + c.f + ' of ' + c.t + '</b> questions. Jump back into any section to add more, or send it to ' + esc(CFG.rep) + ' now. You can still change answers afterwards.</p>' +
			'<div style="margin:20px 0 22px">' + miss + '</div><div style="display:grid;gap:8px">' + rows + '</div>' +
			'<div class="ax-foot"><button type="button" class="ax-btn ax-btn--ghost" data-go="' + (list.length - 1) + '">' + BACK + ' Back</button><span class="sp"></span>' +
			'<button type="button" class="ax-btn ax-btn--p" data-send>' + (sent ? 'Send my updates to ' : 'Send to ') + esc(first(CFG.rep)) + ' ' + ARR + '</button></div>';
	}
	function renderWelcome(c) {
		var started = c.f > 0;
		var who = CFG.contact ? 'Hi ' + esc(CFG.contact) + '. ' : '';
		app.innerHTML = '<div class="ax-hero ax-fade"><div class="ax-k">Project questionnaire · ' + esc(CFG.company || 'Your business') + '</div>' +
			'<h1 class="ax-h">Let’s map out what you need</h1>' +
			'<p>' + who + esc(CFG.rep) + ' sent this so AdaptExec can write your proposal without a long sales call. It takes about 10 to 15 minutes. Nothing is required: skip anything that doesn’t apply. Every answer saves the moment you give it, so you can stop and come back to this link any time.</p>' +
			'<div class="ax-steps"><div><b>01</b>Tell us what you need. We only ask about what you pick.</div><div><b>02</b>AdaptExec writes your proposal from your answers.</div><div><b>03</b>' + esc(first(CFG.rep)) + ' walks you through it. No pressure, no long contracts.</div></div>' +
			'<button type="button" class="ax-btn ax-btn--p" data-start style="min-height:52px;padding:0 26px;font-size:15.5px">' + (started ? 'Pick up where you left off' : 'Let’s start') + ' ' + ARR + '</button>' +
			(started ? '<span style="margin-left:14px;color:rgba(255,255,255,.6);font-size:14px">' + c.pct + '% done</span>' : '') +
			'<div class="ax-meta"><span>' + OK + ' Saves automatically</span><span>' + OK + ' Private to you and AdaptExec</span><span>' + OK + ' Works on your phone</span></div></div>';
	}
	function renderDone() {
		var quoted = CFG.status === 'submitted' || CFG.status === 'reviewed';
		app.innerHTML = '<div class="ax-card ax-done ax-fade"><div class="ax-k">' + (quoted ? 'Proposal in progress' : 'Sent to ' + esc(CFG.rep)) + '</div>' +
			'<h1 class="ax-h">Thank you' + (CFG.contact ? ', ' + esc(CFG.contact) : '') + '. We have what we need.</h1>' +
			'<p>' + (quoted ? 'AdaptExec is writing your proposal from your answers. ' + esc(CFG.rep) + ' will send it over and walk you through it.' : esc(CFG.rep) + ' has your answers and may follow up with a quick question or two. Then AdaptExec writes your proposal.') + '</p>' +
			'<div class="ax-tl"><div class="ok"><b>Your answers</b>Done</div><div class="' + (quoted ? 'on' : 'on') + '"><b>Your proposal</b>Written by AdaptExec</div><div><b>Walkthrough</b>With ' + esc(first(CFG.rep)) + '</div><div><b>Kickoff</b>We start building</div></div>' +
			'<button type="button" class="ax-btn" data-edit>Review or change my answers</button></div>';
	}

	/* Style picker (optional step). */
	function mountStyle() {
		var box = document.getElementById('axsp-disc');
		if (!box || !window.AXStylePicker) return;
		window.AXStylePicker.mount(box, {
			value: CFG.style || {}, notes: true, saveLabel: 'Save my style picks',
			onSave: function (v) {
				if (CFG.preview) return Promise.reject(new Error('Preview: style picks are not saved.'));
				if (!v.shortlist.length && !Object.keys(v.answers).length) return Promise.reject(new Error('Shortlist at least one style first.'));
				return fetch(CFG.styleRest, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ picks: v }) })
					.then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || 'Could not save.'); return j; }); })
					.then(function () { CFG.style = v; meters(); return 'Saved. ' + CFG.rep + ' can see your picks.'; });
			}
		});
	}

	/* Counters update in place, so typing never re-renders the page under the cursor. */
	function meters() {
		var c = counts(), list = sections();
		var pe = app.querySelector('[data-m="pct"]'); if (pe) pe.textContent = c.pct + '%';
		var p2 = app.querySelector('[data-m="pct2"]'); if (p2) p2.textContent = c.pct + '%';
		var te = app.querySelector('[data-m="txt"]'); if (te) te.innerHTML = c.f + ' of ' + c.t + ' answered' + (c.kt ? '<br>' + c.kf + ' of ' + c.kt + ' key questions' : '');
		var bar = app.querySelector('[data-m="bar"]'); if (bar) bar.style.width = c.pct + '%';
		var stp = app.querySelector('[data-m="step"]'), cur = list[S.sec];
		if (stp && cur && !cur.optional) stp.textContent = 'Step ' + (S.sec + 1) + ' of ' + list.length;
		var rg = app.querySelector('[data-ring]');
		if (rg) { var len = parseFloat(rg.getAttribute('stroke-dasharray')); rg.setAttribute('stroke-dashoffset', (len * (1 - c.pct / 100)).toFixed(1)); }
		var nav = app.querySelector('.ax-nav');
		if (nav) {
			var html = rail(list, c);
			var tmp = document.createElement('div'); tmp.innerHTML = html;
			var fresh = tmp.querySelector('.ax-nav');
			if (fresh) nav.innerHTML = fresh.innerHTML;
		}
	}
	function grow(t) { t.style.height = 'auto'; t.style.height = Math.max(110, Math.min(t.scrollHeight + 2, 520)) + 'px'; }

	function go(i, focusKey) {
		var list = sections();
		flush();
		S.view = 'sec'; S.sec = Math.max(0, Math.min(i, list.length - 1));
		persist(); render();
		var top = document.getElementById('ax-main');
		if (top) window.scrollTo({ top: Math.max(0, top.getBoundingClientRect().top + window.pageYOffset - 140), behavior: 'smooth' });
		if (focusKey) {
			var el = app.querySelector('[data-q$=".' + focusKey + '"] input, [data-q$=".' + focusKey + '"] textarea, [data-q$=".' + focusKey + '"] select');
			if (el) setTimeout(function () { el.focus({ preventScroll: false }); }, 250);
		}
	}

	/* ---------------------------------------------------------- events */
	app.addEventListener('click', function (e) {
		var t = e.target.closest ? e.target.closest('button, a, .ax-mob') : null;
		if (!t) return;
		if (t.hasAttribute('data-start')) {
			var list = sections(), at = typeof local.sec === 'number' && local.sec < list.length ? local.sec : 0;
			if (!at) { for (var k = 0; k < list.length; k++) { var sc = secCount(list[k]); if (!list[k].optional && sc.n < sc.t) { at = k; break; } } }
			go(at); return;
		}
		if (t.hasAttribute('data-welcome')) { flush(); S.view = 'welcome'; render(); window.scrollTo(0, 0); return; }
		if (t.hasAttribute('data-go')) { e.preventDefault(); go(+t.getAttribute('data-go'), t.getAttribute('data-focus')); return; }
		if (t.hasAttribute('data-review')) { flush(); S.view = 'review'; render(); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
		if (t.hasAttribute('data-edit')) { S.view = 'review'; render(); window.scrollTo(0, 0); return; }
		if (t.hasAttribute('data-rate')) {
			var id = t.getAttribute('data-rate').split('.'), cur = String(val(id[0], id[1])), nv = cur === t.getAttribute('data-v') ? '' : t.getAttribute('data-v');
			setVal(id[0], id[1], nv, 150);
			[].forEach.call(t.parentNode.children, function (b) { var on = nv && b === t; b.classList.toggle('on', !!on); b.setAttribute('aria-checked', String(!!on)); });
			return;
		}
		if (t.hasAttribute('data-sug')) {
			var sid = t.getAttribute('data-sug').split('.'), inp = app.querySelector('[data-in="' + t.getAttribute('data-sug') + '"]');
			if (inp) inp.value = t.getAttribute('data-v');
			setVal(sid[0], sid[1], t.getAttribute('data-v'), 150);
			return;
		}
		if (t.hasAttribute('data-stack-add') || t.hasAttribute('data-row-del')) {
			var attr = t.getAttribute('data-stack-add') || t.getAttribute('data-row-del'), p = attr.split('.');
			var rows = readStack(attr);
			if (t.hasAttribute('data-stack-add')) rows.push({ name: '', use: '', cost: '' });
			else { rows.splice(+t.getAttribute('data-n'), 1); if (!rows.length) rows.push({ name: '', use: '', cost: '' }); }
			setVal(p[0], p[1], rows, 300);
			var y = window.pageYOffset; render(); window.scrollTo(0, y);
			if (t.hasAttribute('data-stack-add')) { var rs = app.querySelectorAll('[data-stack="' + attr + '"] [data-row]'); var last = rs[rs.length - 1]; if (last) last.querySelector('input').focus(); }
			return;
		}
		if (t.hasAttribute('data-send')) {
			if (CFG.preview) { status('ok', 'Preview: this would go to ' + CFG.rep); return; }
			t.disabled = true; t.textContent = 'Sending…';
			flush().then(function () {
				return post({ changes: {}, submit: true }).then(function (r) { if (!r.ok) throw new Error('send'); return r.json(); });
			}).then(function (d) {
				if (d && d.status) CFG.status = d.status;
				if (CFG.status === 'draft') CFG.status = 'client_done';
				try { localStorage.removeItem(KEY); } catch (er) {}
				S.view = 'done'; render(); window.scrollTo(0, 0);
			}).catch(function () {
				t.disabled = false; t.textContent = 'Try sending again';
				status('off', 'Not sent · check your connection');
			});
		}
	});

	function readStack(id) {
		var box = app.querySelector('[data-stack="' + id + '"]'), rows = [];
		if (!box) { var v = val(id.split('.')[0], id.split('.')[1]); return Array.isArray(v) ? v.slice() : []; }
		[].forEach.call(box.querySelectorAll('[data-row]'), function (r) {
			var o = {}; [].forEach.call(r.querySelectorAll('[data-sf]'), function (i) { o[i.getAttribute('data-sf')] = i.value; }); rows.push(o);
		});
		return rows;
	}

	app.addEventListener('input', function (e) {
		var t = e.target;
		if (t.tagName === 'TEXTAREA') grow(t);
		var box = t.closest && t.closest('[data-stack]');
		if (box) { var p = box.getAttribute('data-stack').split('.'); setVal(p[0], p[1], readStack(box.getAttribute('data-stack'))); return; }
		var k = t.getAttribute && t.getAttribute('data-in');
		if (k && t.tagName !== 'SELECT') { var q = k.split('.'); setVal(q[0], q[1], t.value); }
	});
	app.addEventListener('change', function (e) {
		var t = e.target, pick = t.getAttribute && t.getAttribute('data-pick');
		if (pick) {
			var p = pick.split('.'), multi = t.getAttribute('data-multi') === '1', nv;
			if (multi) {
				var cur = val(p[0], p[1]); cur = Array.isArray(cur) ? cur.slice() : [];
				var i = cur.indexOf(t.value);
				if (t.checked && i < 0) cur.push(t.value);
				if (!t.checked && i > -1) cur.splice(i, 1);
				nv = cur;
			} else nv = t.value;
			var box = t.closest('.ax-opts');
			if (box) [].forEach.call(box.querySelectorAll('.ax-opt'), function (l) { l.classList.toggle('on', l.querySelector('input').checked); });
			setVal(p[0], p[1], nv, 250);
			return;
		}
		var k = t.getAttribute && t.getAttribute('data-in');
		if (k && t.tagName === 'SELECT') { var q = k.split('.'); setVal(q[0], q[1], t.value, 150); }
	});
	/* Leaving a field saves it straight away. */
	app.addEventListener('focusout', function (e) {
		if (e.target && (e.target.hasAttribute('data-in') || e.target.hasAttribute('data-sf')) && Object.keys(pending).length) flush();
	});
	/* A radio answered by mistake can be cleared by tapping it again. */
	app.addEventListener('click', function (e) {
		var lab = e.target.closest && e.target.closest('.ax-opt.is-radio');
		if (!lab || e.target.tagName === 'INPUT') return;
		var inp = lab.querySelector('input');
		if (inp && inp.checked) {
			e.preventDefault();
			inp.checked = false;
			lab.classList.remove('on');
			var p = inp.getAttribute('data-pick').split('.');
			setVal(p[0], p[1], '', 150);
		}
	}, true);

	/* ------------------------------------------------------------ start */
	var c0 = counts();
	if (Object.keys(pending).length) setTimeout(flush, 400);
	if (CFG.preview) status('ok', 'Preview: nothing is saved');
	if (CFG.status === 'client_done' || CFG.status === 'submitted' || CFG.status === 'reviewed') { S.view = 'done'; }
	else if (c0.f > 0 && typeof local.sec === 'number') { S.view = 'sec'; S.sec = local.sec; S.back = true; }
	render();
})();
</script>
</body>
</html>
