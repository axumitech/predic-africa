import { Link, useForm } from '@inertiajs/react';
import { useId, type ReactNode } from 'react';
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
};
export type Account = {
    id: number;
    name: string;
    email: string;
    role: string;
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
}) { return <div className="p-empty">{children}</div>; }
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
    minLength?: number;
    maxLength?: number;
    options?: {
        value: string;
        label: string;
    }[];
    hint?: string;
};
export function ActionForm({ action, initial, fields, submit, children, freshReference = false, onDone }: {
    action: string;
    initial: Record<string, string>;
    fields: FieldSpec[];
    submit: string;
    children?: ReactNode | ((data: Record<string, string>) => ReactNode);
    freshReference?: boolean;
    onDone?: () => void;
}) {
    const form = useForm(initial);
    const prefix = useId();
    return <form className="p-form login-form" onSubmit={event => {
            event.preventDefault();
            form.post(action, { preserveScroll: true, onSuccess: () => {
                    if (freshReference)
                        form.setData('reference', crypto.randomUUID());
                    for (const field of fields)
                        if (field.type === 'password')
                            form.setData(field.name, '');
                    onDone?.();
                } });
        }}>
        {fields.map(field => <label className="form-group" key={field.name} htmlFor={`${prefix}-${field.name}`}>
            <span>{field.label}</span>
            {field.options ? <select id={`${prefix}-${field.name}`} name={field.name} value={form.data[field.name]} onChange={e => form.setData(field.name, e.target.value)}>{field.options.map(option => <option key={option.value} value={option.value}>{option.label}</option>)}</select>
                : field.type === 'textarea' ? <textarea id={`${prefix}-${field.name}`} name={field.name} value={form.data[field.name] || ''} required={field.required !== false} minLength={field.minLength} maxLength={field.maxLength || 10000} rows={4} onChange={e => form.setData(field.name, e.target.value)}/>
                    : <input id={`${prefix}-${field.name}`} name={field.name} type={field.type || 'text'} required={field.required !== false} min={field.min} max={field.max} minLength={field.minLength} maxLength={field.maxLength || 255} step={field.type === 'number' ? 1 : undefined} autoComplete={field.type === 'password' ? (field.name === 'current_password' || action === '/login' ? 'current-password' : 'new-password') : field.name === 'email' ? 'email' : undefined} value={form.data[field.name] || ''} onChange={e => form.setData(field.name, e.target.value)}/>}
            {field.hint && <small>{field.hint}</small>}
        </label>)}
        {typeof children === 'function' ? children(form.data) : children}
        {Object.keys(form.errors).length > 0 && <div className="p-error" role="alert">{Object.entries(form.errors).map(([key, message]) => <p key={key}>{message}</p>)}</div>}
        <button className="p-button btn btn--primary" disabled={form.processing} type="submit">{form.processing ? 'Enregistrement…' : submit}</button>
    </form>;
}
export function MarketCards({ result }: { result?: Pagination<Market> }) {
    if (!result?.data.length) return <Empty>Aucun marché à afficher. <Link href="/creator">Proposer un marché</Link></Empty>;
    const categoryClass: Record<string, string> = { Sport: 'sports', Politique: 'politics', Musique: 'music', 'Économie': 'economy', Crypto: 'crypto', Société: 'cinema' };
    return <><div className="predictions-grid p-grid">{result.data.map(market => {
        const yes = Math.round(market.no_odds / (market.yes_odds + market.no_odds) * 100);
        return <article className="predict-card" key={market.id}>
            <div className="predict-card__header"><span className={`category-tag category-tag--${categoryClass[market.category] || 'economy'}`}>{market.category}</span><Status value={market.status}/></div>
            <h4 className="predict-card__question"><Link href={`/markets/${market.id}`}>{market.title}</Link></h4>
            <div className="predict-card__odds">{[['Oui', market.yes_odds, yes], ['Non', market.no_odds, 100 - yes]].map(([label, odds, probability]) => <Link href={`/markets/${market.id}`} key={String(label)} className="btn btn--odds"><span className="odds-label">{label} <small>({probability}%)</small></span><span className="odds-val">{(Number(odds) / 100).toFixed(2)}</span></Link>)}</div>
            <div className="predict-card__progress p-market-progress"><div style={{width: `${yes}%`}}/></div>
            <div className="predict-card__meta"><Link href={`/markets/${market.id}`}>Voir le marché</Link><span>Fin : <strong>{date(market.closes_at)}</strong></span></div>
        </article>;
    })}</div><Pager result={result}/></>;
}
