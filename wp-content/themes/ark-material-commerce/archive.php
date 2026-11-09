<?php get_header(); ?>
<header><h1 class="ark-page-title"><?php the_archive_title(); ?></h1><?php the_archive_description('<div class="archive-description">','</div>'); ?></header>
<?php if (have_posts()) : ?><div class="ark-post-grid"><?php while (have_posts()) : the_post(); ?>
<article <?php post_class('ark-card'); ?> style="padding:18px;margin-bottom:18px"><?php if (has_post_thumbnail()) : ?><a href="<?php the_permalink(); ?>"><?php the_post_thumbnail('medium_large'); ?></a><?php endif; ?><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><p><?php echo esc_html(wp_trim_words(get_the_excerpt(), 28)); ?></p></article>
<?php endwhile; ?></div><?php the_posts_pagination(); else : ?><p><?php esc_html_e('No posts found.','ark-material-commerce'); ?></p><?php endif; ?>
<?php get_footer(); ?>
