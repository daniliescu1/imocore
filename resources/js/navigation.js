import { router } from '@inertiajs/react';

export function cancelPendingRequests() {
    router.cancelAll({ async: true, prefetch: true, sync: true });
    router.flushAll();
}

export function navigateTo(href, options = {}) {
    cancelPendingRequests();

    router.visit(href, {
        preserveScroll: false,
        ...options,
    });
}
