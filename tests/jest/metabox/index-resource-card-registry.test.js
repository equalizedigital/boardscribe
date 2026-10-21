/**
 * window.edbsResourceCard is exposed so a window.edbsMetaBoxControls custom
 * control (e.g. Pro's Location field) can render the same card system every
 * built-in resource-type field uses, instead of hand-copying it.
 */
import '../../../src/js/metabox/index';
import { ResourceCard, ResourceCardEmpty } from '../../../src/js/metabox/resource-card';
import { focusFirstActionable } from '../../../src/js/metabox/resource-utils';

describe( 'window.edbsResourceCard', () => {
	it( 'exposes the exact ResourceCard/ResourceCardEmpty/focusFirstActionable used internally', () => {
		expect( window.edbsResourceCard.ResourceCard ).toBe( ResourceCard );
		expect( window.edbsResourceCard.ResourceCardEmpty ).toBe( ResourceCardEmpty );
		expect( window.edbsResourceCard.focusFirstActionable ).toBe( focusFirstActionable );
	} );
} );
