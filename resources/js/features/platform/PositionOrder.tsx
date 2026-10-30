import { useForm } from '@inertiajs/react';
import { useEffect, useId, useRef, useState } from 'react';
import { Button, ConfirmationModal } from '../../components/ui';
import { formatRate, currencyScale, formatMinor, formatDecimal, useCurrency, type Minor } from './currency';
import type { Market } from './ui';

type Quote = { id: string; currency: string; outcome: string; odds: number; amount: string; payout: string; base_amount: string; rate: string; as_of: string; expires_at: string; server_time: string };
const displayDecimal = formatDecimal;
export default function PositionOrder({ market, balance, currency }: { market: Market; balance: Minor; currency: string }) {
    const { fx, fresh } = useCurrency();
    const form = useForm({ amount: currency === 'XAF' ? '100' : fx?.min_stake || '', outcome: 'yes', reference: crypto.randomUUID(), quote_id: '' });
    const [quote, setQuote] = useState<Quote | null>(null);
    const [loading, setLoading] = useState(false);
    const [remaining, setRemaining] = useState(0);
    const [error, setError] = useState('');
    const input = useRef<HTMLInputElement>(null);
    const pending = useRef<AbortController | null>(null);
    const expiry = useRef(0);
    const id = useId();
    const supported = currency === 'XAF' || fresh;
    useEffect(() => {
        if (!form.data.amount && fx?.min_stake) form.setData('amount', fx.min_stake);
    }, [fx?.min_stake]);
    useEffect(() => () => pending.current?.abort(), []);
    useEffect(() => {
        if (!quote) return;
        const timer = window.setInterval(() => setRemaining(Math.max(0, Math.ceil((expiry.current - performance.now()) / 1000))), 250);
        return () => clearInterval(timer);
    }, [quote]);
    const prepare = async () => {
        if (loading || form.processing) return;
        setLoading(true); setError(''); form.clearErrors();
        pending.current?.abort(); pending.current = new AbortController();
        try {
            const cookie = document.cookie.split('; ').find(value => value.startsWith('XSRF-TOKEN='));
            const token = cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(`/markets/${market.id}/quote`, { method: 'POST', credentials: 'same-origin', signal: pending.current.signal,
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', [cookie ? 'X-XSRF-TOKEN' : 'X-CSRF-TOKEN']: token, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ amount: form.data.amount, outcome: form.data.outcome, reference: form.data.reference }) });
            if (!response.ok) {
                const body = await response.json().catch(() => ({}));
                const errors = body.errors ? Object.values(body.errors).flat().join(' ') : response.status === 419 || response.status === 401 ? 'Votre session a expiré. Reconnectez-vous avant de continuer.' : 'La confirmation est indisponible. Réessayez.';
                throw new Error(errors);
            }
            const value: Quote = await response.json();
            form.setData('quote_id', value.id);
            expiry.current = performance.now() + Math.max(0, Date.parse(value.expires_at) - Date.parse(value.server_time));
            setRemaining(Math.max(0, Math.ceil((expiry.current - performance.now()) / 1000)));
            setQuote(value);
        } catch (failure) {
            if (!(failure instanceof DOMException && failure.name === 'AbortError')) {
                setQuote(null);
                setError(failure instanceof Error ? failure.message : 'Vérification indisponible. Réessayez.');
                input.current?.focus();
            }
        } finally { setLoading(false); }
    };
    const confirm = () => {
        if (!quote || remaining <= 0 || form.processing) return;
        // Inertia's form contains only the native amount, intent and opaque server quote ID.
        form.post(`/markets/${market.id}/positions`, { preserveScroll: true,
            onError: () => { setQuote(null); requestAnimationFrame(() => input.current?.focus()); } });
    };
    return <><form className="p-form p-native-order" onSubmit={event => { event.preventDefault(); if (supported) void prepare(); }}>
        <div className="form-group"><label htmlFor={`${id}-outcome`}>Votre conviction</label><select id={`${id}-outcome`} value={form.data.outcome} onChange={event => form.setData('outcome', event.target.value)}><option value="yes">Oui · ×{(market.yes_odds / 100).toFixed(2)}</option><option value="no">Non · ×{(market.no_odds / 100).toFixed(2)}</option></select></div>
        <div className="form-group"><label htmlFor={`${id}-amount`}>Mise en {currency}</label><input ref={input} id={`${id}-amount`} name="amount" type="number" inputMode={currencyScale(currency) ? 'decimal' : 'numeric'} required step={currencyScale(currency) ? '0.01' : '1'} min={fx?.min_stake || undefined} max={fx?.max_stake || undefined} value={form.data.amount} onChange={event => { form.setData('amount', event.target.value); form.clearErrors(); setError(''); }} aria-describedby={`${id}-hint`} aria-invalid={!!form.errors.amount}/><small id={`${id}-hint`}>{supported && fx?.min_stake ? `De ${displayDecimal(fx.min_stake, currency)} à ${displayDecimal(fx.max_stake!, currency)} ${currency}.` : 'Un taux de change récent est nécessaire pour confirmer.'}</small></div>
        <div className="p-estimate"><span>Solde disponible</span><strong>{formatMinor(balance, currency)} {currency}</strong><small>La confirmation précisera le gain potentiel, mise incluse. Votre mise reste engagée jusqu’au résultat.</small></div>
        {(error || Object.keys(form.errors).length > 0) && <div className="p-error" role="alert">{error || Object.values(form.errors).join(' ')}</div>}
        {!supported && <p className="p-service-note">La participation sera disponible dès qu’un taux de change récent pourra être confirmé.</p>}
        <Button type="submit" className="p-form-submit" disabled={!supported} loading={loading || form.processing}>Confirmer ma position</Button>
    </form><ConfirmationModal open={!!quote} title="Vérifier avant de confirmer" confirmLabel="Valider définitivement" onClose={() => setQuote(null)} onConfirm={confirm} busy={form.processing} confirmDisabled={remaining <= 0 || loading}>
        {quote && <><p>Votre position · {quote.outcome === 'yes' ? 'Oui' : 'Non'} · Cote ×{(quote.odds / 100).toFixed(2)}</p><dl className="p-confirm-summary"><div><dt>Montant débité</dt><dd>{displayDecimal(quote.amount, currency)} {currency}</dd></div><div><dt>Retour potentiel, mise incluse</dt><dd>{displayDecimal(quote.payout, currency)} {currency}</dd></div>{currency !== 'XAF' && <><div><dt>Équivalent de référence (arrondi)</dt><dd>{quote.base_amount} XAF</dd></div><div><dt>Taux enregistré</dt><dd>1 XAF = {formatRate(quote.rate)} {currency}</dd></div><div><dt>Taux quotidien du</dt><dd>{new Date(quote.as_of).toLocaleString('fr-FR')}</dd></div></>}</dl><p>La mise et le retour potentiel sont fixés en {currency}. Une annulation rembourse le montant débité.</p><div className={`p-quote-expiry ${remaining === 0 ? 'p-quote-expiry--expired' : ''}`} role="status">{remaining > 0 ? `Confirmation valable encore ${remaining} s.` : 'La confirmation a expiré. Actualisez-la avant de continuer.'}</div>{remaining === 0 && <Button variant="secondary" loading={loading} onClick={() => void prepare()}>Actualiser la confirmation</Button>}</>}
    </ConfirmationModal></>;
}
