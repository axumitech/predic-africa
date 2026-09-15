import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import OriginalHome from '../features/platform/OriginalHome';
import Layout, { titles } from '../features/platform/Layout';
import { ActionForm, Empty, MarketCards, Pager, Panel, Status, categories, date, money, type Account, type Market, type Pagination } from '../features/platform/ui';
import '../features/platform/platform.css';
type Position = {
    id: number;
    market_id: number;
    title: string;
    outcome: string;
    stake: number;
    odds: number;
    payout: number;
    status: string;
};
type Transaction = {
    id: number;
    kind: string;
    amount: number;
    reference: string;
    created_at: string;
};
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
    positions?: Pagination<Position>;
    transactions?: Pagination<Transaction>;
    tickets?: Pagination<Ticket>;
    ticket?: Ticket;
    messages?: Pagination<Message>;
    notifications?: Pagination<Notification>;
    users?: Pagination<Account & {
        suspended: boolean;
    }>;
    filters?: Record<string, string>;
    stats?: Record<string, number>;
    volume?: number;
    participants?: number;
    pipeline?: {
        status: string;
        data: unknown[];
    };
};
export default function Platform(props: Props) { if (props.page === 'home') return <OriginalHome authenticated={!!props.auth}/>;
    return <Layout {...props}><Content key={props.page + (props.market?.id || props.ticket?.id || '')} {...props}/></Layout>; }
