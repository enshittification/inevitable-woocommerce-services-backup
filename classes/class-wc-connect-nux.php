<?php

if ( ! class_exists( 'Jetpack_Install_Status' ) ) {
	abstract class Jetpack_Install_Status {
		const UNINSTALLED = 'uninstalled';
		const INSTALLED = 'installed';
		const ACTIVATED = 'activated';
		const DEV = 'dev';
		const CONNECTED = 'connected';
	}
}

if ( ! class_exists( 'WC_Connect_Nux' ) ) {

	class WC_Connect_Nux {

		function __construct() {
			$this->init_pointers();
			add_action( 'admin_init', array( $this, 'set_up_nux_notices' ) );
		}

		private function get_notice_states() {
			$states = get_user_meta( get_current_user_id(), 'wc_connect_nux_notices', true );

			if ( ! is_array( $states ) ) {
				return array();
			}

			return $states;
		}

		public function is_notice_dismissed( $notice ) {
			$notices = $this->get_notice_states();

			return isset( $notices[ $notice ] ) && $notices[ $notice ];
		}

		public function dismiss_notice( $notice ) {
			$notices = $this->get_notice_states();
			$notices[ $notice ] = true;
			update_user_meta( get_current_user_id(), 'wc_connect_nux_notices', $notices );
		}

		private function init_pointers() {
			add_filter( 'wc_services_pointer_woocommerce_page_wc-settings', array( $this, 'register_add_service_to_zone_pointer' ) );
		}

		public function show_pointers( $hook ) {
			/* Get admin pointers for the current admin page.
			 *
			 * @since 0.9.6
			 *
			 * @param array $pointers Array of pointers.
			 */
			$pointers = apply_filters( 'wc_services_pointer_' . $hook, array() );

			if ( ! $pointers || ! is_array( $pointers ) ) {
				return;
			}

			$dismissed_pointers = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );
			$valid_pointers = array();

			if( isset( $dismissed_pointers ) ) {
				foreach ( $pointers as $pointer ) {
					if ( ! in_array( $pointer['id'], $dismissed_pointers ) ) {
						$valid_pointers[] =  $pointer;
					}
				}
			} else {
				$valid_pointers = $pointers;
			}

			if ( empty( $valid_pointers ) ) {
				return;
			}

			wp_enqueue_style( 'wp-pointer' );
			wp_localize_script( 'wc_services_admin_pointers', 'wcSevicesAdminPointers', $valid_pointers );
			wp_enqueue_script( 'wc_services_admin_pointers' );
		}

		public function register_add_service_to_zone_pointer( $pointers ) {
			$pointers[] = array(
				'id' => 'wc_services_add_service_to_zone',
				'target' => 'th.wc-shipping-zone-methods',
				'options' => array(
					'content' => sprintf( '<h3>%s</h3><p>%s</p>',
						__( 'Add a WooCommerce shipping service to a Zone' ,'woocommerce-services' ),
						__( 'To ship products to customers using USPS or Canada Post, you will need to add them as a shipping method to an applicable zone. If you don\'t have any zones, add one first.', 'woocommerce-services' )
					),
					'position' => array( 'edge' => 'right', 'align' => 'left' ),
				)
			);
			return $pointers;
		}

		public function get_jetpack_install_status() {
			// check if Jetpack is activated
			if ( ! class_exists( 'Jetpack_Data' ) ) {
				// not activated, check if installed
				if ( 0 === validate_plugin( 'jetpack/jetpack.php' ) ) {
					return Jetpack_Install_Status::INSTALLED;
				}
				return Jetpack_Install_Status::UNINSTALLED;
			} else if ( defined( 'JETPACK_DEV_DEBUG' ) && true === JETPACK_DEV_DEBUG ) {
				// installed, activated, and dev mode on
				return Jetpack_Install_Status::DEV;
			}

			// installed, activated, dev mode off
			// check if connected
			$user_token = Jetpack_Data::get_access_token( JETPACK_MASTER_USER );
			if ( isset( $user_token->external_user_id ) ) { // always an int
				return Jetpack_Install_Status::CONNECTED;
			}

			return Jetpack_Install_Status::ACTIVATED;
		}

		public function should_display_nux_notice( $screen ) {
			if ( // Display if on any of these admin pages.
				( // Products list.
					'product' === $screen->post_type
					&& 'edit' === $screen->base
				)
				|| ( // Orders list.
					'shop_order' === $screen->post_type
					&& 'edit' === $screen->base
					)
				|| ( // Edit order page.
					'shop_order' === $screen->post_type
					&& 'post' === $screen->base
					)
				|| ( // WooCommerce settings.
					'woocommerce_page_wc-settings' === $screen->base
					)
				|| 'plugins' === $screen->base
			) {
				return true;
			}
			return false;
		}

		public function set_up_nux_notices() {
			$jetpack_install_status = $this->get_jetpack_install_status();

			$ajax_data = array(
				'nonce'                  => wp_create_nonce( 'wcs_install_banner' ),
				'initial_install_status' => $jetpack_install_status,
				'translations'           => array(
					'activating'   => __( 'Activating...', 'woocommerce-services' ),
					'connecting'   => __( 'Connecting...', 'woocommerce-services' ),
					'installError' => __( 'There was an error installing Jetpack. Please try installing it manually.', 'woocommerce-services' ),
					'defaultError' => __( 'Something went wrong. Please try connecting to Jetpack manually, or contact support on the WordPress.org forums.', 'woocommerce-services' ),
				),
			);

			switch ( $jetpack_install_status ) {
				case Jetpack_Install_Status::UNINSTALLED:
				case Jetpack_Install_Status::INSTALLED:
					wp_enqueue_script( 'wc_connect_banner' );
					wp_localize_script( 'wc_connect_banner', 'wcs_install_banner', $ajax_data );
					add_action( 'admin_notices', array( $this, 'show_banner_before_connection_get_access' ) );
					add_action( 'wp_ajax_activate_jetpack',
						array( $this, 'woocommerce_services_ajax_activate_jetpack' )
					);
					break;
				case Jetpack_Install_Status::ACTIVATED:
					add_action( 'admin_notices', array( $this, 'show_banner_before_connection_welcome' ) );
					add_action( 'admin_notices', array( $this, 'show_banner_before_connection_get_access' ) );
					break;
				case Jetpack_Install_Status::CONNECTED:
					add_action(
						'admin_notices',
						array( $this, 'show_banner_after_connection_see_how_it_works' )
					);
					break;
			}
		}

		public function show_banner_before_connection_get_access() {
			if ( ! $this->should_display_nux_notice( get_current_screen() ) ) {
				return;
			}

			$jetpack_status = $this->get_jetpack_install_status();

			$button_url = '#';
			$button_text = __( 'Connect your store to WordPress.com', 'woocommerce-services' );
			$should_install_jetpack = false;

			switch ( $jetpack_status ) {
				case Jetpack_Install_Status::UNINSTALLED:
					$button_text = __( 'Install Jetpack and connect your store to WordPress.com', 'woocommerce-services' );
					$should_install_jetpack = true;
					break;
				case Jetpack_Install_Status::INSTALLED:
					$button_text = __( 'Activate Jetpack and connect your store to WordPress.com', 'woocommerce-services' );
					$should_install_jetpack = true;
					break;
				case Jetpack_Install_Status::ACTIVATED:
					$current_url = add_query_arg( null, null );
					$button_url = Jetpack::init()->build_connect_url( true, $current_url, 'woocommerce-services' );
					break;
			}

			$this->show_nux_banner( array(
				'title'           => __( 'Get access to discount shipping labels by connecting to WordPress.com', 'woocommerce-services' ),
				'description'     => __( 'WooCommerce Services is almost ready to go. Once you connect your store to WordPress.com you can begin printing labels and saving money with discounted shipping rates all from your dashboard.', 'woocommerce-services' ),
				'url'             => $button_url,
				'button_text'     => $button_text,
				'image_url'       => 'https://cldup.com/WpkrskfH_r.jpg',
				'should_show_jp'  => true,
				'will_use_script' => $should_install_jetpack,
			) );
		}

		public function show_banner_before_connection_welcome() {
			if ( ! $this->should_display_nux_notice( get_current_screen() ) ) {
				return;
			}

			$redirect = admin_url( 'plugins.php' );
			$connect_url = Jetpack::init()->build_connect_url( true, $redirect, 'woocommerce-services' );
			$this->show_nux_banner( array(
				'title'          => __( 'Welcome to WooCommerce services', 'woocommerce-services' ),
				'description'    => __( 'WooCommerce services makes shipping a breeze. Print a label and take advantage of discounted shipping rates right as you process your order, all from the convenience of your WordPress dashboard.', 'woocommerce-services' ),
				'url'            => $connect_url,
				'button_text'    => __( 'Connect your store to WordPress.com', 'woocommerce-services' ),
				'image_url'      => 'https://cldup.com/WpkrskfH_r.jpg',
				'should_show_jp' => true,
			) );
		}

		public function show_banner_after_connection_see_how_it_works() {
			if ( ! $this->should_display_nux_notice( get_current_screen() ) ) {
				return;
			}

			$this->show_nux_banner( array(
				'title'          => __( 'You now have access to discount shipping rates and printing services directly within your dashboard!', 'woocommerce-services' ),
				'description'    => __( 'You can begin purchasing discounted labels from USPS, and printing them at any time.', 'woocommerce-services' ),
				'url'            => '#',
				'button_text'    => __( 'See how it works', 'woocommerce-services' ),
				'image_url'      => 'https://cldup.com/opSeqZzABZ.jpg',
				'should_show_jp' => false,
			) );
		}

		public function show_nux_banner( $content ) {
			?>
			<div class="notice wcs-nux__notice" style="display:flex;">
				<div class="wcs-nux__notice-logo">
					<img src="<?php echo esc_url( $content['image_url'] );  ?>">
				</div>
				<div class="wcs-nux__notice-content">
					<h1><?php echo esc_html( $content['title'] ); ?></h1>
					<p><?php echo esc_html( $content['description'] ); ?></p>
					<a
						href="<?php echo esc_url( $content['url'] ); ?>"
						<?php if ( isset( $content['will_use_script'] ) && $content['will_use_script'] ) : ?>
							class="woocommerce-services__install-jetpack"
						<?php endif; ?>
					>
						<?php echo esc_html( $content['button_text'] ); ?>
					</a>
					<?php if ( $content['should_show_jp'] ) : ?>
						<p>By connecting your site you agree to our fascinating <a href="http://google.com">Terms of Service</a> and to <a>share details</a> with WordPress.com.</p>
					<?php endif; ?>
				</div>
				<?php if ( $content['should_show_jp'] ) : ?>
					<div class="wcs-nux__notice-jetpack">
						<img src="https://cldup.com/BxbWlzSyPC.jpg">
						<p>Powered by Jetpack</p>
					</div>
				<?php endif; ?>
			</div>
			<?php
		}

		/**
		 * Activates Jetpack after an ajax request
		 */
		public function woocommerce_services_ajax_activate_jetpack() {
			check_ajax_referer( 'wcs_install_banner' );

			$result = activate_plugin( 'jetpack/jetpack.php' );

			if ( is_null( $result ) ) {
				// The function activate_plugin() returns NULL on success.
				echo 'success';
			} else {
				if ( is_wp_error( $result ) ) {
					echo esc_html( $result->get_error_message() );
				} else {
					echo 'error';
				}
			}

			wp_die();
		}
	}
}
