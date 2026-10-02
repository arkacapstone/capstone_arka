import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

/**
 * Query-string filters for an index page, with a debounced search box.
 * Returns the live search text, its setter, and `apply({...})` for other filters.
 */
export default function useFilters(target, filters) {
    const [search, setSearch] = useState(filters.search ?? '');
    const firstRender = useRef(true);

    // `target` is a route name, or [routeName, params] for routes with parameters.
    const url = Array.isArray(target) ? route(...target) : route(target);

    const apply = (next) =>
        router.get(
            url,
            Object.fromEntries(Object.entries({ ...filters, ...next, page: undefined }).filter(([, value]) => value !== '' && value !== undefined)),
            { preserveState: true, preserveScroll: true, replace: true },
        );

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return undefined;
        }

        const timer = setTimeout(() => apply({ search }), 300);

        return () => clearTimeout(timer);
    }, [search]); // eslint-disable-line react-hooks/exhaustive-deps

    return { search, setSearch, apply };
}

export function SearchInput({ value, onChange, placeholder, label }) {
    return (
        <input
            type="search"
            value={value}
            onChange={(e) => onChange(e.target.value)}
            placeholder={placeholder}
            aria-label={label}
            className="w-full max-w-sm rounded-none border border-console-line bg-console-panel px-3 py-2 text-[13px] text-console-text placeholder:text-console-dim transition-colors hover:border-arka-teal focus:border-arka-teal focus:ring-0"
        />
    );
}
