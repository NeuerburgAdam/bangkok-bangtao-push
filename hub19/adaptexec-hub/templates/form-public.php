<?php
/**
 * The public lead-capture form at /f/<slug>/.
 *
 * @var array    $form   Form row.
 * @var array    $steps  Steps with only the enabled fields.
 * @var array    $config Front-end config.
 * @var WP_User  $rep    Owning representative.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$axh_accent   = $form['accent'] ? $form['accent'] : '#EF5D0F';
$axh_headline = $form['headline'] ? $form['headline'] : 'Let\'s build something that fits.';
$axh_subhead  = $form['subhead'] ? $form['subhead'] : 'Tell us how your business runs today and we will show you what one platform could replace.';
$axh_rep_name = $rep ? $rep->display_name : 'AdaptExec';
$axh_initial  = strtoupper( substr( $axh_rep_name, 0, 1 ) );
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta name="robots" content="noindex, follow">
	<meta name="theme-color" content="#0D0E10">
	<title><?php echo esc_html( $axh_headline ); ?> · AdaptExec</title>
	<meta name="description" content="<?php echo esc_attr( wp_trim_words( $axh_subhead, 28 ) ); ?>">
	<?php
	AXH_Portal::share_tags(
		array(
			'title'       => $axh_headline,
			'description' => wp_trim_words( $axh_subhead, 28 ),
			'url'         => home_url( '/f/' . rawurlencode( $form['slug'] ) . '/' ),
		)
	);
	?>
	<link rel="stylesheet" href="<?php echo esc_url( AXH_URL . 'assets/css/form.css?v=' . AXH_VERSION ); ?>">
	<style>:root{ --accent: <?php echo esc_attr( $axh_accent ); ?>; }</style>
</head>
<body class="axf-body">

<div class="axf">

	<aside class="axf-brand">
		<div class="axf-brand__top">
			<img src="<?php echo esc_url( AXH_URL . 'assets/img/Mask-group-27.svg' ); ?>" alt="AdaptExec" class="axf-logo">
		</div>

		<div class="axf-brand__mid">
			<h1><?php echo esc_html( $axh_headline ); ?></h1>
			<p><?php echo esc_html( $axh_subhead ); ?></p>

			<?php if ( $form['intro'] ) : ?>
				<p class="axf-intro"><?php echo esc_html( $form['intro'] ); ?></p>
			<?php endif; ?>

			<ul class="axf-points">
				<li>One platform instead of six subscriptions</li>
				<li>Built around how you already work</li>
				<li>You own it — software and source code</li>
			</ul>
		</div>

		<div class="axf-rep">
			<span class="axf-rep__av"><?php echo esc_html( $axh_initial ); ?></span>
			<div>
				<strong><?php echo esc_html( $axh_rep_name ); ?></strong>
				<span>Your AdaptExec representative</span>
			</div>
		</div>

		<img class="axf-shape" src="<?php echo esc_url( AXH_URL . 'assets/img/obj2-1.svg' ); ?>" alt="" aria-hidden="true">
	</aside>

	<main class="axf-main">
		<div class="axf-card">

			<div class="axf-mobilehead">
				<img src="<?php echo esc_url( AXH_URL . 'assets/img/logo-dark.svg' ); ?>" alt="AdaptExec">
			</div>

			<div class="axf-progress" id="axf-progress" aria-hidden="true">
				<?php foreach ( $steps as $i => $step ) : ?>
					<span class="axf-progress__seg<?php echo 0 === $i ? ' is-on' : ''; ?>"></span>
				<?php endforeach; ?>
			</div>

			<form id="axf-form" novalidate>
				<?php foreach ( $steps as $i => $step ) : ?>
					<section class="axf-step<?php echo 0 === $i ? ' is-on' : ''; ?>" data-step="<?php echo (int) $i; ?>">
						<p class="axf-eyebrow">Step <?php echo (int) $i + 1; ?> of <?php echo count( $steps ); ?></p>
						<h2><?php echo esc_html( $step['name'] ); ?></h2>

						<?php if ( ! empty( $step['style'] ) ) : ?>
							<p class="axf-stylehelp">Optional &mdash; tap up to three website styles you like. It helps us start your design in the right place.</p>
							<div class="axf-style" id="axf-style"></div>
						<?php endif; ?>

						<div class="axf-grid">
							<?php foreach ( $step['fields'] as $f ) : ?>
								<?php
								$id    = 'axf-' . esc_attr( $f['key'] );
								$req   = ! empty( $f['required'] );
								$width = ( $f['width'] ?? 'full' ) === 'half' ? ' axf-half' : '';
								?>
								<div class="axf-field<?php echo esc_attr( $width ); ?>">
									<label for="<?php echo esc_attr( $id ); ?>">
										<?php echo esc_html( $f['label'] ); ?>
										<?php if ( ! $req ) : ?><span class="axf-opt">optional</span><?php endif; ?>
									</label>

									<?php if ( 'textarea' === $f['type'] ) : ?>
										<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $f['key'] ); ?>"
											rows="4" placeholder="<?php echo esc_attr( $f['placeholder'] ?? '' ); ?>"
											<?php echo $req ? 'data-required' : ''; ?>></textarea>

									<?php elseif ( 'select' === $f['type'] ) : ?>
										<div class="axf-select">
											<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $f['key'] ); ?>" <?php echo $req ? 'data-required' : ''; ?>>
												<option value="">Choose one…</option>
												<?php foreach ( (array) $f['options'] as $opt ) : ?>
													<option value="<?php echo esc_attr( $opt ); ?>"><?php echo esc_html( $opt ); ?></option>
												<?php endforeach; ?>
											</select>
										</div>

									<?php elseif ( 'checks' === $f['type'] ) : ?>
										<div class="axf-checks" data-checks="<?php echo esc_attr( $f['key'] ); ?>">
											<?php foreach ( (array) $f['options'] as $opt ) : ?>
												<button type="button" class="axf-chip" data-val="<?php echo esc_attr( $opt ); ?>"><?php echo esc_html( $opt ); ?></button>
											<?php endforeach; ?>
										</div>

									<?php else : ?>
										<input type="<?php echo esc_attr( $f['type'] ); ?>" id="<?php echo esc_attr( $id ); ?>"
											name="<?php echo esc_attr( $f['key'] ); ?>"
											placeholder="<?php echo esc_attr( $f['placeholder'] ?? '' ); ?>"
											autocomplete="<?php echo esc_attr( axh_autocomplete( $f['key'] ) ); ?>"
											<?php echo $req ? 'data-required' : ''; ?>>
									<?php endif; ?>

									<span class="axf-err" hidden>Please fill this in.</span>
								</div>
							<?php endforeach; ?>
						</div>

						<?php if ( ! empty( $config['consent'] ) && in_array( 'phone', wp_list_pluck( $step['fields'], 'key' ), true ) ) : ?>
							<label class="axf-consent">
								<input type="checkbox" name="sms_consent" value="yes">
								<span><?php echo esc_html( $config['consent'] ); ?></span>
							</label>
						<?php endif; ?>

						<div class="axf-nav">
							<?php if ( $i > 0 ) : ?>
								<button type="button" class="axf-btn axf-btn--ghost" data-back>Back</button>
							<?php else : ?>
								<span></span>
							<?php endif; ?>

							<?php if ( $i < count( $steps ) - 1 ) : ?>
								<button type="button" class="axf-btn axf-btn--go" data-next>Continue</button>
							<?php else : ?>
								<button type="submit" class="axf-btn axf-btn--go" data-submit>Send my details</button>
							<?php endif; ?>
						</div>
					</section>
				<?php endforeach; ?>

				<!-- bots fill this in; people never see it -->
				<div class="axf-hp" aria-hidden="true">
					<label>Website URL<input type="text" name="website_url" tabindex="-1" autocomplete="off"></label>
				</div>
			</form>

			<div class="axf-done" id="axf-done" hidden>
				<div class="axf-done__tick">
					<svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
				</div>
				<h2 id="axf-done-h"></h2>
				<p id="axf-done-b"></p>
				<a class="axf-btn axf-btn--go axf-book" id="axf-book" href="#" target="_blank" rel="noopener" hidden></a>
			</div>

			<p class="axf-fine">
				We only use this to get back to you about your project. No lists, no spam.
			</p>
		</div>
	</main>
</div>

<script>window.AXF = <?php echo wp_json_encode( $config ); ?>;</script>
<?php if ( ! empty( $config['styles'] ) ) : ?>
<style>.axf-stylehelp{margin:-4px 0 14px;color:#69727D;font-size:14px}.axf-style{margin-bottom:8px}</style>
<?php echo axh_style_assets(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
<?php endif; ?>
<script src="<?php echo esc_url( AXH_URL . 'assets/js/form.js?v=' . AXH_VERSION ); ?>"></script>
</body>
</html>
