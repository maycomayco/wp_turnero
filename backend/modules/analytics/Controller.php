<?php
namespace Bookly\Backend\Modules\Analytics;

use Bookly\Lib;

/**
 * Class Controller
 * @package Bookly\Backend\Modules\Analytics
 */
class Controller extends Lib\Base\Controller
{
    const page_slug = 'bookly-analytics';

    /**
     * Display page.
     *
     * @throws
     */
    public function index()
    {
        /** @var \WP_Locale $wp_locale */
        global $wp_locale;

        $this->enqueueStyles( array(
            'frontend' => array( 'css/ladda.min.css', ),
            'backend'  => array(
                'css/select2.min.css',
                'bootstrap/css/bootstrap-theme.min.css',
                'css/daterangepicker.css',
            ),
        ) );

        $this->enqueueScripts( array(
            'backend'  => array(
                'bootstrap/js/bootstrap.min.js' => array( 'jquery' ),
                'js/datatables.min.js'   => array( 'jquery' ),
                'js/moment.min.js',
                'js/daterangepicker.js'  => array( 'jquery' ),
                'js/select2.full.min.js' => array( 'jquery' ),
            ),
            'frontend' => array(
                'js/spin.min.js'  => array( 'jquery' ),
                'js/ladda.min.js' => array( 'jquery' ),
            ),
            'module'   => array( 'js/analytics.js' => array( 'bookly-datatables.min.js' ), ),
        ) );

        $services = Lib\Entities\Service::query()
            ->select( 'id, title' )
            ->where( 'type', Lib\Entities\Service::TYPE_SIMPLE )
            ->indexBy( 'id' )
            ->fetchArray();
        array_unshift( $services, array( 'id' => 0, 'title' => __( 'Custom', 'bookly' ) ) );
        $staff_members = Lib\Entities\Staff::query()
            ->select( 'id, full_name AS title' )
            ->indexBy( 'id' )
            ->fetchArray();

        wp_localize_script( 'bookly-analytics.js', 'BooklyL10n', array(
            'csrfToken'    => Lib\Utils\Common::getCsrfToken(),
            'tomorrow'     => __( 'Tomorrow', 'bookly' ),
            'today'        => __( 'Today', 'bookly' ),
            'yesterday'    => __( 'Yesterday', 'bookly' ),
            'last7'        => __( 'Last 7 Days', 'bookly' ),
            'last30'       => __( 'Last 30 Days', 'bookly' ),
            'thisMonth'    => __( 'This Month', 'bookly' ),
            'nextMonth'    => __( 'Next Month', 'bookly' ),
            'customRange'  => __( 'Custom Range', 'bookly' ),
            'apply'        => __( 'Apply', 'bookly' ),
            'cancel'       => __( 'Cancel', 'bookly' ),
            'to'           => __( 'To', 'bookly' ),
            'from'         => __( 'From', 'bookly' ),
            'calendar'     => array(
                'longMonths'  => array_values( $wp_locale->month ),
                'shortMonths' => array_values( $wp_locale->month_abbrev ),
                'longDays'    => array_values( $wp_locale->weekday ),
                'shortDays'   => array_values( $wp_locale->weekday_abbrev ),
            ),
            'mjsDateFormat' => Lib\Utils\DateTime::convertFormat( 'date', Lib\Utils\DateTime::FORMAT_MOMENT_JS ),
            'startOfWeek'   => (int) get_option( 'start_of_week' ),
            'zeroRecords'   => __( 'No appointments for selected period.', 'bookly' ),
            'processing'    => __( 'Processing...', 'bookly' ),
            'services' => array(
                'allSelected'     => __( 'All services', 'bookly' ),
                'nothingSelected' => __( 'No service selected', 'bookly' ),
                'collection'      => $services,
            ),
            'staff' => array(
                'allSelected'     => __( 'All staff', 'bookly' ),
                'nothingSelected' => __( 'No staff selected', 'bookly' ),
                'collection'      => $staff_members,
            ),
        ) );

        $this->render( 'index', compact( 'staff_members', 'services' ) );
    }

