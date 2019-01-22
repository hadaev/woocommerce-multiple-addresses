<h2><?php _e('My Addresses'); ?></h2>
<?php
echo '<div class="woocommerce">';
    echo '<form action="" method="post" id="address_form">';
        if ( ! empty( $otherAddr ) ) {
        echo '<div id="addresses">';

            global $wma_current_address;
            foreach ( $otherAddr as $idx => $address ) {
            $wma_current_address = $address;
            echo '<div class="shipping_address address_block" id="shipping_address_' . $idx . '">';
                echo '<p align="right"><a href="#" class="delete">' . __( 'delete', self::$plugin_slug ) . '</a></p>';
                do_action( 'woocommerce_before_checkout_shipping_form', $checkout );

                $label['id'] = 'label';
                $label['label'] = __( 'Label', self::$plugin_slug );
                woocommerce_form_field( 'label[]', $label, $address['label'] );

                foreach ( $shipFields as $key => $field ) {

                    if ( 'shipping_alt' == $key ) {
                        continue;
                    }

                    $val = '';
                    if ( isset( $address[ $key ] ) ) {
                        $val = $address[ $key ];
                    }

                    $field['id'] = $key;
                    $key .= '[]';
                    woocommerce_form_field( $key, $field, $val );
                }

                if ( ! wc_ship_to_billing_address_only() && get_option( 'woocommerce_calc_shipping' ) !== 'no' ) {
                $is_checked = $address['shipping_address_is_default'] == 'true' ? "checked" : "";
                echo '<input type="checkbox" class="default_shipping_address" ' . $is_checked . ' value="' . $address['shipping_address_is_default'] . '"> ' . __( 'Mark this shipping address as default', self::$plugin_slug );
                echo '<input type="hidden" class="hidden_default_shipping_address" name="shipping_address_is_default[]" value="' . $address['shipping_address_is_default'] . '" />';
                }

                do_action( 'woocommerce_after_checkout_shipping_form', $checkout );
                echo '</div>';
            }
            echo '</div>';
        } else {

        echo '<div id="addresses">';

            foreach ( $shipFields as $key => $field ) :
            $field['id'] = $key;
            $key .= '[]';
            woocommerce_form_field( $key, $field, $checkout->get_value( $field['id'] ) );
            endforeach;

            if ( ! wc_ship_to_billing_address_only() && get_option( 'woocommerce_calc_shipping' ) !== 'no' ) {
                echo '<input type="checkbox" class="default_shipping_address" checked value="true"> ' . __( 'Mark this shipping address as default', self::$plugin_slug );
                echo '<input type="hidden" class="hidden_default_shipping_address" name="shipping_address_is_default[]" value="true" />';
            }

            echo '</div>';
        }
        echo '<div class="form-row">
            <input type="hidden" name="shipping_account_address_action" value="save" />
            <input type="submit" name="set_addresses" value="' . __( 'Save Addresses', self::$plugin_slug ) . '" class="button alt" />
            <a class="add_address" href="#">' . __( 'Add another', self::$plugin_slug ) . '</a>
        </div>';
        echo '</form>';
    echo '</div>';
?>
<script type="text/javascript">
    var tmpl = '<div class="shipping_address address_block"><p align="right"><a href="#" class="delete"><?php _e( "delete", self::$plugin_slug ); ?></a></p>';

    tmpl += '<?php $label['id'] = 'label';
        $label['label'] = __( 'Label', self::$plugin_slug );
        $row = woocommerce_form_field( 'label[]', $label, '' );
        echo str_replace("\n", "\\\n", str_replace("'", "\'", $row));
        ?>';

    tmpl += '<?php foreach ($shipFields as $key => $field) :
        if ( 'shipping_alt' == $key ) {
            continue;
        }
        $field['return'] = true;
        $val = '';
        $field['id'] = $key;
        $key .= '[]';
        $row = woocommerce_form_field( $key, $field, $val );
        echo str_replace("\n", "\\\n", str_replace("'", "\'", $row));
    endforeach; ?>';

    <?php if ( ! wc_ship_to_billing_address_only() && get_option( 'woocommerce_calc_shipping' ) !== 'no' ) : ?>
    tmpl += '<input type="checkbox" class="default_shipping_address" value="false"> <?php _e( "Mark this shipping address as default", self::$plugin_slug ); ?>';
    tmpl += '<input type="hidden" class="hidden_default_shipping_address" name="shipping_address_is_default[]" value="false" />';
    <?php endif; ?>

    tmpl += '</div>';
    jQuery(".add_address").click(function (e) {
        e.preventDefault();

        jQuery("#addresses").append(tmpl);

        jQuery('html,body').animate({
                scrollTop: jQuery('#addresses .shipping_address:last').offset().top},
            'slow');
    });

    jQuery(".delete").live("click", function (e) {
        e.preventDefault();
        jQuery(this).parents("div.address_block").remove();
    });

    jQuery(document).ready(function () {

        jQuery(document).on("click", ".default_shipping_address", function () {
            var $defaultShippingAddress = jQuery("input.default_shipping_address");
            if (this.checked) {
                $defaultShippingAddress.not(this).removeAttr("checked");
                $defaultShippingAddress.not(this).val("false");
                jQuery("input.hidden_default_shipping_address").val("false");
                jQuery(this).next().val('true');
                jQuery(this).val('true');
            }
            else {
                $defaultShippingAddress.val("false");
                jQuery("input.hidden_default_shipping_address").val("false");
            }
        });

    });
</script>
