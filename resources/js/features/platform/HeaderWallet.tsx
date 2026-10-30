import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { formatMinor, type Minor } from './currency';

type Wallet = { id: number; currency: string; balance: string };
export default function HeaderWallet({ currency, balance }: { currency: string; balance: Minor }) {
    const { wallets = [] } = usePage<{ wallets: Wallet[] }>().props;
    const [switching, setSwitching] = useState(false);
    const [error, setError] = useState('');
    return <div className="p-header-wallet">
        <Link href="/wallet" className="p-header-wallet-balance" aria-label={`Mon portefeuille : ${formatMinor(balance, currency)} ${currency}`}><small>Solde disponible</small><strong>{formatMinor(balance, currency)} <span>{currency}</span></strong></Link>
        <select aria-label="Changer de portefeuille" value={currency} disabled={switching} onChange={event => {
            setSwitching(true); setError('');
            router.post('/wallets/select', { currency: event.target.value }, { preserveState: false, preserveScroll: true,
                onError: () => setError('Changement impossible. Réessayez.'), onFinish: () => setSwitching(false) });
        }}>{wallets.length ? wallets.map(wallet => <option key={wallet.id} value={wallet.currency}>{wallet.currency}</option>) : <option value={currency}>{currency}</option>}</select>
        {error && <span className="p-header-wallet-error" role="alert">{error}</span>}
    </div>;
}
