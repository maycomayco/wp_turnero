<?php
namespace Bookly\Lib\Proxy;

use Bookly\Lib;

/**
 * Class WaitingList
 * Invoke local methods from Waiting List add-on.
 *
 * @package Bookly\Lib\Proxy
 *
 * @method static void handleParticipantsChange( Lib\Entities\Appointment $appointment ) Handle the change of participants of given appointment
 * @see \BooklyWaitingList\Lib\ProxyProviders\Local::handleParticipantsChange()
 *
 * @method static void renderAppearanceTimeStepInfoText() Render info text at time step in appearance
 * @see \BooklyWaitingList\Lib\ProxyProviders\Local::renderAppearanceTimeStepInfoText()
 *
 * @method static void renderTimeStepInfoText() Render info text at time step
 * @see \BooklyWaitingList\Lib\ProxyProviders\Local::renderTimeStepInfoText()
 */
abstract class WaitingList extends Lib\Base\ProxyInvoker
{

}