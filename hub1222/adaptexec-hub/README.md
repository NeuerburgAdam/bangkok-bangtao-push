# AdaptExec Sales Hub

A WordPress plugin that gives your sales representatives a branded portal for
running their business: a CRM, the client discovery questionnaire, and a
full-screen presentation mode built from the AdaptExec packets.

It looks like adaptexec.com, not like WordPress — reps never see wp-admin.

---

## What it does

**For representatives**

- **Pipeline** — leads on a drag-and-drop board or a sortable list, seven stages
  from New through Closed Won.
- **Lead registration** — the Section 2.4 workflow from the sales agreement.
  A rep submits a prospect, you confirm it or flag it as a house account, and the
  decision shows up in their portal immediately.
- **Client questionnaire** — an eight-section discovery instrument covering the
  business, their current software stack and what it costs, how the work flows,
  pain points, data migration, website needs, timing and budget, plus a private
  rep read. Section four is the full **157-module library** from the capability
  packet: tap once for must-have, twice for nice-to-have.
- **Present** — eleven industry slide decks, full screen, arrow keys, click or
  swipe, with an overview grid (press O). These are slides, not PDF pages: one
  idea per screen, built to be talked over. Sessions are logged against the lead
  so you can see who presented what.
- **Shared links** — send a deck as a link. The prospect opens it with no login,
  sees only the slides, and the closing slide points at whichever of that rep's
  lead forms they attached. Views, finishes and enquiries are counted per link.
- **Follow-ups** — simple tasks with due dates.
- **Lead forms** — build a shareable form in a minute, get a link and a QR code, and
  share it in a video description, on a flyer, or by text. Every submission opens a
  lead in that rep's pipeline tagged with the source, with the full answers on the
  timeline. Views, submissions and conversion are tracked per form.
- **Earnings** — commission on closed business, worked out from the schedule in
  section 3 of the agreement. Half is earned when the client's deposit has fully
  cleared and half when their final payment has, each paid by check after the
  funds clear (no fixed date), and the page shows which halves are paid,
  payable or still waiting on the client. The rate is
  locked onto the deal when it closes, so re-tiering later cannot rewrite
  commission already earned.
- **Conflict warnings** — registering a prospect another rep already holds is
  flagged on the lead, in the submission, and in the email to you. Company names
  are normalised, so "Summit Ridge Automotive, LLC" collides with "Summit Ridge
  Automotive", and shared web domains are matched too.
- **Packets** — download any PDF to email a client. The PDFs stay the
  leave-behind; the decks are for the meeting.

**Onboarding (1.6.0)**

- **Rep checklist** — a new rep's dashboard leads with three steps until they are done: e-sign the
  Independent Contractor Agreement, upload a completed W-9, and set up payment (who checks are payable
  to and where they are mailed). Each step says what it is and why.
- **Agreement e-signature** — a reading view with contents and a progress bar; signing unlocks at the end
  (or with "I have read it"). Drawn (mouse, pen or touch) or typed signature, e-sign consent, and a
  record of the time (UTC and Phoenix), IP, browser, version and SHA-256 of the exact text. A standalone,
  print-ready signed copy with a full audit trail goes to the rep by email and is always in the Hub.
  Optional countersignature. A new version (or new company details) asks reps to re-sign.
- **W-9** — links the official IRS form; uploads (PDF/JPG/PNG/HEIC, 10 MB, content-sniffed) are encrypted
  with libsodium in `wp-content/uploads/axh-private/` and served only through the authenticated API.
  Never emailed. Replacements keep the history.
- **Payment setup** — US mailing address with validation and a live preview. A change emails the rep at
  their account email, alerts you, and holds payouts until you mark it verified.
- **Payouts** — each half shows who the check goes to; "Mark paid" asks for an explicit override when a
  rep's W-9, agreement or verified address is missing.
