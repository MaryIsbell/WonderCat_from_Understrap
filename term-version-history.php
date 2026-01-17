<?php
/**
 * Template Name: Term Version History
 *
 * A custom page to display the version history of all terms on this site.
 *
 * @package Understrap
 */

// Exit if accessed directly.


defined('ABSPATH') || exit;
get_header();

$field_map = [
    'experience' => [
        'fields' => ['9', '10'],
        'labels' => ['Proposed Experience Term', 'Proposed Definition']
    ],
    'technology' => [
        'fields' => ['11', '12'],
        'labels' => ['Proposed Technology Term', 'Proposed Definition']
    ],
];
$container = get_theme_mod('understrap_container_type');
?>



<div class="wrapper" id="multi-taxonomy-revisions-wrapper">
    <div class="<?php echo esc_attr($container); ?>" id="content" tabindex="-1">
        <div class="row">

            <main class="site-main col" id="main">

                <?php
                // Page title and content
                if (have_posts()) :
                    while (have_posts()) : the_post();
                        ?>
                        <h1 class="page-title centered-title"><?php the_title(); ?></h1>
                        <div class="page-content"><?php the_content(); ?></div>
                        <?php
                    endwhile;
                endif;

                global $wpdb;

                // Define the taxonomies to display
                $taxonomies = ['experience', 'technology'];

                foreach ($taxonomies as $taxonomy) :
                    echo '<h2>' . esc_html(ucfirst($taxonomy)) . ' Revisions</h2>';

                    // Fetch all terms for the taxonomy
                    $terms = get_terms([
                        'taxonomy' => $taxonomy,
                        'hide_empty' => false,
                        'orderby' => 'name',
                        'order' => 'ASC',
                    ]);

                    if (!empty($terms) && !is_wp_error($terms)) :

                        foreach ($terms as $term) :
                            // Anchor ID for each term
                            $term_anchor = sanitize_title($taxonomy . '-' . $term->slug);
                            $term_link = get_term_link($term);

                            echo '<h3 id="' . esc_attr($term_anchor) . '">';
                            echo esc_html($term->name);
                            echo ' <span class="term-archive-link">[<a href="' . esc_url($term_link) . '">term archive</a>]</span>';
                            echo '</h3>';

                            // ACF editorial note for the term
                            $editorial_notes = get_field('editorial-notes', $taxonomy . '_' . $term->term_id);

                             if ( ! empty($editorial_notes) ) {
                            echo '<p class="term-editorial-notes">' . esc_html($editorial_notes) . '</p>';
                            }

                            // Query Gravity Flow entries associated with this term
                            $entries = $wpdb->get_results(
    $wpdb->prepare(
        "
        SELECT 
            e.id AS entry_id,
            e.date_created,
            u.display_name,
            MAX(CASE WHEN em.meta_key = '9'  THEN em.meta_value END) AS field_9,
            MAX(CASE WHEN em.meta_key = '10' THEN em.meta_value END) AS field_10,
            MAX(CASE WHEN em.meta_key = '11' THEN em.meta_value END) AS field_11,
            MAX(CASE WHEN em.meta_key = '12' THEN em.meta_value END) AS field_12
        FROM {$wpdb->prefix}gf_entry e
        INNER JOIN {$wpdb->prefix}gf_entry_meta term_meta
            ON e.id = term_meta.entry_id
        LEFT JOIN {$wpdb->users} u
            ON e.created_by = u.ID
        LEFT JOIN {$wpdb->prefix}gf_entry_meta em
            ON e.id = em.entry_id
        WHERE 
            e.form_id = 1
            AND term_meta.meta_key = '25'
            AND term_meta.meta_value = %d
        GROUP BY e.id
        ORDER BY e.date_created ASC
        ",
        $term->term_id
    )
);


if (!empty($entries)) :

    if ( ! isset($field_map[$taxonomy]) ) {
        echo '<p>Configuration missing for taxonomy.</p>';
        continue;
    }

    $config = $field_map[$taxonomy];
    $fields = $config['fields'];
    $labels = $config['labels'];

    echo '<table style="width:100%; border-collapse: collapse; border:1px solid #333; margin-bottom: 2rem;">';

    echo '<thead><tr>';
    echo '<th style="border:1px solid #333; padding:8px;">Date</th>';
    echo '<th style="border:1px solid #333; padding:8px;">Proposed By</th>';

    foreach ($labels as $label) {
        echo '<th style="border:1px solid #333; padding:8px;">' . esc_html($label) . '</th>';
    }

    echo '</tr></thead><tbody>';

    foreach ($entries as $entry) {

        echo '<tr>';

        echo '<td style="border:1px solid #333; padding:8px;">' .
             esc_html( date('F j, Y', strtotime($entry->date_created)) ) .
             '</td>';

        echo '<td style="border:1px solid #333; padding:8px;">' .
             esc_html( $entry->display_name ?: 'Unknown' ) .
             '</td>';

        foreach ($fields as $field_number) {
            $property = 'field_' . $field_number;
            $value = isset($entry->$property) ? $entry->$property : '';
            echo '<td style="border:1px solid #333; padding:8px;">' . esc_html($value) . '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table>';

else :
    echo '<p>No proposed revisions for this term.</p>';
endif;

endforeach; // closes: foreach ($terms as $term)

endif;     // closes: if (!empty($terms) && !is_wp_error($terms))

endforeach; // closes: foreach ($taxonomies as $taxonomy)

                ?>

            </main><!-- #main -->

        </div><!-- .row -->
    </div><!-- #content -->
</div><!-- #multi-taxonomy-revisions-wrapper -->

<?php get_footer(); ?>
