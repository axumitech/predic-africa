import '../css/base.css';
import '../css/tokens.css';
import RenderBoundary from './components/RenderBoundary';
import ConnectionStatus from './components/ConnectionStatus';
import { LocaleProvider } from './content/locale';
import '../css/responsive.css';
import { resetWorkspaceEntry } from './hooks/useWorkspaceEntry';
import { router } from '@inertiajs/react';
import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';

router.on('success', event => { if (!event.detail.page.props.auth) resetWorkspaceEntry(); });

const pages = import.meta.glob<{ default: ComponentType }>('./pages/*.tsx');
createInertiaApp({
    resolve: async (name) => {
        const load = pages[`./pages/${name}.tsx`];
        if (!load) throw new Error(`Unknown page: ${name}`);
        return (await load()).default;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<RenderBoundary><LocaleProvider><ConnectionStatus/><App {...props} /></LocaleProvider></RenderBoundary>);
    },
});
