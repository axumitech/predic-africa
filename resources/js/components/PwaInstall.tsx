import { createContext, useContext, useEffect, useRef, useState, type ReactNode } from 'react';
import { Modal } from './ui';
import '../../css/pwa.css';

const INSTALLED_KEY = 'pa:pwa-installed';
const DISMISSED_KEY = 'pa:pwa-remind-after';
const REMIND_DELAY = 7 * 24 * 60 * 60 * 1000;
function storedInstalled() { try { return localStorage.getItem(INSTALLED_KEY) === 'yes'; } catch { return false; } }
function rememberInstalled() { try { localStorage.setItem(INSTALLED_KEY, 'yes'); } catch { /* Storage can be unavailable in private mode. */ } }
function snoozed() { try { return Number(localStorage.getItem(DISMISSED_KEY) || 0) > Date.now(); } catch { return false; } }
function snooze() { try { localStorage.setItem(DISMISSED_KEY, String(Date.now() + REMIND_DELAY)); } catch { /* Keep the popup dismissible without storage. */ } }

type InstallEvent = Event & { prompt: () => Promise<void>; userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }> };
const InstallContext = createContext<{ installed: boolean; busy: boolean; install: () => void }>({ installed: false, busy: false, install: () => {} });
export function PwaProvider({ children }: { children: ReactNode }) {
    const [prompt, setPrompt] = useState<InstallEvent | null>(null);
    const [installed, setInstalled] = useState(storedInstalled);
    const [busy, setBusy] = useState(false);
    const [instructions, setInstructions] = useState(false);
    const [error, setError] = useState('');
    const offered = useRef(false);
    const android = /Android/i.test(navigator.userAgent);
    const safariMac = /Macintosh/.test(navigator.userAgent) && /Safari/.test(navigator.userAgent) && !/Chrome|Chromium|Edg/.test(navigator.userAgent);
    const ios = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    useEffect(() => {
        const standalone = window.matchMedia('(display-mode: standalone)');
        const update = () => {
            const running = standalone.matches || !!(navigator as Navigator & { standalone?: boolean }).standalone;
            if (running) rememberInstalled();
            setInstalled(running || storedInstalled());
        };
        const ready = (event: Event) => {
            event.preventDefault();
            if (standalone.matches || (navigator as Navigator & { standalone?: boolean }).standalone) return;
            setPrompt(event as InstallEvent);
            // A fresh native install opportunity also handles a previously removed app.
            try { localStorage.removeItem(INSTALLED_KEY); } catch { /* Optional storage. */ }
            setInstalled(false);
        };
        const complete = () => { rememberInstalled(); setInstalled(true); setPrompt(null); setInstructions(false); };
        update(); standalone.addEventListener('change', update);
        window.addEventListener('beforeinstallprompt', ready); window.addEventListener('appinstalled', complete);
        return () => { standalone.removeEventListener('change', update); window.removeEventListener('beforeinstallprompt', ready); window.removeEventListener('appinstalled', complete); };
    }, []);
    useEffect(() => {
        if (installed || !window.isSecureContext || snoozed() || offered.current) return;
        const started = Date.now();
        const timer = window.setInterval(() => {
            if (Date.now() - started < 6000 || document.hidden || document.querySelector('dialog[open], .workspace-splash')) return;
            const focused = document.activeElement;
            if (focused?.matches('input, textarea, select, [contenteditable=true]') || focused?.closest('form')) return;
            if (snoozed() || storedInstalled()) { window.clearInterval(timer); return; }
            offered.current = true;
            setInstructions(true);
            window.clearInterval(timer);
        }, 1000);
        return () => window.clearInterval(timer);
    }, [installed]);
    const close = () => { offered.current = true; snooze(); setInstructions(false); };
    const alreadyInstalled = () => { rememberInstalled(); setInstalled(true); setInstructions(false); };
    const install = async () => {
        if (busy) return;
        if (!prompt) { setInstructions(true); return; }
        setBusy(true); setError(''); close();
        try { await prompt.prompt(); await prompt.userChoice; setPrompt(null); }
        catch { setError('L’installation n’a pas pu démarrer. Réessayez depuis le menu du navigateur.'); setInstructions(true); setPrompt(null); }
        finally { setBusy(false); }
    };
    return <InstallContext.Provider value={{ installed, busy, install: () => { void install(); } }}>{children}
        <Modal open={instructions && !installed} title="Installer PredicAfrica" onClose={close} footer={<div className="pwa-popup-actions">{prompt && window.isSecureContext && <button type="button" className="ds-button ds-button--primary" disabled={busy} onClick={() => { void install(); }}>Installer maintenant</button>}<button type="button" className="ds-button ds-button--secondary" onClick={close}>{prompt ? 'Plus tard' : 'J’ai compris'}</button><button type="button" className="pwa-already-installed" onClick={alreadyInstalled}>Je l’ai déjà installée</button></div>}>
            <div className="pwa-install-intro"><img src="/icons/predicafrica-192.png" alt=""/><p>Retrouvez PredicAfrica sur votre écran d’accueil, dans sa propre fenêtre.</p></div>
            {error && <p role="alert">{error}</p>}
            <p className="pwa-device-label">{ios ? 'Sur iPhone ou iPad' : android ? 'Sur Android' : safariMac ? 'Sur Mac avec Safari' : 'Sur votre ordinateur'}</p>
            {!window.isSecureContext ? <p>Ouvrez le site en HTTPS pour pouvoir l’installer.</p> : ios ? <ol className="pwa-install-steps"><li>Ouvrez cette page dans <strong>Safari</strong>.</li><li>Touchez <strong>Partager</strong> (le carré avec une flèche vers le haut). Si nécessaire, ouvrez d’abord le menu « … ».</li><li>Choisissez <strong>Sur l’écran d’accueil</strong>. Si l’option apparaît, activez <strong>Ouvrir comme app web</strong>.</li><li>Touchez <strong>Ajouter</strong>, puis ouvrez PredicAfrica depuis sa nouvelle icône.</li></ol> : android ? <ol className="pwa-install-steps"><li>Ouvrez cette page dans <strong>Chrome</strong> ou <strong>Edge</strong>.</li><li>{prompt ? <>Touchez <strong>Installer maintenant</strong> ci-dessous.</> : <>Ouvrez le menu <strong>⋮</strong> du navigateur, puis <strong>Installer l’application</strong> ou <strong>Ajouter à l’écran d’accueil</strong>.</>}</li><li>Confirmez l’installation et retrouvez l’icône PredicAfrica sur votre écran d’accueil.</li></ol> : safariMac ? <ol className="pwa-install-steps"><li>Dans Safari, ouvrez le menu <strong>Fichier</strong>.</li><li>Choisissez <strong>Ajouter au Dock</strong>.</li><li>Confirmez avec <strong>Ajouter</strong> et ouvrez PredicAfrica depuis le Dock.</li></ol> : <ol className="pwa-install-steps"><li>Ouvrez PredicAfrica dans <strong>Chrome</strong> ou <strong>Edge</strong>.</li><li>{prompt ? <>Cliquez sur <strong>Installer maintenant</strong> ci-dessous.</> : <>Dans le menu du navigateur, choisissez <strong>Installer PredicAfrica</strong> ou <strong>Installer cette page en tant qu’application</strong>.</>}</li><li>Confirmez pour ouvrir PredicAfrica dans sa propre fenêtre.</li></ol>}
            <p>Une connexion reste nécessaire pour consulter vos soldes et effectuer des transactions.</p>
        </Modal>
    </InstallContext.Provider>;
}
export default function PwaInstall() {
    const { installed, busy, install } = useContext(InstallContext);
    if (installed) return null;
    return <button type="button" className="pwa-install-button" disabled={busy} onClick={install}><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="1.7" aria-hidden="true"><path d="M12 3v12m-5-5 5 5 5-5M5 17v4h14v-4"/></svg>{busy ? 'Installation…' : 'Installer l’application'}</button>;
}
