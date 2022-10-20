<?php
/**
 * Pre-built websites importer helper
 *
 * @package Betheme
 * @author Muffin group
 * @link https://muffingroup.com
 * @version 1.0
 */

// error_reporting(E_ALL);
// ini_set("display_errors", 1);

if ( ! defined( 'ABSPATH' ) ){
	exit;
}

class Mfn_Importer_Helper {

  public $demos = [];

	public $demo = ''; // current demo
	public $builder = ''; // current builder
	public $demo_builder = ''; // current demo + builder, ie. shop_el
  public $demo_path = ''; // path to directory with downloaded demo content
  public $url = ''; // current demo url

	/**
	 * Constructor
	 */

	function __construct( $demo, $builder = false ) {

		// set demos list

		require( get_theme_file_path('/functions/importer/demos.php') );

    $this->demos = $demos;

    $this->demo = $demo;
    $this->builder = $builder;

    $this->demo_builder = $demo;
    if( 'elementor' == $builder ){
      $this->demo_builder .= '_el';
    }

    $upload_dir = wp_upload_dir();
		$this->demo_path = wp_normalize_path( $upload_dir['basedir'] .'/betheme/websites/'. $this->demo_builder .'/'. $this->demo_builder );

    $this->url = $this->get_demo_url();
	}

  /**
   * MAIN functions ----------
   */

  /**
	 * Database reset
	 */

	public static function database_reset(){

		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE $wpdb->posts" );
		$wpdb->query( "TRUNCATE TABLE $wpdb->postmeta" );
		$wpdb->query( "TRUNCATE TABLE $wpdb->comments" );
		$wpdb->query( "TRUNCATE TABLE $wpdb->commentmeta" );
		$wpdb->query( "TRUNCATE TABLE $wpdb->terms" );
		$wpdb->query( "TRUNCATE TABLE $wpdb->termmeta" );
		$wpdb->query( "TRUNCATE TABLE $wpdb->term_taxonomy" );
		$wpdb->query( "TRUNCATE TABLE $wpdb->term_relationships" );
		$wpdb->query( "TRUNCATE TABLE $wpdb->links" );

		$wpdb->query( $wpdb->prepare(
			"DELETE FROM $wpdb->options
    	WHERE `option_name` REGEXP %s",
			'mfn_header|mfn_footer' ) );

		return true;
	}

  /**
   * Download package
   */

  public function download_package(){

    // Importer remote API

    require_once( get_theme_file_path( '/functions/importer/class-mfn-importer-api.php' ) );

    $importer_api = new Mfn_Importer_API( $this->demo_builder );
    $demo_path = $importer_api->remote_get_demo();

    if( ! $demo_path ){

      echo 'Remote API error<br />';

    } elseif( is_wp_error( $demo_path ) ){

      echo 'Remote API WP error<br />';

    } else {

      return true;

    }

    return false;
  }

  /**
   * Delete temporary directory
   */

  public function delete_temp_dir(){

    // Importer remote API

    require_once( get_theme_file_path( '/functions/importer/class-mfn-importer-api.php' ) );

    $importer_api = new Mfn_Importer_API( $this->demo_builder );
    $importer_api->delete_temp_dir();

    return true;
  }

  /**
   * Import content
   */

  public function content( $attachments = false ){

    $result = $this->import_xml( $attachments );

    if( ! $result ){
      return false;
    }

 		// Muffin Builder ! do not IF replace_builder(), Be templates are used also in Elementor demos

 		$this->replace_builder();

 		// Elementor

    if( 'elementor' == $this->builder ){

   		$this->replace_elementor();
   		$this->elementor_settings();

   		if ( class_exists( 'Elementor\Plugin' ) ){
   			Elementor\Plugin::$instance->files_manager->clear_cache();
   		}

    }

    return true;
  }

  /**
   * Theme options
   */

