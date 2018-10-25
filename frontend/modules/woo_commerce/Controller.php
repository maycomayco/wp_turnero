<?php
namespace Bookly\Frontend\Modules\WooCommerce;

use Bookly\Lib;
use Bookly\Frontend\Modules\Booking\Lib\Errors;

/**
 * Class Controller
 * @package Bookly\Frontend\Modules\WooCommerce
 */
class Controller extends Lib\Base\Controller
{
    const VERSION = '1.0';

    private $product_id = 0;
    private $checkout_info = array();

    protected function getPermissions()
    {
        return array( '_this' => 'anonymous', );
    }

    public function __construct()
    {
        if ( get_option( 'bookly_wc_enabled' ) ) {
            $this->product_id = get_option( 'bookly_wc_product', 0 );
            // WC 3.0 woocommerce_add_order_item_meta is deprecated but
            // new action wc_add_order_item_meta is missing.
            add_action( 'woocommerce_add_order_item_meta',      array( $this, 'addOrderItemMeta' ), 10, 3 );
            // WC 3.1.1 missing registration wc_add_order_item_meta
            // add_action( 'wc_add_order_item_meta',            array( $this, 'addOrderItemMeta' ), 10, 3 );
            add_action( 'woocommerce_after_order_itemmeta',     array( $this, 'orderItemMeta' ), 10, 1 );
            add_action( 'woocommerce_before_calculate_totals',  array( $this, 'beforeCalculateTotals' ), 10, 1 );
            add_action( 'woocommerce_before_cart_contents',     array( $this, 'checkAvailableTimeForCart' ), 10, 0 );
            add_action( 'woocommerce_order_item_meta_end',      array( $this, 'orderItemMeta' ), 10, 1 );
            add_action( 'woocommerce_order_status_cancelled',   array( $this, 'cancelOrder' ), 10, 1 );
            add_action( 'woocommerce_order_status_completed',   array( $this, 'paymentComplete' ), 10, 1 );
            add_action( 'woocommerce_order_status_on-hold',     array( $this, 'paymentComplete' ), 10, 1 );
            add_action( 'woocommerce_order_status_processing',  array( $this, 'paymentComplete' ), 10, 1 );
            add_action( 'woocommerce_order_status_refunded',    array( $this, 'cancelOrder' ), 10, 1 );

            add_filter( 'woocommerce_checkout_get_value',       array( $this, 'checkoutValue' ), 10, 2 );
            add_filter( 'woocommerce_get_item_data',            array( $this, 'getItemData' ), 10, 2 );
            add_filter( 'woocommerce_quantity_input_args',      array( $this, 'quantityArgs' ), 10, 2 );
            add_filter( 'woocommerce_cart_item_price',          array( $this, 'getCartItemPrice' ), 10, 3 );

            parent::__construct();
        }
    }

    /**
     * Verifies the availability of all appointments that are in the cart
     */
    public function checkAvailableTimeForCart()
    {
        $recalculate_totals = false;
        foreach ( WC()->cart->get_cart() as $wc_key => $wc_item ) {
            if ( array_key_exists( 'bookly', $wc_item ) ) {
                if ( ! isset( $wc_item['bookly']['version'] ) ) {
                    if ( $this->_migration( $wc_key, $wc_item ) === false ) {
                        // Removed item from cart.
                        continue;
                    }
                }
                $userData = new Lib\UserBookingData( null );
                $userData->fillData( $wc_item['bookly'] );
                $userData->cart->setItemsData( $wc_item['bookly']['items'] );
                if ( $wc_item['quantity'] > 1 ) {
                    foreach ( $userData->cart->getItems() as $cart_item ) {
                        // Equal appointments increase quantity
                        $cart_item->setNumberOfPersons( $cart_item->getNumberOfPersons() * $wc_item['quantity'] );
                    }
                }
                // Check if appointment's time is still available
                $failed_cart_key = $userData->cart->getFailedKey();
                if ( $failed_cart_key !== null ) {
                    $cart_item = $userData->cart->get( $failed_cart_key );
                    $slot = $cart_item->getSlots();
                    $notice = strtr( __( 'Sorry, the time slot %date_time% for %service% has been already occupied.', 'bookly' ),
                        array(
                            '%service%'   => '<strong>' . $cart_item->getService()->getTranslatedTitle() . '</strong>',
                            '%date_time%' => Lib\Utils\DateTime::formatDateTime( $slot[0][2] )
                    ) );
                    wc_print_notice( $notice, 'notice' );
                    WC()->cart->set_quantity( $wc_key, 0, false );
                    $recalculate_totals = true;
                }
            }
        }
        if ( $recalculate_totals ) {
            WC()->cart->calculate_totals();
        }
    }

