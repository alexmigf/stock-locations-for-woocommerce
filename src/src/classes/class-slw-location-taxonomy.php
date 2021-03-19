<?php
/**
 * SLW Location Taxonomy Class
 *
 * @since 1.0.0
 */

namespace SLW\SRC\Classes;

if ( !defined( 'WPINC' ) ) {
	die;
}

if(!class_exists('SlwLocationTaxonomy')) {

	class SlwLocationTaxonomy
	{
		public static $tax_plural_name = 'locations';
		public static $tax_singular_name = 'location';
		private $plugin_settings;

		/**
		 * Construct.
		 *
		 * @since 1.1.0
		 */
		public function __construct()
		{
			// get settings
			$this->plugin_settings = get_option( 'slw_settings' );

			add_action( 'init', array($this, 'create_taxonomy'), 1 );
			add_action( 'location_edit_form', array($this, 'hideFields') );
			add_action( 'location_add_form', array($this, 'hideFields') );
			add_filter( 'manage_edit-location_columns', array($this, 'editColumns') );
			add_action( 'location_edit_form', array($this, 'formFields'), 100, 2 );
			add_action( 'location_add_form_fields', array($this, 'formFields'), 10, 2 );
			add_action( 'edited_location', array($this, 'formSave'), 10, 2 );
			add_action( 'created_location', array($this, 'formSave'), 10, 2 );

			if( isset( $this->plugin_settings['default_location_in_frontend_selection'] ) ) {
				add_action( 'admin_footer', array( $this, 'product_default_location_selection' ), 99 );
				add_action( 'wp_ajax_slw_save_product_default_location', array( $this, 'ajax_save_product_default_location' ) );
				add_action( 'wp_ajax_slw_remove_product_default_location', array( $this, 'ajax_remove_product_default_location' ) );
			}
		}

		/**
		 * Returns the taxonomy default names.
		 *
		 * @since 1.0.0
		 * @return array
		 */
		public static function get_tax_names( $type )
		{
			$data = [
				'plural' 	=> self::$tax_plural_name,
				'singular' 	=> self::$tax_singular_name
			];

			return $data[$type];
		}

		/**
		 * Creates the taxonomy.
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function create_taxonomy()
		{

			$labels = array(
				'name'                       => __('Location'),
				'singular_name'              => __('Location'),
				'menu_name'                  => __('Stock locations'),
				'all_items'                  => __('All Items'),
				'parent_item'                => __('Parent Item'),
				'parent_item_colon'          => __('Parent Item:'),
				'new_item_name'              => __('New Item Name'),
				'add_new_item'               => __('Add New Item'),
				'edit_item'                  => __('Edit Item'),
				'update_item'                => __('Update Item'),
				'separate_items_with_commas' => __('Separate Item with commas'),
				'search_items'               => __('Search Items'),
				'add_or_remove_items'        => __('Add or remove Items'),
				'choose_from_most_used'      => __('Choose from the most used Items'),
			);
			$capabilities = array(
				'manage_terms'               => 'manage_woocommerce',
				'edit_terms'                 => 'manage_woocommerce',
				'delete_terms'               => 'manage_woocommerce',
				'assign_terms'               => 'manage_woocommerce',
			);
			$args = array(
				'labels'                     => $labels,
				'hierarchical'               => true,
				'public'                     => true,
				'show_ui'                    => true,
				'show_admin_column'          => true,
				'show_in_rest'               => true,
				'show_in_nav_menus'          => true,
				'show_tagcloud'              => true,
				'capabilities'               => $capabilities,
			);

			register_taxonomy( $this->get_tax_names('singular'), 'product', $args );
			register_taxonomy_for_object_type( $this->get_tax_names('singular'), 'product' );

		}

		/**
		 * Hide unused fields from admin
		 */
		public function hideFields()
		{
			echo '<style>.term-description-wrap, .term-parent-wrap { display:none; } </style>';
		}

		/**
		 * Change columns displayed in table
		 *
		 * @param $columns
		 *
		 * @return mixed
		 */
		public function editColumns( $columns ) {
			if(isset($columns['description'])) {
				unset($columns['description']);
			}

			return $columns;
		}

		/**
		 * Form fields
		 *
		 * @param $tag
		 */
		public function formFields( $tag ) {
			// Defaults
			$view = 'taxonomy-fields-new';
			$default_location = 0;
			$primary_location = 0;
			$auto_order_allocate = 0;
			$auto_order_allocate_priority = 0;
			$location_email = '';

			// Is edit screen
			if (is_object($tag)) {
				$view = 'taxonomy-fields-edit';
				$default_location = get_term_meta($tag->term_id, 'slw_default_location', true);
				$primary_location = get_term_meta($tag->term_id, 'slw_backorder_location', true);
				$auto_order_allocate = get_term_meta($tag->term_id, 'slw_auto_allocate', true);
				$auto_order_allocate_priority = get_term_meta($tag->term_id, 'slw_location_priority', true);
				$location_email = get_term_meta($tag->term_id, 'slw_location_email', true);
			}
			
			// if email notifications are disable
			if( ! isset($this->plugin_settings['location_email_notifications']) || $this->plugin_settings['location_email_notifications'] != 'on' ) {
				$location_email = null;
			}

			// Echo view
			echo \SLW\SRC\Helpers\view($view, [
				'default_location' 				=> $default_location,
				'primary_location' 				=> $primary_location,
				'auto_order_allocate' 			=> $auto_order_allocate,
				'auto_order_allocate_priority'	=> $auto_order_allocate_priority,
				'location_email'				=> $location_email
			]);
		}

		/**
		 * Save term meta
		 *
		 * @param $term_id
		 */
		public function formSave( $term_id ) {
			if ($_POST && isset($_POST['auto_order_allocate']) && isset($_POST['auto_order_allocate']) && isset($_POST['auto_order_allocate_priority'])) {
				update_term_meta($term_id, 'slw_default_location', $_POST['default_location']);
				update_term_meta($term_id, 'slw_backorder_location', $_POST['primary_location']);
				update_term_meta($term_id, 'slw_auto_allocate', $_POST['auto_order_allocate']);
				update_term_meta($term_id, 'slw_location_priority', $_POST['auto_order_allocate_priority']);
				if( isset($_POST['location_email']) ) {
					update_term_meta($term_id, 'slw_location_email', sanitize_text_field($_POST['location_email']));
				}
			}
		}

		public function product_default_location_selection()
		{
			$product_id = get_the_ID();
			if( empty( $product_id ) ) return;

			$default_location = ! empty( get_post_meta( $product_id, '_slw_default_location', true ) ) ? get_post_meta( $product_id, '_slw_default_location', true ) : 0;
			?>
			<script>
				( function( $ ){
					$( document ).ready( function() {
						slwHideLocationsYoastMakePrimary();
						slwWcProductEditSelectDefaultLocation();
					} );

					function slwWcProductEditSelectDefaultLocation()
					{
						let elem  = $( document ).find( '.post-type-product #taxonomy-location' );
						let items = elem.find( '#locationchecklist > li > label' );

						$( items ).each( function( index ) {
							let term_id          = $( this ).find( 'input' ).val();
							let is_checked       = $( this ).find( 'input' ).is( ':checked' );
							let product_id       = <?php echo $product_id; ?>;
							let default_location = <?php echo $default_location; ?>;

							if( is_checked ) {
								if( term_id != default_location ) {
									$( this ).append( '<span style="float:right;"><a class="slw_location_make_default" data-product_id="'+product_id+'" data-term_id="'+term_id+'"><?php _e( 'Make default', 'stock-locations-for-woocommerce' ); ?></a></span>' );
								} else {
									$( this ).append( '<span style="float:right;"><a class="slw_location_remove_default" data-product_id="'+product_id+'" style="color:#d63638;"><?php _e( 'Remove', 'stock-locations-for-woocommerce' ); ?></a></span>' );
								}
							}
						} );
					}

					function slwHideLocationsYoastMakePrimary()
					{
						$( document ).find( '#taxonomy-location .wpseo-make-primary-term' ).hide();
					}
				}( jQuery ) );
			</script>
			<?php
		}

		public function ajax_save_product_default_location()
		{
			check_ajax_referer( 'slw_nonce', 'nonce' );

			if( isset( $_POST['product_id'] ) && isset( $_POST['term_id'] ) ) {
				$product_id = sanitize_text_field( $_POST['product_id'] );
				$term_id    = sanitize_text_field( $_POST['term_id'] );

				// save product default location
				$response   = update_post_meta( $product_id, '_slw_default_location', $term_id );

				if( $response ) {
					wp_send_json_success( array( 'message' => __( 'Product default location saved!' ) ) );
				} else {
					wp_send_json_error( array( 'message' => __( 'Something went wrong saving the default location. Please check WooCommerce logs.' ) ) );
				}
			}
		}

		public function ajax_remove_product_default_location()
		{
			check_ajax_referer( 'slw_nonce', 'nonce' );

			if( isset( $_POST['product_id'] ) ) {
				$product_id = sanitize_text_field( $_POST['product_id'] );

				// remove product default location
				$response   = delete_post_meta( $product_id, '_slw_default_location' );

				if( $response ) {
					wp_send_json_success( array( 'message' => __( 'Product default location removed!' ) ) );
				} else {
					wp_send_json_error( array( 'message' => __( 'Something went wrong removing the default location. Please check WooCommerce logs.' ) ) );
				}
			}
		}

	}

}
