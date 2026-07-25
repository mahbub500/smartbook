/**
 * SmartBook Import/Export page scripts.
 *
 * Progressively enhances the Import/Export admin page in two ways, both
 * strictly additive: the four stacked sections (Export, Import, Backup,
 * Restore) become a tab strip, and the Import/Restore forms' plain
 * multipart submit is replaced with a chunked AJAX flow that animates a
 * progress bar. Without this script (or with `sbImportExport` never
 * localized, i.e. any admin screen but this one) every form still works
 * exactly as a plain admin-post.php submit; see
 * Admin\Pages\ImportExportPage's class doc comment.
 */
( function ( sb_window, sb_document ) {
	'use strict';

	if ( typeof sb_window.sbAdmin === 'undefined' || typeof sb_window.sbImportExport === 'undefined' ) {
		return;
	}

	var sb_config = sb_window.sbImportExport;

	/**
	 * Run a callback once the DOM is ready.
	 */
	function sb_ready( sb_callback ) {
		if ( 'loading' !== sb_document.readyState ) {
			sb_callback();
		} else {
			sb_document.addEventListener( 'DOMContentLoaded', sb_callback );
		}
	}

	/**
	 * Fill in a "%1$d"/"%2$d"-style sprintf placeholder string. Only
	 * supports the small, fixed set of positional integer placeholders
	 * this page's localized strings actually use.
	 */
	function sb_format( sb_template, sb_values ) {
		var sb_result = sb_template;

		sb_values.forEach( function ( sb_value, sb_index ) {
			sb_result = sb_result.replace( '%' + ( sb_index + 1 ) + '$d', String( sb_value ) );
		} );

		return sb_result;
	}

	/**
	 * Turn each "[data-sb-tabs]" group of "[data-sb-tab-panel]" sections
	 * into a tab strip, using each panel's own data-sb-tab-panel value
	 * as the tab key and its (now-hidden) <h2> as the tab label. Every
	 * panel stays in the DOM, just hidden, so this is a pure
	 * display-layer enhancement over markup that already works fully
	 * stacked without it.
	 */
	function sb_initTabs( sb_scope ) {
		var sb_containers = sb_scope.querySelectorAll( '[data-sb-tabs]' );

		sb_containers.forEach( function ( sb_container ) {
			var sb_panels = Array.prototype.slice.call( sb_container.querySelectorAll( '[data-sb-tab-panel]' ) );

			if ( sb_panels.length < 2 ) {
				return;
			}

			var sb_nav = sb_document.createElement( 'div' );
			sb_nav.className = 'sb-tabs__nav';
			sb_nav.setAttribute( 'role', 'tablist' );

			var sb_activeKey = sb_container.getAttribute( 'data-sb-active-tab' ) || '';
			var sb_hasActive = sb_panels.some( function ( sb_panel ) {
				return sb_panel.getAttribute( 'data-sb-tab-panel' ) === sb_activeKey;
			} );

			if ( ! sb_hasActive ) {
				sb_activeKey = sb_panels[ 0 ].getAttribute( 'data-sb-tab-panel' );
			}

			var sb_showPanel = function ( sb_key ) {
				sb_panels.forEach( function ( sb_panel ) {
					sb_panel.classList.toggle( 'sb-hidden', sb_panel.getAttribute( 'data-sb-tab-panel' ) !== sb_key );
				} );

				sb_nav.querySelectorAll( '.sb-tabs__tab' ).forEach( function ( sb_tab ) {
					var sb_isActive = sb_tab.getAttribute( 'data-sb-tab' ) === sb_key;
					sb_tab.classList.toggle( 'sb-tabs__tab--active', sb_isActive );
					sb_tab.setAttribute( 'aria-selected', sb_isActive ? 'true' : 'false' );
				} );
			};

			sb_panels.forEach( function ( sb_panel ) {
				var sb_key = sb_panel.getAttribute( 'data-sb-tab-panel' );
				var sb_heading = sb_panel.querySelector( 'h2' );

				if ( ! sb_key || ! sb_heading ) {
					return;
				}

				var sb_tab = sb_document.createElement( 'button' );
				sb_tab.type = 'button';
				sb_tab.className = 'sb-tabs__tab';
				sb_tab.setAttribute( 'data-sb-tab', sb_key );
				sb_tab.setAttribute( 'role', 'tab' );
				sb_tab.textContent = sb_heading.textContent;

				sb_tab.addEventListener( 'click', function () {
					sb_showPanel( sb_key );
				} );

				sb_nav.appendChild( sb_tab );
				sb_heading.classList.add( 'sb-hidden' );
			} );

			sb_container.insertBefore( sb_nav, sb_container.firstChild );
			sb_showPanel( sb_activeKey );
		} );
	}

	/**
	 * Render the row-level error table (capped to the first 50, matching
	 * the no-JS result page) inside a progress block's results container.
	 */
	function sb_renderErrors( sb_resultsEl, sb_errors, sb_downloadUrl ) {
		sb_resultsEl.innerHTML = '';

		if ( ! sb_errors || 0 === sb_errors.length ) {
			return;
		}

		var sb_table = sb_document.createElement( 'table' );
		sb_table.className = 'widefat striped sb-import-result__errors';

		var sb_thead = sb_document.createElement( 'thead' );
		var sb_headRow = sb_document.createElement( 'tr' );

		[ sb_config.i18n.row, sb_config.i18n.title, sb_config.i18n.errorColumn ].forEach( function ( sb_label ) {
			var sb_th = sb_document.createElement( 'th' );
			sb_th.textContent = sb_label;
			sb_headRow.appendChild( sb_th );
		} );

		sb_thead.appendChild( sb_headRow );
		sb_table.appendChild( sb_thead );

		var sb_tbody = sb_document.createElement( 'tbody' );

		sb_errors.slice( 0, 50 ).forEach( function ( sb_error ) {
			var sb_row = sb_document.createElement( 'tr' );

			[ sb_error.row, sb_error.title, sb_error.message ].forEach( function ( sb_value ) {
				var sb_td = sb_document.createElement( 'td' );
				sb_td.textContent = null === sb_value || undefined === sb_value ? '' : String( sb_value );
				sb_row.appendChild( sb_td );
			} );

			sb_tbody.appendChild( sb_row );
		} );

		sb_table.appendChild( sb_tbody );
		sb_resultsEl.appendChild( sb_table );

		if ( sb_downloadUrl ) {
			var sb_paragraph = sb_document.createElement( 'p' );
			var sb_link = sb_document.createElement( 'a' );
			sb_link.className = 'button';
			sb_link.href = sb_downloadUrl;
			sb_link.textContent = sb_config.i18n.downloadLog;
			sb_paragraph.appendChild( sb_link );
			sb_resultsEl.appendChild( sb_paragraph );
		}
	}

	/**
	 * POST a FormData body to admin-ajax.php as the given action,
	 * stamping on the shared admin nonce, and resolve with the parsed
	 * wp_send_json_*() response.
	 */
	function sb_ajax( sb_action, sb_body ) {
		sb_body.set( 'action', sb_action );
		sb_body.set( 'nonce', sb_window.sbAdmin.nonce );

		return sb_window.fetch( sb_window.sbAdmin.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: sb_body,
		} ).then( function ( sb_response ) {
			return sb_response.json();
		} );
	}

	/**
	 * Intercept a "[data-sb-import-form]" (the Import and Restore forms)
	 * submit and drive it through the chunked start/process_chunk AJAX
	 * flow instead of a normal multipart POST, animating the progress
	 * bar as each batch of rows comes back.
	 */
	function sb_initImportForm( sb_form ) {
		var sb_mode = sb_form.getAttribute( 'data-sb-mode' ) || 'import';
		var sb_fileInput = sb_form.querySelector( 'input[type="file"]' );
		var sb_progress = sb_form.querySelector( '[data-sb-import-progress]' );

		if ( ! sb_fileInput || ! sb_progress ) {
			return;
		}

		var sb_fill = sb_progress.querySelector( '[data-sb-import-fill]' );
		var sb_label = sb_progress.querySelector( '[data-sb-import-label]' );
		var sb_status = sb_progress.querySelector( '[data-sb-import-status]' );
		var sb_results = sb_progress.querySelector( '[data-sb-import-results]' );
		var sb_submitButton = sb_form.querySelector( '[type="submit"]' );

		var sb_setProgress = function ( sb_processed, sb_total ) {
			var sb_percent = sb_total > 0 ? Math.min( 100, Math.round( ( sb_processed / sb_total ) * 100 ) ) : 0;
			sb_fill.style.width = sb_percent + '%';
			sb_label.textContent = sb_percent + '%';
		};

		var sb_fail = function ( sb_message ) {
			sb_status.textContent = sb_message;

			if ( sb_submitButton ) {
				sb_submitButton.disabled = false;
			}
		};

		var sb_finish = function ( sb_data ) {
			sb_status.textContent = sb_config.i18n.done + ' ' + sb_format(
				sb_config.i18n.summary,
				[ sb_data.created, sb_data.updated, sb_data.skipped, sb_data.failed ]
			);

			sb_renderErrors( sb_results, sb_data.errors, sb_data.download_log_url );

			if ( sb_submitButton ) {
				sb_submitButton.disabled = false;
			}
		};

		var sb_poll = function ( sb_token ) {
			var sb_body = new sb_window.FormData();
			sb_body.append( 'token', sb_token );

			sb_ajax( sb_config.chunkAction, sb_body ).then( function ( sb_response ) {
				if ( ! sb_response || ! sb_response.success ) {
					sb_fail( sb_response && sb_response.data ? sb_response.data.message : sb_config.i18n.error );
					return;
				}

				var sb_data = sb_response.data;
				sb_setProgress( sb_data.processed, sb_data.total );
				sb_status.textContent = sb_format( sb_config.i18n.processing, [ sb_data.processed, sb_data.total ] );

				if ( sb_data.done ) {
					sb_finish( sb_data );
				} else {
					sb_poll( sb_token );
				}
			} ).catch( function () {
				sb_fail( sb_config.i18n.error );
			} );
		};

		sb_form.addEventListener( 'submit', function ( sb_event ) {
			if ( ! sb_fileInput.files || 0 === sb_fileInput.files.length ) {
				return;
			}

			sb_event.preventDefault();

			if ( sb_submitButton ) {
				sb_submitButton.disabled = true;
			}

			sb_progress.classList.remove( 'sb-hidden' );
			sb_setProgress( 0, 0 );
			sb_status.textContent = sb_config.i18n.preparing;
			sb_results.innerHTML = '';

			var sb_body = new sb_window.FormData( sb_form );
			sb_body.set( 'mode', sb_mode );

			sb_ajax( sb_config.startAction, sb_body ).then( function ( sb_response ) {
				if ( ! sb_response || ! sb_response.success ) {
					sb_fail( sb_response && sb_response.data ? sb_response.data.message : sb_config.i18n.error );
					return;
				}

				sb_setProgress( 0, sb_response.data.total );
				sb_poll( sb_response.data.token );
			} ).catch( function () {
				sb_fail( sb_config.i18n.error );
			} );
		} );
	}

	sb_ready( function () {
		sb_initTabs( sb_document );

		sb_document.querySelectorAll( '[data-sb-import-form]' ).forEach( function ( sb_form ) {
			sb_initImportForm( sb_form );
		} );
	} );
} )( window, document );
