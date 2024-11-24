
<?php
global $product;
$form_id = get_post_meta($product->get_id(), '_gravity_form_id', true);
echo "Debug - Form ID: " . $form_id;
?>