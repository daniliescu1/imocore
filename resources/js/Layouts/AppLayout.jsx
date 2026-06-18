import React, { useEffect, useRef, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Bell,
    Building2,
    ChevronDown,
    ExternalLink,
    FileText,
    HardDriveUpload,
    Menu,
    Users,
    ReceiptText,
    Search,
    Settings,
    WalletCards,
    X,
    Zap,
} from 'lucide-react';

const navigation = [
    { label: 'Imobile', icon: Building2, href: '/imobile' },
    { label: 'Spații', icon: BarChart3, href: '/spatii' },
    { label: 'Locatori', icon: Users, href: '/locatori' },
    { label: 'Contracte', icon: FileText, href: '/contracte' },
    { label: 'Indexare chirii', icon: BarChart3, href: '/indexare-chirii' },
    { label: 'Utilități', icon: Zap, href: '/utilitati' },
    { label: 'Citiri contoare', icon: BarChart3, href: '/citiri-contoare' },
    { label: 'Configurare anexă', icon: ReceiptText, href: '/configurare-anexa' },
    { label: 'Generare anexe', icon: ReceiptText, href: '/anexe' },
    { label: 'Facturare', icon: WalletCards, href: '/facturare' },
    { label: 'Contabilitate primară', icon: FileText, href: '/contabilitate-primara' },
    { label: 'Cheltuieli', icon: WalletCards, href: '/cheltuieli' },
    { label: 'Reguli imobile', icon: Settings, href: '/reguli-imobile' },
];

const prefetchCacheFor = '2m';
const prefetchedUrls = new Set();
const priorityPrefetchHrefs = [
    '/',
    ...navigation.map((item) => item.href),
    '/operr-app',
    '/setari',
    '/backup',
];

function normalizedInternalHref(rawHref) {
    if (!rawHref || rawHref.startsWith('#') || rawHref.startsWith('mailto:') || rawHref.startsWith('tel:')) {
        return null;
    }

    try {
        const url = new URL(rawHref, window.location.origin);

        if (url.origin !== window.location.origin) {
            return null;
        }

        const href = `${url.pathname}${url.search}${url.hash}`;

        return href === `${window.location.pathname}${window.location.search}${window.location.hash}` ? null : href;
    } catch {
        return null;
    }
}

function prefetchInternalHref(rawHref) {
    const href = normalizedInternalHref(rawHref);

    if (!href || prefetchedUrls.has(href)) {
        return;
    }

    prefetchedUrls.add(href);
    router.prefetch(href, { method: 'get' }, { cacheFor: prefetchCacheFor });
}

function prefetchTarget(target) {
    if (!(target instanceof Element)) {
        return;
    }

    const link = target.closest('a[href]');

    if (link && !link.hasAttribute('download') && link.getAttribute('target') !== '_blank') {
        prefetchInternalHref(link.getAttribute('href'));
        return;
    }

    const prefetchable = target.closest('[data-prefetch-href]');

    if (prefetchable) {
        prefetchInternalHref(prefetchable.getAttribute('data-prefetch-href'));
    }
}

function prefetchElement(element) {
    if (!(element instanceof Element)) {
        return;
    }

    if (element.matches('[data-prefetch-on-intent="true"]')) {
        return;
    }

    prefetchInternalHref(element.getAttribute('href') || element.getAttribute('data-prefetch-href'));
}

function isActive(url, href) {
    if (href === '/') {
        return url === '/';
    }

    return url === href || url.startsWith(`${href}/`);
}

function Logo() {
    return (
        <Link href="/" className="brand">
            <div className="brand-mark">
                <Building2 size={34} strokeWidth={1.8} />
            </div>
            <div className="brand-text">Imo Core</div>
        </Link>
    );
}

function UserMenu() {
    const [open, setOpen] = useState(false);
    const menuRef = useRef(null);

    const menuItems = [
        { label: 'Operr App', icon: FileText, href: '/operr-app', showExternal: true },
        { label: 'Setări', icon: Settings, href: '/setari' },
        { label: 'Backup', icon: HardDriveUpload, href: '/backup' },
    ];

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        function handleClickOutside(event) {
            if (menuRef.current && !menuRef.current.contains(event.target)) {
                setOpen(false);
            }
        }

        function handleEscape(event) {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        }

        document.addEventListener('mousedown', handleClickOutside);
        document.addEventListener('keydown', handleEscape);

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
            document.removeEventListener('keydown', handleEscape);
        };
    }, [open]);

    function closeMenu() {
        setOpen(false);
    }

    return (
        <div className="user-menu user-menu-top" ref={menuRef}>
            <button
                type="button"
                className="user-menu-trigger"
                onClick={() => setOpen((current) => !current)}
                aria-expanded={open}
                aria-haspopup="menu"
            >
                <div className="avatar">O</div>
                <span>Owner</span>
                <ChevronDown size={16} className={open ? 'user-menu-chevron-open' : undefined} />
            </button>

            {open ? (
                <div className="user-menu-dropdown" role="menu">
                    {menuItems.map((item) => {
                        const Icon = item.icon;

                        return (
                            <Link
                                key={item.href}
                                className="user-menu-item"
                                href={item.href}
                                role="menuitem"
                                onClick={closeMenu}
                            >
                                <Icon size={16} />
                                <span>{item.label}</span>
                                {item.showExternal ? <ExternalLink size={14} className="user-menu-external" /> : null}
                            </Link>
                        );
                    })}
                </div>
            ) : null}
        </div>
    );
}