    /**
     * Get analytics.
     */
    public function executeGetAnalytics()
    {
        $date        = $this->getParameter( 'date' );
        $staff_ids   = $this->getParameter( 'staff_ids', array() );
        $service_ids = array_map( function ( $id ) { return $id ?: null; }, $this->getParameter( 'service_ids', array() ) );

        $data = array();
        foreach ( $staff_ids as $staff_id ) {
            foreach ( $service_ids as $service_id ) {
                $staff_service = new Lib\Entities\StaffService();
                $staff_service->loadBy( compact( 'staff_id', 'service_id' ) );
                $data[ $staff_id ][ $service_id ] = array(
                    'staff'   => Lib\Entities\Staff::find( $staff_id )->getFullName(),
                    'service' => $service_id ? Lib\Entities\Service::find( $service_id )->getTitle() : __( 'Custom', 'bookly' ),
                    'price'   => $staff_service->isLoaded() ? $staff_service->getPrice() : 0,
                    'visits' => array(
                        'sessions'  => array(),
                        'pending'   => 0,
                        'approved'  => 0,
                        'rejected'  => 0,
                        'cancelled' => 0,
                    ),
                    'customers' => array(
                        'customers'     => array(),
                        'new_customers' => array(),
                    ),
                    'payments' => array(
                        'uncompleted' => 0,
                        'total'       => 0,
                    ),
                );
            }
        }

        list ( $start, $end ) = explode( ' - ', $date, 2 );
        $end = date( 'Y-m-d', strtotime( '+1 day', strtotime( $end ) ) );

        $query = Lib\Entities\CustomerAppointment::query( 'ca' )
            ->select( 'ca.appointment_id, ca.customer_id, ca.status, ca.number_of_persons, ca.extras, a.staff_id, a.service_id, a.start_date, a.custom_service_price, p.status AS payment_status' )
            ->leftJoin( 'Appointment', 'a', 'a.id = ca.appointment_id' )
            ->leftJoin( 'Service', 's', 's.id = a.service_id' )
            ->leftJoin( 'Customer', 'c', 'c.id = ca.customer_id' )
            ->leftJoin( 'Payment', 'p', 'p.id = ca.payment_id' )
            ->leftJoin( 'Staff', 'st', 'st.id = a.staff_id' )
            ->leftJoin( 'StaffService', 'ss', 'ss.staff_id = st.id AND ss.service_id = s.id' )
            ->whereIn( 'a.staff_id', $staff_ids )
            ->whereBetween( 'a.start_date', $start, $end )
        ;
        if ( array_search( null, $service_ids, true ) !== false ) {
            $where_raw = 'a.service_id IS NULL';
            $service_ids_filtered = array_filter( $service_ids );
            if ( ! empty ( $service_ids_filtered ) ) {
                $where_raw .= sprintf( ' OR a.service_id IN (%s)', implode( ',', $service_ids_filtered ) );
            }
            $query->whereRaw( $where_raw, array() );
        } else {
            $query->whereIn( 'a.service_id', $service_ids );
        }

        foreach ( $query->fetchArray() as $row ) {
            $record = &$data[ $row['staff_id'] ][ $row['service_id'] ];
            $calc_payment = false;
            switch ( $row['status'] ) {
                case Lib\Entities\CustomerAppointment::STATUS_PENDING:
                    $record['visits']['pending'] += $row['number_of_persons'];
                    $record['visits']['sessions'][ $row['appointment_id'] ] = true;
                    $calc_payment = true;
                    break;
                case Lib\Entities\CustomerAppointment::STATUS_APPROVED:
                    $record['visits']['approved'] += $row['number_of_persons'];
                    $record['visits']['sessions'][ $row['appointment_id'] ] = true;
                    $calc_payment = true;
                    break;
                case Lib\Entities\CustomerAppointment::STATUS_REJECTED:
                    $record['visits']['rejected'] += $row['number_of_persons'];
                    break;
                case Lib\Entities\CustomerAppointment::STATUS_CANCELLED:
                    $record['visits']['cancelled'] += $row['number_of_persons'];
                    break;
            }
            $record['customers']['customers'][ $row['customer_id'] ] = true;
            if ( ! isset ( $record['customers']['new_customers'][ $row['customer_id'] ] ) ) {
                $exists = Lib\Entities\CustomerAppointment::query( 'ca' )
                    ->select( '1' )
                    ->leftJoin( 'Appointment', 'a', 'a.id = ca.appointment_id' )
                    ->where( 'ca.customer_id', $row['customer_id'] )
                    ->whereLt( 'a.start_date', $row['start_date'] )
                    ->execute( Lib\Query::HYDRATE_NONE );
                if ( ! $exists ) {
                    $record['customers']['new_customers'][ $row['customer_id'] ] = true;
                }
            }
            // Payments.
            if ( $calc_payment ) {
                $amount = $row['service_id']
                    ? Lib\Proxy\ServiceExtras::prepareServicePrice(
                        $record['price'] * $row['number_of_persons'],
                        $record['price'],
                        $row['number_of_persons'],
                        json_decode( $row['extras'], true )
                    )
                    : $row['custom_service_price']
                ;
                if ( $row['payment_status'] ) {
                    if ( $row['payment_status'] == Lib\Entities\Payment::STATUS_COMPLETED ) {
                        $record['payments']['total'] += $amount;
                    } else if ( $row['payment_status'] == Lib\Entities\Payment::STATUS_PENDING ) {
                        $record['payments']['uncompleted'] += $amount;
                    }
                } else {
                    $record['payments']['total'] += $amount;
                }
            }

            unset ( $record );
        }

        $result = array();
        $total  = array(
            'visits' => array(
                'sessions'  => 0,
                'pending'   => 0,
                'approved'  => 0,
                'rejected'  => 0,
                'cancelled' => 0,
            ),
            'customers' => array(
                'customers'     => 0,
                'new_customers' => 0,
            ),
            'payments' => array(
                'uncompleted' => 0,
                'total'       => 0,
            ),
        );
        foreach ( $data as $staff_data ) {
            foreach ( $staff_data as $record ) {
                unset ( $record['price'] );

                $record['visits']['sessions'] = count( $record['visits']['sessions'] );

                $record['customers']['customers']     = count( $record['customers']['customers'] );
                $record['customers']['new_customers'] = count( $record['customers']['new_customers'] );

                $record['payments']['frontend_formatted'] = Lib\Utils\Price::format( $record['payments']['completed'] );
                $record['payments']['total_formatted']    = Lib\Utils\Price::format( $record['payments']['total'] );
                if ( $record['payments']['uncompleted'] ) {
                    $tail = sprintf( ' (%s)', Lib\Utils\Price::format( $record['payments']['uncompleted'] ) );
                    $record['payments']['frontend_formatted'] .= $tail;
                    $record['payments']['total_formatted']    .= $tail;
                }

                $result[] = $record;

                $total['visits']['sessions']         += $record['visits']['sessions'];
                $total['visits']['pending']          += $record['visits']['pending'];
                $total['visits']['approved']         += $record['visits']['approved'];
                $total['visits']['rejected']         += $record['visits']['rejected'];
                $total['visits']['cancelled']        += $record['visits']['cancelled'];
                $total['customers']['customers']     += $record['customers']['customers'];
                $total['customers']['new_customers'] += $record['customers']['new_customers'];
                $total['payments']['uncompleted']    += $record['payments']['uncompleted'];
                $total['payments']['total']          += $record['payments']['total'];
            }
        }
        $total['payments']['total_formatted'] = Lib\Utils\Price::format( $total['payments']['total'] );
        if ( $total['payments']['uncompleted'] ) {
            $tail = sprintf( ' (%s)', Lib\Utils\Price::format( $total['payments']['uncompleted'] ) );
            $total['payments']['total_formatted'] .= $tail;
        }

        wp_send_json( array( 'data' => $result, 'total' => $total ) );
    }
}