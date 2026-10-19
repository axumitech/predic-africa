import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Landing from '../features/platform/Landing';
import Layout, { titles } from '../features/platform/Layout';
import { ActionForm, Empty, MarketCards, Pager, Panel, Status, categories, date, money, type Account, type Market, type Pagination } from '../features/platform/ui';
import '../features/platform/platform.css';
import '../features/platform/workspace.css';
import MarketExplorer from '../features/platform/MarketExplorer';
import { Positions, Transactions, type Position, type Transaction } from '../features/platform/Records';
import { Button, Modal, ConfirmationModal, StatCard } from '../components/ui';
import PublicInfo from '../features/platform/PublicInfo';
import Dashboard from '../features/platform/Dashboard';
type Ticket = {
    id: number;
    subject: string;
    status: string;
    updated_at: string;
};
type Message = {
    id: number;
    body: string;
    name: string;
    role: string;
    created_at: string;
};
type Notification = {
    id: number;
    message: string;
    read_at: string | null;
    created_at: string;
};
type Props = {
    page: string;
    auth: Account | null;
    balance: number;
    unread: number;
    flash?: {
        success?: string;
    };
    markets?: Pagination<Market>;
    market?: Market;
    adminView?: boolean;
    positions?: Pagination<Position>;
    transactions?: Pagination<Transaction>;
    tickets?: Pagination<Ticket>;
    ticket?: Ticket;
    messages?: Pagination<Message>;
    notifications?: Pagination<Notification>;
    users?: Pagination<Account & {
        suspended: boolean;
        balance:number;
        created_at:string;
    }>;
    filters?: Record<string, string>;
    stats?: Record<string, number>;
    activity?: {label:string;value:number}[];
    walletStats?: Record<string, number>;
    customer?: {name:string;email:string;role:string;created_at:string};
    recentActivity?: { id: number; kind: string; amount: number; created_at: string }[];
    volume?: number;
    participants?: number;
    pipeline?: {
        status: string;
        data: unknown[];
    };
};
export default function Platform(props: Props) { if (props.page === 'home') return <Landing authenticated={!!props.auth} markets={props.markets}/>;
    return <Layout {...props}><Content key={props.page + (props.market?.id || props.ticket?.id || '')} {...props}/></Layout>; }
