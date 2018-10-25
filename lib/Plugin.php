<?php
namespace Bookly\Lib;

use Bookly\Backend\Modules;

/**
 * Class Plugin
 * @package Bookly\Lib
 */
abstract class Plugin extends Base\Plugin
{
    protected static $prefix = 'bookly_';
    protected static $title;
    protected static $version;
    protected static $slug;
    protected static $directory;
    protected static $main_file;
    protected static $basename;
    protected static $text_domain;
    protected static $root_namespace;
    protected static $embedded;

    public static function registerHooks()
    {
        parent::registerHooks();

        if ( is_admin() ) {
            add_action( 'admin_notices', function () {
                $bookly_page = isset( $_REQUEST['page'] ) && strpos( $_REQUEST['page'], 'bookly-' ) === 0;
                if ( $bookly_page ) {
                    // Subscribe notice.
                    Modules\Support\Components::getInstance()->renderSubscribeNotice();
                    // NPS notice.
                    Modules\Support\Components::getInstance()->renderNpsNotice();
                    // Collect stats notice.
                    Modules\Settings\Components::getInstance()->renderCollectStatsNotice();
                    Modules\License\Components::getInstance()->renderPurchaseNotice();

                    if ( Config::booklyExpired() || get_option( 'bookly_grace_hide_admin_notice_time' ) < time() ) {
                        Modules\License\Components::getInstance()->renderLicenseRequired();
                    }
                }
                Modules\License\Components::getInstance()->renderLicenseNotice( $bookly_page );
            }, 10, 0 );
        }

        if ( Config::paypalEnabled() ) {
            add_filter( 'bookly_apply_gateway_price_correction', function ( array $cart_info, $gateway ) {
                if ( $gateway === Entities\Payment::TYPE_PAYPAL ) {
                    $deposit          = $cart_info[1];
                    $cart_info[1]     = Utils\Price::correction( $cart_info[1], - (float) get_option( 'bookly_paypal_increase' ), - (float) get_option( 'bookly_paypal_addition' ) );
                    $price_correction = $cart_info[1] - $deposit;
                    $cart_info[0]    += $price_correction;
                }

                return $cart_info;
            }, 10, 2 );
        }

        add_filter( 'puc_request_info_result-' . Plugin::getSlug(), function ( $pluginInfo, $result ) {
            if ( $result instanceof \WP_Error ) {

            } elseif ( isset( $result['body'] ) ) {
                $response = json_decode( $result['body'], true );
                if ( isset( $response['messages'] ) ) {
                    foreach ( $response['messages'] as $data ) {
                        $message = new Entities\Message();
                        $message->loadBy( array( 'message_id' => $data['message_id'] ) );
                        if ( ! $message->isLoaded() ) {
                            $message->setFields( $data );
                            $message
                                ->setCreated( current_time( 'mysql' ) )
                                ->save();
                        }
                    }
                }
            }

            return $pluginInfo;
        }, 11, 2 );

        add_action( 'bookly_daily_routine', function () {
            // Grace routine
            $states = Config::getPluginVerificationStates();
            $unix_day = (int) ( current_time( 'timestamp' ) / DAY_IN_SECONDS );
            $grace_notifications = get_option( 'bookly_grace_notifications' );
            if ( $unix_day != $grace_notifications['sent'] ) {
                $admin_emails = Utils\Common::getAdminEmails();
                if ( ! empty ( $admin_emails ) ) {
                    $grace_notifications['sent'] = $unix_day;
                    if ( ( $states['bookly'] === 'expired' ) && ( $grace_notifications['bookly'] != 1 ) ) {
                        $subject = __( 'Please verify your Bookly license', 'bookly' );
                        $message = __( 'Bookly will need to verify your license to restore access to your bookings. Please enter the purchase code in the administrative panel.', 'bookly' );
                        if ( wp_mail( $admin_emails, $subject, $message ) ) {
                            $grace_notifications['bookly'] = 1;
                            update_option( 'bookly_grace_notifications', $grace_notifications );
                        }
                    } elseif ( ! empty( $states['grace_remaining_days'] ) && in_array( $states['grace_remaining_days'], array( 13, 7, 1 ) ) ) {
                        $days_text = sprintf( _n( '%d day', '%d days', $states['grace_remaining_days'], 'bookly' ), $states['grace_remaining_days'] );
                        $replace = array( '{days}' => $days_text );
                        $subject = __( 'Please verify your Bookly license', 'bookly' );
                        $message = strtr( __( 'Please verify Bookly license in the administrative panel. If you do not verify the license within {days}, access to your bookings will be disabled.', 'bookly' ), $replace );
                        if ( wp_mail( $admin_emails, $subject, $message ) ) {
                            update_option( 'bookly_grace_notifications', $grace_notifications );
                        }
                    }
                }
            }

            // SMS Summary routine
            if ( get_option( 'bookly_sms_notify_weekly_summary' ) && get_option( 'bookly_sms_token' ) ) {
                if ( get_option( 'bookly_sms_notify_weekly_summary_sent' ) != date( 'W' ) ) {
                    $admin_emails = Utils\Common::getAdminEmails();
                    if ( ! empty ( $admin_emails ) ) {
                        $sms     = new SMS();
                        $start   = date_create( 'last week' )->format( 'Y-m-d 00:00:00' );
                        $end     = date_create( 'this week' )->format( 'Y-m-d 00:00:00' );
                        $summary = $sms->getSummary( $start, $end );
                        if ( $summary !== false ) {
                            $notification_list = '';
                            foreach ( $summary->notifications as $type_id => $count ) {
                                $notification_list .= PHP_EOL . Entities\Notification::getName( Entities\Notification::getTypeString( $type_id ) ) . ': ' . $count->delivered;
                                if ( $count->delivered < $count->sent ) {
                                    $notification_list .= ' (' . $count->sent . ' ' . __( 'sent to our system', 'bookly' ) . ')';
                                }
                            }
                            // For balance.
                            $sms->loadProfile();
                            $message =
                                __( 'Hope you had a good weekend! Here\'s a summary of messages we\'ve delivered last week:
{notification_list}

Your system sent a total of {total} messages last week (that\'s {delta} {sign} than the week before).
Cost of sending {total} messages was {amount}. You current Bookly SMS balance is {balance}.

Thank you for using Bookly SMS. We wish you a lucky week!
Bookly SMS Team.', 'bookly' );
                            $message = strtr( $message,
                                array(
                                    '{notification_list}' => $notification_list,
                                    '{total}'             => $summary->total,
                                    '{delta}'             => abs( $summary->delta ),
                                    '{sign}'              => $summary->delta >= 0 ? __( 'more', 'bookly' ) : __( 'less', 'bookly' ),
                                    '{amount}'            => '$' . $summary->amount,
                                    '{balance}'           => '$' . $sms->getBalance(),
                                )
                            );
                            wp_mail( $admin_emails, __( 'Bookly SMS weekly summary', 'bookly' ), $message );
                            update_option( 'bookly_sms_notify_weekly_summary_sent', date( 'W' ) );
                        }
                    }
                }
            }

            if ( get_option( 'bookly_lic_repeat_time' ) < time() ) {
                update_option( 'bookly_lic_repeat_time', time() + 7776000 );
                if ( get_option( 'bookly_envato_purchase_code' ) == '' ) {
                    foreach ( get_users( array( 'role' => 'administrator' ) ) as $admin ) {
                        update_user_meta( $admin->ID, 'show_purchase_reminder', '1' );
                    }
                }
            }

            // Statistics routine.
            if ( get_option( 'bookly_gen_collect_stats' ) ) {
                API::sendStats();
            }
        }, 10, 0 );

        add_action( 'bookly_hourly_routine', function () {
            // Get list of outdated unpaid payments in format [ 'id' => 'details' ]
            $payments = Proxy\Shared::getOutdatedUnpaidPayments();
            if ( $payments ) {
                $ca_ids = array();
                foreach ( $payments as $payment_details ) {
                    $details = json_decode( $payment_details, true );
                    $ca_ids  = array_merge( $ca_ids, array_map( function ( $item ) { return $item['ca_id']; }, $details['items'] ) );
                }
                Entities\Payment::query()
                    ->update()
                    ->set( 'status', Entities\Payment::STATUS_REJECTED )
                    ->whereIn( 'id', array_keys( $payments ) )
                    ->execute();
                if ( ! empty( $ca_ids ) ) {
                    Entities\CustomerAppointment::query()
                        ->update()
                        ->set( 'status', Entities\CustomerAppointment::STATUS_REJECTED )
                        ->set( 'status_changed_at', current_time( 'mysql' ) )
                        ->whereIn( 'id', $ca_ids )
                        ->execute();
                }
            }
        }, 10, 0 );

        if ( get_option( 'bookly_gen_collect_stats' ) ) {
            // Store admin preferred language.
            add_filter( 'wp_authenticate_user', function ( $user ) {
                if ( $user instanceof \WP_User && $user->has_cap( 'manage_options' ) && isset ( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
                    $locale = strtok( $_SERVER['HTTP_ACCEPT_LANGUAGE'], ',;' );
                    update_option( 'bookly_admin_preferred_language', $locale );
                }

                return $user;
            }, 99, 1 );
        }

        // For admin notices about SMS weekly summary and etc.
        if ( ! wp_next_scheduled( 'bookly_daily_routine' ) ) {
            wp_schedule_event( time(), 'daily', 'bookly_daily_routine' );
        }

        // For cleaning not paid appointments
        if ( ! wp_next_scheduled( 'bookly_hourly_routine' ) ) {
            wp_schedule_event( time(), 'hourly', 'bookly_hourly_routine' );
        }
    }

    public static function run()
    {
        parent::run();
        $dir = Plugin::getDirectory() . '/lib/addons/';
        if ( is_dir( $dir ) ) {
            foreach ( glob( $dir . 'bookly-addon-*', GLOB_ONLYDIR ) as $path ) {
                include_once $path . '/autoload.php';
                $namespace = implode( '', array_map( 'ucfirst', explode( '-', str_replace( '-addon-', '-', basename( $path ) ) ) ) );
                /** @var \Bookly\Lib\Base\Plugin $plugin_class */
                $plugin_class = '\\' . $namespace . '\Lib\Plugin';
                $version_option_name = $plugin_class::getPrefix() . 'data_loaded';
                if ( get_option( $version_option_name ) === false ) {
                    // Install embedded add-on.
                    add_action( 'plugins_loaded', function () use ( $plugin_class ) {
                        $plugin_class::activate( 0 );
                        $plugin_class::run();
                    }, 99, 1 );
                } else {
                    $plugin_class::run();
                }
            }
        }
    }

}