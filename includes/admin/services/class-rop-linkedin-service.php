<?php
/**
 * The file that defines the Linkedin Service specifics.
 *
 * A class that is used to interact with Linkedin.
 * It extends the Rop_Services_Abstract class.
 *
 * @link       https://themeisle.com/
 * @since      8.0.0
 *
 * @package    Rop
 * @subpackage Rop/includes/admin/services
 */

/**
 * Class Rop_Linkedin_Service
 *
 * @since   8.0.0
 * @link    https://themeisle.com/
 */
class Rop_Linkedin_Service extends Rop_Services_Abstract {

	/**
	 * An instance of authenticated LinkedIn user.
	 *
	 * @since   8.0.0
	 * @access  private
	 * @var     array $user An instance of the current user.
	 */
	public $user;
	/**
	 * Defines the service name in slug format.
	 *
	 * @since   8.0.0
	 * @access  protected
	 * @var     string $service_name The service name.
	 */
	protected $service_name = 'linkedin';
	/**
	 * Permissions required by the app.
	 *
	 * @since   8.0.0
	 * @access  protected
	 * @var     array $scopes The scopes to authorize with LinkedIn.
	 */
	protected $scopes = array( 'r_basicprofile', 'r_emailaddress', 'rw_company_admin', 'w_share' );


	/**
	 * Method to inject functionality into constructor.
	 * Defines the defaults and settings for this service.
	 *
	 * @since   8.0.0
	 * @access  public
	 */
	public function init() {
		$this->display_name = 'LinkedIn';
	}

	/**
	 * Method to expose desired endpoints.
	 * This should be invoked by the Factory class
	 * to register all endpoints at once.
	 *
	 * @since   8.0.0
	 * @access  public
	 * @return mixed
	 */
	public function expose_endpoints() {
		$this->register_endpoint( 'authorize', 'authorize' );
		$this->register_endpoint( 'authenticate', 'maybe_authenticate' );
	}

	/**
	 * Method for authorizing the service.
	 *
	 * @codeCoverageIgnore
	 *
	 * @since   8.0.0
	 * @access  public
	 * @return mixed
	 */
	public function authorize() {
		header( 'Content-Type: text/html' );
		if ( ! session_id() ) {
			session_start();
		}

		$credentials = $_SESSION['rop_linkedin_credentials'];

		$api         = $this->get_api( $credentials['client_id'], $credentials['secret'] );
		$accessToken = $api->getAccessToken( $_GET['code'] );

		$_SESSION['rop_linkedin_token'] = $accessToken->getToken();

		parent::authorize();
		// echo '<script>window.setTimeout("window.close()", 500);</script>';
	}

	/**
	 * Method to retrieve the api object.
	 *
	 * @since   8.0.0
	 * @access  public
	 *
	 * @param   string $client_id The Client ID. Default empty.
	 * @param   string $client_secret The Client Secret. Default empty.
	 *
	 * @return \LinkedIn\Client Client Linkedin.
	 */
	public function get_api( $client_id = '', $client_secret = '' ) {
		if ( $this->api == null ) {
			$this->set_api( $client_id, $client_secret );
		}

		return $this->api;
	}

	/**
	 * Method to define the api.
	 *
	 * @since   8.0.0
	 * @access  public
	 *
	 * @param   string $client_id The Client ID. Default empty.
	 * @param   string $client_secret The Client Secret. Default empty.
	 *
	 * @return mixed
	 */
	public function set_api( $client_id = '', $client_secret = '' ) {
		if ( ! class_exists( '\LinkedIn\Client' ) ) {
			return false;
		}
		$this->api = new \LinkedIn\Client( $client_id, $client_secret );

		$this->api->setRedirectUrl( $this->get_legacy_url( 'linkedin' ) );
	}

	/**
	 * Method for maybe authenticate the service.
	 *
	 * @codeCoverageIgnore
	 *
	 * @since   8.0.0
	 * @access  public
	 * @return mixed
	 */
	public function maybe_authenticate() {
		if ( ! session_id() ) {
			session_start();
		}
		if ( ! $this->is_set_not_empty(
			$_SESSION, array(
				'rop_linkedin_credentials',
				'rop_linkedin_token',
			)
		) ) {
			return false;
		}
		if ( ! $this->is_set_not_empty(
			$_SESSION['rop_linkedin_credentials'], array(
				'client_id',
				'secret',
			)
		) ) {
			return false;
		}

		$credentials          = $_SESSION['rop_linkedin_credentials'];
		$token                = $_SESSION['rop_linkedin_token'];
		$credentials['token'] = $token;

		unset( $_SESSION['rop_linkedin_credentials'] );
		unset( $_SESSION['rop_linkedin_token'] );

		return $this->authenticate( $credentials );
	}

