<?php
/*
Plugin Name: Translation Stats
Plugin URI:  https://github.com/pedro-mendonca/translation-stats
Description: Show your plugins translation statistics from WordPress.org on your dashboard.
Author:      Pedro Mendonça
GitHub Plugin URI: pedro-mendonca/translation-stats
Version:     0.5
Text Domain: translation-stats
Domain Path: /languages
Tags: glotpress, translation, statistics, i18n, l10n, dark mode
*/


class Plugin_Translation_Stats {

	/**
	 * Constructor.
	 */
	function __construct() {

		// Load GlotPress locales data
		include 'lib/glotpress/locales.php';

		// Register and enqueue plugin style sheet.
		add_action( 'admin_enqueue_scripts', array( $this, 'ts_register_plugin_styles' ) );

		// Add plugin translation stats column
		add_filter( 'manage_plugins_columns', 'ts_add_translation_stats_column' );

		function ts_add_translation_stats_column( $columns ) {
			$columns['translation-stats'] = _x( 'Translation Stats', 'Column label', 'translation-stats' );
			return $columns;
		}

		// Show plugin translation stats content in column
		add_action( 'manage_plugins_custom_column' , array( $this, 'ts_render_plugin_stats_column' ), 10, 3 );
	}


	/**
	 * Register and enqueue style sheet.
	 */
	function ts_register_plugin_styles( $hook ) {
		// Loads plugin style sheets only in the plugins page.
		if ( 'plugins.php' != $hook ) {
			return;
		};
		wp_register_style( 'translation-stats', plugins_url( 'translation-stats/css/admin.css' ), false, '0.5.0' );
		wp_enqueue_style( 'translation-stats' );
		// Add Dark Mode style sheet.
		// https://github.com/danieltj27/Dark-Mode/wiki/Help:-Plugin-Compatibility-Guide
		add_action( 'doing_dark_mode', array( $this, 'ts_register_plugin_styles_dark_mode' ) );
	}


	/**
	 * Register and enqueue Dark Mode style sheet.
	 */
	 function ts_register_plugin_styles_dark_mode() {
		wp_register_style( 'translation-stats-dark-mode', plugins_url( 'translation-stats/css/admin-dark-mode.css' ), false, '0.5.0' );
		wp_enqueue_style( 'translation-stats-dark-mode' );
	}


	/**
	 * Check if plugin is on WordPress.org by checking if ID (from Plugin wp.org info) exists in 'response' or 'no_update' in 'update_plugins' transient.
	 *
	 * @param string $plugin_file  Plugin ID ( e.g. 'slug/plugin-name.php' )
	 * @return string              Returns 'true' if the plugin exists on WordPress.org
	 */
	function ts_plugin_on_wporg( $plugin_file ) {
		$plugin_state = get_site_transient( 'update_plugins' );
		if ( isset( $plugin_state->response[ $plugin_file ]->id ) || isset( $plugin_state->no_update[ $plugin_file ]->id ) ) {
			return true;
		}
	}


	/**
	 * Get plugin metadata, if the plugin exists on WordPress.org.
	 *
	 * Example:
	 * $plugin_metadata = $this->plugin_data( $plugin_file, 'metadata' ) (e.g. 'slug')
	 *
	 * @param string $plugin_file       Plugin ID ( e.g. 'slug/plugin-name.php' )
	 * @param string $metadata          Metadata field ( e.g. 'slug' )
	 * @return string $plugin_metadata  Returns metadata value from plugin
	 */
	function ts_plugin_metadata( $plugin_file, $metadata ) {
		$plugin_state = get_site_transient( 'update_plugins' );
		// Check if plugin is on WordPress.org
		if ( ! empty( $this->ts_plugin_on_wporg( $plugin_file ) ) ) {
			if ( isset ( $plugin_state->response[ $plugin_file ]->$metadata ) ) {
				$plugin_metadata = $plugin_state->response[ $plugin_file ]->$metadata;
			}
			if ( isset ( $plugin_state->no_update[ $plugin_file ]->$metadata ) ) {
				$plugin_metadata = $plugin_state->no_update[ $plugin_file ]->$metadata;
			}
			return $plugin_metadata;
		}
	}


