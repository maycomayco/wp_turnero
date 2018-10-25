<?php
namespace Bookly\Backend\Modules\Customers;

use Bookly\Lib;

/**
 * Class Controller
 * @package Bookly\Backend\Modules\Customers
 */
class Controller extends Lib\Base\Controller
{
    const page_slug = 'bookly-customers';

    protected function getPermissions()
    {
        return array( '_this' => 'user' );
    }

    /**
     * Render page.
     *
     * @throws
     */
    public function index()
    {
        if ( $this->hasParameter( 'import-customers' ) ) {
            $this->importCustomers();
        }

        $this->enqueueStyles( array(
            'backend'  => array( 'bootstrap/css/bootstrap-theme.min.css', ),
            'frontend' => array( 'css/ladda.min.css', ),
        ) );

        $this->enqueueScripts( array(
            'backend' => array(
                'bootstrap/js/bootstrap.min.js' => array( 'jquery' ),
                'js/datatables.min.js' => array( 'jquery' ),
            ),
            'frontend' => array(
                'js/spin.min.js' => array( 'jquery' ),
                'js/ladda.min.js' => array( 'jquery' ),
            ),
            'module' => array(
                'js/customers.js' => array( 'bookly-datatables.min.js', 'bookly-ng-customer_dialog.js' ),
            ),
        ) );

        // Customer information fields.
        $info_fields = (array) Lib\Proxy\CustomerInformation::getFieldsWhichMayHaveData();

        wp_localize_script( 'bookly-customers.js', 'BooklyL10n', array(
            'csrfToken'       => Lib\Utils\Common::getCsrfToken(),
            'first_last_name' => (int) Lib\Config::showFirstLastName(),
            'groupsActive'    => (int) Lib\Config::customerGroupsActive(),
            'infoFields'      => $info_fields,
            'edit'            => __( 'Edit', 'bookly' ),
            'are_you_sure'    => __( 'Are you sure?', 'bookly' ),
            'wp_users'        => get_users( array( 'fields' => array( 'ID', 'display_name' ), 'orderby' => 'display_name' ) ),
            'zeroRecords'     => __( 'No customers found.', 'bookly' ),
            'processing'      => __( 'Processing...', 'bookly' ),
            'edit_customer'   => __( 'Edit customer', 'bookly' ),
            'new_customer'    => __( 'New customer', 'bookly' ),
            'create_customer' => __( 'Create customer', 'bookly' ),
            'save'            => __( 'Save', 'bookly' ),
            'search'          => __( 'Quick search customer', 'bookly' ),
        ) );

        $this->render( 'index', compact( 'info_fields' ) );
    }

    /**
     * Get list of customers.
     */
    public function executeGetCustomers()
    {
        global $wpdb;

        $columns = $this->getParameter( 'columns' );
        $order   = $this->getParameter( 'order' );
        $filter  = $this->getParameter( 'filter' );

        $query = Lib\Entities\Customer::query( 'c' );

        $total = $query->count();

        $select = 'SQL_CALC_FOUND_ROWS c.*,
                (
                    SELECT MAX(a.start_date) FROM ' . Lib\Entities\Appointment::getTableName() . ' a
                        LEFT JOIN ' . Lib\Entities\CustomerAppointment::getTableName() . ' ca ON ca.appointment_id = a.id
                            WHERE ca.customer_id = c.id
                ) AS last_appointment,
                (
                    SELECT COUNT(DISTINCT ca.appointment_id) FROM ' . Lib\Entities\CustomerAppointment::getTableName() . ' ca
                        WHERE ca.customer_id = c.id
                ) AS total_appointments,
                (
                    SELECT SUM(p.total) FROM ' . Lib\Entities\Payment::getTableName() . ' p
                        WHERE p.id IN (
                            SELECT DISTINCT ca.payment_id FROM ' . Lib\Entities\CustomerAppointment::getTableName() . ' ca
                                WHERE ca.customer_id = c.id
                        )
                ) AS payments,
                wpu.display_name AS wp_user';

        $select = Lib\Proxy\CustomerGroups::prepareCustomerSelect( $select );

        $query
            ->select( $select )
            ->tableJoin( $wpdb->users, 'wpu', 'wpu.ID = c.wp_user_id' )
            ->groupBy( 'c.id' );

        $query = Lib\Proxy\CustomerGroups::prepareCustomerQuery( $query );

        if ( $filter != '' ) {
            $search_value = Lib\Query::escape( $filter );
            $query
                ->whereLike( 'c.full_name', "%{$search_value}%" )
                ->whereLike( 'c.phone', "%{$search_value}%", 'OR' )
                ->whereLike( 'c.email', "%{$search_value}%", 'OR' )
                ->whereLike( 'c.info_fields', "%{$search_value}%", 'OR' )
            ;
        }

        foreach ( $order as $sort_by ) {
            $query
                ->sortBy( str_replace( '.', '_', $columns[ $sort_by['column'] ]['data'] ) )
                ->order( $sort_by['dir'] == 'desc' ? Lib\Query::ORDER_DESCENDING : Lib\Query::ORDER_ASCENDING );
        }

        $query->limit( $this->getParameter( 'length' ) )->offset( $this->getParameter( 'start' ) );

        $data = array();
        foreach ( $query->fetchArray() as $row ) {
            $customer_data = array(
                'id'                 => $row['id'],
                'full_name'          => $row['full_name'],
                'first_name'         => $row['first_name'],
                'last_name'          => $row['last_name'],
                'group_id'           => $row['group_id'],
                'wp_user'            => $row['wp_user'],
                'wp_user_id'         => $row['wp_user_id'],
                'phone'              => $row['phone'],
                'email'              => $row['email'],
                'notes'              => $row['notes'],
                'birthday'           => $row['birthday'],
                'last_appointment'   => $row['last_appointment'] ? Lib\Utils\DateTime::formatDateTime( $row['last_appointment'] ) : '',
                'total_appointments' => $row['total_appointments'],
                'payments'           => Lib\Utils\Price::format( $row['payments'] ),
            );

            $customer_data = Lib\Proxy\CustomerGroups::prepareCustomerListData( $customer_data, $row );
            $customer_data = Lib\Proxy\CustomerInformation::prepareCustomerListData( $customer_data, $row );

            $data[] = $customer_data;
        }

        wp_send_json( array(
            'draw'            => ( int ) $this->getParameter( 'draw' ),
            'recordsTotal'    => $total,
            'recordsFiltered' => ( int ) $wpdb->get_var( 'SELECT FOUND_ROWS()' ),
            'data'            => $data,
        ) );
    }

