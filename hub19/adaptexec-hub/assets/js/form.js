/* =========================================================================
   Public lead-capture form: step navigation, validation, submission.
   ========================================================================= */
(function () {
	'use strict';

	var form = document.getElementById('axf-form');
	if (!form || !window.AXF) return;

	var steps = Array.prototype.slice.call(form.querySelectorAll('.axf-step'));
	var segs = Array.prototype.slice.call(document.querySelectorAll('.axf-progress__seg'));
	var at = 0;
	var checks = {};

	/* Optional style step: a compact gallery of website styles. */
	var stylePicks = [];
	var styleBox = document.getElementById('axf-style');
	if (styleBox && AXF.styles && window.AXStylePicker) {
		window.AXStylePicker.mount(styleBox, {
			compact: true,
			max: 3,
			onChange: function (v) { stylePicks = v.shortlist; }
		});
	}

	/* ------------------------------------------------------------- steps */

	function show(n) {
		at = Math.max(0, Math.min(steps.length - 1, n));
		steps.forEach(function (s, i) { s.classList.toggle('is-on', i === at); });
		segs.forEach(function (s, i) { s.classList.toggle('is-on', i <= at); });

		var card = document.querySelector('.axf-card');
		if (card && window.scrollY > 60) card.scrollIntoView({ behavior: 'smooth', block: 'start' });

		var first = steps[at].querySelector('input, textarea, select');
		if (first && window.matchMedia('(min-width: 900px)').matches) first.focus();
	}

	function validate(step) {
		var ok = true;
		step.querySelectorAll('[data-required]').forEach(function (el) {
			var wrap = el.closest('.axf-field');
			var val = (el.value || '').trim();
			var bad = !val;

			if (!bad && el.type === 'email') {
				bad = !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(val);
			}
			wrap.classList.toggle('has-err', bad);
			var err = wrap.querySelector('.axf-err');
			if (err) {
				err.hidden = !bad;
				if (bad) err.textContent = el.type === 'email' && val ? 'That email does not look right.' : 'Please fill this in.';
			}
			if (bad && ok) el.focus();
			if (bad) ok = false;
		});
		return ok;
	}

	form.addEventListener('click', function (e) {
		var next = e.target.closest('[data-next]');
		var back = e.target.closest('[data-back]');
		var chip = e.target.closest('.axf-chip');

		if (chip) {
			e.preventDefault();
			var group = chip.closest('[data-checks]');
			var key = group.getAttribute('data-checks');
			var val = chip.getAttribute('data-val');
			checks[key] = checks[key] || [];
			var i = checks[key].indexOf(val);
			if (i > -1) checks[key].splice(i, 1); else checks[key].push(val);
			chip.classList.toggle('is-on');
			return;
		}
		if (next) { if (validate(steps[at])) show(at + 1); return; }
		if (back) { show(at - 1); }
	});

	// Enter advances rather than submitting early.
	form.addEventListener('keydown', function (e) {
		if (e.key !== 'Enter') return;
		if (e.target.closest && e.target.closest('.axsp')) return;   // style cards use Enter to pick
		if (e.target.tagName === 'TEXTAREA') return;
		e.preventDefault();
		if (at < steps.length - 1) { if (validate(steps[at])) show(at + 1); }
		else form.requestSubmit ? form.requestSubmit() : submit();
	});

	// Clear the error as soon as they start fixing it.
	form.addEventListener('input', function (e) {
		var wrap = e.target.closest ? e.target.closest('.axf-field') : null;
		if (wrap && wrap.classList.contains('has-err')) {
			wrap.classList.remove('has-err');
			var err = wrap.querySelector('.axf-err');
			if (err) err.hidden = true;
		}
	});

	/* -------------------------------------------------------- submission */

	function collect() {
		var out = {};
		form.querySelectorAll('input[name], textarea[name], select[name]').forEach(function (el) {
			if (el.name === 'website_url') return;               // honeypot handled separately
			if (el.type === 'checkbox') { if (el.checked) out[el.name] = el.value || 'yes'; return; }
			var v = (el.value || '').trim();
			if (v) out[el.name] = v;
		});
		Object.keys(checks).forEach(function (k) {
			if (checks[k].length) out[k] = checks[k];
		});
		if (stylePicks.length) out.style_picks = stylePicks.slice();
		return out;
	}

	function submit() {
		if (!validate(steps[at])) return;

		var btn = form.querySelector('[data-submit]');
		if (btn) { btn.disabled = true; btn.textContent = 'Sending…'; }

		var hp = form.querySelector('[name="website_url"]');
		var params = new URLSearchParams(location.search);

		fetch(AXF.root + '/public/submit', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				slug: AXF.slug,
				nonce: AXF.nonce,
				t: AXF.t,
				website_url: hp ? hp.value : '',
				referrer: document.referrer || '',
				src: params.get('src') || params.get('utm_source') || '',
				answers: collect()
			})
		})
			.then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || 'Something went wrong.'); return j; }); })
			.then(function (res) {
				if (res.redirect) { location.href = res.redirect; return; }
				form.hidden = true;
				document.getElementById('axf-progress').hidden = true;
				var done = document.getElementById('axf-done');
				document.getElementById('axf-done-h').textContent = res.headline || 'Thank you.';
				document.getElementById('axf-done-b').textContent = res.body || '';
				var book = document.getElementById('axf-book');
				if (book && res.booking) { book.href = res.booking; book.textContent = res.bookingLabel || 'Pick a time'; book.hidden = false; }
				done.hidden = false;
				done.scrollIntoView({ behavior: 'smooth', block: 'center' });
			})
			.catch(function (err) {
				if (btn) { btn.disabled = false; btn.textContent = 'Send my details'; }
				var wrap = steps[at].querySelector('.axf-field');
				if (wrap) {
					wrap.classList.add('has-err');
					var e2 = wrap.querySelector('.axf-err');
					if (e2) { e2.hidden = false; e2.textContent = err.message; }
				}
			});
	}

	form.addEventListener('submit', function (e) { e.preventDefault(); submit(); });

	show(0);
})();