	/**
	 * Method for authenticate the service.
	 *
	 * @codeCoverageIgnore
	 *
	 * @since   8.0.0
	 * @access  public
	 * @return mixed
	 */
	public function authenticate( $args ) {
		if ( ! $this->is_set_not_empty(
			$args, array(
				'client_id',
				'token',
				'secret',
			)
		) ) {
			return false;
		}

		$token = $args['token'];

		$api = $this->get_api( $args['client_id'], $args['secret'] );

		$this->credentials['token']     = $token;
		$this->credentials['client_id'] = $args['client_id'];
		$this->credentials['secret']    = $args['secret'];

		$api->setAccessToken( new LinkedIn\AccessToken( $args['token'] ) );
		try {
			$profile = $api->api(
				'people/~:(id,email-address,first-name,last-name,formatted-name,picture-url)', array(), 'GET'
			);
		} catch ( Exception $e ) {
			$this->logger->alert_error( 'Can not get linkedin user details. Error ' . $e->getMessage() );
		}
		if ( ! isset( $profile['id'] ) ) {
			return false;
		}
		$this->service = array(
			'id'                 => $profile['id'],
			'service'            => $this->service_name,
			'credentials'        => $this->credentials,
			'public_credentials' => array(
				'client_id' => array(
					'name'    => 'Client ID',
					'value'   => $this->credentials['client_id'],
					'private' => false,
				),
				'secret'    => array(
					'name'    => 'Client Secret',
					'value'   => $this->credentials['secret'],
					'private' => true,
				),
			),
			'available_accounts' => $this->get_users( $profile ),
		);

		return true;

	}

	/**
	 * Utility method to retrieve users from the Twitter account.
	 *
	 * @codeCoverageIgnore
	 *
	 * @since   8.0.0
	 * @access  public
	 *
	 * @param   object $data Response data from Twitter.
	 *
	 * @return array
	 */
	private function get_users( $data = null ) {
		if ( empty( $data ) ) {
			return array();
		}
		$img = '';
		if ( isset( $data['pictureUrl'] ) && $data['pictureUrl'] ) {
			$img = $data['pictureUrl'];
		}
		$user_details            = $this->user_default;
		$user_details['id']      = $data['id'];
		$user_details['account'] = $this->normalize_string( $data['formattedName'] );
		$user_details['user']    = $this->normalize_string( $data['formattedName'] );
		$user_details['img']     = $img;

		return array( $user_details );
	}


	/**
	 * Method to register credentials for the service.
	 *
	 * @since   8.0.0
	 * @access  public
	 *
	 * @param   array $args The credentials array.
	 */
	public function set_credentials( $args ) {
		$this->credentials = $args;
	}

	/**
	 * Method to request a token from api.
	 *
	 * @codeCoverageIgnore
	 *
	 * @since   8.0.0
	 * @access  protected
	 * @return mixed
	 */
	public function request_api_token() {
		if ( ! session_id() ) {
			session_start();
		}

		$api           = $this->get_api();
		$request_token = $api->oauth( 'oauth/request_token', array( 'oauth_callback' => $this->get_legacy_url( 'linkedin' ) ) );

		$_SESSION['rop_twitter_request_token'] = $request_token;

		return $request_token;
	}

	/**
	 * Returns information for the current service.
	 *
	 * @since   8.0.0
	 * @access  public
	 * @return mixed
	 */
	public function get_service() {
		return $this->service;
	}

	/**
	 * Generate the sign in URL.
	 *
	 * @since   8.0.0
	 * @access  public
	 *
	 * @param   array $data The data from the user.
	 *
	 * @return mixed
	 */
	public function sign_in_url( $data ) {
		$credentials = $data['credentials'];
		// @codeCoverageIgnoreStart
		if ( ! session_id() ) {
			session_start();
		}
		// @codeCoverageIgnoreEnd
		$_SESSION['rop_linkedin_credentials'] = $credentials;
		$this->set_api( $credentials['client_id'], $credentials['secret'] );
		$api = $this->get_api();
		$url = $api->getLoginUrl( $this->scopes );

		return $url;
	}

	/**
	 * Method for publishing with Twitter service.
	 *
	 * @since   8.0.0
	 * @access  public
	 *
	 * @param   array $post_details The post details to be published by the service.
	 * @param   array $args Optional arguments needed by the method.
	 *
	 * @return mixed
	 */
	public function share( $post_details, $args = array() ) {
		$this->set_api( $this->credentials['client_id'], $this->credentials['secret'] );
		$api   = $this->get_api();
		$token = new \LinkedIn\AccessToken( $this->credentials['token'] );
		$api->setAccessToken( $token );

		$new_post = array(
			'comment'    => '',
			'content'    => array(
				'title'         => '',
				'description'   => '',
				'submitted-url' => '',
			),
			'visibility' => array(
				'code' => 'anyone',
			),
		);
		if ( ! empty( $post_details['post_image'] ) ) {
			$new_post['content']['submitted-image-url'] = $post_details['post_image'];
		}

		$new_post['comment']                  = $post_details['content'];
		$new_post['content']['description']   = $post_details['content'];
		$new_post['content']['title']         = get_the_title( $post_details['post_id'] );
		$new_post['content']['submitted-url'] = $this->get_url( $post_details );

		$new_post['visibility']['code'] = 'anyone';

		try {

			$api->post( 'people/~/shares?format=json', $new_post );
			$this->logger->alert_success(
				sprintf(
					'Successfully shared %s to %s on %s ',
					get_the_title( $post_details['post_id'] ),
					$args['user'],
					$post_details['service']
				)
			);
		} catch ( Exception $exception ) {
			$this->logger->alert_error( 'Can not share to linkedin. Error:  ' . $exception->getMessage() );

			return false;
		}

		return true;
	}
}
