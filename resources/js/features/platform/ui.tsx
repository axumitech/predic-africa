import { Link, useForm, usePage } from '@inertiajs/react';
import { useId, useState, useRef, type ReactNode } from 'react';
import { Button, ConfirmationModal } from '../../components/ui';
import { ReferenceAmount, useCurrency, formatDecimal } from './currency';
export type Pagination<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
export type Market = {
    id: number;
    title: string;
    description: string;
    category: string;
    status: string;
    closes_at: string;
    yes_odds: number;
    no_odds: number;
    result: string | null;
    resolution_source: string | null;
    volume?: number;
    participants?: number;
    creator_name?: string;
    created_at?: string;
    updated_at?: string;
};
export type Account = {
    id: number;
    name: string;
    email: string;
    role: string;
    country?: string | null;
    country_name?: string | null;
    wallet_currency?: string;
};
export const categories = ['Sport', 'Politique', 'Musique', 'Économie', 'Crypto', 'Société'];
export const money = (value: number) => new Intl.NumberFormat('fr-FR').format(value);
export const date = (value: string) => new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value.includes('T') ? value : value.replace(' ', 'T') + 'Z'));
export const labels: Record<string, string> = { pending: 'À modérer', open: 'Ouvert', rejected: 'Rejeté', resolved: 'Résolu', void: 'Annulé', won: 'Gagnée', lost: 'Perdue', refunded: 'Remboursée', closed: 'Fermé', yes: 'Oui', no: 'Non' };
export function Status({ value }: {
    value: string;
}) { return <span className={`p-badge p-badge-${value}`}>{labels[value] || value}</span>; }
export function Empty({ children }: {
    children: ReactNode;
}) { return <div className="p-empty"><span className="p-empty-mark" aria-hidden="true">◇</span><div>{children}</div></div>; }
export function Pager({ result }: {
    result?: Pagination<unknown>;
}) {
    if (!result || result.last_page < 2)
        return null;
    return <nav className="p-pagination" aria-label="Pagination">{result.prev_page_url && <Link href={result.prev_page_url}>← Précédent</Link>}<span>Page {result.current_page} / {result.last_page}</span>{result.next_page_url && <Link href={result.next_page_url}>Suivant →</Link>}</nav>;
}
export function Panel({ title, children, className = 'creator-card' }: {
    title?: string;
    children: ReactNode;
    className?: string;
}) { return <section className={`p-panel ${className}`}>{title && <h2>{title}</h2>}{children}</section>; }
export type FieldSpec = {
    name: string;
    label: string;
    type?: string;
    required?: boolean;
    min?: number;
    max?: number;
    step?: number | string;
    minLength?: number;
    maxLength?: number;
    options?: {
        value: string;
        label: string;
    }[];
    hint?: string;
};
export function ActionForm({ action, initial, fields, submit, children, freshReference = false, onDone, disabled = false }: {
    action: string;
    initial: Record<string, string>;
    fields: FieldSpec[];
    submit: string;
    children?: ReactNode | ((data: Record<string, string>) => ReactNode);
    freshReference?: boolean;
    onDone?: () => void;
    disabled?: boolean;
}) {
    const form = useForm(initial);
    const {auth} = usePage<{auth: Account | null}>().props;
    const prefix = useId();
    const [confirm, setConfirm] = useState(false);
    const element = useRef<HTMLFormElement>(null);
    const sensitive = action === '/wallet/ndsgpay' || action === '/markets' || /\/(positions|moderate|resolve)$/.test(action);
    const send = () => {
        if (form.processing) return;
        form.post(action, { preserveScroll: true,
            onError: errors => { setConfirm(false); requestAnimationFrame(() => { const first = fields.find(f => errors[f.name]); if (first) element.current?.querySelector<HTMLElement>(`[name="${first.name}"]`)?.focus(); }); },
            onSuccess: () => { setConfirm(false); if (freshReference) form.setData('reference', crypto.randomUUID()); for (const field of fields) if (field.type === 'password') form.setData(field.name, ''); onDone?.(); }
        });
    };
    const [visible, setVisible] = useState<Record<string, boolean>>({});
    return <><form ref={element} className="p-form" aria-busy={form.processing} onSubmit={event => {
        event.preventDefault(); if (form.processing || disabled) return; if (sensitive) setConfirm(true); else send();
    }}>
        {fields.map(field => {
            const id = `${prefix}-${field.name}`;
            const error = form.errors[field.name];
            const describedBy = [field.hint ? `${id}-hint` : '', error ? `${id}-error` : ''].filter(Boolean).join(' ') || undefined;
            return <div className={`form-group ${field.type === 'textarea' ? 'form-group--wide' : ''}`} key={field.name}>
                <label htmlFor={id}>{field.label}{field.required === false && !field.label.includes('facultatif') && <small> Facultatif</small>}</label>
                {field.options ? <select id={id} name={field.name} aria-invalid={!!error} aria-describedby={describedBy} value={form.data[field.name]} onChange={e => form.setData(field.name, e.target.value)}>{field.options.map(option => <option key={option.value} value={option.value}>{option.label}</option>)}</select>
                    : field.type === 'textarea' ? <textarea id={id} name={field.name} aria-invalid={!!error} aria-describedby={describedBy} value={form.data[field.name] || ''} required={field.required !== false} minLength={field.minLength} maxLength={field.maxLength || 10000} rows={5} onChange={e => form.setData(field.name, e.target.value)}/>
                    : <div className={field.type === 'password' ? 'p-password-field' : 'p-input-field'}>
                        <input id={id} name={field.name} aria-invalid={!!error} aria-describedby={describedBy} type={field.type === 'password' && visible[field.name] ? 'text' : field.type || 'text'} inputMode={field.type === 'number' ? (field.step === '0.01' ? 'decimal' : 'numeric') : field.type === 'tel' ? 'tel' : field.type === 'email' ? 'email' : undefined} required={field.required !== false} min={field.min} max={field.max} minLength={field.minLength} maxLength={field.maxLength || 255} step={field.type === 'number' ? field.step || 1 : undefined} autoComplete={field.type === 'password' ? (field.name === 'current_password' ? 'current-password' : 'new-password') : field.name === 'email' ? 'email' : field.name === 'name' ? 'name' : field.type === 'tel' ? 'tel' : undefined} value={form.data[field.name] || ''} onChange={e => form.setData(field.name, e.target.value)}/>
                        {field.type === 'password' && <button type="button" aria-label={`${visible[field.name] ? 'Masquer' : 'Afficher'} : ${field.label}`} aria-pressed={!!visible[field.name]} onClick={() => setVisible(v => ({ ...v, [field.name]: !v[field.name] }))}>{visible[field.name] ? 'Masquer' : 'Afficher'}</button>}
                    </div>}
                {field.hint && <small id={`${id}-hint`}>{field.hint}</small>}
                {error && <small id={`${id}-error`} className="p-field-error" role="alert">{error}</small>}
            </div>;
        })}
        {typeof children === 'function' ? children(form.data) : children}
        {Object.keys(form.errors).length > 0 && <div className="p-error" role="alert"><strong>Vérifiez les informations saisies.</strong>{Object.entries(form.errors).filter(([key]) => !fields.some(f => f.name === key)).map(([key, message]) => <p key={key}>{message}</p>)}</div>}
        <Button className="p-form-submit" disabled={disabled} loading={form.processing} type="submit">{form.processing ? 'Enregistrement…' : submit}</Button>
    </form><ConfirmationModal open={confirm} onClose={() => setConfirm(false)} onConfirm={send} busy={form.processing} confirmLabel="Valider définitivement" danger={action.endsWith('/resolve')}>
        <p>{submit}</p><dl className="p-confirm-summary">{fields.filter(f=>f.type!=='password').map(f=><div key={f.name}><dt>{f.label}</dt><dd>{f.options?.find(o=>o.value===form.data[f.name])?.label || form.data[f.name] || '—'}</dd></div>)}</dl>
        {typeof children === 'function' ? children(form.data) : children}
        {action === '/wallet/ndsgpay' && <p>Cette opération transfère de l’argent réel en {auth?.wallet_currency || 'XAF'} via NDSGPay. Vérifiez le numéro et le montant.</p>}
    </ConfirmationModal></>;
}
export function MarketCards({ result, admin = false }: { result?: Pagination<Market>; admin?: boolean }) {
    if (!result?.data.length && admin) return <Empty>Aucun marché ne correspond à ces filtres.</Empty>;
    if (!result?.data.length) return <Empty>Aucun marché à afficher. <Link href="/creator">Proposer un marché</Link></Empty>;
    return <><div className="predictions-grid p-grid">{result.data.map(market => <MarketCard key={market.id} market={market} admin={admin}/>)}</div><Pager result={result}/></>;
}
export function MarketCard({market,admin=false}:{market:Market;admin?:boolean}) {
    const {fx,fresh} = useCurrency();
    const {auth} = usePage<{auth: Account | null}>().props;
    const storageKey = `pa-favorites-${auth?.id || 'guest'}`;
    const [favorite,setFavorite] = useState(()=>{try {const value=JSON.parse(localStorage.getItem(storageKey)||'[]');return Array.isArray(value)&&value.includes(market.id);} catch {return false;}});
    const toggle = ()=>{try {const value=JSON.parse(localStorage.getItem(storageKey)||'[]');const ids=new Set<number>(Array.isArray(value)?value:[]);if(favorite)ids.delete(market.id);else ids.add(market.id);localStorage.setItem(storageKey,JSON.stringify([...ids]));setFavorite(!favorite);} catch {setFavorite(!favorite);}};
    const categoryClass: Record<string, string> = { Sport: 'sports', Politique: 'politics', Musique: 'music', 'Économie': 'economy', Crypto: 'crypto', Société: 'cinema' };
        const yes = Math.round(market.no_odds / (market.yes_odds + market.no_odds) * 100);
        return <article className="predict-card" key={market.id}>
            <div className="predict-card__header"><span className={`category-tag category-tag--${categoryClass[market.category] || 'economy'}`}>{market.category}</span><Status value={market.status}/>{!admin&&<button className="p-favorite" aria-label={`${favorite?'Retirer des':'Ajouter aux'} favoris locaux : ${market.title}`} aria-pressed={favorite} onClick={toggle}>{favorite?'★':'☆'}</button>}</div>
            <h4 className="predict-card__question"><Link href={`${admin ? '/admin' : ''}/markets/${market.id}`}>{market.title}</Link></h4>
            <div className="predict-card__odds">{[['Oui', market.yes_odds, yes], ['Non', market.no_odds, 100 - yes]].map(([label, odds, probability]) => <Link href={`${admin ? '/admin' : ''}/markets/${market.id}`} key={String(label)} className="btn btn--odds"><span className="odds-label">{label} <small>({probability}%)</small></span><span className="odds-val">{(Number(odds) / 100).toFixed(2)}</span></Link>)}</div>
            {!admin && <p className="p-market-minimum">Mise minimale : {fresh && fx?.min_stake ? `${formatDecimal(fx.min_stake, fx.currency)} ${fx.currency}` : `Indisponible${fx?.currency ? ' en ' + fx.currency : ''}`}</p>}
            <div className="predict-card__progress p-market-progress"><div style={{width: `${yes}%`}}/></div>
            {market.volume !== undefined && <div className="p-market-facts"><span><ReferenceAmount value={market.volume||0} baseOnly={admin}/> engagés</span><span>{market.participants||0} participant(s)</span></div>}<div className="predict-card__meta"><Link href={`${admin ? '/admin' : ''}/markets/${market.id}`}>Voir le marché</Link><span>Fin : <strong>{date(market.closes_at)}</strong></span></div>
        </article>;
    }
