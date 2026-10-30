import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { formatMinor, type Minor } from './currency';
import { Badge, Button } from '../../components/ui';
import { ActionForm, Panel, money, date } from './ui';

export type NdsgpayWalletData = {
    enabled: boolean;
    currency: string;
    balance: number;
    scale: number;
    min_amount: string;
    max_amount: string;
    correspondents: string[];
    maintenance_message?: string;
    correspondent_labels?: Record<string, string>;
    payments: { id: number; reference: string; kind: string; amount: Minor; currency: string; status: string; local_state: string; created_at: string }[];
};

export default function NdsgpayWallet({ data }: { data?: NdsgpayWalletData }) {
    const [refreshing, setRefreshing] = useState<number | null>(null);
    const active = data?.payments.some(p => ['initiating', 'pending', 'awaiting_confirmation'].includes(p.local_state));
    useEffect(() => {
        if (!active) return;
        let polls = 0;
        const timer = window.setInterval(() => {
            if (document.visibilityState !== 'visible') return;
            if (++polls > 20) { window.clearInterval(timer); return; }
            router.reload({ only: ['ndsgpay', 'wallets', 'balance', 'balance_exact', 'walletStats', 'transactions'] });
        }, 15000);
        return () => window.clearInterval(timer);
    }, [active]);
    const labels: Record<string, string> = { initiating: 'Initiation en cours', pending: 'En attente de confirmation', awaiting_confirmation: 'Validation en cours', completed: 'Succès confirmé', failed: 'Échec confirmé', initiation_unknown: 'Vérification nécessaire' };
    const blocked = data?.payments.some(p => !['completed', 'failed'].includes(p.local_state) && p.kind === 'deposit');
    return <Panel title="Alimenter mon portefeuille" className="p-payment-panel">
        <div className="p-payment-heading"><span className="p-payment-icon" aria-hidden="true">↗</span><div><strong>Mobile Money</strong><small>Confirmation sur votre téléphone</small></div><Badge tone={data?.enabled ? 'success' : 'neutral'}>{data?.enabled ? 'NDSGPay' : 'Indisponible'}</Badge></div>
        {!data?.enabled ? <div className="p-service-note"><h3>Les dépôts sont temporairement indisponibles.</h3><p>Votre solde et vos opérations restent accessibles. Réessayez plus tard ou contactez le support.</p><Link href="/support">Contacter le support →</Link></div>
            : blocked ? <div className="ds-alert ds-alert--warning"><strong>Un dépôt est déjà en cours.</strong><p>Attendez sa confirmation ou contactez le support avec sa référence. Ne relancez pas le paiement.</p></div>
            : data.correspondents.length > 0 ? <ActionForm action="/wallet/ndsgpay" initial={{ kind: 'deposit', amount: '1000', reference: crypto.randomUUID(), correspondent: data.correspondents[0], msisdn: '' }} freshReference fields={[
                { name: 'correspondent', label: 'Pays et opérateur', options: data.correspondents.map(value => ({ value, label: data.correspondent_labels?.[value] ?? value })) },
                { name: 'msisdn', label: 'Numéro Mobile Money', type: 'tel', hint: 'Numéro local ou international. L’indicatif du pays de l’opérateur choisi est ajouté automatiquement.' },
                { name: 'amount', label: `Montant en ${data.currency}`, type: 'number', min: Number(data.min_amount), max: Number(data.max_amount), step: data.scale ? '0.01' : '1', hint: `De ${data.min_amount} à ${data.max_amount} ${data.currency}.` },
            ]} submit="Continuer le dépôt" /> : <p>Aucun opérateur de dépôt n’est activé pour cette devise.</p>}
        <p className="p-payment-security">Ne saisissez jamais votre code Mobile Money sur ce site. Les retraits ne sont pas disponibles actuellement.</p>
        {!!data?.payments.length && <section className="p-payment-history" aria-label="Suivi des paiements"><h3>Suivi des paiements</h3>{data.payments.map(payment => <article className="p-payment-item" key={payment.id}>
            <div className="p-payment-item-heading"><strong>{payment.kind === 'deposit' ? 'Dépôt' : 'Retrait'} · {formatMinor(payment.amount, payment.currency)} {payment.currency}</strong><Badge tone={payment.local_state === 'completed' ? 'success' : payment.local_state === 'failed' ? 'danger' : 'warning'}>{labels[payment.local_state] || 'Vérification nécessaire'}</Badge></div>
            <time>{date(payment.created_at)}</time><code>{payment.reference}</code>
            {['pending', 'awaiting_confirmation'].includes(payment.local_state) && <Button variant="secondary" loading={refreshing === payment.id} disabled={refreshing !== null} onClick={() => {
                setRefreshing(payment.id);
                router.post(`/wallet/ndsgpay/${payment.id}/refresh`, {}, { preserveScroll: true, onFinish: () => setRefreshing(null) });
            }}>Actualiser le statut</Button>}
            {['initiation_unknown', 'initiating'].includes(payment.local_state) && <p>Vérification nécessaire par le support. Ne relancez pas cette opération.</p>}
        </article>)}</section>}
    </Panel>;
}