function Content(props: Props) {
    const { page, auth } = props;
    if (page === 'login' || page === 'register')
        return <section className="login-card p-auth"><div className="login-card__badge"><span className="pulse-dot"/>Accès Sécurisé Bourse &amp; Wallet</div><h1 className="login-card__title">{titles[page]}</h1><p className="login-card__subtitle">Identifiez-vous pour négocier sur la Bourse et gérer votre portefeuille.</p><div className="login-tabs"><Link href="/demo" className="login-tab">Mobile Money / Tél · Démo</Link><span className="login-tab active">Email &amp; Mot de passe</span></div><ActionForm action={`/${page}`} initial={{ name: '', email: '', password: '', password_confirmation: '' }} fields={[...(page === 'register' ? [{ name: 'name', label: 'Nom complet', maxLength: 100 }] : []), { name: 'email', label: 'Adresse e-mail', type: 'email' }, { name: 'password', label: 'Mot de passe', type: 'password', minLength: page === 'register' ? 10 : undefined }, ...(page === 'register' ? [{ name: 'password_confirmation', label: 'Confirmer le mot de passe', type: 'password' }] : [])]} submit={page === 'login' ? 'Se connecter' : 'Créer mon compte'}/><p>{page === 'login' ? <Link href="/register">Pas encore de compte ? Inscrivez-vous →</Link> : <Link href="/login">Déjà inscrit ? Connectez-vous →</Link>}</p></section>;
    if (['terms', 'privacy', 'data', 'help'].includes(page))
        return <PublicInfo page={page}/>;
    if (page === 'markets' || page === 'admin-markets')
        return <>{page === 'markets' && <div className="analytics-summary">{[['volume', 'Volume Global'], ['active', 'Positions Actives'], ['paid', 'Investisseurs payés']].map(([key, title]) => <div className="stat-card" key={key}><div className="stat-card__icon text--gold"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="9"/><path d="M8 12h8M12 8v8"/></svg></div><div className="stat-card__info"><span className="stat-title">{title}</span><span className="stat-value">{money(props.stats?.[key] || 0)}{key !== 'active' && ' crédits'}</span></div></div>)}</div>}<p className="p-intro">{page === 'markets' ? 'Des questions claires. Des cotes fixes. À vous de choisir.' : 'Examinez les propositions et enregistrez les résultats depuis la fiche de chaque marché.'}</p><MarketFilters filters={props.filters} admin={page === 'admin-markets'}/><MarketCards result={props.markets}/></>;
    if (page === 'market' && props.market)
        return <MarketDetail {...props} market={props.market}/>;
    if (page === 'creator')
        return <div className="p-stack"><Panel title="Proposez une question à la communauté"><p>Un marché doit être vérifiable. Précisez les conditions du Oui, du Non et une source de référence. Chaque proposition est soumise à modération.</p><ActionForm action="/markets" initial={{ title: '', description: '', category: 'Sport', closes_at: '', yes_odds: '200', no_odds: '200' }} fields={[
                { name: 'title', label: 'Question du marché', minLength: 10, maxLength: 200 }, { name: 'description', label: 'Règles de résolution et source', type: 'textarea', minLength: 20, maxLength: 5000 },
                { name: 'category', label: 'Catégorie', options: categories.map(c => ({ value: c, label: c })) }, { name: 'closes_at', label: 'Clôture (UTC)', type: 'datetime-local', hint: 'La date est enregistrée en UTC. Les fiches affichent ensuite votre heure locale.' },
                { name: 'yes_odds', label: 'Cote Oui en centièmes', type: 'number', min: 101, max: 1000, hint: '200 = cote ×2,00. Maximum ×10.' }, { name: 'no_odds', label: 'Cote Non en centièmes', type: 'number', min: 101, max: 1000 },
            ]} submit="Soumettre à la modération"/></Panel><h2>Mes propositions</h2><MarketCards result={props.markets}/></div>;
    if (page === 'positions')
        return <Panel title="Votre carnet de positions"><p>Les cotes sont verrouillées à l’achat. Le retour potentiel inclut votre mise. Les positions restent engagées jusqu’à la résolution ; il n’y a pas de revente dans ce mode.</p>{!props.positions?.data.length ? <Empty>Vous n’avez pas encore de position. <Link href="/markets">Explorer les marchés →</Link></Empty> : <div className="p-table-wrap table-responsive"><table className="data-table" role="table"><thead><tr role="row"><th scope="col" role="columnheader">Marché</th><th scope="col" role="columnheader">Choix</th><th scope="col" role="columnheader">Mise</th><th scope="col" role="columnheader">Cote</th><th scope="col" role="columnheader">Retour potentiel</th><th scope="col" role="columnheader">Statut</th></tr></thead><tbody>{props.positions.data.map(p => <tr role="row" key={p.id}><td role="cell" data-label="Marché"><Link href={`/markets/${p.market_id}`}>{p.title}</Link></td><td role="cell" data-label="Choix">{p.outcome === 'yes' ? 'Oui' : 'Non'}</td><td role="cell" data-label="Mise">{money(p.stake)}</td><td role="cell" data-label="Cote">×{(p.odds / 100).toFixed(2)}</td><td role="cell" data-label="Retour potentiel">{money(p.payout)}</td><td role="cell" data-label="Statut"><Status value={p.status}/></td></tr>)}</tbody></table></div>}<Pager result={props.positions}/></Panel>;
    if (page === 'wallet' || page === 'transactions')
        return <div className="p-stack">{page === 'wallet' && <div className="p-two"><div className="p-balance-card wallet-widget"><small>VOTRE SOLDE DISPONIBLE</small><h2>{money(props.balance)} <span>crédits</span></h2><p>Ces crédits servent uniquement à tester la plateforme. Ils n’ont aucune valeur monétaire et ne sont pas convertibles.</p></div><Panel title="Tester un dépôt ou un retrait"><ActionForm action="/wallet" initial={{ kind: 'deposit', amount: '1000', reference: crypto.randomUUID() }} freshReference fields={[{ name: 'kind', label: 'Opération', options: [{ value: 'deposit', label: 'Dépôt simulé' }, { value: 'withdraw', label: 'Retrait simulé' }] }, { name: 'amount', label: 'Montant en crédits', type: 'number', min: 100, max: 1000000 }]} submit="Confirmer la simulation"/></Panel></div>}<Transactions result={props.transactions}/></div>;
    if (page === 'support')
        return <div className="p-two"><Panel title={auth?.role === 'admin' ? 'Tous les tickets' : 'Mes demandes'}>{!props.tickets?.data.length ? <Empty>Aucune demande pour le moment.</Empty> : props.tickets.data.map(t => <Link className="p-list-item" key={t.id} href={`/support/${t.id}`}><div><strong>#{t.id} · {t.subject}</strong><small>{date(t.updated_at)}</small></div><Status value={t.status}/></Link>)}<Pager result={props.tickets}/></Panel><Panel title="Comment pouvons-nous vous aider ?"><ActionForm action="/support" initial={{ subject: '', body: '' }} fields={[{ name: 'subject', label: 'Sujet', minLength: 5, maxLength: 200 }, { name: 'body', label: 'Votre message', type: 'textarea', minLength: 10 }]} submit="Créer un ticket"/></Panel></div>;
    if (page === 'ticket' && props.ticket)
        return <Panel title={`#${props.ticket.id} · ${props.ticket.subject}`}><Link href="/support">← Tous les tickets</Link><div className="p-conversation">{props.messages?.data.map(m => <article className={`p-message ${m.role === 'admin' ? 'p-message-admin' : ''}`} key={m.id}><div className="p-row"><strong>{m.name}{m.role === 'admin' && ' · Support'}</strong><small>{date(m.created_at)}</small></div><p>{m.body}</p></article>)}</div><Pager result={props.messages}/><Status value={props.ticket.status}/><ActionForm action={`/support/${props.ticket.id}`} initial={{ body: '', status: props.ticket.status }} fields={[{ name: 'body', label: 'Votre réponse', type: 'textarea' }, { name: 'status', label: 'État après envoi', options: [{ value: 'open', label: 'Ouvert' }, { value: 'closed', label: 'Fermé' }] }]} submit="Envoyer la réponse"/></Panel>;
    if (page === 'notifications')
        return <Panel title="L’activité de votre compte"><ActionForm action="/notifications/read" initial={{}} fields={[]} submit="Tout marquer comme lu"/>{!props.notifications?.data.length ? <Empty>Vous êtes à jour. Vos prochaines opérations apparaîtront ici.</Empty> : props.notifications.data.map(n => <div className={`p-list-item ${!n.read_at ? 'p-unread' : ''}`} key={n.id}><div><strong>{n.message}</strong><small>{date(n.created_at)}</small></div><span>{n.read_at ? 'Lue' : 'Nouvelle'}</span></div>)}<Pager result={props.notifications}/></Panel>;
    if (page === 'profile' && auth)
        return <Panel title="Informations personnelles"><ActionForm action="/profile" initial={{ name: auth.name, email: auth.email, current_password: '', password: '', password_confirmation: '' }} fields={[{ name: 'name', label: 'Nom complet', maxLength: 100 }, { name: 'email', label: 'Adresse e-mail', type: 'email' }, { name: 'current_password', label: 'Mot de passe actuel', type: 'password' }, { name: 'password', label: 'Nouveau mot de passe (facultatif)', type: 'password', required: false, minLength: 10 }, { name: 'password_confirmation', label: 'Confirmer le nouveau mot de passe', type: 'password', required: false }]} submit="Enregistrer mon profil"/></Panel>;
    if (page === 'admin')
        return <><div className="p-grid">{[['users', 'Utilisateurs', '/admin/users'], ['pending', 'Marchés à modérer', '/admin/markets?status=pending'], ['volume', 'Crédits engagés', '/admin/markets'], ['tickets', 'Tickets ouverts', '/support']].map(([key, title, href]) => <Link className="p-stat" href={href} key={key}><small>{title}</small><strong>{money(props.stats?.[key] || 0)}</strong><span>Consulter →</span></Link>)}</div><Panel title="Intégrité comptable"><p>Somme des écritures du journal : <strong>{money(props.stats?.ledger_balance || 0)}</strong>. Le journal doit rester équilibré à zéro.</p><p>La résolution crédite les gagnants une seule fois. L’annulation d’un marché rembourse les mises engagées.</p></Panel></>;
    if (page === 'admin-users')
        return <Panel title="Accès à la plateforme"><p>La suspension bloque les pages privées et les nouvelles opérations. Les droits administrateur sont attribués par la commande serveur.</p><div className="p-table-wrap table-responsive"><table className="data-table" role="table"><thead><tr role="row"><th scope="col" role="columnheader">Utilisateur</th><th scope="col" role="columnheader">E-mail</th><th scope="col" role="columnheader">Rôle</th><th scope="col" role="columnheader">Accès</th><th scope="col" role="columnheader">Action</th></tr></thead><tbody>{props.users?.data.map(u => <tr role="row" key={u.id}><td role="cell" data-label="Utilisateur">{u.name}</td><td role="cell" data-label="E-mail">{u.email}</td><td role="cell" data-label="Rôle">{u.role}</td><td role="cell" data-label="Accès">{u.suspended ? 'Suspendu' : 'Actif'}</td><td role="cell" data-label="Action">{u.id !== auth?.id && <button className="p-button p-secondary btn btn--outline" onClick={() => router.post(`/admin/users/${u.id}`, { suspended: !u.suspended })}>{u.suspended ? 'Réactiver' : 'Suspendre'}</button>}</td></tr>)}</tbody></table></div><Pager result={props.users}/></Panel>;
    if (page === 'predictions' || page === 'admin-pipeline')
        return <Pipeline pipeline={props.pipeline} admin={page === 'admin-pipeline'}/>;
    return <Empty>Cette page n’est pas disponible. <Link href="/markets">Retour aux marchés</Link></Empty>;
}
function MarketFilters({ filters = {}, admin }: { filters?: Record<string, string>; admin: boolean }) {
    const [q, setQ] = useState(filters.q || '');
    const [category, setCategory] = useState(filters.category || '');
    const [status, setStatus] = useState(filters.status || '');
    const search = (selected = category) => router.get(admin ? '/admin/markets' : '/markets', { q, category: selected, status }, { preserveState: true });
    return <>
        <div className="categories-bar">{['', ...categories].map(c => <button type="button" key={c} className={`category-pill ${category === c ? 'active' : ''}`} onClick={() => { setCategory(c); search(c); }}>{c || 'Toutes'}</button>)}</div>
        <form className="p-filters" onSubmit={event => { event.preventDefault(); search(); }}>
            <input aria-label="Rechercher un marché" placeholder="Rechercher un marché…" value={q} maxLength={100} onChange={e => setQ(e.target.value)}/>
            <select aria-label="Statut" value={status} onChange={e => setStatus(e.target.value)}><option value="">Tous les statuts</option>{(admin ? ['pending', 'open', 'rejected', 'resolved', 'void'] : ['open', 'resolved', 'void']).map(s => <option key={s} value={s}>{({ pending: 'À modérer', open: 'Ouvert', rejected: 'Rejeté', resolved: 'Résolu', void: 'Annulé' })[s]}</option>)}</select>
            <button className="btn btn--primary">Rechercher</button>
        </form>
    </>;
}
function MarketDetail(props: Props & {
    market: Market;
}) {
    const m = props.market;
    const open = m.status === 'open' && new Date(m.closes_at.replace(' ', 'T') + (m.closes_at.includes('T') ? '' : 'Z')) > new Date();
    return <><Link href="/markets">← Retour aux marchés</Link><div className="p-two p-detail"><Panel><div className="p-row"><span className="p-category">{m.category}</span><Status value={m.status}/></div><h2>{m.title}</h2><p className="p-preserve">{m.description}</p><dl><dt>Clôture</dt><dd>{date(m.closes_at)}</dd><dt>Volume</dt><dd>{money(props.volume || 0)} crédits</dd><dt>Participants</dt><dd>{props.participants || 0}</dd></dl>{m.result && <div className="p-success"><strong>Résultat : {m.result === 'void' ? 'Annulation et remboursement' : m.result === 'yes' ? 'Oui' : 'Non'}</strong><p>{m.resolution_source}</p></div>}</Panel><Panel title={open ? 'Prenez position' : 'Prises de position fermées'}>{open ? <ActionForm action={`/markets/${m.id}/positions`} initial={{ outcome: 'yes', amount: '100', reference: crypto.randomUUID() }} fields={[{ name: 'outcome', label: 'Votre conviction', options: [{ value: 'yes', label: `Oui · ×${(m.yes_odds / 100).toFixed(2)}` }, { value: 'no', label: `Non · ×${(m.no_odds / 100).toFixed(2)}` }] }, { name: 'amount', label: 'Mise en crédits', type: 'number', min: 100, max: 1000000 }]} submit="Confirmer ma position">{data => <div className="p-estimate"><span>Retour potentiel (mise incluse)</span><strong>{money(Math.floor((Number(data.amount) || 0) * (data.outcome === 'yes' ? m.yes_odds : m.no_odds) / 100))} crédits</strong><small>Solde disponible : {money(props.balance)} crédits. Mise engagée jusqu’à la résolution.</small></div>}</ActionForm> : <p>{m.status === 'pending' ? 'Cette proposition attend la validation d’un administrateur.' : 'Vous pouvez consulter les conditions et le résultat sur cette fiche.'}</p>}</Panel></div>{props.auth?.role === 'admin' && <Panel title="Administration du marché">{m.status === 'pending' && <ActionForm action={`/admin/markets/${m.id}/moderate`} initial={{ status: 'open' }} fields={[{ name: 'status', label: 'Décision', options: [{ value: 'open', label: 'Publier' }, { value: 'rejected', label: 'Rejeter' }] }]} submit="Enregistrer la décision"/>}{m.status === 'open' && <ActionForm action={`/admin/markets/${m.id}/resolve`} initial={{ result: 'void', source: '' }} fields={[{ name: 'result', label: 'Résultat officiel', options: [...(!open ? [{ value: 'yes', label: 'Oui' }, { value: 'no', label: 'Non' }] : []), { value: 'void', label: 'Annuler et rembourser' }] }, { name: 'source', label: 'Source du résultat / justification', type: 'textarea', minLength: 10, maxLength: 2000 }]} submit="Résoudre et mettre à jour les soldes"><p>Cette opération est définitive. Avant l’échéance, seule l’annulation avec remboursement est disponible.</p></ActionForm>}{!['open', 'pending'].includes(m.status) && <p>Aucune action restante pour ce marché.</p>}</Panel>}</>;
}
function Transactions({ result }: {
    result?: Pagination<Transaction>;
}) { return <Panel title="Journal de vos opérations" className="table-container">{!result?.data.length ? <Empty>Aucune transaction. <Link href="/wallet">Ajouter des crédits de démonstration →</Link></Empty> : <div className="p-table-wrap table-responsive"><table className="data-table" role="table"><thead><tr role="row"><th scope="col" role="columnheader">Date</th><th scope="col" role="columnheader">Opération</th><th scope="col" role="columnheader">Crédits</th><th scope="col" role="columnheader">Référence</th></tr></thead><tbody>{result.data.map(t => <tr role="row" key={t.id}><td role="cell" data-label="Date">{date(t.created_at)}</td><td role="cell" data-label="Opération">{t.kind}</td><td role="cell" data-label="Crédits">{['Mise', 'Retrait simulé'].includes(t.kind) ? '−' : '+'}{money(t.amount)}</td><td role="cell" data-label="Référence"><code>{t.reference}</code></td></tr>)}</tbody></table></div>}<Pager result={result}/></Panel>; }
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
function PublicInfo({ page }: {
    page: string;
}) {
    const sections: Record<string, [
        string,
        string
    ][]> = {
        help: [['1. Créez votre compte', 'Inscrivez-vous avec votre nom, votre adresse e-mail et un mot de passe de dix caractères minimum. Votre compte démarre à zéro crédit.'], ['2. Ajoutez des crédits', 'Dans Portefeuille, effectuez un dépôt simulé de 100 à 1 000 000 crédits. Aucun opérateur de paiement et aucun compte bancaire ne sont sollicités.'], ['3. Choisissez un marché', 'Consultez les règles et la clôture. Choisissez Oui ou Non, saisissez votre mise et vérifiez le retour potentiel. La cote est fixe ; la mise est engagée jusqu’à la résolution.'], ['4. Consultez le résultat', 'Après la clôture, un administrateur renseigne une source et un résultat. Les gagnants reçoivent le retour prévu, mise incluse. Un marché annulé entraîne le remboursement des mises.'], ['5. Besoin d’assistance ?', 'Créez un ticket dans Support client. Vous pourrez suivre la conversation, répondre et fermer ou rouvrir votre demande.']],
        terms: [['Objet', 'Cette version est un environnement de démonstration de marchés à cote fixe. Les crédits sont fictifs, gratuits, non transférables et sans valeur monétaire.'], ['Règles des positions', 'Les positions Oui/Non sont acceptées uniquement sur un marché publié avant son échéance. Le retour potentiel est calculé selon la cote au moment de la mise, arrondi à l’entier inférieur, sans commission. Aucune revente ni clôture anticipée individuelle n’est proposée.'], ['Résolution', 'Un administrateur enregistre le résultat après l’échéance et sa justification. Le gain inclut la mise initiale. Une annulation rembourse les mises. Les litiges peuvent être signalés au support.'], ['Statut du service', 'Ces informations décrivent la démonstration technique. Les conditions commerciales et les mentions de l’exploitant doivent être définies avant une ouverture publique.']],
        privacy: [['Données enregistrées', 'L’application conserve votre nom, votre e-mail, un mot de passe haché, vos sessions, marchés, positions, opérations et conversations de support.'], ['Utilisation et visibilité', 'Ces données permettent de gérer votre compte et vos demandes. Les administrateurs accèdent aux comptes et aux tickets ; les autres traders n’accèdent pas à vos opérations privées. Les descriptions de marchés publiés sont visibles par les utilisateurs connectés.'], ['Services externes', 'Les polices et certaines icônes de l’interface historique sont chargées depuis Google Fonts et cdnjs. Le pipeline de démonstration est interrogé par le serveur. Aucun paiement réel n’est envoyé.'], ['Demandes', 'Pour demander une copie ou la suppression de vos données, ouvrez un ticket depuis votre compte. Les règles de conservation et les coordonnées de l’exploitant restent à définir avant lancement public.']],
        data: [['Consulter et corriger', 'Votre nom et votre e-mail peuvent être modifiés dans Mon profil avec votre mot de passe actuel. Vos positions et votre journal sont consultables dans les pages correspondantes.'], ['Copie et suppression', 'Adressez une demande via Support client pour une copie de vos données ou la suppression de votre compte. Ces demandes sont traitées manuellement ; cette version ne promet pas de suppression automatique.'], ['Séparation du prototype', 'La démonstration historique /demo utilise encore le stockage local du navigateur. L’espace principal utilise la base serveur ; les anciens soldes fictifs ne sont pas importés.']],
    };
    return <article className="p-document"><small>PREDICAFRICA · DÉMONSTRATION</small><h1>{titles[page]}</h1>{sections[page].map(([title, body]) => <section key={title}><h2>{title}</h2><p>{body}</p></section>)}<Link className="p-button btn btn--primary" href="/markets">Accéder à mon espace →</Link></article>;
}
