import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * One resource-card action link/button (View, Edit, Replace, Remove...).
 *
 * @param {Object}   props             Component props.
 * @param {string}   props.label       Action label.
 * @param {string}   [props.ariaLabel] Accessible name, overriding `label` - a resource-shaped
 *                                     field's page often has several cards whose actions all read
 *                                     "View"/"Replace"/"Remove" identically, which a screen
 *                                     reader's controls list can't tell apart; callers pass
 *                                     something like "Replace Agenda" here instead. Falls back to
 *                                     `label` when omitted.
 * @param {Function} [props.onClick]   Click handler - omit for a plain link (props.href).
 * @param {string}   [props.href]      Link target for a plain View-style link.
 * @param {boolean}  [props.danger]    True for a destructive action (Remove).
 * @param {boolean}  [props.disabled]  Disables an onClick-style action (ignored for an href one).
 * @param {string}   [props.id]        DOM id, e.g. so a caller can move focus back to this action.
 * @return {JSX.Element} The action.
 */
function CardAction( { label, ariaLabel, onClick, href, danger, disabled, id } ) {
	const className = danger ? 'edbs-resource-card__action edbs-resource-card__action--danger' : 'edbs-resource-card__action';

	if ( href ) {
		// A link that opens a new tab needs both a visual cue and an
		// accessible one (WCAG 3.2.5 / G201): an icon after the text, and
		// "(opens in a new tab)" in the accessible name. Baked in here so
		// every href action - View, Edit, and any consumer's - inherits it.
		const newTab = __( '(opens in a new tab)', 'boardscribe' );
		return (
			<a
				id={ id }
				className={ className }
				href={ href }
				target="_blank"
				rel="noopener noreferrer"
				aria-label={ ariaLabel ? `${ ariaLabel } ${ newTab }` : undefined }
			>
				{ label }
				{ ! ariaLabel && <span className="screen-reader-text">{ ` ${ newTab }` }</span> }
				<svg className="edbs-resource-card__action-icon" viewBox="0 0 20 20" width="12" height="12" aria-hidden="true" focusable="false">
					<path d="M9 3v2H5v10h10v-4h2v6H3V3h6zm4 0h4v4h-2V6.4l-5.3 5.3-1.4-1.4L13.6 5H13V3z" fill="currentColor" />
				</svg>
			</a>
		);
	}

	return (
		<Button id={ id } className={ className } variant="link" onClick={ onClick } disabled={ disabled } aria-label={ ariaLabel || undefined }>
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
 * @param {Object}                                                                                                                           props              Component props.
 * @param {string}                                                                                                                           props.title        The resource's display title.
 * @param {Array<string>}                                                                                                                    props.chips        Small status/source badges, e.g. ["External URL"].
 * @param {string}                                                                                                                           [props.meta]       Secondary line under the chips (filename, URL, address).
 * @param {import('react').ReactNode}                                                                                                        [props.children]   Extra content under the meta line.
 * @param {Array<{label: string, ariaLabel?: string, onClick?: Function, href?: string, danger?: boolean, disabled?: boolean, id?: string}>} props.actions      Action row - see CardAction's `ariaLabel` doc for why callers should pass it.
 * @param {import('react').ReactNode}                                                                                                        [props.dragHandle] Optional drag handle rendered at the card's edge (repeater rows).
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
					</div>
				) }
				{ meta && <div className="edbs-resource-card__meta">{ meta }</div> }
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
