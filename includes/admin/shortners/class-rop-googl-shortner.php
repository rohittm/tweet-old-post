<?php
/**
 * The file that defines this shortner specific functionality.
 *
 * @link       https://themeisle.com/
 * @since      8.0.0
 *
 * @package    Rop
 * @subpackage Rop/includes/admin/shortners
 */

/**
 * Class Rop_Googl_Shortner
 *
 * @since   8.0.0
 * @link    https://themeisle.com/
 */
class Rop_Googl_Shortner extends Rop_Url_Shortner_Abstract {

	/**
	 * Method to inject functionality into constructor.
	 *
	 * @since   8.0.0
	 * @access  public
	 * @return mixed
	 */
	public function init() {
		$this->service_name = 'goo.gl';
		$this->credentials  = array(
			'key' => '',
		);
	}

	/**
	 * Method to retrieve the shorten url from the API call.
	 *
	 * @since   8.0.0
	 * @access  public
	 * @param   string $url The url to shorten.
	 * @return string
	 */
	public function shorten_url( $url ) {

		$response = $this->callAPI(
			'https://www.googleapis.com/urlshortener/v1/url?key=' . $this->credentials['key'],
			array( 'method' => 'json', 'json' => true ),
			array( 'longUrl' => urldecode( $url ) ),
			array( 'Content-Type' => 'application/json' )
		);
		$shortURL = $url;
		if ( intval( $response['error'] ) == 200 && ! isset( $response['response']['error'] ) ) {
			$shortURL = $response['response']['id'];
		}
		return $shortURL;
	}
}
