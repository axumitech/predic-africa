import { router } from '@inertiajs/react';
import { lazy, Suspense } from 'react';

const Prototype = lazy(() => import('../../pages/Prototype'));

// Reuse the original home, including its FAQ, translations and USSD simulator.
// Only the entry buttons change destination: authenticated business pages.
export default function OriginalHome({ authenticated }: { authenticated: boolean }) {
    return <div onClickCapture={event => {
        const target = (event.target as HTMLElement).closest('#btn-enter-bourse, #btn-hero-start');
        if (!target) return;
        event.preventDefault();
        event.stopPropagation();
        router.visit(authenticated ? '/markets' : '/login');
    }}><Suspense fallback={<div role="status">Chargement…</div>}><Prototype /></Suspense></div>;
}