  public function options(){

		$file = wp_normalize_path( $this->demo_path .'/options.txt' );

		$file_data 	= $this->get_file_data( $file );
		$options = unserialize( call_user_func( 'base'.'64_decode', $file_data ) );

		if( is_array( $options ) ){

			// @since 26.4 options.txt contains header and footer conditions

			if( ! empty($options['betheme']) ){

				// after 26.4

				$theme_options = $options['betheme'];
				unset($options['betheme']);

			} else {

				// before 26.4

				$theme_options = $options;

			}

			// theme options

			// images URL | replace exported URL with destination URL

			if( $this->url ){
				$replace = home_url('/');
				foreach( $theme_options as $key => $option ){
					if( is_string( $option ) ){
						// variable type string only
						$option = $this->replace_multisite( $option );
						$theme_options[$key] = str_replace( $this->url, $replace, $option );
					}
				}
			}

			update_option( 'betheme', $theme_options );

			// header and footer conditions

			if( ! empty($options['conditions']) ){
				foreach( $options['conditions'] as $key => $value ){
					$post = get_page_by_title( $value, null, 'template' );
					if( ! empty($post->ID) ){
						update_option( $key, $post->ID );
					}
				}
			}

			// header and footer builder

			if( ! empty($options['map_menus']) ){

				global $wpdb;

				$map_menus = $options['map_menus'];

				// replace menu IDs in builder

				$templates = get_posts(
					array(
						'post_type'	=> 'template',
						'meta_key' => 'mfn_template_type',
						'meta_value' => ['header','footer','megamenu'],
						'numberposts' => -1
					)
				);

				if(count($templates) > 0){
					foreach($templates as $template){
						if( $builder = get_post_meta($template->ID, 'mfn-page-items', true) ){

							$builder = unserialize( call_user_func( 'base'.'64_decode', $builder ) );

							foreach( $builder as $s_k => $section ){

								$updated = false;

								if( ! empty( $section['wraps'] ) ){
									foreach( $section['wraps'] as $w_k => $wrap ){
										if( ! empty( $wrap['items'] ) ){
											foreach( $wrap['items'] as $i_k => $item ){
												if( ! empty($item['fields']['menu_display']) ){

													$menu_id = $item['fields']['menu_display'];

													if( ! empty( $map_menus[$menu_id] ) ){
														$menu_slug = $map_menus[$menu_id]['slug'];

														$menu_obj = wp_get_nav_menu_object( $menu_slug );
														if( $menu_obj ){
															$builder[$s_k]['wraps'][$w_k]['items'][$i_k]['fields']['menu_display'] = $menu_obj->term_id;
															$updated = true;
														}
													}

												}
											}
										}
									}
								}
							}

							if( $updated ){
								$builder = call_user_func( 'base'.'64_encode', serialize( $builder ) );
								update_post_meta($template->ID, 'mfn-page-items', $builder);
							}

						}
					}
				}

				// update menu items custom post_meta

				foreach( $map_menus as $menu ){
					if( !empty($menu['items']) ){
						foreach( $menu['items'] as $item ){

							$menu_item_ID = false;

							// find menu item

							if( ! empty($item['page']) ){

								// menu item links to page

								$post = get_page_by_title( $item['page'], null, 'page' );

								if( ! empty($post->ID) ){

									$result = $wpdb->get_row( $wpdb->prepare(
										"SELECT post_id
								  	FROM $wpdb->postmeta
								  	WHERE meta_key = '_menu_item_object_id'
								  	AND meta_value = %s",
										$post->ID ) );

									if( ! empty($result->post_id) ){
										$menu_item_ID = $result->post_id;
									}

								}

							} elseif( ! empty($item['product_cat']) ) {

								// menu item links to product category

								$term = get_term_by( 'name', $item['product_cat'], 'product_cat');

								if( ! empty($term->term_id) ){

									$result = $wpdb->get_row( $wpdb->prepare(
										"SELECT post_id
								  	FROM $wpdb->postmeta
								  	WHERE meta_key = '_menu_item_object_id'
								  	AND meta_value = %s",
										$term->term_id ) );

									if( ! empty($result->post_id) ){
										$menu_item_ID = $result->post_id;
									}

								}

							} else {

								$post = get_page_by_title( $item['title'], null, 'nav_menu_item' );
								if( ! empty($post->ID) ){
									$menu_item_ID = $post->ID;
								}

							}

							if( ! $menu_item_ID  ){
								continue;
							}

							// megamenu

							if( ! empty($item['mfn_menu_item_megamenu']) ){
								$post = get_page_by_title( $item['mfn_menu_item_megamenu'], null, 'template' );
								if( ! empty($post->ID) ){
									update_post_meta( $menu_item_ID, 'mfn_menu_item_megamenu', $post->ID );
								}
							}

							// icon

							if( ! empty($item['mfn_menu_item_icon']) ){
								update_post_meta( $menu_item_ID, 'mfn_menu_item_icon', $item['mfn_menu_item_icon'] );
							}

							// icon image

							if( ! empty($item['mfn_menu_item_icon_img']) ){

								$img = $item['mfn_menu_item_icon_img'];
								$replace = home_url('/');

								$img = $this->replace_multisite( $img );
								$img = str_replace( $this->url, $replace, $img );

								update_post_meta( $menu_item_ID, 'mfn_menu_item_icon_img', $img );
							}

						}
					}
				}

			}

		} else {

			echo 'Theme Options import failed';

		}

    return true;
  }