    /**
     * Assign checkout value from appointment.
     *
     * @param $null
     * @param $field_name
     * @return string|null
     */
    public function checkoutValue( $null, $field_name )
    {
        if ( empty( $this->checkout_info ) ) {
            foreach ( WC()->cart->get_cart() as $wc_key => $wc_item ) {
                if ( array_key_exists( 'bookly', $wc_item ) ) {
                    if ( ! isset( $wc_item['bookly']['version'] ) || $wc_item['bookly']['version'] < self::VERSION ) {
                        if ( $this->_migration( $wc_key, $wc_item ) === false ) {
                            // Removed item from cart.
                            continue;
                        }
                    }
                    $this->checkout_info = array(
                        'billing_first_name' => $wc_item['bookly']['first_name'],
                        'billing_last_name'  => $wc_item['bookly']['last_name'],
                        'billing_email'      => $wc_item['bookly']['email'],
                        'billing_phone'      => $wc_item['bookly']['phone']
                    );
                    break;
                }
            }
        }
        if ( array_key_exists( $field_name, $this->checkout_info ) ) {
            return $this->checkout_info[ $field_name ];
        }

        return null;
    }

    /**
     * Do bookings after checkout.
     *
     * @param $order_id
     */
    public function paymentComplete( $order_id )
    {
        $order = new \WC_Order( $order_id );
        foreach ( $order->get_items() as $item_id => $order_item ) {
            $data = wc_get_order_item_meta( $item_id, 'bookly' );
            if ( $data && ! isset ( $data['processed'] ) ) {
                $userData = new Lib\UserBookingData( null );
                $userData->fillData( $data );
                $userData->cart->setItemsData( $data['items'] );
                if ( $order_item['qty'] > 1 ) {
                    foreach ( $userData->cart->getItems() as $cart_item ) {
                        $cart_item->setNumberOfPersons( $cart_item->getNumberOfPersons() * $order_item['qty'] );
                    }
                }
                list( $total, $deposit ) = $userData->cart->getInfo();
                $payment = new Lib\Entities\Payment();
                $payment
                    ->setType( Lib\Entities\Payment::TYPE_WOOCOMMERCE )
                    ->setStatus( Lib\Entities\Payment::STATUS_COMPLETED )
                    ->setTotal( $total )
                    ->setPaid( $deposit )
                    ->setCreated( current_time( 'mysql' ) )
                    ->save();
                $order = $userData->save( $payment );
                $payment->setDetailsFromOrder( $order )->save();
                if ( get_option( 'bookly_cst_create_account' ) && $order->getCustomer()->getWpUserId() ) {
                    update_post_meta( $order_id, '_customer_user', $order->getCustomer()->getWpUserId() );
                }
                // Mark item as processed.
                $data['processed'] = true;
                $data['ca_list']   = array();
                foreach ( $order->getFlatItems() as $item ) {
                    $data['ca_list'][] = $item->getCA()->getId();
                }
                wc_update_order_item_meta( $item_id, 'bookly', $data );
                Lib\NotificationSender::sendFromCart( $order );
            }
        }
    }

    /**
     * Cancel appointments on WC order cancelled.
     *
     * @param $order_id
     */
    public function cancelOrder( $order_id )
    {
        $order = new \WC_Order( $order_id );
        foreach ( $order->get_items() as $item_id => $order_item ) {
            $data = wc_get_order_item_meta( $item_id, 'bookly' );
            if ( isset ( $data['processed'], $data['ca_ids'] ) && $data['processed'] ) {
                /** @var Lib\Entities\CustomerAppointment[] $ca_list */
                $ca_list = Lib\Entities\CustomerAppointment::query()->whereIn( 'id', $data['ca_ids'] )->find();
                foreach ( $ca_list as $ca ) {
                    $ca->cancel();
                }
                $data['ca_ids'] = array();
                wc_update_order_item_meta( $item_id, 'bookly', $data );
            }
        }
    }

