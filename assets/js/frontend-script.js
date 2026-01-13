// Vanilla JavaScript - No jQuery!
document.addEventListener('DOMContentLoaded', function() {
    console.log('📚 Book Reviews Plugin - Vanilla JS Loaded');
    console.log('Total containers:', document.querySelectorAll('[data-view]').length);
    console.log('Total cards:', document.querySelectorAll('.book-card').length);
    
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
        }
    };
    
    // Initialize each book reviews instance
    document.querySelectorAll('[data-view]').forEach(container => {
        const instanceId = container.id;
        
        // Get all cards for this instance
        function getCards() {
            return container.querySelectorAll('.book-card');
        }
        
        // Filter books based on current filter values
        function filterBooks() {
            const searchInput = container.querySelector('.book-search-input');
            const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
            const mediaTypeFilter = container.querySelector('.media-type-filter')?.value || '';
            const genreFilter = container.querySelector('.genre-filter')?.value || '';
            const statusFilter = container.querySelector('.status-filter')?.value || '';
            const ratingFilter = container.querySelector('.rating-filter')?.value || '';
            
            let visibleCount = 0;
            
            getCards().forEach(card => {
                const title = card.dataset.title || '';
                const creator = card.dataset.creator || '';
                const category = card.dataset.category || '';
                const status = card.dataset.status || '';
                const rating = parseInt(card.dataset.rating) || 0;
                const mediaType = card.dataset.mediaType || 'book';
                
                let show = true;
                
                // Search filter
                if (searchTerm && !title.includes(searchTerm) && !creator.includes(searchTerm)) {
                    show = false;
                }
                
                // Media type filter
                if (mediaTypeFilter && mediaType !== mediaTypeFilter) {
                    show = false;
                }
                
                // Genre filter (handle comma-separated)
                if (genreFilter) {
                    const categories = category.split(',').map(g => g.trim());
                    if (!categories.includes(genreFilter)) {
                        show = false;
                    }
                }
                
                // Status filter
                if (statusFilter && status !== statusFilter) {
                    show = false;
                }
                
                // Rating filter
                if (ratingFilter) {
                    const minRating = parseInt(ratingFilter);
                    if (rating < minRating) {
                        show = false;
                    }
                }
                
                // Show/hide card
                if (show) {
                    card.classList.remove('hidden');
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.classList.add('hidden');
                    card.style.display = 'none';
                }
            });
            
            // Show/hide no results message
            const grid = container.querySelector('.grid');
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
        
        // Sort books
        function sortBooks(sortBy) {
            const grid = container.querySelector('.grid');
            const cards = Array.from(getCards());
            
            cards.sort((a, b) => {
                switch(sortBy) {
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
        
        // Event listeners
        const searchInput = container.querySelector('.book-search-input');
        if (searchInput) {
            searchInput.addEventListener('input', filterBooks);
        }
        
        const filters = container.querySelectorAll('.genre-filter, .status-filter, .rating-filter, .media-type-filter');
        filters.forEach(filter => {
            filter.addEventListener('change', filterBooks);
        });
        
        const sortSelect = container.querySelector('.book-sort');
        if (sortSelect) {
            sortSelect.addEventListener('change', (e) => sortBooks(e.target.value));
        }
    });
    
    // Modal functionality - works across all instances
    function openModal(instanceId, itemId) {
        console.log('openModal called:', { instanceId, itemId });
        
        if (!window.bookReviewsData || !window.bookReviewsData[instanceId]) {
            console.error('No data found for instance:', instanceId);
            return;
        }
        
        console.log('Searching for item ID:', itemId, 'Type:', typeof itemId);
        console.log('Available items:', window.bookReviewsData[instanceId].map(item => ({
            id: item.id,
            idType: typeof item.id,
            title: item.title
        })));
        
        const itemData = window.bookReviewsData[instanceId].find(item => {
            console.log('Comparing:', item.id, '('+typeof item.id+')', '==', itemId, '('+typeof itemId+')', '=', item.id == itemId);
            return item.id == itemId;
        });
        
        if (!itemData) {
            console.error('❌ Item data not found for id:', itemId);
            console.error('Available IDs:', window.bookReviewsData[instanceId].map(i => i.id));
            return;
        }
        
        console.log('✅ Item data found:', itemData);
        
        const mediaType = itemData.media_type || 'book';
        const config = mediaConfig[mediaType] || mediaConfig.book;
        
        // Build modal HTML
        let modalHTML = '<div class="flex flex-col md:flex-row">';
        
        // LEFT: Cover with stars
        modalHTML += '<div class="relative w-full md:w-2/5 aspect-[2/3] md:aspect-auto bg-stone-200">';
        
        if (itemData.cover_image_url) {
            modalHTML += `<img src="${itemData.cover_image_url}" alt="Cover of ${itemData.title}" class="h-full w-full object-cover">`;
        } else {
            const mediaIcons = { book: '📚', movie: '🎬', music: '🎵', game: '🎮' };
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
            const labels = { finished: 'Read', watched: 'Watched', listened: 'Listened', completed: 'Completed' };
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
        
        console.log('✅ Modal should now be visible');
        console.log('Modal display:', window.getComputedStyle(modal).display);
        console.log('Modal position:', window.getComputedStyle(modal).position);
    }
    
    function closeModal(instanceId) {
        console.log('Closing modal:', instanceId);
        const modal = document.getElementById(`modal-${instanceId}`);
        if (modal) {
            modal.classList.remove('flex');
            modal.classList.add('hidden');
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }
    
    // Card click handler - using event delegation
    document.addEventListener('click', (e) => {
        const card = e.target.closest('.book-card');
        if (card) {
            e.preventDefault();
            
            // Read data attributes (try both ways for debugging)
            const instanceId = card.dataset.instance || card.getAttribute('data-instance');
            const itemId = card.dataset.itemId || card.getAttribute('data-item-id');
            
            console.log('=== Card Clicked ===');
            console.log('Card element:', card);
            console.log('Card ID:', card.id);
            console.log('Dataset:', card.dataset);
            console.log('Instance ID:', instanceId);
            console.log('Item ID:', itemId);
            console.log('Has bookReviewsData:', !!window.bookReviewsData);
            
            if (window.bookReviewsData) {
                console.log('Available instances:', Object.keys(window.bookReviewsData));
                if (window.bookReviewsData[instanceId]) {
                    console.log('Items in this instance:', window.bookReviewsData[instanceId].length);
                }
            }
            
            if (instanceId && itemId) {
                openModal(instanceId, itemId);
            } else {
                console.error('❌ Missing required data:', { 
                    instanceId, 
                    itemId,
                    hasInstance: !!instanceId,
                    hasItemId: !!itemId
                });
            }
        }
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