	/**
	 * Import | Menu - Locations
	 */

	function menu(){

		$file = wp_normalize_path( $this->demo_path .'/menu.txt' );

		$file_data = $this->get_file_data( $file );
		$data = unserialize( call_user_func( 'base'.'64_decode', $file_data ) );

		if( is_array( $data ) ){

			$menus = wp_get_nav_menus();

			foreach( $data as $key => $val ){
				foreach( $menus as $menu ){
					if( $val && $menu->slug == $val ){
						$data[$key] = absint( $menu->term_id );
					}
				}
			}

			set_theme_mod( 'nav_menu_locations', $data );

		} else {

			echo 'Menu locations import failed';

		}

		return true;
	}

	/**
	 * Import | Widgets
	 *
	 * @param string $file
	 */

	function widgets(){

		$file = wp_normalize_path( $this->demo_path .'/widget_data.json' );

		$file_data = $this->get_file_data( $file );

		if( $file_data ){

			$this->import_widget_data( $file_data );

		} else {

			echo 'Widgets import failed';

		}

		return true;
	}

	/**
	 * Import slider
	 */

	public function slider( $attachments = false ){

		$sliders = array();
		$demo_args = $this->demos[ $this->demo ];

		if( ! isset( $demo_args['plugins'] ) ){
			return false;
		}

		if( false === array_search( 'rev', $demo_args['plugins'] ) ){
			return false;
		}

		if( ! class_exists( 'RevSliderSlider' ) ){
			return false;
		}

		if( isset( $demo_args['revslider'] ) ){

			// multiple sliders
			foreach( $demo_args['revslider'] as $slider ){
				$sliders[] = $slider;
			}

		} else {

			// single slider
			$sliders[] = $this->demo_builder .'.zip';

		}

		if( method_exists( 'RevSliderSlider', 'importSliderFromPost' ) ){

			// RevSlider < 6.0

			$revslider = new RevSliderSlider();

			foreach( $sliders as $slider ){

				ob_start();
					$file = wp_normalize_path( $this->demo_path .'/'. $slider );
					$revslider->importSliderFromPost( true, false, $file );
				ob_end_clean();

			}

		} elseif( method_exists( 'RevSliderSliderImport', 'import_slider' ) ){

			// RevSlider 6.0 +

			$revslider = new RevSliderSliderImport();

			foreach( $sliders as $slider ){

				ob_start();
					$file = wp_normalize_path( $this->demo_path .'/'. $slider );
					$revslider->import_slider( true, $file );
				ob_end_clean();

			}

		} else {

			echo 'Revolution Slider is outdated. Please update plugin.';
			return false;

		}

		return true;
	}

