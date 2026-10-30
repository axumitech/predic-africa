import { Link, usePage } from '@inertiajs/react';
import { ActionForm, Panel, type Account } from './ui';
import { Badge } from '../../components/ui';
import { formatMinor } from './currency';

type Details = { phone: string | null; default_wallet_currency: string; countries: { code: string; name: string }[] };
export default function Profile({ auth, details }: { auth: Account; details?: Details }) {
    const { wallets = [] } = usePage<{ wallets: { id: number; currency: string; balance: string }[] }>().props;
    const currency = details?.default_wallet_currency || wallets[0]?.currency || auth.wallet_currency || 'XAF';
    return <div className="p-profile-layout"><Panel title="Informations personnelles"><p>Gardez vos coordonnées à jour et protégez l’accès à votre compte.</p>
        <dl className="p-confirm-summary"><div><dt>Pays de résidence</dt><dd>{auth.country_name || 'Non renseigné'}</dd></div><div><dt>Numéro de téléphone</dt><dd>{details?.phone || 'Non renseigné'}</dd></div><div><dt>Portefeuille par défaut</dt><dd>{currency}</dd></div></dl>
        <ActionForm key={`${auth.country || 'unset'}:${currency}`} action="/profile" initial={{ name: auth.name, email: auth.email, current_password: '', password: '', password_confirmation: '', ...(!auth.country ? { country: '' } : {}), ...(wallets.length > 1 ? { default_wallet_currency: currency } : {}) }} fields={[
            { name: 'name', label: 'Nom complet', maxLength: 100 },
            { name: 'email', label: 'Adresse e-mail', type: 'email' },
            ...(!auth.country ? [{ name: 'country', label: 'Pays de résidence', required: false, options: [{ value: '', label: 'Choisir mon pays' }, ...(details?.countries || []).map(country => ({ value: country.code, label: country.name }))], hint: 'Renseignez votre pays manquant. Il sera ensuite conservé dans votre profil.' }] : []),
            ...(wallets.length > 1 ? [{ name: 'default_wallet_currency', label: 'Portefeuille par défaut', options: wallets.map(wallet => ({ value: wallet.currency, label: `${auth.name} · ${wallet.currency} · ${formatMinor(wallet.balance, wallet.currency)}` })), hint: 'Sélectionné automatiquement à votre prochaine connexion. Vous pouvez changer de portefeuille à tout moment dans le header.' }] : []),
            { name: 'current_password', label: 'Mot de passe actuel', type: 'password', hint: 'Nécessaire pour confirmer toute modification de votre profil.' },
            { name: 'password', label: 'Nouveau mot de passe (facultatif)', type: 'password', required: false, minLength: 10 },
            { name: 'password_confirmation', label: 'Confirmer le nouveau mot de passe', type: 'password', required: false },
        ]} submit="Enregistrer mon profil"/>
    </Panel><aside className="p-panel p-profile-note"><span className="p-eyebrow">VOTRE COMPTE</span><div className="p-profile-avatar" aria-hidden="true">{auth.name.slice(0, 1).toUpperCase()}</div><h2>{auth.name}</h2><p>{auth.email}</p><Badge>{['admin', 'superadmin'].includes(auth.role) ? 'Administrateur' : 'Compte trader'}</Badge><hr/><h3>Un accès personnel</h3><p>Utilisez un mot de passe unique d’au moins dix caractères. Ne partagez jamais vos identifiants ni votre code Mobile Money.</p><Link href="/support">Besoin d’aide ? →</Link></aside></div>;
}
export type { Details as ProfileDetails };
