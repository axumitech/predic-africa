import { useEffect, useId, useRef } from 'react';
import { createPortal } from 'react-dom';

export default function WorkspaceSplash({ leaving, finish, admin }: { leaving: boolean; finish: () => void; admin: boolean }) {
    const id = useId();
    const skip = useRef<HTMLButtonElement>(null);
    useEffect(() => {
        const focus = () => skip.current?.focus({ preventScroll: true });
        const retainFocus = (event: FocusEvent) => {
            if (event.target !== skip.current) focus();
        };
        const frame = requestAnimationFrame(focus);
        document.addEventListener('focusin', retainFocus);
        return () => { cancelAnimationFrame(frame); document.removeEventListener('focusin', retainFocus); };
    }, []);
    return createPortal(<section className={`workspace-splash ${leaving ? 'workspace-splash--leaving' : ''}`} role="dialog" aria-modal="true" aria-labelledby={id} onKeyDown={e => {
        if (e.key === 'Escape') { e.preventDefault(); finish(); }
        if (e.key === 'Tab') { e.preventDefault(); skip.current?.focus(); }
    }}>
        <div className="workspace-splash__grid" aria-hidden="true" />
        <div className="workspace-splash__halo workspace-splash__halo--one" aria-hidden="true" />
        <div className="workspace-splash__halo workspace-splash__halo--two" aria-hidden="true" />
        <div className="workspace-splash__streaks" aria-hidden="true"><i/><i/><i/></div>
        <div className="workspace-splash__content">
            <div className="workspace-splash__orbit" aria-hidden="true"><span/><span/><span/></div>
            <div className="workspace-splash__brand"><img width="1460" height="481" src="/HOR_WG.png" alt="PredicAfrica" /></div>
            <div className="workspace-splash__copy"><span className="workspace-splash__eyebrow">VOS CONVICTIONS. UNE LONGUEUR D’AVANCE.</span><h2 id={id}>Entrez dans<br/><em>votre prochaine vision.</em></h2><p>{admin ? 'Votre centre de pilotage vous attend.' : 'Votre espace. Vos choix. Vos perspectives.'}</p></div>
            <div className="workspace-splash__signature" aria-hidden="true"><span>OUI</span><i/><span>NON</span></div>
        </div>
        <span className="workspace-splash__edition" aria-hidden="true">PREDICAFRICA / {admin ? 'PILOTAGE' : 'VOTRE ESPACE'}</span>
        <button ref={skip} className="workspace-splash__skip" onClick={finish} type="button">Passer l’animation <span aria-hidden="true">↗</span></button>
    </section>, document.body);
}
