// After selecting a contact in the select2 field, add it to the list of contacts in the preceding checkbox field
const initContactLookup = () => {
	if ( typeof acf === 'undefined' ) return;

	acf.addAction( 'select2_init', function ( $select, args, settings, field ) {
		if ( field?.data?.key !== 'field_67c9c9964066d' ) return;
		
		const addNewChoices = ( select2data ) => {
			const labels = ul.querySelectorAll( 'label' );
			const labelArray = Array.from( labels );

			select2data.forEach( datum => {
				let label = labelArray.find( label => label.innerText.trim() === datum.text );
				if ( !label ) {
					const li = document.createElement( 'li' );
					const label = document.createElement( 'label' );
					const input = document.createElement( 'input' );
					input.type = 'checkbox';
					input.name = 'acf[field_67c9c95083464][field_67ec3cc67b877][]';
					input.value = JSON.stringify( [datum.id, datum.text] );
					input.checked = true;

					label.appendChild( input );
					label.appendChild( document.createTextNode( ' ' + datum.text ) );

					li.appendChild( label );
					ul.appendChild( li );
				} else {
					label.querySelector( 'input' ).checked = true;
				}
			} );
		}

		const ul = document.querySelector( '.acf-field-67ec3cc67b877 .acf-checkbox-list' );

		$select.on( 'change', () => {
			const select2data = $select.select2( 'data' );
			addNewChoices( select2data );
		} );
	} );
}

if ( document.readyState === 'interactive' || document.readyState === 'complete' ) {
	initContactLookup();
} else {
	document.addEventListener( 'DOMContentLoaded', initContactLookup );
}
