import { useEffect } from 'react';

let locks = 0;
let previousOverflow = '';

/** Shared by stacked dialogs: closing one panel must not unlock another. */
export function useBodyScrollLock(locked: boolean) {
    useEffect(() => {
        if (!locked) return;
        if (locks++ === 0) {
            previousOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
        }
        return () => {
            if (--locks === 0) document.body.style.overflow = previousOverflow;
        };
    }, [locked]);
}
