( function ( blocks, element, components, blockEditor, serverSideRender ) {
	const el = element.createElement;
	const InspectorControls = blockEditor.InspectorControls;
	const PanelBody = components.PanelBody;
	const RangeControl = components.RangeControl;
	const ToggleControl = components.ToggleControl;
	const TextControl = components.TextControl;
	const ServerSideRender = serverSideRender;

	blocks.registerBlockType( 'bilingual-post-grid/posts', {
		title: 'Bilingual Post Grid',
		description: 'A responsive, paginated news archive with TranslatePress-aware titles and excerpts.',
		icon: 'grid-view',
		category: 'widgets',
		attributes: {
			columns: { type: 'number', default: 3 },
			rows: { type: 'number', default: 3 },
			excerptWords: { type: 'number', default: 20 },
			showImage: { type: 'boolean', default: true },
			showExcerpt: { type: 'boolean', default: true },
			showReadMore: { type: 'boolean', default: true },
			showCategory: { type: 'boolean', default: true },
			showDate: { type: 'boolean', default: true },
			heading: { type: 'string', default: '' }
		},
		edit: function ( props ) {
			const a = props.attributes;
			const set = props.setAttributes;

			return el(
				element.Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: 'Archive Layout', initialOpen: true },
						el( TextControl, {
							label: 'Archive heading',
							value: a.heading,
							onChange: function ( value ) { set( { heading: value } ); }
						} ),
						el( RangeControl, {
							label: 'Columns',
							value: a.columns,
							onChange: function ( value ) { set( { columns: value } ); },
							min: 1,
							max: 6
						} ),
						el( RangeControl, {
							label: 'Rows per page',
							value: a.rows,
							onChange: function ( value ) { set( { rows: value } ); },
							min: 1,
							max: 9
						} ),
						el( RangeControl, {
							label: 'Excerpt word count',
							value: a.excerptWords,
							onChange: function ( value ) { set( { excerptWords: value } ); },
							min: 1,
							max: 100
						} ),
						el( ToggleControl, {
							label: 'Show featured images',
							checked: a.showImage,
							onChange: function ( value ) { set( { showImage: value } ); }
						} ),
						el( ToggleControl, {
							label: 'Show category',
							checked: a.showCategory,
							onChange: function ( value ) { set( { showCategory: value } ); }
						} ),
						el( ToggleControl, {
							label: 'Show excerpts',
							checked: a.showExcerpt,
							onChange: function ( value ) { set( { showExcerpt: value } ); }
						} ),
						el( ToggleControl, {
							label: 'Show Read More buttons',
							checked: a.showReadMore,
							onChange: function ( value ) { set( { showReadMore: value } ); }
						} ),
						el( ToggleControl, {
							label: 'Show publication date',
							checked: a.showDate,
							onChange: function ( value ) { set( { showDate: value } ); }
						} )
					)
				),
				el(
					'div',
					{ className: props.className },
					el( ServerSideRender, {
						block: 'bilingual-post-grid/posts',
						attributes: a
					} )
				)
			);
		},
		save: function () {
			return null;
		}
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.components,
	window.wp.blockEditor,
	window.wp.serverSideRender
);
