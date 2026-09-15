import { Button } from '@wordpress/components';

/**
 * One resource-card action link/button (View, Edit, Replace, Remove...).
 *
 * @param {Object}   props           Component props.
 * @param {string}   props.label     Action label.
 * @param {Function} [props.onClick] Click handler - omit for a plain link (props.href).
 * @param {string}   [props.href]    Link target for a plain View-style link.
 * @param {boolean}  [props.danger]  True for a destructive action (Remove).
 * @return {JSX.Element} The action.
 */
function CardAction( { label, onClick, href, danger } ) {
	const className = danger ? 'edbs-resource-card__action edbs-resource-card__action--danger' : 'edbs-resource-card__action';

	if ( href ) {
		return (
			<a className={ className } href={ href } target="_blank" rel="noopener noreferrer">
				{ label }
			</a>
		);
	}

	return (
		<Button className={ className } variant="link" onClick={ onClick }>
			{ label }
		</Button>
	);
}

/**
 * A filled resource card: title, source/status chips, a metadata line,
 * optional extra content (e.g. a caption-track list), and an action row.
 * Shared by every meeting-resource field (Agenda, Minutes, Location,
 * Livestream, Recording, CC Transcript, Supporting Documents rows) so they
 * all read as one visual system rather than one-off field markup.
 *
 * @param {Object}                                                                      props              Component props.
 * @param {string}                                                                      props.title        The resource's display title.
 * @param {Array<string>}                                                               props.chips        Small status/source badges, e.g. ["External URL"].
 * @param {string}                                                                      [props.meta]       Secondary line under the chips (filename, URL, address).
 * @param {import('react').ReactNode}                                                   [props.children]   Extra content under the meta line.
 * @param {Array<{label: string, onClick?: Function, href?: string, danger?: boolean}>} props.actions      Action row.
 * @param {import('react').ReactNode}                                                   [props.dragHandle] Optional drag handle rendered at the card's edge (repeater rows).
 * @return {JSX.Element} The card.
 */
export function ResourceCard( { title, chips, meta, children, actions, dragHandle } ) {
	return (
		<div className="edbs-resource-card">
			{ dragHandle }
			<div className="edbs-resource-card__body">
				<div className="edbs-resource-card__title">{ title }</div>
				{ !! ( chips && chips.length ) && (
					<div className="edbs-resource-card__chips">
						{ chips.map( ( chip ) => (
							<span className="edbs-resource-card__chip" key={ chip }>
								{ chip }
							</span>
						) ) }
						{ meta && <span className="edbs-resource-card__meta">{ meta }</span> }
					</div>
				) }
				{ children }
				<div className="edbs-resource-card__actions">
					{ actions.map( ( action ) => (
						<CardAction key={ action.label } { ...action } />
					) ) }
				</div>
			</div>
		</div>
	);
}

/**
 * The empty-state alternative to ResourceCard: a dashed box with a short
 * message and a single primary "Add" action.
 *
 * @param {Object}   props             Component props.
 * @param {string}   props.message     e.g. "No minutes attached."
 * @param {string}   props.actionLabel e.g. "Add minutes".
 * @param {Function} props.onAdd       Called when the add action is activated.
 * @return {JSX.Element} The empty state.
 */
export function ResourceCardEmpty( { message, actionLabel, onAdd } ) {
	return (
		<div className="edbs-resource-card edbs-resource-card--empty">
			<span className="edbs-resource-card__empty-message">{ message }</span>
			<Button variant="secondary" onClick={ onAdd }>
				{ actionLabel }
			</Button>
		</div>
	);
}
