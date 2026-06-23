import { useEffect, useRef } from 'react';

function isEditableTarget(target) {
    if (!target || !(target instanceof HTMLElement)) {
        return false;
    }

    const tag = target.tagName;

    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
        return true;
    }

    return target.isContentEditable;
}

function isScrollable(element) {
    return element.scrollWidth > element.clientWidth + 1
        || element.scrollHeight > element.clientHeight + 1;
}

function visibleArea(element) {
    const rect = element.getBoundingClientRect();
    const top = Math.max(rect.top, 0);
    const bottom = Math.min(rect.bottom, window.innerHeight);
    const left = Math.max(rect.left, 0);
    const right = Math.min(rect.right, window.innerWidth);

    if (bottom <= top || right <= left) {
        return 0;
    }

    return (bottom - top) * (right - left);
}

function pickVisibleTable(tables) {
    let best = null;
    let bestScore = 0;

    tables.forEach((table) => {
        const score = visibleArea(table);

        if (score > bestScore) {
            bestScore = score;
            best = table;
        }
    });

    return best;
}

function scrollElement(element, deltaX, deltaY) {
    const beforeLeft = element.scrollLeft;
    const beforeTop = element.scrollTop;

    element.scrollBy({
        left: deltaX,
        top: deltaY,
        behavior: 'auto',
    });

    return element.scrollLeft !== beforeLeft || element.scrollTop !== beforeTop;
}

function scrollWindow(deltaY) {
    const before = window.scrollY;

    window.scrollBy({
        top: deltaY,
        behavior: 'auto',
    });

    return window.scrollY !== before;
}

function prepareTables(root = document) {
    root.querySelectorAll('.responsive-table').forEach((table) => {
        if (!table.hasAttribute('tabindex')) {
            table.setAttribute('tabindex', '-1');
        }

        table.classList.add('keyboard-scroll-table');
    });
}

export function useTableKeyboardScroll(activeKey = '') {
    const activeTableRef = useRef(null);

    useEffect(() => {
        prepareTables();

        const contentShell = document.querySelector('.content-shell');
        const observer = contentShell
            ? new MutationObserver(() => prepareTables(contentShell))
            : null;

        observer?.observe(contentShell, { childList: true, subtree: true });

        function pickTable() {
            const active = activeTableRef.current;

            if (active && document.contains(active) && visibleArea(active) > 0) {
                return active;
            }

            const scrollableTables = Array.from(document.querySelectorAll('.responsive-table')).filter(isScrollable);
            const scrollableMatch = pickVisibleTable(scrollableTables);

            if (scrollableMatch) {
                return scrollableMatch;
            }

            return pickVisibleTable(Array.from(document.querySelectorAll('.responsive-table')));
        }

        function handlePointerDown(event) {
            const table = event.target.closest('.responsive-table');

            if (table) {
                activeTableRef.current = table;
            }
        }

        function handleKeyDown(event) {
            if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) {
                return;
            }

            if (isEditableTarget(event.target)) {
                return;
            }

            if (event.target.closest('[data-no-table-keys]')) {
                return;
            }

            const table = pickTable();

            if (!table) {
                return;
            }

            const stepX = Math.max(80, Math.round(table.clientWidth * 0.12));
            const stepY = Math.max(48, Math.round(table.clientHeight * 0.15));
            let handled = false;

            if (event.key === 'ArrowLeft') {
                handled = scrollElement(table, -stepX, 0);
            } else if (event.key === 'ArrowRight') {
                handled = scrollElement(table, stepX, 0);
            } else if (event.key === 'ArrowUp') {
                handled = scrollElement(table, 0, -stepY) || scrollWindow(-stepY);
            } else if (event.key === 'ArrowDown') {
                handled = scrollElement(table, 0, stepY) || scrollWindow(stepY);
            }

            if (handled) {
                event.preventDefault();
            }
        }

        document.addEventListener('mousedown', handlePointerDown);
        document.addEventListener('keydown', handleKeyDown);

        return () => {
            observer?.disconnect();
            document.removeEventListener('mousedown', handlePointerDown);
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [activeKey]);
}
