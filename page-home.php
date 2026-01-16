<?php
/**
 * Template Name: Home Page Template
 *
 * @package Understrap
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="primary" class="site-main">

<?php
// Query featured user-experience posts
$args = [
    'post_type'      => 'user-experience',
    'posts_per_page' => 10,
    'meta_query'     => [
        [
            'key'     => 'feature_in_slider',
            'value'   => '1', // TRUE for ACF true/false field
            'compare' => '='
        ]
    ]
];

$slider_query = new WP_Query( $args );

if ( $slider_query->have_posts() ) :
?>

<div id="experienceCarousel" class="carousel slide wc-experience-carousel" data-bs-ride="carousel" data-bs-interval="8000">

    <div class="carousel-inner">

        <?php
        $index = 0;

        // Flag for content-user-experience.php
        $GLOBALS['is_home_slider'] = true;

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
                            <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">

<?php if ( $image && is_array( $image ) && ! empty( $image['caption'] ) ) : ?>
    <div class="wc-slider-caption">
        <?php echo esc_html( $image['caption'] ); ?>
    </div>
<?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="wc-slider-bento">

                        <?php
                        // Render your existing bento box layout
                        get_template_part( 'loop-templates/content', 'user-experience' );
                        ?>

                    </div>

                </div>

            </div>

        </div>

        <?php
            $index++;
        endwhile;

        unset( $GLOBALS['is_home_slider'] );
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
<div class="homepage_header">How it Works</div>

<!-- Full-width homepage image -->
<div class="wc-home-fullwidth-image">
    <img src="<?php echo get_stylesheet_directory_uri(); ?>/images/How_it_works_revised.png" alt="Homepage Image" loading="lazy">
</div>

<!-- Homepage CTA Section -->
<div class="homepage-cta">
    <div class="homepage-cta-left">
        Start chronicling your experiences
    </div>
    <div class="homepage-cta-right">
        <a href="<?php echo esc_url( home_url('/join') ); ?>" class="btn">
            Join WonderCat
        </a>
    </div>
</div>


</main>

<?php get_footer(); ?>