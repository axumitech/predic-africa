import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { CurrencyProvider, ReferenceAmount, formatMinor, type Fx, type Minor } from '../features/platform/currency';
import PositionOrder from '../features/platform/PositionOrder';
import NdsgpayWallet, { type NdsgpayWalletData } from '../features/platform/NdsgpayWallet';
import Landing from '../features/platform/Landing';
import Layout, { titles } from '../features/platform/Layout';
import { ActionForm, Empty, MarketCards, Pager, Panel, Status, categories, date, money, type Account, type Market, type Pagination } from '../features/platform/ui';
import '../features/platform/platform.css';
import '../features/platform/workspace.css';
import '../../css/fx.css';
import MarketExplorer from '../features/platform/MarketExplorer';
import { Positions, Transactions, type Position, type Transaction } from '../features/platform/Records';
import { Button, Modal, ConfirmationModal, StatCard, DataTable, Badge } from '../components/ui';
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
    balance_exact?: string;
    fx?: Fx;
    currencyBalances?: {currency:string;balance:string}[];
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
    ndsgpay?: NdsgpayWalletData;
    walletStats?: Record<string, number>;
    customer?: {name:string;email:string;role:string;created_at:string};
    recentActivity?: { id: number; kind: string; amount: number; created_at: string; currency?: string }[];
    volume?: number;
    participants?: number;
    pipeline?: {
        status: string;
        data: unknown[];
    };
};
export default function Platform(props: Props) {
    return <CurrencyProvider initial={props.fx} enabled={!!props.auth}>{props.page === 'home'
        ? <Landing authenticated={!!props.auth} markets={props.markets} stats={props.stats}/>
        : <Layout {...props}><Content key={props.page + (props.market?.id || props.ticket?.id || '')} {...props}/></Layout>}</CurrencyProvider>;
}

