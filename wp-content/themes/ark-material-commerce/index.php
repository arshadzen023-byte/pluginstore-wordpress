<?php get_header(); ?>
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
<?php if (!is_front_page()) : ?><h1 class="ark-page-title"><?php the_title(); ?></h1><?php endif; ?>
<div class="entry-content"><?php the_content(); ?></div>
</article>
<?php endwhile; the_posts_navigation(); else : ?>
<div class="ark-no-results"><h1><?php esc_html_e('Nothing found','ark-material-commerce'); ?></h1><p><?php esc_html_e('Try searching for something else.','ark-material-commerce'); ?></p><?php get_search_form(); ?></div>
<?php endif; ?>
<?php get_footer(); ?>
