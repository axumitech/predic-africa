import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { type Account, money } from './ui';
import '../../../css/app.css';

export const titles: Record<string, string> = { dashboard: 'Vue d’ensemble', home: 'La première Bourse de Prédictions en Afrique', login: 'Connexion à votre Compte', register: 'Créer un compte PredicAfrica', markets: 'Tous les marchés', market: 'Détail du marché', positions: 'Mes Positions', transactions: 'Transactions', wallet: 'Mon Portefeuille', creator: 'Créer un Marché', support: 'Support Client', ticket: 'Votre conversation', notifications: 'Notifications', profile: 'Mon profil', predictions: 'Radar IA', admin: 'Pilotage', 'admin-markets': 'Modération des marchés', 'admin-users': 'Gestion Utilisateurs', 'admin-pipeline': 'Pipeline IA', terms: 'Conditions de la démonstration', privacy: 'Confidentialité', data: 'Mes données', help: 'Centre d’aide' };
const traderNav = [['dashboard', '/dashboard', 'grid', 'Vue d’ensemble'], ['markets', '/markets', 'positions', 'Explorer les marchés'], ['positions', '/positions', 'positions', 'Mes positions'], ['wallet', '/wallet', 'wallet', 'Portefeuille'], ['transactions', '/transactions', 'clock', 'Transactions'], ['creator', '/creator', 'plus', 'Créer un marché'], ['predictions', '/predictions', 'grid', 'Radar IA']];
const adminNav = [['admin', '/admin', 'grid', 'Vue d’ensemble'], ['admin-markets', '/admin/markets', 'positions', 'Modération'], ['admin-users', '/admin/users', 'user', 'Utilisateurs'], ['admin-pipeline', '/admin/pipeline', 'grid', 'Pipeline IA']];
const commonNav = [['support', '/support', 'support', 'Support'], ['notifications', '/notifications', 'bell', 'Notifications'], ['profile', '/profile', 'user', 'Mon profil']];
export const descriptions: Record<string, string> = {
    markets: 'Une question vous inspire ? Explorez les cotes et faites votre choix.',
    positions: 'Gardez le fil de vos convictions, de la mise au résultat.',
    wallet: 'Votre réserve de crédits pour explorer et expérimenter.',
    transactions: 'Toutes vos opérations, réunies au même endroit.',
    creator: 'Une bonne question commence par des règles claires.',
    support: 'Une question, un blocage ? La conversation commence ici.',
    ticket: 'Retrouvez vos échanges et poursuivez la conversation.',
    notifications: 'Les nouvelles qui comptent pour votre compte.',
    profile: 'Vos informations et la sécurité de votre compte.',
    predictions: 'Explorez les suggestions du simulateur.',
    'admin-markets': 'Examinez les propositions, publiez et résolvez les marchés.',
    'admin-users': 'Consultez les comptes et gérez leur accès à la plateforme.',
    'admin-pipeline': 'Générez et examinez les suggestions avant leur publication.',
};
function Icon({ name }: { name: string }) {
    const paths: Record<string, ReactNode> = {
        grid: <><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></>,
        positions: <><rect x="5" y="5" width="14" height="16" rx="2"/><path d="M9 3h6v4H9zM9 12h6M9 16h6"/></>,
        clock: <><circle cx="12" cy="12" r="9"/><path d="M12 8v4l3 3"/></>,
        plus: <path d="M12 4v16M4 12h16"/>,
        support: <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2zM8 10h8M8 14h5"/>,
        wallet: <><rect x="3" y="5" width="18" height="15" rx="2"/><path d="M3 8h18M16 12h5v5h-5z"/></>,
        bell: <path d="M18 8a6 6 0 0 0-12 0v5l-2 4h16l-2-4zM9 20h6"/>,
        user: <><circle cx="12" cy="7" r="4"/><path d="M4 21v-2a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v2"/></>,
    };
    return <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{paths[name] || paths.grid}</svg>;
}
export default function Layout({ page, auth, balance, unread, flash, children, adminView = false }: { page: string; auth: Account | null; balance: number; unread: number; flash?: { success?: string }; children: ReactNode; adminView?: boolean }) {
    const [collapsed, setCollapsed] = useState(false);
    const [search, setSearch] = useState('');
    const [menu, setMenu] = useState(false);
    const drawer = useRef<HTMLDialogElement>(null);
    useEffect(() => {
        const dialog = drawer.current;
        if (!dialog || !menu) return;
        dialog.showModal();
        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        const desktop = window.matchMedia('(min-width: 1024px)');
        const closeOnDesktop = () => { if (desktop.matches) setMenu(false); };
        desktop.addEventListener('change', closeOnDesktop);
        return () => { dialog.close(); document.body.style.overflow = previous; desktop.removeEventListener('change', closeOnDesktop); };
    }, [menu]);
    const workspace = auth && !['home', 'terms', 'privacy', 'data', 'help'].includes(page);
    const admin = auth?.role === 'admin' && (adminView || !['markets', 'market', 'positions', 'wallet', 'transactions', 'creator', 'predictions'].includes(page));
    const navigation = admin ? adminNav : traderNav.filter(([key]) => auth?.role !== 'admin' || key !== 'dashboard');
    const active = (key: string) => page === key || (page === 'market' && key === (adminView ? 'admin-markets' : 'markets')) || (page === 'ticket' && key === 'support');
    const home = auth?.role === 'admin' ? '/admin' : '/dashboard';
    const roleLabel = admin ? 'Administration' : 'Espace trader';
    const dock = admin ? [...adminNav.slice(0, 3), commonNav[0]] : [traderNav[0], traderNav[1], traderNav[2], traderNav[3]];
    const links = (items: string[][]) => items.map(([key, href, icon, title]) => <Link key={key} href={href} onClick={() => setMenu(false)} aria-current={active(key) ? 'page' : undefined} title={title} className={`nav-menu__item ${active(key) ? 'active' : ''}`}><Icon name={icon}/><span>{title}</span>{key === 'notifications' && unread > 0 && <span className="badge badge--accent">{unread}</span>}</Link>);
    return <div className={`platform ${workspace ? 'p-workspace app-container p-studio' + (collapsed ? ' p-studio--collapsed' : '') : 'p-public'}`} data-page={page} data-workspace={admin ? 'admin' : 'trader'}>
        <Head title={`${titles[page]} — PredicAfrica`} />
        {workspace ? <>
            <aside className="sidebar p-sidebar">
                <Link href={home} className="sidebar__brand"><img src="/HOR_WG.png" alt="PredicAfrica" className="brand-img" /></Link>
                <div className="p-space-label"><span className="pulse-dot"/>{roleLabel}</div>
                <nav className="nav-menu p-navigation" aria-label={roleLabel}><span className="p-nav-caption">{admin ? 'PILOTER' : 'DÉCOUVRIR & PARTICIPER'}</span>{links(navigation)}<span className="p-nav-caption">MON COMPTE</span>{links(commonNav)}</nav>
                {auth.role === 'admin' ? <Link href={admin ? '/markets' : '/admin'} className="p-workspace-switch">{admin ? '↗ Voir l’espace trader' : '↗ Retour à l’administration'}</Link> : <Link href="/wallet" className="p-sidebar-balance" aria-label="Gérer mon portefeuille"><small>CRÉDITS DISPONIBLES</small><strong>{money(balance)} <span>cr.</span></strong><span>Gérer mon portefeuille →</span></Link>}
                <div className="sidebar__profile p-user"><div className="profile-avatar">{auth.name.split(' ').map(n => n[0]).slice(0, 2).join('')}</div><div className="profile-info"><div className="profile-name">{auth.name}</div><div className="profile-location">{auth.role === 'admin' ? 'Administrateur' : 'Compte trader'}</div></div><button className="btn-text-action btn-logout" aria-label="Se déconnecter" onClick={() => router.post('/logout')}>Quitter</button></div>
            </aside>
            <div className="main-panel p-main">
                <header className="app-header p-topbar"><button className="p-collapse" type="button" aria-label={collapsed ? "Déployer la navigation" : "Réduire la navigation"} aria-expanded={!collapsed} onClick={()=>setCollapsed(!collapsed)}>☰</button><Link href={home} className="p-mobile-brand"><img src="/HOR_WG.png" alt="PredicAfrica"/></Link><div className="p-breadcrumb">{roleLabel}<span>/</span><strong>{titles[page]}</strong></div><form className="p-global-search" role="search" onSubmit={e=>{e.preventDefault();router.get(admin ? "/admin/markets" : "/markets",{q:search});}}><input aria-label="Rechercher dans les marchés" type="search" placeholder="Rechercher un marché…" value={search} onChange={e=>setSearch(e.target.value)} maxLength={100}/><button aria-label="Lancer la recherche">↗</button></form><div className="app-header__actions"><span className="p-demo-chip"><span className="pulse-dot"/>Mode démo</span><Link href="/notifications" aria-label={`Notifications : ${unread} non lues`} className="notification-bell"><Icon name="bell"/>{unread > 0 && <span className="notification-badge">{unread}</span>}</Link><Link href="/profile" className="p-top-avatar" aria-label="Mon profil">{auth.name.slice(0, 1).toUpperCase()}</Link></div></header>
                <main className="view-content">{!['dashboard', 'admin'].includes(page) && <div className="p-page-heading"><div><span className="p-eyebrow">{roleLabel}</span><h1>{titles[page]}</h1>{descriptions[page] && <p>{admin && page === 'support' ? 'Suivez les demandes de la communauté et apportez vos réponses.' : descriptions[page]}</p>}</div>{page === 'markets' && <Link href="/creator" className="btn btn--primary">＋ Proposer un marché</Link>}</div>}{flash?.success && <div role="status" className="p-success">{flash.success}</div>}{children}<footer className="p-workspace-footer"><span>PredicAfrica · {roleLabel}</span><span>Crédits fictifs · Aucune valeur monétaire</span></footer></main>
            </div>
            <nav className="p-dock" aria-label="Navigation mobile">{dock.map(([key, href, icon, label]) => <Link key={key} href={href} aria-current={active(key) ? 'page' : undefined}><Icon name={icon}/><span>{({dashboard:'Accueil',markets:'Marchés',positions:'Positions',admin:'Pilotage','admin-users':'Comptes'})[key] || label}</span></Link>)}<button type="button" aria-label="Menu de navigation" aria-haspopup="dialog" aria-expanded={menu} aria-controls="mobile-navigation" onClick={() => setMenu(true)}><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg><span>Menu</span></button></nav>
            <dialog ref={drawer} id="mobile-navigation" className="p-drawer" aria-labelledby="mobile-menu-title" onCancel={() => setMenu(false)} onClose={() => setMenu(false)} onClick={e => { if (e.target === e.currentTarget) setMenu(false); }}><div className="p-drawer-body"><header><div><small>{roleLabel}</small><h2 id="mobile-menu-title">{auth.name}</h2></div><button type="button" aria-label="Fermer le menu" onClick={() => setMenu(false)}>×</button></header>{!admin && <Link className="p-drawer-balance" href="/wallet" onClick={() => setMenu(false)}><span>Mon portefeuille<strong>{money(balance)} <small>crédits démo</small></strong></span><span aria-hidden="true">→</span></Link>}<nav aria-label="Tous les parcours">{links([...navigation, ...commonNav])}</nav>{auth.role === 'admin' && <Link className="p-workspace-switch" href={admin ? '/markets' : '/admin'} onClick={() => setMenu(false)}>{admin ? 'Voir l’espace trader →' : 'Retour à l’administration →'}</Link>}<button type="button" className="p-drawer-logout" onClick={() => { setMenu(false); router.post('/logout'); }}>Se déconnecter</button></div></dialog>
        </> : <><header className="landing-header"><Link href="/" className="landing-header__brand"><img src="/HOR_WG.png" alt="PredicAfrica" className="brand-img" style={{ height: 40, width: 'auto' }}/></Link><Link className="btn btn--primary" href={auth ? home : '/login'}>Accéder à mon espace</Link></header><main>{children}</main><footer className="p-footer landing-footer"><span>© {new Date().getFullYear()} PredicAfrica · Démonstration</span><div><Link href="/terms">Conditions</Link><Link href="/privacy">Confidentialité</Link><Link href="/data">Mes données</Link></div></footer></>}
    </div>;
}
