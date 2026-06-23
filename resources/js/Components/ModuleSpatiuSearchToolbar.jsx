import React from 'react';
import { router } from '@inertiajs/react';
import { useDebouncedSearch } from '../lib/useDebouncedSearch';

function buildModuleSearchFilters(filters, overrides = {}) {
    return {
        search: filters.search || '',
        localitate: filters.localitate || '',
        search_spatii: filters.search_spatii ? 1 : '',
        ...overrides,
    };
}

export default function ModuleSpatiuSearchToolbar({
    filters,
    localitati = [],
    routePath,
    extraActions = null,
    showBack = false,
}) {
    function updateFilters(overrides = {}) {
        router.get(routePath, buildModuleSearchFilters(filters, overrides), {
            preserveState: true,
            preserveScroll: true,
        });
    }

    const [searchDraft, handleSearchChange] = useDebouncedSearch(filters.search, (value) => {
        updateFilters({
            search: value,
            search_spatii: '',
        });
    });

    return (
        <div className="spaces-topbar-toolbar">
            {showBack ? (
                <button
                    type="button"
                    className="secondary-button topbar-back-button"
                    onClick={() => updateFilters({
                        search: '',
                        localitate: '',
                        search_spatii: '',
                    })}
                >
                    ← Înapoi
                </button>
            ) : null}
            <div className="spaces-topbar-filters">
                <label className="inline-topbar-field spaces-topbar-field">
                    <span>Localitate</span>
                    <select
                        className="filter-input topbar-filter"
                        value={filters.localitate || ''}
                        onChange={(event) => updateFilters({ localitate: event.target.value })}
                    >
                        <option value="">Toate</option>
                        {localitati.map((localitate) => (
                            <option value={localitate} key={localitate}>{localitate}</option>
                        ))}
                    </select>
                </label>
                <input
                    className="filter-input topbar-search"
                    type="search"
                    value={searchDraft}
                    placeholder="Caută spațiu sau imobil..."
                    onChange={(event) => handleSearchChange(event.target.value)}
                />
            </div>
            {extraActions}
        </div>
    );
}

export { buildModuleSearchFilters };
