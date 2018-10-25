<?php
namespace Bookly\Lib\Proxy;

use Bookly\Lib as BooklyLib;

/**
 * Class CustomerInformation
 * Invoke local methods from Customer Information add-on.
 *
 * @package Bookly\Lib\Proxy
 *
 * @method static \stdClass[] getFields( $exclude = array() ) Get fields.
 * @see \BooklyCustomerInformation\Lib\ProxyProviders\Local::getFields()
 *
 * @method static \stdClass[] getFieldsWhichMayHaveData() Get fields which may have data (no Text Content).
 * @see \BooklyCustomerInformation\Lib\ProxyProviders\Local::getFieldsWhichMayHaveData()
 *
 * @method static \stdClass[] getTranslatedFields() Get translated fields.
 * @see \BooklyCustomerInformation\Lib\ProxyProviders\Local::getTranslatedFields()
 *
 * @method static array prepareCustomerListData( array $customer_data, array $row ) Prepare customer info fields for customers list.
 * @see \BooklyCustomerInformation\Lib\ProxyProviders\Local::prepareCustomerListData()
 *
 * @method static array prepareCustomerFormData( array $params ) Prepare customer info fields before saving customer form.
 * @see \BooklyCustomerInformation\Lib\ProxyProviders\Local::prepareCustomerFormData()
 *
 * @method static void renderCustomerDialog() Render fields in customer dialog.
 * @see \BooklyCustomerInformation\Lib\ProxyProviders\Local::renderCustomerDialog()
 *
 * @method static void renderDetailsStep( BooklyLib\UserBookingData $userData ) Render fields at Details step.
 * @see \BooklyCustomerInformation\Lib\ProxyProviders\Local::renderDetailsStep()
 *
 * @method static array validate( array $errors, array $values ) Validate fields.
 * @see \BooklyCustomerInformation\Lib\ProxyProviders\Local::validate()
 *
 * @method static void addBooklyMenuItem() Add 'Customer Information' to Bookly menu.
 * @see \BooklyCustomerInformation\Lib\ProxyProviders\Local::addBooklyMenuItem()
 */
abstract class CustomerInformation extends BooklyLib\Base\ProxyInvoker
{

}