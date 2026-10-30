import { Link } from '@inertiajs/react';
import { createPortal } from 'react-dom';
import { useEffect, useId, useRef, useState, type ReactNode } from 'react';

type Props = { href: string; label: string; icon: ReactNode; active?: boolean; compact?: boolean; count?: number; onNavigate?: () => void; className?: string };
export default function SidebarLink({ href, label, icon, active, compact, count = 0, onNavigate, className = '' }: Props) {
    const id = useId();
    const anchor = useRef<HTMLAnchorElement>(null);
    const [position, setPosition] = useState<{ left: number; top: number } | null>(null);
    const show = (node: HTMLElement) => {
        if (!compact || !window.matchMedia('(min-width: 1024px)').matches) return;
        const r = node.getBoundingClientRect();
        setPosition({ left: r.right + 16, top: Math.max(32, Math.min(innerHeight - 32, r.top + r.height / 2)) });
    };
    const showOnFocus = (node: HTMLElement) => {
        // Wait for the browser to scroll an off-screen navigation item into view.
        requestAnimationFrame(() => requestAnimationFrame(() => {
            if (node.isConnected && document.activeElement === node) show(node);
        }));
    };
    useEffect(() => {
        setPosition(null);
        if (compact && anchor.current && document.activeElement === anchor.current) showOnFocus(anchor.current);
    }, [compact]);
    useEffect(() => {
        if (!position) return;
        const close = () => setPosition(null);
        const escape = (e: KeyboardEvent) => { if (e.key === 'Escape') close(); };
        window.addEventListener('resize', close);
        window.addEventListener('scroll', close, true);
        window.addEventListener('keydown', escape);
        return () => { window.removeEventListener('resize', close); window.removeEventListener('scroll', close, true); window.removeEventListener('keydown', escape); };
    }, [position]);
    return <>
        <Link ref={anchor} href={href} className={`nav-menu__item sidebar-link ${active ? 'active' : ''} ${className}`} aria-label={label} aria-current={active ? 'page' : undefined} aria-describedby={position ? id : undefined}
            onMouseEnter={e => show(e.currentTarget)} onMouseLeave={() => setPosition(null)} onFocus={e => showOnFocus(e.currentTarget)} onBlur={() => setPosition(null)} onClick={() => { setPosition(null); onNavigate?.(); }}>
            <span className="sidebar-link__icon">{icon}</span><span className="sidebar-link__label">{label}</span>{count > 0 && <span className="sidebar-link__count">{count > 99 ? '99+' : count}</span>}
        </Link>
        {position && createPortal(<span id={id} role="tooltip" className="sidebar-bubble" style={{ left: position.left, top: position.top }}><span className="sidebar-bubble__dot"/>{label}{count > 0 && <small>{count} non lues</small>}</span>, document.body)}
    </>;
}
