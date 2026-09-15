import { Head, Link, router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { type Account, money } from './ui';
import '../../../css/app.css';

export const titles: Record<string, string> = { home: 'La première Bourse de Prédictions en Afrique', login: 'Connexion à votre Compte', register: 'Créer un compte PredicAfrica', markets: 'Tous les marchés', market: 'Détail du marché', positions: 'Mes Positions', transactions: 'Transactions', wallet: 'Mon Portefeuille', creator: 'Créer un Marché', support: 'Support Client', ticket: 'Votre conversation', notifications: 'Notifications', profile: 'Mon profil', predictions: 'Radar IA', admin: 'Backoffice', 'admin-markets': 'Tous les marchés', 'admin-users': 'Gestion Utilisateurs', 'admin-pipeline': 'Pipeline IA', terms: 'Conditions de la démonstration', privacy: 'Confidentialité', data: 'Mes données', help: 'Centre d’aide' };
const nav = [['markets', '/markets', 'grid', 'Tous les marchés'], ['positions', '/positions', 'positions', 'Mes Positions'], ['transactions', '/transactions', 'clock', 'Transactions'], ['creator', '/creator', 'plus', 'Créer un Marché'], ['support', '/support', 'support', 'Support Client'], ['wallet', '/wallet', 'wallet', 'Portefeuille'], ['predictions', '/predictions', 'grid', 'Radar IA'], ['notifications', '/notifications', 'bell', 'Notifications'], ['profile', '/profile', 'user', 'Mon profil']];
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
export default function Layout({ page, auth, balance, unread, flash, children }: { page: string; auth: Account | null; balance: number; unread: number; flash?: { success?: string }; children: ReactNode }) {
    const [menu, setMenu] = useState(false);
    const [region, setRegion] = useState('SEN');
    const workspace = auth && !['home', 'terms', 'privacy', 'data', 'help'].includes(page);
    const authentication = page === 'login' || page === 'register';
    return <div className={`platform ${workspace ? 'p-workspace app-container' : 'p-public'}`}>
        <Head title={`${titles[page]} — PredicAfrica`} />
        {workspace ? <>
            <aside className="sidebar p-sidebar">
                <Link href="/" className="sidebar__brand"><img src="/HOR_WG.png" alt="Wegame Logo" className="brand-img" style={{ height: 40, width: 'auto' }} /></Link>
                <div className="country-selector"><label htmlFor="platform-region" className="country-selector__label">Région :</label><select id="platform-region" className="country-selector__select" value={region} onChange={e => setRegion(e.target.value)}>{[['SEN', 'Sénégal (XOF)'], ['CIV', "Côte d’Ivoire (XOF)"], ['CMR', 'Cameroun (XAF)'], ['COD', 'RDC (CDF)'], ['COG', 'Congo Brazzaville (XAF)'], ['GAB', 'Gabon (XAF)'], ['BEN', 'Bénin (XOF)'], ['TGO', 'Togo (XOF)']].map(([code, title]) => <option key={code} value={code}>{title}</option>)}</select></div>
                <div className="wallet-widget p-wallet">
                    <div className="wallet-widget__header"><span className="wallet-widget__title">Mon Portefeuille</span><span className="wallet-widget__badge">Compte MVP</span></div>
                    <div className="wallet-widget__balance"><span className="balance-value">{money(balance)}</span> <span className="balance-currency">crédits</span></div>
                    <div className="wallet-widget__actions"><Link href="/wallet" className="btn btn--primary" aria-label="Gérer mon portefeuille">Recharger</Link><Link href="/wallet" className="btn btn--outline">Retirer</Link></div>
                </div>
                <nav className={`nav-menu p-navigation ${menu ? 'p-menu-open' : ''}`} aria-label="Navigation principale">
                    {nav.map(([key, href, icon, title]) => <Link key={key} href={href} onClick={() => setMenu(false)} className={`nav-menu__item ${page === key || (page === 'market' && key === 'markets') || (page === 'ticket' && key === 'support') ? 'active' : ''}`}><Icon name={icon}/><span>{title}</span>{key === 'notifications' && unread > 0 && <span className="badge badge--accent">{unread}</span>}</Link>)}
                    {auth.role === 'admin' && [['admin', 'Backoffice'], ['admin-markets', 'Modération'], ['admin-users', 'Gestion Utilisateurs'], ['admin-pipeline', 'Pipeline IA']].map(([key, title]) => <Link key={key} className={`nav-menu__item ${page === key ? 'active' : ''}`} href={'/' + key.replace('-', '/')} onClick={() => setMenu(false)}><Icon name={key === 'admin-users' ? 'user' : 'grid'}/><span>{title}</span></Link>)}
                </nav>
                <div className="sidebar__profile p-user"><div className="profile-avatar">{auth.name.split(' ').map(n => n[0]).slice(0, 2).join('')}</div><div className="profile-info"><div className="profile-name">{auth.name}</div><div className="profile-location">{auth.role === 'admin' ? 'Administrateur' : 'Compte trader'}</div></div><button className="btn-text-action btn-logout" aria-label="Se déconnecter" onClick={() => router.post('/logout')}>Quitter</button></div>
            </aside>
            <div className="main-panel p-main"><header className="app-header p-topbar"><button className="p-mobile-toggle btn btn--outline" aria-expanded={menu} aria-label="Menu de navigation" onClick={() => setMenu(!menu)}>☰</button><div className="app-header__actions"><div className="status-indicator"><span className="pulse-dot"/><span className="status-text">Simulateur Actif</span></div><Link href="/notifications" aria-label={`Notifications : ${unread} non lues`} className="notification-bell"><Icon name="bell"/>{unread > 0 && <span className="notification-badge">{unread}</span>}</Link></div></header><main className="view-content">{page !== 'markets' && <div className="p-page-heading"><h1>{titles[page]}</h1></div>}{flash?.success && <div role="status" className="p-success">{flash.success}</div>}{children}</main></div>
        </> : authentication ? <div id="app-login"><div className="login-container"><div className="login-header-bar"><Link href="/" className="btn btn--outline btn--sm">← Retour à l’accueil</Link><img src="/HOR_WG.png" alt="Wegame Logo" className="brand-img" style={{ height: 32, width: 'auto' }}/></div>{children}</div></div> : <><header className="landing-header"><Link href="/" className="landing-header__brand"><img src="/HOR_WG.png" alt="Wegame Logo" className="brand-img" style={{ height: 40, width: 'auto' }}/></Link><Link className="btn btn--primary" href={auth ? '/markets' : '/login'}>Accéder à la Bourse</Link></header><main>{children}</main><Footer/></>}
    </div>;
}
function Footer() { return <footer className="p-footer landing-footer"><span>© {new Date().getFullYear()} PredicAfrica · Démonstration</span><div><Link href="/terms">Conditions Générales</Link><Link href="/privacy">Confidentialité</Link><Link href="/data">Gestion des Données</Link></div></footer>; }
