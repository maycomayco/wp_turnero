<?php
namespace Bookly\Lib\Proxy;

use Bookly\Lib;

/**
 * Class Files
 * Invoke local methods from Files add-on.
 *
 * @package Bookly\Lib\Proxy
 *
 * @method static \stdClass[] getAll() Get file custom fields
 * @see \BooklyFiles\Lib\ProxyProviders\Local::getAll()
 *
 * @method static array getAllIds() Get custom fields ids for file
 * @see \BooklyFiles\Lib\ProxyProviders\Local::getAllIds()
 *
 * @method static void attachFiles( array $custom_fields, Lib\Entities\CustomerAppointment $ca ) set file custom fields to customer appointment
 * @see \BooklyFiles\Lib\ProxyProviders\Local::attachFiles()
 *
 * @method static array saveCustomFields( array $custom_fields ) save custom fields
 * @see \BooklyFiles\Lib\ProxyProviders\Local::saveCustomFields()
 *
 * @method static array setFileNamesForCustomFields( array $data, array $custom_fields ) set file names for custom fields
 * @see \BooklyFiles\Lib\ProxyProviders\Local::setFileNamesForCustomFields()
 *
 * @method static array getFileNamesForCustomFields( array $custom_fields ) get file names for custom fields
 * @see \BooklyFiles\Lib\ProxyProviders\Local::getFileNamesForCustomFields()
 *
 * @method static void renderAppearance() Render button browse
 * @see \BooklyFiles\Lib\ProxyProviders\Local::renderAppearance()
 *
 * @method static void renderCustomFieldButton() Render custom fields row in customer profile
 * @see \BooklyFiles\Lib\ProxyProviders\Local::renderCustomFieldButton()
 *
 * @method static void renderCustomFieldTemplate( string $services_html ) Render custom fields row in customer profile
 * @see \BooklyFiles\Lib\ProxyProviders\Local::renderCustomFieldTemplate()
 *
 * @method static void renderCustomField( \stdClass $custom_field, array $cf_item ) Render custom fields row in customer profile
 * @see \BooklyFiles\Lib\ProxyProviders\Local::renderCustomField()
 *
 * @method static Lib\Query getSubQueryAttachmentExists() get query exists attachments in Customer Appointment
 * @see \BooklyFiles\Lib\ProxyProviders\Local::getSubQueryAttachmentExists()
 */
abstract class Files extends Lib\Base\ProxyInvoker
{

}