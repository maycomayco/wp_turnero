<?php
namespace Bookly\Lib\Proxy;

use Bookly\Lib\Base;

/**
 * Class Packages
 * Invoke local methods from Packages add-on.
 *
 * @package Bookly\Lib\Proxy
 *
 * @method static void addBooklyMenuItem() Add 'Packages' to Bookly menu
 * @see \BooklyPackages\Lib\ProxyProviders\Local::addBooklyMenuItem()
 *
 * @method static void renderServicePackage( array $service, array $service_collection ) Render sub services for packages
 * @see \BooklyPackages\Lib\ProxyProviders\Local::renderServicePackage()
 *
 * @method static void renderPackageScheduleDialog()
 * @see \BooklyPackages\Lib\ProxyProviders\Local::renderPackageScheduleDialog()
 *
 * @method static array prepareDataForAppointmentForm( array $data )
 * @see \BooklyPackages\Lib\ProxyProviders\Local::prepareDataForAppointmentForm()
 */
abstract class Packages extends Base\ProxyInvoker
{

}