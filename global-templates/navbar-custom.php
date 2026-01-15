<?php
/**
 * Custom responsive navbar for UnderStrap child theme
 *
 * Left: Site title + nav links
 * Right: Log In / Sign Up (always visible)
 *
 * @package YourChildTheme
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;
?>

<nav class="navbar navbar-expand-lg navbar-light bg-light p-0">
    <div class="container-fluid">

        <!-- Site title / logo -->
<a class="navbar-brand h4 mb-0" href="<?php echo esc_url(home_url('/')); ?>">
    <?php bloginfo('name'); ?>
</a>

<!-- Hamburger toggle -->
<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#primaryNavbar"
        aria-controls="primaryNavbar" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
</button>

<!-- Navigation links FIRST -->
<div class="collapse navbar-collapse mt-2 mt-lg-0" id="primaryNavbar">
    <?php
    wp_nav_menu(array(
        'theme_location' => 'primary',
        'menu_class' => 'navbar-nav me-auto mb-2 mb-lg-0',
        'container' => false,
        'depth' => 1,
        'fallback_cb' => false
    ));
    ?>
</div>

<!-- Login / Signup LAST -->
<div class="d-flex align-items-center login-signup-wrapper ms-lg-auto">
    <a href="<?php echo wp_login_url(); ?>">Log In</a>
    <span class="mx-1">or</span>
    <a href="/sign-up">Sign Up</a>
</div>
</nav>
