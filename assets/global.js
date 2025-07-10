(function() {
	console.log('global.js');

	const init = function() {

		console.log('init');
		setup_collapsibles();

	};

	/*
	Collapsible menu with link:
	Link:
	- Class: bounds-collapsible-toggle
	- Href: #your_target_id

	Target:
	- ID: your_target_id
	- Class: bounds-collapsible
	- Class (When Collapsed): collapsed
	- Class (When Expanded): expanded
	 */
	const setup_collapsibles = function() {
		console.log('setup_collapsibles');
		document.body.addEventListener('click', function(e) {
			console.log('Clicked', e.target);
			// Check if clicking a link with the class "bounds-collapsible-toggle"
			if (e.target.matches('.bounds-collapsible-toggle')) {
				console.log('Clicked a toggle!');
				e.preventDefault(); // Prevent default link behavior
				const targetId = e.target.getAttribute('href').substring(1); // Get the target ID from href
				const targetElement = document.getElementById(targetId); // Get the target element by ID
				console.log('Target ID:', targetId);
				console.log('Target Element:', targetElement);

				if (targetElement) {
					// Toggle classes on the target element
					targetElement.classList.toggle('collapsed');
					targetElement.classList.toggle('expanded');
				}
			}
		});
	};

	// on dom ready, init
	document.addEventListener( 'DOMContentLoaded', init );

})();