<?php
/**
 * Creates a variable test product: colours red/blue (each with its own image) × sizes S/M.
 * Usage: wp eval-file make-variable.php <fixtures-dir>
 */
$S = $args[0];
require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
if ( ! wc_attribute_taxonomy_id_by_name( 'color' ) ) { wc_create_attribute( array( 'name' => 'رنگ', 'slug' => 'color' ) ); }
register_taxonomy( 'pa_color', 'product' );
foreach ( array( 'red' => 'قرمز', 'blue' => 'آبی' ) as $slug => $name ) if ( ! term_exists( $slug, 'pa_color' ) ) wp_insert_term( $name, 'pa_color', array( 'slug' => $slug ) );
$img = function ( $file ) use ( $S ) { $tmp = wp_tempnam(); copy( "$S/$file", $tmp ); return media_handle_sideload( array( 'name' => $file, 'tmp_name' => $tmp ), 0 ); };
$red = $img( 'shirt.jpg' ); $blue = $img( 'shirt-blue.jpg' );
$p = new WC_Product_Variable(); $p->set_name( 'تیشرت رنگی' ); $p->set_image_id( $red );
$a1 = new WC_Product_Attribute(); $a1->set_id( wc_attribute_taxonomy_id_by_name( 'color' ) ); $a1->set_name( 'pa_color' ); $a1->set_options( array( get_term_by( 'slug', 'red', 'pa_color' )->term_id, get_term_by( 'slug', 'blue', 'pa_color' )->term_id ) ); $a1->set_visible( true ); $a1->set_variation( true );
$a2 = new WC_Product_Attribute(); $a2->set_name( 'size' ); $a2->set_options( array( 'S', 'M' ) ); $a2->set_visible( true ); $a2->set_variation( true );
$p->set_attributes( array( $a1, $a2 ) ); $id = $p->save();
foreach ( array( 'red' => $red, 'blue' => $blue ) as $c => $im ) foreach ( array( 'S', 'M' ) as $sz ) {
	$v = new WC_Product_Variation(); $v->set_parent_id( $id ); $v->set_attributes( array( 'pa_color' => $c, 'size' => $sz ) ); $v->set_regular_price( 'blue' === $c ? '800000' : '750000' ); $v->set_image_id( $im ); $v->save();
}
WC_Product_Variable::sync( $id );
echo $id;
