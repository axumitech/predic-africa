import { useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Button, ConfirmationModal } from '../../components/ui';
import { currencyScale, formatDecimal } from './currency';

type Quote = { id: string; source_currency: string; target_currency: string; source_amount: string; target_amount: string; rate: string; as_of: string; expires_at: string; server_time: string };
export default function WalletTransfer({ currency, currencies }: { currency: string; currencies: string[] }) {
    const [target, setTarget] = useState(currencies.find(code => code !== currency) || '');
    const [amount, setAmount] = useState('');
    const [quote, setQuote] = useState<Quote | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [remaining, setRemaining] = useState(0);
    const expiry = useRef(0);
    const pending = useRef<AbortController | null>(null);
    const form = useForm({ quote_id: '' });
    useEffect(() => () => pending.current?.abort(), []);
    useEffect(() => {
        if (!quote) return;
        const timer = window.setInterval(() => setRemaining(Math.max(0, Math.ceil((expiry.current - performance.now()) / 1000))), 250);
        return () => clearInterval(timer);
    }, [quote]);
    const prepare = async () => {
        if (loading || form.processing) return;
        setLoading(true); setError(''); form.clearErrors();
        pending.current = new AbortController();
        try {
            const cookie = document.cookie.split('; ').find(value => value.startsWith('XSRF-TOKEN='));
            const token = cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const response = await fetch('/wallets/transfers/quote', { method: 'POST', credentials: 'same-origin', signal: pending.current.signal,
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', [cookie ? 'X-XSRF-TOKEN' : 'X-CSRF-TOKEN']: token },
                body: JSON.stringify({ source_currency: currency, target_currency: target, amount }) });
            const body = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(body.errors ? Object.values(body.errors).flat().join(' ') : 'Le devis est indisponible. Réessayez.');
            const value = body as Quote;
            form.setData('quote_id', value.id);
            expiry.current = performance.now() + Math.max(0, Date.parse(value.expires_at) - Date.parse(value.server_time));
            setRemaining(Math.max(0, Math.ceil((expiry.current - performance.now()) / 1000)));
            setQuote(value);
        } catch (failure) {
            if (!(failure instanceof DOMException && failure.name === 'AbortError')) {
                setQuote(null); setError(failure instanceof Error ? failure.message : 'Le devis est indisponible.');
            }
        } finally { setLoading(false); }
    };
    return <div className="p-wallet-transfer"><h3>Transférer entre mes portefeuilles</h3><p>Le transfert débite votre portefeuille {currency}. Le taux et le montant reçu seront affichés avant validation.</p>
        <form className="p-record-search" onSubmit={event => { event.preventDefault(); void prepare(); }}>
            <label>Vers le portefeuille<select required value={target} onChange={event => { setTarget(event.target.value); setQuote(null); }} disabled={loading || form.processing}>{currencies.filter(code => code !== currency).map(code => <option key={code}>{code}</option>)}</select></label>
            <label>Montant en {currency}<input required type="number" inputMode={currencyScale(currency) ? 'decimal' : 'numeric'} min={currencyScale(currency) ? '0.01' : '1'} step={currencyScale(currency) ? '0.01' : '1'} value={amount} onChange={event => { setAmount(event.target.value); setQuote(null); }} disabled={loading || form.processing}/></label>
            <Button type="submit" loading={loading || form.processing}>Voir le taux et le montant reçu</Button>
        </form>
        {(error || Object.keys(form.errors).length > 0) && <p className="p-error" role="alert">{error || Object.values(form.errors).join(' ')}</p>}
        <ConfirmationModal open={!!quote} title="Confirmer le transfert" confirmLabel="Transférer" busy={form.processing} confirmDisabled={remaining <= 0 || loading} onClose={() => { if (!form.processing) setQuote(null); }} onConfirm={() => {
            if (!quote || remaining <= 0 || form.processing) return;
            form.post('/wallets/transfers', { preserveScroll: true, onSuccess: () => { setQuote(null); setAmount(''); }, onError: () => setQuote(null) });
        }}>{quote && <><dl className="p-confirm-summary"><div><dt>Montant débité</dt><dd>{formatDecimal(quote.source_amount, quote.source_currency)} {quote.source_currency}</dd></div><div><dt>Montant reçu</dt><dd>{formatDecimal(quote.target_amount, quote.target_currency)} {quote.target_currency}</dd></div><div><dt>Taux de conversion</dt><dd>1 {quote.source_currency} ≈ {quote.rate.replace(/\.?0+$/, '')} {quote.target_currency}</dd></div><div><dt>Date du taux</dt><dd>{new Date(quote.as_of).toLocaleString('fr-FR')}</dd></div><div><dt>Frais de transfert</dt><dd>0 {quote.source_currency}</dd></div></dl><p>Le montant reçu est arrondi à l’unité minimale de la devise destinataire, vers le bas. Le montant affiché est celui qui sera crédité.</p><p role="status">{remaining > 0 ? `Devis valable encore ${remaining} s.` : 'Le devis a expiré. Actualisez le taux avant de confirmer.'}</p>{remaining <= 0 && <Button variant="secondary" loading={loading} onClick={() => void prepare()}>Actualiser le taux</Button>}</>}
        </ConfirmationModal>
    </div>;
}