	/**
	 * Set homepage
	 */

	 function set_pages(){

 		update_option( 'show_on_front', 'page' );

 		$defaults = [
 			'page_on_front' => 'Home',
 			'page_for_posts' => 'Blog',
 			'woocommerce_shop_page_id' => 'Shop',
 			'woocommerce_cart_page_id' => 'Cart',
 			'woocommerce_checkout_page_id' => 'Checkout',
 			'woocommerce_myaccount_page_id' => 'My account',
 			'woocommerce_terms_page_id' => 'Privacy Policy',
 		];

 		if( ! empty( $this->demos[$this->demo]['pages'] ) ){
 			$pages = $this->demos[$this->demo]['pages'];
 		} else {
 			$pages = [];
 		}

 		$pages = array_merge( $defaults, $pages );

 		foreach ( $pages as $slug => $title ) {

 			$post = get_page_by_title( $title );

 			$post_id = ( $post && ! empty( $post->ID ) ) ? $post->ID : '';

 			update_option( $slug, $post_id );

 		}

		return true;

 	}

	/**
	 * Regenerate static class
	 * Stiic CSS files generated for styles in: builder > element > style tab
	 */

	function regenerate_CSS(){

		$items = get_posts( array(
			'post_type' => array( 'page', 'post', 'template', 'portfolio', 'product' ),
			'post_status' => 'publish',
			'posts_per_page' => -1,
		) );

		if( ! empty( $items ) && is_array( $items ) ){
			foreach( $items as $item ){
				if( get_post_meta( $item->ID, 'mfn-page-local-style') ){
					$mfn_styles = json_decode( get_post_meta( $item->ID, 'mfn-page-local-style', true ), true );
					Mfn_Helper::generate_css( $mfn_styles, $item->ID );
				}
			}
		}

		return true;

	}

  /**
   * HELPER functions ----------
   */

   /**
 	  * Import XML
 	  */

 	function import_xml( $attachments = false, $hide_output = false ){

    $file = wp_normalize_path( $this->demo_path .'/content.xml.gz' );

    // Importer classes

    if( ! defined( 'WP_LOAD_IMPORTERS' ) ){
      define( 'WP_LOAD_IMPORTERS', true );
    }

    if( ! class_exists( 'WP_Importer' ) ){
      require_once(ABSPATH .'wp-admin/includes/class-wp-importer.php');
    }

    if( ! class_exists( 'WP_Import' ) ){
      require_once(get_theme_file_path('/functions/importer/wordpress-importer/wordpress-importer.php'));
    }

    // Import START

    if( class_exists( 'WP_Importer' ) && class_exists( 'WP_Import' ) ){

   		$import = new WP_Import();

   		if( $attachments ){
   			$import->fetch_attachments = true;
   		} else {
   			$import->fetch_attachments = false;
   		}

      if( $hide_output ){
        ob_start();
     		$import->import( $file );
     		ob_end_clean();
      } else {
        $import->import( $file );
      }

      return true;
    }

    return false;
 	}

  /**
   * Get demo url to replace
   */

  function get_demo_url(){

    if( 'theme' == $this->demo_builder ){

      $url = 'https://themes.muffingroup.com/betheme/';

    } elseif( 'bethemestore' == $this->demo_builder ){

      $url = 'https://themes.muffingroup.com/betheme-store/';

    } elseif( 'bethemestore_el' == $this->demo_builder ){

      $url = 'https://themes.muffingroup.com/betheme-store_el/';

    } elseif( 'bethemestore2' == $this->demo_builder ){

      $url = 'https://themes.muffingroup.com/betheme-store2/';

    } elseif( 'bethemestore2_el' == $this->demo_builder ){

      $url = 'https://themes.muffingroup.com/betheme-store2_el/';

    } else {

      $url = array(
        'http://themes.muffingroup.com/be/'. $this->demo_builder .'/',
        'https://themes.muffingroup.com/be/'. $this->demo_builder .'/',
      );

    }

    return $url;
  }