    /**
     * Create or edit a customer.
     */
    public function executeSaveCustomer()
    {
        $response = array();

        $params = $this->getPostParameters();
        $errors = array();

        // Check for errors.
        if ( get_option( 'bookly_cst_first_last_name' ) ) {
            if ( $params['first_name'] == '' ) {
                $errors['first_name'] = array( 'required' );
            }
            if ( $params['last_name'] == '' ) {
                $errors['last_name'] = array( 'required' );
            }
        } else if ( $params['full_name'] == '' ) {
            $errors['full_name'] = array( 'required' );
        }

        if ( empty ( $errors ) ) {
            if ( ! $params['wp_user_id'] ) {
                $params['wp_user_id'] = null;
            }
            if ( ! $params['birthday'] ) {
                $params['birthday'] = null;
            }
            if ( ! $params['group_id'] ) {
                $params['group_id'] = null;
            }
            $params = Lib\Proxy\CustomerInformation::prepareCustomerFormData( $params );
            $params['info_fields'] = json_encode( $params['info_fields'] );
            $form = new Forms\Customer();
            $form->bind( $params );
            /** @var Lib\Entities\Customer $customer */
            $customer = $form->save();
            $response['success']  = true;
            $response['customer'] = array(
                'id'          => $customer->getId(),
                'wp_user_id'  => $customer->getWpUserId(),
                'group_id'    => $customer->getGroupId(),
                'full_name'   => $customer->getFullName(),
                'first_name'  => $customer->getFirstName(),
                'last_name'   => $customer->getLastName(),
                'phone'       => $customer->getPhone(),
                'email'       => $customer->getEmail(),
                'notes'       => $customer->getNotes(),
                'birthday'    => $customer->getBirthday(),
                'info_fields' => json_decode( $customer->getInfoFields() ),
            );
        } else {
            $response['success'] = false;
            $response['errors']  = $errors;
        }

        wp_send_json( $response );
    }

    /**
     * Import customers from CSV.
     */
    private function importCustomers()
    {
        @ini_set( 'auto_detect_line_endings', true );
        $fields = array();
        foreach ( array( 'full_name', 'first_name', 'last_name', 'phone', 'email', 'birthday' ) as $field ) {
            if ( $this->getParameter( $field ) ) {
                $fields[] = $field;
            }
        }
        $file = fopen( $_FILES['import_customers_file']['tmp_name'], 'r' );
        while ( $line = fgetcsv( $file, null, $this->getParameter( 'import_customers_delimiter' ) ) ) {
            if ( $line[0] != '' ) {
                $customer = new Lib\Entities\Customer();
                foreach ( $line as $number => $value ) {
                    if ( $number < count( $fields ) ) {
                        if ( $fields[ $number ] == 'birthday' ) {
                            $dob = date_create( $value );
                            if ( $dob !== false ) {
                                $customer->setBirthday( $dob->format( 'Y-m-d' ) );
                            }
                        } else {
                            $method = 'set' . implode( '', array_map( 'ucfirst', explode( '_', $fields[ $number ] ) ) );
                            $customer->$method( $value );
                        }
                    }
                }
                $customer->save();
            }
        }
    }

