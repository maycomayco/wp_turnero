<?php
namespace Bookly\Lib;

abstract class API
{
    const API_URL = 'https://api.booking-wp-plugin.com';

    /**
     * Verify envato.com Purchase Code
     *
     * @param string      $purchase_code
     * @param Base\Plugin $plugin_class
     * @param int|null    $blog_id
     * @return array
     */
    public static function verifyPurchaseCode( $purchase_code, $plugin_class, $blog_id = null )
    {
        $url = add_query_arg(
            array(
                'purchase_code' => $purchase_code,
                'site_url'      => get_site_url( $blog_id ),
            ),
            self::API_URL . '/1.0/plugins/' . $plugin_class::getSlug() . '/purchase-code'
        );
        $response = wp_remote_get( $url, array(
            'timeout' => 25,
        ) );
        if ( $response instanceof \WP_Error ) {

        } elseif ( isset( $response['body'] ) ) {
            $json = json_decode( $response['body'], true );
            if ( isset( $json['success'] ) ) {
                if ( (bool) $json['success'] ) {
                    return array( 'valid' => true, );
                } else {
                    if ( isset ( $json['error'] ) ) {
                        switch ( $json['error'] ) {
                            case 'already_in_use':
                                return array(
                                    'valid' => false,
                                    'error' => sprintf(
                                        __( '%s is used on another domain %s.<br/>In order to use the purchase code on this domain, please dissociate it in the admin panel of the other domain.<br/>If you do not have access to the admin area, please contact our technical support at support@ladela.com to transfer the license manually.', 'bookly' ),
                                        $purchase_code,
                                        isset ( $json['data'] ) ? implode( ', ', $json['data'] ) : ''
                                    ),
                                );
                            case 'connection':
                                // ... Please try again later.
                                break;
                            case 'invalid':
                            default:
                                return array(
                                    'valid' => false,
                                    'error' => sprintf(
                                        __( '%s is not a valid purchase code for %s.', 'bookly' ),
                                        $purchase_code,
                                        $plugin_class::getTitle()
                                    ),
                                );
                        }
                    }
                }
            }
        }

        return array(
            'valid' => false,
            'error' => __( 'Purchase code verification is temporarily unavailable. Please try again later.', 'bookly' )
        );
    }

