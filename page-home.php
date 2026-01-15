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
$args = [
    'post_type'      => 'user-experience',
    'posts_per_page' => 10,
    'meta_query'     => [
        [
            'key'     => 'feature_in_slider',
            'value'   => 'yes',   // since your field is yes/no
            'compare' => '='
        ]
    ]
];

$slider_query = new WP_Query( $args );

if ( $slider_query->have_posts() ) :

    // Enable slider context for template part
    $GLOBALS['is_home_slider'] = true;
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

                <?php if ( $image_url ) : ?>
                    <div class="wc-slider-image">
                        <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php the_title_attribute(); ?>">
                    </div>
                <?php endif; ?>

                <div class="wc-slider-content">

                    <?php
                    // Reuse your existing bento-box template
                    get_template_part( 'loop-templates/content', 'user-experience' );
                    ?>

                </div>

            </div>

        </div>

        <?php
            $index++;
        endwhile;
        ?>

    </div>

    <button class="carousel-control-prev" type="button" data-bs-target="#experienceCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon"></span>
    </button>

    <button class="carousel-control-next" type="button" data-bs-target="#experienceCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon"></span>
    </button>

</div>

<?php
    unset( $GLOBALS['is_home_slider'] );
endif;

wp_reset_postdata();
?>

</main>

<?php get_footer(); ?>