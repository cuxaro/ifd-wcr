<?php

/**
 * Plugin Name
 *
 * @package           WooCommerce Cupones Referidos · I Feel Dev
 * @author            Iván Barreda
 * @copyright         2021 Iván Barreda
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Cupones WooCommerce Referidos · I Feel Dev
 * Description:       Sistema de cupones de referidos y +
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Iván Barreda
 * Author URI:        https://ivanbarreda.com
 * Text Domain:       ifd-wcr
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

namespace IFDWCR;

use Error;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Crear un cupón
 * 
 * @param string|null $codigo_cupon         El string del código del cupón que vamos a crear o NULL para generar uno random
 * @param string|null $args                 Un array con los valores personalizados para la inicialización del plugin
 * 
 */
function crear_cupon($valor_descuento, $args = [])
{


    $cupon = new \WC_Coupon();

    /*
    $cupon_data =   [
        'code'                        => '',
		'amount'                      => 0,
		'status'                      => null,
		'date_created'                => null,
		'date_modified'               => null,
		'date_expires'                => null,
		'discount_type'               => 'fixed_cart',
		'description'                 => '',
		'usage_count'                 => 0,
		'individual_use'              => false,
		'product_ids'                 => [],
		'excluded_product_ids'        => [],
		'usage_limit'                 => 0,
		'usage_limit_per_user'        => 0,
		'limit_usage_to_x_items'      => null,
		'free_shipping'               => false,
		'product_categories'          => [],
		'excluded_product_categories' => [],
		'exclude_sale_items'          => false,
		'minimum_amount'              => '',
		'maximum_amount'              => '',
		'email_restrictions'          => [],
		'used_by'                     => [],
		'virtual'                     => false,
    ];
    */




    $cupon->set_amount($valor_descuento); //Único parametro obligatorio

    $valores_defecto = [
        'code'                        => NULL,
        'date_expires'                => NULL,
        'discount_type'               => 'fixed_cart',
        'individual_use'              => false,
        'product_ids'                 => [],
        'excluded_product_ids'        => [],
        'usage_limit'                 => 0,
        'usage_limit_per_user'        => 0,
        'limit_usage_to_x_items'      => NULL,
        'free_shipping'               => false,
        'product_categories'          => [],
        'excluded_product_categories' => [],
        'exclude_sale_items'          => false,
        'minimum_amount'              => '',
        'maximum_amount'              => '',
        'email_restrictions'          => [],
    ];

    $valores_defecto = apply_filters('ifd_wcr_valores_defecto', $valores_defecto);
    $valores = wp_parse_args($args, $valores_defecto);

    //Si no tiene codigo de cupón generamos uno aleatorio
    $codigo_cupon = $valores['code'] ?  $valores['code'] : wp_generate_password(6, false, false);
    $cupon->set_code($codigo_cupon); //Creamos el cupón con el código


    $cupon->set_discount_type($valores['discount_type']);


    //Valor de descuento, puede ser string o número, con o sin decimales. Lo formatea con el locale de la instalación del WP
    $cupon->set_date_expires($valores['date_expires']);

    //Solo se puede usar una única vez
    $cupon->set_individual_use($valores['individual_use']);

    //Array de ID de productos donde se puede usar este cupón
    $cupon->set_product_ids($valores['product_ids']);

    //Excluir productos ID donde se puede utilizar el cupón
    $cupon->set_excluded_product_ids($valores['excluded_product_ids']);

    //Número de veces donde se puede usar el cupón
    $cupon->set_usage_limit($valores['usage_limit']);

    //Número de veces que un mismo usuario puede usar el cupón
    $cupon->set_usage_limit_per_user($valores['usage_limit_per_user']);

    //El cupón va a permitir envio gratuito o no
    $cupon->set_free_shipping($valores['free_shipping']);

    //Categorias de producto donde se puede usar el cupón
    $cupon->set_product_categories($valores['product_categories']);

    //Excluir categorias de cupón donde se puede aplicar el cupon
    $cupon->set_excluded_product_categories($valores['excluded_product_categories']);

    //Excluir elementos en oferta
    $cupon->set_exclude_sale_items($valores['exclude_sale_items']);

    //Cantidad minima para poder aplicar el cupón
    $cupon->set_minimum_amount($valores['minimum_amount']);

    //Cantidad máxima para poder aplicar el cupón
    $cupon->set_maximum_amount($valores['maximum_amount']);

    //Emails a los que se puede aplicar el cupón
    $cupon->set_email_restrictions($valores['email_restrictions']);


    $cupon = apply_filters('ifd_wcr_before_create_coupon',  $cupon);

    //Guardar cupón
    $cupon->save();

    $cupon = apply_filters('ifd_wcr_after_create_coupon',  $cupon);


    do_action('ifd_wcr_new_coupon',  $cupon);

    return $cupon;

}


