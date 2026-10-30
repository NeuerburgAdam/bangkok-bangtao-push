<?php
/**
 * The AdaptExec module library and the client discovery questionnaire schema.
 *
 * The module list mirrors the capability packet so the questionnaire, the packets,
 * and the build brief all speak the same language.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return array Categories, each with a key, name and list of modules.
 */
function axh_module_library() {
	return apply_filters(
		'axh_module_library',
		array(
			array(
				'key'     => 'A',
				'name'    => 'Sales & CRM',
				'modules' => array(
					array( 'name' => 'Leads pipeline', 'live' => true ),
					array( 'name' => 'Customer records', 'live' => true ),
					array( 'name' => 'Activity feed', 'live' => true ),
					array( 'name' => 'Global search', 'live' => true ),
					array( 'name' => 'Estimates & quotes', 'live' => true ),
					array( 'name' => 'Proposal generator', 'live' => true ),
					array( 'name' => 'Contracts', 'live' => true ),
					array( 'name' => 'Referral tracking', 'live' => true ),
					array( 'name' => 'Prospect tracker', 'live' => true ),
					array( 'name' => 'Fit-score assessments', 'live' => true ),
					array( 'name' => 'E-signature', 'live' => false ),
					array( 'name' => 'Commission engine', 'live' => false ),
					array( 'name' => 'Deal forecasting', 'live' => false ),
					array( 'name' => 'Quote follow-up sequences', 'live' => false ),
				),
			),
			array(
				'key'     => 'B',
				'name'    => 'Scheduling & Booking',
				'modules' => array(
					array( 'name' => 'Online booking pages', 'live' => true ),
					array( 'name' => 'Consult scheduling', 'live' => true ),
					array( 'name' => 'Team calendars', 'live' => true ),
					array( 'name' => 'Free/busy availability', 'live' => true ),
					array( 'name' => 'Walk-in queue', 'live' => true ),
					array( 'name' => 'Appointment reminders', 'live' => true ),
					array( 'name' => 'Event registration', 'live' => true ),
					array( 'name' => 'Dispatch board', 'live' => false ),
					array( 'name' => 'Route planning', 'live' => false ),
					array( 'name' => 'Recurring appointments', 'live' => false ),
					array( 'name' => 'No-show recovery', 'live' => false ),
				),
			),
			array(
				'key'     => 'C',
				'name'    => 'Operations & Projects',
				'modules' => array(
					array( 'name' => 'Jobs & project boards', 'live' => true ),
					array( 'name' => 'Kanban & list views', 'live' => true ),
					array( 'name' => 'Tasks & punch lists', 'live' => true ),
					array( 'name' => 'Time clock & timesheets', 'live' => true ),
					array( 'name' => 'Photo galleries', 'live' => true ),
					array( 'name' => 'Change orders', 'live' => true ),
					array( 'name' => 'Field crew tools', 'live' => true ),
					array( 'name' => 'Production stages', 'live' => true ),
					array( 'name' => 'Price books', 'live' => true ),
					array( 'name' => 'Inventory control', 'live' => false ),
					array( 'name' => 'Equipment tracking', 'live' => false ),
					array( 'name' => 'Safety checklists', 'live' => false ),
					array( 'name' => 'Warranty tracking', 'live' => false ),
				),
			),
			array(
				'key'     => 'D',
				'name'    => 'Money',
				'modules' => array(
					array( 'name' => 'Invoicing', 'live' => true ),
					array( 'name' => 'Card payments (Stripe)', 'live' => true ),
					array( 'name' => 'Bank payments (ACH)', 'live' => true ),
					array( 'name' => 'Payment schedules', 'live' => true ),
					array( 'name' => 'Deposits & retainers', 'live' => true ),
					array( 'name' => 'Receipts & expenses', 'live' => true ),
					array( 'name' => 'Reimbursements', 'live' => true ),
					array( 'name' => 'Payroll integration', 'live' => true ),
					array( 'name' => 'Profit & tax dashboards', 'live' => true ),
					array( 'name' => 'Budgets & designated funds', 'live' => true ),
					array( 'name' => 'Allowance tracking', 'live' => true ),
					array( 'name' => 'Recurring billing', 'live' => false ),
					array( 'name' => 'QuickBooks sync', 'live' => false ),
					array( 'name' => 'Financing options', 'live' => false ),
				),
			),
			array(
				'key'     => 'E',
				'name'    => 'Client-Facing Portals',
				'modules' => array(
					array( 'name' => 'Client portals', 'live' => true ),
					array( 'name' => 'Progress timelines', 'live' => true ),
					array( 'name' => 'Payment portal', 'live' => true ),
					array( 'name' => 'Document sharing', 'live' => true ),
					array( 'name' => 'Guided intake wizards', 'live' => true ),
					array( 'name' => 'Supplier portals', 'live' => true ),
					array( 'name' => 'Member portals', 'live' => true ),
					array( 'name' => 'Presenter mode', 'live' => true ),
					array( 'name' => 'Loyalty & earned credits', 'live' => false ),
					array( 'name' => 'Satisfaction surveys', 'live' => false ),
					array( 'name' => 'Family/guardian access', 'live' => false ),
				),
			),
			array(
				'key'     => 'F',
				'name'    => 'Communication',
				'modules' => array(
					array( 'name' => 'Transactional email', 'live' => true ),
					array( 'name' => 'SMS notifications', 'live' => true ),
					array( 'name' => 'Push notifications', 'live' => true ),
					array( 'name' => 'In-app messaging', 'live' => true ),
					array( 'name' => 'Announcements & events', 'live' => true ),
					array( 'name' => 'Deliverability setup (DMARC)', 'live' => true ),
					array( 'name' => 'Drip campaigns', 'live' => false ),
					array( 'name' => 'Bulk email & newsletters', 'live' => false ),
					array( 'name' => 'Review requests', 'live' => false ),
					array( 'name' => 'Call & voicemail logging', 'live' => false ),
					array( 'name' => 'Contact preferences', 'live' => false ),
				),
			),
			array(
				'key'     => 'G',
				'name'    => 'Automation & Workflow',
				'modules' => array(
					array( 'name' => 'Workflow automations', 'live' => true ),
					array( 'name' => 'Approval chains', 'live' => true ),
					array( 'name' => 'Triggers & rules', 'live' => true ),
					array( 'name' => 'Scheduled jobs', 'live' => true ),
					array( 'name' => 'Data syncs', 'live' => true ),
					array( 'name' => 'Escalations & SLA timers', 'live' => false ),
					array( 'name' => 'Webhooks', 'live' => false ),
					array( 'name' => 'Smart form logic', 'live' => false ),
					array( 'name' => 'Auto-assignment', 'live' => false ),
				),
			),
			array(
				'key'     => 'H',
				'name'    => 'AI & Intelligence',
				'modules' => array(
					array( 'name' => 'Ask-your-business Q&A', 'live' => true ),
					array( 'name' => 'AI content & SEO studio', 'live' => true ),
					array( 'name' => 'AI-assisted client plans', 'live' => true ),
					array( 'name' => 'Quiz & course generation', 'live' => true ),
					array( 'name' => 'AI translation (10+ languages)', 'live' => true ),
					array( 'name' => 'Knowledge engine', 'live' => true ),
					array( 'name' => 'Document analysis', 'live' => false ),
					array( 'name' => 'Meeting summaries', 'live' => false ),
					array( 'name' => 'Smart lead scoring', 'live' => false ),
					array( 'name' => 'AI phone reception', 'live' => false ),
					array( 'name' => 'Transcription', 'live' => false ),
				),
			),
			array(
				'key'     => 'I',
				'name'    => 'Documents & Content',
				'modules' => array(
					array( 'name' => 'Branded PDF proposals', 'live' => true ),
					array( 'name' => 'RFP response factory', 'live' => true ),
					array( 'name' => 'Print collateral from code', 'live' => true ),
					array( 'name' => 'Knowledge base / help center', 'live' => true ),
					array( 'name' => 'Certificates & transcripts', 'live' => true ),
					array( 'name' => 'Secure media players', 'live' => true ),
					array( 'name' => 'Cover-letter templates', 'live' => true ),
					array( 'name' => 'Template letter engine', 'live' => false ),
					array( 'name' => 'Versioned contracts', 'live' => false ),
					array( 'name' => 'Watermarking', 'live' => false ),
				),
			),
			array(
				'key'     => 'J',
				'name'    => 'Education & Community',
				'modules' => array(
					array( 'name' => 'Course platforms', 'live' => true ),
					array( 'name' => 'Lesson & module builders', 'live' => true ),
					array( 'name' => 'Quiz engines', 'live' => true ),
					array( 'name' => 'Learning transcripts', 'live' => true ),
					array( 'name' => 'Audiobooks & radio', 'live' => true ),
					array( 'name' => 'Game arcades', 'live' => true ),
					array( 'name' => 'TTS accessibility reader', 'live' => true ),
					array( 'name' => 'Classes & registration', 'live' => true ),
					array( 'name' => 'Member directories', 'live' => false ),
					array( 'name' => 'Discussion forums', 'live' => false ),
					array( 'name' => 'Mentor matching', 'live' => false ),
				),
			),
			array(
				'key'     => 'K',
				'name'    => 'E-commerce & Growth',
				'modules' => array(
					array( 'name' => 'Lead capture & forms', 'live' => true ),
					array( 'name' => 'Landing pages', 'live' => true ),
					array( 'name' => 'ROI calculators', 'live' => true ),
					array( 'name' => 'Guided product demos', 'live' => true ),
					array( 'name' => 'Storefronts & catalogs', 'live' => false ),
					array( 'name' => 'Carts & checkout', 'live' => false ),
					array( 'name' => 'Subscriptions', 'live' => false ),
					array( 'name' => 'Coupons & gift cards', 'live' => false ),
					array( 'name' => 'Abandoned-cart recovery', 'live' => false ),
					array( 'name' => 'A/B page testing', 'live' => false ),
				),
			),
			array(
				'key'     => 'L',
				'name'    => 'Reporting & Data',
				'modules' => array(
					array( 'name' => 'KPI dashboards', 'live' => true ),
					array( 'name' => 'Custom reports & charts', 'live' => true ),
					array( 'name' => 'Market & contract monitoring', 'live' => true ),
					array( 'name' => 'Legacy data migration', 'live' => true ),
					array( 'name' => 'Live public stats', 'live' => true ),
					array( 'name' => 'Impact reporting', 'live' => true ),
					array( 'name' => 'Scheduled report emails', 'live' => false ),
					array( 'name' => 'Forecasting', 'live' => false ),
					array( 'name' => 'Map & territory views', 'live' => false ),
					array( 'name' => 'Data exports', 'live' => false ),
				),
			),
			array(
				'key'     => 'M',
				'name'    => 'Access & Security',
				'modules' => array(
					array( 'name' => 'Roles & permissions matrix', 'live' => true ),
					array( 'name' => 'Per-customer data isolation', 'live' => true ),
					array( 'name' => 'Approval workflows', 'live' => true ),
					array( 'name' => 'Invite-code onboarding', 'live' => true ),
					array( 'name' => 'Team hierarchy & org views', 'live' => true ),
					array( 'name' => 'Device single sign-on', 'live' => true ),
					array( 'name' => 'Audit logs', 'live' => false ),
					array( 'name' => 'Two-factor auth', 'live' => false ),
					array( 'name' => 'Session controls', 'live' => false ),
					array( 'name' => 'Automated backups', 'live' => false ),
				),
			),
			array(
				'key'     => 'N',
				'name'    => 'Platform & Presence',
				'modules' => array(
					array( 'name' => 'Installable app (your icon)', 'live' => true ),
					array( 'name' => 'SEO-engineered websites', 'live' => true ),
					array( 'name' => 'Programmatic landing pages', 'live' => true ),
					array( 'name' => 'Per-city / per-service pages', 'live' => true ),
					array( 'name' => 'JSON-LD schema', 'live' => true ),
					array( 'name' => 'Cloud file storage', 'live' => true ),
					array( 'name' => 'Custom integrations', 'live' => true ),
					array( 'name' => 'Owned infrastructure', 'live' => true ),
					array( 'name' => 'App-store apps', 'live' => false ),
					array( 'name' => 'Partner API', 'live' => false ),
					array( 'name' => 'Multi-location', 'live' => false ),
					array( 'name' => 'White-label', 'live' => false ),
				),
			),
		)
	);
}

/**
 * Flat list of every module name, for validation.
 */
function axh_module_names() {
	$names = array();
	foreach ( axh_module_library() as $cat ) {
		foreach ( $cat['modules'] as $mod ) {
			$names[] = $mod['name'];
		}
	}
	return $names;
}
/**
 * Industries that have a questionnaire of their own.
 *
 * The general schema below asks 79 questions and suits any business: enough
 * for Adam to quote without a discovery call (1.7.1). Seventeen are marked
 * essential and drive quote readiness; the on-site study is optional. Where we
 * have built a real packet for a trade, that trade's leads get it instead —
 * it asks in their language and its answers scope the build directly. Add a
 * generated schema-<slug>.php and a line here as each packet is written.
 *
 * @return array industry name => file in includes/.
 */
function axh_discovery_packets() {
	return apply_filters(
		'axh_discovery_packets',
		array(
			'Auto Repair' => 'schema-auto-repair.php',
		)
	);
}

/**
 * The client discovery questionnaire for one lead's industry.
 *
 * Sections are rendered in order by the portal. Field types the app understands:
 * text, textarea, number, money, select, multiselect, checklist, stack, rating,
 * date, scale, modules.
 *
 * @param string $industry Lead industry. Empty gives the general questionnaire.
 * @return array
 */
function axh_discovery_schema( $industry = '' ) {
	$packets = axh_discovery_packets();

	if ( $industry && isset( $packets[ $industry ] ) ) {
		$file = AXH_DIR . 'includes/' . $packets[ $industry ];
		if ( is_readable( $file ) ) {
			$schema = include $file;
			if ( is_array( $schema ) && $schema ) {
				return apply_filters( 'axh_discovery_schema', axh_discovery_with_onsite( $schema ), $industry );
			}
		}
	}

	return apply_filters(
		'axh_discovery_schema',
		array(
			array(
				'key'   => 'business',
				'name'  => 'Business basics',
				'blurb' => 'Who they are and how big the operation is. This sets the shape of everything else.',
				'client_blurb' => 'The basics about your business. Skip anything you would rather not share.',
				'fields' => array(
					array( 'key' => 'legal_name', 'label' => 'Legal business name', 'type' => 'text' ),
					array( 'key' => 'trade_name', 'label' => 'Trade / DBA name', 'type' => 'text' ),
					array( 'key' => 'years', 'label' => 'Years in business', 'type' => 'number' ),
					array( 'key' => 'locations', 'label' => 'Locations / shops / offices', 'type' => 'number' ),
					array( 'key' => 'employees', 'label' => 'Employees (office and field)', 'type' => 'number', 'essential' => true ),
					array( 'key' => 'field_staff', 'label' => 'Field staff, techs, stylists, or reps', 'type' => 'number' ),
					array( 'key' => 'customers_month', 'label' => 'Customers or jobs per month', 'type' => 'text' ),
					array( 'key' => 'avg_ticket', 'label' => 'Average ticket or job value', 'type' => 'money' ),
					array( 'key' => 'revenue_band', 'label' => 'Annual revenue band', 'type' => 'select', 'options' => array( 'Under $250K', '$250K–$1M', '$1M–$5M', '$5M–$20M', 'Over $20M', 'Declined to say' ) ),
					array( 'key' => 'service_area', 'label' => 'Service area / markets', 'type' => 'text' ),
					array( 'key' => 'what_they_sell', 'label' => 'In their own words, what do they sell?', 'type' => 'textarea', 'essential' => true ),
				),
			),
			array(
				'key'   => 'goals',
				'name'  => 'Goals and success',
				'blurb' => 'What they are buying and why. Adam builds the proposal around these answers.',
				'client_blurb' => 'What you want this project to do for you. This is the most useful part for us.',
				'fields' => array(
					array( 'key' => 'need', 'label' => 'What are they looking for?', 'type' => 'checklist', 'essential' => true, 'options' => array( 'A new website', 'A website redesign', 'A custom CRM', 'Project-management / operations software', 'A client or member portal', 'A custom app or other software', 'Automations between tools', 'SEO / getting found locally', 'Branding or a logo' ) ),
					array( 'key' => 'goals', 'label' => 'The top three things this project must achieve', 'type' => 'textarea', 'essential' => true ),
					array( 'key' => 'success', 'label' => 'A year from now, how will they know it worked?', 'type' => 'textarea', 'help' => 'Numbers help: hours saved each week, more leads or bookings, fewer missed follow-ups, faster payment.' ),
				),
			),
			array(
				'key'   => 'stack',
				'name'  => 'Current website, tools and software',
				'blurb' => 'Every subscription, what it costs, and what it is actually used for. Real numbers here decide the whole business case.',
				'client_blurb' => 'The website, apps and spreadsheets you use today, and what frustrates you about them.',
				'fields' => array(
					array( 'key' => 'website_url', 'label' => 'Current website address (if any)', 'type' => 'text' ),
					array( 'key' => 'website_platform', 'label' => 'Current website platform', 'type' => 'text' ),
					array( 'key' => 'website_likes', 'label' => 'What they like and dislike about the current website', 'type' => 'textarea' ),
					array( 'key' => 'software', 'label' => 'Current software', 'type' => 'stack', 'essential' => true, 'help' => 'Add every tool they pay for. Monthly cost matters more than the name.' ),
					array( 'key' => 'pain_points', 'label' => 'Biggest pain points with the current tools', 'type' => 'textarea', 'essential' => true ),
					array( 'key' => 'marketing_spend', 'label' => 'Monthly marketing / SEO / agency spend', 'type' => 'money' ),
					array( 'key' => 'spreadsheets', 'label' => 'Where spreadsheets or paper still do the work', 'type' => 'textarea' ),
					array( 'key' => 'double_entry', 'label' => 'What gets typed into more than one system', 'type' => 'textarea' ),
					array( 'key' => 'who_admins', 'label' => 'Who administers these systems today?', 'type' => 'text' ),
					array( 'key' => 'contract_ends', 'label' => 'Any contract or renewal date to work around', 'type' => 'text' ),
				),
			),
			array(
				'key'   => 'team',
				'name'  => 'Users, team and roles',
				'blurb' => 'Who will use the system and what each person needs to see. This drives logins, permissions and price.',
				'client_blurb' => 'Who on your team would use the new system, and what each person needs.',
				'fields' => array(
					array( 'key' => 'users_count', 'label' => 'How many people will log in?', 'type' => 'number', 'essential' => true ),
					array( 'key' => 'roles', 'label' => 'Who logs in, and what should each role see?', 'type' => 'textarea', 'essential' => true, 'help' => 'Owner, office, field, customer, supplier — list them and their limits.' ),
					array( 'key' => 'departments', 'label' => 'Departments or teams involved, and roughly how many people in each', 'type' => 'textarea' ),
					array( 'key' => 'mobile', 'label' => 'Will people use it on phones in the field?', 'type' => 'select', 'options' => array( 'Yes, mostly on phones', 'Some phone use', 'Mostly at a desk', 'Not sure' ) ),
					array( 'key' => 'client_logins', 'label' => 'Should customers, members or clients log in too?', 'type' => 'select', 'options' => array( 'Yes', 'Maybe later', 'No' ) ),
				),
			),
			array(
				'key'   => 'pain',
				'name'  => 'Workflows to automate',
				'blurb' => 'Walk them through a normal week, step by step. The friction they describe here becomes the module list and the quote.',
				'client_blurb' => 'Walk us through how work moves today, step by step. Plain language is perfect.',
				'fields' => array(
					array( 'key' => 'lead_to_cash', 'label' => 'Walk me from a new lead or request to getting paid — every step', 'type' => 'textarea', 'essential' => true, 'help' => 'Capture their words, not a summary. Who does each step, in which tool, and where it gets stuck.' ),
					array( 'key' => 'workflows', 'label' => 'Other workflows to automate, step by step', 'type' => 'textarea', 'help' => 'One per paragraph: what starts it, each step and who does it, the tool used, and what should happen automatically.' ),
					array( 'key' => 'approvals', 'label' => 'Approvals, hand-offs or paperwork that slow things down', 'type' => 'textarea' ),
					array( 'key' => 'weekly_break', 'label' => 'What goes wrong every week?', 'type' => 'textarea' ),
					array( 'key' => 'customer_complaint', 'label' => 'What do customers complain about most?', 'type' => 'textarea' ),
					array( 'key' => 'owner_nights', 'label' => 'What does the owner do at night that software should do?', 'type' => 'textarea' ),
					array( 'key' => 'cant_see', 'label' => 'What can they NOT see today that they wish they could?', 'type' => 'textarea', 'help' => 'Reports and dashboards they would check every week.' ),
					array( 'key' => 'tried_before', 'label' => 'What have they tried before that failed, and why?', 'type' => 'textarea' ),
					array( 'key' => 'team_tech', 'label' => 'How comfortable is the team with technology?', 'type' => 'rating', 'help' => '1 = paper and phone calls · 5 = everyone lives in apps' ),
				),
			),
			array(
				'key'   => 'data',
				'name'  => 'Integrations and data',
				'blurb' => 'What has to come across on day one, and what the platform has to talk to.',
				'client_blurb' => 'The other software it needs to connect to, and the records that need to move over.',
				'fields' => array(
					array( 'key' => 'integrations', 'label' => 'Must integrate with', 'type' => 'checklist', 'essential' => true, 'options' => array( 'QuickBooks', 'Stripe', 'ACH / bank payments', 'Payroll (Gusto, ADP)', 'Google Workspace', 'Google Calendar', 'Gmail', 'Google Drive / Sheets', 'Microsoft 365', 'Twilio / SMS', 'Mailchimp or similar', 'Church management software', 'Industry supplier / catalog', 'Industry software (name it below)', 'None', 'Other (note below)' ) ),
					array( 'key' => 'industry_software', 'label' => 'Church or industry software they use today (name it)', 'type' => 'text', 'help' => 'Planning Center, Breeze, Tekmetric, Clio, ServiceTitan, Mindbody…' ),
					array( 'key' => 'migrate', 'label' => 'What history must migrate?', 'type' => 'checklist', 'essential' => true, 'options' => array( 'Customers / contacts', 'Jobs / projects / tickets', 'Invoices &amp; payments', 'Photos &amp; documents', 'Appointments', 'Products / price book', 'Employees &amp; payroll', 'Notes &amp; communications', 'Nothing — starting fresh' ) ),
					array( 'key' => 'record_volume', 'label' => 'Roughly how many records, and how far back?', 'type' => 'text' ),
					array( 'key' => 'export_ok', 'label' => 'Can they export from the current system?', 'type' => 'select', 'options' => array( 'Yes, confirmed', 'They think so', 'No / locked in', 'Unknown' ) ),
					array( 'key' => 'compliance', 'label' => 'Regulated or sensitive data?', 'type' => 'checklist', 'options' => array( 'Health information (HIPAA)', 'Financial / consumer data', 'Minors', 'Legal / privileged', 'Payment card data', 'None known' ) ),
					array( 'key' => 'integration_notes', 'label' => 'Integration and compliance notes', 'type' => 'textarea' ),
				),
			),
			array(
				'key'   => 'modules',
				'name'  => 'Features: must-have and nice-to-have',
				'blurb' => 'Must-have drives the first phase and the quote; nice-to-have becomes phase two. Mark modules too if you know them.',
				'client_blurb' => 'What it has to do on day one, and what could wait for a later phase.',
				'fields' => array(
					array( 'key' => 'must_have', 'label' => 'Must-have features for launch', 'type' => 'textarea', 'essential' => true, 'or_modules' => true ),
					array( 'key' => 'nice_to_have', 'label' => 'Nice-to-have features (could come in phase two)', 'type' => 'textarea' ),
					array( 'key' => 'selection', 'label' => 'Module selection', 'type' => 'modules' ),
					array( 'key' => 'special', 'label' => 'Anything unusual for their trade that is not on the list', 'type' => 'textarea' ),
				),
			),
			array(
				'key'   => 'web',
				'name'  => 'Website',
				'blurb' => 'Whether the build includes the public face, and what it has to do.',
				'client_blurb' => 'If a website is part of this, what it needs to do.',
				'fields' => array(
					array( 'key' => 'need_site', 'label' => 'Do they need a new website?', 'type' => 'select', 'options' => array( 'Yes — full redesign', 'Yes — but phase two', 'No — keep current site, connect it', 'Undecided' ) ),
					array( 'key' => 'site_goals', 'label' => 'What must the website do?', 'type' => 'checklist', 'options' => array( 'Take bookings / appointments', 'Capture and route leads', 'Show live inventory or availability', 'Take payments or deposits', 'Rank locally (per-city pages)', 'Per-service or per-product pages', 'Customer or member login', 'Publish content automatically' ) ),
					array( 'key' => 'pages', 'label' => 'Pages they need (and roughly how many)', 'type' => 'textarea' ),
				),
			),
			array(
				'key'   => 'content',
				'name'  => 'Content and branding',
				'blurb' => 'What they already have, and what AdaptExec would need to create. This changes the quote more than people expect.',
				'client_blurb' => 'Logo, photos and words: what you have ready and what you would like help with.',
				'fields' => array(
					array( 'key' => 'logo', 'label' => 'Do they have a logo?', 'type' => 'select', 'options' => array( 'Yes, and happy with it', 'Yes, but it needs a refresh', 'No, they need one' ) ),
					array( 'key' => 'brand_assets', 'label' => 'Brand colors, fonts and photography they already have', 'type' => 'textarea' ),
					array( 'key' => 'copy', 'label' => 'Who writes the words?', 'type' => 'select', 'options' => array( 'They have it written', 'They will write it with guidance', 'They want AdaptExec to write it', 'Not sure' ) ),
					array( 'key' => 'photos', 'label' => 'Photos and video', 'type' => 'select', 'options' => array( 'They have good photos', 'They need a photographer or stock', 'Not sure' ) ),
					array( 'key' => 'photo_link', 'label' => 'Where are your photos? Share a Google Drive/Dropbox link, organized by service if possible', 'type' => 'text', 'help' => 'One shared folder with a subfolder for each service or type of work, plus team and headshots and your location, tells us what each picture shows. Descriptive file names help too (e.g. "hydrafacial-treatment-room-1.jpg"). Send the original, highest-resolution files, and before/after photos only with your clients’ consent.' ),
					array( 'key' => 'content_ready', 'label' => 'Anything else ready to use (forms, price lists, FAQs, documents)?', 'type' => 'textarea' ),
				),
			),
			array(
				'key'   => 'examples',
				'name'  => 'Examples they like',
				'blurb' => 'Sites, apps and styles they point to. One good example saves a round of design.',
				'client_blurb' => 'Websites or apps you like (or do not), and why. Links are perfect.',
				'fields' => array(
					array( 'key' => 'liked', 'label' => 'Websites or apps they like, and why', 'type' => 'textarea', 'essential' => true ),
					array( 'key' => 'competitors', 'label' => 'Competitors or sites they admire', 'type' => 'textarea' ),
					array( 'key' => 'style_picks', 'label' => 'Style Finder picks (link or style names)', 'type' => 'text', 'help' => 'From adaptexec.com/website-styles/, the Style finder on the lead, or the style step on their questionnaire link.' ),
					array( 'key' => 'dislikes', 'label' => 'Anything they definitely do not want', 'type' => 'textarea' ),
				),
			),
			array(
				'key'   => 'decision',
				'name'  => 'Timeline, budget and decision',
				'blurb' => 'The part that decides whether this is a deal or a nice conversation.',
				'client_blurb' => 'Timing, a rough budget range and who decides. A range is fine; it only helps us suggest the right phase one.',
				'fields' => array(
					array( 'key' => 'why_now', 'label' => 'Why now? What changes if they do nothing?', 'type' => 'textarea', 'essential' => true, 'help' => 'If there is no answer here, it is not a deal yet.' ),
					array( 'key' => 'go_live', 'label' => 'Target launch date or deadline', 'type' => 'text', 'essential' => true ),
					array( 'key' => 'urgency', 'label' => 'What is driving the timeline?', 'type' => 'select', 'options' => array( 'Renewal or price increase', 'Busy season approaching', 'Growth / new location', 'A system just failed them', 'No particular urgency' ) ),
					array( 'key' => 'budget_range', 'label' => 'Budget range', 'type' => 'select', 'essential' => true, 'options' => array( 'Under $1,500', '$1,500 – $3,000', '$3,000 – $7,500', '$7,500 – $15,000', '$15,000+', 'Not sure yet' ) ),
					array( 'key' => 'budget_state', 'label' => 'Budget', 'type' => 'select', 'options' => array( 'Budget already allocated', 'Willing to reallocate current software spend', 'New spend — needs justification', 'No budget discussed yet' ) ),
					array( 'key' => 'decision_makers', 'label' => 'Who signs, and who else must agree?', 'type' => 'textarea', 'essential' => true ),
					array( 'key' => 'objections', 'label' => 'Concerns they raised', 'type' => 'textarea' ),
				),
			),
			axh_discovery_onsite_section(),
			array(
				'key'      => 'rep',
				'name'     => 'Rep read',
				'audience' => 'internal',
				'blurb'    => 'Your judgement. This is for AdaptExec only and is never shown to the client.',
				'fields'   => array(
					array( 'key' => 'summary', 'label' => 'In two sentences — what should we build and why will they buy?', 'type' => 'textarea' ),
					array( 'key' => 'quote_notes', 'label' => 'Anything Adam should know before he quotes it', 'type' => 'textarea', 'help' => 'Open questions, what you promised to find out, anything said in a meeting that is not written above.' ),
					array( 'key' => 'confidence', 'label' => 'How likely is this to close?', 'type' => 'rating' ),
					array( 'key' => 'risks', 'label' => 'What could kill this deal?', 'type' => 'textarea' ),
					array( 'key' => 'phase_one', 'label' => 'If we had to ship one thing first, what would change their life?', 'type' => 'textarea' ),
				),
			),
		),
		$industry
	);
}

/**
 * The optional on-site workflow study (1.7.1). Adam can travel to a client
 * and watch the operation for a few days before designing a CRM or
 * project-management system. It is an add-on, priced per job, so the
 * section is optional: it never counts against completeness or quote
 * readiness. Every questionnaire carries it, the trade packets included.
 */
function axh_discovery_onsite_section() {
	return array(
		'key'      => 'onsite',
		'name'     => 'On-site workflow study (optional add-on)',
		'optional' => true,
		'blurb'    => 'Optional. For CRM, project-management and custom software builds, Adam can travel to the business and watch the operation for a few days, then design the system around how the work really flows. It is an additional cost, quoted separately, covering travel, lodging and time. Mark their interest and Adam adds it to the proposal as an option.',
		'client_blurb' => 'Optional. For a CRM, project-management system or custom software, Adam Neuerburg, AdaptExec’s founder, can come to you and watch your operation for a few days to see how the work really flows, so the system is built around it. It is an additional cost, quoted separately, that covers travel, lodging and time. Let us know if you are interested and we will include it in your proposal as an option.',
		'fields'   => array(
			array( 'key' => 'interest', 'label' => 'Interested in an on-site workflow study?', 'type' => 'select', 'options' => array( 'Yes', 'Maybe', 'No' ) ),
			array( 'key' => 'dates', 'label' => 'Preferred dates or time of year', 'type' => 'text' ),
			array( 'key' => 'days', 'label' => 'How long?', 'type' => 'select', 'options' => array( '1 day', '2 days', '3 days', 'More than 3 days', 'Not sure' ) ),
			array( 'key' => 'location', 'label' => 'Location (city and state of the main site)', 'type' => 'text' ),
			array( 'key' => 'observe', 'label' => 'Departments or processes to observe', 'type' => 'textarea' ),
			array( 'key' => 'notes', 'label' => 'Notes (busy days, safety rules, who will host)', 'type' => 'textarea' ),
		),
	);
}

/** Packets (trade questionnaires) get the on-site section too, before any internal pages. */
function axh_discovery_with_onsite( $schema ) {
	foreach ( $schema as $sec ) {
		if ( isset( $sec['key'] ) && 'onsite' === $sec['key'] ) {
			return $schema;
		}
	}
	$at = count( $schema );
	foreach ( $schema as $i => $sec ) {
		if ( isset( $sec['audience'] ) && 'internal' === $sec['audience'] ) {
			$at = $i;
			break;
		}
	}
	array_splice( $schema, $at, 0, array( axh_discovery_onsite_section() ) );
	return $schema;
}

/**
 * Answers saved before 1.7.1 under a section that has since moved.
 * section.key (old) => section.key (new). Only fills an empty new home.
 */
function axh_discovery_migrate_payload( $payload ) {
	if ( ! is_array( $payload ) ) {
		return $payload;
	}
	$moves = array( 'modules.roles' => 'team.roles', 'web.brand_assets' => 'content.brand_assets', 'web.competitors' => 'examples.competitors' );
	foreach ( $moves as $from => $to ) {
		list( $fs, $fk ) = explode( '.', $from );
		list( $ts, $tk ) = explode( '.', $to );
		if ( isset( $payload[ $fs ][ $fk ] ) && '' !== $payload[ $fs ][ $fk ] && empty( $payload[ $ts ][ $tk ] ) ) {
			$payload[ $ts ][ $tk ] = $payload[ $fs ][ $fk ];
			unset( $payload[ $fs ][ $fk ] );
		}
	}
	return $payload;
}

/** Whether one answer counts as given. */
function axh_discovery_answered( $field, $value, $modules = array() ) {
	if ( 'modules' === ( $field['type'] ?? '' ) ) {
		return ! empty( $modules );
	}
	if ( is_array( $value ) ) {
		$n = 0;
		foreach ( $value as $v ) {
			if ( is_array( $v ) ? implode( '', array_map( 'strval', $v ) ) !== '' : '' !== trim( (string) $v ) ) {
				$n++;
			}
		}
		if ( $n ) {
			return true;
		}
	} elseif ( '' !== trim( (string) $value ) ) {
		return true;
	}
	return ! empty( $field['or_modules'] ) && ! empty( $modules );
}

/**
 * Quote readiness (1.7.1): how much of what Adam needs to price the work
 * without a call is answered. Where the questionnaire marks key questions
 * (the general one does), readiness is the share of those answered and
 * "Request a quote" unlocks at 70%. A trade packet without key questions
 * falls back to the share of all questions asked, unlocking at 50%.
 * Optional sections (the on-site study) and internal pages never count.
 *
 * @param array $schema  Visible schema (already trimmed).
 * @param array $payload Answers.
 * @param array $modules Module picks.
 * @return array pct, filled, total, threshold, basis (key|all), ready, missing (labels).
 */
function axh_discovery_readiness( $schema, $payload, $modules = array() ) {
	$key = array();
	$all = array();
	foreach ( (array) $schema as $sec ) {
		if ( ! empty( $sec['optional'] ) || ( isset( $sec['audience'] ) && 'internal' === $sec['audience'] ) ) {
			continue;
		}
		foreach ( $sec['fields'] as $f ) {
			$v   = $payload[ $sec['key'] ][ $f['key'] ] ?? '';
			$row = array( 'label' => wp_strip_all_tags( html_entity_decode( $f['label'] ) ), 'section' => wp_strip_all_tags( html_entity_decode( $sec['name'] ) ), 'ok' => axh_discovery_answered( $f, $v, $modules ) );
			$all[] = $row;
			if ( ! empty( $f['essential'] ) ) {
				$key[] = $row;
			}
		}
	}
	$basis = $key ? 'key' : 'all';
	$set   = $key ? $key : $all;
	$total = count( $set );
	$done  = count( array_filter( $set, function ( $r ) { return $r['ok']; } ) );
	$pct   = $total ? (int) round( $done / $total * 100 ) : 0;
	$need  = (int) apply_filters( 'axh_quote_threshold', 'key' === $basis ? 70 : 50, $basis );
	$miss  = array();
	foreach ( $set as $r ) {
		if ( ! $r['ok'] ) {
			$miss[] = $r['label'];
		}
	}
	return array(
		'pct'       => $pct,
		'filled'    => $done,
		'total'     => $total,
		'threshold' => $need,
		'basis'     => $basis,
		'ready'     => $total > 0 && $pct >= $need,
		'missing'   => array_slice( $miss, 0, 12 ),
	);
}

/**
 * Sections a rep has switched off, and questions inside them, are not asked.
 *
 * A packet runs to 56 sections. Sending all of it to a two-bay shop is how a
 * form goes unanswered, so the rep trims it and the trim travels with the
 * record — to the progress figure, to the client's link, and to the brief.
 *
 * @param array $schema Full schema.
 * @param array $config {sections: {key: false}, questions: {key: true}}.
 * @param bool  $client True to also drop the sections meant only for us.
 * @return array
 */
function axh_discovery_visible( $schema, $config = array(), $client = false ) {
	$off_s = isset( $config['sections'] ) && is_array( $config['sections'] ) ? $config['sections'] : array();
	$off_q = isset( $config['questions'] ) && is_array( $config['questions'] ) ? $config['questions'] : array();
	$out   = array();

	foreach ( $schema as $sec ) {
		if ( isset( $off_s[ $sec['key'] ] ) && false === $off_s[ $sec['key'] ] ) {
			continue;
		}
		if ( $client && isset( $sec['audience'] ) && 'internal' === $sec['audience'] ) {
			continue;
		}
		$fields = array();
		foreach ( $sec['fields'] as $f ) {
			if ( empty( $off_q[ $f['key'] ] ) ) {
				$fields[] = $f;
			}
		}
		if ( $fields ) {
			$sec['fields'] = $fields;
			$out[]         = $sec;
		}
	}

	return $out;
}

/* ------------------------------------------------------------ tool results */

/**
 * How long a free-text form answer may be. The "biggest headache" box also
 * carries the results summaries our public tools send (SEO checker, Website
 * Planner, ...), so it gets room for those; everything else stays short.
 */
function axh_answer_limit( $key ) {
	$long = apply_filters( 'axh_long_answer_keys', array( 'headache', 'goals', 'notes', 'message' ) );
	return (int) apply_filters( 'axh_answer_limit', in_array( $key, $long, true ) ? 10000 : 2000, $key );
}

/**
 * Clean the optional answers.tool_results value sent by a lead-capture tool.
 *
 * Accepts a JSON string (up to 20 KB) or an already-decoded object. Keeps the
 * order and nesting (up to 4 levels, 400 values), turns every key and value
 * into plain text, and returns null when there is nothing usable. Text that is
 * not JSON is kept as a single "Results" entry rather than thrown away.
 */
function axh_tool_results_clean( $raw ) {
	if ( null === $raw || '' === $raw || array() === $raw ) {
		return null;
	}
	if ( is_string( $raw ) ) {
		$raw = trim( $raw );
		if ( strlen( $raw ) > 20480 ) {
			return null;
		}
		$dec = json_decode( $raw, true );
		if ( ! is_array( $dec ) ) {
			$txt = sanitize_textarea_field( mb_substr( $raw, 0, 5000 ) );
			return '' === $txt ? null : array( 'Results' => $txt );
		}
		$raw = $dec;
	} elseif ( is_object( $raw ) ) {
		$raw = json_decode( wp_json_encode( $raw ), true );
	}
	if ( ! is_array( $raw ) ) {
		return null;
	}
	$budget = 400;
	$out    = axh_tool_results_walk( $raw, 0, $budget );
	return $out ? $out : null;
}

/** @internal Recursive part of axh_tool_results_clean(). */
function axh_tool_results_walk( $node, $depth, &$budget ) {
	$is_list = array_keys( $node ) === range( 0, count( $node ) - 1 );
	$out     = array();
	foreach ( $node as $k => $v ) {
		if ( $budget <= 0 ) {
			break;
		}
		if ( is_array( $v ) ) {
			if ( $depth >= 3 ) {
				// Too deep to show tidily: keep it as one line of text.
				$v = mb_substr( wp_json_encode( $v ), 0, 500 );
			} else {
				$v = axh_tool_results_walk( $v, $depth + 1, $budget );
				if ( ! $v ) {
					continue;
				}
			}
		}
		if ( null === $v || '' === $v ) {
			continue;
		}
		if ( is_bool( $v ) ) {
			$v = $v ? 'Yes' : 'No';
		} elseif ( is_int( $v ) || is_float( $v ) ) {
			$v = is_finite( (float) $v ) ? $v : null;
		} elseif ( is_string( $v ) ) {
			$v = sanitize_textarea_field( mb_substr( $v, 0, 2000 ) );
		}
		if ( null === $v || '' === $v ) {
			continue;
		}
		--$budget;
		if ( $is_list ) {
			$out[] = $v;
		} else {
			$key = mb_substr( sanitize_text_field( (string) $k ), 0, 80 );
			if ( '' === $key ) {
				continue;
			}
			$out[ $key ] = $v;
		}
	}
	return $out;
}

/** Tool results as plain "Label: value" lines, for emails and the timeline. */
function axh_tool_results_lines( $node, $prefix = '' ) {
	$lines   = array();
	$node    = (array) $node;
	$is_list = array_keys( $node ) === range( 0, count( $node ) - 1 );
	if ( $is_list && ! array_filter( $node, 'is_array' ) ) {
		return array( ( '' !== $prefix ? $prefix . ': ' : '' ) . implode( '; ', array_map( 'strval', $node ) ) );
	}
	foreach ( $node as $k => $v ) {
		$label = $is_list ? ( '' !== $prefix ? $prefix . ' ' : '' ) . '#' . ( $k + 1 ) : ( '' !== $prefix ? $prefix . ' › ' : '' ) . $k;
		if ( is_array( $v ) ) {
			$lines = array_merge( $lines, axh_tool_results_lines( $v, $label ) );
		} else {
			$lines[] = $label . ': ' . $v;
		}
	}
	return $lines;
}

/* ------------------------------------------------------------------ styles */

/** The website style library (styles, quiz, moods, fonts). */
function axh_style_library() {
	static $lib = null;
	if ( null === $lib ) {
		$lib = json_decode( axh_style_library_json(), true );
		if ( ! is_array( $lib ) ) {
			$lib = array( 'styles' => array(), 'quiz' => array(), 'moods' => array() );
		}
	}
	return apply_filters( 'axh_style_library', $lib );
}

/** slug => style. */
function axh_styles_by_slug() {
	$out = array();
	foreach ( axh_style_library()['styles'] as $s ) {
		$out[ $s['slug'] ] = $s;
	}
	return $out;
}

/** Style Finder ranking for a set of quiz answers: [ [slug, pct], ... ]. */
function axh_style_score( $answers ) {
	$lib = axh_style_library();
	$out = array();
	foreach ( $lib['styles'] as $i => $s ) {
		$sc = 0;
		$mx = 0;
		foreach ( $lib['quiz'] as $q ) {
			$a = $answers[ $q['id'] ] ?? '';
			if ( ! $a ) {
				continue;
			}
			$mx += 3 * $q['weight'];
			$sc += ( (float) ( $s['w'][ $q['id'] ][ $a ] ?? 0 ) ) * $q['weight'];
		}
		$out[] = array( 'slug' => $s['slug'], 'pct' => $mx ? (int) round( $sc / $mx * 100 ) : 0, 'i' => $i );
	}
	usort( $out, function ( $a, $b ) { return ( $b['pct'] - $a['pct'] ) ?: ( $a['i'] - $b['i'] ); } );
	return array_map( function ( $r ) { return array( 'slug' => $r['slug'], 'pct' => $r['pct'] ); }, $out );
}

/**
 * Normalise style picks from any source. Unknown styles, questions and answers
 * are dropped; the match percentages are worked out here, not trusted from
 * the browser. Returns null when there is nothing to keep.
 *
 * @param mixed  $in     array( shortlist, answers, notes ) or a list / comma string of slugs.
 * @param string $source rep | client | discovery | form.
 */
function axh_style_clean( $in, $source = 'rep' ) {
	$by = axh_styles_by_slug();
	if ( is_string( $in ) ) {
		$in = array( 'shortlist' => preg_split( '/[\s,]+/', $in ) );
	} elseif ( is_array( $in ) && isset( $in[0] ) ) {
		$in = array( 'shortlist' => $in );
	}
	$in      = (array) $in;
	$short   = array();
	$answers = array();
	foreach ( (array) ( $in['shortlist'] ?? array() ) as $slug ) {
		$slug = sanitize_key( (string) $slug );
		if ( isset( $by[ $slug ] ) && ! in_array( $slug, $short, true ) ) {
			$short[] = $slug;
		}
	}
	$short = array_slice( $short, 0, 6 );
	$given = is_array( $in['answers'] ?? null ) ? $in['answers'] : array();
	foreach ( axh_style_library()['quiz'] as $q ) {
		$a = sanitize_key( (string) ( $given[ $q['id'] ] ?? '' ) );
		if ( $a && in_array( $a, wp_list_pluck( $q['opts'], 'v' ), true ) ) {
			$answers[ $q['id'] ] = $a;
		}
	}
	$notes = mb_substr( sanitize_textarea_field( (string) ( $in['notes'] ?? '' ) ), 0, 2000 );
	if ( ! $short && ! $answers && '' === $notes ) {
		return null;
	}
	return array(
		'shortlist' => $short,
		'answers'   => (object) $answers,
		'top'       => $answers ? array_slice( axh_style_score( $answers ), 0, 3 ) : array(),
		'notes'     => $notes,
		'source'    => in_array( $source, array( 'rep', 'client', 'discovery', 'form' ), true ) ? $source : 'rep',
	);
}

/** "Quiet Minimal, Swiss Grid": the shortlist, or the top matches if nothing was shortlisted. */
function axh_style_names( $picks, $with_pct = false ) {
	$by    = axh_styles_by_slug();
	$picks = (array) $picks;
	$pct   = array();
	foreach ( (array) ( $picks['top'] ?? array() ) as $t ) {
		$t = (array) $t;
		$pct[ $t['slug'] ] = $t['pct'];
	}
	$list = ! empty( $picks['shortlist'] ) ? (array) $picks['shortlist'] : array_keys( $pct );
	$out  = array();
	foreach ( $list as $slug ) {
		if ( isset( $by[ $slug ] ) ) {
			$out[] = $by[ $slug ]['name'] . ( $with_pct && isset( $pct[ $slug ] ) ? ' (' . $pct[ $slug ] . '% match)' : '' );
		}
	}
	return implode( ', ', $out );
}

/** The picker's CSS, data and script, inline. Printed only where a picker is used. */
function axh_style_assets() {
	return '<style id="axh-style-css">' . axh_style_css() . "</style>\n" .
		'<script>window.AXStyleData = ' . wp_json_encode( axh_style_library() ) . ";</script>\n" .
		'<script>' . axh_style_js() . "</script>\n";
}

// <axh-styles>
/*
 * Website Style Finder: the 19 styles from adaptexec.com/website-styles/
 * (data 2026-10-14), their mini-mockup CSS, and the shared picker widget.
 * Generated by scripts/build_styles.py from adaptexec-styles.json — to add or
 * change a style, edit the JSON section below (it is plain JSON).
 */
function axh_style_library_json() {
	return <<<'AXH_STYLE_JSON'
{"version":1,"updated":"2026-10-28","source":"https://adaptexec.com/website-styles/","styles":[{"slug":"minimal","number":1,"name":"Quiet Minimal","vibe":"Lots of white space, a few perfect details and nothing extra.","feels_like":["Calm","Confident","Considered"],"best_for":["Architects & interior designers","Boutique consultants","Photographers","Premium products","Wellness studios"],"watch_outs":"Needs strong photos and sharp copy. With nothing to hide behind, weak content shows, and too sparse can read as unfinished.","palette":["#fafaf7","#141414","#d8d4cc","#8b877f"],"fonts":{"display":"Hanken Grotesk","body":"Hanken Grotesk"},"moods":["calm","premium"],"demo":{"name":"Nacrelle Aesthetics","url":"https://adaptexec.com/demos/nacrelle-aesthetics/"},"demo_url":"https://adaptexec.com/demos/nacrelle-aesthetics/","domain":"stillandco.com","w":{"feel":{"calm":3,"premium":2,"trust":1},"color":{"neutral":3,"classic":1},"type":{"sans":3,"serif":1},"density":{"airy":3},"motion":{"subtle":3,"smooth":1},"aud":{"luxe":2,"pro":2},"ind":{"retail":2,"finance":1,"health":1,"tech":1}},"mockup_html":"<div class=\"axm axm-minimal\"><div class=\"n\"><span class=\"lg\">still&amp;co.</span><span class=\"ln\"><i>Shop</i><i>Studio</i><i>Journal</i></span><span class=\"ct\">Cart (0)</span></div><div class=\"tx\"><span class=\"ey\">New &mdash; autumn glazes</span><span class=\"h\">Objects for<br>slow mornings.</span><span class=\"p\">Hand-thrown stoneware, made in small batches and meant to be used every day.</span><span class=\"lk\">Shop the collection <b>&rarr;</b></span></div><div class=\"art\"><span class=\"sh\"></span><span class=\"pl\"></span><span class=\"vs\"></span><span class=\"cp\"></span></div><span class=\"ft\">01 / 04</span></div>"},{"slug":"swiss","number":2,"name":"Swiss Grid","vibe":"Strict grids, big confident type and one sharp accent color.","feels_like":["Organized","Modern","Decisive"],"best_for":["Real estate","Architecture & engineering","Agencies","B2B services","Listings & data-heavy sites"],"watch_outs":"Can feel cold without warm photography, and the grid has to be respected on every page or it falls apart.","palette":["#ffffff","#111111","#e8332a","#efefea"],"fonts":{"display":"Instrument Sans","body":"Geist Mono"},"moods":["bold","trust"],"demo":{"name":"Halvorne Realty","url":"https://adaptexec.com/demos/halvorne/"},"demo_url":"https://adaptexec.com/demos/halvorne/","domain":"nordgrid.studio","w":{"feel":{"bold":2,"trust":2,"calm":1,"premium":1},"color":{"neutral":3,"bright":1},"type":{"sans":3,"mono":1},"density":{"balanced":3,"packed":2},"motion":{"smooth":2,"subtle":1},"aud":{"pro":3,"luxe":1},"ind":{"finance":3,"tech":2,"trades":1}},"mockup_html":"<div class=\"axm axm-swiss\"><span class=\"gr\"></span><div class=\"n\"><span class=\"lg\">Nordgrid</span><span class=\"ln\"><i><b>01</b> Work</i><i><b>02</b> Studio</i><i><b>03</b> Contact</i></span></div><span class=\"h\">Form<br>follows<br>the brief<em>.</em></span><div class=\"sq\"><b>N&deg;24</b><span>Selected<br>projects</span></div><div class=\"ph\"><span></span></div><div class=\"cols\"><span><b>Index</b>Architecture</span><span><b>Projects</b>128 built</span><span><b>Since</b>2009</span><span class=\"cta\">View work &rarr;</span></div></div>"},{"slug":"editorial","number":3,"name":"Classic Editorial","vibe":"Elegant serif headlines, generous margins and the quiet authority of a printed page.","feels_like":["Established","Trustworthy","Refined"],"best_for":["Law firms","Financial advisors","Consultancies","Publishers","Private schools"],"watch_outs":"Leans formal, so it can feel stuffy for young or casual brands. Serif body text needs careful sizing on phones.","palette":["#f6f1e8","#1a1f2b","#8a6a3b","#ffffff"],"fonts":{"display":"Libre Caslon Display","body":"Source Sans 3"},"moods":["trust","premium","calm"],"demo":{"name":"Ellery & Vantine","url":"https://adaptexec.com/demos/ellery-vantine/"},"demo_url":"https://adaptexec.com/demos/ellery-vantine/","domain":"whitlockhale.law","w":{"feel":{"trust":3,"premium":2,"calm":2},"color":{"classic":3,"neutral":2},"type":{"serif":3},"density":{"airy":2,"balanced":2},"motion":{"subtle":3,"smooth":1},"aud":{"pro":3,"luxe":2},"ind":{"finance":3,"church":1,"health":1}},"mockup_html":"<div class=\"axm axm-editorial\"><div class=\"n\"><span class=\"mk\">W&amp;H</span><span class=\"lg\"><span>Whitlock <i>&amp;</i> Hale</span><small>Counsel &middot; Est. 1998</small></span><span class=\"ln\"><i>Practice</i><i>People</i><i>Insights</i></span><span class=\"bt\">Consultation</span></div><span class=\"rl\"></span><div class=\"tx\"><span class=\"ey\">Families &middot; Founders &middot; Estates</span><span class=\"h\">Considered counsel for what <em>matters most.</em></span><span class=\"p\">Plain-English advice, fees agreed in writing and a partner who answers the phone.</span><span class=\"cta\">Book a consultation</span><span class=\"sec\">Our approach &rarr;</span></div><div class=\"arch\"><span class=\"sun\"></span><span class=\"hill\"></span><span class=\"hill b\"></span></div><span class=\"cap\">Vol. XXVI &mdash; Autumn letter</span></div>"},{"slug":"luxury","number":4,"name":"Soft Luxury Magazine","vibe":"Fashion-magazine layouts with oversized serif type, moody photos and whispered details.","feels_like":["Indulgent","Intimate","High-end"],"best_for":["Salons & med spas","Boutique hotels","Fashion & jewelry","Wedding pros","Fine dining"],"watch_outs":"Lives or dies on photography. Thin type and low-contrast text need care to stay readable and accessible.","palette":["#1d1714","#efe6dc","#c8a27a","#8e7466"],"fonts":{"display":"Cormorant Garamond","body":"Jost"},"moods":["premium","calm"],"demo":{"name":"Maison Ondelle","url":"https://adaptexec.com/demos/maison-ondelle/"},"demo_url":"https://adaptexec.com/demos/maison-ondelle/","domain":"atelierrose.com","w":{"feel":{"premium":3,"calm":2,"warm":1},"color":{"dark":2,"earthy":2,"neutral":1,"pastel":1},"type":{"serif":3},"density":{"airy":3},"motion":{"smooth":3,"subtle":1},"aud":{"luxe":3,"young":1},"ind":{"retail":3,"health":2,"food":1}},"mockup_html":"<div class=\"axm axm-luxury\"><span class=\"glow\"></span><span class=\"sho\"></span><span class=\"hair\"></span><div class=\"n\"><span class=\"lg\">Atelier Ros&eacute;</span><span class=\"ln\"><i>Rituals</i><i>Artists</i><i>Journal</i></span><span class=\"bt\">Book a visit</span></div><span class=\"big\">Ros&eacute;.</span><span class=\"iss\">N&ordm; 12 &middot; <em>The spring issue</em></span><div class=\"col\"><b>Inside &mdash; p.14</b><em>The forty-minute ritual</em></div><span class=\"h\">Beauty,<br><em>slowly.</em></span><span class=\"pill\">Reserve a chair</span></div>"},{"slug":"corporate","number":5,"name":"Clean Corporate","vibe":"Clear structure, reassuring blues and proof points right up front.","feels_like":["Dependable","Clear","Professional"],"best_for":["Financial services","Insurance","Healthcare groups","B2B software","Staffing firms"],"watch_outs":"Easy to drift into generic and forgettable. It needs a distinct accent, real photos and specific claims to stand out.","palette":["#0f2a4a","#1f6feb","#eaf2fb","#19a974"],"fonts":{"display":"Plus Jakarta Sans","body":"Plus Jakarta Sans"},"moods":["trust","calm"],"demo":{"name":"Haverline Insurance & Advisory","url":"/demos/haverline/"},"demo_url":"/demos/haverline/","domain":"northbeamfinancial.com","w":{"feel":{"trust":3,"calm":2},"color":{"classic":2,"neutral":2,"bright":1},"type":{"sans":3},"density":{"balanced":3,"packed":2},"motion":{"smooth":2,"subtle":2},"aud":{"pro":3,"community":1,"homeowners":1},"ind":{"finance":3,"health":2,"tech":2,"trades":1}},"mockup_html":"<div class=\"axm axm-corporate\"><div class=\"n\"><span class=\"lg\"><b></b>Northbeam<small>Financial</small></span><span class=\"ln\"><i>Planning</i><i>Retirement</i><i>About</i></span><span class=\"lo\">Client login</span><span class=\"bt\">Get started</span></div><div class=\"tx\"><span class=\"rt\"><b>&#9733;&#9733;&#9733;&#9733;&#9733;</b> 4.9 from 1,200+ clients</span><span class=\"h\">Plan with clarity.<br>Retire with <em>confidence.</em></span><span class=\"p\">Fee-only fiduciary advisors who build a plan around your life, not a product.</span><span class=\"b1\">Get my free plan</span><span class=\"b2\">How it works</span><div class=\"tr\"><span>Fiduciary</span><span>25 years</span><span>$2.1B guided</span></div></div><div class=\"card\"><span class=\"cl\">Retirement readiness</span><div class=\"ring\"><b>86%</b></div><div class=\"bars\"><i style=\"height:38%\"></i><i style=\"height:52%\"></i><i style=\"height:47%\"></i><i style=\"height:66%\"></i><i style=\"height:74%\"></i><i style=\"height:88%\"></i></div><span class=\"up\">&#9650; 12.4% this year</span></div></div>"},{"slug":"glass","number":6,"name":"Airy Glass","vibe":"Frosted, see-through panels floating over soft pastel gradients.","feels_like":["Light","Gentle","Modern"],"best_for":["Dental & medical","Wellness & therapy","Fintech apps","Skincare","Modern clinics"],"watch_outs":"Blur effects can strain older phones and text over gradients can lose contrast, so we use glass as an accent, not everywhere.","palette":["#eef6fd","#5ab6f2","#c9b8ff","#0f2540"],"fonts":{"display":"Sora","body":"Manrope"},"moods":["calm","trust"],"demo":{"name":"Glasswing Dental","url":"https://adaptexec.com/demos/glasswing-dental/"},"demo_url":"https://adaptexec.com/demos/glasswing-dental/","domain":"lumenwellness.care","w":{"feel":{"calm":3,"trust":2,"premium":1,"playful":1},"color":{"pastel":3,"bright":1},"type":{"sans":3},"density":{"airy":3,"balanced":1},"motion":{"smooth":3},"aud":{"community":2,"young":2,"pro":1},"ind":{"health":3,"tech":2,"retail":1,"finance":1}},"mockup_html":"<div class=\"axm axm-glass\"><span class=\"b1\"></span><span class=\"b2\"></span><span class=\"b3\"></span><div class=\"n\"><span class=\"lg\"><b></b>Lumen</span><span class=\"ln\"><i>Care</i><i>Pricing</i><i>Team</i></span><span class=\"bt\">Book a visit</span></div><div class=\"tx\"><span class=\"ey\">Family &amp; gentle care</span><span class=\"h\">Care that feels<br><em>light as air.</em></span><span class=\"p\">Everything explained first, prices up front and early visits that fit your day.</span><span class=\"cta\">Book a visit</span><span class=\"sec\">Meet the team</span></div><div class=\"g g1\"><span class=\"dot\"></span><b>Next opening</b><small>Tue &middot; 7:00 am</small></div><div class=\"g g2\"><span class=\"ic\"></span><b>No surprise bills</b><small>Price confirmed before we begin</small></div><div class=\"g g3\"><b>4.9</b><small>patient rating</small></div></div>"},{"slug":"organic","number":7,"name":"Warm Organic & Hand-Drawn","vibe":"Earthy tones, soft curves, handwritten notes and little doodles that feel human.","feels_like":["Welcoming","Personal","Grounded"],"best_for":["Churches & ministries","Nonprofits","Bakeries & cafes","Farms & makers","Counselors & family businesses"],"watch_outs":"Doodles and script fonts are seasoning, not the meal. Overdo them and it looks homemade, so navigation and buttons stay crisp.","palette":["#f6efe4","#b85c38","#2f3b2a","#d8c4a4"],"fonts":{"display":"Newsreader","body":"Nunito Sans","accent":"Caveat"},"moods":["warm","calm","playful"],"demo":{"name":"Cairnway Church","url":"https://adaptexec.com/demos/cairnway-church/"},"demo_url":"https://adaptexec.com/demos/cairnway-church/","domain":"hearthandfield.org","w":{"feel":{"warm":3,"calm":2,"playful":1},"color":{"earthy":3,"pastel":1},"type":{"hand":3,"serif":2},"density":{"balanced":2,"airy":2},"motion":{"subtle":2,"smooth":2},"aud":{"community":3,"homeowners":1},"ind":{"church":3,"food":2,"health":1,"retail":1}},"mockup_html":"<div class=\"axm axm-organic\"><div class=\"n\"><span class=\"lg\"><svg viewBox=\"0 0 24 24\"><path d=\"M12 21V9M12 13c-4 0-6-3-6-7 4 0 6 3 6 7Zm0-2c0-4 2-7 6-7 0 4-2 7-6 7Z\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.6\" stroke-linecap=\"round\"/></svg>Hearth &amp; Field</span><span class=\"ln\"><i>Our story</i><i>Gatherings</i><i>Give</i></span><span class=\"bt\">Plan a visit</span></div><div class=\"tx\"><span class=\"ey\">A community table since 1987</span><span class=\"h\">Come as you are,<br>stay for <em>supper.</em></span><svg class=\"ul\" viewBox=\"0 0 160 14\"><path d=\"M3 9c30-7 70-8 154-3\" fill=\"none\" stroke=\"#b85c38\" stroke-width=\"3\" stroke-linecap=\"round\"/></svg><span class=\"p\">Sunday gatherings, a Wednesday meal and a place for every age at the table.</span><span class=\"cta\">Plan your first visit</span></div><div class=\"arch\"><span class=\"sky\"></span><span class=\"sun\"></span><span class=\"h1\"></span><span class=\"h2\"></span><span class=\"h3\"></span></div><span class=\"note\">Sundays at 10 &mdash;<br>kids welcome!</span><svg class=\"arr\" viewBox=\"0 0 70 50\"><path d=\"M4 6c20 2 44 10 56 34m0 0-1-11m1 11-10-5\" fill=\"none\" stroke=\"#2f3b2a\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/></svg><svg class=\"spr\" viewBox=\"0 0 40 60\"><path d=\"M20 58V8M20 20c-8-2-12-8-12-14 8 1 12 7 12 14Zm0 14c8-2 12-8 12-14-8 1-12 7-12 14Zm0 12c-7-2-11-7-11-12 7 1 11 6 11 12Z\" fill=\"none\" stroke=\"#6b7f4e\" stroke-width=\"2\" stroke-linecap=\"round\"/></svg></div>"},{"slug":"flat","number":8,"name":"Flat & Friendly","vibe":"Bright solid colors, simple shapes and cheerful illustration. No shadows, no fuss.","feels_like":["Approachable","Clear","Upbeat"],"best_for":["Cleaning & home services","Pediatric & family care","Education","Consumer apps","Community programs"],"watch_outs":"Generic stock illustrations make it look like a template. A consistent, custom illustration style is what makes it work.","palette":["#ffffff","#2ec4b6","#ff9f1c","#1b2a49"],"fonts":{"display":"Poppins","body":"Poppins"},"moods":["playful","warm"],"demo":{"name":"Pippin & Pail Cleaning Co.","url":"/demos/pippin-pail/"},"demo_url":"/demos/pippin-pail/","domain":"tidynest.co","w":{"feel":{"playful":3,"warm":2,"trust":1},"color":{"bright":3,"pastel":1},"type":{"sans":2,"display":1},"density":{"balanced":3},"motion":{"smooth":2,"lively":1},"aud":{"community":3,"homeowners":2,"young":1},"ind":{"trades":2,"health":2,"church":2,"tech":1}},"mockup_html":"<div class=\"axm axm-flat\"><div class=\"n\"><span class=\"lg\"><b></b>TidyNest</span><span class=\"ln\"><i>Services</i><i>Pricing</i><i>Reviews</i></span><span class=\"bt\">Book a clean</span></div><div class=\"tx\"><span class=\"tag\">Trusted by 3,000+ homes</span><span class=\"h\">A spotless home,<br>without lifting<br>a <em>finger.</em></span><span class=\"cta\">Book in 60 seconds</span><span class=\"pr\">From <b>$89</b></span></div><div class=\"ill\"><span class=\"sun\"></span><span class=\"hill\"></span><span class=\"roof\"></span><span class=\"house\"><i class=\"d\"></i><i class=\"w\"></i><i class=\"w r\"></i></span><span class=\"tree\"></span><span class=\"trunk\"></span><span class=\"sp s1\"></span><span class=\"sp s2\"></span><span class=\"sp s3\"></span><span class=\"bkt\"></span></div></div>"},{"slug":"bento","number":9,"name":"Bento Grid","vibe":"Your story told in tidy tiles of different sizes, like a well-packed lunchbox.","feels_like":["Organized","Smart","Product-led"],"best_for":["Software & apps","Tech startups","Agencies","Product launches","Portfolios"],"watch_outs":"Too many tiles turns into noise. It needs one clear read-this-first tile and careful stacking on phones.","palette":["#f4f4f5","#18181b","#a3e635","#6366f1"],"fonts":{"display":"Geist","body":"Geist Mono"},"moods":["trust","bold","techy"],"demo":{"name":"Northlatch IT","url":"/demos/northlatch/"},"demo_url":"/demos/northlatch/","domain":"quanta.app","w":{"feel":{"trust":2,"bold":1,"playful":1,"calm":1},"color":{"neutral":3,"bright":1},"type":{"sans":3,"mono":2},"density":{"packed":3,"balanced":2},"motion":{"smooth":3},"aud":{"pro":2,"young":2},"ind":{"tech":3,"finance":1,"retail":1}},"mockup_html":"<div class=\"axm axm-bento\"><div class=\"n\"><span class=\"lg\"><b></b>quanta</span><span class=\"ln\"><i>Product</i><i>Pricing</i><i>Docs</i></span><span class=\"bt\">Try free</span></div><div class=\"grid\"><div class=\"t t1\"><span class=\"k\">Analytics for small teams</span><span class=\"h\">Know your numbers at a glance.</span><span class=\"cta\">Start free &rarr;</span></div><div class=\"t t2\"><span class=\"k\">Revenue</span><b>+38%</b><div class=\"br\"><i style=\"height:30%\"></i><i style=\"height:45%\"></i><i style=\"height:40%\"></i><i style=\"height:62%\"></i><i style=\"height:58%\"></i><i style=\"height:80%\"></i><i style=\"height:95%\"></i></div></div><div class=\"t t3\"><span class=\"ring\"></span><span class=\"k\">Goal</span><b>72%</b></div><div class=\"t t4\"><span class=\"av\"><i></i><i></i><i></i><i></i></span><span>2,400 teams</span></div><div class=\"t t5\"><code>GET /v1/metrics</code><code class=\"ok\">200 OK &middot; 41ms</code></div></div></div>"},{"slug":"dark","number":10,"name":"Dark Mode Tech","vibe":"Deep backgrounds, glowing gradients and crisp light type.","feels_like":["Futuristic","Sleek","Powerful"],"best_for":["Tech & AI products","Gaming","Nightlife","Audio & video studios","Automotive"],"watch_outs":"Long reading on dark backgrounds is tiring. Glows and gradients need restraint, and contrast has to be checked carefully.","palette":["#07070c","#7c5cff","#22d3ee","#e5e7eb"],"fonts":{"display":"Space Grotesk","body":"JetBrains Mono"},"moods":["bold","premium","techy"],"demo":{"name":"Obsidra Detail Lab","url":"/demos/obsidra/"},"demo_url":"/demos/obsidra/","domain":"vectra.dev","w":{"feel":{"bold":3,"premium":2},"color":{"dark":3,"bright":1},"type":{"sans":2,"mono":3},"density":{"balanced":2,"packed":1},"motion":{"lively":2,"smooth":2},"aud":{"young":3,"pro":1},"ind":{"tech":3,"food":1,"retail":1}},"mockup_html":"<div class=\"axm axm-dark\"><span class=\"glo\"></span><span class=\"gl2\"></span><span class=\"grd\"></span><div class=\"n\"><span class=\"lg\"><b></b>vectra</span><span class=\"ln\"><i>Platform</i><i>Docs</i><i>Pricing</i></span><span class=\"bt\">Sign in</span></div><span class=\"pill\"><i></i> v2.0 is live</span><span class=\"h\">Ship faster with<br><em>intelligent</em> tooling.</span><span class=\"p\">Build, test and deploy from one quiet, very fast dashboard.</span><div class=\"bts\"><span class=\"b1\">Start free</span><span class=\"b2\">Read the docs</span></div><div class=\"term\"><div class=\"tb\"><i></i><i></i><i></i></div><code><b>$</b> vectra deploy --prod</code><code class=\"ok\">&#10003; built in 1.2s &middot; 14 regions live</code></div></div>"},{"slug":"soft-3d","number":11,"name":"Soft 3D","vibe":"Puffy, toy-like shapes with soft shadows you almost want to squeeze.","feels_like":["Playful","Tactile","Fun"],"best_for":["Kids & family brands","Apps","Pet care","Treats & ice cream","Events"],"watch_outs":"Needs consistent custom 3D artwork, and heavy 3D files must be optimized or pages slow down.","palette":["#f6ecff","#ff8fab","#7bdff2","#3d2c5e"],"fonts":{"display":"Fredoka","body":"Nunito"},"moods":["playful","warm"],"demo":{"name":"Puddlejump Pet Spa & Daycare","url":"/demos/puddlejump/"},"demo_url":"/demos/puddlejump/","domain":"puffinpets.com","w":{"feel":{"playful":3,"warm":2},"color":{"pastel":3,"bright":2},"type":{"display":2,"hand":1,"sans":1},"density":{"balanced":2,"airy":1},"motion":{"lively":3,"smooth":1},"aud":{"young":2,"community":2},"ind":{"retail":2,"tech":2,"health":1,"food":1}},"mockup_html":"<div class=\"axm axm-soft-3d\"><div class=\"n\"><span class=\"lg\"><b></b>Puffin</span><span class=\"ln\"><i>Walks</i><i>Sitting</i><i>Grooming</i></span><span class=\"bt\">Book</span></div><div class=\"tx\"><span class=\"h\">Happy pets,<br>happy <em>humans.</em></span><span class=\"p\">Walks, sitting and grooming by people who adore your animals.</span><span class=\"cta\">Book a walk</span></div><span class=\"s1\"></span><span class=\"s2\"></span><span class=\"s3\"></span><span class=\"tor\"></span><span class=\"pil\"></span><div class=\"card\"><span class=\"av\"></span><b>Next walk</b><small>Today &middot; 3:30 pm</small></div></div>"},{"slug":"neobrutalism","number":12,"name":"Neobrutalism","vibe":"Thick black outlines, hard offset shadows, loud color and sticker energy.","feels_like":["Loud","Fun","Confident"],"best_for":["Gyms & fitness","Youth brands","Creative studios","Food trucks","Startups with attitude"],"watch_outs":"Polarizing on purpose. Great for standing out, wrong for conservative audiences, and it takes discipline to look intentional instead of broken.","palette":["#f3efe2","#0b0b0b","#c6f432","#b9a5ff"],"fonts":{"display":"Archivo Black","body":"Space Mono"},"moods":["bold","playful"],"demo":{"name":"Boltyard","url":"https://adaptexec.com/demos/boltyard/"},"demo_url":"https://adaptexec.com/demos/boltyard/","domain":"gritclub.fit","w":{"feel":{"bold":3,"playful":3},"color":{"bright":3},"type":{"display":3,"mono":1},"density":{"packed":2,"balanced":2},"motion":{"lively":3},"aud":{"young":3,"community":1},"ind":{"tech":2,"food":2,"retail":1,"health":1}},"mockup_html":"<div class=\"axm axm-neobrutalism\"><div class=\"n\"><span class=\"lg\"><b>&#9889;</b>GRIT CLUB</span><span class=\"ln\"><i>Classes</i><i>Coaches</i><i>Plans</i></span><span class=\"bt\">Join now &rarr;</span></div><span class=\"ey\">&#9670; Strength &middot; HIIT &middot; Ride</span><span class=\"h\"><span>SWEAT NOW.</span><br><span class=\"hl\">BRAG LATER.</span></span><span class=\"p\">Coached classes from 5 am. Your first week is on us.</span><span class=\"cta\">Claim free week</span><span class=\"stk\">FIRST<br>CLASS<br>FREE</span><span class=\"star\">NEW!</span><div class=\"card\"><b>NEXT CLASS</b><span>6:00 AM &middot; HIIT 45</span><small>8 spots left</small></div></div>"},{"slug":"brutalism","number":13,"name":"Raw Brutalism","vibe":"Default fonts, visible structure and blunt links. Anti-design, very much on purpose.","feels_like":["Raw","Honest","Artsy"],"best_for":["Artists","Record labels","Galleries","Indie publishers","Design-savvy portfolios"],"watch_outs":"Wrong for almost any business that needs to look trustworthy to the general public, and usability has to stay excellent under the rough look.","palette":["#ffffff","#000000","#0000ee","#ffff00"],"fonts":{"display":"Times New Roman","body":"IBM Plex Mono"},"moods":["bold"],"demo":{"name":"Slabhouse Tattoo & Gallery","url":"/demos/slabhouse/"},"demo_url":"/demos/slabhouse/","domain":"deptofnoise.net","w":{"feel":{"bold":3},"color":{"neutral":2,"bright":1},"type":{"mono":3,"serif":1},"density":{"packed":3},"motion":{"subtle":2},"aud":{"young":3},"ind":{"tech":2,"retail":1}},"mockup_html":"<div class=\"axm axm-brutalism\"><div class=\"mq\">NEW RELEASE &#9733; NEW RELEASE &#9733; NEW RELEASE &#9733; NEW RELEASE &#9733; NEW RELEASE &#9733;</div><span class=\"h\">DEPT. OF <mark>NOISE</mark></span><span class=\"sub\">independent label est. 2011 / tucson az / no algorithm</span><div class=\"lks\"><u>&rarr; releases (24)</u><u>&rarr; tour dates</u><u>&rarr; shop</u><u>&rarr; mailing list</u></div><table><tr><td>11.02</td><td>PHOENIX</td><td>THE BASEMENT</td></tr><tr><td>11.09</td><td>TUCSON</td><td>CLUB 21</td></tr><tr><td>11.16</td><td>FLAGSTAFF</td><td>SOLD OUT</td></tr></table><span class=\"bt\">BUY VINYL</span><span class=\"ct\">[ visitors: 004213 ]</span></div>"},{"slug":"constructivism","number":14,"name":"Constructivist","vibe":"Diagonal layouts, heavy blocks of color and poster-style type that shouts with purpose.","feels_like":["Energetic","Decisive","Hard-working"],"best_for":["Roofing & construction","Events","Campaigns & advocacy","Sports","Bold local brands"],"watch_outs":"Angles and overlaps still have to stack cleanly on phones, and a little goes a long way.","palette":["#f2ead8","#d7261e","#141414","#ffb81c"],"fonts":{"display":"Unbounded","body":"Manrope"},"moods":["bold"],"demo":{"name":"Corvane Roofing","url":"https://adaptexec.com/demos/corvane-roofing/"},"demo_url":"https://adaptexec.com/demos/corvane-roofing/","domain":"forgeandbeam.build","w":{"feel":{"bold":3,"trust":1},"color":{"bright":2,"earthy":1,"dark":1},"type":{"display":3,"condensed":2},"density":{"balanced":2,"packed":1},"motion":{"lively":2,"smooth":1},"aud":{"homeowners":3,"community":2},"ind":{"trades":3,"church":1,"food":1}},"mockup_html":"<div class=\"axm axm-constructivism\"><span class=\"bar\"></span><span class=\"band\"></span><span class=\"circ\"></span><span class=\"tri\"></span><div class=\"n\"><span class=\"lg\">FORGE&amp;BEAM</span><span class=\"ln\"><i>Roofing</i><i>Framing</i><i>Projects</i></span></div><span class=\"h\">BUILT<br><em>TO</em><br>LAST.</span><span class=\"p\">Roofs and frames raised by one crew, inspected twice, warrantied for 25 years.</span><span class=\"cta\">Get a quote &rarr;</span><span class=\"num\">25<small>YR<br>WARRANTY</small></span></div>"},{"slug":"industrial","number":15,"name":"Rugged Industrial","vibe":"Condensed all-caps type, dark steel tones, hazard accents and spec-sheet details.","feels_like":["Tough","Capable","No-nonsense"],"best_for":["Auto repair","Fabrication & welding","Contractors","Logistics","Outdoor gear"],"watch_outs":"All caps and dark backgrounds slow reading, so body copy stays in sentence case and light enough to read easily.","palette":["#161616","#ff8a1e","#2b2b2b","#e2ddd2"],"fonts":{"display":"Oswald","body":"Inter"},"moods":["bold","trust"],"demo":{"name":"Tallgrade Auto","url":"https://adaptexec.com/demos/tallgrade/"},"demo_url":"https://adaptexec.com/demos/tallgrade/","domain":"ironsidefab.com","w":{"feel":{"bold":3,"trust":2},"color":{"dark":3,"earthy":1},"type":{"condensed":3,"mono":1},"density":{"packed":2,"balanced":2},"motion":{"smooth":2,"lively":1},"aud":{"homeowners":3,"pro":1},"ind":{"trades":3}},"mockup_html":"<div class=\"axm axm-industrial\"><span class=\"hz\"></span><div class=\"n\"><span class=\"lg\"><b></b>IRONSIDE<small>FABRICATION CO.</small></span><span class=\"ln\"><i>Capabilities</i><i>Shop</i><i>Quote</i></span><span class=\"ph\">(555) 010-0148</span></div><span class=\"ey\">// Steel &middot; Aluminum &middot; Custom</span><span class=\"h\">Built heavy.<br><em>Built right.</em></span><span class=\"p\">MIG, TIG and CNC work for contractors, ranches and shops. Most quotes back in 48 hours.</span><span class=\"cta\">Request a quote</span><div class=\"plate\"><i></i><i></i><i></i><i></i><span class=\"pl\">SPEC 07</span><b>32</b><span>YEARS ON<br>THE FLOOR</span><span class=\"cert\">ISO 9001 SHOP</span></div></div>"},{"slug":"blueprint","number":16,"name":"Blueprint Playful","vibe":"Grid paper, dashed guide lines and bright pops. Engineering with a grin.","feels_like":["Smart","Handy","Upbeat"],"best_for":["Plumbing & HVAC","Electricians","Engineering firms","Makers","STEM education"],"watch_outs":"The technical motif should support the message, not clutter it, so the grid stays subtle behind text.","palette":["#0f2d5c","#3dd6d8","#ff8a3d","#ffffff"],"fonts":{"display":"Bricolage Grotesque","body":"Figtree"},"moods":["playful","trust","bold"],"demo":{"name":"Copperwren Plumbing","url":"https://adaptexec.com/demos/copperwren/"},"demo_url":"https://adaptexec.com/demos/copperwren/","domain":"pipelinepros.com","w":{"feel":{"playful":2,"trust":2,"bold":2},"color":{"bright":2,"classic":2},"type":{"display":2,"mono":2,"sans":1},"density":{"balanced":3},"motion":{"lively":2,"smooth":2},"aud":{"homeowners":3,"community":1},"ind":{"trades":3,"tech":1,"church":1}},"mockup_html":"<div class=\"axm axm-blueprint\"><span class=\"gp\"></span><div class=\"n\"><span class=\"lg\"><b></b>Pipeline<small>Pros</small></span><span class=\"ln\"><i>Repairs</i><i>Water heaters</i><i>Pricing</i></span><span class=\"bt\">Book a pro</span></div><span class=\"ey\">&#9679; 24/7 local plumbing</span><span class=\"h\">Hot water,<br>back by <em>tonight.</em></span><span class=\"p\">Flat-rate prices before we start. A real person answers, day or night.</span><span class=\"cta\">Book a pro &rarr;</span><svg class=\"pipe\" viewBox=\"0 0 260 220\"><path d=\"M10 40h120a30 30 0 0 1 30 30v60a30 30 0 0 0 30 30h60\" fill=\"none\" stroke=\"#3dd6d8\" stroke-width=\"3\" stroke-dasharray=\"8 7\"/><circle cx=\"130\" cy=\"40\" r=\"7\" fill=\"#ff8a3d\"/><circle cx=\"160\" cy=\"130\" r=\"7\" fill=\"#ff8a3d\"/><circle cx=\"250\" cy=\"160\" r=\"7\" fill=\"#ff8a3d\"/></svg><span class=\"dim\">&#9664; 2.4 m &#9654;</span><span class=\"stk\">24/7<small>NO OT FEES</small></span></div>"},{"slug":"festive","number":17,"name":"Festive & Cinematic","vibe":"Rich warm color, full-bleed video and celebratory details that make people want to visit.","feels_like":["Lively","Warm","Celebratory"],"best_for":["Restaurants & bars","Venues","Event planners","Festivals","Catering"],"watch_outs":"Video has to be compressed with a poster image, and decorations should never push the menu or booking button out of view.","palette":["#1b0f0a","#f2a516","#e0457b","#2bb3a3"],"fonts":{"display":"Fraunces","body":"DM Sans"},"moods":["warm","playful","bold"],"demo":{"name":"Cantina Sol","url":"https://adaptexec.com/demos/cantina-sol/"},"demo_url":"https://adaptexec.com/demos/cantina-sol/","domain":"lalumbre.mx","w":{"feel":{"warm":3,"playful":2,"bold":2},"color":{"earthy":2,"bright":2,"dark":2},"type":{"serif":2,"display":2},"density":{"balanced":2},"motion":{"lively":3,"smooth":1},"aud":{"community":3,"young":2},"ind":{"food":3,"church":1,"retail":1}},"mockup_html":"<div class=\"axm axm-festive\"><span class=\"bk\"></span><span class=\"bk b2\"></span><span class=\"bk b3\"></span><span class=\"bk b4\"></span><span class=\"vg\"></span><span class=\"plate\"><i></i><i></i><i></i><i></i><i></i></span><div class=\"pp\"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div><div class=\"n\"><span class=\"lg\">La Lumbre<small>Taquer&iacute;a &middot; Mezcal</small></span><span class=\"ln\"><i>Menu</i><i>Events</i><i>Visit</i></span><span class=\"bt\">Reserve</span></div><span class=\"ey\">&mdash; Open tonight 5&ndash;11</span><span class=\"h\">Tacos, mezcal<br>&amp; <em>good company.</em></span><span class=\"cta\">Reserve a table</span><span class=\"play\"><b></b>Watch the kitchen</span></div>"},{"slug":"retro-y2k","number":18,"name":"Retro Y2K","vibe":"Chrome type, pixel sparkles, candy gradients and a wink of early-internet nostalgia.","feels_like":["Nostalgic","Quirky","Fun"],"best_for":["Music & merch","Vintage & boutique shops","Events & launches","Youth brands","Creators"],"watch_outs":"Trend-driven, so it can date quickly. It works best for campaigns, launches or brands built on personality.","palette":["#c6f6ff","#ff6ad5","#8c1eff","#fffb96"],"fonts":{"display":"Bungee","body":"Space Mono"},"moods":["playful","bold"],"demo":{"name":"Velvet Static Vintage","url":"/demos/velvetstatic/"},"demo_url":"/demos/velvetstatic/","domain":"starbyte.fm","w":{"feel":{"playful":3,"bold":2},"color":{"bright":3,"pastel":2},"type":{"display":3,"mono":2},"density":{"packed":2,"balanced":1},"motion":{"lively":3},"aud":{"young":3},"ind":{"retail":3,"food":1,"tech":1}},"mockup_html":"<div class=\"axm axm-retro-y2k\"><span class=\"st s1\"></span><span class=\"st s2\"></span><span class=\"st s3\"></span><span class=\"st s4\"></span><div class=\"win\"><div class=\"tb\"><span>starbyte.exe</span><i></i><i></i><i></i></div><div class=\"bd\"><span class=\"h\">STARBYTE</span><span class=\"sub\">&#10022; records &amp; merch &#10022;</span><span class=\"p\">new drop friday &middot; limited glow vinyl &middot; free stickers</span><div class=\"bts\"><span class=\"b1\">ENTER SHOP</span><span class=\"b2\">LISTEN</span></div></div></div><div class=\"mq\"><span>&#9733; WELCOME 2 THE FUTURE &#9733; BEST VIEWED IN ANY BROWSER &#9733; SIGN MY GUESTBOOK &#9733;</span></div><span class=\"cd\"></span></div>"},{"slug":"retro-70s","number":19,"name":"Retro 70s Modern","vibe":"Sunset stripes, round chunky type and the easy optimism of a 1970s album cover.","feels_like":["Warm","Nostalgic","Easygoing"],"best_for":["Landscaping & outdoor living","Pools & patios","Cafés & breweries","Vacation rentals & motels","Local lifestyle brands"],"watch_outs":"The palette is loud, so inner pages need a calm cream base to stay readable. Overdo the stripes and it starts to feel like a costume.","palette":["#fff4e2","#f2643d","#1fa3a0","#2b1a33"],"fonts":{"display":"Shrikhand","body":"Outfit"},"moods":["warm","playful","bold"],"demo":{"name":"Mesquite & Moon","url":"https://adaptexec.com/demos/mesquite-moon/"},"demo_url":"https://adaptexec.com/demos/mesquite-moon/","domain":"sundialyards.co","w":{"feel":{"warm":3,"playful":2,"bold":1},"color":{"earthy":3,"bright":2,"pastel":1},"type":{"display":3,"hand":1,"sans":1},"density":{"balanced":3,"airy":1},"motion":{"smooth":2,"lively":2},"aud":{"homeowners":3,"community":2,"young":1},"ind":{"trades":3,"food":2,"retail":1}},"mockup_html":"<div class=\"axm axm-retro-70s\"><span class=\"rb\"><i></i><i></i><i></i><i></i></span><span class=\"sun\"></span><span class=\"cac\"><i></i><i></i></span><span class=\"grd\"></span><div class=\"n\"><span class=\"lg\">Sundial<small>Desert yards &middot; est. &rsquo;74</small></span><span class=\"ln\"><i>Designs</i><i>Plants</i><i>Care</i></span><span class=\"bt\">Free consult</span></div><span class=\"ey\">Side A &middot; Low-water living</span><span class=\"h\">Yards that<br><em>love the heat.</em></span><span class=\"p\">Shade where you sit, color that survives July.</span><span class=\"cta\">Mix your yard</span><span class=\"bdg\">Save<br><b>60%</b><br>water</span></div>"}],"quiz":[{"id":"feel","weight":3,"q":"How should people feel when your homepage loads?","hint":"Pick the one that matters most.","opts":[{"v":"calm","label":"Calm","sub":"Unhurried and reassuring","why":"a calm first impression"},{"v":"bold","label":"Bold","sub":"Impossible to scroll past","why":"a bold first impression"},{"v":"playful","label":"Playful","sub":"Fun, friendly, a little cheeky","why":"a playful personality"},{"v":"premium","label":"Premium","sub":"Polished and high-end","why":"a premium feel"},{"v":"trust","label":"Trustworthy","sub":"Steady, proven, safe","why":"a trustworthy first impression"},{"v":"warm","label":"Warm","sub":"Welcoming, like family","why":"a warm welcoming feel"}]},{"id":"color","weight":2,"q":"Which color mood feels like you?","hint":"Think about your logo, your space, your customers.","opts":[{"v":"neutral","label":"Clean neutrals","sub":"Whites, grays and one accent","why":"clean neutral colors"},{"v":"dark","label":"Dark & moody","sub":"Deep tones with glow","why":"a dark moody palette"},{"v":"bright","label":"Bright & saturated","sub":"Punchy, confident color","why":"bright saturated color"},{"v":"pastel","label":"Soft pastels","sub":"Airy, gentle tints","why":"soft pastels"},{"v":"earthy","label":"Earthy & warm","sub":"Clay, olive, sand, sunset","why":"earthy warm tones"},{"v":"classic","label":"Classic navy & gold","sub":"Timeless and established","why":"classic navy and gold"}]},{"id":"type","weight":2,"q":"Pick the lettering you like best.","hint":"Type sets the voice before anyone reads a word.","opts":[{"v":"sans","label":"Clean sans","sub":"Modern and neutral","why":"clean sans-serif type"},{"v":"serif","label":"Elegant serif","sub":"Bookish and refined","why":"elegant serif type"},{"v":"display","label":"Chunky display","sub":"Big, loud headlines","why":"chunky display headlines"},{"v":"hand","label":"Friendly & handwritten","sub":"Human and personal","why":"friendly handwritten touches"},{"v":"mono","label":"Techy mono","sub":"Precise, a bit nerdy","why":"techy monospace details"},{"v":"condensed","label":"Tall & condensed","sub":"Strong and industrial","why":"tall condensed type"}]},{"id":"density","weight":1,"q":"How much should each screen hold?","hint":"","opts":[{"v":"airy","label":"Airy","sub":"Lots of breathing room","why":"an airy layout"},{"v":"balanced","label":"Balanced","sub":"A comfortable mix","why":"a balanced layout"},{"v":"packed","label":"Packed","sub":"Info-rich and efficient","why":"an info-rich layout"}]},{"id":"motion","weight":1,"q":"How much movement?","hint":"","opts":[{"v":"subtle","label":"Still & subtle","sub":"Gentle fades only","why":"subtle motion"},{"v":"smooth","label":"Smooth & polished","sub":"Tasteful reveals and hovers","why":"smooth polished motion"},{"v":"lively","label":"Lively & kinetic","sub":"Things move, bounce, scroll","why":"lively kinetic motion"}]},{"id":"aud","weight":1.5,"q":"Who are you mostly talking to?","hint":"","opts":[{"v":"community","label":"Local families & community","sub":"","why":"local families and community"},{"v":"pro","label":"Professionals & businesses","sub":"","why":"professional and business buyers"},{"v":"young","label":"Young, trend-savvy crowd","sub":"","why":"a young trend-savvy crowd"},{"v":"luxe","label":"High-end clients","sub":"","why":"high-end clients"},{"v":"homeowners","label":"Homeowners & tradespeople","sub":"","why":"homeowners"}]},{"id":"ind","weight":2,"q":"What kind of business is it?","hint":"Closest fit is fine.","opts":[{"v":"trades","label":"Home services & trades","sub":"","why":"home services and trades"},{"v":"health","label":"Health, dental & wellness","sub":"","why":"health and wellness"},{"v":"food","label":"Restaurant, food & events","sub":"","why":"restaurants and events"},{"v":"finance","label":"Real estate, finance & legal","sub":"","why":"real estate and finance"},{"v":"church","label":"Church, nonprofit & education","sub":"","why":"churches and nonprofits"},{"v":"retail","label":"Retail, beauty & fashion","sub":"","why":"retail and beauty brands"},{"v":"tech","label":"Tech, startup & creative","sub":"","why":"tech and creative businesses"}]}],"moods":[{"v":"calm","label":"Calm"},{"v":"bold","label":"Bold"},{"v":"playful","label":"Playful"},{"v":"premium","label":"Premium"},{"v":"trust","label":"Trustworthy"},{"v":"warm","label":"Warm"},{"v":"techy","label":"Techy"}],"fonts_css":["https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@300;600&family=Instrument+Sans:wght@700&family=Geist+Mono&family=Libre+Caslon+Display&family=Source+Sans+3:wght@400;600&family=Cormorant+Garamond:ital,wght@0,300;0,400;1,400&family=Jost:wght@400;500&family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Sora:wght@300;600&family=Manrope:wght@500;600;700;800&family=Newsreader:ital,wght@0,500;1,500&family=Nunito+Sans:wght@400;600;700&family=Caveat:wght@500&family=Poppins:wght@500;600;700&family=Geist:wght@500;600;700&display=swap","https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&family=JetBrains+Mono&family=Fredoka:wght@600&family=Nunito:wght@400;700;800&family=Archivo+Black&family=Space+Mono:wght@400;700&family=IBM+Plex+Mono:wght@400;700&family=Unbounded:wght@600;700;900&family=Oswald:wght@600;700&family=Bricolage+Grotesque:wght@800&family=Figtree:wght@400;600;700&family=DM+Mono&family=Fraunces:ital,wght@0,400;0,600;1,400&family=DM+Sans:wght@400;500;600;700&family=Bungee&family=Shrikhand&family=Outfit:wght@400;600;700&display=swap"]}
AXH_STYLE_JSON;
}

function axh_style_css() {
	return <<<'AXH_STYLE_CSS'
.axm{position:absolute;left:0;top:0;width:640px;height:400px;overflow:hidden;transform-origin:0 0;transform:scale(var(--k,1));line-height:1.2;text-align:left;letter-spacing:normal;text-transform:none;font-weight:400;font-style:normal;-webkit-font-smoothing:antialiased;contain:layout paint;pointer-events:none;user-select:none}
.axm *,.axm *:before,.axm *:after{box-sizing:border-box;margin:0;padding:0;border:0;font:inherit;color:inherit;letter-spacing:inherit;text-transform:inherit;line-height:inherit;background:none;box-shadow:none;text-decoration:none;max-width:none}
.axm i,.axm em,.axm b,.axm small,.axm span,.axm u,.axm mark,.axm code{display:inline}
.axm svg{display:block;overflow:visible}
.axm .n{position:absolute;left:0;right:0;top:0;height:52px;display:flex;align-items:center;gap:22px;padding:0 28px;z-index:3}
.axm .n .ln{display:flex;gap:18px;margin-left:auto}
.axm .n .ln i{display:inline}

/* 1 minimal */
.axm-minimal{background:#fafaf7;color:#141414;font-family:"Hanken Grotesk",sans-serif}
.axm-minimal .n{font-size:12px;border-bottom:1px solid #e9e6df}
.axm-minimal .lg{font-weight:600;font-size:15px;letter-spacing:-.02em}
.axm-minimal .ln i{color:#6f6c66}
.axm-minimal .ct{font-size:12px}
.axm-minimal .tx{position:absolute;left:44px;top:108px;width:300px}
.axm-minimal .ey{display:block;font-size:11px;color:#8b877f;letter-spacing:.06em;text-transform:uppercase;margin-bottom:18px}
.axm-minimal .h{display:block;font-size:44px;font-weight:300;line-height:1.02;letter-spacing:-.035em}
.axm-minimal .p{display:block;font-size:13px;line-height:1.55;color:#6f6c66;margin:18px 0 22px;width:250px}
.axm-minimal .lk{display:inline-block;font-size:13px;font-weight:600;border-bottom:1px solid #141414;padding-bottom:3px}
.axm-minimal .art{position:absolute;right:0;top:52px;width:270px;bottom:0;background:#efece6}
.axm-minimal .sh{position:absolute;left:58px;bottom:84px;width:150px;height:18px;border-radius:50%;background:radial-gradient(closest-side,rgba(0,0,0,.14),transparent)}
.axm-minimal .pl{position:absolute;left:40px;right:40px;bottom:0;height:92px;background:#e3dfd7}
.axm-minimal .vs{position:absolute;left:96px;bottom:92px;width:78px;height:150px;background:linear-gradient(100deg,#d9d4ca 0%,#f1eee8 45%,#cfc9be 100%);border-radius:44% 44% 22% 22%/30% 30% 12% 12%}
.axm-minimal .vs:before{content:"";position:absolute;left:24px;top:-14px;width:30px;height:20px;background:#d6d0c5;border-radius:4px 4px 0 0}
.axm-minimal .cp{position:absolute;left:176px;bottom:92px;width:46px;height:34px;background:linear-gradient(100deg,#2a2826,#4a4743);border-radius:4px 4px 18px 18px}
.axm-minimal .ft{position:absolute;left:44px;bottom:24px;font-size:11px;color:#8b877f}

/* 2 swiss */
.axm-swiss{background:#fff;color:#111;font-family:"Instrument Sans",sans-serif}
.axm-swiss .gr{position:absolute;inset:0;background:repeating-linear-gradient(90deg,transparent 0 52px,rgba(0,0,0,.06) 52px 53px)}
.axm-swiss .n{font-family:"Geist Mono",monospace;font-size:10px;border-bottom:2px solid #111;height:46px}
.axm-swiss .lg{font-family:"Instrument Sans",sans-serif;font-weight:700;font-size:16px;letter-spacing:-.03em}
.axm-swiss .ln b{color:#e8332a;margin-right:4px}
.axm-swiss .h{position:absolute;left:28px;top:64px;font-size:74px;font-weight:700;line-height:.86;letter-spacing:-.055em}
.axm-swiss .h em{color:#e8332a;font-style:normal}
.axm-swiss .sq{position:absolute;right:28px;top:64px;width:132px;height:132px;background:#e8332a;color:#fff;padding:12px;display:flex;flex-direction:column;justify-content:space-between}
.axm-swiss .sq b{font-weight:700;font-size:30px;letter-spacing:-.04em}
.axm-swiss .sq span{font-family:"Geist Mono",monospace;font-size:10px;line-height:1.3}
.axm-swiss .ph{position:absolute;right:172px;top:64px;width:132px;height:212px;background:linear-gradient(180deg,#cfd2d4,#9ea3a6);overflow:hidden}
.axm-swiss .ph span{position:absolute;left:18px;right:18px;bottom:0;height:150px;background:repeating-linear-gradient(0deg,#2b2e30 0 3px,#7d8285 3px 22px),#2b2e30;clip-path:polygon(0 30%,60% 0,100% 18%,100% 100%,0 100%)}
.axm-swiss .cols{position:absolute;left:28px;right:28px;bottom:22px;display:grid;grid-template-columns:repeat(4,1fr);border-top:2px solid #111;padding-top:10px;font-family:"Geist Mono",monospace;font-size:10px}
.axm-swiss .cols span{display:flex;flex-direction:column;gap:3px}
.axm-swiss .cols b{color:#8a8a8a;font-weight:400}
.axm-swiss .cols .cta{background:#111;color:#fff;justify-content:center;align-items:center;font-size:11px;height:34px;align-self:end}

/* 3 editorial */
.axm-editorial{background:#f6f1e8;color:#1a1f2b;font-family:"Source Sans 3",sans-serif}
.axm-editorial .n{height:58px;font-size:11px;letter-spacing:.1em;text-transform:uppercase}
.axm-editorial .mk{border:1px solid #1a1f2b;font-family:"Libre Caslon Display",serif;font-size:13px;padding:4px 5px;letter-spacing:0}
.axm-editorial .lg{font-family:"Libre Caslon Display",serif;font-size:19px;text-transform:none;letter-spacing:0;display:flex;flex-direction:column;margin-left:-10px;white-space:nowrap}
.axm-editorial .lg i{color:#8a6a3b;font-style:italic}
.axm-editorial .lg small{font-family:"Source Sans 3",sans-serif;font-size:8px;letter-spacing:.24em;text-transform:uppercase;color:#7a7466;margin-top:2px}
.axm-editorial .ln{font-size:10px}
.axm-editorial .bt{background:#1a1f2b;color:#f6f1e8;padding:8px 12px;font-size:10px}
.axm-editorial .rl{position:absolute;left:28px;right:28px;top:58px;height:1px;background:#d7cfbf}
.axm-editorial .tx{position:absolute;left:28px;top:96px;width:330px}
.axm-editorial .ey{display:flex;align-items:center;gap:10px;font-size:9px;letter-spacing:.22em;text-transform:uppercase;color:#8a6a3b;margin-bottom:16px}
.axm-editorial .ey:before{content:"";width:26px;height:1px;background:#8a6a3b}
.axm-editorial .h{display:block;font-family:"Libre Caslon Display",serif;font-size:42px;line-height:1.02;letter-spacing:-.01em}
.axm-editorial .h em{font-style:italic;color:#8a6a3b}
.axm-editorial .p{display:block;font-size:13px;line-height:1.55;color:#4b4f59;margin:16px 0 20px;width:280px}
.axm-editorial .cta{display:inline-block;background:#1a1f2b;color:#fff;font-size:11px;letter-spacing:.06em;padding:11px 16px;margin-right:16px}
.axm-editorial .sec{font-size:11px;border-bottom:1px solid #1a1f2b;padding-bottom:2px}
.axm-editorial .arch{position:absolute;right:36px;top:86px;width:200px;height:290px;border-radius:100px 100px 0 0;overflow:hidden;background:linear-gradient(180deg,#e9b98a 0%,#f3d7b5 45%,#d99a6c 100%)}
.axm-editorial .sun{position:absolute;left:62px;top:110px;width:76px;height:76px;border-radius:50%;background:#fbecd3}
.axm-editorial .hill{position:absolute;left:-30px;right:-30px;bottom:-40px;height:150px;border-radius:50% 50% 0 0;background:#7a4b35}
.axm-editorial .hill.b{left:60px;right:-90px;bottom:-60px;background:#3c2a24}
.axm-editorial .cap{position:absolute;right:36px;bottom:10px;font-size:9px;font-style:italic;color:#7a7466;font-family:"Libre Caslon Display",serif}

/* 4 luxury */
.axm-luxury{background:#1d1714;color:#efe6dc;font-family:"Jost",sans-serif}
.axm-luxury .glow{position:absolute;right:40px;top:-40px;width:420px;height:480px;background:radial-gradient(50% 50% at 50% 45%,#8a5f45 0%,#4a3227 45%,transparent 75%)}
.axm-luxury .sho{position:absolute;right:78px;top:286px;width:280px;height:200px;border-radius:140px 140px 0 0;background:linear-gradient(100deg,#d9a887 0%,#a8704f 55%,#5e3a2a 100%)}
.axm-luxury .hair{position:absolute;right:130px;top:70px;width:176px;height:360px;border-radius:88px 88px 30px 30px/80px 80px 30px 30px;background:repeating-linear-gradient(96deg,rgba(255,220,180,.0) 0 9px,rgba(255,214,170,.10) 9px 12px,rgba(0,0,0,0) 12px 20px),linear-gradient(180deg,#8a5a3e 0%,#6b432f 35%,#4a2d20 70%,#2e1c14 100%);box-shadow:inset 18px 0 30px -10px rgba(255,200,150,.25),inset -20px 0 30px -10px rgba(0,0,0,.4);clip-path:polygon(18% 0,82% 0,100% 30%,96% 70%,104% 100%,-4% 100%,4% 70%,0 30%)}
.axm-luxury .n{font-size:9px;letter-spacing:.24em;text-transform:uppercase}
.axm-luxury .lg{font-family:"Cormorant Garamond",serif;font-size:17px;letter-spacing:.02em;text-transform:none}
.axm-luxury .bt{border:1px solid rgba(239,230,220,.7);border-radius:30px;padding:7px 13px}
.axm-luxury .big{position:absolute;left:22px;top:44px;font-family:"Cormorant Garamond",serif;font-weight:300;font-size:128px;line-height:1;letter-spacing:-.03em}
.axm-luxury .iss{position:absolute;right:28px;top:76px;font-family:"Cormorant Garamond",serif;font-size:14px;text-align:right}
.axm-luxury .iss em{font-style:italic}
.axm-luxury .col{position:absolute;left:30px;top:200px;display:flex;flex-direction:column;gap:3px;border-top:1px solid rgba(239,230,220,.35);padding-top:8px;width:150px}
.axm-luxury .col b{font-size:8px;letter-spacing:.24em;text-transform:uppercase;color:#c8a27a}
.axm-luxury .col em{font-family:"Cormorant Garamond",serif;font-style:italic;font-size:15px}
.axm-luxury .h{position:absolute;left:30px;bottom:72px;font-family:"Cormorant Garamond",serif;font-size:44px;line-height:.98}
.axm-luxury .h em{font-style:italic;color:#e3c3a4}
.axm-luxury .pill{position:absolute;left:30px;bottom:26px;background:#efe6dc;color:#1d1714;border-radius:30px;padding:9px 16px;font-size:9px;letter-spacing:.24em;text-transform:uppercase}

/* 5 corporate */
.axm-corporate{background:#fff;color:#0f2a4a;font-family:"Plus Jakarta Sans",sans-serif}
.axm-corporate:before{content:"";position:absolute;right:-80px;top:40px;width:420px;height:420px;border-radius:50%;background:#eaf2fb}
.axm-corporate .n{font-size:11px;font-weight:600;border-bottom:1px solid #e6edf5;background:#fff}
.axm-corporate .lg{display:flex;align-items:center;gap:7px;font-weight:800;font-size:15px}
.axm-corporate .lg b{width:20px;height:20px;border-radius:5px;background:linear-gradient(135deg,#1f6feb,#0f2a4a)}
.axm-corporate .lg small{font-weight:500;color:#5b7088;font-size:12px}
.axm-corporate .ln i{color:#4a5f78}
.axm-corporate .lo{color:#1f6feb}
.axm-corporate .bt{background:#1f6feb;color:#fff;padding:8px 13px;border-radius:8px}
.axm-corporate .tx{position:absolute;left:30px;top:82px;width:320px;z-index:2}
.axm-corporate .rt{display:inline-block;font-size:10px;font-weight:600;background:#eaf2fb;border-radius:30px;padding:5px 10px;color:#30475f;margin-bottom:14px}
.axm-corporate .rt b{color:#f5a623;letter-spacing:1px}
.axm-corporate .h{display:block;font-size:31px;font-weight:800;line-height:1.1;letter-spacing:-.025em}
.axm-corporate .h em{font-style:normal;color:#1f6feb}
.axm-corporate .p{display:block;font-size:12.5px;line-height:1.55;color:#4a5f78;margin:12px 0 18px}
.axm-corporate .b1{display:inline-block;background:#1f6feb;color:#fff;font-weight:700;font-size:12px;border-radius:9px;padding:11px 16px;margin-right:8px}
.axm-corporate .b2{display:inline-block;border:1.5px solid #c9d7e6;font-weight:700;font-size:12px;border-radius:9px;padding:9.5px 14px}
.axm-corporate .tr{display:flex;gap:16px;margin-top:22px;font-size:10px;font-weight:600;color:#5b7088}
.axm-corporate .tr span:before{content:"\2713";color:#19a974;margin-right:5px;font-weight:800}
.axm-corporate .card{position:absolute;right:36px;top:92px;width:212px;height:250px;background:#fff;border-radius:16px;box-shadow:0 20px 40px -18px rgba(15,42,74,.35),0 0 0 1px #e6edf5;padding:18px;z-index:2}
.axm-corporate .cl{display:block;font-size:11px;font-weight:700;color:#4a5f78}
.axm-corporate .ring{width:92px;height:92px;border-radius:50%;margin:14px auto;background:conic-gradient(#1f6feb 0 86%,#eaf2fb 0);display:grid;place-items:center;position:relative}
.axm-corporate .ring:before{content:"";position:absolute;inset:10px;border-radius:50%;background:#fff}
.axm-corporate .ring b{position:relative;font-weight:800;font-size:20px}
.axm-corporate .bars{display:flex;align-items:flex-end;gap:7px;height:46px}
.axm-corporate .bars i{flex:1;background:#cfe0f6;border-radius:4px 4px 0 0}
.axm-corporate .bars i:last-child{background:#1f6feb}
.axm-corporate .up{display:inline-block;margin-top:12px;font-size:10px;font-weight:700;color:#0e7a52;background:#e3f6ee;border-radius:20px;padding:4px 9px}

/* 6 glass */
.axm-glass{background:#eef6fd;color:#0f2540;font-family:"Manrope",sans-serif}
.axm-glass .b1{position:absolute;right:30px;top:20px;width:300px;height:300px;border-radius:50%;background:radial-gradient(circle at 40% 40%,#9fd6ff,#5ab6f2 60%,transparent 72%);filter:blur(10px);opacity:.75}
.axm-glass .b2{position:absolute;right:-40px;top:170px;width:280px;height:280px;border-radius:50%;background:radial-gradient(circle,#d9cbff,#c9b8ff 50%,transparent 70%);filter:blur(12px)}
.axm-glass .b3{position:absolute;left:180px;top:250px;width:240px;height:240px;border-radius:50%;background:radial-gradient(circle,#c4f1e9,transparent 70%);filter:blur(8px)}
.axm-glass .n{top:12px;left:20px;right:20px;height:42px;border-radius:30px;background:rgba(255,255,255,.55);border:1px solid rgba(255,255,255,.8);font-size:11px;font-weight:600;padding:0 8px 0 16px}
.axm-glass .lg{display:flex;align-items:center;gap:6px;font-family:"Sora",sans-serif;font-weight:600;font-size:14px}
.axm-glass .lg b{width:16px;height:16px;border-radius:50%;background:conic-gradient(#5ab6f2,#c9b8ff,#7fe0d0,#5ab6f2)}
.axm-glass .bt{background:#0f2540;color:#fff;border-radius:30px;padding:8px 14px}
.axm-glass .tx{position:absolute;left:34px;top:92px;width:310px;z-index:2}
.axm-glass .ey{display:block;font-size:10px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#3a7fb0;margin-bottom:12px}
.axm-glass .h{display:block;font-family:"Sora",sans-serif;font-weight:300;font-size:40px;line-height:1.06;letter-spacing:-.035em}
.axm-glass .h em{font-style:normal;font-weight:600;background:linear-gradient(90deg,#1f78b8,#7b6cf6);-webkit-background-clip:text;background-clip:text;color:transparent}
.axm-glass .p{display:block;font-size:12.5px;line-height:1.55;color:#3d5570;margin:14px 0 18px;width:270px}
.axm-glass .cta{display:inline-block;background:#0f2540;color:#fff;border-radius:30px;padding:11px 18px;font-size:12px;font-weight:700;margin-right:8px}
.axm-glass .sec{display:inline-block;background:rgba(255,255,255,.7);border-radius:30px;padding:11px 16px;font-size:12px;font-weight:700}
.axm-glass .g{position:absolute;z-index:2;background:rgba(255,255,255,.42);border:1px solid rgba(255,255,255,.85);border-radius:16px;padding:12px 14px;-webkit-backdrop-filter:blur(14px);backdrop-filter:blur(14px);box-shadow:0 18px 40px -20px rgba(15,37,64,.35);display:flex;flex-direction:column;gap:2px;font-size:12px}
.axm-glass .g b{font-weight:700}
.axm-glass .g small{font-size:10px;color:#3d5570}
.axm-glass .g1{right:150px;top:96px;padding-left:30px}
.axm-glass .g1 .dot{position:absolute;left:13px;top:16px;width:8px;height:8px;border-radius:50%;background:#2fbf8f;box-shadow:0 0 0 4px rgba(47,191,143,.2)}
.axm-glass .g2{right:34px;top:186px;width:180px;padding-left:44px}
.axm-glass .g2 .ic{position:absolute;left:12px;top:14px;width:22px;height:22px;border-radius:7px;background:linear-gradient(135deg,#5ab6f2,#c9b8ff)}
.axm-glass .g3{right:180px;top:280px;align-items:center}
.axm-glass .g3 b{font-family:"Sora",sans-serif;font-size:22px}

/* 7 organic */
.axm-organic{background:#f6efe4;color:#2f3b2a;font-family:"Nunito Sans",sans-serif}
.axm-organic .n{font-size:11px;font-weight:600}
.axm-organic .lg{display:flex;align-items:center;gap:6px;font-family:"Newsreader",serif;font-size:17px;font-weight:500}
.axm-organic .lg svg{width:20px;height:20px;color:#6b7f4e}
.axm-organic .bt{background:#b85c38;color:#fff;border-radius:30px;padding:8px 14px}
.axm-organic .tx{position:absolute;left:30px;top:86px;width:330px}
.axm-organic .ey{display:block;font-size:10px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#b85c38;margin-bottom:12px}
.axm-organic .h{display:block;font-family:"Newsreader",serif;font-size:41px;line-height:1.04;letter-spacing:-.02em}
.axm-organic .h em{font-style:italic;color:#b85c38}
.axm-organic .ul{width:120px;height:12px;margin:-2px 0 0 136px}
.axm-organic .p{display:block;font-size:12.5px;line-height:1.6;color:#55604c;margin:10px 0 18px;width:280px}
.axm-organic .cta{display:inline-block;background:#b85c38;color:#fff;border-radius:30px;padding:11px 18px;font-size:12px;font-weight:700}
.axm-organic .arch{position:absolute;right:40px;top:70px;width:196px;height:300px;border-radius:98px 98px 18px 18px;overflow:hidden;background:#f0c99a}
.axm-organic .sky{position:absolute;inset:0;background:linear-gradient(180deg,#f3b98a,#f7dcb6 55%)}
.axm-organic .sun{position:absolute;left:70px;top:100px;width:60px;height:60px;border-radius:50%;background:#fff3dc}
.axm-organic .h1,.axm-organic .h2,.axm-organic .h3{position:absolute;border-radius:50% 50% 0 0}
.axm-organic .h1{left:-60px;width:230px;height:140px;bottom:30px;background:#a9a372}
.axm-organic .h2{left:60px;width:260px;height:150px;bottom:-10px;background:#6b7f4e}
.axm-organic .h3{left:-40px;width:200px;height:90px;bottom:-30px;background:#2f3b2a}
.axm-organic .note{position:absolute;right:236px;top:282px;font-family:"Caveat",cursive;font-size:21px;line-height:1;color:#2f3b2a;transform:rotate(-5deg);background:#fffaf0;padding:8px 10px;box-shadow:0 8px 16px -10px rgba(47,59,42,.5)}
.axm-organic .arr{position:absolute;right:214px;top:230px;width:56px;height:40px;transform:scaleY(-1) rotate(-10deg)}
.axm-organic .spr{position:absolute;right:16px;top:300px;width:30px;height:46px;transform:rotate(14deg)}

/* 8 flat */
.axm-flat{background:#fff;color:#1b2a49;font-family:"Poppins",sans-serif}
.axm-flat .n{font-size:11px;font-weight:500}
.axm-flat .lg{display:flex;align-items:center;gap:6px;font-weight:700;font-size:15px}
.axm-flat .lg b{width:18px;height:18px;background:#2ec4b6;border-radius:5px 5px 5px 0}
.axm-flat .bt{background:#1b2a49;color:#fff;border-radius:8px;padding:8px 13px}
.axm-flat .tx{position:absolute;left:30px;top:84px;width:300px}
.axm-flat .tag{display:inline-block;background:#e3f8f5;color:#138a7f;font-size:10px;font-weight:600;border-radius:6px;padding:5px 9px;margin-bottom:14px}
.axm-flat .h{display:block;font-size:33px;font-weight:700;line-height:1.1;letter-spacing:-.02em}
.axm-flat .h em{font-style:normal;color:#ff9f1c}
.axm-flat .cta{display:inline-block;background:#ff9f1c;color:#1b2a49;font-weight:700;font-size:12px;border-radius:10px;padding:12px 18px;margin:20px 12px 0 0}
.axm-flat .pr{font-size:12px;color:#55607a}
.axm-flat .pr b{color:#1b2a49;font-weight:700;font-size:16px}
.axm-flat .ill{position:absolute;right:24px;top:62px;width:280px;height:310px;background:#e3f8f5;border-radius:24px;overflow:hidden}
.axm-flat .sun{position:absolute;right:30px;top:28px;width:56px;height:56px;border-radius:50%;background:#ffd166}
.axm-flat .hill{position:absolute;left:-40px;right:-40px;bottom:-110px;height:200px;border-radius:50%;background:#2ec4b6}
.axm-flat .roof{position:absolute;left:58px;top:96px;width:0;height:0;border-left:78px solid transparent;border-right:78px solid transparent;border-bottom:66px solid #ff6b6b}
.axm-flat .house{position:absolute;left:72px;top:162px;width:128px;height:96px;background:#fff}
.axm-flat .house .d{display:block;position:absolute;left:50px;bottom:0;width:28px;height:44px;background:#1b2a49;border-radius:14px 14px 0 0}
.axm-flat .house .w{display:block;position:absolute;left:14px;top:18px;width:24px;height:24px;background:#ffd166}
.axm-flat .house .w.r{left:auto;right:14px}
.axm-flat .tree{position:absolute;right:26px;top:150px;width:58px;height:58px;border-radius:50%;background:#138a7f}
.axm-flat .trunk{position:absolute;right:51px;top:200px;width:8px;height:52px;background:#1b2a49}
.axm-flat .sp{position:absolute;width:14px;height:14px;background:#ff9f1c;clip-path:polygon(50% 0,62% 38%,100% 50%,62% 62%,50% 100%,38% 62%,0 50%,38% 38%)}
.axm-flat .s1{left:30px;top:60px}.axm-flat .s2{left:206px;top:120px;width:10px;height:10px}.axm-flat .s3{left:40px;top:150px;width:9px;height:9px;background:#fff}
.axm-flat .bkt{position:absolute;left:30px;bottom:30px;width:34px;height:30px;background:#ff9f1c;clip-path:polygon(0 0,100% 0,86% 100%,14% 100%)}

/* 9 bento */
.axm-bento{background:#f4f4f5;color:#18181b;font-family:"Geist",sans-serif}
.axm-bento .n{font-size:11px;font-weight:500;height:46px}
.axm-bento .lg{display:flex;align-items:center;gap:6px;font-weight:700;font-size:15px;letter-spacing:-.03em}
.axm-bento .lg b{width:16px;height:16px;border-radius:4px;background:#18181b;box-shadow:inset -6px -6px 0 #a3e635}
.axm-bento .ln i{color:#52525b}
.axm-bento .bt{background:#18181b;color:#fff;border-radius:30px;padding:7px 13px}
.axm-bento .grid{position:absolute;left:20px;right:20px;top:50px;bottom:20px;display:grid;grid-template-columns:1.5fr 1fr 1fr;grid-template-rows:1fr 1fr;gap:10px}
.axm-bento .t{background:#fff;border-radius:18px;padding:16px;position:relative;overflow:hidden;box-shadow:0 0 0 1px #e4e4e7}
.axm-bento .k{display:block;font-family:"Geist Mono",monospace;font-size:9.5px;color:#71717a;text-transform:uppercase;letter-spacing:.06em}
.axm-bento .t1{grid-row:span 2;background:#18181b;color:#fff;display:flex;flex-direction:column;justify-content:space-between;box-shadow:none}
.axm-bento .t1 .k{color:#a1a1aa}
.axm-bento .t1 .h{font-size:31px;font-weight:600;line-height:1.05;letter-spacing:-.04em}
.axm-bento .t1 .cta{align-self:flex-start;background:#a3e635;color:#18181b;border-radius:30px;padding:10px 16px;font-size:12px;font-weight:600}
.axm-bento .t2{grid-column:span 2;display:grid;grid-template-columns:auto 1fr;grid-template-rows:auto 1fr;column-gap:16px}
.axm-bento .t2 b{font-size:30px;font-weight:600;letter-spacing:-.04em;grid-row:2}
.axm-bento .t2 .br{grid-row:span 2;grid-column:2;display:flex;align-items:flex-end;gap:6px;height:100%}
.axm-bento .t2 .br i{display:block;flex:1;background:#e4e4e7;border-radius:5px}
.axm-bento .t2 .br i:nth-child(n+6){background:#a3e635}
.axm-bento .t3{background:#6366f1;color:#fff;box-shadow:none}
.axm-bento .t3 .k{color:#c7d2fe}
.axm-bento .t3 b{display:block;font-size:28px;font-weight:600;margin-top:4px;letter-spacing:-.03em}
.axm-bento .t3 .ring{position:absolute;right:-18px;bottom:-18px;width:86px;height:86px;border-radius:50%;background:conic-gradient(#a3e635 0 72%,rgba(255,255,255,.25) 0);-webkit-mask:radial-gradient(closest-side,transparent 64%,#000 66%);mask:radial-gradient(closest-side,transparent 64%,#000 66%)}
.axm-bento .t4{display:flex;flex-direction:column;justify-content:space-between;font-size:12px;font-weight:600}
.axm-bento .av{display:flex}
.axm-bento .av i{display:block;width:28px;height:28px;border-radius:50%;border:2px solid #fff;margin-right:-8px;background:#d4d4d8}
.axm-bento .av i:nth-child(2){background:#a3e635}.axm-bento .av i:nth-child(3){background:#6366f1}.axm-bento .av i:nth-child(4){background:#18181b}
.axm-bento .t5{display:none}

/* 10 dark */
.axm-dark{background:#07070c;color:#e5e7eb;font-family:"Space Grotesk",sans-serif;text-align:center}
.axm-dark .glo{position:absolute;left:50%;top:-180px;width:560px;height:420px;margin-left:-280px;border-radius:50%;background:radial-gradient(closest-side,rgba(124,92,255,.55),transparent)}
.axm-dark .gl2{position:absolute;left:50%;bottom:-160px;width:520px;height:300px;margin-left:-60px;border-radius:50%;background:radial-gradient(closest-side,rgba(34,211,238,.28),transparent)}
.axm-dark .grd{position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.05) 1px,transparent 1px);background-size:32px 32px;-webkit-mask:radial-gradient(60% 60% at 50% 40%,#000,transparent);mask:radial-gradient(60% 60% at 50% 40%,#000,transparent)}
.axm-dark .n{font-size:11px;color:#a1a1b5}
.axm-dark .lg{display:flex;align-items:center;gap:6px;font-weight:700;font-size:15px;color:#fff}
.axm-dark .lg b{width:16px;height:16px;border-radius:4px;background:linear-gradient(135deg,#7c5cff,#22d3ee);transform:rotate(45deg) scale(.8)}
.axm-dark .bt{border:1px solid rgba(255,255,255,.2);border-radius:8px;padding:7px 12px;color:#fff}
.axm-dark .pill{position:absolute;left:50%;top:70px;transform:translateX(-50%);font-family:"JetBrains Mono",monospace;font-size:10px;border:1px solid rgba(124,92,255,.5);background:rgba(124,92,255,.12);border-radius:30px;padding:5px 12px;white-space:nowrap}
.axm-dark .pill i{display:inline-block;width:6px;height:6px;border-radius:50%;background:#22d3ee;margin-right:4px;box-shadow:0 0 8px #22d3ee}
.axm-dark .h{position:absolute;left:0;right:0;top:104px;font-size:42px;font-weight:600;line-height:1.04;letter-spacing:-.04em;color:#fff}
.axm-dark .h em{font-style:normal;background:linear-gradient(90deg,#a78bfa,#22d3ee);-webkit-background-clip:text;background-clip:text;color:transparent}
.axm-dark .p{position:absolute;left:0;right:0;top:200px;font-size:13px;color:#9ca3af}
.axm-dark .bts{position:absolute;left:0;right:0;top:232px;display:flex;justify-content:center;gap:10px;font-size:12px;font-weight:600}
.axm-dark .b1{background:#fff;color:#07070c;border-radius:9px;padding:10px 16px}
.axm-dark .b2{border:1px solid rgba(255,255,255,.2);border-radius:9px;padding:10px 16px}
.axm-dark .term{position:absolute;left:150px;right:150px;top:292px;height:140px;border-radius:12px 12px 0 0;background:rgba(18,18,30,.9);border:1px solid rgba(255,255,255,.1);text-align:left;padding:30px 16px 0;font-family:"JetBrains Mono",monospace;font-size:11px;display:flex;flex-direction:column;gap:6px;box-shadow:0 -10px 50px -10px rgba(124,92,255,.5)}
.axm-dark .tb{position:absolute;left:12px;top:10px;display:flex;gap:5px}
.axm-dark .tb i{display:block;width:8px;height:8px;border-radius:50%;background:#2a2a3c}
.axm-dark .term b{color:#7c5cff}
.axm-dark .term code{display:block;color:#d1d5db}
.axm-dark .term .ok{color:#22d3ee}

/* 11 soft 3d */
.axm-soft-3d{background:linear-gradient(160deg,#f6ecff,#fde7f0);color:#3d2c5e;font-family:"Nunito",sans-serif}
.axm-soft-3d .n{font-size:11px;font-weight:700}
.axm-soft-3d .lg{display:flex;align-items:center;gap:6px;font-family:"Fredoka",sans-serif;font-weight:600;font-size:17px}
.axm-soft-3d .lg b{width:20px;height:20px;border-radius:50%;background:radial-gradient(circle at 35% 30%,#fff 0,#ffb3c6 30%,#ff8fab 60%,#e86a8c)}
.axm-soft-3d .bt{background:#3d2c5e;color:#fff;border-radius:30px;padding:8px 15px}
.axm-soft-3d .tx{position:absolute;left:34px;top:96px;width:290px;z-index:2}
.axm-soft-3d .h{display:block;font-family:"Fredoka",sans-serif;font-weight:600;font-size:44px;line-height:1.02;letter-spacing:-.01em}
.axm-soft-3d .h em{font-style:normal;color:#ff6f96}
.axm-soft-3d .p{display:block;font-size:13px;line-height:1.55;color:#6b5a87;margin:14px 0 20px;width:250px}
.axm-soft-3d .cta{display:inline-block;background:linear-gradient(180deg,#ff9fb8,#ff7aa0);color:#fff;font-weight:800;font-size:13px;border-radius:30px;padding:13px 22px;box-shadow:inset 0 -4px 0 rgba(0,0,0,.12),inset 0 3px 0 rgba(255,255,255,.4),0 12px 20px -8px rgba(255,111,150,.6)}
.axm-soft-3d .s1{position:absolute;right:120px;top:86px;width:150px;height:150px;border-radius:50%;background:radial-gradient(circle at 32% 28%,#fff 0,#c2f0fa 18%,#7bdff2 52%,#3fb3cf 100%);box-shadow:0 30px 40px -18px rgba(63,179,207,.6)}
.axm-soft-3d .s2{position:absolute;right:56px;top:220px;width:96px;height:96px;border-radius:50%;background:radial-gradient(circle at 32% 28%,#fff 0,#ffd1dc 20%,#ff8fab 55%,#e2668a 100%);box-shadow:0 24px 30px -14px rgba(226,102,138,.6)}
.axm-soft-3d .s3{position:absolute;right:280px;top:290px;width:44px;height:44px;border-radius:50%;background:radial-gradient(circle at 32% 28%,#fff 0,#fff0b3 25%,#ffd65c 60%,#e8b42a)}
.axm-soft-3d .tor{position:absolute;right:236px;top:60px;width:74px;height:74px;border-radius:50%;border:18px solid #b79cff;box-shadow:inset 4px 6px 6px rgba(255,255,255,.6),inset -4px -6px 8px rgba(80,40,160,.35),0 14px 20px -10px rgba(120,80,220,.5);transform:rotate(-20deg) scaleY(.85)}
.axm-soft-3d .pil{position:absolute;right:20px;top:70px;width:90px;height:38px;border-radius:30px;background:linear-gradient(180deg,#d8ffef,#8fe8c4);box-shadow:inset 0 -6px 0 rgba(0,0,0,.08),inset 0 4px 0 rgba(255,255,255,.6),0 14px 18px -10px rgba(40,160,120,.5);transform:rotate(18deg)}
.axm-soft-3d .card{position:absolute;right:170px;top:268px;background:#fff;border-radius:20px;padding:11px 16px 11px 52px;box-shadow:0 18px 30px -16px rgba(61,44,94,.45);display:flex;flex-direction:column;font-size:12px;z-index:2}
.axm-soft-3d .card b{font-weight:800}
.axm-soft-3d .card small{font-size:10px;color:#7a6a95}
.axm-soft-3d .card .av{position:absolute;left:12px;top:11px;width:30px;height:30px;border-radius:50%;background:radial-gradient(circle at 35% 30%,#fff,#ffd65c 45%,#e8a42a)}

/* 12 neobrutalism */
.axm-neobrutalism{background:#f3efe2;color:#0b0b0b;font-family:"Space Mono",monospace}
.axm-neobrutalism .n{top:12px;left:16px;right:16px;height:42px;border:2.5px solid #0b0b0b;background:#fff;border-radius:10px;font-size:10px;font-weight:700;padding:0 6px 0 12px;box-shadow:3px 3px 0 #0b0b0b}
.axm-neobrutalism .lg{display:flex;align-items:center;gap:6px;font-family:"Archivo Black",sans-serif;font-size:14px}
.axm-neobrutalism .lg b{display:grid;place-items:center;width:22px;height:22px;background:#c6f432;border:2px solid #0b0b0b;border-radius:50%;font-size:11px}
.axm-neobrutalism .bt{background:#c6f432;border:2px solid #0b0b0b;border-radius:30px;padding:6px 12px;font-family:"Archivo Black",sans-serif;font-size:10px}
.axm-neobrutalism .ey{position:absolute;left:24px;top:74px;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
.axm-neobrutalism .h{position:absolute;left:22px;top:94px;font-family:"Archivo Black",sans-serif;font-size:50px;line-height:1.06;letter-spacing:-.02em}
.axm-neobrutalism .h .hl{display:inline-block;background:#c6f432;border:3px solid #0b0b0b;padding:0 8px;transform:rotate(-1.5deg);box-shadow:5px 5px 0 #0b0b0b;margin-top:4px}
.axm-neobrutalism .p{position:absolute;left:24px;top:240px;width:270px;font-size:11.5px;line-height:1.5}
.axm-neobrutalism .cta{position:absolute;left:24px;top:296px;background:#ff5a1f;color:#fff;border:3px solid #0b0b0b;border-radius:10px;padding:11px 18px;font-family:"Archivo Black",sans-serif;font-size:13px;box-shadow:5px 5px 0 #0b0b0b}
.axm-neobrutalism .stk{position:absolute;right:36px;top:76px;width:104px;height:104px;border-radius:50%;background:#b9a5ff;border:3px solid #0b0b0b;display:grid;place-items:center;text-align:center;font-family:"Archivo Black",sans-serif;font-size:13px;line-height:1.05;transform:rotate(12deg);box-shadow:4px 4px 0 #0b0b0b;padding-top:16px}
.axm-neobrutalism .star{position:absolute;right:170px;top:200px;width:84px;height:84px;background:#ffd23f;display:grid;place-items:center;font-family:"Archivo Black",sans-serif;font-size:14px;clip-path:polygon(50% 0,61% 22%,85% 15%,78% 39%,100% 50%,78% 61%,85% 85%,61% 78%,50% 100%,39% 78%,15% 85%,22% 61%,0 50%,22% 39%,15% 15%,39% 22%);transform:rotate(-10deg)}
.axm-neobrutalism .card{position:absolute;right:30px;bottom:30px;width:180px;background:#fff;border:3px solid #0b0b0b;border-radius:12px;padding:12px;box-shadow:6px 6px 0 #0b0b0b;display:flex;flex-direction:column;gap:3px;font-size:11px}
.axm-neobrutalism .card b{font-family:"Archivo Black",sans-serif;font-size:10px;background:#0b0b0b;color:#c6f432;align-self:flex-start;padding:2px 6px;border-radius:4px}
.axm-neobrutalism .card span{font-family:"Archivo Black",sans-serif;font-size:15px}
.axm-neobrutalism .card small{font-size:10px;color:#ff5a1f;font-weight:700}

/* 13 brutalism */
.axm-brutalism{background:#fff;color:#000;font-family:"IBM Plex Mono",monospace}
.axm-brutalism .mq{background:#000;color:#ffff00;font-size:11px;font-weight:700;white-space:nowrap;padding:5px 8px;overflow:hidden}
.axm-brutalism .h{position:absolute;left:14px;top:30px;font-family:"Times New Roman",Times,serif;font-size:82px;line-height:.92;letter-spacing:-.03em}
.axm-brutalism mark{background:#ffff00;color:#000;padding:0 4px}
.axm-brutalism .sub{position:absolute;left:16px;top:116px;font-size:11px}
.axm-brutalism .lks{position:absolute;left:16px;top:150px;display:flex;flex-direction:column;gap:6px;font-family:"Times New Roman",Times,serif;font-size:19px;color:#0000ee}
.axm-brutalism .lks u{text-decoration:underline;text-underline-offset:2px;display:block}
.axm-brutalism .lks u:nth-child(2){color:#551a8b}
.axm-brutalism table{position:absolute;right:16px;top:150px;border-collapse:collapse;font-size:11px;width:310px;margin:0}
.axm-brutalism td{border:1px solid #000;padding:7px 8px;background:#fff}
.axm-brutalism tr:last-child td:last-child{background:#ff0000;color:#fff;font-weight:700}
.axm-brutalism .bt{position:absolute;right:16px;top:268px;background:#e1e1e1;border:2px outset #f5f5f5;font-size:12px;padding:6px 14px;font-family:Arial,sans-serif}
.axm-brutalism .ct{position:absolute;left:16px;bottom:16px;font-size:10px}
.axm-brutalism:after{content:"";position:absolute;left:0;right:0;bottom:44px;border-top:1px dashed #000}

/* 14 constructivism */
.axm-constructivism{background:#f2ead8;color:#141414;font-family:"Manrope",sans-serif}
.axm-constructivism .band{position:absolute;left:-60px;top:198px;width:820px;height:86px;background:#d7261e;transform:rotate(-14deg)}
.axm-constructivism .circ{position:absolute;right:62px;top:46px;width:190px;height:190px;border-radius:50%;background:#141414}
.axm-constructivism .tri{position:absolute;right:30px;top:170px;width:0;height:0;border-left:70px solid transparent;border-right:70px solid transparent;border-bottom:120px solid #ffb81c}
.axm-constructivism .bar{position:absolute;left:300px;top:40px;width:12px;height:360px;background:#141414;transform:rotate(28deg)}
.axm-constructivism .n{font-family:"Unbounded",sans-serif;font-size:9px;font-weight:600;letter-spacing:.1em;border-bottom:3px solid #141414;height:46px;background:#f2ead8}
.axm-constructivism .lg{font-weight:900;font-size:14px;letter-spacing:0}
.axm-constructivism .h{position:absolute;left:26px;top:58px;font-family:"Unbounded",sans-serif;font-weight:900;font-size:62px;line-height:.92;letter-spacing:-.02em;z-index:2}
.axm-constructivism .h em{font-style:normal;color:#f2ead8;-webkit-text-stroke:2px #141414;margin-left:62px}
.axm-constructivism .p{position:absolute;left:28px;bottom:66px;width:230px;font-size:11px;line-height:1.5;font-weight:600;background:#f2ead8;padding:8px 10px;border-left:4px solid #141414;z-index:2}
.axm-constructivism .cta{position:absolute;left:28px;bottom:22px;background:#141414;color:#ffb81c;font-family:"Unbounded",sans-serif;font-weight:700;font-size:11px;padding:10px 16px;z-index:2}
.axm-constructivism .num{position:absolute;right:104px;top:110px;font-family:"Unbounded",sans-serif;font-weight:900;font-size:62px;color:#f2ead8;display:flex;align-items:center;gap:6px;line-height:1}
.axm-constructivism .num small{font-size:10px;line-height:1.1;font-weight:700;color:#ffb81c}

/* 15 industrial */
.axm-industrial{background:#161616 repeating-linear-gradient(135deg,rgba(255,255,255,.018) 0 2px,transparent 2px 7px);color:#e2ddd2;font-family:"Inter",sans-serif}
.axm-industrial .hz{position:absolute;left:0;right:0;top:0;height:8px;background:repeating-linear-gradient(135deg,#ff8a1e 0 12px,#161616 12px 24px)}
.axm-industrial .n{top:8px;font-size:10px;font-weight:600;border-bottom:1px solid #2e2e2e;font-family:"Oswald",sans-serif;letter-spacing:.06em;text-transform:uppercase}
.axm-industrial .lg{display:flex;align-items:center;gap:7px;font-weight:700;font-size:16px;flex-wrap:wrap;line-height:1}
.axm-industrial .lg b{width:20px;height:20px;background:#ff8a1e;clip-path:polygon(25% 0,75% 0,100% 50%,75% 100%,25% 100%,0 50%)}
.axm-industrial .lg small{display:block;font-size:7px;color:#8a857b;letter-spacing:.2em;width:100%;padding-left:27px;margin-top:-4px}
.axm-industrial .ph{border:1px solid #3a3a3a;padding:6px 10px;color:#ff8a1e;font-family:"JetBrains Mono",monospace;font-size:10px;letter-spacing:0}
.axm-industrial .ey{position:absolute;left:28px;top:84px;font-family:"JetBrains Mono",monospace;font-size:10px;color:#ff8a1e}
.axm-industrial .h{position:absolute;left:26px;top:102px;font-family:"Oswald",sans-serif;font-weight:700;font-size:58px;line-height:.95;text-transform:uppercase;letter-spacing:-.005em;color:#f4f1ea}
.axm-industrial .h em{font-style:normal;color:#ff8a1e}
.axm-industrial .p{position:absolute;left:28px;top:238px;width:300px;font-size:12px;line-height:1.55;color:#b4afa4}
.axm-industrial .cta{position:absolute;left:28px;top:304px;background:#ff8a1e;color:#161616;font-family:"Oswald",sans-serif;font-weight:600;font-size:13px;letter-spacing:.06em;text-transform:uppercase;padding:11px 18px}
.axm-industrial .plate{position:absolute;right:34px;top:92px;width:200px;height:250px;background:linear-gradient(160deg,#3a3a3a,#232323);border:1px solid #444;padding:24px 20px;display:flex;flex-direction:column;gap:6px}
.axm-industrial .plate i{display:block;position:absolute;width:8px;height:8px;border-radius:50%;background:radial-gradient(circle at 35% 35%,#888,#333)}
.axm-industrial .plate i:nth-child(1){left:8px;top:8px}.axm-industrial .plate i:nth-child(2){right:8px;top:8px}.axm-industrial .plate i:nth-child(3){left:8px;bottom:8px}.axm-industrial .plate i:nth-child(4){right:8px;bottom:8px}
.axm-industrial .plate .pl{font-family:"JetBrains Mono",monospace;font-size:10px;color:#ff8a1e}
.axm-industrial .plate b{font-family:"Oswald",sans-serif;font-weight:700;font-size:86px;line-height:.9;color:#f4f1ea}
.axm-industrial .plate span{font-family:"Oswald",sans-serif;font-size:14px;letter-spacing:.08em;line-height:1.15}
.axm-industrial .plate .cert{margin-top:auto;border-top:1px dashed #555;padding-top:10px;font-size:10px;color:#8a857b;font-family:"JetBrains Mono",monospace}

/* 16 blueprint */
.axm-blueprint{background:#0f2d5c;color:#fff;font-family:"Figtree",sans-serif}
.axm-blueprint .gp{position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.14) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.14) 1px,transparent 1px),linear-gradient(rgba(255,255,255,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.05) 1px,transparent 1px);background-size:100px 100px,100px 100px,20px 20px,20px 20px}
.axm-blueprint .n{font-size:11px;font-weight:600}
.axm-blueprint .lg{display:flex;align-items:center;gap:6px;font-family:"Bricolage Grotesque",sans-serif;font-weight:800;font-size:16px}
.axm-blueprint .lg b{width:18px;height:18px;border-radius:50% 50% 50% 0;background:#ff8a3d;transform:rotate(-45deg)}
.axm-blueprint .lg small{color:#3dd6d8;font-weight:800;font-size:16px}
.axm-blueprint .bt{background:#ff8a3d;color:#0f2d5c;border-radius:30px;padding:8px 14px;font-weight:700}
.axm-blueprint .ey{position:absolute;left:30px;top:82px;font-family:"DM Mono",monospace;font-size:10px;color:#3dd6d8;letter-spacing:.08em;text-transform:uppercase}
.axm-blueprint .h{position:absolute;left:28px;top:104px;font-family:"Bricolage Grotesque",sans-serif;font-weight:800;font-size:50px;line-height:.98;letter-spacing:-.035em}
.axm-blueprint .h em{font-style:normal;color:#ff8a3d}
.axm-blueprint .p{position:absolute;left:30px;top:224px;width:290px;font-size:12.5px;line-height:1.55;color:#c5d3ea}
.axm-blueprint .cta{position:absolute;left:30px;top:290px;background:#ff8a3d;color:#0f2d5c;border-radius:30px;padding:12px 20px;font-weight:800;font-size:13px;box-shadow:0 0 0 4px rgba(255,138,61,.25)}
.axm-blueprint .pipe{position:absolute;right:30px;top:90px;width:260px;height:220px}
.axm-blueprint .dim{position:absolute;right:120px;top:68px;font-family:"DM Mono",monospace;font-size:10px;color:#3dd6d8;border-bottom:1px solid #3dd6d8;padding:0 8px 2px}
.axm-blueprint .stk{position:absolute;right:56px;top:254px;width:96px;height:96px;border-radius:50%;background:#fff;color:#0f2d5c;display:flex;flex-direction:column;align-items:center;justify-content:center;font-family:"Bricolage Grotesque",sans-serif;font-weight:800;font-size:26px;line-height:1;transform:rotate(-10deg);box-shadow:0 0 0 3px #0f2d5c,0 0 0 5px #fff}
.axm-blueprint .stk small{font-family:"DM Mono",monospace;font-size:8px;font-weight:500;margin-top:4px;color:#ff8a3d}

/* 17 festive */
.axm-festive{background:#1b0f0a;color:#fbe9d0;font-family:"DM Sans",sans-serif}
.axm-festive .bk{position:absolute;right:60px;top:120px;width:300px;height:300px;border-radius:50%;background:radial-gradient(closest-side,rgba(242,165,22,.55),transparent)}
.axm-festive .b2{right:-60px;top:20px;width:260px;height:260px;background:radial-gradient(closest-side,rgba(224,69,123,.35),transparent)}
.axm-festive .b3{right:220px;top:250px;width:180px;height:180px;background:radial-gradient(closest-side,rgba(43,179,163,.3),transparent)}
.axm-festive .b4{right:120px;top:200px;width:200px;height:200px;background:radial-gradient(circle at 50% 50%,#7a3b1a 0,#3a1a0c 40%,transparent 70%)}
.axm-festive .vg{position:absolute;inset:0;background:linear-gradient(90deg,#1b0f0a 30%,rgba(27,15,10,.2) 70%,rgba(27,15,10,.6))}
.axm-festive .pp{position:absolute;left:-10px;right:-10px;top:56px;height:46px;display:flex;justify-content:space-between;z-index:2;border-top:1px solid rgba(251,233,208,.5)}
.axm-festive .pp i{display:block;width:44px;height:40px;background:#e0457b;clip-path:polygon(0 0,100% 0,100% 80%,87% 100%,75% 80%,62% 100%,50% 80%,37% 100%,25% 80%,12% 100%,0 80%);-webkit-mask:radial-gradient(circle at 50% 40%,transparent 5px,#000 6px);mask:radial-gradient(circle at 50% 40%,transparent 5px,#000 6px)}
.axm-festive .pp i:nth-child(4n+2){background:#f2a516}.axm-festive .pp i:nth-child(4n+3){background:#2bb3a3}.axm-festive .pp i:nth-child(4n){background:#fbe9d0}
.axm-festive .pp i:nth-child(odd){transform:translateY(3px)}
.axm-festive .n{font-size:11px;font-weight:500;color:#f3d9b8}
.axm-festive .lg{display:flex;flex-direction:column;font-family:"Fraunces",serif;font-size:18px;font-weight:600;color:#fff;line-height:1}
.axm-festive .lg small{font-family:"DM Sans",sans-serif;font-size:7px;letter-spacing:.24em;text-transform:uppercase;color:#f2a516;margin-top:3px}
.axm-festive .bt{background:#f2a516;color:#1b0f0a;border-radius:30px;padding:8px 14px;font-weight:700}
.axm-festive .ey{position:absolute;left:30px;top:126px;font-size:10px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:#f2a516;z-index:2}
.axm-festive .h{position:absolute;left:28px;top:146px;font-family:"Fraunces",serif;font-size:50px;line-height:.98;letter-spacing:-.02em;color:#fff;z-index:2}
.axm-festive .h em{font-style:italic;color:#f2a516}
.axm-festive .cta{position:absolute;left:30px;top:304px;background:#f2a516;color:#1b0f0a;border-radius:30px;padding:12px 20px;font-weight:700;font-size:13px;z-index:2}
.axm-festive .play{position:absolute;left:186px;top:306px;display:flex;align-items:center;gap:8px;font-size:12px;font-weight:600;z-index:2}
.axm-festive .play b{width:32px;height:32px;border-radius:50%;border:1.5px solid #fbe9d0;position:relative}
.axm-festive .play b:after{content:"";position:absolute;left:12px;top:9px;border-left:10px solid #fbe9d0;border-top:6px solid transparent;border-bottom:6px solid transparent}

/* 18 retro y2k */
.axm-retro-y2k{background:linear-gradient(170deg,#c6f6ff 0%,#e6c8ff 50%,#ff9fe5 100%);color:#2b0a57;font-family:"Space Mono",monospace}
.axm-retro-y2k:before{content:"";position:absolute;left:-20%;right:-20%;bottom:-40px;height:160px;background-image:linear-gradient(rgba(140,30,255,.5) 2px,transparent 2px),linear-gradient(90deg,rgba(140,30,255,.5) 2px,transparent 2px);background-size:40px 26px;transform:perspective(200px) rotateX(55deg);transform-origin:bottom}
.axm-retro-y2k .st{position:absolute;width:26px;height:26px;background:#fff;clip-path:polygon(50% 0,58% 42%,100% 50%,58% 58%,50% 100%,42% 58%,0 50%,42% 42%)}
.axm-retro-y2k .s1{left:40px;top:40px}.axm-retro-y2k .s2{right:50px;top:70px;width:18px;height:18px;background:#fffb96}.axm-retro-y2k .s3{left:90px;top:300px;width:16px;height:16px}.axm-retro-y2k .s4{right:110px;top:290px;width:30px;height:30px;background:#ff6ad5}
.axm-retro-y2k .win{position:absolute;left:110px;right:110px;top:46px;background:#efe8ff;border:2px solid #2b0a57;box-shadow:6px 6px 0 #8c1eff;border-radius:6px;overflow:hidden}
.axm-retro-y2k .tb{display:flex;align-items:center;gap:4px;background:linear-gradient(90deg,#8c1eff,#ff6ad5);color:#fff;font-size:10px;font-weight:700;padding:5px 8px}
.axm-retro-y2k .tb span{margin-right:auto}
.axm-retro-y2k .tb i{display:block;width:12px;height:12px;background:#efe8ff;border:1px solid #2b0a57;border-radius:2px}
.axm-retro-y2k .bd{padding:18px 20px 20px;text-align:center}
.axm-retro-y2k .h{display:block;font-family:"Bungee",sans-serif;font-size:52px;line-height:1;background:linear-gradient(180deg,#ffffff 0%,#b9c3d6 45%,#5d6a85 50%,#e8ecf5 70%,#8f9ab3 100%);-webkit-background-clip:text;background-clip:text;color:transparent;-webkit-text-stroke:1.5px #2b0a57;filter:drop-shadow(3px 3px 0 #ff6ad5)}
.axm-retro-y2k .sub{display:block;font-family:"Bungee",sans-serif;font-size:13px;color:#8c1eff;margin-top:6px}
.axm-retro-y2k .p{display:block;font-size:11px;margin:10px 0 14px}
.axm-retro-y2k .bts{display:flex;justify-content:center;gap:10px}
.axm-retro-y2k .bts span{font-family:"Bungee",sans-serif;font-size:12px;padding:8px 14px;border:2px solid #2b0a57;border-radius:20px}
.axm-retro-y2k .b1{background:linear-gradient(180deg,#fffb96,#ffd23f);box-shadow:inset 0 -3px 0 rgba(0,0,0,.15)}
.axm-retro-y2k .b2{background:linear-gradient(180deg,#9ff3ff,#5ccfe6);box-shadow:inset 0 -3px 0 rgba(0,0,0,.15)}
.axm-retro-y2k .mq{position:absolute;left:0;right:0;bottom:22px;background:#2b0a57;color:#fffb96;font-size:11px;font-weight:700;white-space:nowrap;padding:6px 0;overflow:hidden}
.axm-retro-y2k .mq span{display:inline-block;padding-left:20px}
.axm-retro-y2k .cd{position:absolute;right:30px;top:180px;width:90px;height:90px;border-radius:50%;background:radial-gradient(circle,#efe8ff 0 9px,#2b0a57 9px 11px,transparent 11px),conic-gradient(from 30deg,#c6f6ff,#ff9fe5,#fffb96,#9ff3ff,#e6c8ff,#c6f6ff);border:2px solid #2b0a57;display:none}
.axm-festive .plate{position:absolute;right:-24px;top:232px;width:240px;height:240px;border-radius:50%;background:radial-gradient(circle,#3a2418 0 52%,#e8d9c2 53% 60%,#c9b79c 61% 63%,transparent 64%);z-index:1;opacity:.95}
.axm-festive .plate i{display:block;position:absolute;width:70px;height:34px;border-radius:50% 50% 40% 40%;background:linear-gradient(180deg,#e9b55c,#b9772f);top:84px;left:50px;transform:rotate(-14deg);box-shadow:0 -6px 0 -2px #6a8f3a inset}
.axm-festive .plate i:nth-child(2){left:110px;top:100px;transform:rotate(18deg)}
.axm-festive .plate i:nth-child(3){left:76px;top:130px;transform:rotate(4deg)}
.axm-festive .plate i:nth-child(4){width:16px;height:16px;border-radius:50%;background:#2bb3a3;left:150px;top:70px;box-shadow:none}
.axm-festive .plate i:nth-child(5){width:20px;height:20px;border-radius:50%;background:#e0457b;left:60px;top:150px;box-shadow:none}
.axm-retro-70s{background:#fff4e2;color:#2b1a33;font-family:"Outfit",sans-serif}
.axm-retro-70s .rb{position:absolute;right:-200px;bottom:-250px;width:500px;height:500px;border-radius:50%;background:#2b1a33}
.axm-retro-70s .rb i{position:absolute;border-radius:50%;inset:24px;background:#f2643d}
.axm-retro-70s .rb i:nth-child(2){inset:48px;background:#ffc93c}
.axm-retro-70s .rb i:nth-child(3){inset:72px;background:#1fa3a0}
.axm-retro-70s .rb i:nth-child(4){inset:96px;background:#fff4e2}
.axm-retro-70s .sun{position:absolute;right:30px;top:220px;width:110px;height:110px;border-radius:50%;background:linear-gradient(180deg,#ffc93c,#f2643d);box-shadow:0 0 0 10px rgba(255,201,60,.25)}
.axm-retro-70s .grd{position:absolute;right:0;bottom:0;width:330px;height:60px;background:linear-gradient(180deg,#e9a86a,#c9733f);clip-path:polygon(0 60%,30% 30%,55% 50%,80% 20%,100% 35%,100% 100%,0 100%)}
.axm-retro-70s .cac{position:absolute;right:170px;bottom:26px;width:24px;height:130px;border-radius:14px;background:#2b1a33;z-index:1}
.axm-retro-70s .cac i{position:absolute;width:18px;height:56px;border-radius:10px;background:#2b1a33;left:-28px;top:44px}
.axm-retro-70s .cac i:after{content:"";position:absolute;left:0;bottom:-6px;width:30px;height:16px;border-radius:0 0 10px 10px;background:#2b1a33}
.axm-retro-70s .cac i:nth-child(2){left:36px;top:22px;height:48px}
.axm-retro-70s .cac i:nth-child(2):after{left:-12px}
.axm-retro-70s .n{font-size:11px;font-weight:600}
.axm-retro-70s .lg{display:flex;flex-direction:column;font-family:"Shrikhand",serif;font-size:21px;color:#f2643d;line-height:1}
.axm-retro-70s .lg small{font-family:"Outfit",sans-serif;font-size:7px;letter-spacing:.2em;text-transform:uppercase;color:#2b1a33;margin-top:3px;font-weight:600}
.axm-retro-70s .bt{background:#2b1a33;color:#fff4e2;border-radius:30px;padding:8px 14px;font-weight:700}
.axm-retro-70s .ey{position:absolute;left:30px;top:94px;font-size:10px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:#1fa3a0}
.axm-retro-70s .h{position:absolute;left:28px;top:114px;font-family:"Shrikhand",serif;font-size:40px;line-height:1.02;color:#2b1a33;z-index:2}
.axm-retro-70s .h em{font-style:normal;color:#f2643d}
.axm-retro-70s .p{position:absolute;left:30px;top:216px;width:230px;font-size:13px;line-height:1.4;color:#5e3a63}
.axm-retro-70s .cta{position:absolute;left:30px;top:282px;background:#f2643d;color:#fff4e2;border-radius:30px;padding:12px 20px;font-weight:700;font-size:13px;box-shadow:4px 4px 0 #2b1a33}
.axm-retro-70s .bdg{position:absolute;left:206px;top:270px;width:64px;height:64px;border-radius:50%;background:#ffc93c;color:#2b1a33;font-size:8px;font-weight:700;text-transform:uppercase;text-align:center;line-height:1.05;padding-top:12px;transform:rotate(-12deg);z-index:2}
.axm-retro-70s .bdg b{font-family:"Shrikhand",serif;font-size:17px;text-transform:none;line-height:1.1}
/* AdaptExec Style Picker (shared by the Hub, style links, discovery and forms) */
.axsp{--axsp-ink:#14181E;--axsp-gray:#69727D;--axsp-line:#E4E2DD;--axsp-soft:#F6F5F3;--axsp-o:#EF5D0F;--axsp-od:#C8490A;--axsp-osoft:#FFF1E8;color:var(--axsp-ink);font-size:14px;line-height:1.5;position:relative}
.axsp *,.axsp *::before,.axsp *::after{box-sizing:border-box}
.axsp button{font:inherit;cursor:pointer}
.axsp h3,.axsp h4,.axsp p{margin:0}
.axsp-fr{background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 0 0 1px rgba(0,0,0,.08),0 14px 30px -20px rgba(0,0,0,.45);position:relative}
.axsp-bar{height:22px;display:flex;align-items:center;gap:5px;padding:0 9px;background:#f1efeb;border-bottom:1px solid #e6e3dc}
.axsp-bar i{width:7px;height:7px;border-radius:50%;background:#d7d3cb;flex:none}
.axsp-bar i:first-child{background:#ff6159}.axsp-bar i:nth-child(2){background:#ffbd2e}.axsp-bar i:nth-child(3){background:#28c840}
.axsp-bar span{margin-left:8px;font-size:10px;color:#8b877f;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.axsp-vp{position:relative;aspect-ratio:16/10;overflow:hidden;--k:.5;background:#eee}
@supports not (aspect-ratio:1){.axsp-vp{height:0;padding-top:62.5%}}

.axsp-tabs{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px}
.axsp-tabs button{display:flex;flex-direction:column;align-items:flex-start;gap:1px;padding:12px 16px;border:1px solid var(--axsp-line);border-radius:12px;background:#fff;color:var(--axsp-ink);font-weight:700;font-size:15px;text-align:left}
.axsp-tabs button small{font-weight:500;font-size:12px;color:var(--axsp-gray)}
.axsp-tabs button[aria-selected="true"]{border-color:var(--axsp-ink);background:var(--axsp-ink);color:#fff}
.axsp-tabs button[aria-selected="true"] small{color:rgba(255,255,255,.7)}
.axsp-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border:0;border-radius:10px;background:var(--axsp-o);color:#fff;font-weight:700;font-size:14px;text-decoration:none;white-space:nowrap}
.axsp-btn:hover{background:var(--axsp-od)}
.axsp-btn:disabled{opacity:.6;cursor:wait}
.axsp-btn svg,.axsp-live svg{width:14px;height:14px}
.axsp-link{background:none;border:0;padding:4px 2px;color:var(--axsp-ink);font-weight:600;text-decoration:underline;text-underline-offset:3px}
.axsp :focus-visible{outline:2px solid var(--axsp-o);outline-offset:2px}

/* quiz */
.axsp-quiz{background:#111;color:#fff;border-radius:16px;padding:22px}
.axsp-qtop{display:flex;align-items:center;gap:14px;font-size:12px;color:rgba(255,255,255,.65);letter-spacing:.06em;text-transform:uppercase;font-weight:600}
.axsp-prog{flex:1;height:4px;border-radius:2px;background:rgba(255,255,255,.15);overflow:hidden}
.axsp-prog i{display:block;height:100%;background:var(--axsp-o);transition:width .3s}
.axsp-qq{font-size:24px;line-height:1.2;font-weight:800;margin-top:16px;outline:none;color:#fff}
.axsp-qh{color:rgba(255,255,255,.65);margin-top:4px}
.axsp-opts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:16px}
.axsp-opt{display:flex;flex-direction:column;gap:8px;padding:10px;border:1px solid rgba(255,255,255,.12);border-radius:12px;background:#1b1b1b;color:#fff;text-align:left;transition:border-color .15s,transform .15s}
.axsp-opt:hover{border-color:rgba(255,255,255,.4)}
.axsp-opt[aria-pressed="true"]{border-color:var(--axsp-o);box-shadow:0 0 0 1px var(--axsp-o)}
.axsp-opt strong{display:block;font-size:14px}
.axsp-opt small{display:block;font-size:12px;color:rgba(255,255,255,.6);line-height:1.35}
.axsp-opts.txt .axsp-opt{padding:14px}
.axsp-vis{height:60px;border-radius:9px;background:#262626;display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative}
.axsp-vis svg{width:32px;height:32px;color:var(--axsp-o)}
.axsp-vis .sw{display:flex;width:100%;height:100%}.axsp-vis .sw i{flex:1}
.axsp-vis .aa{font-size:34px;line-height:1;color:#fff}
.axsp-vis .wf{display:grid;gap:5px;width:70%}
.axsp-vis .wf i{display:block;height:6px;border-radius:3px;background:rgba(255,255,255,.35)}
.axsp-vis .wf i.t{height:10px;width:60%;background:#fff}
.axsp-vis .wf.g{grid-template-columns:repeat(3,1fr)}.axsp-vis .wf.g i{height:14px}
.axsp-vis .mo{width:14px;height:14px;border-radius:50%;background:var(--axsp-o)}
.axsp-vis .mo.subtle{animation:axsp-fade 2.4s ease-in-out infinite}
.axsp-vis .mo.smooth{animation:axsp-slide 2.4s cubic-bezier(.65,0,.35,1) infinite}
.axsp-vis .mo.lively{animation:axsp-bounce 1.3s ease-in-out infinite}
@keyframes axsp-fade{0%,100%{opacity:.25}50%{opacity:1}}
@keyframes axsp-slide{0%,100%{transform:translateX(-30px)}50%{transform:translateX(30px)}}
@keyframes axsp-bounce{0%,100%{transform:translate(-34px,6px)}25%{transform:translate(-12px,-14px)}50%{transform:translate(12px,6px)}75%{transform:translate(34px,-12px)}}
@media (prefers-reduced-motion:reduce){.axsp-vis .mo{animation:none!important}}
.axsp-qnav{display:flex;align-items:center;gap:10px;margin-top:18px}
.axsp-qnav .sp{flex:1}
.axsp-quiz .axsp-link{color:#fff}

/* results + gallery */
.axsp-res-hd{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin-bottom:16px}
.axsp-eye{display:block;font-size:11px;letter-spacing:.12em;text-transform:uppercase;font-weight:700;color:var(--axsp-o)}
.axsp-res-hd h3{font-size:26px;font-weight:800;line-height:1.15;outline:none}
.axsp-res-hd p{color:var(--axsp-gray);margin-top:4px}
.axsp-res-acts{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.axsp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:16px}
.axsp-grid--res{grid-template-columns:repeat(3,minmax(0,1fr))}
.axsp-card{background:#fff;border:1px solid var(--axsp-line);border-radius:14px;overflow:hidden;display:flex;flex-direction:column}
.axsp-card .axsp-fr{border-radius:0;box-shadow:none;border-bottom:1px solid var(--axsp-line)}
.axsp-card.is-on{border-color:var(--axsp-o);box-shadow:0 0 0 1px var(--axsp-o)}
.axsp-card.top{border-color:var(--axsp-ink)}
.axsp-cb{padding:12px 14px 14px;display:flex;flex-direction:column;gap:6px;flex:1}
.axsp-num{font-size:11px;font-weight:700;color:var(--axsp-gray);letter-spacing:.08em}
.axsp-card h4{font-size:16px;font-weight:800;line-height:1.2}
.axsp-vibe{color:#3b4350;font-size:13px}
.axsp-why{font-size:12.5px;color:var(--axsp-gray)}
.axsp-feels{font-size:12px;color:var(--axsp-gray)}
.axsp-rank{display:flex;justify-content:space-between;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--axsp-o)}
.axsp-meter{display:block;height:4px;border-radius:2px;background:var(--axsp-soft);overflow:hidden}
.axsp-meter i{display:block;height:100%;background:var(--axsp-o)}
.axsp-pal{display:flex;gap:4px}
.axsp-pal i{width:18px;height:18px;border-radius:50%;box-shadow:inset 0 0 0 1px rgba(0,0,0,.12)}
.axsp-more{font-size:12.5px;color:#3b4350;display:grid;gap:4px;padding:8px 10px;background:var(--axsp-soft);border-radius:8px}
.axsp-more b{color:var(--axsp-ink)}
.axsp-acts{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:auto;padding-top:4px}
.axsp-heart{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border:1px solid var(--axsp-line);border-radius:999px;background:#fff;color:var(--axsp-ink);font-weight:700;font-size:13px}
.axsp-heart svg{width:15px;height:15px}
.axsp-heart em{font-style:normal}
.axsp-heart[aria-pressed="true"]{background:var(--axsp-o);border-color:var(--axsp-o);color:#fff}
.axsp-heart[aria-pressed="true"] svg{fill:currentColor}
.axsp-live{display:inline-flex;align-items:center;gap:4px;font-weight:700;font-size:13px;color:#046BD2;text-decoration:none}
.axsp-flt{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-bottom:14px}
.axsp-flt button{padding:7px 13px;border:1px solid var(--axsp-line);border-radius:999px;background:#fff;color:var(--axsp-ink);font-weight:600;font-size:13px}
.axsp-flt button[aria-pressed="true"]{background:var(--axsp-ink);border-color:var(--axsp-ink);color:#fff}
.axsp-count{margin-left:auto;font-size:12px;color:var(--axsp-gray)}

/* shortlist tray */
.axsp-tray{position:sticky;bottom:0;z-index:5;display:none;gap:14px;align-items:flex-end;justify-content:space-between;margin-top:16px;padding:12px 14px;background:#fff;border:1px solid var(--axsp-line);border-radius:14px;box-shadow:0 -10px 30px -18px rgba(0,0,0,.35)}
.axsp-tray.on{display:flex}
.axsp-tray.is-empty{position:relative;box-shadow:none}
.axsp-tray-l{flex:1;min-width:0;display:grid;gap:6px}
.axsp-tray-l strong{font-size:13px}
.axsp-chips{display:flex;flex-wrap:wrap;gap:6px}
.axsp-chip{display:inline-flex;align-items:center;gap:4px;padding:4px 6px 4px 10px;border-radius:999px;background:var(--axsp-osoft);color:var(--axsp-od);font-weight:700;font-size:12.5px}
.axsp-chip button{border:0;background:none;color:inherit;font-size:16px;line-height:1;padding:0 4px}
.axsp-hint{font-size:12.5px;color:var(--axsp-gray)}
.axsp-notes{display:grid;gap:3px;font-size:12px;font-weight:600;color:var(--axsp-gray)}
.axsp-notes textarea{width:100%;min-height:44px;padding:8px 10px;border:1px solid var(--axsp-line);border-radius:8px;font:inherit;font-size:13px;color:var(--axsp-ink);resize:vertical}
.axsp-msg{position:absolute;left:50%;bottom:calc(100% + 8px);transform:translateX(-50%);padding:8px 14px;border-radius:8px;background:var(--axsp-ink);color:#fff;font-size:13px;font-weight:600;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .2s}
.axsp-msg.on{opacity:1}

/* compact (lead forms) */
.axsp--compact .axsp-grid{grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px}
.axsp--compact .axsp-cb{padding:8px 10px 10px;gap:2px}
.axsp--compact .axsp-card h4{font-size:13.5px}
.axsp--compact .axsp-vibe{font-size:11.5px;line-height:1.35;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.axsp--compact .axsp-num{display:none}
.axsp-cardpick{display:block;width:100%;padding:0;border:0;background:none;position:relative;text-align:left}
.axsp-check{position:absolute;top:8px;right:8px;width:24px;height:24px;border-radius:50%;background:rgba(255,255,255,.92);box-shadow:0 0 0 1.5px rgba(0,0,0,.25)}
.axsp-cardpick[aria-pressed="true"] .axsp-check{background:var(--axsp-o);box-shadow:none}
.axsp-cardpick[aria-pressed="true"] .axsp-check::after{content:"";position:absolute;left:8px;top:4px;width:6px;height:11px;border:solid #fff;border-width:0 2.5px 2.5px 0;transform:rotate(45deg)}
.axsp--compact .axsp-flt button{padding:5px 10px;font-size:12px}
.axsp--compact .axsp-tray{position:static;box-shadow:none}

/* present (client-facing, full screen in the Hub) */
.axsp--present{font-size:15px}
.axsp--present .axsp-qq{font-size:32px}
.axsp--present .axsp-grid{grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px}
.axsp--present .axsp-card h4{font-size:19px}
.axsp--present .axsp-quiz{padding:30px}

/* saved picks */
.axsp-thumbs{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px}
.axsp-thumbs{align-items:start}
.axsp-thumb{margin:0;display:grid;gap:6px;align-content:start}
.axsp-moreall{text-align:center;margin-top:12px}
.axsp-thumb figcaption{display:grid;gap:1px;font-size:12px;color:var(--axsp-gray)}
.axsp-thumb figcaption b{font-size:13px;color:var(--axsp-ink)}
.axsp-thumb figcaption a{color:#046BD2;font-weight:600;text-decoration:none}

@media (max-width:860px){
	.axsp-opts{grid-template-columns:repeat(2,minmax(0,1fr))}
	.axsp-grid--res{grid-template-columns:1fr}
	.axsp-qq,.axsp--present .axsp-qq{font-size:21px}
	.axsp-quiz,.axsp--present .axsp-quiz{padding:16px}
	.axsp-tray{flex-direction:column;align-items:stretch}
	.axsp-tray-r .axsp-btn{width:100%;justify-content:center}
	.axsp--present .axsp-grid{grid-template-columns:1fr}
}
@media (max-width:480px){
	.axsp-tabs button{padding:10px 12px;font-size:14px}
	.axsp-grid{grid-template-columns:1fr}
	.axsp--compact .axsp-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
	.axsp-vis{height:48px}
}
AXH_STYLE_CSS;
}

function axh_style_js() {
	return <<<'AXH_STYLE_JS'
/* AdaptExec Style Picker: the website style gallery and Style Finder quiz as
   one reusable widget. Used in the Hub (with a client, saved to a lead), on a
   client's own style link, in the discovery questionnaire and on lead forms.
   Data: window.AXStyleData (the same styles as adaptexec.com/website-styles/). */
(function () {
	'use strict';
	var D = window.AXStyleData;
	if (!D || !D.styles) return;
	var d = document, S = D.styles, Q = D.quiz || [], BY = {};
	S.forEach(function (s, i) { s._i = i; BY[s.slug] = s; });
	var RM = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

	function esc(t) {
		return String(t === null || t === undefined ? '' : t).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	var ARR = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M8 7h9v9"/></svg>';
	var HEART = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 0 0 0-7.8z"/></svg>';

	/* Fonts for the mockups load once, the first time a picker is shown. */
	var fontsLoaded = false;
	function loadFonts() {
		if (fontsLoaded) return;
		fontsLoaded = true;
		(D.fonts_css || []).forEach(function (u) {
			if (d.querySelector('link[href="' + u + '"]')) return;
			var l = d.createElement('link'); l.rel = 'stylesheet'; l.href = u; d.head.appendChild(l);
		});
	}

	/* Every mockup is a 640x400 canvas scaled to its frame. */
	var ro = window.ResizeObserver ? new ResizeObserver(function (es) {
		es.forEach(function (e) { var w = e.contentRect.width; if (w) e.target.style.setProperty('--k', (w / 640).toFixed(4)); });
	}) : null;
	function watchAll(root) {
		Array.prototype.forEach.call(root.querySelectorAll('.axsp-vp'), function (vp) {
			if (vp._w) return;
			vp._w = 1;
			if (ro) ro.observe(vp); else vp.style.setProperty('--k', ((vp.clientWidth || 320) / 640).toFixed(4));
		});
	}
	function frameHtml(slug, bar) {
		var s = BY[slug];
		if (!s) return '';
		return '<div class="axsp-fr">' + (bar === false ? '' : '<div class="axsp-bar"><i></i><i></i><i></i><span>' + esc(s.domain) + '</span></div>') +
			'<div class="axsp-vp" aria-hidden="true">' + (s.mockup_html || '') + '</div></div>';
	}

	/* Scoring, as on the public page: weight x fit (0-3) per answered question. */
	function whyOf(q, v) {
		for (var i = 0; i < q.opts.length; i++) if (q.opts[i].v === v) return q.opts[i].why || q.opts[i].label;
		return v;
	}
	function score(ans) {
		ans = ans || {};
		var out = S.map(function (s) {
			var sc = 0, mx = 0, why = [];
			Q.forEach(function (q) {
				var a = ans[q.id];
				if (!a) return;
				mx += 3 * q.weight;
				var w = ((s.w || {})[q.id] || {})[a] || 0;
				sc += w * q.weight;
				if (w >= 2) why.push({ k: w * q.weight, t: whyOf(q, a) });
			});
			why.sort(function (x, y) { return y.k - x.k; });
			return { slug: s.slug, pct: mx ? Math.round(sc / mx * 100) : 0, why: why.map(function (x) { return x.t; }) };
		});
		out.sort(function (a, b) { return b.pct - a.pct || BY[a.slug]._i - BY[b.slug]._i; });
		return out;
	}
	function answered(ans) { return Object.keys(ans || {}).filter(function (k) { return ans[k]; }).length; }

	/* Quiz option visuals (same as the public Style Finder). */
	var ICON = { calm: '<path d="M2 10c3-4 6-4 10 0s7 4 10 0M2 16c3-4 6-4 10 0s7 4 10 0"/>', bold: '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>', playful: '<circle cx="12" cy="12" r="9"/><path d="M8 14c1 1.6 2.3 2.4 4 2.4s3-.8 4-2.4M9 9.5h.01M15 9.5h.01"/>', premium: '<path d="M6 3h12l4 6-10 12L2 9z"/><path d="M2 9h20M9 3l3 18 3-18"/>', trust: '<path d="M12 3l8 3v6c0 5-3.4 8-8 9-4.6-1-8-4-8-9V6z"/><path d="m8.5 12 2.5 2.5 4.5-5"/>', warm: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>' };
	var SW = { neutral: ['#f5f4f1', '#d9d6cf', '#8a8780', '#141414'], dark: ['#07070c', '#1d1714', '#7c5cff', '#22d3ee'], bright: ['#ff5a1f', '#c6f432', '#1f6feb', '#ffd23f'], pastel: ['#c9b8ff', '#b8e3ff', '#ffd1dc', '#d8ffef'], earthy: ['#b85c38', '#6b7f4e', '#d8c4a4', '#2f3b2a'], classic: ['#1a1f2b', '#8a6a3b', '#f6f1e8', '#0f2a4a'] };
	var AA = { sans: '"Instrument Sans",sans-serif;font-weight:700', serif: '"Libre Caslon Display",serif', display: '"Archivo Black",sans-serif', hand: 'Caveat,cursive;font-weight:500', mono: '"Space Mono",monospace', condensed: 'Oswald,sans-serif;font-weight:700' };
	function vis(q, v) {
		if (q === 'feel' && ICON[v]) return '<span class="axsp-vis"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + ICON[v] + '</svg></span>';
		if (q === 'color' && SW[v]) return '<span class="axsp-vis"><span class="sw">' + SW[v].map(function (c) { return '<i style="background:' + c + '"></i>'; }).join('') + '</span></span>';
		if (q === 'type' && AA[v]) return '<span class="axsp-vis"><span class="aa" style="font-family:' + AA[v] + '">Aa</span></span>';
		if (q === 'density') {
			var n = v === 'airy' ? '<i class="t"></i><i style="width:80%"></i>' : v === 'balanced' ? '<i class="t"></i><i></i><i style="width:85%"></i><i style="width:70%"></i>' : '<i></i><i></i><i></i><i></i><i></i><i></i>';
			return '<span class="axsp-vis"><span class="wf' + (v === 'packed' ? ' g' : '') + '">' + n + '</span></span>';
		}
		if (q === 'motion') return '<span class="axsp-vis"><span class="mo ' + esc(v) + '"></span></span>';
		return '';
	}

	function names(slugs) { return (slugs || []).filter(function (s) { return BY[s]; }).map(function (s) { return BY[s].name; }); }

	/**
	 * Mount a picker.
	 * o.value     { shortlist:[], answers:{}, notes:'' }
	 * o.compact   small cards for forms (no details, no notes)
	 * o.present   big, client-facing
	 * o.notes     show a notes box
	 * o.max       most styles on the shortlist (6; 3 when compact)
	 * o.start     'quiz' | 'browse'
	 * o.saveLabel + o.onSave(value) -> Promise   adds a save button
	 * o.onChange(value)
	 * o.intro     html shown above the tabs
	 */
	function mount(el, o) {
		o = o || {};
		loadFonts();
		var v = o.value || {};
		var st = {
			tab: o.start || (answered(v.answers) ? 'results' : (o.compact ? 'browse' : 'quiz')),
			step: 0,
			ans: JSON.parse(JSON.stringify(v.answers || {})),
			short: (v.shortlist || []).filter(function (s) { return BY[s]; }),
			notes: v.notes || '',
			mood: 'all',
			open: {},
			saving: false,
			dirty: false
		};
		var max = o.max || (o.compact ? 3 : 6);
		el.classList.add('axsp');
		if (o.compact) el.classList.add('axsp--compact');
		if (o.present) el.classList.add('axsp--present');

		function value() {
			var top = answered(st.ans) ? score(st.ans).slice(0, 3).map(function (r) { return { slug: r.slug, pct: r.pct }; }) : [];
			return { shortlist: st.short.slice(), answers: JSON.parse(JSON.stringify(st.ans)), top: top, notes: st.notes };
		}
		function changed() { st.dirty = true; if (o.onChange) o.onChange(value()); }
		function isOn(slug) { return st.short.indexOf(slug) > -1; }
		function toggle(slug) {
			var k = st.short.indexOf(slug);
			if (k > -1) st.short.splice(k, 1);
			else {
				if (st.short.length >= max) { flash('You can shortlist up to ' + max + '. Remove one first.'); return; }
				st.short.push(slug);
			}
			changed();
			render();
		}
		function flash(msg) {
			var t = el.querySelector('.axsp-msg');
			if (!t) return;
			t.textContent = msg;
			t.classList.add('on');
			clearTimeout(t._t);
			t._t = setTimeout(function () { t.classList.remove('on'); }, 3200);
		}

		function heartBtn(slug) {
			var on = isOn(slug);
			return '<button type="button" class="axsp-heart" data-sp-pick="' + slug + '" aria-pressed="' + (on ? 'true' : 'false') + '">' + HEART +
				'<em>' + (on ? 'Shortlisted' : (o.compact ? 'Pick' : 'Shortlist')) + '</em></button>';
		}

		function tabs() {
			if (o.compact) return '';
			var nQ = answered(st.ans);
			return '<div class="axsp-tabs" role="tablist">' +
				'<button type="button" role="tab" data-sp-tab="' + (nQ ? 'results' : 'quiz') + '" aria-selected="' + (st.tab !== 'browse' ? 'true' : 'false') + '">Find my style <small>' + (nQ ? 'Your matches' : Q.length + ' quick questions') + '</small></button>' +
				'<button type="button" role="tab" data-sp-tab="browse" aria-selected="' + (st.tab === 'browse' ? 'true' : 'false') + '">Browse all ' + S.length + ' <small>Filter by feeling</small></button></div>';
		}

		function quizHtml() {
			var q = Q[st.step], a = st.ans[q.id], txt = !/^(feel|color|type|density|motion)$/.test(q.id);
			return '<div class="axsp-quiz"><div class="axsp-qtop"><span>Question ' + (st.step + 1) + ' of ' + Q.length + '</span>' +
				'<span class="axsp-prog"><i style="width:' + ((st.step + (a ? 1 : 0)) / Q.length * 100) + '%"></i></span></div>' +
				'<h3 class="axsp-qq" tabindex="-1">' + esc(q.q) + '</h3>' + (q.hint ? '<p class="axsp-qh">' + esc(q.hint) + '</p>' : '') +
				'<div class="axsp-opts' + (txt ? ' txt' : '') + '" role="group" aria-label="' + esc(q.q) + '">' +
				q.opts.map(function (op) {
					return '<button type="button" class="axsp-opt" data-sp-ans="' + esc(op.v) + '" aria-pressed="' + (a === op.v ? 'true' : 'false') + '">' + vis(q.id, op.v) +
						'<span><strong>' + esc(op.label) + '</strong>' + (op.sub ? '<small>' + esc(op.sub) + '</small>' : '') + '</span></button>';
				}).join('') + '</div>' +
				'<div class="axsp-qnav">' + (st.step ? '<button type="button" class="axsp-link" data-sp-q="back">&larr; Back</button>' : '') + '<span class="sp"></span>' +
				'<button type="button" class="axsp-link" data-sp-q="skip">Skip</button>' +
				(a ? '<button type="button" class="axsp-btn" data-sp-q="next">' + (st.step === Q.length - 1 ? 'See my styles' : 'Next') + ' ' + ARR + '</button>' : '') + '</div></div>';
		}

		function resultsHtml() {
			var top = score(st.ans).slice(0, 3);
			var notOn = top.filter(function (r) { return !isOn(r.slug); });
			return '<div class="axsp-res"><div class="axsp-res-hd"><div><span class="axsp-eye">Your top match</span><h3 tabindex="-1">' + esc(BY[top[0].slug].name) + '</h3>' +
				'<p>Based on ' + answered(st.ans) + ' answer' + (answered(st.ans) === 1 ? '' : 's') + '. Shortlist the ones you like, or browse all ' + S.length + ' styles.</p></div>' +
				'<div class="axsp-res-acts">' + (notOn.length ? '<button type="button" class="axsp-btn" data-sp-shortall="' + top.map(function (r) { return r.slug; }).join(',') + '">Shortlist ' + (notOn.length === top.length ? 'all ' + top.length : 'the rest') + '</button>' : '') +
				'<button type="button" class="axsp-link" data-sp-q="retake">Retake the quiz</button></div></div>' +
				'<div class="axsp-grid axsp-grid--res">' + top.map(function (r, i) {
					var s = BY[r.slug];
					var why = r.why.length ? 'Fits ' + r.why.slice(0, 3).join(', ').replace(/, ([^,]*)$/, ' and $1') + '.' : '';
					return '<article class="axsp-card' + (i === 0 ? ' top' : '') + '">' + frameHtml(s.slug) +
						'<div class="axsp-cb"><div class="axsp-rank"><span>' + (i === 0 ? 'Top match' : 'Match #' + (i + 1)) + '</span><b>' + r.pct + '%</b></div>' +
						'<h4>' + esc(s.name) + '</h4><span class="axsp-meter"><i style="width:' + r.pct + '%"></i></span>' +
						'<p class="axsp-vibe">' + esc(s.vibe) + '</p>' + (why ? '<p class="axsp-why">' + esc(why) + '</p>' : '') +
						'<div class="axsp-acts">' + heartBtn(s.slug) + (s.demo_url && !o.compact ? '<a class="axsp-live" href="' + esc(s.demo_url) + '" target="_blank" rel="noopener">See it live ' + ARR + '</a>' : '') + '</div></div></article>';
				}).join('') + '</div></div>';
		}

		function browseHtml() {
			var moods = [{ v: 'all', label: 'All' }].concat(D.moods || []);
			var all = S.filter(function (s) { return st.mood === 'all' || (s.moods || []).indexOf(st.mood) > -1; });
			// On forms, start with a handful (plus anything already picked) so the step stays short.
			var list = o.compact && !st.showAll && all.length > 6 ? all.filter(function (s, i) { return i < 6 || isOn(s.slug); }) : all;
			return '<div class="axsp-browse"><div class="axsp-flt" role="group" aria-label="Filter by feeling">' + moods.map(function (m) {
				return '<button type="button" data-sp-mood="' + m.v + '" aria-pressed="' + (st.mood === m.v ? 'true' : 'false') + '">' + esc(m.label) + '</button>';
			}).join('') + '<span class="axsp-count" aria-live="polite">' + all.length + ' style' + (all.length === 1 ? '' : 's') + '</span></div>' +
				'<div class="axsp-grid">' + list.map(function (s) {
					var open = !!st.open[s.slug];
					return '<article class="axsp-card' + (isOn(s.slug) ? ' is-on' : '') + '">' +
						(o.compact ? '<button type="button" class="axsp-cardpick" data-sp-pick="' + s.slug + '" aria-pressed="' + (isOn(s.slug) ? 'true' : 'false') + '" aria-label="' + (isOn(s.slug) ? 'Remove ' : 'Pick ') + esc(s.name) + '">' + frameHtml(s.slug, false) + '<span class="axsp-check" aria-hidden="true"></span></button>' : frameHtml(s.slug)) +
						'<div class="axsp-cb"><div class="axsp-num">' + ('0' + (s._i + 1)).slice(-2) + '</div><h4>' + esc(s.name) + '</h4>' +
						'<p class="axsp-vibe">' + esc(s.vibe) + '</p>' +
						(o.compact ? '' : '<div class="axsp-pal" aria-hidden="true">' + (s.palette || []).map(function (c) { return '<i style="background:' + esc(c) + '"></i>'; }).join('') + '</div>' +
							'<p class="axsp-feels">' + esc((s.feels_like || []).join(' · ')) + '</p>') +
						(open ? '<div class="axsp-more"><p><b>Best for</b> ' + esc((s.best_for || []).join(', ')) + '</p><p><b>Watch out</b> ' + esc(s.watch_outs || '') + '</p>' +
							'<p><b>Type</b> ' + esc(s.fonts.display) + (s.fonts.body && s.fonts.body !== s.fonts.display ? ' with ' + esc(s.fonts.body) : '') + '</p></div>' : '') +
						'<div class="axsp-acts">' + (o.compact ? '' : heartBtn(s.slug)) +
						(o.compact ? '' : '<button type="button" class="axsp-link" data-sp-open="' + s.slug + '" aria-expanded="' + (open ? 'true' : 'false') + '">' + (open ? 'Less' : 'Details') + '</button>') +
						(s.demo_url && !o.compact ? '<a class="axsp-live" href="' + esc(s.demo_url) + '" target="_blank" rel="noopener">See it live ' + ARR + '</a>' : '') + '</div></div></article>';
				}).join('') + '</div>' +
				(list.length < all.length ? '<p class="axsp-moreall"><button type="button" class="axsp-link" data-sp-all>Show all ' + all.length + ' styles</button></p>' : '') + '</div>';
		}

		function trayHtml() {
			var n = st.short.length;
			return '<div class="axsp-tray' + (n || o.onSave ? ' on' : '') + (n ? '' : ' is-empty') + '"><div class="axsp-tray-l"><strong>' + (n ? n + ' shortlisted' : 'Nothing shortlisted yet') + '</strong>' +
				'<div class="axsp-chips">' + (n ? st.short.map(function (s) {
					return '<span class="axsp-chip">' + esc(BY[s].name) + '<button type="button" data-sp-unpick="' + s + '" aria-label="Remove ' + esc(BY[s].name) + '">&times;</button></span>';
				}).join('') : '<span class="axsp-hint">' + (o.compact ? 'Tap up to ' + max + ' styles you like.' : 'Tap the heart on any style you like.') + '</span>') + '</div>' +
				(o.notes && (n || st.notes || st.noteOpen) ? '<label class="axsp-notes"><span>Notes</span><textarea data-sp-notes rows="2" placeholder="Colors they love, sites they mentioned, anything to avoid…">' + esc(st.notes) + '</textarea></label>' : '') +
				'</div>' + (o.onSave ? '<div class="axsp-tray-r"><button type="button" class="axsp-btn axsp-save"' + (st.saving ? ' disabled' : '') + '>' + (st.saving ? 'Saving…' : esc(o.saveLabel || 'Save')) + '</button></div>' : '') +
				'<div class="axsp-msg" role="status" aria-live="polite"></div></div>';
		}

		function render(focusQ) {
			var y = window.scrollY;
			el.innerHTML = (o.intro || '') + tabs() + '<div class="axsp-body">' +
				(st.tab === 'quiz' ? quizHtml() : st.tab === 'results' ? resultsHtml() : browseHtml()) + '</div>' + trayHtml();
			watchAll(el);
			if (focusQ) {
				var h = el.querySelector('.axsp-qq, .axsp-res h3');
				if (h) { try { h.focus({ preventScroll: true }); } catch (e) { h.focus(); } }
				var top = el.getBoundingClientRect().top;
				if (top < 0 && !o.present) window.scrollTo({ top: top + window.scrollY - 20, behavior: RM ? 'auto' : 'smooth' });
			} else if (!o.present) {
				window.scrollTo(0, y);
			}
		}

		el.addEventListener('click', function (e) {
			var t = e.target, b;
			if ((b = t.closest('[data-sp-tab]'))) { st.tab = b.getAttribute('data-sp-tab'); if (st.tab === 'quiz') st.step = 0; render(); return; }
			if ((b = t.closest('[data-sp-ans]'))) {
				var q = Q[st.step];
				st.ans[q.id] = b.getAttribute('data-sp-ans');
				changed();
				Array.prototype.forEach.call(el.querySelectorAll('[data-sp-ans]'), function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
				var s0 = st.step;
				setTimeout(function () { if (st.step === s0 && st.tab === 'quiz') { st.step++; if (st.step >= Q.length) st.tab = 'results'; render(true); } }, RM ? 0 : 280);
				return;
			}
			if ((b = t.closest('[data-sp-q]'))) {
				var k = b.getAttribute('data-sp-q');
				if (k === 'back') st.step = Math.max(0, st.step - 1);
				if (k === 'skip') { delete st.ans[Q[st.step].id]; changed(); st.step++; }
				if (k === 'next') st.step++;
				if (k === 'retake') { st.ans = {}; st.step = 0; st.tab = 'quiz'; changed(); }
				if (st.tab === 'quiz' && st.step >= Q.length) st.tab = answered(st.ans) ? 'results' : 'browse';
				render(true);
				return;
			}
			if ((b = t.closest('[data-sp-shortall]'))) {
				b.getAttribute('data-sp-shortall').split(',').forEach(function (s) { if (!isOn(s) && st.short.length < max) st.short.push(s); });
				changed(); render(); return;
			}
			if ((b = t.closest('[data-sp-pick]'))) { toggle(b.getAttribute('data-sp-pick')); return; }
			if ((b = t.closest('[data-sp-unpick]'))) { toggle(b.getAttribute('data-sp-unpick')); return; }
			if ((b = t.closest('[data-sp-all]'))) { st.showAll = true; render(); return; }
			if ((b = t.closest('[data-sp-mood]'))) { st.mood = b.getAttribute('data-sp-mood'); render(); return; }
			if ((b = t.closest('[data-sp-open]'))) { var sl = b.getAttribute('data-sp-open'); st.open[sl] = !st.open[sl]; render(); return; }
			if ((b = t.closest('.axsp-save')) && o.onSave && !st.saving) {
				st.saving = true; render();
				Promise.resolve(o.onSave(value())).then(function (msg) {
					st.saving = false; st.dirty = false; render(); flash(typeof msg === 'string' ? msg : 'Saved.');
				}, function (err) {
					st.saving = false; render(); flash((err && err.message) || 'Could not save. Try again.');
				});
			}
		});
		el.addEventListener('input', function (e) {
			if (e.target.hasAttribute('data-sp-notes')) { st.notes = e.target.value.slice(0, 2000); st.dirty = true; if (o.onChange) o.onChange(value()); }
		});

		render();
		return {
			get: value,
			isDirty: function () { return st.dirty; },
			set: function (nv) { nv = nv || {}; st.short = (nv.shortlist || []).filter(function (s) { return BY[s]; }); st.ans = nv.answers || {}; st.notes = nv.notes || ''; render(); },
			flash: flash,
			el: el
		};
	}

	/* A row of small thumbnails, for showing saved picks. */
	function thumbs(v, opts) {
		opts = opts || {};
		v = v || {};
		var top = {};
		(v.top || []).forEach(function (t) { top[t.slug] = t.pct; });
		var list = (v.shortlist && v.shortlist.length ? v.shortlist : (v.top || []).map(function (t) { return t.slug; })).filter(function (s) { return BY[s]; });
		if (!list.length) return '';
		var h = '<div class="axsp axsp-thumbs">' + list.map(function (s) {
			var st = BY[s];
			return '<figure class="axsp-thumb">' + frameHtml(s, false) + '<figcaption><b>' + esc(st.name) + '</b>' +
				(top[s] !== undefined ? '<span>' + top[s] + '% match</span>' : '') +
				(opts.live && st.demo_url ? '<a href="' + esc(st.demo_url) + '" target="_blank" rel="noopener">See it live</a>' : '') + '</figcaption></figure>';
		}).join('') + '</div>';
		return h;
	}

	window.AXStylePicker = {
		data: D,
		bySlug: BY,
		mount: mount,
		score: score,
		names: names,
		thumbs: thumbs,
		frame: frameHtml,
		watch: function (root) { root = root || d; if (root.querySelector('.axsp-vp')) { loadFonts(); watchAll(root); } }
	};
})();
AXH_STYLE_JS;
}
// </axh-styles>

/* =========================================================================
 * BILLING (1.7.0)
 *
 * Customers, a products & services catalog, proposals, estimates and
 * invoices, recorded payments, A/R reporting, and team commission payouts.
 *
 * Lives in this file (rather than a file of its own) because the Hub is
 * updated through the WordPress plugin editor, which can change files but
 * not add them. Tables: axh_customers, axh_items, axh_bdocs, axh_bpay,
 * axh_bevents, axh_cadjust, axh_cbatch (see AXH_Install::create_tables()).
 *
 * QuickBooks Online: every customer, item, document and payment carries
 * external_id / sync_token / synced_at so a later sync can map each row to
 * its QBO entity (Customer, Item, Estimate, Invoice, Payment). Proposals
 * map to QBO Estimates. See AXH_Bill::qbo_export().
 *
 * Card payments: none yet. A payment gateway (Stripe later) registers
 * itself on the `axh_bill_gateways` filter; see AXH_Bill::gateways().
 * ========================================================================= */

class AXH_Bill {

	const TYPES = array( 'proposal', 'estimate', 'invoice' );

	public static function init() {
		add_action( 'rest_api_init', array( 'AXH_Bill_Rest', 'routes' ) );
		add_action( 'axh_daily', array( __CLASS__, 'daily' ), 30 );
		add_action( 'axh_lead_assigned', array( __CLASS__, 'lead_assigned' ), 10, 2 );
		add_filter( 'axh_payout_block', array( __CLASS__, 'payout_block' ), 10, 3 );
	}

	public static function t( $name ) {
		return AXH_DB::table( $name );
	}

	public static function now() {
		return current_time( 'mysql' );
	}

	public static function today() {
		return wp_date( 'Y-m-d' );
	}

	protected static function clip( $v, $len ) {
		return mb_substr( trim( (string) $v ), 0, (int) $len );
	}

	protected static function txt( $v, $len = 191 ) {
		return self::clip( sanitize_text_field( (string) $v ), $len );
	}

	/** Long text kept as typed (markdown-lite), without tags. */
	protected static function longtxt( $v, $len = 20000 ) {
		$v = str_replace( array( "\r\n", "\r" ), "\n", (string) $v );
		$v = wp_kses( $v, array() );
		$v = preg_replace( '/[^\P{C}\n\t]/u', '', $v );
		return mb_substr( $v, 0, $len );
	}

	protected static function money( $v ) {
		$v = is_string( $v ) ? preg_replace( '/[^0-9.\-]/', '', $v ) : $v;
		return round( (float) $v, 2 );
	}

	protected static function date_or_null( $v ) {
		$v = trim( (string) $v );
		if ( '' === $v ) {
			return null;
		}
		$ts = strtotime( $v );
		return $ts ? gmdate( 'Y-m-d', $ts ) : null;
	}

	public static function fmt( $n ) {
		return '$' . number_format( (float) $n, 2 );
	}

	protected static function user_name( $id ) {
		$u = $id ? get_userdata( (int) $id ) : null;
		return $u ? $u->display_name : '';
	}

	/* ------------------------------------------------------------ settings */

	public static function categories() {
		return apply_filters( 'axh_bill_categories', array( 'Websites', 'CRM & Apps', 'SEO', 'Branding & Marketing', 'CVI Coaching', 'Hosting/Maintenance', 'Other' ) );
	}

	public static function units() {
		return array( 'each', 'hour', 'month', 'project' );
	}

	public static function methods() {
		return array( 'check' => 'Check', 'zelle' => 'Zelle', 'ach' => 'ACH / bank transfer', 'cash' => 'Cash', 'card' => 'Card', 'other' => 'Other' );
	}

	public static function terms_list() {
		return array(
			'receipt' => array( 'label' => 'Due on receipt', 'days' => 0 ),
			'net15'   => array( 'label' => 'Net 15', 'days' => 15 ),
			'net30'   => array( 'label' => 'Net 30', 'days' => 30 ),
			'custom'  => array( 'label' => 'Custom', 'days' => null ),
		);
	}

	public static function defaults() {
		return array(
			'doc_name'      => '',
			'address'       => '',
			'email'         => '',
			'phone'         => '',
			'website'       => 'adaptexec.com',
			'logo'          => '',
			'accent'        => '#EF5D0F',
			'notes_text'    => 'Thank you for the opportunity. Questions about anything here? Reply to the email this came with, or call us.',
			'terms_text'    => "Prices are valid until the expiry date shown.\nWork begins once the deposit is received. The final payment is due at launch or delivery.\nYou own what we build once it is paid in full. There is no required monthly plan.\nThird-party costs such as domains, hosting and paid plugins are listed separately and billed at cost.",
			'check_payable' => '',
			'pay_zelle'     => '',
			'pay_other'     => '',
			'prefix'        => array( 'proposal' => 'P-', 'estimate' => 'E-', 'invoice' => 'INV-' ),
			'next'          => array( 'proposal' => 1001, 'estimate' => 1001, 'invoice' => 1001 ),
			'due_terms'     => 'net15',
			'expiry_days'   => 30,
			'remind_on'     => 0,
			'remind_after'  => 3,
			'remind_every'  => 7,
			'remind_max'    => 3,
			'tax_on'        => 0,
			'tax_rate'      => 0,
			'tax_label'     => 'Sales tax',
			'notify_views'  => 1,
			'reply_to'      => '',
		);
	}

	public static function settings() {
		$s = get_option( 'axh_bill_settings', array() );
		$s = is_array( $s ) ? $s : array();
		$d = self::defaults();
		$out = array_merge( $d, array_intersect_key( $s, $d ) );
		foreach ( array( 'prefix', 'next' ) as $k ) {
			$out[ $k ] = array_merge( $d[ $k ], is_array( $out[ $k ] ) ? array_intersect_key( $out[ $k ], $d[ $k ] ) : array() );
		}
		return $out;
	}

	/** What goes on documents: the settings plus the fallbacks. */
	public static function business() {
		$s       = self::settings();
		$onb     = class_exists( 'AXH_Onboard' ) ? AXH_Onboard::settings() : array( 'company_legal_name' => 'AdaptExec' );
		$contact = class_exists( 'AXH_Mail' ) ? AXH_Mail::contact() : array( 'email' => 'adam@adaptexec.com', 'phone' => '(602) 584-0614', 'name' => 'Adam' );
		$name    = trim( $s['doc_name'] ) ? trim( $s['doc_name'] ) : $onb['company_legal_name'];
		return array(
			'name'      => $name,
			'address'   => trim( $s['address'] ), // Empty until AdaptExec sets it. Never a fallback.
			'email'     => is_email( $s['email'] ) ? $s['email'] : $contact['email'],
			'phone'     => trim( $s['phone'] ) ? trim( $s['phone'] ) : $contact['phone'],
			'website'   => trim( $s['website'] ),
			'logo'      => esc_url_raw( $s['logo'] ),
			'accent'    => sanitize_hex_color( $s['accent'] ) ? $s['accent'] : '#EF5D0F',
			'contact'   => $contact['name'],
		);
	}

	public static function save_settings( $in ) {
		$in  = (array) $in;
		$cur = self::settings();
		$d   = self::defaults();
		foreach ( array( 'doc_name' => 120, 'phone' => 40, 'website' => 120, 'tax_label' => 40 ) as $k => $len ) {
			if ( isset( $in[ $k ] ) ) {
				$cur[ $k ] = self::txt( $in[ $k ], $len );
			}
		}
		foreach ( array( 'address' => 300, 'notes_text' => 4000, 'terms_text' => 8000, 'pay_zelle' => 600, 'pay_other' => 1200 ) as $k => $len ) {
			if ( isset( $in[ $k ] ) ) {
				$cur[ $k ] = trim( self::longtxt( $in[ $k ], $len ) );
			}
		}
		if ( isset( $in['check_payable'] ) ) {
			$cur['check_payable'] = self::txt( $in['check_payable'], 120 );
		}
		foreach ( array( 'email', 'reply_to' ) as $k ) {
			if ( isset( $in[ $k ] ) ) {
				$cur[ $k ] = is_email( trim( (string) $in[ $k ] ) ) ? sanitize_email( $in[ $k ] ) : '';
			}
		}
		if ( isset( $in['logo'] ) ) {
			$cur['logo'] = esc_url_raw( trim( (string) $in['logo'] ) );
		}
		if ( isset( $in['accent'] ) ) {
			$cur['accent'] = sanitize_hex_color( $in['accent'] ) ? $in['accent'] : '#EF5D0F';
		}
		if ( isset( $in['due_terms'] ) && isset( self::terms_list()[ $in['due_terms'] ] ) && 'custom' !== $in['due_terms'] ) {
			$cur['due_terms'] = $in['due_terms'];
		}
		foreach ( array( 'expiry_days' => array( 1, 365 ), 'remind_after' => array( 1, 90 ), 'remind_every' => array( 1, 90 ), 'remind_max' => array( 1, 12 ) ) as $k => $r ) {
			if ( isset( $in[ $k ] ) ) {
				$cur[ $k ] = max( $r[0], min( $r[1], (int) $in[ $k ] ) );
			}
		}
		foreach ( array( 'remind_on', 'tax_on', 'notify_views' ) as $k ) {
			if ( isset( $in[ $k ] ) ) {
				$cur[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
			}
		}
		if ( isset( $in['tax_rate'] ) ) {
			$cur['tax_rate'] = max( 0, min( 30, round( (float) $in['tax_rate'], 3 ) ) );
		}
		if ( isset( $in['prefix'] ) && is_array( $in['prefix'] ) ) {
			foreach ( self::TYPES as $t ) {
				if ( isset( $in['prefix'][ $t ] ) ) {
					$p                  = preg_replace( '/[^A-Za-z0-9\-_.\/]/', '', (string) $in['prefix'][ $t ] );
					$cur['prefix'][ $t ] = '' === $p ? $d['prefix'][ $t ] : mb_substr( $p, 0, 10 );
				}
			}
		}
		if ( isset( $in['next'] ) && is_array( $in['next'] ) ) {
			foreach ( self::TYPES as $t ) {
				if ( isset( $in['next'][ $t ] ) ) {
					$cur['next'][ $t ] = max( 1, min( 99999999, (int) $in['next'][ $t ] ) );
				}
			}
		}
		update_option( 'axh_bill_settings', $cur, false );
		self::event( 0, 0, 'settings', 'Billing settings saved' );
		return self::settings();
	}

	/**
	 * Card / online payment gateways. None ship with the Hub. A gateway adds
	 * itself here:
	 *
	 *   add_filter( 'axh_bill_gateways', function ( $g ) {
	 *       $g['stripe'] = array(
	 *           'label'  => 'Pay by card',
	 *           'start'  => function ( $doc, $amount ) { return $checkout_url; }, // or WP_Error
	 *       );
	 *       return $g;
	 *   } );
	 *
	 * and its webhook calls AXH_Bill::gateway_payment( $doc_id, $amount,
	 * 'stripe', $txn_id ) once the money has settled. The invoice page shows
	 * a Pay button only while at least one gateway is registered.
	 */
	public static function gateways() {
		$g   = apply_filters( 'axh_bill_gateways', array() );
		$out = array();
		foreach ( (array) $g as $id => $def ) {
			if ( is_array( $def ) && ! empty( $def['start'] ) && is_callable( $def['start'] ) ) {
				$out[ sanitize_key( $id ) ] = array( 'label' => sanitize_text_field( $def['label'] ?? 'Pay online' ), 'start' => $def['start'] );
				// 1.21.0: a gateway may price itself (card vs bank) and add a short note.
				foreach ( array( 'amount', 'note' ) as $opt ) {
					if ( ! empty( $def[ $opt ] ) && is_callable( $def[ $opt ] ) ) {
						$out[ sanitize_key( $id ) ][ $opt ] = $def[ $opt ];
					}
				}
			}
		}
		return $out;
	}

	/** Record a settled online payment (idempotent on the gateway transaction id). */
	public static function gateway_payment( $doc_id, $amount, $gateway, $txn_id ) {
		global $wpdb;
		$ref = mb_substr( sanitize_key( $gateway ) . ':' . sanitize_text_field( $txn_id ), 0, 120 );
		$t   = self::t( 'bpay' );
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE reference = %s AND voided_at IS NULL", $ref ) ) ) { // phpcs:ignore
			return true;
		}
		return self::record_payment(
			array(
				'doc_id'      => (int) $doc_id,
				'amount'      => $amount,
				'method'      => 'card',
				'reference'   => $ref,
				'received_at' => self::today(),
				'cleared'     => 1,
				'note'        => 'Paid online (' . sanitize_key( $gateway ) . ')',
			),
			true
		);
	}

	/* --------------------------------------------------------------- events */

	/**
	 * The audit trail and timeline for billing: who created, changed, sent,
	 * viewed, accepted, paid, refunded or voided what.
	 */
	public static function event( $doc_id, $customer_id, $type, $detail = '', $who = null ) {
		global $wpdb;
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$wpdb->insert(
			self::t( 'bevents' ),
			array(
				'doc_id'      => (int) $doc_id,
				'customer_id' => (int) $customer_id,
				'type'        => sanitize_key( $type ),
				'actor_id'    => null === $who ? get_current_user_id() : (int) $who,
				'detail'      => mb_substr( is_string( $detail ) ? $detail : wp_json_encode( $detail ), 0, 4000 ),
				'ip'          => filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '',
				'ua'          => mb_substr( $ua, 0, 255 ),
				'created_at'  => self::now(),
			)
		);
	}

	public static function events( $args ) {
		global $wpdb;
		$t     = self::t( 'bevents' );
		$where = array( '1=1' );
		if ( ! empty( $args['doc_id'] ) ) {
			$where[] = $wpdb->prepare( 'e.doc_id = %d', (int) $args['doc_id'] );
		}
		if ( ! empty( $args['customer_id'] ) ) {
			$d       = self::t( 'bdocs' );
			$where[] = $wpdb->prepare( "(e.customer_id = %d OR e.doc_id IN (SELECT id FROM {$d} WHERE {$d}.customer_id = %d))", (int) $args['customer_id'], (int) $args['customer_id'] );
		}
		if ( ! empty( $args['types'] ) ) {
			$where[] = "e.type IN ('" . implode( "','", array_map( 'sanitize_key', (array) $args['types'] ) ) . "')";
		}
		$limit = max( 1, min( 500, (int) ( $args['limit'] ?? 100 ) ) );
		$dt    = self::t( 'bdocs' );
		$rows  = $wpdb->get_results( "SELECT e.*, d.number FROM {$t} e LEFT JOIN {$dt} d ON d.id = e.doc_id WHERE " . implode( ' AND ', $where ) . " ORDER BY e.id DESC LIMIT {$limit}", ARRAY_A ); // phpcs:ignore
		$mgr   = axh_is_manager();
		foreach ( $rows as &$r ) {
			$r['id']     = (int) $r['id'];
			$r['doc_id'] = (int) $r['doc_id'];
			$r['actor']  = $r['actor_id'] ? self::user_name( $r['actor_id'] ) : ( in_array( $r['type'], array( 'viewed', 'accepted', 'declined' ), true ) ? 'Customer' : 'System' );
			if ( ! $mgr ) {
				unset( $r['ip'], $r['ua'] );
			}
		}
		return $rows;
	}

	/* -------------------------------------------------------------- catalog */

	public static function seed_items() {
		global $wpdb;
		if ( get_option( 'axh_bill_seeded' ) ) {
			return;
		}
		$t = self::t( 'items' );
		if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" ) ) { // phpcs:ignore
			update_option( 'axh_bill_seeded', 1, false );
			return;
		}
		$now   = self::now();
		$seeds = array(
			array( 'Starter website', 'Custom-designed, mobile-first website that is easy to edit yourself, with forms that feed a CRM. Starts around $900; scoped after discovery.', 900, 'project', 'Websites' ),
			array( 'Custom CRM', 'A CRM built around how your business already works: pipelines, client records, automations and reporting. No per-user fees. Starts around $1,500.', 1500, 'project', 'CRM & Apps' ),
			array( 'Custom software / web app', 'A custom web app or client portal that is not a CRM. Starts around $2,500; scoped after discovery.', 2500, 'project', 'CRM & Apps' ),
			array( 'Design & development (hourly)', 'Hourly design or development work. Placeholder: set your hourly rate before using.', 0, 'hour', 'Websites' ),
			array( 'Local SEO setup', 'Technical fixes, Google Business Profile, citations and on-page work. Placeholder: set a price per engagement.', 0, 'project', 'SEO' ),
			array( 'Core Values Index™ assessment & debrief', 'Online CVI™ assessment (about 7–10 minutes) and a one-on-one results debrief with a Certified CVI™ Coach. Placeholder: set your price.', 0, 'each', 'CVI Coaching' ),
			array( 'Hosting & maintenance (optional)', 'Optional monthly hosting, updates and backups. Never required to keep what was built. Placeholder: set your monthly price.', 0, 'month', 'Hosting/Maintenance' ),
		);
		foreach ( $seeds as $i => $s ) {
			$wpdb->insert(
				$t,
				array(
					'name'        => $s[0],
					'description' => $s[1],
					'rate'        => $s[2],
					'unit'        => $s[3],
					'category'    => $s[4],
					'taxable'     => 0,
					'active'      => 1,
					'is_default'  => 1,
					'sort'        => $i,
					'created_at'  => $now,
					'updated_at'  => $now,
				)
			);
		}
		update_option( 'axh_bill_seeded', 1, false );
	}

	protected static function shape_item( $r ) {
		return array(
			'id'          => (int) $r['id'],
			'name'        => $r['name'],
			'description' => (string) $r['description'],
			'rate'        => (float) $r['rate'],
			'unit'        => $r['unit'],
			'category'    => $r['category'],
			'taxable'     => (bool) $r['taxable'],
			'active'      => (bool) $r['active'],
			'is_default'  => (bool) $r['is_default'],
			'qbo_item'    => (string) $r['qbo_item'],
			'external_id' => (string) $r['external_id'],
			'synced_at'   => $r['synced_at'],
		);
	}

	/**
	 * 1.7.1: the optional on-site workflow study. Added once, at $0 because
	 * Adam prices it per job; never re-added if he deletes or renames it.
	 */
	public static function seed_onsite_item() {
		global $wpdb;
		if ( get_option( 'axh_bill_onsite_item' ) ) {
			return;
		}
		$t = self::t( 'items' );
		if ( ! (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE name = %s", 'On-site workflow study' ) ) ) { // phpcs:ignore
			$now = self::now();
			$wpdb->insert(
				$t,
				array(
					'name'        => 'On-site workflow study',
					'description' => 'Optional add-on. Adam travels to your business to observe your operation for [X] days, then designs your CRM or project-management system around how the work really flows. Covers travel, lodging and time. Quoted separately for each job.',
					'rate'        => 0,
					'unit'        => 'each',
					'category'    => 'CRM & Apps',
					'taxable'     => 0,
					'active'      => 1,
					'is_default'  => 1,
					'sort'        => 20,
					'created_at'  => $now,
					'updated_at'  => $now,
				)
			);
		}
		update_option( 'axh_bill_onsite_item', 1, false );
	}

	/**
	 * 1.7.5: optional marketing add-ons. Added once (by name, so nothing is
	 * duplicated), at standard starting rates Adam can change per document;
	 * never re-added if he deletes or renames them.
	 */
	public static function seed_marketing_items() {
		global $wpdb;
		if ( get_option( 'axh_bill_marketing_items' ) ) {
			return;
		}
		$t     = self::t( 'items' );
		$now   = self::now();
		$seeds = array(
			array( 'Google Ads management', 'Month-to-month management of your Google Ads: campaign structure and keyword research, ad copy, conversion tracking with GA4 and the Google tag, negative keywords, bid and budget optimization, and a monthly report and check-in. Your ad spend is billed by Google directly to you and is not included. No long-term contract; cancel anytime.', 350, 'month', 'Branding & Marketing', 30 ),
			array( 'Google Ads campaign setup', 'One-time setup of your Google Ads account and first campaigns: goals, keyword research, campaign and ad group structure, ad copy and assets, location and schedule targeting, and conversion tracking with GA4 and the Google tag. Ad spend is billed by Google directly to you.', 400, 'project', 'Branding & Marketing', 31 ),
			array( 'Monthly SEO', 'Month-to-month SEO: technical checks and fixes, on-page optimization, 1–2 SEO articles or page improvements, Google Business Profile posts and updates, local citations, and a plain-English rank and traffic report. No one can guarantee rankings; we commit to the work and report it clearly. Cancel anytime.', 450, 'month', 'SEO', 32 ),
		);
		foreach ( $seeds as $s ) {
			if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE name = %s", $s[0] ) ) ) { // phpcs:ignore
				continue;
			}
			$wpdb->insert(
				$t,
				array(
					'name'        => $s[0],
					'description' => $s[1],
					'rate'        => $s[2],
					'unit'        => $s[3],
					'category'    => $s[4],
					'taxable'     => 0,
					'active'      => 1,
					'is_default'  => 1,
					'sort'        => $s[5],
					'created_at'  => $now,
					'updated_at'  => $now,
				)
			);
		}
		update_option( 'axh_bill_marketing_items', 1, false );
	}

	public static function items( $all = false ) {
		global $wpdb;
		self::seed_items();
		self::seed_onsite_item();
		self::seed_marketing_items();
		$t    = self::t( 'items' );
		$rows = $wpdb->get_results( "SELECT * FROM {$t}" . ( $all ? '' : ' WHERE active = 1' ) . ' ORDER BY active DESC, category ASC, sort ASC, name ASC', ARRAY_A ); // phpcs:ignore
		return array_map( array( __CLASS__, 'shape_item' ), $rows );
	}

	public static function item( $id ) {
		global $wpdb;
		$t = self::t( 'items' );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A );
		return $r ? self::shape_item( $r ) : null;
	}

	public static function save_item( $id, $in ) {
		global $wpdb;
		$in   = (array) $in;
		$t    = self::t( 'items' );
		$cur  = $id ? self::item( $id ) : null;
		if ( $id && ! $cur ) {
			return new WP_Error( 'axh_not_found', 'That item is not in the catalog.', array( 'status' => 404 ) );
		}
		$row = array();
		if ( isset( $in['name'] ) || ! $id ) {
			$row['name'] = self::txt( $in['name'] ?? '', 191 );
			if ( '' === $row['name'] ) {
				return new WP_Error( 'axh_bad_item', 'Give the item a name.', array( 'status' => 400 ) );
			}
		}
		if ( isset( $in['description'] ) ) {
			$row['description'] = self::longtxt( $in['description'], 2000 );
		}
		if ( isset( $in['rate'] ) ) {
			$row['rate'] = max( 0, self::money( $in['rate'] ) );
		}
		if ( isset( $in['unit'] ) ) {
			$row['unit'] = in_array( $in['unit'], self::units(), true ) ? $in['unit'] : 'each';
		}
		if ( isset( $in['category'] ) ) {
			$row['category'] = in_array( $in['category'], self::categories(), true ) ? $in['category'] : 'Other';
		}
		foreach ( array( 'taxable', 'active' ) as $k ) {
			if ( isset( $in[ $k ] ) ) {
				$row[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
			}
		}
		foreach ( array( 'qbo_item' => 191, 'external_id' => 64 ) as $k => $len ) {
			if ( isset( $in[ $k ] ) ) {
				$row[ $k ] = self::txt( $in[ $k ], $len );
			}
		}
		$row['updated_at'] = self::now();
		if ( $id ) {
			$row['is_default'] = 0; // Edited: no longer an untouched starter item.
			$wpdb->update( $t, $row, array( 'id' => (int) $id ) );
		} else {
			$row += array( 'unit' => 'each', 'category' => 'Other', 'active' => 1, 'created_at' => self::now() );
			$wpdb->insert( $t, $row );
			$id = (int) $wpdb->insert_id;
		}
		self::event( 0, 0, 'item', ( $cur ? 'Catalog item updated: ' : 'Catalog item added: ' ) . ( $row['name'] ?? $cur['name'] ) );
		return self::item( $id );
	}

	/* ------------------------------------------------------------ customers */

	protected static function rep_scope( $col = 'rep_id' ) {
		global $wpdb;
		return axh_is_manager() ? '1=1' : $wpdb->prepare( "{$col} = %d", get_current_user_id() );
	}

	protected static function shape_customer( $r, $full = false ) {
		$mgr = axh_is_manager();
		$c   = array(
			'id'           => (int) $r['id'],
			'lead_id'      => $r['lead_id'] ? (int) $r['lead_id'] : 0,
			'rep_id'       => (int) $r['rep_id'],
			'rep_name'     => self::user_name( $r['rep_id'] ),
			'company'      => $r['company'],
			'contact_name' => $r['contact_name'],
			'email'        => $r['email'],
			'cc_emails'    => (string) $r['cc_emails'],
			'phone'        => $r['phone'],
			'addr1'        => $r['addr1'],
			'addr2'        => $r['addr2'],
			'city'         => $r['city'],
			'state'        => $r['state'],
			'zip'          => $r['zip'],
			'country'      => $r['country'],
			'archived_at'  => $r['archived_at'],
			'created_at'   => $r['created_at'],
			'updated_at'   => $r['updated_at'],
			'name'         => $r['company'] ? $r['company'] : $r['contact_name'],
		);
		if ( $mgr ) {
			$c['notes']       = (string) $r['notes'];
			$c['external_id'] = (string) $r['external_id'];
			$c['sync_token']  = (string) $r['sync_token'];
			$c['synced_at']   = $r['synced_at'];
		}
		return $c;
	}

	public static function customer_row( $id, $scoped = true ) {
		global $wpdb;
		$t = self::t( 'customers' );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A );
		if ( ! $r ) {
			return null;
		}
		if ( $scoped && ! axh_is_manager() && (int) $r['rep_id'] !== get_current_user_id() ) {
			return null;
		}
		return $r;
	}

	/** Per-customer money: billed, paid, open balance, credit. */
	protected static function customer_money( $ids = null ) {
		global $wpdb;
		$d     = self::t( 'bdocs' );
		$p     = self::t( 'bpay' );
		$in    = null === $ids ? '' : ' AND customer_id IN (' . ( $ids ? implode( ',', array_map( 'intval', $ids ) ) : '0' ) . ')';
		$out   = array();
		$today = self::today();
		foreach ( (array) $wpdb->get_results( "SELECT customer_id, total, amount_paid, balance, due_date, status FROM {$d} WHERE type = 'invoice' AND status NOT IN ('draft','void'){$in}", ARRAY_A ) as $r ) { // phpcs:ignore
			$c = (int) $r['customer_id'];
			if ( ! isset( $out[ $c ] ) ) {
				$out[ $c ] = array( 'billed' => 0.0, 'paid' => 0.0, 'balance' => 0.0, 'overdue' => 0.0, 'open' => 0, 'credit' => 0.0 );
			}
			$out[ $c ]['billed']  += (float) $r['total'];
			$out[ $c ]['paid']    += (float) $r['amount_paid'];
			$out[ $c ]['balance'] += max( 0, (float) $r['balance'] );
			if ( (float) $r['balance'] > 0.004 ) {
				$out[ $c ]['open']++;
				if ( $r['due_date'] && $r['due_date'] < $today ) {
					$out[ $c ]['overdue'] += (float) $r['balance'];
				}
			}
		}
		foreach ( (array) $wpdb->get_results( "SELECT customer_id, kind, credit, amount FROM {$p} WHERE voided_at IS NULL{$in}", ARRAY_A ) as $r ) { // phpcs:ignore
			$c = (int) $r['customer_id'];
			if ( ! isset( $out[ $c ] ) ) {
				$out[ $c ] = array( 'billed' => 0.0, 'paid' => 0.0, 'balance' => 0.0, 'overdue' => 0.0, 'open' => 0, 'credit' => 0.0 );
			}
			if ( 'payment' === $r['kind'] ) {
				$out[ $c ]['credit'] += (float) $r['credit'];
			} elseif ( 'credit' === $r['kind'] ) {
				$out[ $c ]['credit'] -= (float) $r['amount'];
			} elseif ( 'refund' === $r['kind'] ) {
				$out[ $c ]['credit'] -= (float) $r['credit'];
			}
		}
		foreach ( $out as &$o ) {
			foreach ( $o as $k => $v ) {
				$o[ $k ] = is_float( $v ) ? round( $v, 2 ) : $v;
			}
		}
		return $out;
	}

	public static function customer_credit( $customer_id ) {
		$m = self::customer_money( array( (int) $customer_id ) );
		return isset( $m[ (int) $customer_id ] ) ? max( 0, $m[ (int) $customer_id ]['credit'] ) : 0.0;
	}

	public static function customers( $args = array() ) {
		global $wpdb;
		$t     = self::t( 'customers' );
		$where = array( self::rep_scope() );
		if ( empty( $args['archived'] ) ) {
			$where[] = 'archived_at IS NULL';
		}
		if ( ! empty( $args['q'] ) ) {
			$like    = '%' . $wpdb->esc_like( (string) $args['q'] ) . '%';
			$where[] = $wpdb->prepare( '(company LIKE %s OR contact_name LIKE %s OR email LIKE %s OR phone LIKE %s)', $like, $like, $like, $like );
		}
		if ( ! empty( $args['rep_id'] ) && axh_is_manager() ) {
			$where[] = $wpdb->prepare( 'rep_id = %d', (int) $args['rep_id'] );
		}
		$rows  = $wpdb->get_results( "SELECT * FROM {$t} WHERE " . implode( ' AND ', $where ) . ' ORDER BY company ASC, contact_name ASC LIMIT 2000', ARRAY_A ); // phpcs:ignore
		$money = self::customer_money( wp_list_pluck( $rows, 'id' ) );
		$d     = self::t( 'bdocs' );
		$last  = array();
		if ( $rows ) {
			$ids = implode( ',', array_map( 'intval', wp_list_pluck( $rows, 'id' ) ) );
			foreach ( (array) $wpdb->get_results( "SELECT customer_id, MAX(updated_at) AS u, COUNT(*) AS n FROM {$d} WHERE customer_id IN ({$ids}) GROUP BY customer_id", ARRAY_A ) as $r ) { // phpcs:ignore
				$last[ (int) $r['customer_id'] ] = array( $r['u'], (int) $r['n'] );
			}
		}
		$out = array();
		foreach ( $rows as $r ) {
			$c          = self::shape_customer( $r );
			$m          = $money[ $c['id'] ] ?? array( 'billed' => 0, 'paid' => 0, 'balance' => 0, 'overdue' => 0, 'open' => 0, 'credit' => 0 );
			$c['money'] = $m;
			$c['docs']  = $last[ $c['id'] ][1] ?? 0;
			$c['last']  = $last[ $c['id'] ][0] ?? $c['updated_at'];
			$out[]      = $c;
		}
		return $out;
	}

	public static function customer( $id ) {
		$r = self::customer_row( $id );
		if ( ! $r ) {
			return null;
		}
		$c          = self::shape_customer( $r, true );
		$m          = self::customer_money( array( $c['id'] ) );
		$c['money'] = $m[ $c['id'] ] ?? array( 'billed' => 0, 'paid' => 0, 'balance' => 0, 'overdue' => 0, 'open' => 0, 'credit' => 0 );
		$c['money']['credit'] = max( 0, $c['money']['credit'] );
		return $c;
	}

	public static function customer_detail( $id ) {
		$c = self::customer( $id );
		if ( ! $c ) {
			return null;
		}
		$docs     = self::docs( array( 'customer_id' => $c['id'], 'limit' => 500 ) );
		$payments = self::payments( array( 'customer_id' => $c['id'] ) );
		$timeline = self::events( array( 'customer_id' => $c['id'], 'limit' => 80 ) );
		$lead     = null;
		$legacy   = array();
		if ( $c['lead_id'] ) {
			$l = AXH_DB::get_lead( $c['lead_id'] );
			if ( $l ) {
				$lead = array( 'id' => (int) $l['id'], 'company' => $l['company'], 'stage' => $l['stage'], 'contract_value' => (float) $l['contract_value'] );
				// Client payments recorded on the lead before Billing existed.
				foreach ( array( 'deposit_at' => 'deposit', 'final_at' => 'final' ) as $f => $leg ) {
					$inv = self::leg_invoice( $c['lead_id'], $leg );
					if ( ! empty( $l[ $f ] ) && ( ! $inv || 1 !== (int) $inv['leg_applied'] ) ) {
						$legacy[] = array( 'leg' => $leg, 'at' => $l[ $f ], 'label' => 'Customer ' . ( 'deposit' === $leg ? 'deposit' : 'final payment' ) . ' cleared (recorded on the deal before Billing)' );
					}
				}
			}
		}
		$avg = null;
		$n   = 0;
		$sum = 0;
		foreach ( $docs as $dd ) {
			if ( 'invoice' === $dd['type'] && $dd['paid_at'] && $dd['issue_date'] ) {
				$sum += max( 0, ( strtotime( $dd['paid_at'] ) - strtotime( $dd['issue_date'] ) ) / DAY_IN_SECONDS );
				$n++;
			}
		}
		if ( $n ) {
			$avg = round( $sum / $n, 1 );
		}
		return array( 'customer' => $c, 'docs' => $docs, 'payments' => $payments, 'timeline' => $timeline, 'lead' => $lead, 'legacy' => $legacy, 'avg_days' => $avg );
	}

	public static function save_customer( $id, $in ) {
		global $wpdb;
		$in  = (array) $in;
		$t   = self::t( 'customers' );
		$cur = $id ? self::customer_row( $id ) : null;
		if ( $id && ! $cur ) {
			return new WP_Error( 'axh_not_found', 'Customer not found.', array( 'status' => 404 ) );
		}
		$row = array();
		foreach ( array( 'company' => 191, 'contact_name' => 191, 'phone' => 64, 'addr1' => 191, 'addr2' => 191, 'city' => 96, 'state' => 64, 'zip' => 20, 'country' => 64, 'external_id' => 64 ) as $k => $len ) {
			if ( isset( $in[ $k ] ) ) {
				$row[ $k ] = self::txt( $in[ $k ], $len );
			}
		}
		if ( isset( $in['email'] ) ) {
			$e = trim( (string) $in['email'] );
			if ( '' !== $e && ! is_email( $e ) ) {
				return new WP_Error( 'axh_bad_email', 'That email address does not look right.', array( 'status' => 400 ) );
			}
			$row['email'] = sanitize_email( $e );
		}
		if ( isset( $in['cc_emails'] ) ) {
			$list = array_filter( array_map( 'trim', preg_split( '/[,;\s]+/', (string) $in['cc_emails'] ) ) );
			$ok   = array();
			foreach ( $list as $e ) {
				if ( is_email( $e ) ) {
					$ok[] = sanitize_email( $e );
				}
			}
			$row['cc_emails'] = mb_substr( implode( ', ', array_slice( array_unique( $ok ), 0, 5 ) ), 0, 400 );
		}
		if ( isset( $in['notes'] ) ) {
			$row['notes'] = self::longtxt( $in['notes'], 8000 );
		}
		if ( isset( $in['rep_id'] ) ) {
			$rid           = (int) $in['rep_id'];
			$row['rep_id'] = ( $rid && ( $u = get_userdata( $rid ) ) && user_can( $u, 'axh_use_hub' ) ) ? $rid : 0;
		}
		if ( isset( $in['lead_id'] ) ) {
			$lid = (int) $in['lead_id'];
			$l   = $lid ? AXH_DB::get_lead( $lid ) : null;
			$row['lead_id'] = $l ? $lid : null;
			if ( $l ) {
				$row['rep_id'] = (int) $l['rep_id'];
			}
		}
		if ( isset( $in['archived'] ) ) {
			$row['archived_at'] = empty( $in['archived'] ) ? null : self::now();
		}
		$name = $row['company'] ?? ( $cur['company'] ?? '' );
		$cn   = $row['contact_name'] ?? ( $cur['contact_name'] ?? '' );
		if ( '' === trim( $name ) && '' === trim( $cn ) ) {
			return new WP_Error( 'axh_bad_customer', 'Enter a company or a contact name.', array( 'status' => 400 ) );
		}
		$row['updated_at'] = self::now();
		if ( $id ) {
			$wpdb->update( $t, $row, array( 'id' => (int) $id ) );
			if ( isset( $row['rep_id'] ) ) {
				$wpdb->update( self::t( 'bdocs' ), array( 'rep_id' => $row['rep_id'] ), array( 'customer_id' => (int) $id ) );
			}
			self::event( 0, $id, 'customer', 'Customer updated' );
		} else {
			$row += array( 'created_at' => self::now(), 'country' => 'US' );
			$wpdb->insert( $t, $row );
			$id = (int) $wpdb->insert_id;
			if ( ! $id ) {
				return new WP_Error( 'axh_failed', 'Could not save that customer.', array( 'status' => 500 ) );
			}
			self::event( 0, $id, 'customer', 'Customer created' );
		}
		return self::customer( $id );
	}

	/** One click from a lead: the customer it already has, or a new one filled in from it. */
	public static function customer_from_lead( $lead_id ) {
		global $wpdb;
		$lead = AXH_DB::get_lead( (int) $lead_id );
		if ( ! $lead ) {
			return new WP_Error( 'axh_not_found', 'Lead not found.', array( 'status' => 404 ) );
		}
		$t  = self::t( 'customers' );
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE lead_id = %d ORDER BY id ASC LIMIT 1", (int) $lead_id ) );
		if ( $id ) {
			return self::customer( $id );
		}
		$c = self::save_customer(
			0,
			array(
				'company'      => $lead['company'],
				'contact_name' => $lead['contact_name'],
				'email'        => is_email( $lead['email'] ) ? $lead['email'] : '',
				'phone'        => $lead['phone'],
				'city'         => $lead['city'],
				'state'        => $lead['state'],
				'lead_id'      => (int) $lead_id,
			)
		);
		if ( ! is_wp_error( $c ) ) {
			AXH_DB::log( $lead_id, 'billing', 'Billing customer created', 'By ' . self::user_name( get_current_user_id() ) );
		}
		return $c;
	}

	/** A lead changed hands: its customers and documents follow it. */
	public static function lead_assigned( $lead_id, $rep_id ) {
		global $wpdb;
		$wpdb->update( self::t( 'customers' ), array( 'rep_id' => (int) $rep_id ), array( 'lead_id' => (int) $lead_id ) );
		$wpdb->update( self::t( 'bdocs' ), array( 'rep_id' => (int) $rep_id ), array( 'lead_id' => (int) $lead_id ) );
		$c = self::t( 'customers' );
		$wpdb->query( $wpdb->prepare( "UPDATE " . self::t( 'bdocs' ) . " SET rep_id = %d WHERE customer_id IN (SELECT id FROM {$c} WHERE lead_id = %d)", (int) $rep_id, (int) $lead_id ) ); // phpcs:ignore
	}

	/* ------------------------------------------------------------ documents */

	public static function type_label( $type ) {
		return array( 'proposal' => 'Proposal', 'estimate' => 'Estimate', 'invoice' => 'Invoice' )[ $type ] ?? 'Document';
	}

	public static function path_letter( $type ) {
		return array( 'proposal' => 'p', 'estimate' => 'e', 'invoice' => 'i' )[ $type ] ?? 'i';
	}

	public static function public_url( $doc ) {
		return home_url( '/' . self::path_letter( $doc['type'] ) . '/' . $doc['token'] . '/' );
	}

	public static function new_token() {
		return bin2hex( random_bytes( 20 ) ); // 160 bits.
	}

	protected static function next_number( $type ) {
		global $wpdb;
		$s = self::settings();
		$n = max( 1, (int) $s['next'][ $type ] );
		$t = self::t( 'bdocs' );
		for ( $i = 0; $i < 1000; $i++, $n++ ) {
			$num = $s['prefix'][ $type ] . $n;
			if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE number = %s", $num ) ) ) { // phpcs:ignore
				break;
			}
		}
		$all                 = get_option( 'axh_bill_settings', array() );
		$all                 = is_array( $all ) ? $all : array();
		$all['next']         = isset( $all['next'] ) && is_array( $all['next'] ) ? $all['next'] : $s['next'];
		$all['next'][ $type ] = $n + 1;
		update_option( 'axh_bill_settings', $all, false );
		return array( $num, $n );
	}

	/** Clean one line item. */
	protected static function clean_line( $l ) {
		$l    = (array) $l;
		$qty  = round( (float) ( $l['qty'] ?? 1 ), 3 );
		$rate = self::money( $l['rate'] ?? 0 );
		$out  = array(
			'item_id'   => (int) ( $l['item_id'] ?? 0 ),
			'name'      => self::txt( $l['name'] ?? '', 191 ),
			'desc'      => self::longtxt( $l['desc'] ?? '', 2000 ),
			'qty'       => $qty,
			'rate'      => $rate,
			'unit'      => in_array( $l['unit'] ?? 'each', self::units(), true ) ? ( $l['unit'] ?? 'each' ) : 'each',
			'disc_type' => 'amt' === ( $l['disc_type'] ?? 'pct' ) ? 'amt' : 'pct',
			'disc'      => max( 0, round( (float) ( $l['disc'] ?? 0 ), 2 ) ),
			'taxable'   => ! empty( $l['taxable'] ),
			'category'  => in_array( $l['category'] ?? '', self::categories(), true ) ? $l['category'] : 'Other',
		);
		if ( 'pct' === $out['disc_type'] ) {
			$out['disc'] = min( 100, $out['disc'] );
		}
		if ( ! empty( $l['split'] ) && is_array( $l['split'] ) ) {
			$split = array();
			foreach ( $l['split'] as $cat => $amt ) {
				if ( in_array( $cat, self::categories(), true ) ) {
					$split[ $cat ] = round( (float) $amt, 2 );
				}
			}
			$out['split'] = $split;
		}
		return $out;
	}

	protected static function clean_lines( $lines, $max = 80 ) {
		$out = array();
		foreach ( array_slice( (array) $lines, 0, $max ) as $l ) {
			$c = self::clean_line( $l );
			if ( '' !== $c['name'] || '' !== $c['desc'] || $c['rate'] ) {
				$out[] = $c;
			}
		}
		return $out;
	}

	protected static function sid( $v ) {
		$v = preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $v ) );
		return $v ? mb_substr( $v, 0, 24 ) : substr( md5( uniqid( '', true ) ), 0, 8 );
	}

	public static function section_kinds() {
		return array( 'text', 'scope', 'timeline', 'investment', 'included', 'why', 'next', 'terms' );
	}

	/** Clean a document's content (cover, sections, options, add-ons, schedule). */
	protected static function clean_content( $c, $type ) {
		$c   = (array) $c;
		$out = array();
		$cv  = (array) ( $c['cover'] ?? array() );
		$out['cover'] = array(
			'title'    => self::txt( $cv['title'] ?? '', 191 ),
			'subtitle' => self::txt( $cv['subtitle'] ?? '', 300 ),
			'image'    => esc_url_raw( (string) ( $cv['image'] ?? '' ) ),
			'intro'    => self::longtxt( $cv['intro'] ?? '', 4000 ),
		);
		$out['sections'] = array();
		foreach ( array_slice( (array) ( $c['sections'] ?? array() ), 0, 30 ) as $s ) {
			$s    = (array) $s;
			$kind = in_array( $s['kind'] ?? 'text', self::section_kinds(), true ) ? $s['kind'] : 'text';
			$sec  = array(
				'id'    => self::sid( $s['id'] ?? '' ),
				'kind'  => $kind,
				'title' => self::txt( $s['title'] ?? '', 191 ),
				'body'  => self::longtxt( $s['body'] ?? '', 12000 ),
				'on'    => ! isset( $s['on'] ) || ! empty( $s['on'] ),
			);
			if ( 'timeline' === $kind ) {
				$sec['items'] = array();
				foreach ( array_slice( (array) ( $s['items'] ?? array() ), 0, 20 ) as $it ) {
					$it = (array) $it;
					$sec['items'][] = array( 'title' => self::txt( $it['title'] ?? '', 191 ), 'when' => self::txt( $it['when'] ?? '', 80 ), 'detail' => self::longtxt( $it['detail'] ?? '', 1000 ) );
				}
			}
			$out['sections'][] = $sec;
		}
		$out['options'] = array();
		$out['addons']  = array();
		if ( 'proposal' === $type ) {
			foreach ( array_slice( (array) ( $c['options'] ?? array() ), 0, 4 ) as $o ) {
				$o = (array) $o;
				$out['options'][] = array(
					'id'          => self::sid( $o['id'] ?? '' ),
					'name'        => self::txt( $o['name'] ?? 'Option', 120 ),
					'tagline'     => self::txt( $o['tagline'] ?? '', 191 ),
					'desc'        => self::longtxt( $o['desc'] ?? '', 3000 ),
					'recommended' => ! empty( $o['recommended'] ),
					'lines'       => self::clean_lines( $o['lines'] ?? array(), 30 ),
				);
			}
			foreach ( array_slice( (array) ( $c['addons'] ?? array() ), 0, 12 ) as $a ) {
				$a    = (array) $a;
				$line = self::clean_line( $a );
				$line['id'] = self::sid( $a['id'] ?? '' );
				$line['on'] = ! empty( $a['on'] );
				if ( '' !== $line['name'] ) {
					$out['addons'][] = $line;
				}
			}
			$ids  = wp_list_pluck( $out['options'], 'id' );
			$sel  = (string) ( $c['selected'] ?? '' );
			if ( ! in_array( $sel, $ids, true ) ) {
				$sel = '';
				foreach ( $out['options'] as $o ) {
					if ( $o['recommended'] ) {
						$sel = $o['id'];
						break;
					}
				}
				if ( ! $sel && $ids ) {
					$sel = $ids[0];
				}
			}
			$out['selected'] = $sel;
		}
		$out['schedule'] = array();
		if ( 'invoice' !== $type ) {
			$total = 0;
			foreach ( array_slice( (array) ( $c['schedule'] ?? array() ), 0, 6 ) as $s ) {
				$s   = (array) $s;
				$pct = max( 0, min( 100, round( (float) ( $s['pct'] ?? 0 ), 2 ) ) );
				if ( ! $pct ) {
					continue;
				}
				$total += $pct;
				$out['schedule'][] = array(
					'label' => self::txt( $s['label'] ?? 'Payment', 80 ),
					'pct'   => $pct,
					'leg'   => in_array( $s['leg'] ?? '', array( 'deposit', 'final', 'full' ), true ) ? $s['leg'] : '',
					'due'   => self::txt( $s['due'] ?? '', 120 ),
				);
			}
			if ( $out['schedule'] && abs( $total - 100 ) > 0.01 ) {
				return new WP_Error( 'axh_bad_schedule', 'The payment schedule has to add up to 100% (it adds up to ' . rtrim( rtrim( number_format( $total, 2 ), '0' ), '.' ) . '%).', array( 'status' => 400 ) );
			}
		}
		$out['converted'] = array_values( array_map( 'intval', (array) ( $c['converted'] ?? array() ) ) );
		if ( ! empty( $c['accepted'] ) && is_array( $c['accepted'] ) ) {
			$out['accepted'] = $c['accepted']; // Written by accept() only; preserved.
		}
		return $out;
	}

	public static function default_schedule() {
		return array(
			array( 'label' => 'Deposit', 'pct' => 50, 'leg' => 'deposit', 'due' => 'Due when you accept' ),
			array( 'label' => 'Final payment', 'pct' => 50, 'leg' => 'final', 'due' => 'Due at launch' ),
		);
	}

	/* ---- totals (the JS in hub.js and on the public page mirror this) ---- */

	/** 1.7.4: display name for an add-on. The Optional/Added chip already says it, so a trailing
	 * "(optional)" or "[optional]" anywhere in the name (any case), or a trailing "- optional" / "– optional",
	 * is dropped. Stored data is unchanged. */
	public static function addon_label( $name ) {
		$name  = (string) $name;
		$clean = (string) preg_replace( '/\s*[(\[]\s*optional\s*[)\]]/iu', '', $name );
		$clean = trim( (string) preg_replace( '/\s*[-\x{2013}\x{2014}:]\s*optional\s*$/iu', '', $clean ) );
		$clean = trim( (string) preg_replace( '/\s{2,}/u', ' ', $clean ) );
		return '' === $clean ? $name : $clean;
	}

	public static function line_net( $l ) {
		$amount = round( (float) $l['qty'] * (float) $l['rate'], 2 );
		$disc   = 'amt' === ( $l['disc_type'] ?? 'pct' ) ? min( $amount, (float) $l['disc'] ) : round( $amount * (float) $l['disc'] / 100, 2 );
		return array( round( $amount, 2 ), round( $disc, 2 ), round( $amount - $disc, 2 ) );
	}

	/**
	 * The lines that count for a selection: the document's own lines, plus
	 * (proposals) the chosen option's lines and the add-ons switched on.
	 */
	public static function lines_for( $doc, $option = null, $addons = null ) {
		$c     = $doc['content'];
		$lines = (array) $doc['lines'];
		if ( 'proposal' === $doc['type'] ) {
			$option = null === $option ? ( $c['selected'] ?? '' ) : $option;
			foreach ( (array) ( $c['options'] ?? array() ) as $o ) {
				if ( $o['id'] === $option ) {
					foreach ( $o['lines'] as $l ) {
						$lines[] = $l;
					}
				}
			}
			foreach ( (array) ( $c['addons'] ?? array() ) as $a ) {
				$on = null === $addons ? ! empty( $a['on'] ) : in_array( $a['id'], (array) $addons, true );
				if ( $on ) {
					$lines[] = $a;
				}
			}
		}
		return $lines;
	}

	public static function calc( $lines, $doc ) {
		$sub      = 0.0;
		$line_d   = 0.0;
		$taxbase  = 0.0;
		$cats     = array();
		foreach ( $lines as $l ) {
			list( $amt, $d, $net ) = self::line_net( $l );
			$sub    += $net;
			$line_d += $d;
			if ( ! empty( $l['taxable'] ) ) {
				$taxbase += $net;
			}
			if ( ! empty( $l['split'] ) ) {
				$sp = array_sum( $l['split'] );
				foreach ( $l['split'] as $cat => $v ) {
					$cats[ $cat ] = ( $cats[ $cat ] ?? 0 ) + ( $sp ? $net * $v / $sp : 0 );
				}
			} else {
				$cat          = $l['category'] ?? 'Other';
				$cats[ $cat ] = ( $cats[ $cat ] ?? 0 ) + $net;
			}
		}
		$sub  = round( $sub, 2 );
		$dv   = (float) ( $doc['discount_value'] ?? 0 );
		$disc = 'amt' === ( $doc['discount_type'] ?? 'pct' ) ? min( $sub, $dv ) : round( $sub * min( 100, $dv ) / 100, 2 );
		$disc = round( max( 0, $disc ), 2 );
		$f    = $sub > 0 ? ( $sub - $disc ) / $sub : 0;
		$tax  = ! empty( $doc['tax_on'] ) ? round( $taxbase * $f * (float) $doc['tax_rate'] / 100, 2 ) : 0.0;
		foreach ( $cats as $k => $v ) {
			$cats[ $k ] = round( $v * $f, 2 );
		}
		return array(
			'subtotal'      => $sub,
			'line_discount' => round( $line_d, 2 ),
			'discount'      => $disc,
			'tax'           => $tax,
			'total'         => round( $sub - $disc + $tax, 2 ),
			'categories'    => $cats,
		);
	}

	/* ---- reading ---- */

	protected static function doc_scope( $alias = 'd' ) {
		global $wpdb;
		// Reps see the documents for their own clients once AdaptExec has sent
		// them; drafts are work in progress.
		return axh_is_manager() ? '1=1' : $wpdb->prepare( "{$alias}.rep_id = %d AND {$alias}.status <> 'draft'", get_current_user_id() );
	}

	public static function doc_row( $id, $scoped = true ) {
		global $wpdb;
		$t = self::t( 'bdocs' );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A );
		if ( ! $r ) {
			return null;
		}
		if ( $scoped && ! axh_is_manager() && ( (int) $r['rep_id'] !== get_current_user_id() || 'draft' === $r['status'] ) ) {
			return null;
		}
		return $r;
	}

	public static function doc_by_token( $token ) {
		global $wpdb;
		$token = preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $token ) );
		if ( strlen( $token ) < 32 ) {
			return null;
		}
		$t = self::t( 'bdocs' );
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE token = %s", $token ), ARRAY_A );
		return $r ? self::shape_doc( $r, 'public' ) : null;
	}

	/** The status a person should see: overdue and expired are worked out, not stored. */
	public static function display_status( $r ) {
		$s     = $r['status'];
		$today = self::today();
		if ( 'invoice' === $r['type'] ) {
			if ( in_array( $s, array( 'sent', 'viewed', 'partial' ), true ) && $r['due_date'] && $r['due_date'] < $today && (float) $r['balance'] > 0.004 ) {
				return 'overdue';
			}
			return $s;
		}
		if ( in_array( $s, array( 'sent', 'viewed' ), true ) && $r['expiry_date'] && $r['expiry_date'] < $today ) {
			return 'expired';
		}
		return $s;
	}

	public static function status_label( $s ) {
		return array(
			'draft' => 'Draft', 'sent' => 'Sent', 'viewed' => 'Viewed', 'accepted' => 'Accepted', 'declined' => 'Declined',
			'expired' => 'Expired', 'partial' => 'Partially paid', 'paid' => 'Paid', 'overdue' => 'Overdue', 'void' => 'Void',
		)[ $s ] ?? ucfirst( $s );
	}

	/**
	 * A document as the API returns it. $mode: 'admin' (everything), 'rep'
	 * (no internal notes, no audit detail), 'public' (what the customer sees).
	 */
	public static function shape_doc( $r, $mode = null ) {
		$mode    = $mode ? $mode : ( axh_is_manager() ? 'admin' : 'rep' );
		$content = json_decode( (string) $r['content'], true );
		$content = is_array( $content ) ? $content : array();
		$content += array( 'cover' => array( 'title' => '', 'subtitle' => '', 'image' => '', 'intro' => '' ), 'sections' => array(), 'options' => array(), 'addons' => array(), 'schedule' => array(), 'selected' => '', 'converted' => array() );
		$lines   = json_decode( (string) $r['line_items'], true );
		$d       = array(
			'id'             => (int) $r['id'],
			'type'           => $r['type'],
			'type_label'     => self::type_label( $r['type'] ),
			'number'         => $r['number'],
			'title'          => $r['title'],
			'status'         => $r['status'],
			'display_status' => self::display_status( $r ),
			'customer_id'    => (int) $r['customer_id'],
			'lead_id'        => $r['lead_id'] ? (int) $r['lead_id'] : 0,
			'rep_id'         => (int) $r['rep_id'],
			'parent_id'      => $r['parent_id'] ? (int) $r['parent_id'] : 0,
			'milestone'      => (string) $r['milestone'],
			'issue_date'     => $r['issue_date'],
			'expiry_date'    => $r['expiry_date'],
			'due_date'       => $r['due_date'],
			'terms'          => $r['terms'],
			'terms_days'     => null === $r['terms_days'] ? null : (int) $r['terms_days'],
			'content'        => $content,
			'lines'          => is_array( $lines ) ? $lines : array(),
			'discount_type'  => $r['discount_type'],
			'discount_value' => (float) $r['discount_value'],
			'tax_on'         => (bool) $r['tax_on'],
			'tax_rate'       => (float) $r['tax_rate'],
			'tax_label'      => $r['tax_label'],
			'subtotal'       => (float) $r['subtotal'],
			'discount_total' => (float) $r['discount_total'],
			'tax_total'      => (float) $r['tax_total'],
			'total'          => (float) $r['total'],
			'amount_paid'    => (float) $r['amount_paid'],
			'balance'        => (float) $r['balance'],
			'notes'          => (string) $r['notes'],
			'terms_text'     => (string) $r['terms_text'],
			'sent_at'        => $r['sent_at'],
			'viewed_at'      => $r['viewed_at'],
			'view_count'     => (int) $r['view_count'],
			'accepted_at'    => $r['accepted_at'],
			'accepted_name'  => $r['accepted_name'],
			'declined_at'    => $r['declined_at'],
			'decline_reason' => (string) $r['decline_reason'],
			'voided_at'      => $r['voided_at'],
			'paid_at'        => $r['paid_at'],
			'token'          => $r['token'],
			'created_at'     => $r['created_at'],
			'updated_at'     => $r['updated_at'],
		);
		$d['url'] = self::public_url( $d );
		if ( 'public' !== $mode ) {
			$d['rep_name']        = self::user_name( $r['rep_id'] );
			$d['last_viewed_at']  = $r['last_viewed_at'];
			$d['reminders_sent']  = (int) $r['reminders_sent'];
			$d['leg_applied']     = (int) $r['leg_applied'];
			$d['accepted_ip']     = 'admin' === $mode ? $r['accepted_ip'] : '';
			$d['accepted_hash']   = $r['accepted_hash'];
			$d['accepted_method'] = $r['accepted_method'];
		}
		if ( 'admin' === $mode ) {
			$d['internal_notes'] = (string) $r['internal_notes'];
			$d['external_id']    = (string) $r['external_id'];
			$d['sync_token']     = (string) $r['sync_token'];
			$d['synced_at']      = $r['synced_at'];
			$d['accepted_ua']    = $r['accepted_ua'];
		}
		if ( 'public' === $mode ) {
			$d['accepted_sig']    = (string) $r['accepted_sig'];
			$d['accepted_method'] = $r['accepted_method'];
			$d['accepted_hash']   = $r['accepted_hash'];
		}
		return $d;
	}

	public static function docs( $args = array() ) {
		global $wpdb;
		$t     = self::t( 'bdocs' );
		$c     = self::t( 'customers' );
		$where = array( self::doc_scope( 'd' ) );
		if ( ! empty( $args['type'] ) && in_array( $args['type'], self::TYPES, true ) ) {
			$where[] = $wpdb->prepare( 'd.type = %s', $args['type'] );
		}
		if ( ! empty( $args['customer_id'] ) ) {
			$where[] = $wpdb->prepare( 'd.customer_id = %d', (int) $args['customer_id'] );
		}
		if ( ! empty( $args['lead_id'] ) ) {
			$where[] = $wpdb->prepare( '(d.lead_id = %d OR d.customer_id IN (SELECT id FROM ' . $c . ' WHERE lead_id = %d))', (int) $args['lead_id'], (int) $args['lead_id'] ); // phpcs:ignore
		}
		if ( ! empty( $args['rep_id'] ) && axh_is_manager() ) {
			$where[] = $wpdb->prepare( 'd.rep_id = %d', (int) $args['rep_id'] );
		}
		if ( ! empty( $args['q'] ) ) {
			$like    = '%' . $wpdb->esc_like( (string) $args['q'] ) . '%';
			$where[] = $wpdb->prepare( '(d.number LIKE %s OR d.title LIKE %s OR c.company LIKE %s OR c.contact_name LIKE %s)', $like, $like, $like, $like );
		}
		$limit = max( 1, min( 2000, (int) ( $args['limit'] ?? 1000 ) ) );
		$rows  = $wpdb->get_results(
			"SELECT d.*, c.company AS c_company, c.contact_name AS c_contact, c.email AS c_email
			 FROM {$t} d LEFT JOIN {$c} c ON c.id = d.customer_id
			 WHERE " . implode( ' AND ', $where ) . " ORDER BY d.id DESC LIMIT {$limit}",
			ARRAY_A
		); // phpcs:ignore
		$out   = array();
		$want  = ! empty( $args['status'] ) ? sanitize_key( $args['status'] ) : '';
		foreach ( $rows as $r ) {
			$ds = self::display_status( $r );
			if ( $want ) {
				if ( 'open' === $want ) {
					if ( ! in_array( $ds, array( 'sent', 'viewed', 'partial', 'overdue' ), true ) ) {
						continue;
					}
				} elseif ( $ds !== $want ) {
					continue;
				}
			}
			$out[] = array(
				'id'             => (int) $r['id'],
				'type'           => $r['type'],
				'number'         => $r['number'],
				'title'          => $r['title'],
				'status'         => $r['status'],
				'display_status' => $ds,
				'customer_id'    => (int) $r['customer_id'],
				'customer'       => $r['c_company'] ? $r['c_company'] : (string) $r['c_contact'],
				'contact'        => (string) $r['c_contact'],
				'email'          => (string) $r['c_email'],
				'lead_id'        => $r['lead_id'] ? (int) $r['lead_id'] : 0,
				'rep_id'         => (int) $r['rep_id'],
				'rep_name'       => self::user_name( $r['rep_id'] ),
				'milestone'      => (string) $r['milestone'],
				'issue_date'     => $r['issue_date'],
				'due_date'       => $r['due_date'],
				'expiry_date'    => $r['expiry_date'],
				'total'          => (float) $r['total'],
				'amount_paid'    => (float) $r['amount_paid'],
				'balance'        => (float) $r['balance'],
				'view_count'     => (int) $r['view_count'],
				'viewed_at'      => $r['viewed_at'],
				'sent_at'        => $r['sent_at'],
				'accepted_at'    => $r['accepted_at'],
				'paid_at'        => $r['paid_at'],
				'updated_at'     => $r['updated_at'],
				'url'            => home_url( '/' . self::path_letter( $r['type'] ) . '/' . $r['token'] . '/' ),
			);
		}
		return $out;
	}

	public static function doc_detail( $id ) {
		$r = self::doc_row( $id );
		if ( ! $r ) {
			return null;
		}
		$doc      = self::shape_doc( $r );
		$customer = $doc['customer_id'] ? self::customer( $doc['customer_id'] ) : null;
		$out      = array(
			'doc'      => $doc,
			'customer' => $customer,
			'payments' => 'invoice' === $doc['type'] ? self::payments( array( 'doc_id' => $doc['id'] ) ) : array(),
			'events'   => self::events( array( 'doc_id' => $doc['id'], 'limit' => 200 ) ),
			'children' => array(),
			'parent'   => null,
			'credit'   => $customer ? self::customer_credit( $customer['id'] ) : 0,
			'hash'     => self::fingerprint( $doc ),
		);
		if ( ! axh_is_manager() ) {
			$out['events'] = array_values( array_filter( $out['events'], function ( $e ) {
				return in_array( $e['type'], array( 'created', 'sent', 'viewed', 'accepted', 'declined', 'payment', 'refund', 'reminder', 'converted', 'void', 'copied' ), true );
			} ) );
			foreach ( $out['events'] as &$e ) {
				if ( 'sent' !== $e['type'] && 'reminder' !== $e['type'] ) {
					$e['detail'] = preg_replace( '/\s*·\s*IP .*/', '', (string) $e['detail'] );
				}
			}
			unset( $e );
		}
		foreach ( (array) $doc['content']['converted'] as $cid ) {
			$c = self::doc_row( $cid );
			if ( $c ) {
				$out['children'][] = array( 'id' => (int) $c['id'], 'number' => $c['number'], 'type' => $c['type'], 'milestone' => $c['milestone'], 'total' => (float) $c['total'], 'status' => self::display_status( $c ) );
			}
		}
		if ( $doc['parent_id'] ) {
			$p = self::doc_row( $doc['parent_id'] );
			if ( $p ) {
				$out['parent'] = array( 'id' => (int) $p['id'], 'number' => $p['number'], 'type' => $p['type'] );
			}
		}
		if ( $doc['lead_id'] ) {
			$l = AXH_DB::get_lead( $doc['lead_id'] );
			if ( $l ) {
				$out['lead'] = array( 'id' => (int) $l['id'], 'company' => $l['company'], 'stage' => $l['stage'], 'contract_value' => (float) $l['contract_value'], 'commission_rate' => (float) $l['commission_rate'], 'deposit_at' => $l['deposit_at'], 'final_at' => $l['final_at'] );
			}
		}
		return $out;
	}

	/**
	 * A stable fingerprint of everything the customer is agreeing to. Sent
	 * with an acceptance; if the document changed since the page loaded, the
	 * acceptance is refused so nobody signs something they did not see.
	 */
	public static function fingerprint( $doc ) {
		$c = $doc['content'];
		unset( $c['accepted'], $c['converted'] );
		$data = array(
			$doc['type'], $doc['number'], $doc['title'], $doc['customer_id'], $doc['issue_date'], $doc['expiry_date'], $doc['due_date'],
			$c, $doc['lines'], $doc['discount_type'], $doc['discount_value'], $doc['tax_on'], $doc['tax_rate'], $doc['notes'], $doc['terms_text'],
		);
		return hash( 'sha256', wp_json_encode( $data ) );
	}

	/* ---- writing ---- */

	protected static function locked( $r ) {
		if ( 'void' === $r['status'] ) {
			return 'This document is void.';
		}
		if ( 'accepted' === $r['status'] ) {
			return 'This ' . strtolower( self::type_label( $r['type'] ) ) . ' was accepted, so its contents are locked. Duplicate it to make changes.';
		}
		if ( 'invoice' === $r['type'] && (float) $r['amount_paid'] > 0.004 ) {
			return 'Payments are recorded on this invoice, so its amounts are locked. Void the payments first to change it.';
		}
		return '';
	}

	public static function due_from( $issue, $terms, $days ) {
		$tl = self::terms_list();
		$n  = 'custom' === $terms ? max( 0, (int) $days ) : (int) ( $tl[ $terms ]['days'] ?? 15 );
		return gmdate( 'Y-m-d', strtotime( $issue . ' +' . $n . ' days' ) );
	}

	/**
	 * Create (id 0) or update a document. Only AdaptExec (managers) writes.
	 */
	public static function save_doc( $id, $in ) {
		global $wpdb;
		if ( ! axh_is_manager() ) {
			return new WP_Error( 'axh_forbidden', 'Only AdaptExec creates and edits documents.', array( 'status' => 403 ) );
		}
		$in  = (array) $in;
		$t   = self::t( 'bdocs' );
		$cur = $id ? self::doc_row( $id ) : null;
		if ( $id && ! $cur ) {
			return new WP_Error( 'axh_not_found', 'Document not found.', array( 'status' => 404 ) );
		}
		$type = $cur ? $cur['type'] : ( in_array( $in['type'] ?? '', self::TYPES, true ) ? $in['type'] : '' );
		if ( ! $type ) {
			return new WP_Error( 'axh_bad_type', 'Choose proposal, estimate or invoice.', array( 'status' => 400 ) );
		}
		$s   = self::settings();
		$row = array();

		// Internal notes can always change, even on a locked document.
		if ( isset( $in['internal_notes'] ) ) {
			$row['internal_notes'] = self::longtxt( $in['internal_notes'], 8000 );
		}
		$lock    = $cur ? self::locked( $cur ) : '';
		$content_keys = array( 'customer_id', 'title', 'content', 'lines', 'discount_type', 'discount_value', 'tax_on', 'tax_rate', 'tax_label', 'notes', 'terms_text', 'issue_date', 'expiry_date', 'due_date', 'terms', 'terms_days', 'milestone' );
		$touches = array_intersect( $content_keys, array_keys( $in ) );
		if ( $lock && $touches ) {
			// Allow a locked invoice's dates/notes to be corrected? No: keep it simple and honest.
			return new WP_Error( 'axh_locked', $lock, array( 'status' => 409 ) );
		}

		if ( isset( $in['customer_id'] ) || ! $id ) {
			$cid = (int) ( $in['customer_id'] ?? 0 );
			$cr  = $cid ? self::customer_row( $cid ) : null;
			if ( ! $cr ) {
				return new WP_Error( 'axh_bad_customer', 'Choose a customer.', array( 'status' => 400 ) );
			}
			$row['customer_id'] = $cid;
			$row['rep_id']      = (int) $cr['rep_id'];
			$row['lead_id']     = $cr['lead_id'] ? (int) $cr['lead_id'] : null;
		}
		if ( isset( $in['lead_id'] ) && axh_is_manager() ) {
			$lid = (int) $in['lead_id'];
			$l   = $lid ? AXH_DB::get_lead( $lid ) : null;
			if ( $l ) {
				$row['lead_id'] = $lid;
				$row['rep_id']  = (int) $l['rep_id'];
			}
		}
		if ( isset( $in['title'] ) ) {
			$row['title'] = self::txt( $in['title'], 191 );
		}
		if ( isset( $in['content'] ) ) {
			$prev = $cur ? json_decode( (string) $cur['content'], true ) : array();
			$cc   = (array) $in['content'];
			if ( is_array( $prev ) ) {
				$cc['converted'] = $prev['converted'] ?? array();
				if ( isset( $prev['accepted'] ) ) {
					$cc['accepted'] = $prev['accepted'];
				}
			}
			$content = self::clean_content( $cc, $type );
			if ( is_wp_error( $content ) ) {
				return $content;
			}
			$row['content'] = wp_json_encode( $content );
		}
		if ( isset( $in['lines'] ) ) {
			$row['line_items'] = wp_json_encode( self::clean_lines( $in['lines'] ) );
		}
		if ( isset( $in['discount_type'] ) ) {
			$row['discount_type'] = 'amt' === $in['discount_type'] ? 'amt' : 'pct';
		}
		if ( isset( $in['discount_value'] ) ) {
			$row['discount_value'] = max( 0, round( (float) $in['discount_value'], 2 ) );
		}
		if ( isset( $in['tax_on'] ) ) {
			$row['tax_on'] = empty( $in['tax_on'] ) ? 0 : 1;
		}
		if ( isset( $in['tax_rate'] ) ) {
			$row['tax_rate'] = max( 0, min( 30, round( (float) $in['tax_rate'], 3 ) ) );
		}
		if ( isset( $in['tax_label'] ) ) {
			$row['tax_label'] = self::txt( $in['tax_label'], 40 );
		}
		foreach ( array( 'notes' => 6000, 'terms_text' => 12000 ) as $k => $len ) {
			if ( isset( $in[ $k ] ) ) {
				$row[ $k ] = self::longtxt( $in[ $k ], $len );
			}
		}
		if ( isset( $in['milestone'] ) && 'invoice' === $type ) {
			$row['milestone'] = in_array( $in['milestone'], array( 'deposit', 'final', 'full' ), true ) ? $in['milestone'] : '';
		}

		$issue = isset( $in['issue_date'] ) ? self::date_or_null( $in['issue_date'] ) : ( $cur ? $cur['issue_date'] : null );
		$issue = $issue ? $issue : self::today();
		$row['issue_date'] = $issue;
		if ( 'invoice' === $type ) {
			$terms = isset( $in['terms'] ) ? ( isset( self::terms_list()[ $in['terms'] ] ) ? $in['terms'] : $s['due_terms'] ) : ( $cur ? $cur['terms'] : $s['due_terms'] );
			$tdays = isset( $in['terms_days'] ) ? max( 0, min( 365, (int) $in['terms_days'] ) ) : ( $cur ? $cur['terms_days'] : null );
			$row['terms']      = $terms;
			$row['terms_days'] = 'custom' === $terms ? (int) $tdays : null;
			$due = isset( $in['due_date'] ) ? self::date_or_null( $in['due_date'] ) : null;
			if ( ! $due && ( ! $cur || isset( $in['terms'] ) || isset( $in['issue_date'] ) || isset( $in['terms_days'] ) ) ) {
				$due = self::due_from( $issue, $terms, $tdays );
			}
			if ( $due ) {
				$row['due_date'] = $due < $issue ? $issue : $due;
			}
		} else {
			$exp = isset( $in['expiry_date'] ) ? self::date_or_null( $in['expiry_date'] ) : null;
			if ( ! $exp && ! $cur ) {
				$exp = gmdate( 'Y-m-d', strtotime( $issue . ' +' . (int) $s['expiry_days'] . ' days' ) );
			}
			if ( $exp ) {
				$row['expiry_date'] = $exp;
			}
		}

		$row['updated_at'] = self::now();
		if ( ! $id ) {
			list( $num, $seq ) = self::next_number( $type );
			$row += array(
				'type'           => $type,
				'number'         => $num,
				'seq'            => $seq,
				'status'         => 'draft',
				'token'          => self::new_token(),
				'content'        => wp_json_encode( self::clean_content( array( 'schedule' => 'invoice' === $type ? array() : self::default_schedule() ), $type ) ),
				'line_items'     => '[]',
				'discount_type'  => 'pct',
				'tax_on'         => (int) $s['tax_on'],
				'tax_rate'       => (float) $s['tax_rate'],
				'tax_label'      => $s['tax_label'],
				'notes'          => $s['notes_text'],
				'terms_text'     => $s['terms_text'],
				'created_by'     => get_current_user_id(),
				'created_at'     => self::now(),
			);
			if ( isset( $in['parent_id'] ) ) {
				$row['parent_id'] = (int) $in['parent_id'];
			}
			$wpdb->insert( $t, $row );
			$id = (int) $wpdb->insert_id;
			if ( ! $id ) {
				return new WP_Error( 'axh_failed', 'Could not create that document.', array( 'status' => 500 ) );
			}
			self::event( $id, $row['customer_id'], 'created', self::type_label( $type ) . ' ' . $num . ' created' );
		} else {
			$wpdb->update( $t, $row, array( 'id' => (int) $id ) );
			if ( $touches || isset( $row['internal_notes'] ) ) {
				self::event( $id, $cur['customer_id'], 'updated', self::type_label( $type ) . ' ' . $cur['number'] . ' edited' );
			}
			if ( $touches ) {
				do_action( 'axh_bill_doc_changed', (int) $id, 'edited' ); // 1.21.1
			}
		}
		self::recalc( $id );
		return self::doc_detail( $id );
	}

	/** Totals, balance and invoice status from the lines and the payments on record. */
	public static function recalc( $id ) {
		global $wpdb;
		$r = self::doc_row( $id, false );
		if ( ! $r ) {
			return;
		}
		$doc = self::shape_doc( $r, 'admin' );
		if ( 'accepted' === $r['status'] && ! empty( $doc['content']['accepted'] ) ) {
			$acc   = $doc['content']['accepted'];
			$tot   = self::calc( self::lines_for( $doc, $acc['option'] ?? '', $acc['addons'] ?? array() ), $doc );
		} else {
			$tot = self::calc( self::lines_for( $doc ), $doc );
		}
		$row = array(
			'subtotal'       => $tot['subtotal'],
			'discount_total' => $tot['discount'],
			'tax_total'      => $tot['tax'],
			'total'          => $tot['total'],
		);
		if ( 'invoice' === $r['type'] ) {
			$paid  = self::invoice_paid( $id );
			$bal   = round( $tot['total'] - $paid, 2 );
			$row['amount_paid'] = $paid;
			$row['balance']     = $bal;
			if ( 'void' !== $r['status'] && 'draft' !== $r['status'] ) {
				if ( $paid > 0.004 && $bal <= 0.004 ) {
					$row['status']  = 'paid';
					$row['paid_at'] = $r['paid_at'] ? $r['paid_at'] : self::last_payment_date( $id );
				} elseif ( $paid > 0.004 ) {
					$row['status']  = 'partial';
					$row['paid_at'] = null;
				} else {
					$row['status']  = in_array( $r['status'], array( 'paid', 'partial' ), true ) ? ( $r['viewed_at'] ? 'viewed' : 'sent' ) : $r['status'];
					$row['paid_at'] = null;
				}
			} elseif ( 'draft' === $r['status'] && $paid > 0.004 ) {
				$row['status']  = $bal <= 0.004 ? 'paid' : 'partial';
				$row['paid_at'] = $bal <= 0.004 ? self::last_payment_date( $id ) : null;
			}
		} else {
			$row['balance'] = 0;
		}
		$wpdb->update( self::t( 'bdocs' ), $row, array( 'id' => (int) $id ) );
		if ( 'invoice' === $r['type'] ) {
			self::sync_leg( $id );
		}
	}

	protected static function last_payment_date( $doc_id ) {
		global $wpdb;
		$t = self::t( 'bpay' );
		$d = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(received_at) FROM {$t} WHERE doc_id = %d AND voided_at IS NULL AND kind IN ('payment','credit')", (int) $doc_id ) );
		return $d ? $d . ' 00:00:00' : self::now();
	}

	public static function delete_doc( $id ) {
		global $wpdb;
		$r = self::doc_row( $id );
		if ( ! $r || ! axh_is_manager() ) {
			return new WP_Error( 'axh_not_found', 'Document not found.', array( 'status' => 404 ) );
		}
		if ( 'draft' !== $r['status'] || $r['sent_at'] || (float) $r['amount_paid'] > 0 ) {
			return new WP_Error( 'axh_not_draft', 'Only a draft that was never sent can be deleted. Void it instead, so the record stays.', array( 'status' => 409 ) );
		}
		$p = self::t( 'bpay' );
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p} WHERE doc_id = %d", (int) $id ) ) ) { // phpcs:ignore
			return new WP_Error( 'axh_not_draft', 'This document has payment history. Void it instead.', array( 'status' => 409 ) );
		}
		$wpdb->delete( self::t( 'bdocs' ), array( 'id' => (int) $id ) );
		self::event( 0, $r['customer_id'], 'deleted', self::type_label( $r['type'] ) . ' ' . $r['number'] . ' (draft) deleted' );
		return true;
	}

	/** Manager: void, reopen, or record a decision taken outside the page. */
	public static function set_status( $id, $status, $note = '' ) {
		global $wpdb;
		$r = self::doc_row( $id );
		if ( ! $r || ! axh_is_manager() ) {
			return new WP_Error( 'axh_not_found', 'Document not found.', array( 'status' => 404 ) );
		}
		$t    = self::t( 'bdocs' );
		$note = self::txt( $note, 400 );
		$name = self::type_label( $r['type'] ) . ' ' . $r['number'];
		switch ( $status ) {
			case 'void':
				if ( 'void' === $r['status'] ) {
					return self::doc_detail( $id );
				}
				if ( 'invoice' === $r['type'] && self::invoice_paid( $id ) > 0.004 ) {
					return new WP_Error( 'axh_has_payments', 'Void or refund the payments on this invoice first.', array( 'status' => 409 ) );
				}
				$wpdb->update( $t, array( 'status' => 'void', 'voided_at' => self::now(), 'void_reason' => $note, 'balance' => 0, 'updated_at' => self::now() ), array( 'id' => (int) $id ) );
				self::event( $id, $r['customer_id'], 'void', $name . ' voided' . ( $note ? ': ' . $note : '' ) );
				if ( $r['lead_id'] ) {
					AXH_DB::log( $r['lead_id'], 'billing', $name . ' voided', $note );
				}
				do_action( 'axh_bill_doc_changed', (int) $id, 'void' ); // 1.21.1
				break;
			case 'sent':
				// Mark as sent without emailing (for a link sent another way).
				if ( 'draft' !== $r['status'] ) {
					return new WP_Error( 'axh_bad_status', 'Only a draft can be marked as sent.', array( 'status' => 409 ) );
				}
				$wpdb->update( $t, array( 'status' => 'sent', 'sent_at' => $r['sent_at'] ? $r['sent_at'] : self::now(), 'updated_at' => self::now() ), array( 'id' => (int) $id ) );
				self::event( $id, $r['customer_id'], 'sent', 'Marked as sent (no email)' );
				self::after_sent( $r );
				break;
			case 'draft':
				if ( 'void' !== $r['status'] && 'sent' !== $r['status'] ) {
					return new WP_Error( 'axh_bad_status', 'Only a void or sent document can go back to draft.', array( 'status' => 409 ) );
				}
				$wpdb->update( $t, array( 'status' => 'draft', 'voided_at' => null, 'void_reason' => '', 'updated_at' => self::now() ), array( 'id' => (int) $id ) );
				self::event( $id, $r['customer_id'], 'updated', $name . ' reopened as a draft' );
				break;
			case 'accepted':
			case 'declined':
				if ( 'invoice' === $r['type'] || in_array( $r['status'], array( 'accepted', 'declined', 'void' ), true ) ) {
					return new WP_Error( 'axh_bad_status', 'That change is not possible from here.', array( 'status' => 409 ) );
				}
				if ( 'accepted' === $status ) {
					$doc = self::shape_doc( $r, 'admin' );
					$c   = $doc['content'];
					$c['accepted'] = array( 'option' => $c['selected'] ?? '', 'addons' => array_values( array_map( function ( $a ) { return $a['id']; }, array_filter( (array) $c['addons'], function ( $a ) { return ! empty( $a['on'] ); } ) ) ), 'by' => 'manager', 'note' => $note );
					$wpdb->update( $t, array( 'status' => 'accepted', 'accepted_at' => self::now(), 'accepted_name' => self::txt( $note ? $note : 'Recorded by ' . self::user_name( get_current_user_id() ), 120 ), 'accepted_method' => 'manual', 'accepted_hash' => self::fingerprint( $doc ), 'content' => wp_json_encode( $c ), 'updated_at' => self::now() ), array( 'id' => (int) $id ) );
					self::event( $id, $r['customer_id'], 'accepted', $name . ' marked accepted by AdaptExec' . ( $note ? ': ' . $note : '' ) );
				} else {
					$wpdb->update( $t, array( 'status' => 'declined', 'declined_at' => self::now(), 'decline_reason' => $note, 'updated_at' => self::now() ), array( 'id' => (int) $id ) );
					self::event( $id, $r['customer_id'], 'declined', $name . ' marked declined by AdaptExec' . ( $note ? ': ' . $note : '' ) );
				}
				break;
			default:
				return new WP_Error( 'axh_bad_status', 'Unknown status.', array( 'status' => 400 ) );
		}
		self::recalc( $id );
		return self::doc_detail( $id );
	}

	/** A copy as a new draft (optionally as another type: estimate → invoice). */
	public static function duplicate( $id, $type = '' ) {
		$r = self::doc_row( $id );
		if ( ! $r || ! axh_is_manager() ) {
			return new WP_Error( 'axh_not_found', 'Document not found.', array( 'status' => 404 ) );
		}
		$src  = self::shape_doc( $r, 'admin' );
		$type = in_array( $type, self::TYPES, true ) ? $type : $src['type'];
		$c    = $src['content'];
		unset( $c['accepted'] );
		$c['converted'] = array();
		$lines = $src['lines'];
		if ( 'proposal' === $src['type'] && 'proposal' !== $type ) {
			$sel   = $src['status'] === 'accepted' ? ( $c['accepted'] ?? array() ) : array();
			$lines = self::lines_for( $src, $sel['option'] ?? null, $sel['addons'] ?? null );
			foreach ( $lines as &$l ) {
				unset( $l['id'], $l['on'] );
			}
			unset( $l );
		}
		$in = array(
			'type'           => $type,
			'customer_id'    => $src['customer_id'],
			'title'          => $src['title'],
			'content'        => $c,
			'lines'          => $lines,
			'discount_type'  => $src['discount_type'],
			'discount_value' => $src['discount_value'],
			'tax_on'         => $src['tax_on'],
			'tax_rate'       => $src['tax_rate'],
			'tax_label'      => $src['tax_label'],
			'notes'          => $src['notes'],
			'terms_text'     => $src['terms_text'],
			'internal_notes' => 'Copied from ' . $src['number'] . '.',
		);
		if ( $type !== $src['type'] ) {
			$in['parent_id'] = $src['id'];
		}
		$d = self::save_doc( 0, $in );
		if ( ! is_wp_error( $d ) ) {
			self::event( $src['id'], $src['customer_id'], 'duplicated', 'Copied to ' . $d['doc']['number'] );
		}
		return $d;
	}

	/**
	 * Accepted proposal or estimate → invoices, one per payment-schedule row
	 * (50% deposit / 50% at launch by default). Each invoice is tagged with
	 * the commission half it pays for, so recording it feeds Earnings.
	 */
	public static function convert( $id, $force = false ) {
		global $wpdb;
		$r = self::doc_row( $id );
		if ( ! $r || ! axh_is_manager() ) {
			return new WP_Error( 'axh_not_found', 'Document not found.', array( 'status' => 404 ) );
		}
		if ( 'invoice' === $r['type'] ) {
			return new WP_Error( 'axh_bad_type', 'This is already an invoice.', array( 'status' => 400 ) );
		}
		if ( 'accepted' !== $r['status'] ) {
			return new WP_Error( 'axh_not_accepted', 'Convert it once the customer has accepted it (or mark it accepted).', array( 'status' => 409 ) );
		}
		$src = self::shape_doc( $r, 'admin' );
		if ( $src['content']['converted'] && ! $force ) {
			return new WP_Error( 'axh_converted', 'This was already converted to invoices.', array( 'status' => 409 ) );
		}
		$acc   = $src['content']['accepted'] ?? array();
		$lines = self::lines_for( $src, $acc['option'] ?? null, $acc['addons'] ?? null );
		$tot   = self::calc( $lines, $src );
		$sched = $src['content']['schedule'] ? $src['content']['schedule'] : array( array( 'label' => 'Payment in full', 'pct' => 100, 'leg' => 'full', 'due' => '' ) );
		$title = $src['title'] ? $src['title'] : $src['content']['cover']['title'];
		$made  = array();
		$sum   = 0.0;
		$n     = count( $sched );
		foreach ( $sched as $i => $row ) {
			$amt = $i === $n - 1 ? round( $tot['total'] - $sum, 2 ) : round( $tot['total'] * $row['pct'] / 100, 2 );
			$sum += $amt;
			$split = array();
			foreach ( $tot['categories'] as $cat => $v ) {
				$split[ $cat ] = round( $v * $row['pct'] / 100, 2 );
			}
			$leg  = $row['leg'] ? $row['leg'] : ( 1 === $n ? 'full' : ( 0 === $i ? 'deposit' : ( $i === $n - 1 ? 'final' : '' ) ) );
			$line = array(
				'name'     => $row['label'] . ' (' . rtrim( rtrim( number_format( $row['pct'], 2 ), '0' ), '.' ) . '%)',
				'desc'     => trim( ( $title ? $title . ' · ' : '' ) . self::type_label( $src['type'] ) . ' ' . $src['number'] . ( $row['due'] ? ' · ' . $row['due'] : '' ) ),
				'qty'      => 1,
				'rate'     => $amt,
				'unit'     => 'each',
				'category' => count( $split ) === 1 ? key( $split ) : 'Other',
				'split'    => $split,
			);
			$terms = ( 0 === $i || false !== stripos( $row['due'], 'accept' ) ) ? 'receipt' : self::settings()['due_terms'];
			$d     = self::save_doc(
				0,
				array(
					'type'           => 'invoice',
					'customer_id'    => $src['customer_id'],
					'title'          => trim( $row['label'] . ( $title ? ' — ' . $title : '' ) ),
					'lines'          => array( $line ),
					'tax_on'         => 0,
					'notes'          => $src['notes'],
					'terms_text'     => $src['terms_text'],
					'terms'          => $terms,
					'milestone'      => $leg,
					'parent_id'      => $src['id'],
					'internal_notes' => 'Created from ' . $src['number'] . ' (' . $row['label'] . ').',
				)
			);
			if ( is_wp_error( $d ) ) {
				return $d;
			}
			$made[] = $d['doc']['id'];
		}
		$c              = $src['content'];
		$c['converted'] = array_merge( (array) $c['converted'], $made );
		$wpdb->update( self::t( 'bdocs' ), array( 'content' => wp_json_encode( $c ), 'updated_at' => self::now() ), array( 'id' => (int) $id ) );
		$nums = array();
		foreach ( $made as $mid ) {
			$mr     = self::doc_row( $mid );
			$nums[] = $mr['number'];
		}
		self::event( $id, $src['customer_id'], 'converted', 'Converted to ' . implode( ', ', $nums ) );
		if ( $src['lead_id'] ) {
			AXH_DB::log( $src['lead_id'], 'billing', self::type_label( $src['type'] ) . ' ' . $src['number'] . ' converted to ' . implode( ', ', $nums ) );
		}
		return array( 'invoices' => $made, 'detail' => self::doc_detail( $id ) );
	}

	/** Regenerate (old link stops working) or revoke a customer link. */
	public static function relink( $id, $action ) {
		global $wpdb;
		$r = self::doc_row( $id );
		if ( ! $r ) {
			return new WP_Error( 'axh_not_found', 'Document not found.', array( 'status' => 404 ) );
		}
		if ( 'copied' === $action ) {
			self::event( $id, $r['customer_id'], 'copied', 'Customer link copied by ' . self::user_name( get_current_user_id() ) );
			if ( 'draft' === $r['status'] && axh_is_manager() ) {
				return self::set_status( $id, 'sent' );
			}
			return self::doc_detail( $id );
		}
		if ( ! axh_is_manager() ) {
			return new WP_Error( 'axh_forbidden', 'Only AdaptExec changes customer links.', array( 'status' => 403 ) );
		}
		$wpdb->update( self::t( 'bdocs' ), array( 'token' => self::new_token(), 'updated_at' => self::now() ), array( 'id' => (int) $id ) );
		self::event( $id, $r['customer_id'], 'link', 'revoke' === $action ? 'Customer link revoked (a new private link was made)' : 'Customer link regenerated; the old link no longer works' );
		return self::doc_detail( $id );
	}

	/* ------------------------------------------------------------- sending */

	protected static function limited( $what, $max, $who = null ) {
		$key = 'axh_brl_' . md5( $what . '|' . ( null === $who ? get_current_user_id() : $who ) );
		$n   = (int) get_transient( $key );
		if ( $n >= $max ) {
			return true;
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );
		return false;
	}

	/** A proposal or estimate going out moves its lead to "Proposal Sent". */
	protected static function after_sent( $r ) {
		if ( 'invoice' === $r['type'] || ! $r['lead_id'] ) {
			return;
		}
		global $wpdb;
		$lt   = AXH_DB::table( 'leads' );
		$lead = $wpdb->get_row( $wpdb->prepare( "SELECT id, stage FROM {$lt} WHERE id = %d", (int) $r['lead_id'] ), ARRAY_A );
		if ( $lead && in_array( $lead['stage'], array( 'new', 'contacted', 'discovery', 'scoping' ), true ) ) {
			$wpdb->update( $lt, array( 'stage' => 'proposal', 'updated_at' => self::now() ), array( 'id' => (int) $lead['id'] ) );
			AXH_DB::log( $lead['id'], 'stage', 'Stage → Proposal Sent', self::type_label( $r['type'] ) . ' ' . $r['number'] . ' was sent' );
		}
	}

	/**
	 * Email the customer their link. AdaptExec can send anything; a rep can
	 * send (or remind about) documents for their own clients, and the log
	 * records who sent it.
	 */
	public static function send_doc( $id, $in ) {
		global $wpdb;
		$r = self::doc_row( $id );
		if ( ! $r ) {
			return new WP_Error( 'axh_not_found', 'Document not found.', array( 'status' => 404 ) );
		}
		if ( 'void' === $r['status'] ) {
			return new WP_Error( 'axh_void', 'This document is void.', array( 'status' => 409 ) );
		}
		$in   = (array) $in;
		$kind = 'reminder' === ( $in['kind'] ?? '' ) ? 'reminder' : 'send';
		$cust = self::customer_row( $r['customer_id'], false );
		$to   = trim( (string) ( $in['to'] ?? ( $cust ? $cust['email'] : '' ) ) );
		if ( ! is_email( $to ) ) {
			return new WP_Error( 'axh_bad_email', 'Enter the customer’s email address.', array( 'status' => 400 ) );
		}
		$cc = array();
		foreach ( array_filter( array_map( 'trim', preg_split( '/[,;\s]+/', (string) ( $in['cc'] ?? '' ) ) ) ) as $e ) {
			if ( is_email( $e ) && strtolower( $e ) !== strtolower( $to ) ) {
				$cc[] = sanitize_email( $e );
			}
		}
		$cc = array_slice( array_unique( $cc ), 0, 5 );
		if ( self::limited( 'send', 30 ) ) {
			return new WP_Error( 'axh_slow_down', 'That is a lot of emails in an hour. Try again later.', array( 'status' => 429 ) );
		}
		if ( 'draft' === $r['status'] ) {
			$wpdb->update( self::t( 'bdocs' ), array( 'status' => 'sent', 'updated_at' => self::now() ), array( 'id' => (int) $id ) );
			$r['status'] = 'sent';
		}
		$doc  = self::shape_doc( self::doc_row( $id, false ), 'public' );
		$msg  = self::longtxt( $in['message'] ?? '', 3000 );
		$mail = AXH_Bill_View::email( $doc, $cust, $msg, $kind );
		$me   = wp_get_current_user();
		$s    = self::settings();
		$biz  = self::business();
		$reply = is_email( $s['reply_to'] ) ? $s['reply_to'] : $biz['email'];
		if ( ! axh_is_manager() && $me && is_email( $me->user_email ) ) {
			$reply = $me->user_email;
		}
		$headers = array( 'Content-Type: text/html; charset=UTF-8', 'Reply-To: ' . $reply );
		foreach ( $cc as $e ) {
			$headers[] = 'Cc: ' . $e;
		}
		AXH_Mail::set_alt( $mail['text'] );
		$ok = (bool) wp_mail( $to, $mail['subject'], $mail['html'], $headers );
		$who = $me && $me->ID ? $me->display_name : 'System';
		$detail = ( 'reminder' === $kind ? 'Reminder emailed' : 'Emailed' ) . ' to ' . $to . ( $cc ? ' (cc ' . implode( ', ', $cc ) . ')' : '' ) . ' by ' . $who . ( $ok ? '' : ' — the mail server refused it' );
		$upd = array( 'updated_at' => self::now() );
		if ( $ok ) {
			$upd['sent_at'] = self::now();
			if ( 'reminder' === $kind ) {
				$upd['reminders_sent'] = (int) $r['reminders_sent'] + 1;
				$upd['reminder_at']    = self::now();
			}
		}
		$wpdb->update( self::t( 'bdocs' ), $upd, array( 'id' => (int) $id ) );
		self::event( $id, $r['customer_id'], 'reminder' === $kind ? 'reminder' : 'sent', $detail );
		if ( $r['lead_id'] ) {
			AXH_DB::log( $r['lead_id'], 'billing', self::type_label( $r['type'] ) . ' ' . $r['number'] . ( 'reminder' === $kind ? ' reminder sent' : ' sent to the customer' ), $detail );
		}
		if ( $ok && 'send' === $kind ) {
			self::after_sent( $r );
			do_action( 'axh_bill_doc_sent', (int) $id ); // 1.21.0
		}
		if ( ! $ok ) {
			return new WP_Error( 'axh_mail_failed', 'The mail server did not accept the email. Copy the link and send it another way.', array( 'status' => 502 ) );
		}
		return self::doc_detail( $id );
	}

	/** Automatic overdue reminders (off unless switched on in Billing settings). */
	public static function daily( $force = false ) {
		global $wpdb;
		$s = self::settings();
		if ( empty( $s['remind_on'] ) && ! $force ) {
			return 0;
		}
		$t     = self::t( 'bdocs' );
		$today = self::today();
		$rows  = $wpdb->get_results( "SELECT * FROM {$t} WHERE type = 'invoice' AND status IN ('sent','viewed','partial') AND balance > 0.004 AND due_date IS NOT NULL", ARRAY_A ); // phpcs:ignore
		$sent  = 0;
		foreach ( $rows as $r ) {
			if ( gmdate( 'Y-m-d', strtotime( $r['due_date'] . ' +' . (int) $s['remind_after'] . ' days' ) ) > $today ) {
				continue;
			}
			if ( (int) $r['reminders_sent'] >= (int) $s['remind_max'] ) {
				continue;
			}
			if ( $r['reminder_at'] && gmdate( 'Y-m-d', strtotime( $r['reminder_at'] . ' +' . (int) $s['remind_every'] . ' days' ) ) > $today ) {
				continue;
			}
			$cust = self::customer_row( $r['customer_id'], false );
			if ( ! $cust || ! is_email( $cust['email'] ) ) {
				continue;
			}
			$doc  = self::shape_doc( $r, 'public' );
			$mail = AXH_Bill_View::email( $doc, $cust, '', 'reminder' );
			$biz  = self::business();
			AXH_Mail::set_alt( $mail['text'] );
			$ok = (bool) wp_mail( $cust['email'], $mail['subject'], $mail['html'], array( 'Content-Type: text/html; charset=UTF-8', 'Reply-To: ' . ( is_email( $s['reply_to'] ) ? $s['reply_to'] : $biz['email'] ) ) );
			if ( $ok ) {
				$wpdb->update( $t, array( 'reminders_sent' => (int) $r['reminders_sent'] + 1, 'reminder_at' => self::now() ), array( 'id' => (int) $r['id'] ) );
				$sent++;
			}
			self::event( $r['id'], $r['customer_id'], 'reminder', 'Automatic overdue reminder emailed to ' . $cust['email'] . ( $ok ? '' : ' — the mail server refused it' ), 0 );
		}
		return $sent;
	}

	/* -------------------------------------------------- customer actions */

	/** A person opening the link, not a robot, the rep or AdaptExec. */
	public static function record_view( $doc ) {
		global $wpdb;
		if ( AXH_Portal::is_preview_bot() || AXH_Portal::is_internal_viewer( $doc['rep_id'] ) ) {
			return false;
		}
		if ( in_array( $doc['status'], array( 'draft', 'void' ), true ) ) {
			return false;
		}
		$t     = self::t( 'bdocs' );
		$row   = self::doc_row( $doc['id'], false );
		$first = empty( $row['viewed_at'] );
		$upd   = array( 'view_count' => (int) $row['view_count'] + 1, 'last_viewed_at' => self::now() );
		if ( $first ) {
			$upd['viewed_at'] = self::now();
		}
		if ( 'sent' === $row['status'] ) {
			$upd['status'] = 'viewed';
		}
		$wpdb->update( $t, $upd, array( 'id' => (int) $doc['id'] ) );
		$quiet = $row['last_viewed_at'] && ( time() - strtotime( $row['last_viewed_at'] ) ) < 30 * MINUTE_IN_SECONDS;
		if ( ! $quiet ) {
			self::event( $doc['id'], $doc['customer_id'], 'viewed', ( $first ? 'Opened by the customer for the first time' : 'Opened again by the customer' ), 0 );
		}
		if ( $first && self::settings()['notify_views'] ) {
			$cust  = self::customer_row( $doc['customer_id'], false );
			$name  = $cust ? ( $cust['company'] ? $cust['company'] : $cust['contact_name'] ) : 'The customer';
			$title = $name . ' opened ' . strtolower( self::type_label( $doc['type'] ) ) . ' ' . $doc['number'];
			$body  = ( $doc['title'] ? $doc['title'] . "\n\n" : '' ) . 'Total ' . self::fmt( $doc['total'] ) . '.';
			$link  = '#/billing/doc/' . $doc['id'];
			AXH_Notify::managers_add( 'billing_viewed', $title, $body, $link, $doc['lead_id'] ? $doc['lead_id'] : null );
			if ( $doc['rep_id'] && ! axh_is_manager( $doc['rep_id'] ) ) {
				AXH_Notify::add( $doc['rep_id'], 'billing_viewed', $title, $body, $link, $doc['lead_id'] ? $doc['lead_id'] : null );
			}
		}
		return true;
	}

	public static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	protected static function decidable( $doc ) {
		if ( 'invoice' === $doc['type'] ) {
			return new WP_Error( 'axh_bad_type', 'Invoices are paid, not accepted.', array( 'status' => 400 ) );
		}
		if ( 'accepted' === $doc['status'] ) {
			return new WP_Error( 'axh_already', 'This was already accepted.', array( 'status' => 409 ) );
		}
		if ( 'declined' === $doc['status'] ) {
			return new WP_Error( 'axh_already', 'This was declined. Contact us if you would like to revisit it.', array( 'status' => 409 ) );
		}
		if ( in_array( $doc['status'], array( 'draft', 'void' ), true ) ) {
			return new WP_Error( 'axh_unavailable', 'This document is not available.', array( 'status' => 404 ) );
		}
		if ( 'expired' === $doc['display_status'] ) {
			return new WP_Error( 'axh_expired', 'This ' . strtolower( $doc['type_label'] ) . ' has expired. Contact us for an updated one.', array( 'status' => 409 ) );
		}
		return true;
	}

	protected static function notify_decision( $doc, $title, $body ) {
		$link = '#/billing/doc/' . $doc['id'];
		AXH_Notify::managers_add( 'billing_decision', $title, $body, $link, $doc['lead_id'] ? $doc['lead_id'] : null );
		if ( $doc['rep_id'] && ! axh_is_manager( $doc['rep_id'] ) ) {
			AXH_Notify::add( $doc['rep_id'], 'billing_decision', $title, $body, $link, $doc['lead_id'] ? $doc['lead_id'] : null );
		}
	}

	/**
	 * The customer accepts on the page: typed name, a tick, and (optionally) a
	 * drawn or typed signature. Records time, IP, browser and the document
	 * fingerprint, and locks the accepted version.
	 */
	public static function accept_public( $token, $in ) {
		global $wpdb;
		$doc = self::doc_by_token( $token );
		if ( ! $doc ) {
			return new WP_Error( 'axh_not_found', 'This link is not valid.', array( 'status' => 404 ) );
		}
		$ip = self::client_ip();
		if ( self::limited( 'accept|' . $doc['id'], 8, $ip ) || self::limited( 'accept-doc|' . $doc['id'], 40, 'all' ) ) {
			return new WP_Error( 'axh_slow_down', 'Too many attempts. Please wait a few minutes and try again.', array( 'status' => 429 ) );
		}
		$ok = self::decidable( $doc );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$in = (array) $in;
		if ( ! hash_equals( self::fingerprint( $doc ), (string) ( $in['hash'] ?? '' ) ) ) {
			return new WP_Error( 'axh_changed', 'This document was updated after you opened it. Reload the page to see the latest version, then accept.', array( 'status' => 409 ) );
		}
		$name = trim( preg_replace( '/\s+/', ' ', sanitize_text_field( (string) ( $in['name'] ?? '' ) ) ) );
		if ( mb_strlen( $name ) < 2 || mb_strlen( $name ) > 120 ) {
			return new WP_Error( 'axh_bad_name', 'Type your full name to accept.', array( 'status' => 400 ) );
		}
		if ( empty( $in['agree'] ) ) {
			return new WP_Error( 'axh_bad_agree', 'Tick the box to confirm you agree.', array( 'status' => 400 ) );
		}
		$method = in_array( $in['method'] ?? '', array( 'draw', 'type' ), true ) ? $in['method'] : 'type';
		$sig    = '';
		if ( 'draw' === $method ) {
			$sig = (string) ( $in['signature'] ?? '' );
			if ( ! preg_match( '#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $sig, $m ) || strlen( $sig ) > 400000 ) {
				return new WP_Error( 'axh_bad_sig', 'Please draw your signature again.', array( 'status' => 400 ) );
			}
			$bin = base64_decode( $m[1], true );
			if ( ! $bin || "\x89PNG\r\n\x1a\n" !== substr( $bin, 0, 8 ) ) {
				return new WP_Error( 'axh_bad_sig', 'Please draw your signature again.', array( 'status' => 400 ) );
			}
		}
		$c      = $doc['content'];
		$option = '';
		$addons = array();
		if ( 'proposal' === $doc['type'] ) {
			$ids    = wp_list_pluck( (array) $c['options'], 'id' );
			$option = in_array( (string) ( $in['option'] ?? '' ), $ids, true ) ? (string) $in['option'] : (string) $c['selected'];
			$aids   = wp_list_pluck( (array) $c['addons'], 'id' );
			$addons = array_values( array_intersect( $aids, array_map( 'strval', (array) ( $in['addons'] ?? array() ) ) ) );
		}
		$tot = self::calc( self::lines_for( $doc, $option, $addons ), $doc );
		$ua  = mb_substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 0, 255 );
		$at  = self::now();
		$opt_name = '';
		foreach ( (array) $c['options'] as $o ) {
			if ( $o['id'] === $option ) {
				$opt_name = $o['name'];
			}
		}
		$add_names = array();
		foreach ( (array) $c['addons'] as $a ) {
			if ( in_array( $a['id'], $addons, true ) ) {
				$add_names[] = $a['name'];
			}
		}
		$c['accepted'] = array(
			'option'      => $option,
			'option_name' => $opt_name,
			'addons'      => $addons,
			'addon_names' => $add_names,
			'total'       => $tot['total'],
			'by'          => 'customer',
		);
		$fp     = self::fingerprint( $doc );
		$record = hash( 'sha256', $fp . '|' . $option . '|' . implode( ',', $addons ) . '|' . $name . '|' . $at . '|' . $ip . '|' . hash( 'sha256', $sig ) );
		$c['accepted']['record_hash'] = $record;
		$updated = $wpdb->update(
			self::t( 'bdocs' ),
			array(
				'status'          => 'accepted',
				'accepted_at'     => $at,
				'accepted_name'   => $name,
				'accepted_ip'     => $ip,
				'accepted_ua'     => $ua,
				'accepted_hash'   => $fp,
				'accepted_method' => $method,
				'accepted_sig'    => $sig,
				'content'         => wp_json_encode( $c ),
				'updated_at'      => $at,
			),
			array( 'id' => (int) $doc['id'], 'status' => $doc['status'] )
		);
		if ( ! $updated ) {
			return new WP_Error( 'axh_changed', 'This document changed a moment ago. Reload the page.', array( 'status' => 409 ) );
		}
		self::recalc( $doc['id'] );
		$detail = 'Accepted by ' . $name . ' (' . ( 'draw' === $method ? 'drawn signature' : 'typed name' ) . ')' . ( $opt_name ? ' · ' . $opt_name : '' ) . ( $add_names ? ' + ' . implode( ', ', $add_names ) : '' ) . ' · total ' . self::fmt( $tot['total'] ) . ' · IP ' . $ip;
		self::event( $doc['id'], $doc['customer_id'], 'accepted', $detail, 0 );
		if ( $doc['lead_id'] ) {
			AXH_DB::log( $doc['lead_id'], 'billing', $doc['type_label'] . ' ' . $doc['number'] . ' accepted by ' . $name, 'Total ' . self::fmt( $tot['total'] ) . ( $opt_name ? ' · ' . $opt_name : '' ) );
		}
		$cust = self::customer_row( $doc['customer_id'], false );
		$who  = $cust ? ( $cust['company'] ? $cust['company'] : $cust['contact_name'] ) : $name;
		self::notify_decision( $doc, $who . ' accepted ' . strtolower( $doc['type_label'] ) . ' ' . $doc['number'] . ' · ' . self::fmt( $tot['total'] ), 'Accepted by ' . $name . ( $opt_name ? "\n\nOption: " . $opt_name : '' ) . ( $add_names ? "\nAdd-ons: " . implode( ', ', array_map( array( 'AXH_Bill', 'addon_label' ), $add_names ) ) : '' ) . "\n\nNext: convert it to invoices in Billing." );
		return array( 'ok' => true, 'accepted_at' => $at, 'name' => $name, 'total' => $tot['total'], 'record' => $record );
	}

	public static function decline_public( $token, $in ) {
		global $wpdb;
		$doc = self::doc_by_token( $token );
		if ( ! $doc ) {
			return new WP_Error( 'axh_not_found', 'This link is not valid.', array( 'status' => 404 ) );
		}
		$ip = self::client_ip();
		if ( self::limited( 'decline|' . $doc['id'], 8, $ip ) ) {
			return new WP_Error( 'axh_slow_down', 'Too many attempts. Please wait a few minutes and try again.', array( 'status' => 429 ) );
		}
		$ok = self::decidable( $doc );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$reason = self::longtxt( (string) ( ( (array) $in )['reason'] ?? '' ), 1000 );
		$wpdb->update( self::t( 'bdocs' ), array( 'status' => 'declined', 'declined_at' => self::now(), 'decline_reason' => $reason, 'updated_at' => self::now() ), array( 'id' => (int) $doc['id'] ) );
		self::event( $doc['id'], $doc['customer_id'], 'declined', 'Declined by the customer' . ( $reason ? ': ' . $reason : '' ) . ' · IP ' . $ip, 0 );
		if ( $doc['lead_id'] ) {
			AXH_DB::log( $doc['lead_id'], 'billing', $doc['type_label'] . ' ' . $doc['number'] . ' declined by the customer', $reason );
		}
		$cust = self::customer_row( $doc['customer_id'], false );
		$who  = $cust ? ( $cust['company'] ? $cust['company'] : $cust['contact_name'] ) : 'The customer';
		self::notify_decision( $doc, $who . ' declined ' . strtolower( $doc['type_label'] ) . ' ' . $doc['number'], $reason ? 'Reason: ' . $reason : 'No reason given.' );
		return array( 'ok' => true );
	}

	/* ------------------------------------------------------------ payments */

	public static function invoice_paid( $doc_id ) {
		global $wpdb;
		$t    = self::t( 'bpay' );
		$paid = 0.0;
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT kind, amount, credit FROM {$t} WHERE doc_id = %d AND voided_at IS NULL", (int) $doc_id ), ARRAY_A ) as $p ) {
			if ( 'payment' === $p['kind'] ) {
				$paid += (float) $p['amount'] - (float) $p['credit'];
			} elseif ( 'credit' === $p['kind'] ) {
				$paid += (float) $p['amount'];
			} elseif ( 'refund' === $p['kind'] ) {
				$paid -= (float) $p['amount'] - (float) $p['credit'];
			}
		}
		return round( $paid, 2 );
	}

	protected static function shape_payment( $r ) {
		$m = self::methods();
		return array(
			'id'           => (int) $r['id'],
			'kind'         => $r['kind'],
			'doc_id'       => (int) $r['doc_id'],
			'number'       => (string) ( $r['number'] ?? '' ),
			'customer_id'  => (int) $r['customer_id'],
			'customer'     => (string) ( $r['c_company'] ?? '' ) ? $r['c_company'] : (string) ( $r['c_contact'] ?? '' ),
			'amount'       => (float) $r['amount'],
			'credit'       => (float) $r['credit'],
			'applied'      => round( (float) $r['amount'] - (float) $r['credit'], 2 ),
			'method'       => $r['method'],
			'method_label' => $m[ $r['method'] ] ?? ucfirst( (string) $r['method'] ),
			'reference'    => $r['reference'],
			'received_at'  => $r['received_at'],
			'cleared'      => (bool) $r['cleared'],
			'cleared_at'   => $r['cleared_at'],
			'note'         => axh_is_manager() ? (string) $r['note'] : '',
			'refund_of'    => $r['refund_of'] ? (int) $r['refund_of'] : 0,
			'voided_at'    => $r['voided_at'],
			'void_reason'  => axh_is_manager() ? (string) $r['void_reason'] : '',
			'recorded_by'  => self::user_name( $r['recorded_by'] ),
			'external_id'  => axh_is_manager() ? (string) $r['external_id'] : '',
			'created_at'   => $r['created_at'],
		);
	}

	public static function payments( $args = array() ) {
		global $wpdb;
		$t     = self::t( 'bpay' );
		$d     = self::t( 'bdocs' );
		$c     = self::t( 'customers' );
		$where = array( axh_is_manager() ? '1=1' : $wpdb->prepare( "d.rep_id = %d AND d.status <> 'draft'", get_current_user_id() ) );
		if ( ! empty( $args['doc_id'] ) ) {
			$where[] = $wpdb->prepare( 'p.doc_id = %d', (int) $args['doc_id'] );
		}
		if ( ! empty( $args['customer_id'] ) ) {
			$where[] = $wpdb->prepare( 'p.customer_id = %d', (int) $args['customer_id'] );
		}
		if ( ! empty( $args['method'] ) ) {
			$where[] = $wpdb->prepare( 'p.method = %s', sanitize_key( $args['method'] ) );
		}
		if ( ! empty( $args['from'] ) && strtotime( $args['from'] ) ) {
			$where[] = $wpdb->prepare( 'p.received_at >= %s', gmdate( 'Y-m-d', strtotime( $args['from'] ) ) );
		}
		if ( ! empty( $args['to'] ) && strtotime( $args['to'] ) ) {
			$where[] = $wpdb->prepare( 'p.received_at <= %s', gmdate( 'Y-m-d', strtotime( $args['to'] ) ) );
		}
		if ( ! empty( $args['uncleared'] ) ) {
			$where[] = "p.cleared = 0 AND p.voided_at IS NULL AND p.kind = 'payment'";
		}
		if ( ! empty( $args['q'] ) ) {
			$like    = '%' . $wpdb->esc_like( (string) $args['q'] ) . '%';
			$where[] = $wpdb->prepare( '(c.company LIKE %s OR c.contact_name LIKE %s OR d.number LIKE %s OR p.reference LIKE %s)', $like, $like, $like, $like );
		}
		$rows = $wpdb->get_results(
			"SELECT p.*, d.number, c.company AS c_company, c.contact_name AS c_contact FROM {$t} p
			 LEFT JOIN {$d} d ON d.id = p.doc_id LEFT JOIN {$c} c ON c.id = p.customer_id
			 WHERE " . implode( ' AND ', $where ) . ' ORDER BY p.received_at DESC, p.id DESC LIMIT 2000',
			ARRAY_A
		); // phpcs:ignore
		return array_map( array( __CLASS__, 'shape_payment' ), $rows );
	}

	/** Payments on one invoice for the customer's page (no internal notes). */
	public static function payments_public( $doc_id ) {
		global $wpdb;
		$t    = self::t( 'bpay' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE doc_id = %d AND voided_at IS NULL ORDER BY received_at ASC, id ASC", (int) $doc_id ), ARRAY_A );
		$m    = self::methods();
		$out  = array();
		foreach ( $rows as $r ) {
			$out[] = array( 'kind' => $r['kind'], 'amount' => (float) $r['amount'], 'method' => $r['method'], 'method_label' => $m[ $r['method'] ] ?? '', 'reference' => (string) $r['reference'], 'received_at' => $r['received_at'] );
		}
		return $out;
	}

	public static function payment_row( $id ) {
		global $wpdb;
		$t = self::t( 'bpay' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A );
	}

	/**
	 * Record money received against an invoice. Anything over the balance
	 * becomes a credit on the customer's account. Checks start "not cleared":
	 * commission only becomes payable once funds clear (agreement 3.4).
	 */
	public static function record_payment( $in, $system = false ) {
		global $wpdb;
		if ( ! $system && ! axh_is_manager() ) {
			return new WP_Error( 'axh_forbidden', 'Only AdaptExec records payments.', array( 'status' => 403 ) );
		}
		$in  = (array) $in;
		$r   = self::doc_row( (int) ( $in['doc_id'] ?? 0 ), false );
		if ( ! $r || 'invoice' !== $r['type'] ) {
			return new WP_Error( 'axh_not_found', 'Choose an invoice to apply the payment to.', array( 'status' => 404 ) );
		}
		if ( 'void' === $r['status'] ) {
			return new WP_Error( 'axh_void', 'That invoice is void.', array( 'status' => 409 ) );
		}
		$amount = self::money( $in['amount'] ?? 0 );
		if ( $amount <= 0 ) {
			return new WP_Error( 'axh_bad_amount', 'Enter the amount received.', array( 'status' => 400 ) );
		}
		if ( $amount > 10000000 ) {
			return new WP_Error( 'axh_bad_amount', 'That amount is too large.', array( 'status' => 400 ) );
		}
		$method = isset( self::methods()[ $in['method'] ?? '' ] ) ? $in['method'] : 'other';
		$recv   = self::date_or_null( $in['received_at'] ?? '' );
		$recv   = $recv ? $recv : self::today();
		if ( $recv > gmdate( 'Y-m-d', strtotime( self::today() . ' +1 day' ) ) ) {
			return new WP_Error( 'axh_bad_date', 'The date received cannot be in the future.', array( 'status' => 400 ) );
		}
		$cleared = array_key_exists( 'cleared', $in ) ? ! empty( $in['cleared'] ) : 'check' !== $method;
		$cdate   = $cleared ? ( self::date_or_null( $in['cleared_at'] ?? '' ) ?: $recv ) : null;
		$balance = max( 0, round( (float) $r['total'] - self::invoice_paid( $r['id'] ), 2 ) );
		$applied = min( $amount, $balance );
		$credit  = round( $amount - $applied, 2 );
		$wpdb->insert(
			self::t( 'bpay' ),
			array(
				'customer_id' => (int) $r['customer_id'],
				'doc_id'      => (int) $r['id'],
				'kind'        => 'payment',
				'amount'      => $amount,
				'credit'      => $credit,
				'method'      => $method,
				'reference'   => self::txt( $in['reference'] ?? '', 120 ),
				'received_at' => $recv,
				'cleared'     => $cleared ? 1 : 0,
				'cleared_at'  => $cdate,
				'note'        => self::longtxt( $in['note'] ?? '', 1000 ),
				'recorded_by' => get_current_user_id(),
				'created_at'  => self::now(),
			)
		);
		$pid = (int) $wpdb->insert_id;
		if ( ! $pid ) {
			return new WP_Error( 'axh_failed', 'Could not record that payment.', array( 'status' => 500 ) );
		}
		self::recalc( $r['id'] );
		$m      = self::methods();
		$detail = self::fmt( $amount ) . ' by ' . $m[ $method ] . ( ! empty( $in['reference'] ) ? ' (' . self::txt( $in['reference'], 120 ) . ')' : '' ) . ' received ' . $recv . ( $cleared ? '' : ' · not cleared yet' ) . ( $credit > 0 ? ' · ' . self::fmt( $credit ) . ' over the balance kept as customer credit' : '' );
		self::event( $r['id'], $r['customer_id'], 'payment', 'Payment recorded: ' . $detail );
		if ( $r['lead_id'] ) {
			AXH_DB::log( $r['lead_id'], 'payment', 'Client payment on ' . $r['number'] . ': ' . self::fmt( $amount ), $detail );
		}
		if ( $r['rep_id'] && ! axh_is_manager( $r['rep_id'] ) ) {
			$cust = self::customer_row( $r['customer_id'], false );
			AXH_Notify::add( (int) $r['rep_id'], 'billing_paid', 'Payment received: ' . self::fmt( $amount ) . ' on ' . $r['number'] . ( $cust ? ' (' . ( $cust['company'] ? $cust['company'] : $cust['contact_name'] ) . ')' : '' ), $cleared ? 'Commission tied to this payment becomes payable once AdaptExec confirms it.' : 'It is a check, so it counts once the funds clear.', '#/billing/doc/' . $r['id'], $r['lead_id'] ? (int) $r['lead_id'] : null );
		}
		do_action( 'axh_bill_payment_recorded', $pid, $in ); // 1.21.0: QuickBooks records it too
		return array( 'payment' => self::shape_payment( self::payment_row( $pid ) ), 'detail' => self::doc_detail( $r['id'] ) );
	}

	/** Use the customer's credit (from an overpayment) on another invoice. */
	public static function apply_credit( $doc_id, $amount ) {
		global $wpdb;
		if ( ! axh_is_manager() ) {
			return new WP_Error( 'axh_forbidden', 'Only AdaptExec applies credit.', array( 'status' => 403 ) );
		}
		$r = self::doc_row( $doc_id );
		if ( ! $r || 'invoice' !== $r['type'] || 'void' === $r['status'] ) {
			return new WP_Error( 'axh_not_found', 'Invoice not found.', array( 'status' => 404 ) );
		}
		$avail   = self::customer_credit( $r['customer_id'] );
		$balance = max( 0, round( (float) $r['total'] - self::invoice_paid( $r['id'] ), 2 ) );
		$amount  = self::money( $amount );
		$amount  = $amount > 0 ? min( $amount, $avail, $balance ) : min( $avail, $balance );
		if ( $amount <= 0 ) {
			return new WP_Error( 'axh_no_credit', 'There is no credit to apply, or nothing is owed.', array( 'status' => 400 ) );
		}
		$wpdb->insert(
			self::t( 'bpay' ),
			array(
				'customer_id' => (int) $r['customer_id'],
				'doc_id'      => (int) $r['id'],
				'kind'        => 'credit',
				'amount'      => $amount,
				'credit'      => 0,
				'method'      => 'other',
				'reference'   => 'Customer credit',
				'received_at' => self::today(),
				'cleared'     => 1,
				'cleared_at'  => self::today(),
				'recorded_by' => get_current_user_id(),
				'created_at'  => self::now(),
			)
		);
		$cpid = (int) $wpdb->insert_id;
		self::recalc( $r['id'] );
		self::event( $r['id'], $r['customer_id'], 'payment', 'Customer credit applied: ' . self::fmt( $amount ) );
		do_action( 'axh_bill_payment_changed', $cpid, 'credit' ); // 1.21.1
		return self::doc_detail( $r['id'] );
	}

	/** Refunded applied money on a deal whose commission half was already paid → a clawback. */
	protected static function maybe_clawback( $r, $removed, $why, $pay_id ) {
		global $wpdb;
		if ( $removed <= 0 || ! $r['lead_id'] || ! $r['milestone'] ) {
			return;
		}
		$lt   = AXH_DB::table( 'leads' );
		$lead = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$lt} WHERE id = %d", (int) $r['lead_id'] ), ARRAY_A );
		if ( ! $lead ) {
			return;
		}
		$legs = 'full' === $r['milestone'] ? array( 'deposit', 'final' ) : array( $r['milestone'] );
		$paid = 0.0;
		foreach ( $legs as $leg ) {
			$p = AXH_DB::active_payout( $lead['id'], $leg );
			if ( $p ) {
				$paid += (float) $p['amount'];
			}
		}
		if ( $paid <= 0 ) {
			return;
		}
		$rate = (float) $lead['commission_rate'];
		$amt  = round( min( $paid, $removed * $rate / 100 ), 2 );
		if ( $amt <= 0 ) {
			return;
		}
		$wpdb->insert(
			self::t( 'cadjust' ),
			array(
				'kind'       => 'clawback',
				'rep_id'     => (int) $lead['rep_id'],
				'lead_id'    => (int) $lead['id'],
				'leg'        => $r['milestone'],
				'doc_id'     => (int) $r['id'],
				'pay_id'     => (int) $pay_id,
				'amount'     => $amt,
				'reason'     => mb_substr( $why . ' on ' . $r['number'] . ': ' . self::fmt( $removed ) . ' at ' . rtrim( rtrim( number_format( $rate, 2 ), '0' ), '.' ) . '% (agreement 3.6)', 0, 400 ),
				'status'     => 'open',
				'created_by' => get_current_user_id(),
				'created_at' => self::now(),
			)
		);
		self::event( $r['id'], $r['customer_id'], 'clawback', 'Commission clawback of ' . self::fmt( $amt ) . ' opened for ' . self::user_name( $lead['rep_id'] ) );
		AXH_DB::log( $lead['id'], 'payment', 'Commission clawback opened: ' . self::fmt( $amt ), $why . ' on ' . $r['number'] );
	}

	public static function refund_payment( $id, $in ) {
		global $wpdb;
		if ( ! axh_is_manager() ) {
			return new WP_Error( 'axh_forbidden', 'Only AdaptExec records refunds.', array( 'status' => 403 ) );
		}
		$p = self::payment_row( $id );
		if ( ! $p || 'payment' !== $p['kind'] || $p['voided_at'] ) {
			return new WP_Error( 'axh_not_found', 'That payment is not on record.', array( 'status' => 404 ) );
		}
		$t        = self::t( 'bpay' );
		$prev     = $wpdb->get_row( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) AS a, COALESCE(SUM(credit),0) AS c FROM {$t} WHERE refund_of = %d AND voided_at IS NULL", (int) $id ), ARRAY_A );
		$left     = round( (float) $p['amount'] - (float) $prev['a'], 2 );
		$amount   = self::money( ( (array) $in )['amount'] ?? $left );
		if ( $amount <= 0 || $amount > $left + 0.001 ) {
			return new WP_Error( 'axh_bad_amount', 'A refund can be up to ' . self::fmt( $left ) . ' on this payment.', array( 'status' => 400 ) );
		}
		// An overpayment's credit is refunded first (if the customer still has it).
		$credit_left = max( 0, min( (float) $p['credit'] - (float) $prev['c'], self::customer_credit( $p['customer_id'] ) ) );
		$from_credit = round( min( $amount, $credit_left ), 2 );
		$in          = (array) $in;
		$date        = self::date_or_null( $in['refunded_at'] ?? '' ) ?: self::today();
		$wpdb->insert(
			$t,
			array(
				'customer_id' => (int) $p['customer_id'],
				'doc_id'      => (int) $p['doc_id'],
				'kind'        => 'refund',
				'amount'      => $amount,
				'credit'      => $from_credit,
				'method'      => isset( self::methods()[ $in['method'] ?? '' ] ) ? $in['method'] : $p['method'],
				'reference'   => self::txt( $in['reference'] ?? '', 120 ),
				'received_at' => $date,
				'cleared'     => 1,
				'cleared_at'  => $date,
				'note'        => self::longtxt( $in['note'] ?? '', 1000 ),
				'refund_of'   => (int) $id,
				'recorded_by' => get_current_user_id(),
				'created_at'  => self::now(),
			)
		);
		$rid = (int) $wpdb->insert_id;
		$r   = self::doc_row( $p['doc_id'], false );
		self::recalc( $p['doc_id'] );
		self::event( $p['doc_id'], $p['customer_id'], 'refund', 'Refund recorded: ' . self::fmt( $amount ) . ( $from_credit > 0 ? ' (' . self::fmt( $from_credit ) . ' from customer credit)' : '' ) );
		do_action( 'axh_bill_payment_changed', (int) $id, 'refund' ); // 1.21.1
		if ( $r ) {
			self::maybe_clawback( $r, round( $amount - $from_credit, 2 ), 'Refund', $rid );
			if ( $r['lead_id'] ) {
				AXH_DB::log( $r['lead_id'], 'payment', 'Client refund on ' . $r['number'] . ': ' . self::fmt( $amount ) );
			}
		}
		return self::doc_detail( $p['doc_id'] );
	}

	public static function void_payment( $id, $reason = '' ) {
		global $wpdb;
		if ( ! axh_is_manager() ) {
			return new WP_Error( 'axh_forbidden', 'Only AdaptExec voids payments.', array( 'status' => 403 ) );
		}
		$p = self::payment_row( $id );
		if ( ! $p || $p['voided_at'] ) {
			return new WP_Error( 'axh_not_found', 'That payment is not on record.', array( 'status' => 404 ) );
		}
		$t = self::t( 'bpay' );
		// Would voiding it leave the customer with negative credit (its credit already used)?
		if ( 'payment' === $p['kind'] && (float) $p['credit'] > 0 ) {
			$avail = self::customer_money( array( (int) $p['customer_id'] ) );
			$avail = $avail[ (int) $p['customer_id'] ]['credit'] ?? 0;
			$refc  = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(credit),0) FROM {$t} WHERE refund_of = %d AND voided_at IS NULL", (int) $id ) );
			if ( $avail - ( (float) $p['credit'] - $refc ) < -0.004 ) {
				return new WP_Error( 'axh_credit_used', 'The credit from this payment was already applied to another invoice. Void that credit first.', array( 'status' => 409 ) );
			}
		}
		$reason = self::txt( $reason, 300 );
		$now    = self::now();
		$wpdb->update( $t, array( 'voided_at' => $now, 'void_reason' => $reason ), array( 'id' => (int) $id ) );
		$removed = 'payment' === $p['kind'] ? (float) $p['amount'] - (float) $p['credit'] : 0;
		if ( 'payment' === $p['kind'] ) {
			$refunds = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE refund_of = %d AND voided_at IS NULL", (int) $id ), ARRAY_A );
			foreach ( $refunds as $rf ) {
				$removed -= (float) $rf['amount'] - (float) $rf['credit'];
			}
			$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET voided_at = %s, void_reason = %s WHERE refund_of = %d AND voided_at IS NULL", $now, 'Payment voided', (int) $id ) ); // phpcs:ignore
		}
		$r = self::doc_row( $p['doc_id'], false );
		self::recalc( $p['doc_id'] );
		$label = array( 'payment' => 'Payment', 'refund' => 'Refund', 'credit' => 'Credit application' )[ $p['kind'] ] ?? 'Entry';
		self::event( $p['doc_id'], $p['customer_id'], 'void', $label . ' of ' . self::fmt( $p['amount'] ) . ' voided' . ( $reason ? ': ' . $reason : '' ) );
		do_action( 'axh_bill_payment_changed', (int) $id, 'void' ); // 1.21.1
		if ( $r && $removed > 0 ) {
			self::maybe_clawback( $r, round( $removed, 2 ), 'Payment voided', (int) $id );
		}
		return self::doc_detail( $p['doc_id'] );
	}

	/** A check is not money until it clears. */
	public static function set_cleared( $id, $cleared, $date = '' ) {
		global $wpdb;
		if ( ! axh_is_manager() ) {
			return new WP_Error( 'axh_forbidden', 'Only AdaptExec marks payments cleared.', array( 'status' => 403 ) );
		}
		$p = self::payment_row( $id );
		if ( ! $p || $p['voided_at'] || 'payment' !== $p['kind'] ) {
			return new WP_Error( 'axh_not_found', 'That payment is not on record.', array( 'status' => 404 ) );
		}
		$d = $cleared ? ( self::date_or_null( $date ) ?: self::today() ) : null;
		if ( $d && $d < $p['received_at'] ) {
			return new WP_Error( 'axh_bad_date', 'A payment cannot clear before it was received.', array( 'status' => 400 ) );
		}
		$wpdb->update( self::t( 'bpay' ), array( 'cleared' => $cleared ? 1 : 0, 'cleared_at' => $d ), array( 'id' => (int) $id ) );
		self::recalc( $p['doc_id'] );
		self::event( $p['doc_id'], $p['customer_id'], 'payment', 'Payment of ' . self::fmt( $p['amount'] ) . ( $cleared ? ' cleared ' . $d : ' marked not cleared' ) );
		return self::doc_detail( $p['doc_id'] );
	}

	/* ------------------------------------------------ commission engine link */

	/** The invoice that pays for one commission half of a deal, if any. */
	public static function leg_invoice( $lead_id, $leg ) {
		global $wpdb;
		$t = self::t( 'bdocs' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT id, number, status, total, balance, leg_applied, milestone FROM {$t} WHERE type = 'invoice' AND lead_id = %d AND status <> 'void' AND milestone IN (%s, 'full') ORDER BY id ASC LIMIT 1", (int) $lead_id, $leg ), ARRAY_A );
	}

	/**
	 * Keep the deal's "customer deposit/final payment cleared" dates — which
	 * is what the commission engine (AXH_DB::earnings) runs on — in step with
	 * the invoice tagged for that half. One source of truth, no double entry:
	 * the date is set when the invoice is paid in full and every payment on it
	 * has cleared, and taken back if a payment is voided or refunded. A date
	 * typed in on the deal before Billing existed is left alone.
	 */
	public static function sync_leg( $doc_id ) {
		global $wpdb;
		$r = self::doc_row( $doc_id, false );
		if ( ! $r || 'invoice' !== $r['type'] || ! $r['lead_id'] || ! $r['milestone'] ) {
			return;
		}
		$lt   = AXH_DB::table( 'leads' );
		$lead = $wpdb->get_row( $wpdb->prepare( "SELECT id, deposit_at, final_at FROM {$lt} WHERE id = %d", (int) $r['lead_id'] ), ARRAY_A );
		if ( ! $lead ) {
			return;
		}
		$pt   = self::t( 'bpay' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT kind, cleared, cleared_at, received_at FROM {$pt} WHERE doc_id = %d AND voided_at IS NULL AND kind IN ('payment','credit')", (int) $doc_id ), ARRAY_A );
		$all  = (bool) $rows;
		$last = '';
		foreach ( $rows as $p ) {
			if ( ! $p['cleared'] ) {
				$all = false;
			}
			$d    = $p['cleared_at'] ? $p['cleared_at'] : $p['received_at'];
			$last = $d > $last ? $d : $last;
		}
		$done = 'paid' === $r['status'] && (float) $r['balance'] <= 0.004 && $all;
		$legs = 'full' === $r['milestone'] ? array( 'deposit', 'final' ) : array( $r['milestone'] );
		$dt   = 'invoice' === $r['type'] ? self::t( 'bdocs' ) : '';
		if ( $done && ! (int) $r['leg_applied'] ) {
			$set = array();
			foreach ( $legs as $leg ) {
				if ( empty( $lead[ $leg . '_at' ] ) ) {
					$set[ $leg . '_at' ] = $last . ' 00:00:00';
				}
			}
			if ( $set ) {
				$set['updated_at'] = self::now();
				$wpdb->update( $lt, $set, array( 'id' => (int) $lead['id'] ) );
				foreach ( $legs as $leg ) {
					if ( isset( $set[ $leg . '_at' ] ) ) {
						AXH_DB::log( $lead['id'], 'payment', 'Customer ' . ( 'deposit' === $leg ? 'deposit' : 'final payment' ) . ' cleared — ' . $r['number'], gmdate( 'j M Y', strtotime( $last ) ) . ' · commission half is now payable' );
					}
				}
			}
			$wpdb->update( $dt, array( 'leg_applied' => $set ? 1 : 2 ), array( 'id' => (int) $doc_id ) );
		} elseif ( ! $done && (int) $r['leg_applied'] ) {
			if ( 1 === (int) $r['leg_applied'] ) {
				$clear = array();
				foreach ( $legs as $leg ) {
					// Another paid invoice for the same half keeps it set.
					$other = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$dt} WHERE id <> %d AND lead_id = %d AND type = 'invoice' AND status = 'paid' AND leg_applied = 1 AND milestone IN (%s,'full')", (int) $doc_id, (int) $lead['id'], $leg ) );
					if ( ! $other ) {
						$clear[ $leg . '_at' ] = null;
					}
				}
				if ( $clear ) {
					$clear['updated_at'] = self::now();
					$wpdb->update( $lt, $clear, array( 'id' => (int) $lead['id'] ) );
					AXH_DB::log( $lead['id'], 'payment', 'Customer payment no longer counted as cleared — ' . $r['number'], 'A payment on the invoice was voided, refunded or marked not cleared.' );
				}
			}
			$wpdb->update( $dt, array( 'leg_applied' => 0 ), array( 'id' => (int) $doc_id ) );
		}
	}

	/** Commission halves on hold can't be paid (filter on AXH_DB::record_payout). */
	public static function payout_block( $block, $lead_id, $leg ) {
		$h = self::hold_for( $lead_id, $leg );
		if ( $h ) {
			return new WP_Error( 'axh_on_hold', 'This commission half is on hold: ' . $h['reason'] . '. Release the hold first.', array( 'status' => 409 ) );
		}
		return $block;
	}

	public static function hold_for( $lead_id, $leg ) {
		global $wpdb;
		$t = self::t( 'cadjust' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE kind = 'hold' AND status = 'open' AND lead_id = %d AND (leg = %s OR leg = 'both') ORDER BY id DESC LIMIT 1", (int) $lead_id, $leg ), ARRAY_A );
	}

	/* -------------------------------------------- commissions & payouts */

	public static function adjust_rows( $args = array() ) {
		global $wpdb;
		$t     = self::t( 'cadjust' );
		$l     = AXH_DB::table( 'leads' );
		$where = array( '1=1' );
		if ( ! empty( $args['kind'] ) ) {
			$where[] = $wpdb->prepare( 'a.kind = %s', $args['kind'] );
		}
		if ( ! empty( $args['rep_id'] ) ) {
			$where[] = $wpdb->prepare( 'a.rep_id = %d', (int) $args['rep_id'] );
		}
		if ( ! empty( $args['status'] ) ) {
			$where[] = $wpdb->prepare( 'a.status = %s', $args['status'] );
		}
		if ( ! empty( $args['batch_id'] ) ) {
			$where[] = $wpdb->prepare( 'a.batch_id = %d', (int) $args['batch_id'] );
		}
		$rows = $wpdb->get_results( "SELECT a.*, l.company FROM {$t} a LEFT JOIN {$l} l ON l.id = a.lead_id WHERE " . implode( ' AND ', $where ) . ' ORDER BY a.id ASC LIMIT 2000', ARRAY_A ); // phpcs:ignore
		foreach ( $rows as &$r ) {
			$r['id']       = (int) $r['id'];
			$r['rep_id']   = (int) $r['rep_id'];
			$r['lead_id']  = (int) $r['lead_id'];
			$r['amount']   = (float) $r['amount'];
			$r['batch_id'] = (int) $r['batch_id'];
			$r['rep_name'] = self::user_name( $r['rep_id'] );
			$r['by']       = self::user_name( $r['created_by'] );
		}
		return $rows;
	}

	public static function add_hold( $lead_id, $leg, $reason ) {
		global $wpdb;
		$lead = AXH_DB::get_lead( (int) $lead_id );
		if ( ! $lead || ! axh_is_manager() ) {
			return new WP_Error( 'axh_not_found', 'Deal not found.', array( 'status' => 404 ) );
		}
		$leg    = in_array( $leg, array( 'deposit', 'final', 'both' ), true ) ? $leg : 'both';
		$reason = self::txt( $reason, 300 );
		if ( '' === $reason ) {
			return new WP_Error( 'axh_bad_reason', 'Say why it is on hold (for example: chargeback dispute).', array( 'status' => 400 ) );
		}
		$wpdb->insert( self::t( 'cadjust' ), array( 'kind' => 'hold', 'rep_id' => (int) $lead['rep_id'], 'lead_id' => (int) $lead_id, 'leg' => $leg, 'amount' => 0, 'reason' => $reason, 'status' => 'open', 'created_by' => get_current_user_id(), 'created_at' => self::now() ) );
		AXH_DB::log( $lead_id, 'payment', 'Commission put on hold (' . ( 'both' === $leg ? 'both halves' : $leg . ' half' ) . ')', $reason );
		self::event( 0, 0, 'hold', $lead['company'] . ': commission on hold — ' . $reason );
		return true;
	}

	public static function resolve_adjust( $id, $action, $note = '' ) {
		global $wpdb;
		if ( ! axh_is_manager() ) {
			return new WP_Error( 'axh_forbidden', 'Not allowed.', array( 'status' => 403 ) );
		}
		$t = self::t( 'cadjust' );
		$a = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A );
		if ( ! $a || 'open' !== $a['status'] ) {
			return new WP_Error( 'axh_not_found', 'Nothing open with that id.', array( 'status' => 404 ) );
		}
		$status = 'hold' === $a['kind'] ? 'released' : ( 'waive' === $action ? 'waived' : 'recovered' );
		$wpdb->update( $t, array( 'status' => $status, 'resolved_at' => self::now(), 'resolved_by' => get_current_user_id(), 'reason' => mb_substr( $a['reason'] . ( $note ? ' · ' . self::txt( $note, 200 ) : '' ), 0, 600 ) ), array( 'id' => (int) $id ) );
		if ( $a['lead_id'] ) {
			AXH_DB::log( $a['lead_id'], 'payment', 'hold' === $a['kind'] ? 'Commission hold released' : 'Commission clawback ' . $status . ' (' . self::fmt( $a['amount'] ) . ')', $note );
		}
		return true;
	}

	public static function add_clawback( $in ) {
		global $wpdb;
		$in     = (array) $in;
		$amount = self::money( $in['amount'] ?? 0 );
		$rep    = (int) ( $in['rep_id'] ?? 0 );
		$lead   = (int) ( $in['lead_id'] ?? 0 );
		if ( $lead ) {
			$l = AXH_DB::get_lead( $lead );
			if ( $l ) {
				$rep = (int) $l['rep_id'];
			}
		}
		if ( ! axh_is_manager() || ! $rep || $amount <= 0 ) {
			return new WP_Error( 'axh_bad_clawback', 'Choose a rep and an amount.', array( 'status' => 400 ) );
		}
		$wpdb->insert( self::t( 'cadjust' ), array( 'kind' => 'clawback', 'rep_id' => $rep, 'lead_id' => $lead, 'leg' => '', 'amount' => $amount, 'reason' => self::txt( $in['reason'] ?? 'Commission recovery (agreement 3.6)', 400 ), 'status' => 'open', 'created_by' => get_current_user_id(), 'created_at' => self::now() ) );
		if ( $lead ) {
			AXH_DB::log( $lead, 'payment', 'Commission clawback opened: ' . self::fmt( $amount ), self::txt( $in['reason'] ?? '', 400 ) );
		}
		return true;
	}

	/** Commission halves already selected in a draft batch. */
	protected static function legs_in_draft_batches() {
		global $wpdb;
		$t   = self::t( 'cbatch' );
		$out = array();
		foreach ( (array) $wpdb->get_results( "SELECT id, items FROM {$t} WHERE status = 'draft'", ARRAY_A ) as $b ) { // phpcs:ignore
			foreach ( (array) json_decode( (string) $b['items'], true ) as $rep ) {
				foreach ( (array) ( $rep['legs'] ?? array() ) as $l ) {
					$out[ $l['lead_id'] . ':' . $l['leg'] ] = (int) $b['id'];
				}
			}
		}
		return $out;
	}

	/**
	 * Every deal's commission tied to its invoices and payments, per rep:
	 * earned, waiting on the client, ready, held, paid, clawbacks.
	 */
	public static function commissions( $year = 0 ) {
		global $wpdb;
		$year   = $year ? (int) $year : (int) wp_date( 'Y' );
		$e      = AXH_DB::earnings( true );
		$holds  = array();
		foreach ( self::adjust_rows( array( 'kind' => 'hold', 'status' => 'open' ) ) as $h ) {
			$holds[ $h['lead_id'] ][] = $h;
		}
		$inbatch = self::legs_in_draft_batches();
		$dt      = self::t( 'bdocs' );
		$invs    = array();
		foreach ( (array) $wpdb->get_results( "SELECT id, number, lead_id, milestone, status, total, amount_paid, balance, leg_applied, due_date FROM {$dt} WHERE type = 'invoice' AND lead_id IS NOT NULL AND status <> 'void'", ARRAY_A ) as $iv ) { // phpcs:ignore
			$iv['display_status']        = self::display_status( $iv + array( 'type' => 'invoice' ) );
			$invs[ (int) $iv['lead_id'] ][] = $iv;
		}
		$reps  = array();
		$deals = array();
		foreach ( $e['deals'] as $d ) {
			$rid = (int) $d['rep_id'];
			if ( ! isset( $reps[ $rid ] ) ) {
				$reps[ $rid ] = array( 'rep_id' => $rid, 'name' => self::user_name( $rid ), 'earned' => 0.0, 'waiting' => 0.0, 'ready' => 0.0, 'held' => 0.0, 'paid' => 0.0, 'paid_ytd' => 0.0, 'clawback' => 0.0, 'deals' => 0 );
			}
			$reps[ $rid ]['earned'] += $d['commission'];
			$reps[ $rid ]['deals']++;
			$dh = $holds[ $d['lead_id'] ] ?? array();
			foreach ( $d['legs'] as &$leg ) {
				$h = null;
				foreach ( $dh as $x ) {
					if ( 'both' === $x['leg'] || $x['leg'] === $leg['key'] ) {
						$h = $x;
					}
				}
				$leg['hold']     = $h ? array( 'id' => $h['id'], 'reason' => $h['reason'] ) : null;
				$leg['in_batch'] = $inbatch[ $d['lead_id'] . ':' . $leg['key'] ] ?? 0;
				$leg['invoice']  = null;
				foreach ( $invs[ $d['lead_id'] ] ?? array() as $iv ) {
					if ( $iv['milestone'] === $leg['key'] || 'full' === $iv['milestone'] ) {
						$leg['invoice'] = array( 'id' => (int) $iv['id'], 'number' => $iv['number'], 'status' => $iv['display_status'], 'total' => (float) $iv['total'], 'balance' => (float) $iv['balance'] );
					}
				}
				if ( 'paid' === $leg['state'] ) {
					$reps[ $rid ]['paid'] += $leg['amount'];
					if ( $leg['paid_at'] && (int) substr( $leg['paid_at'], 0, 4 ) === $year ) {
						$reps[ $rid ]['paid_ytd'] += $leg['amount'];
					}
				} elseif ( 'waiting' === $leg['state'] ) {
					$reps[ $rid ]['waiting'] += $leg['amount'];
				} elseif ( in_array( $leg['state'], array( 'due', 'overdue' ), true ) ) {
					if ( $h ) {
						$reps[ $rid ]['held'] += $leg['amount'];
					} else {
						$reps[ $rid ]['ready'] += $leg['amount'];
					}
				}
			}
			unset( $leg );
			$d['invoices'] = array_map( function ( $iv ) {
				return array( 'id' => (int) $iv['id'], 'number' => $iv['number'], 'milestone' => $iv['milestone'], 'status' => $iv['display_status'], 'total' => (float) $iv['total'], 'paid' => (float) $iv['amount_paid'], 'balance' => (float) $iv['balance'] );
			}, $invs[ $d['lead_id'] ] ?? array() );
			$deals[] = $d;
		}
		$claw = self::adjust_rows( array( 'kind' => 'clawback' ) );
		foreach ( $claw as $c ) {
			if ( 'open' === $c['status'] ) {
				if ( ! isset( $reps[ $c['rep_id'] ] ) ) {
					$reps[ $c['rep_id'] ] = array( 'rep_id' => $c['rep_id'], 'name' => $c['rep_name'], 'earned' => 0.0, 'waiting' => 0.0, 'ready' => 0.0, 'held' => 0.0, 'paid' => 0.0, 'paid_ytd' => 0.0, 'clawback' => 0.0, 'deals' => 0 );
				}
				$reps[ $c['rep_id'] ]['clawback'] += $c['amount'];
			}
		}
		foreach ( $reps as &$r ) {
			foreach ( $r as $k => $v ) {
				if ( is_float( $v ) ) {
					$r[ $k ] = round( $v, 2 );
				}
			}
			$r['net_ready'] = round( max( 0, $r['ready'] - $r['clawback'] ), 2 );
			$r['payee']     = AXH_Onboard::payee_card( $r['rep_id'] );
			$r['readiness'] = axh_is_manager( $r['rep_id'] ) ? array( 'ready' => true, 'missing' => array() ) : AXH_Onboard::readiness( $r['rep_id'] );
		}
		unset( $r );
		$overpaid = array();
		foreach ( $deals as $d ) {
			if ( $d['overpaid'] > 0 ) {
				$has = false;
				foreach ( $claw as $c ) {
					if ( (int) $c['lead_id'] === (int) $d['lead_id'] && in_array( $c['status'], array( 'open', 'applied', 'recovered', 'waived' ), true ) && false !== strpos( $c['reason'], 'Overpaid' ) ) {
						$has = true;
					}
				}
				if ( ! $has ) {
					$overpaid[] = array( 'lead_id' => $d['lead_id'], 'company' => $d['company'], 'rep_id' => $d['rep_id'], 'rep_name' => $d['rep_name'], 'amount' => $d['overpaid'], 'reversed' => $d['reversed'] );
				}
			}
		}
		return array(
			'year'      => $year,
			'reps'      => array_values( $reps ),
			'deals'     => $deals,
			'clawbacks' => $claw,
			'holds'     => self::adjust_rows( array( 'kind' => 'hold' ) ),
			'batches'   => self::batches(),
			'overpaid'  => $overpaid,
			'totals'    => $e['totals'],
			'methods'   => array( 'Check', 'ACH / direct deposit', 'Zelle', 'PayPal', 'Wire', 'Other' ),
		);
	}

	public static function batches() {
		global $wpdb;
		$t    = self::t( 'cbatch' );
		$rows = $wpdb->get_results( "SELECT * FROM {$t} ORDER BY id DESC LIMIT 200", ARRAY_A ); // phpcs:ignore
		$out  = array();
		foreach ( $rows as $b ) {
			$out[] = self::shape_batch( $b, false );
		}
		return $out;
	}

	protected static function shape_batch( $b, $full = true ) {
		$items = json_decode( (string) $b['items'], true );
		$items = is_array( $items ) ? $items : array();
		$o     = array(
			'id'         => (int) $b['id'],
			'label'      => 'Batch ' . (int) $b['id'],
			'status'     => $b['status'],
			'pay_date'   => $b['pay_date'],
			'method'     => $b['method'],
			'note'       => (string) $b['note'],
			'gross'      => (float) $b['gross'],
			'deductions' => (float) $b['deductions'],
			'total'      => (float) $b['total'],
			'reps'       => count( $items ),
			'legs'       => array_sum( array_map( function ( $r ) { return count( $r['legs'] ?? array() ); }, $items ) ),
			'created_by' => self::user_name( $b['created_by'] ),
			'created_at' => $b['created_at'],
			'paid_at'    => $b['paid_at'],
			'voided_at'  => $b['voided_at'],
		);
		if ( $full ) {
			$o['items'] = $items;
		}
		return $o;
	}

	public static function batch( $id ) {
		global $wpdb;
		$t = self::t( 'cbatch' );
		$b = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A );
		return $b ? self::shape_batch( $b ) : null;
	}

	/**
	 * A payout batch: chosen commission halves (ready to pay, not on hold),
	 * grouped one check per rep, with open clawbacks deducted.
	 */
	public static function create_batch( $in ) {
		global $wpdb;
		if ( ! axh_is_manager() ) {
			return new WP_Error( 'axh_forbidden', 'Not allowed.', array( 'status' => 403 ) );
		}
		$in   = (array) $in;
		$want = array();
		foreach ( (array) ( $in['legs'] ?? array() ) as $l ) {
			$l = (array) $l;
			if ( ! empty( $l['lead_id'] ) && in_array( $l['leg'] ?? '', array( 'deposit', 'final' ), true ) ) {
				$want[ (int) $l['lead_id'] . ':' . $l['leg'] ] = true;
			}
		}
		if ( ! $want ) {
			return new WP_Error( 'axh_empty', 'Choose at least one commission to pay.', array( 'status' => 400 ) );
		}
		$cm    = self::commissions();
		$byrep = array();
		foreach ( $cm['deals'] as $d ) {
			foreach ( $d['legs'] as $leg ) {
				$key = $d['lead_id'] . ':' . $leg['key'];
				if ( ! isset( $want[ $key ] ) ) {
					continue;
				}
				if ( ! in_array( $leg['state'], array( 'due', 'overdue' ), true ) || $leg['amount'] <= 0 ) {
					return new WP_Error( 'axh_not_ready', $d['company'] . ' (' . $leg['key'] . ' half) is not ready to pay.', array( 'status' => 409 ) );
				}
				if ( $leg['hold'] ) {
					return new WP_Error( 'axh_on_hold', $d['company'] . ' (' . $leg['key'] . ' half) is on hold: ' . $leg['hold']['reason'], array( 'status' => 409 ) );
				}
				if ( $leg['in_batch'] ) {
					return new WP_Error( 'axh_in_batch', $d['company'] . ' (' . $leg['key'] . ' half) is already in Batch ' . $leg['in_batch'] . '.', array( 'status' => 409 ) );
				}
				$rid = (int) $d['rep_id'];
				if ( ! isset( $byrep[ $rid ] ) ) {
					$byrep[ $rid ] = array( 'rep_id' => $rid, 'name' => self::user_name( $rid ), 'legs' => array(), 'deductions' => array(), 'gross' => 0.0 );
				}
				$byrep[ $rid ]['legs'][] = array(
					'lead_id' => (int) $d['lead_id'],
					'leg'     => $leg['key'],
					'company' => $d['company'],
					'value'   => $d['value'],
					'rate'    => $d['rate'],
					'amount'  => $leg['amount'],
					'trigger' => $leg['trigger'],
					'invoice' => $leg['invoice'] ? $leg['invoice']['number'] : '',
				);
				$byrep[ $rid ]['gross'] += $leg['amount'];
				unset( $want[ $key ] );
			}
		}
		if ( $want ) {
			return new WP_Error( 'axh_not_ready', 'Some of the chosen commissions are not ready to pay.', array( 'status' => 409 ) );
		}
		$checks  = (array) ( $in['checks'] ?? array() );
		$missing = array();
		$open    = self::adjust_rows( array( 'kind' => 'clawback', 'status' => 'open' ) );
		$g = 0.0;
		$dd = 0.0;
		foreach ( $byrep as $rid => &$rep ) {
			$rd = axh_is_manager( $rid ) ? array( 'ready' => true, 'missing' => array() ) : AXH_Onboard::readiness( $rid );
			if ( ! $rd['ready'] ) {
				$missing[] = $rep['name'] . ' (' . implode( ', ', $rd['missing'] ) . ')';
			}
			$left = $rep['gross'];
			foreach ( $open as $c ) {
				if ( (int) $c['rep_id'] !== (int) $rid || $left <= 0 ) {
					continue;
				}
				$take = round( min( $left, $c['amount'] ), 2 );
				$rep['deductions'][] = array( 'adjust_id' => $c['id'], 'amount' => $take, 'reason' => $c['reason'], 'company' => (string) $c['company'] );
				$left -= $take;
			}
			$rep['gross']     = round( $rep['gross'], 2 );
			$rep['deduct']    = round( $rep['gross'] - $left, 2 );
			$rep['net']       = round( $left, 2 );
			$rep['check_no']  = self::txt( $checks[ $rid ] ?? '', 40 );
			$rep['payee']     = AXH_Onboard::payee_card( $rid );
			$g  += $rep['gross'];
			$dd += $rep['deduct'];
		}
		unset( $rep );
		if ( $missing && empty( $in['override'] ) ) {
			return new WP_Error( 'axh_onboarding_incomplete', 'Onboarding is missing for: ' . implode( '; ', $missing ) . '. Tick “Pay anyway” only if you have it some other way.', array( 'status' => 409, 'missing' => $missing ) );
		}
		$wpdb->insert(
			self::t( 'cbatch' ),
			array(
				'status'     => 'draft',
				'pay_date'   => self::date_or_null( $in['pay_date'] ?? '' ) ?: self::today(),
				'method'     => self::txt( $in['method'] ?? 'Check', 64 ),
				'note'       => self::txt( $in['note'] ?? '', 400 ),
				'items'      => wp_json_encode( array_values( $byrep ) ),
				'gross'      => round( $g, 2 ),
				'deductions' => round( $dd, 2 ),
				'total'      => round( $g - $dd, 2 ),
				'created_by' => get_current_user_id(),
				'created_at' => self::now(),
			)
		);
		$id = (int) $wpdb->insert_id;
		if ( $missing ) {
			foreach ( array_keys( $byrep ) as $rid ) {
				AXH_Onboard::audit( $rid, 'payout_override', 'Payout batch ' . $id . ' created with onboarding incomplete' );
			}
		}
		self::event( 0, 0, 'batch', 'Payout batch ' . $id . ' created: ' . self::fmt( $g - $dd ) . ' to ' . count( $byrep ) . ' rep(s)' );
		return self::batch( $id );
	}

	/** Mark a draft batch paid: one payout record per commission half, clawbacks applied. */
	public static function pay_batch( $id, $in = array() ) {
		global $wpdb;
		$b = self::batch( $id );
		if ( ! $b || ! axh_is_manager() ) {
			return new WP_Error( 'axh_not_found', 'Batch not found.', array( 'status' => 404 ) );
		}
		if ( 'draft' !== $b['status'] ) {
			return new WP_Error( 'axh_bad_status', 'This batch is already ' . $b['status'] . '.', array( 'status' => 409 ) );
		}
		$in     = (array) $in;
		$date   = self::date_or_null( $in['pay_date'] ?? '' ) ?: $b['pay_date'];
		$checks = (array) ( $in['checks'] ?? array() );
		$items  = $b['items'];
		$errors = array();
		$at     = self::t( 'cadjust' );
		foreach ( $items as &$rep ) {
			if ( isset( $checks[ $rep['rep_id'] ] ) ) {
				$rep['check_no'] = self::txt( $checks[ $rep['rep_id'] ], 40 );
			}
			$ref = trim( ( $rep['check_no'] ? 'Check #' . $rep['check_no'] . ' · ' : '' ) . 'Batch ' . $id );
			foreach ( $rep['legs'] as $l ) {
				$p = AXH_DB::record_payout( $l['lead_id'], $l['leg'], array( 'amount' => $l['amount'], 'paid_at' => $date, 'method' => $b['method'], 'reference' => $ref, 'note' => 'Payout batch ' . $id ) );
				if ( is_wp_error( $p ) ) {
					$errors[] = $l['company'] . ' (' . $l['leg'] . '): ' . $p->get_error_message();
				} else {
					$wpdb->update( AXH_DB::table( 'payouts' ), array( 'batch_id' => (int) $id ), array( 'id' => (int) $p['id'] ) );
				}
			}
			foreach ( $rep['deductions'] as $dd ) {
				$c = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$at} WHERE id = %d", (int) $dd['adjust_id'] ), ARRAY_A );
				if ( ! $c || 'open' !== $c['status'] ) {
					continue;
				}
				if ( (float) $c['amount'] - (float) $dd['amount'] > 0.004 ) {
					$wpdb->update( $at, array( 'amount' => round( (float) $c['amount'] - (float) $dd['amount'], 2 ) ), array( 'id' => (int) $c['id'] ) );
					$wpdb->insert( $at, array( 'kind' => 'clawback', 'rep_id' => (int) $c['rep_id'], 'lead_id' => (int) $c['lead_id'], 'leg' => $c['leg'], 'doc_id' => (int) $c['doc_id'], 'pay_id' => (int) $c['pay_id'], 'amount' => (float) $dd['amount'], 'reason' => $c['reason'] . ' (part)', 'status' => 'applied', 'batch_id' => (int) $id, 'created_by' => get_current_user_id(), 'created_at' => self::now(), 'resolved_at' => self::now(), 'resolved_by' => get_current_user_id() ) );
				} else {
					$wpdb->update( $at, array( 'status' => 'applied', 'batch_id' => (int) $id, 'resolved_at' => self::now(), 'resolved_by' => get_current_user_id() ), array( 'id' => (int) $c['id'] ) );
				}
			}
			AXH_Notify::add(
				(int) $rep['rep_id'],
				'payout_recorded',
				'Commission paid: ' . self::fmt( $rep['net'] ) . ' (Batch ' . $id . ')',
				'AdaptExec paid ' . count( $rep['legs'] ) . ' commission ' . ( 1 === count( $rep['legs'] ) ? 'half' : 'halves' ) . ( $rep['deduct'] > 0 ? ', less ' . self::fmt( $rep['deduct'] ) . ' in deductions' : '' ) . '. Your statement is in the Hub under Earnings → Statements.',
				'#/statements',
				null,
				array( 'rows' => array_filter( array( 'Amount' => self::fmt( $rep['net'] ), 'Paid on' => mysql2date( 'F j, Y', $date ), 'Method' => $b['method'], 'Check' => $rep['check_no'] ) ) )
			);
		}
		unset( $rep );
		$wpdb->update( self::t( 'cbatch' ), array( 'status' => 'paid', 'pay_date' => $date, 'paid_at' => self::now(), 'items' => wp_json_encode( $items ) ), array( 'id' => (int) $id ) );
		self::event( 0, 0, 'batch', 'Payout batch ' . $id . ' marked paid' . ( $errors ? ' with problems: ' . implode( '; ', $errors ) : '' ) );
		$out           = self::batch( $id );
		$out['errors'] = $errors;
		return $out;
	}

	public static function void_batch( $id, $reason = '' ) {
		global $wpdb;
		$b = self::batch( $id );
		if ( ! $b || ! axh_is_manager() ) {
			return new WP_Error( 'axh_not_found', 'Batch not found.', array( 'status' => 404 ) );
		}
		if ( 'void' === $b['status'] ) {
			return $b;
		}
		if ( 'paid' === $b['status'] ) {
			$pt = AXH_DB::table( 'payouts' );
			foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$pt} WHERE batch_id = %d AND voided_at IS NULL", (int) $id ) ) as $pid ) {
				AXH_DB::void_payout( (int) $pid, 'Payout batch ' . $id . ' voided' . ( $reason ? ': ' . $reason : '' ) );
			}
			$at = self::t( 'cadjust' );
			$wpdb->query( $wpdb->prepare( "UPDATE {$at} SET status = 'open', batch_id = 0, resolved_at = NULL WHERE batch_id = %d AND status = 'applied'", (int) $id ) ); // phpcs:ignore
		}
		$wpdb->update( self::t( 'cbatch' ), array( 'status' => 'void', 'voided_at' => self::now(), 'note' => trim( $b['note'] . ( $reason ? ' · Voided: ' . self::txt( $reason, 200 ) : ' · Voided' ) ) ), array( 'id' => (int) $id ) );
		self::event( 0, 0, 'batch', 'Payout batch ' . $id . ' voided' . ( $reason ? ': ' . $reason : '' ) );
		return self::batch( $id );
	}

	protected static function rep_identity( $rep_id ) {
		global $wpdb;
		$st  = AXH_DB::table( 'signatures' );
		$sig = $wpdb->get_row( $wpdb->prepare( "SELECT legal_name, business_name FROM {$st} WHERE rep_id = %d ORDER BY id DESC LIMIT 1", (int) $rep_id ), ARRAY_A );
		$u   = get_userdata( (int) $rep_id );
		$st2 = AXH_Onboard::status( (int) $rep_id );
		return array(
			'name'          => $u ? $u->display_name : '',
			'legal_name'    => $sig ? $sig['legal_name'] : ( $u ? $u->display_name : '' ),
			'business_name' => $sig ? (string) $sig['business_name'] : '',
			'payee'         => AXH_Onboard::payee_card( (int) $rep_id ),
			'w9'            => 'on_file' === $st2['steps']['w9']['state'],
		);
	}

	/** One rep's payout statement for a batch. Reps see only their own, once paid. */
	public static function statement( $batch_id, $rep_id ) {
		$b = self::batch( $batch_id );
		if ( ! $b || 'void' === $b['status'] ) {
			return null;
		}
		if ( ! axh_is_manager() && ( (int) $rep_id !== get_current_user_id() || 'paid' !== $b['status'] ) ) {
			return null;
		}
		$rep = null;
		foreach ( $b['items'] as $r ) {
			if ( (int) $r['rep_id'] === (int) $rep_id ) {
				$rep = $r;
			}
		}
		if ( ! $rep ) {
			return null;
		}
		$year = (int) substr( (string) $b['pay_date'], 0, 4 );
		$ytd  = self::year_totals( $year, (int) $rep_id );
		return array(
			'batch'    => array( 'id' => $b['id'], 'label' => $b['label'], 'status' => $b['status'], 'pay_date' => $b['pay_date'], 'method' => $b['method'] ),
			'business' => self::business(),
			'rep'      => self::rep_identity( $rep_id ),
			'lines'    => $rep['legs'],
			'deductions' => $rep['deductions'],
			'gross'    => $rep['gross'],
			'deduct'   => $rep['deduct'],
			'net'      => $rep['net'],
			'check_no' => $rep['check_no'],
			'ytd'      => $ytd,
			'year'     => $year,
		);
	}

	public static function statements( $rep_id = 0 ) {
		$rep_id = axh_is_manager() && $rep_id ? (int) $rep_id : get_current_user_id();
		$out    = array();
		foreach ( self::batches() as $b ) {
			if ( 'paid' !== $b['status'] && ! axh_is_manager() ) {
				continue;
			}
			if ( 'void' === $b['status'] ) {
				continue;
			}
			$full = self::batch( $b['id'] );
			foreach ( $full['items'] as $r ) {
				if ( (int) $r['rep_id'] === $rep_id ) {
					$out[] = array( 'batch_id' => $b['id'], 'label' => $b['label'], 'status' => $b['status'], 'pay_date' => $b['pay_date'], 'method' => $b['method'], 'net' => $r['net'], 'gross' => $r['gross'], 'deduct' => $r['deduct'], 'legs' => count( $r['legs'] ), 'check_no' => $r['check_no'] );
				}
			}
		}
		return $out;
	}

	/** Gross commission paid in a calendar year, deductions recovered, net cash. */
	public static function year_totals( $year, $rep_id = 0 ) {
		global $wpdb;
		$pt    = AXH_DB::table( 'payouts' );
		$where = $wpdb->prepare( 'voided_at IS NULL AND paid_at >= %s AND paid_at < %s', $year . '-01-01', ( $year + 1 ) . '-01-01' );
		if ( $rep_id ) {
			$where .= $wpdb->prepare( ' AND rep_id = %d', (int) $rep_id );
		}
		$out = array();
		foreach ( (array) $wpdb->get_results( "SELECT rep_id, SUM(amount) AS g, COUNT(*) AS n FROM {$pt} WHERE {$where} GROUP BY rep_id", ARRAY_A ) as $r ) { // phpcs:ignore
			$out[ (int) $r['rep_id'] ] = array( 'gross' => round( (float) $r['g'], 2 ), 'deduct' => 0.0, 'payments' => (int) $r['n'] );
		}
		$at = self::t( 'cadjust' );
		$bt = self::t( 'cbatch' );
		$w2 = $wpdb->prepare( "a.kind = 'clawback' AND a.status = 'applied' AND b.status = 'paid' AND b.pay_date >= %s AND b.pay_date < %s", $year . '-01-01', ( $year + 1 ) . '-01-01' );
		if ( $rep_id ) {
			$w2 .= $wpdb->prepare( ' AND a.rep_id = %d', (int) $rep_id );
		}
		foreach ( (array) $wpdb->get_results( "SELECT a.rep_id, SUM(a.amount) AS d FROM {$at} a JOIN {$bt} b ON b.id = a.batch_id WHERE {$w2} GROUP BY a.rep_id", ARRAY_A ) as $r ) { // phpcs:ignore
			$rid = (int) $r['rep_id'];
			if ( ! isset( $out[ $rid ] ) ) {
				$out[ $rid ] = array( 'gross' => 0.0, 'deduct' => 0.0, 'payments' => 0 );
			}
			$out[ $rid ]['deduct'] = round( (float) $r['d'], 2 );
		}
		foreach ( $out as &$o ) {
			$o['net'] = round( $o['gross'] - $o['deduct'], 2 );
		}
		unset( $o );
		if ( $rep_id ) {
			return $out[ (int) $rep_id ] ?? array( 'gross' => 0.0, 'deduct' => 0.0, 'payments' => 0, 'net' => 0.0 );
		}
		return $out;
	}

	/**
	 * Year-end 1099-NEC worksheet: what each rep was paid in the calendar
	 * year. The threshold is $600 for payments through 2025 and $2,000 from
	 * 2026 (One Big Beautiful Bill Act) — confirm with a tax preparer. TINs
	 * are never shown; the W-9 stays in onboarding.
	 */
	public static function summary_1099( $year ) {
		$year   = max( 2020, min( 2100, (int) $year ) );
		$limit  = $year >= 2026 ? 2000 : 600;
		$rows   = array();
		foreach ( self::year_totals( $year ) as $rid => $t ) {
			$id     = self::rep_identity( $rid );
			$rows[] = array_merge(
				$t,
				array(
					'rep_id'        => $rid,
					'name'          => $id['name'],
					'legal_name'    => $id['legal_name'],
					'business_name' => $id['business_name'],
					'payable_to'    => $id['payee'] ? $id['payee']['payable_to'] : '',
					'address'       => $id['payee'] ? implode( ', ', $id['payee']['lines'] ) : '',
					'w9'            => $id['w9'],
					'over'          => $t['net'] >= $limit,
				)
			);
		}
		usort( $rows, function ( $a, $b ) { return $b['net'] <=> $a['net']; } );
		return array( 'year' => $year, 'threshold' => $limit, 'rows' => $rows );
	}

	/* -------------------------------------------------------------- reports */

	/**
	 * The billing dashboard, QuickBooks-style. Billed = invoices issued (not
	 * drafts or void) by issue date; collected = money received less refunds
	 * by date received (customer credit moved between invoices is not new
	 * money).
	 */
	public static function report() {
		global $wpdb;
		$dt    = self::t( 'bdocs' );
		$pt    = self::t( 'bpay' );
		$ct    = self::t( 'customers' );
		$today = self::today();
		$y     = (int) wp_date( 'Y' );
		$m     = (int) wp_date( 'n' );
		$q0    = sprintf( '%04d-%02d-01', $y, ( (int) floor( ( $m - 1 ) / 3 ) ) * 3 + 1 );
		$m0    = wp_date( 'Y-m-01' );
		$y0    = $y . '-01-01';
		$names = array();
		foreach ( (array) $wpdb->get_results( "SELECT id, company, contact_name FROM {$ct}", ARRAY_A ) as $c ) { // phpcs:ignore
			$names[ (int) $c['id'] ] = $c['company'] ? $c['company'] : $c['contact_name'];
		}
		$invs = $wpdb->get_results( "SELECT * FROM {$dt} WHERE type = 'invoice' AND status NOT IN ('draft','void')", ARRAY_A ); // phpcs:ignore
		$k    = array( 'billed_month' => 0.0, 'billed_quarter' => 0.0, 'billed_year' => 0.0, 'collected_month' => 0.0, 'collected_quarter' => 0.0, 'collected_year' => 0.0, 'outstanding' => 0.0, 'overdue' => 0.0, 'open_count' => 0, 'overdue_count' => 0, 'credit' => 0.0 );
		$months = array();
		for ( $i = 11; $i >= 0; $i-- ) {
			$key            = wp_date( 'Y-m', strtotime( $m0 . ' -' . $i . ' months' ) );
			$months[ $key ] = array( 'month' => $key, 'label' => wp_date( 'M', strtotime( $key . '-01' ) ), 'billed' => 0.0, 'collected' => 0.0 );
		}
		$aging = array(
			'current' => array( 'label' => 'Current', 'amount' => 0.0, 'count' => 0, 'items' => array() ),
			'd30'     => array( 'label' => '1–30 days', 'amount' => 0.0, 'count' => 0, 'items' => array() ),
			'd60'     => array( 'label' => '31–60 days', 'amount' => 0.0, 'count' => 0, 'items' => array() ),
			'd90'     => array( 'label' => '61–90 days', 'amount' => 0.0, 'count' => 0, 'items' => array() ),
			'd90p'    => array( 'label' => '90+ days', 'amount' => 0.0, 'count' => 0, 'items' => array() ),
		);
		$cust = array();
		$cats = array();
		$reps = array();
		$days = array();
		foreach ( $invs as $r ) {
			$total = (float) $r['total'];
			$iss   = (string) $r['issue_date'];
			if ( $iss >= $m0 ) {
				$k['billed_month'] += $total;
			}
			if ( $iss >= $q0 ) {
				$k['billed_quarter'] += $total;
			}
			if ( $iss >= $y0 ) {
				$k['billed_year'] += $total;
			}
			$mk = substr( $iss, 0, 7 );
			if ( isset( $months[ $mk ] ) ) {
				$months[ $mk ]['billed'] += $total;
			}
			$bal = max( 0, (float) $r['balance'] );
			if ( $bal > 0.004 ) {
				$k['outstanding'] += $bal;
				$k['open_count']++;
				$late = $r['due_date'] ? (int) floor( ( strtotime( $today ) - strtotime( $r['due_date'] ) ) / DAY_IN_SECONDS ) : 0;
				$b    = $late <= 0 ? 'current' : ( $late <= 30 ? 'd30' : ( $late <= 60 ? 'd60' : ( $late <= 90 ? 'd90' : 'd90p' ) ) );
				if ( $late > 0 ) {
					$k['overdue'] += $bal;
					$k['overdue_count']++;
				}
				$aging[ $b ]['amount'] += $bal;
				$aging[ $b ]['count']++;
				$aging[ $b ]['items'][] = array( 'id' => (int) $r['id'], 'number' => $r['number'], 'customer' => $names[ (int) $r['customer_id'] ] ?? '', 'customer_id' => (int) $r['customer_id'], 'due_date' => $r['due_date'], 'days' => max( 0, $late ), 'balance' => round( $bal, 2 ) );
			}
			$cid = (int) $r['customer_id'];
			if ( ! isset( $cust[ $cid ] ) ) {
				$cust[ $cid ] = array( 'customer_id' => $cid, 'name' => $names[ $cid ] ?? 'Customer', 'billed' => 0.0, 'paid' => 0.0, 'balance' => 0.0 );
			}
			$cust[ $cid ]['billed']  += $total;
			$cust[ $cid ]['paid']    += (float) $r['amount_paid'];
			$cust[ $cid ]['balance'] += $bal;
			$doc = self::shape_doc( $r, 'admin' );
			$tot = self::calc( $doc['lines'], $doc );
			foreach ( $tot['categories'] as $cat => $v ) {
				$cats[ $cat ] = ( $cats[ $cat ] ?? 0 ) + $v;
			}
			$rid = (int) $r['rep_id'];
			if ( ! isset( $reps[ $rid ] ) ) {
				$reps[ $rid ] = array( 'rep_id' => $rid, 'name' => $rid ? self::user_name( $rid ) : 'House (no rep)', 'billed' => 0.0, 'collected' => 0.0, 'open' => 0.0, 'count' => 0 );
			}
			$reps[ $rid ]['billed'] += $total;
			$reps[ $rid ]['open']   += $bal;
			$reps[ $rid ]['count']++;
			if ( 'paid' === $r['status'] && $r['paid_at'] && $iss ) {
				$days[] = max( 0, ( strtotime( substr( $r['paid_at'], 0, 10 ) ) - strtotime( $iss ) ) / DAY_IN_SECONDS );
			}
		}
		$repof = array();
		foreach ( $invs as $r ) {
			$repof[ (int) $r['id'] ] = (int) $r['rep_id'];
		}
		foreach ( (array) $wpdb->get_results( "SELECT doc_id, kind, amount, credit, received_at FROM {$pt} WHERE voided_at IS NULL AND kind IN ('payment','refund')", ARRAY_A ) as $p ) { // phpcs:ignore
			$amt = 'payment' === $p['kind'] ? (float) $p['amount'] : -(float) $p['amount'];
			$d   = (string) $p['received_at'];
			if ( $d >= $m0 ) {
				$k['collected_month'] += $amt;
			}
			if ( $d >= $q0 ) {
				$k['collected_quarter'] += $amt;
			}
			if ( $d >= $y0 ) {
				$k['collected_year'] += $amt;
			}
			$mk = substr( $d, 0, 7 );
			if ( isset( $months[ $mk ] ) ) {
				$months[ $mk ]['collected'] += $amt;
			}
			$rid = $repof[ (int) $p['doc_id'] ] ?? null;
			if ( null !== $rid && isset( $reps[ $rid ] ) ) {
				$reps[ $rid ]['collected'] += $amt;
			}
		}
		$cm = self::customer_money();
		foreach ( $cm as $c ) {
			$k['credit'] += max( 0, $c['credit'] );
		}
		// Proposals & estimates: win rate and open pipeline.
		$won = 0;
		$lost = 0;
		$pipe = 0.0;
		$pipe_n = 0;
		foreach ( (array) $wpdb->get_results( "SELECT type, status, total, expiry_date, due_date, balance FROM {$dt} WHERE type IN ('proposal','estimate') AND status NOT IN ('draft','void')", ARRAY_A ) as $p ) { // phpcs:ignore
			$ds = self::display_status( $p );
			if ( 'accepted' === $ds ) {
				$won++;
			} elseif ( in_array( $ds, array( 'declined', 'expired' ), true ) ) {
				$lost++;
			} elseif ( in_array( $ds, array( 'sent', 'viewed' ), true ) ) {
				$pipe += (float) $p['total'];
				$pipe_n++;
			}
		}
		foreach ( $k as $key => $v ) {
			if ( is_float( $v ) ) {
				$k[ $key ] = round( $v, 2 );
			}
		}
		$k['avg_days_to_pay'] = $days ? round( array_sum( $days ) / count( $days ), 1 ) : null;
		$k['win_rate']        = ( $won + $lost ) ? round( 100 * $won / ( $won + $lost ), 1 ) : null;
		$k['won']             = $won;
		$k['lost']            = $lost;
		$k['pipeline']        = round( $pipe, 2 );
		$k['pipeline_count']  = $pipe_n;
		foreach ( $months as &$mo ) {
			$mo['billed']    = round( $mo['billed'], 2 );
			$mo['collected'] = round( $mo['collected'], 2 );
		}
		unset( $mo );
		foreach ( $aging as &$a ) {
			$a['amount'] = round( $a['amount'], 2 );
			usort( $a['items'], function ( $x, $y2 ) { return $y2['days'] <=> $x['days']; } );
		}
		unset( $a );
		$cust = array_values( $cust );
		usort( $cust, function ( $a, $b ) { return $b['billed'] <=> $a['billed']; } );
		foreach ( $cust as &$c ) {
			foreach ( array( 'billed', 'paid', 'balance' ) as $f ) {
				$c[ $f ] = round( $c[ $f ], 2 );
			}
		}
		unset( $c );
		arsort( $cats );
		$catrows = array();
		foreach ( $cats as $cat => $v ) {
			$catrows[] = array( 'category' => $cat, 'amount' => round( $v, 2 ) );
		}
		$reps = array_values( $reps );
		foreach ( $reps as &$rp ) {
			foreach ( array( 'billed', 'collected', 'open' ) as $f ) {
				$rp[ $f ] = round( $rp[ $f ], 2 );
			}
		}
		unset( $rp );
		usort( $reps, function ( $a, $b ) { return $b['billed'] <=> $a['billed']; } );
		return array(
			'kpis'       => $k,
			'monthly'    => array_values( $months ),
			'aging'      => $aging,
			'customers'  => array_slice( $cust, 0, 8 ),
			'categories' => $catrows,
			'reps'       => $reps,
			'recent'     => self::events( array( 'limit' => 14, 'types' => array( 'sent', 'viewed', 'accepted', 'declined', 'payment', 'refund', 'void', 'converted', 'reminder' ) ) ),
			'generated'  => self::now(),
		);
	}

	/* -------------------------------------------------------------- exports */

	protected static function csv( $rows ) {
		$out = fopen( 'php://temp', 'r+' );
		foreach ( $rows as $r ) {
			// Neutralise spreadsheet formulas in text cells.
			$r = array_map( function ( $c ) {
				$c = (string) $c;
				return ( '' !== $c && in_array( $c[0], array( '=', '+', '-', '@' ), true ) && ! is_numeric( $c ) ) ? "'" . $c : $c;
			}, $r );
			fputcsv( $out, $r );
		}
		rewind( $out );
		$s = stream_get_contents( $out );
		fclose( $out );
		return $s;
	}

	protected static function qbo_ref( $type, $id, $ext = '' ) {
		return $ext ? $ext : 'axh-' . $type . '-' . (int) $id;
	}

	/**
	 * Everything in QuickBooks Online's own entity shapes, so a later sync
	 * (or an import) is a mapping, not a rewrite. Rows already synced carry
	 * their QBO Id in external_id; the rest use a stable local key.
	 */
	public static function qbo_export() {
		$s     = self::settings();
		$cust  = array();
		$cid   = array();
		foreach ( self::customers( array( 'archived' => 1 ) ) as $c ) {
			$ref           = self::qbo_ref( 'cust', $c['id'], $c['external_id'] ?? '' );
			$cid[ $c['id'] ] = $ref;
			$names         = explode( ' ', (string) $c['contact_name'], 2 );
			$cust[]        = array_filter( array(
				'Id'                => $c['external_id'] ?? '' ? $c['external_id'] : null,
				'axh_id'            => $ref,
				'DisplayName'       => $c['name'] . ( $c['company'] && $c['contact_name'] ? '' : '' ),
				'CompanyName'       => $c['company'],
				'GivenName'         => $names[0] ?? '',
				'FamilyName'        => $names[1] ?? '',
				'PrimaryEmailAddr'  => $c['email'] ? array( 'Address' => $c['email'] ) : null,
				'PrimaryPhone'      => $c['phone'] ? array( 'FreeFormNumber' => $c['phone'] ) : null,
				'BillAddr'          => array_filter( array( 'Line1' => $c['addr1'], 'Line2' => $c['addr2'], 'City' => $c['city'], 'CountrySubDivisionCode' => $c['state'], 'PostalCode' => $c['zip'], 'Country' => $c['country'] ) ),
				'Notes'             => $c['notes'] ?? '',
				'Active'            => empty( $c['archived_at'] ),
				'SyncToken'         => ( $c['sync_token'] ?? '' ) ? $c['sync_token'] : null,
			), function ( $v ) { return null !== $v && '' !== $v && array() !== $v; } );
		}
		$items = array();
		$iref  = array();
		foreach ( self::items( true ) as $i ) {
			$ref            = self::qbo_ref( 'item', $i['id'], $i['external_id'] );
			$iref[ $i['id'] ] = array( 'value' => $ref, 'name' => $i['qbo_item'] ? $i['qbo_item'] : $i['name'] );
			$items[]        = array_filter( array(
				'Id'               => $i['external_id'] ? $i['external_id'] : null,
				'axh_id'           => $ref,
				'Name'             => $i['qbo_item'] ? $i['qbo_item'] : $i['name'],
				'Description'      => $i['description'],
				'Type'             => 'Service',
				'UnitPrice'        => $i['rate'],
				'Taxable'          => $i['taxable'],
				'Active'           => $i['active'],
				'IncomeAccountRef' => array( 'name' => 'Services' ),
				'axh_category'     => $i['category'],
				'axh_unit'         => $i['unit'],
			), function ( $v ) { return null !== $v && '' !== $v; } );
		}
		$docs = array( 'Estimate' => array(), 'Invoice' => array() );
		$dref = array();
		$all = self::docs( array( 'limit' => 2000 ) );
		usort( $all, function ( $a, $b ) { return $a['id'] <=> $b['id']; } ); // Estimates before the invoices made from them.
		foreach ( $all as $row ) {
			if ( 'draft' === $row['status'] ) {
				continue;
			}
			$full  = self::shape_doc( self::doc_row( $row['id'], false ), 'admin' );
			$acc   = 'accepted' === $full['status'] ? ( $full['content']['accepted'] ?? array() ) : array();
			$lines = self::lines_for( $full, $acc['option'] ?? null, $acc['addons'] ?? null );
			$ql    = array();
			foreach ( $lines as $l ) {
				list( $amt, $dsc, $net ) = self::line_net( $l );
				$ql[] = array(
					'DetailType'          => 'SalesItemLineDetail',
					'Amount'              => $net,
					'Description'         => trim( $l['name'] . ( $l['desc'] ? ' — ' . $l['desc'] : '' ) ),
					'SalesItemLineDetail' => array_filter( array(
						'ItemRef'      => ! empty( $l['item_id'] ) && isset( $iref[ $l['item_id'] ] ) ? $iref[ $l['item_id'] ] : array( 'name' => $l['name'] ),
						'Qty'          => $l['qty'],
						'UnitPrice'    => $dsc > 0 && $l['qty'] ? round( $net / $l['qty'], 4 ) : $l['rate'],
						'TaxCodeRef'   => array( 'value' => ! empty( $l['taxable'] ) && $full['tax_on'] ? 'TAX' : 'NON' ),
					) ),
				);
			}
			if ( $full['discount_total'] > 0 ) {
				$ql[] = array( 'DetailType' => 'DiscountLineDetail', 'Amount' => $full['discount_total'], 'DiscountLineDetail' => array( 'PercentBased' => 'pct' === $full['discount_type'], 'DiscountPercent' => 'pct' === $full['discount_type'] ? $full['discount_value'] : null ) );
			}
			$key  = 'invoice' === $full['type'] ? 'Invoice' : 'Estimate';
			$ref  = self::qbo_ref( $full['type'], $full['id'], $full['external_id'] );
			$dref[ $full['id'] ] = $ref;
			$obj  = array_filter( array(
				'Id'           => $full['external_id'] ? $full['external_id'] : null,
				'axh_id'       => $ref,
				'axh_type'     => $full['type'],
				'DocNumber'    => $full['number'],
				'TxnDate'      => $full['issue_date'],
				'CustomerRef'  => array( 'value' => $cid[ $full['customer_id'] ] ?? '', 'name' => $row['customer'] ),
				'Line'         => $ql,
				'TotalAmt'     => $full['total'],
				'PrivateNote'  => trim( $full['title'] ),
				'CustomerMemo' => $full['notes'] ? array( 'value' => mb_substr( $full['notes'], 0, 1000 ) ) : null,
				'TxnTaxDetail' => $full['tax_on'] ? array( 'TotalTax' => $full['tax_total'] ) : null,
				'SyncToken'    => $full['sync_token'] ? $full['sync_token'] : null,
			), function ( $v ) { return null !== $v && '' !== $v; } );
			if ( 'Invoice' === $key ) {
				$obj['DueDate'] = $full['due_date'];
				$obj['Balance'] = $full['balance'];
				$obj['axh_milestone'] = $full['milestone'];
				if ( $full['parent_id'] && isset( $dref[ $full['parent_id'] ] ) ) {
					$obj['LinkedTxn'] = array( array( 'TxnId' => $dref[ $full['parent_id'] ], 'TxnType' => 'Estimate' ) );
				}
			} else {
				$obj['ExpirationDate'] = $full['expiry_date'];
				$obj['TxnStatus']      = array( 'accepted' => 'Accepted', 'declined' => 'Rejected', 'void' => 'Closed' )[ $full['status'] ] ?? 'Pending';
				if ( $full['accepted_at'] ) {
					$obj['AcceptedBy']   = $full['accepted_name'];
					$obj['AcceptedDate'] = substr( $full['accepted_at'], 0, 10 );
				}
			}
			$docs[ $key ][] = $obj;
		}
		$pays = array();
		foreach ( self::payments() as $p ) {
			if ( $p['voided_at'] || 'payment' !== $p['kind'] ) {
				continue;
			}
			$pays[] = array_filter( array(
				'Id'             => $p['external_id'] ? $p['external_id'] : null,
				'axh_id'         => self::qbo_ref( 'pay', $p['id'], $p['external_id'] ),
				'TxnDate'        => $p['received_at'],
				'TotalAmt'       => $p['amount'],
				'UnappliedAmt'   => $p['credit'],
				'CustomerRef'    => array( 'value' => $cid[ $p['customer_id'] ] ?? '', 'name' => $p['customer'] ),
				'PaymentRefNum'  => $p['reference'],
				'PaymentMethodRef' => array( 'name' => $p['method_label'] ),
				'PrivateNote'    => trim( $p['note'] . ( $p['cleared'] ? '' : ' (not cleared)' ) ),
				'Line'           => $p['applied'] > 0 ? array( array( 'Amount' => $p['applied'], 'LinkedTxn' => array( array( 'TxnId' => $dref[ $p['doc_id'] ] ?? '', 'TxnType' => 'Invoice' ) ) ) ) : array(),
			), function ( $v ) { return null !== $v && '' !== $v; } );
		}
		return array(
			'format'    => 'qbo-v3-shapes',
			'note'      => 'Entities in QuickBooks Online API shapes. Id/SyncToken are present only for rows already synced (external_id); axh_id is the Hub key to store as the mapping. Proposals are exported as Estimates.',
			'generated' => gmdate( 'c' ),
			'business'  => self::business()['name'],
			'Customer'  => $cust,
			'Item'      => $items,
			'Estimate'  => $docs['Estimate'],
			'Invoice'   => $docs['Invoice'],
			'Payment'   => $pays,
		);
	}

	public static function export( $kind, $format = 'csv', $year = 0 ) {
		$date = wp_date( 'Y-m-d' );
		if ( 'qbo' === $kind && 'json' === $format ) {
			return array( 'filename' => 'adaptexec-quickbooks-' . $date . '.json', 'mime' => 'application/json', 'content' => wp_json_encode( self::qbo_export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		}
		$rows = array();
		$name = $kind;
		switch ( $kind ) {
			case 'invoices':
			case 'estimates':
				$rows[] = array( 'Number', 'Type', 'Status', 'Customer', 'Title', 'Rep', 'Issue date', 'Due / expiry', 'Total', 'Paid', 'Balance', 'Milestone', 'Viewed', 'Sent', 'Accepted', 'Customer link' );
				foreach ( self::docs( array( 'limit' => 2000 ) ) as $d ) {
					if ( ( 'invoices' === $kind ) !== ( 'invoice' === $d['type'] ) ) {
						continue;
					}
					$rows[] = array( $d['number'], $d['type'], self::status_label( $d['display_status'] ), $d['customer'], $d['title'], $d['rep_name'], $d['issue_date'], 'invoice' === $d['type'] ? $d['due_date'] : $d['expiry_date'], number_format( $d['total'], 2, '.', '' ), number_format( $d['amount_paid'], 2, '.', '' ), number_format( $d['balance'], 2, '.', '' ), $d['milestone'], $d['view_count'], substr( (string) $d['sent_at'], 0, 10 ), substr( (string) $d['accepted_at'], 0, 10 ), $d['url'] );
				}
				break;
			case 'payments':
				$rows[] = array( 'Date received', 'Type', 'Customer', 'Invoice', 'Amount', 'Applied', 'To credit', 'Method', 'Reference', 'Cleared', 'Cleared on', 'Recorded by', 'Voided', 'Note' );
				foreach ( self::payments() as $p ) {
					$rows[] = array( $p['received_at'], $p['kind'], $p['customer'], $p['number'], number_format( $p['amount'], 2, '.', '' ), number_format( $p['applied'], 2, '.', '' ), number_format( $p['credit'], 2, '.', '' ), $p['method_label'], $p['reference'], $p['cleared'] ? 'yes' : 'no', $p['cleared_at'], $p['recorded_by'], substr( (string) $p['voided_at'], 0, 10 ), $p['note'] );
				}
				break;
			case 'customers':
				$rows[] = array( 'Company', 'Contact', 'Email', 'Phone', 'Address 1', 'Address 2', 'City', 'State', 'ZIP', 'Rep', 'Billed', 'Paid', 'Balance', 'Credit', 'Hub customer id', 'QuickBooks id' );
				foreach ( self::customers( array( 'archived' => 1 ) ) as $c ) {
					$rows[] = array( $c['company'], $c['contact_name'], $c['email'], $c['phone'], $c['addr1'], $c['addr2'], $c['city'], $c['state'], $c['zip'], $c['rep_name'], number_format( $c['money']['billed'], 2, '.', '' ), number_format( $c['money']['paid'], 2, '.', '' ), number_format( $c['money']['balance'], 2, '.', '' ), number_format( max( 0, $c['money']['credit'] ), 2, '.', '' ), 'axh-cust-' . $c['id'], $c['external_id'] ?? '' );
				}
				break;
			case 'aging':
				$rep    = self::report();
				$rows[] = array( 'Customer', 'Invoice', 'Due date', 'Days past due', 'Bucket', 'Balance' );
				foreach ( $rep['aging'] as $b ) {
					foreach ( $b['items'] as $it ) {
						$rows[] = array( $it['customer'], $it['number'], $it['due_date'], $it['days'], $b['label'], number_format( $it['balance'], 2, '.', '' ) );
					}
				}
				$rows[] = array();
				$rows[] = array( 'Bucket', '', '', '', 'Invoices', 'Total' );
				foreach ( $rep['aging'] as $b ) {
					$rows[] = array( $b['label'], '', '', '', $b['count'], number_format( $b['amount'], 2, '.', '' ) );
				}
				break;
			case 'qbo':
				// QuickBooks Online's "Import invoices" spreadsheet layout.
				$name   = 'quickbooks-invoices';
				$rows[] = array( 'InvoiceNo', 'Customer', 'InvoiceDate', 'DueDate', 'Terms', 'Memo', 'Item(Product/Service)', 'ItemDescription', 'ItemQuantity', 'ItemRate', 'ItemAmount', 'Taxable', 'TaxRate' );
				$tl     = self::terms_list();
				foreach ( self::docs( array( 'type' => 'invoice', 'limit' => 2000 ) ) as $d ) {
					if ( in_array( $d['status'], array( 'draft', 'void' ), true ) ) {
						continue;
					}
					$full = self::shape_doc( self::doc_row( $d['id'], false ), 'admin' );
					foreach ( $full['lines'] as $l ) {
						list( $amt, $dsc, $net ) = self::line_net( $l );
						$rows[] = array( $full['number'], $d['customer'], wp_date( 'm/d/Y', strtotime( $full['issue_date'] ) ), $full['due_date'] ? wp_date( 'm/d/Y', strtotime( $full['due_date'] ) ) : '', 'custom' === $full['terms'] ? 'Net ' . (int) $full['terms_days'] : ( $tl[ $full['terms'] ]['label'] ?? '' ), $full['title'], $l['name'], $l['desc'], $l['qty'], $l['qty'] ? round( $net / $l['qty'], 2 ) : $l['rate'], number_format( $net, 2, '.', '' ), ! empty( $l['taxable'] ) && $full['tax_on'] ? 'Y' : 'N', $full['tax_on'] ? $full['tax_rate'] . '%' : '' );
					}
				}
				break;
			case '1099':
				$y      = $year ? (int) $year : (int) wp_date( 'Y' );
				$sum    = self::summary_1099( $y );
				$name   = '1099-summary-' . $y;
				$rows[] = array( 'Year', 'Rep', 'Legal name', 'Business name', 'Checks payable to', 'Address', 'W-9 on file', 'Gross commission paid', 'Deductions recovered', 'Net paid', 'Payments', 'At or over $' . number_format( $sum['threshold'] ) );
				foreach ( $sum['rows'] as $r ) {
					$rows[] = array( $y, $r['name'], $r['legal_name'], $r['business_name'], $r['payable_to'], $r['address'], $r['w9'] ? 'yes' : 'no', number_format( $r['gross'], 2, '.', '' ), number_format( $r['deduct'], 2, '.', '' ), number_format( $r['net'], 2, '.', '' ), $r['payments'], $r['over'] ? 'yes' : 'no' );
				}
				break;
			default:
				return new WP_Error( 'axh_bad_export', 'Unknown export.', array( 'status' => 400 ) );
		}
		self::event( 0, 0, 'export', 'Exported ' . $name . ' (' . ( count( $rows ) - 1 ) . ' rows)' );
		return array( 'filename' => 'adaptexec-' . $name . '-' . $date . '.csv', 'mime' => 'text/csv', 'content' => self::csv( $rows ) );
	}

	/* ------------------------------------------------- proposal templates */

	protected static function tpl_why() {
		return "- **You own what we build.** Once it is paid in full, the code, design and content are yours.\n- **No required monthly plan.** You pay for the project. Ongoing help is available if you want it, never required.\n- **Real client work.** Websites and custom CRMs for The Miliare Group and Virtuity (CRM tools used by thousands of financial agents), Nova the Beam Guy, Diamondback Pros and Father’s Advocacy Network.\n- **10+ years, 347+ projects, 139+ customers.** Built in Scottsdale, Arizona and delivered nationwide.";
	}

	protected static function tpl_next() {
		return "1. Choose the option that fits, plus any add-ons you want.\n2. Accept online in a minute, or sign the printed copy and send it back.\n3. We send the deposit invoice and book a kickoff call.\n4. You see progress at every milestone and approve before launch.";
	}

	public static function default_templates() {
		$sched = self::default_schedule();
		$web   = array( 'name' => 'Starter website', 'desc' => 'Custom-designed, mobile-first site that is easy to edit, with forms that feed a CRM.', 'qty' => 1, 'rate' => 900, 'unit' => 'project', 'category' => 'Websites' );
		$crm   = array( 'name' => 'Custom CRM', 'desc' => 'Pipelines, client records, automations and reporting built around how you work. No per-user fees.', 'qty' => 1, 'rate' => 1500, 'unit' => 'project', 'category' => 'CRM & Apps' );
		// 1.7.1: Adam can study the workflow on site before a CRM or software build.
		$onsite = array( 'id' => 'onsite_study', 'name' => 'On-site workflow study (optional)', 'desc' => 'Adam travels to observe your operation for [X] days before designing the system, so it fits how the work really flows. Covers travel, lodging and time; priced for your project.', 'qty' => 1, 'rate' => 0, 'unit' => 'each', 'category' => 'CRM & Apps', 'on' => false );
		$app    = array( 'name' => 'Custom software / web app', 'desc' => 'A custom web app or client portal built around your process. No per-user fees.', 'qty' => 1, 'rate' => 2500, 'unit' => 'project', 'category' => 'CRM & Apps' );
		// 1.7.5: optional monthly marketing add-ons, month-to-month, off by default.
		$seo_mo = array( 'id' => 'seo_monthly', 'name' => 'Monthly SEO', 'desc' => 'Month-to-month: technical and on-page work, 1–2 SEO articles or page improvements, Google Business Profile posts, local citations and a monthly rank and traffic report. No one can guarantee rankings. Cancel anytime.', 'qty' => 1, 'rate' => 450, 'unit' => 'month', 'category' => 'SEO', 'on' => false );
		$gads   = array( 'id' => 'google_ads', 'name' => 'Google Ads management', 'desc' => 'Month-to-month: keyword research, ad copy, GA4 conversion tracking, negative keywords, bid and budget tuning, and a monthly report. Ad spend is billed by Google directly, not included. Cancel anytime.', 'qty' => 1, 'rate' => 350, 'unit' => 'month', 'category' => 'Branding & Marketing', 'on' => false );
		$host  = array( 'id' => 'care', 'name' => 'Hosting & care plan (optional)', 'desc' => 'Hosting, updates and backups. Cancel any time; never required to keep your site.', 'qty' => 1, 'rate' => 0, 'unit' => 'month', 'category' => 'Hosting/Maintenance', 'on' => false );
		$t = array(
			array(
				'id' => 'website', 'name' => 'Website', 'builtin' => true,
				'title' => 'New website',
				'content' => array(
					'cover'    => array( 'title' => 'A website built around your business', 'subtitle' => 'Proposal', 'intro' => 'Thank you for the conversation. Here is what we heard, what we will build, and what it costs.' ),
					'sections' => array(
						array( 'kind' => 'text', 'title' => 'What we heard', 'body' => "You need a site that looks as good as the work you do, loads fast on a phone, and turns visitors into calls and bookings.\n\n- Today: [what is not working]\n- Goal: [what success looks like]" ),
						array( 'kind' => 'scope', 'title' => 'Scope & deliverables', 'body' => "- Custom design, mobile-first (no templates)\n- Up to [5] pages: Home, Services, About, Reviews, Contact\n- Contact and quote forms that feed a CRM, so no lead slips through\n- SEO-ready structure, fast pages, Google Analytics\n- Training so you can edit it yourself" ),
						array( 'kind' => 'timeline', 'title' => 'Timeline', 'body' => '', 'items' => array( array( 'title' => 'Kickoff & style', 'when' => 'Week 1', 'detail' => 'Kickoff call, style picks, content checklist. Photos shared in a Google Drive or Dropbox folder, organized by service.' ), array( 'title' => 'Design', 'when' => 'Week 2', 'detail' => 'Homepage design for your review.' ), array( 'title' => 'Build', 'when' => 'Weeks 3–4', 'detail' => 'All pages built, forms connected.' ), array( 'title' => 'Launch', 'when' => 'Week 5', 'detail' => 'Final review, go live, training.' ) ) ),
						array( 'kind' => 'investment', 'title' => 'Investment', 'body' => 'Choose the option that fits. Add-ons are optional and priced separately.' ),
						array( 'kind' => 'included', 'title' => 'Always included', 'body' => "- You own the site and its content once paid in full\n- No required monthly plan\n- 30 days of post-launch fixes" ),
						array( 'kind' => 'why', 'title' => 'Why AdaptExec', 'body' => self::tpl_why() ),
						array( 'kind' => 'next', 'title' => 'Next steps', 'body' => self::tpl_next() ),
						array( 'kind' => 'terms', 'title' => 'Terms', 'body' => '' ),
					),
					'options'  => array(
						array( 'id' => 'essential', 'name' => 'Essential', 'tagline' => 'A sharp, fast site that brings in calls', 'lines' => array( $web ) ),
						array( 'id' => 'growth', 'name' => 'Growth', 'tagline' => 'More pages, a CRM for your leads', 'recommended' => true, 'lines' => array( array_merge( $web, array( 'name' => 'Custom website (up to 8 pages)', 'rate' => 1800 ) ), $crm ) ),
					),
					'addons'   => array( array( 'id' => 'seo', 'name' => 'Local SEO setup', 'desc' => 'Google Business Profile, citations and on-page work.', 'qty' => 1, 'rate' => 0, 'unit' => 'project', 'category' => 'SEO', 'on' => false ), $seo_mo, $gads, $host ),
					'schedule' => $sched,
				),
			),
			array(
				'id' => 'crm', 'name' => 'Custom CRM', 'builtin' => true,
				'title' => 'Custom CRM',
				'content' => array(
					'cover'    => array( 'title' => 'One system for how your business runs', 'subtitle' => 'Custom CRM proposal', 'intro' => 'Replace the spreadsheets and per-seat apps with one CRM built around your process.' ),
					'sections' => array(
						array( 'kind' => 'text', 'title' => 'Where you are today', 'body' => "- Leads and jobs tracked across [spreadsheets / apps]\n- Follow-ups that depend on memory\n- No single view of what is coming in and what it is worth" ),
						array( 'kind' => 'scope', 'title' => 'What we will build', 'body' => "- Lead and customer records with your fields\n- A pipeline that matches your stages\n- Automated reminders and follow-ups\n- Dashboards for the numbers you check every week\n- Import of your existing data" ),
						array( 'kind' => 'timeline', 'title' => 'Timeline', 'body' => '', 'items' => array( array( 'title' => 'Map the process', 'when' => 'Week 1', 'detail' => 'Workshop your stages, fields and roles.' ), array( 'title' => 'First working version', 'when' => 'Weeks 2–4', 'detail' => 'You test it with real data.' ), array( 'title' => 'Refine & import', 'when' => 'Weeks 5–6', 'detail' => 'Adjustments, data import, training.' ), array( 'title' => 'Launch', 'when' => 'Week 6', 'detail' => 'Go live with support.' ) ) ),
						array( 'kind' => 'investment', 'title' => 'Investment', 'body' => 'No per-user fees. You own it once paid in full.' ),
						array( 'kind' => 'why', 'title' => 'Why AdaptExec', 'body' => self::tpl_why() ),
						array( 'kind' => 'next', 'title' => 'Next steps', 'body' => self::tpl_next() ),
						array( 'kind' => 'terms', 'title' => 'Terms', 'body' => '' ),
					),
					'options'  => array( array( 'id' => 'crm', 'name' => 'Custom CRM', 'tagline' => 'Built around your process', 'recommended' => true, 'lines' => array( $crm ) ) ),
					'addons'   => array( array( 'id' => 'portal', 'name' => 'Client portal', 'desc' => 'A login for your customers to see status, documents and invoices.', 'qty' => 1, 'rate' => 0, 'unit' => 'project', 'category' => 'CRM & Apps', 'on' => false ), $onsite, $gads, $host ),
					'schedule' => $sched,
				),
			),
			array(
				'id' => 'website_crm', 'name' => 'Website + CRM', 'builtin' => true,
				'title' => 'Website + CRM',
				'content' => array(
					'cover'    => array( 'title' => 'Your website and the system behind it', 'subtitle' => 'Website + CRM proposal', 'intro' => 'A site that wins customers, connected to a CRM that makes sure every lead is followed up.' ),
					'sections' => array(
						array( 'kind' => 'text', 'title' => 'The goal', 'body' => 'Every visitor who fills in a form lands in one place, gets a fast reply, and is tracked until they buy.' ),
						array( 'kind' => 'scope', 'title' => 'Scope & deliverables', 'body' => "**Website**\n\n- Custom design, mobile-first\n- Service pages built for local search\n- Forms connected straight to the CRM\n\n**CRM**\n\n- Pipeline, customer records, reminders\n- Dashboard of leads, jobs and revenue" ),
						array( 'kind' => 'timeline', 'title' => 'Timeline', 'body' => '', 'items' => array( array( 'title' => 'Kickoff', 'when' => 'Week 1', 'detail' => 'Kickoff call and content checklist. Photos shared in a Google Drive or Dropbox folder, organized by service.' ), array( 'title' => 'Website design', 'when' => 'Weeks 2–3', 'detail' => '' ), array( 'title' => 'CRM build', 'when' => 'Weeks 3–6', 'detail' => '' ), array( 'title' => 'Launch & training', 'when' => 'Week 7', 'detail' => '' ) ) ),
						array( 'kind' => 'investment', 'title' => 'Investment', 'body' => '' ),
						array( 'kind' => 'why', 'title' => 'Why AdaptExec', 'body' => self::tpl_why() ),
						array( 'kind' => 'next', 'title' => 'Next steps', 'body' => self::tpl_next() ),
						array( 'kind' => 'terms', 'title' => 'Terms', 'body' => '' ),
					),
					'options'  => array( array( 'id' => 'bundle', 'name' => 'Website + CRM', 'tagline' => 'Everything connected from day one', 'recommended' => true, 'lines' => array( $web, $crm ) ) ),
					'addons'   => array( $onsite, $seo_mo, $gads, $host ),
					'schedule' => $sched,
				),
			),
			array(
				'id' => 'seo', 'name' => 'SEO', 'builtin' => true,
				'title' => 'Local SEO',
				'content' => array(
					'cover'    => array( 'title' => 'Get found by the customers near you', 'subtitle' => 'SEO proposal', 'intro' => 'A clear strategy, transparent work, and no long-term contract.' ),
					'sections' => array(
						array( 'kind' => 'text', 'title' => 'What we found', 'body' => "From our review of your site and listings:\n\n- [finding 1]\n- [finding 2]\n- [finding 3]" ),
						array( 'kind' => 'scope', 'title' => 'The work', 'body' => "- Technical fixes to the site\n- Google Business Profile, citations and reviews\n- Content written for customers first\n- Monthly report in plain English" ),
						array( 'kind' => 'investment', 'title' => 'Investment', 'body' => 'No long-term contract required. We do not guarantee rankings (no honest provider can); we commit to the work and to reporting it clearly. Most businesses see meaningful movement within 3–6 months.' ),
						array( 'kind' => 'why', 'title' => 'Why AdaptExec', 'body' => self::tpl_why() ),
						array( 'kind' => 'next', 'title' => 'Next steps', 'body' => self::tpl_next() ),
						array( 'kind' => 'terms', 'title' => 'Terms', 'body' => '' ),
					),
					'options'  => array( array( 'id' => 'setup', 'name' => 'SEO setup', 'tagline' => 'Fix the foundations', 'recommended' => true, 'lines' => array( array( 'name' => 'Local SEO setup', 'desc' => 'Technical fixes, Google Business Profile, citations, on-page.', 'qty' => 1, 'rate' => 0, 'unit' => 'project', 'category' => 'SEO' ) ) ) ),
					'addons'   => array( array_merge( $seo_mo, array( 'id' => 'monthly' ) ), $gads ),
					'schedule' => array( array( 'label' => 'Payment in full', 'pct' => 100, 'leg' => 'full', 'due' => 'Due when you accept' ) ),
				),
			),
			array(
				'id' => 'cvi', 'name' => 'CVI coaching', 'builtin' => true,
				'title' => 'Core Values Index™ coaching',
				'content' => array(
					'cover'    => array( 'title' => 'Know how your people are wired', 'subtitle' => 'Core Values Index™ coaching', 'intro' => 'A short assessment, then coaching with a Certified CVI™ Coach.' ),
					'sections' => array(
						array( 'kind' => 'text', 'title' => 'Why CVI', 'body' => 'The Core Values Index™ shows how each person is naturally wired to contribute, communicate and decide. Teams use it for a shared language around strengths and conflict.' ),
						array( 'kind' => 'scope', 'title' => 'What is included', 'body' => "- Online CVI™ assessment for each participant (about 7–10 minutes)\n- One-on-one results debriefs (virtual is fine)\n- Team workshop to put it to work\n- Delivered by a Certified CVI™ Coach through Hardwired Coaching" ),
						array( 'kind' => 'investment', 'title' => 'Investment', 'body' => '' ),
						array( 'kind' => 'next', 'title' => 'Next steps', 'body' => "1. Accept below.\n2. Each participant gets their assessment link.\n3. We schedule debriefs and the workshop." ),
						array( 'kind' => 'terms', 'title' => 'Terms', 'body' => '' ),
					),
					'options'  => array( array( 'id' => 'team', 'name' => 'Team package', 'tagline' => 'Assessments, debriefs and a workshop', 'recommended' => true, 'lines' => array( array( 'name' => 'Core Values Index™ assessment & debrief', 'desc' => 'Per participant.', 'qty' => 5, 'rate' => 0, 'unit' => 'each', 'category' => 'CVI Coaching' ) ) ) ),
					'addons'   => array( array( 'id' => 'onsite', 'name' => 'In-person workshop', 'desc' => 'On site, anywhere in the US.', 'qty' => 1, 'rate' => 0, 'unit' => 'each', 'category' => 'CVI Coaching', 'on' => false ) ),
					'schedule' => array( array( 'label' => 'Payment in full', 'pct' => 100, 'leg' => 'full', 'due' => 'Due when you accept' ) ),
				),
			),
			array(
				'id' => 'app', 'name' => 'Custom software', 'builtin' => true,
				'title' => 'Custom software',
				'content' => array(
					'cover'    => array( 'title' => 'Software built around how you work', 'subtitle' => 'Custom software proposal', 'intro' => 'A web app or portal designed around your process, owned by you once it is paid in full.' ),
					'sections' => array(
						array( 'kind' => 'text', 'title' => 'What we heard', 'body' => "- Today: [how the work gets done now]\n- The cost: [what it costs in time or money]\n- Goal: [what success looks like]" ),
						array( 'kind' => 'scope', 'title' => 'What we will build', 'body' => "- [Core feature 1]\n- [Core feature 2]\n- Roles and logins: [who sees what]\n- Integrations: [QuickBooks, Google, industry software]\n- Import of your existing data from [current system]" ),
						array( 'kind' => 'timeline', 'title' => 'Timeline', 'body' => '', 'items' => array( array( 'title' => 'Map the workflow', 'when' => 'Week 1', 'detail' => 'Confirm the screens, roles and rules from your questionnaire.' ), array( 'title' => 'First working version', 'when' => 'Weeks 2–5', 'detail' => 'You test it with real data.' ), array( 'title' => 'Refine & import', 'when' => 'Weeks 6–7', 'detail' => 'Adjustments, data import, training.' ), array( 'title' => 'Launch', 'when' => 'Week 8', 'detail' => 'Go live with support.' ) ) ),
						array( 'kind' => 'investment', 'title' => 'Investment', 'body' => 'Phased so you can start using it sooner. No per-user fees. You own it once paid in full.' ),
						array( 'kind' => 'why', 'title' => 'Why AdaptExec', 'body' => self::tpl_why() ),
						array( 'kind' => 'next', 'title' => 'Next steps', 'body' => self::tpl_next() ),
						array( 'kind' => 'terms', 'title' => 'Terms', 'body' => '' ),
					),
					'options'  => array( array( 'id' => 'app', 'name' => 'Custom software', 'tagline' => 'Built around your process', 'recommended' => true, 'lines' => array( $app ) ) ),
					'addons'   => array( $onsite, $host ),
					'schedule' => $sched,
				),
			),
		);
		foreach ( $t as &$x ) {
			$c = self::clean_content( $x['content'], 'proposal' );
			$x['content'] = is_wp_error( $c ) ? array() : $c;
			$x['type']    = 'proposal';
		}
		return $t;
	}

	public static function templates() {
		$saved = get_option( 'axh_bill_templates', array() );
		$saved = is_array( $saved ) ? $saved : array();
		$out   = array();
		foreach ( self::default_templates() as $d ) {
			$out[ $d['id'] ] = isset( $saved[ $d['id'] ] ) ? array_merge( $d, $saved[ $d['id'] ], array( 'builtin' => true, 'edited' => true ) ) : $d;
		}
		foreach ( $saved as $id => $s ) {
			if ( ! isset( $out[ $id ] ) && empty( $s['deleted'] ) ) {
				$out[ $id ] = $s + array( 'builtin' => false );
			}
			if ( ! empty( $s['deleted'] ) ) {
				unset( $out[ $id ] );
			}
		}
		return array_values( $out );
	}

	public static function save_template( $in ) {
		if ( ! axh_is_manager() ) {
			return new WP_Error( 'axh_forbidden', 'Not allowed.', array( 'status' => 403 ) );
		}
		$in    = (array) $in;
		$saved = get_option( 'axh_bill_templates', array() );
		$saved = is_array( $saved ) ? $saved : array();
		$id    = self::sid( $in['id'] ?? '' );
		if ( ! empty( $in['reset'] ) ) {
			unset( $saved[ $id ] );
			update_option( 'axh_bill_templates', $saved, false );
			return self::templates();
		}
		if ( ! empty( $in['delete'] ) ) {
			$builtin = in_array( $id, wp_list_pluck( self::default_templates(), 'id' ), true );
			if ( $builtin ) {
				$saved[ $id ] = array( 'deleted' => true );
			} else {
				unset( $saved[ $id ] );
			}
			update_option( 'axh_bill_templates', $saved, false );
			return self::templates();
		}
		$content = self::clean_content( (array) ( $in['content'] ?? array() ), 'proposal' );
		if ( is_wp_error( $content ) ) {
			return $content;
		}
		$saved[ $id ] = array(
			'id'      => $id,
			'name'    => self::txt( $in['name'] ?? 'Template', 80 ),
			'title'   => self::txt( $in['title'] ?? '', 191 ),
			'type'    => 'proposal',
			'content' => $content,
		);
		update_option( 'axh_bill_templates', $saved, false );
		self::event( 0, 0, 'template', 'Proposal template saved: ' . $saved[ $id ]['name'] );
		return self::templates();
	}

	/** A document built from unsaved editor changes, for the live preview. */
	public static function preview_doc( $row, $in ) {
		$in   = (array) $in;
		$type = $row ? $row['type'] : ( in_array( $in['type'] ?? '', self::TYPES, true ) ? $in['type'] : 'proposal' );
		$s    = self::settings();
		if ( ! $row ) {
			$row = array(
				'id' => 0, 'type' => $type, 'number' => $s['prefix'][ $type ] . $s['next'][ $type ], 'seq' => 0, 'title' => '', 'status' => 'draft',
				'customer_id' => 0, 'lead_id' => null, 'rep_id' => 0, 'parent_id' => null, 'milestone' => '', 'issue_date' => self::today(),
				'expiry_date' => gmdate( 'Y-m-d', strtotime( '+' . (int) $s['expiry_days'] . ' days' ) ), 'due_date' => self::due_from( self::today(), $s['due_terms'], 0 ),
				'terms' => $s['due_terms'], 'terms_days' => null, 'content' => '{}', 'line_items' => '[]', 'discount_type' => 'pct', 'discount_value' => 0,
				'tax_on' => $s['tax_on'], 'tax_rate' => $s['tax_rate'], 'tax_label' => $s['tax_label'], 'subtotal' => 0, 'discount_total' => 0, 'tax_total' => 0,
				'total' => 0, 'amount_paid' => 0, 'balance' => 0, 'notes' => $s['notes_text'], 'terms_text' => $s['terms_text'], 'internal_notes' => '',
				'token' => str_repeat( '0', 40 ), 'sent_at' => null, 'viewed_at' => null, 'last_viewed_at' => null, 'view_count' => 0, 'accepted_at' => null,
				'accepted_name' => '', 'accepted_ip' => '', 'accepted_ua' => '', 'accepted_hash' => '', 'accepted_method' => '', 'accepted_sig' => '',
				'declined_at' => null, 'decline_reason' => '', 'voided_at' => null, 'paid_at' => null, 'reminders_sent' => 0, 'leg_applied' => 0,
				'external_id' => '', 'sync_token' => '', 'synced_at' => null, 'created_at' => self::now(), 'updated_at' => self::now(),
			);
		}
		$doc = self::shape_doc( $row, 'public' );
		if ( isset( $in['content'] ) ) {
			$c = self::clean_content( (array) $in['content'], $type );
			if ( is_wp_error( $c ) ) {
				$c = self::clean_content( array_merge( (array) $in['content'], array( 'schedule' => array() ) ), $type );
			}
			if ( ! is_wp_error( $c ) ) {
				if ( isset( $doc['content']['accepted'] ) ) {
					$c['accepted'] = $doc['content']['accepted'];
				}
				$doc['content'] = $c;
			}
		}
		if ( isset( $in['lines'] ) ) {
			$doc['lines'] = self::clean_lines( $in['lines'] );
		}
		foreach ( array( 'title' => 191, 'tax_label' => 40 ) as $k => $len ) {
			if ( isset( $in[ $k ] ) ) {
				$doc[ $k ] = self::txt( $in[ $k ], $len );
			}
		}
		foreach ( array( 'notes' => 6000, 'terms_text' => 12000 ) as $k => $len ) {
			if ( isset( $in[ $k ] ) ) {
				$doc[ $k ] = self::longtxt( $in[ $k ], $len );
			}
		}
		if ( isset( $in['discount_type'] ) ) {
			$doc['discount_type'] = 'amt' === $in['discount_type'] ? 'amt' : 'pct';
		}
		if ( isset( $in['discount_value'] ) ) {
			$doc['discount_value'] = max( 0, (float) $in['discount_value'] );
		}
		if ( isset( $in['tax_on'] ) ) {
			$doc['tax_on'] = ! empty( $in['tax_on'] );
		}
		if ( isset( $in['tax_rate'] ) ) {
			$doc['tax_rate'] = max( 0, min( 30, (float) $in['tax_rate'] ) );
		}
		foreach ( array( 'issue_date', 'expiry_date', 'due_date' ) as $k ) {
			if ( ! empty( $in[ $k ] ) ) {
				$doc[ $k ] = self::date_or_null( $in[ $k ] );
			}
		}
		if ( isset( $in['customer_id'] ) ) {
			$cr = self::customer_row( (int) $in['customer_id'] );
			if ( $cr ) {
				$doc['customer_id'] = (int) $cr['id'];
			}
		}
		if ( isset( $in['milestone'] ) ) {
			$doc['milestone'] = in_array( $in['milestone'], array( 'deposit', 'final', 'full' ), true ) ? $in['milestone'] : '';
		}
		$acc = 'accepted' === $doc['status'] ? ( $doc['content']['accepted'] ?? array() ) : array();
		$tot = self::calc( self::lines_for( $doc, $acc['option'] ?? null, $acc['addons'] ?? null ), $doc );
		$doc['subtotal']       = $tot['subtotal'];
		$doc['discount_total'] = $tot['discount'];
		$doc['tax_total']      = $tot['tax'];
		$doc['total']          = $tot['total'];
		if ( 'invoice' === $type ) {
			$doc['balance'] = round( $tot['total'] - (float) $doc['amount_paid'], 2 );
		}
		return $doc;
	}
}

/**
 * Billing REST routes. Every private route checks the capability in its
 * permission callback (signed in with Hub access, or AdaptExec manager), and
 * every read is scoped again in AXH_Bill (a rep reaches only documents and
 * customers of their own clients — anything else is a 404, never a 403, so
 * ids can't be probed).
 */
class AXH_Bill_Rest {

	public static function routes() {
		$ns     = AXH_Rest::NS;
		$auth   = array( 'AXH_Rest', 'can_use' );
		$manage = array( 'AXH_Rest', 'can_manage' );
		$r      = function ( $path, $methods, $cb, $perm ) use ( $ns ) {
			register_rest_route( $ns, $path, array( 'methods' => $methods, 'callback' => $cb, 'permission_callback' => $perm ) );
		};
		$c = __CLASS__;

		$r( '/billing/boot', 'GET', array( $c, 'boot' ), $auth );
		$r( '/billing/dashboard', 'GET', array( $c, 'dashboard' ), $manage );
		$r( '/billing/docs', 'GET', array( $c, 'docs' ), $auth );
		$r( '/billing/docs', 'POST', array( $c, 'create_doc' ), $manage );
		$r( '/billing/docs/(?P<id>\d+)', 'GET', array( $c, 'get_doc' ), $auth );
		$r( '/billing/docs/(?P<id>\d+)', 'POST', array( $c, 'update_doc' ), $manage );
		$r( '/billing/docs/(?P<id>\d+)', 'DELETE', array( $c, 'delete_doc' ), $manage );
		$r( '/billing/docs/(?P<id>\d+)/send', 'POST', array( $c, 'send_doc' ), $auth );
		$r( '/billing/docs/(?P<id>\d+)/link', 'POST', array( $c, 'link' ), $auth );
		$r( '/billing/docs/(?P<id>\d+)/status', 'POST', array( $c, 'status' ), $manage );
		$r( '/billing/docs/(?P<id>\d+)/convert', 'POST', array( $c, 'convert' ), $manage );
		$r( '/billing/docs/(?P<id>\d+)/duplicate', 'POST', array( $c, 'duplicate' ), $manage );
		$r( '/billing/preview', 'POST', array( $c, 'preview' ), $manage );
		$r( '/billing/customers', 'GET', array( $c, 'customers' ), $auth );
		$r( '/billing/customers', 'POST', array( $c, 'create_customer' ), $manage );
		$r( '/billing/customers/(?P<id>\d+)', 'GET', array( $c, 'get_customer' ), $auth );
		$r( '/billing/customers/(?P<id>\d+)', 'POST', array( $c, 'update_customer' ), $manage );
		$r( '/billing/customers/from-lead/(?P<id>\d+)', 'POST', array( $c, 'from_lead' ), $manage );
		$r( '/billing/items', 'GET', array( $c, 'items' ), $manage );
		$r( '/billing/items', 'POST', array( $c, 'create_item' ), $manage );
		$r( '/billing/items/(?P<id>\d+)', 'POST', array( $c, 'update_item' ), $manage );
		$r( '/billing/payments', 'GET', array( $c, 'payments' ), $auth );
		$r( '/billing/payments', 'POST', array( $c, 'record_payment' ), $manage );
		$r( '/billing/payments/(?P<id>\d+)', 'POST', array( $c, 'payment_action' ), $manage );
		$r( '/billing/credit', 'POST', array( $c, 'apply_credit' ), $manage );
		$r( '/billing/settings', 'GET', array( $c, 'get_settings' ), $manage );
		$r( '/billing/settings', 'POST', array( $c, 'save_settings' ), $manage );
		$r( '/billing/templates', 'GET', array( $c, 'templates' ), $manage );
		$r( '/billing/templates', 'POST', array( $c, 'save_template' ), $manage );
		$r( '/billing/export', 'GET', array( $c, 'export' ), $manage );
		$r( '/billing/commissions', 'GET', array( $c, 'commissions' ), $manage );
		$r( '/billing/holds', 'POST', array( $c, 'add_hold' ), $manage );
		$r( '/billing/adjust/(?P<id>\d+)', 'POST', array( $c, 'resolve_adjust' ), $manage );
		$r( '/billing/clawbacks', 'POST', array( $c, 'add_clawback' ), $manage );
		$r( '/billing/batches', 'POST', array( $c, 'create_batch' ), $manage );
		$r( '/billing/batches/(?P<id>\d+)', 'GET', array( $c, 'get_batch' ), $manage );
		$r( '/billing/batches/(?P<id>\d+)/pay', 'POST', array( $c, 'pay_batch' ), $manage );
		$r( '/billing/batches/(?P<id>\d+)/void', 'POST', array( $c, 'void_batch' ), $manage );
		$r( '/billing/statements', 'GET', array( $c, 'statements' ), $auth );
		$r( '/billing/statements/(?P<batch>\d+)/(?P<rep>\d+)', 'GET', array( $c, 'statement' ), $auth );
		$r( '/billing/1099', 'GET', array( $c, 'summary_1099' ), $manage );
		$r( '/billing/reminders/run', 'POST', array( $c, 'run_reminders' ), $manage );

		// The customer's page. No login: the unguessable token is the credential.
		$r( '/public/bill/(?P<token>[a-f0-9]{32,64})/accept', 'POST', array( $c, 'public_accept' ), '__return_true' );
		$r( '/public/bill/(?P<token>[a-f0-9]{32,64})/decline', 'POST', array( $c, 'public_decline' ), '__return_true' );
		$r( '/public/bill/(?P<token>[a-f0-9]{32,64})/pay', 'POST', array( $c, 'public_pay' ), '__return_true' );
	}

	protected static function out( $v ) {
		return is_wp_error( $v ) ? $v : rest_ensure_response( $v );
	}

	protected static function body( $req ) {
		$b = $req->get_json_params();
		return is_array( $b ) ? $b : (array) $req->get_body_params();
	}

	protected static function nf() {
		return new WP_Error( 'axh_not_found', 'Not found.', array( 'status' => 404 ) );
	}

	public static function boot( $req ) {
		$mgr = axh_is_manager();
		$out = array(
			'manager'    => $mgr,
			'categories' => AXH_Bill::categories(),
			'units'      => AXH_Bill::units(),
			'methods'    => AXH_Bill::methods(),
			'terms'      => AXH_Bill::terms_list(),
			'kinds'      => AXH_Bill::section_kinds(),
			'business'   => AXH_Bill::business(),
			'gateways'   => array_keys( AXH_Bill::gateways() ),
		);
		if ( $mgr ) {
			$out['settings']  = AXH_Bill::settings();
			$out['items']     = AXH_Bill::items();
			$out['templates'] = AXH_Bill::templates();
			$out['schedule']  = AXH_Bill::default_schedule();
		}
		return rest_ensure_response( $out );
	}

	public static function dashboard( $req ) {
		return rest_ensure_response( AXH_Bill::report() );
	}

	public static function docs( $req ) {
		return rest_ensure_response( array(
			'docs' => AXH_Bill::docs( array(
				'type'        => sanitize_key( (string) $req->get_param( 'type' ) ),
				'status'      => sanitize_key( (string) $req->get_param( 'status' ) ),
				'q'           => sanitize_text_field( (string) $req->get_param( 'q' ) ),
				'customer_id' => (int) $req->get_param( 'customer_id' ),
				'lead_id'     => (int) $req->get_param( 'lead_id' ),
				'rep_id'      => (int) $req->get_param( 'rep_id' ),
			) ),
		) );
	}

	public static function create_doc( $req ) {
		$b = self::body( $req );
		if ( ! empty( $b['template'] ) ) {
			foreach ( AXH_Bill::templates() as $t ) {
				if ( $t['id'] === $b['template'] ) {
					$b['content'] = array_merge( $t['content'], (array) ( $b['content'] ?? array() ) );
					if ( empty( $b['title'] ) ) {
						$b['title'] = $t['title'];
					}
				}
			}
		}
		return self::out( AXH_Bill::save_doc( 0, $b ) );
	}

	public static function get_doc( $req ) {
		$d = AXH_Bill::doc_detail( (int) $req['id'] );
		return $d ? rest_ensure_response( $d ) : self::nf();
	}

	public static function update_doc( $req ) {
		return self::out( AXH_Bill::save_doc( (int) $req['id'], self::body( $req ) ) );
	}

	public static function delete_doc( $req ) {
		$r = AXH_Bill::delete_doc( (int) $req['id'] );
		return is_wp_error( $r ) ? $r : rest_ensure_response( array( 'deleted' => true ) );
	}

	public static function send_doc( $req ) {
		return self::out( AXH_Bill::send_doc( (int) $req['id'], self::body( $req ) ) );
	}

	public static function link( $req ) {
		$b = self::body( $req );
		return self::out( AXH_Bill::relink( (int) $req['id'], in_array( $b['action'] ?? '', array( 'copied', 'regenerate', 'revoke' ), true ) ? $b['action'] : 'copied' ) );
	}

	public static function status( $req ) {
		$b = self::body( $req );
		return self::out( AXH_Bill::set_status( (int) $req['id'], sanitize_key( $b['status'] ?? '' ), (string) ( $b['note'] ?? '' ) ) );
	}

	public static function convert( $req ) {
		$b = self::body( $req );
		return self::out( AXH_Bill::convert( (int) $req['id'], ! empty( $b['force'] ) ) );
	}

	public static function duplicate( $req ) {
		$b = self::body( $req );
		return self::out( AXH_Bill::duplicate( (int) $req['id'], sanitize_key( $b['type'] ?? '' ) ) );
	}

	/** Live preview while editing: the real customer page, from unsaved changes. */
	public static function preview( $req ) {
		$b   = self::body( $req );
		$id  = (int) ( $b['id'] ?? 0 );
		$row = $id ? AXH_Bill::doc_row( $id ) : null;
		if ( $id && ! $row ) {
			return self::nf();
		}
		$doc = AXH_Bill_View::draft_doc( $row, $b );
		if ( is_wp_error( $doc ) ) {
			return $doc;
		}
		return rest_ensure_response( array( 'html' => AXH_Bill_View::page( $doc, array( 'preview' => true ) ) ) );
	}

	public static function customers( $req ) {
		return rest_ensure_response( array( 'customers' => AXH_Bill::customers( array(
			'q'        => sanitize_text_field( (string) $req->get_param( 'q' ) ),
			'rep_id'   => (int) $req->get_param( 'rep_id' ),
			'archived' => (int) $req->get_param( 'archived' ),
		) ) ) );
	}

	public static function create_customer( $req ) {
		return self::out( AXH_Bill::save_customer( 0, self::body( $req ) ) );
	}

	public static function get_customer( $req ) {
		$d = AXH_Bill::customer_detail( (int) $req['id'] );
		return $d ? rest_ensure_response( $d ) : self::nf();
	}

	public static function update_customer( $req ) {
		return self::out( AXH_Bill::save_customer( (int) $req['id'], self::body( $req ) ) );
	}

	public static function from_lead( $req ) {
		return self::out( AXH_Bill::customer_from_lead( (int) $req['id'] ) );
	}

	public static function items( $req ) {
		return rest_ensure_response( array( 'items' => AXH_Bill::items( true ) ) );
	}

	public static function create_item( $req ) {
		return self::out( AXH_Bill::save_item( 0, self::body( $req ) ) );
	}

	public static function update_item( $req ) {
		return self::out( AXH_Bill::save_item( (int) $req['id'], self::body( $req ) ) );
	}

	public static function payments( $req ) {
		return rest_ensure_response( array( 'payments' => AXH_Bill::payments( array(
			'doc_id'      => (int) $req->get_param( 'doc_id' ),
			'customer_id' => (int) $req->get_param( 'customer_id' ),
			'method'      => (string) $req->get_param( 'method' ),
			'from'        => (string) $req->get_param( 'from' ),
			'to'          => (string) $req->get_param( 'to' ),
			'q'           => sanitize_text_field( (string) $req->get_param( 'q' ) ),
			'uncleared'   => (int) $req->get_param( 'uncleared' ),
		) ) ) );
	}

	public static function record_payment( $req ) {
		return self::out( AXH_Bill::record_payment( self::body( $req ) ) );
	}

	public static function payment_action( $req ) {
		$b  = self::body( $req );
		$id = (int) $req['id'];
		switch ( $b['action'] ?? '' ) {
			case 'clear':
				return self::out( AXH_Bill::set_cleared( $id, true, (string) ( $b['date'] ?? '' ) ) );
			case 'unclear':
				return self::out( AXH_Bill::set_cleared( $id, false ) );
			case 'void':
				return self::out( AXH_Bill::void_payment( $id, (string) ( $b['reason'] ?? '' ) ) );
			case 'refund':
				return self::out( AXH_Bill::refund_payment( $id, $b ) );
		}
		return new WP_Error( 'axh_bad_action', 'Unknown action.', array( 'status' => 400 ) );
	}

	public static function apply_credit( $req ) {
		$b = self::body( $req );
		return self::out( AXH_Bill::apply_credit( (int) ( $b['doc_id'] ?? 0 ), $b['amount'] ?? 0 ) );
	}

	public static function get_settings( $req ) {
		return rest_ensure_response( array( 'settings' => AXH_Bill::settings(), 'business' => AXH_Bill::business(), 'onboarding_name' => AXH_Onboard::settings()['company_legal_name'] ) );
	}

	public static function save_settings( $req ) {
		$s = AXH_Bill::save_settings( self::body( $req ) );
		return rest_ensure_response( array( 'settings' => $s, 'business' => AXH_Bill::business() ) );
	}

	public static function templates( $req ) {
		return rest_ensure_response( array( 'templates' => AXH_Bill::templates() ) );
	}

	public static function save_template( $req ) {
		$t = AXH_Bill::save_template( self::body( $req ) );
		return is_wp_error( $t ) ? $t : rest_ensure_response( array( 'templates' => $t ) );
	}

	public static function export( $req ) {
		return self::out( AXH_Bill::export( sanitize_key( (string) $req->get_param( 'kind' ) ), sanitize_key( (string) $req->get_param( 'format' ) ), (int) $req->get_param( 'year' ) ) );
	}

	public static function commissions( $req ) {
		return rest_ensure_response( AXH_Bill::commissions( (int) $req->get_param( 'year' ) ) );
	}

	public static function add_hold( $req ) {
		$b = self::body( $req );
		$r = AXH_Bill::add_hold( (int) ( $b['lead_id'] ?? 0 ), (string) ( $b['leg'] ?? 'both' ), (string) ( $b['reason'] ?? '' ) );
		return is_wp_error( $r ) ? $r : rest_ensure_response( array( 'ok' => true ) );
	}

	public static function resolve_adjust( $req ) {
		$b = self::body( $req );
		$r = AXH_Bill::resolve_adjust( (int) $req['id'], (string) ( $b['action'] ?? '' ), (string) ( $b['note'] ?? '' ) );
		return is_wp_error( $r ) ? $r : rest_ensure_response( array( 'ok' => true ) );
	}

	public static function add_clawback( $req ) {
		$r = AXH_Bill::add_clawback( self::body( $req ) );
		return is_wp_error( $r ) ? $r : rest_ensure_response( array( 'ok' => true ) );
	}

	public static function create_batch( $req ) {
		return self::out( AXH_Bill::create_batch( self::body( $req ) ) );
	}

	public static function get_batch( $req ) {
		$b = AXH_Bill::batch( (int) $req['id'] );
		return $b ? rest_ensure_response( $b ) : self::nf();
	}

	public static function pay_batch( $req ) {
		return self::out( AXH_Bill::pay_batch( (int) $req['id'], self::body( $req ) ) );
	}

	public static function void_batch( $req ) {
		$b = self::body( $req );
		return self::out( AXH_Bill::void_batch( (int) $req['id'], (string) ( $b['reason'] ?? '' ) ) );
	}

	public static function statements( $req ) {
		return rest_ensure_response( array( 'statements' => AXH_Bill::statements( (int) $req->get_param( 'rep_id' ) ) ) );
	}

	public static function statement( $req ) {
		$s = AXH_Bill::statement( (int) $req['batch'], (int) $req['rep'] );
		return $s ? rest_ensure_response( $s ) : self::nf();
	}

	public static function summary_1099( $req ) {
		return rest_ensure_response( AXH_Bill::summary_1099( (int) ( $req->get_param( 'year' ) ? $req->get_param( 'year' ) : wp_date( 'Y' ) ) ) );
	}

	public static function run_reminders( $req ) {
		return rest_ensure_response( array( 'sent' => AXH_Bill::daily( true ) ) );
	}

	public static function public_accept( $req ) {
		return self::out( AXH_Bill::accept_public( (string) $req['token'], self::body( $req ) ) );
	}

	public static function public_decline( $req ) {
		return self::out( AXH_Bill::decline_public( (string) $req['token'], self::body( $req ) ) );
	}

	/** Hand the customer to a payment gateway, if one is installed. */
	public static function public_pay( $req ) {
		$doc = AXH_Bill::doc_by_token( (string) $req['token'] );
		if ( ! $doc || 'invoice' !== $doc['type'] || in_array( $doc['status'], array( 'draft', 'void', 'paid' ), true ) ) {
			return self::nf();
		}
		$g  = AXH_Bill::gateways();
		$b  = self::body( $req );
		$id = sanitize_key( $b['gateway'] ?? '' );
		if ( ! isset( $g[ $id ] ) ) {
			return new WP_Error( 'axh_no_gateway', 'Online payment is not available. Use the payment instructions on the invoice.', array( 'status' => 400 ) );
		}
		$url = call_user_func( $g[ $id ]['start'], $doc, $doc['balance'] );
		return is_wp_error( $url ) ? $url : rest_ensure_response( array( 'redirect' => esc_url_raw( $url ) ) );
	}
}
