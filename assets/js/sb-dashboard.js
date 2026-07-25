/**
 * SmartBook dashboard charts.
 *
 * Reads the `sbDashboardCharts` object localized by
 * SmartBook\Admin\Pages\DashboardPage (real, server-computed data — no
 * client-side fetching) and renders it with Chart.js, which is enqueued
 * as this script's dependency (see assets/js/vendor/chart.umd.min.js).
 * Colours are read from the same CSS custom properties sb-admin.css
 * defines, so the charts follow the same light/dark palette as the
 * rest of the admin UI automatically.
 */
( function ( sb_window, sb_document ) {
	'use strict';

	if ( typeof sb_window.sbDashboardCharts === 'undefined' || typeof sb_window.Chart === 'undefined' ) {
		return;
	}

	/**
	 * Read a CSS custom property's current value.
	 */
	function sb_cssVar( sb_name, sb_fallback ) {
		var sb_value = getComputedStyle( sb_document.documentElement ).getPropertyValue( sb_name ).trim();

		return sb_value || sb_fallback;
	}

	/**
	 * Build a repeating colour palette (used for multi-slice charts like
	 * the genre/author breakdowns) long enough for the given data length.
	 */
	function sb_palette( sb_length ) {
		var sb_base = [
			sb_cssVar( '--sb-color-primary', '#2271b1' ),
			sb_cssVar( '--sb-color-success', '#00a32a' ),
			sb_cssVar( '--sb-color-star-filled', '#dba617' ),
			sb_cssVar( '--sb-color-error', '#d63638' ),
			sb_cssVar( '--sb-color-primary-hover', '#135e96' ),
			sb_cssVar( '--sb-color-text-muted', '#646970' )
		];
		var sb_colors = [];

		for ( var sb_i = 0; sb_i < sb_length; sb_i++ ) {
			sb_colors.push( sb_base[ sb_i % sb_base.length ] );
		}

		return sb_colors;
	}

	/**
	 * Shared Chart.js options for single-series charts: no legend (one
	 * series needs no key) and text/grid colours that adapt to the
	 * current colour scheme.
	 */
	function sb_baseOptions() {
		var sb_textColor = sb_cssVar( '--sb-color-text-muted', '#646970' );
		var sb_gridColor = sb_cssVar( '--sb-color-border', '#dcdcde' );

		return {
			responsive: true,
			maintainAspectRatio: false,
			plugins: {
				legend: { display: false }
			},
			scales: {
				x: {
					ticks: { color: sb_textColor },
					grid: { color: sb_gridColor }
				},
				y: {
					ticks: { color: sb_textColor },
					grid: { color: sb_gridColor }
				}
			}
		};
	}

	/**
	 * Render one chart into a canvas, if both the canvas and its data exist.
	 */
	function sb_renderChart( sb_canvasId, sb_type, sb_dataset, sb_config ) {
		var sb_canvas = sb_document.getElementById( sb_canvasId );

		if ( ! sb_canvas || ! sb_dataset || 0 === sb_dataset.labels.length ) {
			return;
		}

		new sb_window.Chart( sb_canvas, sb_config( sb_dataset ) );
	}

	function sb_init() {
		var sb_charts = sb_window.sbDashboardCharts;

		sb_renderChart( 'sb-chart-books-per-year', 'bar', sb_charts.booksPerYear, function ( sb_dataset ) {
			var sb_options = sb_baseOptions();

			return {
				type: 'bar',
				data: {
					labels: sb_dataset.labels,
					datasets: [ {
						label: 'Books',
						data: sb_dataset.data,
						backgroundColor: sb_cssVar( '--sb-color-primary', '#2271b1' )
					} ]
				},
				options: sb_options
			};
		} );

		sb_renderChart( 'sb-chart-books-per-genre', 'doughnut', sb_charts.booksPerGenre, function ( sb_dataset ) {
			return {
				type: 'doughnut',
				data: {
					labels: sb_dataset.labels,
					datasets: [ {
						data: sb_dataset.data,
						backgroundColor: sb_palette( sb_dataset.data.length )
					} ]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: {
							position: 'right',
							labels: { color: sb_cssVar( '--sb-color-text-muted', '#646970' ) }
						}
					}
				}
			};
		} );

		sb_renderChart( 'sb-chart-books-per-author', 'bar', sb_charts.booksPerAuthor, function ( sb_dataset ) {
			var sb_options = sb_baseOptions();
			sb_options.indexAxis = 'y';

			return {
				type: 'bar',
				data: {
					labels: sb_dataset.labels,
					datasets: [ {
						label: 'Books',
						data: sb_dataset.data,
						backgroundColor: sb_cssVar( '--sb-color-success', '#00a32a' )
					} ]
				},
				options: sb_options
			};
		} );

		sb_renderChart( 'sb-chart-monthly-reading', 'line', sb_charts.monthlyReading, function ( sb_dataset ) {
			return {
				type: 'line',
				data: {
					labels: sb_dataset.labels,
					datasets: [ {
						label: 'Books',
						data: sb_dataset.data,
						borderColor: sb_cssVar( '--sb-color-primary', '#2271b1' ),
						backgroundColor: sb_cssVar( '--sb-color-primary', '#2271b1' ),
						tension: 0.3,
						fill: false
					} ]
				},
				options: sb_baseOptions()
			};
		} );
	}

	if ( 'loading' !== sb_document.readyState ) {
		sb_init();
	} else {
		sb_document.addEventListener( 'DOMContentLoaded', sb_init );
	}
} )( window, document );