function Content(props: Props) {
    const { page, auth } = props;
    const walletCurrency = auth?.wallet_currency || 'XAF';
    if (page === 'dashboard' || page === 'admin') return <Dashboard name={auth?.name || ''} admin={page === 'admin'} balance={props.balance_exact ?? props.balance} currency={walletCurrency} currencyBalances={props.currencyBalances} stats={props.stats} markets={props.markets} recentActivity={props.recentActivity} activity={props.activity}/>;
    if (['terms', 'privacy', 'data', 'help'].includes(page))
        return <PublicInfo page={page}/>;
    if (page === 'markets' || page === 'admin-markets')
        return <>{page === 'markets' && <div className="analytics-summary">{[['volume', 'Volume Global'], ['active', 'Positions Actives'], ['paid', 'Gains distribués']].map(([key, title]) => <div className="stat-card" key={key}><div className="stat-card__icon text--gold"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="9"/><path d="M8 12h8M12 8v8"/></svg></div><div className="stat-card__info"><span className="stat-title">{title}</span><span className="stat-value">{key === 'active' ? money(props.stats?.[key] || 0) : <ReferenceAmount value={props.stats?.[key] || 0}/>}</span></div></div>)}</div>}<p className="p-intro">{page === 'markets' ? 'Des questions claires. Des cotes fixes. À vous de choisir.' : 'Examinez les propositions et enregistrez les résultats depuis la fiche de chaque marché.'}</p><MarketExplorer filters={props.filters} result={props.markets} admin={page === 'admin-markets'}/></>;
    if (page === 'market' && props.market)
        return <MarketDetail {...props} market={props.market}/>;
    if (page === 'creator')
        return <div className="p-stack p-creator-layout"><Panel title="Proposez une question à la communauté"><p>Un marché doit être vérifiable. Précisez les conditions du Oui, du Non et une source de référence. Chaque proposition est soumise à modération.</p><ActionForm action="/markets" initial={{ title: '', description: '', category: 'Sport', closes_at: '', yes_odds: '200', no_odds: '200' }} fields={[
                { name: 'title', label: 'Question du marché', minLength: 10, maxLength: 200 }, { name: 'description', label: 'Règles de résolution et source', type: 'textarea', minLength: 20, maxLength: 5000 },
                { name: 'category', label: 'Catégorie', options: categories.map(c => ({ value: c, label: c })) }, { name: 'closes_at', label: 'Clôture (UTC)', type: 'datetime-local', hint: 'La date est enregistrée en UTC. Les fiches affichent ensuite votre heure locale.' },
                { name: 'yes_odds', label: 'Cote Oui en centièmes', type: 'number', min: 101, max: 1000, hint: '200 = cote ×2,00. Maximum ×10.' }, { name: 'no_odds', label: 'Cote Non en centièmes', type: 'number', min: 101, max: 1000 },
            ]} submit="Soumettre à la modération"/></Panel><aside className="p-panel p-creator-guide"><span className="p-eyebrow">AVANT DE SOUMETTRE</span><h2>Une question qui ne laisse rien au hasard.</h2><ol><li><strong>Un résultat vérifiable</strong><p>Précisez exactement les conditions du Oui et du Non.</p></li><li><strong>Une source accessible</strong><p>Indiquez où le résultat officiel pourra être consulté.</p></li><li><strong>Une échéance claire</strong><p>Prévoyez une clôture avant que le résultat soit connu.</p></li></ol><p>Votre proposition sera examinée avant publication.</p></aside><h2 className="p-creator-history">Mes propositions</h2><MarketCards result={props.markets}/></div>;
    if (page === 'positions') return <Positions result={props.positions} filters={props.filters}/>;
    if (page === 'wallet' || page === 'transactions')
        return <div className="p-stack">{page === 'wallet' && <div className="p-wallet-overview"><div className="p-wallet-summary"><div className="p-balance-card wallet-widget"><small>VOTRE SOLDE DISPONIBLE</small><h2>{formatMinor(props.balance_exact ?? props.balance, walletCurrency)} <span>{walletCurrency}</span></h2><p>Votre solde finance vos positions dans la devise de votre portefeuille. Les dépôts sont disponibles après confirmation du prestataire.</p><Link href="/positions">Consulter mes positions →</Link></div><div className="p-wallet-stats">{[['engaged','Montant engagé'],['gains','Gains distribués'],['deposits','Dépôts confirmés'],['withdrawals','Retraits confirmés']].map(([key,label])=><StatCard key={key} label={label} value={<>{formatMinor(props.walletStats?.[key]||0, walletCurrency)} <small>{walletCurrency}</small></>}/>)}</div></div><NdsgpayWallet data={props.ndsgpay}/></div>}<Transactions currency={walletCurrency} result={props.transactions} filters={props.filters} wallet={page === 'wallet'}/></div>;
    if (page === 'support')
        return <div className={['admin', 'superadmin'].includes(auth?.role || '') ? 'p-stack' : 'p-two'}><Panel title={['admin', 'superadmin'].includes(auth?.role || '') ? 'Tous les tickets' : 'Mes demandes'}>{!props.tickets?.data.length ? <Empty>Aucune demande pour le moment.</Empty> : props.tickets.data.map(t => <Link className="p-list-item" key={t.id} href={`/support/${t.id}`}><div><strong>#{t.id} · {t.subject}</strong><small>{date(t.updated_at)}</small></div><Status value={t.status}/></Link>)}<Pager result={props.tickets}/></Panel>{!['admin', 'superadmin'].includes(auth?.role || '') && <Panel title="Comment pouvons-nous vous aider ?"><ActionForm action="/support" initial={{ subject: '', body: '' }} fields={[{ name: 'subject', label: 'Sujet', minLength: 5, maxLength: 200 }, { name: 'body', label: 'Votre message', type: 'textarea', minLength: 10 }]} submit="Créer un ticket"/></Panel>}</div>;
    if (page === 'ticket' && props.ticket)
        return <div className="p-support-layout"><aside className="p-panel p-support-list"><h2>Conversations</h2>{props.tickets?.data.map(t=><Link key={t.id} href={`/support/${t.id}`} aria-current={t.id===props.ticket?.id?'page':undefined}>#{t.id} · {t.subject}<small>{date(t.updated_at)}</small><Status value={t.status}/></Link>)}</aside><Panel title={`#${props.ticket.id} · ${props.ticket.subject}`}><Link className="p-text-link" href="/support">← Tous les tickets</Link><div className="p-conversation">{props.messages?.data.map(m => <article className={`p-message ${['admin', 'superadmin'].includes(m.role) ? 'p-message-admin' : ''}`} key={m.id}><div className="p-row"><strong>{m.name}{['admin', 'superadmin'].includes(m.role) && ' · Support'}</strong><small>{date(m.created_at)}</small></div><p>{m.body}</p></article>)}</div><Pager result={props.messages}/><Status value={props.ticket.status}/><ActionForm action={`/support/${props.ticket.id}`} initial={{ body: '', status: props.ticket.status }} fields={[{ name: 'body', label: 'Votre réponse', type: 'textarea' }, { name: 'status', label: 'État après envoi', options: [{ value: 'open', label: 'Ouvert' }, { value: 'closed', label: 'Fermé' }] }]} submit="Envoyer la réponse"/></Panel><aside className="p-panel p-support-customer"><h2>{props.customer ? 'Informations du client' : 'Votre demande'}</h2>{props.customer&&<><strong>{props.customer.name}</strong><p>{props.customer.email}</p><small>Inscription : {date(props.customer.created_at)}</small></>}<p>Ticket #{props.ticket.id}</p><Status value={props.ticket.status}/><p>Vous pouvez fermer ou rouvrir cette conversation lors de votre réponse.</p></aside></div>;
    if (page === 'notifications')
        return <Panel title="L’activité de votre compte" className="p-notifications"><ActionForm action="/notifications/read" initial={{}} fields={[]} disabled={props.unread === 0} submit={props.unread === 0 ? "Tout est lu" : "Tout marquer comme lu"}/>{!props.notifications?.data.length ? <Empty>Vous êtes à jour. Vos prochaines opérations apparaîtront ici.</Empty> : props.notifications.data.map(n => <div className={`p-list-item ${!n.read_at ? 'p-unread' : ''}`} key={n.id}><div><strong>{n.message}</strong><small>{date(n.created_at)}</small></div><Badge tone={n.read_at ? 'neutral' : 'info'}>{n.read_at ? 'Lue' : 'Nouvelle'}</Badge></div>)}<Pager result={props.notifications}/></Panel>;
    if (page === 'profile' && auth)
        return <div className="p-profile-layout"><Panel title="Informations personnelles"><p>Gardez vos coordonnées à jour et protégez l’accès à votre compte.</p><dl className="p-confirm-summary"><div><dt>Pays de résidence</dt><dd>{auth.country_name || 'Non renseigné'}</dd></div><div><dt>Devise du portefeuille</dt><dd>{walletCurrency}</dd></div></dl><ActionForm action="/profile" initial={{ name: auth.name, email: auth.email, current_password: '', password: '', password_confirmation: '' }} fields={[{ name: 'name', label: 'Nom complet', maxLength: 100 }, { name: 'email', label: 'Adresse e-mail', type: 'email' }, { name: 'current_password', label: 'Mot de passe actuel', type: 'password', hint: 'Nécessaire pour confirmer toute modification de votre profil.' }, { name: 'password', label: 'Nouveau mot de passe (facultatif)', type: 'password', required: false, minLength: 10 }, { name: 'password_confirmation', label: 'Confirmer le nouveau mot de passe', type: 'password', required: false }]} submit="Enregistrer mon profil"/></Panel><aside className="p-panel p-profile-note"><span className="p-eyebrow">VOTRE COMPTE</span><div className="p-profile-avatar" aria-hidden="true">{auth.name.slice(0, 1).toUpperCase()}</div><h2>{auth.name}</h2><p>{auth.email}</p><Badge>{['admin', 'superadmin'].includes(auth.role) ? 'Administrateur' : 'Compte trader'}</Badge><hr/><h3>Un accès personnel</h3><p>Utilisez un mot de passe unique d’au moins dix caractères. Ne partagez jamais vos identifiants ni votre code Mobile Money.</p><Link href="/support">Besoin d’aide ? →</Link></aside></div>;
    if (page === 'admin-users')
        return <Panel title="Accès à la plateforme"><p>La suspension bloque les pages privées et les nouvelles opérations. Les droits administrateur sont attribués par la commande serveur.</p><DataTable caption="Utilisateurs et accès" rows={props.users?.data || []} rowKey={u => u.id} columns={[
            { key: 'name', label: 'Utilisateur', render: u => <strong>{u.name}</strong> },
            { key: 'email', label: 'E-mail', render: u => u.email },
            { key: 'role', label: 'Rôle', render: u => <Badge>{u.role === 'superadmin' ? 'Superadmin' : u.role === 'admin' ? 'Administrateur' : 'Trader'}</Badge> },
            { key: 'balance', label: 'Solde / inscription', render: u => <>{formatMinor(u.balance, u.wallet_currency || 'XAF')} {u.wallet_currency || 'XAF'}<small className="p-cell-sub">{date(u.created_at)}</small></> },
            { key: 'access', label: 'Accès', render: u => <Badge tone={u.suspended ? 'danger' : 'success'}>{u.suspended ? 'Suspendu' : 'Actif'}</Badge> },
            { key: 'action', label: 'Action', render: u => u.id !== auth?.id ? <AccessAction user={u}/> : <small>Votre compte</small> },
        ]}/><Pager result={props.users}/></Panel>;
    if (page === 'predictions' || page === 'admin-pipeline')
        return <Pipeline pipeline={props.pipeline} admin={page === 'admin-pipeline'}/>;
    return <Empty>Cette page n’est pas disponible. <Link href="/markets">Retour aux marchés</Link></Empty>;
}
function MarketDetail(props: Props & {
    market: Market;
}) {
    const [orderOpen, setOrderOpen] = useState(false);
    const m = props.market;
    const walletCurrency = props.auth?.wallet_currency || 'XAF';
    const open = m.status === 'open' && new Date(m.closes_at.replace(' ', 'T') + (m.closes_at.includes('T') ? '' : 'Z')) > new Date();
    const order = <PositionOrder market={m} balance={props.balance_exact ?? props.balance} currency={walletCurrency}/>;
    return <><Link className="p-text-link" href={props.adminView ? '/admin/markets' : '/markets'}>← Retour aux marchés</Link><div className="p-two p-detail"><Panel><div className="p-row"><span className="p-category">{m.category}</span><Status value={m.status}/></div><h2>{m.title}</h2><h3>Règles et source de référence</h3><p className="p-preserve">{m.description}</p><p className="p-data-note">Cotes fixes : aucun historique de variation n’est enregistré.</p><dl><dt>Création</dt><dd>{m.created_at ? date(m.created_at) : 'Non disponible'}</dd><dt>Clôture</dt><dd>{date(m.closes_at)}</dd><dt>Volume</dt><dd><ReferenceAmount value={props.volume || 0} baseOnly={props.adminView}/></dd><dt>Participants</dt><dd>{props.participants || 0}</dd></dl>{m.result && <div className="p-success"><strong>Résultat : {m.result === 'void' ? 'Annulation et remboursement' : m.result === 'yes' ? 'Oui' : 'Non'}</strong><p>{m.resolution_source}</p></div>}</Panel>{props.adminView ? <Panel title="Vérification avant décision"><p>Vérifiez que la question est précise, que la source est accessible et que les conditions de résolution sont sans ambiguïté.</p><div className="p-estimate"><span>Cote Oui : ×{(m.yes_odds / 100).toFixed(2)}</span><span>Cote Non : ×{(m.no_odds / 100).toFixed(2)}</span><small>Les décisions de publication et de résolution sont disponibles ci-dessous.</small></div></Panel> : <Panel title={open ? 'Prenez position' : 'Prises de position fermées'}>{open ? <><div className="p-order-desktop">{order}</div><Button className="p-order-mobile" onClick={()=>setOrderOpen(true)}>Prendre position →</Button><Modal variant="sheet" open={orderOpen} title="Votre position" onClose={()=>setOrderOpen(false)}>{orderOpen && order}</Modal></> : <p>{m.status === 'pending' ? 'Cette proposition attend la validation d’un administrateur.' : 'Vous pouvez consulter les conditions et le résultat sur cette fiche.'}</p>}</Panel>}</div>{['admin', 'superadmin'].includes(props.auth?.role || '') && !props.adminView && <Link className="btn btn--outline" href={`/admin/markets/${m.id}`}>Gérer ce marché dans l’administration →</Link>}{props.adminView && ['admin', 'superadmin'].includes(props.auth?.role || '') && <Panel title="Administration du marché">{m.status === 'pending' && <ActionForm action={`/admin/markets/${m.id}/moderate`} initial={{ status: 'open' }} fields={[{ name: 'status', label: 'Décision', options: [{ value: 'open', label: 'Publier' }, { value: 'rejected', label: 'Rejeter' }] }]} submit="Enregistrer la décision"/>}{m.status === 'open' && <ActionForm action={`/admin/markets/${m.id}/resolve`} initial={{ result: 'void', source: '' }} fields={[{ name: 'result', label: 'Résultat officiel', options: [...(!open ? [{ value: 'yes', label: 'Oui' }, { value: 'no', label: 'Non' }] : []), { value: 'void', label: 'Annuler et rembourser' }] }, { name: 'source', label: 'Source du résultat / justification', type: 'textarea', minLength: 10, maxLength: 2000 }]} submit="Résoudre et mettre à jour les soldes"><p>Cette opération est définitive. Avant l’échéance, seule l’annulation avec remboursement est disponible.</p></ActionForm>}{!['open', 'pending'].includes(m.status) && <p>Aucune action restante pour ce marché.</p>}</Panel>}</>;
}
function Pipeline({ pipeline, admin = false }: {
    pipeline?: Props['pipeline'];
    admin?: boolean;
}) {
    const [refreshing, setRefreshing] = useState(false);
    return <div className="p-stack"><Panel title="Des explications pour éclairer votre intuition"><p>Les analyses disponibles apportent un éclairage complémentaire. Elles ne garantissent aucun résultat.</p><Button variant="secondary" loading={refreshing} onClick={() => { setRefreshing(true); router.reload({ only: ['pipeline'], onFinish: () => setRefreshing(false) }); }}>Actualiser le radar</Button>{admin && <ActionForm action="/admin/pipeline/generate" initial={{}} fields={[]} submit="Actualiser les suggestions"><p>Le service Python doit être démarré. Les suggestions ne deviennent pas publiques avant importation et modération.</p></ActionForm>}</Panel>{pipeline?.status !== 'success' ? <Empty>Le service IA est indisponible. Vos marchés et votre portefeuille restent accessibles. Réessayez lorsque le pipeline est démarré.</Empty> : !pipeline.data.length ? <Empty>Le pipeline ne propose pas encore de prédiction.</Empty> : <div className="p-grid">{pipeline.data.map((raw, i) => {
                const item = (raw && typeof raw === 'object' ? raw : {}) as Record<string, unknown>;
                const prediction = (item.prediction && typeof item.prediction === 'object' ? item.prediction : {}) as Record<string, unknown>;
                const text = (v: unknown) => typeof v === 'string' || typeof v === 'number' ? String(v) : 'Non renseigné';
                if (admin)
                    return <Panel key={i} title={text(item.title)}><span className="p-badge">Suggestion IA </span><p>{text(item.description)}</p><small>Échéance : {text(item.expiry)}</small><ActionForm action="/admin/pipeline/import" initial={{ id: text(item.id) }} fields={[]} submit="Importer pour modération"/></Panel>;
                return <Panel key={i} title={`${text(item.home_team)} — ${text(item.away_team)}`}><span className="p-badge">Analyse IA</span><p>Choix du modèle : <strong>{text(prediction.winner)}</strong></p><p>Score de confiance : {text(prediction.confidence)}</p><p>{text(prediction.explanation)}</p></Panel>;
            })}</div>}</div>;
}

function AccessAction({user}:{user:Account & {suspended:boolean}}) { const [open,setOpen]=useState(false);const [busy,setBusy]=useState(false);return <><Button variant="secondary" onClick={()=>setOpen(true)}>{user.suspended?'Réactiver':'Suspendre'}</Button><ConfirmationModal open={open} title={user.suspended?'Réactiver cet accès ?':'Suspendre cet accès ?'} onClose={()=>setOpen(false)} busy={busy} danger={!user.suspended} onConfirm={()=>{setBusy(true);router.post(`/admin/users/${user.id}`,{suspended:!user.suspended},{onFinish:()=>{setBusy(false);setOpen(false);}});}}><p>{user.name} · {user.email}</p><p>{user.suspended?'La personne pourra de nouveau accéder à son compte.':'Cette personne ne pourra plus accéder aux pages privées ni effectuer de nouvelles opérations.'}</p></ConfirmationModal></>; }
