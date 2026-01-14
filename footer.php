<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after
 *
 * @package Understrap
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$container = get_theme_mod( 'understrap_container_type' );
?>

<div class="wrapper" id="wrapper-footer">

    <div class="<?php echo esc_attr( $container ); ?>">

        <div class="row">

            <div class="col-md-12">

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

        </div>

    </div>

</div>