    /**
     * Delete customers.
     */
    public function executeDeleteCustomers()
    {
        foreach ( $this->getParameter( 'data', array() ) as $id ) {
            $customer = new Lib\Entities\Customer();
            $customer->load( $id );
            $customer->deleteWithWPUser( (bool) $this->getParameter( 'with_wp_user' ) );
        }
        wp_send_json_success();
    }

    /**
     * Export Customers to CSV
     */
    public function executeExportCustomers()
    {
        global $wpdb;
        $delimiter = $this->getParameter( 'export_customers_delimiter', ',' );

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=Customers.csv' );

        $titles = array(
            'full_name'          => Lib\Utils\Common::getTranslatedOption( 'bookly_l10n_label_name' ),
            'first_name'         => Lib\Utils\Common::getTranslatedOption( 'bookly_l10n_label_first_name' ),
            'last_name'          => Lib\Utils\Common::getTranslatedOption( 'bookly_l10n_label_last_name' ),
            'wp_user'            => __( 'User', 'bookly' )
        );

        $titles = Lib\Proxy\CustomerGroups::prepareCustomerExportTitles( $titles );

        $titles = array_merge( $titles, array(
            'phone'              => Lib\Utils\Common::getTranslatedOption( 'bookly_l10n_label_phone' ),
            'email'              => Lib\Utils\Common::getTranslatedOption( 'bookly_l10n_label_email' ),
            'notes'              => __( 'Notes', 'bookly' ),
            'last_appointment'   => __( 'Last appointment', 'bookly' ),
            'total_appointments' => __( 'Total appointments', 'bookly' ),
            'payments'           => __( 'Payments', 'bookly' ),
            'birthday'           => __( 'Date of birth', 'bookly' ),
        ) );

        $fields = (array) Lib\Proxy\CustomerInformation::getFields();

        foreach ( $fields as $field ) {
            $titles[ $field->id ] = $field->label;
        }

        $header = array();
        $column = array();

        foreach ( $this->getParameter( 'exp', array() ) as $key => $value ) {
            $header[] = $titles[ $key ];
            $column[] = $key;
        }

        $output = fopen( 'php://output', 'w' );
        fwrite( $output, pack( 'CCC', 0xef, 0xbb, 0xbf ) );
        fputcsv( $output, $header, $delimiter );

        $select = 'c.*, MAX(a.start_date) AS last_appointment,
                COUNT(a.id) AS total_appointments,
                COALESCE(SUM(p.total),0) AS payments,
                wpu.display_name AS wp_user';
        $select = Lib\Proxy\CustomerGroups::prepareCustomerSelect( $select );

        $query = Lib\Entities\Customer::query( 'c' )
            ->select( $select )
            ->leftJoin( 'CustomerAppointment', 'ca', 'ca.customer_id = c.id' )
            ->leftJoin( 'Appointment', 'a', 'a.id = ca.appointment_id' )
            ->leftJoin( 'Payment', 'p', 'p.id = ca.payment_id' )
            ->tableJoin( $wpdb->users, 'wpu', 'wpu.ID = c.wp_user_id' )
            ->groupBy( 'c.id' );

        $query = Lib\Proxy\CustomerGroups::prepareCustomerQuery( $query );

        $rows = $query->fetchArray();

        foreach ( $rows as $row ) {
            $row_data = array_fill( 0, count( $column ), '' );
            foreach ( $row as $key => $value ) {
                if ( $key == 'info_fields' ) {
                    foreach ( json_decode( $value ) as $field ) {
                        $pos = array_search( $field->id, $column );
                        if ( $pos !== false ) {
                            $row_data[ $pos ] = is_array( $field->value ) ? implode( ', ', $field->value ) : $field->value;
                        }
                    }
                } else {
                    $pos = array_search( $key, $column );
                    if ( $pos !== false ) {
                        $row_data[ $pos ] = $value;
                    }
                }
            }
            fputcsv( $output, $row_data, $delimiter );
        }

        fclose( $output );

        exit;
    }

    /**
     * Check if the current user has access to the action.
     *
     * @param string $action
     * @return bool
     */
    protected function hasAccess( $action )
    {
        if ( parent::hasAccess( $action ) ) {
            if ( ! Lib\Utils\Common::isCurrentUserAdmin() ) {
                switch ( $action ) {
                    case 'executeSaveCustomer':
                    case 'executeGetCustomers':
                    case 'executeExportCustomers':
                    case 'executeDeleteCustomers':
                        return Lib\Entities\Staff::query()
                            ->where( 'wp_user_id', get_current_user_id() )
                            ->count() > 0;
                }
            } else {
                return true;
            }
        }

        return false;
    }

}