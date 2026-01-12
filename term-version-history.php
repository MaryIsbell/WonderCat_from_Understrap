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
                        <h1 class="page-title"><?php the_title(); ?></h1>
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
                            echo '<a href="' . esc_url($term_link) . '">' . esc_html($term->name) . '</a>';
                            echo '</h3>';

                            // Query Gravity Flow entries associated with this term
                            // Replace FORM_ID and FIELD_ID with your actual values
                            $entries = $wpdb->get_results($wpdb->prepare(
                                "SELECT e.id, e.date_created, em.meta_value as description, u.display_name as user_name
                                 FROM {$wpdb->prefix}gf_entry e
                                 INNER JOIN {$wpdb->prefix}gf_entry_meta em ON e.id = em.entry_id
                                 LEFT JOIN {$wpdb->users} u ON e.created_by = u.ID
                                 WHERE em.form_id = %d AND em.meta_key = %s AND em.meta_value = %d
                                 ORDER BY e.date_created ASC",
                                5, // FORM_ID - replace with your Gravity Form ID
                                $taxonomy . '_term_id', // meta_key storing the term ID for each taxonomy
                                $term->term_id
                            ));

                            if (!empty($entries)) :
                                echo '<table style="width:100%; border-collapse: collapse; border:1px solid #333; margin-bottom: 2rem;">';
                                echo '<thead>
                                        <tr>
                                            <th style="border:1px solid #333; padding:8px; text-align:left;">Proposed Description</th>
                                            <th style="border:1px solid #333; padding:8px; text-align:left;">Submitted By</th>
                                            <th style="border:1px solid #333; padding:8px; text-align:left;">Date</th>
                                        </tr>
                                      </thead><tbody>';

                                foreach ($entries as $entry) {
                                    echo '<tr>';
                                    echo '<td style="border:1px solid #333; padding:8px;">' . esc_html($entry->description) . '</td>';
                                    echo '<td style="border:1px solid #333; padding:8px;">' . esc_html($entry->user_name) . '</td>';
                                    echo '<td style="border:1px solid #333; padding:8px;">' . esc_html(date('F j, Y', strtotime($entry->date_created))) . '</td>';
                                    echo '</tr>';
                                }

                                echo '</tbody></table>';
                            else :
                                echo '<p>No proposed revisions for this term.</p>';
                            endif;

                        endforeach;

                    else :
                        echo '<p>No terms found in the ' . esc_html($taxonomy) . ' taxonomy.</p>';
                    endif;

                endforeach;
                ?>

            </main><!-- #main -->

        </div><!-- .row -->
    </div><!-- #content -->
</div><!-- #multi-taxonomy-revisions-wrapper -->

<?php get_footer(); ?>
