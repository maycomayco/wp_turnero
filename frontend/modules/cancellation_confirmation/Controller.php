<?php
namespace Bookly\Frontend\Modules\CancellationConfirmation;

use Bookly\Lib;

/**
 * Class Controller
 * @package Bookly\Frontend\Modules\CancellationConfirmation
 */
class Controller extends Lib\Base\Controller
{
    public function renderShortCode( $attributes )
    {
        // Disable caching.
        Lib\Utils\Common::noCache();

        // Prepare URL for AJAX requests.
        $ajax_url = admin_url( 'admin-ajax.php' );

        $token = $this->getParameter( 'bookly-appointment-token', '' );

        return $this->render( 'short_code', compact( 'ajax_url', 'token' ), false );
    }
}