- Settings (company legal name, optional mailing address, signer, reminders, the agreement text) live in
  the Hub under Administration → Onboarding → Settings. The private folder is never deleted by
  uninstalling, and its files can only be read with this site's wp-config salts.

**For you (administrator)**

- **Team overview** — every rep's open count, pipeline value, closed business and
  last activity.
- **Registrations** — approve or decline pending prospects in one click.
- **Build briefs** — every submitted questionnaire, opened as a readable brief:
  each question with its answer, the client's current software and what it costs
  them, and the module selection split into must-have and nice-to-have and
  grouped by category. It prints cleanly, so a developer can scope from paper.
  The rep's own read is marked internal. Mark a brief reviewed and leave a
  scoping note when you have priced it.
- **Payments** — record when a client paid their deposit and their balance, and
  when each half of the commission went out. That drives every rep's Earnings
  page.

---

## Installing

### 1. The plugin

Upload `adaptexec-hub.zip` through **Plugins → Add New → Upload Plugin**, then
activate it.

On activation it creates five database tables, adds a **Sales Representative**
role, and registers the portal at `/hub/`.

Visit **Settings → Permalinks** once and click Save. WordPress needs that to
recognize the new URL.

### 2. The presentation decks

The decks are the twelve packets and their imagery — about 150 MB, which is more
than most hosts allow through the WordPress uploader. Install them by file
manager or SFTP instead:

1. Unzip `adaptexec-hub-decks.zip`.
2. Upload the resulting `adaptexec-decks` folder to `wp-content/uploads/`.

The final path should be `wp-content/uploads/adaptexec-decks/capability/index.html`.

The plugin scans both `wp-content/uploads/adaptexec-decks/` and its own `decks/`
folder, so either location works. Deck listings are cached for an hour.

The presentation decks read their screenshots from the same place
(`_shared/shots/`), so install this even if you only plan to use the slides —
without it the decks render their text but not their imagery.

### 3. Your representatives

Go to **AdaptExec Hub** in the admin sidebar, set your notification email, and add
each rep by name and email. WordPress sends them a link to set their own password.
They sign in at `https://adaptexec.com/hub/`.

---

## How the pieces fit

```
adaptexec-hub/
├── adaptexec-hub.php          Bootstrap, constants, helper functions
├── includes/
│   ├── class-axh-install.php  Tables, roles, pipeline stages, industries
│   ├── class-axh-db.php       All data access; every read is scoped to the user
│   ├── class-axh-rest.php     REST API under /wp-json/axh/v1/
│   ├── class-axh-portal.php   Front-end routing, login, deck discovery
│   ├── class-axh-admin.php    The wp-admin settings and rep list
│   └── modules.php            The 157-module library + questionnaire schema
├── templates/
│   ├── app.php                App shell
│   └── login.php              Branded sign-in
├── assets/                    Brand CSS, the app JS, fonts and site graphics
├── decks/                     Packets (optional here; uploads folder also works)
│
│   Public form lives at /f/<slug>/ — its own template, CSS and JS, and a
│   dependency-free QR encoder (assets/js/qr.js) so rep links never touch a
│   third-party QR service.
│
│   Slides live in slides/<industry>.json (written by
│   packet-source/build_slides.py) and are played by assets/js/slides.js on a
│   fixed 1280x720 stage. /deck/<slug>/ is the signed-in player; /s/<token>/ is
│   the public share, which adds the closing call to action.
└── bin/sync-decks.py          Rebuilds decks from packet-source/
```

### Adding or changing a module

Modules live in `includes/modules.php`. They are also filterable, so you can add
one from a theme or a small companion plugin without touching this file:

```php
add_filter( 'axh_module_library', function ( $cats ) {
    $cats[0]['modules'][] = array( 'name' => 'Territory mapping', 'live' => false );
    return $cats;
} );
```

The questionnaire schema (`axh_discovery_schema`) and the pipeline stages
(`axh_stages`) are filterable the same way.

### Rebuilding the decks after editing a packet

