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
        <div class="container-fluid"> 
            <div class="row justify-content-between align-items-center"> 
                <div class="col-auto wc-footer-left"> 
                    <a href="<?php echo esc_url( home_url('/about') ); ?>">About</a> 
                </div> <div class="col-auto wc-footer-right text-end"> Powered by WordPress and WikiData | WonderCat Theme </div> 
            </div> 
        </div> 
    </footer> 
</div>

<?php wp_footer(); ?>
</body>
</html>


