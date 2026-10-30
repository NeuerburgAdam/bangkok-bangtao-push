<?php
/**
 * Branded sign-in screen. Deliberately does not look like wp-login.php.
 *
 * @var string $error
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Sign in · AdaptExec Hub</title>
	<link rel="stylesheet" href="<?php echo esc_url( AXH_URL . 'assets/css/hub.css?v=' . AXH_VERSION ); ?>">
</head>
<body class="axh-login-body">
	<div class="axh-login">
		<div class="axh-login__panel">
			<img class="axh-login__logo" src="<?php echo esc_url( AXH_URL . 'assets/img/Mask-group-27.svg' ); ?>" alt="AdaptExec">
			<h1 class="thunder">The Hub.</h1>
			<p>Your pipeline, your clients, your presentations — one login.</p>
			<ul>
				<li>Register and track every prospect</li>
				<li>Run the client questionnaire on site</li>
				<li>Present from any laptop or tablet</li>
			</ul>
			<img class="axh-login__shape" src="<?php echo esc_url( AXH_URL . 'assets/img/obj2-1.svg' ); ?>" alt="" aria-hidden="true">
		</div>

		<div class="axh-login__form">
			<div class="axh-login__formInner">
				<span class="axh-eyebrow">AdaptExec Sales Hub</span>
				<h2 class="thunder">Welcome back.</h2>

				<?php if ( ! empty( $error ) ) : ?>
					<div class="axh-alert" role="alert"><?php echo esc_html( $error ); ?></div>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( axh_portal_url() ); ?>">
					<?php wp_nonce_field( 'axh_login' ); ?>
					<input type="hidden" name="axh_login" value="1">

					<label for="axh-log">Email or username</label>
					<input id="axh-log" name="log" type="text" autocomplete="username" required autofocus>

					<label for="axh-pwd">Password</label>
					<input id="axh-pwd" name="pwd" type="password" autocomplete="current-password" required>

					<label class="axh-check">
						<input type="checkbox" name="rememberme" value="1" checked>
						<span>Keep me signed in</span>
					</label>

					<button type="submit" class="axh-btn axh-btn--primary axh-btn--block">Sign in</button>
				</form>

				<p class="axh-login__help">
					<a href="<?php echo esc_url( wp_lostpassword_url( axh_portal_url() ) ); ?>">Forgot your password?</a>
					<span>Need access? Email <a href="mailto:adam@adaptexec.com">adam@adaptexec.com</a></span>
				</p>
			</div>
		</div>
	</div>
</body>
</html>