    /**
     * Change attr for WC quantity input
     *
     * @param array       $args
     * @param \WC_Product $product
     * @return mixed
     */
    public function quantityArgs( $args, $product )
    {
        if ( $product->get_id() == $this->product_id ) {
            $args['max_value'] = $args['input_value'];
            $args['min_value'] = $args['input_value'];
        }

        return $args;
    }

    /**
     * Change item price in cart.
     *
     * @param \WC_Cart $cart_object
     */
    public function beforeCalculateTotals( $cart_object )
    {
        foreach ( $cart_object->cart_contents as $wc_key => $wc_item ) {
            if ( isset ( $wc_item['bookly'] ) ) {
                if ( ! isset( $wc_item['bookly']['version'] ) || $wc_item['bookly']['version'] < self::VERSION ) {
                    if ( $this->_migration( $wc_key, $wc_item ) === false ) {
                        // Removed item from cart.
                        continue;
                    }
                }
                $userData = new Lib\UserBookingData( null );
                $userData->fillData( $wc_item['bookly'] );
                $userData->cart->setItemsData( $wc_item['bookly']['items'] );
                list( , $deposit ) = $userData->cart->getInfo();
                /** @var \WC_Product $wc_item['data'] */
                $wc_item['data']->set_price( $deposit );
            }
        }
    }

    public function addOrderItemMeta( $item_id, $values, $wc_key )
    {
        if ( isset ( $values['bookly'] ) ) {
            wc_update_order_item_meta( $item_id, 'bookly', $values['bookly'] );
        }
    }

    /**
     * Get item data for cart.
     *
     * @param $other_data
     * @param $wc_item
     * @return array
     */
    public function getItemData( $other_data, $wc_item )
    {
        if ( isset ( $wc_item['bookly'] ) ) {
            $userData = new Lib\UserBookingData( null );
            $info = array();
            if ( isset ( $wc_item['bookly']['version'] ) && $wc_item['bookly']['version'] == self::VERSION ) {
                $userData->fillData( $wc_item['bookly'] );
                if ( Lib\Config::useClientTimeZone() ) {
                    $userData->applyTimeZone();
                }
                $userData->cart->setItemsData( $wc_item['bookly']['items'] );
                list ( , $deposit, $due ) = $userData->cart->getInfo();
                foreach ( $userData->cart->getItems() as $cart_item ) {
                    $slots     = $cart_item->getSlots();
                    $client_dp = Lib\Slots\DatePoint::fromStr( $slots[0][2] )->toClientTz();
                    $service   = $cart_item->getService();
                    $staff     = $cart_item->getStaff();
                    $codes = array(
                        '{amount_due}'        => Lib\Utils\Price::format( $due ),
                        '{amount_to_pay}'     => Lib\Utils\Price::format( $deposit ),
                        '{appointment_date}'  => $client_dp->formatI18nDate(),
                        '{appointment_time}'  => $client_dp->formatI18nTime(),
                        '{category_name}'     => $service ? $service->getTranslatedCategoryName() : '',
                        '{number_of_persons}' => $cart_item->getNumberOfPersons(),
                        '{service_info}'      => $service ? $service->getTranslatedInfo() : '',
                        '{service_name}'      => $service ? $service->getTranslatedTitle() : __( 'Service was not found', 'bookly' ),
                        '{service_price}'     => $service ? Lib\Utils\Price::format( $cart_item->getServicePrice() ) : '',
                        '{staff_info}'        => $staff ? $staff->getTranslatedInfo() : '',
                        '{staff_name}'        => $staff ? $staff->getTranslatedName() : '',
                    );
                    $data  = Lib\Proxy\Shared::prepareCartItemInfoText( array(), $cart_item );
                    $codes = Lib\Proxy\Shared::prepareInfoTextCodes( $codes, $data );
                    // Support deprecated codes [[CODE]]
                    foreach ( array_keys( $codes ) as $code_key ) {
                        if ( $code_key{1} == '[' ) {
                            $codes[ '{' . strtolower( substr( $code_key, 2, -2 ) ) . '}' ] = $codes[ $code_key ];
                        } else {
                            $codes[ '[[' . strtoupper( substr( $code_key, 1, -1 ) ) . ']]' ] = $codes[ $code_key ];
                        }
                    }
                    $info[]  = strtr( Lib\Utils\Common::getTranslatedOption( 'bookly_l10n_wc_cart_info_value' ), $codes );
                }
            }
            $other_data[] = array( 'name' => Lib\Utils\Common::getTranslatedOption( 'bookly_l10n_wc_cart_info_name' ), 'value' => implode( PHP_EOL . PHP_EOL, $info ) );
        }

        return $other_data;
    }

