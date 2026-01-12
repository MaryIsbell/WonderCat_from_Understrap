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
    <div class="container">

        <!-- Site title / logo -->
        <a class="navbar-brand h4 mb-0" href="<?php echo esc_url(home_url('/')); ?>">
            <?php bloginfo('name'); ?>
        </a>

        <!-- Hamburger toggle for nav links -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#primaryNavbar" aria-controls="primaryNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Right-side Log In / Sign Up - always visible -->
        <div class="d-flex align-items-center ms-auto login-signup-wrapper">
            <a href="<?php echo wp_login_url(); ?>" class="btn btn-outline-primary me-2 mb-1 mb-lg-0">Log In</a>
            <?php
            if (function_exists('do_blocks')) {
                echo do_blocks('<!-- wp:button {"className":"btn-primary"} --> <div class="wp-block-button mb-1 mb-lg-0"><a class="wp-block-button__link btn btn-primary" href="/sign-up">Sign Up</a></div> <!-- /wp:button -->');
            }
            ?>
        </div>

        <!-- Collapsible nav links -->
        <div class="collapse navbar-collapse order-lg-1 mt-2 mt-lg-0" id="primaryNavbar">
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

    </div>
</nav>
