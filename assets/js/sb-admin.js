/**
 * SmartBook admin scripts.
 *
 * Progressively enhances the server-rendered admin markup (meta box,
 * dashboard, statistics, import/export, settings) — every feature here
 * degrades gracefully to plain HTML if JavaScript never runs. Relies on
 * the `sbAdmin` object localized by SmartBook\Assets\AdminAssetLoader
 * (ajaxUrl, nonce); that object is a pre-existing PHP<->JS data
 * contract kept in its established camelCase form, everything else
 * declared in this file is prefixed sb_.
 */
( function ( sb_window, sb_document ) {
	'use strict';

	if ( typeof sb_window.sbAdmin === 'undefined' ) {
		return;
	}

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
	 * Add a dismiss button to every notice that doesn't already have one.
	 */
	function sb_initNotices( sb_scope ) {
		var sb_notices = sb_scope.querySelectorAll( '.sb-notice' );

		sb_notices.forEach( function ( sb_notice ) {
			if ( sb_notice.querySelector( '.sb-notice__dismiss' ) ) {
				return;
			}

			var sb_dismiss = sb_document.createElement( 'button' );
			sb_dismiss.type = 'button';
			sb_dismiss.className = 'sb-notice__dismiss';
			sb_dismiss.setAttribute( 'aria-label', 'Dismiss this notice' );
			sb_dismiss.innerHTML = '&times;';

			sb_dismiss.addEventListener( 'click', function () {
				sb_notice.classList.add( 'sb-hidden' );
			} );

			sb_notice.appendChild( sb_dismiss );
		} );
	}

	/**
	 * Turn each meta box section into a collapsible accordion panel.
	 * Section titles/panels are matched purely by their existing DOM
	 * order (a title immediately followed by a ".sb-meta-box__grid"),
	 * so no server-side markup changes are required; every id this
	 * generates is created here, so it is guaranteed "sb-" prefixed.
	 */
	function sb_initAccordions( sb_scope ) {
		var sb_titles = sb_scope.querySelectorAll( '.sb-meta-box__section-title' );

		sb_titles.forEach( function ( sb_title, sb_index ) {
			var sb_panel = sb_title.nextElementSibling;

			if ( ! sb_panel || ! sb_panel.classList.contains( 'sb-meta-box__grid' ) ) {
				return;
			}

			var sb_panelId = 'sb-section-panel-' + sb_index;
			sb_panel.id = sb_panelId;

			var sb_toggle = sb_document.createElement( 'button' );
			sb_toggle.type = 'button';
			sb_toggle.className = 'sb-accordion__toggle';
			sb_toggle.setAttribute( 'aria-expanded', 'true' );
			sb_toggle.setAttribute( 'aria-controls', sb_panelId );
			sb_toggle.innerHTML = sb_title.textContent + '<span class="sb-accordion__icon" aria-hidden="true"></span>';

			sb_title.textContent = '';
			sb_title.appendChild( sb_toggle );

			sb_toggle.addEventListener( 'click', function () {
				var sb_expanded = 'true' === sb_toggle.getAttribute( 'aria-expanded' );
				sb_toggle.setAttribute( 'aria-expanded', sb_expanded ? 'false' : 'true' );
				sb_panel.classList.toggle( 'sb-hidden', sb_expanded );
			} );
		} );
	}

	/**
	 * Replace the plain "sb_rating" number input with a clickable star
	 * picker. The original input is kept (visually hidden) so the value
	 * still submits normally with the rest of the form.
	 */
	function sb_initRatingFields( sb_scope ) {
		var sb_inputs = sb_scope.querySelectorAll( 'input[type="number"][name="sb_rating"]' );

		sb_inputs.forEach( function ( sb_input ) {
			var sb_max = parseInt( sb_input.getAttribute( 'max' ), 10 ) || 5;
			var sb_value = parseInt( sb_input.value, 10 ) || 0;

			var sb_wrapper = sb_document.createElement( 'div' );
			sb_wrapper.className = 'sb-rating';
			sb_wrapper.setAttribute( 'role', 'radiogroup' );
			sb_wrapper.setAttribute( 'aria-label', 'Rating' );

			var sb_paintStars = function () {
				sb_wrapper.querySelectorAll( '.sb-rating__star' ).forEach( function ( sb_star ) {
					var sb_starValue = parseInt( sb_star.getAttribute( 'data-sb-value' ), 10 );
					sb_star.classList.toggle( 'sb-rating__star--filled', sb_starValue <= sb_value );
					sb_star.setAttribute( 'aria-checked', sb_starValue === sb_value ? 'true' : 'false' );
				} );
			};

			for ( var sb_i = 1; sb_i <= sb_max; sb_i++ ) {
				var sb_star = sb_document.createElement( 'button' );
				sb_star.type = 'button';
				sb_star.className = 'sb-rating__star';
				sb_star.setAttribute( 'data-sb-value', String( sb_i ) );
				sb_star.setAttribute( 'role', 'radio' );
				sb_star.setAttribute( 'aria-label', sb_i + ( 1 === sb_i ? ' star' : ' stars' ) );
				sb_star.innerHTML = '&#9733;';

				sb_star.addEventListener( 'click', function ( sb_event ) {
					var sb_clickedValue = parseInt( sb_event.currentTarget.getAttribute( 'data-sb-value' ), 10 );
					sb_value = sb_clickedValue === sb_value ? 0 : sb_clickedValue;
					sb_input.value = String( sb_value );
					sb_input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					sb_paintStars();
				} );

				sb_wrapper.appendChild( sb_star );
			}

			sb_paintStars();
			sb_input.classList.add( 'sb-hidden' );
			sb_input.insertAdjacentElement( 'afterend', sb_wrapper );
		} );
	}

	/**
	 * Add a live visual bar under the "sb_progress" number input that
	 * updates as the value changes.
	 */
	function sb_initProgressFields( sb_scope ) {
		var sb_inputs = sb_scope.querySelectorAll( 'input[type="number"][name="sb_progress"]' );

		sb_inputs.forEach( function ( sb_input ) {
			var sb_bar = sb_document.createElement( 'div' );
			sb_bar.className = 'sb-progress-bar';

			var sb_track = sb_document.createElement( 'div' );
			sb_track.className = 'sb-progress-bar__track';

			var sb_fill = sb_document.createElement( 'div' );
			sb_fill.className = 'sb-progress-bar__fill';

			var sb_label = sb_document.createElement( 'span' );
			sb_label.className = 'sb-progress-bar__label';

			sb_track.appendChild( sb_fill );
			sb_bar.appendChild( sb_track );
			sb_bar.appendChild( sb_label );

			var sb_updateBar = function () {
				var sb_percent = Math.min( 100, Math.max( 0, parseInt( sb_input.value, 10 ) || 0 ) );
				sb_fill.style.width = sb_percent + '%';
				sb_label.textContent = sb_percent + '%';
			};

			sb_input.addEventListener( 'input', sb_updateBar );
			sb_updateBar();

			sb_input.insertAdjacentElement( 'afterend', sb_bar );
		} );
	}

	/**
	 * Auto-resize every textarea inside the meta box to fit its content.
	 */
	function sb_initTextareaAutosize( sb_scope ) {
		var sb_textareas = sb_scope.querySelectorAll( '.sb-meta-box textarea' );

		sb_textareas.forEach( function ( sb_textarea ) {
			var sb_resize = function () {
				sb_textarea.style.height = 'auto';
				sb_textarea.style.height = sb_textarea.scrollHeight + 'px';
			};

			sb_textarea.addEventListener( 'input', sb_resize );
			sb_resize();
		} );
	}

	/**
	 * Ask for confirmation before submitting a form that uploads a file
	 * (the SmartBook CSV import form), since it can create or update
	 * many books at once.
	 */
	function sb_initImportConfirm( sb_scope ) {
		var sb_fileInputs = sb_scope.querySelectorAll( 'input[type="file"]' );

		sb_fileInputs.forEach( function ( sb_fileInput ) {
			var sb_form = sb_fileInput.closest( 'form' );

			if ( ! sb_form ) {
				return;
			}

			sb_form.addEventListener( 'submit', function ( sb_event ) {
				if ( ! sb_fileInput.files || 0 === sb_fileInput.files.length ) {
					return;
				}

				var sb_message = sb_window.sbAdmin.confirmImportMessage
					|| 'This will create or update books from the uploaded file. Continue?';

				if ( ! sb_window.confirm( sb_message ) ) {
					sb_event.preventDefault();
				}
			} );
		} );
	}

	/**
	 * Ask for confirmation before following any link marked with a
	 * "data-sb-confirm" message (the books table's Trash/Delete
	 * Permanently/Restore row actions).
	 */
	function sb_initConfirmLinks( sb_scope ) {
		var sb_links = sb_scope.querySelectorAll( '[data-sb-confirm]' );

		sb_links.forEach( function ( sb_link ) {
			sb_link.addEventListener( 'click', function ( sb_event ) {
				var sb_message = sb_link.getAttribute( 'data-sb-confirm' );

				if ( sb_message && ! sb_window.confirm( sb_message ) ) {
					sb_event.preventDefault();
				}
			} );
		} );
	}

	/**
	 * Wire up "[data-sb-print]" buttons (the QR label print sheet) to
	 * trigger the browser's print dialog.
	 */
	function sb_initPrintButtons( sb_scope ) {
		var sb_buttons = sb_scope.querySelectorAll( '[data-sb-print]' );

		sb_buttons.forEach( function ( sb_button ) {
			sb_button.addEventListener( 'click', function () {
				sb_window.print();
			} );
		} );
	}

	/**
	 * Wire up the QR label selection checklist's "Select All" checkbox.
	 */
	function sb_initSelectAll( sb_scope ) {
		var sb_selectAll = sb_scope.getElementById( 'sb-select-all-books' );

		if ( ! sb_selectAll ) {
			return;
		}

		var sb_checkboxes = sb_scope.querySelectorAll( '.sb-label-select-list__checkbox' );

		sb_selectAll.addEventListener( 'change', function () {
			sb_checkboxes.forEach( function ( sb_checkbox ) {
				sb_checkbox.checked = sb_selectAll.checked;
			} );
		} );
	}

	sb_ready( function () {
		sb_initNotices( sb_document );
		sb_initAccordions( sb_document );
		sb_initRatingFields( sb_document );
		sb_initProgressFields( sb_document );
		sb_initTextareaAutosize( sb_document );
		sb_initImportConfirm( sb_document );
		sb_initConfirmLinks( sb_document );
		sb_initPrintButtons( sb_document );
		sb_initSelectAll( sb_document );

		sb_document.dispatchEvent( new CustomEvent( 'sb:admin:ready', { detail: sb_window.sbAdmin } ) );
	} );
} )( window, document );