    /**
     * Print appointment details inside order items in the backend.
     *
     * @param int $item_id
     */
    public function orderItemMeta( $item_id )
    {
        $data = wc_get_order_item_meta( $item_id, 'bookly' );
        if ( $data ) {
            $other_data = $this->getItemData( array(), array( 'bookly' => $data ) );
            echo '<br/>' . $other_data[0]['name'] . '<br/>' . nl2br( $other_data[0]['value'] );
        }
    }

    /**
     * Add product to cart
     *
     * return string JSON
     */
    public function executeAddToWoocommerceCart()
    {
        if ( ! get_option( 'bookly_wc_enabled' ) ) {
            exit;
        }
        $response = null;
        $userData = new Lib\UserBookingData( $this->getParameter( 'form_id' ) );

        if ( $userData->load() ) {
            $session = WC()->session;
            /** @var \WC_Session_Handler $session */
            if ( $session instanceof \WC_Session_Handler && $session->get_session_cookie() === false ) {
                $session->set_customer_session_cookie( true );
            }
            if ( $userData->cart->getFailedKey() === null ) {
                $cart_item  = $this->getIntersectedItem( $userData->cart->getItems() );
                if ( $cart_item === null ) {
                    $first_name = $userData->getFirstName();
                    $last_name  = $userData->getLastName();
                    $full_name  = $userData->getFullName();
                    // Check if defined First name
                    if ( ! $first_name ) {
                        $first_name = strtok( $full_name, ' ' );
                        $last_name  = strtok( '' );
                    }
                    $bookly = array(
                        'version'           => self::VERSION,
                        'email'             => $userData->getEmail(),
                        'items'             => $userData->cart->getItemsData(),
                        'full_name'         => $full_name,
                        'first_name'        => $first_name,
                        'last_name'         => $last_name,
                        'phone'             => $userData->getPhone(),
                        'time_zone_offset'  => $userData->getTimeZoneOffset(),
                    );

                    // Qnt 1 product in $userData exists value with number_of_persons
                    WC()->cart->add_to_cart( $this->product_id, 1, '', array(), array( 'bookly' => $bookly ) );

                    $response = array( 'success' => true );
                } else {
                    $response = array( 'success' => false, 'error' => Errors::CART_ITEM_NOT_AVAILABLE );
                }
            } else {
                $response = array( 'success' => false, 'error' => Errors::CART_ITEM_NOT_AVAILABLE );
            }
        } else {
            $response = array( 'success' => false, 'error' => Errors::SESSION_ERROR );
        }
        wp_send_json( $response );
    }

