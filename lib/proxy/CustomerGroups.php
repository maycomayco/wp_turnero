<?php
namespace Bookly\Lib\Proxy;

use Bookly\Lib\Base;

/**
 * Class CustomerGroups
 * Invoke local methods from Customer Groups add-on.
 *
 * @package Bookly\Lib\Proxy
 *
 * @method static void addBooklyMenuItem() Add 'Customer Groups' to Bookly menu
 * @see \BooklyCustomerGroups\Lib\ProxyProviders\Local::addBooklyMenuItem()
 *
 * @method static \Bookly\Lib\Query prepareCaSeStQuery( \Bookly\Lib\Query $query ) Prepare CaSeSt Services query
 * @see \BooklyCustomerGroups\Lib\ProxyProviders\Local::prepareCaSeStQuery()
 *
 * @method static array prepareCustomerListData( array $data, array $row ) Prepare 'Customer Groups' data in customers table
 * @see \BooklyCustomerGroups\Lib\ProxyProviders\Local::prepareCustomerListData()
 *
 * @method static array prepareCustomerExportTitles( array $titles ) Prepare 'Customer Groups' data in customers export dialog
 * @see \BooklyCustomerGroups\Lib\ProxyProviders\Local::prepareCustomerExportTitles()
 *
 * @method static \Bookly\Lib\Query prepareCustomerQuery( \Bookly\Lib\Query $query ) Prepare 'Customer Groups' query in customers table
 * @see \BooklyCustomerGroups\Lib\ProxyProviders\Local::prepareCustomerQuery()
 *
 * @method static string prepareCustomerSelect( string $select ) Prepare 'Customer Groups' select in customers table
 * @see \BooklyCustomerGroups\Lib\ProxyProviders\Local::prepareCustomerSelect()
 *
 * @method static float prepareCartTotalPrice( float $total, \Bookly\Lib\UserBookingData $user_data ) Prepare total price depends on group discount
 * @see \BooklyCustomerGroups\Lib\ProxyProviders\Local::prepareCartTotalPrice()
 *
 * @method static string prepareDefaultAppointmentStatus( string $status, int $group_id, string $created_from ) Get Default Appointment Status depends on group_id
 * @see \BooklyCustomerGroups\Lib\ProxyProviders\Local::prepareDefaultAppointmentStatus()
 *
 * @method static renderCartDiscountRow( array $columns, array $positions, string $layout ) Render "Group Discount" row on a Cart step
 * @see \BooklyCustomerGroups\Lib\ProxyProviders\Local::renderCartDiscountRow()
 *
 * @method static void renderCustomerDialog() Render 'Customer Group' row in edit customer dialog
 * @see \BooklyCustomerGroups\Lib\ProxyProviders\Local::renderCustomerDialog()
 *
 * @method static void renderCustomerExportDialogRow() Render 'Customer Group' row in export customer dialog
 * @see \BooklyCustomerGroups\Lib\ProxyProviders\Local::renderCustomerExportDialogRow()
 *
 * @method static void renderCustomerTableHeader() Render 'Customer Group' in customers table
 * @see \BooklyCustomerGroups\Lib\ProxyProviders\Local::renderCustomerTableHeader()
 *
 * @method static void renderPaymentsDialogRow( array $group ) Render 'Group Discount' row in Payments Details dialog
 * @see \BooklyCustomerGroups\Lib\ProxyProviders\Local::renderPaymentsDialogRow()
 *
 * @method static void renderServicesSubForm( array $service ) Render services groups visibility option
 * @see \BooklyCustomerGroups\Lib\ProxyProviders\Local::renderServicesSubForm()
 *
 * @method static void renderServiceVisibilityOption( array $service ) Render services visibility option 'based on groups'
 * @see \BooklyCustomerGroups\Lib\ProxyProviders\Local::renderServiceVisibilityOption()
 *
 */
abstract class CustomerGroups extends Base\ProxyInvoker
{

}