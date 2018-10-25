<?php
namespace Bookly\Backend\Modules\Payments;

use Bookly\Lib;

/**
 * Class Controller
 *
 * @package Bookly\Backend\Modules\Payments
 */
class Controller extends Lib\Base\Controller
{
    const page_slug = 'bookly-payments';

    /**
     * @return array
     */
    protected function getPermissions()
    {
        return array(
            'executeGetPaymentDetails'    => 'user',
            'executeCompletePayment'      => 'user',
            'executeAddPaymentAdjustment' => 'user',
            'executeGetPaymentInfo'       => 'user',
        );
    }

    public function index()
    {
        /** @var \WP_Locale $wp_locale */
        global $wp_locale;

        $this->enqueueStyles( array(
            'frontend' => array( 'css/ladda.min.css', ),
            'backend'  => array(
                'css/select2.min.css',
                'bootstrap/css/bootstrap-theme.min.css' => array( 'bookly-select2.min.css' ),
                'css/daterangepicker.css',
            ),
        ) );

        $this->enqueueScripts( array(
            'backend'  => array(
                'bootstrap/js/bootstrap.min.js' => array( 'jquery' ),
                'js/datatables.min.js'          => array( 'jquery' ),
                'js/moment.min.js',
                'js/daterangepicker.js'         => array( 'jquery' ),
                'js/select2.full.min.js'        => array( 'jquery' ),
            ),
            'frontend' => array(
                'js/spin.min.js'  => array( 'jquery' ),
                'js/ladda.min.js' => array( 'jquery' ),
            ),
            'module'   => array( 'js/payments.js' => array( 'bookly-datatables.min.js', 'bookly-ng-payment_details_dialog.js' ) ),
        ) );

        wp_localize_script( 'bookly-daterangepicker.js', 'BooklyL10n', array(
            'csrf_token'      => Lib\Utils\Common::getCsrfToken(),
            'today'           => __( 'Today', 'bookly' ),
            'yesterday'       => __( 'Yesterday', 'bookly' ),
            'last_7'          => __( 'Last 7 Days', 'bookly' ),
            'last_30'         => __( 'Last 30 Days', 'bookly' ),
            'this_month'      => __( 'This Month', 'bookly' ),
            'last_month'      => __( 'Last Month', 'bookly' ),
            'custom_range'    => __( 'Custom Range', 'bookly' ),
            'apply'           => __( 'Apply', 'bookly' ),
            'cancel'          => __( 'Cancel', 'bookly' ),
            'to'              => __( 'To', 'bookly' ),
            'from'            => __( 'From', 'bookly' ),
            'calendar'        => array(
                'longMonths'  => array_values( $wp_locale->month ),
                'shortMonths' => array_values( $wp_locale->month_abbrev ),
                'longDays'    => array_values( $wp_locale->weekday ),
                'shortDays'   => array_values( $wp_locale->weekday_abbrev ),
            ),
            'startOfWeek'     => (int) get_option( 'start_of_week' ),
            'mjsDateFormat'   => Lib\Utils\DateTime::convertFormat( 'date', Lib\Utils\DateTime::FORMAT_MOMENT_JS ),
            'zeroRecords'     => __( 'No payments for selected period and criteria.', 'bookly' ),
            'processing'      => __( 'Processing...', 'bookly' ),
            'details'         => __( 'Details', 'bookly' ),
            'are_you_sure'    => __( 'Are you sure?', 'bookly' ),
            'no_result_found' => __( 'No result found', 'bookly' ),
        ) );

        $types = array(
            Lib\Entities\Payment::TYPE_LOCAL,
            Lib\Entities\Payment::TYPE_2CHECKOUT,
            Lib\Entities\Payment::TYPE_PAYPAL,
            Lib\Entities\Payment::TYPE_AUTHORIZENET,
            Lib\Entities\Payment::TYPE_STRIPE,
            Lib\Entities\Payment::TYPE_PAYULATAM,
            Lib\Entities\Payment::TYPE_PAYSON,
            Lib\Entities\Payment::TYPE_MOLLIE,
            Lib\Entities\Payment::TYPE_COUPON,
            Lib\Entities\Payment::TYPE_WOOCOMMERCE,
        );

        $providers = Lib\Entities\Staff::query()->select( 'id, full_name' )->sortBy( 'full_name' )->fetchArray();
        $services  = Lib\Entities\Service::query()->select( 'id, title' )->sortBy( 'title' )->fetchArray();

        $this->render( 'index', compact( 'types', 'providers', 'services' ) );
    }

