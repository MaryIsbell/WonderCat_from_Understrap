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

        <div class="container"> <!-- Bootstrap container for consistent spacing -->
            <div class="row justify-content-between align-items-center">
                
                <div class="col-auto wc-footer-left">
                    <a href="<?php echo esc_url( home_url('/about') ); ?>">About WonderCat</a>
                </div>

                <div class="col-auto wc-footer-right text-end">
                    <a href="https://github.com/MaryIsbell/WonderCat_from_Understrap">Powered by WordPress and WikiData | WonderCat Theme</a>
                </div>

            </div>
        </div>

    </footer>
</div>

<?php wp_footer(); ?>
</body>
</html>


