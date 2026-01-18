<?php
/**
 * Custom responsive navbar for UnderStrap child theme (hard-coded)
 *
 * Left: Site title + nav links
 * Right: Log In / Sign Up (always visible)
 *
 * @package YourChildTheme
 */

defined( 'ABSPATH' ) || exit;
?>

<nav class="navbar navbar-expand-lg navbar-light bg-light p-0">
    <div class="container-fluid">

        <!-- Site title / logo -->
        <a class="navbar-brand" href="<?php echo esc_url(home_url('/')); ?>">
            <?php bloginfo('name'); ?>
        </a>

        <!-- Hamburger toggle (mobile) -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#primaryNavbar"
                aria-controls="primaryNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navigation links -->
        <div class="collapse navbar-collapse mt-2 mt-lg-0" id="primaryNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="/user-experience">Archive</a></li>
                <li class="nav-item"><a class="nav-link" href="/glossaries">Glossaries</a></li>
                <li class="nav-item"><a class="nav-link" href="/visualizations">Visualizations</a></li>
                <li class="nav-item"><a class="nav-link" href="/add-experience">Contribute</a></li>
            </ul>
        </div>

        <!-- Login / Signup -->
        <div class="d-flex align-items-center login-signup-wrapper ms-lg-auto">
    <?php if ( ! is_user_logged_in() ) : ?>

        <a href="<?php echo esc_url( wp_login_url() ); ?>">Log In</a>
        <span class="mx-1">or</span>
        <a href="/sign-up">Sign Up</a>

    <?php else : ?>

        <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>">Log Out</a>

    <?php endif; ?>
</div>


    </div>
</nav>
