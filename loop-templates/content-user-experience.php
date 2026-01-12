<?php
/**
 * Post rendering content according to caller of get_template_part
 *
 * @package Understrap
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;
?>

<article <?php post_class(); ?> id="post-<?php the_ID(); ?>">

	<header class="entry-header">

		<?php
		the_title(
			sprintf( '<h2 class="entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ),
			'</a></h2>'
		);
		?>

		<?php if ( 'post' === get_post_type() ) : ?>

			<div class="entry-meta">
				<?php understrap_posted_on(); ?>
			</div><!-- .entry-meta -->

		<?php endif; ?>

	</header><!-- .entry-header -->

	<?php echo get_the_post_thumbnail( $post->ID, 'large' ); ?>

	<div class="entry-content">

		<?php
		//the_excerpt();
		  $post_id = get_the_ID();

    // Get the pieces for the bento box
    $title_of_creative_work = get_field( 'title_of_creative_work', $post_id);
	$experience = get_field( 'experience', $post_id);
	$technology = get_field( 'technology', $post_id);
	$feature = get_field( 'feature', $post_id);
	$display_name = get_the_author_meta( 'display_name', get_post_field( 'post_author', $post_id ));
	$date = get_the_date( 'F j, Y', $post_id);
	//$authorarchivelink = ge


    // Display the bento box

    echo "<div class='container'>
        <div class='row'>
            <div class='col-md-6'>
                <div class='button_creative_work'>{$title_of_creative_work}</div>
                <div class='button_experience'>{$experience}</div>
                <div class='button_technology'>{$technology}</div>
            </div>
            <div class='col-md-6'>
                <div class='button_feature'>{$feature}</div>
                <div class='button_user'>{$display_name}</div>
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
