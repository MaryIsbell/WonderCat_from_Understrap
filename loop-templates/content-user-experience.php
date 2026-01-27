<?php
/**
 * Post rendering content according to caller of get_template_part
 *
 * @package Understrap
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Detect if we are rendering inside the homepage slider
$is_slider = ! empty( $GLOBALS['is_home_slider'] );
?>

<article <?php post_class(); ?> id="post-<?php the_ID(); ?>">

<?php
$post_id = get_the_ID();

// Get the pieces for the bento box
$title_of_creative_work = get_field( 'title_of_creative_work', $post_id );

// Experience taxonomy
$experience = '';
$experience_url = '';
$experience_terms = get_the_terms( $post_id, 'experience' );

if ( ! empty( $experience_terms ) && ! is_wp_error( $experience_terms ) ) {
    $term = $experience_terms[0];
    $experience = $term->name;
    $experience_url = get_term_link( $term );
}

// Technology taxonomy
$technology = '';
$technology_url = '';
$technology_terms = get_the_terms( $post_id, 'technology' );

if ( ! empty( $technology_terms ) && ! is_wp_error( $technology_terms ) ) {
    $term = $technology_terms[0];
    $technology = $term->name;
    $technology_url = get_term_link( $term );
}

// Other fields
$feature = get_field( 'feature', $post_id );

// Trim feature text only inside the homepage slider
if ( $is_slider && ! empty( $feature ) ) {
    $feature = wp_trim_words( wp_strip_all_tags( $feature ), 20, '…' );
}
$display_name = get_the_author_meta( 'display_name', get_post_field( 'post_author', $post_id ) );
$author_id    = get_post_field( 'post_author', $post_id );
$author_url   = get_author_posts_url( $author_id );
$date         = get_the_date( 'F j, Y', $post_id );

// Choose container class based on context
$container_class = $is_slider ? 'bento_container bento_slider' : 'bento_container';
?>

<div class="<?php echo esc_attr( $container_class ); ?>">
   <?php if ( get_post_status( $post_id ) === 'private' ) : ?>
    <span class="bento-private-badge"
          title="Only you and WonderCat admins can see this">
        🔒 Private
    </span>
<?php endif; ?>
    <div class="row">

        <div class="col-md-6">
            <div class="button_creative_work">
                <?php echo esc_html( $title_of_creative_work ); ?>
            </div>

            <div class="button_experience">
                Experience:
                <a href="<?php echo esc_url( $experience_url ); ?>">
                    <?php echo esc_html( $experience ); ?>
                </a>
            </div>

            <div class="button_technology">
                Narrative Technology:
                <a href="<?php echo esc_url( $technology_url ); ?>">
                    <?php echo esc_html( $technology ); ?>
                </a>
            </div>
        </div>

        <div class="col-md-6">
            <?php if ( $is_slider ) : ?>

    <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" class="button_feature button_feature_link">
        <?php echo esc_html( $feature ); ?>
    </a>

<?php else : ?>

    <div class="button_feature">
        <?php echo esc_html( $feature ); ?>
    </div>

<?php endif; ?>


            <div class="button_user">
                Contributed by:
                <a href="<?php echo esc_url( $author_url ); ?>">
                    <?php echo esc_html( $display_name ); ?>
                </a>
            </div>

            <div class="button_date">
                <?php echo esc_html( $date ); ?>
            </div>
        </div>

    </div>
</div>

<?php understrap_link_pages(); ?>

<footer class="entry-footer">
    <?php understrap_entry_footer(); ?>
</footer>

</article>
