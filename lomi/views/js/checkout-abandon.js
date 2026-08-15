
function isPlainObject(value) {
  return value !== null && !Array.isArray(value) && Object(value) === value;
}
function isTranslationLeaf(value) {
  return value === null || value === undefined || Object(value) !== value;
}
function isStringValue(value) {
  return Object.prototype.toString.call(value) === '[object String]';
}
( function () {
	'use strict';

	if ( (lomiCheckoutParams === void 0) ) {
		return;
	}

	var STORAGE_KEY = lomiCheckoutParams.storageKey || 'ps_lomi_checkout_redirect';
	var abandonUrl = lomiCheckoutParams.abandonUrl || '';

	function markRedirectPending() {
		try {
			sessionStorage.setItem(
				STORAGE_KEY,
				JSON.stringify( { startedAt: Date.now() } )
			);
		} catch ( error ) {
			// Ignore private browsing storage errors.
		}
	}

	function handleAbandonedCheckout() {
		var raw;
		try {
			raw = sessionStorage.getItem( STORAGE_KEY );
		} catch ( error ) {
			return;
		}

		if ( ! raw ) {
			return;
		}

		try {
			sessionStorage.removeItem( STORAGE_KEY );
		} catch ( error ) {
			// Ignore.
		}

		if ( ! abandonUrl ) {
			window.location.reload();
			return;
		}

		window.fetch( abandonUrl, {
			method: 'GET',
			credentials: 'same-origin',
			headers: { Accept: 'application/json' },
		} )
			.catch( function () {
				return null;
			} )
			.finally( function () {
				window.location.reload();
			} );
	}

	function bindCheckoutForm() {
		var form = document.querySelector( '#payment-confirmation button[type="submit"]' );
		if ( ! form ) {
			return;
		}

		form.addEventListener( 'click', function () {
			var lomiOption = document.querySelector( 'input[name="payment-option"][data-module-name="lomi"]' );
			if ( lomiOption && lomiOption.checked ) {
				markRedirectPending();
			}
		} );

		var paymentForm = document.querySelector( '#payment-form' );
		if ( paymentForm ) {
			paymentForm.addEventListener( 'submit', function () {
				var selected = document.querySelector( 'input[name="payment-option"]:checked' );
				if ( selected && selected.getAttribute( 'data-module-name' ) === 'lomi' ) {
					markRedirectPending();
				}
			} );
		}
	}

	function shouldHandleAbandon() {
		return document.body.id === 'checkout' || document.body.classList.contains( 'checkout' );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! shouldHandleAbandon() ) {
			try {
				sessionStorage.removeItem( STORAGE_KEY );
			} catch ( error ) {
				// Ignore.
			}
			return;
		}

		handleAbandonedCheckout();
		bindCheckoutForm();
	} );

	window.addEventListener( 'pageshow', function ( event ) {
		if ( ! shouldHandleAbandon() ) {
			return;
		}

		if ( event.persisted ) {
			handleAbandonedCheckout();
		}
	} );
} )();
