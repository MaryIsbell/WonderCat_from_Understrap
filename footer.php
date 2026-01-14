<?php
/**
 * The template for displaying the footer
 *
 * @package Understrap
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrapper" id="wrapper-footer">

    <footer class="site-footer wc-footer-bar">

        <div class="wc-footer-inner">

            <div class="wc-footer-left">
                <a href="<?php echo esc_url( home_url('/about') ); ?>">About</a>
            </div>

            <div class="wc-footer-right">
                Powered by WordPress and WikiData | WonderCat Theme
            </div>

        </div>

    </footer>

</div>

<?php wp_footer(); ?>
</body>
</html>