    /**
     * Get payments.
     */
    public function executeGetPayments()
    {
        $columns = $this->getParameter( 'columns' );
        $order   = $this->getParameter( 'order' );
        $filter  = $this->getParameter( 'filter' );

        $query = Lib\Entities\Payment::query( 'p' )
            ->select( 'p.id, p.created, p.type, p.paid, p.total, p.status, p.details, c.full_name customer, st.full_name provider, s.title service, a.start_date' )
            ->leftJoin( 'CustomerAppointment', 'ca', 'ca.payment_id = p.id' )
            ->leftJoin( 'Customer', 'c', 'c.id = ca.customer_id' )
            ->leftJoin( 'Appointment', 'a', 'a.id = ca.appointment_id' )
            ->leftJoin( 'Service', 's', 's.id = COALESCE(ca.compound_service_id, a.service_id)' )
            ->leftJoin( 'Staff', 'st', 'st.id = a.staff_id' )
            ->groupBy( 'p.id' );

        // Filters.
        list ( $start, $end ) = explode( ' - ', $filter['created'], 2 );
        $end = date( 'Y-m-d', strtotime( '+1 day', strtotime( $end ) ) );

        $query->whereBetween( 'p.created', $start, $end );

        if ( $filter['type'] != '' ) {
            $query->where( 'p.type', $filter['type'] );
        }

        if ( $filter['staff'] != '' ) {
            $query->where( 'st.id', $filter['staff'] );
        }

        if ( $filter['service'] != '' ) {
            $query->where( 's.id', $filter['service'] );
        }

        if ( $filter['status'] != '' ) {
            $query->where( 'p.status', $filter['status'] );
        }

        foreach ( $order as $sort_by ) {
            $query->sortBy( $columns[ $sort_by['column'] ]['data'] )
                ->order( $sort_by['dir'] == 'desc' ? Lib\Query::ORDER_DESCENDING : Lib\Query::ORDER_ASCENDING );
        }

        $payments = $query->fetchArray();

        $data  = array();
        $total = 0;
        foreach ( $payments as $payment ) {
            $details  = json_decode( $payment['details'], true );
            $multiple = count( $details['items'] ) > 1
                ? ' <span class="glyphicon glyphicon-shopping-cart" title="' . esc_attr( __( 'See details for more items', 'bookly' ) ) . '"></span>'
                : '';

            $paid_title = Lib\Utils\Price::format( $payment['paid'] );
            if ( $payment['paid'] != $payment['total'] ) {
                $paid_title = sprintf( __( '%s of %s', 'bookly' ), $paid_title, Lib\Utils\Price::format( $payment['total'] ) );
            }

            $data[] = array(
                'id'         => $payment['id'],
                'created'    => Lib\Utils\DateTime::formatDateTime( $payment['created'] ),
                'type'       => Lib\Entities\Payment::typeToString( $payment['type'] ),
                'customer'   => $payment['customer'] ?: $details['customer'],
                'provider'   => ( $payment['provider'] ?: $details['items'][0]['staff_name'] ) . $multiple,
                'service'    => ( $payment['service'] ?: $details['items'][0]['service_name'] ) . $multiple,
                'start_date' => ( $payment['start_date']
                        ? Lib\Utils\DateTime::formatDateTime( $payment['start_date'] )
                        : Lib\Utils\DateTime::formatDateTime( $details['items'][0]['appointment_date'] ) ) . $multiple,
                'paid'       => $paid_title,
                'status'     => Lib\Entities\Payment::statusToString( $payment['status'] ),

            );

            $total += $payment['paid'];
        }

        wp_send_json( array(
            'draw'            => ( int ) $this->getParameter( 'draw' ),
            'recordsTotal'    => count( $data ),
            'recordsFiltered' => count( $data ),
            'data'            => $data,
            'total'           => Lib\Utils\Price::format( $total ),
        ) );
    }

