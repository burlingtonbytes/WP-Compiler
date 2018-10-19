<?php if ( ! defined( 'ABSPATH' ) ) { die(); }
?>

/* THIS JS WAS COMPILED AND OPTIMIZED, USING THE WP COMPILER PLUGIN */
/* https://bytes.co */
<?php if ( $this->announce_dev_mode() ) : ; ?>
/* Last Compiled: <?php echo $this->now ?> */
<?php endif;
if ( ! $this->options['dev_mode'] && ! apply_filters( 'wp_compiler_skip_minification', false ) ) : ?>
/* WARNING: UNCOMPRESSED SCRIPTS CAN BE QUITE LARGE. */
/*          DISABLE DEV MODE BEFORE PUBLISHING. */
<?php endif;