	/**
	 *
	 * @return string  Returns the translate.WordPress.org API URL
	 */
	function ts_translate_api_url() {
		$api_url = 'https://translate.wordpress.org/api/projects/wp-plugins/';
		return $api_url;
	}


	/**
	 * Display error message.
	 *
	 * @param string $message  Error message to display
	 * @return string          Returns formated error message
	 */
	function ts_error_message( $error_message ) {
		ob_start(); ?>
		<div class="translation-stats-error">
			<span class="error-message"><?php echo __( 'Error:', 'translation-stats' ); ?></span> <span><?php echo $error_message; ?></span>
		</div>
		<?php
		$plugin_error = ob_get_clean();
		return $plugin_error;
	}


	/**
	 * Show Plugin Translation Stats Content
	 *
	 * @param string  $column_name    Column Slug ( e.g. 'translation-stats' )
	 * @param string  $plugin_file    Plugin ID ( e.g. 'slug/plugin-name.php' )
	 * @param string  $plugin_data    Plugin data from WP.org
	 * @return string echo            Show plugin stats if the plugin is in WP.org and if Locale isn´t 'en_US'
	 */
	function ts_render_plugin_stats_column( $column_name, $plugin_file, $plugin_data ) {

		// if ( 'translation-stats' == $column_name && 'My Plugin Name' == $plugin_data['Name'] ) :
		// Add Translation Stats if plugin is on wordpress.org and if user Locale isn't 'en_US'
		// Check if is in column 'translation-stats'
		if ( $column_name == 'translation-stats' ) {

			// Check if user locale is not 'en_US'
			if ( get_user_locale() != 'en_US' ) {

				$project_slug = $this->ts_plugin_metadata( $plugin_file, 'slug' );

				// Check if plugin is on WordPress.org
				if ( empty( $this->ts_plugin_on_wporg( $plugin_file ) ) ) {
					echo $this->ts_error_message( __( 'Plugin not found on WordPress.org', 'translation-stats' ) ); // Add alternative GlotPress API
				} else {
					// Check if translation project is on WordPress.org
					if ( $this->ts_plugin_project_on_translate_wporg( $project_slug ) != true ) {
						echo $this->ts_error_message( __( 'Translation project not found on WordPress.org', 'translation-stats' ) );
					} else {
						echo $this->ts_render_plugin_stats( $project_slug );
					}
				}
			}
		}
	}


	/**
	 * Check if translation project exist without /subproject slug (e.g. https://translate.wordpress.org/api/projects/wp-plugins/wp-seo-acf-content-analysis)
	 *
	 * @param string $project_slug  Plugin Slug (e.g. 'plugin-slug')
	 * @return string               Returns 'true' if the translation project exist on WordPress.org
	 */
	function ts_plugin_project_on_translate_wporg( $project_slug ) {
		// Check project transients
		$on_wporg = get_transient( 'translation_stats_plugin_' . $project_slug );
		if ( $on_wporg === false ) {
			$json = wp_remote_get( $this->ts_translate_api_url() . $project_slug );
			if ( is_wp_error( $json ) || wp_remote_retrieve_response_code( $json ) !== 200 ) {
				$on_wporg = false;
			} else {
				$on_wporg = true;
			}
			set_transient( 'translation_stats_plugin_' . $project_slug, $on_wporg, MONTH_IN_SECONDS );
		}
		return $on_wporg;
	}


	/**
	 * Check if translation subproject exist (e.g. https://translate.wordpress.org/api/projects/wp-plugins/wp-seo-acf-content-analysis/stable)
	 *
	 * @param string $project_slug     Plugin Slug (e.g. 'plugin-slug')
	 * @param string $subproject_slug  Plugin Subproject Slug (e.g. 'dev', 'dev-readme', 'stable', 'stable-readme')
	 * @return string                  Returns 'true' if the translation subproject exist on WordPress.org
	 */
	function ts_plugin_subproject_on_translate_wporg( $project_slug, $subproject_slug ) {
		// Check subproject transients
		$on_wporg = get_transient( 'translation_stats_plugin_' . $project_slug . '_' . $subproject_slug );
		if ( $on_wporg === false ) {
			$json = wp_remote_get( $this->ts_translate_api_url() . $project_slug . '/' . $subproject_slug );
			if ( is_wp_error( $json ) || wp_remote_retrieve_response_code( $json ) !== 200 ) {
				$on_wporg = false;
			} else {
				$on_wporg = true;
			}
			set_transient( 'translation_stats_plugin_' . $project_slug . '_' . $subproject_slug, $on_wporg, MONTH_IN_SECONDS );
		}
		return $on_wporg;
	}



