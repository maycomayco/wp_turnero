<?php
namespace Bookly\Backend\Modules\License;

use Bookly\Lib;

/**
 * Class Components
 * @package Bookly\Backend\Modules\Calendar
 */
class Components extends Lib\Base\Components
{

    private function enqueueAssets()
    {
        $this->enqueueStyles( array(
            'backend' => array( 'bootstrap/css/bootstrap-theme.min.css', ),
        ) );

        $this->enqueueScripts( array(
            'module'  => array( 'js/license.js' => array( 'jquery' ), ),
            'backend' => array(
                'js/alert.js' => array( 'jquery' ),
                'bootstrap/js/bootstrap.min.js' => array( 'jquery' ),
            ),
        ) );

        wp_localize_script( 'bookly-license.js', 'LicenseL10n', array(
            'csrf_token'   => \Bookly\Lib\Utils\Common::getCsrfToken()
        ) );
    }

    public function renderLicenseRequired()
    {
        $states = Lib\Config::getPluginVerificationStates();
        $role   = Lib\Utils\Common::isCurrentUserAdmin() ? 'admin' : 'staff';
        if ( Lib\Config::booklyExpired() ) {
            $this->enqueueAssets();
            $this->render( 'board', array( 'board_body' => $this->render( $role . '_grace_ended', compact( 'states' ), false ) ) );
        } elseif ( $states['grace_remaining_days'] ) {
            // Some plugin in grace period
            $this->enqueueAssets();
            $days_text = array( '{days}' => sprintf( _n( '%d day', '%d days', $states['grace_remaining_days'], 'bookly' ), $states['grace_remaining_days'] ) );
            $this->render( 'board', array( 'board_body' => $this->render( $role . '_grace', compact( 'states', 'days_text' ), false ) ) );
        }
    }

    /**
     * @param bool $bookly_page
     */
    public function renderLicenseNotice( $bookly_page )
    {
        $states = Lib\Config::getPluginVerificationStates();
        if ( ! $bookly_page && Lib\Config::booklyExpired() ) {
            $this->enqueueAssets();
            $replace_data = array(
                '{url}'  => Lib\Utils\Common::escAdminUrl( \Bookly\Backend\Modules\Settings\Controller::page_slug, array( 'tab' => 'purchase_code' ) ),
            );
            $this->render( 'notice_grace_ended', compact( 'replace_data' ) );
        } elseif ( $states['grace_remaining_days'] ) {
            if ( ! $bookly_page ) {
                $this->enqueueAssets();
            }
            $replace_data = array(
                '{url}'  => Lib\Utils\Common::escAdminUrl( \Bookly\Backend\Modules\Settings\Controller::page_slug, array( 'tab' => 'purchase_code' ) ),
                '{days}' => sprintf( _n( '%d day', '%d days', $states['grace_remaining_days'], 'bookly' ), $states['grace_remaining_days'] ),
            );
            $this->render( 'notice_grace', compact( 'replace_data' ) );
        }
    }

    public function renderPurchaseNotice()
    {
        $this->render( 'purchase_reminder' );
    }
}