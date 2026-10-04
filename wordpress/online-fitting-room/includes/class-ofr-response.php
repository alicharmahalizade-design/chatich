<?php
/**
 * An endpoint's answer (see Online_Fitting_Room::respond()).
 */

defined( 'ABSPATH' ) || exit;

/** Thrown so that one handler serves REST and admin-ajax alike. */
class OFR_Response extends Exception {
	public $success;
	public $data;
	public $status;

	public function __construct( $success, $data, $status ) {
		parent::__construct( 'ofr-response' );
		$this->success = $success;
		$this->data    = $data;
		$this->status  = $status;
	}
}
