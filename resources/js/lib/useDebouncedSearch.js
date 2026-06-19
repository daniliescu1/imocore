import { useEffect, useRef, useState } from 'react';

export function useDebouncedSearch(appliedSearch, onApply, delayMs = 350) {
    const [searchDraft, setSearchDraft] = useState(appliedSearch || '');
    const debounceRef = useRef(null);

    useEffect(() => {
        setSearchDraft(appliedSearch || '');
    }, [appliedSearch]);

    useEffect(() => () => {
        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }
    }, []);

    function handleSearchChange(value) {
        setSearchDraft(value);

        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }

        debounceRef.current = setTimeout(() => {
            onApply(value);
        }, delayMs);
    }

    return [searchDraft, handleSearchChange];
}
