<?php if ( ! defined( 'ABSPATH' ) ) { die(); }

$compiler_message = 'WP COMPILER ERROR \a ';
$compiler_message .= $this->now . '\a ------------ \a ' . $message;
$compiler_message .= '\a additional errors may be visible in the WordPress Dashboard \a ';

?>
body:before {
	display: block !important;
	font-size: 14px !important;
	font-family: monospace !important;
	font-weight: bold !important;
	background-color: #a00 !important;
	color: #eee !important;
	padding: 20px !important;
	white-space: pre !important;
	content: "<?php echo $compiler_message; ?>" !important;
}
<?php