	/**
	 * Render plugin stats for current locale.
	 *
	 * @param string  $project_slug                Plugin Slug
	 * @return string $plugin_translation_stats    Plugin translation stats
	 */
	function ts_render_plugin_stats( $project_slug ) {

		$locale = get_user_locale();
		$variant = 'default'; // Todo: Add support for non-default variant
		// Depends of GlotPress library
		$locale = GP_Locales::by_field( 'wp_locale', $locale );
		ob_start(); ?>
		<div class="translation-stats-title">
			<?php
			$url = 'https://translate.wordpress.org/locale/' . $locale->slug . '/' . $variant . '/wp-plugins/' . $project_slug;
			$locale_link = '<a href="' . $url . '" _target="blank">' . $locale->native_name . '</a>';
			/* translators: %s Language native name. */
			echo sprintf( __( 'Translation for %s', 'translation-stats' ), $locale_link );
			?>
		</div>
		<div class="translation-stats-wrap notice-warning notice-alt">
			<?php
			$dev = $this->ts_render_stats_bar( $locale, $project_slug, __( 'Development', 'translation-stats' ), 'dev' );
			$dev_readme = $this->ts_render_stats_bar( $locale, $project_slug, __( 'Development Readme', 'translation-stats' ), 'dev-readme' );
			$stable = $this->ts_render_stats_bar( $locale, $project_slug, __( 'Stable', 'translation-stats' ), 'stable' );
			$stable_readme = $this->ts_render_stats_bar( $locale, $project_slug, __( 'Stable Readme', 'translation-stats' ), 'stable-readme' );

			echo $dev['stats'];
			echo $dev_readme['stats'];
			echo $stable['stats'];
			echo $stable_readme['stats'];
			?>
		</div>
		<?php

		$i18n_errors = $dev['error'] + $dev_readme['error'] + $stable['error'] + $stable_readme['error'];
		if ( ! empty ( $i18n_errors ) ) { ?>
			<p>
				<?php echo sprintf(
					/* translators: %1$s Opening link tag <a href="[link]">. %2$s Closing link tag </a>. */
					__( 'This plugin is not %1$sproperly prepared for localization%2$s.', 'translation-stats' ),
					'<a href="https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/" target="_blank">',
					'</a>'
				); ?>
			</p>
			<p>
				<?php echo sprintf(
					( '%1$s%2$s%3$s' ),
					'<a href="https://make.wordpress.org/meta/handbook/documentation/translations/#this-plugin-is-not-properly-prepared-for-localization-%e2%80%93-help" target="_blank">',
					__( 'View detailed logs on Slack', 'translation-stats' ),
					'</a>'
				); ?>
			</p>
			<p>
				<?php echo sprintf(
					/* translators: %1$s Opening link tag <a href="[link]">. %2$s Closing link tag </a>. */
					__( 'If you would like to translate this plugin, %1$splease contact the author%2$s.', 'translation-stats' ),
					'<a href="https://wordpress.org/support/plugin/' . $project_slug . '" target="_blank">',
					'</a>'
				); ?>
			</p>
		<?php }

		$plugin_translation_stats = ob_get_clean();
		return $plugin_translation_stats;
	}


