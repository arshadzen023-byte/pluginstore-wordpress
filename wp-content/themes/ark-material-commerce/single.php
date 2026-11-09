<?php get_header(); while (have_posts()) : the_post(); ?>
<article <?php post_class(); ?>><h1 class="ark-page-title"><?php the_title(); ?></h1>
<?php if (has_post_thumbnail()) the_post_thumbnail('large'); ?>
<div class="entry-content"><?php the_content(); ?></div>
</article>
<?php endwhile; get_footer(); ?>