    /**
     * Get payment details.
     *
     * @throws \Exception
     */
    public function executeGetPaymentDetails()
    {
        $data    = array();
        $payment = Lib\Entities\Payment::query( 'p' )
            ->select( 'p.id,
                p.total,
                p.status,
                p.created AS created,
                p.type,
                p.details,
                p.paid,
                p.gateway_price_correction,
                c.full_name AS customer' )
            ->leftJoin( 'CustomerAppointment', 'ca', 'ca.payment_id = p.id' )
            ->leftJoin( 'Customer', 'c', 'c.id = ca.customer_id' )
            ->where( 'p.id', $this->getParameter( 'payment_id' ) )
            ->fetchRow();
        if ( $payment ) {
            $details = json_decode( $payment['details'], true );
            $data    = array(
                'payment'             => array(
                    'id'             => $payment['id'],
                    'status'         => $payment['status'],
                    'type'           => $payment['type'],
                    'coupon'         => $details['coupon'],
                    'created'        => $payment['created'],
                    'customer'       => empty ( $payment['customer'] ) ? $details['customer'] : $payment['customer'],
                    'total'          => $payment['total'],
                    'paid'           => $payment['paid'],
                    'price_correction' => $payment['gateway_price_correction'],
                    'customer_group' => $details['customer_group'],
                ),
                'items'               => $details['items'],
                'extras_multiply_nop' => isset( $details['extras_multiply_nop'] ) ? $details['extras_multiply_nop'] : 1,
                'adjustments'         => $details['adjustments'] ?: array(),
                'deposit_enabled'     => Lib\Config::depositPaymentsEnabled(),
            );

            wp_send_json_success( array( 'html' => $this->render( 'details', $data, false ) ) );
        }

        wp_send_json_error( array( 'html' => __( 'Payment is not found.', 'bookly' ) ) );
    }

    /**
     * Adjust payment.
     *
     * @throws \Exception
     */
    public function executeAddPaymentAdjustment()
    {
        $payment_id = $this->getParameter( 'payment_id' );
        $reason     = $this->getParameter( 'reason' );
        $amount     = $this->getParameter( 'amount' );

        $payment = new Lib\Entities\Payment();
        $payment->load( $payment_id );

        if ( $payment && is_numeric( $amount ) ) {
            $details = json_decode( $payment->getDetails(), true );

            $details['adjustments'][] = array( 'reason' => $reason, 'amount' => $amount );
            $payment
                ->setDetails( json_encode( $details ) )
                ->setTotal( $payment->getTotal() + $amount )
                ->save();
        }

        wp_send_json_success();
    }

    /**
     * Delete payments.
     */
    public function executeDeletePayments()
    {
        $payment_ids = array_map( 'intval', $this->getParameter( 'data', array() ) );
        Lib\Entities\Payment::query()->delete()->whereIn( 'id', $payment_ids )->execute();
        wp_send_json_success();
    }

    /**
     * Complete payment.
     */
    public function executeCompletePayment()
    {
        $payment = Lib\Entities\Payment::find( $this->getParameter( 'payment_id' ) );
        $payment
            ->setPaid( $payment->getTotal() )
            ->setStatus( Lib\Entities\Payment::STATUS_COMPLETED )
            ->save();

        $payment_title = Lib\Utils\Price::format( $payment->getPaid() );
        if ( $payment->getPaid() != $payment->getTotal() ) {
            $payment_title = sprintf( __( '%s of %s', 'bookly' ), $payment_title, Lib\Utils\Price::format( $payment->getTotal() ) );
        }
        $payment_title .= sprintf(
            ' %s <span%s>%s</span>',
            Lib\Entities\Payment::typeToString( $payment->getType() ),
            $payment->getStatus() == Lib\Entities\Payment::STATUS_PENDING ? ' class="text-danger"' : '',
            Lib\Entities\Payment::statusToString( $payment->getStatus() )
        );

        wp_send_json_success( array( 'payment_title' => $payment_title ) );
    }

    /**
     * Get payment info
     */
    public function executeGetPaymentInfo()
    {
        $payment = Lib\Entities\Payment::find( $this->getParameter( 'payment_id' ) );

        if ( $payment ) {
            $payment_title = Lib\Utils\Price::format( $payment->getPaid() );
            if ( $payment->getPaid() != $payment->getTotal() ) {
                $payment_title = sprintf( __( '%s of %s', 'bookly' ), $payment_title, Lib\Utils\Price::format( $payment->getTotal() ) );
            }
            $payment_title .= sprintf(
                ' %s <span%s>%s</span>',
                Lib\Entities\Payment::typeToString( $payment->getType() ),
                $payment->getStatus() == Lib\Entities\Payment::STATUS_PENDING ? ' class="text-danger"' : '',
                Lib\Entities\Payment::statusToString( $payment->getStatus() )
            );

            wp_send_json_success( array( 'payment_title' => $payment_title, 'payment_type' => $payment->getPaid() == $payment->getTotal() ? 'full' : 'partial' ) );
        }
    }
}