    /**
     * Find conflicted CartItem with items in WC Cart.
     *
     * Resolved:
     *  number of persons > staff.capacity
     *  Services for some Staff intersected
     *
     * @param Lib\CartItem[] $new_items
     * @return Lib\CartItem
     */
    private function getIntersectedItem( array $new_items )
    {
        /** @var Lib\CartItem[] $wc_items */
        $wc_items  = array();
        $cart_item = new Lib\CartItem();
        foreach ( WC()->cart->get_cart() as $wc_key => $wc_item ) {
            if ( array_key_exists( 'bookly', $wc_item ) ) {
                if ( ! isset( $wc_item['bookly']['version'] ) ) {
                    if ( $this->_migration( $wc_key, $wc_item ) === false ) {
                        // Removed item from cart.
                        continue;
                    }
                }
                foreach ( $wc_item['bookly']['items'] as $item_data ) {
                    $entity = clone $cart_item;
                    $entity->setData( $item_data );
                    if ( $wc_item['quantity'] > 1 ) {
                        $nop = $item_data['number_of_persons'] *= $wc_item['quantity'];
                        $entity->setNumberOfPersons( $nop );
                    }
                    $wc_items[] = $entity;
                }
            }
        }
        $staff_service = array();
        foreach ( $new_items as $cart_key => $candidate_cart_item ) {
            foreach ( $wc_items as $wc_cart_item ) {
                $candidate_staff_id   = $candidate_cart_item->getStaff()->getId();
                $candidate_service_id = $candidate_cart_item->getService()->getId();
                $candidate_slots      = $candidate_cart_item->getSlots();
                $wc_cart_slots        = $wc_cart_item->getSlots();
                if ( $wc_cart_item->getStaff() && $candidate_cart_item->getService() ) {
                    if ( $candidate_staff_id == $wc_cart_item->getStaff()->getId() ) {
                        // Equal Staff
                        $candidate_start = date_create( $candidate_slots[0][2] );
                        $candidate_end   = date_create( $candidate_slots[0][2] )->modify( ( $candidate_cart_item->getService()->getDuration() + $candidate_cart_item->getExtrasDuration() ) . ' sec' );
                        $wc_cart_start   = date_create( $wc_cart_slots[0][2] );
                        $wc_cart_end     = date_create( $wc_cart_slots[0][2] )->modify( ( $wc_cart_item->getService()->getDuration() + $wc_cart_item->getExtrasDuration() ) . ' sec' );
                        if ( ( $wc_cart_end > $candidate_start ) && ( $candidate_end > $wc_cart_start ) ) {
                            // Services intersected.
                            if ( $candidate_start == $wc_cart_start ) {
                                // Equal Staff/Service/Start
                                if ( ! isset( $staff_service[ $candidate_staff_id ][ $candidate_service_id ] ) ) {
                                    $staff_service[ $candidate_staff_id ][ $candidate_service_id ] = $this->getCapacity( $candidate_staff_id, $candidate_service_id );
                                }
                                $allow_capacity = $staff_service[ $candidate_staff_id ][ $candidate_service_id ];
                                $nop = $candidate_cart_item->getNumberOfPersons() + $wc_cart_item->getNumberOfPersons();
                                if ( $nop > $allow_capacity ) {
                                    // Equal Staff/Service/Start and number_of_persons > capacity
                                    return $candidate_cart_item;
                                }
                            } else {
                                // Intersect Services for some Staff
                                return $candidate_cart_item;
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param int $staff_id
     * @param int $service_id
     * @return int
     */
    private function getCapacity( $staff_id, $service_id )
    {
        $staff_service = Lib\Entities\StaffService::query()
            ->select( 'capacity_max' )
            ->where( 'staff_id',   $staff_id )
            ->where( 'service_id', $service_id )
            ->fetchRow();

        return $staff_service['capacity_max'];
    }

    /**
     * Migration deprecated cart items.
     *
     * @param $wc_key
     * @param $data
     * @return bool
     */
    private function _migration( $wc_key, $data )
    {
        // The current implementation only remove cart items with deprecated format.
        WC()->cart->set_quantity( $wc_key, 0, false );
        WC()->cart->calculate_totals();

        return false;
    }

    /**
     * Override parent method to register 'wp_ajax_nopriv_' actions too.
     *
     * @param bool $with_nopriv
     */
    protected function registerWpAjaxActions( $with_nopriv = false )
    {
        parent::registerWpAjaxActions( true );
    }


    /**
     * @param $product_price
     * @param $wc_item
     * @param $cart_item_key
     * @return mixed
     */
    public function getCartItemPrice( $product_price, $wc_item, $cart_item_key )
    {
        if ( isset ( $wc_item['bookly'] ) ) {
            $userData = new Lib\UserBookingData( null );
            $userData->fillData( $wc_item['bookly'] );
            $userData->cart->setItemsData( $wc_item['bookly']['items'] );
            list( , $deposit ) = $userData->cart->getInfo();
            $product_price = $deposit;
        }

        return $product_price;
    }

}