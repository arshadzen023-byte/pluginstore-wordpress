<?php get_header(); ?>
<?php while (have_posts()) : the_post(); ?>
<article id="page-<?php the_ID(); ?>" <?php post_class(); ?>>
<?php if (!function_exists('elementor_theme_do_location') || !elementor_theme_do_location('single')) : ?>
<?php if (!get_post_meta(get_the_ID(), '_elementor_edit_mode', true)) : ?><h1 class="ark-page-title"><?php the_title(); ?></h1><?php endif; ?>
<div class="entry-content"><?php the_content(); ?></div>
<?php endif; ?>
</article>
<?php endwhile; ?>
<?php get_footer(); ?>
