document.addEventListener('DOMContentLoaded', () => {
    const searchForm = document.querySelector('form[data-suggestions-url]');
    if (!searchForm) {
        return;
    }

    const searchInput = searchForm.querySelector('#search-query');
    const suggestionsPanel = searchForm.querySelector('#search-suggestions');
    const suggestionsUrl = searchForm.dataset.suggestionsUrl;
    let debounceTimer = null;
    let currentQuery = '';

    const formatUserItem = (user) => {
        return `
            <a href="/users/${user.id}" class="block px-4 py-3 hover:bg-base-200">
                <div class="font-semibold">${user.name}</div>
                <div class="text-sm text-base-content/60">User ID: ${user.id}</div>
            </a>
        `;
    };

    const formatChirpItem = (chirp) => {
        const preview = chirp.message.length > 80 ? `${chirp.message.slice(0, 80)}…` : chirp.message;
        const author = chirp.user ? chirp.user.name : 'Anonymous';

        return `
            <a href="/search?query=${encodeURIComponent(currentQuery)}" class="block px-4 py-3 hover:bg-base-200">
                <div class="font-semibold">${author}</div>
                <div class="text-sm text-base-content/60">${preview}</div>
            </a>
        `;
    };

    const renderSuggestions = ({ users, chirps }) => {
        if ((!users || users.length === 0) && (!chirps || chirps.length === 0)) {
            suggestionsPanel.innerHTML = '<div class="px-4 py-3 text-sm text-base-content/60">No matches found.</div>';
            suggestionsPanel.classList.remove('hidden');
            return;
        }

        let html = '';

        if (users && users.length > 0) {
            html += '<div class="px-4 py-2 text-xs uppercase tracking-wide text-base-content/60">Users</div>';
            html += users.map(formatUserItem).join('');
        }

        if (chirps && chirps.length > 0) {
            html += '<div class="px-4 py-2 text-xs uppercase tracking-wide text-base-content/60">Chirps</div>';
            html += chirps.map(formatChirpItem).join('');
        }

        suggestionsPanel.innerHTML = html;
        suggestionsPanel.classList.remove('hidden');
    };

    const hideSuggestions = () => {
        suggestionsPanel.classList.add('hidden');
    };

    const updateSuggestions = async (query) => {
        currentQuery = query;

        if (!query) {
            hideSuggestions();
            return;
        }

        try {
            const response = await fetch(`${suggestionsUrl}?query=${encodeURIComponent(query)}`);
            if (!response.ok) {
                hideSuggestions();
                return;
            }

            const data = await response.json();
            renderSuggestions(data);
        } catch (error) {
            hideSuggestions();
        }
    };

    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => updateSuggestions(searchInput.value.trim()), 200);
    });

    searchInput.addEventListener('focus', () => {
        if (searchInput.value.trim()) {
            updateSuggestions(searchInput.value.trim());
        }
    });

    document.addEventListener('click', (event) => {
        if (!searchForm.contains(event.target)) {
            hideSuggestions();
        }
    });
});
