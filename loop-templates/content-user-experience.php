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
		//the_excerpt();
		  $post_id = get_the_ID();

    // Get the pieces for the bento box
    $title_of_creative_work = get_field( 'title_of_creative_work', $post_id);
	$experience = '';
$experience_url = '';

$experience_terms = get_the_terms( $post_id, 'experience' );

if ( ! empty( $experience_terms ) && ! is_wp_error( $experience_terms ) ) {
    $term = $experience_terms[0];
    $experience = $term->name;
    $experience_url = get_term_link( $term );
}

	$technology = '';
	$technology_url='';
	$technology_terms=get_the_terms( $post_id, 'technology' );
if ( ! empty($technology_terms ) && ! is_wp_error( $technology_terms ) ) {
	$term = $technology_terms[0];
	$technology = $term->name;
	$technology_url = get_term_link( $term );
}
	
	
	$feature = get_field( 'feature', $post_id);
	$display_name = get_the_author_meta( 'display_name', get_post_field( 'post_author', $post_id ));
	$author_id = get_post_field( 'post_author', $post_id ); 
	$author_url = get_author_posts_url( $author_id );
	$date = get_the_date( 'F j, Y', $post_id);
	
	// Choose container class based on context
	$container_class = $is_slider ? 'bento_container bento_slider' : 'bento_container';

    // Display the bento box

    echo "<div class='bento_container'>
        <div class='row'>
            <div class='col-md-6'>
                <div class='button_creative_work'>{$title_of_creative_work}</div>
                <div class='button_experience'>Experience:
    				<a href='" . esc_url( $experience_url ) . "'>" . esc_html( $experience ) . "</a>
				</div>
                <div class='button_technology'>Narrative Technology:
    				<a href='" . esc_url( $technology_url ) . "'>" . esc_html( $technology ) . "</a>
				</div></div>
            <div class='col-md-6'>
                <div class='button_feature'>{$feature}</div>
                <div class='button_user'>Contributed by: 
                	<a href='" . esc_url( $author_url ) . "'>" . esc_html( $display_name ) . "</a>
            	</div>
                <div class='button_date'>{$date}</div>
            </div>
        </div>
    </div>"
    ;
		understrap_link_pages();
		?>

	</div><!-- .entry-content -->

	<footer class="entry-footer">

		<?php understrap_entry_footer(); ?>

	</footer><!-- .entry-footer -->

</article><!-- #post-<?php the_ID(); ?> -->
