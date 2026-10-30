import { useEffect, useState } from 'react';
import { useBodyScrollLock } from './useBodyScrollLock';

let enteredUser: number | null = null;
export function resetWorkspaceEntry() { enteredUser = null; }

/** One cinematic entrance per authenticated document/session entry, not per page. */
export function useWorkspaceEntry(userId: number | undefined, enabled: boolean) {
    const [active, setActive] = useState(() => enabled && enteredUser !== userId);
    const [leaving, setLeaving] = useState(false);
    useBodyScrollLock(active);
    useEffect(() => {
        if (!enabled || !userId) return;
        enteredUser = userId;
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const leave = window.setTimeout(() => setLeaving(true), reduced ? 120 : 1900);
        const done = window.setTimeout(() => setActive(false), reduced ? 220 : 2380);
        return () => { clearTimeout(leave); clearTimeout(done); };
    }, [enabled, userId]);
    useEffect(() => {
        if (!leaving || !active) return;
        const done = window.setTimeout(() => setActive(false), window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 40 : 360);
        return () => clearTimeout(done);
    }, [leaving, active]);
    return { active, leaving, finish: () => setLeaving(true) };
}
