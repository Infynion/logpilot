/**
 * LogPilot AI Analysis — front-end interactions.
 *
 * Handles: Get AI Fix fetch, severity badge, copy-to-clipboard,
 * .patch download, production lock banner, and post-apply countdown.
 *
 * Data supplied via wp_localize_script( 'logpilot-ai', 'logpilotAI', {...} ):
 *   ajax_url    {string}
 *   nonce       {string}
 *   log_id      {number}
 *   site_env    {string}  production | staging | development
 *   has_api_key {boolean}
 *
 * @package Infynion\Logpilot
 * @since   1.1.0
 */

/* global logpilotAI */

( function () {
	'use strict';

	// -----------------------------------------------------------------------
	// State
	// -----------------------------------------------------------------------
	let currentSuggestion = null;
	let countdownTimer    = null;
	let countdownSeconds  = 300; // 5 min

	// -----------------------------------------------------------------------
	// DOM references (populated after DOMContentLoaded)
	// -----------------------------------------------------------------------
	const el = {};

	// -----------------------------------------------------------------------
	// Severity badge configuration
	// -----------------------------------------------------------------------
	const SEVERITY_CONFIG = {
		critical: { label: 'CRITICAL', bg: '#dc3232', color: '#fff' },
		high:     { label: 'HIGH',     bg: '#f56e28', color: '#fff' },
		medium:   { label: 'MEDIUM',   bg: '#ffb900', color: '#333' },
		low:      { label: 'LOW',      bg: '#72aee6', color: '#fff' },
	};

	// -----------------------------------------------------------------------
	// Bootstrap
	// -----------------------------------------------------------------------
	document.addEventListener( 'DOMContentLoaded', function () {
		el.panel          = document.getElementById( 'logpilot-ai-panel' );
		el.getFixBtn      = document.getElementById( 'logpilot-get-ai-fix' );
		el.spinner        = document.getElementById( 'logpilot-ai-spinner' );
		el.diagnosisCard  = document.getElementById( 'logpilot-ai-diagnosis' );
		el.fixCard        = document.getElementById( 'logpilot-ai-fix' );
		el.envSection     = document.getElementById( 'logpilot-ai-env-section' );
		el.errorBox       = document.getElementById( 'logpilot-ai-error' );
		el.severityBadge  = document.getElementById( 'logpilot-severity-badge' );
		el.confidenceBar  = document.getElementById( 'logpilot-confidence-bar' );
		el.explanation    = document.getElementById( 'logpilot-explanation' );
		el.fixCode        = document.getElementById( 'logpilot-fix-code' );
		el.targetFile     = document.getElementById( 'logpilot-target-file' );
		el.copyBtn        = document.getElementById( 'logpilot-copy-btn' );
		el.downloadPatch  = document.getElementById( 'logpilot-download-patch' );
		el.prodBanner     = document.getElementById( 'logpilot-production-banner' );
		el.prodBannerTitle = document.getElementById( 'logpilot-prod-banner-title' );
		el.prodBannerMsg  = document.getElementById( 'logpilot-prod-banner-msg' );
		el.stagingSection = document.getElementById( 'logpilot-staging-section' );
		el.appliedSection = document.getElementById( 'logpilot-applied-section' );
		el.countdown      = document.getElementById( 'logpilot-countdown' );
		el.confirmBtn     = document.getElementById( 'logpilot-confirm-btn' );
		el.rollbackBtn    = document.getElementById( 'logpilot-rollback-btn' );

		if ( el.getFixBtn ) {
			el.getFixBtn.addEventListener( 'click', onGetFix );
		}
		if ( el.copyBtn ) {
			el.copyBtn.addEventListener( 'click', onCopy );
		}
		if ( el.downloadPatch ) {
			el.downloadPatch.addEventListener( 'click', onDownloadPatch );
		}
		if ( el.confirmBtn ) {
			el.confirmBtn.addEventListener( 'click', onConfirm );
		}
		if ( el.rollbackBtn ) {
			el.rollbackBtn.addEventListener( 'click', onRollback );
		}
	} );

	// -----------------------------------------------------------------------
	// Handlers
	// -----------------------------------------------------------------------

	/**
	 * Fetch an AI suggestion for the current log entry.
	 */
	function onGetFix() {
		setLoading( true );
		hideError();

		const body = new URLSearchParams( {
			action: 'logpilot_get_suggestion',
			nonce:  logpilotAI.nonce,
			log_id: logpilotAI.log_id,
		} );

		fetch( logpilotAI.ajax_url, {
			method:      'POST',
			credentials: 'same-origin',
			headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
			body:        body.toString(),
		} )
			.then( ( r ) => r.json() )
			.then( ( json ) => {
				if ( ! json.success ) {
					showError( json.data && json.data.message ? json.data.message : 'Unknown error.' );
					return;
				}
				renderSuggestion( json.data );
			} )
			.catch( ( err ) => showError( err.message || 'Network error.' ) )
			.finally( () => setLoading( false ) );
	}

	/**
	 * Copy the suggested fix code to clipboard.
	 */
	function onCopy() {
		if ( ! currentSuggestion ) {
			return;
		}
		const code = ( currentSuggestion.docblock ? currentSuggestion.docblock + '\n' : '' )
			+ currentSuggestion.suggested_code;

		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( code ).then( () => flashCopied() );
		} else {
			// Fallback for older browsers.
			const ta = document.createElement( 'textarea' );
			ta.value = code;
			ta.style.position = 'fixed';
			ta.style.opacity  = '0';
			document.body.appendChild( ta );
			ta.select();
			try {
				document.execCommand( 'copy' );
				flashCopied();
			} catch ( e ) { /* silent */ }
			document.body.removeChild( ta );
		}
	}

	/**
	 * Fetch and trigger download of a .patch file.
	 *
	 * @param {Event} e Click event.
	 */
	function onDownloadPatch( e ) {
		e.preventDefault();
		if ( ! currentSuggestion ) {
			return;
		}

		const body = new URLSearchParams( {
			action:        'logpilot_get_patch',
			nonce:         logpilotAI.nonce,
			suggestion_id: currentSuggestion.id,
		} );

		fetch( logpilotAI.ajax_url, {
			method:      'POST',
			credentials: 'same-origin',
			headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
			body:        body.toString(),
		} )
			.then( ( r ) => r.json() )
			.then( ( json ) => {
				if ( ! json.success || ! json.data.patch ) {
					showError( 'Failed to generate patch.' );
					return;
				}
				const blob = new Blob( [ json.data.patch ], { type: 'text/x-patch' } );
				const url  = URL.createObjectURL( blob );
				const a    = document.createElement( 'a' );
				a.href     = url;
				a.download = 'logpilot-fix-' + logpilotAI.log_id + '.patch';
				document.body.appendChild( a );
				a.click();
				document.body.removeChild( a );
				URL.revokeObjectURL( url );
			} )
			.catch( ( err ) => showError( err.message || 'Network error.' ) );
	}

	/**
	 * Confirm the applied fix is working (Pro feature stub).
	 */
	function onConfirm() {
		clearInterval( countdownTimer );
		showAppliedSection( false );
	}

	/**
	 * Roll back the applied fix (Pro feature stub).
	 */
	function onRollback() {
		clearInterval( countdownTimer );
		showAppliedSection( false );
	}

	// -----------------------------------------------------------------------
	// Rendering helpers
	// -----------------------------------------------------------------------

	/**
	 * Populate and reveal all AI result cards.
	 *
	 * @param {Object} suggestion Suggestion data from the AJAX response.
	 */
	function renderSuggestion( suggestion ) {
		currentSuggestion = suggestion;

		// Severity badge.
		const cfg = SEVERITY_CONFIG[ suggestion.severity ] || SEVERITY_CONFIG.medium;
		el.severityBadge.textContent          = cfg.label;
		el.severityBadge.style.background     = cfg.bg;
		el.severityBadge.style.color          = cfg.color;

		// Confidence.
		const conf = parseInt( suggestion.confidence, 10 ) || 50;
		el.confidenceBar.textContent = 'Confidence: ' + conf + '%';
		if ( conf < 50 ) {
			el.confidenceBar.style.color = '#dc3232';
			el.confidenceBar.title       = 'AI is not certain — review carefully before applying.';
		}

		// Explanation.
		el.explanation.textContent = suggestion.explanation;

		// Code block — docblock + suggested code.
		const displayCode = ( suggestion.docblock ? suggestion.docblock + '\n' : '' ) + suggestion.suggested_code;
		const codeEl      = el.fixCode.querySelector( 'code' ) || el.fixCode;
		codeEl.textContent = displayCode;

		// Target file.
		el.targetFile.textContent = suggestion.target_file;

		// Provider label as title attribute.
		if ( suggestion.provider_label ) {
			el.diagnosisCard.title = 'Analysis by: ' + suggestion.provider_label;
		}

		// Show cards.
		show( el.diagnosisCard );
		show( el.fixCard );

		// Env-specific section.
		show( el.envSection );
		renderEnvSection( suggestion );

		// Hide the Get AI Fix button after a successful load.
		hide( el.getFixBtn );
	}

	/**
	 * Render the environment-specific action section.
	 *
	 * @param {Object} suggestion Suggestion data.
	 */
	function renderEnvSection( suggestion ) {
		const env        = logpilotAI.site_env || 'production';
		const isCoreFile = suggestion.is_core_file;
		const isProd     = ( 'production' === env ) || isCoreFile;

		if ( isProd ) {
			if ( isCoreFile ) {
				el.prodBannerTitle.innerHTML = '&#128274; WORDPRESS CORE FILE — Changes Disabled';
				el.prodBannerMsg.textContent =
					'This error originated in a WordPress core file. ' +
					'Core files cannot be modified automatically in any environment. ' +
					'Copy the suggested fix above and apply it via a child theme, custom plugin, or by updating WordPress.';
			}
			show( el.prodBanner );
			hide( el.stagingSection );
		} else {
			hide( el.prodBanner );
			show( el.stagingSection );
		}

		hide( el.appliedSection );
	}

	// -----------------------------------------------------------------------
	// UI utilities
	// -----------------------------------------------------------------------

	function setLoading( isLoading ) {
		if ( el.getFixBtn ) {
			el.getFixBtn.disabled = isLoading;
		}
		if ( el.spinner ) {
			el.spinner.style.display = isLoading ? 'inline-block' : 'none';
		}
	}

	function showError( message ) {
		if ( el.errorBox ) {
			el.errorBox.textContent  = message;
			el.errorBox.style.display = 'block';
		}
	}

	function hideError() {
		if ( el.errorBox ) {
			el.errorBox.style.display = 'none';
			el.errorBox.textContent   = '';
		}
	}

	function show( element ) {
		if ( element ) {
			element.style.display = '';
		}
	}

	function hide( element ) {
		if ( element ) {
			element.style.display = 'none';
		}
	}

	function showAppliedSection( visible ) {
		if ( visible ) {
			show( el.appliedSection );
			hide( el.stagingSection );
			startCountdown();
		} else {
			hide( el.appliedSection );
		}
	}

	function flashCopied() {
		if ( ! el.copyBtn ) {
			return;
		}
		const original = el.copyBtn.innerHTML;
		el.copyBtn.innerHTML = '&#10003; Copied!';
		el.copyBtn.disabled  = true;
		setTimeout( function () {
			el.copyBtn.innerHTML = original;
			el.copyBtn.disabled  = false;
		}, 2000 );
	}

	// -----------------------------------------------------------------------
	// Countdown timer (for post-apply confirmation — Pro feature)
	// -----------------------------------------------------------------------

	function startCountdown() {
		countdownSeconds = 300;
		updateCountdownDisplay();
		clearInterval( countdownTimer );
		countdownTimer = setInterval( function () {
			countdownSeconds--;
			updateCountdownDisplay();
			if ( countdownSeconds <= 0 ) {
				clearInterval( countdownTimer );
				// Trigger auto-rollback (Pro: AJAX call; here just UI reset).
				showAppliedSection( false );
			}
		}, 1000 );
	}

	function updateCountdownDisplay() {
		if ( ! el.countdown ) {
			return;
		}
		const m = String( Math.floor( countdownSeconds / 60 ) ).padStart( 2, '0' );
		const s = String( countdownSeconds % 60 ).padStart( 2, '0' );
		el.countdown.textContent = m + ':' + s;
	}
}() );
