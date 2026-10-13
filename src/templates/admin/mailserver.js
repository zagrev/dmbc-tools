/* global wp, dmbcMailserver */
( function () {
	'use strict';

	const root = document.querySelector( '.dmbc-mailserver' );
	if ( ! root || ! window.wp || ! wp.apiFetch ) {
		return;
	}

	const basePath = dmbcMailserver.path;
	const notice = document.getElementById( 'dmbc-mailserver-notice' );
	const accountsBody = document.getElementById( 'dmbc-mailserver-accounts' );
	const aliasesBody = document.getElementById( 'dmbc-mailserver-aliases' );
	const accountList = document.getElementById( 'dmbc-mailserver-account-list' );

	function api( path, options ) {
		return wp.apiFetch( Object.assign( { path: basePath + path }, options || {} ) );
	}

	function showNotice( message, type ) {
		notice.className = 'notice inline notice-' + ( type || 'success' );
		notice.querySelector( 'p' ).textContent = message;
		notice.hidden = false;
	}

	function errorMessage( error ) {
		return ( error && error.message ) || 'The request failed.';
	}

	function el( tag, text, attrs ) {
		const node = document.createElement( tag );
		if ( text !== undefined && text !== null ) {
			node.textContent = text;
		}
		Object.entries( attrs || {} ).forEach( ( [ key, value ] ) => node.setAttribute( key, value ) );
		return node;
	}

	function messageRow( body, colspan, text ) {
		body.replaceChildren();
		const row = el( 'tr' );
		row.appendChild( el( 'td', text, { colspan: String( colspan ) } ) );
		body.appendChild( row );
	}

	function setStat( name, value, className ) {
		const node = root.querySelector( '[data-stat="' + name + '"]' );
		node.textContent = value;
		node.className = className || '';
	}

	// Dashboard ---------------------------------------------------------------.

	function loadStatus() {
		return api( '/status' )
			.then( ( status ) => {
				const state = status.status || 'unknown';
				const resources = status.resources || {};
				setStat( 'status', state.charAt( 0 ).toUpperCase() + state.slice( 1 ), 'is-' + state );
				setStat( 'cpu', resources.cpu || 'N/A' );
				setStat( 'memory', resources.memory || 'N/A' );
				setStat( 'disk', resources.disk || 'N/A' );
			} )
			.catch( ( error ) => {
				setStat( 'status', 'Unknown', 'is-unknown' );
				showNotice( errorMessage( error ), 'error' );
			} );
	}

	// Accounts ----------------------------------------------------------------.

	function passwordRow( email, afterRow ) {
		const existing = accountsBody.querySelector( '.dmbc-password-row' );
		if ( existing ) {
			existing.remove();
		}
		const row = el( 'tr', null, { class: 'dmbc-password-row' } );
		const cell = el( 'td', null, { colspan: '5' } );
		const form = el( 'form', null, { class: 'dmbc-mailserver-form' } );
		form.appendChild( el( 'label', 'New password for ' + email ) );
		const password = el( 'input', null, { type: 'password', class: 'regular-text', autocomplete: 'new-password', required: '' } );
		form.appendChild( password );
		form.appendChild( el( 'label', 'Confirm password' ) );
		const confirm = el( 'input', null, { type: 'password', class: 'regular-text', autocomplete: 'new-password', required: '' } );
		form.appendChild( confirm );
		const actions = el( 'div' );
		actions.appendChild( el( 'button', 'Save Password', { type: 'submit', class: 'button button-primary' } ) );
		const cancel = el( 'button', 'Cancel', { type: 'button', class: 'button' } );
		cancel.addEventListener( 'click', () => row.remove() );
		actions.appendChild( cancel );
		form.appendChild( actions );
		form.addEventListener( 'submit', ( event ) => {
			event.preventDefault();
			if ( password.value !== confirm.value ) {
				showNotice( 'Passwords do not match.', 'error' );
				return;
			}
			api( '/accounts/' + encodeURIComponent( email ) + '/password', { method: 'PUT', data: { password: password.value } } )
				.then( () => {
					row.remove();
					showNotice( 'Password updated for ' + email + '.' );
				} )
				.catch( ( error ) => showNotice( errorMessage( error ), 'error' ) );
		} );
		cell.appendChild( form );
		row.appendChild( cell );
		afterRow.after( row );
		password.focus();
	}

	function renderAccounts( accounts ) {
		setStat( 'accounts', String( accounts.length ) );
		accountList.replaceChildren( ...accounts.map( ( account ) => el( 'option', null, { value: account.email } ) ) );
		if ( ! accounts.length ) {
			messageRow( accountsBody, 5, 'No email accounts found.' );
			return;
		}
		accountsBody.replaceChildren();
		accounts.forEach( ( account ) => {
			const storage = account.storage || {};
			const row = el( 'tr' );
			row.appendChild( el( 'td', account.email ) );
			row.appendChild( el( 'td', storage.used || '' ) );
			row.appendChild( el( 'td', storage.total || '' ) );
			row.appendChild( el( 'td', storage.percent || '' ) );
			const actions = el( 'td' );
			const change = el( 'button', 'Change Password', { type: 'button', class: 'button' } );
			change.addEventListener( 'click', () => passwordRow( account.email, row ) );
			const remove = el( 'button', 'Delete', { type: 'button', class: 'button button-link-delete' } );
			remove.addEventListener( 'click', () => {
				if ( ! window.confirm( 'Delete the email account ' + account.email + '? Its mailbox will be removed.' ) ) {
					return;
				}
				api( '/accounts/' + encodeURIComponent( account.email ), { method: 'DELETE' } )
					.then( () => {
						showNotice( 'Deleted ' + account.email + '.' );
						return loadAccounts();
					} )
					.catch( ( error ) => showNotice( errorMessage( error ), 'error' ) );
			} );
			actions.appendChild( change );
			actions.appendChild( remove );
			row.appendChild( actions );
			accountsBody.appendChild( row );
		} );
	}

	function loadAccounts() {
		return api( '/accounts' )
			.then( renderAccounts )
			.catch( ( error ) => {
				setStat( 'accounts', 'N/A' );
				messageRow( accountsBody, 5, errorMessage( error ) );
			} );
	}

	document.getElementById( 'dmbc-mailserver-account-form' ).addEventListener( 'submit', ( event ) => {
		event.preventDefault();
		const form = event.currentTarget;
		if ( form.password.value !== form.password_confirm.value ) {
			showNotice( 'Passwords do not match.', 'error' );
			return;
		}
		const email = form.email.value.trim();
		api( '/accounts', { method: 'POST', data: { email, password: form.password.value } } )
			.then( () => {
				form.reset();
				showNotice( 'Created ' + email + '.' );
				return loadAccounts();
			} )
			.catch( ( error ) => showNotice( errorMessage( error ), 'error' ) );
	} );

	// Aliases -----------------------------------------------------------------.

	function renderAliases( aliases ) {
		setStat( 'aliases', String( aliases.length ) );
		if ( ! aliases.length ) {
			messageRow( aliasesBody, 3, 'No aliases found.' );
			return;
		}
		aliasesBody.replaceChildren();
		aliases.forEach( ( alias ) => {
			const row = el( 'tr' );
			row.appendChild( el( 'td', alias.source ) );
			row.appendChild( el( 'td', alias.destination ) );
			const actions = el( 'td' );
			const remove = el( 'button', 'Delete', { type: 'button', class: 'button button-link-delete' } );
			remove.addEventListener( 'click', () => {
				if ( ! window.confirm( 'Delete the alias ' + alias.source + ' → ' + alias.destination + '?' ) ) {
					return;
				}
				api( '/aliases/' + encodeURIComponent( alias.source ) + '/' + encodeURIComponent( alias.destination ), { method: 'DELETE' } )
					.then( () => {
						showNotice( 'Deleted alias ' + alias.source + '.' );
						return loadAliases();
					} )
					.catch( ( error ) => showNotice( errorMessage( error ), 'error' ) );
			} );
			actions.appendChild( remove );
			row.appendChild( actions );
			aliasesBody.appendChild( row );
		} );
	}

	function loadAliases() {
		return api( '/aliases' )
			.then( renderAliases )
			.catch( ( error ) => {
				setStat( 'aliases', 'N/A' );
				messageRow( aliasesBody, 3, errorMessage( error ) );
			} );
	}

	document.getElementById( 'dmbc-mailserver-alias-form' ).addEventListener( 'submit', ( event ) => {
		event.preventDefault();
		const form = event.currentTarget;
		const source = form.source.value.trim();
		const destination = form.destination.value.trim();
		api( '/aliases', { method: 'POST', data: { source, destination } } )
			.then( () => {
				form.reset();
				showNotice( 'Created alias ' + source + ' → ' + destination + '.' );
				return loadAliases();
			} )
			.catch( ( error ) => showNotice( errorMessage( error ), 'error' ) );
	} );

	// Tabs --------------------------------------------------------------------.

	function showTab( name ) {
		root.querySelectorAll( '.nav-tab' ).forEach( ( tab ) => tab.classList.toggle( 'nav-tab-active', tab.dataset.tab === name ) );
		root.querySelectorAll( '.dmbc-mailserver-tab' ).forEach( ( panel ) => {
			panel.hidden = panel.dataset.tab !== name;
		} );
	}

	root.querySelectorAll( '.nav-tab' ).forEach( ( tab ) => {
		tab.addEventListener( 'click', ( event ) => {
			event.preventDefault();
			window.history.replaceState( null, '', '#' + tab.dataset.tab );
			showTab( tab.dataset.tab );
		} );
	} );

	function refreshAll() {
		return Promise.all( [ loadStatus(), loadAccounts(), loadAliases() ] );
	}

	root.querySelector( '[data-action="refresh"]' ).addEventListener( 'click', refreshAll );

	const initialTab = window.location.hash.replace( '#', '' );
	if ( [ 'dashboard', 'accounts', 'aliases' ].includes( initialTab ) ) {
		showTab( initialTab );
	}
	refreshAll();
}() );
