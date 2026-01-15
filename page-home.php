<?php
/**
 * Template Name: Home Page Template
 *
 * A custom template to associate with a static home page for the WonderCat site that draws in featured user-experience posts into a carousel
 *
 * @package Understrap
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="primary" class="site-main">

<?php
$args = [
    'post_type'      => 'experience',   // change if needed
    'posts_per_page' => 10,
    'meta_query'     => [
        [
            'key'     => 'feature_in_slider',
            'value'   => '1',
            'compare' => '='
        ]
    ]
];

$slider_query = new WP_Query( $args );

if ( $slider_query->have_posts() ) :
?>

<div id="experienceCarousel" class="carousel slide" data-bs-ride="carousel">

    <div class="carousel-inner">

        <?php
        $index = 0;

        while ( $slider_query->have_posts() ) :
            $slider_query->the_post();

            $image = get_field( 'slider_image' );
            $bg    = get_field( 'slider_background_color' );

            $image_url = '';

            if ( is_array( $image ) && isset( $image['url'] ) ) {
                $image_url = $image['url'];
            } elseif ( is_string( $image ) ) {
                $image_url = $image;
            }

            $bg_style = $bg ? 'style="background-color:' . esc_attr( $bg ) . ';"' : '';
        ?>

        <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">

            <div class="wc-slider-slide" <?php echo $bg_style; ?>>

                <div class="wc-slider-content">

                    <?php if ( $image_url ) : ?>
                        <div class="wc-slider-image">
                            <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php the_title_attribute(); ?>">
                        </div>
                    <?php endif; ?>

                    <div class="wc-slider-text">
                        <h3><?php the_title(); ?></h3>
                        <div class="wc-slider-excerpt">
                            <?php the_excerpt(); ?>
                        </div>
                        <a href="<?php the_permalink(); ?>" class="btn btn-outline-dark mt-2">
                            View Experience
                        </a>
                    </div>

                </div>

            </div>

        </div>

        <?php
            $index++;
        endwhile;
        ?>

    </div>

    <!-- Controls -->
    <button class="carousel-control-prev" type="button" data-bs-target="#experienceCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon"></span>
    </button>

    <button class="carousel-control-next" type="button" data-bs-target="#experienceCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon"></span>
    </button>

</div>

<?php
endif;
wp_reset_postdata();
?>

</main>

<?php get_footer(); ?>