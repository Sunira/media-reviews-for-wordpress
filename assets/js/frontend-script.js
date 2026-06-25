// Vanilla JavaScript - No jQuery!
document.addEventListener('DOMContentLoaded', function() {
    function markReviewScrollInteraction(scrollZone) {
        if (!scrollZone) {
            return;
        }

        scrollZone.dataset.scrollInteracting = '1';
        window.clearTimeout(scrollZone._scrollInteractionTimer);
        scrollZone._scrollInteractionTimer = window.setTimeout(function() {
            delete scrollZone.dataset.scrollInteracting;
        }, 180);
    }

    // Media type configuration
    const mediaConfig = {
        book: {
            creator: 'Author',
            findButton: 'Find this Book'
        },
        movie: {
            creator: 'Director',
            findButton: 'Find this Movie'
        },
        music: {
            creator: 'Artist',
            findButton: 'Find this Album'
        },
        game: {
            creator: 'Developer',
            findButton: 'Find this Game'
        },
        tv: {
            creator: 'Creator',
            findButton: 'Find this TV Show'
        }
    };
    
    // Initialize each media reviews instance
    document.querySelectorAll('[data-book-reviews-instance], [data-view]').forEach(container => {
        const state = {
            search: {
                text: ''
            },
            filters: {
                mediaType: '',
                category: '',
                status: ''
            },
            rating: {
                threshold: ''
            },
            sort: {
                order: 'date-desc'
            }
        };
        
        function getItems() {
            return container.querySelectorAll('.book-review-item');
        }

        function getVisibleItems() {
            return Array.from(getItems()).filter(card => !card.classList.contains('hidden'));
        }

        function updateResultsSummary(visibleCount) {
            const summaryEl = container.querySelector('.book-reviews-results-summary');
            if (!summaryEl) {
                return;
            }

            const parts = [];
            const countLabel = visibleCount === 1 ? 'result' : 'results';
            let summary = 'Showing ' + visibleCount + ' ' + countLabel;

            if (state.search.text) {
                summary += ' for “‘' + state.search.text + '”';
            }

            if (state.filters.mediaType) {
                const labelMap = {
                    book: 'Books',
                    movie: 'Movies',
                    music: 'Music',
                    game: 'Games',
                    tv: 'TV Shows'
                };
                parts.push(labelMap[state.filters.mediaType] || state.filters.mediaType);
            }

            if (state.filters.category) {
                parts.push(state.filters.category);
            }

            if (state.filters.status) {
                parts.push(state.filters.status.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase()));
            }

            if (state.rating.threshold) {
                parts.push(state.rating.threshold === '5' ? '5★' : state.rating.threshold + '★+');
            }

            summaryEl.textContent = parts.length > 0 ? summary + ' · Filtered by ' + parts.join(', ') : summary;
        }

        function updateNoResultsState(visibleCount) {
            const grid = container.querySelector('.book-reviews-items, .grid');
            if (!grid) {
                return;
            }

            let noResults = grid.querySelector('.no-results-message');

            if (visibleCount === 0) {
                if (!noResults) {
                    noResults = document.createElement('p');
                    noResults.className = 'no-results-message col-span-full text-center py-12 text-stone-400 text-sm font-serif italic';
                    noResults.textContent = 'No items match your filters.';
                    grid.appendChild(noResults);
                } else {
                    noResults.style.display = '';
                }
            } else if (noResults) {
                noResults.style.display = 'none';
            }
        }

        function applyFilters() {
            let visibleCount = 0;

            getItems().forEach(card => {
                const title = card.dataset.title || '';
                const creator = card.dataset.creator || '';
                const category = card.dataset.category || '';
                const status = card.dataset.status || '';
                const rating = parseInt(card.dataset.rating) || 0;
                const mediaType = card.dataset.mediaType || 'book';
                let show = true;

                if (state.search.text && !title.includes(state.search.text) && !creator.includes(state.search.text)) {
                    show = false;
                }

                if (state.filters.mediaType && mediaType !== state.filters.mediaType) {
                    show = false;
                }

                if (state.filters.category) {
                    const categories = category.split(',').map(g => g.trim());
                    if (!categories.includes(state.filters.category)) {
                        show = false;
                    }
                }

                if (state.filters.status && status !== state.filters.status) {
                    show = false;
                }

                if (state.rating.threshold) {
                    const minRating = parseInt(state.rating.threshold, 10);
                    if (rating < minRating) {
                        show = false;
                    }
                }

                if (show) {
                    card.classList.remove('hidden');
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.classList.add('hidden');
                    card.style.display = 'none';
                }
            });

            updateNoResultsState(visibleCount);
            updateResultsSummary(visibleCount);
        }

        function applySort() {
            const grid = container.querySelector('.book-reviews-items, .grid');
            if (!grid) {
                return;
            }

            const cards = Array.from(getItems());

            cards.sort((a, b) => {
                switch(state.sort.order) {
                    case 'date-desc':
                        return (b.dataset.date || '').localeCompare(a.dataset.date || '');
                    case 'date-asc':
                        return (a.dataset.date || '').localeCompare(b.dataset.date || '');
                    case 'title-asc':
                        return (a.dataset.title || '').localeCompare(b.dataset.title || '');
                    case 'title-desc':
                        return (b.dataset.title || '').localeCompare(a.dataset.title || '');
                    case 'rating-desc':
                        return (parseInt(b.dataset.rating) || 0) - (parseInt(a.dataset.rating) || 0);
                    case 'rating-asc':
                        return (parseInt(a.dataset.rating) || 0) - (parseInt(b.dataset.rating) || 0);
                    default:
                        return 0;
                }
            });
            
            // Re-append in sorted order
            cards.forEach(card => grid.appendChild(card));
        }

        function refreshResults() {
            applySort();
            applyFilters();
        }

        const searchInput = container.querySelector('.book-search-input');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                state.search.text = e.target.value.toLowerCase().trim();
                applyFilters();
            });
        }

        const mediaTypeFilter = container.querySelector('.media-type-filter');
        if (mediaTypeFilter) {
            mediaTypeFilter.addEventListener('change', function(e) {
                state.filters.mediaType = e.target.value;
                applyFilters();
            });
        }

        const categoryFilter = container.querySelector('.genre-filter');
        if (categoryFilter) {
            categoryFilter.addEventListener('change', function(e) {
                state.filters.category = e.target.value;
                applyFilters();
            });
        }

        const statusFilter = container.querySelector('.status-filter');
        if (statusFilter) {
            statusFilter.addEventListener('change', function(e) {
                state.filters.status = e.target.value;
                applyFilters();
            });
        }

        const ratingFilter = container.querySelector('.rating-filter');
        if (ratingFilter) {
            ratingFilter.addEventListener('change', function(e) {
                state.rating.threshold = e.target.value;
                applyFilters();
            });
        }

        const sortSelect = container.querySelector('.book-sort');
        if (sortSelect) {
            state.sort.order = sortSelect.value;
            sortSelect.addEventListener('change', function(e) {
                state.sort.order = e.target.value;
                refreshResults();
            });
        }

        refreshResults();
    });
    
    // Modal functionality - works across all instances
    function openModal(instanceId, itemId) {
        if (!window.bookReviewsData || !window.bookReviewsData[instanceId]) {
            return;
        }

        const itemData = window.bookReviewsData[instanceId].find(item => {
            return item.id == itemId;
        });
        
        if (!itemData) {
            return;
        }

        const mediaType = itemData.media_type || 'book';
        const config = mediaConfig[mediaType] || mediaConfig.book;
        
        // Build modal HTML
        let modalHTML = '<div class="flex flex-col md:flex-row">';
        
        // LEFT: Cover with stars
        modalHTML += '<div class="relative w-full md:w-2/5 aspect-[2/3] md:aspect-auto bg-stone-200">';
        
        if (itemData.cover_image_url) {
            modalHTML += `<img src="${itemData.cover_image_url}" alt="Cover of ${itemData.title}" class="h-full w-full object-cover">`;
        } else {
            const mediaIcons = { book: '📚', movie: '🎬', music: '🎵', game: '🎮', tv: '📺' };
            const icon = mediaIcons[mediaType] || '📄';
            modalHTML += `<div class="h-full w-full flex items-center justify-center bg-gradient-to-br from-stone-100 to-stone-200"><span class="text-8xl opacity-30">${icon}</span></div>`;
        }
        
        modalHTML += '<div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent"></div>';
        
        // Stars overlay
        if (itemData.rating > 0) {
            modalHTML += '<div class="absolute bottom-6 left-6 flex items-center gap-1">';
            for (let i = 1; i <= 5; i++) {
                const color = i <= itemData.rating ? 'text-amber-400' : 'text-stone-300';
                modalHTML += `<span class="text-2xl ${color}">★</span>`;
            }
            modalHTML += '</div>';
        }
        
        modalHTML += '</div>'; // End cover
        
        // RIGHT: Details
        modalHTML += '<div class="flex flex-col p-8 md:w-3/5">';
        modalHTML += `<h2 class="text-3xl font-bold text-stone-900 mb-2 font-serif leading-tight">${itemData.title}</h2>`;
        modalHTML += `<p class="text-lg text-stone-600 mb-4 font-medium">${itemData.creator}</p>`;
        
        // Badges
        modalHTML += '<div class="flex items-center gap-2 mb-6">';
        if (itemData.category) {
            const categories = itemData.category.split(',').map(g => g.trim()).filter(g => g);
            categories.forEach(cat => {
                modalHTML += `<span class="inline-block text-sm text-stone-700 bg-stone-100 px-3 py-1 rounded-full font-medium">${cat}</span>`;
            });
        }
        
        const readStatuses = ['finished', 'watched', 'listened', 'completed'];
        if (itemData.status && readStatuses.includes(itemData.status)) {
            const labels = { finished: 'Finished', watched: 'Watched', listened: 'Listened', completed: 'Completed' };
            modalHTML += `<span class="inline-block text-sm px-3 py-1 rounded-full font-medium bg-green-100 text-green-700">${labels[itemData.status]}</span>`;
        }
        modalHTML += '</div>';
        
        // Review
        if (itemData.review_text && itemData.review_text.trim()) {
            modalHTML += '<div class="flex-1 mb-6">';
            modalHTML += '<h3 class="text-sm font-bold text-stone-900 uppercase tracking-wider mb-2">REVIEW</h3>';
            modalHTML += `<p class="text-base text-stone-700 leading-relaxed italic font-serif">"${itemData.review_text.replace(/\n/g, '<br>')}"</p>`;
            modalHTML += '</div>';
        }
        
        // Find Online button
        const searchQuery = encodeURIComponent(`${itemData.title} ${itemData.creator}`);
        modalHTML += `<a href="https://www.google.com/search?q=${searchQuery}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-stone-900 text-white text-sm font-medium rounded-lg hover:bg-stone-800 transition-colors">`;
        modalHTML += '<span>Find Online</span>';
        modalHTML += '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>';
        modalHTML += '</a>';
        
        modalHTML += '</div>'; // End details
        modalHTML += '</div>'; // End flex container
        
        // Update modal and show
        const modal = document.getElementById(`modal-${instanceId}`);
        if (!modal) {
            console.error('Modal not found:', `modal-${instanceId}`);
            return;
        }
        
        const modalBody = modal.querySelector('.book-modal-body');
        modalBody.innerHTML = modalHTML;
        
        // Show modal with inline styles to guarantee visibility
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.style.display = 'flex';
        modal.style.alignItems = 'center';
        modal.style.justifyContent = 'center';
        document.body.style.overflow = 'hidden';
        
    }
    
    function closeModal(instanceId) {
        const modal = document.getElementById(`modal-${instanceId}`);
        if (modal) {
            modal.classList.remove('flex');
            modal.classList.add('hidden');
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }
    
    // Card click handler - toggle flip
    document.addEventListener('click', (e) => {
        const reviewScrollZone = e.target.closest('.book-card-review-scroll');
        if (reviewScrollZone && reviewScrollZone.dataset.scrollInteracting === '1') {
            e.preventDefault();
            e.stopPropagation();
            return;
        }

        const card = e.target.closest('.book-card-container');
        if (card) {
            e.preventDefault();
            card.classList.toggle('flipped');
            return;
        }

        const listItem = e.target.closest('.book-list-item');
        if (listItem && !e.target.closest('a, button, input, select, textarea')) {
            const instanceId = listItem.dataset.instance;
            const itemId = listItem.dataset.itemId;
            openModal(instanceId, itemId);
        }
    });

    document.addEventListener('wheel', (e) => {
        const scrollZone = e.target.closest('.book-card-review-scroll');
        if (!scrollZone) {
            return;
        }

        const canScroll = scrollZone.scrollHeight > scrollZone.clientHeight;
        if (!canScroll) {
            return;
        }

        const maxScrollTop = scrollZone.scrollHeight - scrollZone.clientHeight;
        const nextScrollTop = Math.max(0, Math.min(scrollZone.scrollTop + e.deltaY, maxScrollTop));

        if (nextScrollTop !== scrollZone.scrollTop) {
            scrollZone.scrollTop = nextScrollTop;
            markReviewScrollInteraction(scrollZone);
            e.preventDefault();
        }

        e.stopPropagation();
    }, { passive: false });

    document.addEventListener('pointerdown', (e) => {
        const scrollZone = e.target.closest('.book-card-review-scroll');
        if (!scrollZone) {
            return;
        }

        scrollZone.dataset.pointerStartY = String(e.clientY);
        delete scrollZone.dataset.scrollInteracting;
    });

    document.addEventListener('pointermove', (e) => {
        const scrollZone = e.target.closest('.book-card-review-scroll');
        if (!scrollZone || !scrollZone.dataset.pointerStartY) {
            return;
        }

        const startY = parseFloat(scrollZone.dataset.pointerStartY);
        if (Math.abs(e.clientY - startY) > 6) {
            markReviewScrollInteraction(scrollZone);
        }
    });

    document.addEventListener('pointerup', (e) => {
        const scrollZone = e.target.closest('.book-card-review-scroll');
        if (!scrollZone) {
            return;
        }

        delete scrollZone.dataset.pointerStartY;
    });
    
    // Close button click
    document.addEventListener('click', (e) => {
        if (e.target.closest('.book-modal-close')) {
            e.preventDefault();
            const modal = e.target.closest('.book-modal');
            if (modal) {
                const instanceId = modal.dataset.instance;
                closeModal(instanceId);
            }
        }
    });
    
    // Backdrop click
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('book-modal')) {
            const instanceId = e.target.dataset.instance;
            closeModal(instanceId);
        }
    });
    
    // Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.book-modal.flex').forEach(modal => {
                const instanceId = modal.dataset.instance;
                closeModal(instanceId);
            });
        }
    });
    
    // Make openModal available globally for debugging
    window.openModal = openModal;
});