function Sidebar({ open, onClose, currentUrl }) {
    return (
        <>
            <div className={`sidebar-overlay ${open ? 'is-open' : ''}`} onClick={onClose} />
            <aside className={`sidebar ${open ? 'is-open' : ''}`}>
                <button className="sidebar-close" type="button" onClick={onClose} aria-label="Închide meniul">
                    <X size={20} />
                </button>
                <Logo />

                <nav className="sidebar-nav" aria-label="Navigație principală">
                    {navigation.map((item) => {
                        const Icon = item.icon;
                        const active = isActive(currentUrl, item.href);

                        return (
                            <Link
                                className={`nav-item ${active ? 'is-active' : ''}`}
                                href={item.href}
                                key={item.label}
                                onClick={onClose}
                            >
                                <Icon size={18} />
                                <span>{item.label}</span>
                            </Link>
                        );
                    })}
                </nav>
            </aside>
        </>
    );
}

function TopbarPageTitle({ title, subtitle }) {
    return (
        <div className="topbar-page-title">
            <h1>{title}</h1>
            {subtitle ? <p>{subtitle}</p> : null}
        </div>
    );
}

export default function AppLayout({
    children,
    title = 'Dashboard',
    subtitle = null,
    showGlobalSearch = true,
    topbarActions = null,
    topbarTitle = null,
}) {
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const { url, props } = usePage();
    const flash = props.flash || {};

    useEffect(() => {
        function handlePointerOver(event) {
            prefetchTarget(event.target);
        }

        function handleFocusIn(event) {
            prefetchTarget(event.target);
        }

        document.addEventListener('pointerover', handlePointerOver, { passive: true });
        document.addEventListener('focusin', handleFocusIn);

        return () => {
            document.removeEventListener('pointerover', handlePointerOver);
            document.removeEventListener('focusin', handleFocusIn);
        };
    }, []);

    useEffect(() => {
        const hrefs = Array.from(document.querySelectorAll('a[href], [data-prefetch-href]'))
            .filter((element) => !element.matches('[data-prefetch-on-intent="true"]'))
            .map((element) => element.getAttribute('href') || element.getAttribute('data-prefetch-href'))
            .filter(Boolean);
        const uniqueHrefs = [...new Set([...priorityPrefetchHrefs, ...hrefs])];
        const scheduledTimeouts = [];
        const scheduleIdle = window.requestIdleCallback
            ? (callback) => window.requestIdleCallback(callback)
            : (callback) => window.setTimeout(callback, 250);
        const cancelIdle = window.cancelIdleCallback
            ? (idleId) => window.cancelIdleCallback(idleId)
            : (idleId) => window.clearTimeout(idleId);
        const idleId = scheduleIdle(() => {
            uniqueHrefs.slice(0, 36).forEach((href, index) => {
                scheduledTimeouts.push(window.setTimeout(() => prefetchInternalHref(href), index * 35));
            });
        });

        return () => {
            cancelIdle(idleId);
            scheduledTimeouts.forEach((timeoutId) => window.clearTimeout(timeoutId));
        };
    }, [url]);

    useEffect(() => {
        const elements = Array.from(document.querySelectorAll('a[href], [data-prefetch-href]'))
            .filter((element) => {
                if (element.matches('[data-prefetch-on-intent="true"]')) {
                    return false;
                }

                const href = element.getAttribute('href') || element.getAttribute('data-prefetch-href');

                return Boolean(normalizedInternalHref(href));
            });

        if (!('IntersectionObserver' in window)) {
            elements.slice(0, 80).forEach(prefetchElement);
            return undefined;
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                prefetchElement(entry.target);
                observer.unobserve(entry.target);
            });
        }, {
            rootMargin: '700px 0px',
            threshold: 0.01,
        });

        elements.forEach((element) => observer.observe(element));

        return () => observer.disconnect();
    }, [url]);

    return (
        <div className="app-shell">
            <Sidebar open={sidebarOpen} onClose={() => setSidebarOpen(false)} currentUrl={url} />

            <main className="main-shell">
                <header className={`topbar ${showGlobalSearch ? '' : 'topbar-no-search'}`}>
                    <div className="topbar-left">
                        <button className="mobile-menu-button" type="button" onClick={() => setSidebarOpen(true)} aria-label="Deschide meniul">
                            <Menu size={22} />
                        </button>
                        {topbarTitle || <TopbarPageTitle title={title} subtitle={subtitle} />}
                    </div>

                    {topbarActions ? (
                        <div className="topbar-actions">
                            {topbarActions}
                        </div>
                    ) : showGlobalSearch ? (
                        <div className="search-box">
                            <Search size={18} />
                            <input type="search" placeholder="Caută..." />
                        </div>
                    ) : null}

                    <div className="topbar-right">
                        <button className="notification-button" type="button" aria-label="Notificări">
                            <Bell size={20} />
                            <span>3</span>
                        </button>
                        <UserMenu />
                    </div>
                </header>

                <div className="content-shell">
                    {flash.success ? <div className="flash-message flash-success">{flash.success}</div> : null}
                    {flash.warning ? <div className="flash-message flash-warning">{flash.warning}</div> : null}
                    {flash.error ? <div className="flash-message flash-error">{flash.error}</div> : null}
                    {children}
                </div>
            </main>
        </div>
    );
}