	/**
	 * Render plugin subproject stat bar.
	 *
	 * @param string  $locale                   Locale (wp_locale), e.g. 'pt_PT' or get_user_locale()
	 * @param string  $project_slug             Plugin Slug
	 * @param string  $subproject               Translation subproject (' Dev', 'Dev Readme', 'Stable', 'Stable Readme' )
	 * @param string  $subproject_slug          Translation subproject Slug ( 'dev', 'dev-readme', 'stable', 'stable-readme' )
	 * @return string $translation_stats_bar    Plugin stats
	 */
	function ts_render_stats_bar( $locale, $project_slug, $subproject, $subproject_slug ) {

		$variant = 'default'; // Todo: Add support for non-default variant
		$url = 'https://translate.wordpress.org/projects/wp-plugins/' . $project_slug . '/' . $subproject_slug . '/' . $locale->slug . '/' . $variant;

		// Get plugin subproject translation stats
		$translation_stats = $this->ts_plugin_subproject_stats( $locale->slug, $variant, $project_slug, $subproject_slug );

		// If translation stats are not an object, project not found
		if ( ! is_object ( $translation_stats ) ) {

			$i18n_error = true;
			ob_start(); ?>
			<div class="disabled <?php echo $subproject_slug; ?>">
				<span class="subproject"><?php echo sprintf( /* translators: %1$s Name of subproject. %2$s Error message. */ __( '%1$s: %2$s', 'translation-stats' ), $subproject, '<strong>' . __( 'Not found', 'translation-stats' ) . '</strong>' ); ?></span>
			</div>
			<?php $translation_stats_bar = ob_get_clean();

		// If translation stats are an object, get the percent translated property
		} else {

			/*
				Get the 'percent_translated' property from subproject translation stats
				Example of allowed properties:
				[id] => 416518
				[name] => Portuguese (Portugal)
				[slug] => default | ao90 | informal
				[project_id] => 3333
				[locale] => pt
				[current_count] => 136
				[untranslated_count] => 0
				[waiting_count] => 0
				[fuzzy_count] => 0
				[percent_translated] => 100
				[wp_locale] => pt_PT
				[last_modified] => 2018-10-11 10:05:30
			*/
			$percent_translated = $translation_stats->percent_translated;
			$i18n_error = false;
			ob_start(); ?>
			<a target="_blank" href="<?php echo $url; ?>">
				<div class="<?php echo 'percent' . 10 * floor ( $percent_translated/10 ) . ' ' . $subproject_slug; ?>" style="width: <?php echo $percent_translated; ?>%;">
					<div class="subproject">
						<span class="percentage"><?php echo $percent_translated; ?>%</span><span class="subproject-name"><?php echo $subproject; ?></span>
					</div>
				</div>
			</a>
			<?php $translation_stats_bar = ob_get_clean();

		}

		$stats = array( 'stats' => $translation_stats_bar, 'error' => $i18n_error );
		return $stats;
	}


	/**
	 * Render plugin subproject stat bar.
	 *
	 * @param string  $locale             Locale (wp_locale), e.g. 'pt_PT' or get_user_locale()
	 * @param string  $variant            Variant ( e.g. 'default', 'formal' )
	 * @param string  $project_slug       Plugin Slug
	 * @param string  $subproject_slug    Translation subproject Slug ( 'dev', 'dev-readme', 'stable', 'stable-readme' )
	 * @return string $translation_stats  Plugin stats
	 */
	function ts_plugin_subproject_stats( $locale, $variant, $project_slug, $subproject_slug ) {

		// Check subproject transients
		$translation_stats = get_transient( 'translation_stats_plugin_' . $project_slug . '_' . $subproject_slug . '_' . $locale );

		if ( $translation_stats === false ) {

			$json = wp_remote_get( $this->ts_translate_api_url() . $project_slug . '/' . $subproject_slug );
			if ( is_wp_error( $json ) || wp_remote_retrieve_response_code( $json ) !== 200 ) {

				// Subproject not found (Error 404) - Plugin is not properly prepared for localization
				$translation_stats = false;

			} else {

				$body = json_decode( $json['body'] );
				if ( empty( $body->translation_sets ) ) {

					// No translation sets found
					$translation_stats = false;

				} else {

					foreach( $body->translation_sets as $translation_set ) {

						if ( $translation_set->locale === $locale && $translation_set->slug === $variant ) {
							// Set transient value
							$translation_stats = $translation_set;
							continue;
						}
					}
				}
			}

			set_transient( 'translation_stats_plugin_' . $project_slug . '_' . $subproject_slug . '_' . $locale, $translation_stats, DAY_IN_SECONDS );
		}

		return $translation_stats;
	}

}

new Plugin_Translation_Stats;