From the repository root, on a Mac with the packet sources present:

```bash
python3 wordpress-plugin/adaptexec-hub/bin/sync-decks.py
```

It rewrites asset paths, shares images once across all decks, downscales the
screenshots for screen presentation, and writes each `deck.json`.

---

## Checking it before it ships

There is no PHP on a design machine, so the plugin cannot be run before it
reaches a staging site. `dev/check.py` catches the mistakes that would otherwise
only surface there:

```bash
python3 dev/check.py
```

It resolves every `AXH_*::method()` call and `axh_*()` helper, verifies each
REST route has a callback that exists and a permission callback, matches the
routes against what `hub.js` actually calls in both directions, checks every
template only reads variables something assigns, parses all JavaScript, balances
the CSS, confirms each table a query touches is created, refuses a schema change
that forgets to bump `DB_VERSION`, and checks the slide decks against the slide
types the player can render. It exits non-zero, so it can gate a commit.

It is static analysis, not a substitute for staging — it cannot catch a wrong
SQL result or a capability misconfiguration. Install on staging first.

## Security notes

- Every REST route requires an authenticated user with the `axh_use_hub`
  capability; administrative routes require `axh_manage_all`.
- Representatives can only read and write their own leads. Scoping is enforced in
  SQL, not in the interface.
- All input is sanitized on the way in and escaped on the way out; every query is
  prepared.
- Representatives are redirected out of wp-admin and the admin bar is hidden for
  them.
- Client questionnaire answers are stored as JSON in a custom table, not in
  `wp_posts`, so they are never exposed through the public REST API or a feed.
- Shared decks expose nothing but the slides and the attached form: no pipeline,
  no other clients, no Hub navigation. A deleted or deactivated link returns a
  plain "no longer live" page.
- The public form endpoint and the share-event counter are the only
  unauthenticated routes. The counter only ever increments a tally on a link
  that is already public, and records no identity.
- The public form endpoint It is guarded by a
  nonce, a honeypot field, a minimum fill time, and a five-per-ten-minutes throttle
  per IP address. Submitter IPs are stored hashed, never in the clear.

## Before you go live

Two host-level settings matter more than anything in this plugin:

1. **SMTP.** `wp_mail()` on shared hosting lands in spam, and this plugin emails reps
   the moment a form is submitted. Configure SendGrid, Postmark or Resend.
2. **A real cron.** WordPress's built-in cron only fires when someone visits the
   site. Point a server cron at `wp-cron.php` if you want reliable reminders.

Install on a staging site first.

## Previewing it without WordPress

There is no PHP on a design machine, so `dev/build-preview.py` generates a
static harness that runs the real CSS and JavaScript against a mock API held in
memory. Changes respond; nothing is saved.

```bash
cd wordpress-plugin/adaptexec-hub
python3 dev/build-preview.py
python3 -m http.server 8080
```

Then open <http://localhost:8080/> — the launcher links to the rep portal, the
public lead form, every slide deck and every demo website.

The signed-in routes (`/hub/`, `/f/<slug>/`, `/s/<token>/`, `/deck/<slug>/`)
need PHP and only exist on a real install. Everything the harness generates is
prefixed `__` or is `index.html`, and none of it ships in the zip.

## Requirements

WordPress 6.0+, PHP 7.4+. No build step and no external dependencies — the app is
plain JavaScript so whoever maintains the site next can read it.

## Billing (1.7.0)

Proposals, estimates and invoices with customer-facing pages, customers, a products & services catalog,
recorded payments, QuickBooks-style reports, and team commission payouts.

**Where the code lives** (the plugin editor can change files but not add them):

