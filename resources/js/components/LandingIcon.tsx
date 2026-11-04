/** Consistent 24px outline icons for the public marketplace. */
export default function LandingIcon({ name, className = '' }: { name: string; className?: string }) {
    const paths: Record<string, React.ReactNode> = {
        arrow: <path d="M7 17 17 7M7 7h10v10" />,
        right: <path d="M4 12h16m-6-6 6 6-6 6" />,
        down: <path d="m7 7 10 10M7 17h10V7" />,
        search: <><circle cx="10.5" cy="10.5" r="6.5" /><path d="m16 16 4.5 4.5" /></>,
        shield: <><path d="m12 3 8 3v5c0 5-4 8-8 10-4-2-8-5-8-10V6l8-3Z" /><path d="m8.5 11.5 2.5 2.5 4.5-5" /></>,
        Sport: <><path d="M8 3h8v6a4 4 0 0 1-8 0V3Zm0 2H4v3a4 4 0 0 0 4 4m8-7h4v3a4 4 0 0 1-4 4M12 13v5m-5 3h10m-8-3h6v3" /></>,
        Politique: <><path d="m3 9 9-6 9 6H3Zm2 3v6m5-6v6m4-6v6m5-6v6M3 21h18M3 18h18" /></>,
        Musique: <><path d="M9 17V5l11-2v12M9 8l11-2" /><ellipse cx="6" cy="18" rx="3" ry="3" /><ellipse cx="17" cy="16" rx="3" ry="3" /></>,
        Économie: <><path d="M4 3v17h17M7 14l4-4 4 3 6-8m-5 0h5v5" /></>,
        Crypto: <><circle cx="12" cy="12" r="9" /><path d="M9 7h4a2.5 2.5 0 0 1 0 5H9m0 0h5a2.5 2.5 0 0 1 0 5H9V7Zm2-2v2m3-2v2m-3 10v2m3-2v2" /></>,
        Société: <><circle cx="9" cy="8" r="3" /><path d="M3 21v-3a6 6 0 0 1 12 0v3m1-16a3 3 0 0 1 0 6m2 4a5 5 0 0 1 3 4v2" /></>,
        featured: <><rect x="3" y="3" width="7" height="7" rx="1.5" /><rect x="14" y="3" width="7" height="7" rx="1.5" /><rect x="3" y="14" width="7" height="7" rx="1.5" /><rect x="14" y="14" width="7" height="7" rx="1.5" /></>,
        flag: <><path d="M5 21V4m0 0c5-4 9 4 14 0v10c-5 4-9-4-14 0" /></>,
        plus: <path d="M5 12h14M12 5v14" />,
        check: <path d="m5 12 4 4L19 6" />,
        close: <path d="m6 6 12 12M6 18 18 6" />,
    };
    return <svg className={`pa-icon ${className}`} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" focusable="false">{paths[name] || paths.featured}</svg>;
}
