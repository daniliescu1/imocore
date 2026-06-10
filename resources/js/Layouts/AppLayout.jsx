import React, { useEffect, useRef, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Bell,
    Building2,
    CalendarDays,
    ChevronDown,
    ExternalLink,
    FileCheck2,
    FileText,
    HardDriveUpload,
    Home,
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
    { label: 'Dashboard', icon: Home, href: '/' },
    { label: 'Imobile', icon: Building2, href: '/imobile' },
    { label: 'Configurare anexă', icon: ReceiptText, href: '/configurare-anexa' },
    { label: 'Spații', icon: BarChart3, href: '/spatii' },
    { label: 'Rezervări', icon: CalendarDays, href: '/rezervari' },
    { label: 'Locatori', icon: Users, href: '/locatori' },
    { label: 'Contracte', icon: FileText, href: '/contracte' },
    { label: 'PV Predare', icon: FileCheck2, href: '/pv-predare' },
    { label: 'Utilități', icon: Zap, href: '/utilitati' },
    { label: 'Citiri contoare', icon: BarChart3, href: '/citiri-contoare' },
    { label: 'Generare anexe', icon: ReceiptText, href: '/anexe' },
    { label: 'Facturare', icon: WalletCards, href: '/facturare' },
    { label: 'Contabilitate primară', icon: FileText, href: '/contabilitate-primara' },
    { label: 'Cheltuieli', icon: WalletCards, href: '/cheltuieli' },
    { label: 'Indexare chirii', icon: BarChart3, href: '/indexare-chirii' },
    { label: 'Reguli imobile', icon: Settings, href: '/reguli-imobile' },
    { label: 'Setări', icon: Settings, href: '/setari' },
    { label: 'Backup', icon: HardDriveUpload, href: '/backup' },
];

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

function UserMenu({ variant = 'top', onNavigate = null }) {
    const [open, setOpen] = useState(false);
    const menuRef = useRef(null);

    const menuItems = [
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
        onNavigate?.();
    }

    return (
        <div className={`user-menu user-menu-${variant}`} ref={menuRef}>
            <button
                type="button"
                className="user-menu-trigger"
                onClick={() => setOpen((current) => !current)}
                aria-expanded={open}
                aria-haspopup="menu"
            >
                <div className={`avatar ${variant === 'sidebar' ? 'sidebar-avatar' : ''}`}>O</div>
                <div className={variant === 'sidebar' ? 'user-menu-copy' : undefined}>
                    {variant === 'sidebar' ? (
                        <>
                            <div className="sidebar-user-name">Owner</div>
                            <div className="sidebar-user-role">Administrator</div>
                        </>
                    ) : (
                        <span>Owner</span>
                    )}
                </div>
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

                <div className="sidebar-bottom">
                    <Link className="nav-item oper-link" href="/operr-app" onClick={onClose}>
                        <FileText size={18} />
                        <span>Operr App</span>
                        <ExternalLink size={16} className="nav-external" />
                    </Link>

                    <UserMenu variant="sidebar" onNavigate={onClose} />
                </div>
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