- `includes/modules.php` — `AXH_Bill` (data, totals, payments, commission link, reports, exports, payout batches) and `AXH_Bill_Rest` (REST routes under `/axh/v1/billing/*`, public routes under `/axh/v1/public/bill/<token>/*`).
- `includes/class-axh-portal.php` — `AXH_Bill_View`: the customer pages at `/p/<token>/`, `/e/<token>/`, `/i/<token>/` (inline CSS/JS, print stylesheet) and the customer email.
- `includes/class-axh-install.php` — tables `axh_customers`, `axh_items`, `axh_bdocs`, `axh_bpay`, `axh_bevents`, `axh_cadjust`, `axh_cbatch`; `axh_payouts.batch_id`.
- `assets/js/hub.js` (BILLING section) and `assets/css/hub.css` (Billing section) — the Hub screens.

**Who can do what.** AdaptExec (managers) creates, edits, sends, records payments and pays commission. Reps see the
documents (not drafts), customers and payments of their own clients, can copy or email the customer link (logged with
their name) and read their own payout statements. Any other id is a 404.

**Customer links** are 160-bit random tokens; regenerate one to kill the old link. Pages are `noindex`, send
`Referrer-Policy: no-referrer`, can't be framed by other sites, and count views only from real visitors (not bots,
the rep or AdaptExec). Acceptance records typed name, optional drawn signature, time, IP, browser and a SHA-256
fingerprint of the document, is rate limited, and locks the document.

**Commission.** An invoice tagged *Deposit half*, *Final half* or *Both halves* drives the deal's "customer payment
cleared" dates that `AXH_DB::earnings()` already uses: set when the invoice is paid in full and every payment on it has
cleared (checks start "not cleared"), taken back if a payment is voided, refunded or marked not cleared. A date typed
on the deal before Billing is never overwritten. Refunds after commission was paid open a clawback, deducted from the
next payout batch. Holds block a half from batches and from the Payouts screen.

**Card payments (later).** Register a gateway on the `axh_bill_gateways` filter (`label`, `start( $doc, $amount )` →
checkout URL) and have its webhook call `AXH_Bill::gateway_payment( $doc_id, $amount, $gateway, $txn_id )`. The Pay
button appears on invoices only while a gateway is registered.

**QuickBooks Online (later).** Customers, items, documents and payments carry `external_id` / `sync_token` /
`synced_at`. Billing → Export → *QuickBooks entities (JSON)* emits Customer, Item, Estimate (proposals included),
Invoice and Payment objects in QBO API shapes with stable `axh_id` keys; *QuickBooks invoice import (CSV)* matches
QBO's invoice import spreadsheet.

## Deal flow (1.7.1): reps run discovery, Adam quotes

Reps nurture their own leads and run discovery themselves; Adam no longer takes a discovery call on a
rep's lead unless he asks for one.

1. **Register** the lead (unchanged).
2. **Discovery questionnaire** on the lead: the rep fills it in during meetings, or sends the client's
   own `/d/<token>/` link. Answers are credited to whoever typed them (`sources`: rep or client) and the
   brief labels them *Rep-entered* / *From the client*. The general questionnaire covers business basics,
   goals, current website/tools, users and roles, step-by-step workflows, integrations (QuickBooks, Google,
   church/industry software), data to migrate, must-have / nice-to-have features, content and branding,
   examples (incl. Style Finder picks), timeline, budget range, decision-makers and an optional
   **On-site workflow study** section. Every questionnaire, trade packets included, carries the on-site
   section; it never counts against completeness.
3. **Quote readiness** = share of the 17 key questions answered (`'essential' => true`); *Request a quote*
   unlocks at 70% (packets without key questions: all questions, 50%; filter `axh_quote_threshold`). Below
   it the rep can still request with a note of 10+ characters. The client page shows completeness too.
4. **Request a quote** sets status `submitted`, stage *Scoping*, creates/links the Billing customer and
   notifies managers in the Hub and by email at the notification address (`discovery_submitted`, now
   labelled "A rep requests a quote"). The client pressing *I'm finished* only sets `client_done` and tells
   the rep.
