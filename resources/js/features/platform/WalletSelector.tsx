import { router, useForm, usePage } from '@inertiajs/react';
import { formatMinor } from './currency';
import { useEffect, useId, useRef, useState } from 'react';
import WalletTransfer from './WalletTransfer';
import NdsgpayWallet, { type NdsgpayWalletData } from './NdsgpayWallet';

type Wallet = { id: number; currency: string; balance: string };
export default function WalletSelector({ currency, manage = false }: { currency: string; manage?: boolean }) {
    const { wallets = [], walletCurrencies = [], auth, ndsgpay } = usePage<{ wallets: Wallet[]; walletCurrencies: string[]; auth?: { name: string }; ndsgpay?: NdsgpayWalletData }>().props;
    const activeIndex = Math.max(0, wallets.findIndex(wallet => wallet.currency === currency));
    const [index, setIndex] = useState(activeIndex);
    const [switching, setSwitching] = useState(false);
    const [action, setAction] = useState<'deposit' | 'withdraw' | 'transfer'>('deposit');
    useEffect(() => { setAction('deposit'); }, [currency]);
    const [switchError, setSwitchError] = useState('');
    const touchStart = useRef<number | null>(null);
    const titleId = useId();
    const available = walletCurrencies.filter(code => !wallets.some(wallet => wallet.currency === code));
    const form = useForm({ currency: available[0] || '' });
    const busy = switching || form.processing;
    const goTo = (next: number) => {
        if (busy || next < 0 || next > wallets.length || next === index) return;
        setIndex(next); setSwitchError('');
        const wallet = wallets[next];
        if (!wallet || wallet.currency === currency) return;
        setSwitching(true);
        router.post('/wallets/select', { currency: wallet.currency }, {
            preserveState: true, preserveScroll: true,
            onError: () => { setIndex(activeIndex); setSwitchError('Le changement de portefeuille a échoué. Réessayez.'); },
            onFinish: () => setSwitching(false),
        });
    };
    return <section className="p-wallet-selector" aria-labelledby={titleId} aria-busy={busy}>
        <div className="p-wallet-carousel-heading"><div><span className="p-eyebrow">VOTRE ARGENT</span><h2 id={titleId}>Mes portefeuilles</h2></div><div className="p-wallet-carousel-controls"><button type="button" aria-label="Portefeuille précédent" disabled={busy || index === 0} onClick={() => goTo(index - 1)}>←</button><button type="button" aria-label="Portefeuille suivant" disabled={busy || index === wallets.length} onClick={() => goTo(index + 1)}>→</button></div></div>
        <div className="p-wallet-carousel-window" role="region" aria-roledescription="carrousel" aria-label="Choix du portefeuille" tabIndex={0}
            onKeyDown={event => { if (event.target !== event.currentTarget) return; if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') { event.preventDefault(); goTo(index + (event.key === 'ArrowRight' ? 1 : -1)); } }}
            onPointerDown={event => { touchStart.current = (event.target as HTMLElement).closest('button, input, select, label') ? null : event.clientX; }}
            onPointerCancel={() => { touchStart.current = null; }}
            onPointerUp={event => { if (touchStart.current === null) return; const distance = event.clientX - touchStart.current; touchStart.current = null; if (Math.abs(distance) >= 45) goTo(index + (distance < 0 ? 1 : -1)); }}>
            <div className="p-wallet-carousel-track" style={{ transform: `translateX(-${index * 100}%)` }}>
                {wallets.map((wallet, position) => <div className="p-wallet-slide" key={wallet.id} role="group" aria-roledescription="diapositive" aria-label={`Portefeuille ${wallet.currency}`} aria-hidden={index !== position} inert={index !== position || undefined}>
                    <div className="p-wallet-account-card"><div className="p-wallet-card-top"><span className="p-wallet-card-symbol" aria-hidden="true">◈</span><span className="p-wallet-card-tag">{position === 0 ? 'Compte principal' : 'Compte en devise'}</span><span className="p-wallet-card-currency">{wallet.currency}</span></div>
                        <h3>{auth?.name || 'Mon compte'}</h3><span className="p-wallet-card-caption">Solde disponible</span><p className="p-wallet-card-amount">{formatMinor(wallet.balance, wallet.currency)} <span>{wallet.currency}</span></p>
                        <div className="p-wallet-card-footer"><span><i aria-hidden="true"/>{wallet.currency === currency ? 'Portefeuille actif' : 'Sélection en cours…'}</span><span>PredicAfrica</span></div>
                    </div>
                </div>)}
                <div className="p-wallet-slide" role="group" aria-roledescription="diapositive" aria-label="Ajouter un portefeuille" aria-hidden={index !== wallets.length} inert={index !== wallets.length || undefined}>
                    <div className="p-wallet-add-card"><span className="p-wallet-add-symbol" aria-hidden="true">＋</span><h3>Ajouter un portefeuille</h3><p>Une nouvelle devise, un solde séparé.<br/>Toujours dans votre compte.</p>
                        {available.length > 0 ? <form onSubmit={event => { event.preventDefault(); form.post('/wallets', { preserveState: 'errors', preserveScroll: true }); }}><label className="p-wallet-currency-label" htmlFor={`${titleId}-currency`}>Devise du nouveau portefeuille</label><select id={`${titleId}-currency`} required value={form.data.currency} disabled={busy} onChange={event => form.setData('currency', event.target.value)}>{available.map(code => <option key={code} value={code}>{code}</option>)}</select><button className="btn btn--primary" disabled={busy} type="submit">Créer un portefeuille <span aria-hidden="true">↗</span></button></form> : <p>Toutes les devises disponibles sont déjà dans vos portefeuilles.</p>}
                        {form.errors.currency && <p className="p-error" role="alert">{form.errors.currency}</p>}
                    </div>
                </div>
            </div>
        </div>
        {manage && index < wallets.length && <div className="p-wallet-card-actions" role="group" aria-label={`Actions du portefeuille ${currency}`}>
            <button type="button" disabled={busy} aria-pressed={action === 'deposit'} aria-controls={`${titleId}-deposit`} onClick={() => setAction('deposit')}><span aria-hidden="true">↓</span>Déposer</button>
            <button type="button" disabled={busy} aria-pressed={action === 'withdraw'} aria-controls={`${titleId}-withdraw`} onClick={() => setAction('withdraw')}><span aria-hidden="true">↑</span>Retirer<small>Indisponible</small></button>
            <button type="button" disabled={busy} aria-pressed={action === 'transfer'} aria-controls={`${titleId}-transfer`} onClick={() => setAction('transfer')}><span aria-hidden="true">⇄</span>Transférer</button>
        </div>}
        <div className="p-wallet-carousel-pagination" aria-label="Navigation des portefeuilles">{wallets.map((wallet, position) => <button type="button" key={wallet.id} disabled={busy} aria-label={`Choisir le portefeuille ${wallet.currency}`} aria-current={index === position ? 'true' : undefined} onClick={() => goTo(position)}><span aria-hidden="true"/>{wallet.currency}</button>)}<button type="button" disabled={busy} aria-label="Ajouter un portefeuille" aria-current={index === wallets.length ? 'true' : undefined} onClick={() => goTo(wallets.length)}><span aria-hidden="true">＋</span>Ajouter</button></div>
        <p className="p-wallet-carousel-status" role="status">{switching ? 'Changement de portefeuille…' : index === wallets.length ? 'Choisissez la devise de votre nouveau portefeuille.' : `Portefeuille ${currency} sélectionné pour vos transactions.`}</p>
        {switchError && <p className="p-error" role="alert">{switchError}</p>}
        {manage && <div className="p-wallet-action-panels" hidden={index === wallets.length || switching}>
            <div id={`${titleId}-deposit`} hidden={action !== 'deposit'}><NdsgpayWallet key={currency} data={ndsgpay}/></div>
            <div id={`${titleId}-withdraw`} hidden={action !== 'withdraw'} className="p-panel"><h3>Retirer depuis mon portefeuille {currency}</h3><p>Les retraits Mobile Money ne sont pas disponibles actuellement.</p></div>
            <div id={`${titleId}-transfer`} hidden={action !== 'transfer'} className="p-panel">{wallets.length > 1 ? <WalletTransfer key={currency} currency={currency} currencies={wallets.map(wallet => wallet.currency)}/> : <><h3>Transférer entre mes portefeuilles</h3><p>Ajoutez un portefeuille dans une autre devise pour effectuer un transfert avec conversion.</p><button type="button" className="btn btn--primary" onClick={() => goTo(wallets.length)}>Ajouter un portefeuille</button></>}</div>
        </div>}
    </section>;
}