  /**
   * Remove all menus
   * TIP: Useful on slower servers when we need to resume downloading
   */

  function remove_menus(){

    global $wpdb;

    $result = $wpdb->query( $wpdb->prepare(
      "DELETE a,b,c
      FROM wp_posts a
      LEFT JOIN wp_term_relationships b
        ON (a.ID = b.object_id)
      LEFT JOIN $wpdb->postmeta c
        ON (a.ID = c.post_id)
      WHERE a.post_type = %s",
      "nav_menu_item" ) );

		echo 'Menu remove status: '. $result;

  }

  /**
	 * Elementor
	 */

	function elementor_settings(){

		$wrapper = '1140';

		if( isset( $this->demos[$this->demo]['wrapper'] ) ){
			$wrapper = $this->demos[$this->demo]['wrapper'];
		}

		$settings = [
			'elementor_cpt_support' => [ 'post', 'page', 'product', 'portfolio' ],
			'elementor_disable_color_schemes' => 'yes',
			'elementor_disable_typography_schemes' => 'yes',
			'elementor_load_fa4_shim' => 'yes',

			// Elementor < 3.0
			'elementor_container_width' => $wrapper,
			'elementor_stretched_section_container' => '#Wrapper',
			'elementor_viewport_lg' => '960',
		];

		foreach ( $settings as $key => $value ) {
			update_option( $key, $value );
		}

		// Elementor 3.0 +

		if ( class_exists( 'Elementor\Plugin' ) ){
			if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.0', '>=' )) {

				$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();

				if ( ! $kit->get_id() ) {

					// FIX: Elementor 3.3 + | default Kit do not exists after Database Reset

					$created_default_kit = \Elementor\Plugin::$instance->kits_manager->create_default();

					if ( ! $created_default_kit ) {
						return false;
					}

					update_option( \Elementor\Core\Kits\Manager::OPTION_ACTIVE, $created_default_kit );

					$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();

				}

				$kit->update_settings( [
					'container_width' => array(
						'size' => $wrapper,
					),
					'stretched_section_container' => '#Wrapper',
					'viewport_lg' => '960',
				] );

			}
		}

	}

	/**
	 * Get FILE data
	 * @return string
	 */

	function get_file_data( $path ){

		$data = false;
		$path = wp_normalize_path( $path );
		$wp_filesystem = Mfn_Helper::filesystem();

		if( $wp_filesystem->exists( $path ) ){

			if( ! $data = $wp_filesystem->get_contents( $path ) ){

				$fp = fopen( $path, 'r' );
				$data = fread( $fp, filesize( $path ) );
				fclose( $fp );

			}

		}

		return $data;
	}

  /**
   * Replace Multisite URLs
   * Multisite 'uploads' directory url
   */

  function replace_multisite( $field ){

    if ( is_multisite() ){

      global $current_blog;

      if( $current_blog->blog_id > 1 ){
        $old_url = '/wp-content/uploads/';
        $new_url = '/wp-content/uploads/sites/'. $current_blog->blog_id .'/';
        $field = str_replace( $old_url, $new_url, $field );
      }

    }

    return $field;
  }

  /**
	 * Replace Elementor URLs
	 */

	function replace_elementor(){

		global $wpdb;

		$old_url = $this->url;

		if( is_array( $old_url ) ){
			$old_url = $old_url[1]; // new demos uses https only
		}

		$old_url = str_replace('/','\/',$old_url);
		$new_url = home_url('/');

		// FIX: importer new line characters in longtext

		$wpdb->query($wpdb->prepare("UPDATE $wpdb->postmeta
			SET `meta_value` =
			REPLACE( meta_value, %s, %s)
			WHERE `meta_key` = '_elementor_data'
		", "\n", ""));

		// replace urls

		$wpdb->query($wpdb->prepare("UPDATE $wpdb->postmeta
			SET `meta_value` =
			REPLACE( meta_value, %s, %s)
			WHERE `meta_key` = '_elementor_data'
		", $old_url, $new_url));

	}

  /**
	 * Replace Muffin Builder URLs
	 */

	function replace_builder(){

		global $wpdb;

		$uids = array();

		$old_url = $this->url;
		$new_url = home_url('/');

		// FIX: importer new line characters in longtext

		$wpdb->query($wpdb->prepare("UPDATE $wpdb->postmeta
			SET `meta_value` =
			REPLACE( meta_value, %s, %s)
			WHERE `meta_key` = 'mfn-page-local-style'
		", "\n", ""));

		// replace urls | local styles

		if( is_array( $old_url ) ){
			$style_old_url = $old_url[1]; // new demos uses https only
		} else {
			$style_old_url = $old_url;
		}

		$wpdb->query($wpdb->prepare("UPDATE $wpdb->postmeta
			SET `meta_value` =
			REPLACE( meta_value, %s, %s)
			WHERE `meta_key` = 'mfn-page-local-style'
		", $style_old_url, $new_url));

		// replace urls | builder

		$results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->postmeta
			WHERE `meta_key` = %s
		", 'mfn-page-items'));

		// posts loop -----

		if( is_array( $results ) ){
			foreach( $results as $result_key => $result ){

				$meta_id = $result->meta_id;
				$meta_value = @unserialize( $result->meta_value );

				// builder 2.0 compatibility

				if( $meta_value === false ){
					$meta_value = unserialize(call_user_func('base'.'64_decode', $result->meta_value));
				}

				// SECTIONS

				if( is_array( $meta_value ) ){
					foreach( $meta_value as $sec_key => $sec ){

						// section uIDs

						if( empty( $sec['uid'] ) ){
							$uids[] = Mfn_Builder_Helper::unique_ID($uids);
							$meta_value[$sec_key]['uid'] = end($uids);
						} else {
							$uids[] = $sec['uid'];
						}

						// section attributes

						if( isset( $sec['attr'] ) && is_array( $sec['attr'] ) ){
							foreach( $sec['attr'] as $attr_key => $attr ){
								$attr = str_replace( $old_url, $new_url, $attr );
								$meta_value[$sec_key]['attr'][$attr_key] = $attr;
							}
						}

						// FIX | Muffin Builder 2 compatibility
						// there were no wraps inside section in Muffin Builder 2

						if( ! isset( $sec['wraps'] ) && ! empty( $sec['items'] ) ){

							$fix_wrap = array(
								'size' => '1/1',
								'uid' => Mfn_Builder_Helper::unique_ID($uids),
								'items'	=> $sec['items'],
							);

							$sec['wraps'] = array( $fix_wrap );

							$meta_value[$sec_key]['wraps'] = $sec['wraps'];
							unset( $meta_value[$sec_key]['items'] );

						}

						// WRAPS

						if( isset( $sec['wraps'] ) && is_array( $sec['wraps'] ) ){
							foreach( $sec['wraps'] as $wrap_key => $wrap ){

								// wrap uIDs

								if( empty( $wrap['uid'] ) ){
									$uids[] = Mfn_Builder_Helper::unique_ID($uids);
									$meta_value[$sec_key]['wraps'][$wrap_key]['uid'] = end($uids);
								} else {
									$uids[] = $wrap['uid'];
								}

								// wrap attributes

								if( isset( $wrap['attr'] ) && is_array( $wrap['attr'] ) ){
									foreach( $wrap['attr'] as $attr_key => $attr ){

										$attr = str_replace( $old_url, $new_url, $attr );
										$meta_value[$sec_key]['wraps'][$wrap_key]['attr'][$attr_key] = $attr;

									}
								}

								// ITEMS

								if( isset( $wrap['items'] ) && is_array( $wrap['items'] ) ){
									foreach( $wrap['items'] as $item_key => $item ){

										// item uIDs

										if( empty( $item['uid'] ) ){
											$uids[] = Mfn_Builder_Helper::unique_ID($uids);
											$meta_value[$sec_key]['wraps'][$wrap_key]['items'][$item_key]['uid'] = end($uids);
										} else {
											$uids[] = $item['uid'];
										}

										// item fields

										if( isset( $item['fields'] ) && is_array( $item['fields'] ) ){
											foreach( $item['fields'] as $field_key => $field ) {

												if( 'tabs' == $field_key ) {

													// tabs

													if( is_array( $field ) ){
														foreach( $field as $tab_key => $tab ){

															// tabs fields

															if( is_array( $tab ) ){
																foreach( $tab as $tab_field_key => $tab_field ){

																	$field = str_replace( $old_url, $new_url, $tab_field );
																	$field = $this->replace_multisite( $field );
																	$meta_value[$sec_key]['wraps'][$wrap_key]['items'][$item_key]['fields']['tabs'][$tab_key][$tab_field_key] = $field;

																}
															}

														}
													}

												} else {

													// default

													$field = str_replace( $old_url, $new_url, $field );
													$field = $this->replace_multisite( $field );
													$meta_value[$sec_key]['wraps'][$wrap_key]['items'][$item_key]['fields'][$field_key] = $field;

												}

											}
										}

									}
								}

							}
						}

					}
				}

				// builder 2.0 compatibility

				$meta_value = call_user_func('base'.'64_encode', serialize( $meta_value ));

				$wpdb->query($wpdb->prepare("UPDATE $wpdb->postmeta
					SET `meta_value` = %s
					WHERE `meta_key` = 'mfn-page-items'
					AND `meta_id`= %d
				", $meta_value, $meta_id));

			}
		}
	}

	/**
	 * Parse JSON import file
	 *
	 * http://wordpress.org/plugins/widget-settings-importexport/
	 *
	 * @param string $json_data
	 */

	function import_widget_data( $json_data ) {

		$json_data = json_decode( $json_data, true );
		$sidebar_data = $json_data[0];
		$widget_data = $json_data[1];

		// prepare widgets table

		$widgets = array();
		foreach( $widget_data as $k_w => $widget_type ){
			if( $k_w ){
				$widgets[ $k_w ] = array();
				foreach( $widget_type as $k_wt => $widget ){
					if( is_int( $k_wt ) ) $widgets[$k_w][$k_wt] = 1;
				}
			}
		}

		// sidebars

		foreach ( $sidebar_data as $title => $sidebar ) {
			$count = count( $sidebar );
			for ( $i = 0; $i < $count; $i++ ) {
				$widget = array( );
				$widget['type'] = trim( substr( $sidebar[$i], 0, strrpos( $sidebar[$i], '-' ) ) );
				$widget['type-index'] = trim( substr( $sidebar[$i], strrpos( $sidebar[$i], '-' ) + 1 ) );
				if ( !isset( $widgets[$widget['type']][$widget['type-index']] ) ) {
					unset( $sidebar_data[$title][$i] );
				}
			}
			$sidebar_data[$title] = array_values( $sidebar_data[$title] );
		}

		// widgets

		foreach ( $widgets as $widget_title => $widget_value ) {
			foreach ( $widget_value as $widget_key => $widget_value ) {
				$widgets[$widget_title][$widget_key] = $widget_data[$widget_title][$widget_key];
			}
		}

		$sidebar_data = array( array_filter( $sidebar_data ), $widgets );
		$this->parse_import_data( $sidebar_data );
	}

	/**
	 * Import widgets
	 *
	 * http://wordpress.org/plugins/widget-settings-importexport/
	 *
	 * @param array $import_array
	 * @return boolean
	 */

	function parse_import_data( $import_array ) {
		$sidebars_data = $import_array[0];
		$widget_data = $import_array[1];

		mfn_register_sidebars(); // fix for sidebars added in Theme Options

		$current_sidebars 	= array( );
		$new_widgets = array( );

		foreach ( $sidebars_data as $import_sidebar => $import_widgets ) :

			foreach ( $import_widgets as $import_widget ) :

				// if NOT the sidebar exists

				if ( ! isset( $current_sidebars[$import_sidebar] ) ){
					$current_sidebars[$import_sidebar] = array();
				}

				$title = trim( substr( $import_widget, 0, strrpos( $import_widget, '-' ) ) );
				$index = trim( substr( $import_widget, strrpos( $import_widget, '-' ) + 1 ) );
				$current_widget_data = get_option( 'widget_' . $title );
				$new_widget_name = $this->get_new_widget_name( $title, $index );
				$new_index = trim( substr( $new_widget_name, strrpos( $new_widget_name, '-' ) + 1 ) );

				if ( !empty( $new_widgets[ $title ] ) && is_array( $new_widgets[$title] ) ) {
					while ( array_key_exists( $new_index, $new_widgets[$title] ) ) {
						$new_index++;
					}
				}
				$current_sidebars[$import_sidebar][] = $title . '-' . $new_index;
				if ( array_key_exists( $title, $new_widgets ) ) {
					$new_widgets[$title][$new_index] = $widget_data[$title][$index];

					// notice fix

					if( ! key_exists('_multiwidget',$new_widgets[$title]) ) $new_widgets[$title]['_multiwidget'] = '';

					$multiwidget = $new_widgets[$title]['_multiwidget'];
					unset( $new_widgets[$title]['_multiwidget'] );
					$new_widgets[$title]['_multiwidget'] = $multiwidget;
				} else {
					$current_widget_data[$new_index] = $widget_data[$title][$index];

					// notice fix

					if( ! key_exists('_multiwidget',$current_widget_data) ) $current_widget_data['_multiwidget'] = '';

					$current_multiwidget = $current_widget_data['_multiwidget'];
					$new_multiwidget = isset($widget_data[$title]['_multiwidget']) ? $widget_data[$title]['_multiwidget'] : false;
					$multiwidget = ($current_multiwidget != $new_multiwidget) ? $current_multiwidget : 1;
					unset( $current_widget_data['_multiwidget'] );
					$current_widget_data['_multiwidget'] = $multiwidget;
					$new_widgets[$title] = $current_widget_data;
				}

			endforeach;
		endforeach;

		// remove old widgets

		delete_option( 'sidebars_widgets' );

		if ( isset( $new_widgets ) && isset( $current_sidebars ) ) {
			update_option( 'sidebars_widgets', $current_sidebars );

			foreach ( $new_widgets as $title => $content )
				update_option( 'widget_' . $title, $content );

			return true;
		}

		return false;
	}

	/**
	 * Get new widget name
	 *
	 * http://wordpress.org/plugins/widget-settings-importexport/
	 *
	 * @param string $widget_name
	 * @param int $widget_index
	 * @return string
	 */

	function get_new_widget_name( $widget_name, $widget_index ) {
		$current_sidebars = get_option( 'sidebars_widgets' );
		$all_widget_array = array( );
		foreach ( $current_sidebars as $sidebar => $widgets ) {
			if ( !empty( $widgets ) && is_array( $widgets ) && $sidebar != 'wp_inactive_widgets' ) {
				foreach ( $widgets as $widget ) {
					$all_widget_array[] = $widget;
				}
			}
		}
		while ( in_array( $widget_name . '-' . $widget_index, $all_widget_array ) ) {
			$widget_index++;
		}
		$new_widget_name = $widget_name . '-' . $widget_index;
		return $new_widget_name;
	}

}