function Content(props: Props) {
    const { page, auth } = props;
    if (page === 'dashboard' || page === 'admin') return <Dashboard name={auth?.name || ''} admin={page === 'admin'} balance={props.balance} stats={props.stats} markets={props.markets} recentActivity={props.recentActivity} activity={props.activity}/>;
    if (['terms', 'privacy', 'data', 'help'].includes(page))
        return <PublicInfo page={page}/>;
    if (page === 'markets' || page === 'admin-markets')
        return <>{page === 'markets' && <div className="analytics-summary">{[['volume', 'Volume Global'], ['active', 'Positions Actives'], ['paid', 'Gains distribués']].map(([key, title]) => <div className="stat-card" key={key}><div className="stat-card__icon text--gold"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="9"/><path d="M8 12h8M12 8v8"/></svg></div><div className="stat-card__info"><span className="stat-title">{title}</span><span className="stat-value">{money(props.stats?.[key] || 0)}{key !== 'active' && ' crédits'}</span></div></div>)}</div>}<p className="p-intro">{page === 'markets' ? 'Des questions claires. Des cotes fixes. À vous de choisir.' : 'Examinez les propositions et enregistrez les résultats depuis la fiche de chaque marché.'}</p><MarketExplorer filters={props.filters} result={props.markets} admin={page === 'admin-markets'}/></>;
    if (page === 'market' && props.market)
        return <MarketDetail {...props} market={props.market}/>;
    if (page === 'creator')
        return <div className="p-stack"><Panel title="Proposez une question à la communauté"><p>Un marché doit être vérifiable. Précisez les conditions du Oui, du Non et une source de référence. Chaque proposition est soumise à modération.</p><ActionForm action="/markets" initial={{ title: '', description: '', category: 'Sport', closes_at: '', yes_odds: '200', no_odds: '200' }} fields={[
                { name: 'title', label: 'Question du marché', minLength: 10, maxLength: 200 }, { name: 'description', label: 'Règles de résolution et source', type: 'textarea', minLength: 20, maxLength: 5000 },
                { name: 'category', label: 'Catégorie', options: categories.map(c => ({ value: c, label: c })) }, { name: 'closes_at', label: 'Clôture (UTC)', type: 'datetime-local', hint: 'La date est enregistrée en UTC. Les fiches affichent ensuite votre heure locale.' },
                { name: 'yes_odds', label: 'Cote Oui en centièmes', type: 'number', min: 101, max: 1000, hint: '200 = cote ×2,00. Maximum ×10.' }, { name: 'no_odds', label: 'Cote Non en centièmes', type: 'number', min: 101, max: 1000 },
            ]} submit="Soumettre à la modération"/></Panel><h2>Mes propositions</h2><MarketCards result={props.markets}/></div>;
    if (page === 'positions') return <Positions result={props.positions} filters={props.filters}/>;
    if (page === 'wallet' || page === 'transactions')
        return <div className="p-stack">{page === 'wallet' && <div className="p-two"><div className="p-balance-card wallet-widget"><small>VOTRE SOLDE DISPONIBLE</small><h2>{money(props.balance)} <span>crédits</span></h2><p>Ces crédits servent uniquement à tester la plateforme. Ils n’ont aucune valeur monétaire et ne sont pas convertibles.</p></div><Panel title="Tester un dépôt ou un retrait"><ActionForm action="/wallet" initial={{ kind: 'deposit', amount: '1000', reference: crypto.randomUUID() }} freshReference fields={[{ name: 'kind', label: 'Opération', options: [{ value: 'deposit', label: 'Dépôt simulé' }, { value: 'withdraw', label: 'Retrait simulé' }] }, { name: 'amount', label: 'Montant en crédits', type: 'number', min: 100, max: 1000000 }]} submit="Confirmer la simulation"/></Panel></div>}<div className="p-wallet-stats">{page === 'wallet' && [['engaged','Crédits engagés'],['gains','Gains distribués'],['deposits','Dépôts simulés'],['withdrawals','Retraits simulés']].map(([key,label])=><StatCard key={key} label={label} value={`${money(props.walletStats?.[key]||0)} cr.`}/>)}</div><Transactions result={props.transactions} filters={props.filters} wallet={page === 'wallet'}/></div>;
    if (page === 'support')
        return <div className={auth?.role === 'admin' ? 'p-stack' : 'p-two'}><Panel title={auth?.role === 'admin' ? 'Tous les tickets' : 'Mes demandes'}>{!props.tickets?.data.length ? <Empty>Aucune demande pour le moment.</Empty> : props.tickets.data.map(t => <Link className="p-list-item" key={t.id} href={`/support/${t.id}`}><div><strong>#{t.id} · {t.subject}</strong><small>{date(t.updated_at)}</small></div><Status value={t.status}/></Link>)}<Pager result={props.tickets}/></Panel>{auth?.role !== 'admin' && <Panel title="Comment pouvons-nous vous aider ?"><ActionForm action="/support" initial={{ subject: '', body: '' }} fields={[{ name: 'subject', label: 'Sujet', minLength: 5, maxLength: 200 }, { name: 'body', label: 'Votre message', type: 'textarea', minLength: 10 }]} submit="Créer un ticket"/></Panel>}</div>;
    if (page === 'ticket' && props.ticket)
        return <div className="p-support-layout"><aside className="p-panel p-support-list"><h2>Conversations</h2>{props.tickets?.data.map(t=><Link key={t.id} href={`/support/${t.id}`} aria-current={t.id===props.ticket?.id?'page':undefined}>#{t.id} · {t.subject}<small>{date(t.updated_at)}</small><Status value={t.status}/></Link>)}</aside><Panel title={`#${props.ticket.id} · ${props.ticket.subject}`}><Link href="/support">← Tous les tickets</Link><div className="p-conversation">{props.messages?.data.map(m => <article className={`p-message ${m.role === 'admin' ? 'p-message-admin' : ''}`} key={m.id}><div className="p-row"><strong>{m.name}{m.role === 'admin' && ' · Support'}</strong><small>{date(m.created_at)}</small></div><p>{m.body}</p></article>)}</div><Pager result={props.messages}/><Status value={props.ticket.status}/><ActionForm action={`/support/${props.ticket.id}`} initial={{ body: '', status: props.ticket.status }} fields={[{ name: 'body', label: 'Votre réponse', type: 'textarea' }, { name: 'status', label: 'État après envoi', options: [{ value: 'open', label: 'Ouvert' }, { value: 'closed', label: 'Fermé' }] }]} submit="Envoyer la réponse"/></Panel><aside className="p-panel p-support-customer"><h2>{props.customer ? 'Informations du client' : 'Votre demande'}</h2>{props.customer&&<><strong>{props.customer.name}</strong><p>{props.customer.email}</p><small>Inscription : {date(props.customer.created_at)}</small></>}<p>Ticket #{props.ticket.id}</p><Status value={props.ticket.status}/><p>Vous pouvez fermer ou rouvrir cette conversation lors de votre réponse.</p></aside></div>;
    if (page === 'notifications')
        return <Panel title="L’activité de votre compte"><ActionForm action="/notifications/read" initial={{}} fields={[]} submit="Tout marquer comme lu"/>{!props.notifications?.data.length ? <Empty>Vous êtes à jour. Vos prochaines opérations apparaîtront ici.</Empty> : props.notifications.data.map(n => <div className={`p-list-item ${!n.read_at ? 'p-unread' : ''}`} key={n.id}><div><strong>{n.message}</strong><small>{date(n.created_at)}</small></div><span>{n.read_at ? 'Lue' : 'Nouvelle'}</span></div>)}<Pager result={props.notifications}/></Panel>;
    if (page === 'profile' && auth)
        return <Panel title="Informations personnelles"><ActionForm action="/profile" initial={{ name: auth.name, email: auth.email, current_password: '', password: '', password_confirmation: '' }} fields={[{ name: 'name', label: 'Nom complet', maxLength: 100 }, { name: 'email', label: 'Adresse e-mail', type: 'email' }, { name: 'current_password', label: 'Mot de passe actuel', type: 'password' }, { name: 'password', label: 'Nouveau mot de passe (facultatif)', type: 'password', required: false, minLength: 10 }, { name: 'password_confirmation', label: 'Confirmer le nouveau mot de passe', type: 'password', required: false }]} submit="Enregistrer mon profil"/></Panel>;
    if (page === 'admin-users')
        return <Panel title="Accès à la plateforme"><p>La suspension bloque les pages privées et les nouvelles opérations. Les droits administrateur sont attribués par la commande serveur.</p><div className="p-table-wrap table-responsive"><table className="data-table" role="table"><thead><tr role="row"><th scope="col" role="columnheader">Utilisateur</th><th scope="col" role="columnheader">E-mail</th><th scope="col" role="columnheader">Rôle</th><th scope="col" role="columnheader">Solde / inscription</th><th scope="col" role="columnheader">Accès</th><th scope="col" role="columnheader">Action</th></tr></thead><tbody>{props.users?.data.map(u => <tr role="row" key={u.id}><td role="cell" data-label="Utilisateur">{u.name}</td><td role="cell" data-label="E-mail">{u.email}</td><td role="cell" data-label="Rôle">{u.role}</td><td role="cell" data-label="Solde / inscription">{money(u.balance)} cr.<small className="p-cell-sub">{date(u.created_at)}</small></td><td role="cell" data-label="Accès">{u.suspended ? 'Suspendu' : 'Actif'}</td><td role="cell" data-label="Action">{u.id !== auth?.id && <AccessAction user={u}/>}</td></tr>)}</tbody></table></div><Pager result={props.users}/></Panel>;
    if (page === 'predictions' || page === 'admin-pipeline')
        return <Pipeline pipeline={props.pipeline} admin={page === 'admin-pipeline'}/>;
    return <Empty>Cette page n’est pas disponible. <Link href="/markets">Retour aux marchés</Link></Empty>;
}
function MarketDetail(props: Props & {
    market: Market;
}) {
    const [orderOpen, setOrderOpen] = useState(false);
    const m = props.market;
    const open = m.status === 'open' && new Date(m.closes_at.replace(' ', 'T') + (m.closes_at.includes('T') ? '' : 'Z')) > new Date();
    const order = <ActionForm action={`/markets/${m.id}/positions`} initial={{ outcome: 'yes', amount: '100', reference: crypto.randomUUID() }} fields={[{ name: 'outcome', label: 'Votre conviction', options: [{ value: 'yes', label: `Oui · ×${(m.yes_odds / 100).toFixed(2)}` }, { value: 'no', label: `Non · ×${(m.no_odds / 100).toFixed(2)}` }] }, { name: 'amount', label: 'Mise en crédits', type: 'number', min: 100, max: 1000000 }]} submit="Confirmer ma position">{data => <div className="p-estimate"><span>Retour potentiel (mise incluse)</span><strong>{money(Math.floor((Number(data.amount) || 0) * (data.outcome === 'yes' ? m.yes_odds : m.no_odds) / 100))} crédits</strong><small>Solde disponible : {money(props.balance)} crédits. Mise engagée jusqu’à la résolution.</small></div>}</ActionForm>;
    return <><Link href={props.adminView ? '/admin/markets' : '/markets'}>← Retour aux marchés</Link><div className="p-two p-detail"><Panel><div className="p-row"><span className="p-category">{m.category}</span><Status value={m.status}/></div><h2>{m.title}</h2><h3>Règles et source de référence</h3><p className="p-preserve">{m.description}</p><p className="p-data-note">Cotes fixes : aucun historique de variation n’est enregistré.</p><dl><dt>Création</dt><dd>{m.created_at ? date(m.created_at) : 'Non disponible'}</dd><dt>Clôture</dt><dd>{date(m.closes_at)}</dd><dt>Volume</dt><dd>{money(props.volume || 0)} crédits</dd><dt>Participants</dt><dd>{props.participants || 0}</dd></dl>{m.result && <div className="p-success"><strong>Résultat : {m.result === 'void' ? 'Annulation et remboursement' : m.result === 'yes' ? 'Oui' : 'Non'}</strong><p>{m.resolution_source}</p></div>}</Panel>{props.adminView ? <Panel title="Vérification avant décision"><p>Vérifiez que la question est précise, que la source est accessible et que les conditions de résolution sont sans ambiguïté.</p><div className="p-estimate"><span>Cote Oui : ×{(m.yes_odds / 100).toFixed(2)}</span><span>Cote Non : ×{(m.no_odds / 100).toFixed(2)}</span><small>Les décisions de publication et de résolution sont disponibles ci-dessous.</small></div></Panel> : <Panel title={open ? 'Prenez position' : 'Prises de position fermées'}>{open ? <><div className="p-order-desktop">{order}</div><Button className="p-order-mobile" onClick={()=>setOrderOpen(true)}>Prendre position →</Button><Modal variant="sheet" open={orderOpen} title="Votre position" onClose={()=>setOrderOpen(false)}>{orderOpen && order}</Modal></> : <p>{m.status === 'pending' ? 'Cette proposition attend la validation d’un administrateur.' : 'Vous pouvez consulter les conditions et le résultat sur cette fiche.'}</p>}</Panel>}</div>{props.auth?.role === 'admin' && !props.adminView && <Link className="btn btn--outline" href={`/admin/markets/${m.id}`}>Gérer ce marché dans l’administration →</Link>}{props.adminView && props.auth?.role === 'admin' && <Panel title="Administration du marché">{m.status === 'pending' && <ActionForm action={`/admin/markets/${m.id}/moderate`} initial={{ status: 'open' }} fields={[{ name: 'status', label: 'Décision', options: [{ value: 'open', label: 'Publier' }, { value: 'rejected', label: 'Rejeter' }] }]} submit="Enregistrer la décision"/>}{m.status === 'open' && <ActionForm action={`/admin/markets/${m.id}/resolve`} initial={{ result: 'void', source: '' }} fields={[{ name: 'result', label: 'Résultat officiel', options: [...(!open ? [{ value: 'yes', label: 'Oui' }, { value: 'no', label: 'Non' }] : []), { value: 'void', label: 'Annuler et rembourser' }] }, { name: 'source', label: 'Source du résultat / justification', type: 'textarea', minLength: 10, maxLength: 2000 }]} submit="Résoudre et mettre à jour les soldes"><p>Cette opération est définitive. Avant l’échéance, seule l’annulation avec remboursement est disponible.</p></ActionForm>}{!['open', 'pending'].includes(m.status) && <p>Aucune action restante pour ce marché.</p>}</Panel>}</>;
}
function Pipeline({ pipeline, admin = false }: {
    pipeline?: Props['pipeline'];
    admin?: boolean;
}) {
    return <div className="p-stack"><Panel title="Des explications pour éclairer votre intuition"><p>Le pipeline actuel génère des données de démonstration. Les scores affichés ne sont pas des prévisions fiables et ne garantissent aucun résultat.</p><button className="p-button p-secondary btn btn--outline" onClick={() => router.reload({ only: ['pipeline'] })}>Actualiser le radar</button>{admin && <ActionForm action="/admin/pipeline/generate" initial={{}} fields={[]} submit="Générer des suggestions de démonstration"><p>Le service Python doit être démarré. Les suggestions ne deviennent pas publiques avant importation et modération.</p></ActionForm>}</Panel>{pipeline?.status !== 'success' ? <Empty>Le service IA est indisponible. Vos marchés et votre portefeuille restent accessibles. Réessayez lorsque le pipeline est démarré.</Empty> : !pipeline.data.length ? <Empty>Le pipeline ne propose pas encore de prédiction.</Empty> : <div className="p-grid">{pipeline.data.map((raw, i) => {
                const item = (raw && typeof raw === 'object' ? raw : {}) as Record<string, unknown>;
                const prediction = (item.prediction && typeof item.prediction === 'object' ? item.prediction : {}) as Record<string, unknown>;
                const text = (v: unknown) => typeof v === 'string' || typeof v === 'number' ? String(v) : 'Non renseigné';
                if (admin)
                    return <Panel key={i} title={text(item.title)}><span className="p-badge">Suggestion IA · Démonstration</span><p>{text(item.description)}</p><small>Échéance : {text(item.expiry)}</small><ActionForm action="/admin/pipeline/import" initial={{ id: text(item.id) }} fields={[]} submit="Importer pour modération"/></Panel>;
                return <Panel key={i} title={`${text(item.home_team)} — ${text(item.away_team)}`}><span className="p-badge">Simulation IA</span><p>Choix du modèle : <strong>{text(prediction.winner)}</strong></p><p>Score de confiance : {text(prediction.confidence)}</p><p>{text(prediction.explanation)}</p></Panel>;
            })}</div>}</div>;
}

function AccessAction({user}:{user:Account & {suspended:boolean}}) { const [open,setOpen]=useState(false);const [busy,setBusy]=useState(false);return <><Button variant="secondary" onClick={()=>setOpen(true)}>{user.suspended?'Réactiver':'Suspendre'}</Button><ConfirmationModal open={open} title={user.suspended?'Réactiver cet accès ?':'Suspendre cet accès ?'} onClose={()=>setOpen(false)} busy={busy} danger={!user.suspended} onConfirm={()=>{setBusy(true);router.post(`/admin/users/${user.id}`,{suspended:!user.suspended},{onFinish:()=>{setBusy(false);setOpen(false);}});}}><p>{user.name} · {user.email}</p><p>{user.suspended?'La personne pourra de nouveau accéder à son compte.':'Cette personne ne pourra plus accéder aux pages privées ni effectuer de nouvelles opérations.'}</p></ConfirmationModal></>; }
