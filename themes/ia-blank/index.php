<?php
/**
 * IA Blank Theme
 * Single-purpose app container
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Force black background at the highest level -->
  <style>
    html, body {
      margin: 0 !important;
      padding: 0 !important;
      width: 100% !important;
      height: 100% !important;
      background: #000 !important;
      color: #fff !important;
    }
    /* Catch common theme/wrapper containers */
    #page, #content, .site, .site-content, .wp-site-blocks, main, .wp-block-group, .wp-block-cover {
      background: #000 !important;
    }
  </style>

  <?php wp_head(); ?>
</head>

<body <?php body_class(); ?> style="background:#000 !important; color:#fff !important;">

<?php
// Output page content only (this renders [ia-atrium])
if (have_posts()) {
  while (have_posts()) {
    the_post();
    the_content();
  }
}
?>

<?php wp_footer(); ?>
</body>
</html>