//Añadir un identificador para saber que el cupón lo hemos creado nosotros

add_filter('ifd_wcr_before_create_coupon', function (\WC_Coupon $cupon) {

    $ifd_wcr_generator_key = apply_filters('ifd_wcr_generator_key', 'ifd_wcr');

    $cupon->update_meta_data('coupon_generator', $ifd_wcr_generator_key); //https://github.com/woocommerce/woocommerce/blob/4.4.1/includes/abstracts/abstract-wc-data.php#L425-L475

    return $cupon;
});



//Crear un cupón asociado a un usuario cuando se crea un usuario
add_action('user_register', function ($user_id, $userdata) {


    //Creamos un cupón con el usuario con el nombre del user_id
    $cupon = \IFDWCR\crear_cupon(100);
    $cupon_id = $cupon->get_id();

    $key_cupon = apply_filters('ifd_wcr_mi_cupon_key',  'ifd_wcr_mi_cupon');
    update_user_meta($user_id, $key_cupon, $cupon_id);

    do_action('ifd_wcr_assigned_coupon_user',  $cupon, $user_id);
}, 10, 2);


//Ver cuando un cupon se ha aplicado a una orden que ha sido marcada como completada
add_action('woocommerce_order_status_completed', function ($order_id) {

    $order = wc_get_order($order_id);

    $cupones = $order->get_coupon_codes();
    if (!empty($cupones)) :


        /**@var \WC_Coupon $cupon_code*/
        foreach ($cupones as  $cupon_code) :

            $cupon = new \WC_Coupon($cupon_code);

            $ifd_wcr = apply_filters('ifd_wcr_generator_key', 'ifd_wcr');
            if ($cupon->get_meta('coupon_generator') == $ifd_wcr) :


                $cupon_id = $cupon->get_id();
                $key_cupon = apply_filters('ifd_wcr_mi_cupon_key',  'ifd_wcr_mi_cupon');
                $usuarios_con_cupon = get_users(array(
                    'meta_key'          => $key_cupon,
                    'meta_value'        => $cupon_id,
                    'number'            => 1,
                    'fields'            => 'ID'

                ));


                foreach ($usuarios_con_cupon as $user_id) :

                    do_action('ifd_wcr_applied_coupon_order_completed', $cupon, $user_id, $order);

                endforeach;
            endif;
        endforeach;
    endif;
}, 10, 1);


add_action('ifd_wcr_applied_coupon_order_completed', function (\WC_Coupon $cupon, $user_id, $order) {



    $amount = $cupon->get_amount();

    $valor_restar = 10;
    if ($amount > $valor_restar) :

        $amount = $amount - $valor_restar;
        $cupon->set_amount($amount);
        $cupon->save();

    endif;
}, 10, 3);


add_action('ifd_wcr_applied_coupon_order_completed', function (\WC_Coupon $cupon, $user_id) {


    $puntuacion_key = apply_filters('ifd_wcr_puntuacion_key',  'ifd_wcr_puntuacion');
    $puntuacion_actual = empty(get_user_meta($user_id, $puntuacion_key, true)) ? 0 : get_user_meta($user_id, $puntuacion_key, true);
    

    $puntuacion_anadir = apply_filters('ifd_wcr_puntuacion_anadir',  10, $cupon, $user_id);

    // $numero_veces_usado = $cupon->get_usage_count();
    // $puntuacion_anadir = $numero_veces_usado * $puntuacion_anadir;

    $puntuacion_nueva = wc_format_decimal($puntuacion_actual) + wc_format_decimal($puntuacion_anadir);


    update_user_meta($user_id, $puntuacion_key, wc_format_decimal($puntuacion_nueva));

    do_action('ifd_wcr_added_score', $puntuacion_nueva, $user_id);
}, 10, 2);



add_action('ifd_wcr_added_score', function($puntuacion_nueva, $user_id ){


    $user = get_user_by('ID', $user_id);
    if(!$user):

        return;
    endif;

    $user_email = $user->user_email;

    
    $message = sprintf(__('¿Sabes qué? Alguien acaba de usar tu cupón, lo que hace que tengas un total de %s', 'ifd-wcr' ), $puntuacion_nueva);
    wp_mail($user_email, __('Alguien ha usado tu cupón', 'ifd-wcr'), $message);

}, 10, 2);


//Tareas:

//TODO: Hook cuando se eliminar un cupon eliminar de los usuarios

//TODO: Mostrar el cupon en el panel de usuario

//TODO: Mostrar el cupon en un shortcode

//TODO: Enviar un email cuando un nuevo cupon se ha creado

//TODO: Que el valor de la recompensa se multiplique por las veces que se ha utilizado el cupón