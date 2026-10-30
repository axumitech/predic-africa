import { Head, Link, router, usePage } from '@inertiajs/react';
import '../../css/app.css';
import '../features/platform/platform.css';
import '../features/platform/workspace.css';

export default function ErrorPage({ status }: { status: number }) {
    const { auth } = usePage().props;
    const messages: Record<number, [string, string]> = {
        403: ['Accès non autorisé', 'Cette page nécessite des droits supplémentaires, ou votre compte est suspendu.'],
        404: ['Page introuvable', 'Ce lien ne correspond à aucune page disponible.'],
        419: ['Votre session a expiré', 'Reconnectez-vous, puis réessayez votre opération.'],
        429: ['Un instant, s’il vous plaît', 'Trop de demandes ont été envoyées. Attendez une minute avant de réessayer.'],
        500: ['Une erreur est survenue', 'Votre demande n’a pas pu aboutir. Consultez vos opérations avant de réessayer.'],
        503: ['Service temporairement indisponible', 'Veuillez réessayer dans quelques instants.'],
    };
    const [title, message] = messages[status] || ['Une erreur est survenue', 'Veuillez réessayer.'];
    return <main className="platform p-public p-error-page"><Head title={`${status} — ${title}`} /><Link href="/" className="p-error-brand" aria-label="PredicAfrica — Accueil"><img width="1460" height="481" src="/HOR_WG.png" alt="PredicAfrica"/></Link><section className="p-document"><span className="p-error-number">{status}</span><h1>{title}</h1><p>{message}</p><div className="p-actions"><Link className="p-button btn btn--primary" href="/">Retour à l’accueil</Link>{auth ? <button className="p-button p-secondary btn btn--outline" onClick={() => router.post('/logout')}>Se déconnecter</button> : <Link className="p-button p-secondary btn btn--outline" href="/login">Connexion</Link>}</div></section></main>;
}
