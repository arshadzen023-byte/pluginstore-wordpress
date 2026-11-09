<footer class="ark-site-footer">
  <div class="ark-container">
    <div class="ark-footer-grid">
      <div>
        <a class="ark-brand" href="<?php echo esc_url(home_url('/')); ?>" style="color:#fff"><span class="ark-brand-mark"><?php echo ark_material_icon('shopping_bag'); ?></span><span><?php echo esc_html(get_theme_mod('ark_brand_name', get_bloginfo('name') ?: 'ARK Mart')); ?></span></a>
        <p><?php esc_html_e('A simple, fast and mobile-friendly shopping experience.','ark-material-commerce'); ?></p>
      </div>
      <div>
        <h2 class="ark-footer-title"><?php esc_html_e('Explore','ark-material-commerce'); ?></h2>
        <?php if (has_nav_menu('footer')) { wp_nav_menu(array('theme_location'=>'footer','container'=>false,'depth'=>1)); } else { ?>
          <p><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home','ark-material-commerce'); ?></a></p>
          <?php if (function_exists('wc_get_page_permalink')) : ?><p><a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>"><?php esc_html_e('Shop','ark-material-commerce'); ?></a></p><?php endif; ?>
        <?php } ?>
      </div>
      <div>
        <h2 class="ark-footer-title"><?php esc_html_e('Customer care','ark-material-commerce'); ?></h2>
        <p><a href="<?php echo esc_url(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url()); ?>"><?php esc_html_e('My account','ark-material-commerce'); ?></a></p>
        <?php if (function_exists('wc_get_page_permalink')) : ?><p><a href="<?php echo esc_url(wc_get_page_permalink('cart')); ?>"><?php esc_html_e('Shopping cart','ark-material-commerce'); ?></a></p><?php endif; ?>
      </div>
    </div>
    <div class="ark-footer-bottom"><?php echo esc_html(str_replace('{year}', date_i18n('Y'), get_theme_mod('ark_footer_note', '© {year} ARK Mart. All rights reserved.'))); ?></div>
  </div>
</footer>
