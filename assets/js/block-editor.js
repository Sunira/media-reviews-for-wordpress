(function(wp) {
    const el = wp.element.createElement;
    const useEffect = wp.element.useEffect;
    const useState = wp.element.useState;
    const registerBlockType = wp.blocks.registerBlockType;
    const InspectorControls = wp.blockEditor.InspectorControls;
    const useBlockProps = wp.blockEditor.useBlockProps;
    const ServerSideRender = wp.serverSideRender;
    const PanelBody = wp.components.PanelBody;
    const RadioControl = wp.components.RadioControl;
    const TextControl = wp.components.TextControl;
    const ToggleControl = wp.components.ToggleControl;
    const RangeControl = wp.components.RangeControl;
    const SelectControl = wp.components.SelectControl;
    const CheckboxControl = wp.components.CheckboxControl;
    const Button = wp.components.Button;
    const Spinner = wp.components.Spinner;
    const Placeholder = wp.components.Placeholder;
    const Notice = wp.components.Notice;
    const apiFetch = wp.apiFetch;

    function MediaTypeControls(props) {
        const values = props.value || [];
        const options = [
            { label: 'Books', value: 'book' },
            { label: 'Movies', value: 'movie' },
            { label: 'Music', value: 'music' },
            { label: 'Games', value: 'game' },
            { label: 'TV Shows', value: 'tv' }
        ];

        return el(
            'div',
            { className: 'book-reviews-block-media-types' },
            options.map(function(option) {
                return el(CheckboxControl, {
                    key: option.value,
                    label: option.label,
                    checked: values.indexOf(option.value) !== -1,
                    onChange: function(checked) {
                        const nextValues = checked
                            ? values.concat(option.value)
                            : values.filter(function(value) {
                                return value !== option.value;
                            });

                        props.onChange(nextValues);
                    }
                });
            })
        );
    }

    registerBlockType('media-reviews/display', {
        edit: function(props) {
            const attributes = props.attributes;
            const setAttributes = props.setAttributes;
            const blockProps = useBlockProps();
            const [searchTerm, setSearchTerm] = useState('');
            const [searchResults, setSearchResults] = useState([]);
            const [isSearching, setIsSearching] = useState(false);
            const [searchError, setSearchError] = useState('');

            useEffect(function() {
                if (attributes.mode !== 'single' || searchTerm.trim() === '') {
                    setSearchResults([]);
                    setSearchError('');
                    return;
                }

                const timeoutId = window.setTimeout(function() {
                    setIsSearching(true);
                    setSearchError('');

                    apiFetch({
                        path: '/media-reviews/v1/items?search=' + encodeURIComponent(searchTerm.trim())
                    }).then(function(results) {
                        setSearchResults(Array.isArray(results) ? results : []);
                    }).catch(function(error) {
                        setSearchResults([]);
                        setSearchError(error && error.message ? error.message : 'Search failed.');
                    }).finally(function() {
                        setIsSearching(false);
                    });
                }, 250);

                return function() {
                    window.clearTimeout(timeoutId);
                };
            }, [searchTerm, attributes.mode]);

            return el(
                wp.element.Fragment,
                null,
                el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: 'Display Settings', initialOpen: true },
                        el(RadioControl, {
                            label: 'Mode',
                            selected: attributes.mode,
                            options: [
                                { label: 'Collection', value: 'collection' },
                                { label: 'Single Item', value: 'single' }
                            ],
                            onChange: function(mode) {
                                setAttributes({ mode: mode });
                            }
                        }),
                        el(TextControl, {
                            label: 'Heading',
                            value: attributes.heading || '',
                            onChange: function(value) {
                                setAttributes({ heading: value });
                            }
                        }),
                        attributes.mode === 'collection' && el(SelectControl, {
                            label: 'Layout',
                            value: attributes.layout,
                            options: [
                                { label: 'Grid', value: 'grid' },
                                { label: 'List', value: 'list' }
                            ],
                            onChange: function(value) {
                                setAttributes({ layout: value });
                            }
                        }),
                        attributes.mode === 'collection' && el(ToggleControl, {
                            label: 'Show filters',
                            checked: !!attributes.showFilters,
                            onChange: function(value) {
                                setAttributes({ showFilters: value });
                            }
                        }),
                        attributes.mode === 'collection' && el(MediaTypeControls, {
                            value: attributes.mediaTypes,
                            onChange: function(value) {
                                setAttributes({ mediaTypes: value });
                            }
                        }),
                        attributes.mode === 'collection' && el(SelectControl, {
                            label: 'Completion date range',
                            value: attributes.datePreset,
                            options: [
                                { label: 'All dates', value: 'all' },
                                { label: 'Last 30 days', value: 'last30' },
                                { label: 'Last 90 days', value: 'last90' },
                                { label: 'Last 365 days', value: 'last365' }
                            ],
                            onChange: function(value) {
                                setAttributes({ datePreset: value });
                            }
                        }),
                        attributes.mode === 'collection' && el(RangeControl, {
                            label: 'Maximum items',
                            value: attributes.limit,
                            min: -1,
                            max: 24,
                            help: attributes.limit === -1 ? 'Showing all matching items.' : 'Limit collection to ' + attributes.limit + ' items.',
                            onChange: function(value) {
                                setAttributes({ limit: value });
                            }
                        }),
                        attributes.mode === 'single' && el(TextControl, {
                            label: 'Search saved items',
                            value: searchTerm,
                            onChange: setSearchTerm,
                            placeholder: 'Search by title or creator'
                        }),
                        attributes.mode === 'single' && isSearching && el(Spinner),
                        attributes.mode === 'single' && searchError && el(Notice, { status: 'error', isDismissible: false }, searchError),
                        attributes.mode === 'single' && attributes.itemId > 0 && el(
                            'p',
                            { className: 'book-reviews-block-selected-id' },
                            'Selected item ID: ',
                            String(attributes.itemId)
                        )
                    )
                ),
                el(
                    'div',
                    blockProps,
                    attributes.mode === 'single' && attributes.itemId === 0 && !searchTerm && el(
                        Placeholder,
                        { label: 'Media Reviews' },
                        'Search for a saved media item in the sidebar to display it here.'
                    ),
                    attributes.mode === 'single' && searchTerm && el(
                        'div',
                        { className: 'book-reviews-block-search-results' },
                        searchResults.map(function(result) {
                            return el(
                                Button,
                                {
                                    key: result.id,
                                    className: 'book-reviews-block-search-result',
                                    variant: attributes.itemId === result.id ? 'primary' : 'secondary',
                                    onClick: function() {
                                        setAttributes({ itemId: result.id });
                                    }
                                },
                                result.title + ' · ' + result.creator + ' · ' + result.media_type
                            );
                        }),
                        !isSearching && searchResults.length === 0 && !searchError && el(
                            'p',
                            { className: 'book-reviews-block-no-results' },
                            'No matching saved items found.'
                        )
                    ),
                    el(ServerSideRender, {
                        block: 'media-reviews/display',
                        attributes: attributes
                    })
                )
            );
        },
        save: function() {
            return null;
        }
    });
})(window.wp);