    /**
     * Detach purchase code from current domain.
     *
     * @param Base\Plugin $plugin_class
     * @param int|null    $blog_id
     * @return bool
     */
    public static function detachPurchaseCode( $plugin_class, $blog_id = null )
    {
        $url = add_query_arg(
            array(
                'site_url' => get_site_url( $blog_id ),
            ),
            self::API_URL . '/1.0/purchase-code/' . $plugin_class::getPurchaseCode()
        );

        $response = wp_remote_request( $url, array(
            'method' => 'DELETE',
            'timeout' => 25,
        ) );

        if ( $response instanceof \WP_Error ) {

        } else if ( isset( $response['body'] ) ) {
            $json = json_decode( $response['body'], true );
            if ( isset ( $json['success'] ) && $json['success'] ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Register subscriber.
     *
     * @param string $email
     * @return bool
     */
    public static function registerSubscriber( $email )
    {
        $response = wp_remote_post( self::API_URL . '/1.0/subscribers', array(
            'timeout' => 25,
            'body'    => array(
                'email'    => $email,
                'site_url' => site_url(),
            ),
        ) );
        if ( $response instanceof \WP_Error ) {

        } elseif ( isset( $response['body'] ) ) {
            $json = json_decode( $response['body'], true );
            if ( isset ( $json['success'] ) && $json['success'] ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Send Net Promoter Score.
     *
     * @param integer $rate
     * @param string  $msg
     * @param string  $email
     * @return bool
     */
    public static function sendNps( $rate, $msg, $email )
    {
        $response = wp_remote_post( self::API_URL . '/1.0/nps', array(
            'timeout' => 25,
            'body'    => array(
                'rate'     => $rate,
                'msg'      => $msg,
                'email'    => $email,
                'site_url' => site_url(),
            ),
        ) );
        if ( $response instanceof \WP_Error ) {

        } elseif ( isset( $response['body'] ) ) {
            $json = json_decode( $response['body'], true );
            if ( isset ( $json['success'] ) && $json['success'] ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $message_ids
     */
    public static function seenMessages( array $message_ids )
    {
        if ( $message_ids ) {
            wp_remote_post( self::API_URL . '/1.0/messages/seen', array(
                'timeout' => 25,
                'body'    => array(
                    'site_url'    => site_url(),
                    'message_ids' => $message_ids,
                ),
            ) );

            Entities\Message::query()->update()->set( 'seen', 1 )->whereIn( 'message_id', $message_ids )->execute();
        }
    }

    /**
     * Send statistics data.
     */
    public static function sendStats()
    {
        /** @global \wpdb */
        global $wpdb;

        $today      = substr( current_time( 'mysql' ), 0, 10 );
        $ago_10days = date_create( $today )->modify( '-10 days' )->format( 'Y-m-d H:i:s' );
        $ago_30days = date_create( $today )->modify( '-30 days' )->format( 'Y-m-d H:i:s' );

        // Staff members.
        $staff = array( 'total' => 0, 'admins' => 0, 'non_admins' => 0 );
        /** @var \Bookly\Lib\Entities\Staff $staff_member */
        foreach ( Entities\Staff::query()->find() as $staff_member ) {
            ++ $staff['total'];
            $wp_user_id = $staff_member->getWpUserId();
            if ( $wp_user_id && $user = get_user_by( 'id', $wp_user_id ) ) {
                if ( $user->has_cap( 'manage_options' ) ) {
                    ++ $staff['admins'];
                } else {
                    ++ $staff['non_admins'];
                }
            }
        }

        // Services.
        $services = array();

        $services['visible_simple'] = Entities\Service::query( 's' )
            ->where( 's.type', Entities\Service::TYPE_SIMPLE )
            ->whereNot( 's.visibility', 'private' )
            ->count();

        // Max duration.
        $row = Entities\Service::query()->select( 'MAX(duration) AS max_duration' )->fetchRow();
        $services['max_duration'] = $row['max_duration'];

        // Max capacity.
        $row = Entities\Service::query( 's' )
            ->select( 'MAX(ss.capacity_max) AS max_capacity' )
            ->innerJoin( 'StaffService', 'ss', 'ss.service_id = s.id' )
            ->where( 's.type', Entities\Service::TYPE_SIMPLE )
            ->whereNot( 's.visibility', 'private' )
            ->fetchRow();
        $services['max_capacity'] = $row['max_capacity'];

        // Services list.
        $rows = Entities\Service::query()->select( 'id, title' )->fetchArray();
        $services['services'] = $rows;

        // StaffServices.
        $staff_services = array(
            'total' => Entities\StaffService::query()->count(),
        );

        // Find active customers.
        $sql = $wpdb->prepare( '
             SELECT COUNT(customer_id) AS active_customers
               FROM ( SELECT DISTINCT(customer_id)
                        FROM `' . Entities\CustomerAppointment::getTableName() . '`
                       WHERE created >= %s
                     ) AS active',
            $ago_30days );
        $active_clients = (int) $wpdb->get_var( $sql );

        // Payments completed.
        $completed_payments = Entities\Payment::query()
              ->whereGt( 'created', $ago_30days )
              ->where( 'status', Entities\Payment::STATUS_COMPLETED )
              ->count();

        // Extras quantity.
        $extras_quantity = Config::serviceExtrasEnabled() ? count( Proxy\ServiceExtras::findAll() ) : null;

        // Cart Enabled.
        $cart_enabled = get_option( 'bookly_cart_enabled' ) == 1 && ! Config::wooCommerceEnabled();

        // History Data.
        $history = array();

        $history_schema = array( 'bookings_from_frontend' => 0, 'bookings_from_backend' => 0 );

        if ( Config::payLocallyEnabled() ) {
            $history_schema[ 'bookings_payment_' . Entities\Payment::TYPE_LOCAL ] = 0;
        }
        if ( Config::paypalEnabled() ) {
            $history_schema[ 'bookings_payment_' . Entities\Payment::TYPE_PAYPAL ] = 0;
        }
        if ( Config::stripeEnabled() ) {
            $history_schema[ 'bookings_payment_' . Entities\Payment::TYPE_STRIPE ] = 0;
        }
        if ( Config::twoCheckoutEnabled() ) {
            $history_schema[ 'bookings_payment_' . Entities\Payment::TYPE_2CHECKOUT ] = 0;
        }
        if ( Config::authorizeNetEnabled() ) {
            $history_schema[ 'bookings_payment_' . Entities\Payment::TYPE_AUTHORIZENET ] = 0;
        }
        if ( Config::paysonEnabled() ) {
            $history_schema[ 'bookings_payment_' . Entities\Payment::TYPE_PAYSON ] = 0;
        }
        if ( Config::payuLatamEnabled() ) {
            $history_schema[ 'bookings_payment_' . Entities\Payment::TYPE_PAYULATAM ] = 0;
        }

        $history_schema[ 'bookings_payment_' . Entities\Payment::TYPE_COUPON ] = Config::couponsActive() ? 0 : null;
        $history_schema[ 'bookings_payment_' . Entities\Payment::TYPE_WOOCOMMERCE ] = get_option( 'bookly_wc_enabled' ) ? 0 : null;

        if ( Config::serviceExtrasEnabled() ) {
            $history_schema['bookings_with_extras']    = 0;
            $history_schema['bookings_without_extras'] = 0;
        }

        if ( Config::couponsEnabled() ) {
            $history_schema['bookings_with_coupon']    = 0;
            $history_schema['bookings_without_coupon'] = 0;
        }

        if ( Config::recurringAppointmentsEnabled() ) {
            $history_schema['bookings_in_series']     = 0;
            $history_schema['bookings_not_in_series'] = 0;
        }

        if ( Config::depositPaymentsEnabled() ) {
            $history_schema['paid_deposit'] = 0;
            $history_schema['paid_in_full'] = 0;
            if ( $staff_services['total'] != 0 ) {
                $staff_services['deposit'] = round( Entities\StaffService::query( 'ss' )->whereNot( 'ss.deposit', '100%' )->whereRaw( 'ss.deposit != ss.price', array() )->count() / $staff_services['total'] * 100 );
            }
        }

        if ( Config::specialDaysEnabled() ) {
            $history_schema['special_days_changed'] = 0;
        }

        $period = new \DatePeriod( date_create( $ago_10days ), new \DateInterval( 'P1D' ), date_create( $today ) );

        foreach ( $period as $date ) {
            $history[ $date->format( 'Y-m-d' ) ] = $history_schema;
        }

        // Bookings With Coupons.
        if ( Config::couponsEnabled() ) {
            $rows = Entities\CustomerAppointment::query( 'ca' )
                ->select( 'p.details, DATE_FORMAT(ca.created, \'%%Y-%%m-%%d\') AS cur_date' )
                ->innerJoin( 'Payment', 'p', 'ca.payment_id = p.id' )
                ->whereGte( 'created', $ago_10days )
                ->whereLt( 'created', $today )
                ->fetchArray();

            foreach ( $rows as $record ) {
                $details = json_decode( $record['details'], true );
                if ( $details['coupon'] ) {
                    $history[ $record['cur_date'] ]['bookings_with_coupon'] ++;
                } else {
                    $history[ $record['cur_date'] ]['bookings_without_coupon'] ++;
                }
            }
        }

        // Bookings Payment Methods.
        $rows = Entities\CustomerAppointment::query( 'ca' )
            ->select( 'COUNT(*) AS quantity, p.type, DATE_FORMAT(ca.created, \'%%Y-%%m-%%d\') AS cur_date' )
            ->innerJoin( 'Payment', 'p', 'ca.payment_id = p.id' )
            ->whereGte( 'created', $ago_10days )
            ->whereLt( 'created', $today )
            ->groupBy( 'p.type, cur_date' )
            ->fetchArray();

        foreach ( $rows as $record ) {
            $history[ $record['cur_date'] ][ 'bookings_payment_' . $record['type'] ] = (int) $record['quantity'];
        }

        // Bookings in Series.
        if ( Config::recurringAppointmentsEnabled() ) {
            $rows = Entities\CustomerAppointment::query( 'ca' )
                ->select( 'ap.series_id, IF(ap.series_id IS NULL, COUNT(*), 1) AS in_series, DATE_FORMAT(created, \'%%Y-%%m-%%d\') AS cur_date' )
                ->innerJoin( 'Appointment', 'ap', 'ca.appointment_id = ap.id' )
                ->whereGte( 'created', date_create( current_time( 'mysql' ) )->modify( '-10 days' )->format( 'Y-m-d' ) )
                ->whereLt( 'created', date_create( current_time( 'mysql' ) )->format( 'Y-m-d' ) )
                ->groupBy( 'ap.series_id, cur_date' )
                ->fetchArray();

            foreach ( $rows as $record ) {
                if ( $record['series_id'] == null ) {
                    $history[ $record['cur_date'] ]['bookings_not_in_series'] = (int) $record['in_series'];
                } else {
                    $history[ $record['cur_date'] ]['bookings_in_series'] += 1;
                }
            }
        }

        // Frontend/Backend Bookings.
        $rows = Entities\CustomerAppointment::query()
            ->select( 'COUNT(*) AS quantity, created_from, DATE_FORMAT(created, \'%%Y-%%m-%%d\') AS cur_date' )
            ->whereGte( 'created', $ago_10days )
            ->whereLt( 'created',  $today )
            ->groupBy( 'created_from, cur_date' )
            ->fetchArray();

        foreach ( $rows as $record ) {
            $history[ $record['cur_date'] ][ 'bookings_from_' . $record['created_from'] ] = (int) $record['quantity'];
        }

        // Statistic
        $rows = Entities\Stat::query( 's' )
            ->select( 'DATE_FORMAT(created, \'%%Y-%%m-%%d\') AS created, `name`, `value`' )
            ->whereGte( 'created', $ago_10days )
            ->whereLt( 'created',  $today )
            ->fetchArray();
        foreach ( $rows as $record ) {
            $history[ $record['created'] ][ $record['name'] ] = $record['value'];
        }

        // Deposits Payments.
        if ( Config::depositPaymentsActive() ) {
            $rows = Entities\Payment::query()
                ->select( 'COUNT(*) AS quantity, paid_type, DATE_FORMAT(created, \'%%Y-%%m-%%d\') AS cur_date' )
                ->whereGte( 'created', $ago_10days )
                ->whereLt( 'created', $today )
                ->groupBy( 'paid_type, cur_date' )
                ->fetchArray();

            foreach ( $rows as $record ) {
                $history[ $record['cur_date'] ][ 'paid_' . $record['paid_type'] ] = (int) $record['quantity'];
            }
        }

        // Bookings with Extras.
        if ( Config::serviceExtrasEnabled() ) {
            $rows = Entities\CustomerAppointment::query()
                ->select( 'COUNT(*) AS quantity, IF(extras=\'[]\', 0, 1) AS with_extras, DATE_FORMAT(created, \'%%Y-%%m-%%d\') AS cur_date' )
                ->whereGte( 'created', $ago_10days )
                ->whereLt( 'created',  $today )
                ->groupBy( 'with_extras, cur_date' )
                ->fetchArray();

            foreach ( $rows as $record ) {
                if ( $record['with_extras'] == 1 ) {
                    $history[ $record['cur_date'] ]['bookings_with_extras'] = (int) $record['quantity'];
                } else {
                    $history[ $record['cur_date'] ]['bookings_without_extras'] = (int) $record['quantity'];
                }
            }
        }

        // Send request.
        wp_remote_post( self::API_URL . '/1.4/stats', array(
            'timeout' => 25,
            'body'    => array(
                'site_url'           => site_url(),
                'active_clients'     => $active_clients,
                'admin_language'     => get_option( 'bookly_admin_preferred_language' ),
                'wp_locale'          => get_locale(),
                'company'            => get_option( 'bookly_co_name' ),
                'completed_payments' => $completed_payments,
                'custom_fields_count' => count( (array) Proxy\CustomFields::getAll() ),
                'description'        => get_bloginfo( 'description' ),
                'extras_quantity'    => $extras_quantity,
                'cart_enabled'       => $cart_enabled,
                'history'            => $history,
                'php'                => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION,
                'services'           => $services,
                'staff'              => $staff,
                'staff_services'     => $staff_services,
                'title'              => get_bloginfo( 'name' ),
            ),
        ) );
    }
}