5. Adam opens **Quote requests** → the printable brief → *Start proposal*. The CRM, Website + CRM and new
   **Custom software** templates include an optional, $0 **On-site workflow study** add-on, and the catalog
   gains an "On-site workflow study" item at $0 (seeded once; Adam prices it per job).

`/d/preview/` (signed-in Hub users only, `?industry=` for a packet) shows the client view with nothing saved.
DB 1.11.0 adds `sources`, `quote_note`, `quote_by`, `quote_ready` to `wp_axh_discovery`.

## Clean-up controls (1.7.2)

- **Delete submissions** (Hub admins only): Lead forms → *Recent submissions* has a checkbox per row,
  *Delete selected* and a per-row *Delete*, each behind a confirm. `DELETE /axh/v1/forms/submissions/<id>`
  and `POST /axh/v1/forms/submissions/delete` (`{ids: [...]}`) — `axh_manage_all` only. The lead a
  submission opened is kept. Each deletion writes an audit row (`submission_deleted`, "Submission #N
  deleted by …") that records the number and who, never the content.
- **Delete notifications**: everyone can remove their own notifications from the bell (× on each, and
  *Clear all read*). `DELETE /axh/v1/notifications/<id>` and `POST /axh/v1/notifications/clear` only ever
  touch the signed-in person's rows.
- **Deleting a lead now cascades** to its form submission(s), every user's notifications about it, deck
  share links made for it and presentation sessions logged on it (as well as activity, follow-ups and the
  questionnaire, as before). A billing customer or billing document linked to the lead is kept and
  unlinked (`lead_id` set to NULL); commission payouts and adjustments are never touched.
- **DB 1.12.0** runs a one-time clean-up of submissions and notifications whose `lead_id` points at a lead
  that no longer exists (rows with no lead are left alone). The removed ids are recorded in the
  `axh_orphans_purged` option.

## Print & PDF (1.7.3)

- **Proposals print as a designed document.** The cover is exactly page 1, edge to edge; every later page
  has the same margins, a small logo + "Proposal P-1001 · Customer" header and "Page X of Y" footer
  (CSS `@page` margin boxes). Cards, timeline steps, option cards, the totals box and the dark
  "Why" card never split, and headings stay with what follows them.
- **No interactive leftovers.** Radio ticks, switches, the accept form and "type your name" copy do not
  print. Options show *Selected option* / *Also available*; every add-on prints with its price and
  *Added* / *Optional*. A print-only **How to accept** block carries the customer link, a QR code
  (bundled `qr.js`, no outside service) and name / signature / date lines for signing on paper.
  Invoices print a "view this invoice online" QR inside *How to pay*.
- **Placeholders.** Unfilled template blanks like `[what is not working]` are highlighted wherever they
  appear (customer page, preview, PDF). The Hub lists them on the document page and in the editor
  (fields outlined), and *Send* / *Mark as sent* ask before going out with any left
  (tick "I know — send it anyway").
- **Download PDF in the Hub.** The document page has a *Download PDF* button, and Ctrl/⌘+P there prints
  the customer page from the preview frame (not the Hub screen). The browser tab and saved PDF are named
  "Proposal P-1001 — Customer — AdaptExec". Printing the Hub screen itself prints a pointer to the button.
- Estimates, invoices and commission statements got the same treatment (margins, page numbers, rows and
  boxes kept whole, the statement prints without the Hub header).
- New proposals from the built-in templates say "accept online, or sign the printed copy" instead of
  "switch on" / "type your name below". Existing documents keep their wording.

## Print pagination (1.7.4)

- **Pages fill up.** No section forces a new page (only the cover is a page of its own); content flows from one
  section to the next.
- **Nothing splits mid-thought.** Paragraphs, list items, table rows, timeline steps, scope cards, included
  chips, add-on rows and option lines are never cut across pages; a heading always stays with what follows.
- **Intros.** A short section intro (about two lines) stays with the block after it. A longer intro is never split,
  and may end a page on its own when the block after it cannot fit, instead of leaving the page part-empty.
- **Big blocks.** Option cards, the add-on box, the dark totals box and the Why card stay whole when they are a
  normal size. Very long ones (12+ option lines, 5+ add-ons, 5+ payments, 7+ Why cards) may continue on the next
  page, breaking only between rows.
- **Lists.** Next steps (up to 6) stay together; lists of up to 3 items stay together; a longer list that has to
  continue never leaves its last item alone at the top of a page. A 4-step timeline prints in one row; the
  contact footer stays with "How to accept"; a single option hides the redundant "Selected option" bar; body text
  prints at 10pt and spacing is a little tighter.
- **Add-on names.** "(optional)" or "[optional]" anywhere in an add-on name (any case), or a trailing "- optional",
  is dropped when the name is shown on the customer page, the PDF, the acceptance record and the acceptance
  notification, since the Optional/Added chip already says it. Saved documents are not changed.

## Marketing add-ons & photo guidance (1.7.5)

- **Catalog.** Three items are added once on upgrade (by name, never duplicated or re-added after you delete or
  rename them): *Google Ads management* ($350 / month, Branding & Marketing), *Google Ads campaign setup* ($400
  one-time) and *Monthly SEO* ($450 / month, SEO). The rates are starting points; change them in the catalog or
  per document. Ad spend is paid to Google by the client and is never included.
- **Proposal templates.** *Monthly SEO* and *Google Ads management* are optional add-ons, off by default, on the
  Website and Website + CRM templates; Custom CRM gets Google Ads; the SEO template's Monthly SEO add-on now
  carries the $450 standard rate and Google Ads is added beside it. Month-to-month, no contract, consistent with
  "No required monthly plan". Templates you have edited and saved keep your version (Reset brings in the new one).
- **Photos.** The Website and Website + CRM timelines (week 1) ask for photos in a Google Drive or Dropbox folder,
  organized by service. The discovery questionnaire's *Content and branding* section adds "Where are your
  photos?" (a link, with helper text on organizing by service, file names, originals and consent), and the Sales
  kit discovery agenda reminds reps to ask for it.
- **Print.** Four or more add-ons print with slightly tighter rows and 8pt descriptions, so the longer add-on list
  takes less room on the page.

## Editor layout (1.7.4)

- **Line items wrap by card width.** Qty, Rate, Unit, Disc. and the line total sit on one row when the line card is
  wide; with the live preview open on a laptop, or on a phone, they wrap (Qty + Rate, Unit + Disc., total on its own
  row, right-aligned). Fields stay at least 64px wide, captions line up and the total never overlaps an input.
- **Toggles.** The add-on "on by default" and section show/hide switches render as full switches again.
- Payment schedule, timeline rows, section headers, the details grid and the discount/tax pair wrap the same way,
  and section cards can no longer be pushed past the editor's edge.

## Discovery rebuild and client onboarding (1.22.0)

**Discovery questionnaire.** Every question now has two voices: the rep's wording and meeting tips, and the
client's wording (`client_label`, `client_help`). The client's link shows only the client's wording; our tips,
rep-only questions (revenue band, budget state, Style Finder picks) and internal pages never reach the page.
The rep sees the client's wording as an "Ask:" line, so the questionnaire doubles as a talk track.

- *What can we help you with?* comes first and decides what follows (`show_if`): a website-only project never
  sees the CRM workflow pages, a CRM never sees page counts. Key questions can be conditional too, and
  readiness, progress and the brief follow the same rules (`axh_discovery_applicable()`).
- Added for quoting: pages needed, how many services get their own page, store size, content to bring over,
  domain, hosting/business email, ongoing help (SEO, Google Ads, social, website care, software support).
- Moved to onboarding (no longer asked, answers kept on the brief and used to pre-fill): legal name, DBA,
  years in business, brand assets, photo link, other ready content.
- Saving is per answer. The client page saves each answer as it is given, keeps unsent answers on the device,
  retries and sends before the page closes; the server merges one question at a time under a lock, so a client
  and rep typing at once never overwrite each other, and answers to sections the rep switched off are kept.
- The client page is a guided, one-section-at-a-time flow with a progress rail, "welcome back" resume and a
  review-and-send step. Reps can email the link from the Hub (`POST /axh/v1/discovery-email/<lead>`).

**Client onboarding** (`AXH_Kickoff`, in `includes/class-axh-files.php`). After the quote, one private page at
`/k/<token>/` collects everything the build needs. What it asks is worked out from the job: the proposal
(accepted first; its chosen option and switched-on add-ons) and the discovery answers. Social media access only
appears when social media management is part of the job; Stripe only when Stripe is involved; Render or other
cloud hosting when the proposal mentions it; email-host access when business email is being set up.

- Items: contacts, business details, dates; logo, colors, fonts, brand guide, graphics, badges, voice; photos
  in albums by service, video; story, services, team (with headshots), reviews, FAQs, collateral, legal pages,
  audience and keywords; domain, hosting, website login, pages to keep, form recipients, Google Analytics,
  Search Console, Business Profile, Tag Manager, store setup and products; email host and mailboxes; logins
  to create, data exports, current paperwork, rules, QuickBooks, Stripe, Square, Google/Microsoft, Twilio,
  email marketing, booking tool, industry software, cloud hosting; Google Ads, Facebook/Instagram, LinkedIn,
  other social accounts, social links, posting preferences, past campaigns; plus custom requests.
- Access items give step-by-step invite guides per platform with the invite address to copy; a client can
  instead share a login, which is sealed with the site key and only managers can open (each opening is logged).
- Uploads are drag and drop, sent in pieces (any size up to the limit in settings) and encrypted piece by piece
  into the lead's private files with a photo preview. Every answer autosaves.
- In the Hub: a card on the lead page, `#/kickoff/<lead>` to set up (suggested items, with the reason each is
  there), email or copy the link, track progress, download files, mark items received or ask for changes
  (the note shows on the client's page), and `#/kickoffs` for all of them. Settings (managers): invite address,
  LinkedIn admin name, Meta partner ID, upload limit.
- Data: table `wp_axh_kickoff` (option `axh_kick_db`), and `kick`, `src`, `thumb` columns on
  `wp_axh_lead_files` (`axh_files_db` = 2). Both are added in place; nothing existing is changed or removed.

### 1.22.2: answer-based branching, next move, training

- **Branching on any answer.** `show_if` can now depend on another question (`array( 'field' => 'onsite.interest',
  'any' => array( 'Yes', 'Maybe' ), 'blank' => false )`), use `not`, `filled`, `or`, or a list of rules that must all
  pass (`axh_disc_cond()`, mirrored in both client page and Hub). Follow-ups appear in place, live, as answers change.
  Applied to: the on-site study details (only after Yes/Maybe), website platform/likes and content to bring over
  (only with an existing site or a redesign), page questions (hidden when they keep their current site), store size
  (store, or "Sell products online"), record volume and export (hidden when nothing is moving), and the industry
  software name (after picking church/industry software). Hidden key questions never count against readiness.
- **Your next move** on every lead: one recommended step and button, worked out from registration, the
  questionnaire, the proposal and onboarding (send the questionnaire, review their answers, request a quote, walk
  them through the proposal, send onboarding, nudge, request Closed Won).
- **Sending onboarding**: email it from AdaptExec, text it (Hub text composer with a ready message), or copy the link.
- **Sales kit training**: new "Questionnaire & onboarding" section (how to send, what clients see, branching,
  readiness, onboarding setup, tracking, logins, quick answers); deal flow now eight steps including onboarding; first
  week and meeting agenda updated; contact email now adam@adaptexec.com. Greetings skip titles ("Dr. Ana Wu" → Ana).
