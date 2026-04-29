(function(blocks, element, blockEditor, components) {
    var el = element.createElement;
    var useBlockProps = blockEditor.useBlockProps;
    var InspectorControls = blockEditor.InspectorControls;
    var MediaUpload = blockEditor.MediaUpload;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var TextareaControl = components.TextareaControl;
    var SelectControl = components.SelectControl;
    var ColorPicker = components.ColorPicker;
    var Button = components.Button;

    var defs  = window.cascadeBlocksDefaults || {};
    var pcDef = defs.pageCards || {
        cardStyle: 'rounded', columns: 2, bgColor: '#f0f0f0', textColor: '#333333',
        iconType: 'mdi', icon: 'chevron-right', subtitleSource: 'excerpt',
        cardCount: 2, cptIconField: 'cpt_icon', cptIconTypeField: 'cpt_icon_type',
        cptSubtitleSource: 'excerpt'
    };
    var cptOptions     = window.cascadePublicCpts || [];
    var cptSelectOpts  = [ { label: '— Select a post type —', value: '' } ].concat(cptOptions);

    // ── Shared helpers ──────────────────────────────────────────────────────────

    function exportBlockSettings(blockType, attributes) {
        var payload = JSON.stringify({ blockType: blockType, attributes: attributes }, null, 2);
        var blob = new Blob([payload], { type: 'application/json' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'cascade-page-cards-' + new Date().toISOString().slice(0, 10) + '.json';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    function toolsPanel(attributes) {
        return el(PanelBody, { title: 'Tools', initialOpen: false },
            el(Button, {
                variant: 'secondary',
                onClick: function() { exportBlockSettings('cascade/page-cards', attributes); }
            }, 'Export Settings')
        );
    }

    function withReset(control, isAtDefault, onReset) {
        return el('div', {},
            control,
            !isAtDefault ? el(Button, {
                onClick: onReset,
                variant: 'link',
                style: { fontSize: '11px', padding: '0 0 6px', display: 'block' }
            }, '↺ Reset to default') : null
        );
    }

    function iconInputForType(iconType, iconValue, iconSvgValue, onChangeIcon, onChangeSvg) {
        if (iconType === 'svg') {
            return el('div', {},
                el(MediaUpload, {
                    onSelect: function(media) { onChangeSvg(media.url); },
                    allowedTypes: ['image'],
                    render: function(obj) {
                        return el(Button, { onClick: obj.open, variant: 'secondary' },
                            iconSvgValue ? 'Change Image' : 'Select Image'
                        );
                    }
                }),
                iconSvgValue ? el('div', { style: { marginTop: '8px' } },
                    el('img', { src: iconSvgValue, style: { width: '32px', height: '32px', objectFit: 'contain', display: 'block', marginBottom: '4px' } }),
                    el(Button, {
                        onClick: function() { onChangeSvg(''); },
                        variant: 'link',
                        isDestructive: true,
                        style: { fontSize: '11px' }
                    }, 'Remove')
                ) : null
            );
        }
        var iconLabel, iconHelp;
        if (iconType === 'fa') {
            iconLabel = 'Font Awesome Class';
            iconHelp  = 'Full class string — e.g. fa-solid fa-file, fa-regular fa-envelope';
        } else if (iconType === 'dashicons') {
            iconLabel = 'Dashicon Name';
            iconHelp  = 'Name without "dashicons-" prefix — e.g. admin-home, format-image';
        } else {
            iconLabel = 'MDI Icon Name';
            iconHelp  = 'Slug from pictogrammers.com/library/mdi — e.g. file-document-outline, home';
        }
        return el(TextControl, { label: iconLabel, help: iconHelp, value: iconValue, onChange: onChangeIcon });
    }

    var iconTypeOptions = [
        { label: 'Material Design Icons', value: 'mdi' },
        { label: 'Font Awesome',          value: 'fa' },
        { label: 'Dashicons (WordPress)', value: 'dashicons' },
        { label: 'Custom Image / SVG',    value: 'svg' }
    ];

    function colorPanelBody(cardStyle, bgColor, textColor, onBgChange, onTextChange) {
        return el(PanelBody, { title: 'Colors', initialOpen: false },
            el('p', {}, cardStyle === 'guide' ? 'Accent Color (left border, icon, title)' : 'Background Color'),
            withReset(
                el(ColorPicker, { color: bgColor, onChange: onBgChange }),
                bgColor === pcDef.bgColor,
                function() { onBgChange(pcDef.bgColor); }
            ),
            el('p', {}, cardStyle === 'guide' ? 'Subtitle Text Color' : 'Text Color'),
            withReset(
                el(ColorPicker, { color: textColor, onChange: onTextChange }),
                textColor === pcDef.textColor,
                function() { onTextChange(pcDef.textColor); }
            )
        );
    }

    // ── Block: Page Cards ────────────────────────────────────────────────────────

    blocks.registerBlockType('cascade/page-cards', {
        title: 'Page Cards',
        icon: 'grid-view',
        category: 'widgets',
        edit: function(props) {
            var attributes   = props.attributes;
            var setAttributes = props.setAttributes;
            var source        = attributes.source;

            // ── Custom Entries card editors ──
            function getCards() {
                var c = Array.isArray(attributes.cards) ? attributes.cards.slice() : [];
                while (c.length < attributes.cardCount) {
                    c.push({ title: '', description: '', link: '', icon: '', iconSvg: '', iconType: 'mdi' });
                }
                return c.slice(0, attributes.cardCount);
            }

            function updateCard(index, field, value) {
                var c = getCards();
                var patch = {};
                patch[field] = value;
                c[index] = Object.assign({}, c[index], patch);
                setAttributes({ cards: c });
            }

            var cardEditors = source === 'custom' ? getCards().map(function(card, index) {
                var cardIconType = card.iconType || 'mdi';
                return el('div', {
                    key: index,
                    style: { border: '1px solid #ddd', borderRadius: '4px', padding: '12px', marginBottom: '10px', background: '#fafafa' }
                },
                    el('strong', { style: { display: 'block', marginBottom: '8px', fontSize: '13px', color: '#1e1e1e' } },
                        'Card ' + (index + 1)
                    ),
                    el(TextControl,     { label: 'Title',       value: card.title       || '', onChange: function(v) { updateCard(index, 'title',       v); } }),
                    el(TextareaControl, { label: 'Description', value: card.description || '', rows: 2, onChange: function(v) { updateCard(index, 'description', v); } }),
                    el(TextControl,     { label: 'Link URL',    value: card.link        || '', type: 'url', onChange: function(v) { updateCard(index, 'link', v); } }),
                    el(SelectControl,   { label: 'Icon Type',   value: cardIconType, options: iconTypeOptions, onChange: function(v) { updateCard(index, 'iconType', v); } }),
                    iconInputForType(
                        cardIconType, card.icon || '', card.iconSvg || '',
                        function(v) { updateCard(index, 'icon',    v); },
                        function(v) { updateCard(index, 'iconSvg', v); }
                    )
                );
            }) : null;

            // ── Inspector ──
            var inspector = el(InspectorControls, {},
                el(PanelBody, { title: 'Settings', initialOpen: true },

                    // Source
                    withReset(
                        el(SelectControl, {
                            label: 'Source',
                            value: source,
                            options: [
                                { label: 'Child Pages',       value: 'child-pages' },
                                { label: 'Custom Entries',    value: 'custom' },
                                { label: 'Custom Post Type',  value: 'cpt' }
                            ],
                            onChange: function(val) { setAttributes({ source: val }); }
                        }),
                        source === 'child-pages',
                        function() { setAttributes({ source: 'child-pages' }); }
                    ),

                    // Shared
                    withReset(
                        el(SelectControl, {
                            label: 'Card Style',
                            value: attributes.cardStyle,
                            options: [ { label: 'Rounded', value: 'rounded' }, { label: 'Flat', value: 'flat' }, { label: 'Guide', value: 'guide' } ],
                            onChange: function(val) { setAttributes({ cardStyle: val }); }
                        }),
                        attributes.cardStyle === pcDef.cardStyle,
                        function() { setAttributes({ cardStyle: pcDef.cardStyle }); }
                    ),
                    withReset(
                        el(SelectControl, {
                            label: 'Desktop Columns',
                            value: String(attributes.columns),
                            options: [ { label: '1', value: '1' }, { label: '2', value: '2' }, { label: '3', value: '3' }, { label: '4', value: '4' } ],
                            onChange: function(val) { setAttributes({ columns: parseInt(val, 10) }); }
                        }),
                        attributes.columns === pcDef.columns,
                        function() { setAttributes({ columns: pcDef.columns }); }
                    ),

                    // Child Pages settings
                    source === 'child-pages' ? el('div', {},
                        withReset(
                            el(SelectControl, {
                                label: 'Icon Type',
                                value: attributes.iconType,
                                options: iconTypeOptions,
                                onChange: function(val) { setAttributes({ iconType: val }); }
                            }),
                            attributes.iconType === pcDef.iconType,
                            function() { setAttributes({ iconType: pcDef.iconType }); }
                        ),
                        attributes.iconType !== 'svg'
                            ? withReset(
                                iconInputForType(attributes.iconType, attributes.icon, attributes.iconSvg,
                                    function(v) { setAttributes({ icon: v }); },
                                    function(v) { setAttributes({ iconSvg: v }); }
                                ),
                                attributes.icon === pcDef.icon,
                                function() { setAttributes({ icon: pcDef.icon }); }
                              )
                            : iconInputForType(attributes.iconType, attributes.icon, attributes.iconSvg,
                                function(v) { setAttributes({ icon: v }); },
                                function(v) { setAttributes({ iconSvg: v }); }
                              ),
                        withReset(
                            el(SelectControl, {
                                label: 'Subtitle Source',
                                value: attributes.subtitleSource,
                                options: [
                                    { label: 'Excerpt',                          value: 'excerpt' },
                                    { label: 'Custom Field: page_description',   value: 'page_description' },
                                    { label: 'None',                             value: 'none' }
                                ],
                                onChange: function(val) { setAttributes({ subtitleSource: val }); }
                            }),
                            attributes.subtitleSource === pcDef.subtitleSource,
                            function() { setAttributes({ subtitleSource: pcDef.subtitleSource }); }
                        )
                    ) : null,

                    // Custom Entries settings
                    source === 'custom' ? withReset(
                        el(SelectControl, {
                            label: 'Number of Cards',
                            value: String(attributes.cardCount),
                            options: [1,2,3,4,5,6,7,8,9,10,11,12].map(function(n) { return { label: String(n), value: String(n) }; }),
                            onChange: function(val) { setAttributes({ cardCount: parseInt(val, 10) }); }
                        }),
                        attributes.cardCount === pcDef.cardCount,
                        function() { setAttributes({ cardCount: pcDef.cardCount }); }
                    ) : null,

                    // CPT settings
                    source === 'cpt' ? el('div', {},
                        el(SelectControl, {
                            label: 'Post Type',
                            value: attributes.postType,
                            options: cptSelectOpts,
                            onChange: function(val) { setAttributes({ postType: val }); }
                        }),
                        withReset(
                            el(TextControl, {
                                label: 'Icon Field',
                                help: 'Meta key that stores the icon name.',
                                value: attributes.cptIconField,
                                onChange: function(val) { setAttributes({ cptIconField: val }); }
                            }),
                            attributes.cptIconField === pcDef.cptIconField,
                            function() { setAttributes({ cptIconField: pcDef.cptIconField }); }
                        ),
                        withReset(
                            el(TextControl, {
                                label: 'Icon Type Field',
                                help: 'Meta key that stores the icon type (mdi, fa, etc.). Leave blank to assume MDI.',
                                value: attributes.cptIconTypeField,
                                onChange: function(val) { setAttributes({ cptIconTypeField: val }); }
                            }),
                            attributes.cptIconTypeField === pcDef.cptIconTypeField,
                            function() { setAttributes({ cptIconTypeField: pcDef.cptIconTypeField }); }
                        ),
                        withReset(
                            el(SelectControl, {
                                label: 'Subtitle Source',
                                value: attributes.cptSubtitleSource,
                                options: [
                                    { label: 'Post Excerpt',  value: 'excerpt' },
                                    { label: 'Custom Field',  value: 'custom' },
                                    { label: 'None',          value: 'none' }
                                ],
                                onChange: function(val) { setAttributes({ cptSubtitleSource: val }); }
                            }),
                            attributes.cptSubtitleSource === pcDef.cptSubtitleSource,
                            function() { setAttributes({ cptSubtitleSource: pcDef.cptSubtitleSource }); }
                        ),
                        attributes.cptSubtitleSource === 'custom' ? el(TextControl, {
                            label: 'Subtitle Field',
                            help: 'Meta key for the subtitle text.',
                            value: attributes.cptSubtitleField,
                            onChange: function(val) { setAttributes({ cptSubtitleField: val }); }
                        }) : null
                    ) : null
                ),

                colorPanelBody(
                    attributes.cardStyle, attributes.bgColor, attributes.textColor,
                    function(val) { setAttributes({ bgColor: val }); },
                    function(val) { setAttributes({ textColor: val }); }
                ),
                toolsPanel(attributes)
            );

            // ── Canvas ──
            var canvas;
            if (source === 'custom') {
                canvas = el('div', { style: { padding: '16px' } },
                    el('p', { style: { fontWeight: '600', marginBottom: '12px' } },
                        'Page Cards — Custom Entries — ' + attributes.cardCount + ' card' + (attributes.cardCount !== 1 ? 's' : '')
                    ),
                    cardEditors
                );
            } else if (source === 'cpt') {
                var cptLabel = attributes.postType
                    ? (cptOptions.filter(function(o) { return o.value === attributes.postType; })[0] || { label: attributes.postType }).label
                    : null;
                canvas = el('div', { style: { padding: '20px', border: '1px dashed #ccc', textAlign: 'center' } },
                    cptLabel
                        ? 'Page Cards — ' + cptLabel + ' — preview renders on the frontend.'
                        : 'Page Cards (Custom Post Type) — select a post type in the Settings panel.'
                );
            } else {
                canvas = el('div', { style: { padding: '20px', border: '1px dashed #ccc', textAlign: 'center' } },
                    'Page Cards — Child Pages — ' + ({ rounded: 'Rounded', flat: 'Flat', guide: 'Guide' }[attributes.cardStyle] || attributes.cardStyle) + ' style — preview renders on the frontend.'
                );
            }

            return el('div', useBlockProps(), inspector, canvas);
        },
        save: function() { return null; }
    });

})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components);
